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

function egwObjectLength(_obj)
{
	var len = 0;
	for (k in _obj) len++;
	return len;
}

/**
 * IE Fix for array.indexOf
 */
if (typeof Array.prototype.indexOf == "undefined")
{
	Array.prototype.indexOf = function(_elem) {
		for (var i = 0; i < this.length; i++)
		{
			if (this[i] === _elem)
				return i;
		}
		return -1;
	};
}

/**
 * Isolates the shift state from an event object
 */
function egwGetShiftState(e)
{
	var state = EGW_AO_SHIFT_STATE_NONE;
	state = egwSetBit(state, EGW_AO_SHIFT_STATE_MULTI, e.ctrkKey || e.metaKey);
	state = egwSetBit(state, EGW_AO_SHIFT_STATE_BLOCK, e.shiftKey);

	return state;
}

function egwPreventSelect(e)
{
	if (egwGetShiftState(e) > EGW_AO_SHIFT_STATE_NONE)
	{
		this.onselectstart = function() {
			return false;
		}

		return false;
	}

	return true;
}

function egwResetPreventSelect(elem)
{
}


function egwCallAbstract(_obj, _fn, _args)
{
	if (_fn)
	{
		return _fn.apply(_obj, _args);
	}
	else
	{
		throw "egw_action Exception: Abstract function call in JS code.";
	}

	return false;
}


