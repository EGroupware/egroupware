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
	@abstract widget that reads dates in via 3 select-boxes
	@note This widget is generates html vi the sbox-class, so it does not work (without an extra implementation) in an other UI
	*/
	class date_widget
	{
		var $public_functions = array(
			'pre_process' => True,
			'render' => True,
			'post_process' => True
		);
		var $human_name = 'Date';	// this is the name for the editor

		function date_widget($ui)
		{
			switch($ui)
			{
				case '':
				case 'html':
					$this->ui = 'html';
					break;
				case 'gtk':
					$this->ui = 'gtk';
					break;
				default:
					return "UI='$ui' not implemented";
			}
			return 0;
		}

		function pre_process($cell,&$value)
		{
			if ($cell['size'] != '')
			{
				$date = split('[/.-]',$value);
				$mdy  = split('[/.-]',$cell['size']);
				for ($value=array(),$n = 0; $n < 3; ++$n)
				{
					switch($mdy[$n])
					{
						case 'Y': $value[0] = $date[$n]; break;
						case 'm': $value[1] = $date[$n]; break;
						case 'd': $value[2] = $date[$n]; break;
					}
				}
			}
			else
			{
				$value = array(date('Y',$value),date('m',$value),date('d',$value));
			}
			return True;	// extra Label is ok
		}

		function render($cell,$form_name,$value,$readonly)
		{
			$func = 'render_'.$this->ui;

			return $this->$func($cell,$form_name,$value,$readonly);
		}

		function post_process($cell,&$value)
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
				if ($cell['size'] == '')
				{
					$value = mktime(0,0,0,$value['m'],$value['d'],$value['Y']);
				}
				else
				{
					for ($n = 0,$str = ''; $n < strlen($cell['size']); ++$n)
					{
						if (strstr('Ymd',$c = $cell['size'][$n]))
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

		function render_html($cell,$form_name,$value,$readonly)
		{
			if ($readonly)
			{
				return $GLOBALS['phpgw']->common->dateformatorder($value[0],$value[1],$value[2],True);
			}
			return $this->et->sbox->getDate($form_name.'[Y]',$form_name.'[m]',$form_name.'[d]',$value,$options);
		}
	}