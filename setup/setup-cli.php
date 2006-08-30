#!/usr/bin/php
<?php
/**
 * Setup - Command line interface
 *
 * @link http://www.egroupware.org
 * @package setup
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2006 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

chdir(dirname(__FILE__));	// to enable our relative pathes to work

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

// setting the language from the enviroment
$_POST['ConfigLang'] = get_lang($charset);
create_http_enviroment();	// guessing the docroot etc.

// setting up the $GLOBALS['egw_setup'] object AND including the header.inc.php if it exists
$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'home',
		'noapi' => true,
));
include('inc/functions.inc.php');
$GLOBALS['egw_setup']->translation->no_translation_marker = '';
$GLOBALS['egw_setup']->system_charset = $charset;

if ((float) PHP_VERSION < $GLOBALS['egw_setup']->required_php_version)
{
	fail(98,lang('You are using PHP version %1. eGroupWare now requires %2 or later, recommended is PHP %3.',PHP_VERSION,$GLOBALS['egw_setup']->required_php_version,$GLOBALS['egw_setup']->recommended_php_version));
}

switch($action)
{
	case '--version':
	case '--check':
		do_check($arguments[0]);
		break;
		
	case '--create-header':
	case '--edit-header':
	case '--upgrade-header':
	case '--update-header':
		do_header($action == '--create-header',$arguments);
		break;
		
	case '--install':
		do_install($arguments[0]);
		break;
		
	case '--config':
		do_config($arguments);
		break;

	case '--admin':
		do_admin($arguments[0]);
		break;

	case '--language':
		do_lang($arguments[0]);
		break;
		
	case '--update':
		do_update($arguments[0]);
		break;
		
	case '--backup':
		do_backup($arguments[0]);
		break;

	case '--languages':
		echo html_entity_decode(file_get_contents('lang/languages'),ENT_COMPAT,'utf-8');
		break;

	case '--charsets':
		echo html_entity_decode(implode("\n",$GLOBALS['egw_setup']->translation->get_charsets(false)),ENT_COMPAT,'utf-8')."\n";
		break;
	
	case '--exit-codes':
		list_exit_codes();
		break;
		
	case '--help':
	case '--usage':
		do_usage($arguments[0]);
		break;

	default:
		fail(90,lang("Unknown option '%1' !!!",$action));
}
exit(0);

/**
 * Configure eGroupWare
 *
 * @param array $args domain(default),[config user(admin)],password,[,name=value,...] --files-dir --backup-dir --mailserver
 */
