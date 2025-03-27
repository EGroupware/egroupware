import {css, html, LitElement, nothing, PropertyValues} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {classMap} from "lit/directives/class-map.js";
import {repeat} from "lit/directives/repeat.js";
import {until} from "lit/directives/until.js";
import "@shoelace-style/shoelace/dist/components/split-panel/split-panel.js";
import styles from "./EgwFramework.styles";
import {egw} from "../../api/js/jsapi/egw_global";
import {SlAlert, SlDropdown, SlTabGroup} from "@shoelace-style/shoelace";
import {EgwFrameworkApp} from "./EgwFrameworkApp";
import {EgwFrameworkMessage} from "./EgwFrameworkMessage";

/**
 * @summary Accessable, webComponent-based EGroupware framework
 *
 * @dependency sl-dropdown
 * @dependency sl-icon-button
 *
 * @slot - Current application
 * @slot banner - Very top, used for things like persistant, system wide messages.  Normally hidden.
 * @slot header - Top of page, contains logo, app icons.
 * @slot header-right - Top right, contains user info / actions.
 * @slot status - Home of the status app, it is limited in size and can be resized and hidden.
 * @slot footer - Very bottom.  Normally hidden.
 * *
 * @csspart base - Wraps it all.
 * @csspart banner -
 * @csspart header -
 * @csspart open-applications - Tab group that has the currently open applications
 * @csspart status-split - Status splitter
 * @csspart main
 * @csspart status
 * @csspart footer
 *
 * @cssproperty [--icon-size=32] - Height of icons used in the framework
 * @cssproperty [--tab-icon-size=32] - Height of application icons in header bar
 * @cssproperty [--tab-icon-size-active=40] - Height of active application icon
 */
@customElement('egw-framework')
//@ts-ignore
export class EgwFramework extends LitElement
{
	static get styles()
	{
		return [
			styles,

			// TEMP STUFF
			css`
				:host .placeholder {
					display: none;
				}

				:host(.placeholder) .placeholder {
					width: 100%;
					display: block;
					font-size: 200%;
					text-align: center;
					background-color: var(--placeholder-background-color, silver);
				}

				.placeholder:after {
					content: " (placeholder)";
				}

				.egw_fw__base {
					--placeholder-background-color: #75bd20;
				}

				.egw_fw__status .placeholder {
					writing-mode: vertical-rl;
					text-orientation: mixed;
					height: 100%;
				}

				:host(.placeholder) [class*="left"] .placeholder {
					background-color: color-mix(in lch, var(--placeholder-background-color), rgba(.5, .5, 1, .5));
				}

				:host(.placeholder) [class*="right"] .placeholder {
					background-color: color-mix(in lch, var(--placeholder-background-color), rgba(.5, 1, .5, .5));
				}

				:host(.placeholder) [class*="footer"] .placeholder {
					background-color: color-mix(in lch, var(--placeholder-background-color), rgba(1, 1, 1, .05));
				}


				::slotted(div#egw_fw_sidebar_r) {
					position: relative;
				}
			`
		];
	}

	@property()
	layout = "default";

	/**
	 * This is the list of all applications we know about
	 */
	@property({type: Array, attribute: "application-list"})
	applicationList : ApplicationInfo[] = [];

	/**
	 * Special tabs that are not directly associated with an application (CRM)
	 */
	private _tabApps : { [id : string] : ApplicationInfo } = {};
	private serializedTabState : string;

	// Keep track of open popups
	private _popups : Window[] = [];
	private _popupsGCInterval : number;

	// Keep track of open messages
	private _messages : SlAlert[] = [];

	// Watch for things (apps) getting added
	private appDOMObserver : MutationObserver

	// Keep track of egw loaded
	private _egwLoaded = Promise.resolve();

	private get tabs() : SlTabGroup { return this.shadowRoot.querySelector("sl-tab-group");}


