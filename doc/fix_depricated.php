#!/usr/bin/php -qC
<?php
/**
 * EGroupware - fix deprecated PHP functions and constructs
 *
 * The depricated warnings fill up the log files, as they can not be swichted off in the logs.
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author RalfBecker@outdoor-training.de
 * @copyright 2009-19 by RalfBecker@outdoor-training.de
 */

if (php_sapi_name() !== 'cli')	// security precaution: forbit calling as web-page
{
	die('<h1>fix_depricated.php must NOT be called as web-page --> exiting !!!</h1>');
}

/**
 * Fix depricated stuff in a given file
 *
 * @param string $file filename
 * @param boolean $replace_file =false replace existing file if modifications are necessary, otherwise .php53 file is created
 * @return boolean false on error
 */
function fix_depricated($file,$replace_file=false)
{
	$orig = $lines = file_get_contents($file);
	if ($lines === false) return false;
	global $prog;
	if (basename($file) == $prog) return true;	// dont fix ourself ;-)

	// match "variables" like: $var, $obj->attr, $arr['key']
	$variable = '\$[a-z_0-9\[\]\'>-]+';

	// list($key) = each($array); --> $key = key($array);
	if (preg_match("/each\(($variable)\);/i", $lines))
	{
		$lines = preg_replace("/list\(($variable)\)\s+=\s+@?each\(($variable)\);/i", '$1 = key($2);', $lines);

		// list($key, $val) = each($array); --> $key = key($array); $val = current($array);
		if (preg_match("/[^=]+=\s+@?each\(($variable)\);/i", $lines))
		{
			$lines = preg_replace("/^(\s)*list\(($variable),\s*($variable)\)\s+=\s+@?each\(($variable)\);/mi",
				'$1$2 = key($4);$1$3 = current($4);', $lines);

			$matches = null;
			if (preg_match_all("/^[^=]+=\s+@?each\(($variable)\);/i", $lines, $matches, PREG_PATTERN_ORDER))
			{
				error_log("Unfixed each(...) constructs: ", implode(' ', $matches[0]));
			}
		}
	}

	// count($non_array) --> is_array($non_array) && count($non_array)
	/* commented as (in most cases) unnecessary, if argument is garantied to be an array
	if (preg_match('/count\((\$[a-z_0-9]+)\)/i', $lines))
	{
		$lines = preg_replace('/count\((\$[a-z_0-9]+)\)/i', 'is_array($1) && $0', $lines);
	}*/

	// PHP Deprecated:  Assigning the return value of new by reference is deprecated
	if (preg_match('/= *& *new /m',$lines))
	{
		$lines = preg_replace('/= *& *new /','= new ',$lines);
	}
	// PHP Deprecated:  Function split() is deprecated
	$matches = null;
	if (preg_match_all('/[= \t(]+spliti? *\\(("[^"]*"|\'[^\']*\'),/m',$lines,$matches))
	{
		$replace = array();
		//print_r($matches);
		foreach($matches[1] as $key => $pattern)
		{
			$full_pattern = $matches[0][$key];
			// single char --> just explode
			if (strlen($pattern) == 3 || strlen($pattern) == 4 && substr($pattern,0,2) == '"\\')
			{
				$replace[$full_pattern] = str_replace('split','explode',$full_pattern);
			}
			else
			{
				$preg_pattern = $pattern[0].'/'.str_replace('/','\\\\/',substr($pattern,1,-1)).'/'.$pattern[0];
				if (strpos($full_pattern,'spliti')) $preg_pattern = substr($preg_pattern,0,-1).'i'.$pattern[0];
				$replace[$full_pattern] = str_replace(array('spliti','split',$pattern),array('preg_split','preg_split',$preg_pattern),$full_pattern);
			}
		}
		//print_r($replace);
		$lines = strtr($lines,$replace);
	}
	// PHP Deprecated:  Function ereg() is deprecated
	if (preg_match_all('/!?eregi? *\\(("[^"]+"[^,]*|\'[^\']+\'[^,]*), *(\$[A-Za-z0-9_\[\]\$\'\"]+)(, *\$[A-Za-z0-9_\[\]\$\'\"]+)?\)([ )&|]+)/m',$lines,$matches))
	{
		$replace = array();
		//print_r($matches);
		foreach($matches[1] as $key => $pattern)
		{
			$full_pattern = $matches[0][$key];
			$what = $matches[2][$key];

			// simple existence check --> use strpos()
			if (preg_quote($pattern) == $pattern)
			{

				$replace[$full_pattern] = (strpos($full_pattern,'eregi')!==false?'strposi':'strpos').'('.$what.','.$pattern.
					') '.($full_pattern[0]=='!'?'===':'!==').' false'.$matches[4][$key];
			}
			else
			{
				// full ereg regular expression --> preg_match
				$preg_pattern = "'/'.".str_replace('/','\\\\/',$pattern).(strpos($full_pattern,'eregi') !== false ? ".'/i'" : ".'/'");
				$replace[$full_pattern] = str_replace(array('eregi','ereg',$pattern),array('preg_match','preg_match',$preg_pattern),$full_pattern);
			}
		}
		//print_r($replace);
		$lines = strtr($lines,$replace);
	}
	// PHP Deprecated:  Function ereg_replace() is deprecated
	if (preg_match_all('/eregi?_replace *\\((".+"|\'.+\'|[^,]+), *(.+), *[\'s$].+\)[,; =]/m',$lines,$matches))
	{
		$replace = array();
		//print_r($matches);
		foreach($matches[1] as $key => $pattern)
		{
			$full_pattern = $matches[0][$key];

			// simple replace --> use str_replace()
			if (preg_quote($pattern) == $pattern)
			{
				$replace[$full_pattern] = str_replace(array('eregi_replace','ereg_replace'),array('stri_replace','str_replace'),$full_pattern);
			}
			else
			{
				// full ereg regular expression --> preg_replace
				$preg_pattern = "'/'.".str_replace('/','\\\\/',$pattern).(strpos($full_pattern,'eregi') !== false ? ".'/i'" : ".'/'");
				$replace[$full_pattern] = str_replace(array('eregi_replace','ereg_replace',$pattern),
					array('preg_replace','preg_replace',$preg_pattern),$full_pattern);
			}
		}
		//print_r($replace);
		$lines = strtr($lines,$replace);
	}
	// remove extra '/' from regular expressions
	$lines = str_replace(array("'/'.'","'.'/'","'.'/i'"),array("'/","/'","/i'"),$lines);

	// fix call to not longer existing PDO method $result->fetchSingle()
	$lines = str_replace('->fetchSingle(','->fetchColumn(',$lines);

	// fix calls to deprecated call_user_method(_array)?(method,object[,args])
	if (preg_match('/call_user_method(_array)?\(/',$lines,$matches))
	{
		$lines = preg_replace('/call_user_method\(([^,]+),([^,\)]+)([,)])/','call_user_func(array(\\2,\\1)\\3',$lines);
		$lines = preg_replace('/call_user_method_array\(([^,]+),([^,\)]+)([,)])/','call_user_func_array(array(\\2,\\1)\\3',$lines);
	}

	if ($lines != $orig)
	{
		file_put_contents($file.'.new',$lines);
		$ret = null;
		system('/usr/bin/php -l '.$file.'.new',$ret);
		system('/usr/bin/diff -u '.$file.' '.$file.'.new');
		if (!$ret && $replace_file)
		{
			unlink($file);
			rename($file.'.new',$file);
		}
		return !$ret;
	}
	return true;
}

/**
 * Loop recursive through directory and call fix_depricated for each php file
 *
 * @param string $dir
 * @param boolean $replace_file =false replace existing file if modifications are necessary, otherwise .php53 file is created
 * @return boolean false on error
 */
function fix_depricated_recursive($dir,$replace_file=false)
{
	if (!is_dir($dir)) return false;

	foreach(scandir($dir) as $file)
	{
		if ($file == '.' || $file == '..') continue;

		if (is_dir($dir.'/'.$file))
		{
			fix_depricated_recursive($dir.'/'.$file,$replace_file);
		}
		elseif(substr($file,-4) == '.php')
		{
			echo "\r".str_repeat(' ',100)."\r".$dir.'/'.$file.': ';
			fix_depricated($dir.'/'.$file,$replace_file);
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
	echo "Usage: $prog [--replace] [-h|--help] file or dir\n\n";
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

		case '--replace':
			$replace_file = true;
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
	fix_depricated($arg,$replace_file);
}
else
{
	fix_depricated_recursive($arg,$replace_file);
}