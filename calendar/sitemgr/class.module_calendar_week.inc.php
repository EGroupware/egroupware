<?php
/**************************************************************************\
 * eGroupWare SiteMgr - Web Content Management                              *
 * http://www.egroupware.org                                                *
 * --------------------------------------------                             *
 *  This program is free software; you can redistribute it and/or modify it *
 *  under the terms of the GNU General Public License as published by the   *
 *  Free Software Foundation; either version 2 of the License, or (at your  *
 *  option) any later version.                                              *
 \**************************************************************************/

use EGroupware\Api;
use EGroupware\Api\Link;
use EGroupware\Api\Framework;
use EGroupware\Api\Acl;
use EGroupware\Api\Etemplate;

/* $Id$ */

class module_calendar_week extends Module
{


	/**
	 * Default calendar CSS file
	 */
	const CALENDAR_CSS = '/calendar/templates/default/app.css';

	const ETEMPLATE_CSS = '/api/templates/default/etemplate2.css';

	/**
	 * Instance of the business object of calendar
	 *
	 * @var bo
	 */
	var $bo;
	/**
	 * Instance of the user interface object of calendar
	 *
	 * @var ui
	 */
	var $ui;
	/**
	 * Instance of the user interface object of calendar
	 *
	 * @var ui
	 */
	var $uiviews;
	/**
	 * Instance of the Api\Accounts object
	 *
	 * @var Api\Accounts
	 */
	var $accounts;
	/**
	 * Default CSS style
	 *
	 * @var default_css
	 */
	var $default_css = '/calendar/templates/default/app.css';

	function __construct()
	{
		parent::__construct();

		$this->arguments = array(
			'cat_id' => array(
				'type' => 'select',
				'label' => lang('Choose a category'),
				'options' => array(),	// specification of options is postponed into the get_user_interface function
				'multiple' => true,
			),
			'numWeeks' => array(
				'type' => 'textfield',
				'label' => lang('Number of weeks to show'),
				'default' => 2,
				'params' => array('size' => 1)
			),
			'search' => array(
				'type' => 'textfield',
				'label' => lang('Search string for the events'),
			),
			'owner' => array(
				'type' => 'select',
				'options' => array(),
				'label' => lang('Group(s) or user(s) whose calendars to show (if ACL exists)'),
				// 'multiple' => true, is set in the get_user_interface function.
			),
			'resources' => array(
				'type' => 'select',
				'label' => lang('Resources'),
				'options' => array(),
				'multiple' => true
			),
			'css' => array(
				'type' => 'textfield',
				'label' => lang('User selectable CSS file for the calendar setup'),
				'default' => $this->default_css,
			),
			'acceptDateParam' => array(
				'type' => 'checkbox',
				'label' => lang('Shall the date parameter be accepted (e.g. from calendar module)?'),
				'default' => false,
			),
		);
		$this->title = lang('Calendar - Multi-Weekly');
		$this->description = lang("This module displays a user's calendar as multiple weeks. Don't give calendar application access to the anon user!");
	}

