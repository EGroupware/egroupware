<?php
/**
 * eGroupWare Setup - Check installation enviroment
 *
 * @link http://www.egroupware.org
 * @package setup
 * @author Miles Lott <milos@groupwhere.org>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */
$run_by_webserver = !!$_SERVER['PHP_SELF'];
$is_windows = strtoupper(substr(PHP_OS,0,3)) == 'WIN';

if ($run_by_webserver)
{
	$safe_er = error_reporting();
	include ('./inc/functions.inc.php');
	error_reporting($safe_er);

	$GLOBALS['egw_info']['setup']['stage']['header'] = $GLOBALS['egw_setup']->detection->check_header();
	if ($GLOBALS['egw_info']['setup']['stage']['header'] == '10')
	{
		// Check header and authentication
		if (!$GLOBALS['egw_setup']->auth('Config') && !$GLOBALS['egw_setup']->auth('Header'))
		{
			Header('Location: index.php');
			exit;
		}
	}
	$passed_icon = '<img src="templates/default/images/completed.png" title="Passed" alt="Passed" align="middle" />';
	$error_icon = '<img src="templates/default/images/incomplete.png" title="Error" alt="Error" align="middle" />';
	$warning_icon = '<img src="templates/default/images/dep.png" title="Warning" alt="Warning" align="middle" />';
}
else
{
	$passed_icon = '>>> Passed ';
	$error_icon = '*** Error: ';
	$warning_icon = '!!! Warning: ';

	function lang($msg,$arg1=NULL,$arg2=NULL,$arg3=NULL,$arg4=NULL)
	{
		return is_null($arg1) ? $msg : str_replace(array('%1','%2','%3','%4'),array($arg1,$arg2,$arg3,$arg4),$msg);
	}
}

