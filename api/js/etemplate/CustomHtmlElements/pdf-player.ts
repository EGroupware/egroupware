/**
 * EGroupware Custom Html Elements - pdf player Web Components
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh <hn[at]egroupware.org>
 * @copyright EGroupware GmbH
 */

import * as pdfjs from "pdfjs-dist";

/*
	This web component allows to display and play pdf file like a video player widget/element. Its attributes and
	methodes are mostley identical as video html. No controls attribute supported yet.
*/

/**
 * pdf.js only ships its worker as a native ES module (pdf.worker.mjs) - no classic-script build
 * exists any more (checked: even the "legacy" build variant is .mjs-only). Module workers (and
 * pdf.js's own "fake worker" fallback, which dynamically import()s the same URL on the main
 * thread) both require the server to serve it with a JavaScript-family Content-Type - many web
 * servers' default mime.types have no `.mjs` mapping and serve it as application/octet-stream
 * instead, which every browser correctly refuses to execute as a module
 * ("Failed to fetch dynamically imported module"/"Failed to resolve module specifier").
 *
 * Since EGroupware ships to many self-hosted installs whose web server config we don't control,
 * fixing this per-server (nginx/Apache mime-type config) isn't a viable general fix - confirmed
 * live against boulder.egroupware.org 2026-09-19. Instead, fetch the worker's source as plain text
 * (fetch() doesn't care about Content-Type) and hand the browser an explicitly-typed Blob URL,
 * which carries its own declared type and is trusted regardless of what the server said. Safe
 * because pdf.worker.mjs is a single self-contained bundle with no external imports of its own
 * (verified) - nothing else needs to resolve relative to it.
 *
 * Cached at module scope (not per pdf-player instance) since GlobalWorkerOptions.workerSrc is
 * itself a single global pdf.js setting, and re-fetching/re-blobbing the ~2MB worker for every
 * <pdf-player> would be wasteful.
 */
let workerBlobUrl : Promise<string> = null;

function ensureWorkerSrc() : Promise<string>
{
	if (!workerBlobUrl)
	{
		// egw.webserverUrl is this codebase's standard way to build an app-root-relative absolute
		// URL regardless of where EGroupware is installed (see egw_images.ts/egw_files.ts/
		// egw_app.ts for the same pattern) - fetch() needs a real URL, not a bare/page-relative path.
		const url = egw.webserverUrl+'/node_modules/pdfjs-dist/build/pdf.worker.mjs';
		workerBlobUrl = fetch(url)
			.then(response => response.text())
			.then(source => URL.createObjectURL(new Blob([source], {type: 'text/javascript'})))
			.then(blobUrl =>
			{
				pdfjs.GlobalWorkerOptions.workerSrc = blobUrl;
				return blobUrl;
			});
	}
	return workerBlobUrl;
}

/**
 *
 */
type PdfViewArray = {
	pdf: any,
	zoom: number,
	currentPage: number
};

// Create a class for the element
class pdf_player extends HTMLElement {

	/**
	 * shadow dom container
	 * @private
	 */
	private readonly _shadow = null;
	/**
	 * wrapper container holds canvas
	 * @private
	 */
	private readonly _wrapper : HTMLDivElement;
	/**
	 * Canvas element to render pdf
	 * @private
	 */
	private _canvas : HTMLCanvasElement;
	/**
	 * Styling contianer
	 * @private
	 */
	private readonly _style : HTMLStyleElement;
	/**
	 * keeps duration time internally
	 * @private
	 */
	private _duration : number = 0;
	/**
	 * keeps currentTime internally
	 * @private
	 */
	private _currentTime : number = 0;
	/**
	 * Keeps playing state internally
	 * @private
	 */
	private __playing: boolean = false;
	/**
	 * keeps playing interval id
	 * @private
	 */
	private __playingInterval : number = 0;
	/**
	 * keeps play back rate
	 * @private
	 */
	private _playBackRate : number = 1000;
	/**
	 * keeps ended state of playing pdf
	 * @private
	 */
	private _ended : boolean = false;
	/**
	 * keep paused state
	 * @private
	 */
	private _paused : boolean = false;
	/**
	 * keeps pdf doc states
	 * @private
	 */
	private __pdfViewState : PdfViewArray = {
		pdf: null,
		currentPage: 1,
		zoom: 1
	};

	constructor() {

		super();

		// Create a shadow root
		this._shadow = this.attachShadow({mode: 'open'});

		// Create wrapper
		this._wrapper = document.createElement('div');
		this._wrapper.setAttribute('class','wrapper');

		// Create some CSS to apply to the shadow dom
		this._style = document.createElement('style');

		this._style.textContent = '.wrapper {' +
			'width: 100%;' +
			'height: auto;' +
			'display: block;' +
			'}'+
			'.wrapper canvas {' +
			'width: 100%;'+
			'height: auto;'+
			'}';

		// attach to the shadow dom
		this._shadow.appendChild(this._style);
		this._shadow.appendChild(this._wrapper);
	}

	/**
	 * set observable attributes
	 * @return {string[]}
	 */
	static get observedAttributes() {
		return ['src', 'type'];
	}

	/**
	 * Gets called on observable attributes changes
	 * @param name attribute name
	 * @param _
	 * @param newVal new value
	 */
	attributeChangedCallback(name, _, newVal) {
		switch(name)
		{
			case 'src':
				this.__buildPDFView(newVal);
				break;
			case 'type':
				// do nothing
				break;
		}
	}

