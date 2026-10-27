<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$viewer   = current_user();
$isRes    = Pricing::isReseller($viewer);
$items    = cart_items();
$subtotal = cart_subtotal();
$resale   = cart_resale_value();

$couponCode = $_SESSION['coupon'] ?? null;
$discount   = 0.0;
if ($couponCode) {
    $check = validate_coupon($couponCode, $subtotal, $isRes ? 'reseller' : 'retail');
    if ($check['valid']) {
        $discount = (float) $check['discount'];
    } else {
        // The basket changed underneath the code — drop it rather than showing
        // a discount the checkout will silently refuse.
        unset($_SESSION['coupon'], $couponCode);
        $couponCode = null;
    }
}

$freeAt     = (float) get_setting('free_shipping_threshold', '1500');
$flatRate   = (float) get_setting('shipping_flat_rate', '120');
$toFree     = max(0.0, $freeAt - $subtotal);
$estShip    = $subtotal >= $freeAt ? 0.0 : $flatRate;
$estTotal   = max(0.0, $subtotal - $discount + $estShip);

$page_title       = 'Your basket — ' . SITE_NAME;
$meta_description = 'Review your FairCart basket before checkout.';
include __DIR__ . '/includes/header.php';
?>

<header class="page-head">
  <div class="container">
    <nav class="crumbs"><a href="<?= SITE_URL ?>/index.php">Home</a><i class="fa-solid fa-chevron-right"></i><span>Basket</span></nav>
    <h1>Your basket</h1>
    <p><?= cart_count() ?> item<?= cart_count() === 1 ? '' : 's' ?><?= $isRes ? ' at your ' . e(strtolower(RESELLER_TIERS[$viewer['reseller_tier']]['label'])) . ' wholesale price' : '' ?>.</p>
  </div>
</header>