function do_config($args)
{
	$options = _check_auth_config(array_shift($args),15);
	
	$values = array();
	foreach($options as $option)
	{
		list($name,$value) = explode('=',$option,2);
		$values[$name] = $value;
	}
	static $config = array(
		'--files-dir'  => 'files_dir',
		'--backup-dir' => 'backup_dir',
		'--temp-dir'   => 'temp_dir',
		'--webserver-url' => 'webserver_url',
		'--mailserver' => array(	//server,{IMAP|IMAPS|POP|POPS},[domain],[{standard(default)|vmailmgr = add domain for mailserver login}]
			'mail_server',
			array('name' => 'mail_server_type','allowed' => array('imap','imaps','pop3','pop3s')),
			'mail_suffix',
			array('name' => 'mail_login_type','allowed'  => array('standard','vmailmgr')),
		),
		'--cyrus' => array(
			'imapAdminUsername',
			'imapAdminPW',
			array('name' => 'imapType','default' => 3),
			array('name' => 'imapEnableCyrusAdmin','default' => 'yes'),
		),
		'--sieve' => array(
			array('name' => 'imapSieveServer','default' => 'localhost'),
			array('name' => 'imapSievePort','default' => 2000),
			array('name' => 'imapEnableSieve','default' => 'yes'),	// null or yes
		),
		'--postfix' => array(
			array('name' => 'editforwardingaddress','allowed' => array('yes',null)),
			array('name' => 'smtpType','default' => 2),
		),
		'--smtpserver' => array(	//smtp server,[smtp port],[smtp user],[smtp password]
			'smtp_server','smtp_port','smtp_auth_user','smtp_auth_passwd',''
		),
		'--account-auth' => array(
			array('name' => 'account_repository','allowed' => array('sql','ldap')),
			array('name' => 'auth_type','allowed' => array('sql','ldap','mail','ads','http','sqlssl','nis','pam')),
			array('name' => 'sql_encryption','allowed' => array('md5','blowfish_crypt','md5_crypt','crypt')),
			'check_save_password','allow_cookie_auth'),
		'--ldap-host' => 'ldap_host',
		'--ldap-root-dn' => 'ldap_root_dn',
		'--ldap-root-pw' => 'ldap_root_pw',
		'--ldap-context' => 'ldap_context',
		'--ldap-group-context' => 'ldap_group_context',
	);
	$do_ea_profile = false;
	while (($arg = array_shift($args)))
	{
		if (!isset($config[$arg])) fail(90,lang("Unknown option '%1' !!!",$arg));

		$options = array();
		if (substr($args[0],0,2) !== '--')
		{
			$options = is_array($config[$arg]) ? explode(',',array_shift($args)) : array(array_shift($args));
		}
		$options[] = ''; $options[] = '';
		foreach($options as $n => $value)
		{
			if ($value === '' && is_array($config[$arg]) && !isset($config[$arg][$n]['default'])) continue;
			
			$name = is_array($config[$arg]) || $n ? $config[$arg][$n] : $config[$arg];
			if (is_array($name))
			{
				if (isset($name['allowed']) && !in_array($value,$name['allowed']))
				{
					fail(91,lang("'%1' is not allowed as %2. arguments of option %3 !!!",$value,1+$n,$arg));
				}
				if (!$value && isset($name['default'])) $value = $name['default'];
				$name = $name['name'];
			}
			$values[$name] = $value;
		}
		if (in_array($arg,array('--mailserver','--smtpserver','--cyrus','--postfix','--sieve')))
		{
			$do_ea_profile = true;
		}
	}
	foreach($values as $name => $value)
	{
		$GLOBALS['egw_setup']->db->insert($GLOBALS['egw_setup']->config_table,array(
			'config_value' => $value,
		),array(
			'config_app'  => 'phpgwapi',
			'config_name' => $name,
		),__LINE__,__FILE__);
	}
	if (count($values))
	{
		echo lang('Configuration changed.')."\n";
		
		if ($do_ea_profile) do_emailadmin($values);
	}
	echo "\n".lang('Current configuration:')."\n";
	$GLOBALS['egw_setup']->db->select($GLOBALS['egw_setup']->config_table,'config_name,config_value',array(
		'config_app'  => 'phpgwapi',
		"(config_name LIKE '%\\_dir' OR (config_name LIKE 'mail%' AND config_name != 'mail_footer') OR config_name LIKE 'smtp\\_%' OR config_name LIKE 'ldap%' OR config_name IN ('webserver_url','system_charset','auth_type','account_repository'))",
	),__LINE__,__FILE__);
	while (($row = $GLOBALS['egw_setup']->db->row(true)))
	{
		echo str_pad($row['config_name'].':',22).$row['config_value']."\n";
	}
}

/**
 * Updates the default EMailAdmin profile
 *
 * @param array $values
 */
function do_emailadmin()
{
	$GLOBALS['egw_setup']->db->select($GLOBALS['egw_setup']->config_table,'config_name,config_value',array(
		'config_app'  => 'phpgwapi',
		"((config_name LIKE 'mail%' AND config_name != 'mail_footer') OR config_name LIKE 'smtp%' OR config_name LIKE 'imap%' OR config_name='editforwardingaddress')",
	),__LINE__,__FILE__);
	while (($row = $GLOBALS['egw_setup']->db->row(true)))
	{
		$config[$row['config_name']] = $row['config_value'];
	}
	$config['smtpAuth'] = $config['smtp_auth_user'] ? 'yes' : null;

	$emailadmin =& CreateObject('emailadmin.bo',-1,false);	// false=no session stuff
	$emailadmin->setDefaultProfile($config);
	
	echo "\n".lang('EMailAdmin profile updated:')."\n";
	foreach($config as $name => $value)
	{
		echo str_pad($name.':',22).$value."\n";
	}
}

/**
 * Create an admin account
 *
 * @param string $arg domain(default),[config user(admin)],password,username,password,[first name],[last name],[email]
 */
