#!/usr/bin/env php
<?php
/**
 * helper to update EGroupware Gruntfile.js
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Ralf Becker <rb@stylite.de>
 * @copyright (c) 2016 by Ralf Becker <rb@stylite.de>
 * @version $Id$
 */

if (php_sapi_name() !== 'cli') die("This is a commandline ONLY tool!\n");

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'login',
	)
);
include(__DIR__.'/header.inc.php');

$gruntfile = __DIR__.'/Gruntfile.js';
if (!($content = @file_get_contents($gruntfile)))
{
	die("\nFile '$gruntfile' not found!\n\n");
}

if (!preg_match('/grunt\.initConfig\(({.+})\);/s', $content, $matches) ||
	!($json = preg_replace('/^(\s*)([a-z0-9_-]+):/mi', '$1"$2":', $matches[1])) ||
	!($config = json_decode($json, true)))
{
	die("\nCan't parse $path!\n\n");
}
//print_r($config); exit;

$bundle2min = array(
	'api' => 'phpgwapi/js/jsapi.min.js',
	'et2' => 'etemplate/js/etemplate2.min.js',
);

$build_files =& $config['uglify']['build']['files'];

foreach(egw_framework::get_bundles() as $name => $files)
{
	if ($name == '.ts') continue;	// ignore timestamp

	// remove leading / from file-names
	array_walk($files, function(&$path)
	{
		if ($path[0] == '/') $path = substr($path, 1);
	});

	//var_dump($name, $files);
	if (isset($bundle2min[$name]))
	{
		$build_files[$bundle2min[$name]] = array_values($files);
	}
	elseif (isset($bundle2min[$append = substr($name, 0, -1)]))
	{
		$build_files[$bundle2min[$append]] = array_merge($build_files[$bundle2min[$append]], array_values($files));
	}
	else
	{
		error_log("Bundle $name ignored!\n");
	}
}

$new_json = str_replace("\n", "\n\t",
	preg_replace_callback('/^( *)/m', function($matches)
	{
		return str_repeat("\t", strlen($matches[1])/4);
	}, json_encode($config, JSON_PRETTY_PRINT)));

$new_content = preg_replace('/^(\s*)"([a-z0-9]+)":/mi', '$1$2:', $new_json);
//die($new_content."\n");

rename($gruntfile, $gruntfile.'.old');
file_put_contents($gruntfile, str_replace($matches[1], $new_content, $content));