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
	 * Current build's manifest, read once per request
	 *
	 * Unversioned - the server only ever resolves an entry against the current build (a fresh
	 * page render) or not at all (ajax_exec, where the client resolves against the manifest it
	 * already has resident in window.egw_manifest since ITS OWN page render, not whatever this
	 * request considers current). So there is never a historical build to look up here.
	 *
	 * @var array|false|null null: not loaded yet this request
	 */
	private static $manifest = null;

	/**
	 * Load the current build's hashed-entry manifest
	 *
	 * @return array|false logical path => hashed path, or false if no manifest exists (a
	 *  pre-hashing install that never wrote one)
	 */
	public static function loadManifest()
	{
		if (!isset(self::$manifest))
		{
			$file = EGW_SERVER_ROOT.'/api/js/build-manifest.json';
			self::$manifest = file_exists($file) ?
				json_decode(file_get_contents($file), true) : false;
		}
		return self::$manifest;
	}

	/**
	 * Resolve a logical entry path to its hashed physical path, against the current build
	 *
	 * @param string $logical eg. "/infolog/js/app.min.js"
	 * @return string|false hashed path (eg. "/chunks/infolog-js-app.min-<hash>.js"), or false on
	 *  a miss (app not built, or no manifest at all) - callers must fall back to the literal
	 *  unhashed path, this is not an error
	 */
	public static function resolveEntry($logical)
	{
		$manifest = self::loadManifest();
		return $manifest[$logical] ?? false;
	}

	/**
	 * Current build's manifest, filtered to apps the current user may access, for stamping into
	 * the page as data-manifest
	 *
	 * Rollup's manifest lists every built app, including ones merely present on disk but not
	 * installed, or installed-but-not-permitted for this user - neither of which today's
	 * data-include leaks. Filtering here follows the same precedent as Link::json_registry().
	 *
	 * The active template/framework (eg. "kdots") is a rendering choice, not a business-app
	 * permission, so it never appears in $GLOBALS['egw_info']['user']['apps'] - but its own JS
	 * gets loaded unconditionally regardless (Framework::init_static() et al), same as "api".
	 * Excluding it here (live-verified 2026-09-09) left its entry with no manifest hit, so it
	 * fell back to a literal path that no longer exists once entries are hashed, 404ing and
	 * leaving the custom element registry half-initialized - which then surfaced as unrelated
	 * "Illegal constructor" crashes in whatever app happened to render next.
	 *
	 * @return array logical path => hashed path
	 */
	public static function clientManifest()
	{
		$manifest = self::loadManifest();
		if (!$manifest) return [];

		$alwaysAllowed = ['api', $GLOBALS['egw_info']['server']['template_set'] ?? null];

		return array_filter($manifest, static function($logical) use ($alwaysAllowed)
		{
			return !preg_match('#^/([^/]+)/#', $logical, $matches) || in_array($matches[1], $alwaysAllowed, true) ||
				isset($GLOBALS['egw_info']['user']['apps'][$matches[1]]);
		}, ARRAY_FILTER_USE_KEY);
	}

	/**
	 * Resolve js-includes to their final urls, picking up a *.min.js companion file and a
	 * cache-buster where appropriate
	 *
	 * An entry (app.min.js, etemplate2.js) is left as its bare logical path here, whether this
	 * is a full page render or an ajax_exec response - both are consumed client-side by
	 * egw_import() (egw_files.ts), which resolves it against the manifest it already has
	 * resident, ie. the build the document's own page render was pinned to, not whatever this
	 * request considers current. The server never needs to resolve an entry's hash for a
	 * document that already exists; the one place it still does is resolveEntry() being called
	 * directly for the small number of things that have no such client-side consumer at all (the
	 * hardcoded egw.min.js <script> tag, and the two `app has an entry at all` existence checks).
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

				if (preg_match('#/js/(app(\.min)?|etemplate/etemplate2)\.js$#', $file))
				{
					$to_include[$file] = $path;
					continue;
				}

				$mod = filemtime(EGW_SERVER_ROOT.$path);
				// check if we have a more recent minified version of the file and use it
				if (substr($path, -3) == '.js' && file_exists(EGW_SERVER_ROOT.($min_path = substr($path, 0, -3).'.min.js')) &&
					(($min_mod = filemtime(EGW_SERVER_ROOT.$min_path)) >= $mod))
				{
					$path = $min_path;
					$mod  = $min_mod;
				}
				if (in_array($file, ['/api/js/jsapi.min.js', '/vendor/bower-asset/jquery/dist/jquery.min.js','/vendor/bower-asset/jquery/dist/jquery.js']))
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
}
