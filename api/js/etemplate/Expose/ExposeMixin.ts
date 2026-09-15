/**
 * EGroupware eTemplate2 - Mixin to add expose view of media and a gallery view
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh <hn[at]egroupware.org>
 * @author Nathan Gray <ng[at]egroupware.org>
 */

// Don't import this more than once
import "../../../../node_modules/blueimp-gallery/js/blueimp-gallery.min";
import {css, html, LitElement, render} from "lit";
import {property} from "lit/decorators/property.js";
import type {Et2Nextmatch} from "../Et2Nextmatch/Et2Nextmatch";
import {Et2Dialog} from "../Et2Dialog/Et2Dialog";
import {egw} from "../../jsapi/egw_global";

// How many additional slides to page in when the user reaches the end of what is loaded.
// Deliberately a local constant rather than the legacy dataview's ET2_DATAVIEW_STEPSIZE:
// this is a gallery pagination step, and importing it pulled the whole legacy dataview
// into every Expose consumer's module graph for one number.
const GALLERY_PAGE_SIZE = 50;

// Minimum data to qualify as an image and not cause errors
const IMAGE_DEFAULT = {
	title: egw.lang ? egw.lang ? egw.lang('loading') : "'loading'" : "'loading'",
	href: '',
	type: 'image/png',
	thumbnail: '',
	loading: true
};

// For filtering to only show things we can handle
export const MIME_REGEX = (navigator.userAgent.match(/(MSIE|Trident)/)) ?
	// IE only supports video/mp4 mime type
				   new RegExp(/(video\/mp4)|(image\/:*(?!tif|x-xcf|pdf))|(audio\/:*)/, 'i') :
				   new RegExp(/(video\/(mp4|ogg|webm))|(image\/:*(?!tif|x-xcf|pdf))|(audio\/:*)/, 'i');

const MIME_AUDIO_REGEX = new RegExp(/(audio\/:*)/, 'i');
// open office document mime type currently supported by webodf editor
const MIME_ODF_REGEX = new RegExp(/application\/vnd\.oasis\.opendocument\.text/);

type Constructor<T> = { new(...args : any[]) : T };

/**
 * Interface used to determine if widget can expose
 */
export interface ExposeValue
{
	path : any;
	mime : string,
	download_url? : string
	// File modification time
	mtime? : number
}

/**
 * Data to show a single slide
 */
export interface MediaValue
{
	// Label for the image, shown in top left
	title? : string,

	// URL to the large version of the image, or full version of file
	href : string,

	// Mime type
	type : string,

	// Smaller image (api/thumbnail.php) to show in indicator
	thumbnail? : string,

	// Url to download the file
	download_href? : string
}

