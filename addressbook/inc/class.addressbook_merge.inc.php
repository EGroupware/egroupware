<?php
/**
 * Addressbook - document merge
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @copyright (c) 2007-9 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Addressbook - document merge object
 */
class addressbook_merge extends bo_merge
{
	/**
	 * Functions that can be called via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array('show_replacements' => true);

	/**
	 * Constructor
	 *
	 * @return addressbook_merge
	 */
	function __construct()
	{
		parent::__construct();

		// overwrite global export-limit, if an addressbook one is set
		if ($GLOBALS['egw_info']['server']['contact_export_limit'])
		{
			$this->export_limit = $GLOBALS['egw_info']['server']['contact_export_limit'];
		}
	}

	/**
	 * Get addressbook replacements
	 *
	 * @param int $id id of entry
	 * @param string &$content=null content to create some replacements only if they are use
	 * @return array|boolean
	 */
	protected function get_replacements($id,&$content=null)
	{
		if (!($replacements = $this->contact_replacements($id)))
		{
			return false;
		}
		if (!(strpos($content,'$$calendar/') === false))
		{
			$replacements += $this->calendar_replacements($id,!(strpos($content,'$$calendar/-1/') === false));
		}
		return $replacements;
	}

	/**
	 * Return replacements for the calendar (next events) of a contact
	 *
	 * @param int $contact contact-id
	 * @param boolean $last_event_too=false also include information about the last event
	 * @return array
	 */
	protected function calendar_replacements($id,$last_event_too=false)
	{
		$calendar = new calendar_boupdate();

		// next events
		$events = $calendar->search(array(
			'start' => $calendar->now_su,
			'users' => 'c'.$id,
			'offset' => 0,
			'num_rows' => 20,
			'order' => 'cal_start',
		));
		if ($events)
		{
			array_unshift($events,false); unset($events[0]);	// renumber the array to start with key 1, instead of 0
		}
		else
		{
			$events = array();
		}
		if ($last_event_too==true)
		{
			$last = $calendar->search(array(
				'end' => $calendar->now_su,
				'users' => 'c'.$id,
				'offset' => 0,
				'num_rows' => 1,
				'order' => 'cal_start DESC',
			));
			$events['-1'] = $last ? array_shift($last) : array();	// returned events are indexed by cal_id!
		}
		$replacements = array();
		foreach($events as $n => $event)
		{
			foreach($calendar->event2array($event) as $name => $data)
			{
				if (substr($name,-4) == 'date') $name = substr($name,0,-4);
				$replacements['$$calendar/'.$n.'/'.$name.'$$'] = is_array($data['data']) ? implode(', ',$data['data']) : $data['data'];
			}
			foreach(array('start','end') as $what)
			{
				foreach(array(
					'date' => $GLOBALS['egw_info']['user']['preferences']['common']['dateformat'],
					'day'  => 'l',
					'time' => $GLOBALS['egw_info']['user']['preferences']['common']['timeformat'] == 12 ? 'h:i a' : 'H:i',
				) as $name => $format)
				{
					$value = $event[$what] ? date($format,$event[$what]) : '';
					if ($format == 'l') $value = lang($value);
					$replacements['$$calendar/'.$n.'/'.$what.$name.'$$'] = $value;
				}
			}
			$duration = ($event['end'] - $event['start'])/60;
			$replacements['$$calendar/'.$n.'/duration$$'] = floor($duration/60).lang('h').($duration%60 ? $duration%60 : '');

			++$n;
		}

		// Need to set some keys if there is no previous event
		if($last_event_too && count($events['-1']) == 0) {
			$replacements['$$calendar/-1/start$$'] = '';
			$replacements['$$calendar/-1/end$$'] = '';
			$replacements['$$calendar/-1/owner$$'] = '';
			$replacements['$$calendar/-1/updated$$'] = '';
		}
		return $replacements;
	}

