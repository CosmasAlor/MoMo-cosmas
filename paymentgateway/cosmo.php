<?php
/**
 * Cosmo Mobile Money Payment Gateway
 * MTN MoMo API Integration
 */

/**
 * Display gateway configuration
 */
function cosmo_show_config() {
    global $ui, $_L, $admin, $config;

    $ui->assign('cosmo_api_url', $config['cosmo_api_url'] ?? 'https://sandbox.momodeveloper.mtn.com');
    $ui->assign('cosmo_subscription_key', $config['cosmo_subscription_key'] ?? '');
    $ui->assign('cosmo_x_reference_id', $config['cosmo_x_reference_id'] ?? '');
    $ui->assign('cosmo_api_secret', $config['cosmo_api_secret'] ?? '');
    $ui->assign('cosmo_currency', $config['cosmo_currency'] ?? 'XAF');
    $ui->assign('cosmo_environment', $config['cosmo_environment'] ?? 'sandbox');
    $ui->assign('cosmo_webhook_url', $config['cosmo_webhook_url'] ?? '');
    $ui->assign('cosmo_enable_logging', $config['cosmo_enable_logging'] ?? '0');
    
    $ui->display('cosmo.tpl');
}

/**
 * Save gateway configuration
 */
function cosmo_save_config() {
    global $admin, $_L;

    $cosmo_api_url = _post('cosmo_api_url');
    $cosmo_subscription_key = _post('cosmo_subscription_key');
    $cosmo_x_reference_id = _post('cosmo_x_reference_id');
    $cosmo_api_secret = _post('cosmo_api_secret');
    $cosmo_currency = _post('cosmo_currency');
    $cosmo_environment = _post('cosmo_environment');
    $cosmo_webhook_url = _post('cosmo_webhook_url');
    $cosmo_enable_logging = _post('cosmo_enable_logging') ? '1' : '0';

    cosmo_save_setting('cosmo_api_url', $cosmo_api_url);
    cosmo_save_setting('cosmo_subscription_key', $cosmo_subscription_key);
    cosmo_save_setting('cosmo_x_reference_id', $cosmo_x_reference_id);
    cosmo_save_setting('cosmo_api_secret', $cosmo_api_secret);
    cosmo_save_setting('cosmo_currency', $cosmo_currency);
    cosmo_save_setting('cosmo_environment', $cosmo_environment);
    cosmo_save_setting('cosmo_webhook_url', $cosmo_webhook_url);
    cosmo_save_setting('cosmo_enable_logging', $cosmo_enable_logging);

    _log('[' . $admin['username'] . ']: Cosmo ' . $_L['Settings_Saved_Successfully'], 'Admin', $admin['id']);
    r2(U . 'paymentgateway/cosmo', 's', $_L['Settings_Saved_Successfully']);
}

function cosmo_save_setting($setting, $value) {
    $d = ORM::for_table('tbl_appconfig')->where('setting', $setting)->find_one();
    if ($d) {
        $d->value = $value;
        $d->save();
    } else {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = $setting;
        $d->value = $value;
        $d->save();
    }
}

function cosmo_get_access_token($params) {
    $url = rtrim($params['api_base_url'], '/') . '/collection/token/';
    $credentials = base64_encode($params['x_reference_id'] . ':' . $params['api_secret']);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => '{}',
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . $credentials,
            'Ocp-Apim-Subscription-Key: ' . $params['subscription_key'],
            'Content-Type: application/json',
            'Cache-Control: no-cache'
        ],
        CURLOPT_SSL_VERIFYPEER => false, // Set to true in production
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if (isset($params['enable_logging']) && $params['enable_logging']) {
        cosmo_log_request('Get Access Token', $url, $httpCode, $response, $curlError);
    }
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        return $data['access_token'] ?? false;
    }
    
    return false;
}

function cosmo_format_phone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 3) === '237') {
        $phone = substr($phone, 3);
    }
    $phone = ltrim($phone, '0');
    if (strlen($phone) < 6 || strlen($phone) > 9) {
        return false;
    }
    return '237' . $phone;
}

function cosmo_generate_uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        random_int(0, 0xffff), random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0x0fff) | 0x4000,
        random_int(0, 0x3fff) | 0x8000,
        random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
    );
}

