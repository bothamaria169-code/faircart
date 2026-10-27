# FairCart — South African marketplace with a built-in reseller programme

> **Real products. Fair prices. Real profit.** · `faircart.co.za`

Four South African supplier catalogues in one shop: **Blossom** (wellness), **Pure Fragrances**
(home & car fragrance, pest control), **Rise** (cleaning) and **ML Group** (mould treatment).

The thing that makes FairCart different from an ordinary storefront is that every product carries
**three** prices instead of one — retail, wholesale, and the recommended selling price — and an
approved reseller sees their cost and their rand profit on every card, product page, basket line,
invoice and printed price list.

---

## Stack

PHP 8.2 + MySQL (PDO), vanilla JS, Bootstrap 5.3 / Font Awesome 6.5 / Chart.js via CDN.
**No Composer, no build step, no framework.** Drop it in a web root and it runs.

---

## Local setup (XAMPP)

```bash
# 1. Start Apache + MySQL
# 2. Create the database
mysql -u root -e "CREATE DATABASE faircart CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
# 3. Schema, then catalogue
mysql -u root faircart < database/database.sql
mysql -u root faircart < database/seed.sql
# 4. Browse to http://localhost/faircart.co.za/
```

### Demo accounts

`database/seed.sql` creates an admin, a reseller and a shopper for local testing (see the comments at
the top of that file). **On the live site, change the admin password immediately** (Account → My
details) — this repository is public.



---

## The pricing model — read this before changing prices

Everything hangs off `classes/Pricing.php`. It is the only place that decides what a viewer pays.

| Column | Meaning |
|---|---|
| `retail_price` | what a walk-up customer pays |
| `sale_price` | optional promo price; needs `is_sale` ticked |
| `reseller_price` | the supplier's own quoted wholesale figure — a per-product override |
| `rrp` | what we recommend a reseller sells at; drives the profit figure |

A reseller pays **the better of** their tier discount off retail and the explicit `reseller_price`:

```php
min($product['reseller_price'], $retail * (1 - $tierRate))
```

That minimum is deliberate. Returning the explicit price whenever it is set would make every tier
pay the same on every product and the whole programme would be theatre; taking the higher would
punish buyers on lines a supplier already prices keenly.

### Tiers

| Tier | Off retail | Qualifies at (rolling 90-day **paid** spend) |
|---|---|---|
| Starter | 45% | first order |
| Bronze | 50% | R2,500 |
| Silver | 55% | R7,500 |
| Gold | 60% | R20,000 |

Starter sits at 45% so a brand-new reseller is never worse off than the printed supplier sheet —
the ML Group sheet says *buy R55, sell R110*, and a Starter reseller pays exactly R55 for it.

Tiers **promote only, never demote**. Edit the rates in `config/constants.php`; the whole site,
the JS calculator on `reseller.php` and the printed price list all read from there.

**Cron** — promote resellers nightly:

```
15 2 * * * /usr/local/bin/php /home/USER/public_html/scripts/refresh-tiers.php >> ~/logs/faircart-tiers.log 2>&1
```

---

## What is in here

**Storefront** — home, shop with faceted filters (category, brand, price, stock, promo) and six
sort orders, product pages with variants/gallery/tabs/reviews, live search suggest (`/` focuses it),
wishlist, 4-up compare tray, recently viewed, back-in-stock alerts, basket, coupons, checkout,
order tracking, print-ready invoice, Learn hub, FAQ, contact, legal pages, 404, sitemap + RSS.

**Reseller programme** — public programme page with a live profit calculator wired to the real
catalogue, free application flow with optional referral code, admin approval, four tiers,
reseller dashboard (tier progress, biggest-margin restock shortlist, what-you-buy-most), and a
printable wholesale price list.

**Checkout** — courier delivery, free depot collection, or **Runner pickup** (an approved member
collects your packed order). EFT with proof-of-payment upload; the PayFast slot is stubbed and
wired but switched off.

**Admin** — dashboard with a 30-day revenue chart and a retail-vs-reseller split, orders with a
timeline and status mail, products + variants + bulk repricing, categories, brands, coupons,
reviews moderation, resellers, customers, messages, articles, newsletter campaigns, settings.

**Shop assistant** — the widget bottom-right (`classes/ShopAssistant.php`). Deliberately
rule-based and answering from live database rows: it quotes real prices, real stock and real order
statuses, costs nothing per message, needs no API key, works offline, and cannot invent a product
that does not exist. If you wire a hosted model in later, keep this as the fallback.

