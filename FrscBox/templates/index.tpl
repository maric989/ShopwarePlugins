{extends file="parent:frontend/index/index.tpl"}


{block name='frontend_index_navigation_categories_top'}
    <div class="frsc--fixed-navigation-wrapper" data-fixednavigationscrolltop="{$frscUiPluginConfig.fixedNavigationScrollTop}" data-fixedanimalfilterscrolltop="{$frscUiPluginConfig.fixedAnimalFilterScrollTop}">
        <nav class="navigation-main">
            <div class="container" data-menu-scroller="true" data-listSelector=".navigation--list.container" data-viewPortSelector=".navigation--list-wrapper">
                {block name="frontend_index_navigation_categories_top_include"}
                    {include file='frontend/index/main-navigation.tpl'}
                {/block}
            </div>
        </nav>
    </div>

{/block}


{block name='frontend_index_after_body' append}

        <div class="frsc--fixed-card">
            <i class="icon--basket"></i>
            <div id="rezultat"></div>
        </div>
{debug}
{/block}