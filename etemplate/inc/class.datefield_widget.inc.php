<?php
	/**************************************************************************\
	* phpGroupWare - eTemplate Extension - DateField Widget                    *
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
	@abstract widget that reads date in via 3 input-fields
	@discussion This widget is independent of the UI as it only uses etemplate-widgets and has therefor no render-function
	*/
	class datefield_widget
	{
		var $public_functions = array(
			'pre_process' => True,
			'post_process' => True
		);
		var $human_name = 'DateField';	// this is the name for the editor

		function datefield_widget($ui)
		{
		}

		function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
		{
			$extension_data = $cell['size'];

			if ($cell['size'] != '')
			{
				$date = split('[/.-]',$value);
				$mdy  = split('[/.-]',$cell['size']);
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
			$format = split('[/.-]',$GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat']);
			$value = array(
				'f1' => $value[$format[0]],
				'f2' => $value[$format[1]],
				'f3' => $value[$format[2]],
				'sep' => $GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat'][1],
				'help' => $cell['help']
			);
			$cell['size'] = $cell['name'];
			$cell['type'] = 'template';
			$cell['name'] = 'etemplate.datefield';

			return True;	// extra Label is ok
		}

		function post_process($name,&$value,&$extension_data,&$loop,&$tmpl)
		{
			if (!isset($value))
			{
				return False;
			}
			$format = split('[/.-]',$GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat']);

			$value = array(
				$format[0] => $value['f1'],
				$format[1] => $value['f2'],
				$format[2] => $value['f3']
			);
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