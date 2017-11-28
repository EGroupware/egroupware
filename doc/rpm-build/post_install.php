#!/usr/bin/env php
<?php
/**
 * EGroupware - RPM post install: automatic install or update EGroupware
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author RalfBecker@outdoor-training.de
 * @version $Id$
 */

if (php_sapi_name() !== 'cli')	// security precaution: forbit calling post_install as web-page
{
	die('<h1>post_install.php must NOT be called as web-page --> exiting !!!</h1>');
}
$verbose = false;
$config = array(
	'php'         => PHP_BINARY,
	'source_dir'  => realpath(__DIR__.'/../..'),
	'data_dir'    => '/var/lib/egroupware',
	'header'      => '$data_dir/header.inc.php',	// symlinked to source_dir by rpm
	'setup-cli'   => '$source_dir/setup/setup-cli.php',
	'domain'      => 'default',
	'config_user' => 'admin',
	'config_passwd'   => randomstring(),
	'db_type'     => 'mysqli',
	'db_host'     => 'localhost',
	'db_port'     => 3306,
	'db_name'     => 'egroupware',
	'db_user'     => 'egroupware',
	'db_pass'     => randomstring(),
	'db_grant_host' => 'localhost',
	'db_root'     => 'root',	// mysql root user/pw to create database
	'db_root_pw'  => '',
	'backup'      => '',
	'admin_user'  => 'sysop',
	'admin_passwd'=> randomstring(),
	'admin_email' => '',
	'lang'        => 'en',	// languages for admin user and extra lang to install
	'charset'     => 'utf-8',
	'start_db'    => '/sbin/service mysqld',
	'autostart_db' => '/sbin/chkconfig --level 345 mysqld on',
	'start_webserver' => '/sbin/service httpd',
	'autostart_webserver' => '/sbin/chkconfig --level 345 httpd on',
	'distro'      => 'rh',
	'account-auth'  => 'sql',
	'account_min_id' => '',
	'ldap_suffix'   => 'dc=local',
	'ldap_host'     => 'localhost',
	'ldap_admin'    => 'cn=admin,$suffix',
	'ldap_admin_pw' => '',
	'ldap_base'     => 'o=$domain,$suffix',
	'ldap_root_dn'  => 'cn=admin,$base',
	'ldap_root_pw'  => randomstring(),
	'ldap_context'  => 'ou=accounts,$base',
	'ldap_search_filter' => '(uid=%user)',
	'ldap_group_context' => 'ou=groups,$base',
	'ldap_encryption_type' => '',
	'sambaadmin/sambasid'=> '',	// SID for sambaadmin
	'mailserver'    => '',
	'smtpserver'    => 'localhost,25',
	'smtp'          => '',	// see setup-cli.php --help config
	'imap'          => '',
	'sieve'         => '',
	'folder'        => '',
	'install-update-app' => '',	// install or update a single (non-default) app
	'webserver_user'=> 'apache',	// required to fix permissions
	'php5enmod'     => '',
);

// read language from LANG enviroment variable
if (($lang = isset($_ENV['LANG']) ? $_ENV['LANG'] : (isset($_SERVER['LANG']) ? $_SERVER['LANG'] : null)))
{
	@list($lang,$nat) = preg_split('/[_.]/',$lang);
	if (in_array($lang.'-'.strtolower($nat),array('es-es','pt-br','zh-tw')))
	{
		$lang .= '-'.strtolower($nat);
	}
	$config['lang'] = $lang;
}
$config['source_dir'] = dirname(dirname(dirname(__FILE__)));

/**
 * Set distribution spezific defaults
 *
 * @param string $distro =null default autodetect
 */
