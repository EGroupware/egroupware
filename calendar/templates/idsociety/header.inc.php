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

	function add_col($str,&$tpl)
	{
		$tpl->set_var('str',$str);
		$tpl->parse('header_column','head_col',True);
	}

	function add_image_ahref($link,$image,$alt)
	{
		return '<a href="'.$link.'"><img src="'.PHPGW_IMAGES.'/'.$image.'" alt="'.$alt.'" border="0"></a>';
	}

	$tpl = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('calendar'));
	$tpl->set_unknowns('remove');
	$templates = Array(
		'head_tpl'	=> 'head.tpl',
		'form_button_dropdown'	=> 'form_button_dropdown.tpl',
		'form_button_script'	=> 'form_button_script.tpl'
	);
	$tpl->set_file($templates);
	$tpl->set_block('head_tpl','head','head');
	$tpl->set_block('head_tpl','head_col','head_col');
	$tpl->set_block('form_button_script','form_button');
	$tpl->set_var('cols',$cols);

	$str = '  <td width="2%">&nbsp;</td>';
	add_col($str,$tpl);

	$link = $phpgw->link('/calendar/day.php','day='.$phpgw->calendar->today['day'].'&month='.$phpgw->calendar->today['month'].'&year='.$phpgw->calendar->today['year'].'&owner='.$owner);
	$str = '  <td width="2%">'.add_image_ahref($link,'today.gif',lang('Today')).'</td>';
	add_col($str,$tpl);

	$link = $phpgw->link('/calendar/week.php','day='.$phpgw->calendar->today['day'].'&month='.$phpgw->calendar->today['month'].'&year='.$phpgw->calendar->today['year'].'&owner='.$owner);
	$str = '  <td width="2%" align="left">'.add_image_ahref($link,'week.gif',lang('This week')).'</td>';
	add_col($str,$tpl);

	$link = $phpgw->link('/calendar/month.php','day='.$phpgw->calendar->today['day'].'&month='.$phpgw->calendar->today['month'].'&year='.$phpgw->calendar->today['year'].'&owner='.$owner);
	$str = '  <td width="2%" align="left">'.add_image_ahref($link,'month.gif',lang('This month')).'</td>';
	add_col($str,$tpl);

	$link = $phpgw->link('/calendar/year.php','day='.$phpgw->calendar->today['day'].'&month='.$phpgw->calendar->today['month'].'&year='.$phpgw->calendar->today['year'].'&owner='.$owner);
	$str = '  <td width="2%" align="left">'.add_image_ahref($link,'year.gif',lang('This Year')).'</td>';
	add_col($str,$tpl);

	$link = $phpgw->link('/calendar/matrixselect.php','day='.$phpgw->calendar->today['day'].'&month='.$phpgw->calendar->today['month'].'&year='.$phpgw->calendar->today['year'].'&owner='.$owner);
	$str = '  <td width="2%" align="left">'.add_image_ahref($link,'view.gif',lang('Daily Matrix View')).'</td>';
	add_col($str,$tpl);

	$base_url = '/calendar/'.basename($SCRIPT_FILENAME);
	$remainder = 65;
	if($phpgw->calendar->check_perms(PHPGW_ACL_PRIVATE) == True)
	{
		$remainder -= 30;
		$hidden_vars = '<input type="hidden" name="from" value="'.$base_url.'">'."\n";
		if(isset($date) && $date)
		{
			$hidden_vars .= '    <input type="hidden" name="date" value="'.$date.'">'."\n";
		}
		$hidden_vars .= '    <input type="hidden" name="month" value="'.$thismonth.'">'."\n";
		$hidden_vars .= '    <input type="hidden" name="day" value="'.$thisday.'">'."\n";
		$hidden_vars .= '    <input type="hidden" name="year" value="'.$thisyear.'">'."\n";
		if(isset($keywords) && $keywords)
		{
			$hidden_vars .= '    <input type="hidden" name="keywords" value="'.$keywords.'">'."\n";
		}
		if(isset($matrixtype) && $matrixtype)
		{
			$hidden_vars .= '    <input type="hidden" name="matrixtype" value="'.$matrixtype.'">'."\n";
		}
		if(isset($participants) && $participants)
		{
			for ($i=0;$i<count($participants);$i++)
			{
				$hidden_vars .= '    <input type="hidden" name="participants[]" value="'.$participants[$i].'">'."\n";
			}
		}
		$form_options = '<option value="all"'.($filter=='all'?' selected':'').'>'.lang('All').'</option>'."\n";
		$form_options .= '     <option value="private"'.((!isset($filter) || !$filter) || $filter=='private'?' selected':'').'>'.lang('Private Only').'</option>'."\n";
		
		$var = Array(
			'form_width' => '30',
			'form_link'	=> $phpgw->link($base_url,'owner='.$owner),
			'form_name'	=> 'filter',
			'title'	=> lang('Filter'),
			'hidden_vars'	=> $hidden_vars,
			'form_options'	=> $form_options,
			'button_value'	=> lang('Go!')
		);
		$tpl->set_var($var);
		$tpl->fp('header_column','form_button_dropdown',True);
	}

	if(count($grants) > 0)
	{
		$hidden_vars = '    <input type="hidden" name="from" value="'.$base_url.'">'."\n";
		if(isset($date) && $date)
		{
			$hidden_vars .= '    <input type="hidden" name="date" value="'.$date.'">'."\n";
		}
		$hidden_vars .= '    <input type="hidden" name="month" value="'.$thismonth.'">'."\n";
		$hidden_vars .= '    <input type="hidden" name="day" value="'.$thisday.'">'."\n";
		$hidden_vars .= '    <input type="hidden" name="year" value="'.$thisyear.'">'."\n";
		if(isset($keywords) && $keywords)
		{
			$hidden_vars .= '    <input type="hidden" name="keywords" value="'.$keywords.'">'."\n";
		}
		if(isset($id) && $id != 0)
		{
			$hidden_vars .= '    <input type="hidden" name="id" value="'.$id.'">'."\n";
		}
		$form_options = '';
		while(list($grantor,$temp_rights) = each($grants))
		{
			$form_options .= '    <option value="'.$grantor.'"'.($grantor==$owner?' selected':'').'>'.$phpgw->common->grab_owner_name($grantor).'</option>'."\n";
      }
		reset($grants);
		
		$var = Array(
			'form_width' => $remainder,
			'form_link'	=> $phpgw->link($base_url),
			'form_name'	=> 'owner',
			'title'	=> lang('User'),
			'hidden_vars'	=> $hidden_vars,
			'form_options'	=> $form_options,
			'button_value'	=> lang('Go!')
		);
		$tpl->set_var($var);
		$tpl->parse('header_column','form_button_dropdown',True);
	}

	$hidden_vars = '    <input type="hidden" name="from" value="'.$base_url.'">'."\n";
	if(isset($date) && $date)
	{
		$hidden_vars .= '    <input type="hidden" name="date" value="'.$date.'">'."\n";
	}
	$hidden_vars .= '    <input type="hidden" name="month" value="'.$thismonth.'">'."\n";
	$hidden_vars .= '    <input type="hidden" name="day" value="'.$thisday.'">'."\n";
	$hidden_vars .= '    <input type="hidden" name="year" value="'.$thisyear.'">'."\n";
	if(isset($keywords) && $keywords)
	{
		$hidden_vars .= '    <input type="hidden" name="keywords" value="'.$keywords.'">'."\n";
	}
	if(isset($filter) && $filter)
	{
		$hidden_vars .= '    <input type="hidden" name="filter" value="'.$filter.'">'."\n";
	}
	$extra_field = $hidden_vars.'    <input name="keywords"'.($keywords?' value="'.$keywords.'"':'').'>';

	$var = Array(
		'action_url_button'	=> $phpgw->link('/calendar/search.php','owner='.$owner),
		'action_text_button'	=> lang('Search'),
		'action_confirm_button'	=> '',
		'action_extra_field'	=> $extra_field
	);
	$tpl->set_var($var);
	$button = $tpl->fp('out','form_button');
	$tpl->set_var('str','<td align="right" valign="bottom">'.$button.'</td>');
	$tpl->parse('header_column','head_col',True);

	echo $tpl->fp('out','head');
	unset($tpl);
?>
