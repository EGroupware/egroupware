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

/*egw:uses
    /node_modules/pdfjs-dist/legacy/build/pdf.js;
    /node_modules/pdfjs-dist/legacy/build/pdf.worker.js;

*/

// Unfortunately compiled version of module:ES2015 TS would break webcomponent constructor.
// Since we don't have ES2020 available on 21.1 we have to use JS format using require and a legacy pdfjs lib instead of TS (used in master)
var pdfjslib = require("pdfjs-dist/legacy/build/pdf");
var pdfjs = pdfjslib['pdfjs-dist/build/pdf'];

pdfjs.GlobalWorkerOptions.workerSrc = 'node_modules/pdfjs-dist/legacy/build/pdf.worker.js';

/*
    This web component allows to display and play pdf file like a video player widget/element. Its attributes and
    methodes are mostley identical as video html. No controls attribute supported yet.
*/
class pdf_player extends HTMLElement {
	/**
	 * shadow dom container
	 * @private
	 */
	_shadow = null;
	/**
	 * wrapper container holds canvas
	 * @private
	 */

	/**
	 * keeps duration time internally
	 * @private
	 */
	_duration = 0;
	/**
	 * keeps currentTime internally
	 * @private
	 */

	_currentTime = 0;
	/**
	 * Keeps playing state internally
	 * @private
	 */

	__playing = false;
	/**
	 * keeps playing interval id
	 * @private
	 */

	__playingInterval = 0;
	/**
	 * keeps play back rate
	 * @private
	 */

	_playBackRate = 1000;
	/**
	 * keeps ended state of playing pdf
	 * @private
	 */

	_ended = false;
	/**
	 * keep paused state
	 * @private
	 */

	_paused = false;
	/**
	 * keeps pdf doc states
	 * @private
	 */

	__pdfViewState = {
		pdf: null,
		currentPage: 1,
		zoom: 1
	};

	constructor() {
		super(); // Create a shadow root

		this._shadow = this.attachShadow({
			mode: 'open'
		}); // Create wrapper

		this._wrapper = document.createElement('div');

		this._wrapper.setAttribute('class', 'wrapper'); // Create some CSS to apply to the shadow dom


		this._style = document.createElement('style');
		this._style.textContent = '.wrapper {' + 'width: 100%;' + 'height: auto;' + 'display: block;' + '}' + '.wrapper canvas {' + 'width: 100%;' + 'height: auto;' + '}'; // attach to the shadow dom

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
		switch (name) {
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


	__buildPDFView(_value) {
		this._canvas = document.createElement('canvas');

		this._wrapper.appendChild(this._canvas);

		let longTask = pdfjs.getDocument(_value);
		longTask.promise.then(pdf => {
			this.__pdfViewState.pdf = pdf;
			this._duration = this.__pdfViewState.pdf._pdfInfo.numPages; // initiate the pdf file viewer for the first time after loading

			this.__render(1).then(_ => {
				this.__pushEvent('loadedmetadata');
			});
		});
	}
	/**
	 * Render given page from pdf into the canvas container
	 *
	 * @param _page
	 * @private
	 */


	__render(_page) {
		if (!this.__pdfViewState.pdf) return;
		let p = _page || this.__pdfViewState.currentPage;
		let self = this;
		return this.__pdfViewState.pdf.getPage(p).then(page => {
			let canvasContext = self._canvas.getContext('2d');

			let viewport = page.getViewport({
				scale: self.__pdfViewState.zoom
			});
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


	__pushEvent(_name) {
		let event = document.createEvent("Event");
		event.initEvent(_name, true, true);
		this.dispatchEvent(event);
	}
	/**************************** PUBLIC ATTRIBUTES & METHODES *************************************************/

	/****************************** ATTRIBUTES **************************************/

	/**
	 * set src
	 * @param _value
	 */


	set src(_value) {
		Array.from(this._wrapper.children).forEach(_ch => {
			_ch.remove();
		});

		this.__buildPDFView(_value);
	}
	/**
	 * get src
	 * @return string returns comma separated sources
	 */


	get src() {
		return this.src;
	}
	/**
	 * currentTime
	 * @param _time
	 */


	set currentTime(_time) {
		let time = Math.floor(_time < 1 ? 1 : _time);

		if (time > this._duration) {
			// set ended state to true as it's the last page of pdf
			this._ended = true; // don't go further because it's litterally the last page

			return;
		} // set ended state to false as it's not the end of the pdf


		this._ended = false;
		this._currentTime = time;
		this.__pdfViewState.currentPage = time;

		this.__render(time);
	}
	/**
	 * get currentTime
	 * @return {number}
	 */


	get currentTime() {
		return this._currentTime;
	}
	/**
	 * get duration time
	 */


	get duration() {
		return this._duration;
	}
	/**
	 * get paused attribute
	 */


	get paused() {
		return this._paused;
	}
	/**
	 * set muted attribute
	 * @param _value
	 */


	set muted(_value) {
		return;
	}
	/**
	 * get muted attribute
	 */


	get muted() {
		return true;
	}

	set ended(_value) {
		this._ended = _value;
	}
	/**
	 * get ended attribute
	 */


	get ended() {
		return this._ended;
	}
	/**
	 * set playbackRate
	 * @param _value
	 */


	set playbackRate(_value) {
		this._playBackRate = _value * 1000;
	}
	/**
	 * get playbackRate
	 */


	get playbackRate() {
		return this._playBackRate;
	}
	/**
	 * set volume
	 */


	set volume(_value) {
		return;
	}
	/**
	 * get volume
	 */


	get volume() {
		return 0;
	}
	/************************************************* METHODES ******************************************/

	/**
	 * Play
	 */


	play() {
		this.__playing = true;
		let self = this;
		return new Promise(function (_resolve, _reject) {
			self.__playingInterval = window.setInterval(_ => {
				if (self.currentTime >= self._duration) {
					self.ended = true;
					self.pause();
				}

				self.currentTime += 1;

				self.__pushEvent('timeupdate');
			}, self._playBackRate);

			_resolve();
		});
	}
	/**
	 * pause
	 */


	pause() {
		this.__playing = false;
		this._paused = true;
		window.clearInterval(this.__playingInterval);
	}
	/**
	 * seek previous page
	 */


	prevPage() {
		this.currentTime -= 1;
	}
	/**
	 * seek next page
	 */


	nextPage() {
		this.currentTime += 1;
	}

} // Define pdf-player element


customElements.define('pdf-player', pdf_player);