function set_distro_defaults($distro=null)
{
	global $config;
	if (is_null($distro))
	{
		$distro = file_exists('/etc/SuSE-release') ? 'suse' :
			(file_exists('/etc/mandriva-release') ? 'mandriva' :
			(file_exists('/etc/lsb-release') && preg_match('/^DISTRIB_ID="?Univention"?$/mi',
				file_get_contents('/etc/lsb-release')) ? 'univention' :
			(file_exists('/etc/debian_version') ? 'debian' : 'rh')));
	}
	switch (($config['distro'] = $distro))
	{
		case 'suse':
			// openSUSE 12.1+ no longer uses php5
			if (file_exists('/usr/bin/php5')) $config['php'] = '/usr/bin/php5';
			$config['start_db'] = '/sbin/service mysql';
			$config['autostart_db'] = '/sbin/chkconfig --level 345 mysql on';
			$config['start_webserver'] = '/sbin/service apache2';
			$config['autostart_webserver'] = '/sbin/chkconfig --level 345 apache2 on';
			$config['ldap_suffix'] = 'dc=site';
			$config['ldap_admin'] = $config['ldap_root_dn'] = 'cn=Administrator,$suffix';
			$config['ldap_root_pw'] = '$admin_pw';
			$config['ldap_base'] = '$suffix';
			$config['ldap_context'] = 'ou=people,$base';
			$config['ldap_group_context'] = 'ou=group,$base';
			$config['webserver_user'] = 'wwwrun';
			break;
		case 'debian':
			// service not in Debian5, only newer Ubuntu, which complains about /etc/init.d/xx
			if (file_exists('/usr/sbin/service'))
			{
				$config['start_db'] = '/usr/sbin/service mysql';
				$config['start_webserver'] = '/usr/sbin/service apache2';
			}
			else
			{
				$config['start_db'] = '/etc/init.d/mysql';
				$config['start_webserver'] = '/etc/init.d/apache2';
			}
			$config['autostart_db'] = '/usr/sbin/update-rc.d mysql defaults';
			$config['autostart_webserver'] = '/usr/sbin/update-rc.d apache2 defaults';
			$config['webserver_user'] = 'www-data';
			break;
		case 'mandriva':
			$config['ldap_suffix'] = 'dc=site';
			$config['ldap_admin'] = $config['ldap_root_dn'] = 'uid=LDAP Admin,ou=System Accounts,$suffix';
			$config['ldap_root_pw'] = '$admin_pw';
			$config['ldap_base'] = '$suffix';
			$config['ldap_context'] = 'ou=People,$base';
			$config['ldap_group_context'] = 'ou=Group,$base';
			break;
		case 'univention':
			set_univention_defaults();
			break;
		default:
			$config['distro'] = 'rh';
			// fall through
		case 'rh':
			// some MySQL packages (mysql.com, MariaDB, ...) use "mysql" as service name instead of RH default "mysqld"
			if (file_exists('/usr/bin/systemctl'))	// RHEL 7
			{
				$config['start_db'] = '/usr/bin/systemctl %s mariadb';
				$config['autostart_db'] = build_cmd('start_db', 'enable');
				$config['start_webserver'] = '/usr/bin/systemctl %s httpd';
				$config['autostart_webserver'] = build_cmd('start_webserver', 'enable');
			}
			elseif (!file_exists('/etc/init.d/mysqld') && file_exists('/etc/init.d/mysql'))
			{
				foreach(array('start_db','autostart_db') as $name)
				{
					$config[$name] = str_replace('mysqld','mysql',$config[$name]);
				}
			}
			break;
	}
}
set_distro_defaults();

// read config from command line
$argv = str_replace(array("''", '""'), '', $_SERVER['argv']);
$prog = array_shift($argv);

// check if we have EGW_POST_INSTALL set and prepend it to the command line (command line has precedence)
if (($config_set = isset($_ENV['EGW_POST_INSTALL']) ? $_ENV['EGW_POST_INSTALL'] : @$_SERVER['EGW_POST_INSTALL']))
{
	$conf = array();
	$config_set = preg_split('/[ \t]+/',trim($config_set));
	while($config_set)
	{
		$val = array_shift($config_set);
		if (($quote = $val[0]) == "'" || $quote == '"')	// arguments might be quoted with ' or "
		{
			while (substr($val,-1) != $quote)
			{
				if (!$config_set) throw new Exception('Invalid EGW_POST_INSTALL enviroment variable!');
				$val .= ' '.array_shift($config_set);
			}
			$val = substr($val,1,-1);
		}
		$conf[] = $val;
	}
	$argv = array_merge($conf,$argv);
}

