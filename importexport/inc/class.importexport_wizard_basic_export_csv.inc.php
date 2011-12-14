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
		list($appname, $part2) = explode('_', get_class($this));
		if(!$GLOBALS['egw_info']['apps'][$appname]) $appname .= '_'.$part2; // Handle apps with _ in the name
		translation::add_app($appname);
	}

	/**
	* Choose fields to export
	*/
	function wizard_step30(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		if($this->debug) error_log(get_class($this) . '::wizard_step30->$content '.print_r($content,true));
		// return from step30
		if ($content['step'] == 'wizard_step30')
		{
			foreach($content['fields']['export'] as $field_name)
			{
				// Preserve original field names, where available
				if($content['plugin_options']['no_header_translation'] && $content['plugin_options']['mapping'][$field_name])
				{
					$content['mapping'][$field_name] = $content['plugin_options']['mapping'][$field_name];
				}
				else
				{
					$content['mapping'][$field_name] = $field_name;
				}
			}
			if($content['mapping']['all_custom_fields']) {
				// Need the appname during actual export, to fetch the fields
				$parts = explode('_', get_class($this));
				$appname = $parts[0];
				foreach($parts as $name_part) {
					if($GLOBALS['egw_info']['apps'][$appname]) break;
					$appname .= '_'.$name_part; // Handle apps with _ in the name
				}
				$content['mapping']['all_custom_fields'] = $appname;
			}
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
			$this->export_fields += array('all_custom_fields' => 'All custom fields');
			$sel_options['field'] = $this->export_fields;
			$preserv = $content;
			unset ($preserv['button']);
			unset ($preserv['fields']);
			$content['fields'] = array('');
			if(!$content['mapping']) $content['mapping'] = $content['plugin_options']['mapping'];
		
			$row = 1;
			foreach($this->export_fields as $field => $name) {
				$content['fields'][] = array(
					'field'	=>	$field,
					'name'	=>	lang($name),
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
			if($content['begin_with_fieldnames'] == 'label') {
				foreach($content['mapping'] as $field => &$label) {
					// Check first, to avoid clearing any pseudo-columns (ex: All custom fields)
					$label = $this->export_fields[$field] ? $this->export_fields[$field] : $label;
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
				$content['charset'] = $content['plugin_options']['charset'] ? $content['plugin_options']['charset'] : 'user';
			}
			if(!array_key_exists('begin_with_fieldnames', $content) && array_key_exists('begin_with_fieldnames', $content['plugin_options'])) {
				$content['begin_with_fieldnames'] = $content['plugin_options']['begin_with_fieldnames'];
			}
			if(!array_key_exists('convert', $content) && array_key_exists('convert', $content['plugin_options'])) {
				$content['convert'] = $content['plugin_options']['convert'];
			}


			$sel_options['begin_with_fieldnames'] = array(
				0	=> lang('No'),
				1	=> lang('Field names'),
				'label'	=> lang('Field labels')
			);
			$sel_options['charset'] = $GLOBALS['egw']->translation->get_installed_charsets()+
			array(
                                'user'  => lang('User preference'),
                        );

                        // Add in extra allowed charsets
                        $config = config::read('importexport');
                        $extra_charsets = array_intersect(explode(',',$config['import_charsets']), mb_list_encodings());
                        if($extra_charsets)
                        {
                                $sel_options['charset'] += array(lang('Extra encodings') => array_combine($extra_charsets,$extra_charsets));
                        }
			$sel_options['convert'] = array(
				0	=> lang('Database values'),
				1	=> lang('Human friendly values')
			);
			$preserv = $content;
			unset ($preserv['button']);
			return $this->step_templates[$content['step']];
		}
		
	}

	/**
	 * Expose export fields for use elsewhere
	 */
	public function get_export_fields()
	{
		return $this->export_fields;
	}
}
