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
		var $human_name = 'Nextmatch Widget';	// this is the name for the editor

		function nextmatch_widget($ui)
		{
		}

		function pre_process(&$cell,&$value,&$templ,$do_get_rows=True)
		{
			//echo "<p>nextmatch_widget.pre_process: value = "; _debug_array($value);
			// save selected tab in persistent extension_data to use it in post_process
			$GLOBALS['phpgw_info']['etemplate']['extension_data']['nextmatch_widget'][$cell['name']] = $value;

			if ($do_get_rows)
			{
				$value['rows'] = ExecMethod($value['get_rows'],$value);
				if ($value['start'] > $value['rows'][0])
				{
					$value['start'] = 0;
					$value['rows'] = ExecMethod($value['get_rows'],$value);
				}
				$GLOBALS['phpgw_info']['etemplate']['extension_data']['nextmatch_widget'][$cell['name']]['total'] = $value['rows'][0];
			}
			if ($cell['size'])
			{
				$value['template'] = $cell['size'];
			}
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
			$total = $value['rows'][0];
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
			$cell['name'] = $nextmatch;
			$cell['label'] = $cell['help'] = '';

			return False;	// NO extra Label
		}

		function post_process(&$cell,&$value,&$templ)
		{
			//echo "<p>nextmatch_widget.post_process: value = "; _debug_array($value);

			$old_value = $GLOBALS['phpgw_info']['etemplate']['extension_data']['nextmatch_widget'][$cell['name']];

			//$this->pre_process($cell,$value,$templ);

			list($value['cat_id']) = $value['cat_id'];
			list($value['filter']) = $value['filter'];
			list($value['filter2'])= $value['filter2'];
			$max   = $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs'];

			$templ->loop = False;
			if ($value['start_search'] || $value['cat_id'] != $old_value['cat_id'] ||
			    $old_value['filter'] != '' && $value['filter'] != $old_value['filter'] ||
			    $old_value['filter2'] != '' && $value['filter2'] != $old_value['filter2'])
			{
				//echo "<p>search='$old_value[search]'->'$value[search]', filter='$old_value[filter]'->'$value[filter]', filter2='$old_value[filter2]'->'$value[filter2]'<br>";
				//echo "new filter --> loop</p>";
				//_debug_array($old_value);
				$templ->loop = True;
			}
			elseif ($value['first'])
			{
				$value['start'] = 0;
				$templ->loop = True;
			}
			elseif ($value['left'])
			{
				$value['start'] = $old_value['start'] - $max;
				$templ->loop = True;
			}
			elseif ($value['right'])
			{
				$value['start'] = $old_value['start'] + $max;
				$templ->loop = True;
			}
			elseif ($value['last'])
			{
				$value['start'] = (int) (($old_value['total']-2) / $max) * $max;
				$templ->loop = True;
			}
			return True;
		}
	}