function do_admin($arg)
{
	list($_POST['username'],$_POST['passwd'],$_POST['fname'],$_POST['lname'],$_POST['email']) =	_check_auth_config($arg,15);
	$_POST['passwd2'] = $_POST['passwd'];
	
	if (!$_POST['fname']) $_POST['fname'] = 'Admin';
	if (!$_POST['lname']) $_POST['lname'] = 'User';
	
	$_POST['submit'] = true;
	$error = include('admin_account.php');

	switch ($error)
	{
		case 41:
			fail(41,lang('Error in admin-creation !!!'));
		case 42:
			fail(42,lang('Error in group-creation !!!'));
	}
	echo lang('Admin account successful created.')."\n";
}

/**
 * Backup one or all domains
 *
 * @param string $arg domain(all),[config user(admin)],password,[backup-file, 'no' for no backup or empty for default name]
 * @param boolean $quite_check quiten the call to _check_auth_config
 */
function do_backup($arg,$quite_check=false)
{
	list($domain,,,$backup) = $options = explode(',',$arg);

	$domains = $GLOBALS['egw_domain'];
	if ($domain && $domain != 'all')
	{
		$domains = array($domain => $GLOBALS['egw_domain'][$domain]);
	}
	foreach($domains as $domain => $data)
	{
		$options[0] = $domain;
		
		if ($quite_check) ob_start();
		_check_auth_config(implode(',',$options),14);
		if ($quite_check) ob_end_clean();
		
		if ($backup == 'no')
		{
			echo lang('Backup skipped!')."\n";
		}
		else
		{
			$db_backup =& CreateObject('phpgwapi.db_backup');
			if (is_resource($f = $db_backup->fopen_backup($backup)))
			{
				echo lang('Backup started, this might take a few minutes ...')."\n";
				$db_backup->backup($f);
				fclose($f);
				echo lang('Backup finished')."\n";
			}
			else	// backup failed ==> dont start the upgrade
			{
				fail(50,lang('Backup failed').': '.$f);
			}
		}
	}
}

/**
 * Update one or all domains
 *
 * @param string $arg domain(all),[config user(admin)],password,[backup-file, 'no' for no backup or empty for default name]
 */
function do_update($arg)
{
	global $setup_info;

	list($domain,,,$no_backup) = $options = explode(',',$arg);

	$domains = $GLOBALS['egw_domain'];
	if ($domain && $domain != 'all')
	{
		$domains = array($domain => $GLOBALS['egw_domain'][$domain]);
	}
	foreach($domains as $domain => $data)
	{
		$options[0] = $domain;
		$arg = implode(',',$options);
		
		_check_auth_config($arg,14);
		
		if ($GLOBALS['egw_info']['setup']['stage']['db'] != 4)
		{
			echo lang('No update necessary, domain %1(%2) is up to date.',$domain,$data['db_type'])."\n";
		}
		else
		{
			echo lang('Start updating the database ...')."\n";
			
			do_backup($arg,true);
			
			ob_start();
			$GLOBALS['egw_setup']->process->init_process();	// we need a new schema-proc instance for each new domain
			$GLOBALS['egw_setup']->process->pass($setup_info,'upgrade',false);
			$messages = ob_get_contents();
			ob_end_clean();
			if ($messages) echo strip_tags($messages)."\n";
			
			echo lang('Update finished.')."\n";
		}
	}
}

/**
 * Install / update languages
 *
 * @param string $arg domain(all),[config user(admin)],password,[+][lang1][,lang2,...]
 */
function do_lang($arg)
{
	global $setup_info;

	list($domain) = $options = explode(',',$arg);

	$domains = $GLOBALS['egw_domain'];
	if ($domain && $domain != 'all')
	{
		$domains = array($domain => $GLOBALS['egw_domain'][$domain]);
	}
	foreach($domains as $domain => $data)
	{
		$options[0] = $domain;
		$arg = implode(',',$options);
		
		$langs = _check_auth_config($arg,15,false);		// false = leave eGW's charset, dont set ours!!!
		
		$GLOBALS['egw_setup']->translation->setup_translation_sql();

		if ($langs[0]{0} === '+' || !count($langs))	// update / add to existing languages
		{
			if ($langs[0]{0} === '+')
			{
				if ($langs[0] === '+')
				{
					array_shift($langs);
				}
				else
				{
					$langs[0] = substr($langs[0],1);
				}
			}
			$installed_langs = $GLOBALS['egw_setup']->translation->sql->get_installed_langs(true);
			if (is_array($installed_langs))
			{
				$langs = array_merge($langs,array_keys($installed_langs));
			}
		}
		$langs = array_unique($langs);
		echo lang('Start updating languages %1 ...',implode(',',$langs))."\n";
		$GLOBALS['egw_setup']->translation->sql->install_langs($langs);
		echo lang('Languages updated.')."\n";
	}
}		

