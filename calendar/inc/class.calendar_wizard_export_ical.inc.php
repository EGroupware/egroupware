<?php

/**
 * Wizard for exporting iCal
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package calendar
 * @subpackage importexport
 * @copyright (c) 2018  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */


use EGroupware\Api;

class calendar_wizard_export_ical extends importexport_wizard_basic_export_csv {

	public function __construct() {
		parent::__construct();

		$this->steps = array(
			'wizard_step80' => lang('Filters'),
		);
	}
}