$auth_type_given = false;
while(($arg = array_shift($argv)))
{
	if ($arg == '-v' || $arg == '--verbose')
	{
		$verbose = true;
	}
	elseif($arg == '-h' || $arg == '--help')
	{
		usage();
	}
	elseif($arg == '--suse')
	{
		set_distro_defaults('suse');
	}
	elseif($arg == '--distro')
	{
		set_distro_defaults(array_shift($argv));
	}
	elseif(substr($arg,0,2) == '--' && isset($config[$name=substr($arg,2)]))
	{
		$config[$name] = array_shift($argv);

		switch($name)
		{
			case 'auth_type':
				$auth_type_given = true;
				break;

			case 'account_repository':	// auth-type defaults to account-repository
				if (!$auth_type_given)
				{
					$config['auth_type'] = $config[$name];
				}
				break;
		}
	}
	else
	{
		usage("Unknown argument '$arg'!");
	}
}

$replace = array();
foreach($config as $name => $value)
{
	$replace['$'.$name] = $value;
	if (strpos($value,'$') !== false)
	{
		$config[$name] = strtr($value,$replace);
	}
}
// basic config checks
foreach(array('php','source_dir','data_dir','setup-cli') as $name)
{
	if (!file_exists($config[$name])) bail_out(1,$config[$name].' not found!');
}

// fix important php.ini and conf.d/*.ini settings
check_fix_php_apc_ini();

// not limiting memory, as backups might fail with limit we set
$setup_cli = $config['php'].' -d memory_limit=-1 '.$config['setup-cli'];

