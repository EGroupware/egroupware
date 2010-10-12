<?php
/**
 * eGroupWare - A basic implementation of a wizard to go with the basic CSV plugin.
 * 
 * To add or remove steps, change $this->steps appropriately.  The key is the function, the value is the title.
 * Don't go past 80, as that's where the wizard picks it back up again to finish it off.
 * 
 * For the field list to work properly, you'll have to populate $export_fields with the fields available
 * 
 * NB: Your wizard class must be in <appname>/inc/class.appname_wizard_<plugin_name>.inc.php
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 */

class importexport_wizard_basic_export_csv 
{

	const TEMPLATE_MARKER = '-eTemplate-';

	/**
	* List of steps.  Key is the function, value is the translated title.
	*/
	public $steps;

	/**
	* List of eTemplates to use for each step.  You can override this with your own etemplates steps.
	*/
	protected $step_templates = array(
		'wizard_step30' => 'importexport.wizard_basic_export_csv.choose_fields',
		'wizard_step40' => 'importexport.wizard_basic_export_csv.choosesepncharset',
	);
		

	/**
	* Destination fields for the export
	* Key is the field name, value is the human version
	*/
	protected $export_fields = array();
	
	/**
	* List of conditions your plugin supports
	*/
	protected $conditions = array();

	/**
	* List of actions your plugin supports
	*/
	protected $actions = array();

	/**
	 * constructor
	 */
	function __construct()
	{
		$this->steps = array(
			'wizard_step30' => lang('Choose fields to export'),
			'wizard_step40' => lang('Choose seperator and charset'),
		);
	}

	/**
	* Choose fields to export
	*/
	function wizard_step30(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		if($this->debug || true) error_log(get_class($this) . '::wizard_step30->$content '.print_r($content,true));
		// return from step30
		if ($content['step'] == 'wizard_step30')
		{
			$content['mapping'] = array_combine($content['fields']['export'], $content['fields']['export']);
			unset($content['mapping']['']);
			unset($content['fields']);
			switch (array_search('pressed', $content['button']))
			{
				case 'next':
					return $GLOBALS['egw']->importexport_definitions_ui->get_step($content['step'],1);
				case 'previous' :
					return $GLOBALS['egw']->importexport_definitions_ui->get_step($content['step'],-1);
				case 'finish':
					return 'wizard_finish';
				default :
					return $this->wizard_step30($content,$sel_options,$readonlys,$preserv);
			}
		}
		// init step30
		else
		{
			$content['msg'] = $this->steps['wizard_step30'];
			$content['step'] = 'wizard_step30';
			$sel_options['field'] = $this->export_fields;
			$preserv = $content;
			unset ($preserv['button']);
			$content['fields'] = array();
			if(!$content['mapping']) $content['mapping'] = $content['plugin_options']['mapping'];
			$row = 0;
			foreach($this->export_fields as $field => $name) {
				$content['fields'][] = array(
					'field'	=>	$field,
					'name'	=>	$name,
				);
				if($content['mapping'][$field]) {
					$content['fields']['export'][$row] = $field;
				}
				$row++;
			}
//_debug_array($content);
			return $this->step_templates[$content['step']];
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

			// If editing an existing definition, these will be in plugin_options
			if(!$content['delimiter'] && $content['plugin_options']['delimiter']) {
				$content['delimiter'] = $content['plugin_options']['delimiter'];
			} elseif (!$content['delimiter']) {
				$content['delimiter'] = ';';
			}
			if(!$content['charset'] && $content['plugin_options']['charset']) {
				$content['charset'] = $content['plugin_options']['charset'];
			}
			if(!array_key_exists('begin_with_fieldnames', $content) && array_key_exists('begin_with_fieldnames', $content['plugin_options'])) {
				$content['begin_with_fieldnames'] = $content['plugin_options']['begin_with_fieldnames'];
			}

			$sel_options['charset'] = $GLOBALS['egw']->translation->get_installed_charsets()+
				array('utf-8' => 'utf-8 (Unicode)');
			$preserv = $content;
			unset ($preserv['button']);
			return $this->step_templates[$content['step']];
		}
		
	}
}
