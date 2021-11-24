<?php
/**
 * EGroupware Calendar - document merge
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Nathan Gray
 * @package calendar
 * @copyright (c) 2007-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright 2011 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;

/**
 * Calendar - document merge object
 */
class calendar_merge extends Api\Storage\Merge
{
	/**
	 * Functions that can be called via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'download_by_request' => true,
		'show_replacements'   => true,
		'merge_entries'       => true
	);

	// Object for getting calendar info
	protected $bo;

	// Object used for getting resource info
	protected static $resources;

	/**
	 * Recognised relative days - used as a day table, like day_<n>
	 */
	protected static $relative = array(
		'today',
		'tomorrow',
		'yesterday',
		'selected',
	);

	/**
	 * If you use a range, these extra tags are available
	 */
	protected static $range_tags = array(
		'start' => 'Y-m-d',
		'end'   => 'Y-m-d',
		'month' => 'F',
		'year'  => 'Y'
	);

	/**
	 * Base query for all event searches
	 */
	protected $query = array();

	/**
	 * Stored IDs, if user passed in ID / events instead of date range
	 * We keep the IDs then filter the events in the range to only the selected
	 * IDs
	 */
	protected $ids = array();

	/**
	 * Constructor
	 */
	function __construct()
	{
		parent::__construct();

		// overwrite global export-limit, if one is set for calendar/appointments
		$this->export_limit = Api\Storage\Merge::getExportLimit('calendar');

		// switch of handling of Api\Html formated content, if Api\Html is not used
		$this->parse_html_styles = Api\Storage\Customfields::use_html('calendar');
		$this->bo = new calendar_boupdate();

		self::$range_tags['start'] = $GLOBALS['egw_info']['user']['preferences']['common']['dateformat'];
		self::$range_tags['end'] = $GLOBALS['egw_info']['user']['preferences']['common']['dateformat'];

		// Register table plugins
		$this->table_plugins['participant'] = 'participant';
		for($i = 0; $i < 7; $i++)
		{
			$this->table_plugins[date('l', strtotime("+$i days"))] = 'day_plugin';
		}
		for($i = 1; $i <= 31; $i++)
		{
			$this->table_plugins['day_' . $i] = 'day'; // Numerically by day number (1-31)
		}
		foreach(self::$relative as $day)
		{
			$this->table_plugins[$day] = 'day'; // Current day
		}
		$this->query = is_array($this->bo->cal_prefs['saved_states']) ?
			$this->bo->cal_prefs['saved_states'] : unserialize($this->bo->cal_prefs['saved_states']);

		$this->query['users'] = is_array($this->query['owner']) ? $this->query['owner'] : explode(',', $this->query['owner']);
		$this->query['num_rows'] = -1;
	}

	/**
	 * Merges a given document with contact data
	 *
	 * Overridden from parent to be able to change a list of events into a range,
	 * if the target document has no pagerepeat tag.  Otherwise, parent::merge_string()
	 * would fail because we're trying to merge multiple records with no pagerepeat tag.
	 *
	 *
	 * @param string $content
	 * @param array $ids array with contact id(s)
	 * @param string &$err error-message on error
	 * @param string $mimetype mimetype of complete document, eg. text/*, application/vnd.oasis.opendocument.text, application/rtf
	 * @param array $fix =null regular expression => replacement pairs eg. to fix garbled placeholders
	 * @param string $charset =null charset to override default set by mimetype or export charset
	 * @return string|boolean merged document or false on error
	 */
	function &merge_string($content, $ids, &$err, $mimetype, array $fix = null, $charset = null)
	{
		$ids = $this->validate_ids((array)$ids, $content);

		return parent::merge_string($content, $ids, $err, $mimetype, $fix, $charset);
	}

	public static function merge_entries(array $ids = null, \EGroupware\Api\Storage\Merge &$document_merge = null, $pdf = null)
	{
		$document_merge = new calendar_merge();

		if(is_null(($ids)))
		{
			$ids = json_decode($_REQUEST['id'], true);
		}

		// Try to make time span into appropriate ranges to match
		$template = $ids['view'] ?: '';
		if(stripos($_REQUEST['document'], 'month') !== false || stripos($_REQUEST['document'], lang('month')) !== false)
		{
			$template = 'month';
		}
		if(stripos($_REQUEST['document'], 'week') !== false || stripos($_REQUEST['document'], lang('week')) !== false)
		{
			$template = 'week';
		}

		//error_log("Detected template $template");
		$date = $ids['date'];
		$first = $ids['first'];
		$last = $ids['last'];

		// Pull dates from session if they're not in the request
		if(!array_key_exists('first', $ids))
		{
			$ui = new calendar_ui();
			$date = $ui->date;
			$first = $ui->first;
			$last = $ui->last;
		}
		switch($template)
		{
			case 'month':
				// Trim to _only_ the month, do not pad to week start / end
				$time = new Api\DateTime($date);
				$timespan = array(array(
									  'start' => Api\DateTime::to($time->format('Y-m-01 00:00:00'), 'ts'),
									  'end'   => Api\DateTime::to($time->format('Y-m-t 23:59:59'), 'ts')
								  ));
				break;
			case 'week':
				$timespan = array();
				$start = new Api\DateTime($first);
				$end = new Api\DateTime($last);
				$t = clone $start;
				$t->modify('+1 week')->modify('-1 second');
				if($t < $end)
				{
					do
					{
						$timespan[] = array(
							'start' => $start->format('ts'),
							'end'   => $t->format('ts')
						);
						$start->modify('+1 week');
						$t->modify('+1 week');
					}
					while($start < $end);
					break;
				}
			// Fall through
			default:
				$timespan = array(array(
									  'start' => $first,
									  'end'   => $last
								  ));
		}

		// Add path into document
		static::check_document($_REQUEST['document'], $GLOBALS['egw_info']['user']['preferences']['calendar']['document_dir']);

		return \EGroupware\Api\Storage\Merge::merge_entries(array_key_exists('0', $ids) ? $ids : $timespan, $document_merge);
	}

