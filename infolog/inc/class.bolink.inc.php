<?php
	/**************************************************************************\
	* phpGroupWare - InfoLog Links                                             *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	if(!isset($GLOBALS['phpgw_info']['flags']['included_classes']['solink']))
	{
		include(PHPGW_API_INC . '/../../infolog/inc/class.solink.inc.php');
		$GLOBALS['phpgw_info']['flags']['included_classes']['solink'] = True;
	}

	/*!
	@class bolink
	@author ralfbecker
	@abstract generalized linking between entries of phpGroupware apps - BO layer
	@discussion This class is the BO-layer of the links
	@discussion Links have to ends each pointing to an entry, an entry is a double:
	@discussion app   app-name or directory-name of an phpgw application, eg. 'infolog'
	@discussion id    this is the id, eg. an integer or a tupple like '0:INBOX:1234'
	*/
	class bolink extends solink
	{
		var $app_register = array(		// this should be setup/extended by setup
			'infolog' => array(
				'query' => 'infolog.boinfolog.link_query',
				'title' => 'infolog.boinfolog.link_title',
				'view' => array(
					'menuaction' => 'infolog.uiinfolog.get_list',
					'action' => 'sp'
				),
				'view_id' => 'info_id',
			),
			'addressbook' => array(
				'query' => 'addressbook_query',
				'title' => 'addressbook_title',
				'view' => array(
					'menuaction' => 'addressbook.uiaddressbook.view'
				),
				'view_id' => 'ab_id'
			),
			'projects' => array(
				'query' => 'projects_query',
				'title' => 'projects_title',
				'view' => array (
					'menuaction' => 'projects.uiprocject.view_project'
				),
				'view_id' => 'project_id'
			),
			'calendar' => array(
				'query' => 'calendar_query',
				'title' => 'calendar_title',
				'view' => array (
					'menuaction' => 'calendar.uicalendar.view
				),
				'view_id' => 'cal_id'
			) /*,
			'email' => array(
				'view' => array(
					'menuaction' => 'email.uimessage.message'
				),
				'view_id' => 'msgball[acctnum:folder:msgnum]'	// id is a tupple/array, fields separated by ':'
			) */
		);

		function bolink( )
		{
			solink( );									// call constructor of derived class
			$this->public_functions += array(	// extend the public_functions of solink
				'query'      => True,
				'title' => True,
			);
		}

		function check_method($method,&$class,&$func)
		{
			// Idea: check if method exist and cache the class 
		}

		/*!
		@function query
		@syntax query( $app,$pattern )
		@author ralfbecker
		@abstract Searches for a $pattern in the entries of $app
		@result an array of $id => $title pairs
		*/
		function query($app,$pattern)
		{
			if ($app == '' || !is_array($reg = $this->app_register[$app]) || !is_set[$reg['query']])
			{
				return array();
			}
			$method = $reg['query'];

			return strchr('.',$method) ? ExecuteMethod($method,$pattern) : $this->$method($pattern);
		}

		/*!
		@function title
		@syntax title( $app,$id )
		@author ralfbecker
		@abstract returns the title (short description) of entry $id and $app
		@result the title
		*/
		function title($app,$id)
		{
			if ($app == '' || !is_array($reg = $this->app_register[$app]) || !is_set[$reg['title']])
			{
				return array();
			}
			$method = $reg['title'];

			return strchr('.',$method) ? ExecuteMethod($method,$id) : $this->$method($id);
		}

		/*!
		@function calendar_title
		@syntax calendar_title(  $id  )
		@author ralfbecker
		@abstract get title for an event, should be moved to bocalendar.link_title
		*/
		function calendar_title( $event )
		{
			if (!is_object($this->bocal))
			{
				$this->bocal = createobject('calendar.bocalendar');
			}
			if (!is_array($event) && (int) $event > 0)
			{
				$event = $this->bocal->read_entry($event);
			}
			if (!is_array($event))
			{
				return 'not an event !!!';
			}
			$name = $GLOBALS['phpgw']->common->show_date($this->bocal->maketime($event['start']) - $this->bocal->datetime->tz_offset);
			$name .= ' -- ' . $GLOBALS['phpgw']->common->show_date($this->bocal->maketime($event['end']) - $this->bocal->datetime->tz_offset);
			$name .= ': ' . $event['title'];

			return $GLOBALS['phpgw']->strip_html($name);
		}

		/*!
		@function calendar_query
		@syntax calendar_query(  $pattern  )
		@author ralfbecker
		@abstract query calendar for an event $matching pattern, should be moved to bocalendar.link_query
		*/
		function calendar_query($pattern)
		{
			if (!is_object($this->bocal))
			{
				$this->bocal = createobject('calendar.bocalendar');
			}
			$event_ids = $this->bocal->search_keywords($query_name);

			$content = array( );
			while (is_array($event_ids) && list( $key,$id ) = each( $event_ids ))
			{
				$content[$id] = $this->calendar_title( $id );
			}
			return $content;
		}

		/*!
		@function addressbook_title
		@syntax addressbook_title(  $id  )
		@author ralfbecker
		@abstract get title for an address, should be moved to boaddressbook.link_title
		*/
		function addressbook_title( $addr )
		{
			if (!is_object($this->contacts))
			{
				$this->contacts = createobject('phpgwapi.contacts');
			}
			if (!is_array($addr))
			{
				list( $addr ) = $this->contacts->read_single_entry( $addr );
			}
			$name = $addr['n_family'];
			if ($addr['n_given'])
			{
				$name .= ', '.$addr['n_given'];
			}
			else
			{
				if ($addr['n_prefix'])
				{
					$name .= ', '.$addr['n_prefix'];
				}
			}
			if ($addr['org_name'])
			{
				$name = $addr['org_name'].': '.$name;
			}
			return $GLOBALS['phpgw']->strip_html($name);
		}

		/*!
		@function addressbook_query
		@syntax addressbook_query(  $pattern  )
		@author ralfbecker
		@abstract query addressbook for $pattern, should be moved to boaddressbook.link_query
		*/
		function addressbook_query( $pattern )
		{
			if (!is_object($this->contacts))
			{
				$this->contacts = createobject('phpgwapi.contacts');
			}
			$addrs = $contacts->read( 0,0,'',$query_name,'','DESC','org_name,n_family,n_given' );
			$content = array( );
			while ($addrs && list( $key,$addr ) = each( $addrs ))
			{
				$content[$addr['id']] = $this->addressbook_title( $addr );
			}
		}

		/*!
		@function projects_title
		@syntax projects_title(  $id  )
		@author ralfbecker
		@abstract get title for a project, should be moved to boprojects.link_title
		*/
		function projects_title( $proj )
		{
			if (!is_object($this->boprojects))
			{
				if (!file_exists(PHPGW_SERVER_ROOT.'/projects'))	// check if projects installed
					return '';
				$this->boprojects = createobject('projects.boprojects');
			}
			if (!is_array($proj))
			{
				$proj = $this->boprojects->read_single_project( $proj ))
			}
			return $proj['title'];
		}

		/*!
		@function projects_query
		@syntax projects_query(  $pattern  )
		@author ralfbecker
		@abstract query for projects matching $pattern, should be moved to boprojects.link_query
		*/
		function projects_title( $pattern )
		{
			if (!is_object($this->boprojects))
			{
				if (!file_exists(PHPGW_SERVER_ROOT.'/projects'))	// check if projects installed
					return array();
				$this->boprojects = createobject('projects.boprojects');
			}
			$projs = $this->boprojects->list_projects( 0,0,$pattern,'','','','',0,'mains','' );
			$content = array();
			while ($projs && list( $key,$proj ) = each( $projs ))
			{
				$content[$proj['project_id']] = $this->projects_title($proj);
			}
			return $content;
		}
	}




