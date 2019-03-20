#!/usr/bin/env php
<?php
/**
 * helper to update EGroupware Gruntfile.js
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright (c) 2016-18 by Ralf Becker <rb@egroupware.org>
 */

use EGroupware\Api\Framework;
use EGroupware\Api\Framework\Bundle;

if (php_sapi_name() !== 'cli') die("This is a commandline ONLY tool!\n");

// force a domain for MServer install
$_REQUEST['domain'] = 'boulder.egroupware.org';
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

$uglify =& $config['uglify'];

// some files are not in a bundle, because loaded otherwise or are big enough themselfs
$exclude = array(
	// api/js/jsapi/egw.js loaded via own tag, and we must not load it twice!
	'api/js/jsapi/egw.js',
	// TinyMCE is loaded separate before the bundle
	'vendor/tinymce/tinymce/tinymce.min.js',
);

foreach(Bundle::all() as $name => $files)
{
	if ($name == '.ts') continue;	// ignore timestamp

	// remove leading / from file-names
	array_walk($files, function(&$path)
	{
		if ($path[0] == '/') $path = substr($path, 1);
	});

	// some files are not in a bundle, because they are big enough themselfs
	foreach($exclude as $file)
	{
		if (($key = array_search($file, $files))) unset($files[$key]);
	}

	//var_dump($name, $files);
	if (isset($uglify[$name]))
	{
		list($target) = each($uglify[$name]['files']);
		$uglify[$name]['files'][$target] = array_values($files);
	}
	elseif (isset($uglify[$append = substr($name, 0, -1)]))
	{
		reset($uglify[$append]['files']);
		list($target) = each($uglify[$append]['files']);
		$uglify[$append]['files'][$target] = array_merge($uglify[$append]['files'][$target], array_values($files));
	}
	else	// create new bundle using last file as target
	{
		$target = str_replace('.js', '.min.js', end($files));
		$uglify[$name]['files'][$target] = array_values($files);
	}
}

// add css for all templates and themes
$cssmin =& $config['cssmin'];
$GLOBALS['egw_info']['flags']['currentapp'] = '*grunt*';	// to not find any app.css files
$GLOBALS['egw_info']['server']['debug_minify'] = 'True';	// otherwise we would only get minified file
foreach(array('pixelegg','jdots')/*array_keys(Framework::list_templates())*/ as $template)
{
	$GLOBALS['egw_info']['server']['template_set'] = $template;
	$tpl = Framework::factory();
	$themes = $tpl->list_themes();
	if ($template == 'pixelegg') $themes[] = 'fw_mobile';	// this is for mobile devices
	foreach(array_keys($themes) as $theme)
	{
		// skip not working cssmin of pixelegg/traditional: Broken @import declaration of "../../etemplate/templates/default/etemplate2.css"
		if ($template == 'pixelegg' && $theme == 'traditional') continue;
		$GLOBALS['egw_info']['user']['preferences']['common']['theme'] = $theme;
		// empty include list by not-existing file plus last true
		Framework\CssIncludes::add('*grunt*', null, true, true);
		$tpl->_get_css();
		$dest = substr($tpl->template_dir, 1).($theme == 'fw_mobile' ? '/mobile/' : '/css/').$theme.'.min.css';
		$cssmin[$template]['files'][$dest] =
			// remove leading slash from src path
			array_map(function($path)
			{
				return substr($path, 1);
			},
			// filter out all dynamic css, like categories.php
			array_values(array_filter(Framework\CssIncludes::get(true), function($path)
			{
				return strpos($path, '.php?') === false;
			})));
	}
}

$new_json = str_replace("\n", "\n\t",
	preg_replace_callback('/^( *)/m', function($matches)
	{
		return str_repeat("\t", strlen($matches[1])/4);
	}, json_encode($config, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)));

$new_content = preg_replace('/^(\s*)"([a-z0-9]+)":/mi', '$1$2:', $new_json);
//die($new_content."\n");

rename($gruntfile, $gruntfile.'.old');
file_put_contents($gruntfile, str_replace($matches[1], $new_content, $content));
