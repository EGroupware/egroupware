<?php
	/**************************************************************************\
	* eGroupWare - Admin - Global categories                                   *
	* http://www.egroupware.org                                                *
	* Written by Bettina Gille [ceb@phpgroupware.org]                          *
	* Simplified ;-) and icon & color added by RalfBecker@outdoor-training.de  *
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
			'edit'   => True,
			'delete' => True
		);

		function uicategories()
		{
			if ($GLOBALS['phpgw']->acl->check('global_categories_access',1,'admin'))
			{
				$GLOBALS['phpgw']->redirect_link('/index.php');
			}

			$this->bo			= CreateObject('admin.bocategories');
			$this->template		= $GLOBALS['phpgw']->template;
			$this->nextmatchs	= CreateObject('phpgwapi.nextmatchs');

			$this->acl_search = !$GLOBALS['phpgw']->acl->check('global_categories_access',2,'admin');
			$this->acl_add    = !$GLOBALS['phpgw']->acl->check('global_categories_access',4,'admin');
			$this->acl_view   = !$GLOBALS['phpgw']->acl->check('global_categories_access',8,'admin');
			$this->acl_edit   = !$GLOBALS['phpgw']->acl->check('global_categories_access',16,'admin');
			$this->acl_delete = !$GLOBALS['phpgw']->acl->check('global_categories_access',32,'admin');
			$this->acl_add_sub= !$GLOBALS['phpgw']->acl->check('global_categories_access',64,'admin');

			if(!@is_object($GLOBALS['phpgw']->js))
			{
				$GLOBALS['phpgw']->js = CreateObject('phpgwapi.javascript');
			}
			$GLOBALS['phpgw']->js->validate_file('jscode','openwindow','admin');

			$this->appname = get_var('appname',array('GET','POST'));
			$GLOBALS['phpgw_info']['flags']['app_header'] = $GLOBALS['phpgw_info']['apps'][$this->appname ? $this->appname : 'admin']['title'];

			$this->start		= $this->bo->start;
			$this->query		= $this->bo->query;
			$this->sort 		= $this->bo->sort;
			$this->order		= $this->bo->order;
			$this->cat_id		= $this->bo->cat_id;
			if($this->debug) { $this->_debug_sqsof(); }

			$dir = dir(PHPGW_SERVER_ROOT.'/phpgwapi/images');
			while($file = $dir->read())
			{
				if (preg_match('/\\.(png|gif|jpe?g)$/i',$file))
				{
					$this->icons[] = $file;
				}
			}
			$dir->close();
			sort($this->icons);
			$this->img_url = $GLOBALS['phpgw_info']['server']['webserver_url'].'/phpgwapi/images/';
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

		function save_sessiondata()
		{
			$data = array
			(
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

		function set_langs()
		{
			$this->template->set_var('lang_save',lang('Save'));
			$this->template->set_var('lang_search',lang('Search'));
			$this->template->set_var('lang_sub',lang('Add sub'));
			$this->template->set_var('lang_icon',lang('icon'));
			$this->template->set_var('lang_edit',lang('Edit'));
			$this->template->set_var('lang_delete',lang('Delete'));
			$this->template->set_var('lang_parent',lang('Parent category'));
			$this->template->set_var('lang_none',lang('None'));
			$this->template->set_var('lang_name',lang('Name'));
			$this->template->set_var('lang_descr',lang('Description'));
			$this->template->set_var('lang_add',lang('Add'));
			$this->template->set_var('lang_reset',lang('Clear Form'));
			$this->template->set_var('lang_cancel',lang('Cancel'));
			$this->template->set_var('lang_done',lang('Done'));
			$this->template->set_var('lang_color',lang('Color'));
			$this->template->set_var('lang_icon',lang('Icon'));
		}

		function index()
		{
			$link_data = array
			(
				'menuaction'	=> 'admin.uicategories.edit',
				'appname'		=> $this->appname
			);

			if ($_POST['add'])
			{
				$GLOBALS['phpgw']->redirect_link('/index.php',$link_data);
			}

			if ($_POST['done'])
			{
				$GLOBALS['phpgw']->redirect_link('/admin/index.php');
			}

			$this->template->set_file(array('cat_list_t' => 'listcats.tpl'));
			$this->template->set_block('cat_list_t','cat_list','list');
			if (!$this->acl_add)
			{
				$this->template->set_block('cat_list_t','add','addhandle');
			}
			if (!$this->acl_search)
			{
				$this->template->set_block('cat_list_t','search','searchhandle');
			}

			$GLOBALS['phpgw_info']['flags']['app_header'] .= ' - '.lang('Global categories');
			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();
			$this->set_langs();
			$this->template->set_var('query',$this->query);

			$link_data['menuaction'] = 'admin.uicategories.index';
			$this->template->set_var('action_url',$GLOBALS['phpgw']->link('/index.php',$link_data));

			if(!$start)
			{
				$start = 0;
			}

			$cats = $this->bo->get_list();
			if (!is_array($cats)) $cats = array();

			$left  = $this->nextmatchs->left('/index.php',$this->start,$this->bo->cats->total_records,$link_data);
			$right = $this->nextmatchs->right('/index.php',$this->start,$this->bo->cats->total_records,$link_data);
			$this->template->set_var('left',$left);
			$this->template->set_var('right',$right);

			$this->template->set_var('lang_showing',$this->nextmatchs->show_hits($this->bo->cats->total_records,$this->start));

			$this->template->set_var('sort_name',$this->nextmatchs->show_sort_order($this->sort,'cat_name',$this->order,'/index.php',lang('Name'),$link_data));
			$this->template->set_var('sort_description',$this->nextmatchs->show_sort_order($this->sort,'cat_description',$this->order,'/index.php',lang('Description'),$link_data));

			foreach($cats as $cat)
			{
				$data = unserialize($cat['data']);

				if ($data['color'])
				{
					$this->template->set_var('tr_color',$data['color']);
					$gray = (hexdec(substr($data['color'],1,2))+hexdec(substr($data['color'],3,2))+hexdec(substr($data['color'],5,2)))/3;
				}
				else
				{
					$this->nextmatchs->template_alternate_row_color($this->template);
					$gray = 255;
				}
				$this->template->set_var('color',$gray < 128 ? 'style="color: white;"' : '');

				$id = $cat['id'];
				$level = $cat['level'];
				$cat_name = $GLOBALS['phpgw']->strip_html($cat['name']);

				if ($level > 0)
				{
					$space = '&nbsp;&nbsp;';
					$spaceset = str_repeat($space,$level);
					$cat_name = $spaceset . $cat_name;
				}

				$descr = $GLOBALS['phpgw']->strip_html($cat['description']);
				if (!$descr) { $descr = '&nbsp;'; }

				if ($level == 0)
				{
					$cat_name = '<font color="FF0000"><b>' . $cat_name . '</b></font>';
					$descr = '<font color="FF0000"><b>' . $descr . '</b></font>';
				}

				if ($this->appname && $cat['app_name'] == 'phpgw')
				{
					$appendix = '&lt;' . lang('Global') . '&gt;';
				}
				else
				{
					$appendix = '';
				}

				$this->template->set_var(array
				(
					'name' => $cat_name . $appendix,
					'descr' => $descr
				));

				if ($this->acl_add_sub)
				{
					$link_data['menuaction'] = 'admin.uicategories.edit';
					$link_data['cat_parent'] = $id;
					unset($link_data['cat_id']);
					$this->template->set_var('add_sub','<a href="'.$GLOBALS['phpgw']->link('/index.php',$link_data).'">'.
						lang('Add sub').'</a>');
				}
				if ($this->appname && $cat['app_name'] == $this->appname)
				{
					$show_edit_del = True;
				}
				elseif(!$this->appname && $cat['app_name'] == 'phpgw')
				{
					$show_edit_del = True;
				}
				else
				{
					$show_edit_del = False;
				}

				$link_data['cat_id'] = $id;
				unset($link_data['cat_parent']);
				if ($show_edit_del && $this->acl_edit)
				{
					$link_data['menuaction'] = 'admin.uicategories.edit';
					$this->template->set_var('edit','<a href="'.$GLOBALS['phpgw']->link('/index.php',$link_data).'">'.
						lang('Edit').'</a>');
				}
				else
				{
					$this->template->set_var('edit','');
				}
				if ($show_edit_del && $this->acl_delete)
				{
					$link_data['menuaction'] = 'admin.uicategories.delete';
					$this->template->set_var('delete','<a href="'.$GLOBALS['phpgw']->link('/index.php',$link_data).'">'.
						lang('Delete').'</a>');
				}
				else
				{
					$this->template->set_var('delete','');
				}

				$data = unserialize($cat['data']);
				$icon = $data['icon'];
				$dir_img = $GLOBALS['phpgw_info']['server']['webserver_url'] . SEP . 'phpgwapi' . SEP . 'images' . SEP;
				$this->template->set_var('icon', "<img src='". $dir_img . $icon  ."'>");

				$this->template->fp('list','cat_list',True);
			}
			$link_data['menuaction'] = 'admin.uicategories.edit';
			unset($link_data['cat_id']);
			unset($link_data['cat_parent']);
			$this->template->set_var('add_action',$GLOBALS['phpgw']->link('/index.php',$link_data));

			$this->save_sessiondata();
			$this->template->pfp('out','cat_list_t',True);
		}

		function edit()
		{
			$new_parent			= (int)$_POST['new_parent'];
			$cat_parent			= (int)$_POST['cat_parent'];
			$cat_name			= $_POST['cat_name'];
			$cat_description	= $_POST['cat_description'];
			$cat_data			= $_POST['cat_data'];
			$old_parent			= (int)$_POST['old_parent'];
			
			if ($new_parent)
			{
				$cat_parent = $new_parent;
			}

			$link_data = array
			(
				'menuaction'	=> 'admin.uicategories.index',
				'appname'		=> $this->appname
			);

			if (!$this->acl_add && $cat_parent == 0 || !$this->acl_add_sub && $cat_parent != 0)
			{
				$GLOBALS['phpgw']->redirect_link('/index.php');
			}
			if ($_POST['cancel'] || $this->cat_id && !$this->acl_edit || $this->cat_id &&
				(!$this->acl_add && $cat_parent == 0 || !$this->acl_add_sub && $cat_parent != 0))
			{
				$GLOBALS['phpgw']->redirect_link('/index.php',$link_data);
			}

			if ($_POST['save'])
			{
				$data = serialize($cat_data);

				$values = array
				(
					'parent'		=> $cat_parent,
					'descr'			=> $cat_description,
					'name'			=> $cat_name,
					'access'		=> 'public',
					'data'			=> $data
				);
				if ($this->cat_id)
				{
					$values['id'] = $this->cat_id;
					$values['old_parent'] = $old_parent;
				}
				$error = $this->bo->check_values($values);
				if (is_array($error))
				{
					$this->template->set_var('message',$GLOBALS['phpgw']->common->error_list($error));
				}
				else
				{
					$this->cat_id = $this->bo->save_cat($values);
					$GLOBALS['phpgw']->redirect_link('/index.php',$link_data);
				}
			}

			$GLOBALS['phpgw_info']['flags']['app_header'] .= ' - '.($this->cat_id ? lang('Edit global category'):lang('Add global category'));

			$this->set_langs();

			$this->template->set_file(array('form' => 'category_form.tpl'));

			if ($this->cat_id)
			{
				list($cat) = $this->bo->cats->return_single($this->cat_id);
				$cat['data'] = unserialize($cat['data']);
			}
			else
			{
				$cat = array();
				$cat['parent'] = $_GET['cat_parent'];
			}
			// update the old calendar color format, color was added to the description
			if (preg_match('/(#[0-9a-fA-F]{6})\n?$/',$cat['description'],$matches))
			{
				$cat['data']['color'] = $matches[1];
				$cat['description'] = str_replace($matches[1],'',$cat['description']);
			}

			$hidden_vars = '<input type="hidden" name="cat_id" value="' . $this->cat_id . '">' . "\n" .
				'<input type="hidden" name="old_parent" value="' . $cat['parent'] . '">' . "\n";

			$link_data['menuaction']	= 'admin.uicategories.edit';
			$link_data['cat_id']		= $this->cat_id; 
			$this->template->set_var('action_url',$GLOBALS['phpgw']->link('/index.php',$link_data));

			if ($this->acl_delete)
			{
				$link_data['menuaction'] = 'admin.uicategories.delete';

				$this->template->set_var('delete','<form method="POST" action="' . $GLOBALS['phpgw']->link('/index.php',$link_data)
					. '"><input type="submit" value="' . lang('Delete') .'"></form>');
			}
			else
			{
				$this->template->set_var('delete','&nbsp;');
			}
			$this->template->set_var('cat_name',$GLOBALS['phpgw']->strip_html($cat['name']));
			$this->template->set_var('cat_description',$GLOBALS['phpgw']->strip_html($cat['description']));
			$this->template->set_var('category_list',$this->bo->cats->formatted_list(array('selected' => $cat['parent'],'self' => $this->cat_id)));

			if (!is_object($GLOBALS['phpgw']->html))
			{
				$GLOBALS['phpgw']->html = CreateObject('phpgwapi.html');
			}
			$this->template->set_var('color',$GLOBALS['phpgw']->html->inputColor('cat_data[color]',$cat['data']['color'],lang('Click to select a color')));

			$options = '<option value=""'.(!$cat['data']['icon'] ? ' selected="1"':'').'>'.lang('none')."</options>\n";
			foreach ($this->icons as $icon)
			{
				$options .= '<option value="'.$icon.'"'.($icon == $cat['data']['icon'] ? ' selected="1"':'').'>'.
					ucfirst(preg_replace('/\\.(png|gif|jpe?g)$/i','',$icon))."</option>\n";
			}
			$this->template->set_var('select_icon', '<select name="cat_data[icon]" onchange="document.images[\'icon\'].src=\''.$this->img_url.'\' + this.value;">'.$options."</select>\n");
			$this->template->set_var('icon','<img id="icon" src="'. $this->img_url.$cat['data']['icon'] .'">');

			$already_done = array('icon','color');

			if ($extra)
			{
				foreach(explode(',',$extra) as $i => $name)
				{
					$this->template->set_var('class',($i & 1) ? 'row_on' : 'row_off');
					$this->template->set_var('td_data','<input name="cat_data[' . htmlspecialchars($name) . ']" size="50" value="' . htmlspecialchars($cat['data'][$name]) . '">');
					$this->template->set_var('lang_data',lang($name));
					$this->template->fp('row','data_row',True);
					$already_done[] = $name;
				}
			}
			// preserv everything in the data array, not already shown via extra
			if (is_array($cat['data']))
			{
				foreach($cat['data'] as $name => $value)
				{
					if (!in_array($name,$already_done))
					{
						$hidden_vars .= '<input type="hidden" name="cat_data['.htmlspecialchars($name).']" value="' . htmlspecialchars($value) . '">';
					}
				}
			}
			$this->template->set_var('hidden_vars',$hidden_vars);

			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();

			$this->template->pfp('out','form');
		}

		function delete()
		{
			if (!$this->acl_delete)
			{
				$GLOBALS['phpgw']->redirect_link('/index.php');
			}
			$link_data = array
			(
				'menuaction'	=> 'admin.uicategories.index',
				'appname'		=> $this->appname
			);

			if (!$this->cat_id || $_POST['cancel'])
			{
				$GLOBALS['phpgw']->redirect_link('/index.php',$link_data);
			}

			if ($_POST['confirm'])
			{
				if ($_POST['subs'])
				{
					$this->bo->delete($this->cat_id,True);
				}
				else
				{
					$this->bo->delete($this->cat_id,False);
				}
				$GLOBALS['phpgw']->redirect_link('/index.php',$link_data);
			}
			$this->template->set_file(array('category_delete' => 'delete_cat.tpl'));

			if ($this->appname)
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

			$GLOBALS['phpgw_info']['flags']['app_header'] .= ' - '.lang('Delete category');
			if(!@is_object($GLOBALS['phpgw']->js))
			{
				$GLOBALS['phpgw']->js = CreateObject('phpgwapi.javascript');
			}
			$GLOBALS['phpgw']->js->validate_file('jscode','openwindow','admin');
			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();

			$hidden_vars = '<input type="hidden" name="cat_id" value="' . $this->cat_id . '">' . "\n";
			$this->template->set_var('hidden_vars',$hidden_vars);

			$cats = $this->bo->cats->return_single($this->cat_id);
			$this->template->set_var('cat_name',$cat['name']);
			
			if ($apps_cats)
			{
				$this->template->set_block('category_delete','delete','deletehandle');
				$this->template->set_var('messages',lang('This category is currently being used by applications as a parent category') . '<br>'
					. lang('You will need to remove the subcategories before you can delete this category'));

				$this->template->set_var('lang_subs','');
				$this->template->set_var('subs','');
				$this->template->set_var('nolink',$nolink);
				$this->template->set_var('deletehandle','');
				$this->template->set_var('donehandle','');
				$this->template->set_var('lang_ok',lang('Ok'));
				$this->template->pfp('out','category_delete');
			}
			else
			{
				$this->template->set_block('category_delete','done','donehandle');
				$this->template->set_var('messages',lang('Are you sure you want to delete this category ?'));

				$exists = $this->bo->exists(array
				(
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

				$link_data['menuaction'] = 'admin.uicategories.delete';
				$link_data['cat_id'] = $this->cat_id;
				$this->template->set_var('action_url',$GLOBALS['phpgw']->link('/index.php',$link_data));
				$this->template->set_var('lang_yes',lang('Yes'));
				$this->template->set_var('lang_no',lang('No'));
				$this->template->pfp('out','category_delete');
			}
		}
	}
?>
