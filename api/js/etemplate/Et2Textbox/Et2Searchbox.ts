/**
 * EGroupware eTemplate2 - Searchbox widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Ralf Becker
 */

/* eslint-disable import/no-extraneous-dependencies */
import {Et2Textbox} from "./Et2Textbox";
import {property} from "lit/decorators/property.js";
import {egw} from "../../jsapi/egw_global";

/**
 * @customElement et2-searchbox
 */
export class Et2Searchbox extends Et2Textbox
{

	/**
	 * Define whether the searchbox overlays while it's open (true) or stay as solid box in front of the search button (false). Default is false.
	 * @todo implement again
	 */
	@property({type: Boolean}) overlay;
	/**
	 * Define whether the searchbox should be a fix input field or flexible search button. Default is true (fix).
	 * @todo implement again
	 */
	@property({type: Boolean}) fix;

	/**
	 * Fire a change event when the user stops typing instead of waiting for enter or blur.
	 */
	@property({type: Boolean}) autochange;

	protected _searchTimeout : number;

	constructor(...args : any[])
	{
		super(...args);

		this.overlay = false;
		this.fix = true;

		this.clearable = true;
		this.type = 'search';
		this.placeholder = egw.lang('Search');
		this.enterkeyhint = egw.lang('Search');
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();

		// Stop timeout timer
		clearTimeout(this._searchTimeout);
	}

	/**
	 * Overwritten to trigger a change/search
	 *
	 * @param event
	 */
	handleKeyDown(event: KeyboardEvent)
	{
		clearTimeout(this._searchTimeout);
		const hasModifier = event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;

		// Pressing enter when focused on an input should submit the form like a native input, but we wait a tick before
		// submitting to allow users to cancel the keydown event if they need to
		if (event.key === 'Enter' && !hasModifier)
		{
			event.preventDefault();

			// Stop from bubbling; enter in search is just for here.
			event.stopPropagation();

			// Lose focus, which triggers change, instead of calling change handler which would trigger again when losing focus
			this.blur();
		}
		// Start the search automatically if they have enough letters
		if(this.autochange && this.value.length > 0)
		{
			this._searchTimeout = window.setTimeout(() =>
			{
				this.dispatchEvent(new Event("change"));
			}, 500);
		}
	}
}
// @ts-ignore TypeScript is not recognizing that this is a LitElement
customElements.define("et2-searchbox", Et2Searchbox);