$checks = array(
	'phpversion' => array(
		'func' => 'php_version',
		'value' => $GLOBALS['egw_setup']->required_php_version,
		'verbose_value' => $GLOBALS['egw_setup']->required_php_version.'+',
		'recommended' => $GLOBALS['egw_setup']->recommended_php_version,
	),
	'safe_mode' => array(
		'func' => 'php_ini_check',
		'value' => 0,
		'verbose_value' => 'Off',
		'warning' => lang('safe_mode is turned on, which is generaly a good thing as it makes your install more secure.')."\n".
			lang('If safe_mode is turned on, eGW is not able to change certain settings on runtime, nor can we load any not yet loaded module.')."\n".
			lang('*** You have to do the changes manualy in your php.ini (usualy in /etc on linux) in order to get eGW fully working !!!')."\n".
			lang('*** Do NOT update your database via setup, as the update might be interrupted by the max_execution_time, which leaves your DB in an unrecoverable state (your data is lost) !!!')
	),
	'magic_quotes_runtime' => array(
		'func' => 'php_ini_check',
		'value' => 0,
		'verbose_value' => 'Off',
		'safe_mode' => 'magic_quotes_runtime = Off'
	),
	'register_globals' => array(
		'func' => 'php_ini_check',
		'value' => 0,
		'verbose_value' => 'Off',
		'warning' => lang("register_globals is turned On, eGroupWare does NOT require it and it's generaly more secure to have it turned Off")
	),
	'display_errors' => array(
		'func' => 'php_ini_check',
		'value' => 0,
		'verbose_value' => 'Off',
		'warning' => lang('%1 is set to %2. This is NOT recommeded for a production system, as displayed error messages can contain passwords or other sensitive information!','display_errors',ini_get('display_errors')),
	),
	'memory_limit' => array(
		'func' => 'php_ini_check',
		'value' => '24M',
		'check' => '>=',
		'error' => lang('memory_limit is set to less than %1: some applications of eGroupWare need more than the recommend 8M, expect occasional failures','24M'),
		'change' => 'memory_limit = 24M'
	),
	'max_execution_time' => array(
		'func' => 'php_ini_check',
		'value' => 30,
		'check' => '>=',
		'error' => lang('max_execution_time is set to less than 30 (seconds): eGroupWare sometimes needs a higher execution_time, expect occasional failures'),
		'safe_mode' => 'max_execution_time = 30'
	),
	'file_uploads' => array(
		'func' => 'php_ini_check',
		'value' => 1,
		'verbose_value' => 'On',
		'error' => lang('File uploads are switched off: You can NOT use any of the filemanagers, nor can you attach files in several applications!'),
	),
	'upload_max_filesize' => array(
		'func' => 'php_ini_check',
		'value' => '8M',
		'check' => '>=',
		'error' => lang('%1 is set to %2, you will NOT be able to upload or attach files bigger then that!','upload_max_filesize',ini_get('upload_max_filesize')),
		'change' => 'upload_max_filesize = 8M'
	),
	'post_max_size' => array(
		'func' => 'php_ini_check',
		'value' => '8M',
		'check' => '>=',
		'error' => lang('%1 is set to %2, you will NOT be able to upload or attach files bigger then that!','post_max_size',ini_get('max_post_size')),
		'change' => 'post_max_size = 8M'
	),
	'session' => array(
		'func' => 'extension_check',
		'error' => lang('The session extension is required!')
	),
	'include_path' => array(
		'func' => 'php_ini_check',
		'value' => '.',
		'check' => 'contain',
		'error' => lang('include_path need to contain "." - the current directory'),
	),
	'date.timezone' => array(
		'func' => 'php_ini_check',
		'value' => 'System/Localtime',
		'verbose_value' => '"System/Localtime"',
		'check' => '!=',
		'error' => lang('No VALID timezone set! ("%1" is NOT sufficient, you have to use a timezone identifer like "%2", see %3full list of valid identifers%4)',
			'System/Localtime','Europe/Berlin','<a href="http://www.php.net/manual/en/timezones.php" target="_blank">','</a>'),
	),
	'pdo' => array(
		'func' => 'extension_check',
		'error' => lang('The PDO extension plus a database specific driver is needed by the VFS (virtual file system)!'),
	),
	'mysql' => array(
		'func' => 'extension_check',
		'warning' => lang('The %1 extension is needed, if you plan to use a %2 database.','mysql','MySQL')
	),
	'pdo_mysql' => array(
		'func' => 'extension_check',
		'warning' => lang('The %1 extension is needed, if you plan to use a %2 database.','pdo_mysql','MySQL')
	),
	'pgsql' => array(
		'func' => 'extension_check',
		'warning' => lang('The %1 extension is needed, if you plan to use a %2 database.','pgsql','pgSQL')
	),
	'pdo_pgsql' => array(
		'func' => 'extension_check',
		'warning' => lang('The %1 extension is needed, if you plan to use a %2 database.','pdo_pgsql','pgSQL')
	),
	'mssql' => array(
		'func' => 'extension_check',
		'warning' => lang('The %1 extension is needed, if you plan to use a %2 database.','mssql','MsSQL'),
		'win_only' => True
	),
	'pdo_dblib' => array(
		'func' => 'extension_check',
		'warning' => lang('The %1 extension is needed, if you plan to use a %2 database.','pdo_dblib','MsSQL'),
		'win_only' => True
	),
	'odbc' => array(
		'func' => 'extension_check',
		'warning' => lang('The %1 extension is needed, if you plan to use a %2 database.','odbc','MaxDB, MsSQL or Oracle'),
	),
	'pdo_odbc' => array(
		'func' => 'extension_check',
		'warning' => lang('The %1 extension is needed, if you plan to use a %2 database.','pdo_odbc','MaxDB, MsSQL or Oracle'),
	),
	'oci8' => array(
		'func' => 'extension_check',
		'warning' => lang('The %1 extension is needed, if you plan to use a %2 database.','oci','Oracle'),
	),
	'pdo_oci' => array(
		'func' => 'extension_check',
		'warning' => lang('The %1 extension is needed, if you plan to use a %2 database.','pdo_oci','Oracle'),
	),
	'mbstring' => array(
		'func' => 'extension_check',
		'warning' => lang('The mbstring extension is needed to fully support unicode (utf-8) or other multibyte-charsets.')
	),
	'mbstring.func_overload' => array(
		'func' => 'php_ini_check',
		'value' => 7,
		'warning' => '<div class="setup_info">' . lang('The mbstring.func_overload = 7 is needed to fully support unicode (utf-8) or other multibyte-charsets.') . "</div>",
		'change' => check_load_extension('mbstring') ? 'mbstring.func_overload = 7' : '',
	),
	'ldap' => array(
		'func' => 'extension_check',
		'warning' => lang("The ldap extension is needed, if you use ldap as account or contact storage, authenticate against ldap or active directory. It's not needed for a standard SQL installation."),
	),
	'' => array(
		'func' => 'pear_check',
		'error' => lang('PEAR extensions are required by many eGroupware applications, PEAR itself is the required basis for each extension!'),
	),
	'HTTP_WebDAV_Server' => array(
		'func' => 'pear_check',
		'from' => 'WebDAV',
	),
	realpath('..') => array(
		'func' => 'permission_check',
		'is_world_writable' => False,
		'only_if_exists' => true,	// quitens "file does not exist" for doc symlinks in Debian to files outside open_basedir
		'recursiv' => True
	),
	realpath('../header.inc.php') => array(
		'func' => 'permission_check',
		'is_world_readable' => False,
		'only_if_exists' => @$GLOBALS['egw_info']['setup']['stage']['header'] != 10
	),
	'ctype' => array(
		'func' => 'extension_check',
		'error' => lang("The ctype extension is needed by HTMLpurifier to check content of FCKeditor agains Cross Site Skripting."),
	),
	'mcrypt' => array(
		'func' => 'extension_check',
		'warning' => lang('The mcrypt extension is used, to store eTemplate form state on the client, which is the prefered way to store it.'),
	),
	'json' => array(
		'func' => 'extension_check',
		'error' => lang('The json extension is required by EGroupware for AJAX.'),
	),
	'tidy' => array(
		'func' => 'extension_check',
		'warning' => lang('The tidy extension is need in merge-print to clean up html before inserting it in office documents.'),
	),
	'xmlreader' => array(
		'func' => 'extension_check',
		'error' => lang('The xmlreader extension is required by EGroupware in several applications.'),
	),
);
if (extension_loaded('session') && ini_get('session.save_handler') == 'files' && ($session_path = realpath(session_save_path())))
{
	$sp_visible = true;
	if (($open_basedir = ini_get('open_basedir')) && $open_basedir != 'none')
	{
		foreach(explode(PATH_SEPARATOR,$open_basedir) as $dir)
		{
			$dir = realpath($dir);
			if (($sp_visible = substr($session_path,0,strlen($dir)) == $dir)) break;
		}
	}
	if ($sp_visible)	// only check if session_save_path is visible by webserver
	{
		$checks[$session_path] = array(
			'func' => 'permission_check',
			'is_writable' => true,
			'msg' => lang("Checking if php.ini setting session.save_path='%1' is writable by the webserver",session_save_path()),
			'error' => lang('You will NOT be able to log into eGroupWare using PHP sessions: "session could not be verified" !!!'),
		);
	}
}
$setup_info = $GLOBALS['egw_setup']->detection->get_versions();
foreach($setup_info as $app => $app_data)
{
	if (!isset($app_data['check_install'])) continue;

	foreach ($app_data['check_install'] as $name => $data)
	{
		if (isset($checks[$name]))
		{
			if ($checks[$name] == $data) continue;	// identical check --> ignore it

			if ($data['func'] == 'pear_check' || in_array($data['func'],array('extension_check','php_ini_check')) && !isset($data['warning']))
			{
				if (isset($checks[$name]['from']) && $checks[$name]['from'] && !is_array($checks[$name]['from']))
				{
					$checks[$name]['from'] = array($checks[$name]['from']);
				}
				if (!isset($data['from'])) $data['from'] = $app;
				if (!isset($checks[$name]['from']) || !is_array($checks[$name]['from'])) $checks[$name]['from'] = array();
				if (!in_array($data['from'],$checks[$name]['from'])) $checks[$name]['from'][] = $data['from'];
			}
			else
			{
				$checks[$app.'_'.$name] = $data;
			}
		}
		else
		{
			if (!isset($data['from'])) $data['from'] = $app;
			$checks[$name] = $data;
		}
		//echo "added check $data[func]($name) for $app"; _debug_array($data);
	}
}
$sorted_checks = array();
foreach(array('php_version','php_ini_check','extension_check','pear_check','gd_check','permission_check') as $func)
{
	foreach($checks as $name => $data)
	{
		if ($data['func'] == $func)
		{
			$sorted_checks[$name] = $data;
			unset($checks[$name]);
		}
	}
}
if ($checks) $sorted_checks += $checks;
$checks =& $sorted_checks;

