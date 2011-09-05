<?php
/**
 * eGroupWare
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
 * export iCal plugin of calendar
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
		foreach($options['mapping'] as $key => $label) {
			if($key[0] == '#') $cfs[] = substr($key,1);
		}

		$limit_exception = bo_merge::is_export_limit_excepted();

		if($options['selection']['select'] == 'criteria') {
			$query = array(
				'start' => $options['selection']['start'],
				'end'   => $options['selection']['end'],
				'categories'	=> $options['categories'] ? $options['categories'] : $options['selection']['categories'],
				'enum_recuring' => false,
				'daywise'       => false,
				'users'         => $options['selection']['owner'],
				'cfs'		=> $cfs // Otherwise we shouldn't get any custom fields
			);
			if($config['export_limit'] && !$limit_exception) {
				$query['offset'] = 0;
				$query['num_rows'] = (int)$config['export_limit'];
			}
			$events =& $this->bo->search($query);
		} elseif ($options['selection']['select'] == 'search_results') {
			$states = $GLOBALS['egw']->session->appsession('session_data','calendar');
			if($states['view'] == 'listview') {
				$query = $GLOBALS['egw']->session->appsession('calendar_list','calendar');
				$query['num_rows'] = -1;        // all
				$query['start'] = 0;
				$query['cfs'] = $cfs;

				if($config['export_limit'] && !$limit_exception) {
					$query['num_rows'] = (int)$config['export_limit'];
				}
				$ui = new calendar_uilist();
				$ui->get_rows($query, $events, $unused);
			} else {
				$query = $GLOBALS['egw']->session->appsession('session_data','calendar');
				$query['users'] = explode(',', $query['owner']);
				$query['num_rows'] = -1;
				if($config['export_limit'] && !$limit_exception) {
					$query['num_rows'] = (int)$config['export_limit'];
				}

				$events = array();
				switch($states['view']) {
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
						$ui = new calendar_uiviews($query);
						$query += array(
							'start' => is_array($ui->first) ? $this->bo->date2ts($ui->first) : $ui->first,
							'end' => is_array($ui->last) ? $this->bo->date2ts($ui->last) : $ui->last
						);

				}
				$bo = new calendar_boupdate();
				$events = $bo->search($query + array(
					'offset' => 0,
					'order' => 'cal_start',
				));
			}
		}

		$ical =& $boical->exportVCal($events,'2.0','PUBLISH',false);
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
	public function get_selectors_etpl() {
		return array(
			'name'		=> 'calendar.export_csv_select',
			'content'	=> array(
				'start'		=> time(),
				'end'		=> time()
			)
		);
	}
}