	constructor()
	{
		super();

		this._tabApps = JSON.parse(egw.getSessionItem('api', 'fw_tab_apps') || null) || {};

		this.handleAppDOMChange = this.handleAppDOMChange.bind(this);
		this.appDOMObserver = new MutationObserver(this.handleAppDOMChange);
		this.handleDarkmodeChange = this.handleDarkmodeChange.bind(this);
	}
	connectedCallback()
	{
		super.connectedCallback();
		if(this.egw.window && this.egw.window.opener == null && !this.egw.window.framework)
		{
			// This works, but stops a lot else from working
			this.egw.window.framework = this;
		}
		if(this.egw.window?.framework && this.egw.window?.framework !== this)
		{
			// Override framework setSidebox, use arrow function to force context
			this.egw.framework.setSidebox = (applicationName, sideboxData, hash?) => this.setSidebox(applicationName, sideboxData, hash);
		}

		document.body.addEventListener("egw-darkmode-change", this.handleDarkmodeChange);
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();

		document.body.removeEventListener("egw-darkmode-change", this.handleDarkmodeChange);
		this.appDOMObserver.disconnect();
	}

	protected firstUpdated(_changedProperties : PropertyValues)
	{
		super.firstUpdated(_changedProperties);

		// Load hidden apps like status, as long as they can be loaded
		this.applicationList.forEach((app) =>
		{
			if(app.status == "5" && app.url && !app.url.match(/menuaction\=none/))
			{
				this.loadApp(app.name);
			}
		});
		// Load additional tabs
		Object.values(this._tabApps).forEach(app => this.loadApp(app.name));

		// Init timer
		this.egw.add_timer('topmenu_info_timer');

		// These need egw fully loaded
		this.getEgwComplete().then(async() =>
		{
			// EGW is loaded now, but framework is not guaranteed to be rendered yet

			// Register the "message" plugin
			this.egw.registerJSONPlugin((type, res, req) =>
			{
				//Check whether all needed parameters have been passed and call the alertHandler function
				if((typeof res.data.message != 'undefined'))
				{
					this.message(res.data.message, res.data.type)
					return true;
				}
				throw 'Invalid parameters';
			}, null, 'message');

			// Quick add
			this.egw.link_quick_add('topmenu_info_quick_add');

			// Ask about timer before logout
			const logout = this.querySelector('#topmenu_logout');
			logout.addEventListener('click', async(e) =>
			{
				e.preventDefault();
				e.stopImmediatePropagation();
				await this.egw.onLogout_timer();
				this.egw.open_link(e.target.value);
			});

			// Deal with bug where avatar menu does not position correctly
			(<SlDropdown>this.querySelector("#topmenu_info_user_avatar"))?.popup?.dispatchEvent(new Event("slotchange"));

			// Listen for apps added / removed
			this.appDOMObserver.observe(this, {childList: true});
		});
	}

	get egw() : typeof egw
	{
		return window.egw ?? <typeof egw>{
			// Dummy egw so we don't get failures from missing methods
			lang: (t) => t,
			preference: (n, app, promise? : Function | boolean | undefined) => Promise.resolve(""),
			set_preference(_app : string, _name : string, _val : any, _callback? : Function) {}
		};
	}

	/**
	 * A promise for if egw is loaded
	 *
	 * @returns {Promise<void>}
	 */
	getEgwComplete()
	{
		if(typeof this.egw.window['egw_ready'] !== "undefined")
		{
			this._egwLoaded = this.egw.window['egw_ready'];
		}
		return this._egwLoaded;
	}

	/**
	 *
	 * @param _function Framework function to be called on the server.
	 * @param _ajax_exec_url Actual function we want to call.
	 * @returns {string}
	 */
	public getMenuaction(_fun, _ajax_exec_url, appName = 'home')
	{
		let baseUrl = this.getBaseUrl();

		// Check whether the baseurl is actually set. If not, then this application
		// resides inside the same egw instance as the jdots framework. We'll simply
		// return a menu action and not a full featured url here.
		if(baseUrl != '')
		{
			baseUrl = baseUrl + 'json.php?menuaction=';
		}

		const menuaction = _ajax_exec_url ? _ajax_exec_url.match(/menuaction=([^&]+)/) : null;

		// use template handler to call current framework, eg. pixelegg
		return baseUrl + appName + '.kdots_framework.' + _fun + '.template' +
			(menuaction ? '.' + menuaction[1] : '');
	};