	function get_user_interface()
	{
		// copied from bookmarks module.
		$cats = new Api\Categories('','calendar');
		foreach($cats->return_array('all',0,False,'','cat_name','',True) as $cat)
		{
			$this->arguments['cat_id']['options'][$cat['id']] = str_repeat('&nbsp; ',$cat['level']).$cat['name'];
		}
		if (count($this->arguments['cat_id']['options']) > 5)
		{
			$this->arguments['cat_id']['multiple'] = 5;
		}

		if (! isset($GLOBALS['egw']->accounts))
		{
			$GLOBALS['egw']->accounts = new Api\Accounts();
		}
		$this->accounts =& $GLOBALS['egw']->accounts;
		$search_params=array(
			'type' => 'both',
			'app' => 'calendar',
		);
		$accounts = $this->accounts->search($search_params);
		$calendar_bo = new calendar_bo();
		$users = array();
		$groups = array();
		// sort users and groups separately.
		$anon_user = $this->accounts->name2id($GLOBALS['Common_BO']->sites->current_site['anonymous_user'],'account_lid','u');
		$anon_groups = $this->accounts->memberships($anon_user,true);
		foreach ($accounts as $entry)
		{
			$is_group = false;
			$has_read_permissions = false;
			$acl = new Acl($entry['account_id']);
			$acl->read_repository();
			// get the rights for each account to check whether the anon user has read permissions.
			$rights = $acl->get_rights($anon_user,'calendar');
			// also add the anon user if it's his own calendar.
			if ($calendar_bo->check_perms(Acl::READ|calendar_bo::ACL_READ_FOR_PARTICIPANTS|calendar_bo::ACL_FREEBUSY,0,$entry['account_id'],'ts',null,$anon_user) || ($entry['account_id'] == $anon_user))
			{
				$has_read_permissions = true;
			}
			else
			{
				// scan the groups which pass on permissions to the anon user group member
				// or ass permissions if this is the anon group's calendar.
				foreach ($anon_groups as $parent_group)
				{
					$rights = $acl->get_rights($parent_group,'calendar');
					if (($rights & Acl::READ) || ($entry['account_id'] == $parent_group))
					{
						$has_read_permissions = true;
						break;
					}
				}
			}
			if ($has_read_permissions)
			{
				// Separate groups from users for improved readability.
				if ($is_group)
				{
					$groups[$entry['account_id']] = $entry['account_lid'];
				}
				else
				{
					$users[$entry['account_id']] = Api\Accounts::format_username($entry['account_lid'],$entry['account_firstname'],$entry['account_lastname']);
				}
			}
		}
		asort($groups);
		asort($users);
		// concat users and groups to the option array.
		$this->arguments['owner']['options'] = array_unique($groups + $users);
		if (count($this->arguments['owner']['options']) > 10)
		{
			$this->arguments['owner']['multiple'] = 10;
		}
		else if (count($this->arguments['owner']['options']) > 0)
		{
			$this->arguments['owner']['multiple'] = true;
		}

		// Resources
		$query = '';
		$options = array('start' => 0);

		$acl = new Acl($anon_user);
		$acl->read_repository();
 		foreach ($calendar_bo->resources as $type => $data)
		{
			// Check anon user's permissions - must have at least run for the hook to be available
			if($acl->check('run',EGW_ACL_READ, $data['app']) &&
				$type != '' && $data['app'] && Link::get_registry($data['app'], 'query')
			)
			{
				$_results = Link::query($data['app'], $query,$options);
			}
			if(!$_results) continue;
			$_results = array_unique($_results);
			foreach ($_results as $key => $value)
			{
				if($calendar_bo->check_perms(Acl::READ,0,$type.$key,'ts',null,$anon_user))
				{
					$this->arguments['resources']['options'][$type.$key] = $value;
				}
			}
		}

		return parent::get_user_interface();
	}

