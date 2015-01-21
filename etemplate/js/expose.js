/**
 * EGroupware eTemplate2 - JS object implementing expose view of media and a gallery view
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Hadi Nategh <hn[at]stylite.de>
 * @copyright Stylite AG
 * @version $Id$
 */

/*egw:uses
	jquery.jquery;
	/phpgwapi/js/jquery/blueimp/js/jquery.blueimp-gallery.min.js;
*/

/**
 * Interface all exposed widget must support in order to getMedia for the blueimp Gallery.
 */
var et2_IExposable = new Interface(
{
	/**
	 * get media an array of media objects to pass to blueimp Gallery
	 * @param {array} _attrs
	 */
	getMedia: function(_attrs) {}
});

/**
 * This function extends the given widget with blueimp gallery plugin
 *
 * @param {type} widget
 * @returns {widget}
 */
function expose (widget)
{
	// Common expose functions
	var THUMBNAIL_MAX = 100;

	// Minimum data to qualify as an image and not cause errors
	var IMAGE_DEFAULT = {
		title: egw.lang('loading'),
		href: '',
		type: 'image/png',
		thumbnail: '',
		loading: true
	};

	// For filtering to only show things we can handle
	var mime_regex = new RegExp(/video\/|image\//);
	
	// Only one gallery
	var gallery = null;

	/**
	 * See if the current widget is in a nextmatch, as this allows us to display
	 * thumbnails underneath
	 * 
	 * @param {et2_IExposable} widget
	 * @returns {et2_nextmatch | null}
	 */
	var find_nextmatch = function(widget)
	{
		var current = widget;
		var nextmatch = null;
		while(nextmatch == null && current)
		{
			current = current.getParent();
			if(typeof current !='undefined' && current.instanceOf(et2_nextmatch))
			{
				nextmatch = current;
			}
		}
		// No nextmatch, or nextmatch not quite ready
		if(nextmatch == null || nextmatch.controller == null) return null;

		return nextmatch;
	};

	/**
	 * Read images out of the data for the nextmatch
	 * 
	 * @param {et2_nextmatch} nm
	 * @param {Object[]} images
	 * @returns {undefined}
	 */
	var read_from_nextmatch = function(nm, images, start_at)
	{
		if(!start_at) start_at = 0;
		var stop = Math.max.apply(null,Object.keys(nm.controller._indexMap));

		for(var i = start_at; i <= stop; i++)
		{
			if(!nm.controller._indexMap[i] || !nm.controller._indexMap[i].uid)
			{
				// Returning instead of using IMAGE_DEFAULT means we stop as
				// soon as a hole is found, instead of getting everything that is
				// available.  The gallery can't fill in the holes.
				images[i] = IMAGE_DEFAULT;
				continue;
			}
			var uid = nm.controller._indexMap[i].uid;
			if(!uid) continue;
			var data = egw.dataGetUIDdata(uid);
			if(data && data.data && data.data.mime && mime_regex.test(data.data.mime))
			{
				var media = this.getMedia(data.data);
				images[i] = jQuery.extend({}, data.data, media[0]);
			}
		}
	}

	/**
	 * Set a particular index/image in the gallery instead of just appending
	 * it to the end
	 *
	 * @param {integer} index
	 * @param {Object} image
	 * @returns {undefined}
	 */
	var set_slide = function(index, image)
	{
		var active = (index == gallery.index);

		// Pad with blanks until length is right
		while(index > gallery.getNumber())
		{
			gallery.add([jQuery.extend({}, IMAGE_DEFAULT)]);
		}

		// Don't bother with adding a default, we just did that
		if(image.loading)
		{
			$j(gallery.slides[index])
				.addClass(gallery.options.slideLoadingClass)
				.removeClass(gallery.options.slideErrorClass);
			return;
		}

		// Just use add to let gallery create everything it needs
		var new_index = gallery.num;
		gallery.add([image]);

		// Move it to where we want it.
		// Gallery uses arrays and indexes and has several internal variables
		// that need to be updated.
		// 
		// list
		gallery.list[index] = gallery.list[new_index];
		gallery.list.splice(new_index,1)

		// indicators & slides
		var dom_nodes = ['indicators','slides'];
		for(var i in dom_nodes)
		{
			var var_name = dom_nodes[i];
			// Remove old one from DOM
			$j(gallery[var_name][index]).remove();
			// Move new one into it's place in gallery
			gallery[var_name][index] = gallery[var_name][new_index];
			// Move into place in DOM
			var node = $j(gallery[var_name][index]);
			node.attr('data-index', index)
				.insertAfter($j("[data-index='"+(index-1)+"']",node.parent()));
			if(active) node.addClass(gallery.options.activeIndicatorClass);
			gallery[var_name].splice(new_index,1);
		}
		if(active)
		{
			gallery.activeIndicator = $j(gallery.indicators[index]);
		}
		
		// positions
		gallery.positions[index] = active ? 0 : (index > gallery.index ? gallery.slideWidth : -gallery.slideWidth);
		gallery.positions.splice(new_index,1);

		// elements - removing will allow to re-do the slide
		if(gallery.elements[index])
		{
			delete gallery.elements[index];
			gallery.loadElement(index);
		}

		// Remove the one we just added
		gallery.num -= 1;
	};

	return widget.extend([et2_IExposable],{
		
			/**
			 * Initialize the expose media gallery
			 */
			init: function()
			{
				this._super.apply(this, arguments);

				var self=this;
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
					//Hide controls when the slideshow is playing
					hideControlsOnSlideshow: true,
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
					onslide: function(index, slide) {
						// Call our onslide method, and include gallery as an attribute
						self.expose_onslide.apply(self, [this, index,slide])
					},
					// Callback function executed after the slide change transition.
					// Is called with the gallery instance as "this" object and the
					// current index and slide as arguments:
					onslideend: function(index, slide) {
						// Call our onslide method, and include gallery as an attribute
						self.expose_onslideend.apply(self, [this, index,slide])
					},
					//// Callback function executed on slide content load.
					// Is called with the gallery instance as "this" object and the
					// slide index and slide element as arguments:
					onslidecomplete: function(index, slide) {
						// Call our onslide method, and include gallery as an attribute
						self.expose_onslidecomplete.apply(self, [this, index,slide])
					},
					//// Callback function executed when the Gallery is about to be closed.
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
				// Do not run set value of expose if expose_view is not set
				// it causes a wired error on nested image widgets which
				// seems the expose is not its child widget
				if (!this.options.expose_view )
				{
					return;
				}
				this._super.apply(this,arguments);

				var self=this;
				// If the media type is not supported do not bind the click handler
				if (_value && typeof _value.mime != 'undefined' && !_value.mime.match(mime_regex,'ig'))
				{
					return;
				}
				if (typeof this.options.expose_view != 'undefined' && this.options.expose_view )
				{
					jQuery(this.node).on('click', function(event){
						self._init_blueimp_gallery(event, _value);
					}).addClass('et2_clickable');
				}
			},

			_init_blueimp_gallery: function (event, _value)
			{
				var mediaContent = [];
				var nm = find_nextmatch(this);
				var current_index = 0;
				if(nm)
				{
					// Get the row that was clicked, find its index in the list
					var current_entry = nm.controller.getRowByNode(event.target);
					current_index = current_entry.idx || 0;

					// But before it goes, we'll pull everything we can
					read_from_nextmatch.call(this, nm, mediaContent);

					// This will trigger nm to refresh and get just the ones we can handle
					// but it might take a while, so do it later - make sure our current
					// one is loaded first.
					window.setTimeout(function() {
						nm.applyFilters({col_filter: {mime: '/'+mime_regex.source+'/'}});
					},1);
				}
				else
				{
					mediaContent = this.getMedia(_value);
				}
				this.expose_options.index = Math.min(current_index, mediaContent.length-1);
				gallery = blueimp.Gallery(mediaContent, this.expose_options);
			},
			expose_onopen: function (event){},
			expose_onopened: function (event){
				// Check to see if we're in a nextmatch, do magic
				var nm = find_nextmatch(this);
				if(nm)
				{
					// Add scrolling to the indicator list
					var total_count = nm.controller._grid.getTotalCount();
					if(total_count >= gallery.num)
					{
						gallery.container.find('.indicator').off()
							.addClass('paginating')
							.mousewheel(function(event, delta) {
								if(delta > 0 && parseInt($j(this).css('left')) > gallery.container.width() / 2) return;
								// Move it about 5 indicators
								$j(this).css('left',parseInt($j(this).css('left'))-(-delta*gallery.activeIndicator.width()*5)+'px');
								event.preventDefault();
							})
							.swipe(function(event, direction, distance) {
								if(direction == jQuery.fn.swipe.directions.LEFT)
								{
									distance *= -1;
								}
								else if(direction == jQuery.fn.swipe.directions.RIGHT)
								{
									// OK.
								}
								else
								{
									return;
								}
								$j(this).css('left',min(0,parseInt($j(this).css('left'))-(distance*30))+'px');
							});
					}
				}
			},
			/**
			 * Trigger on slide left/right
			 * @param {Gallery} gallery
			 * @param {integer} index
			 * @param {DOMNode} slide
			 */
			expose_onslide: function (gallery, index, slide){
				// First let parent try
				this._super.apply(this, arguments);

				// Check to see if we're in a nextmatch, do magic
				var nm = find_nextmatch(this);
				if(nm)
				{
					// See if we need to move the indicator
					var indicator = gallery.container.find('.indicator');
					var current = $j('.active',indicator).position();
					if(current)
					{
						indicator.animate({left: (gallery.container.width() / 2)-current.left});
					}
				}
			},
			expose_onslideend: function (gallery, index, slide){
				// Check to see if we're in a nextmatch, do magic
				var nm = find_nextmatch(this);
				if(nm)
				{
					// Check to see if we're near the end, or maybe some pagination
					// would be good.
					var total_count = nm.controller._grid.getTotalCount();
					
					// Already at the end, don't bother
					if(index == total_count) return;

					// Try to determine direction from state of next & previous slides
					var direction = 1;
					for(var i in gallery.elements)
					{
						// Loading or error
						if(gallery.elements[i] == 1 || gallery.elements[i] == 3 || gallery.list[i].loading)
						{
							direction = i >= index ? 1 : -1;
							break;
						}
					}
					
					if(!gallery.list[index+direction] || gallery.list[index+direction].loading ||
						total_count > gallery.getNumber() && index + ET2_DATAVIEW_STEPSIZE > gallery.getNumber())
					{
						// This will get the next batch of rows
						var start = Math.max(0, direction > 0 ? index : index - ET2_DATAVIEW_STEPSIZE);
						var end = Math.min(total_count - 1, start + ET2_DATAVIEW_STEPSIZE);
						nm.controller._gridCallback(start, end);
						var images = [];
						read_from_nextmatch.call(this, nm, images, start);

						// Gallery always adds to the end, causing problems with pagination
						for(var i in images)
						{
							//if(i == index || i < gallery.num) continue;
							set_slide(i, images[i]);
							//gallery.add([images[i]]);
						}
					}
				}
			},
			expose_onslidecomplete:function (gallery, index, slide){},
			expose_onclose: function(event){
				// Check to see if we're in a nextmatch, remove magic
				var nm = find_nextmatch(this);
				if(nm)
				{
					// Remove scrolling from thumbnails
					gallery.container.find('.indicator')
						.removeClass('paginating')
						.off('mousewheel')
						.off('swipe');

					// Remove applied mime filter
					nm.applyFilters({col_filter: {mime: ''}});
				}
			},
			expose_onclosed: function (event){}

	});
}
