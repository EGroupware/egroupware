<?php
/**
 * eGroupWare
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package infolog
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray
 * @version $Id$
 */

/**
 * export iCal plugin of infolog
 */
class infolog_export_ical extends infolog_export_csv {

	/**
	 * Exports records as defined in $_definition
	 *
	 * @param egw_record $_definition
	 */
	public function export( $_stream, importexport_definition $_definition) {
		$options = $_definition->plugin_options;
		$this->bo = new infolog_bo();
		$boical = new infolog_ical();

		$limit_exception = bo_merge::is_export_limit_excepted();
		if (!$limit_exception) $export_limit = bo_merge::getExportLimit('infolog');

		$ids = array();
		$query = array();
		switch($options['selection'])
		{
			case 'search':
				$query = array_merge($GLOBALS['egw']->session->appsession('session_data','infolog'), $query);
				// Fall through
			case 'all':
				$query['num_rows'] = $export_limit ? $export_limit : -1;
				$query['start'] = 0;
				$selection = $this->bo->search($query);

				break;
			default:
				$ids = $selection = explode(',',$options['selection']);
				break;
		}

		$ical = '';
		foreach($selection as $_selection) {
			$result = $boical->exportVTODO($_selection,'2.0','PUBLISH',false);
			if($result)
			{
				$ical .= $result;
			}
		}
		fwrite($_stream, $ical);
	}

	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name() {
		return lang('iCal export');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description() {
		return lang("Exports in iCal format.");
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
		return 'text/infolog';
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
                        'name'  => 'infolog.export_csv_selectors',
                        'content'       => 'search'
                );
	}
}
