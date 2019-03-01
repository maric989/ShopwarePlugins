{extends file='parent:frontend/checkout/ajax_cart.tpl'}

{block name='frontend_checkout_ajax_cart'}
    <div class="ajax--cart">
        {block name='frontend_checkout_ajax_cart_buttons_offcanvas'}
            <div class="buttons--off-canvas">
                {block name='frontend_checkout_ajax_cart_buttons_offcanvas_inner'}
                    <a href="#close-categories-menu" class="close--off-canvas">
                        <i class="icon--arrow-left"></i>
                        {s name="AjaxCartContinueShopping"}{/s}
                    </a>
                {/block}
            </div>
        {/block}

        {block name='frontend_checkout_ajax_cart_alert_box'}
            {if $theme.offcanvasCart}
                {if $basketInfoMessage}
                    <div class="alert is--info is--rounded is--hidden">
                        <div class="alert--icon">
                            <div class="icon--element icon--info"></div>
                        </div>
                        <div class="alert--content">{$basketInfoMessage}</div>
                    </div>
                {else}
                    <div class="alert is--success is--rounded is--hidden">
                        <div class="alert--icon">
                            <div class="icon--element icon--check"></div>
                        </div>
                        <div class="alert--content">{s name="AjaxCartSuccessText" namespace="frontend/checkout/ajax_cart"}{/s}</div>
                    </div>
                {/if}
                {if $boxcount['remTotal'] < 900 && $boxcount['remTotal'] > 0}
                    <div class="alert is--info is--rounded">
                        <div class="alert--icon">
                        <div class="icon--element icon--warning"></div>
                        </div>
                        <div class="alert--content">Every box must have min 10kg</div>
                    </div>
                {/if}
                {if $sumBoxes}
                    {foreach from=$sumBoxes key=k item=v}
                        <div class="wrapperic">
                            <div class="box--container">

                                <div class="number">100%</div>
                                <div id="full--progressbar">
                                    <div style="background-color: limegreen"></div>
                                </div>

                                <div class='test'><span class="circle">{$v}</span><i class="icon--box"></i></div>

                            </div>
                        </div>
                    {/foreach}
                {/if}
                {if $boxcount['remTotal'] != 0}
                    <div class="wrapperic">
                        <div class="box--container">
                            <div class="number">{$boxcount['remTotal']/10}%</div>
                            <div id="progressbar">
                                {if $boxcount['remTotal']/10 < 30}
                                    <div style="width:{$boxcount['remTotal']/10}%;background-color: red"></div>
                                {elseif $boxcount['remTotal']/10 >= 30 && $boxcount['remTotal']/10 < 80}
                                    <div style="width:{$boxcount['remTotal']/10}%;background-color: orange"></div>
                                {else}
                                    <div style="width:{$boxcount['remTotal']/10}%;background-color: green"></div>
                                {/if}
                            </div>

                            <div class='test'><span class="circle">{$boxcount['boxes']+1}</span><i class="icon--box"></i></div>

                        </div>
                    </div>
                {/if}
            {/if}
        {/block}
        {block name='frontend_checkout_ajax_cart_item_container'}
            <div class="item--container">
                {block name='frontend_checkout_ajax_cart_item_container_inner'}
                    {if $sBasket.content}
                        {foreach $sBasket.content as $sBasketItem}
                            {block name='frontend_checkout_ajax_cart_row'}
                                {include file="frontend/checkout/ajax_cart_item.tpl" basketItem=$sBasketItem}
                            {/block}
                        {/foreach}
                    {else}
                        {block name='frontend_checkout_ajax_cart_empty'}
                            <div class="cart--item is--empty">
                                {block name='frontend_checkout_ajax_cart_empty_inner'}
                                    <span class="cart--empty-text">{s name='AjaxCartInfoEmpty'}{/s}</span>
                                {/block}
                            </div>
                        {/block}
                    {/if}
                {/block}
            </div>
        {/block}

        {block name='frontend_checkout_ajax_cart_prices_container'}
            {if $sBasket.content}
                <div class="prices--container">
                    {block name='frontend_checkout_ajax_cart_prices_container_inner'}
                        <div class="prices--articles">
                            <span class="prices--articles-text">{s name="AjaxCartTotalAmount"}{/s}</span>
                            <span class="prices--articles-amount">{$sBasket.Amount|currency}</span>
                        </div>
                        {if $transport}
                        <div class="prices--articles">
                            <span class="prices--articles-text">Price with transport</span>
                            <span class="prices--articles-amount">{$transport|currency}</span>
                        </div>
                        <div class="alert is--info is--rounded">
                            <div class="alert--icon">
                                <div class="icon--element icon--warning"></div>
                            </div>
                            <div class="alert--content">If box is not 100% full you pay extra shipping</div>
                        </div>
                        {/if}

                    {/block}
                </div>
            {/if}
        {/block}

        {if !$boxError}
            {block name='frontend_checkout_ajax_cart_button_container'}
                <div class="button--container{if $frozenValueError}{s} frozen-value-error{/s}{/if}" >
                    {block name='frontend_checkout_ajax_cart_button_container_inner'}
                        {if !($sDispatchNoOrder && !$sDispatches) && !$frozenValueError}
                            {block name='frontend_checkout_ajax_cart_open_checkout'}
                                <a href="{if {config name=always_select_payment}}{url controller='checkout' action='shippingPayment'}{else}{url controller='checkout' action='confirm'}{/if}" class="btn is--primary button--checkout is--icon-right" title="{"{s name='AjaxCartLinkConfirm'}{/s}"|escape}">
                                    <i class="icon--arrow-right"></i>
                                    {s name='AjaxCartLinkConfirm'}{/s}
                                </a>
                            {/block}
                        {else}
                            {block name='frontend_checkout_ajax_cart_open_checkout'}
                                <span class="btn is--disabled is--primary button--checkout is--icon-right" title="{"{s name='AjaxCartLinkConfirm'}{/s}"|escape}">
                                    <i class="icon--arrow-right"></i>
                                    {s name='AjaxCartLinkConfirm'}{/s}
                                </span>
                            {/block}
                        {/if}
                        {block name='frontend_checkout_ajax_cart_open_basket'}
                            <a href="{url controller='checkout' action='cart'}" class="btn button--open-basket is--icon-right" title="{"{s name='AjaxCartLinkBasket'}{/s}"|escape}">
                                <i class="icon--arrow-right"></i>
                                {s name='AjaxCartLinkBasket'}{/s}
                            </a>
                        {/block}
                    {/block}
                </div>
            {/block}
        {/if}
    </div>
    {debug}
{/block}
