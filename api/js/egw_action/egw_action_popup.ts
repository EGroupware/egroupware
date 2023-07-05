/**
 * eGroupWare egw_action framework - egw action framework
 *
 * @link http://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 * @version $Id$
 */


import {egwPopupAction} from "./EgwPopupAction";
import {egwPopupActionImplementation} from "./EgwPopupActionImplementation";

if (typeof window._egwActionClasses == "undefined")
	window._egwActionClasses = {};
_egwActionClasses["popup"] = {
	"actionConstructor": egwPopupAction,
	"implementation": getPopupImplementation
};

var
	_popupActionImpl = null;

export function getPopupImplementation()
{
	if (!_popupActionImpl)
	{
		_popupActionImpl = new egwPopupActionImplementation();
	}
	return _popupActionImpl;
}

