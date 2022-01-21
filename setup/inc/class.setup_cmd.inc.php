<?php
/**
 * eGgroupWare setup - abstract baseclass for all setup commands, extending admin_cmd
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package setup
 * @copyright (c) 2007-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Egw;

/**
 * setup command: abstract baseclass for all setup commands, extending admin_cmd
 */
abstract class setup_cmd extends admin_cmd
{
	/**
	 * Defaults set for empty options while running the command
	 *
	 * @var array
	 */
	public $set_defaults = array();

	/**
	 * Should be called by every command usually requiring header admin rights
	 *
	 * @throws Api\Exception\NoPermission(lang('Wrong credentials to access the header.inc.php file!'),2);
	 */
	protected function _check_header_access()
	{
		if (!$this->header_secret && $this->header_admin_user)	// no secret specified but header_admin_user/password
		{
			if (!$this->uid) $this->uid = true;
			$this->set_header_secret($this->header_admin_user,$this->header_admin_password);
		}
		$secret = $this->_calc_header_secret($GLOBALS['egw_info']['server']['header_admin_user'],
				$GLOBALS['egw_info']['server']['header_admin_password']);
		if ($this->uid === true) unset($this->uid);

		if ($this->header_secret != $secret)
		{
			//echo "_check_header_access: header_secret='$this->header_secret' != '$secret'=_calc_header_secret({$GLOBALS['egw_info']['server']['header_admin_user']},{$GLOBALS['egw_info']['server']['header_admin_password']})\n";
			throw new Api\Exception\NoPermission(lang('Wrong credentials to access the header.inc.php file!'),5);
		}

	}

	/**
	 * Set the user and pw required for any operation on the header file
	 *
	 * @param string $user
	 * @param string $pw password or md5 hash of it
	 */
	public function set_header_secret($user,$pw)
	{
		if ($this->uid || parent::save(false))	// we need to save first, to get the uid
		{
			$this->header_secret = $this->_calc_header_secret($user,$pw);
		}
		else
		{
			throw new Exception ('failed to set header_secret!');
		}
	}

	/**
	 * Calculate the header_secret used to access the header from this command
	 *
	 * It's an md5 over the uid, header-admin-user and -password.
	 *
	 * @param string $header_admin_user
	 * @param string $header_admin_password
	 * @return string
	 */
	private function _calc_header_secret($header_admin_user=null,$header_admin_password=null)
	{
		if (!self::is_md5($header_admin_password)) $header_admin_password = md5($header_admin_password);

		$secret = md5($this->uid.$header_admin_user.$header_admin_password);
		//echo "header_secret='$secret' = md5('$this->uid'.'$header_admin_user'.'$header_admin_password')\n";
		return $secret;
	}

	/**
	 * Saving the object to the database, reimplemented to not do it in setup context
	 *
	 * @param boolean $set_modifier =true set the current user as modifier or 0 (= run by the system)
	 * @return boolean true on success, false otherwise
	 */
	function save($set_modifier=true)
	{
		if (isset($GLOBALS['egw']->db) && is_object($GLOBALS['egw']->db) && $GLOBALS['egw']->db->Database)
		{
			return parent::save($set_modifier);
		}
		return true;
	}

	/**
	 * Reference to the setup object, after calling check_setup_auth method
	 *
	 * @var setup
	 */
	static protected $egw_setup;

	static private $egw_accounts_backup;

