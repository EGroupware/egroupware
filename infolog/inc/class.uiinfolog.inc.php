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

	class uiinfolog 	// UI - User Interface - HTML 
	{
		var $public_functions = array
		(
			'get_list'	=> True,
			'view'	=>	True,
			'add'		=>	True,
			'edit'	=> True,
			'delete'	=> True,
			'preferences' => True
		);
		var $icons;

		function uiinfolog( )
		{
			global $phpgw;

			$this->bo = CreateObject('infolog.boinfolog');

			$this->icons = array(
				'type' => array(
					'task' => 'task.gif',       'task_alt' => 'Task',
					'phone' => 'phone.gif',      'phone_alt' => 'Phonecall',
					'note' => 'note.gif',      'note_alt' => 'Note',
					'confirm' => 'confirm.gif','confirm_alt' => 'Confirmation',
					'reject' => 'reject.gif',   'reject_alt' => 'Reject',
					'email' => 'email.gif',      'email_alt' => 'Email' ),
				'action' => array(
					'new' => 'new.gif',         'new_alt' => 'Add Sub',
					'view' => 'view.gif',      'view_alt' => 'View Subs',
					'parent' => 'parent.gif',   'parent_alt' => 'View other Subs',
					'edit' => 'edit.gif',      'edit_alt' => 'Edit',
					'delete' => 'delete.gif',   'delete_alt' => 'Delete' ),
				'status' => array(
					'billed' => 'billed.gif',   'billed_alt' => 'billed',
					'done' => 'done.gif',      'done_alt' => 'done',
					'will-call' => 'will-call.gif', 'will-call_alt' => 'will-call',
					'call' => 'call.gif',      'call_alt' => 'call',
					'ongoing' => 'ongoing.gif','ongoing_alt' => 'ongoing',
					'offer' => 'offer.gif',      'offer_alt' => 'offer' )
			);

			$this->filters = array(
				'none'				=>	lang('no Filter'),
				'done'				=>	lang('done'),
				'own'					=>	lang('own'),
				'own-open-today'	=>	lang('own open'),
				'own-open-overdue'=>	lang('own overdue'),
				'own-upcoming'		=>	lang('own upcoming'),
				'open-today'		=>	lang('open'),
				'open-overdue'		=>	lang('overdue'),
				'upcoming'			=>	lang('upcoming')
			);

			$this->html = CreateObject('infolog.html');
			$this->template = CreateObject('phpgwapi.Template',
													 $phpgw->common->get_tpl_dir('infolog'));
			$this->categories = CreateObject('phpgwapi.categories');
			$this->nextmatchs = CreateObject('phpgwapi.nextmatchs');
		}
				
		function menuaction($action = 'get_list',$app='infolog')
		{
			return array( 'menuaction' => "$app.ui$app.$action" );
		}

		function icon($cat,$id,$status='')
		{
			global $phpgw,$DOCUMENT_ROOT;

			if (!$status || !($icon = $this->icons[$cat][$id.'_'.$status])) {
				$icon = $this->icons[$cat][$id];
			}
			if ($icon)
			{
				$fname = $phpgw->common->get_image_dir() . '/' . $icon; 
				
				if (!is_readable($fname))
				{
					$icon = False;      // echo "<br>Can't read '$fname' !!!";
				}
				else
				{            
					$icon = $phpgw->common->get_image_path() . '/' . $icon;
				}            
			}
			if (!$status || !($alt = $this->icons[$cat][$id.'_'.$status.'_alt']))
			{
				if (!($alt = $this->icons[$cat][$id.'_alt']))
				{
					$alt = $id;
				}
			}
   		return ($icon ? "<img src='$icon' alt='" : '') . lang($alt) .
					 ($icon ? '\' border=0>' : '');
		}
		
		function setStyleSheet( )
		{
			global $phpgw;

			return array (
				'info_css' => '<link rel="stylesheet" type="text/css" href="'.
									str_replace( '/images','',
									$phpgw->common->get_image_path()).'/info.css">'
			);
		}
		
		/*
		 * $info: info_id or array with one row form info-db
		 * no Proj.Info if proj_id == p_id / no Addr.Info if addr_id == a_id
		 */
		function formatInfo($info=0,$p_id=0,$a_id=0)
		{
			global $phpgw,$phpgw_info;
			
			if (!is_array($info) && (!$info ||
				 !is_array($info=$this->bo->read($info))))
			{
				$info = $this->bo->so->data;
			}
			$done = $info['info_status'] == 'done' ||
					  $info['info_status'] == 'billed';   

			$css_class = $info['info_pri'].($done ? '_done' : '');
			$subject = "<span class=$css_class>";
			
			if ($p_id != ($proj_id = $info['info_proj_id']) &&
			    $proj = $this->bo->readProj($proj_id))
			{
				$subject .= $this->html->bold($this->html->a_href($proj['title'],
									'/index.php',$this->menuaction()+
								 	array( 'filter' => $filter,'action' => 'proj',
											 'proj_id' => $proj_id )));
			}
			if ($a_id != ($addr_id = $info['info_addr_id']) &&
				 $addr = $this->bo->readAddr($addr_id))
			{
				if ($proj) $subject .= '<br>';
				$addr = $this->bo->addr2name( $addr );
				$subject .= $this->html->bold($this->html->a_href($addr,'/index.php',
									array( 'menuaction' => 'addressbook.uiaddressbook.view',
											 'ab_id' => $addr_id)
									/* $this->menuaction() + array( 'filter' => $filter,'action' => 'addr',
																			  'addr_id' => $addr_id )*/
								));
			}
			if (($from = $info['info_from']) && (!$addr || !strstr($addr,$from)))
			{
				if ($addr) $subject .= '<br>';
				$subject .= '<b>'.$from.'</b>';
			}
			if ($info['info_addr'])
			{
				if ($addr || $from) $subject .= ': ';
				$subject .= $info['info_addr'];
			}
			if ($proj || $addr || $from || $info['info_addr'])
			{
				$subject .= '<br>';
			}
			$subject .= '<b>';
			$subject .= $info['info_subject'] ? $info['info_subject'] :
							substr($info['info_des'],0,60).' ...';
			$subject .= '</b></span>';

			if (!$info['info_enddate'])
			{
				$enddate = '&nbsp;';
			}
			else
			{
				$enddate = $phpgw->common->show_date($info['info_enddate'],
						$phpgw_info['user']['preferences']['common']['dateformat']);
				
				if (!$done && $info['info_enddate'] < time()+(60*60)*$phpgw_info['user']['preferences']['common']['tz_offset'])
				{
					$enddate = "<span class=overdue>$enddate</span>";
				}
			}
			if (!($responsible = $info['info_responsible']) &&
				 $info['info_status'] == 'offer')
			{
				$responsible = $this->icon('status','offer');
			}
			else
			{
				$responsible = $this->bo->accountInfo($responsible);         
			}         
			$owner = $this->bo->accountInfo($info['info_owner']);
			if ($info['info_access'] == 'private')
			{
				$owner = "<span class=private>$owner</span>";
			}
			return array(
				'type'        => $this->icon('type',$info['info_type']),
				'status'      => $this->icon('status',$info['info_status']),
				'pri'         => lang($info['info_pri']),
				'subject'     => $subject,
				'des'         => nl2br($info['info_des']),
				'startdate'   => $phpgw->common->show_date($info['info_startdate'],
						$phpgw_info['user']['preferences']['common']['dateformat']),
				'enddate'     => $enddate,
				'owner'       => $owner,
				'datecreated' => $phpgw->common->show_date($info['info_datecreated'],
						$phpgw_info['user']['preferences']['common']['dateformat']),
				'responsible' => $responsible
			);            
		}

		function infoHeaders( $do_sort_header=0,$sort=0,$order=0,$cat_id=0)
		{
			global $phpgw,$phpgw_info;
			
			$headers['th_bg'] = $phpgw_info['theme']['th_bg'];
			
			$fields = array(
				'type'		=> 'Type',
				'status'		=> 'Status',
				'urgency'	=>	'Urgency',
				'subject'	=> 'Subject',
				'startdate'	=>	'Startdate',
				'enddate'	=>	'Enddate',
				'owner'		=>	'Owner',
				'datecreated' => 'last changed',	// is MODIFICATION date now
				'responsible' => 'Responsible'
			);
			while (list($f,$lang) = each($fields))
			{
				$lang = lang($lang);
				$headers['lang_'.$f] = $do_sort_header ? $this->nextmatchs->show_sort_order($sort,'info_'.$f,$order,'/index.php',$lang,"&cat_id=$cat_id") : $lang;
			}
			return $headers;         
		}
		
		function get_referer( )
		{
			global $phpgw_info,$HTTP_REFERER,$referer;

			if (!$referer)
				$referer = $HTTP_REFERER;

			$url = parse_url(str_replace($phpgw_info['server']['webserver_url'],'',
										  		  $referer));
			$referer = $url['path'];

			if ($url['query'])
			{
				$referer .= '?'.$url['query'];
			}
			return $referer;
		}

	 	function get_list($for_include=0)
		{
			global $phpgw,$phpgw_info;
			global $cat_filter,$cat_id,$sort,$order,$query,$start,$filter;
			global $action,$addr_id,$proj_id,$info_id;

			if (!$for_include) {
				$phpgw->common->phpgw_header();
				echo parse_navbar();
			}
			$t = $this->template; $html = $this->html;

			$t->set_file(array( 'info_list_t' => 'list.tpl' ));
			$t->set_block('info_list_t','info_list','list');

			if ($cat_filter) $cat_id = $cat_filter;

			if (!$filter)
			{
				$filter = $phpgw_info['user']['preferences']['infolog']['defaultFilter'];
			}

			$hidden_vars = array(
				'sort' => $sort,'order' => $order,'query' => $query,
				'start' => $start,'filter' => $filter,'cat_id' => $cat_id
			);

			global $PHP_SELF,$QUERY_STRING;	// set referer for form
			$referer = $PHP_SELF.($QUERY_STRING ? '?'.$QUERY_STRING : '');

			$action_vars = array( 'action' => 'new' );
			switch ($action)
			{
				case 'sp':        // Sub-List
					$action_vars = array('action'=>'sp','info_id'=>$info_id);
					$t->set_var(lang_info_action,lang('InfoLog - Subprojects from'));
					break;
			  case 'proj':
					$action_vars += array( 'id_project' => $proj_id,
												  'proj_id' => $proj_id);
					$proj = $this->bo->readProj($proj_id);
					$t->set_var(lang_info_action,lang('InfoLog').' - '.
									$proj['title']);
					break;
			  case 'addr':
					$action_vars += array( 'id_addr' => $addr_id,
												  'addr_id' => $addr_id );
					$addr = $this->bo->readAddr($addr_id);
					$t->set_var(lang_info_action,lang('InfoLog').' - '.
									$this->bo->addr2name($addr));
					break;
			  default:
					if ($filter && $filter != 'none')
					{
						$filter_name = ': '.$this->filters[ $filter ];
					}
					$t->set_var(lang_info_action,lang('InfoLog').$filter_name);
					break;
			}
			$t->set_var($this->setStyleSheet( ));

			if (!$for_include)
			{
				$t->set_var('add_button',$html->form_1button('add_button','Add',
							$hidden_vars+$action_vars+array('referer'=>$referer),
							'/index.php',$this->menuaction('edit')));
			}
			$add_icons = lang('Add').':';

			$icons = array( 'task'	=> True,
								 'phone'	=> True,
								 'note'	=>	True
							  );

			while (list($type,$nul) = each($icons))
			{
				$add_icons .= $html->a_href($this->icon('type',$type),'/index.php',
						 $this->menuaction('edit')+$action_vars+array('type'=>$type));
			}
			$t->set_var('add_icons',$add_icons);

			// ===========================================
			// list header variable template-declarations
			// ===========================================
			$t->set_var( $this->infoHeaders( !$for_include,$sort,$order,$cat_id ));
			$t->set_var(h_lang_sub,lang('Sub'));
			$t->set_var(h_lang_action,lang('Action'));
			// -------------- end header declaration -----------------

			$ids = $this->bo->readIdArray($order,$sort,$filter,$cat_id,$query,
								  					$action,$addr_id,$proj_id,$info_id,
													$ordermethod,$start,$total);

			$maxmatchs = $phpgw_info['user']['preferences']['common']['maxmatchs'];
			if ($total > $maxmatchs)
			{
			  $to = $start + $maxmatchs;
			  if ($to > $total) $to = $total;
			  $total_matchs = lang('showing x - x of x',($start + 1),$to,$total);
			}
			else
			{
			  $total_matchs = lang('showing x',$total);
			}
			$t->set_var('total_matchs',$total_matchs);

			// ==========================================
			// project description if subprojectlist
			// ==========================================

			$t->set_block('info_list_t','projdetails','projdetailshandle');

			switch ($action)
			{
			  case 'sp':        // details of parent
					$t->set_var( $this->infoHeaders(  ));
					$t->set_var( $this->formatInfo( $info_id ));
					$t->parse('projdetailshandle','projdetails',True);
					break;
			  case 'addr':
			  		$nm_extra = "&addr_id=$addr_id";
					break;
			  case 'proj':
			  		$nm_extra = "&proj_id=$proj_id";
					break;
			}

			if (!$for_include || $total > $maxmatchs ||
				 $query || $cat_id)
			{
			// ===========================================
			// nextmatch variable template-declarations
			// ===========================================
				if (!($q_string = strstr($QUERY_STRING,'menuaction')))
				{
					$q_string = "menuaction=infolog.uiinfolog.get_list";
				}
				if (!strstr($q_string,'cat_id'))
				{
					$q_string .= "&cat_id=$cat_id";
				}
				while (list($f,$lang) = each ($this->filters))
				{
					$filters[] = array( $f,$lang );
				}
				$next_matchs = $this->nextmatchs->show_tpl('/index.php',$start,
							$total,'&'.$q_string,'95%',$phpgw_info['theme']['th_bg'],
							0,$filters,1,0,$cat_id,'cat_id');

				$t->set_var('next_matchs',$next_matchs);

				if ($total > $maxmatchs)
				{
					$t->set_var('next_matchs_end',$next_matchs);
				}
			}

			// ---------- end nextmatch template --------------------

			while (list($id,$parent) = each($ids))
			{
				$subproact = $this->bo->anzSubs($id);

				$this->nextmatchs->template_alternate_row_color(&$t);

				$t->set_var( $this->formatInfo( $id,$proj_id,$addr_id ));

				if ($this->bo->check_access($id,PHPGW_ACL_EDIT))
				{
					$t->set_var('edit',$html->a_href(
						$this->icon('action','edit'),'/index.php',
						$hidden_vars+array('info_id' => $id)+
						$this->menuaction('edit')));
				}
				else
				{
					$t->set_var('edit','');
				}

				if ($this->bo->check_access($id,PHPGW_ACL_DELETE))
				{
					$t->set_var('delete',$html->a_href(
									$this->icon('action','delete'),'/index.php',
									$hidden_vars+array('info_id' => $id)+
									$this->menuaction('delete')));
			  }
			  else
			  {
					$t->set_var('delete','');
			  }
			  $t->set_var('subadd', '');        // defaults no icons
			  $t->set_var('viewsub', '');
			  $t->set_var('viewparent', '');

			  if ($subproact > 0)   // if subprojects exist, display VIEW SUB icon
			  {
					$t->set_var('viewsub', $html->a_href(
						$this->icon('action','view'),'/index.php',
						$this->menuaction()+array( 'info_id' => $id,
						'filter' => $filter, 'action' => 'sp')));
			  }
			  else                           // else display ADD SUB-Icon
			  {
					if ($this->bo->check_access($id,PHPGW_ACL_ADD))
					{
						$t->set_var('subadd',$html->a_href(
							$this->icon('action','new'),'/index.php',
							$this->menuaction('edit')+
							array('info_id' => $id,'filter' => $filter,
									'action' => 'sp')));
					}            
				}                       // if parent --> display VIEW SUBS of Parent
				if ($parent && $action != 'sp')
				{
					$t->set_var('viewparent',$html->a_href(
						$this->icon('action','parent'),'/index.php',
						$this->menuaction()+
						array('info_id' => $parent,
								'filter' => $filter,'action' => 'sp')));
			  }
			  
			  $t->parse('list','info_list',True);
			  // -------------- end record declaration ------------------------
			}

			// =========================================================
			// back2project list href declaration for subproject list
			// =========================================================

			if ($action && !$for_include)
			{
				$t->set_var('back2projects',
						$html->form_1button('back','Back to Projectlist','',
						'/index.php',$this->menuaction()+array('filter'=>$filter)));
			}

			$t->pfp('out','info_list_t',true);
		}

		function edit( )
		{
			global $phpgw,$phpgw_info;
			global $cat_id,$sort,$order,$query,$start,$filter;
			global $action,$info_id,$save,$add,$query_addr,$query_project;
			// formular fields
			global $selfortoday,$sday,$smonth,$syear;
			global $dur_days,$eday,$emonth,$eyear;
			global $type,$from,$addr,$id_addr,$id_project,$subject,$des,$access;
			global $pri,$status,$confirm,$info_cat,$id_parent,$responsible;

			$t = $this->template; $html = $this->html;

			$hidden_vars = array('sort' => $sort,'order' => $order,
										'query' => $query,'start' => $start,
										'filter' => $filter,'cat_id' => $cat_id );

			$referer = $this->get_referer();

			if ((!isset($info_id) || !$info_id) && !$action)
			{
				Header('Location: ' . 
						 $html->link('/index.php',$hidden_vars+$this->menuaction()));
			}

			// check wether to write dates or not
			if ($selfortoday)
			{
				$startdate = time();     // startdate is today (checkbox is clicked)
			}
			else
			{
				if ($sday)
				{
					if ($sday && !$smonth) $smonth = date('m',time());
					if ($sday && !$syear)  $syear  = date('Y',time());

					if (! checkdate($smonth,$sday,$syear))
					{
						$error[] = lang('You have entered an invalid starting date');
					}
					else
					{
						$startdate = mktime(12,0,0,$smonth, $sday, $syear);
					}
				}
				else
				{
					if (isset($sday)) $startdate = 0;
				}         
			}

			// Check ending date
			if ($dur_days > 0)
			{
				$enddate = mktime(12,0,0,date('m',$startdate),
								date('d',$startdate)+$dur_days, date('Y',$startdate));
			}
			else
			{
				if ($eday)
				{
					if ($eday && !$emonth) $emonth = date('m',time());
					if ($eday && !$eyear)  $eyear  = date('Y',time());

					if (!checkdate($emonth,$eday,$eyear))
					{
						$error[] = lang('You have entered an invalid enddate');
					}
					else
					{
						$enddate = mktime(12,0,0,$emonth,$eday,$eyear);
					}
				}
				else
				{
					if (isset($eday)) $enddate = 0;
				}
			}
			if ($save || $add)		// form submited
			{
				if (strlen($des) >= 8000)
				{
					$error[] = lang('Description can not exceed 8000 characters in length');
				}
				if (!$subject && !$des)
				{
					$error[] = lang('You must enter a subject or a description');
				}

				if ($enddate < $startdate && $enddate && $startdate)
				{
					$error[] = lang('Enddate can not be before startdate');
				}

				$access = $access ? 'private' : 'public';

				if (! is_array($error))
				{
					$this->bo->write(array(
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
			
					if (!$query_addr && !$query_project)
					{
						Header('Location: ' . $html->link($referer, array('cd'=>15)));
					}
				}
			}
			$this->bo->read( $info_id );

			if ($info_id && $action == 'sp')    // new SubProject
			{
				if (!$this->bo->check_access($info_id,PHPGW_ACL_ADD))
				{
					Header('Location: ' .  $html->link($referer));
					$phpgw->common->phpgw_exit();
				}
				$parent = $this->bo->so->data;
				$this->bo->so->data['info_id'] = $info_id = 0;
				$this->bo->so->data['info_owner'] = $phpgw_info['user']['account_id'];
				$this->bo->so->data['info_id_parent'] = $parent['info_id'];
				if ($parent['info_type']=='task' && $parent['info_status']=='offer')
				{
					$this->bo->so->data['info_type'] = 'confirm';   // confirmation to parent
					$this->bo->so->data['info_responsible'] = $parent['info_owner'];
				}
				$this->bo->so->data['info_status'] = 'ongoing';
				$this->bo->so->data['info_confirm'] = 'not';
				$this->bo->so->data['info_subject']=lang('Re:').' '.$parent['info_subject'];
				$this->bo->so->data['info_des'] = '';
			}
			else
			{
				if ($info_id && !$this->bo->check_access($info_id,PHPGW_ACL_EDIT))
				{
					Header('Location: ' .  $html->link($referer));
					$phpgw->common->phpgw_exit();
				}
			}      
			if (!$id_parent)
				$id_parent = $this->bo->so->data['info_id_parent'];

			$common_hidden_vars =  $html->input_hidden( $hidden_vars + array(
				'info_id' => $info_id,
				'action' => $action,
				'id_parent' => $id_parent,
				'referer' => $referer
			));

			$phpgw->common->phpgw_header();
			echo parse_navbar();

			$t->set_file(array('info_edit' => 'form.tpl'));
			  
			// ====================================================================
			// create two seperate blocks, addblock will be cut off from template
			// editblock contains the buttons and forms for edit
			// ====================================================================
			$t->set_block('info_edit', 'add', 'addhandle');
			$t->set_block('info_edit', 'edit', 'edithandle');
			$t->set_block('info_edit', 'subpro', 'subprohandle');
			  
			if (is_array($error))
			{
				$t->set_var('error_list',$phpgw->common->error_list($error));
			}

			switch ($action)
			{
				case 'sp':
					$info_action = 'InfoLog - New Subproject';
					break;
				case 'new': case 'addr': case 'proj':
					$info_action = 'InfoLog - New';
					if ($info_type && isset($this->bo->enums['type'][$info_type]))
						$this->bo->so->data['info_type'] = $info_type;
					break;
				default:
					$info_action = 'InfoLog - Edit'; break;
			}
			$t->set_var('lang_info_action',lang($info_action) .
					($query_addr ? ' - '.lang('Search for:')." '$query_addr'" : ''));
			$t->set_var($this->setStyleSheet( ));
			$t->set_var('lang_category',lang('Category'));
			$t->set_var('lang_none',lang('None'));
			if (!isset($info_cat)) $info_cat = $this->bo->so->data['info_cat'];
			$t->set_var('cat_list',$this->categories->formated_list('select',
																		'all',$info_cat,'True'));

			$t->set_var('actionurl',$html->link('/index.php',
							$this->menuaction('edit')));
			$t->set_var('common_hidden_vars',$common_hidden_vars);

			// get an instance of select box class
			$sb = CreateObject('phpgwapi.sbox2');

			$t->set_var('lang_owner',lang('Owner'));
			$t->set_var('owner_info',$sb->accountInfo($this->bo->so->data['info_owner']));

			$t->set_var('lang_type',lang('Type'));
			if (!isset($type)) $type = $this->bo->so->data['info_type'];
			if (!$type) $type = 'note';
			$t->set_var('type_list',$html->sbox_submit($sb->getArrayItem(
												'type',$type,$this->bo->enums['type']),True));

			$t->set_var('lang_prfrom', lang('From'));
			if (!isset($from)) $from =$phpgw->strip_html($this->bo->so->data['info_from']);
			$t->set_var('fromval', $from);

			$t->set_var('lang_praddr', lang('Phone/Email'));
			if (!isset($addr)) $addr =$phpgw->strip_html($this->bo->so->data['info_addr']);
			$t->set_var('addrval', $addr);

			if (!isset($id_project)) $id_project = $this->bo->so->data['info_proj_id'];
			$t->set_var($sb->getProject('project',$id_project,$query_project));

			if (!isset($id_addr)) $id_addr = $this->bo->so->data['info_addr_id'];
			$t->set_var($sb->getAddress('addr',$id_addr,$query_addr));
					
			$t->set_var('lang_prsubject', lang('Subject'));
			if (!isset($subject)) {
				$subject = $phpgw->strip_html($this->bo->so->data['info_subject']);
			}
			$t->set_var('subjectval', $subject);

			$t->set_var('lang_prdesc', lang('Description'));
			if (!isset($des)) $des = $phpgw->strip_html($this->bo->so->data['info_des']);
			$t->set_var('descval', $des);

			$t->set_var('lang_start_date',lang('Startdate'));
			if (!isset($startdate)) $startdate = $this->bo->so->data['info_startdate'];
			$t->set_var('start_select_date',
							$sb->getDate('syear','smonth','sday',$startdate));

			$t->set_var('lang_end_date',lang('Enddate'));
			if (!isset($enddate)) $enddate = $this->bo->so->data['info_enddate'];
			$t->set_var('end_select_date',
							$sb->getDate('eyear','emonth','eday',$enddate));

			$t->set_var('lang_selfortoday',lang('Today'));
			$t->set_var('selfortoday',$html->checkbox('selfortoday',0));
			$t->set_var('lang_dur_days',lang('Duration'));
			$t->set_var('days',lang('days'));

			$t->set_var('lang_status',lang('Status'));
			if (!isset($status)) $status = $this->bo->so->data['info_status'];
			if (!$status) $status = $this->bo->status['defaults'][$type];
			$t->set_var('status_list',$sb->getArrayItem('status',$status,
																	$this->bo->status[$type]));

			$t->set_var('lang_priority',lang('Priority'));
			if (!isset($pri)) $pri = $this->bo->so->data['info_pri'];
			$t->set_var('priority_list',$sb->getArrayItem('pri',$pri,
																	$this->bo->enums['priority']));

			$t->set_var('lang_confirm',lang('Confirm'));
			if (!isset($confirm)) $confirm = $this->bo->so->data['info_confirm'];
			$t->set_var('confirm_list',$sb->getArrayItem('confirm',$confirm,
																	$this->bo->enums['confirm']));

			$t->set_var('lang_responsible',lang('Responsible'));
			if (!isset($responsible)) $responsible=$this->bo->so->data['info_responsible'];
			$t->set_var('responsible_list',$sb->getAccount('responsible',
																		  $responsible));

			$t->set_var('lang_access_type',lang('Private'));
			if (!isset($access)) $access = $this->bo->so->data['info_access'] == 'private';
			$t->set_var('access_list',$html->checkbox('access',$access));
			  
			$t->set_var('edit_button',$html->submit_button('save','Save'));
			 
			if (!$action && $this->bo->check_access($info_id,PHPGW_ACL_DELETE))
			{
				$t->set_var('delete_button',$html->form_1button('delete','Delete',
					array('referer'=>$referer),'/index.php',
					$this->menuaction('delete')+array('info_id'=>$info_id)));
			}
			$t->set_var('edithandle','');
			$t->set_var('addhandle','');
			$t->set_var('subprohandle','');
			$t->pfp('out','info_edit');
			$t->pfp('edithandle','edit');
		}

		function delete( )
		{
			global $phpgw,$phpgw_info;
			global $cat_filter,$cat_id,$sort,$order,$query,$start,$filter;
			global $info_id,$confirm;

			$t = $this->template; $html = $this->html;

			$referer = $this->get_referer();

			$hidden_vars = array('sort' => $sort,'order' => $order,
										'query' => $query,'start' => $start,
										'filter' => $filter,'cat_id' => $cat_id,
										'referer' => $referer );
			if (!$info_id ||
			    !$this->bo->check_access($info_id,PHPGW_ACL_DELETE))
			{
				Header('Location: ' .  $html->link($referer));
			}
			if ($confirm)
			{
				$this->bo->delete($info_id);

				Header('Location: ' . $html->link($referer,array( 'cd' => 16 )));
			}
			else
			{
				$phpgw->common->phpgw_header();
				echo parse_navbar();

				$t->set_file(array( 'info_delete' => 'delete.tpl' ));
				$t->set_var( $this->setStyleSheet( ));
				$t->set_var( $this->infoHeaders(  ));
				$t->set_var( $this->formatInfo( $info_id ));
				$t->set_var('lang_info_action',lang('InfoLog - Delete'));

				$t->set_var('deleteheader',
								lang('Are you sure you want to delete this entry'));
				$t->set_var('no_button',$html->form_1button('no_button',
					'No - Cancel','',$referer));
				$t->set_var('yes_button',$html->form_1button('yes_button',
					'Yes - Delete',$hidden_vars,'/index.php',
					array('info_id' => $info_id,'confirm' => 'True')+
					$this->menuaction('delete')));
				$t->pfp('out','info_delete');
			}
		}

		function preferences( )
		{
			global $phpgw,$phpgw_info;
			global $save;

			$prefs = array(
				'homeShowEvents'	=> 'Show open Events: Tasks/Calls/Notes on main screen',
				'defaultFilter'	=>	'Default Filter for InfoLog',
				'listNoSubs'		=> 'List no Subs/Childs',
				'longNames'			=> 'Show full usernames'
			);
			$allowed_values = array (
				'defaultFilter' => $this->filters
			);

			$phpgw->preferences->read_repository();

			if ($save)
			{
				while (list($pref,$lang) = each($prefs))
				{
					$phpgw->preferences->add('infolog',$pref);
				}
				$phpgw->preferences->save_repository(True);

				Header('Location: '.$phpgw->link('/preferences/index.php'));
				$phpgw->common->phpgw_exit();
			}
			$phpgw->common->phpgw_header();
			echo parse_navbar();

			$t = $this->template; $html = $this->html;

			$t->set_file(array('info_prefs' => 'preferences.tpl'));

			$vars = Array(
				'title' => lang('InfoLog preferences'),
				'text' => '&nbsp;',
				'action_url' => $html->link('/index.php',
													 $this->menuaction('preferences')),
				'bg_h_color' => $phpgw_info['theme']['th_bg'],
				'save_button' => $html->submit_button('save','Save')
			);
			$t->set_var($vars);

			$t->set_block('info_prefs', 'pref_line', 'pref_linehandle');

			while (list($pref,$lang) = each($prefs))
			{
				$t->set_var('bg_nm_color',$this->nextmatchs->alternate_row_color());
				$t->set_var('field',lang($lang));

				if (is_array($allowed_values[$pref]))
				{
					if (!is_object($sbox)) $sbox = CreateObject('phpgwapi.sbox2');

					$t->set_var('data',$sbox->getArrayItem($pref,
									$phpgw_info['user']['preferences']['infolog'][$pref],
									$allowed_values[$pref],1));
				}
				else
				{
					$t->set_var('data',$html->checkbox($pref,
								$phpgw_info['user']['preferences']['infolog'][$pref]));
				}
				$t->parse('pref_linehandle','pref_line',True);
			}
			$t->pfp('out','info_prefs');
		}
	}
