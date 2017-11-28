<?php
/**
 * EGroupware - Wizard for Groups CSV export
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package admin
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

use EGroupware\Api;

class admin_wizard_export_acl_csv extends importexport_wizard_basic_export_csv
{
	public function __construct() {
		parent::__construct();

		// Field mapping
		$this->export_fields = array(
			'acl_account'		=> lang('Account'),
			'acl_appname'		=> lang('Application'),
			'acl_location'      => lang('Data from'),
			'all_acls'          => lang('All ACLs'),
			'acl_run'			=> lang('Run'),
			'acl1'              => lang('Read'),
			'acl2'              => lang('Add'),
			'acl4'              => lang('Edit'),
			'acl8'              => lang('Delete'),
			'acl16'             => lang('Private'),
			'acl64'             => lang('Custom') .' 1',
			'acl128'            => lang('Custom') .' 2',
			'acl256'            => lang('Custom') .' 3',
		);

		// Custom fields - not possible for ACL
		unset($this->export_fields['customfields']);
	}

	/**
	 * Choose fields to export - overridden from parent to remove 'All custom fields',
	 * which does not apply here
	 */
	function wizard_step30(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		$result = parent::wizard_step30($content, $sel_options, $readonlys, $preserv);
		unset($this->export_fields['all_custom_fields']);
		foreach($content['fields'] as $field_id => $field)
		{
			if($field['field'] == 'all_custom_fields')
			{
				unset($content['fields'][$field_id]);
			}
		}
		return $result;
	}
}
