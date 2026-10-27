<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$repo        = new ProductRepository();
$viewer      = current_user();
$isRes       = Pricing::isReseller($viewer);
$wish        = $viewer ? wishlist_ids((int) $viewer['id']) : [];

$featured    = $repo->getFeatured(8);
$bestsellers = $repo->getBestsellers(8);
$categories  = get_categories();
$brands      = get_brands();

$testimonials = db()->query("SELECT * FROM testimonials WHERE status='published' ORDER BY sort_order, id LIMIT 6")->fetchAll();
$posts        = get_blogs([], 1, 3)['items'];

$productCount = (int) db()->query("SELECT COUNT(*) FROM products WHERE status='published'")->fetchColumn();
$resellerCount= (int) db()->query("SELECT COUNT(*) FROM users WHERE reseller_status='approved'")->fetchColumn();
// Units on PAID orders only, so the counter can never quote demo or abandoned orders.
$soldCount    = (int) db()->query("SELECT COALESCE(SUM(i.qty),0) FROM order_items i JOIN orders o ON o.id = i.order_id WHERE o.payment_status = 'paid'")->fetchColumn();
$cardPay      = IKhokha::enabled();

$page_title       = 'FairCart — wellness, fragrance and home care delivered across South Africa';
$meta_description = 'Shop Blossom, Pure Fragrances, Rise and ML Group in one place. Juices, collagen, detox tea, body oils, perfume and cleaning — fair prices, free delivery over R1,500, 2–4 day courier nationwide.';
$json_ld = [
    '@type' => 'WebSite',
    'name'  => SITE_NAME,
    'url'   => SITE_URL,
    'potentialAction' => [
        '@type'       => 'SearchAction',
        'target'      => SITE_URL . '/shop.php?q={search_term_string}',
        'query-input' => 'required name=search_term_string',
    ],
];
include __DIR__ . '/includes/header.php';
?>

<!-- ── Promotions carousel ──────────────────────────────────────────── -->
<div style="padding-top:1.25rem">
  <?php $carousel_page = 'home'; include __DIR__ . '/includes/banner-carousel.php'; ?>
</div>

<!-- ── Hero ─────────────────────────────────────────────────────────── -->
<section class="hero" style="margin-top:1.5rem">
  <div class="container hero__grid">
    <div>
      <span class="badge-fc badge-fc--amber mb-3">
        <i class="fa-solid fa-location-dot"></i> Proudly South African · 4 brands · <?= $productCount ?> products
      </span>

      <h1>Wellness that actually<br>arrives at your <em>door</em>.</h1>

      <p class="hero__lede">
        Juices, collagen, detox tea, body oils, fine fragrance and the cleaners that do the work —
        four South African brands in one basket, one delivery, one honest price.
      </p>

      <div class="hero__cta">
        <a href="<?= SITE_URL ?>/shop.php" class="btn-fc btn-fc--lg">
          <i class="fa-solid fa-basket-shopping"></i> Shop the catalogue
        </a>
        <a href="<?= SITE_URL ?>/shop.php?sort=popular" class="btn-fc btn-fc--on-dark btn-fc--lg">
          <i class="fa-solid fa-fire"></i> Shop best sellers
        </a>
      </div>

      <div class="hero__stats">
        <div class="hero__stat"><b><?= $productCount ?></b><span>PRODUCTS IN STOCK</span></div>
        <div class="hero__stat"><b>Free</b><span>DELIVERY OVER <?= format_price_short((float) get_setting('free_shipping_threshold', '1500')) ?></span></div>
        <div class="hero__stat"><b>2–4 days</b><span>COURIER, NATIONWIDE</span></div>
      </div>
    </div>

    <?php
    // The hero leads with the thing people came to buy: a real best seller at
    // its real price, priced for whoever is looking.
    $heroProduct = $repo->getBySlug('lady-bloom-juice') ?: ($bestsellers[0] ?? $featured[0] ?? null);
    $heroStats   = $heroProduct ? $repo->reviewStats((int) $heroProduct['id']) : null;
    ?>
    <div class="hero__art">
      <figure class="hero__shot">
        <img src="<?= SITE_URL ?>/assets/img/editorial/juice-trio.jpg" alt="Blossom Luna, Lady and King Bloom juice bottles">
      </figure>

      <?php if ($heroProduct): ?>
      <a class="hero__buy-card" href="<?= SITE_URL ?>/modules/shop/product.php?slug=<?= e($heroProduct['slug']) ?>"
         aria-label="Shop <?= e($heroProduct['name']) ?>">
        <div class="hero__buy-head">
          <img src="<?= e(product_image($heroProduct['featured_image'])) ?>" alt="">
          <span>
            <b><?= e($heroProduct['name']) ?></b>
            <span><?= e((string) $heroProduct['pack_size']) ?> · <?= e((string) $heroProduct['brand_name']) ?></span>
          </span>
        </div>
        <div class="hero__buy-rows">
          <?php if ($heroStats && $heroStats['count'] > 0): ?>
            <div><span><?= stars_html($heroStats['average']) ?></span><b style="font-size:.8rem;color:var(--text-2)"><?= $heroStats['count'] ?> review<?= $heroStats['count'] === 1 ? '' : 's' ?></b></div>
          <?php endif; ?>
          <div class="is-price">
            <span>In stock now</span>
            <b class="money"><?= format_price_short(Pricing::effective($heroProduct, $viewer)) ?></b>
          </div>
        </div>
        <p class="hero__buy-foot">
          <i class="fa-solid fa-truck-fast"></i> Ordered today, on its way tomorrow.
        </p>
      </a>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ── Trust strip ──────────────────────────────────────────────────── -->
