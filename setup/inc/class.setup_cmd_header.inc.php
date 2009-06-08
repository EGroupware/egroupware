<?php
/**
 * eGgroupWare setup - create or update the header.inc.php
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package setup
 * @copyright (c) 2007/8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * setup command: create or update the header.inc.php
 *
 * @ToDo: incorporate setup_header here
 */
class setup_cmd_header extends setup_cmd
{
	/**
	 * Instance of setup's header object
	 *
	 * @var setup_header
	 */
	private $setup_header;
	/**
	 * Full path of the header.inc.php
	 *
	 * @var string
	 */
	private $header_path;

	/**
	 * Constructor
	 *
	 * @param string/array $sub_command='create' 'create','edit','delete'(-domain) or array with all arguments
	 * @param array $arguments=null comand line arguments
	 */
	function __construct($sub_command='create',$arguments=null)
	{
		if (!is_array($sub_command))
		{
			$sub_command = array(
				'sub_command' => $sub_command,
				'arguments'   => $arguments,
			);
		}
		//echo __CLASS__.'::__construct()'; _debug_array($domain);
		admin_cmd::__construct($sub_command);

		// header is 3 levels lower then this command in setup/inc
		$this->header_path = dirname(dirname(dirname(__FILE__))).'/header.inc.php';

		// if header is a symlink --> work on it's target
		if (is_link($this->header_path))
		{
			$this->header_path = readlink($this->header_path);
			if ($this->header_path[0] != '/' && $this->header_path[1] != ':')
			{
				$this->header_path = dirname(dirname(dirname(__FILE__))).'/'.$this->header_path;
			}
		}
		$this->setup_header = new setup_header();
	}

	/**
	 * Create or update header.inc.php
	 *
	 * @param boolean $check_only=false only run the checks (and throw the exceptions), but not the command itself
	 * @return string serialized $GLOBALS defined in the header.inc.php
	 * @throws Exception(lang('Wrong credentials to access the header.inc.php file!'),2);
	 * @throws Exception('header.inc.php not found!');
	 */
	protected function exec($check_only=false)
	{
		if ($check_only && $this->remote_id)
		{
			return true;	// can only check locally
		}
		if (!file_exists($this->header_path) || filesize($this->header_path) < 200)	// redirect header in rpms is ~150 byte
		{
			if ($this->sub_command != 'create')
			{
				throw new egw_exception_wrong_userinput(lang('eGroupWare configuration file (header.inc.php) does NOT exist.')."\n".lang('Use --create-header to create the configuration file (--usage gives more options).'),1);
			}
			$this->defaults(false);
		}
		else
		{
			if ($this->sub_command == 'create')
			{
				throw new egw_exception_wrong_userinput(
					lang('eGroupWare configuration file header.inc.php already exists, you need to use --edit-header or delete it first!'),20);
			}
			if ($this->arguments)
			{
				list($this->header_admin_password,$this->header_admin_user) = explode(',',$this->arguments[1]);
			}
			$this->check_setup_auth($this->header_admin_user,$this->header_admin_password);	// no domain, we require header access!

			$GLOBALS['egw_info']['server']['server_root'] = EGW_SERVER_ROOT;
			$GLOBALS['egw_info']['server']['include_root'] = EGW_INCLUDE_ROOT;
		}

		if ($this->arguments)	// we have command line arguments
		{
			$this->_parse_cli_arguments();
		}
		elseif ($this->sub_command == 'delete')
		{
			self::_delete_domain($this->domain);
		}
		else
		{
			$this->_parse_properties();
		}
		if (($errors = $this->validation_errors($GLOBALS['egw_info']['server']['server_root'],
			$GLOBALS['egw_info']['server']['include_root'])))
		{
			if ($this->arguments)
			{
				unset($GLOBALS['egw_info']['flags']);
				echo '$GLOBALS[egw_info] = '; print_r($GLOBALS['egw_info']);
				echo '$GLOBALS[egw_domain] = '; print_r($GLOBALS['egw_domain']);
			}
			throw new egw_exception_wrong_userinput(lang('Configuration errors:')."\n- ".implode("\n- ",$errors)."\n".lang("You need to fix the above errors, before the configuration file header.inc.php can be written!"),23);
		}
		if ($check_only)
		{
			return true;
		}
		$header = $this->generate($GLOBALS['egw_info'],$GLOBALS['egw_domain']);

		if ($this->arguments)
		{
			echo $header;	// for cli, we echo the header
		}
		if (file_exists($this->header_path) && is_writable($this->header_path) || is_writable(dirname($this->header_path)) ||
			function_exists('posix_getuid') && !posix_getuid())	// root has all rights
		{
			if (is_writable(dirname($this->header_path)) && file_exists($this->header_path)) unlink($this->header_path);
			if (($f = fopen($this->header_path,'wb')) && ($w=fwrite($f,$header)))
			{
				fclose($f);
				return lang('header.inc.php successful written.');
			}
		}
		throw new egw_exception_no_permission(lang("Failed writing configuration file header.inc.php, check the permissions !!!"),24);
	}

