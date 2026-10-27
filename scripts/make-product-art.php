<?php
/**
 * Generates the branded SVG product renders used for catalogue items we have
 * no studio photography for yet. Each render is a simple, honest illustration
 * of the actual pack format (trigger spray, flacon, pouch, tub…) drawn in the
 * FairCart palette — so a catalogue with mixed photo/illustration still reads
 * as one shelf. Re-runnable: `php scripts/make-product-art.php`.
 *
 * Replace any of these with real photography by dropping a JPG of the same
 * basename into assets/img/products/ and updating the product's featured_image.
 */
declare(strict_types=1);

$OUT = __DIR__ . '/../assets/img/products';
@mkdir($OUT, 0775, true);

/** Shared defs: soft ground, glass sheen, drop shadow. */
function defs(string $groundA, string $groundB, string $bodyA, string $bodyB): string
{
    return <<<SVG
  <defs>
    <linearGradient id="ground" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="$groundA"/><stop offset="1" stop-color="$groundB"/>
    </linearGradient>
    <linearGradient id="body" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0" stop-color="$bodyA"/>
      <stop offset="0.42" stop-color="$bodyB"/>
      <stop offset="1" stop-color="$bodyA"/>
    </linearGradient>
    <linearGradient id="sheen" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0" stop-color="#fff" stop-opacity="0"/>
      <stop offset="0.5" stop-color="#fff" stop-opacity=".45"/>
      <stop offset="1" stop-color="#fff" stop-opacity="0"/>
    </linearGradient>
    <radialGradient id="shadow" cx="0.5" cy="0.5" r="0.5">
      <stop offset="0" stop-color="#0E1726" stop-opacity=".28"/>
      <stop offset="1" stop-color="#0E1726" stop-opacity="0"/>
    </radialGradient>
  </defs>
SVG;
}

function label(string $text, float $cx, float $y, float $size, string $fill, float $weight = 700, float $tracking = 0.06): string
{
    $t = htmlspecialchars($text, ENT_QUOTES);
    return sprintf(
        '<text x="%.1f" y="%.1f" text-anchor="middle" font-family="Plus Jakarta Sans, Segoe UI, Arial, sans-serif" font-size="%.1f" font-weight="%d" letter-spacing="%.2f" fill="%s">%s</text>',
        $cx, $y, $size, (int) $weight, $tracking * $size, $fill, $t
    );
}

function shadow(float $cx, float $cy, float $rx, float $ry): string
{
    return sprintf('<ellipse cx="%.1f" cy="%.1f" rx="%.1f" ry="%.1f" fill="url(#shadow)"/>', $cx, $cy, $rx, $ry);
}

/** Wrap the finished art in a 600×600 canvas. */
function canvas(string $inner, string $groundA, string $groundB, string $bodyA, string $bodyB): string
{
    $d = defs($groundA, $groundB, $bodyA, $bodyB);
    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 600" width="600" height="600" role="img">
$d
  <rect width="600" height="600" fill="url(#ground)"/>
$inner
</svg>
SVG;
}

// ── Pack silhouettes ─────────────────────────────────────────────────────

