<?php
	/**************************************************************************\
	* phpGroupWare - Info Log                                                 *
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

	$phpgw_info['flags'] = array(
		'currentapp'              => 'infolog',
		'noheader'                => True,
		'nofooter'                => True,
		'nonavbar'                => True,
		'enable_categories_class' => True
	);
	include('../header.inc.php');

	if ((!isset($info_id) || !$info_id) && !$action)	{
			Header('Location: ' . $phpgw->link('/infolog/index.php',"sort=$sort&order=$order&query=$query&start=$start&".
																					  "filter=$filter&cat_id=$cat_id"));
	}

	$phpgw->infolog = createobject('infolog.infolog');

	if ($submit) {
		if (strlen($des) >= 8000) {
			$error[] = lang('Description can not exceed 8000 characters in length');
		}
		if (!$subject && !$des) {
			$error[] = lang('You must enter a subject or a description');
		}

		// check wether to write dates or not
		if ($selfortoday) {
			$startdate = time();		// startdate is today (checkbox is clicked)
		} else {
			if ($smonth || $sday || $syear) {
				if ($sday && !$smonth) $smonth = date('m',time());
				if ($sday && !$syear)  $syear  = date('Y',time());
				if (! checkdate($smonth,$sday,$syear)) {
					$error[] = lang('You have entered an invalid starting date');
				} else {
					$startdate = mktime(12,0,0,$smonth, $sday, $syear);
				}
			} else {
				$startdate = 0;
			}			
		}

		// Check ending date
		if ($dur_days > 0)	{
			$enddate = mktime(12,0,0,date('m',$startdate), date('d',$startdate)+$dur_days, date('Y',$startdate));
		} else
			if ($emonth || $eday || $eyear) {
				if ($eday && !$emonth) $emonth = date('m',time());
				if ($eday && !$eyear)  $eyear  = date('Y',time());
				if (!checkdate($emonth,$eday,$eyear)) {
					$error[] = lang('You have entered an invalid ending date');
				} else {
					$enddate = mktime(12,0,0,$emonth,$eday,$eyear);
				}
			} else {
				$enddate = 0;
			}

		if ($enddate < $startdate && $enddate && $startdate) {
			$error[] = lang('Ending date can not be before start date');
		}

		if ($access) {
			$access = 'private';
		} else {
			$access = 'public';
		}

		if (! is_array($error)) {
			$phpgw->infolog->write(array(
				'type'		=> $type,
				'from'		=> $from,
				'addr'		=> $addr,
				'addr_id'	=>	$addr_id,
				'proj_id'	=>	$proj_id,
				'subject'	=> $subject,
				'des'       => $des,
				'pri'       => $pri,
				'status'    => $status,
				'confirm'	=> $confirm,
				'access'    => $access,
				'cat'       => $info_cat,
				'startdate' => $startdate,
				'enddate'   => $enddate,
				'info_id'   => $info_id,
				'id_parent' => $id_parent,
				'responsible' => $responsible
			));
	
			if (!$addrsearch && !$projectsearch) {
				Header('Location: ' . $phpgw->link('/infolog/index.php', "cd=15&sort=$sort&order=$order&query=$query&".
						"start=$start&filter=$filter&cat_id=$cat_id"));
			}			
		}
	}
	$phpgw->infolog->read( $info_id );
	if ($info_id && $action == 'sp') {	// new SubProject
		if (!$phpgw->infolog->check_access($info_id,PHPGW_ACL_ADD)) {
			Header('Location: ' . $phpgw->link('/infolog/index.php',"sort=$sort&order=$order&query=$query&start=$start&filter=$filter"));
			$phpgw->common->phpgw_exit();
		}
		$parent = $phpgw->infolog->data;
		$phpgw->infolog->data['info_id'] = $info_id = 0;
		$phpgw->infolog->owner = $phpgw_info['user']['account_id'];
		$phpgw->infolog->data['info_id_parent'] = $parent['info_id'];
		if ($parent['info_type'] == 'task' && $parent['info_status'] == 'offer') {
			$phpgw->infolog->data['info_type'] = 'confirm';
			$phpgw->infolog->data['info_responsible'] = $parent['info_owner'];	// confirmation to parent
		}
		$phpgw->infolog->data['info_status'] = 'ongoing';
		$phpgw->infolog->data['info_confirm'] = 'not';
		$phpgw->infolog->data['info_subject'] = lang('Re:').' '.$parent['info_subject'];
		$phpgw->infolog->data['info_des'] = '';
	} else {
		if ($info_id && !$phpgw->infolog->check_access($info_id,PHPGW_ACL_EDIT)) {
			Header('Location: ' . $phpgw->link('/infolog/index.php',"sort=$sort&order=$order&query=$query&start=$start&filter=$filter"));
			$phpgw->common->phpgw_exit();
		}
	}		
	$common_hidden_vars =
	  '<input type="hidden" name="sort" value="' . $sort . '">'
	. '<input type="hidden" name="order" value="' . $order. '">'
	. '<input type="hidden" name="query" value="' . $query . '">'
	. '<input type="hidden" name="start" value="' . $start . '">'
	. '<input type="hidden" name="filter" value="' . $filter . '">'
	. '<input type="hidden" name="info_id" value="' . $info_id. '">'
	. '<input type="hidden" name="id_parent" value="' . ($id_parent = $phpgw->infolog->data['info_id_parent']). '">'	
	. '<input type="hidden" name="action" value="' . $action. '">';

	$phpgw->common->phpgw_header();
	echo parse_navbar();

	$phpgw->db->query("select * FROM infolog where info_id='$info_id'");
	$phpgw->db->next_record();

	$pri_selected[$phpgw->infolog->data['info_pri']] = ' selected';
	$status_selected[$phpgw->infolog->data['info_status']] = ' selected';

	$phpgw->template->set_file(array('info_edit' => 'form.tpl'));
     
	// ====================================================================
	// create two seperate blocks, addblock will be cut off from template
	// editblock contains the buttons and forms for edit
	// ====================================================================
	$phpgw->template->set_block('info_edit', 'add', 'addhandle');
	$phpgw->template->set_block('info_edit', 'edit', 'edithandle');
	$phpgw->template->set_block('info_edit', 'subpro', 'subprohandle');
     
	if (is_array($error)) {
		$phpgw->template->set_var('error_list',$phpgw->common->error_list($error));
	}
	switch ($action) {
		case 'sp':
			$info_action = 'Info Log - New Subproject'; break;
		case 'new':
			$info_action = 'Info Log - New'; break;
		default:
			$info_action = 'Info Log - Edit'; break;
	}
	$phpgw->template->set_var('lang_info_action',lang($info_action).($addrsearch?' - '.lang('Search for:')." '$addrsearch'":''));
	$phpgw->template->set_var($phpgw->infolog->setStyleSheet( ));
	$phpgw->template->set_var('lang_category',lang('Category'));
	$phpgw->template->set_var('lang_none',lang('None'));
	$phpgw->template->set_var('cat_list',$phpgw->categories->formated_list('select','all',$phpgw->infolog->data['info_cat'],'True'));

	$phpgw->template->set_var('actionurl',$phpgw->link('/infolog/edit.php'));
	$phpgw->template->set_var('common_hidden_vars',$common_hidden_vars);

	$phpgw->template->set_var('lang_owner',lang('Owner'));
	$phpgw->template->set_var('owner_info',$phpgw->infolog->accountInfo($phpgw->infolog->data['info_owner']));
	$phpgw->template->set_var('lang_type',lang('Type'));
	$phpgw->template->set_var('type_list',$phpgw->infolog->getEnum('type',$phpgw->infolog->data['info_type'],$phpgw->infolog->enums['type']));

	$phpgw->template->set_var('lang_prfrom', lang('From'));
	$phpgw->template->set_var('fromval', $phpgw->strip_html($phpgw->infolog->data['info_from']));
	$phpgw->template->set_var('lang_praddr', lang('Phone/Email'));
	$phpgw->template->set_var('addrval', $phpgw->strip_html($phpgw->infolog->data['info_addr']));

	$phpgw->template->set_var('lang_search', lang('Search'));
	$phpgw->template->set_var('lang_prproject', lang('Project'));
	$phpgw->template->set_var('lang_proj_prompt', lang('Pattern for Search in Projects'));
	
	if (($proj_id = $phpgw->infolog->data['info_proj_id']) || $projectsearch) {
		$projects = createobject('projects.projects');

		if ($projectsearch) {
			$projs = $projects->read_projects( 0,0,$projectsearch );
			if (count($projs)) {
				$project = '<select name="proj_id">';
				while (list( $key,$proj ) = each( $projs )) {
					$project .= '<option value="'.$proj['id'].'">'.$phpgw->strip_html($proj['title'])."\n";
				}
				$project .= '<option value="0">'.lang('none')."\n";
				$project .= '</select>';			
			} else {
				$project = lang( 'No entrys found for %1, try again ...',"'$projectsearch'" );
			}					
		} else {		// read name/company from addressbook entry info_addr_id
			list( $proj ) = $projects->read_single_project( $proj_id );
			if (count($proj)) {
				$project = $proj['title'].'<input type="hidden" name="proj_id" value="' . $proj_id . '">';
				$customer_id = $proj['customer'];
			}			
		}
	}
	if (!$project)
		$project = '<span class=note>'.lang('not set, use Button to search for').'</span>';
	$phpgw->template->set_var('project', $project);
	
	$phpgw->template->set_var('lang_praddrbook', lang('Addressbook'));
	$phpgw->template->set_var('lang_addr_prompt', lang('Pattern for Search in Addressbook'));
	
	if (($addr_id = $phpgw->infolog->data['info_addr_id']) || $addrsearch) {
		$contacts = createobject('phpgwapi.contacts');

		if ($addrsearch) {
			$addrs = $contacts->read( 0,0,'',$addrsearch,'','DESC','org_name,n_family,n_given' );
			if (count($addrs)) {
				$addrbook = '<select name="addr_id">';
				while (list( $key,$addr ) = each( $addrs )) {
					$addrbook .= '<option value="'.$addr['id'].'">'.$phpgw->infolog->addr2name( $addr )."\n";
				}
				$addrbook .= '<option value="0">'.lang('none')."\n";
				$addrbook .= '</select>';			
			} else {
				$addrbook = lang( 'No entrys found for %1, try again ...',"'$addrsearch'" );
			}					
		} else {		// read name/company from addressbook entry info_addr_id
			list( $addr ) = $contacts->read_single_entry( $addr_id );
			if (count($addr)) {
				$addrbook = $phpgw->infolog->addr2name( $addr ).'<input type="hidden" name="addr_id" value="' . $addr_id . '">';
			}			
		}
	}
	if (!$addrbook)
		$addrbook = '<span class=note>'.lang('not set, use Button to search for').'</span>';
		
	$phpgw->template->set_var('addrbook', $addrbook);
			
	$phpgw->template->set_var('lang_prsubject', lang('Subject'));
	$phpgw->template->set_var('subjectval', $phpgw->strip_html($phpgw->infolog->data['info_subject']));
	$phpgw->template->set_var('lang_prdesc', lang('Description'));
	$phpgw->template->set_var('descval', $phpgw->strip_html($phpgw->infolog->data['info_des']));

	// get month/day/year fields for startdate and enddate
	if ($phpgw->infolog->data['info_startdate'] == 0) {
		$sday = $smonth = $syear = 0;
	} else {
		$sday = date('d',$phpgw->infolog->data['info_startdate']);
		$smonth = date('m',$phpgw->infolog->data['info_startdate']);
		$syear = date('Y',$phpgw->infolog->data['info_startdate']);
	}

	if ($phpgw->infolog->data['info_enddate'] == 0) {
		$eday = $emonth = $eyear = 0;
	} else {
		$eday = date('d',$phpgw->infolog->data['info_enddate']);
		$emonth = date('m',$phpgw->infolog->data['info_enddate']);
		$eyear = date('Y',$phpgw->infolog->data['info_enddate']);
	}
     
	// get an instance of select box class
	$sm = CreateObject('phpgwapi.sbox');
	  
	$phpgw->template->set_var('lang_start_date',lang('Start Date'));
	$phpgw->template->set_var('start_select_date',$phpgw->common->dateformatorder($sm->getYears('syear',$syear,$syear<date('Y')?$syear:date('Y')-2),
																											$sm->getMonthText('smonth', $smonth),$sm->getDays('sday', $sday)));
	$phpgw->template->set_var('lang_end_date',lang('End Date'));
	$phpgw->template->set_var('end_select_date',$phpgw->common->dateformatorder($sm->getYears('eyear', $eyear,$eyear<date('Y')?$eyear:date('Y')-2),
																										 $sm->getMonthText('emonth', $emonth),$sm->getDays('eday', $eday)));
	$phpgw->template->set_var('lang_selfortoday',lang('Today'));
	$phpgw->template->set_var('selfortoday','<input type="checkbox" name="selfortoday" value="True">&nbsp;');
	$phpgw->template->set_var('lang_dur_days',lang('Duration'));
	$phpgw->template->set_var('days',lang('days'));

	$phpgw->template->set_var('lang_status',lang('Status'));
	$phpgw->template->set_var('status_list',$phpgw->infolog->getEnum('status',$phpgw->infolog->data['info_status'],$phpgw->infolog->enums['status']));

	$phpgw->template->set_var('lang_priority',lang('Priority'));
	$phpgw->template->set_var('priority_list',$phpgw->infolog->getEnum('pri',$phpgw->infolog->data['info_pri'],$phpgw->infolog->enums['priority']));

	$phpgw->template->set_var('lang_confirm',lang('Confirm'));
	$phpgw->template->set_var('confirm_list',$phpgw->infolog->getEnum('confirm',$phpgw->infolog->data['info_confirm'],$phpgw->infolog->enums['confirm']));

	$phpgw->template->set_var('lang_responsible',lang('Responsible'));
	$phpgw->template->set_var('responsible_list',$phpgw->infolog->getAccount('responsible',$phpgw->infolog->data['info_responsible']));

	$phpgw->template->set_var('lang_access_type',lang('Private'));
	$phpgw->template->set_var('access_list', '<input type="checkbox" name="access" value="True"' . ($phpgw->infolog->data['info_access'] == 'private'?' checked':'') . '>');
     
	$phpgw->template->set_var('delete_action',$phpgw->link('/infolog/delete.php'));

	$phpgw->template->set_var('edit_button','<input type="submit" name="submit" value="' . lang('Save') . '">');
    
	if (!$action && $phpgw->infolog->check_access($info_id,PHPGW_ACL_DELETE)) {
		$phpgw->template->set_var('delete_button','<input type="submit" name="delete" value="' . lang('Delete') . '">');
	}
	$phpgw->template->set_var('edithandle','');
	$phpgw->template->set_var('addhandle','');
	$phpgw->template->set_var('subprohandle','');
	$phpgw->template->pfp('out','info_edit');
	$phpgw->template->pfp('edithandle','edit');
    
	// I would like to have it calculate the amount of days and drop it in days from now.
    
	$phpgw->common->phpgw_footer();
	echo parse_navbar_end();
