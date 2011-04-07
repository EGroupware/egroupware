<?php
/**
 * eGroupWare - Wizard for Infolog CSV export
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package infolog
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

class infolog_wizard_export_csv extends importexport_wizard_basic_export_csv
{
	public function __construct() {
		parent::__construct();

		// Field mapping
		$bo = new infolog_tracking();
		$this->export_fields = array('info_id' => 'Infolog ID') + $bo->field2label;

		// Custom fields
		unset($this->export_fields['custom']); // Heading, not a real field
		$custom = config::get_customfields('infolog', true);
		foreach($custom as $name => $data) {
			$this->export_fields['#'.$name] = $data['label'];
		}
	}
}
