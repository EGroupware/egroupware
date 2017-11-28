<?php
/**
 * Infolog - document merge
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Nathan Gray
 * @package infolog
 * @copyright (c) 2007-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright 2011 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Link;

/**
 * Infolog - document merge object
 */
class infolog_merge extends Api\Storage\Merge
{
	/**
	 * Functions that can be called via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'download_by_request'	=> true,
		'show_replacements'		=> true,
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
		$this->bo = new infolog_bo();

		$this->date_fields += array(
			'info_startdate',
			'info_enddate',
			'info_datecompleted',
			'info_datemodified',
			'info_created',
		);

		// switch of handling of Api\Html formated content, if Api\Html is not used
		$this->parse_html_styles = Api\Storage\Customfields::use_html('infolog');
	}

	/**
	 * Get infolog replacements
	 *
	 * @param int $id id of entry
	 * @param string &$content=null content to create some replacements only if they are use
	 * @return array|boolean
	 */
	protected function get_replacements($id,&$content=null)
	{
		if (!($replacements = $this->infolog_replacements($id, '', $content)))
		{
			return false;
		}
		return $replacements;
	}

	/**
	 * Get infolog replacements
	 *
	 * @param int $id id of entry
	 * @param string $prefix='' prefix like eg. 'erole'
	 * @return array|boolean
	 */
	public function infolog_replacements($id,$prefix='', &$content = '')
	{
		$record = new infolog_egw_record($id);
		$info = array();

		// Convert to human friendly values
		$types = infolog_egw_record::$types;
		$_selects = $this->bo->enums + array('status' => $this->bo->status[$record->info_type]);
		foreach($_selects as $name => $value)
		{
			$selects['info_'.$name] = $value;
			if(!in_array('info_'.$name, $types['select'])) $types['select'][] = 'info_'.$name;
		}

		if($content && strpos($content, '$$#') !== FALSE)
		{
			$this->cf_link_to_expand($record->get_record_array(), $content, $info);
		}

		importexport_export_csv::convert($record, $types, 'infolog', $selects);

		$array = $record->get_record_array();
		if($record->info_contact)
		{
			$array['info_contact'] = $array['info_link']['title'];
		}

		// Set any missing custom fields, or the marker will stay
		foreach($this->bo->customfields as $name => $field)
		{
			if(!$array['#'.$name])
			{
				$array['#'.$name] = '';
			}
			// Format date cfs per user Api\Preferences
			if($field['type'] == 'date' || $field['type'] == 'date-time')
			{
				$this->date_fields[] = '#'.$name;
				$array['#'.$name] = Api\DateTime::to($array['#'.$name], $field['type'] == 'date' ? true : '');
			}
		}

		// Links
		$array += $this->get_all_links('infolog', $id, $prefix, $content);

		// Timesheet time
		if(strpos($content, 'info_sum_timesheets'))
		{
			$links = Link::get_links('infolog',$id,'timesheet');
			$sum = ExecMethod('timesheet.timesheet_bo.sum',$links);
			$info['$$info_sum_timesheets$$'] = $sum['duration'];
		}

		// Check for linked project ID
		$links = Link::get_links('infolog', $id, 'projectmanager');
		foreach($links as $app_id)
		{
			$array['pm_id'] = $app_id;
			$array['project'] = Link::title('projectmanager', $app_id);
			break;
		}
		if (strpos($content, '$$project/') !== false && $array['pm_id'] && class_exists('projectmanager_merge'))
		{
			$pm_merge = new projectmanager_merge($array['pm_id']);
			$info += $pm_merge->projectmanager_replacements($array['pm_id'],'project',$content);
		}

		// Add markers
		foreach($array as $key => &$value)
		{
			if(!$value) $value = '';
			$info['$$'.($prefix ? $prefix.'/':'').$key.'$$'] = $value;
		}

		// Add contact fields
		if($array['info_link']['app'] && $array['info_link']['id'])
		{
			$info+=$this->get_app_replacements($array['info_link']['app'], $array['info_link']['id'], $content, 'info_contact');
		}

		// Add parent
		if($record->info_id_parent)
		{
			$info += $this->infolog_replacements($record->info_id_parent, 'info_id_parent', $content);
		}
		return $info;
	}

