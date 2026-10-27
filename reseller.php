<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$viewer = current_user();
$status = $viewer['reseller_status'] ?? 'none';
$isRes  = Pricing::isReseller($viewer);

$errors = [];
$sent   = false;

// The application form only exists for signed-in users — the account is what
// the reseller record hangs off, so anonymous applications have nowhere to go.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $viewer && $status === 'none') {
    if (!verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Your session expired. Please submit again.';
    }
    $business = trim((string) ($_POST['business_name'] ?? ''));
    $idnum    = trim((string) ($_POST['id_number'] ?? ''));
    $area     = trim((string) ($_POST['trading_area'] ?? ''));
    $phone    = trim((string) ($_POST['phone'] ?? ''));

    if (mb_strlen($business) < 2)  { $errors[] = 'Enter a trading name — your own name is fine if you have no registered business.'; }
    if (mb_strlen($area) < 2)      { $errors[] = 'Tell us the area you trade in.'; }
    if (mb_strlen(preg_replace('/\D/', '', $phone) ?? '') < 9) { $errors[] = 'Enter a contact number.'; }
    if (empty($_POST['agree']))    { $errors[] = 'Please confirm you accept the reseller terms.'; }

    if (!$errors) {
        $sponsorId = null;
        if (!empty($_POST['sponsor_code'])) {
            $stmt = db()->prepare("SELECT id FROM users WHERE reseller_code = :c AND reseller_status='approved' LIMIT 1");
            $stmt->execute([':c' => strtoupper(trim((string) $_POST['sponsor_code']))]);
            $sponsorId = ($row = $stmt->fetch()) ? (int) $row['id'] : null;
        }

        ResellerService::apply((int) $viewer['id'], [
            'business_name' => sanitize($business),
            'id_number'     => sanitize($idnum),
            'trading_area'  => sanitize($area),
            'phone'         => sanitize($phone),
            'sponsor_id'    => $sponsorId,
        ]);

        Mailer::send(mail_config()['admin'], 'New reseller application — ' . $business, email_wrap(
            '<p><b>' . e($viewer['name']) . '</b> (' . e($viewer['email']) . ') applied to become a reseller.</p>'
            . '<p>Trading as <b>' . e($business) . '</b> in ' . e($area) . '.</p>'
            . '<p><a href="' . SITE_URL . '/dashboards/admin/resellers.php" style="display:inline-block;background:#12805C;color:#fff;padding:11px 22px;border-radius:999px;font-weight:700;">Review the application</a></p>'
        ));
        Mailer::send((string) $viewer['email'], 'We have your FairCart reseller application', email_wrap(
            '<p>Thanks ' . e($viewer['name']) . ' — your reseller application is in.</p>'
            . '<p>We verify applications by hand, usually the same working day. As soon as you are approved you will see '
            . 'wholesale pricing and your margin on every product page.</p>'
        ));

        $sent   = true;
        $status = 'pending';
    }
}

$repo     = new ProductRepository();
$calcable = $repo->getAll(['status' => 'published', 'sort' => 'popular'], 1, 60)['items'];

// A worked example that is real, not invented: the median-margin product.
$margins = [];
foreach ($calcable as $p) { $margins[(int) $p['id']] = Pricing::margin($p, null)['margin_pct']; }
asort($margins);
$medianId = array_keys($margins)[intdiv(count($margins), 2)] ?? ((int) ($calcable[0]['id'] ?? 0));

$approvedCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE reseller_status='approved'")->fetchColumn();
$faqs = db()->query("SELECT * FROM faqs WHERE status='published' AND topic='Reseller' ORDER BY sort_order")->fetchAll();
$quotes = db()->query("SELECT * FROM testimonials WHERE status='published' AND is_reseller=1 ORDER BY sort_order LIMIT 3")->fetchAll();

$page_title       = 'Trade &amp; wholesale — bulk pricing for shops and salons | ' . SITE_NAME;
$meta_description = 'Buying FairCart products for a shop, salon, pharmacy or stall? Trade accounts get wholesale pricing across the catalogue. Free to register, no monthly fee.';
$extra_js         = [SITE_URL . '/assets/js/reseller.js'];
include __DIR__ . '/includes/header.php';
?>

