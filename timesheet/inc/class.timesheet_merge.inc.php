<?php
/**
 * Timesheet - document merge
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Nathan Gray
 * @package timesheet
 * @copyright (c) 2007-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright 2011 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Link;

/**
 * Timesheet - document merge object
 */
class timesheet_merge extends Api\Storage\Merge
{
	/**
	 * Functions that can be called via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'download_by_request'		=> true,
		'show_replacements'			=> true,
		'timesheet_replacements'	=> true,
		'merge_entries'				=> true
	);

	/**
	 * Fields that are numeric, for special numeric handling
	 */
	protected $numeric_fields = array(
		'$$ts_duration$$',
		'$$ts_quantity$$',
		'$$ts_unitprice$$'
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

		// switch of handling of Api\Html formated content, if Api\Html is not used
		$this->parse_html_styles = Api\Storage\Customfields::use_html('timesheet');

		$this->bo = new timesheet_bo();
		$this->date_fields += timesheet_egw_record::$types['date-time'];
	}

	/**
	 * Get replacements
	 *
	 * @param int $id id of entry
	 * @param string &$content=null content to create some replacements only if they are use
	 * @return array|boolean
	 */
	protected function get_replacements($id,&$content=null)
	{
		if (!($replacements = $this->timesheet_replacements($id, '', $content)))
		{
			return false;
		}
		if (!(strpos($content,'$$ts_project/') === false))
		{
			// Check to see if it's actually a project, then load
			if($replacements['$$pm_id$$'])
			{
				if(!$this->pm)
				{
					$this->pm = new projectmanager_merge();
				}
				$this->pm->projectmanager_bo = new projectmanager_bo($replacements['$$pm_id$$']);
				$replacements += $this->pm->projectmanager_replacements($replacements['$$pm_id$$'],'ts_project');
			}
		}
		return $replacements;
	}

	/**
	 * Get timesheet replacements
	 *
	 * @param int $id id of entry
	 * @param string $prefix='' prefix like eg. 'erole'
	 * @return array|boolean
	 */
	public function timesheet_replacements($id,$prefix='', &$content = null)
	{
		$record = new timesheet_egw_record($id);
		$info = array();

		// Get project manager ID
		$links = Link\Storage::get_links('timesheet',$id,'projectmanager');
		if($links)
		{
			$record->pm_id = current($links);
		}

		// Convert to human friendly values
		$types = timesheet_egw_record::$types;
		// We have specific requirements for floats, we'll do them in Merge
		unset($types['float']);

		$_selects = array('status' => $this->bo->status_labels);
		foreach($_selects['status'] as &$status)
		{
			$status = str_replace('&nbsp;', '', $status);
		}
		foreach($_selects as $name => $value)
		{
			$selects['ts_'.$name] = $value;
		}
		if($content && strpos($content, '#') !== 0)
		{
			$this->cf_link_to_expand($record->get_record_array(), $content, $info);
		}

		importexport_export_csv::convert($record, $types, 'timesheet', $selects);

		$array = $record->get_record_array();
		$array['ts_total'] = $array['ts_quantity'] * $array['ts_unitprice'];
		foreach(array('ts_duration','ts_quantity','ts_unitprice','ts_total') as $key)
		{
			$array[$key] = self::number_format($array[$key],2,$this->mimetype);
		}

		// Set any missing custom fields, or the marker will stay
		foreach(array_keys($this->bo->customfields) as $name)
		{
			if(!$array['#'.$name]) $array['#'.$name] = '';
		}

		// Add markers
		foreach($array as $key => &$value)
		{
			if(!$value) $value = '';
			$info['$$'.($prefix ? $prefix.'/':'').$key.'$$'] = $value;
		}

		// Links
		$info += $this->get_all_links('timesheet', $id, $prefix, $content);

		return $info;
	}

	/**
	 * Get a list of placeholders provided.
	 *
	 * Placeholders are grouped logically.  Group key should have a user-friendly translation.
	 */
	public function get_placeholder_list($prefix = '')
	{
		$placeholders = array(
				'timesheet'     => [],
				lang('Project') => []
			) + parent::get_placeholder_list($prefix);

		$fields = array('ts_id' => lang('Timesheet ID')) + $this->bo->field2label + array(
				'ts_total'    => lang('total'),
				'ts_created'  => lang('Created'),
				'ts_modified' => lang('Modified'),
			);
		$group = 'timesheet';
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
			// Add project placeholders
			$pm_merge = new projectmanager_merge();
			$this->add_linked_placeholders($placeholders, lang('Project'), $pm_merge->get_placeholder_list('ts_project'));
		}
		return $placeholders;
	}
}