	/**
	 * Create the setup environment (for running within setup or EGw)
	 */
	static protected function _setup_enviroment($domain=null)
	{
		if (!is_object($GLOBALS['egw_setup']))
		{
			require_once(EGW_INCLUDE_ROOT.'/setup/inc/class.setup.inc.php');
			$GLOBALS['egw_setup'] = new setup(true,true);
		}
		self::$egw_setup = $GLOBALS['egw_setup'];
		self::$egw_setup->ConfigDomain = $domain;

		if (isset($GLOBALS['egw_info']['server']['header_admin_user']) && !isset($GLOBALS['egw_domain']) &&
			is_object($GLOBALS['egw']) && $GLOBALS['egw'] instanceof Egw)
		{
			// we run inside EGw, not setup --> read egw_domain array from the header via the showheader cmd
			$cmd = new setup_cmd_showheader(null);	// null = only header, no db stuff, no hashes
			$header = $cmd->run();
			$GLOBALS['egw_domain'] = $header['egw_domain'];

			if (is_object($GLOBALS['egw']->accounts) && is_null(self::$egw_accounts_backup))
			{
				self::$egw_accounts_backup = $GLOBALS['egw']->accounts;
				unset($GLOBALS['egw']->accounts);
			}
		}
		if (is_object($GLOBALS['egw']->db) && $domain)
		{
			$GLOBALS['egw']->db->disconnect();
			$GLOBALS['egw']->db = new Api\Db($GLOBALS['egw_domain'][$domain]);

			// change caching to managed instance
			Api\Cache::unset_instance_key();
		}
	}

	/**
	 * Restore EGw's db connection
	 *
	 */
	static function restore_db()
	{
		if (is_object($GLOBALS['egw']->db))
		{
			$GLOBALS['egw']->db->disconnect();
			$GLOBALS['egw']->db = new Api\Db($GLOBALS['egw_info']['server']);

			// change caching back to own instance
			Api\Cache::unset_instance_key();

			if (!is_null(self::$egw_accounts_backup))
			{
				$GLOBALS['egw']->accounts = self::$egw_accounts_backup;
				Api\Accounts::cache_invalidate();
				self::$egw_accounts_backup = null;
			}
		}
	}

	/**
	 * Creates a setup like enviroment and checks for the header user/pw or config_user/pw if domain given
	 *
	 * @param string $user
	 * @param string $pw
	 * @param string $domain =null if given we also check agains config user/pw
	 * @throws Api\Exception\NoPermission(lang('Access denied: wrong username or password for manage-header !!!'),21);
	 * @throws Api\Exception\NoPermission(lang("Access denied: wrong username or password to configure the domain '%1(%2)' !!!",$domain,$GLOBALS['egw_domain'][$domain]['db_type']),40);
	 */
	static function check_setup_auth($user,$pw,$domain=null)
	{
		self::_setup_enviroment($domain);

		// check the authentication if a header_admin_password is set, if not we dont have a header yet and no authentication
		if ($GLOBALS['egw_info']['server']['header_admin_password'])	// if that's not given we dont have a header yet
		{
			if (!self::$egw_setup->check_auth($user,$pw,$GLOBALS['egw_info']['server']['header_admin_user'],
					$GLOBALS['egw_info']['server']['header_admin_password']) &&
				(is_null($domain) || !isset($GLOBALS['egw_domain'][$domain]) || // if valid domain given check it's config user/pw
					!self::$egw_setup->check_auth($user,$pw,$GLOBALS['egw_domain'][$domain]['config_user'],
						$GLOBALS['egw_domain'][$domain]['config_passwd'])))
			{
				if (is_null($domain))
				{
					throw new Api\Exception\NoPermission(lang('Access denied: wrong username or password for manage-header !!!'),21);
				}
				else
				{
					throw new Api\Exception\NoPermission(lang("Access denied: wrong username or password to configure the domain '%1(%2)' !!!",$domain,$GLOBALS['egw_domain'][$domain]['db_type']),40);
				}
			}
		}
	}

	/**
	 * Applications which are currently not installed (set after call to check_installed, for the last/only domain only)
	 *
	 * @var array
	 */
	static public $apps_to_install=array();
	/**
	 * Applications which are currently need update (set after call to check_installed, for the last/only domain only)
	 *
	 * @var array
	 */
	static public $apps_to_upgrade=array();

