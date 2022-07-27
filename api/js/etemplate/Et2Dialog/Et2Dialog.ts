/**
 * EGroupware eTemplate2 - Box widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {Et2Widget} from "../Et2Widget/Et2Widget";
import {et2_button} from "../et2_widget_button";
import {LionDialog} from "@lion/dialog";
import {et2_widget} from "../et2_core_widget";
import {html, LitElement, ScopedElementsMixin, SlotMixin} from "@lion/core";
import {Et2DialogOverlay} from "./Et2DialogOverlay";
import {Et2DialogContent} from "./Et2DialogContent";
import {et2_template} from "../et2_widget_template";
import {etemplate2} from "../etemplate2";
import {IegwAppLocal} from "../../jsapi/egw_global";
import interact from "@interactjs/interactjs";
import type {InteractEvent} from "@interactjs/core/InteractEvent";

export interface DialogButton
{
	id : string,
	button_id? : number,
	label : string,
	image? : string,
	default? : boolean
	align? : string,
	disabled? : boolean
}

/**
 * Et2Dialog widget
 *
 * A common dialog widget that makes it easy to inform users or prompt for information.
 *
 * It is possible to have a custom dialog by using a template, but you can also use
 * the static method Et2Dialog.show_dialog().  At its simplest, you can just use:
 * ```ts
 *	Et2Dialog.show_dialog(false, "Operation completed");
 * ```
 *
 * Or a more complete example:
 * ```js
 * 	let callback = function (button_id)
 *	{
 *		if(button_id == Et2Dialog.YES_BUTTON)
 *		{
 *			// Do stuff
 *		}
 *		else if (button_id == Et2Dialog.NO_BUTTON)
 *		{
 *			// Other stuff
 *		}
 *		else if (button_id == Et2Dialog.CANCEL_BUTTON)
 *		{
 *			// Abort
 *		}
 *	}.
 *	let dialog = Et2Dialog.show_dialog(
 *		callback, "Erase the entire database?","Break things", {} // value
 *		et2_dialog.BUTTONS_YES_NO_CANCEL, et2_dialog.WARNING_MESSAGE
 *	);
 * ```
 *
 * Or, using Promises instead of a callback:
 * ```ts
 * let result = await Et2Dialog.show_prompt(null, "Name").getComplete();
 * if(result.button_id == Et2Dialog.OK_BUTTON)
 * {
 *     // Do stuff with result.value
 * }
 * ```
 *
 * The parameters for the above are all optional, except callback (which can be null) and message:
 *	callback - function called when the dialog closes, or false/null.
 *		The ID of the button will be passed.  Button ID will be one of the Et2Dialog.*_BUTTON constants.
 *		The callback is _not_ called if the user closes the dialog with the X in the corner, or presses ESC.
 * 	message - (plain) text to display
 *	title - Dialog title
 *	value (for prompt)
 *	buttons - Et2Dialog BUTTONS_* constant, or an array of button settings.  Use DialogButton interface.
 *	dialog_type - Et2Dialog *_MESSAGE constant
 *	icon - URL of icon
 *
 * Note that these methods will _not_ block program flow while waiting for user input unless you use "await" on getComplete().
 * The user's input will be provided to the callback.
 *
 * You can also create a custom dialog using an etemplate, even setting all the buttons yourself.
 * ```ts
 *  // Pass egw in the constructor
 * 	let dialog = new Et2Dialog(my_egw_reference);
 *
 * 	// Set attributes.  They can be set in any way, but this is convenient.
 * 	dialog.transformAttributes({
 * 		// If you use a template, the second parameter will be the value of the template, as if it were submitted.
 * 		callback: function(button_id, value) {...},	// return false to prevent dialog closing
 * 		buttons: [
 * 			// These ones will use the callback, just like normal.  Use DialogButton interface.
 * 			{label: egw.lang("OK"),id:"OK", default: true},
 * 			{label: egw.lang("Yes"),id:"Yes"},
 * 			{label: egw.lang("Sure"),id:"Sure"},
 * 			{label: egw.lang("Maybe"),click: function() {
 * 				// If you override, 'this' will be the dialog DOMNode.
 * 				// Things get more complicated.
 * 				// Do what you like here
 * 			}},
 *
 * 		],
 * 		title: 'Why would you want to do this?',
 * 		template:"/egroupware/addressbook/templates/default/edit.xet",
 * 		value: { content: {...default values}, sel_options: {...}...}
 * 	});
 *	// Add to DOM, dialog will auto-open
 *	document.body.appendChild(dialog);
 *	// If you want, wait for close
 *	let result = await dialog.getComplete();
 *```
 *
 * Because of how LionDialog does its layout and rendering, it's easiest to separate the dialog popup from
 * the dialog content.  This allows us to easily preserve the WebComponent styling.  You should interact with the
 * Et2Dialog though, and ignore the Et2DialogOverlay & Et2DialogContent.
 */
