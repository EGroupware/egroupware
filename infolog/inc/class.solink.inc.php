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

	/*!
	@class solink
	@author ralfbecker
	@abstract generalized linking between entries of phpGroupware apps - DB layer
	@discussion This class is to access the links in the DB
	@discussion Links have to ends each pointing to an entry, an entry is a double:
	@discussion app   app-name or directory-name of an phpgw application, eg. 'infolog'
	@discussion id    this is the id, eg. an integer or a tupple like '0:INBOX:1234'
	*/
	class solink 				// DB-Layer
	{
		var $public_functions = array
		(
			'link'      => True,
			'get_links' => True,
			'unlink'    => True,
			'chown'     => True
		);
		var $db,$db2;
		var $user;
		var $db_name = 'phpgw_links';
		var $debug = 0;

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
		@syntax link(  $app1,$name1,$id1,$app2,$name2,$id2,$remark='',$user=0  )
		@author ralfbecker
		@abstract creats a link between $app1,$name1,$id1 and $app2,$name2,$id2
		@param $remark Remark to be saved with the link (defaults to '')
		@param $owner Owner of the link (defaults to user)
		@discussion Does NOT check if link already exists
		@result db-errno or -1 (for param-error) or 0 for success
		*/
		function link( $app1,$id1,$app2,$id2,$remark='',$owner=0 )
		{
			if ($this->debug)
				echo "<p>solink.link($app1,$id1,$app2,$id2,'$remark',$owner)</p>\n";

			if ($app1 == $app2 && $id1 == $id2 ||
			    $id1 == '' || $id2 == '' || $app1 == '' || $app2 == '')
			{
				return -1;	// dont link to self or other nosense
			}
			if (!$owner)
			{
				$owner = $this->user;
			}
			$remark = $this->db->db_addslashes($remark);
			$lastmod = time();

			$sql = "INSERT INTO $this->db_name (link_app1,link_id1,link_app2,link_id2,link_remark,link_lastmod,link_owner) ".
			       " VALUES ('$app1','$id1','$app2','$id2','$remark',$lastmod,$owner)";

			if ($this->debug)
			{
				echo "<p>solink.link($app1,$id1,$app2,$id2,'$remark',$owner) sql='$sql'</p>\n";
			}
			$this->db->query($sql);

			return $this->db->errno;
		}

		/*!
		@function get_links
		@syntax get_links(  $app,$name,$id,$only_app='',$only_name='',$order='link_lastmod DESC'  )
		@author ralfbecker
		@abstract returns array of links to $app,$name,$id
		@param $only_app if set return only links from $only_app (eg. only addressbook-entries)
		@param $order defaults to newest links first
		@result array of links or empty array if no matching links found
		*/
		function get_links( $app,$id,$only_app='',$order='link_lastmod DESC' )
		{
			$links = array();

			$sql = "SELECT * FROM $this->db_name".
					 " WHERE (link_app1 = '$app' AND link_id1 = '$id')".
					 " OR (link_app2 = '$app' AND link_id2 = '$id')".
					 ($order != '' ? " ORDER BY $order" : '');

			if ($this->debug)
			{
				echo "<p>solink.get_links($app,$id,$only_app,$order) sql='$sql'</p>\n";
			}
			$this->db->query($sql);

			while ($this->db->next_record())
			{
				$row = $this->db->Record;

            if ($row['link_app1'] == $app AND $row['link_id1'] == $id)
				{
					$link = array(
						'app'  => $row['link_app2'],
						'id'   => stripslashes($row['link_id2'])
					);
				}
				else
				{
					$link = array(
						'app'  => $row['link_app1'],
						'id'   => stripslashes($row['link_id1'])
					);
				}
				if ($only_app != '' && $link['app'] != $only_app)
				{
					continue;
				}
				$link['remark']  = stripslashes($row['link_remark']);
				$link['owner']   = $row['link_owner'];
				$link['lastmod'] = $row['link_lastmod'];
				$link['link_id'] = $row['link_id'];

				$links[] = $link;
			}
			return $links;
		}

		/*!
      @function unlink
      @syntax unlink( $link_id,$app='',$id='',$owner='' )
      @author ralfbecker
		@abstract Remove link with $link_id or all links matching given params
		@param $link_id link-id to remove if > 0
		@param $app,$id,$owner if $link_id <= 0: removes all links matching the non-empty params
		@result the number of links deleted
		*/
		function unlink($link_id,$app='',$id='',$owner='')
		{
			$sql = "DELETE FROM $this->db_name WHERE ";
			if ($link_id > 0)
			{
				$sql .= "link_id=$link_id";
			}
			elseif ($app == '' AND $owner == '')
			{
				return 0;
			}
			else
			{
				if ($app != '')
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
				if ($owner != '')
				{
					$sql .= ($app != '' ? ' AND ' : '') . "link_owner='$owner'";
				}
			}
			if ($this->debug)
			{
				echo "<p>solink.unlink($link_id,$app,$id,$owner) sql='$sql'</p>\n";
			}
			$this->db->query($sql);

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
			if ($owner <= 0 || $new_owner <= 0)
			{
				return 0;
			}
			$this->db->query("UPDATE $this->db_name SET owner=$new_owner WHERE owner=$owner");

			return $this->db->affected_rows();
		}
	}




