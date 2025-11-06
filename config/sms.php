<?php
function sendSms($phoneNumber, $message) {
  $url = 'https://sms.skyio.site/api/sms/send';
  $apiKey = 'Ls9HTrWtoOcN2cCCFEavCUfKER8bXty8P97XML0lfQxi2z89SZf8cwEqdatRcLjg'; // Replace with your actual API key
  
  // Format phone number (remove +63, keep 09 format or add +63)
  $phoneNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);
  if (strpos($phoneNumber, '09') === 0) {
    $phoneNumber = '+63' . substr($phoneNumber, 1);
  }
  
  $data = [
    'to' => $phoneNumber,
    'message' => $message
  ];
  
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
  
  // Log for debugging (remove in production)
  error_log("SMS API Response: " . $response);
  error_log("SMS API HTTP Code: " . $httpCode);
  
  if ($err) {
    return ['success' => false, 'error' => $err];
  }
  
  $result = json_decode($response, true);
  
  // Check for successful response
  if ($httpCode == 200 || $httpCode == 201) {
    return ['success' => true, 'response' => $result];
  }
  
  return ['success' => false, 'error' => $response, 'http_code' => $httpCode];
   error_log("SMS to $phone: $message");
    return true;
}
?>