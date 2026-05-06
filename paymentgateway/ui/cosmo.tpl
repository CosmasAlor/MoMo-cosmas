{include file="sections/header.tpl"}

<div class="panel panel-primary panel-collapsible">
    <div class="panel-heading" data-toggle="collapse" href="#collapse-cosmo">
        <h4 class="panel-title">Cosmo Mobile Money Gateway</h4>
    </div>
    <div id="collapse-cosmo" class="panel-body collapse in">
        <form id="cosmo_config_form" class="form-horizontal" method="post" role="form">
            <div class="form-group">
                <label class="col-md-2 control-label">API Base URL</label>
                <div class="col-md-6">
                    <input type="url" class="form-control" name="cosmo_api_url" value="{$cosmo_api_url}" placeholder="https://sandbox.momodeveloper.com" required>
                </div>
                <div class="col-md-4">
                    <span class="help-block">Use sandbox URL for testing, production URL for live.</span>
                </div>
            </div>
            <div class="form-group">
                <label class="col-md-2 control-label">API Key</label>
                <div class="col-md-6">
                    <input type="password" class="form-control" name="cosmo_api_key" value="{$cosmo_api_key}" required>
                </div>
                <div class="col-md-4">
                    <span class="help-block">Your Ocp-Apim-Subscription-Key</span>
                </div>
            </div>
            <div class="form-group">
                <label class="col-md-2 control-label">Currency</label>
                <div class="col-md-6">
                    <input type="text" class="form-control" name="cosmo_currency" value="{$cosmo_currency}" placeholder="EUR, XAF, USD, etc." required>
                </div>
            </div>
            <div class="form-group">
                <label class="col-md-2 control-label">Environment</label>
                <div class="col-md-6">
                    <select class="form-control" name="cosmo_environment">
                        <option value="sandbox" {if $cosmo_environment == 'sandbox'}selected{/if}>Sandbox (Test)</option>
                        <option value="production" {if $cosmo_environment == 'production'}selected{/if}>Production (Live)</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <div class="col-lg-offset-2 col-lg-10">
                    <button class="btn btn-success" type="submit" name="save" id="save">{$_L['Save']}</button>
                </div>
            </div>
        </form>
    </div>
</div>

{include file="sections/footer.tpl"}