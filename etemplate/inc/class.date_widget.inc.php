<?php
	/**************************************************************************\
	* eGroupWare - eTemplate Extension - Date Widget                           *
	* http://www.egroupware.org                                                *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	/**
	 * eTemplate extension to input or display date and/or time values
	 *
	 * Contains the following widgets: Date, Date+Time, Time, Hour, Duration
	 *
	 * Supported attributes: format[,options]
	 *  format: ''=timestamp, or eg. 'Y-m-d H:i' for 2002-12-31 23:59
	 *  options: &1 = year is int-input not selectbox, &2 = show a [Today] button, (html-UI always uses jscal and dont care for &1+&2)
	 *           &4 = 1min steps for time (default is 5min, with fallback to 1min if value is not in 5min-steps),
	 *           &8 = dont show time for readonly and type date-time if time is 0:00, 
	 *           &16 = prefix r/o display with dow
	 *
	 * This widget is independent of the UI as it only uses etemplate-widgets and has therefor no render-function.
	 * Uses the adodb datelibary to overcome the windows-limitation to not allow dates before 1970
	 *
	 * @package etemplate
	 * @subpackage extensions
	 * @author RalfBecker-AT-outdoor-training.de
	 * @license GPL
	 */
	class date_widget
	{
		/** 
		 * exported methods of this class
		 * @var array
		 */
		var $public_functions = array(
			'pre_process' => True,
			'post_process' => True
		);
		/**
		 * availible extensions and there names for the editor
		 * @var array
		 */
		var $human_name = array(
			'date'      => 'Date',		// just a date, no time
			'date-time' => 'Date+Time',	// date + time
			'date-timeonly' => 'Time',	// time
			'date-houronly' => 'Hour',	// hour
			'date-duration' => 'Duration', // duration
		);
		var $dateformat;	// eg. Y-m-d, d-M-Y
		var $timeformat;	// 12 or 24

		/**
		 * Constructor of the extension
		 *
		 * @param string $ui '' for html
		 */
		function date_widget($ui)
		{
			if ($ui == 'html')
			{
				if (!is_object($GLOBALS['egw']->jscalendar))
				{
					$GLOBALS['egw']->jscalendar =& CreateObject('phpgwapi.jscalendar');
				}
				$this->jscal =& $GLOBALS['egw']->jscalendar;
			}
			$this->timeformat = $GLOBALS['egw_info']['user']['preferences']['common']['timeformat'];
			$this->dateformat = $GLOBALS['egw_info']['user']['preferences']['common']['dateformat'];
		}

		/**
		 * pre-processing of the extension
		 *
		 * This function is called before the extension gets rendered
		 *
		 * @param string $name form-name of the control
		 * @param mixed &$value value / existing content, can be modified
		 * @param array &$cell array with the widget, can be modified for ui-independent widgets 
		 * @param array &$readonlys names of widgets as key, to be made readonly
		 * @param mixed &$extension_data data the extension can store persisten between pre- and post-process
		 * @param object &$tmpl reference to the template we belong too
		 * @return boolean true if extra label is allowed, false otherwise
		 */
		function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
		{
			$type = $cell['type'];
			if ($type == 'date-duration')
			{
				return $this->pre_process_duration($name,$value,$cell,$readonlys,$extension_data,$tmpl);
			}
			list($data_format,$options,$options2) = explode(',',$cell['size']);
			if ($type == 'date-houronly' && empty($data_format)) $data_format = 'H';
			$extension_data = array(
				'type'			=> $type,
				'data_format'	=> $data_format,
			);	
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
				// for the timeformats we use only seconds, no timezone conversation between server-time and UTC
				if (substr($type,-4) == 'only') $value -= adodb_date('Z',0);

				$value = array(
					'Y' => adodb_date('Y',$value),
					'm' => adodb_date('m',$value),
					'M' => substr(lang(adodb_date('F',$value)),0,3),
					'd' => adodb_date('d',$value),
					'H' => adodb_date('H',$value),
					'i' => adodb_date('i',$value)
				);
			}
			$time_0h0 = !(int)$value['H'] && !(int)$value['i'];

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
			$format = split('[/.-]',$this->dateformat);
			
			$readonly = $cell['readonly'] || $readonlys;

			// no time also if $options&8 and readonly and time=0h0
			if ($type != 'date' && !($readonly && ($options & 8) && $time_0h0))
			{
				$format += $timeformat;
			}
			if ($readonly)	// is readonly
			{
				$sep = array(
					1 => $this->dateformat[1],
					2 => $this->dateformat[1],
					3 => ' ',
					4 => ':'
				);
				for ($str='',$n = substr($type,-4) == 'only' ? 3 : 0; $n < count($format); ++$n)
				{
					if ($value[$format[$n]])
					{
						if (!$n && $options & 16 )
						{
							$str = lang(adodb_date('l',adodb_mktime(12,0,0,$value['m'],$value['d'],$value['Y']))).' ';
						}
						$str .= ($str != '' ? $sep[$n] : '') . $value[$format[$n]];
					}
					if ($type == 'date-houronly') ++$n;	// no minutes
				}
				$value = $str;
				$cell['type'] = 'label';
				if (!$cell['no_lang'])
				{
					$cell['no_lang'] = True;
					$cell['label'] = strlen($cell['label']) > 1 ? lang($cell['label']) : $cell['label'];
				}
				unset($cell['size']);
				return True;
			}
			if ($cell['needed'])
			{
				$GLOBALS['egw_info']['etemplate']['to_process'][$name] = array(
					'type' => 'ext-'.$type,
					'needed' => $cell['needed'],
				);
			}
			$tpl =& new etemplate;
			$tpl->init('*** generated fields for date','','',0,'',0,0);	// make an empty template
			// keep the editor away from the generated tmpls
			$tpl->no_onclick = true;

			$types = array(
				'Y' => ($options&1 ? 'int' : 'select-year'),	// if options&1 set, show an int-field
				'm' => 'select-month',
				'M' => 'select-month',
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
				'M' => 'Month',
				'd' => 'Day',
				'H' => 'Hour',
				'i' => 'Minute'
			);
			$row = array();
			for ($i=0,$n= substr($type,-4) == 'only' ? 3 : 0; $n < ($type == 'date' ? 3 : 5); ++$n,++$i)
			{
				$dcell = $tpl->empty_cell();
				if ($cell['tabindex']) $dcell['tabindex'] = $cell['tabindex'];
				if (!$i && $cell['accesskey']) $dcell['accesskey'] = $cell['accesskey'];
				
				// test if we can use jsCalendar
				if ($n == 0 && $this->jscal && $tmpl->java_script())
				{
					$dcell['type'] = 'html';
					$dcell['name'] = 'str';
					$value['str'] = $this->jscal->input($name.'[str]',False,$value['Y'],$value['m'],$value['d'],lang($cell['help']));
					$n = 2;				// no other fields
					$options &= ~2;		// no set-today button
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
				$dcell['no_lang'] = 2;
				$row[$tpl->num2chrs($i)] = &$dcell;
				unset($dcell);
				
				if ($n == 2 && ($options & 2))	// Today button
				{
					$dcell = $tpl->empty_cell();
					if ($cell['tabindex']) $dcell['tabindex'] = $cell['tabindex'];
					$dcell['name'] = 'today';
					$dcell['label'] = 'Today';
					$dcell['help'] = 'sets today as date';
					$dcell['no_lang'] = True;
					if (($js = $tmpl->java_script()))
					{
						$dcell['needed'] = True;	// to get a button
						$dcell['onchange'] = "this.form.elements['$name"."[Y]'].value='".adodb_date('Y')."'; this.form.elements['$name"."[m]'].value='".adodb_date('n')."';this.form.elements['$name"."[d]'].value='".(0+adodb_date('d'))."'; return false;";
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
					$row[$tpl->num2chrs(++$i)] = &$dcell;
					unset($dcell);
				}
				if ($type == 'date-houronly') $n++;	// no minutes

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

		/**
		 * pre-processing of the duration extension
		 *
		 * Options contain $data_format,$input_format,$hours_per_day,$empty_not_0,$short_labels
		 *  1. data_format: d = days, h = hours, default minutes
		 *	2. input_format: d = days, h = hours, default hours+days (selectbox), optional % = allow to enter a percent value (no conversation)
		 *	3. hours_per_day: default 8 (workday)
		 *  4. should the widget differ between 0 and empty, which get then returned as NULL
		 *  5. short_labels use d/h instead of day/hour
		 *
		 * @param string $name form-name of the control
		 * @param mixed &$value value / existing content, can be modified
		 * @param array &$cell array with the widget, can be modified for ui-independent widgets 
		 * @param array &$readonlys names of widgets as key, to be made readonly
		 * @param mixed &$extension_data data the extension can store persisten between pre- and post-process
		 * @param object &$tmpl reference to the template we belong too
		 * @return boolean true if extra label is allowed, false otherwise
		 */
		function pre_process_duration($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
		{
			//echo "<p>pre_process_duration($name,$value,...) cell[size]='$cell[size]'</p>\n";
			$readonly = $readonlys || $cell['readonly'];
			list($data_format,$input_format,$hours_per_day,$empty_not_0,$short_labels) = explode(',',$cell['size']);
			if (!$hours_per_day) $hours_per_day = 8; // workday is 8 hours
			if (($percent_allowed = strstr($input_format,'%') !== false))
			{
				$input_format = str_replace('%','',$input_format);
			}
			if ($input_format != 'h' && $input_format != 'd') $input_format = 'dh'; // hours + days
			
			$extension_data = array(
				'type'			=> $cell['type'],
				'data_format'	=> $data_format,
				'unit'          => ($unit = $input_format == 'd' ? 'd' : 'h'),
				'input_format'  => $input_format,
				'hours_per_day' => $hours_per_day,
				'percent_allowed'=> $percent_allowed,
				'empty_not_0'   => $empty_not_0,
			);
			if ($value)
			{
				switch($data_format)
				{
					case 'd':
						$value *= $hours_per_day;
						// fall-through
					case 'h': case 'H':
						$value *= 60;
						break;
				}
			}			
			$cell['type'] = 'text';
			$cell['size'] = '4,,/^-?[0-9]*[,.]?[0-9]*'.($percent_allowed ? '%?' : '').'$/';
			$cell_name = $cell['name'];
			$cell['name'] .= '[value]';
			
			if ($input_format == 'dh' && $value >= 60*$hours_per_day)
			{
				$unit = 'd';
			}
			$value = $empty_not_0 && (string) $value === '' || !$empty_not_0 && !$value ? '' : 
				round($value / 60 / ($unit == 'd' ? $hours_per_day : 1),3);

			if (!$readonly && $input_format == 'dh') // selectbox to switch between hours and days
			{
				$value = array(
					'value' => $value,
					'unit'  => $unit,
				);
				$tpl =& new etemplate;
				$tpl->init('*** generated fields for duration','','',0,'',0,0);	// make an empty template
				// keep the editor away from the generated tmpls
				$tpl->no_onclick = true;
				
				$selbox =& $tpl->empty_cell('select',$cell_name.'[unit]');
				$selbox['sel_options'] = array(
					'h' => $short_labels ? 'h' : 'hours',
					'd' => $short_labels ? 'd' : 'days',
				);
				if ($cell['tabindex']) $selbox['tabindex'] = $cell['tabindex'];
				
				$tpl->data[0] = array();
				$tpl->data[1] =array(
					'A' => $cell,
					'B' => $selbox,
				);
				$tpl->set_rows_cols();
				$tpl->size = ',,,,0';
	
				unset($cell['size']);
				$cell['type'] = 'template';
				$cell['name'] = $tpl->name;
				unset($cell['label']);
				$cell['obj'] = &$tpl;
			}
			elseif (!$readonly || $value)
			{
				$cell['no_lang'] = 2;
				$cell['label'] .= ($cell['label'] ? ' ' : '') . '%s '.($unit == 'h' ? ($short_labels ? 'h' : lang('hours')) : 
					($short_labels ? 'd' : lang('days')));
			}
			return True;	// extra Label is ok
		}

		/**
		 * postprocessing method, called after the submission of the form
		 *
		 * It has to copy the allowed/valid data from $value_in to $value, otherwise the widget
		 * will return no data (if it has a preprocessing method). The framework insures that
		 * the post-processing of all contained widget has been done before.
		 *
		 * Only used by select-dow so far
		 *
		 * @param string $name form-name of the widget
		 * @param mixed &$value the extension returns here it's input, if there's any
		 * @param mixed &$extension_data persistent storage between calls or pre- and post-process
		 * @param boolean &$loop can be set to true to request a re-submision of the form/dialog
		 * @param object &$tmpl the eTemplate the widget belongs too
		 * @param mixed &value_in the posted values (already striped of magic-quotes)
		 * @return boolean true if $value has valid content, on false no content will be returned!
		 */
		function post_process($name,&$value,&$extension_data,&$loop,&$tmpl,$value_in)
		{
			//echo "<p>date_widget::post_process('$name','$extension_data[type]','$extension_data[data_format]') value="; print_r($value); echo ", value_in="; print_r($value_in); echo "</p>\n";
			if (!isset($value) && !isset($value_in))
			{
				return False;
			}
			if ($extension_data['type'] == 'date-duration')
			{
				if (is_array($value))	// template with selectbox
				{
					$unit = $value['unit'];
					$value = $value['value'];
				}
				elseif (!preg_match('/^-?[0-9]*[,.]?[0-9]*'.($extension_data['percent_allowed'] ? '%?' : '').'$/',$value_in))
				{
					$GLOBALS['egw_info']['etemplate']['validation_errors'][$name] = lang("'%1' is not a valid floatingpoint number !!!",$value_in);
					return false;
				}
				else
				{
					$value = $value_in;
					$unit = $extension_data['unit'];
				}
				if ($extension_data['percent_allowed'] && substr($value,-1) == '%')
				{
					return true;
				}
				if ($value === '' && $extension_data['empty_not_0'])	// we differ between 0 and empty, which get returned as null
				{
					$value = null;
					return true;
				}
				$value = (int) round(str_replace(',','.',$value) * 60 * ($unit == 'd' ? $extension_data['hours_per_day'] : 1)); 

				switch($extension_data['data_format'])
				{
					case 'd':
						$value /= (float) $extension_data['hours_per_day'];
						// fall-through
					case 'h': case 'H':
						$value /= 60.0;
						break;
				}
				return true;
			}
			$no_date = substr($extension_data['type'],-4) == 'only';

			if ($value['today'])
			{
				$set = array('Y','m','d');
				foreach($set as $d)
				{
					$value[$d] = adodb_date($d);
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
			if ($value['d'] || $no_date && 
				(isset($value['H']) && $value['H'] !== '' || isset($value['i']) && $value['i'] !== ''))
			{
				if ($value['d'])
				{
					if (!$value['m'])
					{
						$value['m'] = adodb_date('m');
					}
					if (!$value['Y'])
					{
						$value['Y'] = adodb_date('Y');
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
				// checking the date is a correct one
				if (!checkdate($value['m'],$value['d'],$value['Y']))
				{
					$GLOBALS['egw_info']['etemplate']['validation_errors'][$name] .= lang("'%1' is not a valid date !!!",
						$GLOBALS['egw']->common->dateformatorder($value['Y'],$value['m'],$value['d'],true));
				}
				$data_format = $extension_data['data_format'];
				if (empty($data_format))
				{
					// for time or hour format we use just seconds (and no timezone correction between server-time and UTC)
					$value = $no_date ? 3600 * (int) $value['H'] + 60 * (int) $value['i'] :
						adodb_mktime((int) $value['H'],(int) $value['i'],0,$value['m'],$value['d'],$value['Y']);
				}
				else
				{
					for ($n = 0,$str = ''; $n < strlen($data_format); ++$n)
					{
						if (strstr('YmdHi',$c = $data_format[$n]))
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
