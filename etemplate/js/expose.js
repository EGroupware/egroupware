/*egw:uses
	jquery.jquery;
	/phpgwapi/js/jquery/blueimp/js/jquery.blueimp-gallery.min.js;
*/

/**
 * Interface all exposed widget must support in order to getMedia for the blueimp Gallery.
 */
var et2_IExposable = new Interface({

	/**
	 * get media an array of media objects to pass to blueimp Gallery 
	 * @param {array} _attrs
	 */
	getMedia: function(_attrs) {},
	
});

/**
 * This function extends the given widget with blueimp gallery plugin
 * 
 * @param {type} widget
 * @returns {widget}
 */
function expose (widget)
{
	return widget.extend([et2_IExposable],{
		
			/**
			 * Initialize the expose media gallery
			 */
			init: function() {
				this._super.apply(this, arguments);
				
				this.expose_options = {
					// The Id, element or querySelector of the gallery widget:
					container: '#blueimp-gallery',
					// The tag name, Id, element or querySelector of the slides container:
					slidesContainer: 'div',
					// The tag name, Id, element or querySelector of the title element:
					titleElement: 'h3',
					// The class to add when the gallery is visible:
					displayClass: 'blueimp-gallery-display',
					// The class to add when the gallery controls are visible:
					controlsClass: 'blueimp-gallery-controls',
					// The class to add when the gallery only displays one element:
					singleClass: 'blueimp-gallery-single',
					// The class to add when the left edge has been reached:
					leftEdgeClass: 'blueimp-gallery-left',
					// The class to add when the right edge has been reached:
					rightEdgeClass: 'blueimp-gallery-right',
					// The class to add when the automatic slideshow is active:
					playingClass: 'blueimp-gallery-playing',
					// The class for all slides:
					slideClass: 'slide',
					// The slide class for loading elements:
					slideLoadingClass: 'slide-loading',
					// The slide class for elements that failed to load:
					slideErrorClass: 'slide-error',
					// The class for the content element loaded into each slide:
					slideContentClass: 'slide-content',
					// The class for the "toggle" control:
					toggleClass: 'toggle',
					// The class for the "prev" control:
					prevClass: 'prev',
					// The class for the "next" control:
					nextClass: 'next',
					// The class for the "close" control:
					closeClass: 'close',
					// The class for the "play-pause" toggle control:
					playPauseClass: 'play-pause',
					// The list object property (or data attribute) with the object type:
					typeProperty: 'type',
					// The list object property (or data attribute) with the object title:
					titleProperty: 'title',
					// The list object property (or data attribute) with the object URL:
					urlProperty: 'href',
					// The gallery listens for transitionend events before triggering the
					// opened and closed events, unless the following option is set to false:
					displayTransition: true,
					// Defines if the gallery slides are cleared from the gallery modal,
					// or reused for the next gallery initialization:
					clearSlides: true,
					// Defines if images should be stretched to fill the available space,
					// while maintaining their aspect ratio (will only be enabled for browsers
					// supporting background-size="contain", which excludes IE < 9).
					// Set to "cover", to make images cover all available space (requires
					// support for background-size="cover", which excludes IE < 9):
					stretchImages: true,
					// Toggle the controls on pressing the Return key:
					toggleControlsOnReturn: true,
					// Toggle the automatic slideshow interval on pressing the Space key:
					toggleSlideshowOnSpace: true,
					// Navigate the gallery by pressing left and right on the keyboard:
					enableKeyboardNavigation: true,
					// Close the gallery on pressing the ESC key:
					closeOnEscape: true,
					// Close the gallery when clicking on an empty slide area:
					closeOnSlideClick: true,
					// Close the gallery by swiping up or down:
					closeOnSwipeUpOrDown: true,
					// Emulate touch events on mouse-pointer devices such as desktop browsers:
					emulateTouchEvents: true,
					// Stop touch events from bubbling up to ancestor elements of the Gallery:
					stopTouchEventsPropagation: false,
					// Hide the page scrollbars: 
					hidePageScrollbars: true,
					// Stops any touches on the container from scrolling the page:
					disableScroll: true,
					// Carousel mode (shortcut for carousel specific options):
					carousel: true,
					// Allow continuous navigation, moving from last to first
					// and from first to last slide:
					continuous: false,
					// Remove elements outside of the preload range from the DOM:
					unloadElements: true,
					// Start with the automatic slideshow:
					startSlideshow: false,
					// Delay in milliseconds between slides for the automatic slideshow:
					slideshowInterval: 3000,
					// The starting index as integer.
					// Can also be an object of the given list,
					// or an equal object with the same url property:
					index: 0,
					// The number of elements to load around the current index:
					preloadRange: 2,
					// The transition speed between slide changes in milliseconds:
					transitionSpeed: 400,
					// The transition speed for automatic slide changes, set to an integer
					// greater 0 to override the default transition speed:
					slideshowTransitionSpeed: undefined,
					// The tag name, Id, element or querySelector of the indicator container:
					indicatorContainer: 'ol',
					// The class for the active indicator:
					activeIndicatorClass: 'active',
					// The list object property (or data attribute) with the thumbnail URL,
					// used as alternative to a thumbnail child element:
					thumbnailProperty: 'thumbnail',
					// Defines if the gallery indicators should display a thumbnail:
					thumbnailIndicators: true,
					// The event object for which the default action will be canceled
					// on Gallery initialization (e.g. the click event to open the Gallery):
					event: jQuery.proxy(this.expose_event,this),
					// Callback function executed when the Gallery is initialized.
					// Is called with the gallery instance as "this" object:
					onopen: jQuery.proxy(this.expose_onopen,this),
					// Callback function executed when the Gallery has been initialized
					// and the initialization transition has been completed.
					// Is called with the gallery instance as "this" object:
					onopened: jQuery.proxy(this.expose_onopened,this),
					// Callback function executed on slide change.
					// Is called with the gallery instance as "this" object and the
					// current index and slide as arguments:
					onslide: jQuery.proxy(this.expose_onslide,this),
					// Callback function executed after the slide change transition.
					// Is called with the gallery instance as "this" object and the
					// current index and slide as arguments:
					onslideend: jQuery.proxy(this.expose_onslideend,this),
					// Callback function executed on slide content load.
					// Is called with the gallery instance as "this" object and the
					// slide index and slide element as arguments:
					onslidecomplete: jQuery.proxy(this.expose_onslidecomplete,this),
					// Callback function executed when the Gallery is about to be closed.
					// Is called with the gallery instance as "this" object:
					onclose:jQuery.proxy(this.expose_onclose,this),
					// Callback function executed when the Gallery has been closed
					// and the closing transition has been completed.
					// Is called with the gallery instance as "this" object:
					onclosed: jQuery.proxy(this.expose_onclosed,this)
				};
				var $body = jQuery('body');
				if ($body.find('#blueimp-gallery').length == 0)
				{
					// Gallery Main DIV container
					var $expose_node = jQuery(document.createElement('div')).attr({id:"blueimp-gallery", class:"blueimp-gallery"});
					// Create Gallery DOM NODE
					$expose_node.append('<div class="slides"></div><h3 class="title"></h3><a class="prev">‹</a><a class="next">›</a><a class="close">×</a><a class="play-pause"></a><ol class="indicator"></ol>');
					// Append the gallery Node to DOM
					$body.append($expose_node);
				}
				
			},
			
			set_value:function (_value)
			{
				this._super.apply(this,arguments)
				var self=this;
				// If the media type is not supported do not bind the click handler
				if (_value && typeof _value.mime != 'undefined' && !_value.mime.match(/^(video|image|audio|media)\//,'ig'))
				{
					return;
				}
				if (typeof this.options.expose_view != 'undefined' && this.options.expose_view )
				{
					jQuery(this.node).on('click', function(){
						self._init_blueimp_gallery(_value);
					});
				}
			},
			
			_init_blueimp_gallery: function (_value)
			{
				var mediaContent = this.getMedia(_value);
				blueimp.Gallery(mediaContent, this.expose_options);
			},
			expose_event:function (event){
				console.log(event);
			},
			expose_onopen: function (event){},
			expose_onopened: function (event){},
			/**
			 * Trigger on slide left/right 
			 * @param {type} event
			 * @param {type} _callback
			 */
			expose_onslide: function (event){},
			expose_onslideend: function (event){},
			expose_onslidecomplete:function (event){},
			expose_onclose: function(event){},
			expose_onclosed: function (event){}
			
	});
}
