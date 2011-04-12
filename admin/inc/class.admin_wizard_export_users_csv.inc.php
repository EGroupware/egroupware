<?php
/**
 * eGroupWare - Wizard for User CSV export
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package admin
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

class admin_wizard_export_users_csv extends importexport_wizard_basic_export_csv
{
	public function __construct() {
		parent::__construct();

		// Field mapping
                $this->export_fields = array(
			'account_id'		=> lang('Account ID'),
                        'account_lid'           => lang('LoginID'),
                        'account_firstname'     => lang('First Name'),
                        'account_lastname'      => lang('Last Name'),
                        'account_email'         => lang('email'),
                        'account_passwd'        => lang('Password'),
                        'account_active'        => lang('Account active'),
                        'account_primary_group' => lang('primary Group'),
                        'account_groups'        => lang('Groups'),
                        'account_expires'       => lang('Expires'),
                        'anonymous'             => lang('Anonymous User (not shown in list sessions)'),
                        'changepassword'        => lang('Can change password'),
                        'mustchangepassword'    => lang('Must change password upon next login'),
                );

		// Custom fields - not really used in admin...
		unset($this->export_fields['customfields']);
		$custom = config::get_customfields('admin', true);
		foreach($custom as $name => $data) {
			$this->export_fields['#'.$name] = $data['label'];
		}
	}
}