// if we have a header, include it
if (file_exists($config['header']) && filesize($config['header']) >= 200)	// default header redirecting to setup is 147 bytes
{
	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'noapi' => true,
			'currentapp' => 'login',	// stop PHP Notice: Undefined index "currentapp" in pre 16.1 header
		)
	);
	include $config['header'];

	// get user from header and replace password, as we dont know it
	$old_password = patch_header($config['header'],$config['config_user'],$config['config_passwd']);
	// register a shutdown function to put old password back in any case
	register_shutdown_function(function() use (&$config, $old_password)
	{
		patch_header($config['header'], $config['config_user'], $old_password);
	});
}
// new header or does not include requested domain (!= "default") --> new install
if (!isset($GLOBALS['egw_domain']) ||  $config['domain'] !== 'default' && !isset($GLOBALS['egw_domain'][$config['domain']]))
{
	// --> new install
	$extra_config = '';

	// check for localhost if database server is started and start it (permanent) if not
	if ($config['db_host'] == 'localhost' && $config['start_db'])
	{
		exec(build_cmd('start_db', 'status'), $dummy, $ret);
		if ($ret)
		{
			system(build_cmd('start_db', 'start'));
			if (!empty($config['autostart_db'])) system($config['autostart_db']);
		}
	}
	// create database
	$setup_db = $setup_cli.' --setup-cmd-database sub_command=create_db';
	foreach(array('domain','db_type','db_host','db_port','db_name','db_user','db_pass','db_root','db_root_pw','db_grant_host') as $name)
	{
		$setup_db .= ' '.escapeshellarg($name.'='.$config[$name]);
	}
	run_cmd($setup_db);

	// check if ldap is required and initialise it
	// we need to specify account_repository and auth_type to --install as extra config, otherwise install happens for sql!
	@list($config['account_repository'],$config['auth_type'],$rest) = explode(',',$config['account-auth'],3);
	$extra_config .= ' '.escapeshellarg('account_repository='.$config['account_repository']);
	$extra_config .= ' '.escapeshellarg('auth_type='.(empty($config['auth_type']) ? $config['account_repository'] : $config['auth_type']));
	if (empty($rest)) unset($config['account-auth']);
	if ($config['account_repository'] == 'ldap' || $config['auth_type'] == 'ldap')
	{
		// set account_min_id to 1100 if not specified to NOT clash with system accounts
		$extra_config .= ' '.escapeshellarg('account_min_id='.(!empty($config['account_min_id']) ? $config['account_min_id'] : 1100));

		$setup_ldap = $setup_cli.' --setup-cmd-ldap sub_command='.
			($config['account_repository'] == 'ldap' ? 'create_ldap' : 'test_ldap');
		foreach(array(
			'domain','ldap_suffix','ldap_host','ldap_admin','ldap_admin_pw',	// non-egw params: only used for create
			'ldap_base','ldap_root_dn','ldap_root_pw','ldap_context','ldap_search_filter','ldap_group_context',	// egw params
			'ldap_encryption_type', 'sambaadmin/sambasid',
		) as $name)
		{
			if (strpos($value=$config[$name],'$') !== false)
			{
				$config[$name] = $value = strtr($value,array(
					'$suffix' => $config['ldap_suffix'],
					'$base' => $config['ldap_base'],
					'$admin_pw' => $config['ldap_admin_pw'],
				));
			}
			$setup_ldap .= ' '.escapeshellarg($name.'='.$value);

			if (!in_array($name,array('domain','ldap_suffix','ldap_admin','ldap_admin_pw')))
			{
				$extra_config .= ' '.escapeshellarg($name.'='.$value);
			}
		}
		run_cmd($setup_ldap);
	}
	// enable mcrypt extension eg. for Ubuntu 14.04+
	if (!empty($config['php5enmod']))
	{
		run_cmd($config['php5enmod']);
	}

	// create or edit header header
	$setup_header = $setup_cli.(isset($GLOBALS['egw_domain']) ? ' --edit-header ' : ' --create-header ').
		escapeshellarg($config['config_passwd'].','.$config['config_user']).
		' --domain '.escapeshellarg($config['domain'].','.$config['db_name'].','.$config['db_user'].','.$config['db_pass'].
			','.$config['db_type'].','.$config['db_host'].','.$config['db_port']);
	run_cmd($setup_header);

	// install egroupware
	$setup_install = $setup_cli.' --install '.escapeshellarg($config['domain'].','.$config['config_user'].','.$config['config_passwd'].','.$config['backup'].','.$config['charset'].','.$config['lang'])
		.$extra_config;
	run_cmd($setup_install);

	if ($config['data_dir'] != '/var/lib/egroupware')
	{
		// set files dir different from default
		$setup_config = $setup_cli.' --config '.escapeshellarg($config['domain'].','.$config['config_user'].','.$config['config_passwd']).
			' --files-dir '.escapeshellarg($config['data_dir'].'/files').' --backup-dir '.escapeshellarg($config['data_dir'].'/backup');
		run_cmd($setup_config);
	}
	// create mailserver config (fmail requires at least minimal config given as default, otherwise fatal error)
	$setup_mailserver = $setup_cli.' --config '.escapeshellarg($config['domain'].','.$config['config_user'].','.$config['config_passwd']);
	foreach(array('account-auth','smtpserver','smtp','postfix','mailserver','imap','cyrus','sieve','folder') as $name)
	{
		if (!empty($config[$name])) $setup_mailserver .= ' --'.$name.' '.escapeshellarg($config[$name]);
	}
	run_cmd($setup_mailserver);

	// create first user
	$setup_admin = $setup_cli.' --admin '.escapeshellarg($config['domain'].','.$config['config_user'].','.$config['config_passwd'].','.
		$config['admin_user'].','.$config['admin_passwd'].',,,'.$config['admin_email'].','.$config['lang']);
	run_cmd($setup_admin);

	// check if webserver is started and start it (permanent) if not
	if ($config['start_webserver'])
	{
		exec(build_cmd('start_webserver', 'status'),$dummy,$ret);
		if ($ret)
		{
			system(build_cmd('start_webserver', 'start'));
			if (!empty($config['autostart_webserver'])) system($config['autostart_webserver']);
		}
		else
		{
			system(build_cmd('start_webserver', 'reload'));
		}
	}
	// fix egw_cache evtl. created by root, stoping webserver from accessing it
	fix_perms();

	echo "\n";
	echo "EGroupware successful installed\n";
	echo "===============================\n";
	echo "\n";
	echo "Please note the following user names and passwords:\n";
	echo "\n";
	echo "Setup username:      $config[config_user]\n";
	echo "      password:      $config[config_passwd]\n";
	echo "\n";
	echo "EGroupware username: $config[admin_user]\n";
	echo "           password: $config[admin_passwd]\n";
	echo "\n";
	echo "You can log into EGroupware by pointing your browser to http://localhost/egroupware/\n";
	echo "Please replace localhost with the appropriate hostname, if you connect remote.\n\n";

	if (empty($config['db_root_pw']))
	{
		echo "*** Database has no root password set, please fix that immediatly".
			(substr($config['db_type'], 0, 5) === 'mysql' ? ": mysqladmin -u root password NEWPASSWORD\n\n" : "!\n\n");
	}
}
else
{
	// --> existing install --> update

	// update egroupware, or single app(s), in later case skip backup
	$setup_update = $setup_cli.' --update '.escapeshellarg('all,'.$config['config_user'].','.$config['config_passwd'].
		(empty($config['install-update-app']) ? '' : ',no,'.$config['install-update-app']));
	$ret = run_cmd($setup_update,$output,array(4,15));

	switch($ret)
	{
		case 4:		// header needs an update
			$header_update = $setup_cli.' --update-header '.escapeshellarg($config['config_passwd'].','.$config['config_user']);
			run_cmd($header_update);
			$ret = run_cmd($setup_update,$output,15);
			if ($ret != 15) break;
			// fall through
		case 15:	// missing configuration (eg. mailserver)
			if (!$verbose) echo implode("\n",(array)$output)."\n";
			break;

		case 0:
			echo "\nEGroupware successful updated\n";
			break;
	}
	// fix egw_cache evtl. created by root, stoping webserver from accessing it
	fix_perms();

	if (!empty($config['start_webserver']))
	{
		// restart running Apache, to force APC to update changed sources and/or Apache configuration
		$output = array();
		run_cmd(build_cmd('start_webserver', 'status').' && '.build_cmd('start_webserver', 'restart'), $output, true);
	}
	exit($ret);
}