export class Et2Dialog extends Et2Widget(ScopedElementsMixin(SlotMixin(LionDialog)))
{
	/**
	 * Dialogs don't always get added to an etemplate, so we keep our own egw
	 *
	 * @type {IegwAppLocal}
	 * @protected
	 */
	protected __egw : IegwAppLocal

	/**
	 * As long as the template is a legacy widget, we want to hold on to the widget
	 * When it becomes a WebComponent, we can just include it in render()
	 *
	 * @type {et2_template | null}
	 * @protected
	 */
	protected _template_widget : etemplate2 | null;
	protected _template_promise : Promise<boolean>;

	/**
	 * Treat the dialog as an atomic operation, and use this promise to notify when
	 * "done" instead of (or in addition to) using the callback function.
	 * It gives the button ID and the dialog value.
	 */
	protected _complete_promise : Promise<[number, Object]>;

	/**
	 * The ID of the button that was clicked.  Always one of the button constants,
	 * unless custom buttons were used
	 *
	 * @type {number|null}
	 * @protected
	 */
	protected _button_id : number | null;

	/**
	 * Types
	 * @constant
	 */
	public static readonly PLAIN_MESSAGE : number = 0;
	public static readonly INFORMATION_MESSAGE : number = 1;
	public static readonly QUESTION_MESSAGE : number = 2;
	public static readonly WARNING_MESSAGE : number = 3;
	public static readonly ERROR_MESSAGE : number = 4;

	/* Pre-defined Button combos */
	public static readonly BUTTONS_OK : number = 0;
	public static readonly BUTTONS_OK_CANCEL : number = 1;
	public static readonly BUTTONS_YES_NO : number = 2;
	public static readonly BUTTONS_YES_NO_CANCEL : number = 3;

	/* Button constants */
	public static readonly CANCEL_BUTTON : number = 0;
	public static readonly OK_BUTTON : number = 1;
	public static readonly YES_BUTTON : number = 2;
	public static readonly NO_BUTTON : number = 3;

	static get properties()
	{
		return {
			...super.properties,
			callback: Function,

			/**
			 * Allow other controls to be accessed while the dialog is visible
			 */
			modal: Boolean,

			/**
			 * Title for the dialog, goes in the header
			 */
			title: String,

			/**
			 * Pre-defined group of buttons, one of the BUTTONS_*
			 */
			buttons: Number,

			/**
			 * Instead of a message, show this template file instead
			 */
			template: String,

			// Force size on the dialog.  Normally it sizes to content.
			width: Number,
			height: Number,

			// We just pass these on to Et2DialogContent
			message: String,
			dialog_type: Number,
			icon: String,
			value: Object,

			/**
			 * Automatically destroy the dialog when it closes.  Set to false to keep the dialog around.
			 */
			destroyOnClose: Boolean,

			/**
			 * Legacy-option for appending dialog into a specific dom node
			 */
			appendTo: String,

			/**
			 * When it's set to false dialog won't get closed by hitting Esc
			 */
			hideOnEscape: Boolean,

			/**
			 * When set to true it removes the close button from dialog's header
			 */
			noCloseButton: Boolean
		}
	}

	get slots()
	{
		return {
			...super.slots
		}
	}

	// Still not sure what this does, but it's important.
	// Seems to be related to the constructor, and what's available during the "creation"
	static get scopedElements()
	{
		return {
			...super.scopedElements,
			'et2-dialog-overlay-frame': Et2DialogOverlay,
			'et2-dialog-content': Et2DialogContent
		};
	}

	/*
	* List of properties that get translated
	* Done separately to not interfere with properties - if we re-define label property,
	* labels go missing.
	*/
	static get translate()
	{
		return {
			...super.translate,
			title: true,
			message: true
		}
	}

