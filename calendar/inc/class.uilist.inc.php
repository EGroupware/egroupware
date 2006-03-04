<?php
/**************************************************************************\
* eGroupWare - Calendar - Listview and Search                             *
* http://www.egroupware.org                                                *
* Written and (c) 2005 by Ralf Becker <RalfBecker@outdoor-training.de>     *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

include_once(EGW_INCLUDE_ROOT . '/calendar/inc/class.uical.inc.php');

/**
 * Class to generate the calendar listview and the search
 *
 * The new UI, BO and SO classes have a strikt definition, in which time-zone they operate:
 *  UI only operates in user-time, so there have to be no conversation at all !!!
 *  BO's functions take and return user-time only (!), they convert internaly everything to servertime, because
 *  SO operates only on server-time
 *
 * The state of the UI elements is managed in the uical class, which all UI classes extend.
 *
 * All permanent debug messages of the calendar-code should done via the debug-message method of the bocal class !!!
 *
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2004/5 by RalfBecker-At-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
class uilist extends uical
{
	var $public_functions = array(
		'listview'  => True,
	);
	/**
	 * @var $debug mixed integer level or string function- or widget-name
	 */
	var $debug=false;

	/**
	 * Constructor
	 *
	 * @param array $set_states=null to manualy set / change one of the states, default NULL = use $_REQUEST
	 */
	function uilist($set_states=null)
	{
		$this->uical(true,$set_states);	// call the parent's constructor

		$GLOBALS['egw_info']['flags']['app_header'] = $GLOBALS['egw_info']['apps']['calendar']['title'].' - '.lang('Listview').
			// for a single owner we add it's name to the app-header
			(count(explode(',',$this->owner)) == 1 ? ': '.$this->bo->participant_name($this->owner) : '');
		
		$this->date_filters = array(
			'after'  => lang('After current date'),
			'before' => lang('Before current date'),
			'all'    => lang('All events'),
		);
		
		$this->check_owners_access();
	}
	
	/**
	 * Show the calendar on the home page
	 *
	 * @return string with content
	 */
	function &home()
	{
		// set the defaults for the home-page
		$this->uilist(array(
			'date'       => $this->bo->date2string($this->bo->now_su),
			'cat_id'     => 0,
			'filter'     => 'all',
			'owner'      => $this->user,
			'multiple'   => 0,
			'view'       => $this->bo->cal_prefs['defaultcalendar'],			
		));
		$GLOBALS['egw']->session->appsession('calendar_list','calendar','');	// in case there's already something set

		return $this->listview(null,'',true);
	}	
	
	/**
	 * Show the listview
	 */
	function listview($content=null,$msg='',$home=false)
	{
		if ($_GET['msg']) $msg .= $_GET['msg'];
		if ($this->group_warning) $msg .= $this->group_warning;

		$etpl =& CreateObject('etemplate.etemplate','calendar.list');

		if (is_array($content) && $content['nm']['rows']['delete'])
		{
			list($id) = each($content['nm']['rows']['delete']);

			if ($this->bo->delete($id))
			{
				$msg = lang('Event deleted');
			}
		}
		$content = array(
			'nm'  => $GLOBALS['egw']->session->appsession('calendar_list','calendar'),
			'msg' => $msg,
		);
		if (!is_array($content['nm']))
		{
			$content['nm'] = array(
				'get_rows'       =>	'calendar.uilist.get_rows',
	 			'filter_no_lang' => True,	// I  set no_lang for filter (=dont translate the options)
				'no_filter2'     => True,	// I  disable the 2. filter (params are the same as for filter)
				'no_cat'         => True,	// I  disable the cat-selectbox
//				'bottom_too'     => True,// I  show the nextmatch-line (arrows, filters, search, ...) again after the rows
				'filter'         => 'after',
				'order'          =>	'cal_start',// IO name of the column to sort after (optional for the sortheaders)
				'sort'           =>	'ASC',// IO direction of the sort: 'ASC' or 'DESC'
			);
		}
		if (isset($_REQUEST['keywords']))	// new search => set filters so every match is shown
		{
			$this->adjust_for_search($_REQUEST['keywords'],$content['nm']);
		}
		return $etpl->exec('calendar.uilist.listview',$content,array(
			'filter' => &$this->date_filters,
		),$readonlys,'',$home ? -1 : 0);
	}
	
	/**
	 * set filter for search, so that everything is shown
	 */
	function adjust_for_search($keywords,&$params)
	{
		$params['search'] = $keywords;
		$params['start']  = 0;
		$params['order'] = 'cal_start';
		if ($keywords)
		{
			$params['filter'] = 'all';
			$params['sort'] = 'DESC';
			unset($params['col_filter']['participant']);
		}
		else
		{
			$params['filter'] = 'after';
			$params['sort'] = 'ASC';
		}
	}

	/**
	 * query calendar for nextmatch in the listview
	 *
	 * @internal 
	 * @param array &$params parameters
	 * @param array &$rows returned rows/events
	 * @param array &$readonlys eg. to disable buttons based on acl
	 */
	function get_rows(&$params,&$rows,&$readonlys)
	{
		//echo "uilist::get_rows() params="; _debug_array($params);
		$old_params = $GLOBALS['egw']->session->appsession('calendar_list','calendar');
		if ($old_params['filter'] && $old_params['filter'] != $params['filter'])	// filter changed => order accordingly
		{
			$params['order'] = 'cal_start';
			$params['sort'] = $params['filter'] == 'after' ? 'ASC' : 'DESC';
		}
		if ($old_params['search'] != $params['search'])
		{
			$this->adjust_for_search($params['search'],$params);
		}
		$GLOBALS['egw']->session->appsession('calendar_list','calendar',$params);
		
		$search_params = array(
			'cat_id'  => $this->cat_id,
			'filter'  => $this->filter,
			'query'   => $params['search'],
			'offset'  => (int) $params['start'],
			'order'   => $params['order'] ? $params['order'].' '.$params['sort'] : 'cal_start',
		);
		switch($params['filter'])
		{
			case 'all':
				break;
			case 'before':
				$search_params['end'] = $this->date;
				break;
			case 'after':
			default:
				$search_params['start'] = $this->date;
				break;
		}
		if ((int) $params['col_filter']['participant'])
		{
			$search_params['users'] = (int) $params['col_filter']['participant'];
		}
		elseif(empty($params['search']))	// active search displays entries from all users
		{
			$search_params['users'] = explode(',',$this->owner);
		}
		$rows = array();
		foreach((array) $this->bo->search($search_params) as $event)
		{
			$readonlys['edit['.$event['id'].']'] = !$this->bo->check_perms(EGW_ACL_EDIT,$event);
			$readonlys['delete['.$event['id'].']'] = !$this->bo->check_perms(EGW_ACL_DELETE,$event);
			$readonlys['view['.$event['id'].']'] = !$this->bo->check_perms(EGW_ACL_READ,$event);

			$event['parts'] = implode(",\n",$this->bo->participants($event));
			$event['recure'] = $this->bo->recure2string($event);
			$event['date'] = $this->bo->date2string($event['start']);
			if (empty($event['description'])) $event['description'] = ' ';	// no description screws the titles horz. alignment
			if (empty($event['location'])) $event['location'] = ' ';	// no location screws the owner horz. alignment
			$rows[] = $event;
		}
		//_debug_array($rows);
		return $this->bo->total;
	}
}
