#!/usr/bin/php -qC
<?php
/**
 * Setup - Command line interface
 *
 * @link http://www.egroupware.org
 * @package setup
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2006-9 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

chdir(dirname(__FILE__));	// to enable our relative pathes to work

if (isset($_SERVER['HTTP_HOST']))	// security precaution: forbit calling setup-cli as web-page
{
	die('<h1>setup-cli.php must NOT be called as web-page --> exiting !!!</h1>');
}
elseif ($_SERVER['argc'] > 1)
{
	$arguments = $_SERVER['argv'];
	array_shift($arguments);
	$action = array_shift($arguments);
	if (isset($arguments[0])) list($_POST['FormDomain']) = explode(',',$arguments[0]);	// header include needs that to detects the right domain
}
else
{
	$action = '--version';
}

// setting the language from the enviroment
$_POST['ConfigLang'] = get_lang($charset);
create_http_enviroment();	// guessing the docroot etc.

if (ini_get('session.save_handler') == 'files' && !is_writable(ini_get('session.save_path')) && is_dir('/tmp') && is_writable('/tmp'))
{
	ini_set('session.save_path','/tmp');	// regular users may have no rights to apache's session dir
}
// setting up the $GLOBALS['egw_setup'] object AND including the header.inc.php if it exists
include('inc/functions.inc.php');
$GLOBALS['egw_info']['flags']['no_exception_handler'] = 'cli';	// inc/functions.inc.php does NOT set it
$GLOBALS['egw_setup']->system_charset = $charset;

if ((float) PHP_VERSION < $GLOBALS['egw_setup']->required_php_version)
{
	throw new egw_exception_wrong_userinput(lang('You are using PHP version %1. eGroupWare now requires %2 or later, recommended is PHP %3.',PHP_VERSION,$GLOBALS['egw_setup']->required_php_version,$GLOBALS['egw_setup']->recommended_php_version),98);
}

switch($action)
{
	case '--version':
	case '--check':
		setup_cmd::check_installed($arguments[0],0,true);
		break;

	case '--create-header':
	case '--edit-header':
	case '--upgrade-header':
	case '--update-header':
		do_header($action == '--create-header',$arguments);
		break;

	case '--install':
		do_install($arguments);
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
		// we allow to call admin_cmd classes directly, if they define the constant SETUP_CLI_CALLABLE
		if (substr($action,0,2) == '--' && class_exists($class = str_replace('-','_',substr($action,2))) &&
			is_subclass_of($class,'admin_cmd') && @constant($class.'::SETUP_CLI_CALLABLE'))
		{
			$args = array();
			$args['domain'] = array_shift($arguments);	// domain must be first argument, to ensure right domain get's selected in header-include
			foreach($arguments as $arg)
			{
				list($name,$value) = explode('=',$arg,2);
				if(property_exists('admin_cmd',$name))		// dont allow to overwrite admin_cmd properties
				{
					throw new egw_exception_wrong_userinput(lang("Invalid argument '%1' !!!",$arg),90);
				}
				if (substr($name,-1) == ']')	// allow 1-dim. arrays
				{
					list($name,$sub) = explode('[',substr($name,0,-1),2);
					$args[$name][$sub] = $value;
				}
				else
				{
					$args[$name] = $value;
				}
			}
			$cmd = new $class($args);
			$msg = $cmd->run();
			if (is_array($msg)) $msg = print_r($msg,true);
			echo "$msg\n";
			break;
		}
		throw new egw_exception_wrong_userinput(lang("Unknown option '%1' !!!",$action),90);
}
exit(0);

/**
 * Configure eGroupWare
 *
 * @param array $args domain(default),[config user(admin)],password,[,name=value,...] --files-dir --backup-dir --mailserver
 */
