<?php
	/**************************************************************************\
	* phpGroupWare - eTemplates - Editor                                       *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	class editor
	{
		var $debug;
		var $etemplate; // eTemplate we edit
		var $editor;	// editor eTemplate
		var $messages = array(
			'not_found' => 'Error: Template not found !!!',
			'deleted'   => 'Template deleted',
			'saved'     => 'Template saved',
			'error_writing' => 'Error: while saveing !!!',
			'other_version' => 'only an other Version found !!!'
		);
		var $aligns = array(
			'' => 'Left',
			'right' => 'Right',
			'center' => 'Center'
		);
		var $extensions = '';

		var $public_functions = array
		(
			'edit'         => True,
			'process_edit' => True,
			'delete'       => True,
			'show'         => True,
			//'admin'       => True,
			//'preferences' => True
		);

		function editor($lang_on_messages=True)
		{
			$this->etemplate = CreateObject('etemplate.etemplate');
			//echo '$HTTP_POST_VARS='; _debug_array($HTTP_POST_VARS);

			$this->editor = new etemplate('etemplate.editor');

			if ($lang_on_messages)
			{
				reset($this->messages);
				while (list($key,$msg) = each($this->messages))
					$this->messages[$key] = lang($msg);
			}
		}

		function edit($msg = '')
		{
			$get_vars = $GLOBALS['HTTP_GET_VARS'];
			if (isset($get_vars['name']) && !$this->etemplate->read($get_vars))
			{
				$msg .= $this->messages['not_found'];
			}
			if ($this->extensions == '')
			{
				$this->extensions = $this->scan_for_extensions();
				list($app) = explode('.',$this->name);
				if ($app != '' && $app != 'etemplate')
				{
					$this->extensions += $this->scan_for_extensions($app);
				}
			}
			$content = $this->etemplate->as_array() + array(
				'cols' => $this->etemplate->cols,
				'msg' => $msg
			);
			$cols_spanned = array();
			reset($this->etemplate->data);
			if (isset($this->etemplate->data[0]))
			{
				each($this->etemplate->data);
			}
			$no_button = array('values' => True,'edit' => True);
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
			$this->editor->exec('etemplate.editor.process_edit',$content,
				array(
					'type' => array_merge($this->etemplate->types,$this->extensions),
					'align' => $this->aligns
				),
				$no_button,$cols_spanned + array('**extensions**' => $this->extensions));
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
			$this->extensions = $content['**extensions**']; unset($content['**extensions**']);
			$this->etemplate->init($content);
			$this->etemplate->size = $content['size'];
			$this->etemplate->style = $content['style'];

			$this->etemplate->data = array($content['width']+$content['height']+$content['class']);
			$row = 1; $col = 0;
			while (isset($content[$name = $this->etemplate->num2chrs($col) . $row]))
			{
				$row_data[$this->etemplate->num2chrs($col++)] = $content[$name];
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
				if (!$this->etemplate->read($content))
				{
					$content['version'] = '';	// trying it without version
					$msg = $this->messages['other_version'];
					if (!$this->etemplate->read($content))
					{
						$msg = $this->messages['not_found'];
					}
				}
			}
			elseif ($content['delete'])
			{
				$this->delete();
				return;
			}
			elseif ($content['dump'])
			{
				$msg = $this->etemplate->dump2setup($content['name']);
			}
			elseif ($content['save'])
			{
				$ok = $this->etemplate->save($content['name'],$content['template'],$content['lang'],$content['group'],$content['version']);
				$msg = $this->messages[$ok ? 'saved' : 'error_writing'];
			}
			elseif ($content['show'])
			{
				$this->show();
				return;
			}
			elseif ($content['langfile'])
			{
				$additional = array();
				if (substr($content['name'],0,9) == 'etemplate')
				{
					$m = new editor(False);
					$additional = $m->messages + $this->etemplate->types + $this->aligns;
				}
				$msg = $this->etemplate->writeLangFile($content['name'],'en',$additional);
			}
			elseif ($content['db_tools'])
			{
				ExecMethod('etemplate.db_tools.edit');
				return;
			}
			$this->edit($msg);
		}

		function delete($post_vars='',$back = 'edit')
		{
			if (!$post_vars)
			{
				$post_vars = array();
			}
			if (isset($post_vars['name']))
			{
				$read_ok = $this->etemplate->read($post_vars);
			}
			if (isset($post_vars['yes']))	// Delete
			{
				if ($read_ok)
				{
					$read_ok = $this->etemplate->delete();
				}
				$this->edit($this->messages[$read_ok ? 'deleted' : 'not_found']);
				return;
			}
			if (isset($post_vars['no']))	// Back to ...
			{
				if (($back = $post_vars['back']) != 'show')
				{
					$back = 'edit';
				}
				$this->$back();
				return;
			}
			if (isset($GLOBALS['HTTP_GET_VARS']['name']) && !$this->etemplate->read($GLOBALS['HTTP_GET_VARS']))
			{
				$this->edit($this->messages['not_found']);
				return;
			}
			$content = $this->etemplate->as_array() + array('back' => $back);

			$delete = new etemplate('etemplate.editor.delete');

			$delete->exec('etemplate.editor.delete',$content,array(),array(),$content,'');
		}

		function show($post_vars='')
		{
			if ($this->debug)
			{
				echo "<p>etemplate.editor.show: content="; _debug_array($post_vars);
			}
			if (!$post_vars)
			{
				$post_vars = array();
			}
			if (isset($GLOBALS['HTTP_GET_VARS']['name']) && !$this->etemplate->read($GLOBALS['HTTP_GET_VARS']) ||
			    isset($post_vars['name']) && !$this->etemplate->read($post_vars))
			{
				$msg = $this->messages['not_found'];
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
			$content = $this->etemplate->as_array() + array('msg' => $msg);

			$show = new etemplate('etemplate.editor.show');
			$no_buttons = array(
				'save' => True,
				'show' => True,
				'dump' => True,
				'langfile' => True,
				'size' => True
			);
			if (!$msg && isset($post_vars['values']) && !isset($post_vars['vals']))
			{
				$cont = $post_vars['cont'];
				for ($r = 1; list($key,$val) = @each($cont); ++$r)
				{
					$vals["@$r"] = $key;
					$vals["A$r"] = $val;
				}
				$show->data[$show->rows]['A']['name'] = 'etemplate.editor.values';
				$show->data[$show->rows]['A']['size'] = 'vals';
				$content['vals'] = $vals;
			}
			else
			{
				$show->data[$show->rows]['A']['name'] = $this->etemplate;
				$vals = $post_vars['vals'];
				$olds = $post_vars['olds'];

				for ($r = 1; isset($vals["A$r"]); ++$r)
				{
					$content['cont'][$olds["@$r"]] = $vals["A$r"];
				}
			}
			$show->exec('etemplate.editor.show',$content,array(),$no_buttons,array('olds' => $vals),'');
		}

		/*!
		@function scan_for_extensions()
		@abstract search the inc-dirs of etemplate and the app whichs template is edited for extensions / custom widgets
		@note extensions are class-files in $app/inc/class.${name}_widget.inc.php
		@returns array with name => human_name of the extensions found
		*/
		function scan_for_extensions($app='etemplate')
		{
			$extensions = array();

			$dir = @opendir(PHPGW_SERVER_ROOT.'/'.$app.'/inc');

			while ($dir && ($file = readdir($dir)))
			{
				if (ereg('class\\.([a-zA-Z0-9_]*)_widget.inc.php',$file,$regs) &&
					 ($ext = $this->etemplate->loadExtension($regs[1].'.'.$app,$this->etemplate)))
				{
					$extensions[$regs[1]] = $ext->human_name;
				}
			}
			return $extensions;
		}
	};



