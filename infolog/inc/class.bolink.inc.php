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
					'menuaction' => 'calendar.uicalendar.view'
				),
				'view_id' => 'cal_id'
			), /*
			'email' => array(
				'view' => array(
					'menuaction' => 'email.uimessage.message'
				),
				'view_id' => 'msgball[acctnum:folder:msgnum]'	// id is a tupple/array, fields separated by ':'
			), */
			'infolog' => array(
				'query' => 'infolog.boinfolog.link_query',
				'title' => 'infolog.boinfolog.link_title',
				'view' => array(
					'menuaction' => 'infolog.uiinfolog.get_list',
					'action' => 'sp'
				),
				'view_id' => 'info_id',
			)
		);

		function bolink( )
		{
			$this->solink( );							// call constructor of derived class
			$this->public_functions += array(	// extend the public_functions of solink
				'query' => True,
				'title' => True,
				'view'  => True
			);
		}

		/*!
		@function link
		@syntax link(  $app1,$id1,$app2,$id2='',$remark='',$user=0  )
		@author ralfbecker
		@abstract creats a link between $app1,$id1 and $app2,$id2 - $id1 does NOT need to exist yet
		@param $app1 app of $id1
		@param $id1 id of item to linkto or 0 if item not yet created or array with links of not created item
		@param $app2 app of 2.linkend or array with links ($id2 not used)
		@param $remark Remark to be saved with the link (defaults to '')
		@param $owner Owner of the link (defaults to user)
		@discussion Does NOT check if link already exists
		@result db-errno or -1 (for param-error) or 0 for success
		@result if $id1==0 or already an array: $id1 is array with links
		*/
		function link( $app1,&$id1,$app2,$id2='',$remark='',$owner=0 )
		{
			if ($this->debug)
			{
				echo "<p>bolink.link('$app1',$id1,'$app2',$id2,'$remark',$owner)</p>\n";
			}
			if (!$app1 || !$app2 || !$id1 && isarray($id2) || $app1 == $app2 && $id1 == $id2)
			{
				return -1;
			}
			if (is_array($id1) || !$id1)		// create link only in $id1 array
			{
				if (!is_array($id1))
				{
					$id1 = array( );
				}
				$id1["$app2:$id2"] = array(
					'app' => $app2,
					'id'  => $id2,
					'remark' => $remark,
					'owner'  => $owner,
					'link_id' => "$app2:$id2"
				);
				return 0;
			}
			if (is_array($app2) && !$id2)
			{
				reset($app2);
				$err = 0;
				while (!$err && list(,$link) = each($app2))
				{
					$err = solink::link($app1,$id1,$link['app'],$link['id'],$link['remark'],$link['owner']);
				}
				return $err;
			}
			return solink::link($app1,$id1,$app2,$id2,$remark,$owner);
		}

		/*!
		@function get_links
		@syntax get_links(  $app,$id,$only_app='',$only_name='',$order='link_lastmod DESC'  )
		@author ralfbecker
		@abstract returns array of links to $app,$id (reimplemented to deal with not yet created items)
		@param $id id of entry in $app or array of links if entry not yet created
		@param $only_app if set return only links from $only_app (eg. only addressbook-entries) or NOT from if $only_app[0]=='!'
		@param $order defaults to newest links first
		@result array of links or empty array if no matching links found
		*/
		function get_links( $app,$id,$only_app='',$order='link_lastmod DESC' )
		{
			if (is_array($id) || !$id)
			{
				$ids = array();
				if (is_array($id))
				{
					if ($not_only = $only_app[0])
					{
						$only_app = substr(1,$only_app);
					}
					reset($id);
					while (list($key,$link) = each($id))
					{
						if ($only_app && $not_only == ($link['app'] == $only_app))
						{
							continue;
						}
						$ids[$key] = $link;
					}
				}
				return $ids;
			}
			return solink::get_links($app,$id,$only_app,$order);
		}

		/*!
      @function unlink
      @syntax unlink( $link_id,$app='',$id='',$owner='' )
      @author ralfbecker
		@abstract Remove link with $link_id or all links matching given $app,$id
		@param $link_id link-id to remove if > 0
		@param $app,$id,$owner if $link_id <= 0: removes all links matching the non-empty params
		@discussion Note: if $link_id != '' and $id is an array: unlink removes links from that array only
		@discussion       unlink has to be called with &$id so see the result !!!
		@result the number of links deleted
		*/
		function unlink($link_id,$app='',$id='',$owner='')
		{
			if ($link_id > 0 || !is_array($id))
			{
				return solink::unlink($link_id,$app,$id,$owner);
			}
			$result = isset($id[$link_id]);

			unset($id[$link_id]);

			return $result;
		}

		/*!
		@function app_list
		@syntax app_list(   )
		@author ralfbecker
		@abstrac get list/array of link-aware apps
		@result array( $app => lang($app), ... )
		*/
		function app_list( )
		{
			reset ($this->app_register);
			$apps = array();
			while (list($app,$reg) = each($this->app_register))
			{
				$apps[$app] = lang($app);
			}
			return $apps;
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
			if ($app == '' || !is_array($reg = $this->app_register[$app]) || !isset($reg['query']))
			{
				return array();
			}
			$method = $reg['query'];

			if ($this->debug)
			{
				echo "<p>bolink.query('$app','$pattern') => '$method'</p>\n";
			}
			return strchr($method,'.') ? ExecMethod($method,$pattern) : $this->$method($pattern);
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
			if ($app == '' || !is_array($reg = $this->app_register[$app]) || !isset($reg['title']))
			{
				return array();
			}
			$method = $reg['title'];

			return strchr($method,'.') ? ExecMethod($method,$id) : $this->$method($id);
		}

		/*!
		@function view
		@syntax view( $app,$id )
		@author ralfbecker
		@abstract view entry $id of $app
		@result array with name-value pairs for link to view-methode of $app to view $id
		*/
		function view($app,$id)
		{
			if ($app == '' || !is_array($reg = $this->app_register[$app]) || !isset($reg['view']) || !isset($reg['view_id']))
			{
				return array();
			}
			$view = $reg['view'];

			$names = explode(':',$reg['view_id']);
			if (count($names) > 1)
			{
				$id = explode(':',$id);
				while (list($n,$name) = each($names))
				{
					$view[$name] = $id[$n];
				}
			}
			else
			{
				$view[$reg['view_id']] = $id;
			}
			return $view;
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
			$event_ids = $this->bocal->search_keywords($pattern);

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
			$addrs = $this->contacts->read( 0,0,'',$pattern,'','DESC','org_name,n_family,n_given' );
			$content = array( );
			while ($addrs && list( $key,$addr ) = each( $addrs ))
			{
				$content[$addr['id']] = $this->addressbook_title( $addr );
			}
			return $content;
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
				$proj = $this->boprojects->read_single_project( $proj );
			}
			return $proj['title'];
		}

		/*!
		@function projects_query
		@syntax projects_query(  $pattern  )
		@author ralfbecker
		@abstract query for projects matching $pattern, should be moved to boprojects.link_query
		*/
		function projects_query( $pattern )
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