	public function get_filename_placeholders($document, $ids)
	{
		$placeholders = parent::get_filename_placeholders($document, $ids);

		$request = json_decode($_REQUEST['id'], true) ?: [];
		$template = $ids['view'] ?: '';
		if(stripos($document, 'month') !== false || stripos($document, lang('month')) !== false)
		{
			$template = 'month';
		}
		if(stripos($document, 'week') !== false || stripos($document, lang('week')) !== false)
		{
			$template = 'week';
		}

		$placeholders['$$span$$'] = lang($template);
		$placeholders['$$first$$'] = Api\DateTime::to($ids['first'] ?: $request['first'], true);
		$placeholders['$$last$$'] = Api\DateTime::to($ids['last'] ?: $request['last'], true);
		$placeholders['$$date$$'] = Api\DateTime::to($ids['date'] ?: $request['date'], true);

		return $placeholders;
	}

	/**
	 * Get replacements
	 *
	 * @param int|array $id event-id array with id,recur_date, or array with search parameters
	 * @param string &$content =null content to create some replacements only if they are used
	 * @return array|boolean
	 */
	protected function get_replacements($id, &$content = null)
	{
		$prefix = '';
		// List events ?
		if(is_array($id) && !$id['id'] && !$id[0]['id'])
		{
			$events = $this->bo->search($this->query + $id + array(
											'offset' => 0,
											'order'  => 'cal_start',
											'cfs'    => strpos($content, '#') !== false ? array_keys(Api\Storage\Customfields::get('calendar')) : null
										)
			);
			if(strpos($content, '$$calendar/') !== false || strpos($content, '$$table/day') !== false)
			{
				array_unshift($events, false);
				unset($events[0]);    // renumber the array to start with key 1, instead of 0
				$prefix = 'calendar/%d';
			}
		}
		elseif(is_array($id) && $id[0]['id'])
		{
			// Passed an array of events, to be handled like a date range
			$events = $id;
			$id = array($this->events_to_range($id));
		}
		else
		{
			$events = array($id);
		}
		// as this function allows to pass query- parameters, we need to check the result of the query against export_limit restrictions
		if(Api\Storage\Merge::hasExportLimit($this->export_limit) && !Api\Storage\Merge::is_export_limit_excepted() && count($events) > (int)$this->export_limit)
		{
			$err = lang('No rights to export more than %1 entries!', (int)$this->export_limit);
			throw new Api\Exception\WrongUserinput($err);
		}
		$replacements = array();
		$n = 0;
		foreach($events as $event)
		{
			$event_id = $event['id'] . ($event['recur_date'] ? ':' . $event['recur_date'] : '');
			if($this->ids && !in_array($event_id, $this->ids)) continue;
			$values = $this->calendar_replacements($event, sprintf($prefix, ++$n), $content);
			if(is_array($id) && $id['start'])
			{
				foreach(self::$range_tags as $key => $format)
				{
					$value = Api\DateTime::to($key == 'end' ? $id['end'] : $id['start'], $format);
					if($key == 'month') $value = lang($value);
					$values["$\$range/$key$$"] = $value;
				}
			}
			$replacements += $values;
		}
		return $replacements;
	}

