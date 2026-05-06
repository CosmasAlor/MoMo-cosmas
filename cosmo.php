<?php
/**
 * Cosmo Mobile Money Gateway - Pure PHP
 * No external libraries, only cURL
 */

if (!defined('BASEPATH')) {
    die('No direct script access allowed');
}

// -------------------------------------------------------------------
// 1. Display configuration page (admin)
// -------------------------------------------------------------------
function cosmo_show_config()
{
    global $ui, $_L;

    $cosmo_api_url         = getConfig('cosmo_api_url');
    $cosmo_subscription_key = decrypt(getConfig('cosmo_subscription_key'));
    $cosmo_api_user        = decrypt(getConfig('cosmo_api_user'));
    $cosmo_api_key         = decrypt(getConfig('cosmo_api_key'));
    $cosmo_currency        = getConfig('cosmo_currency');
    $cosmo_environment     = getConfig('cosmo_environment');

    $ui->assign('cosmo_api_url', $cosmo_api_url);
    $ui->assign('cosmo_subscription_key', $cosmo_subscription_key);
    $ui->assign('cosmo_api_user', $cosmo_api_user);
    $ui->assign('cosmo_api_key', $cosmo_api_key);
    $ui->assign('cosmo_currency', $cosmo_currency);
    $ui->assign('cosmo_environment', $cosmo_environment);
    $ui->display('cosmo.tpl');
}

// -------------------------------------------------------------------
// 2. Save configuration settings
// -------------------------------------------------------------------
function cosmo_save_config()
{
    global $_POST;

    $cosmo_api_url           = $_POST['cosmo_api_url'];
    $cosmo_subscription_key  = encrypt($_POST['cosmo_subscription_key']);
    $cosmo_api_user          = encrypt($_POST['cosmo_api_user']);
    $cosmo_api_key           = encrypt($_POST['cosmo_api_key']);
    $cosmo_currency          = $_POST['cosmo_currency'];
    $cosmo_environment       = $_POST['cosmo_environment'];

    updateConfig('cosmo_api_url', $cosmo_api_url);
    updateConfig('cosmo_subscription_key', $cosmo_subscription_key);
    updateConfig('cosmo_api_user', $cosmo_api_user);
    updateConfig('cosmo_api_key', $cosmo_api_key);
    updateConfig('cosmo_currency', $cosmo_currency);
    updateConfig('cosmo_environment', $cosmo_environment);

    echo json_encode(['success' => true]);
}

// -------------------------------------------------------------------
// 3. Send payment – initiate request to pay
// -------------------------------------------------------------------
function cosmo_send_payment($transaction, $plans)
{
    $amount   = $transaction['price'];
    $currency = getConfig('cosmo_currency');
    $txn_id   = $transaction['invoice_id'];
    $phone    = $transaction['phone'];

    $api_url        = getConfig('cosmo_api_url');
    $subscription_key = decrypt(getConfig('cosmo_subscription_key'));
    $api_user       = decrypt(getConfig('cosmo_api_user'));
    $api_key        = decrypt(getConfig('cosmo_api_key'));
    $env            = getConfig('cosmo_environment');

    // 1. Get OAuth2 token
    $token = cosmo_get_token($api_url, $api_user, $api_key, $subscription_key);
    if (!$token) {
        error_log("Cosmo: Token acquisition failed");
        $transaction->setFailed();
        return false;
    }

    // 2. Generate reference UUID
    $reference_id = cosmo_generate_uuid();

    // 3. Prepare request payload
    $payload = [
        'amount'      => $amount,
        'currency'    => $currency,
        'externalId'  => $txn_id,
        'payer'       => [
            'partyIdType' => 'MSISDN',
            'partyId'     => $phone
        ],
        'payerMessage' => "Payment for invoice #{$txn_id}",
        'payeeNote'    => "Thank you for your purchase"
    ];

    // 4. Send request to pay
    $headers = [
        "Authorization: Bearer {$token}",
        "X-Reference-Id: {$reference_id}",
        "X-Target-Environment: {$env}",
        "Ocp-Apim-Subscription-Key: {$subscription_key}",
        "Content-Type: application/json"
    ];

    $url = rtrim($api_url, '/') . "/collection/v1_0/requesttopay";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, ($env === 'production'));

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 202) {
        $transaction->setPending($reference_id);
        $_SESSION['cosmo_ref_id'] = $reference_id;
        $_SESSION['cosmo_txn_id'] = $txn_id;
        header("Location: " . base_url() . "?cosmo_pending=1");
        exit;
    } else {
        error_log("Cosmo request error: $response");
        $transaction->setFailed();
        return false;
    }
}

// Helper: Get OAuth2 token
function cosmo_get_token($api_url, $api_user, $api_key, $subscription_key)
{
    $url = rtrim($api_url, '/') . "/collection/token/";
    $auth = base64_encode("{$api_user}:{$api_key}");

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Basic {$auth}",
        "Ocp-Apim-Subscription-Key: {$subscription_key}",
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

// Helper: Generate UUID v4
function cosmo_generate_uuid()
{
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        random_int(0, 0xffff), random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0x0fff) | 0x4000,
        random_int(0, 0x3fff) | 0x8000,
        random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
    );
}

// -------------------------------------------------------------------
// 4. Callback handler (webhook) – add via hook
// -------------------------------------------------------------------
add_hook('pre_routing', function() {
    if (isset($_GET['cosmo_callback'])) {
        $ref_id = $_GET['ref'] ?? '';
        if (!$ref_id) {
            http_response_code(400);
            exit('Missing reference ID');
        }

        $txn_id = $_SESSION['cosmo_txn_id'] ?? '';

        $api_url = getConfig('cosmo_api_url');
        $subscription_key = decrypt(getConfig('cosmo_subscription_key'));
        $api_user = decrypt(getConfig('cosmo_api_user'));
        $api_key = decrypt(getConfig('cosmo_api_key'));
        $env = getConfig('cosmo_environment');

        $token = cosmo_get_token($api_url, $api_user, $api_key, $subscription_key);
        if (!$token) {
            http_response_code(500);
            exit('Token error');
        }

        $status_url = rtrim($api_url, '/') . "/collection/v1_0/requesttopay/{$ref_id}";
        $ch = curl_init($status_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$token}",
            "Ocp-Apim-Subscription-Key: {$subscription_key}",
            "X-Target-Environment: {$env}"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, ($env === 'production'));
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200) {
            $data = json_decode($response, true);
            if ($data['status'] == 'SUCCESSFUL') {
                $transaction = Transaction::where('invoice_id', $txn_id)->first();
                if ($transaction && $transaction->status != 1) {
                    $transaction->setSuccess();
                }
            } else {
                $transaction = Transaction::where('invoice_id', $txn_id)->first();
                if ($transaction) $transaction->setFailed();
            }
            unset($_SESSION['cosmo_ref_id'], $_SESSION['cosmo_txn_id']);
        }
        http_response_code(200);
        exit('OK');
    }

    if (isset($_GET['cosmo_pending'])) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Payment Pending</title>
            <meta http-equiv="refresh" content="5;url=<?php echo base_url(); ?>payment/status/<?php echo $_SESSION['cosmo_txn_id'] ?? ''; ?>">
        </head>
        <body>
            <h3>Waiting for payment confirmation...</h3>
            <p>Please check your mobile money app and authorize the payment.</p>
        </body>
        </html>
        <?php
        exit;
    }
});
?>