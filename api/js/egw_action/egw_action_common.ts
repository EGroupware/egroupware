/**
 * eGroupWare egw_action framework - egw action framework
 *
 * @link http://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 */

import {EGW_AO_SHIFT_STATE_BLOCK, EGW_AO_SHIFT_STATE_MULTI, EGW_AO_SHIFT_STATE_NONE} from "./egw_action_constants";
// this import breaks shoelace with ("Illegal constructor (custom element class must be registered with global customElements registry to be newable)"); in SlAvatar
//et2 also has this import without problems
//import {egw} from "../jsapi/egw_global.js";
/**
 * Sets properties given in _data in _obj. Checks whether the property keys
 * exists and if corresponding setter functions are available. Properties starting
 * with "_" are ignored.
 *
 * @param  _data may be an object with data that will be stored inside the
 *    given object.
 * @param  _obj is the object where the data will be stored.
 * @param  _setterOnly false: store everything, true: only store when setter function exists, "data" store rest in data property
 */
export function egwActionStoreJSON(_data: any, _obj: any, _setterOnly: boolean | string): void {
    for (let key in _data) {
        if (key.charAt(0) != '_') {
            //Check whether there is a setter function available
            if (typeof _obj['set_' + key] == "function") {
                _obj['set_' + key](_data[key]);
            } else if (typeof _obj[key] != "undefined" && !_setterOnly) {
                _obj[key] = _data[key];
            } else if (_setterOnly === 'data') {
                if (typeof _data.data == 'undefined') _data.data = {};
                _data.data[key] = _data[key];
                _obj.set_data(_data.data);
            }
        }
    }
}

/**
 * Switches the given bit in the set on or off.
 * only iff _bit is exponent of 2; 2^^i means i_th bit from the right will be set
 * might switch all bits of _set, depending on _bit
 *
 * @param _set is the current set
 * @param  _bit is the position of the bit which should be switched on/off
 * @param  _state is whether the bit should be switched on or off
 * @returns the new set
 */
export function egwSetBit(_set: number, _bit: number, _state: boolean) {
    if (_state)
        return _set | _bit;
    else
        return _set & ~_bit;
}

/**
 * Returns whether the given bit is set in the set.
 */
export function egwBitIsSet(_set: number, _bit: number) {
    return (_set & _bit) > 0;
}

export function egwObjectLength(_obj) {
    let len = 0;
    for (let k in _obj) len++;
    return len;
}


/**
 * Isolates the shift state from an event object
 */
export function egwGetShiftState(e) {
    let state = EGW_AO_SHIFT_STATE_NONE;
    state = egwSetBit(state, EGW_AO_SHIFT_STATE_MULTI, e.ctrlKey || e.metaKey);
    state = egwSetBit(state, EGW_AO_SHIFT_STATE_BLOCK, e.shiftKey);

    return state;
}

export function egwPreventSelect(e) {
    if (egwGetShiftState(e) > EGW_AO_SHIFT_STATE_NONE) {
        this.onselectstart = function () {
            return false;
        }

        return false;
    }

    return true;
}

export function egwUnfocus() {
    //TODO
    if (document.activeElement) {
        try {
            (document.activeElement as HTMLElement).blur();
        } catch (e) {
            //console.log("Can't be unfocused because active element isn't an HTMLElement")
        }

    }
}

// function never used
// export function egwCallAbstract(_obj: any, _fn: { apply: (arg0: any, arg1: any) => any; }, _args: any) {
//     if (_fn) {
//         return _fn.apply(_obj, _args);
//     } else {
//         throw "egw_action Exception: Abstract function call in JS code.";
//     }
// }

//never used
// export function egwArraysEqual(_ar1, _ar2) {
//     var result = _ar1.length == _ar2.length;
//
//     for (var i = 0; i < _ar1.length; i++) {
//         result = result && (_ar1[i] == _ar2[i])
//     }
//
//     return result;
// }

let _egwQueuedCallbacks = {};

