(function($) {

    function ctxGetWidget() {
        return $( '#ctx_linker' );
    }

    function ctxGetWidgetType() {
        return ctxGetWidget().attr( 'widget-type' );
    }
	
	function ctxClassChanger(className) {
		var fullClass = 'ctx_around_site ' + className;
		$('.ctx_around_site').attr('class',fullClass);
	}
	
	function ctxTextClassChanger(className) {
		var fullClass = 'ctx_see_also ctx_text_widget ' + className;
		$('.ctx_see_also').attr('class',fullClass);
	}

	// branding popup
    function ctxResponsiveResizeHandler() {
        var screenWidth = window.innerWidth;
        var cxt_popup_width;
        var cxt_popup_height;

        if(screenWidth > 605) { 
			cxt_popup_width = 552; cxt_popup_height = 292; 
		}
        else { 
			cxt_popup_width = 250; cxt_popup_height = 500; 
		}

        $("#ctx_branding_open").prettyPhoto({
            theme:'light_square',
            autoplay_slideshow: false,
            default_width: cxt_popup_width,
            default_height: cxt_popup_height,
            social_tools: false,
            show_title: false
        });
		
		// blocks2 widget
        var widgetType = ctxGetWidgetType();
        var getWidgetWidth = ctxGetWidget().width();
        var resizeMinLimit = 480;

        if ( widgetType == 'blocks2' ) {
            if(getWidgetWidth < resizeMinLimit) {
				ctxClassChanger('ctx_blocks2mobile');
			} else {
				ctxClassChanger('ctx_blocks2site');
			}
        }

		//float widget
        if ( widgetType == 'float' ) {
            if(getWidgetWidth < resizeMinLimit) {
				ctxClassChanger('ctx_floatmobile');
			}
            else if(getWidgetWidth < 550 && getWidgetWidth > resizeMinLimit) {
				ctxClassChanger('ctx_floattablet');
            } 
			else { 
				ctxClassChanger('ctx_floatsite'); 
			}
        }

		//blocks widget
        if ( widgetType == 'blocks' ) {  
            if( getWidgetWidth <  500 ) { 
				ctxClassChanger('ctx_blockmobile');
            } else {
				ctxClassChanger('ctx_blocksite'); 
			}

            $(".ctx_blocks_widget li a").on("mouseover", function(event){
                $(this).toggleClass('ctx_blocksslider');
                var getTextHeight = $('.ctx_blocksslider p span').height();
                if(getTextHeight>50) {
                    $(".ctx_blocksslider p").css("height", getTextHeight);
                }
            });

            $(".ctx_blocks_widget li a").on("mouseout", function(event){
                $(".ctx_blocksslider p").css("height", "46px");
                $(this).removeClass('ctx_blocksslider');
            });
        }
		
		//text widget
        if ( widgetType == 'default' ) {  		
            if( getWidgetWidth <  400 ) { 
				ctxTextClassChanger('ctx_textmobile');
            } else {
				ctxTextClassChanger('ctx_textsite'); 
			}
        }
		
		//sidebar
        var getLeftSidebarWidth = $('.ctx_sidebar').width();
        if(getLeftSidebarWidth < 240) {
            $(".ctx_sidebar .ctx_horizontal_line li").css("float", "left");
            $("ctx_horizontal_line").css("float", "left");       
        } else {
            $(".ctx_sidebar .ctx_horizontal_line li").css("float", "none");
        }      
    }

    function ctxCheckIfWidgetLoadedAndResize() {
        var widgetType = ctxGetWidgetType();
        documentLoadCheckCount++;

        if ( widgetType ) {
            ctxResponsiveResizeHandler();
            ctxClearIfWidgetLoadedInterval();
        }

        if ( documentLoadCheckCount > 10 ) {
            ctxClearIfWidgetLoadedInterval();
        }
    }

    function ctxClearIfWidgetLoadedInterval() {
        if ( documentLoadInterval ) {
            clearInterval( documentLoadInterval );
        }
    }

    $(window).resize(
        function() {
            ctxResponsiveResizeHandler();
        }
    );

    var documentLoadInterval = null;
    var documentLoadCheckCount = 0;

    $(document).ready(
        function() {
            documentLoadInterval = self.setInterval(
                function () {
                    ctxCheckIfWidgetLoadedAndResize();
                },
                500
            );
        }
    );

})(jQuery);
