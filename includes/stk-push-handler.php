<?php
class STK_Push_Handler {

    public static function get_access_token($consumerKey, $consumerSecret) {
        $credentials = base64_encode($consumerKey . ':' . $consumerSecret);
        $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $credentials,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false, // Set to true in production
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            error_log('Curl Error while fetching access token: ' . $error);
            return false;
        }

        $data = json_decode($response, true);
        if (isset($data['access_token'])) {
            error_log('Access token retrieved successfully.');
            return $data['access_token'];
        } else {
            error_log('Failed to retrieve access token. Response: ' . $response);
            return false;
        }
    }

    public static function initiate_stk_push($order_id, $phone, $amount, $config = []) {
        $access_token = self::get_access_token($config['consumer_key'], $config['consumer_secret']);
        if (!$access_token) {
            return [
                'ResponseCode' => '1',
                'ResponseDesc' => 'Failed to retrieve access token'
            ];
        }

        $url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
        $timestamp = date("YmdHis");
        $password = base64_encode($config['shortcode'] . $config['passkey'] . $timestamp);

        // Hard-code the callback URL here
        $callback_url = '';

        $payload = json_encode([
            'BusinessShortCode' => $config['shortcode'],
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phone,
            'PartyB' => $config['shortcode'],
            'PhoneNumber' => $phone,
            'CallBackURL' => $callback_url, // Use the hardcoded callback URL
            'AccountReference' => 'Order ' . $order_id,
            'TransactionDesc' => 'Payment for order ' . $order_id,
        ]);

        $headers = [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false // Set to true in production
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response) {
            $data = json_decode($response, true);
            error_log('STK Push response data: ' . print_r($data, true));

            if (isset($data['ResponseCode']) && $data['ResponseCode'] == '0') {
                error_log('STK Push initiated successfully');
                return $data;
            } else {
                error_log('STK Push failed with ResponseCode: ' . $data['ResponseCode']);
                return [
                    'ResponseCode' => '1',
                    'ResponseDesc' => $data['errorMessage'] ?? $data['ResponseDescription'] ?? 'Unknown error'
                ];
            }
        } else {
            error_log('Curl error during STK Push: ' . $error);
            return [
                'ResponseCode' => '1',
                'ResponseDesc' => 'Curl error initiating STK Push'
            ];
        }
    }
}

