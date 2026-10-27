<?php
declare(strict_types=1);

/**
 * Posts products to the FairCart Facebook Page and its linked Instagram
 * account through Meta's Graph API — photo posts through the day and product
 * reels at set times — from scripts/cron.php. Ported from EasyAuctions'
 * SocialPoster/VideoPoster. Setup and the step-by-step Meta guide live on
 * Admin → Social posting.
 *
 * The catalogue is small, so it cycles: the product posted longest ago (or
 * never) goes next, and nothing repeats within `social_repeat_days`.
 */
final class SocialPoster
{
    public const GRAPH = 'https://graph.facebook.com/v23.0';

    public const PLATFORMS = [
        'facebook'  => 'Facebook Page',
        'instagram' => 'Instagram',
        'fb_reel'   => 'Facebook video',
        'ig_reel'   => 'Instagram Reels',
    ];

    /** What "Connect Facebook" asks for. App admins get these without Meta App Review. */
    public const SCOPES = ['pages_show_list', 'pages_read_engagement', 'pages_manage_posts', 'business_management',
        'instagram_basic', 'instagram_content_publish'];

    public static function config(): array
    {
        $times = array_values(array_filter(array_map('trim', explode(',', (string) get_setting('reel_times', '11:00,17:30'))),
            fn($t) => (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t)));
        return [
            'app_id'      => trim((string) get_setting('meta_app_id', '')),
            'app_secret'  => secret_get('meta_app_secret'),
            'page_id'     => (string) get_setting('meta_page_id', ''),
            'page_name'   => (string) get_setting('meta_page_name', ''),
            'token'       => secret_get('meta_page_token'),
            'ig_id'       => (string) get_setting('meta_ig_id', ''),
            'ig_username' => (string) get_setting('meta_ig_username', ''),
            'auto'        => get_setting('social_auto', '0') === '1',
            'per_day'     => max(1, min(24, (int) get_setting('social_per_day', '3'))),
            'start'       => max(0, min(23, (int) get_setting('social_start_hour', '8'))),
            'end'         => max(1, min(24, (int) get_setting('social_end_hour', '19'))),
            'facebook'    => get_setting('social_use_facebook', '1') === '1',
            'instagram'   => get_setting('social_use_instagram', '1') === '1',
            'repeat_days' => max(1, (int) get_setting('social_repeat_days', '10')),
            'ai'          => get_setting('social_ai_captions', '0') === '1',
            'reel_auto'   => get_setting('reel_auto', '0') === '1',
            'fb_reel'     => get_setting('reel_use_facebook', '1') === '1',
            'ig_reel'     => get_setting('reel_use_instagram', '1') === '1',
            'reel_times'  => $times ?: ['11:00', '17:30'],
        ];
    }

    public static function connected(string $platform): bool
    {
        $c = self::config();
        return match ($platform) {
            'facebook', 'fb_reel'   => $c['page_id'] !== '' && $c['token'] !== '',
            'instagram', 'ig_reel'  => $c['ig_id'] !== '' && $c['token'] !== '',
            default => false,
        };
    }

    // ── Connecting the Page (OAuth) ──────────────────────────────────────

    public static function callbackUrl(): string
    {
        return absolute_url('dashboards/admin/social-callback.php');
    }

    public static function loginUrl(string $state): string
    {
        $c = self::config();
        $q = ['client_id' => $c['app_id'], 'redirect_uri' => self::callbackUrl(), 'state' => $state, 'response_type' => 'code'];
        $config = trim((string) get_setting('meta_config_id', ''));
        // Facebook Login for Business apps take a saved configuration instead of a scope list.
        $q += $config !== '' ? ['config_id' => $config, 'override_default_response_type' => 'true'] : ['scope' => implode(',', self::SCOPES)];
        return 'https://www.facebook.com/v23.0/dialog/oauth?' . http_build_query($q);
    }

    /** Swaps the login code for never-expiring Page tokens. Returns the Pages the user manages. */
    public static function pagesFromCode(string $code): array
    {
        $c = self::config();
        $short = self::call('GET', 'oauth/access_token', ['client_id' => $c['app_id'], 'client_secret' => $c['app_secret'], 'redirect_uri' => self::callbackUrl(), 'code' => $code])['access_token'] ?? '';
        // A long-lived user token makes the Page tokens it hands out never expire.
        $long = self::call('GET', 'oauth/access_token', ['grant_type' => 'fb_exchange_token', 'client_id' => $c['app_id'], 'client_secret' => $c['app_secret'], 'fb_exchange_token' => $short])['access_token'] ?? $short;
        return self::call('GET', 'me/accounts', ['fields' => 'id,name,access_token,instagram_business_account{id,username}', 'limit' => 50, 'access_token' => $long])['data'] ?? [];
    }

