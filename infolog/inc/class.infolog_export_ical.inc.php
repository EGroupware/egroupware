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

use EGroupware\Api;

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

		$limit_exception = Api\Storage\Merge::is_export_limit_excepted();
		if (!$limit_exception) $export_limit = Api\Storage\Merge::getExportLimit('infolog');

		$ids = array();
		$query = array();
		switch($options['selection'])
		{
			case 'search':
				$query = array_merge(Api\Cache::getSession('infolog', 'session_data'), $query);
				// Fall through
			case 'all':
				$query['num_rows'] = $export_limit ? $export_limit : -1;
				$query['start'] = 0;
				$this->selection = $this->bo->search($query);

				break;
			default:
				$ids = $this->selection = explode(',',$options['selection']);
				break;
		}

		$boical = new infolog_ical();
		fwrite($_stream, $boical->exportvCalendar($this->selection));
	}

	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name() {
		return lang('Infolog iCal export');
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
	 * Suggest a file name for the downloaded file
	 * No suffix
	 */
	public function get_filename()
	{
		if(is_array($this->selection) && count($this->selection) == 1)
		{
			return $this->bo->link_title(current($this->selection));
		}
		return false;
	}

	/**
	 * return array for options.
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
	public function get_selectors_etpl() {
		return array(
                        'name'  => 'infolog.export_csv_selectors',
                        'content'       => 'search'
                );
	}
}