/**
 * Check if eGW is installed according to $stop and we have the necessary authorization for config
 * 
 * The password can be specified as parameter, via the enviroment variable EGW_CLI_PASSWORD or
 * querier from the user. Specifying it as parameter can be security problem!
 * 
 * We allow the config user/pw of the domain OR the header admin user/pw!
 *
 * @param string $arg [domain(default)],[user(admin)],password
 * @param int $stop see do_check()
 * @param boolean $set_lang=true set our charset, overwriting the charset of the eGW installation, default true
 * @return array with unprocessed arguments from $arg
 */
function _check_auth_config($arg,$stop,$set_lang=true)
{
	$options = explode(',',$arg);
	if (!($domain = array_shift($options))) $domain = 'default';
	if (!($user = array_shift($options))) $user = 'admin';
	if (!($password = array_shift($options)))
	{
		if (!($password = $_SERVER['EGW_CLI_PASSWORD']))
		{
			echo lang('Config password').' ';
			$password = trim(fgets($f = fopen('php://stdin','rb')));
			fclose($f);
		}
	}
	do_check($domain,$stop);	// check if eGW is installed

	// reset charset for the output to the charset used by the OS
	if ($set_lang) $GLOBALS['egw_setup']->system_charset = $GLOBALS['charset'];
	
	//echo "check_auth('$user','$password','{$GLOBALS['egw_domain'][$domain]['config_user']}','{$GLOBALS['egw_domain'][$domain]['config_passwd']}')\n";
	if (!$GLOBALS['egw_setup']->check_auth($user,$password,$GLOBALS['egw_domain'][$domain]['config_user'],
		$GLOBALS['egw_domain'][$domain]['config_passwd']) &&
		!$GLOBALS['egw_setup']->check_auth($user,$password,$GLOBALS['egw_domain'][$domain]['header_admin_user'],
		$GLOBALS['egw_domain'][$domain]['header_admin_password']))
	{
		fail(40,lang("Access denied: wrong username or password to configure the domain '%1(%2)' !!!",$domain,$GLOBALS['egw_domain'][$domain]['db_type']));
	}
	return $options;
}

/**
 * Install eGroupWare
 *
 * @param string $args domain,[config user(admin)],password,[backup-file],[charset]
 */
function do_install($args)
{
	global $setup_info;

	list($domain,,,$backup,$charset) = explode(',',$args);
	if (!$domain) $domain = 'default';
	
	$options = _check_auth_config($args,array(13,14,20));
	
	// use uploaded backup, instead installing from scratch
	if ($backup)
	{
		$db_backup =& CreateObject('phpgwapi.db_backup');

		if (!is_resource($f = $db_backup->fopen_backup($backup,true)))
		{
			fail(31,lang('Restore failed'));
		}
		echo lang('Restore started, this might take a few minutes ...')."\n";
		$db_backup->restore($f,$charset);
		fclose($f);
		echo lang('Restore finished')."\n";
	}
	else
	{
		if ($GLOBALS['egw_info']['setup']['stage']['db'] != 3)
		{
			fail(30,lang('eGroupWare is already installed!'));
		}
		if (!$charset) $charset = $GLOBALS['egw_setup']->translation->langarray['charset'];

		$setup_info = $GLOBALS['egw_setup']->detection->upgrade_exclude($setup_info);

		// Set the DB's client charset if a system-charset is set
		$GLOBALS['egw_setup']->system_charset = strtolower($charset);
		$GLOBALS['egw_setup']->db->Link_ID->SetCharSet($charset);

		echo lang('Installation started, this might take a few minutes ...')."\n";
		$setup_info = $GLOBALS['egw_setup']->process->pass($setup_info,'new',false,True);
		echo lang('Installation finished')."\n";
	}	
}