/**
 * Build command to execute
 *
 * @param string $cmd command or index into $config, which either incl. %s for arg or arg with be appended
 * @param string $arg argument
 * @return string
 */
function build_cmd($cmd, $arg)
{
	global $config;

	if (isset($config[$cmd])) $cmd = $config[$cmd];

	if (strpos($cmd, '%s')) return str_replace('%s', $arg, $cmd);

	return $cmd.' '.$arg;
}

/**
 * Patches a given password (for header admin) into the EGroupware header.inc.php and returns the old one
 *
 * @param string $filename
 * @param string &$user username on return(!)
 * @param string $password new password
 * @return string old password
 */
function patch_header($filename,&$user,$password)
{
	$header = file_get_contents($filename);

	$umatches = $pmatches = null;
	if (!preg_match('/'.preg_quote("\$GLOBALS['egw_info']['server']['header_admin_user'] = '", '/')."([^']+)';/m",$header,$umatches) ||
		!preg_match('/'.preg_quote("\$GLOBALS['egw_info']['server']['header_admin_password'] = '", '/')."([^']*)';/m",$header,$pmatches))
	{
		bail_out(99,"$filename is no regular EGroupware header.inc.php!");
	}
	file_put_contents($filename,preg_replace('/'.preg_quote("\$GLOBALS['egw_info']['server']['header_admin_password'] = '", '/')."([^']*)';/m",
		"\$GLOBALS['egw_info']['server']['header_admin_password'] = '".$password."';",$header));

	$user = $umatches[1];

	return $pmatches[1];
}

