<?php
/**
 * eGroupWare - Wizard for Timesheet CSV export
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package timesheet
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

use EGroupware\Api;

class timesheet_wizard_export_csv extends importexport_wizard_basic_export_csv
{
	public function __construct() {
		parent::__construct();

		// Field mapping
		$bo = new timesheet_bo();
		$this->export_fields = array('ts_id' => 'Timesheet ID') + $bo->field2label + array(
			'ts_created' => 'Created',
			'ts_modified'=> 'Modified'
		);

		// Custom fields
		unset($this->export_fields['customfields']);
		$custom = Api\Storage\Customfields::get('timesheet', true);
		foreach($custom as $name => $data) {
			$this->export_fields['#'.$name] = $data['label'];
		}
	}
}
