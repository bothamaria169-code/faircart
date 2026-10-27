<?php
declare(strict_types=1);
$dash_role   = 'admin';
$dash_active = 'dashboard';
$page_title  = 'Admin dashboard — FairCart';
require_once __DIR__ . '/../../includes/dash-head.php';

$orders = new OrderRepository();
$series = $orders->revenueSeries(30);
$recent = $orders->getAll([], 1, 8)['items'];
$top    = $orders->topProducts(6);

$lowStock = db()->query(
    "SELECT p.id, p.name, p.slug, p.stock_qty, p.stock_status, b.name AS brand_name
     FROM products p LEFT JOIN brands b ON b.id = p.brand_id
     WHERE p.status='published' AND p.stock_status IN ('low_stock','out_of_stock')
     ORDER BY p.stock_qty ASC LIMIT 8"
)->fetchAll();

$pendingResellers = db()->query(
    "SELECT id, name, email, business_name, trading_area, applied_at
     FROM users WHERE reseller_status='pending' ORDER BY applied_at DESC LIMIT 5"
)->fetchAll();

$mixStmt = db()->query(
    "SELECT order_type, COUNT(*) n, COALESCE(SUM(total_amount),0) v
     FROM orders GROUP BY order_type"
)->fetchAll();
$mix = ['retail' => ['n' => 0, 'v' => 0.0], 'reseller' => ['n' => 0, 'v' => 0.0]];
foreach ($mixStmt as $r) { $mix[$r['order_type']] = ['n' => (int) $r['n'], 'v' => (float) $r['v']]; }
?>

<div class="dash-head">
  <div>
    <h1>Good <?= (int) date('H') < 12 ? 'morning' : ((int) date('H') < 17 ? 'afternoon' : 'evening') ?>, <?= e(explode(' ', $user['name'])[0]) ?></h1>
    <p><?= date('l j F Y') ?> · here is where FairCart stands right now.</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="product-form.php" class="btn-fc btn-fc--sm"><i class="fa-solid fa-plus"></i> New product</a>
    <a href="<?= SITE_URL ?>/index.php" class="btn-fc btn-fc--ghost btn-fc--sm" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i> View site</a>
  </div>
</div>

<?php if ($stats['pending_apps'] > 0 || $stats['orders_pending'] > 0 || $stats['unread_msgs'] > 0): ?>
<div class="alert-fc alert-fc--warn">
  <i class="fa-solid fa-bell"></i>
  <span>
    <b>Waiting on you:</b>
    <?php
    $todo = [];
    if ($stats['orders_pending']) { $todo[] = '<a href="orders.php?status=pending" class="fw-bold">' . $stats['orders_pending'] . ' unpaid order' . ($stats['orders_pending'] === 1 ? '' : 's') . '</a>'; }
    if ($stats['pending_apps'])   { $todo[] = '<a href="resellers.php?status=pending" class="fw-bold">' . $stats['pending_apps'] . ' reseller application' . ($stats['pending_apps'] === 1 ? '' : 's') . '</a>'; }
    if ($stats['unread_msgs'])    { $todo[] = '<a href="messages.php" class="fw-bold">' . $stats['unread_msgs'] . ' unread message' . ($stats['unread_msgs'] === 1 ? '' : 's') . '</a>'; }
    if ($stats['pending_reviews']){ $todo[] = '<a href="reviews.php" class="fw-bold">' . $stats['pending_reviews'] . ' review' . ($stats['pending_reviews'] === 1 ? '' : 's') . ' to moderate</a>'; }
    echo implode(' · ', $todo);
    ?>
  </span>
</div>
<?php endif; ?>