	private readonly _buttons : DialogButton[][] = [
		/*
		Pre-defined Button combos
		*/
		//BUTTONS_OK: 0,
		[{"button_id": Et2Dialog.OK_BUTTON, label: 'ok', id: 'dialog[ok]', image: 'check', "default": true}],
		//BUTTONS_OK_CANCEL: 1,
		[
			{"button_id": Et2Dialog.OK_BUTTON, label: 'ok', id: 'dialog[ok]', image: 'check', "default": true},
			{
				"button_id": Et2Dialog.CANCEL_BUTTON,
				label: 'cancel',
				id: 'dialog[cancel]',
				image: 'cancel',
				align: "right"
			}
		],
		//BUTTONS_YES_NO: 2,
		[
			{"button_id": Et2Dialog.YES_BUTTON, label: 'yes', id: 'dialog[yes]', image: 'check', "default": true},
			{"button_id": Et2Dialog.NO_BUTTON, label: 'no', id: 'dialog[no]', image: 'cancel'}
		],
		//BUTTONS_YES_NO_CANCEL: 3,
		[
			{"button_id": Et2Dialog.YES_BUTTON, label: 'yes', id: 'dialog[yes]', image: 'check', "default": true},
			{"button_id": Et2Dialog.NO_BUTTON, label: 'no', id: 'dialog[no]', image: 'cancelled'},
			{
				"button_id": Et2Dialog.CANCEL_BUTTON,
				label: 'cancel',
				id: 'dialog[cancel]',
				image: 'cancel',
				align: "right"
			}
		]
	];

	constructor(parent_egw? : string | IegwAppLocal)
	{
		super();

		if(parent_egw)
		{
			this._setApiInstance(parent_egw);
		}
		this.modal = true;
		this.dialog_type = Et2Dialog.PLAIN_MESSAGE;
		this.destroyOnClose = true;
		this.hideOnEscape = this.hideOnEscape === false ? false : true;
		this.__value = {};

		this._onOpen = this._onOpen.bind(this);
		this._onClose = this._onClose.bind(this);
		this._onClick = this._onClick.bind(this);
		this._onMoveResize = this._onMoveResize.bind(this);
		this._adoptTemplateButtons = this._adoptTemplateButtons.bind(this);

		// Create this here so we have something, otherwise the creator might continue with undefined while we
		// wait for the dialog to complete & open
		this._complete_promise = new Promise<[number, Object]>((resolve) =>
		{
			this._completeResolver = value => resolve(value);
		});
	}

	connectedCallback()
	{
		super.connectedCallback();

		// Wait for everything to complete, then auto-open
		this.getUpdateComplete().then(() =>
		{
			// _overlayCtrl is not available earlier
			this._overlayCtrl?.addEventListener("show", this._onOpen);
			this._overlayCtrl.addEventListener('hide', this._onClose);

			window.setTimeout(this.open, 0);
		});
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		this._overlayCtrl.removeEventListener("hide", this._onClose);
		this._overlayCtrl.removeEventListener("show", this._onOpen);
	}

	// Need to wait for Overlay
	async getUpdateComplete()
	{
		await super.getUpdateComplete();
		await this._overlayContentNode.getUpdateComplete();

		// Wait for template to finish loading
		if(this._template_widget)
		{
			await this._template_promise;
		}

		// This calls _onClose() when the dialog is closed
		this._overlayContentNode.querySelectorAll("et2-button").forEach((button) => button.addEventListener("click", this._onClick));
	}

	getComplete() : Promise<[number, Object]>
	{
		return this._complete_promise;
	}

	_onOpen()
	{
		this._button_id = null;
		this._complete_promise = this._complete_promise || new Promise<[number, Object]>((resolve) => this._completeResolver);

		this._setupMoveResize(this._overlayContentNode);
		// Now consumers can listen for "open" event, though getUpdateComplete().then(...) also works
		this.dispatchEvent(new Event('open'));
	}

	_onClose(ev : PointerEvent)
	{
		this._completeResolver([this._button_id, this.value]);
		this._button_id = null;
		this._complete_promise = undefined;

		this.dispatchEvent(new Event('close'));

		if(this.destroyOnClose)
		{
			if(this._template_widget)
			{
				this._template_widget.clear();
			}
			this._overlayCtrl.teardown();
			this.remove();
		}
	}

	_onClick(ev : MouseEvent)
	{
		// @ts-ignore
		this._button_id = ev.target?.getAttribute("button_id") ? parseInt(ev.target?.getAttribute("button_id")) : (ev.target?.getAttribute("id") || null);

		// Handle anything bound via et2 onclick property
		try
		{
			let et2_widget_result = super._handleClick(ev);
			if(!et2_widget_result)
			{
				ev.preventDefault();
				ev.stopPropagation();
				return false;
			}
		}
		catch(e)
		{
			console.log(e);
		}


		// Callback expects (button_id, value)
		try
		{
			let callback_result = this.callback ? this.callback(this._button_id, this.value) : true;
			if(callback_result === false)
			{
				ev.preventDefault();
				ev.stopPropagation();
				return false;
			}
		}
		catch(e)
		{
			console.log(e);
		}
		this.close();
	}

