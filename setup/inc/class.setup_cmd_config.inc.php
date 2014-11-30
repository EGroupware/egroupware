<?php
/**
 * eGgroupWare setup - create / change eGW configuration
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package setup
 * @copyright (c) 2007-14 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * setup command: create / change eGW configuration
 */
class setup_cmd_config extends setup_cmd
{
	/**
	 * Allow to run this command via setup-cli
	 */
	const SETUP_CLI_CALLABLE = true;

	/**
	 * Constructor
	 *
	 * @param string $domain string with domain-name or array with all arguments
	 * @param string $config_user =null user to config the domain (or header_admin_user)
	 * @param string $config_passwd =null pw of above user
	 * @param string $arguments =null array with command line argruments
	 * @param boolean $verbose =false if true, echos out some status information during the run
	 */
	function __construct($domain,$config_user=null,$config_passwd=null,$arguments=null,$verbose=false)
	{
		if (!is_array($domain))
		{
			$domain = array(
				'domain'        => $domain,
				'config_user'   => $config_user,
				'config_passwd' => $config_passwd,
				'arguments'     => $arguments,
				'verbose'       => $verbose,
			);
		}
		//echo __CLASS__.'::__construct()'; _debug_array($domain);
		admin_cmd::__construct($domain);
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
		if ($check_only && $this->remote_id)
		{
			return true;	// can only check locally
		}
		// instanciate setup object and check authorisation
		$this->check_setup_auth($this->config_user,$this->config_passwd,$this->domain);

		$this->check_installed($this->domain,15,$this->verbose);

		// fixing authtypes in self::$options
		self::auth_types(true);

		$values = array();
		if ($this->arguments)	// we have command line arguments
		{
			$save_mail_account = $this->_parse_cli_arguments($values);
		}
		else
		{
			$save_mail_account = $this->_parse_properties($values);
		}

		// store the config
		foreach($values as $name => $value)
		{
			if (substr($name, 0, 4) == 'acc_') continue;

			$app = 'phpgwapi';
			if (strpos($name, '/') !== false)
			{
				list($app, $name) = explode('/', $name);
			}
			self::$egw_setup->db->insert(self::$egw_setup->config_table,array(
				'config_value' => $value,
			),array(
				'config_app'  => $app,
				'config_name' => $name,
			),__LINE__,__FILE__);
		}
		if (count($values))
		{
			if ($save_mail_account) $this->_save_mail_account($values);

			// flush instance cache, so above config get read from database not cache
			egw_cache::flush();

			$this->restore_db();

			return lang('Configuration changed.');
		}
		$this->restore_db();

		return lang('Nothing to change.');
	}

	/**
	 * Return or echo the most common config options
	 *
	 * @param boolean $echoit =false if true the config is additionally echo'ed out
	 * @return array with name => value pairs
	 */
	static function get_config($echoit=false)
	{
		self::$egw_setup->db->select(self::$egw_setup->config_table,'config_name,config_value',array(
			'config_app'  => 'phpgwapi',
			"(config_name LIKE '%\\_dir' OR (config_name LIKE 'mail%' AND config_name != 'mail_footer') OR config_name LIKE 'smtp\\_%' OR config_name LIKE 'ldap%' OR config_name IN ('webserver_url','system_charset','auth_type','account_repository'))",
		),__LINE__,__FILE__);

		$config = array();
		while (($row = self::$egw_setup->db->row(true)))
		{
			$config[$row['config_name']] = $row['config_value'];
		}
		if ($echoit)
		{
			echo lang('Current configuration:')."\n";
			foreach($config as $name => $value)
			{
				echo str_pad($name.':',22).$value."\n";
			}
		}
		return $config;
	}

