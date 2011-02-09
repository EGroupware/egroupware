<?php
/**
 * Calendar - document merge
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Nathan Gray
 * @package calendar
 * @copyright (c) 2007-9 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright 2011 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Calendar - document merge object
 */
class calendar_merge extends bo_merge
{
	/**
	 * Functions that can be called via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'show_replacements'	=> true,
	);

	// Object for getting calendar info
	protected $bo;

	// Object used for getting resource info
	protected static $resources;

	/**
	 * Constructor
	 */
	function __construct()
	{
		parent::__construct();

		$this->bo = new calendar_boupdate();

		// Register table plugins
		$this->table_plugins['participant'] = 'participant';
		for($i = 0; $i < 7; $i++)
		{
			$this->table_plugins[date('l', strtotime("+$i days"))] = 'day_plugin';
		}
	}

	/**
	 * Get replacements
	 *
	 * @param int|array $id event-id array with id,recur_date, or array with search parameters
	 * @param string &$content=null content to create some replacements only if they are used
	 * @return array|boolean
	 */
	protected function get_replacements($id,&$content=null)
	{
		$prefix = '';

		// List events ?
		if(is_array($id) && !$id['id'] && strpos($content,'$$calendar') !== false)
		{
			$events = $this->bo->search($id + array(
				'offset' => 0,
				'order' => 'cal_start',
			));
			array_unshift($events,false); unset($events[0]);	// renumber the array to start with key 1, instead of 0
			$prefix = 'calendar/%d/';
		}
		else
		{
			$events = array($id);
		}
		$replacements = array();
		foreach($events as $n => $event)
		{
			$values = $this->calendar_replacements($event,sprintf($prefix,$n));
			$replacements += $values;
		}
		return $replacements;
	}

	/**
	 * Return replacements for the calendar
	 *
	 * @param int|array $id event-id or array with id/recur_date, or array with event info
	 * @param boolean $last_event_too=false also include information about the last event
	 * @return array
	 */
	protected function calendar_replacements($id,$prefix = '')
	{
		$replacements = array();
		if(!is_array($id) || !$id['start']) {
			$event = $this->bo->read(is_array($id) ? $id['id'] : $id, is_array($id) ? $id['recur_date'] : null);
		} else {
			$event = $id;
		}
		foreach($this->bo->event2array($event) as $name => $data)
		{
			if (substr($name,-4) == 'date') $name = substr($name,0,-4);
			$replacements['$$' . ($prefix ? $prefix . '/' : '') . $name . '$$'] = is_array($data['data']) ? implode(', ',$data['data']) : $data['data'];
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
				$replacements['$$' .($prefix ? $prefix.'/':'').$what.$name.'$$'] = $value;
			}
		}
		$duration = ($event['end'] - $event['start'])/60;
		$replacements['$$'.($prefix?$prefix.'/':'').'duration$$'] = floor($duration/60).lang('h').($duration%60 ? $duration%60 : '');

		$custom = config::get_customfields('calendar');
		foreach($custom as $name => $field)
		{
			$replacements['$$'.($prefix?$prefix.'/':'').'#'.$name.'$$'] = $event['#'.$name];
		}