function php_version($name,$args)
{
	global $passed_icon, $error_icon;

	$version_ok = version_compare(phpversion(),$args['value']) >= 0;

	echo '<div>'.($version_ok ? $passed_icon : $error_icon).' <span'.($version_ok ? '' : ' class="setup_error"').'>'.
		lang('Checking required PHP version %1 (recommended %2)',$args['verbose_value'],$args['recommended']).': '.
		phpversion().' ==> '.($version_ok ? lang('True') : lang('False'))."</span></div>\n";
}

/**
 * quering the pear registry to find out which pear packages and versions are installed
 *
 * @return array with package-name => version pairs, eg. array('Log' => '1.9.8','PEAR' => '1.4.11')
 */
function get_installed_pear_packages()
{
	$pear_config = '';	// use the system default
	// fix for SuSE having the pear.conf only for cli, will fail with open_basedir - no idea what to do then
	if (@is_dir('/etc/php5/apache2') && !file_exists('/etc/php5/apache2/pear.conf') && @file_exists('/etc/php5/cli/pear.conf'))
	{
		$pear_config = '/etc/php5/cli/pear.conf';
	}
	@include_once 'PEAR/Config.php';

	if (!class_exists('PEAR_Config')) return false;

	$config = new PEAR_Config('',$pear_config);
	//echo "<pre>config = ".print_r($config,true)."</pre>\n";

	$channel = $config->get('default_channel');
	//echo "<pre>channel = ".print_r($channel,true)."</pre>\n";

	if (!method_exists($config,'getRegistry')) return false;	// PEAR version to old

	$reg = &$config->getRegistry();
	//echo "<pre>reg = ".print_r($reg,true)."</pre>\n";

	// a bug in pear causes an endless loop if the install-dir does not exist
	// bug reported: http://pear.php.net/bugs/bug.php?id=11317
	if (!file_exists($reg->install_dir)) return false;

	$installed = $reg->packageInfo(null,null,$channel);

	//echo "<pre>installed =".print_r($installed,true)."</pre>\n";
	$packages = array();
	foreach($installed as $package)
	{
		$name = isset($package['package']) ? $package['package'] : $package['name'];
		$version = $package['version'];
		if (is_array($version)) $version = $version['release'];

		$packages[$name] = $version;
	//	echo "<p>$name: ".print_r($package['version'],true)."</p>\n";
	}
	ksort($packages);

	return $packages;
}

