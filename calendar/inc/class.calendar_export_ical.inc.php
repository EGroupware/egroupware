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
		$events =& $this->bo->search(array(
			'start' => $options['selection']['start'],
			'end'   => $options['selection']['end'],
			'categories'	=> $options['categories'] ? $options['categories'] : $options['selection']['categories'],
			'enum_recuring' => false,
			'daywise'       => false,
			'users'         => $options['selection']['owner'],
			'date_format'   => 'server',
		));
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