	/**
	 * Generate table with replacements for the Api\Preferences
	 *
	 */
	public function show_replacements()
	{
		$GLOBALS['egw_info']['flags']['app_header'] = lang('infolog').' - '.lang('Replacements for inserting entries into documents');
		$GLOBALS['egw_info']['flags']['nonavbar'] = false;
		echo $GLOBALS['egw']->framework->header();

		echo "<table width='90%' align='center'>\n";
		echo '<tr><td colspan="4"><h3>'.lang('Infolog fields:')."</h3></td></tr>";

		$n = 0;
		$tracking = new infolog_tracking($this->bo);
		$fields = array('info_id' => lang('Infolog ID'), 'pm_id' => lang('Project ID'), 'project' => lang('Project name')) + $tracking->field2label + array('info_sum_timesheets' => lang('Used time'));
		Api\Translation::add_app('projectmanager');
		foreach($fields as $name => $label)
		{
			if (in_array($name,array('custom'))) continue;	// dont show them

			if (in_array($name,array('info_subject', 'info_des')) && $n&1)		// main values, which should be in the first column
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
		$contact_custom = false;
		foreach($this->bo->customfields as $name => $field)
		{
			echo '<tr><td>{{#'.$name.'}}</td><td colspan="3">'.$field['label'].($field['type'] == 'select-account' ? '*':'')."</td></tr>\n";
			if($field['type'] == 'select-account') $contact_custom = true;
		}
		if($contact_custom)
		{
			echo '<tr><td /><td colspan="3">* '.lang('Addressbook placeholders available'). '</td></tr>';
		}

		echo '<tr><td colspan="4"><h3>'.lang('Parent').":</h3></td></tr>";
		echo '<tr><td>{{info_id_parent/info_subject}}</td><td colspan="3">'.lang('All other %1 fields are valid',lang('infolog'))."</td></tr>\n";

		echo '<tr><td colspan="4"><h3>'.lang('Contact fields').':</h3></td></tr>';
		$i = 0;
		foreach($this->contacts->contact_fields as $name => $label)
		{
			if (in_array($name,array('tid','label','geo'))) continue;       // dont show them, as they are not used in the UI atm.

			if (in_array($name,array('email','org_name','tel_work','url')) && $n&1)         // main values, which should be in the first column
			{
					echo "</tr>\n";
					$i++;
			}
			if (!($i&1)) echo '<tr>';
			echo '<td>{{info_contact/'.$name.'}}</td><td>'.$label.'</td>';
			if ($i&1) echo "</tr>\n";
			$i++;
		}

		echo '<tr><td colspan="4"><h3>'.lang('Custom fields').":</h3></td></tr>";
		foreach($this->contacts->customfields as $name => $field)
		{
			echo '<tr><td>{{info_contact/#'.$name.'}}</td><td colspan="3">'.$field['label']."</td></tr>\n";
		}

		echo '<tr><td colspan="4"><h3>'.lang('General fields:')."</h3></td></tr>";
		foreach(array(
			'link' => lang('HTML link to the current record'),
			'links' => lang('Titles of any entries linked to the current record, excluding attached files'),
			'attachments' => lang('List of files linked to the current record'),
			'links_attachments' => lang('Links and attached files'),
			'links/[appname]' => lang('Links to specified application.  Example: {{links/infolog}}'),
			'links/href' => lang('Links wrapped in an HREF tag with download link'),
			'links/link' => lang('Download url for links'),
			'date' => lang('Date'),
			'user/n_fn' => lang('Name of current user, all other contact fields are valid too'),
			'user/account_lid' => lang('Username'),
			'pagerepeat' => lang('For serial letter use this tag. Put the content, you want to repeat between two Tags.'),
			'label' => lang('Use this tag for addresslabels. Put the content, you want to repeat, between two tags.'),
			'labelplacement' => lang('Tag to mark positions for address labels'),
			'IF fieldname' => lang('Example {{IF n_prefix~Mr~Hello Mr.~Hello Ms.}} - search the field "n_prefix", for "Mr", if found, write Hello Mr., else write Hello Ms.'),
			'NELF' => lang('Example {{NELF role}} - if field role is not empty, you will get a new line with the value of field role'),
			'NENVLF' => lang('Example {{NENVLF role}} - if field role is not empty, set a LF without any value of the field'),
			'LETTERPREFIX' => lang('Example {{LETTERPREFIX}} - Gives a letter prefix without double spaces, if the title is emty for example'),
			'LETTERPREFIXCUSTOM' => lang('Example {{LETTERPREFIXCUSTOM n_prefix title n_family}} - Example: Mr Dr. James Miller'),
			) as $name => $label)
		{
			echo '<tr><td>{{'.$name.'}}</td><td colspan="3">'.$label."</td></tr>\n";
		}

		echo "</table>\n";

		echo $GLOBALS['egw']->framework->footer();
	}
}