	public getApplicationByName(appName) : EgwFrameworkApp
	{
		return this.querySelector(`egw-app[name="${appName}"]`);
	}

	/**
	 * Load an application into the framework
	 *
	 * Loading is done by name, and we look up everything we need in the applicationList.
	 * If already loaded, this just returns the existing EgwFrameworkApp, optionally activated & with new URL loaded.
	 *
	 * @param {string} appname
	 * @param {boolean} active
	 * @param {string} url
	 * @returns {EgwFrameworkApp}
	 */
	public loadApp(appname : string, active = false, url = null) : EgwFrameworkApp
	{
		const existing : EgwFrameworkApp = this.querySelector(`egw-app[id="${appname}"]`);
		if(existing)
		{
			if(active)
			{
				this.tabs?.show(appname);
			}
			if(url)
			{
				existing.load(url);
			}
			return existing;
		}

		const app = this.applicationList.find(a => a.name == appname) ??
			this._tabApps[appname];

		if(!app)
		{
			console.log("Cannot load unknown app '" + appname + "'");
			return null;
		}
		let appComponent = <EgwFrameworkApp>document.createElement("egw-app");
		appComponent.setAttribute("id", appname);
		appComponent.setAttribute("name", app.internalName || appname);
		appComponent.url = url ?? app?.url;
		if(app.title)
		{
			appComponent.title = app.title;
		}
		if(active)
		{
			appComponent.setAttribute("active", '');
		}

		this.append(appComponent);
		// App was not in the tab list
		if(typeof app.opened == "undefined")
		{
			app.opened = this.shadowRoot.querySelectorAll("sl-tab").length;
			if(typeof this._tabApps[appname] == "object")
			{
				this._tabApps[appname] = app;
			}
			this.requestUpdate("applicationList");
		}

		// Wait until new tab is there to activate it
		if(active || app.active)
		{
			// Wait for egw & redraw
			Promise.all([this.getEgwComplete(), this.updateComplete]).then(async() =>
			{
				do
				{
					await this.updateComplete;
				}
				while(!this.tabs)
				// Tabs present
				await this.tabs.updateComplete;
				this.tabs.show(appname);
			});
		}

		return appComponent;
	}

	public closeApp(app : string | EgwFrameworkApp)
	{
		const applicationInfo = this._tabApps[typeof app == "string" ? app : app.id] ??
			this.applicationList.find(a => a.name == (typeof app == "string" ? app : app.name));

		if(!applicationInfo || !app)
		{
			return;
		}

		// Mark closed internally
		this.closeTab(applicationInfo.name);

		// Remove app component from DOM
		const appComponent = this.querySelector(`egw-app#${applicationInfo.name}`);
		if(appComponent)
		{
			appComponent.remove();
			appComponent.hasSlotController = null;
		}
	}

	private closeTab(tabName : string)
	{
		const applicationInfo = this._tabApps[tabName] ??
			this.applicationList.find(a => a.name == tabName);

		const active = applicationInfo.active || this.querySelector(`egw-app#${applicationInfo.name}`)?.getAttribute("active") != null;

		// Just the tab, ignore the app element
		delete applicationInfo.opened;
		applicationInfo.active = false;
		delete this._tabApps[tabName];

		if(active)
		{
			const tab = this.tabs.querySelector('[panel=' + applicationInfo.name + ']');
			this.showTab(tab.previousElementSibling?.getAttribute("panel") ?? tab.nextElementSibling?.getAttribute("panel"));
		}
		else
		{
			// Not visible, just update server with closed tab
			this.updateTabs();
		}

		this.requestUpdate("applicationList");
	}

	public get activeApp() : EgwFrameworkApp
	{
		return this.querySelector("egw-app[active]");
	}