	/**
	 * Available options and allowed arguments
	 *
	 * @var array
	 */
	static $options = array(
		'--config'     => array(),	// name=value,...
		'--files-dir'  => 'files_dir',
		'--vfs-root-user' => 'vfs_root_user',
		'--backup-dir' => 'backup_dir',
		'--temp-dir'   => 'temp_dir',
		'--webserver-url' => 'webserver_url',
		// mail must NOT have any default, as it causes to store a mail profile!
		'--mailserver' => array(	//server,{IMAP|IMAPS},[domain],[{standard(default)|vmailmgr = add domain for mailserver login|email = use email of user (Standard Maildomain should be set)}]
			'acc_imap_host',
			'acc_imap_port',
			'acc_domain',
			array('name' => 'acc_imap_logintype','allowed'  => array(
				'username (standard)' => 'standard',
				'username@domain (virtual mail manager)' => 'vmailmgr',
				'Username/Password defined by admin' => 'admin',
				'userId@domain eg. u123@domain' => 'uidNumber',
				'email (Standard Maildomain should be set)' => 'email',
			)),
			array('name' => 'acc_imap_ssl','allowed' => array(0,'no',1,'starttls',3,'ssl',2,'tls')),
		),
		'--imap' => array(
			'acc_imap_admin_username',
			'acc_imap_admin_password',
			'acc_imap_type',
		),
		'--folder' => array(
			'acc_folder_sent','acc_folder_trash','acc_folder_draft','acc_folder_template','acc_folder_junk',
		),
		'--sieve' => array(
			array('name' => 'acc_sieve_host'),
			'acc_sieve_port',
			'acc_sieve_enabled',
			array('name' => 'acc_sieve_ssl','allowed' => array(0,'no',1,'starttls',3,'ssl',2,'tls')),
		),
		'--smtp' => array(
			array('name' => 'editforwardingaddress','allowed' => array('yes',null)),
			'acc_smtp_type',
		),
		'--smtpserver' => array(	//smtp server,[smtp port],[smtp user],[smtp password],[auth session user/pw],[no|starttls|ssl|tls],[user editable],[further identities]
			'acc_smtp_host','acc_smtp_port','acc_smtp_username','acc_smtp_passwd','acc_smtp_auth_session',
			array('name' => 'acc_smtp_ssl','allowed' => array(0,'no',1,'starttls',3,'ssl',2,'tls')),
			'acc_user_editable','acc_further_identities',
		),
		'--account-auth' => array(
			array('name' => 'account_repository','allowed' => array('sql','ldap','ads'),'default'=>'sql'),
			array('name' => 'auth_type','allowed' => array('sql','ldap','mail','ads','http','sqlssl','nis','pam'),'default'=>'sql'),
			array('name' => 'sql_encryption','allowed' => array('blowfish_crypt','sha512_crypt','sha256_crypt','md5_crypt','crypt','ssha','smd5','md5'),'default'=>'blowfish_crypt'),
			'check_save_password','allow_cookie_auth'),
		'--ldap-host' => 'ldap_host',
		'--ldap-root-dn' => 'ldap_root_dn',
		'--ldap-root-pw' => 'ldap_root_pw',
		'--ldap-context' => 'ldap_context',
		'--ldap-search-filter' => 'ldap_search_filter',
		'--ldap-group-context' => 'ldap_group_context',
		'--sambaadmin-sid' => 'sambaadmin/sambaSID',
		'--allow-remote-admin' => 'allow_remote_admin',
		'--install-id' => 'install_id',
		'--ads-host' => 'ads_host',
		'--ads-domain' => 'ads_domain',
		'--ads-admin-user' => 'ads_domain_admin',	// eg. Administrator
		'--ads-admin-pw' => 'ads_admin_pw',
		'--ads-connection' => array(
			array('name' => 'ads_connection', 'allowed' => array('ssl', 'tls'))
		),
		'--ads-context' => 'ads_context',
	);
	/**
	 * Translate old EMailAdmin profile name to new mail account names
	 *
	 * @var array
	 */
	var $old2new = array(
		'mail_server' => 'acc_imap_host',
		'mail_server_type' => 'acc_imap_port',
		'mail_suffix' => 'acc_domain',
		'mail_login_type' => 'acc_imap_logintype',
		'imapAdminUsername' => 'acc_imap_admin_username',
		'imapAdminPW' => 'acc_imap_admin_password',
		'imapType' => 'acc_imap_type',
		'imapTLSEncryption' => 'acc_imap_ssl',
		'imapSieveServer' => 'acc_sieve_host',
		'imapSievePort' => 'acc_sieve_port',
		'imapEnableSieve' => 'acc_sieve_enabled',
		'editforwardingaddress' => null,
		'smtpType' => 'acc_smtp_type',
		'smtp_server' => 'acc_smtp_host',
		'smtp_port' => 'acc_smtp_port',
		'smtp_auth_user' => 'acc_smtp_username',
		'smtp_auth_passwd' => 'acc_smtp_password',
		'smtpAuth' => null,
		'organisationName' => 'ident_org',
	);