<section class="trust">
  <div class="container trust__grid">
    <div class="trust__item"><i class="fa-solid fa-truck-fast"></i><span><b>Free delivery over <?= format_price_short((float) get_setting('free_shipping_threshold', '1500')) ?></b><span><?= Pudo::enabled() ? 'The Courier Guy to your door or a Pudo locker' : 'Or collect free from Randburg' ?></span></span></div>
    <div class="trust__item"><i class="fa-solid fa-person-running"></i><span><b>Runner pickup</b><span>Every handover logged, <?= format_price_short((float) get_setting('runner_fee', '20')) ?></span></span></div>
    <div class="trust__item"><i class="fa-solid <?= $cardPay ? 'fa-credit-card' : 'fa-building-columns' ?>"></i><span><b><?= $cardPay ? 'Pay by card or EFT' : 'Pay by EFT' ?></b><span><?= $cardPay ? 'Secured by iKhokha, confirmed instantly' : 'Upload your POP, confirmed in a day' ?></span></span></div>
    <div class="trust__item"><i class="fa-solid fa-rotate-left"></i><span><b>7-day returns</b><span>Unopened stock, no questions</span></span></div>
  </div>
</section>

<!-- ── Categories ───────────────────────────────────────────────────── -->
<section class="section">
  <div class="container">
    <div class="section__head d-flex flex-wrap justify-content-between align-items-end gap-3">
      <div>
        <span class="eyebrow">Shop by category</span>
        <h2>Eight aisles, one basket</h2>
        <p>Four suppliers, one delivery. Mix wellness with cleaning with car care and pay for one order.</p>
      </div>
      <a href="<?= SITE_URL ?>/shop.php" class="btn-fc btn-fc--ghost">All products <i class="fa-solid fa-arrow-right"></i></a>
    </div>

    <div class="row g-3">
      <?php foreach ($categories as $c): ?>
      <div class="col-lg-3 col-md-4 col-sm-6" data-reveal>
        <a class="cat-tile" href="<?= SITE_URL ?>/shop.php?category=<?= e($c['slug']) ?>">
          <i class="fa-solid <?= e($c['icon'] ?: 'fa-tag') ?>"></i>
          <b><?= e($c['name']) ?></b>
          <span><?= e($c['blurb'] ?? '') ?></span>
          <span class="count"><?= (int) $c['product_count'] ?> products <i class="fa-solid fa-arrow-right ms-1"></i></span>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── Featured ─────────────────────────────────────────────────────── -->
