<?php
	/**************************************************************************\
	* phpGroupWare - eTemplates - DB-Tools                                     *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	class db_tools
	{
		var $public_functions = array
		(
			'edit'         => True,
			'needs_save'   => True,
			'writeLangFile'=> True,
			//'admin'       => True,
			//'preferences' => True
		);

		var $debug = 0;
		var $editor;	// editor eTemplate
		var $data;		// Table definitions
		var $app;		// used app
		var $table;		// used table
		var $messages = array(
			'not_found' => 'Not found !!!',
			'select_one' => 'Select one ...',
			'writen' => 'File writen',
			'error_writing' => 'Error: writing file (no write-permission for the webserver) !!!',
			'give_table_name' => 'Please enter table-name first !!!',
			'new_table' => 'New table created',
			'select_app' => 'Select an app first !!!'
		);
		var $types = array(
			'auto'		=> 'auto',
			'blob'		=> 'blob',
			'char'		=> 'char',
			'date'		=> 'date',
			'decimal'	=> 'decimal',
			'float'		=> 'float',
			'int'			=> 'int',
			'longtext'	=> 'longtext',
			'text'		=> 'text',
			'timestamp'	=> 'timestamp',
			'varchar'	=> 'varchar'
		);
		var $setup_header = '<?php
  /**************************************************************************\\
  * phpGroupWare - Setup                                                     *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \\**************************************************************************/

  /* $Id$ */
