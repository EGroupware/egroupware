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

class calendar_wizard_import_ical
{
	/**
	* List of steps.  Key is the function, value is the translated title.
	*/
	public $steps;

	/**
	* List of eTemplates to use for each step.  You can override this with your own etemplates steps.
	*/
	protected $step_templates = array(
		'wizard_step55' => 'calendar.import.ical_conditions',
		'wizard_step60' => 'calendar.importexport_wizard_ical_chooseowner'
	);
    /**
	 * constructor
	 */
	function __construct()
	{
		Api\Framework::includeCSS('calendar','calendar');
		$this->steps = array(
			'wizard_step55' => lang('Edit conditions'),
			'wizard_step60' => lang('Choose owner of imported data'),
		);
	}

	/* fix PHP 8 error: Cannot use "parent" when current class scope has no parent
	function wizard_step50(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		$result = parent::wizard_step50($content, $sel_options, $readonlys, $preserv);
		$content['msg'] .= "\n*" ;

		return $result;
	}*/

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
			foreach(array('skip_conflicts','empty_before_import','remove_past','remove_future','override_values') as $field)
			{
				if(!$content[$field] && is_array($content['plugin_options']) && array_key_exists($field, $content['plugin_options']))
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


	/**
	 * Set / override owner
	 */
	function wizard_step60(&$content, &$sel_options, &$readonlys, &$preserv)
	{
		if($this->debug) error_log('addressbook.importexport.addressbook_csv_import::wizard_step60->$content '.print_r($content,true));
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
			$content['text'] = $this->steps['wizard_step60'];
			$content['step'] = 'wizard_step60';
			if(!array_key_exists('cal_owner', $content) && $content['plugin_options'])
			{
				$content['cal_owner'] = $content['plugin_options']['cal_owner'];
			}

			// Include calendar-owner widget
			Api\Framework::includeJS('/calendar/js/app.min.js');
			Api\Framework::includeCSS('calendar', 'calendar');
			$preserv = $content;
			unset ($preserv['button']);
			return $this->step_templates[$content['step']];
		}
	}
}
