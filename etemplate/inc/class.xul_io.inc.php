<?php
	/**************************************************************************\
	* phpGroupWare - eTemplates - XUL/XML Import & Export                      *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */


	if (!function_exists('var2xml'))
	{
		if (file_exists(PHPGW_API_INC.'class.xmltool.inc.php'))
		{
			include_once(PHPGW_API_INC.'class.xmltool.inc.php');
		}
		else
		{
			include_once('class.xmltool.inc.php');
		}
	}

	class xul_io
	{
		var $widget2xul;
		var $attr2xul;
		var $xul2widget;

		function xul_io()
		{
         $this->attr2xul = array(	// how to translate attr, common to all widgets
				'name' => 'id',
				'help' => 'statustext',
				'span' => 'span,class',
				'type' => '',	// this is the widget-name => dont write as attr
				'disabled' => 'disabled=true',
				'readonly' => 'readonly=true',
				'size' => 'options'
			);
			$this->widget2xul = array(	// how to translate widget-names and widget-spec. attr.
				'label' => array(
					'.name' => 'description',
					'label' => 'value'
				),
				'text' => array(
					'.name' => 'textbox',
					'size' => 'size,maxlength'
				),
				'textarea' => array(
					'.name' => 'textbox',
					'.set' => 'multiline=true',
					'size' => 'rows,cols'
				),
				'integer' => array(
					'.name' => 'textbox',
					'.set' => 'type=integer',
					'size' => 'min,max,size'
				),
				'float' => array(
					'.name' => 'textbox',
					'.set' => 'type=float',
					'size' => 'min,max,size'
				),
				'select' => array(
					'.name' => 'menulist,menupopup',
				),
				'select-multi' => array(	// multiselection, if size > 0
					'.name' => 'listbox',
					'size'  => 'rows,options'
				),
				'template' => array(
					'.name' => 'grid',
					'size'  => 'content'
				),
				'image'   => array(
					'.name' => 'image',
					'name' => 'src'
				),
				'tab' => array(
					'.name' => 'tabbox,tabs,tabpanels'
				),
				'button' => array(
					'.name' => 'button',
					'size'  => 'image,ro_image'
				)
			);
			$this->xul2widget = array(
				'menulist' => 'select',
				'listbox' => 'select',
				'description' => 'label'
			);
		}

		function set_attributes(&$widget,$attr,$val,$spanned='')
		{
			if ($attr != '')
			{
				$attrs = explode(',',$attr);

				if (count($attrs))
				{
					$vals = count($attrs) > 1 ? split(',',$val,count($attrs)) : array($val);
					while (list($n,$attr) = each($attrs))
					{
						if (($val = $vals[$n]) != '')
						{
							if ($attr == 'span')
							{
								$spanned = $val == 'all' ? 999 : $val - 1;
							}
							list($attr,$set) = explode('=',$attr);
							$widget->set_attribute($attr,$set != '' ? $set : $val);
						}
					}
				}
			}
		}

		function cell2widget($cell,&$spanned,$etempl,&$root,&$embeded_too)
		{
			$type = $cell['type'];
			if (is_array($type))
			{
				list(,$type) = each($type);
			}
			if (substr($type,0,6) == 'select')
			{
				$type = $cell['size'] > 1 ? 'select-multi' : 'select';
			}
			$widgetattr2xul = isset($this->widget2xul[$type]) ? $this->widget2xul[$type] : array();
			$type = isset($widgetattr2xul['.name']) ? $widgetattr2xul['.name'] : $type;
			list($parent,$child,$child2) = explode(',',$type);
			$widget = new xmlnode($parent);
			$attr_widget = &$widget;
			if ($child)
			{
				$child = new xmlnode($child);
				$attr_widget = &$child;
			}
			if ($child2)
			{
				$child2 = new xmlnode($child2);
			}
			if (isset($widgetattr2xul['.set']))	// set default-attr for type
			{
				$attrs = explode(',',$widgetattr2xul['.set']);
				while (list(,$attr) = each($attrs))
				{
					list($attr,$val) = explode('=',$attr);
					$widget->set_attribute($attr,$val);
				}
			}
			switch ($parent)
			{
			case 'nextmatch':
				$embeded = new etemplate($cell['size'],$etempl->as_array());
				if ($embeded_too)
				{
					$this->etempl2grid($embeded,&$root,$embeded_too);
				}
				$cell['size'] = $embeded->name;
				unset($embeded);
				break;
			case 'tabbox':
				$labels = explode('|',$cell['label']);  unset($cell['label']);
				$helps  = explode('|',$cell['help']);   unset($cell['help']);
				$names  = explode('|',$cell['name']);   unset($cell['name']);
				for ($n = 0; $n < count($labels); ++$n)
				{
					$tab = new xmlnode('tab');
					$tab->set_attribute('label',$labels[$n]);
					$tab->set_attribute('statustext',$helps[$n]);
					$child->add_node($tab);

					$embeded = new etemplate($names[$n],$etempl->as_array());
					if ($embeded_too)
					{
						$this->etempl2grid($embeded,&$root,$embeded_too);
					}
					$grid = new xmlnode('grid');
					$grid->set_attribute('id',$embeded->name);
					$child2->add_node($grid);
					unset($embeded);
				}
				break;
			case 'menulist':	// id,options belongs to the 'menupopup' child
				if ($cell['span'])
				{
					$widget->set_attribute('span',$cell['span']);
					unset($cell['span']);
				}
				// fall-trought
			case 'listbox':
				if ($cell['type'] != 'select')	// one of the sub-types
				{
					$attr_widget->set_attribute('type',$cell['type']);
				}
				break; 
			case 'vbox':
			case 'hbox':
				list($anz,$options) = split(',',$cell['size'],2);
				for ($n = 1; $n <= $anz; ++$n)
				{
					$widget->add_node($this->cell2widget($cell[$n],$no_span,$etempl,$root,$embeded_too));
					unset($cell[$n]);
				}
				$cell['size'] = $options;
				break;

			case 'grid':
				if ($cell['name'][0] != '@' && $embeded_too)
				{
					$embeded = new etemplate();
					if ($embeded->read($name=$embeded->expand_name($cell['name'],0,0),'default','default',0,'',$etempl->as_array()))
					{
						$this->etempl2grid($embeded,&$root,$embeded_too);
					}
					$cell['name'] = $embeded->name;
					unset($embeded);
				}
				break;
			}
			while (list($attr,$val) = each($cell))
			{
				if (is_array($val))	// correct old buggy etemplates
				{
					list(,$val) = each($val);
				}
				if (isset($widgetattr2xul[$attr]))
				{
					$attr = $widgetattr2xul[$attr];
				}
				elseif (isset($this->attr2xul[$attr]))
				{
					$attr = $this->attr2xul[$attr];
				}
				$this->set_attributes($attr_widget,$attr,$val,&$spanned);
			}
			if ($child)
			{
				$widget->add_node($child);
			}
			if ($child2)
			{
				$widget->add_node($child2);
			}
			return $widget;
		}

		function etempl2grid($etempl,&$root,&$embeded_too)
		{
			if (is_array($embeded_too))
			{
				if (isset($embeded_too[$etempl->name]))
				{
					return;	// allready embeded 
				}
			}
			else
			{
				$embeded_too = array();
			}
			$embeded_too[$etempl->name] = True;
			
			$xul_grid = new xmlnode('grid');
			$xul_grid->set_attribute('id',$etempl->name);
			$xul_grid->set_attribute('template',$etempl->template);
			$xul_grid->set_attribute('lang',$etempl->lang);
			$xul_grid->set_attribute('group',$etempl->group);
			$xul_grid->set_attribute('version',$etempl->version);
			$this->set_attributes($xul_grid,'width,height,border,class,spacing,padding',$etempl->size);

			$xul_columns = new xmlnode('columns');
			$xul_rows = new xmlnode('rows');

			reset($etempl->data);
			list(,$opts) = each ($etempl->data); // read over options-row
			while (list($r,$row) = each ($etempl->data))
			{
				$xul_row = new xmlnode('row');
				$this->set_attributes($xul_row,'class,valign',$opts["c$r"]);
				$this->set_attributes($xul_row,'height,disabled',$opts["h$r"]);

				$spanned = 0;
				while (list($c,$cell) = each($row))
				{
					if ($r == '1')	// write columns only once in the first row
					{
						$xul_column = new xmlnode('column');
						$this->set_attributes($xul_column,'width,disabled',$opts[$c]);
						$xul_columns->add_node($xul_column);
					}
					if ($spanned)
					{
						--$spanned;
						continue;	// spanned cells are not written
					}
					$xul_row->add_node($this->cell2widget($cell,$spanned,$etempl,$root,$embeded_too));
				}
				$xul_rows->add_node($xul_row);
			}
			$xul_grid->add_node($xul_columns);
			$xul_grid->add_node($xul_rows);

			if ($etempl->style != '')
			{
				$styles = new xmlnode('styles');
				$styles->set_value($etempl->style);
				$xul_grid->add_node($styles);
			}
			$root->add_node($xul_grid);

			return '';
		}

		function export($etempl)
		{
			if ($this->debug)
			{
				echo "<p>etempl->data = "; _debug_array($etempl->data);
			}
			$doc = new xmldoc();
			$doc->add_comment('$'.'Id$');

			$xul_overlay = new xmlnode('overlay');

			$embeded_too = True;
			$this->etempl2grid($etempl,&$xul_overlay,$embeded_too);

			$doc->add_root($xul_overlay);
			$xml = $doc->export_xml();

			//if ($this->debug)
			{
				echo "<pre>\n" . htmlentities($xml) . "\n</pre>\n";
			}
			return $xml;
		}

		function add_cell(&$etempl,$cell,&$box,&$col,$node_level)
		{
			if (!isset($box[$node_level-1]))
			{
				list($spanned) = explode(',',$cell['span']);
				$spanned = $spanned == 'all' ? $etempl->cols - $col : $spanned;

				$etempl->data[$etempl->rows][$etempl->num2chrs($col++)] = $cell;

			/*	if ($attr['type'] == 'template' && !empty($attr['name']) && $attr['name'][0] != '@')
				{
					$etempl->data[$etempl->rows][$etempl->num2chrs($col++)]['obj'] = new etemplate($attr['name']);
				} */
				while (--$spanned > 0)
				{
					$etempl->data[$etempl->rows][$etempl->num2chrs($col++)] = $etempl->empty_cell();
				}
			}
			else
			{
				$pcell = &$box[$node_level-1];
				$pcell[++$pcell['anz']] = $cell;
			}
		}

		function import(&$etempl,$data)
		{
			if ($this->debug)
			{
				echo "<pre>\n" . htmlentities($data) . "\n</pre><p>\n";
			}
			$parser = xml_parser_create();
			xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
			xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE,   1);
			$vals = $index = '';
			$ok = xml_parse_into_struct($parser, $data, $vals, $index);

			if (!$ok || !is_array($vals))
			{
				$err = 'Error Line '.xml_get_current_line_number($parser).', Column '.xml_get_current_column_number($parser).
				       ': '.xml_error_string(xml_get_error_code($parser));
			}
			xml_parser_free($parser);

			if ($err != '')
			{
				return $err;
			}
			while (list($n,$node) = each($vals))
			{
				$type = $node['type'];
				$tag = $node['tag'];
				$attr = is_array($node['attributes']) ? $node['attributes'] : array();
				if ($attr['id'])
				{
					$attr['name'] = $attr['id']; unset($attr['id']);
				}
				if (isset($attr['options']) && $attr['options'] != '')
				{
					$attr['size'] = $attr['options']; unset($attr['options']);
				}
				if ($tag == 'grid' && $type == 'complete' && !is_array($tab_attr))
				{
					$tag = 'template';
				}
				if ($tag != 'textbox' && !isset($attr['type']))
				{
					$attr['type'] = $this->xul2widget[$tag] ? $this->xul2widget[$tag] : $tag;
				}
				if ($this->debug)
				{
					echo "<p>$node[level]: $tag/$type: value='$node[value]' attr=\n"; _debug_array($attr);
				}
				switch ($tag)
				{
					case 'overlay':
						break;
					case 'grid':
						if ($type != 'close' && is_array($tab_attr))
						{
							$tab_names[] = $attr['name'];
							break;
						}
						if ($node['level'] > 2)	// level 1 is the overlay
						{
							return "Can't import nested $node[tag]'s !!!";
						}
						if ($type != 'open')
						{
							break;
						}
						if ($grid_started)	// more than one grid in the file --> place it into the cache
						{
							$cname = ($etempl->template == '' ? 'default' : $etempl->template).'/'.$etempl->name.
							         ($etempl->lang == '' ? '' : '.'.$etempl->lang);
							$imported[] = $etempl->name;
							$GLOBALS['phpgw_info']['etemplate']['cache'][$cname] = $etempl->as_array(1);
						}
						$grid_started = True;
						$etempl->init($attr);
						$size_opts = array('padding','spacing','class','border','height','width');
						for ($size = ''; list(,$opt) = each($size_opts); )
						{
							$size = $attr[$opt] . ($size != '' ? ",$size" : '');
						}
						$etempl->size = $size;
						$etempl->cols = $etempl->rows = 0;
						$etempl->data = array();
						break;
					case 'columns':
					case 'rows':
						break;
					case 'column':
						if ($type != 'complete')
						{
							return 'place widgets in <row> and not in <column> !!!';
						}
						$etempl->data[0][$etempl->num2chrs($etempl->cols++)] = $attr['width'] .
							($attr['disabled'] ? ','.$attr['disabled'] : '');
						break;
					case 'row':
						if ($type != 'open')
						{
							break;
						}
						$r = ++$etempl->rows;
						$col = 0;
						$etempl->data[0]["c$r"] = $attr['class'] . ($attr['valign'] ? ','.$attr['valign'] : '');
						$etempl->data[0]["h$r"] = $attr['height'] .
							($attr['disabled'] ? ','.$attr['disabled'] : '');
						break;
					case 'styles':
						$etempl->style = trim($node['value']);
						break;
					case 'tabbox':
						if ($type == 'open')
						{
							$tab_labels = $tab_helps = $tab_names = array();
							$tab_attr = $attr;
						}
						else
						{
							$tab_attr['type'] = 'tab';
							$tab_attr['label'] = implode('|',$tab_labels);
							$tab_attr['name'] = implode('|',$tab_names);
							$tab_attr['help'] = implode('|',$tab_helps);
							$tab_attr['span'] .= $tab_attr['class'] ? ','.$tab_attr['class'] : '';
							unset($tab_attr['class']);
							
							$this->add_cell($etempl,$tab_attr,&$box,&$col,$node['level']);
							unset($tab_attr);
						}
						break;
					case 'tabs':
					case 'tabpanels':
						break;
					case 'tab':
						if ($type != 'close')
						{
							$tab_labels[] = $attr['label'];
							$tab_helps[]  = $attr['statustext'];
						}
						break;
					case 'menupopup':
						if (is_array($menulist_attr))
						{
							$attr['help'] = $attr['statustext']; unset($attr['statustext']);
							unset($menulist_attr['type']);
							$menulist_attr += $attr;
						}
						break;
					case 'menulist':
						if ($type == 'open')
						{
							$menulist_attr = $attr;
						}
						else
						{
							$this->add_cell($etempl,$menulist_attr,&$box,&$col,$node['level']);
							unset($menulist_attr);
						}
						break; 
					case 'vbox':
					case 'hbox':
						if ($type == 'open')
						{
							$box[$node['level']] = $attr;
						}
						else
						{
							$cell = &$box[$node['level']];
							$cell['size'] = $cell['anz'] . ($cell['size'] != '' ? ','.$cell['size'] : '');
							unset($cell['anz']);
							$this->add_cell($etempl,$cell,&$box,&$col,$node['level']);
							unset($box[$node['level']]);
						}
						break;
					case 'textbox':
						if ($attr['multiline'])
						{
							$attr['type'] = 'textarea';
							$attr['size'] = $attr['rows'] . ($attr['cols'] ? ','.$attr['cols'] : '');
							unset($attr['cols']);
							unset($attr['rows']);
						}
						elseif ($attr['type'])	// integer,float
						{
							$attr['size'] = $attr['min'] . ($attr['max'] ? ','.$attr['max'] : ($attr['size'] ? ',':'')) . ','.$attr['size'];
							unset($attr['min']);
							unset($attr['max']);
						}
						else	// input
						{
							$attr['type'] = 'text';
							$attr['size'] .= $attr['maxlength']!='' ? ','.$attr['maxlength'] : '';
							unset($attr['maxlength']);
						}
						// fall-through
					default:
						switch ($tag)
						{
							case 'description':
							case 'label':
								$attr['label'] = $attr['value'];
								unset($attr['value']);
								break;
							case 'template':
								$attr['size'] = $attr['content'];
								unset($attr['content']);
								break;
							case 'image':
								$attr['name'] = $attr['src'];
								unset($attr['src']);
								break;
							case 'listbox':
								$attr['size'] = ereg_replace(',*$','',$attr['rows'].','.$attr['size']);
								unset($attr['rows']);
								break;
							case 'button':
								if ($attr['image'] || $attr['ro_image'])
								{
									$attr['size'] = $attr['image'] . ($attr['ro_image'] ? ','.$attr['ro_image'] : '');
									unset($attr['image']); unset($attr['ro_image']);
								}
								break;
						}
						$attr['help'] = $attr['statustext']; unset($attr['statustext']);
						$attr['span'] .= $attr['class'] ? ','.$attr['class'] : ''; unset($attr['class']);
						if ($type == 'close')
						{
							break;
						}
						$this->add_cell($etempl,$attr,&$box,&$col,$node['level']);
						break;
				}
			}
			if ($this->debug)
			{
				_debug_array($etempl->data);
			}
			$imported[] = $etempl->name;

			return $imported;
		}
	}

?>
