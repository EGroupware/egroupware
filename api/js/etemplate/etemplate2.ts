/**
 * EGroupware eTemplate2 - JS file which contains the complete et2 module
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright EGroupware GmbH 2011-2021
 */


import {et2_widget} from "./et2_core_widget";
import {et2_baseWidget, et2_container} from "./et2_core_baseWidget";
import {EgwApp} from "../jsapi/egw_app";
import {et2_IInput, et2_IPrint, et2_IResizeable, et2_ISubmitListener} from "./et2_core_interfaces";
import {egw} from "../jsapi/egw_global";
import {et2_arrayMgr, et2_readonlysArrayMgr} from "./et2_core_arrayMgr";
import {et2_checkType} from "./et2_core_common";
import {et2_compileLegacyJS} from "./et2_core_legacyJSFunctions";
import {et2_loadXMLFromURL} from "./et2_core_xml";
import {et2_nextmatch, et2_nextmatch_header_bar} from "./et2_extension_nextmatch";
import '../jsapi/egw_json.js';
import {egwIsMobile} from "../egw_action/egw_action_common.js";
import './Layout/Et2Box/Et2Box';
import './Layout/Et2Details/Et2Details';
import './Layout/Et2Tabs/Et2Tab';
import './Layout/Et2Tabs/Et2Tabs';
import './Layout/Et2Tabs/Et2TabPanel';
import './Layout/Et2Tabs/Et2TabsMobile';
import './Et2Avatar/Et2Avatar';
import './Et2Avatar/Et2AvatarGroup';
import './Et2Button/Et2Button';
import './Et2Button/Et2ButtonIcon';
import './Et2Button/Et2ButtonTimestamper';
import './Et2Checkbox/Et2Checkbox';
import './Et2Checkbox/Et2CheckboxReadonly';
import './Et2Date/Et2Date';
import './Et2Date/Et2DateDuration';
import './Et2Date/Et2DateDurationReadonly';
import './Et2Date/Et2DateRange';
import './Et2Date/Et2DateReadonly';
import './Et2Date/Et2DateSinceReadonly';
import './Et2Date/Et2DateTime';
import './Et2Date/Et2DateTimeOnly';
import './Et2Date/Et2DateTimeOnlyReadonly';
import './Et2Date/Et2DateTimeReadonly';
import './Et2Date/Et2DateTimeToday';
import './Et2Description/Et2Description';
import './Et2Dialog/Et2Dialog';
import './Et2DropdownButton/Et2DropdownButton';
import './Expose/Et2ImageExpose';
import './Expose/Et2DescriptionExpose';
import './Et2Favorites/Et2Favorites';
import './Et2Image/Et2Image';
import './Et2Image/Et2AppIcon';
import './Et2Avatar/Et2LAvatar';
import './Et2Link/Et2Link';
import './Et2Link/Et2LinkAdd';
import './Et2Link/Et2LinkAppSelect';
import './Et2Link/Et2LinkEntry';
import './Et2Link/Et2LinkList';
import './Et2Link/Et2LinkSearch';
import './Et2Link/Et2LinkString';
import './Et2Link/Et2LinkTo';
import './Et2Nextmatch/ColumnSelection';
import './Et2Nextmatch/Headers/AccountFilterHeader';
import './Et2Nextmatch/Headers/CustomFilterHeader';
import './Et2Nextmatch/Headers/EntryHeader';
import './Et2Nextmatch/Headers/FilterHeader';
import './Et2Select/Et2Listbox';
import './Et2Select/Et2Select';
import './Et2Select/Et2SelectAccount';
import './Et2Select/Et2SelectCategory';
import './Et2Select/Et2SelectCountry';
import './Et2Select/Et2SelectEmail';
import './Et2Select/Et2SelectReadonly';
import './Et2Select/Et2SelectThumbnail'
import './Et2Select/Tag/Et2Tag';
import './Et2Select/Tag/Et2CategoryTag';
import './Et2Select/Tag/Et2EmailTag';
import './Et2Select/Tag/Et2ThumbnailTag';
import './Et2Spinner/Et2Spinner';
import './Et2Switch/Et2Switch';
import './Et2Textarea/Et2Textarea';
import './Et2Textarea/Et2TextareaReadonly';
import './Et2Textbox/Et2Textbox';
import './Et2Textbox/Et2TextboxReadonly';
import './Et2Textbox/Et2Number';
import './Et2Textbox/Et2NumberReadonly';
import './Et2Colorpicker/Et2Colorpicker';
import './Et2Url/Et2Url';
import './Et2Url/Et2UrlReadonly';
import './Et2Url/Et2UrlEmail';
import './Et2Url/Et2UrlEmailReadonly';
import './Et2Url/Et2UrlPhone';
import './Et2Url/Et2UrlPhoneReadonly';
import './Et2Url/Et2UrlFax';
import './Et2Url/Et2UrlFaxReadonly';
import "./Layout/Et2Split/Et2Split";
import "./Layout/RowLimitedMixin";
import "./Et2Vfs/Et2VfsMime";
import "./Et2Vfs/Et2VfsUid";
import "./Et2Textbox/Et2Password";
import './Et2Textbox/Et2Searchbox';

/* Include all widget classes here, we only care about them registering, not importing anything*/
import './et2_widget_vfs'; // Vfs must be first (before et2_widget_file) due to import cycle
import './et2_widget_template';
import './et2_widget_grid';
import './et2_widget_box';
import './et2_widget_hbox';
import './et2_widget_groupbox';
import './et2_widget_button';
import './et2_widget_entry';
import './et2_widget_textbox';
import './et2_widget_number';
import './et2_widget_selectbox';
import './et2_widget_radiobox';
import './et2_widget_date';
import './et2_widget_dialog';
import './et2_widget_diff';
import './et2_widget_styles';
import './et2_widget_html';
import './et2_widget_htmlarea';
import './et2_widget_taglist';
import './et2_widget_toolbar';
import './et2_widget_tree';
import './et2_widget_historylog';
import './et2_widget_hrule';
import './et2_widget_iframe';
import './et2_widget_file';
import './et2_widget_placeholder';
import './et2_widget_progress';
import './et2_widget_portlet';
import './et2_widget_selectAccount';
import './et2_widget_ajaxSelect';
import './et2_widget_video';
import './et2_widget_audio';
import './et2_widget_barcode';
import './et2_widget_itempicker';
import './et2_widget_script';
import './et2_widget_countdown';
import './et2_extension_nextmatch';
import './et2_extension_customfields';
import './vfsSelectUI';
import {Et2Tabs} from "./Layout/Et2Tabs/Et2Tabs";


