<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$brands   = get_brands();
$products = (int) db()->query("SELECT COUNT(*) FROM products WHERE status='published'")->fetchColumn();
$resell   = (int) db()->query("SELECT COUNT(*) FROM users WHERE reseller_status='approved'")->fetchColumn();

$page_title       = 'About FairCart — four South African brands, one delivery';
$meta_description = 'FairCart is a South African online shop for wellness, fragrance, cleaning and pest-control products. Real stock, honest prices, courier delivery nationwide.';
include __DIR__ . '/includes/header.php';
?>

<header class="page-head">
  <div class="container">
    <nav class="crumbs"><a href="<?= SITE_URL ?>/index.php">Home</a><i class="fa-solid fa-chevron-right"></i><span>About</span></nav>
    <h1>Four South African brands.<br>One basket. One delivery.</h1>
    <p>
      FairCart is an online shop for the wellness, fragrance and household products South African
      homes actually restock — brought together in one place so you order once and it arrives once.
    </p>
  </div>
</header>

<section class="section">
  <div class="container">
    <div class="row g-5 align-items-center">
      <div class="col-lg-6">
        <span class="eyebrow">Why we built it</span>
        <h2>Buying these used to mean four separate orders</h2>
        <div class="prose">
          <p>
            The products we carry were sold the hard way: a price list photographed and forwarded through
            WhatsApp groups, a different person to message for each brand, no way to know what was in stock
            until your money had already moved, and no single delivery to wait for.
          </p>
          <p>
            FairCart puts all four catalogues at one address with live stock, one basket, one payment and one
            delivery. You can see the price before you commit, the stock level before you order, and exactly
            when it will reach you.
          </p>
          <p>
            No membership, no subscription, no minimum spend. Buy one bottle of juice if that is all you want —
            the price is the same either way.
          </p>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="row g-3">
          <div class="col-6"><img src="<?= SITE_URL ?>/assets/img/editorial/juice-trio.jpg" alt="Blossom juice range" style="border-radius:var(--r-lg);width:100%;aspect-ratio:3/4;object-fit:cover"></div>
          <div class="col-6 d-flex flex-column gap-3">
            <img src="<?= SITE_URL ?>/assets/img/products/mold-stopper-remover.jpg" alt="ML Group Mold Stopper" style="border-radius:var(--r-lg);width:100%;aspect-ratio:1;object-fit:cover">
            <img src="<?= SITE_URL ?>/assets/img/editorial/rise-results.jpg" alt="Rise cleaner results" style="border-radius:var(--r-lg);width:100%;aspect-ratio:1;object-fit:cover">
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section section--tight" style="background:var(--card);border-block:1px solid var(--line)">
  <div class="container">
    <div class="row g-3 text-center">
      <?php foreach ([
        [(string) count($brands), 'South African brands'],
        [(string) $products, 'products in the catalogue'],
        ['2–4', 'day courier, nationwide'],
        ['7', 'day returns on unopened stock'],
      ] as [$n, $label]): ?>
      <div class="col-lg-3 col-6">
        <div class="card-fc card-fc--pad h-100">
          <div class="money" style="font-size:2.2rem;color:var(--leaf);line-height:1.1"><?= e($n) ?></div>
          <div class="text-muted-2" style="font-size:.84rem;font-weight:600"><?= e($label) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section__head text-center">
      <span class="eyebrow">What we stock</span>
      <h2>Four suppliers, named</h2>
      <p class="mx-auto">No white-label mystery boxes, no drop-shipped anything. Every product here comes from one of these four.</p>
    </div>
    <div class="row g-3">
      <?php foreach ($brands as $b): ?>
      <div class="col-lg-6" data-reveal>
        <a class="card-fc card-fc--pad card-fc--hover d-block h-100" href="<?= SITE_URL ?>/shop.php?brand=<?= e($b['slug']) ?>">
          <div class="d-flex align-items-center gap-2 mb-2">
            <span style="width:14px;height:14px;border-radius:50%;background:<?= e($b['accent']) ?>"></span>
            <b style="font-family:var(--font-display);font-size:1.15rem"><?= e($b['name']) ?></b>
            <span class="badge-fc badge-fc--muted ms-auto"><?= (int) $b['product_count'] ?> products</span>
          </div>
          <div class="eyebrow" style="color:<?= e($b['accent']) ?>"><?= e((string) $b['tagline']) ?></div>
          <p style="font-size:.9rem;color:var(--text-2);margin:0"><?= e((string) $b['blurb']) ?></p>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" style="background:var(--card);border-block:1px solid var(--line)">
  <div class="container">
    <div class="section__head text-center">
      <span class="eyebrow">How we try to behave</span>
      <h2>Four things we will not do</h2>
    </div>
    <div class="row g-3">
      <?php foreach ([
        ['fa-ban', 'Make medical claims', 'The supplements here are Category D complementary medicines. They have not been evaluated by SAHPRA. We will not describe them as treating or curing anything, and resellers agree not to either.'],
        ['fa-tags', 'Move the price at checkout', 'The price on the product page is the price you pay. Delivery is shown before you commit, and there are no fees that appear on the last step.'],
        ['fa-box-open', 'Sell you stock we do not have', 'Stock levels on this site are live. If something is out of stock it says so and you can ask to be emailed — we will not take your money and leave you waiting.'],
        ['fa-user-slash', 'Sell or rent your details', 'No advertising trackers on this site, and your information never gets passed to anyone except the courier bringing your parcel.'],
      ] as [$icon, $title, $body]): ?>
      <div class="col-lg-6" data-reveal>
        <div class="card-fc card-fc--pad h-100 d-flex gap-3">
          <span style="width:44px;height:44px;display:grid;place-items:center;background:var(--coral-wash);color:var(--coral);border-radius:var(--r);flex-shrink:0">
            <i class="fa-solid <?= $icon ?>"></i>
          </span>
          <span>
            <b style="font-family:var(--font-display);font-size:1rem;display:block;margin-bottom:.25rem"><?= e($title) ?></b>
            <span style="font-size:.88rem;color:var(--text-2)"><?= e($body) ?></span>
          </span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section section--tight">
  <div class="container">
    <div class="card-fc" style="padding:clamp(2rem,4vw,3rem);text-align:center;background:linear-gradient(135deg,var(--leaf-wash),var(--card))">
      <h2>Have a look at what is on the shelf</h2>
      <p class="lede mx-auto mb-4" style="max-width:54ch">
        Every product, every price, in one public list — or browse the shop and see what is in stock right now.
      </p>
      <div class="d-flex gap-2 justify-content-center flex-wrap">
        <a href="<?= SITE_URL ?>/shop.php" class="btn-fc btn-fc--lg">Shop the catalogue</a>
        <a href="<?= SITE_URL ?>/price-list.php" class="btn-fc btn-fc--ghost btn-fc--lg">See the price list</a>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
