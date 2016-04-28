<?php
/**
 * Egroupware - Calendar - A portlet for displaying a list of entries
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package calendar
 * @subpackage home
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Etemplate;

/**
 * The addressbook_list_portlet uses a nextmatch / favorite
 * to display a list of entries.
 */
class calendar_favorite_portlet extends home_favorite_portlet
{

	/**
	 * Construct the portlet
	 * Calendar complicates things...
	 */
	public function __construct(Array &$context = array(), &$need_reload = false)
	{
		$context['appname'] = 'calendar';
		// Reload is NULL when changing properties via AJAX
		$reload = !is_null($need_reload);

		// Let parent handle the basic stuff
		parent::__construct($context,$need_reload);

		if($this->favorite['state']['view'] == 'listview')
		{
			$this->context['template'] = 'calendar.list.rows';
			$this->context['sel_options'] = array();
			$this->nm_settings += array(
				'csv_export'      => True,
				'filter_no_lang'  => True,	// I  set no_lang for filter (=dont translate the options)
				'no_filter2'      => True,	// I  disable the 2. filter (params are the same as for filter)
				'no_cat'          => True,	// I  disable the cat-selectbox
				'filter'          => 'after',
				'row_id'          => 'row_id',	// set in get rows "$event[id]:$event[recur_date]"
				'row_modified'    => 'modified',
				'get_rows'	=> 'calendar_favorite_portlet::get_rows',
				// Use a different template so it can be accessed from client side
				'template'	=> 'calendar.list.rows',
				// Default to fewer columns
				'default_cols'	=> 'cal_start_cal_end,cal_title'
			);
		}

		// Checking against NULL allows us to skip the reload for resizing
		$need_reload = $reload && $need_reload;
	}

	public function exec($id = null, Etemplate &$etemplate = null)
	{

		// Always load app's javascript, so most actions have a chance of working
		Framework::includeJS('.','app',$this->context['appname']);

		// Always load app's css
		Framework::includeCSS('calendar', 'app-'.$GLOBALS['egw_info']['user']['preferences']['common']['theme']) ||
			Framework::includeCSS('calendar','app');

		if($this->favorite['state']['view'] == 'listview' || is_array($this->favorite) && !$this->favorite['state']['view'])
		{
			$ui = new calendar_uilist();
		}
		else
		{
			$ui = new calendar_uiviews();
			if ($this->favorite)
			{
				if($this->favorite['state']['start']) $ui->search_params['start'] = $this->favorite['state']['start'];
				if($this->favorite['state']['cat_id']) $ui->search_params['cat_id'] = $this->favorite['state']['cat_id'];
				// Owner can be 0 for current user
				if(array_key_exists('owner',$this->favorite['state'])) $ui->search_params['users'] = $this->favorite['state']['owner'];
				if($ui->search_params['users'] && !is_array($ui->search_params['users']))
				{
					$ui->search_params['users'] = explode(',',$ui->search_params['users']);
				}
				if($this->favorite['state']['filter']) $ui->search_params['filter'] = $this->favorite['state']['filter'];
				if($this->favorite['state']['sortby']) $ui->search_params['sortby'] = $this->favorite['state']['sortby'];
				$ui->search_params['weekend'] = $this->favorite['state']['weekend'];
			}
			$etemplate->read('home.legacy');

			$etemplate->set_dom_id($id);
		}

		$content = array('legacy' => '');

		switch($this->favorite['state']['view'])
		{
			case 'listview':
				$this->context['sel_options']['filter'] = &$ui->date_filters;
				$this->nm_settings['actions'] = $ui->get_actions($this->nm_settings['col_filter']['tid'], $this->nm_settings['org_view']);
				// Early exit
				return parent::exec($id, $etemplate);

			case 'planner_user':
			case 'planner_cat':
			case 'planner':
				$content = array();
				$etemplate->read('calendar.planner');
				$etemplate->set_dom_id($id);
				$this->actions =& $etemplate->getElementAttribute('planner', 'actions');
				// Don't notify the calendar app of date changes
				$etemplate->setElementAttribute('planner','onchange',false);
				$ui->planner_view = $this->favorite['state']['planner_view'];
				$ui->planner(array(), $etemplate);
				return;
			case 'month':
			case 'weekN':
				$etemplate->read('calendar.view');
				$etemplate->set_dom_id($id);
				$this->actions =& $etemplate->getElementAttribute('view', 'actions');

				$ui->month($this->favorite['state']['view'] == 'month' ?
					0 :
					(int)$ui->cal_prefs['multiple_weeks'],
					$etemplate
				);
				return;
			case 'week':
				$etemplate->read('calendar.view');
				$etemplate->set_dom_id($id);
				$this->actions =& $etemplate->getElementAttribute('view', 'actions');
				// Don't notify the calendar app of date changes
				$etemplate->setElementAttribute('view[0]','onchange',false);
				$ui->week(array(), $etemplate);
				return;
			case 'day':
			case 'day4':
				$etemplate->read('calendar.view');
				$etemplate->set_dom_id($id);
				$days = $this->favorite['state']['days'] ? $this->favorite['state']['days'] : (
					$this->favorite['state']['view'] == 'day' ? 1 : 4
				);
				$this->actions =& $etemplate->getElementAttribute('view', 'actions');
				$ui->week($days, $etemplate);
				return;
		}

		unset($GLOBALS['egw_info']['flags']['app_header']);
		// Force loading of CSS
		Framework::include_css_js_response();

		// Set this to calendar so app.js gets initialized
		$old_app = $GLOBALS['egw_info']['flags']['currentapp'];
		$GLOBALS['egw_info']['flags']['currentapp'] = 'calendar';

		$etemplate->exec(get_called_class() .'::process',$content);
		$GLOBALS['egw_info']['flags']['currentapp'] = $old_app;
	}

