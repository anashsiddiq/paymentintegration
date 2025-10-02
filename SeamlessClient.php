<?php
class SeamlessClient {
    private $create_key;
    private $validate_key;
    private $mock;

    public function __construct($opts = []) {
        $this->create_key = $opts['create_key'] ?? '';
        $this->validate_key = $opts['validate_key'] ?? $this->create_key;
        $this->mock = $opts['mock'] ?? false;
    }

    public function createTransaction($amount, $invoice_id) {
        if ($this->mock) {
            return $this->mockCreate($amount, $invoice_id);
        }
        $payload = [
            'merchant_key' => $this->create_key,
            'invoice' => [
                'items' => [['name' => 'Deposit', 'price' => (int)$amount, 'description' => 'deposit', 'qty' => 1]],
                'invoice_id' => (string)$invoice_id,
                'invoice_description' => 'Deposit',
                'total' => (int)$amount
            ],
            'currency_code' => 'INR',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
            'user_id' => 'demoUser',
            'last_three_transactions' => [
                ['amount' => 100, 'utr' => 'UTR1234567890'],
                ['amount' => 99, 'utr' => 'UTR1234567891'],
                ['amount' => 98, 'utr' => 'UTR1234567892']
            ]
        ];
        return $this->postJson('https://sandboxtest.space/en/purchase/create-transaction', $payload);
    }

    public function getDepositDetails($token) {
        if ($this->mock) {
            return $this->mockDeposit($token);
        }
        $payload = ['merchant_key' => $this->create_key, 'token' => $token, 'type' => 'upi'];
        return $this->postJson('https://sandboxtest.space/en/purchase/get-deposit-details', $payload);
    }

    public function validateTransaction($token) {
        if ($this->mock) {
            return $this->mockValidate($token);
        }
        $payload = ['token' => $token, 'merchant_key' => $this->validate_key];
        return $this->postJson('https://sandboxtest.space/api/v1/validate-transaction', $payload);
    }

    private function postJson($url, $data) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, true);
        $resp = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($resp, $header_size);
        $parsed = json_decode($body, true);
        if ($parsed === null) {
            return ['status' => false, 'error_message' => 'Invalid JSON', 'raw' => $body, 'http_code' => $http_code];
        }
        $parsed['http_code'] = $http_code;
        return $parsed;
    }

    private function mockCreate($amount, $invoice_id) {
        return [
            'status' => true,
            'success_message' => 'Transaction created (mock)',
            'data' => ['token' => 'mock_' . uniqid(), 'amount' => $amount, 'invoice_id' => $invoice_id],
            'http_code' => 200
        ];
    }

    private function mockDeposit($token) {
        $amount = 10;
        $link = 'upi://pay?pa=merchant@upi&pn=DemoMerchant&am=' . $amount;
        $png = file_get_contents('https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($link));
        $base64 = 'data:image/png;base64,' . base64_encode($png);
        return [
            'status' => true,
            'success_message' => 'Deposit details fetched (mock)',
            'data' => [
                'qr' => $base64,
                'link' => $link,
                'amount' => $amount,
                'links' => [],
                'messages' => []
            ],
            'http_code' => 200
        ];
    }

    private function mockValidate($token) {
        return [
            'status' => true,
            'success_message' => 'Transaction status fetched (mock)',
            'transaction_status' => 'Pending',
            'txn_details' => ['bank_txn_ref' => ''],
            'http_code' => 200
        ];
    }
}
?>