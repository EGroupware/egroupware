/**
 * EGroupware eTemplate2 - JS Audio tag
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh <hn[at]egroupware.org>
 * @copyright EGroupware GmbH
 */

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	et2_core_interfaces;
	et2_core_baseWidget;
*/

import { et2_baseWidget } from './et2_core_baseWidget'
import {ClassWithAttributes} from "./et2_core_inheritance";
import {WidgetConfig, et2_register_widget} from "./et2_core_widget";
import {et2_IDOMNode} from "./et2_core_interfaces";

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
export class et2_audio  extends et2_baseWidget implements et2_IDOMNode {
	static readonly _attributes: any = {
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

	audio: HTMLAudioElement = null;
	container: HTMLElement = null;

	constructor(_parent, _attrs?: WidgetConfig, _child?: object) {
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_audio._attributes, _child || {}));

		//Create Audio tag
		this.audio = new Audio();
		// Container
		this.container = document.createElement('div');
		this.container.append(this.audio);
		this.container.classList.add('et2_audio');

		if (this.options.autohide) this.container.classList.add('et2_audio_autohide');

		if (this.options.controls) this.audio.setAttribute("controls", '1');

		if (this.options.autoplay) this.audio.setAttribute("autoplay", '1');

		if (this.options.muted) this.audio.setAttribute("muted", '1');

		if (this.options.loop) this.audio.setAttribute("loop", '1');

		if (this.options.preload) this.audio.setAttribute('preload', this.options.preload);

		this.setDOMNode(this.container);
	}

	/**
	 * Set audio source
	 *
	 * @param {string} _value url
	 */
	set_src(_value: string) {
		if (_value) {
			this.audio.setAttribute('src', _value);

			if (this.options.src_type) {
				this.audio.setAttribute('type', this.options.src_type);
			}
			//preload the audio after changing the source/ only if preload is allowed
			if (this.options.preload != "none") this.audio.load();
		}
	}

	/**
	 * @return Promise
	 */
	public play()
	{
		return this.audio.play();
	}

	public pause()
	{
		this.audio.pause();
	}

	public currentTime()
	{
		return this.audio.currentTime;
	}

	public seek(_time)
	{
		this.audio.currentTime = _time;
	}
}
et2_register_widget(et2_audio, ["audio"]);