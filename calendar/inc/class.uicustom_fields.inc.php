<?php
  /**************************************************************************\
  * phpGroupWare - Calendar - Custom fields and sorting                      *
  * http://www.phpgroupware.org                                              *
  * Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */

	require_once(PHPGW_INCLUDE_ROOT.'/calendar/inc/class.bocustom_fields.inc.php');
	$GLOBALS['phpgw_info']['flags']['included_classes']['bocustom_fields'] = True; // for 0.9.14

	class uicustom_fields extends bocustom_fields
	{
		var $public_functions = array(
			'index' => True,
			'submited'  => True
		);

		function uicustom_fields()
		{
			$this->bocustom_fields();	// call constructor of extended class

			$this->tpl = $GLOBALS['phpgw']->template;
			if (!is_object($GLOBALS['phpgw']->nextmatchs))
			{
				$GLOBALS['phpgw']->nextmatchs = CreateObject('phpgwapi.nextmatchs');
			}
			$this->html = CreateObject('calendar.html');
		}

		function index($error='')
		{
			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw_info']['flags']['app_header'] = $GLOBALS['phpgw_info']['apps']['calendar']['title'].' - '.lang('Custom fields and sorting');
			$GLOBALS['phpgw']->common->phpgw_header();

			$this->tpl = $GLOBALS['phpgw']->template;

			$this->tpl->set_unknowns('remove');
			$this->tpl->set_file(array(
				'custom_fields_tpl'	=> 'custom_fields.tpl'
			));
			$this->tpl->set_block('custom_fields_tpl','custom_fields','custom_fields');
			$this->tpl->set_block('custom_fields_tpl','row','row');

			$n = 0;
			foreach($this->fields as $field => $data)
			{
				$data['order'] = ($n += 10);
				if (isset($this->stock_fields[$field]))
				{
					$this->set_row($data,$field);
				}
				else
				{
					$this->set_row($data,$field,'delete','Delete');
				}
			}
			$this->tpl->set_var(array(
				'hidden_vars'  => '',
				'lang_error'   => $error,
				'lang_name'    => lang('Name'),
				'lang_length'  => lang('Length<br>(<= 255)'),
				'lang_shown'   => lang('Length shown<br>(emtpy for full length)'),
				'lang_order'   => lang('Order'),
				'lang_title'   => lang('Title-row'),
				'lang_disabled'=> lang('Disabled'),
				'action_url'   => $GLOBALS['phpgw']->link('/index.php','menuaction=calendar.uicustom_fields.submited'),
				'save_button'  => $this->html->submit_button('save','Save'),
				'cancel_button'=> $this->html->submit_button('cancel','Cancel'),
			));

			$this->set_row(array('order' => $n+10),'***new***','add','Add');

			$this->tpl->pfp('out','custom_fields');
		}

		function set_row($values,$id='',$name='',$label='')
		{
			if ($id !== '')
			{
				$id = '['.htmlspecialchars($id).']';
			}
			$this->tpl->set_var(array(
				'name'    => $values['label'] ? lang($values['label']) : $this->html->input('name'.$id,$values['name'],'','SIZE="40" MAXLENGTH="40"'),
				'length'  => $values['label'] ? '&nbsp' : $this->html->input('length'.$id,$values['length'],'','SIZE="3"'),
				'shown'   => $values['label'] ? '&nbsp' : $this->html->input('shown'.$id,$values['shown'],'','SIZE="3"'),
				'order'   => $this->html->input('order'.$id,$values['order'],'','SIZE="3"'),
				'title'   => $this->html->checkbox('title'.$id,$values['title']),
				'disabled'=> $this->html->checkbox('disabled'.$id,$values['disabled']),
				'button'  => $name ? $this->html->submit_button($name.$id,$label) : '&nbsp'
			));
			if ($name !== 'add')
			{
				$this->tpl->set_var('tr_color',$values['title'] ? $GLOBALS['phpgw_info']['theme']['th_bg'] : $GLOBALS['phpgw']->nextmatchs->alternate_row_color());
				$this->tpl->parse('rows','row',True);
			}
		}

		function submited()
		{
			if ($_POST['cancel'])
			{
				$GLOBALS['phpgw']->redirect_link('/admin/');
			}
			//echo "<pre>"; print_r($_POST); echo "</pre>";

			foreach ($_POST['order'] as $field => $order)
			{
				if (isset($_POST['delete'][$field]) || $field == '***new***')
				{
					continue;
				}
				while(isset($ordered[$order]))
				{
					++$order;
				}
				$ordered[$order] = array(
					'field'     => $field,
					'name'      => stripslashes($_POST['name'][$field]),
					'length'    => intval($_POST['length'][$field]),
					'shown'     => intval($_POST['shown'][$field]),
					'title'     => !!$_POST['title'][$field],
					'disabled'  => !!$_POST['disabled'][$field]
				);
				if (isset($this->stock_fields[$field]))
				{
					$ordered[$order]['name']  = $this->fields[$field]['name'];
					$ordered[$order]['label'] = $this->fields[$field]['label'];
				}
			}
			if (isset($_POST['add']) || strlen($_POST['name']['***new***']))
			{
				$name = stripslashes($_POST['name']['***new***']);

				if (!strlen($name) || array_search($name,$_POST['name']) != '***new***')
				{
					$error .= lang('New name must not exist and not be empty!!!');
				}
				else
				{
					$order = $_POST['order']['***new***'];
					while(isset($_POST['order'][$order]))
					{
						++$order;
					}
					$ordered[$order] = array(
						'field'     => '#'.$name,
						'name'      => $name,
						'length'    => intval($_POST['length']['***new***']),
						'shown'     => intval($_POST['shown']['***new***']),
						'title'     => !!$_POST['title']['***new***'],
						'disabled'  => !!$_POST['disabled']['***new***']
					);
				}
			}
			//echo "<pre>"; print_r($ordered); echo "</pre>\n";
			ksort($ordered,SORT_NUMERIC);

			$this->fields = array();
			foreach($ordered as $order => $data)
			{
				if ($data['length'] > 255)
				{
					$data['length'] = 255;
				}
				if ($data['length'] <= 0)
				{
					unset($data['length']);
				}
				if ($data['shown'] >= $data['length'] || $data['shown'] <= 0)
				{
					unset($data['shown']);
				}
				if (!$data['title'])
				{
					unset($data['title']);
				}
				if (!$data['disabled'])
				{
					unset($data['disabled']);
				}
				$field = $data['field'];
				unset($data['field']);
				$this->fields[$field] = $data;
			}
			if (!$error && isset($_POST['save']))
			{
				$this->save();
				$GLOBALS['phpgw']->redirect_link('/admin/');
			}
			$this->index($error);
		}
	}
?>
