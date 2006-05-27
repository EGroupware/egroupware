#!/usr/bin/php
<?php
/**************************************************************************\
* eGroupWare - Setup - Command line interface                              *
* http://www.egroupware.org                                                *
* Written and (c) 2006 by  Ralf Becker <RalfBecker-AT-outdoor-training.de> *
* ------------------------------------------------------------------------ *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id: class.socontacts_sql.inc.php 21634 2006-05-24 02:28:57Z ralfbecker $ */

/**
 * Command line interface for setup
 *
 * @package addressbook
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2006 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

chdir(dirname(__FILE__));	// to enable our relative pathes to work, if we are called with a path

if ($_SERVER['argc'] > 1)
{
	$arguments = $_SERVER['argv'];
	array_shift($arguments);
	$action = array_shift($arguments);
}
else
{
	$action = '--version';
}

if ((float) PHP_VERSION < '4.3')
{
	fail(98,lang2('You are using PHP version %1. eGroupWare now requires %2 or later, recommended is PHP %3.',PHP_VERSION,'4.3','5+'));
}

switch($action)
{
	case '--show-languages':
	case '--show-lang':
		echo html_entity_decode(file_get_contents('lang/languages'),ENT_COMPAT,'utf-8');
		break;
	
	case '--version':
	case '--check':
		do_check();
		break;
		
	case '--create-header':
	case '--edit-header':
	case '--update-header':
		do_header($action == '--create-header',$arguments);
		break;
		
	case '--exit-codes':
		list_exit_codes();
		break;
		
	default:
		fail(20,lang2("Unknows option '%1' !!!",$action));

	case '--help':
	case '--usage':
		do_usage();
		break;
}
exit(0);

/**
 * Dummy translation function, if we ever want to translate the command line interface
 *
 * @param string $message the message with %# replacements
 * @param string $arg variable number of arguments
 * @return string
 */
function lang2($message,$arg=null)
{
	$args = func_get_args();
	array_shift($args);
	
	return str_replace(array('%1','%2','%3','%4','%5'),$args,$message);
}

/**
 * Echos usage message
 */
function do_usage()
{
	echo lang2('Usage: %1 {--version|--create-header|--edit-header|--install|--update} [additional options]',basename($_SERVER['argv'][0]))."\n\n";
	
	echo '--install '.lang2('[comma-separated languages(en)],[charset(default depending on languages default)],[backup to install]')."\n";
	echo '--show-languages '.lang2('get a list of availible languages')."\n";
	echo '--update '.lang2('run a database schema update (if necessary)')."\n";
	echo '--check '.lang2('checks eGroupWare\'s installed, it\'s versions and necessary upgrads (exits 0: eGW is up to date, 1: no header.inc.php exists, 2: header update necessary, 3: database schema update necessary)')."\n";
	echo '--exit-codes '.lang2('list all exist codes of the command line interface, 0 means Ok')."\n";

	echo "\n".lang2('Create or edit the eGroupWare configuration file: header.inc.php:')."\n";
	echo '--create-header '.lang2('header-password[,header-user(admin)]')."\n";
	echo '--edit-header '.lang2('[header-password],[header-user],[new-password],[new-user]')."\n";

	echo "\n".lang2('Additional options and there defaults (int brackets)')."\n";
	echo '--server-root '.lang2('path of eGroupWare install directory (default auto-detected)')."\n";
	echo '--session-type '.lang2('{db|php(default)|php-restore}')."\n";
	echo '--limit-access '.lang2('comma separated ip-addresses or host-names, default access to setup from everywhere')."\n";
	echo '--mcrypt '.lang2('use mcrypt to crypt session-data: {off(default)|on},[mcrypt-init-vector(default randomly generated)],[mcrypt-version(default empty = recent)]')."\n";
	echo '--db-persistent '.lang2('use persistent db connections: {on(default)|off}')."\n";
	echo '--domain-selectbox '.lang2('{off(default)|on}')."\n";

	echo "\n".lang2('Adding, editing or deleting an eGroupWare domain / database instance:')."\n";
	echo '--domain '.lang2('add or edit a domain: [domain-name(default)],[db-name(egroupware)],[db-user(egroupware)],db-password,[db-type(mysql)],[db-host(localhost)],[db-port(db specific)],[config-user(as header)],[config-passwd(as header)]')."\n";
	echo '--delete-domain '.lang2('domain-name')."\n";
}

