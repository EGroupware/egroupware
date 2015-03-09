#!/usr/bin/php -qC
<?php
/**
 * EGroupware - checkout, build and release archives, build Debian and rpm packages
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author RalfBecker@outdoor-training.de
 * @copyright (c) 2009-14 by Ralf Becker <rb@stylite.de>
 * @version $Id$
 */

if (php_sapi_name() !== 'cli')	// security precaution: forbit calling setup-cli as web-page
{
	die('<h1>checkout-build-archives.php must NOT be called as web-page --> exiting !!!</h1>');
}
date_default_timezone_set('Europe/Berlin');	// to get ride of 5.3 warnings

$verbose = 0;
$config = array(
	'packagename' => 'egroupware-epl',
	'version' => '14.2',                            // '1.6'
	'packaging' => date('Ymd'),                     // '001'
	'egwdir' => 'egroupware',
	'svndir' => '/tmp/build_root/epl_14.2_buildroot-svn',
	'egw_buildroot' => '/tmp/build_root/epl_14.2_buildroot',
	'sourcedir' => '/srv/obs/download/stylite-epl/egroupware-epl-14.2',
	'svnbase' => 'svn+ssh://svn@dev.egroupware.org/egroupware',
	'stylitebase' => 'svn+ssh://stylite@svn.stylite.de/stylite',
	'svnbranch' => 'branches/14.2',	//'trunk', // 'branches/1.6' or 'tags/1.6.001'
	'svnalias' => 'aliases/default-ssh',    // default alias
	'aliasdir' => 'egroupware',             // directory created by the alias
	'extra' => array('$stylitebase/$svnbranch/stylite', '$stylitebase/$svnbranch/esyncpro'),//, '$stylitebase/$svnbranch/groups'), //,'svn+ssh://stylite@svn.stylite.de/stylite/trunk/eventmgr'),
	'types' => array('tar.bz2','tar.gz','zip'),
	// diverse binaries we need
	'svn' => trim(`which svn`),
	'tar' => trim(`which tar`),
	'mv' => trim(`which mv`),
	'zip' => trim(`which zip`),
	'clamscan' => trim(`which clamscan`),
	'freshclam' => trim(`which freshclam`),
	'gpg' => trim(`which gpg`),
	'editor' => trim(`which vi`),
	'rsync' => trim(`which rsync`).' --progress -e ssh --exclude "*-stylite-*" --exclude "*-esyncpro-*"',
	'composer' => ($composer=trim(`which composer.phar`)) ? $composer.' install' : '',
	'packager' => 'build@stylite.de',
	'obs' => './obs',
	'obs_package_alias' => '',	// name used in obs package, if different from packagename
	'changelog' => false,   // eg. '* 1. Zeile\n* 2. Zeile' for debian.changes
	'changelog_packager' => 'Ralf Becker <rb@stylite.de>',
	'editsvnchangelog' => '* ',
	'svntag' => 'tags/$version.$packaging',
	'release' => 'ralfbecker,egroupware@frs.sourceforge.net:/home/frs/project/e/eg/egroupware/eGroupware-$version/eGroupware-$version.$packaging/',
	'copychangelog' => '$sourcedir/README', //'ralfbecker,egroupware@frs.sourceforge.net:/home/frs/project/e/eg/egroupware/README',
	'skip' => array(),
	'run' => array('editsvnchangelog','svntag','checkout','copy','virusscan','create','sign','obs','copychangelog'),
	'patchCmd' => '# run cmd after copy eg. "cd $egw_buildroot; patch -p1 /path/to/patch"',
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
					$config[$name] = array_unique(array_merge($config[$name],preg_split('/[ ,]+/',substr($value,1))));
				}
				elseif ($value[0] == '-')
				{
					$config[$name] = array_diff($config[$name],preg_split('/[ ,]+/',substr($value,1)));
				}
				else
				{
					$config[$name] = array_unique(preg_split('/[ ,]+/',$value));
				}
				break;

			case 'svntag':
			case 'release':
			case 'copychangelog':
				$config[$name] = $value;
				if ($value) array_unshift($config['run'],$name);
				break;

			case 'editsvnchangelog':
				$config[$name] = $value ? $value : true;
				if (!in_array('editsvnchangelog',$config['run']))
				{
					array_unshift($config['run'],'editsvnchangelog');
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
	chdir(dirname(__FILE__));	// make relative filenames work, if other command changes dir
	call_user_func('do_'.$func);
}

