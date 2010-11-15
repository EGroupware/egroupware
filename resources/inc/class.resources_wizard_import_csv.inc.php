<?php
/**
 * eGroupWare - Wizard for Resources CSV import
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package resources
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

class resources_wizard_import_csv extends importexport_wizard_basic_import_csv
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
		$export = new resources_wizard_export_csv();
		$this->mapping_fields = array(
                        'res_id'        => lang('ID'),
                        'name'          => lang('name'),
                        'short_description'     => lang('short description'),
                        'cat_id'        => lang('Category'),
                        'quantity'      => lang('Quantity'),
                        'useable'       => lang('Useable'),
                        'location'      => lang('Location'),
                        'bookable'      => lang('Bookable'),
                        'buyable'       => lang('Buyable'),
                        'prize'         => lang('Prize'),
                        'long_description'      => lang('Long description'),
                        'inventory_number'      => lang('inventory number'),
                );

		$custom = config::get_customfields('resources', true);
		foreach($custom as $name => $data) {
			$this->mapping_fields['#'.$name] = $data['label'];
		}

		// Actions
		$this->actions = array(
			'none'		=>	lang('none'),
			'update'	=>	lang('update'),
			'insert'	=>	lang('insert'),
			'delete'	=>	lang('delete'),
		);

		// Conditions
		$this->conditions = array(
			'exists'	=>	lang('exists'),
		);
	}
}