	/**
	 * Magic method to allow to call all methods from setup_header, as if they were our own
	 *
	 * @param string $method
	 * @param array $args=null
	 * @return mixed
	 */
	function __call($method,array $args=null)
	{
		if (method_exists($this->setup_header,$method))
		{
			return call_user_func_array(array($this->setup_header,$method),$args);
		}
	}

	/**
	 * Available options and allowed arguments
	 *
	 * @var array
	 */
	static $options = array(
		'--create-header' => array(
			'header_admin_password' => 'egw_info/server/',
			'header_admin_user' => 'egw_info/server/',
		),
		'--edit-header'   => array(
			'header_admin_password' => 'egw_info/server/',
			'header_admin_user' => 'egw_info/server/',
			'new_admin_password' => 'egw_info/server/header_admin_password',
			'new_admin_user' => 'egw_info/server/header_admin_user',
		),
		'--server-root'  => 'egw_info/server/server_root',
		'--include-root' => 'egw_info/server/include_root',
		'--session-type' => array(
			'sessions_type' => array(
				'type' => 'egw_info/server/',
				'allowed' => array('php'=>'php4','php4'=>'php4','php-restore'=>'php4-restore','php4-restore'=>'php4-restore','db'=>'db'),
			),
		),
		'--session-handler' => array(
			'session_handler' => array(
				'type' => 'egw_info/server/',
				'allowed' => array('files'=>'files','memcache'=>'memcache','db'=>'db'),
			),
		),
		'--limit-access' => 'egw_info/server/setup_acl',	// name used in setup
		'--setup-acl'    => 'egw_info/server/setup_acl',	// alias to match the real name
		'--mcrypt' => array(
			'mcrypt_enabled' => array(
				'type' => 'egw_info/server/',
				'allowed' => array('on' => true,'off' => false),
			),
			'mcrypt_iv' => 'egw_info/server/',
			'mcrypt' => 'egw_info/versions/mcrypt',
		),
		'--domain-selectbox' => array(
			'show_domain_selectbox' => array(
				'type' => 'egw_info/server/',
				'allowed' => array('on' => true,'off' => false),
			),
		),
		'--db-persistent' => array(
			'db_persistent' => array(
				'type' => 'egw_info/server/',
				'allowed' => array('on' => true,'off' => false),
			),
		),
		'--domain' => array(
			'domain' => '@',
			'db_name' => 'egw_domain/@/',
			'db_user' => 'egw_domain/@/',
			'db_pass' => 'egw_domain/@/',
			'db_type' => 'egw_domain/@/',
			'db_host' => 'egw_domain/@/',
			'db_port' => 'egw_domain/@/',
			'config_user'   => 'egw_domain/@/',
			'config_passwd' => 'egw_domain/@/',
		),
		'--delete-domain' => true,
	);