    public static function savePage(array $page): void
    {
        set_setting('meta_page_id', (string) $page['id']);
        set_setting('meta_page_name', (string) ($page['name'] ?? ''));
        secret_set('meta_page_token', (string) $page['access_token']);
        set_setting('meta_ig_id', (string) ($page['instagram_business_account']['id'] ?? ''));
        set_setting('meta_ig_username', (string) ($page['instagram_business_account']['username'] ?? ''));
    }

    public static function disconnect(): void
    {
        foreach (['meta_page_id', 'meta_page_name', 'meta_ig_id', 'meta_ig_username'] as $k) {
            set_setting($k, '');
        }
        secret_set('meta_page_token', '');
        set_setting('social_auto', '0');
        set_setting('reel_auto', '0');
    }

    /** What Facebook says about the saved Page token. */
    public static function diagnose(): array
    {
        $c = self::config();
        $out = [];
        try {
            $d = self::call('GET', 'debug_token', ['input_token' => $c['token'], 'access_token' => $c['app_id'] . '|' . $c['app_secret']])['data'] ?? [];
            $out['Token valid'] = !empty($d['is_valid']) ? 'yes' : 'NO';
            $out['Expires']     = empty($d['expires_at']) ? 'never' : date('j M Y H:i', (int) $d['expires_at']);
            $out['Permissions'] = implode(', ', $d['scopes'] ?? []) ?: 'none';
        } catch (Throwable $e) {
            $out['Token check'] = $e->getMessage();
        }
        try {
            $p = self::call('GET', $c['page_id'], ['fields' => 'id,name,instagram_business_account{username}', 'access_token' => $c['token']]);
            $out['Page'] = ($p['name'] ?? '?') . ' (' . ($p['id'] ?? '') . ')';
            $out['Instagram on the Page'] = $p['instagram_business_account']['username'] ?? 'none linked';
        } catch (Throwable $e) {
            $out['Page check'] = $e->getMessage();
        }
        return $out;
    }

    public static function call(string $method, string $path, array $fields): array
    {
        $url = self::GRAPH . '/' . ltrim($path, '/');
        $r = $method === 'GET'
            ? http_request('GET', $url, ['query' => $fields, 'timeout' => 40])
            : http_request('POST', $url, ['form' => $fields, 'timeout' => 90]);
        $j = is_array($r['json']) ? $r['json'] : [];
        // Never echo the request URL: it carries the token.
        $where = preg_replace('/^\d+\//', '{page}/', $path);
        if ($r['error'] !== '') {
            throw new RuntimeException("Could not reach Facebook ($where): " . $r['error']);
        }
        if ($r['status'] >= 400 || isset($j['error'])) {
            $e = $j['error'] ?? [];
            throw new RuntimeException("Meta ($where): " . ($e['message'] ?? 'HTTP ' . $r['status']) . (isset($e['error_user_msg']) ? ' — ' . $e['error_user_msg'] : ''));
        }
        return $j;
    }

    // ── What to post ─────────────────────────────────────────────────────

    /** Next product for $platform: in stock, not posted there recently, longest-ago first. */
    public static function next(string $platform): ?array
    {
        $c = self::config();
        $st = db()->prepare(
            "SELECT p.*, b.name AS brand_name,
                    (SELECT MAX(created_at) FROM social_posts s WHERE s.product_id = p.id AND s.platform = :pl AND s.status = 'posted') AS last_posted,
                    (SELECT COUNT(*) FROM social_posts s WHERE s.product_id = p.id AND s.platform = :pl2 AND s.status = 'failed'
                       AND s.created_at > NOW() - INTERVAL 7 DAY) AS recent_fails
             FROM products p LEFT JOIN brands b ON b.id = p.brand_id
             WHERE p.status = 'published' AND p.stock_status <> 'out_of_stock'
             HAVING (last_posted IS NULL OR last_posted < NOW() - INTERVAL :days DAY) AND recent_fails < 3
             ORDER BY last_posted IS NOT NULL, last_posted, p.is_bestseller DESC, p.sold_count DESC
             LIMIT 1"
        );
        $st->execute([':pl' => $platform, ':pl2' => $platform, ':days' => $c['repeat_days']]);
        return $st->fetch() ?: null;
    }

    public static function productUrl(array $p, string $platform): string
    {
        return SITE_URL . '/modules/shop/product.php?slug=' . urlencode((string) $p['slug'])
            . '&utm_source=' . $platform . '&utm_medium=social&utm_campaign=auto_post';
    }

