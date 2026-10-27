<?php
declare(strict_types=1);
$dash_role   = 'reseller';
$dash_active = 'orders';
$page_title  = 'Stock orders — FairCart';
require_once __DIR__ . '/../../includes/dash-head.php';

$repo   = new OrderRepository();
$page   = max(1, (int) ($_GET['page'] ?? 1));
$status = (string) ($_GET['status'] ?? '');
$result = $repo->getAll(['user_id' => (int) $user['id'], 'status' => $status ?: null], $page, 15);
?>

<div class="dash-head">
  <div>
    <h1>Stock orders</h1>
    <p><?= (int) $result['total'] ?> order<?= $result['total'] === 1 ? '' : 's' ?>. Click any one for tracking, the invoice and payment details.</p>
  </div>
  <a href="<?= SITE_URL ?>/shop.php" class="btn-fc btn-fc--sm"><i class="fa-solid fa-basket-shopping"></i> Order again</a>
</div>

<div class="d-flex gap-2 flex-wrap mb-3">
  <a class="chip <?= $status === '' ? 'is-on' : '' ?>" href="orders.php">All</a>
  <?php foreach (ORDER_STATUSES as $k => $m): ?>
    <a class="chip <?= $status === $k ? 'is-on' : '' ?>" href="?status=<?= $k ?>"><?= e($m['label']) ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$result['items']): ?>
  <div class="empty card-fc">
    <div class="empty__icon"><i class="fa-solid fa-receipt"></i></div>
    <h3>No orders here</h3>
    <p class="mb-3">Nothing matches that filter yet.</p>
    <a href="<?= SITE_URL ?>/shop.php" class="btn-fc">Browse the catalogue</a>
  </div>
<?php else: ?>

  <?php foreach ($result['items'] as $o):
    $meta  = ORDER_STATUSES[$o['status']];
    $tone  = ['ok' => 'ok', 'warn' => 'warn', 'danger' => 'danger'][$meta['tone']] ?? 'info';
    $items = $repo->getItems((int) $o['id']); ?>

    <article class="card-fc card-fc--pad mb-3">
      <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div>
          <b style="font-family:var(--font-mono);font-size:.95rem"><?= e($o['order_number']) ?></b>
          <?php if ($o['order_type'] === 'reseller'): ?><span class="badge-fc badge-fc--amber ms-2">Stock order</span><?php endif; ?>
          <div class="text-muted-3" style="font-size:.8rem">
            <?= date('j F Y', strtotime($o['created_at'])) ?> ·
            <i class="fa-solid <?= e(FULFILMENT_METHODS[$o['fulfilment']]['icon']) ?> ms-1 me-1"></i><?= e(FULFILMENT_METHODS[$o['fulfilment']]['label']) ?>
          </div>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
          <span class="pill pill--<?= $tone ?>"><i class="fa-solid <?= $meta['icon'] ?>"></i><?= e($meta['label']) ?></span>
          <span class="pill pill--<?= $o['payment_status'] === 'paid' ? 'ok' : 'warn' ?>"><?= e(ucfirst($o['payment_status'])) ?></span>
          <b class="money" style="font-size:1.1rem"><?= format_price((float) $o['total_amount']) ?></b>
        </div>
      </div>

      <div class="d-flex gap-2 flex-wrap mb-3">
        <?php foreach (array_slice($items, 0, 6) as $i): ?>
          <div style="position:relative">
            <img src="<?= e(product_image($i['image'])) ?>" alt="<?= e($i['name']) ?>" title="<?= e($i['name']) ?>"
                 width="52" height="52" style="border-radius:var(--r-xs);object-fit:cover;background:var(--paper-2)">
            <span class="icon-btn__badge" style="top:-6px;right:-6px"><?= (int) $i['qty'] ?></span>
          </div>
        <?php endforeach; ?>
        <?php if (count($items) > 6): ?>
          <div style="width:52px;height:52px;display:grid;place-items:center;background:var(--paper-2);border-radius:var(--r-xs);font-size:.8rem;font-weight:700;color:var(--text-2)">
            +<?= count($items) - 6 ?>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($o['payment_status'] === 'unpaid' && !$o['pop_path']): ?>
        <div class="alert-fc alert-fc--warn" style="font-size:.85rem">
          <i class="fa-solid fa-triangle-exclamation"></i>
          <span>Waiting on your payment. Transfer <b><?= format_price((float) $o['total_amount']) ?></b> using
            <b><?= e($o['order_number']) ?></b> as the reference, then upload your proof of payment.</span>
        </div>
      <?php endif; ?>

      <div class="d-flex gap-2 flex-wrap">
        <a href="<?= SITE_URL ?>/order-confirmation.php?order=<?= urlencode($o['order_number']) ?>" class="btn-fc btn-fc--sm">
          <i class="fa-solid fa-location-crosshairs"></i> Track this order
        </a>
        <a href="<?= SITE_URL ?>/invoice.php?order=<?= urlencode($o['order_number']) ?>" target="_blank" class="btn-fc btn-fc--ghost btn-fc--sm">
          <i class="fa-solid fa-file-invoice"></i> Invoice
        </a>
      </div>
    </article>
  <?php endforeach; ?>

  <?= render_pager($result['page'], $result['pages']) ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/dash-foot.php'; ?>
