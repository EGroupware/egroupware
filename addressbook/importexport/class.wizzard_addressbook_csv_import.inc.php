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

require_once(EGW_INCLUDE_ROOT.'/addressbook/inc/class.addressbook_csv_import.inc.php');

class wizzard_addressbook_csv_import extends addressbook_csv_import 
{

	var $steps;
	
	/**
	 * constructor
	 */
	function wizzard_addressbook_csv_import()
	{
		$this->steps = array(
			'wizzard_step30' => lang('Load Sample file'),
			'wizzard_step40' => lang('Choose seperator and charset'),
			'wizzard_step50' => lang('Manage mapping'),
			'wizzard_step60' => lang('Choose owner of imported data'),
		);
		$this->__construct();
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
					error_log(print_r($content,true));
					$file = fopen ($content['file']['tmp_name'],'rb');
					
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
			//$content['csv_fields'] = array('csv_01','csv_02','csv_03','csv_04','csv_05','csv_06','csv_07','csv_08','csv_09','csv_10','csv_11','csv_12');
			array_shift($content['field_mapping']);
			array_shift($content['field_translation']);
			
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

			array_unshift($content['csv_fields'],array('row0'));
			array_unshift($content['field_mapping'],array('row0'));
			array_unshift($content['field_translation'],array('row0'));
			
			$j = 0;
			foreach ($content['csv_fields'] as $field)
			{
				if(strstr($field,'no_csv_')) $j++;
			}
			while ($j <= 3) 
			{
				$content['csv_fields'][] = 'no_csv_'.$j;
				$content['field_mapping'][] = $content['field_translation'][] = '';
				$j++;
			}
			$contact_fields = $this->bocontacts->get_contact_columns();
			$sel_options['field_mapping'] = array('' => lang('none')) + array_combine($contact_fields,$contact_fields);
			error_log(print_r($sel_options['field_mapping'],true));
			$preserv = $content;
			unset ($preserv['button']);
			return 'addressbook.importexport_wizzard_fieldmaping';
		}
		
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

			$preserv = $content;
			unset ($preserv['button']);
			return 'addressbook.importexport_wizzard_chooseowner';
		}
		
	}
}