    /** Site-relative image paths for a product, JPEG/PNG only (SVG art swapped for its JPEG copy). */
    public static function images(array $p, int $max = 4): array
    {
        $paths = [(string) $p['featured_image']];
        $g = db()->prepare('SELECT image FROM product_gallery WHERE product_id = :id ORDER BY sort_order, id');
        $g->execute([':id' => (int) $p['id']]);
        $paths = array_merge($paths, $g->fetchAll(PDO::FETCH_COLUMN));
        $out = [];
        foreach ($paths as $path) {
            $path = ltrim(trim((string) $path), '/');
            if ($path === '' || preg_match('#^https?://#', $path)) {
                continue;
            }
            if (str_ends_with(strtolower($path), '.svg')) {
                $path = 'assets/img/products/email/' . pathinfo($path, PATHINFO_FILENAME) . '.jpg';
            }
            if (is_file(__DIR__ . '/../' . $path) && !in_array($path, $out, true)) {
                $out[] = $path;
            }
        }
        return array_slice($out, 0, $max);
    }

    /** Public JPEG URLs for Meta: via ImageKit when set up, else straight off the site. */
    public static function publicPhotos(array $p, int $max): array
    {
        $urls = [];
        foreach (self::images($p, $max) as $path) {
            $urls[] = ImageKit::configured() ? ImageKit::squareJpeg(ImageKit::urlFor($path)) : SITE_URL . '/' . $path;
        }
        return $urls;
    }

    public static function priceLabels(array $p): array
    {
        $now = Pricing::retail($p);
        $was = (float) $p['retail_price'];
        return [format_price_short($now), $now < $was ? format_price_short($was) : ''];
    }

    public static function caption(array $p, string $platform): string
    {
        [$price, $was] = self::priceLabels($p);
        $ig    = in_array($platform, ['instagram', 'ig_reel'], true);
        $intro = '';
        if (self::config()['ai'] && GroqWriter::configured()) {
            try {
                $intro = GroqWriter::caption($p, $price, $ig ? 'Instagram' : 'Facebook');
            } catch (Throwable $e) {
                error_log('AI caption failed: ' . $e->getMessage());
            }
        }
        if ($intro === '') {
            $intro = excerpt((string) ($p['short_desc'] ?: $p['description']), 180);
        }
        $brandTag = preg_replace('/[^A-Za-z0-9]/', '', (string) $p['brand_name']);
        $lines = [
            '✨ ' . $p['name'] . ($p['pack_size'] ? ' (' . $p['pack_size'] . ')' : ''),
            '',
            $intro,
            '',
            '💰 ' . $price . ($was !== '' ? ' (was ' . $was . ')' : ''),
            '📦 Delivered to a Pudo locker near you, or door to door with The Courier Guy',
            '🤝 Resell it and earn: ask about the FairCart reseller programme',
            '',
            $ig ? '🛒 Shop: ' . SITE_DOMAIN . ' · link in bio' : '🛒 Order now: ' . self::productUrl($p, $platform),
            '',
            '#FairCart #SouthAfrica #ShopLocal' . ($brandTag ? ' #' . $brandTag : '') . ' #Mzansi',
        ];
        // Supplements: the SAHPRA line travels with every post, as on the site.
        if (preg_match('/blossom/i', (string) $p['brand_name'])) {
            $lines[] = '';
            $lines[] = 'Complementary medicine — not evaluated by SAHPRA.';
        }
        return implode("\n", $lines);
    }

    // ── Posting ──────────────────────────────────────────────────────────

    private static function log(?int $productId, string $platform, bool $ok, string $remoteId, string $caption, string $error = ''): void
    {
        db_insert('social_posts', [
            'product_id' => $productId, 'platform' => $platform, 'status' => $ok ? 'posted' : 'failed',
            'remote_id'  => $remoteId ?: null, 'caption' => $caption, 'error' => $error !== '' ? mb_substr($error, 0, 500) : null,
        ]);
    }

    /** Posts a product's photos to one platform. Returns a one-line result. */
    public static function postPhotos(array $p, string $platform): string
    {
        $caption = self::caption($p, $platform);
        try {
            if (!self::connected($platform)) {
                throw new RuntimeException(self::PLATFORMS[$platform] . ' is not connected.');
            }
            $photos = self::publicPhotos($p, $platform === 'instagram' ? 5 : 4);
            if (!$photos) {
                throw new RuntimeException('This product has no usable photos.');
            }
            $id = $platform === 'facebook' ? self::facebookPhotos($photos, $caption) : self::instagramPhotos($photos, $caption);
            self::log((int) $p['id'], $platform, true, $id, $caption);
            return self::PLATFORMS[$platform] . ': posted ' . $p['name'];
        } catch (Throwable $e) {
            self::log((int) $p['id'], $platform, false, '', $caption, $e->getMessage());
            return self::PLATFORMS[$platform] . ': failed — ' . $e->getMessage();
        }
    }

