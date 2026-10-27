<?php
declare(strict_types=1);
$dash_role   = 'customer';
$dash_active = 'account';
$page_title  = 'My details — FairCart';
require_once __DIR__ . '/../../includes/dash-head.php';

$errors  = [];
$flashes = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'details') {
        $name  = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));

        if (mb_strlen($name) < 2)  { $errors[] = 'Enter your full name.'; }
        if (!safe_email($email))   { $errors[] = 'Enter a valid email address.'; }

        if (!$errors && $email !== $user['email']) {
            $stmt = db()->prepare('SELECT id FROM users WHERE email = :e AND id <> :id LIMIT 1');
            $stmt->execute([':e' => $email, ':id' => (int) $user['id']]);
            if ($stmt->fetch()) { $errors[] = 'Another account already uses that email address.'; }
        }

        if (!$errors) {
            db_update('users', (int) $user['id'], [
                'name'  => sanitize($name),
                'email' => $email,
                'phone' => $phone !== '' ? sanitize($phone) : null,
            ]);
            $flashes[] = 'Your details are updated.';
            $user['name'] = $name; $user['email'] = $email; $user['phone'] = $phone;
        }
    }

    if ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        $stmt = db()->prepare('SELECT password FROM users WHERE id = :id');
        $stmt->execute([':id' => (int) $user['id']]);
        $hash = (string) $stmt->fetchColumn();

        if (!password_verify($current, $hash))  { $errors[] = 'Your current password is not right.'; }
        elseif (mb_strlen($new) < 8)            { $errors[] = 'The new password needs at least 8 characters.'; }
        elseif ($new !== $confirm)              { $errors[] = 'The two new passwords do not match.'; }
        else {
            db_update('users', (int) $user['id'], ['password' => password_hash($new, PASSWORD_BCRYPT, ['cost' => 12])]);
            $flashes[] = 'Password changed.';
        }
    }
}
?>

<div class="dash-head">
  <div><h1>My details</h1><p>Your contact details and password.</p></div>
</div>

<?php foreach ($errors as $err): ?>
  <div class="alert-fc alert-fc--danger"><i class="fa-solid fa-circle-exclamation"></i><span><?= e($err) ?></span></div>
<?php endforeach; ?>
<?php foreach ($flashes as $msg): ?>
  <div class="alert-fc alert-fc--ok"><i class="fa-solid fa-circle-check"></i><span><?= e($msg) ?></span></div>
<?php endforeach; ?>

<div class="row g-3">
  <div class="col-lg-6">
    <form method="post" class="card-fc card-fc--pad">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="details">
      <h2 style="font-size:1.05rem">Contact details</h2>
      <div class="field">
        <label for="name">Full name</label>
        <input class="input-fc" type="text" id="name" name="name" value="<?= e((string) $user['name']) ?>" required>
      </div>
      <div class="field">
        <label for="email">Email address</label>
        <input class="input-fc" type="email" id="email" name="email" value="<?= e((string) $user['email']) ?>" required>
        <div class="field__hint">Order confirmations and delivery updates go here.</div>
      </div>
      <div class="field">
        <label for="phone">Mobile number</label>
        <input class="input-fc" type="tel" id="phone" name="phone" value="<?= e((string) $user['phone']) ?>" placeholder="+27 82 000 0000">
        <div class="field__hint">Used by the courier or your Runner.</div>
      </div>
      <button class="btn-fc" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save details</button>
    </form>
  </div>

  <div class="col-lg-6">
    <form method="post" class="card-fc card-fc--pad mb-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="password">
      <h2 style="font-size:1.05rem">Change password</h2>
      <div class="field">
        <label for="current_password">Current password</label>
        <input class="input-fc" type="password" id="current_password" name="current_password" required autocomplete="current-password">
      </div>
      <div class="field">
        <label for="new_password">New password</label>
        <input class="input-fc" type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
        <div class="field__hint">At least 8 characters.</div>
      </div>
      <div class="field">
        <label for="confirm_password">Confirm new password</label>
        <input class="input-fc" type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
      </div>
      <button class="btn-fc btn-fc--ghost" type="submit"><i class="fa-solid fa-key"></i> Change password</button>
    </form>

    <?php if ($isRes): ?>
    <div class="card-fc card-fc--pad" style="background:var(--amber-wash);border-color:#F0DFB6">
      <h2 style="font-size:1.05rem">Reseller account</h2>
      <div style="font-size:.88rem;color:var(--text-2);line-height:1.9">
        <div><b>Trading as</b> <?= e((string) $user['business_name']) ?></div>
        <div><b>Code</b> <span style="font-family:var(--font-mono)"><?= e((string) $user['reseller_code']) ?></span></div>
        <div><b>Tier</b> <?= e(RESELLER_TIERS[$user['reseller_tier']]['label']) ?> · <?= (int) (RESELLER_TIERS[$user['reseller_tier']]['off_retail'] * 100) ?>% off retail</div>
      </div>
      <a href="<?= SITE_URL ?>/dashboards/reseller/index.php" class="btn-fc btn-fc--amber btn-fc--block btn-fc--sm mt-3">
        <i class="fa-solid fa-chart-line"></i> Reseller dashboard
      </a>
    </div>
    <?php elseif (($user['reseller_status'] ?? 'none') === 'none'): ?>
    <div class="card-fc card-fc--pad" style="background:var(--leaf-wash);border-color:#BFE3D5">
      <h2 style="font-size:1.05rem">Selling these products on?</h2>
      <p style="font-size:.88rem;color:var(--text-2)">
        Reseller registration is free. Once approved you pay 45–60% below retail and see your rand profit on every product.
      </p>
      <a href="<?= SITE_URL ?>/reseller.php" class="btn-fc btn-fc--block btn-fc--sm">See the programme</a>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/dash-foot.php'; ?>
