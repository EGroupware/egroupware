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
	 * @param string $app=null single application to update or install
	 */
	function __construct($domain,$config_user=null,$config_passwd=null,$backup=null,$verbose=false,$app=null)
	{
		if (!is_array($domain))
		{
			$domain = array(
				'domain'        => $domain,
				'config_user'   => $config_user,
				'config_passwd' => $config_passwd,
				'backup'        => $backup,
				'verbose'       => $verbose,
				'app'           => $app,
			);
		}
		//echo __CLASS__.'::__construct()'; _debug_array($domain);
		admin_cmd::__construct($domain);
	}

	/**
	 * run the command: update or install/update a single app ($this->app)
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

		if ($GLOBALS['egw_info']['setup']['stage']['db'] != 4 &&
			(!$this->app || !in_array($this->app, self::$apps_to_install) && !in_array($this->app, self::$apps_to_upgrade)))
		{
			return lang('No update necessary, domain %1(%2) is up to date.',$this->domain,$GLOBALS['egw_domain'][$this->domain]['db_type']);
		}
		$setup_info = self::$egw_setup->detection->upgrade_exclude($setup_info);

		self::$egw_setup->process->init_process();	// we need a new schema-proc instance for each new domain

		// request to install a single app
		if ($this->app && in_array($this->app, self::$apps_to_install))
		{
			$app_title = $setup_info[$this->app]['title'] ? $setup_info[$this->app]['title'] : $setup_info[$this->app]['name'];
			self::_echo_message($this->verbose,lang('Start installing application %1 ...',$app_title));
			ob_start();
			$terror = array($this->app => $setup_info[$this->app]);

			if ($setup_info[$this->app]['tables'])
			{
				$terror = self::$egw_setup->process->current($terror,$DEBUG);
				$terror = self::$egw_setup->process->default_records($terror,$DEBUG);
				echo $app_title . ' ' . lang('tables installed, unless there are errors printed above') . '.';
			}
			else
			{
				// check default_records for apps without tables, they might need some initial work too
				$terror = self::$egw_setup->process->default_records($terror,$DEBUG);
				if (self::$egw_setup->app_registered($setup_info[$this->app]['name']))
				{
					self::$egw_setup->update_app($setup_info[$this->app]['name']);
				}
				else
				{
					self::$egw_setup->register_app($setup_info[$this->app]['name']);
				}
				echo $app_title . ' ' . lang('registered') . '.';

				if ($setup_info[$this->app]['hooks'])
				{
					self::$egw_setup->register_hooks($setup_info[$this->app]['name']);
					echo "\n".$app_title . ' ' . lang('hooks registered') . '.';
				}
			}
			self::$egw_setup->process->translation->drop_add_all_langs(false,$this->app);
		}
		else
		{
			self::_echo_message($this->verbose,lang('Start updating the database ...'));
			ob_start();
			self::$egw_setup->process->pass($setup_info,'upgrade',false);
		}
		$messages = ob_get_contents();
		ob_end_clean();
		if ($messages && $this->verbose) echo strip_tags($messages)."\n";

		$this->restore_db();

		return lang('Update finished.');
	}
}
