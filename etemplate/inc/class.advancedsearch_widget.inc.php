<?php
	/**************************************************************************\
	* eGroupWare - eTemplate Extension - Advanced search                       *
	* http://www.egroupware.org                                                *
	* Written by Cornelius Weiss <egw@von-und-zu-weiss.de>                     *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */
	
	/**
	* eTemplate Extension: Advanced Search
	*
	* $content[$name] = array(
	*	'input_template'	=> app.template
	*	'search_method'		=> app.class.method in so_sql style
	*	'colums_to_present'	=> array with field_name => label
	*
	*/
	class advancedsearch_widget
	{
		/** 
		 * @var $public_functions array with exported methods of this class
		 */
		var $public_functions = array(
			'pre_process' => True,
			'post_process' => True
		);
		
		/**
		 * @var $human_name
		 */
		var $human_name = 'Advanced search';
		
		/**
		* @var $debug bool
		*/
		var $debug = False;

		/**
		 * Constructor of the extension
		 *
		 * @param string $ui '' for html
		 */
		function advancedsearch_widget($ui='')
		{
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
			$value = is_array($extension_data) ? $extension_data : $value;
			
			$tpl =& new etemplate;
			$tpl->init('*** generated advanced search widget','','',0,'',0,0);	// make an empty template
			$tpl->add_child($tpl,$search_header = $tpl->empty_cell('label',$cell['label'],array(
				'no_lang' => 0,
				'label' => 'Advanced search',
				'span' =>',heading1',
			)));
			if($value['msg'])
			{
				$tpl->add_child($tpl,$msg = $tpl->empty_cell('label','msg',array(
					'no_lang' => true,
				)));
			}
			
			if (isset($value['result_nm']))
			{
				$GLOBALS['egw']->session->appsession('result','etemplate',$extension_data['result']);
				$tpl->add_child($tpl, $result_nm = $tpl->empty_cell('nextmatch','result_nm',array(
// 					'width' => '800px',
				)));

				$result_rows_tpl =& new etemplate;
				$result_rows_tpl->init('*** generated rows template for advanced search results','','',0,'',0,0);
				$grid =& $result_rows_tpl->children[0];
				foreach((array)$extension_data['colums_to_present'] as $field => $label)
				{
					if($label == '') continue;
					$result_rows_tpl->add_child($grid,$result_nm_header = $result_rows_tpl->empty_cell('nextmatch-sortheader',$field,array(
						'label' => $label,
						'no_lang' => true,
						'span' => ',nmh',
					)));
					unset($result_nm_header);
				}
				$result_rows_tpl->add_child($grid,$rows);
				foreach((array)$extension_data['colums_to_present'] as $field => $label)
				{
					if($label == '') continue;
					$result_rows_tpl->add_child($grid,$result_nm_rows = $result_rows_tpl->empty_cell('text','${row}['.$field.']',array(
						'no_lang' => true,
						'readonly' => true,
					)));
					
					unset($result_nm_rows);
				}
				
				$value['result_nm'] = array_merge(
					$value['result_nm'],
					array(
						'no_filter' => true,
						'no_filter2' => true,
						'no_cat' => true,
						'no_search' => true,
						'get_rows' => 'etemplate.advancedsearch_widget.get_rows',
						'search_method' => $value['search_method'],
						'colums_to_present' => $value['colums_to_present'],
						'template' => $result_rows_tpl,
					));
				
				$tpl->add_child($tpl, $action_buttons = $tpl->empty_cell('hbox','action_buttons'));
				foreach ($value['actions'] as $action => $options)
				{
					$tpl->add_child($action_buttons, $result_button = $tpl->empty_cell($options['type'],'action['.$action.']',$options['options']));
					unset($result_button);
				}	
			}
			else
			{
				$GLOBALS['egw_info']['etemplate']['advanced_search'] = true;
				$extension_data = $value;

				$tpl->add_child($tpl, $search_template = $tpl->empty_cell('template',$value['input_template']));
				$tpl->add_child($tpl, $button_box = $tpl->empty_cell('hbox','button_box'));
				$tpl->add_child($button_box, $op_select = $tpl->empty_cell('select','opt_select',array(
					'sel_options' => array(
						'OR' => 'OR',
						'AND' => 'AND'
					),
					'label' => 'Operator',
					'no_lang' => true,
				)));
				$tpl->add_child($button_box, $search_button = $tpl->empty_cell('button','button[search]',array(
					'label' => 'Search',
				)));
			}
;
			$cell['size'] = $cell['name'];
			$cell['type'] = 'template';
			$cell['name'] = $tpl->name;
			$cell['obj'] =& $tpl;
				
			// keep the editor away from the generated tmpls
			$tpl->no_onclick = true;

			if ($this->debug)
			{
				echo "<p>end: $type"."[$name]::pre_process: value ="; _debug_array($value);
			}
			return True;
		}
		
		/**
		 * postprocessing method, called after the submission of the form
		 *
		 * It has to copy the allowed/valid data from $value_in to $value, otherwise the widget
		 * will return no data (if it has a preprocessing method). The framework insures that
		 * the post-processing of all contained widget has been done before.
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
			//echo 'advancedsearch_widget::post_process->value'; _debug_array($value);
			//echo 'advancedsearch_widget::post_process->extension_data'; _debug_array($extension_data);
			if(!isset($extension_data['result_nm']['search_values']))
			{
				foreach($value as $haystack => $needle)
				{
					if($needle == '') unset($value[$haystack]);
				}
				$extension_data['result_nm']['search_values'] = $value;
			}
			else
			{
				$extension_data['result_nm'] = array_merge($extension_data['result_nm'],$value['result_nm']);
			}
			
			if(isset($value['action']))
			{
				// Also inputfileds etc. could be in actions
				foreach($value['action'] as $action => $label)
				{
					if($extension_data['actions'][$action]['type'] == 'button')
					{
						$result = $GLOBALS['egw']->session->appsession('advanced_search_result','etemplate');
						$extension_data['msg'] = ExecMethod2($extension_data['actions'][key($value['action'])]['method'],$result);
					}
				}
			}		}
		
		function get_rows($query,&$rows,&$readonlys)
		{
			$order_by = $query['order'] ? $query['order'].' '.$query['sort'] : '';
			$only_keys = implode(',',array_flip($query['colums_to_present']));
			$rows = ExecMethod2($query['search_method'],$query['search_values'],$only_keys,
				$order_by,'','','',$query['search_values']['opt_select'],$query['start']);
			$result = ExecMethod2($query['search_method'],$query['search_values'],$only_keys,'','','','',$query['search_values']['opt_select'],false,'','',false);
			// We store the result in session so actions can fetch them here:
			$GLOBALS['egw']->session->appsession('advanced_search_result','etemplate',$result);
			return count($result);

		}
	}