#!/usr/bin/php
<?php
/**
 * eGroupWare - RPM post install: automatic install or update EGroupware
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author RalfBecker@outdoor-training.de
 * @version $Id$
 */

if (isset($_SERVER['HTTP_HOST']))	// security precaution: forbit calling setup-cli as web-page
{
	die('<h1>rpm_post_install.php must NOT be called as web-page --> exiting !!!</h1>');
}
$verbose = false;
$config = array(
	'php'         => '/usr/bin/php',
	'source_dir'  => '/usr/share/egroupware',
	'data_dir'    => '/var/lib/egroupware',
	'header'      => '/var/lib/egroupware/header.inc.php',	// symlinked to source_dir by rpm
	'setup-cli'   => '/usr/share/egroupware/setup/setup-cli.php',
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
	'start_db'    => '/etc/init.d/mysqld',
	'autostart_db' => '/sbin/chkconfig --level 3 mysqld on',
	'start_webserver' => '/etc/init.d/httpd',
	'autostart_webserver' => '/sbin/chkconfig --level 3 httpd on',
);
// read language from LANG enviroment variable
if (($lang = $_ENV['LANG']))
{
	list($lang,$nat) = split('[_.]',$lang);
	if (in_array($lang.'-'.strtolower($nat),array('es-es','pt-br','zh-tw')))
	{
		$lang .= '-'.strtolower($nat);
	}
	$config['lang'] = $lang;
}
// read config from command line
$argv = $_SERVER['argv'];
$prog = array_shift($argv);

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
	elseif(substr($arg,0,2) == '--' && isset($config[$name=substr($arg,2)]))
	{
		$config[$name] = array_shift($argv);
	}
	else
	{
		usage("Unknown argument '$arg'!");
	}
}
// basic config checks
foreach(array('php','source_dir','data_dir','setup-cli') as $name)
{
	if (!file_exists($config[$name])) bail_out(1,$config[$name].' not found!');
}
$setup_cli = $config['php'].' '.$config['setup-cli'];

if (!file_exists($config['header']) || filesize($config['header']) < 200)	// default header redirecting to setup is 147 bytes
{
	// --> new install

	// create header
	@unlink($config['header']);	// remove redirect header, setup-cli stalls otherwise
	$setup_header = $setup_cli.' --create-header '.escapeshellarg($config['config_passwd'].','.$config['config_user']).
		' --domain '.escapeshellarg($config['domain'].','.$config['db_name'].','.$config['db_user'].','.$config['db_pass'].
			','.$config['db_type'].','.$config['db_host'].','.$config['db_port']);
	run_cmd($setup_header);

	// check for localhost if database server is started and start it (permanent) if not
	if ($config['db_host'] == 'localhost' && file_exists($config['start_db']))
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

	// install egroupware
	$setup_install = $setup_cli.' --install '.escapeshellarg($config['domain'].','.$config['config_user'].','.$config['config_passwd'].','.$config['backup'].','.$config['charset'].','.$config['lang']);
	run_cmd($setup_install);

	if ($config['data_dir'] != '/var/lib/egroupware')
	{
		// set files dir different from default
		$setup_config = $setup_cli.' --config '.escapeshellarg($config['domain'].','.$config['config_user'].','.$config['config_passwd']).
			' --files-dir '.escapeshellarg($config['data_dir'].'/files').' --backup-dir '.escapeshellarg($config['data_dir'].'/backup');
		run_cmd($setup_config);
	}

	// create first user
	$setup_admin = $setup_cli.' --admin '.escapeshellarg($config['domain'].','.$config['config_user'].','.$config['config_passwd'].','.
		$config['admin_user'].','.$config['admin_passwd'].',,,,'.$config['lang']);
	run_cmd($setup_admin);

	// check if webserver is started and start it (permanent) if not
	if (file_exists($config['start_webserver']))
	{
		if (exec($config['start_webserver'].' status',$dummy,$ret) && $ret)
		{
			system($config['start_webserver'].' start');
			system($config['autostart_webserver']);
		}
	}
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
	$header = file_get_contents($config['header']);
	if (!preg_match('/'.preg_quote("\$GLOBALS['egw_info']['server']['header_admin_user'] = '")."([^']+)';/m",$header,$matches))
	{
		bail_out(1,$config['header']." is no regular EGroupware header.inc.php!");
	}
	$tmp_header= preg_replace('/'.preg_quote("\$GLOBALS['egw_info']['server']['header_admin_password'] = '")."([^']+)';/m",
		"\$GLOBALS['egw_info']['server']['header_admin_password'] = '".$config['config_passwd']."';",$header);
	file_put_contents($config['header'],$tmp_header);

	// update egroupware
	$setup_update = $setup_cli.' --update '.escapeshellarg('all,'.$config['config_user'].','.$config['config_passwd']);
	run_cmd($setup_update);

	// restore original header
	file_put_contents($config['header'],$header);

	echo "EGroupware successful updated\n";
}

function run_cmd($cmd,array &$output=null)
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
	if ($ret) bail_out($ret,$verbose?null:$output);
}

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

function usage($error=null)
{
	global $prog,$config;
	
	echo "Usage: $prog [-h|--help] [-v|--verbose] [options, ...]\n\n";
	echo "options and their defaults:\n";
	foreach($config as $name => $default)
	{
		if (in_array($name,array('config_passwd','db_pass','admin_passwd'))) $default = '<16 char random string>';
		echo '--'.str_pad($name,20).$default."\n";
	}
	if ($error) echo "$error\n\n";
	exit(1);
}
