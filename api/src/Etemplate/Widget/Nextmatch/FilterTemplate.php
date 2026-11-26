<?php
/**
 * EGroupware - eTemplate serverside: filter template of a nextmatch generated from its row template
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 */

namespace EGroupware\Api\Etemplate\Widget\Nextmatch;

use EGroupware\Api;
use EGroupware\Api\Etemplate\Widget\Template;
use XMLReader;

/**
 * Filter template of a nextmatch, which has none of its own
 *
 * It is generated from the template file of the rows: a search, the columns to sort by, the
 * standard filters (filter, filter2 and cat_id) and a widget for each filter-header of the columns.
 *
 * api/filter-template.php sends it to the client to show the filters, and the nextmatch uses the
 * widgets in it to validate the filters the client sends. Both use this class, so what is validated
 * is what is shown.
 */
class FilterTemplate
{
	/**
	 * Generate the filter template from a template file
	 *
	 * @param string $app application of the template file
	 * @param string $name name of the template file without extension, eg. "index"
	 * @param string $path path of the template file
	 * @param array $params parameters of the url the template is requested with:
	 *	- template: name of the template in the file to use, all of the file otherwise
	 *	- filter, filter2, cat_id: label of the standard filter, which is only added if given
	 *	- (filter|filter2|cat_id)_(widget|no_lang|statustext|placeholder|onchange): widget and attributes of it
	 *	- cat_is_select: use a selectbox for cat_id
	 *	- header_type[$id]: type the application gave a filter-header for the request
	 *	- favorites: add the favorites
	 * @param ?string $path_info path of the template file in the url, eg. "/infolog/templates/default/index.xet"
	 * @return string|null xet of the filter template, null if the file can not be read
	 */
	public static function generate(string $app, string $name, string $path, array $params=[], ?string $path_info=null) : ?string
	{
		// make sure to not allow path traversal or other funny business
		if (isset($params['template']) && !preg_match('/^[a-z0-9.-]+$/i', $params['template']))
		{
			unset($params['template']);
		}
		if (($str = $overlay = @file_get_contents($path)) === false)
		{
			return null;
		}
		if (!empty($params['template']) &&
			preg_match('#<(et2-)?template id="'.preg_quote($params['template'], '#').'"[^>]*>.*?</(et2-)?template>#s', $str, $matches))
		{
			$str = $matches[0];
			$template_id = str_replace('.rows', '', $params['template'].'.filter');
		}
		else
		{
			$template_id = $app.'.filter';
		}
		$xet = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE overlay PUBLIC "-//EGroupware GmbH//eTemplate 2.0//EN" "https://www.egroupware.org/etemplate2.0.dtd">
<overlay>
	<et2-template id="$template_id">
EOF;
		// Add searchbox
		self::searchbox($xet, $app, $path_info);

		// get all NM sort-header and place them in filter-template
		$sort_options = [];
		foreach(preg_match_all('#<(et2-)?nextmatch-sortheader ([^>]+?)/?>#s', $str, $matches, PREG_SET_ORDER) ? $matches : [] as $n => $match)
		{
			$attrs = self::parseAttrs($match[2]);
			$sort_options[$attrs['id']] = "\t\t\t\t".'<option value="'.$attrs['id'].'"'.(!empty($attrs['statustext']) ? ' title="'.$attrs['statustext'].'"':'').'>'.$attrs['label'].'</option>';
		}

		if ($sort_options)
		{
			$sort_options = implode("\n", $sort_options)."\n";
			$xet .= (Api\Header\UserAgent::mobile() ? '<et2-hbox class="sort">' : '<et2-visually-hidden class="sort">');
			$xet .= <<<EOF
			<et2-select id="sort[id]" label="Sorting" ariaLabel="Ordering" class="et2-label-fixed" value="{$attrs['id']}">
$sort_options
			</et2-select>
			<et2-button-toggle ariaLabel="Sorting" id="sort[asc]" onIcon="caret-up-fill" offIcon="caret-down-fill" style="flex-grow: 0;"></et2-button-toggle>
EOF;
			$xet .= (Api\Header\UserAgent::mobile() ? '</et2-hbox>' : '</et2-visually-hidden>');
		}

		// add standard filter
		foreach(['cat_id', 'filter', 'filter2'] as $id)
		{
			if (isset($params[$id]))
			{
				$label = htmlspecialchars($params[$id], ENT_XML1, 'UTF-8');
				if ($id === 'cat_id' && empty($params['cat_is_select']))
				{
					$widget = 'et2-select-cat';
				}
				else
				{
					$widget = $params[$id . '_widget'] ?? 'et2-select';
				}
				$extra_attrs = '';
				foreach(['no_lang', 'statustext', 'placeholder', 'onchange'] as $attr)
				{
					if (!empty($params[$id . '_' . $attr]))
					{
						$extra_attrs .= ' '.$attr . '="' . htmlspecialchars($params[$id . '_' . $attr], ENT_XML1, 'UTF-8') . '"';
					}
				}
				$xet .= <<<EOF
		<$widget id="$id" label="$label"$extra_attrs class="et2-label-fixed"></$widget>
EOF;
				if ($id === 'filter' && preg_match('/<(et2-)?template\s+id="'.preg_quote("$app.$name.dates", '/').'"/', $overlay))
				{
					$xet .= <<<EOF
		<et2-template id="$app.$name.dates"></et2-template>
EOF;
				}
			}
		}

		// get all NM filter-headers and place them in the template
		if (preg_match_all('#<(et2-)?nextmatch-([^ ]+) ([^>]+?)/?>#s', $str, $matches, PREG_SET_ORDER) &&
			($matches = array_filter($matches, static fn($match) => !in_array($match[2], ['header', 'sortheader']))))
		{
			$xet .= <<<EOF
		<et2-details summary="Column Filters" id="col_filters" open="true">
EOF;
			foreach ($matches as $match)
			{
				$attrs = self::parseAttrs($match[3]);
				// type the application gave the filter-header for the request, in place of the template's
				$header_type = $params['header_type'][$attrs['id'] ?? ''] ?? null;
				if (is_string($header_type) && preg_match('/^(?:et2-)?nextmatch-([a-z-]+)$/', $header_type, $type_matches) &&
					!in_array($type_matches[1], ['header', 'sortheader']))
				{
					$match[2] = $type_matches[1];
				}
				[$widget, $attrs] = self::headerWidget($match[2], $attrs);
				$attrs = self::stringAttrs($attrs);
				$xet .= <<<EOF
			<$widget $attrs></$widget>
EOF;
			}
			$xet .= <<<EOF
		</et2-details>
EOF;
		}
		// add favorites only if enabled in NM attribute
		if (!empty($params['favorites']))
		{
			$xet .= <<<EOF
		<et2-details summary="Favorites" open="true">
			<et2-favorites-menu application="$app"></et2-favorites-menu>
		</et2-details>
EOF;
		}
		return $xet . <<<EOF
		<et2-styles>et2-hbox.sort::part(base) {  align-items: flex-end }</et2-styles>
	</et2-template>
</overlay>
EOF;
	}

