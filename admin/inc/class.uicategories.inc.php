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
		var $nextmatchs;
		var $xslttpl;

		var $start;
		var $query;
		var $sort;
		var $order;
		var $cat_id;
		var $debug = False;

		var $public_functions = array
		(
			'index'  => True,
			'edit'   => True,
			'delete' => True
		);

		function uicategories()
		{
			$GLOBALS['phpgw_info']['flags']['xslt_app'] = True;

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

		function index()
		{
			$global_cats  = get_var('global_cats',array('POST','GET'));

			$GLOBALS['phpgw']->xslttpl->add_file(array('app_data','cats',
										$GLOBALS['phpgw']->common->get_tpl_dir('phpgwapi','default') . SEP . 'search_field',
										$GLOBALS['phpgw']->common->get_tpl_dir('phpgwapi','default') . SEP . 'nextmatchs'));

			$link_data = array
			(
				'menuaction'  => 'admin.uicategories.index',
				'appname'     => $GLOBALS['appname'],
				'global_cats' => $global_cats
			);

			if ($_POST['add'])
			{
				$link_data['menuaction'] = 'admin.uicategories.edit';
				$GLOBALS['phpgw']->redirect_link('/index.php',$link_data);
			}

			if ($_POST['done'])
			{
				$GLOBALS['phpgw']->redirect_link('/index.php','menuaction=admin.uimainscreen.mainscreen');
			}

			if ($GLOBALS['appname'])
			{
				$GLOBALS['phpgw_info']['flags']['app_header'] = lang($GLOBALS['appname']) . '&nbsp;' . lang('global categories') . ': ' . lang('category list');
			}
			else
			{
				$GLOBALS['phpgw_info']['flags']['app_header'] = lang('global categories') . ': ' . lang('category list');
			}

			if (!$global_cats)
			{
				$global_cats = False;
			}

			$categories = $this->bo->get_list($global_cats);

			$cat_header[] = array
			(
				'sort_name'				=> $this->nextmatchs->show_sort_order(array
										(
											'sort'	=> $this->sort,
											'var'	=> 'cat_name',
											'order'	=> $this->order,
											'extra'	=> $link_data
										)),
				'lang_add_sub'			=> lang('add sub'),
				'lang_name'				=> lang('name'),
				'lang_descr'			=> lang('description'),
				'lang_edit'				=> lang('edit'),
				'lang_delete'			=> lang('delete'),
				'lang_sort_statustext'	=> lang('sort the entries'),
				'sort_descr'			=> $this->nextmatchs->show_sort_order(array
											(
												'sort'	=> $this->sort,
												'var'	=> 'cat_description',
												'order'	=> $this->order,
												'extra'	=> $link_data
											))
			);

			while (is_array($categories) && list(,$cat) = each($categories))
			{
				$level		= $cat['level'];
				$cat_name	= $GLOBALS['phpgw']->strip_html($cat['name']);

				$main = 'yes';
				if ($level > 0)
				{
					$space = '&nbsp;.&nbsp;';
					$spaceset = str_repeat($space,$level);
					$cat_name = $spaceset . $cat_name;
					$main = 'no';
				}

				$descr = $GLOBALS['phpgw']->strip_html($cat['descr']);

				if ($GLOBALS['appname'] && $cat['app_name'] == 'phpgw')
				{
					$appendix = '&nbsp;&lt;' . lang('Global') . '&gt;';
				}
				else
				{
					$appendix = '';
				}

				$link_data['menuaction'] = 'admin.uicategories.edit';
				$link_data['parent'] = $cat['cat_id'];
				$add_sub_url = $GLOBALS['phpgw']->link('/index.php',$link_data);

				if ($GLOBALS['appname'] && $cat['app_name'] == $GLOBALS['appname'])
				{
					$show_edit_del = True;
				}
				elseif(!$GLOBALS['appname'] && $cat['app_name'] == 'phpgw')
				{
					$show_edit_del = True;
				}
				else
				{
					$show_edit_del = False;
				}

				if ($show_edit_del)
				{
					$link_data['cat_id']		= $cat['cat_id'];
					$link_data['menuaction']	= 'admin.uicategories.edit';
					$edit_url					= $GLOBALS['phpgw']->link('/index.php',$link_data);
					$lang_edit					= lang('edit');

					$link_data['menuaction']	= 'admin.uicategories.delete';
					$delete_url					= $GLOBALS['phpgw']->link('/index.php',$link_data);
					$lang_delete				= lang('delete');
				}
				else
				{
					$edit_url					= '';
					$lang_edit					= '';
					$delete_url					= '';
					$lang_delete				= '';
				}

				$content[] = array
				(
					'name'						=> $cat_name . $appendix,
					'descr'						=> $descr,
					'date'						=> $note['date'],
					'main'						=> $main,
					'add_sub_url'				=> $add_sub_url,
					'edit_url'					=> $edit_url,
					'delete_url'				=> $delete_url,
					'lang_add_sub_statustext'	=> lang('add a subcategory'),
					'lang_edit_statustext'		=> lang('edit this category'),
					'lang_delete_statustext'	=> lang('delete this category'),
					'lang_add_sub'				=> lang('add sub'),
					'lang_edit'					=> $lang_edit,
					'lang_delete'				=> $lang_delete
				);
			}

			$link_data['menuaction'] = 'admin.uicategories.index';
			$link_data['parent'] = '';

			$cat_add[] = array
			(
				'lang_add'				=> lang('add'),
				'lang_add_statustext'	=> lang('add a category'),
				'action_url'			=> $GLOBALS['phpgw']->link('/index.php',$link_data),
				'lang_done'				=> lang('done'),
				'lang_done_statustext'	=> lang('return to admin mainscreen')
			);

			$link_data['menuaction'] = 'admin.uicategories.index';

			$data = array
			(
				'start_record'					=> $this->start,
 				'record_limit'					=> $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs'],
 				'num_records'					=> count($categoris),
 				'all_records'					=> $this->bo->cats->total_records,
				'nextmatchs_url'				=> $GLOBALS['phpgw']->link('/index.php',$link_data),
				'nextmatchs_img_path'			=> $GLOBALS['phpgw']->common->get_image_path('phpgwapi','default'),
				'select_url'					=> $GLOBALS['phpgw']->link('/index.php',$link_data),
				'lang_searchfield_statustext'	=> lang('Enter the search string. To show all entries, empty this field and press the SUBMIT button again'),
				'lang_searchbutton_statustext'	=> lang('Submit the search string'),
				'query'							=> $this->query,
				'lang_search'					=> lang('search'),
				'cat_header'					=> $cat_header,
				'cat_data'						=> $content,
				'cat_add'						=> $cat_add 
			);

			$this->save_sessiondata();
			$GLOBALS['phpgw']->xslttpl->set_var('phpgw',array('cat_list' => $data));
		}

		function edit()
		{
			$global_cats	= get_var('global_cats',array('POST','GET'));
			$parent			= get_var('parent',array('GET'));
			$values			= get_var('values',array('POST'));

			$link_data = array
			(
				'menuaction'  => 'admin.uicategories.index',
				'appname'     => $GLOBALS['appname'],
				'global_cats' => $global_cats
			);

			if ($values['cancel'])
			{
				$GLOBALS['phpgw']->redirect_link('/index.php',$link_data);
			}

			if ($values['save'] || $values['apply'])
			{
				$values['cat_id'] = $this->cat_id;
				$values['access'] = 'public';

				$error = $this->bo->check_values($values);
				if (is_array($error))
				{
					$message = $GLOBALS['phpgw']->common->error_list($error);
				}
				else
				{
					$this->cat_id = $this->bo->save_cat($values);
					if ($values['apply'])
					{
						$message = lang('Category %1 has been saved !',$values['name']);
					}
					else
					{
						$GLOBALS['phpgw']->redirect_link('/index.php',$link_data);
					}
				}
			}

			if ($this->cat_id)
			{
				$cats = $this->bo->cats->return_single($this->cat_id);
				$parent = $cats['parent'];
			}

			if ($GLOBALS['appname'])
			{
				$GLOBALS['phpgw_info']['flags']['app_header'] = lang($GLOBALS['appname']) . '&nbsp;' . lang('global categories') . ': ' . ($this->cat_id?lang('edit category'):lang('add category'));
			}
			else
			{
				$GLOBALS['phpgw_info']['flags']['app_header'] = lang('global categories') . ': ' . $function;
			}

			$GLOBALS['phpgw']->xslttpl->add_file(array('app_data','cats'));

			if ($GLOBALS['appname'])
			{
				$GLOBALS['phpgw']->template->set_var('title_categories',lang('Edit global category for %1',lang($GLOBALS['appname'])));
			}
			else
			{
				$GLOBALS['phpgw']->template->set_var('title_categories',lang('Edit global category'));
			}

			$data = array
			(
				'lang_name'					=> lang('name'),
				'lang_descr'				=> lang('description'),
				'lang_parent'				=> lang('parent category'),
				'old_parent'				=> $cats['parent'],
				'lang_save'					=> lang('save'),
				'lang_apply'				=> lang('apply'),
				'lang_cancel'				=> lang('cancel'),
				'value_name'				=> $GLOBALS['phpgw']->strip_html($cats['name']),
				'value_descr'				=> $GLOBALS['phpgw']->strip_html($cats['descr']),
				'message'					=> $message,
				'lang_content_statustext'	=> lang('enter a description for the category'),
				'lang_cancel_statustext'	=> lang('leave the category untouched and return back to the list'),
				'lang_save_statustext'		=> lang('save the category and return back to the list'),
				'lang_apply_statustext'		=> lang('save the category'),
				'lang_no_cat'				=> lang('no category'),
				'lang_cat_statustext'		=> lang('Select the parent category. If this is a main category select NO CATEGORY'),
				'select_name'				=> 'values[parent]',
				'cat_list'					=> $this->bo->cats->formatted_xslt_list(array('format' => 'select','selected' => $parent,'globals' => $global_cats))
			);

			$link_data['menuaction'] = 'admin.uicategories.edit';
			if ($this->cat_id)
			{
				$link_data['cat_id']	= $this->cat_id;
			}
			$data['edit_url'] = $GLOBALS['phpgw']->link('/index.php',$link_data);

			$GLOBALS['phpgw']->xslttpl->set_var('phpgw',array('cat_edit' => $data));
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

			if ($_POST['done'] || $_POST['cancel'] || !$this->cat_id)
			{
				$GLOBALS['phpgw']->redirect_link('/index.php',$link_data);
			}

			if ($_POST['delete'])
			{
				if ($_POST['subs'])
				{
					switch ($_POST['subs'])
					{
						case 'move':
							$this->bo->delete(array('cat_id' => $this->cat_id, 'modify_subs' => True));
							$GLOBALS['phpgw']->redirect_link('/index.php',$link_data);
							break;
						case 'drop':
							$this->bo->delete(array('cat_id' => $this->cat_id, 'drop_subs' => True));
							$GLOBALS['phpgw']->redirect_link('/index.php',$link_data);
							break;
						default:
							$error			= array('Please choose one of the methods to handle the subcategories');
							$msgbox_error	= $GLOBALS['phpgw']->common->error_list($error);
							$show_done		= 'yes';
							break;
					}
				}
				else
				{
					$this->bo->delete(array('cat_id' => $this->cat_id));
					$GLOBALS['phpgw']->redirect_link('/index.php',$link_data);
				}
			}

			$GLOBALS['phpgw']->xslttpl->add_file(array($GLOBALS['phpgw']->common->get_tpl_dir('phpgwapi','default') . SEP . 'app_delete'));

			$GLOBALS['phpgw_info']['flags']['app_header'] = ($GLOBALS['appname']?lang($GLOBALS['appname']) . '&nbsp;':'') . lang('global categories') . ': ' . lang('delete category');

			$type = ($GLOBALS['appname']?'noglobalapp':'noglobal');

			$apps_cats = $this->bo->exists(array('type'		=> $type,
												'cat_name'	=> '',
												'cat_id'	=> $this->cat_id));
			if ($apps_cats)
			{
				$error = array('This category is currently being used by applications as a parent category',
								'You will need to reassign these subcategories before you can delete this category');

				$msgbox_error = $GLOBALS['phpgw']->common->error_list($error);
			}
			else
			{
				$confirm_msg = lang('Are you sure you want to delete this global category ?');

				$exists = $this->bo->exists(array
				(
					'type'     => 'subs',
					'cat_name' => '',
					'cat_id'   => $this->cat_id
				));

				if ($exists)
				{
					$subs					= 'yes';
					$lang_sub_select_move	= lang('Do you want to move all global subcategories one level down ?');
					$lang_sub_select_drop	= lang('Do you want to delete all global subcategories ?');
				}
			}

			$data = array
			(
				'show_done'					=> $show_done,
				'msgbox_data'				=> $msgbox_error,
				'lang_delete'				=> lang('delete'),
				'subs'						=> $subs,
				'lang_sub_select_move'		=> $lang_sub_select_move,
				'lang_sub_select_drop'		=> $lang_sub_select_drop,
				'lang_delete_statustext'	=> lang('delete the category'),
				'lang_cancel_statustext'	=> lang('do NOT delete the category and return back to the list'),
				'lang_done_statustext'		=> lang('back to the list'),
				'lang_cancel'				=> lang('cancel'),
				'lang_done'					=> lang('done'),
				'lang_confirm_msg'			=> $confirm_msg
			);

			$link_data['menuaction']	= 'admin.uicategories.delete';
			$link_data['cat_id']		= $this->cat_id;
			$data['delete_url']			= $GLOBALS['phpgw']->link('/index.php',$link_data);

			$GLOBALS['phpgw']->xslttpl->set_var('phpgw',array('delete' => $data));
		}
	}
?>
