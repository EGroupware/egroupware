<?php

/*
 * Egroupware - Addressbook - A portlet for displaying a list of entries
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package timesheet
 * @subpackage home
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

/**
 * The timesheet_list_portlet uses a nextmatch / favorite
 * to display a list of entries.
 */
class timesheet_favorite_portlet extends home_favorite_portlet
{

	/**
	 * Construct the portlet
	 *
	 */
	public function __construct(Array &$context = array(), &$need_reload = false)
	{
		$context['appname'] = 'timesheet';
		
		// Let parent handle the basic stuff
		parent::__construct($context,$need_reload);

		$ui = new timesheet_ui();

		$this->context['template'] = 'timesheet.index.rows';
		$this->nm_settings += array(
			'get_rows'	=> 'timesheet_favorite_portlet::get_rows',
			// Use a different template so it can be accessed from client side
			'template'	=> 'timesheet.index.rows',
			// Use a reduced column set for home, user can change if needed
			'default_cols'   => 'ts_start,ts_project_pm_id_linked_ts_title,ts_duration_duration',
			'row_id'         => 'ts_id',
			'row_modified'   => 'ts_modified',
		);
	}

	public function exec($id = null, etemplate_new &$etemplate = null)
	{
		$ui = new timesheet_ui();

		$date_filters = array('All');
		foreach(array_keys($ui->date_filters) as $name)
		{
			$date_filters[$name] = $name;
		}
		$date_filters['custom'] = 'custom';
		$this->context['sel_options']['filter'] = $date_filters;
		$this->context['sel_options']['filter2'] = array('No details','Details');
		$read_grants = $ui->grant_list(EGW_ACL_READ);
		$this->context['sel_options'] += array(
			'ts_owner'   => $read_grants,
			'pm_id'      => array(lang('No project')),
			'cat_id'     => array(array('value' => '', 'label' => lang('all')), array('value' => 0, 'label'=>lang('None'))),
			'ts_status'  => $ui->status_labels+array(lang('No status')),
		);
		$this->nm_settings['actions'] = $ui->get_actions($this->nm_settings);

		parent::exec($id, $etemplate);
	}

	/**
	 * Override from timesheet to clear the app header
	 *
	 * @param type $query
	 * @param type $rows
	 * @param type $readonlys
	 * @return integer Total rows found
	 */
	public static function get_rows(&$query, &$rows, &$readonlys)
	{
		$ui = new timesheet_ui();
		$total = $ui->get_rows($query, $rows, $readonlys);
		unset($GLOBALS['egw_info']['flags']['app_header']);
		return $total;
	}

	/**
	 * Here we need to handle any incoming data.  Setup is done in the constructor,
	 * output is handled by parent.
	 *
	 * @param type $id
	 * @param etemplate_new $etemplate
	 */
	public static function process($content = array())
	{
		parent::process($content);
		$ui = new timesheet_ui();

		// This is just copy+pasted from timesheet_ui line 816, but we don't want
		// the etemplate exec to fire again.
		if (is_array($content) && isset($content['nm']['rows']['document']))  // handle insert in default document button like an action
		{
			list($id) = @each($content['nm']['rows']['document']);
			$content['nm']['action'] = 'document';
			$content['nm']['selected'] = array($id);
		}
		if ($content['nm']['action'])
		{
			// remove sum-* rows from checked rows
			$content['nm']['selected'] = array_filter($content['nm']['selected'], function($id)
			{
				return $id > 0;
			});
			if (!count($content['nm']['selected']) && !$content['nm']['select_all'])
			{
				$msg = lang('You need to select some entries first!');
				egw_json_response::get()->apply('egw.message',array($msg,'error'));
			}
			else
			{
				$success = $failed = $action_msg = null;
				if ($ui->action($content['nm']['action'],$content['nm']['selected'],$content['nm']['select_all'],
					$success,$failed,$action_msg,'index',$msg))
				{
					$msg .= lang('%1 timesheets(s) %2',$success,$action_msg);

					egw_json_response::get()->apply('egw.message',array($msg,'success'));
					foreach($content['nm']['selected'] as &$id)
					{
						$id = 'timesheet::'.$id;
					}
					// Directly request an update - this will get timesheet tab too
					egw_json_response::get()->apply('egw.dataRefreshUIDs',array($content['nm']['selected']));
				}
				elseif(empty($msg))
				{
					$msg .= lang('%1 timesheets(s) %2, %3 failed because of insufficent rights !!!',$success,$action_msg,$failed);
				}
				egw_json_response::get()->apply('egw.message',array($msg,'error'));
			}
		}

	}
 }