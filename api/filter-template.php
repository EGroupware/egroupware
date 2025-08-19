<?php
/**
 * API: generating a filter-eTemplate from a NM row-template
 *
 * Usage: /egroupware/api/filter-template.php/$app/templates/default/index.xet?(filter(2)|cat_id)_(label)=$label
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware-org>
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;

// switch evtl. set output-compression off, as we can't calculate a Content-Length header with transparent compression
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
include dirname(__DIR__).'/header.inc.php';

send_template();

/**
 * Give usage plus option error and exit
 *
 * @param string $prog
 * @param string? $err
 * @return void
 */
function usage($prog, $err=null)
{
	error_log("Usage: $prog <xet-file>\n");
	error_log("\t generate filter template from <xet-file>.\n\n");
	if ($err) error_log("$err\n\n");
	exit;
}

function send_template()
{
	$header_include = microtime(true);

	if (PHP_SAPI === 'cli')
	{
		$in_place = false;
		$args = $_SERVER['argv'];
		$prog = array_shift($args);
		while($args[0][0] === '-')
		{
			switch($arg = array_shift($args))
			{
				default:
					usage($prog, "Invalid argument '$arg'!");
			}
			if (count($args) !== 1)
			{
				usage($prog);
			}
		}
		$fspath = array_shift($args);
		if ($fspath[0] !== '/') $fspath = '/'.$fspath;
	}
	else
	{
		// release session, as we don't need it and it blocks parallel requests
		$GLOBALS['egw']->session->commit_session();

		header('Content-Type: application/xml; charset=UTF-8');

		$fspath = $_SERVER['PATH_INFO'];
	}
	// check for customized template in VFS
	list(, $app, , $template, $name) = explode('/', $fspath);
	$path = Api\Etemplate::rel2path(Api\Etemplate::relPath($app . '.' . basename($name, '.xet'), $template));
	if(empty($path) || !file_exists($path) || !is_readable($path))
	{
		if (PHP_SAPI === 'cli')
		{
			usage("Path '$path' NOT found!");
		}
		else
		{
			http_response_code(404);
		}
		exit;
	}
	$cache = $GLOBALS['egw_info']['server']['temp_dir'] . '/egw_cache/eT2-Filter-Cache-' .
		$GLOBALS['egw_info']['server']['install_id'] . '-' . str_replace('/', '-', $_SERVER['PATH_INFO']) . '-' . filemtime($path);
	/*if (PHP_SAPI !== 'cli' && file_exists($cache) && filemtime($cache) > max(filemtime($path), filemtime(__FILE__)) &&
		($str = file_get_contents($cache)) !== false)
	{
		$cache_read = microtime(true);
	}
	else*/if(($str = file_get_contents($path)) !== false)
	{
		$template_id = $app.'.filter';
		$xet = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE overlay PUBLIC "-//EGroupware GmbH//eTemplate 2.0//EN" "https://www.egroupware.org/etemplate2.0.dtd">
<overlay>
	<et2-template id="$template_id" slot="filter">
EOF;
		if (empty($_GET['no_search']))
		{
			$xet .= <<<EOF
		<et2-searchbox id="search" label="Search" class="et2-label-fixed"></et2-searchbox>
EOF;
		}
		// get all NM sort-header and place them in filter-template
		$sort_options = [];
		foreach(preg_match_all('#<(et2-)?nextmatch-sortheader ([^>]+?)/>#s', $str, $matches, PREG_SET_ORDER) ? $matches : [] as $n => $match)
		{
			$attrs = parseAttrs($match[2]);
			$sort_options[] = "\t\t\t\t".'<option value="'.$attrs['id'].'">'.$attrs['label'].'</option>';
		}
		// ToDo: add custom-fields
		if ($sort_options)
		{
			$sort_options = implode("\n", $sort_options);
			$xet .= <<<EOF
		<et2-visually-hidden>
			<et2-select id="order" label="Sorting" ariaLabel="Ordering" class="et2-label-fixed">
				$sort_options
			</et2-select>
			<et2-button-toggle ariaLabel="Sorting" id="sort" onIcon="carret-down-fill" offIcon="carret-up-fill"></et2-button-toggle>
		</et2-visually-hidden>
EOF;
		}

		// add standard filter
		foreach(['filter', 'filter2', 'cat_id'] as $id)
		{
			if (isset($_GET[$id]))
			{
				$label = htmlspecialchars($_GET[$id], ENT_XML1, 'UTF-8');
				if ($id === 'cat_id')
				{
					$xet .= <<<EOF
		<et2-select-cat id="$id" label="$label" class="et2-label-fixed"></et2-select-cat>
EOF;
				}
				else
				{
					$xet .= <<<EOF
		<et2-select id="$id" label="$label" class="et2-label-fixed"></et2-select>
EOF;
				}
			}
		}

		// get all NM filter-headers and place them in the template
		$xet .= <<<EOF
		<et2-details summary="Column Filters" open="true">
EOF;
		foreach(preg_match_all('#<(et2-)?nextmatch-([^ ]+filter|filterheader) ([^>]+?)/>#s', $str, $matches, PREG_SET_ORDER) ? $matches : [] as $n => $match)
		{
			$attrs = parseAttrs($match[3]);
			switch($match[2])
			{
				case 'header-filter':
				case 'accountfilter':
				case 'customfilter':
					$label = htmlspecialchars($attrs['label'] ?? $attrs['ariaLabel'] ?? $attrs['emptyLabel'] ?? $attrs['statustext'], ENT_XML1, 'UTF-8');
					$xet .= <<<EOF
			<et2-select id="$attrs[id]" label="$label" class="et2-label-fixed"></et2-select>
EOF;
			}
		}
		$xet .= <<<EOF
		</et2-details>
EOF;
		// favorites
		$xet .= <<<EOF
		<et2-details summary="Favorites" open="true">
			<et2-favorites-menu application="$app"></et2-favorites-menu>
		</et2-details>
EOF;
		$str = $xet . <<<EOF
	</et2-template>
</overlay>
EOF;
		$processing = microtime(true);

		if (isset($cache) && (file_exists($cache_dir = dirname($cache)) || mkdir($cache_dir, 0755, true) || is_dir($cache_dir)))
		{
			file_put_contents($cache, $str);
		}
	}
	// stop here for not existing file or path-traversal for both file and cache here
	if(empty($str) || strpos($path, '..') !== false)
	{
		if (PHP_SAPI === 'cli')
		{
			usage("Path '$path' NOT found!");
		}
		else
		{
			http_response_code(404);
		}
		exit;
	}


	// cli just echos or updates the file
	if (PHP_SAPI === 'cli')
	{
		if (!$in_place)
		{
			echo $str;
		}
		elseif (!is_writable($path) ||
			!rename($path, dirname($path).'/'.basename($path, '.xet').'.old.xet') ||
			file_put_contents($path, $str) !== strlen($str))
		{
			error_log("Error writing file '$path'!\n");
		}
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

/**
 * Parse attributes in an array
 *
 * @param string $str
 * @return array
 */
function parseAttrs($str)
{
	if (empty($str) || !trim($str))
	{
		return [];
	}
	if (!preg_match_all('/(^|\s)([a-z\d_-]+)="([^"]*)"/i', $str, $attrs, PREG_PATTERN_ORDER))
	{
		throw new Exception("Can NOT parse attributes from '$str'");
	}
	return array_combine($attrs[2], $attrs[3]);
}

/**
 * Combine attribute array into a string
 *
 * If there are attributes the returned string is prefixed with a single space, otherwise an empty string is returned.
 *
 * @param array $attrs
 * @return string
 */
function stringAttrs(array $attrs)
{
	if (!$attrs)
	{
		return '';
	}
	// replace deprecated et2_dialog with new Et2Dialog
	if (!empty($attrs['onclick']) && strpos($attrs['onclick'], 'et2_dialog.') !== false)
	{
		$attrs['onclick'] = str_replace('et2_dialog.', 'Et2Dialog.', $attrs['onclick']);
	}
	return ' '.implode(' ', array_map(static function ($name, $value) {
		return $name . '="' . $value . '"';
	}, array_keys($attrs), $attrs));
}