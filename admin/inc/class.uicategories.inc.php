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

		var $public_functions = array(
			'index'  => True,
			'add'    => True,
			'edit'   => True,
			'delete' => True
		);
	
		function uicategories()
		{
			$this->bo = CreateObject('admin.bocategories');
			$this->template = $GLOBALS['phpgw']->template;
			$this->nextmatchs = CreateObject('phpgwapi.nextmatchs');

			$this->start  = $this->bo->start;
			$this->query  = $this->bo->query;
			$this->sort   = $this->bo->sort;
			$this->order  = $this->bo->order;
			$this->cat_id = $this->bo->cat_id;
			if($this->debug) { $this->_debug_sqsof(); }
		}

		function _debug_sqsof()
		{
			$data = array(
				'start'  => $this->start,
				'query'  => $this->query,
				'sort'   => $this->sort,
				'order'  => $this->order,
				'cat_id' => $this->cat_id
			);
			echo '<br>UI:<br>';
			_debug_array($data);
		}

		/* Called only by index() */
		function save_sessiondata()
		{
			$data = array(
				'start' => $this->start,
				'query' => $this->query,
				'sort'  => $this->sort,
				'order' => $this->order
			);
			if(isset($this->cat_id))
			{
				$data['cat_id'] = $this->cat_id;
			}
			$this->bo->save_sessiondata($data);
		}

		function index()
		{
			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();

			$this->template->set_file(array(
				'cat_list_t' => 'listcats.tpl',
				'cat_list'   => 'listcats.tpl'
			));
			$this->template->set_block('cat_list_t','cat_list','list');

			/*
			$common_hidden_vars = '<input type="hidden" name="sort" value="' . $this->sort . '">' . "\n"
				. '<input type="hidden" name="order" value="' . $this->order . '">' . "\n"
				. '<input type="hidden" name="query" value="' . $this->query . '">' . "\n"
				. '<input type="hidden" name="start" value="' . $this->start . '">' . "\n"
				. '<input type="hidden" name="filter" value="' . $this->filter . '">' . "\n";
			*/

			$this->template->set_var('lang_action',lang('Category list'));
			$this->template->set_var('add_action',$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicategories.add'));
			$this->template->set_var('lang_add',lang('Add'));
			$this->template->set_var('title_categories',lang('Global categories'));
			$this->template->set_var('lang_search',lang('Search'));
			$this->template->set_var('query',$this->query);
			$this->template->set_var('actionurl',$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicategories.index'));
			$this->template->set_var('lang_done',lang('Done'));
			$this->template->set_var('doneurl',$GLOBALS['phpgw']->link('/admin/index.php'));

			if(!$start)
			{
				$start = 0;
			}

			$this->bo->cats->app_name = 'phpgw';
			$categories = $this->bo->get_list();

			$left  = $this->nextmatchs->left('/index.php',$this->start,$this->bo->cats->total_records,'menuaction=admin.uicategories.index');
			$right = $this->nextmatchs->right('/index.php',$this->start,$this->bo->cats->total_records,'menuaction=admin.uicategories.index');
			$this->template->set_var('left',$left);
			$this->template->set_var('right',$right);

			$this->template->set_var('lang_showing',$this->nextmatchs->show_hits($this->bo->cats->total_records,$this->start));

			$this->template->set_var('th_bg',$GLOBALS['phpgw_info']['theme']['th_bg']);
			$this->template->set_var('sort_name',$this->nextmatchs->show_sort_order($this->sort,'cat_name',$this->order,'/index.php',lang('Name'),'&menuaction=admin.uicategories.index'));
			$this->template->set_var('sort_description',$this->nextmatchs->show_sort_order($this->sort,'cat_description',$this->order,'/index.php',lang('Description'),'&menuaction=admin.uicategories.index'));
			$this->template->set_var('lang_sub',lang('Add sub'));
			$this->template->set_var('lang_edit',lang('Edit'));
			$this->template->set_var('lang_delete',lang('Delete'));

			for ($i=0;$i<count($categories);$i++)
			{
				$tr_color = $this->nextmatchs->alternate_row_color($tr_color);
				$this->template->set_var(tr_color,$tr_color);

				$id = $categories[$i]['id'];
				$level = $categories[$i]['level'];

				if ($level > 0)
				{
					$space = '&nbsp;&nbsp;';
					$spaceset = str_repeat($space,$level);
					$name = $spaceset . $GLOBALS['phpgw']->strip_html($categories[$i]['name']);
				}

				$descr = $GLOBALS['phpgw']->strip_html($categories[$i]['description']);
				if (!$descr) { $descr = '&nbsp;'; }

				if ($level == 0)
				{
					$name = '<font color="FF0000"><b>' . $GLOBALS['phpgw']->strip_html($categories[$i]['name']) . '</b></font>';
					$descr = '<font color="FF0000"><b>' . $descr . '</b></font>';
				}

				$this->template->set_var(array('name' => $name,
				'descr' => $descr));

				$this->template->set_var('add_sub',$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicategories.add&cat_parent=' . $id));
				$this->template->set_var('lang_sub_entry',lang('Add sub'));
				$this->template->set_var('edit',$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicategories.edit&cat_id=' . $id));
				$this->template->set_var('lang_edit_entry',lang('Edit'));
				$this->template->set_var('delete',$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicategories.delete&cat_id=' . $id));
				$this->template->set_var('lang_delete_entry',lang('Delete'));

				$this->template->parse('list','cat_list',True);
			}

			$this->save_sessiondata();

			$this->template->parse('out','cat_list_t',True);
			$this->template->p('out');
		}

		function add()
		{
			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();

			$new_parent = $GLOBALS['HTTP_POST_VARS']['new_parent'];
			$submit     = $GLOBALS['HTTP_POST_VARS']['submit'];
			$cat_parent = $GLOBALS['HTTP_POST_VARS']['cat_parent'] ? $GLOBALS['HTTP_POST_VARS']['cat_parent'] : $GLOBALS['HTTP_GET_VARS']['cat_parent'];
			$cat_name   = $GLOBALS['HTTP_POST_VARS']['cat_name'];
			$cat_description = $GLOBALS['HTTP_POST_VARS']['cat_description'];

			$this->template->set_file(array('form' => 'category_form.tpl'));
			$this->template->set_block('form','add','addhandle');
			$this->template->set_block('form','edit','edithandle');

			$this->bo->cats->app_name = 'phpgw';

			if ($new_parent)
			{
				$cat_parent = $new_parent;
			}

			if ($submit)
			{
				$errorcount = 0;

				if (!$cat_name)
				{
					$error[$errorcount++] = lang('Please enter a name');
				}

				if (!$error)
				{
					if (!$cat_parent)
					{
						$exists = $this->bo->exists(array(
							'type'     => 'appandmains',
							'cat_name' => $cat_name,
							'cat_id'   => ''
						));
					}
					else
					{
						$exists = $this->bo->exists(array(
							'type'     => 'appandsubs',
							'cat_name' => $cat_name,
							'cat_id'   => ''
						));
					}

					if ($exists == True)
					{
						$error[$errorcount++] = lang('That name has been used already');
					}
				}

				if (!$error)
				{
					$this->bo->add(array(
						'parent' => $cat_parent,
						'descr'  => $cat_description,
						'name'   => $cat_name,
						'access' => 'public'
					));
				}
			}

			if ($errorcount)
			{
				$this->template->set_var('message',$GLOBALS['phpgw']->common->error_list($error));
			}

			if (($submit) && (! $error) && (! $errorcount))
			{
				$this->template->set_var('message',lang('Category x has been added !', $cat_name));
			}

			if ((! $submit) && (! $error) && (! $errorcount))
			{
				$this->template->set_var('message','');
			}

			$this->template->set_var('title_categories',lang('Add global category'));
			$this->template->set_var('actionurl',$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicategories.add'));
			$this->template->set_var('doneurl',$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicategories.index'));
			$this->template->set_var('hidden_vars','<input type="hidden" name="cat_id" value="' . $this->cat_id . '">');
			$this->template->set_var('lang_parent',lang('Parent category'));
			$this->template->set_var('lang_none',lang('None'));
			$this->template->set_var('category_list',$this->bo->formatted_list(array('select' => 'select','all' => 'all','cat_parent' => $cat_parent)));
			$this->template->set_var('lang_name',lang('Name'));
			$this->template->set_var('lang_descr',lang('Description'));
			$this->template->set_var('cat_name',$cat_name);
			$this->template->set_var('cat_description',$cat_description);
			$this->template->set_var('lang_add',lang('Add'));
			$this->template->set_var('lang_reset',lang('Clear Form'));
			$this->template->set_var('lang_done',lang('Done'));

			$this->template->set_var('edithandle','');
			$this->template->set_var('addhandle','');
			$this->template->pparse('out','form');
			$this->template->pparse('addhandle','add');
		}

		function edit()
		{
			if (!$this->cat_id)
			{
				Header('Location: ' . $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicategories.index'));
			}

			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();

			$new_parent = $GLOBALS['HTTP_POST_VARS']['new_parent'];
			$submit     = $GLOBALS['HTTP_POST_VARS']['submit'];
			$cat_parent = $GLOBALS['HTTP_POST_VARS']['cat_parent'];
			$cat_name   = $GLOBALS['HTTP_POST_VARS']['cat_name'];
			$cat_description = $GLOBALS['HTTP_POST_VARS']['cat_description'];

			$this->template->set_file(array('form' => 'category_form.tpl'));
			$this->template->set_block('form','add','addhandle');
			$this->template->set_block('form','edit','edithandle');

			$this->bo->cats->app_name = 'phpgw';

			$hidden_vars = '<input type="hidden" name="cat_id" value="' . $this->cat_id . '">' . "\n";

			if ($new_parent)
			{
				$cat_parent = $new_parent;
			}

			if ($submit)
			{
				$errorcount = 0;

				if (!$cat_name)
				{
					$error[$errorcount++] = lang('Please enter a name');
				}

				if (!$error)
				{
					if (!$cat_parent)
					{
						$exists = $this->bo->exists(array(
							'type'     => 'appandmains',
							'cat_name' => $cat_name,
							'cat_id'   => $this->cat_id
						));
					}
					else
					{
						$exists = $this->bo->exists(array(
							'type'     => 'appandsubs',
							'cat_name' => $cat_name,
							'cat_id'   => $this->cat_id
						));
					}

					if ($exists == True)
					{
						$error[$errorcount++] = lang('That name has been used already');
					}
				}

				if (!$error)
				{
					$this->bo->cats->edit(array(
						'access' => 'public',
						'parent' => $cat_parent,
						'descr'  => $cat_description,
						'name'   => $cat_name,
						'id'     => $this->cat_id
					));
				}
			}

			if ($errorcount)
			{
				$this->template->set_var('message',$GLOBALS['phpgw']->common->error_list($error));
			}

			if (($submit) && (!$error) && (!$errorcount))
			{
				$this->template->set_var('message',lang('Category x has been updated !',$cat_name));
			}

			if ((!$submit) && (!$error) && (!$errorcount))
			{
				$this->template->set_var('message','');
			}

			$cats = $this->bo->cats->return_single($this->cat_id);

			$this->template->set_var('title_categories',lang('Edit global category'));
			$this->template->set_var('lang_parent',lang('Parent category'));
			$this->template->set_var('lang_none',lang('None'));
			$this->template->set_var('actionurl',$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicategories.edit'));
			$this->template->set_var('deleteurl',$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicategories.delete&cat_id=' . $this->cat_id));
			$this->template->set_var('doneurl',$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicategories.index'));
			$this->template->set_var('hidden_vars',$hidden_vars);
			$this->template->set_var('lang_name',lang('Name'));
			$this->template->set_var('lang_descr',lang('Description'));
			$this->template->set_var('lang_done',lang('Done'));
			$this->template->set_var('lang_edit',lang('Edit'));
			$this->template->set_var('lang_delete',lang('Delete'));

			$this->template->set_var('cat_name',$GLOBALS['phpgw']->strip_html($cats[0]['name']));
			$this->template->set_var('cat_description',$GLOBALS['phpgw']->strip_html($cats[0]['description']));
			$this->template->set_var('category_list',$this->bo->formatted_list(array('select' => 'select','all' => 'all', 'cat_parent' => $cats[0]['parent'])));

			$this->template->set_var('edithandle','');
			$this->template->set_var('addhandle','');
			$this->template->pparse('out','form');
			$this->template->pparse('edithandle','edit');
		}

		function delete()
		{
			if (!$this->cat_id)
			{
				Header('Location: ' . $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicategories.index'));
			}

			$this->bo->cats->app_name = 'phpgw';

			if ($GLOBALS['HTTP_POST_VARS']['confirm'])
			{
				if ($GLOBALS['HTTP_POST_VARS']['subs'])
				{
					$this->bo->delete($this->cat_id,True);
				}
				else
				{
					$this->bo->delete($this->cat_id);
				}
				Header('Location: ' . $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicategories.index'));
			}
			else
			{
				$hidden_vars = '<input type="hidden" name="cat_id" value="' . $this->cat_id . '">' . "\n";

				$this->template->set_file(array('category_delete' => 'delete_cat.tpl'));
				$this->template->set_block('category_delete','delete','deletehandle');
				$this->template->set_block('category_delete','done','donehandle');

				$nolink = $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicategories.index&cat_id=' . $this->cat_id);

				$apps_cats = $this->bo->exists(array(
					'type'     => 'subs',
					'cat_name' => '',
					'cat_id'   => $this->cat_id
				));

				$GLOBALS['phpgw']->common->phpgw_header();
				echo parse_navbar();

				if ($apps_cats)
				{
					$this->template->set_var('messages',lang('This category is currently being used by applications as a parent category') . '<br>'
						. lang('You will need to remove the subcategories before you can delete this category'));
					$this->template->set_var('hidden_vars',$hidden_vars);
					$this->template->set_var('lang_subs','');
					$this->template->set_var('subs','');
					$this->template->set_var('nolink',$nolink);
					$this->template->set_var('lang_done',lang('Done'));
					$this->template->set_var('deletehandle','');
					$this->template->set_var('donehandle','');
					$this->template->pparse('out','category_delete');
					$this->template->pparse('donehandle','done');
				}
				else
				{
					$this->template->set_var('messages',lang('Are you sure you want to delete this category ?'));
					$this->template->set_var('hidden_vars',$hidden_vars);

					$exists = $this->bo->exists(array(
						'type'     => 'subs',
						'cat_name' => '',
						'cat_id'   => $this->cat_id
					));
					if ($exists)
					{
						$this->template->set_var('lang_subs',lang('Do you also want to delete all global subcategories ?'));
						$this->template->set_var('subs','<input type="checkbox" name="subs" value="True">');
					}
					else
					{
						$this->template->set_var('lang_subs','');
						$this->template->set_var('subs', '');
					}

					$this->template->set_var('nolink',$nolink);
					$this->template->set_var('lang_no',lang('No'));
					$this->template->set_var('action_url',$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicategories.delete&cat_id=' . $this->cat_id));
					$this->template->set_var('lang_yes',lang('Yes'));
					$this->template->set_var('deletehandle','');
					$this->template->set_var('donehandle','');
					$this->template->pparse('out','category_delete');
					$this->template->pparse('deletehandle','delete');
				}
			}
		}
	}
?>
