#!/usr/bin/php -qC
<?php
/**
 * EGroupware - checkout, build and release archives, build Debian and rpm packages
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author RalfBecker@outdoor-training.de
 * @copyright (c) 2009-19 by Ralf Becker <rb@egroupware.org>
 */

if (php_sapi_name() !== 'cli')	// security precaution: forbit calling setup-cli as web-page
{
	die('<h1>checkout-build-archives.php must NOT be called as web-page --> exiting !!!</h1>');
}
date_default_timezone_set('Europe/Berlin');	// to get ride of 5.3 warnings

$verbose = 0;
$config = array(
	'packagename' => 'egroupware-epl',
	'version' => '17.1',        // '17.1'
	'packaging' => date('Ymd'), // '20160520'
	'branch'  => 'master',        // checked out branch
	'tag' => '$version.$packaging',	// name of tag
	'checkoutdir' => realpath(__DIR__.'/../..'),
	'egw_buildroot' => '/tmp/build_root/epl_17.1_buildroot',
	'sourcedir' => '/home/download/stylite-epl/egroupware-epl-17.1',
	/* svn-config currently not used, as we use .mrconfig to define modules and urls
	'svntag' => 'tags/$version.$packaging',
	'svnbase' => 'svn+ssh://svn@dev.egroupware.org/egroupware',
	'stylitebase' => 'svn+ssh://stylite@svn.stylite.de/stylite',
	'svnbranch' => 'branches/16.1',         //'trunk', // 'branches/1.6' or 'tags/1.6.001'
	'svnalias' => 'aliases/default-ssh',    // default alias
	'extra' => array('$stylitebase/$svnbranch/stylite', '$stylitebase/$svnbranch/esyncpro', '$stylitebase/trunk/archive'),//, '$stylitebase/$svnbranch/groups'), //,'svn+ssh://stylite@svn.stylite.de/stylite/trunk/eventmgr'),
	*/
	'extra' => array('functions' => array('stylite'), 'esyncpro', 'archive',	// create an extra archive for given apps
		// these apps are placed in egroupware-epl-contrib archive
		'contrib' => array('phpgwapi', 'etemplate', 'jdots', 'phpbrain', 'wiki', 'sambaadmin', 'sitemgr', 'phpfreechat')),
	'aliasdir' => 'egroupware',             // directory created by the alias
	'types' => array('tar.bz2','tar.gz','zip','all.tar.bz2'),
	// add given extra-apps or (uncompressed!) archives to above all.tar.bz2 archive
	'all-add' => array('contrib', '/home/stylite/epl-trunk/phpfreechat_data_public.tar'),
	// diverse binaries we need
	'svn' => trim(`which svn`),
	'tar' => trim(`which tar`),
	'mv' => trim(`which mv`),
	'rm' => trim(`which rm`),
	'zip' => trim(`which zip`),
	'bzip2' => trim(`which bzip2`),
	'clamscan' => trim(`which clamscan`),
	'freshclam' => trim(`which freshclam`),
	'git' => trim(`which git`),
	'gpg' => trim(`which gpg`),
	'editor' => trim(`which vi`),
	'rsync' => trim(`which rsync`).' --progress -e ssh --exclude "*-stylite-*" --exclude "*-esyncpro-*"',
	'composer' => trim(`which composer.phar`),
	'after-checkout' => 'rm -rf */source */templates/*/source',
	'packager' => 'build@egroupware.org',
	'obs' => '/home/stylite/obs/stylite-epl-trunk',
	'obs_package_alias' => '',	// name used in obs package, if different from packagename
	'changelog' => false,   // eg. '* 1. Zeile\n* 2. Zeile' for debian.changes
	'changelog_packager' => 'Ralf Becker <rb@egroupware.org>',
	'editchangelog' => '* ',
	//'sfuser' => 'ralfbecker',
	//'release' => '$sfuser,egroupware@frs.sourceforge.net:/home/frs/project/e/eg/egroupware/eGroupware-$version/eGroupware-$version.$packaging/',
	// what gets uploaded with upload
	'upload' => '$sourcedir/*egroupware-epl{,-contrib}-$version.$packaging*',
	'copychangelog' => '$sourcedir/README', //'$sfuser,egroupware@frs.sourceforge.net:/home/frs/project/e/eg/egroupware/README',
	'skip' => array(),
	'run' => array('checkout','editchangelog','tag','copy','virusscan','create','sign','obs','copychangelog'),
	'patchCmd' => '# run cmd after copy eg. "cd $egw_buildroot; patch -p1 /path/to/patch"',
	'github_user' => 'ralfbecker',	// Github user for following token
	'github_token' => '',	// Github repo personal access token from above user
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
		if (in_array('editchangelog', $config['skip']) || !in_array('editchangelog', $config['run']))
		{
			$config['changelog'] = parse_current_changelog(true);
		}
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
			case 'types':
			case 'add-all':
			case 'modules':
				$op = '=';
				if (in_array($value[0], array('+', '-')))
				{
					$op = $value[0];
					$value = substr($value, 1);
				}
				if (in_array($value[0], array('[', '{')) && ($json = json_decode($value, true)))
				{
					$value = $json;
				}
				else
				{
					$value = array_unique(preg_split('/[ ,]+/', $value));
				}
				switch($op)
				{
					case '+':
						$config[$name] = array_unique(array_merge($config[$name], $value));
						break;
					case '-':
						$config[$name] = array_diff($config[$name], $value);
						break;
					default:
						$config[$name] = $value;
				}
				break;

			case 'svntag':
			case 'tag':
			case 'release':
			case 'copychangelog':
				$config[$name] = $value;
				if ($value) array_unshift($config['run'],$name);
				break;

			case 'editchangelog':
				$config[$name] = $value ? $value : true;
				if (!in_array('editchangelog',$config['run']))
				{
					array_unshift($config['run'],'editchangelog');
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
	echo "Using following config:\n".json_encode($config, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n\n";
}
$svn = $config['svn'];

$run = array_diff($config['run'],$config['skip']);

// if we dont edit the changelog, set packaging from changelog
if (!in_array('editchangelog', $run))
{
	parse_current_changelog(true);
}
foreach($run as $func)
{
	chdir(dirname(__FILE__));	// make relative filenames work, if other command changes dir
	call_user_func('do_'.$func);
}

/**
 * Read changelog for given branch from (last) tag or given revision from svn
 *
 * @param string $_path relativ path to repo starting with $config['aliasdir']
 * @param string $log_pattern =null	a preg regular expression or start of line a log message must match, to be returned
 * 	if regular perl regular expression given only first expression in brackets \\1 is used,
 * 	for a start of line match, only the first line is used, otherwise whole message is used
 * @param string& $last_tag =null from which tag on to query logs
 * @param string $prefix ='* ' prefix, which if not presend should be added to all log messages
 * @return string with changelog
 */
function get_changelog_from_git($_path, $log_pattern=null, &$last_tag=null, $prefix='* ')
{
	//echo __FUNCTION__."('$branch_url','$log_pattern','$revision','$prefix')\n";
	global $config;

	$changelog = '';
	$path = str_replace($config['aliasdir'], $config['checkoutdir'], $_path);
	if (!file_exists($path) || !is_dir($path) || !file_exists($path.'/.git'))
	{
		throw new Exception("$path is not a git repository!");
	}
	if (empty($last_tag))
	{
		$last_tag = get_last_git_tag();
	}
	if (!empty($last_tag))
	{
		$cmd = $config['git'].' log '.escapeshellarg($last_tag.'..HEAD');
		if (getcwd() != $path) $cmd = 'cd '.$path.'; '.$cmd;
		$output = null;
		run_cmd($cmd, $output);

		foreach($output as $line)
		{
			if (substr($line, 0, 4) == "    " && ($msg = _match_log_pattern(substr($line, 4), $log_pattern, $prefix)))
			{
				$changelog .= $msg."\n";
			}
		}
	}
	return $changelog;
}

/**
 * Get module path (starting with $config['aliasdir']) per repo from .mrconfig for svn and git
 *
 * @return array with $repro_url => $path => $url, eg. array(
 *		"git@github.com:EGroupware/egroupware.git" => array(
 *			"egroupware" => "git@github.com:EGroupware/egroupware.git"),
 *		"git@github.com:EGroupware/tracker.git" => array(
 *			"egroupware/tracker" => "git@github.com:EGroupware/tracker.git"),
 *		"svn+ssh://stylite@svn.stylite.de/stylite" => array(
 *			"egroupware/stylite] => svn+ssh://stylite@svn.stylite.de/stylite/branches/14.2/stylite",
 *			"egroupware/esyncpro] => svn+ssh://stylite@svn.stylite.de/stylite/branches/14.2/esyncpro",
 */
function get_modules_per_repo()
{
	global $config, $verbose;

	if ($verbose) echo "Get modules from .mrconfig in checkoutdir $config[checkoutdir]\n";

	if (!is_dir($config['checkoutdir']))
	{
		throw new Exception("checkout directory '{$config['checkoutdir']} does NOT exists or is NO directory!");
	}
	if (!($mrconfig = file_get_contents($path=$config['checkoutdir'].'/.mrconfig')))
	{
		throw new Exception("$path not found!");
	}
	$module = $baseurl = null;
	$modules = array();
	foreach(explode("\n", $mrconfig) as $line)
	{
		$matches = null;
		if (isset($baseurl))
		{
			$line = str_replace("\${EGW_REPO_BASE:-\$(git config --get remote.origin.url|sed 's|/egroupware.git||')}", $baseurl, $line);
		}
		if ($line && $line[0] == '[' && preg_match('/^\[([^]]*)\]/', $line, $matches))
		{
			if (in_array($matches[1], array('DEFAULT', 'vendor/egroupware/ckeditor', 'api/src/Accounts/Ads', 'phpgwapi/js/ckeditor', 'phpgwapi/inc/adldap')))
			{
				$module = null;
				continue;
			}
			$module = (string)$matches[1];
		}
		elseif (isset($module) && preg_match('/^checkout\s*=\s*(git\s+clone\s+(-b\s+[0-9.]+\s+)?((git|http)[^ ]+)|svn\s+checkout\s+((svn|http)[^ ]+))/', $line, $matches))
		{
			$repo = $url = substr($matches[1], 0, 3) == 'svn' ? $matches[5] : $matches[3];
			if (substr($matches[1], 0, 3) == 'svn') $repo = preg_replace('#/(trunk|branches)/.*$#', '', $repo);
			$modules[$repo][$config['aliasdir'].($module ? '/'.$module : '')] = $url;
			if ($module === '' && !isset($baseurl)) $baseurl = str_replace('/egroupware.git', '', $url);
		}
	}
	if ($verbose) print_r($modules);
	return $modules;
}

/**
 * Get commit of last git tag matching a given pattern
 *
 * @return string name of last tag matching $config['version'].'.*'
 */
function get_last_git_tag()
{
	global $config;

	if (!is_dir($config['checkoutdir']))
	{
		throw new Exception("checkout directory '{$config['checkoutdir']} does NOT exists or is NO directory!");
	}
	chdir($config['checkoutdir']);

	$cmd = $config['git'].' tag -l '.escapeshellarg($config['version'].'.*');
	$output = null;
	run_cmd($cmd, $output);
	array_shift($output);

	return trim(array_pop($output));
}

/**
 * Checkout or update EGroupware
 *
 * Ensures an existing checkout is from the correct branch! Otherwise it get's deleted
 */
function do_checkout()
{
	global $config;

	echo "Starting checkout/update\n";
	if (!file_exists($config['checkoutdir']))
	{
		$cmd = $config['git'].' clone '.(!empty($config['branch']) ? ' -b '.$config['branch'] : '').
			' git@github.com:EGroupware/egroupware.git '.$config['checkoutdir'];
		run_cmd($cmd);
	}
	elseif (!is_dir($config['checkoutdir']) || !is_writable($config['checkoutdir']))
	{
		throw new Exception("checkout directory '{$config['checkoutdir']} exists and is NO directory or NOT writable!");
	}
	chdir($config['checkoutdir']);

	run_cmd('./install-cli.php --ignore-platform-reqs --no-dev');
}

/**
 * Create a tag using mr in svn or git for current checked out branch
 */
function do_tag()
{
	global $config;

	if (!is_dir($config['checkoutdir']))
	{
		throw new Exception("checkout directory '{$config['checkoutdir']} does NOT exists or is NO directory!");
	}
	chdir($config['checkoutdir']);

	$config['tag'] = config_translate('tag');	// allow to use config vars like $version in tag

	if (empty($config['tag'])) return;	// otherwise we copy everything in svn root!

	echo "Creating tag and pushing $config[tag]\n";

	run_cmd('./install-cli.php --git tag '.escapeshellarg($config['tag']).' -m '.escapeshellarg('Creating '.$config['tag']));

	// push tags in all apps (not main-dir!)
	run_cmd('./install-cli.php --git-apps push origin '.escapeshellarg($config['tag']));

	// checkout tag, update composer.{json,lock}, move tag to include them
	run_cmd($config['git'].' checkout '.$config['tag']);
	update_composer_json_version($config['tag']);
	// might require more then one run, as pushed tags need to be picked up by packagist
	$output = $ret = null;
	$timeout = 10;
	for($try=1; $try < 10 && run_cmd($config['composer'].' update --ignore-platform-reqs --no-dev egroupware/\*', $output, 2); ++$try)
	{
		echo "$try. retry in $timeout seconds ...\n";
		sleep($timeout);
	}
	run_cmd($config['git'].' commit -m '.escapeshellarg('Updating dependencies for '.$config['tag']).' composer.{json,lock}');
	run_cmd($config['git'].' tag -f '.escapeshellarg($config['tag']).' -m '.escapeshellarg('Updating dependencies for '.$config['tag']));
}

/**
 * Update composer.json with version number (or add it after "name" if not yet there)
 *
 * @param string $version
 * @throws Exception on error
 */
function update_composer_json_version($version)
{
	global $config;

	if (!($json = file_get_contents($path=$config['checkoutdir'].'/composer.json')))
	{
		throw new Exception("Can NOT read $path to update with new version!");
	}
	if (preg_match('/"version":\s*"[^"]+"/', $json))
	{
		$json = preg_replace('/"version":\s*"[^"]+"/', '"version": "'.$version.'"', $json);
	}
	elseif (preg_replace('/^(\s*)"name":\s*"[^"]+",$/m', $json))
	{
		$json = preg_replace('/^(\s*)"name":\s*"[^"]+",$/m', '$0'."\n".'$1"version": "'.$version.'",', $json);
	}
	else
	{
		throw new Exception("Failed to add new version to $path!");
	}
	if (!file_put_contents($path, $json))
	{
		throw new Exception("Can NOT update $path with new version!");
	}
}

/**
 * Release sources by rsync'ing them to a distribution / download directory
 */
function do_release()
{
	global $config,$verbose;

	// push local changes to Github incl. tag (tags of apps are already pushed by do_tag)
	if ($verbose) echo "Pushing changes and tags\n";
	chdir($config['checkoutdir']);
	run_cmd($config['git'].' push');
	$tag = config_translate('tag');
	run_cmd($config['git'].' push origin '.$tag);
	// checkout release-branch again (we are on the tag!)
	run_cmd($config['git'].' checkout '.$config['version']);

	if (empty($config['github_user']) || empty($config['github_token']))
	{
		throw new Exception("No personal Github user or access token specified (--github_token)!");
	}
	if (empty($config['changelog']))
	{
		$config['changelog'] = parse_current_changelog();
	}
	$data = array(
		'tag_name' => $tag,
		'name' => $tag,
		'target_commitish' => $config['branch'],
		'body' => $config['changelog'],
	);
	$response = github_api("/repos/EGroupware/egroupware/releases", $data);
	$config['upload_url'] = preg_replace('/{\?[^}]+}$/', '', $response['upload_url']);	// remove {?name,label} template

	do_upload();
}

/**
 * Upload archives
 */
function do_upload()
{
	global $config,$verbose;

	if (empty($config['upload_url']))
	{
		$response = github_api("/repos/EGroupware/egroupware/releases", array(), 'GET');
		$config['upload_url'] = preg_replace('/{\?[^}]+}$/', '', $response[0]['upload_url']);	// remove {?name,label} template
	}

	$archives = config_translate('upload');
	echo "Uploading $archives to $config[upload_url]\n";

	foreach(glob($archives, GLOB_BRACE) as $path)
	{
		$label = null;
		if (substr($path, -4) == '.zip')
		{
			$content_type = 'application/zip';
		}
		elseif(substr($path, -7) == '.tar.gz')
		{
			$content_type = 'application/x-gzip';
		}
		elseif(substr($path, -8) == '.tar.bz2')
		{
			$content_type = 'application/x-bzip2';
		}
		elseif(substr($path, -8) == '.txt.asc')
		{
			$content_type = 'text/plain';
			$label = 'Signed hashes of downloads';
		}
		else
		{
			continue;
		}
		if ($verbose) echo "Uploading $path as $content_type\n";
		$name = basename($path);
		github_api($config['upload_url'], array(
			'name' => $name,
			'label' => isset($label) ? $label : $name,
		), 'FILE', $path, $content_type);
	}

	if (!empty($config['release']))
	{
		$target = config_translate('release');	// allow to use config vars like $svnbranch in module
		$cmd = $config['rsync'].' '.$archives.' '.$target;
		if ($verbose) echo $cmd."\n";
		passthru($cmd);
	}
}

/**
 * Sending a Github API request
 *
 * @param string $_url url of just path where to send request to (https://api.github.com is added automatic)
 * @param string|array $data payload, array get automatic added as get-parameter or json_encoded for POST
 * @param string $method ='POST'
 * @param string $upload =null path of file to upload, payload for request with $method='FILE'
 * @param string $content_type =null
 * @throws Exception
 * @return array with response
 */
function github_api($_url, $data, $method='POST', $upload=null, $content_type=null)
{
	global $config, $verbose;

	$url = $_url[0] == '/' ? 'https://api.github.com'.$_url : $_url;
	$c = curl_init();
	curl_setopt($c, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($c, CURLOPT_USERPWD, $config['github_user'].':'.$config['github_token']);
	curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($c, CURLOPT_USERAGENT, basename(__FILE__));
	curl_setopt($c, CURLOPT_TIMEOUT, 240);
	curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);

	switch($method)
	{
		case 'POST':
			curl_setopt($c, CURLOPT_POST, true);
			if (is_array($data)) $data = json_encode($data, JSON_FORCE_OBJECT);
			curl_setopt($c, CURLOPT_POSTFIELDS, $data);
			break;
		case 'GET':
			if(count($data)) $url .= '?' . http_build_query($data);
			break;
		case 'FILE':
			curl_setopt($c, CURLOPT_HTTPHEADER, array("Content-type: $content_type"));
			curl_setopt($c, CURLOPT_POST, true);
			curl_setopt($c, CURLOPT_POSTFIELDS, file_get_contents($upload));
			if(count($data)) $url .= '?' . http_build_query($data);
			break;
		default:
			throw new Exception(__FUNCTION__.": Unknown/unimplemented method=$method!");
	}
	curl_setopt($c, CURLOPT_URL, $url);

	if (is_string($data)) $short_data = strlen($data) > 100 ? substr($data, 0, 100).' ...' : $data;
	if ($verbose) echo "Sending $method request to $url ".(isset($short_data)&&$method!='GET'?$short_data:'')."\n";

	if (($response = curl_exec($c)) === false)
	{
		// run failed request again to display response including headers
		curl_setopt($c, CURLOPT_HEADER, true);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, false);
		curl_exec($c);
		throw new Exception("$method request to $url failed ".(isset($short_data)&&$method!='GET'?$short_data:''));
	}

	if ($verbose) echo (strlen($response) > 200 ? substr($response, 0, 200).' ...' : $response)."\n";

	curl_close($c);

	return json_decode($response, true);
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
 * Query changelog and let user edit it
 */
function do_editchangelog()
{
	global $config,$svn;

	echo "Querying changelog from Git/SVN\n";
	if (!isset($config['modules']))
	{
		$config['modules'] = get_modules_per_repo();
	}
	// query changelog per repo
	$changelog = '';
	$last_tag = null;
	foreach($config['modules'] as $branch_url => $modules)
	{
		$revision = null;
		if (substr($branch_url, -4) == '.git')
		{
			list($path) = each($modules);
			$changelog .= get_changelog_from_git($path, $config['editchangelog'], $last_tag);
		}
		else
		{
			$changelog .= get_changelog_from_svn($branch_url, $config['editchangelog'], $revision);
		}
	}
	if (empty($changelog))
	{
		$changelog = "Could not query changelog for $config[version], eg. no last tag found!\n";
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
	$changelog = $config['checkoutdir'].'/doc/rpm-build/debian.changes';
	if (!file_exists($changelog))
	{
		throw new Exception("Changelog '$changelog' not found!");
	}
	file_put_contents($changelog, update_changelog(file_get_contents($changelog)));

	update_api_setup($api_setup=$config['checkoutdir'].'/api/setup/setup.inc.php');

	if (file_exists($config['checkoutdir'].'/.git'))
	{
		$cmd = $config['git']." commit -m 'Changelog for $config[version].$config[packaging]' ".$changelog.' '.$api_setup;
	}
	else
	{
		$cmd = $svn." commit -m 'Changelog for $config[version].$config[packaging]' ".$changelog.' '.$api_setup;
	}
	run_cmd($cmd);

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
 * @param string& $revision =null from which to HEAD the log should be retrieved, default search revision of latest tag in ^/tags
 * @param string $prefix ='* ' prefix, which if not presend should be added to all log messages
 * @return string with changelog
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
		$pattern='|/tags/('.preg_quote($config['version'], '|').'\.[0-9.]+)|';
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
	foreach($xml as $log)
	{
		if (!($msg = _match_log_pattern($log->msg, $log_pattern, $prefix))) continue;	// no match --> ignore

		$message .= $msg."\n";
	}
	if ($verbose) echo $message;

	return $message;
}

/**
 * Return first row of matching log lines always prefixed with $prefix
 *
 * @param string $msg whole log message
 * @param string $log_pattern
 * @param string $prefix ='* '
 * @return string
 */
function _match_log_pattern($msg, $log_pattern, $prefix='* ')
{
	$pattern_len = strlen($log_pattern);
	$prefix_len = strlen($prefix);

	$matches = null;
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
		return null;
	}
	if ($prefix_len && substr($msg,0,$prefix_len) != $prefix) $msg = $prefix.$msg;

	return $msg;
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

	$cmd = $svn.' log --xml --limit 40 -v '.escapeshellarg($tags_url);
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
	$is_regexp = $pattern[0] == substr($pattern, -1);
	foreach($xml as $log)
	{
		//print_r($log);
		if (!$is_regexp && strpos($log->paths->path, $pattern) !== false ||
			$is_regexp && preg_match($pattern, $log->paths->path, $matches))
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
 * @param boolean $set_packaging =false true: set packaging from last changelog entry
 * @return string changelog entries without header and footer lines
 */
function parse_current_changelog($set_packaging=false)
{
	global $config;

	$changelog = file_get_contents($config['checkoutdir'].'/doc/rpm-build/debian.changes');
	$lines = explode("\n", $changelog, 100);
	$matches = null;
	foreach($lines as $n => $line)
	{
		if (preg_match($preg='/^'.preg_quote($config['packagename']).' \('.preg_quote($config['version']).'\.'.
			($set_packaging ? '([0-9]+)' : preg_quote($config['packaging'])).'/', $line, $matches))
		{
			if ($set_packaging)
			{
				$config['packaging'] = $matches[1];
			}
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
 * Update content of api/setup/setup.inc.php file with new maintenance version
 *
 * @param string $path full path to "api/setup/setup.inc.php"
 */
function update_api_setup($path)
{
	global $config;

	if (!($content = file_get_contents($path)))
	{
		throw new Exception("Could not read file '$path' to update maintenance-version!");
	}

	$content = preg_replace('/'.preg_quote("\$setup_info['api']['versions']['maintenance_release']", '/').'[^;]+;/',
		"\$setup_info['api']['versions']['maintenance_release'] = '".$config['version'].'.'.$config['packaging']."';",
		$content);

	if (!file_put_contents($path, $content))
	{
		throw new Exception("Could not update file '$path' with maintenance-version!");
	}
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
		$exclude = $exclude_all = array();
		foreach($config['extra'] as $name => $modules)
		{
			foreach((array)$modules as $module)
			{
				$exclude[] = basename($module);
				if (!empty($config['all-add']) && !in_array($module, $config['all-add']) && (is_int($name) || !in_array($name, $config['all-add'])))
				{
					$exclude_all[] = basename($module);
				}
			}
		}
		$exclude_extra = ' --exclude=egroupware/'.implode(' --exclude=egroupware/', $exclude);
		$exclude_all_extra =  $exclude_all ? ' --exclude=egroupware/'.implode(' --exclude=egroupware/', $exclude_all) : '';
	}
	foreach($config['types'] as $type)
	{
		echo "Creating $type archives\n";
		$tar_type = $type == 'tar.bz2' ? 'j' : 'z';

		$file = $config['sourcedir'].'/'.$config['packagename'].'-'.$config['version'].'.'.$config['packaging'].'.'.$type;
		switch($type)
		{
			case 'all.tar.bz2':	// single tar-ball for debian builds not easily supporting to use multiple
				$file = $config['sourcedir'].'/'.$config['packagename'].'-all-'.$config['version'].'.'.$config['packaging'].'.tar';
				$cmd = $config['tar'].' --owner=root --group=root -cf '.$file.$exclude_all_extra.' egroupware';
				if (!empty($config['all-add']))
				{
					foreach((array)$config['all-add'] as $add)
					{
						if (substr($add, -4) != '.tar') continue;	// probably a module
						if (!($tar = realpath($add))) throw new Exception("File '$add' not found!");
						$cmd .= '; '.$config['tar'].' --owner=root --group=root -Af '.$file.' '.$tar;
					}
				}
				if (file_exists($file.'.bz2')) $cmd .= '; rm -f '.$file.'.bz2';
				$cmd .= '; '.$config['bzip2'].' '.$file;
				// run cmd now and continue without adding all tar-ball to sums, as we dont want to publish it
				run_cmd($cmd);
				continue 2;
			case 'tar.bz2':
			case 'tar.gz':
				$cmd = $config['tar'].' --owner=root --group=root -c'.$tar_type.'f '.$file.$exclude_extra.' egroupware';
				break;
			case 'zip':
				$cmd = file_exists($file) ? $config['rm'].' -f '.$file.'; ' : '';
				$cmd .= $config['mv'].' egroupware/'.implode(' egroupware/', $exclude).' . ;';
				$cmd .= $config['zip'].' -q -r -9 '.$file.' egroupware ;';
				$cmd .= $config['mv'].' '.implode(' ', $exclude).' egroupware';
				break;
		}
		run_cmd($cmd);
		$sums .= sha1_file($file)."\t".basename($file)."\n";

		foreach($config['extra'] as $name => $modules)
		{
			if (is_numeric($name)) $name = $modules;
			$dirs = ' egroupware/'.implode(' egroupware/', (array)$modules);
			$file = $config['sourcedir'].'/'.$config['packagename'].'-'.$name.'-'.$config['version'].'.'.$config['packaging'].'.'.$type;
			switch($type)
			{
				case 'all.tar.bz2':
					break;	// nothing to do
				case 'tar.bz2':
				case 'tar.gz':
					$cmd = $config['tar'].' --owner=root --group=root -c'.$tar_type.'f '.$file.$dirs;
					break;
				case 'zip':
					$cmd = file_exists($file) ? $config['rm'].' -f '.$file.'; ' : '';
					$cmd .= $config['zip'].' -q -r -9 '.$file.$dirs;
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
 * Copy non .svn/.git parts to egw_buildroot and fix permissions and ownership
 *
 * We need to stash local modifications (currently only in egroupware main module) to revert eg. .mrconfig modifications
 */
function do_copy()
{
	global $config;

	// copy everything, but .svn dirs from checkoutdir to egw_buildroot
	echo "Copying non-svn/git/tests dirs to buildroot\n";

	if (!file_exists($config['egw_buildroot']))
	{
		run_cmd("mkdir -p $config[egw_buildroot]");
	}

	// we need to stash uncommited changes like .mrconfig, before copying
	if (file_exists($config['checkoutdir'].'/.git')) run_cmd("cd $config[checkoutdir]; git stash");

	try {
		$cmd = '/usr/bin/rsync -r --delete --delete-excluded --exclude .svn --exclude .git\* --exclude .mrconfig --exclude node_modules/ --exclude tests '.$config['checkoutdir'].'/ '.$config['egw_buildroot'].'/'.$config['aliasdir'].'/';
		run_cmd($cmd);
	}
	catch (Exception $e) {
		// catch failures to pop stash, before throwing exception
	}
	if (file_exists($config['checkoutdir'].'/.git')) run_cmd("git stash pop");
	if (isset($e)) throw $e;

	if (($cmd = config_translate('patchCmd')) && $cmd[0] != '#')
	{
		echo "Running $cmd\n";
		run_cmd($cmd);
	}
	// fix permissions
	echo "Fixing permissions\n";
	chdir($config['egw_buildroot'].'/'.$config['aliasdir']);
	run_cmd('/bin/chmod -R a-x,u=rwX,g=rX,o=rX .');
	run_cmd('/bin/chmod +x */*cli.php phpgwapi/cron/*.php doc/rpm-build/*.php');
}

/**
 * Checkout or update EGroupware
 *
 * Ensures an existing checkout is from the correct branch! Otherwise it get's deleted
 */
function do_svncheckout()
{
	global $config,$svn;

	echo "Starting svn checkout/update\n";
	if (!file_exists($config['checkoutdir']))
	{
		mkdir($config['checkoutdir'],0755,true);
	}
	elseif (!is_dir($config['checkoutdir']) || !is_writable($config['checkoutdir']))
	{
		throw new Exception("svn checkout directory '{$config['checkoutdir']} exists and is NO directory or NOT writable!");
	}
	chdir($config['checkoutdir']);

	// do we use a just created tag --> list of taged modules
	if ($config['svntag'])
	{
		if (!isset($config['modules']))
		{
			get_modules_per_repo();
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
		run_cmd($config['composer'].' install --ignore-platform-reqs --no-dev');
	}
	// run after-checkout command(s), eg. to purge source directories
	run_cmd($config['after-checkout']);
}

/**
 * Get module path per svn repo from our config
 *
 * @return array with $repro_url => $path => $url, eg. array(
 *		"svn+ssh://svn@dev.egroupware.org/egroupware" => array(
 *			"egroupware" => "svn+ssh://svn@dev.egroupware.org/egroupware/branches/14.2/egroupware",
 *			"egroupware/addressbook" => "svn+ssh://svn@dev.egroupware.org/egroupware/branches/14.2/addressbook",
 */
function get_modules_per_svn_repo()
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
	if ($verbose) print_r($config['modules']);
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
		get_modules_per_repo();
	}
	// create tags (per repo)
	foreach($config['modules'] as $repo => $modules)
	{
		$cmd = $svn.' cp --parents -m '.escapeshellarg('Creating '.$config['svntag']).' '.implode(' ',$modules).' '.$repo.'/'.$config['svntag'].'/';
		run_cmd($cmd);
	}
}

/**
 * Runs given shell command
 *
 * If command return non-zero exit-code:
 * 1) output is echoed, if not already running verbose
 * 2a) if exit-code is contained in $no_bailout --> return it
 * 2b) otherwise throws with $cmd as message and exit-code
 *
 * @param string $cmd
 * @param array& $output=null $output of command, only if !$verbose !!!
 * @param int|array $no_bailout =null exit code(s) to NOT bail out
 * @throws Exception on non-zero exit-code not matching $no_bailout
 * @return int exit code of $cmd
 */
function run_cmd($cmd,array &$output=null,$no_bailout=null)
{
	global $verbose;

	if ($verbose && func_num_args() == 1)
	{
		echo $cmd."\n";
		$ret = null;
		system($cmd,$ret);
	}
	else
	{
		$output[] = $cmd;
		exec($cmd,$output,$ret);
		if ($verbose) echo implode("\n",$output)."\n";
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
	global $prog,$config,$verbose;

	echo "Usage: $prog [-h|--help] [-v|--verbose] [options, ...]\n\n";
	echo "options and their defaults:\n";
	if ($verbose)
	{
		if (!isset($config['modules'])) $config['modules'] = get_modules_per_repo();
	}
	else
	{
		unset($config['modules']);	// they give an error, because of nested array and are quite lengthy
	}
	foreach($config as $name => $default)
	{
		if (is_array($default)) $default = json_encode ($default, JSON_UNESCAPED_SLASHES);
		echo '--'.str_pad($name,20).$default."\n";
	}
	if ($error)
	{
		echo "$error\n\n";
		exit(90);
	}
	exit(0);
}
