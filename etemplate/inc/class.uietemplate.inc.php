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

	include_once(PHPGW_INCLUDE_ROOT . '/etemplate/inc/class.boetemplate.inc.php');

	/*!
	@class etemplate
	@author ralfbecker
	@abstract creates dialogs / HTML-forms from eTemplate descriptions
	@discussion etemplate or uietemplate extends boetemplate, all vars and public functions are inherited
	@example $tmpl = CreateObject('etemplate.etemplate','app.template.name');
	@example $tmpl->exec('app.class.callback',$content_to_show);
	@example This creates a form from the eTemplate 'app.template.name' and takes care that
	@example the method / public function 'callback' in (bo)class 'class' of 'app' gets called
	@example if the user submitts the form. Vor the complete param's see the description of exec.
	@param $debug enables debug messages: 0=no, 1=calls to show and process_show, 2=content of process_show
	@param                                3=calls to show_cell OR template- or cell-type name
	@param $html instances of html class used to generate the html
	*/
	class etemplate extends boetemplate
	{
		var $debug; // 1=calls to show and process_show, 2=content after process_show,
						// 3=calls to show_cell and process_show_cell, or template-name or cell-type
		var $html;	// instance of html-class
		var $class_conf = array('nmh' => 'th','nmr0' => 'row_on','nmr1' => 'row_off');

		/*!
		@function etemplate
		@abstract constructor of etemplate class, reads an eTemplate if $name is given
		@param $name     name of etemplate or array with name and other keys
		@param $load_via name/array with keys of other etemplate to load in order to get $name
		*/
		function etemplate($name='',$load_via='')
		{
			$this->public_functions += array(
				'exec'			=> True,
				'process_exec'	=> True,
				'show'			=> True,
				'process_show'	=> True,
			);
			$this->html = CreateObject('etemplate.html');	// should  be in the api (older version in infolog)

			$this->boetemplate($name,$load_via);

			list($a,$b,$c,$d) = explode('.',$GLOBALS['phpgw_info']['server']['versions']['phpgwapi']);
			//echo "Version: $a.$b.$c.$d\n";
			if ($this->stable = $a <= 0 && $b <= 9 && $c <= 14)
				$this->class_conf = array();
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
		@result nothing
		*/
		function exec($method,$content,$sel_options='',$readonlys='',$preserv='',$changes='')
		{
			//echo "<br>globals[java_script] = '".$GLOBALS['phpgw_info']['etemplate']['java_script']."', this->java_script() = '".$this->java_script()."'\n";
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
			if (!$changes)
			{
				$changes = array();
			}
			$GLOBALS['phpgw']->common->phpgw_header();

			$id = $this->appsession_id();
			$GLOBALS['phpgw_info']['etemplate']['loop'] = False;
			$GLOBALS['phpgw_info']['etemplate']['form_options'] = '';	// might be set in show
			$GLOBALS['phpgw_info']['etemplate']['to_process'] = array();
			$html .= ($this->stable ? $this->html->nextMatchStyles()."\n\n" : ''). // so they get included once
				$this->html->form($this->include_java_script() .
					$this->show($this->complete_array_merge($content,$changes),$sel_options,$readonlys,'exec'),array(
						'etemplate_exec_id' => $id,
						'etemplate_exec_app' => $GLOBALS['phpgw_info']['flags']['currentapp']
					),'/etemplate/process_exec.php','','eTemplate',$GLOBALS['phpgw_info']['etemplate']['form_options']);
			//_debug_array($GLOBALS['phpgw_info']['etemplate']['to_process']);
			list($width,$height,,,,,$overflow) = explode(',',$this->size);
			if ($overflow)
			{
				$html = $this->html->div($html,'STYLE="'.($width?"width: $width; ":'').($height?"height: $height; ":'')."overflow: $overflow;\"");
			}
			$id = $this->save_appsession($this->as_array(1) + array(
				'readonlys' => $readonlys,
				'content' => $content,
				'changes' => $changes,
				'sel_options' => $sel_options,
				'preserv' => $preserv,
				'extension_data' => $GLOBALS['phpgw_info']['etemplate']['extension_data'],
				'to_process' => $GLOBALS['phpgw_info']['etemplate']['to_process'],
				'java_script' => $GLOBALS['phpgw_info']['etemplate']['java_script'],
				'method' => $method
			),$id);

			if ($this->stable)
			{
				echo parse_navbar() . $html;
			}
			else
			{
				echo $html;
				// this removes {} eg.'${test}' -> '$' $GLOBALS['phpgw']->template->set_var('phpgw_body',$html);
			}
		}

		/*!
		@function process_exec
		@abstract Makes the necessary adjustments to HTTP_POST_VARS before it calls the app's method
		@discussion This function is only to submit forms to, create with exec.
		@discussion All eTemplates / forms executed with exec are submited to this function
		@discussion (via the global index.php and menuaction). It then calls process_show
		@discussion for the eTemplate (to adjust the content of the HTTP_POST_VARS) and
		@discussion ExecMethod's the given callback from the app with the content of the form as first argument.
		*/
		function process_exec()
		{
			//echo "process_exec: HTTP_POST_VARS ="; _debug_array($GLOBALS['HTTP_POST_VARS']);
			$session_data = $this->get_appsession($GLOBALS['HTTP_POST_VARS']['etemplate_exec_id']);
			//echo "<p>process_exec: session_data ="; _debug_array($session_data);

			$content = $GLOBALS['HTTP_POST_VARS']['exec'];
			if (!is_array($content))
			{
				$content = array();
			}
			$this->init($session_data);
			$GLOBALS['phpgw_info']['etemplate']['extension_data'] = $session_data['extension_data'];
			$GLOBALS['phpgw_info']['etemplate']['java_script'] = $session_data['java_script'] || $GLOBALS['HTTP_POST_VARS']['java_script'];
			//echo "globals[java_script] = '".$GLOBALS['phpgw_info']['etemplate']['java_script']."', session_data[java_script] = '".$session_data['java_script']."', HTTP_POST_VARS[java_script] = '".$GLOBALS['HTTP_POST_VARS']['java_script']."'\n";
			//echo "process_exec($this->name) content ="; _debug_array($content);
			$this->process_show($content,$session_data['to_process'],'exec');

			//echo "process_exec($this->name) process_show(content) ="; _debug_array($content);
			//echo "process_exec($this->name) session_data[changes] ="; _debug_array($session_data['changes']);
			$content = $this->complete_array_merge($session_data['changes'],$content);
			//echo "process_exec($this->name) merge(changes,content) ="; _debug_array($content);

			if ($GLOBALS['phpgw_info']['etemplate']['loop'])
			{
				//echo "<p>process_exec($this->name): <font color=red>loop is set</font>, content=</p>\n"; _debug_array($content);
				$this->exec($session_data['method'],$session_data['content'],$session_data['sel_options'],
					$session_data['readonlys'],$session_data['preserv'],$content);
			}
			else
			{
				ExecMethod($session_data['method'],$this->complete_array_merge($content,$session_data['preserv']));
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
		@result the generated HTML
		*/
		function show($content,$sel_options='',$readonlys='',$cname='',$show_c=0,$show_row=0)
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
			reset($this->data);
			if (isset($this->data[0]))
			{
				list(,$width) = each($this->data);
			}
			else
			{
				$width = array();
			}
			for ($r = 0; $row = 1+$r /*list($row,$cols) = each($this->data)*/; ++$r)
			{
				if (!(list($r_key) = each($this->data)))	// no further row
				{
					if (!($this->autorepeat_idx($cols['A'],0,$r,$idx,$idx_cname) && $idx_cname) &&
						!($this->autorepeat_idx($cols['B'],1,$r,$idx,$idx_cname) && $idx_cname) ||
						!$this->isset_array($content,$idx))
					{
						break;                     	// no auto-row-repeat
					}
				}
				else
				{
					$cols = &$this->data[$r_key];
					$height = &$this->data[0]["h$row"];
					$class = &$this->data[0]["c$row"];
				}
				reset ($cols);
				$row_data = array();
				for ($c = 0; True /*list($col,$cell) = each($cols)*/; ++$c)
				{
					if (!(list($c_key) = each($cols)))		// no further cols
					{
						if (!$this->autorepeat_idx($cell,$c,$r,$idx,$idx_cname,True) ||
							!$this->isset_array($content,$idx))
						{
							break;	// no auto-col-repeat
						}
					}
					else
					{
						$cell = &$cols[$c_key];
					}
					$col = $this->num2chrs($c);
					$row_data[$col] = $this->show_cell($cell,$content,$sel_options,$readonlys,$cname,
						$c,$r,$span);
					if ($row_data[$col] == '' && $this->rows == 1)
					{
						unset($row_data[$col]);	// omit empty/disabled cells if only one row
						continue;
					}
					$colspan = $span == 'all' ? $this->cols-$c : 0+$span;
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
					$row_data[".$col"] .= $this->html->formatOptions($cell['align'],'ALIGN');
					list(,$cl) = explode(',',$cell['span']);
					$cl = isset($this->class_conf[$cl]) ? $this->class_conf[$cl] : $cl;
					$row_data[".$col"] .= $this->html->formatOptions($cl,'CLASS');
				}
				$rows[$row] = $row_data;

				$rows[".$row"] .= $this->html->formatOptions($height,'HEIGHT');
				list($cl) = explode(',',$class);
				if ($cl == 'nmr')
				{
					$cl .= $nmr_alternate++ & 1; // alternate color
				}
				$cl = isset($this->class_conf[$cl]) ? $this->class_conf[$cl] : $cl;
				$rows[".$row"] .= $this->html->formatOptions($cl,'CLASS');
				$rows[".$row"] .= $this->html->formatOptions($class,',VALIGN');
			}
			if (!$GLOBALS['phpgw_info']['etemplate']['styles_included'][$this->name])
			{
				$style = $this->html->style($this->style);
				$GLOBALS['phpgw_info']['etemplate']['styles_included'][$this->name] = True;
			}
			$html = $this->html->table($rows,$this->html->formatOptions($this->size,'WIDTH,HEIGHT,BORDER,CLASS,CELLSPACING,CELLPADDING'));

			/* does NOT work with mozilla: shows nothing if a div is inside a form
			list($width,$height,,,,,$overflow) = explode(',',$this->size);
			if (!empty($overflow)) {
				$div_style=' STYLE="'.($width?"width: $width; ":'').($height ? "height: $height; ":'')."overflow: $overflow\"";
				$html = $this->html->div($html,$div_style);
			}*/
			return "\n\n<!-- BEGIN $this->name -->\n$style\n".$html."<!-- END $this->name -->\n\n";
		}

		/*!
		@function show_cell
		@abstract generates HTML for 1 input-field / cell
		@discussion calls show to generate included eTemplates. Again only an INTERMAL function.
		@param $cell array with data of the cell: name, type, ...
		@param for rest see show
		@result the generated HTML
		*/
		function show_cell($cell,$content,$sel_options,$readonlys,$cname,$show_c,$show_row,&$span)
		{
			if ($this->debug >= 3 || $this->debug == $cell['type'])
			{
				echo "<p>etemplate.show_cell($this->name,name='${cell['name']}',type='${cell['type']}',cname='$cname')</p>\n";
			}
			list($span) = explode(',',$cell['span']);	// evtl. overriten later for type template

			if ($cell['name'][0] == '@' && $cell['type'] != 'template')
			{
				$cell['name'] = $this->get_array($content,substr($cell['name'],1));
			}
			$name = $this->expand_name($cell['name'],$show_c,$show_row,$content['.c'],$content['.row'],$content);

			if (ereg('^([^[]*)(\\[.*\\])$',$name,$regs))	// name contains array-index
			{
				$form_name = $cname == '' ? $name : $cname.'['.$regs[1].']'.$regs[2];
				eval(str_replace(']',"']",str_replace('[',"['",'$value = $content['.$regs[1].']'.$regs[2].';')));
				$org_name = substr($regs[2],1,-1);
			}
			else
			{
				$form_name = $cname == '' ? $name : $cname.'['.$name.']';
				$value = $content[$name];
				$org_name = $name;
			}
			if ($readonly = $cell['readonly'] || $readonlys[$name] || $readonlys['__ALL__'])
			{
				$options .= ' READONLY';
			}
			if ($cell['disabled'] || $readonly && $cell['type'] == 'button' && !strstr($cell['size'],','))
			{
				if ($this->rows == 1) {
					return '';	// if only one row omit cell
				}
				$cell = $this->empty_cell(); // show nothing
				$value = '';
			}
			$extra_label = True;

			list($type,$sub_type) = explode('-',$cell['type']);
			if ((!$this->types[$cell['type']] || !empty($sub_type)) && $this->haveExtension($type,'pre_process'))
			{
				$ext_type = $type;
				$extra_label = $this->extensionPreProcess($ext_type,$form_name,$value,$cell,$readonlys[$name]);

				if (!$regs)
				{
					$content[$name] = $value;	// set result for template
				}
				else
				{
					eval(str_replace(']',"']",str_replace('[',"['",'$content['.$regs[1].']'.$regs[2].' = $value;')));
				}
			}
			$label = $cell['label'];
			if ($label[0] == '@')
			{
				$label = $this->get_array($content,substr($label,1));
			}
			$help = $cell['help'];
			if ($help[0] == '@')
			{
				$help = $this->get_array($content,substr($help,1));
			}
			if ($this->java_script())
			{
				if ($help)
				{
					$options .= " onFocus=\"self.status='".addslashes(lang($help))."'; return true;\"";
					$options .= " onBlur=\"self.status=''; return true;\"";
					if ($cell['type'] == 'button')	// for button additionally when mouse over button
					{
						$options .= " onMouseOver=\"self.status='".addslashes(lang($help))."'; return true;\"";
						$options .= " onMouseOut=\"self.status=''; return true;\"";
					}
				}
				if ($cell['onchange'] && $cell['type'] != 'button')	// values != '1' can only set by a program (not in the editor so far)
				{
					$options .= ' onChange="'.($cell['onchange']=='1'?'this.form.submit();':$cell['onchange']).'"';
				}
			}
			list($type,$sub_type) = explode('-',$cell['type']);
			switch ($type)
			{
				case 'label':		//  size: [[b]old][[i]talic]
					$value = strlen($value) > 1 && !$cell['no_lang'] ? lang($value) : $value;
					if ($value != '' && strstr($cell['size'],'b')) $value = $this->html->bold($value);
					if ($value != '' && strstr($cell['size'],'i')) $value = $this->html->italic($value);
					$html .= $value;
					break;
				case 'raw':
					$html .= $value;
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
						$html .= $this->html->bold($value);
					}
					else
					{
						$html .= $this->html->input($form_name,$value,'',
							$options.$this->html->formatOptions($cell['size'],'SIZE,MAXLENGTH'));
						$GLOBALS['phpgw_info']['etemplate']['to_process'][$form_name] = $cell['type'];
					}
					break;
				case 'textarea':	// Multiline Text Input, size: [rows][,cols]
					$html .= $this->html->textarea($form_name,$value,
						$options.$this->html->formatOptions($cell['size'],'ROWS,COLS'));
					if (!$readonly)
						$GLOBALS['phpgw_info']['etemplate']['to_process'][$form_name] = $cell['type'];
					break;
				case 'checkbox':
					if (!empty($cell['size']))
					{
						list($true_val,$false_val,$ro_true,$ro_false) = explode(',',$cell['size']);
						$value = $value == $true_val;
					}
					else
					{
						$ro_true = 'x';
						$ro_false = '';
					}
					if ($value)
					{
						$options .= ' CHECKED';
					}
					if ($readonly)
					{
						$html .= $value ? $this->html->bold($ro_true) : $ro_false;
					}
					else
					{
						$html .= $this->html->input($form_name,'1','CHECKBOX',$options);
						$GLOBALS['phpgw_info']['etemplate']['to_process'][$form_name] = array(
							'type' => $cell['type'],
							'values' => $cell['size']
						);
					}
					break;
				case 'radio':		// size: value if checked
					if ($value == $cell['size'])
					{
						$options .= ' CHECKED';
					}
					if ($readonly)
					{
						$html .= $value == $cell['size'] ? $this->html->bold('x') : '';
					}
					else
					{
						$html .= $this->html->input($form_name,$cell['size'],'RADIO',$options);
						$GLOBALS['phpgw_info']['etemplate']['to_process'][$form_name] = $cell['type'];
					}
					break;
				case 'button':
					if ($this->java_script() && $cell['onchange'])
					{
						$html .= $this->html->input_hidden($form_name,'',False) . "\n";
						$html .= '<a href="" onClick="set_element(document.eTemplate,\''.$form_name.'\',\'pressed\'); document.eTemplate.submit(); return false;" '.$options.'>' .
							(strlen($label) <= 1 || $cell['no_lang'] ? $label : lang($label)) . '</a>';
					}
					else
					{
						list($img,$ro_img) = explode(',',$cell['size']);

						$html .= !$readonly ? $this->html->submit_button($form_name,$label,'',
								strlen($label) <= 1 || $cell['no_lang'],$options,$img) :
							$this->html->image(substr($this->name,0,strpos($this->name,'.')),$ro_img);
					}
					$extra_label = False;
					if (!$readonly)
						$GLOBALS['phpgw_info']['etemplate']['to_process'][$form_name] = $cell['type'];
					break;
				case 'hrule':
					$html .= $this->html->hr($cell['size']);
					break;
				case 'template':	// size: index in content-array (if not full content is past further on)
					if (is_object($cell['name']))
					{
						$cell['obj'] = &$cell['name'];
						unset($cell['name']);
						$cell['name'] = 'was Object';
						echo "<p>Object in Name in tpl '$this->name': "; _debug_array($this->data);
					}
					$obj_read = 'already loaded';
					if (!is_object($cell['obj']))
					{
						if ($cell['name'][0] == '@')
						{
							$cell['obj'] = $this->get_array($content,substr($cell['name'],1));
							$obj_read = is_object($cell['obj']) ? 'obj from content' : 'obj read, obj-name from content';
							if (!is_object($cell['obj']))
							{
								$cell['obj'] = new etemplate($cell['obj'],$this->as_array());
							}
						}
						else
						{  $obj_read = 'obj read';
							$cell['obj'] = new etemplate($cell['name'],$this->as_array());
						}
					}
					if ($this->debug >= 3 || $this->debug == $cell['type'])
					{
						echo "<p>show_cell::template(tpl=$this->name,name=$cell[name]): $obj_read</p>\n";
					}
					if ($this->autorepeat_idx($cell,$show_c,$show_row,$idx,$idx_cname) || $cell['size'] != '')
					{
						if ($span == '' && isset($content[$idx]['span']))
						{	// this allows a colspan in autorepeated cells like the editor
							list($span) = explode(',',$content[$idx]['span']);
							if ($span == 'all')
							{
								$span = 1 + $content['cols'] - $show_c;
							}
						}
						$readonlys = $this->get_array($readonlys,$idx); //$readonlys[$idx];
						$content = $this->get_array($content,$idx); // $content[$idx];
						if ($idx_cname != '')
						{
							$cname .= $cname == '' ? $idx_cname : '['.str_replace('[','][',str_replace(']','',$idx_cname)).']';
						}
						//echo "<p>show_cell-autorepeat($name,$show_c,$show_row,cname='$cname',idx='$idx',idx_cname='$idx_cname',span='$span'): content ="; _debug_array($content);
					}
					if ($readonly)
					{
						$readonlys['__ALL__'] = True;
					}
					$html .= $cell['obj']->show($content,$sel_options,$readonlys,$cname,$show_c,$show_row);
					break;
				case 'select':	// size:[linesOnMultiselect]
					if (!empty($cell['sel_options']))
					{
						if (!is_array($cell))
						{
							$sel_options = array();
							$opts = explode(',',$cell['sel_options']);
							while (list(,$opt) = each($opts))
							{
								list($k,$v) = explode('=',$opt);
								$sel_options[$k] = $v;
							}
						}
						else
						{
							$sel_options = $cell['sel_options'];
						}
					}
					elseif (isset($sel_options[$name]))
					{
						$sel_options = $sel_options[$name];
					}
					elseif (isset($sel_options[$org_name]))
					{
						$sel_options = $sel_options[$org_name];
					}
					elseif (isset($content["options-$name"]))
					{
						$sel_options = $content["options-$name"];
					}
					list($multiple) = explode(',',$cell['size']);

					if ($readonly)
					{
						$html .= $cell['no_lang'] ? $sel_options[$value] : lang($sel_options[$value]);
					}
					else
					{
						$html .= $this->html->select($form_name.($multiple > 1 ? '[]' : ''),$value,$sel_options,
							$cell['no_lang'],$options,$multiple);
						$GLOBALS['phpgw_info']['etemplate']['to_process'][$form_name] = $cell['type'];
					}
					break;
				case 'image':
					$image = $this->html->image(substr($this->name,0,strpos($this->name,'.')),
						$label,lang($help),'BORDER=0');
					$html .= $name == '' ? $image : $this->html->a_href($image,$name);
					$extra_label = False;
					break;
				case 'file':
					$html .= $this->html->input_hidden($path = str_replace($name,$name.'_path',$form_name),'.');
					$html .= $this->html->input($form_name,'','file');
					$GLOBALS['phpgw_info']['etemplate']['form_options'] =
						"enctype=\"multipart/form-data\" onSubmit=\"set_element2(this,'$path','$form_name')\"";
					$GLOBALS['phpgw_info']['etemplate']['to_process'][$form_name] = $cell['type'];
					$GLOBALS['phpgw_info']['etemplate']['to_process'][$path] = 'file-path';
					break;
				default:
					if ($ext_type && $this->haveExtension($ext_type,'render'))
					{
						$html .= $this->extensionRender($ext_type,$form_name,$value,$cell,$readonly);
					}
					else
					{
						$html .= "<i>unknown type '$cell[type]'</i>";
					}
					break;
			}
			if ($ext_type && !$readonly && // extension-processing need to be after all other and only with diff. name
				 !isset($GLOBALS['phpgw_info']['etemplate']['to_process'][$form_name]))
			{
				$GLOBALS['phpgw_info']['etemplate']['to_process'][$form_name] = 'ext-'.$ext_type;
			}
			if ($extra_label && ($label != '' || $html == ''))
			{
				if (strlen($label) > 1 && !$cell['no_lang'])
				{
					$label = lang($label);
				}
				$html_label = $html != '' && $label != '';

				if (strstr($label,'%s'))
				{
					$html = str_replace('%s',$html,$label);
				}
				elseif (($html = $label . ' ' . $html) == ' ')
				{
					$html = '&nbsp;';
				}
				if ($html_label)
				{
					$html = $this->html->label($html);
				}
			}
			return $html;
		}


		/*!
		@function process_show
		@abstract makes necessary adjustments on HTTP_POST_VARS after a eTemplate / form gots submitted
		@discussion This is only an internal function, dont call it direct use only exec
		@discussion Process_show uses a list of input-fields/widgets generated by show.
		@param $content HTTP_POST_VARS[$cname]
		@param $to_process list of widgets/form-fields to process
		@param $cname basename of our returnt content (same as in call to show)
		@result the adjusted content (by using the var-param &$content)
		*/
		function process_show(&$content,$to_process,$cname='')
		{
			if (!isset($content) || !is_array($content))
			{
				return;
			}
			if ($this->debug >= 1 || $this->debug == $this->name && $this->name)
			{
				echo "<p>process_show($this->name) start: content ="; _debug_array($content);
			}
			$content_in = $cname ? array($cname => $content) : $content;
			$content = array();
			reset($to_process);
			while (list($form_name,$type) = each($to_process))
			{
				if (is_array($type))
				{
					$attr = $type;
					$type = $attr['type'];
				}
				else
				{
					$attr = array();
				}
				$value = $this->get_array($content_in,$form_name);
				//echo "<p>process_show($this->name) $type: $form_name = '$value'</p>\n";
				list($type,$sub) = explode('-',$type);
				switch ($type)
				{
					case 'ext':
						$this->extensionPostProcess($sub,$form_name,$this->get_array($content,$form_name),$value);
						break;
					case 'text':
					case 'textarea':
						if (isset($value))
						{
							$value = stripslashes($value);
						}
						$this->set_array($content,$form_name,$value);
						break;
					case 'button':
						if ($value)
						{
							$this->set_array($content,$form_name,$value);
						}
						break;
					case 'select':
						$this->set_array($content,$form_name,is_array($value) ? implode(',',$value) : $value);
						break;
					case 'checkbox':
						if (!isset($value))	// checkbox was not checked
						{
							$value = 0;			// need to be reported too
						}
						if (!empty($attr['values']))
						{
							list($true_val,$false_val) = explode(',',$attr['values']);
							$value = $value ? $true_val : $false_val;
						}
						$this->set_array($content,$form_name,$value);
						break;
					default:
						$this->set_array($content,$form_name,$value);
						break;
				}
			}
			if ($cname)
			{
				$content = $content[$cname];
			}
			if ($this->debug >= 2 || $this->debug == $this->name && $this->name)
			{
				echo "<p>process_show($this->name) end: content ="; _debug_array($content);
			}
		}

		/*!
		@function java_script
		@syntax java_script( $consider_not_tested_as_enabled = True )
		@author ralfbecker
		@abstract is javascript enabled?
		@discussion this should be tested by the api at login
		@result true if javascript is enabled or not yet tested
		*/
		function java_script($consider_not_tested_as_enabled = True)
		{
			return !!$GLOBALS['phpgw_info']['etemplate']['java_script'] ||
				$consider_not_tested_as_enabled &&
				(!isset($GLOBALS['phpgw_info']['etemplate']['java_script']) ||
				$GLOBALS['phpgw_info']['etemplate']['java_script'].'' == '');
		}

		/*!
		@function include_java_script
		@syntax include_java_script(  )
		@author ralfbecker
		@abstract returns the javascript to be included by exec
		*/
		function include_java_script()
		{
			// this is to test if javascript is enabled
			if (!isset($GLOBALS['phpgw_info']['etemplate']['java_script']))
			{
				$js = '<script language="javascript">
document.write(\''.str_replace("\n",'',$this->html->input_hidden('java_script','1')).'\');
</script>
';
			}

			// here are going all the necesarry functions if javascript is enabled
			if ($this->java_script(True))
			{
				$js .= '<script language="JavaScript">
function set_element(form,name,value)
{
'. /* '	alert("set_element: "+name+"="+value);'. */ '
	for (i = 0; i < form.length; i++)
	{
		if (form.elements[i].name == name)
		{
			form.elements[i].value = value;
		}
	}
}

function set_element2(form,name,vname)
{
'. /* '	alert("set_element2: "+name+"="+vname);'. */ '
	for (i = 0; i < form.length; i++)
	{
		if (form.elements[i].name == vname)
		{
			value = form.elements[i].value;
		}
	}
'. /* '	alert("set_element2: "+name+"="+value);'. */ '
	for (i = 0; i < form.length; i++)
	{
		if (form.elements[i].name == name)
		{
			form.elements[i].value = value;
		}
	}
}
</script>
';
			}
			return $js;
		}
	};