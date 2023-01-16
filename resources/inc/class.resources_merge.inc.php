<?php
/**
 * Resources - document merge
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package resources
 * @copyright 2022 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Link;

/**
 * Resources - document merge object
 */
class resources_merge extends Api\Storage\Merge
{
	/**
	 * Functions that can be called via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'download_by_request'	=> true,
		'show_replacements'		=> true,
		'merge_entries'		=> true,
	);

	/**
	 * Business object to pull records from
	 */
	protected $bo = null;

	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
		parent::__construct();
		$this->bo = new resources_bo();

		$this->date_fields += array(
			'res_modified',
			'res_created',
		);

		// switch of handling of Api\Html formated content, if Api\Html is not used
		$this->parse_html_styles = Api\Storage\Customfields::use_html('resources');
	}

	/**
	 * Get replacements
	 *
	 * @param int $id id of entry
	 * @param string &$content=null content to create some replacements only if they are use
	 * @return array|boolean
	 */
	protected function get_replacements($id, &$content = null)
	{
		if(!($replacements = $this->resource_replacements($id, '', $content)))
		{
			return false;
		}
		return $replacements;
	}

	/**
	 * Get resource replacements
	 *
	 * @param int $id id of entry
	 * @param string $prefix ='' prefix like eg. 'erole'
	 * @return array|boolean
	 */
	public function resource_replacements($id, $prefix = '', &$content = '')
	{
		$record = new resources_egw_record($id);
		$info = array();

		// Convert to human friendly values
		$types = resources_egw_record::$types;
		// Needed to find fields later
		$accessory_of = $record->accessory_of;

		$selects = array();
		if($content && strpos($content, '$$#') !== FALSE)
		{
			$this->cf_link_to_expand($record->get_record_array(), $content, $info);
		}

		importexport_export_csv::convert($record, $types, 'resources', $selects);

		$array = $record->get_record_array();

		// Set any missing custom fields, or the marker will stay
		foreach(\EGroupware\Api\Storage\Customfields::get('resources') as $name => $field)
		{
			if(empty($array['#' . $name]))
			{
				$array['#' . $name] = '';
			}
			// Format date cfs per user Api\Preferences
			if($array['#' . $name] && ($field['type'] == 'date' || $field['type'] == 'date-time'))
			{
				$this->date_fields[] = '#' . $name;
				$array['#' . $name] = Api\DateTime::to($array['#' . $name], $field['type'] == 'date' ? true : '');
			}
		}

		// Add markers
		foreach($array as $key => &$value)
		{
			if(!$value) $value = '';
			$info['$$'.($prefix ? $prefix.'/':'').$key.'$$'] = $value;
		}

		// Links
		$info += $this->get_all_links('resources', $id, $prefix, $content);

		// Add parent
		if($record->accessory_of)
		{
			$info += $this->resource_replacements($accessory_of, 'accessory_of', $content);
		}
		return $info;
	}

	public function get_placeholder_list($prefix = '')
	{
		$placeholders = array(
				'resource'       => [],
				'Accessory of'   => []
			) + parent::get_placeholder_list($prefix);

		$fields = array(
			'res_id'			=> lang('Resource ID'),
			'name'              => lang('name'),
			'short_description' => lang('short description'),
			'cat_id'            => lang('category'),
			'quantity'          => lang('quantity'),
			'useable'           => lang('useable'),
			'location'          => lang('location'),
			'storage_info'      => lang('storage'),
			'bookable'          => lang('bookable'),
			'long_description'  => lang('description'),
			'inventory_number'  => lang('inventory number'),
			'accessory_of'      => lang('accessory of')
		);

		$group = 'resource';
		foreach($fields as $name => $label)
		{
			if(in_array($name, array('custom')))
			{
				// dont show them
				continue;
			}
			$marker = $this->prefix($prefix, $name, '{');
			if(!array_filter($placeholders, function ($a) use ($marker)
			{
				return array_key_exists($marker, $a);
			}))
			{
				$placeholders[$group][] = [
					'value' => $marker,
					'label' => $label
				];
			}
		}

		// Don't add any linked placeholders if we're not at the top level
		// This avoids potential recursion
		if(!$prefix)
		{
			// Add parent placeholders
			$this->add_linked_placeholders(
				$placeholders,
				lang('accessory of'),
				$this->get_placeholder_list(($prefix ? $prefix . '/' : '') . 'accessory_of')
			);
		}
		else
		{
			unset($placeholders[lang('accessory of')]);
		}
		return $placeholders;
	}
}
