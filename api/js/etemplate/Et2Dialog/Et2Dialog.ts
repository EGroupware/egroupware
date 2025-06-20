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
import {css, html, LitElement, render} from "lit";
import {classMap} from "lit/directives/class-map.js";
import {ifDefined} from "lit/directives/if-defined.js";
import {repeat} from "lit/directives/repeat.js";
import {styleMap} from "lit/directives/style-map.js";
import type {etemplate2} from "../etemplate2";
import {egw, IegwAppLocal} from "../../jsapi/egw_global";
import interact from "@interactjs/interactjs";
import type {InteractEvent} from "@interactjs/core/InteractEvent";
import {Et2Button} from "../Et2Button/Et2Button";
import shoelace from "../Styles/shoelace";
import {SlDialog} from "@shoelace-style/shoelace";
import {egwIsMobile} from "../../egw_action/egw_action_common";
import {waitForEvent} from "../Et2Widget/event";
import {property} from "lit/decorators/property.js";

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
 * A common dialog widget that makes it easy to inform users or prompt for information.
 *
 * @slot - The dialog's main content
 * @slot label - The dialog's title.  Alternatively, you can use the title attribute.
 * @slot header-actions - Optional actions to add to the header. Works best with <et2-button-icon>
 * @slot footer - The dialog's footer, where we put the buttons.
 *
 * @event open - Emitted when the dialog opens
 * @event close - Emitted when the dialog closes
 * @event before-load - Emitted before the dialog opens
 */
export class Et2Dialog extends Et2Widget(SlDialog)
{
	/**
	 * Dialogs don't always get added to an etemplate, so we keep our own egw
	 *
	 * @type {IegwAppLocal}
	 * @protected
	 * @internal
	 */
	protected __egw : IegwAppLocal

	/**
	 * As long as the template is a legacy widget, we want to hold on to the widget
	 * When it becomes a WebComponent, we can just include it in render()
	 *
	 * @type {et2_template | null}
	 * @protected
	 * @internal
	 */
	private __template : string; // Name
	protected _template_widget : etemplate2 | null;
	protected _template_promise : Promise<boolean>;

	/**
	 * Treat the dialog as an atomic operation, and use this promise to notify when
	 * "done" instead of (or in addition to) using the callback function.
	 * It gives the button ID and the dialog value.
	 * @internal
	 */
	protected _complete_promise : Promise<[number, Object]>;

	/**
	 * Resolve the template promise
	 */
	private _templateResolver : (value) => void;
	/**
	 * Resolve the dialog complete promise
	 */
	private _completeResolver : (value) => [number, Object];

	/**
	 * The ID of the button that was clicked.  Always one of the button constants,
	 * unless custom buttons were used
	 *
	 * @type {number|null}
	 * @protected
	 * @internal
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


	static get styles()
	{
		return [
			...shoelace,
			...(super.styles || []),
			css`
				:host {
					--header-spacing: var(--sl-spacing-medium);
					--body-spacing: var(--sl-spacing-medium);
				    --width: auto;
				}
				.dialog__panel {
					border: 1px solid silver;
					box-shadow: -2px 1px 9px 3px var(--sl-color-gray-400);
					min-width: 250px;
					touch-action: none;
				}
				.dialog__header {
					display: flex;
          			border-bottom: 1px inset;
				}
				.dialog__title {
					font-size: var(--sl-font-size-medium);
					user-select: none;
				}
				.dialog__close {
					padding: 0;
					order: 99;
					border-top-right-radius: calc(var(--sl-border-radius-medium) * .5);
				}
				.dialog__footer	{
					--footer-spacing: 5px;
					display: flex;
					flex-wrap: nowrap;
					justify-content: flex-start;
					align-items: stretch;
					gap: 5px;
					border-top: 1px solid var(--sl-color-gray-400);
					margin-top: 0.5em;
				}

			  .dialog_content {
				height: var(--height, auto);
			  }

			  /* Non-modal dialogs don't have an overlay */