	/**
	 * Handle moving and resizing
	 *
	 * @param event
	 */
	_onMoveResize(event : InteractEvent)
	{
		let target = event.target
		let x = (parseFloat(target.getAttribute('data-x')) || 0)
		let y = (parseFloat(target.getAttribute('data-y')) || 0)

		// update the element's style
		target.style.width = event.rect.width + 'px'
		target.style.height = event.rect.height + 'px'

		// translate when resizing from top or left edges
		if(event.type == "resizemove")
		{
			x += event.deltaRect.left
			y += event.deltaRect.top
		}
		else
		{
			x += event.delta.x;
			y += event.delta.y;
		}

		target.style.transform = 'translate(' + x + 'px,' + y + 'px)'

		target.setAttribute('data-x', x)
		target.setAttribute('data-y', y)
	}

	/**
	 * Returns the values of any widgets in the dialog.  This does not include
	 * the buttons, which are only supplied for the callback.
	 */
	get value() : Object
	{
		let value = this.__value;
		if(this._template_widget && this._template_widget.widgetContainer)
		{
			value = this._template_widget.getValues(this._template_widget.widgetContainer);
		}
		return value;
	}

	/**
	 * Fire the close-overlay event to use all registered listeners
	 * @deprecated
	 */
	destroy()
	{
		this._overlayContentNode.dispatchEvent(new Event('close-overlay'));
	}

	/**
	 * @deprecated
	 * @returns {Object}
	 */
	get_value() : Object
	{
		console.warn("Deprecated get_value() called");
		return this.value;
	}

	set value(new_value)
	{
		this.__value = new_value;
	}

	set template(new_template_name)
	{
		let old_template = this.__template;
		this.__template = new_template_name;
		// inject preprocessor, if not already in template-url
		const webserverUrl = this.egw().webserverUrl;
		if (!new_template_name.match(new RegExp(webserverUrl+'/api/etemplate.php')))
		{
			this.__template = new_template_name.replace(new RegExp(webserverUrl), webserverUrl+'/api/etemplate.php');
		}
		// if we have no cache-buster, reload daily
		if (this.__template.indexOf('?') === -1)
		{
			this.__template += '?'+((new Date).valueOf()/86400|0).toString();
		}
		this.requestUpdate("template", old_template);
	}

	get template()
	{
		// Can't return undefined or requestUpdate() will not notice a change
		return this._template_widget || null;
	}


	updated(changedProperties)
	{
		super.updated(changedProperties);
		if(changedProperties.has("template"))
		{
			this._loadTemplate();
		}
	}

	_loadTemplate()
	{
		if(this._template_widget)
		{
			this._template_widget.clear();
		}

		// Etemplate wants a content
		if(typeof this.__value.content === "undefined")
		{
			this.__value.content = {};
		}
		this._template_widget = new etemplate2(this._overlayContentNode._contentNode);

		// Fire an event so consumers can do their thing - etemplate will fire its own load event when its done
		if(!this.dispatchEvent(new CustomEvent("before-load", {
			bubbles: true,
			cancelable: true,
			detail: this._template_widget
		})))
		{
			return;
		}

		if(this.__template.indexOf('.xet') > 0)
		{
			// File name provided, fetch from server
			this._template_promise = this._template_widget.load("", this.__template, this.__value || {content: {}},);
		}
		else
		{
			// Just template name, it better be loaded already
			this._template_promise = this._template_widget.load(this.__template, '', this.__value || {},
				// true: do NOT call et2_ready, as it would overwrite this.et2 in app.js
				undefined, undefined, true);
		}

		// Don't let dialog closing destroy the parent session
		if(this._template_widget.etemplate_exec_id && this._template_widget.app)
		{
			for(let et of etemplate2.getByApplication(this._template_widget.app))
			{
				if(et !== this._template_widget && et.etemplate_exec_id === this._template_widget.etemplate_exec_id)
				{
					// Found another template using that exec_id, don't destroy when dialog closes.
					this._template_widget.unbind_unload();
					break;
				}
			}
		}
		// set template-name as id, to allow to style dialogs
		this._template_widget.DOMContainer.setAttribute('id', this.__template.replace(/^(.*\/)?([^/]+?)(\.xet)?(\?.*)?$/, '$2').replace(/\./g, '-'));

		// Look for buttons after load
		this._overlayContentNode._contentNode.addEventListener("load", this._adoptTemplateButtons);
	}

