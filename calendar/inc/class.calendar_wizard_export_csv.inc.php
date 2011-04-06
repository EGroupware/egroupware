<?php
/**
 * eGroupWare - Wizard for Adressbook CSV export
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package calendar
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

class calendar_wizard_export_csv extends importexport_wizard_basic_export_csv
{
	public function __construct() {
		parent::__construct();
		// Field mapping
		$bo = new calendar_tracking();
		$this->export_fields = array('id' => 'Calendar ID') + $bo->field2label;
		$custom = config::get_customfields('calendar', true);
		foreach($custom as $name => $data) {
			$this->export_fields['#'.$name] = $data['label'];
		}

	}

	
}
