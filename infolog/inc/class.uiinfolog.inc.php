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
			'index'       => True,
			'edit'        => True,
			'delete'      => True,
			'get_file'    => True,
			'add_file'    => True,
			'admin'       => True,
			'writeLangFile' => True
		);
		var $icons;

		function uiinfolog( )
		{
			$this->bo = CreateObject('infolog.boinfolog');

			$this->icons = array(
				'type' => array(
					'task'      => 'task.gif',      'task_alt'      => 'Task',
					'phone'     => 'phone.gif',     'phone_alt'     => 'Phonecall',
					'note'      => 'note.gif',      'note_alt'      => 'Note',
					'confirm'   => 'confirm.gif',   'confirm_alt'   => 'Confirmation',
					'reject'    => 'reject.gif',    'reject_alt'    => 'Reject',
					'email'     => 'email.gif',     'email_alt'     => 'Email' ),
				'action' => array(
					'new'       => 'new.gif',       'new_alt'       => 'Add Sub',
					'view'      => 'view.gif',      'view_alt'      => 'View Subs',
					'parent'    => 'parent.gif',    'parent_alt'    => 'View other Subs',
					'edit'      => 'edit.gif',      'edit_alt'      => 'Edit',
					'addfile'   => 'addfile.gif',   'addfile_alt'   => 'Add a file',
					'delete'    => 'delete.gif',    'delete_alt'    => 'Delete' ),
				'status' => array(
					'billed'    => 'billed.gif',    'billed_alt'    => 'billed',
					'done'      => 'done.gif',      'done_alt'      => 'done',
					'will-call' => 'will-call.gif', 'will-call_alt' => 'will-call',
					'call'      => 'call.gif',      'call_alt'      => 'call',
					'ongoing'   => 'ongoing.gif',   'ongoing_alt'   => 'ongoing',
					'offer'     => 'offer.gif',     'offer_alt'     => 'offer' )
			);

			$this->filters = array(
				'none'				=>	'no Filter',
				'done'				=>	'done',
				'own'					=>	'own',
				'own-open-today'	=>	'own open',
				'own-open-overdue'=>	'own overdue',
				'own-upcoming'		=>	'own upcoming',
				'open-today'		=>	'open',
				'open-overdue'		=>	'overdue',
				'upcoming'			=>	'upcoming'
			);
         
			$this->messages = array(
				'edit'    => 'InfoLog - Edit',
				'add'     => 'InfoLog - New',
				'add_sub' => 'InfoLog - New Subproject',
				'sp'      => '- Subprojects from',
				're'      => 'Re:'
			);
			$this->link = &$this->bo->link;
			
			$this->tmpl = CreateObject('etemplate.etemplate');
			$this->html = &$this->tmpl->html;
		}

		function get_info($info,&$readonlys,$action='',$action_id='')
		{
			if (!is_array($info))
			{
				$info = $this->bo->read($info);
			}
			$id = $info['info_id'];
			$done = $info['info_status'] == 'done' || $info['info_status'] == 'billed';
			$info['sub_class'] = $info['info_pri'] . ($done ? '_done' : '');
			if (!$done && $info['info_enddate'] < time()+(60*60)*$GLOBALS['phpgw_info']['user']['preferences']['common']['tz_offset'])
			{
				$info['end_class'] = 'overdue';
			}
			$info['info_des'] = nl2br($info['info_des']);
			$info['info_anz_subs'] = $this->bo->anzSubs($id);
			$this->bo->link_id2from($info,$action,$action_id);	// unset from for $action:$action_id
			
			$readonlys["edit[$id]"] = !$this->bo->check_access($id,PHPGW_ACL_EDIT);
			$readonlys["delete[$id]"] = !$this->bo->check_access($id,PHPGW_ACL_DELETE);
			$readonlys["sp[$id]"] = !$this->bo->check_access($id,PHPGW_ACL_ADD);
			$readonlys["view[$id]"] = $info['info_anz_subs'] < 1;
			$readonlys['view[0]'] = True;	// no parent

			return $info;
		}

		function get_rows($query,&$rows,&$readonlys)
		{
			//echo "<p>uiinfolog.get_rows(start=$query[start],search='$query[search]',filter='$query[filter]',cat_id=$query[cat_id],action='$query[action]/$query[action_id]')</p>\n";
			$GLOBALS['phpgw']->session->appsession('session_data','infolog',array(
				'search' => $query['search'],
				'start'  => $query['start'],
				'filter' => $query['filter'],
				'cat_id' => $query['cat_id']
			));
			$ids = $this->bo->search($query['order'],$query['sort'],$query['filter'],$query['cat_id'],
				$query['search'],$query['action'],$query['action_id'],$query['ordermethod'],
				$query['start'],$total);
			
			if (!is_array($ids))
			{
				$ids = array( );
			}
			$rows = array( $total );
			$readonlys = array();
			while (list($id,$info) = each($ids))
			{
				$rows[] = $this->get_info($info,$readonlys,$query['action'],$query['action_id']);
			}
			//echo "<p>readonlys = "; _debug_array($readonlys);
			reset($rows);
			
			return $total;
		}

		function index($values = 0,$action='',$action_id='',$referer=0)
		{
			$referer = is_array($values) ? $values['referer'] : $referer;
			//echo "<p>uiinfolog::index(action='$action/$action_id',referer='$referer/$values[referer]')</p>\n";
			if (!is_array($values))
			{
				$values = array('nm' => $GLOBALS['phpgw']->session->appsession('session_data','infolog'));
			}
			else
			{
				$GLOBALS['phpgw']->session->appsession('session_data','infolog',array(
					'search' => $values['nm']['search'],
					'start'  => $values['nm']['start'],
					'filter' => $values['nm']['filter'],
					'cat_id' => $values['nm']['cat_id']
				));
			}
			if ($action == '')
			{
				$action = $values['action'] ? $values['action'] : get_var('action',array('POST','GET'));
				$action_id = $values['action_id'] ? $values['action_id'] : get_var('action_id',array('POST','GET'));
			}
			if ($values['add'] || $values['cancel'] || isset($values['nm']['rows']) || isset($values['main']))
			{

				if ($values['add'])
				{
					list($type) = each($values['add']);
					return $this->edit(0,$action,$action_id,$type,$referer);
				}
				else
				{
					list($do,$do_id) = isset($values['main']) ? each($values['main']) : @each($values['nm']['rows']);
					list($do_id) = @each($do_id);
					//echo "<p>infolog::index: do='$do/$do_id', referer="; _debug_array($referer);
					switch($do)
					{
						case 'edit':
							return $this->edit($do_id,$action,$action_id,'',$referer);
						case 'delete':
							return $this->delete($do_id,$referer);
						case 'sp':
							return $this->edit(0,'sp',$do_id,'',$referer);
						case 'view':
							$value = array();
							$action = 'sp';
							$action_id = $do_id;
							break;
						default:
							$value = array();
							$action = '';
							$action_id = 0;
							break;
					}
				}
			}
			switch ($action)
			{
				case 'sp':
					if (!$this->bo->read($action_id))
					{
						$action = '';
						$action_id = 0;
						break;
					}
					$values['main'][1] = $this->get_info($action_id,&$readonlys['main']);
					$values['appheader'] = $this->messages['sp'];
					break;
			}
			$readonlys['cancel'] = $action != 'sp'; 

			$this->tmpl->read('infolog.index');

			$values['nm']['options-filter'] = $this->filters;
			$values['nm']['get_rows'] = 'infolog.uiinfolog.get_rows';
			$values['nm']['no_filter2'] = True;
			$persist['action'] = $values['nm']['action'] = $action;
			$persist['action_id'] = $values['nm']['action_id'] = $action_id;
			$persist['referer'] = $referer;

			$this->tmpl->exec('infolog.uiinfolog.index',$values,'',$readonlys,$persist);
		}

		function delete($values=0,$referer='')
		{
			$info_id = is_array($values) ? $values['info_id'] : $values;
			$referer = is_array($values) ? $values['referer'] : $referer;

			if (is_array($values) || $info_id <= 0)
			{
				if ($values['delete'] && $info_id > 0 && $this->bo->check_access($info_id,PHPGW_ACL_DELETE))
				{
					$this->bo->delete($info_id);
				}
				return $referer ? $this->tmpl->location($referer) : $this->index();
			}
			$readonlys = $values = array();
			$values['main'][1] = $this->get_info($info_id,&$readonlys['main']);

			$this->tmpl->read('infolog.delete');

			$values['main']['no_actions'] = True;
			$persist['info_id'] = $info_id;
			$persist['referer'] = $referer;

			$this->tmpl->exec('infolog.uiinfolog.delete',$values,'',$readonlys,$persist);
		}

		/*!
		@function edit
		@syntax edit( $content=0,$action='',$action_id=0,$type='' )
		@author ralfbecker
		@abstract Edit/Create an InfoLog Entry
		@param $content   Content from the eTemplate Exec call or info_id on inital call
		@param $action    Name of an app of 'sp' for a infolog-sub
		@param $action_id Id of app-entry to which a link is created
		@param $type      Type of log-entry: note,todo,task
		*/
		function edit($content = 0,$action = '',$action_id=0,$type='',$referer='')
		{
			if (is_array($content))
			{
				//echo "uiinfolog::edit: content="; _debug_array($content);
				$info_id   = $content['info_id'];
				$action    = $content['action'];
				$action_id = $content['action_id'];
				$referer   = $content['referer'];

				if (isset($content['link_to']['primary']))
				{
					$content['info_link_id'] = $content['link_to']['primary'];
				}
				if (intval($content['info_link_id']) > 0 && !$this->link->get_link($content['info_link_id']))
				{
					$content['info_link_id'] = 0;	// link has been deleted
				}
				if ($content['set_today'])
				{
					$content['info_startdate'] = time();
					unset($content['set_today']);
				}
				if ($content['save'] || $content['delete'] || $content['cancel'])
				{
					if ($content['save'] && (!$info_id || $this->bo->check_access($info_id,PHPGW_ACL_EDIT)))
					{
						if (strstr($content['info_link_id'],':') !== False)
						{
							$info_link_id = $content['info_link_id'];
							$content['info_link_id'] = 0;	// as field has to be int
						}
						$this->bo->write($content);

						if (!$info_id && is_array($content['link_to']['to_id']))	// writing link for new entry
						{
							$content['info_id'] = $this->bo->so->data['info_id'];
							$this->link->link('infolog',$content['info_id'],$content['link_to']['to_id']);
							if ($info_link_id)
							{
								list($app,$id) = explode(':',$info_link_id);
								$link = $this->link->get_link('infolog',$content['info_id'],$app,$id);
								$content['info_link_id'] = $link['link_id'];

								$this->bo->write(array(
									'info_id' => $content['info_id'],
									'info_link_id' => $content['info_link_id'],
									'info_from' => $content['info_from']
								),False);
							}
						}
					}
					elseif ($content['delete'] && $info_id > 0)
					{
						if (!$referer && $action) $referer = array(
							'menuaction' => 'infolog.uiinfolog.index',
							'action' => $action,
							'action_id' => $action_id
						);
						return $this->delete($info_id,$referer);	// checks ACL first
					}
					return $referer ? $this->tmpl->location($referer) : $this->index(0,$action,$action_id);
				}
			}
			else
			{
				//echo "<p>uiinfolog::edit: info_id=$info_id,  action='$action', action_id='$action_id', type='$type', referer='$referer'</p>\n";
				$action    = $action    ? $action    : get_var('action',   array('POST','GET'));
				$action_id = $action_id ? $action_id : get_var('action_id',array('POST','GET'));
				$info_id   = $content   ? $content   : get_var('info_id',  array('POST','GET'));
				$type      = $type      ? $type      : get_var('type',     array('POST','GET'));
				$referer   = $referer !== '' ? $referer : 
					ereg_replace('^.*'.$GLOBALS['phpgw_info']['server']['webserver_url'],'',
					get_var('HTTP_REFERER',Array('GLOBAL')));
				//echo "<p>uiinfolog::edit: info_id=$info_id,  action='$action', action_id='$action_id', type='$type', referer='$referer'</p>\n";
				
				$this->bo->read( $info_id || $action != 'sp' ? $info_id : $action_id );
				$content = $this->bo->so->data;

				if (!$info_id && $action_id && $action == 'sp')    // new SubProject
				{
					if (!$this->bo->check_access($action_id,PHPGW_ACL_ADD))
					{
						return $referer ? $this->tmpl->location($referer) : $this->index(0,$action,$action_id);
					}
					$parent = $this->bo->so->data;
					$content['info_id'] = $info_id = 0;
					$content['info_owner'] = $GLOBALS['phpgw_info']['user']['account_id'];
					$content['info_id_parent'] = $parent['info_id'];
					/*
					if ($parent['info_type']=='task' && $parent['info_status']=='offer')
					{
						$content['info_type'] = 'confirm';   // confirmation to parent
						$content['info_responsible'] = $parent['info_owner'];
					}
					*/
					$content['info_status'] = 'ongoing';
					$content['info_confirm'] = 'not';
					$content['info_subject']=lang($this->messages['re']).' '.$parent['info_subject'];
					$content['info_des'] = '';
					$content['info_lastmodified'] = '';
				}
				else
				{
					if ($info_id && !$this->bo->check_access($info_id,PHPGW_ACL_EDIT))
					{
						return $referer ? $this->tmpl->location($referer) : $this->index(0,$action,$action_id);
					}
				}
				$content['links'] = $content['link_to'] = array(
					'to_id' => $info_id,
					'to_app' => 'infolog',
				);
				switch ($action)
				{
					case 'sp':
						break;
					case 'addressbook':
					case 'projects':
					case 'calendar':
						$content['info_link_id'] = $this->link->link('infolog',$content['link_to']['to_id'],$action,$action_id);
					case 'new': 
						if ($type != '')
						{
							$content['info_type'] = $type;
						}
						break;
					default:
						$action = '';
						break;
				}
				$content['link_to']['primary'] = $content['info_link_id'] ? $content['info_link_id'] : True;
				
				if (!isset($this->bo->enums['type'][$content['info_type']]))
				{
					$content['info_type'] = 'note';
				}
			}
			$readonlys['delete'] = $action != '';
			$content['appheader'] = $this->messages[$info_id ? 'edit' : ($action == 'sp' ? 'add_sub' : 'add')];

			//echo "<p>uiinfolog.edit(info_id=$info_id,mode=$mode) content = "; _debug_array($content);
			$this->tmpl->read('infolog.edit');
			$this->tmpl->exec('infolog.uiinfolog.edit',$content,array(
				'info_type'     => $this->bo->enums['type'],
				'info_pri'      => $this->bo->enums['priority'],
				'info_confirm'  => $this->bo->enums['confirm'],
				'info_status'   => $this->bo->status[$content['info_type']]
			),$readonlys,array(
				'info_id'   => $info_id,
				'info_id_parent' => $content['info_id_parent'],
				'info_link_id' => $content['info_link_id'],
				'action'    => $action,
				'action_id' => $action_id,
				'referer'   => $referer,
				'link_to'   => array('to_id' => $content['link_to']['to_id'])	// in case tab gets not viewed
			));
		}

		function menuaction($action = 'get_list',$app='infolog')
		{
			return array( 'menuaction' => "$app.ui$app.$action" );
		}

		function icon($cat,$id,$status='')
		{
			if (!$status || !($icon = $this->icons[$cat][$id.'_'.$status]))
			{
				$icon = $this->icons[$cat][$id];
			}
			if ($icon && !is_readable($GLOBALS['phpgw']->common->get_image_dir() . '/' . $icon))
			{
				$icon = False;
			}
			if (!$status || !($alt = $this->icons[$cat][$id.'_'.$status.'_alt']))
			{
				if (!($alt = $this->icons[$cat][$id.'_alt']))
				{
					$alt = $id;
				}
			}
			return $icon ? $this->html->image('infolog',$icon,lang($alt),'border=0') : lang($alt);
		}

		function setStyleSheet( )
		{
			return array (
				'info_css' => '<link rel="stylesheet" type="text/css" href="'.
									str_replace( '/images','',
									$GLOBALS['phpgw']->common->get_image_path()).'/info.css">'
			);
		}

		/*
		 * $info: info_id or array with one row form info-db
		 * no Proj.Info if action='proj' proj_id == action_id / no Addr.Info if addr_id == a_id
		 */
		function formatInfo($info=0,$action='',$action_id=0)
		{
			if (!is_array($info) && (!$info ||
				 !is_array($info=$this->bo->read($info))))
			{
				$info = $this->bo->so->data;
			}
			$done = $info['info_status'] == 'done' ||
					  $info['info_status'] == 'billed';

			$css_class = $info['info_pri'].($done ? '_done' : '');
			$subject = "<span class=$css_class>";
/*
			if (($action_id != ($proj_id = $info['info_proj_id']) || $action != 'proj') &&
			    $proj = $this->bo->readProj($proj_id))
			{
				$subject .= $this->html->bold($this->html->a_href($proj['title'],'/index.php',
					file_exists(PHPGW_SERVER_ROOT.'/projects') &&
						$GLOBALS['phpgw_info']['user']['apps']['projects']['enabled'] ?
					array(
						'menuaction' => 'projects.uiprojects.view_project',
						'action' => 'mains',
						'project_id' => $proj_id
					) : $this->menuaction() + array(
						'filter' => $filter,
						'action' => 'proj',
						'action_id' => $proj_id
					)
				));
			}
			if (($action_id != ($addr_id = $info['info_addr_id']) || $action != 'addr') &&
				 $addr = $this->bo->readAddr($addr_id))
			{
				if ($proj) $subject .= '<br>';
				$addr = $this->bo->addr2name( $addr );
				$subject .= $this->html->bold($this->html->a_href($addr,'/index.php',
									file_exists(PHPGW_SERVER_ROOT.'/addressbook') &&
										$GLOBALS['phpgw_info']['user']['apps']['addressbook']['enabled'] ?
									array(
										'menuaction' => 'addressbook.uiaddressbook.view',
										'ab_id' => $addr_id
									) : $this->menuaction() + array(
										'filter' => $filter,
										'action' => 'addr',
										'action_id' => $addr_id
									)
								));
			}
			if (($action_id != ($event_id = $info['info_event_id']) || $action != 'event') &&
				 $event = $this->bo->readEvent($event_id))
			{
				if ($proj || $addr) $subject .= '<br>';
				$event = $this->bo->event2name( $event );
				$subject .= $this->html->bold($this->html->a_href($event,'/index.php',
									file_exists(PHPGW_SERVER_ROOT.'/calendar') &&
										$GLOBALS['phpgw_info']['user']['apps']['calendar']['enabled'] ?
									array(
										'menuaction' => 'calendar.uicalendar.view',
										'cal_id' => $event_id
									) : $this->menuaction() + array(
										'filter' => $filter,
										'action' => 'event',
										'action_id' => $event_id
									)
								));
			} */
			if (($from = $info['info_from']) && (!$addr || !strstr($addr,$from)))
			{
				if ($addr || $event) $subject .= '<br>';
				$subject .= '<b>'.$from.'</b>';
			}
			if ($info['info_addr'])
			{
				if ($addr || $from || $event) $subject .= ': ';
				$subject .= $info['info_addr'];
			}
			if ($proj || $addr || $from || $info['info_addr'] || $event)
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
				$enddate = $GLOBALS['phpgw']->common->show_date($info['info_enddate'],
						$GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat']);

				if (!$done && $info['info_enddate'] < time()+(60*60)*$GLOBALS['phpgw_info']['user']['preferences']['common']['tz_offset'])
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
/*
			// add the links to the files which corrospond to this entry
			$attachments = $this->bo->list_attached($info['info_id']);
			while (list($name,$comment) = @each($attachments))
			{
				$links .= isset($links) ? ', ' : '<br>';
				$links .= $this->html->a_href($name,'/index.php',
					$this->menuaction('get_file') + array(
						'info_id'    => $info['info_id'],
						'filename'   => str_replace('&','%26',$name)
					),'target=_blank');
				if ($comment) $links .= ' (' . $comment . ')';
			}
*/
			return array(
				'type'        => $this->icon('type',$info['info_type']),
				'status'      => $this->icon('status',$info['info_status']),
				'pri'         => lang($info['info_pri']),
				'subject'     => $subject,
				'des'         => nl2br($info['info_des']),
				'startdate'   => $GLOBALS['phpgw']->common->show_date($info['info_startdate'],
					$GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat']),
				'enddate'     => $enddate,
				'owner'       => $owner,
				'datemodified' => $GLOBALS['phpgw']->common->show_date($info['info_datemodified'],
					$GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat']),
				'responsible' => $responsible,
				'filelinks'   => $links
			);
		}

		function infoHeaders( $do_sort_header=0,$sort=0,$order=0,$cat_id=0)
		{
			$headers['th_bg'] = $GLOBALS['phpgw_info']['theme']['th_bg'];
			$headers['row_off'] = $GLOBALS['phpgw_info']['theme']['row_off'];
			$headers['row_on'] = $GLOBALS['phpgw_info']['theme']['row_on'];

			$fields = array(
				'type'		=> 'Type',
				'status'		=> 'Status',
				'urgency'	=>	'Urgency',
				'subject'	=> 'Subject',
				'startdate'	=>	'Startdate',
				'enddate'	=>	'Enddate',
				'owner'		=>	'Owner',
				'datemodified' => 'last changed',
				'responsible' => 'Responsible'
			);
			while (list($f,$lang) = each($fields))
			{
				$lang = lang($lang);
				$headers['lang_'.$f] = $do_sort_header ? $this->nextmatchs->show_sort_order($sort,'info_'.$f,$order,'/index.php',$lang,"&cat_id=$cat_id") : $lang;
			}
			return $headers;
		}

		function get_referer($vars='')
		{
			if(!$vars)
			{
				$vars=array('sort','order','query','start','filter','cat_id');
			}
			global $referer;

			if (!$referer)
			{
				$referer = get_var('HTTP_REFERER',Array('GLOBAL'));
			}

			//	make the referer relative to the phpgw-install
			$url = parse_url($referer);
			$server = parse_url($GLOBALS['phpgw_info']['server']['webserver_url']);
			$url['path'] = str_replace($server['path'],'',$url['path']);
			
			if (!strstr($url['query'],'menuaction=infolog') || !is_array($vars))
			{
				return $url['path'].($url['query'] ? '?'.$url['query'] : '');
			}
			$args = explode('&',$url['query']);
			if (!is_array($args))
			{
				$args = array();
				if ($url['query'])
				{
					$args[] = $url['query'];
				}
			}
			$query_arr = array( );
			while (list($null,$arg) = each($args))
			{
				list($var,$val) = explode('=',$arg,2);
				$query_arr[$var] = $val;
			}
			reset ($vars);
			while (list($null,$var) = each($vars))
			{
				global $$var;								// merge in HTTP_{POST|GET}_VARS
				if (isset($$var))
				{
					$query_arr[$var] = $$var;
				}
			}
			$qstr = '';
			reset ($query_arr);
			while (list($var,$val) = each($query_arr))
			{
				if ($val && !($val == 'none' && $var == 'filter'))
				{
					$qstr .= ($qstr ? '&' : '?')."$var=$val";
				}
			}
			//echo "<p>qstr='$qstr'</p>\n";
			return $url['path'] . $qstr;
		}

	 	function get_list($for_include=0,$action='',$action_id=0)
		{
			global $cat_filter,$cat_id,$sort,$order,$query,$start,$filter;

			if (!$for_include)
			{
				$GLOBALS['phpgw']->common->phpgw_header();
			}
			if ($action == '')
			{
				$action = get_var('action',array('GET','POST'));
				$action_id = get_var('action_id',array('GET','POST'));
			}
			$GLOBALS['phpgw']->template->set_root($GLOBALS['phpgw']->common->get_tpl_dir('infolog'));	// necessary for hooks

			$GLOBALS['phpgw']->template->set_file(array( 'info_list_t' => 'list.tpl' ));
			if ($action == 'sp')
			{
				$GLOBALS['phpgw']->template->set_block('info_list_t','projdetails','projdetailshandle');
			}
			$GLOBALS['phpgw']->template->set_block('info_list_t','info_headers');
			$GLOBALS['phpgw']->template->set_block('info_list_t','info_list','list');
			$GLOBALS['phpgw']->template->set_block('info_list_t','get_list');

			if ($cat_filter) $cat_id = $cat_filter;

			if (!$filter)
			{
				$filter = $GLOBALS['phpgw_info']['user']['preferences']['infolog']['defaultFilter'];
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
					$action_vars = array('action'=>'sp','info_id'=>$action_id);
					$GLOBALS['phpgw']->template->set_var(lang_info_action,lang('InfoLog - Subprojects from'));
					break;
				case 'proj': case 'projects':
					$action_vars = array( /*'id_project' => $action_id, 'proj_id' => $action_id */
						'action' => 'projects', 'action_id' => $action_id );
					$GLOBALS['phpgw']->template->set_var(lang_info_action,lang('InfoLog').' - '.
									$this->bo->proj2name($action_id));
					break;
				case 'addr': case 'addressbook':
					$action_vars = array( /*'id_addr' => $action_id,'addr_id' => $action_id */
						'action' => 'addressbook', 'action_id' => $action_id );
					$GLOBALS['phpgw']->template->set_var(lang_info_action,lang('InfoLog').' - '.
									$this->bo->addr2name($action_id));
					break;
				case 'event': case 'calendar':
					$action_vars = array( /*'id_event' => $action_id,'event_id' => $action_id */
						'action' => 'calendar', 'action_id' => $action_id );
					$GLOBALS['phpgw']->template->set_var(lang_info_action,lang('InfoLog').' - '.
									$this->bo->event2name($action_id));
					break;
				default:
					if ($filter && $filter != 'none')
					{
						$filter_name = ': '.$this->filters[ $filter ];
					}
					$GLOBALS['phpgw']->template->set_var(lang_info_action,lang('InfoLog').$filter_name);
					break;
			}
			$GLOBALS['phpgw']->template->set_var($this->setStyleSheet( ));

			if (!$for_include)
			{
				$GLOBALS['phpgw']->template->set_var('add_button',$this->html->form_1button('add_button','Add',
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
				$add_icons .= $this->html->a_href($this->icon('type',$type),'/index.php',
						 $this->menuaction('edit')+$action_vars+array('type'=>$type));
			}
			$GLOBALS['phpgw']->template->set_var('add_icons',$add_icons);

			// ===========================================
			// list header variable template-declarations
			// ===========================================
			$GLOBALS['phpgw']->template->set_var( $this->infoHeaders( !$for_include,$sort,$order,$cat_id ));
			$GLOBALS['phpgw']->template->set_var(h_lang_sub,lang('Sub'));
			$GLOBALS['phpgw']->template->set_var(h_lang_action,lang('Action'));
			$GLOBALS['phpgw']->template->parse('info_headers','info_headers');
			// -------------- end header declaration -----------------

			$ids = $this->bo->search($order,$sort,$filter,$cat_id,$query,
											 $action,$action_id,$ordermethod,$start,$total);

			$maxmatchs = $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs'];
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
			$GLOBALS['phpgw']->template->set_var('total_matchs',$total_matchs);

			// ==========================================
			// project description if subprojectlist
			// ==========================================

			switch ($action)
			{
			  case 'sp':        // details of parent
					$GLOBALS['phpgw']->template->set_var( $this->infoHeaders(  ));
					$GLOBALS['phpgw']->template->set_var( $this->formatInfo( $action_id ));
					$GLOBALS['phpgw']->template->parse('projdetails','projdetailshandle');
					break;
			  case 'addr':
			  case 'proj':
			  case 'event':
			  		$nm_extra = "&action_id=$action_id";
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
							$total,'&'.$q_string,'95%',$GLOBALS['phpgw_info']['theme']['th_bg'],
							0,$filters,1,0,$cat_id,'cat_id');

				$GLOBALS['phpgw']->template->set_var('next_matchs',$next_matchs);

				if ($total > $maxmatchs)
				{
					$GLOBALS['phpgw']->template->set_var('next_matchs_end',$next_matchs);
				}
			}

			// ---------- end nextmatch template --------------------

			if (intval($for_include) == 2)
			{
				$GLOBALS['phpgw']->template->set_var('info_headers','');
			}
			while (list($id,$info) = each($ids))
			{
				$subproact = $this->bo->anzSubs($id);

				$this->nextmatchs->template_alternate_row_color($GLOBALS['phpgw']->template);

				$GLOBALS['phpgw']->template->set_var( $this->formatInfo( $info,$action,$action_id ));

				if ($this->bo->check_access($id,PHPGW_ACL_EDIT))
				{
					$GLOBALS['phpgw']->template->set_var('edit',$this->html->a_href(
						$this->icon('action','edit'),'/index.php',
						$this->menuaction('edit')+$hidden_vars+array('info_id' => $id)));
					$GLOBALS['phpgw']->template->set_var('addfiles',$this->html->a_href(
						$this->icon('action','addfile'),'/index.php',
						$this->menuaction('add_file')+$hidden_vars+array('info_id' => $id)));
				}
				else
				{
					$GLOBALS['phpgw']->template->set_var('edit','');
				}

				if ($this->bo->check_access($id,PHPGW_ACL_DELETE))
				{
					$GLOBALS['phpgw']->template->set_var('delete',$this->html->a_href(
									$this->icon('action','delete'),'/index.php',
									$this->menuaction('delete')+$hidden_vars+array('info_id' => $id)));
			  }
			  else
			  {
					$GLOBALS['phpgw']->template->set_var('delete','');
			  }
			  $GLOBALS['phpgw']->template->set_var('subadd', '');        // defaults no icons
			  $GLOBALS['phpgw']->template->set_var('viewsub', '');
			  $GLOBALS['phpgw']->template->set_var('viewparent', '');

			  if ($subproact > 0)   // if subprojects exist, display VIEW SUB icon
			  {
					$GLOBALS['phpgw']->template->set_var('viewsub', $this->html->a_href(
						$this->icon('action','view'),'/index.php',
						$this->menuaction()+array( 'action_id' => $id,
						'filter' => $filter, 'action' => 'sp')));
			  }
			  else                           // else display ADD SUB-Icon
			  {
					if ($this->bo->check_access($id,PHPGW_ACL_ADD))
					{
						$GLOBALS['phpgw']->template->set_var('subadd',$this->html->a_href(
							$this->icon('action','new'),'/index.php',
							$this->menuaction('edit')+
							array('info_id' => $id,'filter' => $filter,
									'action' => 'sp')));
					}
				}                       // if parent --> display VIEW SUBS of Parent
				if ($parent && $action != 'sp')
				{
					$GLOBALS['phpgw']->template->set_var('viewparent',$this->html->a_href(
						$this->icon('action','parent'),'/index.php',
						$this->menuaction()+
						array('action_id' => $parent,
								'filter' => $filter,'action' => 'sp')));
			  }

			  $GLOBALS['phpgw']->template->parse('info_list','list',True);
			  // -------------- end record declaration ------------------------
			}

			// =========================================================
			// back2project list href declaration for subproject list
			// =========================================================

			if ($action && !$for_include)
			{
				$GLOBALS['phpgw']->template->set_var('back2projects',
						$this->html->form_1button('back','Back to Projectlist','',
						'/index.php',$this->menuaction()+array('filter'=>$filter)));
			}

			if (intval($for_include) == 2)
			{
				return $GLOBALS['phpgw']->template->fp('phpgw_body','get_list',true);
			}
			else
			{
				$GLOBALS['phpgw']->template->fp('phpgw_body','get_list',true);
				$GLOBALS['phpgw']->template->reset_root();
			}
		}

		/*
		**	Send a requested file to the user.
		**	ACL check is done by the VFS
		*/
		function get_file( )
		{
			$info_id  = get_var('info_id',Array('GET'));
			$filename = get_var('filename',Array('GET'));
			//echo "<p>get_file: info_id='$info_id', filename='$filename'</p>\n";

			$browser = CreateObject('phpgwapi.browser');

			$referer = $this->get_referer();

			if (!$info_id || !$filename || !$this->bo->check_access($info_id,PHPGW_ACL_READ))
			{
				Header('Location: ' .  $this->html->link($referer));
				$GLOBALS['phpgw']->common->phpgw_exit();
			}
			$local = $this->bo->attached_local($info_id,$filename,get_var('REMOTE_ADDR',Array('SERVER')),$browser->is_windows());

			if ($local)
			{
				//echo "<p>local: '$local'</p>\n";
				Header('Location: ' . $local  );
			}
			else
			{
				//echo "<p>not local: ".$this->bo->vfs_path($info_id,$filename)."</p>\n";
				$info = $this->bo->info_attached($info_id,$filename);
				$browser->content_header($filename,$info['mime_type']);
				echo $this->bo->read_attached($info_id,$filename);
			}
			$GLOBALS['phpgw']->common->phpgw_exit();
		}

		/*
		**  Display dialog to add one file to an info_log entry
		*/
		function add_file( )
		{
			global $upload,$info_id;
			global $attachfile,$attachfile_name,$attachfile_size,$attachfile_type;
			global $filecomment,$full_fname;
			global $sort,$order,$query,$start,$filter,$cat_id;

			$referer = $this->get_referer();

			if (!isset($info_id) || !$info_id || !$this->bo->check_access($info_id,PHPGW_ACL_EDIT))
			{
				$error[]=lang('Access denied');
				Header('Location: ' . $this->html->link($referer,array('cd'=>15)));
				$GLOBALS['phpgw']->common->phpgw_exit();
			}

			if ($upload && $attachfile && $attachfile != "none")
			{
				$fileerror = $this->bo->attach_file($info_id,$attachfile,$attachfile_name,$attachfile_size,
					$attachfile_type,$filecomment,$full_fname,get_var('REMOTE_ADDR',Array('SERVER')));
				if ($fileerror) $error[]=$fileerror;
			}
			$GLOBALS['phpgw']->common->phpgw_header();

			$GLOBALS['phpgw']->template->set_file(array('info_add_file' => 'add_file.tpl'));
			$GLOBALS['phpgw']->template->set_var( $this->setStyleSheet( ));
			$GLOBALS['phpgw']->template->set_var( $this->infoHeaders(  ));
			$GLOBALS['phpgw']->template->set_var( $this->formatInfo( $info_id ));
			$GLOBALS['phpgw']->template->set_var( 'hidden_vars',$this->html->input_hidden(array(
				'info_id' => $info_id, 'referer' => $referer, 'full_fname' => '.'	// must not be empty
			)));

			if (is_array($error))
			{
				$GLOBALS['phpgw']->template->set_var('error_list',$GLOBALS['phpgw']->common->error_list($error));
			}

			$GLOBALS['phpgw']->template->set_var('lang_info_action',lang('InfoLog').' - '.lang('attach file'));

			$GLOBALS['phpgw']->template->set_var('actionurl',$this->html->link('/index.php',array('menuaction' => 'infolog.uiinfolog.add_file')));

			$GLOBALS['phpgw']->template->set_var('lang_file',lang('attach file').':');
			$GLOBALS['phpgw']->template->set_var('lang_comment',lang('comment').':');

			$GLOBALS['phpgw']->template->set_var('submit_button',$this->html->submit_button('upload','attach file','this.form.full_fname.value=this.form.attachfile.value'));
			$GLOBALS['phpgw']->template->set_var('cancel_button',$this->html->form_1button('cancel_button','Done','',$referer));

			$GLOBALS['phpgw']->template->fp('phpgw_body','info_add_file');
		}

		function old_edit( )
		{
			global $action,$info_id,$save,$add,$query_addr,$query_project;
			// formular fields
			global $selfortoday,$sday,$smonth,$syear;
			global $dur_days,$eday,$emonth,$eyear;
			global $type,$from,$addr,$id_addr,$id_project,$subject,$des,$access;
			global $pri,$status,$confirm,$info_cat,$id_parent,$responsible;
			global $attachfile,$attachfile_name,$attachfile_size,$attachfile_type;
			global $filecomment,$full_fname;
			global $id_event,$query_event;

			$referer = $this->get_referer();

			if((!isset($info_id) || !$info_id) && !$action || get_var('cancel',Array('POST')))
			{
				Header('Location: ' . $this->html->link($referer) );
				$GLOBALS['phpgw']->common->phpgw_exit();
			}
			if(get_var('delete',Array('POST')))
			{
				$this->delete($info_id);
				return;
			}

			$app2var = array('addressbook' => 'id_addr','projects' => 'id_project','calendar' => 'id_event' );
			$linkto = get_var('linkto',Array('POST'));
			if (is_array($linkto) && $linkto['create'] && ($var = $app2var[$linkto['app']]))
			{
				$$var = $linkto['id'];
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
					/*
					**	if an info_id exists, check if this user hast the rights to edit
					**	this entry (should prevent faking the info_id in a post request)
					**	or if is a new sub check if he has rights to add a sub
					*/
					if ($info_id && !$this->bo->check_access($info_id,PHPGW_ACL_EDIT) ||
					    !$info_id && $id_parent && !$this->bo->check_access($id_parent,PHPGW_ACL_ADD))
					{
						$error[]=lang('Access denied');
					}
					else
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
							'responsible' => $responsible,
							'event_id'  => $id_event
						));

						if ($attachfile && $attachfile != "none")	// save the attached file
						{
							$fileerror = $this->bo->attach_file($this->bo->so->data['info_id'],$attachfile,
								$attachfile_name,$attachfile_size,$attachfile_type,$filecomment,$full_fname,
								get_var('REMOTE_ADDR',Array('SERVER')));
							if ($fileerror) $error[]=$fileerror;
						}
					}

					if (!$query_addr && !$query_project && !$query_event)
					{
						Header('Location: ' . $this->html->link($referer, array('cd'=>15)));
						$GLOBALS['phpgw']->common->phpgw_exit();
					}
				}
			}
			$this->bo->read( $info_id );

			if ($info_id && $action == 'sp')    // new SubProject
			{
				if (!$this->bo->check_access($info_id,PHPGW_ACL_ADD))
				{
					Header('Location: ' .  $this->html->link($referer));
					$GLOBALS['phpgw']->common->phpgw_exit();
				}
				$parent = $this->bo->so->data;
				$this->bo->so->data['info_id'] = $info_id = 0;
				$this->bo->so->data['info_owner'] = $GLOBALS['phpgw_info']['user']['account_id'];
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
					Header('Location: ' .  $this->html->link($referer));
					$GLOBALS['phpgw']->common->phpgw_exit();
				}
			}
			if (!$id_parent)
				$id_parent = $this->bo->so->data['info_id_parent'];

			$common_hidden_vars = $this->html->input_hidden( array(
				'info_id' => $info_id,
				'action' => $action,
				'id_parent' => $id_parent,
				'referer' => $referer,
				'full_fname' => '.'	// must not be empty
			));

			$GLOBALS['phpgw']->common->phpgw_header();

			$GLOBALS['phpgw']->template->set_file(array('info_edit_t' => 'form.tpl'));
			$GLOBALS['phpgw']->template->set_block('info_edit_t','info_edit');

			$GLOBALS['phpgw']->template->set_var('linkto',$this->link->getEntry('linkto','infolog',&$info_id).
			                                              $this->link->showLinks('links','infolog',$info_id));
			if (is_array($error))
			{
				$GLOBALS['phpgw']->template->set_var('error_list',$GLOBALS['phpgw']->common->error_list($error));
			}

			switch ($action)
			{
				case 'sp':
					$info_action = 'InfoLog - New Subproject';
					$GLOBALS['phpgw']->template->set_block('info_edit_t', 'subpro');
					break;
				case 'new': case 'addr': case 'proj': case 'event':
					$info_action = 'InfoLog - New';
					if ($info_type && isset($this->bo->enums['type'][$info_type]))
						$this->bo->so->data['info_type'] = $info_type;
					break;
				default:
					$info_action = 'InfoLog - Edit';
					$is_edit = True;
					break;
			}
			$GLOBALS['phpgw']->template->set_var('lang_info_action',lang($info_action) .
					($query_addr ? ' - '.lang('Search for:')." '$query_addr'" : ''));
			$GLOBALS['phpgw']->template->set_var($this->setStyleSheet( ));
			$GLOBALS['phpgw']->template->set_var('lang_category',lang('Category'));
			$GLOBALS['phpgw']->template->set_var('lang_none',lang('None'));
			if (!isset($info_cat)) $info_cat = $this->bo->so->data['info_cat'];
			$GLOBALS['phpgw']->template->set_var('cat_list',$this->categories->formated_list('select',
																		'all',$info_cat,'True'));

			$GLOBALS['phpgw']->template->set_var('actionurl',$this->html->link('/index.php',
							$this->menuaction('edit')));
			$GLOBALS['phpgw']->template->set_var('common_hidden_vars',$common_hidden_vars);

			// get an instance of select box class
			$sb = CreateObject('infolog.sbox2');

			$GLOBALS['phpgw']->template->set_var('lang_owner',lang('Owner'));
			$GLOBALS['phpgw']->template->set_var('owner_info',$sb->accountInfo($this->bo->so->data['info_owner']));

			$GLOBALS['phpgw']->template->set_var('lang_type',lang('Type'));
			if (!isset($type)) $type = $this->bo->so->data['info_type'];
			if (!$type) $type = 'note';
			$GLOBALS['phpgw']->template->set_var('type_list',$this->html->sbox_submit($sb->getArrayItem(
												'type',$type,$this->bo->enums['type']),True));

			$GLOBALS['phpgw']->template->set_var('lang_prfrom', lang('From'));
			if (!isset($from)) $from =$GLOBALS['phpgw']->strip_html($this->bo->so->data['info_from']);
			$GLOBALS['phpgw']->template->set_var('fromval', $from);

			$GLOBALS['phpgw']->template->set_var('lang_praddr', lang('Phone/Email'));
			if (!isset($addr)) $addr =$GLOBALS['phpgw']->strip_html($this->bo->so->data['info_addr']);
			$GLOBALS['phpgw']->template->set_var('addrval', $addr);

			if (!isset($id_project)) $id_project = $this->bo->so->data['info_proj_id'];
			$GLOBALS['phpgw']->template->set_var($sb->getProject('project',$id_project,$query_project));

			if (!isset($id_addr)) $id_addr = $this->bo->so->data['info_addr_id'];
			$GLOBALS['phpgw']->template->set_var($sb->getAddress('addr',$id_addr,$query_addr));

			if (!isset($id_event)) $id_event = $this->bo->so->data['info_event_id'];
			$GLOBALS['phpgw']->template->set_var($sb->getEvent('event',$id_event,$query_event));

			$GLOBALS['phpgw']->template->set_var('lang_prsubject', lang('Subject'));
			if (!isset($subject)) {
				$subject = $GLOBALS['phpgw']->strip_html($this->bo->so->data['info_subject']);
			}
			$GLOBALS['phpgw']->template->set_var('subjectval', $subject);

			$GLOBALS['phpgw']->template->set_var('lang_prdesc', lang('Description'));
			if (!isset($des)) $des = $GLOBALS['phpgw']->strip_html($this->bo->so->data['info_des']);
			$GLOBALS['phpgw']->template->set_var('descval', $des);

			$GLOBALS['phpgw']->template->set_var('lang_start_date',lang('Startdate'));
			if (!isset($startdate)) $startdate = $this->bo->so->data['info_startdate'];
			$GLOBALS['phpgw']->template->set_var('start_select_date',
							$sb->getDate('syear','smonth','sday',$startdate));

			$GLOBALS['phpgw']->template->set_var('lang_end_date',lang('Enddate'));
			if (!isset($enddate)) $enddate = $this->bo->so->data['info_enddate'];
			$GLOBALS['phpgw']->template->set_var('end_select_date',
							$sb->getDate('eyear','emonth','eday',$enddate));

			$GLOBALS['phpgw']->template->set_var('lang_selfortoday',lang('Today'));
			$GLOBALS['phpgw']->template->set_var('selfortoday',$this->html->checkbox('selfortoday',0));
			$GLOBALS['phpgw']->template->set_var('lang_dur_days',lang('Duration'));
			$GLOBALS['phpgw']->template->set_var('days',lang('days'));

			$GLOBALS['phpgw']->template->set_var('lang_status',lang('Status'));
			if (!isset($status)) $status = $this->bo->so->data['info_status'];
			if (!$status) $status = $this->bo->status['defaults'][$type];
			$GLOBALS['phpgw']->template->set_var('status_list',$sb->getArrayItem('status',$status,
																	$this->bo->status[$type]));

			$GLOBALS['phpgw']->template->set_var('lang_priority',lang('Priority'));
			if (!isset($pri)) $pri = $this->bo->so->data['info_pri'];
			$GLOBALS['phpgw']->template->set_var('priority_list',$sb->getArrayItem('pri',$pri,
																	$this->bo->enums['priority']));

			$GLOBALS['phpgw']->template->set_var('lang_confirm',lang('Confirm'));
			if (!isset($confirm)) $confirm = $this->bo->so->data['info_confirm'];
			$GLOBALS['phpgw']->template->set_var('confirm_list',$sb->getArrayItem('confirm',$confirm,
																	$this->bo->enums['confirm']));

			$GLOBALS['phpgw']->template->set_var('lang_responsible',lang('Responsible'));
			if (!isset($responsible)) $responsible=$this->bo->so->data['info_responsible'];
			$GLOBALS['phpgw']->template->set_var('responsible_list',$sb->getAccount('responsible',
																		  $responsible,0,'accounts',-1));

			$GLOBALS['phpgw']->template->set_var('lang_access_type',lang('Private'));
			if (!isset($access)) $access = $this->bo->so->data['info_access'] == 'private';
			$GLOBALS['phpgw']->template->set_var('access_list',$this->html->checkbox('access',$access));

			$GLOBALS['phpgw']->template->set_var(array('lang_file' => lang('attach file').':','file_val' => $full_fname));
			$GLOBALS['phpgw']->template->set_var(array('lang_comment' => lang('comment').':','comment_val' => $filecomment));

			$GLOBALS['phpgw']->template->set_var('edit_button',$this->html->submit_button('save','Save','this.form.full_fname.value=this.form.attachfile.value'));

			$GLOBALS['phpgw']->template->set_var('cancel_button',$this->html->submit_button('cancel','Cancel'));

			if (!$action && $this->bo->check_access($info_id,PHPGW_ACL_DELETE))
			{
				$GLOBALS['phpgw']->template->set_var('delete_button',$this->html->submit_button('delete','Delete'));
			}
			$GLOBALS['phpgw']->template->fp('phpgw_body','info_edit');
		}

		function old_delete( $id=0 )
		{
			global $info_id,$confirm,$to_del;
			//echo "<p>delete(id=$id): info_id='$info_id', confirm='$confirm', to_del='$to_del'</p>\n";

			$referer = $this->get_referer();

			if ($id)
			{
				$info_id = $id;
			}
			if (!$info_id ||
			    !$this->bo->check_access($info_id,PHPGW_ACL_DELETE))
			{
				Header('Location: ' .  $this->html->link($referer));
				$GLOBALS['phpgw']->common->phpgw_exit();
			}
			if ($confirm && !$id)
			{
				if (!isset($to_del) || $to_del == '.')
				{
					$this->bo->delete($info_id);
				}
				else
				{
					$this->bo->delete_attached($info_id,$to_del);
				}
				Header('Location: ' . $this->html->link($referer,array( 'cd' => 16 )));
				$GLOBALS['phpgw']->common->phpgw_exit();
			}
			else
			{
				$GLOBALS['phpgw']->common->phpgw_header();

				$GLOBALS['phpgw']->template->set_file(array( 'info_delete' => 'delete.tpl' ));

				// add the links to the files which corrospond to this entry
				$attachments = $this->bo->list_attached($info_id);
				if ($attachments)
				{
					$to_del = array('.' => lang('entry and all files'));

					while (list($name,$comment) = each($attachments))
					{
						$to_del[$name] = $name . ($comment ? ' ('.$comment.')' : '');
					}
					$sbox2 = CreateObject('phpgwapi.sbox2');
					$GLOBALS['phpgw']->template->set_var('to_del',$sbox2->getArrayItem('to_del','.',$to_del,True));
				}
				else
				{
					$GLOBALS['phpgw']->template->set_var('to_del','');
				}
				$GLOBALS['phpgw']->template->set_var( $this->setStyleSheet( ));
				$GLOBALS['phpgw']->template->set_var( $this->infoHeaders(  ));
				$GLOBALS['phpgw']->template->set_var( $this->formatInfo( $info_id ));
				$GLOBALS['phpgw']->template->set_var('lang_info_action',lang('InfoLog - Delete'));

				$GLOBALS['phpgw']->template->set_var('deleteheader',lang('Are you sure you want to delete this entry'));
				$GLOBALS['phpgw']->template->set_var('no_button',$this->html->form_1button('no_button',
					'No - Cancel','',$referer));

				$GLOBALS['phpgw']->template->set_var('yes_form',$this->html->form('',array('referer' => $referer),'/index.php',
					$this->menuaction('delete') + array('info_id' => $info_id,'confirm' => 'True')));
				$GLOBALS['phpgw']->template->set_var('yes_button',$this->html->submit_button('yes_button','Yes - Delete'));

				$GLOBALS['phpgw']->template->fp('phpgw_body','info_delete');
			}
		}

		function admin( )
		{
			if(get_var('done',Array('POST')))
			{
				Header('Location: '.$GLOBALS['phpgw']->link('/admin/index.php'));
				$GLOBALS['phpgw']->common->phpgw_exit();
			}

			if(get_var('save',Array('POST')))
			{
				$this->bo->link_pathes = $this->bo->send_file_ips = array();

				$valid = get_var('valid',Array('POST'));
				$trans = get_var('trans',Array('POST'));
				$ip = get_var('ip',Array('POST'));
				while(list($key,$val) = each($valid))
				{
					if($val = stripslashes($val))
					{
						$this->bo->link_pathes[$val]   = stripslashes($trans[$key]);
						$this->bo->send_file_ips[$val] = stripslashes($ip[$key]);
					}
				}
				$this->bo->config->config_data = array(
					'link_pathes' => $this->bo->link_pathes,
					'send_file_ips' => $this->bo->send_file_ips
				);
				$this->bo->config->save_repository(True);
			}

			$GLOBALS['phpgw']->common->phpgw_header();

			$GLOBALS['phpgw']->template->set_file(array('info_admin_t' => 'admin.tpl'));
			$GLOBALS['phpgw']->template->set_block('info_admin_t', 'admin_line');
			$GLOBALS['phpgw']->template->set_block('info_admin_t', 'info_admin');

			$GLOBALS['phpgw']->template->set_var(Array(
				'title' => lang('InfoLog').' - '.lang('configuration'),
				'text' => lang('<b>file-attachments via symlinks</b> instead of uploads and retrieval via file:/path for direct lan-clients'),
				'action_url'  => $this->html->link('/index.php',$this->menuaction('admin')),
				'bg_h_color'  => 'th',
				'save_button' => $this->html->submit_button('save','Save'),
				'done_button' => $this->html->submit_button('done','Done'),
				'lang_valid'  => lang('valid path on clientside<br>eg. \\\\Server\\Share or e:\\'),
				'lang_trans'  => lang('path on (web-)serverside<br>eg. /var/samba/Share'),
				'lang_ip'     => lang('reg. expr. for local IP\'s<br>eg. ^192\\.168\\.1\\.')
			));

			$i = 0; @reset($this->bo->link_pathes);
			do {
				list($valid,$trans) = @each($this->bo->link_pathes);
				$GLOBALS['phpgw']->template->set_var(array(
					'bg_nm_color' => $i & 1 ? 'row_off' : 'row_on',
					'num'       => $i+1,
					'val_valid' => $this->html->input("valid[$i]",$valid),
					'val_trans' => $this->html->input("trans[$i]",$trans),
					'val_ip'    => $this->html->input("ip[$i]",$this->bo->send_file_ips[$valid])
				));
				$GLOBALS['phpgw']->template->parse('admin_lines','admin_line',True);
				++$i;
			} while ($valid);

			if ($this->tmpl->stable)
			{
				echo parse_navbar();
				$GLOBALS['phpgw']->template->pfp('phpgw_body','info_admin');
			}
			else
			{
				$GLOBALS['phpgw']->template->fp('phpgw_body','info_admin');
			}
		}
		
		/*!
		@function writeLangFile
		@abstract writes langfile with all templates and messages registered here
		@discussion called via [write Langfile] in the etemplate-editor or as http://domain/phpgroupware/index.php?menuaction=infolog.uiinfolog.writeLangFile
		*/
		function writeLangFile()
		{
			$extra = $this->messages + $this->filters;
			$enums = $this->bo->enums + $this->bo->status;
			unset($enums['defaults']);
			reset($enums);
			while (list($key,$msg_arr) = each($enums))
			{
				$extra += $msg_arr;
			}
			return $this->tmpl->writeLangFile('infolog','en',$extra);
		}
	}
