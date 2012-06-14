#!/usr/bin/php
<?php
/**
 * EGroupware - RPM post install: automatic install or update EGroupware
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author RalfBecker@outdoor-training.de
 * @version $Id$
 */

if (isset($_SERVER['HTTP_HOST']))	// security precaution: forbit calling post_install as web-page
{
	die('<h1>rpm_post_install.php must NOT be called as web-page --> exiting !!!</h1>');
}
$verbose = false;
$config = array(
	'php'         => '/usr/bin/php',
	'pear'        => '/usr/bin/pear',
	'source_dir'  => '/usr/share/egroupware',
	'data_dir'    => '/var/lib/egroupware',
	'header'      => '$data_dir/header.inc.php',	// symlinked to source_dir by rpm
	'setup-cli'   => '$source_dir/setup/setup-cli.php',
	'domain'      => 'default',
	'config_user' => 'admin',
	'config_passwd'   => randomstring(),
	'db_type'     => 'mysql',
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
	'mailserver'    => '',
	'smtpserver'    => 'localhost,25',
	'postfix'       => '',	// see setup-cli.php --help config
	'cyrus'         => '',
	'sieve'         => '',
	'install-update-app' => '',	// install or update a single (non-default) app
	'webserver_user'=> 'apache',	// required to fix permissions
);

// read language from LANG enviroment variable
if (($lang = isset($_ENV['LANG']) ? $_ENV['LANG'] : $_SERVER['LANG']))
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
 * @param string $distro=null default autodetect
 */
