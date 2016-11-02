<?php

/**
 * EGroupware - Calendar's holiday report utility
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
 * This class reports number of holidays taken by users based
 * on categories defined as holidays. THe report would be based
 * on start and end date and can be specified with several options
 * for each category. The result of the report would be a CSV file
 * consistent of user full name, categories names, and number of
 * days/ or weeks.
 *
 */
class calendar_holiday_report extends calendar_ui{

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
		$this->tmpl = new Api\Etemplate('calendar.holiday_report');
	}

	/**
	 * Function to build holiday report index interface and logic
	 *
	 * @param type $content
	 */
	public function index ($content = null)
	{
		$cat = new Api\Categories($GLOBALS['egw_info']['user']['account_id'],'calendar');
		if (is_null($content))
		{
			$cats = $cat->return_sorted_array($start=0, false, '', 'ASC', 'cat_name', true, 0, true);
			$cats_status = $GLOBALS['egw_info']['user']['preferences']['calendar']['holiday_report'];
			foreach ($cats as &$value)
			{
				$content['grid'][] = array(
					'cat_id' => $value['id'],
					'user' => '',
					'weekend' => '',
					'holidays' => '',
					'min_days' => 4,
					'enable' => true
				);
			}
			if (is_array($cats_status))
			{
				$content['grid'] = array_replace_recursive($content['grid'], $cats_status);
			}
		}
		else
		{
			list($button) = @each($content['button']);
			$categories = array ();
			$users_map = $GLOBALS['egw']->accounts->search(array('type'=>'accounts', active=>true));
			$users = array_keys($users_map);
			$result = array();

			// report button pressed
			if (!empty($button))
			{
				// shift the grid content by one because of the reserved first row
				// for the header.
				array_shift($content['grid']);
				Api\Framework::ajax_set_preference('calendar', 'holiday_report',$content['grid']);

				foreach ($content['grid'] as $row)
				{
					if ($row['enable'])
					{
						$categories [] = $row['cat_id'];
					}
				}

				$params = array(
					'startdate' => $content['start'],
					'enddate' => $content['end'],
					'users' => $users,
					'cat_id' => $categories
				);
				$events = $this->bo->search($params);

				// iterate over found events
				foreach($events as $event)
				{
					$cats = explode(',',$event['category']);
					if (is_array($cats))
					{
						foreach($cats as $val)
						{
							if (in_array($val, $categories)) $cat_index = (int)$val;
						}
					}
					$result [$event['owner']]['user_name'] = $users_map[$event['owner']]['account_lid'];

					// number of days of event (in sec)
					$days = $event['end'] - $event['start'];

					if (isset($result[$event['owner']][$cat->id2name($cat_index)]))
					{
						$result[$event['owner']][$cat->id2name($cat_index)] =
								$result[$event['owner']][$cat->id2name($cat_index)] + $days;
					}
					else
					{
						$result[$event['owner']][$cat->id2name($cat_index)] = $days;
					}

				}

				// calculates total days for each user based on cats
				foreach ($result as &$record)
				{
					$total = 0;
					foreach($record as $c => $value)
					{
						if ($c != 'user_name') $total += (int)$value;
					}
					$record ['total_days'] = $total;
				}
			}
		}
		// Add an extra row for the grid header
		array_unshift($content['grid'],array(''=> ''));
		$preserv = $content;
		$this->tmpl->exec('calendar.calendar_holiday_report.index', $content, array(), array(), $preserv, 2);
	}
}
