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
	$phpgw->infolog = CreateObject('infolog.infolog');
	$html = $phpgw->infolog->html;

	$hidden_vars = array( 'sort' => $sort,'order' => $order,'query' => $query,'start' => $start,'filter' => $filter,
								 'cat_id' => $cat_id );

	if ((!isset($info_id) || !$info_id) && !$action)   {
			Header('Location: ' . $html->link('/infolog/index.php',$hidden_vars));
	}

	// check wether to write dates or not
	if ($selfortoday) {
		$startdate = time();      // startdate is today (checkbox is clicked)
	} else {
		if ($sday) {
			if ($sday && !$smonth) $smonth = date('m',time());
			if ($sday && !$syear)  $syear  = date('Y',time());
			if (! checkdate($smonth,$sday,$syear)) {
				$error[] = lang('You have entered an invalid starting date');
			} else {
				$startdate = mktime(12,0,0,$smonth, $sday, $syear);
			}
		} else {
			if (isset($sday))
				$startdate = 0;
		}         
	}

	// Check ending date
	if ($dur_days > 0)   {
		$enddate = mktime(12,0,0,date('m',$startdate), date('d',$startdate)+$dur_days, date('Y',$startdate));
	} else
		if ($eday) {
			if ($eday && !$emonth) $emonth = date('m',time());
			if ($eday && !$eyear)  $eyear  = date('Y',time());
			if (!checkdate($emonth,$eday,$eyear)) {
				$error[] = lang('You have entered an invalid ending date');
			} else {
				$enddate = mktime(12,0,0,$emonth,$eday,$eyear);
			}
		} else {
			if (isset($eday))
				$enddate = 0;
		}

	if ($save || $add) {
		if (strlen($des) >= 8000) {
			$error[] = lang('Description can not exceed 8000 characters in length');
		}
		if (!$subject && !$des) {
			$error[] = lang('You must enter a subject or a description');
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
				'type'      => $type,
				'from'      => $from,
				'addr'      => $addr,
				'addr_id'   =>	$id_addr,
				'proj_id'   =>	$id_project,
				'subject'   => $subject,
				'des'       => $des,
				'pri'       => $pri,
				'status'    => $status,
				'confirm'   => $confirm,
				'access'    => $access,
				'cat'       => $info_cat,
				'startdate' => $startdate,
				'enddate'   => $enddate,
				'info_id'   => $info_id,
				'id_parent' => $id_parent,
				'responsible' => $responsible
			));
	
			if (!$query_addr && !$query_project) {
				Header('Location: ' . $html->link('/infolog/index.php', $hidden_vars + array( 'cd' => 15 )));
			}
		}
	}
	$phpgw->infolog->read( $info_id );
	if ($info_id && $action == 'sp') {   // new SubProject
		if (!$phpgw->infolog->check_access($info_id,PHPGW_ACL_ADD)) {
			Header('Location: ' . $html->link('/infolog/index.php',$hidden_vars));
			$phpgw->common->phpgw_exit();
		}
		$parent = $phpgw->infolog->data;
		$phpgw->infolog->data['info_id'] = $info_id = 0;
		$phpgw->infolog->owner = $phpgw_info['user']['account_id'];
		$phpgw->infolog->data['info_id_parent'] = $parent['info_id'];
		if ($parent['info_type'] == 'task' && $parent['info_status'] == 'offer') {
			$phpgw->infolog->data['info_type'] = 'confirm';
			$phpgw->infolog->data['info_responsible'] = $parent['info_owner'];   // confirmation to parent
		}
		$phpgw->infolog->data['info_status'] = 'ongoing';
		$phpgw->infolog->data['info_confirm'] = 'not';
		$phpgw->infolog->data['info_subject'] = lang('Re:').' '.$parent['info_subject'];
		$phpgw->infolog->data['info_des'] = '';
	} else {
		if ($info_id && !$phpgw->infolog->check_access($info_id,PHPGW_ACL_EDIT)) {
			Header('Location: ' . $html->link('/infolog/index.php',$hidden_vars));
			$phpgw->common->phpgw_exit();
		}
	}      
	$id_parent = $phpgw->infolog->data['info_id_parent'];
	$common_hidden_vars =  $html->input_hidden( $hidden_vars +
															  array('info_id' => $info_id,'action' => $action,'id_parent' => $id_parent ));

	$phpgw->common->phpgw_header();
	echo parse_navbar();

	$t = CreateObject('phpgwapi.Template',PHPGW_APP_TPL); 
	$t->set_file(array('info_edit' => 'form.tpl'));
	  
	// ====================================================================
	// create two seperate blocks, addblock will be cut off from template
	// editblock contains the buttons and forms for edit
	// ====================================================================
	$t->set_block('info_edit', 'add', 'addhandle');
	$t->set_block('info_edit', 'edit', 'edithandle');
	$t->set_block('info_edit', 'subpro', 'subprohandle');
	  
	if (is_array($error)) {
		$t->set_var('error_list',$phpgw->common->error_list($error));
	}
	switch ($action) {
		case 'sp':
			$info_action = 'Info Log - New Subproject'; break;
		case 'new':
			$info_action = 'Info Log - New';
			if ($info_type && isset($phpgw->infolog->enums['type'][$info_type]))
				$phpgw->infolog->data['info_type'] = $info_type;
			break;
		default:
			$info_action = 'Info Log - Edit'; break;
	}
	$t->set_var('lang_info_action',lang($info_action) . ($query_addr ? ' - '.lang('Search for:')." '$query_addr'" : ''));
	$t->set_var($phpgw->infolog->setStyleSheet( ));
	$t->set_var('lang_category',lang('Category'));
	$t->set_var('lang_none',lang('None'));
	if (!isset($info_cat)) $info_cat = $phpgw->infolog->data['info_cat'];
	$t->set_var('cat_list',$phpgw->categories->formated_list('select','all',$info_cat,'True'));

	$t->set_var('actionurl',$phpgw->link('/infolog/edit.php'));
	$t->set_var('common_hidden_vars',$common_hidden_vars);

	// get an instance of select box class
	$sb = CreateObject('phpgwapi.sbox2');

	$t->set_var('lang_owner',lang('Owner'));
	$t->set_var('owner_info',$sb->accountInfo($phpgw->infolog->data['info_owner']));

	$t->set_var('lang_type',lang('Type'));
	if (!isset($type)) $type = $phpgw->infolog->data['info_type'];
	$t->set_var('type_list',$sb->getArrayItem('type',$type,$phpgw->infolog->enums['type']));

	$t->set_var('lang_prfrom', lang('From'));
	if (!isset($from)) $from = $phpgw->strip_html($phpgw->infolog->data['info_from']);
	$t->set_var('fromval', $from);

	$t->set_var('lang_praddr', lang('Phone/Email'));
	if (!isset($addr)) $addr = $phpgw->strip_html($phpgw->infolog->data['info_addr']);
	$t->set_var('addrval', $addr);

	if (!isset($id_project)) $id_project = $phpgw->infolog->data['info_proj_id'];
	$t->set_var($sb->getProject('project',$id_project,$query_project));

	if (!isset($id_addr)) $id_addr = $phpgw->infolog->data['info_addr_id'];
	$t->set_var($sb->getAddress('addr',$id_addr,$query_addr));
			
	$t->set_var('lang_prsubject', lang('Subject'));
	if (!isset($subject)) $subject = $phpgw->strip_html($phpgw->infolog->data['info_subject']);
	$t->set_var('subjectval', $subject);

	$t->set_var('lang_prdesc', lang('Description'));
	if (!isset($des)) $des = $phpgw->strip_html($phpgw->infolog->data['info_des']);
	$t->set_var('descval', $des);

	$t->set_var('lang_start_date',lang('Start Date'));
	if (!isset($startdate)) $startdate = $phpgw->infolog->data['info_startdate'];
	$t->set_var('start_select_date',$sb->getDate('syear','smonth','sday',$startdate));

	$t->set_var('lang_end_date',lang('End Date'));
	if (!isset($enddate)) $enddate = $phpgw->infolog->data['info_enddate'];
	$t->set_var('end_select_date',$sb->getDate('eyear','emonth','eday',$enddate));

	$t->set_var('lang_selfortoday',lang('Today'));
	$t->set_var('selfortoday',$html->checkbox('selfortoday',0));
	$t->set_var('lang_dur_days',lang('Duration'));
	$t->set_var('days',lang('days'));

	$t->set_var('lang_status',lang('Status'));
	if (!isset($status)) $status = $phpgw->infolog->data['info_status'];
	$t->set_var('status_list',$sb->getArrayItem('status',$status,$phpgw->infolog->enums['status']));

	$t->set_var('lang_priority',lang('Priority'));
	if (!isset($pri)) $pri = $phpgw->infolog->data['info_pri'];
	$t->set_var('priority_list',$sb->getArrayItem('pri',$pri,$phpgw->infolog->enums['priority']));

	$t->set_var('lang_confirm',lang('Confirm'));
	if (!isset($confirm)) $confirm = $phpgw->infolog->data['info_confirm'];
	$t->set_var('confirm_list',$sb->getArrayItem('confirm',$confirm,$phpgw->infolog->enums['confirm']));

	$t->set_var('lang_responsible',lang('Responsible'));
	if (!isset($responsible)) $responsible = $phpgw->infolog->data['info_responsible'];
	$t->set_var('responsible_list',$sb->getAccount('responsible',$responsible));

	$t->set_var('lang_access_type',lang('Private'));
	if (!isset($access)) $access = $phpgw->infolog->data['info_access'] == 'private';
	$t->set_var('access_list',$html->checkbox('access',$access));
	  
	$t->set_var('edit_button',$html->submit_button('save','Save'));
	 
	if (!$action && $phpgw->infolog->check_access($info_id,PHPGW_ACL_DELETE)) {
		$t->set_var('delete_button',$html->form_1button('delete','Delete',$hidden_vars,'/infolog/delete.php'));
	}
	$t->set_var('edithandle','');
	$t->set_var('addhandle','');
	$t->set_var('subprohandle','');
	$t->pfp('out','info_edit');
	$t->pfp('edithandle','edit');
	 
	$phpgw->common->phpgw_footer();
	echo parse_navbar_end();