<header class="page-head">
  <div class="container">
    <nav class="crumbs"><a href="<?= SITE_URL ?>/index.php">Home</a><i class="fa-solid fa-chevron-right"></i><span>Trade &amp; wholesale</span></nav>
    <span class="badge-fc badge-fc--amber mb-2">Free to register · no monthly fee · six-unit case minimum</span>
    <h1>Buying for a shop, salon<br>or stall? Buy it at trade.</h1>
    <p>
      If you sell these products on — from a storefront, a salon, a pharmacy counter or a market stall —
      a trade account gets you wholesale pricing across the whole catalogue, with your cost and margin
      shown on every product page.
    </p>
  </div>
</header>

<!-- ── Status banner ────────────────────────────────────────────────── -->
<?php if ($isRes): ?>
<div class="container" style="margin-top:-1px">
  <div class="alert-fc alert-fc--ok" style="border-radius:0 0 var(--r) var(--r);margin-bottom:0">
    <i class="fa-solid fa-circle-check"></i>
    <span>
      You are approved at <b><?= e(RESELLER_TIERS[$viewer['reseller_tier']]['label']) ?></b>
      (<?= (int) (RESELLER_TIERS[$viewer['reseller_tier']]['off_retail'] * 100) ?>% off retail), code
      <b><?= e((string) $viewer['reseller_code']) ?></b>.
      <a href="<?= SITE_URL ?>/dashboards/reseller/index.php" class="fw-bold">Open your dashboard</a>
    </span>
  </div>
</div>
<?php elseif ($status === 'pending' || $sent): ?>
<div class="container" style="margin-top:-1px">
  <div class="alert-fc alert-fc--warn" style="border-radius:0 0 var(--r) var(--r);margin-bottom:0">
    <i class="fa-solid fa-hourglass-half"></i>
    <span><b>Your application is with us.</b> We verify by hand, usually the same working day. You will get an email the moment it is approved.</span>
  </div>
</div>
<?php elseif ($status === 'suspended'): ?>
<div class="container" style="margin-top:-1px">
  <div class="alert-fc alert-fc--danger" style="border-radius:0 0 var(--r) var(--r);margin-bottom:0">
    <i class="fa-solid fa-circle-exclamation"></i>
    <span>Your reseller account is suspended. <a href="<?= SITE_URL ?>/contact.php" class="fw-bold">Contact us</a> and we will sort it out.</span>
  </div>
</div>
<?php endif; ?>

<!-- ── Promotions carousel ──────────────────────────────────────────── -->
<div style="padding-top:1.5rem">
  <?php $carousel_page = 'reseller'; include __DIR__ . '/includes/banner-carousel.php'; ?>
</div>

<!-- ── Calculator ───────────────────────────────────────────────────── -->
<section class="section">
  <div class="container">
    <div class="row g-4 align-items-start">

      <div class="col-lg-5">
        <span class="eyebrow eyebrow--amber">Work it out yourself</span>
        <h2>What would you actually make?</h2>
        <p class="lede">
          Pick a real product, pick a tier, pick a quantity. The numbers below come straight from the live catalogue —
          no illustrative examples, no rounded-up best cases.
        </p>

        <ul class="pdp-benefits">
          <li><i class="fa-solid fa-check"></i><span><b>No joining fee and no subscription.</b> You pay for stock, nothing else.</span></li>
          <li><i class="fa-solid fa-check"></i><span><b>Tiers move on your own paid orders</b> over a rolling 90 days — a rule you can verify from your order history.</span></li>
          <li><i class="fa-solid fa-check"></i><span><b>A tier once earned is kept.</b> A quiet month does not cut your margin.</span></li>
          <li><i class="fa-solid fa-check"></i><span><b>No recruitment requirement.</b> Your discount depends on what you buy, not on who you sign up.</span></li>
        </ul>
      </div>

      <div class="col-lg-7">
        <div class="calc-panel">
          <div class="row g-3">
            <div class="col-md-6 field mb-0">
              <label for="calcProduct">Product</label>
              <select class="select-fc" id="calcProduct">
                <?php foreach ($calcable as $p):
                  $m = Pricing::margin($p, null); ?>
                  <option value="<?= (int) $p['id'] ?>"
                          data-retail="<?= number_format((float) $p['retail_price'], 2, '.', '') ?>"
                          data-cost="<?= number_format($m['cost'], 2, '.', '') ?>"
                          data-rrp="<?= number_format($m['rrp'], 2, '.', '') ?>"
                          data-min="<?= (int) $p['min_reseller_qty'] ?>"
                          <?= (int) $p['id'] === $medianId ? 'selected' : '' ?>>
                    <?= e($p['name']) ?><?= $p['pack_size'] ? ' (' . e($p['pack_size']) . ')' : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-3 field mb-0">
              <label for="calcTier">Your tier</label>
              <select class="select-fc" id="calcTier">
                <?php foreach (RESELLER_TIERS as $k => $t): ?>
                  <option value="<?= number_format($t['off_retail'], 4, '.', '') ?>"
                          <?= ($isRes && $viewer['reseller_tier'] === $k) || (!$isRes && $k === 'starter') ? 'selected' : '' ?>>
                    <?= e($t['label']) ?> · <?= (int) ($t['off_retail'] * 100) ?>%
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-3 field mb-0">
              <label for="calcQty">Units</label>
              <input class="input-fc" type="number" id="calcQty" value="12" min="1" max="500">
            </div>
          </div>

          <div class="calc-out">
            <div><b class="money" id="calcCost">—</b><span>Your stock cost</span></div>
            <div><b class="money" id="calcRevenue">—</b><span>Revenue at RRP</span></div>
            <div><b class="money big" id="calcProfit">—</b><span>Your profit</span></div>
          </div>

          <p class="field__hint mt-3 mb-0" id="calcNote"></p>

          <div class="d-flex gap-2 mt-3 flex-wrap">
            <?php if ($isRes): ?>
              <a href="<?= SITE_URL ?>/shop.php" class="btn-fc">Order stock</a>
              <a href="<?= SITE_URL ?>/price-list.php" class="btn-fc btn-fc--ghost">Printable price list</a>
            <?php elseif ($status === 'none'): ?>
              <a href="#apply" class="btn-fc"><i class="fa-solid fa-pen-to-square"></i> Apply — it is free</a>
              <a href="<?= SITE_URL ?>/price-list.php" class="btn-fc btn-fc--ghost">See the full price list</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── Tiers ────────────────────────────────────────────────────────── -->