	/**
	 * Return replacements for the calendar
	 *
	 * @param int|array $id event-id or array with id/recur_date, or array with event info
	 * @param boolean $last_event_too =false also include information about the last event
	 * @return array
	 */
	public function calendar_replacements($id, $prefix = '', &$content = '')
	{
		$replacements = array();
		if(!is_array($id) || !$id['start'])
		{
			if(is_string($id) && strpos($id, ':'))
			{
				$_id = $id;
				$id = array();
				list($id['id'], $id['recur_date']) = explode(':', $_id);
			}
			$event = $this->bo->read(is_array($id) ? $id['id'] : $id, is_array($id) ? $id['recur_date'] : null);
		}
		else
		{
			$event = $id;
		}

		$record = new calendar_egw_record($event['id']);

		// Convert to human friendly values
		$types = calendar_egw_record::$types;
		importexport_export_csv::convert($record, $types, 'calendar');

		$array = $record->get_record_array();
		foreach($array as $key => $value)
		{
			$replacements['$$' . ($prefix ? $prefix . '/' : '') . $key . '$$'] = $value;
		}

		$replacements['$$' . ($prefix ? $prefix . '/' : '') . 'calendar_id' . '$$'] = $event['id'];
		foreach($this->bo->event2array($event) as $name => $data)
		{
			if (substr($name,-4) == 'date') $name = substr($name,0,-4);
			$replacements['$$' . ($prefix ? $prefix . '/' : '') . 'calendar_' . $name . '$$'] = is_array($data['data']) ? implode(', ', $data['data']) : $data['data'];
		}
		// Add seperate lists of participants by type
		if(strpos($content, 'calendar_participants/') !== false)
		{
			$types = array();
			foreach($this->bo->resources as $resource)
			{
				$types[$resource['app']] = array();
			}
			foreach($event['participants'] as $uid => $status)
			{
				$type = $this->bo->resources[$uid[0]]['app'];
				if($type == 'api-accounts')
				{
					$type = ($GLOBALS['egw']->accounts->get_type($uid) == 'g' ? 'group' : 'account');
				}
				$types[$type][] = $this->bo->participant_name($uid);
			}
			foreach($types as $t_id => $type)
			{
				$replacements['$$' . ($prefix ? $prefix . '/' : '') . "calendar_participants/{$t_id}$$"] = implode(', ', $type);
			}
		}
		// Participant email list (not declined)
		$this->participant_emails($replacements, $record, $prefix, $content);

		// Add participant summary
		$this->participant_summary($replacements, $record, $prefix, $content);

		if(!$replacements['$$' . ($prefix ? $prefix . '/' : '') . 'calendar_recur_type$$'])
		{
			// Need to set it to '' if not set or previous record may be used
			$replacements['$$' . ($prefix ? $prefix . '/' : '') . 'calendar_recur_type$$'] = '';
		}
		foreach(array('start', 'end') as $what)
		{
			foreach(array(
						'date' => $GLOBALS['egw_info']['user']['preferences']['common']['dateformat'],
						'day'  => 'l',
						'time' => (date('Ymd', $event['start']) != date('Ymd', $event['end']) ? $GLOBALS['egw_info']['user']['preferences']['common']['dateformat'] . ' ' : '') . ($GLOBALS['egw_info']['user']['preferences']['common']['timeformat'] == 12 ? 'h:i a' : 'H:i'),
					) as $name => $format)
			{
				$value = Api\DateTime::to($event[$what], $format);
				if($format == 'l')
				{
					$value = lang($value);
				}
				$replacements['$$' . ($prefix ? $prefix . '/' : '') . 'calendar_' . $what . $name . '$$'] = $value;
			}
		}
		$duration = ($event['end'] - $event['start']) / 60;
		$replacements['$$' . ($prefix ? $prefix . '/' : '') . 'calendar_duration$$'] = floor($duration / 60) . lang('h') . ($duration % 60 ? $duration % 60 : '');

		// Add in contact stuff for owner
		if(strpos($content, '$$calendar_owner/') !== null && ($user = $GLOBALS['egw']->accounts->id2name($event['owner'], 'person_id')))
		{
			$replacements += $this->contact_replacements($user, ($prefix ? $prefix . '/' : '') . 'calendar_owner');
			$replacements['$$' . ($prefix ? $prefix . '/' : '') . 'calendar_owner/primary_group$$'] = $GLOBALS['egw']->accounts->id2name($GLOBALS['egw']->accounts->id2name($event['owner'], 'account_primary_group'));
		}

		if($content && strpos($content, '$$#') !== FALSE)
		{
			$this->cf_link_to_expand($event, $content, $replacements);
		}

		// Links
		$replacements += $this->get_all_links('calendar', $event['id'], $prefix, $content);

		return $replacements;
	}

	/**
	 * Generate placeholder(s) for email addresses of all participants who have
	 * them.
	 *
	 * @param Array $replacements Array of replacements
	 * @param calendar_egw_record $record Event record
	 * @param string $prefix Prefix of placeholder
	 * @param string $content Content with placeholders in it
	 */
	public function participant_emails(&$replacements, &$record, $prefix, &$content)
	{
		// Early exit if the placeholder is not used
		if(strpos($content, '$$' . ($prefix ? $prefix . '/' : '') . 'participant_emails$$') === FALSE)
		{
			return false;
		}

		$emails = array();
		$event = array(
			'participants' => $record->participants
		);
		$this->bo->enum_groups($event);
		foreach($event['participants'] as $uid => $status)
		{
			// Skip rejected
			if(in_array(substr($status, 0, 1), array('R')))
			{
				continue;
			}

			$info = $this->bo->resource_info($uid);
			if($info['email'])
			{
				$emails[] = $info['email'];
			}
		}
		$replacements['$$' . ($prefix ? $prefix . '/' : '') . 'participant_emails$$'] = implode(', ', $emails);
	}