/**
 * The etemplate2 class manages a certain etemplate2 instance.
 *
 * @param _container is the DOM-Node into which the DOM-Nodes of this instance
 * 	should be inserted
 * @param _menuaction is the URL to which the form data should be submitted.
 */
export class etemplate2
{
	/**
	 * List of loaded templates
	 */
	public static templates = {};
	/**
	 * List of etemplates by loaded template
	 */
	private static _byTemplate = {};


	private _etemplate_exec_id : string;
	private readonly menuaction : string;
	name : string;
	private uniqueId : void | string;
	private template_base_url : string;

	private _widgetContainer : et2_container;
	private _DOMContainer : HTMLElement;

	private resize_timeout : number | boolean;
	private destroy_session : any;
	private close_prompt : any;
	private _skip_close_prompt : boolean;
	private app_obj : EgwApp;
	app : string;

	/**
	 * Flag indicating that all loading is done, and the etemplate is ready to be used by app.js
	 *
	 * onChange handler checks this to ignore change events before the etemplate is ready
	 */
	private ready : boolean = false;

	constructor(_container : HTMLElement, _menuaction? : string, _uniqueId? : string)
	{
		if(typeof _menuaction == "undefined")
		{
			_menuaction = "EGroupware\\Api\\Etemplate::ajax_process_content";
		}

		// Copy the given parameters
		this._DOMContainer = _container;
		this.menuaction = _menuaction;

		// Unique ID to prevent DOM collisions across multiple templates
		this.uniqueId = _uniqueId ? _uniqueId : (_container.getAttribute("id") ? _container.getAttribute("id").replace('.', '-') : '');

		/**
		 * Preset the object variable
		 * @type {et2_container}
		 */
		this._widgetContainer = null;


		// List of templates (XML) that are known, not always used.  Indexed by id.
		// We share list of templates with iframes and popups
		try
		{
			if(opener && opener.etemplate2)
			{
				etemplate2.templates = opener.etemplate2.templates;
			}
			// @ts-ignore
			else if(top.etemplate2)
			{
				// @ts-ignore
				etemplate2.templates = top.etemplate2.templates;
			}
		}
		catch(e)
		{
			// catch security exception if opener is from a different domain
			console.log('Security exception accessing etemplate2.prototype of opener or top!');
		}
		if(typeof etemplate2.templates == "undefined")
		{
			etemplate2.templates = {};
		}
	}

	/**
	 * Calls the resize event of all widgets
	 *
	 * @param {jQuery.event} e
	 */
	public resize(e)
	{
		const event = e;
		const self = this;
		let excess_height : number | boolean = false;

		// Check if the framework has an specific excess height calculation
		if(typeof window.framework != 'undefined' && typeof window.framework.get_wExcessHeight != 'undefined')
		{
			excess_height = window.framework.get_wExcessHeight(window);
		}

		//@TODO implement getaccess height for other framework and remove
		if(typeof event != 'undefined' && event.type == 'resize')
		{
			if(this.resize_timeout)
			{
				clearTimeout(<number>this.resize_timeout);
			}
			this.resize_timeout = setTimeout(function()
			{
				self.resize_timeout = false;
				if(self._widgetContainer)
				{
					const appHeader = jQuery('#divAppboxHeader');

					//Calculate the excess height
					excess_height = egw(window).is_popup() ? jQuery(window).height() - jQuery(self._DOMContainer).height() - appHeader.outerHeight() + 11 : 0;
					// Recalculate excess height if the appheader is shown
					if(appHeader.length > 0 && appHeader.is(':visible'))
					{
						excess_height -= appHeader.outerHeight() - 9;
					}

					// Do not resize if the template height is bigger than screen available height
					// For templates which have sub templates and they are bigger than screenHeight
					if(screen.availHeight < jQuery(self._DOMContainer).height())
					{
						excess_height = 0;
					}

					// If we're visible, call the "resize" event of all functions which implement the
					// "IResizeable" interface
					if(jQuery(self.DOMContainer).is(":visible"))
					{
						self._widgetContainer.iterateOver(function(_widget)
						{
							if (typeof _widget.resize === 'function')
							{
								_widget.resize(excess_height);
							}
						}, self, et2_IResizeable);
					}
				}
			}, 100);
		}
		// Initial resize needs to be resized immediately (for instance for nextmatch resize)
		else if(this._widgetContainer)
		{
			// Call the "resize" event of all functions which implement the
			// "IResizeable" interface
			this._widgetContainer.iterateOver(function(_widget)
			{
				_widget.resize(excess_height);
			}, this, et2_IResizeable);
		}
	};

	/**
	 * Clears the current instance.
	 * @param _keep_app_object keep app object
	 * @param _keep_session keep server-side et2 session eg. for vfs-select
	 */
	public clear(_keep_app_object? : boolean, _keep_session? : boolean)
	{
		jQuery(this._DOMContainer).trigger('clear');

		// Remove any handlers on window (resize)
		if(this.uniqueId)
		{
			jQuery(window).off("." + this.uniqueId);
		}

		// call our destroy_session handler, if it is not already unbind, and unbind it after
		if(this.destroy_session)
		{
			if(!_keep_session)
			{
				this.destroy_session();
			}
			this.unbind_unload();
		}
		if(this._widgetContainer != null)
		{
			// Un-register handler
			this._widgetContainer.egw().unregisterJSONPlugin(this.handle_assign, this, 'assign');

			this._widgetContainer.destroy();
			this._widgetContainer = null;
		}
		jQuery(this._DOMContainer).empty();

		// Remove self from the index
		for(const name in etemplate2.templates)
		{
			if(typeof etemplate2._byTemplate[name] == "undefined")
			{
				continue;
			}
			for(let i = 0; i < etemplate2._byTemplate[name].length; i++)
			{
				if(etemplate2._byTemplate[name][i] === this)
				{
					etemplate2._byTemplate[name].splice(i, 1);
				}
			}
		}

		// If using a private app object, remove all of them
		if(!_keep_app_object && this.app_obj !== window.app)
		{
			for(const app_name in this.app_obj)
			{
				if(this.app_obj[app_name] instanceof EgwApp)
				{
					this.app_obj[app_name].destroy(this.app_obj);
				}
			}
		}
	}