function set_distro_defaults($distro=null)
{
	global $config;
	if (is_null($distro))
	{
		$distro = file_exists('/etc/SuSE-release') ? 'suse' : (file_exists('/etc/debian_version') ? 'debian' :
			(file_exists('/etc/mandriva-release') ? 'mandriva' : 'rh'));
	}
	switch (($config['distro'] = $distro))
	{
		case 'suse':
			// openSUSE 12.1+ no longer uses php5
			if (file_exists('/usr/bin/php5')) $config['php'] = '/usr/bin/php5';
			if (file_exists('/usr/bin/pear5')) $config['pear'] = '/usr/bin/pear5';
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
		default:
			$config['distro'] = 'rh';
			// fall through
		case 'rh':
			// some MySQL packages (mysql.com, MariaDB, ...) use "mysql" as service name instead of RH default "mysqld"
			if (!file_exists('/etc/init.d/mysqld') && file_exists('/etc/init.d/mysql'))
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
$argv = $_SERVER['argv'];
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
$setup_cli = $config['php'].' -d memory_limit=256M '.$config['setup-cli'];

if (!file_exists($config['header']) || filesize($config['header']) < 200)	// default header redirecting to setup is 147 bytes
{
	// --> new install
	$extra_config = '';

	// check for localhost if database server is started and start it (permanent) if not
	if ($config['db_host'] == 'localhost' && $config['start_db'])
	{
		if (exec($config['start_db'].' status',$dummy,$ret) && $ret)
		{
			system($config['start_db'].' start');
			system($config['autostart_db']);
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

	// create header
	$setup_header = $setup_cli.' --create-header '.escapeshellarg($config['config_passwd'].','.$config['config_user']).
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
	foreach(array('account-auth','smtpserver','postfix','mailserver','cyrus','sieve') as $name)
	{
		if (!empty($config[$name])) $setup_mailserver .= ' --'.$name.' '.escapeshellarg($config[$name]);
	}
	run_cmd($setup_mailserver);

	// create first user
	$setup_admin = $setup_cli.' --admin '.escapeshellarg($config['domain'].','.$config['config_user'].','.$config['config_passwd'].','.
		$config['admin_user'].','.$config['admin_passwd'].',,,,'.$config['lang']);
	run_cmd($setup_admin);

	// check if webserver is started and start it (permanent) if not
	if ($config['start_webserver'])
	{
		if (exec($config['start_webserver'].' status',$dummy,$ret) && $ret)
		{
			system($config['start_webserver'].' start');
			system($config['autostart_webserver']);
		}
		else
		{
			system($config['start_webserver'].' reload');
		}
	}
	// install/upgrade required pear packages
	check_install_pear_packages();
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
		echo "*** Database has no root password set, please fix that immediatly: mysqladmin -u root password NEWPASSWORD\n\n";
	}
}
else
{
	// --> existing install --> update

	// get user from header and replace password, as we dont know it
	$old_password = patch_header($config['header'],$config['config_user'],$config['config_passwd']);
	// register a shutdown function to put old password back in any case
	register_shutdown_function('patch_header',$config['header'],$config['config_user'],$old_password);

	// update egroupware
	$setup_update = $setup_cli.' --update '.escapeshellarg('all,'.$config['config_user'].','.$config['config_passwd'].',,'.$config['install-update-app']);
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
	// install/upgrade required pear packages
	check_install_pear_packages();
	// fix egw_cache evtl. created by root, stoping webserver from accessing it
	fix_perms();

	// restart running Apache, to force APC to update changed sources and/or Apache configuration
	$output = array();
	run_cmd($config['start_webserver'].' status && '.$config['start_webserver'].' restart', $output, true);

	exit($ret);
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

	if (!preg_match('/'.preg_quote("\$GLOBALS['egw_info']['server']['header_admin_user'] = '")."([^']+)';/m",$header,$umatches) ||
		!preg_match('/'.preg_quote("\$GLOBALS['egw_info']['server']['header_admin_password'] = '")."([^']*)';/m",$header,$pmatches))
	{
		bail_out(99,"$filename is no regular EGroupware header.inc.php!");
	}
	file_put_contents($filename,preg_replace('/'.preg_quote("\$GLOBALS['egw_info']['server']['header_admin_password'] = '")."([^']*)';/m",
		"\$GLOBALS['egw_info']['server']['header_admin_password'] = '".$password."';",$header));

	$user = $umatches[1];

	return $pmatches[1];
}

/**
 * Runs given shell command, exists with error-code after echoing the output of the failed command (if not already running verbose)
 *
 * @param string $cmd
 * @param array &$output=null $output of command
 * @param int|array|true $no_bailout=null exit code(s) to NOT bail out, or true to never bail out
 * @return int exit code of $cmd
 */
function run_cmd($cmd,array &$output=null,$no_bailout=null)
{
	global $verbose;

	if ($verbose)
	{
		echo $cmd."\n";
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
 * @param int $ret=1
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
 * @param int $len=16
 * @return string
 */
function randomstring($len=16)
{
	static $usedchars = array(
		'0','1','2','3','4','5','6','7','8','9','a','b','c','d','e','f',
		'g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v',
		'w','x','y','z','A','B','C','D','E','F','G','H','I','J','K','L',
		'M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z',
		'@','!','$','%','&','/','(',')','=','?',';',':','#','_','-','<',
		'>','|','{','[',']','}',	// dont add \,'" as we have problems dealing with them
	);

	$str = '';
	for($i=0; $i < $len; $i++)
	{
		$str .= $usedchars[mt_rand(0,count($usedchars)-1)];
	}
	return $str;
}

/**
 * Give usage information and an optional error-message, before stoping program execution with exit-code 90 or 0
 *
 * @param string $error=null optional error-message
 */
function usage($error=null)
{
	global $prog,$config;

	echo "Usage: $prog [-h|--help] [-v|--verbose] [--distro=(suse|rh|debian)] [options, ...]\n\n";
	echo "options and their defaults:\n";
	foreach($config as $name => $default)
	{
		if (in_array($name,array('config_passwd','db_pass','admin_passwd','ldap_root_pw')))
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
 * Check if required PEAR packges are installed and install them if not, update pear packages with to low version
 */
function check_install_pear_packages()
{
	global $config;

	exec($config['pear'].' list',$out,$ret);
	if ($ret)
	{
		echo "Error running pear command ($config[pear])!\n";
		exit(95);
	}
	$packages_installed = array();
	foreach($out as $n => $line)
	{
		if (preg_match('/^([a-z0-9_]+)\s+([0-9.]+[a-z0-9]*)\s+([a-z]+)/i',$line,$matches))
		{
			$packages_installed[$matches[1]] = $matches[2];
		}
	}
	// read required packages from apps
	$packages = array('PEAR' => true, 'HTTP_WebDAV_Server' => '999.egw-pear');	// pear must be the first, to run it's update first!
	$egw_pear_packages = array();
	foreach(scandir($config['source_dir']) as $app)
	{
		if (is_dir($dir=$config['source_dir'].'/'.$app) && file_exists($file=$dir.'/setup/setup.inc.php')) include $file;
	}
	foreach($setup_info as $app => $data)
	{
		if (isset($data['check_install']))
		{
			foreach($data['check_install'] as $package => $args)
			{
				if ($args['func'] == 'pear_check')
				{
					if (!$package) $package = 'PEAR';
					// only overwrite lower version or no version
					if (!isset($packages[$package]) || $packages[$package] === true || isset($args['version']) && version_compare($args['version'],$packages[$package],'>'))
					{
						$packages[$package] = isset($args['version']) ? $args['version'] : true;
					}
				}
			}
		}
		if ($app == 'egw-pear')
		{
			$egw_pear_packages['HTTP_WebDAV_Server'] = $egw_pear_packages['Net_IMAP'] = $egw_pear_packages['Net_Sieve'] = $egw_pear_packages['Log'] = '999.egw-pear';
		}
	}
	//echo 'Installed: '; print_r($packages_installed);
	//echo 'egw-pear: '; print_r($egw_pear_packages);
	//echo 'Required: '; print_r($packages);
	$to_install = array_diff(array_keys($packages),array_keys($packages_installed),array_keys($egw_pear_packages));

	$need_upgrade = array();
	foreach($packages as $package => $version)
	{
		if ($version !== true && isset($packages_installed[$package]) &&
			version_compare($version, $packages_installed[$package], '>'))
		{
			$need_upgrade[] = $package;
		}
	}
	//echo 'Need upgrade: '; print_r($need_upgrade);
	//echo 'To install: '; print_r($to_install);
	if (($to_install || $need_upgrade))
	{
		if (getmyuid())
		{
			echo "You need to run as user root to be able to install/upgrade required PEAR packages!\n";
		}
		else
		{
			echo "Install/upgrade required PEAR packages:\n";
			// need to run upgrades first, they might be required for install!
			if ($need_upgrade)
			{
				if (in_array('PEAR',$need_upgrade))	// updating pear itself can be very tricky, this is what's needed for stock RHEL pear
				{
					$cmd = $config['pear'].' channel-update pear.php.net';
					echo "$cmd\n";	system($cmd);
					$cmd = $config['pear'].' upgrade --force Console_Getopt Archive_Tar';
					echo "$cmd\n";	system($cmd);
				}
				$cmd = $config['pear'].' upgrade '.implode(' ',$need_upgrade);
				echo "$cmd\n";	system($cmd);
			}
			if ($to_install)
			{
				$cmd = $config['pear'].' install '.implode(' ',$to_install);
				echo "$cmd\n";	system($cmd);
			}
		}
	}
}

function lang() {}	// required to be able to include */setup/setup.inc.php files

/**
 * fix egw_cache perms evtl. created by root, stoping webserver from accessing it
 */
function fix_perms()
{
	global $config;

	if (file_exists('/tmp/egw_cache'))
	{
		system('/bin/chown -R '.$config['webserver_user'].' /tmp/egw_cache');
		system('/bin/chmod 700 /tmp/egw_cache');
	}
}
