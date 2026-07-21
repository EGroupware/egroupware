/**
 * EGroupware eTemplate2 - VfsMode WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 */

import {Et2Description} from "../Et2Description/Et2Description";
import {html} from "lit";
import {customElement} from "lit/decorators/custom-element.js";

/**
 * @summary Unix permission string renderer.
 *
 * Migrated from classic et2_vfsMode (et2_widget_vfs.ts).  No dependency on
 * any legacy et2_vfs* class.
 */
@customElement('et2-vfs-mode')
export class Et2VfsMode extends Et2Description
{
	/** File-type masks matching the S_IFMT bits. */
	static readonly types : Record<string, number> = {
		'l': 0xA000, // link
		's': 0xC000, // Socket
		'p': 0x1000, // FIFO pipe
		'c': 0x2000, // Character special
		'd': 0x4000, // Directory
		'b': 0x6000, // Block special
		'-': 0x8000  // Regular
	};

	/** Read / write / execute bit masks. */
	static readonly perms : Record<string, number> = {
		'x': 0x1, // Execute
		'w': 0x2, // Write
		'r': 0x4  // Read
	};

	/**
	 * Sticky / set-UID / set-GID overrides.
	 *
	 * Applied against the full mode after the base permission bits have
	 * been shifted into the owner / group / world positions.
	 */
	static readonly sticky : {mask : number, char : string, position : number}[] = [
		{mask: 0x200, char: "T", position: 9}, // Sticky
		{mask: 0x400, char: "S", position: 6}, // sGID
		{mask: 0x800, char: "S", position: 3}  // SUID
	];

	/**
	 * Convert a numeric mode into a `d---rwx---` style permission string.
	 *
	 * Ported from et2_vfsMode::text_mode() with identical sticky-bit handling.
	 *
	 * @param _value Numeric mode, or a row object that carries `.mode`.
	 * @returns Permission string such as `d---rwx---`, or an empty string for unknown / empty values.
	 */
	static formatMode(_value : number | string | undefined | null) : string
	{
		if(_value === undefined || _value === null || _value === '')
		{
			return '';
		}
		if(typeof _value === 'object' && _value !== null && typeof _value.mode !== 'undefined')
		{
			_value = _value.mode;
		}
		let mode = typeof _value === 'number' ? _value : parseInt(String(_value), 10);
		if(Number.isNaN(mode))
		{
			return '';
		}

		// Figure out type
		let type = '-';
		for(const [flag, mask] of Object.entries(Et2VfsMode.types))
		{
			if((mode & mask) === mask)
			{
				type = flag;
				break;
			}
		}

		// World, group, user – build string backwards
		const text : string[] = [];
		for(let i = 0; i < 3; i++)
		{
			const shifted = mode >> (i * 3);
			if(shifted & Et2VfsMode.perms['x'])
			{
				text.unshift('x');
			}
			else
			{
				text.unshift('-');
			}
			if(shifted & Et2VfsMode.perms['w'])
			{
				text.unshift('w');
			}
			else
			{
				text.unshift('-');
			}
			if(shifted & Et2VfsMode.perms['r'])
			{
				text.unshift('r');
			}
			else
			{
				text.unshift('-');
			}
		}

		// Sticky / UID / GID
		for(const entry of Et2VfsMode.sticky)
		{
			if(mode & entry.mask)
			{
				const current = text[entry.position];
				text[entry.position] = entry.char;
			}
		}

		return type + text.join('');
	}

	/**
	 * Numeric mode value, or a row object that carries `.mode`.
	 */
	set value(_value)
	{
		if(_value && typeof _value === 'object' && typeof _value.mode !== 'undefined')
		{
			_value = _value.mode;
		}
		super.value = _value;
	}

	get value()
	{
		return super.value;
	}

	// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
	render()
	{
		const text = Et2VfsMode.formatMode(this.value);
		if(!text)
		{
			return html``;
		}
		return html`<span title="${text}">${text}</span>`;
	}
}
