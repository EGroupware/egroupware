<?php

/**
 * EGroupware - Calendar's category report utility
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Hadi Nategh <hn-AT-egroupware.org>
 * @copyright (c) 2013-16 by Hadi Nategh <hn-AT-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Link;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;
use EGroupware\Api\Etemplate;

/**
 * This class reports amount of events taken by users based
 * on categories. The report would be based on start and end
 * date and can be specified with several options for each
 * category. The result of the report would be a CSV file
 * consistent of user full name, categories names, and amount
 * of time (hour|minute|second).
 *
 */
class calendar_category_report extends calendar_ui{

	/**
	 * Public functions allowed to get called
	 *
	 * @var type
	 */
	var $public_functions = array(
		'index' => True,
	);

	/**
	 * Constructor
	 */
	function __construct()
	{
		parent::__construct(true);	// call the parent's constructor
		$this->tmpl = new Api\Etemplate('calendar.category_report');
	}

	/**
	 * Function to check if the given date is a weekend date
	 *
	 * @param int $date timestamp as date
	 *
	 * @return boolean returns true if the date is weekend
	 */
	public static function isWeekend($date)
	{
		return (date('N',$date) >= 6);
	}

	/**
	 * Function to check if the given date is a holiday date
	 *
	 * @param int $_date timestamp as date
	 *
	 * @return boolean returns true if the date is holiday
	 */
	public function isHoliday($_date)
	{
		$holidays =  $this->bo->read_holidays(date('Y', $_date));
		$date = date('Ymd', $_date);
		foreach ($holidays[$date] as $holiday)
		{
			if (is_array($holiday) && !$holiday['birthyear']) return true;
		}
		return false;
	}

	/**
	 * This function processes given day array and select eligible events
	 *
	 * @param array $events_log array to keep multiple days/recurrence event in track
	 * @param array $week_sum array to keep tracking of weeks
	 * @param array $day array to keep tracking of eligible events of the day
	 * @param string $day_index string representation of the processing day
	 * @param array $events events of the day
	 * @param int $cat_id category id
	 * @param int $holidays holiday option
	 * @param int $weekend weekend option
	 * @param int $min_days min_days option
	 * @param int $unit unit option
	 *
	 */
	public function process_days(&$events_log, &$week_sum, &$day,$day_index, $events, $cat_id, $holidays, $weekend, $min_days, $unit, $start_range, $end_range)
	{
		foreach ($events as &$event)
		{
			foreach ($event['participants'] as $user_id => $status)
			{
				// if the participant has not accepted the event, not a chair or not an user then skip.
				if (!($status == 'A' || $status == 'ACHAIR') || preg_match('/^(.*<)?([a-z0-9_.-]+@[a-z0-9_.-]{5,})>?$/i',$user_id)) continue;

				$categories = explode(',', $event['category']);
				if (!in_array($cat_id, $categories)) continue;

				// processing day as timestamp
				$day_timestamp = strtotime($day_index);

				// week number
				$week_number = ltrim(date('W', $day_timestamp), '0');

				$previous_week_number = $week_number == 1? ($events_log[$user_id]['53']? 53: 52): $week_number -1;
				// check if multidays event starts before start range
				$is_over_range_event = $day_timestamp< $event['end'] && $start_range > $event['start'];
				// check if multidays event ends after end range
				$is_over_end_range = $day_timestamp< $event['end'] && $end_range < $event['end'];

				$is_multiple_days_event = $event['start']< $day_timestamp && $day_timestamp< $event['end'];


				if (($weekend && self::isWeekend($day_timestamp)) || (!$holidays && $this->isHoliday($day_timestamp)))
				{
					// calculate reduction of holidays or weekend amounts from
					// multidays event
					if ($is_multiple_days_event)
					{
						$day_diff_to_end = $event['end'] - $day_timestamp;
						$reduction_amount = $day_diff_to_end > 86400? 86400: $day_diff_to_end;
						$events_log['reductions'][$user_id][$cat_id] = $events_log['reductions'][$user_id][$cat_id] + $reduction_amount;
					}
					continue;
				}

				// Mark multidays event as counted after the first day of event, therefore
				// we can procced calculating the amount of the event via the first day
				// and mark as counted for the rest of the days to avoid miscalculation.
				if ($event['start']< $day_timestamp && $day_timestamp< $event['end'] &&
						($events_log[$user_id][$week_number][$event['id']]['counted'] ||
						$events_log[$user_id][$previous_week_number][$event['id']]['counted']))
				{
					$events_log[$user_id][$week_number][$event['id']]['counted'] = true;
					$events_log[$user_id][$week_number][$event['id']]['over_range'] = $is_over_range_event? true: false;
				}
				// In case of start range is in middle of multidays event, we need to calculate the
				// amount base on the part of event on the range and keep track of counting to avoid
				// miscalculation too.
				if ($is_over_range_event &&
						!$events_log[$user_id][$week_number][$event['id']]['over_range'] &&
						!$events_log[$user_id][$previous_week_number][$event['id']]['over_range'])
				{
					$events_log[$user_id][$week_number][$event['id']]['counted'] = false;
					$events_log[$user_id][$week_number][$event['id']]['over_range'] = true;
				}

				// if we already counted the multidays event, set the amount to 0
				// for the rest of days, to end up with a right calculation.
				if ($events_log[$user_id][$week_number][$event['id']]['counted'] && $is_multiple_days_event)
				{
					$amount = 0;
				}
				else
				{
					// over ranged multidays event
					if ($is_over_range_event && $is_over_end_range && $is_multiple_days_event)
					{
						$amount = $end_range - $start_range;
					}
					else if ($is_over_range_event)
					{
						$amount =  $event['end'] - $start_range;
					}
					else if ($is_over_end_range) // over end range multidays event
					{
						$amount = $end_range - $event['start'];
					}
					else
					{
						$amount = $event['end'] - $event['start'];
					}
					$events_log[$user_id][$week_number][$event['id']]['counted'] = true;
				}
				// store day
				$day[$user_id][$cat_id][$event['id']] = array (
					'weekN' => date('W', $event['start']),
					'cat_id' => $cat_id,
					'event_id' => $event['id'],
					'amount' => $amount,
					'min_days' => $min_days,
					'unit' => $unit
				);
				// store the week sum for those events which their categories marked as
				// specified with min_days in their row.
				if ($min_days)
				{
					$week_sum[$user_id][date('W', $event['start'])][$cat_id][$event['id']][] = $amount;
					$week_sum[$user_id][date('W', $event['start'])][$cat_id]['min_days'] = $min_days;
				}
			}
		}
	}