		return $replacements;
	}

	/**
	* Table plugin for event
	* Lists events for a certain day of the week.  Only works for one week at a time, so for multiple weeks,
	* use multiple date ranges.
	*
	* Use:
	* $$table/Monday$$ $$starttime$$ $$title$$ $$endtable$$
	* The day of the week may be language specific (date('l')).
	*
	* @param string $plugin (Monday-Sunday)
	* @param int/array date or date range
	* @param int $n
	* @param string $repeat Text being repeated for each entry
	* @return array
	*/
	public function day_plugin($plugin,$date,$n,$repeat)
	{
		static $days;
		if(is_array($date) && !$date['start']) {
			$event = $this->bo->read(is_array($date) ? $date['id'] : $date, is_array($date) ? $date['recur_date'] : null);
			if(date('l',$event['start']) != $plugin) return array();
			$date = $event['start'];
		}

		$_date = $date['start'] ? $date['start'] : $date;

		if($days[date('Ymd',$_date)][$plugin]) return $days[date('Ymd',$_date)][$plugin][$n];

		$events = $this->bo->search(array(
			'start' => $date['end'] ? $date['start'] : mktime(0,0,0,date('m',$_date),date('d',$_date),date('Y',$_date)),
			'end' => $date['end'] ? $date['end'] : mktime(23,59,59,date('m',$_date),date('d',$_date),date('Y',$_date)),
			'offset' => 0,
			'num_rows' => 20,
			'order' => 'cal_start',
			'daywise' => true
		));
		$replacements = array();
		foreach($events as $day => $list) 
		{
			$i = 0;
			foreach($list as $key => $event)
			{
				$days[date('Ymd',$_date)][date('l',$event['start'])][$i++] = $this->calendar_replacements($event);
			}
		}
		return $days[date('Ymd',$_date)][$plugin][0];
	}

	/**
	* Table plugin for participants
	*
	* Copied from eventmgr resources
	*
	* @param string $plugin
	* @param int $id
	* @param int $n
	* @return array
	*/
	public function participant($plugin,$id,$n)
	{
		if(!is_array($id) || !$id['start']) {
			$event = $this->bo->read(is_array($id) ? $id['id'] : $id, is_array($id) ? $id['recur_date'] : null);
		} else {
			$event = $id;
		}

		if(!is_array($event['participants']) || $n >= count($event['participants'])) return array();

		$participant = null;
		$status = null;
		$i = -1;
		foreach($event['participants'] as $participant => $status) {
			if(++$i == $n) break;
		}

		if(!$participant) return array();

		// Add some common information
		calendar_so::split_status($status,$quantity,$role);
		if ($role != 'REQ-PARTICIPANT')
		{
			if (isset($this->bo->roles[$role]))
			{
				$role = lang($this->bo->roles[$role]);
			}
			// allow to use cats as roles (beside regular iCal ones)
			elseif (substr($role,0,6) == 'X-CAT-' && ($cat_id = (int)substr($role,6)) > 0)
			{
				$role = $GLOBALS['egw']->categories->id2name($cat_id);
			}
			else
			{
				$role = lang(str_replace('X-','',$role));
			}
		}
		$info = array(
			'name'		=> $this->bo->participant_name($participant),
			'status'	=> $this->bo->verbose_status[$status],
			'quantity'	=> $quantity,
			'role'		=> $role
		);
		
		switch ($participant[0])
		{
			case 'c':
				$replacements = $this->contact_replacements(substr($participant,1),'');
				break;
			case 'r':
				if (is_null(self::$resources)) self::$resources = CreateObject('resources.bo_resources');
				if (($resource = self::$resources->read(substr($participant,1))))
				{
					foreach($resource as $name => $value)
					{
					    $replacements['$$'.$name.'$$'] = $value;
					}
				}
				break;
			default:
				if (is_numeric($participant) && ($contact = $GLOBALS['egw']->accounts->id2name($participant,'person_id')))
				{
					$replacements = $this->contact_replacements($contact,'');
				}
				break;
		}
		foreach($info as $name => $value)
		{
			$replacements['$$'.$name.'$$'] = $value;
		}
		return $replacements;
	}

	/**
	 * Generate table with replacements for the preferences
	 *
	 */
	public function show_replacements()
	{
		$GLOBALS['egw']->translation->add_app('calendar');
		$GLOBALS['egw_info']['flags']['app_header'] = lang('calendar').' - '.lang('Replacements for inserting events into documents');
		$GLOBALS['egw_info']['flags']['nonavbar'] = false;
		common::egw_header();

		echo "<table width='90%' align='center'>\n";
		echo '<tr><td colspan="4"><h3>'.lang('Calendar fields:')."</h3></td></tr>";

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
			echo '<td>$$'.$name.'$$</td><td>'.$label.'</td>';
			if ($n&1) echo "</tr>\n";
			$n++;
		}

		echo '<tr><td colspan="4"><h3>'.lang('Custom fields').":</h3></td></tr>";
		$custom = config::get_customfields('calendar');
		foreach($custom as $name => $field)
		{
			echo '<tr><td>$$#'.$name.'$$</td><td colspan="3">'.$field['label']."</td></tr>\n";
		}

		echo '<tr><td colspan="4"><h3>'.lang('Participant table').":</h3></td></tr>";
		echo '<tr><td colspan="4">$$table/participant$$ ... $$endtable$$</td></tr>';
		echo '<tr><td>$$name$$</td></tr>';
		echo '<tr><td>$$role$$</td></tr>';
		echo '<tr><td>$$quantity$$</td></tr>';
		echo '<tr><td>$$status$$</td></tr>';
		
		echo '<tr><td colspan="4"><h3>'.lang('Day of week tables').":</h3></td></tr>";
		$days = array();
		for($i = 0; $i < 7; $i++)
		{
			$days[date('N',strtotime("+$i days"))] = date('l',strtotime("+$i days"));
		}
		ksort($days);
		foreach($days as $day)
		{
			echo '<tr><td colspan="4">$$table/'.$day. '$$ ... $$endtable$$</td></tr>';
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
			'LETTERPREFIX' => lang('Example $$LETTERPREFIX$$ - Gives a letter prefix without double spaces, if the title is emty for  example'),
			'LETTERPREFIXCUSTOM' => lang('Example $$LETTERPREFIXCUSTOM n_prefix title n_family$$ - Example: Mr Dr. James Miller'),
			) as $name => $label)
		{
			echo '<tr><td>$$'.$name.'$$</td><td colspan="3">'.$label."</td></tr>\n";
		}

		echo "</table>\n";

		common::egw_footer();
	}
}
