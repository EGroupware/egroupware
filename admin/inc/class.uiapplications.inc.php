<?php
  /**************************************************************************\
  * phpGroupWare - administration                                            *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	class uiapplications
	{
		var $public_functions = array(
			'get_list' => True,
			'add'      => True,
			'edit'     => True,
			'delete'   => True,
			'register_all_hooks' => True
		);

		var $bo;
		var $nextmatchs;

		function uiapplications()
		{
			$this->bo = CreateObject('admin.boapplications');
			$this->nextmatchs = CreateObject('phpgwapi.nextmatchs');
		}

		function get_list()
		{
			if ($GLOBALS['phpgw']->acl->check('applications_access',1,'admin'))
			{
				$GLOBALS['phpgw']->redirect_link('/index.php');
			}
			$can_add    = !$GLOBALS['phpgw']->acl->check('applications_access',2,'admin');
			$can_edit   = !$GLOBALS['phpgw']->acl->check('applications_access',4,'admin');
			$can_delete = !$GLOBALS['phpgw']->acl->check('applications_access',8,'admin');
			
			$GLOBALS['phpgw_info']['flags']['app_header'] = lang('Admin').' - '.lang('Installed applications');
			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();

			$GLOBALS['phpgw']->template->set_file(array('applications' => 'applications.tpl'));
			$GLOBALS['phpgw']->template->set_block('applications','list','list');
			$GLOBALS['phpgw']->template->set_block('applications','row','row');
			$GLOBALS['phpgw']->template->set_block('applications','add','add');
			
			$start = get_var('start',array('POST','GET'));
			$sort  = $_GET['sort'];
			$order = $_GET['order'];
			$offset = $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs'];

			$apps = $this->bo->get_list();
			$total = count($apps);

			$sort = $sort ? $sort : 'ASC';

			if($sort == 'ASC')
			{
				ksort($apps);
			}
			else
			{
				krsort($apps);
			}

			if ($start && $offset)
			{
				$limit = $start + $offset;
			}
			elseif ($start && !$offset)
			{
				$limit = $start;
			}
			elseif(!$start && !$offset)
			{
				$limit = $total;
			}
			else
			{
				$start = 0;
				$limit = $offset;
			}

			if ($limit > $total)
			{
				$limit = $total;
			}

			$i = 0;
			$applications = array();
			while(list($app,$data) = @each($apps))
			{
				if($i >= $start && $i<= $limit)
				{
					$applications[$app] = $data;
				}
				$i++;
			}

			$GLOBALS['phpgw']->template->set_var('bg_color',$GLOBALS['phpgw_info']['theme']['bg_color']);
			$GLOBALS['phpgw']->template->set_var('th_bg',$GLOBALS['phpgw_info']['theme']['th_bg']);

			$GLOBALS['phpgw']->template->set_var('sort_title',$this->nextmatchs->show_sort_order($sort,'title','title','/index.php',lang('Title'),'&menuaction=admin.uiapplications.get_list'));
			$GLOBALS['phpgw']->template->set_var('lang_showing',$this->nextmatchs->show_hits($total,$start));
			$GLOBALS['phpgw']->template->set_var('left',$this->nextmatchs->left('/index.php',$start,$total,'menuaction=admin.uiapplications.get_list'));
			$GLOBALS['phpgw']->template->set_var('right',$this->nextmatchs->right('index.php',$start,$total,'menuaction=admin.uiapplications.get_list'));

			$GLOBALS['phpgw']->template->set_var('lang_edit',lang('Edit'));
			$GLOBALS['phpgw']->template->set_var('lang_delete',lang('Delete'));
			$GLOBALS['phpgw']->template->set_var('lang_enabled',lang('Enabled'));

			$GLOBALS['phpgw']->template->set_var('new_action',$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiapplications.add'));
			$GLOBALS['phpgw']->template->set_var('lang_note',lang('(To install new applications use<br><a href="setup/" target="setup">Setup</a> [Manage Applications] !!!)'));
			$GLOBALS['phpgw']->template->set_var('lang_add',lang('add'));

			foreach($applications as $app)
			{
				$tr_color = $this->nextmatchs->alternate_row_color($tr_color);

				$GLOBALS['phpgw']->template->set_var('tr_color',$tr_color);
				$GLOBALS['phpgw']->template->set_var('name',$GLOBALS['phpgw_info']['apps'][$app['name']]['title']);

				$GLOBALS['phpgw']->template->set_var('edit',$can_edit ? '<a href="' . $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiapplications.edit&app_name=' . urlencode($app['name'])) . '&start='.$start.'"> ' . lang('Edit') . ' </a>' : '&nbsp;');
				$GLOBALS['phpgw']->template->set_var('delete',$can_delete ? '<a href="' . $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiapplications.delete&app_name=' . urlencode($app['name'])) . '&start='.$start.'"> ' . lang('Delete') . ' </a>' : '&nbsp;');

				if ($app['status'])
				{
					$status = lang('Yes');
				}
				else
				{
					$status = '<b>' . lang('No') . '</b>';
				}
				$GLOBALS['phpgw']->template->set_var('status',$status);

				$GLOBALS['phpgw']->template->parse('rows','row',True);
			}
			if ($can_add)
			{
				$GLOBALS['phpgw']->template->parse('addbutton','add');
			}
			else
			{
				$GLOBALS['phpgw']->template->set_var('addbutton','');
			}

			$GLOBALS['phpgw']->template->pparse('out','list');
		}

		function display_row($label, $value)
		{
			$GLOBALS['phpgw']->template->set_var('tr_color',$this->nextmatchs->alternate_row_color());
			$GLOBALS['phpgw']->template->set_var('label',$label);
			$GLOBALS['phpgw']->template->set_var('value',$value);
			$GLOBALS['phpgw']->template->parse('rows','row',True);
		}

		function add()
		{
			if ($GLOBALS['phpgw']->acl->check('applications_access',2,'admin'))
			{
				$GLOBALS['phpgw']->redirect_link('/index.php');
			}
			$start = get_var('start',array('POST','GET'));
	
			$GLOBALS['phpgw']->template->set_file(array('application' => 'application_form.tpl'));
			$GLOBALS['phpgw']->template->set_block('application','form','form');
			$GLOBALS['phpgw']->template->set_block('application','row','row');
			$GLOBALS['phpgw']->template->set_block('form','delete_button');
			$GLOBALS['phpgw']->template->set_var('delete_button','');

			if ($_POST['cancel'])
			{
				$GLOBALS['phpgw']->redirect_link('/index.php','menuaction=admin.uiapplications.get_list&start='.$start);
			}

			if ($_POST['save'])
			{
				$totalerrors = 0;

				$app_order    = $_POST['app_order'] ? $_POST['app_order'] : 0;
				$n_app_name   = chop($_POST['n_app_name']);
				$n_app_status = $_POST['n_app_status'];

				if ($this->bo->exists($n_app_name))
				{
					$error[$totalerrors++] = lang('That application name already exists.');
				}
				if (preg_match("/\D/",$app_order))
				{
					$error[$totalerrors++] = lang('That application order must be a number.');
				}
				if (!$n_app_name)
				{
					$error[$totalerrors++] = lang('You must enter an application name.');
				}

				if (!$totalerrors)
				{
					$this->bo->add(array(
						'n_app_name'   => $n_app_name,
						'n_app_status' => $n_app_status,
						'app_order'    => $app_order
					));

					$GLOBALS['phpgw']->redirect_link('/index.php','menuaction=admin.uiapplications.get_list&start='.$start);
					$GLOBALS['phpgw']->common->phpgw_exit();
				}
				else
				{
					$GLOBALS['phpgw']->template->set_var('error','<p><center>' . $GLOBALS['phpgw']->common->error_list($error) . '</center><br>');
				}
			}
			else
			{	// else submit
				$GLOBALS['phpgw']->template->set_var('error','');
			}

			$GLOBALS['phpgw_info']['flags']['app_header'] = lang('Admin').' - '.lang('Add new application');
			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();

			$GLOBALS['phpgw']->template->set_var('th_bg',$GLOBALS['phpgw_info']['theme']['th_bg']);

			$GLOBALS['phpgw']->template->set_var('hidden_vars','<input type="hidden" name="start" value="'.$start.'">');
			$GLOBALS['phpgw']->template->set_var('form_action',$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiapplications.add'));

			$this->display_row(lang('application name'),'<input name="n_app_name" value="' . $n_app_name . '">');

			if(!isset($n_app_status))
			{
				$n_app_status = 1;
			}

			$selected[$n_app_status] = ' selected';
			$status_html = '<option value="0"' . $selected[0] . '>' . lang('Disabled') . '</option>'
				. '<option value="1"' . $selected[1] . '>' . lang('Enabled') . '</option>'
				. '<option value="2"' . $selected[2] . '>' . lang('Enabled - Hidden from navbar') . '</option>';
			$this->display_row(lang('Status'),'<select name="n_app_status">' . $status_html . '</select>');

			if (!$app_order)
			{
				$app_order = $this->bo->app_order();
			}

			$this->display_row(lang('Select which location this app should appear on the navbar, lowest (left) to highest (right)'),'<input name="app_order" value="' . $app_order . '">');

			$GLOBALS['phpgw']->template->set_var('lang_save_button',lang('Add'));
			$GLOBALS['phpgw']->template->set_var('lang_cancel_button',lang('Cancel'));
			$GLOBALS['phpgw']->template->pparse('phpgw_body','form');
		}

		function edit()
		{
			if ($GLOBALS['phpgw']->acl->check('applications_access',4,'admin'))
			{
				$GLOBALS['phpgw']->redirect_link('/index.php');
			}
			$app_name = get_var('app_name',array('POST','GET'));
			$start = get_var('start',array('POST','GET'));

			$GLOBALS['phpgw']->template->set_file(array('application' => 'application_form.tpl'));
			$GLOBALS['phpgw']->template->set_block('application','form','form');
			$GLOBALS['phpgw']->template->set_block('application','row','row');

			if ($_POST['cancel'])
			{
				$GLOBALS['phpgw']->redirect_link('/index.php','menuaction=admin.uiapplications.get_list&start='.$start);
			}
			
			if ($_POST['delete'])
			{
				return $this->delete();
			}

			if ($_POST['save'])
			{
				$totalerrors = 0;

				$app_order    = $_POST['app_order'] ? $_POST['app_order'] : 0;
				$n_app_name   = chop($_POST['n_app_name']);
				$n_app_status = $_POST['n_app_status'];

				if (! $n_app_name)
				{
					$error[$totalerrors++] = lang('You must enter an application name.');
				}

				if ($app_name != $n_app_name)
				{
					if ($this->bo->exists($n_app_name))
					{
						$error[$totalerrors++] = lang('That application name already exists.');
					}
				}
 
				if (! $totalerrors)
				{
					$this->bo->save(array(
						'n_app_name'   => $n_app_name,
						'n_app_status' => $n_app_status,
						'app_order'    => $app_order,
						'old_app_name' => $app_name
					));

					$GLOBALS['phpgw']->redirect_link('/index.php','menuaction=admin.uiapplications.get_list&start='.$start);
				}
			}

			$GLOBALS['phpgw_info']['flags']['app_header'] = lang('Admin').' - '.lang('Edit application');
			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();

			if ($totalerrors)
			{
				$GLOBALS['phpgw']->template->set_var('error','<p><center>' . $GLOBALS['phpgw']->common->error_list($error) . '</center><br>');
			}
			else
			{
				$GLOBALS['phpgw']->template->set_var('error','');
				list($n_app_name,$n_app_title,$n_app_status,$old_app_name,$app_order) = $this->bo->read($app_name);
			}
			$GLOBALS['phpgw']->template->set_var('hidden_vars','<input type="hidden" name="start" value="'.$start.'">'.
				'<input type="hidden" name="app_name" value="' . $app_name . '">');
			$GLOBALS['phpgw']->template->set_var('th_bg',$GLOBALS['phpgw_info']['theme']['th_bg']);
			$GLOBALS['phpgw']->template->set_var('form_action',$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiapplications.edit'));

			$this->display_row(lang('application name'),'<input name="n_app_name" value="' . $n_app_name . '">');

			$GLOBALS['phpgw']->template->set_var('lang_status',lang('Status'));
			$GLOBALS['phpgw']->template->set_var('lang_save_button',lang('Save'));
			$GLOBALS['phpgw']->template->set_var('lang_cancel_button',lang('Cancel'));
			$GLOBALS['phpgw']->template->set_var('lang_delete_button',lang('Delete'));

			$selected[$n_app_status] = ' selected';
			$status_html = '<option value="0"' . $selected[0] . '>' . lang('Disabled') . '</option>'
				. '<option value="1"' . $selected[1] . '>' . lang('Enabled') . '</option>'
				. '<option value="2"' . $selected[2] . '>' . lang('Enabled - Hidden from navbar') . '</option>';

			$this->display_row(lang("Status"),'<select name="n_app_status">' . $status_html . '</select>');
			$this->display_row(lang("Select which location this app should appear on the navbar, lowest (left) to highest (right)"),'<input name="app_order" value="' . $app_order . '">');

			$GLOBALS['phpgw']->template->set_var('select_status',$status_html);
			$GLOBALS['phpgw']->template->pparse('phpgw_body','form');
		}

		function delete()
		{
			if ($GLOBALS['phpgw']->acl->check('applications_access',8,'admin'))
			{
				$GLOBALS['phpgw']->redirect_link('/index.php');
			}
			$app_name = get_var('app_name',array('POST','GET'));
			$start = get_var('start',array('POST','GET'));
			
			if (!$app_name || $_POST['no'] || $_POST['yes'])
			{
				if ($_POST['yes'])
				{
					$this->bo->delete($app_name);
				}
				$GLOBALS['phpgw']->redirect_link('/index.php','menuaction=admin.uiapplications.get_list&start='.$start);
			}

			$GLOBALS['phpgw']->template->set_file(array('body' => 'delete_common.tpl'));

			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();

			$GLOBALS['phpgw']->template->set_var('messages',lang('Are you sure you want to delete the application %1 ?',$GLOBALS['phpgw_info']['apps'][$app_name]['title']));
			$GLOBALS['phpgw']->template->set_var('no',lang('No'));
			$GLOBALS['phpgw']->template->set_var('yes',lang('Yes'));
			$GLOBALS['phpgw']->template->set_var('form_action',$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiapplications.delete'));
			$GLOBALS['phpgw']->template->set_var('hidden_vars','<input type="hidden" name="start" value="'.$start.'">'.
				'<input type="hidden" name="app_name" value="'. urlencode($app_name) . '">');
			$GLOBALS['phpgw']->template->pparse('phpgw_body','body');
		}
		
		function register_all_hooks()
		{
			if ($GLOBALS['phpgw']->acl->check('applications_access',16,'admin'))
			{
				$GLOBALS['phpgw']->redirect_link('/index.php');
			}
			if (!is_object($GLOBALS['phpgw']->hooks))
			{
				$GLOBALS['phpgw']->hooks = CreateObject('phpgwapi.hooks');
			}
			$GLOBALS['phpgw']->hooks->register_all_hooks();
			
			$GLOBALS['phpgw']->redirect_link('/admin/index.php');
		}
	}
?>
