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

/**
 * @customElement et2-searchbox
 */
export class Et2Searchbox extends Et2Textbox
{
	/** @type {any} */
	static get properties()
	{
		return {
			...super.properties,
			/**
			 * Define whether the searchbox overlays while it's open (true) or stay as solid box in front of the search button (false). Default is false.
			 * @todo implement again
			 */
			overlay: Boolean,
			/**
			 * Define whether the searchbox should be a fix input field or flexible search button. Default is true (fix).
			 * @todo implement again
			 */
			fix: Boolean,
		};
	}

	constructor(...args : any[])
	{
		super(...args);

		this.overlay = false;
		this.fix = true;

		this.clearable = true;
		this.type = 'search';
		this.placeholder = 'search';
		this.enterkeyhint = 'search';
	}

	/**
	 * Overwritten to trigger a change/search
	 *
	 * @param event
	 */
	handleKeyDown(event: KeyboardEvent)
	{
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
	}

	/**
	 * Overwritten to trigger a change/search
	 *
	 * @param event
	 */
	handleClearClick(event : MouseEvent)
	{
		event.preventDefault();

		this.value = '';
		this._oldChange(event);

		// Call super so it works as expected
		super.handleClearClick(event);
	}
}
// @ts-ignore TypeScript is not recognizing that this is a LitElement
customElements.define("et2-searchbox", Et2Searchbox);