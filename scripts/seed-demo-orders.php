<?php
declare(strict_types=1);

/**
 * Generates a spread of plausible orders across the last 45 days so the admin
 * dashboard, the revenue chart and the reseller dashboard have something real
 * to show. Strictly a demo aid — delete the file before launch, or at least
 * never run it against a live database.
 *
 *   php scripts/seed-demo-orders.php          # add demo orders
 *   php scripts/seed-demo-orders.php --reset  # wipe ALL orders first
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../includes/functions.php';

if (IS_PRODUCTION) {
    exit("Refusing to run against a production database.\n");
}

$reset = in_array('--reset', $argv, true);
if ($reset) {
    db()->exec('SET FOREIGN_KEY_CHECKS = 0');
    db()->exec('TRUNCATE order_items');
    db()->exec('TRUNCATE order_events');
    db()->exec('TRUNCATE orders');
    db()->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "Cleared existing orders.\n";
}

$repo     = new ProductRepository();
$products = $repo->getAll(['status' => 'published'], 1, 200)['items'];

$shopper  = db()->query("SELECT * FROM users WHERE email='shopper@faircart.co.za' LIMIT 1")->fetch() ?: null;
$reseller = db()->query("SELECT * FROM users WHERE email='reseller@faircart.co.za' LIMIT 1")->fetch() ?: null;

$guests = [
    ['Amanda Pretorius', 'amanda.p@example.co.za',  '+27 82 445 1120', 'Randburg'],
    ['Thabo Sithole',    'thabo.s@example.co.za',   '+27 71 220 8834', 'Kempton Park'],
    ['Lerato Dlamini',   'lerato.d@example.co.za',  '+27 83 991 4477', 'Centurion'],
    ['Farhaan Cassim',   'farhaan.c@example.co.za', '+27 84 336 2091', 'Lenasia'],
    ['Nomsa Khumalo',    'nomsa.k@example.co.za',   '+27 79 118 5502', 'Soweto'],
];

$orderRepo = new OrderRepository();
$made      = 0;

for ($day = 44; $day >= 0; $day--) {
    // Weekends are quieter, the last fortnight busier — a shape rather than noise.
    $date    = new DateTimeImmutable("-{$day} day");
    $weekend = in_array((int) $date->format('N'), [6, 7], true);
    $count   = random_int(0, $weekend ? 1 : ($day < 14 ? 3 : 2));

    for ($n = 0; $n < $count; $n++) {
        $isReseller = $reseller && random_int(1, 100) <= 35;
        $buyer      = $isReseller ? $reseller : (random_int(1, 100) <= 30 ? $shopper : null);

        if ($buyer) {
            $name  = $buyer['name'];
            $email = $buyer['email'];
            $phone = $buyer['phone'] ?: '+27 82 000 0000';
        } else {
            [$name, $email, $phone] = $guests[array_rand($guests)];
        }

        // Resellers buy fewer lines, deeper; shoppers buy more lines, shallower.
        $lines    = [];
        $subtotal = 0.0;
        $resale   = 0.0;
        $picked   = (array) array_rand($products, $isReseller ? random_int(1, 3) : random_int(1, 4));

        foreach ($picked as $idx) {
            $p   = $products[$idx];
            $qty = $isReseller ? max((int) $p['min_reseller_qty'], random_int(6, 24)) : random_int(1, 3);

            $unit = $isReseller
                ? Pricing::reseller($p, $reseller)
                : Pricing::retail($p);
            $rrp  = Pricing::rrp($p);

            $subtotal += $unit * $qty;
            $resale   += $rrp * $qty;

            $lines[] = [
                'product_id'   => (int) $p['id'],
                'variant_id'   => null,
                'name'         => $p['name'],
                'variant_text' => null,
                'sku'          => $p['sku'],
                'qty'          => $qty,
                'unit_price'   => $unit,
                'unit_rrp'     => $rrp,
                'image'        => $p['featured_image'],
            ];
        }

        $fulfilment = ['delivery', 'delivery', 'collection', 'runner'][random_int(0, 3)];
        $shipping   = shipping_cost($fulfilment, $subtotal, 1);
        $total      = $subtotal + $shipping;

        // Older orders have mostly run their course; recent ones are still moving.
        // Cancellations sit at roughly 1 in 20, which is what a shop like this
        // actually sees — a demo that shows a 25% cancellation rate is worse
        // than no demo data at all.
        if ($day > 12) {
            $status = random_int(1, 20) === 1 ? 'cancelled' : 'delivered';
        } elseif ($day > 6) {
            $status = ['delivered', 'delivered', 'shipped', 'ready'][random_int(0, 3)];
        } elseif ($day > 2) {
            $status = ['confirmed', 'packing', 'packing', 'shipped'][random_int(0, 3)];
        } else {
            $status = ['pending', 'pending', 'confirmed'][random_int(0, 2)];
        }

        $payment = $status === 'pending' ? 'unpaid' : ($status === 'cancelled' ? 'refunded' : 'paid');

        $created = $date->setTime(random_int(8, 20), random_int(0, 59))->format('Y-m-d H:i:s');

        $created_order = $orderRepo->create([
            'user_id'          => $buyer['id'] ?? null,
            'order_type'       => $isReseller ? 'reseller' : 'retail',
            'customer_name'    => $name,
            'customer_email'   => $email,
            'customer_phone'   => $phone,
            'subtotal'         => round($subtotal, 2),
            'discount_amount'  => 0,
            'shipping_amount'  => $shipping,
            'total_amount'     => round($total, 2),
            'est_resale_value' => $isReseller ? round($resale, 2) : 0,
            'status'           => $status,
            'payment_method'   => 'eft',
            'payment_status'   => $payment,
            'fulfilment'       => $fulfilment,
            'pickup_point_id'  => $fulfilment === 'delivery' ? null : 1,
            'shipping_address' => $fulfilment === 'delivery'
                ? "12 Example Street\nSuburb, Gauteng, 2194"
                : 'Randburg Depot — 152 Bram Fischer Drive, Blairgowrie',
            'created_at'       => $created,
        ], $lines);

        // create() stamps "now"; backdate the row and its opening event.
        db()->prepare('UPDATE orders SET created_at = :c, updated_at = :c2 WHERE id = :id')
            ->execute([':c' => $created, ':c2' => $created, ':id' => $created_order['id']]);
        db()->prepare('UPDATE order_events SET created_at = :c WHERE order_id = :id')
            ->execute([':c' => $created, ':id' => $created_order['id']]);

        $made++;
    }
}

// Stock took a hammering from all those simulated sales — put it back.
db()->exec("UPDATE products SET stock_qty = 60 + FLOOR(RAND()*140), stock_status='in_stock'");
db()->exec("UPDATE products SET stock_status='low_stock' WHERE id IN (SELECT * FROM (SELECT id FROM products ORDER BY RAND() LIMIT 2) t)");
db()->exec("UPDATE products SET stock_qty = 8 WHERE stock_status='low_stock'");
db()->exec("UPDATE product_variants SET stock_qty = 20 + FLOOR(RAND()*45)");

echo "Created $made demo order(s) across the last 45 days.\n";
echo "Stock levels reset.\n";
