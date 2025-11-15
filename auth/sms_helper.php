<?php
/**
 * SMS Helper Function for Skycode API
 * Sends SMS messages using the Skycode SMS gateway
 * 
 * @param string $phoneNumber The recipient's phone number (09XXXXXXXXX or +639XXXXXXXXX format)
 * @param string $message The message to send
 * @return array ['success' => bool, 'response' => array|null, 'error' => string|null]
 */
function sendSms($phoneNumber, $message) {
    $url = 'https://sms.skyio.site/api/sms/send';
    $apiKey = 'Ls9HTrWtoOcN2cCCFEavCUfKER8bXty8P97XML0lfQxi2z89SZf8cwEqdatRcLjg';
    
    // Format phone number to +63 format
    $phoneNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);
    if (strpos($phoneNumber, '09') === 0) {
        $phoneNumber = '+63' . substr($phoneNumber, 1);
    } elseif (strpos($phoneNumber, '63') === 0 && strpos($phoneNumber, '+') !== 0) {
        $phoneNumber = '+' . $phoneNumber;
    }
    
    // Validate phone number format
    if (!preg_match('/^\+639\d{9}$/', $phoneNumber)) {
        return [
            'success' => false,
            'error' => 'Invalid phone number format. Must be +639XXXXXXXXX'
        ];
    }
    
    // Prepare request data
    $data = [
        'to' => $phoneNumber,
        'message' => $message
    ];
    
    // Initialize cURL
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    // Log for debugging (remove in production or use proper logging)
    error_log("SMS API Request to: $phoneNumber");
    error_log("SMS API Response Code: $httpCode");
    error_log("SMS API Response Body: " . $response);
    
    // Handle cURL errors
    if ($err) {
        error_log("SMS API cURL Error: " . $err);
        return [
            'success' => false,
            'error' => 'Connection error: ' . $err
        ];
    }
    
    // Parse response
    $result = json_decode($response, true);
    
    // Check for successful response (200 or 201)
    if ($httpCode == 200 || $httpCode == 201) {
        return [
            'success' => true,
            'response' => $result
        ];
    }
    
    // Handle error responses
    $errorMessage = 'Unknown error occurred';
    if (is_array($result) && isset($result['message'])) {
        $errorMessage = $result['message'];
    } elseif (is_array($result) && isset($result['error'])) {
        $errorMessage = $result['error'];
    }
    
    return [
        'success' => false,
        'error' => $errorMessage,
        'http_code' => $httpCode,
        'raw_response' => $response
    ];
}

/**
 * Validate Philippine phone number format
 * 
 * @param string $phone Phone number to validate
 * @return bool True if valid, false otherwise
 */
function validatePhoneNumber($phone) {
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Accept formats: 09XXXXXXXXX or +639XXXXXXXXX
    return preg_match('/^(09|\+639)\d{9}$/', $phone);
}

/**
 * Normalize phone number to +63 format
 * 
 * @param string $phone Phone number to normalize
 * @return string|null Normalized phone number or null if invalid
 */
function normalizePhoneNumber($phone) {
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    if (strpos($phone, '09') === 0) {
        return '+63' . substr($phone, 1);
    } elseif (strpos($phone, '+639') === 0) {
        return $phone;
    } elseif (strpos($phone, '639') === 0) {
        return '+' . $phone;
    }
    
    return null;
}
?>