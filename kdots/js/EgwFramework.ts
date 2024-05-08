import {css, html, LitElement, nothing} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {classMap} from "lit/directives/class-map.js";
import {repeat} from "lit/directives/repeat.js";
import "@shoelace-style/shoelace/dist/components/split-panel/split-panel.js";
import styles from "./EgwFramework.styles";
import {egw} from "../../api/js/jsapi/egw_global";
import {SlDropdown, SlTab, SlTabGroup} from "@shoelace-style/shoelace";
import {EgwFrameworkApp} from "./EgwFrameworkApp";

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
	 *
	 * @type {any[]}
	 */
	@property({type: Array, attribute: "application-list"})
	applicationList = [];

	private get tabs() : SlTabGroup { return this.shadowRoot.querySelector("sl-tab-group");}

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

	public loadApp(appname, active = false)
	{
		const app = this.applicationList.find(a => a.name == appname);
		let appComponent = <EgwFrameworkApp>document.createElement("egw-app");
		appComponent.id = appname;
		appComponent.name = appname;
		appComponent.url = app?.url;

		this.append(appComponent);
		// App was not in the tab list
		if(typeof app.opened == "undefined")
		{
			app.opened = this.shadowRoot.querySelectorAll("sl-tab").length;
		this.requestUpdate("applicationList");
		}

		// Wait until new tab is there to activate it
		if(active)
		{
			this.updateComplete.then(() =>
			{
				this.tabs.show(appname);
			})
		}

		return appComponent;
	}

	protected getBaseUrl() {return "";}

	/**
	 * An application tab is chosen, show the app
	 *
	 * @param e
	 * @protected
	 */
	protected handleApplicationTabShow(event)
	{
		this.querySelectorAll("egw-app").forEach(app => app.removeAttribute("active"));

		// Create & show app
		const appname = event.target.activeTab.panel;
		let appComponent = this.querySelector(`egw-app#${appname}`);
		if(!appComponent)
		{
			appComponent = this.loadApp(appname);
		}
		appComponent.setAttribute("active", "");

		// Update the list on the server
		this.updateTabs(event.target.activeTab);
	}

	/**
	 * An application tab is closed
	 */
	protected handleApplicationTabClose(event)
	{
		const tabGroup : SlTabGroup = this.shadowRoot.querySelector("sl-tab-group.egw_fw__open_applications");
		const tab = event.target;
		const panel = tabGroup.querySelector(`sl-tab-panel[name="${tab.panel}"]`);

		// Show the previous tab if the tab is currently active
		if(tab.active)
		{
			tabGroup.show(tab.previousElementSibling.panel);
		}
		else
		{
			// Show will update, but closing in the background we call directly
			this.updateTabs(tabGroup.querySelector("sl-tab[active]"));
		}

		// Remove the tab + panel
		tab.remove();
		panel.remove();
	}

	private updateTabs(activeTab)
	{
		let appList = [];
		Array.from(this.shadowRoot.querySelectorAll("sl-tab-group.egw_fw__open_applications sl-tab")).forEach((tab : SlTab) =>
		{
			appList.push({appName: tab.panel, active: activeTab.panel == tab.panel})
		});
		this.egw.jsonq('EGroupware\\Api\\Framework\\Ajax::ajax_tab_changed_state', [appList]);
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
		const statusPosition = this.egw?.preference("statusPosition", "common") ?? parseInt(iconSize) ?? "36";

		const classes = {
			"egw_fw__base": true
		}
		classes[`egw_fw__layout-${this.layout}`] = true;

		return html`
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
                        ${repeat(this.applicationList
                                .filter(app => typeof app.opened !== "undefined")
                                .sort((a, b) => a.opened - b.opened), (app) => this._applicationTabTemplate(app))}
                    </sl-tab-group>
                    <slot name="header"><span class="placeholder">header</span></slot>
                    <slot name="header-right"><span class="placeholder">header-right</span></slot>
                </header>
                <div class="egw_fw__divider">
                    <sl-split-panel part="status-split" position-in-pixels="${statusPosition}" primary="end"
                                    snap="150px ${iconSize} 0px"
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
		`;
	}
}