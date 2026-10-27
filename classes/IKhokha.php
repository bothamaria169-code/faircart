<?php
declare(strict_types=1);

/**
 * iKhokha iK Pay API: card and Instant EFT through a hosted payment link.
 *
 * Flow: checkout creates the order, then a paylink, and sends the shopper to
 * iKhokha. iKhokha calls modules/payment/ikhokha-callback.php when the payment
 * finishes and sends the shopper back to order-confirmation.php. Neither of
 * those is trusted on its own — both ask iKhokha's getStatus endpoint before an
 * order is marked paid, so a forged callback cannot confirm anything.
 *
 * Keys: iKhokha merchant dashboard → iK Pay API → Application ID + Secret.
 * Signing: IK-SIGN = hex HMAC-SHA256(secret, escape(path + body)).
 */
final class IKhokha
{
    private const BASE = 'https://api.ikhokha.com';
    private const PATH_CREATE = '/public-api/v1/api/payment';
    private const PATH_STATUS = '/public-api/v1/api/getStatus/';

    public static function appId(): string
    {
        return trim(secret_get('ikhokha_app_id'));
    }

    public static function secret(): string
    {
        return trim(secret_get('ikhokha_app_secret'));
    }

    public static function configured(): bool
    {
        return self::appId() !== '' && self::secret() !== '';
    }

    public static function enabled(): bool
    {
        return get_setting('ikhokha_enabled', '1') === '1' && self::configured();
    }

    /** iKhokha's reference escaping: quotes and backslashes escaped, slashes left alone. */
    private static function escape(string $s): string
    {
        $s = preg_replace('/[\\\\"\']/u', '\\\\$0', $s) ?? $s;
        $s = str_replace("\0", '\\0', $s);
        return str_replace('\\/', '/', $s);
    }

    public static function sign(string $path, string $body): string
    {
        return hash_hmac('sha256', self::escape($path . $body), self::secret());
    }

    private static function call(string $method, string $path, ?array $data = null): array
    {
        // Slashes unescaped so the signed string and the sent bytes are identical.
        $body = $data === null ? '' : (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $o = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
                'IK-APPID'     => self::appId(),
                'IK-SIGN'      => self::sign($path, $body),
            ],
            'timeout' => 30,
        ];
        if ($data !== null) {
            $o['body'] = $body;
        }
        $r = http_request($method, self::BASE . $path, $o);
        if ($r['error'] !== '') {
            throw new RuntimeException('Could not reach iKhokha: ' . $r['error']);
        }
        $json = is_array($r['json']) ? $r['json'] : [];
        if ($r['status'] >= 400) {
            throw new RuntimeException('iKhokha said (HTTP ' . $r['status'] . '): ' . ($json['message'] ?? mb_substr(strip_tags($r['body']), 0, 200)));
        }
        return $json;
    }

    /**
     * Creates a payment link for the order and stores it. Returns the URL to send the shopper to.
     */
    public static function createPaylink(array $order): string
    {
        if (!self::configured()) {
            throw new RuntimeException('iKhokha keys are not set in Admin → Integrations.');
        }
        $no   = (string) $order['order_number'];
        $page = absolute_url('order-confirmation.php?order=' . urlencode($no));
        $data = [
            'entityID'              => self::appId(),
            'externalEntityID'      => $no,
            'amount'                => (int) round((float) $order['total_amount'] * 100),
            'currency'              => 'ZAR',
            'requesterUrl'          => SITE_URL,
            'description'           => 'FairCart order ' . $no,
            'paymentReference'      => $no,
            'mode'                  => 'live',
            // Unique per attempt: a shopper who abandons and retries gets a fresh link.
            'externalTransactionID' => $no . '-' . substr(bin2hex(random_bytes(3)), 0, 6),
            'urls' => [
                'callbackUrl'    => absolute_url('modules/payment/ikhokha-callback.php'),
                'successPageUrl' => $page . '&paid=1',
                'failurePageUrl' => $page . '&payment=failed',
                'cancelUrl'      => $page . '&payment=cancelled',
            ],
        ];
        $res = self::call('POST', self::PATH_CREATE, $data);
        if (($res['responseCode'] ?? '') !== '00' || empty($res['paylinkUrl'])) {
            throw new RuntimeException('iKhokha did not create a payment link: ' . ($res['message'] ?? json_encode($res)));
        }
        db_update('orders', (int) $order['id'], [
            'payment_method' => 'ikhokha',
            'paylink_id'     => (string) $res['paylinkID'],
            'paylink_url'    => (string) $res['paylinkUrl'],
        ]);
        return (string) $res['paylinkUrl'];
    }

    /** iKhokha's own word on a paylink: PAID, PENDING, FAILED… */
    public static function status(string $paylinkId): string
    {
        $res = self::call('GET', self::PATH_STATUS . rawurlencode($paylinkId));
        return strtoupper((string) ($res['status'] ?? ''));
    }

    /**
     * Asks iKhokha whether the order is paid and, if so, marks it paid and
     * confirmed (once). Returns true when the order is paid.
     */
    public static function reconcile(array $order): bool
    {
        if (($order['payment_status'] ?? '') === 'paid') {
            return true;
        }
        if (empty($order['paylink_id']) || !self::configured()) {
            return false;
        }
        try {
            $status = self::status((string) $order['paylink_id']);
        } catch (Throwable $e) {
            error_log('iKhokha status check failed for ' . $order['order_number'] . ': ' . $e->getMessage());
            return false;
        }
        if (!in_array($status, ['PAID', 'SUCCESS', 'COMPLETED'], true)) {
            return false;
        }
        // Only the request that flips unpaid -> paid sends the emails.
        $flip = db()->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = :id AND payment_status <> 'paid'");
        $flip->execute([':id' => (int) $order['id']]);
        if ($flip->rowCount() === 1) {
            $repo = new OrderRepository();
            if (($order['status'] ?? '') === 'pending') {
                $repo->setStatus((int) $order['id'], 'confirmed', 'Paid by card via iKhokha.');
            }
            $fresh = $repo->getById((int) $order['id']);
            if ($fresh) {
                notify_order_status($fresh);
                Mailer::send(mail_config()['admin'], 'Paid: ' . $fresh['order_number'] . ' — ' . format_price((float) $fresh['total_amount']),
                    email_wrap('<p>Order <b>' . e($fresh['order_number']) . '</b> was paid by card through iKhokha.</p>'
                        . '<p><a href="' . SITE_URL . '/dashboards/admin/order-detail.php?id=' . (int) $fresh['id'] . '">Open the order</a></p>'));
            }
        }
        return true;
    }
}
