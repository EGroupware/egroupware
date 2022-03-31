/**
 * EGroupware Custom Html Elements - Multi Video Web Components
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
// Create a class for the element
var multi_video = /** @class */ (function (_super) {
    __extends(multi_video, _super);
    function multi_video() {
        var _this = _super.call(this) || this;
        /**
         * shadow dom container
         * @private
         */
        _this._shadow = null;
        /**
         * contains video objects of type VideoTagsArray
         * @private
         */
        _this._videos = [];
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
         * Keeps video playing state internally
         * @private
         */
        _this.__playing = false;
        // Create a shadow root
        _this._shadow = _this.attachShadow({ mode: 'open' });
        // Create videos wrapper
        _this._wrapper = document.createElement('div');
        _this._wrapper.setAttribute('class', 'wrapper');
        // Create some CSS to apply to the shadow dom
        _this._style = document.createElement('style');
        _this._style.textContent = '.wrapper {' +
            'width: 100%;' +
            'height: auto;' +
            'display: block;' +
            '}' +
            '.wrapper video {' +
            'width: 100%;' +
            'height: auto;' +
            '}';
        // attach to the shadow dom
        _this._shadow.appendChild(_this._style);
        _this._shadow.appendChild(_this._wrapper);
        return _this;
    }
    Object.defineProperty(multi_video, "observedAttributes", {
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
    multi_video.prototype.attributeChangedCallback = function (name, _, newVal) {
        switch (name) {
            case 'src':
                this.__buildVideoTags(newVal);
                break;
            case 'type':
                this._videos.forEach(function (_item) {
                    _item.node.setAttribute('type', newVal);
                });
                break;
        }
    };
    /**
     * init/update video tags
     * @param _value
     * @private
     */
    multi_video.prototype.__buildVideoTags = function (_value) {
        var value = _value.split(',');
        var video = null;
        var _loop_1 = function (i) {
            video = document.createElement('video');
            video.src = value[i];
            this_1._videos[i] = {
                node: this_1._wrapper.appendChild(video),
                loadedmetadata: false,
                timeupdate: false,
                duration: 0,
                previousDurations: 0,
                currentTime: 0,
                active: false,
                index: i
            };
            // loadmetadata event
            this_1._videos[i]['node'].addEventListener("loadedmetadata", function (_i, _event) {
                this._videos[_i]['loadedmetadata'] = true;
                this.__check_loadedmetadata();
            }.bind(this_1, i));
            //timeupdate event
            this_1._videos[i]['node'].addEventListener("timeupdate", function (_i, _event) {
                this._currentTime = this._videos[i]['previousDurations'] + _event.target.currentTime;
                // push the next video to start otherwise the time update gets paused as it ends automatically
                // with the current video being ended
                if (this._videos[i].node.ended && !this.ended)
                    this.currentTime = this._currentTime + 0.1;
                this.__pushEvent('timeupdate');
            }.bind(this_1, i));
        };
        var this_1 = this;
        for (var i = 0; i < value.length; i++) {
            _loop_1(i);
        }
    };
    /**
     * calculates duration of videos
     * @return {number} returns accumulated durations
     * @private
     */
    multi_video.prototype.__duration = function () {
        var duration = 0;
        this._videos.forEach(function (_item) {
            duration += _item.duration;
        });
        return duration;
    };
    /**
     * Get current active video
     * @return {*[]} returns an array of object consist of current video displayed node
     * @private
     */
    multi_video.prototype.__getActiveVideo = function () {
        return this._videos.filter(function (_item) {
            return (_item.active);
        });
    };
    /**
     * check if all meta data from videos are ready then pushes the event
     * @private
     */
    multi_video.prototype.__check_loadedmetadata = function () {
        var _this = this;
        var allReady = true;
        this._videos.forEach(function (_item) {
            allReady = allReady && _item.loadedmetadata;
        });
        if (allReady) {
            this._videos.forEach(function (_item) {
                _item.duration = _item.node.duration;
                _item.previousDurations = _item.index > 0 ? _this._videos[_item.index - 1]['duration'] + _this._videos[_item.index - 1]['previousDurations'] : 0;
            });
            this.duration = this.__duration();
            this.currentTime = 0;
            this.__pushEvent('loadedmetadata');
        }
    };
    /**
     * Creates event and dispatches it
     * @param _name
     */
    multi_video.prototype.__pushEvent = function (_name) {
        var event = document.createEvent("Event");
        event.initEvent(_name, true, true);
        this.dispatchEvent(event);
    };
    Object.defineProperty(multi_video.prototype, "src", {
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
            var value = _value.split(',');
            this._wrapper.children.forEach(function (_ch) {
                _ch.remove();
            });
            this.__buildVideoTags(value);
        },
        enumerable: false,
        configurable: true
    });
    Object.defineProperty(multi_video.prototype, "currentTime", {
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
            var _this = this;
            var ctime = _time;
            this._currentTime = _time;
            this._videos.forEach(function (_item) {
                if ((ctime < _item.duration + _item.previousDurations)
                    && ((ctime == 0 && _item.previousDurations == 0) || ctime > _item.previousDurations)) {
                    if (_this.__playing && _item.node.paused)
                        _item.node.play();
                    _item.currentTime = Math.abs(_item.previousDurations - ctime);
                    _item.node.currentTime = _item.currentTime;
                    _item.active = true;
                }
                else {
                    _item.active = false;
                    _item.node.pause();
                }
                _item.node.hidden = !_item.active;
            });
        },
        enumerable: false,
        configurable: true
    });
    Object.defineProperty(multi_video.prototype, "duration", {
        /**
         * get video duration time
         */
        get: function () {
            return this._duration;
        },
        /**
         * set video duration time attribute
         * @param _value
         */
        set: function (_value) {
            this._duration = _value;
        },
        enumerable: false,
        configurable: true
    });
    Object.defineProperty(multi_video.prototype, "paused", {
        /**
         * get paused attribute
         */
        get: function () {
            var _a, _b;
            return (_b = (_a = this.__getActiveVideo()[0]) === null || _a === void 0 ? void 0 : _a.node) === null || _b === void 0 ? void 0 : _b.paused;
        },
        enumerable: false,
        configurable: true
    });
    Object.defineProperty(multi_video.prototype, "muted", {
        /**
         * get muted attribute
         */
        get: function () {
            var _a, _b;
            return (_b = (_a = this.__getActiveVideo()[0]) === null || _a === void 0 ? void 0 : _a.node) === null || _b === void 0 ? void 0 : _b.muted;
        },
        /**
         * set muted attribute
         * @param _value
         */
        set: function (_value) {
            this._videos.forEach(function (_item) {
                _item.node.muted = _value;
            });
        },
        enumerable: false,
        configurable: true
    });
    Object.defineProperty(multi_video.prototype, "ended", {
        /**
         * get video ended attribute
         */
        get: function () {
            var _a, _b;
            return (_b = (_a = this._videos[this._videos.length - 1]) === null || _a === void 0 ? void 0 : _a.node) === null || _b === void 0 ? void 0 : _b.ended;
        },
        enumerable: false,
        configurable: true
    });
    Object.defineProperty(multi_video.prototype, "playbackRate", {
        /**
         * get playbackRate
         */
        get: function () {
            var _a, _b;
            return (_b = (_a = this.__getActiveVideo()[0]) === null || _a === void 0 ? void 0 : _a.node) === null || _b === void 0 ? void 0 : _b.playbackRate;
        },
        /**
         * set playbackRate
         * @param _value
         */
        set: function (_value) {
            this._videos.forEach(function (_item) {
                _item.node.playbackRate = _value;
            });
        },
        enumerable: false,
        configurable: true
    });
    Object.defineProperty(multi_video.prototype, "volume", {
        /**
         * get volume
         */
        get: function () {
            var _a, _b;
            return (_b = (_a = this.__getActiveVideo()[0]) === null || _a === void 0 ? void 0 : _a.node) === null || _b === void 0 ? void 0 : _b.volume;
        },
        /**
         * set volume
         */
        set: function (_value) {
            this._videos.forEach(function (_item) {
                _item.node.volume = _value;
            });
        },
        enumerable: false,
        configurable: true
    });
    /************************************************* METHODES ******************************************/
    /**
     * Play video
     */
    multi_video.prototype.play = function () {
        var _a, _b;
        this.__playing = true;
        return (_b = (_a = this.__getActiveVideo()[0]) === null || _a === void 0 ? void 0 : _a.node) === null || _b === void 0 ? void 0 : _b.play();
    };
    /**
     * pause video
     */
    multi_video.prototype.pause = function () {
        var _a, _b;
        this.__playing = false;
        (_b = (_a = this.__getActiveVideo()[0]) === null || _a === void 0 ? void 0 : _a.node) === null || _b === void 0 ? void 0 : _b.pause();
    };
    return multi_video;
}(HTMLElement));
// Define the new multi-video element
customElements.define('multi-video', multi_video);
//# sourceMappingURL=multi-video.js.map