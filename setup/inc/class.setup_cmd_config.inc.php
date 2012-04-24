<?php
/**
 * eGgroupWare setup - create / change eGW configuration
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package setup
 * @copyright (c) 2007-10 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * setup command: create / change eGW configuration
 */
class setup_cmd_config extends setup_cmd
{
	/**
	 * Constructor
	 *
	 * @param string $domain string with domain-name or array with all arguments
	 * @param string $config_user=null user to config the domain (or header_admin_user)
	 * @param string $config_passwd=null pw of above user
	 * @param string $arguments=null array with command line argruments
	 * @param boolean $verbose=false if true, echos out some status information during the run
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
	 * @param boolean $check_only=false only run the checks (and throw the exceptions), but not the command itself
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
			$save_ea_profile = $this->_parse_cli_arguments($values);
		}
		else
		{
			$save_ea_profile = $this->_parse_properties($values);
		}

		// store the config
		foreach($values as $name => $value)
		{
			self::$egw_setup->db->insert(self::$egw_setup->config_table,array(
				'config_value' => $value,
			),array(
				'config_app'  => 'phpgwapi',
				'config_name' => $name,
			),__LINE__,__FILE__);
		}
		if (count($values))
		{
			if ($save_ea_profile) $this->_save_ea_profile();

			$this->restore_db();

			return lang('Configuration changed.');
		}
		$this->restore_db();

		return lang('Nothing to change.');
	}

	/**
	 * Return or echo the most common config options
	 *
	 * @param boolean $echoit=false if true the config is additionally echo'ed out
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
		'--mailserver' => array(	//server,{IMAP|IMAPS|POP|POPS},[domain],[{standard(default)|vmailmgr = add domain for mailserver login|email = use email of user (Standard Maildomain should be set)}]
			'mail_server',
			array('name' => 'mail_server_type','allowed' => array('imap','imaps'),'default'=>'imap'),
			'mail_suffix',
			array('name' => 'mail_login_type','allowed'  => array(
				'username (standard)' => 'standard',
				'username@domain (virtual mail manager)' => 'vmailmgr',
				'Username/Password defined by admin' => 'admin',
				'userId@domain eg. u123@domain' => 'uidNumber',
				'email (Standard Maildomain should be set)' => 'email',
			),'default'=>'standard'),
		),
		'--cyrus' => array(
			'imapAdminUsername',
			'imapAdminPW',
			array('name' => 'imapType','default' => 'cyrusimap'),
			array('name' => 'imapEnableCyrusAdmin','default' => 'yes'),
		),
		'--sieve' => array(
			array('name' => 'imapSieveServer'),
			array('name' => 'imapSievePort','default' => 2000),
			array('name' => 'imapEnableSieve','default' => 'yes'),	// null or yes
		),
		'--postfix' => array(
			array('name' => 'editforwardingaddress','allowed' => array('yes',null)),
			array('name' => 'smtpType','default' => 'postfixldap'),
		),
		'--smtpserver' => array(	//smtp server,[smtp port],[smtp user],[smtp password]
			'smtp_server',array('name' => 'smtp_port','default' => 25),'smtp_auth_user','smtp_auth_passwd',''
		),
		'--account-auth' => array(
			array('name' => 'account_repository','allowed' => array('sql','ldap'),'default'=>'sql'),
			array('name' => 'auth_type','allowed' => array('sql','ldap','mail','ads','http','sqlssl','nis','pam'),'default'=>'sql'),
			array('name' => 'sql_encryption','allowed' => array('sha512_crypt','sha256_crypt','blowfish_crypt','md5_crypt','crypt','ssha','smd5','md5'),'default'=>'sha512_crypt'),
			'check_save_password','allow_cookie_auth'),
		'--ldap-host' => 'ldap_host',
		'--ldap-root-dn' => 'ldap_root_dn',
		'--ldap-root-pw' => 'ldap_root_pw',
		'--ldap-context' => 'ldap_context',
		'--ldap-search-filter' => 'ldap_search_filter',
		'--ldap-group-context' => 'ldap_group_context',
		'--allow-remote-admin' => 'allow_remote_admin',
		'--install-id' => 'install_id',
	);

	/**
	 * Parses properties from this object
	 *
	 * @param array &$value contains set values on return
	 * @return boolean do we need to save the emailadmin profile
	 */
	private function _parse_properties(&$values)
	{
		$this->_merge_defaults();

		$save_ea_profile = false;
		$values = array();
		foreach(self::$options as $arg => $option)
		{
			foreach(is_array($option) ? $option : array($option) as $n => $data)
			{
				$name = is_array($data) ? $data['name'] : $data;

				if (isset($this->$name))
				{
					$save_ea_profile |= $this->_parse_value($arg,$n,$option,$this->$name,$values);
				}
			}
		}
		return $save_ea_profile;
	}

