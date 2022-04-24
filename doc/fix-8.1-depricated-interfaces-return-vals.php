#!/usr/bin/php -qC
<?php
/**
 * EGroupware - fix deprecated PHP functions and constructs
 *
 * The deprecated warnings fill up the log files, as they can not be switched off in the logs.
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author RalfBecker@outdoor-training.de
 * @copyright 2022 by rb@egroupware.org
 */

if (php_sapi_name() !== 'cli')	// security precaution: forbid calling as web-page
{
	die('<h1>fix_depricated.php must NOT be called as web-page --> exiting !!!</h1>');
}

/**
 * Interfaces which methods require return types to avoid deprecated warnings
 *
 * As 'mixed' return type is only available since PHP 8.0, we need to add '#[\ReturnTypeWillChange]' annotation instead,
 * for EGroupware versions not requiring PHP 8.0+ / 21.1.
 */
$interfaces = [
	'Iterator' => [
		'current' => 'mixed',
		'key' => 'mixed',
		'next' => 'void',
		'rewind' => 'void',
		'valid' => 'bool',
	],
	'IteratorAggregate' => [
		'getIterator' => '\Traversable',
	],
	'ArrayAccess' => [
		'offsetExists' => 'bool',
		'offsetGet' => 'mixed',
		'offsetSet' => 'void',
		'offsetUnset' => 'void',
	],
	'Serializable' => [
		'serialize' => '?string',
		'unserialize' => 'void',
	],
	'JsonSerializable' => [
		'jsonSerialize' => 'mixed',
	],
];

/**
 * Fix deprecated stuff in a given file
 *
 * @param string $file filename
 * @param boolean $replace_file =false replace existing file if modifications are necessary, otherwise .php53 file is created
 * @return boolean false on error
 */
function fix_depricated($file,$replace_file=false)
{
    global $interfaces;
	$orig = $lines = file_get_contents($file);
	if ($lines === false) return false;
	global $prog;
	if (basename($file) == $prog) return true;	// dont fix ourself ;-)

	// match "variables" like: $var, $obj->attr, $arr['key']
	$variable = '\$[a-z_0-9\[\]\'>-]+';

    foreach($interfaces as $interface => $methods)
    {
        // class Account implements \ArrayAccess
        if (!preg_match('/class\\s+([a-z_0-9]+)\s+implements\s+(\\\\?[a-z_0-9]+\s*,\s*)*\\\\?'.preg_quote($interface, '/').'/i', $lines, $matches))
        {
            //error_log("$file does NOT implement $interface: ".json_encode($matches));
            continue;
        }
	    error_log("$file DOES implement $interface: ".json_encode($matches));
	    //$phpdoc = "(\s*\\/\\*\\*.*\\*\\/\n)?";
        $phpdoc = '';
        $lines = preg_replace_callback("/^(\\s*((static|public|private|protected)\\s+)*function\\s+([a-z_0-9]+)\\s*)\\(\\s*([^)]*)\\s*\\)(\\s*:\\s*([a-z]+))?/msi",
            static function(array $matches) use ($methods, $lines)
            {
	            global $mixed_annotation;
                if (isset($matches[6]) || !isset($methods[$matches[4]]))
                {
                    //error_log($matches[0].' already fixed --> nothing to do');
                    return $matches[0];
                }
                //error_log(json_encode($matches)." --> need fixing");
                if ($methods[$matches[4]] !== 'mixed')
                {
                    return $matches[0].': '.$methods[$matches[4]];
                }
	            //$phpdoc = "\s*\\/\\*\\*.*?(@return\s+([a-z]+)).*?\\*\\/\n";
	            $phpdoc = "\s+\\*\s+@return\s+([a-z]+).*\n\s+\\*\\/\n";
                if (preg_match('/^'.$phpdoc.preg_quote($matches[0], '/').'/mi', $lines, $phpdoc_matches) &&
                    $phpdoc_matches[1] !== 'mixed')
                {
                    switch($type = $phpdoc_matches[1])
                    {
                        case 'boolean': $type = 'bool'; break;
                        case 'integer': $type = 'int'; break;
                        case 'double':  $type = 'float'; break;
                    }
	                return $matches[0].': '.$type;
                }
	            if (!$mixed_annotation)
	            {
		            return $matches[0].': '.$methods[$matches[4]];
	            }
	            return "\t#[\\ReturnTypeWillChange]\n".$matches[0];
            }, $lines);
    }

	if ($lines !== $orig)
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
	echo "Usage: $prog [--replace] [--mixed-annotation] [-h|--help] file or dir\n\n";
	if ($error) echo $error."\n\n";
	exit($error ? 1 : 0);
}

$args = $_SERVER['argv'];
$prog = basename(array_shift($args));

if (!$args) usage();

$replace_file = false;
$mixed_annotation = false;
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

		case '--mixed-annotation':
			$mixed_annotation = true;
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