/**
 * EGroupware egw_action framework - Shortcut/Keyboard input manager
 *
 * @link https://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 */

/*egw:uses
	vendor.bower-asset.jquery.dist.jquery;
	egw_action;
*/

import {egw_getAppObjectManager, egw_globalObjectManager} from "./egw_action";
import {_egw_active_menu} from "./egw_menu";
import {
	EGW_AO_FLAG_DEFAULT_FOCUS,
	EGW_AO_EXEC_SELECTED,
	EGW_VALID_KEYS,
	EGW_KEY_MENU,
	EGW_KEY_F1, EGW_KEY_F12
} from "./egw_action_constants";
import {egwBitIsSet} from "./egw_action_common";
import type {EgwActionObject} from "./EgwActionObject";
import type {EgwActionObjectManager} from "./EgwActionObjectManager";

/**
 * The translation function converts the given native key code into one of the
 * egw key constants as listed above. This key codes were chosen to match the
 * key codes of IE and FF.
 */
export var egw_keycode_translation_function = function (_nativeKeyCode) {
	// Map the numpad to the 0..9 keys
	if (_nativeKeyCode >= 96 && _nativeKeyCode <= 105)
	{
		_nativeKeyCode -= 48
	}

	return _nativeKeyCode;
}

/**
 * Checks whether the given keycode is in the list of valid key codes. If not,
 * returns -1.
 */
export function egw_keycode_makeValid(_keyCode)
{
	const idx = EGW_VALID_KEYS.indexOf(_keyCode);
	if (idx >= 0)
	{
		return _keyCode;
	}

	return -1;
}

function _egw_nodeIsInInput(_node)
{
	if ((_node != null) && (_node != document))
	{
		const tagName = _node.tagName.toLowerCase();
		if (tagName == "input" || tagName == "select" || tagName == 'textarea' || tagName == 'button' ||
			['et2-textbox', 'et2-number', 'et2-searchbox', 'et2-select', 'et2-textarea', 'et2-button'].indexOf(tagName) != -1)
		{
			return true;
		} else
		{
			return _egw_nodeIsInInput(_node.parentNode);
		}
	} else
	{
		return false;
	}
}

/**
 * execute
 * @param fn after DOM is ready
 * replacement for jQuery.ready()
 */
function ready(fn)
{
	if (document.readyState !== 'loading')
	{
		fn();
	} else
	{
		document.addEventListener('DOMContentLoaded', fn);
	}
}

/**
 * Register the onkeypress handler on the document
 */
ready(() => {//waits for DOM ready

	// Fetch the key down event and translate it into browser-independent and
	// easy to use key codes and shift states
	document.addEventListener("keydown", (keyboardEvent: KeyboardEvent) => {

		// Translate the given key code and make it valid
		// TODO there are actual string key codes now so it would be nice to use those standardized ones instead of using our own
		let keyCode = keyboardEvent.keyCode;
		keyCode = egw_keycode_translation_function(keyCode);
		keyCode = egw_keycode_makeValid(keyCode);

		// Only go on if this is a valid key code - call the key handler
		if (keyCode != -1)
		{
			// Check whether the event came from the sidebox - if yes, ignore
			//if(jQuery(keyboardEvent.target).parents("#egw_fw_sidemenu").length > 0) return;
			let target: any = keyboardEvent.target // this is some kind of element
			while ((target = target.parentNode) && target !== document)
			{
				if (!"#egw_fw_sidemenu" || target.matches("#egw_fw_sidemenu")) return;
			}

			// If a context menu is open, give the keyboard to it
			if (typeof _egw_active_menu !== undefined && _egw_active_menu &&
				_egw_active_menu.keyHandler(keyCode, keyboardEvent.shiftKey, keyboardEvent.ctrlKey || keyboardEvent.metaKey, keyboardEvent.altKey))
			{
				keyboardEvent.preventDefault();
				return;
			}
			// Check whether the event came from an input field - if yes, only
			// allow function keys (like F1) to be captured by our code
			const inInput = _egw_nodeIsInInput(keyboardEvent.target);
			if (!inInput || (keyCode >= EGW_KEY_F1 && keyCode <= EGW_KEY_F12))
			{
				if (egw_keyHandler(keyCode, keyboardEvent.shiftKey, keyboardEvent.ctrlKey || keyboardEvent.metaKey, keyboardEvent.altKey))
				{
					// If the key handler successfully passed the key event to some
					// subcomponent, prevent the default action
					keyboardEvent.preventDefault();
				}
			}
		}
	});
});
/**
 * Required to catch the context menu
 */
jQuery(window).on("contextmenu",document, function(event) {
	// Check for actual key press
	if (!(event.originalEvent.x == 1 && event.originalEvent.y == 1)) return true;
	if (!event.ctrlKey && egw_keyHandler(EGW_KEY_MENU, event.shiftKey, event.ctrlKey || event.metaKey, event.altKey))
	{
		// If the key handler successfully passed the key event to some
		// subcomponent, prevent the default action
		event.preventDefault();
		return false;
	}
	return true;
})


