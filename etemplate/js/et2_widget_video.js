/**
 * EGroupware eTemplate2 - JS Description object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Hadi Nategh <hn[at]stylite.de>
 * @copyright Stylite AG
 * @version $Id:$
 */

"use strict";

/*egw:uses
	jquery.jquery;
	et2_core_interfaces;
	et2_core_baseWidget;
*/

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
var et2_video = et2_baseWidget.extend(et2_IDOMNode,
{
	attributes: {
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
		loop: {
			"name": "Video loop",
			"type": "boolean",
			"default": false,
			"description": "Defines if the video should be played repeatedly"
		}
	},
	
	
	/**
	 * Constructor
	 *
	 * @memberOf et2_video
	 */
	init: function() {
		this._super.apply(this, arguments);
		
		//Create Video tag
		this.video = jQuery(document.createElement("video"));
		if (this.options.controls)
		{
			this.video.attr("controls",true);
		}
		if (this.options.autoplay)
		{
			this.video.attr("autoplay",true);
		}
		if (this.options.muted)
		{
			this.video.attr("muted",true);
		}
		if (this.options.video_src)
		{
			this.set_src(this.options.video_src);
		}
		if (this.options.loop)
		{
			this.video.attr("loop",true);
		}
		this.setDOMNode(this.video[0]);
	},

	/**
	 * Set video src
	 *
	 * @param {string} _value url
	 */
	set_src: function(_value) {
		
		if (_value)
		{
			var source  = jQuery(document.createElement('source'))
					.attr('src',_value)
					.appendTo(this.video);
			
			if (this.options.src_type)
			{
				source.attr('type',this.options.src_type);
			}
		}
	},
	
	/**
	 * Set autoplay option for video
	 * -If autoplay is set, video would be played automatically after the page is loaded
	 * 
	 * @param {string} _value true set the autoplay and false not to set
	 */
	set_autoplay: function (_value)
	{
		if (_value)
		{
			this.video.attr("autoplay",_value);
		}
	},
	
	/**
	 * Set controls option for video
	 *
	 * @param {string} _value true set the autoplay and false not to set
	 */
	set_controls: function (_value)
	{
		if (_value)
		{
			this.video.attr("controls",_value);
		}
	},
	
	/**
	 * Set poster attribute in order to specify 
	 * an image to be shown while video is loading or before user play it
	 * 
	 * @param {type} _url
	 */
	set_poster: function (_url)
	{
		if (_url)
		{
			this.video.attr("poster", _url);
		}
	}
	
});

et2_register_widget(et2_video, ["video"]);
