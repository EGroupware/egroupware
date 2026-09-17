/**
 * EGroupware eTemplate2 - Readonly password widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 */

import {Et2Description} from "../Et2Description/Et2Description";

/**
 * How much of a password we are prepared to say, which is only whether there is one.
 */
const MASK = "***";

/**
 * @summary Shows whether a password is set, without showing the password.
 *
 * A readonly password is not the editable widget with its input disabled: that one is given the
 * stored password so it can offer to reveal it, and somewhere it is only being displayed - a
 * nextmatch row, a print template - there is nothing to reveal it for.  This never holds the value
 * it was given, so the password does not travel with the page.
 */
export class Et2PasswordReadonly extends Et2Description
{
	constructor()
	{
		super();
		this.noLang = true;    // a password is never a translatable phrase
	}

	get value() : string
	{
		return super.value;
	}

	/**
	 * Keep only whether there was a password, never the password.
	 *
	 * Anything stored reads as the same mask, so handing the widget its own displayed value back -
	 * which a re-render does - leaves it saying exactly what it said before.
	 *
	 * @param {string} _value Stored value, which for a password customfield is its ciphertext.
	 */
	set value(_value : string)
	{
		super.value = _value ? MASK : "";
	}
}

// We can't bind the same class to a different tag
// @ts-ignore TypeScript is not recognizing that Et2Description is a LitElement
customElements.define("et2-password_ro", Et2PasswordReadonly);