	/**
	 * Override from calendar list to clear the app header
	 *
	 * @param type $query
	 * @param type $rows
	 * @param type $readonlys
	 * @return integer Total rows found
	 */
	public static function get_rows(&$query, &$rows, &$readonlys)
	{
		$ui = new calendar_uilist();
		$old_owner = $ui->owner;
		$ui->owner = $query['owner'];
		$total = $ui->get_rows($query, $rows, $readonlys);
		$ui->owner = $old_owner;
		unset($GLOBALS['egw_info']['flags']['app_header']);
		unset($query['selectcols']);
		return $total;
	}

	/**
	 * Here we need to handle any incoming data.  Setup is done in the constructor,
	 * output is handled by parent.
	 *
	 * @param $values =array()
	 */
	public static function process($values = array())
	{
		parent::process($values);
		$ui = new calendar_uilist();
		if (is_array($values) && !empty($values['nm']['action']))
		{
			if (!count($values['nm']['selected']) && !$values['nm']['select_all'])
			{
				Framework::message(lang('You need to select some entries first'));
			}
			else
			{
				$success = $failed = $action_msg = $msg = null;
				if ($ui->action($values['nm']['action'],$values['nm']['selected'],$values['nm']['select_all'],
						$success,$failed,$action_msg,'calendar_list',$msg, $values['nm']['checkboxes']['no_notifications']))
				{
					$msg .= lang('%1 event(s) %2',$success,$action_msg);
					Api\Json\Response::get()->apply('egw.message',array($msg,'success'));
					foreach($values['nm']['selected'] as &$id)
					{
						$id = 'calendar::'.$id;
					}
					// Directly request an update - this will get addressbook tab too
					Api\Json\Response::get()->apply('egw.dataRefreshUIDs',array($values['nm']['selected']));
				}
				elseif(is_null($msg))
				{
					$msg .= lang('%1 entries %2, %3 failed because of insufficent rights !!!',$success,$action_msg,$failed);
					Api\Json\Response::get()->apply('egw.message',array($msg,'error'));
				}
				elseif($msg)
				{
					$msg .= "\n".lang('%1 entries %2, %3 failed.',$success,$action_msg,$failed);
					Api\Json\Response::get()->apply('egw.message',array($msg,'error'));
				}
				unset($values['nm']['action']);
				unset($values['nm']['select_all']);
			}
		}
	}

	/**
	 * No filters default favorite causes problems with calendar's special state handling,
	 * so just remove it.
	 * @return type
	 */
	public function get_properties()
	{
		$properties = parent::get_properties();
		foreach($properties as &$property)
		{
			if($property['name'] == 'favorite')
			{
				unset($property['select_options']['blank']);
				break;
			}
		}
		return $properties;
	}

	public function get_actions() {
		if($this->favorite['state']['view'] == 'listview' || !$this->actions)
		{
			return array();
		}
		else
		{
			return $this->actions;
		}
	}
}