<section class="section section--tight">
  <div class="container">
    <div class="reseller-band">
      <div style="position:relative;z-index:1">
        <div class="section__head text-center">
          <span class="eyebrow eyebrow--amber">Four tiers, one rule</span>
          <h2>Your discount follows your rolling 90-day spend</h2>
          <p class="mx-auto" style="max-width:60ch">
            Only paid orders count, and only your own. Nothing here depends on recruiting anyone.
          </p>
        </div>

        <div class="tier-grid">
          <?php foreach (RESELLER_TIERS as $key => $t):
            $mine = $isRes && $viewer['reseller_tier'] === $key; ?>
          <div class="tier" <?= $mine ? 'style="background:rgba(233,162,27,.16);border-color:var(--amber)"' : '' ?>>
            <?php if ($mine): ?><span class="badge-fc badge-fc--amber mb-2">Your tier</span><?php endif; ?>
            <b><?= e($t['label']) ?></b>
            <div class="off"><?= (int) ($t['off_retail'] * 100) ?>%</div>
            <span>off retail</span>
            <div style="margin-top:.7rem;padding-top:.7rem;border-top:1px solid rgba(255,255,255,.14);font-size:.74rem;color:var(--on-dark-2)">
              <?= $t['min_spend'] > 0 ? 'From ' . format_price_short((float) $t['min_spend']) . ' paid<br>in 90 days' : 'From your<br>first order' ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <p class="text-center mt-4 mb-0" style="font-size:.84rem;color:var(--on-dark-2)">
          <?= $approvedCount ?> approved reseller<?= $approvedCount === 1 ? '' : 's' ?> trading on FairCart today.
        </p>
      </div>
    </div>
  </div>
</section>

