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

export class Et2VfsName extends Et2Textbox
{
	constructor()
	{
		super();

		this.validator = /^[^\/\\]+$/;
	}

	set value(_value)
	{
		if(_value.path)
		{
			_value = _value.path;
		}
		try
		{
			_value = egw.decodePath(_value);
		} catch (e)
		{
			_value = 'Error! ' + _value;
		}
		super.value = _value;
	}

	get value()
	{
		return egw.encodePath(super.value || '');
	}
}
// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-vfs-name", Et2VfsName);

export class Et2VfsNameReadonly extends Et2Description
{
	set value(_value)
	{
		if(_value.path)
		{
			_value = _value.path;
		}
		try
		{
			_value = egw.decodePath(_value);
		} catch (e)
		{
			_value = 'Error! ' + _value;
		}
		super.value = _value;
	}

	get value()
	{
		return egw.encodePath(super.value || '');
	}
}
// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-vfs-name_ro", Et2VfsNameReadonly);