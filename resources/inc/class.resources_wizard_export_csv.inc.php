<?php
/**
 * eGroupWare - Wizard for Resources CSV export
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package resources
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

use EGroupware\Api;

class resources_wizard_export_csv extends importexport_wizard_basic_export_csv
{
	public function __construct() {
		parent::__construct();

		// Field mapping
		$this->export_fields = resources_bo::$field2label;

		// Fields deprecated, but still in DB
		unset($this->export_fields['buyable']);
		unset($this->export_fields['prize']);

		// Custom fields
		$custom = Api\Storage\Customfields::get('resources', true);
		foreach($custom as $name => $data) {
			$this->export_fields['#'.$name] = $data['label'];
		}
	}

	public function wizard_step50(&$content, &$sel_options, &$readonlys, &$preserv) {
		if($this->debug || true) error_log(get_class($this) . '::wizard_step50->$content '.print_r($content,true));
		// return
		if ($content['step'] == 'wizard_step50')
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
					return $this->wizard_step50($content,$sel_options,$readonlys,$preserv);
			}
		}
		// init step
		else
		{
			$content['step'] = 'wizard_step50';
			$content['msg'] = $this->steps[$content['step']];
			$preserv = $content;
			unset ($preserv['button']);
			$fields = array('pm_used_time', 'pm_planned_time', 'pm_replanned_time');
			$sel_options = array_fill_keys($fields, array('h' => lang('hours'), 'd' => lang('days')));
			foreach($fields as $field) {
				$content[$field] = $content[$field] ? $content[$field] : $content['plugin_options'][$field];
			}
		}
		return $this->step_templates[$content['step']];
	}
}
