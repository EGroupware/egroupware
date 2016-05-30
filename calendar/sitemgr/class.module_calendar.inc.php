<?php
/**
 * eGroupWare - Calendar planner block for sitemgr
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Calendar day selection for sitemgr
 */
class module_calendar extends Module
{
	function __construct()
	{
		$this->arguments = array(
			'redirect' => array(
				'type' => 'textfield',
				'label' => lang('Specify where URL of the day links to'),
			),
		);

		$this->title = lang('Calendar');
		$this->description = lang('This module displays the current month');
 	}

	function get_content(&$arguments,$properties)
	{
		$date = (int) (strtotime(get_var('date',array('POST','GET'))));
		$redirect = $arguments['redirect'] ? $arguments['redirect'] : '#';

		if (!file_exists(EGW_SERVER_ROOT.'/phpgwapi'))
		{
			return 'Requires old phpgwapi!';
		}

		return $GLOBALS['egw']->jscalendar->get_javascript().
			$GLOBALS['egw']->jscalendar->flat($redirect,$date);
	}
}
