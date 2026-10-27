<?php
declare(strict_types=1);

/**
 * Writes newsletter copy with Groq (OpenAI-compatible chat API, very fast and
 * cheap). It only ever writes words — subject, preview text, label, heading,
 * message, button — for one of the existing templates; products, prices and
 * coupons still come from the database, so the AI cannot invent a price.
 *
 * Key: console.groq.com → API Keys, saved in Admin → Integrations.
 */
final class GroqWriter
{
    private const ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';
    public const DEFAULT_MODEL = 'llama-3.3-70b-versatile';

    public static function configured(): bool
    {
        return secret_get('groq_api_key') !== '';
    }

    /** Best writers first; the first one the key can actually use is the fallback. */
    private const PREFERRED = [
        'llama-3.3-70b-versatile', 'openai/gpt-oss-120b', 'moonshotai/kimi-k2-instruct',
        'meta-llama/llama-4-maverick-17b-128e-instruct', 'qwen/qwen3-32b', 'openai/gpt-oss-20b',
        'meta-llama/llama-4-scout-17b-16e-instruct', 'llama-3.1-8b-instant',
    ];

    public static function model(): string
    {
        return (string) get_setting('groq_model', self::DEFAULT_MODEL);
    }

    /**
     * Chat models this key can use, asked from Groq and cached for a day —
     * Groq retires models often, so a hard-coded list goes stale.
     *
     * @return list<string>
     */
    public static function models(bool $refresh = false): array
    {
        $cached = json_decode((string) get_setting('groq_models', ''), true);
        if (!$refresh && is_array($cached) && ($cached['at'] ?? 0) > time() - 86400 && !empty($cached['ids'])) {
            return $cached['ids'];
        }
        if (!self::configured()) {
            return [];
        }
        $r = http_request('GET', 'https://api.groq.com/openai/v1/models', [
            'headers' => ['Authorization' => 'Bearer ' . secret_get('groq_api_key')], 'timeout' => 20,
        ]);
        $ids = [];
        foreach ($r['json']['data'] ?? [] as $m) {
            $id = (string) ($m['id'] ?? '');
            // Speech, guard and embedding models cannot write copy.
            if ($id !== '' && ($m['active'] ?? true) && !preg_match('/whisper|tts|guard|embed|playai|orpheus|distil|compound/i', $id)) {
                $ids[] = $id;
            }
        }
        if (!$ids) {
            return is_array($cached) ? ($cached['ids'] ?? []) : [];
        }
        // Preferred models first, then the rest alphabetically.
        usort($ids, function ($a, $b) {
            $pa = array_search($a, self::PREFERRED, true);
            $pb = array_search($b, self::PREFERRED, true);
            return ($pa === false ? 99 : $pa) <=> ($pb === false ? 99 : $pb) ?: strcmp($a, $b);
        });
        set_setting('groq_models', json_encode(['at' => time(), 'ids' => $ids]));
        return $ids;
    }

