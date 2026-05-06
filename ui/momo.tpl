<div class="panel panel-primary panel-collapsible">
    <div class="panel-heading" data-toggle="collapse" href="#collapse-momo">
        <h4 class="panel-title">Mobile Money (MTN / Airtel) - Pure PHP Gateway</h4>
    </div>
    <div id="collapse-momo" class="panel-body collapse in">
        <form id="momo_config_form" onsubmit="return false;">
            <div class="form-group">
                <label>API Base URL</label>
                <input type="url" class="form-control" name="momo_api_url" value="{$momo_api_url}" placeholder="https://sandbox.momodeveloper.com" required>
                <small class="text-muted">Use sandbox URL for testing, production URL for live.</small>
            </div>
            <div class="form-group">
                <label>Ocp-Apim-Subscription-Key</label>
                <input type="text" class="form-control" name="momo_subscription_key" value="{$momo_subscription_key}" required>
            </div>
            <div class="form-group">
                <label>API User (Primary key)</label>
                <input type="text" class="form-control" name="momo_api_user" value="{$momo_api_user}" required>
            </div>
            <div class="form-group">
                <label>API Key (Secondary key)</label>
                <input type="password" class="form-control" name="momo_api_key" value="{$momo_api_key}" required>
            </div>
            <div class="form-group">
                <label>Currency Code</label>
                <input type="text" class="form-control" name="momo_currency" value="{$momo_currency}" placeholder="EUR, XAF, USD, etc." required>
            </div>
            <div class="form-group">
                <label>Environment</label>
                <select class="form-control" name="momo_environment">
                    <option value="sandbox" {if $momo_environment == 'sandbox'}selected{/if}>Sandbox (Test)</option>
                    <option value="production" {if $momo_environment == 'production'}selected{/if}>Production (Live)</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" id="momo_save_btn">Save Settings</button>
        </form>
    </div>
</div>

<script>
    $('#momo_save_btn').click(function() {
        var formData = $('#momo_config_form').serialize();
        $.post('{$_url}momo/save_config/', formData, function(response) {
            if (response.success) {
                toastr.success("Settings saved successfully");
            } else {
                toastr.error("Error saving settings");
            }
        }, 'json').fail(function() {
            toastr.error("Request failed");
        });
    });
</script>