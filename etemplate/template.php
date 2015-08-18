<?php

 /*
 * Egroupware
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

const CACHE_TIME = 3600;

//Set all necessary info and fire up egroupware
$GLOBALS['egw_info']['flags'] = array(
	'currentapp'	=>	'etemplate',
	'noheader'	=>	true,
	'nonavbar'	=>	true
);
include ('../header.inc.php');

if (!ajaxtoJSON($_GET['name']))
{
	header('404 Not found');
	http_response_code(404);
}

/**
* Gets the specified template XML file converted to JSON representation
*
* @param String $name
* @return JSON
*/
function ajaxtoJSON($name)
{
	if(!$name)
	{
		$name = get_var('name');
	}
	$filename = etemplate_widget_template::rel2path(etemplate_widget_template::relPath($name));
	// Bad template name
	if(trim($filename) == '')
	{
		return false;
	}
	error_log("Filename: $filename");

	$mtime = filemtime($filename);

	// First, check cache
	$cached = egw_cache::getInstance('etemplate', $name);

	// Not found, or modified
	if(!$cached || !is_array($cached) || is_array($cached) && $cached['mtime'] != $mtime)
	{
		// Load XML & parse into JSON
		$reader = simplexml_load_file($filename);
		$template = json_encode(nodeToArray($reader));
		$cached = array(
			'template' => $template,
			'mtime'	=> $mtime
		);
	}
	else if ($cached);
	{
		$template = $cached['template'];
	}
	if($cached)
	{
		// Keep in instance cache so we don't have to regenerate it
		egw_cache::setInstance('etemplate', $name, $cached, CACHE_TIME);
	}
	else
	{
		return false;
	}

	// Should set some headers so the browser can cache it too
	header('Cache-Control: public, max-age='.CACHE_TIME);
	header('Expires: ' . gmdate('D, d M Y H:i:s', time()+CACHE_TIME) . ' GMT');
	header('Content-type: application/json');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime));
	header('Content-Length: ' . mb_strlen($template));

	echo $template;
	return true;
}

function nodeToArray($xmlnode, &$jsnode = false)
{
	if(!$xmlnode) return;

	if(!($xmlnode instanceof SimpleXMLElement) && trim($xmlnode))
	{
		$jsnode['content'] = $xmlnode;
		return '';
	}
	$nodename = $xmlnode->getName();
	$node =& $jsnode ? $jsnode : array();
	$node['tag'] = strtolower($nodename);
	$node['attributes'] = array();

	if (count($xmlnode->attributes()) > 0)
	{
		$node["attributes"] = array();
		foreach($xmlnode->attributes() as $key => $value)
		{
			$node["attributes"][$key] = (string)$value;
		}
	}

	if(trim($xmlnode->__toString()) != '')
	{
		$node['content'] = $xmlnode->__toString();
	}

	// Load children
	$child_index = 0;
	foreach ($xmlnode->children() as $childxmlnode)
	{
		$node['children'][$child_index] = array('tag' => $childxmlnode->getName());
		nodeToArray($childxmlnode, $node['children'][$child_index++]);
	}
	return $node;
}