<section class="section section--tight" style="background:var(--card);border-block:1px solid var(--line)">
  <div class="container">
    <div class="section__head d-flex flex-wrap justify-content-between align-items-end gap-3">
      <div>
        <span class="eyebrow">Hand-picked</span>
        <h2>What we would put on the shelf first</h2>
        <?php if ($isRes): ?>
          <p>Your wholesale price is showing, with the rand you make at the recommended selling price.</p>
        <?php else: ?>
          <p>The lines that move fastest across all four brands.</p>
        <?php endif; ?>
      </div>
      <a href="<?= SITE_URL ?>/shop.php?sort=featured" class="btn-fc btn-fc--ghost">See all <i class="fa-solid fa-arrow-right"></i></a>
    </div>

    <div class="grid-products">
      <?php foreach ($featured as $product):
        $in_wishlist = in_array((int) $product['id'], $wish, true);
        include __DIR__ . '/includes/product-card.php';
      endforeach; ?>
    </div>
  </div>
</section>

<!-- ── Why shop with us ─────────────────────────────────────────────── -->
<section class="section">
  <div class="container">
    <div class="reseller-band" data-reveal>
      <div class="row g-4 align-items-center" style="position:relative;z-index:1">
        <div class="col-lg-6">
          <span class="badge-fc badge-fc--amber mb-3">Real stock · real people · real delivery</span>
          <h2>Order today. Wear it, drink it, clean with it this week.</h2>
          <p class="mb-4" style="max-width:52ch">
            Everything on this site is stock we actually hold. You see the real price, the real
            stock level and a real delivery window before you pay — no back-orders dressed up as
            "shipping soon", no prices that change at checkout.
          </p>

          <div class="d-flex gap-2 flex-wrap mb-4">
            <a href="<?= SITE_URL ?>/shop.php" class="btn-fc btn-fc--amber btn-fc--lg">
              <i class="fa-solid fa-basket-shopping"></i> Start shopping
            </a>
            <a href="<?= SITE_URL ?>/price-list.php" class="btn-fc btn-fc--on-dark btn-fc--lg">See every price</a>
          </div>

          <?php if ($soldCount >= 100): ?>
          <p style="font-size:.82rem;color:var(--on-dark-2);margin:0">
            <i class="fa-solid fa-box-open me-1"></i>
            <?= number_format($soldCount) ?> units shipped to South African doors so far.
          </p>
          <?php endif; ?>
        </div>

        <div class="col-lg-6">
          <div class="tier-grid">
            <?php foreach ([
              ['fa-truck-fast',   '2–4', 'day courier',      'nationwide'],
              ['fa-store',        'Free', 'depot collection', 'in Randburg'],
              ['fa-rotate-left',  '7',   'day returns',      'unopened stock'],
              ['fa-lock',         $cardPay ? 'Card' : 'EFT',  'secure payment',  $cardPay ? 'iKhokha · or EFT' : 'POP confirmed in a day'],
            ] as [$icon, $big, $label, $sub]): ?>
            <div class="tier">
              <i class="fa-solid <?= $icon ?>" style="color:var(--amber);font-size:1.05rem;margin-bottom:.35rem"></i>
              <div class="off" style="font-size:1.4rem"><?= $big ?></div>
              <b style="font-size:.82rem"><?= $label ?></b>
              <div style="margin-top:.35rem;font-size:.7rem;color:var(--on-dark-2)"><?= $sub ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── Best sellers ─────────────────────────────────────────────────── -->
