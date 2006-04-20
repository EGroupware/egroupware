<?php
	/**
	 * eGroupWare - eTemplate Extension - Tab Widget
	 *
	 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	 * @package etemplate
	 * @link http://www.egroupware.org
	 * @author Ralf Becker <RalfBecker@outdoor-training.de>
	 * @version $Id$
	 */

	/**
	 * eTemplate Extension: widget that shows one row of tabs and an other row with the eTemplate of the selected tab
	 *
	 * See the example in 'etemplate.tab_widget.test'
	 *
	 * This widget is independent of the UI as it only uses etemplate-widgets and has therefor no render-function
	 *
	 * @package etemplate
	 * @subpackage extensions
	 * @author RalfBecker-AT-outdoor-training.de
	 * @license GPL
	 */
	class tab_widget
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
		 * @var string
		 */
		var $human_name = 'Tabs';	// this is the name for the editor

		/**
		 * Constructor of the extension
		 *
		 * @param string $ui '' for html
		 */
		function tab_widget($ui)
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
		function pre_process($form_name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
		{
			//echo "<p>tab_widget::pre_process('$form_name',$value,,$extension_data)</p>\n";

			if (!$cell['onchange'])	// onchange allows to use the old behavior (submit for each new tab)
			{
				$dom_enabled = isset($GLOBALS['egw_info']['etemplate']['dom_enabled']) ? $GLOBALS['egw_info']['etemplate']['dom_enabled'] : true;
			}
			$labels = explode('|',$cell['label']);
			$helps = explode('|',$cell['help']);
			$names = explode('|',$cell['name']);

			// disable tab mentioned in readonlys
			foreach(is_array($readonlys) ? $readonlys : array($readonlys => true) as $name => $disable)
			{
				if($name && $disable && ($key = array_search($name,$names)) !== false)
				{
					unset($names[$key]);
					$names = array_values($names);
					unset($helps[$key]);
					$helps = array_values($helps);
					unset($labels[$key]);
					$labels = array_values($labels);
				}
			}
			$all_names = implode('|',$names);

			$tab_widget =& new etemplate('etemplate.tab_widget');
			$tab_widget->no_onclick = true;
	
			if ($value && !strstr($value,'.'))
			{
				$value = $tmpl->name . '.' . $value;
			}
			foreach($names as $k => $name)
			{
				if (!strstr($name,'.'))
				{
					$name = $names[$k] = $tmpl->name . '.' . $name;
				}
				if ($value == $name)
				{
					$selected_tab = $name;
				}
			}
			if (empty($selected_tab))
			{
				$value = $selected_tab = $names[0];
			}
			$extension_data = $value;	// remember the active tab in the extension_data

			foreach($names as $k => $name)
			{
				if (!strstr($name,'.'))
				{
					$name = $names[$k] = $tmpl->name . '.' . $name;
				}
				$tcell =& $tab_widget->empty_cell();
				if ($value == $name)
				{
					$tcell['span'] = ',etemplate_tab_active th';
				}
				else
				{
					$tcell['span'] = ',etemplate_tab row_on';
				}
				if ($dom_enabled)
				{
					$tcell['onclick'] = "activate_tab('$name','$all_names','$form_name');";
					$tcell['id'] = $name.'-tab';
				}
				elseif ($value != $name)
				{
					$tcell['type'] = 'button';
					$tcell['onchange'] = '1';
					$tcell['name'] = $cell['name'].'['.$name.']';
				}
				$tcell['label'] = $labels[$k];
				$tcell['help'] = $helps[$k];

				$tab_widget->set_cell_attribute('tabs',1+$k,$tcell);
			}
			$tab_widget->set_cell_attribute('tabs','type','hbox');
			$tab_widget->set_cell_attribute('tabs','size',count($names));
			$tab_widget->set_cell_attribute('tabs','name','');

			if ($dom_enabled)
			{
				foreach($names as $n => $name)
				{
					$bcell = $tab_widget->empty_cell('template',$name);
					$bcell['obj'] =& new etemplate($name,$tmpl->as_array());
					$tab_widget->set_cell_attribute('body',$n+1,$bcell);
				}
				$tab_widget->set_cell_attribute('body','type','deck');
				$tab_widget->set_cell_attribute('body','size',count($names));
				$tab_widget->set_cell_attribute('body','span',',tab_body');
				$tab_widget->set_cell_attribute('body','name',$cell['name']);
			}
			else
			{
				$stab =& new etemplate($selected_tab,$tmpl->as_array());
				$tab_widget->set_cell_attribute('body','type','template');
				$tab_widget->set_cell_attribute('body','size','');	// the deck has a '1' there
				$tab_widget->set_cell_attribute('body','obj',$stab);
			}
			$tab_widget->set_cell_attribute('body','name',$selected_tab);

			$cell['type'] = 'template';
			$cell['obj'] = &$tab_widget;
			$cell['label'] = $cell['help'] = '';

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
			//echo "<p>tab_widget::post_process($name): value_in = "; _debug_array($value_in);

			if (is_array($value_in))
			{
				foreach ($value_in as $tab => $button_pressed)
				{
					if ($button_pressed)
					{
						$value = $tab;
						$loop = True;
					}
				}
			}
			else
			{
				$value = $value_in;
			}
			// if value not set (other button pressed), set the value we remembered in the extension_data
			if (!$value)
			{
				$value = $extension_data;	
			}
			return True;
		}
	}
