<?php
/**
 * eGroupWare API: contains classes and key constants for generating shortcuts
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage egw action grid
 * @author Andreas Stöckel
 * @copyright (c) 2011 Stylite
 * @version $Id$
 */

define("EGW_KEY_BACKSPACE", 8);
define("EGW_KEY_TAB", 9);
define("EGW_KEY_ENTER", 13);
define("EGW_KEY_ESCAPE", 27);
define("EGW_KEY_DELETE", 46);

define("EGW_KEY_SPACE", 32);

define("EGW_KEY_PAGE_UP", 33);
define("EGW_KEY_PAGE_DOWN", 34);

define("EGW_KEY_ARROW_LEFT", 37);
define("EGW_KEY_ARROW_UP", 38);
define("EGW_KEY_ARROW_RIGHT", 39);
define("EGW_KEY_ARROW_DOWN", 40);

define("EGW_KEY_0", 48);
define("EGW_KEY_1", 49);
define("EGW_KEY_2", 50);
define("EGW_KEY_3", 51);
define("EGW_KEY_4", 52);
define("EGW_KEY_5", 53);
define("EGW_KEY_6", 54);
define("EGW_KEY_7", 55);
define("EGW_KEY_8", 56);
define("EGW_KEY_9", 57);

define("EGW_KEY_A", 65);
define("EGW_KEY_B", 66);
define("EGW_KEY_C", 67);
define("EGW_KEY_D", 68);
define("EGW_KEY_E", 69);
define("EGW_KEY_F", 70);
define("EGW_KEY_G", 71);
define("EGW_KEY_H", 72);
define("EGW_KEY_I", 73);
define("EGW_KEY_J", 74);
define("EGW_KEY_K", 75);
define("EGW_KEY_L", 76);
define("EGW_KEY_M", 77);
define("EGW_KEY_N", 78);
define("EGW_KEY_O", 79);
define("EGW_KEY_P", 80);
define("EGW_KEY_Q", 81);
define("EGW_KEY_R", 82);
define("EGW_KEY_S", 83);
define("EGW_KEY_T", 84);
define("EGW_KEY_U", 85);
define("EGW_KEY_V", 86);
define("EGW_KEY_W", 87);
define("EGW_KEY_X", 88);
define("EGW_KEY_Y", 89);
define("EGW_KEY_Z", 90);

define("EGW_KEY_F1", 112);
define("EGW_KEY_F2", 113);
define("EGW_KEY_F3", 114);
define("EGW_KEY_F4", 115);
define("EGW_KEY_F5", 116);
define("EGW_KEY_F6", 117);
define("EGW_KEY_F7", 118);
define("EGW_KEY_F8", 119);
define("EGW_KEY_F9", 120);
define("EGW_KEY_F10", 121);
define("EGW_KEY_F11", 122);
define("EGW_KEY_F12", 123);

class egw_keymanager
{
	/**
	 * Generates a shortcut structure which can be JSON encoded and send to the
	 * egw action system. This function and class could later be used to provide
	 * user defined shortcuts.
	 */
	public static function shortcut($keyCode, $shift = false, $ctrl = false, $alt = false)
	{
		if ($shift == false && $ctrl == false && $alt == false)
		{
			return $keyCode;
		}

		return array(
			"keyCode" => $keyCode,
			"shift" => (boolean)$shift,
			"ctrl" => (boolean)$ctrl,
			"alt" => (boolean)$alt
		);
	}
}

