<?php
	/**************************************************************************\
	* eGroupWare - eTemplates - Editor                                         *
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
	 * template editor of the eTemplate package
	 *
	 * @package etemplate
	 * @author RalfBecker-AT-outdoor-training.de
	 * @license GPL
	 */
	class editor
	{
		var $debug;
		var $etemplate; // eTemplate we edit
		var $editor;	// editor eTemplate
		var $aligns = array(
			'' => 'Left',
			'right' => 'Right',
			'center' => 'Center'
		);
		var $edit_menu = array(
			'delete' => 'delete',
			'cut' => 'cut',
			'copy' => 'copy',
			'paste' => 'paste',
			'swap' => 'swap',
		);
		var $grid_menu = array(
			'row' => array(
				'row_delete' => 'delete this row',
				'row_insert_above' => 'insert a row above',
				'row_insert_below' => 'insert a row below',
				'row_swap_next' => 'swap with next row',
				'row_prefs' => 'preferences of this row',
			),
			'column' => array(
				'colum_delete' => 'delete this row',
				'colum_insert_before' => 'insert a column before',
				'column_insert_behind' => 'insert a column behind',
				'column_swap_next' => 'swap with next column',
				'column_prefs' => 'preferences of this column',
			),				
			'grid_prefs' => 'preferences',
		);
		var $box_menu = array(
			'box_insert_before' => 'insert a widget before',
			'box_insert_behind' => 'insert a widget behind',
			'box_swap_next' => 'swap widget with next one',
			'box_prefs' => 'preferences',
		);
		var $options = array(
			'width',
			'height',
			'border',
			'class',
			'spacing',
			'padding',
			'overflow'
		);
		var $overflows = array(
			'' => 'visible',
			'hidden' => 'hidden',
			'scroll' => 'scroll',
			'auto' => 'auto'
		);
		var $extensions = '';

		var $public_functions = array
		(
			'edit'         => True,
			'process_edit' => True,
			'delete'       => True,
			'show'         => True,
			'widget'       => True,
		);

		function editor()
		{
			$this->etemplate = CreateObject('etemplate.etemplate');

			$this->editor = new etemplate('etemplate.editor');
			
			$this->extensions = $GLOBALS['phpgw']->session->appsession('extensions','etemplate');
		}

		function edit($msg = '',$xml='',$xml_label='')
		{
			if (isset($_GET['name']) && !$this->etemplate->read($_GET))
			{
				$msg .= lang('Error: Template not found !!!');
			}
			if (!is_array($this->extensions))
			{
				if (($extensions = $this->scan_for_extensions()))
				{
					$msg .= lang('Extensions loaded:') . ' ' . $extensions;
					$msg_ext_loaded = True;
				}
			}
			list($app) = explode('.',$this->etemplate->name);
			if ($app && $app != 'etemplate')
			{
				$GLOBALS['phpgw']->translation->add_app($app);	// load translations for app

				if (($extensions = $this->scan_for_extensions($app)))
				{
					$msg .= (!$msg_ext_loaded?lang('Extensions loaded:').' ':', ') . $extensions;
				}
			}
			$content = $this->etemplate->as_array() + array(
				'cols' => $this->etemplate->cols,
				'msg' => $msg,
				'xml_label' => $xml_label,
				'xml' => $xml ? '<pre>'.$this->etemplate->html->htmlspecialchars($xml)."</pre>\n" : '',
			);
			foreach(explode(',',$this->etemplate->size) as $n => $opt)
			{
				$content['options'][$opt] = $options[$n];
			}
			$cols_spanned = array();
			reset($this->etemplate->data);
			if (isset($this->etemplate->data[0]))
			{
				each($this->etemplate->data);
			}
			$no_button = array();
			while (list($row,$cols) = each($this->etemplate->data))
			{
				if ($this->etemplate->rows <= 1)
				{
					$no_button["Row$row"]['delete_row[1]'] = True;
				}
				if ($row > 1)
				{
					$no_button["Row$row"]['insert_row[0]'] = True;
				}
				$content["Row$row"] = array(
					'height' => array("h$row" => $this->etemplate->data[0]["h$row"]),
					'class'  => array("c$row" => $this->etemplate->data[0]["c$row"])
				);
				for ($spanned = $c = 0; $c < $this->etemplate->cols; ++$c)
				{
					if (!(list($col,$cell) = each($cols)))
					{
						$cell = $this->etemplate->empty_cell();	// if cell gots lost, create it empty
						$col = $this->etemplate->num2chrs($c);
					}
					if (--$spanned > 0)	// preserv spanned cells
					{
						while(list($k,$v) = each($cell))		// so spanned (not shown) cells got
						{                                   // reported back like regular one
							$cols_spanned[$col.$row][$k] = $v;
						}
					}
					else
					{
						$spanned = $cell['span'] == 'all' ? $this->etemplate->cols-$c : 0+$cell['span'];
						
						switch($cell['type'])	// load a cell-type-specific tpl
						{
							case 'vbox':
							case 'hbox':
							case 'deck':
							case 'box':
								$cell['cell_tpl'] = '.vbox';
								break;
							case 'groupbox':
								$cell['cell_tpl'] = '.groupbox';
								break;
						}
						$content[$col.$row] = $cell;
					}
					if ($row == 1)
					{
						$content["Col$col"] = array('width' => array($col => $this->etemplate->data[0][$col]));
						if ($this->etemplate->cols <= 1)
						{
							$no_button["Col$col"]['delete_col[1]'] = True;
						}
						if ($c > 0)
						{
							$no_button["Col$col"]['insert_col[0]'] = True;
						}
					}
				}
			}
			$no_button['ColA']['exchange_col[1]'] = $no_button['Row1']['exchange_row[1]'] = True;

			if ($this->debug)
			{
				echo 'editor.edit: content ='; _debug_array($content);
			}
			$GLOBALS['phpgw_info']['flags']['app_header'] = lang('Editable Templates - Editor');
			$this->editor->exec('etemplate.editor.process_edit',$content,
				array(
					'type' => array_merge($this->etemplate->types,$this->extensions),
					'align' => $this->aligns,
					'overflow' => $this->overflows
				),
				$no_button,$cols_spanned);
		}

		function swap(&$a,&$b)
		{
			$t = $a; $a = $b; $b = $t;
		}

		function process_edit($content)
		{
			if ($this->debug)
			{
				echo "editor.process_edit: content ="; _debug_array($content);
			}
			$this->etemplate->init($content);

			$opts = array();
			reset($this->options);
			while (list(,$opt) = each($this->options))
			{
				$opts[$opt] = $content['options'][$opt];
			}
			$this->etemplate->size = ereg_replace(',*$','',implode(',',$opts));
			$this->etemplate->style = $content['style'];

			$names = array('width','height','class');
			$opts = array();
			while (list(,$opt) = each($names))
			{
				if (is_array($content[$opt]))
				{
					$opts += $content[$opt];
				}
			}
			$this->etemplate->data = array($opts);
			$row = 1; $col = 0;
			while (isset($content[$name = $this->etemplate->num2chrs($col) . $row]))
			{
				$cell = &$content[$name];
				switch ($cell['type'])
				{
					case 'vbox':
					case 'hbox':
					case 'deck':
					case 'groupbox':
					case 'box':
						// default size for all boxes is 2, minimum size is 1 for a (group)box and 2 for the others
						if ($cell['size'] < 2 && ($cell['type'] != 'groupbox' || $cell['type'] != 'box' || !$cell['size']))
						{
							$cell['size'] = 2;
						}
						for ($n = 1; $n <= $cell['size']; ++$n)	// create new rows
						{
							if (!isset($cell[$n]) || !is_array($cell[$n]))
							{
								$cell[$n] = $this->etemplate->empty_cell();
							}
						}
						while (isset($cell[$n]))	// unset not longer used rows
						{
							unset($cell[$n++]);
						}
						break;
				}
				$row_data[$this->etemplate->num2chrs($col++)] = $cell;

				if (!isset($content[$name = $this->etemplate->num2chrs($col) . $row]))	// try new row
				{
					if ($col > $cols)
					{
						$cols = $col;
					}
					$this->etemplate->data[$row] = $row_data;
					++$row; $col = 0; $row_data = array();
				}
			}
			$this->etemplate->rows = $row - 1;
			$this->etemplate->cols = $cols;

			if (isset($content['insert_row']))
			{
				list($row) = each($content['insert_row']);
				$opts = $this->etemplate->data[0];		// move height + class options of rows
				for ($r = $this->etemplate->rows; $r > $row; --$r)
				{
					$opts['c'.(1+$r)] = $opts["c$r"]; unset($opts["c$r"]);
					$opts['h'.(1+$r)] = $opts["h$r"]; unset($opts["h$r"]);
				}
				$this->etemplate->data[0] = $opts;
				$old = $this->etemplate->data;	// move rows itself
				$row_data = array();
				for ($col = 0; $col < $this->etemplate->cols; ++$col)
				{
					$row_data[$this->etemplate->num2chrs($col)] = $this->etemplate->empty_cell();
				}
				$this->etemplate->data[++$row] = $row_data;
				for (; $row <= $this->etemplate->rows; ++$row)
				{
					$this->etemplate->data[1+$row] = $old[$row];
				}
				++$this->etemplate->rows;
			}
			elseif (isset($content['insert_col']))
			{
				list($insert_col) = each($content['insert_col']);
				for ($row = 1; $row <= $this->etemplate->rows; ++$row)
				{
					$old = $row_data = $this->etemplate->data[$row];
					$row_data[$this->etemplate->num2chrs($insert_col)] = $this->etemplate->empty_cell();
					for ($col = $insert_col; $col < $this->etemplate->cols; ++$col)
					{
						$row_data[$this->etemplate->num2chrs(1+$col)] = $old[$this->etemplate->num2chrs($col)];
					}
					$this->etemplate->data[$row] = $row_data;
				}
				$width = $this->etemplate->data[0];
				for ($col = $this->etemplate->cols; $col > $insert_col; --$col)
				{
					$width[$this->etemplate->num2chrs($col)] = $width[$this->etemplate->num2chrs($col-1)];
				}
				unset($width[$this->etemplate->num2chrs($insert_col)]);
				$this->etemplate->data[0] = $width;

				++$this->etemplate->cols;
			}
			elseif (isset($content['exchange_col']))
			{
				list($exchange_col) = each($content['exchange_col']);
				$right = $this->etemplate->num2chrs($exchange_col-1);
				$left  = $this->etemplate->num2chrs($exchange_col-2);

				for ($row = 1; $row <= $this->etemplate->rows; ++$row)
				{
					$this->swap($this->etemplate->data[$row][$left],$this->etemplate->data[$row][$right]);
				}
				$this->swap($this->etemplate->data[0][$left],$this->etemplate->data[0][$right]);
			}
			elseif (isset($content['exchange_row']))
			{
				list($er2) = each($content['exchange_row']); $er1 = $er2-1;
				$this->swap($this->etemplate->data[$er1],$this->etemplate->data[$er2]);
				$this->swap($this->etemplate->data[0]["c$er1"],$this->etemplate->data[0]["c$er2"]);
				$this->swap($this->etemplate->data[0]["h$er1"],$this->etemplate->data[0]["h$er2"]);
			}
			elseif (isset($content['delete_row']))
			{
				list($delete_row) = each($content['delete_row']);
				$opts = $this->etemplate->data[0];
				for ($row = $delete_row; $row < $this->etemplate->rows; ++$row)
				{
					$this->etemplate->data[$row] = $this->etemplate->data[1+$row];
					$opts["c$row"] = $opts['c'.(1+$row)];
					$opts["h$row"] = $opts['h'.(1+$row)];
				}
				unset($this->etemplate->data[$this->etemplate->rows--]);
				$this->etemplate->data[0] = $opts;
			}
			elseif (isset($content['delete_col']))
			{
				list($delete_col) = each($content['delete_col']);
				for ($row = 1; $row <= $this->etemplate->rows; ++$row)
				{
					$row_data = $this->etemplate->data[$row];
					for ($col = $delete_col; $col < $this->etemplate->cols; ++$col)
					{
						$row_data[$this->etemplate->num2chrs($col-1)] = $row_data[$this->etemplate->num2chrs($col)];
					}
					unset($row_data[$this->etemplate->num2chrs($this->etemplate->cols-1)]);
					$this->etemplate->data[$row] = $row_data;
				}
				$width = $this->etemplate->data[0];
				for ($col = $delete_col; $col < $this->etemplate->cols; ++$col)
				{
					$width[$this->etemplate->num2chrs($col-1)] = $width[$this->etemplate->num2chrs($col)];
				}
				$this->etemplate->data[0] = $width;
				--$this->etemplate->cols;
			}
			if ($this->debug)
			{
				echo 'editor.process_edit: rows='.$this->etemplate->rows.', cols='.
					$this->etemplate->cols.', data ='; _debug_array($this->etemplate->data);
			}
			// Execute the action resulting from the submit-button
			if ($content['read'])
			{
				if ($content['version'] != '')
				{
					$save_version = $content['version'];
					unset($content['version']);
					$this->etemplate->read($content);
					$newest_version = $this->etemplate->version;
					$content['version'] = $save_version;
				}
				if (!$this->etemplate->read($content))
				{
					$content['version'] = '';	// trying it without version
					if ($this->etemplate->read($content))
					{
						$msg = lang('only an other Version found !!!');
					}
					else
					{
						$result = $this->etemplate->search($content);
						if (count($result) > 1)
						{
							return $this->list_result(array('result' => $result));
						}
						elseif (!count($result) || !$this->etemplate->read($result[0]))
						{
							$msg = lang('Error: Template not found !!!');
						}
						elseif ($content['name'] == $result[0]['name'])
						{
							$msg = lang('only an other Version found !!!');
						}
					}
				}
				elseif ($newest_version != '' && $this->etemplate->version != $newest_version)
				{
					$msg = lang("newer version '%1' exists !!!",$newest_version);
				}
			}
			elseif ($content['delete'])
			{
				$this->delete();
				return;
			}
			elseif ($content['dump'])
			{
				list($name) = explode('.',$content['name']);
				if (empty($name) || !@is_dir(PHPGW_SERVER_ROOT.'/'.$name))
				{
					$msg = lang('Application name needed to write a langfile or dump the eTemplates !!!');
				}
				else
				{
					$msg = $this->etemplate->dump2setup($content['name']);
				}
			}
			elseif ($content['save'])
			{
				if (!$this->etemplate->modified_set || !$this->etemplate->modified)
				{
					$this->etemplate->modified = time();
				}
				$ok = $this->etemplate->save(trim($content['name']),trim($content['template']),trim($content['lang']),intval($content['group']),trim($content['version']));
				$msg = $ok ? lang('Template saved') : lang('Error: while saveing !!!');
			}
			elseif ($content['show'])
			{
				$this->show();
				return;
			}
			elseif ($content['langfile'])
			{
				list($name) = explode('.',$content['name']);
				if (empty($name) || !@is_dir(PHPGW_SERVER_ROOT.'/'.$name))
				{
					$msg = lang('Application name needed to write a langfile or dump the eTemplates !!!');
				}
				else
				{
					$additional = array();
					if ($name == 'etemplate')
					{
						$additional = $this->etemplate->types + $this->extensions + $this->aligns;
					}
					else	// try to call the writeLangFile function of the app's ui-layer
					{
						$ui = @CreateObject($name.'.'.($class = 'ui'.$name));
						if (!is_object($ui))
						{
							$ui = @CreateObject($name.'.'.($class = 'ui'));
						}
						if (!is_object($ui))
						{
							$ui = @CreateObject($name.'.'.($class = $name));
						}
						if (is_object($ui) && @$ui->public_functions['writeLangFile'])
						{
							$msg = "$class::writeLangFile: ".$ui->writeLangFile();
						}
						unset($ui);
					}
					if (empty($msg))
					{
						$msg = $this->etemplate->writeLangFile($name,'en',$additional);
					}
				}
			}
			elseif ($content['export_xml'])
			{
				$msg = $this->export_xml($xml,$xml_label);
			}
			elseif ($content['import_xml'])
			{
				$msg = $this->import_xml($content['file']['tmp_name'],$xml);
				$xml_label = $content['file']['name'];
			}
			elseif ($content['db_tools'])
			{
				ExecMethod('etemplate.db_tools.edit');
				return;
			}
			$this->edit($msg,$xml,$xml_label);
		}

		function export_xml(&$xml,&$xml_label)
		{
			$name = $this->etemplate->name;
			$template = $this->etemplate->template != '' ? $this->etemplate->template : 'default';

			list($app) = explode('.',$name);

			$dir = PHPGW_SERVER_ROOT . "/$app/templates/$template";
			if ($create_it = !is_dir($dir))
			{
				$dir = PHPGW_SERVER_ROOT . "/$app/templates";
			}
			if (!is_writeable($dir))
			{
				return lang("Error: webserver is not allowed to write into '%1' !!!",$dir);
			}
			if ($create)
			{
				mkdir($dir .= "/$template");
			}
			$file = $dir . '/' . substr($name,strlen($app)+1);
			if ($this->etemplate->lang)
			{
				$file .= '.' . $this->etemplate->lang;
			}
			$old_file = $file . '.old.xet';
			$file .= '.xet';
			if (file_exists($file))
			{
				if (file_exists($old_file))
				{
					unlink($old_file);
				}
				rename($file,$old_file);
			}

			if (!($f = fopen($xml_label=$file,'w')))
			{
				return 0;
			}
			if (!is_object($this->etemplate->xul_io))
			{
				$this->etemplate->xul_io = CreateObject('etemplate.xul_io');
			}
			$xml = $this->etemplate->xul_io->export($this->etemplate);

			fwrite($f,$xml);
			fclose($f);

			return lang("eTemplate '%1' written to '%2'",$name,$file);
		}

		function import_xml($file,&$xml)
		{
			if ($file == 'none' || $file == '' || !($f = fopen($file,'r')))
			{
				return lang('no filename given or selected via Browse...');
			}
			$xml = fread ($f, filesize ($file));
			fclose($f);

			if (!is_object($this->etemplate->xul_io))
			{
				$this->etemplate->xul_io = CreateObject('etemplate.xul_io');
			}
			$imported = $this->etemplate->xul_io->import($this->etemplate,$xml);
			$this->etemplate->modified = @filemtime($f);
			$this->etemplate->modified_set = 'xul-import';

			if (is_array($imported))
			{
				if (count($imported) == 1)
				{
					$imported = lang("eTemplate '%1' imported, use Save to put it in the database",$this->etemplate->name);
				}
				else
				{
					$imported = lang('File contains more than one eTemplate, last one is shown !!!');
				}
			}
			return $imported;
		}

		function delete($content='',$back = 'edit')
		{
			if ($this->debug)
			{
				echo "delete(back='$back') content = "; _debug_array($content);
			}
			if (!is_array($content))
			{
				$content = array();
			}
			if (isset($content['name']))
			{
				$read_ok = $this->etemplate->read($content);
			}
			if (isset($content['yes']))	// Delete
			{
				if ($read_ok)
				{
					$read_ok = $this->etemplate->delete();
				}
				$msg = $read_ok ? lang('Template deleted') : lang('Error: Template not found !!!');

				if ($content['back'] == 'list_result')
				{
					$this->list_result($content['preserv'],$msg);
				}
				else
				{
					$this->edit($msg);
				}
				return;
			}
			if (isset($content['no']))	// Back to ...
			{
				switch ($back = $content['back'])
				{
					case 'list_result':
						$this->$back($content['preserv']);
						return;
					case 'show':
						break;
					default:
						$back = 'edit';
				}
				$this->$back();
				return;
			}
			if (isset($_GET['name']) && !$this->etemplate->read($_GET))
			{
				$this->edit(lang('Error: Template not found !!!'));
				return;
			}
			$preserv = array(
				'preserv' => $content['preserv'],
				'back'    => $back
			);
			$content = $this->etemplate->as_array();

			$delete = new etemplate('etemplate.editor.delete');
			$GLOBALS['phpgw_info']['flags']['app_header'] = lang('Editable Templates - Delete Template');
			$delete->exec('etemplate.editor.delete',$content,array(),array(),
				$content+$preserv,'');
		}

		function list_result($cont='',$msg='')
		{
			if ($this->debug)
			{
				echo "<p>etemplate.editor.list_result: cont="; _debug_array($cont);
			}
			if (!$cont || !is_array($cont))
			{
				return $this->edit('error');
			}
			if (!isset($cont['result']) || isset($cont['search']))
			{
				$cont['result'] = $this->etemplate->search($cont);
			}
			$result = $cont['result'];

			if (isset($cont['delete']))
			{
				list($delete) = each($cont['delete']);
				$this->etemplate->read($result[$delete-1]);
				unset($cont['delete']);
				unset($cont['result']);
				$this->delete(array('preserv' => $cont),'list_result');
				return;
			}
			if (isset($cont['delete_selected']))
			{
				while (list($row,$sel) = each($cont['selected']))
				{
					if ($sel)
					{
						$this->etemplate->read($result[$row-1]);
						$this->etemplate->delete();
						++$n;
					}
				}
				if ($n)
				{
					$msg = lang('%1 eTemplates deleted',$n);
				}
				unset($cont['selected']);
				unset($cont['delete_selected']);
				$result = $this->etemplate->search($cont);
			}
			if (isset($cont['read']))
			{
				list($read) = each($cont['read']);
				$this->etemplate->read($result[$read-1]);
				$this->edit();
				return;
			}
			if (isset($cont['view']))
			{
				list($read) = each($cont['view']);
				$this->etemplate->read($result[$read-1]);
				$this->show();
				return;
			}
			if (!$msg)
			{
				$msg = lang('%1 eTemplates found',count($result));
			}
			unset($cont['result']);
			if (!isset($cont['name']))
			{
				$cont += $this->etemplate->as_array();
			}
			$content = $cont + array('msg' => $msg);

			reset($result);
			for ($row=1; list(,$param) = each($result); ++$row)
			{
				$content[$row] = $param;
			}
			$list_result = new etemplate('etemplate.editor.list_result');
			$GLOBALS['phpgw_info']['flags']['app_header'] = lang('Editable Templates - Search');
			$list_result->exec('etemplate.editor.list_result',$content,'','',array(
				'result' => $result,
			),'');
		}

		function show($post_vars='')
		{
			if ($this->debug)
			{
				echo "<p>etemplate.editor.show: content="; _debug_array($post_vars);
			}
			if (!is_array($post_vars))
			{
				$post_vars = array();
			}
			if (!is_array($this->extensions))
			{
				$this->scan_for_extensions();
			}
			if (isset($_GET['name']) && !$this->etemplate->read($_GET) ||
			    isset($post_vars['name']) && !$this->etemplate->read($post_vars))
			{
				$msg = lang('Error: Template not found !!!');

				if (isset($post_vars['name']))
				{
					$post_vars['version'] = '';	// trying it without version
					if ($this->etemplate->read($post_vars))
					{
						$msg = lang('only an other Version found !!!');
					}
					else
					{
						$result = $this->etemplate->search($post_vars);
						if (count($result) > 1)
						{
							return $this->list_result(array('result' => $result));
						}
						elseif (!count($result) || !$this->etemplate->read($result[0]))
						{
							$msg = lang('Error: Template not found !!!');
						}
						elseif ($post_vars['name'] == $result[0]['name'])
						{
							$msg = lang('only an other Version found !!!');
						}
					}
				}
			}
			if (!$msg && isset($post_vars['delete']))
			{
				$this->delete(array(),'show');
				return;
			}
			if (isset($post_vars['edit']))
			{
				$this->edit();
				return;
			}
			list($app) = explode('.',$this->etemplate->name);
			if ($app && $app != 'etemplate')
			{
				$GLOBALS['phpgw']->translation->add_app($app);	// load translations for app
				$this->scan_for_extensions($app);
			}
			$content = $this->etemplate->as_array() + array('msg' => $msg);

			$show =& new etemplate('etemplate.editor.show');
			if (!$msg && isset($post_vars['values']) && !isset($post_vars['vals']))
			{
				$cont = $post_vars['cont'];
				for ($r = 1; list($key,$val) = @each($cont); ++$r)
				{
					$vals["@$r"] = $key;
					$vals["A$r"] = is_array($val) ? htmlspecialchars(serialize($val)).'#SeR#' : $val;
				}
				$show->data[$show->rows]['A']['name'] = 'etemplate.editor.values';
				$show->data[$show->rows]['A']['size'] = 'vals';
				$content['vals'] = $vals;
			}
			else
			{
				// set onclick handler
				$this->etemplate->onclick_handler = "edit_widget('%p');";
				// setting the javascript via the content, allows looping too
				$content['onclick'] = '
				<script language="javascript">
					function edit_widget(path)
					{
						window.open("'.$GLOBALS['phpgw']->link('/index.php',$this->etemplate->as_array(-1)+array(
							'menuaction' => 'etemplate.editor.widget',
							'path'       => ''	// has to be last !
						)).'"+path,"etemplate_editor_widget","dependent=yes,width=600,height=400,location=no,menubar=no,toolbar=no,scrollbars=yes,status=yes");
					}
				</script>';
				$show->data[$show->rows]['A']['obj'] = &$this->etemplate;
				$vals = $post_vars['vals'];
				$olds = $post_vars['olds'];

				for ($r = 1; isset($vals["A$r"]); ++$r)
				{
					$content['cont'][$olds["@$r"]] = substr($vals["A$r"],-5)=='#SeR#' ?
						unserialize(substr($vals["A$r"],0,-5)) : $vals["A$r"];
				}
			}
			$GLOBALS['phpgw_info']['flags']['app_header'] = lang('Editable Templates - Show Template');
			$show->exec('etemplate.editor.show',$content,array(),'',array(
				'olds' => $vals,
			),'');
		}

		/**
		 * initialises the children arrays for the new widget type, converts boxes <--> grids
		 *
		 * @param array &$widget reference to the new widget data
		 * @param array $old the old widget data
		 */
		function change_widget_type(&$widget,$old)
		{
			//echo "<p>editor::change_widget_type($widget[type]=$old[type])</p>\n";
			$old_type = $old['type'];
			$old_had_children = isset($this->etemplate->widgets_with_children[$old_type]);
			
			if (!isset($this->etemplate->widgets_with_children[$widget['type']]) ||
				$old_had_children && ($old_type == 'grid') == ($widget['type'] == 'grid'))
			{
				return; // no change necessary, eg. between different box-types
			}
			if ($widget['type'] == 'grid')
			{
				$widget['data'] = array(array());
				$widget['cols'] = $widget['rows'] = 0;

				if ($old_had_children)	// box --> grid: hbox --> 1 row, other boxes --> 1 column
				{
					list($num) = explode(',',$old['size']);
					for ($n = 1; is_array($old[$n]) && $n <= $num; ++$n)
					{
						soetemplate::add_child($widget,$old[$n]);
						if ($old_type != 'hbox') soetemplate::add_child($widget,null);
					}
					$widget['size'] = '';
				}
				else	// 1 row with 1 column/child
				{
					soetemplate::add_child($widget,soetemplate::empty_cell());
				}
			}
			else // a box-type
			{
				$widget['size'] = 0;
				
				if ($old_type == 'grid')
				{
					if ($widget['type'] == 'hbox')	// 1 row --> hbox
					{
						$row =& $old['data'][1];
						for ($n = 1; $n <= $old['cols']; ++$n)
						{
							$cell =& $row[soetemplate::num2chrs($n)];
							soetemplate::add_child($widget,$cell);
							list($span) = (int)explode(',',$cell['span']);
							if ($span == 'all') break;
							while ($span-- > 1) ++$n;
						}
					}
					else
					{
						for ($n = 1; $n <= $old['rows']; ++$n)
						{
							soetemplate::add_child($widget,$old['data'][$n][soetemplate::num2chrs(1)]);
						}
					}
				}
				if (!$widget['size']) // minimum one child
				{
					soetemplate::add_child($widget,soetemplate::empty_cell());
				}
			}
			//_debug_array($widget);
		}

		/**
		 * edit dialog for a widget
		 */
		function widget($content='',$msg='')
		{
			if (is_array($content))
			{
				$this->etemplate->read($content['name'],$content['template'],$content['lang'],$content['old_version']);
				$widget =& $this->etemplate->get_widget_by_path($content['path']);
				$path_parts = explode('/',$content['path']);
				$child_id = array_pop($path_parts);
				$parent_path = implode('/',$path_parts);
				//echo "<p>path='$content[path]': child_id='$child_id', parent_path='$parent_path</p>\n";
				$parent =& $this->etemplate->get_widget_by_path($parent_path);
				
				foreach(array('save','apply','cancel','edit','grid','box') as $n => $name)
				{
					if (($action = $content[$name] ? ($n < 3 ? $name : $content[$name]) : false)) break;
					$name = '';
				}
				unset($content[$name]);
				
				//echo "<p>name='$name', parent-type='$parent[type]', action='$action'</p>\n";
				if ($name == 'grid' && $parent['type'] != 'grid' ||
					$name == 'box' && $parent['type'] == 'grid' ||
					substr($action,-4) == 'prefs' && !$parent['type'])
				{
					$msg .= lang("parent is a '%1' !!!",lang($parent['type'] ? $parent['type'] : 'template'));
					$action = false;
				}
				switch ($action)
				{
					case '':
						// initialise the children arrays if type is changed to a widget with children
						if (isset($this->etemplate->widgets_with_children[$content['cell']['type']]) &&
							$content['cell']['type'] != $widget['type'])
						{
							$this->change_widget_type($content['cell'],$widget);
						}
						break;
						
					case 'paste':
					case 'swap':
						$clipboard = $GLOBALS['phpgw']->session->appsession('clipboard','etemplate');
						if (!is_array($clipboard))
						{
							$msg .= lang('nothing in clipboard to paste !!!');
						}
						else
						{
							$content['cell'] = $clipboard;
						}
						if ($action == 'paste') break;
						// fall-through
					case 'copy':
					case 'cut':
						$GLOBALS['phpgw']->session->appsession('clipboard','etemplate',$widget);
						if ($action != 'cut')
						{
							$msg .= lang('widget copied into clipboard');
							break;
						}
						// fall-through
					case 'delete':
						if ($parent['type'] != 'grid')
						{
							// delete widget from parent
							if ($parent['type'])	// box
							{
								list($num,$options) = explode('/',$parent['size'],2);
								if ($num <= 1)	// cant delete last child --> only empty it
								{
									$parent[$num=1] = soetemplate::empty_cell();
								}
								else
								{
									for($n = $child_id; $n < $num; ++$n)
									{
										$parent[$n] = $parent[1+$n];
									}
									unset($parent[$num--]);
								}
								$parent['size'] = $num . ($options ? ','.$options : '');
							}
							else	// template itself
							{
								if (count($this->etemplate->children) <= 1)	// cat delete last child
								{
									$this->etemplate->children[0] = soetemplate::empty_cell();
								}
								else
								{
									unset($parent[$child_id]);
									$this->etemplate->children = array_values($this->etemplate->children);
								}
							}
							$action = 'save-no-merge';
						}
						else
						{
							$msg .= lang('cant delete a single widget from a grid !!!');
						}
						break;
						
					case 'box_prefs':
					case 'grid_prefs':	// to edit the parent, we set it as widget
						$content['cell'] = $parent;
						$content['path'] = $parent_path;
						$parent =& $this->etemplate->get_widget_by_path($parent_path,1);
						break;
					
					case 'box_insert_before':
					case 'box_insert_behind':
						$n = $child_id + (int)($action == 'box_insert_behind');
						if (!$parent['type'])	// template
						{
							$num = count($parent)-1;	// 0..count()-1
						}
						else // boxes
						{
							list($num,$options) = explode(',',$parent['size'],2);
						}
						for($i = $num; $i >= $n; --$i)
						{
							$parent[1+$i] = $parent[$i];
						}
						$parent[$n] = $content['cell'] = soetemplate::empty_cell();
						$content['path'] = $parent_path.'/'.$n;
						if ($parent['type']) $parent['size'] = (1+$num) . ($options ? ','.$options : '');
						$action = 'apply-no-merge';
						break;
						
					case 'box_swap_next':
						if (!$parent['type'])	// template
						{
							$num = count($parent)-1;	// 0..count()-1
						}
						else // boxes
						{
							list($num) = explode(',',$parent['size'],2);
						}
						if ($child_id < $num)
						{
							$content['cell'] = $parent[1+$child_id];
							$parent[1+$child_id] = $parent[$child_id];
							$parent[$child_id] = $content['cell'];
							$action = 'apply';
						}
						else
						{
							$msg .= lang('no further widget !!!');
						}
						break;
						
				}
				switch ($action)
				{
					case 'save': case 'apply':
						$widget = $content['cell'];
						// fall-through
					case 'save-no-merge':
					case 'apply-no-merge':
						//$this->etemplate->echo_tmpl();
						$ok = $this->etemplate->save($content);
						$msg .= $ok ? lang('Template saved') : lang('Error: while saveing !!!');
	
						// if necessary fix the version of our opener
						if ($content['opener']['name'] == $content['name'] &&
							$content['opener']['template'] == $content['template'] &&
							$content['opener']['group'] == $content['group'] &&
							$content['opener']['lang'] == $content['lang'])
						{
							$content['opener']['version'] = $content['version'];
						}
						$js = "opener.location.href='".$GLOBALS['phpgw']->link('/index.php',array(
								'menuaction' => 'etemplate.editor.show',
							)+$content['opener'])."';";
						if ($action == 'apply' || $action == 'apply-no-merge') break;
						// fall through
					case 'cancel':
						$js .= 'window.close();';
						echo "<html><body><script>$js</script></body></html>\n";
						$GLOBALS['phpgw']->common->phpgw_exit();
						break;
				}				
				if ($js)
				{
					$content['java_script'] = "<script>$js</script>";
				}
			}
			else
			{
				//echo "<p><b>".($_GET['path']).":</b></p>\n";
				list($name,$path) = explode(':',$_GET['path'],2);	// <name>:<path>
				
				if (!$this->etemplate->read($name))
				{
					$msg .= lang('Error: eTemplate not found !!!');
				}
				$widget =& $this->etemplate->get_widget_by_path($path);
				$parent =& $this->etemplate->get_widget_by_path($path,1);
				
				$content = $this->etemplate->as_array();
				$content['cell'] = $widget;
				$content['path'] = $path;
				
				foreach($this->etemplate->db_key_cols as $var)
				{
					if (isset($_GET[$var]))
					{
						$content['opener'][$var] = $_GET[$var];
					}
				}
			}
			$editor =& new etemplate('etemplate.editor.widget');
			$type_tmpl =& new etemplate;
			if ($type_tmpl->read('etemplate.editor.widget.'.$widget['type']))
			{
				$editor->set_cell_attribute('etemplate.editor.widget.generic','obj',$type_tmpl);
			}
			$editor->set_cell_attribute('cancel','onclick','window.close();');
			
			$readonlys['grid'] = $parent['type'] != 'grid';
			$readonlys['box'] = $parent['type'] == 'grid';
			
			$content['msg'] = $msg;
			$content['parent_type'] = $parent['type'] ? $parent['type'] : 'template';
			
			$GLOBALS['phpgw_info']['flags']['java_script'] = "<script>window.focus();</script>\n";
			$GLOBALS['phpgw_info']['flags']['app_header'] = lang('Editable Templates - Editor');
			$editor->exec('etemplate.editor.widget',$content,array(
					'type'  => array_merge($this->etemplate->types,$this->extensions),
					'align' => $this->aligns,
					'edit'  => $this->edit_menu,
					'grid' => $this->grid_menu,
					'box'   => $this->box_menu,
				),'',$this->etemplate->as_array()+array(
					'path'        => $content['path'],
					'old_version' => $this->etemplate->version,
					'opener'      => $content['opener'],
					'cell'        => $content['cell'],
				),2);
		}

		/**
		 * search the inc-dirs of etemplate and the app whichs template is edited for extensions / custom widgets
		 *
		 * extensions are class-files in $app/inc/class.${name}_widget.inc.php
		 * the extensions found will be saved in a class-var and in the session
		 *
		 * @param string $app='etemplate' app to scan
		 * @return string comma delimited list of new found extensions
		 */
		function scan_for_extensions($app='etemplate')
		{
			if (!is_array($this->extensions)) $this->extensions = array();
			
			if (isset($this->extensions['**loaded**'][$app])) return '';	// already loaded
			
			$labels = array();
			$dir = @opendir(PHPGW_SERVER_ROOT.'/'.$app.'/inc');
			while ($dir && ($file = readdir($dir)))
			{
				if (ereg('class\\.([a-zA-Z0-9_]*)_widget.inc.php',$file,$regs) &&
					 ($ext = $this->etemplate->loadExtension($regs[1].'.'.$app,$this->etemplate)))
				{
					if (is_array($ext))
					{
						$this->extensions += $ext;
						$labels += $ext;
					}
					else
					{
						$this->extensions[$regs[1]] = $ext;
						$labels[] = $ext;
					}
				}
			}
			// store the information in the session, our constructor loads it from there
			$GLOBALS['phpgw']->session->appsession('extensions','etemplate',$this->extensions);
			$apps_loaded = $GLOBALS['phpgw']->session->appsession('apps_loaded','etemplate');
			$apps_loaded[$app] = true;
			$GLOBALS['phpgw']->session->appsession('apps_loaded','etemplate',$apps_loaded);
			//_debug_array($this->extensions); _debug_array($apps_loaded);
			
			return implode(', ',$labels);
		}
	};



