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
	@class datefield_widget
	@author ralfbecker
	@abstract widget that reads a date
	@discussion This widget is independent of the UI as it only uses etemplate-widgets and has therefor no render-function
	*/
	class date_widget
	{
		var $public_functions = array(
			'pre_process' => True,
			'post_process' => True
		);
		var $human_name = 'Date';	// this is the name for the editor

		function date_widget($ui)
		{
		}

		function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
		{
			list($data_format,$options) = explode(',',$cell['size']);
			$extension_data = $data_format;

			if (!$value)
			{
				$value = array(
					'Y' => '',
					'm' => '',
					'd' => ''
				);
			}
			elseif ($data_format != '')
			{
				$date = split('[/.-]',$value);
				$mdy  = split('[/.-]',$data_format);
				for ($value=array(),$n = 0; $n < 3; ++$n)
				{
					switch($mdy[$n])
					{
						case 'Y': $value['Y'] = $date[$n]; break;
						case 'm': $value['m'] = $date[$n]; break;
						case 'd': $value['d'] = $date[$n]; break;
					}
				}
			}
			else
			{
				$value = array(
					'Y' => date('Y',$value),
					'm' => date('m',$value),
					'd' => date('d',$value)
				);
			}
			$tpl = new etemplate;
			$tpl->init('*** generated fields for date','','',0,'',0,0);	// make an empty template

			$format = split('[/.-]',$GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat']);
			$fields = array('Y' => 'year', 'm' => 'month', 'd' => 'day');
			$row = array();
			for ($n=0; $n < 3; ++$n)
			{
				$dcell = $tpl->empty_cell();
				$dcell['type'] = 'select-'.$fields[$format[$n]];
				$dcell['name'] = $format[$n];
				$dcell['help'] = lang($fields[$format[$n]]).': '.$cell['help'];	// note: no lang on help, already done
				$dcell['no_lang'] = True;
				$row[$tpl->num2chrs($n)] = &$dcell;
				unset($dcell);
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

		function post_process($name,&$value,&$extension_data,&$loop,&$tmpl)
		{
			if (!isset($value))
			{
				return False;
			}
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
				if (empty($extension_data))
				{
					$value = mktime(0,0,0,$value['m'],$value['d'],$value['Y']);
				}
				else
				{
					for ($n = 0,$str = ''; $n < strlen($extension_data); ++$n)
					{
						if (strstr('Ymd',$c = $extension_data[$n]))
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