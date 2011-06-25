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

class egw_keymanager
{
	const BACKSPACE = 8;
	const TAB = 9;
	const ENTER = 13;
	const ESCAPE = 27;
	const DELETE = 46;

	const SPACE = 32;

	const PAGE_UP = 33;
	const PAGE_DOWN = 34;

	const ARROW_LEFT = 37;
	const ARROW_UP = 38;
	const ARROW_RIGHT = 39;
	const ARROW_DOWN = 40;

	const _0 = 48;
	const _1 = 49;
	const _2 = 50;
	const _3 = 51;
	const _4 = 52;
	const _5 = 53;
	const _6 = 54;
	const _7 = 55;
	const _8 = 56;
	const _9 = 57;

	const A = 65;
	const B = 66;
	const C = 67;
	const D = 68;
	const E = 69;
	const F = 70;
	const G = 71;
	const H = 72;
	const I = 73;
	const J = 74;
	const K = 75;
	const L = 76;
	const M = 77;
	const N = 78;
	const O = 79;
	const P = 80;
	const Q = 81;
	const R = 82;
	const S = 83;
	const T = 84;
	const U = 85;
	const V = 86;
	const W = 87;
	const X = 88;
	const Y = 89;
	const Z = 90;

	const F1 = 112;
	const F2 = 113;
	const F3 = 114;
	const F4 = 115;
	const F5 = 116;
	const F6 = 117;
	const F7 = 118;
	const F8 = 119;
	const F9 = 120;
	const F10 = 121;
	const F11 = 122;
	const F12 = 123;

	/**
	 * Converts the given key codes into translated key names.
	 */
	public static function key_name($keyCode)
	{
		// Keys which can be directly translated into ASCII chars
		if (($keyCode >= self::_0 && $keyCode <= self::_9) ||
			($keyCode >= self::A && $keyCode <= self::Z))
		{
			return chr($keyCode);
		}

		// Function keys
		if ($keyCode >= self::F1 && $keyCode <= self::F12)
		{
			return "F".($keyCode - EGW_KEY_F1 + 1);
		}

		// Special keys
		switch ($keyCode) {
			case self::BACKSPACE:
				return lang("Back");
			case self::TAB:
				return lang("Tab");
			case self::DELETE:
				return lang("Del");
			case self::SPACE:
				return lang("Space");
			case self::PAGE_UP:
				return lang("Pg up");
			case self::PAGE_DOWN:
				return lang("Pg down");
		}

		return "";
	}

	/**
	 * Generates the caption of the given shortcut and returns it
	 */
	public static function shortcut_caption($keyCode, $shift = false, $ctrl = false, $alt = false)
	{
		$elems = array();

		if ($shift)
		{
			$elems[] = lang("Shift");
		}

		if ($ctrl)
		{
			$elems[] = lang("Ctrl");
		}

		if ($alt)
		{
			$elems[] = lang("Alt");
		}

		$elems[] = self::key_name($keyCode);

		return implode(" + ", $elems);
	}

	/**
	 * Generates a shortcut structure which can be JSON encoded and send to the
	 * egw action system. This function and class could later be used to provide
	 * user defined shortcuts.
	 */
	public static function shortcut($keyCode, $shift = false, $ctrl = false, $alt = false)
	{
		return array(
			"keyCode" => $keyCode,
			"shift" => (boolean)$shift,
			"ctrl" => (boolean)$ctrl,
			"alt" => (boolean)$alt,
			"caption" => self::shortcut_caption($keyCode, $shift, $ctrl, $alt)
		);
	}
}