    /** One chat completion that must come back as a JSON object. */
    public static function json(string $system, string $user, float $temperature = 0.8): array
    {
        if (!self::configured()) {
            throw new RuntimeException('Add a Groq API key under Admin → Integrations first.');
        }
        $model = self::model();
        $jsonMode = true;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $body = [
                'model'       => $model,
                'temperature' => $temperature,
                'max_tokens'  => 1500,
                'messages'    => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
            ];
            if ($jsonMode) {
                $body['response_format'] = ['type' => 'json_object'];
            }
            $r = http_request('POST', self::ENDPOINT, [
                'headers' => ['Authorization' => 'Bearer ' . secret_get('groq_api_key')],
                'json'    => $body,
                'timeout' => 60,
            ]);
            if ($r['error'] !== '') {
                throw new RuntimeException('Could not reach Groq: ' . $r['error']);
            }
            if ($r['status'] < 400) {
                break;
            }
            $msg = (string) ($r['json']['error']['message'] ?? 'HTTP ' . $r['status']);
            $code = (string) ($r['json']['error']['code'] ?? '');
            if (preg_match('/does not exist|decommissioned|not have access|model_not_found|no longer supported/i', $msg . ' ' . $code)) {
                // The saved model is gone: switch to the best one this key can use and remember it.
                $next = array_values(array_diff(self::models(true), [$model]))[0] ?? null;
                if ($next === null) {
                    throw new RuntimeException('Groq said: ' . $msg);
                }
                $model = $next;
                set_setting('groq_model', $model);
                continue;
            }
            if ($jsonMode && stripos($msg, 'response_format') !== false) {
                $jsonMode = false;
                continue;
            }
            throw new RuntimeException('Groq said: ' . $msg);
        }
        if (($r['status'] ?? 500) >= 400) {
            throw new RuntimeException('Groq said: ' . ($r['json']['error']['message'] ?? 'HTTP ' . $r['status']));
        }
        $content = trim((string) ($r['json']['choices'][0]['message']['content'] ?? ''));
        // Some models wrap JSON in ```json fences or think aloud first: keep the outermost object.
        if (($a = strpos($content, '{')) !== false && ($b = strrpos($content, '}')) !== false) {
            $content = substr($content, $a, $b - $a + 1);
        }
        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new RuntimeException('Groq did not return usable copy. Try again.');
        }
        return $data;
    }

    /**
     * Newsletter copy for $template featuring $productIds.
     *
     * @return array{subject:string,preheader:string,eyebrow:string,heading:string,intro:string,cta_label:string,sale_note:string}
     */
    public static function newsletter(string $template, array $productIds, string $brief = '', string $couponCode = '', string $audience = 'all'): array
    {
        $products = [];
        if ($productIds) {
            $in   = implode(',', array_map('intval', $productIds));
            $rows = db()->query(
                "SELECT p.id, p.name, p.pack_size, p.short_desc, p.retail_price, p.sale_price, p.is_sale, b.name AS brand
                 FROM products p LEFT JOIN brands b ON b.id = p.brand_id WHERE p.id IN ($in)"
            )->fetchAll();
            foreach ($rows as $p) {
                $price = ((float) $p['sale_price'] > 0 && (float) $p['sale_price'] < (float) $p['retail_price']) ? (float) $p['sale_price'] : (float) $p['retail_price'];
                $products[] = '- ' . $p['name'] . ($p['pack_size'] ? ' (' . $p['pack_size'] . ')' : '') . ' by ' . $p['brand']
                    . ', ' . format_price_short($price) . ($price < (float) $p['retail_price'] ? ' (was ' . format_price_short((float) $p['retail_price']) . ')' : '')
                    . ($p['short_desc'] ? ' — ' . excerpt((string) $p['short_desc'], 160) : '');
            }
        }

        $coupon = '';
        if ($couponCode !== '') {
            $st = db()->prepare('SELECT code, discount_type, discount_value, min_order, expiry_date FROM coupons WHERE code = :c');
            $st->execute([':c' => $couponCode]);
            if ($c = $st->fetch()) {
                $coupon = 'Coupon ' . $c['code'] . ': ' . ($c['discount_type'] === 'percentage' ? rtrim(rtrim((string) $c['discount_value'], '0'), '.') . '% off' : format_price_short((float) $c['discount_value']) . ' off')
                    . ((float) $c['min_order'] > 0 ? ' on orders over ' . format_price_short((float) $c['min_order']) : '')
                    . ($c['expiry_date'] ? ', ends ' . date('j F', strtotime((string) $c['expiry_date'])) : '');
            }
        }

        $kind = [
            'spotlight'    => 'a single-product spotlight email',
            'grid'         => 'a "new arrivals / favourites" email showing a grid of products',
            'sale'         => 'a sale email built around a coupon code',
            'announcement' => 'a short plain announcement with no products',
        ][$template] ?? 'a product newsletter';
        $who = ['retail' => 'shoppers', 'reseller' => 'resellers who buy wholesale to resell for profit', 'all' => 'shoppers and resellers'][$audience] ?? 'shoppers';

        $system = <<<TXT
You write marketing emails for FairCart (faircart.co.za), a South African online shop selling Blossom wellness drinks and supplements, Pure Fragrances home and car fragrance and pest control, Rise cleaning products and ML Group mould treatment, with a reseller programme.
Voice: warm, direct, South African English (colour, favourite, rand), no hype words like "revolutionary", no ALL CAPS, at most one emoji in the subject and none elsewhere.
Hard rules: never make medical or health claims (supplements are Category D complementary medicines not evaluated by SAHPRA — say "supports" or describe taste/routine, never "cures", "treats", "heals", "burns fat" or "detoxes"); never invent prices, discounts, stock levels or dates that are not given; mention only the products listed.
Reply with JSON only, keys: subject (max 60 chars), preheader (max 110), eyebrow (2–4 words), heading (max 70), intro (2–3 short paragraphs separated by \\n, max 600 chars total), cta_label (2–4 words), sale_note (max 90, only for a sale email, else "").
TXT;
        $user = "Write $kind for $who.\n"
            . ($brief !== '' ? "Brief from the shop owner: $brief\n" : '')
            . ($products ? "Products featured:\n" . implode("\n", $products) . "\n" : '')
            . ($coupon !== '' ? "$coupon\n" : '')
            . 'Today is ' . date('l j F Y') . '.';

        $d = self::json($system, $user);
        $s = fn(string $k, int $max) => mb_substr(trim((string) ($d[$k] ?? '')), 0, $max);
        return [
            'subject'   => $s('subject', 120),
            'preheader' => $s('preheader', 200),
            'eyebrow'   => $s('eyebrow', 60),
            'heading'   => $s('heading', 160),
            'intro'     => $s('intro', 3000),
            'cta_label' => $s('cta_label', 60),
            'sale_note' => $s('sale_note', 160),
        ];
    }

    /** A Facebook/Instagram caption for a product (used by the social poster when AI captions are on). */
    public static function caption(array $product, string $price, string $platform): string
    {
        $system = 'You write short social media captions for FairCart, a South African online shop. Friendly, South African English, 2 short lines, 1–2 emoji, no hashtags, no prices, no medical or health claims, no invented facts. Reply as JSON {"caption": "..."}.';
        $user   = 'Product: ' . $product['name'] . ($product['pack_size'] ? ' (' . $product['pack_size'] . ')' : '') . ' by ' . ($product['brand_name'] ?? '')
            . '. About it: ' . excerpt((string) ($product['short_desc'] ?? $product['description'] ?? ''), 300) . ". Platform: $platform.";
        return trim((string) (self::json($system, $user, 0.9)['caption'] ?? ''));
    }
}