';

		/*!
		@function db_tools()
		@abstract constructor of class
		*/
		function db_tools($lang_on_messages=True)
		{
			$this->editor = CreateObject('etemplate.etemplate','etemplate.db-tools.edit');
			$this->data = array();

			if (!is_array($GLOBALS['phpgw_info']['apps']) || !count($GLOBALS['phpgw_info']['apps']))
			{
				ExecMethod('phpgwapi.applications.read_installed_apps');
			}
			$this->apps = array();
			reset($GLOBALS['phpgw_info']['apps']);
			while (list($name,$data) = each($GLOBALS['phpgw_info']['apps']))
			{
				$this->apps[$name] = $data['title'];
			}
			if ($lang_on_messages)
			{
				reset($this->messages);
				while(list($key,$msg) = each($this->messages))
					$this->messages[$key] = lang($msg);
			}
		}

		/*!
		@function edit($content='',$msg='')
		@abstract this is the table editor (and the callback/submit-method too)
		*/
		function edit($content='',$msg = '')
		{
			if (isset($GLOBALS['HTTP_GET_VARS']['app']))
			{
				$this->app = $GLOBALS['HTTP_GET_VARS']['app'];
			}
			if (is_array($content))
			{
				if ($this->debug)
				{
					echo "content ="; _debug_array($content);
				}
				$this->app = $content['app'];	// this is what the user selected
				$this->table = $content['table_name'];
				$posted_app = $content['posted_app'];	// this is the old selection
				$posted_table = $content['posted_table'];
			}
			if ($posted_app && $posted_table &&		// user changed app or table
				 ($posted_app != $this->app || $posted_table != $this->table))
			{
				if ($this->needs_save('',$posted_app,$posted_table,$this->content2table($content)))
				{
					return;
				}
				$this->renames = array();
			}
			if (!$this->app)
			{
				$this->table = '';
				$table_names = array('' => lang('none'));
			}
			else
			{
				$this->read($this->app,$this->data);

				for (reset($this->data); list($name,$table) = each($this->data); )
				{
					$table_names[$name] = $name;
				}
			}
			if (!$this->table || $this->app != $posted_app)
			{
				reset($this->data);
				list($this->table) = each($this->data);	// use first table
			}
			elseif ($this->app == $posted_app && $posted_table)
			{
				$this->data[$posted_table] = $this->content2table($content);
			}
			if ($content['write_tables'])
			{
				if ($this->needs_save('',$this->app,$this->table,$this->data[$posted_table]))
				{
					return;
				}
				$msg .= $this->messages[$this->write($this->app,$this->data) ? 'writen' : 'error_writing'];
			}
			elseif ($content['delete'])
			{
				list($col) = each($content['delete']);

				@reset($this->data[$posted_table]['fd']);
				while ($col-- > 0 && list($key,$data) = @each($this->data[$posted_table]['fd'])) ;

				unset($this->data[$posted_table]['fd'][$key]);
				$this->changes[$posted_table][$key] = '**deleted**';
			}
			elseif ($content['add_column'])
			{
				$this->data[$posted_table]['fd'][''] = array();
			}
			elseif ($content['add_table'] || $content['import'])
			{
				if (!$this->app)
				{
					$msg .= $this->messages['select_app'];
				}
				elseif (!$content['new_table_name'])
				{
					$msg .= $this->messages['give_table_name'];
				}
				elseif ($content['add_table'])
				{
					$this->table = $content['new_table_name'];
					$this->data[$this->table] = array('fd' => array(),'pk' =>array(),'ix' => array(),'uc' => array(),'fk' => array());
					$msg .= $this->messages['new_table'];
				}
				else // import
				{
					$oProc = CreateObject('phpgwapi.schema_proc',$GLOBALS['phpgw_info']['server']['db_type']);
					$oProc->m_odb = $GLOBALS['phpgw']->db;
					$oProc->m_oTranslator->_GetColumns($oProc,$content['new_table_name'],$nul);

					while (list($key,$tbldata) = each ($oProc->m_oTranslator->sCol))
					{
						$cols .= $tbldata;
					}
					eval('$cols = array('. $cols . ');');

					$this->data[$this->table = $content['new_table_name']] = array(
						'fd' => $cols,
						'pk' => $oProc->m_oTranslator->pk,
						'fk' => $oProc->m_oTranslator->fk,
						'ix' => $oProc->m_oTranslator->ix,
						'uc' => $oProc->m_oTranslator->uc
					);
				}
			}
			elseif ($content['editor'])
			{
				ExecMethod('etemplate.editor.edit');
				return;
			}
			// from here on, filling new content for eTemplate
			$content = array(
				'msg' => $msg,
				'table_name' => $this->table,
				'app' => $this->app,
			);
			if (!isset($table_names[$this->table]))	// table is not jet written
			{
				$table_names[$this->table] = $this->table;
			}
			$sel_options = array(
				'table_name' => $table_names,
				'type' => $this->types,
				'app' => array('' => $this->messages['select_one']) + $this->apps
			);
			if ($this->table != '' && isset($this->data[$this->table]))
			{
				$content += $this->table2content($this->data[$this->table]);
			}
			$no_button = array( );
			if (!$this->app || !$this->table)
			{
				$no_button += array('write_tables' => True);
			}
			if ($this->debug)
			{
				echo 'editor.edit: content ='; _debug_array($content);
			}
			$this->editor->exec('etemplate.db_tools.edit',$content,$sel_options,$no_button,
				array('posted_table' => $this->table,'posted_app' => $this->app,'changes' => $this->changes));
		}

		/*!
		@function needs_save($cont='',$posted_app='',$posted_table='',$edited_table='')
		@abstract checks if table was changed and if so offers user to save changes
		@param $cont the content of the form (if called by process_exec)
		@param $posted_app the app the table is from
		@param $posted_table the table-name
		@param $edited_table the edited table-definitions
		@returns only if no changes
		*/
		function needs_save($cont='',$posted_app='',$posted_table='',$edited_table='')
		{
			if (!$posted_app && is_array($cont))
			{
				if (isset($cont['yes']))
				{
					$this->app   = $cont['app'];
					$this->table = $cont['table'];
					$this->read($this->app,$this->data);
					$this->data[$this->table] = $cont['edited_table'];
					$this->changes = $cont['changes'];
					if ($cont['new_version'])
					{
						$this->update($this->app,$this->data,$cont['new_version']);
					}
					$msg .= $this->messages[$this->write($this->app,$this->data) ?
						'writen' : 'error_writing'];
				}
				$this->changes = array();
				// return to edit with everything set, so the user gets the table he asked for
				$this->edit(array(
					'app' => $cont['new_app'],
					'table_name' => $cont['app']==$cont['new_app'] ? $cont['new_table'] : '',
					'posted_app' => $cont['new_app']
				),$msg);

				return True;
			}
			$new_app   = $this->app;	// these are the ones, the users whiches to change too
			$new_table = $this->table;

			$this->app = $posted_app;
			$this->data = array();
			$this->read($posted_app,$this->data);

			if (isset($this->data[$posted_table]) &&
				 $this->tables_identical($this->data[$posted_table],$edited_table))
			{
				$this->app = $new_app;
				$this->data = array();
				return False;	// continue edit
			}
			$content = array(
				'app' => $posted_app,
				'table' => $posted_table,
				'version' => $this->setup_version($posted_app)
			);
			$preserv = $content + array(
				'new_app' => $new_app,
				'new_table' => $new_table,
				'edited_table' => $edited_table,
				'changes' => $this->changes
			);
			$content['new_version'] = $content['version'];

			$tmpl = new etemplate('etemplate.db-tools.ask_save');

			if (!file_exists(PHPGW_SERVER_ROOT."/$posted_app/setup/tables_current.inc.php"))
			{
				$tmpl->disable_cells('version');
				$tmpl->disable_cells('new_version');
			}
			$tmpl->exec('etemplate.db_tools.needs_save',$content,array(),array(),$preserv);

			return True;	// dont continue in edit
		}

		/*!
		@function table2content($table)
		@abstract creates content-array from a $table
		@param $table table-definition, eg. $phpgw_baseline[$table_name]
		@returns content-array
		*/
		function table2content($table)
		{
			$content = array();
			for ($n = 1; list($col_name,$col_defs) = each($table['fd']); ++$n)
			{
				$col_defs['name'] = $col_name;
				$col_defs['pk'] = in_array($col_name,$table['pk']);
				$col_defs['uc']  = in_array($col_name,$table['uc']);
				$col_defs['ix'] = in_array($col_name,$table['ix']);
				$col_defs['fk'] = $table['fk'][$col_name];
				if (isset($col_defs['default']) && $col_defs['default'] == '')
				{
					$col_defs['default'] = is_int($col_defs['default']) ? '0' : "''";	// spezial value for empty, but set, default
				}
				$col_defs['n'] = $n;

				$content["Row$n"] = $col_defs;
			}
			if ($this->debug >= 3)
			{
				echo "<p>table2content: content ="; _debug_array($content);
			}
			return $content;
		}

		/*!
		@function content2table($content)
		@abstract creates table-definition from posted content
		@param $content posted content-array
		@returns table-definition
		*/
		function content2table($content)
		{
			if (!is_array($this->data))
			{
				$this->read($content['posted_app'],$this->data);
			}
			$old_cols = $this->data[$posted_table = $content['posted_table']]['fd'];
			$this->changes = $content['changes'];

			$table = array();
			$table['fd'] = array();	// do it in the default order of tables_*
			$table['pk'] = array();
			$table['fk'] = array();
			$table['ix'] = array();
			$table['uc'] = array();
			for (reset($content),$n = 1; isset($content["Row$n"]); ++$n)
			{
				$col = $content["Row$n"];

				while ((list($old_name,$old_col) = @each($old_cols)) &&
				       $this->changes[$posted_table][$old_name] == '**deleted**') ;

				if (($name = $col['name']) != '')		// ignoring lines without column-name
				{
					if ($col['name'] != $old_name && $n <= count($old_cols))	// column renamed --> remeber it
					{
						$this->changes[$posted_table][$old_name] = $col['name'];
						//echo "<p>content2table: $posted_table.$old_name renamed to $col[name]</p>\n";
					}
					while (list($prop,$val) = each($col))
					{
						switch ($prop)
						{
							case 'default':
							case 'type':	// selectbox ensures type is not empty
							case 'precision':
							case 'scale':
							case 'nullable':
								if ($val != '' || $prop == 'nullable')
								{
									$table['fd'][$name][$prop] = $prop=='default'&& $val=="''" ? '' : $val;
								}
								break;
							case 'pk':
							case 'uc':
							case 'ix':
								if ($val)
								{
									$table[$prop][] = $name;
								}
								break;
							case 'fk':
								if ($val != '')
								{
									$table['fk'][$name] = $val;
								}
								break;
						}
					}
				}
			}
			if ($this->debug >= 2)
			{
				echo "<p>content2table: table ="; _debug_array($table);
				echo "<p>changes = "; _debug_array($this->changes);
			}
			return $table;
		}

		/*!
		@function read($app,&$phpgw_baseline)
		@abstract includes $app/setup/tables_current.inc.php
		@param $app application name
		@param $phpgw_baseline where to put the data
		@returns True if file found, False else
		*/
		function read($app,&$phpgw_baseline)
		{
			$file = PHPGW_SERVER_ROOT."/$app/setup/tables_current.inc.php";

			$phpgw_baseline = array();

			if ($app != '' && file_exists($file))
			{
				include($file);
			}
			else
			{
				return False;
			}
			if ($this->debug >= 5)
			{
				echo "<p>read($app): file='$file', phpgw_baseline =";
				_debug_array($phpgw_baseline);
			}
			return True;
		}

		function write_array($arr,$depth,$parent='')
		{
			if (in_array($parent,array('pk','fk','ix','uc')))
			{
				$depth = 0;
				if ($parent != 'fk')
				{
					$only_vals = True;
				}
			}
			if ($depth)
			{
				$tabs = "\n";
				for ($n = 0; $n < $depth; ++$n)
				{
					$tabs .= "\t";
				}
				++$depth;
			}
			$def = "array($tabs".($tabs ? "\t" : '');

			reset($arr);
			for ($n = 0; list($key,$val) = each($arr); ++$n)
			{
				if (!$only_vals)
				{
					$def .= "'$key' => ";
				}
				if (is_array($val))
				{
					$def .= $this->write_array($val,$parent == 'fd' ? 0 : $depth,$key,$only_vals);
				}
				else
				{
					if (!$only_vals && $key == 'nullable')
					{
						$def .= $val ? 'True' : 'False';
					}
					else
					{
						$def .= "'$val'";
					}
				}
				if ($n < count($arr)-1)
				{
					$def .= ",$tabs".($tabs ? "\t" : '');
				}
			}
			$def .= "$tabs)";

			return $def;
		}

		/*!
		@function write($app,$phpgw_baseline)
		@abstract writes tabledefinitions $phpgw_baseline to file /$app/setup/tables_current.inc.php
		@param $app app-name
		@param $phpgw_baseline tabledefinitions
		@return True if file writen else False
		*/
		function write($app,$phpgw_baseline)
		{
			$file = PHPGW_SERVER_ROOT."/$app/setup/tables_current.inc.php";

			if (file_exists($file) && ($f = fopen($file,'r')))
			{
				$header = fread($f,filesize($file));
				$header = substr($header,0,strpos($header,'$phpgw_baseline'));
				fclose($f);

				if (is_writable(PHPGW_SERVER_ROOT."/$app/setup"))
				{
					rename($file,PHPGW_SERVER_ROOT."/$app/setup/tables_current.old.inc.php");
				}
				while ($header[strlen($header)-1] == "\t")
				{
					$header = substr($header,0,strlen($header)-1);
				}
			}
			if (!$header)
			{
				$header = $this->setup_header . "\n\n";
			}
			if (!is_writeable(PHPGW_SERVER_ROOT."/$app/setup") || !($f = fopen($file,'w')))
			{
				return False;
			}
			$def .= "\t\$phpgw_baseline = ";
			$def .= $this->write_array($phpgw_baseline,1);
			$def .= ";\n";

			fwrite($f,$header . $def);
			fclose($f);

			return True;
		}

		/*!
		@function setup_version($app,$new = '')
		@abstract reads and updates the version in file $app/setup/setup.inc.php if $new != ''
		@return the version or False if the file could not be read or written
		*/
		function setup_version($app,$new = '')
		{
			//echo "<p>etemplate.db_tools.setup_version('$app','$new')</p>\n";

			$file = PHPGW_SERVER_ROOT."/$app/setup/setup.inc.php";
			if (file_exists($file))
			{
				include($file);
			}
			if (!is_array($setup_info[$app]) || !isset($setup_info[$app]['version']))
			{
				return False;
			}
			if ($new == '' || $setup_info[$app]['version'] == $new)
			{
				return $setup_info[$app]['version'];
			}
			if (!($f = fopen($file,'r')))
			{
				return False;
			}
			$fcontent = fread($f,filesize($file));
			fclose ($f);

			if (is_writable(PHPGW_SERVER_ROOT."/$app/setup"))
			{
				rename($file,PHPGW_SERVER_ROOT."/$app/setup/setup.old.inc.php");
			}
			$fnew = eregi_replace("(.*\\$"."setup_info\\['$app'\\]\\['version'\\][ \\t]*=[ \\t]*')[^']*('.*)","\\1$new"."\\2",$fcontent);

			if (!is_writeable(PHPGW_SERVER_ROOT."/$app/setup") || !($f = fopen($file,'w')))
			{
				return False;
			}
			fwrite($f,$fnew);
			fclose($f);

			return $new;
		}

		/*!
		@function update($app,$current,$version)
		@abstract updates file /$app/setup/tables_update.inc.php to reflect changes in $current
		@param $app app-name
		@param $current new tabledefinitions
		@param $version new version
		@return True if file writen else False
		*/
		function update($app,$current,$version)
		{
			//echo "<p>etemplate.db_tools.update('$app',...,'$version')</p>\n";

			if (!is_writable(PHPGW_SERVER_ROOT."/$app/setup"))
			{
				return False;
			}
			$file_baseline = PHPGW_SERVER_ROOT."/$app/setup/tables_baseline.inc.php";
			$file_current  = PHPGW_SERVER_ROOT."/$app/setup/tables_current.inc.php";
			$file_update   = PHPGW_SERVER_ROOT."/$app/setup/tables_update.inc.php";

			if (!file_exists($file_baseline) && !copy($file_current,$file_baseline))
			{
				//echo "<p>Can't copy $file_current to $file_baseline !!!</p>\n";
				return False;
			}
			$old_version = $this->setup_version($app);
			$old_version_ = str_replace('.','_',$old_version);

			if (file_exists($file_update))
			{
				$f = fopen($file_update,'r');
				$update = fread($f,filesize($file_update));
				$update = str_replace('?>','',$update);
				fclose($f);
				rename($file_update,PHPGW_SERVER_ROOT."/$app/setup/tables_update.old.inc.php");
			}
			else
			{
				$update = $this->setup_header;
			}
			$update .= "
	\$test[] = '$old_version';
	function $app"."_upgrade$old_version_()
	{\n";

			$update .= $this->update_schema($app,$current);

			$update .= "\n
		\$GLOBALS['setup_info']['$app']['currentver'] = '$version';
		return \$GLOBALS['setup_info']['phpgwapi']['currentver'];
	}
?".">\n";
			if (!($f = fopen($file_update,'w')))
			{
				//echo "<p>Cant open '$update' for writing !!!</p>\n";
				return False;
			}
			fwrite($f,$update);
			fclose($f);

			$this->setup_version($app,$version);

			return True;
		}

		function remove_from_array(&$arr,$value)
		{
			reset($arr);
			while (list($key,$val) = each($arr))
			{
				if ($val == $value)
				{
					unset($arr[$key]);
				}
			}
		}

		function update_schema($app,$current)
		{
			$this->read($app,$old);

			reset($old);
			while (list($name,$table_def) = each($old))
			{
				if (!isset($current[$name]))	// table $name droped
				{
					$update .= "\t\t\$GLOBALS['phpgw_setup']->oProc->DropTable('$name');\n";
				}
				else
				{
					reset($table_def['fd']);
					while(list($col,$col_def) = each($table_def['fd']))
					{
						if (!isset($current[$name]['fd'][$col]))	// column $col droped
						{
							if (!isset($this->changes[$name][$col]) || $this->changes[$name][$col] == '**deleted**')
							{
								$new_table_def = $table_def;
								unset($new_table_def['fd'][$col]);
								$this->remove_from_array($new_table_def['pk'],$col);
								$this->remove_from_array($new_table_def['fk'],$col);
								$this->remove_from_array($new_table_def['ix'],$col);
								$this->remove_from_array($new_table_def['uc'],$col);
								$update .= "\t\t\$GLOBALS['phpgw_setup']->oProc->DropColumn('$name',";
								$update .= $this->write_array($new_table_def,2).",'$col');\n";
							}
							else	// column $col renamed
							{
								$new_col = $this->changes[$name][$col];
								$update .= "\t\t\$GLOBALS['phpgw_setup']->oProc->RenameColumn('$name','$col','$new_col');\n";
							}
						}
					}
					@reset($this->changes[$name]);
					while (list($col,$new_col) = @each($this->changes[$name]))
					{
						if ($new_col != '**deleted**')
						{
							$old[$name]['fd'][$new_col] = $old[$name]['fd'][$col];	// to be able to detect further changes of the definition
							unset($old[$name]['fd'][$col]);
						}
					}
				}
			}
			reset($current);
			while(list($name,$table_def) = each($current))
			{
				if (!isset($old[$name]))	// table $name added
				{
					$update .= "\t\t\$GLOBALS['phpgw_setup']->oProc->CreateTable('$name',";
					$update .= $this->write_array($table_def,2).");\n";
				}
				else
				{
					$old_norm = $this->normalize($old[$name]);
					$new_norm = $this->normalize($table_def);
					reset($table_def['fd']);
					while (list($col,$col_def) = each($table_def['fd']))
					{
						if (($add = !isset($old[$name]['fd'][$col])) ||	// column $col added
							 serialize($old_norm['fd'][$col]) != serialize($new_norm['fd'][$col])) // column definition altered
						{
							$update .= "\t\t$"."GLOBALS['phpgw_setup']->oProc->".($add ? 'Add' : 'Alter')."Column('$name','$col',";
							$update .= $this->write_array($col_def,2) . ");\n";
						}
					}
				}
			}
			if ($this->debug)
			{
				echo "<p>update_schema($app, ...) =<br><pre>$update</pre>)</p>\n";
			}
			return $update;
		}

		/*!
		@function normalize($table)
		@abstract sets all nullable properties to True or False
		@returns the new array
		*/
		function normalize($table)
		{
			$all_props = array('type','precision','nullable','default');

			reset($table['fd']);
			while (list($col,$props) = each($table['fd']))
			{
				$table['fd'][$col] = array(
					'type' => ''.$props['type'],
					'precision' => 0+$props['precision'],
					'scale' => 0+$props['scale'],
					'nullable' => !!$props['nullable'],
					'default' => ''.$props['default']
				);
			}
			return array(
				'fd' => $table['fd'],
				'pk' => $table['pk'],
				'fk' => $table['fk'],
				'ix' => $table['ix'],
				'uc' => $table['uc']
			);
		}

		/*!
		@function tables_identical($old,$new)
		@abstract compares two table-definitions
		@returns True if they are identical or False else
		*/
		function tables_identical($a,$b)
		{
			$a = serialize($this->normalize($a));
			$b = serialize($this->normalize($b));

			//echo "<p>checking if tables identical = ".($a == $b ? 'True' : 'False')."<br>\n";
			//echo "a: $a<br>\nb: $b</p>\n";

			return $a == $b;
		}

		/*!
		@function writeLangFile
		@abstract writes langfile with all templates and messages registered here
		@discussion can be called via http://domain/phpgroupware/index.php?etemplate.db_tools.writeLangFile
		*/
		function writeLangFile()
		{
			$m = new db_tools(False);	// no lang on messages
			$this->editor->writeLangFile('etemplate','en',$m->messages);
		}
	};