	/**
	 * Generate table with replacements for the preferences
	 *
	 */
	public function show_replacements()
	{
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Addressbook').' - '.lang('Replacements for inserting contacts into documents');
		$GLOBALS['egw_info']['flags']['nonavbar'] = false;
		common::egw_header();

		echo "<table width='90%' align='center'>\n";
		echo '<tr><td colspan="4"><h3>'.lang('Contact fields:')."</h3></td></tr>";

		$n = 0;
		foreach($this->contacts->contact_fields as $name => $label)
		{
			if (in_array($name,array('tid','label','geo'))) continue;	// dont show them, as they are not used in the UI atm.

			if (in_array($name,array('email','org_name','tel_work','url')) && $n&1)		// main values, which should be in the first column
			{
				echo "</tr>\n";
				$n++;
			}
			if (!($n&1)) echo '<tr>';
			echo '<td>{{'.$name.'}}</td><td>'.$label.'</td>';
			if($name == 'cat_id')
			{
				if ($n&1) echo "</tr>\n";
				echo '<td>{{categories}}</td><td>'.lang('Category path').'</td>';
				$n++;
			}
			if ($n&1) echo "</tr>\n";
			$n++;
		}

		echo '<tr><td colspan="4"><h3>'.lang('Custom fields').":</h3></td></tr>";
		foreach($this->contacts->customfields as $name => $field)
		{
			echo '<tr><td>{{#'.$name.'}}</td><td colspan="3">'.$field['label']."</td></tr>\n";
		}

		echo '<tr><td colspan="4"><h3>'.lang('General fields:')."</h3></td></tr>";
		foreach(array(
			'date' => lang('Date'),
			'user/n_fn' => lang('Name of current user, all other contact fields are valid too'),
			'user/account_lid' => lang('Username'),
			'pagerepeat' => lang('For serial letter use this tag. Put the content, you want to repeat between two Tags.'),
			'label' => lang('Use this tag for addresslabels. Put the content, you want to repeat, between two tags.'),
			'labelplacement' => lang('Tag to mark positions for address labels'),
			'IF fieldname' => lang('Example {{IF n_prefix~Mr~Hello Mr.~Hello Ms.}} - search the field "n_prefix", for "Mr", if found, write Hello Mr., else write Hello Ms.'),
			'NELF' => lang('Example {{NELF role}} - if field role is not empty, you will get a new line with the value of field role'),
			'NENVLF' => lang('Example {{NELFNV role}} - if field role is not empty, set a LF without any value of the field'),
			'LETTERPREFIX' => lang('Example {{LETTERPREFIX}} - Gives a letter prefix without double spaces, if the title is emty for  example'),
			'LETTERPREFIXCUSTOM' => lang('Example {{LETTERPREFIXCUSTOM n_prefix title n_family}} - Example: Mr Dr. James Miller'),
			) as $name => $label)
		{
			echo '<tr><td>{{'.$name.'}}</td><td colspan="3">'.$label."</td></tr>\n";
		}

		$GLOBALS['egw']->translation->add_app('calendar');
		echo '<tr><td colspan="4"><h3>'.lang('Calendar fields:')." # = 1, 2, ..., 20, -1</h3></td></tr>";
		foreach(array(
			'title' => lang('Title'),
			'description' => lang('Description'),
			'participants' => lang('Participants'),
			'location' => lang('Location'),
			'start'    => lang('Start').': '.lang('Date').'+'.lang('Time'),
			'startday' => lang('Start').': '.lang('Weekday'),
			'startdate'=> lang('Start').': '.lang('Date'),
			'starttime'=> lang('Start').': '.lang('Time'),
			'end'      => lang('End').': '.lang('Date').'+'.lang('Time'),
			'endday'   => lang('End').': '.lang('Weekday'),
			'enddate'  => lang('End').': '.lang('Date'),
			'endtime'  => lang('End').': '.lang('Time'),
			'duration' => lang('Duration'),
			'category' => lang('Category'),
			'priority' => lang('Priority'),
			'updated'  => lang('Updated'),
			'recur_type' => lang('Repetition'),
			'access'   => lang('Access').': '.lang('public').', '.lang('private'),
			'owner'    => lang('Owner'),
		) as $name => $label)
		{
			if (in_array($name,array('start','end')) && $n&1)		// main values, which should be in the first column
			{
				echo "</tr>\n";
				$n++;
			}
			if (!($n&1)) echo '<tr>';
			echo '<td>{{calendar/#/'.$name.'}}</td><td>'.$label.'</td>';
			if ($n&1) echo "</tr>\n";
			$n++;
		}
		echo "</table>\n";

		common::egw_footer();
	}
}
