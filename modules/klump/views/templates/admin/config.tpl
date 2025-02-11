<div class="panel panel-default">
    <div class="panel-heading">
        <i class="icon-cogs"></i>Sync Products with Klump Commerce
    </div>

    {if $is_sync_enabled}
        <form method="post" action="{$form_action}">
            <button type="submit" name="sync_all_products" value="1" class="btn btn-primary">
                Sync All Products with Klump Commerce
            </button>
        </form>
    {else}
        <div class="alert alert-warning">
            Enable "Automatic Product Sync" to use this feature.
        </div>
    {/if}
</div>
