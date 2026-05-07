{include file="sections/header.tpl"}

<div class="panel panel-primary panel-collapsible">
    <div class="panel-heading" data-toggle="collapse" href="#collapse-cosmo">
        <h4 class="panel-title">Cosmo Mobile Money Gateway - MTN MoMo Integration</h4>
    </div>
    <div id="collapse-cosmo" class="panel-body collapse in">
        
        <!-- Alert Info -->
        <div class="alert alert-info">
            <i class="fa fa-info-circle"></i> 
            <strong>Setup Instructions:</strong>
            <ol style="margin-bottom:0; margin-top:5px;">
                <li>Get your credentials from <a href="https://momodeveloper.mtn.com" target="_blank">MTN Developer Portal</a></li>
                <li>Subscribe to the Collection product to get your <strong>Subscription Key</strong></li>
                <li>Create an API User and get your <strong>X-Reference-Id</strong> and <strong>API Secret</strong></li>
                <li>Enter all three credentials below and save</li>
            </ol>
        </div>
        
        <form id="cosmo_config_form" class="form-horizontal" method="post" role="form">
            <input type="hidden" name="action" value="save">
            
            <!-- API Base URL -->
            <div class="form-group">
                <label class="col-md-2 control-label">API Base URL <span class="text-danger">*</span></label>
                <div class="col-md-6">
                    <input type="url" class="form-control" name="cosmo_api_url" 
                           value="{$cosmo_api_url}" 
                           placeholder="https://sandbox.momodeveloper.mtn.com" required>
                </div>
                <div class="col-md-4">
                    <span class="help-block">
                        Sandbox: https://sandbox.momodeveloper.mtn.com<br>
                        Production: https://momodeveloper.mtn.com
                    </span>
                </div>
            </div>
            
            <!-- Subscription Key -->
            <div class="form-group">
                <label class="col-md-2 control-label">Subscription Key <span class="text-danger">*</span></label>
                <div class="col-md-6">
                    <input type="password" class="form-control" name="cosmo_subscription_key" 
                           value="{$cosmo_subscription_key}" 
                           placeholder="Your Ocp-Apim-Subscription-Key" required>
                </div>
                <div class="col-md-4">
                    <span class="help-block">Primary subscription key from MTN Developer Portal</span>
                </div>
            </div>
            
            <!-- X-Reference-Id (API User ID) -->
            <div class="form-group">
                <label class="col-md-2 control-label">API User ID <span class="text-danger">*</span></label>
                <div class="col-md-6">
                    <input type="text" class="form-control" name="cosmo_x_reference_id" 
                           value="{$cosmo_x_reference_id}" 
                           placeholder="XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX" required>
                </div>
                <div class="col-md-4">
                    <span class="help-block">X-Reference-Id (UUID from API user creation)</span>
                </div>
            </div>
            
            <!-- API Secret -->
            <div class="form-group">
                <label class="col-md-2 control-label">API Secret <span class="text-danger">*</span></label>
                <div class="col-md-6">
                    <input type="password" class="form-control" name="cosmo_api_secret" 
                           value="{$cosmo_api_secret}" 
                           placeholder="Your API secret key" required>
                </div>
                <div class="col-md-4">
                    <span class="help-block">Secret key generated for your API user</span>
                </div>
            </div>
            
            <!-- Currency -->
            <div class="form-group">
                <label class="col-md-2 control-label">Currency <span class="text-danger">*</span></label>
                <div class="col-md-6">
                    <select class="form-control" name="cosmo_currency" required>
                        <option value="XAF" {if $cosmo_currency == 'XAF'}selected{/if}>XAF (Central African CFA Franc)</option>
                        <option value="XOF" {if $cosmo_currency == 'XOF'}selected{/if}>XOF (West African CFA Franc)</option>
                        <option value="USD" {if $cosmo_currency == 'USD'}selected{/if}>USD (US Dollar)</option>
                        <option value="EUR" {if $cosmo_currency == 'EUR'}selected{/if}>EUR (Euro)</option>
                        <option value="NGN" {if $cosmo_currency == 'NGN'}selected{/if}>NGN (Nigerian Naira)</option>
                        <option value="GHS" {if $cosmo_currency == 'GHS'}selected{/if}>GHS (Ghanaian Cedi)</option>
                        <option value="UGX" {if $cosmo_currency == 'UGX'}selected{/if}>UGX (Ugandan Shilling)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <span class="help-block">Select your transaction currency</span>
                </div>
            </div>
            
            <!-- Environment -->
            <div class="form-group">
                <label class="col-md-2 control-label">Environment <span class="text-danger">*</span></label>
                <div class="col-md-6">
                    <select class="form-control" name="cosmo_environment">
                        <option value="sandbox" {if $cosmo_environment == 'sandbox'}selected{/if}>Sandbox (Test)</option>
                        <option value="production" {if $cosmo_environment == 'production'}selected{/if}>Production (Live)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <span class="help-block">Use Sandbox for testing, Production for live transactions</span>
                </div>
            </div>
            
            <!-- Webhook URL (Optional) -->
            <div class="form-group">
                <label class="col-md-2 control-label">Webhook URL</label>
                <div class="col-md-6">
                    <input type="url" class="form-control" name="cosmo_webhook_url" 
                           value="{$cosmo_webhook_url}" 
                           placeholder="https://yourdomain.com/webhook/cosmo">
                </div>
                <div class="col-md-4">
                    <span class="help-block">URL for payment notifications (optional)</span>
                </div>
            </div>
            
            <!-- Debug Logging -->
            <div class="form-group">
                <label class="col-md-2 control-label">Debug Logging</label>
                <div class="col-md-6">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="cosmo_enable_logging" value="1" {if $cosmo_enable_logging == '1'}checked{/if}>
                            Enable detailed logging for troubleshooting
                        </label>
                    </div>
                </div>
                <div class="col-md-4">
                    <span class="help-block">Logs are stored in /logs/cosmo_*.log</span>
                </div>
            </div>
            
            <!-- Buttons -->
            <div class="form-group">
                <div class="col-lg-offset-2 col-lg-10">
                    <button class="btn btn-success" type="submit" name="save" id="save">
                        <i class="fa fa-save"></i> {$_L['Save']}
                    </button>
                    <button class="btn btn-info" type="button" id="test_connection">
                        <i class="fa fa-vial"></i> Test Connection
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Connection Status Panel -->
<div class="panel panel-default" id="connection-status-panel" style="display: none; margin-top: 20px;">
    <div class="panel-heading">
        <h4 class="panel-title">Connection Status</h4>
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-bordered">
                    <tr>
                        <td width="40%"><strong>Status</strong></td>
                        <td><span id="status-badge" class="label label-default">--</span></td>
                    </tr>
                    <tr>
                        <td><strong>Portal</strong></td>
                        <td id="portal-info">--</td>
                    </tr>
                    <tr>
                        <td><strong>Last Tested</strong></td>
                        <td id="last-tested">--</td>
                    </tr>
                    <tr>
                        <td><strong>Success Rate</strong></td>
                        <td id="success-rate">--</td>
                    </tr>
                    <tr>
                        <td><strong>Failures (Recent)</strong></td>
                        <td id="failures-count">--</td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h5 class="panel-title">Test Results</h5>
                    </div>
                    <div class="panel-body" id="test-results" style="max-height: 250px; overflow-y: auto;">
                        <!-- Test results will appear here -->
                    </div>
                </div>
                <button class="btn btn-sm btn-default" onclick="viewTransactionLogs()">
                    <i class="fa fa-history"></i> View Transaction Logs
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function viewTransactionLogs() {
    var today = new Date().toISOString().slice(0,10);
    window.open('/system/logs/cosmo_' + today + '.log', '_blank');
}

