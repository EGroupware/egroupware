<?php
	/**************************************************************************\
	* phpGroupWare - eTemplate Extension - Nextmatch Widget                    *
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
	@class nextmatch_widget
	@author ralfbecker
	@abstract Widget that show only a certain number of data-rows and allows to modifiy the rows shown (scroll).
	@discussion This widget replaces the old nextmatch-class
	@discussion This widget is independent of the UI as it only uses etemplate-widgets and has therefor no render-function
	*/
	class nextmatch_widget
	{
		var $public_functions = array(
			'pre_process' => True,
			'post_process' => True
		);
		var $human_name = 'Nextmatch';	// this is the name for the editor

		function nextmatch_widget($ui)
		{
		}

		function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
		{
			//echo "<p>nextmatch_widget.pre_process: value = "; _debug_array($value);

			list($app,$class,$method) = explode('.',$value['get_rows']);
			$obj = CreateObject($app.'.'.$class);
			if (!is_object($obj))
			{
				echo "<p>nextmatch_widget::pre_process($name): '$value[get_rows]' is no valid method !!!</p>\n";
				//return;
			}
			else
			{
				$total = $value['total'] = $obj->$method($value,$value['rows'],$readonlys['rows']);
			}
			if ($value['start'] > $total)
			{
				$value['start'] = 0;
				$total = $obj->$method($value,$value['rows'],$readonlys['rows']);
			}
			list($template,$options) = explode(',',$cell['size']);
			if ($template)	// template name can be supplied either in $value['template'] or the options-field
			{
				$value['template'] = $template;
			}
			if (!is_object($value['template']))
			{
				$value['template'] = new etemplate($value['template'],$tmpl->as_array());
			}
			if ($total < 1)
			{
				$value['template']->data[0]['h2'] = ',1';	// disable the data row
			}
			$max   = $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs'];
			if ($total <= $max && $options && $value['search'] == '' &&
				 ($value['no_cat'] || !$value['cat_id']) &&
				 ($value['no_filter'] || !$value['filter'] || $value['filter'] == 'none') &&
				 ($value['no_filter2'] || !$value['filter2'] || $value['filter2'] == 'none'))
			{											// disable whole nextmatch line if no scrolling necessary
				if ($value['header_left'] || $value['header_right'])
				{
					$nextmatch = new etemplate('etemplate.nextmatch_widget.header_only');
					$cell['size'] = $cell['name'];
					$cell['obj'] = &$nextmatch;
					$cell['name'] = $nextmatch->name;
				}
				else
				{
					$cell['size'] = $cell['name'].'[rows]';
					$cell['obj'] = &$value['template'];
					$cell['name'] = $value['template']->name;
				}
			}
			else
			{
				$nextmatch = new etemplate('etemplate.nextmatch_widget');

				if ($value['no_cat'])
				{
					$nextmatch->disable_cells('cat_id');
				}
				if ($value['no_filter'])
				{
					$nextmatch->disable_cells('filter');
				}
				if ($value['no_filter2'])
				{
					$nextmatch->disable_cells('filter2');
				}
				$start = $value['start'];
				$end   = $start+$max > $total ? $total : $start+$max;
				$value['range'] = $total ? (1+$start) . ' - ' . $end : '0';
				$nextmatch->set_cell_attribute('first','readonly',$start <= 0);
				$nextmatch->set_cell_attribute('left', 'readonly',$start <= 0);
				$nextmatch->set_cell_attribute('right','readonly',$start+$max >= $total);
				$nextmatch->set_cell_attribute('last', 'readonly',$start+$max >= $total);

				$cell['size'] = $cell['name'];
				$cell['obj'] = &$nextmatch;
				$cell['name'] = $nextmatch->name;
			}
			$cell['type'] = 'template';
			$cell['label'] = $cell['help'] = '';

			// save values in persistent extension_data to be able use it in post_process
			$extension_data = $value;
			
			$value['bottom'] = $value;	// copy the values for the bottom-bar

			return False;	// NO extra Label
		}

		function post_process($name,&$value,&$extension_data,&$loop,&$tmpl)
		{
			//echo "<p>nextmatch_widget.post_process: value = "; _debug_array($value);
			$old_value = $extension_data;

			$max   = $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs'];
			$loop = False;
			$value['start'] = $old_value['start'];	// need to be set, to be reported back

			if (is_array($value['bottom']))			// we have a second bottom-bar
			{
				$inputs = array('search','cat_id','filter','filter2');
				foreach($inputs as $name)
				{
					if (isset($value['bottom'][$name]) && $value['bottom'][$name] != $old_value[$name])
					{
						$value[$name] = $value['bottom'][$name];
					}
				}
				$buttons = array('start_search','first','left','right','last');
				foreach($buttons as $name)
				{
					if (isset($value['bottom'][$name]) && $value['bottom'][$name])
					{
						$value[$name] = $value['bottom'][$name];
					}
				}
				unset($value['bottom']);
			}
			if ($value['start_search'] ||
			    isset($value['cat_id']) && $value['cat_id'] != $old_value['cat_id'] ||
			    $old_value['filter'] != '' && isset($value['filter']) && $value['filter'] != $old_value['filter'] ||
			    $old_value['filter2'] != '' && isset($value['filter2']) && $value['filter2'] != $old_value['filter2'])
			{
				//echo "<p>search='$old_value[search]'->'$value[search]', filter='$old_value[filter]'->'$value[filter]', filter2='$old_value[filter2]'->'$value[filter2]'<br>";
				//echo "new filter --> loop</p>";
				//echo "value ="; _debug_array($value);
				//echo "old_value ="; _debug_array($old_value);
				$loop = True;
			}
			elseif ($value['first'])
			{
				$value['start'] = 0;
				$loop = True;
			}
			elseif ($value['left'])
			{
				$value['start'] = $old_value['start'] - $max;
				$loop = True;
			}
			elseif ($value['right'])
			{
				$value['start'] = $old_value['start'] + $max;
				$loop = True;
			}
			elseif ($value['last'])
			{
				$value['start'] = (int) (($old_value['total']-2) / $max) * $max;
				$loop = True;
			}
			return True;
		}
	}
