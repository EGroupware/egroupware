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
			'owner' => array(
				'type' => 'select',
				'label' => lang('Group(s) or user(s) to show'),
				'options' => array(),
			),
			'resources' => array(
				'type' => 'select',
				'label' => lang('Resources'),
				'options' => array(),
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
	 * Reimplemented to fetch the cats, users/groups and resources
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

		if (!isset($GLOBALS['egw']->accounts))
		{
			$GLOBALS['egw']->accounts = new accounts();
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
			$acl = new acl($entry['account_id']);
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
		$this->arguments['owner']['options'] = $groups + $users;
		if (count($this->arguments['owner']['options']) > 10)
		{
			$this->arguments['owner']['multiple'] = 10;
		}
		else if (count($this->arguments['owner']['options']) > 0)
		{
			$this->arguments['owner']['multiple'] = true;
		}

		$calendar_bo = new calendar_bo();
 		foreach ($calendar_bo->resources as $resource)
		{
			if(!is_array($resource['cal_sidebox'])) continue;
			$this->arguments['resources']['options'] = array_merge($this->arguments['resources']['options'],
				ExecMethod($resource['cal_sidebox']['file'],array(
					'menuaction' => $_GET['menuaction'],
					'block_id' => $_GET['block_id'],
					'return_array' => true,
				)));
		}
		$this->arguments['resources']['multiple'] = count($this->arguments['resources']['options']) ? 4 : 0;

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

		//_debug_array($arguments);
		$arguments['view'] = 'planner';
		if (empty($arguments['date']))
		{
			$arguments['date'] = date('Ymd');
		}
		if ($arguments['sortby'] == 'yearly')
		{
			$arguments['sortby'] = 'month';
			$arguments['date'] = substr($arguments['date'],0,4).'0101';
		}
		if (isset($_GET['date'])) $arguments['date'] = $_GET['date'];
		if (empty($arguments['cat_id'])) $arguments['cat_id'] = 0;
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
		$html .= '</style>'."\n";

		if (is_array($params['owner']))
		{
			$params['owner'] = implode(',', $params['owner']);

			$uiviews = new calendar_uiviews($params);
			$uiviews->allowEdit = false;	// switches off all edit popups

			// Initialize Tooltips
			static $wz_tooltips;
			if (!$wz_tooltips++) $html .= '<script language="JavaScript" type="text/javascript" src="'.$GLOBALS['egw_info']['server']['webserver_url'].'/phpgwapi/js/wz_tooltip/wz_tooltip.js"></script>'."\n";

			// replacing egw-urls with sitemgr ones, allows to use navigation links
			$html .= str_replace($GLOBALS['egw_info']['server']['webserver_url'].'/index.php?',
				$this->link().'&',
				$uiviews->planner(true));
		}
		else
		{
			$html .= '<div class="redItalic" align="center">'.lang('No owner selected').'</div>';
		}

		return $html;
	}
}
