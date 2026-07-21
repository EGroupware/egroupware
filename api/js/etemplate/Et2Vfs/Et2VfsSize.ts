/**
 * EGroupware eTemplate2 - VfsSize WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 */

import {SlFormatBytes} from "@shoelace-style/shoelace";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {nothing} from "lit";

/**
 * @summary Human-readable file size.
 *
 * Extends Shoelace <code>&lt;sl-format-bytes&gt;</code> and unwraps the
 * legacy row-bound value shape <code>{ size, mode, path }</code> before
 * passing the numeric size upstream.
 *
 *
 * @dependency sl-format-bytes, @shoelace-style/shoelace
 */
export class Et2VfsSize extends Et2Widget(SlFormatBytes as any)
{
	private _hasValue = false;

	private _normalizeSize(_value) : number | null
	{
		if(_value && typeof _value === 'object' && typeof _value.size !== 'undefined')
		{
			_value = _value.size;
		}
		if(_value === undefined || _value === null || _value === '' || _value === false)
		{
			return null;
		}
		const size = typeof _value === 'number' ? _value : parseInt(String(_value), 10);
		return Number.isNaN(size) ? null : size;
	}

	set_value(_value)
	{
		this.value = _value;
	}

	/**
	 * Row data from the VFS backend.  Unlike Shoelace's strict
	 * <code>number</code> type, this widget also accepts the legacy row
	 * object <code>{ size, mode, path }</code> and unwraps <code>.size</code>
	 * before setting the underlying numeric <code>value</code>.
	 */
	set value(_value)
	{
		const oldValue = super.value;
		const size = this._normalizeSize(_value);
		this._hasValue = size !== null;
		if(this._hasValue)
		{
			super.value = size;
		}
		this.requestUpdate("value", oldValue);
	}

	get value()
	{
		return super.value;
	}

	render()
	{
		return this._hasValue ? super.render() : nothing;
	}
}
// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-vfs-size", Et2VfsSize);
