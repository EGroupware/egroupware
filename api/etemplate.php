<?php
/**
 * API: loading for web-components modified eTemplate from server
 *
 * Usage: /egroupware/api/etemplate.php/<app>/templates/default/<name>.xet
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware-org>
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;

// add et2- prefix to following widgets/tags
const ADD_ET2_PREFIX_REGEXP = '#<((/?)([vh]?box|textbox|textarea|button|colorpicker|description))(/?|\s[^>]*)>#m';

// switch evtl. set output-compression off, as we cant calculate a Content-Length header with transparent compression
ini_set('zlib.output_compression', 0);

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp'                  => 'api',
		'noheader'                    => true,
		// miss-use session creation callback to send the template, in case we have no session
		'autocreate_session_callback' => 'send_template',
		'nocachecontrol'              => true,
	)
);

$start = microtime(true);
include '../header.inc.php';

send_template();

function send_template()
{
	$header_include = microtime(true);

	// release session, as we don't need it and it blocks parallel requests
	$GLOBALS['egw']->session->commit_session();

	header('Content-Type: application/xml; charset=UTF-8');

	//$path = EGW_SERVER_ROOT.$_SERVER['PATH_INFO'];
	// check for customized template in VFS
	list(, $app, , $template, $name) = explode('/', $_SERVER['PATH_INFO']);
	$path = Api\Etemplate::rel2path(Api\Etemplate::relPath($app . '.' . basename($name, '.xet'), $template));
	if(empty($path) || !file_exists($path) || !is_readable($path))
	{
		http_response_code(404);
		exit;
	}
	/* disable caching for now, as you need to delete the cache, once you change ADD_ET2_PREFIX_REGEXP
	$cache = $GLOBALS['egw_info']['server']['temp_dir'].'/egw_cache/eT2-Cache-'.$GLOBALS['egw_info']['server']['install_id'].$_SERVER['PATH_INFO'];
	if (file_exists($cache) && filemtime($cache) > filemtime($path) &&
		($str = file_get_contents($cache)) !== false)
	{
		$cache_read = microtime(true);
	}
	else*/
	if(($str = file_get_contents($path)) !== false)
	{
		// fix <menulist...><menupopup type="select-*"/></menulist> --> <select type="select-*" .../>
		$str = preg_replace('#<menulist([^>]*)>[\r\n\s]*<menupopup([^>]+>)[\r\n\s]*</menulist>#', '<select$1$2', $str);

		// fix <textbox multiline="true" .../> --> <textarea .../> (et2-prefix and self-closing is handled below)
		$str = preg_replace('#<textbox(.*?)\smultiline="true"(.*?)/>#u', '<textarea$1$2/>', $str);

		// fix <buttononly.../> --> <button type="buttononly".../>
		$str = preg_replace('#<buttononly\s(.*?)/>#u', '<button type="buttononly" $1/>', $str);

		$str = preg_replace_callback(ADD_ET2_PREFIX_REGEXP, static function (array $matches)
		{
			return '<' . $matches[2] . 'et2-' . $matches[3] .
				// web-components must not be self-closing (no "<et2-button .../>", but "<et2-button ...></et2-button>")
				(substr($matches[4], -1) === '/' ? substr($matches[4], 0, -1) . '></et2-' . $matches[3] : $matches[4]) . '>';
		}, $str);

		// handling of partially implemented select and date widget (only readonly or simple select without tags or search attribute or options)
		$str = preg_replace_callback('#<(select|date)(-[^ ]+)? ([^>]+)/>#', static function (array $matches)
		{
			preg_match_all('/(^| )([a-z0-9_-]+)="([^"]+)"/', $matches[3], $attrs, PREG_PATTERN_ORDER);
			$attrs = array_combine($attrs[2], $attrs[3]);

			// add et2-prefix for <select-* or <date-* readonly="true"
			if (($matches[1] === 'select' || in_array($matches[1].$matches[2], ['date','date-time'])) &&
					isset($attrs['readonly']) && !in_array($attrs['readonly'], ['false', '0']) ||
				// also add it for untyped/simple <select without search or tags attribute
				$matches[1] === 'select' && empty($matches[2]) && !isset($attrs['type']) && !isset($attrs['search']) && !isset($attrs['tags']))
			{
				return '<et2-'.$matches[1].$matches[2].' '.$matches[3].'></et2-'.$matches[1].$matches[2].'>';
			}
			return $matches[0];
		}, $str);

		$processing = microtime(true);

		if(isset($cache) && (file_exists($cache_dir = dirname($cache)) || mkdir($cache_dir, 0755, true)))
		{
			file_put_contents($cache, $str);
		}
	}
	// stop here for not existing file path-traversal for both file and cache here
	if(empty($str) || strpos($path, '..') !== false)
	{
		http_response_code(404);
		exit;
	}

	// headers to allow caching, egw_framework specifies etag on url to force reload, even with Expires header
	Api\Session::cache_control(86400);    // cache for one day
	$etag = '"' . md5($str) . '"';
	Header('ETag: ' . $etag);

	// if servers send a If-None-Match header, response with 304 Not Modified, if etag matches
	if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag)
	{
		header("HTTP/1.1 304 Not Modified");
		exit;
	}

	// we run our own gzip compression, to set a correct Content-Length of the encoded content
	if(function_exists('gzencode') && in_array('gzip', explode(',', $_SERVER['HTTP_ACCEPT_ENCODING']), true))
	{
		$gzip_start = microtime(true);
		$str = gzencode($str);
		header('Content-Encoding: gzip');
		$gziping = microtime(true) - $gzip_start;
	}
	header('X-Timing: header-include=' . number_format($header_include - $GLOBALS['start'], 3) .
		   (empty($processing) ? ', cache-read=' . number_format($cache_read - $header_include, 3) :
			   ', processing=' . number_format($processing - $header_include, 3)) .
		   (!empty($gziping) ? ', gziping=' . number_format($gziping, 3) : '') .
		   ', total=' . number_format(microtime(true) - $GLOBALS['start'], 3)
	);

	// Content-Length header is important, otherwise browsers dont cache!
	Header('Content-Length: ' . bytes($str));
	echo $str;

	exit;    // stop further processing eg. redirect to login
}
