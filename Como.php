<?php
/**
 * Mobile Money (MTN / Airtel) Gateway - Pure PHP
 * No external libraries, only cURL
 */

if (!defined('BASEPATH')) {
    die('No direct script access allowed');
}

// -------------------------------------------------------------------
// 1. Display configuration page (admin)
// -------------------------------------------------------------------
function momo_show_config()
{
    global $ui, $_L;

    $momo_api_url         = getConfig('momo_api_url');
    $momo_subscription_key = decrypt(getConfig('momo_subscription_key'));
    $momo_api_user        = decrypt(getConfig('momo_api_user'));
    $momo_api_key         = decrypt(getConfig('momo_api_key'));
    $momo_currency        = getConfig('momo_currency');
    $momo_environment     = getConfig('momo_environment'); // sandbox or production

    $ui->assign('momo_api_url', $momo_api_url);
    $ui->assign('momo_subscription_key', $momo_subscription_key);
    $ui->assign('momo_api_user', $momo_api_user);
    $ui->assign('momo_api_key', $momo_api_key);
    $ui->assign('momo_currency', $momo_currency);
    $ui->assign('momo_environment', $momo_environment);
    $ui->display('momo.tpl');
}

// -------------------------------------------------------------------
// 2. Save configuration settings
// -------------------------------------------------------------------
function momo_save_config()
{
    global $_POST;

    $momo_api_url           = $_POST['momo_api_url'];
    $momo_subscription_key  = encrypt($_POST['momo_subscription_key']);
    $momo_api_user          = encrypt($_POST['momo_api_user']);
    $momo_api_key           = encrypt($_POST['momo_api_key']);
    $momo_currency          = $_POST['momo_currency'];
    $momo_environment       = $_POST['momo_environment'];

    updateConfig('momo_api_url', $momo_api_url);
    updateConfig('momo_subscription_key', $momo_subscription_key);
    updateConfig('momo_api_user', $momo_api_user);
    updateConfig('momo_api_key', $momo_api_key);
    updateConfig('momo_currency', $momo_currency);
    updateConfig('momo_environment', $momo_environment);

    echo json_encode(['success' => true]);
}

// -------------------------------------------------------------------
// 3. Send payment – initiate request to pay
// -------------------------------------------------------------------
function momo_send_payment($transaction, $plans)
{
    $amount   = $transaction['price'];
    $currency = getConfig('momo_currency');
    $txn_id   = $transaction['invoice_id'];
    $phone    = $transaction['phone'];

    // API config
    $api_url        = getConfig('momo_api_url');
    $subscription_key = decrypt(getConfig('momo_subscription_key'));
    $api_user       = decrypt(getConfig('momo_api_user'));
    $api_key        = decrypt(getConfig('momo_api_key'));
    $env            = getConfig('momo_environment');

    // 1. Get OAuth2 token
    $token = momo_get_token($api_url, $api_user, $api_key, $subscription_key);
    if (!$token) {
        error_log("MoMo: Token acquisition failed");
        $transaction->setFailed();
        return false;
    }

    // 2. Generate reference UUID
    $reference_id = momo_generate_uuid();

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
        // Request accepted – store reference_id and redirect to pending page
        $transaction->setPending($reference_id);
        // Save reference_id in session or database for callback
        $_SESSION['momo_ref_id'] = $reference_id;
        $_SESSION['momo_txn_id'] = $txn_id;
        header("Location: " . base_url() . "?momo_pending=1");
        exit;
    } else {
        error_log("MoMo request error: $response");
        $transaction->setFailed();
        return false;
    }
}

// -------------------------------------------------------------------
// Helper: Get OAuth2 token using cURL
// -------------------------------------------------------------------
function momo_get_token($api_url, $api_user, $api_key, $subscription_key)
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // sandbox only – adjust for production

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }
    return null;
}

// -------------------------------------------------------------------
// Helper: Generate UUID v4 (RFC 4122)
// -------------------------------------------------------------------
function momo_generate_uuid()
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
    if (isset($_GET['momo_callback'])) {
        // This endpoint is called by MoMo API (webhook)
        $ref_id = $_GET['ref'] ?? '';
        if (!$ref_id) {
            http_response_code(400);
            exit('Missing reference ID');
        }

        // Retrieve transaction details from your storage (session or DB)
        // For simplicity, we'll fetch from session; better to store in DB.
        $txn_id = $_SESSION['momo_txn_id'] ?? '';

        // Verify payment status
        $api_url = getConfig('momo_api_url');
        $subscription_key = decrypt(getConfig('momo_subscription_key'));
        $api_user = decrypt(getConfig('momo_api_user'));
        $api_key = decrypt(getConfig('momo_api_key'));
        $env = getConfig('momo_environment');

        $token = momo_get_token($api_url, $api_user, $api_key, $subscription_key);
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
                    // Optionally send success email/sms
                }
            } else {
                $transaction = Transaction::where('invoice_id', $txn_id)->first();
                if ($transaction) $transaction->setFailed();
            }
            unset($_SESSION['momo_ref_id'], $_SESSION['momo_txn_id']);
        }
        http_response_code(200);
        exit('OK');
    }

    // Pending page – show waiting message and poll status
    if (isset($_GET['momo_pending'])) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Payment Pending</title>
            <meta http-equiv="refresh" content="5;url=<?php echo base_url(); ?>payment/status/<?php echo $_SESSION['momo_txn_id'] ?? ''; ?>">
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

// Also provide a manual status check endpoint (optional)
add_hook('pre_routing', function() {
    if (isset($_GET['momo_check_status']) && isset($_GET['ref'])) {
        $ref_id = $_GET['ref'];
        $api_url = getConfig('momo_api_url');
        $subscription_key = decrypt(getConfig('momo_subscription_key'));
        $api_user = decrypt(getConfig('momo_api_user'));
        $api_key = decrypt(getConfig('momo_api_key'));
        $env = getConfig('momo_environment');

        $token = momo_get_token($api_url, $api_user, $api_key, $subscription_key);
        if (!$token) {
            echo json_encode(['status' => 'error', 'message' => 'Token failed']);
            exit;
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
            echo json_encode(['status' => $data['status']]);
        } else {
            echo json_encode(['status' => 'error']);
        }
        exit;
    }
});
?>