	render()
	{
		return this._overlayTemplate();
	}

	/**
	 * Don't allow any children here, pass them on to the content node
	 *
	 * @param {HTMLElement} node
	 * @returns {any}
	 */
	appendChild(node : HTMLElement)
	{
		return this._overlayContentNode.appendChild(node);
	}

	/**
	 * Defining this overlay as a templates from OverlayMixin
	 * this is our source to give as .contentNode to OverlayController.
	 * @protected
	 */
	protected _overlayTemplate()
	{
		return html`
            <div id="overlay-content-node-wrapper">
                <et2-dialog-overlay-frame class="dialog__overlay-frame"
                                          .width=${this.width}
                                          .height=${this.height}
                                          ._dialog=${this}
                                          .buttons=${this._getButtons()}>
                    <span slot="heading">${this.title}</span>
                    ${this._contentTemplate()}

                </et2-dialog-overlay-frame>
            </div>
		`;
	}

	/**
	 * @override Configures OverlayMixin
	 */
	get _overlayContentNode()
	{
		if(this._cachedOverlayContentNode)
		{
			return this._cachedOverlayContentNode;
		}
		this._cachedOverlayContentNode = /** @type {HTMLElement} */ (
			/** @type {ShadowRoot} */ (this.shadowRoot).querySelector('.dialog__overlay-frame')
		);
		return this._cachedOverlayContentNode;
	}

	_contentTemplate()
	{
		if(this.__template)
		{
			return html`
                <div slot="content"></div>`;
		}
		else
		{
			return html`
                <et2-dialog-content slot="content" ?icon=${this.icon}
                                    .dialog_type=${this.dialog_type}
                                    .value=${this.value}
                >
                    ${this.message}
                </et2-dialog-content>
			`;
		}
	}

	_getButtons()
	{
		if(Number.isInteger(this.buttons))
		{
			// Translate as needed, since we're not calling transformAttributes() on the buttons
			// when we create them in a render()
			return this._buttons[this.buttons].map((but) =>
			{
				but.label = this.egw().lang(but.label);
				return but
			});
		}
		else if(Array.isArray(this.buttons))
		{
			return this.buttons;
		}
	}

	/**
	 * Search for buttons in the template, and try to slot them
	 *
	 * We don't want to just grab them all, as there may be other buttons.
	 */
	_adoptTemplateButtons()
	{
		// Check for something with buttons slot set
		let search_in = this._template_widget?.DOMContainer || this._overlayContentNode._contentNode;
		let template_buttons = search_in.querySelectorAll('[slot="buttons"]') ||
			// Look for a dialog footer, which will contain several buttons and possible other widgets
			search_in.querySelectorAll(".dialogFooterToolbar") ||
			// Look for buttons anywhere
			search_in.querySelectorAll("et2-button");
		if(template_buttons)
		{
			template_buttons.forEach((button) =>
			{
				button.setAttribute("slot", "buttons");
				this._overlayContentNode.appendChild(button);
			})
		}
		return template_buttons;
	}

	/**
	 * @override Configures OverlayMixin
	 * @desc overrides default configuration options for this component
	 * @returns {Object}
	 */
	_defineOverlayConfig()
	{
		let not_modal = {
			hasBackdrop: false,
			preventsScroll: false,
			trapsKeyboardFocus: false,
		}
		return {
			...super._defineOverlayConfig(),
			hidesOnEscape: this.hideOnEscape,
			...(this.modal ? {} : not_modal)
		}


	}

	_setupMoveResize(element)
	{
		// Quick calculation of min size - dialog is made up of header, content & buttons
		let minHeight = 0;
		for(let e of element.shadowRoot.querySelector('.overlay').children)
		{
			minHeight += e.getBoundingClientRect().height + parseFloat(getComputedStyle(e).marginTop) + parseFloat(getComputedStyle(e).marginBottom)
		}

		interact(element)
			.resizable({
				edges: {bottom: true, right: true},
				listeners: {
					move: this._onMoveResize
				},
				modifiers: [
					// keep the edges inside the parent
					interact.modifiers.restrictEdges({
						outer: 'parent'
					}),

					// minimum size
					interact.modifiers.restrictSize({
						min: {width: 100, height: minHeight}
					})
				]
			})

			.draggable({
				allowFrom: ".overlay__heading",
				listeners: {
					move: this._onMoveResize
				},
			});
	}