/** Trigger spray bottle (cleaners, repellents). */
function trigger_spray(string $name, string $sub, string $capColor, string $textOnBody): string
{
    return shadow(300, 520, 150, 26) . <<<SVG
  <g>
    <!-- trigger head -->
    <path d="M262 118 h48 a10 10 0 0 1 10 10 v26 h-68 v-26 a10 10 0 0 1 10 -10 z" fill="$capColor"/>
    <path d="M246 154 h88 v20 h-88 z" fill="$capColor" opacity=".85"/>
    <path d="M244 128 h-38 a8 8 0 0 0 -8 8 v10 a8 8 0 0 0 8 8 h18 l20 14 v-40 z" fill="$capColor"/>
    <rect x="252" y="120" width="10" height="34" rx="4" fill="$capColor" opacity=".6"/>
    <!-- neck -->
    <rect x="268" y="174" width="64" height="24" fill="$capColor" opacity=".9"/>
    <!-- body -->
    <path d="M252 198 h96 a26 26 0 0 1 26 26 v230 a26 26 0 0 1 -26 26 h-96 a26 26 0 0 1 -26 -26 v-230 a26 26 0 0 1 26 -26 z" fill="url(#body)"/>
    <rect x="240" y="210" width="20" height="248" rx="10" fill="url(#sheen)"/>
    <!-- label plate -->
    <rect x="240" y="272" width="120" height="132" rx="10" fill="#fff" opacity=".94"/>
SVG
    . label($name, 300, 316, 20, '#0E1726') . label($sub, 300, 342, 12, '#5A6675', 600, .1)
    . '<rect x="262" y="356" width="76" height="2" fill="#E2E6EC"/>'
    . label('FAIRCART', 300, 384, 10, $textOnBody, 800, .22)
    . '</g>';
}

/** Straight-wall juice / tonic bottle. */
function juice_bottle(string $name, string $flavour, string $capColor, string $accent): string
{
    return shadow(300, 524, 132, 24) . <<<SVG
  <g>
    <rect x="266" y="96" width="68" height="40" rx="8" fill="$capColor"/>
    <rect x="272" y="102" width="12" height="28" rx="6" fill="#fff" opacity=".22"/>
    <rect x="274" y="136" width="52" height="18" fill="$capColor" opacity=".7"/>
    <path d="M274 154 q-56 26 -56 96 v186 a30 30 0 0 0 30 30 h104 a30 30 0 0 0 30 -30 v-186 q0 -70 -56 -96 z" fill="url(#body)"/>
    <rect x="236" y="230" width="18" height="212" rx="9" fill="url(#sheen)"/>
    <rect x="222" y="262" width="156" height="150" rx="8" fill="#fff" opacity=".95"/>
    <rect x="222" y="262" width="156" height="8" rx="4" fill="$accent"/>
SVG
    . label($name, 300, 306, 21, '#0E1726')
    . label($flavour, 300, 332, 12, '#5A6675', 600, .12)
    . '<circle cx="300" cy="362" r="15" fill="' . $accent . '" opacity=".16"/>'
    . label('500ml', 300, 367, 11, $accent, 800, .06)
    . label('FAIRCART', 300, 398, 9, '#8A94A3', 800, .24)
    . '</g>';
}

/** Small perfume flacon. */
function flacon(string $name, string $sub, string $capColor, string $accent): string
{
    return shadow(300, 500, 108, 20) . <<<SVG
  <g>
    <rect x="272" y="126" width="56" height="46" rx="6" fill="$capColor"/>
    <rect x="278" y="132" width="10" height="34" rx="5" fill="#fff" opacity=".2"/>
    <rect x="284" y="172" width="32" height="22" fill="$capColor" opacity=".75"/>
    <rect x="216" y="194" width="168" height="272" rx="14" fill="url(#body)"/>
    <rect x="228" y="212" width="16" height="236" rx="8" fill="url(#sheen)"/>
    <rect x="238" y="272" width="124" height="128" rx="6" fill="#fff" opacity=".95"/>
    <circle cx="300" cy="308" r="20" fill="none" stroke="$accent" stroke-width="2"/>
SVG
    . label('PF', 300, 314, 14, $accent, 800, .04)
    . label($name, 300, 348, 17, '#0E1726')
    . label($sub, 300, 372, 11, '#5A6675', 600, .12)
    . label('FAIRCART', 300, 392, 8.5, '#8A94A3', 800, .24)
    . '</g>';
}

