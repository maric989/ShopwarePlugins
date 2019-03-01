{extends file="parent:frontend/checkout/cart_footer.tpl"}

{*Shipping costs *}
{block name='frontend_checkout_cart_footer_field_labels_shipping'}
    {if $shippingExist}
        <li class="list--entry block-group entry--shipping">
            {block name='frontend_checkout_cart_footer_field_labels_shipping_label'}
                <div class="entry--label block">
                    {s name="CartFooterLabelShipping"}{/s}
                </div>
            {/block}
            {block name='frontend_checkout_cart_footer_field_labels_shipping_value'}
                <div class="entry--value block">
                    {if $b2bUser}
                        {if $palletExist}
                            {$pallet_price|currency}
                        {else}
                            {$dostava|currency}
                        {/if}
                    {else}
                        {$dostava|currency}
                    {/if}
                </div>
            {/block}
        </li>
    {elseif $urlBox === '/checkout/finish'}
        <li class="list--entry block-group entry--shipping">
            {block name='frontend_checkout_cart_footer_field_labels_shipping_label'}
                <div class="entry--label block">
                    {s name="CartFooterLabelShipping"}{/s}
                </div>
            {/block}
            {block name='frontend_checkout_cart_footer_field_labels_shipping_value'}
                <div class="entry--value block">
                    {$last_order[0]['invoice_shipping']|currency}
                </div>
            {/block}
        </li>
    {else}
        {$smarty.block.parent}
    {/if}
{/block}

{*Total sum *}
{block name='frontend_checkout_cart_footer_field_labels_total'}
    {if $shippingExist}
        {block name='frontend_checkout_cart_footer_field_labels_total_label'}
            <div class="entry--label block">
                {s name="CartFooterLabelTotal"}{/s}
            </div>
        {/block}
        {block name='frontend_checkout_cart_footer_field_labels_total_label'}
            <div class="entry--value block is--no-star">
                {if $frscUserGroup == 'H'}
                    {if $palletExist}
                        {($sAmountWithTax + $pallet_price)|currency}
                    {else}
                        {($transport + $sAmountTax)|currency}
                    {/if}
                {else}
                    {$transport|currency}
                {/if}
            </div>
        {/block}
    {elseif $last_order}
        {block name='frontend_checkout_cart_footer_field_labels_total_label'}
            <div class="entry--label block">
                {s name="CartFooterLabelTotal"}{/s}
            </div>
        {/block}
        {block name='frontend_checkout_cart_footer_field_labels_total_label'}
            <div class="entry--value block is--no-star">
                {$last_order[0]['invoice_amount']|currency}
            </div>
        {/block}
    {else}
        {$smarty.block.parent}
    {/if}
    
{/block}