	get widgetContainer() : et2_container
	{
		return this._widgetContainer;
	}

	get DOMContainer() : HTMLElement
	{
		return this._DOMContainer;
	}

	get etemplate_exec_id() : string
	{
		return this._etemplate_exec_id;
	}

	get isReady() : boolean
	{
		return this.ready;
	}

	/**
	 * Creates an associative array containing the data array managers for each part
	 * of the associative data array. A part is something like "content", "readonlys"
	 * or "sel_options".
	 *
	 * @param {object} _data object with values for attributes content, sel_options, readonlys, modifications
	 */
	private _createArrayManagers(_data)
	{
		if(typeof _data == "undefined")
		{
			_data = {};
		}

		// Create all neccessary _data entries
		const neededEntries = ["content", "sel_options", "readonlys", "modifications",
			"validation_errors"];
		for(let i = 0; i < neededEntries.length; i++)
		{
			if(typeof _data[neededEntries[i]] == "undefined" || !_data[neededEntries[i]])
			{
				egw.debug("log", "Created not passed entry '" + neededEntries[i] +
					"' in data array.");
				_data[neededEntries[i]] = {};
			}
		}

		const result = {};

		// Create an array manager object for each part of the _data array.
		for(const key in _data)
		{
			switch(key)
			{
				case "etemplate_exec_id":	// already processed
				case "app_header":
					break;
				case "readonlys":
					result[key] = new et2_readonlysArrayMgr(_data[key]);
					result[key].perspectiveData.owner = this._widgetContainer;
					break;
				default:
					result[key] = new et2_arrayMgr(_data[key]);
					result[key].perspectiveData.owner = this._widgetContainer;
			}
		}

		return result;
	}

	/**
	 * Bind our unload handler to notify server that eT session/request no longer needed
	 *
	 * We only bind, if we have an etemplate_exec_id: not the case for pure client-side
	 * calls, eg. via et2_dialog.
	 */
	bind_unload()
	{
		// Prompt user to save for dirty popups
		if(window !== egw_topWindow() && !this.close_prompt)
		{
			this.close_prompt = this._close_changed_prompt.bind(this);
			window.addEventListener("beforeunload", this.close_prompt);
		}
		if(this._etemplate_exec_id)
		{
			this.destroy_session = jQuery.proxy(function(ev)
			{
				// need to use async === "keepalive" to run via beforeunload
				egw.json("EGroupware\\Api\\Etemplate::ajax_destroy_session",
					[this._etemplate_exec_id], null, null, "keepalive").sendRequest();
			}, this);

			window.addEventListener("beforeunload", this.destroy_session);
		}
	}

	private _close_changed_prompt(e : BeforeUnloadEvent)
	{
		if(this._skip_close_prompt || !this.isDirty())
		{
			return;
		}

		// Cancel the event
		e.preventDefault(); // If you prevent default behavior in Mozilla Firefox prompt will always be shown

		// Chrome requires returnValue to be set
		e.returnValue = '';
	}

	public skip_close_prompt(skip = true)
	{
		this._skip_close_prompt = skip;
	}

	/**
	 * Unbind our unload handler
	 */
	unbind_unload()
	{
		window.removeEventListener("beforeunload", this.destroy_session);
		window.removeEventListener("beforeunload", this.close_prompt);
		if(window.onbeforeunload === this.destroy_session)
		{
			window.onbeforeunload = null;
		}
		else
		{
			const onbeforeunload = window.onbeforeunload;
			window.onbeforeunload = null;
			// bind unload handler again (can NOT do it direct, as this would be quick enough to be still triggered!)
			window.setTimeout(function()
			{
				window.onbeforeunload = onbeforeunload;
			}, 100);
		}
		delete this.destroy_session;
	}

	/**
	 * Download a URL not triggering our unload handler and therefore destroying our et2 request
	 *
	 * We use a new anchor element to avoid not destroying other etemplates as well, which
	 * is what happens if we use window.location
	 *
	 * @param {string} _url
	 */
	download(_url)
	{
		const a = document.createElement('a');
		a.href = _url;
		a.download = 'download';

		// Programmatically trigger a click on the anchor element
		a.click();
	}