/**
 * Check if eGW is installed, which versions and if an update is needed
 * 
 * @param string $domain='' domain to check, default '' = all
 * @param int/array $stop=0 stop checks before given exit-code(s), default 0 = all checks
 */
function do_check($domain='',$stop=0)
{
	global $setup_info;
	static $header_checks=true;	// output the header checks only once
	
	if ($stop && !is_array($stop)) $stop = array($stop);

	$versions =& $GLOBALS['egw_info']['server']['versions'];

	if (!$versions['phpgwapi'])
	{
		if (!include('../phpgwapi/setup/setup.inc.php'))
		{
			fail(99,lang("eGroupWare sources in '%1' are not complete, file '%2' missing !!!",realpath('..'),'phpgwapi/setup/setup.inc.php'));	// should not happen ;-)
		}
		$versions['phpgwapi'] = $setup_info['phpgwapi']['version'];
		unset($setup_info);
	}
	if ($header_checks)
	{
		echo lang('eGroupWare API version %1 found.',$versions['phpgwapi'])."\n";
	}
	$header_stage = $GLOBALS['egw_setup']->detection->check_header();
	if ($stop && in_array($header_stage,$stop)) return true;
	
	switch ($header_stage)
	{
		case 1: fail(1,lang('eGroupWare configuration file (header.inc.php) does NOT exist.')."\n".lang('Use --create-header to create the configuration file (--usage gives more options).'));
			
		case 2: fail(2,lang('eGroupWare configuration file (header.inc.php) version %1 exists%2',$versions['header'],'.')."\n".lang('No header admin password set! Use --edit-header <password>[,<user>] to set one (--usage gives more options).'));

		case 3: fail(3,lang('eGroupWare configuration file (header.inc.php) version %1 exists%2',$versions['header'],'.')."\n".lang('No eGroupWare domains / database instances exist! Use --edit-header --domain to add one (--usage gives more options).'));

		case 4: fail(4,lang('eGroupWare configuration file (header.inc.php) version %1 exists%2',$versions['header'],'.')."\n".lang('It needs upgrading to version %1! Use --update-header <password>[,<user>] to do so (--usage gives more options).',$versions['current_header']));
	}
	if ($header_checks)
	{
		echo lang('eGroupWare configuration file (header.inc.php) version %1 exists%2',
			$versions['header'],' '.lang('and is up to date')).".\n";
	}
	$header_checks = false;	// no further output of the header checks

	$domains = $GLOBALS['egw_domain'];
	if ($domain)	// domain to check given
	{
		if (!isset($GLOBALS['egw_domain'][$domain])) fail(92,lang("Domain '%1' does NOT exist !!!",$domain));
		
		$domains = array($domain => $GLOBALS['egw_domain'][$domain]);
	}
	foreach($domains as $domain => $data)
	{
		$GLOBALS['egw_setup']->ConfigDomain = $domain;	// set the domain the setup class operates on
		if (count($GLOBALS['egw_domain']) > 1) echo "\n".lang('eGroupWare domain/instance %1(%2):',$domain,$data['db_type'])."\n";

		$setup_info = $GLOBALS['egw_setup']->detection->get_versions();
		// check if there's already a db-connection and close if, otherwise the db-connection of the previous domain will be used
		if (is_object($GLOBALS['egw_setup']->db))
		{
			$GLOBALS['egw_setup']->db->disconnect();
		}
		$GLOBALS['egw_setup']->loaddb();
		
		$db = $data['db_type'].'://'.$data['db_user'].'@'.$data['db_host'].'/'.$data['db_name'];

		$db_stage =& $GLOBALS['egw_info']['setup']['stage']['db'];
		if (($db_stage = $GLOBALS['egw_setup']->detection->check_db($setup_info)) != 1)
		{
			$setup_info = $GLOBALS['egw_setup']->detection->get_db_versions($setup_info);
			$db_stage = $GLOBALS['egw_setup']->detection->check_db($setup_info);
		}
		if ($stop && in_array(10+$db_stage,$stop)) return true;

		switch($db_stage)
		{
			case 1: fail(11,lang('Your Database is not working!')." $db: ".$GLOBALS['egw_setup']->db->Error);

			case 3: fail(13,lang('Your database is working, but you dont have any applications installed')." ($db). ".lang("Use --install to install eGroupWare."));

			case 4: fail(14,lang('eGroupWare API needs a database (schema) update from version %1 to %2!',$setup_info['phpgwapi']['currentver'],$versions['phpgwapi']).' '.lang('Use --update to do so.'));
			
			case 10:	// also check apps of updates
				$apps_to_upgrade = array();
				foreach($setup_info as $app => $data)
				{
					if ($data['currentver'] && $data['version'] && $data['version'] != $data['currentver'])
					{
						$apps_to_upgrade[] = $app;
					}
				}
				if ($apps_to_upgrade)
				{
					$db_stage = 4;
					if ($stop && in_array(10+$db_stage,$stop)) return true;
					fail(14,lang('The following applications need to be upgraded:').' '.implode(', ',$apps_to_upgrade).'! '.lang('Use --update to do so.'));
				}
				break;
		}
		echo lang("database is version %1 and up to date.",$setup_info['phpgwapi']['currentver'])."\n";

		$GLOBALS['egw_setup']->detection->check_config();
		if ($GLOBALS['egw_info']['setup']['config_errors'] && $stop && !in_array(15,$stop))
		{
			fail(15,lang('You need to configure eGroupWare:')."\n- ".@implode("\n- ",$GLOBALS['egw_info']['setup']['config_errors']));
		}
	}
	return false;
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
	require_once('inc/class.setup_header.inc.php');
	$GLOBALS['egw_setup']->header =& new setup_header();

	if (!file_exists('../header.inc.php'))
	{
		if (!$create) fail(1,lang('eGroupWare configuration file (header.inc.php) does NOT exist.')."\n".lang('Use --create-header to create the configuration file (--usage gives more options).'));

		$GLOBALS['egw_setup']->header->defaults(false);
	}
	else
	{
		if ($create) fail(20,lang('eGroupWare configuration file header.inc.php already exists, you need to use --edit-header or delete it first!'));
		
		// check header-admin-user and -password (only if a password is set!)
		if ($GLOBALS['egw_info']['server']['header_admin_password'])
		{
			@list($password,$user) = $options = explode(',',@$arguments[0]);
			if (!$user) $user = 'admin';
			if (!$password && !($password = $_SERVER['EGW_CLI_PASSWORD']))
			{
				echo lang('Admin password to header manager').' ';
				$password = trim(fgets($f = fopen('php://stdin','rb')));
				fclose($f);
			}
			$options[0] = $password;
			$options[1] = $user;
			$arguments[0] = implode(',',$options);

			if (!$GLOBALS['egw_setup']->check_auth($user,$password,$GLOBALS['egw_info']['server']['header_admin_user'],
					$GLOBALS['egw_info']['server']['header_admin_password']))
			{
				fail(21,lang('Access denied: wrong username or password for manage-header !!!'));
			}
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
			if (!isset($GLOBALS['egw_domain'][$values])) fail(92,lang("Domain '%1' does NOT exist !!!",$values));
			unset($GLOBALS['egw_domain'][$values]);
			continue;
		}
		
		if (!isset($options[$arg]))	fail(90,lang("Unknown option '%1' !!!",$arg));

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
					fail(91,lang("'%1' is not allowed as %2. arguments of option %3 !!!",$value,1+$n,$arg));
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
				_set_value($GLOBALS,str_replace('@',$remember,$type),$name,$value);
				if ($name == 'egw_info/server/server_root')
				{
					_set_value($GLOBALS,'egw_info/server/include_root',$name,$value);
				}
			}
			++$n;
		}
	}
	if (($errors = $GLOBALS['egw_setup']->header->validation_errors($GLOBALS['egw_info']['server']['server_root'],$GLOBALS['egw_info']['server']['include_root'])))
	{
		unset($GLOBALS['egw_info']['flags']);
		echo '$GLOBALS[egw_info] = '; print_r($GLOBALS['egw_info']);
		echo '$GLOBALS[egw_domain] = '; print_r($GLOBALS['egw_domain']);
		echo "\n".lang('Configuration errors:')."\n- ".implode("\n- ",$errors)."\n";
		fail(23,lang("You need to fix the above errors, before the configuration file header.inc.php can be written!"));
	}
	$header = $GLOBALS['egw_setup']->header->generate($GLOBALS['egw_info'],$GLOBALS['egw_domain']);
		
	echo $header;

	if (file_exists('../header.inc.php') && is_writable('../header.inc.php') || is_writable('../'))
	{
		if (is_writable('../') && file_exists('../header.inc.php')) unlink('../header.inc.php');
		if (($f = fopen('../header.inc.php','wb')) && fwrite($f,$header))
		{
			fclose($f);
			echo "\n".lang('header.inc.php successful written.')."\n\n";
			exit(0);
		}
	}
	fail(24,lang("Failed writing configuration file header.inc.php, check the permissions !!!"));
}

