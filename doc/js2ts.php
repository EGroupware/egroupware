#!/usr/bin/env php
<?php
/**
 * EGroupware - modify our old JS inheritance to TypeScript
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright 2020 by Ralf Becker <rb@egroupware.org>
 */

if (PHP_SAPI !== 'cli')	// security precaution: forbit calling as web-page
{
	die('<h1>fix_api.php must NOT be called as web-page --> exiting !!!</h1>');
}

// raw replacements
$replace = array(
	'/^app.classes.([a-z0-9_]+)(\s*=\s*AppJS.extend)\(.*^}\);/misU' =>
		function($matches) {
			return strtr($matches[0], [
				'app.classes.'.$matches[1].$matches[2].'(' => 'class '.ucfirst($matches[1]).'App extends EgwApp',
				"\n});" => "\n}\n\napp.classes.$matches[1] = ".ucfirst($matches[1])."App;"
			]);
		},
	"/^\tappname:\s*'([^']+)',/m" => "\treadonly appname = '$1';",
	"/^\t([^: ,;(\t]+?):\s*([^()]+?),/m" => "\t\$1 : any = $2;",
	"/^\t([^:\n]+):\s*function\s*\(.*this._super.(apply|call)\(/msU" =>
		function($matches) {
	        return str_replace('this._super',
				$matches[1] === 'init' ? 'super' : 'super.'.$matches[1], $matches[0]);
	    },
	"/^\t([^:\n]+):\s*function\s*\(/m" => function($matches) {
		return "\t".($matches[1] === 'init' ? 'constructor' : $matches[1]).'(';
	},
    // TS does not like to call parent constructor with super.apply(this, arguments) and we dont have arguments ...
    '/\tsuper.apply\(this, *arguments\)/' => "\tsuper()",
	"/^\t},$/m" => "\t}",
	'/^ \* @version \$Id[^$]*\$\n/m' => '',
	'#^ \* @link http://www.egroupware.org#m' => ' * @link: https://www.egroupware.org',
);

/**
 * Add boilerplate for app.js files after header
 *
 * @param $app
 * @param $content
 * @return string
 */
function app_js_add($app, $content)
{
	return preg_replace('#^(/\*\*.*\n\ \*/)#Us', <<<EOF
$1

/*egw:uses
	/api/js/jsapi/egw_app.js
 */

import 'jquery';
import 'jqueryui';
import '../jsapi/egw_global';
import '../etemplate/et2_types';

import { EgwApp } from '../../api/js/jsapi/egw_app';
EOF
		, $content);
}

/**
 * Convert JavaScript to TypeScript
 *
 * @param string $file filename
 * @param boolean $dry_run =false true: only echo fixed file, not fix it
 * @return boolean false on error
 */
function convert($file, $dry_run=false)
{
	global $prog, $replace;
	if (basename($file) == $prog) return true;	// dont fix ourself ;-)

	if (($content = $content_in = file_get_contents($file)) === false) return false;

	$replace_callbacks = array_filter($replace, 'is_callable');
	$replace_strings = array_filter($replace, 'is_string');

	$content = preg_replace(array_keys($replace_strings), array_values($replace_strings),
		preg_replace_callback_array($replace_callbacks, $content));

	// add app.js spezific boilerplate
	if (preg_match('#/([^/]+)/js/app(\.old)?\.js$#', realpath($file), $matches))
	{
		$content = app_js_add($matches[1], $content);
	}

	if ($content == $content_in) return true;	// nothing changed

	if ($dry_run)
	{
		echo $content;
	}
	else
	{
		$ret = file_put_contents($new_file=preg_replace('/\.js$/', '.ts', $file), $content) === false ? -1 : 0;
		//system('/usr/bin/php -l '.$file.'.new', $ret);
		system('/usr/bin/diff -u '.$file.' '.$new_file);
		return !$ret;
	}

	return true;
}

/**
 * Loop recursive through directory and call fix_api for each php file
 *
 * @param string $dir
 * @param boolean $dry_run =false true: only echo fixed file, not fix it
 * @return boolean false on error
 */
function convert_recursive($dir, $dry_run=false)
{
	if (!is_dir($dir)) return false;

	foreach(scandir($dir) as $file)
	{
		if ($file == '.' || $file == '..') continue;

		if (is_dir($dir.'/'.$file))
		{
			convert_recursive($dir.'/'.$file, $dry_run);
		}
		elseif(substr($file,-3) == '.js')
		{
			echo "\r".str_repeat(' ',100)."\r".$dir.'/'.$file.': ';
			convert($dir.'/'.$file, $dry_run);
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
	echo "Usage: $prog [-h|--help] [-d|--dry-run] file(s) or dir(s)\n\n";
	if ($error) echo $error."\n\n";
	exit($error ? 1 : 0);
}

$args = $_SERVER['argv'];
$prog = basename(array_shift($args));

if (!$args) usage();

$dry_run = false;
while(($arg = array_shift($args)) && $arg[0] == '-')
{
	switch($arg)
	{
		case '-h':
		case '--help':
			usage();
			break;

		case '-d':
		case '--dry-run':
			$dry_run = true;
			break;

		default:
			if ($args)	// not last argument
			{
				usage("Unknown argument '$arg'!");
			}
			break 2;
	}
}

do {
	if (!file_exists($arg)) usage("Error: $arg not found!");

	if (!is_dir($arg))
	{
		convert($arg, $dry_run);
	}
	else
	{
		convert_recursive($arg, $dry_run);
	}
}
while(($arg = array_shift($args)));