	/**
	 * Loads the template from the given URL and sets the data object
	 *
	 * @param {string} _name name of template
	 * @param {string} _url url to load template
	 * @param {object} _data object with attributes content, langRequire, etemplate_exec_id, ...
	 * @param {function} _callback called after template is loaded
	 * @param {object} _app local app object
	 * @param {boolean} _no_et2_ready true: do not send et2_ready, used by et2_dialog to not overwrite app.js et2 object
	 * @param {string} _open_target flag of string to distinguish between tab target and normal app object
	 * @return Promise
	 */
	async load(_name, _url, _data, _callback?, _app?, _no_et2_ready?, _open_target?)
	{
		this.ready = false;
		let app = _app || window.app;
		this.name = _name;	// store top-level template name to have it available in widgets
		// store template base url, in case initial template is loaded via webdav, to use that for further loads too
		// need to split off domain first, as it could contain app-name part of template eg. stylite.report.xet and https://my.stylite.de/egw/...
		if(_url && _url[0] != '/')
		{
			this.template_base_url = _url.match(/https?:\/\/[^/]+/).shift();
			_url = _url.split(this.template_base_url)[1];
		}
		else
		{
			this.template_base_url = '';
		}
		this.template_base_url += _url.split(_name.split('.').shift())[0];

		egw().debug("info", "Loaded data", _data);
		const currentapp = this.app = _data.currentapp || egw().app_name();
		const appname = _name.split('.')[0];
		// if no app object provided and template app is not currentapp (eg. infolog CRM view)
		// create private app object / closure with just classes / prototypes
		if(!_app && appname && appname != currentapp || _open_target)
		{
			app = {classes: window.app.classes};
		}
		// remember used app object, to eg. use: onchange="widget.getInstanceMgr().app_object[app].callback()"
		this.app_obj = app;

		// extract $content['msg'] and call egw.message() with it
		const msg = _data.content.msg;
		if(typeof msg != 'undefined')
		{
			egw(window).message(msg);
			delete _data.content.msg;
		}

		// Register a handler for AJAX responses
		egw(currentapp, window).registerJSONPlugin(this.handle_assign, this, 'assign');

		if(egw.debug_level() >= 3)
		{
			if(console.groupCollapsed)
			{
				egw.window.console.groupCollapsed("Loading %s into ", _name, '#' + this._DOMContainer.id);
			}
		}
		// Timing & profiling on debug level 'log' (4)
		if(egw.debug_level() >= 4)
		{
			if(console.time)
			{
				console.time(_name);
			}
			if(console.profile)
			{
				console.profile(_name);
			}
			var start_time = (new Date).getTime();
		}

		// require necessary translations from server AND the app.js file, if not already loaded
		let promisses = [window.egw_ready];	// to wait for legacy-loaded JS
		if(Array.isArray(_data.langRequire))
		{
			promisses.push(egw(currentapp, window).langRequire(window, _data.langRequire));
		}
		if(appname && typeof app[appname] !== "object")
		{
			/*
			Don't have the app.ts code - load it here and delay load until its ready
						promisses.push(import(egw.webserverUrl + "/" + appname + "/js/app.min.js?" + ((new Date).valueOf() / 86400 | 0).toString())
							.then(() =>
							{
								if(typeof app.classes[appname] === "undefined")
								{
									throw new Error("app.classes." + appname + " not found!");
								}
							}));
			*/

		}
		return Promise.all(promisses).catch((err) =>
		{
			console.log("et2.load(): error loading lang-files and app.js: " + err.message);
		}).then(() =>
		{
			this.clear();

			// Initialize application js
			let app_callback = null;
			// Only initialize once
			// new app class with constructor function in app.classes[appname]
			if(typeof app[appname] !== 'object' && typeof app.classes[appname] == 'function')
			{
				app[appname] = new app.classes[appname]();
			}
			else if(appname && typeof app[appname] !== "object")
			{
				egw.debug("warn", "Did not load '%s' JS object", appname);
			}
			// If etemplate current app does not match app owning the template,
			// initialize the current app too
			if(typeof app[this.app] !== 'object' && typeof app.classes[this.app] == 'function')
			{
				app[this.app] = new app.classes[this.app]();
			}
			if(typeof app[appname] == "object")
			{
				app_callback = function(_et2, _name)
				{
					app[appname].et2_ready(_et2, _name);
				};
			}

			// Create the basic widget container and attach it to the DOM
			this._widgetContainer = new et2_container(null);
			this._widgetContainer.setApiInstance(egw(currentapp, egw.elemWindow(this._DOMContainer)));
			this._widgetContainer.setInstanceManager(this);
			this._widgetContainer.setParentDOMNode(this._DOMContainer);

			// store the id to submit it back to server
			if(_data)
			{
				this._etemplate_exec_id = _data.etemplate_exec_id;
				// set app_header
				if(typeof _data.app_header == 'string')
				{
					// @ts-ignore
					window.egw_app_header(_data.app_header);
				}
				// bind our unload handler
				this.bind_unload();
			}

			const _load = function()
			{
				egw.debug("log", "Loading template...");
				if(egw.debug_level() >= 4 && console.timeStamp)
				{
					console.timeStamp("Begin rendering template");
					console.time("Template load");
					console.time("loadFromXML");
				}

				// Add into indexed list - do this before, so anything looking can find it,
				// even if it's not loaded
				if(typeof etemplate2._byTemplate[_name] == "undefined")
				{
					etemplate2._byTemplate[_name] = [];
				}
				etemplate2._byTemplate[_name].push(this);

				// Read the XML structure of the requested template
				this._widgetContainer.loadFromXML(etemplate2.templates[this.name]);
				console.timeEnd("loadFromXML");
				console.time("deferred");

				// List of Promises from widgets that are not quite fully loaded
				const deferred = [];

				// Inform the widget tree that it has been successfully loaded.
				this._widgetContainer.loadingFinished(deferred);

				// Connect to the window resize event
				jQuery(window).on("resize." + this.uniqueId, this, function(e)
				{
					e.data.resize(e);
				});

				if(egw.debug_level() >= 3 && console.groupEnd)
				{
					if(console.timeStamp)
					{
						console.timeStamp("loading finished, waiting for deferred");
					}
					egw.window.console.groupEnd();
				}

				// Wait for everything to be loaded, then finish it up.  Use timeout to give anything else a chance
				// to run.
				setTimeout(() =>
				{
					Promise.all(deferred).then(() =>
					{

						console.timeEnd("deferred");
						console.timeStamp("Deferred done");
						// Clear dirty now that it's all loaded
						this.widgetContainer.iterateOver((_widget) =>
						{
							_widget.resetDirty();
						}, this, et2_IInput);
						egw.debug("log", "Finished loading %s, triggering load event", _name);

						if(typeof window.framework != 'undefined' && typeof window.framework.et2_loadingFinished != 'undefined')
						{
							//Call loading finished method of the framework with local window
							window.framework.et2_loadingFinished(egw(window).window);
						}
						// Trigger the "resize" event
						this.resize();

						// Automatically set focus to first visible input for popups
						if(this._widgetContainer._egw.is_popup() && jQuery('[autofocus]', this._DOMContainer).focus().length == 0)
						{
							this.focusOnFirstInput();
						}

						// Now etemplate is ready for others to interact with (eg: app.js)
						this.ready = true;

						// Tell others about it
						if(typeof _callback == "function")
						{
							_callback.call(window, this, _name);
						}
						if(app_callback && _callback != app_callback && !_no_et2_ready)
						{
							app_callback.call(window, this, _name);
						}
						if(appname && appname != this.app && typeof app[this.app] == "object" && !_no_et2_ready)
						{
							// Loaded a template from a different application?
							// Let the application that loaded it know too
							app[this.app].et2_ready(this, this.name);
						}

						// Dispatch an event that will bubble through shadow DOM boundary (pass through custom elements)
						this._DOMContainer.dispatchEvent(new CustomEvent('load', {
							bubbles: true,
							composed: true,
							detail: this
						}));

						if(etemplate2.templates[this.name].attributes.onload)
						{
							let onload = et2_checkType(etemplate2.templates[this.name].attributes.onload.value, 'js', 'onload', {});
							if(typeof onload === 'string')
							{
								onload = et2_compileLegacyJS(onload, this, this._widgetContainer);
							}
							onload.call(this._widgetContainer);
						}

						// Profiling
						if(egw.debug_level() >= 4)
						{
							if(console.timeEnd)
							{
								console.timeEnd(_name);
							}
							if(console.profileEnd)
							{
								console.profileEnd(_name);
							}
							const end_time = (new Date).getTime();
							let gen_time_div = jQuery('#divGenTime_' + appname);
							if(!gen_time_div.length)
							{
								gen_time_div = jQuery('.pageGenTime');
							}
							gen_time_div.find('.et2RenderTime').remove();
							gen_time_div.append('<span class="et2RenderTime">' + egw.lang('eT2 rendering took %1s', '' + ((end_time - start_time) / 1000)) + '</span>');
						}
					});
				});
			};


			// Load & process
			try
			{
				if(etemplate2.templates[_name])
				{
					// Set array managers first, or errors will happen
					this._widgetContainer.setArrayMgrs(this._createArrayManagers(_data));

					// Already have it
					_load.apply(this, []);
					return;
				}
			}
			catch(e)
			{
				// weird security exception in IE denying access to template cache in opener
				if(e.message == 'Permission denied')
				{
					etemplate2.templates = {};
				}
				// other error eg. in app.js et2_ready or event handlers --> rethrow it
				else
				{
					throw e;
				}
			}
			// Split the given data into array manager objects and pass those to the
			// widget container - do this here because file is loaded async
			this._widgetContainer.setArrayMgrs(this._createArrayManagers(_data));

			// Asynchronously load the XET file
			return et2_loadXMLFromURL(_url, function(_xmldoc)
			{

				// Scan for templates and store them
				for(let i = 0; i < _xmldoc.childNodes.length; i++)
				{
					const template = _xmldoc.childNodes[i];
					if(template.nodeName.toLowerCase() != "template")
					{
						continue;
					}
					etemplate2.templates[template.getAttribute("id")] = template;
					if(!_name)
					{
						this.name = template.getAttribute("id");
					}
				}
				_load.apply(this, []);
			}, this);
		});
	}