			  :host(:not([ismodal])) .dialog, :host(:not([isModal])) .dialog__overlay {
				pointer-events: none;
				background: transparent;
			  }

			  :host(:not([ismodal])) .dialog__panel {
				pointer-events: auto;
			  }

			  /* Hide close button when set */

			  :host([noclosebutton]) .dialog__close {
				display: none;
			  }

			  /* Button alignments */

			  ::slotted([align="left"]) {
				margin-right: auto;
				order: -1;
			  }

			  ::slotted([align="right"]) {
				margin-left: auto;
				order: 1;
			  }
			`
		];
	}

	/**
	 * Function called when the dialog is closed
	 *
	 * Wait for dialog.getComplete() instead
	 */
	@property({type: Function})
	callback : Function;

	/**
	 * Allow other controls to be accessed while the dialog is visible
	 * while not conflicting with internal attribute
	 */
	@property({type: Boolean, reflect: true})
	isModal : boolean;

	/**
	 * Pre-defined group of buttons, one of the BUTTONS_*
	 */
	@property({type: Number})
	buttons : Number;

	// Force size on the dialog.  Normally it sizes to content.
	@property({type: Number})
	width : number;
	// Force size on the dialog.  Normally it sizes to content.
	@property({type: Number})
	height : number;

	/**
	 * Message to show to user
	 */
	@property({type: String})
	message : string;

	/**
	 * Pre-defined dialog styles
	 */
	@property({type: Number})
	dialog_type : number;

	/**
	 * Include an icon on the dialog
	 *
	 * @type {string}
	 */
	@property({type: String})
	icon : string;

	/**
	 * Automatically destroy the dialog when it closes.  Set to false to keep the dialog around.
	 */
	@property({type: Boolean})
	destroyOnClose : boolean;

	/**
	 * When it's set to false dialog won't get closed by hitting Esc
	 */
	@property({type: Boolean})
	hideOnEscape : boolean;

	/**
	 * When set to true it removes the close button from dialog's header
	 */
	@property({type: Boolean, reflect: true})
	noCloseButton : boolean;


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
		this.isModal = false;
		this.dialog_type = Et2Dialog.PLAIN_MESSAGE;
		this.destroyOnClose = true;
		this.hideOnEscape = this.hideOnEscape === false ? false : true;
		this.__value = {};
		this.open = true;

		this.handleOpen = this.handleOpen.bind(this);
		this.handleClose = this.handleClose.bind(this);
		this._onClick = this._onClick.bind(this);
		this._onButtonClick = this._onButtonClick.bind(this);
		this._onMoveResize = this._onMoveResize.bind(this);
		this.handleKeyUp = this.handleKeyUp.bind(this);
		this._adoptTemplateButtons = this._adoptTemplateButtons.bind(this);

		// Don't leave it undefined, it's easier to deal with if it's just already resolved.
		// It will be re-set if a template is loaded
		this._template_promise = Promise.resolve(false);

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

		this.addEventListener("keyup", this.handleKeyUp);

		// Prevent close if they click the overlay when the dialog is modal
		this.addEventListener('sl-request-close', event =>
		{
			// Prevent close on clicking somewhere else
			if(this.isModal && event.detail.source === 'overlay')
			{
				event.preventDefault();
				return;
			}
			// Prevent close on escape
			if(!this.hideOnEscape && event.detail.source === 'keyboard')
			{
				event.preventDefault();
				return;
			}
		})

		this.addEventListener("sl-after-show", this.handleOpen);
		this.addEventListener('sl-hide', this.handleClose);

	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		this.removeEventListener("keyup", this.handleKeyUp);
		this.removeEventListener("sl-hide", this.handleClose);
		this.removeEventListener("sl-after-show", this.handleOpen);
	}

	destroy()
	{
		if(this._template_widget)
		{
			this._template_widget.clear(true);
		}
		this.remove();
	}

	/**
	 * Hide the dialog.
	 * Depending on destroyOnClose, it may be removed as well
	 *
	 * N.B. We can't have open() because it conflicts with SlDialog.  Use show() instead.
	 */
	close()
	{
		return this.hide();
	}

	addOpenListeners()
	{
		super.addOpenListeners();

		// Bind on the ancestor, not the buttons, so their click handler gets a chance to run
		this.addEventListener("click", this._onButtonClick);
		this.addEventListener("keydown", this.handleKeyDown);
	}

	removeOpenListeners()
	{
		super.removeOpenListeners();
		this.removeEventListener("click", this._onButtonClick);
		this.removeEventListener("keydown", this.handleKeyDown);
	}

	handleKeyUp(event : KeyboardEvent)
	{
		// Trigger the "primary" or first button
		if(this.open && event.key === 'Enter')
		{
			let button = this.querySelectorAll("[varient='primary']");
			if(button.length == 0)
			{
				// Nothing explicitly marked, check for buttons in the footer
				button = this.querySelectorAll("et2-button[slot='footer']");
			}
			if(button && button[0])
			{
				event.stopPropagation();
				button[0].dispatchEvent(new CustomEvent('click', {bubbles: true}));
			}
		}
	}

	firstUpdated(changedProperties)
	{
		super.firstUpdated(changedProperties);

		render(this._contentTemplate(), this);

		// Rendering content will change some things, SlDialog needs to update
		this.requestUpdate()

		// If we start open, fire handler to get setup done
		if(this.open)
		{
			this.handleOpenChange();
		}

		this.updateComplete.then(() => this._setDefaultAutofocus());
	}

	// Need to wait for Overlay
	async getUpdateComplete()
	{
		let result = await super.getUpdateComplete();

		// Wait for template to finish loading
		await this._template_promise;

		return result;
	}

	getComplete() : Promise<[number, Object]>
	{
		return this._complete_promise;
	}

	handleOpen(event)
	{
		if(event.target !== this)
		{
			return;
		}

		this.addOpenListeners();
		this._button_id = null;
		this._complete_promise = this._complete_promise || new Promise<[number, Object]>((resolve) =>
		{
			this._completeResolver = value => resolve(value);
		});

		// Now consumers can listen for "open" event, though getUpdateComplete().then(...) also works
		this.dispatchEvent(new Event('open', {bubbles: true}));

		Promise.all([this._template_promise, this.updateComplete])
			.then(() => this._setupMoveResize());
	}

	handleClose(ev : PointerEvent)
	{
		// Avoid closing if a selectbox is closed
		if(ev.target !== this)
		{
			return;
		}

		if(typeof document.activeElement?.blur == "function")
		{
			document.activeElement?.blur();
		}
		this.removeOpenListeners();
		this._completeResolver([this._button_id, this.value]);

		interact(this.panel).unset();

		this.dispatchEvent(new Event('close', {bubbles: true}));

		waitForEvent(this, 'sl-after-hide').then(() =>
		{
			this._button_id = null;

			this._complete_promise = undefined;
			if(this.destroyOnClose)
			{
				if(this._template_widget)
				{
					this._template_widget.clear();
				}
				this.remove();
			}
		});
	}

	/**
	 * Only internally do our onClick on buttons in the footer
	 * This calls _onClose() when the dialog is closed
	 *
	 * @param {MouseEvent} ev
	 * @returns {boolean}
	 */
	_onButtonClick(ev : MouseEvent)
	{
		if(ev.target instanceof Et2Button && ev.target.slot == 'footer')
		{
			return this._onClick(ev);
		}
	}

	_onClick(ev : MouseEvent)
	{
		// @ts-ignore
		this._button_id = ev.target?.getAttribute("button_id") ? parseInt(ev.target?.getAttribute("button_id")) : (ev.target?.getAttribute("id") || null);

		// we need to consider still buttons used in dialogs that may actually submit and have server-side interactions(eg.vfsSelect)
		if(!ev.target?.getInstanceManager()?._etemplate_exec_id)
		{
			// we always need to stop the event as otherwise the result would be submitted to server-side eT2 handler
			// which does not know what to do with it, as the dialog was initiated from client-side (no eT2 request)
			ev.preventDefault();
			ev.stopPropagation();
		}


		// Handle anything bound via et2 onclick property
		try
		{
			let et2_widget_result = super._handleClick(ev);
			if(et2_widget_result === false)
			{
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
			let callback_result = this.callback ? this.callback(this._button_id, this.value, ev) : true;
			if(callback_result === false)
			{
				return false;
			}
		}
		catch(e)
		{
			console.log(e);
		}
		this.hide();
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
	 * @deprecated
	 * @returns {Object}
	 */
	get_value() : Object
	{
		console.warn("Deprecated get_value() called");
		return this.value;
	}

	@property({type: Object})
	set value(new_value : Object)
	{
		this.__value = new_value;
	}

	/**
	 * Instead of a simple message, show this template file instead
	 */
	@property({type: String})
	set template(new_template_name : string)
	{
		let old_template = this.__template;
		this.__template = new_template_name;

		// Create the new promise here so we can wait for it immediately, not in update
		this._template_promise = new Promise<boolean>((resolve) =>
		{
			this._templateResolver = value => resolve(value);
		});
		if(!this.__template)
		{
			this._templateResolver(true);
		}
		this.requestUpdate("template", old_template);
	}


	/**
	 * Getter for template name.
	 *
	 * Historically this returned the etemplate2 widget, but this was incorrect and has been fixed.
	 * Use `eTemplate` instead of `template` to access the etemplate2 widget.
	 *
	 * @returns {string}
	 */
	get template()
	{
		// Can't return undefined or requestUpdate() will not notice a change
		return this.__template || null;
	}

	/**
	 * The loaded etemplate2 object.
	 *
	 * Only available if `template` is set
	 *
	 * @returns {etemplate2}
	 */
	get eTemplate()
	{
		return this._template_widget;
	}

	/**
	 * Title for the dialog, goes in the header
	 */
	@property()
	set title(new_title : string)
	{
		this.label = new_title;
	}

	get title() : string { return this.label }

	updated(changedProperties)
	{
		super.updated(changedProperties);
		if(changedProperties.has("template"))
		{
			// Wait until update is finished to avoid an error in Safari
			super.getUpdateComplete().then(() => this._loadTemplate());
		}
		if(changedProperties.has("buttons"))
		{
			//render(this._buttonsTemplate(), this);
			this.requestUpdate();
		}
		if(changedProperties.has("width"))
		{
			this.style.setProperty("--width", this.width ? this.width + "px" : "initial");
		}
	}

	_loadTemplate()
	{
		if(this._template_widget)
		{
			this._template_widget.clear();
		}
		this._contentNode.replaceChildren();

		// Etemplate wants a content
		if(typeof this.__value.content === "undefined")
		{
			this.__value.content = {};
		}
		this._template_widget = new etemplate2(this._contentNode);

		// Fire an event so consumers can do their thing - etemplate will fire its own load event when it's done
		if(!this.dispatchEvent(new CustomEvent("before-load", {
			bubbles: true,
			cancelable: true,
			detail: this._template_widget
		})))
		{
			return;
		}

		this._template_widget.load(this.__template, '', this.__value || {},
				// true: do NOT call et2_ready, as it would overwrite this.et2 in app.js
				undefined, undefined, true)
				.then(() =>
				{
					this._templateResolver(true);

					// Don't let dialog closing destroy the parent session
					if (this._template_widget.etemplate_exec_id)
					{
						for(const et of etemplate2.getByEtemplateExecId(this._template_widget.etemplate_exec_id))
						{
							if (et !== this._template_widget)
							{
								// Found another template using that exec_id, don't destroy when dialog closes.
								this._template_widget.unbind_unload();
								break;
							}
						}
					}
				});

		// set template-name as id, to allow to style dialogs
		this._template_widget.DOMContainer.setAttribute('id', this.__template.replace(/^(.*\/)?([^/]+?)(\.xet)?(\?.*)?$/, '$2').replace(/\./g, '-'));

		// Look for buttons after load
		this._template_promise.then(() => {this._adoptTemplateButtons();});

		// Default autofocus to first input if autofocus is not set
		this._template_promise.then(() => {this._setDefaultAutofocus();});

		// Need to update to pick up changes
		this.requestUpdate();
	}

	_contentTemplate()
	{
		/**
		 * Classes for dialog type options
		 */
		const _dialogTypes : any = [
			//PLAIN_MESSAGE: 0
			"",
			//INFORMATION_MESSAGE: 1,
			"dialog_info",
			//QUESTION_MESSAGE: 2,
			"dialog_help",
			//WARNING_MESSAGE: 3,
			"dialog_warning",
			//ERROR_MESSAGE: 4,
			"dialog_error"
		];
		let icon = this.icon || this.egw().image(_dialogTypes[this.dialogType] || "") || "";
		let type = _dialogTypes[this.dialogType];
		let classes = {
			dialog_content: true,
			"dialog--has_message": this.message,
			"dialog--has_template": this.__template
		};
		if(type)
		{
			classes[type] = true;
		}

		// Add in styles set via property
		let styles = {};
		if(this.width)
		{
			styles["--width"] = this.width;
		}
		if(this.height)
		{
			styles["--height"] = this.height;
		}

		return html`
            <div class=${classMap(classes)} style="${styleMap(styles)}">
                ${this.__template ? "" :
                  html` <img class="dialog_icon" src=${icon}/>
                  <slot>${this.message}</slot>`
                }