/** Reed diffuser. */
function diffuser(string $name, string $sub, string $accent): string
{
    $reeds = '';
    foreach ([-30, -18, -6, 6, 18, 30] as $i => $x) {
        $lean = $x * 0.9;
        $reeds .= sprintf(
            '<line x1="%.1f" y1="252" x2="%.1f" y2="%.1f" stroke="#3B2A1C" stroke-width="5" stroke-linecap="round"/>',
            300 + $x, 300 + $x + $lean, 118 + abs($i - 2.5) * 8
        );
    }
    return shadow(300, 512, 116, 22) . $reeds . <<<SVG
  <g>
    <rect x="264" y="244" width="72" height="18" rx="6" fill="#1B2739"/>
    <rect x="220" y="262" width="160" height="212" rx="16" fill="url(#body)"/>
    <rect x="232" y="280" width="16" height="176" rx="8" fill="url(#sheen)"/>
    <rect x="242" y="326" width="116" height="112" rx="6" fill="#fff" opacity=".95"/>
SVG
    . label($name, 300, 366, 16, '#0E1726')
    . label($sub, 300, 390, 11, '#5A6675', 600, .12)
    . '<rect x="270" y="404" width="60" height="2" fill="' . $accent . '"/>'
    . label('FAIRCART', 300, 424, 8.5, '#8A94A3', 800, .24)
    . '</g>';
}

/** Hanging car diffuser / mirror hanger. */
function hanging(string $name, string $sub, string $accent, bool $heart = false): string
{
    $vessel = $heart
        ? '<path d="M300 300 c-30 -40 -78 -18 -78 22 c0 42 52 70 78 96 c26 -26 78 -54 78 -96 c0 -40 -48 -62 -78 -22 z" fill="url(#body)"/>'
        : '<rect x="248" y="286" width="104" height="132" rx="18" fill="url(#body)"/>';
    return shadow(300, 470, 96, 18) . <<<SVG
  <g>
    <path d="M300 92 q-46 34 -46 74 q0 26 46 26 q46 0 46 -26 q0 -40 -46 -74 z" fill="none" stroke="#B4885A" stroke-width="7" stroke-linecap="round"/>
    <rect x="282" y="192" width="36" height="42" rx="6" fill="#7A5433"/>
    <rect x="288" y="234" width="24" height="18" fill="#8C6440"/>
    <rect x="272" y="252" width="56" height="34" rx="6" fill="#3B2A1C"/>
    $vessel
    <rect x="262" y="308" width="12" height="82" rx="6" fill="url(#sheen)"/>
SVG
    . label($name, 300, 452, 16, '#0E1726')
    . label($sub, 300, 474, 11, '#5A6675', 600, .12)
    . '<circle cx="300" cy="352" r="16" fill="#fff" opacity=".9"/>'
    . label('PF', 300, 357, 11, $accent, 800, .04)
    . '</g>';
}

/** Amber dropper bottle. */
function dropper(string $name, string $sub, string $accent): string
{
    return shadow(300, 500, 96, 18) . <<<SVG
  <g>
    <rect x="278" y="96" width="44" height="16" rx="6" fill="#1B2739"/>
    <rect x="284" y="112" width="32" height="62" rx="8" fill="#2B384A"/>
    <rect x="270" y="174" width="60" height="26" rx="6" fill="#1B2739"/>
    <rect x="296" y="200" width="8" height="120" fill="#C8873A" opacity=".55"/>
    <path d="M292 320 h16 l-8 22 z" fill="#C8873A" opacity=".8"/>
    <rect x="228" y="200" width="144" height="266" rx="18" fill="url(#body)"/>
    <rect x="240" y="220" width="16" height="228" rx="8" fill="url(#sheen)"/>
    <rect x="248" y="286" width="104" height="122" rx="6" fill="#fff" opacity=".95"/>
SVG
    . label($name, 300, 326, 17, '#0E1726')
    . label($sub, 300, 350, 11, '#5A6675', 600, .12)
    . '<rect x="276" y="364" width="48" height="2" fill="' . $accent . '"/>'
    . label('FAIRCART', 300, 390, 8.5, '#8A94A3', 800, .24)
    . '</g>';
}

