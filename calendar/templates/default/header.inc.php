<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
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
		return '<a href="'.$link.'"><img src="'.$GLOBALS['phpgw']->common->image('calendar',$image).'" alt="'.$alt.'" border="0"></a>';
	}

	$refer = explode('.',$GLOBALS['HTTP_GET_VARS']['menuaction']);
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

	$today = date('Ymd',time());

	$col_width = 12;

	add_col($tpl,'  <td width="2%">&nbsp;</td>');

	add_col($tpl,'  <td width="2%">'.add_image_ahref($this->page('day','&date='.$today),'today.gif',lang('Today')).'</td>');

	add_col($tpl,'  <td width="2%" align="left">'.add_image_ahref($this->page('week','&date='.$today),'week.gif',lang('This week')).'</td>');

	add_col($tpl,'  <td width="2%" align="left">'.add_image_ahref($this->page('month','&date='.$today),'month.gif',lang('This month')).'</td>');

	add_col($tpl,'  <td width="2%" align="left">'.add_image_ahref($this->page('year','&date='.$today),'year.gif',lang('This Year')).'</td>');

	if(floor(phpversion()) >= 4)
	{
		add_col($tpl,'  <td width="2%" align="left">'.add_image_ahref($this->page('planner','&date='.$today),'planner.gif',lang('Planner')).'</td>');
		$col_width += 2;
	}

	add_col($tpl,'  <td width="2%" align="left">'.add_image_ahref($this->page('matrixselect'),'view.gif',lang('Daily Matrix View')).'</td>');

	add_col($tpl,'  <td width="'.(100 - $col_width).'%" align="left"'.(floor(phpversion()) < 4?' colspan="2"':'').'>&nbsp;</td>');

	$tpl->parse('row','head_table',True);

	$tpl->set_var('header_column','');
	$tpl->set_var('cols',$cols);

	$remainder = 72;

	$hidden_vars = '<input type="hidden" name="from" value="'.$GLOBALS['HTTP_GET_VARS']['menuaction'].'">'."\n";
	if(isset($GLOBALS['HTTP_GET_VARS']['cal_id']) && $GLOBALS['HTTP_GET_VARS']['cal_id'] != 0)
	{
		$hidden_vars .= '    <input type="hidden" name="cal_id" value="'.$GLOBALS['HTTP_GET_VARS']['cal_id'].'">'."\n";
	}
	if(isset($GLOBALS['HTTP_POST_VARS']['keywords']) && $GLOBALS['HTTP_POST_VARS']['keywords'])
	{
		$hidden_vars .= '    <input type="hidden" name="keywords" value="'.$GLOBALS['HTTP_POST_VARS']['keywords'].'">'."\n";
	}
	if(isset($GLOBALS['HTTP_POST_VARS']['matrixtype']) && $GLOBALS['HTTP_POST_VARS']['matrixtype'])
	{
		$hidden_vars .= '    <input type="hidden" name="matrixtype" value="'.$GLOBALS['HTTP_POST_VARS']['matrixtype'].'">'."\n";
	}
	if(isset($GLOBALS['HTTP_POST_VARS']['participants']) && $GLOBALS['HTTP_POST_VARS']['participants'])
	{
		for ($i=0;$i<count($GLOBALS['HTTP_POST_VARS']['participants']);$i++)
		{
			$hidden_vars .= '    <input type="hidden" name="participants[]" value="'.$GLOBALS['HTTP_POST_VARS']['participants'][$i].'">'."\n";
		}
	}
	if($this->debug) { echo 'Cat ID = ('.$this->bo->cat_id.")<br>\n"; }

	$var = Array(
		'form_width' => '28',
		'form_link'	=> $this->page($referrer),
		'form_name'	=> 'cat_id',
		'title'	=> lang('Category'),
		'hidden_vars'	=> $hidden_vars,
		'form_options'	=> '<option value="0">All</option>'.$this->cat->formated_list('select','all',$this->bo->cat_id,'True'),
		'button_value'	=> lang('Go!')
	);
	$tpl->set_var($var);
	$tpl->set_var('str',$tpl->fp('out','form_button_dropdown'));
	$tpl->parse('header_column','head_col',True);

	if($this->bo->check_perms(PHPGW_ACL_PRIVATE))
	{
		$remainder -= 28;
		$hidden_vars = '<input type="hidden" name="from" value="'.$GLOBALS['HTTP_GET_VARS']['menuaction'].'">'."\n";
		if(isset($GLOBALS['HTTP_GET_VARS']['cal_id']) && $GLOBALS['HTTP_GET_VARS']['cal_id'] != 0)
		{
			$hidden_vars .= '    <input type="hidden" name="cal_id" value="'.$GLOBALS['HTTP_GET_VARS']['cal_id'].'">'."\n";
		}
		if(isset($GLOBALS['HTTP_POST_VARS']['keywords']) && $GLOBALS['HTTP_POST_VARS']['keywords'])
		{
			$hidden_vars .= '    <input type="hidden" name="keywords" value="'.$GLOBALS['HTTP_POST_VARS']['keywords'].'">'."\n";
		}
		if(isset($GLOBALS['HTTP_POST_VARS']['matrixtype']) && $GLOBALS['HTTP_POST_VARS']['matrixtype'])
		{
			$hidden_vars .= '    <input type="hidden" name="matrixtype" value="'.$GLOBALS['HTTP_POST_VARS']['matrixtype'].'">'."\n";
		}
		if(isset($GLOBALS['HTTP_POST_VARS']['participants']) && $GLOBALS['HTTP_POST_VARS']['participants'])
		{
			for ($i=0;$i<count($GLOBALS['HTTP_POST_VARS']['participants']);$i++)
			{
				$hidden_vars .= '    <input type="hidden" name="participants[]" value="'.$GLOBALS['HTTP_POST_VARS']['participants'][$i].'">'."\n";
			}
		}
		if($this->debug) { echo 'Filter = ('.$this->bo->filter.")<br>\n"; }
		$form_options = '<option value=" all "'.($this->bo->filter==' all '?' selected':'').'>'.lang('All').'</option>'."\n";
		$form_options .= '     <option value=" private "'.((!isset($this->bo->filter) || !$this->bo->filter) || $this->bo->filter==' private '?' selected':'').'>'.lang('Private Only').'</option>'."\n";
		
		$var = Array(
			'form_width' => '28',
			'form_link'	=> $this->page($referrer),
			'form_name'	=> 'filter',
			'title'	=> lang('Filter'),
			'hidden_vars'	=> $hidden_vars,
			'form_options'	=> $form_options,
			'button_value'	=> lang('Go!')
		);
		$tpl->set_var($var);
		$tpl->set_var('str',$tpl->fp('out','form_button_dropdown'));
		$tpl->parse('header_column','head_col',True);
	}

	if(count($this->bo->grants) > 0)
	{
		$hidden_vars = '    <input type="hidden" name="from" value="'.$GLOBALS['HTTP_GET_VARS']['menuaction'].'">'."\n";
		if(isset($GLOBALS['HTTP_POST_VARS']['keywords']) && $GLOBALS['HTTP_POST_VARS']['keywords'])
		{
			$hidden_vars .= '    <input type="hidden" name="keywords" value="'.$GLOBALS['HTTP_POST_VARS']['keywords'].'">'."\n";
		}
		if(isset($GLOBALS['HTTP_GET_VARS']['cal_id']) && $GLOBALS['HTTP_GET_VARS']['cal_id'] != 0)
		{
			$hidden_vars .= '    <input type="hidden" name="cal_id" value="'.$GLOBALS['HTTP_GET_VARS']['cal_id'].'">'."\n";
		}
		$form_options = '';
		reset($this->bo->grants);
		while(list($grantor,$temp_rights) = each($this->bo->grants))
		{
			$GLOBALS['phpgw']->accounts->get_account_name($grantor,$lid,$fname,$lname);
			$drop_down[$lname.' '.$fname] = Array(
				'grantor'	=> $grantor,
				'name'		=> $GLOBALS['phpgw']->common->display_fullname($lid,$fname,$lname)
			);
		}
		@reset($drop_down);
		@ksort($drop_down);
		while(list($key,$grant) = each($drop_down))
		{
			$form_options .= '    <option value="'.$grant['grantor'].'"'.($grant['grantor']==$this->bo->owner?' selected':'').'>'.$grant['name'].'</option>'."\n";
      }
		reset($this->bo->grants);
		
		$var = Array(
			'form_width' => $remainder,
			'form_link'	=> $this->page($referrer),
			'form_name'	=> 'owner',
			'title'	=> lang('User'),
			'hidden_vars'	=> $hidden_vars,
			'form_options'	=> $form_options,
			'button_value'	=> lang('Go!')
		);
		$tpl->set_var($var);
		$tpl->set_var('str',$tpl->fp('out','form_button_dropdown'));
		$tpl->parse('header_column','head_col',True);
	}

	$hidden_vars = '    <input type="hidden" name="from" value="'.$GLOBALS['HTTP_GET_VARS']['menuaction'].'">'."\n";
	if(isset($GLOBALS['HTTP_GET_VARS']['date']) && $GLOBALS['HTTP_GET_VARS']['date'])
	{
		$hidden_vars .= '    <input type="hidden" name="date" value="'.$GLOBALS['HTTP_GET_VARS']['date'].'">'."\n";
	}
	$hidden_vars .= '    <input type="hidden" name="month" value="'.$this->bo->month.'">'."\n";
	$hidden_vars .= '    <input type="hidden" name="day" value="'.$this->bo->day.'">'."\n";
	$hidden_vars .= '    <input type="hidden" name="year" value="'.$this->bo->year.'">'."\n";
	if(isset($this->bo->filter) && $this->bo->filter)
	{
		$hidden_vars .= '    <input type="hidden" name="filter" value="'.$this->bo->filter.'">'."\n";
	}
	$hidden_vars .= '    <input name="keywords"'.($GLOBALS['HTTP_POST_VARS']['keywords']?' value="'.$GLOBALS['HTTP_POST_VARS']['keywords'].'"':'').'>';

	$var = Array(
		'action_url_button'	=> $this->page('search'),
		'action_text_button'	=> lang('Search'),
		'action_confirm_button'	=> '',
		'action_extra_field'	=> $hidden_vars
	);
	$tpl->set_var($var);
	$button = $tpl->fp('out','form_button');
	$tpl->set_var('str','<td align="right" valign="bottom">'.$button.'</td>');
	$tpl->parse('header_column','head_col',True);
	$tpl->parse('row','head_table',True);
?>
