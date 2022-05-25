<?php
/**
 * EGroupware API - Bundle JS includes
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage framework
 * @access public
 * @version $Id$
 */

namespace EGroupware\Api\Framework;

use EGroupware\Api;
use EGroupware\Api\Cache;
use EGroupware\Api\Header\UserAgent;

/**
 * Bundle JS includes
 */
class Bundle
{
	/**
	 * Url of minified version of bundle
	 *
	 * @var array
	 */
	static $bundle2minurl = array(
		'api' => '/api/js/jsapi.min.js',
	//	'et2' => '/api/js/etemplate/etemplate2.min.js',
		'et21'=> '/api/js/etemplate/etemplate2.min.js',
		'pixelegg' => '/pixelegg/js/fw_pixelegg.min.js',
		'jdots' => '/jdots/js/fw_jdots.min.js',
		'mobile' => '/pixelegg/js/fw_mobile.min.js',
	);

	/**
	 * Devide js-includes in bundles of javascript files to include eg. api or etemplate2, if minifying is enabled
	 *
	 * @param array $js_includes files to include with egw relative url
	 * @param array& $to_include on return map file => bundle
	 * @return array egw relative urls to include incl. bundels/minify urls, if enabled
	 */
	public static function js_includes(array $js_includes, array &$to_include=null)
	{
		// disable dev-mode for now with rollup
		unset($GLOBALS['egw_info']['server']['debug_minify']);

		$file2bundle = array();
		if (!isset($GLOBALS['egw_info']['server']['debug_minify']) || $GLOBALS['egw_info']['server']['debug_minify'] !== 'True')
		{
			// get used bundles and cache them on tree-level for 2h
			//$bundles = self::all(); Cache::setTree(__CLASS__, 'bundles', $bundles, 7200);
			$bundles = Cache::getTree(__CLASS__, 'bundles', array(__CLASS__, 'all'), array(), 7200);
			$bundles_ts = $bundles['.ts'] ?? null;
			unset($bundles['.ts']);
			foreach($bundles as $name => $files)
			{
				// to facilitate move to new api/et2 location, can be removed after 16.1 release
				if ($name == 'et21' && !in_array('/api/js/etemplate/etemplate2.js', $files) ||
					$name == 'api' && !in_array('/vendor/bower-asset/jquery/dist/jquery.js', $files))
				{
					Cache::unsetTree(__CLASS__, 'bundles');
					return self::js_includes($js_includes);
				}
				// ignore bundles of not used templates, as they can contain identical files
				if (in_array($name, array('api', 'et2', 'et21')) ||
					$name == (UserAgent::mobile() ? 'mobile' : $GLOBALS['egw_info']['server']['template_set']) ||
					isset($GLOBALS['egw_info']['apps'][$name]))
				{
					$file2bundle += array_combine($files, array_fill(0, count($files), $name));
				}
			}
			//error_log(__METHOD__."() file2bundle=".array2string($file2bundle));
		}
		$to_include = $included_bundles = array();
		$query = null;
		foreach($js_includes as $file)
		{
			if (in_array($file, ['/api/js/jsapi/egw.js','/api/js/jsapi/egw.min.js'])) continue;	// loaded via own tag, and we must not load it twice!

			if (!isset($to_include[$file]))
			{
				if (($bundle = $file2bundle[$file] ?? false))
				{
					//error_log(__METHOD__."() requiring bundle $bundle for $file");
					if (!in_array($bundle, $included_bundles))
					{
						$included_bundles[] = $bundle;
						$minurl = self::$bundle2minurl[$bundle] ?? null;
						if (!isset($minurl) && isset($GLOBALS['egw_info']['apps'][$bundle]))
						{
							$minurl = '/'.$bundle.'/js/app.min.js';
						}
						$max_modified = 0;
						$to_include = array_merge($to_include, self::urls($bundles[$bundle], $max_modified, $minurl));
						// check if bundle-config is more recent then
						if ($max_modified > $bundles_ts)
						{
							// force new bundle Config by deleting cached one and call ourself again
							Cache::unsetTree(__CLASS__, 'bundles');
							return self::js_includes($js_includes);
						}
					}
				}
				else
				{
					unset($query);
					list($path, $query) = explode('?', $file, 2)+[null,null];
					$mod = filemtime(EGW_SERVER_ROOT.$path);
					// check if we have a more recent minified version of the file and use it
					if ((!isset($GLOBALS['egw_info']['server']['debug_minify']) || $GLOBALS['egw_info']['server']['debug_minify'] !== 'True') &&
						substr($path, -3) == '.js' && file_exists(EGW_SERVER_ROOT.($min_path = substr($path, 0, -3).'.min.js')) &&
						(($min_mod = filemtime(EGW_SERVER_ROOT.$min_path)) >= $mod))
					{
						$path = $min_path;
						$mod  = $min_mod;
					}
					// use cache-buster only for entry-points / app.js, as the have no hash
					if (preg_match('#/js/(app(\.min)?|etemplate/etemplate2)\.js$#', $file))
					{
						$to_include[$file] = $path.'?'.$mod.($query ? '&'.$query : '');
					}
					elseif (in_array($file, ['/api/js/jsapi.min.js', '/vendor/bower-asset/jquery/dist/jquery.min.js','/vendor/bower-asset/jquery/dist/jquery.js']))
					{
						//error_log(function_backtrace());	// no NOT include
					}
					else
					{
						$to_include[$file] = $path.($query ? '?'.$query : '');
					}
				}
			}
		}
		//error_log(__METHOD__."(".array2string($js_includes).') debug_minify='.array2string($GLOBALS['egw_info']['server']['debug_minify']).', include_bundels='.array2string($included_bundles).' returning '.array2string(array_values(array_unique($to_include))));
		return array_values(array_unique($to_include));
	}