	/**
	 * Load a link into the framework
	 *
	 * @param {string} _link
	 * @param {string} _app
	 * @returns {undefined}
	 */
	public linkHandler(_link : string, _app : string)
	{
		// Determine the app string from the application parameter
		let app = null;
		if(_app && typeof _app == 'string')
		{
			app = this.applicationList.find(a => a.name == _app);
		}

		if(!app)
		{
			//The app parameter was false or not a string or the application specified did not exists.
			//Determine the target application from the link that had been passed to this function
			app = this.parseAppFromUrl(_link);
		}

		if(app)
		{
			if(_app == '_tab')
			{
				// add target flag
				_link += '&target=_tab';
				const appname = app.appName + ":" + btoa(_link);
				this.applicationList.push({
					...app,
					name: appname,
					url: _link,
					title: 'view'
				});
				app = this.applicationList[this.applicationList.length - 1];
			}
			this.loadApp(app.name, true, _link);
		}
		else if(typeof _app == 'string')
		{
			//Display some error messages to have visible feedback
			egw_alertHandler('Application "' + _app + '" not found.',
				'The application "' + _app + '" the link "' + _link + '" points to is not registered.');
		}
		else
		{
			this.egw.window.location.replace(_link);
		}
	}

	public tabLinkHandler(_link : string, _extra = {
		id: ""
	})
	{
		const app = this.parseAppFromUrl(_link);
		if(app)
		{
			const appname = app.name + "-" + btoa(_extra.id ? _extra.id : _link).replace(/=/g, 'i');
			if(this.getApplicationByName(appname))
			{
				this.loadApp(appname, true, _link);
				return appname;
			}

			// add target flag
			_link += '&fw_target=' + appname;
			// create an actual clone of existing app object
			let clone = {
				...app,
				..._extra,
				//isFrameworkTab: true, ??
				name: appname,
				internalName: app.name,
				url: _link,
				// Need to override to open, base app might already be opened
				opened: undefined
			};
			// Store only in session
			this._tabApps[appname] = clone;
			this.loadApp(appname, true);
			this._setTabAppsSession();

			return appname;
		}
		else
		{
			egw_alertHandler("No appropriate target application has been found.",
				"Target link: " + _link);
		}
	}

	/**
	 * Open a (centered) popup window with given size and url
	 *
	 * @param {string} _url
	 * @param {number} _width
	 * @param {number} _height
	 * @param {string} _windowName or "_blank"
	 * @param {string|boolean} _app app-name for framework to set correct opener or false for current app
	 * @param {boolean} _returnID true: return window, false: return undefined
	 * @param {type} _status "yes" or "no" to display status bar of popup
	 * @param {DOMWindow} _parentWnd parent window
	 * @returns {DOMWindow|undefined}
	 */
	public openPopup(_url, _width, _height, _windowName, _app, _returnID, _status, _parentWnd)
	{
		const windowID = this.egw.openPopup(_url, _width, _height, _windowName, _app, true, _status, true);

		windowID.framework = this;
		this._popups.push(windowID);
		if(!this._popupsGCInterval)
		{
			// Check every 60s to make sure we didn't miss any
			this._popupsGCInterval = window.setInterval(() => this.popups_garbage_collector(), 10000);
		}

		if(_returnID !== false)
		{
			return windowID;
		}
	}

	/**
	 * Collect and close all already closed windows
	 * egw.open_link expects it from the framework
	 */
	public popups_garbage_collector()
	{
		let i = this._popups.length;
		while(i--)
		{
			if(this._popups[i].closed)
			{
				this._popups.splice(i, 1);
			}
		}
		if(this._popups.length == 0 && this._popupsGCInterval)
		{
			window.clearInterval(this._popupsGCInterval);
			this._popupsGCInterval = null;
		}
	}

	/**
	 * Tries to obtain the application from a menuaction
	 * @param {string} _url
	 */
	protected parseAppFromUrl(_url : string)
	{
		let _app = null;

		// Check the menuaction parts from the url
		let matches = _url.match(/menuaction=([a-z0-9_-]+)\./i) ||
			// Check the url for a scheme of "/app/something.php"
			_url.match(/\/([^\/]+)\/[^\/]+\.php/i);
		if(matches)
		{
			// check if this is a regular app-name
			_app = this.applicationList.find(a => a.name == matches[1]);
		}

		return _app;
	}