	/**
	 * Parses properties from this object
	 */
	private function _parse_properties()
	{
		foreach(self::$options as $arg => $option)
		{
			foreach(is_array($option) ? $option : array($option => $option) as $name => $data)
			{
				if (strpos($name,'/') !== false)
				{
					$name = array_pop($parts = explode('/',$name));
				}
				if (isset($this->$name))
				{
					$this->_parse_value($arg,$name,$data,$this->$name);
				}
			}
		}
	}

	/**
	 * Parses command line arguments in $this->arguments
	 */
	private function _parse_cli_arguments()
	{
		$arguments = $this->arguments;
		while(($arg = array_shift($arguments)))
		{
			$values = count($arguments) && substr($arguments[0],0,2) !== '--' ? array_shift($arguments) : 'on';

			if ($arg == '--delete-domain')
			{
				$this->_delete_domain($values);
				continue;
			}

			if (!isset(self::$options[$arg]))
			{
				throw new egw_exception_wrong_userinput(lang("Unknown option '%1' !!!",$arg),90);
			}

			$option = self::$options[$arg];
			$values = !is_array($option) ? array($values) : explode(',',$values);
			if (!is_array($option)) $option = array($option => $option);
			$n = 0;
			foreach($option as $name => $data)
			{
				if ($n >= count($values)) break;

				$this->_parse_value($arg,$name,$data,$values[$n++]);
			}
		}
	}

	/**
	 * Delete a given domain/instance from the header
	 *
	 * @param string $domain
	 */
	private static function _delete_domain($domain)
	{
		if (!isset($GLOBALS['egw_domain'][$domain]))
		{
			throw new egw_exception_wrong_userinput(lang("Domain '%1' does NOT exist !!!",$domain),92);
		}
		unset($GLOBALS['egw_domain'][$domain]);
	}

	/**
	 * Parses a single value
	 *
	 * @param string $arg current cli argument processed
	 * @param string $name name of the property
	 * @param array/string $data string with type or array containing values for type, allowed
	 * @param mixed $value value to set
	 */
	private function _parse_value($arg,$name,$data,$value)
	{
		static $domain;

		if (!is_array($data)) $data = array('type' => $data);
		$type = $data['type'];

		if (isset($data['allowed']))
		{
			if (!isset($data['allowed'][$value]))
			{
				throw new egw_exception_wrong_userinput(lang("'%1' is not allowed as %2. arguments of option %3 !!!",$value,1+$n,$arg),91);
			}
			$value = $data['allowed'][$value];
		}
		if ($type == '@')
		{
			$domain = $arg == '--domain' && !$value ? 'default' : $value;
			if ($arg == '--domain' && (!isset($GLOBALS['egw_domain'][$domain]) || $this->sub_command == 'create'))
			{
				$GLOBALS['egw_domain'][$domain] = $this->domain_defaults($GLOBALS['egw_info']['server']['header_admin_user'],$GLOBALS['egw_info']['server']['header_admin_password']);
			}
		}
		elseif ($value !== '')
		{
			self::_set_value($GLOBALS,str_replace('@',$domain,$type),$name,$value);
			if ($type == 'egw_info/server/server_root')
			{
				self::_set_value($GLOBALS,'egw_info/server/include_root',$name,$value);
			}
		}
	}

	/**
	 * Set a value in the given array $arr with (multidimensional) key $index[/$name]
	 *
	 * @param array &$arr
	 * @param string $index multidimensional index written with / as separator, eg. egw_info/server/
	 * @param string $name additional index to use if $index end with a slash
	 * @param mixed $value value to set
	 */
	static private function _set_value(&$arr,$index,$name,$value)
	{
		if (substr($index,-1) == '/') $index .= $name;

		$var =& $arr;
		foreach(explode('/',$index) as $name)
		{
			$var =& $var[$name];
		}
		$var = strpos($name,'passw') !== false ? md5($value) : $value;
	}
}
