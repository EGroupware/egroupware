<?php
/**
 * Router script for `php -S` used by the "Run PHPUnit" CI job (see .github/workflows/testing.yml).
 *
 * Two things nginx normally does for us that php -S's built-in file/PATH_INFO resolution does not:
 *
 * 1. header.inc.php's webserver_url config is "/egroupware" (matching the standard nginx-fronted
 *    deployment layout, see doc/docker/nginx.conf's "fastcgi_param SCRIPT_FILENAME
 *    /var/www/egroupware$1;"), but here the checked-out source is served at the webserver root.
 *    Any server-generated absolute redirect (Egw::redirect_link(), Api\Framework::redirect_link(),
 *    ...) therefore points at "/egroupware/some/script.php/extra", which does not correspond to any
 *    real file under this docroot. Without a router, php -S's undocumented fallback for a
 *    non-matching path is to just execute the docroot's own index.php - silently bypassing whatever
 *    script the request was actually meant for (see the CI failure this was added for: an openid
 *    /authorize follow-up redirect landed on the desktop instead of completing the OAuth flow).
 *    We resolve the real script + PATH_INFO ourselves and require() it directly, mirroring exactly
 *    what nginx's rewrite already does.
 *
 * 2. doc/docker/nginx.conf also has an explicit rewrite mapping /.well-known/openid-configuration
 *    to openid/well-known-configuration.php, which needs the same manual handling here.
 *
 * Any other path (already matching a real file under the docroot, eg. "/openid/endpoint.php/authorize"
 * without the "/egroupware" prefix, since most requests - including all test-code-constructed URLs -
 * never carry it) is left alone by returning false, so php -S's normal handling is unaffected.
 */

$doc_root = __DIR__.'/..';

$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

if (preg_match('#^/egroupware(/.*|)$#', $path, $m))
{
	$real_path = $m[1] !== '' ? $m[1] : '/';

	// find the longest prefix of $real_path that is an actual .php file, the remainder is PATH_INFO
	// (same resolution nginx's fastcgi_split_path_info / php-fpm would do for us)
	$segments = explode('/', ltrim($real_path, '/'));
	$script = $path_info = '';
	for ($i = count($segments); $i > 0; $i--)
	{
		$candidate = implode('/', array_slice($segments, 0, $i));
		if (substr($candidate, -4) === '.php' && is_file($doc_root.'/'.$candidate))
		{
			$script = $candidate;
			$path_info = implode('/', array_slice($segments, $i));
			break;
		}
	}
	if ($script !== '')
	{
		$_SERVER['SCRIPT_NAME'] = '/'.$script;
		$_SERVER['SCRIPT_FILENAME'] = $doc_root.'/'.$script;
		$_SERVER['PATH_INFO'] = $path_info !== '' ? '/'.$path_info : '';
		$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'].$_SERVER['PATH_INFO'];
		$query = parse_url($request_uri, PHP_URL_QUERY);
		$_SERVER['REQUEST_URI'] = $real_path.($query !== null ? '?'.$query : '');
		chdir($doc_root.'/'.dirname($script));
		require $doc_root.'/'.$script;
		return true;
	}
	// no matching script for the stripped path either - fall through with the corrected URI, so
	// the well-known-configuration check and the final "return false" see the real path too
	$_SERVER['REQUEST_URI'] = $real_path;
	$path = $real_path;
}

if (preg_match('#^/\.well-known/openid-configuration(?:\?.*)?$#', $path))
{
	chdir($doc_root.'/openid');
	require $doc_root.'/openid/well-known-configuration.php';
	return true;
}
return false;
