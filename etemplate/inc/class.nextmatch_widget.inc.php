<?php
	/**
	 * eGroupWare  eTemplate Extension - Nextmatch Widget
	 *
	 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	 * @package etemplate
	 * @link http://www.egroupware.org
	 * @author Ralf Becker <RalfBecker@outdoor-training.de>
	 * @version $Id$
	 */

	require_once(EGW_INCLUDE_ROOT. '/etemplate/inc/class.etemplate.inc.php');
	
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
	 *  'cat_app'        =>     // I  application the cat's should be from, default app in get_rows
	 * 	'template'       =>		// I  template to use for the rows, if not set via options
	 * 	'header_left'    =>		// I  template to show left of the range-value, left-aligned (optional)
	 * 	'header_right'   =>		// I  template to show right of the range-value, right-aligned (optional)
	 * 	'bottom_too'     => True// I  show the nextmatch-line (arrows, filters, search, ...) again after the rows
	 *	'never_hide'     => True// I  never hide the nextmatch-line if less then maxmatch entries
	 *  'lettersearch'   => True// I  show a lettersearch
	 *  'searchletter'   =>     // I0 active letter of the lettersearch or false for [all]
	 * 	'start'          =>		// IO position in list
	 *	'num_rows'       =>     // IO number of rows to show, defaults to maxmatches from the general prefs
	 * 	'cat_id'         =>		// IO category, if not 'no_cat' => True
	 * 	'search'         =>		// IO search pattern
	 * 	'order'          =>		// IO name of the column to sort after (optional for the sortheaders)
	 * 	'sort'           =>		// IO direction of the sort: 'ASC' or 'DESC'
	 * 	'col_filter'     =>		// IO array of column-name value pairs (optional for the filterheaders)
	 * 	'filter'         =>		// IO filter, if not 'no_filter' => True
	 * 	'filter_no_lang' => True// I  set no_lang for filter (=dont translate the options)
	 *	'filter_onchange'=> 'this.form.submit();' // I onChange action for filter, default: this.form.submit();
	 * 	'filter2'        =>		// IO filter2, if not 'no_filter2' => True
	 * 	'filter2_no_lang'=> True// I  set no_lang for filter2 (=dont translate the options)
	 *	'filter2_onchange'=> 'this.form.submit();' // I onChange action for filter2, default: this.form.submit();
	 * 	'rows'           =>		//  O content set by callback
	 * 	'total'          =>		//  O the total number of entries
	 * 	'sel_options'    =>		//  O additional or changed sel_options set by the callback and merged into $tmpl->sel_options
	 *  'return'         =>     // IO allows to return something from the get_rows function if $query is a var-param!
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
			'post_process' => True,
		);
		/**
		 * availible extensions and there names for the editor
		 * @var array
		 */
		var $human_name = array(
			'nextmatch'               => 'Nextmatch',
			'nextmatch-sortheader'    => 'Nextmatch Sortheader',
			'nextmatch-filterheader'  => 'Nextmatch Filterheader',
			'nextmatch-accountfilter' => 'Nextmatch Accountfilter',
			'nextmatch-customfilter'  => 'Nextmatch Custom Filterheader',
			'nextmatch-header'        => 'Nextmatch Header',
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
		 * 
		 * @internal
		 * @return string 
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
		 * @param etemplate &$tmpl reference to the template we belong too
		 * @return boolean true if extra label is allowed, false otherwise
		 */
		function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
		{
			$nm_global = &$GLOBALS['egw_info']['etemplate']['nextmatch'];
			//echo "<p>nextmatch_widget.pre_process(name='$name',type='$cell[type]'): value = "; _debug_array($value);
			//echo "<p>nextmatch_widget.pre_process(name='$name',type='$cell[type]'): nm_global = "; _debug_array($nm_global);

			$extension_data = array(
				'type' => $cell['type']
			);
			switch ($cell['type'])
			{
				case 'nextmatch-header':
					$cell['type'] = 'label';
					return true;	// false = no extra label

				case 'nextmatch-sortheader':	// Option: default sort: ASC(default) or DESC
					$extension_data['default_sort'] = $cell['size']&&preg_match('/^(ASC|DESC)/i',$cell['size'],$matches) ? strtoupper($matches[1]) : 'ASC';
					$cell['type'] = 'button';
					$cell['onchange'] = True;
					if (!$cell['help'])
					{
						$cell['help'] = 'click to order after that criteria';
					}
					if ($this->last_part($name) == $nm_global['order'])	// we're the active column
					{
						$cell[1] = $cell;
						unset($cell[1]['align']);
						$cell[2] = $tmpl->empty_cell('image',$nm_global['sort'] != 'DESC' ? 'down' : 'up');
						$cell['type'] = 'hbox';
						$cell['size'] = '2,,0,0';
						$class = 'activ_sortcolumn';
						$cell['name'] = $cell['label'] = '';
					}
					else
					{
						$class = 'inactiv_sortcolumn';
					}
					$parts = explode(',',$cell['span']);
					$parts[1] .= ($parts[1] ? ' ' : '').$class;
					$cell['span'] = implode(',',$parts);
					return True;

				case 'nextmatch-accountfilter':	// Option: as for selectbox: [extra-label(default ALL)[,#lines(default 1)]]
					$cell['size'] = 'select-account,'.$cell['size'];
					// fall through
				case 'nextmatch-customfilter':	// Option: widget-name, options as for selectbox
					list($type,$cell['size']) = explode(',',$cell['size'],2);
					// fall through					
				case 'nextmatch-filterheader':	// Option: as for selectbox: [extra-label(default ALL)[,#lines(default 1)]]
					if (!$type) $type = 'select';
					$cell['type'] = $type;
					if (!$cell['size'])
					{
						$cell['size'] = 'All';
					}
					if (!$cell['help'])
					{
						$cell['help'] = 'select which values to show';
					}
					$cell['onchange'] = $cell['noprint'] = True;
					$parts = explode(',',$cell['span']);
					$parts[1] .= ($parts[1] ? ' ' : '').'filterheader';
					$cell['span'] = implode(',',$parts);
					$extension_data['old_value'] = $value = $nm_global['col_filter'][$this->last_part($name)];
					return True;
			}
			// presetting the selectboxes with their default values, to NOT loop, because post-process thinks they changed
			if (!isset($value['cat_id'])) $value['cat_id'] = '';
			if (!isset($value['search'])) $value['search'] = '';
			foreach(array('filter','filter2') as $f)
			{
				if (!isset($value[$f]))
				{
					list($value[$f]) = isset($tmpl->sel_options[$f]) ? @each($tmpl->sel_options[$f]) : @each($value['options-'.$f]);
					if (!is_string($value[$f])) $value[$f] = (string) $value[$f];
				}
			}
			// save values in persistent extension_data to be able use it in post_process
			$extension_data += $value;

			if (!$value['filter_onchange']) $value['filter_onchange'] = 'this.form.submit();';
			if (!$value['filter2_onchange']) $value['filter2_onchange'] = 'this.form.submit();';

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
			if (!isset($value['cat_app'])) $value['cat_app'] = $app;	// if no cat_app set, use the app from the get_rows func

			$max = (int)$GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'];
			$row_options = array();
			foreach(array(5,12,25,50,100,200,500,999) as $n)
			{
				if ($n-5 <= $max && $max <= $n+5) $n = $max;
				$row_options[$n] = $n;
			}
			if (!isset($row_options[$max]))
			{
				$row_options[$max] = $max;
				ksort($row_options);
			}
			$value['options-num_rows'] =& $row_options;

			if (!isset($value['num_rows'])) $value['num_rows'] = $max;
			if ($value['num_rows'] != $max)
			{
				$GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'] = $max = (int)$value['num_rows'];
			}				
			if (!$value['no_columnselection'])
			{
				// presetting the options for selectcols empty, so the get_rows function can set it
				$value['options-selectcols'] = array();
			}
			if (!is_object($obj) || !method_exists($obj,$method))
			{
				$GLOBALS['egw_info']['etemplate']['validation_errors'][$name] = "nextmatch_widget::pre_process($cell[name]): '$value[get_rows]' is no valid method !!!";
			}
			else
			{
				if (!is_array($readonlys)) $readonlys = array();
				$total = $value['total'] = $obj->$method($value,$value['rows'],$readonlys['rows']);

				// allow the get_rows function to override / set sel_options
				if (isset($value['rows']['sel_options']) && is_array($value['rows']['sel_options']))
				{
					$tmpl->sel_options = array_merge($tmpl->sel_options,$value['rows']['sel_options']);
					unset($value['rows']['sel_options']);
				}
			}
			if ($method && $total && $value['start'] >= $total)
			{
				$value['start'] = 0;
				$total = $obj->$method($value,$value['rows'],$readonlys['rows']);
			}
			if (is_array($value['rows'][0]))	// fixed 0 based arrays
			{
				array_unshift($value['rows'],false);
			}
			list($template,$options) = explode(',',$cell['size']);
			if (!$value['template'] && $template)	// template name can be supplied either in $value['template'] or the options-field
			{
				$value['template'] = $template;
			}
			if (!is_object($value['template']))
			{
				$value['template'] =& new etemplate($value['template'],$tmpl->as_array());
			}
			$extension_data['template'] = $value['template']->name;	// used for the column-selection, and might be set in get_rows()
			$extension_data['columnselection_pref'] = $value['columnselection_pref'];

			if ($total < 1 && $value['template']->rows > 1)
			{
				$value['template']->data[0]['h'.$value['template']->rows] .= ',1';	// disable the last data row
			}
			if (!$value['never_hide'] && $total <= $max && $options && $value['search'] == '' &&
				 ($value['no_cat'] || !$value['cat_id']) &&
				 ($value['no_filter'] || !$value['filter'] || $value['filter'] == 'none') &&
				 ($value['no_filter2'] || !$value['filter2'] || $value['filter2'] == 'none'))
			{											// disable whole nextmatch line if no scrolling necessary
				if ($value['header_left'] || $value['header_right'])
				{
					$nextmatch =& new etemplate('etemplate.nextmatch_widget.header_only');
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
				
				if ($value['lettersearch'])
				{
					$lettersearch =& $nextmatch->get_widget_by_name('lettersearch');	// hbox for the letters
					if (($alphabet = lang('alphabet')) == 'alphabet*') $alphabet = 'a,b,c,d,e,f,g,h,i,j,k,l,m,n,o,p,q,r,s,t,u,v,w,x,y,z';
					$alphabet = explode(',',$alphabet);
					$alphabet['all'] = lang('all');
					foreach($alphabet as $key => $letter)
					{
						// make each letter internally behave like a button
						$form_name = $name.'[searchletter]['.($key === 'all' ? $key : $letter).']';
						$GLOBALS['egw_info']['etemplate']['to_process'][$form_name] = 'button';

						if (!$key) $letterbox =& $lettersearch[1];	// to re-use the first child
						$letterbox = etemplate::empty_cell('label',$letter,array(
							'label'   => $letter,
							'span'    => ',lettersearch'.($letter == (string) $value['searchletter'] || 
								$key === 'all' && !$value['searchletter'] ? '_active' : ''),
							'no_lang' => 2,
							'align'   => $key == 'all' ? 'right' : '',
							'onclick' => "return submitit($tmpl->name_form,'$form_name');",
						));
						// if not the first (re-used) child, add it to the parent
						if ($key) etemplate::add_child($lettersearch,$letterbox); 
						unset($letterbox);
					}
					//_debug_array($GLOBALS['egw_info']['etemplate']['to_process']);
				}				
				if(isset($value['no_search'])) $value['no_start_search'] = $value['no_search'];
				foreach(array('no_cat'=>'cat_id','no_filter'=>'filter','no_filter2'=>'filter2', 'no_search' => 'search', 'no_start_search' => 'start_search' ) as $val_name => $cell_name)
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
			// preset everything for the column selection
			if (!$value['no_columnselection'] &&	// fetching column-names & -labels from the template
				$this->_cols_from_tpl($value['template'],$value['options-selectcols'],$name2col,$value['rows']))
			{
				//_debug_array($name2col);
				//_debug_array($value['options-selectcols']);
				// getting the selected colums from the prefs (or if not set a given default or all)
				$name = is_object($value['template']) ? $value['template']->name : $value['template'];
				list($app) = explode('.',$name);
				if (isset($value['columnselection_pref'])) $name = $value['columnselection_pref'];
				if (!($value['selectcols'] = $GLOBALS['egw_info']['user']['preferences'][$app]['nextmatch-'.$name]))
				{
					$value['selectcols'] = array_keys($value['options-selectcols']);
					if (isset($value['default_cols']))
					{
						if ($value['default_cols']{0} == '!')
						{
							$value['selectcols'] = array_diff($value['selectcols'],explode(',',substr($value['default_cols'],1)));
						}
						else
						{
							$value['selectcols'] = $value['default_cols'];
						}
					}
				}
				if (!is_array($value['selectcols'])) $value['selectcols'] = explode(',',$value['selectcols']);
				foreach(array_keys($value['options-selectcols']) as $name)
				{
					// set 'no_'.$col for each column-name to true, if the column is not selected 
					// (and the value is not set be the get_rows function / programmer!) 
					if (!isset($value['rows']['no_'.$name])) $value['rows']['no_'.$name] = !in_array($name,$value['selectcols']);
					// setting '@no_'.$name as disabled attr for each column, that has only a single nextmatch-header
					if (is_object($value['template']))
					{
						$col = $name2col[$name];
						list(,$disabled) = $value['template']->set_column_attributes($col);
						//echo "<p>$col: $name: $disabled</p>\n";
						if (!isset($disabled)) $value['template']->set_column_attributes($col,0,'@no_'.$name);
					}
				}
				//_debug_array($value);
				if (is_object($nextmatch))
				{
					$size =& $nextmatch->get_cell_attribute('selectcols','size');
					if ($size > count($value['options-selectcols'])) $size = '0'.count($value['options-selectcols']);
					if (!$GLOBALS['egw_info']['user']['apps']['admin'])
					{
						$nextmatch->disable_cells('default_prefs');
					}
				}
				// should reset on each submit
				unset($value['default_prefs']);
			}
			$cell['type'] = 'template';
			$cell['label'] = $cell['help'] = '';

			foreach(array('sort','order','col_filter') as $n)	// save them for the sortheader
			{
				$nm_global[$n] = $value[$n];
			}
			$value['bottom'] = $value;	// copy the values for the bottom-bar

			return False;	// NO extra Label
		}
		
		/**
		 * Extract the column names and labels from the template
		 *
		 * @param etemplate &$tmpl
		 * @param array &$cols here we add the column-name/-label
		 * @param array &$name2col
		 * @param array $content nextmatch content, to be able to resolve labels with @name
		 * @return int columns found, count($cols)
		 */
		function _cols_from_tpl(&$tmpl,&$cols,&$name2col,&$content)
		{
			//_debug_array($cols); 
			// fetching column-names & -labels from the template
			$cols['__content__'] =& $content;
			$tmpl->widget_tree_walk(array($this,'_cols_from_tpl_walker'),$cols);
			unset($cols['__content__']);
			$name2col = is_array($cols['name2col']) ? $cols['name2col'] : array(); unset($cols['name2col']);
			//_debug_array($cols); 
			foreach($cols as $name => $label)
			{
				if (!$label) unset($cols[$name]);
			}
			//_debug_array($cols); 
			//_debug_array($name2col);
			// now we check if a column of the grid has more then one header
			$col2names = array();
			foreach($name2col as $name => $col)
			{
				if ($name != $col) $col2names[$col][] = $name;
			}
			//_debug_array($col2names);
			$cols2 = $cols;
			$cols = array();
			foreach($cols2 as $name => $label)
			{
				$col = $name;
				// and replace the column letter then with the name concatinated by an underscore
				if (is_array($col2names[$name]))
				{
					$name = implode('_',$col2names[$name]);
					$name2col[$name] = $col;
				}
				$cols[$name] = $label;
			}
			//_debug_array($cols);
			return count($cols);
		}

		/**
		 * Extract the column names and labels from the template (callback for etemplate::widget_tree_walk())
		 *
		 * @param array &$widget
		 * @param array &$cols here we add the column-name/-label
		 * @param string $path
		 */
		function _cols_from_tpl_walker(&$widget,&$cols,$path)
		{
			list($type,$subtype) = explode('-',$widget['type']);
			if ($type != 'nextmatch' || !$subtype || !$widget['name'] || $widget['disabled'])
			{
				return;
			}
			$options = explode(',',$widget['size']);
			if (!($label = $widget['label']) || in_array($subtype,array('header','sortheader')) && $options[1])
			{
				if (in_array($subtype,array('customfilter','sortheader'))) $subtype = array_shift($options);

				$label = $options[0];
			}
			list(,,$col,$sub) = explode('/',$path);
			$row = (int)$col;
			$col = substr($col,$row > 9 ? 2 : 1);
			if (($label{0} == '@' || strchr($lable,'$') !== false) && is_array($cols['__content__']))
			{
				$label = etemplate::expand_name($label,$col,$row,'','',$cols['__content__']);
			}
			if (!isset($cols[$widget['name']]) && $label)
			{
				$label = substr($label,-3) == '...' ? lang(substr($label,0,-3)) : lang($label);
				
				if (!$sub)
				{
					$cols[$widget['name']] = $label;
				}
				elseif (strpos($cols[$col],$label) === false)
				{
					$cols[$col] .= ($cols[$col] ? ', ' : '').$label;
					$cols['name2col'][$col] = $col;
				}
			}
			$cols['name2col'][$widget['name']] = $col;
			
			//echo "<p>$path: $widget[name] $label</p>\n";
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
			$nm_global = &$GLOBALS['egw_info']['etemplate']['nextmatch'];
			//echo "<p>nextmatch_widget.post_process(type='$extension_data[type]', name='$name',value_in=".print_r($value_in,true).",order='$nm_global[order]'): value = "; _debug_array($value);
			switch($extension_data['type'])
			{
				case 'nextmatch':
					break;
					
				case 'nextmatch-sortheader':
					if ($value_in)
					{
						$nm_global['order'] = $this->last_part($name);
						$nm_global['default_sort'] = $extension_data['default_sort'];
					}
					return False;	// dont report value back, as it's in the wrong location (rows)

				default:
				case 'select-account':		// used by nextmatch-accountfilter
				case 'nextmatch-filterheader':
					if ((string)$value_in != (string)$extension_data['old_value'])
					{
						//echo "<p>setting nm_global[filter][".$this->last_part($name)."]='$value_in' (was '$extension_data[old_value]')</p>\n";
						$nm_global['filter'][$this->last_part($name)] = $value_in;
					}
					return False;	// dont report value back, as it's in the wrong location (rows)
					
				case 'nextmatch-header':
					return False;	// nothing to report
			}
			$old_value = $extension_data;

			$value['start'] = $old_value['start'];	// need to be set, to be reported back
			$value['return'] = $old_value['return'];

			if (is_array($value['bottom']))			// we have a second bottom-bar
			{
				$inputs = array('search','cat_id','filter','filter2','num_rows');
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
				if ($value['bottom']['savecols'])
				{
					$value['selectcols'] = $value['bottom']['selectcols'];
					$value['default_prefs'] = $value['bottom']['default_prefs'];
				}
				unset($value['bottom']);
			}
			if (isset($old_value['num_rows']) && !is_null($value['num_rows']) && $value['num_rows'] != $old_value['num_rows'])
			{
				$loop = true;	// num_rows changed
			}
			$value['num_rows'] = (int) $value['num_rows'];
			$max = $value['num_rows'] ? $value['num_rows'] : (int)$GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'];
			
			
			if(strpos($value['search'],'xjxquery')) {
				// We deal with advancedsearch
				$aXml = $value['search'];
				$value['search'] = '';
				$aXml = str_replace("<xjxquery><q>","&",$aXml);
				$aXml = str_replace("</q></xjxquery>","",$aXml);
				$value['advsearch_cont'] = explode('&',$aXml);
				//$GLOBALS['egw']->boetemplate = &CreateObject('etemplate.boetemplate');
				//print_r($GLOBALS['egw']->boetemplate->get_array($value['advsearch_cont'],'exec[]',true));
				//$value['advsearch_cont'] = preg_replace("/exec\[ (.*)/",'$1',$value['advsearch_cont']);
				//$value['advsearch_cont'] = preg_replace("\]",'',$value['advsearch_cont']);
				//print_r($value['advsearch_cont']);
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
			elseif ($value['first'] || $value['left'] && $old_value['start'] < $max)
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
				$value['start'] = (int) (($old_value['total']-1) / $max) * $max;
				unset($value['last']);
				$loop = True;
			}
			elseif ($nm_global['order'])
			{
				$value['order'] = $nm_global['order'];
				if ($old_value['order'] != $value['order'])
				{
					$value['sort'] = $nm_global['default_sort'];
				}
				else
				{
					$value['sort'] = $old_value['sort'] != 'DESC' ? 'DESC' : 'ASC';
				}
				//echo "<p>old_value=$old_value[order]/$old_value[sort] ==> $value[order]/$value[sort]</p>\n";
				$loop = True;
			}
			elseif ($nm_global['filter'])
			{
				if (!is_array($value['col_filter'])) $value['col_filter'] = array();

				$value['col_filter'] += $nm_global['filter'];
				$loop = True;
			}
			elseif (isset($value['searchletter']))
			{
				list($value['searchletter']) = @each($value['searchletter']);
				if ($value['searchletter'] === 'all') $value['searchletter'] = false;
				$loop = True;
			}
			if ($value['savecols'])
			{
				$name = is_object($extension_data['template']) ? $extension_data['template']->name : $extension_data['template'];
				list($app) = explode('.',$name);
				if (isset($extension_data['columnselection_pref'])) $name = $extension_data['columnselection_pref'];
				$pref = !$GLOBALS['egw_info']['user']['apps']['admin'] && $value['default_prefs'] ? 'default' : 'user';
				$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->add($app,'nextmatch-'.$name,is_array($value['selectcols']) ? 
					implode(',',$value['selectcols']) : $value['selectcols'],$pref);
				$GLOBALS['egw']->preferences->save_repository(false,$pref);
				$loop = True;
			}
			return True;
		}
	}
