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

use EGroupware\Api;
use EGroupware\Api\Link;
use EGroupware\Api\Framework;
use EGroupware\Api\Acl;
use EGroupware\Api\Etemplate;

/**
 * Calendar planner block for sitemgr
 */
class module_calendar_planner extends Module
{
	/**
	 * Default calendar CSS file
	 */
	const CALENDAR_CSS = '/calendar/templates/default/app.css';

	const ETEMPLATE_CSS = '/api/templates/default/etemplate2.css';

	/**
	 * Constructor
	 */
	function __construct()
	{
		parent::__construct();

		$this->arguments = array(
			'sortby' => array(
				'type' => 'select',
				'label' => lang('Type of planner'),
				'options' => array(
					0 => lang('Planner by category'),
					'user' => lang('Planner by user'),
					'month' => lang('Yearly Planner')
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
		$cats = new Api\Categories('','calendar');
		foreach($cats->return_array('all',0,False,'','cat_name','',True) as $cat)
		{
			$this->arguments['cat_id']['options'][$cat['id']] = str_repeat('&nbsp; ',$cat['level']).$cat['name'];
		}
		if (count($this->arguments['cat_id']['options']) > 5)
		{
			$this->arguments['cat_id']['multiple'] = 5;
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
		$this->arguments['resources']['options'] = array_unique($this->arguments['resources']['options']);
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
		$GLOBALS['egw_info']['flags']['currentapp'] = 'calendar';

		//error_log(array2string($arguments));
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
		$html .= '.popupMainDiv #calendar-planner { position: static;}
		#calendar-planner .calendar_plannerWidget, #calendar-planner div.calendar_plannerRows {
    height: auto !important;
}
	</style>'."\n";
		$html .= Api\Html::image('sitemgr', 'left', lang('Previous'), 'onclick=\'app.calendar.toolbar_action({id:"previous"});\'')
		. Api\Html::image('sitemgr', 'right', lang('Next'), 'style="float: right;" onclick=\'app.calendar.toolbar_action({id:"next"});\'');

		if (is_array($params['owner']))
		{
			// Buffer, and add anything that gets cleared to the content
			ob_start(function($buffer) use(&$html) {
				$html .= $buffer;
				return '';
			});
			Framework::$header_done = true;
			$ui = new calendar_uiviews();
			$ui->sortby = $arguments['sortby'];
			$ui->owner = $params['owner'];

			if (!$ui->planner_view || $ui->planner_view == 'month')	// planner monthview
			{
				if ($ui->day < 15)	// show one complete month
				{
					$ui->_week_align_month($ui->first,$ui->last);
				}
				else	// show 2 half month
				{
					$ui->_week_align_month($ui->first,$ui->last,15);
				}
			}
			elseif ($ui->planner_view == 'week' || $ui->planner_view == 'weekN')	// weeekview
			{
				$start = new Api\DateTime($ui->date);
				$start->setWeekstart();
				$ui->first = $start->format('ts');
				$ui->last = $ui->bo->date2array($this->first);
				$ui->last['day'] += ($ui->planner_view == 'week' ? 7 : 7 * $ui->cal_prefs['multiple_weeks'])-1;
				$ui->last['hour'] = 23; $ui->last['minute'] = $ui->last['sec'] = 59;
				unset($ui->last['raw']);
				$ui->last = $ui->bo->date2ts($ui->last);
			}
			else // dayview
			{
				$ui->first = $ui->bo->date2ts($ui->date);
				$ui->last = $ui->bo->date2array($ui->first);
				$ui->last['day'] += 0;
				$ui->last['hour'] = 23; $ui->last['minute'] = $ui->last['sec'] = 59;
				unset($ui->last['raw']);
				$ui->last = $ui->bo->date2ts($ui->last);
			}

			$search_params = $ui->search_params;
			$search_params['daywise'] = false;
			$search_params['start'] = $ui->first;
			$search_params['end'] = $ui->last;
			$search_params['owner'] = $ui->owner;
			$search_params['enum_groups'] = $ui->sortby == 'user';

			$content = array();
			$sel_options = array();
			$content['planner'] = $ui->bo->search($search_params);
			foreach($content['planner'] as &$event)
			{
				$ui->to_client($event);
			}

			$tmpl = new Etemplate('calendar.planner');

			$tmpl->setElementAttribute('planner','start_date', Api\DateTime::to($ui->first, Api\DateTime::ET2));
			$tmpl->setElementAttribute('planner','end_date', Api\DateTime::to($ui->last, Api\DateTime::ET2));
			$tmpl->setElementAttribute('planner','owner', $search_params['owner']);
			$tmpl->setElementAttribute('planner','group_by', $ui->sortby);

			// Make sure all used owners are there, faking
			// calendar_owner_etemplate_widget::beforeSendToClient() since the
			// rest of the calendar app is probably missing.
			foreach($search_params['owner'] as $owner)
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
			. '(function() {jQuery("#calendar-planner").on("load",function() {'
			. 'app.calendar.update_state(' . json_encode(array(
					'view'   => 'planner',
					'planner_view' => 'month',
					'date'   => Api\DateTime::to($ui->first, Api\DateTime::ET2),
					'owner'  => $search_params['owner'],
					'sortby' => $ui->sortby,
					'filter' => $arguments['filter']
				)).');'
			. '});})();'
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
