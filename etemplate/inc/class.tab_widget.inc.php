<?php
	/**
	 * eGroupWare - eTemplate Extension - Tab Widget
	 *
	 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	 * @package etemplate
	 * @subpackage extensions
	 * @link http://www.egroupware.org
	 * @author Ralf Becker <RalfBecker@outdoor-training.de>
	 * @version $Id$
	 */

	/**
	 * eTemplate Extension: widget that shows one row of tabs and an other row with the eTemplate of the selected tab
	 *
	 * You can use etemplate::setElementAttributes($name, 'tabs', array $tabs) to define tabs on runtime.
	 * $tabs is an array containing an array for each tab with following (eT2 compatible) values:
	 * - 'label' label of tab
	 * - 'help' title/helptext of tab label
	 * - 'template' name of template
	 * - 'id' optional namespace for template (actually 1. grid in template)
	 *
	 * See the example in 'etemplate.tab_widget.test'
	 *
	 * This widget is independent of the UI as it only uses etemplate-widgets and has therefor no render-function
	 */
	class tab_widget
	{
		/**
		 * exported methods of this class
		 * @var array
		 */
		var $public_functions = array(
			'pre_process' => True,
			'post_process' => True,
			'noReadonlysALL' => true,	// mark extension as not to set readonly for $readonlys['__ALL__']
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
		 * @param etemplate &$tmpl reference to the template we belong too
		 * @return boolean true if extra label is allowed, false otherwise
		 */
		function pre_process($form_name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
		{
			//echo "<p>tab_widget::pre_process('$form_name',".array2string($value).','.array2string($readonlys).','.array2string($extension_data).")</p>\n";

			if (!$cell['onchange'])	// onchange allows to use the old behavior (submit for each new tab)
			{
				$dom_enabled = true;
			}
			if (strpos($cell_name=$tab_names=$cell['name'],'=') !== false)
			{
				list($cell_name,$tab_names) = explode('=',$cell['name']);
				$cell['name'] = $cell_name;
			}
			$labels = $helps = $names = $templates = $ids = array();
			if ($cell['tabs'])	// set via etemplate::setElementAttribute()
			{
				foreach($cell['tabs'] as $tab)
				{
					$labels[] = $tab['label'];
					$helps[] = $tab['help'];
					$names[] = $tab['id'] ? $tab['id'] : $tab['template'];
					if ($tab['template']) $templates[count($names)-1] = $tab['template'];
					if ($tab['id']) $ids[count($names)-1] = $tab['id'];
				}
			}
			else
			{
				$labels = explode('|',$cell['label']);
				$helps = explode('|',$cell['help']);
				$names = explode('|',$tab_names);
			}

			$short_names = array();
			foreach($names as $name)
			{
				$nparts = explode('.', $name);
				$short_names[] = array_pop($nparts);
			}
			// disable tab mentioned in readonlys
			foreach(is_array($readonlys) ? $readonlys : array($readonlys => true) as $name => $disable)
			{
				if ($name && $disable && (($key = array_search($name, $names)) !== false ||
					($key = array_search($name, $short_names)) !== false))
				{
					unset($names[$key]);
					$names = array_values($names);
					unset($short_names[$key]);
					$short_names = array_values($short_names);
					unset($helps[$key]);
					$helps = array_values($helps);
					unset($labels[$key]);
					$labels = array_values($labels);
					if ($templates)
					{
						unset($templates[$key]);
						$templates = array_values($templates);
					}
				}
			}
			$tab_widget = new etemplate('etemplate.tab_widget');
			$tab_widget->no_onclick = true;

			if ($value && strpos($value,'.') === false)
			{
				$value = $tmpl->name . '.' . $value;
			}
			foreach($names as $k => $name)
			{
				if (strpos($name,'.') === false)
				{
					$name = $names[$k] = $tmpl->name . '.' . $name;
				}
				if ($value == $name)
				{
					$selected_tab = $name;
				}
			}
			$all_names = implode('|',$names);

			if (empty($selected_tab))
			{
				$value = $selected_tab = $names[0];
			}
			$extension_data = $value;	// remember the active tab in the extension_data

			foreach($names as $k => $name)
			{
				if (strpos($name,'.') === false)
				{
					$name = $names[$k] = $tmpl->name . '.' . $name;
				}
				$tcell = boetemplate::empty_cell();
				if ($value == $name)
				{
					$tcell['span'] = ',etemplate_tab_active';
				}
				else
				{
					$tcell['span'] = ',etemplate_tab';
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
					$tcell['name'] = $cell_name.'['.$name.']';
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
					$bcell = boetemplate::empty_cell('template',$name);
					$bcell['obj'] = new etemplate(empty($templates[$n]) ? $name : $templates[$n],$tmpl->as_array());
					// hack to set id / content attribute on first grid, as it's not supported on template itself
					if (!empty($ids[$n]) && $bcell['obj']->children[0]['type'] == 'grid')
					{
						$bcell['obj']->children[0]['name'] = $ids[$n];
					}
					$tab_widget->set_cell_attribute('body',$n+1,$bcell);
				}
				$tab_widget->set_cell_attribute('body','type','deck');
				$tab_widget->set_cell_attribute('body','size',count($names));
				$tab_widget->set_cell_attribute('body','span',',tab_body');
				$tab_widget->set_cell_attribute('body','name',$cell_name);
			}
			else
			{
				$stab = new etemplate($selected_tab,$tmpl->as_array());
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
