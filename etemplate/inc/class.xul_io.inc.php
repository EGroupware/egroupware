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

	include(PHPGW_SERVER_ROOT . '/etemplate/inc/xmltools.php');


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
				'readonly' => 'readonly=true'
			);
			$this->widget2xul = array(	// how to translate widget-names and widget-spec. attr.
				'label' => array(
					'.name' => 'label',
					'label' => 'value'
				),
				'text' => array(
					'.name' => 'textbox',
					'size' => 'size,maxlength'
				),
				'textarea' => array(
					'.name' => 'textbox',
					'.set' => 'multiline=true',
					'size' => 'cols,rows'
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
					'.name' => 'menulist,menupopup'
				)
			);
			$this->xul2widget = array(
				'menupopup' => 'select'
			);
		}

		function set_attributes(&$widget,$attr,$val,$spanned='')
		{
			if ($attr != '')
			{
				$attrs = explode(',',$attr);

				if (count($attrs))
				{
					$vals = count($attrs) > 1 ? explode(',',$val) : array($val);
					while (list($n,$attr) = each($attrs))
					{
						if ($val = $vals[$n])
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

		function export($etempl)
		{
			if ($this->debug)
			{
				echo "<p>etempl->data = "; _debug_array($etempl->data);
			}
			$doc = new xmldoc();

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
				$this->set_attributes($xul_row,'height',$opts["h$r"]);

				$spanned = 0;
				while (list($c,$cell) = each($row))
				{
					if ($r == '1')	// write columns only once in the first row
					{
						$xul_column = new xmlnode('column');
						$this->set_attributes($xul_column,'width',$opts[$c]);
						$xul_columns->add_node($xul_column);
					}
					if ($spanned)
					{
						--$spanned;
						continue;	// spanned cells are not written
					}
					$type = $cell['type'];
					if (is_array($type))
					{
						list(,$type) = each($type);
					}
					$widgetattr2xul = isset($this->widget2xul[$type]) ? $this->widget2xul[$type] : array();
					$type = isset($widgetattr2xul['.name']) ? $widgetattr2xul['.name'] : $type;
					list($parent,$child) = explode(',',$type);
					$widget = new xmlnode($child ? $child : $parent);

					if (isset($widgetattr2xul['.set']))	// set default-attr for type
					{
						$attrs = explode(',',$widgetattr2xul[1]);
						while (list(,$attr) = each($attr))
						{
							list($attr,$val) = explode('=',$attr);
							$widget->set_attribute($attr,$val);
						}
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
						$this->set_attributes($widget,$attr,$val,&$spanned);
					}
					if ($child)
					{
						$parent = new xmlnode($parent);
						$parent->add_node($widget);
						$xul_row->add_node($parent);
					}
					else
					{
						$xul_row->add_node($widget);
					}
				}
				$xul_rows->add_node($xul_row);
			}
			$xul_grid->add_node($xul_columns);
			$xul_grid->add_node($xul_rows);

			if ($etempl->style != '')
			{
				$styles = new xmlnode('styles');
				$styles->set_text($etempl->style);
				$xul_grid->add_node($styles);
			}
			$doc->add_root($xul_grid);
			$xml = $doc->dump_mem();

			if ($this->debug)
			{
				echo "<pre>\n" . htmlentities($xml) . "\n</pre>\n";
			}
			return $xml;
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
			xml_parse_into_struct($parser, $data, $vals, $index);

			if (!is_array($vals))
			{
				$err = xml_error_string(xml_get_error_code($parser));
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
				if ($tag != 'textbox')
				{
					$attr['type'] = $this->xul2widget[$tag] ? $this->xul2widget[$tag] : $tag;
				}
				if ($this->debug)
				{
					echo "<p>$node[level]: $tag/$type: value='$node[value]' attr="; _debug_array($attr);
				}
				switch ($tag)
				{
					case 'grid':
						if ($type != 'open')
						{
							break;
						}
						if ($node['level'] > 1)
						{
							return "Can't import nested $node[type]'s !!!";
						}
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
					case 'menulist':
						break;
					case 'column':
						if ($type != 'complete')
						{
							return 'place widgets in <row> and not in <column> !!!';
						}
						$etempl->data[0][$etempl->num2chrs($etempl->cols++)] = $attr['width'];
						break;
					case 'row':
						if ($type != 'open')
						{
							break;
						}
						$r = ++$etempl->rows;
						$col = 0;
						$etempl->data[0]["c$r"] = $attr['class'] . ($attr['valign'] ? ','.$attr['valign'] : '');
						$etempl->data[0]["h$r"] = $attr['height'];
						break;
					case 'styles':
						$etempl->style = $node['value'];
					case 'textbox':
						if ($attr['multiline'])
						{
							$attr['type'] = 'textarea';
							$attr['size'] = $attr['cols'] . ($attr['rows'] ? ','.$attr['rows'] : '');
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
						if ($tag == 'label')
						{
							$attr['label'] = $attr['value']; unset($attr['value']);
						}
						$attr['help'] = $attr['statustext']; unset($attr['statustext']);
						$spanned = $attr['span'] == 'all' ? $etempl->cols - $col : $attr['span'];
						$attr['span'] .= $attr['class'] ? ','.$attr['class'] : ''; unset($attr['class']);
						if ($type == 'close')
						{
							break;
						}
						$etempl->data[$etempl->rows][$etempl->num2chrs($col++)] = $attr;

						while (--$spanned > 0)
						{
							$etempl->data[$etempl->rows][$etempl->num2chrs($col++)] = $etempl->empty_cell();
						}
						break;
				}
			}
			if ($this->debug)
			{
				_debug_array($etempl->data);
			}
			return '';
		}
	}

?>