/** Screw-top jar / tub. */
function jar(string $name, string $sub, string $capColor, string $accent): string
{
    return shadow(300, 480, 128, 22) . <<<SVG
  <g>
    <rect x="196" y="188" width="208" height="52" rx="14" fill="$capColor"/>
    <rect x="196" y="228" width="208" height="10" fill="#000" opacity=".12"/>
    <rect x="206" y="238" width="188" height="204" rx="20" fill="url(#body)"/>
    <rect x="218" y="256" width="18" height="168" rx="9" fill="url(#sheen)"/>
    <rect x="228" y="290" width="144" height="118" rx="8" fill="#fff" opacity=".95"/>
    <rect x="228" y="290" width="144" height="7" rx="3.5" fill="$accent"/>
SVG
    . label($name, 300, 334, 18, '#0E1726')
    . label($sub, 300, 358, 11, '#5A6675', 600, .12)
    . label('FAIRCART', 300, 390, 9, '#8A94A3', 800, .24)
    . '</g>';
}

/** Stand-up foil pouch. */
function pouch(string $name, string $sub, string $accent): string
{
    return shadow(300, 502, 132, 22) . <<<SVG
  <g>
    <path d="M188 140 h224 a14 14 0 0 1 14 14 v300 a20 20 0 0 1 -20 20 h-212 a20 20 0 0 1 -20 -20 v-300 a14 14 0 0 1 14 -14 z" fill="url(#body)"/>
    <path d="M188 140 h224 v22 h-224 z" fill="#0E1726" opacity=".16"/>
    <rect x="200" y="168" width="20" height="288" rx="10" fill="url(#sheen)"/>
    <rect x="214" y="230" width="172" height="176" rx="10" fill="#fff" opacity=".95"/>
    <rect x="214" y="230" width="172" height="8" rx="4" fill="$accent"/>
SVG
    . label($name, 300, 288, 20, '#0E1726')
    . label($sub, 300, 316, 12, '#5A6675', 600, .12)
    . '<circle cx="300" cy="352" r="20" fill="' . $accent . '" opacity=".14"/>'
    . label('NET', 300, 350, 9, $accent, 800, .1)
    . label('WT', 300, 362, 9, $accent, 800, .1)
    . label('FAIRCART', 300, 392, 9, '#8A94A3', 800, .24)
    . '</g>';
}

/** Slim pump bottle (body oils). */
function pump(string $name, string $sub, string $accent): string
{
    return shadow(300, 500, 96, 18) . <<<SVG
  <g>
    <rect x="278" y="94" width="44" height="30" rx="8" fill="#E8DCC8"/>
    <rect x="256" y="112" width="52" height="16" rx="8" fill="#E8DCC8"/>
    <rect x="286" y="124" width="28" height="46" rx="6" fill="#D9C9AE"/>
    <rect x="270" y="170" width="60" height="24" rx="4" fill="#CBB894"/>
    <rect x="238" y="194" width="124" height="272" rx="16" fill="url(#body)"/>
    <rect x="250" y="214" width="14" height="234" rx="7" fill="url(#sheen)"/>
    <rect x="252" y="280" width="96" height="132" rx="6" fill="#fff" opacity=".92"/>
SVG
    . label($name, 300, 322, 15, '#0E1726')
    . label($sub, 300, 344, 10, '#5A6675', 600, .12)
    . '<rect x="278" y="358" width="44" height="2" fill="' . $accent . '"/>'
    . label('FAIRCART', 300, 386, 8, '#8A94A3', 800, .24)
    . '</g>';
}

