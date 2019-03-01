/**
 * Created by tobias on 05.05.17.
 */
;(function($, windows) {
    'use strict';


    $.plugin('FixedCard', {
        defaults: {

        },

        init: function() {
            var me = this;
            me.onMobile;
            me.applyDataAttributes();
            me.registerEvents();


        },

        registerEvents: function () {
            var me = this;

            var enterFn = $.proxy(me.onEnterMobile, me);
            var exitFn  = $.proxy(me.onExitMobile, me);


            me._on($(window), 'scroll', $.proxy(me.onScroll, me));

            me._on(me.$el, 'click', $.proxy(me.onClick, me));

            StateManager.registerListener([
                {state: 'xs', enter: enterFn, exit: exitFn},
                {state: 's', enter: enterFn, exit: exitFn}
            ]);

            $.subscribe('plugin/swCollapseCart/onRegisterEvents', function (_event, _me) {
                me.cart = _me;
            });
        },
        onClick: function(e) {
            var me = this;
            me.cart.onMouseEnter(e);
        },
        onScroll : function (event) {
            var me = this;
            var el = me.$el[0];
            //if (me.onMobile) return;

            var isVisible = me.inViewport(el);
            if (me.fading) return;
            if (!isVisible) {
                me.fading = true;
                $(el).fadeIn(400, function() {
                    me.fading = false;
                });
                //if(!$(el).hasClass('fixed'))
                //    $(el).addClass('fixed');

            } else {
                me.fading = true;
                $(el).fadeOut(400, function () {
                    me.fading = false;
                });
                //if($(el).hasClass('fixed'))
                //    $(el).removeClass('fixed');
            }
        },
        onEnterMobile: function(state) {
            var me = this;
            me.onMobile = true;

        },
        onExitMobile: function(state) {
            var me = this;
            me.onMobile = false;
            this.onScroll()
        },

        inViewport: function(el) {


            var viewportTop = $(window).scrollTop();

            return viewportTop < 100;
        },

        destroy: function () {

            var me = this;
            me.off(me.eventSuffix);
            me._destroy();
        }
    })
})(jQuery, window);

$('.frsc--fixed-card').FixedCard();