<?php
	/**************************************************************************\
	* phpGroupWare - eTemplate Extension - Tab Widget                          *
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
	@class tab_widget
	@author ralfbecker
	@abstract widget that shows one row of tabs and an other row with the eTemplate of the selected tab
	@discussion see the example in 'etemplate.tab_widget.test' (use show to view it)
	@discussion This widget is independent of the UI as it only uses etemplate-widgets and has therefor no render-function
	*/
	class tab_widget
	{
		var $public_functions = array(
			'pre_process' => True,
			'post_process' => True
		);
		var $human_name = 'Tabs';	// this is the name for the editor

		function tab_widget($ui)
		{
		}

		function pre_process(&$cell,&$value,&$templ)
		{
			$labels = explode('|',$cell['label']);
			$helps = explode('|',$cell['help']);
			$names = explode('|',$cell['name']);

			$tabs = new etemplate();
			$tab = new etemplate('etemplate.tab_widget.tab');
			$tab_active = new etemplate('etemplate.tab_widget.tab_active');

			$tabs->init('*** generated tabs','','',0,'',0,0);	// make an empty template

			$tab_row = array();	// generate the tab row
			while (list($k,$name) = each($names))
			{
				$tcell = $tabs->empty_cell();
				if (is_array($value['_tab_widget']) && $value['_tab_widget'][$name][0])
				{
					// save selected tab in persistent extension_data to use it in post_process
					$GLOBALS['phpgw_info']['etemplate']['extension_data']['tab_widget'][$cell['name']] = $selected_tab = $name;
					$tcell['name'] = $tab_active;
				}
				else
				{
					$tcell['name'] = $tab;
				}
				$tcell['type'] = 'template';
				$tcell['size'] = "_tab_widget[$name]";
				$value['_tab_widget'][$name] = array(
					'name'  => $name,
					'label' => $labels[$k],
					'help'  => $helps[$k]
				);
				$tab_row[$tabs->num2chrs($k)] = $tcell;
			}
			// add one empty cell to take all the space of the row
			$tab_row[$k = $tabs->num2chrs(sizeof($tab_row))] = $tabs->empty_cell();
			$tabs->data[0][$k] = '99%'; // width
			$tabs->data[0]['c1'] = ',bottom';

			if (!isset($selected_tab))
			{
				$tab_row['A']['name'] = $tab_active;
				$GLOBALS['phpgw_info']['etemplate']['extension_data']['tab_widget'][$cell['name']] = $selected_tab = $names[0];
			}
			$tabs->data[1] = $tab_row;
			$tabs->rows = 1;
			$tabs->cols = sizeof($tab_row);
			$tabs->size = ',,,,0';

			$tab_widget = new etemplate('etemplate.tab_widget');
			$tab_widget->set_cell_attribute('@tabs','name',$tabs);
			$tab_widget->set_cell_attribute('@body','name',$selected_tab);

			$cell['type'] = 'template';
			$cell['name'] = $tab_widget;
			$cell['label'] = $cell['help'] = '';

			return False;	// NO extra Label
		}

		function post_process(&$cell,&$value,&$templ)
		{
			$old_value = array(
				'_tab_widget' => array(
					$GLOBALS['phpgw_info']['etemplate']['extension_data']['tab_widget'][$cell['name']] => array(True)
			));
			$this->pre_process($cell,$old_value,$templ);

			if (is_array($value['_tab_widget']))
			{
				while (list($key,$val) = each($value['_tab_widget']))
				{
					if (is_array($val) && $val[0])
					{
						$templ->loop = True;
					}
				}
			}
			//$templ->loop = is_array($value['_tab_widget']);

			return True;
		}
	}