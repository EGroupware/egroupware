<?php
  /**************************************************************************\
  * phpGroupWare - Preferences - categories                                  *
  * http://www.phpgroupware.org                                              *
  * Written by Bettina Gille [ceb@phpgroupware.org]                          *
  * -----------------------------------------------                          *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
  /* $Id$ */

	class uicategories
	{
		var $bo;
		var $t;

		var $start;
		var $query;
		var $sort;
		var $order;
		var $cat_id;

		var $public_functions = array
		(
			'index'  => True,
			'add'    => True,
			'edit'   => True,
			'delete' => True
		);

		function uicategories()
		{
			$this->bo			= CreateObject('preferences.bocategories',$cats_app);
			$this->t 			= $GLOBALS['phpgw']->template;
			$this->nextmatchs	= CreateObject('phpgwapi.nextmatchs');
			$this->account		= $GLOBALS['phpgw_info']['user']['account_id'];
			$this->user			= $GLOBALS['phpgw_info']['user']['fullname'];

			$this->start		= $this->bo->start;
			$this->query		= $this->bo->query;
			$this->sort			= $this->bo->sort;
			$this->order		= $this->bo->order;
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
			$this->t->set_var('lang_access',lang('Private'));
			$this->t->set_var('lang_save',lang('Save'));
			$this->t->set_var('user_name',$this->user);
			$this->t->set_var('lang_search',lang('Search'));
			$this->t->set_var('lang_done',lang('Done'));
			$this->t->set_var('lang_sub',lang('Add sub'));
			$this->t->set_var('lang_edit',lang('Edit'));
			$this->t->set_var('lang_delete',lang('Delete'));
			$this->t->set_var('lang_parent',lang('Parent category'));
			$this->t->set_var('lang_none',lang('None'));
			$this->t->set_var('lang_name',lang('Name'));
			$this->t->set_var('lang_descr',lang('Description'));
			$this->t->set_var('lang_add',lang('Add'));
			$this->t->set_var('lang_reset',lang('Clear Form'));
		}

		function index()
		{
			global $cats_app, $extra, $global_cats, $cats_level;

			$link_data = array
			(
				'menuaction'	=> 'preferences.uicategories.index',
				'cats_app'		=> $cats_app,
				'extra'			=> $extra,
				'global_cats'	=> $global_cats,
				'cats_level'	=> $cats_level
			);

			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();

			$this->t->set_file(array('cat_list_t' => 'listcats.tpl'));
			$this->t->set_block('cat_list_t','cat_list','list');

			$this->set_langs();

			$this->t->set_var('title_categories',lang('categories for'));
			$this->t->set_var('lang_app',lang($cats_app));
			$this->t->set_var('actionurl',$GLOBALS['phpgw']->link('/index.php',$link_data));
			$this->t->set_var('doneurl',$GLOBALS['phpgw']->link('/preferences/index.php'));

			if(!$this->start)
			{
				$this->start = 0;
			}

			$this->bo->cats->app_name = $cats_app;
			$cats = $this->bo->get_list();

//--------------------------------- nextmatch --------------------------------------------

			$left  = $this->nextmatchs->left('/index.php',$this->start,$this->bo->cats->total_records,$link_data);
			$right = $this->nextmatchs->right('/index.php',$this->start,$this->bo->cats->total_records,$link_data);
			$this->t->set_var('left',$left);
			$this->t->set_var('right',$right);

			$this->t->set_var('lang_showing',$this->nextmatchs->show_hits($this->bo->cats->total_records,$this->start));

// ------------------------------ end nextmatch ------------------------------------------

//------------------- list header variable template-declarations ------------------------- 

			$this->t->set_var('th_bg',$GLOBALS['phpgw_info']['theme']['th_bg']);
			$this->t->set_var('sort_name',$this->nextmatchs->show_sort_order($this->sort,'cat_name',$this->order,'/index.php',lang('Name'),$link_data));
			$this->t->set_var('sort_description',$this->nextmatchs->show_sort_order($this->sort,'cat_description',$this->order,'/index.php',lang('Description'),$link_data));

			if ($extra)
			{
				$this->t->set_var('sort_data','<td bgcolor="' . $GLOBALS['phpgw_info']['theme']['th_bg'] . '">'
											. $this->nextmatchs->show_sort_order($this->sort,'cat_data',
												$this->order,'/index.php',lang($extra),$link_data) . '</td>');
			}
			else
			{
				$this->t->set_var('sort_data','');
			}

// -------------------------- end header declaration --------------------------------------

			for ($i=0;$i<count($cats);$i++)
			{
				$this->nextmatchs->template_alternate_row_color(&$this->t);

				if ($cats[$i]['app_name'] == 'phpgw')
				{
					$appendix = '&lt;' . lang('Global') . '&gt;';
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

				if ($extra)
				{
					$data = $cats[$i]['data'];
					if (! $data)
					{
						$data  = '&nbsp;';
					}
					$this->t->set_var('td_data','<td>' . $data . '</td>');
				}
				else
				{
					$this->t->set_var('td_data','');
				}

				if ($level == 0)
				{
					$name = '<font color="FF0000"><b>' . $GLOBALS['phpgw']->strip_html($cats[$i]['name']) . '</b></font>' . $appendix;
					$descr = '<font color="FF0000"><b>' . $descr . '</b></font>';
					$data = '<font color="FF0000"><b>' . $data . '</b></font>';
				}

				$this->t->set_var(array('name' => $name,
										'descr' => $descr));

				$this->t->set_var('app_url',$GLOBALS['phpgw']->link('/' . $cats_app . '/index.php','cat_id=' . $cats[$i]['id']));

				if ($cats_level || ($level == 0))
				{
					if ($cats[$i]['owner'] == $this->account || $cats[$i]['app_name'] == 'phpgw')
					{
						$link_data['menuaction']	= 'preferences.uicategories.add';
						$link_data['cat_parent']	= $cats[$i]['id'];
						$this->t->set_var('add_sub',$GLOBALS['phpgw']->link('/index.php',$link_data));
						$this->t->set_var('lang_sub_entry',lang('Add sub'));
					}
				}
				else
				{
					$t->set_var('add_sub','');
					$t->set_var('lang_sub_entry','&nbsp;');
				}

				$link_data['cat_id'] = $cats[$i]['id'];
				if ($cats[$i]['owner'] == $this->account && $cats[$i]['app_name'] != 'phpgw')
				{
					$link_data['menuaction'] = 'preferences.uicategories.edit';
					$this->t->set_var('edit',$GLOBALS['phpgw']->link('/index.php',$link_data));
					$this->t->set_var('lang_edit_entry',lang('Edit'));

					$link_data['menuaction'] = 'preferences.uicategories.delete';
					$this->t->set_var('delete',$GLOBALS['phpgw']->link('/index.php',$link_data));
					$this->t->set_var('lang_delete_entry',lang('Delete'));
				}
				else
				{
					$this->t->set_var('edit','');
					$this->t->set_var('lang_edit_entry','&nbsp;');

					$this->t->set_var('delete','');
					$this->t->set_var('lang_delete_entry','&nbsp;');
				}
				$this->t->fp('list','cat_list',True);
			}
			$link_data['menuaction'] = 'preferences.uicategories.add';
			$this->t->set_var('add_action',$GLOBALS['phpgw']->link('/index.php',$link_data));
			$this->save_sessiondata($cats_app);

			$this->t->pfp('out','cat_list_t',True);
		}

		function add()
		{
			global $cats_app, $extra, $global_cats, $cats_level;

			$link_data = array
			(
				'menuaction'	=> 'preferences.uicategories.add',
				'cats_app'		=> $cats_app,
				'extra'			=> $extra,
				'global_cats'	=> $global_cats,
				'cats_level'	=> $cats_level
			);

			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();

			$new_parent			= $GLOBALS['HTTP_POST_VARS']['new_parent'];
			$submit				= $GLOBALS['HTTP_POST_VARS']['submit'];
			$cat_parent			= $GLOBALS['HTTP_POST_VARS']['cat_parent'] ? $GLOBALS['HTTP_POST_VARS']['cat_parent'] : $GLOBALS['HTTP_GET_VARS']['cat_parent'];
			$cat_name			= $GLOBALS['HTTP_POST_VARS']['cat_name'];
			$cat_description	= $GLOBALS['HTTP_POST_VARS']['cat_description'];
			$cat_data			= $GLOBALS['HTTP_POST_VARS']['cat_data'];
			$cat_access			= $GLOBALS['HTTP_POST_VARS']['cat_access'];

			$this->t->set_file(array('form' => 'category_form.tpl'));
			$this->t->set_block('form','add','addhandle');
			$this->t->set_block('form','edit','edithandle');

			$this->set_langs();

			$this->bo->cats->app_name = $cats_app;

			if ($new_parent)
			{
				$cat_parent = $new_parent;
			}

			if ($submit)
			{
				$values = array
				(
					'id'		=> '',
					'parent'	=> $cat_parent,
					'descr'		=> $cat_description,
					'name'		=> $cat_name,
					'access'	=> $cat_access,
					'data'		=> $cat_data
				);

				$error = $this->bo->check_values($values);
				if (is_array($error))
				{
					$this->t->set_var('message',$GLOBALS['phpgw']->common->error_list($error));
				}
				else
				{
					$this->bo->save_cat($values);
					$this->t->set_var('message',lang('Category x has been added !', $cat_name));
				}
			}

			$this->t->set_var('title_categories',lang('Add x category for',lang($cats_app)));
			$this->t->set_var('lang_app',lang($cats_app));
			$this->t->set_var('actionurl',$GLOBALS['phpgw']->link('/index.php',$link_data));

			if ($cats_level)
			{
				if ($global_cats)
				{
					$cats_list = $this->bo->cats->formated_list('select','all',$cat_parent,True);
				}
				else
				{
					$cats_list = $this->bo->cats->formated_list('select','all',$cat_parent);
				}
			}
			else
			{
				if ($global_cats)
				{
					$cats_list = $this->bo->cats->formated_list('select','mains',$cat_parent,True);
				}
				else
				{
					$cats_list = $this->bo->cats->formated_list('select','mains',$cat_parent);
				}
			}

			$this->t->set_var('category_list',$cats_list);
			$this->t->set_var('cat_name',$cat_name);
			$this->t->set_var('cat_description',$cat_description);

			$this->t->set_var('access','<input type="checkbox" name="cat_access" value="True"'
										. ($cat_access == True ?' checked':'') . '>');

			if ($extra)
			{
				$this->t->set_var('td_data','<input name="cat_data" size="50" value="' . $GLOBALS['phpgw']->strip_html($cat_data) . '">');
				$this->t->set_var('lang_data',lang($extra));
			}
			else
			{
				$this->t->set_var('td_data','');
				$this->t->set_var('lang_data','');
			}

			$link_data['menuaction']	= 'preferences.uicategories.index';
			$this->t->set_var('doneurl',$GLOBALS['phpgw']->link('/index.php',$link_data));
			$this->t->set_var('edithandle','');
			$this->t->set_var('addhandle','');
			$this->t->pfp('out','form');
			$this->t->pfp('addhandle','add');
		}

		function edit()
		{
			global $cats_app, $extra, $global_cats, $cats_level, $cat_id;

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
				Header('Location: ' . $GLOBALS['phpgw']->link('/index.php',$link_data));
			}

			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();

			$new_parent			= $GLOBALS['HTTP_POST_VARS']['new_parent'];
			$submit				= $GLOBALS['HTTP_POST_VARS']['submit'];
			$cat_parent			= $GLOBALS['HTTP_POST_VARS']['cat_parent'];
			$cat_name			= $GLOBALS['HTTP_POST_VARS']['cat_name'];
			$cat_description	= $GLOBALS['HTTP_POST_VARS']['cat_description'];
			$cat_data			= $GLOBALS['HTTP_POST_VARS']['cat_data'];
			$cat_access			= $GLOBALS['HTTP_POST_VARS']['cat_access'];

			$this->t->set_file(array('form' => 'category_form.tpl'));
			$this->t->set_block('form','add','addhandle');
			$this->t->set_block('form','edit','edithandle');

			$this->set_langs();
			$this->t->set_var('doneurl',$GLOBALS['phpgw']->link('/index.php',$link_data));
			$this->bo->cats->app_name = $cats_app;

			if ($new_parent)
			{
				$cat_parent = $new_parent;
			}

			if ($submit)
			{
				$values = array
				(
					'id'		=> $cat_id,
					'parent'	=> $cat_parent,
					'descr'		=> $cat_description,
					'name'		=> $cat_name,
					'access'	=> $cat_access,
					'data'		=> $cat_data
				);

				$error = $this->bo->check_values($values);
				if (is_array($error))
				{
					$this->t->set_var('message',$GLOBALS['phpgw']->common->error_list($error));
				}
				else
				{
					$this->bo->save_cat($values);
					$this->t->set_var('message',lang('Category x has been updated !',$cat_name));
				}
			}

			$cats = $this->bo->cats->return_single($cat_id);

			$this->t->set_var('title_categories',lang('Edit x category for',lang($cats_app)));
			$this->t->set_var('lang_app',lang($cats_app));
			$link_data['menuaction']	= 'preferences.uicategories.edit';
			$this->t->set_var('actionurl',$GLOBALS['phpgw']->link('/index.php',$link_data));

			$this->t->set_var('cat_name',$GLOBALS['phpgw']->strip_html($cats[0]['name']));
			$this->t->set_var('cat_description',$GLOBALS['phpgw']->strip_html($cats[0]['description']));

			if ($cats_level) 
			{
				if ($global_cats)
				{
					$cats_list = $this->bo->cats->formated_list('select','all',$cats[0]['parent'],True);
				}
				else
				{
					$cats_list = $this->bo->cats->formated_list('select','all',$cats[0]['parent']);
				}
			}
			else
			{
				if ($global_cats)
				{
					$cats_list = $this->bo->cats->formated_list('select','mains',$cats[0]['parent'],True);
				}
				else
				{
					$cats_list = $this->bo->cats->formated_list('select','mains',$cats[0]['parent']);
				}
			}

			$this->t->set_var('category_list',$cats_list);

			$this->t->set_var('access','<input type="checkbox" name="cat_access" value="True"'
										. ($cat_access == True ?' checked':'') . '>');

			if ($cats[0]['owner'] == $this->account)
			{
				$link_data['menuaction']	= 'preferences.uicategories.delete';
				$this->t->set_var('delete','<form method="POST" action="' . $GLOBALS['phpgw']->link('/index.php',$link_data)
											. '"><input type="submit" value="' . lang('Delete') .'"></form>');
			}
			else
			{
				$this->t->set_var('delete','&nbsp;');
			}

			$this->t->set_var('edithandle','');
			$this->t->set_var('addhandle','');
			$this->t->pfp('out','form');
			$this->t->pfp('edithandle','edit');
		}

		function delete()
		{
			global $cats_app, $extra, $global_cats, $cats_level, $cat_id;

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
				Header('Location: ' . $GLOBALS['phpgw']->link('/index.php',$link_data));
			}

			$this->bo->cats->app_name = $cats_app;

			if ($GLOBALS['HTTP_POST_VARS']['confirm'])
			{
				if ($GLOBALS['HTTP_POST_VARS']['subs'])
				{
					$this->bo->delete($cat_id,True);
				}
				else
				{
					$this->bo->delete($cat_id,False);
				}
				Header('Location: ' . $GLOBALS['phpgw']->link('/index.php',$link_data));
			}
			else
			{
				$this->t->set_file(array('category_delete' => 'delete.tpl'));

				$nolink = $GLOBALS['phpgw']->link('/index.php',$link_data);

				$GLOBALS['phpgw']->common->phpgw_header();
				echo parse_navbar();

				$this->t->set_var('deleteheader',lang('Are you sure you want to delete this category ?'));

				$exists = $this->bo->exists(array
				(
					'type'     => 'subs',
					'cat_name' => '',
					'cat_id'   => $cat_id
				));

				if ($exists)
				{
					$this->t->set_var('lang_subs',lang('Do you also want to delete all subcategories ?'));
					$this->t->set_var('subs','<input type="checkbox" name="subs" value="True">');
				}
				else
				{
					$this->t->set_var('lang_subs','');
					$this->t->set_var('subs', '');
				}

				$this->t->set_var('nolink',$nolink);
				$this->t->set_var('lang_no',lang('No'));
				$link_data['menuaction']	= 'preferences.uicategories.delete';
				$this->t->set_var('action_url',$GLOBALS['phpgw']->link('/index.php',$link_data));
				$this->t->set_var('lang_yes',lang('Yes'));
				$this->t->pfp('out','category_delete');
			}
		}
	}
?>
