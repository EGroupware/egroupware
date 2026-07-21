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

/**
 * @summary VFS file name, decoded from VFS path encoding.
 *
 * Shows the basename from row-bound data rather than the full path
 *
 * decodes the value on initially receiving it and encodes the value before submitting it
 * Client side always has a decoded value and server side always sends and receives an encoded value
 */
export class Et2VfsName extends Et2Textbox
{
	constructor()
	{
		super();
		this.validator = /^[^\/\\]+$/;
		this.updateComplete.then((_v) =>
		{
			//decode value only once when receiving it initially
			if(this.value) this.value = egw.decodePath(this.value);
		});
	}

	/**
	 * Row-bound values arrive as an object with `.path` (full path) and
	 * `.name` (basename).  Extract and decode the basename so the Name
	 * column shows `1`, `Generated`, etc. instead of the full path.
	 *
	 * @param _value Raw value, usually a row object `{path, name}`.
	 */
	set value(_value)
	{
		if(_value && typeof _value === 'object')
		{
			if(typeof _value.name === 'string' && _value.name.length)
			{
				_value = _value.name;
			}
			else if(typeof _value.path === 'string' && _value.path.length)
			{
				const segments = _value.path.split('/');
				_value = segments[segments.length - 1] || _value.path;
			}
		}
		super.value = _value;
	}

	get value()
	{
		return super.value;
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
 */
export class Et2VfsNameReadonly extends Et2Description
{
	constructor()
	{
		super();
		this.updateComplete.then((v) =>
		{
			this.value = egw.decodePath(this.value);
		});
	}

	/**
	 * Row-bound values arrive as an object with `.path` (full path) and
	 * `.name` (basename).  Extract and decode the basename.
	 *
	 * @param _value Raw value, usually a row object `{path, name}`.
	 */
	set value(_value)
	{
		if(_value && typeof _value === 'object')
		{
			if(typeof _value.name === 'string' && _value.name.length)
			{
				_value = _value.name;
			}
			else if(typeof _value.path === 'string' && _value.path.length)
			{
				const segments = _value.path.split('/');
				_value = segments[segments.length - 1] || _value.path;
			}
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
