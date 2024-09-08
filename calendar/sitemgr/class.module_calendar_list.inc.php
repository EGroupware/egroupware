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
use EGroupware\Api\Acl;

/* $Id$ */

/*
// AN EXAMPLE STYLESHEET
.cal_list_event {
 padding-bottom: 5px;
}

.cal_list_title {
 clear: both;
 font-weight: bold;
}

.cal_list_date {
 clear: both;
 padding-left:15px;
}

.cal_list_weekday {
}

.cal_list_descr {
 clear: both;
 padding-left: 15px;
 font-style: italic;
}

.cal_list_end {
  display: none;
}

.cal_event_even {
  background: #F1F1F1;
}

.cal_event_uneven {
}

.cal_list_weeksplit {
  width = 80%;
  vertical-align: center;
  border-top-width: 1px;
}

.cal_list_weektop {
}

.cal_list_weeksplit {
  width: 100%;
  border-top: 1px;
  border-top-style: solid;
}

.cal_list_weekbottom {
}

*/

class module_calendar_list extends Module
{
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

	function __construct()
	{
		$this->arguments = array(
			'category' => array(
				'type' => 'select',
				'label' => lang('Choose a category'),
				'options' => array(),	// specification of options is postponed into the get_user_interface function
				'multiple' => true,
			),
			'numWeeks' => array(
				'type' => 'textfield',
				'label' => lang('Number of weeks to show'),
				'default' => '4',
				'params' => array('size' => 1),
			),
			'showTitle' => array(
				'type' => 'checkbox',
				'label' => lang('Show a calendar title'),
				'default' => false,
			),
			'offset' => array(
				'type' => 'textfield',
				'label' => lang('Weeks offset (for multi-column display)'),
				'default' => '0',
				'params' => array('size' => 1),
			),
			'search' => array(
				'type' => 'textfield',
				'label' => lang('Search string for the events'),
			),
			'numEntries' => array(
				'type' => 'textfield',
				'label' => lang('Max. Number of entries to show (leave empty for no restriction)'),
				'default' => '',
				'params' => array('size' => 1),
			),
			'entryOffset' => array(
				'type' => 'textfield',
				'label' => lang('How much entries to skip'),
				'default' => '0',
				'params' => array('size' => 1),
			),
			'users' => array(
				'type' => 'select',
				'options' => array(),
				'label' => lang('Group(s) or user(s) whose calendars to show (if ACL exists)'),
				// 'multiple' => true, is set in the get_user_interface function.
			),
			'showWeeks' => array(
				'type' => 'checkbox',
				'label' => lang('Should the number of weeks be shown on top of the calendar (only if offset = 0)'),
				'default' => false,
			),
			'useWeekStart' => array(
				'type' => 'checkbox',
				'label' => lang('Use weekday start'),
				'default' => false,
			),
			'acceptDateParam' => array(
				'type' => 'checkbox',
				'label' => lang('Shall the date parameter be accepted (e.g. from calendar module)?'),
				'default' => false,
			),
		);
		$this->title = lang('Calendar - List');
		$this->description = lang("This module displays calendar events as a list.");
	}

	function get_user_interface()
	{
		//_debug_array($GLOBALS['Common_BO']->sites->current_site);
		// copied from bookmarks module.
		$cat = new Api\Categories('','calendar');
		$cats = $cat->return_array('all',0,False,'','cat_name','',True);
		$cat_ids = array();
		foreach($cats as $category)
		{
			$cat_ids[$category['id']] = $GLOBALS['egw']->strip_html($category['name']);
		}
		$this->arguments['category']['options'] = $cat_ids;
		if (count($cat_ids) > 5) {
			$this->arguments['category']['multiple'] = 5;
		}

		if (!isset($GLOBALS['egw']->accounts))
		{
			$GLOBALS['egw']->accounts = new Api\Accounts();
		}
		$this->accounts =& $GLOBALS['egw']->accounts;
		$search_params = array(
			'type' => 'both',
			'app' => 'calendar',
		);
		$accounts = $this->accounts->search($search_params);
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
			if (($rights & Acl::READ) || ($entry['account_id'] == $anon_user))
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
		$this->arguments['users']['options'] = $groups + $users;
		if (count($this->arguments['users']['options']) > 10)
		{
			$this->arguments['users']['multiple'] = 10;
		}
		else if (count($this->arguments['users']['options']) > 0)
		{
			$this->arguments['users']['multiple'] = true;
		}

		return parent::get_user_interface();
	}

