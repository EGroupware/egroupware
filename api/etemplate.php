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
const ADD_ET2_PREFIX_REGEXP = '#<((/?)([vh]?box|date(-time[^\s]*|-duration|-since)?|textbox|textarea|button|colorpicker|description|image|url(-email|-phone|-fax)?))(/?|\s[^>]*)>#m';
const ADD_ET2_PREFIX_LAST_GROUP = 6;

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
	$cache = $GLOBALS['egw_info']['server']['temp_dir'].'/egw_cache/eT2-Cache-'.
		$GLOBALS['egw_info']['server']['install_id'].'-'.str_replace('/', '-', $_SERVER['PATH_INFO']);
	if (file_exists($cache) && filemtime($cache) > max(filemtime($path), filemtime(__FILE__)) &&
		($str = file_get_contents($cache)) !== false)
	{
		$cache_read = microtime(true);
	}
	elseif(($str = file_get_contents($path)) !== false)
	{
		// fix <menulist...><menupopup type="select-*"/></menulist> --> <select type="select-*" .../>
		$str = preg_replace('#<menulist([^>]*)>[\r\n\s]*<menupopup([^>]+>)[\r\n\s]*</menulist>#', '<select$1$2', $str);

		// Change splitter dockside -> primary + vertical
		$str = preg_replace_callback('#<split([^>]*?)>(.*)</split>#su', static function ($matches) use ($name) {
			$tag = 'et2-split';
			preg_match_all('/(^| )([a-z0-9_-]+)="([^"]+)"/', $matches[1], $attrs, PREG_PATTERN_ORDER);
			$attrs = array_combine($attrs[2], $attrs[3]);

			$attrs['vertical'] = $attrs['orientation'] === 'h' ? "true" : "false";
			if (str_contains($attrs['dock_side'], 'top') || str_contains($attrs['dock_side'], 'left'))
			{
				$attrs['primary'] = "end";
			}
			elseif (str_contains($attrs['dock_side'], 'bottom') || str_contains($attrs['dock_side'], 'right'))
			{
				$attrs['primary'] = "start";
			}
			unset($attrs['dock_side']);

			return "<$tag " . implode(' ', array_map(function ($name, $value) {
						return $name . '="' . $value . '"';
					}, array_keys($attrs), $attrs)
				) . '>' . $matches[2] . "</$tag>";
		}, $str);

		// ^^^^^^^^^^^^^^^^ above widgets get transformed independent of legacy="true" set in overlay ^^^^^^^^^^^^^^^^^^

		// eTemplate marked as legacy --> replace only some widgets (eg. requiring jQueryUI) with web-components
		if (preg_match('/<overlay[^>]* legacy="true"/', $str))
		{
			$str = preg_replace_callback('#<date(-time[^\s]*|-duration|-since)?\s([^>]+)/>#', static function($matches)
			{
				if ($matches[1] === 'date-time_today') $matches[1] = 'date-time-today';
				return "<et2-date$matches[1] $matches[2]></et2-date$matches[1]>";
			}, $str);
		}
		else
		{
			// fix legacy options, so new client-side has not to deal with them
			$str = preg_replace_callback('#<([^- />]+)(-[^ ]+)?[^>]* (options="([^"]+)")[ />]#', static function ($matches) {
				// take care of (static) type attribute, if used
				if (preg_match('/ type="([a-z-]+)"/', $matches[0], $type))
				{
					str_replace('<' . $matches[1] . $matches[2], '<' . $type[1], $matches[0]);
					str_replace($type[0], '', $matches[0]);
					list($matches[1], $matches[2]) = explode('-', $type[1], 2);
				}
				static $legacy_options = array( // use "ignore" to ignore further comma-sep. values, otherwise they are all in last attribute
					'select' => 'empty_label,ignore',
					'select-account' => 'empty_label,account_type,ignore',
					'box' => ',cellpadding,cellspacing,keep',
					'hbox' => 'cellpadding,cellspacing,keep',
					'vbox' => 'cellpadding,cellspacing,keep',
					'groupbox' => 'cellpadding,cellspacing,keep',
					'checkbox' => 'selected_value,unselected_value,ro_true,ro_false',
					'radio' => 'set_value,ro_true,ro_false',
					'customfields' => 'sub-type,use-private,field-names',
					'date' => 'data_format,ignore', // Legacy option "mode" was never implemented in et2
					'description' => 'bold-italic,link,activate_links,label_for,link_target,link_popup_size,link_title',
					'button' => 'image,ro_image',
					'buttononly' => 'image,ro_image',
				);
				// prefer more specific type-subtype over just type
				$names = $legacy_options[$matches[1] . $matches[2]] ?? $legacy_options[$matches[1]] ?? null;
				if (isset($names))
				{
					$names = explode(',', $names);
					$values = Api\Etemplate\Widget::csv_split($matches[4], count($names));
					if (count($values) < count($names))
					{
						$values = array_merge($values, array_fill(count($values), count($names) - count($values), ''));
					}
					$attrs = array_diff(array_combine($names, $values), ['', null]);
					unset($attrs['ignore']);
					// fix select options can be either multiple or empty_label
					if ($matches[1] === 'select' && !empty($attrs['empty_label']) && (int)$attrs['empty_label'] > 0)
					{
						$attrs['multiple'] = (int)$attrs['empty_label'];
						unset($matches['empty_label']);
					}
					$options = '';
					foreach ($attrs as $attr => $value)
					{
						$options .= $attr . '="' . $value . '" ';
					}
					return str_replace($matches[3], $options, $matches[0]);
				}
				return $matches[0];
			}, $str);

			// fix deprecated attributes: needed, blur, ...
			static $deprecated = [
				'needed' => 'required',
				'blur' => 'placeholder',
			];
			$str = preg_replace_callback('#<[^ ]+[^>]* (' . implode('|', array_keys($deprecated)) . ')="([^"]+)"[ />]#',
				static function ($matches) use ($deprecated) {
					return str_replace($matches[1] . '="', $deprecated[$matches[1]] . '="', $matches[0]);
				}, $str);

			// fix <textbox multiline="true" .../> --> <textarea .../> (et2-prefix and self-closing is handled below)
			$str = preg_replace('#<textbox(.*?)\smultiline="true"(.*?)/>#u', '<textarea$1$2/>', $str);

			// fix <(textbox|int(eger)?|float) precision="int(eger)?|float" .../> --> <et2-number precision=...></et2-number>
			$str = preg_replace_callback('#<(textbox|int(eger)?|float|number).*?\s(type="(int(eger)?|float)")?.*?/>#u',
				static function ($matches) {
					if ($matches[1] === 'textbox' && !in_array($matches[4], ['float', 'int', 'integer'], true))
					{
						return $matches[0]; // regular textbox --> nothing to do
					}
					$type = $matches[1] === 'float' || $matches[4] === 'float' ? 'float' : 'int';
					$tag = str_replace('<' . $matches[1], '<et2-number', substr($matches[0], 0, -2));
					if (!empty($matches[3])) $tag = str_replace($matches[3], '', $tag);
					if ($type !== 'float') $tag .= ' precision="0"';
					return $tag . '></et2-number>';
				}, $str);

			// fix <button(only)?.../> --> <et2-button(-image)? noSubmit="true".../>
			$str = preg_replace_callback('#<button(only)?\s(.*?)/>#u', function ($matches) use ($name) {
				$tag = 'et2-button';
				preg_match_all('/(^| )([a-z0-9_-]+)="([^"]+)"/', $matches[2], $attrs, PREG_PATTERN_ORDER);
				$attrs = array_combine($attrs[2], $attrs[3]);
				// replace buttononly tag with noSubmit="true" attribute
				if (!empty($matches[1]))
				{
					$attrs['noSubmit'] = 'true';
				}
				// novalidation --> noValidation
				if (!empty($attrs['novalidation']) && in_array($attrs['novalidation'], ['true', '1'], true))
				{
					unset($attrs['novalidation']);
					$attrs['noValidation'] = 'true';
				}
				// replace not set background_image attribute with et2-image tag, if not in NM / lists
				if (!empty($attrs['image']) && (empty($attrs['background_image']) || $attrs['background_image'] === 'false') &&
					!preg_match('/^(index|list)/', $name))
				{
					$tag = 'et2-image';
					$attrs['src'] = $attrs['image'];
					unset($attrs['image']);
					// Was expected to submit.  Images don't have noValidation, so add directly
					if (!array_key_exists('onclick', $attrs) && empty($attrs['noSubmit']))
					{
						$attrs['onclick'] = 'this.getInstanceManager().submit(this, undefined, ' . $attrs['noValidation'] . ')';
					}
				}
				unset($attrs['background_image']);
				return "<$tag " . implode(' ', array_map(function ($name, $value) {
						return $name . '="' . $value . '"';
					}, array_keys($attrs), $attrs)) . '></' . $tag . '>';
			}, $str);

			$str = preg_replace_callback(ADD_ET2_PREFIX_REGEXP, static function (array $matches) {
				if ($matches[3] === 'date-time_today') $matches[3] = 'date-time-today';
				return '<' . $matches[2] . 'et2-' . $matches[3] .
					// web-components must not be self-closing (no "<et2-button .../>", but "<et2-button ...></et2-button>")
					(substr($matches[ADD_ET2_PREFIX_LAST_GROUP], -1) === '/' ? substr($matches[ADD_ET2_PREFIX_LAST_GROUP], 0, -1) .
						'></et2-' . $matches[3] : $matches[ADD_ET2_PREFIX_LAST_GROUP]) . '>';
			}, $str);

			// handling of date and partially implemented select widget (no search or tags attribute), incl. removing of type attribute
			$str = preg_replace_callback('#<(select)(-[^ ]+)? ([^>]+)/>#', static function (array $matches) {
				preg_match_all('/(^| )([a-z0-9_-]+)="([^"]+)"/', $matches[3], $attrs, PREG_PATTERN_ORDER);
				$attrs = array_combine($attrs[2], $attrs[3]);

				// add et2-prefix for <date-* and <select-* without search or tags attribute
				if (!isset($attrs['search']) && !isset($attrs['tags']))
				{
					// type attribute need to go in widget type <select type="select-account" --> <et2-select-account
					if (empty($matches[2]) && isset($attrs['type']))
					{
						$matches[1] = $attrs['type'];
						$matches[3] = str_replace('type="' . $attrs['type'] . '"', '', $matches[3]);
					}
					return '<et2-' . $matches[1] . $matches[2] . ' ' . $matches[3] . '></et2-' . $matches[1] . $matches[2] . '>';
				}
				return $matches[0];
			}, $str);
		}
		$processing = microtime(true);

		if (isset($cache) && (file_exists($cache_dir = dirname($cache)) || mkdir($cache_dir, 0755, true) || is_dir($cache_dir)))
		{
			file_put_contents($cache, $str);
		}
	}
	// stop here for not existing file or path-traversal for both file and cache here
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