	/**
	 * Inject application specific egw object with loaded translations into the dialog
	 *
	 * @param {string|egw} _egw_or_appname egw object with already loaded translations or application name to load translations for
	 */
	_setApiInstance(_egw_or_appname ? : string | IegwAppLocal)
	{
		if(typeof _egw_or_appname == 'undefined')
		{
			// @ts-ignore
			_egw_or_appname = egw_appName;
		}
		// if egw object is passed in because called from et2, just use it
		if(typeof _egw_or_appname != 'string')
		{
			this.__egw = _egw_or_appname;
		}
		// otherwise use given appname to create app-specific egw instance and load default translations
		else
		{
			this.__egw = egw(_egw_or_appname);
			this.egw().langRequireApp(this.egw().window, _egw_or_appname);
		}
	}

	egw() : IegwAppLocal
	{
		if(this.__egw)
		{
			return this.__egw;
		}
		else
		{
			return super.egw();
		}
	}

	/**
	 * Show a confirmation dialog
	 *
	 * @param {function} _callback Function called when the user clicks a button.  The context will be the et2_dialog widget, and the button constant is passed in.
	 * @param {string} _message Message to be place in the dialog.
	 * @param {string} _title Text in the top bar of the dialog.
	 * @param _value passed unchanged to callback as 2. parameter
	 * @param {integer|array} _buttons One of the BUTTONS_ constants defining the set of buttons at the bottom of the box
	 * @param {integer} _type One of the message constants.  This defines the style of the message.
	 * @param {string} _icon URL of an icon to display.  If not provided, a type-specific icon will be used.
	 * @param {string|egw} _egw_or_appname egw object with already laoded translations or application name to load translations for
	 *
	 * @return {Et2Dialog} You can use dialog.getComplete().then(...) to wait for the dialog to close.
	 */
	static show_dialog(_callback? : Function, _message? : string, _title? : string, _value? : object, _buttons?, _type? : number, _icon? : string, _egw_or_appname? : string | IegwAppLocal)
	{
		// Just pass them along, widget handles defaults & missing
		let dialog = <Et2Dialog><unknown>document.createElement('et2-dialog');
		dialog._setApiInstance(_egw_or_appname);
		dialog.transformAttributes({
			callback: _callback || function() {},
			message: _message,
			title: _title || dialog.egw().lang('Confirmation required'),
			buttons: typeof _buttons != 'undefined' ? _buttons : Et2Dialog.BUTTONS_YES_NO,
			dialog_type: typeof _type != 'undefined' ? _type : Et2Dialog.QUESTION_MESSAGE,
			icon: _icon,
			value: _value
		});

		document.body.appendChild(<LitElement><unknown>dialog);
		return dialog;
	};

	/**
	 * Show an alert message with OK button
	 *
	 * @param {string} _message Message to be place in the dialog.
	 * @param {string} _title Text in the top bar of the dialog.
	 * @param {integer} _type One of the message constants.  This defines the style of the message.
	 *
	 * @return Promise<[ button_id : number, value : Object ]> will resolve when the dialog closes
	 */
	static alert(_message? : string, _title? : string, _type?)
	{
		let dialog = <Et2Dialog><unknown>document.createElement('et2-dialog');
		dialog._setApiInstance();
		dialog.transformAttributes({
			callback: function() {},
			message: _message,
			title: _title,
			buttons: Et2Dialog.BUTTONS_OK,
			dialog_type: _type || Et2Dialog.INFORMATION_MESSAGE
		});

		document.body.appendChild(<LitElement><unknown>dialog);

		return dialog.getComplete();
	}

	/**
	 * Show a prompt dialog
	 *
	 * @param {function} _callback Function called when the user clicks a button.  The button constant is passed in along with the value.
	 * @param {string} _message Message to be place in the dialog.
	 * @param {string} _title Text in the top bar of the dialog.
	 * @param {string} _value for prompt, passed to callback as 2. parameter
	 * @param {integer|array} _buttons One of the BUTTONS_ constants defining the set of buttons at the bottom of the box
	 * @param {string|egw} _egw_or_appname egw object with already laoded translations or application name to load translations for
	 *
	 * @return {Et2Dialog} You can use dialog.getComplete().then(...) to wait for the dialog to close.
	 */
	static show_prompt(_callback, _message, _title?, _value?, _buttons?, _egw_or_appname?)
	{
		let dialog = <Et2Dialog><unknown>document.createElement('et2-dialog');
		dialog._setApiInstance();
		dialog.transformAttributes({
			// Wrap callback to _only_ return _value.value, not the whole object like we normally would
			callback: function(_button_id, _value)
			{
				if(typeof _callback == "function")
				{
					_callback.call(this, _button_id, _value.value);
				}
			},
			title: _title || 'Input required',
			buttons: _buttons || Et2Dialog.BUTTONS_OK_CANCEL,
			value: {
				content: {
					value: _value,
					message: _message
				}
			},
			template: egw.webserverUrl + '/api/etemplate.php/api/templates/default/prompt.xet',
			class: "et2_prompt"
		});

		document.body.appendChild(<LitElement><unknown>dialog);

		return dialog
	}

