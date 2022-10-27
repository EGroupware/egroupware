<?php
/**
 * EGroupware admin - change EGw configuration
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray <ng@egroupware.org>
 * @package admin
 * @copyright (c) 2018 by Nathan Gray <ng@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;

/**
 * setup command: change EGw configuration
 *
 * @property-read string $app app whos customfield to change
 * @property-read array $set config data to set, value of null or "" to remove
 * @property-read array $old old values to record
 */
class admin_cmd_customfield extends admin_cmd
{
	/**
	 * Allow to run this command via setup-cli
	 */
	//const SETUP_CLI_CALLABLE = true;	// need to check how to parse arguments

	/**
	 * Constructor
	 *
	 * @param array|string $data data array or app whos config to change
	 * @param array $set =null config data to set, just customfield name & ID to remove
	 * @param array $old =null old values to record
	 * @param array $other =null values for keys "requested", "requested_email", "comment", etc
	 */
	function __construct($data, array $set=null, array $old=null, $other=null)
	{
		if (!is_array($data))
		{
			$data = array(
				'app' => $data,
				'field' => $set['name'],
				'set' => $set,
				'old' => $old,
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
		if ($check_only)
		{
			return true;	// no specific checks exist
		}

		$deleted = false;

		if($this->set['id'] && !$this->old)
		{
			$cfs = Api\Storage\Customfields::get($this->app,true);
			$this->old = $cfs[$this->field];
		}

		if(array_keys($this->set) == array('id','name'))
		{
			// Delete
			$so = new Api\Storage\Base('phpgwapi', 'egw_customfields', null, '', true);
			$so->delete($this->set['id']);
			$deleted = true;

			$push = new Api\Json\Push(Api\Json\Push::ALL);
			$push->apply("egw.push", [[
										  'app'  => Api\Storage\Customfields::PUSH_APP,
										  'id'   => $this->set['id'],
										  'type' => 'delete'
									  ]]);
		}
		else
		{
			Api\Storage\Customfields::update($this->set);
		}

		// Clean data for history
		$set = $this->set;
		$old = $this->old;
		unset($old['modified'], $old['modifier'], $old['tab']);
		foreach($set as $key => $value)
		{
			if(is_array($old) && array_key_exists($key, $old) && $old[$key] == $value)
			{
				// Need to keep these 2 in set so we can tell if it was deleted
				if(!in_array($key, array('id','name')))
				{
					unset($set[$key]);
					unset($old[$key]);
				}
				else
				{
					// Make sure it's a string, not an int
					$set[$key] = ''.$value;
				}
			}
		}
		$this->set = $set;
		$this->old = $old;

		return $deleted ? lang('Customfield deleted') : lang('Customfield saved.');
	}

	/**
	 * Return a title / string representation for a given command, eg. to display it
	 *
	 * @return string
	 */
	function __tostring()
	{
		if(array_keys($this->set) == array('id','name'))
		{

			return lang('Customfield \'%1\' deleted', $this->field);
		}
		else if ($this->old == NULL)
		{
			return lang('Customfield \'%1\' added', $this->field);
		}
		return lang('Customfield \'%1\' modified', $this->field);
	}

	/**
	 * Return the whole object-data as array, it's a cast of the object to an array
	 *
	 * @return array
	 */
	function as_array()
	{
		$array = parent::as_array();
		$stringify = function($_values)
		{
			if (is_array($_values))
			{
				$values = '';
				foreach($_values as $var => $value)
				{
					$values .= (!empty($values) ? "\n" : '').$var.'='.$value;
				}
				return $values;
			}
			return $_values;
		};
		$array['set']['values'] = $stringify($array['set']['values']);
		$array['old']['values'] = $stringify($array['old']['values']);
		return $array;
	}

	/**
	 * Get name of eTemplate used to make the change to derive UI for history
	 *
	 * @return string|null etemplate name
	 */
	protected function get_etemplate_name()
	{
		return 'admin.customfield_edit';
	}


	/**
	 * Return (human readable) labels for keys of changes
	 *
	 * @return array
	 */
	function get_change_labels()
	{
		$labels = parent::get_change_labels();

		foreach($labels as $id => $label)
		{
			if(strpos($id, 'cf_') === 0)
			{
				$labels[substr($id, 3)] = $label;
				unset($labels[$id]);
			}
		}
		$labels['app'] = 'Application';
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
		foreach($widgets as $id => $type)
		{
			if(strpos($id, 'cf_') === 0)
			{
				$widgets[substr($id, 3)] = $type;
				unset($widgets[$id]);
			}
		}
		$widgets['private'] = 'select-account';
		$widgets['type2'] = array(
			'n' => 'Contact' // Addressbook doesn't define it's normal type
		);
		foreach(Api\Config::get_content_types($this->app) as $type => $entry)
		{
			$widgets['type2'][$type] = is_array($entry) ? $entry['name'] : $entry;
		}
		return $widgets;
	}
}
