<?php
/**
 * eGroupWare - Wizard for Timesheet CSV import
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package timesheet
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

class timesheet_wizard_import_csv extends importexport_wizard_basic_import_csv
{

	/**
	 * constructor
	 */
	function __construct()
	{
		parent::__construct();

		$this->steps += array(
			'wizard_step50' => lang('Manage mapping'),
			'wizard_step60' => lang('Choose \'creator\' of imported data'),
		);

		// Field mapping
		$bo = new timesheet_bo();
		$this->mapping_fields = array('ts_id' => lang('Timesheet ID')) + $bo->field2label;

		// These aren't in the list
                $this->mapping_fields += array(
                        'ts_modified'   => lang('Modified'),
                );

		// List each custom field
		unset($this->mapping_fields['customfields']);
		$custom = config::get_customfields('timesheet');
		foreach($custom as $name => $data) {
			$this->mapping_fields['#'.$name] = $data['label'];
		}

		$this->mapping_fields += tracker_import_csv::$special_fields;

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
		
		return $result;
	}
	
	function wizard_step60(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		if($this->debug) error_log(__METHOD__.'->$content '.print_r($content,true));
		unset($content['no_owner_map']);

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
			if(!array_key_exists($content['creator']) && $content['plugin_options']) {
				$content['creator'] = $content['plugin_options']['creator'];
			}
			if(!array_key_exists($content['creator_from_csv']) && $content['plugin_options']) {
				$content['creator_from_csv'] = $content['plugin_options']['creator_from_csv'];
			}
			if(!array_key_exists($content['change_creator']) && $content['plugin_options']) {
				$content['change_creator'] = $content['plugin_options']['change_creator'];
			}

			if(!in_array('ts_creator', $content['field_mapping'])) {
				$content['no_owner_map'] = true;
			}

			$preserv = $content;
			unset ($preserv['button']);
			return 'infolog.importexport_wizard_chooseowner';
		}
		
	}
}
