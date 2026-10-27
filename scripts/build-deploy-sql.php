<?php
declare(strict_types=1);

/**
 * Generates database/deploy-production.sql from the working local database.
 *
 * Production has the current code and all the image files, but none of the
 * database changes — no banners table, old announcement text, old article and
 * FAQ content, and products still pointing at the pre-photography images.
 *
 * Generating the file from the live local rows (rather than hand-writing it)
 * means what gets imported is exactly what is already working here.
 *
 * Deliberately partial where it matters: products, settings and brands get
 * targeted UPDATEs, never a full table replace, because production owns
 * things this machine does not — real stock levels, sold counts, real orders
 * and the real bank details in settings.
 *
 *   php scripts/build-deploy-sql.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../includes/functions.php';

$out = [];
$q = fn(?string $v): string => $v === null ? 'NULL' : db()->quote($v);

$out[] = "-- ============================================================================";
$out[] = "--  FairCart — production deploy";
$out[] = "--  Generated " . date('Y-m-d H:i') . " from the local database.";
$out[] = "--";
$out[] = "--  Import this ONCE into the production database (phpMyAdmin → Import).";
$out[] = "--  Safe to re-run: content tables are replaced wholesale, and everything";
$out[] = "--  else is a targeted UPDATE. It never touches orders, users, stock levels";
$out[] = "--  or your bank details in settings.";
$out[] = "-- ============================================================================";
$out[] = "SET NAMES utf8mb4;";
$out[] = "";

// ── 1. banners table ─────────────────────────────────────────────────────
$out[] = "-- ── 1. Promotional banners (this is why the carousel is blank) ──────────";
$out[] = file_get_contents(__DIR__ . '/../database/banners.sql');
$out[] = "DELETE FROM `banners`;";
foreach (db()->query('SELECT * FROM banners ORDER BY id') as $b) {
    $out[] = sprintf(
        "INSERT INTO `banners` (`title`,`image`,`alt_text`,`link_url`,`aspect`,`show_home`,`show_shop`,`show_reseller`,`sort_order`,`status`) VALUES (%s,%s,%s,%s,%s,%d,%d,%d,%d,%s);",
        $q($b['title']), $q($b['image']), $q($b['alt_text']), $q($b['link_url']), $q($b['aspect']),
        (int) $b['show_home'], (int) $b['show_shop'], (int) $b['show_reseller'], (int) $b['sort_order'], $q($b['status'])
    );
}
$out[] = "";

// ── 2. settings ──────────────────────────────────────────────────────────
$out[] = "-- ── 2. Sales-focused site copy ─────────────────────────────────────────";
foreach (['announcement', 'site_tagline'] as $key) {
    $stmt = db()->prepare('SELECT value FROM settings WHERE key_name = :k');
    $stmt->execute([':k' => $key]);
    $val = (string) $stmt->fetchColumn();
    $out[] = sprintf("UPDATE `settings` SET `value` = %s WHERE `key_name` = %s;", $q($val), $q($key));
}
$out[] = "";

// ── 3. brand blurb ───────────────────────────────────────────────────────
$stmt = db()->query("SELECT slug, blurb FROM brands WHERE slug = 'pure-fragrances'");
if ($row = $stmt->fetch()) {
    $out[] = "-- ── 3. Brand copy that was written for resellers ───────────────────────";
    $out[] = sprintf("UPDATE `brands` SET `blurb` = %s WHERE `slug` = %s;", $q($row['blurb']), $q($row['slug']));
    $out[] = "";
}

// ── 4. content tables (full replace) ─────────────────────────────────────
$out[] = "-- ── 4. Articles, FAQs and testimonials, rewritten for a sales-led shop ──";
$out[] = "DELETE FROM `blogs`;";
foreach (db()->query('SELECT * FROM blogs ORDER BY id') as $r) {
    $out[] = sprintf(
        "INSERT INTO `blogs` (`id`,`title`,`slug`,`image`,`excerpt`,`content`,`category`,`author_id`,`read_mins`,`is_featured`,`status`) VALUES (%d,%s,%s,%s,%s,%s,%s,%d,%d,%d,%s);",
        (int) $r['id'], $q($r['title']), $q($r['slug']), $q($r['image']), $q($r['excerpt']), $q($r['content']),
        $q($r['category']), (int) $r['author_id'], (int) $r['read_mins'], (int) $r['is_featured'], $q($r['status'])
    );
}
$out[] = "";
$out[] = "DELETE FROM `faqs`;";
foreach (db()->query('SELECT * FROM faqs ORDER BY id') as $r) {
    $out[] = sprintf(
        "INSERT INTO `faqs` (`question`,`answer`,`topic`,`sort_order`,`status`) VALUES (%s,%s,%s,%d,%s);",
        $q($r['question']), $q($r['answer']), $q($r['topic']), (int) $r['sort_order'], $q($r['status'])
    );
}
$out[] = "";
$out[] = "DELETE FROM `testimonials`;";
foreach (db()->query('SELECT * FROM testimonials ORDER BY id') as $r) {
    $out[] = sprintf(
        "INSERT INTO `testimonials` (`name`,`role`,`location`,`quote`,`rating`,`avatar`,`is_reseller`,`status`,`sort_order`) VALUES (%s,%s,%s,%s,%d,%s,%d,%s,%d);",
        $q($r['name']), $q($r['role']), $q($r['location']), $q($r['quote']), (int) $r['rating'],
        $q($r['avatar']), (int) $r['is_reseller'], $q($r['status']), (int) $r['sort_order']
    );
}
$out[] = "";

// ── 5. product photography ───────────────────────────────────────────────
// Every product, not just the ones with new photography: production's rows are
// stale across the board, and this machine is the source of truth for image
// paths (production owns stock, sold counts and orders — not artwork).
//
// Matched on slug, not id: production ids could differ if rows were ever
// re-imported, but slugs are unique and stable.
$out[] = "-- ── 5. Point products at the real photography ──────────────────────────";
$rows = db()->query('SELECT slug, featured_image FROM products ORDER BY id')->fetchAll();
foreach ($rows as $r) {
    $out[] = sprintf("UPDATE `products` SET `featured_image` = %s WHERE `slug` = %s;", $q($r['featured_image']), $q($r['slug']));
}
$out[] = "";

$out[] = "-- Product galleries (cleared per product first so re-running cannot double up)";
$galleryProducts = db()->query(
    "SELECT DISTINCT p.id, p.slug FROM products p JOIN product_gallery g ON g.product_id = p.id ORDER BY p.id"
)->fetchAll();
foreach ($galleryProducts as $p) {
    $out[] = sprintf(
        "DELETE FROM `product_gallery` WHERE `product_id` = (SELECT `id` FROM `products` WHERE `slug` = %s);",
        $q($p['slug'])
    );
    $stmt = db()->prepare('SELECT * FROM product_gallery WHERE product_id = :p ORDER BY sort_order, id');
    $stmt->execute([':p' => $p['id']]);
    foreach ($stmt->fetchAll() as $g) {
        $out[] = sprintf(
            "INSERT INTO `product_gallery` (`product_id`,`image`,`caption`,`sort_order`) SELECT `id`, %s, %s, %d FROM `products` WHERE `slug` = %s;",
            $q($g['image']), $q($g['caption']), (int) $g['sort_order'], $q($p['slug'])
        );
    }
}
$out[] = "";

$out[] = "-- Variant images (flavour swatches on the product page)";
$variants = db()->query(
    "SELECT v.option_value, v.image, p.slug FROM product_variants v
     JOIN products p ON p.id = v.product_id
     WHERE v.image IS NOT NULL AND v.image <> '' ORDER BY v.id"
)->fetchAll();
foreach ($variants as $v) {
    $out[] = sprintf(
        "UPDATE `product_variants` SET `image` = %s WHERE `option_value` = %s AND `product_id` = (SELECT `id` FROM `products` WHERE `slug` = %s);",
        $q($v['image']), $q($v['option_value']), $q($v['slug'])
    );
}
$out[] = "";
$out[] = "-- Done. Reload faircart.co.za — the carousel and the new photos should be live.";

$path = __DIR__ . '/../database/deploy-production.sql';
file_put_contents($path, implode("\n", $out) . "\n");

printf("Wrote %s (%.1f KB)\n", basename($path), filesize($path) / 1024);
printf("  banners      : %d\n", count(db()->query('SELECT id FROM banners')->fetchAll()));
printf("  articles     : %d\n", count(db()->query('SELECT id FROM blogs')->fetchAll()));
printf("  faqs         : %d\n", count(db()->query('SELECT id FROM faqs')->fetchAll()));
printf("  testimonials : %d\n", count(db()->query('SELECT id FROM testimonials')->fetchAll()));
printf("  product images updated : %d\n", count($rows));
printf("  gallery rows : %d\n", count(db()->query('SELECT id FROM product_gallery')->fetchAll()));
