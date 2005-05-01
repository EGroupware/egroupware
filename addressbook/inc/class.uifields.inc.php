<?php
  /**************************************************************************\
  * eGroupWare - Addressbook                                                 *
  * http://www.egroupware.org                                                *
  * Written by Joseph Engo <jengo@phpgroupware.org> and                      *
  * Miles Lott <milosch@groupwhere.org>                                      *
  * -----------------------------------------------                          *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	class uifields
	{
		var $public_functions = array(
			'index'  => True,
			'add'    => True,
			'edit'   => True,
			'delete' => True
		);

		function uifields($only_bo=False)
		{
			if (!$only_bo)
			{
				$GLOBALS['egw']->template = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
				$GLOBALS['egw']->nextmatchs = CreateObject('phpgwapi.nextmatchs');
			}
			$this->config = CreateObject('phpgwapi.config','addressbook');
		}

		function index()
		{
			if(!$GLOBALS['egw']->acl->check('run',1,'admin'))
			{
				$GLOBALS['egw']->common->phpgw_header();
				echo parse_navbar();
				echo lang('access not permitted');
				$GLOBALS['egw']->common->phpgw_exit();
			}

			$GLOBALS['egw']->template->set_file(array(
				'field_list_t' => 'listfields.tpl',
				'field_list'   => 'listfields.tpl'
			));
			$GLOBALS['egw']->template->set_block('field_list_t','field_list','list');

			$field = $_POST['field'];
			$start = $_POST['start'] ? $_POST['start'] : $_GET['start'];
			$query = $_POST['query'] ? $_POST['query'] : $_GET['query'];
			$sort  = $_POST['sort']  ? $_POST['sort']  : $_GET['sort'];

			$common_hidden_vars =
				'<input type="hidden" name="sort"   value="' . $sort   . '">' . "\n"
				. '<input type="hidden" name="order"  value="' . $order  . '">' . "\n"
				. '<input type="hidden" name="query"  value="' . $query  . '">' . "\n"
				. '<input type="hidden" name="start"  value="' . $start  . '">' . "\n"
				. '<input type="hidden" name="filter" value="' . $filter . '">' . "\n";

			$GLOBALS['egw']->template->set_var('lang_action',lang('Custom Fields'));
			$GLOBALS['egw']->template->set_var('add_action',$GLOBALS['egw']->link('/index.php','menuaction=addressbook.uifields.add'));
			$GLOBALS['egw']->template->set_var('lang_add',lang('Add'));
			$GLOBALS['egw']->template->set_var('title_fields',lang('addressbook').' - '.lang('Custom Fields'));
			$GLOBALS['egw']->template->set_var('lang_search',lang('Search'));
			$GLOBALS['egw']->template->set_var('actionurl',$GLOBALS['egw']->link('/index.php','menuaction=addressbook.uifields.index'));
			$GLOBALS['egw']->template->set_var('lang_done',lang('Done'));
			$GLOBALS['egw']->template->set_var('doneurl',$GLOBALS['egw']->link('/admin/index.php'));

			if(!$start)
			{
				$start = 0;
			}

			if(!$sort)
			{
				$sort = 'ASC';
			}

			$fields = $this->read_custom_fields($start,$limit,$query,$sort);
			$total_records = count($fields);

			$GLOBALS['egw']->common->phpgw_header();
			echo parse_navbar();

			$GLOBALS['egw']->template->set_var('left',$GLOBALS['egw']->nextmatchs->left('/index.php',$start,$total_records,'menuaction=addressbook.uifields.index'));
			$GLOBALS['egw']->template->set_var('right',$GLOBALS['egw']->nextmatchs->right('/index.php',$start,$total_records,'menuaction=addressbook.uifields.index'));

			$GLOBALS['egw']->template->set_var('lang_showing',$GLOBALS['egw']->nextmatchs->show_hits($total_records,$start));

			$GLOBALS['egw']->template->set_var('th_bg',$GLOBALS['egw_info']['theme']['th_bg']);
			$GLOBALS['egw']->template->set_var('sort_field',$GLOBALS['egw']->nextmatchs->show_sort_order($sort,'name',$order,'/index.php',lang('Name')),'menuaction=addressbook.uifields.index');
			$GLOBALS['egw']->template->set_var('lang_edit',lang('Edit'));
			$GLOBALS['egw']->template->set_var('lang_delete',lang('Delete'));

			for($i=0;$i<count($fields);$i++)
			{
				$tr_color = $GLOBALS['egw']->nextmatchs->alternate_row_color($tr_color);
				$GLOBALS['egw']->template->set_var(tr_color,$tr_color);

				$field = $fields[$i]['name'];
				$title = $fields[$i]['title'];

				$GLOBALS['egw']->template->set_var('cfield',$title);

				$params = array(
					'menuaction' => 'addressbook.uifields.edit',
					'field'      => urlencode($field),
					'start'      => $start,
					'query'      => $query,
					'sort'       => $sort,
					'order'      => $order,
					'filter'     => $filter
				);
				$GLOBALS['egw']->template->set_var('edit',$GLOBALS['egw']->link('/index.php',$params));
				$GLOBALS['egw']->template->set_var('lang_edit_entry',lang('Edit'));

				$params['menuaction'] = 'addressbook.uifields.delete';
				$GLOBALS['egw']->template->set_var('delete',$GLOBALS['egw']->link('/index.php',$params));
				$GLOBALS['egw']->template->set_var('lang_delete_entry',lang('Delete'));
				$GLOBALS['egw']->template->parse('list','field_list',True);
			}

			$GLOBALS['egw']->template->parse('out','field_list_t',True);
			$GLOBALS['egw']->template->p('out');
		}

		function add()
		{
			if(!$GLOBALS['egw']->acl->check('run',1,'admin'))
			{
				$GLOBALS['egw']->common->phpgw_header();
				echo parse_navbar();
				echo lang('access not permitted');
				$GLOBALS['egw']->common->phpgw_exit();
			}

			$field      = stripslashes($_POST['field']);
			$field_name = stripslashes($_POST['field_name']);
			$start      = $_POST['start'];
			$query      = $_POST['query'];
			$sort       = $_POST['sort'];
			$submit     = $_POST['submit'];

			$GLOBALS['egw']->template->set_file(array('form' => 'field_form.tpl'));
			$GLOBALS['egw']->template->set_block('form','add','addhandle');
			$GLOBALS['egw']->template->set_block('form','edit','edithandle');

			if($submit)
			{
				$errorcount = 0;

				if(!$field_name)
				{
					$error[$errorcount++] = lang('Please enter a name for that field !');
				}

				$fields = $this->read_custom_fields($start,$limit,$field_name);
				if($fields[0]['name'])
				{
					$error[$errorcount++] = lang('That field name has been used already !');
				}

				if(!$error)
				{
					$this->save_custom_field($field,$field_name);
				}
			}

			$GLOBALS['egw']->common->phpgw_header();
			echo parse_navbar();

			if($errorcount)
			{
				$GLOBALS['egw']->template->set_var('message',$GLOBALS['egw']->common->error_list($error));
			}
			if(($submit) && (!$error) && (!$errorcount))
			{
				$GLOBALS['egw']->template->set_var('message',lang('Field %1 has been added !', $field_name));
			}
			if((!$submit) && (!$error) && (!$errorcount))
			{
				$GLOBALS['egw']->template->set_var('message','');
			}

			$GLOBALS['egw']->template->set_var('title_fields',lang('Add Custom Field'));
			$GLOBALS['egw']->template->set_var('actionurl',$GLOBALS['egw']->link('/index.php','menuaction=addressbook.uifields.add'));
			$GLOBALS['egw']->template->set_var('doneurl',$GLOBALS['egw']->link('/index.php','menuaction=addressbook.uifields.index'));
			$GLOBALS['egw']->template->set_var('hidden_vars','<input type="hidden" name="field" value="' . $field . '">');

			$GLOBALS['egw']->template->set_var('lang_name',lang('Field name'));

			$GLOBALS['egw']->template->set_var('lang_add',lang('Add'));
			$GLOBALS['egw']->template->set_var('lang_reset',lang('Clear Form'));
			$GLOBALS['egw']->template->set_var('lang_done',lang('Done'));

			$GLOBALS['egw']->template->set_var('field_name',$field_name);

			$GLOBALS['egw']->template->set_var('edithandle','');
			$GLOBALS['egw']->template->set_var('addhandle','');
			$GLOBALS['egw']->template->pparse('out','form');
			$GLOBALS['egw']->template->pparse('addhandle','add');
		}

		function edit()
		{
			if(!$GLOBALS['egw']->acl->check('run',1,'admin'))
			{
				$GLOBALS['egw']->common->phpgw_header();
				echo parse_navbar();
				echo lang('access not permitted');
				$GLOBALS['egw']->common->phpgw_exit();
			}

			$field      = stripslashes($_POST['field'] ? $_POST['field'] : $_GET['field']);
			$field_name = stripslashes($_POST['field_name']);
			$start      = $_POST['start'] ? $_POST['start'] : $_GET['start'];
			$query      = $_POST['query'] ? $_POST['query'] : $_GET['query'];
			$sort       = $_POST['sort']  ? $_POST['sort']  : $_GET['sort'];
			$submit     = $_POST['submit'];

			if(!$field)
			{
				Header('Location: ' . $GLOBALS['egw']->link('/index.php',"menuaction=addressbook.uifields.index&sort=$sort&query=$query&start=$start"));
			}

			$GLOBALS['egw']->template->set_file(array('form' => 'field_form.tpl'));
			$GLOBALS['egw']->template->set_block('form','add','addhandle');
			$GLOBALS['egw']->template->set_block('form','edit','edithandle');

			$hidden_vars = '<input type="hidden" name="sort" value="' . $sort . '">' . "\n"
				. '<input type="hidden" name="query" value="' . $query . '">' . "\n"
				. '<input type="hidden" name="start" value="' . $start . '">' . "\n"
				. '<input type="hidden" name="field" value="' . $field . '">' . "\n";

			if($submit)
			{
				$errorcount = 0;
				if(!$field_name)
				{
					$error[$errorcount++] = lang('Please enter a name for that field !');
				}

				if(!$error)
				{
					$this->save_custom_field($field,$field_name);
				}
			}

			$GLOBALS['egw']->common->phpgw_header();
			echo parse_navbar();

			if($errorcount)
			{
				$GLOBALS['egw']->template->set_var('message',$GLOBALS['egw']->common->error_list($error));
			}
			if(($submit) && (!$error) && (!$errorcount))
			{
				$GLOBALS['egw']->template->set_var('message',lang('Field %1 has been updated !', $field_name));
			}
			if((!$submit) && (!$error) && (!$errorcount))
			{
				$GLOBALS['egw']->template->set_var('message','');
			}

			if($submit)
			{
				$field = $field_name;
			}
			else
			{
				$fields = $this->read_custom_fields($start,$limit,$field);
				$field  = $GLOBALS['egw']->strip_html($fields[0]['title']);
				$fn = $fields[0]['name'];
			}

			$GLOBALS['egw']->template->set_var('title_fields',lang('Edit Custom Field'));
			$GLOBALS['egw']->template->set_var('actionurl',$GLOBALS['egw']->link('/index.php','menuaction=addressbook.uifields.edit'));
			$GLOBALS['egw']->template->set_var('deleteurl',$GLOBALS['egw']->link('/index.php',"menuaction=addressbook.uifields.delete&field=$fn&start=$start&query=$query&sort=$sort"));
			$GLOBALS['egw']->template->set_var('doneurl',$GLOBALS['egw']->link('/index.php',"menuaction=addressbook.uifields.index&start=$start&query=$query&sort=$sort"));

			$GLOBALS['egw']->template->set_var('hidden_vars',$hidden_vars);
			$GLOBALS['egw']->template->set_var('lang_name',lang('Field name'));

			$GLOBALS['egw']->template->set_var('lang_done',lang('Done'));
			$GLOBALS['egw']->template->set_var('lang_edit',lang('Edit'));
			$GLOBALS['egw']->template->set_var('lang_delete',lang('Delete'));

			$GLOBALS['egw']->template->set_var('field_name',$field);

			$GLOBALS['egw']->template->set_var('edithandle','');
			$GLOBALS['egw']->template->set_var('addhandle','');

			$GLOBALS['egw']->template->pparse('out','form');
			$GLOBALS['egw']->template->pparse('edithandle','edit');
		}

		function delete()
		{
			if(!$GLOBALS['egw']->acl->check('run',1,'admin'))
			{
				$GLOBALS['egw']->common->phpgw_header();
				echo parse_navbar();
				echo lang('access not permitted');
				$GLOBALS['egw']->common->phpgw_exit();
			}

			$field    = urldecode($_POST['field'] ? $_POST['field'] : $_GET['field']);
			$field_id = $_POST['field_id'] ? $_POST['field_id'] : $_GET['field_id'];
			$start    = $_POST['start'] ? $_POST['start'] : $_GET['start'];
			$query    = $_POST['query'] ? $_POST['query'] : $_GET['query'];
			$sort     = $_POST['sort']  ? $_POST['sort']  : $_GET['sort'];

			if(!$field)
			{
				Header('Location: ' . $GLOBALS['egw']->link('/index.php','menuaction=addressbook.uifields.index'));
			}

			if($_POST['confirm'])
			{
				$this->save_custom_field($field);
				Header('Location: ' . $GLOBALS['egw']->link('/index.php',"menuaction=addressbook.uifields.index&start=$start&query=$query&sort=$sort"));
			}
			else
			{
				$GLOBALS['egw']->common->phpgw_header();
				echo parse_navbar();

				$hidden_vars = '<input type="hidden" name="sort" value="' . $sort . '">' . "\n"
					. '<input type="hidden" name="order" value="' . $order .'">' . "\n"
					. '<input type="hidden" name="query" value="' . $query .'">' . "\n"
					. '<input type="hidden" name="start" value="' . $start .'">' . "\n"
					. '<input type="hidden" name="field" value="' . $field .'">' . "\n";

				$GLOBALS['egw']->template->set_file(array('field_delete' => 'delete_common.tpl'));
				$GLOBALS['egw']->template->set_var('messages',lang('Are you sure you want to delete this field?'));

				$nolinkf = $GLOBALS['egw']->link('/index.php',"menuaction=addressbook.uifields.index&field_id=$field_id&start=$start&query=$query&sort=$sort");
				$nolink = '<a href="' . $nolinkf . '">' . lang('No') . '</a>';
				$GLOBALS['egw']->template->set_var('no',$nolink);

				$yeslinkf = $GLOBALS['egw']->link('/index.php','menuaction=addressbook.uifieldsdelete&field_id=' . $field_id . '&confirm=True');
				$yeslinkf = '<form method="POST" name="yesbutton" action="' . $GLOBALS['egw']->link('/index.php','menuaction=addressbook.uifields.delete') . '">'
					. $hidden_vars
					. '<input type="hidden" name="field_id"  value="' . $field_id . '">'
					. '<input type="hidden" name="confirm"   value="True">'
					. '<input type="submit" name="yesbutton" value="Yes">'
					. '</form><script>document.yesbutton.yesbutton.focus()</script>';

				$yeslink = '<a href="' . $yeslinkf . '">' . lang('Yes') . '</a>';
				$yeslink = $yeslinkf;
				$GLOBALS['egw']->template->set_var('yes',$yeslink);

				$GLOBALS['egw']->template->pparse('out','field_delete');
			}
		}

		function read_custom_fields($start=0,$limit=5,$query='')
		{
			$i = 0;
			$fields = array();

			$this->config->read_repository();

			while(list($name,$descr) = @each($this->config->config_data['custom_fields']))
			{
				/*
				if($start < $i)
				{
					continue;
				}
				*/

				$test = @strtolower($name);
				//if($query && !strstr($test,strtolower($query)))
				if($query && ($query != $test))
				{
				}
				else
				{
					$fields[$i]['name'] = $name;
					$fields[$i]['title'] = $descr;
					$fields[$i]['id'] = $i;

					/*
					if($i >= $limit)
					{
						break;
					}
					*/
					$i++;
				}
			}
			switch($sort)
			{
				case 'DESC';
					krsort($fields);
					break;
				case 'ASC':
				default:
					ksort($fields);
			}
			@reset($fields);

			return $fields;
		}

		function save_custom_field($old='',$new='')
		{
			$this->config->read_repository();

			if(!is_array($this->config->config_data['custom_fields']))
			{
				$this->config->config_data['custom_fields'] = array();
			}

			if($old)
			{
				unset($this->config->config_data['custom_fields'][$old]);
			}
			if($new)
			{
				$tmp = strtolower(str_replace(' ','_',$new));
				$this->config->config_data['custom_fields'][$tmp] = $new;
			}

			$this->config->save_repository();
		}
	}
?>
