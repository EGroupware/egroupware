<?php
/**
 * Infolog - document merge
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Nathan Gray
 * @package infolog
 * @copyright (c) 2007-9 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright 2011 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Infolog - document merge object
 */
class infolog_merge extends bo_merge
{
	/**
	 * Functions that can be called via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array('show_replacements' => true);

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
		if (!($replacements = $this->infolog_replacements($id)))
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
	 * Get infolog replacements
	 *
	 * @param int $id id of entry
	 * @return array|boolean
	 */
	protected function infolog_replacements($id) 
	{
		$record = new infolog_egw_record($id);
		$info = array();

		// Convert to human friendly values
		$types = infolog_export_csv::$types;
		$_selects = $this->bo->enums + array('status' => $this->bo->status[$record->info_type]);
		foreach($_selects as $name => $value)
		{
			$selects['info_'.$name] = $value;
			$types['select'][] = 'info_'.$name;
		}
		importexport_export_csv::convert($record, $types, 'infolog', $selects);
		if($record->info_contact)
		{
			$array['info_contact'] = $array['info_link']['title'];
		}
		// Set any missing custom fields, or the marker will stay
		$array = $record->get_record_array();
		foreach($this->bo->customfields as $name => $field)
		{
			if(!$array['#'.$name]) $array['#'.$name] = '';
		}

		// Add markers
		foreach($array as $key => $value)
		{
			$info['$$' . $key . '$$'] = $value;
		}
		return $info;
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
		if ($last_event_too=true)
		{
			$last = $calendar->search(array(
				'end' => $calendar->now_su,
				'users' => 'c'.$id,
				'offset' => 0,
				'num_rows' => 1,
				'order' => 'cal_start DESC',
			));
			if ($last) $events['-1'] = array_shift($last);	// returned events are indexed by cal_id!
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
					$value = date($format,$event[$what]);
					if ($format == 'l') $value = lang($value);
					$replacements['$$calendar/'.$n.'/'.$what.$name.'$$'] = $value;
				}
			}
			$duration = ($event['end'] - $event['start'])/60;
			$replacements['$$calendar/'.$n.'/duration$$'] = floor($duration/60).lang('h').($duration%60 ? $duration%60 : '');

			++$n;
		}
		return $replacements;
	}

	/**
	 * Generate table with replacements for the preferences
	 *
	 */
	public function show_replacements()
	{
		$GLOBALS['egw_info']['flags']['app_header'] = lang('infolog').' - '.lang('Replacements for inserting entries into documents');
		$GLOBALS['egw_info']['flags']['nonavbar'] = false;
		common::egw_header();

		echo "<table width='90%' align='center'>\n";
		echo '<tr><td colspan="4"><h3>'.lang('Infolog fields:')."</h3></td></tr>";

		$n = 0;
		$tracking = new infolog_tracking($this->bo);
		foreach($tracking->field2label as $name => $label)
		{
			if (in_array($name,array('custom'))) continue;	// dont show them

			if (in_array($name,array('info_subject', 'info_des')) && $n&1)		// main values, which should be in the first column
			{
				echo "</tr>\n";
				$n++;
			}
			if (!($n&1)) echo '<tr>';
			echo '<td>$$'.$name.'$$</td><td>'.$label.'</td>';
			if ($n&1) echo "</tr>\n";
			$n++;
		}

		echo '<tr><td colspan="4"><h3>'.lang('Custom fields').":</h3></td></tr>";
		foreach($this->bo->customfields as $name => $field)
		{
			echo '<tr><td>$$#'.$name.'$$</td><td colspan="3">'.$field['label']."</td></tr>\n";
		}

		echo '<tr><td colspan="4"><h3>'.lang('General fields:')."</h3></td></tr>";
		foreach(array(
			'date' => lang('Date'),
			'user/n_fn' => lang('Name of current user, all other contact fields are valid too'),
			'user/account_lid' => lang('Username'),
			'pagerepeat' => lang('For serial letter use this tag. Put the content, you want to repeat between two Tags.'),
			'label' => lang('Use this tag for addresslabels. Put the content, you want to repeat, between two tags.'),
			'labelplacement' => lang('Tag to mark positions for address labels'),
			'IF fieldname' => lang('Example $$IF n_prefix~Mr~Hello Mr.~Hello Ms.$$ - search the field "n_prefix", for "Mr", if found, write Hello Mr., else write Hello Ms.'),
			'NELF' => lang('Example $$NELF role$$ - if field role is not empty, you will get a new line with the value of field role'),
			'NENVLF' => lang('Example $$NELFNV role$$ - if field role is not empty, set a LF without any value of the field'),
			'LETTERPREFIX' => lang('Example $$LETTERPREFIX$$ - Gives a letter prefix without double spaces, if the title is emty for example'),
			'LETTERPREFIXCUSTOM' => lang('Example $$LETTERPREFIXCUSTOM n_prefix title n_family$$ - Example: Mr Dr. James Miller'),
			) as $name => $label)
		{
			echo '<tr><td>$$'.$name.'$$</td><td colspan="3">'.$label."</td></tr>\n";
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
			echo '<td>$$calendar/#/'.$name.'$$</td><td>'.$label.'</td>';
			if ($n&1) echo "</tr>\n";
			$n++;
		}
		echo "</table>\n";

		common::egw_footer();
	}
}
