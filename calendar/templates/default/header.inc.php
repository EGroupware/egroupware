<?php
  /**************************************************************************\
  * eGroupWare                                                               *
  * http://www.egroupware.org                                                *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	function add_col(&$tpl,$str)
	{
		$tpl->set_var('str',$str);
		$tpl->parse('header_column','head_col',True);
	}

	function add_image_ahref($link,$image,$alt)
	{
		return '<a href="'.$link.'"><img src="'.$GLOBALS['phpgw']->common->image('calendar',$image).'" alt="'.$alt.'" title="'.$alt.'" border="0"></a>';
	}

	$refer = explode('.',$_GET['menuaction']);
	$referrer = $refer[2];

	$templates = Array(
		'head_tpl'	=> 'head.tpl',
		'form_button_dropdown'	=> 'form_button_dropdown.tpl',
		'form_button_script'	=> 'form_button_script.tpl'
	);
	$tpl->set_file($templates);
	$tpl->set_block('head_tpl','head','head');
	$tpl->set_block('head_tpl','head_table','head_table');
	$tpl->set_block('head_tpl','head_col','head_col');
	$tpl->set_block('form_button_script','form_button');

	if(floor(phpversion()) >= 4)
	{
		$tpl->set_var('cols',8);
	}
	else
	{
		$tpl->set_var('cols',7);
	}

	$today = date('Ymd',$GLOBALS['phpgw']->datetime->users_localtime);

	$col_width = 12;

	add_col($tpl,'  <td width="2%">&nbsp;</td>');

	add_col($tpl,'  <td width="2%">'.add_image_ahref($this->page('day','&date='.$today),'today',lang('Today')).'</td>');

	add_col($tpl,'  <td width="2%" align="left">'.add_image_ahref($this->page('week','&date='.$today),'week',lang('This week')).'</td>');

	add_col($tpl,'  <td width="2%" align="left">'.add_image_ahref($this->page('month','&date='.$today),'month',lang('This month')).'</td>');

	add_col($tpl,'  <td width="2%" align="left">'.add_image_ahref($this->page('year','&date='.$today),'year',lang('This Year')).'</td>');

	if(floor(phpversion()) >= 4)
	{
		add_col($tpl,'  <td width="2%" align="left">'.add_image_ahref($this->page('planner','&date='.$today),'planner',lang('Planner')).'</td>');
		$col_width += 2;
	}

	add_col($tpl,'  <td width="2%" align="left">'.add_image_ahref($this->page('matrixselect'),'view',lang('Daily Matrix View')).'</td>');

	add_col($tpl,'  <td width="'.(100 - $col_width).'%" align="left"'.(floor(phpversion()) < 4?' colspan="2"':'').'>&nbsp;</td>');

	$tpl->parse('row','head_table',True);

	$tpl->set_var('header_column','');
	$tpl->set_var('cols',$cols);

	if($referrer!='view')
	{
		$remainder = 72;
		
		$base_hidden_vars = $this->html->input_hidden('from',$_GET['menuaction']);
		if(isset($_GET['cal_id']) && (int) $_GET['cal_id'])
		{
			$base_hidden_vars .= '    '.$this->html->input_hidden('cal_id',(int)$_GET['cal_id']);
		}
		if(isset($_POST['matrixtype']) && ($_POST['matrixtype'] == 'free/busy' || $_POST['matrixtype'] == 'weekly'))
		{
			$base_hidden_vars .= '    '.$this->html->input_hidden('matrixtype',$_POST['matrixtype']);
		}
		if($this->bo->date)
		{
			$base_hidden_vars .= '    '.$this->html->input_hidden('date',$this->bo->date);
		}
		$base_hidden_vars .= '    '.$this->html->input_hidden('month',$this->bo->month);
		$base_hidden_vars .= '    '.$this->html->input_hidden('day',$this->bo->day);
		$base_hidden_vars .= '    '.$this->html->input_hidden('year',$this->bo->year);
		
		if(isset($_POST['participants']) && $_POST['participants'])
		{
			foreach ($_POST['participants'] as $part)
			{
				$base_hidden_vars .= '    '.$this->html->input_hidden('participants[]',
					substr($part,0,2) == 'g_' ? 'g_'.(int)substr($part,2) : (int) $part);
			}
		}
		$base_hidden_vars_no_keywords = $base_hidden_vars;

		if(isset($_POST['keywords']) && $_POST['keywords'])
		{
			$base_hidden_vars .= '    '.$this->html->input_hidden('keywords',$_POST['keywords']);
		}

		$var = Array(
			'form_width' => '28',
			'form_link'	=> $this->page($referrer),
			'form_name'	=> 'cat_id',
			'title'	=> lang('Category'),
			'hidden_vars'	=> $base_hidden_vars,
			'form_options'	=> '<option value="0">'.lang('All').'</option>'.$this->cat->formated_list('select','all',$this->bo->cat_id,'True'),
			'button_value'	=> lang('Go!')
		);
		$tpl->set_var($var);
		$tpl->set_var('str',$tpl->fp('out','form_button_dropdown'));
		$tpl->parse('header_column','head_col',True);

		if($_GET['menuaction'] == 'calendar.uicalendar.planner')
		{
			$remainder -= 28;
			print_debug('Sort By',$this->bo->sortby);

			$form_options = '<option value="user"'.($this->bo->sortby=='user'?' selected':'').'>'.lang('User').'</option>'."\n";
			$form_options .= '     <option value="category"'.((!isset($this->bo->sortby) || !$this->bo->sortby) || $this->bo->sortby=='category'?' selected':'').'>'.lang('Category').'</option>'."\n";
		
			$var = Array(
				'form_width' => '28',
				'form_link'	=> $this->page($referrer),
				'form_name'	=> 'sortby',
				'title'	=> lang('Sort By'),
				'hidden_vars'	=> $base_hidden_vars,
				'form_options'	=> $form_options,
				'button_value'	=> lang('Go!')
			);
			$tpl->set_var($var);
			$tpl->set_var('str',$tpl->fp('out','form_button_dropdown'));
			$tpl->parse('header_column','head_col',True);
		}

		if($this->bo->check_perms(PHPGW_ACL_PRIVATE))
		{
			$remainder -= 28;
			$form_options = '<option value=" all "'.($this->bo->filter==' all '?' selected':'').'>'.lang('All').'</option>'."\n";
			$form_options .= '     <option value=" private "'.((!isset($this->bo->filter) || !$this->bo->filter) || $this->bo->filter==' private '?' selected':'').'>'.lang('Private Only').'</option>'."\n";
		
			$var = Array(
				'form_width' => '28',
				'form_link'	=> $this->page($referrer),
				'form_name'	=> 'filter',
				'title'	=> lang('Filter'),
				'hidden_vars'	=> $base_hidden_vars,
				'form_options'	=> $form_options,
				'button_value'	=> lang('Go!')
			);
			$tpl->set_var($var);
			$tpl->set_var('str',$tpl->fp('out','form_button_dropdown'));
			$tpl->parse('header_column','head_col',True);
		}

		if((!isset($GLOBALS['phpgw_info']['server']['deny_user_grants_access']) || !$GLOBALS['phpgw_info']['server']['deny_user_grants_access']) && count($this->bo->grants) > 0)
		{
			$form_options = '';
			$drop_down = $this->bo->list_cals();
			foreach($drop_down as $key => $grant)
			{
				$form_options .= '    <option value="'.$grant['value'].'"'.($grant['grantor']==$this->bo->owner?' selected':'').'>'.$grant['name'].'</option>'."\n";
			}
		
			$var = Array(
				'form_width' => $remainder,
				'form_link'	=> $this->page($referrer),
				'form_name'	=> 'owner',
				'title'	=> lang('User'),
				'hidden_vars'	=> $base_hidden_vars,
				'form_options'	=> $form_options,
				'button_value'	=> lang('Go!')
			);
			$tpl->set_var($var);
			$tpl->set_var('str',$tpl->fp('out','form_button_dropdown'));
			$tpl->parse('header_column','head_col',True);
		}
	}

	$var = Array(
		'action_url_button'	=> $this->page('search'),
		'action_text_button'	=> lang('Search'),
		'action_confirm_button'	=> '',
		'action_extra_field'	=> $base_hidden_vars_no_keywords . '    '.$this->html->input('keywords',$_POST['keywords'])
	);
	$tpl->set_var($var);
	$button = $tpl->fp('out','form_button');
	$tpl->set_var('str','<td align="right" valign="bottom">'.$button.'</td>');
	$tpl->parse('header_column','head_col',True);
	$tpl->parse('row','head_table',True);
?>
