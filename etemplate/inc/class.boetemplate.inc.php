<?php
	/**************************************************************************\
	* eGroupWare - EditableTemplates - Business Objects                       *
	* http://www.eGroupWare.org                                                *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	include_once(PHPGW_INCLUDE_ROOT . '/etemplate/inc/class.soetemplate.inc.php');

	/**
	 * Business Object for eTemplates, extending the Storage Object
	 *
	 * Not so much so far, as the most logic is still in the UI-class
	 *
	 * @package etemplate
	 * @author RalfBecker-AT-outdoor-training.de
	 * @license GPL
	 */
	class boetemplate extends soetemplate
	{
		var $extensions = array();

		var $types = array(
			'label'	=> 'Label',			// Label $cell['label'] is (to be translated) textual content
			'text'	=> 'Text',			// Textfield 1 Line (size = [length][,maxlength])
			'int'	=> 'Integer',		// like text, but only numbers (size = [min][,max])
			'float'	=> 'Floating Point', // --------------- " --------------------------
			'textarea'=> 'Textarea',	// Multiline Text Input (size = [rows][,cols])
			'htmlarea' => 'Formatted Text (HTML)',
			'checkbox'=> 'Checkbox',
			'radio'	=> 'Radiobutton',	// Radiobutton (size = value if checked)
			'button'=> 'Submitbutton',
			'hrule'	=> 'Horizontal Rule',
			'template' => 'Template',	// $cell['name'] contains template-name, $cell['size'] index into $content,$cname,$readonlys
			'image'	=> 'Image',			// label = url, name=link or method, help=alt or title
			'date'	=> '', 				// Datefield, size='' timestamp or size=format like 'm/d/Y'
			'select'=>	'Selectbox',	// Selectbox ($sel_options[$name] or $content[options-$name] is array with options)
										// if size > 1 then multiple selections, size lines showed
			'html'	=> 'Html',			// Raw html in $content[$cell['name']]
			'file'	=> 'FileUpload',	// show an input type='file', set the local name as ${name}_path
			'vbox'	=> 'VBox',			// a (vertical) box to contain widgets in rows, size = # of rows
			'hbox'	=> 'HBox',			// a (horizontal) box to contain widgets in cols, size = # of cols 
			'groupbox' => 'GroupBox',	// a box with a label containing other elements to group them (html: fieldset)
			'box'	=> 'Box',			// just a container for widgets (html: div)
			'grid'	=> 'Grid',			// tabular widget containing rows with columns of widgets
			'deck'	=> 'Deck'			// a container of elements where only one is visible, size = # of elem.
		);

		/**
		 * constructor of class
		 *
		 * Calls the constructor of soetemplate
		 *
		 * @param string/array $name name of etemplate or array with name and other keys
		 * @param string/array $load_via name or array with keys of other etemplate to load in order to get $name
		 */
		function boetemplate($name='',$load_via='')
		{
			$this->soetemplate();

			$tname = &$name;
			if (is_array($name))
			{
				$tname = &$name['name'];
			}
			$tname = (strstr($tname,'.') === False && !empty($tname) ?
				(is_array($load_via) ? $load_via['name'] : $load_via).'.':'').$tname;

			if (empty($tname) || !$this->read($name,'','',0,'',$load_via))
			{
				$this->init($name);
			}
		}

		/**
		 * checks if a grid row or column is disabled
		 *
		 * Expression: [!][@]val[=[@]check] 
		 * Parts in square brackets are optional, a ! negates the expression, @val evaluates to $content['val']
		 * if no =check is given all set non-empty and non-zero strings are true (standard php behavior)
		 *
		 * @param string $disabled expression to check, eg. "!@var" for !$content['var']
		 * @param array $content the content-array in the context of the grid
		 * @return boolean true if the row/col is disabled or false if not
		 */
		function check_disabled($disabled,$content)
		{
			//return False;
			if ($not = $disabled[0] == '!')
			{
				$disabled = substr($disabled,1);
			}
			list($val,$check_val) = $vals = explode('=',$disabled);

			if ($val[0] == '@')
			{
				$val = $this->get_array($content,substr($val,1));
			}
			if ($check_val[0] == '@')
			{
				$check_val = $this->get_array($content,substr($check_val,1));
			}
			$result = count($vals) == 1 ? $val != '' : $val == $check_val;
			if ($not) $result = !$result;
			//echo "<p>check_disabled: '".($not?'!':'')."$disabled' = '$val' ".(count($vals) == 1 ? '' : ($not?'!':'=')."= '$check_val'")." = ".($result?'True':'False')."</p>\n";
			return $result;
		}

		/**
		 * allows a few variables (eg. row-number) to be used in field-names
		 *
		 * This is mainly used for autorepeat, but other use is possible.
		 * You need to be aware of the rules PHP uses to expand vars in strings, a name
		 * of "Row$row[length]" will expand to 'Row' as $row is scalar, you need to use
		 * "Row${row}[length]" instead. Only one indirection is allowd in a string by php !!!
		 * Out of that reason we have now the variable $row_cont, which is $cont[$row] too.
		 * Attention !!!
		 * Using only number as index in field-names causes a lot trouble, as depending
		 * on the variable type (which php determines itself) you used filling and later
		 * accessing the array it can by the index or the key of an array element.
		 * To make it short and clear, use "Row$row" or "$col$row" not "$row" or "$row$col" !!!
		 *
		 * @param sring $name the name to expand
		 * @param int $c is the column index starting with 0 (if you have row-headers, data-cells start at 1)
		 * @param int $row is the row number starting with 0 (if you have col-headers, data-cells start at 1)
		 * @param int $c_ is the value of the previous template-inclusion,
		 * 	eg. the column-headers in the eTemplate-editor are templates itself,
		 * 	to show the column-name in the header you can not use $col as it will
		 * 	be constant as it is always the same col in the header-template,
		 * 	what you want is the value of the previous template-inclusion.
		 * @param int $row_ is the value of the previous template-inclusion,
		 * @param array $cont content of the template, you might use it to generate button-names with id values in it:
		 * 	"del[$cont[id]]" expands to "del[123]" if $cont = array('id' => 123)
		 * @return string the expanded name
		 */
		function expand_name($name,$c,$row,$c_='',$row_='',$cont='')
		{
			$is_index_in_content = $name[0] == '@';
			if (strstr($name,'$') !== False)
			{
				if (!$cont)
				{
					$cont = array();
				}
				$col = $this->num2chrs($c-1);	// $c-1 to get: 0:'@', 1:'A', ...
				$col_ = $this->num2chrs($c_-1);
				$row_cont = $cont[$row];
				$col_row_cont = $cont[$col.$row];

				eval('$name = "'.$name.'";');
			}
			if ($is_index_in_content)
			{
				$name = $this->get_array($cont,substr($name,1));
			}
			return $name;
		}

		/**
		 * Checks if we have an row- or column autorepeat and sets the indexes for $content, etc.
		 *
		 * Autorepeat is important to allow a variable numer of rows or cols, eg. for a list.
		 * The eTemplate has only one (have to be the last) row or column, which gets
		 * automaticaly repeated as long as content is availible. To check this the content
		 * has to be in an sub-array of content. The index / subscript into content is
		 * determined by the content of size for templates or name for regular fields.
		 * An autorepeat is defined by an index which contains variables to expand.
		 * (vor variable expansion in names see expand_names). Usually I use the keys
		 * $row: 0, 1, 2, 3, ... for only rows, $col: '@', 'A', 'B', 'C', ... for only cols or
		 * $col$row: '@0','A0',... '@1','A1','B1',... '@2','A2','B2',... for both rows and cells.
		 * In general everything expand_names can generate is ok - see there.
		 * As you usually have col- and row-headers, data-cells start with '1' or 'A' !!!
		 *
		 * @param array $cell with data of cell: name, type, size, ...
		 * @param int $c,$r col/row index starting from 0
		 * @param string &$idx returns the index in $content and $readonlys (NOT $sel_options !!!)
		 * @param string &$idx_cname returns the basename for the form-name: is $idx if only one value
		 *		(no ',') is given in size (name (not template-fields) are always only one value)
		 * @param boolean $check_col to check for col- or row-autorepeat
		 * @return boolean true if cell is autorepeat (has index with vars / '$') or false otherwise
		 */
		function autorepeat_idx($cell,$c,$r,&$idx,&$idx_cname,$check_col=False)
		{
			$org_idx = $idx = $cell[ $cell['type'] == 'template' ? 'size' : 'name' ];

			$idx = $this->expand_name($idx,$c,$r);
			if (!($komma = strpos($idx,',')))
			{
				$idx_cname = $idx;
			}
			else
			{
				$idx_cname = substr($idx,1+$komma);
				$idx = substr($idx,0,$komma);
			}
			$Ok = False;
			$pat = $org_idx;
			while (!$Ok && ($pat = strstr($pat,'$')))
			{
				$pat = substr($pat,$pat[1] == '{' ? 2 : 1);

				if ($check_col)
				{
					$Ok = $pat[0] == 'c' && !(substr($pat,0,4) == 'cont' ||
							substr($pat,0,2) == 'c_' || substr($pat,0,4) == 'col_');
				}
				else
				{
					$Ok = $pat[0] == 'r' && !(substr($pat,0,2) == 'r_' || 
						substr($pat,0,4) == 'row_' && substr($pat,0,8) != 'row_cont');
				}
			}
			if ($this->name && $this->name == $this->debug)
			{
				echo "$this->name ".($check_col ? 'col' : 'row')."-check: c=$c, r=$r, idx='$org_idx'='$idx' idx_cname='$idx_cname' ==> ".($Ok?'True':'False')."<p>\n";
			}
			return $Ok;
		}

		/**
		 *  creates a new appsession-id via microtime()
		 */
		function appsession_id()
		{
			list($msec,$sec) = explode(' ',microtime());
			$id = $GLOBALS['phpgw_info']['flags']['currentapp'] . (intval(1000000 * $msec) + 1000000 * ($sec % 100000));
			//echo "<p>microtime()=".microtime().", sec=$sec, msec=$msec, id=$id</p>\n";
			return $id;
		}

		/**
		 * saves content,readonlys,template-keys, ... via the appsession function
		 *
		 * As a user may open several windows with the same content/template wie generate a location-id from microtime
		 * which is used as location for appsession to descriminate between the different windows. This location-id
		 * is then saved as a hidden-var in the form. The above mentions session-id has nothing to do / is different
		 * from the session-id which is constant for all windows opened in one session.
		 *
		 * @param array $data the data to save
		 * @param string $id the id to use or '' to generate a new id
		 * @return string location-id
		 */
		function save_appsession($data,$id='')
		{
			if (!$id)
			{
				$id = $this->appsession_id;
			}
			$GLOBALS['phpgw']->session->appsession($id,'etemplate',$data);

			return $id;
		}

		/**
		 * gets content,readonlys,template-keys, ... back from the appsession function
		 *
		 * @param string $id the location-id
		 * @return array with session-data
		 */
		function get_appsession($id)
		{
			$data = $GLOBALS['phpgw']->session->appsession($id,'etemplate');

			//echo "<p>get_appsession('$id') data="; _debug_array($data);

			// if we delete the returned value here, we cant get back (back-button),
			// not even to a non-submitted page
			//$GLOBALS['phpgw']->session->appsession_delete($id,'etemplate');

			return $data;
		}

		/**
		 * gets an attribute in a named cell
		 *
		 * @static 
		 * @param string $name cell-name
		 * @param string $attr attribute-name
		 * @return mixed the attribute or False if named cell not found
		 */
		function &get_cell_attribute($name,$attr)
		{
			return $this->set_cell_attribute($name,$attr,NULL);
		}

		/**
		 * set an attribute in a named cell if val is not NULL else return the attribute
		 *
		 * @static 
		 * @param sting $name cell-name
		 * @param string $attr attribute-name
		 * @param mixed $val if not NULL sets attribute else returns it
		 * @return mixed number of changed cells or False, if none changed
		 */
		function &set_cell_attribute($name,$attr,$val)
		{
			//echo "<p>set_cell_attribute(tpl->name=$this->name, name='$name', attr='$attr',val='$val')</p>\n";

			$extra = array(false,$name,$attr,$val);
			$result =& $this->widget_tree_walk('set_cell_attribute_helper',$extra);
			
			if (is_null($val))
			{
				return $result;
			}
			return $extra[0];
		}
		
		/**
		 *  disables all cells with name == $name
		 *
		 * @param sting $name cell-name
		 * @return mixed number of changed cells or False, if none changed
		 */
		function disable_cells($name)
		{
			return $this->set_cell_attribute($name,'disabled',True);
		}
		
		/**
		 * set one or more attibutes for row $n
		 *
		 * @deprecated as it uses this->data
		 * @param int $n numerical row-number starting with 1 (!)
		 * @param string $height percent or pixel or '' for no height
		 * @param string $class name of css class (without the leading '.') or '' for no class
		 * @param string $valign alignment (top,middle,bottom) or '' for none
		 * @param boolean $disabled True or expression or False to disable or enable the row (Only the number 0 means dont change the attribute !!!)
		 */
		function set_row_attributes($n,$height=0,$class=0,$valign=0,$disabled=0)
		{
			list($old_height,$old_disabled) = explode(',',$this->data[0]["h$n"]);
			$disabled = $disabled !== 0 ? $disabled : $old_disabled;
			$this->data[0]["h$n"] = ($height !== 0 ? $height : $old_height).
				($disabled ? ','.$disabled : '');
			list($old_class,$old_valign) = explode(',',$this->data[0]["c$n"]);
			$valign = $valign !== 0 ? $valign : $old_valign;
			$this->data[0]["c$n"] = ($class !== 0 ? $class : $old_class).
				($valign ? ','.$valign : '');
		}

		/**
		 * disables row $n
		 *
		 * @deprecated as it uses this->data
		 * @param int $n numerical row-number starting with 1 (!)
		 * @param boolean $enable=false can be used to re-enable a row if set to True
		 */
		function disable_row($n,$enable=False)
		{
			$this->set_row_attributes($n,0,0,0,!$enable);
		}

		/**
		 * set one or more attibutes for column $c
		 *
		 * @deprecated as it uses this->data
		 * @param int/string $c numerical column-number starting with 0 (!), or the char-code starting with 'A'
		 * @param string $width percent or pixel or '' for no height
		 * @param mixed $disabled=0 True or expression or False to disable or enable the column (Only the number 0 means dont change the attribute !!!)
		 */
		function set_column_attributes($c,$width=0,$disabled=0)
		{
			if (is_numeric($c))
			{
				$c = $this->num2chrs($c);
			}
			list($old_width,$old_disabled) = explode(',',$this->data[0][$c]);
			$disabled = $disabled !== 0 ? $disabled : $old_disabled;
			$this->data[0][$c] = ($width !== 0 ? $width : $old_width).
				($disabled ? ','.$disabled : '');
		}

		/**
		 * disables column $c
		 *
		 * @deprecated as it uses this->data
		 * @param int/string $c numerical column-number starting with 0 (!), or the char-code starting with 'A'
		 * @param boolean $enable can be used to re-enable a column if set to True
		*/
		function disable_column($c,$enable=False)
		{
			$this->set_column_attributes($c,0,!$enable);
		}

		/**
		 * trys to load the Extension / Widget-class from the app or etemplate
		 *
		 * @param string $name name of the extension, the classname should be class.${name}_widget.inc.php
		 *	the $name might be "$name.$app" to give a app-name (default is the current app,or template-name)
		 * @return string/boolean human readable name or false if not found/loadable
		 */
		function loadExtension($type)
		{
			list($class,$app) = explode('.',$type);
			$class .= '_widget';

			if ($app == '')
			{
				$app = $GLOBALS['phpgw_info']['flags']['current_app'];
			}
			if (!file_exists(PHPGW_SERVER_ROOT."/$app/inc/class.$class.inc.php"))
			{
				list($app) = explode('.',$this->name);
			}
			if (!file_exists(PHPGW_SERVER_ROOT."/$app/inc/class.$class.inc.php"))
			{
				$app = 'etemplate';
			}
			if (!file_exists(PHPGW_SERVER_ROOT."/$app/inc/class.$class.inc.php"))
			{
				return $GLOBALS['phpgw_info']['etemplate']['extension'][$type] = False;
			}
			$GLOBALS['phpgw_info']['etemplate']['extension'][$type] = CreateObject($app.'.'.$class,$ui='html');

			return $GLOBALS['phpgw_info']['etemplate']['extension'][$type]->human_name;
		}

		/**
		 * checks if extension is loaded (load it if it isnt) and optional checks if it has a given method
		 *
		 * @param string $name name of the extension, the classname should be class.${name}_widget.inc.php
		 *	the $name might be "$name.$app" to give a app-name (default is the current app,or template-name)
		 * @param string $function 'pre_process', 'post_process' or 'render'
		 * @return boolean true if the extension (incl. method) exists, else false
		 */
		function haveExtension($type,$function='')
		{
			return ($GLOBALS['phpgw_info']['etemplate']['extension'][$type] || $this->loadExtension($type,$ui)) &&
			        ($function == '' || $GLOBALS['phpgw_info']['etemplate']['extension'][$type]->public_functions[$function]);
		}

		/**
		 * executes the pre_process-function of the extension $cell[type]
		 *
		 * @param string $type type of the extension
		 * @param string $name form-name of this widget/field (used as a unique index into extension_data)
		 * @param mixed  &$value value of the extensions content(-array)
		 * @param array &$cell table-cell on which the extension operates
		 * @param array &$readonlys value of the extensions readonly-setting(-array)
		 * @return mixed the return-value of the extensions preprocess function
		 */
		function extensionPreProcess($type,$name,&$value,&$cell,&$readonlys)
		{
			if (!$this->haveExtension($type))
			{
				return False;
			}
			return $GLOBALS['phpgw_info']['etemplate']['extension'][$type]->pre_process($name,$value,$cell,$readonlys,
				$GLOBALS['phpgw_info']['etemplate']['extension_data'][$name],$this);
		}

		/**
		 * executes the post_process-function of the extension $cell[type]
		 *
		 * @param string $type name of the extension
		 * @param string $name form-name of this widget/field (used as a unique index into extension_data)
		 * @param mixed &$value returns the value of the extensions content(-array)
		 * @param mixed $value_in unprocessed value, eg. as posted by the browser
		 * @return boolean True if a value should be returned (default for no postprocess fkt.), else False
		 */
		function extensionPostProcess($type,$name,&$value,$value_in)
		{
			if (!$this->haveExtension($type,'post_process'))
			{
				return True;
			}
			return $GLOBALS['phpgw_info']['etemplate']['extension'][$type]->post_process($name,$value,
				$GLOBALS['phpgw_info']['etemplate']['extension_data'][$name],
				$GLOBALS['phpgw_info']['etemplate']['loop'],$this,$value_in);
		}

		/**
		 * executes the render-function of the extension $cell[type]
		 *
		 * @param string $type name of the extension
		 * @param string $name form-name of this widget/field (used as a unique index into extension_data)
		 * @param mixed &$value value of the extensions content(-array)
		 * @param array &$cell table-cell on which the extension operates
		 * @param array &$readonlys value of the extensions readonly-setting(-array)
		 * @return mixed return-value of the render function
		 */
		function extensionRender($type,$name,&$value,&$cell,$readonly)
		{
			if (!$this->haveExtension($type,'render'))
			{
				return False;
			}
			return $GLOBALS['phpgw_info']['etemplate']['extension'][$type]->render($cell,$name,$value,$readonly,
				$GLOBALS['phpgw_info']['etemplate']['extension_data'][$name],$this);
		}

		/**
		 * checks if $idx is set in array $arr
		 *
		 * for one level of subindes identical to isset($arr[$idx])
		 *
		 * @static 
		 * @param array $arr array to check
		 * @param string $idx may contain multiple subindex (eg.'x[y][z]')
		 * @return boolean true if set, else false
		 */
		function isset_array($arr,$idx)
		{
			$idxs = explode('[',str_replace(']','',$idx));
			$last_idx = array_pop($idxs);
			$pos = &$arr;
			foreach($idxs as $idx)
			{
				if (!is_array($pos))
				{
					return False;
				}
				$pos = &$pos[$idx];
			}
			return isset($pos[$last_idx]);
		}

		/**
		 * sets $arr[$idx] = $val
		 *
		 * This works for non-trival indexes like 'a[b][c]' too: $arr['a']['b']['c'] = $val;
		 *
		 * @static 
		 * @param array &$arr the array to search
		 * @param string $idx the index, may contain sub-indices like a[b], see example below
		 * @param mixed $val value to set
		 */
		function set_array(&$arr,$idx,$val)
		{
			if (!is_array($arr))
			{
				die('set_array() $arr is no array<br>'.function_backtrace());
			}
			$idxs = explode('[',str_replace(']','',$idx));
			$pos = &$arr;
			foreach($idxs as $idx)
			{
				$pos = &$pos[$idx];
			}
			$pos = $val;
		}

		/**
		 * return a reference to $arr[$idx]
		 *
		 * This works for non-trival indexes like 'a[b][c]' too: it returns &$arr[a][b][c]
		 * $sub = get_array($arr,'a[b]'); $sub = 'c'; is equivalent to $arr['a']['b'] = 'c';
		 *
		 * @static 
		 * @param array $arr the array to search, referenz as a referenz gets returned
		 * @param string $idx the index, may contain sub-indices like a[b], see example below
		 * @param boolean $reference_into default False, if True none-existing sub-arrays/-indices get created to be returned as referenz, else False is returned
		 * @return mixed reference to $arr[$idx] or false if $idx is not set and not $reference_into
		 */
		function &get_array(&$arr,$idx,$reference_into=False)
		{
			if (!is_array($arr))
			{
				die('set_array() $arr is no array<br>'.function_backtrace());
			}
			$idxs = explode('[',str_replace(']','',$idx));
			$pos = &$arr;
			foreach($idxs as $idx)
			{
				if (!is_array($pos) && !$referenz_info)
				{
					return False;
				}
				$pos = &$pos[$idx];
			}
			return $pos;
		}

		/**
		 * unsets $arr[$idx]
		 *
		 * This works for non-trival indexes like 'a[b][c]' too
		 * unset_array($arr,'a[b]'); is equivalent to unset($arr['a']['b']);
		 *
		 * @static 
		 * @param array $arr the array to search, referenz as a referenz gets returned
		 * @param string $idx the index, may contain sub-indices like a[b], see example below
		 */
		function unset_array(&$arr,$idx)
		{
			if (!is_array($arr))
			{
				die('set_array() $arr is no array<br>'.function_backtrace());
			}
			$idxs = explode('[',str_replace(']','',$idx));
			$last_idx = array_pop($idxs);
			$pos = &$arr;
			foreach($idxs as $idx)
			{
				$pos = &$pos[$idx];
			}
			unset($pos[$last_idx]);
		}

		/**
		 * merges $old and $new, content of $new has precedence over $old
		 *
		 * THIS IS NOT THE SAME AS PHP4's functions: 
		 * - array_merge, as it calls itself recursive for values which are arrays.
		 * - array_merge_recursive accumulates values with the same index and $new does NOT overwrite $old
		 *
		 * @static 
		 * @param array $old
		 * @param array $new
		 * @return array the merged array
		 */
		function complete_array_merge($old,$new)
		{
			if (is_array($new))
			{
				foreach($new as $k => $v)
				{
					if (!is_array($v) || !isset($old[$k]))
					{
						$old[$k] = $v;
					}
					else
					{
						$old[$k] = $this->complete_array_merge($old[$k],$v);
					}
				}
			}
			return $old;
		}
		
		/**
		 * returns a reference to a widget in the widget's children tree spezified by a path
		 *
		 * The path get's generated by the widget_tree_walk() methode and consists of the keys of the children arrays. 
		 * For the 3. Column in the 2. row of a grid which is the only widget in the children-tree it is eg.: "/0/2C"
		 *
		 * @param string $path path in the widget tree
		 * @param int $ancestor=0 0: widget itself, 1: parent, 2: grand-parent, ...
		 * @return array referenz to the widget spezified or null, if it's not found
		 */
		function &get_widget_by_path($path,$ancestor=0)
		{
			//echo "<p>boetemplate::get_widget_by_path('$path',$ancestor)</p>\n";
			$path_parts = explode('/',$path);
			while($ancestor-- > 0)
			{
				if (array_pop($path_parts) === '') return null;
			}
			$path = implode('/',$path_parts);
			if ($path == '/' || $path === '') return $this->children;

			return $this->widget_tree_walk('get_widget_by_path_helper',$path);
		}

		/**
		 * generated a file-name from an eTemplates, name, template(-set) and lang
		 *
		 * @param string/array $name name of template or array('name'=>$name,'template'=>$template,'lang'=>$lang)
		 * @param string $template template-set
		 * @param string $lang language to use
		 * @return string
		 */
		function cache_name($name='',$template='default',$lang='default')
		{
			if (empty($name))
			{
				$name     = $this->name;
				$template = $this->template;
				$lang     = $this->lang;
			}
			elseif (is_array($name))
			{
				$template = $name['template'];
				$lang     = $name['lang'];
				$name     = $name['name'];
			}
			if (empty($template))
			{
				$template = 'default';
			}
			$cname = $template . '/' . $name . (!empty($lang) && $lang != 'default' ? '.' . $lang : '');
			//echo "cache_name('$name','$template','$lang') = '$cname'";

			return $cname;
		}

		/**
		 * stores the etemplate in the cache in phpgw_info
		 */
		function store_in_cache()
		{
			//echo "<p>store_in_cache('$this->name','$this->template','$this->lang','$this->version')</p>\n";
			$GLOBALS['phpgw_info']['etemplate']['cache'][$this->cache_name()] = $this->as_array(1);
		}

		/*
		 * returns true if a given eTemplate is in the cache
		 *
		 * @param string/array $name name of template or array('name'=>$name,'template'=>$template,'lang'=>$lang)
		 * @param string $template template-set
		 * @param string $lang language to use
		 * @param int $group to use the template for
		 * @param string $version of the template
		 * @return boolean
		 */
		function in_cache($name,$template='default',$lang='default',$group=0,$version='')
		{
			$cname = $this->cache_name($name,$template,$lang);
			if (is_array($name))
			{
				$version = $name['version'];
				$name    = $name['name'];
			}
			if (!isset($GLOBALS['phpgw_info']['etemplate']['cache'][$cname]) ||
			    !empty($version) && $GLOBALS['phpgw_info']['etemplate']['cache'][$cname]['version'] != $version)
			{
				//echo " NOT found in cache</p>\n";
				return False;
			}
			//echo " found in cache</p>\n";
			return $cname;
		}

		/*
		 * reads the content of an eTemplate from the cache into the current object
		 *
		 * same as read but only via the cache
		 *
		 * @param string/array $name name of template or array('name'=>$name,'template'=>$template,'lang'=>$lang)
		 * @param string $template template-set
		 * @param string $lang language to use
		 * @param int $group to use the template for
		 * @param string $version of the template
		 * @return boolean true if the eTemplate was found in the cache
		 */
		function read_from_cache($name,$template='default',$lang='default',$group=0,$version='')
		{
			//if (is_array($name)) $version = $name['version']; echo "<p>read_from_cache(,,,version='$version'): ";
			if ($cname = $this->in_cache($name,$template,$lang,$group))
			{
				$this->init($GLOBALS['phpgw_info']['etemplate']['cache'][$cname]);

				return True;
			}
			return False;
		}

		/**
		 * reads an eTemplate from the cache or database / filesystem (and updates the cache)
		 *
		 * reimplementation of soetemplate::read to use and/or update the cache
		 *
		 * @param string $name name of the eTemplate or array with the values for all keys
		 * @param string $template template-set, '' loads the prefered template of the user, 'default' loads the default one '' in the db
		 * @param string $lang language, '' loads the pref. lang of the user, 'default' loads the default one '' in the db
		 * @param int $group id of the (primary) group of the user or 0 for none, not used at the moment !!!
		 * @param string $version version of the eTemplate
		 * @param mixed $load_via name/array of keys of etemplate to load in order to get $name (only as second try!)
		 * @return boolean True if a fitting template is found, else False
		 */
		function read($name,$template='default',$lang='default',$group=0,$version='',$load_via='')
		{
			if (is_array($name)) {
				$pname = &$name['name'];
			}
			else
			{
				$pname = &$name;
			}	
			if (empty($pname))
			{
				return False;
			}
			$parent = is_array($load_via) ? $load_via['name'] : $load_via;

         	if (strstr($pname,'.') === False && !empty($parent))
			{
				$pname = $parent . '.' . $pname;
			}
			if (!$this->read_from_cache($name,$template,$lang,$group,$version))
			{
				if (!soetemplate::read($name,$template,$lang,$group,$version))
				{
					if ($load_via && (is_string($load_via) ||
					    !isset($load_via['tpls_in_file']) || $load_via['tpls_in_file'] > 1))
					{
						soetemplate::read($load_via);
						return $this->read_from_cache($name,$template,$lang,$group,$version);
					}
					return False;
				}
				$this->store_in_cache();
			}
			return True;
		}

		/**
		 * saves eTemplate-object to db and update the cache
		 *
		 * reimplementation of soetemplate::save to update the cache
		 *
		 * @param string $name name of the eTemplate or array with the values for all keys
		 * @param string $template template-set or '' for the default one
		 * @param string $lang language or '' for the default one
		 * @param int $group id of the (primary) group of the user or 0 for none, not used at the moment !!!
		 * @param string $version version of the eTemplate
		 * @return the number of affected rows, 1 should be ok, 0 somethings wrong
		 */
		function save($name='',$template='.',$lang='.',$group=0,$version='.')
		{
			if ($result = soetemplate::save($name,$template,$lang,$group,$version))
			{
				$this->store_in_cache();
			}
			return $result;
		}
	}

	if (!function_exists('set_cell_attribute_helper'))
	{
		function &set_cell_attribute_helper(&$widget,&$extra)
		{
			// extra = array(0=>n,1=>name,2=>attr,3=>value)
			if ($widget['name'] == $extra[1])
			{
				if (is_null($extra[3])) 
				{
					$extra['__RETURN_NOW__'] = true;	// wouldnt work otherwise, if attr is not yet set == null
					return $widget[$extra[2]];
				}
				$widget[$extra[2]] = $extra[3];
				++$extra[0];
			}
		}
		
		function &get_widget_by_path_helper(&$widget,$extra,$path)
		{
			//echo "<p>path_searched='$extra', widget-path($widget[type]:$widget[name])='$path'</p>\n";
			if ($path == $extra) return $widget;
		}
	}
