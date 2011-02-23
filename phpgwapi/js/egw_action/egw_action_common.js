/**
 * eGroupWare egw_action framework - egw action framework
 *
 * @link http://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 * @version $Id:$
 */

/**
 * Sets properties given in _data in _obj. Checks whether the property keys
 * exists and if corresponding setter functions are available. Properties starting
 * with "_" are ignored.
 *
 * @param object _data may be an object with data that will be stored inside the
 * 	given object.
 * @param object _obj is the object where the data will be stored.
 */
function egwActionStoreJSON(_data, _obj, _setterOnly)
{
	for (key in _data)
	{
		if (key.charAt(0) != '_')
		{
			//Check whether there is a setter function available
			if (typeof _obj['set_' + key] == "function")
			{
				_obj['set_' + key](_data[key]);
			}
			else if (typeof _obj[key] != "undefined" && !_setterOnly)
			{
				_obj[key] = _data[key];
			}
		}
	}
}

