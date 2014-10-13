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

// allow to call direct for tests (see end of class)
if (!isset($GLOBALS['egw_info']))
{
	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'currentapp' => 'login',
			'debug' => 'etemplate_widget_template',
		)
	);
	include_once '../../header.inc.php';
}

/**
 * eTemplate widget baseclass
 */
class etemplate_widget_template extends etemplate_widget
{
	/**
	 * Cache of already read templates
	 *
	 * @var array with name => template pairs
	 */
	protected static $cache = array();

	/**
	 * Get instance of template specified by name, template(-set) and version
	 *
	 * @param string $_name
	 * @param string $template_set =null default try template-set from user and if not found "default"
	 * @param string $version =''
	 * @param string $load_via ='' use given template to load $name
	 * @todo Reading customized templates from database
	 * @return etemplate_widget_template|boolean false if not found
	 */
	public static function instance($_name, $template_set=null, $version='', $load_via='')
	{
		//$start = microtime(true);
		list($name) = explode('?', $_name);	// remove optional cache-buster
		if (isset(self::$cache[$name]) || !($path = self::relPath($name, $template_set, $version)))
		{
			if ((!$path || self::read($load_via, $template_set)) && isset(self::$cache[$name]))
			{
				//error_log(__METHOD__."('$name', '$template_set', '$version', '$load_via') read from cache");
				return self::$cache[$name];
			}
			// Template not found, try again as if $name were a partial name
			else if(!$path && strpos($name,'.') === false)
			{
				foreach(self::$cache as $c_name => $c_template)
				{
					list(,, $c_sub) = explode('.',$c_name, 3);
					if($name == $c_sub)
					{
						//error_log(__METHOD__ . "('$name' loaded from cache ($c_name)");
						return $c_template;
					}

					$parts = explode('.',$c_name);
					if($name == $parts[count($parts)-1]) return $c_template;
				}
			}
			// Template not found, try again with content expansion
			if (is_array(self::$request->content))
			{
				$expand_name = self::expand_name($name, '','','','',self::$cont);
				if($expand_name && $expand_name != $name)
				{
					$template = self::instance($expand_name, $template_set, $version, $load_via);
					// Remember original, un-expanded name in case content changes while still cached
					$template->original_name = $name;
					return $template;
				}
			}

			error_log(__METHOD__."('$name', '$template_set', '$version', '$load_via') template NOT found!");
			return false;
		}
		$reader = new XMLReader();
		if (!$reader->open(self::rel2path($path))) return false;

		while($reader->read())
		{
			if ($reader->nodeType == XMLReader::ELEMENT && $reader->name == 'template')
			{
				$template = new etemplate_widget_template($reader);
				//echo $template->id; _debug_array($template);

				self::$cache[$template->id] = $template;

				if ($template->id == $name)
				{
					//error_log(__METHOD__."('$name', '$template_set', '$version', '$load_via') read in ".round(1000.0*(microtime(true)-$start),2)." ms");
					return $template;
				}
			}
		}

		// template not found in file, should never happen
		error_log(__METHOD__."('$name', '$template_set', '$version', '$load_via') template NOT found in file '$path'!");
		return false;
	}

	const VFS_TEMPLATE_PATH = '/etemplates';

	/**
	 * Get path/URL relative to EGroupware install of a template of full vfs url
	 *
	 * @param string $name
	 * @param string $template_set =null default try template-set from user and if not found "default"
	 * @param string $version =''
	 * @return string path of template xml file or null if not found
	 */
	public static function relPath($name, $template_set=null, $version='')
	{
		unset($version);	// not used currently
		list($app, $rest) = explode('.', $name, 2);

		if (empty($template_set))
		{
			$template_set = $GLOBALS['egw_info']['user']['preferences']['common']['template_set'];
		}
		$template_path = '/'.$app.'/templates/'.$template_set.'/'.$rest.'.xet';
		$default_path = '/'.$app.'/templates/default/'.$rest.'.xet';

		foreach(array(egw_vfs::PREFIX.self::VFS_TEMPLATE_PATH, EGW_SERVER_ROOT) as $prefix)
		{
			if (file_exists($prefix.$template_path))
			{
				$path = $template_path;
				break;
			}
			if (file_exists($prefix.$default_path))
			{
				$path = $default_path;
				break;
			}
		}
		// for a vfs template path we keep the prefix, to be able to distinquish between real filesystem and vfs
		if (isset($path) && $prefix !== EGW_SERVER_ROOT)
		{
			$path = $prefix.$path;
		}
		//error_log(__METHOD__."('$name', '$template_set') returning ".array2string($path));
		return $path;
	}

	/**
	 * Convert relative template path from relPath to an absolute path
	 *
	 * @param string $path
	 * @return string
	 */
	public static function rel2path($path)
	{
		if ($path[0] === '/')
		{
			$path = EGW_SERVER_ROOT.$path;
		}
		return $path;
	}

	/**
	 * Convert relative template path from relPath to an url incl. cache-buster modification time postfix
	 *
	 * @param string $path
	 * @return string url
	 */
	public static function rel2url($path)
	{
		if ($path)
		{
			if ($path[0] === '/')
			{
				$url = $GLOBALS['egw_info']['server']['webserver_url'].$path.'?'.filemtime(self::rel2path($path));
			}
			else
			{
				$url = egw_vfs::download_url($path);

				if ($url[0] == '/') $url = egw::link($url);

				// mtime postfix has to use '?download=', as our WebDAV treats everything else literal and not ignore them like Apache for static files!
				$url .= '?download='.filemtime(egw_vfs::PREFIX.$path);
			}
		}
		//error_log(__METHOD__."('$path') returning $url");
		return $url;
	}

	/**
	 * Run method on all children
	 *
	 * Reimplemented because templates can have an own namespace specified in attrs[content], NOT id!
	 *
	 * @param string $method_name
	 * @param array $params =array('') parameter(s) first parameter has to be cname, second $expand!
	 * @param boolean $respect_disabled =false false (default): ignore disabled, true: method is NOT run for disabled widgets AND their children
	 */
	public function run($method_name, $params=array(''), $respect_disabled=false)
	{
		$cname =& $params[0];
		$old_cname = $params[0];
		if ($this->attrs['content']) $cname = self::form_name($cname, $this->attrs['content'], $params[1]);

		// Check for template from content, and run over it
		$expand_name = self::expand_name($this->id, '','','','',self::$request->content);
		if($this->original_name)
		{
			$expand_name = self::expand_name($this->original_name, '','','','',self::$request->content);
		}
		//error_log("$this running $method_name() cname: {$this->id} -> expand_name: $expand_name");
		if($expand_name && $expand_name != $this->id)
		{
			$row_template = etemplate_widget_template::instance($expand_name);
			$row_template->run($method_name, $params, $respect_disabled);
		}
		else
		{
			parent::run($method_name, $params, $respect_disabled);
		}
		$params[0] = $old_cname;
	}
}

/*
if ($GLOBALS['egw_info']['flags']['debug'] == 'etemplate_widget_template')
{
	$name = isset($_GET['name']) ? $_GET['name'] : 'timesheet.edit';
	if (!($template = etemplate_widget_template::instance($name)))
	{
		header('HTTP-Status: 404 Not Found');
		echo "<html><head><title>Not Found</title><body><h1>Not Found</h1><p>The requested eTemplate '$name' was not found!</p></body></html>\n";
		exit;
	}
	header('Content-Type: text/xml');
	echo $template->toXml();
}
*/