<div class="stat-grid">
  <div class="stat">
    <span class="stat__icon stat__icon--leaf"><i class="fa-solid fa-sack-dollar"></i></span>
    <span><b><?= format_price_short($stats['revenue']) ?></b><span>Revenue (paid)</span>
      <div class="delta up"><?= format_price_short($stats['revenue_month']) ?> this month</div>
    </span>
  </div>
  <div class="stat">
    <span class="stat__icon stat__icon--ink"><i class="fa-solid fa-receipt"></i></span>
    <span><b><?= (int) $stats['orders_total'] ?></b><span>Orders all time</span>
      <div class="delta<?= $stats['orders_pending'] ? ' down' : '' ?>"><?= (int) $stats['orders_pending'] ?> awaiting payment</div>
    </span>
  </div>
  <div class="stat">
    <span class="stat__icon stat__icon--amber"><i class="fa-solid fa-handshake"></i></span>
    <span><b><?= (int) $stats['resellers'] ?></b><span>Approved resellers</span>
      <?php if ($stats['pending_apps']): ?><div class="delta down"><?= (int) $stats['pending_apps'] ?> awaiting review</div><?php endif; ?>
    </span>
  </div>
  <div class="stat">
    <span class="stat__icon stat__icon--coral"><i class="fa-solid fa-boxes-stacked"></i></span>
    <span><b><?= (int) $stats['products'] ?></b><span>Published products</span>
      <?php if ($stats['low_stock']): ?><div class="delta down"><?= (int) $stats['low_stock'] ?> low or out of stock</div><?php endif; ?>
    </span>
  </div>
</div>

