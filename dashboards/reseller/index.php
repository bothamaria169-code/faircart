<?php
declare(strict_types=1);
$dash_role   = 'reseller';
$dash_active = 'dashboard';
$page_title  = 'Reseller dashboard — FairCart';
require_once __DIR__ . '/../../includes/dash-head.php';

$d      = ResellerService::dashboard((int) $user['id']);
$orders = new OrderRepository();
$recent = $orders->getAll(['user_id' => (int) $user['id']], 1, 6)['items'];

$tier     = RESELLER_TIERS[$d['current_tier']];
$nextTier = $d['next_tier'] ? RESELLER_TIERS[$d['next_tier']] : null;
$progress = $nextTier && $d['next_at'] > 0 ? min(100, (int) round($d['spend_90d'] / $d['next_at'] * 100)) : 100;

// What this reseller has actually bought, and what it is worth at RRP.
$lines = db()->prepare(
    "SELECT oi.name, SUM(oi.qty) qty,
            SUM(oi.qty * oi.unit_price) cost,
            SUM(oi.qty * oi.unit_rrp)   resale
     FROM order_items oi JOIN orders o ON o.id = oi.order_id
     WHERE o.user_id = :u AND o.payment_status = 'paid'
     GROUP BY oi.name ORDER BY resale DESC LIMIT 8"
);
$lines->execute([':u' => (int) $user['id']]);
$topLines = $lines->fetchAll();

// The best margins available right now — a restock shortlist.
$repo  = new ProductRepository();
$all   = $repo->getAll(['status' => 'published', 'in_stock' => true], 1, 200)['items'];
usort($all, fn($a, $b) => Pricing::margin($b, $user)['profit'] <=> Pricing::margin($a, $user)['profit']);
$best  = array_slice($all, 0, 5);
?>

<div class="dash-head">
  <div>
    <h1>Your reselling</h1>
    <p>
      <?= e($user['business_name'] ?: $user['name']) ?> ·
      code <b style="font-family:var(--font-mono)"><?= e((string) $user['reseller_code']) ?></b>
      <button class="icon-btn" type="button" data-copy="<?= e((string) $user['reseller_code']) ?>" style="width:26px;height:26px" aria-label="Copy code">
        <i class="fa-regular fa-copy" style="font-size:.72rem"></i>
      </button>
    </p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?= SITE_URL ?>/shop.php" class="btn-fc btn-fc--sm"><i class="fa-solid fa-basket-shopping"></i> Order stock</a>
    <a href="<?= SITE_URL ?>/price-list.php" class="btn-fc btn-fc--ghost btn-fc--sm"><i class="fa-solid fa-print"></i> Print price list</a>
  </div>
</div>

