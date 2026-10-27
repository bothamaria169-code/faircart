<?php
declare(strict_types=1);

require_once __DIR__ . '/MjpegAviWriter.php';

/**
 * A ~15-second vertical (720×1280, 24 fps) reel of one product, drawn with
 * nothing but GD: an opening card, each photo slowly zooming under the name and
 * price, and a closing card. Written as Motion-JPEG AVI; ImageKit turns it into
 * the MP4 that Instagram Reels and Facebook need.
 * Fonts: Big Shoulders and Work Sans (SIL Open Font License), lib/fonts.
 */
final class ProductReel
{
    private const W = 720;
    private const H = 1280;
    private const FPS = 24;
    private const PHOTO_SECONDS = 3.0;
    private const MAX_PHOTOS = 4;

    private const INK = '#0E1726';
    private const LEAF = '#12805C';
    private const AMBER = '#E9A21B';
    private const PAPER = '#F6F7F9';
    private const MUTED = '#B9C3D3';

    public static function ready(): bool
    {
        return function_exists('imagettftext') && function_exists('imagecreatefromjpeg') && is_file(self::fonts()['display']);
    }

    /** @return array{display:string,bold:string,regular:string} */
    private static function fonts(): array
    {
        $d = __DIR__ . '/../fonts/';
        return ['display' => $d . 'BigShoulders-Bold.ttf', 'bold' => $d . 'WorkSans-Bold.ttf', 'regular' => $d . 'WorkSans-Regular.ttf'];
    }

    /**
     * @param array $product row with name, brand_name, pack_size, price_label, was_label
     * @param list<string> $images site-relative image paths (JPEG/PNG/WebP)
     * @return string path of the finished .avi (delete it when done)
     */
    public function build(array $product, array $images): string
    {
        if (!self::ready()) {
            throw new RuntimeException('This server\'s PHP has no GD FreeType support, so reels cannot be drawn.');
        }
        @set_time_limit(600);
        $writer = new MjpegAviWriter(self::W, self::H, self::FPS, 60);
        $frame  = imagecreatetruecolor(self::W, self::H);

        $this->intro($frame, $product);
        $writer->addFrame($frame, (int) round(2.2 * self::FPS));

        $overlay = $this->overlay($product);
        $used = 0;
        foreach (array_slice($images, 0, self::MAX_PHOTOS) as $path) {
            $photo = $this->load($path);
            if (!$photo) {
                continue;
            }
            $n = (int) round(self::PHOTO_SECONDS * self::FPS);
            for ($i = 0; $i < $n; $i++) {
                $this->photoFrame($frame, $photo, $overlay, $i / max(1, $n - 1));
                $writer->addFrame($frame);
            }
            imagedestroy($photo);
            $used++;
        }
        imagedestroy($overlay);
        if ($used === 0) {
            $writer->discard();
            imagedestroy($frame);
            throw new RuntimeException('None of the product photos could be loaded.');
        }

        $this->outro($frame);
        $writer->addFrame($frame, (int) round(2.6 * self::FPS));
        imagedestroy($frame);
        return $writer->finish();
    }

    /** A JPEG still of the opening card and a photo frame, for the admin preview. */
    public function preview(array $product, array $images): array
    {
        $frame = imagecreatetruecolor(self::W, self::H);
        $out = [];
        $this->intro($frame, $product);
        $out[] = $this->jpeg($frame);
        if ($images && ($photo = $this->load($images[0]))) {
            $overlay = $this->overlay($product);
            $this->photoFrame($frame, $photo, $overlay, 0.4);
            $out[] = $this->jpeg($frame);
        }
        return $out;
    }

    private function load(string $path): ?GdImage
    {
        $file = realpath(__DIR__ . '/../../' . ltrim($path, '/'));
        if (!$file || !is_file($file)) {
            return null;
        }
        $img = @imagecreatefromstring((string) file_get_contents($file));
        if (!$img) {
            return null;
        }
        // Transparent PNGs go on white, not black.
        $flat = imagecreatetruecolor(imagesx($img), imagesy($img));
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
        imagedestroy($img);
        return $flat;
    }

