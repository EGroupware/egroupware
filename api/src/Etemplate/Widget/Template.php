<?php
/**
 * EGroupware - eTemplate serverside template widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-16 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api\Etemplate;
use EGroupware\Api;
use XMLReader;

/* allow to call direct for tests (see end of class)
if (!isset($GLOBALS['egw_info']))
{
	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'currentapp' => 'login',
			'debug' => 'etemplate_widget_template',
		)
	);
	include_once '../../header.inc.php';
} */

/**
 * eTemplate widget baseclass
 */
class Template extends Etemplate\Widget
{
	/**
	 * Cache of already read templates
	 *
	 * @var array with name => template pairs
	 */
	protected static $cache = array();

	/**
	 * Path of template relative to EGW_SERVER_ROOT
	 *
	 * @var string
	 */
	public $rel_path;

	/**
	 * Get instance of template specified by name, template(-set) and version
	 *
	 * @param string $_name
	 * @param string $template_set =null default try template-set from user and if not found "default"
	 * @param string $version =''
	 * @param string $load_via ='' use given template to load $name
	 * @return Template|boolean false if not found
	 */
	public static function instance($_name, $template_set=null, $version='', $load_via='')
	{
		if (Api\Header\UserAgent::mobile())
		{
			$template_set = "mobile";
		}

		//$start = microtime(true);
		list($name) = explode('?', $_name);	// remove optional cache-buster
		if (isset(self::$cache[$name]) || !($path = self::relPath($name, $template_set, $version, $load_via)))
		{
			if ((empty($path) || self::read($load_via, $template_set)) && isset(self::$cache[$name]))
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
				if ($expand_name && $expand_name != $name &&
					($template = self::instance($expand_name, $template_set, $version, $load_via)))
				{
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
				$template = new Template($reader);
				$template->rel_path = $path;
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
	 * @param string $load_via =''
	 * @return string path of template xml file or null if not found
	 */
	public static function relPath($name, $template_set=null, $version='', $load_via='')
	{
		static $prefixes = null;
		unset($version);	// not used currently
		list($app, $rest) = explode('.', $load_via ?: $name, 2)+[null,null];

		if (empty($template_set))
		{
			$template_set = $GLOBALS['egw_info']['user']['preferences']['common']['template_set'];
		}
		$template_path = '/'.$app.'/templates/'.$template_set.'/'.$rest.'.xet';
		$default_path = '/'.$app.'/templates/default/'.$rest.'.xet';

		// check if /etemplates is mounted in VFS and prefer it in that case over phy. file-system
		if (!isset($prefixes))
		{
			$prefixes = array(EGW_SERVER_ROOT);
			$fs_tab = Api\Vfs::mount();
			if (isset($fs_tab[self::VFS_TEMPLATE_PATH]))
			{
				array_unshift($prefixes, Api\Vfs::PREFIX.self::VFS_TEMPLATE_PATH);
			}
		}
		foreach($prefixes as $prefix)
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
		return $path ?? null;
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
				$url = Api\Vfs::download_url($path);

				if ($url[0] == '/') $url = Api\Framework::link($url);

				// mtime postfix has to use '?download=', as our WebDAV treats everything else literal and not ignore them like Apache for static files!
				$url .= '?download='.filemtime($path);
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
	 * @param string|callable $method_name or function($cname, $expand, $widget)
	 * @param array $params =array('') parameter(s) first parameter has to be cname, second $expand!
	 * @param boolean $respect_disabled =false false (default): ignore disabled, true: method is NOT run for disabled widgets AND their children
	 */
	public function run($method_name, $params=array(''), $respect_disabled=false)
	{
		$cname =& $params[0];
		$old_cname = $params[0];
		if (!empty($this->attrs['content'])) $cname = self::form_name($cname, $this->attrs['content'], $params[1]);

		// Check for template from content, and run over it
		// templates included via template tag have their name to load them from in attribute "template"
		$expand_name = self::expand_name($this->id ?: $this->attrs['template'], '','','','',self::$request->content);
		if(!$expand_name && $this->id && $this->attrs['template'])
		{
			$expand_name = $this->attrs['template'];
		}
		if (!empty($this->original_name))
		{
			$expand_name = self::expand_name($this->original_name, '','','','',self::$request->content);
		}
		//error_log("$this running $method_name() cname: {$this->id} -> expand_name: $expand_name");
		if($expand_name && $expand_name != $this->id)
		{
			if (($row_template = self::instance($expand_name)))
			{
				$row_template->run($method_name, $params, $respect_disabled);
			}
		}
		else
		{
			parent::run($method_name, $params, $respect_disabled);
		}
		$params[0] = $old_cname;
	}

	/**
	 * Fill type options in self::$request->sel_options to be used on the client
	 *
	 * @param string $cname
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 */
	public function beforeSendToClient($cname, array $expand=null)
	{
		//error_log(__METHOD__."('$cname') this->id=$this->id, this->type=$this->type, this->attrs=".array2string($this->attrs));
		$form_name = self::form_name($cname, $this->id, $expand);

		self::setElementAttribute($form_name, 'url', self::rel2url($this->rel_path));
	}
}

/*
if ($GLOBALS['egw_info']['flags']['debug'] == 'etemplate_widget_template')
{
	$name = isset($_GET['name']) ? $_GET['name'] : 'timesheet.edit';
	if (!($template = Template::instance($name)))
	{
		header('HTTP-Status: 404 Not Found');
		echo "<html><head><title>Not Found</title><body><h1>Not Found</h1><p>The requested eTemplate '$name' was not found!</p></body></html>\n";
		exit;
	}
	header('Content-Type: text/xml');
	echo $template->toXml();
}
*/