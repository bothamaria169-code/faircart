<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

/**
 * How the reseller (trade) programme works, in full.
 *
 * Written for two readers: someone deciding whether to open a trade account,
 * and a compliance reviewer — a bank, a payment provider's FICA screening, or
 * the National Consumer Commission — checking that this is ordinary wholesale
 * trade and not a pyramid or referral scheme.
 *
 * Every number comes from the live catalogue and from RESELLER_TIERS, so the
 * page can never drift from what the shop actually charges.
 */

$company = e((string) get_setting('company_name', (string) get_setting('bank_account_holder', 'FairCart Trading (Pty) Ltd')));
$email   = e((string) get_setting('contact_email', SITE_EMAIL));
$flat    = format_price_short((float) get_setting('shipping_flat_rate', '120'));
$free    = format_price_short((float) get_setting('free_shipping_threshold', '1500'));
$terms   = SITE_URL . '/terms.php';
$privacy = SITE_URL . '/privacy.php';
$returns = SITE_URL . '/refund-policy.php';
$apply   = SITE_URL . '/reseller.php';

// ── Tier table, straight from the constants the pricing engine uses ───────
$tierRows = '';
foreach (RESELLER_TIERS as $tier) {
    $tierRows .= '<tr><td><b>' . e($tier['label']) . '</b></td>'
        . '<td>' . (int) round($tier['off_retail'] * 100) . '% below retail</td>'
        . '<td>' . ($tier['min_spend'] > 0
            ? format_price_short((float) $tier['min_spend']) . ' in the last 90 days'
            : 'No minimum — where everyone starts') . '</td></tr>';
}

// ── A real worked example, priced by the same code the shop uses ──────────
$stmt = db()->prepare("SELECT * FROM products WHERE slug = :s AND status = 'published' LIMIT 1");
$stmt->execute([':s' => 'lady-bloom-juice']);
$example    = $stmt->fetch() ?: null;
$exampleTbl = '';

if ($example) {
    $retail = (float) $example['retail_price'];
    $rows   = '';
    foreach (RESELLER_TIERS as $key => $tier) {
        // Mirrors Pricing::reseller() — the lower of the tier price and any
        // per-product wholesale price set on the item.
        $fromTier = round($retail * (1 - $tier['off_retail']), 2);
        $explicit = (float) ($example['reseller_price'] ?? 0);
        $cost     = $explicit > 0 ? min($explicit, $fromTier) : $fromTier;
        $profit   = $retail - $cost;

        $rows .= '<tr><td><b>' . e($tier['label']) . '</b></td>'
            . '<td>' . format_price($cost) . '</td>'
            . '<td>' . format_price($retail) . '</td>'
            . '<td><b style="color:var(--leaf)">' . format_price($profit) . '</b></td>'
            . '<td>' . ($retail > 0 ? round($profit / $retail * 100) : 0) . '%</td></tr>';
    }
    $exampleTbl = '<p><b>' . e($example['name']) . '</b> ('
        . e((string) $example['pack_size']) . '), at a recommended retail price of '
        . format_price($retail) . ', bought in the minimum quantity of '
        . (int) $example['min_reseller_qty'] . ' units:</p>'
        . '<div class="policy-table-wrap"><table class="policy-table">'
        . '<thead><tr><th>Your tier</th><th>You pay</th><th>You sell at</th><th>You keep</th><th>Margin</th></tr></thead>'
        . '<tbody>' . $rows . '</tbody></table></div>'
        . '<p class="text-muted-3" style="font-size:.85rem">Live prices from our catalogue. You set your own selling '
        . 'price — the retail column is only what we recommend.</p>';
}

