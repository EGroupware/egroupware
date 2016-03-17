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
 * @param mixed _setterOnly false: store everything, true: only store when setter exists, "data" store rest in data property
 */
function egwActionStoreJSON(_data, _obj, _setterOnly)
{
	for (var key in _data)
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
			else if (_setterOnly === 'data')
			{
				if (typeof _data.data == 'undefined') _data.data = {};
				_data.data[key] = _data[key];
				_obj.set_data(_data.data);
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
	for (var k in _obj) len++;
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
	state = egwSetBit(state, EGW_AO_SHIFT_STATE_MULTI, e.ctrlKey || e.metaKey);
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

function egwUnfocus()
{
	if (document.activeElement)
	{
		document.activeElement.blur();
	}
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

function egwArraysEqual(_ar1, _ar2)
{
	var result = _ar1.length == _ar2.length;

	for (var i = 0; i < _ar1.length; i++)
	{
		result = result && (_ar1[i] == _ar2[i])
	}

	return result;
}

var _egwQueuedCallbacks = {};
function egwQueueCallback(_proc, _args, _context, _id)
{
	if (_proc)
	{
		var cur_id = 0;
		if (typeof _egwQueuedCallbacks[_id] == "undefined")
		{
			cur_id = _egwQueuedCallbacks[_id] = 1;
		}
		else
		{
			cur_id = ++_egwQueuedCallbacks[_id];
		}

		window.setTimeout(function() {
			if (_egwQueuedCallbacks[_id] == cur_id)
			{
				_proc.apply(_context, _args);
				delete _egwQueuedCallbacks[_id];
			}
		}, 0);
	}
}

/**
 * The eventQueue object is used to have control over certain events such as
 * ajax responses or timeouts. Sometimes it may happen, that a function attached
 * to such an event should no longer be called - with egwEventQueue one has
 * a simple possibility to control that.
 */

/**
 * Constructor for the egwEventQueue class. Initializes the queue object and the
 * internal data structures such as the internal key.
 */
function egwEventQueue()
{
	this.events = {};
	this.key_id = 0;
}

/**
 * Flushes all queued events - all events which had been queued in this queue objects
 * can no longer be ran by calling egwEventQueue.run
 */
egwEventQueue.prototype.flush = function()
{
	this.events = {};
}

/**
 * Queues the given function. A key is returned which can be passed to the "run"
 * function which will then run the passed function.
 *
 * @param function _proc is the funciton which should be called
 * @param object _context is the context in which the function should be called
 * @param array _args is an optional array of parameters which should be passed
 * 	to the function. Defaults to an emtpy array.
 * @param string _id is an optional id which can be used to identify the event (may
 * 	also be a key returned by this funciton). If the queue function is called multiple
 * 	times for a given _id, the so called "call counter" of the event will be incremented.
 * 	Each time "run" is called for a given event, the "call counter" will be decremented.
 * 	Only if it reaches 0, the function connected to the event will be executed.
 *
 * @returns string the key which has to be passed to the "run" function.
 */
egwEventQueue.prototype.queue = function(_proc, _context, _args, _id)
{
	// Default _args to an empty array
	if (typeof _args == "undefined" || !(_args instanceof Array))
	{
		_args = [];
	}

	// Increment the queue key which is used to store the event objectes with
	// a unique key
	this.key_id++;

	var key = "";

	// _id must be a string and evaluate to true - if this is not
	// generate an unique key.
	if (typeof _id != "string" || !_id)
	{
		key = "ev_" + this.key_id;
	}
	else
	{
		key = _id;
	}

	// Check whether an queued event with the given id already exists
	var cnt = 1;
	if (typeof this.events[key] != "undefined")
	{
		// The specified event already existed - increment the call counter.
		cnt = this.events[key].cnt + 1;
	}

	// Generate the event object
	this.events[key] = {
		"proc": _proc,
		"context": _context,
		"args": _args,
		"cnt": cnt
	}

	// Return the key which has to be passed to the "run" function in order to
	// run the specified function
	return key;
}

/**
 * Runs the event specified by the given key. The key is the string which had been
 * returned by the queue function.
 */
egwEventQueue.prototype.run = function(_key)
{
	// Check whether the given key exists
	if (typeof this.events[_key] != "undefined")
	{
		// Fetch the event object
		var eventObj = this.events[_key];

		// Decrement the call counter
		eventObj.cnt--;

		// Only run the event if the call counter has reached zero
		if (eventObj.cnt == 0)
		{
			// Run the event
			eventObj.proc.apply(eventObj.context, eventObj.args);

			// Delete the given key from the event set
			delete this.events[_key];
		}
	}
}

/**
 * Does the same as "queue" but runs the event after the given timeout.
 */
egwEventQueue.prototype.queueTimeout = function(_proc, _context, _args, _id, _timeout)
{
	// Create the queue entry
	var key = this.queue(_proc, _context, _args, _id);

	// Run the event in the setTimeout event.
	var self = this;
	window.setTimeout(function() {
		self.run(key);
	}, _timeout)
}


/**
 * Class which is used to be able to handle references to JavaScript functions
 * from strings.
 *
 * @param object _context is the context in which the function will be executed.
 * @param mixed _default is the default value which should be returned when no
 * 	function (string) has been set. If it is a function this function will be
 * 	called.
 * @param array _acceptedTypes is an array of types which contains the "typeof"
 * 	strings of accepted non-functions in setValue
 */
function egwFnct(_context, _default, _acceptedTypes)
{
	if (typeof _context == "undefined")
	{
		_context = null;
	}

	if (typeof _default == "undefined")
	{
		_default = false;
	}

	if (typeof _acceptedTypes == "undefined")
	{
		_acceptedTypes = ["boolean"];
	}

	this.context = _context;
	this.acceptedTypes = _acceptedTypes;
	this.fnct = null;
	this.value = null;
	// Flag for if this action is using a default handler
	this.isDefault = false;
	this.setValue(_default);
}

egwFnct.prototype.hasHandler = function()
{
	return this.fnct !== null && !this.isDefault;
}

/**
 * Sets the function/return value for the exec function
 */
egwFnct.prototype.setValue = function(_value)
{
	this.value = null;
	this.fnct = null;

	// Actual function
	if (typeof _value == "function")
	{
		this.fnct = _value;
	}

	// Global function (on window)
	else if (typeof _value == "string" &&
	         _value.substr(0,11) == "javaScript:" &&
	         typeof window[_value.substr(11)] == "function")
	{
		this.fnct = window[_value.substr(11)];
		console.log("Global function is bad!", _value);
	}
	else if (this.acceptedTypes.indexOf(typeof _value) >= 0)
	{
		this.value = _value;
	}

	// egw application specific function
	else if (typeof _value == "string" &&
	         _value.substr(0,15) == "javaScript:app." && app)
	{
		var parts = _value.split(".");
		if(parts.length == 3 && typeof app[parts[1]] == "object" &&
			typeof app[parts[1]][parts[2]] == "function")
		{
			this.fnct = app[parts[1]][parts[2]];
			this.context = app[parts[1]];
		}
	}

	// Something, but could not figure it out
	else if (_value)
	{
		egw.debug("warn", "Unable to parse Exec function %o for action '%s'",_value, this.context.id);
	}
}

/**
 * Executes the function
 */
egwFnct.prototype.exec = function()
{
	if (this.fnct)
	{
		return this.fnct.apply(this.context, arguments);
	}
	else
	{
		return this.value;
	}
}

/**
 * Checks whether this is currently run on a mobile browser
 */
var _egw_mobileBrowser = null;

function egwIsMobile() {

	if (_egw_mobileBrowser == null)
	{
		var ua = navigator.userAgent;

		_egw_mobileBrowser =
			ua.match(/iPhone/i) || ua.match(/iPad/i) || ua.match(/iPod/) ||
			ua.match(/Android/i) || ua.match(/SymbianOS/i);
	}

	return _egw_mobileBrowser;
}


/**
sprintf() for JavaScript 0.6

Copyright (c) Alexandru Marasteanu <alexaholic [at) gmail (dot] com>
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of sprintf() for JavaScript nor the
      names of its contributors may be used to endorse or promote products
      derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL Alexandru Marasteanu BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.


Changelog:
2007.04.03 - 0.1:
 - initial release
2007.09.11 - 0.2:
 - feature: added argument swapping
2007.09.17 - 0.3:
 - bug fix: no longer throws exception on empty paramenters (Hans Pufal)
2007.10.21 - 0.4:
 - unit test and patch (David Baird)
2010.05.09 - 0.5:
 - bug fix: 0 is now preceeded with a + sign
 - bug fix: the sign was not at the right position on padded results (Kamal Abdali)
 - switched from GPL to BSD license
2010.05.22 - 0.6:
 - reverted to 0.4 and fixed the bug regarding the sign of the number 0
 Note:
 Thanks to Raphael Pigulla <raph (at] n3rd [dot) org> (http://www.n3rd.org/)
 who warned me about a bug in 0.5, I discovered that the last update was
 a regress. I appologize for that.
**/

function str_repeat(i, m) {
	for (var o = []; m > 0; o[--m] = i);
	return o.join('');
}

function sprintf() {
	var i = 0, a, f = arguments[i++], o = [], m, p, c, x, s = '';
	while (f) {
		if (m = /^[^\x25]+/.exec(f)) {
			o.push(m[0]);
		}
		else if (m = /^\x25{2}/.exec(f)) {
			o.push('%');
		}
		else if (m = /^\x25(?:(\d+)\$)?(\+)?(0|'[^$])?(-)?(\d+)?(?:\.(\d+))?([b-fosuxX])/.exec(f)) {
			if (((a = arguments[m[1] || i++]) == null) || (a == undefined)) {
				throw('Too few arguments.');
			}
			if (/[^s]/.test(m[7]) && (typeof(a) != 'number')) {
				throw('Expecting number but found ' + typeof(a));
			}
			switch (m[7]) {
				case 'b': a = a.toString(2); break;
				case 'c': a = String.fromCharCode(a); break;
				case 'd': a = parseInt(a); break;
				case 'e': a = m[6] ? a.toExponential(m[6]) : a.toExponential(); break;
				case 'f': a = m[6] ? parseFloat(a).toFixed(m[6]) : parseFloat(a); break;
				case 'o': a = typeof(a) == 'number' ? a.toString(8):JSON.stringify(a); break;
				case 's': a = ((a = String(a)) && m[6] ? a.substring(0, m[6]) : a); break;
				case 'u': a = Math.abs(a); break;
				case 'x': a = a.toString(16); break;
				case 'X': a = a.toString(16).toUpperCase(); break;
			}
			a = (/[def]/.test(m[7]) && m[2] && a >= 0 ? '+'+ a : a);
			c = m[3] ? m[3] == '0' ? '0' : m[3].charAt(1) : ' ';
			x = m[5] - String(a).length - s.length;
			p = m[5] ? str_repeat(c, x) : '';
			o.push(s + (m[4] ? a + p : p + a));
		}
		else {
			throw('Invalid sprintf format "' + arguments[0]+'"');
		}
		f = f.substring(m[0].length);
	}
	return o.join('');
}

