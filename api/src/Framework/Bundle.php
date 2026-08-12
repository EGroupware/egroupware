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

/**
 * Bundle JS includes
 *
 * JS bundling/minifying (grouping several files into one, terser-minified via Grunt) was dropped
 * when we moved to rollup for building the real JS bundles (jsapi.min.js, app.min.js, ...);
 * the per-file resolution (cache-buster, picking up a *.min.js companion file if present) in
 * js_includes() below is all that's left running here.
 */
class Bundle
{
	/**
	 * Resolve js-includes to their final urls, picking up a *.min.js companion file and a
	 * cache-buster where appropriate
	 *
	 * @param array $js_includes files to include with egw relative url
	 * @param array& $to_include on return map file => resolved url
	 * @return array egw relative urls to include
	 */
	public static function js_includes(array $js_includes, array &$to_include=null)
	{
		$to_include = array();
		foreach($js_includes as $file)
		{
			if (in_array($file, ['/api/js/jsapi/egw.js','/api/js/jsapi/egw.min.js'])) continue;	// loaded via own tag, and we must not load it twice!

			if (!isset($to_include[$file]))
			{
				list($path, $query) = explode('?', $file, 2)+[null,null];
				$mod = filemtime(EGW_SERVER_ROOT.$path);
				// check if we have a more recent minified version of the file and use it
				if (substr($path, -3) == '.js' && file_exists(EGW_SERVER_ROOT.($min_path = substr($path, 0, -3).'.min.js')) &&
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
					// do NOT include
				}
				else
				{
					$to_include[$file] = $path.($query ? '?'.$query : '');
				}
			}
		}
		return array_values(array_unique($to_include));
	}

	/**
	 * Generate importmap for whole instance
	 *
	 * JS bundling/minifying was dropped when we moved to rollup (see class doc-comment); there is
	 * currently nothing to add here. Framework::getImportMap() adds its own extra mappings on top.
	 *
	 * @return array
	 */
	public static function getImportMap()
	{
		return [];
	}
}