---

## Key folders

```
assets/css/theme.css      every brand token — re-skin the whole site from this one file
assets/js/                app shell, cart, product, reseller calculator, chat, compare
classes/                  Pricing, ProductRepository, OrderRepository, ResellerService,
                          NewsletterService, ShopAssistant, Mailer
config/                   config, constants (tiers, order statuses, fulfilment), db, security, JWT
includes/                 header/navbar/footer, product card, chat widget, dashboard shell, functions
modules/api/              JSON endpoints: cart, search, wishlist, coupon, shipping, review,
                          stock-alert, newsletter, chat
dashboards/               admin/ · reseller/ · customer/
scripts/                  make-product-art.php (SVG renders), refresh-tiers.php (cron)
source-screenshots/       the original supplier price lists this catalogue was transcribed from
```

---

## Product imagery

Real photography lives in `assets/img/products/`. Where a product had no photograph, a branded SVG
render of the actual pack format was generated by `scripts/make-product-art.php` — a trigger spray
looks like a trigger spray, a pouch like a pouch — so a mixed catalogue still reads as one shelf.

To replace one with real photography: drop a JPG into `assets/img/products/` and update the
product's **Image** field in Admin → Products. Nothing else needs touching.

```bash
php scripts/make-product-art.php   # regenerate all SVG renders
```

---

## Prices — confirm these before launch

Prices were transcribed from the sheets in `source-screenshots/`. Where a sheet printed only one
figure it was treated as **retail**, and the wholesale column derives from the tier rate.

**Three products had no legible price on any sheet** and carry a working estimate. Confirm them in
Admin → Products first:

| Product | Assumed retail | Why |
|---|---|---|
| gutCleanse 300g | R300 | Blossom sheet lists benefits but the price is cropped |
| Rise Multi-Purpose Cleaner 750ml | R120 | no price on either Rise poster |
| Blossom Beauty Anti-Ageing Drink | R180 | product shot only, no sheet |

Also worth a second look: the Pure Fragrances sheet is headed *"BUY LOW – SELL HIGH"*, so its
figures **may** be wholesale rather than retail. They are loaded as retail, which is the
conservative reading — if they are wholesale, raise the retail prices on those eleven lines.

---

## Deploying to cPanel

Environment detection is automatic — Windows/localhost uses the local DB, anything else uses the
production branch in `config/config.php`.

1. Upload everything except `source-screenshots/` (optional) to the document root.
2. Create the database and import `database/database.sql`, then `database/seed.sql`.
3. Copy `config/secrets.example.php` to `config/secrets.php` and fill in the database and mailbox
   passwords. `secrets.php` is git-ignored — never commit it (the repository is public).
4. API keys (Pudo, iKhokha, Groq, ImageKit, Meta) go in **Admin → Integrations** — see
   `SETUP-INTEGRATIONS.md` for every step, including the one cron job.
5. Make `uploads/`, `uploads/products/` and `uploads/proof-of-payments/` writable (0755).
6. Sign in, change the admin password, then set the real bank details in Admin → Settings.
7. Add the tier-refresh cron above.
8. Once HTTPS is confirmed working, uncomment the HSTS header in `.htaccess`.

---

## Security notes

- Passwords: bcrypt cost 12. Sessions: signed HS256 JWT in an HTTP-only, SameSite cookie.
- CSRF tokens on every state-changing form; rate limiting on login, contact, newsletter and chat.
- PDO with emulated prepares **off** — so a named placeholder can only appear once per statement.
  Search clauses use `:q1 :q2 :q3` for this reason; do not "tidy" them back to one name.
- Uploads validated by real MIME type, stored under generated filenames.
- **Proof-of-payment files are bank screenshots.** The directory is denied in `.htaccess` and every
  read goes through `serve-pop.php`, which only serves an admin, the order's owner, or a guest who
  knows the (unguessable) order number.
- `config/`, `classes/`, `database/`, `includes/`, `scripts/` and `source-screenshots/` are all
  404'd by `.htaccess` — they are includes, never entry points.

---

## Health products — a standing constraint

The Blossom supplements are **Category D complementary medicines**. They have not been evaluated by
SAHPRA for quality, safety or intended use. The site says so on the product page, in the footer, on
the FAQ, at checkout, and on every invoice — and the reseller terms require resellers not to make
medical claims either. If you edit product copy, keep it that way. It is not boilerplate; it is the
condition under which these products can be sold at all.