	/**
	 * Print
	 */
	public async print()
	{
		const appElement : EgwFrameworkApp = this.activeApp;
		try
		{
			if(appElement)
			{
				await appElement.print();
			}
			const appWindow = this.egw.window;
			appWindow.setTimeout(appWindow.print, 0);
		}
		catch
		{
			// Ignore rejected
		}
	}

	public async setSidebox(appname, sideboxData, hash)
	{
		const app = this.loadApp(appname);
		app.setSidebox(sideboxData, hash);
	}

	/**
	 * Show a message, with an optional type
	 *
	 * @param {string} message
	 * @param {"" | "help" | "info" | "error" | "warning" | "success"} type
	 * @param {number} duration The length of time, seconds, the alert will show before closing itself.  Success
	 * 	messages are shown for 5s, other messages require manual closing by the user.
	 * @param {boolean} closable=true Message can be closed by the user
	 * @param {string} _discardID unique string id (appname:id) in order to register
	 * the message as discardable. Discardable messages offer a checkbox to never be shown again.
	 * If no appname given, the id will be prefixed with current app. The discardID will be stored in local storage.
	 * @returns {Promise<SlAlert>} SlAlert element
	 */
	public async message(message : string, type : "" | "help" | "info" | "error" | "warning" | "success" = "", duration : null | number = null, closable = true, _discardID : null | string = null)
	{
		if(message && !type)
		{
			const error_reg_exp = new RegExp('(error|' + egw.lang('error') + ')', 'i');
			type = message.match(error_reg_exp) ? 'error' : 'success';
		}

		// Do not add a same message twice if it's still not dismissed
		const hash = await this.egw.hashString(message);
		if(typeof this._messages[hash] !== "undefined")
		{
			return this._messages[hash];
		}

		// Already discarded, just stop
		if(_discardID && EgwFrameworkMessage.isDiscarded(_discardID))
		{
			return;
		}

		const attributes = {
			type: type,
			closable: closable,
			duration: duration * 1000,
			discard: _discardID,
			message: message,
			"data-hash": hash
		}
		if(!duration)
		{
			delete attributes.duration;
		}

		const alert = Object.assign(document.createElement("egw-message"), attributes);
		alert.addEventListener("sl-hide", (e) =>
		{
			delete this._messages[e.target["data-hash"] ?? ""];
		});
		this._messages[hash] = alert;
		document.body.append(alert);
		await alert.updateComplete;

		alert.toast();

		return alert;
	}

	/**
	 * Set a notification message for topmenu info item
	 *
	 * @param {string} _id id of topmenu info item with its prefix
	 * @param {string} _message message that should be displayed
	 * @param {string} _tooltip hint text as tooltip
	 */
	public topmenu_info_notify(_id, _switch, _message, _tooltip)
	{
		var $items = jQuery('#egw_fw_topmenu_info_items').children();
		var prefix = "topmenu_info_";

		$items.each(function(i, item)
		{
			if(item.id == prefix + _id || item.id == _id)
			{
				var $notify = jQuery(item).find('.egw_fw_topmenu_info_notify');
				if(_switch)
				{
					if($notify.length == 0)
					{
						$notify = jQuery(document.createElement('div'))
							.addClass('egw_fw_topmenu_info_notify')
							.prop('title', _tooltip)
							.appendTo(item);
					}
					$notify.prop('title', _tooltip).text(_message);
				}
				else
				{
					$notify.remove();
				}
			}
		});
	}

	protected getBaseUrl() {return "";}

	protected handleDarkmodeChange(event)
	{
		// Update CSS classes
		this.classList.toggle("sl-theme-light", !event.target.darkmode);
		this.classList.toggle("sl-theme-dark", event.target.darkmode);

		// Update preference
		this.egw.set_preference("common", "darkmode", (event.target.darkmode ? "1" : "0"));
	}
	/**
	 * An application tab is chosen, show the app
	 *
	 * @param e
	 * @protected
	 */
	protected handleApplicationTabShow(event)
	{
		// Create & show app
		this.showTab(event.target.activeTab.panel);
	}

