<?php
/**
 * EGroupware API - CSS Includes
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de> rewrite in 12/2006
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage framework
 * @access public
 * @version $Id$
 */

namespace EGroupware\Api\Framework;

/**
 * CSS includes
 */
class CssIncludes
{
	/**
	 * Content from add calls
	 *
	 * @var array
	 */
	protected static $files = array();

	/**
	 * Include a css file, either speicified by it's path (relative to EGW_SERVER_ROOT) or appname and css file name
	 *
	 * @param string $app path (relative to EGW_SERVER_ROOT) or appname (if !is_null($name))
	 * @param string $name =null name of css file in $app/templates/{default|$this->template}/$name.css
	 * @param boolean $append =true true append file, false prepend (add as first) file used eg. for template itself
	 * @param boolean $clear_includes =false true: clear all previous includes
	 * @return boolean false: css file not found, true: file found
	 */
	public static function add($app, $name=null, $append=true, $clear_includes=false)
	{
		if ($clear_includes)
		{
			self::$files = array();
		}

		if (!is_null($name))
		{
			foreach($GLOBALS['egw']->framework->template_dirs as $dir)
			{
				if (file_exists(EGW_SERVER_ROOT.($path = '/'.$app.'/templates/'.$dir.'/'.$name.'.css')))
				{
					break;
				}
			}
		}
		else
		{
			$path = $app;
		}
		if (!file_exists(EGW_SERVER_ROOT.$path) && !file_exists(EGW_SERVER_ROOT . parse_url($path,PHP_URL_PATH)))
		{
			//error_log(__METHOD__."($app,$name) $path NOT found!");
			return false;
		}
		if (!in_array($path,self::$files))
		{
			if ($append)
			{
				self::$files[] = $path;
			}
			else
			{
				self::$files = array_merge(array($path), self::$files);
			}
		}
		return true;
	}

	/**
	 * Get all css files included with add
	 *
	 * @return string
	 */
	public static function get($resolve=false)
	{
		if (!$resolve)
		{
			return self::$files;
		}
		$files = array();
		foreach(self::$files as $path)
		{
			foreach(self::resolve_css_includes($path) as $path)
			{
				$files[] = $path;
			}
		}
		return $files;
	}

	/**
	 * Return link tags for all included css files incl. minifying
	 *
	 * @return string
	 */
	public static function tags()
	{
		// add all css files from self::includeCSS
		$max_modified = 0;
		//no more dynamic minifying: $debug_minify = $GLOBALS['egw_info']['server']['debug_minify'] === 'True';
		$base_path = $GLOBALS['egw_info']['server']['webserver_url'];
		if ($base_path[0] != '/') $base_path = parse_url($base_path, PHP_URL_PATH);
		$css_files = '';
		foreach(self::$files as $path)
		{
			foreach(self::resolve_css_includes($path) as $path)
			{
				list($file,$query) = explode('?',$path,2)+[null,null];
				if (($mod = filemtime(EGW_SERVER_ROOT.$file)) > $max_modified) $max_modified = $mod;

				// do NOT include app.css or categories.php, as it changes from app to app
				//no more dynamic minifying: if ($debug_minify || substr($path, -8) == '/app.css' || substr($file,-14) == 'categories.php')
				{
					$css_files .= '<link href="'.$GLOBALS['egw_info']['server']['webserver_url'].$path.($query ? '&' : '?').$mod.'" type="text/css" rel="StyleSheet" />'."\n";
				}
				/* no more dynamic minifying
				else
				{
					$css_file .= ($css_file ? ',' : '').substr($path, 1);
				}*/
			}
		}
		/* no more dynamic minifying
		if (!$debug_minify)
		{
			$css = $GLOBALS['egw_info']['server']['webserver_url'].'/phpgwapi/inc/min/?';
			if ($base_path && $base_path != '/') $css .= 'b='.substr($base_path, 1).'&';
			$css .= 'f='.$css_file .
				($GLOBALS['egw_info']['server']['debug_minify'] === 'debug' ? '&debug' : '').
				'&'.$max_modified;
			$css_files = '<link href="'.$css.'" type="text/css" rel="StyleSheet" />'."\n".$css_files;
		}*/
		return $css_files;
	}

	/**
	 * Parse beginning of given CSS file for /*@import url("...") statements
	 *
	 * @param string $path EGroupware relative path eg. /phpgwapi/templates/default/some.css
	 * @return array parsed pathes (EGroupware relative) including $path itself
	 */
	protected static function resolve_css_includes($path, &$pathes=array())
	{
		$matches = null;

		list($file) = explode('?',$path,2);
		if (($to_check = file_get_contents (EGW_SERVER_ROOT.$file, false, null, 0, 1024)) &&
			stripos($to_check, '/*@import') !== false && preg_match_all('|/\*@import url\("([^"]+)"|i', $to_check, $matches))
		{
			foreach($matches[1] as $import_path)
			{
				if ($import_path[0] != '/')
				{
					$dir = dirname($path);
					while(substr($import_path,0,3) == '../')
					{
						$dir = dirname($dir);
						$import_path = substr($import_path, 3);
					}
					$import_path = ($dir != '/' ? $dir : '').'/'.$import_path;
				}
				self::resolve_css_includes($import_path, $pathes);
			}
		}
		$pathes[] = $path;

		return $pathes;
	}
}