/**
 * detect eGW versions
 *
 * @return array array with versions (keys phpgwapi, current_header and header)
 */
function detect_versions()
{
	$versions = null;
	$GLOBALS['egw_info']['flags']['noapi'] = true;
	if (!@include('../header.inc.php'))
	{
		if (!@include('../phpgwapi/setup/setup.inc.php'))
		{
			fail(99,lang2("eGroupWare sources in '%1' are not complete, file '%2' missing !!!",realpath('..'),'phpgwapi/setup/setup.inc.php'));	// should not happen ;-)
		}
		return array(
			'phpgwapi' => $setup_info['phpgwapi']['version'],
			'current_header' => $setup_info['phpgwapi']['versions']['current_header'],
		);
	}
	return $GLOBALS['egw_info']['server']['versions'];
}

/**
 * Check if eGW is installed, which versions and if an update is needed
 */
function do_check()
{
	$versions = detect_versions();
	echo lang2('eGroupWare API version %1 found.',$versions['phpgwapi'])."\n";

	if (isset($versions['header']))	// header.inc.php exists
	{
		echo lang2("eGroupWare configuration file (header.inc.php) version %1 exists%2",$versions['header'],
			($versions['header'] == $versions['current_header'] ? ' '.lang2('and is up to date') : '')).".\n";

		if ($versions['header'] != $versions['current_header'])
		{
			// exit-code 2: header.inc.php needs upgrading
			fail(2,lang2('You need to upgrade your header to the new version %1 (using --edit-header)!',$versions['current_header']));
		}
	}
	else
	{
		// exit-code 1: no header.inc.php
		$this->check_fail_header_exists();
	}
	// ToDo: check if eGW needs a schema upgrade and exit(3) if so
}

function fail($exit_code,$message)
{
	echo $message."\n";
	exit($exit_code);
}

/**
 * List all exit codes used by the command line interface
 *
 */
function list_exit_codes()
{
	error_reporting(error_reporting() & ~E_NOTICE);

	$codes = array();
	foreach(file(__FILE__) as $line)
	{
		if (preg_match('/fail\(([0-9]+),(.*)\);/',$line,$matches))
		{
			eval('$codes['.$matches[1].'] = '.$matches[2].';');
		}
	}
	ksort($codes,SORT_NUMERIC);
	foreach($codes as $num => $msg)
	{
		echo $num."\t".str_replace("\n","\n\t",$msg)."\n";
	}
}

/**
 * Check if we have a header.inc.php and fail with exit(10) if not
 *
 */
function check_fail_header_exists()
{
	if (!file_exists('../header.inc.php'))
	{
		fail(1,lang2('eGroupWare configuration file (header.inc.php) does NOT exist.')."\n".lang2('Use --create-header to create the configuration file (--usage gives more options).'));
	}
}
/**
 * Create, edit or update the header.inc.php
 *
 * @param boolean $create true = create new header, false = edit (or update) existing header
 * @param array $arguments
 * @return int
 */
