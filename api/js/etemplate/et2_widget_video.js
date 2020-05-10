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
/*egw:uses
    /vendor/bower-asset/jquery/dist/jquery.js;
    et2_core_interfaces;
    et2_core_baseWidget;
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
        //Create Video tag
        _this.video = jQuery(document.createElement("video"));
        if (_this.options.controls) {
            _this.video.attr("controls", 1);
        }
        if (_this.options.autoplay) {
            _this.video.attr("autoplay", 1);
        }
        if (_this.options.muted) {
            _this.video.attr("muted", 1);
        }
        if (_this.options.video_src) {
            _this.set_src(_this.options.video_src);
        }
        if (_this.options.loop) {
            _this.video.attr("loop", 1);
        }
        _this.setDOMNode(_this.video[0]);
        return _this;
    }
    /**
     * Set video src
     *
     * @param {string} _value url
     */
    et2_video.prototype.set_src = function (_value) {
        if (_value) {
            var source = jQuery(document.createElement('source'))
                .attr('src', _value)
                .appendTo(this.video);
            if (this.options.src_type) {
                source.attr('type', this.options.src_type);
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
        if (_value) {
            this.video.attr("autoplay", _value);
        }
    };
    /**
     * Set controls option for video
     *
     * @param {string} _value true set the autoplay and false not to set
     */
    et2_video.prototype.set_controls = function (_value) {
        if (_value) {
            this.video.attr("controls", _value);
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
        this.video[0].currentTime = _vtime;
    };
    /**
     * Play video
     */
    et2_video.prototype.play_video = function () {
        return this.video[0].play();
    };
    /**
     * Pause video
     */
    et2_video.prototype.pause_video = function () {
        this.video[0].pause();
    };
    /**
     * Get current video time / position in seconds
     */
    et2_video.prototype.currentTime = function () {
        return this.video[0].currentTime;
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
        "muted": {
            "name": "Audio control",
            "type": "boolean",
            "default": false,
            "description": "Defines that the audio output of the video should be muted"
        },
        "autoplay": {
            "name": "Autoply",
            "type": "boolean",
            "default": false,
            "description": "Defines if Video will start playing as soon as it is ready"
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
        }
    };
    return et2_video;
}(et2_core_baseWidget_1.et2_baseWidget));
exports.et2_video = et2_video;
et2_core_widget_1.et2_register_widget(et2_video, ["video"]);
//# sourceMappingURL=et2_widget_video.js.map