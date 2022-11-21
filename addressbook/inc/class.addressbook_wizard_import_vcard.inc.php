<?php
/**
 * eGroupWare - Wizard for Adressbook vCard import
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package addressbook
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Acl;

class addressbook_wizard_import_vcard extends addressbook_import_vcard
{
	/**
	 * constructor
	 */
	function __construct()
	{
		$this->steps = array(
			'wizard_step40' => lang('Choose charset'),
			'wizard_step60' => lang('Choose owner of imported data'),
		);
	}

	function wizard_step40(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		if($this->debug) error_log(get_class($this) . '::wizard_step40->$content '.print_r($content,true));
		// return from step40
		if ($content['step'] == 'wizard_step40')
		{
			switch (array_search('pressed', $content['button']))
			{
				case 'next':
					return $GLOBALS['egw']->importexport_definitions_ui->get_step($content['step'],1);
				case 'previous' :
					return $GLOBALS['egw']->importexport_definitions_ui->get_step($content['step'],-1);
				case 'finish':
					return 'wizard_finish';
				default :
					return $this->wizard_step60($content,$sel_options,$readonlys,$preserv);
			}
		}
		// init step40
		else
		{
			$content['msg'] = $this->steps['wizard_step40'];
			$content['step'] = 'wizard_step40';
			if(!$content['charset'] && $content['plugin_options']['charset']) {
				$content['charset'] = $content['plugin_options']['charset'];
			}
			$sel_options['charset'] = Api\Translation::get_installed_charsets()+
				array(
					'user'  => lang('User preference'),
				);

			// Add in extra allowed charsets
			$config = Api\Config::read('importexport');
			$extra_charsets = array_intersect(explode(',',$config['import_charsets']), mb_list_encodings());
			if($extra_charsets)
			{
				$sel_options['charset'] += array(lang('Extra encodings') => array_combine($extra_charsets,$extra_charsets));
			}

			$preserv = $content;
			unset ($preserv['button']);
			return 'addressbook.importexport_wizard_vcard_charset';
		}
	}

	function wizard_step60(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		if($this->debug) error_log(__METHOD__.print_r($content,true));

		// return from step60
		if ($content['step'] == 'wizard_step60')
		{
			switch (array_search('pressed', $content['button']))
			{
				case 'next':
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
			$content['title'] = $this->steps['wizard_step60'];
			$content['step'] = 'wizard_step60';
			if (!array_key_exists('contact_owner', $content) && $content['plugin_options']) {
				$content['contact_owner'] = $content['plugin_options']['contact_owner'];
			}
			if(!array_key_exists('change_owner', $content) && $content['plugin_options']) {
				$content['change_owner'] = $content['plugin_options']['change_owner'];
			}

			$bocontacts = new Api\Contacts();
			$sel_options['contact_owner'] = array('personal' => lang("Importer's personal")) + $bocontacts->get_addressbooks(Acl::ADD);

			foreach(array('override_values') as $field)
			{
				if(!$content[$field] && is_array($content['plugin_options']) && array_key_exists($field, $content['plugin_options']))
				{
					$content[$field] = $content['plugin_options'][$field];
				}
			}
			$preserv = $content;
			unset ($preserv['button']);
			return 'addressbook.importexport_wizard_vcard_chooseowner';
		}
	}
}