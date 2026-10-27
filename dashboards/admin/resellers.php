<?php
declare(strict_types=1);
$dash_role   = 'admin';
$dash_active = 'resellers';
$page_title  = 'Resellers — FairCart admin';
require_once __DIR__ . '/../../includes/dash-head.php';

$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
    $targetId = (int) ($_POST['user_id'] ?? 0);
    $action   = (string) ($_POST['action'] ?? '');
    $target   = ResellerService::find($targetId);

    if ($target) {
        if ($action === 'approve') {
            $code = ResellerService::approve($targetId);
            notify_reseller_approved(ResellerService::find($targetId), $code);
            $flash = ['ok', e($target['name']) . ' approved as a reseller — code ' . e($code) . '. They have been emailed.'];
        } elseif ($action === 'suspend') {
            ResellerService::setStatus($targetId, 'suspended');
            $flash = ['warn', e($target['name']) . ' suspended. They now see retail prices.'];
        } elseif ($action === 'reinstate') {
            $code = ResellerService::approve($targetId);
            $flash = ['ok', e($target['name']) . ' reinstated at ' . e(RESELLER_TIERS[$target['reseller_tier']]['label']) . '.'];
        } elseif ($action === 'decline') {
            ResellerService::setStatus($targetId, 'none');
            $flash = ['warn', 'Application from ' . e($target['name']) . ' declined.'];
        } elseif ($action === 'tier') {
            ResellerService::setTier($targetId, (string) ($_POST['tier'] ?? 'starter'));
            $flash = ['ok', e($target['name']) . ' moved to ' . e(RESELLER_TIERS[(string) $_POST['tier']]['label']) . '.'];
        }
    }
}

$filters = [
    'status' => (string) ($_GET['status'] ?? ''),
    'tier'   => (string) ($_GET['tier'] ?? ''),
    'search' => trim((string) ($_GET['q'] ?? '')),
];
$page   = max(1, (int) ($_GET['page'] ?? 1));
$result = ResellerService::listAll($filters, $page, 25);

$counts = [];
foreach (db()->query("SELECT reseller_status s, COUNT(*) n FROM users WHERE reseller_status <> 'none' GROUP BY reseller_status") as $r) {
    $counts[$r['s']] = (int) $r['n'];
}
?>

<div class="dash-head">
  <div>
    <h1>Resellers</h1>
    <p>Approve applications, move tiers and suspend accounts. Pricing follows immediately on the next page load.</p>
  </div>
  <a href="<?= SITE_URL ?>/reseller.php" class="btn-fc btn-fc--ghost btn-fc--sm" target="_blank">
    <i class="fa-solid fa-arrow-up-right-from-square"></i> Public page
  </a>
</div>

<?php if ($flash): ?>
  <div class="alert-fc alert-fc--<?= $flash[0] === 'ok' ? 'ok' : 'warn' ?>">
    <i class="fa-solid fa-circle-<?= $flash[0] === 'ok' ? 'check' : 'exclamation' ?>"></i><span><?= $flash[1] ?></span>
  </div>
<?php endif; ?>