	function get_content(&$arguments,$properties)
	{
		$html = "";
		Api\Translation::add_app('calendar');
		$this->bo = new calendar_bo();
		$this->ui = new calendar_uiviews();
		$this->ui->allowEdit = false;
		$this->ui->use_time_grid = isset($arguments['grid']) ? $arguments['grid'] : false;

		$weeks = $arguments['numWeeks'] ? (int) $arguments['numWeeks'] : 4;
		$dateOffset = $arguments['offset'] ? (int) $arguments['offset'] : 0;

		if (($arguments['acceptDateParam']) && (get_var('date',array('POST','GET'))))
		{
			$first = (int) (strtotime(get_var('date',array('POST','GET'))) +
					(60 * 60 * 24 * 7 * $dateOffset));
		}
		else
		{
			$first = (int) ($this->bo->now_su +
					(60 * 60 * 24 * 7 * $dateOffset));
		}
		if ($arguments['useWeekStart'])
		{
			$start = Api\DateTime($first);
			$start->setWeekStart();
			$first = $start->format('ts');
		}

		$last = (int) ($first +
				(60 * 60 * 24 * 7 * $weeks));

		if ($arguments['showTitle'])
		{
			$html .= '<div id="divAppboxHeader">'.$GLOBALS['egw_info']['apps']['calendar']['title'].' - ';
			$html .= lang('After %1',$this->bo->long_date($first));
			$html .= "</div>";
		}

		// set the search parameters
		$search_params = Array
		(
			'offset' => $arguments['entryOffset'] ? (int) $arguments['entryOffset'] : false,
			'order' => 'cal_start ASC',
			'start' => $first,
			'end' => $last,
		);
		$search_string = trim($arguments['search']);
		if ($search_string != "")
		{
			$search_params['query'] = $search_string;
		}
		if (count($arguments['category']) > 0)
		{
			$search_params['cat_id'] = $arguments['category'];
		}
		if ((is_array($arguments['users'])) && (count($arguments['users']) > 0))
		{
			$search_params['users'] = $arguments['users'];
		}
		if ($arguments['numEntries'])
		{
			$search_params['num_rows'] = (int) $arguments['numEntries'];
			$search_params['offset'] =  $arguments['entryOffset'] ? (int) $arguments['entryOffset'] :0;
		}
		$rows = array();

		foreach((array) $this->bo->search($search_params) as $event)
		{
			$event['date'] = $this->bo->date2string($event['start']);
			if (empty($event['description'])) $event['description'] = ' ';	// no description screws the titles horz. alignment
			if (empty($event['location'])) $event['location'] = ' ';	// no location screws the owner horz. alignment
			$rows[] = $event;
		}
		if (($arguments['showWeeks']) && ((int)$arguments['offset'] == 0))
		{
			$html .= "<div>".lang('Next')." ".lang('%1 weeks', $weeks).":</div>\n";
		}
		if (($search_params['offset'] && $this->bo->total == 0) || count($rows)==0)
		{
			$html .= "<div>".lang("no events found")."</div>";
		}
		else
		{
			$event_count = 0;
			$last_week = 0;

			$html .= "\n<div>\n";
			$html .= '  <div class="cal_list_weektop"></div>'."\n";
			foreach ($rows as $event)
			{
				if (($last_week != 0) && (date('W-Y',$event['start']) != $last_week))
				{
					$html .= '  <div class="cal_list_weeksplit"></div>'."\n";
				}
				$last_week = date('W-Y',$event['start']);
				$html .= "  <!-- Event -->\n";
				if ($event_count % 2 == 0) {
					$html .= '  <div class="cal_list_event cal_event_even">'."\n";
				}
				else
				{
					$html .= '  <div class="cal_list_event cal_event_uneven">'."\n";
				}
				$html .= '    <div class="cal_list_title">'.$event['title']."</div>\n";
				$html .= '    <div class="cal_list_date">';
				$html .= '<span class="cal_list_start">';
				$html .= '<span class="cal_list_weekday">'.lang(date('D',$event['start'])).".".($this->bo->common_prefs['dateformat'][0] != 'd' ? ' ' : ', ')."</span>";
				$html .= $this->bo->format_date($event['start'])."</span>";
				$html .= '<span class="cal_list_end"> - ';
				$html .= '<span class="cal_list_weekday">'.lang(date('D',$event['end'])).".".($this->bo->common_prefs['dateformat'][0] != 'd' ? ' ' : ', ')."</span>";
				$html .= $this->bo->format_date($event['end'])."</span></div>\n";
				$descr = trim($event['description']);
				if (! empty($descr)) {
					$html .= "    <div class=\"cal_list_descr\">\n".preg_replace('/\\n/',"<br>\n",$event['description'])."</div>\n";
				}
				$html .= "  </div><!-- cal_list_event -->\n";
				$event_count ++;
			}
			$html .= '  <div class="cal_list_weekbottom"></div>'."\n";
			$html .= "<!-- End module -->\n";
			$html .= "</div>\n";
		}
		return $html;
	}
}