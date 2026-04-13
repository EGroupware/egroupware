/**
 * EGroupware eTemplate2 - Readonly select WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 */

import {Et2Textbox} from "../Et2Textbox/Et2Textbox";
import {Et2Description} from "../Et2Description/Et2Description";
import {egw} from "../../jsapi/egw_global";

/**
 * decodes the value on initially receiving it and encodes the value before submitting it
 * Client side always has a decoded value and server side always sends and receives an encoded value
 */
export class Et2VfsName extends Et2Textbox
{
	constructor()
	{
		super();

		this.validator = /^[^\/\\]+$/;
		this.updateComplete.then((v) =>
		{
			//decode value only once when receiving it initially
			if(this.value) this.value = egw.decodePath(this.value);
		})
	}

	set value(_value)
	{
		if (_value.path)
		{
			_value = _value.path;
		}
		super.value = _value;
	}

	get value()
	{
		return super.value
	}

	submit(_value)
	{
		if (_value?.name)
		{
			//encode value once before submitting it
			_value.name = egw.encodePath(_value.name);
		}
		super.submit(_value);
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-vfs-name", Et2VfsName);

export class Et2VfsNameReadonly extends Et2Description
{
	constructor()
	{
		super();
		this.updateComplete.then((v) =>
		{
			//decode value only once when receiving it initially
			this.value = egw.decodePath(this.value);
		})
	}

	set value(_value)
	{
		if (_value.path)
		{
			_value = _value.path;
		}
		super.value = _value;
	}

	get value()
	{
		return super.value;
	}
}
// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-vfs-name_ro", Et2VfsNameReadonly);