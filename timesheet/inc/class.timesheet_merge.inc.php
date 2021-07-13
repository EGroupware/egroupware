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
	 * Generate table with replacements for the Api\Preferences
	 *
	 */
	public function show_replacements()
	{
		$GLOBALS['egw_info']['flags']['app_header'] = lang('timesheet').' - '.lang('Replacements for inserting entries into documents');
		$GLOBALS['egw_info']['flags']['nonavbar'] = false;
		echo $GLOBALS['egw']->framework->header();

		echo "<table width='90%' align='center'>\n";
		echo '<tr><td colspan="4"><h3>'.lang('Timesheet fields:')."</h3></td></tr>";

		$n = 0;
		$fields = array('ts_id' => lang('Timesheet ID')) + $this->bo->field2label + array(
			'ts_total' => lang('total'),
			'ts_created' => lang('Created'),
			'ts_modified' => lang('Modified'),
		);
		foreach($fields as $name => $label)
		{
			if (in_array($name,array('pl_id','customfields'))) continue;	// dont show them

			if (in_array($name,array('ts_title', 'ts_description')) && $n&1)		// main values, which should be in the first column
			{
				echo "</tr>\n";
				$n++;
			}
			if (!($n&1)) echo '<tr>';
			echo '<td>{{'.$name.'}}</td><td>'.lang($label).'</td>';
			if ($n&1) echo "</tr>\n";
			$n++;
		}

		echo '<tr><td colspan="4"><h3>'.lang('Custom fields').":</h3></td></tr>";
		foreach($this->bo->customfields as $name => $field)
		{
			echo '<tr><td>{{#'.$name.'}}</td><td colspan="3">'.$field['label']."</td></tr>\n";
		}

		echo '<tr><td colspan="4"><h3>'.lang('Project fields').':</h3></td></tr>';
		$pm_merge = new projectmanager_merge();
		$i = 0;
		foreach($pm_merge->projectmanager_fields as $name => $label)
		{
			if (!($i&1)) echo '<tr>';
			echo '<td>{{ts_project/'.$name.'}}</td><td>'.$label.'</td>';
			if ($i&1) echo "</tr>\n";
			$i++;
		}

		echo '<tr><td colspan="4"><h3>'.lang('General fields:')."</h3></td></tr>";
		foreach($this->get_common_replacements() as $name => $label)
		{
			echo '<tr><td>{{'.$name.'}}</td><td colspan="3">'.$label."</td></tr>\n";
		}

		echo "</table>\n";

		echo $GLOBALS['egw']->framework->footer();
	}
}
