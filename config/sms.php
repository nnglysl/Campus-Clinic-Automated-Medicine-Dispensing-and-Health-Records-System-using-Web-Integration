<?php
function sendSms($phoneNumber, $message) {
<<<<<<< HEAD
  if (!isset($_ENV['SMS_API_KEY'])) {
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
      require_once __DIR__ . '/../vendor/autoload.php';
      $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
      $dotenv->load();
    }
  }
  
  $url = $_ENV['SMS_API_URL'] ?? 'https://sms.skyio.site/api/sms/send';
  $apiKey = $_ENV['SMS_API_KEY'] ?? 'b86a19tKUApQ2jd0HIvMPI85MnzGpDeQoNqHh0qZZoNJSSPDgnB4b1l1CBkkRBET';
  
  if (empty($apiKey) || $apiKey === 'YOUR_SMS_API_KEY_HERE') {
    error_log("SMS API Key not configured. Please set SMS_API_KEY in your .env file.");
    return ['success' => false, 'error' => 'SMS API key not configured'];
  }
  
=======
  $url = 'https://sms.skyio.site/api/sms/send';
  $apiKey = 'Ls9HTrWtoOcN2cCCFEavCUfKER8bXty8P97XML0lfQxi2z89SZf8cwEqdatRcLjg'; // Replace with your actual API key
  
  // Format phone number (remove +63, keep 09 format or add +63)
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
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
  
<<<<<<< HEAD
=======
  // Log for debugging (remove in production)
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
  error_log("SMS API Response: " . $response);
  error_log("SMS API HTTP Code: " . $httpCode);
  
  if ($err) {
    return ['success' => false, 'error' => $err];
  }
  
  $result = json_decode($response, true);
  
<<<<<<< HEAD
=======
  // Check for successful response
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
  if ($httpCode == 200 || $httpCode == 201) {
    return ['success' => true, 'response' => $result];
  }
  
  return ['success' => false, 'error' => $response, 'http_code' => $httpCode];
   error_log("SMS to $phone: $message");
    return true;
}
?>