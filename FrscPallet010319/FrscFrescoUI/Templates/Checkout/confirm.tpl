{extends file='parent:frontend/checkout/confirm.tpl'}

{block name='frontend_checkout_confirm_error_messages' append}
    {if $frozenValueError}
        {include file="frontend/_includes/messages.tpl" type="error" content="{s name='FrozenValueError'}Du hast die Mindestmenge von gefrorenen Artikeln nicht erreicht! Es fehlen noch{/s} {math equation="x - y" x=$frozenValueMin y=$frozenValue format="%.2f"} Kg"}
    {/if}

{/block}


{block name='frontend_checkout_confirm_submit'}
    {if !$boxError}
        {$smarty.block.parent}
    {/if}
    {debug}
{/block}