<?php
  /**************************************************************************\
  * phpGroupWare - Admin                                                     *
  * http://www.phpgroupware.org                                              *
  * Written by Bettina Gille [ceb@phpgroupware.org]                          *
  * -----------------------------------------------                          *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
	/* $Id$ */

	if(!$id)
	{
		Header('Location: ' . $phpgw->link('/calendar/editlocale.php','locale='.$locale));
	}

	$phpgw_flags = Array(
		'currentapp'		=> 'calendar',
		'enable_nextmatchs_class'	=> True,
		'admin_header'		=>	True,
		'noheader'			=> True,
		'nonavbar'			=> True,
		'noappheader'		=> True,
		'noappfooter'		=> True,
		'parent_page'		=> 'holiday_admin.php'
	);
	$phpgw_info['flags'] = $phpgw_flags;
	include('../header.inc.php');

	function display_item(&$p,$field,$data)
	{
		$p->set_var('field',$field);
		$p->set_var('data',$data);
		$p->parse('rows','list',True);
	}

	if(!$submit)
	{
		$phpgw->calendar->holidays->users['admin'] = $locale;
		$phpgw->calendar->holidays->read_holiday();
		if(!isset($phpgw->calendar->holidays->index[$id]))
		{
			Header('Location: ' . $phpgw->link('/calendar/editlocale.php','locale='.$locale));
		}
		else
		{
			$phpgw->common->phpgw_header();
			echo parse_navbar();
			$index = $phpgw->calendar->holidays->index[$id];
		}
		
		$sb = CreateObject('phpgwapi.sbox');
		
		$holiday = $phpgw->calendar->holidays->get_holiday($index);
		$t = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
		$templates = Array(
			'holiday'	=> 'holiday.tpl',
			'form_button'	=>	'form_button_script.tpl'
		);
		$t->set_file($templates);
		$t->set_block('holiday','form','form');
		$t->set_block('holiday','list','list');

		$title_holiday = lang('Holiday');

		if ($errorcount)
		{
			$message = $phpgw->common->error_list($error);
		}
		else
		{
			$message = '';
		}

		$actionurl = $phpgw->link('/calendar/editholiday.php');
		$hidden_vars = '<input type="hidden" name="locale" value="'.$locale.'">'."\n"
						 . '<input type="hidden" name="id" value="'.$id.'">'."\n";


		$var = Array(
			'title_holiday'	=> $title_holiday,
			'message'		=> $message,
			'actionurl'	=> $actionurl,
			'hidden_vars'	=> $hidden_vars
		);

		$t->set_var($var);

// Title/Name
		display_item($t,lang('title'),'<input name="name" size="25" maxlength="50" value="'.$holiday['name'].'">');

// Date
		$day_html = $sb->getDays('day',$holiday['day']);
		$month_html = $sb->getMonthText('month',$holiday['month']);
		$year_html = '';
		display_item($t,lang('Date'),$phpgw->common->dateformatorder($year_html,$month_html,$day_html));

// Occurence
		$occur = Array(
			0	=> '0',
			1	=> '1st',
			2	=> '2nd',
			3	=> '3rd',
			4	=> '4th',
			5	=> '5th',
			99	=> 'Last'
		);
		$out = '<select name="occurence">'."\n";
		while(list($key,$value) = each($occur))
		{
			$out .= '<option value="'.$key.'"';
			if($holiday['occurence']==$key) $out .= ' selected';
			$out .= '>'.$value.'</option>'."\n";
		}
		$out .= '</select>'."\n";

		$occurence_html = $out;

		$dow = Array(
			0	=> lang('Sun'),
			1	=> lang('Mon'),
			2	=> lang('Tue'),
			3	=> lang('Wed'),
			4	=> lang('Thu'),
			5	=> lang('Fri'),
			6	=> lang('Sat')
		);
		$out = '<select name="dow">'."\n";
		for($i=0;$i<7;$i++)
		{
			$out .= '<option value="'.$i.'"';
			if($holiday['occurence']==$i) $out .= ' selected';
			$out .= '>'.$dow[$i].'</option>'."\n";
		}
		$out .= '</select>'."\n";

		$dow_html = $out;
		
		display_item($t,lang('Occurence'),$occurence_html.'&nbsp;'.$dow_html);

		$str = '<input type="checkbox" name="observance_rule" value="True"';
		if($holiday['observance_rule'])
		{
			$str .= ' checked';
		}
		$str .= '>';
		display_item($t,lang('Observance Rule'),$str);


		$t->set_var('lang_add',lang('Save'));
		$t->set_var('lang_reset',lang('Reset'));
		$var = Array(
			'action_url_button'	=> $phpgw->link('/calendar/editlocale.php','locale='.$locale),
			'action_text_button'	=> lang('Cancel'),
			'action_confirm_button'	=> '',
			'action_extra_field'	=> ''
		);
		$t->set_var($var);
		$t->parse('cancel_button','form_button');
		$t->pparse('out','form');
		$phpgw->common->phpgw_footer();
	}
?>
