/**
 * EGroupware eTemplate2 - Readonly select WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 */

import {Et2SelectAccountReadonly} from "../Et2Select/Et2SelectReadonly";

export class Et2VfsUid extends Et2SelectAccountReadonly
{
	set value(_val)
	{
		if (!_val || _val === '0')
		{
			this.select_options = [{value: '0', label: 'root'}];
		}
		super.value = _val;
	}

	get value()
	{
		return super.value;
	}
}
// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-vfs-uid", Et2VfsUid);

export class Et2VfsGid extends Et2VfsUid {}
// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-vfs-gid", Et2VfsGid);