<?php
declare(strict_types=1);

/**
 * Where Facebook sends the admin back after "Connect Facebook Page". Swaps the
 * one-time code for never-expiring Page tokens and saves the Page (or lists
 * the Pages to choose from when there are several).
 */
require_once __DIR__ . '/../../includes/functions.php';
require_auth('admin');

$expected = (string) ($_SESSION['meta_state'] ?? '');
unset($_SESSION['meta_state']);
$back = function (string $tone, string $msg): never {
    $_SESSION['social_flash'] = [$tone, $msg];
    redirect('dashboards/admin/social.php');
};

if (empty($_GET['code']) || $expected === '' || !hash_equals($expected, (string) ($_GET['state'] ?? ''))) {
    $back('warn', 'The Facebook connection was cancelled or expired — press Connect again.' . (!empty($_GET['error_description']) ? ' Facebook said: ' . mb_substr((string) $_GET['error_description'], 0, 200) : ''));
}

try {
    $pages = SocialPoster::pagesFromCode((string) $_GET['code']);
} catch (Throwable $e) {
    $back('danger', 'Facebook connection failed — ' . preg_replace('/(access_token|client_secret|code)=[^&\s]+/', '$1=…', $e->getMessage()));
}

if (!$pages) {
    $back('warn', 'Facebook returned no Pages. Connect again and make sure you tick the FairCart Page when Facebook asks which Pages to allow.');
}
if (count($pages) === 1) {
    SocialPoster::savePage($pages[0]);
    $back('ok', 'Connected to ' . $pages[0]['name'] . (isset($pages[0]['instagram_business_account']) ? ' and Instagram @' . $pages[0]['instagram_business_account']['username'] : ' (no Instagram account is linked to this Page yet — see step 1)') . '.');
}
// Several Pages: keep them in this session only and let the admin choose.
$_SESSION['meta_pages'] = $pages;
$back('info', 'Choose which Page FairCart should post to.');
