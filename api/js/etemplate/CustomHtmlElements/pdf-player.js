"use strict";
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
var __extends = (this && this.__extends) || (function () {
    var extendStatics = function (d, b) {
        extendStatics = Object.setPrototypeOf ||
            ({ __proto__: [] } instanceof Array && function (d, b) { d.__proto__ = b; }) ||
            function (d, b) { for (var p in b) if (b.hasOwnProperty(p)) d[p] = b[p]; };
        return extendStatics(d, b);
    };
    return function (d, b) {
        extendStatics(d, b);
        function __() { this.constructor = d; }
        d.prototype = b === null ? Object.create(b) : (__.prototype = b.prototype, new __());
    };
})();
Object.defineProperty(exports, "__esModule", { value: true });
/*egw:uses
    /node_modules/@bundled-es-modules/pdfjs-dist/build/pdf.js;
    /node_modules/@bundled-es-modules/pdfjs-dist/build/pdf.worker.js;

*/
var pdf_1 = require("@bundled-es-modules/pdfjs-dist/build/pdf");
/*
    This web component allows to display and play pdf file like a video player widget/element. Its attributes and
    methodes are mostley identical as video html. No controls attribute supported yet.
*/
pdf_1.default.GlobalWorkerOptions.workerSrc = 'node_modules/@bundled-es-modules/pdfjs-dist/build/pdf.worker.js';
// Create a class for the element
var pdf_player = /** @class */ (function (_super) {
    __extends(pdf_player, _super);
    function pdf_player() {
        var _this = _super.call(this) || this;
        /**
         * shadow dom container
         * @private
         */
        _this._shadow = null;
        /**
         * keeps duration time internally
         * @private
         */
        _this._duration = 0;
        /**
         * keeps currentTime internally
         * @private
         */
        _this._currentTime = 0;
        /**
         * Keeps playing state internally
         * @private
         */
        _this.__playing = false;
        /**
         * keeps playing interval id
         * @private
         */
        _this.__playingInterval = 0;
        /**
         * keeps play back rate
         * @private
         */
        _this._playBackRate = 1000;
        /**
         * keeps ended state of playing pdf
         * @private
         */
        _this._ended = false;
        /**
         * keep paused state
         * @private
         */
        _this._paused = false;
        /**
         * keeps pdf doc states
         * @private
         */
        _this.__pdfViewState = {
            pdf: null,
            currentPage: 1,
            zoom: 1
        };
        // Create a shadow root
        _this._shadow = _this.attachShadow({ mode: 'open' });
        // Create wrapper
        _this._wrapper = document.createElement('div');
        _this._wrapper.setAttribute('class', 'wrapper');
        // Create some CSS to apply to the shadow dom
        _this._style = document.createElement('style');
        _this._style.textContent = '.wrapper {' +
            'width: 100%;' +
            'height: auto;' +
            'display: block;' +
            '}' +
            '.wrapper canvas {' +
            'width: 100%;' +
            'height: auto;' +
            '}';
        // attach to the shadow dom
        _this._shadow.appendChild(_this._style);
        _this._shadow.appendChild(_this._wrapper);
        return _this;
    }
    Object.defineProperty(pdf_player, "observedAttributes", {
        /**
         * set observable attributes
         * @return {string[]}
         */
        get: function () {
            return ['src', 'type'];
        },
        enumerable: false,
        configurable: true
    });
    /**
     * Gets called on observable attributes changes
     * @param name attribute name
     * @param _
     * @param newVal new value
     */
    pdf_player.prototype.attributeChangedCallback = function (name, _, newVal) {
        switch (name) {
            case 'src':
                this.__buildPDFView(newVal);
                break;
            case 'type':
                // do nothing
                break;
        }
    };
    /**
     * init/update pdf tag
     * @param _value
     * @private
     */
    pdf_player.prototype.__buildPDFView = function (_value) {
        var _this = this;
        this._canvas = document.createElement('canvas');
        this._wrapper.appendChild(this._canvas);
        var longTask = pdf_1.default.getDocument(_value);
        longTask.promise.then(function (pdf) {
            _this.__pdfViewState.pdf = pdf;
            _this._duration = _this.__pdfViewState.pdf._pdfInfo.numPages;
            // initiate the pdf file viewer for the first time after loading
            _this.__render(1).then(function (_) {
                _this.__pushEvent('loadedmetadata');
            });
        });
    };
    /**
     * Render given page from pdf into the canvas container
     *
     * @param _page
     * @private
     */
    pdf_player.prototype.__render = function (_page) {
        if (!this.__pdfViewState.pdf)
            return;
        var p = _page || this.__pdfViewState.currentPage;
        var self = this;
        return this.__pdfViewState.pdf.getPage(p).then(function (page) {
            var canvasContext = self._canvas.getContext('2d');
            var viewport = page.getViewport({ scale: self.__pdfViewState.zoom });
            self._canvas.width = viewport.width;
            self._canvas.height = viewport.height;
            page.render({
                canvasContext: canvasContext,
                viewport: viewport
            });
        });
    };
    /**
     * Creates event and dispatches it
     * @param _name
     */
    pdf_player.prototype.__pushEvent = function (_name) {
        var event = document.createEvent("Event");
        event.initEvent(_name, true, true);
        this.dispatchEvent(event);
    };
    Object.defineProperty(pdf_player.prototype, "src", {
        /**
         * get src
         * @return string returns comma separated sources
         */
        get: function () {
            return this.src;
        },
        /**************************** PUBLIC ATTRIBUTES & METHODES *************************************************/
        /****************************** ATTRIBUTES **************************************/
        /**
         * set src
         * @param _value
         */
        set: function (_value) {
            this._wrapper.children.forEach(function (_ch) {
                _ch.remove();
            });
            this.__buildPDFView(_value);
        },
        enumerable: false,
        configurable: true
    });
    Object.defineProperty(pdf_player.prototype, "currentTime", {
        /**
         * get currentTime
         * @return {number}
         */
        get: function () {
            return this._currentTime;
        },
        /**
         * currentTime
         * @param _time
         */
        set: function (_time) {
            var time = Math.floor(_time < 1 ? 1 : _time);
            if (time > this._duration) {
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
        },
        enumerable: false,
        configurable: true
    });
    Object.defineProperty(pdf_player.prototype, "duration", {
        /**
         * get duration time
         */
        get: function () {
            return this._duration;
        },
        enumerable: false,
        configurable: true
    });
    Object.defineProperty(pdf_player.prototype, "paused", {
        /**
         * get paused attribute
         */
        get: function () {
            return this._paused;
        },
        enumerable: false,
        configurable: true
    });
    Object.defineProperty(pdf_player.prototype, "muted", {
        /**
         * get muted attribute
         */
        get: function () {
            return true;
        },
        /**
         * set muted attribute
         * @param _value
         */
        set: function (_value) {
            return;
        },
        enumerable: false,
        configurable: true
    });
    Object.defineProperty(pdf_player.prototype, "ended", {
        /**
         * get ended attribute
         */
        get: function () {
            return this._ended;
        },
        set: function (_value) {
            this._ended = _value;
        },
        enumerable: false,
        configurable: true
    });
    Object.defineProperty(pdf_player.prototype, "playbackRate", {
        /**
         * get playbackRate
         */
        get: function () {
            return this._playBackRate;
        },
        /**
         * set playbackRate
         * @param _value
         */
        set: function (_value) {
            this._playBackRate = _value * 1000;
        },
        enumerable: false,
        configurable: true
    });
    Object.defineProperty(pdf_player.prototype, "volume", {
        /**
         * get volume
         */
        get: function () {
            return 0;
        },
        /**
         * set volume
         */
        set: function (_value) {
            return;
        },
        enumerable: false,
        configurable: true
    });
    /************************************************* METHODES ******************************************/
    /**
     * Play
     */
    pdf_player.prototype.play = function () {
        this.__playing = true;
        var self = this;
        return new Promise(function (_resolve, _reject) {
            self.__playingInterval = window.setInterval(function (_) {
                if (self.currentTime >= self._duration) {
                    self.ended = true;
                    self.pause();
                }
                self.currentTime += 1;
                self.__pushEvent('timeupdate');
            }, self._playBackRate);
            _resolve();
        });
    };
    /**
     * pause
     */
    pdf_player.prototype.pause = function () {
        this.__playing = false;
        this._paused = true;
        window.clearInterval(this.__playingInterval);
    };
    /**
     * seek previous page
     */
    pdf_player.prototype.prevPage = function () {
        this.currentTime -= 1;
    };
    /**
     * seek next page
     */
    pdf_player.prototype.nextPage = function () {
        this.currentTime += 1;
    };
    return pdf_player;
}(HTMLElement));
// Define pdf-player element
customElements.define('pdf-player', pdf_player);
//# sourceMappingURL=pdf-player.js.map