	/**
	 * Generate bundle url(s) for given js files
	 *
	 * @param array $js_includes
	 * @param int& $max_modified =null on return maximum modification time of bundle
	 * @param string $minurl =null url of minified bundle, to be used, if existing and recent
	 * @return array js-files (can be more then one, if one of given files can not be bundeled)
	 */
	protected static function urls(array $js_includes, &$max_modified=null, $minurl=null)
	{
		$debug_minify = !empty($GLOBALS['egw_info']['server']['debug_minify']) && $GLOBALS['egw_info']['server']['debug_minify'] === 'True';
		// ignore not existing minurl
		if (!empty($minurl) && !file_exists(EGW_SERVER_ROOT.$minurl)) $minurl = null;
		$to_include_first = $to_include = $to_minify = array();
		$max_modified = 0;
		$query = null;
		foreach($js_includes as $path)
		{
			if ($path == '/api/js/jsapi/egw.js') continue; // Leave egw.js out of bundle
			unset($query);
			list($path,$query) = explode('?',$path,2)+[null,null];
			$mod = filemtime(EGW_SERVER_ROOT.$path);
			if ($mod > $max_modified) $max_modified = $mod;

			// TinyMCE must be included before bundled files, as it depends on it!
			if (strpos($path, '/tinymce/tinymce.min.js') !== false)
			{
				$to_include_first[] = $path;// . '?' . $mod;
			}
			// for now minify does NOT support query parameters, nor php files generating javascript
			elseif ($debug_minify || $query || substr($path, -3) != '.js' || empty($minurl))
			{
				//$path .= '?'. $mod.($query ? '&'.$query : '');
				$to_include[] = $path;
			}
			else
			{
				$to_minify[] = substr($path,1);
			}
		}
		if (!$debug_minify && $to_minify)
		{
			$path = $minurl;//.'?'.filemtime(EGW_SERVER_ROOT.$minurl);
			/* no more dynamic minifying
			if (!empty($minurl) && file_exists(EGW_SERVER_ROOT.$minurl) &&
				($mod=filemtime(EGW_SERVER_ROOT.$minurl)) >= $max_modified)
			{
				$path = $minurl.'?'.$mod;
			}
			else
			{
				$base_path = $GLOBALS['egw_info']['server']['webserver_url'];
				if ($base_path[0] != '/') $base_path = parse_url($base_path, PHP_URL_PATH);
				$path = '/phpgwapi/inc/min/?'.($base_path && $base_path != '/' ? 'b='.substr($base_path, 1).'&' : '').
					'f='.implode(',', $to_minify) .
					($GLOBALS['egw_info']['server']['debug_minify'] === 'debug' ? '&debug' : '').
					'&'.$max_modified;
			}*/
			// need to include minified javascript before not minified stuff like jscalendar-setup, as it might depend on it
			array_unshift($to_include, $path);
		}
		if ($to_include_first) $to_include = array_merge($to_include_first, $to_include);
		//error_log(__METHOD__."("./*array2string($js_includes).*/", $max_modified, $minurl) returning ".array2string($to_include));
		return $to_include;
	}