/**
 * Runs given shell command, exists with error-code after echoing the output of the failed command (if not already running verbose)
 *
 * @param string $cmd
 * @param array &$output=null $output of command
 * @param int|array|true $no_bailout =null exit code(s) to NOT bail out, or true to never bail out
 * @return int exit code of $cmd
 */
function run_cmd($cmd,array &$output=null,$no_bailout=null)
{
	global $verbose;

	if ($verbose)
	{
		echo $cmd."\n";
		$ret = null;
		system($cmd,$ret);
	}
	else
	{
		$output[] = $cmd;
		exec($cmd,$output,$ret);
	}
	if ($ret && $no_bailout !== true && !in_array($ret,(array)$no_bailout))
	{
		bail_out($ret,$verbose?null:$output);
	}
	return $ret;
}

/**
 * Stop programm execution with a given exit code and optional extra message
 *
 * @param int $ret =1
 * @param array|string $output line(s) to output before temination notice
 */
function bail_out($ret=1,$output=null)
{
	if ($output) echo implode("\n",(array)$output);
	echo "\n\nInstallation failed --> exiting!\n\n";
	exit($ret);
}

/**
 * Return a rand string, eg. to generate passwords
 *
 * @param int $len =16
 * @return string
 */
function randomstring($len=16)
{
	static $usedchars = array(
		'0','1','2','3','4','5','6','7','8','9','a','b','c','d','e','f',
		'g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v',
		'w','x','y','z','A','B','C','D','E','F','G','H','I','J','K','L',
		'M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z',
		'@','!','%','&','(',')','=','?',';',':','#','_','-','<',
		'>','|','[',']','}',	// dont add /\,'"{ as we have problems dealing with them
	);

	// use cryptographically secure random_int available in PHP 7+
	$func = function_exists('random_int') ? 'random_int' : 'mt_rand';

	$str = '';
	for($i=0; $i < $len; $i++)
	{
		$str .= $usedchars[$func(0,count($usedchars)-1)];
	}
	return $str;
}

/**
 * Give usage information and an optional error-message, before stoping program execution with exit-code 90 or 0
 *
 * @param string $error =null optional error-message
 */
function usage($error=null)
{
	global $prog,$config;

	echo "Usage: $prog [-h|--help] [-v|--verbose] [--distro=(suse|rh|debian)] [options, ...]\n\n";
	echo "options and their defaults:\n";
	foreach($config as $name => $default)
	{
		if (in_array($name, array('postfix','cyrus'))) continue;	// do NOT report deprecated options
		if (in_array($name,array('config_passwd','db_pass','admin_passwd','ldap_root_pw')) && strlen($config[$name]) == 16)
		{
			$default = '<16 char random string>';
		}
		echo '--'.str_pad($name,20).$default."\n";
	}
	if ($error)
	{
		echo "$error\n\n";
		exit(90);
	}
	exit(0);
}

/**
 * fix egw_cache and files_dir perms evtl. created by root, stoping webserver from accessing it
 */
function fix_perms()
{
	global $config;

	if (file_exists('/tmp/egw_cache') && !empty($config['webserver_user']))
	{
		system('/bin/chown -R '.$config['webserver_user'].' /tmp/egw_cache');
		system('/bin/chmod 700 /tmp/egw_cache');
	}
	// in case update changes something in filesystem
	if (file_exists($config['data_dir']) && !empty($config['webserver_user']))
	{
		system('/bin/chown -R '.$config['webserver_user'].' '.$config['data_dir']);
	}
}

/**
 * Set Univention UCS specific defaults
 *
 * Defaults are read from ucr registry and /etc/*.secret files
 *
 * There are 4 types of Univention servers:
 * - master DC: /etc/machine.secret, /etc/ldap.secret, ldap/server/type=master
 * - backup DC: /etc/machine.secret, /etc/ldap.secret, /etc/ldap-backup.secret, ldap/server/type=slave (not backup!)
 * - slave:     /etc/machine.secret, /etc/ldap-backup.secret, ldap/server/type=slave
 * - member:    /etc/machine.secret, no ldap/server/type
 *
 * univention-ldapsearch works on all 4 types.
 *
 * ucr get ldap/server/(ip|port) points to local ldap (not member).
 * ucr get ldap/master(/port) ldap/base points to master (on all servers)
 *
 * @todo slave and member have no /etc/ldap.secret
 */
