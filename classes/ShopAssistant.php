<?php
declare(strict_types=1);

/**
 * The on-site shop assistant.
 *
 * Deliberately rule-based and answering from the live database rather than a
 * language model: it can quote a real price, a real stock level and a real
 * order status, it costs nothing per message, it works with no API key, and it
 * cannot invent a product that does not exist. Every answer it gives is a row
 * someone can go and check.
 *
 * If a hosted model is wired in later, keep this as the fallback — an assistant
 * that is offline is worse than one that is narrow.
 */
final class ShopAssistant
{
    /**
     * @return array{reply:string,chips:string[]}
     */
    public static function answer(string $message, ?array $user = null): array
    {
        $q = mb_strtolower(trim($message));

        if ($q === '') {
            return self::greeting($user);
        }

        foreach (self::intents() as $intent => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($q, $pattern)) {
                    return self::{$intent}($q, $user);
                }
            }
        }

        // No intent matched — try the catalogue, then the FAQ, then give up honestly.
        $found = self::searchCatalogue($q);
        if ($found !== null) {
            return $found;
        }
        $faq = self::searchFaq($q);
        if ($faq !== null) {
            return $faq;
        }

        return [
            'reply' => "I could not find that one. I can help with prices, stock, delivery, payment and order status — "
                     . "or you can " . self::link('/contact.php', 'message a human') . " and someone will come back to you.",
            'chips' => ['What do you sell?', 'Delivery options', 'Best sellers', 'Track my order'],
        ];
    }

    /** intent method => trigger substrings, checked in order. */
    private static function intents(): array
    {
        return [
            'greeting'   => ['hello', 'hi ', 'hey', 'howzit', 'sawubona', 'good morning', 'good afternoon', 'molo'],
            'catalogue'  => ['what do you sell', 'what products', 'catalogue', 'catalog', 'show me', 'browse'],
            'priceOf'    => ['how much', 'price of', 'cost of', 'what does it cost', 'price list', 'pricelist'],
            'delivery'   => ['deliver', 'shipping', 'courier', 'collect', 'pickup', 'pick up', 'runner', 'how long'],
            'payment'    => ['pay', 'payment', 'eft', 'payfast', 'card', 'bank', 'proof of payment', 'pop'],
            'reseller'   => ['reseller', 'resell', 'distributor', 'wholesale', 'profit', 'margin', 'make money', 'tier', 'business'],
            'orderTrack' => ['track', 'my order', 'order status', 'where is my'],
            'returns'    => ['return', 'refund', 'exchange', 'broken', 'damaged'],
            'safety'     => ['safe', 'side effect', 'pregnan', 'breastfeed', 'sahpra', 'registered', 'medication', 'doctor'],
            'contact'    => ['contact', 'phone', 'whatsapp', 'email', 'address', 'where are you', 'speak to'],
            'stock'      => ['in stock', 'available', 'sold out', 'out of stock'],
        ];
    }

    // ── Intent handlers ──────────────────────────────────────────────────

    private static function greeting(string $q = '', ?array $user = null): array
    {
        $name = $user ? ' ' . explode(' ', (string) $user['name'])[0] : '';
        return [
            'reply' => "Hi{$name} — I am the FairCart assistant. I answer from the live catalogue, so anything I quote you is the real current price and stock.",
            'chips' => ['What do you sell?', 'How much is the Rise cleaner?', 'Delivery options', 'How do I pay?'],
        ];
    }

    private static function catalogue(string $q = '', ?array $user = null): array
    {
        $rows = db()->query(
            "SELECT c.name, c.slug, COUNT(p.id) n FROM categories c
             LEFT JOIN products p ON p.category_id = c.id AND p.status='published'
             WHERE c.status='active' GROUP BY c.id HAVING n > 0 ORDER BY c.sort_order"
        )->fetchAll();

        $lines = [];
        foreach ($rows as $r) {
            $lines[] = '• ' . self::link('/shop.php?category=' . $r['slug'], $r['name']) . " ({$r['n']})";
        }

        return [
            'reply' => "We carry four South African brands across these categories:<br>" . implode('<br>', $lines)
                     . "<br><br>" . self::link('/shop.php', 'Browse everything') . '.',
            'chips' => ['Best sellers', 'Delivery options', 'How do I pay?'],
        ];
    }

    private static function priceOf(string $q, ?array $user = null): array
    {
        $found = self::searchCatalogue($q);
        if ($found !== null) {
            return $found;
        }
        return [
            'reply' => "Tell me which product and I will quote the current price. You can also open the full "
                     . self::link('/price-list.php', 'price list') . " — every product and price on one print-friendly page.",
            'chips' => ['Rise cleaner', 'Lady Bloom Juice', 'Car perfume', 'Full price list'],
        ];
    }

    private static function delivery(string $q = '', ?array $user = null): array
    {
        $flat  = format_price((float) get_setting('shipping_flat_rate', '120'));
        $free  = format_price((float) get_setting('free_shipping_threshold', '1500'));
        $runner= format_price((float) get_setting('runner_fee', '20'));
        return [
            'reply' => "Ways to get your order:<br>"
                     . (Pudo::enabled() ? "• <b>Pudo locker</b> — The Courier Guy delivers to a smart locker you pick on a map at checkout, " . fulfilment_price_text('pudo') . ".<br>" : '')
                     . "• <b>The Courier Guy to your door</b> — {$flat}, 2–4 working days. Free over {$free}.<br>"
                     . "• <b>Depot collection</b> — free at the Randburg depot, R20 at regional points.<br>"
                     . "• <b>Runner pickup</b> — {$runner}. An approved Runner collects your packed order and hands it to you; every handover is logged on your order page.<br><br>"
                     . self::link('/faq.php#delivery', 'More on delivery') . '.',
            'chips' => ['Where do I collect?', 'How do I pay?', 'Track my order'],
        ];
    }

    private static function payment(string $q = '', ?array $user = null): array
    {
        return [
            'reply' => (IKhokha::enabled() ? "Pay by card or Instant EFT at checkout — iKhokha's secure page confirms your order instantly. Or choose bank EFT: " : "Payment is bank EFT with a proof-of-payment upload. ")
                     . "place the order, transfer the total to the account shown on your order page, then upload the POP — we confirm within one working day and your stock is held for 48 hours while we wait.",
            'chips' => ['Delivery options', 'Track my order', 'Returns policy'],
        ];
    }

    private static function reseller(string $q = '', ?array $user = null): array
    {
        if (Pricing::isReseller($user)) {
            $d    = ResellerService::dashboard((int) $user['id']);
            $tier = RESELLER_TIERS[$d['current_tier']]['label'];
            $off  = (int) (RESELLER_TIERS[$d['current_tier']]['off_retail'] * 100);
            $next = $d['next_tier']
                ? " Another " . format_price($d['to_next']) . " of paid orders in the next 90 days moves you to " . RESELLER_TIERS[$d['next_tier']]['label'] . "."
                : " You are on the top tier.";
            return [
                'reply' => "You are approved at <b>{$tier}</b> — {$off}% off retail across the catalogue.{$next}<br><br>"
                         . self::link('/dashboards/reseller/index.php', 'Open your reseller dashboard') . '.',
                'chips' => ['My price list', 'Place a stock order', 'Track my order'],
            ];
        }

        $tiers = [];
        foreach (RESELLER_TIERS as $t) {
            $tiers[] = $t['label'] . ' ' . (int) ($t['off_retail'] * 100) . '%';
        }
        return [
            'reply' => "Registration is free — no joining fee, no monthly subscription. You apply, we verify your details, and once approved you see wholesale pricing and your rand profit per unit on every product page.<br><br>"
                     . "Tiers move on your rolling 90-day spend: " . implode(' · ', $tiers) . " off retail.<br><br>"
                     . self::link('/reseller.php', 'See the programme and the profit calculator') . '.',
            'chips' => ['How much can I make?', 'Is there a minimum order?', 'Apply now'],
        ];
    }

    private static function orderTrack(string $q, ?array $user = null): array
    {
        // A bare order number in the message is the fastest path.
        if (preg_match('/\bFC\d{6}[A-Z0-9]{5}\b/i', strtoupper($q), $m)) {
            $order = (new OrderRepository())->getByNumber(strtoupper($m[0]));
            if ($order) {
                $label = ORDER_STATUSES[$order['status']]['label'] ?? $order['status'];
                return [
                    'reply' => "Order <b>{$order['order_number']}</b> is <b>{$label}</b>, "
                             . "placed " . date('j M Y', strtotime($order['created_at'])) . ", total "
                             . format_price((float) $order['total_amount']) . ".",
                    'chips' => ['Delivery options', 'Contact support'],
                ];
            }
            return ['reply' => "I could not find that order number. Check it against your confirmation email.", 'chips' => ['Contact support']];
        }

        if ($user) {
            $recent = (new OrderRepository())->getAll(['user_id' => (int) $user['id']], 1, 3)['items'];
            if ($recent) {
                $lines = [];
                foreach ($recent as $o) {
                    $lines[] = '• ' . self::link('/order-confirmation.php?order=' . urlencode($o['order_number']), $o['order_number'])
                             . ' — ' . (ORDER_STATUSES[$o['status']]['label'] ?? $o['status']);
                }
                return ['reply' => "Your most recent orders:<br>" . implode('<br>', $lines), 'chips' => ['Delivery options', 'Contact support']];
            }
            return ['reply' => "You have no orders yet. " . self::link('/shop.php', 'Start shopping') . '.', 'chips' => ['Best sellers']];
        }

        return [
            'reply' => "Give me your order number (it looks like FC260907AB12C) or " . self::link('/modules/auth/login.php', 'sign in') . " to see all your orders.",
            'chips' => ['Delivery options', 'Contact support'],
        ];
    }

    private static function returns(string $q = '', ?array $user = null): array
    {
        return [
            'reply' => "Unopened, unused stock in its original packaging can be returned within 7 days of delivery for a refund or exchange. Opened consumables cannot be returned for hygiene reasons. Anything damaged in transit is replaced free — send us a photo within 48 hours.<br><br>"
                     . self::link('/refund-policy.php', 'Full returns policy') . '.',
            'chips' => ['Contact support', 'Track my order'],
        ];
    }

    private static function safety(string $q = '', ?array $user = null): array
    {
        return [
            'reply' => "Honest answer: the Blossom supplements are Category D complementary medicines. They are not registered with SAHPRA for quality, safety or intended use, and they are intended to complement a diet — not to treat or cure any condition.<br><br>"
                     . "If you are on chronic medication, pregnant, breastfeeding or under 18, speak to a pharmacist or doctor before starting. I am not able to give medical advice.",
            'chips' => ['Read the label info', 'Contact support'],
        ];
    }

    private static function contact(string $q = '', ?array $user = null): array
    {
        $phone = get_setting('contact_phone', '+27 60 850 8785');
        $email = get_setting('contact_email', SITE_EMAIL);
        $addr  = get_setting('contact_address', '');
        return [
            'reply' => "Phone <b>{$phone}</b> · Email <b>{$email}</b><br>{$addr}<br><br>"
                     . self::link('/contact.php', 'Send a message') . " and it lands in the admin inbox straight away.",
            'chips' => ['Delivery options', 'How do I pay?'],
        ];
    }

    private static function stock(string $q, ?array $user = null): array
    {
        $found = self::searchCatalogue($q);
        if ($found !== null) {
            return $found;
        }
        $n = (int) db()->query("SELECT COUNT(*) FROM products WHERE status='published' AND stock_status='out_of_stock'")->fetchColumn();
        return [
            'reply' => $n === 0
                ? "Everything in the catalogue is in stock right now."
                : "{$n} line(s) are out of stock. Open any product page and use <b>Notify me</b> and we will email you the moment it lands.",
            'chips' => ['Browse the shop', 'Best sellers'],
        ];
    }

    // ── Lookups ──────────────────────────────────────────────────────────

    /** Matches the message against product names, brands and tags. */
    private static function searchCatalogue(string $q): ?array
    {
        // Strip filler so "how much is the rise cleaner" searches for "rise cleaner".
        $stop  = ['how','much','is','the','a','price','of','cost','do','you','have','sell','can','i','buy','what','for','me','get','tell','about','your','does','and','in','stock','it','there','any'];
        $words = array_values(array_filter(
            preg_split('/[^a-z0-9]+/', $q) ?: [],
            fn($w) => $w !== '' && strlen($w) > 2 && !in_array($w, $stop, true)
        ));
        if (!$words) {
            return null;
        }

        $repo    = new ProductRepository();
        $matches = [];
        foreach ($words as $w) {
            foreach ($repo->suggest($w, 4) as $row) {
                $matches[$row['id']] = ($matches[$row['id']] ?? ['row' => $row, 'hits' => 0]);
                $matches[$row['id']]['hits']++;
            }
        }
        if (!$matches) {
            return null;
        }

        usort($matches, fn($a, $b) => $b['hits'] <=> $a['hits']);
        $top  = array_slice($matches, 0, 3);
        $user = current_user();

        $lines = [];
        foreach ($top as $m) {
            $p    = $repo->getById((int) $m['row']['id']);
            $name = self::link('/modules/shop/product.php?slug=' . urlencode($p['slug']), $p['name']);
            $line = "• {$name} — <b>" . format_price(Pricing::retail($p)) . "</b>";
            if ($p['pack_size']) {
                $line .= " ({$p['pack_size']})";
            }
            if ($p['stock_status'] === 'out_of_stock') {
                $line .= ' — <i>out of stock</i>';
            }
            if (Pricing::isReseller($user)) {
                $mg = Pricing::margin($p, $user);
                $line .= "<br>&nbsp;&nbsp;&nbsp;Your cost " . format_price($mg['cost'])
                       . " · profit <b>" . format_price($mg['profit']) . "</b> per unit";
            }
            $lines[] = $line;
        }

        return [
            'reply' => (count($lines) === 1 ? "Here it is:<br>" : "Closest matches:<br>") . implode('<br>', $lines),
            'chips' => ['Delivery options', 'How do I pay?', Pricing::isReseller($user) ? 'My price list' : 'Best sellers'],
        ];
    }

    private static function searchFaq(string $q): ?array
    {
        $words = array_values(array_filter(
            preg_split('/[^a-z0-9]+/', $q) ?: [],
            fn($w) => strlen($w) > 3
        ));
        if (!$words) {
            return null;
        }
        $like = '%' . implode('%', array_slice($words, 0, 3)) . '%';
        $stmt = db()->prepare("SELECT question, answer FROM faqs WHERE status='published' AND (question LIKE :q1 OR answer LIKE :q2) LIMIT 1");
        $stmt->execute([':q1' => $like, ':q2' => $like]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        return [
            'reply' => "<b>" . htmlspecialchars($row['question']) . "</b><br>" . htmlspecialchars($row['answer']),
            'chips' => ['See all FAQs', 'Contact support'],
        ];
    }

    private static function link(string $path, string $text): string
    {
        return '<a href="' . SITE_URL . $path . '">' . htmlspecialchars($text) . '</a>';
    }
}