	/**
	 * Maximum number of files in a bundle
	 *
	 * We split bundles, if they contain more then these number of files,
	 * because IE silently stops caching them, if Content-Length get's too big.
	 *
	 * IE11 cached 142kb compressed api bundle, but not 190kb et2 bundle.
	 * Splitting et2 bundle in max 50 files chunks, got IE11 to cache both bundles.
	 */
	const MAX_BUNDLE_FILES = 50;

	/**
	 * Apps which should be their own bundle:
	 * - own eT2 widgets
	 * - not just an app.js or a huge one
	 */
	const BUNDLE_APPS = ['calendar', 'mail', 'projectmanager', 'smallpart'];

	/**
	 * Return all bundels we use:
	 * - api stuff phpgwapi/js/jsapi/* and it's dependencies incl. jquery
	 * - etemplate2 stuff not including api bundle, but jquery-ui
	 *
	 * @param bool $all_apps=false true: return bundle for every app with an app.js/ts
	 * @return array bundle-url => array of contained files
	 */
	public static function all(bool $all_apps = false)
	{
		return [];
		$inc_mgr = new IncludeMgr();
		$bundles = array();

		$max_mod = array();

		// generate api bundle
		$inc_mgr->include_js_file('/vendor/bower-asset/jquery/dist/jquery.js');
		$inc_mgr->include_js_file('/api/js/jquery/jquery.noconflict.js');
		$inc_mgr->include_js_file('/api/js/jsapi/jsapi.js');
		$inc_mgr->include_js_file('/api/js/egw_json.js');
		$inc_mgr->include_js_file('/api/js/jsapi/egw.js');
		// dhtmlxTree (dhtmlxMenu get loaded via dependency in egw_menu_dhtmlx.js)
		$inc_mgr->include_js_file('/api/js/dhtmlxtree/codebase/dhtmlxcommon.js');
		$inc_mgr->include_js_file('/api/js/dhtmlxtree/sources/dhtmlxtree.js');
		$inc_mgr->include_js_file('/api/js/dhtmlxtree/sources/ext/dhtmlxtree_json.js');
		// actions
		$inc_mgr->include_js_file('/api/js/egw_action/egw_action_constants.js');
		$inc_mgr->include_js_file('/api/js/egw_action/egw_action.js');
		$inc_mgr->include_js_file('/api/js/egw_action/egw_keymanager.js');
		$inc_mgr->include_js_file('/api/js/egw_action/egw_action_popup.js');
		$inc_mgr->include_js_file('/api/js/egw_action/egw_action_dragdrop.js');
		$inc_mgr->include_js_file('/api/js/egw_action/egw_dragdrop_dhtmlx_tree.js');
		$inc_mgr->include_js_file('/api/js/egw_action/egw_menu.js');
		$inc_mgr->include_js_file('/api/js/egw_action/egw_menu_dhtmlx.js');
		// include choosen in api, as old eTemplate uses it and fail if it pulls in half of et2
		$inc_mgr->include_js_file('/api/js/jquery/chosen/chosen.jquery.js');
		$bundles['api'] = $inc_mgr->get_included_files();
		self::urls($bundles['api'], $max_mod['api']);

		// generate et2 bundle (excluding files in api bundle)
		$inc_mgr->include_js_file('/api/js/etemplate/etemplate2.js');
		$bundles['et2'] = array_diff($inc_mgr->get_included_files(), $bundles['api']);
		self::urls($bundles['et2'], $max_mod['et2']);

		$stock_files = array_merge(...array_values($bundles));

		// generate template and app bundles, if installed
		foreach([
			'jdots' => '/jdots/js/fw_jdots.js',
			'mobile' => '/pixelegg/js/fw_mobile.js',
			'pixelegg' => '/pixelegg/js/fw_pixelegg.js',
		]+($all_apps ? scandir(EGW_SERVER_ROOT) : self::BUNDLE_APPS) as $bundle => $file)
		{
			if (is_int($bundle))
			{
				$bundle = $file;
				$file = "/$bundle/js/app.js";
			}
			if (@file_exists(EGW_SERVER_ROOT.$file))
			{
				$inc_mgr = new IncludeMgr($stock_files);	// reset loaded files to stock files
				$inc_mgr->include_js_file($file);
				$bundles[$bundle] = array_diff($inc_mgr->get_included_files(), $stock_files);
				self::urls($bundles[$bundle], $max_mod[$bundle]);
			}
		}

		// automatic split bundles with more then MAX_BUNDLE_FILES (=50) files
		foreach($bundles as $name => $files)
		{
			$n = '';
			while (count($files) > self::MAX_BUNDLE_FILES*(int)$n)
			{
				$files80 = array_slice($files, self::MAX_BUNDLE_FILES*(int)$n, self::MAX_BUNDLE_FILES, true);
				$bundles[$name.$n++] = $files80;
			}
		}

		// store max modification time of all files in all bundles
		$bundles['.ts'] = max($max_mod);

		//error_log(__METHOD__."() returning ".array2string($bundles));
		return $bundles;
	}

