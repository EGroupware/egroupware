<?php
/**
 * EGroupware admin - delete EGw category
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray <ng@egroupware.org>
 * @package admin
 * @copyright (c) 2018 Nathan Gray <ng@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;

/**
 * setup command: delete EGw category
 *
 * @property-read string $app app whos category to delete (Categories->app_name)
 * @property-read array $set category data to set, value of null or "" to remove
 * @property array $old old values to record
 * @property-read int $cat_id category ID to delete
 * @property string $cat_name Category name at the time of the change
 * @property-read boolean $subs Delete subs as well
 */
class admin_cmd_delete_category extends admin_cmd
{
	/**
	 * Allow to run this command via setup-cli
	 */
	//const SETUP_CLI_CALLABLE = true;	// need to check how to parse arguments

	/**
	 * Constructor
	 *
	 * @param array|int ID of category to remove
	 * @param boolean $subs Remove sub-categories as well
	 * @param array $other =null values for keys "requested", "requested_email", "comment", etc
	 */
	function __construct($data, $subs = true, $other=null)
	{

		if(!is_array($data))
		{
			$this->app = Api\Categories::id2name($data, 'appname');
			$data = array(
				'cat_id' => (int)$data,
				'subs' => $subs
			)+(array)$other;
		}

		//echo __CLASS__.'::__construct()'; _debug_array($domain);
		admin_cmd::__construct($data);
	}

	/**
	 * run the command: write the configuration to the database
	 *
	 * @param boolean $check_only =false only run the checks (and throw the exceptions), but not the command itself
	 * @return string success message
	 * @throws Exception(lang('Wrong credentials to access the header.inc.php file!'),2);
	 * @throws Exception('header.inc.php not found!');
	 */
	protected function exec($check_only=false)
	{
		$cats = new Api\Categories('',$this->app);
		if(empty($this->old) && $this->cat_id)
		{
			$this->old = $cats->read($this->cat_id);
		}
		if ($check_only)
		{
			return true;
		}

		// Put this there for posterity
		$this->cat_name = Api\Categories::id2name($this->cat_id);
		$cats->delete($this->cat_id, $this->subs, !$this->subs);

		return lang('Category deleted.');
	}

	/**
	 * Return a title / string representation for a given command, eg. to display it
	 *
	 * @return string
	 */
	function __tostring()
	{
		return lang('Category \'%1\' deleted' , $this->data['cat_name']);
	}


	/**
	 * Get name of eTemplate used to make the change to derive UI for history
	 *
	 * @return string|null etemplate name
	 */
	protected function get_etemplate_name()
	{
		return 'admin.categories.edit';
	}

	/**
	 * Return (human readable) labels for keys of changes
	 *
	 * @return array
	 */
	function get_change_labels()
	{
		$labels = parent::get_change_labels();
		// Never seems to be in old value, so don't show it
		$labels['icon_url'] = False;
		// Just for internal use, no need to show it
		$labels['main'] = $labels['app_name'] = $labels['level'] = False;

		return $labels;
	}

	/**
	 * Return widget types (indexed by field key) for changes
	 *
	 * Used by historylog widget to show the changes the command recorded.
	 */
	function get_change_widgets()
	{
		$widgets = parent::get_change_widgets();
		unset($widgets['data[icon]']);
		unset($widgets['data[color]']);
		$widgets['data'] = array(
			// Categories have non-standard image location, so image widget can't find them
			// without being given the full path, which we don't have
			'icon' => 'description',
			'color' => 'colorpicker'
		);
		$widgets['parent'] = 'select-cat';
		$widgets['owner'] = 'select-account';
		$widgets['appname'] = 'select-app';
		return $widgets;
	}
}
