"use strict";
/**
 * EGroupware eTemplate2 - JS Description object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Hadi Nategh <hn[at]stylite.de>
 * @copyright Stylite AG
 * @version $Id$
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
exports.et2_video = void 0;
/*egw:uses
    /vendor/bower-asset/jquery/dist/jquery.js;
    et2_core_interfaces;
    et2_core_baseWidget;
    /api/js/etemplate/CustomHtmlElements/multi-video.js;
    /api/js/etemplate/CustomHtmlElements/pdf-player.js;
*/
var et2_core_baseWidget_1 = require("./et2_core_baseWidget");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
var et2_core_widget_1 = require("./et2_core_widget");
/**
 * This widget represents the HTML5 video tag with all its optional attributes
 *
 * The widget can be created in the following ways:
 * <code>
 * var videoTag = et2_createWidget("video", {
 *			video_src: "../../test.mp4",
 *			src_type: "video/mp4",
 *			muted: true,
 *			autoplay: true,
 *			controls: true,
 *			poster: "../../poster.jpg",
 *			loop: true,
 *			height: 100,
 *			width: 200,
 * });
 * </code>
 * Or by adding XET-tag in your template (.xet) file:
 * <code>
 * <video [attributes...]/>
 * </code>
 */
/**
 * Class which implements the "video" XET-Tag
 *
 * @augments et2_baseWidget
 */
