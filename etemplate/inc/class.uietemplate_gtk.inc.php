<?php
	/**************************************************************************\
	* phpGroupWare - EditableTemplates - HTML User Interface                   *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	include(PHPGW_API_INC . '/../../etemplate/inc/class.boetemplate.inc.php');

	/*!
	@class etemplate
	@abstract creates dialogs / HTML-forms from eTemplate descriptions
	@discussion etemplate or uietemplate extends boetemplate, all vars and public functions are inherited
	@example $tmpl = CreateObject('etemplate.etemplate','app.template.name');
	@example $tmpl->exec('app.class.callback',$content_to_show);
	@example This creates a form from the eTemplate 'app.template.name' and takes care that
	@example the method / public function 'callback' in (bo)class 'class' of 'app' gets called
	@example if the user submitts the form. Vor the complete param's see the description of exec.
	@param $debug enables debug messages: 0=no, 1=calls to show and process_show, 2=content of process_show
	@param                                3=calls to show_cell OR template- or cell-type name
	*/
	class etemplate extends boetemplate
	{
		var $debug;//='etemplate.editor.edit'; // 1=calls to show and process_show, 2=content after process_show,
						// 3=calls to show_cell and process_show_cell, or template-name or cell-type

		var $no_result = array(	// field-types which generate no direct result
			'label' => True,
			'hrule' => True,
			'image' => True,
			'raw' => True,
			'template' => True
		);
		var $font_width=8;

		/*!
		@function etemplate
		@abstract constructor of etemplate class, reads an eTemplate if $name is given
		@param as soetemplate.read
		*/
		function etemplate($name='',$template='default',$lang='default',$group=0,$version='',$rows=2,$cols=2)
		{
			$this->public_functions += array(
				'exec'			=> True,
			);
			$this->boetemplate();

			if (!$this->read($name,$template,$lang,$group,$version))
			{
				$this->init($name,$template,$lang,$group,$version,$rows,$cols);
				return False;
			}
			return True;
		}

		/*!
		@function exec
		@abstract Generats a Dialog from an eTemplate - abstract the UI-layer
		@discussion This is the only function an application should use, all other are INTERNAL and
		@discussion do NOT abstract the UI-layer, because they return HTML.
		@discussion Generates a webpage with a form from the template and puts process_exec in the
		@discussion form as submit-url to call process_show for the template before it
		@discussion ExecuteMethod's the given $methode of the caller.
		@param $methode Methode (e.g. 'etemplate.editor.edit') to be called if form is submitted
		@param $content Array with content to fill the input-fields of template, eg. the text-field
		@param          with name 'name' gets its content from $content['name']
		@param $sel_options Array or arrays with the options for each select-field, keys are the
		@param              field-names, eg. array('name' => array(1 => 'one',2 => 'two')) set the
		@param              options for field 'name'. ($content['options-name'] is possible too !!!)
		@param $readonlys Array with field-names as keys for fields with should be readonly
		@param            (eg. to implement ACL grants on field-level or to remove buttons not applicable)
		@param $preserv Array with vars which should be transported to the $method-call (eg. an id) array('id' => $id) sets $HTTP_POST_VARS['id'] for the $method-call
		@returns nothing
		*/
		function exec($method,$content,$sel_options='',$readonlys='',$preserv='')
		{
			if (!$sel_options)
			{
				$sel_options = array();
			}
			if (!$readonlys)
			{
				$readonlys = array();
			}
			if (!$preserv)
			{
				$preserv = array();
			}
			if (!class_exists('gtk'))	// load the gtk extension
			{
				if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN')
				{
					dl('php_gtk.dll');
				}
				else
				{
					dl('php_gtk.so');
				}
			}
			/*
			* Create a new top-level window and connect the signals to the appropriate
			* functions if the window not already exists.
			*/
			if (!$GLOBALS['phpgw_info']['etemplate']['window'])
			{
				$window = &new GtkWindow();
				$window->connect('destroy',array('etemplate','destroy'));
				$window->connect('delete-event',array('etemplate','delete_event'));
				$window->set_title('phpGroupWareGTK: '.$GLOBALS['phpgw_info']['server']['site_title']);
				$window->set_default_size(1024,600);

				$GLOBALS['phpgw_info']['etemplate']['window'] = &$window;
			}
			else
			{
				$window = &$GLOBALS['phpgw_info']['etemplate']['window'];
			}
			$this->result = array('test' => 'test');
			$table = &$this->show($this->result,$content,$sel_options,$readonlys);
			$table->set_border_width(10);
			$table->show();

			$swindow = &new GtkScrolledWindow(null,null);
			$swindow->set_policy(GTK_POLICY_AUTOMATIC,GTK_POLICY_AUTOMATIC);
			$swindow->add_with_viewport($table);
			$swindow->show();

			$window->add($swindow);
			$window->show_all();

			/* Run the main loop. */
			Gtk::main();

			$this->collect_results();

			$swindow->hide();
			$window->remove($swindow);
			unset($swindow);
			unset($this->widgets);

			// set application name so that lang, etc. works
			list($GLOBALS['phpgw_info']['flags']['currentapp']) = explode('.',$method);

			ExecMethod($method,array_merge($this->result,$preserv));
		}

		/*
		* Called when delete-event happens. Returns false to indicate that the event
		* should proceed.
		*/
		function delete_event()
		{
			return false;
		}

		/*
		* Called when the window is being destroyed. Simply quit the main loop.
		*/
		function destroy()
		{
			Gtk::main_quit();
			exit();
		}

		function button_clicked(&$var,$form_name)
		{
			echo "button '$form_name' pressed\n";
			$var = 'pressed';
			Gtk::main_quit();
		}

		function submit()
		{
			echo "OnChange --> submit\n";
			Gtk::main_quit();
		}

		function collect_results()
		{
			for($i=0; isset($this->widgets[$i]); ++$i)
			{
				$set = &$this->widgets[$i];
				$widget = &$set['widget'];

				$val_is_set = False;
				echo "$i: $set[name]/$set[type]/".Gtk::type_name($widget->get_type());
				switch ($set['type'])
				{
					case 'button':	// is set to 'pressed' or is '' (not unset !!!)
						$val_is_set = ($val = $this->get_array($this->result,$set['name']));
						break;
					case 'int':
					case 'float':
					case 'text':
					case 'textarea':
						$val = $widget->get_chars(0,-1);
						$val_is_set = True;
						break;
					case 'checkbox':
						$val = $widget->get_active();
						$val_is_set = True;
						break;
					case 'radio':
						if ($widget->get_active())
						{
							$val = $set['set_val'];
							$val_is_set = True;
						}
						break;
					case 'select':
						$entry = $widget->entry;
						$selected = $entry->get_chars(0,-1);
						$options = $set['set_val'];
						reset($options);
						while (list($key,$val) = each($options))
						{
							if ($val == $selected)
							{
								$val = $key;
								$val_is_set = True;
								break;
							}
						}
						break;
					case 'date':
				}
				echo $val_is_set && !$set['readonly'] ? " = '$val'\n" : " NOT SET\n";

				$this->set_array($this->result,$set['name'],$val,$val_is_set && !$set['readonly']);
			}
		}

		/*!
		@function show
		@abstract creates HTML from an eTemplate
		@discussion This is done by calling show_cell for each cell in the form. show_cell itself
		@discussion calls show recursivly for each included eTemplate.
		@discussion You can use it in the UI-layer of an app, just make shure to call process_show !!!
		@discussion This is intended as internal function and should NOT be called by new app's direct,
		@discussion as it deals with HTML and is so UI-dependent, use exec instead.
		@param $content array with content for the cells, keys are the names given in the cells/form elements
		@param $sel_options array with options for the selectboxes, keys are the name of the selectbox
		@param $readonlys array with names of cells/form-elements to be not allowed to change
		@param            This is to facilitate complex ACL's which denies access on field-level !!!
		@param $cname basename of names for form-elements, means index in $HTTP_POST_VARS
		@param        eg. $cname='cont', element-name = 'name' returned content in $HTTP_POST_VARS['cont']['name']
		@param $show_xxx row,col name/index for name expansion
		@returns the generated HTML
		*/
		function show(&$result,$content,$sel_options='',$readonlys='',$cname='',$show_c=0,$show_row=0)
		{
			if (!$sel_options)
			{
				$sel_options = array();
			}
			if (!$readonlys)
			{
				$readonlys = array();
			}
			if ($this->debug >= 1 || $this->debug == $this->name && $this->name)
			{
				echo "<p>etemplate.show($this->name): $cname =\n"; _debug_array($content);
			}
			if (!is_array($content))
			{
				$content = array();	// happens if incl. template has no content
			}
			$content += array(	// for var-expansion in names in show_cell
				'.c' => $show_c,
				'.col' => $this->num2chrs($show_c-1),
				'.row' => $show_row
			);

			$table = &new GtkTable($this->rows,$this->cols,False);
			$table->set_row_spacings(2);
			$table->set_col_spacings(5);
			$table->show();

			reset($this->data);
			if (isset($this->data[0]))
			{
				list($nul,$width) = each($this->data);
			}
			else
			{
				$width = array();
			}
			for ($r = 0; $row = 1+$r /*list($row,$cols) = each($this->data)*/; ++$r)
			{
				$old_cols = $cols; $old_class = $class; $old_height = $height;
				if (!(list($nul,$cols) = each($this->data)))	// no further row
				{
					$cols = $old_cols; $class = $old_class; $height = $old_height;
					list($nul,$cell) = each($cols); reset($cols);
					if (!($this->autorepeat_idx($cols['A'],0,$r,$idx,$idx_cname) && $idx_cname) &&
						!($this->autorepeat_idx($cols['B'],1,$r,$idx,$idx_cname) && $idx_cname) ||
						!$this->isset_array($idx,$content))
					{
						break;                     	// no auto-row-repeat
					}
				}
				else
				{
					$height = $this->data[0]["h$row"];
					list($class,$valign) = explode(',',$this->data[0]["c$row"]);
					switch($valign)
					{
						case 'top':
							$valign = 0.0;
							break;
						case 'bottom':
							$valign = 1.0;
							break;
						default:
							$valign = 0.5;
					}
				}
				$row_data = array();
				for ($c = 0; True /*list($col,$cell) = each($cols)*/; ++$c)
				{
					$old_cell = $cell;
					if (!(list($nul,$cell) = each($cols)))		// no further cols
					{
						$cell = $old_cell;
						if (!$this->autorepeat_idx($cell,$c,$r,$idx,$idx_cname,True) ||
							!$this->isset_array($idx,$content))
						{
							break;	// no auto-col-repeat
						}
					}
					$col = $this->num2chrs($c);

					//$row_data[$col] = $this->show_cell($cell,$content,$sel_options,$readonlys,$cname,$c,$r,$span);
					$widget = &$this->show_cell($cell,$content,$sel_options,$readonlys,$cname,$c,$r,$span,$result);

					if (($colspan = $span == 'all' ? $this->cols-$c : 0+$span) < 1)
					{
						$colspan = 1;
					}
					if ($widget)
					{
						$widget->show();
						if ($align = ($cell['align'] || $valign))
						{
							switch ($cell['align'])
							{
								case 'center':
									$align = 0.5;
									break;
								case 'right':
									$align = 1.0;
									break;
								default:
									$align = 0.0;
							}
							$align = &new GtkAlignment($align,$valign,$cell['type'] == 'hrule' ? 1.0 : 0.0,0.0);
							$align->add($widget);
						}
						$table->attach($align ? $align : $widget, $c, $c+$colspan, $r, $r+1,GTK_FILL,GTK_FILL,0,0);
					}
					if ($row_data[$col] == '' && $this->rows == 1)
					{
						unset($row_data[$col]);	// omit empty/disabled cells if only one row
						continue;
					}
					if ($colspan > 1)
					{
						$row_data[".$col"] .= " COLSPAN=$colspan";
						for ($i = 1; $i < $colspan; ++$i,++$c)
						{
							each($cols);	// skip next cell(s)
						}
					}
					elseif ($width[$col])	// width only once for a non colspan cell
					{
						$row_data[".$col"] .= ' WIDTH='.$width[$col];
						$width[$col] = 0;
					}
//					$row_data[".$col"] .= $this->html->formatOptions($cell['align'],'ALIGN');
//					$row_data[".$col"] .= $this->html->formatOptions($cell['span'],',CLASS');
				}
				$rows[$row] = $row_data;

//				$rows[".$row"] .= $this->html->formatOptions($height,'HEIGHT');
				list($cl) = explode(',',$class);
				if ($cl == 'nmr')
				{
					$cl .= $nmr_alternate++ & 1; // alternate color
				}
//				$rows[".$row"] .= $this->html->formatOptions($cl,'CLASS');
//				$rows[".$row"] .= $this->html->formatOptions($class,',VALIGN');
			}
			if (!$GLOBALS['phpgw_info']['etemplate']['styles_included'][$this->name])
			{
//				$style = $this->html->style($this->style);
				$GLOBALS['phpgw_info']['etemplate']['styles_included'][$this->name] = True;
			}
			return $table;
		}

		/*!
		@function show_cell
		@abstract generates HTML for 1 input-field / cell
		@discussion calls show to generate included eTemplates. Again only an INTERMAL function.
		@param $cell array with data of the cell: name, type, ...
		@param for rest see show
		@returns the generated HTML
		*/
		function show_cell($cell,$content,$sel_options,$readonlys,$cname,$show_c,$show_row,&$span,&$result)
		{
			if ($this->debug >= 3 || $this->debug == $cell['type'])
			{
				echo "<p>etemplate.show_cell($this->name,name='${cell['name']}',type='${cell['type']}',cname='$cname')</p>\n";
			}
			list($span) = explode(',',$cell['span']);	// evtl. overriten later for type template

			$name = $this->expand_name($cell['name'],$show_c,$show_row,$content['.c'],$content['.row'],$content);

			// building the form-field-name depending on prefix $cname and possibl. Array-subscript in name
			if (ereg('^([^[]*)(\\[.*\\])$',$name,$regs))	// name contains array-index
			{
				$form_name = $cname == '' ? $name : $cname.'['.$regs[1].']'.$regs[2];
				eval(str_replace(']',"']",str_replace('[',"['",'$value = $content['.$regs[1].']'.$regs[2].';')));
				$org_name = substr($regs[2],1,-1);
				eval(str_replace(']',"']",str_replace('[',"['",'$var = &$result['.$regs[1].']'.$regs[2].';')));
			}
			else
			{
				$form_name = $cname == '' ? $name : $cname.'['.$name.']';
				$value = $content[$name];
				$org_name = $name;
				$var = &$result[$name];
			}
			$readonly = $cell['readonly'] || $readonlys[$name] || $readonlys['__ALL__'];

			if ($cell['disabled'] || $cell['type'] == 'button' && $readonly)
			{
				if ($this->rows == 1) {
					return '';	// if only one row omit cell
				}
				$cell = $this->empty_cell(); // show nothing
				$value = '';
			}
			if ($cell['onchange'])	// values != '1' can only set by a program (not in the editor so far)
			{
				$options .= ' onChange="'.($cell['onchange']=='1'?'this.form.submit();':$cell['onchange']).'"';
			}

			if (strlen($label = $cell['label']) > 1)
			{
				$label = lang($label);
			}
			list($left_label,$right_label) = explode('%s',$label);

			//echo "show_cell: type='$cell[type]', name='$cell[name]'-->'$name', value='$value'\n";
			$widget = False;
			switch ($cell['type'])
			{
				case 'label':		//  size: [[b]old][[i]talic]
					$value = strlen($value) > 1 && !$cell['no_lang'] ? lang($value) : $value;

					//if ($value != '' && strstr($cell['size'],'b')) $value = $this->html->bold($value);
					//if ($value != '' && strstr($cell['size'],'i')) $value = $this->html->italic($value);
					$html .= $value;

					if ($value)
					{
						$widget = &new GtkLabel($value);
						if ($cell['align'] != 'center')
						{
							$widget->set_justify($cell['align'] == 'right' ? GTK_JUSTIFY_RIGHT : GTK_JUSTIFY_LEFT);
						}
					}
					break;
				case 'raw':
					//$html .= $value;
					break;
				case 'int':		// size: [min][,[max][,len]]
				case 'float':
					list($min,$max,$cell['size']) = explode(',',$cell['size']);
					if ($cell['size'] == '')
					{
						$cell['size'] = $cell['type'] == 'int' ? 5 : 8;
					}
					// fall-through
				case 'text':		// size: [length][,maxLength]
					if ($readonly)
					{
						//$html .= $this->html->bold($value);
					}
					else
					{
						//$html .= $this->html->input($form_name,$value,'',$options.$this->html->formatOptions($cell['size'],'SIZE,MAXLENGTH'));
					}
					list($len,$max) = explode(',',$cell['size']);
					$widget = &new GtkEntry();
					$widget->set_text($value);
					if ($max)
					{
						$widget->set_max_length($max);
					}
					$widget->set_editable(!$readonly);
					if ($len)
					{
						$widget->set_usize($len*$this->font_width,0);
					}
					break;
				case 'textarea':	// Multiline Text Input, size: [rows][,cols]
					//$html .= $this->html->textarea($form_name,$value,$options.$this->html->formatOptions($cell['size'],'ROWS,COLS'));
					$widget = &new GtkText(null,null);
					$widget->insert_text($value,strlen($value));
					$widget->set_editable(!$readonly);
					break;
/*				case 'date':
					if ($cell['size'] != '')
					{
						$date = split('[/.-]',$value);
						$mdy  = split('[/.-]',$cell['size']);
						for ($value=array(),$n = 0; $n < 3; ++$n)
						{
							switch($mdy[$n])
							{
								case 'Y': $value[0] = $date[$n]; break;
								case 'm': $value[1] = $date[$n]; break;
								case 'd': $value[2] = $date[$n]; break;
							}
						}
					}
					else
					{
						$value = array(date('Y',$value),date('m',$value),date('d',$value));
					}
					if ($readonly)
					{
						$html .= $GLOBALS['phpgw']->common->dateformatorder($value[0],$value[1],$value[2]);
					}
					else
					{
						$html .= $this->sbox->getDate($name.'[Y]',$name.'[m]',$name.'[d]',$value,$options);
					}
					break;
*/				case 'checkbox':
					if ($value)
					{
						$options .= ' CHECKED';
					}
					//$html .= $this->html->input($form_name,'1','CHECKBOX',$options);
					$widget = &new GtkCheckButton($right_label);
					$right_label = '';
					$widget->set_active($value);
					break;
				case 'radio':		// size: value if checked
					if ($value == $cell['size'])
					{
						$options .= ' CHECKED';
					}
					//$html .= $this->html->input($form_name,$cell['size'],'RADIO',$options);
					if (isset($this->buttongroup[$form_name]))
					{
						$widget = &new GtkRadioButton($this->buttongroup[$form_name],$right_label);
					}
					else
					{
						$this->buttongroup[$form_name] = $widget = &new GtkRadioButton(null,$right_label);
					}
					$right_label = '';
					$widget->set_active($value == $cell['size']);
					break;
				case 'button':
					//$html .= $this->html->submit_button($form_name,$cell['label'],'',strlen($cell['label']) <= 1 || $cell['no_lang'],$options);
					$widget = &new GtkButton(strlen($cell['label']) > 1 ? lang($cell['label']) : $cell['label']);
					$widget->connect_object('clicked', array('etemplate', 'button_clicked'),&$var,$form_name);
					break;
				case 'hrule':
					//$html .= $this->html->hr($cell['size']);
					$widget = &new GtkHSeparator();
					break;
				case 'template':	// size: index in content-array (if not full content is past further on)
					if ($this->autorepeat_idx($cell,$show_c,$show_row,$idx,$idx_cname) || $cell['size'] != '')
					{
						if ($span == '' && isset($content[$idx]['span']))
						{	// this allows a colspan in autorepeated cells like the editor
							$span = explode(',',$content[$idx]['span']); $span = $span[0];
							if ($span == 'all')
							{
								$span = 1 + $content['cols'] - $show_c;
							}
						}
						$readonlys = $readonlys[$idx];
						$content = $content[$idx];
						$var = &$result[$idx];
						if ($idx_cname != '')
						{
							$cname .= $cname == '' ? $idx_cname : "[$idx_cname]";
						}
						//echo "<p>show_cell-autorepeat($name,$show_c,$show_row,cname='$cname',idx='$idx',idx_cname='$idx_cname',span='$span'): readonlys[$idx] ="; _debug_array($readonlys);
					}
					else
					{
						$var = &$result;
					}
					if ($readonly)
					{
						$readonlys['__ALL__'] = True;
					}
					$templ = is_object($cell['name']) ? $cell['name'] : new etemplate($name);
					$templ->widgets = &$this->widgets;
					//$html .= $templ->show($content,$sel_options,$readonlys,$cname,$show_c,$show_row);
					$widget = $templ->show($var,$content,$sel_options,$readonlys,$cname,$show_c,$show_row);
					break;
				case 'select':	// size:[linesOnMultiselect]
					if (isset($sel_options[$name]))
					{
						$sel_options = $sel_options[$name];
					}
					elseif (isset($sel_options[$org_name]))
					{
						$sel_options = $sel_options[$org_name];
					} elseif (isset($content["options-$name"]))
					{
						$sel_options = $content["options-$name"];
					}
					//$html .= $this->sbox->getArrayItem($form_name.'[]',$value,$sel_options,$cell['no_lang'],$options,$cell['size']);

					reset($sel_options);
					for ($maxlen=0; list($key,$val) = each($sel_options); )
					{
						if (!$cell['no_lang'])
						{
							$sel_options[$key] = lang($val);
						}
						if (($len = strlen($sel_options[$key])) > $maxlen)
						{
							$maxlen = $len;
						}
					}
					$widget = &new GtkCombo();
					$widget->set_popdown_strings($sel_options);
					$entry = $widget->entry;
					$entry->set_text($sel_options[$value]);
					$entry->set_editable(False);
					$entry->set_usize($maxlen*$this->font_width,0);
					if ($cell['onchange'] == '1')
					{
						$entry->connect('changed',array('etemplate', 'submit'));
					}
					break;
/*				case 'select-percent':
					$html .= $this->sbox->getPercentage($form_name,$value,$options);
					break;
				case 'select-priority':
					$html .= $this->sbox->getPriority($form_name,$value,$options);
					break;
				case 'select-access':
					$html .= $this->sbox->getAccessList($form_name,$value,$options);
					break;
				case 'select-country':
					$html .= $this->sbox->getCountry($form_name,$value,$options);
					break;
				case 'select-state':
					$html .= $this->sbox->list_states($form_name,$value);  // no helptext - old Function!!!
					break;
				case 'select-cat':
					$html .= $this->sbox->getCategory($form_name.'[]',$value,$cell['size'] >= 0,
						False,$cell['size'],$options);
					break;
				case 'select-account':
					$type = substr(strstr($cell['size'],','),1);
					if ($type == '')
					{
						$type = 'accounts';	// default is accounts
					}
					$html .= $this->sbox->getAccount($form_name.'[]',$value,2,$type,0+$cell['size'],$options);
					break;
				case 'image':
					$image = $this->html->image(substr($this->name,0,strpos($this->name,'.')),
						$cell['label'],lang($cell['help']),'BORDER=0');
					$html .= $name == '' ? $image : $this->html->a_href($image,$name);
					break;
*/				default:
					//$html .= '<i>unknown type</i>';
					$widget = &new GtkLabel('unknown type: '.$cell['type']);
					$widget->set_justify(GTK_JUSTIFY_LEFT);
					break;
			}
			if ($widget && !$readonly && !$this->no_result[$cell['type']])
			{
				$this->widgets[] = array(
					'widget' => &$widget,
					'type' => $cell['type'],
					'set_val' => $cell['type'] == 'radio' ? $cell['size'] : $sel_options,
					'name' => $form_name,
					'readonly' => $readonly
				);
			}
			if ($cell['type'] != 'button' && $cell['type'] != 'image' && ($left_label || $right_label))
			{
				if (!$widget && !$right_label)
				{
					$widget = &new GtkLabel($left_label);
				}
				else
				{
					$hbox = &new GtkHBox(False,5);
					if ($left_label)
					{
						$left = &new GtkLabel($left_label);
						$left->show();
						$hbox->add($left);
					}
					if ($widget)
					{
						$widget->show();
						$hbox->add($widget);
					}
					if ($right_label)
					{
						$right = &new GtkLabel($right_label);
						$right->show();
						$hbox->add($right);
					}
				}
			}
			if ($cell['help'] && $widget)
			{
				if (!$this->tooltips)
				{
					$this->tooltips = &new GtkTooltips();
				}
				$this->tooltips->set_tip($widget,lang($cell['help']),$this->name.'/'.$form_name);
			}
			return $hbox ? $hbox : $widget;
		}
	};