	/**
	 * some files are not in a bundle, because loaded otherwise or are big enough themselves
	 *
	 * @var array
	 */
	static public $exclude = [
		// api/js/jsapi/egw.js loaded via own tag, and we must not load it twice!
		'api/js/jsapi/egw.js',
		// TinyMCE is loaded separate before the bundle
		'vendor/tinymce/tinymce/tinymce.min.js',
		// CRM.js from addressbook is also used in infolog, so it can't be bundled with either!
		'addressbook/js/CRM.js',
	];

	/**
	 * Generate importmap for whole instance
	 *
	 * It need to be for the whole instance incl. all app.js, as it does not get reloaded, when we execute
	 * apps via ajax!
	 *
	 * @ToDo new-js-loader: use static file in filesystem updated when js-files get minified (for minified only!)
	 *
	 * @return array
	 */
	public static function getImportMap()
	{
		$minified = empty($GLOBALS['egw_info']['server']['debug_minify']);

		// cache map for the whole tree to use
		return Cache::getTree('api', 'importmap'.($minified?'-minified':''), static function()
		{
			$gruntfile = EGW_SERVER_ROOT . '/Gruntfile.js';
			if (!($content = @file_get_contents($gruntfile)))
			{
				die("\nFile '$gruntfile' not found!\n\n");
			}

			if (!preg_match('/grunt\.initConfig\(({.+})\);/s', $content, $matches) ||
				!($json = preg_replace('/^(\s*)([a-z0-9_-]+):/mi', '$1"$2":', $matches[1])) ||
				!($config = json_decode($json, true)))
			{
				die("\nCan't parse $gruntfile!\n\n");
			}

			if (($prefix = parse_url($GLOBALS['egw_info']['server']['webserver_url'], PHP_URL_PATH)) === '/') $prefix = '';
			$uglify = $config['terser'];
			unset($config, $uglify['options']);
			$map = [];
			foreach (self::all(true) as $name => $files)
			{
				if ($name == '.ts') continue;    // ignore timestamp

				// some files are not in a bundle, because they are big enough themselves or otherwise excluded
				foreach (self::$exclude as $file)
				{
					if (($key = array_search($file, $files)))
					{
						$map[$prefix . $file] = $prefix . $file ;//. '?' . filemtime(EGW_SERVER_ROOT . $file);
						unset($files[$key]);
					}
				}

				if (isset($uglify[$name]))
				{
					$target = key($uglify[$name]['files']);
					$uglify[$name]['files'][$target] = array_values($files);
				}
				elseif (isset($uglify[$append = substr($name, 0, -1)]))
				{
					reset($uglify[$append]['files']);
					$target = key($uglify[$append]['files']);
					$uglify[$append]['files'][$target] = array_merge($uglify[$append]['files'][$target], array_values($files));
				}
				else    // create new bundle using last file as target
				{
					$target = str_replace('.js', '.min.js', end($files));
					$uglify[$name]['files'][$target] = array_values($files);
				}
				if ($target[0] !== '/') $target = '/' . $target;

				$use_bundle = in_array($name, array_merge(['api', 'et2'], Bundle::BUNDLE_APPS)) &&
					empty($GLOBALS['egw_info']['server']['debug_minify']);

				foreach ($files as $file)
				{
					// use bundle / minified url as target or not
					if (!$use_bundle) $target = $file;
					$map[$prefix . $file] = $prefix.$target;//.'?'.filemtime(EGW_SERVER_ROOT.$target);
					// typescript unfortunately has currently no option to add ".js" to it's es6 import statements
					// therefore we add extra entries without .js extension to the map
					if (file_exists(EGW_SERVER_ROOT.substr($file, 0, -3) . '.ts'))
					{
						$map[$prefix . substr($file, 0, -3)] = $prefix.$target;//.'?'.filemtime(EGW_SERVER_ROOT.$target);
					}
				}
			}
			// filter out legacy js files not load via import
			$map = array_filter($map, function($url)
			{
				return !preg_match(Api\Framework::legacy_js_imports, $url);
			});
			ksort($map);

			return $map;
		}, [], 30);
	}
}