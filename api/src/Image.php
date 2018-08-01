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
	 * @param boolean $_svg =false should svg images be returned or not:
	 *	true: always return svg, false: never return svg (current default), null: browser dependent, see svg_usable()
	 * @return string url of image or null if not found
	 */
	static function find($app,$image,$extension='',$_svg=false)
	{
		$svg = Header\UserAgent::mobile() ? null : $_svg; // ATM we use svg icons only for mobile theme
		static $image_map_no_svg = null, $image_map_svg = null;
		if (is_null($svg)) $svg = self::svg_usable ();
		if ($svg)
		{
			$image_map =& $image_map_svg;
		}
		else
		{
			$image_map =& $image_map_no_svg;
		}
		if (is_null($image_map)) $image_map = self::map(null, $svg);

		// array of images in descending precedence
		if (is_array($image))
		{
			foreach($image as $img)
			{
				if (($url = self::find($app, $img, $extension)))
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
			return $webserver_url.$image_map['vfs'][$image.$extension];
		}
		// then app specific ones
		if(isset($image_map[$app][$image.$extension]))
		{
			return $webserver_url.$image_map[$app][$image.$extension];
		}
		// then api
		if(isset($image_map['api'][$image.$extension]))
		{
			return $webserver_url.$image_map['api'][$image.$extension];
		}
		if(isset($image_map['phpgwapi'][$image.$extension]))
		{
			return $webserver_url.$image_map['phpgwapi'][$image.$extension];
		}

		// if image not found, check if it has an extension and try withoug
		if (strpos($image, '.') !== false)
		{
			$name = null;
			self::get_extension($image, $name);
			return self::find($app, $name, $extension);
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
	 * Does browser support svg
	 *
	 * All non IE and IE 9+
	 *
	 * @return boolean
	 */
	public static function svg_usable()
	{
		return Header\UserAgent::type() !== 'msie' || Header\UserAgent::version() >= 9;
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
	 * @param boolean $svg =null prefer svg images, default for all browsers but IE<9
	 * @return array of application => image-name => full path
	 */
	public static function map($template_set=null, $svg=null)
	{
		if (is_null($template_set))
		{
			$template_set = $GLOBALS['egw_info']['server']['template_set'];
		}
		if (is_null($svg))
		{
			$svg = self::svg_usable();
		}

		$cache_name = 'image_map_'.$template_set.($svg ? '_svg' : '').(Header\UserAgent::mobile() ? '_mobile' : '');
		if (($map = Cache::getInstance(__CLASS__, $cache_name)))
		{
			return $map;
		}
		//$starttime = microtime(true);

		// priority: : PNG->JPG->GIF
		$img_types = array('png','jpg','gif','ico');

		// if we want svg, prepend it to img-types
		if ($svg) array_unshift ($img_types, 'svg');

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
		if (($dir = $GLOBALS['egw_info']['server']['vfs_image_dir']) && Vfs::file_exists($dir) && Vfs::is_readable($dir))
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
