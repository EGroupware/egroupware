<?php
	/**************************************************************************\
	* phpGroupWare - Admin - Global categories                                 *
	* http://www.phpgroupware.org                                              *
	* Written by Bettina Gille [ceb@phpgroupware.org]                          *
	* -----------------------------------------------                          *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	/* $Id$ */
	/* $Source$ */

	class uicategories
	{
		var $bo;
		var $template;

		var $start;
		var $query;
		var $sort;
		var $order;
		var $cat_id;
		var $debug = False;

		var $public_functions = array
		(
			'index'  => True,
			'add'    => True,
			'edit'   => True,
			'delete' => True
		);

		function uicategories()
		{
			$this->bo			= CreateObject('admin.bocategories');
			$this->nextmatchs	= CreateObject('phpgwapi.nextmatchs');

			$this->start		= $this->bo->start;
			$this->query		= $this->bo->query;
			$this->sort			= $this->bo->sort;
			$this->order		= $this->bo->order;
			$this->cat_id		= $this->bo->cat_id;
			if($this->debug) { $this->_debug_sqsof(); }
		}

		function _debug_sqsof()
		{
			$data = array(
				'start'		=> $this->start,
				'query'		=> $this->query,
				'sort'		=> $this->sort,
				'order'		=> $this->order,
				'cat_id'	=> $this->cat_id
			);
			echo '<br>UI:<br>';
			_debug_array($data);
		}

		function save_sessiondata()
		{
			$data = array
			(
				'start'	=> $this->start,
				'query'	=> $this->query,
				'sort'	=> $this->sort,
				'order'	=> $this->order
			);

			if(isset($this->cat_id))
			{
				$data['cat_id'] = $this->cat_id;
			}
			$this->bo->save_sessiondata($data);
		}

		function set_langs()
		{
			$GLOBALS['phpgw']->template->set_var('th_bg',$GLOBALS['phpgw_info']['theme']['th_bg']);
			$GLOBALS['phpgw']->template->set_var('lang_save',lang('Save'));
			$GLOBALS['phpgw']->template->set_var('lang_search',lang('Search'));
			$GLOBALS['phpgw']->template->set_var('lang_sub',lang('Add sub'));
			$GLOBALS['phpgw']->template->set_var('lang_edit',lang('Edit'));
			$GLOBALS['phpgw']->template->set_var('lang_delete',lang('Delete'));
			$GLOBALS['phpgw']->template->set_var('lang_parent',lang('Parent category'));
			$GLOBALS['phpgw']->template->set_var('lang_none',lang('None'));
			$GLOBALS['phpgw']->template->set_var('lang_name',lang('Name'));
			$GLOBALS['phpgw']->template->set_var('lang_descr',lang('Description'));
			$GLOBALS['phpgw']->template->set_var('lang_add',lang('Add'));
			$GLOBALS['phpgw']->template->set_var('lang_reset',lang('Clear Form'));
			$GLOBALS['phpgw']->template->set_var('lang_done',lang('Done'));
		}

		function index()
		{
			$global_cats  = get_var('global_cats',array('POST','GET'));

			$GLOBALS['phpgw']->common->phpgw_header();

			$GLOBALS['phpgw']->template->set_file(array('cat_list_t' => 'listcats.tpl'));
			$GLOBALS['phpgw']->template->set_block('cat_list_t','cat_list');
			$GLOBALS['phpgw']->template->set_block('cat_list_t','cat_row');

			$this->set_langs();

			$link_data = array
			(
				'menuaction'  => 'admin.uicategories.index',
				'appname'     => $GLOBALS['appname'],
				'global_cats' => $global_cats
			);

			$GLOBALS['phpgw']->template->set_var('lang_action',lang('Category list'));

			if ($GLOBALS['appname'])
			{
				$GLOBALS['phpgw']->template->set_var('title_categories',lang($GLOBALS['appname']) . '&nbsp;' . lang('global categories'));
			}
			else
			{
				$GLOBALS['phpgw']->template->set_var('title_categories',lang('Global categories'));
			}
			$GLOBALS['phpgw']->template->set_var('query',$this->query);
			$GLOBALS['phpgw']->template->set_var('actionurl',$GLOBALS['phpgw']->link('/index.php',$link_data));
			$GLOBALS['phpgw']->template->set_var('doneurl',$GLOBALS['phpgw']->link('/admin/index.php'));

			if (!$global_cats)
			{
				$global_cats = False;
			}

			$categories = $this->bo->get_list($global_cats);

			$left  = $this->nextmatchs->left('/index.php',$this->start,$this->bo->cats->total_records,$link_data);
			$right = $this->nextmatchs->right('/index.php',$this->start,$this->bo->cats->total_records,$link_data);
			$GLOBALS['phpgw']->template->set_var('left',$left);
			$GLOBALS['phpgw']->template->set_var('right',$right);

			$GLOBALS['phpgw']->template->set_var('lang_showing',$this->nextmatchs->show_hits($this->bo->cats->total_records,$this->start));

			$GLOBALS['phpgw']->template->set_var('sort_name',$this->nextmatchs->show_sort_order($this->sort,'cat_name',$this->order,'/index.php',lang('Name'),$link_data));
			$GLOBALS['phpgw']->template->set_var('sort_description',$this->nextmatchs->show_sort_order($this->sort,'cat_description',$this->order,'/index.php',lang('Description'),$link_data));

			for ($i=0;$i<count($categories);$i++)
			{
				$tr_color = $this->nextmatchs->alternate_row_color($tr_color);
				$GLOBALS['phpgw']->template->set_var('tr_color',$tr_color);

				$level		= $categories[$i]['level'];
				$cat_name	= $GLOBALS['phpgw']->strip_html($categories[$i]['name']);

				if ($level > 0)
				{
					$space = '-';
					$spaceset = str_repeat($space,$level);
					$cat_name = $spaceset . $cat_name;
				}

				$descr = $GLOBALS['phpgw']->strip_html($categories[$i]['descr']);
				if (!$descr) { $descr = '&nbsp;'; }

				if ($level == 0)
				{
					$cat_name = '<font color="FF0000"><b>' . $cat_name . '</b></font>';
					$descr = '<font color="FF0000"><b>' . $descr . '</b></font>';
				}

				if ($GLOBALS['appname'] && $categories[$i]['app_name'] == 'phpgw')
				{
					$appendix = '&lt;' . lang('Global') . '&gt;';
				}
				else
				{
					$appendix = '';
				}

				$GLOBALS['phpgw']->template->set_var(array
				(
					'name' => $cat_name . $appendix,
					'descr' => $descr
				));

				$link_data['menuaction'] = 'admin.uicategories.add';
				$link_data['parent'] = $categories[$i]['cat_id'];
				$GLOBALS['phpgw']->template->set_var('add_sub',$GLOBALS['phpgw']->link('/index.php',$link_data));
				$GLOBALS['phpgw']->template->set_var('lang_sub_entry',lang('Add sub'));

				if ($GLOBALS['appname'] && $categories[$i]['app_name'] == $GLOBALS['appname'])
				{
					$show_edit_del = True;
				}
				elseif(!$GLOBALS['appname'] && $categories[$i]['app_name'] == 'phpgw')
				{
					$show_edit_del = True;
				}
				else
				{
					$show_edit_del = False;
				}

				if ($show_edit_del)
				{
					$link_data['cat_id'] = $categories[$i]['cat_id'];
					$link_data['menuaction'] = 'admin.uicategories.edit';
					$GLOBALS['phpgw']->template->set_var('edit',$GLOBALS['phpgw']->link('/index.php',$link_data));
					$GLOBALS['phpgw']->template->set_var('lang_edit_entry',lang('Edit'));

					$link_data['menuaction'] = 'admin.uicategories.delete';
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
				$GLOBALS['phpgw']->template->fp('rows','cat_row',True);
			}

			$link_data['menuaction'] = 'admin.uicategories.add';
			$link_data['parent'] = '';
			$GLOBALS['phpgw']->template->set_var('add_action',$GLOBALS['phpgw']->link('/index.php',$link_data));

			$this->save_sessiondata();
			$GLOBALS['phpgw']->template->fp('phpgw_body','cat_list',True);
		}

		function add()
		{
			$global_cats	= get_var('global_cats',array('POST','GET'));
			$parent			= get_var('parent',array('GET'));

			$link_data = array
			(
				'menuaction'  => 'admin.uicategories.index',
				'appname'     => $GLOBALS['appname'],
				'global_cats' => $global_cats
			);

			$GLOBALS['phpgw']->common->phpgw_header();

			$this->set_langs();

			$values = get_var('values',array('POST'));

			$GLOBALS['phpgw']->template->set_file(array('cat_form' => 'category_form.tpl'));
			$GLOBALS['phpgw']->template->set_block('cat_form','add');
			$GLOBALS['phpgw']->template->set_block('cat_form','edit');
			$GLOBALS['phpgw']->template->set_block('cat_form','form');

			$GLOBALS['phpgw']->template->set_var('doneurl',$GLOBALS['phpgw']->link('/index.php',$link_data));

			if ($GLOBALS['appname'])
			{
				$GLOBALS['phpgw']->template->set_var('title_categories',lang('Add global category for x',lang($GLOBALS['appname'])));
			}
			else
			{
				$GLOBALS['phpgw']->template->set_var('title_categories',lang('Add global category'));
			}

			if (get_var('submit',array('POST')))
			{
				if(is_array($values))
				{
					$values['access'] = 'public';
				}

				$error = $this->bo->check_values($values);
				if (is_array($error))
				{
					$GLOBALS['phpgw']->template->set_var('message',$GLOBALS['phpgw']->common->error_list($error));
				}
				else
				{
					$this->bo->save_cat($values);
					$GLOBALS['phpgw']->template->set_var('message',lang('Category x has been added !', $values['name']));
				}
			}

			$link_data['menuaction'] = 'admin.uicategories.add';
			$GLOBALS['phpgw']->template->set_var('actionurl',$GLOBALS['phpgw']->link('/index.php',$link_data));

			if ($values['parent'])
			{
				$parent = $values['parent'];
			}

			$GLOBALS['phpgw']->template->set_var('category_list',$this->bo->cats->formated_list(array('format'	=> 'select',
																									'type'		=> 'all',
																									'selected'	=> $parent,
																									'globals'	=> $global_cats)));

			$GLOBALS['phpgw']->template->set_var('cat_name',$values['name']);
			$GLOBALS['phpgw']->template->set_var('cat_description',$values['descr']);

			$GLOBALS['phpgw']->template->parse('buttons','add');
			$GLOBALS['phpgw']->template->fp('phpgw_body','form');
		}

		function edit()
		{
			$global_cats  = get_var('global_cats',array('POST','GET'));

			$link_data = array
			(
				'menuaction'  => 'admin.uicategories.index',
				'appname'     => $GLOBALS['appname'],
				'global_cats' => $global_cats
			);

			if (!$this->cat_id)
			{
				Header('Location: ' . $GLOBALS['phpgw']->link('/index.php',$link_data));
			}

			$GLOBALS['phpgw']->common->phpgw_header();

			$this->set_langs();

			$values = get_var('values',array('POST'));

			$GLOBALS['phpgw']->template->set_file(array('cat_form' => 'category_form.tpl'));
			$GLOBALS['phpgw']->template->set_block('cat_form','add');
			$GLOBALS['phpgw']->template->set_block('cat_form','edit');
			$GLOBALS['phpgw']->template->set_block('cat_form','form');

			$GLOBALS['phpgw']->template->set_var('doneurl',$GLOBALS['phpgw']->link('/index.php',$link_data));

			if (get_var('submit',array('POST')))
			{
				if (is_array($values))
				{
					$values['cat_id'] = $this->cat_id;
					$values['access'] = 'public';
				}

				$error = $this->bo->check_values($values);
				if (is_array($error))
				{
					$GLOBALS['phpgw']->template->set_var('message',$GLOBALS['phpgw']->common->error_list($error));
				}
				else
				{
					$this->cat_id = $this->bo->save_cat($values);
					$GLOBALS['phpgw']->template->set_var('message',lang('Category x has been updated !',$values['name']));
				}
			}

			$cats = $this->bo->cats->return_single($this->cat_id);

			if ($GLOBALS['appname'])
			{
				$GLOBALS['phpgw']->template->set_var('title_categories',lang('Edit global category for x',lang($GLOBALS['appname'])));
			}
			else
			{
				$GLOBALS['phpgw']->template->set_var('title_categories',lang('Edit global category'));
			}

			$link_data['menuaction'] = 'admin.uicategories.edit';
			$link_data['cat_id']	= $this->cat_id;
			$GLOBALS['phpgw']->template->set_var('actionurl',$GLOBALS['phpgw']->link('/index.php',$link_data));

			$link_data['menuaction'] = 'admin.uicategories.delete';
			$GLOBALS['phpgw']->template->set_var('deleteurl',$GLOBALS['phpgw']->link('/index.php',$link_data));

			$GLOBALS['phpgw']->template->set_var('old_parent',$cats['parent']);

			$GLOBALS['phpgw']->template->set_var('cat_name',$GLOBALS['phpgw']->strip_html($cats['name']));
			$GLOBALS['phpgw']->template->set_var('cat_description',$GLOBALS['phpgw']->strip_html($cats['descr']));
			$GLOBALS['phpgw']->template->set_var('category_list',$this->bo->formatted_list(array('select'	=> 'select',
																									'all'	=> 'all',
																								'parent'	=> $cats['parent'],
																							'global_cats'	=> $global_cats)));
			$GLOBALS['phpgw']->template->parse('buttons','edit');
			$GLOBALS['phpgw']->template->fp('phpgw_body','form');
		}

		function delete()
		{
			$global_cats  = get_var('global_cats',array('POST','GET'));

			$link_data = array
			(
				'menuaction'  => 'admin.uicategories.index',
				'appname'     => $GLOBALS['appname'],
				'global_cats' => $global_cats
			);

			if (!$this->cat_id)
			{
				Header('Location: ' . $GLOBALS['phpgw']->link('/index.php',$link_data));
			}

			if ($GLOBALS['HTTP_POST_VARS']['confirm'])
			{
				if ($GLOBALS['HTTP_POST_VARS']['subs'])
				{
					switch ($GLOBALS['HTTP_POST_VARS']['subs'])
					{
						case 'move':
							$this->bo->delete(array('cat_id' => $this->cat_id, 'modify_subs' => True));
							Header('Location: ' . $GLOBALS['phpgw']->link('/index.php',$link_data));
							break;
						case 'drop':
							$this->bo->delete(array('cat_id' => $this->cat_id, 'drop_subs' => True));
							Header('Location: ' . $GLOBALS['phpgw']->link('/index.php',$link_data));
							break;
						default:
							$error_msg = lang('Please choose one of the methods to handle the subcategories');
							break;
					}
				}
				else
				{
					$this->bo->delete(array('cat_id' => $this->cat_id));
					Header('Location: ' . $GLOBALS['phpgw']->link('/index.php',$link_data));
				}
			}

			$GLOBALS['phpgw']->template->set_file(array('category_delete' => 'delete_cat.tpl'));

			$GLOBALS['phpgw']->template->set_var('error_msg',$error_msg);
			$nolink = $GLOBALS['phpgw']->link('/index.php',$link_data);

			if ($GLOBALS['appname'])
			{
				$type = 'noglobalapp';
			}
			else
			{
				$type = 'noglobal';
			}

			$apps_cats = $this->bo->exists(array
			(
				'type'     => $type,
				'cat_name' => '',
				'cat_id'   => $this->cat_id
			));

			$GLOBALS['phpgw']->common->phpgw_header();

			if ($apps_cats)
			{
				$GLOBALS['phpgw']->template->set_var('delete_msg',lang('This category is currently being used by applications as a parent category') . '<br>'
																. lang('You will need to reassign these subcategories before you can delete this category'));

				$GLOBALS['phpgw']->template->set_var('lang_subs','');
				$GLOBALS['phpgw']->template->set_var('subs','');
				$GLOBALS['phpgw']->template->set_var('nolink',$nolink);
				$GLOBALS['phpgw']->template->set_var('deletehandle','');
				$GLOBALS['phpgw']->template->set_var('donehandle','');
				$GLOBALS['phpgw']->template->pfp('out','category_delete');
				$GLOBALS['phpgw']->template->pfp('donehandle','done');
			}
			else
			{
				$GLOBALS['phpgw']->template->set_var('delete_msg',lang('Are you sure you want to delete this global category ?'));

				$exists = $this->bo->exists(array
				(
					'type'     => 'subs',
					'cat_name' => '',
					'cat_id'   => $this->cat_id
				));

				if ($exists)
				{
					$sub_select = '<input type="radio" name="subs" value="move">' . lang('Do you want to move all global subcategories one level down ?') . '<br>';
					$sub_select .= '<input type="radio" name="subs" value="drop">' . lang('Do you want to delete all global subcategories ?');
					$GLOBALS['phpgw']->template->set_var('sub_select',$sub_select);
				}

				$GLOBALS['phpgw']->template->set_var('nolink',$nolink);
				$GLOBALS['phpgw']->template->set_var('lang_no',lang('No'));

				$link_data['menuaction'] = 'admin.uicategories.delete';
				$link_data['cat_id'] = $this->cat_id;
				$GLOBALS['phpgw']->template->set_var('action_url',$GLOBALS['phpgw']->link('/index.php',$link_data));
				$GLOBALS['phpgw']->template->set_var('lang_yes',lang('Yes'));

				$GLOBALS['phpgw']->template->fp('phpgw_body','category_delete');
			}
		}
	}
?>
