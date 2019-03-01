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
                {if $b2bUser}
                    {if $frost_faktor}
                        <div class="alert is--primary is--rounded">
                            <div class="alert--icon">
                                <div class="icon--element icon--info"></div>
                            </div>
                            <div class="alert--content">Bitte wählen Ihre Versandart aus</div>
                        </div>
                        <div class="tab">
                            <button class="tablinks" onclick="openTab(event, 'NormalShipp')" id="noPallet">DHL-Versand</button>
                            <button class="tablinks" onclick="openTab(event, 'PalletShipp')" id="usePallet">Palettenversand</button>
                        </div>
                        <div class="alert is--info is--rounded">
                            <div class="alert--icon">
                                <div class="icon--element icon--info"></div>
                            </div>
                            <div class="alert--content" id="price_compare_info">0,64 € pro 1 Kg/Versandkosten</div>
                        </div>
                    {/if}
                    {if $boxcount['remTotal'] <= 899 && $boxcount['remTotal'] > 0}
                        <div class="alert is--info is--rounded" id="normalFrozenWarning">
                            <div class="alert--icon">
                                <div class="icon--element icon--warning"></div>
                            </div>
                            <div class="alert--content">Wir versenden ausschließlich zu 90% gefüllte 20kg Kartons</div>
                        </div>
                        <div class="alert is--info is--rounded" style="display: none" id="palletFrozenWarning">
                            <div class="alert--icon">
                                <div class="icon--element icon--warning"></div>
                            </div>
                            <div class="alert--content">Sie können eine Palette mit einem Gewicht bis 650kg befüllen.</div>
                        </div>
                    {/if}
                {else}
                    {if $zeroBox}
                        <div class="alert is--error is--rounded">
                            <div class="alert--icon">
                                <div class="icon--element icon--warning"></div>
                            </div>
                            <div class="alert--content">{$zeroBox}</div>
                        </div>
                    {else}
                        {if $boxcount['remTotal'] < 500 && $boxcount['remTotal'] > 0}
                            <div class="alert is--info is--rounded">
                                <div class="alert--icon">
                                    <div class="icon--element icon--warning"></div>
                                </div>
                                <div class="alert--content">Du musst jedes Weitere Paket wegen des Eigenkühleffekts mindestens zu 50% befüllen.
                                </div>
                            </div>
                        {/if}
                    {/if}

                {/if}
                {*ajax cart progress bar*}
                {if $b2bUser}
                    <div id="NormalShipp" class="tabcontent" style="display: block">
                        {if $boxcount['remTotal'] != 0}
                            <div class="wrapperPallet">
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
                        {elseif $boxcount['boxes'] >= 1 && $boxcount['remTotal'] == 0}
                            <div class="wrapperPallet">
                                <div class="box--container">
                                    <div class="number">100%</div>
                                    <div id="progressbar">
                                        <div style="width:100%;background-color: green"></div>
                                    </div>
                                    <div class='test'><span class="circle">{$boxcount['boxes']}</span><i class="icon--box"></i></div>
                                </div>
                            </div>
                        {/if}
                    </div>

                    <div id="PalletShipp" class="tabcontent" style="display: none">
                        {if $pallet['remTotal'] != 0}
                            <div class="wrapperPallet">
                                <div class="box--container">
                                    <div class="number">{$pallet['remTotal']/10}%</div>
                                    <div id="progressbar">
                                        {if $pallet['remTotal']/10 < 30}
                                            <div style="width:{$pallet['remTotal']/10}%;background-color: red"></div>
                                        {elseif $pallet['remTotal']/10 >= 30 && $pallet['remTotal']/10 < 80}
                                            <div style="width:{$pallet['remTotal']/10}%;background-color: orange"></div>
                                        {else}
                                            <div style="width:{$pallet['remTotal']/10}%;background-color: green"></div>
                                        {/if}
                                    </div>

                                    <div class='test'><span class="circle">{$pallet['boxes']+1}</span><i class="icon--box"></i></div>

                                </div>
                            </div>
                        {elseif $pallet['boxes'] >= 1 && $pallet['remTotal'] == 0}
                            <div class="wrapperPallet">
                                <div class="box--container">
                                    <div class="number">100%</div>
                                    <div id="progressbar">
                                        <div style="width:100%;background-color: green"></div>
                                    </div>
                                    <div class='test'><span class="circle">{$pallet['boxes']}</span><i class="icon--box"></i></div>
                                </div>
                            </div>
                        {/if}

                    </div>
                {else}
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
                    {elseif $boxcount['boxes'] >= 1 && $boxcount['remTotal'] == 0}
                        <div class="wrapperic">
                            <div class="box--container">
                                <div class="number">100%</div>
                                <div id="progressbar">
                                        <div style="width:100%;background-color: green"></div>
                                </div>
                                <div class='test'><span class="circle">{$boxcount['boxes']}</span><i class="icon--box"></i></div>
                            </div>
                        </div>
                    {/if}
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
                            {if $transport}
                                <span class="prices--articles-text">Preis</span>
                            {else}
                                <span class="prices--articles-text">{s name="AjaxCartTotalAmount"}{/s}</span>
                            {/if}
                            <span class="prices--articles-amount">{$sBasket.Amount|currency}</span>
                        </div>

                        {if $transport}
                            <div class="prices--articles">
                                {*<span class="prices--articles-text">{s name="AjaxCartTotalAmount"}{/s}</span>*}
                                <input type="hidden" value="{$dostava}" id="frozen_shipping">
                                <input type="hidden" value="{$pallet_price}" id="pallet_shipping">
                                <input type="hidden" value="{$pallet_sum}" id="pallet_sum">
                                <input type="hidden" value="{$sAmount}" id="sAmount">
                                <span class="prices--articles-text">Versandkosten</span>
                                <span class="prices--articles-amount" id="shipping_price" >{$dostava}<span> &euro;</span></span>
                            </div>
                            {if $b2bUser == false}
                                <div class="not-full-box" style="width: 109%;margin-left: -10px">
                                    <div class="alert is--info is--rounded">
                                        <div class="alert--icon">
                                            <div class="icon--element icon--warning"></div>
                                        </div>
                                        {if $zeroBoxShipping}
                                            <div class="alert--content">{$zeroBoxShipping}</div>
                                        {else}
                                            <div class="alert--content">Willst Du Dein Paket nicht mit mindestens 80% befüllen, fallen Versandkosten in Höhe von {$dostava|currency} an.</div>
                                        {/if}
                                    </div>
                                </div>
                            {/if}
                            <div class="prices--articles">
                                <span class="prices--articles-text">Preis einschl. Versandkosten</span>
                                <span class="prices--articles-amount" id="basket_amount">{$transport|currency}</span>
                            </div>
                        {/if}

                    {/block}
                </div>
            {/if}
        {/block}

        {if !$boxError}
            {block name='frontend_checkout_ajax_cart_button_container'}
                <div class="button--container{if $frozenValueError}{s} frozen-value-error{/s}{/if}" >
                    {*Zur Kasse*}
                    {block name='frontend_checkout_ajax_cart_button_container_inner'}
                        {if !($sDispatchNoOrder && !$sDispatches) && !$frozenValueError}
                            {block name='frontend_checkout_ajax_cart_open_checkout'}
                                <a href="{if {config name=always_select_payment}}{url controller='checkout' action='shippingPayment'}{else}{url controller='checkout' action='confirm'}{/if}" class="btn is--primary button--checkout is--icon-right" title="{"{s name='AjaxCartLinkConfirm'}{/s}"|escape}" id="zurkasse">
                                    <i class="icon--arrow-right"></i>
                                    {s name='AjaxCartLinkConfirm'}{/s}
                                </a>
                                {*<form name="sAddToBasket" method="post" action="{url controller='checkout' action='addArticle'}" class="buybox--form" data-add-article="true" data-eventname="submit" data-showmodal="false" data-addarticleurl="{url controller='checkout' action='ajaxAddArticleCart'}">*}
                                    {*<input type="hidden" name="sAdd" value="{$variant.ordernumber}">*}
                                    {*<input type="hidden" name="sQuantity" value="1" class="quick-buy-quantity">*}
                                    {*<input type="hidden" name="Pallet" value="1">*}
                                    {*<button class="buybox--button btn block is--primary is--icon is--center{if $variant.prices[0].minpurchase == 0} is--not-available{/if}" name="{s name='ListingBuyActionAdd'}In den Warenkorb{/s}">*}
                                        {*Zurrkasse*}
                                    {*</button>*}
                                {*</form>*}
                            {/block}
                        {else}
                            {block name='frontend_checkout_ajax_cart_open_checkout'}
                                <span class="btn is--disabled is--primary button--checkout is--icon-right" title="{"{s name='AjaxCartLinkConfirm'}{/s}"|escape}">
                                    <i class="icon--arrow-right"></i>
                                    {s name='AjaxCartLinkConfirm'}{/s}
                                </span>
                            {/block}
                            <form name="sAddToBasket" method="post" action="{url controller='checkout' action='addArticle'}" class="buybox--form" data-add-article="true" data-eventname="submit" data-showmodal="false" data-addarticleurl="{url controller='checkout' action='ajaxAddArticleCart'}">
                                <input type="hidden" name="sAdd" value="{$variant.ordernumber}">
                                <input type="hidden" name="sQuantity" value="1" class="quick-buy-quantity">
                                <button class="buybox--button btn block is--primary is--icon is--center{if $variant.prices[0].minpurchase == 0} is--not-available{/if}" name="{s name='ListingBuyActionAdd'}In den Warenkorb{/s}">
                                    <i class="icon--basket"></i>
                                </button>
                            </form>
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
{literal}
    <script id="pallet_table" type="fresco/pallet">
        <caption>Vergleichskosten zum DHL-Versand</caption>
        <thead>
            <tr>
                <th>Pakete</th>
                <th>Kg</th>
                <th>Preis</th>
            </tr>

        </thead>
        <tr>
            <td>1</td>
            <td>20 Kg</td>
            <td>12.95 €</td>
        </tr>
        <tr>
            <td>2</td>
            <td>40 Kg</td>
            <td>25.9 €</td>
        </tr>
        <tr>
            <td>3</td>
            <td>60 Kg</td>
            <td>38.85 €</td>
        </tr>
        <tr>
            <td>4</td>
            <td>80 Kg</td>
            <td>51.80 €</td>
        </tr>
        <tr>
            <td>5</td>
            <td>100 Kg</td>
            <td>64.75 €</td>
        </tr>
        <tr>
            <td>6</td>
            <td>120 Kg</td>
            <td>77.70 €</td>
        </tr>
        <tr>
            <td>7</td>
            <td>140 Kg</td>
            <td>90.65 €</td>
        </tr>
        <tr>
            <td>8</td>
            <td>160 Kg</td>
            <td>103.60 €</td>
        </tr>
        <tr>
            <td>9</td>
            <td>180 Kg</td>
            <td>116.55 €</td>
        </tr>
        <tr>
            <td>10</td>
            <td>200 Kg</td>
            <td>129.50 €</td>
        </tr>
        <tr>
            <td>11</td>
            <td>220 Kg</td>
            <td>142.45 €</td>
        </tr>
        <tr>
            <td>12</td>
            <td>240 Kg</td>
            <td>155.40 €</td>
        </tr>
        <tr>
            <td>13</td>
            <td>260 Kg</td>
            <td>168.35 €</td>
        </tr>
        <tr>
            <td>14</td>
            <td>280 Kg</td>
            <td>181.30 €</td>
        </tr>
        <tr>
            <td style="background-color:green;color:white">15</td>
            <td style="background-color:green;color:white">300 Kg</td>
            <td style="background-color:green;color:white">194.25 €</td>
        </tr>
        <tr>
            <td>16</td>
            <td>320 Kg</td>
            <td>207.20 €</td>
        </tr>
    </script>
{/literal}
{/block}
