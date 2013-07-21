(function($) {	
	
	// TODO: move this code to main plugin script
	
	var videoPopupPlayButton=self.setInterval(function(){		
		
	  $(document).ready(function() {
		  
		var widget = $( '#linker_widget' );
        var getWidgetWidth = widget.width();
        var widgetType = widget.attr( 'widget-type' );
		  
		  /*display type Blocks2 <--*/
		  
	var getWidgetWidth = $('#linker_widget').width();		
		
		if(getWidgetWidth<350) {
			$(".blocks-widget2 li").css("width", "100%");
			$(".blocks-widget2 li").css("max-width", "100%");
			$(".blocks-widget2 li img").css("width", "30%");
			$(".blocks-widget2 li p").css({"width":"60%", "margin-top":0});
			$(".vidpop-playbutton-big").css("width", "30%");
		} else { 
			$(".blocks-widget2 li").css("width", "23%");
			$(".blocks-widget2 li").css("max-width", 160);
			$(".blocks-widget2 li img").css("width", "94%");
			$(".blocks-widget2 li p").css({ "width":"94%", "margin-top":5});
			$(".vidpop-playbutton-big").css("width", "94%");			
		}	
	  
	});
	
	},10);			
	
var videoPopupPlayButton=self.setInterval(function(){		
		
	  $(document).ready(function() {
		  
		  /*display type: Float <--*/		
		var getFloatWidth = $('#linker_widget').width();

		if(getFloatWidth<350) {			
			$(".float-widget li").css({"width":"100%", "min-height":"90px"});
			$(".float-widget img").css("width", "100%");		
			$(".float-widget p.link").css({ "width":"100%", "max-width":"100%", "margin-left":"0"});
		}		
		
		else if(getFloatWidth<550 && getFloatWidth>350) {			
			$(".float-widget li").css({"width":"100%", "min-height":"90px"});
			$(".float-widget img").css("width", "38%");
			$(".float-widget p.link").css({ "width":"59%", "max-width":"100%", "margin-left":"2%"});
		}
		
		else { 
			$(".float-widget li").css({"width":"30.3%", "min-height":"190px"});
			$(".float-widget img").css("width", "100%");
			$(".float-widget p.link").css({ "width":"100%", "max-width":"100%", "margin-left":"0"});		
		}				
		
		var getImageHeight = $('.blocks-widget li img').height();
		$(".vidpop-playbutton-big").css("height", getImageHeight);
		
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
			
	  });	
	  		
	},10);	
		
	var videoPopupPlayButton=self.setInterval(function(){		
		
	  $(document).ready(function() {		  
	  
	var getBlocksWidth = $('#linker_widget').width();		
		
		if(getBlocksWidth<500) {		
			$(".blocks-widget li").css({"width":"153px", "margin-left":"6.4%", "margin-bottom":"5%", });

		} else { 
			$(".blocks-widget li").css({"width":"22%", "margin-left":"2.4%", "margin-bottom":"2.2%", });
		}	
	  
	});
	
	},10);	
	
	

})(jQuery);















