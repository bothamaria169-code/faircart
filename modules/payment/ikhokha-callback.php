<?php
declare(strict_types=1);

/**
 * iKhokha's webhook, called once a payment attempt finishes. The body is not
 * trusted: we only take the paylink ID from it and ask iKhokha's getStatus API
 * whether that paylink is paid (IKhokha::reconcile), so a forged call can do
 * nothing but trigger a harmless status check.
 */
require_once __DIR__ . '/../../includes/functions.php';

$body = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
$paylinkId = (string) ($body['paylinkID'] ?? $body['paylinkId'] ?? '');

if ($paylinkId === '' || !IKhokha::configured()) {
    http_response_code(400);
    exit('Bad request');
}

$st = db()->prepare('SELECT order_number FROM orders WHERE paylink_id = :p LIMIT 1');
$st->execute([':p' => $paylinkId]);
$number = $st->fetchColumn();
if (!$number) {
    http_response_code(404);
    exit('Unknown paylink');
}

$order = (new OrderRepository())->getByNumber((string) $number);
$paid  = $order ? IKhokha::reconcile($order) : false;

http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['received' => true, 'paid' => $paid]);
