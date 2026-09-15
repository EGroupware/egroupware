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
	constructor()
	{
		super();
		this.noLang = true;	// value is a numeric fs_mode, never a translatable phrase
	}

	/** Mask selecting the file-type bits out of a mode, everything below it is permissions. */
	static readonly S_IFMT = 0xF000;

	/**
	 * File-type masks, to be compared against the S_IFMT bits as a whole.
	 *
	 * Several of these share bits (block special 0x6000 contains character special 0x2000, for
	 * example), so a plain `mode & mask` test matches the wrong type - mask with S_IFMT and compare
	 * for equality instead, which also makes the result independent of the order declared here.
	 */
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
	 * Each replaces the execute character of one permission triplet.  The positions are indices
	 * into the 9 character permission string only - the leading file-type character is prepended
	 * afterwards and must not be counted here.
	 */
	static readonly sticky : {mask : number, char : string, position : number}[] = [
		{mask: 0x200, char: "T", position: 8}, // Sticky, replaces world execute
		{mask: 0x400, char: "S", position: 5}, // sGID, replaces group execute
		{mask: 0x800, char: "S", position: 2}  // SUID, replaces owner execute
	];

	/**
	 * Convert a numeric mode into a `d---rwx---` style permission string.
	 *
	 * Matches what `ls -l` prints, and the server-side EGroupware\Api\Vfs::int2mode() for the same mode.
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
			if((mode & Et2VfsMode.S_IFMT) === mask)
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
				// ls convention: lowercase if the execute bit this replaces is set too, uppercase if not
				text[entry.position] = text[entry.position] === 'x' ? entry.char.toLowerCase() : entry.char;
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
