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

	$GLOBALS['phpgw_info']['flags']['included_classes']['solink'] = True;
	/*!
	@class solink
	@author ralfbecker
	@copyright GPL - GNU General Public License
	@abstract generalized linking between entries of phpGroupware apps - DB layer
	@discussion This class is to access the links in the DB<br>
		Links have to ends each pointing two an entry, each entry is a double:<br>
		app   app-name or directory-name of an phpgw application, eg. 'infolog'<br>
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
		var $db_name = 'phpgw_links';
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
			$vars2addslashes = array('app1','id1','app2','id2','remark');
			foreach ($vars2addslashes as $var)
			{
				$$var = $this->db->db_addslashes($$var);
			}
			if (!$lastmod)
			{
				$lastmod = time();
			}
			$sql = "INSERT INTO $this->db_name (link_app1,link_id1,link_app2,link_id2,link_remark,link_lastmod,link_owner) ".
			       " VALUES ('$app1','$id1','$app2','$id2','$remark',".intval($lastmod).','.intval($owner).')';

			if ($this->debug)
			{
				echo "<p>solink.link($app1,$id1,$app2,$id2,'$remark',$owner) sql='$sql'</p>\n";
			}
			$this->db->query($sql,__LINE__,__FILE__);

			return $this->db->errno ? False : $this->db->get_last_insert_id($this->db_name,'link_id');
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
			$links = array();

			$vars2addslashes = array('app','id','only_app','order');
			foreach ($vars2addslashes as $var)
			{
				$$var = $this->db->db_addslashes($$var);
			}
			$sql = "SELECT * FROM $this->db_name".
					 " WHERE (link_app1 = '$app' AND link_id1 = '$id')".
					 " OR (link_app2 = '$app' AND link_id2 = '$id')".
					 ($order != '' ? " ORDER BY $order" : '');

			if ($this->debug)
			{
				echo "<p>solink.get_links($app,$id,$only_app,$order) sql='$sql'</p>\n";
			}
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
			$sql = "SELECT * FROM $this->db_name WHERE ";
			if (intval($app_link_id) > 0)
			{
				$sql .= 'link_id='.intval($app_link_id);
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
				$sql .= "(link_app1='$app_link_id' AND link_id1='$id' AND link_app2='$app2' AND link_id2='$id2') OR".
				        "(link_app2='$app_link_id' AND link_id2='$id' AND link_app1='$app2' AND link_id1='$id2')";
			}
			$this->db->query($sql,__LINE__,__FILE__);

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
			$sql = "DELETE FROM $this->db_name WHERE ";
			if (intval($link_id) > 0)
			{
				$sql .= 'link_id='.intval($link_id);
			}
			elseif ($app == '' AND $owner == '')
			{
				return 0;
			}
			else
			{
				$vars2addslashes = array('app','id','app2','id2');
				foreach ($vars2addslashes as $var)
				{
					$$var = $this->db->db_addslashes($$var);
				}
				if ($app != '' && $app2 == '')
				{
					$sql .= "((link_app1='$app'";
					$sql2 = '';
					if ($id != '')
					{
						$sql  .= " AND link_id1='$id'";
						$sql2 .= " AND link_id2='$id'";
					}
					$sql .= ") OR (link_app2='$app'$sql2))";
				}
				elseif ($app != '' && $app2 != '')
				{
					$sql .= "((link_app1='$app' AND link_id1='$id' AND link_app2='$app2' AND link_id2='$id2') OR";
					$sql .= " (link_app1='$app2' AND link_id1='$id2' AND link_app2='$app' AND link_id2='$id'))";
				}
				if ($owner != '')
				{
					$sql .= ($app != '' ? ' AND ' : '') . 'link_owner='.intval($owner);
				}
			}
			if ($this->debug)
			{
				echo "<p>solink.unlink($link_id,$app,$id,$owner,$app2,$id2) sql='$sql'</p>\n";
			}
			$this->db->query($sql,__LINE__,__FILE__);

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
			if (intval($owner) <= 0 || intval($new_owner) <= 0)
			{
				return 0;
			}
			$this->db->query("UPDATE $this->db_name SET owner=".intval($new_owner).' WHERE owner='.intval($owner),__LINE__,__FILE__);

			return $this->db->affected_rows();
		}
	}