	/**
	 * Widget a filter-header of a column becomes in the filter template
	 *
	 * @param string $type type of the filter-header without its "(et2-)nextmatch-" prefix, eg. "header-account"
	 * @param array $attrs attributes of the filter-header
	 * @return array [string $widget, array $attrs] type of the widget and its attributes, with the id it
	 *	has in the namespace of the nextmatch: "col_filter[$id]", or "col_filter" for all custom-fields
	 */
	public static function headerWidget(string $type, array $attrs) : array
	{
		$widget = $attrs['widgetType'] ?? 'et2-select';
		switch ($type)
		{
			case 'header-customfields':
			case 'customfields':
				$widget = 'et2-customfields-filters';
				$attrs['label'] = '';
				$attrs['id'] = 'col_filter';
				// No field filter - override what's set for nm
				$attrs['fields'] = '';
				break;
			case 'accountfilter':
			case 'header-account':
				$widget = 'et2-select-account';
				if (empty($attrs['emptyLabel']) && !empty($attrs['options']))
				{
					$attrs['emptyLabel'] = $attrs['options'];
					unset($attrs['options']);
				}
				break;
			case 'header-entry':
				$widget = 'et2-link-entry';
				break;
			case 'customfilter':
			case 'header-custom':
				$widget = ($attrs['widgetType'] ?? $attrs['widget_type'] ?? $attrs['options'] ?? '' ?: $attrs['type'] ?? 'et2-select');
				unset($attrs['widgetType'], $attrs['widget_type'], $attrs['options'], $attrs['class']);
				// legacy "options" are "<type>[,<options>]" eg. "link-entry,infolog": only the type can be the widget name,
				// a comma in the element name makes the whole filter-template unparsable (dropping all filters behind it)
				if (str_contains($widget, ','))
				{
					[$widget, $widget_options] = explode(',', $widget, 2);
					if ($widget === 'link-entry' && ($only_app = trim(explode(',', $widget_options)[0])) !== '')
					{
						$attrs['onlyApp'] = $only_app;
					}
				}
				if ($widget !== 'records-field') break;
				// fall-through for records-field
			case 'records-field':
				$attrs['label'] = $attrs['widget_options'] ?? '';
				$attrs['emptyLabel'] = $attrs['empty_label'] ?? '';
				unset($attrs['widgetOptions'], $attrs['widget_options'], $attrs['type'], $attrs['empty_label']);
				$widget = 'et2-select';
				break;
		}
		if (!str_starts_with($widget, 'et2-'))
		{
			$widget = 'et2-'.$widget;
		}
		if (!isset($attrs['label']))
		{
			$attrs['label'] = $attrs['ariaLabel'] ?? $attrs['emptyLabel'] ?? $attrs['statustext'] ?? $attrs['blur'] ?? null;
			if ($attrs['label'] === ($attrs['ariaLabel'] ?? null)) unset($attrs['ariaLabel']);
			if (empty($attrs['label'])) unset($attrs['label']);
		}
		unset($attrs['blur']);	// legacy placeholder of nextmatch-customfilter, used as label above
		$attrs['class'] = trim(str_replace('selectboxFullWidth', '', $attrs['class'] ?? '') . ' et2-label-fixed');
		// et2-details does NOT create a namespace therefore, we need to use "col_filter[$id]" (not for cfs without id!)
		if(!empty($attrs['id']) && $attrs['id'] !== 'col_filter')
		{
			$attrs['id'] = 'col_filter[' . $attrs['id'] . ']';
		}
		return [$widget, $attrs];
	}