    private function c(GdImage $im, string $hex, int $alpha = 0): int
    {
        [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%02x%02x%02x');
        return imagecolorallocatealpha($im, $r, $g, $b, $alpha);
    }

    /** Wrapped text; returns the y below it. */
    private function text(GdImage $im, string $text, string $font, float $size, int $x, int $y, int $width, int $colour, int $maxLines = 3, string $align = 'center'): int
    {
        $lines = [];
        $line  = '';
        foreach (preg_split('/\s+/', trim($text)) ?: [] as $word) {
            $try = $line === '' ? $word : "$line $word";
            $box = imagettfbbox($size, 0, $font, $try);
            if ($box[2] - $box[0] > $width && $line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] = rtrim($lines[$maxLines - 1], ' ,.-') . '…';
        }
        foreach ($lines as $l) {
            $box = imagettfbbox($size, 0, $font, $l);
            $tw  = $box[2] - $box[0];
            $left = match ($align) {
                'center' => $x + (int) (($width - $tw) / 2),
                'right'  => $x + $width - $tw,
                default  => $x,
            };
            $y += (int) round($size * 1.25);
            imagettftext($im, $size, 0, $left, $y, $colour, $font, $l);
        }
        return $y;
    }

    private function box(GdImage $im, int $x1, int $y1, int $x2, int $y2, int $r, int $colour): void
    {
        imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $colour);
        imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $colour);
        foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
            imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $colour);
        }
    }

    private function logo(GdImage $im, int $y, int $width, ?int $x = null): void
    {
        $logo = @imagecreatefrompng(__DIR__ . '/../../assets/img/logo/faircart-logo.png');
        if (!$logo) {
            return;
        }
        $h = (int) round(imagesy($logo) * $width / imagesx($logo));
        imagecopyresampled($im, $logo, $x ?? (int) ((self::W - $width) / 2), $y, 0, 0, $width, $h, imagesx($logo), imagesy($logo));
        imagedestroy($logo);
    }

    private function intro(GdImage $im, array $p): void
    {
        $f = self::fonts();
        imagefilledrectangle($im, 0, 0, self::W, self::H, $this->c($im, self::INK));
        imagefilledrectangle($im, 0, 0, self::W, 10, $this->c($im, self::LEAF));
        $this->box($im, 150, 180, 570, 330, 40, $this->c($im, '#FFFFFF'));
        $this->logo($im, 215, 340);
        $y = $this->text($im, mb_strtoupper((string) ($p['eyebrow'] ?? 'Now at FairCart')), $f['display'], 50, 40, 430, 640, $this->c($im, self::AMBER), 1);
        $y = $this->text($im, (string) $p['name'], $f['display'], 74, 40, $y + 30, 640, $this->c($im, '#FFFFFF'), 3);
        if (!empty($p['brand_name'])) {
            $y = $this->text($im, 'by ' . $p['brand_name'] . (!empty($p['pack_size']) ? ' · ' . $p['pack_size'] : ''), $f['regular'], 28, 40, $y + 20, 640, $this->c($im, self::MUTED), 2);
        }
        $this->box($im, 160, $y + 60, 560, $y + 220, 24, $this->c($im, self::LEAF));
        $this->text($im, (string) $p['price_label'], $f['display'], 78, 160, $y + 85, 400, $this->c($im, '#FFFFFF'), 1);
        if (!empty($p['was_label'])) {
            $this->text($im, 'was ' . $p['was_label'], $f['regular'], 26, 40, $y + 240, 640, $this->c($im, self::MUTED), 1);
        }
    }

    private function outro(GdImage $im): void
    {
        $f = self::fonts();
        imagefilledrectangle($im, 0, 0, self::W, self::H, $this->c($im, self::INK));
        $this->box($im, 150, 300, 570, 450, 40, $this->c($im, '#FFFFFF'));
        $this->logo($im, 335, 340);
        $y = $this->text($im, 'REAL PRODUCTS. FAIR PRICES.', $f['display'], 54, 30, 540, 660, $this->c($im, self::AMBER), 2);
        $y = $this->text($im, 'Shop online at', $f['regular'], 30, 40, $y + 50, 640, $this->c($im, '#FFFFFF'), 1);
        $y = $this->text($im, SITE_DOMAIN, $f['bold'], 46, 40, $y + 6, 640, $this->c($im, '#FFFFFF'), 1);
        $y = $this->text($im, 'Delivered to a Pudo locker near you', $f['regular'], 26, 40, $y + 50, 640, $this->c($im, self::MUTED), 2);
        if ($wa = get_setting('contact_whatsapp')) {
            $digits = preg_replace('/\D/', '', $wa) ?? '';
            $local  = str_starts_with($digits, '27') ? '0' . substr($digits, 2) : $digits;
            $this->text($im, 'WhatsApp ' . trim(chunk_split($local, 3, ' ')), $f['bold'], 34, 40, $y + 40, 640, $this->c($im, self::AMBER), 1);
        }
    }

    /** Top band (logo) and bottom band (name, price, link) laid over every photo. */
    private function overlay(array $p): GdImage
    {
        $f  = self::fonts();
        $im = imagecreatetruecolor(self::W, self::H);
        $ink = $this->c($im, self::INK);
        imagefilledrectangle($im, 0, 0, self::W, 150, $ink);
        $this->box($im, 30, 40, 290, 115, 30, $this->c($im, '#FFFFFF'));
        $this->logo($im, 55, 200, 60);
        $this->text($im, 'faircart.co.za', $f['bold'], 26, 330, 50, 360, $this->c($im, self::MUTED), 1, 'right');

        imagefilledrectangle($im, 0, 960, self::W, self::H, $ink);
        imagefilledrectangle($im, 0, 960, self::W, 966, $this->c($im, self::LEAF));
        $y = $this->text($im, (string) $p['name'], $f['display'], 52, 40, 975, 640, $this->c($im, '#FFFFFF'), 2, 'left');
        $this->box($im, 40, $y + 25, 400, $y + 125, 18, $this->c($im, self::LEAF));
        $this->text($im, (string) $p['price_label'], $f['display'], 56, 40, $y + 32, 360, $this->c($im, '#FFFFFF'), 1);
        $this->text($im, 'Order online · Pudo delivery', $f['regular'], 22, 420, $y + 50, 280, $this->c($im, self::MUTED), 2, 'left');
        return $im;
    }

    /** The photo fitted into the middle band, zooming in slowly over $t (0–1). */
    private function photoFrame(GdImage $frame, GdImage $photo, GdImage $overlay, float $t): void
    {
        imagefilledrectangle($frame, 0, 150, self::W, 960, $this->c($frame, '#FFFFFF'));
        $areaW = self::W;
        $areaH = 810;
        $sw = imagesx($photo);
        $sh = imagesy($photo);
        $scale = min($areaW / $sw, $areaH / $sh) * (0.92 + 0.10 * $t);
        $w = (int) round($sw * $scale);
        $h = (int) round($sh * $scale);
        imagecopyresampled($frame, $photo, (int) (($areaW - $w) / 2), 150 + (int) (($areaH - $h) / 2), 0, 0, $w, $h, $sw, $sh);
        imagecopy($frame, $overlay, 0, 0, 0, 0, self::W, 150);
        imagecopy($frame, $overlay, 0, 960, 0, 960, self::W, self::H - 960);
    }

    private function jpeg(GdImage $im): string
    {
        ob_start();
        imagejpeg($im, null, 75);
        return (string) ob_get_clean();
    }
}