/**
 * Release sources by rsync'ing them to a distribution / download directory
 */
function do_release()
{
	global $config,$verbose;

	$target = config_translate('release');	// allow to use config vars like $svnbranch in module
	$cmd = $config['rsync'].' '.$config['sourcedir'].'/*'.$config['version'].'.'.$config['packaging'].'* '.$target;
	if ($verbose) echo $cmd."\n";
	passthru($cmd);
}

/**
 * Fetch a config value allowing to use config vars like $svnbranch in it
 *
 * @param string $name
 * @param string $value =null value to use, default $config[$name]
 */
function config_translate($name, $value=null)
{
	global $config;

	if (!isset($value)) $value = $config[$name];
	if (is_string($value) && strpos($value, '$') !== false)
	{
		$translate = array();
		foreach($config as $n => $val)
		{
			if (is_string($val)) $translate['$'.$n] = $val;
		}
		$value = strtr($value, $translate);
	}
	return $value;
}

/**
 * Copy changelog by rsync'ing it to a distribution / download directory
 */
function do_copychangelog()
{
	global $config;

	$changelog = __DIR__.'/debian.changes';
	$cmd = $config['rsync'].' '.$changelog.' '.config_translate('copychangelog');
	passthru($cmd);
}

/**
 * Query changelog from svn and let user edit it
 */
function do_editsvnchangelog()
{
	global $config,$svn;

	echo "Querying changelog from SVN\n";
	if (!isset($config['modules']))
	{
		get_modules_per_repro();
	}
	// query changelog per repo
	$changelog = '';
	foreach($config['modules'] as /*$repo =>*/ $modules)
	{
		$branch_url = '';
		$revision = null;
		foreach($modules as $path => $url)
		{
			$module = basename($path);
			$burl = substr($url,0,-strlen($module)-1);
			if (empty($branch_url) || $burl != $branch_url)
			{
				if (empty($branch_url)) $url = $branch_url = $burl;
				//if (count($config['modules']) > 1) $changelog .= $url."\n";
				$changelog .= get_changelog_from_svn($url,$config['editsvnchangelog'],$revision);
			}
		}
	}
	$logfile = tempnam('/tmp','checkout-build-archives');
	file_put_contents($logfile,$changelog);
	$cmd = $config['editor'].' '.escapeshellarg($logfile);
	passthru($cmd);
	$config['changelog'] = file_get_contents($logfile);
	// remove trailing newlines
	while (substr($config['changelog'],-1) == "\n")
	{
		$config['changelog'] = substr($config['changelog'],0,-1);
	}
	// allow user to abort, by deleting the changelog
	if (strlen($config['changelog']) <= 2)
	{
		die("\nChangelog must not be empty --> aborting\n\n");
	}
	// commit changelog
	$changelog = __DIR__.'/debian.changes';
	if (file_exists($changelog))
	{
		file_put_contents($changelog, update_changelog(file_get_contents($changelog)));
		$cmd = $svn." commit -m 'Changelog for $config[version].$config[packaging]' ".$changelog;
		run_cmd($cmd);
	}
	// update obs changelogs (so all changlogs are updated in case of a later error and changelog step can be skiped)
	do_obs(true);	// true: only update debian.changes in obs checkouts
}

