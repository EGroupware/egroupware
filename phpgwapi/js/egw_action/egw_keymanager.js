/**
 * eGroupWare egw_action framework - Shortcut/Keyboard input manager
 *
 * @link http://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 * @version $Id$
 */

/*egw:uses
	jquery.jquery;
	egw_action;
*/

/**
 * Define the key constants (IE doesn't support "const" keyword)
 */

var EGW_KEY_BACKSPACE = 8;
var EGW_KEY_TAB = 9;
var EGW_KEY_ENTER = 13;
var EGW_KEY_ESCAPE = 27;
var EGW_KEY_DELETE = 46;

var EGW_KEY_SPACE = 32;

var EGW_KEY_PAGE_UP = 33;
var EGW_KEY_PAGE_DOWN = 34;

var EGW_KEY_ARROW_LEFT = 37;
var EGW_KEY_ARROW_UP = 38;
var EGW_KEY_ARROW_RIGHT = 39;
var EGW_KEY_ARROW_DOWN = 40;

var EGW_KEY_0 = 48;
var EGW_KEY_1 = 49;
var EGW_KEY_2 = 50;
var EGW_KEY_3 = 51;
var EGW_KEY_4 = 52;
var EGW_KEY_5 = 53;
var EGW_KEY_6 = 54;
var EGW_KEY_7 = 55;
var EGW_KEY_8 = 56;
var EGW_KEY_9 = 57;

var EGW_KEY_A = 65;
var EGW_KEY_B = 66;
var EGW_KEY_C = 67;
var EGW_KEY_D = 68;
var EGW_KEY_E = 69;
var EGW_KEY_F = 70;
var EGW_KEY_G = 71;
var EGW_KEY_H = 72;
var EGW_KEY_I = 73;
var EGW_KEY_J = 74;
var EGW_KEY_K = 75;
var EGW_KEY_L = 76;
var EGW_KEY_M = 77;
var EGW_KEY_N = 78;
var EGW_KEY_O = 79;
var EGW_KEY_P = 80;
var EGW_KEY_Q = 81;
var EGW_KEY_R = 82;
var EGW_KEY_S = 83;
var EGW_KEY_T = 84;
var EGW_KEY_U = 85;
var EGW_KEY_V = 86;
var EGW_KEY_W = 87;
var EGW_KEY_X = 88;
var EGW_KEY_Y = 89;
var EGW_KEY_Z = 90;

var EGW_KEY_MENU = 93;

var EGW_KEY_F1 = 112;
var EGW_KEY_F2 = 113;
var EGW_KEY_F3 = 114;
var EGW_KEY_F4 = 115;
var EGW_KEY_F5 = 116;
var EGW_KEY_F6 = 117;
var EGW_KEY_F7 = 118;
var EGW_KEY_F8 = 119;
var EGW_KEY_F9 = 120;
var EGW_KEY_F10 = 121;
var EGW_KEY_F11 = 122;
var EGW_KEY_F12 = 123;

var EGW_VALID_KEYS = [
	8, 9, 13, 27, 46, 32, 33, 34, 37, 38, 39, 40, 48, 49, 50, 51, 52, 53, 54,
	55, 56, 57, 65, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 79, 80,
	81, 82, 83, 84, 85, 86, 87, 88, 89, 90, 93, 112, 113, 114, 115, 116, 117, 118,
	119, 120, 121, 122, 123
]

/**
 * The tranlation function converts the given native key code into one of the
 * egw key constants as listed above. This key codes were chosen to match the
 * key codes of IE and FF.
 */
