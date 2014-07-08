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

		$this->steps['wizard_step50'] = lang('Choose export options');
		$this->step_templates['wizard_step50'] = 'addressbook.export_explode_fields';

		// Field mapping
		$bocontacts = new addressbook_bo();
		$this->export_fields = $bocontacts->contact_fields;
		foreach($bocontacts->customfields as $name => $data) {
			$this->export_fields['#'.$name] = $data['label'];
		}
		unset($this->export_fields['jpegphoto']);        // can't cvs export that
	}

	/**
	* Overridden to be able to skip the next step
	*/
	function wizard_step40(&$content, &$sel_options, &$readonlys, &$preserv) {

		if ($content['step'] == 'wizard_step40' && array_search('pressed', $content['button']) == 'next') {
			$result = parent::wizard_step40($content, $sel_options, $readonlys, $preserv);
			$field_list = $this->get_field_list($content);
			if(count($field_list)) {
				return $result;
			} else {
				return $GLOBALS['egw']->importexport_definitions_ui->get_step($content['step'],2);
			}
		} else {
			return parent::wizard_step40($content, $sel_options, $readonlys, $preserv);
		}
	}

        /**
        * Choose how to export multi-selects (includes categories)
        */
        function wizard_step50(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		if($this->debug) error_log(get_class($this) . '::wizard_step50->$content '.print_r($content,true));
		// return from step50
		if ($content['step'] == 'wizard_step50')
		{
			if($content['explode_multiselects']) {
				$explodes = $content['explode_multiselects'];
				$content['explode_multiselects'] = array();
				foreach($explodes as $row => $settings) {
					if($settings['explode'] != addressbook_export_contacts_csv::NO_EXPLODE) {
						$content['explode_multiselects'][$settings['field']] = $settings;
					}
				}
			}
			switch (array_search('pressed', $content['button']))
			{
				case 'next':
					return $GLOBALS['egw']->importexport_definitions_ui->get_step($content['step'],1);
				case 'previous' :
					return $GLOBALS['egw']->importexport_definitions_ui->get_step($content['step'],-1);
				case 'finish':
					return 'wizard_finish';
				default :
					return $this->wizard_step50($content,$sel_options,$readonlys,$preserv);
			}
		}
		// init step50
		else
		{
			$content['msg'] = $this->steps['wizard_step50'];
			$content['step'] = 'wizard_step50';
			unset ($preserv['button']);
			$field_list = $this->get_field_list($content);
			
			$settings = $content['explode_multiselects'] ? $content['explode_multiselects'] : $content['plugin_options']['explode_multiselects'];

			// Skip this step if no fields applicable
			if(count($field_list) == 0) {
				$content['explode_multiselects'] = array();
			}

			$cat_options = array(
				addressbook_export_contacts_csv::NO_EXPLODE => lang('All in one field'),
				addressbook_export_contacts_csv::MAIN_CATS => lang('Main categories in their own field'),
				addressbook_export_contacts_csv::EACH_CAT => lang('Each category in its own field'),
			);
			$multi_options = array(
				addressbook_export_contacts_csv::NO_EXPLODE => lang('All in one field'),
				addressbook_export_contacts_csv::EXPLODE => lang('Each option in its own field'),
			);

			$row = 1;
			foreach($field_list as $field => $name) {
				$content['explode_multiselects'][$row] = array(
					'field' =>      $field,
					'name'  =>      $name,
					'explode'=>	$settings[$field]
				);
				if($field == 'cat_id') {
					$sel_options['explode_multiselects'][$row]['explode'] = $cat_options;
				} else {
					$sel_options['explode_multiselects'][$row]['explode'] = $multi_options;
				}
				$row++;
			}
			// Cheat server side validation, which can't handle different options per row
			$sel_options['explode'] = $cat_options + $multi_options;
			$preserv = $content;
	//_debug_array($content['explode_multiselects']);
			return $this->step_templates[$content['step']];
		}
	}

	/**
	* Get a list of multi-select fields
	*/
	protected function get_field_list($content) {
		$field_list = array();
		
		// Category gets special handling
		if(in_array('cat_id', array_keys($content['mapping']))) {
			$field_list['cat_id'] = $this->export_fields['cat_id'];
		}

		// Add any multi-select custom fields
		$custom = config::get_customfields('addressbook');
		foreach($custom as $name => $c_field) {
			if($c_field['type'] = 'select' && $c_field['rows'] > 1 && in_array('#'.$name, array_keys($content['mapping']))) {
				$field_list['#'.$name] = $c_field['label'];
			}
		}
		return $field_list;
	}
}