/**
 * Read changelog for given branch from (last) tag or given revision from svn
 *
 * @param string $branch_url ='svn+ssh://svn@svn.stylite.de/egroupware/branches/Stylite-EPL-10.1'
 * @param string $log_pattern =null	a preg regular expression or start of line a log message must match, to be returned
 * 	if regular perl regular expression given only first expression in brackets \\1 is used,
 * 	for a start of line match, only the first line is used, otherwise whole message is used
 * @param string $revision =null from which to HEAD the log should be retrieved, default search revision of latest tag in ^/tags
 * @param string $prefix ='* ' prefix, which if not presend should be added to all log messages
 */
function get_changelog_from_svn($branch_url, $log_pattern=null, &$revision=null, $prefix='* ')
{
	//echo __FUNCTION__."('$branch_url','$log_pattern','$revision','$prefix')\n";
	global $config,$verbose,$svn;

	if (is_null($revision))
	{
		list($tags_url,$branch) = preg_split('#/(branches/|trunk)#',$branch_url);
		if (empty($branch)) $branch = $config['version'];
		$tags_url .= '/tags';
		$pattern=str_replace('Stylite-EPL-10\.1',preg_quote($branch),'/tags\/(Stylite-EPL-10\.1\.[0-9.]+)/');
		$matches = null;
		$revision = get_last_svn_tag($tags_url,$pattern,$matches);
		$tag = $matches[1];
	}
	elseif(!is_numeric($revision))
	{
		$revision = get_last_svn_tag($tags_url,$tag=$revision);
	}
	$cmd = $svn.' log --xml -r '.escapeshellarg($revision.':HEAD').' '.escapeshellarg($branch_url);
	if (($v = $verbose))
	{
		echo "Querying SVN for log from r$revision".($tag ? " ($tag)" : '').":\n$cmd\n";
		$verbose = false;	// otherwise no $output!
	}
	$output = array();
	run_cmd($cmd,$output);
	$verbose = $v;
	array_shift($output);	// remove the command

	$xml = simplexml_load_string($output=implode("\n",$output));
	$message = '';
	$pattern_len = strlen($log_pattern);
	$prefix_len = strlen($prefix);
	foreach($xml as $log)
	{
		$msg = $log->msg;
		if ($log_pattern[0] == '/' && preg_match($log_pattern,$msg,$matches))
		{
			$msg = $matches[1];
		}
		elseif($log_pattern && $log_pattern[0] != '/' && substr($msg,0,$pattern_len) == $log_pattern)
		{
			list($msg) = explode("\n",$msg);
		}
		elseif($log_pattern)
		{
			continue;	// no match --> ignore
		}
		if ($prefix_len && substr($msg,0,$prefix_len) != $prefix) $msg = $prefix.$msg;
		$message .= $msg."\n";
	}
	if ($verbose) echo $message;

	return $message;
}

/**
 * Get revision of last svn tag matching a given pattern in the log message
 *
 * @param string $tags_url
 * @param string $pattern which has to be contained in the log message (NOT the tag itself)
 * 	or (perl) regular expression against which log message is matched
 * @param array &$matches=null on return matches of preg_match
 * @return int revision of last svn tag matching pattern
 */
function get_last_svn_tag($tags_url,$pattern,&$matches=null)
{
	global $verbose,$svn;

	$cmd = $svn.' log --xml --limit 40 '.escapeshellarg($tags_url);
	if (($v = $verbose))
	{
		echo "Querying SVN for last tags\n$cmd\n";
		$verbose = false;	// otherwise no $output!
	}
	$output = array();
	run_cmd($cmd,$output);
	$verbose = $v;
	array_shift($output);	// remove the command

	$xml = simplexml_load_string($output=implode("\n",$output));
	foreach($xml as $log)
	{
		//print_r($log);
		if ($pattern[0] != '/' && strpos($log->msg,$pattern) !== false ||
			$pattern[0] == '/' && preg_match($pattern,$log->msg,$matches))
		{
			if ($verbose) echo "Revision {$log['revision']} matches".($matches?': '.$matches[1] : '')."\n";
			return (int)$log['revision'];
		}
	}
	return null;
}

/**
 * Copy archive files to obs checkout and commit them
 *
 * @param boolean $only_update_changelog =false true update debian.changes, but nothing else, nor commit it
 */
