<?php
  /**************************************************************************\
  * phpGroupWare - addressbook                                               *
  * http://www.phpgroupware.org                                              *
  * Written by Miles Lott <milosch@phpgroupware.org>                         *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	class boaddressbook
	{
		var $public_functions = array(
			'read_entries'    => True,
			'read_entry'      => True,
			'read_last_entry' => True,
			'add_entry'       => True,
			'add_vcard'       => True,
			'add_email'       => True,
			'update_entry'    => True
		);

		var $debug = False;

		var $so;
		var $start;
		var $limit;
		var $query;
		var $sort;
		var $order;
		var $filter;
		var $cat_id;
		var $total;

		var $use_session = False;

		function boaddressbook($session=False)
		{
			global $phpgw;

			$this->so = CreateObject('addressbook.soaddressbook');

			if($session)
			{
				$this->read_sessiondata();
				$this->use_session = True;
			}
			global $start,$limit,$query,$sort,$order,$filter,$cat_id,$fcat_id;

			if(!empty($start) || ($start == "0" ))
			{
				if($this->debug) { echo '<br>overriding start: "' . $this->start . '" now "' . $start . '"'; }
				$this->start = $start;
			}
			if($limit)  { $this->limit  = $limit;  }
			if(isset($query))   { $this->query  = $query;  }
			if(isset($sort))    { $this->sort   = $sort;   }
			if(isset($order))   { $this->order  = $order;  }
			if(isset($filter))  { $this->filter = $filter; }
			if(isset($fcat_id)) { $this->cat_id = $fcat_id; }
		}

		function save_sessiondata($data)
		{
			if ($this->use_session)
			{
				global $phpgw;
				if($this->debug) { echo '<br>Save:'; _debug_array($data); }
				$phpgw->session->appsession('session_data','addressbook',$data);
			}
		}

		function read_sessiondata()
		{
			global $phpgw;

			$data = $phpgw->session->appsession('session_data','addressbook');
			if($this->debug) { echo '<br>Read:'; _debug_array($data); }

			$this->start  = $data['start'];
			$this->limit  = $data['limit'];
			$this->query  = $data['query'];
			$this->sort   = $data['sort'];
			$this->order  = $data['order'];
			$this->filter = $data['filter'];
			$this->cat_id = $data['cat_id'];
		}

		function strip_html($dirty = '')
		{
			global $phpgw;

			if ($dirty == ''){$dirty = array();}
			for($i=0;$i<count($dirty);$i++)
			{
				while (list($name,$value) = each($dirty[$i]))
				{
					$cleaned[$i][$name] = $phpgw->strip_html($dirty[$i][$name]);
				}
			}
			return $cleaned;
		}

		function read_entries($start,$limit,$qcols,$qfilter,$userid='')
		{
			$entries = $this->so->read_entries($start,$limit,$qcols,$this->query,$qfilter,$this->sort,$this->order,$userid);
			$this->total = $this->so->contacts->total_records;
			if($this->debug) { echo '<br>Total records="' . $this->total . '"'; }
			return $this->strip_html($entries);
		}

		function read_entry($id,$fields,$userid='')
		{
			$entry = $this->so->read_entry($id,$fields,$userid);
			return $this->strip_html($entry);
		}

		function read_last_entry($fields)
		{
			$entry = $this->so->read_last_entry($fields);
			return $this->strip_html($entry);
		}

		function add_vcard()
		{
			global $phpgw,$phpgw_info,$uploadedfile;

			if($uploadedfile == 'none' || $uploadedfile == '')
			{
				Header('Location: ' . $phpgw->link('/index.php','menuaction=addressbook.uivcard.in&action=GetFile'));
			}
			else
			{
				$uploaddir = $phpgw_info['server']['temp_dir'] . SEP;

				srand((double)microtime()*1000000);
				$random_number = rand(100000000,999999999);
				$newfilename = md5("$uploadedfile, $uploadedfile_name, "
					. time() . getenv("REMOTE_ADDR") . $random_number );

				copy($uploadedfile, $uploaddir . $newfilename);
				$ftp = fopen($uploaddir . $newfilename . '.info','w');
				fputs($ftp,"$uploadedfile_type\n$uploadedfile_name\n");
				fclose($ftp);

				$filename = $uploaddir . $newfilename;

				$vcard = CreateObject('phpgwapi.vcard');
				$entry = $vcard->in_file($filename);
				/* _debug_array($entry);exit; */
				$this->so->add_entry($phpgw_info['user']['account_id'],$entry,'private','','n');
				$ab_id = $this->get_lastid();

				/* Delete the temp file. */
				unlink($filename);
				unlink($filename . '.info');
				Header('Location: ' . $phpgw->link('/index.php','menuaction=addressbook.uiaddressbook.view&ab_id=' . $ab_id));
			}
		}

		function add_email()
		{
			global $phpgw_info,$name,$referer;

			$named = explode(' ', $name);
			for ($i=count($named);$i>=0;$i--) { $names[$i] = $named[$i]; }
			if ($names[2])
			{
				$fields['n_given']  = $names[0];
				$fields['n_middle'] = $names[1];
				$fields['n_family'] = $names[2];
			}
			else
			{
				$fields['n_given']  = $names[0];
				$fields['n_family'] = $names[1];
			}
			$fields['email']    = $add_email;
			$referer = urlencode($referer);

			$this->so->add_entry($phpgw_info['user']['account_id'],$fields,'private','','n');
			$ab_id = $this->get_lastid();

			Header('Location: '
				. $phpgw->link('/index.php',"menuaction=addressbook.uiaddressbook.view&ab_id=$ab_id&referer=$referer"));
		}

		function OLDcopy_entry($ab_id)
		{
			global $phpgw,$phpgw_info;

			$addnew = $this->read_entry($ab_id,$this->so->contacts->stock_contact_fields,$phpgw_info['user']['account_id']);

			$addnew[0]['note'] .= "\nCopied from ".$phpgw->accounts->id2name($addnew[0]['owner']).", record #".$addnew[0]['id'].".";
			$addnew[0]['owner'] = $phpgw_info['user']['account_id'];
			$addnew[0]['id']    = '';
			$fields = $addnew[0];

			if ($addnew['tid'])
			{
				$this->so->add_entry($fields['owner'],$fields,$fields['access'],$fields['cat_id'],$fields['tid']);
			}
			else
			{
				$this->so->add_entry($fields['owner'],$fields,$fields['access'],$fields['cat_id']);
			}

			$ab_id = $this->get_lastid();
			Header("Location: " . $phpgw->link('/index.php',"menuaction=addressbook.uiaddressbook.edit&ab_id=$ab_id"));
		}

		function add_entry($userid,$fields)
		{
			return $this->so->add_entry($userid,$fields);
		}

		function get_lastid()
		{
			return $this->so->get_lastid();
		}

		function update_entry($userid,$fields)
		{
			return $this->so->update_entry($userid,$fields);
		}

		function delete_entry($ab_id)
		{
			return $this->so->delete_entry($ab_id);
		}
	}
?>
