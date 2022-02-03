/**
 * EGroupware - Filemanager - Javascript UI
 *
 * @link https://www.egroupware.org
 * @package filemanager
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-21 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

import {filemanagerAPP} from "./filemanager";

/**
 * This is the app.ts endpoint, code is in ./filemanager.ts to ensure proper loading/cache-invalidation for Collabora extending filemanagerAPP!
 */
if(typeof app.classes.filemanager === "undefined")
{
	app.classes.filemanager = filemanagerAPP;
}
