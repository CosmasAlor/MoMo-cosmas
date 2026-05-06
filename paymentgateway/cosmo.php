<?php
// system/paymentgateway/cosmo.php

function cosmo_show_config() {
    global $ui, $_L, $admin;
    
    $cosmo_api_url = getConfig('cosmo_api_url');
    $cosmo_api_key = getConfig('cosmo_api_key');
    $cosmo_currency = getConfig('cosmo_currency');
    $cosmo_environment = getConfig('cosmo_environment');

    $ui->assign('cosmo_api_url', $cosmo_api_url);
    $ui->assign('cosmo_api_key', $cosmo_api_key);
    $ui->assign('cosmo_currency', $cosmo_currency);
    $ui->assign('cosmo_environment', $cosmo_environment);
    $ui->display('cosmo.tpl');
}

function cosmo_save_config() {
    global $admin, $_L;
    
    $cosmo_api_url = _post('cosmo_api_url');
    $cosmo_api_key = _post('cosmo_api_key');
    $cosmo_currency = _post('cosmo_currency');
    $cosmo_environment = _post('cosmo_environment');

    $d = ORM::for_table('tbl_appconfig')->where('setting', 'cosmo_api_url')->find_one();
    if ($d) {
        $d->value = $cosmo_api_url;
        $d->save();
    } else {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'cosmo_api_url';
        $d->value = $cosmo_api_url;
        $d->save();
    }

    $d = ORM::for_table('tbl_appconfig')->where('setting', 'cosmo_api_key')->find_one();
    if ($d) {
        $d->value = $cosmo_api_key;
        $d->save();
    } else {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'cosmo_api_key';
        $d->value = $cosmo_api_key;
        $d->save();
    }

    $d = ORM::for_table('tbl_appconfig')->where('setting', 'cosmo_currency')->find_one();
    if ($d) {
        $d->value = $cosmo_currency;
        $d->save();
    } else {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'cosmo_currency';
        $d->value = $cosmo_currency;
        $d->save();
    }

    $d = ORM::for_table('tbl_appconfig')->where('setting', 'cosmo_environment')->find_one();
    if ($d) {
        $d->value = $cosmo_environment;
        $d->save();
    } else {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'cosmo_environment';
        $d->value = $cosmo_environment;
        $d->save();
    }

    _log('[' . $admin['username'] . ']: Cosmo ' . $_L['Settings_Saved_Successfully'], 'Admin', $admin['id']);
    r2(U . 'paymentgateway/cosmo', 's', $_L['Settings_Saved_Successfully']);
}

function cosmo_create_transaction($trx, $user) {
    global $config;
    
    $api_key = $config['cosmo_api_key'];
    $api_url = $config['cosmo_api_url'];
    $environment = $config['cosmo_environment'];
    $amount = $trx['price'];
    $currency = $config['cosmo_currency'];
    $phone = $user['phonenumber'];
    $invoice_id = $trx['id'];
    
    // 1. Get Access Token
    $token = cosmo_get_token($api_url, $api_key);
    if (!$token) {
        Message::sendTelegram("Cosmo: Failed to get token");
        r2(U . 'order/package', 'e', 'Payment gateway error. Please contact support.');
        return false;
    }
    
    // 2. Generate reference UUID
    $reference_id = cosmo_generate_uuid();
    
    // 3. Prepare request payload
    $payload = [
        'amount' => $amount,
        'currency' => $currency,
        'externalId' => $invoice_id,
        'payer' => [
            'partyIdType' => 'MSISDN',
            'partyId' => $phone
        ],
        'payerMessage' => "Payment for invoice #{$invoice_id}",
        'payeeNote' => "Thank you for your purchase"
    ];
    
    // 4. Send request to pay
    $headers = [
        "Authorization: Bearer {$token}",
        "X-Reference-Id: {$reference_id}",
        "X-Target-Environment: {$environment}",
        "Ocp-Apim-Subscription-Key: {$api_key}",
        "Content-Type: application/json"
    ];
    
    $url = rtrim($api_url, '/') . "/collection/v1_0/requesttopay";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, ($environment === 'production'));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 202) {
        // Transaction initiated successfully, redirect to a waiting page
        $callback_url = U . "order/view/{$invoice_id}/check?ref={$reference_id}";
        header("Location: {$callback_url}");
        exit;
    } else {
        Message::sendTelegram("Cosmo payment failed\n\n" . json_encode($response, JSON_PRETTY_PRINT));
        r2(U . 'order/package', 'e', 'Payment could not be processed. Please try again.');
        return false;
    }
}

function cosmo_get_token($api_url, $api_key) {
    $url = rtrim($api_url, '/') . "/collection/token/";
    $auth = base64_encode("{$api_key}:{$api_key}");
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Basic {$auth}",
        "Ocp-Apim-Subscription-Key: {$api_key}",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }
    return null;
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