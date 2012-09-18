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

class addressbook_wizard_import_vcard extends addressbook_import_vcard
{

	/**
	 * constructor
	 */
	function __construct()
	{

		$this->steps = array(
			'wizard_step60' => lang('Choose owner of imported data'),
		);

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
			$content['msg'] = $this->steps['wizard_step60'];
			$content['step'] = 'wizard_step60';
			if(!array_key_exists($content['contact_owner']) && $content['plugin_options']) {
				$content['contact_owner'] = $content['plugin_options']['contact_owner'];
			}
			if(!array_key_exists($content['change_owner']) && $content['plugin_options']) {
				$content['change_owner'] = $content['plugin_options']['change_owner'];
			}

			$bocontacts = new addressbook_bo();
			$sel_options['contact_owner'] = array('personal' => lang("Importer's personal")) + $bocontacts->get_addressbooks(EGW_ACL_ADD);

			$preserv = $content;
			unset ($preserv['button']);
			return 'addressbook.importexport_wizard_vcard_chooseowner';
		}
		
	}
}
