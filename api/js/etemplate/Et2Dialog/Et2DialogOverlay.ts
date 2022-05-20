import {css, html, ifDefined, LitElement, repeat, SlotMixin} from '@lion/core';
import {DialogButton, Et2Dialog} from "./Et2Dialog";
import {et2_template} from "../et2_widget_template";
import {Et2DialogContent} from "./Et2DialogContent";

/**
 * This handles the visible portion of the dialog, including the title & close button.
 *
 * Note we can't extend Et2Widget.  If I try, something in the render / creation breaks and calling open() gives an
 * error with modal: true
 */
export class Et2DialogOverlay extends SlotMixin(LitElement)
{

	protected buttons : DialogButton[];

	protected _dialog : Et2Dialog;


	/**
	 * I don't know what's going on with styles here, but if we define Et2DialogOverlay.styles it breaks
	 * dialog display in Firefox.
	 * The styles are added in render(), which is bad from a performance standpoint, but good in that it works.
	 */
	static get _styles()
	{
		return css`
        :host {
          display: inline-block;
          background: white;
          position: relative;
          border: 1px solid silver;
          box-shadow: -2px 1px 9px 3px #b4b4b4;
          min-width: 250px;
          touch-action: none;
          box-sizing: border-box;
        }

        :host([hidden]) {
          display: none;
        }

		.overlay {
			display: flex;
			flex-direction: column;
			height: 100%;
		}
        .overlay__header {
          display: flex;
          border-bottom: 1px inset;
        }

        .overlay__heading {
			margin: 0px;
			padding: 6px 10px 0px;
			flex: 1;
			font-size: 130%;
			font-weight: 400;
        }
        #overlay-content-node-wrapper {
        	flex: 1 1 auto;
        	padding: 10px;
        }

        .overlay__heading > .overlay__close-button {
          flex: none;
        }

        .overlay__close-button {
          min-width: 24px;
          min-height: 24px;
          border-width: 0;
          padding: 0;
          font-size: 24px;
        }
        #overlay-content-buttons {
        	display: flex;
			flex-wrap: nowrap;
			justify-content: flex-start;
			align-items: stretch;
			gap: 5px;
			border-top: 1px solid silver;
			margin-top: 0.5em;
			padding: 5px;
        }
        /* Override style for legacy nextmatch action dialogs */
        ::slotted([slot="content"]) {
        	display: block !important;
        	position: relative  !important;
        	inset: initial !important;
        }
        ::slotted([slot="buttons"]) {
			flex: 1 0 auto;
		}
		::slotted([align="right"]) {
			margin-left: auto;
			order: 1;
		}
      `;
	}

	get properties()
	{
		return {
			// Allow to force size, otherwise it sizes to contents
			width: Number,
			height: Number,
		}
	}

	get slots()
	{
		return {
			...super.slots,
			buttons: () =>
			{
				return this._buttonsTemplate();
			}
		}
	}

	constructor()
	{
		super();
		this.buttons = [];
	}



	// Need to wait for Overlay
	async getUpdateComplete()
	{
		let result = await super.getUpdateComplete();
		if(this._contentNode && this._contentNode instanceof LitElement)
		{
			await (<LitElement>this._contentNode).updateComplete;
		}
		return result;
	}

	connectedCallback()
	{
		super.connectedCallback();
		// Need to wait for Overlay
		this.updateComplete
			.then(async() =>
			{
				if(this._contentNode && this._contentNode instanceof LitElement)
				{
					// Re-do render to get proper images
					this._contentNode.requestUpdate();

					await this._contentNode.updateComplete;
				}
			});
	}

	egw() : IegwAppLocal
	{
		if(this._dialog)
		{
			return this._dialog.egw();
		}
		else
		{
			return egw();
		}
	}

	/**
	 * Block until after the paint - This is needed to deal with children not fully "done" before the OverlayController
	 * tries to do things with them
	 *
	 * @returns {Promise<any>}
	 */
	async performUpdate()
	{
		await new Promise((resolve) => setTimeout(() => resolve()));
		return super.performUpdate();
	}

	get _contentNode() : Et2DialogContent | et2_template
	{
		// @ts-ignore
		return this.querySelector("[slot='content']");
	}

	/** @private */
	__dispatchCloseEvent()
	{
		this.dispatchEvent(new Event('close-overlay'));
	}

	render()
	{
		// This style is just for this dialog
		let styles = [Et2DialogOverlay._styles];

		if(this.width && Number.isInteger(this.width))
		{
			styles.push(css`.overlay {width: ${this.width}px}`);
		}
		if(this.height && Number.isInteger(this.height))
		{
			styles.push(css`.overlay {height: ${this.height}px}`);
		}
		return html`
            <style>${styles}</style>
            <div class="overlay">
                <div class="overlay__header">
                    <h1 class="overlay__heading">
                        <slot name="heading"></slot>
                    </h1>
                    <slot name="header"></slot>
                    ${this._closeButtonTemplate()}
                </div>
                <div id="overlay-content-node-wrapper">
                    <slot name="content"></slot>
                </div>
                <div id="overlay-content-buttons">
                    <slot name="buttons"></slot>
                </div>
            </div>
		`;
	}

	_closeButtonTemplate()
	{
		if (this._dialog.noCloseButton)
		{
			return;
		}

		return html`<button
							@click="${this.__dispatchCloseEvent}"
							id="close-button"
							title="${this.egw().lang("Close")}"
							aria-label="${this.egw().lang("Close dialog")}"
							class="overlay__close-button"
					>
						<slot name="close-icon">&times;</slot>
					</button>
		`;
	}

	_buttonsTemplate()
	{
		if(!this.buttons)
		{
			return;
		}

		// Set button._parent here, otherwise button will have trouble finding our egw()
		return html`${repeat(this.buttons, (button : DialogButton) => button.id, (button, index) =>
		{
			return html`
                <et2-button ._parent=${this} id=${button.id} button_id=${button.button_id}
                            label=${button.label}
                            .image=${ifDefined(button.image)}
                            .noSubmit=${true}
                            disabled=${ifDefined(button.disabled)}
                            align=${ifDefined(button.align)}>
                </et2-button>
			`
		})}`;
	}
}