/**
 * Creates a unique key for the given shortcut
 * TODO those ids exist already
 */
export function egw_shortcutIdx(_keyCode: number, _shift: boolean, _ctrl: boolean, _alt: boolean):string
{
	return "_" + _keyCode + "_" +
		(_shift ? "S" : "") +
		(_ctrl ? "C" : "") +
		(_alt ? "A" : "");
}
type Shortcut= {"handler":()=> boolean,
	"context": any,
	"shortcut":{
		"keyCode": number,
		"shift": boolean,
		"ctrl": boolean,
		"alt":boolean
	}}

export var egw_registeredShortcuts = {}

/**
 * Registers a global shortcut. If the shortcut already exists, it is overwritten.
 * @param {int} _keyCode is one of the keycode constants
 * @param {bool} _shift whether shift has to be set
 * @param {bool} _ctrl whether ctrl has to be set
 * @param {bool} _alt whether alt has to be set
 * @param {function} _handler the function which will be called when the shortcut
 * 	is evoked. An object containing the shortcut data will be passed as first
 * 	parameter.
 * @param {any} _context is the context in which the function will be executed
 */
export function egw_registerGlobalShortcut(_keyCode: number, _shift: boolean, _ctrl: boolean, _alt: boolean, _handler: () => boolean, _context: any)
{
	// Generate the hash map index for the shortcut
	const idx = egw_shortcutIdx(_keyCode, _shift, _ctrl, _alt);

	// Register the shortcut
	egw_registeredShortcuts[idx] = {
		"handler": _handler,
		"context": _context,
		"shortcut": {
			"keyCode": _keyCode,
			"shift": _shift,
			"ctrl": _ctrl,
			"alt": _alt
		}
	}
}

/**
 * Unregisters the given shortcut.
 */
export function egw_unregisterGlobalShortcut(_keyCode, _shift, _ctrl, _alt)
{
	// Generate the hash map index for the shortcut
	const idx = egw_shortcutIdx(_keyCode, _shift, _ctrl, _alt);

	// Delete the entry from the hash map
	delete egw_registeredShortcuts[idx];
}

/**
 * the egw_keyHandler function handles various key presses. The boolean
 * _shift, _ctrl, _alt values have been translated into platform independent
 * values (for apple devices).
 */
export function egw_keyHandler(_keyCode, _shift, _ctrl, _alt)
{

	// Check whether there is a global shortcut waiting for the keypress event
	const idx = egw_shortcutIdx(_keyCode, _shift, _ctrl, _alt);
	if (typeof egw_registeredShortcuts[idx] != "undefined")
	{
		const shortcut:Shortcut = egw_registeredShortcuts[idx];

		// Call the registered shortcut function and return its result, if it handled it
		const result = shortcut.handler.call(shortcut.context, shortcut.shortcut);
		if (result) return result;
	}

	// Pass the keypress to the currently focused action object

	// Get the object manager and fetch the container of the currently
	// focused object
	let focusedObject: EgwActionObject = egw_globalObjectManager ? egw_globalObjectManager.getFocusedObject() : null;
	const appMgr: EgwActionObjectManager = egw_getAppObjectManager(false);
	if (appMgr && !focusedObject)
	{
		focusedObject = appMgr.getFocusedObject();

		if (!focusedObject)
		{
			// If the current application doesn't have a focused object,
			// check whether the application object manager contains an object
			// with the EGW_AO_FLAG_DEFAULT_FOCUS flag set.
			//We should never do this for the delete key(keyCode === 46 ; idx === __46__ ) as one might delete an unselected mail by mistake
			if(idx !== "__46__") {
			let egwActionObject:EgwActionObject = null;
			for (const child of appMgr.children)
			{
				if (egwBitIsSet(EGW_AO_FLAG_DEFAULT_FOCUS, child.flags))
				{
					egwActionObject = child;
					break;
				}
			}

			// Get the first child of the found container and focus the first
			// object
			if (egwActionObject && egwActionObject.children.length > 0)
			{
				egwActionObject.children[0].setFocused(true);
				focusedObject = egwActionObject.children[0];
			}
			}
		}
	}
	if (focusedObject)
	{
		// Handle the default keys (arrow_up, down etc.)
		let egwActionObject = focusedObject.getContainerRoot();
		let handled = false;

		if (egwActionObject)
		{
			handled = egwActionObject.handleKeyPress(_keyCode, _shift, _ctrl, _alt);
		}

		// Execute the egw_popup key handler of the focused object
		if (!handled)
		{
			return focusedObject.executeActionImplementation(
				{
					"keyEvent": {
						"keyCode": _keyCode,
						"shift": _shift,
						"ctrl": _ctrl,
						"alt": _alt
					}
				}, "popup", EGW_AO_EXEC_SELECTED);
		}

		return handled;
	}

	return false;
}


