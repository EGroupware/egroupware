#!/usr/bin/env php
<?php
/**
 * Install / update EGroupware - Command line interface
 *
 * Usage: install-cli.php [-v|--verbose] [--use-prerelease] (master|bugfix|release|<branch>|<tag>)
 *
 * EGroupware main directory should be either git cloned:
 *
 *	git clone -b <branch> https://github.com/EGroupware/egroupware [<target>]
 *
 * or created via composer create-project
 *
 *	composer create-project --prefer-source --keep-vcs egroupware/egroupware[:(dev-master|17.1.x-dev|<tag>)] <target>
 *
 * Both will create a git clone, which can be further updated by calling this tool without argument.
 *
 * We currently use 3 "channels":
 * - release: taged maintenance releases only eg. 17.1.20190214
 * - bugfix:  release-branch incl. latest bugfixes eg. 17.1 or 17.1.x-dev for composer
 * - master:  latest development for next release
 * To change the channel, call install-cli.php <channel-to-update-to>.
 *
 * This tool requires the following binaries installed at the usually places or in your path:
 * - php & git: apt/yum/zypper install php-cli git
 * - composer: see https://getcomposer.org/download/ for installation instructions
 * The following binaries are needed to minify JavaScript and CSS
 * - npm: apt/yum/zypper install npm
 * - grunt: npm install -g grunt-cli
 *
 * @link http://www.egroupware.org
 * @package api
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright (c) 2019 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

chdir(__DIR__);	// to enable relative pathes to work

if (php_sapi_name() !== 'cli')	// security precaution: forbit calling setup-cli as web-page
{
	die('<h1>install-cli.php must NOT be called as web-page --> exiting !!!</h1>');
}
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);

// parse arguments
$verbose = $use_prerelease = false;

$argv = $_SERVER['argv'];
$cmd  = array_shift($argv);

foreach($argv as $n => $arg)
{
	if ($arg[0] === '-')
	{
		switch($arg)
		{
			case '-v':
			case '--verbose':
				$verbose = true;
				unset($argv[$n]);
				break;

			case '--use-prerelease':
				$use_prerelease = true;
				unset($argv[$n]);
				break;

			case '-h':
			case '--help':
				usage();

			default:
				usage("Unknown argument '$arg'!");
		}
	}
}

if (count($argv) > 1) usage("Too many arguments!");

function usage($err=null)
{
	global $cmd;

	if ($err)
	{
		echo "$err\n\n";
	}
	die("Usage: $cmd [-v|--verbose] [--use-prerelease] (master|bugfix|release|<branch>|<tag>)\n\n");
}

$bins = array(
	'php'      => PHP_BINARY,
	'git'      => '/usr/bin/git',
	'composer' => ['/usr/bin/composer', '/usr/bin/composer.phar'],
	// npm and grunt are no hard requirement and should be the last in the list!
	'npm'      => '/usr/bin/npm',
	'grunt'    => '/usr/bin/grunt',
);

// check if the necessary binaries are installed
foreach($bins as $name => $binaries)
{
	foreach((array)$binaries as $bin)
	{
		if (file_exists($bin) && is_executable($bin))
		{
			$bins[$name] = $$name = $bin;
			continue 2;
		}
	}
	$output = null;
	if (($bin = exec('which '.$name, $output, $ret)) && !$ret &&
		(file_exists($bin)) && is_executable($bin))
	{
		$bins[$name] = $$name = $bin;
	}
	else
	{
		$bins[$name] = $$name = false;
		error_log("Could not find $name command!");
		if (!in_array($name, ['npm','grunt']))
		{
			exit(1);
		}
		else
		{
			error_log("npm and grunt are required to minify JavaScript and CSS files to improve performance.");
			break;
		}
	}
}

if ($verbose) echo "Using following binaries: ".json_encode ($bins, JSON_UNESCAPED_SLASHES)."\n";

if (!extension_loaded('curl')) die("Required PHP extesion 'curl' missing! You need to install php-curl package.\n\n");

// check if we are on a git clone
$output = array();
if (!$git || !file_exists(__DIR__.'/.git') || !is_dir(__DIR__.'/.git') ||
	!exec($git.' branch --no-color', $output, $ret) || $ret)
{
	foreach($output as $line)
	{
		error_log($line);
	}
	error_log("Could not identify git branch (you need to use git clone or composer create-project --prefer-source --keep-vcs egroupware/egroupware)!");
	exit(1);
}
foreach($output as $line)
{
	if ($line[0] == '*')
	{
		$branch = substr($line, 2);
		// are we on a tag
		if (preg_match('/^\(HEAD .* ([0-9.]+)\)$/', $branch, $matches))
		{
			$branch = $matches[1];
		}
		break;
	}
}
$channel = 'development';
if (preg_match('/^\d+\.\d+(\.\d{8})?/', $branch, $machtes))
{
	$channel = isset($matches[1]) ? 'release' : 'bugfix';
}
if ($verbose) echo "Currently using branch: $branch --> $channel channel\n";

if ($argv)
{
	$target = array_shift($argv);

	if ($target === 'release')
	{
		$target = get_latest_release($use_prerelease);
	}
	elseif ($target === 'bugfix')
	{
		$target = (string)(float)get_latest_release($use_prerelease);
	}
}
else
{
	$target = $branch;

	// find latest release
	if ($channel == 'release')
	{
		$target = get_latest_release($use_prerelease);
	}
}

echo "Updating to: $target\n";

// Update EGroupware itself and further apps installed via git
foreach(scandir(__DIR__) as $dir)
{
	if ($dir !== '..' && file_exists(__DIR__.'/'.$dir.'/.git') &&
		// these apps / dirs are managed by composer, no need to run manual updates
		!in_array($dir, ['vendor', 'activesync', 'collabora', 'projectmanager', 'tracker']))
	{
		$cmd = "cd $dir ; $git stash -q";
		// switch message about detached head off for release-channel/tags
		if (preg_match('/^\d+\.\d+\.\d{8}/', $target))
		{
			$cmd .= "; $git config advice.detachedHead false";
		}
		if ($branch != $target)
		{
			$cmd .= "; $git checkout $target";
		}
		// no need to pull for release-channel/tags
		if (!preg_match('/^\d+\.\d+\.\d{8}/', $target))
		{
			$cmd .= "; $git pull --rebase";
		}
		$cmd .= "; test -z $($git stash list) || echo $git stash pop";
		if ($verbose) echo "$cmd\n";
		system($cmd);
	}
}

// update composer managed dependencies
$cmd = $composer.' install';
if ($verbose) echo "$cmd\n";
system($cmd);

// update npm dependencies and run grunt to minify javascript and css
if ($npm && $grunt)
{
	$cmd = $npm.' install';
	if ($verbose) echo "$cmd\n";
	system($cmd);

	if ($verbose) echo "$grunt\n";
	system($grunt);
}

/**
 * Get latest release
 *
 * @param boolean $prerelease =false include releases taged as prerelease
 * @param boolean $return_name =true true: just return name, false: full release object
 * @return array|string|null null if no release found
 */
function get_latest_release($prerelease=false, $return_name=true)
{
	foreach(github_api('/repos/egroupware/egroupware/releases', [], 'GET') as $release)
	{
		if ($prerelease || $release['prerelease'] === false)
		{
			return $return_name ? $release['tag_name'] : $release;
		}
	}
	return null;
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
	global /*$config,*/ $verbose;

	$url = $_url[0] == '/' ? 'https://api.github.com'.$_url : $_url;
	$c = curl_init();
	curl_setopt($c, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	//curl_setopt($c, CURLOPT_USERPWD, $config['github_user'].':'.$config['github_token']);
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
