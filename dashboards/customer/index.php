<?php
declare(strict_types=1);
$dash_role   = 'customer';
$dash_active = 'dashboard';
$page_title  = 'My account — FairCart';
require_once __DIR__ . '/../../includes/dash-head.php';

$orders = new OrderRepository();
$mine   = $orders->getAll(['user_id' => (int) $user['id']], 1, 5);
$wish   = wishlist_ids((int) $user['id']);

$spentStmt = db()->prepare("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE user_id = :u AND payment_status='paid'");
$spentStmt->execute([':u' => (int) $user['id']]);
$spent = (float) $spentStmt->fetchColumn();

$openStmt = db()->prepare("SELECT COUNT(*) FROM orders WHERE user_id = :u AND status NOT IN ('delivered','cancelled')");
$openStmt->execute([':u' => (int) $user['id']]);
$open = (int) $openStmt->fetchColumn();

$saved = $wish ? (new ProductRepository())->getAll(['ids' => $wish], 1, 4)['items'] : [];
?>

<div class="dash-head">
  <div>
    <h1>Hi <?= e(explode(' ', $user['name'])[0]) ?></h1>
    <p>Your orders, saved items and details — all in one place.</p>
  </div>
  <a href="<?= SITE_URL ?>/shop.php" class="btn-fc btn-fc--sm"><i class="fa-solid fa-basket-shopping"></i> Shop</a>
</div>

<div class="stat-grid">
  <div class="stat"><span class="stat__icon stat__icon--leaf"><i class="fa-solid fa-receipt"></i></span>
    <span><b><?= (int) $mine['total'] ?></b><span>Orders placed</span>
      <?php if ($open): ?><div class="delta up"><?= $open ?> still in progress</div><?php endif; ?></span></div>
  <div class="stat"><span class="stat__icon stat__icon--ink"><i class="fa-solid fa-wallet"></i></span>
    <span><b><?= format_price_short($spent) ?></b><span>Spent with us</span></span></div>
  <div class="stat"><span class="stat__icon stat__icon--coral"><i class="fa-solid fa-heart"></i></span>
    <span><b><?= count($wish) ?></b><span>Saved items</span></span></div>
  <div class="stat"><span class="stat__icon stat__icon--amber"><i class="fa-solid fa-handshake"></i></span>
    <span>
      <b style="font-size:1.1rem"><?= $isRes ? e(RESELLER_TIERS[$user['reseller_tier']]['label']) : (($user['reseller_status'] ?? 'none') === 'pending' ? 'Pending' : 'Not yet') ?></b>
      <span>Reseller status</span>
      <?php if (!$isRes && ($user['reseller_status'] ?? 'none') === 'none'): ?>
        <div class="delta"><a href="<?= SITE_URL ?>/reseller.php" class="text-leaf fw-bold">Register free</a></div>
      <?php endif; ?>
    </span></div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card-fc">
      <div class="d-flex justify-content-between align-items-center p-3 pb-0">
        <h2 style="font-size:1.05rem;margin:0">Recent orders</h2>
        <a href="orders.php" class="btn-fc btn-fc--ghost btn-fc--sm">All orders</a>
      </div>

      <?php if (!$mine['items']): ?>
        <div class="empty">
          <div class="empty__icon"><i class="fa-solid fa-receipt"></i></div>
          <h3>No orders yet</h3>
          <p class="mb-3">When you place one it shows up here with live tracking.</p>
          <a href="<?= SITE_URL ?>/shop.php" class="btn-fc">Browse the catalogue</a>
        </div>
      <?php else: ?>
        <div class="p-3">
        <?php foreach ($mine['items'] as $o):
          $meta = ORDER_STATUSES[$o['status']];
          $tone = ['ok' => 'ok', 'warn' => 'warn', 'danger' => 'danger'][$meta['tone']] ?? 'info'; ?>
          <a class="d-flex gap-3 align-items-center py-3" href="<?= SITE_URL ?>/order-confirmation.php?order=<?= urlencode($o['order_number']) ?>"
             style="border-bottom:1px solid var(--line)">
            <span style="width:42px;height:42px;display:grid;place-items:center;background:var(--paper-2);border-radius:var(--r-sm);flex-shrink:0">
              <i class="fa-solid <?= e(FULFILMENT_METHODS[$o['fulfilment']]['icon']) ?> text-muted-2"></i>
            </span>
            <span style="flex:1;min-width:0">
              <b style="font-family:var(--font-mono);font-size:.85rem"><?= e($o['order_number']) ?></b>
              <span class="d-block text-muted-3" style="font-size:.76rem">
                <?= date('j M Y', strtotime($o['created_at'])) ?> · <?= (int) $o['line_count'] ?> line<?= (int) $o['line_count'] === 1 ? '' : 's' ?>
              </span>
            </span>
            <span class="pill pill--<?= $tone ?>"><i class="fa-solid <?= $meta['icon'] ?>"></i><?= e($meta['label']) ?></span>
            <b class="money" style="white-space:nowrap"><?= format_price_short((float) $o['total_amount']) ?></b>
          </a>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card-fc card-fc--pad mb-3">
      <h2 style="font-size:1.05rem">Your details</h2>
      <div style="font-size:.88rem;color:var(--text-2);line-height:1.9">
        <div><i class="fa-solid fa-user me-2 text-muted-3"></i><?= e($user['name']) ?></div>
        <div><i class="fa-solid fa-envelope me-2 text-muted-3"></i><?= e($user['email']) ?></div>
        <div><i class="fa-solid fa-phone me-2 text-muted-3"></i><?= e($user['phone'] ?: 'No number saved') ?></div>
      </div>
      <a href="account.php" class="btn-fc btn-fc--ghost btn-fc--block btn-fc--sm mt-3">
        <i class="fa-solid fa-pen"></i> Update my details
      </a>
    </div>

    <div class="card-fc card-fc--pad">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 style="font-size:1.05rem;margin:0">Saved items</h2>
        <a href="wishlist.php" class="text-leaf fw-bold" style="font-size:.8rem">See all</a>
      </div>
      <?php if (!$saved): ?>
        <p class="text-muted-3 mb-0" style="font-size:.86rem">Nothing saved yet. Tap the heart on any product.</p>
      <?php else: ?>
        <?php foreach ($saved as $p): ?>
          <div class="d-flex gap-2 align-items-center py-2" style="border-bottom:1px solid var(--line)">
            <img src="<?= e(product_image($p['featured_image'])) ?>" alt="" width="40" height="40"
                 style="border-radius:6px;object-fit:cover;background:var(--paper-2);flex-shrink:0">
            <div style="flex:1;min-width:0">
              <a href="<?= SITE_URL ?>/modules/shop/product.php?slug=<?= e($p['slug']) ?>"
                 style="font-size:.85rem;font-weight:700;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($p['name']) ?></a>
              <span class="text-muted-3" style="font-size:.73rem"><?= e((string) $p['brand_name']) ?></span>
            </div>
            <b class="money" style="font-size:.86rem"><?= format_price_short(Pricing::effective($p, $user)) ?></b>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/dash-foot.php'; ?>
