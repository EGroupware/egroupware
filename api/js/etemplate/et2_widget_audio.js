"use strict";
/**
 * EGroupware eTemplate2 - JS Audio tag
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Hadi Nategh <hn[at]egroupware.org>
 * @copyright EGroupware GmbH
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
exports.et2_audio = void 0;
/*egw:uses
    /vendor/bower-asset/jquery/dist/jquery.js;
    et2_core_interfaces;
    et2_core_baseWidget;
*/
var et2_core_baseWidget_1 = require("./et2_core_baseWidget");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
var et2_core_widget_1 = require("./et2_core_widget");
/**
 * This widget represents the HTML5 Audio tag with all its optional attributes
 *
 * The widget can be created in the following ways:
 * <code>
 * var audioTag = et2_createWidget("audio", {
 *			audio_src: "../../test.mp3",
 *			src_type: "audio/mpeg",
 *			muted: true,
 *			autoplay: true,
 *			controls: true,
 *			loop: true,
 *			height: 100,
 *			width: 200,
 * });
 * </code>
 * Or by adding XET-tag in your template (.xet) file:
 * <code>
 * <audio [attributes...]/>
 * </code>
 */
/**
 * Class which implements the "audio" XET-Tag
 *
 * @augments et2_baseWidget
 */
var et2_audio = /** @class */ (function (_super) {
    __extends(et2_audio, _super);
    function et2_audio(_parent, _attrs, _child) {
        var _this = _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_audio._attributes, _child || {})) || this;
        _this.audio = null;
        _this.container = null;
        //Create Audio tag
        _this.audio = new Audio();
        // Container
        _this.container = document.createElement('div');
        _this.container.append(_this.audio);
        _this.container.classList.add('et2_audio');
        if (_this.options.autohide)
            _this.container.classList.add('et2_audio_autohide');
        if (_this.options.controls)
            _this.audio.setAttribute("controls", '1');
        if (_this.options.autoplay)
            _this.audio.setAttribute("autoplay", '1');
        if (_this.options.muted)
            _this.audio.setAttribute("muted", '1');
        if (_this.options.loop)
            _this.audio.setAttribute("loop", '1');
        if (_this.options.preload)
            _this.audio.setAttribute('preload', _this.options.preload);
        _this.setDOMNode(_this.container);
        return _this;
    }
    /**
     * Set audio source
     *
     * @param {string} _value url
     */
    et2_audio.prototype.set_src = function (_value) {
        if (_value) {
            this.audio.setAttribute('src', _value);
            if (this.options.src_type) {
                this.audio.setAttribute('type', this.options.src_type);
            }
            //preload the audio after changing the source/ only if preload is allowed
            if (this.options.preload != "none")
                this.audio.load();
        }
    };
    /**
     * @return Promise
     */
    et2_audio.prototype.play = function () {
        return this.audio.play();
    };
    et2_audio.prototype.pause = function () {
        this.audio.pause();
    };
    et2_audio.prototype.currentTime = function () {
        return this.audio.currentTime;
    };
    et2_audio.prototype.seek = function (_time) {
        this.audio.currentTime = _time;
    };
    et2_audio._attributes = {
        "src": {
            "name": "Audio",
            "type": "string",
            "description": "Source of audio to play"
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
            "description": "Defines that the audio output should be muted"
        },
        "autoplay": {
            "name": "Autoplay",
            "type": "boolean",
            "default": false,
            "description": "Defines if audio will start playing as soon as it is ready"
        },
        "controls": {
            "name": "Control buttons",
            "type": "boolean",
            "default": true,
            "description": "Defines if audio controls, play/pause buttons should be displayed"
        },
        "loop": {
            "name": "Audio loop",
            "type": "boolean",
            "default": false,
            "description": "Defines if the audio should be played repeatedly"
        },
        "autohide": {
            "name": "Auto hide",
            "type": "boolean",
            "default": false,
            "description": "Auto hides audio control bars and only shows a play button, hovering for few seconds will show the whole controlbar."
        },
        "preload": {
            "name": "preload",
            "type": "string",
            "default": 'auto',
            "description": "preloads audio source based on given option. none(do not preload), auto(preload), metadata(preload metadata only)."
        }
    };
    return et2_audio;
}(et2_core_baseWidget_1.et2_baseWidget));
exports.et2_audio = et2_audio;
et2_core_widget_1.et2_register_widget(et2_audio, ["audio"]);
//# sourceMappingURL=et2_widget_audio.js.map