function pear_check($package,$args)
{
	global $passed_icon, $warning_icon;
	static $pear_available = null;
	static $pear_packages = null;

	$min_version = isset($args['version']) ? $args['version'] : null;

	if (is_null($pear_packages))
	{
		$pear_packages = get_installed_pear_packages();
	}
	$version_available = false;

	// check if egw-pear is availible and packages is included
	if ($package && is_dir('../egw-pear') && file_exists('../egw-pear/'.str_replace('_','/',$package).'.php'))
	{
		$available = true;
		$version_available = '999.egw-pear';
	}
	// packages found in the pear registry --> use that info
	elseif ($pear_packages)
	{
		$pear_available = true;
		// check if package is installed
		if ($package && isset($pear_packages[$package])) $available = true;
		// check if it's the right version
		$version_available = $pear_packages[$package ? $package : 'PEAR'];
	}
	else	// use the old checks as fallback
	{
		if (is_null($pear_available))
		{
			$pear_available = @include_once('PEAR.php');

			if (!class_exists('PEAR')) $pear_available = false;
		}
		if ($pear_available && $package)
		{
			$file = str_replace('_','/',$package).'.php';

			$available = @include_once($file);

			if (!class_exists($package)) $available = false;
		}
	}
	// is the right version availible
	$available = (@$available || $pear_available && !$package) && (!$min_version || version_compare($min_version,$version_available) <= 0);
	echo '<div>'.($available ? $passed_icon : $warning_icon).' <span'.($available ? '' : ' class="setup_warning"').'>'.
		lang('Checking PEAR%1 is installed',($package?'::'.$package:'').($min_version?" ($min_version)":'')).': '.
		($available ? ($version_available ? $version_available : lang('True')) : lang('False'))."</span></div>\n";

	if (!$available)	// give further info only if not availible
	{
		echo '<div class="setup_info">' . lang('PEAR%1 is needed by: %2.',$package ? '::'.$package : '',
			is_array($args['from']) ? implode(', ',$args['from']) : $args['from']);

		if (!$pear_available)
		{
			echo ' '.lang('PEAR (%1) is a PHP repository and is usually in a package called %2.',
				'<a href="http://pear.php.net" target="_blank">pear.php.net</a>','php-pear');
		}
		elseif ($min_version && !$version_available)
		{
			echo ' '.lang('We could not determine the version of %1, please make sure it is at least %2',$package,$min_version);
		}
		elseif ($min_version && version_compare($min_version,$version_available) > 0)
		{
			echo ' '.lang('Your installed version of %1 is %2, required is at least %3, please run: ',
				$package,$version_available,$min_version).' pear update '.$package;
		}
		elseif ($package)
		{
			echo ' '.lang('You can install it by running:').' pear install '.$package;
		}
		echo "</div>";
	}
	echo "\n";

	return $available;
}

function extension_check($name,$args)
{
	//echo "<p>extension_check($name,".print_r($args,true).")</p>\n";
	global $passed_icon, $error_icon, $warning_icon, $is_windows;

	if (isset($args['win_only']) && $args['win_only'] && !$is_windows)
	{
		return True;	// check only under windows
	}
	// we check for the existens of 'dl', as multithreaded webservers dont have it !!!
	$available = check_load_extension($name);

	echo '<div>'.($available ? $passed_icon : $warning_icon).' <span'.($available ? '' : ' class="setup_warning"').'>'.lang('Checking extension %1 is loaded or loadable',$name).': '.($available ? lang('True') : lang('False'))."</span></div>\n";

	if (!$available)
	{
		if (!isset($args['warning']))
		{
			$args['warning'] = lang('The %1 extension is needed from: %2.',$name,
				is_array($args['from']) ? implode(', ',$args['from']) : $args['from']);
		}
		echo "<div class='setup_info'>".$args['warning'].'</div>';
	}
	echo "\n";

	return $available;
}

