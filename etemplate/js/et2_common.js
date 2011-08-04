/**
 * eGroupWare eTemplate2 - JS Widget base class
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 */

function et2_debug(_level, _msg)
{
	if (typeof console != "undefined")
	{
		if (_level == "log" && typeof console.log == "function")
		{
			console.log(_msg);
		}

		if (_level == "warn" && typeof console.warn == "function")
		{
			console.warn(_msg);
		}

		if (_level == "error" && typeof console.error == "function")
		{
			console.error(_msg);
		}
	}
}

