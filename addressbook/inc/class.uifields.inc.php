<?php
  /**************************************************************************\
  * phpGroupWare - Addressbook                                               *
  * http://www.phpgroupware.org                                              *
  * Written by Bettina Gille [ceb@phpgroupware.org]                          *
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

		function uifields()
		{
			$GLOBALS['phpgw']->template = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
			$GLOBALS['phpgw']->nextmatchs = CreateObject('phpgwapi.nextmatchs');
		}

		function index()
		{
			if(!$GLOBALS['phpgw']->acl->check('run',1,'admin'))
			{
				$GLOBALS['phpgw']->common->phpgw_header();
				echo parse_navbar();
				echo lang('access not permitted');
				$GLOBALS['phpgw']->common->phpgw_footer();
				$GLOBALS['phpgw']->common->phpgw_exit();
			}

			$GLOBALS['phpgw']->template->set_file(array(
				'field_list_t' => 'listfields.tpl',
				'field_list'   => 'listfields.tpl'
			));
			$GLOBALS['phpgw']->template->set_block('field_list_t','field_list','list');

			$field  = $GLOBALS['HTTP_POST_VARS']['field'];
			$start  = $GLOBALS['HTTP_POST_VARS']['start'];
			$query  = $GLOBALS['HTTP_POST_VARS']['query'];
			$sort   = $GLOBALS['HTTP_POST_VARS']['sort'];
			$order  = $GLOBALS['HTTP_POST_VARS']['order'];
			$filter = $GLOBALS['HTTP_POST_VARS']['filter'];

			$common_hidden_vars =
				'<input type="hidden" name="sort"   value="' . $sort   . '">' . "\n"
				. '<input type="hidden" name="order"  value="' . $order  . '">' . "\n"
				. '<input type="hidden" name="query"  value="' . $query  . '">' . "\n"
				. '<input type="hidden" name="start"  value="' . $start  . '">' . "\n"
				. '<input type="hidden" name="filter" value="' . $filter . '">' . "\n";

			$GLOBALS['phpgw']->template->set_var('lang_action',lang('Custom Fields'));
			$GLOBALS['phpgw']->template->set_var('add_action',$GLOBALS['phpgw']->link('/index.php','menuaction=addressbook.uifields.add'));
			$GLOBALS['phpgw']->template->set_var('lang_add',lang('Add'));
			$GLOBALS['phpgw']->template->set_var('title_fields',lang('addressbook').' - '.lang('Custom Fields'));
			$GLOBALS['phpgw']->template->set_var('lang_search',lang('Search'));
			$GLOBALS['phpgw']->template->set_var('actionurl',$GLOBALS['phpgw']->link('/index.php','menuaction=addressbook.uifields.index'));
			$GLOBALS['phpgw']->template->set_var('lang_done',lang('Done'));
			$GLOBALS['phpgw']->template->set_var('doneurl',$GLOBALS['phpgw']->link('/admin/index.php'));

			if (!$start)
			{
				$start = 0;
			}

			if (!$sort)
			{
				$sort = 'ASC';
			}

			$fields = $this->read_custom_fields($start,$limit,$query,$sort,$order);
			$total_records = count($fields);

			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();

			$GLOBALS['phpgw']->template->set_var('left',$GLOBALS['phpgw']->nextmatchs->left('/index.php',$start,$total_records,'menuaction=addressbook.uifields.index'));
			$GLOBALS['phpgw']->template->set_var('right',$GLOBALS['phpgw']->nextmatchs->right('/index.php',$start,$total_records,'menuaction=addressbook.uifields.index'));

			$GLOBALS['phpgw']->template->set_var('lang_showing',$GLOBALS['phpgw']->nextmatchs->show_hits($total_records,$start));

			$GLOBALS['phpgw']->template->set_var('th_bg',$GLOBALS['phpgw_info']['theme']['th_bg']);
			$GLOBALS['phpgw']->template->set_var('sort_field',$GLOBALS['phpgw']->nextmatchs->show_sort_order($sort,'name',$order,'/index.php',lang('Name')),'menuaction=addressbook.uifields.index');
			$GLOBALS['phpgw']->template->set_var('lang_edit',lang('Edit'));
			$GLOBALS['phpgw']->template->set_var('lang_delete',lang('Delete'));

			for ($i=0;$i<count($fields);$i++)
			{
				$tr_color = $GLOBALS['phpgw']->nextmatchs->alternate_row_color($tr_color);
				$GLOBALS['phpgw']->template->set_var(tr_color,$tr_color);

				$field = $fields[$i]['name'];

				$GLOBALS['phpgw']->template->set_var('cfield',$field);

				$GLOBALS['phpgw']->template->set_var('edit',$GLOBALS['phpgw']->link('/index.php',"menuaction=addressbook.uifields.edit&field=$field&start=$start&query=$query&sort=$sort&order=$order&filter=$filter"));
				$GLOBALS['phpgw']->template->set_var('lang_edit_entry',lang('Edit'));

				$GLOBALS['phpgw']->template->set_var('delete',$GLOBALS['phpgw']->link('/index.php',"menuaction=addressbook.uifields.delete&field=$field&start=$start&query=$query&sort=$sort&order=$order&filter=$filter"));
				$GLOBALS['phpgw']->template->set_var('lang_delete_entry',lang('Delete'));
				$GLOBALS['phpgw']->template->parse('list','field_list',True);
			}

			$GLOBALS['phpgw']->template->parse('out','field_list_t',True);
			$GLOBALS['phpgw']->template->p('out');

			$GLOBALS['phpgw']->common->phpgw_footer();
		}

		function add()
		{
			if(!$GLOBALS['phpgw']->acl->check('run',1,'admin'))
			{
				$GLOBALS['phpgw']->common->phpgw_header();
				echo parse_navbar();
				echo lang('access not permitted');
				$GLOBALS['phpgw']->common->phpgw_footer();
				$GLOBALS['phpgw']->common->phpgw_exit();
			}

			$field      = $GLOBALS['HTTP_POST_VARS']['field'];
			$field_name = $GLOBALS['HTTP_POST_VARS']['field_name'];
			$start      = $GLOBALS['HTTP_POST_VARS']['start'];
			$query      = $GLOBALS['HTTP_POST_VARS']['query'];
			$sort       = $GLOBALS['HTTP_POST_VARS']['sort'];

			$GLOBALS['phpgw']->template->set_file(array('form' => 'field_form.tpl'));
			$GLOBALS['phpgw']->template->set_block('form','add','addhandle');
			$GLOBALS['phpgw']->template->set_block('form','edit','edithandle');

			if ($GLOBALS['HTTP_POST_VARS']['submit'])
			{
				$errorcount = 0;

				if (!$field_name)
				{
					$error[$errorcount++] = lang('Please enter a name for that field !');
				}

				$fields = $this->read_custom_fields($start,$limit,$field_name);
				if ($fields[0]['name'])
				{
					$error[$errorcount++] = lang('That field name has been used already !');
				}

				if (! $error)
				{
					$field_name = addslashes($field_name);
					$this->save_custom_field($field,$field_name);
				}
			}

			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();

			if ($errorcount)
			{
				$GLOBALS['phpgw']->template->set_var('message',$GLOBALS['phpgw']->common->error_list($error));
			}
			if (($submit) && (! $error) && (! $errorcount))
			{
				$GLOBALS['phpgw']->template->set_var('message',lang('Field x has been added !', $field_name));
			}
			if ((! $submit) && (! $error) && (! $errorcount))
			{
				$GLOBALS['phpgw']->template->set_var('message','');
			}

			$GLOBALS['phpgw']->template->set_var('title_fields',lang('Add Custom Field'));
			$GLOBALS['phpgw']->template->set_var('actionurl',$GLOBALS['phpgw']->link('/index.php','menuaction=addressbook.uifields.add'));
			$GLOBALS['phpgw']->template->set_var('doneurl',$GLOBALS['phpgw']->link('/index.php','menuaction=addressbook.uifields.index'));
			$GLOBALS['phpgw']->template->set_var('hidden_vars','<input type="hidden" name="field" value="' . $field . '">');

			$GLOBALS['phpgw']->template->set_var('lang_name',lang('Field name'));

			$GLOBALS['phpgw']->template->set_var('lang_add',lang('Add'));
			$GLOBALS['phpgw']->template->set_var('lang_reset',lang('Clear Form'));
			$GLOBALS['phpgw']->template->set_var('lang_done',lang('Done'));

			$GLOBALS['phpgw']->template->set_var('field_name',$field_name);

			$GLOBALS['phpgw']->template->set_var('edithandle','');
			$GLOBALS['phpgw']->template->set_var('addhandle','');
			$GLOBALS['phpgw']->template->pparse('out','form');
			$GLOBALS['phpgw']->template->pparse('addhandle','add');
		}

		function edit()
		{
			if(!$GLOBALS['phpgw']->acl->check('run',1,'admin'))
			{
				$GLOBALS['phpgw']->common->phpgw_header();
				echo parse_navbar();
				echo lang('access not permitted');
				$GLOBALS['phpgw']->common->phpgw_footer();
				$GLOBALS['phpgw']->common->phpgw_exit();

			}

			$field      = $GLOBALS['HTTP_POST_VARS']['field'] ? $GLOBALS['HTTP_POST_VARS']['field'] : $GLOBALS['HTTP_GET_VARS']['field'];
			$field_name = $GLOBALS['HTTP_POST_VARS']['field_name'];
			$start      = $GLOBALS['HTTP_POST_VARS']['start'] ? $GLOBALS['HTTP_POST_VARS']['start'] : $GLOBALS['HTTP_GET_VARS']['start'];
			$query      = $GLOBALS['HTTP_POST_VARS']['query'] ? $GLOBALS['HTTP_POST_VARS']['query'] : $GLOBALS['HTTP_GET_VARS']['query'];
			$sort       = $GLOBALS['HTTP_POST_VARS']['sort']  ? $GLOBALS['HTTP_POST_VARS']['sort']  : $GLOBALS['HTTP_GET_VARS']['sort'];
			$submit     = $GLOBALS['HTTP_POST_VARS']['submit'];

			if (!$field)
			{
				Header('Location: ' . $GLOBALS['phpgw']->link('/index.php',"menuaction=addressbook.uifields.index&sort=$sort&query=$query&start=$start"));
			}

			$GLOBALS['phpgw']->template->set_file(array('form' => 'field_form.tpl'));
			$GLOBALS['phpgw']->template->set_block('form','add','addhandle');
			$GLOBALS['phpgw']->template->set_block('form','edit','edithandle');

			$hidden_vars = '<input type="hidden" name="sort" value="' . $sort . '">' . "\n"
				. '<input type="hidden" name="query" value="' . $query . '">' . "\n"
				. '<input type="hidden" name="start" value="' . $start . '">' . "\n"
				. '<input type="hidden" name="field" value="' . $field . '">' . "\n";

			if ($submit)
			{
				$errorcount = 0;
				if (!$field_name) { $error[$errorcount++] = lang('Please enter a name for that field!'); }

				$field_name = addslashes($field_name);

				if (! $error)
				{
					$this->save_custom_field($field,$field_name);
				}
			}

			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();

			if ($errorcount)
			{
				$GLOBALS['phpgw']->template->set_var('message',$GLOBALS['phpgw']->common->error_list($error));
			}
			if (($submit) && (! $error) && (! $errorcount))
			{
				$GLOBALS['phpgw']->template->set_var('message',lang('Field x has been updated !', $field_name));
			}
			if ((! $submit) && (! $error) && (! $errorcount))
			{
				$GLOBALS['phpgw']->template->set_var('message','');
			}

			if ($submit)
			{
				$field = $field_name;
			}
			else
			{
				$fields = $this->read_custom_fields($start,$limit,$field);
				$field  = $GLOBALS['phpgw']->strip_html($fields[0]['name']);
			}

			$GLOBALS['phpgw']->template->set_var('title_fields',lang('Edit Custom Field'));
			$GLOBALS['phpgw']->template->set_var('actionurl',$GLOBALS['phpgw']->link('/index.php','menuaction=addressbook.uifields.edit'));
			$GLOBALS['phpgw']->template->set_var('deleteurl',$GLOBALS['phpgw']->link('/index.php',"menuaction=addressbook.uifields.delete&field=$field&start=$start&query=$query&sort=$sort"));
			$GLOBALS['phpgw']->template->set_var('doneurl',$GLOBALS['phpgw']->link('/index.php',"menuaction=addressbook.uifields.index&start=$start&query=$query&sort=$sort"));

			$GLOBALS['phpgw']->template->set_var('hidden_vars',$hidden_vars);
			$GLOBALS['phpgw']->template->set_var('lang_name',lang('Field name'));

			$GLOBALS['phpgw']->template->set_var('lang_done',lang('Done'));
			$GLOBALS['phpgw']->template->set_var('lang_edit',lang('Edit'));
			$GLOBALS['phpgw']->template->set_var('lang_delete',lang('Delete'));

			$GLOBALS['phpgw']->template->set_var('field_name',$field);

			$GLOBALS['phpgw']->template->set_var('edithandle','');
			$GLOBALS['phpgw']->template->set_var('addhandle','');

			$GLOBALS['phpgw']->template->pparse('out','form');
			$GLOBALS['phpgw']->template->pparse('edithandle','edit');
		}

		function delete()
		{
			$field    = $GLOBALS['HTTP_POST_VARS']['field'] ? $GLOBALS['HTTP_POST_VARS']['field'] : $GLOBALS['HTTP_GET_VARS']['field'];
			$field_id = $GLOBALS['HTTP_POST_VARS']['field_id'] ? $GLOBALS['HTTP_POST_VARS']['field_id'] : $GLOBALS['HTTP_GET_VARS']['field_id'];
			$start    = $GLOBALS['HTTP_POST_VARS']['start'] ? $GLOBALS['HTTP_POST_VARS']['start'] : $GLOBALS['HTTP_GET_VARS']['start'];
			$query    = $GLOBALS['HTTP_POST_VARS']['query'] ? $GLOBALS['HTTP_POST_VARS']['query'] : $GLOBALS['HTTP_GET_VARS']['query'];
			$sort     = $GLOBALS['HTTP_POST_VARS']['sort']  ? $GLOBALS['HTTP_POST_VARS']['sort']  : $GLOBALS['HTTP_GET_VARS']['sort'];

			if (!$field)
			{
				Header('Location: ' . $GLOBALS['phpgw']->link('/index.php','menuaction=addressbook.uifields.index'));
			}

			if ($GLOBALS['HTTP_POST_VARS']['confirm'])
			{
				$this->save_custom_field($field);
				Header('Location: ' . $GLOBALS['phpgw']->link('/index.php',"menuaction=addressbook.uifields.index&start=$start&query=$query&sort=$sort"));
			}
			else
			{
				$GLOBALS['phpgw']->common->phpgw_header();
				echo parse_navbar();

				$hidden_vars = '<input type="hidden" name="sort" value="' . $sort . '">' . "\n"
					. '<input type="hidden" name="order" value="' . $order .'">' . "\n"
					. '<input type="hidden" name="query" value="' . $query .'">' . "\n"
					. '<input type="hidden" name="start" value="' . $start .'">' . "\n"
					. '<input type="hidden" name="field" value="' . $field .'">' . "\n";

				$GLOBALS['phpgw']->template->set_file(array('field_delete' => 'delete_common.tpl'));
				$GLOBALS['phpgw']->template->set_var('messages',lang('Are you sure you want to delete this field?'));

				$nolinkf = $GLOBALS['phpgw']->link('/index.php',"menuaction=addressbook.uifields.index&field_id=$field_id&start=$start&query=$query&sort=$sort");
				$nolink = '<a href="' . $nolinkf . '">' . lang('No') . '</a>';
				$GLOBALS['phpgw']->template->set_var('no',$nolink);

				$yeslinkf = $GLOBALS['phpgw']->link('/index.php','menuaction=addressbook.uifieldsdelete&field_id=' . $field_id . '&confirm=True');
				$yeslinkf = '<form method="POST" name="yesbutton" action="' . $GLOBALS['phpgw']->link('/index.php','menuaction=addressbook.uifields.delete') . '\">'
					. $hidden_vars
					. '<input type="hidden" name="field_id"  value="' . $field_id . '">'
					. '<input type="hidden" name="confirm"   value="True">'
					. '<input type="submit" name="yesbutton" value="Yes">'
					. '</form><script>document.yesbutton.yesbutton.focus()</script>';

				$yeslink = '<a href="' . $yeslinkf . '">' . lang('Yes') . '</a>';
				$yeslink = $yeslinkf;
				$GLOBALS['phpgw']->template->set_var('yes',$yeslink);

				$GLOBALS['phpgw']->template->pparse('out','field_delete');
			}
		}

		function read_custom_fields()
		{
			$i = 0; $j = 0;
			$fields = array();
			@reset($GLOBALS['phpgw_info']['user']['preferences']['addressbook']);
			while (list($col,$descr) = @each($GLOBALS['phpgw_info']['user']['preferences']['addressbook']))
			{
				if ( substr($col,0,6) == 'extra_' )
				{
					$fields[$j]['name'] = ereg_replace('extra_','',$col);
					$fields[$j]['name'] = ereg_replace(' ','_',$fields[$j]['name']);
					$fields[$j]['id'] = $i;

					if ($query && ($fields[$j]['name'] != $query))
					{
						unset($fields[$j]['name']);
						unset($fields[$j]['id']);
					}
					else
					{
						/* echo "<br>".$j.": '".$fields[$j]['name']."'"; */
						$j++;
					}
				}
				$i++;
			}
			@reset($fields);
			return $fields;
		}

		function save_custom_field($old='',$new='')
		{
			$GLOBALS['phpgw']->preferences->read_repository($GLOBALS['phpgw_info']['user']['account_id']);
			if ($old)
			{
				$GLOBALS['phpgw']->preferences->delete("addressbook","extra_".$old);
			}
			if($new)
			{
				$GLOBALS['phpgw']->preferences->add("addressbook","extra_".$new);
			}
			$GLOBALS['phpgw']->preferences->save_repository(1);
		}
	}
?>
