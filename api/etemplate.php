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

// add et2- prefix to following widgets/tags (box/hbox/vbox/vfs-select)
// customfields sits inside the (/?) group, not beside it, so a paired tag has its closing
// </customfields> rewritten too - matched on its own it would leave the opening converted and the
// closing behind.  "customfields-types" is a different widget and must not be caught, which the
// alternation's explicit -list/-filters takes care of.
const ADD_ET2_PREFIX_REGEXP = '#<((/?)([vh]?box|customfields(?:-list|-filters)?)|vfs-select)(/?|\s[^>]*)>#m';
const ADD_ET2_PREFIX_LAST_GROUP = 4;

// add et2- prefix to this (larger) set of widgets
const ADD_ET2_PREFIX_LEGACY_REGEXP = '#<((/?)(template|tabbox|description|searchbox|textbox|hidden|historylog|toolbar|label|avatar|lavatar|image|appicon|colorpicker|checkbox|file|iframe|url(-email|-phone|-fax)?|vfs-mime|vfs-uid|vfs-gid|vfs-select|vfs-name|vfs-upload|vfs-mode|link|link-[a-z]+|favorites|html|htmlarea|styles))(/?|\s[^>]*)>#m';
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
	// CLI conversion targets the given physical file, NOT a live VFS-customized override of it
	// (relPath() deliberately prefers a mounted /etemplates VFS customization for real serving)
	$path = PHP_SAPI === 'cli' ? Api\Etemplate::rel2path($fspath) :
		Api\Etemplate::rel2path(Api\Etemplate::relPath($app . '.' . basename($name, '.xet'), $template));
	// $app/$template (unlike $name above) are not basename()'d - same path-traversal safety-net
	// filter-template.php already applies for the same reason
	if(empty($path) || strpos($path, '..') !== false || !file_exists($path) || !is_readable($path))
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
	$cache = $GLOBALS['egw_info']['server']['temp_dir'] . '/egw_cache/eT2-Cache-' .
		$GLOBALS['egw_info']['server']['install_id'] . '-' . str_replace('/', '-', $_SERVER['PATH_INFO']) . '-' . filemtime($path);
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
				// calendar's edit.xet uses <historylog options="history_status"/>.  Named here so
				// the attribute arrives as status_id= rather than as a raw options= the web
				// component would ignore; the legacy widget declares status_id as an attribute
				// too, so this is equally correct for either tag.
				'historylog'              => 'status_id',
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

		// Change groupbox <caption label="..."/> --> summary attribute
		$str = preg_replace_callback('#<groupbox([^>]*?)>(.*?)</groupbox>#su', static function ($matches)
		{
			$attrs = parseAttrs($matches[1]);

			if (preg_match('#^\n?\s*<caption([^>]*?)/?>(.*?)(</caption>)?#su', $matches[2], $caption))
			{
				$attrs['summary'] = parseAttrs($caption[1])['label'];
				$matches[2] = str_replace($caption[0], '', $matches[2]);
			}
			return "<et2-groupbox" . stringAttrs($attrs) . '>' . $matches[2] . "</et2-groupbox>";
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
		// the "(?:\s(type=...))?" wrapping - rather than a bare mandatory \s before an optional
		// group - matters: a completely bare, attribute-less <int/>/<integer/>/<float/> has no
		// whitespace at all to match, so a mandatory \s there made the whole pattern never match
		$str = preg_replace_callback('#<(textbox|int(eger)?|float|number|passwd).*?(?:\s(type="(int(eger)?|float|passwd)"))?.*?(/|></textbox)>#',
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
				// don't clobber an explicitly-given precision with the int/integer default
				if ($type !== 'float' && !preg_match('/\sprecision="/', $tag)) $tag .= ' precision="0"';
				return $tag . '></et2-number>';
			}, $str);

		// fix already-converted <et2-textbox type="int(eger)?|float|hidden" .../> -->
		// <et2-number precision=.../> or <et2-hidden .../>
		// (et2-textbox has no styling/behaviour for any of these - it was never meant to be
		// used that way; this is the et2-prefixed equivalent of the type="int(eger)?|float"
		// case handled above, for templates that were hand-written/converted using
		// et2-textbox instead of the bare tag)
		$str = preg_replace_callback('#<et2-textbox([^>]*)></et2-textbox>#', static function (array $matches)
		{
			$attrs = parseAttrs($matches[1]);
			if (empty($attrs['type']) || !in_array($attrs['type'], ['int', 'integer', 'float', 'hidden'], true))
			{
				return $matches[0];
			}
			if ($attrs['type'] === 'hidden')
			{
				unset($attrs['type']);
				return '<et2-hidden'.stringAttrs($attrs).'></et2-hidden>';
			}
			$float = $attrs['type'] === 'float';
			unset($attrs['type']);
			if (!$float) $attrs['precision'] = '0';
			return '<et2-number'.stringAttrs($attrs).'></et2-number>';
		}, $str);

		// strip a redundant type="int(eger)?|float" left over on an already-<et2-number> tag
		// (eg. from a hand-edit that renamed the tag but not the now-meaningless type attribute) -
		// otherwise it wins over the tag name (see Et2Widget.createElementFromNode()) and routes
		// straight back to the legacy et2_number class despite the tag already being et2-number
		$str = preg_replace_callback('#<et2-number([^>]*)></et2-number>#', static function (array $matches)
		{
			$attrs = parseAttrs($matches[1]);
			if (empty($attrs['type']) || !in_array($attrs['type'], ['int', 'integer', 'float'], true))
			{
				return $matches[0];
			}
			$float = $attrs['type'] === 'float';
			unset($attrs['type']);
			if (!$float && !isset($attrs['precision'])) $attrs['precision'] = '0';
			return '<et2-number'.stringAttrs($attrs).'></et2-number>';
		}, $str);

		// replace just description, as they often contain >, like label="> %s"
		$str = preg_replace('#<description\s*/>#', '<et2-description></et2-description>', $str);
		$str = preg_replace('#<description\s(.*?")\s*/>#s', '<et2-description $1></et2-description>', $str);

		// fix <vfs ...(/|></vfs)> --> <et2-vfs-path readonly="true" ...></et2-vfs-path>
		// (bare vfs is a read-only, clickable path breadcrumb bound to row data - same
		// widget et2-vfs-path already implements for its editable use, just forced readonly)
		$str = preg_replace_callback('#<vfs\s(.*?)(/|></vfs)>#s', static function (array $matches)
		{
			$attrs = parseAttrs($matches[1]);
			$attrs['readonly'] = 'true';
			return '<et2-vfs-path'.stringAttrs($attrs).'></et2-vfs-path>';
		}, $str);

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
					$attrs['searchUrl'] = $attrs['autocomplete_url'] ?? 'EGroupware\\Api\\Etemplate\\Widget\\Select::'.
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

		// use et2-email instead of et2-select-email
		$str = preg_replace('#<et2-select-email\s(.*?")\s*/?>(</et2-select-email>)?#s', '<et2-email $1></et2-email>', $str);

		// a sortheader's only legacy option is its default sort direction, the web-component ignores options=
		$str = preg_replace_callback('#<((?:et2-)?nextmatch-sortheader)\s([^>]*?)(\s*/?>)#s', static function (array $matches)
		{
			$attrs = parseAttrs($matches[2]);
			if (!isset($attrs['options']))
			{
				return $matches[0];
			}
			$attrs['sortmode'] = $attrs['sortmode'] ?? $attrs['options'];
			unset($attrs['options']);
			return '<' . $matches[1] . stringAttrs($attrs) . $matches[3];
		}, $str);

		// nextmatch headers
		// replace all filters with NM headers, if not running via cli (as we currently don't want to remove them permanently!)
		// replaceFilters="false" keeps them, eg. for a nextmatch in a popup, which gets no filterbox
		$replace_filters = PHP_SAPI !== 'cli' && !preg_match('/<(et2-)?nextmatch [^>]*replaceFilters="false"/', $str);
		// legacy name --> web-component: nextmatch-header, -filterheader/-taglistheader/-filter, -accountfilter,
		// -customfilter, -entryheader/-entry, the dashed nextmatch-header-* form and the et2- prefixed one
		// (closing tag optional) all give a "kind" of '', filter, account, custom or entry
		$str = preg_replace_callback('#<((et2-)?nextmatch-(account|sort|custom|filter|taglist|entry)?(header(?:-(account|custom|filter|entry))?|filter|entry))\s([^>]*?)\s*(/>|></\1>)#s',
			static function (array $matches) use ($replace_filters)
		{
			$attrs = parseAttrs($matches[6]);
			$kind = $matches[5] ?: $matches[3] ?: ($matches[4] === 'header' ? '' : $matches[4]);
			if ($kind === 'taglist')
			{
				$kind = 'filter';
			}
			if ($kind === 'custom')
			{
				$attrs['widget_type'] = $attrs['type'] ?? null;
			}
			// sortheaders get renamed by convertNextmatch(), et2- prefixed headers are already converted
			if ($kind === 'sort' || !$replace_filters && ($matches[2] || $kind === 'custom' && empty($attrs['widget_type'])))
			{
				return $matches[0];
			}
			// No longer needed & type causes problems
			unset($attrs['type'], $attrs['tags']);

			if ($replace_filters)
			{
				if (empty($attrs['label']))
				{
					$attrs['label'] = $attrs['ariaLabel'] ?? $attrs['emptyLabel'];
					unset($attrs['ariaLabel'], $attrs['emptyLabel']);
				}
				unset($attrs['widget_type'], $attrs['widgetType'], $attrs['class'], $attrs['options']);
				return '<et2-nextmatch-header ' . stringAttrs($attrs) . '/>';
			}
			$tag = 'et2-nextmatch-header' . ($kind ? '-' . $kind : '');
			return '<' . $tag . stringAttrs($attrs) . '></' . $tag . '>';
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

		// replace <tree(-cat)? multiple="..." with <et2-tree(-cat) and fix attributes
		$str = preg_replace_callback('#<tree(-cat)?\s(.*?)\s*/>#s', static function (array $matches)
		{
			$tag = 'et2-tree'.($matches[1] ?? '');
			$attrs = parseAttrs($matches[2]);
			if (!empty($attrs['options']) && (int)$attrs['options'] > 1)
			{
				$attrs['multiple'] = 'true';
			}
			// fix renamed attrs, thought regular under_score to camelCase happens later
			if (!empty($attrs['parent_node'])) $attrs['parentId'] = $attrs['parent_node'];
			unset($attrs['options'], $attrs['parent_node']);
			return "<$tag" . stringAttrs($attrs) . "></$tag>";
		}, $str);

		// replace no longer used <et2-tree-multiple.../> with <et2-tree multiple="true".../>
		$str = preg_replace('#<et2-tree-multiple ([^>]+)(/>|</et2-tree-multiple>)#', '<et2-tree multiple="true" $1></et2-tree>', $str);

		// use et2-select-cat instead of et2-tree-cat - must run after the bare tree(-cat)
		// rewrite above, since that's what actually produces et2-tree-cat in the first place;
		// this used to run before it, so a bare <tree-cat> only reached et2-tree-cat, not
		// et2-select-cat, in a single pass (needed a second conversion pass to fully resolve)
		$str = preg_replace('#<et2-tree-cat\s(.*?")\s*/?>(</et2-tree-cat>)?#s', '<et2-select-cat $1></et2-select-cat>', $str);

		if ($template === 'mobile')
		{
			// Fix add button
			$str = preg_replace_callback('#<et2-button\s(.*?")\s*/?>(</et2-button>)?#s', function (array $matches)
			{
				$attrs = parseAttrs($matches[1]);
				if(str_contains($attrs['class'], 'plus_button'))
				{
					$attrs['image'] = 'plus-lg';
					$attrs_string = stringAttrs($attrs);
					return "<et2-button-icon $attrs_string></et2-button-icon>";
				}
				return "<et2-button $matches[1]>$matches[2]";
			}, $str);
		}

		// wrap et2-textarea and htmlarea in et2-ai, if not already done or noAiTools attribute is set truish
		// nextmatch rows (ids bound to the row: ${row}[...], $row_cont[...]) are never wrapped, a template can still
		// wrap them explicitly with <et2-ai>
		$str = preg_replace_callback('#(<et2-ai[^>]*>\n?)?\s*<(et2-textarea|et2-htmlarea|htmlarea)\s(.*?)\s*/?>(</\2>)?#s', function ($matches)
		{
			$attrs = parseAttrs($matches[3]);
			$noAiTools = $attrs['noAiTools'] ?? 'false';
			if (!empty($matches[1]) || $noAiTools && $noAiTools !== 'false' ||
				preg_match('/^\$(\{row\}|row_cont\b|row\b)/', $attrs['id'] ?? ''))
			{
				return $matches[0];
			}
			if($attrs['span'])
			{
				// Move span to et2-ai
				$span = ' span="' . $attrs['span'] . '"';
				unset($attrs['span']);
			}
			// remove height, so widget takes full height inside et2-ai
			unset($attrs['height']);
			$attrs = stringAttrs($attrs);
			$tag = str_starts_with($matches[2], 'et2-') ? $matches[2] : 'et2-'.$matches[2];
			return "<et2-ai{$span}><$tag $attrs></$tag></et2-ai>";
		}, $str);

		// box/hbox/vbox/vfs-select --> et2-box/et2-hbox/et2-vbox/et2-vfs-select
		// (used to be skipped for <overlay legacy="true">, an escape hatch no template needs any more -
		// api/templates/default/show_replacements.xet was the last one, see widget-migration-status.md)
		$str = preg_replace_callback(ADD_ET2_PREFIX_REGEXP, static function (array $matches) {
			return '<' . $matches[2] . 'et2-' . $matches[3] .
				// web-components must not be self-closing (no "<et2-button .../>", but "<et2-button ...></et2-button>")
				(substr($matches[ADD_ET2_PREFIX_LAST_GROUP], -1) === '/' ? substr($matches[ADD_ET2_PREFIX_LAST_GROUP], 0, -1) .
					'></et2-' . $matches[3] : $matches[ADD_ET2_PREFIX_LAST_GROUP]) . '>';
		}, $str);

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

			// Legacy htmlarea used positive booleans for toolbar / menubar / statusbar, while
			// the web component uses inverse `no*` booleans. Translate only plain
			// boolean values and leave string toolbar/menu configuration intact.
			if ($matches[1] === 'et2' && $matches[2] === 'htmlarea')
			{
				if (isset($attrs['toolbar']) && in_array($attrs['toolbar'], ['true', 'false'], true))
				{
					$attrs['noToolbar'] = $attrs['toolbar'] === 'true' ? 'false' : 'true';
					unset($attrs['toolbar']);
				}
				if (isset($attrs['menubar']) && in_array($attrs['menubar'], ['true', 'false'], true))
				{
					$attrs['noMenubar'] = $attrs['menubar'] === 'true' ? 'false' : 'true';
					unset($attrs['menubar']);
				}
				if(isset($attrs['statusbar']) && in_array($attrs['statusbar'], ['true', 'false'], true))
				{
					$attrs['noStatusbar'] = $attrs['statusbar'] === 'true' ? 'false' : 'true';
					unset($attrs['statusbar']);
				}
				if (isset($attrs['expandToolbar']) && in_array($attrs['expandToolbar'], ['true', 'false'], true))
				{
					$attrs['toolbarMode'] = 'wrap';
					unset($attrs['expandToolbar']);
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

		// legacy <nextmatch> --> <et2-nextmatch>, unless it opts out with legacy="true"
		$str = convertNextmatch($str, $fspath);

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
 * Convert legacy <nextmatch> widgets and their row templates to <et2-nextmatch>
 *
 * Runs last, on the otherwise fully converted template, so apps nobody hand-converted (eg. customer
 * customisations) still get a working list.  It only makes the template changes every hand conversion
 * made; app JS written against the legacy widget (nm.controller, nm_action(), ...) is not touched and
 * can still fail on first use.  Like <historylog>, only the copy sent to the client changes: the server
 * keeps parsing the raw file and so keeps processing the row templates the legacy way, which is what
 * provides eg. the customfields data the rows need.
 *
 * <nextmatch legacy="true"> keeps the legacy widget, together with the row template it references.
 *
 * What gets converted:
 * - the nextmatch itself: options= becomes template=, header_left/header_row become a template in its
 *   "header" slot, header_right a sibling template in the "main-header" slot
 * - its row template(s): the first row becomes the header row (class="th"), the legacy
 *   sortheader/customfields headers get renamed, bare $field row classes become $row_cont[field],
 *   and options= left on a row widget is removed, as a web-component can not take it
 * - a grid nested in a header or row cell becomes an et2-vbox of et2-hboxes, as the datagrid can only
 *   render web-components; row/column disabled= and a row class= carry over, column alignment is lost
 *
 * A repeating grid (an id containing $row, one row per entry of an array in the row data) can not be
 * expressed this way: it is left alone, with a comment in the template and a note in the error log, and the
 * datagrid warns about it in the browser console.
 *
 * @param string $str template xml
 * @param string $path template path, for the error log
 * @return string
 */
function convertNextmatch(string $str, string $path) : string
{
	// only a legacy nextmatch, or a separate row template still using legacy headers, needs converting
	if (!preg_match('#<nextmatch[\s/>]|<nextmatch-(sortheader|customfields)[\s/>]#', $str))
	{
		return $str;
	}
	$dom = new DOMDocument();
	$use_errors = libxml_use_internal_errors(true);
	$loaded = $dom->loadXML($str, LIBXML_NONET);
	libxml_clear_errors();
	libxml_use_internal_errors($use_errors);
	if (!$loaded)
	{
		error_log(__FUNCTION__."() $path: can not parse template, nextmatch NOT converted");
		return $str;
	}
	$xpath = new DOMXPath($dom);
	$warnings = [];
	$row_templates = $legacy_templates = [];

	foreach (iterator_to_array($xpath->query('//nextmatch')) as $nm)
	{
		$template = $nm->getAttribute('template') ?: $nm->getAttribute('options');
		if ($nm->getAttribute('legacy') === 'true')
		{
			$nm->removeAttribute('legacy');
			$legacy_templates[$template] = true;
			continue;
		}
		$row_templates[$template] = true;
		convertNextmatchWidget($nm, $template);
	}

	foreach (iterator_to_array($xpath->query('//template[@id]|//et2-template[@id]')) as $tpl)
	{
		$id = $tpl->getAttribute('id');
		if (isset($legacy_templates[$id]) || !isset($row_templates[$id]) &&
			!$xpath->query('.//nextmatch-sortheader|.//nextmatch-customfields', $tpl)->length)
		{
			continue;
		}
		convertNextmatchRowTemplate($tpl, $xpath, $warnings);
	}

	if ($warnings)
	{
		error_log(__FUNCTION__."() $path: ".implode("\n", $warnings));
	}
	// empty web-components must not be self-closing, everything else keeps its short form
	return preg_replace('#<((?!et2-)[a-zA-Z][\w.:-]*)(\s[^<>]*)?></\1>#', '<$1$2/>',
		$dom->saveXML(null, LIBXML_NOEMPTYTAG));
}

/**
 * Turn one legacy <nextmatch> element into <et2-nextmatch>
 *
 * @param DOMElement $nm
 * @param string $template row template
 */
function convertNextmatchWidget(DOMElement $nm, string $template) : void
{
	$dom = $nm->ownerDocument;
	$et2 = $dom->createElement('et2-nextmatch');
	foreach (iterator_to_array($nm->attributes) as $attr)
	{
		switch ($attr->name)
		{
			case 'template':
			case 'options':
			case 'no_dynheight':    // no longer needed
			case 'disable_selection_advance':    // no equivalent, the next row is not selected
				break;
			case 'header_left':
			case 'header_row':
				// the header slot is inside the nextmatch's namespace, as the legacy header templates were
				$header = $dom->createElement('et2-template');
				$header->setAttribute('id', $attr->value);
				$header->setAttribute('slot', 'header');
				$et2->appendChild($header);
				break;
			case 'header_right':
				$header = $dom->createElement('et2-template');
				$header->setAttribute('template', $attr->value);
				$header->setAttribute('slot', 'main-header');
				$nm->parentNode->insertBefore($header, $nm);
				break;
			default:
				$et2->setAttribute(camelCaseAttr($attr->name), $attr->value);
		}
	}
	if ($template !== '')
	{
		$et2->setAttribute('template', $template);
	}
	$nm->parentNode->replaceChild($et2, $nm);
}

/**
 * Make a legacy nextmatch row template usable by the et2-nextmatch datagrid
 *
 * @param DOMElement $tpl <template> or <et2-template>
 * @param DOMXPath $xpath
 * @param string[] &$warnings
 */
function convertNextmatchRowTemplate(DOMElement $tpl, DOMXPath $xpath, array &$warnings) : void
{
	$id = $tpl->getAttribute('id');
	if (($grid = $xpath->query('(.//grid)[1]', $tpl)->item(0)))
	{
		// nested grids, innermost first, so an outer one is converted with its content already done
		foreach (array_reverse(iterator_to_array($xpath->query('./rows/row//grid', $grid))) as $nested)
		{
			if (!convertNestedGrid($nested, $xpath))
			{
				$message = "row template '$id': repeating grid id=\"{$nested->getAttribute('id')}\" can not be converted, its cell stays empty";
				$warnings[] = $message;
				$nested->parentNode->insertBefore($tpl->ownerDocument->createComment(' '.$message.' '), $nested);
			}
		}
		$rows = iterator_to_array($xpath->query('./rows/row', $grid));
		// the datagrid finds the header row by its class, the legacy widget just took the first row
		if (count($rows) > 1 && !preg_match('/(^|\s)th(\s|$)/', $rows[0]->getAttribute('class')))
		{
			$rows[0]->setAttribute('class', trim('th '.$rows[0]->getAttribute('class')));
		}
		foreach (array_slice($rows, 1) as $row)
		{
			// on a row, only the $row_cont[field] form resolves, a bare $field is used literally
			if (($class = $row->getAttribute('class')) !== '')
			{
				$row->setAttribute('class', preg_replace('/(^|\s)\$(?!row(_cont)?\b)([a-z_][a-z0-9_]*)(?=\s|$)/i',
					'$1$row_cont[$3]', $class));
			}
			foreach (iterator_to_array($xpath->query('.//*[@options]', $row)) as $widget)
			{
				$warnings[] = "row template '$id': removed options=\"{$widget->getAttribute('options')}\" from <$widget->tagName>";
				$widget->removeAttribute('options');
			}
		}
	}
	foreach (iterator_to_array($xpath->query('.//nextmatch-sortheader|.//nextmatch-customfields', $tpl)) as $header)
	{
		renameElement($header, $header->tagName === 'nextmatch-customfields' ?
			'et2-nextmatch-header-customfields' : 'et2-nextmatch-sortheader');
	}
}

/**
 * Replace a grid nested in a row template cell with et2-vbox/et2-hbox
 *
 * Each grid row becomes an et2-hbox, carrying the row's disabled= and class=, a column's disabled= goes
 * onto each of its cells.  A grid with a single, plain row becomes a single et2-hbox.
 *
 * @param DOMElement $grid
 * @param DOMXPath $xpath
 * @return bool false if it is a repeating grid, which can not be converted
 */
function convertNestedGrid(DOMElement $grid, DOMXPath $xpath) : bool
{
	if (str_contains($grid->getAttribute('id'), '$'))
	{
		return false;
	}
	$dom = $grid->ownerDocument;
	$columns_disabled = array_map(static fn(DOMElement $column) => $column->getAttribute('disabled'),
		iterator_to_array($xpath->query('./columns/column', $grid)));
	$rows = iterator_to_array($xpath->query('./rows/row', $grid));
	$cells = array_map(static fn(DOMElement $row) => iterator_to_array($xpath->query('./*', $row)), $rows);
	$multi_column = count($columns_disabled) > 1 || max(array_map('count', $cells) ?: [0]) > 1;
	$single_row = count($rows) === 1 && !$rows[0]->hasAttribute('disabled') && !$rows[0]->hasAttribute('class');

	$box = $dom->createElement($single_row ? 'et2-hbox' : 'et2-vbox');
	foreach (iterator_to_array($grid->attributes) as $attr)
	{
		// layout attributes of the grid have no meaning for a box
		if (!in_array($attr->name, ['width', 'height', 'spacing', 'padding', 'border', 'resize_ratio'], true))
		{
			$box->setAttribute(camelCaseAttr($attr->name), $attr->value);
		}
	}
	foreach ($rows as $n => $row)
	{
		$parent = $box;
		if (!$single_row && ($multi_column && count($cells[$n]) > 1 || $row->hasAttribute('disabled') || $row->hasAttribute('class')))
		{
			$parent = $box->appendChild($dom->createElement('et2-hbox'));
			foreach (['disabled', 'class'] as $name)
			{
				if ($row->hasAttribute($name)) $parent->setAttribute($name, $row->getAttribute($name));
			}
		}
		$column = 0;
		foreach ($cells[$n] as $cell)
		{
			$span = $cell->getAttribute('span');
			$cell->removeAttribute('span');
			if (!empty($disabled = $columns_disabled[$column] ?? ''))
			{
				if (!$cell->hasAttribute('disabled'))
				{
					$cell->setAttribute('disabled', $disabled);
				}
				else
				{
					// a widget has only one disabled= expression, so the column's goes on a wrapper
					$wrapper = $dom->createElement('et2-hbox');
					$wrapper->setAttribute('disabled', $disabled);
					$wrapper->appendChild($cell);
					$cell = $wrapper;
				}
			}
			$parent->appendChild($cell);
			$column = $span === 'all' ? count($columns_disabled) : $column + max(1, (int)$span);
		}
	}
	$grid->parentNode->replaceChild($box, $grid);
	return true;
}

/**
 * Rename an element, keeping its attributes (camelCased for a web-component) and children
 *
 * @param DOMElement $element
 * @param string $name new tag name
 * @return DOMElement the new element
 */
function renameElement(DOMElement $element, string $name) : DOMElement
{
	$new = $element->ownerDocument->createElement($name);
	foreach (iterator_to_array($element->attributes) as $attr)
	{
		$new->setAttribute(str_starts_with($name, 'et2-') ? camelCaseAttr($attr->name) : $attr->name, $attr->value);
	}
	while ($element->firstChild)
	{
		$new->appendChild($element->firstChild);
	}
	$element->parentNode->replaceChild($new, $element);
	return $new;
}

/**
 * Web-component attribute name for a legacy one, eg. "no_lang" --> "noLang"
 *
 * The same renames the web-component attribute pass in send_template() does, for elements created after it ran.
 *
 * @param string $name
 * @return string
 */
function camelCaseAttr(string $name) : string
{
	static $deprecated = [
		'needed' => 'required',
		'blur' => 'placeholder',
	];
	$name = $deprecated[$name] ?? $name;
	if (count($parts = preg_split('/[_-]/', $name)) > 1)
	{
		if ($name === 'parent_node') $parts[1] = 'Id';  // we can not use DOM property parentNode --> parentId
		$name = array_shift($parts).implode('', array_map('ucfirst', $parts));
	}
	return $name;
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