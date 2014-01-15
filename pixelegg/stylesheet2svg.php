#!/usr/bin/php
<?php
/**
 * helper to add a stylesheet to a svg image
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2014 by Ralf Becker <rb@stylite.de>
 * @version $Id$
 */

if (php_sapi_name() !== 'cli') die("This is a commandline ONLY tool!\n");

$args = $_SERVER['argv'];
$prog = array_shift($args);

if ($_SERVER['argc'] <= 1 || $prog != 'pixelegg/stylesheet2svg.php') die("
Usage: pixelegg/stylesheet2svg [-s stylesheet] svg-image(s)
Add an external stylesheet to an svg image and sets id of svg tag to app_image
Examples:
- pixelegg/stylesheet2svg -s pixelegg/less/svg.css */templates/pixelegg/images/*.svg pixelegg/images/*.svg
\n");

$stylesheet = 'pixelegg/less/svg.css';
if ($args[0] == '-s')
{
	$stylesheet = $args[1];
	array_shift($args);
	array_shift($args);
}

foreach($args as $path)
{
	if (!preg_match('|^([^/]+)/.*/(.*).svg$|', $path, $matches) || !($svg = file_get_contents($path)))
	{
		error_log("SVG image $path NOT found or empty!\n");
		continue;
	}
	// remove evtl. existing old stylesheet
	$svg = preg_replace("|<\\?xml-stylesheet[^?]+\\?>\n?|", '', $svg);
	// add stylesheet
	$style_url = rel_path($stylesheet, $path);
	$svg = preg_replace('|<svg|', '<?xml-stylesheet type="text/css" href="'.$style_url.'" ?>'."\n".'<svg', $svg);
	// change id to app_image
	$id = $matches[1].'_'.$matches[2];
	$svg = preg_replace('/(<svg.*) id="[^"]+"/', '\\1 id="'.$id.'"', $svg);
	// store image again
	file_put_contents($path, $svg);
	echo "$path: added $style_url and id=\"$id\"\n";
}

function rel_path($stylesheet, $image)
{
	$i_parts = explode('/', $image);
	array_pop($i_parts);
	$rel_parts = $s_parts = explode('/', $stylesheet);

	foreach($i_parts as $n => $i_part)
	{
		if (isset($s_parts[$n]) && $s_parts[$n] === $i_part)
		{
			array_shift($rel_parts);
		}
		else
		{
			array_unshift($rel_parts, '..');
		}
	}
	$rel_path = implode('/', $rel_parts);
	//error_log(__FUNCTION__."($stylesheet, $image) returned $rel_path");
	return $rel_path;
}