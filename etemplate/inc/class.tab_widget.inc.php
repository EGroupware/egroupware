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
	@abstract widget that shows one row of tabs and an other row with the eTemplate of the selected tab
	@note This widget is independent of the UI as it only uses etemplate-widgets and has therefor no render-function
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

		function pre_process(&$cell,&$value,&$parent)
		{
			$labels = explode('|',$cell['label']);
			$helps = explode('|',$cell['help']);
			$names = explode('|',$cell['name']);

			$cell['type'] = 'template';
			$templ = new etemplate();
			$tab = new etemplate('etemplate.tab_widget.tab');
			$tab_active = new etemplate('etemplate.tab_widget.tab_active');

			$templ->init('*** generated tab_widget','','',0,'',0,0);	// make an empty template

			$tabs = array();	// generate the tabs row
			while (list($k,$name) = each($names))
			{
				$tcell = $templ->empty_cell();
/*				$tcell['name'] = "_tab_widget[$name]";
				$tcell['type'] = 'button';
				$tcell['label'] = $labels[$k];
				$tcell['help'] = $helps[$k];
*/				if (is_array($value['_tab_widget']) && isset($value['_tab_widget'][$name]))
				{
//					$tcell['span'] = ',nmh';	// set tab as selected
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
					'name'  => "_tab_widget[$name]",
					'label' => $labels[$k],
					'help'  => $helps[$k]
				);
				$tabs[$templ->num2chrs($k)] = $tcell;
			}
			// add one empty cell to take all the space of the row
			$tabs[$k = $templ->num2chrs(sizeof($tabs))] = $templ->empty_cell();
			$templ->data[0][$k] = '99%'; // width

			if (!isset($selected_tab))
			{
				//$tabs['A']['span'] = ',nmh';
				$tabs['A']['name'] = $tab_active;
				$GLOBALS['phpgw_info']['etemplate']['extension_data']['tab_widget'][$cell['name']] = $selected_tab = $names[0];
			}
			$templ->data[1] = $tabs;

			$tcell = $templ->empty_cell(); 	// make the tabwidget-header
			$tcell['label'] = ' ';
			$tcell['span'] = 'all';
			$templ->data[2]['A'] = $tcell;
			$templ->data[0]['c2'] = 'nmh';

			$tcell = $templ->empty_cell(); 	// make the tabwidget-body
			$tcell['type'] = 'template';
			$tcell['name'] = $selected_tab;
			$tcell['span'] = 'all';
			$templ->data[3]['A'] = $tcell;

			$templ->rows = 3;
			$templ->cols = sizeof($tabs);

			$templ->size = ',,,,0';

			$cell['type'] = 'template';
			$cell['name'] = $templ;
			$cell['label'] = $cell['help'] = '';

			return False;	// extra Label NOT ok
		}

		function post_process(&$cell,&$value,&$templ)
		{
			$old_value = array(
				'_tab_widget' => array(
					$GLOBALS['phpgw_info']['etemplate']['extension_data']['tab_widget'][$cell['name']] => True
			));
			$this->pre_process($cell,$old_value,$templ);

			$templ->loop = is_array($value['_tab_widget']);

			return True;
		}
	}