function do_config($args)
{
	$arg0 = explode(',',array_shift($args));
	if (!($domain = @array_shift($arg0))) $domain = 'default';
	$user = @array_shift($arg0);
	$password = @array_shift($arg0);
	_fetch_user_password($user,$password);

	if ($arg0)	// direct assignments (name=value,...) left
	{
		array_unshift($args,implode(',',$arg0));
		array_unshift($args,'--config');
	}

	$cmd = new setup_cmd_config($domain,$user,$password,$args,true);
	echo $cmd->run()."\n\n";

	$cmd->get_config(true);
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

	$emailadmin = new emailadmin_bo(-1,false);	// false=no session stuff
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
 * @param string $arg domain(default),[config user(admin)],password,username,password,[first name],[last name],[email],[lang]
 */
function do_admin($arg)
{
	list($domain,$user,$password,$admin,$pw,$first,$last,$email,$lang) = explode(',',$arg);
	_fetch_user_password($user,$password);

	$cmd = new setup_cmd_admin($domain,$user,$password,$admin,$pw,$first,$last,$email,array(),$lang);
	echo $cmd->run()."\n";
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
			$db_backup = new db_backup();
			if (is_resource($f = $db_backup->fopen_backup($backup)))
			{
				echo lang('Backup started, this might take a few minutes ...')."\n";
				$db_backup->backup($f);
				echo lang('Backup finished')."\n";
			}
			else	// backup failed ==> dont start the upgrade
			{
				throw new egw_exception_wrong_userinput(lang('Backup failed').': '.$f,50);
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

	list($domain,$user,$password,$backup) = explode(',',$arg);
	_fetch_user_password($user,$password);

	$domains = $GLOBALS['egw_domain'];
	if ($domain && $domain != 'all')
	{
		$domains = array($domain => $GLOBALS['egw_domain'][$domain]);
	}
	foreach($domains as $domain => $data)
	{
		$arg = "$domain,$user,$password,$backup";

		_check_auth_config($arg,14);

		if ($GLOBALS['egw_info']['setup']['stage']['db'] != 4)
		{
			echo lang('No update necessary, domain %1(%2) is up to date.',$domain,$data['db_type'])."\n";
		}
		else
		{
			do_backup($arg,true);

			$cmd = new setup_cmd_update($domain,$user,$password,$backup,true);
			echo $cmd->run()."\n";
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
 * @param int $stop see setup_cmd::check_installed
 * @param boolean $set_lang=true set our charset, overwriting the charset of the eGW installation, default true
 * @return array with unprocessed arguments from $arg
 */
function _check_auth_config($arg,$stop,$set_lang=true)
{
	$options = explode(',',$arg);
	if (!($domain = array_shift($options))) $domain = 'default';
	$user = array_shift($options);
	$password = array_shift($options);
	_fetch_user_password($user,$password);

	setup_cmd::check_installed($domain,$stop,true);

	// reset charset for the output to the charset used by the OS
	if ($set_lang) $GLOBALS['egw_setup']->system_charset = $GLOBALS['charset'];

	setup_cmd::check_setup_auth($user,$password,$domain);

	return $options;
}

/**
 * Install eGroupWare
 *
 * @param array $args array(0 => "domain,[config user(admin)],password,[backup-file],[charset],[lang]", "name=value", ...)
 */
function do_install($args)
{
	list($domain,$user,$password,$backup,$charset,$lang) = explode(',',array_shift($args));
	_fetch_user_password($user,$password);

	$config = array();
	foreach($args as $arg)
	{
		list($name,$value) = explode('=',$arg,2);
		$config[$name] = $value;
	}
	$cmd = new setup_cmd_install($domain,$user,$password,$backup,$charset,true,$config,$lang);
	echo $cmd->run()."\n";
}

/**
 * Set defaults for user and password or queries the password from the user
 *
 * @param string &$user
 * @param string &$password
 */
function _fetch_user_password(&$user,&$password)
{
	// read password from enviroment or query it from user, if not given
	if (!$user) $user = 'admin';
	if (!$password && !($password = $_SERVER['EGW_CLI_PASSWORD']))
	{
		echo lang('Admin password to header manager').' ';
		$password = trim(fgets($f = fopen('php://stdin','rb')));
		fclose($f);
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
	if (!$create)
	{
		// read password from enviroment or query it from user, if not given
		@list($password,$user) = $options = explode(',',@$arguments[0]);
		_fetch_user_password($options[1],$options[0]);
		$arguments[0] = implode(',',$options);
	}
	array_unshift($arguments,$create ? '--create-header' : '--edit-header');

	$cmd = new setup_cmd_header($create?'create':'edit',$arguments);
	echo $cmd->run()."\n";
}

/**
 * Reads the users language from the enviroment
 *
 * @param string &$charset charset set in LANG enviroment variable or the default utf-8
 * @return string 2 or 5 digit language code used in eGW
 */
function get_lang(&$charset)
{
	@list($lang,$nation,$charset) = preg_split("/[_.]/",strtolower($_SERVER['LANG']));

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
		echo '	--mailserver '.lang('host,{imap | imaps },[domain],[{standard(default)|vmailmgr = add domain for mailserver login}]')."\n";
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
		echo '--update '.lang('run a database schema update (if necessary): domain(all),[config user(admin)],password').'[,no = no backup]'."\n";
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
 *
 * @todo we need to grep for the exceptions too!
 */
function list_exit_codes()
{
	error_reporting(error_reporting() & ~E_NOTICE);

	$codes = array('Ok');
	$setup_dir = EGW_SERVER_ROOT.'/setup/';
	//$files = array('setup-cli.php');
	foreach(scandir($setup_dir.'/inc') as $file)
	{
		if (substr($file,0,strlen('class.setup_cmd')) == 'class.setup_cmd')
		{
			$files[] = 'inc/'.$file;
		}
	}
	foreach($files as $file)
	{
		$content = file_get_contents($setup_dir.'/'.$file);

		if (preg_match_all('/throw new (egw_exception[a-z_]*)\((.*),([0-9]+)\);/m',$content,$matches))
		{
			//echo $file.":\n"; print_r($matches);
			foreach($matches[3] as $key => $code)
			{
				//if (isset($codes[$code])) echo "$file redifines #$code: {$codes[$code]}\n";

				$src = $matches[2][$key];
				$src = preg_replace('/self::\$[a-z_>-]/i',"''",$src);	// gives fatal error otherwise
				@eval($src='$codes['.$code.'] = '.$src.';');
				//echo "- codes[$code] => '{$codes[$code]}'\n";
			}
			//echo $file.":\n"; print_r($codes);
		}
	}
	ksort($codes,SORT_NUMERIC);
	foreach($codes as $num => $msg)
	{
		echo $num."\t".str_replace("\n","\n\t",$msg)."\n";
	}
}