function set_univention_defaults()
{
	global $config;

	set_distro_defaults('debian');
	$config['distro'] = 'univention';

	// set lang from ucr locale, as cloud-config at least never has anything but EN set in enviroment
	@list($lang,$nat) = preg_split('/[_.]/', _ucr_get('locale/default'));
	if (in_array($lang.'-'.strtolower($nat),array('es-es','pt-br','zh-tw')))
	{
		$lang .= '-'.strtolower($nat);
	}
	$config['lang'] = $lang;

	// mysql settings
	$config['db_root_pw'] = _ucr_secret('mysql');

	// check if ucs ldap server is configured
	if (_ucr_get('ldap/server/ip'))
	{
		// ldap settings, see http://docs.univention.de/developer-reference.html#join:secret
		$config['ldap_suffix'] = $config['ldap_base'] = _ucr_get('ldap/base');
		// port is ldap allowing starttls (zertificate/CA is correctly set in /etc/ldap/ldap.conf)
		$config['ldap_host'] = 'tls://'._ucr_get('ldap/master').':'._ucr_get('ldap/master/port');
		$config['ldap_admin'] = $config['ldap_root'] = 'cn=admin,$suffix';
		$config['ldap_admin_pw'] = $config['ldap_root_pw'] = _ucr_secret('ldap');
		$config['ldap_context'] = 'cn=users,$base';
		$config['ldap_group_context'] = 'cn=groups,$base';
		$config['ldap_search_filter'] = '(uid=%user)';

		// ldap password hash (our default blowfish_crypt seems not to work)
		$config['ldap_encryption_type'] = 'sha512_crypt';

		$config['account_min_id'] = 1200;	// UCS use 11xx for internal users/groups

		$config['account-auth'] = 'univention,ldap';

		// set sambaadmin sambaSID
		$config['sambaadmin/sambasid'] = exec('/usr/bin/univention-ldapsearch -x "(objectclass=sambadomain)" sambaSID|sed -n "s/sambaSID: \(.*\)/\1/p"');

		// mailserver, see setup-cli.php --help config
		if (($mailserver = exec('/usr/bin/univention-ldapsearch -x "(univentionAppID=mailserver_*)" univentionAppInstalledOnServer|sed -n "s/univentionAppInstalledOnServer: \(.*\)/\1/p"')) &&
			// only set on host mailserver app is installed: _ucr_get('mail/cyrus/imap') == 'yes' &&
			($domains=_ucr_get('mail/hosteddomains')))
		{
			if (!is_array($domains)) $domains = explode("\n", $domains);
			$domain = array_shift($domains);
			// set "use auth with session credentials",tls,"not user editable","further identities"
			$config['smtpserver'] = "$mailserver,465,,,yes,tls,no,yes";
			$config['smtp'] = ',Smtp\\Univention';
			$config['mailserver'] = "$mailserver,993,$domain,email,tls";
			if (_ucr_get('mail/dovecot') == 'yes')
			{
				$matches = null;
				if (file_exists('/etc/dovecot/master-users') &&
					preg_match('/^([^:]+):{PLAIN}([^:]+):/i', file_get_contents('/etc/dovecot/master-users'), $matches))
				{
					$config['imap'] = $matches[1].','.$matches[2].',Imap\\Dovecot';
				}
				else
				{
					$config['imap'] = ',,Imap\\Dovecot';
				}
				// default with sieve port to 4190, as config is only available on host mailserver app is installed
				if (!($sieve_port = _ucr_get('mail/dovecot/sieve/port'))) $sieve_port = 4190;
			}
			else
			{
				$config['imap'] = /*'cyrus,'._ucr_secret('cyrus')*/','.',Imap\\Cyrus';
				// default with sieve port to 4190, as config is only available on host mailserver app is installed
				if (!($sieve_port = _ucr_get('mail/cyrus/sieve/port'))) $sieve_port = 4190;
			}
			// set folders so mail creates them on first login, UCS does not automatic
			$config['folder'] = 'INBOX/Sent,INBOX/Trash,INBOX/Drafts,INBOX/Templates,Spam,,Ham';
			$config['sieve'] = "$mailserver,$sieve_port,starttls";
			// set an email address for sysop user so mail works right away
			$config['admin_email'] = '$admin_user@'.$domain;
		}
		# add directory of univention-directory-manager and it's sysmlink target to open_basedir
		system("/bin/sed -i 's|/usr/bin|/usr/bin:/usr/sbin:/usr/share/univention-directory-manager-tools|' /etc/egroupware/apache.conf");

	}
}