/** Two supplement tubs side by side (AM/PM). */
function tub_pair(string $left, string $right, string $accent): string
{
    return shadow(300, 486, 148, 22) . <<<SVG
  <g>
    <rect x="150" y="212" width="140" height="34" rx="10" fill="#1B2739"/>
    <rect x="158" y="246" width="124" height="216" rx="16" fill="url(#body)"/>
    <rect x="168" y="264" width="14" height="180" rx="7" fill="url(#sheen)"/>
    <rect x="176" y="304" width="88" height="112" rx="6" fill="#fff" opacity=".93"/>
SVG
    . label($left, 220, 348, 17, '#0E1726')
    . label('60 caps', 220, 372, 10, '#5A6675', 600, .1)
    . '<rect x="200" y="386" width="40" height="2" fill="' . $accent . '"/>'
    . <<<SVG
    <rect x="310" y="212" width="140" height="34" rx="10" fill="#1B2739"/>
    <rect x="318" y="246" width="124" height="216" rx="16" fill="url(#body)"/>
    <rect x="328" y="264" width="14" height="180" rx="7" fill="url(#sheen)"/>
    <rect x="336" y="304" width="88" height="112" rx="6" fill="#fff" opacity=".93"/>
SVG
    . label($right, 380, 348, 17, '#0E1726')
    . label('60 caps', 380, 372, 10, '#5A6675', 600, .1)
    . '<rect x="360" y="386" width="40" height="2" fill="' . $accent . '"/>'
    . '</g>';
}

/** A shipper case of bottles (student / bulk packs). */
function case_pack(string $name, string $sub, string $accent, string $bottleColor): string
{
    $bottles = '';
    for ($r = 0; $r < 2; $r++) {
        for ($c = 0; $c < 6; $c++) {
            $x = 174 + $c * 42 + $r * 6;
            $y = 196 + $r * 22;
            $bottles .= sprintf(
                '<rect x="%d" y="%d" width="28" height="46" rx="8" fill="%s" opacity="%.2f"/><rect x="%d" y="%d" width="12" height="10" rx="4" fill="#1B2739" opacity="%.2f"/>',
                $x, $y, $bottleColor, $r ? .75 : 1, $x + 8, $y - 10, $r ? .75 : 1
            );
        }
    }
    return shadow(300, 488, 152, 22) . $bottles . <<<SVG
  <g>
    <path d="M156 262 h288 l-16 200 h-256 z" fill="url(#body)"/>
    <path d="M156 262 h288 v18 h-288 z" fill="#0E1726" opacity=".14"/>
    <rect x="212" y="308" width="176" height="118" rx="8" fill="#fff" opacity=".95"/>
    <rect x="212" y="308" width="176" height="8" rx="4" fill="$accent"/>
SVG
    . label($name, 300, 356, 18, '#0E1726')
    . label($sub, 300, 380, 11, '#5A6675', 600, .12)
    . label('12 × 500ml', 300, 408, 12, $accent, 800, .08)
    . '</g>';
}

// ── Catalogue of renders ─────────────────────────────────────────────────

$AMBER = '#E9A21B';
$LEAF  = '#12805C';
$INK   = '#0E1726';
$PLUM  = '#6B3FA0';