/**
 * Set a value in the given array $arr with (multidimensional) key $index[/$name]
 *
 * @param array &$arr
 * @param string $index multidimensional index written with / as separator, eg. egw_info/server/
 * @param string $name additional index to use if $index end with a slash
 * @param mixed $value value to set
 */
function _set_value(&$arr,$index,$name,$value)
{
	if (substr($index,-1) == '/') $index .= $name;
	
	$var =& $arr;
	foreach(explode('/',$index) as $name)
	{
		$var =& $var[$name];
	}
	$var = strstr($name,'passw') ? md5($value) : $value;
}

/**
 * Reads the users language from the enviroment
 *
 * @param string &$charset charset set in LANG enviroment variable or the default utf-8
 * @return string 2 or 5 digit language code used in eGW
 */
function get_lang(&$charset)
{
	@list($lang,$nation,$charset) = split("[_.]",strtolower($_SERVER['LANG']));

	foreach(file('lang/languages') as $line)
	{
		list($code,$language) = explode("\t",$line);
		$languages[$code] = $language;
	}
	if (isset($languages[$lang.'-'.$nation])) return $lang.'-'.$nation;

	if (isset($languages[$lang])) return $lang;
	
	return 'en';
}

/**
 * Try guessing the document root of the webserver, should work for RH, SuSE, debian and plesk
 */
