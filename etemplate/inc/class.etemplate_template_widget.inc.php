<?php
/**
 * EGroupware - eTemplate serverside template widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-11 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

/* testwise to have autoloading
if (!isset($GLOBALS['egw_info']))
{
	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'currentapp' => 'login',
		),
	);
	include_once '../../header.inc.php';
}
*/
/**
 * eTemplate widget baseclass
 */
class etemplate_template_widget extends etemplate_widget
{
	/**
	 * Cache of already read templates
	 *
	 * @var array with name => template pairs
	 */
	protected static $cache = array();

	/**
	 * Read a templates specified by name, template(-set) and version
	 *
	 * @param string $name
	 * @param string $template_set='default'
	 * @param string $version=''
	 * @param string $load_via='' use given template to load $name
	 * @todo Reading customized templates from database
	 * @return etemplate_template_widget|boolean false if not found
	 */
	public static function read($name, $template_set='default', $version='', $load_via='')
	{
		$start = microtime(true);
		if (isset(self::$cache[$name]) || !($path = self::relPath($name, $template_set, $version)))
		{
			if ((!$path || self::read($load_via, $template_set)) && isset(self::$cache[$name]))
			{
				error_log(__METHOD__."('$name', '$template_set', '$version', '$load_via') read from cache");
				return self::$cache[$name];
			}
			error_log(__METHOD__."('$name', '$template_set', '$version', '$load_via') template NOT found!");
			return false;
		}
		$reader = new XMLReader();
		if (!$reader->open(EGW_SERVER_ROOT.$path)) return false;

		while($reader->read())
		{
			if ($reader->nodeType == XMLReader::ELEMENT && $reader->name == 'template')
			{
				$template = new etemplate_template_widget($reader);
				//echo $template->id; _debug_array($template);

				self::$cache[$template->id] = $template;

				if ($template->id == $name)
				{
					error_log(__METHOD__."('$name', '$template_set', '$version', '$load_via') read in ".round(1000.0*(microtime(true)-$start),2)." ms");
					return $template;
				}
			}
		}
		// template not found in file, should never happen
		error_log(__METHOD__."('$name', '$template_set', '$version', '$load_via') template NOT found in file '$path'!");
		return false;
	}

	/**
	 * Get path/URL relative to EGroupware install of a template
	 *
	 * @param string $name
	 * @param string $template_set='default'
	 * @param string $version=''
	 * @return string|boolean path of template xml file or false if not found
	 */
	public static function relPath($name, $template_set='default', $version='')
	{
		list($app, $rest) = explode('.', $name, 2);
		$path = '/'.$app.'/templates/'.$template_set.'/'.$rest.'.xet';

		if (file_exists(EGW_SERVER_ROOT.$path)) return $path;

		if ($templateSet != 'default')
		{
			$path = '/'.$app.'/templates/default/'.$rest.'.xet';

			if (file_exists(EGW_SERVER_ROOT.$path)) return $path;
		}
		return false;
	}
}
/*
header('Content-Type: text/xml');
$template = etemplate_template_widget::read('timesheet.edit');
$template->toXml();
*/