	public showTab(appname : string)
	{
		this.querySelectorAll("egw-app").forEach(app => app.removeAttribute("active"));

		let appComponent = this.loadApp(appname, true);
		appComponent.setAttribute("active", "");

		// Show it now
		this.tabs?.show(appname)
		// Keep it through updates

		const applicationInfo = this._tabApps[appname] ??
			this.applicationList.find(a => a.name == appname);
		applicationInfo.active = true;

		// Update the list on the server
		this.tabs.updateComplete.then(() =>
		{
			this.updateTabs();
		});
	}

	/**
	 * An application tab is closed
	 */
	protected handleApplicationTabClose(event)
	{
		const tab = event.target;

		// Remove egw-app from DOM
		if(this.querySelector(`egw-app[id='${tab.panel}']`))
		{
			this.closeApp(this.querySelector(`egw-app[id='${tab.panel}']`));
		}
		else
		{
			this.closeTab(tab.panel);
		}
	}

	/**
	 * Watch for changes in child nodes (applications) and remove the application if its node is removed.
	 *
	 * @param mutationList
	 * @param observer
	 * @protected
	 */
	protected handleAppDOMChange(mutationList, observer)
	{
		mutationList.forEach(mutation =>
		{
			mutation.removedNodes.forEach(removedNode =>
			{
				if(removedNode instanceof EgwFrameworkApp)
				{
					this.closeApp(removedNode);
				}
			})
			mutation.addedNodes.forEach(addedNode => {});
		});
	}

	/**
	 * Store last status of tabs
	 * tab status being used in order to open all previous opened
	 * tabs and to activate the last active tab
	 */
	private updateTabs()
	{
		//Send the current tab list to the server
		let data = this.assembleTabList(this.activeApp);

		// If no current app, use the first one
		if(!this.activeApp && data.length)
		{
			data[0].active = true;
		}
		else
		{
			Object.keys(this._tabApps).forEach((t) =>
			{
				if(data.some(d => d.appName == t))
				{
					this._tabApps[t].active = t == this.activeApp.id;
				}
			});
		}

		//Serialize the tab list and check whether it really has changed since the last
		//submit
		var serialized = egw.jsonEncode(data);
		if(serialized != this.serializedTabState)
		{
			this.serializedTabState = serialized;
			if(this._tabApps)
			{
				// Update session tabs
				this._setTabAppsSession();
			}
			egw.jsonq('EGroupware\\Api\\Framework\\Ajax::ajax_tab_changed_state', [data]);
		}
	}

	private assembleTabList(activeTab)
	{
		let appList = []
		const assembleApp = (app) =>
		{
			const obj = {appName: app.name};
			if(activeTab && app.name == activeTab)
			{
				obj['active'] = true;
			}
			if(app.opened)
			{
				appList.push({appName: app.name, active: app.active})
			}
		};
		Array.from(this.applicationList).forEach((app : ApplicationInfo) =>
		{
			assembleApp(app);
		});
		Object.values(this._tabApps).forEach((app : ApplicationInfo) =>
		{
			assembleApp(app);
		});
		return appList;
	}

	private _setTabAppsSession()
	{
		egw.setSessionItem('api', 'fw_tab_apps', JSON.stringify(this._tabApps));
	}

	/**
	 * Renders one application into the 9-dots application menu
	 *
	 * @param app
	 * @returns {TemplateResult<1>}
	 * @protected
	 */
	protected _applicationListAppTemplate(app)
	{
		if(app.status !== "1")
		{
			return nothing;
		}

		return html`
            <sl-tooltip placement="bottom" role="menuitem" content="${app.title}">
                <et2-button-icon src="${app.icon}" aria-label="${app.title}" role="menuitem" noSubmit
                                 helptext="${app.title}"
                                 @click=${() =>
                                 {
                                     this.loadApp(app.name, true);
                                     (<SlDropdown>this.shadowRoot.querySelector(".egw_fw__app_list")).hide();
                                 }}
                ></et2-button-icon>
            </sl-tooltip>`;
	}