<?php if ($bestsellers): ?>
<section class="section section--tight">
  <div class="container">
    <div class="section__head d-flex flex-wrap justify-content-between align-items-end gap-3">
      <div>
        <span class="eyebrow eyebrow--amber">Moving fastest</span>
        <h2>Best sellers</h2>
        <p>Ranked on what has actually shipped, not on what we would like to shift.</p>
      </div>
      <a href="<?= SITE_URL ?>/shop.php?sort=popular" class="btn-fc btn-fc--ghost">Full ranking <i class="fa-solid fa-arrow-right"></i></a>
    </div>

    <div class="grid-products">
      <?php foreach ($bestsellers as $product):
        $in_wishlist = in_array((int) $product['id'], $wish, true);
        include __DIR__ . '/includes/product-card.php';
      endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ── Brands ───────────────────────────────────────────────────────── -->
<section class="section" style="background:var(--card);border-block:1px solid var(--line)">
  <div class="container">
    <div class="section__head text-center">
      <span class="eyebrow">The suppliers</span>
      <h2>Four brands we actually stock</h2>
      <p class="mx-auto">Everything on FairCart comes from one of these four. No drop-shipped mystery boxes.</p>
    </div>

    <div class="row g-3">
      <?php foreach ($brands as $b): ?>
      <div class="col-lg-3 col-md-6" data-reveal>
        <a class="card-fc card-fc--pad card-fc--hover d-block h-100" href="<?= SITE_URL ?>/shop.php?brand=<?= e($b['slug']) ?>">
          <div class="d-flex align-items-center gap-2 mb-2">
            <span style="width:12px;height:12px;border-radius:50%;background:<?= e($b['accent']) ?>"></span>
            <b style="font-family:var(--font-display);font-size:1.1rem"><?= e($b['name']) ?></b>
          </div>
          <div class="eyebrow" style="color:<?= e($b['accent']) ?>;margin-bottom:.5rem"><?= e($b['tagline'] ?? '') ?></div>
          <p style="font-size:.86rem;color:var(--text-2)"><?= e($b['blurb'] ?? '') ?></p>
          <div style="font-size:.78rem;font-weight:700;color:var(--leaf)">
            <?= (int) $b['product_count'] ?> products <i class="fa-solid fa-arrow-right ms-1"></i>
          </div>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── How it works ─────────────────────────────────────────────────── -->
<section class="section">
  <div class="container">
    <div class="section__head text-center">
      <span class="eyebrow">How ordering works</span>
      <h2>Pay, then choose how you get it</h2>
      <p class="mx-auto">Delivery to a Pudo locker or your door with The Courier Guy, or collect. Priced honestly.</p>
    </div>

    <div class="row g-3">
      <?php $methods = fulfilment_methods(); foreach ($methods as $key => $m):
        $cost = fulfilment_price_text($key); ?>
      <div class="<?= count($methods) > 3 ? 'col-md-6 col-lg-3' : 'col-lg-4' ?>" data-reveal>
        <div class="card-fc card-fc--pad h-100">
          <i class="fa-solid <?= e($m['icon']) ?>" style="width:46px;height:46px;display:grid;place-items:center;background:var(--leaf-wash);color:var(--leaf);border-radius:var(--r);font-size:1.1rem;margin-bottom:.9rem"></i>
          <h3 style="font-size:1.05rem"><?= e($m['label']) ?></h3>
          <p style="font-size:.88rem;color:var(--text-2)"><?= e($m['blurb']) ?></p>
          <div class="badge-fc badge-fc--leaf"><?= e($cost) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── Testimonials ─────────────────────────────────────────────────── -->
<?php if ($testimonials): ?>
<section class="section" style="background:var(--card);border-block:1px solid var(--line)">
  <div class="container">
    <div class="section__head text-center">
      <span class="eyebrow">Verified buyers</span>
      <h2>What customers say</h2>
    </div>
    <div class="row g-3">
      <?php foreach ($testimonials as $t): ?>
      <div class="col-lg-4 col-md-6" data-reveal>
        <div class="quote-card">
          <?= stars_html((float) $t['rating']) ?>
          <p class="mt-2">&ldquo;<?= e($t['quote']) ?>&rdquo;</p>
          <div class="quote-card__who">
            <span class="quote-card__av"><?= e(mb_substr($t['name'], 0, 1)) ?></span>
            <span>
              <b><?= e($t['name']) ?></b>
              <span><?= e(trim(($t['role'] ?? '') . ($t['location'] ? ' · ' . $t['location'] : ''), ' ·')) ?></span>
            </span>
            <?php if ($t['is_reseller']): ?>
              <span class="badge-fc badge-fc--amber ms-auto">Trade</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ── Learn ────────────────────────────────────────────────────────── -->
