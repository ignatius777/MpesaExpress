<?php

// Log access immediately
file_put_contents(__DIR__ . '/access_check.log', date('Y-m-d H:i:s') . " - File hit.\n", FILE_APPEND);

// Process callback before sending any output
error_log("Request Headers: " . json_encode(getallheaders()));
getStkPushResult();

// Send HTTP 200 response
header("Content-Type: application/json");
http_response_code(200);
echo json_encode(["ResultCode" => 0, "ResultDesc" => "Callback received successfully."]);

// Callback function
function getStkPushResult() {
    $timestamp = date('Y-m-d H:i:s');
    $rawContent = file_get_contents("php://input");

    $logFile = __DIR__ . "/mpesa_error.log";
    $rawLog = __DIR__ . "/daraja_raw.log";

    // Log raw input from Daraja
    file_put_contents($rawLog, "[$timestamp] $rawContent" . PHP_EOL, FILE_APPEND);

    if ($rawContent === false || empty(trim($rawContent))) {
        error_log("[$timestamp] Empty or unreadable request body." . PHP_EOL, 3, $logFile);
        return;
    }

    $content = json_decode($rawContent);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("[$timestamp] JSON error: " . json_last_error_msg() . PHP_EOL, 3, $logFile);
        return;
    }

    $token = null;
    if (isset($_SERVER['REQUEST_URI'])) {
        $parsedUrl = parse_url($_SERVER['REQUEST_URI']);
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $query);
            $token = $query['token'] ?? null;
        }
    }

    // Log token
    error_log("[$timestamp] Token: " . ($token ?? 'None') . PHP_EOL, 3, $logFile);

    // DB setup
    $host = '';
    $username = '';
    $password = '';
    $dbname = '';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    } catch (PDOException $e) {
        error_log("[$timestamp] DB connection error: " . $e->getMessage() . PHP_EOL, 3, $logFile);
        return;
    }

    if (isset($content->Body->stkCallback->ResultCode)) {
        $callback = $content->Body->stkCallback;
        $resultCode = $callback->ResultCode;
        
        // Extract metadata
        $metadata = $callback->CallbackMetadata->Item ?? [];
        $data = [];
        foreach ($metadata as $item) {
            $data[$item->Name] = $item->Value ?? null;
        }

        // Prepare DB insert
        $stmt = $pdo->prepare("
            INSERT INTO mpesa_resp 
            (MerchantRequestID, CheckoutRequestID, ResultCode, ResultDesc, Amount, MpesaReceiptNumber, TransactionDate, PhoneNumber, Token, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        // Insert data (successful payment or otherwise)
        $stmt->execute([
            $callback->MerchantRequestID ?? '',
            $callback->CheckoutRequestID ?? '',
            $resultCode,
            $callback->ResultDesc ?? '',
            $data['Amount'] ?? 0,
            $data['MpesaReceiptNumber'] ?? '',
            $data['TransactionDate'] ?? '',
            $data['PhoneNumber'] ?? '',
            $token,
            $timestamp,
            $timestamp
        ]);

        error_log("[$timestamp] DB saved: CheckoutRequestID = " . ($callback->CheckoutRequestID ?? 'N/A') . PHP_EOL, 3, $logFile);
    } else {
        error_log("[$timestamp] No ResultCode in callback" . PHP_EOL, 3, $logFile);
    }
}