var egw_keycode_translation_function = function(_nativeKeyCode) {
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
function egw_keycode_makeValid(_keyCode) {
	var idx = EGW_VALID_KEYS.indexOf(_keyCode);
	if (idx >= 0) {
		return _keyCode;
	}

	return -1;
}

function _egw_nodeIsInInput(_node)
{
	if ((_node != null) && (_node != document))
	{
		var tagName = _node.tagName.toLowerCase();
		if (tagName == "input" || tagName == "select" || tagName == 'textarea' || tagName == 'button')
		{
			return true;
		}
		else
		{
			return _egw_nodeIsInInput(_node.parentNode);
		}
	}
	else
	{
		return false;
	}
}

/**
 * Register the onkeypress handler on the document 
 */
$j(document).ready(function() {

	// Fetch the key down event and translate it into browser-independent and
	// easy to use key codes and shift states
	$j(document).keydown( function(e) {

		// Translate the given key code and make it valid
		var keyCode = e.which;
		keyCode = egw_keycode_translation_function(keyCode);
		keyCode = egw_keycode_makeValid(keyCode);

		// Only go on if this is a valid key code - call the key handler
		if (keyCode != -1)
		{
			// Check whether the event came from an input field - if yes, only
			// allow function keys (like F1) to be captured by our code
			var inInput = _egw_nodeIsInInput(e.target);
			if (!inInput || (keyCode >= EGW_KEY_F1 && keyCode <= EGW_KEY_F12))
			{
				if (egw_keyHandler(keyCode, e.shiftKey, e.ctrlKey || e.metaKey, e.altKey))
				{
					// If the key handler successfully passed the key event to some
					// sub component, prevent the default action
					e.preventDefault();
				}
			}
		}
	});
});

/**
 * Required to catch the context menu
 */
$j(window).on("contextmenu",document, function(event) {
	// Check for actual key press
	if(!(event.originalEvent.x == 1 && event.originalEvent.y == 1)) return true;
	if(!event.ctrlKey && egw_keyHandler(EGW_KEY_MENU, event.shiftKey, event.ctrlKey || event.metaKey, event.altKey))
	{
		// If the key handler successfully passed the key event to some
		// sub component, prevent the default action
		event.preventDefault();
		return false;
	}
	return true;
});


/**
 * Creates an unique key for the given shortcut
 */
function egw_shortcutIdx(_keyCode, _shift, _ctrl, _alt)
{
	return "_" + _keyCode + "_" + 
		(_shift ? "S" : "") +
		(_ctrl ? "C" : "") +
		(_alt ? "A" : "");
}

var egw_registeredShortcuts = {}

/**
 * Registers a global shortcut. If the shortcut already exists, it is overwritten.
 * @param int _keyCode is one of the keycode constants
 * @param bool _shift whether shift has to be set
 * @param bool _ctrl whether ctrl has to be set
 * @param bool _alt whether alt has to be set
 * @param function _handler the function which will be called when the shortcut
 * 	is evoked. An object containing the shortcut data will be passed as first
 * 	parameter.
 * @param object _context is the context in which the function will be executed
 */
function egw_registerGlobalShortcut(_keyCode, _shift, _ctrl, _alt, _handler, _context)
{
	// Generate the hash map index for the shortcut
	var idx = egw_shortcutIdx(_keyCode, _shift, _ctrl, _alt);

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
function egw_unregisterGlobalShortcut(_keyCode, _shift, _ctrl, _alt) {
	// Generate the hash map index for the shortcut
	var idx = egw_shortcutIdx(_keyCode, _shift, _ctrl, _alt);

	// Delete the entry from the hash map
	delete egw_registeredShortcuts[idx];
}

/**
 * the egw_keyHandler function handles various key presses. The boolean
 * _shift, _ctrl, _alt values have been translated into platform independent
 * values (for apple devices).
 */
function egw_keyHandler(_keyCode, _shift, _ctrl, _alt) {

	// Check whether there is a global shortcut waiting for the keypress event
	var idx = egw_shortcutIdx(_keyCode, _shift, _ctrl, _alt);
	if (typeof egw_registeredShortcuts[idx] != "undefined")
	{
		var shortcut = egw_registeredShortcuts[idx];

		// Call the registered shortcut function and return its result, if it handled it
		var result = shortcut.handler.call(shortcut.context, shortcut.shortcut);
		if(result) return result;
	}
	
	// Pass the keypress to the currently focused action object

	// Get the object manager and fetch the container of the currently
	// focused object
	var appMgr = egw_getAppObjectManager(false);
	if (appMgr)
	{
		var focusedObject = appMgr.getFocusedObject();

		if (!focusedObject)
		{
			// If the current application doesn't have a focused object,
			// check whether the application object manager contains an object
			// with the EGW_AO_FLAG_DEFAULT_FOCUS flag set.
			var cntr = null;
			for (var i = 0; i < appMgr.children.length; i++)
			{
				var child = appMgr.children[i];
				if (egwBitIsSet(EGW_AO_FLAG_DEFAULT_FOCUS, child.flags))
				{
					cntr = child;
					break;
				}
			}

			// Get the first child of the found container and focus the first
			// object
			if (cntr && cntr.children.length > 0)
			{
				cntr.children[0].setFocused(true);
				focusedObject = cntr.children[0];
			}
		}

		if (focusedObject)
		{

			// Handle the default keys (arrow_up, down etc.)
			var cntr = focusedObject.getContainerRoot();
			var handled = false;

			if (cntr)
			{
				handled = cntr.handleKeyPress(_keyCode, _shift, _ctrl, _alt);
			}

			// Execute the egw_popup key handler of the focused object
			if (!handled) {
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
	}

	return false;
}