	public focusOnFirstInput()
	{
		const $input = jQuery('input:visible,et2-textbox:visible,et2-select-email:visible', this.DOMContainer)
			// Date fields open the calendar popup on focus
			.not('.et2_date')
			.filter(function()
			{
				// Skip inputs that are out of tab ordering
				const $this = jQuery(this);
				return !$this.attr('tabindex') || parseInt($this.attr('tabIndex')) >= 0;
			}).first();

		// mobile device, focus only if the field is empty (usually means new entry)
		// should focus always for non-mobile one
		if(egwIsMobile() && $input.val() == "" || !egwIsMobile())
		{
			$input.focus();
		}
	}

	/**
	 * Check if template contains any dirty (unsaved) content
	 *
	 * @returns {Boolean}
	 */
	public isDirty()
	{
		let dirty = false;
		this._widgetContainer?.iterateOver(function(_widget)
		{
			if(_widget.isDirty && _widget.isDirty())
			{
				console.info(_widget.id + " is dirty", _widget);
				dirty = true;
			}
		}, this);

		return dirty;
	}

	/**
	 * Submit the et2_container form to a blank iframe in order to activate browser autocomplete
	 */
	autocomplete_fixer()
	{
		const self = this;
		const form = self._DOMContainer;

		// Safari always do the autofill for password field regardless of autocomplete = off
		// and since there's no other way to switch the autocomplete of, we should switch the
		// form autocomplete off (e.g. compose dialog, attachment password field)
		if(navigator.userAgent.match(/safari/i) && !navigator.userAgent.match(/chrome/i)
			&& jQuery('input[type="password"]').length > 0)
		{
			return;
		}

		if(form)
		{
			// Stop submit propagation in order to not fire other possible submit events
			form.onsubmit = function(e)
			{
				e.stopPropagation();
			};

			// Firefox give a security warning when transmitting to "about:blank" from a https site
			// we work around that by giving existing etemplate/empty.html url
			// Safari shows same warning, thought Chrome userAgent also includes Safari
			if(navigator.userAgent.match(/(firefox|safari|iceweasel)/i) && !navigator.userAgent.match(/chrome/i))
			{
				jQuery(form).attr({action: egw.webserverUrl + '/api/templates/default/empty.html', method: 'post'});
			}
			// need to trigger submit because submit() would not trigger onsubmit event
			// since the submit does not get fired directly via user interaction.
			jQuery(form).trigger('submit');
		}
	}

	private _set_button(button, values)
	{
		if(typeof button == 'string')
		{
			button = this._widgetContainer.getWidgetById(button);
		}
		// Button parameter used for submit buttons in datagrid
		// TODO: This should probably go in nextmatch's getValues(), along with selected rows somehow.
		// I'm just not sure how.
		if(button && !values.button)
		{
			let i;
			values.button = {};
			const path = button.getPath();
			let target = values;
			for(i = 0; i < path.length; i++)
			{
				if(!values[path[i]])
				{
					values[path[i]] = {};
				}
				target = values[path[i]];
			}
			if(target != values || button.id.indexOf('[') != -1 && path.length == 0)
			{
				let indexes = button.id.split('[');
				if(indexes.length > 1)
				{
					indexes = [indexes.shift(), indexes.join('[')];
					indexes[1] = indexes[1].substring(0, indexes[1].length - 1);
					const children = indexes[1].split('][');
					if(children.length)
					{
						indexes = jQuery.merge([indexes[0]], children);
					}
				}
				let idx = '';
				for(i = 0; i < indexes.length; i++)
				{
					idx = indexes[i];
					if(!target[idx] || target[idx]['$row_cont'])
					{
						target[idx] = i < indexes.length - 1 ? {} : true;
					}
					target = target[idx];
				}
			}
			else if(typeof values.button == 'undefined' || jQuery.isEmptyObject(values.button))
			{
				delete values.button;
				values[button.id] = true;
			}
		}
	}

	/**
	 * Check if there is an invalid widget / all widgets are valid
	 *
	 * @param container
	 * @param values
	 * @return Promise<et2_widget>|Promise<Et2Widget>|null
	 */
	async isInvalid(container : et2_container | undefined, values : object | undefined) : Promise<et2_ISubmitListener> | null
	{
		if(typeof container === 'undefined')
		{
			container = this._widgetContainer;
		}
		if(typeof values === 'undefined')
		{
			values = this.getValues(container);
		}
		let invalid = [];
		container.iterateOver(function(_widget)
		{
			let submit = _widget.submit(values);
			if(submit === false)
			{
				invalid.push(_widget);
			}
			else if(submit instanceof Promise)
			{
				invalid.push(submit.then(function(ok)
				{
					return ok ? false : this;
				}.bind(_widget)));
			}
		}, this, et2_ISubmitListener);

		return Promise.all(invalid);
	}

