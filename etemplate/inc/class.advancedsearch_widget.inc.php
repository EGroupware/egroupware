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
			$tpl =& new etemplate;
			$tpl->init('*** generated advanced search widget','','',0,'',0,0);	// make an empty template
			$tpl->add_child($tpl,$search_header = $tpl->empty_cell('label','Advanced search',array(
				'no_lang' => 0,
				'label' => 'Advanced search',
				'row' => array( 0 => array('#' => '800px')),
			)));

					
			if (isset($extension_data['result']))
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
				
				$value['result_nm'] = array(
					'no_filter' => true,
					'no_filter2' => true,
					'no_cat' => true,
					'template' => $result_rows_tpl,
					'get_rows' => 'etemplate.advancedsearch_widget.get_rows',
				);
				
				$tpl->add_child($tpl, $result_button = $tpl->empty_cell('button','button[action]',array(
					'label' => 'dump',
					'no_lang' => true,
				)));
				
				//unset($extension_data['result']);
			}
			else
			{
				$extension_data = $value;
				$tpl->add_child($tpl, $search_template = $tpl->empty_cell('template',$value['input_template']));
				$tpl->add_child($tpl, $search_button = $tpl->empty_cell('button','button[search]',array(
					'label' => 'Search',
				)));
				
			}
			
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
			foreach($value as $haystack => $needle)
			{
				if($needle == '') unset($value[$haystack]);
			}
// 			_debug_array($value); 
			if($value['button']['search'])
			{
				$extension_data['result'] = ExecMethod($extension_data['search_method'],array(
					0 => $value, // citeria
					1 => implode(',',array_flip($extension_data['colums_to_present'])), // only_keys
					2 => '', // order_by
					3 => '', // extra_cols
					4 => '', // wildcard
					5 => '', // empty
					6 => 'OR', // operaror
					'multivalue' => true,
				));
			}
		}
		
		function get_rows($query,&$rows,&$readonlys)
		{
			$rows = $GLOBALS['egw']->session->appsession('result','etemplate');
// 			_debug_array($data);
			return count($rows);

		}
	}