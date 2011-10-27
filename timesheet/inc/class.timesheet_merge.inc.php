<?php
/**
 * Timesheet - document merge
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Nathan Gray
 * @package timesheet
 * @copyright (c) 2007-9 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright 2011 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Timesheet - document merge object
 */
class timesheet_merge extends bo_merge
{
	/**
	 * Functions that can be called via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'show_replacements'		=> true,
		'timesheet_replacements'	=> true,
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
		$this->bo = new timesheet_bo();
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
		if (!($replacements = $this->timesheet_replacements($id)))
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
	public function timesheet_replacements($id,$prefix='') 
	{
		$record = new timesheet_egw_record($id);
		$info = array();

		// Get project manager ID
		$links = solink::get_links('timesheet',$id,'projectmanager');
		if($links)
		{
			$record->pm_id = current($links);
		}

		// Convert to human friendly values
		$types = timesheet_egw_record::$types;
		$_selects = array('status' => $this->bo->status_labels);
		foreach($_selects['status'] as &$status)
		{
			$status = str_replace('&nbsp;', '', $status);
		}
		foreach($_selects as $name => $value)
		{
			$selects['ts_'.$name] = $value;
		}
		importexport_export_csv::convert($record, $types, 'timesheet', $selects);

		$array = $record->get_record_array();
		$array['ts_total'] = $array['ts_quantity'] * $array['ts_unitprice'];
		foreach(array('ts_duration','ts_quantity','ts_unitprice','ts_total') as $key)
		{
			$array[$key] = self::number_format($array[$key],2,$this->mimetype);
		}
		// Set any missing custom fields, or the marker will stay
		foreach($this->bo->customfields as $name => $field)
		{
			if(!$array['#'.$name]) $array['#'.$name] = '';
		}

		// Links
		$array['links'] = $this->get_links('timesheet', $id, '!'.egw_link::VFS_APPNAME);
 		$array['attachments'] = $this->get_links('timesheet', $id, egw_link::VFS_APPNAME);
		$array['links_attachments'] = $this->get_links('timesheet', $id);

		// Add markers
		foreach($array as $key => &$value)
		{
			if(!$value) $value = '';
			$info['$$'.($prefix ? $prefix.'/':'').$key.'$$'] = $value;
		}
		return $info;
	}

	/**
	 * Generate table with replacements for the preferences
	 *
	 */
	public function show_replacements()
	{
		$GLOBALS['egw_info']['flags']['app_header'] = lang('timesheet').' - '.lang('Replacements for inserting entries into documents');
		$GLOBALS['egw_info']['flags']['nonavbar'] = false;
		common::egw_header();

		echo "<table width='90%' align='center'>\n";
		echo '<tr><td colspan="4"><h3>'.lang('Timesheet fields:')."</h3></td></tr>";

		$n = 0;
		$fields = array('ts_id' => lang('Timesheet ID')) + $this->bo->field2label + array('ts_total' => lang('total'));
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
		$n = 0;
		foreach($pm_merge->projectmanager_fields as $name => $label)
                {
                        if (!($n&1)) echo '<tr>';
                        echo '<td>{{ts_project/'.$name.'}}</td><td>'.$label.'</td>';
                        if ($n&1) echo "</tr>\n";
                        $n++;
                }

		echo '<tr><td colspan="4"><h3>'.lang('General fields:')."</h3></td></tr>";
		foreach(array(
			'links' => lang('Titles of any entries linked to the current record, excluding attached files'),
 			'attachments' => lang('List of files linked to the current record'),
			'links_attachments' => lang('Links and attached files'),
			'date' => lang('Date'),
			'user/n_fn' => lang('Name of current user, all other contact fields are valid too'),
			'user/account_lid' => lang('Username'),
			'pagerepeat' => lang('For serial letter use this tag. Put the content, you want to repeat between two Tags.'),
			'label' => lang('Use this tag for addresslabels. Put the content, you want to repeat, between two tags.'),
			'labelplacement' => lang('Tag to mark positions for address labels'),
			'IF fieldname' => lang('Example {{IF n_prefix~Mr~Hello Mr.~Hello Ms.}} - search the field "n_prefix", for "Mr", if found, write Hello Mr., else write Hello Ms.'),
			'NELF' => lang('Example {{NELF role}} - if field role is not empty, you will get a new line with the value of field role'),
			'NENVLF' => lang('Example {{NELFNV role}} - if field role is not empty, set a LF without any value of the field'),
			'LETTERPREFIX' => lang('Example {{LETTERPREFIX}} - Gives a letter prefix without double spaces, if the title is empty for example'),
			'LETTERPREFIXCUSTOM' => lang('Example {{LETTERPREFIXCUSTOM n_prefix title n_family}} - Example: Mr Dr. James Miller'),
			) as $name => $label)
		{
			echo '<tr><td>{{'.$name.'}}</td><td colspan="3">'.$label."</td></tr>\n";
		}

		echo "</table>\n";

		common::egw_footer();
	}
}
