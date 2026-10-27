<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/functions.php';

if (is_logged_in()) {
    redirect('dashboards/customer/index.php');
}

// reseller.php sends people here with ?intent=reseller so the application form
// can open immediately after the account exists.
$intent   = ($_GET['intent'] ?? '') === 'reseller' ? 'reseller' : 'retail';
$errors   = [];
$values   = ['name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['name']  = trim((string) ($_POST['name'] ?? ''));
    $values['email'] = trim((string) ($_POST['email'] ?? ''));
    $values['phone'] = trim((string) ($_POST['phone'] ?? ''));
    $password        = (string) ($_POST['password'] ?? '');
    $confirm         = (string) ($_POST['confirm'] ?? '');
    $intent          = ($_POST['intent'] ?? 'retail') === 'reseller' ? 'reseller' : 'retail';

    if (!verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Your session expired. Please try again.';
    }
    if (mb_strlen($values['name']) < 2)          { $errors[] = 'Enter your full name.'; }
    if (!safe_email($values['email']))           { $errors[] = 'Enter a valid email address.'; }
    if (mb_strlen($password) < 8)                { $errors[] = 'Your password needs at least 8 characters.'; }
    if ($password !== $confirm)                  { $errors[] = 'The two passwords do not match.'; }
    if (empty($_POST['terms']))                  { $errors[] = 'Please accept the terms to continue.'; }

    if (!$errors) {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $values['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'An account already exists for that email. <a href="login.php" class="fw-bold">Sign in instead</a>.';
        }
    }

    if (!$errors) {
        $userId = db_insert('users', [
            'name'     => sanitize($values['name']),
            'email'    => $values['email'],
            'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            'phone'    => $values['phone'] !== '' ? sanitize($values['phone']) : null,
            'role'     => 'customer',
        ]);

        $stmt = db()->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        JWTHandler::setToken($user);
        session_regenerate_id(true);

        $html = '<p>Welcome to FairCart, ' . e($values['name']) . '.</p>'
              . '<p>Your account is live. You can shop the full catalogue now, and if you plan to sell these lines on, '
              . 'reseller registration is free and takes about two minutes.</p>'
              . '<p><a href="' . SITE_URL . '/shop.php" style="display:inline-block;background:#12805C;color:#fff;padding:11px 22px;border-radius:999px;font-weight:700;">Start shopping</a></p>';
        Mailer::send($values['email'], 'Welcome to FairCart', email_wrap($html));

        redirect($intent === 'reseller' ? 'reseller.php?apply=1' : 'index.php');
    }
}

$page_title = 'Create an account — ' . SITE_NAME;
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
      <h2><?= $intent === 'reseller' ? 'Two steps to wholesale.' : 'Join FairCart.' ?></h2>
      <p style="color:var(--on-dark-2);max-width:40ch;">
        <?= $intent === 'reseller'
            ? 'Create your account, then tell us where you trade. Approval is usually the same working day.'
            : 'One account for shopping, saved items, order tracking — and reseller pricing later if you want it.' ?>
      </p>
      <ul class="list-unstyled mt-4">
        <li><i class="fa-solid fa-check"></i><span>Free to join, no subscription</span></li>
        <li><i class="fa-solid fa-check"></i><span>45–60% off retail once approved as a reseller</span></li>
        <li><i class="fa-solid fa-check"></i><span>Delivery, depot collection or Runner pickup</span></li>
        <li><i class="fa-solid fa-check"></i><span>Your rand profit shown on every product</span></li>
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

      <h1 style="font-size:1.9rem">Create your account</h1>
      <p class="text-muted-2 mb-4">Already registered? <a href="<?= SITE_URL ?>/modules/auth/login.php" class="text-leaf fw-bold">Sign in</a></p>

      <?php foreach ($errors as $err): ?>
        <div class="alert-fc alert-fc--danger"><i class="fa-solid fa-circle-exclamation"></i><span><?= $err ?></span></div>
      <?php endforeach; ?>

      <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="intent" value="<?= e($intent) ?>">

        <div class="field">
          <label for="name">Full name</label>
          <input class="input-fc" type="text" id="name" name="name" value="<?= e($values['name']) ?>" required autocomplete="name" autofocus>
        </div>
        <div class="field">
          <label for="email">Email address</label>
          <input class="input-fc" type="email" id="email" name="email" value="<?= e($values['email']) ?>" required autocomplete="email">
        </div>
        <div class="field">
          <label for="phone">Mobile number <span class="text-muted-3 fw-normal">(optional)</span></label>
          <input class="input-fc" type="tel" id="phone" name="phone" value="<?= e($values['phone']) ?>" placeholder="+27 82 000 0000" autocomplete="tel">
        </div>
        <div class="row g-3">
          <div class="col-md-6 field">
            <label for="password">Password</label>
            <input class="input-fc" type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
            <div class="field__hint">At least 8 characters.</div>
          </div>
          <div class="col-md-6 field">
            <label for="confirm">Confirm password</label>
            <input class="input-fc" type="password" id="confirm" name="confirm" required minlength="8" autocomplete="new-password">
          </div>
        </div>

        <label class="opt-card mb-3">
          <input type="checkbox" name="terms" value="1" required>
          <span class="opt-card__blurb">
            I accept the <a href="<?= SITE_URL ?>/terms.php" target="_blank" class="text-leaf fw-bold">terms of use</a>
            and <a href="<?= SITE_URL ?>/privacy.php" target="_blank" class="text-leaf fw-bold">privacy policy</a>.
          </span>
        </label>

        <button class="btn-fc btn-fc--block btn-fc--lg" type="submit">
          <?= $intent === 'reseller' ? 'Create account and continue' : 'Create my account' ?>
        </button>
      </form>

      <p class="text-center mt-4 mb-0" style="font-size:.85rem">
        <a href="<?= SITE_URL ?>/index.php" class="text-muted-2"><i class="fa-solid fa-arrow-left me-1"></i>Back to the shop</a>
      </p>
    </div>
  </div>
</div>
</body>
</html>