/**
 * Get a value from Univention registry
 *
 * @param string $name
 * @return string
 */
function _ucr_get($name)
{
	static $values=null;
	if (!isset($values))
	{
		$output = $matches = null;
		exec('/usr/sbin/ucr dump', $output);
		foreach($output as $line)
		{
			if (preg_match("/^([^:]+): (.*)\n?$/", $line, $matches))
			{
				$values[$matches[1]] = $matches[2];
			}
		}
	}
	return $values[$name];
}

/**
 * Read one Univention secret/password eg. _ucr_secret('mysql')
 *
 * @param string $name
 * @return string|boolean
 */
function _ucr_secret($name)
{
	if (!file_exists($filename = '/etc/'.basename($name).'.secret'))
	{
		return false;
	}
	return trim(file_get_contents($filename));
}

/**
 * Check and evtl. fix APC(u) shared memory size (apc.shm_segments * apc.shm_size) >= 64M
 *
 * We check for < 64M to allow to use that for small installs manually, but set 128M by default.
 */
function check_fix_php_apc_ini()
{
	if (extension_loaded('apc') || extension_loaded('apcu'))
	{
		$shm_size = ini_get('apc.shm_size');
		$shm_segments = ini_get('apc.shm_segments');
		// ancent APC (3.1.3) in Debian 6/Squezze has size in MB without a unit
		if (($numeric_size = is_numeric($shm_size) && $shm_size <= 1048576)) $shm_size .= 'M';

		$size = _size_with_unit($shm_size) * $shm_segments;
		//echo "shm_size=$shm_size, shm_segments=$shm_segments --> $size, numeric_size=$numeric_size\n";

		// check if we have less then 64MB (eg. default 32M) --> set it to 128MB
		if ($size < _size_with_unit('64M'))
		{
			ob_start();
			phpinfo();
			$phpinfo = ob_get_clean();
			$matches = null;
			if (preg_match('#(/[a-z0-9./-]+apcu?.ini)(,| |$)#mi', $phpinfo, $matches) &&
				file_exists($path = $matches[1]) && ($apc_ini = file_get_contents($path)))
			{
				$new_shm_size = 128 / $shm_segments;
				if (!$numeric_size) $new_shm_size .= 'M';
				if (preg_match('|^apc.shm_size\s*=\s*(\d+[KMG]?)$|m', $apc_ini))
				{
					file_put_contents($path, preg_replace('|^apc.shm_size\s*=\s*(\d+[KMG]?)$|m', 'apc.shm_size='.$new_shm_size, $apc_ini));
				}
				else
				{
					file_put_contents($path, $apc_ini."\napc.shm_size=$new_shm_size\n");
				}
				echo "Fix APC(u) configuration, set apc.shm_size=$new_shm_size in $path\n";
			}
		}
	}
}

/**
 * Convert a size with unit eg. 32M to a number
 * @param int|string $_size
 * @return int
 */
function _size_with_unit($_size)
{
	$size = (int)$_size;
	switch(strtoupper(substr($_size, -1)))
	{
		case 'G':
			$size *= 1024;
		case 'M':
			$size *= 1024;
		case 'K':
			$size *= 1024;
	}
	return $size;
}
