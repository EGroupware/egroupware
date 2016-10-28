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
class calendar_holiday_report {

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
		$this->bo = new calendar_bo();
		$this->tmpl = new Etemplate('calendar.holiday_report');
	}

	/**
	 * Function to build holiday report index interface and logic
	 *
	 * @param type $content
	 */
	public function index ($content = null)
	{
		if (is_null($content))
		{
			$cat = new Api\Categories($GLOBALS['egw_info']['user']['account_id'],'calendar');
			$cats = $cat->return_sorted_array($start=0, false, '', 'ASC', 'cat_name', true, 0, true);

			foreach ($cats as &$value)
			{
				$content['grid'][] = array(
					'cat_id' => $value['id'],
					'user' => '',
					'weekend' => '',
					'holidays' => '',
					'min_days' => 4
				);
			}
		}
		else
		{

		}

		$this->tmpl->exec('calendar.holiday_report', $content);
	}
}