	/**
	 * function to add up days
	 *
	 * @param array $days array of days
	 * @return int returns sum of amount of events
	 */
	public static function add_days ($days) {
		$sum = 0;
		foreach ($days as $val)
		{
			$sum = $sum + $val;
		}
		return $sum;
	}

	/**
	 * Function to build holiday report index interface and logic
	 *
	 * @param type $content
	 */
	public function index ($content = null)
	{
		$api_cats = new Api\Categories($GLOBALS['egw_info']['user']['account_id'],'calendar');
		if (is_null($content))
		{
			$cats = $api_cats->return_sorted_array($start=0, false, '', 'ASC', 'cat_name', 'all_no_acl', 0, true);
			$cats_status = $GLOBALS['egw_info']['user']['preferences']['calendar']['category_report'];
			foreach ($cats as &$value)
			{
				$content['grid'][] = array(
					'cat_id' => $value['id'],
					'user' => '',
					'weekend' => '',
					'holidays' => '',
					'min_days' => 0,
					'unit' => 3600,
					'enable' => true
				);
			}

			if (is_array($cats_status))
			{
				foreach ($content['grid'] as &$row)
				{
					foreach ($cats_status as $value)
					{
						if ($row['cat_id'] == $value['cat_id'])
						{
							$row = $value;
						}
					}
				}
			}
		}
		else
		{
			$button = @key($content['button']);
			$result = $categories = $content_rows = array ();
			$users = array_keys($GLOBALS['egw']->accounts->search(array('type'=>'accounts', 'active'=>true)));

			// report button pressed
			if (!empty($button))
			{
				// shift the grid content by one because of the reserved first row
				// for the header.
				array_shift($content['grid']);
				Api\Framework::ajax_set_preference('calendar', 'category_report',$content['grid']);

				foreach ($content['grid'] as $key => $row)
				{
					if ($row['enable'])
					{
						$categories [] = $content_rows [$key] = $row['cat_id'];
					}
				}

				$end_obj = new Api\DateTime($content['end']);
				// Add 1 day minus a second to only query untill the end of the
				// end range day.
				$end_range =  $end_obj->modify('+1 day -1 sec');

				// query calendar for events
				$events = $this->bo->search(array(
					'start' => $content['start'],
					'end' => $end_range->getTimestamp(), // range till midnight of the sele3cted end date
					'users' => $users,
					'cat_id' => $categories,
					'daywise' => true
				));

				$days_sum = $weeks_sum = $events_log = array ();
				// iterate over found events
				foreach($events as $day_index => $day_events)
				{
					if (is_array($day_events))
					{
						foreach ($content_rows as $row_id => $cat_id)
						{
							$this->process_days(
									$events_log,
									$weeks_sum,
									$days_sum[$day_index],
									$day_index,
									$day_events,
									$cat_id,
									$content['grid'][$row_id]['holidays'],
									$content['grid'][$row_id]['weekend'],
									$content['grid'][$row_id]['min_days'],
									$content['grid'][$row_id]['unit'],
									$content['start'],
									$end_range->getTimestamp() // range till midnight of the selected end date
							);
						}
					}
				}

				asort($days_sum);
				ksort($days_sum);
				$days_output = $min_days_output = array ();

				foreach($days_sum as $day_index => $users)
				{
					foreach ($users as $user_id => $cat_ids)
					{
						foreach ($cat_ids as $cat_id => $event)
						{
							foreach ($event as $e)
							{
								$days_output[$user_id][$cat_id]['amount'] =
										$days_output[$user_id][$cat_id]['amount'] + (int)$e['amount'];
								$days_output[$user_id][$cat_id]['unit'] = $e['unit'];
								$days_output[$user_id][$cat_id]['days'] += 1;
							}
						}
					}
				}

				foreach ($weeks_sum as $user_id => $weeks)
				{
					foreach ($weeks as $week_id => $cat_ids)
					{
						foreach ($cat_ids as $cat_id => $events)
						{
							foreach ($events as $event_id => $e)
							{
								if ($event_id !== 'min_days')
								{
									$days = $weeks_sum[$user_id][$week_id][$cat_id][$event_id];
									$min_days_output[$user_id][$cat_id]['amount'] =
											$min_days_output[$user_id][$cat_id]['amount'] +
											(count($days) >= (int)$events['min_days']?self::add_days($days):0);
									$min_days_output[$user_id][$cat_id]['days'] =
											$min_days_output[$user_id][$cat_id]['days'] +
											(count($days) >= (int)$events['min_days']?count($days):0);
								}
							}
						}
					}
				}

				// Replace value of those cats who have min_days set on with regular day calculation
				// result array structure:
				// [user_ids] =>
				//      [cat_ids] => [
				//             days: amount of event in second,
				//              unit: unit to be shown as output (in second, eg. 3600)
				//          ]
				//
				$result = array_replace_recursive($days_output, $min_days_output);
				$raw_csv = $csv_header =  array ();

				// create csv header
				foreach ($categories as $cat_id)
				{
					$csv_header [] = $api_cats->id2name($cat_id);
				}
				array_unshift($csv_header, 'n_given');
				array_unshift($csv_header, 'n_family');

				// file pointer
				$fp = fopen('php://output', 'w');


				// printout csv header into file
				fputcsv($fp, array_values($csv_header));

				// set header to download csv file
				header('Content-type: text/csv');
				header('Content-Disposition: attachment; filename="report.csv"');

				// iterate over csv rows for each user to print them out into csv file
				foreach ($result as $user_id => $cats_data)
				{
					$cats_row = array();
					foreach ($categories as $cat_id)
					{
						$cats_row [$cat_id] = ceil($cats_data[$cat_id]['amount']?
								($cats_data[$cat_id]['amount'] - $events_log['reductions'][$user_id][$cat_id]) /
								$cats_data[$cat_id]['unit']: 0);
						if ($cats_data[$cat_id]['unit'] == 86400) $cats_row [$cat_id] = $cats_data[$cat_id]['days'];
					}
					// first name
					$n_given =  array('n_given' => Api\Accounts::id2name($user_id, 'account_firstname')) ?
							array('n_given' => Api\Accounts::id2name($user_id, 'account_firstname')) : array();
					// last name
					$n_family = array('n_family' => Api\Accounts::id2name($user_id, 'account_lastname')) ?
							array('n_family' => Api\Accounts::id2name($user_id, 'account_lastname')) : array();
					$raw_csv[] = $n_family + $n_given+ $cats_row;
				}
				// comparision function for usort
				function compareByFamily ($key='n_family') {
					return function ($a, $b) use ($key){
						return strcasecmp($a[$key], $b[$key]);
					};
				}
				usort($raw_csv, compareByFamily($content['sort_key']));

				foreach ($raw_csv as &$row)
				{
					//check if all values of the row is zero then escape the row
					if (!array_sum($row)) continue;

					// printout each row into file
					fputcsv($fp, array_values($row));
				}
				// echo out csv file
				fpassthru($fp);
				exit();
			}
		}

		// unit selectbox options
		$sel_options['unit'] = array (
			86400 => array('label' => lang('day'), 'title'=>'Output value in day'),
			3600 => array('label' => lang('hour'), 'title'=>'Output value in hour'),
			60 => array('label' => lang('minute'), 'title'=>'Output value in minute'),
			1  => array('label' => lang('second'), 'title'=>'Output value in second')
		);
		// Add an extra row for the grid header
		array_unshift($content['grid'],array(''=> ''));
		$preserv = $content;
		$this->tmpl->exec('calendar.calendar_category_report.index', $content, $sel_options, array(), $preserv, 2);
	}
}