	/**
	 * Parses properties from this object
	 *
	 * @param array &$value contains set values on return
	 * @return boolean do we need to save a mail account
	 */
	private function _parse_properties(&$values)
	{
		$this->_merge_defaults();

		$save_mail_account = false;
		$values = array();
		foreach(self::$options as $arg => $option)
		{
			foreach(is_array($option) ? $option : array($option) as $n => $data)
			{
				$name = is_array($data) ? $data['name'] : $data;
				$oldname = array_key_exists($name, $this->old2new);

				if (!isset($this->$name) && $oldname && isset($this->$oldname))
				{
					$this->$name = $this->$oldname;
					unset($this->$oldname);
				}
				if (isset($this->$name))
				{
					$save_mail_account = $this->_parse_value($arg,$n,$option,$this->$name,$values) || $save_mail_account;
				}
			}
		}
		return $save_mail_account;
	}

	/**
	 * Parses command line arguments in $this->arguments
	 *
	 * @param array &$value contains set values on return
	 * @return boolean do we need to save a mail account
	 */
	private function _parse_cli_arguments(&$values)
	{
		$values = array();
		$save_mail_account = false;
		$args = $this->arguments;
		while(($arg = array_shift($args)))
		{
			if (!isset(self::$options[$arg]))
			{
				throw new egw_exception_wrong_userinput(lang("Unknown option '%1' !!!",$arg),90);
			}
			$options = is_array(self::$options[$arg]) ? explode(',',array_shift($args)) : array(array_shift($args));

			if ($arg == '--config' || $arg == '--setup_cmd_config')
			{
				foreach($options as $option)
				{
					list($name,$value) = explode('=',$option,2);
					$values[$name] = $value;
				}
				continue;
			}
			$options[] = ''; $options[] = '';
			foreach($options as $n => $value)
			{
				$save_mail_account = $this->_parse_value($arg,$n,self::$options[$arg],$value,$values) || $save_mail_account;
			}
		}
		return $save_mail_account;
	}

	/**
	 * Parses a single value
	 *
	 * @param string $arg current cli argument processed
	 * @param int $n number of the property
	 * @param array|string $data string with type or array containing values for type, allowed
	 * @param mixed $value value to set
	 * @param array &$values where the values get set
	 * @return boolean true if mail-accounts needs to be saved, false if not
	 */
	private function _parse_value($arg,$n,$data,$value,array &$values)
	{
		if ($value === '' && is_array($data) && !isset($data[$n]['default'])) return false;

		$name = is_array($data) || $n ? $data[$n] : $data;

		if (is_array($name))
		{
			if (!$value && isset($name['default'])) $value = $name['default'];

			if (isset($name['allowed']) && !in_array($value,$name['allowed']))
			{
				throw new egw_exception_wrong_userinput(lang("'%1' is not allowed as %2. arguments of option %3 !!!",$value,1+$n,$arg)." ($name[name])",91);
			}
			$name = $name['name'];
		}
		$values[$name] = $value;

		return in_array($arg,array('--mailserver','--smtpserver','--imap','--smtp','--sieve','--folder'));
	}

	/**
	 * Add new new mail account
	 *
	 * @param array $data
	 */
	function _save_mail_account(array $data)
	{
		// convert ssl textual values to nummerical ones used in emailadmin_account
		foreach(array('acc_imap_ssl', 'acc_sieve_ssl', 'acc_smtp_ssl') as $name)
		{
			switch(strtolower($data[$name]))
			{
				case 'no':       $data[$name] = emailadmin_account::SSL_NONE; break;
				case 'starttls': $data[$name] = emailadmin_account::SSL_STARTTLS; break;
				case 'ssl':      $data[$name] = emailadmin_account::SSL_SSL; break;
				case 'tls':      $data[$name] = emailadmin_account::SSL_TLS; break;
			}
		}
		// convert 'yes', 'no' to boolean
		foreach(array('acc_sieve_enabled','acc_user_editable','acc_further_identities','acc_smtp_auth_session') as $name)
		{
			$data[$name] = $data[$name] && strtolower($data[$name]) != 'no';
		}
		// do NOT write empty usernames
		foreach(array('acc_imap_username', 'acc_smtp_username') as $name)
		{
			if (empty($data[$name]))
			{
				unset($data[$name]);
				unset($data[str_replace('username', 'password', $name)]);
			}
		}

		$data['acc_name'] = 'Created by setup';
		$data['account_id'] = 0;	// 0 = valid for all users

		emailadmin_account::write($data);

		if ($this->verbose)
		{
			echo "\n".lang('EMailAdmin mail account saved:')."\n";
			foreach($data as $name => $value)
			{
				echo str_pad($name.':',22).$value."\n";
			}
		}
	}

