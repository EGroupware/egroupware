/**
 * EGroupware eTemplate2 - InvokerMixing
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Ralf Becker
 */

/* eslint-disable import/no-extraneous-dependencies */
import {css, dedupeMixin, html, LitElement, SlotMixin} from '@lion/core';
import {Et2InputWidget, Et2InputWidgetInterface} from "../Et2InputWidget/Et2InputWidget";
import {colorsDefStyles} from "../Styles/colorsDefStyles";

/**
 * Invoker mixing adds an invoker button to a widget to trigger some action, e.g.:
 * - searchbox to delete input
 * - url to open url
 * - url-email to open mail compose
 *
 * Inspired by Lion date-picker.
 */

type Constructor<T = Et2InputWidgetInterface> = new (...args : any[]) => T;
export const Et2InvokerMixin = dedupeMixin(<T extends Constructor<LitElement>>(superclass : T) =>
{
	class Et2Invoker extends SlotMixin(Et2InputWidget(superclass))
	{
		/** @type {any} */
		static get properties()
		{
			return {
				/**
				 * Textual label or image specifier for egw.image()
				 */
				_invokerLabel: {
					type: String,
				},
				_invokerTitle: {
					type: String,
				},
				_invokerAction: {
					type: Function,
				}
			};
		}

		static get styles()
		{
			return [
				...super.styles,
				colorsDefStyles,
				css`
				::slotted(input), input, ::slotted(select) {
					background-color: transparent;
					border: none !important;
				}
				.input-group {
					border: 1px solid var(--input-border-color);
				}
				.input-group__suffix{
					text-align: center;
				}
				.input-group__container {
					align-items: center
				}
				::slotted([slot="suffix"]) {
					width: 14px;
					border: none !important;
					background-color: transparent !important;
					width: 1em;
					height: 1em;
					background-position: center right;
					background-size: contain;
					background-repeat: no-repeat;
				}
				::slotted(:disabled) {cursor: default !important;}
				:host(:hover) ::slotted([slot="suffix"]) {
					cursor: pointer;
				}
			`,
			];
		}

		get slots()
		{
			return {
				...super.slots,
				suffix: () =>
				{
					return this._invokerTemplate();
				},
			};
		}

		/**
		 * @protected
		 */
		get _invokerNode()
		{
			return /** @type {HTMLElement} */ (this.querySelector(`#${this.__invokerId}`));
		}

		constructor(...args : any[])
		{
			super(...args);
			/** @private */
			this.__invokerId = this.__createUniqueIdForA11y();
			// default for properties
			this._invokerTitle = 'Click to open';
		}

		/** @private */
		__createUniqueIdForA11y()
		{
			return `${this.localName}-${Math.random().toString(36).substr(2, 10)}`;
		}

		/**
		 * @param {PropertyKey} name
		 * @param {?} oldValue
		 */
		requestUpdate(name, oldValue)
		{
			super.requestUpdate(name, oldValue);

			if (name === 'disabled' || name === 'showsFeedbackFor' || name === 'modelValue')
			{
				this._toggleInvokerDisabled();
			}

			if (name === '_invokerLabel' || name === '_invokerTitle')
			{
				this._toggleInvoker();
			}
			if (name === '_invokerAction')
			{
				if (oldValue) this._invokerNode?.removeEventListener('click', oldValue);
				this._invokerNode?.addEventListener('click', this._invokerAction.bind(this), true);
			}
		}

		/**
		 * (Un)Hide invoker, if no label or action defined
		 *
		 * @protected
		 * */
		_toggleInvoker()
		{
			if (this._invokerNode)
			{
				this._invokerNode.style.display = !this._invokerLabel ? 'none' : 'inline-block';
				const img = this._invokerLabel ? this.egw().image(this._invokerLabel) : null;
				if (img)
				{
					this._invokerNode.style.backgroundImage = 'url('+img+')';
					this._invokerNode.innerHTML = '';
				}
				else
				{
					this._invokerNode.style.backgroundImage = 'none';
					this._invokerNode.innerHTML = this._invokerLabel || '';
				}
				this._invokerNode.title = this._invokerTitle || '';
			}
		}

		/**
		 * Method to check if invoker can be activated: not disabled, empty or invalid
		 *
		 * @protected
		 * */
		_toggleInvokerDisabled()
		{
			if (this._invokerNode)
			{
				const invokerNode = /** @type {HTMLElement & {disabled: boolean}} */ (this._invokerNode);
				invokerNode.disabled = this.disabled || !this.value || (this.input && !this.input.reportValidity())
			}
		}

		/**
		 * Reimplemented to enable/disable invoker on content change
		 *
		 * @param _ev
		 * @returns
		 */
		_oldChange(_ev: Event): boolean
		{
			this._toggleInvokerDisabled();

			return super._oldChange(_ev);
		}

		/** @param {import('@lion/core').PropertyValues } changedProperties */
		firstUpdated(changedProperties)
		{
			super.firstUpdated(changedProperties);
			this._toggleInvokerDisabled();
			this._toggleInvoker();
		}

		/**
		 * Subclassers can replace this with their custom extension invoker,
		 * like `<my-button><calendar-icon></calendar-icon></my-button>`
		 */
		// eslint-disable-next-line class-methods-use-this
		_invokerTemplate()
		{
			return html`
                <button
                        type="button"
                        @click="${this._invokerAction}"
                        id="${this.__invokerId}"
                        aria-label="${this._invokerTitle}"
                        title="${this._invokerTitle}"
                >
                    ${this._invokerLabel}
                </button>
			`;
		}
	}

	return Et2Invoker as unknown as Constructor<Et2Invoker> & T;
})