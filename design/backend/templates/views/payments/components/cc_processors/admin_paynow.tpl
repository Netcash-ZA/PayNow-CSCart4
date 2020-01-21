<div class="control-group">
    <label class='control-label' for="account_number">{__("account_number")}:</label>
    <div class="controls">
        <input type="text" name="payment_data[processor_params][account_number]" id="account_number" value="{$processor_params.account_number}" class="input-text" />
    </div>
</div>

<div class="control-group">
    <label class='control-label' for="service_key">{__("text_service_key")}:</label>
    <div class="controls">
        <input type="text" name="payment_data[processor_params][service_key]" id="service_key" value="{$processor_params.service_key}" class="input-text" />
        <input type='hidden' name='payment_data[processor_params][currency]' id='currency' value='ZAR'>
    </div>
</div>

<div class="control-group">
    <label class='control-label' for="do_tokenization">{__("text_do_tokenization")}:</label>
    <div class="controls">
        <select name="payment_data[processor_params][do_tokenization]" id="do_tokenization">
            <option value="1" {if $processor_params.do_tokenization}selected="selected"{/if}>{__("true")}</option>
            <option value="0" {if !$processor_params.do_tokenization}selected="selected"{/if}>{__("false")}</option>
        </select>
    </div>
</div>

<div class="control-group">
    <label class='control-label' for="debug">{__("text_debug")}:</label>
    <div class="controls">
        <select name="payment_data[processor_params][debug]" id="debug">
            <option value="1" {if $processor_params.debug}selected="selected"{/if}>{__("true")}</option>
            <option value="0" {if !$processor_params.debug}selected="selected"{/if}>{__("false")}</option>
        </select>
    </div>
</div>


{include file="common/subheader.tpl" title=__("text_paynow_status_map") target="#text_paynow_status_map"}


{assign var="statuses" value=$smarty.const.STATUSES_ORDER|fn_get_simple_statuses}
<div id='text_paynow_status_map'>
    <div class="control-group">
        <label class='control-label' for="paynow_completed">{__("completed")}:</label>
        <div class="controls">
            <select name="payment_data[processor_params][statuses][completed]" id="paynow_completed">
                {foreach from=$statuses item="s" key="k"}
                <option value="{$k}" {if (isset($processor_params.statuses.completed) && $processor_params.statuses.completed == $k) || (!isset($processor_params.statuses.completed) && $k == 'P')}selected="selected"{/if}>{$s}</option>
                {/foreach}
            </select>
        </div>
    </div>

    <div class="control-group">
        <label class='control-label' for="paynow_failed">{__("failed")}:</label>
        <div class="controls">
            <select name="payment_data[processor_params][statuses][failed]" id="paynow_failed">
                {foreach from=$statuses item="s" key="k"}
                <option value="{$k}" {if (isset($processor_params.statuses.failed) && $processor_params.statuses.failed == $k) || (!isset($processor_params.statuses.failed) && $k == 'F')}selected="selected"{/if}>{$s}</option>
                {/foreach}
            </select>
        </div>
    </div>
</div>