<!-- ── How it works ─────────────────────────────────────────────────── -->
<section class="section">
  <div class="container">
    <div class="section__head text-center">
      <span class="eyebrow">Getting started</span>
      <h2>Four steps, no salesperson</h2>
    </div>
    <div class="row g-4">
      <?php foreach ([
        ['Create an account', 'Name, email, password. Thirty seconds. You can shop at retail immediately.'],
        ['Apply as a reseller', 'Tell us your trading name, your area and a contact number. We verify by hand — usually the same working day.'],
        ['Order your first stock', 'Wholesale pricing switches on across the whole catalogue. Most lines have a six-unit minimum so we can pack a clean case.'],
        ['Sell, restock, move up', 'Your tier climbs automatically on paid orders. Nothing to claim, nothing to ask for.'],
      ] as $i => [$title, $blurb]): ?>
      <div class="col-lg-3 col-md-6" data-reveal>
        <div class="step-card">
          <span class="step-card__n"><?= $i + 1 ?></span>
          <span><b><?= e($title) ?></b><p><?= e($blurb) ?></p></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php $resellerTiles = get_banners('reseller', 'tall'); ?>
    <?php if ($resellerTiles): ?>
    <div class="row g-4 align-items-center mt-4">
      <div class="col-lg-5">
        <?php $tile = $resellerTiles[0]; $tileHref = banner_link($tile['link_url']); ?>
        <a class="promo-tile" <?= $tileHref ? 'href="' . e($tileHref) . '"' : '' ?>>
          <img src="<?= e(product_image($tile['image'])) ?>" alt="<?= e($tile['alt_text'] ?: $tile['title']) ?>" loading="lazy" decoding="async">
        </a>
      </div>
      <div class="col-lg-7">
        <span class="eyebrow eyebrow--amber">Why people join</span>
        <h2>Wellness products people already want</h2>
        <p class="lede">
          You are not cold-selling something nobody has heard of. Juices, collagen, detox tea, cleaners and
          fragrance are things households restock anyway — your job is being the person they buy them from.
        </p>
        <ul class="pdp-benefits">
          <li><i class="fa-solid fa-check"></i><span>Stock arrives packed and ready to sell — no repackaging, no labelling.</span></li>
          <li><i class="fa-solid fa-check"></i><span>Order what you can move. No minimum spend, no monthly target.</span></li>
          <li><i class="fa-solid fa-check"></i><span>Courier, depot collection or a Runner brings it to you.</span></li>
        </ul>
        <a href="#apply" class="btn-fc mt-2"><i class="fa-solid fa-pen-to-square"></i> Apply to resell</a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- ── Application form ─────────────────────────────────────────────── -->
