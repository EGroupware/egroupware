<?php
	/**************************************************************************\
	* phpGroupWare - InfoLog                                                   *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* originaly based on todo written by Joseph Engo <jengo@phpgroupware.org>  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	class infolog 
	{
		var $db,$db2;
		var $grants;
		var $icons;
		var $enums;
		var $data = array( );

		function infolog( $info_id = 0) {
			global $phpgw;
			$this->db     = $phpgw->db;
			$this->grants = $phpgw->acl->get_grants('infolog');
			$this->enums = array( 'priority' => array( 'urgent' => 'urgent','high' => 'high','normal' => 'normal','low' => 'low' ),
										 'status'   => array( 'offer' => 'offer','ongoing' => 'ongoing','call' => 'call',
																	  'will-call' => 'will-call','done' => 'done','billed' => 'billed' ),
										 'confirm'   => array( 'not' => 'not','accept' => 'accept','finish' => 'finish','both' => 'both' ),
										 'type'      => array( 'task' => 'task','phone' => 'phone','note' => 'note','confirm' => 'confirm',
																	  'reject' => 'reject','email' => 'email','fax' => 'fax' ));
										 
			$this->icons = array( 'type' => array(   'task' => 'task.gif',       'task_alt' => 'Task',
																'phone' => 'phone.gif',      'phone_alt' => 'Phonecall',
																'note' => 'note.gif',      'note_alt' => 'Note',
																'confirm' => 'confirm.gif','confirm_alt' => 'Confirmation',
																'reject' => 'reject.gif',   'reject_alt' => 'Reject',
																'email' => 'email.gif',      'email_alt' => 'Email' ),
										 'action' => array( 'new' => 'new.gif',         'new_alt' => 'Add Sub',
																'view' => 'view.gif',      'view_alt' => 'View Subs',
																'parent' => 'parent.gif',   'parent_alt' => 'View other Subs',
																'edit' => 'edit.gif',      'edit_alt' => 'Edit',
																'delete' => 'delete.gif',   'delete_alt' => 'Delete' ),
									  'status' => array( 'billed' => 'billed.gif',   'billed_alt' => 'billed',
																  'done' => 'done.gif',      'done_alt' => 'done',
																'will-call' => 'will-call.gif', 'will-call_alt' => 'will-call',
																'call' => 'call.gif',      'call_alt' => 'call',
																'ongoing' => 'ongoing.gif','ongoing_alt' => 'ongoing',
																'offer' => 'offer.gif',      'offer_alt' => 'offer' ));
			
			$this->longnames = 0;   // should go into preferences
			$this->listChilds = 1;

			$this->html = CreateObject('infolog.html');
			
			$this->read( $info_id);
		}
				
		function icon($cat,$id,$status='') {
			// echo "<br>icon('$cat','$id','$status')";
			global $phpgw,$DOCUMENT_ROOT;
			$icons = &$this->icons[$cat];

			if (!$status || !($icon = $icons[$id.'_'.$status]))
				$icon = $icons[$id];
			if ($icon) {
				$fname = $phpgw->common->get_image_dir() . '/' . $icon; 
				if (!is_readable($fname)) {
					$icon = False;      // echo "<br>Can't read '$fname' !!!";
				} else {            
					$icon = $phpgw->common->get_image_path() . '/' . $icon;
				}            
			}
			if (!$status || !($alt = $icons[$id.'_'.$status.'_alt']))
				if (!($alt = $icons[$id.'_alt']))
					$alt = $id;
			
			return ($icon ? "<img src='$icon' alt='" : '') . lang($alt) . ($icon ? '\' border=0>' : '');
		}
		
		function setStyleSheet( ) {
			global $phpgw;
			return array ( 'info_css' => '<link rel="stylesheet" type="text/css" href="'.
													str_replace( '/images','',$phpgw->common->get_image_path()).'/info.css">' );
		}      
		
		function loadStyleSheet( ) {
			list( $style ) = $this->setStyleSheet(); echo $style;
		}
				
		function accountInfo($id,$account_data=0,$longname=0) {
			global $phpgw;
			
			if (!$id) return '&nbsp;';
			
			if (!is_array($account_data)) {
				$accounts = createobject('phpgwapi.accounts',$id);
				$accounts->db = $phpgw->db;
				$accounts->read_repository();
				$account_data = $accounts->data;
			}
			if ($longnames)
				return $account_data['firstname'].' '.$account_data['lastname'];
				
			return $account_data['account_lid'];   
		}      

		function addr2name( $addr ) {
			global $phpgw;
			$name = $addr['n_family'];
			if ($addr['n_given'])
				$name .= ', '.$addr['n_given'];
			else 
				if ($addr['n_prefix'])
					$name .= ', '.$addr['n_prefix'];
			if ($addr['org_name'])
				$name = $addr['org_name'].': '.$name;
			return $phpgw->strip_html($name);         
		}

		function readProj($proj_id) {
			if ($proj_id) {
				if (!is_object($this->projects)) {
					$this->projects = createobject('projects.projects');
				}            
				if (list( $proj ) = $this->projects->read_single_project( $proj_id ))
					return $proj;
			}
			return False;         
		}               

		function readAddr($addr_id) {
			if ($addr_id) {
				if (!is_object($this->contacts)) {
					$this->contacts = createobject('phpgwapi.contacts');
				}            
				if (list( $addr ) = $this->contacts->read_single_entry( $addr_id ))
					return $addr;
			}
			return False;                  
		}      
					
		function formatInfo($info=0,$p_id=0,$a_id=0) {   // $info: info_id or array with one row form info-db
			global $phpgw,$phpgw_info;                     // no Proj.Info if proj_id == p_id / no Addr.Info if addr_id == a_id
			
			if (!is_array($info) && (!$info || !is_array($info = $this->read($info))))
				$info = $this->data;
			
			$done = $info['info_status'] == 'done' || $info['info_status'] == 'billed';   
			$css_class = $info['info_pri'].($done ? '_done' : '');
			$subject = "<span class=$css_class>";
			
			if ($p_id != ($proj_id = $info['info_proj_id']) && $proj = $this->readProj($proj_id)) {
				$subject .= '<b><a href="'.$this->html->link('/infolog/index.php',
								array( 'filter' => $filter,'action' => 'proj','proj_id' => $proj_id )).
								'">'.$proj['title'].'</a></b>';
			}
			if ($a_id != ($addr_id = $info['info_addr_id']) && $addr = $this->readAddr($addr_id)) {
				if ($proj) $subject .= '<br>';
				$addr = $this->addr2name( $addr );
				$subject .= '<b><a href="'.$this->html->link('/infolog/index.php',
								array( 'filter' => $filter,'action' => 'addr','addr_id' => $addr_id )).
								"\">$addr</a></b>";
			}
			if (($from = $info['info_from']) && (!$addr || !strstr($addr,$from))) {
				if ($addr) $subject .= '<br>';
				$subject .= '<b>'.$from.'</b>';
			}
			if ($info['info_addr']) {
				if ($addr || $from) $subject .= ': ';
				$subject .= $info['info_addr'];
			}
			if ($proj || $addr || $from || $info['info_addr']) {
				$subject .= '<br>';
			}
			$subject .= '<b>';
			$subject .= $info['info_subject'] ? $info['info_subject'] : substr($info['info_des'],0,60).' ...';
			$subject .= '</b></span>';

			if (!$info['info_enddate']) {
				$enddate = '&nbsp;';
			} else {
				$enddate = $phpgw->common->show_date($info['info_enddate'],$phpgw_info['user']['preferences']['common']['dateformat']);
				
				if (!$done && $info['info_enddate'] < time()+(60*60)*$phpgw_info['user']['preferences']['common']['tz_offset'])
					$enddate = "<span class=overdue>$enddate</span>";
			}
			if (!($responsible = $info['info_responsible']) && $info['info_status'] == 'offer') {
				$responsible = $this->icon('status','offer');
			} else {
				$responsible = $this->accountInfo($responsible);         
			}         
			$owner = $this->accountInfo($info['info_owner']);
			if ($info['info_access'] == 'private')
				$owner = "<span class=private>$owner</span>";
				
			return array(
				'type'        => $this->icon('type',$info['info_type']),
				'status'        => $this->icon('status',$info['info_status']),
				'pri'         => lang($info['info_pri']),
				'subject'     => $subject,
				'des'           => $info['info_des'],
				'startdate'   => $phpgw->common->show_date($info['info_startdate'],$phpgw_info['user']['preferences']['common']['dateformat']),
				'enddate'     => $enddate,
				'owner'       => $owner,
				'datecreated' => $phpgw->common->show_date($info['info_datecreated'],$phpgw_info['user']['preferences']['common']['dateformat']),
				'responsible' => $responsible   );            
		}      

		function infoHeaders( $do_sort_header=0,$sort=0,$order=0) {
			global $phpgw,$phpgw_info;
			
			$headers['th_bg'] = $phpgw_info['theme']['th_bg'];
			
			$fields = array( 'type','status','urgency','subject','startdate','enddate','owner','datecreated','responsible' );
			for ( ;$f = $h = current($fields); $f = next($fields)) {
				$lang = lang(ucfirst( $f ));
				if ($do_sort_header) {
					$headers['sort_'.$f] = $phpgw->nextmatchs->show_sort_order($sort,'info_'.$f,$order,'/infolog/index.php',$lang);
				} else {
					$headers['lang_'.$f] = $lang;            
				}
			}
			return $headers;         
		}
		
		function debug( $str ) {
		/* $stdout = fopen('/tmp/log','a');
			fwrite( $stdout,"\r\n".$str );
			fclose( $stdout ); */
		}      
		
		function check_access( $info_id,$required_rights ) {
			global $phpgw_info;
			if ($info_id != $this->data['info_id']) {      // already loaded?
				$private_info = $this;                      // dont change our own internal data, dont use new as it changes $phpgw->db
				$info = $private_info->read($info_id);
			} else {
				$info = $this->data;
			}
			if (!$info || !$info_id)
				return False;
			
			$owner = $info['info_owner'];
			$user  = $phpgw_info['user']['account_id'];
			$access_ok = $owner == $user ||                // user has all rights
							 !!($this->grants[$owner] & $required_rights) &&    // ACL only on public entrys || $owner granted _PRIVATE
							 ($info['info_access'] == 'public' || !!($this->grants[$owner] & PHPGW_ACL_PRIVATE));   
							
			// $this->debug("check_access(info_id=$info_id (owner=$owner, user=$user),required_rights=$required_rights): access".($access_ok?"Ok":"Denied"));
			
			return $access_ok;         
		}
		
		function aclFilter($filter = 'none') {            // sql to be AND into a query to ensure ACL is respected (incl. _PRIVATE)
			global $phpgw_info;                           // filter: none    - list all entrys user have rights to see
																		//         private - list only his personal entrys (incl. those he is responsible for !!!)            
			if (isset($this->acl_filter[$filter]))
				return $this->acl_filter[$filter];         // used cached filter if found
				
			if (is_array($this->grants)) {
				while (list($user,$grant) = each($this->grants)) {
						// echo "<p>grants: user=$user, grant=$grant</p>";
					if ($grant & (PHPGW_ACL_READ|PHPGW_ACL_EDIT))
						$public_user_list[] = $user;
					if ($grant & PHPGW_ACL_PRIVATE)
						$private_user_list[] = $user;
				}
				if (count($private_user_list)) {               
					$has_private_access = 'info_owner IN ('.implode(',',$private_user_list).')';
				}            
			}
			$user = $phpgw_info['user']['account_id'];
			
			$filtermethod = " (info_owner=$user";         // user has all rights

			if ($filter == 'private') {                  // private means own entrys plus the one user is responsible for (and has rights to see)
				$filtermethod .= " OR info_responsible=$user AND (info_access='public'".($has_private_access?" OR $has_private_access":'').')';
			} else {      // none --> all entrys user has rights to see
				if ($has_private_access) {
					$filtermethod .= " OR $has_private_access";
				}
				if (count($public_user_list)) {         
					$filtermethod .= " OR (info_access='public' AND info_owner IN(" . implode(',',$public_user_list) . '))';
				}
			}
			$filtermethod .= ') ';
			
			// echo "<p>aclFilter('$filter')(user='$user') = '$filtermethod'</p>";
			
			return $this->acl_filter[$filter] = $filtermethod;   // cache the filter         
		}      
	
		function read($info_id) {                        // did _not_ ensure ACL, has to be done by the calling code
			if ($info_id <= 0 || $info_id != $this->data['info_id'] && 
										(!$this->db->query("select * FROM phpgw_infolog where info_id='$info_id'") ||   !$this->db->next_record())) 
			{
				$this->init( );
				return False;
			}
			if ($info_id != $this->data['info_id']) {      // data yet read in
				$this->data = $this->db->Record;
				
				if ($this->data['info_subject'] == (substr($this->data['info_des'],0,60).' ...')) {
					$this->data['info_subject'] = '';
				}
				if ($this->data['info_addr_id'] && $this->data['info_from'] == $this->addr2name( $this->readAddr( $this->data['info_addr_id'] ))) {
					$this->data['info_from'] = '';
				}            
			}         
			return $this->data;         
		}
		
		function init() {
			global $phpgw_info;
			
			$this->data = array( 'info_owner' => $phpgw_info['user']['account_id'],
										'info_pri'    => 'normal' );
		}      
				
		function delete($info_id) {                     // did _not_ ensure ACL, has to be done by the calling code
			global $phpgw_info;
			$this->db->query("delete FROM phpgw_infolog where info_id='$info_id' or info_id_parent='"
				. "$info_id' AND ((info_access='public' and info_owner != '"
				. $phpgw_info['user']['account_id'] . "') or (info_owner='"
				. $phpgw_info['user']['account_id'] . "'))" ,__LINE__,__FILE__);
				
			if ($this->data['info_id'] == $info_id)
				$this->init( );            
		}

		function write($values) {                        // did _not_ ensure ACL, has to be done by the calling code
			global $phpgw_info;
			if ($values['responsible'] && $values['status'] == 'offer') {
				$values['status'] = 'ongoing';   // have to match if not finished
			}
			if (!$values['info_id'] && !$values['owner']) {
				// $this->debug( "write(value[info_id]==0,values[owner]==0) --> owner set to user" );
				$values['owner'] = $phpgw_info['user']['account_id']; // user gets owner
			}
			if (!$values['info_id'] && !$values['datecreated'])
				$values['datecreated'] = time();               // set creation time
				
			if (!$values['subject']) $values['subject'] = substr($values['des'],0,60).' ...';
			
			if ($values['addr_id'] && !$values['from']) 
				$values['from'] = $this->addr2name( $this->readAddr( $values['addr_id'] ));

			while (list($key,$val) = each($values)) {
				$this->data['info_'.$key] = $val;   // update internal data
				switch ($key) {
					case 'info_id':
						break;         // later in where clause
					case 'des': case 'subject': case 'from': case 'addr':
						$val = addslashes($val);
					default:
						if ($query) $query .= ',';
						$query .= "info_$key='$val'";
				}
			}
			if ($values['info_id']) {
				$query = 'update phpgw_infolog set '.$query.' where info_id=\'' . $values['info_id'] .'\'';
			} else {
				$query = 'insert INTO phpgw_infolog set '.$query;
				/*
				 * need to set $this->data['info_id'] with assigned autoincrement id
				 */
			}                  
				
			//echo '<br>edit(): query: '.$query;
			
			$this->db->query($query,__LINE__,__FILE__);         
		}
	}
