<?php
	/**************************************************************************\
	* phpGroupWare - eTemplate Extension - Date Widget                         *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	/*!
	@class date_widget
	@author ralfbecker
	@abstract widget that reads a date and/or time 
	@param Options/$cell['size'] = $format[,$options], 
	@param $format: ''=timestamp or eg. 'Y-m-d H:i' for 2002-12-31 23:59
	@param $options: &1 = year is int-input not selectbox, &2 = show a [Today] button, \
		&4 = 1min steps for time (default is 5min, with fallback to 1min if value is not in 5min-steps)
	@discussion This widget is independent of the UI as it only uses etemplate-widgets and has therefor no render-function
	*/
	class date_widget
	{
		var $public_functions = array(
			'pre_process' => True,
			'post_process' => True
		);
		var $human_name = array(
			'date'      => 'Date',		// just a date, no time
			'date-time' => 'Date+Time',	// date + time
			'date-timeonly' => 'Time'
		);

		function date_widget($ui)
		{
			if ($ui == 'html')
			{
				$this->jscal = CreateObject('phpgwapi.jscalendar');
			}
			$this->timeformat = $GLOBALS['phpgw_info']['user']['preferences']['common']['timeformat'];
		}

		function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
		{
			list($data_format,$options) = explode(',',$cell['size']);
			$extension_data = $data_format;
			$type = $cell['type'];

			if (!$value)
			{
				$value = array(
					'Y' => '',
					'm' => '',
					'd' => '',
					'H' => '',
					'i' => ''
				);
			}
			elseif ($data_format != '')
			{
				$date = split('[- /.:,]',$value);
				//echo "date=<pre>"; print_r($date); echo "</pre>";
				$mdy  = split('[- /.:,]',$data_format);
				$value = array();
				foreach ($date as $n => $dat)
				{
					switch($mdy[$n])
					{
						case 'Y': $value['Y'] = $dat; break;
						case 'm': $value['m'] = $dat; break;
						case 'd': $value['d'] = $dat; break;
						case 'H': $value['H'] = $dat; break;
						case 'i': $value['i'] = $dat; break;
					}
				}
			}
			else
			{
				$value += $GLOBALS['phpgw']->datetime->tz_offset;
				$value = array(
					'Y' => date('Y',$value),
					'm' => date('m',$value),
					'd' => date('d',$value),
					'H' => date('H',$value),
					'i' => date('i',$value)
				);
			}
			$timeformat = array(3 => 'H', 4 => 'i');
			if ($this->timeformat == '12')
			{
				$value['a'] = $value['H'] < 12 ? 'am' : 'pm';
				
				if ($value['H'] > 12)
				{
					$value['H'] -= 12; 
				}
				$timeformat += array(5 => 'a');
			}
			$format = split('[/.-]',$sep=$GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat']);
			
			if ($type != 'date')
			{
				$format += $timeformat;
			}
			if ($cell['readonly'] || $readonlys)	// is readonly
			{
				$sep = array(
					1 => $sep[1],
					2 => $sep[1],
					3 => ' ',
					4 => ':'
				);
				for ($str='',$n = $type == 'date-timeonly' ? 3 : 0; $n < count($format); ++$n)
				{
					$str .= ($str != '' ? $sep[$n] : '');
					$str .= $value[$format[$n]];
				}
				$value = $str;
				$cell['type'] = 'label';
				$cell['no_lang'] = True;
				return True;
			}
			$tpl = new etemplate;
			$tpl->init('*** generated fields for date','','',0,'',0,0);	// make an empty template

			$types = array(
				'Y' => ($options&1 ? 'int' : 'select-year'),	// if options&1 set, show an int-field
				'm' => 'select-month',
				'd' => 'select-day',
				'H' => 'select-number',
				'i' => 'select-number'
			);
			$opts = array(
				'H' => $this->timeformat == '12' ? ',0,12' : ',0,23,01',
				'i' => $value['i'] % 5 || $options & 4 ? ',0,59,01' : ',0,59,05' // 5min steps, if ok with value
			);
			$help = array(
				'Y' => 'Year',
				'm' => 'Month',
				'd' => 'Day',
				'H' => 'Hour',
				'i' => 'Minute'
			);
			$row = array();
			for ($i=0,$n=$type == 'date-timeonly'?3:0; $n < ($type == 'date' ? 3 : 5); ++$n,++$i)
			{
				$dcell = $tpl->empty_cell();
				// test if we can use jsCalendar
				if ($n == 0 && $this->jscal && $tmpl->java_script())
				{
					$dcell['type'] = 'html';
					$dcell['name'] = 'str';
					$value['str'] = $this->jscal->input($name.'[str]',False,$value['Y'],$value['m'],$value['d'],lang($cell['help']));
					$n = 2;				// no other fields
					$options &= ~2;		// no set-today button
					// register us for process_exec
					$GLOBALS['phpgw_info']['etemplate']['to_process'][$name] = 'ext-'.$cell['type'];
				}
				else
				{
					$dcell['type'] = $types[$format[$n]];
					$dcell['size'] = $opts[$format[$n]];
					$dcell['name'] = $format[$n];
					$dcell['help'] = lang($help[$format[$n]]).': '.lang($cell['help']);	// note: no lang on help, already done
				}
				if ($n == 4)
				{
					$dcell['label'] = ':';	// put a : between hour and minute
				}
				$dcell['no_lang'] = True;
				$row[$tpl->num2chrs($i)] = &$dcell;
				unset($dcell);
				
				if ($n == 2 && ($options & 2))	// Today button
				{
					$dcell = $tpl->empty_cell();
					$dcell['name'] = 'today';
					$dcell['label'] = 'Today';
					$dcell['help'] = 'sets today as date';
					if (($js = $tmpl->java_script()))
					{
						$dcell['needed'] = True;	// to get a button
						$dcell['onchange'] = "this.form.elements['$name"."[Y]'].value='".date('Y')."'; this.form.elements['$name"."[m]'].value='".date('n')."';this.form.elements['$name"."[d]'].value='".(0+date('d'))."'; return false;";
					}
					$dcell['type'] = $js ? 'button' : 'checkbox';
					$row[$tpl->num2chrs(++$i)] = &$dcell;
					unset($dcell);
				}
				if ($n == 2 && $type == 'date-time')	// insert some space between date+time
				{
					$dcell = $tpl->empty_cell();
					$dcell['type'] = 'html';
					$dcell['name'] = 'space';
					$value['space'] = ' &nbsp; &nbsp; ';
					$dcell['no_lang'] = True;
					$row[$tpl->num2chrs(++$i)] = &$dcell;
					unset($dcell);
				}
				if ($n == 4 && $type != 'date' && $this->timeformat == '12')
				{
					$dcell = $tpl->empty_cell();
					$dcell['type'] = 'radio';
					$dcell['name'] = 'a';
					$dcell['help'] = $cell['help'];
					$dcell['size'] = $dcell['label'] = 'am';
					$row[$tpl->num2chrs(++$i)] = $dcell;
					$dcell['size'] = $dcell['label'] = 'pm';
					$row[$tpl->num2chrs(++$i)] = &$dcell;
					unset($dcell);
				}
			}
			$tpl->data[0] = array();
			$tpl->data[1] = &$row;
			$tpl->set_rows_cols();
			$tpl->size = ',,,,0';

			$cell['size'] = $cell['name'];
			$cell['type'] = 'template';
			$cell['name'] = $tpl->name;
			$cell['obj'] = &$tpl;

			return True;	// extra Label is ok
		}

		function post_process($name,&$value,&$extension_data,&$loop,&$tmpl,$value_in)
		{
			//echo "<p>date_widget::post_process('$name','$extension_data') value="; print_r($value); echo ", value_in="; print_r($value_in); echo "</p>\n";
			if (!isset($value) && !isset($value_in))
			{
				return False;
			}
			if ($value['today'])
			{
				$set = array('Y','m','d');
				foreach($set as $d)
				{
					$value[$d] = date($d);
				}
			}
			if (isset($value_in['str']) && !empty($value_in['str']))
			{
				if (!is_array($value))
				{
					$value = array();
				}
				$value += $this->jscal->input2date($value_in['str'],False,'d','m','Y');
			}
			if ($value['d'] || isset($value['H']) && $value['H'] !== '' ||
			                   isset($value['i']) && $value['i'] !== '')
			{
				if ($value['d'])
				{
					if (!$value['m'])
					{
						$value['m'] = date('m');
					}
					if (!$value['Y'])
					{
						$value['Y'] = date('Y');
					}
					elseif ($value['Y'] < 100)
					{
						$value['Y'] += $value['Y'] < 30 ? 2000 : 1900;
					}
				}
				else	// for the timeonly field
				{
					$value['d'] = $value['m'] = 1;
					$value['Y'] = 1970;
				}
				if (isset($value['a']))
				{
					if ($value['a'] == 'pm' && $value['H'] < 12)
					{
						$value['H'] += 12;
					}
				}
				if (empty($extension_data))
				{
					$value = mktime(intval($value['H']),intval($value['i']),0,$value['m'],$value['d'],$value['Y']) 
						- $GLOBALS['phpgw']->datetime->tz_offset;
				}
				else
				{
					for ($n = 0,$str = ''; $n < strlen($extension_data); ++$n)
					{
						if (strstr('YmdHi',$c = $extension_data[$n]))
						{
							$str .= sprintf($c=='Y'?'%04d':'%02d',$value[$c]);
						}
						else
						{
							$str .= $c;
						}
					}
					$value = $str;
				}
			}
			else
			{
				$value = '';
			}
			return True;
		}
	}
