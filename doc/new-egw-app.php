#!/usr/bin/env php
<?php
/**
 * EGroupware - create new app based on example app
 *
 * Usage: doc/new-egw-app.php <appname>
 *
 * @link https://github.com/EGroupware/example/tree/master
 * @link https://www.egroupware.org
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright 2024 by Ralf Becker <rb@egroupware.org>
 */

if (php_sapi_name() !== 'cli')	// security precaution: forbid calling as web-page
{
	die('<h1>fix_api.php must NOT be called as web-page --> exiting !!!</h1>');
}

$verbose = $use_prerelease = $run_git = $continue_on_error = false;
$composer_args = [];

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

			case '-h':
			case '--help':
				usage();
				break;

			default:	// pass unknown arguments to composer install
				$composer_args[] = $arg;
				unset($argv[$n]);
				break;
		}
	}
}

if (0 >= count($argv) || count($argv) > 1) usage();
$app_name = $argv[0];

function usage($err=null)
{
	global $cmd;

	die("Usage:\t$cmd [-v|--verbose] <appname>\n\n".
        "Example:\t$cmd hosts\n\n".
        ($err ? "$err\n\n" : ''));
}

$bins = array(
	'php'      => PHP_BINARY,
	'git'      => ['/usr/local/bin/git', '/usr/bin/git'],
	'composer' => ['/usr/local/bin/composer', '/usr/bin/composer', '/usr/bin/composer.phar'],
	// npm and grunt are no hard requirement and should be the last in the list!
	'npm'      => ['/usr/local/bin/npm', '/usr/bin/npm'],
	'grunt'    => [__DIR__.'/node_modules/.bin/grunt', '/usr/local/bin/grunt', '/usr/bin/grunt'],
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
	$output = $ret = null;
	if (($bin = exec('which '.$name, $output, $ret)) && !$ret &&
		(file_exists($bin)) && is_executable($bin))
	{
		$bins[$name] = $$name = $bin;
	}
	// check if we can just run it, because it's in the path
	elseif (exec($name.' -v', $output, $ret) && !$ret)
	{
		$bins[$name] = $$name = $num;
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

if (!preg_match('/^[a-z]+$/', $app_name))
{
    usage("Hostname must only container lowercase letters!");
}

$egw_dir = dirname(__DIR__);
chdir($egw_dir);
$app_dir = $egw_dir.'/'.$app_name;
if (file_exists($app_dir))
{
	die((is_dir($app_dir)?"Directory":"File")." ".$app_dir." already exists --> aborting!\n");
}
if (system('bash -x -c "'.$git.' clone -b master https://github.com/EGroupware/example.git '.$app_name.'"') === false) exit(1);

replace([
        'example' => $app_name,
        'Example' => ucfirst($app_name),
        'Beispiel' => ucfirst($app_name),
        "'host_" => "'".preg_replace('/s$/', '', $app_name).'_',
	    "'egw_hosts" => "'egw_$app_name",
        '$host_id' => '$'.preg_replace('/s$/', '', $app_name).'_id',
], $app_dir);

// create empty README.md
file_put_contents($app_dir.'/README.md', "# ".ucfirst($app_name)." app for EGroupware\n");

echo "Renamed app to $app_name\n";

chdir($app_dir);

if (system('bash -x -c "'.$git.' add .'.'"') === false) exit(1);
if (system('bash -x -c "'.$git." commit -m 'Renamed app to $app_name'".'"') === false) exit(1);
if (system('bash -x -c "'.$git.' remote rename origin upstream'.'"') === false) exit(1);

echo "App $app_name successful created.\n\n";
echo "You need to set now a repo-url and push the created app: git remote add origin <repo-url> && git push --set-upstream origin master\n";

exit(0);

/**
 * Recursively replace all patterns in all files
 * @param array $patterns replace => with pairs
 * @param string $dir
 * @return void
 */
function replace(array $patterns, $dir)
{
    foreach(scandir($dir) as $file)
    {
        if ($file === '.' || $file === '..') continue;
        if (is_dir($dir.'/'.$file))
        {
            replace($patterns, $dir.'/'.$file);
            continue;
        }
        $content = file_get_contents($dir.'/'.$file);
        $out = strtr($content, $patterns);
        if ($out !== $content)
        {
            file_put_contents($dir.'/'.$file, $out);
        }
    }
}