<div class="row g-3">

  <!-- ── Revenue chart ────────────────────────────────────────────── -->
  <div class="col-lg-8">
    <div class="card-fc card-fc--pad">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
          <h2 style="font-size:1.1rem;margin-bottom:.1rem">Orders, last 30 days</h2>
          <p class="text-muted-3 mb-0" style="font-size:.82rem">Order value by day, whether paid yet or not.</p>
        </div>
        <span class="badge-fc badge-fc--leaf"><?= format_price_short(array_sum(array_column($series, 'value'))) ?> in 30 days</span>
      </div>
      <canvas id="revenueChart" height="110"></canvas>
    </div>
  </div>

  <!-- ── Retail vs reseller split ─────────────────────────────────── -->
  <div class="col-lg-4">
    <div class="card-fc card-fc--pad h-100">
      <h2 style="font-size:1.1rem">Where the money comes from</h2>
      <?php
      $totalMix = max(0.01, $mix['retail']['v'] + $mix['reseller']['v']);
      foreach ([['retail', 'Retail shoppers', 'var(--ink)'], ['reseller', 'Resellers', 'var(--amber)']] as [$k, $label, $colour]):
        $pct = (int) round($mix[$k]['v'] / $totalMix * 100); ?>
        <div class="mt-3">
          <div class="d-flex justify-content-between align-items-baseline">
            <span style="font-size:.86rem;font-weight:600"><?= $label ?></span>
            <b class="money" style="font-size:.95rem"><?= format_price_short($mix[$k]['v']) ?></b>
          </div>
          <div class="ship-meter__bar" style="background:var(--paper-2)">
            <div class="ship-meter__fill" style="width:<?= $pct ?>%;background:<?= $colour ?>"></div>
          </div>
          <div class="text-muted-3" style="font-size:.74rem;margin-top:.25rem"><?= $mix[$k]['n'] ?> order<?= $mix[$k]['n'] === 1 ? '' : 's' ?> · <?= $pct ?>%</div>
        </div>
      <?php endforeach; ?>

      <?php if ($top): ?>
        <div class="dash-side__label" style="color:var(--text-3);padding-left:0">Top sellers</div>
        <?php foreach (array_slice($top, 0, 4) as $t): ?>
          <div class="d-flex justify-content-between py-1" style="font-size:.83rem;border-bottom:1px solid var(--line)">
            <span style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($t['name']) ?></span>
            <b style="white-space:nowrap;margin-left:.5rem"><?= (int) $t['q'] ?> units</b>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Recent orders ────────────────────────────────────────────── -->
  <div class="col-lg-8">
    <div class="card-fc">
      <div class="d-flex justify-content-between align-items-center p-3 pb-0">
        <h2 style="font-size:1.1rem;margin:0">Latest orders</h2>
        <a href="orders.php" class="btn-fc btn-fc--ghost btn-fc--sm">All orders</a>
      </div>
      <?php if (!$recent): ?>
        <div class="empty"><div class="empty__icon"><i class="fa-solid fa-receipt"></i></div><p>No orders yet.</p></div>
      <?php else: ?>
      <div class="table-wrap" style="border:0">
        <table class="table-fc">
          <thead><tr><th>Order</th><th>Customer</th><th>Type</th><th>Status</th><th class="text-end">Total</th></tr></thead>
          <tbody>
          <?php foreach ($recent as $o):
            $meta = ORDER_STATUSES[$o['status']]; ?>
            <tr onclick="location.href='order-detail.php?id=<?= (int) $o['id'] ?>'" style="cursor:pointer">
              <td>
                <b style="font-family:var(--font-mono);font-size:.8rem"><?= e($o['order_number']) ?></b>
                <span class="d-block text-muted-3" style="font-size:.73rem"><?= time_ago($o['created_at']) ?></span>
              </td>
              <td>
                <?= e((string) $o['customer_name']) ?>
                <span class="d-block text-muted-3" style="font-size:.73rem"><?= (int) $o['line_count'] ?> line<?= (int) $o['line_count'] === 1 ? '' : 's' ?></span>
              </td>
              <td><span class="badge-fc <?= $o['order_type'] === 'reseller' ? 'badge-fc--amber' : 'badge-fc--muted' ?>"><?= e($o['order_type']) ?></span></td>
              <td><span class="pill pill--<?= $meta['tone'] === 'ok' ? 'ok' : ($meta['tone'] === 'warn' ? 'warn' : ($meta['tone'] === 'danger' ? 'danger' : 'info')) ?>"><i class="fa-solid <?= $meta['icon'] ?>"></i><?= e($meta['label']) ?></span></td>
              <td class="text-end money"><?= format_price_short((float) $o['total_amount']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Needs attention ──────────────────────────────────────────── -->
  <div class="col-lg-4">
    <?php if ($pendingResellers): ?>
    <div class="card-fc card-fc--pad mb-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 style="font-size:1.05rem;margin:0">Reseller applications</h2>
        <a href="resellers.php?status=pending" class="text-leaf fw-bold" style="font-size:.8rem">Review</a>
      </div>
      <?php foreach ($pendingResellers as $r): ?>
        <div class="py-2" style="border-bottom:1px solid var(--line)">
          <b style="font-size:.87rem"><?= e((string) $r['business_name']) ?></b>
          <div class="text-muted-3" style="font-size:.76rem">
            <?= e($r['name']) ?> · <?= e((string) $r['trading_area']) ?> · <?= $r['applied_at'] ? time_ago($r['applied_at']) : '' ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="card-fc card-fc--pad">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 style="font-size:1.05rem;margin:0">Stock to watch</h2>
        <a href="products.php" class="text-leaf fw-bold" style="font-size:.8rem">Products</a>
      </div>
      <?php if (!$lowStock): ?>
        <p class="text-muted-3 mb-0" style="font-size:.86rem">Everything is comfortably in stock.</p>
      <?php else: ?>
        <?php foreach ($lowStock as $p): ?>
        <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom:1px solid var(--line)">
          <div style="min-width:0">
            <a href="product-form.php?id=<?= (int) $p['id'] ?>" style="font-size:.86rem;font-weight:700;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($p['name']) ?></a>
            <span class="text-muted-3" style="font-size:.73rem"><?= e((string) $p['brand_name']) ?></span>
          </div>
          <span class="pill <?= $p['stock_status'] === 'out_of_stock' ? 'pill--danger' : 'pill--warn' ?>" style="white-space:nowrap">
            <?= $p['stock_status'] === 'out_of_stock' ? 'Out' : (int) $p['stock_qty'] . ' left' ?>
          </span>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.3/chart.umd.min.js"></script>
<script>
  const SERIES = <?= json_encode($series) ?>;
  new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
      labels: SERIES.map(d => new Date(d.date).toLocaleDateString('en-ZA', { day: 'numeric', month: 'short' })),
      datasets: [{
        data: SERIES.map(d => d.value),
        borderColor: '#12805C',
        backgroundColor: 'rgba(18,128,92,.10)',
        borderWidth: 2.5,
        fill: true,
        tension: .35,
        pointRadius: 0,
        pointHoverRadius: 5,
        pointHoverBackgroundColor: '#12805C'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#0E1726', padding: 10, displayColors: false,
          callbacks: {
            label: (ctx) => 'R' + ctx.parsed.y.toLocaleString('en-ZA') + ' · ' + SERIES[ctx.dataIndex].count + ' order(s)'
          }
        }
      },
      scales: {
        y: { beginAtZero: true, border: { display: false }, grid: { color: '#E3E8EF' },
             ticks: { callback: (v) => 'R' + v.toLocaleString('en-ZA'), font: { size: 11 }, color: '#7C8899' } },
        x: { border: { display: false }, grid: { display: false },
             ticks: { maxTicksLimit: 8, font: { size: 11 }, color: '#7C8899' } }
      }
    }
  });
</script>

<?php require_once __DIR__ . '/../../includes/dash-foot.php'; ?>
