<?php
/**
 * eGroupWare - Wizard for Adressbook CSV export
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package addressbook
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id:  $
 */

class addressbook_wizard_export_contacts_csv extends importexport_wizard_basic_export_csv
{
	public function __construct() {
		parent::__construct();
		// Field mapping
		$bocontacts = new addressbook_bo();
		$this->export_fields = $bocontacts->contact_fields;
		foreach($bocontacts->customfields as $name => $data) {
			$this->export_fields['#'.$name] = $data['label'];
		}
		unset($this->export_fields['jpegphoto']);        // can't cvs export that
	}
}
