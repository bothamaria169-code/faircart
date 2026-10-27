<?php
declare(strict_types=1);

/**
 * One-off import: re-encodes the promotional banner artwork dropped into
 * assets/img/banners/ as web-sized JPEGs and registers each in the `banners`
 * table with its page placement.
 *
 * The source PNGs are ~2MB each. Five of them load on the homepage carousel,
 * so shipping them raw would put 10MB on the landing page — they come down to
 * roughly 200-350KB each here without a visible quality drop.
 *
 *   php scripts/import-banners.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../includes/functions.php';

const BANNER_DIR = __DIR__ . '/../assets/img/banners/';
const WIDE_MAX   = 1800;  // full-bleed hero never needs more than this
const TALL_MAX   = 900;   // promo tiles render at ~300-560px, so 900 covers retina
const QUALITY    = 85;

function encode_banner(string $srcName, string $dstName, int $maxEdge): int
{
    $srcPath = BANNER_DIR . $srcName;
    $dstPath = BANNER_DIR . $dstName;

    [$w, $h, $type] = getimagesize($srcPath);
    $src = match ($type) {
        IMAGETYPE_PNG  => imagecreatefrompng($srcPath),
        IMAGETYPE_JPEG => imagecreatefromjpeg($srcPath),
        IMAGETYPE_WEBP => imagecreatefromwebp($srcPath),
        default        => throw new RuntimeException("Unsupported: $srcName"),
    };

    $scale = min(1.0, $maxEdge / max($w, $h));
    $newW  = max(1, (int) round($w * $scale));
    $newH  = max(1, (int) round($h * $scale));

    $dst   = imagecreatetruecolor($newW, $newH);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
    imagejpeg($dst, $dstPath, QUALITY);
    imagedestroy($src);
    imagedestroy($dst);

    return (int) round(filesize($dstPath) / 1024);
}

/**
 * source => [destination, title, alt, link, aspect, home, shop, reseller, sort]
 */
$banners = [
    'banner.png' => [
        'flash-sale-lady-bloom.jpg',
        'Flash Sale — Lady Bloom Juice',
        'Flash Sale on Lady Bloom Juice — save up to 50%, order today and get it tomorrow',
        '/shop.php?category=health-wellness', 'wide', 1, 0, 0, 1,
    ],
    'banner2.png' => [
        'discover-our-variety.jpg',
        'Discover our Variety',
        'Lady Bloom Juice in hibiscus and pineapple — different flavours, same benefits',
        '/modules/shop/product.php?slug=lady-bloom-juice', 'wide', 1, 1, 0, 2,
    ],
    'banner3.png' => [
        'wellness-in-every-sip.jpg',
        'Wellness in Every Sip',
        'The Blossom wellness range — juices, collagen, slimming shake, gut cleanse and diffuser',
        '/shop.php?brand=blossom', 'wide', 1, 1, 0, 3,
    ],
    'banner4.png' => [
        'be-your-own-boss.jpg',
        'Be Your Own Boss',
        'Resell top brands and earn real income — no membership fees, wholesale prices, free sign up',
        '/reseller.php', 'wide', 1, 0, 1, 4,
    ],
    'banner5.png' => [
        'more-than-shopping.jpg',
        'More Than Just Shopping',
        'Join FairCart, get access to wholesale products and start reselling today',
        '/reseller.php', 'wide', 1, 0, 1, 5,
    ],
    'banner-body.png' => [
        'tile-discover-variety.jpg',
        'Two Amazing Flavours',
        'Lady Bloom Juice in hibiscus and pineapple — goodness in every sip',
        '/modules/shop/product.php?slug=lady-bloom-juice', 'tall', 0, 1, 0, 1,
    ],
    'banner-body2.png' => [
        'tile-earn-real-income.jpg',
        'Earn Real Income',
        'Resell top wellness brands with FairCart — great margins, reliable delivery, free to join',
        '/reseller.php', 'tall', 0, 0, 1, 2,
    ],
];

echo "Encoding " . count($banners) . " banner(s)…\n";

db()->exec('DELETE FROM banners');

foreach ($banners as $src => [$dst, $title, $alt, $link, $aspect, $home, $shop, $reseller, $sort]) {
    $kb = encode_banner($src, $dst, $aspect === 'wide' ? WIDE_MAX : TALL_MAX);
    db_insert('banners', [
        'title'         => $title,
        'image'         => 'assets/img/banners/' . $dst,
        'alt_text'      => $alt,
        'link_url'      => $link,
        'aspect'        => $aspect,
        'show_home'     => $home,
        'show_shop'     => $shop,
        'show_reseller' => $reseller,
        'sort_order'    => $sort,
        'status'        => 'active',
    ]);
    printf("  ok  %-28s %4dKB  [%s] home:%d shop:%d reseller:%d\n", $dst, $kb, $aspect, $home, $shop, $reseller);
}

echo "\nRegistered " . db()->query('SELECT COUNT(*) FROM banners')->fetchColumn() . " banner(s).\n";