function function_check($name,$args)
{
	global $passed_icon, $error_icon, $warning_icon, $is_windows;

	$available = function_exists($name);

	echo '<div>'.($available ? $passed_icon : $warning_icon).' <span'.($available ? '' : ' class="setup_warning"').'>'.lang('Checking function %1 exists',$name).': '.($available ? lang('True') : lang('False'))."</span></div>\n";

	if (!$available)
	{
		if (!isset($args['warning']))
		{
			$args['warning'] = lang('The function %1 is needed from: %2.',$name,
				is_array($args['from'] ? implode(', ',$args['from']) : $args['from']));
		}
		echo "<div class='setup_info'>".$args['warning'].'</div>';
	}
	echo "\n";

	return $available;
}

function verbosePerms( $in_Perms )
{
	if($in_Perms & 0x1000)     // FIFO pipe
	{
		$sP = 'p';
	}
	elseif($in_Perms & 0x2000) // Character special
	{
		$sP = 'c';
	}
	elseif($in_Perms & 0x4000) // Directory
	{
		$sP = 'd';
	}
	elseif($in_Perms & 0x6000) // Block special
	{
		$sP = 'b';
	}
	elseif($in_Perms & 0x8000) // Regular
	{
		$sP = '-';
	}
	elseif($in_Perms & 0xA000) // Symbolic Link
	{
		$sP = 'l';
	}
	elseif($in_Perms & 0xC000) // Socket
	{
		$sP = 's';
	}
	else                         // UNKNOWN
	{
		$sP = 'u';
	}

	// owner
	$sP .= (($in_Perms & 0x0100) ? 'r' : '-') .
	(($in_Perms & 0x0080) ? 'w' : '-') .
	(($in_Perms & 0x0040) ? (($in_Perms & 0x0800) ? 's' : 'x' ) :
	(($in_Perms & 0x0800) ? 'S' : '-'));

	// group
	$sP .= (($in_Perms & 0x0020) ? 'r' : '-') .
	(($in_Perms & 0x0010) ? 'w' : '-') .
	(($in_Perms & 0x0008) ? (($in_Perms & 0x0400) ? 's' : 'x' ) :
	(($in_Perms & 0x0400) ? 'S' : '-'));

	// world
	$sP .= (($in_Perms & 0x0004) ? 'r' : '-') .
	(($in_Perms & 0x0002) ? 'w' : '-') .
	(($in_Perms & 0x0001) ? (($in_Perms & 0x0200) ? 't' : 'x' ) :
	(($in_Perms & 0x0200) ? 'T' : '-'));
	return $sP;
}

