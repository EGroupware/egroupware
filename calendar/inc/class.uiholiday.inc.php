<?php
	/**************************************************************************\
	* eGroupWare - Calendar                                                    *
	* http://www.egroupware.org                                                *
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

	/**
	 * User interface for calendar holidays
	 *
	 * @package calendar
	 * @author Mark Peters <skeeter@phpgroupware.org>
	 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	 */
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
			'copy_holiday' => True,
			'delete_holiday' => True,
			'delete_locale'	=> True,
			'submit'	=> True
		);

		function uiholiday()
		{
			$this->bo =& CreateObject('calendar.boholiday');
			$this->bo->check_admin();
			$this->base_url = $this->bo->base_url;
			$this->template_dir = common::get_tpl_dir('calendar');
			$this->sb =& CreateObject('calendar.sbox');

			// calendar does not work with hidden sidebox atm.
			unset($GLOBALS['egw_info']['user']['preferences']['common']['auto_hide_sidebox']);

			$GLOBALS['egw_info']['flags']['app_header'] = $GLOBALS['egw_info']['apps']['calendar']['title'].' - '.lang('Holiday Management');

			$GLOBALS['egw']->template->set_var('help_msg',lang('<b>Please note</b>: The calendar use the holidays of your country, which is set to %1. You can change it in your %2.<br />Holidays are %3 automatic installed from %4. You can changed it in %5.',
				'<b>'.$GLOBALS['egw_info']['user']['preferences']['common']['country'].'</b>',lang('common preferences'),
				$GLOBALS['egw_info']['server']['auto_load_holidays'] ? '' : '<b>'.lang('not').'</b>',
				'<b>'.$GLOBALS['egw_info']['server']['holidays_url_path'].'</b>',
				'<a href="'.$GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'admin.uiconfig.index',
					'appname'    => 'calendar',
				)).'">'.lang('admin').' >> '.lang('calendar').' >> '.lang('site configuration').'</a>'));
			$GLOBALS['egw']->template->set_var('total','');
		}

		function admin()
		{
			unset($GLOBALS['egw_info']['flags']['noheader']);
			unset($GLOBALS['egw_info']['flags']['nonavbar']);
			$GLOBALS['egw_info']['flags']['noappfooter'] = True;
			common::egw_header();

			$p = &$GLOBALS['egw']->template;
			$p->set_file(Array('locales'=>'locales.tpl'));
			$p->set_block('locales','list','list');
			$p->set_block('locales','row','row');
			$p->set_block('locales','row_empty','row_empty');

			$var = Array(
				'th_bg'		=> $GLOBALS['egw_info']['theme']['th_bg'],
				'left_next_matchs'	=> $GLOBALS['egw']->nextmatchs->left('/index.php?menuaction=calendar.uiholiday.admin',$this->bo->start,$this->bo->total),
				'right_next_matchs'	=> $GLOBALS['egw']->nextmatchs->right('/index.php?menuaction=calendar.uiholiday.admin',$this->bo->start,$this->bo->total),
				'center'			=> '<td align="center">'.lang('Countries').'</td>',
				'sort_name'		=> $GLOBALS['egw']->nextmatchs->show_sort_order($this->bo->sort,'hol_locale',$this->bo->order,'/calendar/'.basename($SCRIPT_FILENAME),lang('Country')),
				'header_edit'	=> lang('Edit'),
				'header_delete'	=> lang('Delete'),
				'header_extra'	=> lang('Submit to Repository'),
				'extra_width'  => 'width="45%"',
				'rule'         => '',
				'header_rule'  => '',
				'back_button'	=> ''
			);

			$p->set_var($var);

			$locales = $this->bo->get_locale_list($this->bo->sort, $this->bo->order, $this->bo->query, $this->bo->total);
			@reset($locales);
			if (!$locales)
			{
				$p->set_var('message',lang('no matches found'));
				$p->parse('rows','row_empty',True);
			}
			else
			{
				$p->set_var('submit_extra',' width="5%"');
				while (list(,$value) = each($locales))
				{
					$tr_color = $GLOBALS['egw']->nextmatchs->alternate_row_color($tr_color);
					if (! $value)  $value  = '&nbsp;';

					$var = Array(
						'tr_color'		=> $tr_color,
						'group_name'	=> $value,
						'edit_link'		=> '<a href="'.$GLOBALS['egw']->link($this->base_url,Array('menuaction'=>'calendar.uiholiday.edit_locale','locale'=>$value)) . '"> '.lang('Edit').' </a>',
						'delete_link'	=> '<a href="'.$GLOBALS['egw']->link($this->base_url,Array('menuaction'=>'calendar.uiholiday.delete_locale','locale'=>$value)).'"> '.lang('Delete').' </a>',
						'extra_link'	=> '<a href="'.$GLOBALS['egw']->link($this->base_url,Array('menuaction'=>'calendar.uiholiday.submit','locale'=>$value)).'"> '.lang('Submit').' </a>'.
							' &nbsp; &nbsp; <a href="'.$GLOBALS['egw']->link($this->base_url,Array('menuaction'=>'calendar.uiholiday.submit','locale'=>$value,'download'=>1)).'"> '.lang('Download').' </a>'
					);
					$p->set_var($var);
					$p->parse('rows','row',True);
				}
			}

			$var = Array(
				'new_action'		=> $GLOBALS['egw']->link($this->base_url,Array('menuaction'=>'calendar.uiholiday.edit_holiday','id'=>0)),
				'lang_add'			=> lang('add'),
				'search_action'	=> $GLOBALS['egw']->link($this->base_url,Array('menuaction'=>'calendar.uiholiday.admin')),
				'lang_search'		=> lang('search')
			);

			$p->set_var($var);
			$p->pparse('out','list');
		}

		function edit_locale($locale='')
		{
			if ($locale === '')
			{
				$locale = $this->bo->locale;
			}
			if ($locale)
			{
				$this->bo->locales = array($locale);
				$this->bo->total = $this->bo->so->holiday_total($locale,$this->bo->query);
			}
			if(!$this->bo->total && !isset($this->bo->query))
			{
				$link_params = Array(
					'menuaction'	=> 'calendar.uiholiday.admin'
				);
				$GLOBALS['egw']->redirect_link($this->base_url,$link_params);
			}
			unset($GLOBALS['egw_info']['flags']['noheader']);
			unset($GLOBALS['egw_info']['flags']['nonavbar']);
			$GLOBALS['egw_info']['flags']['noappfooter'] = True;
			common::egw_header();
			$p =& $GLOBALS['egw']->template;
			$p->set_file(Array('locale'=>'locales.tpl'));
			$p->set_block('locale','list','list');
			$p->set_block('locale','row','row');
			$p->set_block('locale','row_empty','row_empty');
			$p->set_block('locale','back_button_form','back_button_form');

			if (!is_object($GLOBALS['egw']->html))
			{
				$GLOBALS['egw']->html =& CreateObject('phpgwapi.html');
			}
			$html = &$GLOBALS['egw']->html;
			$year_form = str_replace('<option value=""></option>','',$html->form($html->sbox_submit($this->sb->getYears('year',$this->bo->year),true),array(),
				$this->base_url,Array('menuaction'=>'calendar.uiholiday.edit_locale','locale'=>$this->bo->locales[0])));
			unset($html);

			$holidays = $this->bo->get_holiday_list();
			$total = count($holidays);

			$var = Array(
				'th_bg'				=> $GLOBALS['egw_info']['theme']['th_bg'],
				'left_next_matchs'	=> $GLOBALS['egw']->nextmatchs->left('/index.php',$this->bo->start,$total,'&menuaction=calendar.uiholiday.edit_locale&locale='.$this->bo->locales[0].'&year='.$this->bo->year),
				'right_next_matchs'	=> $GLOBALS['egw']->nextmatchs->right('/index.php',$this->bo->start,$total,'&menuaction=calendar.uiholiday.edit_locale&locale='.$this->bo->locales[0].'&year='.$this->bo->year),
				'center'			=> '<td align="right">'.lang('Holidays').' ('.$this->bo->locales[0].')</td><td align="left">'.$year_form.'</td>',
				'sort_name'			=> $GLOBALS['egw']->nextmatchs->show_sort_order($this->bo->sort,'hol_name',$this->bo->order,'/index.php',lang('Holiday'),'&menuaction=calendar.uiholiday.edit_locale&locale='.$this->bo->locales[0].'&year='.$this->bo->year),
				'header_edit'		=> lang('Edit'),
				'header_delete'		=> lang('Delete'),
				'header_rule'		=> '<td>'.$GLOBALS['egw']->nextmatchs->show_sort_order($this->bo->sort,'hol_month_num,hol_mday',$this->bo->order,'/index.php',lang('Rule'),'&menuaction=calendar.uiholiday.edit_locale&locale='.$this->bo->locales[0].'&year='.$this->bo->year).'</td>',
				'header_extra'		=> lang('Copy'),
				'extra_width'		=> 'width="5%"'
			);

			$p->set_var($var);

			if (!count($holidays))
			{
				$p->set_var('total',lang('no matches found'));
				//$p->parse('rows','row_empty',True);
			}
			else
			{
				$maxmatchs = $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'];
				$end = min($total,$this->bo->start+$maxmatchs);
				$p->set_var('total',lang('showing %1 - %2 of %3',1+$this->bo->start,$end,$total));
				//$p->parse('rows','row_empty',True);
				for($i=$this->bo->start; $i < $end; $i++)
				{
					$tr_color = $GLOBALS['egw']->nextmatchs->alternate_row_color($tr_color);
					if (!$holidays[$i]['name'])
					{
						$holidays[$i]['name'] = '&nbsp;';
					}

					$var = Array(
						'tr_color'		=> $tr_color,
						'header_delete'=> lang('Delete'),
						'group_name'	=> $holidays[$i]['name'],
						'rule'			=> '<td>'.$this->bo->rule_string($holidays[$i]).'</td>',
						'edit_link'		=> '<a href="'.$GLOBALS['egw']->link($this->base_url,Array('menuaction'=>'calendar.uiholiday.edit_holiday','locale'=>$this->bo->locales[0],'id'=>$holidays[$i]['index'],'year'=>$this->bo->year)).'"> '.lang('Edit').' </a>',
						'extra_link'	=> '<a href="'.$GLOBALS['egw']->link($this->base_url,Array('menuaction'=>'calendar.uiholiday.copy_holiday','locale'=>$this->bo->locales[0],'id'=>$holidays[$i]['index'],'year'=>$this->bo->year)).'"> '.lang('Copy').' </a>',
						'delete_link'	=> '<a href="'.$GLOBALS['egw']->link($this->base_url,Array('menuaction'=>'calendar.uiholiday.delete_holiday','locale'=>$this->bo->locales[0],'id'=>$holidays[$i]['index'],'year'=>$this->bo->year)).'"> '.lang('Delete').' </a>'
					);

					$p->set_var($var);
					$p->parse('rows','row',True);
				}
			}

			$var = Array(
				'new_action'	=> $GLOBALS['egw']->link($this->base_url,Array('menuaction'=>'calendar.uiholiday.edit_holiday','locale'=>$this->bo->locales[0],'id'=>0,'year'=>$this->bo->year)),
				'lang_add'		=> lang('add'),
				'back_action'	=> $GLOBALS['egw']->link($this->base_url,Array('menuaction'=>'calendar.uiholiday.admin')),
				'lang_back'		=> lang('Back'),
				'search_action'=> $GLOBALS['egw']->link($this->base_url,Array('menuaction'=>'calendar.uiholiday.edit_locale','locale'=>$this->bo->locales[0],'year'=>$this->bo->year)),
				'lang_search'	=> lang('search')
			);
			$p->set_var($var);
			$p->parse('back_button','back_button_form',False);
			$p->pparse('out','list');
		}

		function copy_holiday()
		{
			if(@$this->bo->id)
			{
				$holiday = $this->bo->read_entry($this->bo->id);
			}
			$this->bo->id = 0;

			if (!$holiday['occurence'] || $holiday['occurence'] >= 1900)
			{
				$holiday['occurence'] = date('Y');
			}
			$this->edit_holiday('',$holiday);
		}

		function edit_holiday($error='',$holiday='')
		{
			if(@$this->bo->id && !$holiday)
			{
				$holiday = $this->bo->read_entry($this->bo->id);
			}
			if ($this->bo->locale)
			{
				$holiday['locale'] = $this->bo->locale;
			}
			unset($GLOBALS['egw_info']['flags']['noheader']);
			unset($GLOBALS['egw_info']['flags']['nonavbar']);
			$GLOBALS['egw_info']['flags']['noappfooter'] = True;
			$GLOBALS['egw_info']['flags']['app_header'] = $GLOBALS['egw_info']['apps']['calendar']['title'].' - '.($this->bo->id ? lang('Edit') : lang('Add')).' '.lang('Holiday');
			common::egw_header();

			$t = &$GLOBALS['egw']->template;
			$t->set_file(Array('holiday'=>'holiday.tpl','form_button'=>'form_button_script.tpl'));
			$t->set_block('holiday','form','form');
			$t->set_block('holiday','list','list');

			if (@count($error))
			{
				$message = common::error_list($error);
			}
			else
			{
				$message = '';
			}

			$var = Array(
				'title_holiday' => ($this->bo->id ? lang('Edit') : lang('Add')).' '.lang('Holiday'),
				'message'       => $message,
				'actionurl'     => $GLOBALS['egw']->link($this->base_url,'menuaction=calendar.boholiday.add&year='.$this->bo->year),
				'hidden_vars'   => '<input type="hidden" name="holiday[hol_id]" value="'.$this->bo->id.'">'."\n"
					. '<input type="hidden" name="holiday[locales]" value="'.$this->bo->locales[0].'">'."\n"
			);
			$t->set_var($var);

// Locale
			$this->display_item($t,lang('Country'),$this->sb->form_select($holiday['locale'],'holiday[locale]'));

// Title/Name
			$this->display_item($t,lang('title'),'<input name="holiday[name]" size="60" maxlength="50" value="'.$holiday['name'].'">');

// Date
			$this->display_item($t,lang('Date'),common::dateformatorder($this->sb->getYears('holiday[year]',$holiday['occurence']>1900?$holiday['occurence']:0),$this->sb->getMonthText('holiday[month_num]',$holiday['month']),$this->sb->getDays('holiday[mday]',$holiday['day'])).
				'&nbsp;'.lang('Set a Year only for one-time / non-regular holidays.'));

// Occurence
			$occur = Array(
				0	=> '',
				1	=> '1.',
				2	=> '2.',
				3	=> '3.',
				4	=> '4.',
				5	=> '5.',
				99	=> lang('Last')
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
			$this->display_item($t,lang('Occurence'),$occurence_html.'&nbsp;'.$dow_html.
				'&nbsp;'.lang('You can either set a Year or a Occurence, not both !!!'));
			$this->display_item($t,lang('Observance Rule'),'<input type="checkbox" name="holiday[observance_rule]" value="True"'.($holiday['observance_rule']?' checked':'').'>'.
				'&nbsp;'.lang('If checked holidays falling on a weekend, are taken on the monday after.'));

			$t->set_var('lang_add',lang('Save'));
			$t->set_var('lang_reset',lang('Reset'));

			if(@$this->bo->locales[0])
			{
				$link_params = Array(
					'menuaction'	=> 'calendar.uiholiday.edit_locale',
					'year'			=> $this->bo->year,
					'locale'		=> $this->bo->locales[0]
				);
			}
			else
			{
				$link_params = Array(
					'menuaction'	=> 'calendar.uiholiday.admin'
				);
			}

			$t->set_var(Array(
				'action_url_button'	=> $GLOBALS['egw']->link($this->base_url,$link_params),
				'action_text_button'	=> lang('Cancel'),
				'action_confirm_button'	=> '',
				'action_extra_field'	=> ''
			));
			$t->parse('cancel_button','form_button');

			if ($this->bo->id)
			{
				$link_params = Array(
					'menuaction'	=> 'calendar.uiholiday.delete_holiday',
					'year'			=> $this->bo->year,
					'locale'		=> $this->bo->locales[0],
					'id'			=> $this->bo->id
				);
				$t->set_var(Array(
					'action_url_button'	=> $GLOBALS['egw']->link($this->base_url,$link_params),
					'action_text_button'	=> lang('Delete'),
					'action_confirm_button'	=> '',
					'action_extra_field'	=> ''
				));
				$t->parse('delete_button','form_button');
			}
			else
			{
				$t->set_var('delete_button','&nbsp;');
			}
			$t->pparse('out','form');
		}


		function delete_locale()
		{
			$this->admin();

			$p =& CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_file(Array('form'=>'delete_common.tpl','form_button'=>'form_button_script.tpl'));

			$p->set_var('messages',lang('Are you sure you want to delete this Country ?')."<br>".$this->bo->locales[0]);

			$var = Array(
				'action_url_button'	=> $GLOBALS['egw']->link($this->base_url,Array('menuaction'=>'calendar.uiholiday.admin')),
				'action_text_button'	=> lang('No'),
				'action_confirm_button'	=> '',
				'action_extra_field'	=> ''
			);
			$p->set_var($var);
			$p->parse('no','form_button');

			$var = Array(
				'action_url_button'	=> $GLOBALS['egw']->link($this->base_url,Array('menuaction'=>'calendar.boholiday.delete_locale','locale'=>$this->bo->locales[0])),
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
				return $this->edit_locale();
			}

			unset($GLOBALS['egw_info']['flags']['noheader']);
			unset($GLOBALS['egw_info']['flags']['nonavbar']);
			$GLOBALS['egw_info']['flags']['noappfooter'] = True;
			common::egw_header();

			$p =& CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_file(Array('form'=>'delete_common.tpl','form_button'=>'form_button_script.tpl'));

			$p->set_var('messages',lang('Are you sure you want to delete this holiday ?')."<br>".$holiday['name'].' ('.$this->bo->locales[0].') '.$this->bo->rule_string($holiday));

			$var = Array(
				'action_url_button'	=> $GLOBALS['egw']->link($this->base_url,Array('menuaction'=>'calendar.uiholiday.edit_locale','locale'=>$this->bo->locales[0],'year'=>$this->bo->year)),
				'action_text_button'	=> lang('No'),
				'action_confirm_button'	=> '',
				'action_extra_field'	=> ''
			);
			$p->set_var($var);
			$p->parse('no','form_button');

			$var = Array(
				'action_url_button'	=> $GLOBALS['egw']->link($this->base_url,Array('menuaction'=>'calendar.boholiday.delete_holiday','locale'=>$this->bo->locales[0],'id'=>$this->bo->id,'year'=>$this->bo->year)),
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
				return $this->admin();
			}
			$this->bo->year = 0;	// for a complete list with all years
			$holidays = $this->bo->get_holiday_list();

			if (!is_array($holidays) || !count($holidays))
			{
				$this->admin();
			}
			// sort holidays by year / occurence:
			usort($holidays,'_holiday_cmp');

			if (isset($_GET['download']))
			{
				$locale = $this->bo->locales[0];
				html::content_header("holidays.$locale.csv",'text/text');

				echo "charset\t".translation::charset()."\n";
				$last_year = -1;
				foreach($holidays as $holiday)
				{
					$year = $holiday['occurence'] <= 0 ? 0 : $holiday['occurence'];
					if ($year != $last_year)
					{
						echo "\n".($year ? $year : 'regular (year=0)').":\n";
						$last_year = $year;
					}
					echo "$locale\t$holiday[name]\t$holiday[day]\t$holiday[month]\t$holiday[occurence]\t$holiday[dow]\t$holiday[observance_rule]\n";
				}
				common::egw_exit();
			}
			if($this->debug)
			{
				$action = $GLOBALS['egw']->link('/calendar/egroupware.org/accept_holiday.php');
			}
			else
			{
				$action = 'http://www.egroupware.org/cal/accept_holiday.php';
			}
			$GLOBALS['egw_info']['flags']['noappheader']	= True;
			$GLOBALS['egw_info']['flags']['noappfooter'] = True;
			$GLOBALS['egw_info']['flags']['nofooter'] = True;
			common::egw_header();

			echo '<body onLoad="document.submitform.submit()">'."\n";
			echo '<form action="'.$action.'" method="post" name="submitform">'."\n";

			echo '<input type="hidden" name="locale" value="'.$this->bo->locales[0].'">'."\n";
			echo '<input type="hidden" name="charset" value="'.translation::charset().'">'."\n";
			foreach($holidays as $holiday)
			{
				echo '<input type="hidden" name="name[]" value="'.htmlspecialchars($holiday['name']).'">'."\n"
					. '<input type="hidden" name="day[]" value="'.$holiday['day'].'">'."\n"
					. '<input type="hidden" name="month[]" value="'.$holiday['month'].'">'."\n"
					. '<input type="hidden" name="occurence[]" value="'.$holiday['occurence'].'">'."\n"
					. '<input type="hidden" name="dow[]" value="'.$holiday['dow'].'">'."\n"
					. '<input type="hidden" name="observance[]" value="'.$holiday['observance_rule'].'">'."\n\n";
			}
			echo "</form>\n</body>\n</head>";
		}

		/* private functions */
		function display_item(&$p,$field,$data)
		{
			$var = Array(
				'tr_color' => $GLOBALS['egw']->nextmatchs->alternate_row_color(),
				'field'	=> $field,
				'data'	=> $data
			);
			$p->set_var($var);
			$p->parse('rows','list',True);
		}
	}
?>
