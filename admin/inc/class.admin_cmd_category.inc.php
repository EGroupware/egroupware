<?php
/**
 * EGroupware admin - change EGw category
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray <ng@egroupware.org>
 * @package admin
 * @copyright (c) 2018 Nathan Gray <ng@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;

/**
 * setup command: change EGw category
 *
 * @property-read string $app app whos category to change (Categories->app_name)
 * @property-read array $set category data to set, value of null or "" to remove
 * @property-read array $old old values to record
 * @property int $cat_id Category ID
 * @property string $cat_name Category name at the time of the change
 */
class admin_cmd_category extends admin_cmd
{
	/**
	 * Allow to run this command via setup-cli
	 */
	//const SETUP_CLI_CALLABLE = true;	// need to check how to parse arguments

	/**
	 * Constructor
	 *
	 * @param array|string $data data array or app whos category to change
	 * @param array $set =null category data to set, value of null or "" to remove
	 * @param array $old =null old values to record
	 * @param array $other =null values for keys "requested", "requested_email", "comment", etc
	 */
	function __construct($data, array $set=null, array $old=null, $other=null)
	{

		if (!is_array($data))
		{
			$data = array(
				'app' => $data,
				'set' => $set,
				'old' => $old,
			)+(array)$other;
		}
		else if ($data['appname'])
		{
			$this->app = $data['appname'];
		}
		if(!$old && $old !== NULL && $set['id'])
		{
			$data['old'] = Api\Categories::read($set['id']);
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
		if ($check_only)
		{
			return $cats->check_consistency4update($this->set);
		}

		// store the cat
		$this->cat_id = $this->set['id'] ? $cats->edit($this->set) : $cats->add($this->set);

		// Put this there for posterity, if it gets deleted later
		$this->cat_name = Api\Categories::id2name($this->cat_id);

		// Clean data for history
		$set = $this->set;
		$old = $this->old;
		unset($old['last_mod']);
		unset($set['old_parent'], $set['base_url'], $set['last_mod'], $set['all_cats'], $set['no_private']);
		foreach($set as $key => $value)
		{
			if ($old && array_key_exists($key, $old) && $old[$key] == $value)
			{
				unset($set[$key]);
				unset($old[$key]);
			}
		}
		$this->set = $set;
		$this->old = $old;

		return lang('Category saved.');
	}

	/**
	 * Return a title / string representation for a given command, eg. to display it
	 *
	 * @return string
	 */
	function __tostring()
	{
		$current_name = Api\Categories::id2name($this->cat_id);
		if($current_name !== $this->cat_name)
		{
			$current_name = $this->cat_name . ($current_name == '--' ?
					'' : " ($current_name)");
		}
		return lang('%1 category \'%2\' %3',
			lang($this->app),
			$current_name,
			$this->old ? lang('edited') : lang('added')
		);
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
