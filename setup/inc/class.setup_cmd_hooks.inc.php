<?php
/**
 * EGgroupware setup - register all hooks
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package setup
 * @copyright (c) 2011 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * setup command: register all hooks
 */
class setup_cmd_hooks extends setup_cmd
{
	/**
	 * Constructor
	 *
	 * @param string $domain string with domain-name or array with all arguments
	 * @param string $config_user=null user to config the domain (or header_admin_user)
	 * @param string $config_passwd=null pw of above user
	 * @param boolean $verbose=false if true, echos out some status information during the run
	 */
	function __construct($domain,$config_user=null,$config_passwd=null)
	{
		if (!is_array($domain))
		{
			$domain = array(
				'domain'        => $domain,
				'config_user'   => $config_user,
				'config_passwd' => $config_passwd,
			);
		}
		//echo __CLASS__.'::__construct()'; _debug_array($domain);
		admin_cmd::__construct($domain);
	}

	/**
	 * run the command: register all hooks
	 *
	 * @param boolean $check_only=false only run the checks (and throw the exceptions), but not the command itself
	 * @return string success message
	 * @throws Exception(lang('Wrong credentials to access the header.inc.php file!'),2);
	 * @throws Exception('header.inc.php not found!');
	 */
	protected function exec($check_only=false)
	{
		if ($check_only) return true;	// nothing to check, no arguments ...

		// instanciate setup object and check authorisation
		$this->check_setup_auth($this->config_user,$this->config_passwd,$this->domain);

		$this->check_installed($this->domain,15,$this->verbose);

		global $setup_info;
		foreach($setup_info as $appname => $info)
		{
			if ($info['currentver']) self::$egw_setup->register_hooks($appname);
		}
		$this->restore_db();

		return lang('All hooks registered');
	}
}
