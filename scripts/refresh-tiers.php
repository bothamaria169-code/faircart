<?php
declare(strict_types=1);

/**
 * Nightly reseller tier promotion.
 *
 * Promotes any approved reseller whose rolling 90-day paid spend has earned a
 * higher tier. It never demotes: a tier once earned is kept, because a quiet
 * month should not quietly cut somebody's margin — and because a discount that
 * can disappear without warning is the thing that makes these programmes feel
 * like a trap.
 *
 * cPanel cron (daily at 02:15):
 *   15 2 * * * /usr/local/bin/php /home/USER/public_html/scripts/refresh-tiers.php >> /home/USER/logs/faircart-tiers.log 2>&1
 *
 * Locally:  php scripts/refresh-tiers.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script runs from the command line only.\n");
}

require_once __DIR__ . '/../includes/functions.php';

$started = microtime(true);
echo '[' . date('Y-m-d H:i:s') . "] Refreshing reseller tiers…\n";

$before = db()->query(
    "SELECT reseller_tier, COUNT(*) n FROM users WHERE reseller_status='approved' GROUP BY reseller_tier"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$changed = ResellerService::refreshTiers();

$after = db()->query(
    "SELECT reseller_tier, COUNT(*) n FROM users WHERE reseller_status='approved' GROUP BY reseller_tier"
)->fetchAll(PDO::FETCH_KEY_PAIR);

foreach (array_keys(RESELLER_TIERS) as $tier) {
    printf("  %-8s %3d -> %3d\n", RESELLER_TIERS[$tier]['label'], (int) ($before[$tier] ?? 0), (int) ($after[$tier] ?? 0));
}

printf("Promoted %d reseller(s) in %.2fs.\n", $changed, microtime(true) - $started);
