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
        }
    };

    video: JQuery<HTMLVideoElement|HTMLIFrameElement|HTMLElement> = null;

    youtube: any;
    private static youtube_player_states = {
        unstarted: -1,
        ended: 0,
        playing: 1,
        paused: 2,
        buffering: 3,
        video_cued: 5
    };

    /**
     * keeps internal state of previousTime video played
     * @private
     */
    private _previousTime: number = 0;
    /**
     * keeps the currentTime
     * @private
     */
    private _currentTime : number;
    /**
     * interval defined for youtube type to simulate ontimeupdate html5 event
     * @private
     */
    private _youtubeOntimeUpdateIntrv : number;
    /**
     * youtube iframe api will replace it with iframe
     * @protected
     */
    protected youtubeFrame : JQuery;
    /**
     * prefix id used for addressing youtube player dom
     * @private
     */
    private static youtubePrefixId : string = "frame-";
    constructor(_parent, _attrs? : WidgetConfig, _child? : object)
    {
        super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_video._attributes, _child || {}));

        //Create Video tag
		this.video = jQuery(document.createElement(this._isYoutube()?"div":"video")).addClass('et2_video');

		if (this._isYoutube())
        {
            // this div will be replaced by youtube iframe api when youtube gets ready
            this.youtubeFrame = jQuery(document.createElement('div'))
                .appendTo(this.video)
                .attr('id', et2_video.youtubePrefixId+this.id);

            this.video.attr('id', this.id);

            //Load youtube iframe api
            let tag = document.createElement('script');
            tag.src = "https://www.youtube.com/iframe_api";
            let firstScriptTag = document.getElementsByTagName('script')[0];
            firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
        }

        if (!this._isYoutube() && this.options.controls)
        {
            this.video.attr("controls", 1);
        }
        if (!this._isYoutube() && this.options.autoplay)
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
        let self = this;
        if (_value && !this._isYoutube())
        {
            let source  = jQuery(document.createElement('source'))
                .attr('src',_value)
                .appendTo(this.video);

            if (this.options.src_type)
            {
                source.attr('type', this.options.src_type);
            }
        }
        else if(_value)
        {
            //initiate youtube Api object, it gets called automatically by iframe_api script from the api
            // @ts-ignore
            window.onYouTubeIframeAPIReady = function() {
                // @ts-ignore
                self.youtube = new YT.Player( et2_video.youtubePrefixId+self.id, {
                    height: '400',
                    width: '100%',
                    playerVars: {
                        'autoplay': 0,
                        'controls': 0,
                        'modestbranding': 1,
                        'fs':0,
                        'disablekb': 1,
                        'rel': 0,
                        'iv_load_policy': 0,
                        'cc_load_policy': 0
                    },
                    videoId: _value.split('v=')[1], //TODO get youtube video id
                    events: {
                        'onReady': jQuery.proxy(self._onReady, self),
                        'onStateChange': jQuery.proxy(self._onStateChangeYoutube, self)
                    }
                });
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
        if (_value && !this._isYoutube())
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
        if (_value && !this._isYoutube())
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
        if (this._isYoutube())
        {
            if (this.youtube.seekTo)
            {
                this.youtube.seekTo(_vtime, true);
                this._currentTime = _vtime;
            }
        }
        else
        {
            (<HTMLVideoElement>this.video[0]).currentTime = _vtime;
        }
    }

    /**
     * Play video
     */
    public play_video() : Promise<void>
    {
        if (this._isYoutube())
        {
            let self = this;
            return new Promise<void>(function(resolve){
                if (self.youtube.playVideo)
                {
                    self.youtube.playVideo();
                    resolve();
                }
            });
        }
        return (<HTMLVideoElement>this.video[0]).play();
    }

    /**
     * Pause video
     */
    public pause_video()
    {
        if (this._isYoutube())
        {
            if (this.youtube.pauseVideo)
            {
                this.youtube.pauseVideo();
                this.currentTime(this.youtube.getCurrentTime());
            }
        }
        else
        {
            (<HTMLVideoElement>this.video[0]).pause();
        }
    }

    /**
     * play video
     * ***Internal use and should not be overriden in its extended class***
     */
    public play() : Promise<void>
    {
        return this._isYoutube()  && this.youtube?.playVideo ?this.youtube.playVideo():(<HTMLVideoElement>this.video[0]).play();
    }

    /**
     * Get/set current video time / position in seconds
     * @return returns currentTime
     */
    public currentTime(_time?) : number
    {
        if (_time)
        {
            if (this._isYoutube())
            {
                this.youtube.seekTo(_time);
            }
            else
            {
                (<HTMLVideoElement>this.video[0]).currentTime = _time;
            }
            return this._currentTime = _time;
        }
        if (this._isYoutube())
        {
            if (typeof this._currentTime != 'undefined')
            {
               return this._currentTime;
            }
            return this.youtube?.getCurrentTime ?  this.youtube.getCurrentTime() : 0;
        }
        else
        {
            return (<HTMLVideoElement>this.video[0]).currentTime;
        }
    }

    /**
     * get duration time
     * @return returns duration time
     */
    public duration() : number
    {
        if (this._isYoutube())
        {
            return this.youtube?.getDuration ? this.youtube.getDuration() : 0;
        }
        else
        {
            return (<HTMLVideoElement>this.video[0]).duration;
        }
    }

    /**
     * get pasued
     * @return returns paused flag
     */
    public paused() : boolean
    {
        if (this._isYoutube())
        {
            return this.youtube.getPlayerState() == et2_video.youtube_player_states.paused;
        }
        return (<HTMLVideoElement>this.video[0]).paused;
    }

    /**
     * get ended
     * @return returns ended flag
     */
    public ended() : boolean
    {
        if (this._isYoutube())
        {
            return this.youtube.getPlayerState() == et2_video.youtube_player_states.ended;
        }
        return (<HTMLVideoElement>this.video[0]).ended;
    }

    /**
     * get/set priviousTime
     * @param _time
     * @return returns time
     */
    public previousTime(_time?) : number
    {
        if (_time) this._previousTime = _time;
        return this._previousTime;
    }

    doLoadingFinished(): boolean
    {
        super.doLoadingFinished();
        let self = this;
        if (!this._isYoutube())
        {
            this.video[0].addEventListener("loadedmetadata", function(){
                self._onReady();
            });
            this.video[0].addEventListener("timeupdate", function(){
                self._onTimeUpdate();
            });
        }
        return false;
    }

    public videoLoadnigIsFinished()
    {
        if (this.options.starttime)
        {
            this.seek_video(this.options.starttime);
        }
    }

    private _onReady()
    {
        // need to set the video dom to transformed iframe
        if (this._isYoutube() && this.youtube.getIframe) this.youtubeFrame = jQuery(this.youtube.getIframe());
        let event = document.createEvent("Event");
        event.initEvent('et2_video.onReady.'+this.id, true, true);
        this.video[0].dispatchEvent(event);
    }

    private _onTimeUpdate()
    {
        // update currentTime manually since youtube currentTime might be updated due to the loading
        if (this._isYoutube() && this.youtube.getCurrentTime) this._currentTime = this.youtube.getCurrentTime();

        let event = document.createEvent("Event");
        event.initEvent('et2_video.onTimeUpdate.'+this.id, true, true);
        this.video[0].dispatchEvent(event);
    }

    /**
     * check if the video is a youtube type
     * @return return true if it's a youtube type video
     * @private
     */
    private _isYoutube() : boolean
    {
        return !!this.options.src_type.match('youtube');
    }

    private _onStateChangeYoutube(_data)
    {
        switch (_data.data)
        {
            case et2_video.youtube_player_states.unstarted:
                // do not start the video on initiation
                this.pause_video();
                break;
            case et2_video.youtube_player_states.playing:
                this._youtubeOntimeUpdateIntrv = window.setInterval(jQuery.proxy(this._onTimeUpdate, this), 100);
                break;
            default:
                window.clearInterval(this._youtubeOntimeUpdateIntrv);
        }
        console.log(_data)
    }
}
et2_register_widget(et2_video, ["video"]);