	/**
	 * Generate placeholder for a summary of participant status:
	 * 3 Participants: 1 Accepted, 2 Unknown
	 *
	 * Blank if only one participant, matches what's shown in UI event hover
	 *
	 * @param Array $replacements Array of replacements
	 * @param calendar_egw_record $record Event record
	 * @param string $prefix Prefix of placeholder
	 * @param string $content Content with placeholders in it
	 */
	public function participant_summary(&$replacements, &$record, $prefix, &$content)
	{
		// Early exit if the placeholder is not used
		if(strpos($content, '$$' . ($prefix ? $prefix . '/' : '') . 'participant_summary$$') === FALSE)
		{
			return false;
		}

		$placeholder = '$$' . ($prefix ? $prefix . '/' : '') . 'participant_summary$$';

		// No summary for 1 participant
		if(count($record->participants) < 2)
		{
			$replacements[$placeholder] = '';
		}

		$participant_status = array('A' => 0, 'R' => 0, 'T' => 0, 'U' => 0, 'D' => 0);
		$status_label = array('A' => 'accepted', 'R' => 'rejected', 'T' => 'tentative', 'U' => 'unknown',
							  'D' => 'delegated');
		$participant_summary = count($record->participants) . ' ' . lang('Participants') . ': ';
		$status_totals = [];

		foreach($record->participants as $uid => $status)
		{
			$participant_status[substr($status, 0, 1)]++;
		}
		foreach($participant_status as $status => $count)
		{
			if($count > 0)
			{
				$status_totals[] = $count . ' ' . lang($status_label[$status]);
			}
		}
		$summary = $participant_summary . join(', ', $status_totals);
		$replacements[$placeholder] = $summary;
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
	 * @param int $n Row number
	 * @param string $repeat Text being repeated for each entry
	 * @return array
	 */
	public function day_plugin($plugin, $date, $n, $repeat)
	{
		static $days = null;
		if(is_array($date) && !$date['start'])
		{
			// List of IDs
			if($date[0]['start'])
			{
				$id = array('start' => PHP_INT_MAX, 'end' => 0);
				foreach($date as $event)
				{
					if($event['start'] && $event['start'] < $id['start'])
					{
						$id['start'] = $event['start'];
					}
					if($event['end'] && $event['end'] > $id['end'])
					{
						$id['end'] = $event['end'];
					}
				}
				$date = $id;
			}
			else
			{
				$event = $this->bo->read(is_array($date) ? $date['id'] : $date, is_array($date) ? $date['recur_date'] : null);
				if(date('l', $event['start']) != $plugin)
				{
					return array();
				}
				$date = $event['start'];
			}
		}

		$_date = new Api\DateTime(['start'] ? $date['start'] : $date);
		if($days[$_date->format('Ymd')][$plugin])
		{
			return $days[$_date->format('Ymd')][$plugin][$n];
		}

		$events = $this->bo->search($this->query + array(
										'start'    => $date['end'] ? $date['start'] : mktime(0, 0, 0, date('m', $_date), date('d', $_date), date('Y', $_date)),
										'end'      => $date['end'] ? $date['end'] : mktime(23, 59, 59, date('m', $_date), date('d', $_date), date('Y', $_date)),
										'offset'   => 0,
										'num_rows' => 20,
										'order'    => 'cal_start',
										'daywise'  => true,
										'cfs'      => array(),    // read all custom-fields
									)
		);

		if (true) $days = array();
		$replacements = array();
		$time_format = $GLOBALS['egw_info']['user']['preferences']['common']['timeformat'] == 12 ? 'h:i a' : 'H:i';
		foreach($events as $day => $list)
		{
			foreach($list as $event)
			{
				$event_id = $event['id'] . ($event['recur_date'] ? ':' . $event['recur_date'] : '');
				if($this->ids && !in_array($event_id, $this->ids))
				{
					continue;
				}
				$start = Api\DateTime::to($event['start'], 'array');
				$end = Api\DateTime::to($event['end'], 'array');
				$replacements = $this->calendar_replacements($event);
				if($start['year'] == $end['year'] && $start['month'] == $end['month'] && $start['day'] == $end['day'])
				{
					$dow = date('l', $event['start']);
				}
				else
				{
					$dow = date('l', strtotime($day));
					// Fancy date+time formatting for multi-day events
					$replacements['$$calendar_starttime$$'] = date($time_format, $day == date('Ymd', $event['start']) ? $event['start'] : mktime(0, 0, 0, 0, 0, 1));
					$replacements['$$calendar_endtime$$'] = date($time_format, $day == date('Ymd', $event['end']) ? $event['end'] : mktime(23, 59, 59, 0, 0, 0));
				}

				$days[$_date->format('Ymd')][$dow][] = $replacements;
			}
			if(strpos($repeat, 'day/date') !== false || strpos($repeat, 'day/name') !== false)
			{
				$date_marker = array(
					'$$day/date$$' => date($GLOBALS['egw_info']['user']['preferences']['common']['dateformat'], strtotime($day)),
					'$$day/name$$' => lang(date('l', strtotime($day)))
				);
				if(!is_array($days[$_date->format('Ymd')][date('l', strtotime($day))]))
				{
					$blank = $this->calendar_replacements(array());
					foreach($blank as &$value)
					{
						$value = '';
					}
					$days[$_date->format('Ymd')][date('l', strtotime($day))][] = $blank;
				}
				$days[$_date->format('Ymd')][date('l', strtotime($day))][0] += $date_marker;
			}
			// Add in birthdays
			if(strpos($repeat, 'day/birthdays') !== false)
			{
				$days[$_date->format('Ymd')][date('l', strtotime($day))][0]['$$day/birthdays$$'] = $this->get_birthdays($day);
			}
		}
		return $days[$_date->format('Ymd')][$plugin][0];
	}

	/**
	 * Table plugin for a certain date
	 *
	 * Can be either a particular date (2011-02-15) or a day of the month (15)
	 *
	 * @param string $plugin
	 * @param int $id ID for this record
	 * @param int $n Repeated row number
	 * @param string $repeat Text being repeated for each entry
	 * @return array
	 */
	public function day($plugin, $id, $n, $repeat)
	{
		static $days = null;

		// Figure out which day
		list($type, $which) = explode('_', $plugin);
		if($type == 'day' && $which)
		{
			$arr = $this->bo->date2array($id['start']);
			$arr['day'] = $which;
			$date = $this->bo->date2ts($arr);
			if(is_array($id) && $id['start'] && ($date < $id['start'] || $date > $id['end']))
			{
				return array();
			}
		}
		elseif($plugin == 'selected')
		{
			$date = $id['start'];
		}
		else
		{
			$date = strtotime($plugin);
		}
		if($type == 'day' && is_array($id) && !$id['start'])
		{
			$event = $this->bo->read(is_array($id) ? $id['id'] : $id, is_array($id) ? $id['recur_date'] : null);
			if($which && date('d', $event['start']) != $which)
			{
				return array();
			}
			if(date('Ymd', $date) != date('Ymd', $event['start']))
			{
				return array();
			}
			return $n == 0 ? $this->calendar_replacements($event) : array();
		}

		// Use start for cache, in case of multiple months
		$_date = $id['start'] ? $id['start'] : $date;
		if($days[date('Ymd', $_date)][$plugin]) return $days[date('Ymd', $_date)][$plugin][$n];

		$events = $this->bo->search($this->query + array(
										'start'    => $date,
										'end'      => mktime(23, 59, 59, date('m', $date), date('d', $date), date('Y', $date)),
										'offset'   => 0,
										'num_rows' => 20,
										'order'    => 'cal_start',
										'daywise'  => true,
										'cfs'      => array(),    // read all custom-fields
									)
		);

		$replacements = array();
		if (true) $days = array();
		$time_format = $GLOBALS['egw_info']['user']['preferences']['common']['timeformat'] == 12 ? 'h:i a' : 'H:i';
		foreach($events as $day => $list)
		{
			foreach($list as $event)
			{
				$event_id = $event['id'] . ($event['recur_date'] ? ':' . $event['recur_date'] : '');
				if($this->ids && !in_array($event_id, $this->ids))
				{
					continue;
				}
				$start = Api\DateTime::to($event['start'], 'array');
				$end = Api\DateTime::to($event['end'], 'array');
				$replacements = $this->calendar_replacements($event);
				if($start['year'] == $end['year'] && $start['month'] == $end['month'] && $start['day'] == $end['day'])
				{
					//$dow = date('l',$event['start']);
				}
				else
				{
					// Fancy date+time formatting for multi-day events
					$replacements['$$calendar_starttime$$'] = date($time_format, $day == date('Ymd', $event['start']) ? $event['start'] : mktime(0, 0, 0, 0, 0, 1));
					$replacements['$$calendar_endtime$$'] = date($time_format, $day == date('Ymd', $event['end']) ? $event['end'] : mktime(23, 59, 59, 0, 0, 0));
				}
				$days[date('Ymd', $_date)][$plugin][] = $replacements;
			}
			if(strpos($repeat, 'day/date') !== false || strpos($repeat, 'day/name') !== false)
			{
				$date_marker = array(
					'$$day/date$$' => date($GLOBALS['egw_info']['user']['preferences']['common']['dateformat'], strtotime($day)),
					'$$day/name$$' => lang(date('l', strtotime($day)))
				);
				if(!is_array($days[date('Ymd', $_date)][$plugin]))
				{
					$blank = $this->calendar_replacements(array());
					foreach($blank as &$value)
					{
						$value = '';
					}
					$days[date('Ymd', $_date)][$plugin][] = $blank;
				}
				$days[date('Ymd', $_date)][$plugin][0] += $date_marker;
			}
			// Add in birthdays
			if(strpos($repeat, 'day/birthdays') !== false)
			{
				$days[date('Ymd', $_date)][date('l', strtotime($day))][0]['$$day/birthdays$$'] = $this->get_birthdays($day);
			}
		}
		return $days[date('Ymd', $_date)][$plugin][0];
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
	public function participant($plugin, $id, $n)
	{
		unset($plugin);    // not used, but required by function signature

		if(!is_array($id) || !$id['start'])
		{
			$event = $this->bo->read(is_array($id) ? $id['id'] : $id, is_array($id) ? $id['recur_date'] : null);
		}
		else
		{
			$event = $id;
		}

		if(!is_array($event['participants']) || $n >= count($event['participants']))
		{
			return array();
		}

		$participant = null;
		$status = null;
		$i = -1;
		foreach($event['participants'] as $participant => $status)
		{
			if(++$i == $n)
			{
				break;
			}
		}

		if(!$participant)
		{
			return array();
		}

		// Add some common information
		$quantity = $role = null;
		calendar_so::split_status($status, $quantity, $role);
		if($role != 'REQ-PARTICIPANT')
		{
			if(isset($this->bo->roles[$role]))
			{
				$role = lang($this->bo->roles[$role]);
			}
			// allow to use cats as roles (beside regular iCal ones)
			elseif(substr($role, 0, 6) == 'X-CAT-' && ($cat_id = (int)substr($role, 6)) > 0)
			{
				$role = $GLOBALS['egw']->categories->id2name($cat_id);
			}
			else
			{
				$role = lang(str_replace('X-', '', $role));
			}
		}
		$info = array(
			'name'     => $this->bo->participant_name($participant),
			'status'   => lang($this->bo->verbose_status[$status]),
			'quantity' => $quantity,
			'role'     => $role
		);

		switch($participant[0])
		{
			case 'c':
				$replacements = $this->contact_replacements(substr($participant, 1), '');
				break;
			case 'r':
				if(is_null(self::$resources))
				{
					self::$resources = new resources_bo();
				}
				if(($resource = self::$resources->read(substr($participant, 1))))
				{
					foreach($resource as $name => $value)
					{
						$replacements['$$' . $name . '$$'] = $value;
					}
				}
				break;
			default:
				if(is_numeric($participant) && ($contact = $GLOBALS['egw']->accounts->id2name($participant, 'person_id')))
				{
					$replacements = $this->contact_replacements($contact, '');
				}
				break;
		}
		foreach($info as $name => $value)
		{
			$replacements['$$' . $name . '$$'] = $value;
		}
		return $replacements;
	}

	/**
	 * Get replacement for birthdays placeholder
	 * @param String $day Date in Ymd format
	 */
	protected function get_birthdays($day)
	{
		$contacts = new Api\Contacts();
		$birthdays = array();
		foreach($contacts->get_addressbooks() as $owner => $name)
		{
			$birthdays += $contacts->read_birthdays($owner, substr($day, 0, 4));
		}
		return $birthdays[$day] ? implode(', ', array_column($birthdays[$day], 'name')) : '';
	}

	/**
	 * Validate and properly format a list of 'ID's into either a list of ranges
	 * or a list of IDs, depending on what the template needs.  Templates using
	 * the range placeholder need a list of date ranges, templates using pagerepeat
	 * need a list of individual events.  Templates using neither get just the
	 * first ID
	 *
	 * @param Array[]|String[] $ids List of IDs, which can be a list of individual
	 *    event IDs, entire events, a date range (start & end) or a list of date ranges.
	 * @param String $content Template content, used to determine what style of
	 *    ID is needed.
	 */
	protected function validate_ids(array $ids, $content)
	{
		$validated_ids = array();
		if((strpos($content, '$$range') !== false || strpos($content, '{{range') !== false) && is_array($ids))
		{
			// Merging into a template that uses range - need ranges, got events
			if (is_array($ids) && (is_array($ids[0]) && isset($ids[0]['id']) || is_string($ids[0])))
			{
				// Passed an array of events, to be handled like a date range
				$events = $ids;
				$validated_ids = (array)$this->events_to_range($ids);
			}
			else if(is_array($ids) && $ids[0]['start'])
			{
				// Got a list of ranges
				$validated_ids = $ids;
			}
		}
		// Handle merging a range of events into a document with pagerepeat instead of range
		else if((strpos($content, '$$pagerepeat') !== false || strpos($content, '{{pagerepeat') !== false)
			&& ((strpos($content, '$$range') === false && strpos($content, '{{range') === false)))
		{
			if (is_array($ids) && !(is_array($ids[0]) && isset($ids[0]['id']) || is_string($ids[0])))
			{
				foreach($ids as $range)
				{
					// Passed a range, needs to be expanded into list of events
					$events = $this->bo->search($this->query + $range + array(
													'offset'        => 0,
													'enum_recuring' => true,
													'order'         => 'cal_start',
													'cfs'           => strpos($content, '#') !== false ? array_keys(Api\Storage\Customfields::get('calendar')) : null
												)
					);
					foreach($events as $event)
					{
						$validated_ids[] = $event;
					}
				}
			}
			else
			{
				foreach($ids as $id)
				{
					$validated_ids[] = $this->normalize_event_id($id);
				}
			}
		}
		else
		{
			$validated_ids[] = $this->normalize_event_id(array_shift($ids));
		}

		return $validated_ids;
	}

	/**
	 * Convert a list of event IDs into a range
	 *
	 * @param String[]|Array[] $ids Some event identifier, in either string or array form
	 */
	protected function events_to_range($ids)
	{
		$limits = array('start' => PHP_INT_MAX, 'end' => 0);
		$this->ids = array();
		foreach($ids as $event)
		{
			$event = $this->normalize_event_id($event);

			if($event['start'] && Api\DateTime::to($event['start'], 'ts') < $limits['start'])
			{
				$limits['start'] = Api\DateTime::to($event['start'], 'ts');
			}
			if($event['end'] && Api\DateTime::to($event['end'], 'ts') > $limits['end'])
			{
				$limits['end'] = Api\DateTime::to($event['end'], 'ts');
			}
			// Keep ids for future use
			if($event['id'])
			{
				$this->ids[] = $event['id'] . ($event['recur_date'] ? ':' . $event['recur_date'] : '');
			}
		}
		// Check a start was found
		if($limits['start'] == PHP_INT_MAX)
		{
			// Start of today
			$limits['start'] = mktime(0, 0, 0);
		}
		// Check an end was found
		if($limits['end'] == 0)
		{
			// End of today
			$limits['end'] = mktime(25, 59, 59);
		}
		$limits['start'] = new Api\DateTime($limits['start']);
		$limits['end'] = new Api\DateTime($limits['end']);

		// Align with user's week
		$limits['start']->setTime(0, 0);
		$limits['start']->setWeekstart();

		// Ranges should be at most a week, since that's what our templates expect
		$rrule = new calendar_rrule($limits['start'], calendar_rrule::WEEKLY, 1, $limits['end']);
		$rrule->rewind();
		do
		{
			$current = $rrule->current();
			$rrule->next_no_exception();
			$validated_ids[] = array(
				'start' => Api\DateTime::to($current, 'ts'),
				'end'   => Api\DateTime::to($rrule->current(), 'ts') - 1
			);
		}
		while($rrule->valid());

		return $validated_ids;
	}

	/**
	 * Normalize a calendar event ID into a standard array.
	 *
	 * Depending on where they come from, IDs can be passed in as colon separated,
	 * an array with ID & recur_date, or be a full event.  They can also be a
	 * date range with start and end, rather than a single event.
	 *
	 * @param String|Array $id Some record identifier, in either string or array form
	 *
	 * @param Array If an id for a single event is passed in, an array with id & recur_date,
	 *    otherwise a range with start & end.
	 */
	protected function normalize_event_id($id)
	{
		if(is_string($id) || is_array($id) && !empty($id['id']) && empty($id['start']))
		{
			if (is_string($id) && strpos($id, ':'))
			{
				$_id = $id;
				$id = array();
				list($id['id'], $id['recur_date']) = explode(':', $_id);
			}
			$event = $this->bo->read(is_array($id) ? $id['id'] : $id, is_array($id) ? $id['recur_date'] : null);
		}
		else
		{
			$event = $id;
		}

		return $event;
	}

	/**
	 * Generate table with replacements for the preferences
	 *
	 */
	public function show_replacements()
	{
		Api\Translation::add_app('calendar');
		$GLOBALS['egw_info']['flags']['app_header'] = lang('calendar') . ' - ' . lang('Replacements for inserting events into documents');
		$GLOBALS['egw_info']['flags']['nonavbar'] = true;
		echo $GLOBALS['egw']->framework->header();

		echo "<table width='90%' align='center'>\n";
		echo '<tr><td colspan="4"><h3>' . lang('Calendar fields:') . "</h3></td></tr>";

		$n = 0;
		foreach(array(
					'calendar_id'           => lang('Calendar ID'),
					'calendar_title'        => lang('Title'),
					'calendar_description'  => lang('Description'),
					'calendar_participants' => lang('Participants'),
					'calendar_location'     => lang('Location'),
					'calendar_start'        => lang('Start') . ': ' . lang('Date') . '+' . lang('Time'),
					'calendar_startday'     => lang('Start') . ': ' . lang('Weekday'),
					'calendar_startdate'    => lang('Start') . ': ' . lang('Date'),
					'calendar_starttime'    => lang('Start') . ': ' . lang('Time'),
					'calendar_end'          => lang('End') . ': ' . lang('Date') . '+' . lang('Time'),
					'calendar_endday'       => lang('End') . ': ' . lang('Weekday'),
					'calendar_enddate'      => lang('End') . ': ' . lang('Date'),
					'calendar_endtime'      => lang('End') . ': ' . lang('Time'),
					'calendar_duration'     => lang('Duration'),
					'calendar_category'     => lang('Category'),
					'calendar_priority'     => lang('Priority'),
					'calendar_updated'      => lang('Updated'),
					'calendar_recur_type'   => lang('Repetition'),
					'calendar_access'       => lang('Access') . ': ' . lang('public') . ', ' . lang('private'),
					'calendar_owner'        => lang('Owner'),
				) as $name => $label)
		{
			if(in_array($name, array('start',
									 'end')) && $n & 1)        // main values, which should be in the first column
			{
				echo "</tr>\n";
				$n++;
			}
			if(!($n & 1))
			{
				echo '<tr>';
			}
			echo '<td>{{' . $name . '}}</td><td>' . $label . '</td>';
			if($n & 1)
			{
				echo "</tr>\n";
			}
			$n++;
		}

		echo '<tr><td colspan="4"><h3>' . lang('Range fields') . ":</h3></td></tr>";
		echo '<tr><td colspan="4">' . lang('If you select a range (month, week, etc) instead of a list of entries, these extra fields are available') . '</td></tr>';
		foreach(array_keys(self::$range_tags) as $name)
		{
			echo '<tr><td>{{range/' . $name . '}}</td><td>' . lang($name) . "</td></tr>\n";
		}
		echo '<tr><td colspan="4"><h3>' . lang('Custom fields') . ":</h3></td></tr>";
		$custom = Api\Storage\Customfields::get('calendar');
		foreach($custom as $name => $field)
		{
			echo '<tr><td>{{#' . $name . '}}</td><td colspan="3">' . $field['label'] . "</td></tr>\n";
		}


		echo '<tr><td colspan="4"><h3>' . lang('Participants') . ":</h3></td></tr>";
		echo '<tr><td>{{participant_emails}}</td><td colspan="3">' . lang('A list of email addresses of all participants who have not declined') . "</td></tr>\n";
		echo '<tr><td>{{participant_summary}}</td><td colspan="3">' . lang('Summary of participant status: 3 Participants: 1 Accepted, 2 Unknown') . "</td></tr>\n";
		echo '<tr><td colspan="4">' . lang('Participant names by type') . '</td></tr>';
		echo '<tr><td>{{calendar_participants/account}}</td><td colspan="3">' . lang('Accounts') . "</td></tr>\n";
		echo '<tr><td>{{calendar_participants/group}}</td><td colspan="3">' . lang('Groups') . "</td></tr>\n";
		foreach($this->bo->resources as $resource)
		{
			if($resource['type'])
			{
				echo '<tr><td>{{calendar_participants/' . $resource['app'] . '}}</td><td colspan="3">' . lang($resource['app']) . "</td></tr>\n";
			}
		}

		echo '<tr><td colspan="4"><h3>' . lang('Participant table') . ":</h3></td></tr>";
		echo '<tr><td colspan="4">{{table/participant}} ... </td></tr>';
		echo '<tr><td>{{name}}</td><td>' . lang('name') . '</td></tr>';
		echo '<tr><td>{{role}}</td><td>' . lang('role') . '</td></tr>';
		echo '<tr><td>{{quantity}}</td><td>' . lang('quantity') . '</td></tr>';
		echo '<tr><td>{{status}}</td><td>' . lang('status') . '</td></tr>';
		echo '<tr><td colspan="4">{{endtable}}</td></tr>';

		echo '<tr style="vertical-align:top"><td colspan="2"><table >';
		echo '<tr><td><h3>' . lang('Day of week tables') . ":</h3></td></tr>";
		$days = array();
		for($i = 0; $i < 7; $i++)
		{
			$days[date('N', strtotime("+$i days"))] = date('l', strtotime("+$i days"));
		}
		ksort($days);
		foreach($days as $day)
		{
			echo '<tr><td>{{table/' . $day . '}} ... {{endtable}}</td></tr>';
		}
		echo '</table></td><td colspan="2"><table >';
		echo '<tr><td><h3>' . lang('Daily tables') . ":</h3></td></tr>";
		foreach(self::$relative as $value)
		{
			echo '<tr><td>{{table/' . $value . '}} ... {{endtable}}</td></tr>';
		}
		echo '<tr><td>{{table/day_n}} ... {{endtable}}</td><td>1 <= n <= 31</td></tr>';
		echo '</table></td></tr>';
		echo '<tr><td colspan="2">' . lang('Available for the first entry inside each day of week or daily table inside the selected range:') . '</td></tr>';
		echo '<tr><td>{{day/date}}</td><td colspan="3">' . lang('Date for the day of the week') . '</td></tr>';
		echo '<tr><td>{{day/name}}</td><td colspan="3">' . lang('Name of the day of the week (ex: Monday)') . '</td></tr>';
		echo '<tr><td>{{day/birthdays}}</td><td colspan="3">' . lang('Birthdays') . '</td></tr>';

		echo '<tr><td colspan="4"><h3>' . lang('General fields:') . "</h3></td></tr>";
		foreach($this->get_common_replacements() as $name => $label)
		{
			echo '<tr><td>{{' . $name . '}}</td><td colspan="3">' . $label . "</td></tr>\n";
		}

		echo "</table>\n";

		echo $GLOBALS['egw']->framework->footer();
	}

	/**
	 * Get a list of placeholders provided.
	 *
	 * Placeholders are grouped logically.  Group key should have a user-friendly translation.
	 */
	public function get_placeholder_list($prefix = '')
	{
		$placeholders = array(
				'event'        => [],
				'range'        => [],
				'participant'  => [],
				'customfields' => []
			) + parent::get_placeholder_list($prefix);
		unset($placeholders['placeholders']);

		$fields = array(
			'calendar_id'          => lang('Calendar ID'),
			'calendar_title'       => lang('Title'),
			'calendar_description' => lang('Description'),
			'calendar_location'    => lang('Location'),
			'calendar_start'       => lang('Start') . ': ' . lang('Date') . '+' . lang('Time'),
			'calendar_startday'    => lang('Start') . ': ' . lang('Weekday'),
			'calendar_startdate'   => lang('Start') . ': ' . lang('Date'),
			'calendar_starttime'   => lang('Start') . ': ' . lang('Time'),
			'calendar_end'         => lang('End') . ': ' . lang('Date') . '+' . lang('Time'),
			'calendar_endday'      => lang('End') . ': ' . lang('Weekday'),
			'calendar_enddate'     => lang('End') . ': ' . lang('Date'),
			'calendar_endtime'     => lang('End') . ': ' . lang('Time'),
			'calendar_duration'    => lang('Duration'),
			'calendar_category'    => lang('Category'),
			'calendar_priority'    => lang('Priority'),
			'calendar_updated'     => lang('Updated'),
			'calendar_recur_type'  => lang('Repetition'),
			'calendar_access'      => lang('Access') . ': ' . lang('public') . ', ' . lang('private'),
			'calendar_owner'       => lang('Owner'),
		);
		$group = 'event';
		foreach($fields as $name => $label)
		{
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

		/**
		 * These ones only work if you select a range, not events
		 * $group = 'range';
		 * foreach(array_keys(self::$range_tags) as $name)
		 * {
		 * $marker = $this->prefix($prefix, "range/$name", '{');
		 * $placeholders[$group][] = [
		 * 'value' => $marker,
		 * 'label' => lang($name)
		 * ];
		 * }
		 */

		$group = 'participant';
		$placeholders[$group][] = array(
			'value' => '{{calendar_participants}}',
			'label' => lang('Participants')
		);
		$placeholders[$group][] = array(
			'value' => '{{participant_emails}}',
			'label' => 'Emails',
			'title' => lang('A list of email addresses of all participants who have not declined')
		);
		$placeholders[$group][] = array(
			'value' => '{{participant_summary}}',
			'label' => 'participant summary',
			'title' => lang('Summary of participant status: 3 Participants: 1 Accepted, 2 Unknown')
		);
		$placeholders[$group][] = array(
			'value' => '{{calendar_participants/account}}',
			'label' => lang('Accounts')
		);
		$placeholders[$group][] = array(
			'value' => '{{calendar_participants/group}}',
			'label' => lang('Groups')
		);
		foreach($this->bo->resources as $resource)
		{
			if($resource['type'])
			{
				$marker = $this->prefix($prefix, 'calendar_participants/' . $resource['app'], '{');
				$placeholders[$group][] = array(
					'value' => $marker,
					'label' => lang($resource['app'])
				);
			}
		}
		return $placeholders;
	}
}
