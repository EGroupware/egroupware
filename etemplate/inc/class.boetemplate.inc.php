<?php
	/**************************************************************************\
	* phpGroupWare - EditableTemplates - Buiseness Objects                     *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	include(PHPGW_API_INC . '/../../etemplate/inc/class.soetemplate.inc.php');

	/*!
	@class boetemplate
	@author ralfbecker
	@abstract Buiseness Objects for eTemplates
	@discussion Not so much so far, as the most logic is still in the UI-class
	@param $types,$alings converts internal names/values to (more) human readible ones
	*/
	class boetemplate extends soetemplate
	{
		var $extensions = array();

		var $types = array(
			'label'	=> 'Label',			// Label $cell['label'] is (to be translated) textual content
			'text'	=> 'Text',			// Textfield 1 Line (size = [length][,maxlength])
			'int'		=> 'Integer',		// like text, but only numbers (size = [min][,max])
			'float'	=> 'Floating Point', // --------------- " --------------------------
			'textarea' => 'Textarea',	// Multiline Text Input (size = [rows][,cols])
			'checkbox'=> 'Checkbox',
			'radio'	=> 'Radiobutton',	// Radiobutton (size = value if checked)
			'button'	=> 'Submitbutton',
			'hrule'	=> 'Horizontal Rule',
			'template' => 'Template',	// $cell['name'] contains template-name, $cell['size'] index into $content,$cname,$readonlys
			'image' => 'Image',			// label = url, name=link or method, help=alt or title
			'date'	=> '', 			// Datefield, size='' timestamp or size=format like 'm/d/Y'
			'select'	=>	'Selectbox',	// Selectbox ($sel_options[$name] or $content[options-$name] is array with options)
												// if size > 1 then multiple selections, size lines showed
			'select-percent' => 'Select Percentage',
			'select-priority' => 'Select Priority',
			'select-access' => 'Select Access',
			'select-country' => 'Select Country',
			'select-state' => 'Select State',		// US-states
			'select-cat' => 'Select Category',		// Category-Selection, size: -1=Single+All, 0=Single, >0=Multiple with size lines
			'select-account' => 'Select Account',	// label=accounts(default),groups,both
																// size: -1=Single+not assigned, 0=Single, >0=Multiple
			'raw'		=> 'Raw',	// Raw html in $content[$cell['name']]
			'file' => 'FileUpload'	// show an input type='file', set the local name as ${name}_path
		);
		/*!
		@function boetemplate
		@abstract constructor of class
		@param $name     name of etemplate or array with name and other keys
		@param $load_via name/array with keys of other etemplate to load in order to get $name
		@discussion Calls the constructor of soetemplate
		*/
		function boetemplate($name='',$load_via='')
		{
			$this->public_functions += array(
				'disable_cells' => True,
				'set_cell_attribute' => True
			);
			$this->soetemplate();

			if (empty($name) || !$this->read($name,'','',0,'',$load_via))
			{
				$this->init($name);
			}
		}

		/*!
		@function expand_name
		@syntax expand_name( $name,$c,$row,$c_='',$row_='',$cont='' )
		@author ralfbecker
		@abstract allows a few variables (eg. row-number) to be used in field-names
		@discussion This is mainly used for autorepeat, but other use is possible.
		@discussion You need to be aware of the rules PHP uses to expand vars in strings, a name
		@discussion of "Row$row[length]" will expand to 'Row' as $row is scalar, you need to use
		@discussion "Row${row}[length]" instead. Only one indirection is allowd in a string by php !!!
		@discussion Out of that reason we have now the variable $row_cont, which is $cont[$row] too.
		@discussion Attention !!!
		@discussion Using only number as index in field-names causes a lot trouble, as depending
		@discussion on the variable type (which php determines itself) you used filling and later
		@discussion accessing the array it can by the index or the key of an array element.
		@discussion To make it short and clear, use "Row$row" or "$col$row" not "$row" or "$row$col" !!!
		@param $name the name to expand
		@param $c is the column index starting with 0 (if you have row-headers, data-cells start at 1)
		@param $row is the row number starting with 0 (if you have col-headers, data-cells start at 1)
		@param $c_, $row_ are the respective values of the previous template-inclusion,
		@param            eg. the column-headers in the eTemplate-editor are templates itself,
		@param            to show the column-name in the header you can not use $col as it will
		@param            be constant as it is always the same col in the header-template,
		@param            what you want is the value of the previous template-inclusion.
		@param $cont content array of the template, you might use it to generate button-names with
		@param       id values in it: "del[$cont[id]]" expands to "del[123]" if $cont = array('id' => 123)
		*/
		function expand_name($name,$c,$row,$c_='',$row_='',$cont='')
		{
			if (!$cont)
			{
				$cont = array();
			}
			$col = $this->num2chrs($c-1);	// $c-1 to get: 0:'@', 1:'A', ...
			$col_ = $this->num2chrs($c_-1);
			$row_cont = $cont[$row];

			eval('$name = "'.$name.'";');

			return $name;
		}

		/*!
		@function autorepeat_idx
		@abstract Checks if we have an row- or column autorepeat and sets the indexes for $content, etc.
		@discussion Autorepeat is important to allow a variable numer of rows or cols, eg. for a list.
		@discussion The eTemplate has only one (have to be the last) row or column, which gets
		@discussion automaticaly repeated as long as content is availible. To check this the content
		@discussion has to be in an sub-array of content. The index / subscript into content is
		@discussion determined by the content of size for templates or name for regular fields.
		@discussion An autorepeat is defined by an index which contains variables to expand.
		@discussion (vor variable expansion in names see expand_names). Usually I use the keys
		@discussion $row: 0, 1, 2, 3, ... for only rows, $col: '@', 'A', 'B', 'C', ... for only cols or
		@discussion $col$row: '@0','A0',... '@1','A1','B1',... '@2','A2','B2',... for both rows and cells.
		@discussion In general everything expand_names can generate is ok - see there.
		@discussion As you usually have col- and row-headers, data-cells start with '1' or 'A' !!!
		@param $cell array with data of cell: name, type, size, ...
		@param $c,$r col/row index starting from 0
		@param &$idx returns the index in $content and $readonlys (NOT $sel_options !!!)
		@param &$idx_cname returns the basename for the form-name: is $idx if only one value
		@param       (no ',') is given in size (name (not template-fields) are always only one value)
		@param $check_col boolean to check for col- or row-autorepeat
		@result true if cell is autorepeat (has index with vars / '$') or false otherwise
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
					$Ok = $pat[0] == 'r' && !(substr($pat,0,2) == 'r_' || substr($pat,0,4) == 'row_');
				}
			}
			if ($this->name && $this->name == $this->debug)
			{
				echo "$this->name ".($check_col ? 'col' : 'row')."-check: c=$c, r=$r, idx='$org_idx' ==> ".($Ok?'True':'False')."<p>\n";
			}
			return $Ok;
		}

		function appsession_id()
		{
			list($msec,$sec) = explode(' ',microtime());
			$id = $GLOBALS['phpgw_info']['flags']['currentapp'] . (intval(1000000 * $msec) + 1000000 * ($sec % 100000));
			//echo "<p>microtime()=".microtime().", sec=$sec, msec=$msec, id=$id</p>\n";
			return $id;
		}

		/*!
		@function save_appsession
		@syntax save_appsession( $data,$id='' )
		@author ralfbecker
		@abstract saves content,readonlys,template-keys, ... via the appsession function
		@discussion As a user may open several windows with the same content/template wie generate a location-id from microtime
		@discussion which is used as location for appsession to descriminate between the different windows. This location-id
		@discussion is then saved as a hidden-var in the form. The above mentions session-id has nothing to do / is different
		@discussion from the session-id which is constant for all windows opened in one session.
		@param $data the data to save
		@param $id the id to use or '' to generate a new id
		@result the location-id
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

		/*!
		@function get_appsession
		@syntax get_appsession( $id )
		@author ralfbecker
		@abstract gets content,readonlys,template-keys, ... back from the appsession function
		@param $id the location-id
		@result the session-data
		*/
		function get_appsession($id)
		{
			$data = $GLOBALS['phpgw']->session->appsession($id,'etemplate');

			//echo "<p>get_appsession('$id') data="; _debug_array($data);

			return $data;
		}

		/*!
		@function get_cell_attribute
		@syntax get_cell_attribute( $name,$attr )
		@author ralfbecker
		@abstract gets an attribute in a named cell
		@result the attribute or False if named cell not found
		*/
		function get_cell_attribute($name,$attr)
		{
			reset($this->data);
         while(list($row,$cols) = each($this->data))
			{
				while(list($col,$cell) = each($cols))
				{
					if ($cell['name'] == $name)
					{
						reset($this->data);
						return $this->data[$row][$col][$attr];
					}
				}
			}
			reset($this->data);

			return False;
		}

		/*!
		@function set_cell_attribute
		@syntax set_cell_attribute( $name,$attr,$val )
		@author ralfbecker
		@abstract set an attribute in a named cell
		@result the number of changed cells
		*/
		function set_cell_attribute($name,$attr,$val)
		{
			//echo "<p>set_cell_attribute(tpl->name=$this->name, name='$name', attr='$attr',val='$val')</p>\n";

			reset($this->data);
			$n = 0;
         while(list($row,$cols) = each($this->data))
			{
				while(list($col,$cell) = each($cols))
				{
					if ($cell['name'] == $name)
					{
						$this->data[$row][$col][$attr] = $val;
						++$n;
					}
					if ($cell['type'] == 'template' && (is_object($cell['obj']) || $cell['name'][0] != '@'))
					{
						if (!is_object($cell['obj']))
						{
							$this->data[$row][$col]['obj'] = CreateObject('etemplate.etemplate',$cell['name']);
						}
						$n += $this->data[$row][$col]['obj']->set_cell_attribute($name,$attr,$val);
					}
				}
			}
			reset($this->data);

			return $n;
		}

		/*!
		@function disable_cells
		@syntax disable_cells( $name )
		@author ralfbecker
		@abstract disables all cells with name == $name
		*/
		function disable_cells($name)
		{
			return $this->set_cell_attribute($name,'disabled',True);
		}

		/*!
		@function loadExtension
		@syntax loadExtension( $type )
		@author ralfbecker
		@abstact trys to load the Extension / Widget-class from the app or etemplate
		@param $name name of the extension, the classname should be class.${name}_widget.inc.php
		@discussion the $name might be "$name.$app" to give a app-name (default is the current app,or template-name)
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

		function haveExtension($type,$function='')
		/*
		@function haveExtension
		@syntax haveExtension($type)
		@abstract checks if extension is loaded and load it if it isnt
		*/
		{
			return ($GLOBALS['phpgw_info']['etemplate']['extension'][$type] || $this->loadExtension($type,$ui)) &&
			        ($function == '' || $GLOBALS['phpgw_info']['etemplate']['extension'][$type]->public_functions[$function]);
		}

		function extensionPreProcess(&$cell,&$value,&$readonlys)
		/*
		@function extensionPreProcess
		@syntax extensionPreProcess(&$cell,&$value,&$readonlys)
		@param &$cell table-cell on which the extension operates
		@param &$value value of the extensions content(-array)
		@param &$readonlys value of the extensions readonly-setting(-array)
		@abstract executes the pre_process-function of the extension $cell[]type]
		*/
		{
			if (!$this->haveExtension($type = $cell['type']))
			{
				return False;
			}
			return $GLOBALS['phpgw_info']['etemplate']['extension'][$type]->pre_process($cell,$value,
				$GLOBALS['phpgw_info']['etemplate']['extension_data'][$type][$cell['name']],$readonlys,$this);
		}

		function extensionPostProcess(&$cell,&$value)
		/*
		@function extensionPostProcess
		@syntax extensionPostProcess(&$cell,&$value)
		@abstract executes the post_process-function of the extension $cell[type]
		*/
		{
			if (!$this->haveExtension($type = $cell['type'],'post_process'))
			{
				return False;
			}
			return $GLOBALS['phpgw_info']['etemplate']['extension'][$type]->post_process($cell,$value,
				$GLOBALS['phpgw_info']['etemplate']['extension_data'][$type][$cell['name']],
				$GLOBALS['phpgw_info']['etemplate']['loop'],$this);
		}

		function extensionRender(&$cell,$form_name,&$value,$readonly)
		/*
		@function extensionRender
		@syntax extensionRender(&$cell,$form_name,&$value,$readonly)
		@abstract executes the render-function of the extension $cell[type]
		*/
		{
			if (!$this->haveExtension($type = $cell['type'],'render'))
			{
				return False;
			}
			return $GLOBALS['phpgw_info']['etemplate']['extension'][$name]->render($cell,$form_name,$value,$readonly,
				$GLOBALS['phpgw_info']['etemplate']['extension_data'][$type][$cell['name']],$this);
		}

		/*!
		@function isset_array
		@syntax isset_array( $arr,$idx )
		@author ralfbecker
		@abstract checks if idx, which may contain ONE subindex is set in array
		*/
		function isset_array($arr,$idx)
		{
			if (ereg('^([^[]*)\\[(.*)\\]$',$idx,$regs))
			{
				return $regs[2] && isset($arr[$regs[1]][$regs[2]]);
			}
			return isset($arr[$idx]);
		}

		function set_array(&$arr,$idx,$val,$set=True)
		{
			if (ereg('^([^[]*)(\\[.*\\])$',$idx,$regs))	// idx contains array-index
			{
				$arr_idx = '['.$regs[1].']'.$regs[2];
			}
			else
			{
				$arr_idx = "[$idx]";
			}
			if ($set)
			{
				$code = '$arr'.$arr_idx.' = $val;';
			}
			else
			{
				$code = 'unset($arr'.$arr_idx.');';
			}
			eval($code = str_replace(']',"']",str_replace('[',"['",$code)));

			//echo "set_array: $code = '$val'\n";
		}

		function &get_array(&$arr,$idx)
		{
			if (ereg('^([^[]*)(\\[.*\\])$',$idx,$regs))	// idx contains array-index
			{
				eval($code = str_replace(']',"']",str_replace('[',"['",'$val = &$arr['.$regs[1].']'.$regs[2].';')));
				//echo "get_array: $code = '$val'\n";
			}
			else
			{
				$val = &$arr[$idx];
			}
			return $val;
		}

		/*!
		@function complete_array_merge
		@syntax complete_array_merge( $old,$new )
		@author ralfbecker
		@abstract merges $old and $new, content of $new has precedence over $old
		@discussion THIS IS NOT THE SAME AS PHP4: array_merge (as it calls itself recursive for values which are arrays,
		@discussion if there key does NOT start with a '_' (array_merge just overwrites the old (sub)array)
		*/
		function complete_array_merge($old,$new)
		{
			@reset($new);
			while (list($k,$v) = @each($new))
			{
				if (!is_array($v) || !isset($old[$k]) || $k[0] == '_')
				{
					$old[$k] = $v;
				}
				else
				{
					$old[$k] = $this->complete_array_merge($old[$k],$v);
				}
			}
			return $old;
		}


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

		/*!
		@function store_in_cache()
		@abstract stores the etemplate in the cache in phpgw_info
		*/
		function store_in_cache()
		{
			//echo "<p>store_in_cache('$this->name','$this->template','$this->lang','$this->version')</p>\n";
			$GLOBALS['phpgw_info']['etemplate']['cache'][$this->cache_name()] = $this->as_array(1);
		}

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

		/*!
		@function read
		@abstract Reads an eTemplate from the cache or database / filesystem (and updates the cache)
		@param as discripted in soetemplate::read
		@param $load_via name/array of keys of etemplate to load in order to get $name (only as second try!)
		@result True if a fitting template is found, else False
		*/
		function read($name,$template='default',$lang='default',$group=0,$version='',$load_via='')
		{
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

		/*!
		@function save
		@abstract saves eTemplate-object to db and update the cache
		@params keys see soetemplate::save
		@result the number of affected rows, 1 should be ok, 0 somethings wrong
		*/
		function save($name='',$template='.',$lang='.',$group='',$version='.')
		{
			if ($result = soetemplate::save($name,$template,$lang,$group,$version))
			{
				$this->store_in_cache();
			}
			return $result;
		}
	};