	protected _applicationTabTemplate(app)
	{
		return html`
            <sl-tab slot="nav" panel="${app.name}" closable aria-label="${app.title}" ?active=${app.active}>
                <sl-tooltip placement="bottom" content="${app.title}" hoist>
                    <et2-image src="${app.icon}"></et2-image>
                </sl-tooltip>
            </sl-tab>`;
	}

	render()
	{
		const iconSize = getComputedStyle(this).getPropertyValue("--icon-size");
		// Snap positions need to be in pixels
		const statusSnap = (parseInt(iconSize) + 6) + 'px';
		const statusPosition = this.egw?.preference("statusPosition", "common") ?? parseInt(statusSnap) ?? "36";

		const classes = {
			"egw_fw__base": true
		}
		classes[`egw_fw__layout-${this.layout}`] = true;

		return html`${until(this.getEgwComplete().then(() => html`
            <div class=${classMap(classes)} part="base">
                <div class="egw_fw__banner" part="banner" role="banner">
                    <slot name="banner"><span class="placeholder">Banner</span></slot>
                </div>
                <header class="egw_fw__header" part="header">
                    <slot name="logo"></slot>
                    <sl-dropdown class="egw_fw__app_list" role="menu">
                        <sl-icon-button slot="trigger" name="grid-3x3-gap"
                                        label="${this.egw.lang("Application list")}"
                                        aria-description="${this.egw.lang("Activate for a list of applications")}"
                        ></sl-icon-button>
                        ${repeat(this.applicationList, (app) => this._applicationListAppTemplate(app))}
                    </sl-dropdown>
                    <sl-tab-group part="open-applications" class="egw_fw__open_applications" activation="manual"
                                  role="tablist"
                                  @sl-tab-show=${this.handleApplicationTabShow}
                                  @sl-close=${this.handleApplicationTabClose}
                    >
                        ${repeat([...this.applicationList, ...Object.values(this._tabApps)]
                                .filter(app => typeof app.opened !== "undefined" && app.status !== "5")
                                .sort((a, b) => a.opened - b.opened), (app) => this._applicationTabTemplate(app))}
                    </sl-tab-group>
                    <slot name="header"><span class="placeholder">header</span></slot>
                    <slot name="header-right"><span class="placeholder">header-right</span></slot>
                </header>
                <div class="egw_fw__divider">
                    <sl-split-panel part="status-split" position-in-pixels="${statusPosition}" primary="end"
                                    snap="150px ${statusSnap} 0px"
                                    snap-threshold="${Math.min(40, parseInt(iconSize) - 5)}"
                                    aria-label="Side menu resize">
                        <main slot="start" part="main" class="egw_fw__main">
                            <slot></slot>
                        </main>
                        <sl-icon slot="divider" name="grip-vertical"></sl-icon>
                        <aside slot="end" class="egw_fw__status" part="status">
                            <slot name="status"><span class="placeholder">status</span></slot>
                        </aside>
                    </sl-split-panel>
                </div>
                <footer class="egw_fw__footer" part="footer">
                    <slot name="footer"><span class="placeholder">footer</span></slot>
                </footer>
            </div>
		`), html`<span>Waiting for egw...</span>
        <slot></slot>`)}`;
	}
}

/**
 * Information we keep and use about each app on the client side
 * This might not be limited to actual EGw apps,
 */
export interface ApplicationInfo
{
	/* Internal name, used for reference & indexing.  Might not be an egw app, might have extra bits */
	name : string,
	/* Must be an egw app, used for the EgwFrameworkApp, preferences, etc. */
	internalName? : string,
	icon : string
	title : string,
	url : string,
	/* What type of application (1: normal, 5: loaded but no tab) */
	status : string,// = "1",
	/* Is the app open, and at what place in the tab list */
	opened? : number,
	/* Is the app currently active */
	active? : boolean// = false
}