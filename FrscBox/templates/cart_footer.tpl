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
                {$dostava|currency}
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
            {$transport|currency}
        </div>
    {/block}
    {else}
        {$smarty.block.parent}
    {/if}
{/block}

