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

		var $start;
		var $query;
		var $sort;
		var $order;
		var $cat_id;

		var $public_functions = array
		(
			'index'		=> True,
			'add'		=> True,
			'edit'		=> True,
			'delete'	=> True
		);

		function uicategories()
		{
			$cats_app			= get_var('cats_app',array('POST','GET'));	

			$this->bo			= CreateObject('preferences.bocategories',$cats_app);
			$this->nextmatchs	= CreateObject('phpgwapi.nextmatchs');
			$this->account		= $GLOBALS['phpgw_info']['user']['account_id'];
			$this->user			= $GLOBALS['phpgw_info']['user']['fullname'];

			$this->start		= $this->bo->start;
			$this->query		= $this->bo->query;
			$this->sort			= $this->bo->sort;
			$this->order		= $this->bo->order;
			$this->cat_id		= $this->bo->cat_id;
		}

		function save_sessiondata($cats_app)
		{
			$data = array
			(
				'start'		=> $this->start,
				'query'		=> $this->query,
				'sort'		=> $this->sort,
				'order'		=> $this->order,
				'cat_id'	=> $this->cat_id
			);
			$this->bo->save_sessiondata($data,$cats_app);
		}

		function set_langs()
		{
			$GLOBALS['phpgw']->template->set_var('th_bg',$GLOBALS['phpgw_info']['theme']['th_bg']);
			$GLOBALS['phpgw']->template->set_var('lang_access',lang('Private'));
			$GLOBALS['phpgw']->template->set_var('lang_save',lang('Save'));
			$GLOBALS['phpgw']->template->set_var('user_name',$this->user);
			$GLOBALS['phpgw']->template->set_var('lang_search',lang('Search'));
			$GLOBALS['phpgw']->template->set_var('lang_done',lang('Done'));
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
			$cats_app		= get_var('cats_app',array('POST','GET'));
			$extra			= get_var('extra',array('POST','GET'));
			$global_cats	= get_var('global_cats',array('POST','GET'));
			$cats_level		= get_var('cats_level',array('POST','GET'));

			$link_data = array
			(
				'menuaction'	=> 'preferences.uicategories.index',
				'cats_app'		=> $cats_app,
				'extra'			=> $extra,
				'global_cats'	=> $global_cats,
				'cats_level'	=> $cats_level
			);

			if ($extra)
			{
				$edata = explode(',',$extra);
			}

			$GLOBALS['phpgw']->common->phpgw_header();

			$GLOBALS['phpgw']->template->set_file(array('cat_list_t' => 'listcats.tpl'));
			$GLOBALS['phpgw']->template->set_block('cat_list_t','cat_list');
			$GLOBALS['phpgw']->template->set_block('cat_list_t','cat_row');

			$this->set_langs();

			$GLOBALS['phpgw']->template->set_var('title_categories',lang('categories for'));
			$GLOBALS['phpgw']->template->set_var('lang_app',lang($cats_app));
			$GLOBALS['phpgw']->template->set_var('actionurl',$GLOBALS['phpgw']->link('/index.php',$link_data));
			$GLOBALS['phpgw']->template->set_var('doneurl',$GLOBALS['phpgw']->link('/preferences/index.php'));

			if (!$global_cats)
			{
				$global_cats = False;
			}

			$this->bo->cats->app_name = $cats_app;
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

			$th_data = '';
			if (is_array($edata))
			{
				for($i=0;$i<count($edata);$i++)
				{
					$th_data .= '<td bgcolor="' . $GLOBALS['phpgw_info']['theme']['th_bg'] . '">' . lang($edata[$i]) . '</td>'."\n";
				}
			}
			$GLOBALS['phpgw']->template->set_var('th_data',$th_data);

// -------------------------- end header declaration --------------------------------------

			for ($i=0;$i<count($cats);$i++)
			{
				$this->nextmatchs->template_alternate_row_color($GLOBALS['phpgw']->template);

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

				$descr = $GLOBALS['phpgw']->strip_html($cats[$i]['descr']);
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
					'name' => $name,
					'descr' => $descr
				));

				$GLOBALS['phpgw']->template->set_var('app_url',$GLOBALS['phpgw']->link('/' . $cats_app . '/index.php','cat_id=' . $cats[$i]['cat_id']));

				if ($cats_level || ($level == 0))
				{
					if ($cats[$i]['owner'] == $this->account || $cats[$i]['app_name'] == 'phpgw')
					{
						$link_data['menuaction'] = 'preferences.uicategories.add';
						$GLOBALS['phpgw']->template->set_var('add_sub',$GLOBALS['phpgw']->link('/index.php',$link_data));
						$GLOBALS['phpgw']->template->set_var('lang_sub_entry',lang('Add sub'));
					}
				}
				else
				{
					$GLOBALS['phpgw']->template->set_var('add_sub','');
					$GLOBALS['phpgw']->template->set_var('lang_sub_entry','&nbsp;');
				}

				$link_data['cat_id'] = $cats[$i]['cat_id'];
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
				$GLOBALS['phpgw']->template->fp('rows','cat_row',True);
			}
			$link_data['menuaction'] = 'preferences.uicategories.add';
			$GLOBALS['phpgw']->template->set_var('add_action',$GLOBALS['phpgw']->link('/index.php',$link_data));
			$this->save_sessiondata($cats_app);

			$GLOBALS['phpgw']->template->fp('phpgw_body','cat_list',True);
		}

		function add()
		{
			$cats_app		= get_var('cats_app',array('POST','GET'));
			$extra			= get_var('extra',array('POST','GET'));
			$global_cats	= get_var('global_cats',array('POST','GET'));
			$cats_level		= get_var('cats_level',array('POST','GET'));

			$link_data = array
			(
				'menuaction'  => 'preferences.uicategories.add',
				'cats_app'    => $cats_app,
				'extra'       => $extra,
				'global_cats' => $global_cats,
				'cats_level'  => $cats_level
			);

			$values		= get_var('values',array('POST'));
			$cat_data	= get_var('cat_data',array('POST'));

			if (get_var('submit',Array('POST')))
			{
				if (is_array($cat_data))
				{
					$data = serialize($cat_data);
				}

				if (is_array($values))
				{
					$values['data'] = $data;

					$error = $this->bo->check_values($values);
					if (is_array($error))
					{
						 $message = $GLOBALS['phpgw']->common->error_list($error);
					}
					else
					{
						$this->cat_id = $this->bo->save_cat($values);
						$message = lang('Category x has been added !', $values['name']);
					}
				}
			}

			$GLOBALS['phpgw']->common->phpgw_header();

			$GLOBALS['phpgw']->template->set_file(array('cat_form' => 'category_form.tpl'));
			$GLOBALS['phpgw']->template->set_block('cat_form','data_row');
			$GLOBALS['phpgw']->template->set_block('cat_form','add');
			$GLOBALS['phpgw']->template->set_block('cat_form','edit');
			$GLOBALS['phpgw']->template->set_block('cat_form','form');

			$this->set_langs();

			$GLOBALS['phpgw']->template->set_var('title_categories',lang('Add x category for',lang($cats_app)));
			$GLOBALS['phpgw']->template->set_var('message',$message);
			$GLOBALS['phpgw']->template->set_var('lang_app',lang($cats_app));
			$GLOBALS['phpgw']->template->set_var('actionurl',$GLOBALS['phpgw']->link('/index.php',$link_data));

			if (!$global_cats)
			{
				$global_cats = False;
			}

			if ($cats_level)
			{
				$type = 'all';
			}
			else
			{
				$type = 'mains';
			}

			$GLOBALS['phpgw']->template->set_var('category_list',$this->bo->cats->formated_list('select',$type,$values['parent'],$global_cats));
			$GLOBALS['phpgw']->template->set_var('cat_name',$values['name']);
			$GLOBALS['phpgw']->template->set_var('cat_description',$values['descr']);

			$GLOBALS['phpgw']->template->set_var('access',$values['access'] == 'private'?' checked':'');

			if ($extra)
			{
				$edata = explode(',',$extra);
				for($i=0;$i<count($edata);$i++)
				{
					$GLOBALS['phpgw']->template->set_var('td_data','<input name="cat_data[' . $edata[$i] . ']" size="50" value="' . $cat_data[$edata[$i]] . '">');
					$GLOBALS['phpgw']->template->set_var('lang_data',lang($edata[$i]));
					$GLOBALS['phpgw']->template->fp('rows','data_row',True);
				}
			}

			$link_data['menuaction'] = 'preferences.uicategories.index';
			$GLOBALS['phpgw']->template->set_var('doneurl',$GLOBALS['phpgw']->link('/index.php',$link_data));

			$GLOBALS['phpgw']->template->parse('buttons','add');
			$GLOBALS['phpgw']->template->fp('phpgw_body','form');
		}

		function edit()
		{
			$cats_app		= get_var('cats_app',array('POST','GET'));
			$extra			= get_var('extra',array('POST','GET'));
			$global_cats	= get_var('global_cats',array('POST','GET'));
			$cats_level		= get_var('cats_level',array('POST','GET'));

			$link_data = array
			(
				'menuaction'  => 'preferences.uicategories.index',
				'cats_app'    => $cats_app,
				'extra'       => $extra,
				'global_cats' => $global_cats,
				'cats_level'  => $cats_level
			);

			if (!$this->cat_id)
			{
				Header('Location: ' . $GLOBALS['phpgw']->link('/index.php',$link_data));
			}

			$values		= get_var('values',Array('POST'));
			$cat_data	= get_var('cat_data',Array('POST'));

			if (get_var('submit',Array('POST')))
			{
				if (is_array($cat_data))
				{
					$data = serialize($cat_data);
				}

				if (is_array($values))
				{
					$values['cat_id']	= $this->cat_id;
					$values['data']		= $data;

					$error = $this->bo->check_values($values);
					if (is_array($error))
					{
						$message = $GLOBALS['phpgw']->common->error_list($error);
					}
					else
					{
						$this->cat_id = $this->bo->save_cat($values);
						$message = lang('Category x has been updated !',$values['name']);
					}
				}
			}

			$GLOBALS['phpgw']->common->phpgw_header();

			$GLOBALS['phpgw']->template->set_file(array('cat_form' => 'category_form.tpl'));
			$GLOBALS['phpgw']->template->set_block('cat_form','data_row');
			$GLOBALS['phpgw']->template->set_block('cat_form','add');
			$GLOBALS['phpgw']->template->set_block('cat_form','edit');
			$GLOBALS['phpgw']->template->set_block('cat_form','form');

			$this->set_langs();

			$cats = $this->bo->cats->return_single($this->cat_id);

			$GLOBALS['phpgw']->template->set_var('title_categories',lang('Edit x category for',lang($cats_app)));
			$GLOBALS['phpgw']->template->set_var('message',$message);
			$GLOBALS['phpgw']->template->set_var('lang_app',lang($cats_app));
			$GLOBALS['phpgw']->template->set_var('doneurl',$GLOBALS['phpgw']->link('/index.php',$link_data));

			$link_data['menuaction'] = 'preferences.uicategories.edit';
			$GLOBALS['phpgw']->template->set_var('actionurl',$GLOBALS['phpgw']->link('/index.php',$link_data));

			$GLOBALS['phpgw']->template->set_var('old_parent',$cats['parent']);
			$GLOBALS['phpgw']->template->set_var('cat_name',$GLOBALS['phpgw']->strip_html($cats['name']));
			$GLOBALS['phpgw']->template->set_var('cat_description',$GLOBALS['phpgw']->strip_html($cats['descr']));

			if (!$global_cats)
			{
				$global_cats = False;
			}

			if ($cats_level)
			{
				$type = 'all';
			}
			else
			{
				$type = 'mains';
			}

			$GLOBALS['phpgw']->template->set_var('category_list',$this->bo->cats->formated_list('select',$type,$cats['parent'],$global_cats));

			$GLOBALS['phpgw']->template->set_var('access',$cats['access'] == 'private'?' checked':'');

			if ($extra)
			{
				$edata = explode(',',$extra);

				$data = unserialize($cats['data']);
				for($i=0;$i<count($edata);$i++)
				{
					$GLOBALS['phpgw']->template->set_var('td_data','<input name="cat_data[' . $edata[$i] . ']" size="50" value="' . $data[$edata[$i]] . '">');
					$GLOBALS['phpgw']->template->set_var('lang_data',lang($edata[$i]));
					$GLOBALS['phpgw']->template->fp('rows','data_row',True);
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

			$GLOBALS['phpgw']->template->parse('buttons','edit');
			$GLOBALS['phpgw']->template->fp('phpgw_body','form');
		}

		function delete()
		{
			$cats_app		= get_var('cats_app',array('POST','GET'));
			$extra			= get_var('extra',array('POST','GET'));
			$global_cats	= get_var('global_cats',array('POST','GET'));
			$cats_level		= get_var('cats_level',array('POST','GET'));

			$link_data = array
			(
				'menuaction'  => 'preferences.uicategories.index',
				'cats_app'    => $cats_app,
				'extra'       => $extra,
				'global_cats' => $global_cats,
				'cats_level'  => $cats_level
			);

			if (!$this->cat_id)
			{
				Header('Location: ' . $GLOBALS['phpgw']->link('/index.php',$link_data));
			}

			$this->bo->cats->app_name = $cats_app;

			if (get_var('confirm',array('POST')))
			{
				if (get_var('subs',array('POST')))
				{
					switch (get_var('subs',array('POST')))
					{
						case 'move':
							$this->bo->delete(array('cat_id' => $this->cat_id,'modify_subs' => True));
							Header('Location: ' . $GLOBALS['phpgw']->link('/index.php',$link_data));
							break;
						case 'drop':
							$this->bo->delete(array('cat_id' => $this->cat_id,'drop_subs' => True));
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

			$GLOBALS['phpgw']->common->phpgw_header();
			$GLOBALS['phpgw']->template->set_file(array('category_delete' => 'delete.tpl'));

			$GLOBALS['phpgw']->template->set_var('error_msg',$error_msg);
			$GLOBALS['phpgw']->template->set_var('deleteheader',lang('Are you sure you want to delete this category ?'));

			$exists = $this->bo->exists(array
			(
				'type'     => 'subs',
				'cat_name' => '',
				'cat_id'   => $this->cat_id
			));

			if ($exists)
			{
					$sub_select = '<input type="radio" name="subs" value="move">' . lang('Do you want to move all subcategories one level down ?') . '<br>';
					$sub_select .= '<input type="radio" name="subs" value="drop">' . lang('Do you want to delete all subcategories ?');
					$GLOBALS['phpgw']->template->set_var('sub_select',$sub_select);
			}

			$GLOBALS['phpgw']->template->set_var('nolink',$GLOBALS['phpgw']->link('/index.php',$link_data));
			$GLOBALS['phpgw']->template->set_var('lang_no',lang('No'));

			$link_data['menuaction'] = 'preferences.uicategories.delete';
			$GLOBALS['phpgw']->template->set_var('action_url',$GLOBALS['phpgw']->link('/index.php',$link_data));
			$GLOBALS['phpgw']->template->set_var('lang_yes',lang('Yes'));
			$GLOBALS['phpgw']->template->fp('phpgw_body','category_delete');
		}
	}
?>
