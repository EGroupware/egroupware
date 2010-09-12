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

/* $Id: class.module_calendar_month.inc.php,v 1.7 2008-12-30 15:38:33 hjtappe Exp $ */

class module_calendar_month extends Module
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
	/**
	 * Instance of the user interface object of calendar
	 *
	 * @var ui
	 */
	var $uiviews;
	/**
	 * Instance of the accounts object
	 *
	 * @var accounts
	 */
	var $accounts;
	/**
	 * Default CSS style
	 *
	 * @var default_css
	 */
	var $default_css = '/calendar/templates/default/app.css';

	function module_calendar_month()
	{
		$this->bo =& new calendar_bo();
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
				'default' => 2,
				'params' => array('size' => 1)
			),
			'showWeeks' => array(
				'type' => 'checkbox',
				'label' => lang('Should the number of weeks be shown on top of the calendar'),
				'default' => false,
			),
			'showTitle' => array(
				'type' => 'checkbox',
				'label' => lang('Show a calendar title'),
				'default' => false,
			),
			'search' => array(
				'type' => 'textfield',
				'label' => lang('Search string for the events'),
			),
			'users' => array(
				'type' => 'select',
				'options' => array(),
				'label' => lang('Group(s) or user(s) whose calendars to show (if ACL exists)'),
				// 'multiple' => true, is set in the get_user_interface function.
			),
			'grid' => array(
				'type' => 'checkbox',
				'label' => lang('Should the grid be shown in the calendar'),
				'default' => false,
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
		$cat = createobject('phpgwapi.categories','','calendar');
		$cats = $cat->return_array('all',0,False,'','cat_name','',True);
		$cat_ids = array();
		while (list(,$category) = @each($cats))
		{
			$cat_ids[$category['id']] = $GLOBALS['egw']->strip_html($category['name']);
		}
		$this->arguments['category']['options'] = $cat_ids;
		if (count($cat_ids) > 5) {
			$this->arguments['category']['multiple'] = 5;
		}

		if (! isset($GLOBALS['egw']->accounts))
		{
			$GLOBALS['egw']->accounts = new accounts();
		}
		$this->accounts =& $GLOBALS['egw']->accounts;
		$search_params=array(
			'type' => 'both',
			'app' => 'calendar',
		);
		$accounts = $this->accounts->search($search_params);
		$users = array();
		$groups = array();
		// sort users and groups separately.
		if (isset($GLOBALS['sitemgr_info']['anonymous_user']))
		{
			$anon_user = $this->accounts->name2id($GLOBALS['sitemgr_info']['anonymous_user'],'account_lid','u');
		}
		else
		{
			// sitemgr is not in global variables. Get it.
			/*
			 * Get possible sitemgr paths from the HTTP_REFERRER in order to unreveal the
			 * anonymous user for the correct site.
			 */
			$sitemgr_path = preg_replace('/^[^\/]+:\/\/[^\/]+\/([^\?]*)(\?.*)*$/',"/\${1}",$_SERVER['HTTP_REFERER']);
			// Remove the trailing file- / pathname if any
			$sitemgr_path = preg_replace('/[^\/]*$/', '', $sitemgr_path);
			// Add leading slash if it has been lost.
			if (strncmp('/', $sitemgr_path, 1) != 0)
			{
				$sitemgr_path = '/'.$sitemgr_path;
			}

			// Code adapted from sitemgr-site/index.php
			$site_urls = array();
			$site_urls[] = $sitemgr_path;
			$site_urls[] = ($_SERVER['HTTPS'] ? 'https://' : 'http://') . $_SERVER['SERVER_ADDR'] . $sitemgr_path;
			$site_urls[] = $site_url = ($_SERVER['HTTPS'] ? 'https://' : 'http://') . $_SERVER['SERVER_NAME'] . $sitemgr_path;

			$GLOBALS['egw']->db->select('egw_sitemgr_sites','anonymous_user,anonymous_passwd,site_id',
				array('site_url' => $site_urls),__LINE__,__FILE__,false,'','sitemgr');

			$GLOBALS['egw']->db->next_record();
			$anon_user = $this->accounts->name2id($GLOBALS['egw']->db->f('anonymous_user'),'account_lid','u');
		}

		$anon_groups = $this->accounts->memberships($anon_user,true);
		foreach ($accounts as $entry)
		{
			$is_group = false;
			$has_read_permissions = false;
			$acl =& new acl($entry['account_id']);
			$acl->read_repository();
			// get the rights for each account to check whether the anon user has read permissions.
			$rights = $acl->get_rights($anon_user,'calendar');
			// also add the anon user if it's his own calendar.
			if (($rights & EGW_ACL_READ) || ($entry['account_id'] == $anon_user))
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
					if (($rights & EGW_ACL_READ) || ($entry['account_id'] == $parent_group))
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
					$users[$entry['account_id']] = $GLOBALS['egw']->common->display_fullname($entry['account_lid'],$entry['account_firstname'],$entry['account_lastname']);
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
		$GLOBALS['egw']->translation->add_app('calendar');
		$this->ui =& new calendar_uiviews();
		$this->ui->allowEdit = false;
		$this->ui->use_time_grid = isset($arguments['grid']) ? $arguments['grid'] : false;

		$weeks = $arguments['numWeeks'] ? (int) $arguments['numWeeks'] : 2;

		if (($arguments['acceptDateParam']) && (get_var('date',array('POST','GET'))))
		{
			$start = (int) (strtotime(get_var('date',array('POST','GET'))) +
					(60 * 60 * 24 * 7 * $dateOffset));
		}
		else
		{
			$start = (int) ($this->bo->now_su +
					(60 * 60 * 24 * 7 * $dateOffset));
		}
		$first = $this->ui->datetime->get_weekday_start(
					adodb_date('Y',$start),
					adodb_date('m',$start),
					adodb_date('d',$start));
		$last = strtotime("+$weeks weeks",$first) - 1;

		if ($arguments['showTitle'])
		{
			$html .= '<div id="divAppboxHeader">'.$GLOBALS['egw_info']['apps']['calendar']['title'].' - '.lang('Weekview').": ";
			$html .= lang('After %1',$this->bo->long_date($first));
			$html .= "</div>";
		}

		// set the search parameters
		$search_params = Array
		(
			'offset' => false,
			'order' => 'cal_start ASC',
			'start' => $first,
			'end' => $last,
			'daywise' => true,
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
		$rows = $this->bo->search($search_params);
		if ($arguments['showWeeks'])
		{
			$html .= "<div>".lang('Next')." ".lang('%1 weeks', $weeks).":</div>\n";
		}
		$css_file = isset($arguments['css']) ? $arguments['css'] : $this->default_css;
		$html .= '<!-- BEGIN Calendar info -->'."\n";
		$html .= '<style type="text/css">'."\n";
		$html .= '<!--'."\n";
		$html .= '@import url('.$GLOBALS['egw_info']['server']['webserver_url'].$css_file.");\n";
		$html .= '-->'."\n";
		$html .= '</style>'."\n";
		$html .= '<!-- END Calendar info -->'."\n";
		unset($css_file);
		// we add DAY_s/2 to $this->first (using 12h), to deal with daylight saving changes
		for ($week_start = $first; $week_start < $last; $week_start = strtotime("+1 week",$week_start))
		{
			$week = array();
			for ($i = 0; $i < 7; ++$i)
			{
				$day_ymd = $this->bo->date2string($i ? strtotime("+$i days",$week_start) : $week_start);
				$week[$day_ymd] = array_shift($rows);
			}
			$week_view = array(
				'menuaction' => false,
				'date' => $this->bo->date2string($week_start),
			);
			$title = lang('Wk').' '.adodb_date('W',$week_start);
			if (!isset($GLOBALS['egw']->template))
			{
				$GLOBALS['egw']->template = new Template;
			}
			$html .= $this->ui->timeGridWidget($this->ui->tagWholeDayOnTop($week),$weeks == 2 ? 30 : 60,200,'',$title,0,$week_start+WEEK_s >= $last);
		}
		// Initialize Tooltips
		$html .= '<script language="JavaScript" type="text/javascript" src="'.$GLOBALS['egw_info']['server']['webserver_url'].'/phpgwapi/js/wz_tooltip/wz_tooltip.js"></script>'."\n";

		return $html;
	}
}
