<?php
/**
 * eGroupWare - Wizard for user CSV import
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package calendar
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

class calendar_wizard_import_csv extends importexport_wizard_basic_import_csv
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
		$tracking = new calendar_tracking();
		$this->mapping_fields = array('id' => 'Calendar ID') + $tracking->field2label;

		// List each custom field
		unset($this->mapping_fields['customfields']);
		$custom = config::get_customfields('calendar');
		foreach($custom as $name => $data) {
			$this->mapping_fields['#'.$name] = $data['label'];
		}

		// Actions
		$this->actions = array(
			'none'		=>	lang('none'),
			'update'	=>	lang('update'),
			'insert'	=>	lang('insert'),
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

	// Conditions
	function wizard_step55(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		$result = parent::wizard_step55($content, $sel_options, $readonlys, $preserv);

		// Search can only deal with ID
		$sel_options['string'] = array(
			'id'	=>	'Calendar ID'
		);
		return $result;
	}
}
