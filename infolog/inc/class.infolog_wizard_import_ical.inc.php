<?php
/**
 * EGroupware - Wizard for user CSV import
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package calendar
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

use EGroupware\Api;

class infolog_wizard_import_ical
{
	/**
	* List of steps.  Key is the function, value is the translated title.
	*/
	public $steps;

	/**
	* List of eTemplates to use for each step.  You can override this with your own etemplates steps.
	*/
	protected $step_templates = array(
		'wizard_step55' => 'infolog.import.ical_conditions',
	);
    /**
	 * constructor
	 */
	function __construct()
	{
		Api\Framework::includeJS('.','et2_widget_owner','calendar');
		Api\Framework::includeCSS('calendar','calendar');
		$this->steps = array(
			'wizard_step55' => lang('Edit conditions'),
		);
	}

	// Conditions
	function wizard_step55(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		// return from step55
		if ($content['step'] == 'wizard_step55')
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
					return $this->wizard_step55($content,$sel_options,$readonlys,$preserv);
			}
		}
		// init step30
		else
		{
			$content['text'] = $this->steps['wizard_step55'];
			$content['step'] = 'wizard_step55';
			foreach(array('override_values') as $field)
			{
				if(!$content[$field] && array_key_exists($field, $content['plugin_options']))
				{
					$content[$field] = $content['plugin_options'][$field];
				}
			}
			$preserv = $content;
			unset ($preserv['button']);

			// No real conditions, but we share a template
			$content['no_conditions'] = true;

			return $this->step_templates[$content['step']];
		}
	}
}
