<?php
	/**************************************************************************\
	* phpGroupWare - Info Log                                                  *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* based on todo written by Joseph Engo <jengo@phpgroupware.org>            *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$phpgw_info['flags'] = array(
		'currentapp'              => 'info', 
		'enable_nextmatchs_class' => True,
		'enable_categories_class' => True
	);
	include('../header.inc.php');

	$phpgw->info = createobject('info.info');
	$db = $phpgw->db;
	$db2 = $phpgw->db;

	$phpgw->template = new Template($phpgw->common->get_tpl_dir('info'));
	$phpgw->template->set_file(array( 'info_list_t' => 'list.tpl' ));
	$phpgw->template->set_block('info_list_t','info_list','list');

	$grants = $phpgw->acl->get_grants('info');
  
	$common_hidden_vars =
			'<input type="hidden" name="sort" value="' . $sort . '">'
		 . '<input type="hidden" name="order" value="' . $order . '">'
		 . '<input type="hidden" name="query" value="' . $query . '">'
		 . '<input type="hidden" name="start" value="' . $start . '">'
		 . '<input type="hidden" name="filter" value="' . $filter . '">'
		 . '<input type="hidden" name="cat_id" value="' . $cat_id . '">';
 
	if ($action)
		$common_hidden_vars	.= '<input type="hidden" name="action" value="'.$action.'">';
	
	switch ($action) {
		case 'sp':		// Sub-List
			$common_hidden_vars	.= '<input type="hidden" name="info_id" value="' . $info_id . '">';
			$phpgw->template->set_var(lang_info_action,lang('Info Log - Subprojects from'));
			break;
		case 'proj':
			$common_hidden_vars	.= '<input type="hidden" name="proj_id" value="' . $proj_id . '">';
			$proj = $phpgw->info->readProj($proj_id);
			$phpgw->template->set_var(lang_info_action,lang('Info Log').' - '.$proj['title']);
			break;
		case 'addr':
			$common_hidden_vars	.= '<input type="hidden" name="addr_id" value="' . $addr_id . '">';
			$addr = $phpgw->info->readAddr($addr_id);
			$phpgw->template->set_var(lang_info_action,lang('Info Log').' - '.$phpgw->info->addr2name($addr));
			break;
		default:
			$phpgw->template->set_var(lang_info_action,lang('Info Log'));
			break;
	}	
	$phpgw->template->set_var($phpgw->info->setStyleSheet( ));
	$phpgw->template->set_var(actionurl,$phpgw->link('/info/edit.php?action=new'));
	$phpgw->template->set_var('cat_form',$phpgw->link('/info/index.php'));
	$phpgw->template->set_var('lang_category',lang('Category'));
	$phpgw->template->set_var('lang_all',lang('All'));
	$phpgw->template->set_var('lang_select',lang('Select'));
	$phpgw->template->set_var('categories',$phpgw->categories->formated_list('select','all',$cat_id,'True'));
	$phpgw->template->set_var(common_hidden_vars,$common_hidden_vars);

	// ===========================================
	// list header variable template-declarations
	// ===========================================
	$phpgw->template->set_var( $phpgw->info->infoHeaders( 1,$sort,$order ));
	$phpgw->template->set_var(h_lang_sub,lang('Sub'));
	$phpgw->template->set_var(h_lang_action,lang('Action'));
	// -------------- end header declaration -----------------

	if (! $start) {
		$start = 0;
	}

	if ($order) {
		$ordermethod = 'order by ' . $order . ' ' . $sort;
	} else {
		$ordermethod = 'order by info_datecreated desc';		// newest first
	}
	if (!$filter) 	{
		$filter = 'none';
	}
	$filtermethod = $phpgw->info->aclFilter($filter);
	
	if ($cat_id) {
		$filtermethod .= " AND info_cat='$cat_id' "; 
	}
	if ($action == 'addr') $filtermethod .= " AND info_addr_id=$addr_id ";
	if ($action == 'proj') $filtermethod .= " AND info_proj_id=$proj_id ";
														// we search in _from, _subject and _des for $query
	if ($query) $sql_query = "AND (info_from like '%$query%' OR info_subject like '%$query%' OR info_des like '%$query%') ";

	$pid = 'AND info_id_parent='.($action == 'sp' ? $info_id : 0);  
	if ($phpgw->info->listChilds && $action != 'sp')
	   $pid = '';
	
	$db->query("SELECT COUNT(*) FROM info WHERE $filtermethod $pid $sql_query",__LINE__,__FILE__);
	$db->next_record();
	$total = $db->f(0);

	if ($total <= $start) $start = 0;
	
	if ($total > $phpgw_info['user']['preferences']['common']['maxmatchs']) {
		$to = $start + $phpgw_info['user']['preferences']['common']['maxmatchs']; if ($to > $total) $to = $total;
		$total_matchs = lang('showing x - x of x',($start + 1),$to,$total);
	} else {
		$total_matchs = lang('showing x',$total);
	}
  	$phpgw->template->set_var('total_matchs',$total_matchs);

	// ==========================================
	// project description if subprojectlist
	// ==========================================


	$phpgw->template->set_block('info_list_t','projdetails','projdetailshandle');
	
	switch ($action) {
		case 'sp':		// details of parent
			$phpgw->template->set_var( $phpgw->info->infoHeaders(  ));
			$phpgw->template->set_var( $phpgw->info->formatInfo( $info_id ));
			$phpgw->template->parse('projdetailshandle','projdetails',True);
			break;
		case 'addr':
			break;
		case 'proj':
			break;
	}

	// ===========================================
	// nextmatch variable template-declarations
	// ===========================================
	$next_matchs = $phpgw->nextmatchs->show_tpl('/info/index.php',$start,$total,
						 "&order=$order&filter=$filter&sort=$sort&query=$query&action=$action&info_id=$info_id&cat_id=$cat_id",
						 '95%',$phpgw_info['theme']['th_bg']);
	$phpgw->template->set_var(next_matchs,$next_matchs);
	// ---------- end nextmatch template --------------------

	$limit = $db->limit($start);

	$db->query($q="SELECT * FROM info WHERE $filtermethod $pid $sql_query $ordermethod $limit",__LINE__,__FILE__);
	
	while ($db->next_record()) {
		// ========================================
		// check if actual project has subprojects
		// ========================================
		$db2->query("select count(*) as cnt from info where info_id_parent=" .$db->f('info_id'),__LINE__,__FILE__);
		$db2->next_record();
		if ($db2->f('cnt') > 0) {
			$subproact = 1;
		} else {
			$subproact = 0;
		}
		// -----------------------------------------

		$phpgw->nextmatchs->template_alternate_row_color(&$phpgw->template);

		$phpgw->template->set_var( $phpgw->info->formatInfo( $db->Record,$proj_id,$addr_id ));

		if ($phpgw->info->check_access($db->f('info_id'),PHPGW_ACL_EDIT)) {
			$phpgw->template->set_var('edit','<a href="' . $phpgw->link('/info/edit.php','info_id=' . $db->f('info_id')
				. '&sort=' . $sort . '&order=' . $order . '&query=' . $query . '&start=' . $start . '&filter=' . $filter)
				. '">' . $phpgw->info->icon('action','edit') . '</a>');
		} else {
			$phpgw->template->set_var('edit','');
		}

		if ($phpgw->info->check_access($db->f('info_id'),PHPGW_ACL_DELETE)) {
			$phpgw->template->set_var('delete','<a href="' . $phpgw->link('/info/delete.php','info_id=' . $db->f('info_id')
				. '&sort=' . $sort . '&order=' . $order . '&query=' . $query . '&start=' . $start . '&filter=' . $filter)
				. '">' . $phpgw->info->icon('action','delete') . '</a>');
		} else {
			$phpgw->template->set_var('delete','');
		}
		$phpgw->template->set_var('subadd', '');		// defaults no icons
		$phpgw->template->set_var('viewsub', '');
		$phpgw->template->set_var('viewparent', '');

		if ($subproact > 0) {	// if subprojects exist, display VIEW SUB icon
			$phpgw->template->set_var('viewsub', '<a href="' . $phpgw->link('/info/index.php','info_id=' . $db->f('info_id')
					. "&filter=$filter&action=sp") . '">' . $phpgw->info->icon('action','view') . '</a>');
		} else {			  			// else display ADD SUB-Icon
			if ($phpgw->info->check_access($db->f('info_id'),PHPGW_ACL_ADD)) {
				 $phpgw->template->set_var('subadd', '<a href="' . $phpgw->link('/info/edit.php','info_id=' . $db->f('info_id') .
											  '&filter=' . $filter . '&action=sp') . '">' . $phpgw->info->icon('action','new') . '</a>');
			}			
      }	 							// if parent --> display VIEW SUBS of Parent
		if ($db->f('info_id_parent') && $action != 'sp') {
			$phpgw->template->set_var('viewparent', '<a href="' . $phpgw->link('/info/index.php','info_id=' . $db->f('info_id_parent') .
					"&filter=$filter&action=sp") . '">' . $phpgw->info->icon('action','parent') . '</a>');
		}
		
		$phpgw->template->parse('list','info_list',True);
		// -------------- end record declaration ------------------------
	}

	// =========================================================
	// back2project list href declaration for subproject list
	// =========================================================
      
	if ($action) {
		$phpgw->template->set_var('lang_back2projects', '<br><a href="' . 
											$phpgw->link('/info/index.php',"filter=$filter").
											'">'.lang('Back to Projectlist').'</a>');
	}

	// get actual date and year for matrixview arguments
/*	$year = date('Y');
	$month = date('m');
	$phpgw->template->set_var('lang_matrixviewhref', '<br><a href="' . $phpgw->link('/info/graphview.php',"month=$month&year=$year&filter=$filter").
																	 '">'.lang('View Matrix of actual Month').'</a>'); */
	// ============================================
	// template declaration for Add Form
	// ============================================

	$phpgw->template->set_var(lang_add,lang('Add'));
	$phpgw->template->pfp('out','info_list_t',true);

	// -------------- end Add form declaration ------------------------

	$phpgw->common->phpgw_footer();
?>