	function get_content(&$arguments,$properties)
	{
		$GLOBALS['egw_info']['flags']['currentapp'] = 'calendar';
		Api\Translation::add_app('calendar');

		//error_log(array2string($arguments));
		if (empty($arguments['date']))
		{
			$arguments['date'] = date('Ymd');
		}

		if (isset($_GET['date'])) $arguments['date'] = $_GET['date'];
		if (empty($arguments['cat_id'])) $arguments['cat_id'] = '';
		if(isset($arguments['resources']) && in_array('r0', $arguments['resources']))
		{
			foreach($arguments['resources'] as $index => $value)
			{
				if($value == 'r0')
				{
					unset($arguments['resources'][$index]);
				}
			}
		}

		$params = $arguments;
		if (isset($params['resources']) && count($params['resources']))
		{
			$params['owner'] = array_merge((array)$params['owner'], (array)$params['resources']);
			unset($params['resources']);
		}

		$html = '<style type="text/css">'."\n";
		$html .= '@import url('.$GLOBALS['egw_info']['server']['webserver_url'].self::CALENDAR_CSS.");\n";
		$html .= '@import url('.$GLOBALS['egw_info']['server']['webserver_url'].self::ETEMPLATE_CSS.");\n";
		$html .= '@import url('.$GLOBALS['egw_info']['server']['webserver_url'].Api\Categories::css(Api\Categories::GLOBAL_APPNAME).");\n";
		$html .= '@import url('.$GLOBALS['egw_info']['server']['webserver_url'].Api\Categories::css('calendar').");\n";
		$html .= '.popupMainDiv #calendar-view {position: static; min-height: 250px; height: 99% !important;}
	</style>'."\n";
		$html .= Api\Html::image('sitemgr', 'left', lang('Previous'), 'onclick=\'app.calendar.toolbar_action({id:"previous",data:{state:{view:"weekN"}}});\'')
		. Api\Html::image('sitemgr', 'right', lang('Next'), 'style="float: right;" onclick=\'app.calendar.toolbar_action({id:"next",data:{state:{view:"weekN"}}});\'');

		if (is_array($params['owner']))
		{
			// Buffer, and add anything that gets cleared to the content
			ob_start(function($buffer) use(&$html) {
				$html .= $buffer;
				return '';
			});
			Framework::$header_done = true;
			$ui = new calendar_uiviews();
			$ui->owner = $params['owner'];

			$tmpl = new Etemplate('calendar.view');

			$start = new Api\DateTime($arguments['date']);
			$start->setWeekstart();
			$ui->first = $start->format('ts');
			$ui->last = strtotime("+{$params['numWeeks']} weeks",$ui->first) - 1;

			// Calendar uses user preferences for number of weeks, so set it
			if((int)$params['numWeeks'] != (int)$ui->cal_prefs['multiple_weeks'])
			{
				$anon_user = $GLOBALS['egw']->accounts->name2id($GLOBALS['Common_BO']->sites->current_site['anonymous_user'],'account_lid','u');
				$pref = new Api\Preferences($anon_user);
				$pref->add('calendar','multiple_weeks',(int)$params['numWeeks']);
				$pref->save_repository();
			}

			$navHeader = lang('Week').' '.$ui->week_number($ui->first).' - '.$ui->week_number($this->last).': '.
				$ui->bo->long_date($ui->first,$ui->last);

			$granularity = ($ui->cal_prefs['interval'] ? (int)$ui->cal_prefs['interval'] : 30);
			
			$content = array('view' => array());

			$sel_options = array();

			$ui->search_params['query'] = $params['search'];
			$ui->search_params['cat_id'] = $params['cat_id'];

			// Loop through, using Api\DateTime to handle DST
			$week = 0;
			$week_start = new EGroupware\Api\DateTime($ui->first);
			$week_start->setTime(0,0,0);
			$week_end = new Api\DateTime($week_start);
			$week_end->add(new DateInterval('P6DT23H59M59S'));
			$last = new EGroupware\Api\DateTime($ui->last);
			
			for ($week_start; $week_start < $last; $week_start->add('1 week'), $week_end->add('1 week'))
			{
				$search_params = $ui->search_params;

				$search_params['start'] = $week_start->format('ts');
				$search_params['end'] = $week_end->format('ts');

				$content['view'][] = (array)$ui->tagWholeDayOnTop($ui->bo->search($search_params)) +
				array(
					'id' => $week_start->format('Ymd')
				);
				$tmpl->setElementAttribute("view[$week]",'onchange',false);
				$tmpl->setElementAttribute("view[$week]",'granularity',$granularity);
				$tmpl->setElementAttribute("view[$week]",'height','250px');
				$week++;
			}


			// Make sure all used owners are there, faking
			// calendar_owner_etemplate_widget::beforeSendToClient() since the
			// rest of the calendar app is probably missing.
			foreach($params['owner'] as $owner)
			{
				$sel_options['owner'][] = Array(
					'id' => $owner,
					'value' => $owner,
					'label' => calendar_owner_etemplate_widget::get_owner_label($owner)
				);
			}
			$tmpl->exec(__METHOD__, $content,$sel_options, array('__ALL__' => true),array(),2);
			$html .= ob_get_contents();
			
			$html .= '<script>'
			. '	window.egw_LAB.wait(function() {jQuery(function() {'
			. 'app.calendar.set_state(' . json_encode(array(
					'owner' => $params['owner'],
					'date' => $start->format(EGroupware\Api\DateTime::ET2)
				)).'); '
			. '});});'
			. '</script>';

			ob_end_clean();
		}
		else
		{
			$html .= '<div class="message" align="center">'.lang('No owner selected').'</div>';
		}

		return $html;
	}
}