	/**
	 * init/update pdf tag
	 * @param _value
	 * @private
	 */
	private __buildPDFView(_value)
	{
		this.__pdfViewState.pdf?.destroy();
		this.__pdfViewState.pdf = null;
		this._canvas?.remove();
		this._canvas = document.createElement('canvas');
		this._wrapper.appendChild(this._canvas);
		// ensureWorkerSrc() resolves once (cached across every pdf-player instance/load) - see its
		// own docblock for why this can't just be a synchronous GlobalWorkerOptions assignment.
		ensureWorkerSrc().then(() =>
		{
			let longTask = pdfjs.getDocument(_value);
			return longTask.promise;
		}).then((pdf) => {

			this.__pdfViewState.pdf = pdf;
			this._duration = this.__pdfViewState.pdf.numPages;

			// initiate the pdf file viewer for the first time after loading
			this.__render(1).then(_ =>{
				this.__pushEvent('loadedmetadata');
			});
		}).catch((error) =>
		{
			egw.message(error.message, 'error');
		});
	}

	/**
	 * Render given page from pdf into the canvas container
	 *
	 * @param _page
	 * @private
	 */
	private __render(_page)
	{
		if (!this.__pdfViewState.pdf) return;
		let p = _page || this.__pdfViewState.currentPage;
		let self = this;
		return this.__pdfViewState.pdf.getPage(p).then((page) => {
			let canvasContext = self._canvas.getContext('2d');
			let viewport = page.getViewport({scale:self.__pdfViewState.zoom});
			self._canvas.width = viewport.width;
			self._canvas.height = viewport.height;

			page.render({
				canvasContext: canvasContext,
				viewport: viewport
			});
		});
	}

	/**
	 * Creates event and dispatches it
	 * @param _name
	 */
	private __pushEvent(_name: string)
	{
		let event = document.createEvent("Event");
		event.initEvent(_name, true, true);
		this.dispatchEvent(event);
	}

	/**************************** PUBLIC ATTRIBUTES & METHODES *************************************************/

	/****************************** ATTRIBUTES **************************************/

	/**
	 * currently set src, as passed to set src() - not reflected to the 'src' attribute
	 * @private
	 */
	private _src : any = null;

	/**
	 * set src
	 * @param _value
	 */
	set src(_value)
	{
		Array.from(this._wrapper.children).forEach(_ch=>{
			_ch.remove();
		});
		this._src = _value;
		this.__buildPDFView(_value);
	}

	/**
	 * get src
	 * @return string returns comma separated sources
	 */
	get src ()
	{
		return this._src;
	}

	/**
	 * currentTime
	 * @param _time
	 */
	set currentTime(_time : number)
	{
		let time = Math.floor(_time < 1 ? 1 : _time);

		if (time>this._duration)
		{
			// set ended state to true as it's the last page of pdf
			this._ended = true;
			// don't go further because it's litterally the last page
			return;
		}

		// set ended state to false as it's not the end of the pdf
		this._ended = false;
		this._currentTime = time;
		this.__pdfViewState.currentPage = time;
		this.__render(time);
	}

	/**
	 * get currentTime
	 * @return {number}
	 */
	get currentTime()
	{
		return this._currentTime;
	}

	/**
	 * get duration time
	 */
	get duration()
	{
		return this._duration;
	}

	/**
	 * get paused attribute
	 */
	get paused()
	{
		return this._paused;
	}

	/**
	 * set muted attribute
	 * @param _value
	 */
	set muted(_value: boolean)
	{
		return;
	}

	/**
	 * get muted attribute
	 */
	get muted()
	{
		return true;
	}

	set ended (_value : boolean)
	{
		this._ended = _value;
	}

	/**
	 * get ended attribute
	 */
	get ended()
	{
		return this._ended;
	}

	/**
	 * set playbackRate
	 * @param _value
	 */
	set playbackRate(_value: number)
	{
		this._playBackRate = _value*1000;
	}

	/**
	 * get playbackRate
	 */
	get playbackRate()
	{
		return this._playBackRate;
	}

	/**
	 * set volume
	 */
	set volume(_value: number)
	{
		return;
	}

	/**
	 * get volume
	 */
	get volume()
	{
		return 0;
	}


	/************************************************* METHODES ******************************************/

	/**
	 * Play
	 */
	play()
	{
		this.__playing = true;
		let self = this;
		return new Promise(function(_resolve, _reject){
			self.__playingInterval = window.setInterval(_=>{
				if (self.currentTime >= self._duration)
				{
					self.ended = true;
					self.pause();
				}
				self.currentTime +=1;
				self.__pushEvent('timeupdate');
			}, self._playBackRate);
			_resolve();
		});
	}

	/**
	 * pause
	 */
	pause()
	{
		this.__playing = false;
		this._paused = true;
		window.clearInterval(this.__playingInterval);
	}

	disconnectedCallback()
	{
		window.clearInterval(this.__playingInterval);
		this.__pdfViewState.pdf?.destroy();
		this.__pdfViewState.pdf = null;
	}

	/**
	 * seek previous page
	 */
	prevPage()
	{
		this.currentTime -=1;
	}

	/**
	 * seek next page
	 */
	nextPage()
	{
		this.currentTime +=1;
	}
}

// Define pdf-player element
customElements.define('pdf-player', pdf_player);