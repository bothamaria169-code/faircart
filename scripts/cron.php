<?php
declare(strict_types=1);

/**
 * FairCart's one cron job. Run every 5 minutes:
 *
 *   * /5 * * * * /usr/local/bin/php /home/wyimzdyd/public_html/scripts/cron.php >> /home/wyimzdyd/logs/faircart-cron.log 2>&1
 *   (cPanel → Cron Jobs → "Once Per Five Minutes", command without the stars)
 *
 * Each run:
 *   1. starts scheduled newsletters that are due,
 *   2. sends the next batches of any newsletter that is going out,
 *   3. writes (and optionally sends) the weekly AI newsletter,
 *   4. posts to Facebook / Instagram and makes reels when due,
 *   5. once a night, promotes resellers to the tier they have earned.
 * A lock file stops two runs overlapping when a reel takes a few minutes.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script runs from the command line only.\n");
}

require_once __DIR__ . '/../includes/functions.php';

$lockFile = __DIR__ . '/../uploads/cache/cron.lock';
@mkdir(dirname($lockFile), 0755, true);
$lock = fopen($lockFile, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo '[' . date('Y-m-d H:i:s') . "] Previous run still busy — skipping.\n";
    exit(0);
}

@set_time_limit(840);
$started = time();
$log = [];
$say = function (string $line) use (&$log): void {
    $log[] = $line;
    echo '[' . date('Y-m-d H:i:s') . "] $line\n";
};
$task = function (string $name, callable $fn) use ($say): void {
    try {
        $fn();
    } catch (Throwable $e) {
        $say("$name failed: " . $e->getMessage());
    }
};

NewsletterService::ensureSchema();

// ── 1. Scheduled newsletters that are due ────────────────────────────────
$task('Scheduled newsletters', function () use ($say) {
    $due = db()->query("SELECT id, subject FROM newsletter_campaigns WHERE status = 'scheduled' AND scheduled_at <= NOW()")->fetchAll();
    foreach ($due as $c) {
        $n = NewsletterService::queue((int) $c['id']);
        if ($n === 0) {
            db_update('newsletter_campaigns', (int) $c['id'], ['status' => 'draft']);
            $say("Scheduled \"{$c['subject']}\": nobody in that audience — back to drafts.");
        } else {
            $say("Scheduled \"{$c['subject']}\": started sending to $n.");
        }
    }
});

// ── 2. Send batches ──────────────────────────────────────────────────────
$task('Newsletter sending', function () use ($say, $started) {
    // Per-run cap keeps us under the host's hourly mail limit (cPanel often allows ~300–500/hour).
    $budget = max(5, (int) get_setting('cron_newsletter_batch', '40'));
    $ids = db()->query("SELECT id, subject FROM newsletter_campaigns WHERE status = 'sending' ORDER BY id")->fetchAll();
    foreach ($ids as $c) {
        while ($budget > 0 && time() - $started < 120) {
            $before = NewsletterService::progress((int) $c['id']);
            $batch  = min(20, $budget);
            $p = NewsletterService::sendBatch((int) $c['id'], $batch);
            $budget -= $batch;
            $sent = $p['sent'] + $p['failed'] - $before['sent'] - $before['failed'];
            if ($sent > 0) {
                $say("\"{$c['subject']}\": {$p['sent']}/{$p['total']} sent" . ($p['failed'] ? ", {$p['failed']} failed" : '') . '.');
            }
            if ($p['done'] || $sent === 0) {
                break;
            }
        }
    }
});

// ── 3. Weekly AI newsletter ──────────────────────────────────────────────
$task('AI newsletter', function () use ($say) {
    $mode = (string) get_setting('auto_newsletter', 'off');
    if ($mode === 'off' || !GroqWriter::configured()) {
        return;
    }
    $now  = new DateTimeImmutable('now', new DateTimeZone('Africa/Johannesburg'));
    $week = $now->format('o-W');
    if ((int) $now->format('N') !== (int) get_setting('auto_newsletter_day', '4')
        || (int) $now->format('G') < (int) get_setting('auto_newsletter_hour', '9')
        || get_setting('auto_newsletter_week') === $week) {
        return;
    }
    set_setting('auto_newsletter_week', $week); // claimed first: never twice in a week

    // New, on-sale and best-selling stock first; skip what last week's email featured.
    $last = json_decode((string) get_setting('auto_newsletter_products', '[]'), true) ?: [0];
    $ids = db()->query(
        "SELECT id FROM products WHERE status = 'published' AND stock_status <> 'out_of_stock'
           AND id NOT IN (" . implode(',', array_map('intval', $last)) . ")
         ORDER BY is_new DESC, is_sale DESC, is_bestseller DESC, sold_count DESC, RAND() LIMIT 4"
    )->fetchAll(PDO::FETCH_COLUMN);
    if (count($ids) < 2) {
        return;
    }
    set_setting('auto_newsletter_products', json_encode(array_map('intval', $ids)));

    $copy = GroqWriter::newsletter('grid', $ids, 'Weekly FairCart roundup: what is new, on special and selling fast this week.');
    $data = [
        'eyebrow' => $copy['eyebrow'], 'heading' => $copy['heading'], 'intro' => $copy['intro'],
        'product_ids' => array_map('intval', $ids), 'coupon_code' => '', 'sale_note' => '',
        'cta_label' => $copy['cta_label'] ?: 'Shop the range', 'cta_url' => '/shop.php',
    ];
    $admin = db()->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetchColumn();
    $id = db_insert('newsletter_campaigns', [
        'subject'       => $copy['subject'] ?: 'This week at FairCart',
        'preheader'     => $copy['preheader'] ?: null,
        'audience'      => 'all',
        'template'      => 'grid',
        'template_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'body_html'     => NewsletterTemplates::render('grid', $data),
        'status'        => 'draft',
        'ai_generated'  => 1,
        'created_by'    => (int) ($admin ?: 1),
    ]);
    if ($mode === 'send') {
        $n = NewsletterService::queue($id);
        $say("AI newsletter \"{$copy['subject']}\" written and sending to $n.");
    } else {
        $say("AI newsletter \"{$copy['subject']}\" written as a draft.");
        Mailer::send(mail_config()['admin'], 'Your weekly newsletter draft is ready',
            email_wrap('<p>The AI writer drafted this week\'s newsletter: <b>' . e($copy['subject']) . '</b>.</p>'
                . '<p><a href="' . SITE_URL . '/dashboards/admin/newsletter-compose.php?id=' . $id . '">Check it and send it</a></p>'));
    }
});

// ── 4. Facebook & Instagram ──────────────────────────────────────────────
$task('Social posting', function () use ($say) {
    foreach (SocialPoster::runScheduled() as $line) {
        if ($line !== 'Social: nothing due.') {
            $say($line);
        }
    }
});

// ── 5. Nightly: reseller tiers ───────────────────────────────────────────
$task('Reseller tiers', function () use ($say) {
    $today = date('Y-m-d');
    if ((int) date('G') < 2 || get_setting('tiers_refreshed_on') === $today) {
        return;
    }
    set_setting('tiers_refreshed_on', $today);
    ob_start();
    require __DIR__ . '/refresh-tiers.php';
    $say(trim((string) ob_get_clean()) ?: 'Reseller tiers refreshed.');
});

set_setting('cron_last_run', date('Y-m-d H:i:s'));
if ($log) {
    set_setting('cron_last_output', mb_substr(date('j M H:i') . "\n" . implode("\n", $log), 0, 4000));
}
flock($lock, LOCK_UN);
