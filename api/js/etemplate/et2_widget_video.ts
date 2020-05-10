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

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	et2_core_interfaces;
	et2_core_baseWidget;
*/

import { et2_baseWidget } from './et2_core_baseWidget'
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_DOMWidget} from "./et2_core_DOMWidget";
import {WidgetConfig, et2_register_widget} from "./et2_core_widget";

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
export class et2_video  extends et2_baseWidget implements et2_IDOMNode
{
    static readonly _attributes: any  = {
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

    video: JQuery<HTMLVideoElement> = null;

    constructor(_parent, _attrs? : WidgetConfig, _child? : object)
    {
        super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_video._attributes, _child || {}));

        //Create Video tag
		this.video = jQuery(document.createElement("video"));
        if (this.options.controls)
        {
            this.video.attr("controls", 1);
        }
        if (this.options.autoplay)
        {
            this.video.attr("autoplay", 1);
        }
        if (this.options.muted)
        {
            this.video.attr("muted", 1);
        }
        if (this.options.video_src)
        {
            this.set_src(this.options.video_src);
        }
        if (this.options.loop)
        {
            this.video.attr("loop", 1);
        }
        this.setDOMNode(this.video[0]);
    }

    /**
     * Set video src
     *
     * @param {string} _value url
     */
    set_src(_value: string) {

        if (_value)
        {
            let source  = jQuery(document.createElement('source'))
                .attr('src',_value)
                .appendTo(this.video);

            if (this.options.src_type)
            {
                source.attr('type', this.options.src_type);
            }
        }
    }

    /**
     * Set autoplay option for video
     * -If autoplay is set, video would be played automatically after the page is loaded
     *
     * @param {string} _value true set the autoplay and false not to set
     */
    set_autoplay(_value: string)
    {
        if (_value)
        {
            this.video.attr("autoplay", _value);
        }
    }

    /**
     * Set controls option for video
     *
     * @param {string} _value true set the autoplay and false not to set
     */
    set_controls(_value: string)
    {
        if (_value)
        {
            this.video.attr("controls", _value);
        }
    }

    /**
     * Set poster attribute in order to specify
     * an image to be shown while video is loading or before user play it
     *
     * @param {string} _url url or image spec like "api/mime128_video"
     */
    set_poster(_url: string)
    {
        if (_url)
        {
            if (_url[0] !== '/' && !_url.match(/^https?:\/\//))
            {
                _url = this.egw().image(_url);
            }
            this.video.attr("poster", _url);
        }
    }

    /**
     * Seek to a time / position
     *
     * @param _vtime in seconds
     */
    public seek_video(_vtime : number)
    {
        this.video[0].currentTime = _vtime;
    }

    /**
     * Play video
     */
    public play_video() : Promise<void>
    {
        return this.video[0].play();
    }

    /**
     * Pause video
     */
    public pause_video()
    {
        this.video[0].pause();
    }

    /**
     * Get current video time / position in seconds
     */
    public currentTime() : number
    {
        return this.video[0].currentTime;
    }
}
et2_register_widget(et2_video, ["video"]);