	/**
	 * Method to build a confirmation dialog only with
	 * YES OR NO buttons and submit content back to server
	 *
	 * @param {widget} _senders widget that has been clicked
	 * @param {string} _dialogMsg message shows in dialog box
	 * @param {string} _titleMsg message shows as a title of the dialog box
	 * @param {boolean} _postSubmit true: use postSubmit instead of submit
	 *
	 * @description submit the form contents including the button that has been pressed
	 */
	static confirm(_senders, _dialogMsg, _titleMsg, _postSubmit?)
	{
		let senders = _senders;
		let buttonId = _senders.id;
		let dialogMsg = (typeof _dialogMsg != "undefined") ? _dialogMsg : '';
		let titleMsg = (typeof _titleMsg != "undefined") ? _titleMsg : '';
		let egw = _senders instanceof et2_widget ? _senders.egw() : undefined;
		let callbackDialog = function(button_id)
		{
			if(button_id == Et2Dialog.YES_BUTTON)
			{
				if(_postSubmit)
				{
					senders.getRoot().getInstanceManager().postSubmit(buttonId);
				}
				else if(senders.instanceOf(et2_button) && senders.getType() !== "buttononly")
				{
					senders.clicked = true;
					senders.getInstanceManager().submit(senders, false, senders.options.novalidate);
					senders.clicked = false;
				}
				else
				{
					senders.getRoot().getInstanceManager().submit(buttonId);
				}
			}
		};
		Et2Dialog.show_dialog(callbackDialog, dialogMsg, titleMsg, {},
			Et2Dialog.BUTTONS_YES_NO, Et2Dialog.WARNING_MESSAGE, undefined, egw);
	};


