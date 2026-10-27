<?php
declare(strict_types=1);

/**
 * Makes a JPEG copy of every SVG product image, for use in email.
 *
 * Gmail and Outlook will not display SVG, and 14 of the catalogue's product
 * images are SVG artwork — so a product newsletter would arrive with holes in
 * it. email_image_url() looks for these copies in assets/img/products/email/.
 *
 * GD can't read SVG and Imagick isn't installed, so Chrome renders each file
 * headlessly and GD converts the screenshot to a compact JPEG.
 *
 *   php scripts/make-email-images.php          (only files without a copy yet)
 *   php scripts/make-email-images.php --force  (redo everything)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$chrome = null;
foreach ([
    'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
    '/usr/bin/google-chrome',
    '/usr/bin/chromium',
] as $candidate) {
    if (is_file($candidate)) { $chrome = $candidate; break; }
}
if ($chrome === null) {
    exit("No Chrome or Edge found — cannot render SVG.\n");
}

$srcDir = realpath(__DIR__ . '/../assets/img/products');
$outDir = $srcDir . DIRECTORY_SEPARATOR . 'email';
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

$force = in_array('--force', $argv, true);
$tmp   = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fc-email-' . getmypid() . '.png';
$made  = 0;

foreach (glob($srcDir . '/*.svg') as $svg) {
    $name = pathinfo($svg, PATHINFO_FILENAME);
    $out  = $outDir . DIRECTORY_SEPARATOR . $name . '.jpg';
    if (!$force && is_file($out)) {
        continue;
    }

    // The artwork is drawn on a 600x600 viewBox; render it at that size.
    $url = 'file:///' . str_replace('\\', '/', $svg);
    $cmd = sprintf(
        '"%s" --headless=new --disable-gpu --hide-scrollbars --window-size=600,600 --screenshot="%s" "%s" 2>&1',
        $chrome, $tmp, $url
    );
    @unlink($tmp);
    exec($cmd);

    $png = is_file($tmp) ? @imagecreatefrompng($tmp) : false;
    if (!$png) {
        echo "  FAILED  $name\n";
        continue;
    }
    imagejpeg($png, $out, 86);
    imagedestroy($png);
    printf("  %-32s %5.1f KB\n", $name . '.jpg', filesize($out) / 1024);
    $made++;
}

@unlink($tmp);
echo "$made image(s) written to assets/img/products/email/\n";
