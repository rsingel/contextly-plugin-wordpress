(function($) {

    // TODO: Rewrite this code to be better and more intuitive

    $.fn.getWidget = function () {
        return $( '#linker_widget' );
    }

    $.fn.getWidgetType = function () {
        return $.fn.getWidget().attr( 'widget-type' );
    }

    $.fn.responsiveResizeHandler = function () {
        var widgetType = $.fn.getWidgetType();
        var getWidgetWidth = $.fn.getWidget().width();
        var resizeMinLimit = 350;

        if ( widgetType == 'blocks2' ) {
            if(getWidgetWidth < resizeMinLimit) {
                $(".blocks-widget2 li").css("width", "100%");
                $(".blocks-widget2 li").css("max-width", "100%");
                $(".blocks-widget2 li img").css("width", "30%");
                $(".blocks-widget2 li p").css({"width":"60%", "margin-top":0});
            } else {
                $(".blocks-widget2 li").css("width", "23%");
                $(".blocks-widget2 li").css("max-width", 160);
                $(".blocks-widget2 li img").css("width", "94%");
                $(".blocks-widget2 li p").css({ "width":"94%", "margin-top":5});
            }
        }

        if ( widgetType == 'float' ) {
            if(getWidgetWidth < resizeMinLimit) {
                $(".float-widget li").css({"width":"100%", "min-height":"90px"});
                $(".float-widget img").css("width", "100%");
                $(".float-widget p.link").css({ "width":"100%", "max-width":"100%", "margin-left":"0"});
            }

            else if(getWidgetWidth < 550 && getWidgetWidth > resizeMinLimit) {
                $(".float-widget li").css({"width":"100%", "min-height":"90px"});
                $(".float-widget img").css("width", "38%");
                $(".float-widget p.link").css({ "width":"59%", "max-width":"100%", "margin-left":"2%"});
            }

            else {
                $(".float-widget li").css({"width":"30.3%", "min-height":"190px"});
                $(".float-widget img").css("width", "100%");
                $(".float-widget p.link").css({ "width":"100%", "max-width":"100%", "margin-left":"0"});
            }
        }

        if ( widgetType == 'blocks' ) {
            var getImageHeight = $('.blocks-widget li img').height();
            $(".vidpop-playbutton-big").css("height", getImageHeight);

            if( getWidgetWidth <  500 ) {
                $(".blocks-widget li").css({"width":"153px", "margin-left":"6.4%", "margin-bottom":"5%"});
            } else {
                $(".blocks-widget li").css({"width":"22%", "margin-left":"2.4%", "margin-bottom":"2.2%"});
            }
        }

        /*display type: sidebar left <--*/

        var getLeftSidebarWidth = $('.contextly-sidebar-left').width();
        if(getLeftSidebarWidth < 240) {
            $(".contextly-sidebar .horizontal-line li").css("float", "left");
            $("horizontal-line").css("float", "left");
            $(".contextly-sidebar .linker_images li:first-child").css("margin-bottom", "5px");
        } else {
            $(".contextly-sidebar .horizontal-line li").css("float", "none");
            $(".contextly-sidebar .linker_images li:first-child").css("margin-bottom", "0");
        }

        if( getWidgetWidth < resizeMinLimit ) {
            $(".vidpop-playbutton-big").css("width", "30%");
        } else {
            $(".vidpop-playbutton-big").css("width", "94%");
        }
    }

    $.fn.checkIfWidgetLoadedAndResize = function () {
        var widgetType = $.fn.getWidgetType();

        if ( widgetType ) {
            $.fn.responsiveResizeHandler();

            if ( $.documentLoadInterval ) {
                clearInterval( $.documentLoadInterval );
            }
        }
    }

    $(window).resize(
        function() {
            $.fn.responsiveResizeHandler();
        }
    );

    $.documentLoadInterval = null;

    $(document).ready(
        function() {
            $.documentLoadInterval = self.setInterval(
                function () {
                    $.fn.checkIfWidgetLoadedAndResize();
                },
                500
            );
        }
    );

})(jQuery);
