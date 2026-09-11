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
	 * Current build's manifest, for stamping into the page as data-manifest
	 *
	 * Used to filter this to apps the current user may access, following the same precedent as
	 * Link::json_registry() - that filtering was removed (ticket #124112's underlying cause,
	 * found 2026-09-11): `data-include` (Framework::get_script_links()/js_includes(), checking
	 * the *full*, unfiltered manifest via resolveEntry()) and `data-manifest` (this method) were two
	 * separately-computed lists, with nothing guaranteeing they agreed on which apps to include.
	 * An app landing in data-include's client-side-resolved set without a matching data-manifest
	 * entry made egw_import() (correctly, per its own design) fall back to that app's literal,
	 * unhashed app.min.js path - which still exists on disk (rollup no longer writes it, so it's
	 * frozen at whatever it last contained) and can reference a long-stale copy of shared chunks
	 * like etemplate2, colliding with whatever a correctly hash-resolved app already registered -
	 * the exact "Illegal constructor" this whole project exists to prevent. A special case for
	 * "kdots" (the active template/framework, needed even though it's not a business-app
	 * permission) papered over one instance of this; live testing then found apps with a real,
	 * granted permission (kanban, rocketchat) hitting the exact same class of bug regardless.
	 *
	 * Unlike Link::json_registry(), the JS itself carries nothing confidential - there's no real
	 * reason a user shouldn't be able to fetch another app's app.min.js if they know the hashed
	 * URL. Given that, and that the filtering was actively causing this bug class rather than
	 * protecting anything meaningful, an unfiltered manifest is simpler and safer than keeping
	 * two independently-computed lists in sync.
	 *
	 * @return array logical path => hashed path
	 */
	public static function clientManifest()
	{
		return self::loadManifest() ?: [];
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

				$is_entry = preg_match('#/js/(app(\.min)?|etemplate/etemplate2)\.js$#', $file);

				// A legacy, pre-rollup <app>/js/app.js can still be sitting on disk - they are
				// gitignored, so they survive every deploy - while the manifest only ever keys the
				// app.min.js rollup actually builds. Handing that non-min path to the client
				// unresolved makes it load the stale artifact, whose own baked-in chunk/vendor
				// imports are long gone (404s). Upgrade it to its .min sibling, which is what the
				// .min-companion swap below has always silently done for such a file.
				if ($is_entry && str_ends_with($path, '/app.js') &&
					self::resolveEntry($min_entry = substr($path, 0, -3).'.min.js'))
				{
					$path = $min_entry;
				}
				// Only emit a bare logical path the client can actually resolve against its own
				// manifest; without a hit, fall through to the pre-hashing handling below
				// (min-companion swap plus cache-buster) rather than emitting an unresolvable one.
				if ($is_entry && self::resolveEntry($path))
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
