<?php
/**
 * eGroupWare - Calendar planner block for sitemgr
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2010 by RalfBecker-At-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Calendar planner block for sitemgr
 */
class module_calendar_planner extends Module
{
	/**
	 * Default callendar CSS file
	 */
	const CALENDAR_CSS = '/calendar/templates/default/app.css';

	/**
	 * Constructor
	 */
	function __construct()
	{
		$this->arguments = array(
			'sortby' => array(
				'type' => 'select',
				'label' => lang('Type of planner'),
				'options' => array(
					0 => lang('Planner by category'),
					'user' => lang('Planner by user'),
					'month' => lang('Yearly Planner'),
					'yearly' => lang('Yearly Planner').' ('.lang('initially year aligned').')',
				),
			),
			'cat_id' => array(
				'type' => 'select',
				'label' => lang('Choose a category'),
				'options' => array(),	// done by get_user_interface()
				'multiple' => true,
			),
			'filter' => array(
				'type' => 'select',
				'label' => lang('Filter'),
				'options' => array(
					'default'     => lang('Not rejected'),
					'accepted'    => lang('Accepted'),
					'unknown'     => lang('Invitations'),
					'tentative'   => lang('Tentative'),
					'rejected'    => lang('Rejected'),
					'owner'       => lang('Owner too'),
					'all'         => lang('All incl. rejected'),
					'hideprivate' => lang('Hide private infos'),
					'no-enum-groups' => lang('only group-events'),
				),
				'default' => 'default',
			),
			'date' => array(
				'type' => 'textfield',
				'label' => 'Startdate as YYYYmmdd (empty for current date)',
				'default' => '',
				'params' => array('size' => 10),
			),
		);
		$this->title = lang('Calendar - Planner');
		$this->description = lang('This module displays a planner calendar.');
	}

	/**
	 * Reimplemented to fetch the cats
	 */
	function get_user_interface()
	{
		$cats = new categories('','calendar');
		foreach($cats->return_array('all',0,False,'','cat_name','',True) as $cat)
		{
			$this->arguments['cat_id']['options'][$cat['id']] = str_repeat('&nbsp; ',$cat['level']).$cat['name'];
		}
		if (count($cat_ids) > 5)
		{
			$this->arguments['cat_id']['multiple'] = 5;
		}
		return parent::get_user_interface();
	}

	/**
	 * Get block content
	 *
	 * @param $arguments
	 * @param $properties
	 */
	function get_content(&$arguments,$properties)
	{
		translation::add_app('calendar');

		$arguments['view'] = 'planner';
		if (empty($arguments['date']))
		{
			$arguments['date'] = date('Ymd');
		}
		if ($arguments['sortby'] == 'yearly')
		{
			$arguments['sortby'] = 'month';
			$arguments['date'] = date('Y0101');
		}
		if (isset($_GET['date'])) $arguments['date'] = $_GET['date'];
		if (empty($arguments['cat_id'])) $arguments['cat_id'] = 0;

		$uiviews = new calendar_uiviews($arguments);
		$uiviews->allowEdit = false;	// switches off all edit popups

		$html = '<style type="text/css">'."\n";
		$html .= '@import url('.$GLOBALS['egw_info']['server']['webserver_url'].self::CALENDAR_CSS.");\n";
		$html .= '</style>'."\n";

		// Initialize Tooltips
		static $wz_tooltips;
		if (!$wz_tooltips++) $html .= '<script language="JavaScript" type="text/javascript" src="'.$GLOBALS['egw_info']['server']['webserver_url'].'/phpgwapi/js/wz_tooltip/wz_tooltip.js"></script>'."\n";

		// replacing egw-urls with sitemgr ones, allows to use navigation links
		$html .= str_replace($GLOBALS['egw_info']['server']['webserver_url'].'/index.php?',
			$this->link().'&',
			$uiviews->planner(true));

		return $html;
	}
}
