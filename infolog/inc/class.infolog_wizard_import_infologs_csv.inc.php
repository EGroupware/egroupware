<?php
/**
 * eGroupWare - Wizard for Infolog CSV import
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package addressbook
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id:  $
 */

class infolog_wizard_import_infologs_csv extends importexport_wizard_basic_import_csv
{

	/**
	 * constructor
	 */
	function __construct()
	{
		parent::__construct();

		$this->steps += array(
			'wizard_step50' => lang('Manage mapping'),
			# This doesn't work with infolog very well
			#'wizard_step60' => lang('Choose owner of imported data'),
		);

		// Field mapping
		$tracking = new infolog_tracking();
		$this->mapping_fields = array('info_id' => 'Infolog ID') + $tracking->field2label + infolog_import_infologs_csv::$special_fields;
		// List each custom field
		unset($this->mapping_fields['custom']);
		$custom = config::get_customfields('infolog');
		foreach($custom as $name => $data) {
			$this->mapping_fields['#'.$name] = $data['label'];
		}

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
	
	# Skipped for now (or forever)
	function wizard_step60(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		if($this->debug) error_log(__METHOD__.'->$content '.print_r($content,true));
		unset($content['no_owner_map']);
		// Check that record owner has access
		$access = true;
		if($content['record_owner'])
		{
			$bo = new infolog_bo();
			$access = $bo->check_access(0,EGW_ACL_EDIT, $content['record_owner']);
		}

		// return from step60
		if ($content['step'] == 'wizard_step60')
		{
			if(!$access) {
				$step = $content['step'];
				unset($content['step']);
				return $step;
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
					return $this->wizard_step60($content,$sel_options,$readonlys,$preserv);
			}
		}
		// init step60
		else
		{
			$content['msg'] = $this->steps['wizard_step60'];
			if(!$access) {
				$content['msg'] .= "\n* " . lang('Owner does not have edit rights');
			}
			$content['step'] = 'wizard_step60';
			if(!array_key_exists($content['record_owner']) && $content['plugin_options']) {
				$content['record_owner'] = $content['plugin_options']['record_owner'];
			}
			if(!array_key_exists($content['owner_from_csv']) && $content['plugin_options']) {
				$content['owner_from_csv'] = $content['plugin_options']['owner_from_csv'];
			}
			if(!array_key_exists($content['change_owner']) && $content['plugin_options']) {
				$content['change_owner'] = $content['plugin_options']['change_owner'];
			}

			if(!in_array('info_owner', $content['field_mapping'])) {
				$content['no_owner_map'] = true;
			}

			$preserv = $content;
			unset ($preserv['button']);
			return 'infolog.importexport_wizard_chooseowner';
		}
		
	}
}
