<?php
	/**************************************************************************\
	* eGroupWare - eTemplate Extension - Nextmatch Widget                      *
	* http://www.eGroupWare.org                                                *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	/**
	 * eTemplate Extension: Widget that show only a certain number of data-rows and allows to modifiy the rows shown (scroll).
	 *
	 * This widget replaces the old nextmatch-class. It is independent of the UI,
	 * as it only uses etemplate-widgets and has therefor no render-function
	 *
	 * $content[$id] = array(	// I = value set by the app, 0 = value on return / output
	 * 	'get_rows'       =>		// I  method/callback to request the data for the rows eg. 'notes.bo.get_rows'
	 * 	'filter_label'   =>		// I  label for filter    (optional)
	 * 	'filter_help'    =>		// I  help-msg for filter (optional)
	 * 	'no_filter'      => True// I  disable the 1. filter
	 * 	'no_filter2'     => True// I  disable the 2. filter (params are the same as for filter)
	 * 	'no_cat'         => True// I  disable the cat-selectbox
	 * 	'template'       =>		// I  template to use for the rows, if not set via options
	 * 	'header_left'    =>		// I  template to show left of the range-value, left-aligned (optional)
	 * 	'header_right'   =>		// I  template to show right of the range-value, right-aligned (optional)
	 * 	'bottom_too'     => True// I  show the nextmatch-line (arrows, filters, search, ...) again after the rows
	 * 	'start'          =>		// IO position in list
	 * 	'cat_id'         =>		// IO category, if not 'no_cat' => True
	 * 	'search'         =>		// IO search pattern
	 * 	'order'          =>		// IO name of the column to sort after (optional for the sortheaders)
	 * 	'sort'           =>		// IO direction of the sort: 'ASC' or 'DESC'
	 * 	'col_filter'     =>		// IO array of column-name value pairs (optional for the filterheaders)
	 * 	'filter'         =>		// IO filter, if not 'no_filter' => True
	 * 	'filter_no_lang' => True// I  set no_lang for filter (=dont translate the options)
	 * 	'filter2'        =>		// IO filter2, if not 'no_filter2' => True
	 * 	'filter2_no_lang'=> True// I  set no_lang for filter2 (=dont translate the options)
	 * 	'rows'           =>		//  O content set by callback
	 * 	'total'          =>		//  O the total number of entries
	 * 	'sel_options'    =>		//  O additional or changed sel_options set by the callback and merged into $tmpl->sel_options
	 * );
	 * @package etemplate
	 * @subpackage extensions
	 * @author RalfBecker-AT-outdoor-training.de
	 * @license GPL
	 */
	class nextmatch_widget
	{
		/** 
		 * exported methods of this class
		 * @var array
		 */
		var $public_functions = array(
			'pre_process' => True,
			'post_process' => True
		);
		/**
		 * availible extensions and there names for the editor
		 * @var array
		 */
		var $human_name = array(
			'nextmatch' => 'Nextmatch',
			'nextmatch-sortheader' => 'Nextmatch Sortheader',
			'nextmatch-filterheader' => 'Nextmatch Filterheader'
		);

		/**
		 * Constructor of the extension
		 *
		 * @param string $ui '' for html
		 */
		function nextmatch_widget($ui)
		{
		}

		/**
		 * returns last part of a form-name
		 * @internal 
		 */
		function last_part($name)
		{
			$parts = explode('[',str_replace(']','',$name));
			return $parts[count($parts)-1];
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
			$nm_global = &$GLOBALS['phpgw_info']['etemplate']['nextmatch'];
			//echo "<p>nextmatch_widget.pre_process(name='$name',type='$cell[type]'): value = "; _debug_array($value);
			//echo "<p>nextmatch_widget.pre_process(name='$name',type='$cell[type]'): nm_global = "; _debug_array($nm_global);

			$extension_data = array(
				'type' => $cell['type']
			);
			switch ($cell['type'])
			{
				case 'nextmatch-sortheader':
					$cell['type'] = 'button';
					$cell['onchange'] = True;
					if (!$cell['help'])
					{
						$cell['help'] = 'click to order after that criteria';
					}
					if ($this->last_part($name) == $nm_global['order'])	// we're the active column
					{
						$cell[1] = $cell;
						$cell[1]['span'] .= ',activ_sortcolumn';
						$cell[2] = $tmpl->empty_cell('image',$nm_global['sort'] != 'DESC' ? 'down' : 'up');
						$cell['type'] = 'hbox';
						$cell['size'] = '2,0,0';
						$cell['name'] = $cell['label'] = '';
					}
					else
					{
						$cell['span'] .= ',inactiv_sortcolumn';
					}
					return True;

				case 'nextmatch-filterheader':
					$cell['type'] = 'select';
					if (!$cell['size'])
					{
						$cell['size'] = 'All';
					}
					if (!$cell['help'])
					{
						$cell['help'] = 'select which values to show';
					}
					$cell['onchange'] = True;
					$extension_data['old_value'] = $value = $nm_global['col_filter'][$this->last_part($name)];
					return True;
			}
			list($app,$class,$method) = explode('.',$value['get_rows']);
			if ($app && $class)
			{
				if (is_object($GLOBALS[$class]))	// use existing instance (put there by a previous CreateObject)
				{
					$obj =& $GLOBALS[$class];
				}
				else
				{
					$obj =& CreateObject($app.'.'.$class);
				}
			}
			if (!is_object($obj) || !method_exists($obj,$method))
			{
				$GLOBALS['phpgw_info']['etemplate']['validation_errors'][$name] = "nextmatch_widget::pre_process($cell[name]): '$value[get_rows]' is no valid method !!!";
			}
			else
			{
				$total = $value['total'] = $obj->$method($value,$value['rows'],$readonlys['rows']);
				
				// allow the get_rows function to override / set sel_options
				if (isset($value['rows']['sel_options']) && is_array($value['rows']['sel_options']))
				{
					$tmpl->sel_options = array_merge($tmpl->sel_options,$value['rows']['sel_options']);
					unset($value['rows']['sel_options']);
				}
			}
			if ($value['start'] > $total)
			{
				$value['start'] = 0;
				$total = $obj->$method($value,$value['rows'],$readonlys['rows']);
			}
			if (is_array($value['rows'][0]))	// fixed 0 based arrays
			{
				array_unshift($value['rows'],false);
			}
			list($template,$options) = explode(',',$cell['size']);
			if ($template)	// template name can be supplied either in $value['template'] or the options-field
			{
				$value['template'] = $template;
			}
			if (!is_object($value['template']))
			{
				$value['template'] =& new etemplate($value['template'],$tmpl->as_array());
			}
			if ($total < 1 && $value['template']->rows > 1)
			{
				$value['template']->data[0]['h'.$value['template']->rows] .= ',1';	// disable the last data row
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
				$nextmatch =& new etemplate('etemplate.nextmatch_widget');
				// keep the editor away from the generated tmpls
				$nextmatch->no_onclick = true;			

				foreach(array('no_cat'=>'cat_id','no_filter'=>'filter','no_filter2'=>'filter2') as $val_name => $cell_name)
				{
					if (isset($value[$val_name])) $nextmatch->disable_cells($cell_name,$value[$val_name]);
				}
				foreach(array('header_left','header_right') as $name)
				{
					if (!$value[$name]) $nextmatch->disable_cells('@'.$name);
				}
				foreach(array('filter','filter2') as $cell_name)
				{
					if (isset($value[$cell_name.'_no_lang'])) $nextmatch->set_cell_attribute($cell_name,'no_lang',$value[$cell_name.'_no_lang']);
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
			$extension_data += $value;

			foreach(array('sort','order','col_filter') as $n)	// save them for the sortheader
			{
				$nm_global[$n] = $value[$n];
			}
			$value['bottom'] = $value;	// copy the values for the bottom-bar

			return False;	// NO extra Label
		}

		/**
		 * postprocessing method, called after the submission of the form
		 *
		 * It has to copy the allowed/valid data from $value_in to $value, otherwise the widget
		 * will return no data (if it has a preprocessing method). The framework insures that
		 * the post-processing of all contained widget has been done before.
		 *
		 * Only used by select-dow so far
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
			$nm_global = &$GLOBALS['phpgw_info']['etemplate']['nextmatch'];
			//echo "<p>nextmatch_widget.post_process(name='$name',value_in='$value_in',order='$nm_global[order]'): value = "; _debug_array($value);
			switch($extension_data['type'])
			{
				case 'nextmatch-sortheader':
					if ($value_in)
					{
						$nm_global['order'] = $this->last_part($name);
					}
					return False;	// dont report value back, as it's in the wrong location (rows)

				case 'nextmatch-filterheader':
					if ($value_in != $extension_data['old_value'])
					{
						$nm_global['filter'][$this->last_part($name)] = $value_in;
					}
					return False;	// dont report value back, as it's in the wrong location (rows)
			}
			$old_value = $extension_data;

			$max   = $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs'];
			$value['start'] = $old_value['start'];	// need to be set, to be reported back

			if (is_array($value['bottom']))			// we have a second bottom-bar
			{
				$inputs = array('search','cat_id','filter','filter2');
				foreach($inputs as $name)
				{
					if (isset($value['bottom'][$name]) && $value[$name] == $old_value[$name])
					{
						//echo "value[$name] overwritten by bottom-value[$name]='".$value['bottom'][$name]."', old_value[$name]='".$old_value[$name]."'<br>\n";
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
			if ($value['start_search'] || $value['search'] != $old_value['search'] ||
			    isset($value['cat_id']) && $value['cat_id'] != $old_value['cat_id'] ||
			    isset($value['filter']) && $value['filter'] != $old_value['filter'] ||
			    isset($value['filter2']) && $value['filter2'] != $old_value['filter2'])
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
				unset($value['first']);
				$loop = True;
			}
			elseif ($value['left'])
			{
				$value['start'] = $old_value['start'] - $max;
				unset($value['left']);
				$loop = True;
			}
			elseif ($value['right'])
			{
				$value['start'] = $old_value['start'] + $max;
				unset($value['right']);
				$loop = True;
			}
			elseif ($value['last'])
			{
				$value['start'] = (int) (($old_value['total']-2) / $max) * $max;
				unset($value['last']);
				$loop = True;
			}
			elseif ($nm_global['order'])
			{
				$value['order'] = $nm_global['order'];
				$value['sort']  = $old_value['order'] == $nm_global['order'] && $old_value['sort']!='DESC'?'DESC':'ASC';
				$loop = True;
			}
			elseif ($nm_global['filter'])
			{
				if (!is_array($value['col_filter'])) $value['col_filter'] = array();
				$value['col_filter'] += $nm_global['filter'];
				$loop = True;
			}
			return True;
		}
	}
