<?php
require_once __DIR__ . '/SeamlessClient.php';
header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    http_response_code(400);
    echo json_encode(['status' => false, 'error_message' => 'Invalid JSON']);
    exit;
}

$action = $body['action'] ?? '';
$mock = $body['mock'] ?? false;

$client = new SeamlessClient([
    'create_key' => '$2y$10$dummyMerchantKeyCreate1234567890abcd',
    'validate_key' => '$2y$10$dummyMerchantKeyValidate0987654321xyz',
    'mock' => $mock
]);

try {
    if ($action === 'create_transaction') {
        $amount = floatval($body['amount'] ?? 0);
        $invoice_id = $body['invoice_id'] ?? time();
        if (!$amount || $amount <= 0) {
            http_response_code(400);
            throw new Exception('Invalid amount');
        }
        $resp = $client->createTransaction($amount, $invoice_id);
        if (!$resp['status']) {
            http_response_code($resp['http_code'] ?? 400);
        }
        echo json_encode($resp);
        exit;
    }

    if ($action === 'get_deposit') {
        $token = $body['token'] ?? '';
        if (!$token) {
            http_response_code(400);
            throw new Exception('Missing token');
        }
        $resp = $client->getDepositDetails($token);
        if (!$resp['status']) {
            http_response_code($resp['http_code'] ?? 400);
        }
        echo json_encode($resp);
        exit;
    }

    if ($action === 'validate') {
        $token = $body['token'] ?? '';
        if (!$token) {
            http_response_code(400);
            throw new Exception('Missing token');
        }
        $resp = $client->validateTransaction($token);
        if (!$resp['status']) {
            http_response_code($resp['http_code'] ?? 400);
        }
        echo json_encode($resp);
        exit;
    }

    http_response_code(400);
    throw new Exception('Unknown action');
} catch (Exception $e) {
    echo json_encode(['status' => false, 'error_message' => $e->getMessage()]);
    exit;
}
?>