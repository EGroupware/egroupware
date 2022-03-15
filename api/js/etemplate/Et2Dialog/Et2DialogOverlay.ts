import {css, html, LitElement, repeat, SlotMixin} from '@lion/core';
import {DialogButton, Et2Dialog} from "./Et2Dialog";
import {Et2Widget} from "../Et2Widget/Et2Widget";

/**
 * This handles the visible portion of the dialog, including the title & close button.
 *
 */
export class Et2DialogOverlay extends Et2Widget(SlotMixin(LitElement))
{

	protected buttons : DialogButton[];

	protected _dialog : Et2Dialog;


	static get styles()
	{
		return [
			css`
        :host {
          display: inline-block;
          background: white;
          position: relative;
          border: 1px solid silver;
          min-width: 200px
        }

        :host([hidden]) {
          display: none;
        }

        .overlay__header {
          display: flex;
        }

        .overlay__heading {
			margin: 0px;
			padding: 6px 16px 8px;
			flex: 1;
        }

        .overlay__heading > .overlay__close-button {
          flex: none;
        }

        .overlay__close-button {
          min-width: 40px;
          min-height: 32px;
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
        }
        ::slotted([slot="buttons"]) {
			flex: 1 0 auto;
		}
		::slotted([align="right"]) {
			margin-left: auto;
			order: 1;
		}
      `,
		];
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

	firstUpdated(_changedProperties)
	{
		super.firstUpdated(_changedProperties);
		// Tell content about its parent, but don't move it
		//@ts-ignore
		(<Et2Widget><unknown>this.querySelector("[slot='content']"))._parent = this._dialog;
	}


	// Need to wait for Overlay
	async getUpdateComplete()
	{
		await super.getUpdateComplete();
		await this._contentNode.getUpdateComplete();
	}

	/**
	 * Dialog might not be part of an etemplate, use dialog's egw
	 *
	 * @returns {IegwAppLocal}
	 */
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

	get _contentNode()
	{
		return this.querySelector("[slot='content']");
	}

	/** @private */
	__dispatchCloseEvent()
	{
		this.dispatchEvent(new Event('close-overlay'));
	}

	render()
	{
		// eslint-disable-line class-methods-use-this
		return html`
            <div class="overlay">
                <div class="overlay__header">
                    <h1 class="overlay__heading">
                        <slot name="heading"></slot>
                    </h1>
                    <slot name="header"></slot>
                    <button
                            @click="${this.__dispatchCloseEvent}"
                            id="close-button"
                            title="${this.egw().lang("Close")}"
                            aria-label="${this.egw().lang("Close dialog")}"
                            class="overlay__close-button"
                    >
                        <slot name="close-icon">&times;</slot>
                    </button>
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

	_buttonsTemplate()
	{
		// Set button._parent here, otherwise button will have trouble finding our egw()
		return html`${repeat(this.buttons, (button : DialogButton) => button.id, (button, index) =>
		{
			return html`
                <et2-button ._parent=${this} id=${button.id} button_id=${button.button_id} .image=${button.image}
                            label=${button.text}
                            ?align=${button.align}>
                </et2-button>
			`
		})}`;
	}
}