function permission_check($name,$args,$verbose=True)
{
	global $passed_icon, $error_icon, $warning_icon,$is_windows;
	//echo "<p>permision_check('$name',".print_r($args,True).",'$verbose')</p>\n";

	// add a ../ for non-absolute pathes
	$rel_name = $name;
	if ($name && substr($name,0,3) != '../' && $name[0] != '/' && $name[0] != '\\' && strpos($name,':') === false)
	{
		$name = '../'.$name;
	}

	if (!file_exists($name) && isset($args['only_if_exists']) && $args['only_if_exists'])
	{
		return True;
	}

	$perms = $checks = '';
	if (file_exists($name))
	{
		$owner = function_exists('posix_getpwuid') ? posix_getpwuid(@fileowner($name)) : array('name' => 'nn');
		$group = function_exists('posix_getgrgid') ? posix_getgrgid(@filegroup($name)) : array('name' => 'nn');
		$perms = "$owner[name]/$group[name] ".verbosePerms(@fileperms($name));
	}

	$checks = array();
	if (isset($args['is_readable']))
	{
		$checks[] = lang('readable by the webserver');
		$check_not = (!$args['is_readable']?lang('not'):'');
	}
	if (isset($args['is_writable']))
	{
		$checks[] = lang('writable by the webserver');
		$check_not = (!$args['is_writable']?lang('not'):'');
	}
	if (isset($args['is_world_readable']))
	{
		$checks[] = lang('world readable');
		$check_not = (!$args['is_world_readable']?lang('not'):'');
	}
	if (isset($args['is_world_writable']))
	{
		$checks[] = lang('world writable');
		$check_not = (!$args['is_world_writable']?lang('not'):'');
	}
	$checks = implode(', ',$checks);

	$icon = $passed_icon;
	if (isset($args['msg']) && ($msg = $args['msg']))
	{
		$msg .= ': '.$perms."<br />\n";
	}
	else
	{
		$msg = lang('Checking file-permissions of %1 for %2 %3: %4',$rel_name,$check_not,$checks,$perms)."<br />\n";
	}
	$extra_error_msg = '';
	if (isset($args['error']) && $args['error'])
	{
		$extra_error_msg = "<br />\n".$args['error'];
	}
	if (!file_exists($name))
	{
		echo '<div>'. $error_icon . '<span class="setup_error">' . $msg . lang('%1 does not exist !!!',$rel_name).$extra_error_msg."</span></div>\n";
		return False;
	}
	$warning = False;
	if (!$GLOBALS['run_by_webserver'] && (@$args['is_readable'] || @$args['is_writable']))
	{
		echo $warning_icon.' '.$msg. lang('Check can only be performed, if called via a webserver, as the user-id/-name of the webserver is not known.')."\n";
		unset($args['is_readable']);
		unset($args['is_writable']);
		$warning = True;
	}
	$Ok = True;
	if (isset($args['is_writable']) && is_writable($name) != $args['is_writable'])
	{
		echo '<div>'.$error_icon.' <span class="setup_error">'.$msg.' '.lang('%1 is %2%3 !!!',$rel_name,$args['is_writable']?lang('not').' ':'',lang('writable by the webserver')).$extra_error_msg."</span></div>\n";
		$Ok = False;
	}
	if (isset($args['is_readable']) && is_readable($name) != $args['is_readable'])
	{
		echo '<div>'.$error_icon.' <span class="setup_error">'.$msg.' '.lang('%1 is %2%3 !!!',$rel_name,$args['is_readable']?lang('not').' ':'',lang('readable by the webserver')).$extra_error_msg."</span></div>\n";
		$Ok = False;
	}
	if (!$is_windows && isset($args['is_world_readable']) && !(fileperms($name) & 04) == $args['is_world_readable'])
	{
		echo '<div>'.$error_icon.' <span class="setup_error">'.$msg.' '.lang('%1 is %2%3 !!!',$rel_name,$args['is_world_readable']?lang('not').' ':'',lang('world readable')).$extra_error_msg."</span></div>\n";
		$Ok = False;
	}
	if (!$is_windows && isset($args['is_world_writable']) && !(fileperms($name) & 02) == $args['is_world_writable'])
	{
		echo '<div>'.$error_icon.' <span class="setup_error">'.$msg.' '.lang('%1 is %2%3 !!!',$rel_name,$args['is_world_writable']?lang('not').' ':'',lang('world writable')).$extra_error_msg."</span></div>\n";
		$Ok = False;
	}
	if ($Ok && !$warning && $verbose)
	{
		echo $passed_icon.' '.$msg;
	}
	if ($Ok && @$args['recursiv'] && is_dir($name))
	{
		if ($verbose)
		{
			@set_time_limit(0);
			echo "<div class='setup_info'>" . lang('This might take a while, please wait ...')."</div>\n";
			flush();
		}
		@set_time_limit(0);
		$handle = @opendir($name);
		while($handle && ($file = readdir($handle)))
		{
			if ($file != '.' && $file != '..')
			{
				$Ok = $Ok && permission_check(($name!='.'?$name.'/':'').$file,$args,False);
			}
		}
		if ($handle) closedir($handle);
	}
	if ($verbose) echo "\n";

	return $Ok;
}

function mk_value($value)
{
	if (!preg_match('/^([0-9]+)([mk]+)$/i',$value,$matches)) return $value;

	return (strtolower($matches[2]) == 'm' ? 1024*1024 : 1024) * (int) $matches[1];
}

function tnef_check($name,$args)
{
	global $passed_icon, $error_icon, $warning_icon;

	$available = false;
	$tnef = array();

	if (file_exists('/usr/bin/tnef'))
	{
		$available = true;
		$tnef[] = '/usr/bin/tnef';
	}
	// Mac ports location
	elseif (file_exists('/opt/local/bin/tnef'))
	{
		$available = true;
		$tnef[] = '/opt/local/bin/tnef';
	}
	elseif (exec("which tnef", $tnef))
	{
		$available = true;
	}
	elseif (exec("which ytnef", $tnef))
	{
		$available = true;
	}

	echo "<div>".($available ? $passed_icon : $warning_icon).
		' <span'.($available?'':' class="setup_warning"').'>'.
		lang('Checking for tnef application').': '.
		($available ? $tnef[0] : lang('False'));

	if (!$available)
	{
		echo "<br />\n".lang('You dont have tnef or ytnef installed! It is needed to decode winmail.dat attachments in felamimail.')."\n";
	}
	echo "</span></div>\n";

	return $available;
}

