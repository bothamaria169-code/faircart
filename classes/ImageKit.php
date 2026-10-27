<?php
declare(strict_types=1);

/**
 * ImageKit: hosts product photos and reel videos on a CDN for the social
 * poster. Instagram only accepts public JPEG URLs and Reels only accept MP4,
 * so photos go up once (remembered in imagekit_files) and are fetched back as
 * a 1080×1080 JPEG; reels go up as AVI and come back as ImageKit's MP4.
 *
 * Keys: imagekit.io → Developer options → API keys (private key + URL endpoint).
 */
final class ImageKit
{
    private const UPLOAD = 'https://upload.imagekit.io/api/v1/files/upload';
    private const API    = 'https://api.imagekit.io/v1/files';

    public static function privateKey(): string
    {
        return secret_get('imagekit_private_key');
    }

    public static function configured(): bool
    {
        return self::privateKey() !== '';
    }

    public static function folder(): string
    {
        return '/' . trim((string) get_setting('imagekit_folder', 'faircart'), '/');
    }

    /** @return array{url:string,fileId:string} */
    public static function uploadFile(string $path, string $fileName, string $subFolder = '', bool $unique = true): array
    {
        if (!self::configured()) {
            throw new RuntimeException('ImageKit is not set up in Admin → Integrations.');
        }
        if (!is_file($path)) {
            throw new RuntimeException('File not found: ' . basename($path));
        }
        $r = http_request('POST', self::UPLOAD, [
            'auth'      => [self::privateKey(), ''],
            'multipart' => [
                'file'              => new CURLFile($path, null, $fileName),
                'fileName'          => $fileName,
                'folder'            => self::folder() . ($subFolder !== '' ? '/' . trim($subFolder, '/') : ''),
                'useUniqueFileName' => $unique ? 'true' : 'false',
            ],
            'timeout' => 300,
        ]);
        if (empty($r['json']['url'])) {
            throw new RuntimeException('ImageKit upload failed: ' . ($r['json']['message'] ?? ($r['error'] ?: 'HTTP ' . $r['status'])));
        }
        return ['url' => (string) $r['json']['url'], 'fileId' => (string) ($r['json']['fileId'] ?? '')];
    }

    /**
     * A site image (e.g. "assets/img/products/x.jpg") on ImageKit — uploaded the
     * first time, from the cache after. Remote URLs are returned untouched.
     */
    public static function urlFor(string $sitePath): string
    {
        if (preg_match('#^https?://#', $sitePath)) {
            return $sitePath;
        }
        $rel  = ltrim($sitePath, '/');
        $file = realpath(__DIR__ . '/../' . $rel);
        if (!$file || !str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', realpath(__DIR__ . '/..')))) {
            throw new RuntimeException('Image not found: ' . $rel);
        }
        $hash = md5($rel . '|' . filesize($file) . '|' . filemtime($file));
        $st = db()->prepare('SELECT url FROM imagekit_files WHERE local_hash = :h');
        $st->execute([':h' => $hash]);
        if ($url = $st->fetchColumn()) {
            return (string) $url;
        }
        $up = self::uploadFile($file, preg_replace('/[^A-Za-z0-9._-]+/', '-', basename($file)) ?: 'image.jpg', 'products');
        db()->prepare('INSERT INTO imagekit_files (local_path, local_hash, url, file_id) VALUES (:p, :h, :u, :f)
                       ON DUPLICATE KEY UPDATE url = :u2, file_id = :f2')
            ->execute([':p' => $rel, ':h' => $hash, ':u' => $up['url'], ':f' => $up['fileId'], ':u2' => $up['url'], ':f2' => $up['fileId']]);
        return $up['url'];
    }

    /** Square white-padded JPEG — what Instagram wants. */
    public static function squareJpeg(string $url, int $size = 1080): string
    {
        return explode('?', $url)[0] . "?tr=w-$size,h-$size,cm-pad_resize,bg-FFFFFF,q-85,f-jpg";
    }

    /** Uploads a video and waits until ImageKit's MP4 of it is ready. Returns the MP4 URL. */
    public static function videoToMp4(string $file): string
    {
        $up  = self::uploadFile($file, 'reel-' . date('Ymd-His') . '.avi', 'reels');
        $mp4 = $up['url'] . '/ik-video.mp4';
        // The first request starts the conversion; wait for a real MP4 to come back.
        for ($i = 0; $i < 36; $i++) {
            $h = http_request('HEAD', $mp4, ['timeout' => 30]);
            if ($h['status'] === 200 && str_starts_with((string) ($h['headers']['content-type'] ?? ''), 'video/mp4')) {
                return $mp4;
            }
            sleep(5);
        }
        throw new RuntimeException('ImageKit did not finish converting the reel in time; it will be retried.');
    }

    /** Deletes reels older than three days (Meta keeps its own copy). */
    public static function cleanupReels(): void
    {
        if (!self::configured()) {
            return;
        }
        $r = http_request('GET', self::API, ['auth' => [self::privateKey(), ''], 'query' => ['path' => self::folder() . '/reels', 'limit' => 100]]);
        foreach (is_array($r['json']) ? $r['json'] : [] as $f) {
            if (!empty($f['fileId']) && strtotime((string) ($f['createdAt'] ?? 'now')) < time() - 3 * 86400) {
                http_request('DELETE', self::API . '/' . rawurlencode((string) $f['fileId']), ['auth' => [self::privateKey(), '']]);
            }
        }
    }

    /** Lists one file to prove the key works. */
    public static function test(): string
    {
        $r = http_request('GET', self::API, ['auth' => [self::privateKey(), ''], 'query' => ['limit' => 1]]);
        if ($r['status'] === 200) {
            return 'Connected to ImageKit.';
        }
        throw new RuntimeException('ImageKit refused the key: ' . ($r['json']['message'] ?? 'HTTP ' . $r['status']));
    }
}