	/**
	 * Submit form via ajax
	 *
	 * @param {(et2_button|string)} button button widget or string with id
	 * @param {boolean|string} async true: do an asynchronious submit, string: spinner message (please wait...)
	 * default is asynchronoush with message
	 * @param {boolean} no_validation - Do not do individual widget validation, just submit their current values
	 * @param {et2_widget|undefined} _container container to submit, default whole template
	 * @return {boolean} true if submit was send, false if eg. validation stoped submit
	 */
	submit(button, async, no_validation, _container)
	{
		const api = this._widgetContainer.egw();

		if(typeof no_validation == 'undefined')
		{
			no_validation = false;
		}
		const container = _container || this._widgetContainer;

		// Get the form values
		const values = this.getValues(container);

		const doSubmit = () =>
		{
			if(typeof async == 'undefined' || typeof async == 'string')
			{
				api.loading_prompt('et2_submit_spinner', true, api.lang(typeof async == 'string' ? async : 'Please wait...'));
				async = true;
			}
			if(button)
			{
				this._set_button(button, values);
			}

			// Create the request object
			if(this.menuaction)
			{

				//Autocomplete
				this.autocomplete_fixer();

				// unbind our session-destroy handler, as we are submitting
				this.unbind_unload();


				const request = api.json(this.menuaction, [this._etemplate_exec_id, values, no_validation], function()
				{
					api.loading_prompt('et2_submit_spinner', false);
				}, this, async);
				request.sendRequest();
			}
			else
			{
				this._widgetContainer.egw().debug("warn", "Missing menuaction for submit.  Values: ", values);
			}
		}

		// Trigger the submit event
		let canSubmit = true;
		let invalid = null;
		if(!no_validation)
		{
			canSubmit = !(invalid = this.isInvalid(container, values));

			invalid?.then((widgets) =>
			{
				let invalid_widgets = widgets.filter((widget) => widget);

				if(invalid_widgets.length && !(invalid_widgets[0] instanceof et2_widget))
				{
					// Handle validation_error (messages coming back from server as a response) if widget is children of a tabbox
					let tmpWidget = invalid_widgets[0];
					while(tmpWidget.getParent() && tmpWidget.getType() !== 'ET2-TABBOX')
					{
						tmpWidget = tmpWidget.getParent();
					}
					//Activate the tab where the widget with validation error is located
					if(tmpWidget.getType() === 'ET2-TABBOX')
					{
						(<Et2Tabs><unknown>tmpWidget).activateTab(invalid_widgets[0]);
					}
					// scroll the widget into view
					if(typeof tmpWidget.scrollIntoView === 'function')
					{
						tmpWidget.scrollIntoView();
					}
				}
				else
				{
					doSubmit();
				}
			});
		}
		else
		{
			doSubmit();
		}
		return canSubmit;
	}

	/**
	 * Does a full form post submit necessary for downloads
	 *
	 * Only use this one if you need it, use the ajax submit() instead.
	 * It ensures eT2 session continues to exist on server by unbinding unload handler and rebinding it.
	 *
	 * @param {(et2_button|string)} button button widget or string with id
	 */
	postSubmit(button)
	{
		// Get the form values
		const values = this.getValues(this._widgetContainer);

		// Trigger the submit event
		let canSubmit = true;
		this._widgetContainer.iterateOver(function(_widget)
		{
			if(_widget.submit(values) === false)
			{
				canSubmit = false;
			}
		}, this, et2_ISubmitListener);

		if(canSubmit)
		{
			if(button)
			{
				this._set_button(button, values);
			}

			// unbind our session-destroy handler, as we are submitting
			this.unbind_unload();

			const form = jQuery("<form id='form' action='" + egw().webserverUrl +
				"/index.php?menuaction=" + this._widgetContainer.egw().getAppName() + ".EGroupware\\Api\\Etemplate.process_exec&ajax=true' method='POST'>");

			const etemplate_id = jQuery(document.createElement("input"))
				.attr("name", 'etemplate_exec_id')
				.attr("type", 'hidden')
				.val(this._etemplate_exec_id)
				.appendTo(form);

			const input = document.createElement("input");
			input.type = "hidden";
			input.name = 'value';
			input.value = egw().jsonEncode(values);
			form.append(input);
			form.appendTo(jQuery('body')).submit();

			// bind unload handler again (can NOT do it direct, as this would be quick enough to be still triggered!)
			window.setTimeout(jQuery.proxy(this.bind_unload, this), 100);
		}
	}

	/**
	 * Fetches all input element values and returns them in an associative
	 * array. Widgets which introduce namespacing can use the internal _target
	 * parameter to add another layer.
	 *
	 * @param {et2_widget} _root widget to start iterating
	 */
	getValues(_root : et2_widget)
	{
		const result = {};

		// Iterate over the widget tree
		_root.iterateOver(function(_widget)
		{
			// The widget must have an id to be included in the values array
			if(_widget.id === undefined || _widget.id === "")
			{
				return;
			}

			// Get the path to the node we have to store the value at
			let path = _widget.getPath();

			// check if id contains a hierachical name, eg. "button[save]"
			let id = _widget.id || "";
			let indexes = id?.split('[');
			if(indexes?.length > 1)
			{
				indexes = [indexes.shift(), indexes.join('[')];
				indexes[1] = indexes[1].substring(0, indexes[1].length - 1);
				const children = indexes[1].split('][');
				if(children.length)
				{
					indexes = jQuery.merge([indexes[0]], children);
				}
				path = path.concat(indexes);
				// Take the last one as the ID
				id = path.pop();
			}

			// Set the _target variable to that node
			let _target = result;
			for(var i = 0; i < path.length; i++)
			{
				// Create a new object for not-existing path nodes
				if(typeof _target[path[i]] === 'undefined')
				{
					_target[path[i]] = {};
				}

				// Check whether the path node is really an object
				if(typeof _target[path[i]] === 'object')
				{
					_target = _target[path[i]];
				}
				else
				{
					egw.debug("error", "ID collision while writing at path " +
						"node '" + path[i] + "'");
				}
			}

			// Handle arrays, eg radio[]
			if(id === "")
			{
				id = typeof _target == "undefined" ? 0 : Object.keys(_target).length;
			}

			const value = _widget.getValue();

			// Check whether the entry is really undefined
			if(typeof _target[id] != "undefined" && (typeof _target[id] != 'object' || typeof value != 'object'))
			{
				// Don't warn about children of nextmatch header - they're part of nm value
				if(!_widget.getParent().instanceOf(et2_nextmatch_header_bar))
				{
					egw.debug("warn", _widget, "Overwriting value of '" + _widget.id +
						"', id exists twice!");
				}
			}

			// Store the value of the widget and reset its dirty flag
			if(value !== null)
			{
				// Merge, if possible (link widget)
				if(typeof _target[id] == 'object' && typeof value == 'object')
				{
					_target[id] = jQuery.extend({}, _target[id], value);
				}
				else
				{
					_target[id] = value;
				}
			}
			else if(jQuery.isEmptyObject(_target))
			{
				// Avoid sending back empty sub-arrays
				_target = result;
				for(var i = 0; i < path.length - 1; i++)
				{
					_target = _target[path[i]];
				}
				delete _target[path[path.length - 1]];
			}
			_widget.resetDirty();

		}, this, et2_IInput);

		egw().debug("info", "Value", result);
		return result;
	}

