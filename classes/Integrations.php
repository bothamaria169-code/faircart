<?php
declare(strict_types=1);

/**
 * Shared plumbing for the third-party services: encrypted API keys in the
 * settings table, one cURL helper, and the schema upgrades the integrations
 * need (run automatically, like NewsletterService::ensureSchema(), because
 * production has no shell and manual SQL imports have been missed before).
 */

// ── Encrypted settings ───────────────────────────────────────────────────
// API keys are typed into Admin → Integrations and stored AES-256-GCM
// encrypted, so a database export alone never leaks them. The key derives from
// JWT_SECRET (itself derived from the DB password in config/secrets.php).

function secret_key(): string
{
    return hash('sha256', 'faircart-secrets|' . JWT_SECRET, true);
}

function secret_set(string $key, string $value): void
{
    if ($value === '') {
        set_setting($key, '');
        return;
    }
    $iv  = random_bytes(12);
    $tag = '';
    $enc = openssl_encrypt($value, 'aes-256-gcm', secret_key(), OPENSSL_RAW_DATA, $iv, $tag);
    set_setting($key, 'enc:' . base64_encode($iv . $tag . $enc));
}

function secret_get(string $key): string
{
    $raw = (string) get_setting($key, '');
    if (!str_starts_with($raw, 'enc:')) {
        return $raw;
    }
    $bin = base64_decode(substr($raw, 4), true);
    if ($bin === false || strlen($bin) < 29) {
        return '';
    }
    $out = openssl_decrypt(substr($bin, 28), 'aes-256-gcm', secret_key(), OPENSSL_RAW_DATA, substr($bin, 0, 12), substr($bin, 12, 16));
    return $out === false ? '' : $out;
}

/** "sk_live_abc…xyz" -> "••••xyz": proof a key is saved without showing it. */
function secret_hint(string $key): string
{
    $v = secret_get($key);
    return $v === '' ? '' : '••••' . substr($v, -4);
}

// ── HTTP ─────────────────────────────────────────────────────────────────

/**
 * @param array{headers?:array<string,string>,json?:mixed,form?:array,body?:string,query?:array,timeout?:int,auth?:array{0:string,1:string},multipart?:array} $o
 * @return array{status:int,body:string,json:mixed,error:string,headers:array<string,string>}
 */
function http_request(string $method, string $url, array $o = []): array
{
    if (!empty($o['query'])) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($o['query']);
    }
    $headers = $o['headers'] ?? [];
    $ch = curl_init($url);
    $opts = [
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $o['timeout'] ?? 30,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'FairCart/1.0 (+https://faircart.co.za)',
    ];
    if (array_key_exists('json', $o)) {
        $opts[CURLOPT_POSTFIELDS] = is_string($o['json']) ? $o['json'] : json_encode($o['json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers['Content-Type'] ??= 'application/json';
        $headers['Accept'] ??= 'application/json';
    } elseif (isset($o['form'])) {
        $opts[CURLOPT_POSTFIELDS] = http_build_query($o['form']);
    } elseif (isset($o['multipart'])) {
        $opts[CURLOPT_POSTFIELDS] = $o['multipart'];
    } elseif (isset($o['body'])) {
        $opts[CURLOPT_POSTFIELDS] = $o['body'];
    }
    if (isset($o['auth'])) {
        $opts[CURLOPT_USERPWD] = $o['auth'][0] . ':' . $o['auth'][1];
    }
    if (strtoupper($method) === 'HEAD') {
        $opts[CURLOPT_NOBODY] = true;
    }
    $opts[CURLOPT_HTTPHEADER] = array_map(fn($k, $v) => "$k: $v", array_keys($headers), $headers);
    $respHeaders = [];
    $opts[CURLOPT_HEADERFUNCTION] = function ($ch, $line) use (&$respHeaders) {
        if (str_contains($line, ':')) {
            [$k, $v] = explode(':', $line, 2);
            $respHeaders[strtolower(trim($k))] = trim($v);
        }
        return strlen($line);
    };
    curl_setopt_array($ch, $opts);
    $body   = curl_exec($ch);
    $error  = $body === false ? curl_error($ch) : '';
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $body = $body === false ? '' : (string) $body;

    return ['status' => $status, 'body' => $body, 'json' => json_decode($body, true), 'error' => $error, 'headers' => $respHeaders];
}

/** Public URL of a site path, for webhooks and links that leave the site. */
function absolute_url(string $path): string
{
    return SITE_URL . '/' . ltrim($path, '/');
}

// ── Mail settings ────────────────────────────────────────────────────────

/** SMTP settings: Admin → Integrations → Email first, config/secrets.php after. */
function mail_config(): array
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    $c = [
        'host'   => (string) get_setting('smtp_host', SMTP_HOST),
        'port'   => (int) get_setting('smtp_port', (string) SMTP_PORT),
        'secure' => (string) get_setting('smtp_secure', SMTP_SECURE),
        'user'   => (string) get_setting('smtp_user', SMTP_USER),
        'pass'   => secret_get('smtp_pass') ?: SMTP_PASS,
        'from'   => (string) get_setting('smtp_from_name', SMTP_FROM_NAME),
        'admin'  => (string) get_setting('admin_alert_email', ADMIN_EMAIL),
    ];
    return $c;
}

