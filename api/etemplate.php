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

// add et2- prefix to following widgets/tags, if NO <overlay legacy="true"
const ADD_ET2_PREFIX_REGEXP = '#<((/?)([vh]?box))(/?|\s[^>]*)>#m';
const ADD_ET2_PREFIX_LAST_GROUP = 4;

// unconditional of legacy add et2- prefix to this widgets
const ADD_ET2_PREFIX_LEGACY_REGEXP = '#<((/?)(tabbox|description|searchbox|textbox|label|avatar|lavatar|image|appicon|colorpicker|checkbox|url(-email|-phone|-fax)?|vfs-mime|vfs-uid|vfs-gid|link|link-[a-z]+|favorites))(/?|\s[^>]*)>#m';
const ADD_ET2_PREFIX_LEGACY_LAST_GROUP = 5;

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
	error_log("Usage: $prog [(-i|--in-place)] <xet-file>\n");
	error_log("\t convert <xet-file> to new syntax and output or replace it in-place.\n\n");
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
				case '-i':
				case '--in-place':
					$in_place = true;
					break;
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
	$cache = $GLOBALS['egw_info']['server']['temp_dir'].'/egw_cache/eT2-Cache-'.
		$GLOBALS['egw_info']['server']['install_id'].'-'.str_replace('/', '-', $_SERVER['PATH_INFO']);
	if (PHP_SAPI !== 'cli' && file_exists($cache) && filemtime($cache) > max(filemtime($path), filemtime(__FILE__)) &&
		($str = file_get_contents($cache)) !== false)
	{
		$cache_read = microtime(true);
	}
	elseif(($str = file_get_contents($path)) !== false)
	{
		// replace single quote enclosing attribute values with double quotes
		$str = preg_replace_callback("#([a-z_-]+)='([^']*)'([ />])#i", static function($matches){
			return $matches[1].'="'.str_replace('"', '&quot;', $matches[2]).'"'.$matches[3];
		}, $str);

		// fix <menulist...><menupopup type="select-*"/></menulist> --> <select type="select-*" .../>
		$str = preg_replace('#<menulist([^>]*)>[\r\n\s]*(<!--[^>]+-->[\r\n\s]*)?<menupopup([^>]+>)[\r\n\s]*</menulist>#', '$2<select$1$3', $str);
		$str = preg_replace('#<menupopup([^>]*)>#', '<select$1>', $str);

		// fix legacy options, so new client-side has not to deal with them
		$str = preg_replace_callback('#<([^- />]+)(-[^ ]+)?[^>]* (options="([^"]+)")[ />]#', static function ($matches) {
			// take care of (static) type attribute, if used
			if (preg_match('/ type="([a-z-]+)"/', $matches[0], $type))
			{
				str_replace('<' . $matches[1] . $matches[2], '<' . $type[1], $matches[0]);
				str_replace($type[0], '', $matches[0]);
				list($matches[1], $matches[2]) = explode('-', $type[1], 2);
				if (!empty($matches[2])) $matches[2] = '-'.$matches[2];
			}
			static $legacy_options = array(
				// use "ignore" to ignore further comma-sep. values, otherwise they are all in last attribute
				'select'                  => 'empty_label,ignore',
				'select-account'          => 'empty_label,account_type,ignore',
				'select-number'           => 'empty_label,min,max,interval,suffix',
				'select-cat'              => 'empty_label,global_categories,ignore,application,parentCat,owner',
				'box'                     => ',cellpadding,cellspacing,keep',
				'hbox'                    => 'cellpadding,cellspacing,keep',
				'vbox'                    => 'cellpadding,cellspacing,keep',
				'groupbox'                => 'cellpadding,cellspacing,keep',
				'checkbox'                => 'selected_value,unselected_value,ro_true,ro_false',
				'radio'                   => 'set_value,ro_true,ro_false',
				'customfields'            => 'type_filter,private,fields',
				'date'                    => 'data_format,ignore',
				// Legacy option "mode" was never implemented in et2
				'description'             => 'bold-italic,link,activate_links,label_for,link_target,link_popup_size,link_title',
				'button'                  => 'image,ro_image',
				'buttononly'              => 'image,ro_image',
				'link'                    => 'app',
				'link-entry'              => 'only_app,application_list',
				'nextmatch-filterheader'  => 'empty_label',
				// disable legacy-options conversation for nm-customfilter as it breaks infolog and addressbook index templates
				//'nextmatch-customfilter'  => 'widget_type,widget_options',
				'nextmatch-accountfilter' => 'empty_label,account_type,ignore',
			);
			// prefer more specific type-subtype over just type
			$names = $legacy_options[$matches[1] . $matches[2]] ?? $legacy_options[trim($matches[1])] ?? null;
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

		// Change details title --> summary (This can currently not kope with nested details like in smallpart.curse.xet
		$str = preg_replace_callback('#<details([^>]*?)>(.*?)</details>#su', static function ($matches)
		{
			$attrs = parseAttrs($matches[1]);
			if (isset($attrs['title']) && !isset($attrs['summary']))
			{
				$attrs['summary'] = $attrs['title'];
				unset($attrs['title']);
			}
			return "<et2-details" . stringAttrs($attrs) . '>' . $matches[2] . "</et2-details>";
		}, $str);

		// Change splitter dockside -> primary + vertical
		$str = preg_replace_callback('#<split([^>]*?)>(.*?)</split>#su', static function ($matches)
		{
			$tag = 'et2-split';
			$attrs = parseAttrs($matches[1]);

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

			return "<$tag" . stringAttrs($attrs) . '>' . $matches[2] . "</$tag>";
		}, $str);

		// modify <(image|description) expose_view="true" --> <et2-*-expose
		$str = preg_replace('/<(image|description)\s([^><]*)expose_view="true"\s([^><]*)\\/>/',
			'<et2-$1-expose $2 $3></et2-$1-expose>', $str);

		// fix <textbox multiline="true" or rows="..." --> <et2-textarea .../>
		$str = preg_replace('#<textbox(.*?)\smultiline="true"(.*?)/>#', '<et2-textarea$1$2></et2-textarea>', $str);
		$str = preg_replace('#<textbox(.*?\srows="\d+".*?)/>#', '<et2-textarea$1></et2-textarea>', $str);

		// fix <(textbox|int(eger)?|float|passwd) precision="int(eger)?|float|passwd" .../> --> <et2-number precision=.../>, <et2-password .../> or <et2-textbox .../>
		$str = preg_replace_callback('#<(textbox|int(eger)?|float|number|passwd).*?\s(type="(int(eger)?|float|passwd)")?.*?(/|></textbox)>#',
			static function ($matches)
			{
				if ($matches[1] === 'passwd' || $matches['4'] === 'passwd')
				{
					return '<et2-password'.str_replace('type="passwd"', '',
						substr($matches[0], 1+strlen($matches[1]), -strlen($matches[6])-1)).'></et2-password>';
				}
				if ($matches[1] === 'textbox' && !in_array($matches[4], ['float', 'int', 'integer'], true))
				{
					return '<et2-'.substr($matches[0], 1, -strlen($matches[6])-1).'></et2-textbox>'; // regular textbox --> nothing to do
				}
				$type = $matches[1] === 'float' || $matches[4] === 'float' ? 'float' : 'int';
				$tag = str_replace('<' . $matches[1], '<et2-number', substr($matches[0], 0, -2));
				if (!empty($matches[3])) $tag = str_replace($matches[3], '', $tag);
				if ($type !== 'float') $tag .= ' precision="0"';
				return $tag . '></et2-number>';
			}, $str);

		// replace just description, as they often contain >, like label="> %s"
		$str = preg_replace('#<description\s*/>#', '<et2-description></et2-description>', $str);
		$str = preg_replace('#<description\s(.*?")\s*/>#s', '<et2-description $1></et2-description>', $str);

		// modify <(vfs-mime|link-string|link-list) --> <et2-*
		$str = preg_replace_callback(ADD_ET2_PREFIX_LEGACY_REGEXP, static function (array $matches) {
			return '<' . $matches[2] . 'et2-' . $matches[3] .
				// web-components must not be self-closing (no "<et2-button .../>", but "<et2-button ...></et2-button>")
				(substr($matches[ADD_ET2_PREFIX_LEGACY_LAST_GROUP], -1) === '/' ? substr($matches[ADD_ET2_PREFIX_LEGACY_LAST_GROUP], 0, -1) .
					'></et2-' . $matches[3] : $matches[ADD_ET2_PREFIX_LEGACY_LAST_GROUP]) . '>';
		}, $str);

		// change link attribute only_app to et2-link attribute app and map r/o link-entry to link
		$str = preg_replace_callback('#<et2-link(-[a-z]+)?([^>]*?)></et2-link(-[a-z]+)?>#su', static function ($matches)
		{
			$tag = 'et2-link'.$matches[1];
			$attrs = parseAttrs($matches[2]);

			if ($tag === 'et2-link-entry' && !empty($attrs['readonly']) || $tag === 'et2-link')
			{
				$tag = 'et2-link';
				$attrs['app'] = $attrs['app'] ?? $attrs['only_app'];
				unset($attrs['only_app'], $attrs['readonly']);
			}
			return "<$tag" . stringAttrs($attrs) . "></$tag>";
		}, $str);

		// handling of select and taglist widget, incl. removing of type attribute
		$str = preg_replace_callback('#<(select|taglist|listbox)(-[^ ]+)? ([^>]+?)(/|>(.*?)</(select|taglist|listbox))>#s', static function (array $matches)
		{
			$attrs = parseAttrs($matches[3]);

			// ignore tags for select-country, it was never used to get multiple countries
			if (isset($attrs['tags']) && ($attrs['type'] === 'select-country' || str_starts_with($matches[0], '<select-country')))
			{
				unset($attrs['tags']);
			}

			// set multiple for old tags attribute or taglist without maxSelection="1"
			if (isset($attrs['tags']) || $matches['1'] === 'taglist' && (empty($attrs['maxSelection']) || $attrs['maxSelection'] > 1))
			{
				$attrs['multiple'] = 'true';
				unset($attrs['tags']);
			}
			// converting taglist to et2-select
			if($matches['1'] === 'taglist')
			{
				// taglist had allowFreeEntries and enableEditMode with a default of true, while et2-select has it with a default of false
				if(!$matches[2] && !isset($attrs['allowFreeEntries']) && (empty($matches[5]) || !preg_match('#</?option(\s[^>]+|/)>#', $matches[5])))
				{
					$attrs['allowFreeEntries'] = 'true';

					if(!isset($attrs['editModeEnabled']))
					{
						$attrs['editModeEnabled'] = 'true';
					}
				}
				// only set (default) searchUrl for regular taglist or taglist-email, or if a non-empty autocomplete_url was given
				if (empty($matches['2']) || $matches[2] === '-email' || !empty($attrs['autocomplete_url']))
				{
					$attrs['searchUrl'] = $attrs['autocomplete_url'] ?? 'EGroupware\\Api\\Etemplate\\Widget\\Taglist::'.
						($matches[2] === '-email' ? 'ajax_email' : 'ajax_search');

					if (isset($attrs['autocomplete_params']))
					{
						$attrs['searchOptions'] = $attrs['autocomplete_params'];
					}
				}
				unset($attrs['autocomplete_url'], $attrs['autocomplete_params']);
				if (isset($attrs['maxSelection']) && $attrs['maxSelection'] === '1')
				{
					unset($attrs['multiple'], $attrs['maxSelection']);
				}
			}
			// no multiple="toggle" or expand_multiple_rows="N" currently, thought Shoelace's select multiple="true" is relative close
			// until we find something better, just switch to multiple="true"
			if (isset($attrs['multiple']) && $attrs['multiple'] === 'toggle' || !empty($attrs['expand_multiple_rows']))
			{
				$attrs['multiple'] = 'true';
				unset($attrs['expand_multiple_rows']);
			}
			// <select rows="N" (to show N rows) previously also switched multiple on
			if (!empty($attrs['rows']) && (int)$attrs['rows'] > 1)
			{
				$attrs['multiple'] = true;
			}
			else
			{
				unset($attrs['rows']);
			}
			// automatic convert empty_label for multiple=true to a placeholder
			if (!empty($attrs['empty_label']) && !empty($attrs['multiple']))
			{
				$attrs['placeholder'] = $attrs['empty_label'];
				unset($attrs['empty_label']);
			}
			// type attribute need to go in widget type <select type="select-account" --> <et2-select-account
			if (empty($matches[2]) && isset($attrs['type']))
			{
				$matches[2] = preg_replace('/^(select|taglist)/', '', $attrs['type']);
				unset($attrs['type']);
			}
			return '<et2-select' . $matches[2] . stringAttrs($attrs) . '>'.$matches[5].'</et2-select' . $matches[2] . '>';
		}, $str);

		// nextmatch headers
		$str = preg_replace_callback('#<(nextmatch-)([^ ]+)(header|filter) ([^>]+?)/>#s', static function (array $matches)
		{
			$attrs = parseAttrs($matches[4]);

			if ($matches[2] === 'custom')
			{
				$attrs['widget_type'] = $attrs['type'];
			}
			if(!$matches[2] || in_array($matches[2], ['sort']) || ($matches[2] == "custom" && empty($attrs['widget_type'])))
			{
				return $matches[0];
			}
			// No longer needed & type causes problems
			unset($attrs['type'], $attrs['tags']);

			if($matches[2] === 'taglist')
			{
				$matches[2] = "filter";
			}

			return '<et2-nextmatch-header-' . $matches[2] . stringAttrs($attrs) . '/>';
		}, $str);

		// fix <(button|buttononly|timestamper).../> --> <et2-(button|image|button-timestamp) (noSubmit="true")?.../>
		$str = preg_replace_callback('#<(button|buttononly|timestamper|button-timestamp|dropdown_button)\s(.*?)(/|></(button|buttononly|timestamper|button-timestamp|dropdown_button))>#s', function ($matches) use ($name)
		{
			$tag = 'et2-button';
			$attrs = parseAttrs($matches[2]);
			switch ($matches[1])
			{
				case 'buttononly':	// replace buttononly tag with noSubmit="true" attribute
				$attrs['noSubmit'] = 'true';
					break;
				case 'timestamper':
				case 'button-timestamp':
					$tag .= '-timestamp';
					$attrs['background_image'] = 'true';
					break;
				case 'dropdown_button':
					$tag = 'et2-dropdown-button';
					break;
			}
			// novalidation --> noValidation
			if (!empty($attrs['novalidation']) && in_array($attrs['novalidation'], ['true', '1'], true))
			{
				unset($attrs['novalidation']);
				$attrs['noValidation'] = 'true';
			}
			// replace not set background_image attribute with et2-button-icon tag, if not in NM / lists
			if (!empty($attrs['image']) && (empty($attrs['background_image']) || $attrs['background_image'] === 'false') &&
				empty($attrs['label']) && !preg_match('/^(index|list)/', $name))
			{
				$tag = 'et2-button-icon';
			}
			unset($attrs['background_image']);
			return "<$tag" . stringAttrs($attrs) . '></' . $tag . '>';
		}, $str);

		$str = preg_replace('#<time_or_date\s([^>]+)/>#', '<et2-date-time-today $1></et2-date-time-today>', $str);
		$str = preg_replace_callback('#<date(-time[^\s]*|-duration|-since)?\s([^>]+)/>#', static function($matches)
		{
			if ($matches[1] === '-time_today') $matches[1] = '-time-today';
			return "<et2-date$matches[1] $matches[2]></et2-date$matches[1]>";
		}, $str);

		if ($template === 'mobile')
		{
		}

		// ^^^^^^^^^^^^^^^^ above widgets get transformed independent of legacy="true" set in overlay ^^^^^^^^^^^^^^^^^^

		// eTemplate marked as legacy --> replace only some widgets (eg. requiring jQueryUI) with web-components
		if (!preg_match('/<overlay[^>]* legacy="true"/', $str))
		{
			$str = preg_replace_callback(ADD_ET2_PREFIX_REGEXP, static function (array $matches) {
				return '<' . $matches[2] . 'et2-' . $matches[3] .
					// web-components must not be self-closing (no "<et2-button .../>", but "<et2-button ...></et2-button>")
					(substr($matches[ADD_ET2_PREFIX_LAST_GROUP], -1) === '/' ? substr($matches[ADD_ET2_PREFIX_LAST_GROUP], 0, -1) .
						'></et2-' . $matches[3] : $matches[ADD_ET2_PREFIX_LAST_GROUP]) . '>';
			}, $str);
		}

		// change all attribute-names of new et2-* widgets to camelCase, and other attribute modifications for all web-components
		$str = preg_replace_callback('#<(et2|records)-([a-z-]+)\s(.*?")\s*/?>#s', static function(array $matches)
		{
			$attrs = parseAttrs($matches[3]);

			// fix deprecated attributes: needed, blur, ...
			static $deprecated = [
				'needed' => 'required',
				'blur' => 'placeholder',
			];
			foreach($attrs as $name => $value)
			{
				if (isset($deprecated[$name]))
				{
					unset($attrs[$name]);
					$attrs[$name = $deprecated[$name]] = $value;
				}
				if (count($parts = preg_split('/[_-]/', $name)) > 1)
				{
					if ($name === 'parent_node') $parts[1] = 'Id';  // we can not use DOM property parentNode --> parentId
					$attrs[array_shift($parts).implode('', array_map('ucfirst', $parts))] = $value;
					unset($attrs[$name]);
				}
			}

			// remove no longer necessary et2_fullWidth class, it's the default now anyway
			if (isset($attrs['class']) && empty($attrs['class'] = trim(preg_replace('/(^| )et2_fullWidth( |$)/', ' ', $attrs['class']))))
			{
				unset($attrs['class']);
			}

			// Drop all (old) size attributes of input like fields, if it's not shoelace size format: small, medium or large
			if (preg_match('/^<et2-(textbox|number|int|float|password|url|vfs-|input)/', $matches[0]) &&
				isset($attrs['size']) && !in_array($attrs['size'], ['small', 'medium', 'large']))
			{
				unset($attrs['size']);
			}

			return str_replace($matches[3], stringAttrs($attrs).(substr($matches[3], -1) === '/' ? '/' : ''), $matches[0]);
		}, $str);

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

	// remove old CSV Id
	$str = trim(str_replace("<!-- \$Id$ -->\n", '', $str))."\n";

	// replace DTD
	$str = preg_replace('/^<!DOCTYPE.*>$/m',
		'<!DOCTYPE overlay PUBLIC "-//EGroupware GmbH//eTemplate 2.0//EN" "https://www.egroupware.org/etemplate2.0.dtd">', $str);

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
	return ' '.implode(' ', array_map(static function ($name, $value) {
		return $name . '="' . $value . '"';
	}, array_keys($attrs), $attrs));
}