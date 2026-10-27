<?php
declare(strict_types=1);

/**
 * One-off import: takes the real product photography dropped into
 * assets/img/final-products/, re-encodes each as a web-sized JPEG into
 * assets/img/products/, and wires featured/gallery images (and one variant
 * image) onto the matching catalogue rows.
 *
 * Source files are 1–2MB PNGs straight out of an image generator — far
 * larger than a product photo needs to be. Every file is resized to a
 * max edge of 1400px and re-encoded at quality 85, which is where a photo
 * this size stops shrinking noticeably but the byte size drops by 85-95%.
 *
 * Safe to re-run: it only ever overwrites its own destination files and
 * upserts by product id, never touches products this mapping doesn't name.
 *
 *   php scripts/import-product-photos.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../includes/functions.php';

const SRC_DIR = __DIR__ . '/../assets/img/final-products/';
const DST_DIR = __DIR__ . '/../assets/img/products/';
const MAX_EDGE = 1400;
const JPEG_QUALITY = 85;

/** Re-encodes one source image to a web-sized JPEG. Returns the new filename. */
function import_image(string $srcFile, string $dstName): string
{
    $srcPath = SRC_DIR . $srcFile;
    $dstPath = DST_DIR . $dstName;

    $info = getimagesize($srcPath);
    if (!$info) {
        throw new RuntimeException("Not a readable image: $srcFile");
    }
    [$w, $h, $type] = $info;

    $src = match ($type) {
        IMAGETYPE_PNG  => imagecreatefrompng($srcPath),
        IMAGETYPE_JPEG => imagecreatefromjpeg($srcPath),
        IMAGETYPE_WEBP => imagecreatefromwebp($srcPath),
        default        => throw new RuntimeException("Unsupported source type: $srcFile"),
    };
    if (!$src) {
        throw new RuntimeException("Failed to decode: $srcFile");
    }

    $scale = min(1.0, MAX_EDGE / max($w, $h));
    $newW  = max(1, (int) round($w * $scale));
    $newH  = max(1, (int) round($h * $scale));

    $dst = imagecreatetruecolor($newW, $newH);
    // Flatten onto white first — these are opaque product shots, but a PNG
    // with any antialiased edge transparency would otherwise turn black
    // once written out as JPEG (JPEG has no alpha channel).
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

    imagejpeg($dst, $dstPath, JPEG_QUALITY);
    imagedestroy($src);
    imagedestroy($dst);

    return $dstName;
}

/**
 * One row per destination file: [source filename, new filename].
 * Grouped by product so the mapping below reads as one block per product.
 */
$imports = [
    // Lady Bloom Juice (id 1) — hibiscus is the default flavour, so it's featured.
    'product-lady-bloom.png'    => 'lady-bloom-juice-1.jpg',
    'prouct bloom juicfe.png'   => 'lady-bloom-juice-2.jpg',
    'product-lady-bloom2.png'   => 'lady-bloom-juice-3.jpg',
    'product ladybloom.png'     => 'lady-bloom-pineapple-1.jpg',
    'product ladybloom2.png'    => 'lady-bloom-pineapple-2.jpg',

    // King Bloom Juice (id 3)
    'product king bloom.png'    => 'king-bloom-juice-1.jpg',
    'product king bloom2.png'   => 'king-bloom-juice-2.jpg',

    // gutCleanse (id 4)
    'product gut-cleanse1.png'  => 'gut-cleanse-1.jpg',
    'product gut-cleanse.png'   => 'gut-cleanse-2.jpg',

    // Absolute Pure Hydrolysed Collagen (id 5)
    'product pure.png'          => 'absolute-pure-collagen-1.jpg',
    'product pure2.png'         => 'absolute-pure-collagen-2.jpg',

    // Collagen & Berberine Slimming Shake, Berry Blast (id 9)
    'product collagen.png'      => 'collagen-berberine-berry-1.jpg',
    'product collagen2.png'     => 'collagen-berberine-berry-2.jpg',

    // Brightening Body Oil (id 10)
    'product oil.png'           => 'brightening-body-oil-1.jpg',
    'product oil2.png'          => 'brightening-body-oil-2.jpg',

    // Blossom Beauty Anti-Ageing Drink (id 12)
    'product antiaging2.png'    => 'blossom-beauty-antiaging-1.jpg',

    // Rise Multi-Purpose Cleaner (id 25)
    'product rise.png'          => 'rise-multipurpose-cleaner-1.jpg',
    'product rise2.png'         => 'rise-multipurpose-cleaner-2.jpg',

    // Bloom Detox Tea (id 7)
    'product tea.png'           => 'bloom-detox-tea-1.jpg',
    'product tea2.png'          => 'bloom-detox-tea-2.jpg',

    // Home Reed Diffuser (id 15)
    'product-Home Reed Diffuser.png' => 'home-diffuser-1.jpg',

    // Signature Perfume (id 16)
    'product-perfume.png'       => 'signature-perfume-1.jpg',
    'product-perfume2.png'      => 'signature-perfume-2.jpg',

    // Lady Bloom Student Pack (id 13)
    'prouct bloom student pack.png' => 'lady-bloom-student-pack-1.jpg',
];

