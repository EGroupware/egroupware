<?php
/**
 * EGroupware Setup - Manage the EGw config file header.inc.php
 *
 * @link http://www.egroupware.org
 * @package setup
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Miles Lott <milos@groupwhere.org>
 * @author Tony Puglisi (Angles)
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use EGroupware\Api\Framework;

/**
 * Functions to manage the EGw config file header.inc.php
 *
 * Used by manageheader.php and the new setup command line interface setup-cli.php
 *
 * @package setup
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
class setup_header
{
	/**
	 * @var array with php-extension / ADOdb drive names => describtiv label
	 */
	var $db_fullnames = array(
		'mysqli' => 'MySQLi (recommended, incl. transactions)',
		'mysql'  => 'MySQL (deprecated)',
		'mysqlt' => 'MySQL (deprecated, transactions)',
		'pgsql'  => 'PostgreSQL',
		'mssql'  => 'MS SQL Server',
		'odbc_mssql'  => 'MS SQL Server via ODBC',
		'oracle' => 'Oracle',
		'odbc_oracle' => 'Oracle via ODBC',
		'sapdb'  => 'SAP/Max DB via ODBC',
	);

	/**
	 * @var array with php-extension / ADOdb drive names => default port used by database
	 */
	var $default_db_ports = array(
		'pgsql'  => 5432,
		'mysql'  => 3306,
		'mysqli' => 3306,
		'mysqlt' => 3306,
		'mssql'  => 1433,
		'odbc_mssql'  => '',
		'oracle' => 1521,
		'odbc_oracle' => '',
		'sapdb'  => '',
	);

	/**
	 * Detect settings or set defaults for the header.inc.php file (used if it does not yet exist)
	 *
	 * Sets $GLOBALS['egw_info'], $GLOBALS['egw_domains'] and the defines EGW_SERVER_ROOT and EGW_INCLUDE_ROOT,
	 * as if the header has been included
	 *
	 * @param string $domain ='default' domain to set
	 */
	function defaults($domain='default')
	{
		$egw_root = realpath(__DIR__.'/../..');
		$GLOBALS['egw_info']['server']['server_root'] = $GLOBALS['egw_info']['server']['include_root'] = $egw_root;
		define('EGW_SERVER_ROOT',$egw_root);	// this is usally already defined by setup and cant be changed
		define('EGW_INCLUDE_ROOT',$egw_root);

		$GLOBALS['egw_info']['server']['header_admin_user'] = 'admin';
		$GLOBALS['egw_info']['server']['header_admin_password'] = '';
		$GLOBALS['egw_info']['server']['setup_acl'] = '';

		if ($domain) $GLOBALS['egw_domain'][$domain] = $this->domain_defaults();

		$GLOBALS['egw_info']['server']['show_domain_selectbox'] = false;
		$GLOBALS['egw_info']['server']['db_persistent'] = True;
		$GLOBALS['egw_info']['login_template_set'] = 'default';
		$GLOBALS['egw_info']['server']['mcrypt_enabled'] = False;
		$GLOBALS['egw_info']['server']['versions']['mcrypt'] = '';
		$GLOBALS['egw_info']['server']['mcrypt_iv'] = $this->generate_mcyrpt_iv();
	}

	function domain_defaults($user='admin',$passwd='',$supported_db=null)
	{
		$null = null;
		if (is_null($supported_db)) $supported_db = $this->check_db_support($null);
		$default_db = count($supported_db) ? $supported_db[0] : 'mysqli';

		return array(
			'db_host' => 'localhost',
			'db_port' => $this->default_db_ports[$default_db],
			'db_name' => 'egroupware',
			'db_user' => 'egroupware',
			'db_pass' => '',
			'db_type' => $default_db,
			'config_user'   => $user,
			'config_passwd' => $passwd,
		);
	}

	/**
	 * Checks the values of the (included) header.inc.php file
	 *
	 * The values are set in $GLOBALS['egw_info'], $GLOBALS['egw_domain'] and EGW_SERVER_ROOT
	 *
	 * @return array with errors or null if no errors
	 */
	function validation_errors($path=EGW_SERVER_ROOT)
	{
		$errors = null;

		if (!is_dir($path) || !is_readable($path) || !is_dir($path.'/api'))
		{
			$errors[] = lang("%1 '%2' does NOT exist, is not readable by the webserver or contains no EGroupware installation!",lang('Server root'),$path);
		}
		if(!$GLOBALS['egw_info']['server']['header_admin_password'])
		{
			$errors[] = lang("You didn't enter a header admin password");
		}
		if(!$GLOBALS['egw_info']['server']['header_admin_user'])
		{
			$errors[] = lang("You didn't enter a header admin username");
		}
		if (!is_array($GLOBALS['egw_domain']) || !count($GLOBALS['egw_domain']))
		{
			$errors[] = lang('You need to add at least one EGroupware domain / database instance.');
		}
		else
		{
			foreach($GLOBALS['egw_domain'] as $domain => $data)
			{
				if (!$data['config_passwd'])
				{
					$errors[] = lang("You didn't enter a config password for domain %1",$domain);
				}
				if(!$data['config_user'])
				{
					$errors[] = lang("You didn't enter a config username for domain %1",$domain);
				}
			}
		}
		return $errors;
	}

	/**
	 * Check if any domain using mysql(i) gives a warning about disabled persistent connections
	 *
	 * @param array $egw_domains
	 * @param boolean $persistent =true current value
	 * @return boolean
	 */
	function check_db_persistent(array $egw_domains, $persistent=true)
	{
		if ($persistent !== false)
		{
			foreach($egw_domains as $data)
			{
				// check if persistent connections are allowed
				if (substr($data['db_type'], 0, 5) === 'mysql' && !ini_get('mysqli.allow_persistent'))
				{
					$persistent = false;
					break;
				}
			}
		}
		return $persistent;
	}

	/**
	 * generate header.inc.php file from given values
	 *
	 * setup_header::generate($GLOBALS['egw_info'],$GLOBALS['egw_domains'])
	 * should write an identical header.inc.php as the one include
	 *
	 * @param array $egw_info usual content (in server key) plus keys server_root and include_root
	 * @param array $egw_domain info about the existing EGw domains / DB instances
	 * @return string content of header.inc.php
	 */
	function generate($egw_info,$egw_domain)
	{
		$tpl = new Framework\Template('../', 'keep');	// 'keep' to not loose '{hash}' prefix of password-hashes!
		$tpl->set_file(array('header' => 'header.inc.php.template'));
		$tpl->set_block('header','domain','domain');

		$most_secure_pw_hash = null;
		Api\Auth::passwdhashes($most_secure_pw_hash);

		foreach($egw_domain as $domain => $data)
		{
			$var = array('DB_DOMAIN' => $domain);
			foreach($data as $name => $value)
			{
				if ($name == 'db_port' && !$value) $value = $this->default_db_ports[$data['db_type']];
				if ($name == 'config_passwd')
				{
					$var['CONFIG_PASS'] = self::is_hashed($value) ? $value : Api\Auth::encrypt_sql($value, $most_secure_pw_hash);
				}
				else
				{
					$var[strtoupper($name)] = addslashes($value);
				}
			}
			$tpl->set_var($var);
			$tpl->parse('domains','domain',True);
		}
		$tpl->set_var('domain','');

		$var = Array();
		foreach($egw_info['server'] as $name => $value)
		{
			if ($name == 'header_admin_password' && $value && !self::is_hashed($value))
			{
				$value = Api\Auth::encrypt_sql($value, $most_secure_pw_hash);
			}
			if ($name == 'versions')
			{
				$name = 'mcrypt_version';
				$value = $value['mcrypt'];
			}
			static $bools = array(
				'mcrypt_enabled' => 'ENABLE_MCRYPT',
				'db_persistent'  => 'db_persistent',
				'show_domain_selectbox' => 'DOMAIN_SELECTBOX',
			);
			if (isset($bools[$name]))
			{
				$name = $bools[$name];
				$value = $value ? 'true' : 'false';
			}
			$var[strtoupper($name)] = addslashes($value);
		}
		$tpl->set_var($var);

		return $tpl->parse('out','header');
	}

	/**
	 * Generate a random mcrypt_iv vector
	 *
	 * @return string
	 */
	function generate_mcyrpt_iv()
	{
		/*$mcrypt = mcrypt_module_open(Api\Session::MCRYPT_ALGO, '', Api\Session::MCRYPT_MODE, '');
		$size = mcrypt_enc_get_iv_size($mcrypt);
		if (function_exists('mcrypt_create_iv'))	// PHP 5.3+
		{
			$iv = mcrypt_create_iv($size, MCRYPT_DEV_URANDOM);
			error_log(__METHOD__."() size=$size returning ".array2string($iv));
			return $iv;
		}*/
		$size = 30;
		srand((double)microtime()*1000000);
		$random_char = array(
			'0','1','2','3','4','5','6','7','8','9','a','b','c','d','e','f',
			'g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v',
			'w','x','y','z','A','B','C','D','E','F','G','H','I','J','K','L',
			'M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'
		);

		$iv = '';
		for($i=0; $i < $size; $i++)
		{
			$iv .= $random_char[rand(1,count($random_char))];
		}
		//error_log(__METHOD__."() size=$size returning ".array2string($iv));
		return $iv;
	}

	function check_db_support(&$detected)
	{
		$supported_db = $detected = array();
		foreach(array(
			// short => array(extension,func_to_check,supported_db(s))
			'mysqli' => array('mysql','mysqli_connect','mysqli'),
			'mysql'  => array('mysql','mysql_connect','mysql'),
			'mysqlt' => array('mysql','mysql_connect','mysqlt'),
			'pgsql'  => array('pgsql','pg_connect','pgsql'),
			'mssql'  => array('mssql','mssql_connect','mssql'),
			'odbc'   => array('odbc',false,'sapdb','odbc_mssql','odbc_oracle'),
			'oracle' => array('oci8',false,'oracle'),
		) as $db => $data)
		{
			$ext = array_shift($data);
			$func_to_check = array_shift($data);
			$name = isset($this->db_fullnames[$db]) ? $this->db_fullnames[$db] : strtoupper($db);
			if (check_load_extension($ext) || $func_to_check && function_exists($func_to_check))
			{
				$detected[] = lang('You appear to have %1 support.',$name);
				$supported_db = array_merge($supported_db,$data);
			}
			else
			{
				$detected[] .= lang('No %1 support found. Disabling',$name);
			}
		}
		return $supported_db;
	}

	/**
	 * Check if pw is hashed
	 *
	 * @param string $pw
	 * @return boolean
	 */
	static function is_hashed($pw)
	{
		$ret = $pw[0] == '{' || preg_match('/^[0-9a-f]{32}$/', $pw);
		//error_log(__METHOD__."('$pw') returning ".array2string($ret));
		return $ret;
	}
}

// some constanst for pre php4.3
if (!defined('PHP_SHLIB_SUFFIX'))
{
	define('PHP_SHLIB_SUFFIX',strtoupper(substr(PHP_OS, 0,3)) == 'WIN' ? 'dll' : 'so');
}
if (!defined('PHP_SHLIB_PREFIX'))
{
	define('PHP_SHLIB_PREFIX',PHP_SHLIB_SUFFIX == 'dll' ? 'php_' : '');
}
