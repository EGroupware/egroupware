/**
 * EGroupware eTemplate2 - VfsFileMixin
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {LitElement} from "lit";
import {egw} from "../../jsapi/egw_global";
import {DIR_MIME_TYPE} from "./VfsMime";

/**
 * The part of a VFS row this mixin cares about.
 *
 * Row data carries a lot more (mtime, size, uid, permissions, ...) and the whole object is what
 * gets assigned as the value, these are just the keys that are read back out of it.  `mime` is
 * typed loosely because the server sends boolean false when it could not determine the type.
 */
export interface VfsFileInfo
{
	path? : string;
	name? : string;
	mime? : string | boolean;
	is_dir? : boolean | number;
}

export declare class VfsFileInterface
{
	set value(_value : any)

	get value() : any

	open() : boolean
}

type Constructor<T = {}> = new (...args : any[]) => T;

/**
 * What the mixin needs from whatever widget it is applied to: somewhere to put the displayed value,
 * and the egw instance.  Naming them here instead of the bare LitElement every other mixin uses
 * keeps `super.value` and `this.egw()` below type-checked.
 */
type VfsFileBase = LitElement & { get value() : any; set value(_value : any); egw : () => any };

/**
 * Common code for the editable & read-only VFS file name widgets
 * Moved out so we don't have copy+paste code in both
 *
 * Both are bound to a whole VFS row (`id="$row"`), show only the basename, and open the file on
 * click.  Keeping that in one place is not cosmetic: the two copies this replaces drifted apart
 * once already, and a fix applied to only one of them looked like it had simply not worked.
 */
export const VfsFileMixin = <T extends Constructor<VfsFileBase>>(superclass : T) =>
{
	class VfsFile extends superclass
	{
		/**
		 * The row object the value came from.
		 *
		 * Kept because the displayed value is reduced to the basename, while open() still needs
		 * the full path and the mime-type.
		 */
		protected fileInfo : VfsFileInfo | null = null;

		constructor(...args : any[])
		{
			super(...args);
			this.updateComplete.then(() =>
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
				this.fileInfo = _value;
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
			else if(_value == null)
			{
				this.fileInfo = null;
			}
			super.value = _value;
		}

		get value()
		{
			return super.value;
		}

		/**
		 * Open the row-bound VFS file using the standard file handler.
		 */
		open() : boolean
		{
			if(!this.fileInfo?.path)
			{
				return false;
			}
			// the server sends mime === false when it could not resolve the file at all, eg. a symlink
			// pointing at a deleted target.  Opening it would just hand the browser a webdav url that
			// 404s into a blank tab, so tell the user instead.
			if(typeof this.fileInfo.mime !== "string")
			{
				this.egw().message(this.egw().lang("File '%1' not found!", this.fileInfo.name || this.fileInfo.path), "error");
				return false;
			}
			// not every directory reports Vfs::DIR_MIME_TYPE: a symlink to one keeps its target's mime,
			// and EPL's links stream-wrapper gives the /apps/<app>/... directories "egw/<app>".  The
			// link-registry only has filemanager registered for DIR_MIME_TYPE, so without this the
			// directory finds no handler and falls back to its WebDAV url, opening the listing in a new
			// browser tab instead of in filemanager.  The server flags every one of them with is_dir.
			const mime = this.fileInfo.is_dir ? DIR_MIME_TYPE : this.fileInfo.mime;
			this.egw().open({path: this.fileInfo.path, type: mime}, "file");
			return false;
		}
	}

	return VfsFile as unknown as Constructor<VfsFileInterface> & T;
}