// ── Schema upgrades for the integrations ─────────────────────────────────

final class AppSchema
{
    private const VERSION = 1;

    public static function ensure(): void
    {
        static $done = false;
        if ($done || (int) get_setting('app_schema', '0') >= self::VERSION) {
            $done = true;
            return;
        }
        $done = true;
        try {
            self::migrate();
            set_setting('app_schema', (string) self::VERSION);
        } catch (Throwable $e) {
            error_log('FairCart schema upgrade failed: ' . $e->getMessage());
        }
    }

    private static function migrate(): void
    {
        $pdo = db();
        NewsletterService::ensureSchema();

        // New payment and delivery options.
        $pdo->exec("ALTER TABLE `orders` MODIFY `payment_method` ENUM('eft','payfast','cash','ikhokha') NOT NULL DEFAULT 'eft'");
        $pdo->exec("ALTER TABLE `orders` MODIFY `fulfilment` ENUM('delivery','collection','runner','pudo') NOT NULL DEFAULT 'delivery'");
        $pdo->exec("ALTER TABLE `newsletter_campaigns` MODIFY `status` ENUM('draft','scheduled','sending','sent','failed') NOT NULL DEFAULT 'draft'");

        self::addColumns([
            'orders' => [
                'pudo_locker_code'  => 'VARCHAR(20) DEFAULT NULL',
                'pudo_locker_name'  => 'VARCHAR(160) DEFAULT NULL',
                'courier_waybill'   => 'VARCHAR(60) DEFAULT NULL',
                'courier_shipment'  => 'VARCHAR(40) DEFAULT NULL',
                'courier_pin'       => 'VARCHAR(20) DEFAULT NULL',
                'paylink_id'        => 'VARCHAR(80) DEFAULT NULL',
                'paylink_url'       => 'VARCHAR(500) DEFAULT NULL',
            ],
            'newsletter_campaigns' => [
                'scheduled_at' => 'DATETIME DEFAULT NULL',
                'ai_generated' => 'TINYINT(1) NOT NULL DEFAULT 0',
            ],
        ]);

        $pdo->exec("CREATE TABLE IF NOT EXISTS `social_posts` (
            `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `product_id` INT UNSIGNED DEFAULT NULL,
            `platform`   VARCHAR(20) NOT NULL,
            `status`     ENUM('posted','failed') NOT NULL,
            `remote_id`  VARCHAR(80) DEFAULT NULL,
            `caption`    TEXT DEFAULT NULL,
            `error`      VARCHAR(500) DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_platform` (`platform`, `status`),
            KEY `idx_product` (`product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Local image path -> ImageKit URL, so each photo is uploaded once.
        $pdo->exec("CREATE TABLE IF NOT EXISTS `imagekit_files` (
            `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `local_path` VARCHAR(500) NOT NULL,
            `local_hash` CHAR(32) NOT NULL,
            `url`        VARCHAR(500) NOT NULL,
            `file_id`    VARCHAR(80) DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_hash` (`local_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Pudo gets its own collection-point row label in the fulfilment list.
        $pdo->exec("INSERT IGNORE INTO settings (key_name, value) VALUES
            ('pudo_enabled', '1'), ('pudo_fallback_fee', '65'), ('pudo_box', 'S'),
            ('cron_newsletter_batch', '40')");
    }

    /** @param array<string,array<string,string>> $tables */
    private static function addColumns(array $tables): void
    {
        $has = db()->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
        );
        foreach ($tables as $table => $cols) {
            foreach ($cols as $col => $def) {
                $has->execute([':t' => $table, ':c' => $col]);
                if ((int) $has->fetchColumn() === 0) {
                    db()->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
                }
            }
        }
    }
}
