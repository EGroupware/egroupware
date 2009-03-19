<?php
	/**
	 * eGroupWare - eTemplates - XUL/XML Import & Export
	 *
	 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	 * @package etemplate
	 * @link http://www.egroupware.org
	 * @author Ralf Becker <RalfBecker@outdoor-training.de>
	 * @version $Id$
	 */

	if (!function_exists('var2xml'))
	{
		if (file_exists(EGW_API_INC.'class.xmltool.inc.php'))
		{
			include_once(EGW_API_INC.'class.xmltool.inc.php');
		}
		else
		{
			include_once('class.xmltool.inc.php');
		}
	}

	/**
	 * XUL/XML Import & Export for eTemplates
	 *
	 * used only internaly
	 *
	 * @package etemplate
	 * @subpackage api
	 * @author RalfBecker-AT-outdoor-training.de
	 * @license GPL
	 */
	class xul_io
	{
		/**
		 * translate attr, common to all widgets
		 *
		 * @var array
		 */
		var $attr2xul = array(
			'name' => 'id',
			'help' => 'statustext',
			'span' => 'span,class',
			'type' => '',	// this is the widget-name => dont write as attr
			'disabled' => 'disabled=true',
			'readonly' => 'readonly=true',
			'size' => 'options'
		);
		/**
		 * translate widget-names and widget-spec. attr., not set ones are identical
		 *
		 * @var array
		 */
		var $widget2xul = array(
			'label' => array(
				'.name' => 'description',
				'label' => 'value'
			),
			'text' => array(
				'.name' => 'textbox',
				'size' => 'size,maxlength,validator'
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
				'size' => 'min,max,size,precision'
			),
			'select' => array(
				'.name' => 'menulist,menupopup',
			),
			'select-multi' => array(	// multiselection, if size > 0
				'.name' => 'listbox',
				'size'  => 'rows,options'
			),
			'template' => array(
				'.name' => 'template',
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
			),
			'htmlarea' => array(
				'size' => 'mode,height,width,toolbar,base_href',
			),
		);
		/**
		 * translate xul-widget names to our internal ones, not set ones are identical
		 *
		 * @var array
		 */
		var $xul2widget = array(
			'menulist' => 'select',
			'listbox' => 'select',
			'menupopup' => 'select',
			'description' => 'label'
		);

		/**
		 * sets an attribute in the xml object representing a widget
		 *
		 * @param object &$widget widget to set the attribute in
		 * @param string $attr comma delimited attr = default-value pairs, eg. "type=int,min=0"
		 * @param array $val array with values to set
		 */
		function set_attributes(&$widget,$attr,$val)
		{
			if ($attr != '' && !is_numeric($attr))
			{
				$attrs = explode(',',$attr);

				if (count($attrs))
				{
					$vals = count($attrs) > 1 ? explode(',',$val,count($attrs)) : array($val);
					foreach($attrs as $n => $attr)
					{
						if (($val = $vals[$n]) != '')
						{
							list($attr,$set) = explode('=',$attr);
							$widget->set_attribute($attr,$set != '' ? $set : $val);
						}
					}
				}
			}
		}

		/**
		 * add a widget to a parent
		 *
		 * @param object &$parent parten to add the widget
		 * @param array $cell widget to add
		 * @param array &$embeded_too already embeded eTemplates
		 * @return object reference (!) the the xml object representing the widget, so other children can be added
		 */
		function &add_widget(&$parent,$cell,&$embeded_too)
		{
			$type = $cell['type'];
			if (is_array($type))
			{
				list(,$type) = each($type);
			}
			if (!$type) $cell['type'] = $type = 'hugo';
			if (substr($type,0,6) == 'select')
			{
				$type = $cell['size'] > 1 ? 'select-multi' : 'select';
			}
			$widgetattr2xul = isset($this->widget2xul[$type]) ? $this->widget2xul[$type] : array();
			$type = isset($widgetattr2xul['.name']) ? $widgetattr2xul['.name'] : $type;
			list($type,$child,$child2) = explode(',',$type);
			$widget =& new xmlnode($type);
			$attr_widget = &$widget;
			if ($child)
			{
				$child =& new xmlnode($child);
				if ($type != 'tabbox') $attr_widget = &$child;
			}
			if ($child2)
			{
				$child2 =& new xmlnode($child2);
			}
			if (isset($widgetattr2xul['.set']))	// set default-attr for type
			{
				$attrs = explode(',',$widgetattr2xul['.set']);
				foreach($attrs as $attr)
				{
					list($attr,$val) = explode('=',$attr);
					$widget->set_attribute($attr,$val);
				}
			}
			switch ($type)
			{
			case 'nextmatch':
				list($tpl) = explode(',',$cell['size']);
				$embeded =& new etemplate($tpl,$this->load_via);
				if ($embeded_too)
				{
					$this->add_etempl($embeded,$embeded_too);
				}
				$cell['size'] = $embeded->name;
				unset($embeded);
				break;
			case 'tabbox':
				$labels = explode('|',$cell['label']);  unset($cell['label']);
				$helps  = explode('|',$cell['help']);   unset($cell['help']);
				if (strpos($tab_names=$cell['name'],'=') !== false)
				{
					list($cell['name'],$tab_names) = explode('=',$cell['name']);
				}
				$names  = explode('|',$tab_names);
				for ($n = 0; $n < count($labels); ++$n)
				{
					$tab =& new xmlnode('tab');
					$tab->set_attribute('label',$labels[$n]);
					$tab->set_attribute('statustext',$helps[$n]);
					$child->add_node($tab);

					$embeded =& new etemplate($names[$n],$this->load_via);
					if ($embeded_too)
					{
						$this->add_etempl($embeded,$embeded_too);
					}
					$template =& new xmlnode('template');
					$template->set_attribute('id',$embeded->name);
					$child2->add_node($template);
					unset($embeded);
					unset($template);
				}
				break;
			case 'menulist':	// id,options belongs to the 'menupopup' child
				if ($cell['span'])
				{
					$this->set_attributes($widget,'span,class',$cell['span']);
					unset($cell['span']);
				}
				// fall-trought
			case 'listbox':
				if ($cell['type'] != 'select')	// one of the sub-types
				{
					$attr_widget->set_attribute('type',$cell['type']);
				}
				break;
			case 'groupbox':
				if ($cell['label'])
				{
					$caption =& new xmlnode('caption');
					$caption->set_attribute('label',$cell['label']);
					$widget->add_node($caption);
					unset($cell['label']);
				}
				// fall-through
			case 'vbox':
			case 'hbox':
			case 'box':
			case 'deck':
				list($anz,$orient,$options) = split(',',$cell['size'],3);
				for ($n = 1; $n <= $anz; ++$n)
				{
					$this->add_widget($widget,$cell[$n],$embeded_too);
					unset($cell[$n]);
				}
				// no sure where the data key gets set, but it gives a warning in xml serialization (empty array)
				unset($cell['data']);
				$cell['orient'] = $orient;
				$cell['size'] = $options;
				break;

			case 'template':
				if ($cell['name'][0] != '@' && $embeded_too)
				{
					$templ =& new etemplate();
					if ($templ->read(boetemplate::expand_name($cell['name'],0,0),'default','default',0,'',$this->load_via))
					{
						$this->add_etempl($templ,$embeded_too);
					}
					unset($templ);
				}
				break;

			case 'grid':
				$this->add_grid($parent,$cell,$embeded_too);
				return;	// grid is already added
			}
			foreach($cell as $attr => $val)
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
				$this->set_attributes($attr_widget,$attr,$val);
			}
			if ($child)
			{
				$widget->add_node($child);
			}
			if ($child2)
			{
				$widget->add_node($child2);
			}
			$parent->add_node($widget);
		}

		/**
		 * add a grid to $parent (xml object)
		 *
		 * @param object &$parent where to add the grid
		 * @param array $grid grid to add
		 * @param array &embeded_too array with already embeded eTemplates
		 */
		function add_grid(&$parent,$grid,&$embeded_too)
		{
			$xul_grid =& new xmlnode('grid');
			$this->set_attributes($xul_grid,'width,height,border,class,spacing,padding,overflow',$grid['size']);
			$this->set_attributes($xul_grid,'id',$grid['name']);

			$xul_columns =& new xmlnode('columns');
			$xul_rows =& new xmlnode('rows');

			reset($grid['data']);
			list(,$opts) = each ($grid['data']); // read over options-row
			while (list($r,$row) = each ($grid['data']))
			{
				$xul_row =& new xmlnode('row');
				$this->set_attributes($xul_row,'class,valign',$opts["c$r"]);
				$this->set_attributes($xul_row,'height,disabled,part',$opts["h$r"]);

				$spanned = 0;
				foreach($row as $c => $cell)
				{
					if ($r == '1')	// write columns only once in the first row
					{
						$xul_column =& new xmlnode('column');
						$this->set_attributes($xul_column,'width,disabled',$opts[$c]);
						$xul_columns->add_node($xul_column);
					}
					if ($spanned-- > 1)
					{
						continue;	// spanned cells are not written
					}
					$this->add_widget($xul_row,$cell,$embeded_too);

					$spanned = $cell['span'] == 'all' ? 999 : $cell['span'];
				}
				$xul_rows->add_node($xul_row);
			}
			$xul_grid->add_node($xul_columns);
			$xul_grid->add_node($xul_rows);

			$parent->add_node($xul_grid);
		}

		/**
		 * add / embed an eTemplate into the global $xul_overlay object (used by export)
		 *
		 * @param object &$etempl eTemplate to embed
		 * @param array &embeded_too array with already embeded templates
		 */
		function add_etempl(&$etempl,&$embeded_too)
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

			$template =& new xmlnode('template');
			$template->set_attribute('id',$etempl->name);
			$template->set_attribute('template',$etempl->template);
			$template->set_attribute('lang',$etempl->lang);
			$template->set_attribute('group',$etempl->group);
			$template->set_attribute('version',$etempl->version);

			foreach($etempl->children as $child)
			{
				$this->add_widget($template,$child,$embeded_too);
			}
			if ($etempl->style != '')
			{
				$styles =& new xmlnode('styles');
				$styles->set_value(str_replace("\r",'',$etempl->style));
				$template->add_node($styles);
			}
			$this->xul_overlay->add_node($template);
		}

		/**
		 * create an XML representation of an eTemplate
		 *
		 * @param object $etempl eTemplate object to export
		 * @return string the XML
		 */
		function export($etempl)
		{
			if ($this->debug)
			{
				echo "<p>etempl->data = "; _debug_array($etempl->data);
			}
			$doc =& new xmldoc();
			$doc->add_comment('$'.'Id$');

			$this->xul_overlay =& new xmlnode('overlay');	// global for all add_etempl calls
			$this->load_via = $etempl->as_array();

			$embeded_too = True;
			$this->add_etempl($etempl,$embeded_too);

			$doc->add_root($this->xul_overlay);
			$xml = $doc->export_xml();

			if ($this->debug)
			{
				echo "<pre>\n" . htmlentities($xml) . "\n</pre>\n";
			}
			return $xml;
		}

		/**
		 * create an eTemplate from it's XML representation
		 *
		 * @param object &$etempl eTemplate object to set
		 * @param string $data the XML
		 * @return array/string array with names of imported templates or error-message
		 */
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
			$parents = array();
			$parent = null;
			foreach($vals as $n => $node)
			{
				if ($this->debug)
				{
					echo "<h1>$n</h1><pre>".print_r($node,true)."</pre>";
				}
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
					case 'template':
					case 'grid':
						if ($type != 'open' && is_array($tab_attr))	// templates/grids in a tabpanel
						{
							$tab_names[] = $attr['name'];
							break;
						}
						if ($tag == 'template' && $type != 'complete' && $node['level'] > 2)	// level 1 is the overlay
						{
							return "Can't import nested $tag's !!!";
						}
						switch ($type)
						{
							case 'close':
								if (!count($parents))	// templ import complet => save it
								{
									unset($parent); $parents = array();
									$etempl->fix_old_template_format(); 	// set the depricated compat vars
									// save tmpl to the cache, as the file may contain more then one tmpl
									$cname = ($etempl->template == '' ? 'default' : $etempl->template).'/'.$etempl->name.
													 ($etempl->lang == '' ? '' : '.'.$etempl->lang);
									boetemplate::store_in_cache($etempl);
									if ($this->debug)
									{
										$etempl->echo_tmpl();
									}
									$imported[] = $etempl->name;
								}
								else
								{
									// poping the last used parent from the end of the parents array (array_pop does not work with references)
									$parent = &$parents[count($parents)-1];
									unset($parents[count($parents)-1]);
								}
								break;
							case 'open':
								if (($is_root = is_null($parent)))	// starting a new templ
								{
									$etempl->init($attr);
									$etempl->children = array();	// init adds one grid by default
									$parent = &$etempl;				// parent is the template-object itself!
								}
								if ($tag == 'grid')
								{
									$size = '';
									foreach(array('overflow','padding','spacing','class','border','height','width') as $opt)
									{
										$size = $attr[$opt] . ($size != '' ? ",$size" : '');
									}
									$grid = array(	// empty grid
										'type' => 'grid',
										'data' => array(),
										'cols' => 0,
										'rows' => 0,
										'size' => $size,
									);
									soetemplate::add_child($parent,$grid);
									$parents[count($parents)] = &$parent;
									$parent = &$grid;
									unset($grid);
								}
								break;
							case 'complete':	// reference to an other template
								$attr['type'] = 'template';	// might be grid in old xet-files
								soetemplate::add_child($parent,$attr);
								unset($attr);
								break;
						}
						break;
					case 'columns':
					case 'rows':
						break;
					case 'column':
						if ($type != 'complete')
						{
							return 'place widgets in <row> and not in <column> !!!';
						}
						$parent['data'][0][$etempl->num2chrs($parent['cols']++)] = $attr['width'] .
							($attr['disabled'] ? ','.$attr['disabled'] : '');
						break;
					case 'row':
						if ($type != 'open')
						{
							break;
						}
						$nul = null; soetemplate::add_child($parent,$nul);	// null =& new row
						$parent['data'][0]['c'.$parent['rows']] = $attr['class'] . ($attr['valign'] ? ','.$attr['valign'] : '');
						$parent['data'][0]['h'.$parent['rows']] = $attr['height'] .
							($attr['disabled']||$attr['part'] ? ','.$attr['disabled'] : '').
							($attr['part'] ? ','.$attr['part'] : '');
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

							soetemplate::add_child($parent,$tab_attr);
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
							soetemplate::add_child($parent,$menulist_attr);
							unset($menulist_attr);
						}
						break;
					case 'vbox':
					case 'hbox':
					case 'deck':
					case 'groupbox':
					case 'box':
						if ($type != 'close')	// open or complete
						{
							$attr['size'] = '0'.($attr['orient'] || $attr['size'] ? ','.$attr['orient'].
								($attr['size'] ? ','.$attr['size'] : '') : '');
							soetemplate::add_child($parent,$attr);
							$parents[count($parents)] = &$parent;	// $parents[] does not always the same - strange
							$parent = &$attr;
							unset($attr);
						}
						if ($type != 'open')	// close or complete
						{
							// poping the last used parent from the end of the parents array (array_pop does not work with references)
							$parent = &$parents[count($parents)-1];
							unset($parents[count($parents)-1]);
						}
						break;
					case 'caption':	// caption of (group)box
						if ($parent['type'] == 'groupbox')
						{
							$parent['label'] = $attr['label'];
						}
						break;
					// the following labels create automaticaly a child-entry in their parent
					case 'textbox':
						if ($attr['multiline'])
						{
							unset($attr['multiline']);
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
						soetemplate::add_child($parent,$attr);
						unset($attr);
						break;
				}
				if ($this->debug)
				{
					echo "<b>parent</b><pre>".print_r($parent,true)."</pre>";
					echo "<b>parents</b><pre>".print_r($parents,true)."</pre>";
					echo "<b>children</b><pre>".print_r($etempl->children,true)."</pre>";
				}
			}
			return $imported;
		}
	}