	/**
	 * Show a dialog for a long-running, multi-part task
	 *
	 * Given a server url and a list of parameters, this will open a dialog with
	 * a progress bar, asynchronously call the url with each parameter, and update
	 * the progress bar.
	 * Any output from the server will be displayed in a box.
	 *
	 * When all tasks are done, the callback will be called with boolean true.  It will
	 * also be called if the user clicks a button (OK or CANCEL), so be sure to
	 * check to avoid executing more than intended.
	 *
	 * @param {function} _callback Function called when the user clicks a button,
	 *	or when the list is done processing.  The context will be the et2_dialog
	 *	widget, and the button constant is passed in.
	 * @param {string} _message Message to be place in the dialog.  Usually just
	 *	text, but DOM nodes will work too.
	 * @param {string} _title Text in the top bar of the dialog.
	 * @param {string} _menuaction the menuaction function which should be called and
	 * 	which handles the actual request. If the menuaction is a full featured
	 * 	url, this one will be used instead.
	 * @param {Array[]} _list - List of parameters, one for each call to the
	 *	address.  Multiple parameters are allowed, in an array.
	 * @param {string|egw} _egw_or_appname egw object with already laoded translations or application name to load translations for
	 *
	 * @return {et2_dialog}
	 */
	static long_task(_callback, _message, _title, _menuaction, _list, _egw_or_appname)
	{
		// Special action for cancel
		let buttons = [
			// OK starts disabled
			{"button_id": Et2Dialog.OK_BUTTON, label: 'ok', "default": true, "disabled": true, image: "check"},
			{
				"button_id": Et2Dialog.CANCEL_BUTTON, label: 'cancel', image: "cancel", click: function()
				{
					// Cancel run
					cancel = true;
					jQuery("button[button_id=" + Et2Dialog.CANCEL_BUTTON + "]", dialog.div.parent()).button("disable");
					update.call(_list.length, '');
				}
			}
		];
		let dialog = new Et2Dialog(_egw_or_appname);
		dialog.transformAttributes({
			template: dialog.egw().webserverUrl + '/api/etemplate.php/api/templates/default/long_task.xet',
			value: {
				content: {
					message: _message
				}
			},
			callback: function(_button_id, _value)
			{
				if(_button_id == Et2Dialog.CANCEL_BUTTON)
				{
					cancel = true;
				}
				if(typeof _callback == "function")
				{
					_callback.call(this, _button_id, _value.value);
				}
			},
			title: _title || 'please wait...',
			buttons: buttons
		});
		dialog.egw().window.document.body.appendChild(<LitElement><unknown>dialog);

		let log = null;
		let progressbar = null;
		let cancel = false;
		let totals = {
			success: 0,
			skipped: 0,
			failed: 0,
			widget: null
		};

		// Updates progressbar & log, calls next step
		let update = function(response)
		{
			// context is index
			let index = this || 0;

			progressbar.set_value(100 * (index / _list.length));
			progressbar.set_label(index + ' / ' + _list.length);

			// Display response information
			switch(response.type)
			{
				case 'error':
					let div = document.createElement("DIV");
					div.className = "message error";
					div.textContent = response.data
					log.appendChild(div);

					totals.failed++;

					// Ask to retry / ignore / abort
					let retry = new Et2Dialog(dialog.egw());
					retry.transformAttributes({
						callback: function(button)
						{
							debugger;
							switch(button)
							{
								case 'dialog[cancel]':
									cancel = true;
									return update.call(index, '');
								case 'dialog[skip]':
									// Continue with next index
									totals.skipped++;
									return update.call(index, '');
								default:
									// Try again with previous index
									return update.call(index - 1, '');
							}

						},
						message: response.data,
						title: '',
						buttons: [
							// These ones will use the callback, just like normal
							{label: dialog.egw().lang("Abort"), id: 'dialog[cancel]'},
							{label: dialog.egw().lang("Retry"), id: 'dialog[retry]'},
							{label: dialog.egw().lang("Skip"), id: 'dialog[skip]', default: true}
						],
						dialog_type: Et2Dialog.ERROR_MESSAGE
					});
					dialog.egw().window.document.body.appendChild(<LitElement><unknown>retry);
					// Early exit
					return;
				default:
					if(response && typeof response === "string")
					{
						totals.success++;
						let div = document.createElement("DIV");
						div.className = "message";
						div.textContent = response
						log.appendChild(div);
					}
					else if(response)
					{
						let div = document.createElement("DIV");
						div.className = "message error";
						div.textContent = JSON.stringify(response)
						log.appendChild(div);
					}
			}
			// Scroll to bottom
			let height = log.scrollHeight;
			log.scrollTop = height;

			// Update totals
			totals.widget.set_value(dialog.egw().lang(
				"Total: %1 Successful: %2 Failed: %3 Skipped: %4",
				_list.length, <string><unknown>totals.success, <string><unknown>totals.failed, <string><unknown>totals.skipped
			));

			// Fire next step
			if(!cancel && index < _list.length)
			{
				var parameters = _list[index];
				if(typeof parameters != 'object')
				{
					parameters = [parameters];
				}

				// Async request, we'll take the next step in the callback
				// We can't pass index = 0, it looks like false and causes issues
				dialog.egw().json(_menuaction, parameters, update, index + 1, true, index + 1).sendRequest();
			}
			else
			{
				// All done
				if(!cancel)
				{
					progressbar.set_value(100);
				}

				// Disable cancel (it's too late), enable OK
				dialog._overlayContentNode.querySelector('et2-button[button_id="' + Et2Dialog.CANCEL_BUTTON + '"]').setAttribute("disabled")
				dialog._overlayContentNode.querySelector('et2-button[button_id="' + Et2Dialog.OK_BUTTON + '"]').removeAttribute("disabled")
				if(!cancel && typeof _callback == "function")
				{
					_callback.call(dialog, true, response);
				}
			}
		};

		// Wait for dialog, then start the process
		dialog.getUpdateComplete().then(function()
		{
			// Get access to template widgets
			log = dialog.template.widgetContainer.getDOMWidgetById('log').getDOMNode();
			progressbar = dialog.template.widgetContainer.getWidgetById('progressbar');
			progressbar.set_label('0 / ' + _list.length);
			totals.widget = dialog.template.widgetContainer.getWidgetById('totals');

			// Start
			window.setTimeout(function()
			{
				update.call(0, '');
			}, 0);
		});

		return dialog;
	}
}

//@ts-ignore TS doesn't recognize Et2Dialog as HTMLEntry
customElements.define("et2-dialog", Et2Dialog);
// make et2_dialog publicly available as we need to call it from templates
{
	window['et2_dialog'] = Et2Dialog;
	window['Et2Dialog'] = Et2Dialog;
}