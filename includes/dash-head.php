<?php
/**
 * Dashboard shell — opens <body>, the grid and the sidebar, and leaves
 * <div class="dash-main"> open for the page. Close it with dash-foot.php.
 *
 * Expects the page to set, before including:
 *   $dash_role   'admin' | 'reseller' | 'customer'
 *   $dash_active the slug of the current nav item
 *   $page_title
 */
require_once __DIR__ . '/functions.php';

// Dashboard pages print this shell first and handle their form posts after it,
// then redirect. Without a buffer the redirect fails with "headers already
// sent" as soon as the sidebar outgrows PHP's own small output buffer — and on
// a host with output_buffering off, it fails every time.
ob_start();

$dash_role   = $dash_role   ?? 'customer';
$dash_active = $dash_active ?? '';
$user        = require_auth($dash_role === 'admin' ? 'admin' : '');
$isRes       = Pricing::isReseller($user);

// An admin can open any dashboard; nobody else can open one above their level.
if ($dash_role === 'reseller' && !$isRes && $user['role'] !== 'admin') {
    redirect('reseller.php');
}

$stats = $dash_role === 'admin' ? (new OrderRepository())->stats() : [];

$nav = match ($dash_role) {
    'admin' => [
        'Overview' => [
            ['dashboard', 'index.php',     'fa-gauge-high',      'Dashboard', null],
            ['orders',    'orders.php',    'fa-receipt',         'Orders',    $stats['orders_pending'] ?? 0],
        ],
        'Catalogue' => [
            ['products',   'products.php',   'fa-boxes-stacked', 'Products',   null],
            ['categories', 'categories.php', 'fa-tags',          'Categories', null],
            ['brands',     'brands.php',     'fa-copyright',     'Brands',     null],
            ['coupons',    'coupons.php',    'fa-ticket',        'Coupons',    null],
            ['reviews',    'reviews.php',    'fa-star',          'Reviews',    $stats['pending_reviews'] ?? 0],
        ],
        'People' => [
            ['resellers', 'resellers.php', 'fa-handshake',   'Resellers', $stats['pending_apps'] ?? 0],
            ['users',     'users.php',     'fa-users',       'Customers', null],
            ['messages',  'messages.php',  'fa-inbox',       'Messages',  $stats['unread_msgs'] ?? 0],
        ],
        'Content' => [
            ['banners',    'banners.php',    'fa-images',       'Banners',     null],
            ['blog',       'blog.php',       'fa-newspaper',    'Articles',    null],
            ['newsletter', 'newsletter.php', 'fa-paper-plane',  'Newsletter',  null],
            ['social',     'social.php',     'fa-share-nodes',  'Social posting', null],
        ],
        'Setup' => [
            ['integrations', 'integrations.php', 'fa-plug',     'Integrations', null],
            ['settings',     'settings.php',     'fa-sliders',  'Settings',     null],
        ],
    ],
    'reseller' => [
        'Reselling' => [
            ['dashboard', 'index.php',      'fa-chart-line',   'Dashboard',  null],
            ['orders',    'orders.php',     'fa-receipt',      'Stock orders', null],
            ['pricelist', '../../price-list.php', 'fa-file-lines', 'My price list', null],
            ['shop',      '../../shop.php', 'fa-basket-shopping', 'Order stock', null],
        ],
        'Account' => [
            ['account',  '../customer/account.php',  'fa-user-gear', 'My details', null],
            ['wishlist', '../customer/wishlist.php', 'fa-heart',     'Saved items', null],
        ],
    ],
    default => [
        'My account' => [
            ['dashboard', 'index.php',  'fa-house-user', 'Overview',    null],
            ['orders',    'orders.php', 'fa-receipt',    'My orders',   null],
            ['wishlist',  'wishlist.php','fa-heart',     'Saved items', null],
            ['account',   'account.php','fa-user-gear',  'My details',  null],
        ],
    ],
};

$page_title = $page_title ?? ucfirst($dash_role) . ' dashboard — ' . SITE_NAME;
?>
<!doctype html>
<html lang="en-ZA">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/svg+xml" href="<?= SITE_URL ?>/assets/img/logo/faircart-mark.svg">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?= asset('assets/css/theme.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/components.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/layout.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/pages.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/responsive.css') ?>">
</head>
<body>
<div class="scrim" id="scrim"></div>
<div class="dash">

  <aside class="dash-side" id="dashSide">
    <a href="<?= SITE_URL ?>/index.php" class="brand">
      <img src="<?= SITE_URL ?>/assets/img/logo/faircart-mark.svg" alt="" class="brand__mark">
      <span class="brand__word">Fair<em style="color:var(--leaf-light)">Cart</em></span>
    </a>

    <?php foreach ($nav as $group => $links): ?>
      <div class="dash-side__label"><?= e($group) ?></div>
      <?php foreach ($links as [$slug, $href, $icon, $label, $count]): ?>
        <a href="<?= str_starts_with($href, '..') ? $href : $href ?>" class="<?= $dash_active === $slug ? 'is-active' : '' ?>">
          <i class="fa-solid <?= $icon ?>"></i><?= e($label) ?>
          <?php if ($count): ?><span class="n"><?= (int) $count ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>

    <div class="dash-side__label">Session</div>
    <a href="<?= SITE_URL ?>/index.php"><i class="fa-solid fa-store"></i>Back to the shop</a>
    <a href="<?= SITE_URL ?>/modules/auth/logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i>Sign out</a>

    <div style="margin-top:auto;padding:1.2rem .75rem 0;border-top:1px solid var(--line-dark)">
      <div style="font-size:.84rem;color:#fff;font-weight:700"><?= e($user['name']) ?></div>
      <div style="font-size:.74rem;color:var(--on-dark-2)"><?= e($user['email']) ?></div>
      <?php if ($isRes): ?>
        <span class="badge-fc badge-fc--amber mt-2"><?= e(RESELLER_TIERS[$user['reseller_tier']]['label']) ?> · <?= e((string) $user['reseller_code']) ?></span>
      <?php endif; ?>
    </div>
  </aside>

  <div class="dash-main">
    <button class="btn-fc btn-fc--ghost btn-fc--sm dash-burger mb-3" type="button" id="dashBurger">
      <i class="fa-solid fa-bars"></i> Menu
    </button>
