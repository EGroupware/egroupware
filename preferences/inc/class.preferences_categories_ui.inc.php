<?php
/**
 * Preferences UI for regular users
 * 
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package preferences
 * @copyright (c) 2011 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Preference UI extends Admin class to give regular users restricted
 * access to modify categories
 */
class preferences_categories_ui extends admin_categories {

	protected $appname = 'preferences';
	protected $get_rows = 'preferences_categories_ui::get_rows';
	protected $list_link = 'preferences.preferences_categories_ui.index';
	protected $edit_link = 'preferences.preferences_categories_ui.edit';
	protected $add_link = 'preferences.preferences_categories_ui.edit';

	function __construct() {
	}

	public static function get_rows(&$query, &$rows, &$readonlys) {
		$count = parent::get_rows($query, $rows, $readonlys);
		$rows['edit_link'] = 'preferences.preferences_categories_ui.edit';
		return $count;
	}
}
?>
