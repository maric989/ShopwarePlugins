{extends file='parent:frontend/checkout/cart.tpl'}



{* Error messages *}
{block name='frontend_checkout_cart_error_messages' append}
    {if $frozenValueError}
        {include file="frontend/_includes/messages.tpl" type="error" content="{s name='FrozenValueError'}Du hast die Mindestmenge von gefrorenen Artikeln nicht erreicht! Es fehlen noch{/s} {math equation="x - y" x=$frozenValueMin y=$frozenValue format="%.2f"} Kg"}
    {/if}
    {if $frozenValueError2}
        {include file="frontend/_includes/messages.tpl" type="error" content="{s name='FrozenValueError'}Du hast die Mindestmenge von gefrorenen Artikeln nicht erreicht! Es muessen noch{/s} {math equation="x - (y-10)" x=$frozenValue y=$frozenValueMin format="%.2f"} Kg"}
    {/if}
{/block}

{block name='frontend_checkout_paypal_payment_express_top'}
    {if !$frozenValueError}
        {$smarty.block.parent}
    {/if}
{/block}
{block name='frontend_checkout_paypal_payment_express_bottom'}
    {if !$frozenValueError}
        {$smarty.block.parent}
    {/if}
{/block}



{block name="frontend_checkout_actions_confirm"}
    {* Forward to the checkout *}
    {if !$sMinimumSurcharge && !($sDispatchNoOrder && !$sDispatches) && !$frozenValueError}
        {block name="frontend_checkout_actions_checkout"}
            <a href="{if {config name=always_select_payment}}{url controller='checkout' action='shippingPayment'}{else}{url controller='checkout' action='confirm'}{/if}"
               title="{"{s name='CheckoutActionsLinkProceedShort' namespace="frontend/checkout/actions"}{/s}"|escape}"
               class="btn btn--checkout-proceed is--primary right is--icon-right is--large">
                {s name="CheckoutActionsLinkProceedShort" namespace="frontend/checkout/actions"}{/s}
                <i class="icon--arrow-right"></i>
            </a>
        {/block}
    {else}
        {block name="frontend_checkout_actions_checkout"}
            <span
                    title="{"{s name='CheckoutActionsLinkProceedShort' namespace="frontend/checkout/actions"}{/s}"|escape}"
                    class="btn is--disabled btn--checkout-proceed is--primary right is--icon-right is--large">
                                            {s name="CheckoutActionsLinkProceedShort" namespace="frontend/checkout/actions"}{/s}
                <i class="icon--arrow-right"></i>
                                        </span>
        {/block}
    {/if}
{/block}
{block name="frontend_checkout_actions_confirm_bottom"}
    <div class="main--actions">

        {* Contiune shopping *}
        {if $sBasket.sLastActiveArticle.link}
            {block name="frontend_checkout_actions_link_last_bottom"}
                <a href="{$sBasket.sLastActiveArticle.link}"
                   title="{"{s name='CheckoutActionsLinkLast' namespace="frontend/checkout/actions"}{/s}"|escape}"
                   class="btn btn--checkout-continue is--secondary is--left continue-shopping--action is--icon-left is--large">
                    <i class="icon--arrow-left"></i> {s name="CheckoutActionsLinkLast" namespace="frontend/checkout/actions"}{/s}
                </a>
            {/block}
        {/if}

        {* Forward to the checkout *}
        {if !$sMinimumSurcharge && !($sDispatchNoOrder && !$sDispatches) && !$frozenValueError}
            {block name="frontend_checkout_actions_confirm_bottom_checkout"}
                <a href="{if {config name=always_select_payment}}{url controller='checkout' action='shippingPayment'}{else}{url controller='checkout' action='confirm'}{/if}"
                   title="{"{s name='CheckoutActionsLinkProceedShort' namespace="frontend/checkout/actions"}{/s}"|escape}"
                   class="btn btn--checkout-proceed is--primary right is--icon-right is--large">
                    {s name="CheckoutActionsLinkProceedShort" namespace="frontend/checkout/actions"}{/s}
                    <i class="icon--arrow-right"></i>
                </a>
            {/block}
        {else}
            {block name="frontend_checkout_actions_confirm_bottom_checkout"}
                <span
                        title="{"{s name='CheckoutActionsLinkProceedShort' namespace="frontend/checkout/actions"}{/s}"|escape}"
                        class="btn is--disabled btn--checkout-proceed is--primary right is--icon-right is--large">
                                                {s name="CheckoutActionsLinkProceedShort" namespace="frontend/checkout/actions"}{/s}
                    <i class="icon--arrow-right"></i>
                                            </span>
            {/block}
        {/if}
    </div>

    {if !$sMinimumSurcharge && ($sInquiry || $sDispatchNoOrder)}
        {block name="frontend_checkout_actions_inquiry"}
            <a href="{$sInquiryLink}"
               title="{"{s name='CheckoutActionsLinkOffer' namespace="frontend/checkout/actions"}{/s}"|escape}"
               class="btn btn--inquiry is--large is--full is--center">
                {s name="CheckoutActionsLinkOffer" namespace="frontend/checkout/actions"}{/s}
            </a>
        {/block}
    {/if}
{/block}