$policy = [
    'key'     => 'reseller',
    'title'   => 'How the reseller programme works',
    'lede'    => 'Buy stock at wholesale, sell it at your own price, keep the difference. No fees, no recruiting, no catches.',
    'updated' => '29 September 2026',
    'summary' => [
        'It is <b>ordinary wholesale trade</b>: you buy stock at a discount and resell it to your own customers.',
        'Joining is <b>free</b> — no joining fee, no subscription, no compulsory purchase.',
        'Your discount depends only on <b>what you yourself buy</b>, over a rolling 90 days.',
        'We pay <b>nothing for recruiting</b> anyone. There are no commissions, bonuses or overrides.',
        'It is <b>not an investment</b> and not a guarantee of income. What you earn depends on what you sell.',
    ],
    'sections' => [
        [
            'id' => 'what-it-is', 'icon' => 'fa-store', 'title' => 'What the programme is',
            'html' => <<<HTML
<p>{$company} sells wellness, fragrance, cleaning and pest-control products. Anyone who wants to resell those
products — a spaza shop, a salon, a stall, a WhatsApp seller, an online store — can apply for a <b>trade account</b>,
which lets them buy the same stock at wholesale prices.</p>
<p>It works the way wholesale has always worked:</p>
<ol class="policy-steps">
  <li><b>You buy stock</b> from us at a wholesale price and pay for it up front.</li>
  <li><b>The stock is yours.</b> We deliver it to you and the goods become your property.</li>
  <li><b>You sell it</b> to your own customers, at whatever price you choose.</li>
  <li><b>You keep the difference</b> between what you paid and what you sold it for.</li>
</ol>
<p>There is no other money in the programme. We earn by selling products to resellers; resellers earn by selling
those products on.</p>
HTML,
        ],
        [
            'id' => 'not-a-scheme', 'icon' => 'fa-shield-halved', 'title' => 'What this programme is not',
            'html' => <<<HTML
<div class="policy-callout">
  <i class="fa-solid fa-circle-check"></i>
  <p>We pay <b>no commission, bonus, override or reward of any kind for recruiting other people</b>. Not on their
  purchases, not on their sales, not on anything they do. There is no downline, no team volume, and no level
  structure anywhere in this business.</p>
</div>
<p>To be explicit, a FairCart trade account involves:</p>
<ul>
  <li><b>No joining fee, registration fee or subscription</b> — applying and holding a trade account costs nothing.</li>
  <li><b>No compulsory purchase</b> — no minimum monthly order, no quota, no "stay active" requirement.</li>
  <li><b>No payment for introducing people.</b> Your discount never depends on how many people you bring in.</li>
  <li><b>No investment.</b> You never deposit money with us or buy into anything. You buy stock, and you receive that stock.</li>
  <li><b>No income promise.</b> We do not claim, suggest or guarantee any level of earnings. What you make depends entirely on what you sell.</li>
</ul>
<p>If anyone tells you otherwise while using the FairCart name, please report it to {$email}.</p>
HTML,
        ],
        [
            'id' => 'joining', 'icon' => 'fa-user-plus', 'title' => 'How to join',
            'html' => <<<HTML
<ol class="policy-steps">
  <li><b>Create a free account</b> on faircart.co.za.</li>
  <li><b>Apply for a trade account</b> on the <a href="{$apply}">reseller page</a>, giving your trading name, the area you sell in, your contact number and your ID number so we can verify who you are.</li>
  <li><b>We check the application</b> by hand. We may ask for supporting documents.</li>
  <li><b>Once approved</b> you get a reseller code, and wholesale prices appear automatically everywhere you shop on the site.</li>
</ol>
<p>Applications are free, and we may decline one without giving reasons. How we handle the personal information in
your application is set out in our <a href="{$privacy}">privacy policy</a>.</p>
HTML,
        ],
        [
            'id' => 'pricing', 'icon' => 'fa-percent', 'title' => 'Wholesale prices and tiers',
            'html' => <<<HTML
<p>There are four tiers. Your tier sets how far below the recommended retail price you buy:</p>
<div class="policy-table-wrap"><table class="policy-table">
  <thead><tr><th>Tier</th><th>You buy at</th><th>Reached by spending</th></tr></thead>
  <tbody>{$tierRows}</tbody>
</table></div>
<p>Tiers move on <b>your own paid orders over a rolling 90 days</b> — nothing else. Not on how many people you
recruited, not on anyone else's orders, not on a fee. The system recalculates automatically, so a tier is earned by
trading, never bought.</p>
<p>Some products carry their own wholesale price. Where that price is lower than your tier price, you pay the lower
of the two.</p>
HTML,
        ],
        [
            'id' => 'what-you-earn', 'icon' => 'fa-calculator', 'title' => 'What you can earn',
            'html' => <<<HTML
{$exampleTbl}
<p>That is the whole earning model: the gap between your wholesale cost and your selling price, on stock you have
actually sold. Unsold stock is money you have spent and not yet recovered, which is the risk you carry as a trader
— so start small on a new line and reorder what moves.</p>
HTML,
        ],
        [
            'id' => 'ordering', 'icon' => 'fa-truck-fast', 'title' => 'Ordering, paying and delivery',
            'html' => <<<HTML
<ul>
  <li><b>Minimum quantities.</b> Each product has a minimum trade quantity, shown on the product page — usually between 1 and 12 units.</li>
  <li><b>Payment.</b> You pay by EFT and upload your proof of payment. We confirm within one working day, and hold your stock for 48 hours while we wait.</li>
  <li><b>Delivery.</b> Courier is {$flat}, free on orders over {$free}, usually 2–4 working days. Collection points are available at checkout.</li>
  <li><b>Returns.</b> Unopened trade stock can come back within 7 days, and faulty stock is covered for 6 months — see the <a href="{$returns}">returns policy</a>. We cannot take back stock that simply did not sell.</li>
</ul>
HTML,
        ],
        [
            'id' => 'your-obligations', 'icon' => 'fa-clipboard-check', 'title' => 'The rules you agree to',
            'html' => <<<HTML
<p>As a trade account holder you agree to:</p>
<ul>
  <li>buy wholesale only stock you intend to sell to your own customers;</li>
  <li>set your own retail prices, and carry your own risk on unsold stock;</li>
  <li><b>make no medical, therapeutic or curative claims</b> about any product — our supplements are Category D complementary medicines that have not been evaluated by SAHPRA, and may not be sold as treating or curing anything;</li>
  <li>not present yourself as an employee, agent or franchisee of FairCart;</li>
  <li>follow South African law when you sell, including the Consumer Protection Act.</li>
</ul>
<p>We may suspend a trade account that breaks these rules. A suspended account simply reverts to normal retail
prices, and any stock already bought remains yours. You can stop trading with us at any time; there is nothing to
cancel and no notice to give.</p>
HTML,
        ],
        [
            'id' => 'referral-code', 'icon' => 'fa-link', 'title' => 'The referral code field',
            'html' => <<<HTML
<p>The application form has an optional referral code box. If an existing reseller told you about FairCart, you can
enter their code and we record who referred you.</p>
<div class="policy-callout policy-callout--warn">
  <i class="fa-solid fa-circle-info"></i>
  <p>This is <b>for our records only</b>. The person who referred you is <b>paid nothing</b> — no commission, no
  bonus, no discount, and no change to their tier. It has no effect on your prices either.</p>
</div>
HTML,
        ],
        [
            'id' => 'legal', 'icon' => 'fa-scale-balanced', 'title' => 'The legal bits',
            'html' => <<<HTML
<p>A trade account is governed by our <a href="{$terms}">terms and conditions</a>, and the personal information you
give us is handled under our <a href="{$privacy}">privacy policy</a>, in line with the Protection of Personal
Information Act.</p>
<p>Questions about the programme — including from banks, payment providers or regulators — can go to {$email}.</p>
HTML,
        ],
    ],
];

include __DIR__ . '/includes/policy-page.php';
