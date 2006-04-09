<?php
	/**************************************************************************\
	* eGroupWare - eTemplate Extension - Select Widgets                        *
	* http://www.egroupware.org                                                *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	/**
	 * eTemplate Extension: widget to display a path with clickable components
	 *
	 * The value is an array with id => label pairs. 
	 * Returned will be the id of the clicked component or nothing at all.
	 *
	 * @package etemplate
	 * @subpackage extensions
	 * @author RalfBecker-AT-outdoor-training.de
	 * @license GPL
	 */
	class path_widget
	{
		/** 
		 * exported methods of this class
		 * @var array
		 */
		var $public_functions = array(
			'pre_process' => True,
			'post_process' => True,
		);
		/**
		 * availible extensions and there names for the editor
		 * @var string
		 */
		var $human_name = 'clickable path';

		/**
		 * Constructor of the extension
		 *
		 * @param string $ui '' for html
		 */
		function select_widget($ui)
		{
			$this->ui = $ui;
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
			$seperator = $cell['size'] ? $cell['size'] : '/';
			$extension_data = (array) $value;

			if (!is_array($value) || !count($value))
			{
				$cell = soetemplate::empty_cell();
				$cell['label'] = $seperator;
				return true;
			}
			$cell_name = $cell['name'];
			$cell['name'] = '';
			$cell['type'] = 'hbox';
			$cell['size'] = 0;

			foreach ($value as $id => $label)
			{
				$sep = soetemplate::empty_cell();
				$sep['label'] = $seperator;
				soetemplate::add_child($cell,$sep);
				unset($sep);
				
				$button = soetemplate::empty_cell('button',$cell_name.'['.$id.']');
				$button['label'] = $label;
				$button['onchange'] = 1; // display as link
				$button['no_lang'] = $cell['no_lang'];
				$button['help'] = $cell['help'] ? $cell['help'] : lang($label)."($i)";
				soetemplate::add_child($cell,$button);
				unset($button);
			}	
			return True;	// extra Label Ok
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
			$value = '';
			
			foreach((array)$value_in as $id => $pressed)
			{
				if ($pressed && isset($extension_data[$id]))
				{
					$value = $id;
					break;
				}
			}
			//echo "<p>select_widget::post_process('$name',value=".print_r($value,true).",".print_r($extension_data,true).",,,value_in=".print_r($value_in,true).")</p>\n";
			return true;
		}
	}