function cosmo_log_request($action, $url, $httpCode, $response, $error = '', $payload = null) {
    $log_dir = __DIR__ . '/../logs/';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . 'cosmo_' . date('Y-m-d') . '.log';
    
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => $action,
        'url' => $url,
        'http_code' => $httpCode,
        'response' => json_decode($response, true) ?? $response,
        'error' => $error
    ];
    
    file_put_contents($log_file, json_encode($log_entry, JSON_PRETTY_PRINT) . PHP_EOL . str_repeat('-', 80) . PHP_EOL, FILE_APPEND);
}

function cosmo_create_transaction($trx, $user) {
    global $config;

    $params = [
        'api_base_url' => $config['cosmo_api_url'] ?? 'https://sandbox.momodeveloper.mtn.com',
        'subscription_key' => $config['cosmo_subscription_key'] ?? '',
        'x_reference_id' => $config['cosmo_x_reference_id'] ?? '',
        'api_secret' => $config['cosmo_api_secret'] ?? '',
        'currency' => $config['cosmo_currency'] ?? 'XAF',
        'environment' => $config['cosmo_environment'] ?? 'sandbox',
        'enable_logging' => $config['cosmo_enable_logging'] ?? '0'
    ];
    
    if (empty($params['subscription_key']) || empty($params['x_reference_id']) || empty($params['api_secret'])) {
        Message::sendTelegram("Cosmo: Missing gateway configuration");
        r2(U . 'order/package', 'e', 'Payment gateway not properly configured. Please contact support.');
        return false;
    }
    
    $phone = cosmo_format_phone($user['phonenumber'] ?? '');
    if (!$phone) {
        Message::sendTelegram("Cosmo: Invalid phone number - {$user['phonenumber']}");
        r2(U . 'order/package', 'e', 'Invalid phone number format. Please use a valid mobile number.');
        return false;
    }
    
    $access_token = cosmo_get_access_token($params);
    if (!$access_token) {
        Message::sendTelegram("Cosmo: Failed to get access token");
        r2(U . 'order/package', 'e', 'Payment gateway temporarily unavailable. Please try again later.');
        return false;
    }
    
    $reference_id = cosmo_generate_uuid();
    $amount = number_format($trx['price'], 2, '.', '');
    $invoice_id = $trx['id'];
    
    $payload = [
        'amount' => $amount,
        'currency' => $params['currency'],
        'externalId' => (string)$invoice_id,
        'payer' => [
            'partyIdType' => 'MSISDN',
            'partyId' => $phone
        ],
        'payerMessage' => "Payment for Invoice #{$invoice_id}",
        'payeeNote' => "Thank you for your purchase"
    ];
    
    $url = rtrim($params['api_base_url'], '/') . '/collection/v1_0/requesttopay';
    $headers = [
        "Authorization: Bearer {$access_token}",
        "X-Reference-Id: {$reference_id}",
        "X-Target-Environment: {$params['environment']}",
        "Ocp-Apim-Subscription-Key: {$params['subscription_key']}",
        "Content-Type: application/json"
    ];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 60
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($params['enable_logging']) {
        cosmo_log_request('Request to Pay', $url, $http_code, $response, $curl_error, $payload);
    }
    
    if ($http_code == 202) {
        $callback_url = U . "order/view/{$invoice_id}/check?ref={$reference_id}";
        header("Location: {$callback_url}");
        exit;
    } elseif ($http_code == 400) {
        $error_data = json_decode($response, true);
        $error_message = $error_data['message'] ?? 'Invalid payment request';
        Message::sendTelegram("Cosmo: 400 Error - {$error_message}");
        r2(U . 'order/package', 'e', "Payment failed: {$error_message}");
    } elseif ($http_code == 403) {
        Message::sendTelegram("Cosmo: Authentication failed");
        r2(U . 'order/package', 'e', 'Payment gateway authentication failed. Please contact support.');
    } elseif ($http_code == 409) {
        r2(U . 'order/package', 'e', 'Duplicate transaction. Please check if payment was already processed.');
    } else {
        $error_msg = $curl_error ?: "HTTP {$http_code}";
        Message::sendTelegram("Cosmo: Payment failed - {$error_msg}");
        r2(U . 'order/package', 'e', 'Payment could not be processed. Please try again.');
    }
    
    return false;
}