	/**
	 * Widgets of the filter template generated for an url of api/filter-template.php
	 *
	 * The template is built for this call and not kept with the templates read from files.
	 *
	 * @param string $url as the nextmatch gives it to the client, eg.
	 *	"/egroupware/api/filter-template.php/infolog/templates/default/index.xet?1234&filter=Filter"
	 * @return Template|null null if the url names no template file which can be read
	 */
	public static function widgets(string $url) : ?Template
	{
		static $widgets = [];
		if (array_key_exists($url, $widgets))
		{
			return $widgets[$url];
		}
		$widgets[$url] = null;
		if (!preg_match('#/api/filter-template\.php(/([^/?]+)/templates/([^/?]+)/([^/?]+)\.xet)(?:\?(.*))?$#s', $url, $matches))
		{
			return null;
		}
		[, $path_info, $app, $template_set, $name] = $matches;
		$params = [];
		parse_str($matches[5] ?? '', $params);

		$path = Template::rel2path(Template::relPath($app.'.'.$name, $template_set));
		if (empty($path) || str_contains($path, '..') || !file_exists($path) || !is_readable($path) ||
			($xet = self::generate($app, $name, $path, $params, $path_info)) === null)
		{
			return null;
		}
		$reader = new XMLReader();
		if (!@$reader->XML($xet))
		{
			return null;
		}
		while(@$reader->read())
		{
			if ($reader->nodeType === XMLReader::ELEMENT && in_array($reader->name, ['template', 'et2-template'], true))
			{
				$widgets[$url] = new Template($reader);
				break;
			}
		}
		$reader->close();
		return $widgets[$url];
	}

	/**
	 * Parse attributes in an array
	 *
	 * @param string $str
	 * @return array
	 */
	public static function parseAttrs($str)
	{
		if (empty($str) || !trim($str))
		{
			return [];
		}
		if (!preg_match_all('/(^|\s)([a-z\d_-]+)\s*=\s*"([^"]*)"/i', $str, $attrs, PREG_PATTERN_ORDER))
		{
			throw new \Exception("Can NOT parse attributes from '$str'");
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
	public static function stringAttrs(array $attrs)
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

	/**
	 * Add the search to the filter template: the one of RAG if it supports the application
	 *
	 * @param string &$str xet to add the search to
	 * @param string $app
	 * @param ?string $path path of the template file in the url
	 */
	protected static function searchbox(&$str, $app, $path)
	{
		if (class_exists('EGroupware\\Rag\\Embedding') &&
			($plugins = \EGroupware\Rag\Embedding::plugins($app)) &&
			array_key_exists($app, $plugins) &&
			// exceptions supporting RAG, but not with all (sub-)templates
			!preg_match('#^/projectmanager/templates/[^/]+/(elements|pricelist).list.xet#', (string)$path))
		{
			// Use RAG search
			$str .= <<<EOF
			<et2-template template="rag.search"></et2-template>
EOF;
		}
		else
		{
			$str .= <<<EOF
	<et2-searchbox id="search" label="Search" class="et2-label-fixed"></et2-searchbox>
EOF;
		}
	}
}
