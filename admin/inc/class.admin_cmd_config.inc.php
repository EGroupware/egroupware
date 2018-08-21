<?php
/**
 * EGroupware admin - change EGw configuration
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 * @package setup
 * @copyright (c) 2018 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;

/**
 * setup command: change EGw configuration
 *
 * @property-read string $app app whos config to change (egw_config.config_app)
 * @property-read string $appname app name whos config is changed (some apps store their config under app="phpgwapi")
 * @property-read array $set config data to set, value of null or "" to remove
 * @property-read array $old old values to record
 */
class admin_cmd_config extends admin_cmd
{
	/**
	 * Allow to run this command via setup-cli
	 */
	//const SETUP_CLI_CALLABLE = true;	// need to check how to parse arguments

	/**
	 * Constructor
	 *
	 * @param array|string $data data array or app whos config to change
	 * @param array $set =null config data to set, value of null or "" to remove
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

		$config = new Api\Config($this->app);
		$config->read_repository();

		// store the config
		foreach($this->set as $name => $value)
		{
			$config->value($name, $value);
		}
		$config->save_repository();

		return lang('Configuration saved.');
	}

	/**
	 * Return a title / string representation for a given command, eg. to display it
	 *
	 * @return string
	 */
	function __tostring()
	{
		return lang('%1 site configuration',
			lang($this->appname ? $this->appname : $this->app));
	}
}
