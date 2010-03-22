#!/usr/bin/php -qC
<?php
/**
 * EGroupware - checkout and build EGroupware tgz
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author RalfBecker@outdoor-training.de
 * @version $Id$
 */

if (isset($_SERVER['HTTP_HOST']))	// security precaution: forbit calling setup-cli as web-page
{
	die('<h1>checkout-build-tgz.php must NOT be called as web-page --> exiting !!!</h1>');
}

$verbose = 0;
$config = array(
	'packagename' => 'eGroupware',
	'version' => 'trunk',				// '1.6'
	'packaging' => date('Ymd'),			// '001'
	'egwdir' => 'egroupware',
	'svndir' => '/tmp/build_root/egw_buildroot-svn',
	'egw_buildroot' => '/tmp/build_root/egw_buildroot',
	'sourcedir' => '~/rpm/SOURCES',
	'svnbase' => 'http://svn.egroupware.org/egroupware',
	'svnbranch' => 'trunk',				// 'branches/1.6' or 'tags/1.6.001'
	'svnalias' => 'aliases/default',	// default alias
	'aliasdir' => 'egroupware',			// directory created by the alias
	'extra' => array('egw-pear','gallery','mydms','icalsrv'),
	'types' => array('tar.bz2','tar.gz','zip'),
	'svn' => '/usr/bin/svn',
	'clamscan' => '/usr/bin/clamscan',
	'freshclam' => '/usr/bin/freshclam',
	'gpg' => '/usr/bin/gpg',
	'packager' => 'packager@egroupware.org',
	'obs' => false,
	'changelog' => false,	// eg. '  * 1. Zeile\n  * 2. Zeile' for debian.changes
	'changelog_packager' => 'Ralf Becker <rb@stylite.de>',
	'skip' => array(),
	'run' => array('checkout','copy','virusscan','create','sign')
);

// process config from command line
$argv = $_SERVER['argv'];
$prog = array_shift($argv);

while(($arg = array_shift($argv)))
{
	if ($arg == '-v' || $arg == '--verbose')
	{
		++$verbose;
	}
	elseif($arg == '-h' || $arg == '--help')
	{
		usage();
	}
	elseif(substr($arg,0,2) == '--' && isset($config[$name=substr($arg,2)]))
	{
		$value = array_shift($argv);
		switch($name)
		{
			case 'extra':	// stored as array and allow to add/delete items with +/- prefix
			case 'types':
			case 'skip':
			case 'run':
				if ($value[0] == '+')
				{
					$config[$name] = array_unique(array_merge($config[$name],preg_split('/[ ,]+/',$value)));
				}
				elseif ($value[0] == '-')
				{
					$config[$name] = array_diff($config[$name],preg_split('/[ ,]+/',$value));
				}
				else
				{
					$config[$name] = array_unique(preg_split('/[ ,]+/',$value));
				}
				break;

			case 'obs':
				if (!is_dir($value))
				{
					usage("Path '$value' not found!");
				}
				if (!in_array('obs',$config['run'])) $config['run'][] = 'obs';
				// fall through
			default:
				$config[$name] = $value;
				break;
		}
	}
	else
	{
		usage("Unknown argument '$arg'!");
	}
}
if ($verbose > 1)
{
	echo "Using following config:\n";
	print_r($config);
}
$svn = $config['svn'];

foreach(array_diff($config['run'],$config['skip']) as $func)
{
	$func = 'do_'.$func;
	$func();
}

/**
 * Copy archive files to obs checkout and commit them
 *
 */