            </div>${this._buttonsTemplate()}`;

	}

	_buttonsTemplate()
	{
		// No buttons set, but careful with BUTTONS_OK
		if(!this.buttons && this.buttons !== Et2Dialog.BUTTONS_OK)
		{
			return;
		}

		let buttons = this._getButtons();
		let hasDefault = false;
		buttons.forEach((button) =>
		{
			if(button.default)
			{
				hasDefault = true;
			}
		})

		// Set button._parent here, otherwise button will have trouble finding our egw()
		return html`${repeat(buttons, (button : DialogButton) => button.id, (button, index) =>
		{
			let isDefault = hasDefault && button.default || !hasDefault && index == 0;
			return html`
                <et2-button ._parent=${this} id=${button.id} button_id=${button.button_id}
                            label=${button.label}
                            slot="footer"
                            .image=${ifDefined(button.image)}
                            .noSubmit=${true}
                            ?disabled=${button.disabled}
                            variant=${isDefault ? "primary" : "default"}
                            align=${ifDefined(button.align)}>
                </et2-button>
			`
		})}`;
	}

	_getButtons() : DialogButton[]
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
		let search_in = <HTMLElement>(this._template_widget?.DOMContainer ?? this._contentNode);
		if(!search_in)
		{
			return;
		}
		let template_buttons = [
			...search_in.querySelectorAll('[slot="footer"],[slot="buttons"]'),
			// Look for a dialog footer, which will contain several buttons and possible other widgets
			...search_in.querySelectorAll(".dialogFooterToolbar et2-button"),
			// Look for buttons at high level (not everywhere, otherwise we can't have other buttons in the template)
			...search_in.querySelectorAll(":scope > et2-button, :scope > * > et2-button")
		];
		if(template_buttons)
		{
			if(template_buttons[0]?.instanceOf(Et2Button))
			{
				template_buttons[0].variant = "primary";
			}
			template_buttons.forEach((button) =>
			{
				button.setAttribute("slot", "footer");
				this.appendChild(button);
			});
			this.requestUpdate();
		}
		// do NOT submit dialog, if it has no etemplate_exec_id, it only gives and error on server-side
		if (this._template_widget && !this._template_widget.widgetContainer.getInstanceManager().etemplate_exec_id)
		{
			this._template_widget?.DOMContainer.querySelectorAll('et2-button').forEach((button : Et2Button) =>
			{
				button.noSubmit = true;
			});
		}
		return template_buttons;
	}

	/**
	 * Set autofocus on first input element if nothing has autofocus
	 */
	_setDefaultAutofocus()
	{
		const autofocused = this.querySelector("[autofocus]");
		if(autofocused)
		{
			return;
		}
		if(this._template_widget && typeof this._template_widget.focusOnFirstInput == "function")
		{
			this._template_widget.focusOnFirstInput();
		}
		else
		{
			// Not a template, but maybe something?
			const input = Array.from(this.querySelectorAll('input,et2-textbox,et2-select-email')).filter(element =>
			{
				// Skip invisible
				if(!element.checkVisibility())
				{
					return false;
				}

				// Date fields open the calendar popup on focus
				if(element.classList.contains("et2_date"))
				{
					return false;
				}
				// Skip inputs that are out of tab ordering
				return !element.hasAttribute('tabindex') || parseInt(element.getAttribute('tabIndex')) >= 0
			}).pop();

			// mobile device, focus only if the field is empty (usually means new entry)
			// should focus always for non-mobile one
			if(input && (egwIsMobile() && typeof input.getValue == "function" && input.getValue() == "" || !egwIsMobile()))
			{
				input.focus();
			}
		}
	}

	get _contentNode() : HTMLElement
	{
		return this.querySelector('.dialog_content');
	}

	_setupMoveResize()
	{
		// Quick calculation of min size - dialog is made up of header, content & buttons
		let minHeight = 0;
		for(let e of this.panel.children)
		{
			minHeight += e.getBoundingClientRect().height + parseFloat(getComputedStyle(e).marginTop) + parseFloat(getComputedStyle(e).marginBottom)
		}

		interact(this.panel)
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
				allowFrom: ".dialog__header",
				ignoreFrom: ".dialog__close",
				listeners: {
					move: this._onMoveResize
				},
				modifiers: (this.isModal ? [] : [
					interact.modifiers.restrict({
						restriction: 'parent',
						endOnly: true
					})
				])
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
	 * @param {function} _callback Function called when the user clicks a button.  The context will be the Et2Dialog widget, and the button constant is passed in.
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
		const document = !_egw_or_appname || typeof _egw_or_appname === 'string' ? window.document : _egw_or_appname.window.document;
		// Just pass them along, widget handles defaults & missing
		let dialog = <Et2Dialog><unknown>document.createElement('et2-dialog');
		dialog._setApiInstance(_egw_or_appname);
		dialog.transformAttributes({
			callback: _callback || function() {},
			message: _message,
			title: _title || dialog.egw().lang('Confirmation required'),
			buttons: typeof _buttons != 'undefined' ? _buttons : Et2Dialog.BUTTONS_YES_NO,
			isModal: true,
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
			isModal: true,
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
		const document = !_egw_or_appname || typeof _egw_or_appname === 'string' ? window.document : _egw_or_appname.window.document;
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
			isModal: true,
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
		let button = _senders;
		let dialogMsg = (typeof _dialogMsg != "undefined") ? _dialogMsg : '';
		let titleMsg = (typeof _titleMsg != "undefined") ? _titleMsg : '';
		let egw = _senders?.egw();
		let callbackDialog = function(button_id)
		{
			if(button_id == Et2Dialog.YES_BUTTON)
			{
				if(_postSubmit)
				{
					senders.getRoot().getInstanceManager().postSubmit(button);
				}
				else if(senders.instanceOf(Et2Button) && senders.getType() !== "buttononly")
				{
					senders.clicked = true;
					senders.getInstanceManager().submit(senders, false, senders.novalidate);
					senders.clicked = false;
				}
				else
				{
					senders.clicked = true;
					senders.getRoot().getInstanceManager().submit(button);
					senders.clicked = false;
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
	 *	or when the list is done processing.  The context will be the Et2Dialog
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
	 * @return {Et2Dialog}
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
					let button = <Et2Button>dialog.querySelector("button[button_id=" + Et2Dialog.CANCEL_BUTTON + "]");
					if(button)
					{
						button.disabled = true;
					}
					updateUi.call(_list.length, '');
				}
			}
		];
		const document = !_egw_or_appname || typeof _egw_or_appname === 'string' ? window.document : _egw_or_appname.window.document;
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
			isModal: true,
			buttons: buttons
		});
		document.body.appendChild(<LitElement><unknown>dialog);

		let log = null;
		let progressbar = null;
		let cancel = false;
		let skip_all = false;
		let totals = {
			success: 0,
			skipped: 0,
			failed: 0,
			widget: null
		};
		let success = [];
		let retryDialog = null;

		// Updates progressbar & log, returns next index
		let updateUi = function(response, index = 0)
		{
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
					if(skip_all)
					{
						totals.skipped++;
						break;
					}

					// Ask to retry / ignore / abort
					let retry = new Et2Dialog(dialog.egw());
					let retry_index = null;
					retry.transformAttributes({
						callback: function(button)
						{
							switch(button)
							{
								case 'dialog[cancel]':
									cancel = true;
									break;
								case 'dialog[skip]':
									totals.skipped++;
									break;
								case 'dialog[skip_all]':
									totals.skipped++;
									skip_all = true;
									break;
								default:
									// Try again with previous index
									retry_index = index - 1;
							}
						},
						message: response.data,
						title: '',
						buttons: [
							// These ones will use the callback, just like normal
							{label: dialog.egw().lang("Abort"), id: 'dialog[cancel]'},
							{label: dialog.egw().lang("Retry"), id: 'dialog[retry]'},
							{label: dialog.egw().lang("Skip"), id: 'dialog[skip]', default: true},
							{label: dialog.egw().lang("Skip all"), id: 'dialog[skip_all]'}
						],
						dialog_type: Et2Dialog.ERROR_MESSAGE
					});
					dialog.egw().window.document.body.appendChild(<LitElement><unknown>retry);
					// Early exit
					retryDialog = retry.getComplete().then(() =>
					{
						retryDialog = null;
						if(retry_index !== null)
						{
							sendRequest(retry_index)
						}
					});
				default:
					if(response && typeof response === "string")
					{
						success.push(_list[index - 1]);
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
				return Promise.resolve(index);
			}
		}

		/** Send off the request for one item */
		let sendRequest = function(index)
		{
			let request = null;
			let parameters = _list[index];
			if(typeof parameters != 'object')
			{
				parameters = [parameters];
			}

			// Set up timeout for 30 seconds
			const timeout_id = window.setTimeout(() =>
			{
				// Abort request, we'll either skip it or try again
				if(request && request.abort)
				{
					request.abort();
				}
				updateUi({type: 'error', data: dialog.egw().lang("failed") + " " + parameters.join(" ")}, index + 1)
			}, 30000);

				// Async request, we'll take the next step in the callback
				// We can't pass index = 0, it looks like false and causes issues
			try
			{
				request = dialog.egw().json(_menuaction, parameters).sendRequest()
					.then(async(response) =>
					{
						if(response && response.response)
						{
							clearTimeout(timeout_id);
							for(let value of response.response)
							{
								await updateUi(value.type == "data" ? value.data : value, index + 1);
							}
						}
					})
					.catch(async(response) =>
					{
						clearTimeout(timeout_id);
						updateUi({type: 'error', data: response.message ?? response}, index + 1);
					});
			}
			catch(e)
			{
				clearTimeout(timeout_id);
				request.abort();
				updateUi({type: 'error', data: dialog.egw().lang("No response from server: your data is probably NOT saved")}, index + 1);
			}
			return request;
		};
		// Wait for dialog, then start the process
		dialog.getUpdateComplete().then(async function()
		{
			// Get access to template widgets
			log = dialog.eTemplate.widgetContainer.getDOMWidgetById('log').getDOMNode();
			progressbar = dialog.eTemplate.widgetContainer.getWidgetById('progressbar');
			progressbar.set_label('0 / ' + _list.length);
			totals.widget = dialog.eTemplate.widgetContainer.getWidgetById('totals');

			for(let index = 0; index < _list.length && !cancel; index++)
			{
				await sendRequest(index);
				if(retryDialog)
				{
					await retryDialog;
				}
			}

			// All done
			if(!cancel)
			{
				progressbar.set_value(100);
			}

			// Disable cancel (it's too late), enable OK
			dialog.querySelector('et2-button[button_id="' + Et2Dialog.CANCEL_BUTTON + '"]').setAttribute("disabled", "")
			dialog.querySelector('et2-button[button_id="' + Et2Dialog.OK_BUTTON + '"]').removeAttribute("disabled")
			if(!cancel && typeof _callback == "function")
			{
				_callback.call(dialog, true, success);
			}
		});
		return dialog;
	}

	/**
	 * Show a dialog to confirm overwriting an existing file or suggest a new name
	 *
	 * @param {string} etemplate_exec_id
	 * @param {string} path File destination, should include trailing /
	 * @param {string} filename Original file name
	 * @param {string} mimetype
	 * @param {boolean} noConfirm Will not ask user about change or overwrite, just return new filename or null
	 * @param {string|IegwAppLocal} egw object or application name
	 *
	 * @return Promise<string | false | null> Accepted file name, false to cancel, null if there was no conflict
	 */
	static async confirm_file(etemplate_exec_id : string, path : string, filename : string, mimetype : string, noConfirm : boolean, _egw_or_appname : string | IegwAppLocal) : Promise<string | false | null>
	{
		const confirm_file_dialog = new Et2Dialog(_egw_or_appname);

		// Check to see if the file exists
		let exists = {exists: false, filename: "", errs: 0, msg: ""};

		exists = await confirm_file_dialog.egw().request(
			"EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_conflict_check", [
				etemplate_exec_id,
				path,
				filename,
				mimetype
			]);
		if(exists && exists.errs)
		{
			throw new Error(exists.msg || "Could not check for conflict " + path + "/" + filename);
		}
		if(!exists || !exists.exists)
		{
			// No conflicts, use requested name
			return null;
		}
		// If they don't want to be prompted, skip it and just return the name
		if(noConfirm)
		{
			return exists.filename ?? filename;
		}

		return confirmConflict(confirm_file_dialog.egw(), path, filename, exists.filename ?? filename);
	}
}

/* Ask the user if they want to overwrite or change the name, called by Et2Dialog.confirm_file() */
async function confirmConflict(egw, path, fileName : string, suggestedName : string) : Promise<string | false>
{
	const buttons = [
		{
			label: egw.lang("Overwrite"),
			id: "overwrite",
			class: "ui-priority-primary",
			"default": true,
			image: 'check'
		},
		{label: egw.lang("Rename"), id: "rename", image: 'edit'},
		{label: egw.lang("Cancel"), id: "cancel", image: "cancel"}
	];
	let button_id, value;
	if(path.endsWith("/"))
	{
		// Filename is up to user, let them rename
		[button_id, value] = <[string, Object]><unknown>await Et2Dialog.show_prompt(undefined,
			egw.lang('Do you want to overwrite existing file %1 in directory %2?', fileName, path),
			egw.lang('File %1 already exists', fileName),
			suggestedName ?? fileName, buttons, egw
		).getComplete();
	}
	else
	{
		// Filename is set, only ask to overwrite
		buttons.splice(1, 1);
		fileName = suggestedName ?? fileName;
		[button_id, value] = <[string, Object]><unknown>await Et2Dialog.show_dialog(undefined,
			egw.lang('Do you want to overwrite existing file %1 in directory %2?', fileName, path),
			egw.lang('File %1 already exists', fileName),
			undefined, buttons, Et2Dialog.QUESTION_MESSAGE, "", egw
		).getComplete();
	}
	switch(button_id)
	{
		case "rename":
			// Take suggestion
			return suggestedName;
		// fall through
		case "overwrite":
			// Upload as set
			return fileName;
		case "cancel":
			// Don't upload
			return false;
	}
}

//@ts-ignore TS doesn't recognize Et2Dialog as HTMLEntry
customElements.define("et2-dialog", Et2Dialog);
// make Et2Dialog publicly available as we need to call it from templates
{
	window['Et2Dialog'] = Et2Dialog;
}