<?php
declare(strict_types=1);

/**
 * "Write it with AI" on the compose page: returns subject, preview text and
 * copy for the chosen template and products (JSON). Nothing is saved here.
 */
require_once __DIR__ . '/../../includes/functions.php';

require_auth('admin');
if (!verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
    json_response(['success' => false, 'message' => 'Your session expired. Reload the page.'], 419);
}
if (!rate_limit('nl_ai', 30, 600)) {
    json_response(['success' => false, 'message' => 'That is a lot of drafts — wait a few minutes.'], 429);
}

$template = (string) ($_POST['template'] ?? 'grid');
$ids      = array_values(array_filter(array_map('intval', explode(',', (string) ($_POST['product_ids'] ?? '')))));
@set_time_limit(90);

try {
    $copy = GroqWriter::newsletter(
        $template,
        $ids,
        mb_substr(trim((string) ($_POST['brief'] ?? '')), 0, 600),
        strtoupper(trim((string) ($_POST['coupon_code'] ?? ''))),
        (string) ($_POST['audience'] ?? 'all')
    );
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => $e->getMessage()]);
}

if ($template === 'custom') {
    $paras = array_map(fn($p) => '<p>' . e($p) . '</p>', array_filter(array_map('trim', explode("\n", $copy['intro']))));
    $copy['body_html'] = '<h1>' . e($copy['heading']) . '</h1>' . "\n" . implode("\n", $paras)
        . "\n" . '<p><a href="' . SITE_URL . '/shop.php">' . e($copy['cta_label'] ?: 'Visit the shop') . '</a></p>';
}

json_response(['success' => true, 'copy' => $copy]);
