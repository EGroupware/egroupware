<?php
/**
 * eGroupWare - Wizard for Groups CSV export
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package admin
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

class admin_wizard_export_groups_csv extends importexport_wizard_basic_export_csv
{
	public function __construct() {
		parent::__construct();

		// Field mapping
                $this->export_fields = array(
			'account_id'		=> lang('Account ID'),
			'account_lid'		=> lang('Group Name'),
			'account_members'	=> lang('Members'),
                );

		// Custom fields - not really used in admin...
		unset($this->export_fields['customfields']);
		$custom = config::get_customfields('admin', true);
		foreach($custom as $name => $data) {
			$this->export_fields['#'.$name] = $data['label'];
		}
	}
}