	/**
	 * Parses command line arguments in $this->arguments
	 *
	 * @param array &$value contains set values on return
	 * @return boolean do we need to save the emailadmin profile
	 */
	private function _parse_cli_arguments(&$values)
	{
		$arguments = $this->arguments;
		$values = array();
		$save_ea_profile = false;
		$args = $this->arguments;
		while(($arg = array_shift($args)))
		{
			if (!isset(self::$options[$arg]))
			{
				throw new egw_exception_wrong_userinput(lang("Unknown option '%1' !!!",$arg),90);
			}
			$options = is_array(self::$options[$arg]) ? explode(',',array_shift($args)) : array(array_shift($args));

			if ($arg == '--config')
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
				$save_ea_profile |= $this->_parse_value($arg,$n,self::$options[$arg],$value,$values);
			}
		}
		return $save_ea_profile;
	}

	/**
	 * Parses a single value
	 *
	 * @param string $arg current cli argument processed
	 * @param int $n number of the property
	 * @param array/string $data string with type or array containing values for type, allowed
	 * @param mixed $value value to set
	 * @param array &$values where the values get set
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

		return in_array($arg,array('--mailserver','--smtpserver','--cyrus','--postfix','--sieve'));
	}

	/**
	 * Updates the default EMailAdmin profile from the eGW config
	 */
	function _save_ea_profile($config=array())
	{
		self::$egw_setup->db->select(self::$egw_setup->config_table,'config_name,config_value',array(
			'config_app'  => 'phpgwapi',
			"((config_name LIKE 'mail%' AND config_name != 'mail_footer') OR config_name LIKE 'smtp%' OR config_name LIKE 'imap%' OR config_name='editforwardingaddress')",
		),__LINE__,__FILE__);
		while (($row = self::$egw_setup->db->row(true)))
		{
			$config[$row['config_name']] = $row['config_value'];
		}
		$config['smtpAuth'] = $config['smtp_auth_user'] ? 'yes' : null;

		$emailadmin = new emailadmin_bo(false,false);	// false=no session stuff
		$emailadmin->setDefaultProfile($config);

		if ($this->verbose)
		{
			echo "\n".lang('EMailAdmin profile updated:')."\n";
			foreach($config as $name => $value)
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
				foreach($option as $n => $data)
				{
					if (is_array($data) && isset($data['allowed']))
					{
						if ($data['name'] == 'auth_type')
						{
							$options[$data['name']] = self::auth_types();
							continue;
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
		static $scan_done;
		if (!$scan_done++)
		{
			// now add auth backends found in filesystem
			foreach(scandir(EGW_INCLUDE_ROOT.'/phpgwapi/inc') as $class)
			{
				if (preg_match('/^class\.auth_([a-z]+)\.inc\.php$/',$class,$matches) &&
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
				foreach($option as $n => $data)
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
		$defaults['smtp_server'] = 'localhost';
		//$defaults['mail_server'] = 'localhost';
		$defaults['mail_suffix'] = '$domain';
		$defaults['imapAdminUsername'] = 'cyrus@$domain';
		$defaults['imapAdminPW'] = self::randomstring();
		$defaults['imapType'] = 'defaultimap';	// standard IMAP
		$defaults['smtpType'] = 'defaultsmtp';	// standard SMTP

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