<div class="card-fc card-fc--pad mb-3">
  <form method="get" class="row g-2 align-items-end">
    <div class="col-md-4 field mb-0">
      <label for="q">Search</label>
      <input class="input-fc" type="search" id="q" name="q" value="<?= e($filters['search']) ?>" placeholder="Name, email, business or code">
    </div>
    <div class="col-md-3 field mb-0">
      <label for="status">Status</label>
      <select class="select-fc" id="status" name="status">
        <option value="">All statuses</option>
        <?php foreach (['pending' => 'Pending review', 'approved' => 'Approved', 'suspended' => 'Suspended'] as $v => $label): ?>
          <option value="<?= $v ?>" <?= $filters['status'] === $v ? 'selected' : '' ?>>
            <?= $label ?><?= isset($counts[$v]) ? ' (' . $counts[$v] . ')' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3 field mb-0">
      <label for="tier">Tier</label>
      <select class="select-fc" id="tier" name="tier">
        <option value="">All tiers</option>
        <?php foreach (RESELLER_TIERS as $k => $t): ?>
          <option value="<?= $k ?>" <?= $filters['tier'] === $k ? 'selected' : '' ?>><?= e($t['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <button class="btn-fc btn-fc--block" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
    </div>
  </form>
</div>

<?php if (!$result['items']): ?>
  <div class="empty card-fc">
    <div class="empty__icon"><i class="fa-solid fa-handshake"></i></div>
    <h3>No resellers match that</h3>
    <p>Applications arrive from the public reseller page.</p>
  </div>
<?php else: ?>
<div class="table-wrap">
  <table class="table-fc">
    <thead>
      <tr>
        <th>Reseller</th><th>Trading as</th><th>Tier</th><th class="text-end">90-day spend</th>
        <th class="text-end">Paid orders</th><th>Status</th><th style="width:1%">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($result['items'] as $r):
      $earned  = Pricing::tierForSpend((float) $r['spend_90d']);
      $keys    = array_keys(RESELLER_TIERS);
      $behind  = array_search($earned, $keys, true) > array_search($r['reseller_tier'], $keys, true); ?>
      <tr>
        <td>
          <b><?= e($r['name']) ?></b>
          <span class="d-block text-muted-3" style="font-size:.75rem"><?= e($r['email']) ?></span>
          <?php if ($r['reseller_code']): ?>
            <span class="d-block" style="font-size:.72rem;font-family:var(--font-mono);color:var(--leaf)"><?= e($r['reseller_code']) ?></span>
          <?php endif; ?>
        </td>
        <td>
          <?= e((string) $r['business_name']) ?>
          <span class="d-block text-muted-3" style="font-size:.75rem"><?= e((string) $r['trading_area']) ?></span>
          <?php if ($r['phone']): ?><span class="d-block text-muted-3" style="font-size:.75rem"><?= e($r['phone']) ?></span><?php endif; ?>
        </td>
        <td>
          <form method="post" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= (int) $r['id'] ?>">
            <input type="hidden" name="action" value="tier">
            <select class="select-fc" name="tier" onchange="this.form.submit()" style="padding:.35rem 1.9rem .35rem .6rem;font-size:.8rem;width:auto">
              <?php foreach (RESELLER_TIERS as $k => $t): ?>
                <option value="<?= $k ?>" <?= $r['reseller_tier'] === $k ? 'selected' : '' ?>>
                  <?= e($t['label']) ?> · <?= (int) ($t['off_retail'] * 100) ?>%
                </option>
              <?php endforeach; ?>
            </select>
          </form>
          <?php if ($behind): ?>
            <span class="badge-fc badge-fc--amber mt-1" title="Their spend qualifies for a higher tier">
              earned <?= e(RESELLER_TIERS[$earned]['label']) ?>
            </span>
          <?php endif; ?>
        </td>
        <td class="text-end money"><?= format_price_short((float) $r['spend_90d']) ?></td>
        <td class="text-end"><?= (int) $r['orders_paid'] ?></td>
        <td>
          <?php $tone = ['pending' => 'warn', 'approved' => 'ok', 'suspended' => 'danger'][$r['reseller_status']] ?? 'muted'; ?>
          <span class="pill pill--<?= $tone ?>"><?= e(ucfirst($r['reseller_status'])) ?></span>
          <?php if ($r['applied_at']): ?>
            <span class="d-block text-muted-3" style="font-size:.72rem">applied <?= time_ago($r['applied_at']) ?></span>
          <?php endif; ?>
        </td>
        <td>
          <form method="post" class="d-flex gap-1">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= (int) $r['id'] ?>">
            <?php if ($r['reseller_status'] === 'pending'): ?>
              <button class="btn-fc btn-fc--sm" name="action" value="approve"><i class="fa-solid fa-check"></i> Approve</button>
              <button class="btn-fc btn-fc--sm btn-fc--light" name="action" value="decline"
                      onclick="return confirm('Decline this application? They can apply again later.')">Decline</button>
            <?php elseif ($r['reseller_status'] === 'approved'): ?>
              <button class="btn-fc btn-fc--sm btn-fc--light" name="action" value="suspend"
                      onclick="return confirm('Suspend <?= e($r['name']) ?>? They immediately go back to retail pricing.')">
                <i class="fa-solid fa-pause"></i> Suspend
              </button>
            <?php else: ?>
              <button class="btn-fc btn-fc--sm" name="action" value="reinstate"><i class="fa-solid fa-rotate-left"></i> Reinstate</button>
            <?php endif; ?>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?= render_pager($result['page'], $result['pages']) ?>
<?php endif; ?>

<div class="card-fc card-fc--pad mt-3" style="font-size:.84rem;color:var(--text-2)">
  <b>How tiers move.</b>
  Tiers promote automatically on rolling 90-day <i>paid</i> spend
  (<?php
    echo implode(', ', array_map(
      fn($k, $t) => $t['label'] . ' ' . format_price_short((float) $t['min_spend']),
      array_keys(RESELLER_TIERS), RESELLER_TIERS
    ));
  ?>)
  when <code>scripts/refresh-tiers.php</code> runs on a nightly cron. Promotion never demotes anyone —
  a tier once earned is kept. The dropdown above overrides that manually.
</div>

<?php require_once __DIR__ . '/../../includes/dash-foot.php'; ?>
