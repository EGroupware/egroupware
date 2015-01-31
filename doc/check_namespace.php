#!/usr/bin/php -qC
<?php
/**
 * EGroupware - check namespace usage in converted code
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Ralf Becker <rb@stylite.de>
 * @copyright 2015 by Ralf Becker <rb@stylite.de>
 * @version $Id$
 */

if (php_sapi_name() !== 'cli')	// security precaution: forbit calling as web-page
{
	die('<h1>check_namespace.php must NOT be called as web-page --> exiting !!!</h1>');
}

/**
 * Check namespace usage in converted code
 *
 * @param string $file filename
 * @return boolean false on error
 */
function check_namespace($file)
{
	global $prog;
	if (basename($file) == $prog) return true;	// dont fix ourself ;-)

	if (($content = file_get_contents($file)) === false) return false;

	// remove commented lines
	$lines = preg_replace('#(//.*$|/\\*.*\\*/)#msU', '', $content);

	$namespace = '';
	$use = array();
	$allways = array('self', 'parent', 'static');
	foreach(explode("\n", $lines) as $num => $line)
	{
		$matches = null;
		if (preg_match('/namespace\s+([A-Za-z0-9_\\\\]+);/', $line, $matches))
		{
			$namespace = $matches[1];
			$use = array();
		}
		if ($namespace === '') continue;

		if (preg_match('/use\s+([^;]+);/', $line, $matches))
		{
			foreach(preg_split('/,\s*/', $matches[1]) as $alias)
			{
				$parts = explode('\\', $alias);
				$use[$alias] = array_pop($parts);
			}
		}
		$all_matches_raw = array();
		if (preg_match_all('/[=\s]new\s+([a-z0-9_\\\\]+)\s*\(/i', $line, $matches))
		{
			$all_matches_raw = $matches[1];
		}
		if (preg_match_all('/[\s,\(]([a-z0-9_\\\\]+)::/i', $line, $matches))
		{
			$all_matches_raw = array_merge($all_matches_raw, $matches[1]);
		}
		$all_matches = array_unique($all_matches_raw);
		foreach($all_matches as $c => $class)
		{
			$parts = explode('\\', $class);
			$first_part = array_shift($parts);
			if (in_array($class, $allways) || $class[0] == '\\' || in_array($first_part, $use))
			{
				unset($all_matches[$c]);
				continue;
			}
			if (file_exists(dirname($file).'/'.str_replace('\\', '/', $class).'.php'))
			{
				$use[$namespace.'\\'.$class] = $class;
				unset($all_matches[$c]);
				continue;
			}
		}
		if ($all_matches)
		{
			echo (1+$num).":\t".$line."\n";
			echo "--> ".implode(', ', $all_matches)."\n\n";
		}
	}
	//print_r($use);
	return true;
}

/**
 * Loop recursive through directory and call check_namespace for each php file
 *
 * @param string $dir
 * @return boolean false on error
 */
function check_namespace_recursive($dir)
{
	if (!is_dir($dir)) return false;

	foreach(scandir($dir) as $file)
	{
		if ($file == '.' || $file == '..') continue;

		if (is_dir($dir.'/'.$file))
		{
			check_namespace_recursive($dir.'/'.$file);
		}
		elseif(substr($file,-4) == '.php')
		{
			echo "\r".str_repeat(' ',100)."\r".$dir.'/'.$file.': ';
			check_namespace($dir.'/'.$file);
		}
	}
	echo "\r".str_repeat(' ',100)."\r";
	return true;
}

/**
 * Give usage
 *
 * @param string $error =null
 */
function usage($error=null)
{
	global $prog;
	echo "Usage: $prog [-h|--help] file or dir\n\n";
	if ($error) echo $error."\n\n";
	exit($error ? 1 : 0);
}

$args = $_SERVER['argv'];
$prog = basename(array_shift($args));

if (!$args) usage();

$replace_file = false;
while(($arg = array_shift($args)))
{
	switch($arg)
	{
		case '-h':
		case '--help':
			usage();
			break;

		default:
			if ($args)	// not last argument
			{
				usage("Unknown argument '$arg'!");
			}
			break 2;
	}
}

if (!file_exists($arg)) usage("Error: $arg not found!");

if (!is_dir($arg))
{
	check_namespace($arg,$replace_file);
}
else
{
	check_namespace_recursive($arg,$replace_file);
}