<?php
	/**************************************************************************\
	* phpGroupWare - Preferences - categories                                  *
	* http://www.phpgroupware.org                                              *
	* Written by Bettina Gille [ceb@phpgroupware.org]                          *
	* -----------------------------------------------                          *
	* Copyright 2000 - 2003 Free Software Foundation, Inc                      *
	*                                                                          *
	* This program is free software; you can redistribute it and/or modify it  *
	* under the terms of the GNU General Public License as published by the    *
	* Free Software Foundation; either version 2 of the License, or (at your   *
	* option) any later version.                                               *
	\**************************************************************************/
	/* $Id$ */

	class uicategories
	{
		var $bo;

		var $start;
		var $query;
		var $sort;
		var $order;
		var $cat_id;

		var $cats_app;

		var $public_functions = array
		(
			'index'  => True,
			'add'    => True,
			'edit'   => True,
			'delete' => True
		);

		function uicategories()
		{
			$cats_app			= get_var('cats_app',array('GET','POST'));

			$this->bo			= CreateObject('preferences.bocategories',$cats_app);
			$this->nextmatchs	= CreateObject('phpgwapi.nextmatchs');
			$this->account		= $GLOBALS['phpgw_info']['user']['account_id'];
			$this->user			= $GLOBALS['phpgw_info']['user']['fullname'];

			$this->start = $this->bo->start;
			$this->query = $this->bo->query;
			$this->sort  = $this->bo->sort;
			$this->order = $this->bo->order;
		}

		function save_sessiondata($cats_app)
		{
			$data = array
			(
				'start' => $this->start,
				'query' => $this->query,
				'sort'  => $this->sort,
				'order' => $this->order
			);
			$this->bo->save_sessiondata($data,$cats_app);
		}

		function set_langs()
		{
			$GLOBALS['phpgw']->template->set_var('th_bg',$GLOBALS['phpgw_info']['theme']['th_bg']);
			$GLOBALS['phpgw']->template->set_var('row_on',$GLOBALS['phpgw_info']['theme']['row_on']);
			$GLOBALS['phpgw']->template->set_var('row_off',$GLOBALS['phpgw_info']['theme']['row_off']);
			$GLOBALS['phpgw']->template->set_var('lang_access',lang('Private'));
			$GLOBALS['phpgw']->template->set_var('lang_save',lang('Save'));
			$GLOBALS['phpgw']->template->set_var('user_name',$this->user);
			$GLOBALS['phpgw']->template->set_var('lang_search',lang('Search'));
			$GLOBALS['phpgw']->template->set_var('lang_cancel',lang('Cancel'));
			$GLOBALS['phpgw']->template->set_var('lang_done',lang('done'));
			$GLOBALS['phpgw']->template->set_var('lang_sub',lang('Add sub'));
			$GLOBALS['phpgw']->template->set_var('lang_edit',lang('Edit'));
			$GLOBALS['phpgw']->template->set_var('lang_delete',lang('Delete'));
			$GLOBALS['phpgw']->template->set_var('lang_parent',lang('Parent category'));
			$GLOBALS['phpgw']->template->set_var('lang_none',lang('None'));
			$GLOBALS['phpgw']->template->set_var('lang_name',lang('Name'));
			$GLOBALS['phpgw']->template->set_var('lang_descr',lang('Description'));
			$GLOBALS['phpgw']->template->set_var('lang_add',lang('Add'));
			$GLOBALS['phpgw']->template->set_var('lang_reset',lang('Clear Form'));
		}

		function cat_data($edata,$data)
		{
			for ($j=0;$j<count($edata);$j++)
			{
				$td_data .= '<td>' . $data[$edata[$j]] . '</td>' . "\n";
			}
			return $td_data;
		}

		function index()
		{
			$cats_app    = get_var('cats_app',array('GET','POST'));
			$extra       = get_var('extra',array('GET','POST'));
			$global_cats = get_var('global_cats',array('GET','POST'));
			$cats_level  = get_var('cats_level',array('GET','POST'));

			$link_data = array
			(
				'menuaction'  => 'preferences.uicategories.index',
				'cats_app'    => $cats_app,
				'extra'       => $extra,
				'global_cats' => $global_cats,
				'cats_level'  => $cats_level
			);

			if($extra)
			{
				$edata = explode(',',$extra);
			}
			$GLOBALS['phpgw_info']['flags']['app_header'] = $GLOBALS['phpgw_info']['apps'][$cats_app]['title'].
				'&nbsp;'.lang('categories for').':&nbsp;'.$this->user;
			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();

			$GLOBALS['phpgw']->template->set_file(array('cat_list_t'  => 'listcats.tpl',
														'data_column' => 'listcats.tpl'));
			$GLOBALS['phpgw']->template->set_block('cat_list_t','data_column','column');
			$GLOBALS['phpgw']->template->set_block('cat_list_t','cat_list','list');

			$this->set_langs();

			$GLOBALS['phpgw']->template->set_var('title_categories',lang('categories for'));
			$GLOBALS['phpgw']->template->set_var('lang_app',lang($cats_app));
			$GLOBALS['phpgw']->template->set_var('actionurl',$GLOBALS['phpgw']->link('/index.php',$link_data));
			$GLOBALS['phpgw']->template->set_var('doneurl',$GLOBALS['phpgw']->link('/preferences/index.php'));

			if(!$this->start)
			{
				$this->start = 0;
			}

			if(!$global_cats)
			{
				$global_cats = False;
			}

			$cats = $this->bo->get_list($global_cats);

//--------------------------------- nextmatch --------------------------------------------

			$left  = $this->nextmatchs->left('/index.php',$this->start,$this->bo->cats->total_records,$link_data);
			$right = $this->nextmatchs->right('/index.php',$this->start,$this->bo->cats->total_records,$link_data);
			$GLOBALS['phpgw']->template->set_var('left',$left);
			$GLOBALS['phpgw']->template->set_var('right',$right);

			$GLOBALS['phpgw']->template->set_var('lang_showing',$this->nextmatchs->show_hits($this->bo->cats->total_records,$this->start));

// ------------------------------ end nextmatch ------------------------------------------

//------------------- list header variable template-declarations ------------------------- 

			$GLOBALS['phpgw']->template->set_var('sort_name',$this->nextmatchs->show_sort_order($this->sort,'cat_name',$this->order,'/index.php',lang('Name'),$link_data));
			$GLOBALS['phpgw']->template->set_var('sort_description',$this->nextmatchs->show_sort_order($this->sort,'cat_description',$this->order,'/index.php',lang('Description'),$link_data));

			if (is_array($edata))
			{
				for($i=0;$i<count($edata);$i++)
				{
					$GLOBALS['phpgw']->template->set_var('th_data','<td bgcolor="' . $GLOBALS['phpgw_info']['theme']['th_bg'] . '">' . lang($edata[$i]) . '</td>');
					$GLOBALS['phpgw']->template->fp('column','data_column',True);
				}
			}
			else
			{
				$GLOBALS['phpgw']->template->set_var('th_data','');
			}

// -------------------------- end header declaration --------------------------------------

			for ($i=0;$i<count($cats);$i++)
			{
				$this->nextmatchs->template_alternate_row_color(&$GLOBALS['phpgw']->template);

				if ($cats[$i]['app_name'] == 'phpgw')
				{
					$appendix = '&lt;' . lang('Global') . '&gt;';
				}
				elseif ($cats[$i]['owner'] == '-1')
				{
					$appendix = '&lt;' . lang('Global') . '&nbsp;' . $GLOBALS['phpgw_info']['apps'][$cats_app]['title'] . '&gt;';
				}
				else
				{
					$appendix = '';
				}

				$level = $cats[$i]['level'];

				if ($level > 0)
				{
					$space = '&nbsp;&nbsp;';
					$spaceset = str_repeat($space,$level);
					$name = $spaceset . $GLOBALS['phpgw']->strip_html($cats[$i]['name']) . $appendix;
				}

				$descr = $GLOBALS['phpgw']->strip_html($cats[$i]['description']);
				if (!$descr) { $descr = '&nbsp;'; }

				if (is_array($edata))
				{
					$data = unserialize($cats[$i]['data']);
					if (! is_array($data))
					{
						$holder = '<td>&nbsp;</td>' . "\n";
						$placeholder = str_repeat($holder,count($edata));
						$GLOBALS['phpgw']->template->set_var('td_data',$placeholder);
					}
					else
					{
						$GLOBALS['phpgw']->template->set_var('td_data',$this->cat_data($edata,$data));
					}
				}

				if ($level == 0)
				{
					$name = '<font color="FF0000"><b>' . $GLOBALS['phpgw']->strip_html($cats[$i]['name']) . '</b></font>' . $appendix;
					$descr = '<font color="FF0000"><b>' . $descr . '</b></font>';
				}

				$GLOBALS['phpgw']->template->set_var(array(
					'name'  => $name,
					'descr' => $descr
				));

				$GLOBALS['phpgw']->template->set_var('app_url',$GLOBALS['phpgw']->link('/' . $cats_app . '/index.php','cat_id=' . $cats[$i]['id']));

				if ($cats_level || ($level == 0))
				{
					if ($cats[$i]['owner'] == $this->account || $cats[$i]['app_name'] == 'phpgw')
					{
						$link_data['menuaction'] = 'preferences.uicategories.add';
						$link_data['cat_parent'] = $cats[$i]['id'];
						$GLOBALS['phpgw']->template->set_var('add_sub',$GLOBALS['phpgw']->link('/index.php',$link_data));
						$GLOBALS['phpgw']->template->set_var('lang_sub_entry',lang('Add sub'));
					}
				}
				else
				{
					$GLOBALS['phpgw']->template->set_var('add_sub','');
					$GLOBALS['phpgw']->template->set_var('lang_sub_entry','&nbsp;');
				}

				$link_data['cat_id'] = $cats[$i]['id'];
				if ($cats[$i]['owner'] == $this->account && $cats[$i]['app_name'] != 'phpgw')
				{
					$link_data['menuaction'] = 'preferences.uicategories.edit';
					$GLOBALS['phpgw']->template->set_var('edit',$GLOBALS['phpgw']->link('/index.php',$link_data));
					$GLOBALS['phpgw']->template->set_var('lang_edit_entry',lang('Edit'));

					$link_data['menuaction'] = 'preferences.uicategories.delete';
					$GLOBALS['phpgw']->template->set_var('delete',$GLOBALS['phpgw']->link('/index.php',$link_data));
					$GLOBALS['phpgw']->template->set_var('lang_delete_entry',lang('Delete'));
				}
				else
				{
					$GLOBALS['phpgw']->template->set_var('edit','');
					$GLOBALS['phpgw']->template->set_var('lang_edit_entry','&nbsp;');

					$GLOBALS['phpgw']->template->set_var('delete','');
					$GLOBALS['phpgw']->template->set_var('lang_delete_entry','&nbsp;');
				}
				$GLOBALS['phpgw']->template->fp('list','cat_list',True);
			}
			$link_data['menuaction'] = 'preferences.uicategories.add';
			$GLOBALS['phpgw']->template->set_var('add_action',$GLOBALS['phpgw']->link('/index.php',$link_data));
			$this->save_sessiondata($cats_app);

			$GLOBALS['phpgw']->template->pfp('out','cat_list_t',True);
		}

		function add()
		{
			$cats_app    = get_var('cats_app',array('GET','POST'));
			$extra       = get_var('extra',array('GET','POST'));
			$global_cats = get_var('global_cats',array('GET','POST'));
			$cats_level  = get_var('cats_level',array('GET','POST'));

			$link_data = array
			(
				'menuaction'  => 'preferences.uicategories.add',
				'cats_app'    => $cats_app,
				'extra'       => $extra,
				'global_cats' => $global_cats,
				'cats_level'  => $cats_level
			);

			$GLOBALS['phpgw_info']['flags']['app_header'] = lang('Add %1 category for',
				$GLOBALS['phpgw_info']['apps'][$cats_app]['title']).':&nbsp;'.$this->user;
			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();

			$new_parent      = $_POST['new_parent'];
			$cat_parent      = get_var('cat_parent',array('POST','GET'));
			$cat_name        = $_POST['cat_name'];
			$cat_description = $_POST['cat_description'];
			$cat_data        = $_POST['cat_data'];
			$cat_access      = $_POST['cat_access'];

			$GLOBALS['phpgw']->template->set_file(array('form' => 'category_form.tpl'));
			$GLOBALS['phpgw']->template->set_block('form','data_row','row');
			$GLOBALS['phpgw']->template->set_block('form','add','addhandle');
			$GLOBALS['phpgw']->template->set_block('form','edit','edithandle');

			$this->set_langs();

			if ($new_parent)
			{
				$cat_parent = $new_parent;
			}

			if (!$global_cats)
			{
				$global_cats = False;
			}

			if ($_POST['save'])
			{
				$data = serialize($cat_data);

				$values = array
				(
					'parent' => $cat_parent,
					'descr'  => $cat_description,
					'name'   => $cat_name,
					'access' => $cat_access,
					'data'   => $data
				);

				$error = $this->bo->check_values($values);
				if (is_array($error))
				{
					$GLOBALS['phpgw']->template->set_var('message',$GLOBALS['phpgw']->common->error_list($error));
				}
				else
				{
					$this->bo->save_cat($values);
					$GLOBALS['phpgw']->template->set_var('message',lang('Category %1 has been added !', $cat_name));
				}
			}

			$GLOBALS['phpgw']->template->set_var('actionurl',$GLOBALS['phpgw']->link('/index.php',$link_data));

			if ($cats_level)
			{
				$type = 'all';
			}
			else
			{
				$type = 'mains';
			}

			$GLOBALS['phpgw']->template->set_var('category_list',$this->bo->cats->formated_list('select',$type,$cat_parent,$global_cats));
			$GLOBALS['phpgw']->template->set_var('cat_name',$cat_name);
			$GLOBALS['phpgw']->template->set_var('cat_description',$cat_description);

			$GLOBALS['phpgw']->template->set_var('access','<input type="checkbox" name="cat_access" value="True"'
				. ($cat_access == True ?' checked':'') . '>');

			if ($extra)
			{
				$edata = explode(',',$extra);
				for($i=0;$i<count($edata);$i++)
				{
					$GLOBALS['phpgw']->template->set_var('tr_color',$GLOBALS['phpgw']->nextmatchs->alternate_row_color());
					$GLOBALS['phpgw']->template->set_var('td_data','<input name="cat_data[' . $edata[$i] . ']" size="50" value="' . $cat_data[$edata[$i]] . '">');
					$GLOBALS['phpgw']->template->set_var('lang_data',lang($edata[$i]));
					$GLOBALS['phpgw']->template->fp('row','data_row',True);
				}
			}

			$link_data['menuaction'] = 'preferences.uicategories.index';
			$GLOBALS['phpgw']->template->set_var('cancel_url',$GLOBALS['phpgw']->link('/index.php',$link_data));
			$GLOBALS['phpgw']->template->set_var('edithandle','');
			$GLOBALS['phpgw']->template->set_var('addhandle','');
			$GLOBALS['phpgw']->template->pfp('out','form');
			$GLOBALS['phpgw']->template->pfp('addhandle','add');
		}

		function edit()
		{
			$cats_app    = get_var('cats_app',array('GET','POST'));
			$extra       = get_var('extra',array('GET','POST'));
			$global_cats = get_var('global_cats',array('GET','POST'));
			$cats_level  = get_var('cats_level',array('GET','POST'));
			$cat_id      = get_var('cat_id',array('GET','POST'));

			$link_data = array
			(
				'menuaction'	=> 'preferences.uicategories.index',
				'cats_app'		=> $cats_app,
				'extra'			=> $extra,
				'global_cats'	=> $global_cats,
				'cats_level'	=> $cats_level,
				'cat_id'		=> $cat_id
			);

			if (!$cat_id)
			{
				$GLOBALS['phpgw']->link_redirect('/index.php',$link_data);
			}

			$GLOBALS['phpgw_info']['flags']['app_header'] = lang('Edit %1 category for',
				$GLOBALS['phpgw_info']['apps'][$cats_app]['title']).':&nbsp;'.$this->user;
			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();

			$new_parent			= $_POST['new_parent'];
			$cat_parent			= $_POST['cat_parent'];
			$cat_name			= $_POST['cat_name'];
			$cat_description	= $_POST['cat_description'];
			$cat_data			= $_POST['cat_data'];
			$cat_access			= $_POST['cat_access'];
			$old_parent			= $_POST['old_parent'];

			$GLOBALS['phpgw']->template->set_file(array('form' => 'category_form.tpl'));
			$GLOBALS['phpgw']->template->set_block('form','data_row','row');
			$GLOBALS['phpgw']->template->set_block('form','add','addhandle');
			$GLOBALS['phpgw']->template->set_block('form','edit','edithandle');

			$this->set_langs();
			$GLOBALS['phpgw']->template->set_var('cancel_url',$GLOBALS['phpgw']->link('/index.php',$link_data));

			if ($new_parent)
			{
				$cat_parent = $new_parent;
			}

			if (!$global_cats)
			{
				$global_cats = False;
			}

			if ($_POST['save'])
			{
				$data = serialize($cat_data);

				$values = array
				(
					'id'			=> $cat_id,
					'parent'		=> $cat_parent,
					'descr'			=> $cat_description,
					'name'			=> $cat_name,
					'access'		=> $cat_access,
					'data'			=> $data,
					'old_parent'	=> $old_parent
				);

				$error = $this->bo->check_values($values);
				if (is_array($error))
				{
					$GLOBALS['phpgw']->template->set_var('message',$GLOBALS['phpgw']->common->error_list($error));
				}
				else
				{
					$cat_id = $this->bo->save_cat($values);
					$GLOBALS['phpgw']->template->set_var('message',lang('Category %1 has been updated !',$cat_name));
				}
			}

			$cats = $this->bo->cats->return_single($cat_id);

			$link_data['menuaction'] = 'preferences.uicategories.edit';
			$GLOBALS['phpgw']->template->set_var('actionurl',$GLOBALS['phpgw']->link('/index.php',$link_data));

			$GLOBALS['phpgw']->template->set_var('cat_name',$GLOBALS['phpgw']->strip_html($cats[0]['name']));
			$GLOBALS['phpgw']->template->set_var('cat_description',$GLOBALS['phpgw']->strip_html($cats[0]['description']));

			$GLOBALS['phpgw']->template->set_var('hidden_vars','<input type="hidden" name="old_parent" value="' . $cats[0]['parent'] . '">');

			if ($cats_level)
			{
				$type = 'all';
			}
			else
			{
				$type = 'mains';
			}

			$GLOBALS['phpgw']->template->set_var('category_list',$this->bo->cats->formated_list(array('type' => $type,'selected' => $cats[0]['parent'],
																									'globals' => $global_cats, 'self' => $cat_id)));

			$GLOBALS['phpgw']->template->set_var('access','<input type="checkbox" name="cat_access" value="True"'
												. ($cats[0]['access'] == private ?' checked':'') . '>');

			if ($extra)
			{
				$edata = explode(',',$extra);

				$data = unserialize($cats[0]['data']);
				for($i=0;$i<count($edata);$i++)
				{
					$GLOBALS['phpgw']->template->set_var('td_data','<input name="cat_data[' . $edata[$i] . ']" size="50" value="' . $data[$edata[$i]] . '">');
					$GLOBALS['phpgw']->template->set_var('lang_data',lang($edata[$i]));
					$GLOBALS['phpgw']->template->fp('row','data_row',True);
				}
			}

			if ($cats[0]['owner'] == $this->account)
			{
				$link_data['menuaction'] = 'preferences.uicategories.delete';
				$GLOBALS['phpgw']->template->set_var('delete','<form method="POST" action="' . $GLOBALS['phpgw']->link('/index.php',$link_data)
					. '"><input type="submit" value="' . lang('Delete') .'"></form>');
			}
			else
			{
				$GLOBALS['phpgw']->template->set_var('delete','&nbsp;');
			}

			$GLOBALS['phpgw']->template->set_var('edithandle','');
			$GLOBALS['phpgw']->template->set_var('addhandle','');
			$GLOBALS['phpgw']->template->pfp('out','form');
			$GLOBALS['phpgw']->template->pfp('edithandle','edit');
		}

		function delete()
		{
			$cats_app    = get_var('cats_app',array('GET','POST'));
			$extra       = get_var('extra',array('GET','POST'));
			$global_cats = get_var('global_cats',array('GET','POST'));
			$cats_level  = get_var('cats_level',array('GET','POST'));
			$cat_id      = get_var('cat_id',array('GET','POST'));

			$link_data = array
			(
				'menuaction'  => 'preferences.uicategories.index',
				'cats_app'    => $cats_app,
				'extra'       => $extra,
				'global_cats' => $global_cats,
				'cats_level'  => $cats_level,
				'cat_id'      => $cat_id
			);

			if (!$cat_id || $_POST['cancel'])
			{
				$GLOBALS['phpgw']->redirect_link('/index.php',$link_data);
			}

			if ($_POST['confirm'])
			{
				if ($_POST['subs'])
				{
					$this->bo->delete($cat_id,True);
				}
				else
				{
					$this->bo->delete($cat_id,False);
				}
				$GLOBALS['phpgw']->redirect_link('/index.php',$link_data);
			}
			else
			{
				$GLOBALS['phpgw']->template->set_file(array('category_delete' => 'delete.tpl'));

				$GLOBALS['phpgw_info']['flags']['app_header'] = lang('Delete Categories');
				$GLOBALS['phpgw']->common->phpgw_header();
				echo parse_navbar();

				$GLOBALS['phpgw']->template->set_var('deleteheader',lang('Are you sure you want to delete this category ?'));

				$exists = $this->bo->exists(array
				(
					'type'     => 'subs',
					'cat_name' => '',
					'cat_id'   => $cat_id
				));

				if ($exists)
				{
					$GLOBALS['phpgw']->template->set_var('lang_subs',lang('Do you also want to delete all subcategories ?'));
					$GLOBALS['phpgw']->template->set_var('subs','<input type="checkbox" name="subs" value="True">');
				}
				else
				{
					$GLOBALS['phpgw']->template->set_var('lang_subs','');
					$GLOBALS['phpgw']->template->set_var('subs', '');
				}

				$GLOBALS['phpgw']->template->set_var('lang_no',lang('No'));
				$link_data['menuaction'] = 'preferences.uicategories.delete';
				$GLOBALS['phpgw']->template->set_var('action_url',$GLOBALS['phpgw']->link('/index.php',$link_data));
				$GLOBALS['phpgw']->template->set_var('lang_yes',lang('Yes'));
				$GLOBALS['phpgw']->template->pfp('out','category_delete');
			}
		}
	}
?>
