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

/**
 * Switches the given bit in the set on or off.
 *
 * @param int _set is the current set
 * @param int _bit is the position of the bit which should be switched on/off
 * @param boolean _state is whether the bit should be switched on or off
 * @returns the new set
 */
function egwSetBit(_set, _bit, _state)
{
	if (_state)
		return _set |= _bit;
	else
		return _set &= ~_bit;
}

/**
 * Returns whether the given bit is set in the set.
 */
function egwBitIsSet(_set, _bit)
{
	return (_set & _bit) > 0;
}