	/**
	 * "Intelligently" refresh the template based on the given ID
	 *
	 * Rather than blindly re-load the entire template, we try to be a little smarter about it.
	 * If there's a message provided, we try to find where it goes and set it directly.  Then
	 * we look for a nextmatch widget, and tell it to refresh its data based on that ID.
	 *
	 * @see egw_message.refresh()
	 *
	 * @param {string} msg message to try to display.  eg: "Entry added" (not used anymore, handeled by egw_refresh and egw_message)
	 * @param {string} app app-name
	 * @param {(string|null)} id application specific entry ID to try to refresh
	 * @param {(string|null)} type type of change.  One of 'update','edit', 'delete', 'add' or null
	 * @return {boolean} true if nextmatch found and refreshed, false if not
	 */
	refresh(msg, app, id, type)
	{
		// msg, app;	// unused but required by function signature
		let refresh_done = false;

		// Refresh nextmatches
		this._widgetContainer.iterateOver(function(_widget)
		{
			// Trigger refresh
			_widget.refresh(id, type);
			refresh_done = true;
		}, this, et2_nextmatch);

		return refresh_done;
	}

	/**
	 * "Intelligently" refresh a given app
	 *
	 * @see egw_message.refresh()
	 *
	 * @param {string} _msg message to try to display.  eg: "Entry added" (not used anymore, handeled by egw_refresh and egw_message)
	 * @param {string} _app app-name
	 * @param {(string|null)} _id application specific entry ID to try to refresh
	 * @param {(string|null)} _type type of change.  One of 'update','edit', 'delete', 'add' or null
	 * @return {boolean} true if nextmatch found and refreshed, false if not
	 */
	static app_refresh(_msg, _app, _id, _type)
	{
		let refresh_done = false;
		let app = _app.split('-');
		const et2 = etemplate2.getByApplication(app[0]);
		for(let i = 0; i < et2.length; i++)
		{
			if(app[1])
			{
				if(et2[i]['uniqueId'].match(_app))
				{
					refresh_done = et2[i].refresh(_msg, app[0], _id, _type) || refresh_done;
					break;
				}
			}
			else
			{
				refresh_done = et2[i].refresh(_msg, app[0], _id, _type) || refresh_done;
			}
		}
		return refresh_done;
	}

	/**
	 * "Intelligently" print a given etemplate
	 *
	 * Mostly, we let the nextmatch change how many rows it's showing, so you don't
	 * get just one printed page.
	 *
	 * @return {Deferred[]} A list of Deferred objects that must complete before
	 *  actual printing can begin.
	 */
	public print()
	{
		// Sometimes changes take time
		const deferred = [];

		// Skip hidden etemplates
		if(jQuery(this._DOMContainer).filter(':visible').length === 0)
		{
			return [];
		}

		// Allow any widget to change for printing
		this._widgetContainer.iterateOver(function(_widget)
		{
			// Skip widgets from a different etemplate (home)
			if(_widget.getInstanceManager() != this)
			{
				return;
			}

			// Skip hidden widgets
			if(jQuery(_widget.getDOMNode()).filter(':visible').length === 0)
			{
				return;
			}

			const result = _widget.beforePrint();
			if(typeof result == "object")
			{
				deferred.push(result);
			}
		}, this, et2_IPrint);

		return deferred;
	}


	// Some static things to make getting into widget context a little easier //


	/**
	 * Get a list of etemplate2 objects that loaded the given template name
	 *
	 * @param template String Name of the template that was loaded
	 *
	 * @return Array list of etemplate2 that have that template
	 */

	public static getByTemplate(template) : etemplate2[]
	{
		if(typeof etemplate2._byTemplate[template] != "undefined")
		{
			return etemplate2._byTemplate[template];
		}
		else
		{
			// Return empty array so you can always iterate over results
			return [];
		}
	}

	/**
	 * Get a list of etemplate2 objects that are associated with the given application
	 *
	 * "Associated" is determined by the first part of the template
	 *
	 * @param {string} app app-name
	 * @return {etemplate2[]} list of etemplate2 that have that app as the first part of their loaded template
	 */
	public static getByApplication(app) : etemplate2[]
	{
		let list = [];
		for(let name in etemplate2._byTemplate)
		{
			if(name.indexOf(app + ".") == 0)
			{
				list = list.concat(etemplate2._byTemplate[name]);
			}
		}
		return list;
	}

	/**
	 * Get a etemplate2 object from the given DOM ID
	 *
	 * @param {string} id DOM ID of the container node
	 * @returns {etemplate2|null}
	 */
	public static getById(id) : etemplate2|null
	{
		for(let name in etemplate2._byTemplate)
		{
			for(let i = 0; i < etemplate2._byTemplate[name].length; i++)
			{
				const et = etemplate2._byTemplate[name][i];

				if(et._DOMContainer.getAttribute("id") == id)
				{
					return et;
				}
			}
		}
		return null;
	}