function do_obs()
{
	global $config,$verbose;

	if (!is_dir($config['obs']))
	{
		usage("Path '$config[obs]' not found!");
	}
	if ($verbose) echo "Updating OBS checkout\n";
	run_cmd('osc up '.$config['obs']);

	$n = 0;
	foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($config['obs'])) as $path)
	{
		if (basename(dirname($path)) == '.osc') continue;
		if (!preg_match('/\/'.preg_quote($config['packagename']).'[a-z-]*-'.preg_quote($config['version']).'/',$path)) continue;

		if (preg_match('/\/('.preg_quote($config['packagename']).'[a-z-]*)-'.preg_quote($config['version']).'\.[0-9]+(\.tar\.(gz|bz2))$/',$path,$matches) &&
			file_exists($new_name=$config['sourcedir'].'/'.$matches[1].'-'.$config['version'].'.'.$config['packaging'].$matches[2]))
		{
			if (basename($path) != basename($new_name))
			{
				unlink($path);
				if ($verbose) echo "rm $path\n";
			}
			copy($new_name,dirname($path).'/'.basename($new_name));
			if ($verbose) echo "cp $new_name ".dirname($path)."/\n";
			++$n;
		}
		// updating dsc, spec and changelog files
		if (substr($path,-4) == '.dsc' || substr($path,-5) == '.spec' ||
			!empty($config['changelog']) && basename($path) == 'debian.changes')
		{
			$content = $content_was = file_get_contents($path);

			if (substr($path,-4) == '.dsc' || substr($path,-5) == '.spec')
			{
				$content = preg_replace('/^Version: '.preg_quote($config['version']).'\.[0-9]+/m','Version: '.$config['version'].'.'.$config['packaging'],$content);
			}
			if (substr($path,-4) == '.dsc')
			{
				$content = preg_replace('/^(Debtransform-Tar: '.preg_quote($config['packagename']).'[a-z-]*)-'.
					preg_quote($config['version']).'\.[0-9]+(\.tar\.(gz|bz2))$/m',
					'\\1-'.$config['version'].'.'.$config['packaging'].'\\2',$content);
			}
			if (basename($path) == 'debian.changes' && strpos($content,$config['version'].'.'.$config['packaging']) === false)
			{
				list($new_header) = explode("\n",$content);
				$new_header = preg_replace('/\('.preg_quote($config['version']).'.[0-9]+(.*)\)/','('.$config['version'].'.'.$config['packaging'].'\\1)',$new_header);
				if (substr($config['changelog'],0,2) != '  ') $config['changelog'] = '  '.implode("\n  ",explode("\n",$config['changelog']));
				$content = $new_header."\n\n".$config['changelog'].
					"\n\n -- ".$config['changelog_packager'].'  '.date('r')."\n\n".$content;
			}
			if (!empty($config['changelog']) && substr($path,-5) == '.spec' &&
				($pos_changelog = strpos($content,'%changelog')) !== false)
			{
				$pos_changelog += strlen("%changelog\n");
				$content = substr($content,0,$pos_changelog).' *'.date('D M d Y').' '.$config['changelog_packager']."\n".
					$config['changelog']."\n".substr($content,$pos_changelog);
			}
			if ($content != $content_was)
			{
				file_put_contents($path,$content);
				if ($verbose) echo "Updated $path\n";
				++$n;
			}
		}
	}
	if ($n)
	{
		echo "$n files updated in OBS checkout ($config[obs]), commiting them now...\n";
		//run_cmd('osc status '.$config['obs']);
		run_cmd('osc addremove '.$config['obs'].'/*');
		run_cmd('osc commit -m '.escapeshellarg('Version: '.$config['version'].'.'.$config['packaging'].":\n".$config['changelog']).' '.$config['obs']);
	}
}

/**
 * Sign sha1sum file
 */
function do_sign()
{
	global $config,$verbose;

	if (substr($config['sourcedir'],0,2) == '~/')	// sha1_file cant deal with '~/rpm'
	{
		$config['sourcedir'] = getenv('HOME').substr($config['sourcedir'],1);
	}
	$sumsfile = $config['sourcedir'].'/sha1sum-'.$config['packagename'].'-'.$config['version'].'.'.$config['packaging'].'.txt';

	if (!file_exists($sumsfile))
	{
		echo "sha1sum file '$sumsfile' not found!\n";
		return;
	}
	// signing it
	if (empty($config['gpg']) || !file_exists($config['gpg']))
	{
		if (!empty($config['gpg'])) echo "{$config['gpg']} not found --> skipping signing sha1sum file!\n";
		return;
	}
	echo "Signing sha1sum file:\n";
	if (file_exists($sumsfile.'.asc')) unlink($sumsfile.'.asc');
	$cmd = $config['gpg'].' --local-user '.$config['packager'].' --clearsign '.$sumsfile;
	run_cmd($cmd);
	unlink($sumsfile);	// delete the unsigned file
}

