<?php
declare(strict_types=1);

// ── Site identity ─────────────────────────────────────────────────────────
define('SITE_NAME', 'FairCart');
define('SITE_DOMAIN', 'faircart.co.za');
define('SITE_TAGLINE', 'Real products. Fair prices. Delivered.');
define('SITE_EMAIL', 'hello@faircart.co.za');
define('CURRENCY', 'R');
define('CURRENCY_CODE', 'ZAR');

// ── Environment detection ────────────────────────────────────────────────
// Local = Windows/XAMPP dev machine or a localhost hostname; anything else
// (the Linux cPanel host) is production. Works for both web and CLI.
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
define('IS_PRODUCTION', !(
    PHP_OS_FAMILY === 'Windows'
    || str_starts_with($httpHost, 'localhost')
    || str_starts_with($httpHost, '127.0.0.1')
));

// ── Secrets ──────────────────────────────────────────────────────────────
// Passwords live in config/secrets.php, which is NOT in git (the repository is
// public). Copy config/secrets.example.php to config/secrets.php on each server
// and fill it in once; `git pull` never touches it.
$secrets = is_file(__DIR__ . '/secrets.php') ? (array) require __DIR__ . '/secrets.php' : [];
if (IS_PRODUCTION && !$secrets) {
    http_response_code(500);
    exit('FairCart is not configured: create config/secrets.php from config/secrets.example.php.');
}

// ── Database ─────────────────────────────────────────────────────────────
// cPanel account: wyimzdyd (host prefixes db name + user with the account name).
if (IS_PRODUCTION) {
    define('DB_HOST', $secrets['db_host'] ?? 'localhost');
    define('DB_NAME', $secrets['db_name'] ?? 'wyimzdyd_faircart');
    define('DB_USER', $secrets['db_user'] ?? 'wyimzdyd_nexus');
    define('DB_PASS', $secrets['db_pass'] ?? '');
} else {
    define('DB_HOST', $secrets['local_db_host'] ?? 'localhost');
    define('DB_NAME', $secrets['local_db_name'] ?? 'faircart');
    define('DB_USER', $secrets['local_db_user'] ?? 'root');
    define('DB_PASS', $secrets['local_db_pass'] ?? '');
}
define('DB_CHARSET', 'utf8mb4');

// ── Auth / JWT ───────────────────────────────────────────────────────────
// DB_PASS is mixed in so the secret can't be derived from readable source alone.
define('JWT_SECRET', 'fc_' . hash('sha256', 'faircart-za-2026-marketplace|' . DB_PASS));
define('JWT_EXPIRY', 60 * 60 * 24 * 7); // 7 days
define('JWT_COOKIE_NAME', 'fc_token');

// ── Uploads ──────────────────────────────────────────────────────────────
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 8 * 1024 * 1024);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml']);
define('ALLOWED_POP_TYPES', ['image/jpeg', 'image/png', 'application/pdf']);
define('ALLOWED_DOC_TYPES', ['image/jpeg', 'image/png', 'application/pdf']);

// ── Catalog ──────────────────────────────────────────────────────────────
define('PRODUCTS_PER_PAGE', 24);

// ── Mail (SMTP via the cPanel mailbox) ───────────────────────────────────
// Defaults from config/secrets.php; Admin → Integrations → Email can override
// every one of these without touching a file (see mail_config()). Until a user
// and password exist anywhere, Mailer falls back to PHP mail().
define('SMTP_HOST', $secrets['smtp_host'] ?? 'mail.faircart.co.za');
define('SMTP_PORT', (int) ($secrets['smtp_port'] ?? 465));
define('SMTP_SECURE', $secrets['smtp_secure'] ?? 'ssl'); // 'ssl' for 465, 'tls' for 587
define('SMTP_USER', $secrets['smtp_user'] ?? '');
define('SMTP_PASS', $secrets['smtp_pass'] ?? '');
define('SMTP_FROM_NAME', SITE_NAME);
define('ADMIN_EMAIL', $secrets['admin_email'] ?? 'admin@faircart.co.za');

define('DEBUG_MODE', !IS_PRODUCTION);

// ── SITE_URL auto-detection (works unchanged in an XAMPP subfolder and on a
//    live vhost root) ────────────────────────────────────────────────────
$protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host       = $_SERVER['HTTP_HOST'] ?? SITE_DOMAIN;
$docRootDir = str_replace('\\', '/', dirname(__DIR__));
$basePath   = '';
if (!empty($_SERVER['DOCUMENT_ROOT'])) {
    $documentRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
    $basePath     = str_replace($documentRoot, '', $docRootDir);
}
define('SITE_URL', rtrim($protocol . $host . $basePath, '/'));
