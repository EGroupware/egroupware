<?php
	/***************************************************************************\
	* phpGroupWare - Notes eTemplate Port                                       *
	* http://www.phpgroupware.org                                               *
	* Written by : Bettina Gille [ceb@phpgroupware.org]                         *
	* Ported to eTemplate by Ralf Becker [ralfbecker@outdoor-training.de]       *
	* -------------------------------------------------                         *
	* This program is free software; you can redistribute it and/or modify it   *
	* under the terms of the GNU General Public License as published by the     *
	* Free Software Foundation; either version 2 of the License, or (at your    *
	* option) any later version.                                                *
	\***************************************************************************/
	/* $Id$ */

	class so
	{
		var $grants;

		function so()
		{
			$this->db     = $GLOBALS['phpgw']->db;
			$this->db2    = $this->db;
			$this->grants = $GLOBALS['phpgw']->acl->get_grants('et_notes');
			$this->owner  = $GLOBALS['phpgw_info']['user']['account_id'];
		}

		function read($start, $search = '', $filter = '',$cat_id = '')
		{
			if (! $filter)
			{
				$filter = 'all';
			}

			if ($filter == 'all')
			{
				$filtermethod = " ( note_owner=" . $this->owner;
				if (is_array($this->grants))
				{
					$grants = $this->grants;
					while (list($user) = each($grants))
					{
						$public_user_list[] = $user;
					}
					reset($public_user_list);
					$filtermethod .= " OR (note_access='public' AND note_owner IN(" . implode(',',$public_user_list) . ")))";
				}
				else
				{
					$filtermethod .= ' )';
				}
			}
			elseif ($filter == 'public')
			{
				$filtermethod = " note_owner='" . $this->owner . "'";
			}
			else
			{
				$filtermethod = " note_owner='" . $this->owner . "' AND note_access='private'";
			}

			if ($cat_id)
			{
				$filtermethod .= " AND note_category='$cat_id' ";
			}

			if($search)
			{
				$search = ereg_replace("'",'',$search);
				$search = ereg_replace('"','',$search);

				$searchmethod = " AND note_content LIKE '%$search%'";
			}

			$sql = "SELECT * FROM phpgw_et_notes WHERE $filtermethod $searchmethod ORDER BY note_date DESC";

			$this->db2->query($sql,__LINE__,__FILE__);
			$this->total_records = $this->db2->num_rows();
			$this->db->limit_query($sql,$start,__LINE__,__FILE__);

			while ($this->db->next_record())
			{
				$ngrants = (int)$this->grants[$this->db->f('note_owner')];
				$notes[] = array
				(
					'id'		=> (int)$this->db->f('note_id'),
					'owner'		=> $this->db->f('note_owner'),
					'owner_id'	=> (int)$this->db->f('note_owner'),
					'access'	=> $this->db->f('note_access'),
					'date'		=> $this->db->f('note_date'),
					'cat'		=> (int)$this->db->f('note_category'),
					'content'	=> $this->db->f('note_content'),
					'grants'	=> $ngrants
				);
			}
			return $notes;
		}

		function read_single($note_id)
		{
			$this->db->query("select * from phpgw_et_notes where note_id='$note_id'",__LINE__,__FILE__);

			if ($this->db->next_record())
			{
				$note['id']			= (int)$this->db->f('note_id');
				$note['owner']		= $this->db->f('note_owner');
				$note['content']	= $this->db->f('note_content');
				$note['access']		= $this->db->f('note_access');
				$note['date']		= $this->db->f('note_date');
				$note['cat']		= (int)$this->db->f('note_category');

				return $note;
			}
		}

		function add($note)
		{
			$note['content'] = $this->db->db_addslashes($note['content']);

			$this->db->query("INSERT INTO phpgw_et_notes (note_owner,note_access,note_date,note_content,note_category) "
				. "VALUES ('" . $this->owner . "','" . $note['access'] . "','" . time() . "','" . $note['content']
				. "','" . $note['cat'] . "')",__LINE__,__FILE__);
			//return $this->db->get_last_insert_id('phpgw_et_notes','note_id');
		}

		function edit($note)
		{
			$note['content'] = $this->db->db_addslashes($note['content']);

			$this->db->query("UPDATE phpgw_et_notes set note_content='" . $note['content'] . "', note_date='" . time() . "', note_category='"
							. $note['cat'] . "', note_access='" . $note['access'] . "' WHERE note_id=" . intval($note['id']),__LINE__,__FILE__);
		}

		function delete($note_id)
		{
			$this->db->query('DELETE FROM phpgw_et_notes WHERE note_id=' . intval($note_id),__LINE__,__FILE__);
		}
	}
?>
