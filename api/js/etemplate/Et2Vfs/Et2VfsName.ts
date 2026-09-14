/**
 * EGroupware eTemplate2 - VfsName WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 */

import {Et2Textbox} from "../Et2Textbox/Et2Textbox";
import {Et2Description} from "../Et2Description/Et2Description";
import {egw} from "../../jsapi/egw_global";
import {VfsFileMixin} from "./VfsFileMixin";

/**
 * @summary VFS file name, decoded from VFS path encoding.
 *
 * Shows the basename from row-bound data rather than the full path
 *
 * decodes the value on initially receiving it and encodes the value before submitting it
 * Client side always has a decoded value and server side always sends and receives an encoded value
 *
 * Reading the row-bound value and opening the file live in VfsFileMixin, shared with the read-only
 * variant below.
 */
export class Et2VfsName extends VfsFileMixin(Et2Textbox)
{
	constructor()
	{
		super();
		this.validator = /^[^\/\\]+$/;
	}

	/**
	 * Encode the basename back to VFS path encoding before submitting.
	 *
	 * @param _value Value to submit.
	 */
	submit(_value)
	{
		if(_value && typeof _value === 'object' && _value.name)
		{
			_value.name = egw.encodePath(_value.name);
		}
		super.submit(_value);
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-vfs-name", Et2VfsName);

/**
 * @summary Read-only VFS file name.
 *
 * Everything it does comes from VfsFileMixin - the only difference to the editable widget is the
 * Et2Description base.
 */
export class Et2VfsNameReadonly extends VfsFileMixin(Et2Description)
{
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-vfs-name_ro", Et2VfsNameReadonly);
