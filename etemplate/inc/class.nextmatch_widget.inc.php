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

		function pre_process(&$cell,&$value,&$extension_data,&$readonlys,&$tmpl)
		{
			//echo "<p>nextmatch_widget.pre_process: value = "; _debug_array($value);
			// save values in persistent extension_data to be able use it in post_process
			$extension_data = $value;

			list($app,$class,$method) = explode('.',$value['get_rows']);
			$obj = CreateObject($app.'.'.$class);
			$total = $value['total'] = $obj->$method($value,$value['rows'],$readonlys['rows']);
			if ($value['start'] > $total)
			{
				$extension_data['start'] = $value['start'] = 0;
				$total = $obj->$method($value,$value['rows'],$readonlys['rows']);
			}
			$extension_data['total'] = $total;

			if ($cell['size'])
			{
				$value['template'] = $cell['size'];
			}
			$value['template'] = new etemplate($value['template'],$tmpl->as_array());

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
			$max   = $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs'];
			$end   = $start+$max > $total ? $total : $start+$max;
			$value['range'] = (1+$start) . ' - ' . $end;
			$nextmatch->set_cell_attribute('first','readonly',$start <= 0);
			$nextmatch->set_cell_attribute('left', 'readonly',$start <= 0);
			$nextmatch->set_cell_attribute('right','readonly',$start+$max >= $total);
			$nextmatch->set_cell_attribute('last', 'readonly',$start+$max >= $total);

			$cell['type'] = 'template';
			$cell['size'] = $cell['name'];
			$cell['obj'] = &$nextmatch;
			$cell['name'] = $nextmatch->name;
			$cell['label'] = $cell['help'] = '';

			return False;	// NO extra Label
		}

		function post_process(&$cell,&$value,&$extension_data,&$loop,&$tmpl)
		{
			//echo "<p>nextmatch_widget.post_process: value = "; _debug_array($value);

			$old_value = $extension_data;

			list($value['cat_id']) = $value['cat_id'];
			list($value['filter']) = $value['filter'];
			list($value['filter2'])= $value['filter2'];
			$value['start'] = $old_value['start'];	// need to be set, to be reported back
			$max   = $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs'];

			$loop = False;
			if ($value['start_search'] || $value['cat_id'] != $old_value['cat_id'] ||
			    $old_value['filter'] != '' && $value['filter'] != $old_value['filter'] ||
			    $old_value['filter2'] != '' && $value['filter2'] != $old_value['filter2'])
			{
				//echo "<p>search='$old_value[search]'->'$value[search]', filter='$old_value[filter]'->'$value[filter]', filter2='$old_value[filter2]'->'$value[filter2]'<br>";
				//echo "new filter --> loop</p>";
				//_debug_array($old_value);
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