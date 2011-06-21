<?php
	/**************************************************************************\
	* eGroupWare - Preferences - categories                                    *
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

		var $start;
		var $query;
		var $sort;
		var $order;
		var $cat_id;
		var $referer;

		var $cats_app;

		var $public_functions = array
		(
			'index'  => True,
			'edit'   => True,
			'delete' => True
		);

		function uicategories()
		{
			$cats_app			= get_var('cats_app',array('GET','POST'));
			translation::add_app($cats_app);

			$this->bo			=& CreateObject('preferences.bocategories',$cats_app);
			$this->nextmatchs	=& CreateObject('phpgwapi.nextmatchs');
			$this->account		= $GLOBALS['egw_info']['user']['account_id'];
			$this->user			= $GLOBALS['egw_info']['user']['fullname'];

			$this->start = $this->bo->start;
			$this->query = $this->bo->query;
			$this->sort  = $this->bo->sort;
			$this->order = $this->bo->order;
			$this->referer = $this->bo->referer;

			$dir = dir(EGW_SERVER_ROOT.'/phpgwapi/images');
			while($file = $dir->read())
			{
				if (preg_match('/\\.(png|gif|jpe?g)$/i',$file))
				{
					$this->icons[] = $file;
				}
			}
			$dir->close();
			sort($this->icons);
			$this->img_url = $GLOBALS['egw_info']['server']['webserver_url'].'/phpgwapi/images/';
		}

		function save_sessiondata($cats_app)
		{
			$data = array
			(
				'start' => $this->start,
				'query' => $this->query,
				'sort'  => $this->sort,
				'order' => $this->order,
				'referer' => $this->referer,
			);
			$this->bo->save_sessiondata($data,$cats_app);
		}

		function set_langs()
		{
			$GLOBALS['egw']->template->set_var('lang_access',lang('Private'));
			$GLOBALS['egw']->template->set_var('lang_save',lang('Save'));
			$GLOBALS['egw']->template->set_var('user_name',$this->user);
			$GLOBALS['egw']->template->set_var('lang_search',lang('Search'));
			$GLOBALS['egw']->template->set_var('lang_cancel',lang('Cancel'));
			$GLOBALS['egw']->template->set_var('lang_sub',lang('Add sub'));
			$GLOBALS['egw']->template->set_var('lang_edit',lang('Edit'));
			$GLOBALS['egw']->template->set_var('lang_delete',lang('Delete'));
			$GLOBALS['egw']->template->set_var('lang_parent',lang('Parent category'));
			$GLOBALS['egw']->template->set_var('lang_none',lang('None'));
			$GLOBALS['egw']->template->set_var('lang_name',lang('Name'));
			$GLOBALS['egw']->template->set_var('lang_descr',lang('Description'));
			$GLOBALS['egw']->template->set_var('lang_add',lang('Add'));
			$GLOBALS['egw']->template->set_var('lang_reset',lang('Clear Form'));
			$GLOBALS['egw']->template->set_var('lang_color',lang('Color'));
			$GLOBALS['egw']->template->set_var('lang_icon',lang('Icon'));
		}

		function cat_data($edata,$data)
		{
			foreach ($edata as $name)
			{
				$td_data .= '<td>' . ($name == 'icon' && $data['icon'] ? '<img src="'.$this->img_url.$data['icon'].'">' : $data[$name]) . '</td>' . "\n";
			}
			return $td_data;
		}

		function index()
		{
$cats = new preferences_categories_ui();
return $cats->index();
			$cats_app    = get_var('cats_app',array('GET','POST'));
			$extra       = get_var('extra',array('GET','POST'));
			$global_cats = get_var('global_cats',array('GET','POST'));
			$cats_level  = get_var('cats_level',array('GET','POST'));

			// make categories called via sidebox menu of an app, to behave like a part of that app
			$referer = $GLOBALS['egw']->common->get_referer('/preferences/index.php');
			if (strpos($referer,'menuaction=preferences.uicategories') === false)
			{
				$this->referer = $referer;
			}
			if ($this->referer != '/preferences/index.php')
			{
				$GLOBALS['egw_info']['flags']['currentapp'] = $cats_app;
			}

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
			else
			{
				$edata = array('icon');
			}
			$GLOBALS['egw_info']['flags']['app_header'] = $GLOBALS['egw_info']['apps'][$cats_app]['title'].
				'&nbsp;'.lang('categories for').':&nbsp;'.$this->user;
			$GLOBALS['egw']->common->egw_header();
			echo parse_navbar();

			$GLOBALS['egw']->template->set_file(array(
				'cat_list_t'  => 'listcats.tpl',
			));
			$GLOBALS['egw']->template->set_block('cat_list_t','cat_list','list');

			$this->set_langs();

			$GLOBALS['egw']->template->set_var('title_categories',lang('categories for'));
			$GLOBALS['egw']->template->set_var('lang_app',lang($cats_app));
			$GLOBALS['egw']->template->set_var('actionurl',$GLOBALS['egw']->link('/index.php',$link_data));
			$GLOBALS['egw']->template->set_var('doneurl',$GLOBALS['egw']->link($this->referer));

			if(!$this->start)
			{
				$this->start = 0;
			}

			if(!$global_cats)
			{
				$global_cats = False;
			}

			$cats = $this->bo->get_list($global_cats);
			if (!is_array($cats)) $cats = array();

//--------------------------------- nextmatch --------------------------------------------

			$left  = $this->nextmatchs->left('/index.php',$this->start,$this->bo->cats->total_records,$link_data);
			$right = $this->nextmatchs->right('/index.php',$this->start,$this->bo->cats->total_records,$link_data);
			$GLOBALS['egw']->template->set_var('left',$left);
			$GLOBALS['egw']->template->set_var('right',$right);

			$GLOBALS['egw']->template->set_var('lang_showing',$this->nextmatchs->show_hits($this->bo->cats->total_records,$this->start));

// ------------------------------ end nextmatch ------------------------------------------

//------------------- list header variable template-declarations -------------------------

			$GLOBALS['egw']->template->set_var('sort_name',$this->nextmatchs->show_sort_order($this->sort,'cat_name',$this->order,'/index.php',lang('Name'),$link_data));
			$GLOBALS['egw']->template->set_var('sort_description',$this->nextmatchs->show_sort_order($this->sort,'cat_description',$this->order,'/index.php',lang('Description'),$link_data));

			if (is_array($edata))
			{
				for($i=0;$i<count($edata);$i++)
				{
					$GLOBALS['egw']->template->set_var('sort_data','<td class="th">' . lang($edata[$i]) . '</td>');
				}
			}
			else
			{
				$GLOBALS['egw']->template->set_var('th_data','');
			}

// -------------------------- end header declaration --------------------------------------

			foreach($cats as $cat)
			{
				$data = unserialize($cat['data']);

				if ($data['color'])
				{
					$GLOBALS['egw']->template->set_var('tr_color',$data['color']);
					$gray = (hexdec(substr($data['color'],1,2))+hexdec(substr($data['color'],3,2))+hexdec(substr($data['color'],5,2)))/3;
				}
				else
				{
					$this->nextmatchs->template_alternate_row_color($GLOBALS['egw']->template);
					$gray = 255;
				}
				$GLOBALS['egw']->template->set_var('color',$gray < 128 ? 'style="color: white;"' : '');

				if ($cat['app_name'] == 'phpgw')
				{
					$appendix = '&lt;' . lang('Global') . '&gt;';
				}
				elseif ($cat['owner'] == '-1')
				{
					$appendix = '&lt;' . lang('Global') . '&nbsp;' . $GLOBALS['egw_info']['apps'][$cats_app]['title'] . '&gt;';
				}
				else
				{
					$appendix = '';
				}

				$level = $cat['level'];

				if ($level > 0)
				{
					$space = '&nbsp;&nbsp;';
					$spaceset = str_repeat($space,$level);
					$name = $spaceset . $GLOBALS['egw']->strip_html($cat['name']) . $appendix;
				}

				$descr = $GLOBALS['egw']->strip_html($cat['description']);
				if (!$descr) { $descr = '&nbsp;'; }

				if (is_array($edata))
				{
					if (!is_array($data))
					{
						$holder = '<td>&nbsp;</td>' . "\n";
						$placeholder = str_repeat($holder,count($edata));
						$GLOBALS['egw']->template->set_var('td_data',$placeholder);
					}
					else
					{
						$GLOBALS['egw']->template->set_var('td_data',$this->cat_data($edata,$data));
					}
				}

				if ($level == 0)
				{
					$name = '<font color="FF0000"><b>' . $GLOBALS['egw']->strip_html($cat['name']) . '</b></font>' . $appendix;
					$descr = '<font color="FF0000"><b>' . $descr . '</b></font>';
				}

				$GLOBALS['egw']->template->set_var(array(
					'name'  => $name,
					'descr' => $descr
				));

				$GLOBALS['egw']->template->set_var('app_url',$GLOBALS['egw']->link('/' . $cats_app . '/index.php','cat_id=' . $cat['id']));

				if ($cats_level || ($level == 0))
				{
					if ($cat['owner'] == $this->account || $cat['app_name'] == 'phpgw')
					{
						$link_data['menuaction'] = 'preferences.uicategories.edit';
						$link_data['cat_parent'] = $cat['id'];
						unset($link_data['cat_id']);
						$GLOBALS['egw']->template->set_var('add_sub',$GLOBALS['egw']->link('/index.php',$link_data));
						$GLOBALS['egw']->template->set_var('lang_sub_entry',lang('Add sub'));
					}
				}
				else
				{
					$GLOBALS['egw']->template->set_var('add_sub','');
					$GLOBALS['egw']->template->set_var('lang_sub_entry','&nbsp;');
				}

				$link_data['cat_id'] = $cat['id'];
				if ($cat['owner'] == $this->account && $cat['app_name'] != 'phpgw')
				{
					$link_data['menuaction'] = 'preferences.uicategories.edit';
					$GLOBALS['egw']->template->set_var('edit',$GLOBALS['egw']->link('/index.php',$link_data));
					$GLOBALS['egw']->template->set_var('lang_edit_entry',lang('Edit'));

					$link_data['menuaction'] = 'preferences.uicategories.delete';
					$GLOBALS['egw']->template->set_var('delete',$GLOBALS['egw']->link('/index.php',$link_data));
					$GLOBALS['egw']->template->set_var('lang_delete_entry',lang('Delete'));
				}
				else
				{
					$GLOBALS['egw']->template->set_var('edit','');
					$GLOBALS['egw']->template->set_var('lang_edit_entry','&nbsp;');

					$GLOBALS['egw']->template->set_var('delete','');
					$GLOBALS['egw']->template->set_var('lang_delete_entry','&nbsp;');
				}
				$GLOBALS['egw']->template->fp('list','cat_list',True);
			}
			$link_data['menuaction'] = 'preferences.uicategories.edit';
			unset($link_data['cat_id']);
			unset($link_data['cat_parent']);
			$GLOBALS['egw']->template->set_var('add_action',$GLOBALS['egw']->link('/index.php',$link_data));
			$this->save_sessiondata($cats_app);

			$GLOBALS['egw']->template->pfp('out','cat_list_t',True);
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
			if (!preg_match('/^(#[0-9a-f]+|[a-z]+)?$/i',$_POST['cat_data']['color'])) unset($_POST['cat_data']['color']);
			if (!preg_match('/^[-_\.a-z0-9]+\.(png|gif|jpe?g)$/i',$_POST['cat_data']['icon'])) unset($_POST['cat_data']['icon']);
			$new_parent			= $_POST['new_parent'];
			$cat_parent			= $_POST['cat_parent'];
			$cat_name			= $_POST['cat_name'];
			$cat_description	= $_POST['cat_description'];
			$cat_data			= $_POST['cat_data'];
			$cat_access			= $_POST['cat_access'];
			$old_parent			= $_POST['old_parent'];

			$GLOBALS['egw']->template->set_file(array('form' => 'category_form.tpl'));
			$GLOBALS['egw']->template->set_block('form','data_row','row');
			$GLOBALS['egw']->template->set_block('form','add','addhandle');
			$GLOBALS['egw']->template->set_block('form','edit','edithandle');

			$this->set_langs();
			$GLOBALS['egw']->template->set_var('cancel_url',$GLOBALS['egw']->link('/index.php',$link_data));

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
				$values = array
				(
					'id'			=> (int)$cat_id,
					'parent'		=> $cat_parent,
					'descr'			=> $cat_description,
					'name'			=> $cat_name,
					'access'		=> $cat_access,
					'data'			=> serialize($cat_data),
				);
				if ((int)$cat_id)
				{
					$values['id'] = (int)$cat_id;
					$values['old_parent'] = $old_parent;
				}

				$error = $this->bo->check_values($values);
				if (is_array($error))
				{
					$GLOBALS['egw']->template->set_var('message',$GLOBALS['egw']->common->error_list($error));
				}
				else
				{
					$cat_id = $this->bo->save_cat($values);
					return $this->index();
					//$GLOBALS['egw']->template->set_var('message',lang('Category %1 has been updated !',$cat_name));
				}
			}

			if ($cat_id)
			{
				list($cat) = $this->bo->cats->return_single($cat_id);
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

			$link_data['menuaction'] = 'preferences.uicategories.edit';
			$GLOBALS['egw']->template->set_var('actionurl',$GLOBALS['egw']->link('/index.php',$link_data));

			$GLOBALS['egw']->template->set_var('cat_name',$GLOBALS['egw']->strip_html($cat['name']));
			$GLOBALS['egw']->template->set_var('cat_description',$GLOBALS['egw']->strip_html($cat['description']));

			$hidden_vars = '<input type="hidden" name="old_parent" value="' . $cat['parent'] . '">';

			if ($cats_level)
			{
				$type = 'all';
			}
			else
			{
				$type = 'mains';
			}

			$GLOBALS['egw']->template->set_var('category_list',$this->bo->cats->formatted_list(array(
				'type' => $type,'selected' => $cat['parent'],
				'globals' => $global_cats, 'self' => $cat_id
			)));

			$GLOBALS['egw']->template->set_var('access','<input type="checkbox" name="cat_access" value="True"'
				. ($cat['access'] == 'private' ? ' checked' : '') . '>');

			$GLOBALS['egw']->template->set_var('color',html::inputColor('cat_data[color]',$cat['data']['color'],lang('Click to select a color')));

			$options = '<option value=""'.(!$cat['data']['icon'] ? ' selected="1"':'').'>'.lang('none')."</options>\n";
			foreach ($this->icons as $icon)
			{
				$options .= '<option value="'.$icon.'"'.($icon == $cat['data']['icon'] ? ' selected="1"':'').'>'.
					ucfirst(preg_replace('/\\.(png|gif|jpe?g)$/i','',$icon))."</option>\n";
			}
			$GLOBALS['egw']->template->set_var('select_icon', '<select name="cat_data[icon]" onchange="document.images[\'icon\'].src=\''.$this->img_url.'\' + this.value;">'.$options."</select>\n");
			$GLOBALS['egw']->template->set_var('icon','<img id="icon" src="'. $this->img_url.$cat['data']['icon'] .'">');

			$already_done = array('icon','color');

			if ($extra)
			{
				foreach(explode(',',$extra) as $i => $name)
				{
					$GLOBALS['egw']->template->set_var('class',($i & 1) ? 'row_on' : 'row_off');
					$GLOBALS['egw']->template->set_var('td_data','<input name="cat_data[' . htmlspecialchars($name) . ']" size="50" value="' . htmlspecialchars($cat['data'][$name]) . '">');
					$GLOBALS['egw']->template->set_var('lang_data',lang($name));
					$GLOBALS['egw']->template->fp('row','data_row',True);
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
			$GLOBALS['egw']->template->set_var('hidden_vars',$hidden_vars);

			if ($cat['owner'] == $this->account)
			{
				$link_data['menuaction'] = 'preferences.uicategories.delete';
				$GLOBALS['egw']->template->set_var('delete','<form method="POST" action="' . $GLOBALS['egw']->link('/index.php',$link_data)
					. '"><input type="submit" value="' . lang('Delete') .'"></form>');
			}
			else
			{
				$GLOBALS['egw']->template->set_var('delete','&nbsp;');
			}

			$GLOBALS['egw_info']['flags']['app_header'] = lang($cat_id ? 'Edit %1 category for' : 'Add %1 category for',
				$GLOBALS['egw_info']['apps'][$cats_app]['title']).':&nbsp;'.$this->user;
			$GLOBALS['egw']->common->egw_header();
			echo parse_navbar();

			$GLOBALS['egw']->template->set_var('edithandle','');
			$GLOBALS['egw']->template->set_var('addhandle','');
			$GLOBALS['egw']->template->pfp('out','form');
			$GLOBALS['egw']->template->pfp('edithandle','edit');
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
				$GLOBALS['egw']->redirect_link('/index.php',$link_data);
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
				$GLOBALS['egw']->redirect_link('/index.php',$link_data);
			}
			else
			{
				$GLOBALS['egw']->template->set_file(array('category_delete' => 'delete.tpl'));

				$GLOBALS['egw_info']['flags']['app_header'] = lang('Delete Categories');
				$GLOBALS['egw']->common->egw_header();
				echo parse_navbar();

				$GLOBALS['egw']->template->set_var('deleteheader',lang('Are you sure you want to delete this category ?'));

				$exists = $this->bo->exists(array
				(
					'type'     => 'subs',
					'cat_name' => '',
					'cat_id'   => $cat_id
				));

				if ($exists)
				{
					$GLOBALS['egw']->template->set_var('lang_subs',lang('Do you also want to delete all subcategories ?'));
					$GLOBALS['egw']->template->set_var('subs','<input type="checkbox" name="subs" value="True">');
				}
				else
				{
					$GLOBALS['egw']->template->set_var('lang_subs','');
					$GLOBALS['egw']->template->set_var('subs', '');
				}

				$GLOBALS['egw']->template->set_var('lang_no',lang('No'));
				$link_data['menuaction'] = 'preferences.uicategories.delete';
				$GLOBALS['egw']->template->set_var('action_url',$GLOBALS['egw']->link('/index.php',$link_data));
				$GLOBALS['egw']->template->set_var('lang_yes',lang('Yes'));
				$GLOBALS['egw']->template->pfp('out','category_delete');
			}
		}
	}
?>