$art = [
    // Pure Fragrances
    'room-linen-spray'      => [flacon('ROOM & LINEN', '250ml spray', '#C9A227', $AMBER),                 '#F4F1EA', '#E6E0D3', '#20252E', '#3A4250'],
    'home-diffuser'         => [diffuser('HOME DIFFUSER', '150ml · reeds', $AMBER),                        '#F6F2E9', '#EAE2D2', '#2B3341', '#465165'],
    'signature-perfume'     => [flacon('PERFUME', '30ml eau de parfum', '#1B2739', $AMBER),                '#F7F4EE', '#EDE7DA', '#DCE3EC', '#F4F7FA'],
    'car-perfume'           => [hanging('CAR PERFUME', '50ml hanging bottle', $AMBER),                     '#F3F5F7', '#E4E8ED', '#E9EEF4', '#FBFCFE'],
    'car-perfume-dropper'   => [dropper('CAR DROPPER', 'Refill concentrate', $AMBER),                      '#F6F3EC', '#E9E2D4', '#8A5A22', '#B9803A'],
    'car-mirror-hanger'     => [hanging('MIRROR HANGER', 'Scented glass charm', $AMBER, true),             '#F5F3F6', '#E8E4EC', '#EFE6F2', '#FBF8FD'],
    'cockroach-spray'       => [trigger_spray('COCKROACH', '100ml eliminator', '#1B2739', $LEAF),          '#F1F5F2', '#E1EAE4', '#F2F5F7', '#FFFFFF'],
    'fly-repellent'         => [trigger_spray('FLY REPELLENT', '500ml trigger spray', '#22303F', $LEAF),   '#F1F5F2', '#DFE9E2', '#F4F6F8', '#FFFFFF'],
    'rat-butter'            => [jar('RAT BUTTER', '200g bait paste', '#1B2739', '#C9A227'),                '#F6F3EA', '#E9E2D0', '#E6C97A', '#F5E4B0'],
    'wonke-wonke-cleaner'   => [trigger_spray('WONKE WONKE', '500ml multi-purpose', '#1B2739', '#D6456B'), '#FBF1F4', '#F2DDE4', '#E8607F', '#F49AB0'],
    'pit-mzansi-fresh'      => [pouch('PIT MZANSI FRESH', '500g toilet treatment', $LEAF),                 '#F1F5F2', '#DFE9E2', '#8E96A2', '#C3CAD3'],
    // Blossom
    'luna-bloom-juice'      => [juice_bottle('LUNA BLOOM', 'Berry · the HERO', '#6B3FA0', $PLUM),          '#F5F2FA', '#E7E0F2', '#3B2A55', '#5B4180'],
    'lady-bloom-mango'      => [juice_bottle('LADY BLOOM', 'Mango passion', '#F1B434', $AMBER),            '#FDF7EA', '#F6EAD0', '#D99A16', '#F4C255'],
    'lady-bloom-cranberry'  => [juice_bottle('LADY BLOOM', 'Cranberry pomegranate', '#C2255C', '#C2255C'), '#FCF1F5', '#F4DEE7', '#7E1E39', '#B33459'],
    'shrink-am-pm'          => [tub_pair('shrinkAM', 'shrinkPM', $AMBER),                                  '#F3F4F6', '#E4E7EB', '#171C24', '#2B333F'],
    'firming-body-oil'      => [pump('FIRMING', 'Body oil · 60ml', $AMBER),                                '#FBF6EA', '#F3E9D3', '#D8A72A', '#F2CE63'],
    'lady-bloom-student-12' => [case_pack('STUDENT PACK', 'Lady Bloom Juice', $LEAF, '#C2255C'),           '#F1F5F2', '#DFE9E2', '#C8955A', '#E2B87F'],
];

foreach ($art as $slug => [$inner, $gA, $gB, $bA, $bB]) {
    file_put_contents("$OUT/$slug.svg", canvas($inner, $gA, $gB, $bA, $bB));
    echo "wrote $slug.svg\n";
}

// Neutral placeholder used whenever an image is missing entirely.
file_put_contents("$OUT/../placeholder.svg", canvas(
    shadow(300, 470, 110, 20)
    . '<rect x="216" y="180" width="168" height="264" rx="20" fill="url(#body)"/>'
    . '<rect x="228" y="200" width="16" height="224" rx="8" fill="url(#sheen)"/>'
    . label('FAIRCART', 300, 320, 16, '#8A94A3', 800, .24)
    . label('image coming soon', 300, 346, 11, '#A6AEBA', 600, .06),
    '#F6F7F9', '#E9ECF1', '#D9DEE6', '#EFF2F6'
));
echo "wrote placeholder.svg\n";
echo "done — " . (count($art) + 1) . " files\n";
