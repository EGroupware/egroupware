<?php
/**
 * Wizard for exporting vCard with import/export
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package addressbook
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 */

use EGroupware\Api;

/**
 * We need to allow choosing of charset, so we'll just use the standard one from CSV
 */
class addressbook_wizard_export_vcard
{
	public function __construct()
	{
		$this->steps = array(
			'wizard_step40' => ''
		);
		$this->step_templates = array(
			'wizard_step40' => 'addressbook.importexport_wizard_vcard_charset'
		);
	}

	/**
         * choose charset
         *
         * @param array $content
         * @param array $sel_options
         * @param array $readonlys
         * @param array $preserv
         * @return string template name
         */
        function wizard_step40(&$content, &$sel_options, &$readonlys, &$preserv)
        {
                if($this->debug) error_log(get_class($this) . '::wizard_step40->$content '.print_r($content,true));
                // return from step40
                if ($content['step'] == 'wizard_step40') {
                        switch (array_search('pressed', $content['button']))
                        {
                                case 'next':
                                        return $GLOBALS['egw']->importexport_definitions_ui->get_step($content['step'],1);
                                case 'previous' :
                                        return $GLOBALS['egw']->importexport_definitions_ui->get_step($content['step'],-1);
                                case 'finish':
                                        return 'wizard_finish';
                                default :
                                        return $this->wizard_step40($content,$sel_options,$readonlys,$preserv);
                        }
                }
                // init step40
                else
                {
                        $content['msg'] = $this->steps['wizard_step40'];
                        $content['step'] = 'wizard_step40';
			if(!$content['charset'] && $content['plugin_options']['charset']) {
                                $content['charset'] = $content['plugin_options']['charset'] ? $content['plugin_options']['charset'] : 'user';
                        }
			$sel_options['charset'] = Api\Translation::get_installed_charsets()+
                        array(
                                'user'  => lang('User preference'),
                        );
			$preserv = $content;

                        // Add in extra allowed charsets
                        $config = Api\Config::read('importexport');
                        $extra_charsets = array_intersect(explode(',',$config['import_charsets']), mb_list_encodings());
                        if($extra_charsets)
                        {
                                $sel_options['charset'] += array(lang('Extra encodings') => array_combine($extra_charsets,$extra_charsets));
                        }
			unset ($preserv['button']);
                        return $this->step_templates[$content['step']];
		}
	}

}
