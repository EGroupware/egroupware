<?php
	/**************************************************************************\
	* eGroupWare - InfoLog Links                                               *
	* http://www.egroupware.org                                                *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$GLOBALS['phpgw_info']['flags']['included_classes']['solink'] = True;
	/*!
	@class solink
	@author ralfbecker
	@copyright GPL - GNU General Public License
	@abstract generalized linking between entries of eGroupware apps - DB layer
	@discussion This class is to access the links in the DB<br>
		Links have to ends each pointing two an entry, each entry is a double:<br>
		app   app-name or directory-name of an egw application, eg. 'infolog'<br>
		id    this is the id, eg. an integer or a tupple like '0:INBOX:1234'
	@note All vars passed to this class are run either through addslashes or intval 
		to prevent query insertion and to get pgSql 7.3 compatibility.
	*/
	class solink 				// DB-Layer
	{
		var $public_functions = array
		(
			'link'      => True,
			'get_links' => True,
			'unlink'    => True,
			'chown'     => True,
			'get_link'  => True
		);
		var $db;
		var $user;
		var $link_table = 'phpgw_links';
		var $debug;

		/*!
		@function solink
		@syntax solink(   )
		@author ralfbecker
		@abstract constructor
		*/
		function solink( )
		{
			$this->db     = $GLOBALS['phpgw']->db;
			$this->db->set_app('infolog');
			$this->user   = $GLOBALS['phpgw_info']['user']['account_id'];
		}

		/*!
		@function link
		@syntax link(  $app1,$id1,$app2,$id2,$remark='',$user=0  )
		@author ralfbecker
		@abstract creats a link between $app1,$id1 and $app2,$id2
		@param $remark Remark to be saved with the link (defaults to '')
		@param $owner Owner of the link (defaults to user)
		@discussion Does NOT check if link already exists
		@result False (for db or param-error) or link_id for success
		*/
		function link( $app1,$id1,$app2,$id2,$remark='',$owner=0,$lastmod=0 )
		{
			if ($this->debug)
			{
				echo "<p>solink.link('$app1',$id1,'$app2',$id2,'$remark',$owner)</p>\n";
			}
			if ($app1 == $app2 && $id1 == $id2 ||
			    $id1 == '' || $id2 == '' || $app1 == '' || $app2 == '')
			{
				return False;	// dont link to self or other nosense
			}
			if ($link = $this->get_link($app1,$id1,$app2,$id2))
			{
				return $link['link_id'];	// link alread exist
			}
			if (!$owner)
			{
				$owner = $this->user;
			}
			return $this->db->insert($this->link_table,array(
					'link_app1'		=> $app1,
					'link_id1'		=> $id1,
					'link_app2'		=> $app2,
					'link_id2'		=> $id2,
					'link_remark'	=> $remark,
					'link_lastmod'	=> $lastmod ? $lastmod : time(),
					'link_owner'	=> $owner,
				),False,__LINE__,__FILE__) ? $this->db->get_last_insert_id($this->link_table,'link_id') : false;
		}

		/*!
		@function get_links
		@syntax get_links(  $app,$id,$only_app='',$only_name='',$order='link_lastmod DESC'  )
		@author ralfbecker
		@abstract returns array of links to $app,$id
		@param $only_app if set return only links from $only_app (eg. only addressbook-entries) or NOT from if $only_app[0]=='!'
		@param $order defaults to newest links first
		@result array of links (only_app: ids) or empty array if no matching links found
		*/
		function get_links( $app,$id,$only_app='',$order='link_lastmod DESC' )
		{
			if ($this->debug)
			{
				echo "<p>solink.get_links($app,$id,$only_app,$order)</p>\n";
			}
			$links = array();

			$this->db->select($this->link_table,'*',$this->db->expression($this->link_table,'(',array(
						'link_app1'	=> $app,
						'link_id1'	=> $id,
					),') OR (',array(
						'link_app2'	=> $app,
						'link_id2'	=> $id,
					),')'
				),__LINE__,__FILE__,False,$order ? " ORDER BY $order" : '');

			$this->db->query($sql,__LINE__,__FILE__);

			if ($not_only = $only_app[0] == '!')
			{
				$only_app = substr($only_app,1);
			}
			while ($this->db->next_record())
			{
				$row = $this->db->Record;

				if ($row['link_app1'] == $app AND $row['link_id1'] == $id)
				{
					$link = array(
						'app'  => $row['link_app2'],
						'id'   => $row['link_id2']
					);
				}
				else
				{
					$link = array(
						'app'  => $row['link_app1'],
						'id'   => $row['link_id1']
					);
				}
				if ($only_app && $not_only == ($link['app'] == $only_app) ||
					 !$GLOBALS['phpgw_info']['user']['apps'][$link['app']])
				{
					continue;
				}
				$link['remark']  = $row['link_remark'];
				$link['owner']   = $row['link_owner'];
				$link['lastmod'] = $row['link_lastmod'];
				$link['link_id'] = $row['link_id'];

				$links[] = $only_app && !$not_only ? $link['id'] : $link;
			}
			return $links;
		}
		
		/*!
		@function get_link
		@syntax get_link(  $app_link_id,$id='',$app2='',$id2='' )
		@author ralfbecker
		@abstract returns data of a link
		@param $app_link_id > 0 link_id of link or app-name of link
		@param $id,$app2,$id2 other param of the link if not link_id given
		@result array with link-data or False
		*/
		function get_link($app_link_id,$id='',$app2='',$id2='')
		{
			if ($this->debug)
			{
				echo "<p>solink.get_link('$app_link_id',$id,'$app2','$id2')</p>\n";
			}
			if ((int) $app_link_id > 0)
			{
				$where = array('link_id' => $app_link_id);
			}
			else
			{
				if ($app_link_id == '' || $id == '' || $app2 == '' || $id2 == '')
				{
					return False;
				}
				$vars2addslashes = array('app_link_id','id','app2','id2');
				foreach ($vars2addslashes as $var)
				{
					$$var = $this->db->db_addslashes($$var);
				}
				$where = $this->db->expression($this->link_table,'(',array(
						'link_app1'	=> $app_link_id,
						'link_id1'	=> $id,
						'link_app2'	=> $app2,
						'link_id2'	=> $id2,
					),') OR (',array(
						'link_app2'	=> $app_link_id,
						'link_id2'	=> $id,
						'link_app1'	=> $app2,
						'link_id1'	=> $id2,
					),')');
			}
			$this->db->select($this->link_table,'*',$where,__LINE__,__FILE__);

			if ($this->db->next_record())
			{
				if ($this->debug)
				{
					_debug_array($this->db->Record);
				}
				return $this->db->Record;
			}
			return False;
		}

		/*!
		@function unlink
		@syntax unlink( $link_id,$app='',$id='',$owner='',$app2='',$id2='' )
		@author ralfbecker
		@abstract Remove link with $link_id or all links matching given params
		@param $link_id link-id to remove if > 0
		@param $app,$id,$owner,$app2,$id2 if $link_id <= 0: removes all links matching the non-empty params
		@result the number of links deleted
		*/
		function unlink($link_id,$app='',$id='',$owner='',$app2='',$id2='')
		{
			if ($this->debug)
			{
				echo "<p>solink.unlink($link_id,$app,$id,$owner,$app2,$id2)</p>\n";
			}
			$sql = "DELETE FROM $this->link_table WHERE ";
			if ((int)$link_id > 0)
			{
				$where = array('link_id' => $link_id);
			}
			elseif ($app == '' AND $owner == '')
			{
				return 0;
			}
			else
			{
				if ($app != '' && $app2 == '')
				{
					$check1 = array('link_app1' => $app);
					$check2 = array('link_app2' => $app);
					if ($id != '')
					{
						$check1['link_id1'] = $id;
						$check2['link_id2'] = $id;
					}
					$where = $this->db->expression($this->link_table,'((',$check1,') OR (',$check2,'))');
				}
				elseif ($app != '' && $app2 != '')
				{
					$where = $this->db->expression($this->link_table,'((',array(
							'link_app1'	=> $app,
							'link_id1'	=> $id,
							'link_app2'	=> $app2,
							'link_id2'	=> $id2,
						),') OR (',array(
							'link_app1'	=> $app2,
							'link_id1'	=> $id2,
							'link_app2'	=> $app,
							'link_id2'	=> $id,
						),')');
				}
				if ($owner != '')
				{
					if ($app) $where = array($where);
					$where['link_owner'] = $owner;
				}
			}
			$this->db->delete($this->link_table,$where,__LINE__,__FILE__);

			return $this->db->affected_rows();
		}

		/*!
		@function chown
		@syntax chown( $owner,$new_owner )
		@author ralfbecker
		@abstract Changes ownership of all links from $owner to $new_owner
		@discussion This is needed when a user/account gets deleted
		@discussion Does NOT change the modification-time
		@result the number of links changed
		*/
		function chown($owner,$new_owner)
		{
			if ((int)$owner <= 0 || (int) $new_owner)
			{
				return 0;
			}
			$this->db->update($this->link_table,array('owner'=>$new_owner),array('owner'=>$owner),__LINE__,__FILE__);

			return $this->db->affected_rows();
		}
	}



