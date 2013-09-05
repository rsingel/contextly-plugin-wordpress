(function($) {

    $.fn.getWidget = function () {
        return $( '#ctx_linker' );
    }

    $.fn.getWidgetType = function () {
        return $.fn.getWidget().attr( 'widget-type' );
    }

    $.fn.classChanger = function ( widgetClass, hasClassName, addClassName) {
		if( $( '.' + widgetClass ).hasClass( hasClassName ) ) {
            $( '.' + widgetClass ).removeClass( hasClassName );
        }
		$( '.' + widgetClass ).addClass( addClassName );
	}

    $.fn.responsiveResizeHandler = function () {
        var screenWidth = window.innerWidth;
        var cxt_popup_width;
        var cxt_popup_height;

        if(screenWidth > 605) { cxt_popup_width = 552; cxt_popup_height = 292; }
        else { cxt_popup_width = 250; cxt_popup_height = 500; }

        $("#ctx_branding_open").prettyPhoto({
            theme:'light_square',
            autoplay_slideshow: false,
            default_width: cxt_popup_width,
            default_height: cxt_popup_height,
            social_tools: false,
            show_title: false
        });

        var widgetType = $.fn.getWidgetType();
        var getWidgetWidth = $.fn.getWidget().width();
        var resizeMinLimit = 350;

        if ( widgetType == 'blocks2' ) {
            if(getWidgetWidth < resizeMinLimit) {
                $.fn.classChanger('ctx_blocks_widget2','ctx_blocks2site', 'ctx_blocks2mobile');
            } else {
                $.fn.classChanger('ctx_blocks_widget2','ctx_blocks2mobile', 'ctx_blocks2site');
            }
        }

        if ( widgetType == 'float' ) {
            if(getWidgetWidth < resizeMinLimit) {
                $(".ctx_float_widget li").css({"width":"100%", "min-height":"90px"});
                $(".ctx_float_widget img").css("width", "100%");
                $(".ctx_float_widget p.ctx_link").css({ "width":"100%", "max-width":"100%", "margin-left":"0"});
            }

            else if(getWidgetWidth < 550 && getWidgetWidth > resizeMinLimit) {
                $(".ctx_float_widget li").css({"width":"100%", "min-height":"90px"});
                $(".ctx_float_widget img").css("width", "38%");
                $(".ctx_float_widget p.ctx_link").css({ "width":"59%", "max-width":"100%", "margin-left":"2%"});
            }

            else {
                $(".ctx_float_widget li").css({"width":"32.3%"});
                $(".ctx_float_widget img").css("width", "100%");
                $(".ctx_float_widget p.ctx_link").css({ "width":"100%", "max-width":"100%", "margin-left":"0"});
            }
        }

        if ( widgetType == 'blocks' ) {
            var getImageHeight = $('.ctx_blocks_widget li img').height();
            $(".vidpop-playbutton-big").css("height", getImageHeight);

            if( getWidgetWidth <  500 ) {
                $(".ctx_blocks_widget li").css({"width":"153px", "margin-left":"6.4%", "margin-bottom":"5%"});
            } else {
                $(".ctx_blocks_widget li").css({"width":"22%", "margin-left":"2.4%", "margin-bottom":"2.2%"});
            }

            $(".ctx_blocks_widget li a").on("mouseover", function(event){
                $(this).toggleClass('heightauto');
                var getTextHeight = $('.heightauto p span').height();
                if(getTextHeight>50) {
                    $(".heightauto p").css("height", getTextHeight);
                }
            });

            $(".ctx_blocks_widget li a").on("mouseout", function(event){
                $(".heightauto p").css("height", "46px");
                $(this).removeClass('heightauto');
            });
        }

        var getLeftSidebarWidth = $('.ctx_sidebar').width();
        if(getLeftSidebarWidth < 240) {
            $(".ctx_sidebar .ctx_horizontal_line li").css("float", "left");
            $("ctx_horizontal_line").css("float", "left");
            $(".ctx_sidebar .linker_images li:first-child").css("margin-bottom", "5px");
        } else {
            $(".ctx_sidebar .ctx_horizontal_line li").css("float", "none");
            $(".ctx_sidebar .linker_images li:first-child").css("margin-bottom", "0");
        }

        if( getWidgetWidth < resizeMinLimit ) {
            $(".vidpop-playbutton-big").css("width", "30%");
        } else {
            $(".vidpop-playbutton-big").css("width", "94%");
        }
    }

    $.fn.checkIfWidgetLoadedAndResize = function () {
        var widgetType = $.fn.getWidgetType();
        $.documentLoadCheckCount++;

        if ( widgetType ) {
            $.fn.responsiveResizeHandler();
            $.fn.clearIfWidgetLoadedInterval();
        }

        if ( $.documentLoadCheckCount > 10 ) {
            $.fn.clearIfWidgetLoadedInterval();
        }
    }

    $.fn.clearIfWidgetLoadedInterval = function () {
        if ( $.documentLoadInterval ) {
            clearInterval( $.documentLoadInterval );
        }
    }

    $(window).resize(
        function() {
            $.fn.responsiveResizeHandler();
        }
    );

    $.documentLoadInterval = null;
    $.documentLoadCheckCount = 0;

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