<div class="section section--tight">
  <div class="container">

    <?php if (!$items): ?>
      <div class="empty card-fc">
        <div class="empty__icon"><i class="fa-solid fa-basket-shopping"></i></div>
        <h2>Your basket is empty</h2>
        <p class="mb-4">Nothing in here yet. The catalogue is a good place to start.</p>
        <a href="<?= SITE_URL ?>/shop.php" class="btn-fc btn-fc--lg">Browse the catalogue</a>
      </div>

    <?php else: ?>
      <div class="cart-layout">

        <!-- ── Lines ─────────────────────────────────────────────── -->
        <div class="card-fc" style="padding:0 1.4rem">
          <?php foreach ($items as $item):
            $p = $item['product'];
            $lineMargin = $isRes ? Pricing::margin($p, $viewer) : null; ?>
          <div class="cart-line">
            <a class="cart-line__img" href="<?= SITE_URL ?>/modules/shop/product.php?slug=<?= e($p['slug']) ?>">
              <img src="<?= e(product_image($item['image'])) ?>" alt="<?= e($p['name']) ?>"
                   onerror="this.onerror=null;this.src='<?= SITE_URL ?>/assets/img/placeholder.svg'">
            </a>

            <div>
              <a href="<?= SITE_URL ?>/modules/shop/product.php?slug=<?= e($p['slug']) ?>">
                <div class="cart-line__name"><?= e($p['name']) ?></div>
              </a>
              <div class="cart-line__meta">
                <?= e((string) $p['brand_name']) ?>
                <?php if ($item['variant_text']): ?> · <?= e($item['variant_text']) ?><?php endif; ?>
                <?php if ($p['pack_size']): ?> · <?= e($p['pack_size']) ?><?php endif; ?>
              </div>

              <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="qty">
                  <button type="button" data-step="down" aria-label="Decrease"><i class="fa-solid fa-minus"></i></button>
                  <input type="number" value="<?= (int) $item['qty'] ?>" min="1" max="999"
                         data-cart-qty="<?= e($item['key']) ?>" aria-label="Quantity for <?= e($p['name']) ?>">
                  <button type="button" data-step="up" aria-label="Increase"><i class="fa-solid fa-plus"></i></button>
                </div>
                <span class="text-muted-3" style="font-size:.82rem"><?= format_price($item['unit_price']) ?> each</span>
                <button class="btn-fc btn-fc--sm" type="button" data-cart-remove="<?= e($item['key']) ?>"
                        style="background:none;color:var(--text-3);padding:.3rem .5rem">
                  <i class="fa-solid fa-trash-can"></i> Remove
                </button>
              </div>

              <?php if ($lineMargin && $lineMargin['profit'] > 0): ?>
                <div class="badge-fc badge-fc--amber mt-2">
                  <i class="fa-solid fa-arrow-trend-up"></i>
                  Sells for <?= format_price_short($item['line_rrp']) ?> · you keep
                  <?= format_price_short($item['line_rrp'] - $item['line_total']) ?>
                </div>
              <?php endif; ?>
            </div>

            <div class="cart-line__right">
              <span class="money" style="font-size:1.1rem"><?= format_price($item['line_total']) ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- ── Summary ───────────────────────────────────────────── -->
        <div class="summary">
          <div class="card-fc card-fc--pad">
            <h2 style="font-size:1.15rem">Order summary</h2>

            <?php if ($toFree > 0): ?>
              <div class="ship-meter">
                <i class="fa-solid fa-truck-fast me-1"></i>
                Add <b><?= format_price_short($toFree) ?></b> for free courier delivery.
                <div class="ship-meter__bar"><div class="ship-meter__fill" style="width:<?= min(100, (int) round($subtotal / $freeAt * 100)) ?>%"></div></div>
              </div>
            <?php else: ?>
              <div class="ship-meter"><i class="fa-solid fa-circle-check me-1"></i><b>Free courier delivery</b> unlocked on this order.</div>
            <?php endif; ?>

            <form id="couponForm" class="coupon-row">
              <input class="input-fc" type="text" id="couponCode" placeholder="Promo code"
                     value="<?= e((string) $couponCode) ?>" style="text-transform:uppercase" aria-label="Promo code">
              <button class="btn-fc btn-fc--ghost" type="submit">Apply</button>
            </form>
            <div id="couponMsg" style="font-size:.8rem;margin-bottom:.6rem"></div>

            <div class="summary-row"><span>Subtotal</span><b><?= format_price($subtotal) ?></b></div>

            <?php if ($discount > 0): ?>
              <div class="summary-row" style="color:var(--leaf)">
                <span>Discount (<?= e((string) $couponCode) ?>) <a href="#" id="couponRemove" class="text-muted-3 ms-1" aria-label="Remove code"><i class="fa-solid fa-xmark"></i></a></span>
                <b style="color:var(--leaf)">−<?= format_price($discount) ?></b>
              </div>
            <?php endif; ?>

            <div class="summary-row">
              <span>Delivery <span class="text-muted-3">(estimate)</span></span>
              <b><?= $estShip > 0 ? format_price($estShip) : 'Free' ?></b>
            </div>

            <div class="summary-total">
              <span>Total</span>
              <b class="money"><?= format_price($estTotal) ?></b>
            </div>

            <p class="field__hint">Choose collection or Runner pickup at checkout to change the delivery figure.</p>

            <a href="<?= SITE_URL ?>/checkout.php" class="btn-fc btn-fc--block btn-fc--lg mt-2">
              Checkout <i class="fa-solid fa-arrow-right"></i>
            </a>
            <a href="<?= SITE_URL ?>/shop.php" class="btn-fc btn-fc--light btn-fc--block mt-2">Keep shopping</a>
          </div>

          <?php if ($isRes && $resale > $subtotal): ?>
          <div class="card-fc card-fc--pad mt-3" style="background:var(--amber-wash);border-color:#F0DFB6">
            <div class="eyebrow eyebrow--amber"><i class="fa-solid fa-arrow-trend-up me-1"></i>What this basket is worth</div>
            <div class="d-flex justify-content-between align-items-baseline mt-2">
              <span style="font-size:.86rem;color:var(--text-2)">Resale value at RRP</span>
              <b class="money"><?= format_price($resale) ?></b>
            </div>
            <div class="d-flex justify-content-between align-items-baseline mt-1 pt-2" style="border-top:1px dashed #E3CE9E">
              <span style="font-size:.86rem;font-weight:700;color:var(--amber-dark)">Your profit if it all sells</span>
              <b class="money" style="color:var(--leaf);font-size:1.4rem">+<?= format_price($resale - $subtotal) ?></b>
            </div>
          </div>
          <?php elseif (!$isRes): ?>
          <div class="card-fc card-fc--pad mt-3" style="background:var(--leaf-wash);border-color:#BFE3D5">
            <b style="font-family:var(--font-display);display:block;margin-bottom:.5rem">What happens next</b>
            <ul style="font-size:.85rem;color:var(--text-2);margin:0;padding-left:1.1rem;line-height:1.8">
              <li>Place the order — nothing is charged on the site.</li>
              <li>Pay by EFT using your order number as the reference.</li>
              <li>Upload your proof of payment; we confirm within a working day.</li>
              <li>Your stock is held for 48 hours while we wait.</li>
            </ul>
          </div>
          <?php endif; ?>

          <div class="d-flex gap-3 justify-content-center mt-3 text-muted-3" style="font-size:.76rem">
            <span><i class="fa-solid fa-lock me-1"></i>Secure checkout</span>
            <span><i class="fa-solid fa-rotate-left me-1"></i>7-day returns</span>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
