<?php
/**
 * eGroupWare - Wizzard for Adressbook CSV import
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package addressbook
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @version $Id:  $
 */

require_once(EGW_INCLUDE_ROOT.'/addressbook/importexport/class.import_contacts_csv.inc.php');

class wizzard_import_contacts_csv extends import_contacts_csv 
{

	var $steps;
	
	/**
	 * constructor
	 */
	function __construct()
	{
		$this->steps = array(
			'wizzard_step30' => lang('Load Sample file'),
			'wizzard_step40' => lang('Choose seperator and charset'),
			'wizzard_step50' => lang('Manage mapping'),
			'wizzard_step55' => lang('Edit conditions'),
			'wizzard_step60' => lang('Choose owner of imported data'),
		);
	}

	function wizzard_step30(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		if($this->debug) error_log('addressbook.importexport.addressbook_csv_import::wizzard_step30->$content '.print_r($content,true));
		// return from step30
		if ($content['step'] == 'wizzard_step30')
		{
			switch (array_search('pressed', $content['button']))
			{
				case 'next':
					// Move sample file to temp
					if($content['file']['tmp_name']) {
						$csvfile = tempnam($GLOBALS['egw_info']['server']['temp_dir'],$content['plugin']."_");
						move_uploaded_file($content['file']['tmp_name'], $csvfile);
						$GLOBALS['egw']->session->appsession('csvfile','',$csvfile);
					}
					unset($content['file']);
					return $GLOBALS['egw']->uidefinitions->get_step($content['step'],1);
				case 'previous' :
					return $GLOBALS['egw']->uidefinitions->get_step($content['step'],-1);
				case 'finish':
					return 'wizzard_finish';
				default :
					return $this->wizzard_step30($content,$sel_options,$readonlys,$preserv);
			}
		}
		// init step30
		else
		{
			$content['msg'] = $this->steps['wizzard_step30'];
			$content['step'] = 'wizzard_step30';
			$preserv = $content;
			unset ($preserv['button']);
			$GLOBALS['egw']->js->set_onload("var btn = document.getElementById('exec[button][next]'); btn.attributes.removeNamedItem('onclick');");
			return 'addressbook.importexport_wizzard_samplefile';
		}
		
	}
	
	/**
	 * choose fieldseperator, charset and headerline
	 *
	 * @param array $content
	 * @param array $sel_options
	 * @param array $readonlys
	 * @param array $preserv
	 * @return string template name
	 */
	function wizzard_step40(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		if($this->debug) error_log('addressbook.importexport.addressbook_csv_import::wizzard_step40->$content '.print_r($content,true));
		// return from step40
		if ($content['step'] == 'wizzard_step40')
		{//error_log(serialize($GLOBALS['egw']->uidefinitions));
			switch (array_search('pressed', $content['button']))
			{
				case 'next':
					// Process sample file for fields
					if (($handle = fopen($GLOBALS['egw']->session->appsession('csvfile'), "rb")) !== FALSE) {
						$data = fgetcsv($handle, 8000, $content['fieldsep']);
						$content['csv_fields'] = translation::convert($data,$content['charset']);
					} elseif($content['plugin_options']['csv_fields']) {
						$content['csv_fields'] = $content['plugin_options']['csv_fields'];
					}
					return $GLOBALS['egw']->uidefinitions->get_step($content['step'],1);
				case 'previous' :
					return $GLOBALS['egw']->uidefinitions->get_step($content['step'],-1);
				case 'finish':
					return 'wizzard_finish';
				default :
					return $this->wizzard_step40($content,$sel_options,$readonlys,$preserv);
			}
		}
		// init step40
		else
		{
			$content['msg'] = $this->steps['wizzard_step40'];
			$content['step'] = 'wizzard_step40';

			// If editing an existing definition, these will be in plugin_options
			if(!$content['fieldsep'] && $content['plugin_options']['fieldsep']) {
				$content['fieldsep'] = $content['plugin_options']['fieldsep'];
			} elseif (!$content['fieldsep']) {
				$content['fieldsep'] = ';';
			}
			if(!$content['charset'] && $content['plugin_options']['charset']) {
				$content['charset'] = $content['plugin_options']['charset'];
			}
			if(!$content['has_header_line'] && $content['plugin_options']['has_header_line']) {
				$content['num_header_lines'] = 1;
			}
			if(!$content['num_header_lines'] && $content['plugin_options']['num_header_lines']) {
				$content['num_header_lines'] = $content['plugin_options']['num_header_lines'];
			}

			$sel_options['charset'] = $GLOBALS['egw']->translation->get_installed_charsets()+
				array('utf-8' => 'utf-8 (Unicode)');
			$preserv = $content;
			unset ($preserv['button']);
			return 'addressbook.importexport_wizzard_choosesepncharset';
		}
		
	}
	
