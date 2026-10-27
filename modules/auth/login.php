<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/functions.php';

if (is_logged_in()) {
    redirect('dashboards/customer/index.php');
}

$redirect = $_GET['redirect'] ?? '';
$errors   = [];
$email    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif (!rate_limit('login_' . md5($email), 8, 900)) {
        $errors[] = 'Too many attempts. Wait fifteen minutes and try again.';
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $email]);
        $user = $stmt->fetch();

        // One message for both branches — a wrong email and a wrong password
        // must be indistinguishable, or the form becomes an account enumerator.
        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'Those details do not match an account.';
        } elseif (!$user['is_active']) {
            $errors[] = 'That account is disabled. Contact support.';
        } else {
            JWTHandler::setToken($user);
            session_regenerate_id(true);

            $target = $redirect !== '' ? $redirect
                : ($user['role'] === 'admin' ? 'dashboards/admin/index.php'
                : (($user['reseller_status'] ?? '') === 'approved' ? 'dashboards/reseller/index.php'
                : 'dashboards/customer/index.php'));
            redirect($target);
        }
    }
}

$page_title = 'Sign in — ' . SITE_NAME;
$body_class = 'no-chrome';
?>
<!doctype html>
<html lang="en-ZA">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title) ?></title>
<meta name="robots" content="noindex">
<link rel="icon" type="image/svg+xml" href="<?= SITE_URL ?>/assets/img/logo/faircart-mark.svg">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/theme.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/components.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/layout.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/pages.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/responsive.css">
</head>
<body>
<div class="auth-shell">

  <aside class="auth-aside">
    <a href="<?= SITE_URL ?>/index.php" class="brand" style="position:relative;z-index:1">
      <img src="<?= SITE_URL ?>/assets/img/logo/faircart-mark.svg" alt="" class="brand__mark">
      <span class="brand__word" style="color:#fff">Fair<em style="color:var(--leaf-light)">Cart</em></span>
    </a>

    <div style="position:relative;z-index:1">
      <h2>Welcome back.</h2>
      <p style="color:var(--on-dark-2);max-width:40ch;">Your basket, your saved items and your order history are waiting.</p>
      <ul class="list-unstyled mt-4">
        <li><i class="fa-solid fa-check"></i><span>Track every order from placed to delivered</span></li>
        <li><i class="fa-solid fa-check"></i><span>Reseller pricing and margins on every product</span></li>
        <li><i class="fa-solid fa-check"></i><span>Save items and get restock alerts</span></li>
      </ul>
    </div>

    <p style="position:relative;z-index:1;font-size:.8rem;color:var(--on-dark-2);margin:0">
      &copy; <?= date('Y') ?> FairCart · <?= e(SITE_TAGLINE) ?>
    </p>
  </aside>

  <div class="auth-form">
    <div class="auth-form__inner">
      <a href="<?= SITE_URL ?>/index.php" class="d-lg-none brand mb-4">
        <img src="<?= SITE_URL ?>/assets/img/logo/faircart-mark.svg" alt="" class="brand__mark">
        <span class="brand__word">Fair<em>Cart</em></span>
      </a>

      <h1 style="font-size:1.9rem">Sign in</h1>
      <p class="text-muted-2 mb-4">New here? <a href="<?= SITE_URL ?>/modules/auth/register.php" class="text-leaf fw-bold">Create an account</a></p>

      <?php foreach ($errors as $err): ?>
        <div class="alert-fc alert-fc--danger"><i class="fa-solid fa-circle-exclamation"></i><span><?= e($err) ?></span></div>
      <?php endforeach; ?>

      <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label for="email">Email address</label>
          <input class="input-fc" type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="email" autofocus>
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input class="input-fc" type="password" id="password" name="password" required autocomplete="current-password">
          <div class="field__hint"><a href="<?= SITE_URL ?>/modules/auth/forgot.php">Forgot your password?</a></div>
        </div>
        <button class="btn-fc btn-fc--block btn-fc--lg" type="submit">Sign in</button>
      </form>

      <p class="text-center mt-4 mb-0" style="font-size:.85rem">
        <a href="<?= SITE_URL ?>/index.php" class="text-muted-2"><i class="fa-solid fa-arrow-left me-1"></i>Back to the shop</a>
      </p>
    </div>
  </div>
</div>
</body>
</html>