echo "Importing " . count($imports) . " photo(s)…\n";
foreach ($imports as $src => $dst) {
    try {
        import_image($src, $dst);
        $size = round(filesize(DST_DIR . $dst) / 1024);
        echo "  ok   $dst  ({$size}KB)\n";
    } catch (Throwable $e) {
        echo "  FAIL $src -> $dst : " . $e->getMessage() . "\n";
    }
}

// ── Wire the new files onto the catalogue ────────────────────────────────
$repo = new ProductRepository();

/**
 * @var array<int,array{featured?:string,gallery?:string[]}>
 */
$plan = [
    1  => ['featured' => 'lady-bloom-juice-1.jpg',           'gallery' => ['lady-bloom-juice-2.jpg', 'lady-bloom-juice-3.jpg', 'lady-bloom-pineapple-1.jpg', 'lady-bloom-pineapple-2.jpg']],
    3  => ['featured' => 'king-bloom-juice-1.jpg',           'gallery' => ['king-bloom-juice-2.jpg']],
    4  => ['featured' => 'gut-cleanse-1.jpg',                'gallery' => ['gut-cleanse-2.jpg']],
    5  => ['featured' => 'absolute-pure-collagen-1.jpg',     'gallery' => ['absolute-pure-collagen-2.jpg']],
    9  => ['featured' => 'collagen-berberine-berry-1.jpg',   'gallery' => ['collagen-berberine-berry-2.jpg']],
    10 => ['featured' => 'brightening-body-oil-1.jpg',       'gallery' => ['brightening-body-oil-2.jpg']],
    12 => ['featured' => 'blossom-beauty-antiaging-1.jpg'],
    25 => ['featured' => 'rise-multipurpose-cleaner-1.jpg',  'gallery' => ['rise-multipurpose-cleaner-2.jpg']],
    7  => ['featured' => 'bloom-detox-tea-1.jpg',            'gallery' => ['bloom-detox-tea-2.jpg']],
    15 => ['featured' => 'home-diffuser-1.jpg'],
    16 => ['featured' => 'signature-perfume-1.jpg',          'gallery' => ['signature-perfume-2.jpg']],
    13 => ['featured' => 'lady-bloom-student-pack-1.jpg'],
];

echo "\nUpdating products…\n";
foreach ($plan as $productId => $set) {
    $product = $repo->getById($productId);
    if (!$product) {
        echo "  skip id $productId (not found)\n";
        continue;
    }

    $repo->update($productId, ['featured_image' => 'assets/img/products/' . $set['featured']]);
    echo "  {$product['name']}: featured -> {$set['featured']}\n";

    if (!empty($set['gallery'])) {
        // Re-running the script would otherwise duplicate gallery rows —
        // clear this product's existing gallery first so it stays idempotent.
        foreach ($repo->getGallery($productId) as $g) {
            $repo->deleteGalleryImage((int) $g['id']);
        }
        foreach ($set['gallery'] as $i => $file) {
            $repo->addGalleryImage($productId, 'assets/img/products/' . $file, null, $i + 1);
        }
        echo "    gallery -> " . implode(', ', $set['gallery']) . "\n";
    }
}

// The Pineapple variant on Lady Bloom Juice gets its own real photo too.
$stmt = db()->prepare("UPDATE product_variants SET image = :img WHERE product_id = 1 AND option_value = 'Pineapple'");
$stmt->execute([':img' => 'assets/img/products/lady-bloom-pineapple-1.jpg']);
echo "\nUpdated Lady Bloom Juice → Pineapple variant image.\n";

echo "\nDone.\n";