	function wizzard_step50(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		if($this->debug) error_log('addressbook.importexport.addressbook_csv_import::wizzard_step50->$content '.print_r($content,true));
		// return from step50
		if ($content['step'] == 'wizzard_step50')
		{
			array_shift($content['csv_fields']);
			array_shift($content['field_mapping']);
			array_shift($content['field_conversion']);

			foreach($content['field_conversion'] as $field => $convert) {
				if(!trim($convert)) unset($content['field_conversion'][$field]);
			}
			
			switch (array_search('pressed', $content['button']))
			{
				case 'next':
					return $GLOBALS['egw']->uidefinitions->get_step($content['step'],1);
				case 'previous' :
					return $GLOBALS['egw']->uidefinitions->get_step($content['step'],-1);
				case 'finish':
					return 'wizzard_finish';
				default :
					return $this->wizzard_step50($content,$sel_options,$readonlys,$preserv);
			}
		}
		// init step50
		else
		{
			$content['msg'] = $this->steps['wizzard_step50'];
			$content['step'] = 'wizzard_step50';

			if(!$content['field_mapping'] && $content['plugin_options']) {
				$content['field_mapping'] = $content['plugin_options']['field_mapping'];
				$content['field_conversion'] = $content['plugin_options']['field_conversion'];
			}
			array_unshift($content['csv_fields'],array('row0'));
			array_unshift($content['field_mapping'],array('row0'));
			array_unshift($content['field_conversion'],array('row0'));
			
			$j = 1;
			foreach ($content['csv_fields'] as $field)
			{
				if(strstr($field,'no_csv_')) $j++;
			}
			while ($j <= 3) 
			{
				$content['csv_fields'][] = 'no_csv_'.$j;
				$content['field_mapping'][] = $content['field_conversion'][] = '';
				$j++;
			}
			$bocontacts = new addressbook_bo();
			$contact_fields = $bocontacts->contact_fields;
			foreach($bocontacts->customfields as $name => $data) {
				$contact_fields['#'.$name] = $data['label'];
			}
			unset($addr_names['jpegphoto']);        // can't cvs import that
			$sel_options['field_mapping'] = array('' => lang('none')) + $contact_fields;
			$preserv = $content;
			unset ($preserv['button']);
			return 'addressbook.importexport_wizzard_fieldmaping';
		}
		
	}
	
	/**
	* Edit conditions
	*/
	function wizzard_step55(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		if($this->debug) error_log('addressbook.wizzard_import_contacts_csv->$content '.print_r($content,true));
		// return from step55
		if ($content['step'] == 'wizzard_step55')
		{
			array_shift($content['conditions']);

			foreach($content['conditions'] as $key => &$condition) {
				// Clear empties
				if($condition['string'] == '') {
					unset($content['conditions'][$key]);
					continue;
				}
			}
			
			switch (array_search('pressed', $content['button']))
			{
				case 'next':
					return $GLOBALS['egw']->uidefinitions->get_step($content['step'],1);
				case 'previous' :
					return $GLOBALS['egw']->uidefinitions->get_step($content['step'],-1);
				case 'finish':
					return 'wizzard_finish';
				case 'add':
					return $GLOBALS['egw']->uidefinitions->get_step($content['step'],0);
				default :
					return $this->wizzard_step55($content,$sel_options,$readonlys,$preserv);
					break;
			}
		}
		// init step55
		$content['msg'] = $this->steps['wizzard_step55'];
		$content['step'] = 'wizzard_step55';

		if(!$content['conditions'] && $content['plugin_options']['conditions']) {
			$content['conditions'] = $content['plugin_options']['conditions'];
		}

		$bocontacts = new addressbook_bo();
		$contact_fields = $bocontacts->contact_fields;
		$sel_options['string'][''] = 'None';
		foreach($bocontacts->customfields as $name => $data) {
			$contact_fields['#'.$name] = $data['label'];
		}
		foreach($content['field_mapping'] as $field) {
			$sel_options['string'][$field] = $contact_fields[$field];
		}
		$sel_options['type'] = array_combine(self::$conditions, self::$conditions);
		$sel_options['action'] = array_combine(self::$actions, self::$actions);

		// Make 3 empty conditions
		$j = 1;
		foreach ($content['conditions'] as $condition)
		{
			if(!$condition['string']) $j++;
		}
		while ($j <= 3) 
		{
			$content['conditions'][] = array('string' => '');
			$j++;
		}

		// Leave room for heading
		array_unshift($content['conditions'], false);

		$preserv = $content;
		unset ($preserv['button']);
		return 'addressbook.importexport_wizzard_conditions';
	}

	function wizzard_step60(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		if($this->debug) error_log('addressbook.importexport.addressbook_csv_import::wizzard_step60->$content '.print_r($content,true));
		// return from step60
		if ($content['step'] == 'wizzard_step60')
		{
			switch (array_search('pressed', $content['button']))
			{
				case 'next':
					unset($content['csv_fields']);
					return $GLOBALS['egw']->uidefinitions->get_step($content['step'],1);
				case 'previous' :
					return $GLOBALS['egw']->uidefinitions->get_step($content['step'],-1);
				case 'finish':
					return 'wizzard_finish';
				default :
					return $this->wizzard_step60($content,$sel_options,$readonlys,$preserv);
			}
		}
		// init step60
		else
		{
			$content['msg'] = $this->steps['wizzard_step60'];
			$content['step'] = 'wizzard_step60';

			if(!$content['contact_owner'] && $content['plugin_options']) {
				$content['contact_owner'] = $content['plugin_options']['contact_owner'];
			}

			$bocontacts = new addressbook_bo();
			$sel_options['contact_owner'] = $bocontacts->get_addressbooks(EGW_ACL_ADD);

			$preserv = $content;
			unset ($preserv['button']);
			return 'addressbook.importexport_wizzard_chooseowner';
		}
		
	}
}