	/**
	 * Check if EGw is installed, which versions and if an update is needed
	 *
	 * Sets self::$apps_to_update and self::$apps_to_install for the last/only domain only!
	 *
	 * @param string $domain ='' domain to check, default '' = all
	 * @param int/array $stop =0 stop checks before given exit-code(s), default 0 = all checks
	 * @param boolean $verbose =false echo messages as they happen, instead returning them
	 * @return array with translated messages
	 */
	static function check_installed($domain='',$stop=0,$verbose=false)
	{
		self::_setup_enviroment($domain);

		global $setup_info;
		static $header_checks=true;	// output the header checks only once

		$messages = array();

		if ($stop && !is_array($stop)) $stop = array($stop);

		$versions =& $GLOBALS['egw_info']['server']['versions'];

		if (!$versions['api'])
		{
			if (!include(EGW_INCLUDE_ROOT.'/api/setup/setup.inc.php'))
			{
				throw new Api\Exception\WrongUserinput(lang("EGroupware sources in '%1' are not complete, file '%2' missing !!!",realpath('..'),'api/setup/setup.inc.php'),99);	// should not happen ;-)
			}
			$versions['api'] = $setup_info['api']['version'];
			unset($setup_info);
		}
		if ($header_checks)
		{
			$messages[] = self::_echo_message($verbose,lang('EGroupware API version %1 found.',$versions['api']));
		}
		$header_stage = self::$egw_setup->detection->check_header();
		if ($stop && in_array($header_stage,$stop)) return true;

		switch ($header_stage)
		{
			case 1: throw new Api\Exception\WrongUserinput(lang('EGroupware configuration file (header.inc.php) does NOT exist.')."\n".lang('Use --create-header to create the configuration file (--usage gives more options).'),1);

//			case 2: throw new Api\Exception\WrongUserinput(lang('EGroupware configuration file (header.inc.php) version %1 exists%2',$versions['header'],'.')."\n".lang('No header admin password set! Use --edit-header <password>[,<user>] to set one (--usage gives more options).'),2);

			case 3: throw new Api\Exception\WrongUserinput(lang('EGroupware configuration file (header.inc.php) version %1 exists%2',$versions['header'],'.')."\n".lang('No EGroupware domains / database instances exist! Use --edit-header --domain to add one (--usage gives more options).'),3);

			case 4: throw new Api\Exception\WrongUserinput(lang('EGroupware configuration file (header.inc.php) version %1 exists%2',$versions['header'],'.')."\n".lang('It needs upgrading to version %1! Use --update-header <password>[,<user>] to do so (--usage gives more options).',$versions['current_header']),4);
		}
		if ($header_checks)
		{
			$messages[] = self::_echo_message($verbose,lang('EGroupware configuration file (header.inc.php) version %1 exists%2',
				$versions['header'],' '.lang('and is up to date')));
		}
		unset($header_checks);	// no further output of the header checks

		$domains = $GLOBALS['egw_domain'];
		if ($domain)	// domain to check given
		{
			if (!isset($GLOBALS['egw_domain'][$domain])) throw new Api\Exception\WrongUserinput(lang("Domain '%1' does NOT exist !!!",$domain), 92);

			$domains = array($domain => $GLOBALS['egw_domain'][$domain]);
		}
		foreach($domains as $domain => $data)
		{
			self::$egw_setup->ConfigDomain = $domain;	// set the domain the setup class operates on
			if (count($GLOBALS['egw_domain']) > 1)
			{
				self::_echo_message($verbose);
				$messages[] = self::_echo_message($verbose,lang('EGroupware domain/instance %1(%2):',$domain,$data['db_type']));
			}
			$setup_info = self::$egw_setup->detection->get_versions();
			// check if there's already a db-connection and close if, otherwise the db-connection of the previous domain will be used
			if (is_object(self::$egw_setup->db))
			{
				self::$egw_setup->db->disconnect();
			}
			self::$egw_setup->loaddb();

			$db = $data['db_type'].'://'.$data['db_user'].':'.$data['db_pass'].'@'.$data['db_host'].'/'.$data['db_name'];

			$db_stage =& $GLOBALS['egw_info']['setup']['stage']['db'];
			if (($db_stage = self::$egw_setup->detection->check_db($setup_info)) != 1)
			{
				$setup_info = self::$egw_setup->detection->get_db_versions($setup_info);
				$db_stage = self::$egw_setup->detection->check_db($setup_info);
			}
			if ($stop && in_array(10+$db_stage,$stop))
			{
				return $messages;
			}
			switch($db_stage)
			{
				case 1: throw new Api\Exception\WrongUserinput(lang('Your Database is not working!')." $db: ".self::$egw_setup->db->Error,11);

				case 3: throw new Api\Exception\WrongUserinput(lang('Your database is working, but you dont have any applications installed')." ($db). ".lang("Use --install to install EGroupware."),13);

				case 4: throw new Api\Exception\WrongUserinput(lang('EGroupware API needs a database (schema) update from version %1 to %2!',$setup_info['api']['currentver'],$versions['api']).' '.lang('Use --update to do so.'),14);

				case 10:	// also check apps of updates
					self::$apps_to_upgrade = self::$apps_to_install = array();
					foreach($setup_info as $app => $data)
					{
						if ($data['currentver'] && $data['version'] && $data['version'] != 'deleted' && $data['version'] != $data['currentver'] &&
							$data['currentversion'] !== 'uninstalled')
						{
							self::$apps_to_upgrade[] = $app;
						}
						if (!isset($data['enabled']) && isset($data['version']))	// jdots eg. is no app, but a template
						{
							self::$apps_to_install[] = $app;
						}
					}
					// add autodeinstall apps
					self::$apps_to_upgrade = array_unique(array_merge(self::$apps_to_upgrade, self::check_autodeinstall()));

					if (self::$apps_to_install)
					{
						self::_echo_message($verbose);
						$messages[] = self::_echo_message($verbose,lang('The following applications are NOT installed:').' '.implode(', ',self::$apps_to_install));
					}
					if (self::$apps_to_upgrade)
					{
						$db_stage = 4;
						if ($stop && in_array(10+$db_stage,$stop)) return $messages;

						throw new Api\Exception\WrongUserinput(lang('The following applications need to be upgraded:').' '.implode(', ',self::$apps_to_upgrade).'! '.lang('Use --update to do so.'),14);
					}
					break;
			}
			$messages[] = self::_echo_message($verbose,lang("database is version %1 and up to date.",$setup_info['api']['currentver']));

			self::$egw_setup->detection->check_config();
			if ($GLOBALS['egw_info']['setup']['config_errors'] && $stop && !in_array(15,$stop))
			{
				throw new Api\Exception\WrongUserinput(lang('You need to configure EGroupware:')."\n- ".@implode("\n- ",$GLOBALS['egw_info']['setup']['config_errors']),15);
			}
		}
		return $messages;
	}