// ============================================
// ENHANCED TEST CONNECTION HANDLER
// ============================================
if (isset($_POST['action']) && $_POST['action'] === 'test_connection') {
    header('Content-Type: application/json');
    
    $api_url = $_POST['api_url'] ?? '';
    $subscription_key = $_POST['subscription_key'] ?? '';
    $x_reference_id = $_POST['x_reference_id'] ?? '';
    $api_secret = $_POST['api_secret'] ?? '';
    $environment = $_POST['environment'] ?? 'sandbox';
    
    // Validate inputs
    if (empty($api_url) || empty($subscription_key) || empty($x_reference_id) || empty($api_secret)) {
        echo json_encode([
            'success' => false, 
            'status' => 'Disconnected',
            'message' => 'Missing required credentials',
            'tests' => []
        ]);
        exit;
    }
    
    $results = [
        'status' => 'Disconnected',
        'portal' => 'MTN MoMo',
        'last_tested' => date('n/j/Y, g:i:s A'),
        'success_rate' => '',
        'failures' => 0,
        'tests' => []
    ];
    
    // TEST 1: API Endpoint Reachability
    $start_time = microtime(true);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => rtrim($api_url, '/') . '/collection/token/',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY => true,
        CURLOPT_TIMEOUT => 10
    ]);
    curl_exec($ch);
    $reachable = curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 0;
    $response_time = round((microtime(true) - $start_time) * 1000);
    curl_close($ch);
    
    $results['tests']['endpoint'] = [
        'name' => 'API Endpoint',
        'status' => $reachable ? 'Pass' : 'Fail',
        'message' => $reachable ? "Reachable ({$response_time}ms)" : 'Cannot reach API endpoint',
        'response_time' => $response_time
    ];
    
    if (!$reachable) {
        $results['failures']++;
        echo json_encode($results);
        exit;
    }
    
    // TEST 2: Subscription Key Validation
    $url = rtrim($api_url, '/') . '/collection/token/';
    $credentials = base64_encode($x_reference_id . ':' . $api_secret);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => '{}',
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . $credentials,
            'Ocp-Apim-Subscription-Key: ' . $subscription_key,
            'Content-Type: application/json',
            'Cache-Control: no-cache'
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $start_time = microtime(true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $response_time = round((microtime(true) - $start_time) * 1000);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // TEST 2: Subscription Key
    $results['tests']['subscription'] = [
        'name' => 'Subscription Key',
        'status' => ($http_code !== 401 && $http_code !== 403) ? 'Pass' : 'Fail',
        'message' => $http_code === 200 ? 'Valid' : ($http_code === 401 ? 'Invalid/Expired' : "HTTP {$http_code}"),
        'response_time' => $response_time
    ];
    
    if ($http_code === 401 || $http_code === 403) {
        $results['failures']++;
        $results['status'] = 'Disconnected';
        echo json_encode($results);
        exit;
    }
    
    // TEST 3: API User (X-Reference-Id) Validation
    $results['tests']['api_user'] = [
        'name' => 'API User (X-Reference-Id)',
        'status' => ($http_code === 200) ? 'Pass' : 'Fail',
        'message' => ($http_code === 200) ? 'Valid API User' : 'Invalid or unlinked API User',
        'response_time' => $response_time
    ];
    
    if ($http_code !== 200) {
        $results['failures']++;
        $results['status'] = 'Disconnected';
        echo json_encode($results);
        exit;
    }
    
    // TEST 4: Access Token Generation (Final Test)
    $token_data = json_decode($response, true);
    $has_token = isset($token_data['access_token']);
    
    $results['tests']['token'] = [
        'name' => 'Access Token',
        'status' => $has_token ? 'Pass' : 'Fail',
        'message' => $has_token ? 'Token generated successfully' : 'Failed to generate token',
        'response_time' => $response_time
    ];
    
    if ($has_token) {
        $results['status'] = 'Connected';
        $results['success_rate'] = '100%';
        $results['failures'] = 0;
        $results['token_expires_in'] = $token_data['expires_in'] ?? 3600;
    } else {
        $results['failures']++;
        $results['status'] = 'Disconnected';
    }
    
    // Try to get account info (optional - for extra info)
    $results['tests']['account'] = [
        'name' => 'Account Verification',
        'status' => 'Info',
        'message' => 'API User: ' . substr($x_reference_id, 0, 8) . '...',
        'response_time' => 0
    ];
    
    echo json_encode($results);
    exit;
}