function do_header($create,&$arguments)
{
	// setting up the $GLOBALS['egw_setup'] object AND including the header.inc.php if it exists
	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'currentapp' => 'home',
			'noapi' => true,
	));
	include('inc/functions.inc.php');

	require_once('inc/class.setup_header.inc.php');
	$GLOBALS['egw_setup']->header =& new setup_header();

	if (!file_exists('../header.inc.php'))
	{
		if (!$create) $this->check_fail_header_exists();

		$GLOBALS['egw_setup']->header->defaults(false);
	}
	else
	{
		if ($create) fail(11,lang2('eGroupWare configuration file header.inc.php already exists, you need to use --edit-header or delete it first!'));
		
		// header.inc.php is already include by include('inc/functions.inc.php')!
		unset($GLOBALS['egw_info']['flags']);

		// check header-admin-user and -password
		@list($password,$user) = explode(',',@$arguments[0]);
		if (!$user) $user = 'admin';
		require_once('inc/class.setup.inc.php');
		if (!setup::check_auth($user,$password,$GLOBALS['egw_info']['server']['header_admin_user'],
			$GLOBALS['egw_info']['server']['header_admin_password']))
		{
			fail(12,lang2('Access denied: wrong username or password for manage-header !!!'));
		}
		$GLOBALS['egw_info']['server']['server_root'] = EGW_SERVER_ROOT;
		$GLOBALS['egw_info']['server']['include_root'] = EGW_INCLUDE_ROOT;
	}
	
	$options = array(
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
	array_unshift($arguments,$create ? '--create-header' : '--edit-header');
	while(($arg = array_shift($arguments)))
	{
		$values = count($arguments) && substr($arguments[0],0,2) !== '--' ? array_shift($arguments) : 'on';
		
		if ($arg == '--delete-domain')
		{
			if (!isset($GLOBALS['egw_domain'][$values])) fail(22,lang2("Domain '%1' does NOT exist !!!",$values));
			unset($GLOBALS['egw_domain'][$values]);
			continue;
		}
		
		if (!isset($options[$arg]))	fail(20,lang2("Unknow option '%1' !!!",$arg));

		$option = $options[$arg];
		$values = !is_array($option) ? array($values) : explode(',',$values);
		if (!is_array($option)) $option = array($option => $option);
		$n = 0;
		foreach($option as $name => $data)
		{
			if ($n >= count($values)) break;

			if (!is_array($data)) $data = array('type' => $data);
			$type = $data['type'];
			
			$value = $values[$n];
			if (isset($data['allowed']))
			{
				if (!isset($data['allowed'][$value]))
				{
					fail(21,lang2("'%1' is not allowed as %2. arguments of option %3 !!!",$value,1+$n,$arg));
				}
				$value = $data['allowed'][$value];
			}
			if ($type == '@')
			{
				$remember = $arg == '--domain' && !$value ? 'default' : $value;
				if ($arg == '--domain' && (!isset($GLOBALS['egw_domain'][$remember]) || $create))
				{
					$GLOBALS['egw_domain'][$remember] = $GLOBALS['egw_setup']->header->domain_defaults($GLOBALS['egw_info']['server']['header_admin_user'],$GLOBALS['egw_info']['server']['header_admin_password']);
				}
			}
			elseif ($value !== '')
			{
				set_value($GLOBALS,str_replace('@',$remember,$type),$name,$value);
				if ($name == 'egw_info/server/server_root')
				{
					set_value($GLOBALS,'egw_info/server/include_root',$name,$value);
				}
			}
			++$n;
		}
	}
	if (($errors = $GLOBALS['egw_setup']->header->validation_errors($GLOBALS['egw_info']['server']['server_root'],$GLOBALS['egw_info']['server']['include_root'])))
	{
		echo '$GLOBALS[egw_info] = '; print_r($GLOBALS['egw_info']);
		echo '$GLOBALS[egw_domain] = '; print_r($GLOBALS['egw_domain']);
		echo "\n".lang2('Configuration errors:')."\n- ".implode("\n- ",$errors)."\n";
		fail(23,lang2("You need to fix the above errors, before the configuration file header.inc.php can be written!"));
	}
	$header = $GLOBALS['egw_setup']->header->generate($GLOBALS['egw_info'],$GLOBALS['egw_domain'],
		$GLOBALS['egw_info']['server']['server_root'],$GLOBALS['egw_info']['server']['include_root']);
		
	echo $header;

	if (file_exists('../header.inc.php') && is_writable('../header.inc.php') || is_writable('../'))
	{
		if (is_writable('../') && file_exists('../header.inc.php')) unlink('../header.inc.php');
		if (($f = fopen('../header.inc.php','wb')) && fwrite($f,$header))
		{
			fclose($f);
			echo "\n".lang2('header.inc.php successful written.')."\n\n";
			exit(0);
		}
	}
	fail(24,lang2("Failed writing configuration file header.inc.php, check the permissions !!!"));
}

function set_value($arr,$index,$name,$value)
{
	if (substr($index,-1) == '/') $index .= $name;
	
	$var =& $arr;
	foreach(explode('/',$index) as $name)
	{
		$var =& $var[$name];
	}
	$var = strstr($name,'passw') ? md5($value) : $value;
}

if (!function_exists('generate_mcyrpt_iv'))
{
	function generate_mcyrpt_iv()
	{
		srand((double)microtime()*1000000);
		$random_char = array(
			'0','1','2','3','4','5','6','7','8','9','a','b','c','d','e','f',
			'g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v',
			'w','x','y','z','A','B','C','D','E','F','G','H','I','J','K','L',
			'M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'
		);

		$iv = '';
		for($i=0; $i<30; $i++)
		{
			$iv .= $random_char[rand(1,count($random_char))];
		}
		return $iv;
	}
}