var et2_video = /** @class */ (function (_super) {
    __extends(et2_video, _super);
    function et2_video(_parent, _attrs, _child) {
        var _this = _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_video._attributes, _child || {})) || this;
        _this.video = null;
        /**
         * keeps internal state of previousTime video played
         * @private
         */
        _this._previousTime = 0;
        _this.set_src_type(_this.options.src_type);
        _this.options.starttime = isNaN(_this.options.starttime) ? 0 : _this.options.starttime;
        return _this;
    }
    et2_video.prototype.set_src_type = function (_type) {
        this.options.src_type = _type;
        if (this.video && this._isYoutube() === (this.video[0].tagName === 'DIV')) {
            return;
        }
        //Create Video tag
        this.video = jQuery(document.createElement(this._isYoutube() ? "div" :
            (_type.match('pdf') ? "pdf-player" : (this.options.multi_src ? 'multi-video' : 'video'))))
            .addClass('et2_video')
            .attr('id', this.dom_id);
        if (this._isYoutube()) {
            // this div will be replaced by youtube iframe api when youtube gets ready
            this.youtubeFrame = jQuery(document.createElement('div'))
                .appendTo(this.video)
                .attr('id', et2_video.youtubePrefixId + this.id);
            if (!document.getElementById('youtube-api-script')) {
                //Load youtube iframe api
                var tag = document.createElement('script');
                tag.id = 'youtube-api-script';
                tag.src = "https://www.youtube.com/iframe_api";
                var firstScriptTag = document.getElementsByTagName('script')[0];
                firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
            }
        }
        if (!this._isYoutube() && this.options.controls) {
            this.video.attr("controls", 1);
        }
        if (!this._isYoutube() && this.options.autoplay) {
            this.video.attr("autoplay", 1);
        }
        if (this.options.muted) {
            this.video.attr("muted", 1);
        }
        if (this.options.video_src) {
            this.set_src(this.options.video_src);
        }
        if (this.options.loop) {
            this.video.attr("loop", 1);
        }
        this.setDOMNode(this.video[0]);
        this.set_width(this.options.width || 'auto');
        this.set_height(this.options.height || 'auto');
    };
    /**
     * Set video src
     *
     * @param {string} _value url
     */
    et2_video.prototype.set_src = function (_value) {
        var self = this;
        this.options.video_src = _value;
        if (_value && !this._isYoutube()) {
            this.video.attr('src', _value);
            if (this.options.src_type) {
                this.video.attr('type', this.options.src_type);
            }
        }
        else if (_value) {
            if (typeof YT == 'undefined') {
                //initiate youtube Api object, it gets called automatically by iframe_api script from the api
                window.onYouTubeIframeAPIReady = this._onYoutubeIframeAPIReady;
                window.addEventListener('et2_video.onYoutubeIframeAPIReady', function () {
                    self._createYoutubePlayer(self.options.video_src);
                });
            }
            else {
                self._createYoutubePlayer(self.options.video_src);
            }
        }
    };
    /**
     * Set autoplay option for video
     * -If autoplay is set, video would be played automatically after the page is loaded
     *
     * @param {string} _value true set the autoplay and false not to set
     */
    et2_video.prototype.set_autoplay = function (_value) {
        if (_value && !this._isYoutube()) {
            this.video.attr("autoplay", _value);
        }
    };
    /**
     * Set controls option for video
     *
     * @param {string} _value true set the autoplay and false not to set
     */
    et2_video.prototype.set_controls = function (_value) {
        if (_value && !this._isYoutube()) {
            this.video.attr("controls", _value);
        }
    };
    /**
     * Method to set volume
     * @param _value
     */
    et2_video.prototype.set_volume = function (_value) {
        var value = _value > 100 ? 100 : _value;
        if (value >= 0) {
            if (this._isYoutube() && this.youtube && typeof this.youtube.setVolume === 'function') {
                this.youtube.setVolume(value);
            }
            else if (!this._isYoutube()) {
                this.video[0].volume = value / 100;
            }
        }
    };
    /**
     * get volume
     */
    et2_video.prototype.get_volume = function () {
        if (this._isYoutube() && this.youtube) {
            return this.youtube.getVolume();
        }
        else {
            return this.video[0].volume * 100;
        }
    };
    /**
     * method to set playBackRate
     * @param _value
     */
    et2_video.prototype.set_playBackRate = function (_value) {
        var value = _value > 16 ? 16 : _value;
        if (value >= 0) {
            if (this._isYoutube() && this.youtube) {
                this.youtube.setPlaybackRate(value);
            }
            else {
                this.video[0].playbackRate = value;
            }
        }
    };
    /**
     * get playBackRate
     */
    et2_video.prototype.get_playBackRate = function () {
        if (this._isYoutube() && this.youtube) {
            return this.youtube.getPlaybackRate();
        }
        else {
            return this.video[0].playbackRate;
        }
    };
    et2_video.prototype.set_mute = function (_value) {
        if (this._isYoutube() && this.youtube) {
            if (_value) {
                this.youtube.mute();
            }
            else {
                this.youtube.unMute();
            }
        }
        else {
            this.video[0].muted = _value;
        }
    };
    et2_video.prototype.get_mute = function () {
        if (this._isYoutube() && this.youtube) {
            return this.youtube.isMuted();
        }
        else {
            return this.video[0].muted;
        }
    };
    /**
     * Set poster attribute in order to specify
     * an image to be shown while video is loading or before user play it
     *
     * @param {string} _url url or image spec like "api/mime128_video"
     */
    et2_video.prototype.set_poster = function (_url) {
        if (_url) {
            if (_url[0] !== '/' && !_url.match(/^https?:\/\//)) {
                _url = this.egw().image(_url);
            }
            this.video.attr("poster", _url);
        }
    };
    /**
     * Seek to a time / position
     *
     * @param _vtime in seconds
     */
    et2_video.prototype.seek_video = function (_vtime) {
        if (this._isYoutube()) {
            if (this.youtube.seekTo) {
                this.youtube.seekTo(_vtime, true);
                this._currentTime = _vtime;
            }
        }
        else {
            this.video[0].currentTime = _vtime;
        }
    };
    /**
     * Play video
     */
    et2_video.prototype.play_video = function () {
        if (this._isYoutube()) {
            var self_1 = this;
            return new Promise(function (resolve) {
                if (self_1.youtube.playVideo) {
                    self_1.youtube.playVideo();
                    resolve();
                }
            });
        }
        return this.video[0].play();
    };
    /**
     * Pause video
     */
    et2_video.prototype.pause_video = function () {
        var _this = this;
        if (this._isYoutube()) {
            if (this.youtube.pauseVideo) {
                this.youtube.pauseVideo();
                if (this.youtube.getCurrentTime() != this._currentTime) {
                    // give it a chance to get actual current time ready otherwise we would still get the previous time
                    // unfortunately we need to rely on a timeout as the youtube seekTo not returning any promise to wait for.
                    setTimeout(function (_) { _this.currentTime(_this.youtube.getCurrentTime()); }, 500);
                }
                else {
                    this.currentTime(this.youtube.getCurrentTime());
                }
            }
        }
        else {
            this.video[0].pause();
        }
    };
    /**
     * play video
     * ***Internal use and should not be overriden in its extended class***
     */
    et2_video.prototype.play = function () {
        var _a;
        return this._isYoutube() && ((_a = this.youtube) === null || _a === void 0 ? void 0 : _a.playVideo) ? this.youtube.playVideo() : this.video[0].play();
    };
    /**
     * Get/set current video time / position in seconds
     * @return returns currentTime
     */
    et2_video.prototype.currentTime = function (_time) {
        var _a;
        if (_time) {
            if (this._isYoutube()) {
                this.youtube.seekTo(_time);
            }
            else {
                this.video[0].currentTime = _time;
            }
            return this._currentTime = _time;
        }
        if (this._isYoutube()) {
            if (typeof this._currentTime != 'undefined') {
                return this._currentTime;
            }
            return ((_a = this.youtube) === null || _a === void 0 ? void 0 : _a.getCurrentTime) ? this.youtube.getCurrentTime() : 0;
        }
        else {
            return this.video[0].currentTime;
        }
    };
    /**
     * get duration time
     * @return returns duration time
     */
    et2_video.prototype.duration = function () {
        var _a;
        if (this._isYoutube()) {
            return ((_a = this.youtube) === null || _a === void 0 ? void 0 : _a.getDuration) ? this.youtube.getDuration() : 0;
        }
        else {
            return this.video[0].duration;
        }
    };
    /**
     * get pasued
     * @return returns paused flag
     */
    et2_video.prototype.paused = function () {
        if (this._isYoutube()) {
            return this.youtube.getPlayerState() == et2_video.youtube_player_states.paused;
        }
        return this.video[0].paused;
    };
    /**
     * get ended
     * @return returns ended flag
     */
    et2_video.prototype.ended = function () {
        if (this._isYoutube()) {
            return this.youtube.getPlayerState() == et2_video.youtube_player_states.ended;
        }
        return this.video[0].ended;
    };
    /**
     * get/set priviousTime
     * @param _time
     * @return returns time
     */
    et2_video.prototype.previousTime = function (_time) {
        if (_time)
            this._previousTime = _time;
        return this._previousTime;
    };
    et2_video.prototype.doLoadingFinished = function () {
        _super.prototype.doLoadingFinished.call(this);
        var self = this;
        if (!this._isYoutube()) {
            this.video[0].addEventListener("loadedmetadata", function () {
                self._onReady();
            });
            this.video[0].addEventListener("timeupdate", function () {
                self._onTimeUpdate();
            });
        }
        else {
            // need to create the player after the DOM is ready otherwise player won't show up
            if (window.YT)
                this._createYoutubePlayer(this.options.video_src);
        }
        return false;
    };
    et2_video.prototype.videoLoadnigIsFinished = function () {
        if (this.options.starttime >= 0) {
            this.seek_video(this.options.starttime);
            // unfortunately, youtube api autoplays the video after seekTo on initiation
            // and there's no way to stop that therefore we need to trick it by manually
            // pausing the video (this would bring up the spinner with the black screen,
            // in order to avoid that we let the video plays for a second then we pause).
            // since the youtube timeline is one second advanced we need to seek back to
            // the original stattime although this time because it was manually paused
            // we won't have the spinner and black screen instead we get the preview.
            if (this._isYoutube())
                window.setTimeout(function () {
                    this.youtube.pauseVideo();
                    this.youtube.seekTo(this.options.starttime);
                    ;
                }.bind(this), 1000);
        }
    };
    et2_video.prototype._onReady = function () {
        // need to set the video dom to transformed iframe
        if (this._isYoutube() && this.youtube.getIframe)
            this.youtubeFrame = jQuery(this.youtube.getIframe());
        var event = document.createEvent("Event");
        event.initEvent('et2_video.onReady.' + this.id, true, true);
        this.video[0].dispatchEvent(event);
    };
    et2_video.prototype._onTimeUpdate = function () {
        // update currentTime manually since youtube currentTime might be updated due to the loading
        if (this._isYoutube() && this.youtube.getCurrentTime)
            this._currentTime = this.youtube.getCurrentTime();
        var event = document.createEvent("Event");
        event.initEvent('et2_video.onTimeUpdate.' + this.id, true, true);
        this.video[0].dispatchEvent(event);
    };
    /**
     * check if the video is a youtube type
     * @return return true if it's a youtube type video
     * @private
     */
    et2_video.prototype._isYoutube = function () {
        return !!this.options.src_type.match('youtube');
    };
    et2_video.prototype._onStateChangeYoutube = function (_data) {
        switch (_data.data) {
            case et2_video.youtube_player_states.unstarted:
                // do nothing
                break;
            case et2_video.youtube_player_states.playing:
                this._youtubeOntimeUpdateIntrv = window.setInterval(jQuery.proxy(this._onTimeUpdate, this), 100);
                break;
            default:
                window.clearInterval(this._youtubeOntimeUpdateIntrv);
        }
        console.log(_data);
    };
    /**
     * youtube on IframeAPI ready event
     */
    et2_video.prototype._onYoutubeIframeAPIReady = function () {
        var event = document.createEvent("Event");
        event.initEvent('et2_video.onYoutubeIframeAPIReady', true, true);
        window.dispatchEvent(event);
    };
    /**
     * create youtube player
     *
     * @param _value
     */
    et2_video.prototype._createYoutubePlayer = function (_value) {
        var matches = _value === null || _value === void 0 ? void 0 : _value.match(et2_video.youtubeRegexp);
        if (matches && typeof YT != 'undefined') {
            this.youtube = new YT.Player(et2_video.youtubePrefixId + this.id, {
                height: this.options.height || '400',
                width: '100%',
                playerVars: {
                    'autoplay': 0,
                    'controls': 0,
                    'modestbranding': 1,
                    'fs': 0,
                    'disablekb': 1,
                    'rel': 0,
                    'iv_load_policy': 0,
                    'cc_load_policy': 0
                },
                videoId: matches[4],
                events: {
                    'onReady': jQuery.proxy(this._onReady, this),
                    'onStateChange': jQuery.proxy(this._onStateChangeYoutube, this)
                }
            });
        }
    };
    et2_video._attributes = {
        "video_src": {
            "name": "Video",
            "type": "string",
            "description": "Source of video to display"
        },
        "src_type": {
            "name": "Source type",
            "type": "string",
            "description": "Defines the type the stream source provided"
        },
        "multi_src": {
            "name": "Multi Video source",
            "type": "boolean",
            "default": false,
            "description": "creates a multi-video tag in order to render all provided video sources"
        },
        "muted": {
            "name": "Audio control",
            "type": "boolean",
            "default": false,
            "description": "Defines that the audio output of the video should be muted"
        },
        "autoplay": {
            "name": "Autoplay",
            "type": "boolean",
            "default": false,
            "description": "Defines if Video will start playing as soon as it is ready"
        },
        starttime: {
            "name": "Inital position of video",
            "type": "float",
            "default": 0,
            "description": "Set initial position of video"
        },
        "controls": {
            "name": "Control buttons",
            "type": "boolean",
            "default": false,
            "description": "Defines if Video controls, play/pause buttons should be displayed"
        },
        "poster": {
            "name": "Video Poster",
            "type": "string",
            "default": "",
            "description": "Specifies an image to be shown while video is loading or before user play it"
        },
        "loop": {
            "name": "Video loop",
            "type": "boolean",
            "default": false,
            "description": "Defines if the video should be played repeatedly"
        },
        "volume": {
            "name": "Video volume",
            "type": "float",
            "default": 0,
            "description": "Set video's volume"
        },
        "playbackrate": {
            "name": "Video playBackRate",
            "type": "float",
            "default": 1,
            "description": "Set video's playBackRate"
        }
    };
    et2_video.youtube_player_states = {
        unstarted: -1,
        ended: 0,
        playing: 1,
        paused: 2,
        buffering: 3,
        video_cued: 5
    };
    /**
     * prefix id used for addressing youtube player dom
     * @private
     */
    et2_video.youtubePrefixId = "frame-";
    et2_video.youtubeRegexp = new RegExp(/^https:\/\/((www\.|m\.)?youtube(-nocookie)?\.com|youtu\.be)\/.*(?:\/|%3D|v=|vi=)([0-9A-z-_]{11})(?:[%#?&]|$)/m);
    return et2_video;
}(et2_core_baseWidget_1.et2_baseWidget));
exports.et2_video = et2_video;
et2_core_widget_1.et2_register_widget(et2_video, ["video"]);
//# sourceMappingURL=et2_widget_video.js.map