    private static function facebookPhotos(array $photos, string $caption): string
    {
        $c = self::config();
        if (count($photos) === 1) {
            $r = self::call('POST', $c['page_id'] . '/photos', ['url' => $photos[0], 'caption' => $caption, 'access_token' => $c['token']]);
            return (string) ($r['post_id'] ?? $r['id'] ?? '');
        }
        // Several photos in one post: upload each unpublished, then publish together.
        $fields = ['message' => $caption, 'access_token' => $c['token']];
        $i = 0;
        foreach ($photos as $url) {
            $up = self::call('POST', $c['page_id'] . '/photos', ['url' => $url, 'published' => 'false', 'access_token' => $c['token']]);
            if (!empty($up['id'])) {
                $fields['attached_media[' . $i++ . ']'] = json_encode(['media_fbid' => $up['id']]);
            }
        }
        if ($i === 0) {
            throw new RuntimeException('Facebook would not accept the photos.');
        }
        return (string) (self::call('POST', $c['page_id'] . '/feed', $fields)['id'] ?? '');
    }

    private static function instagramPhotos(array $photos, string $caption): string
    {
        $c = self::config();
        if (count($photos) === 1) {
            $container = self::call('POST', $c['ig_id'] . '/media', ['image_url' => $photos[0], 'caption' => $caption, 'access_token' => $c['token']]);
        } else {
            $children = [];
            foreach ($photos as $url) {
                $item = self::call('POST', $c['ig_id'] . '/media', ['image_url' => $url, 'is_carousel_item' => 'true', 'access_token' => $c['token']]);
                if (!empty($item['id'])) {
                    $children[] = $item['id'];
                }
            }
            if (!$children) {
                throw new RuntimeException('Instagram would not accept the photos.');
            }
            $container = self::call('POST', $c['ig_id'] . '/media', ['media_type' => 'CAROUSEL', 'children' => implode(',', $children), 'caption' => $caption, 'access_token' => $c['token']]);
        }
        return self::publishInstagram((string) ($container['id'] ?? ''), 10, 3);
    }

    /** Waits for Instagram to finish processing a container, then publishes it. */
    private static function publishInstagram(string $cid, int $tries, int $wait): string
    {
        if ($cid === '') {
            throw new RuntimeException('Instagram did not create the post.');
        }
        $c = self::config();
        for ($i = 0; $i < $tries; $i++) {
            $s = self::call('GET', $cid, ['fields' => 'status_code,status', 'access_token' => $c['token']]);
            $code = (string) ($s['status_code'] ?? '');
            if ($code === 'FINISHED') {
                return (string) (self::call('POST', $c['ig_id'] . '/media_publish', ['creation_id' => $cid, 'access_token' => $c['token']])['id'] ?? '');
            }
            if ($code === 'ERROR' || $code === 'EXPIRED') {
                throw new RuntimeException('Instagram could not process it: ' . ($s['status'] ?? $code));
            }
            sleep($wait);
        }
        throw new RuntimeException('Instagram was still processing after ' . ($tries * $wait) . ' seconds.');
    }

