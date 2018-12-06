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

use EGroupware\Api;

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

		$events = $this->get_events($_definition, $options);

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
	 * Return array of settings for export dialog
	 *
	 * @param $definition Specific definition
	 *
	 * @return array (
	 * 		name 		=> string,
	 * 		content		=> array,
	 * 		sel_options	=> array,
	 * 		readonlys	=> array,
	 * 		preserv		=> array,
	 * )
	 */
	public function get_options_etpl(importexport_definition &$definition = NULL)
	{
		return false;
	}

	/**
	 * returns selectors of this plugin
	 *
	 */
	public function get_selectors_etpl($definition = null) {
		$data = parent::get_selectors_etpl($definition);
		return $data;
	}
	/**
	 * Get the class name for the egw_record to use while exporting
	 *
	 * @return string;
	 */
	public static function get_egw_record_class()
	{
		return 'calendar_egw_record';
	}
}
