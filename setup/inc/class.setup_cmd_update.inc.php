<?php
/**
 * EGroupware setup - update one EGroupware instances
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package setup
 * @copyright (c) 2009 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * setup command: update one EGroupware instances
 */
class setup_cmd_update extends setup_cmd
{
	/**
	 * Allow to run this command via setup-cli
	 */
	const SETUP_CLI_CALLABLE = true;

	/**
	 * Constructor
	 *
	 * @param string|array $domain string with domain-name or array with all arguments
	 * @param string $config_user=null user to config the domain (or header_admin_user)
	 * @param string $config_passwd=null pw of above user
	 * @param string $backup=null filename of backup to use instead of new install, default new install
	 * @param boolean $verbose=false if true, echos out some status information during the run
	 */
	function __construct($domain,$config_user=null,$config_passwd=null,$backup=null,$verbose=false)
	{
		if (!is_array($domain))
		{
			$domain = array(
				'domain'        => $domain,
				'config_user'   => $config_user,
				'config_passwd' => $config_passwd,
				'backup'        => $backup,
				'verbose'       => $verbose,
			);
		}
		//echo __CLASS__.'::__construct()'; _debug_array($domain);
		admin_cmd::__construct($domain);
	}

	/**
	 * run the command: update
	 *
	 * @param boolean $check_only=false only run the checks (and throw the exceptions), but not the command itself
	 * @return string serialized $GLOBALS defined in the header.inc.php
	 * @throws Exception(lang('Wrong credentials to access the header.inc.php file!'),2);
	 * @throws Exception('header.inc.php not found!');
	 */
	protected function exec($check_only=false)
	{
		global $setup_info;

		// instanciate setup object and check authorisation
		$this->check_setup_auth($this->config_user,$this->config_passwd,$this->domain);

		$this->check_installed($this->domain,array(14),$this->verbose);

		if ($GLOBALS['egw_info']['setup']['stage']['db'] != 4)
		{
			return lang('No update necessary, domain %1(%2) is up to date.',$this->domain,$GLOBALS['egw_domain'][$this->domain]['db_type']);
		}
		$setup_info = self::$egw_setup->detection->upgrade_exclude($setup_info);

		self::_echo_message($this->verbose,lang('Start updating the database ...'));

		ob_start();
		self::$egw_setup->process->init_process();	// we need a new schema-proc instance for each new domain
		self::$egw_setup->process->pass($setup_info,'upgrade',false);
		$messages = ob_get_contents();
		ob_end_clean();
		if ($messages && $this->verbose) echo strip_tags($messages)."\n";

		$this->restore_db();

		return lang('Update finished.');
	}
}