    /** Makes a reel of the product and posts it to the reel platforms given. */
    public static function postReel(array $p, array $platforms): array
    {
        @set_time_limit(900);
        require_once __DIR__ . '/../lib/Video/ProductReel.php';
        $lines = [];
        $file  = null;
        try {
            if (!ImageKit::configured()) {
                throw new RuntimeException('Reels need ImageKit (it converts the video to MP4). Add the key under Integrations.');
            }
            [$price, $was] = self::priceLabels($p);
            $file = (new ProductReel())->build($p + ['price_label' => $price, 'was_label' => $was], self::images($p, 4));
            $mp4  = ImageKit::videoToMp4($file);
        } catch (Throwable $e) {
            foreach ($platforms as $pl) {
                self::log((int) $p['id'], $pl, false, '', '', $e->getMessage());
                $lines[] = self::PLATFORMS[$pl] . ': failed — ' . $e->getMessage();
            }
            if ($file) {
                @unlink($file);
            }
            return $lines;
        }
        @unlink($file);
        $c = self::config();
        foreach ($platforms as $pl) {
            $caption = self::caption($p, $pl);
            try {
                if (!self::connected($pl)) {
                    throw new RuntimeException(self::PLATFORMS[$pl] . ' is not connected.');
                }
                if ($pl === 'ig_reel') {
                    $container = self::call('POST', $c['ig_id'] . '/media', ['media_type' => 'REELS', 'video_url' => $mp4,
                        'caption' => mb_substr($caption, 0, 2100), 'share_to_feed' => 'true', 'access_token' => $c['token']]);
                    $id = self::publishInstagram((string) ($container['id'] ?? ''), 36, 5);
                } else {
                    $id = (string) (self::call('POST', $c['page_id'] . '/videos', ['file_url' => $mp4, 'description' => $caption, 'access_token' => $c['token']])['id'] ?? '');
                }
                self::log((int) $p['id'], $pl, true, $id, $caption);
                $lines[] = self::PLATFORMS[$pl] . ': posted reel of ' . $p['name'];
            } catch (Throwable $e) {
                self::log((int) $p['id'], $pl, false, '', $caption, $e->getMessage());
                $lines[] = self::PLATFORMS[$pl] . ': failed — ' . $e->getMessage();
            }
        }
        ImageKit::cleanupReels();
        return $lines;
    }

    // ── The schedule (scripts/cron.php, every 5–10 minutes) ──────────────

    public static function runScheduled(): array
    {
        $c = self::config();
        $done = [];
        $now  = new DateTimeImmutable('now', new DateTimeZone('Africa/Johannesburg'));
        $hour = (int) $now->format('G');

        // Photo posts, spaced evenly through the posting window.
        if ($c['auto'] && $hour >= $c['start'] && $hour < $c['end']) {
            $gap = (int) floor(max(1, $c['end'] - $c['start']) * 60 / $c['per_day']);
            foreach (['facebook', 'instagram'] as $pl) {
                if (!$c[$pl] || !self::connected($pl)) {
                    continue;
                }
                $st = db()->prepare("SELECT COUNT(*) AS today, MAX(created_at) AS last FROM social_posts WHERE platform = :p AND created_at >= CURDATE()");
                $st->execute([':p' => $pl]);
                $r = $st->fetch();
                if ((int) $r['today'] >= $c['per_day'] || ($r['last'] && strtotime((string) $r['last']) > time() - $gap * 60)) {
                    continue;
                }
                $p = self::next($pl);
                $done[] = $p ? self::postPhotos($p, $pl) : self::PLATFORMS[$pl] . ': every product was posted recently.';
            }
        }

        // Reels: one product at each reel time (within the hour after it), once a day per slot.
        $queued = json_decode((string) get_setting('reel_queue', '[]'), true) ?: [];
        $slot   = null;
        if (!$queued && $c['reel_auto']) {
            $doneSlots = json_decode((string) get_setting('reel_slots_done', '{}'), true) ?: [];
            foreach (array_reverse($c['reel_times']) as $t) {
                $at = $now->setTime((int) substr($t, 0, 2), (int) substr($t, 3, 2));
                if ($now >= $at && $now->getTimestamp() - $at->getTimestamp() <= 3600 && ($doneSlots[$t] ?? '') !== $now->format('Y-m-d')) {
                    $slot = $t;
                    // Claimed first, so an overlapping run cannot post the same slot twice.
                    $doneSlots[$t] = $now->format('Y-m-d');
                    set_setting('reel_slots_done', json_encode($doneSlots));
                    break;
                }
            }
        }
        if ($queued || $slot) {
            $targets = array_values(array_filter(['ig_reel', 'fb_reel'], fn($pl) => $c[$pl] && self::connected($pl)));
            if ($targets) {
                $p = null;
                if ($queued) {
                    $id = (int) array_shift($queued);
                    set_setting('reel_queue', json_encode($queued));
                    $p = (new ProductRepository())->getById($id);
                } else {
                    $p = self::next($targets[0]);
                }
                $done = array_merge($done, $p ? self::postReel($p, $targets) : ['Reels: nothing to post.']);
            }
        }
        return $done ?: ['Social: nothing due.'];
    }

    public static function stats(): array
    {
        $out = [];
        foreach (array_keys(self::PLATFORMS) as $pl) {
            $st = db()->prepare("SELECT SUM(status='posted') AS posted, SUM(status='posted' AND created_at >= CURDATE()) AS today,
                                        SUM(status='failed' AND created_at > NOW() - INTERVAL 1 DAY) AS failed
                                 FROM social_posts WHERE platform = :p");
            $st->execute([':p' => $pl]);
            $out[$pl] = array_map('intval', $st->fetch() ?: []);
        }
        return $out;
    }
}
