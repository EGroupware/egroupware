<?php
	/**
	 * eGroupWare eTemplate Extension - AJAX Select Widget
	 *
	 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	 * @package etemplate
	 * @subpackage extensions
	 * @link http://www.egroupware.org
	 * @author Nathan Gray <nathangray@sourceforge.net>
	 * @version $Id$
	 */

    /**
	 * AJAX Select Widget
	 *
	 * Using AJAX, this widget allows a type-ahead find similar to a ComboBox, where as the user enters information,
	 * a drop-down box is populated with the n closest matches.  If the user clicks on an item in the drop-down, that
	 * value is selected.  
	 * n is the maximum number of results set in the user's preferences.
	 * The user is restricted to selecting values in the list.
	 * This widget can get data from any function that can provide data to a nextmatch widget.
	 * This widget is generating html, so it does not work (without an extra implementation) in an other UI
	 */
	class ajax_select_widget
	{
		var $public_functions = array(
			'pre_process' => True,
			'post_process' => True,
			'ajax_search'	=>	True,
		);
		var $human_name = 'AJAX Select';	// this is the name for the editor

		private $debug = false;

		function ajax_select_widget($ui='')
		{

			switch($ui)
			{
				case '':
				case 'html':
					$this->ui = 'html';
					break;
				default:
					echo "UI='$ui' not implemented";
			}
			return 0;
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
			if($this->debug) {
				echo __METHOD__ . '<br />';
				printf("Name:%20s<br />", $name);
				echo 'Value:';
				_debug_array($value);
				echo 'Cell:';
				_debug_array($cell);

				echo 'Readonlys:';
				_debug_array($readonlys);

				echo 'Extension_data:';
				_debug_array($extension_data);

			}

			// Get Options
			$options = array();
			if(!is_array($cell['size'])) {
				list(
					$options['get_rows'],
					$options['get_title'],
					$options['id_field'],
					$options['template'],
					$options['filter'], 
					$options['filter2'],
					$options['link'],
					$options['icon']
				) = explode(',', $cell['size']);
			} else {
				$options = $cell['size'];
			}

			if(is_array($value)) {
				$options = array_merge($options, $value);
			}

			if(!$options['template']) {
				$options['template'] = 'etemplate.ajax_select_widget.row';
			}

			$onchange = ($cell['onchange'] ? $cell['onchange'] : 'false');

			// Set current value
			if(!is_array($value)) {
				$current_value = $value;
			} elseif($value[$options['id_field']]) {
				$current_value = $value[$options['id_field']];
			}
			$extension_data['old_value'] = $value;

			list($title_app, $title_class, $title_method) = explode('.', $options['get_title']);
			if($title_app && $title_class) {
				if (is_object($GLOBALS[$title_class])) {       // use existing instance (put there by a previous CreateObject)
					$title_obj =& $GLOBALS[$title_class];
				} else {
					$title_obj =& CreateObject($title_app . '.' . $title_class);
				}
			}
			if(!is_object($title_obj) || !method_exists($title_obj,$title_method)) {
				echo "$entry_app.$entry_class.$entry_method is not a valid method for getting the title";
			} elseif($current_value) {
				$title = $title_obj->$title_method($current_value);
			}

			// Check get_rows method
			list($get_rows_app, $get_rows_class, $get_rows_method) = explode('.', $options['get_rows']);
			if($get_rows_app && $get_rows_class) {
				if (is_object($GLOBALS[$get_rows_class])) {       // use existing instance (put there by a previous CreateObject)
					$get_rows_obj =& $GLOBALS[$get_rows_class];
				} else {
					$get_rows_obj =& CreateObject($get_rows_app . '.' . $get_rows_class);
				}

				if(!is_object($get_rows_obj) || !method_exists($get_rows_obj, $get_rows_method)) {
					echo "$get_rows_app.$get_rows_class.$get_rows_method is not a valid method for getting the rows";
				}
			}


			// Set up widget
			$cell['type'] = 'template';
			$cell['size'] = $cell['name'];
			$value = array('value' => $current_value, 'search' => $title);
			$widget =& new etemplate('etemplate.ajax_select_widget');
			$widget->no_onclick = True;

			// Link if readonly & link is set
			$search =& $widget->get_widget_by_name('search');
			if(($cell['readonly'] || $readonlys['search']) && $options['link']) {
				$cell['readonly'] = false;
				$readonlys['search'] = true;
				$search['type'] = 'label';
				$search['no_lang'] = 1;
				$search['size'] = ',' . $options['link'];
				$extension_data['readonly'] = true;
			} else {
				$search['type'] = 'text';
				$search['size'] = '';
			}

			// Icon
			$icon =& $widget->get_widget_by_path('/0/1A');
			$icon['name'] = $options['icon'];
			
			$cell['obj'] = &$widget;

			// Save options for post_processing
			$extension_data['options'] = $options;
			$extension_data['needed'] = $cell['needed'];

			// xajax
			$GLOBALS['egw_info']['flags']['include_xajax'] = True;
			
			// JavaScript
			if(!is_object($GLOBALS['egw']->js)) {
				$GLOBALS['egw']->js =& CreateObject('phpgwapi.javascript');
			}
			$options = $GLOBALS['egw']->js->convert_phparray_jsarray("options['$name']", $options, true);
			$GLOBALS['egw']->js->set_onload("if(!options) { 
					var options = new Object();
				}\n 
				$options;\n 
				ajax_select_widget_setup('$name', '$onchange', options['$name'], '" . $GLOBALS['egw_info']['flags']['currentapp'] . "'); 
			");
			$GLOBALS['egw']->js->validate_file('', 'ajax_select', 'etemplate');

			return True;	// no extra label
		}

		function post_process($name,&$value,&$extension_data,&$loop,&$tmpl,$value_in)
		{
			//echo "<p>ajax_select_widget.post_process: $name = "; _debug_array($value_in);_debug_array($extension_data);
			if(!is_array($value_in)) {
				$value_in = $extension_data['old_value'];
			}

			// They typed something in, but didn't choose a result
			if(!$value_in['value'] && $value_in['search']) {
				list($get_rows_app, $get_rows_class, $get_rows_method) = explode('.', $extension_data['options']['get_rows']);
				if($get_rows_app && $get_rows_class) {
					if (is_object($GLOBALS[$get_rows_class])) {       // use existing instance (put there by a previous CreateObject)
						$get_rows_obj =& $GLOBALS[$get_rows_class];
					} else {
						$get_rows_obj =& CreateObject($get_rows_app . '.' . $get_rows_class);
					}

					if(!is_object($get_rows_obj) || !method_exists($get_rows_obj, $get_rows_method)) {
						echo "$get_rows_app.$get_rows_class.$get_rows_method is not a valid method for getting the rows";
					} else {
						$query = array_merge($extension_data['options'], $value_in);
						$count = $get_rows_obj->$get_rows_method($query, $results);

						if($count == 1) {
							$value = $results[0][$extension_data['options']['id_field']];
							return true;
						} elseif ($count > 1) {
							$GLOBALS['egw_info']['etemplate']['validation_errors'][$name] = lang("More than 1 match for '%1'",$value_in['search']);
							$loop = true;
							return false;
						} else {
							$value = $value_in['search'];
							return true;
						}
					}
				}
			} elseif ($extension_data['readonly']) {
				$value = $extension_data['old_value'];
				return true;
			} elseif ($value_in['search'] == '') {
				// They're trying to clear the form
				$value = null;

				// True if not needed, false if needed and they gave no value
				$return = !($extension_data['needed'] && trim($value_in['value']) == '');

				if(!$return) {
					$value = $extension_data['old_value'];
					$GLOBALS['egw_info']['etemplate']['validation_errors'][$name] = lang('Required');
				}

				// Loop if some other widget wants to loop, or if this is required and they gave no value
				$loop = $GLOBALS['egw_info']['etemplate']['loop'] || !$return;

				if($this->debug && $loop) {
					echo 'Looping...<br />Returning ' . $return . '<br />';
				}
				return $return;
			} else {
				$value = $value_in['value'];
				$loop = $GLOBALS['egw_info']['etemplate']['loop'] ||  false;
				return true;
			}
		}

		function ajax_search($id, $value, $set_id, $query) {
			$base_id = substr($id, 0, strrpos($id, '['));
			$result_id = ($set_id ? $set_id : $base_id . '[results]');
			$response = new xajaxResponse();
			if($query['get_rows']) {
				list($app, $class, $method) = explode('.', $query['get_rows']);
				$this->bo = CreateObject($app . '.' . $class);
				unset($query['get_rows']);
			} else {
				return $response->getXML();
			}

			// Expand lists
			foreach($query as $key => $row) {
				if(strpos($row, ',')) {
					$query[$key] = explode(',', $row);
				}

				// sometimes it sends 'null' (not null)
				if($row == 'null') {
					unset($query[$key]);
				}
			}
			$query['search'] = $value;

			$result_list = array();
			$readonlys = array();
			if(is_object($this->bo)) {
				$count = $this->bo->$method($query, $result_list, $readonlys);
			}
			if(is_array($count)) {
				$count = count($result_list);
			}

			$response->addScript("remove_ajax_results('$result_id')");
			if($count > 0) {
				$response->addScript("add_ajax_result('$result_id', '', '', '" . lang('Select') ."');");
				$count = 0;

				if(!$query['template'] || $query['template'] == 'etemplate.ajax_select_widget.row') {
					$query['template'] = 'etemplate.ajax_select_widget.row';
				}
				foreach($result_list as $key => &$row) {
					if(!is_array($row)) {
						continue;
					}
					if($query['id_field'] && $query['get_title']) {
						if($row[$query['id_field']]) {
							$row['title'] = ExecMethod($query['get_title'], $row[$query['id_field']]);
						} 
					}

					$data = ($query['nextmatch_template']) ? array(1=>$row) : $row;
					$widget =& CreateObject('etemplate.etemplate', $query['template']);
					$html = addslashes(str_replace("\n", '', $widget->show($data, '', $readonlys)));
					$row['title'] = htmlspecialchars(addslashes($row['title']));
					$response->addScript("add_ajax_result('$result_id', '${row[$query['id_field']]}', '" . $row['title'] . "', '$html');");
					$count++;
					if($count > $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs']) {
						$response->addScript("add_ajax_result('$result_id', '', '" . lang("%1 more...", (count($result_list) - $count)) . "');");
						break;
					}
				}
			} else {
				$response->addScript("add_ajax_result('$result_id', '', '', '" . lang('No matches found') ."');");
			}
			return $response->getXML();
		}
	}
?>