<?php if ($posts): ?>
<section class="section">
  <div class="container">
    <div class="section__head d-flex flex-wrap justify-content-between align-items-end gap-3">
      <div>
        <span class="eyebrow">Learn</span>
        <h2>Worth reading before you order</h2>
        <p>Pricing, label literacy and how to get the most out of what you buy.</p>
      </div>
      <a href="<?= SITE_URL ?>/modules/blog/index.php" class="btn-fc btn-fc--ghost">All articles <i class="fa-solid fa-arrow-right"></i></a>
    </div>

    <div class="row g-3">
      <?php foreach ($posts as $post): ?>
      <div class="col-lg-4 col-md-6" data-reveal>
        <a class="post-card" href="<?= SITE_URL ?>/modules/blog/post.php?slug=<?= e($post['slug']) ?>">
          <div class="post-card__media">
            <img src="<?= e(product_image($post['image'])) ?>" alt="<?= e($post['title']) ?>" loading="lazy">
          </div>
          <div class="post-card__body">
            <span class="badge-fc badge-fc--muted mb-2" style="align-self:flex-start"><?= e($post['category']) ?></span>
            <h3><?= e($post['title']) ?></h3>
            <p><?= e(excerpt($post['excerpt'], 110)) ?></p>
            <div class="post-card__meta">
              <span><i class="fa-regular fa-clock me-1"></i><?= (int) $post['read_mins'] ?> min read</span>
              <span><?= date('j M Y', strtotime($post['created_at'])) ?></span>
            </div>
          </div>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ── Closing CTA ──────────────────────────────────────────────────── -->
<section class="section section--tight">
  <div class="container">
    <div class="card-fc" style="padding:clamp(2rem,4vw,3rem);text-align:center;background:linear-gradient(135deg,var(--leaf-wash),var(--card))">
      <span class="eyebrow">Ready when you are</span>
      <h2 class="mb-2">Your basket is a few clicks away.</h2>
      <p class="lede mx-auto mb-4" style="max-width:56ch">
        <?= $productCount ?> products across four South African brands, priced honestly and packed the
        day you pay. Free courier delivery over <?= format_price_short((float) get_setting('free_shipping_threshold', '1500')) ?>.
      </p>
      <div class="d-flex gap-2 justify-content-center flex-wrap">
        <a href="<?= SITE_URL ?>/shop.php" class="btn-fc btn-fc--lg">Shop now</a>
        <a href="<?= SITE_URL ?>/shop.php?sort=popular" class="btn-fc btn-fc--ghost btn-fc--lg">Browse best sellers</a>
      </div>
    </div>
  </div>
</section>

<!-- ── Trade enquiry strip ──────────────────────────────────────────── -->
<?php if (!$isRes): ?>
<section class="section--tight" style="padding-bottom:2rem">
  <div class="container">
    <div class="card-fc" style="padding:1rem 1.4rem;display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
      <i class="fa-solid fa-shop" style="width:38px;height:38px;display:grid;place-items:center;background:var(--paper-2);color:var(--text-2);border-radius:var(--r-pill);flex-shrink:0"></i>
      <div style="flex:1;min-width:220px">
        <b style="font-size:.92rem">Buying in bulk for a shop, salon or stall?</b>
        <span class="d-block text-muted-2" style="font-size:.85rem">
          We supply trade customers at wholesale prices. Registration is free.
        </span>
      </div>
      <a href="<?= SITE_URL ?>/reseller.php" class="btn-fc btn-fc--ghost btn-fc--sm">Trade enquiries</a>
    </div>
  </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
