<?php
/**
 * eGroupWare - Wizard for group CSV import
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package addressbook
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id:  $
 */

class admin_wizard_import_groups_csv extends importexport_wizard_basic_import_csv
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
			'account_lid'		=> lang('Account ID'),
			'account_name'		=> lang('Group Name'),
			'account_members'	=> lang('Members'),
		);

		// Actions
		$this->actions = array(
			'none'		=>	lang('none'),
			'update'	=>	lang('update'),
			'create'	=>	lang('create'),
			'delete'	=>	lang('delete'),
		);

		// Conditions
		$this->conditions = array(
			'exists'	=>	lang('exists'),
		);
	}

	function wizard_step50(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		$result = parent::wizard_step50($content, $sel_options, $readonlys, $preserv);
		
		return $result;
	}
}