/**
 * Create archives
 */
function do_create()
{
	global $config,$verbose;

	if (!file_exists($config['sourcedir'])) mkdir($config['sourcedir'],0755,true);
	if (substr($config['sourcedir'],0,2) == '~/')	// sha1_file cant deal with '~/rpm'
	{
		$config['sourcedir'] = getenv('HOME').substr($config['sourcedir'],1);
	}
	$sumsfile = $config['sourcedir'].'/sha1sum-'.$config['packagename'].'-'.$config['version'].'.'.$config['packaging'].'.txt';
	$sums = '';

	chdir($config['egw_buildroot']);

	if($config['extra'])
	{
		foreach($config['extra'] as $key => $module)
		{
			if (strpos($module,'/') !== false) $config['extra'][$key] = basename($module);
		}
		$exclude_extra = ' --exclude=egroupware/'.implode(' --exclude=egroupware/',$config['extra']);
	}
	foreach($config['types'] as $type)
	{
		echo "Creating $type archives\n";
		$tar_type = $type == 'tar.bz2' ? 'j' : 'z';

		$file = $config['sourcedir'].'/'.$config['packagename'].'-'.$config['version'].'.'.$config['packaging'].'.'.$type;
		switch($type)
		{
			case 'tar.bz2':
			case 'tar.gz':
				$cmd = '/bin/tar --owner=root --group=root -c'.$tar_type.'f '.$file.' '.$exclude_extra.' egroupware';
				break;
			case 'zip':
				$cmd = '/bin/mv egroupware/'.implode(' egroupware/',$config['extra']).' . ;';
				$cmd .= '/usr/bin/zip -q -r -9 '.$file.' egroupware ;';
				$cmd .= '/bin/mv '.implode(' ',$config['extra']).' egroupware';
				break;
		}
		run_cmd($cmd);
		$sums .= sha1_file($file)."\t".basename($file)."\n";

		foreach($config['extra'] as $module)
		{
			$file = $config['sourcedir'].'/'.$config['packagename'].'-'.$module.'-'.$config['version'].'.'.$config['packaging'].'.'.$type;
			switch($type)
			{
				case 'tar.bz2':
				case 'tar.gz':
					$cmd = '/bin/tar --owner=root --group=root -c'.$tar_type.'f '.$file.' egroupware/'.$module;
					break;
				case 'zip':
					$cmd = '/usr/bin/zip -q -r -9 '.$file.' egroupware/'.$module;
					break;
			}
			run_cmd($cmd);
			$sums .= sha1_file($file)."\t".basename($file)."\n";
		}
	}
	// writing sha1sum file
	file_put_contents($sumsfile,$sums);
}

/**
 * Scan checkout for viruses, if clamscan is installed (not fatal if not!)
 */
function do_virusscan()
{
	global $config,$verbose;

	if (!file_exists($config['clamscan']) || !is_executable($config['clamscan']))
	{
		echo "Virusscanner '$config[clamscan]' not found --> skipping virus scan!\n";
		return;
	}
	// try updating virus database
	if (file_exists($config['freshclam']))
	{
		echo "Updating virus signatures\n";
		$cmd = '/usr/bin/sudo '.$config['freshclam'];
		if (!$verbose && function_exists('posix_getuid') && posix_getuid()) echo $cmd."\n";
		run_cmd($cmd,$output,1);	// 1 = ignore already up to date database
	}
	echo "Starting virusscan\n";
	$cmd = $config['clamscan'].' --quiet -r '.$config['egw_buildroot'];
	run_cmd($cmd);
	echo "Virusscan successful (no viruses found).\n";
}