<section class="section" id="apply" style="background:var(--card);border-block:1px solid var(--line)">
  <div class="container" style="max-width:760px">

    <?php if ($isRes): ?>
      <div class="text-center">
        <div class="empty__icon" style="background:var(--leaf-wash);color:var(--leaf)"><i class="fa-solid fa-circle-check"></i></div>
        <h2>You are already approved</h2>
        <p class="lede">Your wholesale pricing is live across the catalogue.</p>
        <a href="<?= SITE_URL ?>/dashboards/reseller/index.php" class="btn-fc btn-fc--lg">Open your dashboard</a>
      </div>

    <?php elseif ($status === 'pending' || $sent): ?>
      <div class="text-center">
        <div class="empty__icon" style="background:var(--amber-wash);color:var(--amber-dark)"><i class="fa-solid fa-hourglass-half"></i></div>
        <h2>Application received</h2>
        <p class="lede">We verify applications by hand, usually the same working day. You will get an email the moment it is approved.</p>
        <a href="<?= SITE_URL ?>/shop.php" class="btn-fc btn-fc--ghost">Browse while you wait</a>
      </div>

    <?php elseif (!$viewer): ?>
      <div class="text-center">
        <span class="eyebrow">Step one</span>
        <h2>Create your account first</h2>
        <p class="lede mx-auto" style="max-width:52ch">
          The reseller application hangs off your account, so we need that first. It takes thirty seconds and
          the application form opens straight afterwards.
        </p>
        <div class="d-flex gap-2 justify-content-center flex-wrap mt-3">
          <a href="<?= SITE_URL ?>/modules/auth/register.php?intent=reseller" class="btn-fc btn-fc--lg">Create an account</a>
          <a href="<?= SITE_URL ?>/modules/auth/login.php?redirect=<?= urlencode('/reseller.php#apply') ?>" class="btn-fc btn-fc--ghost btn-fc--lg">I already have one</a>
        </div>
      </div>

    <?php else: ?>
      <div class="section__head">
        <span class="eyebrow">Step two</span>
        <h2>Reseller application</h2>
        <p>Five fields. No fee, no stock commitment, no contract term.</p>
      </div>

      <?php foreach ($errors as $err): ?>
        <div class="alert-fc alert-fc--danger"><i class="fa-solid fa-circle-exclamation"></i><span><?= e($err) ?></span></div>
      <?php endforeach; ?>

      <form method="post" class="card-fc card-fc--pad" novalidate>
        <?= csrf_field() ?>
        <div class="row g-3">
          <div class="col-md-6 field">
            <label for="business_name">Trading name</label>
            <input class="input-fc" type="text" id="business_name" name="business_name" required
                   value="<?= e((string) ($_POST['business_name'] ?? $viewer['business_name'] ?? '')) ?>"
                   placeholder="Your business, or your own name">
            <div class="field__hint">A registered company is not required.</div>
          </div>
          <div class="col-md-6 field">
            <label for="trading_area">Where do you trade?</label>
            <input class="input-fc" type="text" id="trading_area" name="trading_area" required
                   value="<?= e((string) ($_POST['trading_area'] ?? '')) ?>"
                   placeholder="Suburb, township or town">
            <div class="field__hint">Helps us tell you which lines move in your area.</div>
          </div>
          <div class="col-md-6 field">
            <label for="phone">Contact number</label>
            <input class="input-fc" type="tel" id="phone" name="phone" required
                   value="<?= e((string) ($_POST['phone'] ?? $viewer['phone'] ?? '')) ?>" placeholder="+27 82 000 0000">
          </div>
          <div class="col-md-6 field">
            <label for="id_number">ID number <span class="text-muted-3 fw-normal">(optional)</span></label>
            <input class="input-fc" type="text" id="id_number" name="id_number"
                   value="<?= e((string) ($_POST['id_number'] ?? '')) ?>" placeholder="Only used to verify your identity">
            <div class="field__hint">Optional. Stored only to verify you, never shared.</div>
          </div>
          <div class="col-md-6 field">
            <label for="sponsor_code">Referral code <span class="text-muted-3 fw-normal">(optional)</span></label>
            <input class="input-fc" type="text" id="sponsor_code" name="sponsor_code" style="text-transform:uppercase"
                   value="<?= e((string) ($_POST['sponsor_code'] ?? '')) ?>" placeholder="FC-XXXXXX">
            <div class="field__hint">If another reseller introduced you. It does not change your pricing.</div>
          </div>
        </div>

        <label class="opt-card mt-2 mb-3">
          <input type="checkbox" name="agree" value="1" required>
          <span class="opt-card__blurb">
            I understand that reseller pricing is for stock I intend to resell, that I set my own selling prices, and that
            I am responsible for complying with South African law when I sell these products —
            including not making medical claims about the supplements.
            I accept the <a href="<?= SITE_URL ?>/terms.php" target="_blank" class="text-leaf fw-bold">terms</a>.
          </span>
        </label>

        <button class="btn-fc btn-fc--lg btn-fc--block" type="submit">
          <i class="fa-solid fa-paper-plane"></i> Submit my application
        </button>
      </form>
    <?php endif; ?>
  </div>
</section>

<!-- ── Reseller voices ──────────────────────────────────────────────── -->
<?php if ($quotes): ?>
<section class="section">
  <div class="container">
    <div class="section__head text-center"><span class="eyebrow">From people already doing it</span><h2>Resellers on FairCart</h2></div>
    <div class="row g-3">
      <?php foreach ($quotes as $t): ?>
      <div class="col-lg-4" data-reveal>
        <div class="quote-card">
          <?= stars_html((float) $t['rating']) ?>
          <p class="mt-2">&ldquo;<?= e($t['quote']) ?>&rdquo;</p>
          <div class="quote-card__who">
            <span class="quote-card__av"><?= e(mb_substr($t['name'], 0, 1)) ?></span>
            <span><b><?= e($t['name']) ?></b><span><?= e(trim(($t['role'] ?? '') . ($t['location'] ? ' · ' . $t['location'] : ''), ' ·')) ?></span></span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ── FAQ ──────────────────────────────────────────────────────────── -->
<?php if ($faqs): ?>
<section class="section" style="background:var(--card);border-top:1px solid var(--line)">
  <div class="container" style="max-width:820px">
    <div class="section__head text-center"><span class="eyebrow">Straight answers</span><h2>Reseller questions</h2></div>
    <?php foreach ($faqs as $f): ?>
    <div class="faq-item">
      <button class="faq-q" type="button" aria-expanded="false"><?= e($f['question']) ?><i class="fa-solid fa-plus"></i></button>
      <div class="faq-a"><p><?= e($f['answer']) ?></p></div>
    </div>
    <?php endforeach; ?>
    <p class="text-center mt-4 mb-0"><a href="<?= SITE_URL ?>/faq.php" class="btn-fc btn-fc--ghost">All FAQs</a></p>
  </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