function php_ini_check($name,$args)
{
	global $passed_icon, $error_icon, $warning_icon, $is_windows;

	$safe_mode = ini_get('safe_mode');

	$ini_value = ini_get($name);
	$check = isset($args['check']) ? $args['check'] : '=';
	$verbose_value = isset($args['verbose_value']) ? $args['verbose_value'] : $args['value'];
	$ini_value_verbose = '';
	if ($verbose_value == 'On' || $verbose_value == 'Off')
	{
		$ini_value_verbose = ' = '.($ini_value ? 'On' : 'Off');
	}
	switch ($check)
	{
		case 'not set':
			$check = lang('not set');
			$result = !($ini_value & $args['value']);
			break;
		case 'set':
			$check = lang('set');
			$result = !!($ini_value & $args['value']);
			break;
		case '>=':
			$result = !$ini_value ||	// value not used, eg. no memory limit
			(int) mk_value($ini_value) >= (int) mk_value($args['value']);
			break;
		case 'contain':
			$check = lang('contain');
			$sep = $is_windows ? '/[; ]+/' : '/[: ]+/';
			$result = in_array($args['value'],preg_split($sep,$ini_value));
			break;
		case '!=':
			$check = lang('set and not');
			$result = !empty($ini_value) && $ini_value != $args['value'];
			break;
		case '=':
		default:
			$result = $ini_value == $args['value'];
			break;
	}
	if ($name == 'date.timezone')
	{
		try {
			$tz = new DateTimeZone($ini_value);
			unset($tz);
		}
		catch(Exception $e) {
			$result = false;	// no valid timezone
		}
	}
	$msg = ' '.lang('Checking php.ini').": $name $check $verbose_value: <span class='setup_info'>ini_get('$name')='$ini_value'$ini_value_verbose</span>";

	if ($result)
	{
		echo "<div>".$passed_icon.$msg."</div>\n";
	}
	if (!$result)
	{
		if (isset($args['error']))
		{
			echo "<div>".$error_icon.' <span class="setup_error">'.$msg.'</span><div class="setup_info">'.$args['error']."</div></div>\n";
		}
		elseif (isset($args['warning']))
		{
			echo "<div>".$warning_icon.' <span class="setup_warning">'.$msg.'</span><div class="setup_info">'.$args['warning']."</div></div>\n";
		}
		elseif (!isset($args['safe_mode']))
		{
			echo "<div>".$warning_icon.' <span class="setup_warning">'.$msg.'</span><div class="setup_info">'.
				lang('%1 is needed by: %2.',$name,is_array($args['from']) ? implode(', ',$args['from']) : $args['from'])
				."</div></div>\n";
		}
		if (isset($args['safe_mode']) && $safe_mode || @$args['change'])
		{
			if (!isset($args['warning']) && !isset($args['error']))
			{
				echo '<div>'.$error_icon.' <span class="setup_error">'.$msg.'</span></div>';
			}
			echo "<div class='setup_error'>\n";
			echo '*** '.lang('Please make the following change in your php.ini').' ('.get_php_ini().'): '.(@$args['safe_mode']?$args['safe_mode']:$args['change'])."<br />\n";
			echo '*** '.lang('AND reload your webserver, so the above changes take effect !!!')."</div>\n";
		}
	}
	return $result;
}

function jpgraph_check($name,array $args)
{
	global $passed_icon, $error_icon, $warning_icon;

	$egw_path = defined(EGW_SERVER_ROOT) ? EGW_SERVER_ROOT : dirname(dirname(__FILE__));
	if (!($jpgraph_path = realpath($egw_path.'/..')))
	{
		$jpgraph_path = dirname($egw_path);	// realpath can fail because eg. open_basedir does NOT include ..
	}
	$jpgraph_path .= SEP.'jpgraph';

	$min_version = isset($args['min_version']) ? $args['min_version'] : '1.13';

	$available = false;
	if (($check = file_exists($jpgraph_path) && is_dir($jpgraph_path)) &&
		(file_exists($jpgraph_path.'/src/jpgraph.php') || file_exists($jpgraph_path.'/jpgraph.php')))
	{
		if (file_exists($jpgraph_path.'/VERSION') && preg_match('/Version: v([0-9.]+)/',file_get_contents($jpgraph_path.'/VERSION'),$matches))
		{
			$version = $matches[1];
			$available = version_compare($version,$min_version,'>=');
		}
		else
		{
			$version = 'unknown';
		}
	}
	echo "<div>".($available ? $passed_icon : (isset($version) ? $warning_icon : $error_icon)).
		' <span'.($available?'':' class="setup_warning"').'>'.
		lang('Checking for JPGraph in %1',$jpgraph_path).': '.
		(isset($version) ? lang('Version').' '.$version : lang('False'));

	if (!$available)
	{
		echo "<br />\n".lang('You dont have JPGraph version %1 or higher installed! It is needed from ProjectManager for Ganttcharts.',$min_version)."<br />\n";
		echo lang('Please download a recent version from %1 and install it as %2.',
			'<a href="http://jpgraph.net/download/" target="_blank">jpgraph.net/download/</a>',
			$jpgraph_path);
	}
	echo "</span></div>\n";

	return $available;
}

