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
			'copy_entry'      => True,
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
			global $start,$limit,$query,$sort,$order,$filter,$cat_id;

			if(!empty($start) || ($start == "0" ))
			{
				if($this->debug) { echo '<br>overriding start: "' . $this->start . '" now "' . $start . '"'; }
				$this->start = $start;
			}
			if($limit)  { $this->limit  = $limit;  }
			if(!empty($query))  { $this->query  = $query;  }
			if(!empty($sort))   { $this->sort   = $sort;   }
			if(!empty($order))  { $this->order  = $order;  }
			if(!empty($filter)) { $this->filter = $filter; }
			$this->cat_id = $cat_id;
		}

		function save_sessiondata()
		{
			global $phpgw,$start,$limit,$query,$sort,$order,$filter,$cat_id;

			if ($this->use_session)
			{
				$data = array(
					'start'  => $start,
					'limit'  => $limit,
					'query'  => $query,
					'sort'   => $sort,
					'order'  => $order,
					'filter' => $filter,
					'cat_id' => $cat_id
				);
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
				Header('Location: ' . $phpgw->link('/addressbook/main.php','menuaction=addressbook.uivcard.in&action=GetFile'));
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
				Header('Location: ' . $phpgw->link('/addressbook/main.php','menuaction=addressbook.uiaddressbook.view&ab_id=' . $ab_id));
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
				. $phpgw->link('/addressbook/main.php',"menuaction=addressbook.uiaddressbook.view&ab_id=$ab_id&referer=$referer"));
		}

		function copy_entry()
		{
			global $phpgw,$phpgw_info,$ab_id;

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
			Header("Location: " . $phpgw->link('/addressbook/main.php',"menuaction=addressbook.uiaddressbook.edit&ab_id=$ab_id"));
		}

		function add_entry()
		{
			global $phpgw,$phpgw_info;

			$fields = $this->get_form();

			$referer = urlencode($fields['referer']);
			unset($fields['referer']);

			$this->so->add_entry($phpgw_info['user']['account_id'],$fields,$fields['access'],$fields['cat_id'],$fields['tid']);

			$ab_id = $this->get_lastid();

			Header('Location: '
					. $phpgw->link('/addressbook/main.php',"menuaction=addressbook.uiaddressbook.view&ab_id=$ab_id&referer=$referer"));
			$phpgw->common->phpgw_exit();
		}

		function get_lastid()
		{
			return $this->so->get_lastid();
		}

		function update_entry()
		{
			global $phpgw,$phpgw_info;

			$fields = $this->get_form();
			$check = $this->read_entry($fields['ab_id'],array('owner' => 'owner'));

			if (($this->contacts->grants[$check[0]['owner']] & PHPGW_ACL_EDIT) && $check[0]['owner'] != $phpgw_info['user']['account_id'])
			{
				$userid = $check[0]['owner'];
			}
			else
			{
				$userid = $phpgw_info['user']['account_id'];
			}
			$referer = urlencode($fields['referer']);
			unset($fields['referer']);

			$this->so->update_entry($fields['ab_id'],$fields['userid'],$fields,$fields['access'],$fields['cat_id'],$fields['tid']);

			Header("Location: "
				. $phpgw->link('/addressbook/main.php',"menuaction=addressbook.uiaddressbook.view&ab_id=" . $fields['ab_id'] . "&referer=$referer"));
			$phpgw->common->phpgw_exit();
		}

		function get_form()
		{
			global $entry;
			/* _debug_array($entry); */

			if (!$entry['bday_month'] && !$entry['bday_day'] && !$entry['bday_year'])
			{
				$fields['bday'] = '';
			}
			else
			{
				$bday_day = $entry['bday_day'];
				if (strlen($bday_day) == 1)
				{
					$bday_day = '0' . $entry['bday_day'];
				}
				$fields['bday'] = $entry['bday_month'] . '/' . $bday_day . '/' . $entry['bday_year'];
			}

			if ($entry['url'] == 'http://')
			{
				$fields['url'] = '';
			}

			$fields['org_name']				= $entry['company'];
			$fields['org_unit']				= $entry['department'];
			$fields['n_given']				= $entry['firstname'];
			$fields['n_family']				= $entry['lastname'];
			$fields['n_middle']				= $entry['middle'];
			$fields['n_prefix']				= $entry['prefix'];
			$fields['n_suffix']				= $entry['suffix'];
			if ($entry['prefix']) { $pspc = ' '; }
			if ($entry['middle']) { $mspc = ' '; } else { $nspc = ' '; }
			if ($entry['suffix']) { $sspc = ' '; }
			$fields['fn']					= $entry['prefix'].$pspc.$entry['firstname'].$nspc.$mspc.$entry['middle'].$mspc.$entry['lastname'].$sspc.$entry['suffix'];
			$fields['email']				= $entry['email'];
			$fields['email_type']			= $entry['email_type'];
			$fields['email_home']			= $entry['hemail'];
			$fields['email_home_type']		= $entry['hemail_type'];
			$fields['title']				= $entry['title'];
			$fields['tel_work']				= $entry['wphone'];
			$fields['tel_home']				= $entry['hphone'];
			$fields['tel_fax']				= $entry['fax'];
			$fields['tel_pager']			= $entry['pager'];
			$fields['tel_cell']				= $entry['mphone'];
			$fields['tel_msg']				= $entry['msgphone'];
			$fields['tel_car'] 				= $entry['carphone'];
			$fields['tel_video']			= $entry['vidphone'];
			$fields['tel_isdn']				= $entry['isdnphone'];
			$fields['adr_one_street']		= $entry['bstreet'];
			$fields['adr_one_locality']		= $entry['bcity'];
			$fields['adr_one_region']		= $entry['bstate'];
			$fields['adr_one_postalcode']	= $entry['bzip'];
			$fields['adr_one_countryname']	= $entry['bcountry'];

			if($entry['one_dom'])
			{
				$typea .= 'dom;';
			}
			if($entry['one_intl'])
			{
				$typea .= 'intl;';
			}
			if($entry['one_parcel'])
			{
				$typea .= 'parcel;';
			}
			if($entry['one_postal'])
			{
				$typea .= 'postal;';
			}
			$fields['adr_one_type'] = substr($typea,0,-1);

			$fields['address2']				= $entry['address2'];
			$fields['address3']				= $entry['address3'];

			$fields['adr_two_street']		= $entry['hstreet'];
			$fields['adr_two_locality']		= $entry['hcity'];
			$fields['adr_two_region']		= $entry['hstate'];
			$fields['adr_two_postalcode']	= $entry['hzip'];
			$fields['adr_two_countryname']	= $entry['hcountry'];

			if($entry['two_dom'])
			{
				$typeb .= 'dom;';
			}
			if($entry['two_intl'])
			{
				$typeb .= 'intl;';
			}
			if($entry['two_parcel'])
			{
				$typeb .= 'parcel;';
			}
			if($entry['two_postal'])
			{
				$typeb .= 'postal;';
			}
			$fields['adr_two_type'] = substr($typeb,0,-1);

			while (list($name,$val) = @each($entry['customfields']))
			{
				$fields[$name] = $val;
			}

			$fields['ophone']	= $entry['ophone'];
			$fields['tz']		= $entry['timezone'];
			$fields['pubkey']	= $entry['pubkey'];
			$fields['note']		= $entry['notes'];
			$fields['label']	= $entry['label'];

			if ($entry['access'] == True)
			{
				$fields['access'] = 'private';
			}
			else
			{
				$fields['access'] = 'public';
			}

			if (is_array($entry['cat_id']))
			{
				$fields['cat_id'] = count($entry['cat_id']) > 1 ? ','.implode(',',$entry['cat_id']).',' : $entry['cat_id'][0];
			}
			else
			{
				$fields['cat_id'] = $entry['cat_id'];
			}	

			$fields['ab_id']   = $entry['ab_id'];
			$fields['tid']     = $entry['tid'];
			$fields['referer'] = $entry['referer'];
			/* _debug_array($fields);exit; */
			return $fields;
		} /* end get_form() */
	}
?>