export function egwQueueCallback(_proc: { apply: (arg0: any, arg1: any) => void; }, _args: any, _context: any, _id: string | number) {
    if (_proc) {
        let cur_id = 0;
        if (typeof _egwQueuedCallbacks[_id] == "undefined") {
            cur_id = _egwQueuedCallbacks[_id] = 1;
        } else {
            cur_id = ++_egwQueuedCallbacks[_id];
        }

        window.setTimeout(function () {
            if (_egwQueuedCallbacks[_id] == cur_id) {
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

//egwEventQueue is never used
//
// /**
//  * Constructor for the egwEventQueue class. Initializes the queue object and the
//  * internal data structures such as the internal key.
//  */
// export function egwEventQueue() {
//     this.events = {};
//     this.key_id = 0;
// }
//
// /**
//  * Flushes all queued events - all events which had been queued in this queue objects
//  * can no longer be ran by calling egwEventQueue.run
//  */
// egwEventQueue.prototype.flush = function () {
//     this.events = {};
// }
//
// /**
//  * Queues the given function. A key is returned which can be passed to the "run"
//  * function which will then run the passed function.
//  *
//  * @param function _proc is the funciton which should be called
//  * @param object _context is the context in which the function should be called
//  * @param array _args is an optional array of parameters which should be passed
//  *    to the function. Defaults to an emtpy array.
//  * @param string _id is an optional id which can be used to identify the event (may
//  *    also be a key returned by this funciton). If the queue function is called multiple
//  *    times for a given _id, the so called "call counter" of the event will be incremented.
//  *    Each time "run" is called for a given event, the "call counter" will be decremented.
//  *    Only if it reaches 0, the function connected to the event will be executed.
//  *
//  * @returns string the key which has to be passed to the "run" function.
//  */
// egwEventQueue.prototype.queue = function (_proc, _context, _args, _id) {
//     // Default _args to an empty array
//     if (typeof _args == "undefined" || !(_args instanceof Array)) {
//         _args = [];
//     }
//
//     // Increment the queue key which is used to store the event objectes with
//     // a unique key
//     this.key_id++;
//
//     var key = "";
//
//     // _id must be a string and evaluate to true - if this is not
//     // generate an unique key.
//     if (typeof _id != "string" || !_id) {
//         key = "ev_" + this.key_id;
//     } else {
//         key = _id;
//     }
//
//     // Check whether an queued event with the given id already exists
//     var cnt = 1;
//     if (typeof this.events[key] != "undefined") {
//         // The specified event already existed - increment the call counter.
//         cnt = this.events[key].cnt + 1;
//     }
//
//     // Generate the event object
//     this.events[key] = {
//         "proc": _proc,
//         "context": _context,
//         "args": _args,
//         "cnt": cnt
//     }
//
//     // Return the key which has to be passed to the "run" function in order to
//     // run the specified function
//     return key;
// }
//
// /**
//  * Runs the event specified by the given key. The key is the string which had been
//  * returned by the queue function.
//  */
// egwEventQueue.prototype.run = function (_key) {
//     // Check whether the given key exists
//     if (typeof this.events[_key] != "undefined") {
//         // Fetch the event object
//         var eventObj = this.events[_key];
//
//         // Decrement the call counter
//         eventObj.cnt--;
//
//         // Only run the event if the call counter has reached zero
//         if (eventObj.cnt == 0) {
//             // Run the event
//             eventObj.proc.apply(eventObj.context, eventObj.args);
//
//             // Delete the given key from the event set
//             delete this.events[_key];
//         }
//     }
// }
//
// /**
//  * Does the same as "queue" but runs the event after the given timeout.
//  */
// egwEventQueue.prototype.queueTimeout = function (_proc, _context, _args, _id, _timeout) {
//     // Create the queue entry
//     var key = this.queue(_proc, _context, _args, _id);
//
//     // Run the event in the setTimeout event.
//     var self = this;
//     window.setTimeout(function () {
//         self.run(key);
//     }, _timeout)
// }

/**
 * @deprecated use class EgwFnct instead
 */
/**
 * Class which is used to be able to handle references to JavaScript functions
 * from strings.
 *
 * @param object _context is the context in which the function will be executed.
 * @param mixed _default is the default value which should be returned when no
 *    function (string) has been set. If it is a function this function will be
 *    called.
 * @param array _acceptedTypes is an array of types which contains the "typeof"
 *    strings of accepted non-functions in setValue
 */
export class EgwFnct
{
    private readonly context: any;
    private readonly acceptedTypes: string[];
    functionToPerform;
    private value;
    // Flag for if this action is using a default handler
    // I don't think this is ever used @unused
    public isDefault;

    constructor(_context = null, _default: any = false, _acceptedTypes = ["boolean"]) {
        this.context = _context;
        this.acceptedTypes = _acceptedTypes;
        this.functionToPerform = null;
        this.value = null;
        this.isDefault = false
        this.setValue(_default)
    }

    /**
     * @returns true iff there is a function to perform and is not default
     */
    public hasHandler = (): boolean => this.functionToPerform !== null && !this.isDefault;

    /**
     * Sets the function/return value for the exec function
     * egw_action hints that value has to be on of 3:
     * 1. _value may be a string with the word "javaScript:" prefixed. The function
     *       which is specified behind the colon and which has to be in the global scope
     *       will be executed.
     *    2. _value may be a boolean, which specifies whether the external onExecute handler
     *       (passed as "_handler" in the constructor) will be used.
     *    3. _value may be a JS function which will then be called.
     *
     *    TODO check if there should be other options
     */
    public setValue(_value: any) {
        this.value = null;
        this.functionToPerform = null;

        // Actual function
        if (typeof _value == "function") {
            this.functionToPerform = _value;
        } else if (typeof _value == "string" && _value.substring(0, 11) === 'javaScript:') {
            this.functionToPerform = function (): any {
                const manager = this.context && this.context.getManager ? this.context.getManager() : null;
                return window.egw.applyFunc(_value.substring(11), arguments, (manager ? manager.data.context : null) || window);
            }
        } else if (this.acceptedTypes.includes(typeof _value)) {
            this.value = _value;
        }// Something, but could not figure it out
        else if (_value) {
            //egw.debug("warn", "Unable to parse Exec function %o for action '%s'", _value, this.context.id);
        }
    }

    /**
     * Executes the function
     */
    public exec() {
        if (this.functionToPerform) {
          return  this.functionToPerform.apply(this.context,arguments)
        } else {
            return this.value
        }
    }

}

/**
 * @deprecated use upperCase class instead
 */
export class  egwFnct extends EgwFnct {}

/**
 * Checks whether this is currently run on a mobile browser
 */
let _egw_mobileBrowser: boolean = null;

export function egwIsMobile(): boolean
{
    if (_egw_mobileBrowser == null) {
        let ua = navigator.userAgent;
        _egw_mobileBrowser =
            !!(ua.match(/iPhone/i) || ua.match(/iPad/i) || ua.match(/iPod/) ||
                ua.match(/Android/i) || ua.match(/SymbianOS/i));
    }

    return _egw_mobileBrowser;
}

window.egwIsMobile = egwIsMobile;


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
export function str_repeat(i, m) {
    for (var o = []; m > 0; o[--m] = i) ;
    return o.join('');
}

export function sprintf() {
    var i = 0, a, f = arguments[i++], o = [], m, p, c, x, s = '';
    while (f) {
        if (m = /^[^\x25]+/.exec(f)) {
            o.push(m[0]);
        } else if (m = /^\x25{2}/.exec(f)) {
            o.push('%');
        } else if (m = /^\x25(?:(\d+)\$)?(\+)?(0|'[^$])?(-)?(\d+)?(?:\.(\d+))?([b-fosuxX])/.exec(f)) {
            if (((a = arguments[m[1] || i++]) == null) || (a == undefined)) {
                throw('Too few arguments.');
            }
            if (/[^s]/.test(m[7]) && (typeof (a) != 'number')) {
                throw('Expecting number but found ' + typeof (a));
            }
            switch (m[7]) {
                case 'b':
                    a = a.toString(2);
                    break;
                case 'c':
                    a = String.fromCharCode(a);
                    break;
                case 'd':
                    a = parseInt(a);
                    break;
                case 'e':
                    a = m[6] ? a.toExponential(m[6]) : a.toExponential();
                    break;
                case 'f':
                    a = m[6] ? parseFloat(a).toFixed(m[6]) : parseFloat(a);
                    break;
                case 'o':
                    a = typeof (a) == 'number' ? a.toString(8) : JSON.stringify(a);
                    break;
                case 's':
                    a = ((a = String(a)) && m[6] ? a.substring(0, m[6]) : a);
                    break;
                case 'u':
                    a = Math.abs(a);
                    break;
                case 'x':
                    a = a.toString(16);
                    break;
                case 'X':
                    a = a.toString(16).toUpperCase();
                    break;
            }
            a = (/[def]/.test(m[7]) && m[2] && a >= 0 ? '+' + a : a);
            c = m[3] ? m[3] == '0' ? '0' : m[3].charAt(1) : ' ';
            x = m[5] - String(a).length - s.length;
            p = m[5] ? str_repeat(c, x) : '';
            o.push(s + (m[4] ? a + p : p + a));
        } else {
            throw('Invalid sprintf format "' + arguments[0] + '"');
        }
        f = f.substring(m[0].length);
    }
    return o.join('');
}