function do_obs($only_update_changelog=false)
{
	global $config,$verbose;

	if (!is_dir($config['obs']))
	{
		usage("Path '$config[obs]' not found!");
	}
	if ($verbose) echo $only_update_changelog ? "Updating OBS changelogs\n" : "Updating OBS checkout\n";
	run_cmd('osc up '.$config['obs']);

	$n = 0;
	foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($config['obs'])) as $path)
	{
		if (basename(dirname($path)) == '.osc' ||
			!preg_match('/\/('.preg_quote($config['packagename']).
				($config['obs_package_alias'] ? '|'.preg_quote($config['obs_package_alias']) : '').
				')[a-z-]*-('.preg_quote($config['version']).'|trunk)/',$path))
		{
			continue;
		}
		$matches = null;
		if (preg_match('/\/('.preg_quote($config['packagename']).'[a-z-]*)-'.preg_quote($config['version']).'\.[0-9.]+[0-9](\.tar\.(gz|bz2))$/',$path,$matches) &&
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
		// if we have no changelog (eg. because commands run separate), try parsing it from changelog file
		if (empty($config['changelog']))
		{
			$config['changelog'] = parse_current_changelog();
		}
		// updating dsc, spec and changelog files
		if (!$only_update_changelog && (substr($path,-4) == '.dsc' || substr($path,-5) == '.spec') ||
			!empty($config['changelog']) && basename($path) == 'debian.changes')
		{
			$content = $content_was = file_get_contents($path);

			if (substr($path,-4) == '.dsc' || substr($path,-5) == '.spec')
			{
				$content = preg_replace('/^Version: '.preg_quote($config['version']).'\.[0-9.]+[0-9]/m','Version: '.$config['version'].'.'.$config['packaging'],$content);
			}
			if (substr($path,-4) == '.dsc')
			{
				$content = preg_replace('/^(Debtransform-Tar: '.preg_quote($config['packagename']).'[a-z-]*)-'.
					preg_quote($config['version']).'\.[0-9.]+[0-9](\.tar\.(gz|bz2))$/m',
					'\\1-'.$config['version'].'.'.$config['packaging'].'\\2',$content);
			}
			if (basename($path) == 'debian.changes' && strpos($content,$config['version'].'.'.$config['packaging']) === false)
			{
				$content = update_changelog($content);
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
	if ($n && !$only_update_changelog)
	{
		echo "$n files updated in OBS checkout ($config[obs]), commiting them now...\n";
		//run_cmd('osc status '.$config['obs']);
		run_cmd('osc addremove '.$config['obs'].'/*');
		run_cmd('osc commit -m '.escapeshellarg('Version: '.$config['version'].'.'.$config['packaging'].":\n".$config['changelog']).' '.$config['obs']);
	}
}

/**
 * Parse current changelog from debian.changes file
 *
 * @return string changelog entries without header and footer lines
 */
function parse_current_changelog()
{
	global $config;

	$changelog = file_get_contents(__DIR__.'/debian.changes');
	$lines = explode("\n", $changelog, 100);
	foreach($lines as $n => $line)
	{
		if (preg_match($preg='/^'.preg_quote($config['packagename']).' \('.preg_quote($config['version'].'.'.$config['packaging']).'/', $line))
		{
			$n += empty($lines[$n+1]) ? 2 : 1;	// overead empty line behind header
			$logentry = '';
			while($lines[$n])	// entry is terminated by empty line
			{
				$logentry .= (substr($lines[$n], 0, 2) == '  ' ? substr($lines[$n], 2) : $lines[$n])."\n";
				++$n;
			}
			return substr($logentry, 0, -1);	// remove training "\n"
		}
	}
	return null;	// paragraph for current version NOT found
}

/**
 * Update content of debian changelog file with new content from $config[changelog]
 *
 * @param string $content existing changelog content
 * @return string updated changelog content
 */
function update_changelog($content)
{
	global $config;

	list($header) = explode("\n", $content);
	$new_header = preg_replace('/\('.preg_quote($config['version']).'\.[0-9.]+[0-9](.*)\)/','('.$config['version'].'.'.$config['packaging'].'\\1)', $header);
	if (substr($config['changelog'],0,2) != '  ') $config['changelog'] = '  '.implode("\n  ",explode("\n",$config['changelog']));
	$content = $new_header."\n\n".$config['changelog'].
		"\n\n -- ".$config['changelog_packager'].'  '.date('r')."\n\n".$content;

	return $content;
}

/**
 * Sign sha1sum file
 */
function do_sign()
{
	global $config;

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
	global $config;

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
				$cmd = $config['tar'].' --owner=root --group=root -c'.$tar_type.'f '.$file.' '.$exclude_extra.' egroupware';
				break;
			case 'zip':
				$cmd = $config['mv'].' egroupware/'.implode(' egroupware/',$config['extra']).' . ;';
				$cmd .= $config['zip'].' -q -r -9 '.$file.' egroupware ;';
				$cmd .= $config['mv'].' '.implode(' ',$config['extra']).' egroupware';
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
					$cmd = $config['tar'].' --owner=root --group=root -c'.$tar_type.'f '.$file.' egroupware/'.$module;
					break;
				case 'zip':
					$cmd = $config['zip'].' -q -r -9 '.$file.' egroupware/'.$module;
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
		$output = null;
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

	if (($cmd = config_translate('patchCmd')) && $cmd[0] != '#')
	{
		echo "Running $cmd\n";
		run_cmd($cmd);
	}
	// fix permissions
	echo "Fixing permissions\n";
	chdir($config['egw_buildroot'].'/'.$config['aliasdir']);
	run_cmd('/bin/chmod -R a-x,u=rwX,g=rX,o=rX .');
	run_cmd('/bin/chmod +x */*cli.php phpgwapi/cron/*.php svn-helper.php doc/rpm-build/*.php');
}

/**
 * Checkout or update EGroupware
 *
 * Ensures an existing checkout is from the correct branch! Otherwise it get's deleted
 */
function do_checkout()
{
	global $config,$svn;

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

	// do we use a just created tag --> list of taged modules
	if ($config['svntag'])
	{
		if (!isset($config['modules']))
		{
			get_modules_per_repro();
		}
		$config['svntag'] = config_translate('svntag');	// in case svntag command did not run, translate tag name

		if (file_exists($config['aliasdir']))
		{
			system('/bin/rm -rf .svn '.$config['aliasdir']);	// --> remove the whole checkout, as we dont implement switching tags
			clearstatcache();
		}
		foreach($config['modules'] as $repo => $modules)
		{
			$cmd = $svn.' co ';
			foreach($modules as $path => $url)
			{
				if ($path == $config['aliasdir'])
				{
					$cmd = $svn.' co '.$repo.'/'.$config['svntag'].'/'.$path;
					run_cmd($cmd);
					chdir($path);
					$cmd = $svn.' co ';
					continue;
				}
				if(file_exists($config['aliasdir']))
				{
					die("'egroupware' applications must be first one in externals!\n");
				}
				$cmd .= ' '.$repo.'/'.$config['svntag'].'/'.basename($path);
			}
			run_cmd($cmd);
		}
	}
	// regular branch update, without tag
	else
	{
		$svnbranch = $config['svnbase'].'/'.$config['svnbranch'];
		if (file_exists($config['aliasdir']))
		{
			// check if correct branch
			$cmd = 'LANG=C '.$svn.' info';
			$output = $ret = null;
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
			$module = config_translate(null, $module);	// allow to use config vars like $svnbranch in module
			$url = strpos($module,'://') === false ? $svnbranch.'/' : '';
			$url .= $module;
			$cmd = $svn.' co '.$url;
			run_cmd($cmd);
		}
	}
	// do composer install to fetch dependencies
	if ($config['composer'])
	{
		run_cmd($config['composer']);
	}
}

/**
 * Get module name per svn repro
 *
 * @return array with $repro_url => array(module1, ..., moduleN) pairs
 */
function get_modules_per_repro()
{
	global $config,$svn,$verbose;

	// process alias/externals
	$svnbranch = $config['svnbase'].'/'.$config['svnbranch'];
	$url = $svnbranch.'/'.$config['svnalias'];
	$cmd = $svn.' propget svn:externals --strict '.$url;
	if ($verbose) echo $cmd."\n";
	$output = $ret = null;
	exec($cmd,$output,$ret);
	$config['modules'] = array();
	foreach($output as $line)
	{
		$line = trim($line);
		if (empty($line) || $line[0] == '#') continue;
		list($path,$url) = preg_split('/[ \t\r\n]+/',$line);
		$matches = null;
		if (!preg_match('/([a-z+]+:\/\/[a-z@.]+\/[a-z]+)\/(branches|tags|trunk)/',$url,$matches)) die("Invalid SVN URL: $url\n");
		$repo = $matches[1];
		if ($repo == 'http://svn.egroupware.org/egroupware') $repo = 'svn+ssh://svn@dev.egroupware.org/egroupware';
		$config['modules'][$repo][$path] = $url;
	}
	// process extra modules
	foreach($config['extra'] as $module)
	{
		$module = config_translate(null, $module);	// allow to use config vars like $svnbranch in module
		$url = strpos($module,'://') === false ? $svnbranch.'/' : '';
		$url .= $module;
		if (strpos($module,'://') !== false) $module = basename($module);
		if (!preg_match('/([a-z+]+:\/\/[a-z@.]+\/[a-z]+)\/(branches|tags|trunk)/',$url,$matches)) die("Invalid SVN URL: $url\n");
		$repo = $matches[1];
		if ($repo == 'http://svn.egroupware.org/egroupware') $repo = 'svn+ssh://svn@dev.egroupware.org/egroupware';
		$config['modules'][$repo][$config['aliasdir'].'/'.$module] = $url;
	}
	return $config['modules'];
}

/**
 * Create svn tag or branch
 */
function do_svntag()
{
	global $config,$svn;

	if (empty($config['svntag'])) return;	// otherwise we copy everything in svn root!

	$config['svntag'] = config_translate('svntag');	// allow to use config vars like $version in tag

	echo "Creating SVN tag $config[svntag]\n";
	if (!isset($config['modules']))
	{
		get_modules_per_repro();
	}
	// create tags (per repo)
	foreach($config['modules'] as $repo => $modules)
	{
		$cmd = $svn.' cp --parents -m '.escapeshellarg('Creating '.$config['svntag']).' '.implode(' ',$modules).' '.$repo.'/'.$config['svntag'].'/';
		run_cmd($cmd);
	}
}

/**
 * Runs given shell command, exists with error-code after echoing the output of the failed command (if not already running verbose)
 *
 * @param string $cmd
 * @param array &$output=null $output of command, only if !$verbose !!!
 * @param int|array $no_bailout =null exit code(s) to NOT bail out
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
	if ($ret && !in_array($ret,(array)$no_bailout))
	{
		if (!$verbose) echo implode("\n",$output)."\n";
		throw new Exception("Error during '$cmd' --> aborting",$ret);
	}
	return $ret;
}

/**
 * Format array or other types as (one-line) string, eg. for error_log statements
 *
 * @param mixed $var variable to dump
 * @return string
 */
function array2string($var)
{
	switch (($type = gettype($var)))
	{
		case 'boolean':
			return $var ? 'TRUE' : 'FALSE';
		case 'string':
			return "'$var'";
		case 'integer':
		case 'double':
		case 'resource':
			return $var;
		case 'NULL':
			return 'NULL';
		case 'object':
		case 'array':
			return str_replace(array("\n",'    '/*,'Array'*/),'',print_r($var,true));
	}
	return 'UNKNOWN TYPE!';
}

/**
 * Give usage information and an optional error-message, before stoping program execution with exit-code 90 or 0
 *
 * @param string $error =null optional error-message
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