	/**
	 * Check if there are apps which should be autoinstalled
	 *
	 * @return array with app-names
	 */
	static function check_autoinstall()
	{
		$ret = array_filter(self::$apps_to_install, function($app)
		{
			global $setup_info;
			return !empty($setup_info[$app]['autoinstall']) && !isset($setup_info[$app]['currentversion']);
		});
		//error_log(__METHOD__."() apps_to_install=".array2string(self::$apps_to_install).' returning '.array2string($ret));
		return $ret;
	}

	/**
	 * Check if app should be automatically deinstalled
	 *
	 * @return array with app-names to automatic deinstall
	 */
	static function check_autodeinstall()
	{
		global $setup_info;

		$ret = array_values(array_filter(array_keys($setup_info), function($app)
		{
			global $setup_info;
			if (empty($setup_info[$app]['autodeinstall']))
			{
				return false;
			}
			$autodeinstall = $setup_info[$app]['autodeinstall'];
			if (!is_bool($autodeinstall))
			{
				try {
					$autodeinstall = (bool)$GLOBALS['egw_setup']->db->query($autodeinstall, __LINE__, __FILE__)->fetchColumn();
				}
				catch (\Exception $e) {
					_egw_log_exception($e);
					$autodeinstall = false;
				}
			}
			return $autodeinstall;
		}));
		//error_log(__METHOD__."() apps=".json_encode(array_keys($setup_info)).' returning '.json_encode($ret));
		return $ret;
	}

	/**
	 * Echo the given message, if $verbose
	 *
	 * @param boolean $verbose
	 * @param string $msg
	 * @return string $msg
	 */
	static function _echo_message($verbose,$msg='')
	{
		if ($verbose) echo $msg."\n";

		return $msg;
	}
}