function create_http_enviroment()
{
	$_SERVER['SCRIPT_FILENAME'] = __FILE__;

	foreach(array('httpsdocs','httpdocs','htdocs','html','www') as $docroottop)
	{
		$parts = explode($docroottop,__FILE__);
		if (count($parts) == 2)
		{
			$_SERVER['DOCUMENT_ROOT'] = $parts[0].$docroottop;
			$_SERVER['PHP_SELF'] = str_replace('\\','/',$parts[1]);
			break;
		}
	}
	//print_r($_SERVER); exit;
}

/**
 * Echos usage message
 */
function do_usage($what='')
{
	echo lang('Usage: %1 command [additional options]',basename($_SERVER['argv'][0]))."\n\n";
	
	if (!$what)
	{
		echo '--check '.lang('checks eGroupWare\'s installed, it\'s versions and necessary upgrads (return values see --exit-codes)')."\n";
		echo '--install '.lang('domain(default),[config user(admin)],password,[backup to install],[charset(default depends on language)]')."\n";
	}
	if (!$what || $what == 'config')
	{
		echo '--config '.lang('domain(default),[config user(admin)],password,[name=value,...] sets config values beside:')."\n";
		if (!$what) echo '	--help config '.lang('gives further options')."\n";
	}
	if ($what == 'config')
	{
		echo '	--files-dir, --backup-dir, --temp-dir '.lang('path to various directories: have to exist and be writeable by the webserver')."\n";
		echo '	--webserver-url '.lang('eg. /egroupware or http://domain.com/egroupware, default: %1',str_replace('/setup/setup-cli.php','',$_SERVER['PHP_SELF']))."\n";
		echo '	--mailserver '.lang('host,{imap | pop3 | imaps | pop3s},[domain],[{standard(default)|vmailmgr = add domain for mailserver login}]')."\n";
		echo '	--smtpserver '.lang('host,[smtp port],[smtp user],[smtp password]')."\n";
		echo '	--postfix '.lang('Postfix with LDAP: [yes(user edit forwarding)]')."\n";
		echo '	--cyrus '.lang('Cyrus IMAP: Admin user,Password')."\n";
		echo '	--sieve '.lang('Sieve: Host[,Port(2000)]')."\n";
		echo '	--account-auth '.lang('account repository{sql(default) | ldap},[authentication{sql | ldap | mail | ads | http | ...}],[sql encrypttion{md5 | blowfish_crypt | md5_crypt | crypt}],[check save password{ (default)|True}],[allow cookie auth{ (default)|True}]')."\n";
		echo '	--ldap-host  --ldap-root-dn  --ldap-root-pw  --ldap-context  --ldap-group-context'."\n";
	}
	if (!$what)
	{
		echo '--admin '.lang('creates an admin user: domain(default),[config user(admin)],password,username,password,[first name],[last name],[email]')."\n";
		echo '--language '.lang('install or update translations: domain(all),[config user(admin)],password,[[+]lang1[,lang2,...]] + adds, no langs update existing ones')."\n";
		echo '--backup '.lang('domain(all),[config user(admin)],password,[file-name(default: backup-dir/db_backup-YYYYMMDDHHii)]')."\n";
		echo '--update '.lang('run a database schema update (if necessary): domain(all),[config user(admin)],password')."\n";
		echo lang('You can use the header user and password for every domain too. If the password is not set via the commandline, it is read from the enviroment variable EGW_CLI_PASSWORD or queried from the user.')."\n";
	}
	if (!$what || $what == 'header')
	{
		echo lang('Create or edit the eGroupWare configuration file: header.inc.php:')."\n";
		echo '--create-header '.lang('header-password[,header-user(admin)]')."\n";
		echo '--edit-header '.lang('[header-password],[header-user],[new-password],[new-user]')."\n";
		if (!$what) echo '	--help header '.lang('gives further options')."\n";
	}
	if ($what == 'header')
	{
		echo "\n".lang('Additional options and there defaults (in brackets)')."\n";
		echo '--server-root '.lang('path of eGroupWare install directory (default auto-detected)')."\n";
		echo '--session-type '.lang('{db | php(default) | php-restore}')."\n";
		echo '--limit-access '.lang('comma separated ip-addresses or host-names, default access to setup from everywhere')."\n";
		echo '--mcrypt '.lang('use mcrypt to crypt session-data: {off(default) | on},[mcrypt-init-vector(default randomly generated)],[mcrypt-version]')."\n";
		echo '--db-persistent '.lang('use persistent db connections: {on(default) | off}')."\n";
		echo '--domain-selectbox '.lang('{off(default) | on}')."\n";
	
		echo "\n".lang('Adding, editing or deleting an eGroupWare domain / database instance:')."\n";
		echo '--domain '.lang('add or edit a domain: [domain-name(default)],[db-name(egroupware)],[db-user(egroupware)],db-password,[db-type(mysql)],[db-host(localhost)],[db-port(db specific)],[config-user(as header)],[config-passwd(as header)]')."\n";
		echo '--delete-domain '.lang('domain-name')."\n";
	}
	if (!$what)
	{
		echo '--help list '.lang('List availible values')."\n";
	}
	if ($what == 'list')
	{
		echo lang('List availible values').":\n";
		echo '--languages '.lang('list of availible translations')."\n";
		echo '--charsets '.lang('charsets used by the different languages')."\n";
		echo '--exit-codes '.lang('all exit codes of the command line interface')."\n";
	}
	if (!$what || !in_array($what,array('config','header','list')))
	{
		echo '--help [config|header|list] '.lang('gives further options')."\n";
	}
}

function fail($exit_code,$message)
{
	echo $message."\n";
	exit($exit_code);
}

/**
 * List all exit codes used by the command line interface
 *
 * The list is generated by "greping" this file for calls to the fail() function. 
 * Calls to fail() have to be in one line, to be recogniced!
 */
function list_exit_codes()
{
	error_reporting(error_reporting() & ~E_NOTICE);

	$codes = array('Ok');
	foreach(file(__FILE__) as $n => $line)
	{
		if (preg_match('/fail\(([0-9]+),(.*)\);/',$line,$matches))
		{
			//echo "Line $n: $matches[1]: $matches[2]\n";
			@eval('$codes['.$matches[1].'] = '.$matches[2].';');
		}
	}
	ksort($codes,SORT_NUMERIC);
	foreach($codes as $num => $msg)
	{
		echo $num."\t".str_replace("\n","\n\t",$msg)."\n";
	}
}