function get_php_ini()
{
	ob_start();
	phpinfo(INFO_GENERAL);
	$phpinfo = ob_get_contents();
	ob_end_clean();

	return preg_match('/\(php.ini\).*<\/td><td[^>]*>([^ <]+)/',$phpinfo,$found) ? $found[1] : False;
}

function gd_check()
{
	global $passed_icon, $warning_icon;

	$available = (function_exists('imagecopyresampled')  || function_exists('imagecopyresized'));

	echo "<div>".($available ? $passed_icon : $warning_icon).' <span'.($available?'':' class="setup_warning"').'>'.lang('Checking for GD support...').': '.($available ? lang('True') : lang('False'))."</span></div>\n";

	if (!$available)
	{
		echo lang('Your PHP installation does not have appropriate GD support. You need gd library version 1.8 or newer to see Gantt charts in projects.')."\n";
	}
	return $available;
}

if ($run_by_webserver)
{
	$tpl_root = $GLOBALS['egw_setup']->html->setup_tpl_dir('setup');
	$setup_tpl = CreateObject('phpgwapi.Template',$tpl_root);
	$setup_tpl->set_file(array(
		'T_head' => 'head.tpl',
		'T_footer' => 'footer.tpl',
	));
	$ConfigDomain = get_var('ConfigDomain',Array('POST','COOKIE'));
	if (@$_GET['intro']) {
		if(($ConfigLang = setup::get_lang()))
		{
			$GLOBALS['egw_setup']->set_cookie('ConfigLang',$ConfigLang,(int) (time()+(1200*9)),'/');
		}
		$GLOBALS['egw_setup']->html->show_header(lang('Welcome to the eGroupWare Installation'),False,'config');
		echo '<h1>'.lang('Welcome to the eGroupWare Installation')."</h1>\n";
		if(!$ConfigLang)
		{
			echo '<p><form action="check_install.php?intro=1" method="Post">Please Select your language '.setup_html::lang_select(True,'en')."</form></p>\n";
		}
		echo '<p>'.lang('The first step in installing eGroupWare is to ensure your environment has the necessary settings to correctly run the application.').'</p>';
		echo '<p>'.lang('We will now run a series of tests, which may take a few minutes.  Click the link below to proceed.').'</p>';
		echo '<h3><a href="check_install.php">'.lang('Run installation tests').'</a></h3>';
		echo '<p><a href="manageheader.php">'.lang('Skip the installation tests (not recommended)')."</a></p>\n";
		$setup_tpl->pparse('out','T_footer');
		exit;
	} else {
		$GLOBALS['egw_setup']->html->show_header(lang('Checking the eGroupWare Installation'),False,'config',$ConfigDomain ? $ConfigDomain . '(' . @$GLOBALS['egw_domain'][$ConfigDomain]['db_type'] . ')' : '');
		echo '<h1>'.lang('Checking the eGroupWare Installation')."</h1>\n";
		# echo "<pre style=\"text-align: left;\">\n";;
	}
}
else
{
	echo "Checking the eGroupWare Installation\n";
	echo "====================================\n\n";
}

$Ok = True;
foreach ($checks as $name => $args)
{
	$check_ok = $args['func']($name,$args);
	$Ok = $Ok && $check_ok;
}

if ($run_by_webserver)
{
	# echo "</pre>\n";;

	if ($GLOBALS['egw_info']['setup']['stage']['header'] != 10)
	{
		if (!$Ok)
		{
			echo '<h3>'.lang('Please fix the above errors (%1) and warnings(%2)',$error_icon,$warning_icon)."</h3>\n";
			echo '<h3><a href="check_install.php">'.lang('Click here to re-run the installation tests')."</a></h3>\n";
			echo '<h3>'.lang('or %1Continue to the Header Admin%2','<a href="manageheader.php">','</a>')."</h3>\n";
		}
		else
		{
			echo '<h3><a href="manageheader.php">'.lang('Continue to the Header Admin')."</a></h3>\n";
		}
	}
	else
	{
		echo '<h3>';
		if (!$Ok)
		{
			echo lang('Please fix the above errors (%1) and warnings(%2)',$error_icon,$warning_icon).'. ';
		}
		echo '<br /><a href="'.str_replace('check_install.php','',@$_SERVER['HTTP_REFERER']).'">'.lang('Return to Setup')."</a></h3>\n";
	}
	$setup_tpl->pparse('out','T_footer');
	//echo "</body>\n</html>\n";
}