/**
 * Copy non .svn parts to egw_buildroot and fix permissions and ownership
 */
function do_copy()
{
	global $config;

	// copy everything, but .svn dirs from svndir to egw_buildroot
	echo "Copying non-svn dirs to buildroot\n";
	$cmd = '/usr/bin/rsync -r --delete --exclude .svn '.$config['svndir'].'/'.$config['aliasdir'].' '.$config['egw_buildroot'];
	run_cmd($cmd);

	// fix permissions
	echo "Fixing permissions\n";
	chdir($config['egw_buildroot'].'/'.$config['aliasdir']);
	$cmd = '/bin/chmod -R a-x,u=rwX,g=rX,o=rX .';
	run_cmd($cmd);
	$cmd = '/bin/chmod +x */*cli.php phpgwapi/cron/*.php svn-helper.php doc/rpm-build/*.php';
	run_cmd($cmd);
}

/**
 * Checkout or update EGroupware
 *
 * Ensures an existing checkout is from the correct branch! Otherwise it get's deleted
 */
function do_checkout()
{
	global $config,$svn,$verbose;

	echo "Starting svn checkout/update\n";
	if (!file_exists($config['svndir']))
	{
		mkdir($config['svndir'],0755,true);
	}
	elseif (!is_dir($config['svndir']) || !is_writable($config['svndir']))
	{
		throw new Exception("svn checkout directory '{$config['svndir']} exists and is NO directory or NOT writable!");
	}
	chdir($config['svndir']);
	$svnbranch = $config['svnbase'].'/'.$config['svnbranch'];
	if (file_exists($config['aliasdir']))
	{
		// check if correct branch
		$cmd = 'LANG=C '.$svn.' info';
		exec($cmd,$output,$ret);
		foreach($output as $line)
		{
			if ($ret || substr($line,0,5) == 'URL: ')
			{
				$url = substr($line,5);
				if ($ret || substr($url,0,strlen($svnbranch)) != $svnbranch)	// wrong branch (or no svn dir)
				{
					echo "Checkout is of wrong branch --> deleting it\n";
					system('/bin/rm -rf .svn '.$config['aliasdir']);	// --> remove the whole checkout
					clearstatcache();
				}
				break;
			}
		}
	}
	$url = $svnbranch.'/'.$config['svnalias'];
	$cmd = $svn.' co '.$url.' .';
	run_cmd($cmd);

	chdir($config['aliasdir']);
	foreach($config['extra'] as $module)
	{
		if (strpos($module,'$') !== false)	// allow to use config vars like $svnbranch in module
		{
			$translate = array();
			foreach($config as $name => $value) $translate['$'.$name] = $value;
			$module = strtr($module,$translate);
		}
		$url = strpos($module,'://') === false ? $svnbranch.'/' : '';
		$url .= $module;
		$cmd = $svn.' co '.$url;
		run_cmd($cmd);
	}
}

/**
 * Runs given shell command, exists with error-code after echoing the output of the failed command (if not already running verbose)
 *
 * @param string $cmd
 * @param array &$output=null $output of command
 * @param int|array $no_bailout=null exit code(s) to NOT bail out
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
	if ($ret && !in_array($ret,(array)$no_bailout))
	{
		if (!$verbose) echo implode("\n",$output)."\n";
		throw new Exception("Error during '$cmd' --> aborting",$ret);
	}
	return $ret;
}

/**
 * Give usage information and an optional error-message, before stoping program execution with exit-code 90 or 0
 *
 * @param string $error=null optional error-message
 */
function usage($error=null)
{
	global $prog,$config;

	echo "Usage: $prog [-h|--help] [-v|--verbose] [options, ...]\n\n";
	echo "options and their defaults:\n";
	foreach($config as $name => $default)
	{
		if (is_array($default)) $default = implode(' ',$default);
		echo '--'.str_pad($name,20).$default."\n";
	}
	if ($error)
	{
		echo "$error\n\n";
		exit(90);
	}
	exit(0);
}