	/**
	 * Return the options from the $options array
	 *
	 * @return array with name => array(value=>label,...) pairs
	 */
	static function options()
	{
		$options = array();
		foreach(self::$options as $option)
		{
			if (is_array($option))
			{
				foreach($option as $data)
				{
					if (is_array($data) && isset($data['allowed']))
					{
						switch ($data['name'])
						{
							case 'auth_type':
								$options[$data['name']] = self::auth_types();
								continue 2;
							case 'account_repository':
								$options[$data['name']] = self::account_repositories();
								continue 2;
						}
						foreach($data['allowed'] as $label => $value)
						{
							if (is_int($label))
							{
								$label = (string) $value === '' ? 'No' : strtoupper($value);
							}
							$options[$data['name']][$value] = lang($label);
						}
					}
				}
			}
		}
		return $options;
	}

	/**
	 * Read auth-types (existing auth backends) from filesystem and fix our $options array
	 *
	 * @return array
	 */
	static function auth_types()
	{
		// default backends in order of importance
		static $auth_types = array(
			'sql'  => 'SQL',
			'ldap' => 'LDAP',
			'mail' => 'Mail',
			'ads'  => 'Active Directory',
			'http' => 'HTTP',
			'fallback' => 'Fallback LDAP --> SQL',
			'fallbackmail2sql' => 'Fallback Mail --> SQL',
			'sqlssl' => 'SQL / SSL',
		);
		static $scan_done = null;
		if (!$scan_done++)
		{
			// now add auth backends found in filesystem
			foreach(scandir(EGW_INCLUDE_ROOT.'/phpgwapi/inc') as $class)
			{
				$matches = null;
				if (preg_match('/^class\.auth_([a-z]+)\.inc\.php$/', $class, $matches) &&
					!isset($auth_types[$matches[1]]))
				{
					$auth_types[$matches[1]] = ucfirst($matches[1]);
				}
			}
			foreach(self::$options['--account-auth'] as &$param)
			{
				if ($param['name'] == 'auth_type')
				{
					$param['allowed'] = array_keys($auth_types);
					break;
				}
			}
		}
		return $auth_types;
	}

	/**
	 * Read auth-types (existing auth backends) from filesystem and fix our $options array
	 *
	 * @param string $current =null current value, to allways return it
	 * @return array
	 */
	static function account_repositories($current=null)
	{
		static $account_repositories = array(
			'sql' => 'SQL',
			'ldap' => 'LDAP',
			'ads'  => 'Active Directory',
		);
		static $scan_done = null;
		if (!$scan_done++)
		{
			// now add auth backends found in filesystem
			foreach(scandir(EGW_INCLUDE_ROOT.'/phpgwapi/inc') as $file)
			{
				$matches = null;
				if (preg_match('/^class\.accounts_([a-z]+)\.inc\.php$/', $file, $matches) &&
					!isset($account_repositories[$matches[1]]) &&
					class_exists($class='accounts_'.$matches[1]) &&
					($matches[1] == $current || !is_callable($callable=$class.'::available') || call_user_func($callable)))
				{
					$account_repositories[$matches[1]] = ucfirst($matches[1]);
				}
			}
		}
		return $account_repositories;
	}

	/**
	 * Return the defaults from the $options array
	 *
	 * @return array with name => $value pairs
	 */
	static function defaults()
	{
		$defaults = array();
		// fetch the default from the cli options
		foreach(self::$options as $option)
		{
			if (is_array($option))
			{
				foreach($option as $data)
				{
					if (is_array($data) && isset($data['default']))
					{
						$defaults[$data['name']] = $data['default'];
					}
				}
			}
		}
		// some extra defaults for non-cli operation
		$defaults['files_dir'] = '/var/lib/egroupware/$domain/files';
		$defaults['backup_dir'] = '/var/lib/egroupware/$domain/backup';
		$defaults['backup_mincount'] = 0;
		$defaults['backup_files'] = false;
		$defaults['temp_dir'] = '/tmp';
		$defaults['webserver_url'] = '/egroupware';
		// no more mail defaults, to not create a (2.) mail account during setup!

		return $defaults;
	}

	/**
	 * Merges the default into the current properties, if they are empty or contain placeholders
	 *
	 * Replacements like $domain, only work for values listed by self::defaults()
	 */
	private function _merge_defaults()
	{
		foreach(self::defaults() as $name => $default)
		{
			if (!$this->$name)
			{
				//echo "<p>setting $name='{$this->$name}' to it's default='$default'</p>\n";
				$this->set_defaults[$name] = $this->$name = $default;
			}
			if (strpos($this->$name,'$') !== false)
			{
				$this->set_defaults[$name] = $this->$name = str_replace(array(
					'$domain',
				),array(
					$this->domain,
				),$this->$name);
			}
		}
	}
}
