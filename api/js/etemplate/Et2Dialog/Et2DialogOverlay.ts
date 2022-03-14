import {css, html, LitElement, repeat, SlotMixin} from '@lion/core';
import {DialogButton, Et2Dialog} from "./Et2Dialog";

/**
 * This handles the visible portion of the dialog, including the title & close button.
 *
 * Note we can't extend Et2Widget.  If I try, something in the render / creation breaks and calling open() gives an
 * error
 */
export class Et2DialogOverlay extends SlotMixin(LitElement)
{
	private egw : IegwAppLocal;

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
		debugger;
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
                            title="${this.egw.lang("Close")}"
                            aria-label="${this.egw.lang("Close dialog")}"
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
		return html`${repeat(this.buttons, (button : DialogButton) => button.id, (button, index) => html`
            <et2-button id=${button.id} button_id=${button.button_id} .image=${button.image || ""} ?label=${button.text}></et2-button>
		`)}`;
	}
}
