<div class="panel panel-primary panel-collapsible">
    <div class="panel-heading" data-toggle="collapse" href="#collapse-cosmo">
        <h4 class="panel-title">Cosmo Mobile Money Gateway (MTN / Airtel)</h4>
    </div>
    <div id="collapse-cosmo" class="panel-body collapse in">
        <form id="cosmo_config_form" onsubmit="return false;">
            <div class="form-group">
                <label>API Base URL</label>
                <input type="url" class="form-control" name="cosmo_api_url" value="{$cosmo_api_url}" placeholder="https://sandbox.momodeveloper.com" required>
                <small class="text-muted">Use sandbox URL for testing, production URL for live.</small>
            </div>
            <div class="form-group">
                <label>Ocp-Apim-Subscription-Key</label>
                <input type="text" class="form-control" name="cosmo_subscription_key" value="{$cosmo_subscription_key}" required>
            </div>
            <div class="form-group">
                <label>API User (Primary key)</label>
                <input type="text" class="form-control" name="cosmo_api_user" value="{$cosmo_api_user}" required>
            </div>
            <div class="form-group">
                <label>API Key (Secondary key)</label>
                <input type="password" class="form-control" name="cosmo_api_key" value="{$cosmo_api_key}" required>
            </div>
            <div class="form-group">
                <label>Currency Code</label>
                <input type="text" class="form-control" name="cosmo_currency" value="{$cosmo_currency}" placeholder="EUR, XAF, USD, etc." required>
            </div>
            <div class="form-group">
                <label>Environment</label>
                <select class="form-control" name="cosmo_environment">
                    <option value="sandbox" {if $cosmo_environment == 'sandbox'}selected{/if}>Sandbox (Test)</option>
                    <option value="production" {if $cosmo_environment == 'production'}selected{/if}>Production (Live)</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" id="cosmo_save_btn">Save Settings</button>
        </form>
    </div>
</div>

<script>
    $('#cosmo_save_btn').click(function() {
        var formData = $('#cosmo_config_form').serialize();
        $.post('{$_url}cosmo/save_config/', formData, function(response) {
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