<div class="stat-grid">
  <div class="stat">
    <span class="stat__icon stat__icon--amber"><i class="fa-solid fa-award"></i></span>
    <span><b><?= e($tier['label']) ?></b><span>Your tier</span>
      <div class="delta up"><?= (int) ($tier['off_retail'] * 100) ?>% off retail</div></span>
  </div>
  <div class="stat">
    <span class="stat__icon stat__icon--ink"><i class="fa-solid fa-cart-shopping"></i></span>
    <span><b><?= format_price_short($d['spend_90d']) ?></b><span>Spend, last 90 days</span>
      <div class="delta"><?= (int) $d['orders_paid'] ?> paid order<?= (int) $d['orders_paid'] === 1 ? '' : 's' ?> all time</div></span>
  </div>
  <div class="stat">
    <span class="stat__icon stat__icon--leaf"><i class="fa-solid fa-arrow-trend-up"></i></span>
    <span><b><?= format_price_short($d['lifetime_profit']) ?></b><span>Margin bought, all time</span>
      <div class="delta">at recommended prices</div></span>
  </div>
  <div class="stat">
    <span class="stat__icon stat__icon--coral"><i class="fa-solid fa-boxes-stacked"></i></span>
    <span><b><?= format_price_short($d['lifetime_resale']) ?></b><span>Stock value at RRP</span>
      <div class="delta">from <?= format_price_short($d['lifetime_spend']) ?> spent</div></span>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">

    <!-- ── Tier progress ────────────────────────────────────────── -->
    <div class="card-fc card-fc--pad mb-3">
      <h2 style="font-size:1.05rem">Where your tier stands</h2>

      <?php if ($nextTier): ?>
        <p class="text-muted-2" style="font-size:.9rem">
          <?= format_price(($d['to_next'])) ?> more in paid orders over the next 90 days moves you to
          <b><?= e($nextTier['label']) ?></b> — <?= (int) ($nextTier['off_retail'] * 100) ?>% off retail
          instead of <?= (int) ($tier['off_retail'] * 100) ?>%.
        </p>
        <div class="d-flex justify-content-between" style="font-size:.8rem;color:var(--text-3)">
          <span><?= e($tier['label']) ?></span>
          <span><?= format_price_short($d['spend_90d']) ?> / <?= format_price_short((float) $d['next_at']) ?></span>
          <span><?= e($nextTier['label']) ?></span>
        </div>
        <div class="ship-meter__bar" style="height:10px;background:var(--paper-2)">
          <div class="ship-meter__fill" style="width:<?= $progress ?>%;background:var(--amber)"></div>
        </div>
      <?php else: ?>
        <div class="alert-fc alert-fc--ok mb-0">
          <i class="fa-solid fa-crown"></i>
          <span>You are on the top tier — <?= (int) ($tier['off_retail'] * 100) ?>% off retail across the catalogue.</span>
        </div>
      <?php endif; ?>

      <div class="tier-grid mt-3" style="--x:0">
        <?php foreach (RESELLER_TIERS as $k => $t):
          $mine = $k === $d['current_tier'];
          $past = array_search($k, array_keys(RESELLER_TIERS), true) < array_search($d['current_tier'], array_keys(RESELLER_TIERS), true); ?>
          <div class="tier" style="background:<?= $mine ? 'var(--amber-wash)' : 'var(--paper)' ?>;border-color:<?= $mine ? 'var(--amber)' : 'var(--line)' ?>">
            <b style="color:var(--ink)"><?= e($t['label']) ?></b>
            <div class="off" style="color:<?= $mine ? 'var(--amber-dark)' : ($past ? 'var(--leaf)' : 'var(--text-3)') ?>"><?= (int) ($t['off_retail'] * 100) ?>%</div>
            <span style="color:var(--text-3)"><?= $mine ? 'you are here' : ($past ? 'passed' : format_price_short((float) $t['min_spend'])) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ── Recent orders ────────────────────────────────────────── -->
    <div class="card-fc">
      <div class="d-flex justify-content-between align-items-center p-3 pb-0">
        <h2 style="font-size:1.05rem;margin:0">Your stock orders</h2>
        <a href="orders.php" class="btn-fc btn-fc--ghost btn-fc--sm">All orders</a>
      </div>
      <?php if (!$recent): ?>
        <div class="empty">
          <div class="empty__icon"><i class="fa-solid fa-boxes-stacked"></i></div>
          <p>No orders yet. Your wholesale pricing is already live across the catalogue.</p>
          <a href="<?= SITE_URL ?>/shop.php" class="btn-fc">Place your first order</a>
        </div>
      <?php else: ?>
      <div class="table-wrap" style="border:0">
        <table class="table-fc">
          <thead><tr><th>Order</th><th>Status</th><th class="text-end">Paid</th><th class="text-end">Worth at RRP</th></tr></thead>
          <tbody>
          <?php foreach ($recent as $o):
            $meta = ORDER_STATUSES[$o['status']];
            $tone = ['ok' => 'ok', 'warn' => 'warn', 'danger' => 'danger'][$meta['tone']] ?? 'info'; ?>
            <tr>
              <td>
                <a href="<?= SITE_URL ?>/order-confirmation.php?order=<?= urlencode($o['order_number']) ?>"
                   style="font-family:var(--font-mono);font-size:.8rem;font-weight:700"><?= e($o['order_number']) ?></a>
                <span class="d-block text-muted-3" style="font-size:.73rem"><?= date('j M Y', strtotime($o['created_at'])) ?></span>
              </td>
              <td><span class="pill pill--<?= $tone ?>"><i class="fa-solid <?= $meta['icon'] ?>"></i><?= e($meta['label']) ?></span></td>
              <td class="text-end money"><?= format_price_short((float) $o['total_amount']) ?></td>
              <td class="text-end money" style="color:var(--leaf)">
                <?= (float) $o['est_resale_value'] > 0 ? format_price_short((float) $o['est_resale_value']) : '—' ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-5">

    <!-- ── Restock shortlist ────────────────────────────────────── -->
    <div class="card-fc card-fc--pad mb-3">
      <h2 style="font-size:1.05rem">Biggest rand margin right now</h2>
      <p class="text-muted-3" style="font-size:.82rem">In stock, at your <?= e(strtolower($tier['label'])) ?> pricing.</p>
      <?php foreach ($best as $p):
        $m = Pricing::margin($p, $user); ?>
        <div class="d-flex gap-2 align-items-center py-2" style="border-bottom:1px solid var(--line)">
          <img src="<?= e(product_image($p['featured_image'])) ?>" alt="" width="40" height="40"
               style="border-radius:6px;object-fit:cover;background:var(--paper-2);flex-shrink:0">
          <div style="flex:1;min-width:0">
            <a href="<?= SITE_URL ?>/modules/shop/product.php?slug=<?= e($p['slug']) ?>"
               style="font-size:.85rem;font-weight:700;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($p['name']) ?></a>
            <span class="text-muted-3" style="font-size:.73rem">
              <?= format_price_short($m['cost']) ?> → <?= format_price_short($m['rrp']) ?> · <?= $m['margin_pct'] ?>%
            </span>
          </div>
          <b class="money" style="color:var(--leaf);white-space:nowrap">+<?= format_price_short($m['profit']) ?></b>
        </div>
      <?php endforeach; ?>
      <a href="<?= SITE_URL ?>/price-list.php" class="btn-fc btn-fc--ghost btn-fc--block btn-fc--sm mt-3">See the full price list</a>
    </div>

    <!-- ── What you have bought ─────────────────────────────────── -->
    <?php if ($topLines): ?>
    <div class="card-fc card-fc--pad">
      <h2 style="font-size:1.05rem">What you buy most</h2>
      <div class="table-wrap" style="border:0">
        <table class="table-fc">
          <thead><tr><th>Line</th><th class="text-center">Units</th><th class="text-end">Margin bought</th></tr></thead>
          <tbody>
          <?php foreach ($topLines as $l): ?>
            <tr>
              <td style="font-size:.84rem"><?= e($l['name']) ?></td>
              <td class="text-center"><?= (int) $l['qty'] ?></td>
              <td class="text-end money" style="color:var(--leaf)">+<?= format_price_short(max(0, (float) $l['resale'] - (float) $l['cost'])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="card-fc card-fc--pad mt-3" style="font-size:.84rem;color:var(--text-2)">
  <b>About these figures.</b>
  "Margin bought" is the difference between what you paid and what your stock is worth at our recommended selling
  prices. It is not a record of what you actually sold — we have no way of knowing that. Treat it as the ceiling,
  not the result.
</div>

<?php require_once __DIR__ . '/../../includes/dash-foot.php'; ?>
