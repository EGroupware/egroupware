<?php
  /**************************************************************************\
  * phpGroupWare - Calendar                                                  *
  * http://www.phpgroupware.org                                              *
  * Based on Webcalendar by Craig Knudsen <cknudsen@radix.net>               *
  *          http://www.radix.net/~cknudsen                                  *
  * Modified by Mark Peters <skeeter@phpgroupware.org>                       *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */

	class uiholiday
	{
		var $debug = False;
		var $base_url;
		var $bo;
		var $template_dir;
		var $holidays;
		var $cat_id;

		var $public_functions = array(
			'admin' => True,
			'edit_locale' => True,
			'edit_holiday' => True,
			'add_holiday' => True,
			'delete_holiday' => True,
			'delete_locale'	=> True
		);

		function uiholiday()
		{
			$GLOBALS['phpgw']->nextmatchs = CreateObject('phpgwapi.nextmatchs');

			$this->bo = CreateObject('calendar.boholiday');
			$this->bo->check_admin();
			$this->base_url = $this->bo->base_url;
			$this->template_dir = $GLOBALS['phpgw']->common->get_tpl_dir('calendar');
		}

		function admin()
		{
			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw_info']['flags']['noappfooter'] = True;
			$GLOBALS['phpgw']->common->phpgw_header();

			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_file(Array('locales'=>'locales.tpl'));
			$p->set_block('locales','list','list');
			$p->set_block('locales','row','row');
			$p->set_block('locales','row_empty','row_empty');
			$p->set_block('locales','submit_column','submit_column');

			$var = Array(
				'th_bg'		=> $GLOBALS['phpgw_info']['theme']['th_bg'],
				'left_next_matchs'	=> $GLOBALS['phpgw']->nextmatchs->left('/calendar/'.basename($SCRIPT_FILENAME),$this->bo->start,$this->bo->total),
				'right_next_matchs'	=> $GLOBALS['phpgw']->nextmatchs->right('/calendar/'.basename($SCRIPT_FILENAME),$this->bo->start,$this->bo->total),
				'lang_groups'	=> lang('Countries'),
				'sort_name'		=> $GLOBALS['phpgw']->nextmatchs->show_sort_order($this->bo->sort,'locale',$this->bo->order,'/calendar/'.basename($SCRIPT_FILENAME),lang('Country')),
				'header_edit'	=> lang('Edit'),
				'header_delete'	=> lang('Delete'),
				'submit_extra'	=> '',
				'submit_link'	=> lang('Submit to Repository'),
				'back_button'	=> ''
			);

			$p->set_var($var);
			$p->parse('header_submit','submit_column',False);

			$locales = $this->bo->get_locale_list($this->bo->sort, $this->bo->order, $this->bo->query, $this->bo->total);
			@reset($locales);
			if (!$locales)
			{
				$p->set_var('message',lang('No matchs found'));
				$p->parse('rows','row_empty',True);
			}
			else
			{
				$p->set_var('submit_extra',' width="5%"');
				while (list(,$value) = each($locales))
				{
					$tr_color = $GLOBALS['phpgw']->nextmatchs->alternate_row_color($tr_color);
					if (! $value)  $value  = '&nbsp;';

					$var = Array(
						'tr_color'		=> $tr_color,
						'group_name'	=> $value,
						'edit_link'		=> '<a href="'.$GLOBALS['phpgw']->link($this->base_url,Array('menuaction'=>'calendar.uiholiday.edit_locale','locale'=>$value)) . '"> '.lang('Edit').' </a>',
						'delete_link'	=> '<a href="'.$GLOBALS['phpgw']->link($this->base_url,Array('menuaction'=>'calendar.uiholiday.delete_locale','locale'=>$value)).'"> '.lang('Delete').' </a>',
						'submit_link'	=> '<a href="'.$GLOBALS['phpgw']->link($this->base_url,Array('menuaction'=>'calendar.uiholiday.submit','locale'=>$value)).'"> '.lang('Submit').' </a>'
					);
					$p->set_var($var);
					$p->parse('submit_link_column','submit_column',False);
					$p->parse('rows','row',True);
				}
			}

			$var = Array(
				'new_action'		=> $GLOBALS['phpgw']->link($this->base_url,Array('menuaction'=>'calendar.uiholiday.add_holiday','id'=>0)),
				'lang_add'			=> lang('add'),
				'search_action'	=> $GLOBALS['phpgw']->link($this->base_url,Array('menuaction'=>'calendar.uiholiday.admin')),
				'lang_search'		=> lang('search')
			);

			$p->set_var($var);
			$p->pparse('out','list');
		}

		function edit_locale()
		{
			if(!$this->bo->total && !isset($this->bo->query))
			{
				$link_params = Array(
					'menuaction'	=> 'calendar.uiholiday.admin'
				);
				Header('Location: ' . $GLOBALS['phpgw']->link($this->base_url,$link_params));
			}
 
			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw_info']['flags']['noappfooter'] = True;
			$GLOBALS['phpgw']->common->phpgw_header();
			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_file(Array('locale'=>'locales.tpl'));
			$p->set_block('locale','list','list');
			$p->set_block('locale','row','row');
			$p->set_block('locale','row_empty','row_empty');
			$p->set_block('locale','back_button_form','back_button_form');

			$var = Array(
				'th_bg'			=> $phpgw_info['theme']['th_bg'],
				'left_next_matchs'	=> $GLOBALS['phpgw']->nextmatchs->left('/calendar/'.basename($SCRIPT_FILENAME),$this->bo->start,$this->bo->total,'&locale='.$this->bo->locales[0]),
				'right_next_matchs'	=> $GLOBALS['phpgw']->nextmatchs->right('/calendar/'.basename($SCRIPT_FILENAME),$this->bo->start,$this->bo->total,'&locale='.$this->bo->locales[0]),
				'lang_groups'		=> lang('Holidays').' ('.$this->bo->locales[0].')',
				'sort_name'		=> $GLOBALS['phpgw']->nextmatchs->show_sort_order($this->bo->sort,'name',$this->bo->order,'/calendar/'.basename($SCRIPT_FILENAME),lang('Holiday'),'&locale='.$this->bo->locales[0]),
				'header_edit'		=> lang('Edit'),
				'header_delete'		=> lang('Delete'),
				'header_submit'		=> '',
				'submit_link_column'	=> ''
			);

			$p->set_var($var);

			$holidays = $this->bo->get_holiday_list();

			if (!count($holidays))
			{
				$p->set_var('message',lang('No matchs found'));
				$p->parse('rows','row_empty',True);
			}
			else
			{
				for($i=0;$i<count($holidays);$i++)
				{
					$tr_color = $GLOBALS['phpgw']->nextmatchs->alternate_row_color($tr_color);
					if (!$holidays[$i]['name'])
					{
						$holidays[$i]['name'] = '&nbsp;';
					}
					
					$var = Array(
						'tr_color'		=> $tr_color,
						'header_delete'=> lang('Delete'),
						'group_name'	=> $holidays[$i]['name'],
						'edit_link'		=> '<a href="'.$GLOBALS['phpgw']->link($this->base_url,Array('menuaction'=>'calendar.uiholiday.edit_holiday','locale'=>$this->bo->locales[0],'id'=>$holidays[$i]['index'])).'"> '.lang('Edit').' </a>',
						'delete_link'	=> '<a href="'.$GLOBALS['phpgw']->link($this->base_url,Array('menuaction'=>'calendar.uiholiday.delete_holiday','locale'=>$this->bo->locales[0],'id'=>$holidays[$i]['index'])).'"> '.lang('Delete').' </a>'
					);

					$p->set_var($var);
					$p->parse('rows','row',True);
				}
			}

			$var = Array(
				'new_action'	=> $GLOBALS['phpgw']->link($this->base_url,Array('menuaction'=>'calendar.uiholiday.add_holiday','locale'=>$this->bo->locales[0],'id'=>0)),
				'lang_add'		=> lang('add'),
				'back_action'	=> $GLOBALS['phpgw']->link($this->base_url,Array('menuaction'=>'calendar.uiholiday.admin')),
				'lang_back'		=> lang('Back'),
				'search_action'=> $GLOBALS['phpgw']->link($this->base_url,Array('menuaction'=>'calendar.uiholiday.edit_locale','locale'=>$this->bo->locales[0])),
				'lang_search'	=> lang('search')
			);
			$p->set_var($var);
			$p->parse('back_button','back_button_form',False);
			$p->pparse('out','list');
		}

		function edit_holiday()
		{
			if(@$this->bo->id)
			{
				$holiday = $this->bo->read_entry($this->bo->id);
			}
			if(!$holiday || !@$this->bo->id)
			{
				Header('Location: ' . $GLOBALS['phpgw']->link($this->base_url,Array('menuaction'=>'calendar.uiholiday.edit_locale','locale'=>$this->bo->locales[0])));
			}

			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw_info']['flags']['noappfooter'] = True;
			$GLOBALS['phpgw']->common->phpgw_header();

			$sb = CreateObject('phpgwapi.sbox');

			$t = CreateObject('phpgwapi.Template',$this->template_dir);
			$t->set_file(Array('holiday'=>'holiday.tpl','form_button'=>'form_button_script.tpl'));
			$t->set_block('holiday','form','form');
			$t->set_block('holiday','list','list');

			if (@$errorcount)
			{
				$message = $GLOBALS['phpgw']->common->error_list($error);
			}
			else
			{
				$message = '';
			}
	
			$var = Array(
				'title_holiday'=> lang('Edit').' '.lang('Holiday'),
				'message'		=> $message,
				'actionurl'	   => $GLOBALS['phpgw']->link($this->base_url,'menuaction=calendar.boholiday.add'),
				'hidden_vars'	=> '<input type="hidden" name="holiday[hol_id]" value="'.$this->bo->id.'">'."\n"
							 . '<input type="hidden" name="holiday[locales]" value="'.$this->bo->locales[0].'">'."\n"
			);
			$t->set_var($var);

// Locale
			$this->display_item($t,lang('Country'),'<input name="holiday[locale]" size="2" maxlength="2" value="'.$holiday[locale].'">');

// Title/Name
			$this->display_item($t,lang('title'),'<input name="holiday[name]" size="25" maxlength="50" value="'.$holiday['name'].'">');

// Date
			$this->display_item($t,lang('Date'),$GLOBALS['phpgw']->common->dateformatorder('',$sb->getMonthText('holiday[month_num]',$holiday['month']),$sb->getDays('holiday[mday]',$holiday['day'])));

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
			$out = '';
			while(list($key,$value) = each($occur))
			{
				$out .= '<option value="'.$key.'"'.($holiday['occurence']==$key?' selected':'').'>'.$value.'</option>'."\n";
			}
			$occurence_html = '<select name="holiday[occurence]">'."\n".$out.'</select>'."\n";

			$dow = Array(
				0	=> lang('Sun'),
				1	=> lang('Mon'),
				2	=> lang('Tue'),
				3	=> lang('Wed'),
				4	=> lang('Thu'),
				5	=> lang('Fri'),
				6	=> lang('Sat')
			);
			$out = '';
			for($i=0;$i<7;$i++)
			{
				$out .= '<option value="'.$i.'"'.($holiday['dow']==$i?' selected':'').'>'.$dow[$i].'</option>'."\n";
			}
			$dow_html = '<select name="holiday[dow]">'."\n".$out.'</select>'."\n";
			$this->display_item($t,lang('Occurence'),$occurence_html.'&nbsp;'.$dow_html);
			$this->display_item($t,lang('Observance Rule'),'<input type="checkbox" name="holiday[observance_rule]" value="True"'.($holiday['observance_rule']?' checked':'').'>');

			$t->set_var('lang_add',lang('Save'));
			$t->set_var('lang_reset',lang('Reset'));

			if(@$this->bo->locales[0])
			{
				$link_params = Array(
					'menuaction'	=> 'calendar.uiholiday.edit_locale',
					'locale'		=> $this->bo->locales[0]
				);
			}
			else
			{
				$link_params = Array(
					'menuaction'	=> 'calendar.uiholiday.admin'
				);
			}
			
			$var = Array(
				'action_url_button'	=> $GLOBALS['phpgw']->link($this->base_url,$link_params),
				'action_text_button'	=> lang('Cancel'),
				'action_confirm_button'	=> '',
				'action_extra_field'	=> ''
			);
			$t->set_var($var);
			$t->parse('cancel_button','form_button');
			$t->pparse('out','form');
		}


		function add_holiday($messages='',$holiday='')
		{
			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw_info']['flags']['noappfooter'] = True;
			$GLOBALS['phpgw']->common->phpgw_header();

			$sb = CreateObject('phpgwapi.sbox');

			$t = CreateObject('phpgwapi.Template',$this->template_dir);
			$t->set_file(Array('holiday'=>'holiday.tpl','form_button'=>'form_button_script.tpl'));
			$t->set_block('holiday','form','form');
			$t->set_block('holiday','list','list');

			if($messages)
			{
				if (is_array($messages))
				{
					$message = $GLOBALS['phpgw']->common->error_list($messages);
				}
				else
				{
					$message = '';
				}
			}
	
			$var = Array(
				'title_holiday'	=> lang('Add').' '.lang('Holiday'),
				'message'			=> $message,
				'actionurl'			=> $GLOBALS['phpgw']->link($this->base_url,'menuaction=calendar.boholiday.add'),
				'hidden_vars'		=> '<input type="hidden" name="locale" value="'.$this->bo->locales[0].'">'."\n"
									. '<input type="hidden" name="id" value="'.$this->bo->id.'">'."\n"
									. '<input type="hidden" name="holiday[hol_id]" value="'.$this->bo->id.'">'."\n"
									. '<input type="hidden" name="holiday[locales]" value="'.$this->bo->locales[0].'">'."\n"
			);

			$t->set_var($var);

// Locale
			if($this->bo->locales[0])
			{
				$holiday['locale'] = $this->bo->locales[0];
			}
			$this->display_item($t,lang('Country'),'<input name="holiday[locale]" size="2" maxlength="2" value="'.$holiday[locale].'">');

// Title/Name
			$this->display_item($t,lang('title'),'<input name="holiday[name]" size="25" maxlength="50" value="'.$holiday['name'].'">');

// Date
			$day_html = $sb->getDays('holiday[mday]',$holiday['day']);
			$month_html = $sb->getMonthText('holiday[month_num]',$holiday['month']);
			$year_html = '';
			$this->display_item($t,lang('Date'),$GLOBALS['phpgw']->common->dateformatorder($year_html,$month_html,$day_html));

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
			$out = '';
			while(list($key,$value) = each($occur))
			{
				$out .= '<option value="'.$key.'"'.($holiday['occurence']==$key?' selected':'').'>'.$value.'</option>'."\n";
			}
			$occurence_html = '<select name="holiday[occurence]">'."\n".$out.'</select>'."\n";

			$dow = Array(
				0	=> lang('Sun'),
				1	=> lang('Mon'),
				2	=> lang('Tue'),
				3	=> lang('Wed'),
				4	=> lang('Thu'),
				5	=> lang('Fri'),
				6	=> lang('Sat')
			);
			$out = '';
			for($i=0;$i<7;$i++)
			{
				$out .= '<option value="'.$i.'"'.($holiday['dow']==$i?' selected':'').'>'.$dow[$i].'</option>'."\n";
			}
			$dow_html = '<select name="holiday[dow]">'."\n".$out.'</select>'."\n";
			$this->display_item($t,lang('Occurence'),$occurence_html.'&nbsp;'.$dow_html);
			$this->display_item($t,lang('Observance Rule'),'<input type="checkbox" name="holiday[observance_rule]" value="True"'.($holiday['observance_rule']?' checked':'').'>');

			$t->set_var('lang_add',lang('Save'));
			$t->set_var('lang_reset',lang('Reset'));
			if(@$this->bo->locales[0])
			{
				$link_params = Array(
					'menuaction'	=> 'calendar.uiholiday.edit_locale',
					'locale'		=> $this->bo->locales[0]
				);
			}
			else
			{
				$link_params = Array(
					'menuaction'	=> 'calendar.uiholiday.admin'
				);
			}
			$var = Array(
				'action_url_button'	=> $GLOBALS['phpgw']->link($this->base_url,$link_params),
				'action_text_button'	=> lang('Cancel'),
				'action_confirm_button'	=> '',
				'action_extra_field'	=> ''
			);
			$t->set_var($var);
			$t->parse('cancel_button','form_button');
			$t->pparse('out','form');
		}

		function delete_locale()
		{
			if(!$this->bo->total)
			{
				$this->admin();
			}
			
			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw_info']['flags']['noappfooter'] = True;
			$GLOBALS['phpgw']->common->phpgw_header();

			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_file(Array('form'=>'delete_common.tpl','form_button'=>'form_button_script.tpl'));
		
			$p->set_var('messages',lang('Are you sure you want to delete this Country ?')."<br>".$this->bo->locales[0]);

			$var = Array(
				'action_url_button'	=> $GLOBALS['phpgw']->link($this->base_url,Array('menuaction'=>'calendar.uiholiday.admin')),
				'action_text_button'	=> lang('No'),
				'action_confirm_button'	=> '',
				'action_extra_field'	=> ''
			);
			$p->set_var($var);
			$p->parse('no','form_button');

			$var = Array(
				'action_url_button'	=> $GLOBALS['phpgw']->link($this->base_url,Array('menuaction'=>'calendar.boholiday.delete_locale','locale'=>$this->bo->locales[0])),
				'action_text_button'	=> lang('Yes'),
				'action_confirm_button'	=> '',
				'action_extra_field'	=> ''
			);
			$p->set_var($var);
			$p->parse('yes','form_button');

			$p->pparse('out','form');
		}

		function delete_holiday()
		{
			$holiday = $this->bo->read_entry($this->bo->id);

			if(!$holiday)
			{
				$this->edit_locale();
			}
			
			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw_info']['flags']['noappfooter'] = True;
			$GLOBALS['phpgw']->common->phpgw_header();

			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_file(Array('form'=>'delete_common.tpl','form_button'=>'form_button_script.tpl'));
		
			$p->set_var('messages',lang('Are you sure you want to delete this holiday ?')."<br>".$holiday['name'].' ('.$this->bo->locales[0].')');

			$var = Array(
				'action_url_button'	=> $GLOBALS['phpgw']->link($this->base_url,Array('menuaction'=>'calendar.uiholiday.edit_locale','locale'=>$this->bo->locales[0])),
				'action_text_button'	=> lang('No'),
				'action_confirm_button'	=> '',
				'action_extra_field'	=> ''
			);
			$p->set_var($var);
			$p->parse('no','form_button');

			$var = Array(
				'action_url_button'	=> $GLOBALS['phpgw']->link($this->base_url,Array('menuaction'=>'calendar.boholiday.delete_holiday','locale'=>$this->bo->locales[0],'id'=>$this->bo->id)),
				'action_text_button'	=> lang('Yes'),
				'action_confirm_button'	=> '',
				'action_extra_field'	=> ''
			);
			$p->set_var($var);
			$p->parse('yes','form_button');

			$p->pparse('out','form');
		}

		function submit()
		{
			if(!@$this->bo->locales[0])
			{
				$this->admin();
			}
			$holidays = $this->bo->get_holiday_list();
			$GLOBALS['phpgw_info']['flags']['noappheader']	= True;
			$GLOBALS['phpgw_info']['flags']['noappfooter'] = True;
			$GLOBALS['phpgw_info']['flags']['nofooter'] = True;
			$GLOBALS['phpgw']->common->phpgw_header();

			echo '<body onLoad="document.submitform.submit()">'."\n";
			if($this->debug)
			{
				echo '<form action="'.$GLOBALS['phpgw']->link($this->base_url,'menuaction=calendar.boholiday.accept_holiday').'" method="post" name="submitform">'."\n";
			}
			else
			{
				echo '<form action="http://www.phpgroupware.org/cal/accept_holiday.php" method="post" name="submitform">'."\n";
			}

			$c_holidays = count($holidays);
			echo '<input type="hidden" name="locale" value="'.$this->bo->locales[0].'">'."\n";
			for($i=0;$i<$c_holidays;$i++)
			{
				echo '<input type="hidden" name="name[]" value="'.$holidays[$i]['name'].'">'."\n"
					. '<input type="hidden" name="day[]" value="'.$holidays[$i]['day'].'">'."\n"
					. '<input type="hidden" name="month[]" value="'.$holidays[$i]['month'].'">'."\n"
					. '<input type="hidden" name="occurence[]" value="'.$holidays[$i]['occurence'].'">'."\n"
					. '<input type="hidden" name="dow[]" value="'.$holidays[$i]['dow'].'">'."\n"
					. '<input type="hidden" name="observance[]" value="'.$holidays[$i]['observance_rule'].'">'."\n";
			}
			echo "</form>\n</body>\n</head>";
		}

		/* private functions */
		function display_item(&$p,$field,$data)
		{
			$var = Array(
				'field'	=> $field,
				'data'	=> $data
			);
			$p->set_var($var);
			$p->parse('rows','list',True);
		}
	}
?>
