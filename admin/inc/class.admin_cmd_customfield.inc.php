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
			$so = new Api\Storage\Base('phpgwapi','egw_customfields',null,'',true);
			$so->delete($this->set['id']);
			$deleted = true;
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
			if(array_key_exists($key, $old) && $old[$key] == $value)
			{
				unset($set[$key]);
				unset($old[$key]);
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
}
