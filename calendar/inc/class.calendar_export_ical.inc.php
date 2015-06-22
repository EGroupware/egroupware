<?php
/**
 * EGroupware: iCal export plugin of calendar
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package calendar
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray
 * @version $Id$
 */

/**
 * iCal export plugin of calendar
 */
class calendar_export_ical extends calendar_export_csv {

	/**
	 * Exports records as defined in $_definition
	 *
	 * @param egw_record $_definition
	 */
	public function export( $_stream, importexport_definition $_definition) {
		$options = $_definition->plugin_options;
		$this->bo = new calendar_bo();
		$boical = new calendar_ical();

		// Custom fields need to be specifically requested
		$cfs = array();

		$limit_exception = bo_merge::is_export_limit_excepted();
		if (!$limit_exception) $export_limit = bo_merge::getExportLimit('calendar');

		if($options['selection'] == 'criteria')
		{
			$query = array(
				'start' => $options['criteria']['start'],
				'end'   => strtotime('+1 day',$options['criteria']['end'])-1,
				'categories'	=> $options['categories'],
				'daywise'       => false,
				'users'         => $options['criteria']['owner'],
				'cfs'		=> $cfs // Otherwise we shouldn't get any custom fields
			);
			if(bo_merge::hasExportLimit($export_limit) && !$limit_exception) {
				$query['offset'] = 0;
				$query['num_rows'] = (int)$export_limit;  // ! int of 'no' is 0
			}
			$events =& $this->bo->search($query);
		}
		// Scheduled export will use 'all', which we don't allow through UI
		elseif ($options['selection'] == 'search_results' || $options['selection'] == 'all')
		{
			$states = $GLOBALS['egw']->session->appsession('session_data','calendar');
			if($states['view'] == 'listview')
			{
				$query = $GLOBALS['egw']->session->appsession('calendar_list','calendar');
				$query['num_rows'] = -1;        // all
				$query['start'] = 0;
				$query['cfs'] = $cfs;

				if(bo_merge::hasExportLimit($export_limit) && !$limit_exception) {
					$query['num_rows'] = (int)$export_limit; // ! int of 'no' is 0
				}
				$ui = new calendar_uilist();
				$unused = null;
				$ui->get_rows($query, $events, $unused);
			}
			else
			{
				$query = $GLOBALS['egw']->session->appsession('session_data','calendar');
				$query['users'] = explode(',', $query['owner']);
				$query['num_rows'] = -1;
				if(bo_merge::hasExportLimit($export_limit) && !$limit_exception)
				{
					$query['num_rows'] = (int)$export_limit;  // ! int of 'no' is 0
				}

				switch($states['view'])
				{
					case 'month':
						$query += calendar_export_csv::get_query_month($states);
						break;
					case 'week':
						$query += calendar_export_csv::get_query_week($states);
						break;
					case 'day':
						$query += calendar_export_csv::get_query_day($states);
						break;
					default:
						// Let UI set the date ranges
						$ui = new calendar_uiviews($query);
						if(method_exists($ui, $states['view']))
						{
							ob_start();
							$ui->$states['view']();
							ob_end_clean();
						}
						$query += array(
							'start' => is_array($ui->first) ? $this->bo->date2ts($ui->first) : $ui->first,
							'end' => is_array($ui->last) ? $this->bo->date2ts($ui->last) : $ui->last
						);

				}
				$boupdate = new calendar_boupdate();
				$events = $boupdate->search($query + array(
					'offset' => 0,
					'order' => 'cal_start',
				));
			}
		}
		// compile list of unique cal_id's, as iCal should contain whole series, not recurrences
		// calendar_ical->exportVCal needs to read events again, to get them in server-time
		$ids = array();
		foreach($events as $event)
		{
			$id = is_array($event) ? $event['id'] : $event;
			if (($id = (int)$id)) $ids[$id] = $id;
		}

		$ical =& $boical->exportVCal($ids,'2.0','PUBLISH',false);
		fwrite($_stream, $ical);
	}

	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name() {
		return lang('Calendar iCal export');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description() {
		return lang("Exports events from your Calendar in iCal format.");
	}

	/**
	 * retruns file suffix for exported file
	 *
	 * @return string suffix
	 */
	public static function get_filesuffix() {
		return 'ics';
	}

	public static function get_mimetype() {
		return 'text/calendar';
	}

	/**
	 * return html for options.
	 *
	 */
	public function get_options_etpl() {
	}

	/**
	 * returns selectors of this plugin
	 *
	 */
	public function get_selectors_etpl($definition = null) {
		$data = parent::get_selectors_etpl($definition);
		return $data;
	}
}