export function ExposeMixin<B extends Constructor<LitElement>>(superclass : B)
{
	class ExposeMixinClass extends superclass
	{
		static get styles()
		{
			return [
				...super.styles,
				css`
				`
			];
		}

		/**
		 * Function to extract an image list
		 *
		 * "Normally" we'll try to pull a list of images from the nextmatch or show just the current widget,
		 * but if you know better you can provide a method to get the list.
		 */
		@property({type: Function})
		mediaContentFunction : any;

		// @ts-ignore
		private _gallery : blueimp.Gallery;

		// We store the wheel handler here so we can remove it properly
		private _wheelHandler : (ev: WheelEvent) => void = null;

		// The nextmatch the currently open gallery is syncing with.
		// Opening the gallery applies a mime filter, which reloads the grid and can replace
		// the very row this widget was rendered into - leaving this widget detached, with no
		// DOM ancestors left to find the nextmatch through.  The legacy widget tree survived
		// that; the composed DOM tree does not, so remember it for the life of the gallery.
		private _gallery_nextmatch : Et2Nextmatch | null = null;

		// Row count the currently open gallery was built from.
		// Opening the gallery kicks off a reload of the nextmatch with a mime filter, and a
		// reloading grid reports no total at all - expose_onopened() runs after that reload has
		// started, so asking the nextmatch again there gets 0 instead of the real count.
		private _gallery_total : number = 0;

		private __mediaContentFunction : Function | null;

		constructor(...args : any[])
		{
			super(...args);

			// bind handler context to instance - needed because _galleryTemplate() below is
			// rendered via the standalone lit `render()` into document.body (not this component's
			// own shadow DOM) WITHOUT a `{host: this}` option (see Et2Diff.ts for the pattern that
			// option enables), so Lit's own @click=${this.handleDownload} binding can't resolve the
			// right `this` on its own - found live 2026-09-15 (ralf: "the download button... does
			// NOT work at all"): clicking it threw `TypeError: this.getInstanceManager is not a
			// function`, silently, with no visible error (an uncaught exception in an event
			// listener) - handleDownload was the one handler this list forgot.
			const handlers = [
				"expose_onclick",
				"expose_onopen",
				"expose_onopened",
				"expose_onslide",
				"expose_onslideend",
				"expose_onslidecomplete",
				"expose_onclose",
				"expose_onclosed",
				"handleDownload"
			];

			for(let key of handlers)
			{
				this[key] = (<Function><unknown>this[key]).bind(this);
			}
		}

		connectedCallback()
		{
			super.connectedCallback();

			if(document.body.querySelector('#blueimp-gallery') == null)
			{
				// Create Gallery DOM structure
				render(this._galleryTemplate(), document.body);
			}
		}

		disconnectedCallback()
		{
			super.disconnectedCallback();
		}

		/**
		 * Get the info needed to determine if this widget's value allows it to participate in expose
		 * It needs to have a path, and we use mime to determine if it can expose
		 *
		 * It's also passed to getMedia() when we're not reading from a nextmatch
		 *
		 * @returns {ExposeValue}
		 */
		get exposeValue() : ExposeValue
		{
			//@ts-ignore value might not exist
			return this.value || null;
		}

		/**
		 * Get the info needed to show the given value as slide(s)
		 *
		 * _value is (usually?) pulled from egw.dataGetUIDdata()
		 *
		 * Override this
		 */
		getMedia(_value) : MediaValue[]
		{
			let mediaContent = [];
			if(_value)
			{
				mediaContent = [{
					title: _value.label,
					href: _value.download_url ? this._processUrl(_value.download_url) : this._processUrl(_value.path),
					type: _value.mime || (_value.type ? _value.type + "/*" : "")
				}];
				if(this.isExposable())
				{
					mediaContent[0].thumbnail = _value.thumbnail ? this._processUrl(_value.thumbnail) : mediaContent[0].href;
				}
				else
				{
					let fe = egw.file_editor_prefered_mimes(_value.mime);
					if(fe && fe.mime[_value.mime] && fe.mime[_value.mime].favIconUrl)
					{
						mediaContent[0].thumbnail = fe.mime[_value.mime].favIconUrl;
					}
				}
			}
			return mediaContent;
		}

		protected _processUrl(url)
		{
			// Already-absolute URLs (blob:, data:, or any other own URI scheme - eg. a client-side
			// object URL, never server-relative) must never get the app base URL prepended - only
			// checking against base_url itself (as below) misses every other absolute case, found
			// live 2026-08-27 via a blob: object URL for attachment viewing becoming
			// ".../egroupware/blob:https://..." once double-prefixed.
			if(/^[a-z][a-z0-9+.-]*:/i.test(url))
			{
				return url;
			}
			let base_url = egw.webserverUrl.match(/^\/ig/) ? egw(window).window.location.origin + egw.webserverUrl + '/' : egw.webserverUrl + '/';
			if(base_url && base_url != '/' && url.indexOf(base_url) != 0)
			{
				url = base_url + url;
			}
			return url;
		}

		/**
		 * Handle changes that have to happen based on changes to properties
		 *
		 */
		requestUpdate(name?, oldValue?, options?)
		{
			super.requestUpdate(name, oldValue, options);

			// if there's a value change, (de)bind the gallery
			if(name === "value")
			{
				this._bindGallery();
			}
		}

		/**
		 * Binds a click handler so if the user clicks, we'll initialize & show the gallery
		 *
		 * @protected
		 */
		protected _bindGallery()
		{
			// If the media type is not supported do not bind the click handler
			if(!this.isExposable())
			{
				this.classList.remove("et2_clickable");
				if(this._gallery)
				{
					this._gallery.close();
				}
				return;
			}

			if(!this._gallery)
			{
				this.classList.add("et2_clickable");

				// Normal click handler will handle it
			}
		}

		public isExposable() : boolean
		{
			if(!this.exposeValue || typeof this.exposeValue.mime !== "string")
			{
				return false
			}
			if(this.exposeValue.mime.match(MIME_REGEX) || this.exposeValue.mime.match(MIME_AUDIO_REGEX))
			{
				return true;
			}
			return false;
		}

		/**
		 * Just override the normal click handler
		 *
		 * @param {MouseEvent} _ev
		 * @returns {boolean}
		 */
		_handleClick(_ev : MouseEvent) : boolean
		{
			if((!this.isExposable() || this.expose_onclick(_ev)) && typeof super._handleClick === "function")
			{
				return super._handleClick(_ev);
			}
			return false;
		}

		get expose_options()
		{
			return {
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
				slideLoadingClass: 'loading',
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
				// The class to add for fullscreen button option
				fullscreenClass: 'fullscreen',
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
				closeOnSlideClick: false,
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
				//Request fullscreen on slide show
				toggleFullscreenOnSlideShow: true,
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
				//thumbnail with image tag
				thumbnailWithImgTag: true,
				// Callback function executed when the Gallery is initialized.
				// Is called with the gallery instance as "this" object:
				onopen: this.expose_onopen,
				// Callback function executed when the Gallery has been initialized
				// and the initialization transition has been completed.
				// Is called with the gallery instance as "this" object:
				onopened: this.expose_onopened,
				// Callback function executed on slide change.
				// Is called with the gallery instance as "this" object and the
				// current index and slide as arguments:
				onslide: this.expose_onslide,
				// Callback function executed after the slide change transition.
				// Is called with the gallery instance as "this" object and the
				// current index and slide as arguments:
				onslideend: this.expose_onslideend,
				//// Callback function executed on slide content load.
				// Is called with the gallery instance as "this" object and the
				// slide index and slide element as arguments:
				onslidecomplete: this.expose_onslidecomplete,
				//// Callback function executed when the Gallery is about to be closed.
				// Is called with the gallery instance as "this" object:
				onclose: this.expose_onclose,
				// Callback function executed when the Gallery has been closed
				// and the closing transition has been completed.
				// Is called with the gallery instance as "this" object:
				onclosed: this.expose_onclosed
			}
		}

		protected _galleryTemplate()
		{
			return html`
                <div id="blueimp-gallery" class="blueimp-gallery">
                    <div class="slides"></div>
                    <h3 class="title"></h3>
                    <a class="prev">‹</a>
                    <a class="next">›</a>
                    <a title="${egw().lang('Close')}" class="close"></a>
                    <a title="${egw().lang('Play/Pause')}" class="play-pause"></a>
                    <a title="${egw().lang('Fullscreen')}" class="fullscreen"></a>
                    <a title="${egw().lang('Save')}" class="download" @click=${this.handleDownload}></a>
                    <ol class="indicator"></ol>
                </div>
			`;
		}

		/**
		 * See if the current widget is in a nextmatch, as this allows us to display
		 * thumbnails underneath
		 *
		 * Walking the composed DOM tree rather than the widget tree: rows are rendered inside
		 * the nextmatch's shadow DOM and the widgets in them are hydrated without a widget-tree
		 * parent, so getParent() stops short.  Going up through shadow hosts also gets out of
		 * the exposable widget's own shadow root, which closest() cannot do.
		 *
		 * @param {et2_IExposable} widget
		 * @returns {Et2Nextmatch | null}
		 */
		protected find_nextmatch(widget) : Et2Nextmatch | null
		{
			let current : Node | null = <Node><unknown>widget;
			let nextmatch : Et2Nextmatch | null = null;
			while(nextmatch == null && current)
			{
				if(current instanceof HTMLElement && current.localName == "et2-nextmatch")
				{
					nextmatch = <Et2Nextmatch><unknown>current;
					break;
				}
				current = current.parentNode ?? (<ShadowRoot>current).host ?? null;
			}
			if(nextmatch == null && this._gallery_nextmatch?.isConnected)
			{
				// We were detached (see _gallery_nextmatch), but the gallery is still open
				// and still belongs to that nextmatch.
				nextmatch = this._gallery_nextmatch;
			}
			// No nextmatch
			// At the moment only filemanger nm would work
			// as gallery, thus we disable other nestmatches
			// to build up gallery but filemanager
			if(nextmatch == null || !nextmatch.dom_id?.match(/filemanager/i))
			{
				return null;
			}

			return nextmatch;
		};

		private _init_blueimp_gallery(event, _value)
		{
			// Image list
			let mediaContent = [];

			// We'll customise default options
			let options = this.expose_options;

			let nm = this.find_nextmatch(this);
			this._gallery_nextmatch = nm;
			this._gallery_total = nm?.totalCount ?? 0;
			if(typeof this.__mediaContentFunction == "function")
			{
				this.__mediaContentFunction(this);
			}
			else if(nm && !this._is_target_indepth(nm, event.target))
			{
				// Get the row that was clicked, find its index in the list
				let current_entry = nm.getRowByNode(<Node>event.target);

				// But before it goes, we'll pull everything we can
				this.read_from_nextmatch(nm, mediaContent);
				// find current_entry in array and set it's array-index
				for(let i = 0; i < mediaContent.length; i++)
				{
					if('filemanager::' + mediaContent[i].path == current_entry?.id)
					{
						options.index = i;
						break;
					}
				}

				// This will trigger nm to refresh and get just the ones we can handle
				// but it might take a while, so do it later - make sure our current
				// one is loaded first.
				window.setTimeout(function()
				{
					nm.applyFilters({col_filter: {mime: '/' + MIME_REGEX.source + '/'}});
				}, 1);
			}
			else
			{
				// Try for all exposable of the same type in the parent widget
				try
				{
					let others = (this.getParent().closest("[exposable]") || this.getParent().getDOMNode()).querySelectorAll(this.localName);
					//might be in elements shadow root e.g. Links in LinksTab of edit windows
					if(!others || others.length === 0){
						others = this.getParent().getDOMNode().shadowRoot.querySelectorAll(this.localName)
					}
					others.forEach((exposable, index) =>
					{
						if(exposable === this)
						{
							options.index = mediaContent.length;
						}
						if(exposable.isExposable())
						{
							mediaContent.push(...exposable.getMedia(Object.assign({}, IMAGE_DEFAULT, exposable.exposeValue)));
						}
					});
					if(!others || others.length == 0)
					{
						mediaContent = this.getMedia(_value);
					}
				}
				catch(e)
				{
					// Well, that didn't work.  Just the one then.
					// @ts-ignore
					mediaContent = this.getMedia(_value);
				}
				// Do not show thumbnail indicator on single expose view
				options.thumbnailIndicators = (mediaContent.length > 1);
				if(!options.thumbnailIndicators)
				{
					options.indicatorContainer = 'nope';
				}
			}

			// @ts-ignore
			this._gallery = new blueimp.Gallery(mediaContent, options);
			// @ts-ignore
			document.body.querySelector("#blueimp-gallery").gallery = this._gallery
		}

		/**
		 * Read images out of the data for the nextmatch
		 *
		 * @param {Et2Nextmatch} nm
		 * @param {Object[]} images
		 * @param {number} start_at
		 * @returns {undefined}
		 */
		protected read_from_nextmatch(nm : Et2Nextmatch, images, start_at?)
		{
			if(!start_at)
			{
				start_at = 0;
			}
			let image_index = start_at;
			// Row index -> datastore uid, with a hole for every row not (yet) loaded
			const row_ids = nm.getLoadedRowIds();
			let stop = row_ids.length - 1;

			for(let i = start_at; i <= stop; i++)
			{
				let uid = row_ids[i];
				if(!uid)
				{
					// Returning instead of using IMAGE_DEFAULT means we stop as
					// soon as a hole is found, instead of getting everything that is
					// available.  The gallery can't fill in the holes.
					images[image_index++] = IMAGE_DEFAULT;
					continue;
				}
				let data = egw.dataGetUIDdata(uid);
				if(typeof data?.data?.mime === "string" && MIME_REGEX.test(data.data.mime))
				{
					let media = this.getMedia(data.data);
					images[image_index++] = Object.assign({}, data.data, media[0]);
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
		protected set_slide(index, image)
		{
			let active = (index == this._gallery.index);

			// Pad with blanks until length is right
			while(index > this._gallery.getNumber())
			{
				this._gallery.add([Object.assign({}, IMAGE_DEFAULT)]);
			}

			// Don't bother with adding a default, we just did that
			if(image.loading)
			{
				//Add load class if it's really a slide with error
				if(this._gallery.slidesContainer.find('[data-index="' + index + '"]').hasClass(this._gallery.options.slideErrorClass))
				{
					this._gallery.slides[index].classList.add(this._gallery.options.slideLoadingClass)
					this._gallery.slides[index].classList.remove(this._gallery.options.slideErrorClass);
				}
				return;
			}
			// Remove the loading class if the slide is loaded
			else if(this._gallery.slides[index])
			{
				this._gallery.slides[index].classList.remove(this._gallery.options.slideLoadingClass);
			}

			// Just use add to let gallery create everything it needs
			let new_index = this._gallery.num;
			this._gallery.add([image]);

			// Move it to where we want it.
			// Gallery uses arrays and indexes and has several internal variables
			// that need to be updated.
			//
			// list
			this._gallery.list[index] = this._gallery.list[new_index];
			this._gallery.list.splice(new_index, 1);

			// indicators & slides
			let dom_nodes = ['indicators', 'slides'];
			for(let i in dom_nodes)
			{
				let var_name = dom_nodes[i];
				// Remove old one from DOM
				this._gallery[var_name][index].remove();
				// Move new one into it's place in gallery
				this._gallery[var_name][index] = this._gallery[var_name][new_index];
				// Move into place in DOM
				let node = this._gallery[var_name][index];
				node.setAttribute('data-index', index)
				if(this._gallery.slides[(index + 1)])
				{
					this._gallery.slidesContainer[0].insertBefore(this._gallery.slides[(index + 1)], undefined);
				}
				if(active)
				{
					node.classList.add(this._gallery.options.activeIndicatorClass);
				}
				this._gallery[var_name].splice(new_index, 1);
			}
			if(active)
			{
				this._gallery.setActiveIndicator(this._gallery.indicators[index])
			}

			// positions
			this._gallery.positions[index] = active ? 0 : (index > this._gallery.index ? this._gallery.slideWidth : -this._gallery.slideWidth);
			this._gallery.positions.splice(new_index, 1);

			// elements - removing will allow to re-do the slide
			if(this._gallery.elements[index])
			{
				delete this._gallery.elements[index];
				this._gallery.loadElement(index);
			}

			// Remove the one we just added
			this._gallery.num -= 1;
		};

		/**
		 * audio player expose
		 * @param _value
		 * @private
		 */
		private _audio_player(_value)
		{
			let button = [
				{
					"button_id": 1,
					"label": egw.lang ? egw.lang("close") : "close",
					id: '1',
					image: 'cancel',
					default: true
				}
			];

			let mediaContent = this.getMedia(_value)[0];
			let dialog = new Et2Dialog();
			dialog.transformAttributes({
				callback: function(_btn, value)
				{
					if(_btn == Et2Dialog.OK_BUTTON)
					{

					}
				},
				beforeClose: function()
				{

				},
				title: mediaContent.title,
				buttons: button,
				minWidth: 350,
				minHeight: 200,
				isModal: false,
				position: "right bottom,right-50 bottom-10",
				value: {
					content: {
						src: mediaContent.download_href || mediaContent.href
					}
				},
				resizable: false,
				template: egw.webserverUrl + '/api/templates/default/audio_player.xet',
				dialogClass: "audio_player"
			});
			// @ts-ignore
			document.body.appendChild(dialog);
		}


		/**
		 * Check if clicked target from nm is in depth
		 *
		 * "In depth" means the row lives in an expanded child grid rather than in the
		 * nextmatch's own top-level row list.  Those rows are a different result set, so the
		 * gallery must not treat them as part of the list it pages through.
		 *
		 *  @param nm nextmatch widget
		 *  @param target selected target dom node, defaults to this widget
		 *
		 *  @return {boolean} returns false if target is not in depth otherwise True
		 */
		private _is_target_indepth(nm : Et2Nextmatch, target? : Node)
		{
			if(!nm)
			{
				return false;
			}
			return (nm.getRowByNode(target ?? <Node><unknown>this)?.depth ?? 0) > 0;
		}

		protected expose_onclick(event : MouseEvent)
		{
			// Do not trigger expose view if one of the operator keys are held
			if(event.altKey || event.ctrlKey || event.shiftKey || event.metaKey)
			{
				return;
			}

			event.stopImmediatePropagation();

			if(this.exposeValue.mime.match(MIME_REGEX) && !this.exposeValue.mime.match(MIME_AUDIO_REGEX))
			{
				this._init_blueimp_gallery(event, this.exposeValue);
				return false;
			}
			else if(this.exposeValue.mime.match(MIME_AUDIO_REGEX))
			{
				this._audio_player(this.exposeValue);
				return false;
			}

			return true;
		}

		protected expose_onopen() {}

		protected expose_onopened()
		{
			// Check to see if we're in a nextmatch, do magic
			let nm = this.find_nextmatch(this);
			let self = this;
			if(nm)
			{
				// Add scrolling to the indicator list.
				// 0 means the grid is mid-reload and does not know its total right now (see
				// _gallery_total), not that there is nothing to page through.
				let total_count = nm.totalCount || this._gallery_total;
				if(total_count >= this._gallery.num)
				{
					let $indicator = this._gallery.container.find('.indicator');
					$indicator
						.addClass('paginating');
					/*
						.swipe(function(event, direction, distance)
						{
							// @ts-ignore
							if(direction == jQuery.fn.swipe.directions.LEFT)
							{
								distance *= -1;
							}
							// @ts-ignore
							else if(direction == jQuery.fn.swipe.directions.RIGHT)
							{
								// OK.
							}
							else
							{
								return;
							}
							jQuery(this).css('left', Math.min(0, parseInt(jQuery(this).css('left')) - (distance * 30)) + 'px');
						});

					 */

					// Store the handler for removal later
					this._wheelHandler = (event: WheelEvent) =>
					{
						// Convert delta as it was in original code
						let _delta = event.deltaY / 120;
						let delta = _delta;

						let g_width = parseInt(getComputedStyle(this._gallery.container[0]).width);
						let width = parseInt(getComputedStyle(this._gallery.indicatorContainer[0]).width);
						let left = parseInt(getComputedStyle(this._gallery.indicatorContainer[0]).left);
						if(delta > 0 && left > g_width / 2)
						{
							return;
						}

						// Reload next pictures into the gallery by scrolling on thumbnails
						if(delta < 0 && width + left < g_width)
						{
							let nextIndex = this._gallery.indicatorContainer.find('[title="loading"]')[0];
							if(nextIndex)
							{
								self.expose_onslideend(this._gallery, nextIndex.dataset.index - 1);
							}
							return;
						}
						// Move it about 5 indicators
						let i_width = parseInt(getComputedStyle(this._gallery.activeIndicator[0]).width);
						jQuery($indicator[0]).css('left', (left - (-delta * i_width * 5)) + 'px');

						event.preventDefault();
					};

					// Native addEventListener for 'wheel'
					$indicator[0].addEventListener('wheel', this._wheelHandler);
				}
			}
		}

		protected expose_onslide(index, slide)
		{
			//todo
			//if (typeof this._super == 'undefined') return;
			// First let parent try
			let nm = this.find_nextmatch(this);
			if(nm)
			{
				// See if we need to move the indicator
				let indicator = this._gallery.container.find('.indicator');
				let current = jQuery('.active', indicator).position();

				if(current)
				{
					let width = parseInt(window.getComputedStyle(this._gallery.container[0]).width)
					//	indicator.animate({left: (width / 2) - current.left}, 10);
				}
			}
		}

		protected async expose_onslideend(index, slide)
		{
			// Check to see if we're in a nextmatch, do magic
			let nm = this.find_nextmatch(this);
			if(nm && !nm.isLoading)
			{
				// Check to see if we're near the end, or maybe some pagination
				// would be good.
				let total_count = nm.totalCount;

				// Already at the end, don't bother
				if(index == total_count - 1 || index == 0)
				{
					return;
				}

				// Try to determine direction from state of next & previous slides
				let direction = 1;
				for(let i in this._gallery.elements)
				{
					// Loading or error
					if(this._gallery.elements[i] == 1 || this._gallery.elements[i] == 3 || this._gallery.list[i].loading)
					{
						direction = i >= index ? 1 : -1;
						break;
					}
				}

				if(!this._gallery.list[index + direction] || this._gallery.list[index + direction].loading ||
					total_count > this._gallery.getNumber() && index + GALLERY_PAGE_SIZE > this._gallery.getNumber())
				{
					// This will get the next batch of rows
					let start = Math.max(0, direction > 0 ? index : index - GALLERY_PAGE_SIZE);
					let end = Math.min(total_count - 1, start + GALLERY_PAGE_SIZE);
					// Unlike the legacy grid callback this actually waits for the rows, so what
					// we read below is the fetched page and not just whatever was already cached.
					await nm.loadRowRange(start, end);
					let images = [];
					this.read_from_nextmatch(nm, images, start);

					// Gallery always adds to the end, causing problems with pagination
					for(let i in images)
					{
						this.set_slide(parseInt(i), images[i]);
					}
				}
			}

		}

		readonly URL_REGEXP = /url\("([^)]+)"\)/;
		protected expose_onslidecomplete()
		{
			const indicators = this._gallery.container.find('ol.indicator')[0].querySelectorAll('li');
			indicators.forEach(indicator => {
				if (indicator.style.backgroundImage && indicator.style.backgroundImage !== 'none')
				{
					const img = indicator.ownerDocument.createElement('img');
					img.src = indicator.style.backgroundImage.replace(this.URL_REGEXP, '$1');
					indicator.appendChild(img);
					indicator.style.backgroundImage = 'none';
				}
			});
		}

		protected expose_onclose()
		{
			// Check to see if we're in a nextmatch, remove magic
			let nm = this.find_nextmatch(this);

			// Unregister the wheel handler to prevent leaks
			let $indicator = this._gallery.container.find('.indicator');
			if(this._wheelHandler)
			{
				$indicator[0].removeEventListener('wheel', this._wheelHandler);
				this._wheelHandler = null;
			}

			// redefine essential removeClass method in case the active element has no longer contains it.
			if (!this._gallery.activeIndicator.removeClass)
			{
				this._gallery.activeIndicator.removeClass = blueimp.helper.prototype.removeClass;
			}
			if(nm && !this._is_target_indepth(nm))
			{
				// Remove scrolling from thumbnails
				$indicator
					.removeClass('paginating')
					.off('swipe');

				// Remove applied mime filter
				nm.applyFilters({col_filter: {mime: ''}});
			}
		}

		protected expose_onclosed()
		{
			this._gallery_nextmatch = null;
			this._gallery_total = 0;
		}

		protected handleDownload(e)
		{
			const gallery = document.body.querySelector("#blueimp-gallery").gallery;
			const index = gallery.getIndex();
			const data = gallery.list[index] ?? {};
			this.getInstanceManager().download(data.download_href ?? data.download_url ?? data.href);
		}
	}

	return ExposeMixinClass as unknown as Constructor<ExposeMixinClass> & B;
}