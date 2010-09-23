<?php
/**
 * eGroupWare - Wizard for Adressbook CSV import
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package addressbook
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @version $Id:  $
 */

class addressbook_wizard_import_contacts_csv extends importexport_wizard_basic_import_csv
{

	/**
	 * constructor
	 */
	function __construct()
	{
		parent::__construct();

		$this->steps += array(
			'wizard_step50' => lang('Manage mapping'),
			'wizard_step60' => lang('Choose owner of imported data'),
		);

		// Field mapping
		$bocontacts = new addressbook_bo();
		$this->mapping_fields = $bocontacts->contact_fields;
		foreach($bocontacts->customfields as $name => $data) {
			$this->mapping_fields['#'.$name] = $data['label'];
		}
		unset($this->mapping_fields['jpegphoto']);        // can't cvs import that

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

	function wizard_step50(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		$result = parent::wizard_step50($content, $sel_options, $readonlys, $preserv);
		$content['msg'] .= "\n*" . lang('Contact ID cannot be changed by import');
		
		return $result;
	}
	
	function wizard_step60(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		if($this->debug) error_log('addressbook.importexport.addressbook_csv_import::wizard_step60->$content '.print_r($content,true));
		unset($content['no_owner_map']);
		// return from step60
		if ($content['step'] == 'wizard_step60')
		{
			switch (array_search('pressed', $content['button']))
			{
				case 'next':
					unset($content['csv_fields']);
					return $GLOBALS['egw']->importexport_definitions_ui->get_step($content['step'],1);
				case 'previous' :
					return $GLOBALS['egw']->importexport_definitions_ui->get_step($content['step'],-1);
				case 'finish':
					return 'wizard_finish';
				default :
					return $this->wizard_step60($content,$sel_options,$readonlys,$preserv);
			}
		}
		// init step60
		else
		{
			$content['msg'] = $this->steps['wizard_step60'];
			$content['step'] = 'wizard_step60';
			if(!array_key_exists($content['contact_owner']) && $content['plugin_options']) {
				$content['contact_owner'] = $content['plugin_options']['contact_owner'];
			}
			if(!array_key_exists($content['owner_from_csv']) && $content['plugin_options']) {
				$content['owner_from_csv'] = $content['plugin_options']['owner_from_csv'];
			}
			if(!array_key_exists($content['change_owner']) && $content['plugin_options']) {
				$content['change_owner'] = $content['plugin_options']['change_owner'];
			}

			$bocontacts = new addressbook_bo();
			$sel_options['contact_owner'] = $bocontacts->get_addressbooks(EGW_ACL_ADD);
			if(!in_array('owner', $content['field_mapping'])) {
				$content['no_owner_map'] = true;
			}

			$preserv = $content;
			unset ($preserv['button']);
			return 'addressbook.importexport_wizard_chooseowner';
		}
		
	}
}