$(document).ready(function() {
    $('#test_connection').click(function() {
        var api_url = $('input[name="cosmo_api_url"]').val();
        var subscription_key = $('input[name="cosmo_subscription_key"]').val();
        var x_reference_id = $('input[name="cosmo_x_reference_id"]').val();
        var api_secret = $('input[name="cosmo_api_secret"]').val();
        var environment = $('select[name="cosmo_environment"]').val();
        
        if (!api_url || !subscription_key || !x_reference_id || !api_secret) {
            toastr.error('Please fill in all required fields first');
            return;
        }
        
        toastr.info('Testing connection...');
        
        $.ajax({
            url: window.location.pathname,
            type: 'POST',
            data: {
                action: 'test_connection',
                api_url: api_url,
                subscription_key: subscription_key,
                x_reference_id: x_reference_id,
                api_secret: api_secret,
                environment: environment
            },
            dataType: 'json',
            success: function(data) {
                // Show status panel
                $('#connection-status-panel').show();
                
                // Update status badge
                var statusBadge = $('#status-badge');
                if (data.status === 'Connected') {
                    statusBadge.removeClass('label-default label-danger').addClass('label-success');
                    statusBadge.html('<i class="fa fa-check-circle"></i> ' + data.status);
                    toastr.success('Connection successful!');
                } else {
                    statusBadge.removeClass('label-default label-success').addClass('label-danger');
                    statusBadge.html('<i class="fa fa-times-circle"></i> ' + data.status);
                    toastr.error('Connection failed. Check details below.');
                }
                
                // Update info
                $('#portal-info').html(data.portal + ' enabled');
                $('#last-tested').html(data.last_tested);
                $('#success-rate').html(data.success_rate || '--');
                $('#failures-count').html(data.failures);
                
                // Build test results table
                var resultsHtml = '<table class="table table-condensed table-bordered" style="margin-bottom:0;">';
                $.each(data.tests, function(key, test) {
                    var statusClass = '';
                    var statusIcon = '';
                    if (test.status === 'Pass') {
                        statusClass = 'text-success';
                        statusIcon = '<i class="fa fa-check-circle text-success"></i>';
                    } else if (test.status === 'Fail') {
                        statusClass = 'text-danger';
                        statusIcon = '<i class="fa fa-times-circle text-danger"></i>';
                    } else {
                        statusClass = 'text-info';
                        statusIcon = '<i class="fa fa-info-circle text-info"></i>';
                    }
                    resultsHtml += '<tr>';
                    resultsHtml += '<td width="40%">' + statusIcon + ' <strong>' + test.name + '</strong></td>';
                    resultsHtml += '<td class="' + statusClass + '">' + test.message + '</td>';
                    resultsHtml += '<td width="15%">' + (test.response_time ? test.response_time + 'ms' : '--') + '</td>';
                    resultsHtml += '</tr>';
                });
                resultsHtml += '</table>';
                $('#test-results').html(resultsHtml);
            },
            error: function(xhr, status, error) {
                toastr.error('Error testing connection: ' + error);
                console.error(xhr.responseText);
            }
        });
    });
});
</script>

<style>
#test-results table tr td {
    padding: 5px 8px;
    font-size: 12px;
}
.label-success {
    background-color: #5cb85c;
    padding: 5px 10px;
}
.label-danger {
    background-color: #d9534f;
    padding: 5px 10px;
}
.label-default {
    background-color: #777;
    padding: 5px 10px;
}
</style>

{include file="sections/footer.tpl"}
