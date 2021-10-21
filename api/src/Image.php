<?php
/**
 * EGroupware API: Finding template specific images
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage image
 * @version $Id$
 */

namespace EGroupware\Api;

/**
 * Finding template specific images
 *
 * Images availability is cached on instance level, cache can be invalidated by
 * calling Admin >> Delete cache and register hooks.
 */
class Image
{
	/**
	 * Searches a appname, template and maybe language and type-specific image
	 *
	 * @param string $app
	 * @param string|array $image one or more image-name in order of precedence
	 * @param string $extension ='' extension to $image, makes sense only with an array
	 * @param boolean $add_cachebuster =false true: add a cachebuster to the returnd url
	 *
	 * @return string url of image or null if not found
	 */
	static function find($app,$image,$extension='',$add_cachebuster=false)
	{
		$image_map = self::map(null);

		// array of images in descending precedence
		if (is_array($image))
		{
			foreach($image as $img)
			{
				if (($url = self::find($app, $img, $extension, $add_cachebuster)))
				{
					return $url;
				}
			}
			//error_log(__METHOD__."('$app', ".array2string($image).", '$extension') NONE found!");
			return null;
		}

		$webserver_url = $GLOBALS['egw_info']['server']['webserver_url'];

		// instance specific images have highest precedence
		if (isset($image_map['vfs'][$image.$extension]))
		{
			$url = $webserver_url.$image_map['vfs'][$image.$extension];
		}
		// then app specific ones
		elseif(isset($image_map[$app][$image.$extension]))
		{
			$url = $webserver_url.$image_map[$app][$image.$extension];
		}
		// then api
		elseif(isset($image_map['api'][$image.$extension]))
		{
			$url = $webserver_url.$image_map['api'][$image.$extension];
		}
		elseif(isset($image_map['phpgwapi'][$image.$extension]))
		{
			$url = $webserver_url.$image_map['phpgwapi'][$image.$extension];
		}

		if (!empty($url))
		{
			if ($add_cachebuster)
			{
				$url .= '?'.filemtime(EGW_SERVER_ROOT.substr($url, strlen($webserver_url)));
			}
			return $url;
		}

		// if image not found, check if it has an extension and try withoug
		if (strpos($image, '.') !== false)
		{
			$name = null;
			self::get_extension($image, $name);
			return self::find($app, $name, $extension, $add_cachebuster);
		}
		//error_log(__METHOD__."('$app', '$image', '$extension') image NOT found!");
		return null;
	}

	/**
	 * Get extension (and optional basename without extension) of a given path
	 *
	 * @param string $path
	 * @param string &$name on return basename without extension
	 * @return string extension without dot, eg. 'php'
	 */
	protected static function get_extension($path, &$name=null)
	{
		$parts = explode('.', Vfs::basename($path));
		$ext = array_pop($parts);
		$name = implode('.', $parts);
		return $ext;
	}

	/**
	 * Scan filesystem for images of all apps
	 *
	 * For each application and image-name (without extension) one full path is returned.
	 * The path takes template-set and image-type-priority (now fixed to: png, jpg, gif, ico) into account.
	 *
	 * VFS image directory is treated like an application named 'vfs'.
	 *
	 * @param string $template_set =null 'default', 'idots', 'jerryr', default is template-set from user prefs
	 *
	 * @return array of application => image-name => full path
	 */
	public static function map($template_set=null)
	{
		if (is_null($template_set))
		{
			$template_set = $GLOBALS['egw_info']['server']['template_set'];
		}

		$cache_name = 'image_map_'.$template_set.'_svg'.(Header\UserAgent::mobile() ? '_mobile' : '');
		if (($map = Cache::getInstance(__CLASS__, $cache_name)))
		{
			return $map;
		}
		//$starttime = microtime(true);

		// priority: : SVG->PNG->JPG->GIF->ICO
		$img_types = array('svg','png','jpg','gif','ico');

		$map = array();
		foreach(scandir(EGW_SERVER_ROOT) as $app)
		{
			if ($app[0] == '.' || !is_dir(EGW_SERVER_ROOT.'/'.$app) || !file_exists(EGW_SERVER_ROOT.'/'.$app.'/templates')) continue;

			$app_map =& $map[$app];
			if (true) $app_map = array();
			$imagedirs = array();
			if (Header\UserAgent::mobile())
			{
				$imagedirs[] = '/'.$app.'/templates/mobile/images';
			}
			if ($app == 'api')
			{
				$imagedirs[] = $GLOBALS['egw']->framework->template_dir.'/images';
			}
			else
			{
				$imagedirs[] = '/'.$app.'/templates/'.$template_set.'/images';
			}
			if ($template_set != 'idots') $imagedirs[] = '/'.$app.'/templates/idots/images';
			$imagedirs[] = '/'.$app.'/templates/default/images';

			foreach($imagedirs as $imagedir)
			{
				if (!file_exists($dir = EGW_SERVER_ROOT.$imagedir) || !is_readable($dir)) continue;

				foreach(scandir($dir) as $img)
				{
					if ($img[0] == '.') continue;

					$subdir = null;
					foreach(is_dir($dir.'/'.$img) ? scandir($dir.'/'.($subdir=$img)) : (array) $img as $img)
					{
						$name = null;
						if (!in_array($ext = self::get_extension($img, $name), $img_types) || empty($name)) continue;

						if (isset($subdir)) $name = $subdir.'/'.$name;

						if (!isset($app_map[$name]) || array_search($ext, $img_types) < array_search(self::get_extension($app_map[$name]), $img_types))
						{
							$app_map[$name] = $imagedir.'/'.$name.'.'.$ext;
						}
					}
				}
			}
		}
		$app_map =& $map['vfs'];
		if (true) $app_map = array();
		if (!empty($dir = $GLOBALS['egw_info']['server']['vfs_image_dir']) && Vfs::file_exists($dir) && Vfs::is_readable($dir))
		{
			foreach(Vfs::find($dir) as $img)
			{
				if (!in_array($ext = self::get_extension($img, $name), $img_types) || empty($name)) continue;

				if (!isset($app_map[$name]) || array_search($ext, $img_types) < array_search(self::get_extension($app_map[$name]), $img_types))
				{
					$app_map[$name] = Vfs::download_url($img);
				}
			}
		}
		else if ($dir)
		{
			return $map;
		}
		//error_log(__METHOD__."('$template_set') took ".(microtime(true)-$starttime).' secs');
		Cache::setInstance(__CLASS__, $cache_name, $map, 86400);	// cache for one day
		//echo "<p>template_set=".array2string($template_set)."</p>\n"; _debug_array($map);
		return $map;
	}

	/**
	 * Delete image map cache for ALL template sets
	 */
	public static function invalidate()
	{
		$templates = array('idots', 'jerryr', 'jdots', 'pixelegg');
		if (($template_set = $GLOBALS['egw_info']['user']['preferences']['common']['template_set']) && !in_array($template_set, $templates))
		{
			$templates[] = $template_set;
		}
		//error_log(__METHOD__."() for templates ".array2string($templates));
		foreach($templates as $template_set)
		{
			Cache::unsetInstance(__CLASS__, 'image_map_'.$template_set);
			Cache::unsetInstance(__CLASS__, 'image_map_'.$template_set.'_svg');
		}
	}
}