	/**
	 * Plugin for egw.json type "et2_load"
	 *
	 * @param _type
	 * @param _response
	 * @returns Promise
	 */
	public static async handle_load(_type, _response)
	{
		// Check the parameters
		const data = _response.data;

		// handle Api\Framework::refresh_opener()
		if(Array.isArray(data['refresh-opener']))
		{
			if(window.opener)// && typeof window.opener.egw_refresh == 'function')
			{
				var egw = window.egw(opener);
				egw.refresh.apply(egw, data['refresh-opener']);
			}
		}
		var egw = window.egw(window);

		// need to set app_header before message, as message temp. replaces app_header
		if(typeof data.data == 'object' && typeof data.data.app_header == 'string')
		{
			egw.app_header(data.data.app_header, data.data.currentapp || null);
			delete data.data.app_header;
		}

		// handle Api\Framework::message()
		if(jQuery.isArray(data.message))
		{
			egw.message.apply(egw, data.message);
		}

		// handle Api\Framework::window_close(), this will terminate execution
		if(data['window-close'])
		{
			if(typeof data['window-close'] == 'string' && data['window-close'] !== 'true')
			{
				alert(data['window-close']);
			}
			egw.close();
			return true;
		}

		// handle Api\Framework::window_focus()
		if(data['window-focus'])
		{
			window.focus();
		}

		// handle framework.setSidebox calls
		if(window.framework && jQuery.isArray(data.setSidebox))
		{
			if(data['fw-target'])
			{
				data.setSidebox[0] = data['fw-target'];
			}

			window.framework.setSidebox.apply(window.framework, data.setSidebox);
		}

		// regular et2 re-load
		if(typeof data.url == "string" && typeof data.data === 'object')
		{
			// @ts-ignore
			if(this && typeof this.load == 'function')
			{
				// Called from etemplate
				// set id in case serverside returned a different template
				this._DOMContainer.id = this.uniqueId = data.DOMNodeID;
				// @ts-ignore
				return this.load(data.name, data.url, data.data);
			}
			else
			{
				// Not etemplate
				const node = document.getElementById(data.DOMNodeID);
				let uniqueId = data.DOMNodeID;
				if(node)
				{
					if(node.children.length)
					{
						// Node has children already?  Check for loading over an
						// existing etemplate
						const old = etemplate2.getById(node.id);
						if(old)
						{
							old.clear();
						}
					}
					if(data['open_target'] && !uniqueId.match(data['open_target']))
					{
						uniqueId = data.DOMNodeID.replace('.', '-') + '-' + data['open_target'];
					}
					const et2 = new etemplate2(node, data.data.menuaction, uniqueId);
					return et2.load(data.name, data.url, data.data, null, null, null, data['fw-target']);
				}
				else
				{
					egw.debug("error", "Could not find target node %s", data.DOMNodeId);
				}
			}
		}

		throw("Error while parsing et2_load response");
	}

	/**
	 * Plugin for egw.json type "et2_validation_error"
	 *
	 * @param _type
	 * @param _response
	 */
	public static handle_validation_error(_type, _response)
	{
		// Display validation errors
		for(let id in _response.data)
		{
			// @ts-ignore
			const widget = this._widgetContainer.getWidgetById(id);
			if(widget && widget.instanceOf(et2_baseWidget))
			{
				(<et2_baseWidget>widget).showMessage(_response.data[id], 'validation_error');

			}
			else if(typeof widget.set_validation_error == "function")
			{
				widget.set_validation_error(_response.data[id]);
			}
			// Handle validation_error (messages coming back from server as a response) if widget is children of a tabbox
			let tmpWidget = widget;
			while(tmpWidget.getParent() && tmpWidget.getType() !== 'ET2-TABBOX')
			{
				tmpWidget = tmpWidget.getParent();
			}
			//Activate the tab where the widget with validation error is located
			if(tmpWidget.getType() === 'ET2-TABBOX')
			{
				(<Et2Tabs><unknown>tmpWidget).activateTab(widget);
			}
			// scroll the widget into view
			if (typeof widget.getDOMNode().scrollIntoView === 'function')
			{
				widget.scrollIntoView();
			}
		}
		egw().debug("warn", "Validation errors", _response.data);
	}

	/**
	 * Handle assign for attributes on etemplate2 widgets
	 *
	 * @param {string} type "assign"
	 * @param {object} res Response
	 * res.data.id {String} Widget ID
	 * res.data.key {String} Attribute name
	 * res.data.value New value for widget
	 * res.data.etemplate_exec_id
	 * @param {object} req
	 * @returns {Boolean} Handled by this plugin
	 * @throws Invalid parameters if the required res.data parameters are missing
	 */
	public handle_assign(type, res, req)
	{
		//type, req;	// unused, but required by plugin signature

		//Check whether all needed parameters have been passed and call the alertHandler function
		if((typeof res.data.id != 'undefined') &&
			(typeof res.data.key != 'undefined') &&
			(typeof res.data.value != 'undefined')
		)
		{
			if(typeof res.data.etemplate_exec_id == 'undefined' ||
				res.data.etemplate_exec_id != this._etemplate_exec_id)
			{
				// Not for this etemplate, but not an error
				return false;
			}
			if(res.data.key == 'etemplate_exec_id')
			{
				this._etemplate_exec_id = res.data.value;
				return true;
			}
			if(this._widgetContainer == null)
			{
				// Right etemplate, but it's already been cleared.
				egw.debug('warn', "Tried to call assign on an un-loaded etemplate", res.data);
				return false;
			}
			const widget = this._widgetContainer.getWidgetById(res.data.id);
			if(widget)
			{
				if(typeof widget['set_' + res.data.key] != 'function')
				{
					egw.debug('warn', "Cannot set %s attribute %s via JSON assign, no set_%s()", res.data.id, res.data.key, res.data.key);
					return false;
				}
				try
				{
					widget['set_' + res.data.key].call(widget, res.data.value);
					return true;
				}
				catch(e)
				{
					egw.debug("error", "When assigning %s on %s via AJAX, \n" + (e.message || e + ""), res.data.key, res.data.id, widget);
				}
			}
			return false;
		}
		throw 'Invalid parameters';
	}
}

// make etemplate2 global, as we need it to check an app uses it and then call methods on it
if(typeof window.etemplate2 === 'undefined')
{
	window['etemplate2'] = etemplate2;
}

// Calls etemplate2_handle_response in the context of the object which
// requested the response from the server
egw(window).registerJSONPlugin(etemplate2.handle_load, null, 'et2_load');
egw(window).registerJSONPlugin(etemplate2.handle_validation_error, null, 'et2_validation_error');