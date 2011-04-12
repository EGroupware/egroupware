<?php
/**
 * eGroupWare - Wizard for user CSV import
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package addressbook
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @version $Id:  $
 */

class admin_wizard_import_users_csv extends importexport_wizard_basic_import_csv
{

	/**
	 * constructor
	 */
	function __construct()
	{
		parent::__construct();

		$this->steps += array(
			'wizard_step50' => lang('Manage mapping'),
		);

		// Field mapping
		$this->mapping_fields = array(
			'account_id'		=> lang('Account ID'),
			'account_lid'		=> lang('LoginID'),
			'account_firstname'	=> lang('First Name'),
			'account_lastname'	=> lang('Last Name'),
			'account_email'		=> lang('email'),
			'account_passwd'	=> lang('Password'),
			'account_status'	=> lang('Status'),
			'account_primary_group'	=> lang('primary Group'),
			'account_groups'	=> lang('Groups'),
			'account_expires'	=> lang('Expires'),
			'anonymous'		=> lang('Anonymous User (not shown in list sessions)'),
			'changepassword'	=> lang('Can change password'),
			'mustchangepassword'	=> lang('Must change password upon next login'),
		);

		// Actions
		$this->actions = array(
			'none'		=>	lang('none'),
			'update'	=>	lang('update'),
			'create'	=>	lang('create'),
			'delete'	=>	lang('delete'),
			'disable'	=>	lang('disable'),
			'enable'	=>	lang('enable'),
		);

		// Conditions
		$this->conditions = array(
			'exists'	=>	lang('exists'),
		);
	}

	function wizard_step50(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		$result = parent::wizard_step50($content, $sel_options, $readonlys, $preserv);
		$content['msg'] .= "\n*" ;
		
		return $result;
	}
}
