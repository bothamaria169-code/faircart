<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

/**
 * A print-ready invoice. The order number is the credential — it is
 * unguessable, and guests must be able to print their own invoice without an
 * account. An admin can open any invoice; nobody else can enumerate them.
 */
$repo   = new OrderRepository();
$number = trim((string) ($_GET['order'] ?? ''));
$order  = $number !== '' ? $repo->getByNumber($number) : null;

if (!$order) {
    http_response_code(404);
    exit('Invoice not found.');
}

$items    = $repo->getItems((int) $order['id']);
$isRes    = $order['order_type'] === 'reseller';
$issued   = date('j F Y', strtotime($order['created_at']));
$paid     = $order['payment_status'] === 'paid';
?>
<!doctype html>
<html lang="en-ZA">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice <?= e($order['order_number']) ?> — FairCart</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/svg+xml" href="<?= SITE_URL ?>/assets/img/logo/faircart-mark.svg">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root {
    --ink: #0E1726; --leaf: #12805C; --amber: #E9A21B;
    --text: #101A2B; --text-2: #4A5768; --text-3: #7C8899;
    --line: #E3E8EF; --paper: #F6F7F9;
    --display: 'Bricolage Grotesque', Georgia, serif;
    --sans: 'Plus Jakarta Sans', system-ui, Arial, sans-serif;
  }
  * { box-sizing: border-box; }
  body { margin: 0; background: var(--paper); font-family: var(--sans); color: var(--text); font-size: 14px; line-height: 1.6; }
  .sheet { max-width: 860px; margin: 24px auto; background: #fff; padding: 44px; border-radius: 14px; box-shadow: 0 6px 24px rgba(16,26,43,.08); }

  .top { display: flex; justify-content: space-between; gap: 24px; flex-wrap: wrap; padding-bottom: 24px; border-bottom: 2px solid var(--ink); }
  .brand { display: flex; align-items: center; gap: 10px; }
  .brand img { height: 40px; }
  .brand b { font-family: var(--display); font-size: 1.5rem; letter-spacing: -.03em; }
  .brand b em { font-style: normal; color: var(--leaf); }
  .brand small { display: block; color: var(--text-3); font-size: 11px; }

  .doc { text-align: right; }
  .doc h1 { font-family: var(--display); font-size: 1.8rem; margin: 0 0 4px; letter-spacing: -.02em; }
  .doc .no { font-family: ui-monospace, Consolas, monospace; font-weight: 700; color: var(--leaf); font-size: 15px; }
  .doc .meta { font-size: 12px; color: var(--text-3); }

  .stamp { display: inline-block; padding: 5px 14px; border-radius: 999px; font-size: 11px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; margin-top: 8px; }
  .stamp--paid   { background: #E7F4EF; color: #0C6247; border: 1px solid #BFE3D5; }
  .stamp--unpaid { background: #FDF3DF; color: #C4820C; border: 1px solid #F0DFB6; }

  .cols { display: flex; gap: 40px; flex-wrap: wrap; padding: 24px 0; }
  .cols > div { flex: 1; min-width: 220px; }
  .lbl { font-size: 10px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: var(--text-3); margin-bottom: 6px; }

  table { width: 100%; border-collapse: collapse; margin-top: 8px; }
  th { text-align: left; font-size: 10px; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; color: var(--text-2); padding: 10px 8px; background: var(--paper); border-bottom: 2px solid var(--line); }
  td { padding: 11px 8px; border-bottom: 1px solid var(--line); vertical-align: top; }
  .r { text-align: right; } .c { text-align: center; }
  .num { font-variant-numeric: tabular-nums; white-space: nowrap; }

  .totals { margin-left: auto; width: 320px; margin-top: 16px; }
  .totals div { display: flex; justify-content: space-between; padding: 6px 8px; }
  .totals .grand { border-top: 2px solid var(--ink); margin-top: 6px; padding-top: 12px; font-family: var(--display); font-size: 1.3rem; font-weight: 800; }

  .pay { background: var(--paper); border-radius: 10px; padding: 18px; margin-top: 28px; }
  .pay dl { display: grid; grid-template-columns: auto 1fr; gap: 4px 18px; margin: 8px 0 0; font-size: 13px; }
  .pay dt { color: var(--text-3); } .pay dd { margin: 0; font-weight: 700; font-family: ui-monospace, Consolas, monospace; }

  .note { margin-top: 26px; padding-top: 18px; border-top: 1px solid var(--line); font-size: 11px; color: var(--text-3); line-height: 1.7; }

  .bar { max-width: 860px; margin: 0 auto; display: flex; gap: 8px; justify-content: flex-end; padding: 0 8px; }
  .bar button, .bar a {
    font: inherit; font-size: 13px; font-weight: 700; cursor: pointer;
    padding: 9px 18px; border-radius: 999px; border: 1px solid var(--line);
    background: #fff; color: var(--ink); text-decoration: none;
  }
  .bar .go { background: var(--leaf); color: #fff; border-color: var(--leaf); }

  @media print {
    body { background: #fff; }
    .bar { display: none; }
    .sheet { margin: 0; box-shadow: none; border-radius: 0; padding: 0; max-width: none; }
    th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
</style>
</head>
<body>

<div class="bar">
  <a href="<?= SITE_URL ?>/order-confirmation.php?order=<?= urlencode($order['order_number']) ?>">← Back to the order</a>
  <button class="go" onclick="window.print()">Print / save as PDF</button>
</div>

<div class="sheet">
  <header class="top">
    <div class="brand">
      <img src="<?= SITE_URL ?>/assets/img/logo/faircart-mark.svg" alt="">
      <span>
        <b>Fair<em>Cart</em></b>
        <small><?= e(SITE_TAGLINE) ?></small>
      </span>
    </div>
    <div class="doc">
      <h1><?= $isRes ? 'Stock invoice' : 'Invoice' ?></h1>
      <div class="no"><?= e($order['order_number']) ?></div>
      <div class="meta">Issued <?= $issued ?></div>
      <span class="stamp stamp--<?= $paid ? 'paid' : 'unpaid' ?>"><?= $paid ? 'Paid' : 'Awaiting payment' ?></span>
    </div>
  </header>

  <div class="cols">
    <div>
      <div class="lbl">From</div>
      <b><?= e((string) get_setting('bank_account_holder', 'FairCart Trading (Pty) Ltd')) ?></b><br>
      <?= e((string) get_setting('contact_address', '')) ?><br>
      <?= e((string) get_setting('contact_email', SITE_EMAIL)) ?><br>
      <?= e((string) get_setting('contact_phone', '')) ?>
    </div>
    <div>
      <div class="lbl">Billed to</div>
      <b><?= e((string) $order['customer_name']) ?></b><br>
      <?= e((string) $order['customer_email']) ?><br>
      <?= e((string) $order['customer_phone']) ?>
      <?php if ($order['reseller_code']): ?><br>Reseller <?= e($order['reseller_code']) ?><?php endif; ?>
    </div>
    <div>
      <div class="lbl"><?= e(FULFILMENT_METHODS[$order['fulfilment']]['label']) ?></div>
      <?= nl2br(e((string) $order['shipping_address'])) ?>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:44%">Description</th>
        <th>SKU</th>
        <th class="c">Qty</th>
        <th class="r">Unit price</th>
        <?php if ($isRes): ?><th class="r">RRP</th><?php endif; ?>
        <th class="r">Amount</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $i): ?>
      <tr>
        <td>
          <b><?= e($i['name']) ?></b>
          <?php if ($i['variant_text']): ?><br><span style="color:var(--text-3);font-size:12px"><?= e($i['variant_text']) ?></span><?php endif; ?>
        </td>
        <td style="font-family:ui-monospace,Consolas,monospace;font-size:12px"><?= e((string) $i['sku']) ?></td>
        <td class="c"><?= (int) $i['qty'] ?></td>
        <td class="r num"><?= format_price((float) $i['unit_price']) ?></td>
        <?php if ($isRes): ?><td class="r num" style="color:var(--text-3)"><?= format_price((float) $i['unit_rrp']) ?></td><?php endif; ?>
        <td class="r num"><b><?= format_price((float) $i['unit_price'] * (int) $i['qty']) ?></b></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="totals">
    <div><span>Subtotal</span><span class="num"><?= format_price((float) $order['subtotal']) ?></span></div>
    <?php if ((float) $order['discount_amount'] > 0): ?>
      <div style="color:var(--leaf)"><span>Discount<?= $order['coupon_code'] ? ' (' . e($order['coupon_code']) . ')' : '' ?></span><span class="num">−<?= format_price((float) $order['discount_amount']) ?></span></div>
    <?php endif; ?>
    <div><span><?= e(FULFILMENT_METHODS[$order['fulfilment']]['label']) ?></span><span class="num"><?= (float) $order['shipping_amount'] > 0 ? format_price((float) $order['shipping_amount']) : 'Free' ?></span></div>
    <div class="grand"><span>Total</span><span class="num"><?= format_price((float) $order['total_amount']) ?></span></div>
  </div>

  <?php if ($isRes && (float) $order['est_resale_value'] > 0): ?>
    <div class="pay" style="background:#FDF3DF">
      <div class="lbl" style="color:#C4820C">For your records</div>
      <div style="display:flex;justify-content:space-between;font-size:13px"><span>Resale value at recommended prices</span><b class="num"><?= format_price((float) $order['est_resale_value']) ?></b></div>
      <div style="display:flex;justify-content:space-between;font-size:13px"><span>Gross margin if it sells through</span><b class="num" style="color:var(--leaf)">+<?= format_price(max(0, (float) $order['est_resale_value'] - (float) $order['total_amount'])) ?></b></div>
    </div>
  <?php endif; ?>

  <?php if (!$paid): ?>
  <div class="pay">
    <div class="lbl">Pay by EFT</div>
    <dl>
      <dt>Bank</dt><dd><?= e((string) get_setting('bank_name', '')) ?></dd>
      <dt>Account holder</dt><dd><?= e((string) get_setting('bank_account_holder', '')) ?></dd>
      <dt>Account number</dt><dd><?= e((string) get_setting('bank_account_number', '')) ?></dd>
      <dt>Branch code</dt><dd><?= e((string) get_setting('bank_branch_code', '')) ?></dd>
      <dt>Amount</dt><dd><?= format_price((float) $order['total_amount']) ?></dd>
      <dt>Reference</dt><dd><?= e($order['order_number']) ?></dd>
    </dl>
    <p style="font-size:12px;color:var(--text-2);margin:12px 0 0">
      Upload your proof of payment at
      <b><?= e(SITE_DOMAIN) ?>/order-confirmation.php?order=<?= e($order['order_number']) ?></b>
      and we confirm within one working day.
    </p>
  </div>
  <?php endif; ?>

  <div class="note">
    <b>Notes.</b>
    Prices include VAT where applicable.
    <?php if ($isRes): ?>
      Recommended selling prices are a recommendation only — you set your own prices.
    <?php endif; ?>
    Supplements listed on this invoice are Category D complementary medicines and have not been evaluated by the
    South African Health Products Regulatory Authority for quality, safety or intended use.
    Returns: unopened stock within 7 days. Full terms at <?= e(SITE_DOMAIN) ?>/terms.php.
    <br><br>
    Thank you for your business — <?= e(SITE_NAME) ?>.
  </div>
</div>

</body>
</html>
