<?php
	/**************************************************************************\
	* phpGroupWare - xmltools                                                  *
	* http://www.phpgroupware.org                                              *
	* Written by Seek3r                                                        *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	function xml_get_children($vals, &$i)
	{
		$children = array();
		if (isset($vals[$i]['value']))
		{
			$children[] = $vals[$i]['value'];
		}

		while (++$i < count($vals))
		{
			switch ($vals[$i]['type'])
			{
				case 'cdata':
					$children[] = $vals[$i]['value'];
					break;
				case 'complete':
					$children[] = array(
						'tag'        => $vals[$i]['tag'],
						'attributes' => isset($vals[$i]['attributes']) ? $vals[$i]['attributes'] : null,
						'value'      => $vals[$i]['value'],
					);
					break;
				case 'open':
					$children[] = array(
						'tag'        => $vals[$i]['tag'],
						'attributes' => isset($vals[$i]['attributes']) ? $vals[$i]['attributes'] : null,
						'children'   => xml_get_children($vals, $i),
					);
					break;
				case 'close':
					return $children;
			}
		}
	}

	function xml_get_tree($data) 
	{
		$parser = xml_parser_create();
		xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
		xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE,   1);
		xml_parse_into_struct($parser, $data, $vals, $index);
		xml_parser_free($parser);

		return array(
			'tag'        => $vals[0]['tag'],
			'attributes' => isset($vals[0]['attributes']) ? $vals[0]['attributes'] : null,
			'children'   => xml_get_children($vals, $i = 0),
		);
	}

	function xml2array ($xmldata,$is_start = True)
	{
		if($is_start)
		{
			$xmldata = xml_get_tree($xmldata);
		}
		
		if(!is_array($xmldata['children']))
		{
			$found_at = strstr($xmldata['value'],'PHP_SERIALIZED_OBJECT:');
			if($found_at != False)
			{
				$xmldata['value'] = str_replace ('PHP_SERIALIZED_OBJECT:', '', $xmldata['value']);
				$xmldata['value'] = unserialize ($xmldata['value']);
			}
			if($is_start)
			{
				$xml_array[$xmldata['tag']] = $xmldata['value'];
			}
			else
			{
				return $xmldata['value'];
			}
		}
		else
		{
			$new_index = False;
			reset($xmldata['children']);
			while(list($key,$val) = each($xmldata['children']))
			{
				if(!isset($found_keys[$val['tag']]))
				{
					$found_keys[$val['tag']] = True;
				}
				else
				{
					$new_index = True;
				}
			}

			if($new_index)
			{
				reset($xmldata['children']);
				while(list($key,$val) = each($xmldata['children']))
				{
					$xml_array[$val['tag']][] = xml2array($val,False);
				}				
			}
			else
			{
				reset($xmldata['children']);
				while(list($key,$val) = each($xmldata['children']))
				{
					$xml_array[$val['tag']] = xml2array($val,False);
				}
			}
		}
		return $xml_array;
	}

	function var2xml ($name, $value,$is_root=True)
	{
		$node = new xmlnode($name);
		switch (gettype($value))
		{
			case 'string':
			case 'integer':
			case 'double':
			case 'NULL':
				$node->set_text($value);
				break;
			case 'boolean':
				if($value == True)
				{
					$node->set_text('1');
				}
				else
				{
					$node->set_text('0');
				}
				break;
			case 'array':
				$new_index = False;
				while (list ($idxkey, $idxval) = each ($value))
				{
					if(is_array($idxval))
					{
						while (list ($k, $i) = each ($idxval))
						{
							if (is_int($k))
							{
								$new_index = True;
							}
						}
					}
				}
				reset($value);	
				while (list ($key, $val) = each ($value))
				{
					if($new_index)
					{
						$keyname = $name;
						$nextkey = $key;
					}
					else
					{
						$keyname = $key;
						$nextkey = $key;
					}
					switch (gettype($val))
					{
						case 'string':
						case 'integer':
						case 'double':
						case 'NULL':
							$subnode = new xmlnode($nextkey);
							$subnode->set_text($val);
							$node->add_node($subnode);							
							break;
						case 'boolean':
							$subnode = new xmlnode($nextkey);
							if($val == True)
							{
								$subnode->set_text('1');
							}
							else
							{
								$subnode->set_text('0');
							}
							$node->add_node($subnode);							
							break;
						case 'array':
							if($new_index)
							{
								while (list ($subkey, $subval) = each ($val))
								{
									$node->add_node(var2xml ($nextkey, $subval, False));
								}
							}
							else
							{
								$subnode = var2xml ($nextkey, $val, False);
								$node->add_node($subnode);							
							}
							break;
						case 'object':
							$subnode = new xmlnode($nextkey);
							$subnode->set_cdata('PHP_SERIALIZED_OBJECT:'.serialize($val));
							$node->add_node($subnode);							
							break;
						case 'resource':
							echo 'Halt: Cannot package PHP resource pointers into XML<br>';
							exit;
						default:
							echo 'Halt: Invalid or unknown data type<br>';
							exit;
					}
				}
				break;
			case 'object':
				$node->set_cdata('PHP_SERIALIZED_OBJECT:'.serialize($value));
				break;
			case 'resource':
				echo 'Halt: Cannot package PHP resource pointers into XML<br>';
				exit;
			default:
				echo 'Halt: Invalid or unknown data type<br>';
				exit;
		}

		if($is_root)
		{
			$xmldoc = new xmldoc();
			$xmldoc->add_root($node);
			return $xmldoc;
		}
		else
		{
			return $node;
		}
	}

	class xmldoc
	{
		var $xmlversion = '';
		var $root_node;
		var $doctype = Array();
		var $comments = Array();
		
		function xmldoc ($version = '1.0')
		{
			$this->xmlversion = $version;
		}
		
		function add_root ($node_object)
		{
			if(is_object($node_object))
			{
				$this->root_node = $node_object;
			}
			else
			{
				echo 'Not a valid xmlnode object<br>';
				exit;
			}
		}

		function set_doctype ($name, $uri = '')
		{
			$this->doctype[$name] = $uri;
		}

		function add_comment ($comment)
		{
			$this->comments[] = $comment;
		}
	
		function dump_mem()
		{
			$result = '<?xml version="'.$this->xmlversion.'"?>'."\n";
			if(count($this->doctype) == 1)
			{
				list($doctype_name,$doctype_uri) = each($this->doctype);
				$result .= '<!DOCTYPE '.$doctype_name.' SYSTEM "'.$doctype_uri.'">'."\n";
			}
			if(count($this->comments) > 0 )
			{
				reset($this->comments);
				while(list($key,$val) = each ($this->comments))
				{
					$result .= "<!-- $val -->\n";
				}
			}

			if(is_object($this->root_node))
			{
				$indent = 0;
				$result .= $this->root_node->dump_mem($indent);
			}
			return $result;
		}
	}

	class xmlnode
	{
		var $name = '';
		var $data;
		var $data_type;
		var $attributes = Array();
		var $comments = Array();
		var $indentstring = "  ";
		
		function xmlnode ($name)
		{
			$this->name = $name;
		}

		function add_node ($node_object , $name = '')
		{
			if (is_object($node_object))
			{
				if(!is_array($this->data))
				{
					$this->data = Array();
					$this->data_type = 'node';
				}
				if ($name != '')
				{
					$this->data[$name] = $node_object;
				}
				else
				{
					$this->data[] = $node_object;
				}
			}
			else
			{
				if(!is_array($this->data))
				{
					$this->data = Array();
					$this->data_type = 'node';
				}
				$this->data[$name] = var2xml ($name, $node_object, False);
				//echo 'Not a valid xmlnode object<br>';
				//exit;
			}
		}

		function set_text ($string)
		{
			$this->data = $string;
			$this->data_type = 'text';
		}
		
		function set_cdata ($string)
		{
			$this->data = $string;
			$this->data_type = 'cdata';
		}

		function set_attribute ($name, $value = '')
		{
			$this->attributes[$name] = $value;
		}

		function add_comment ($comment)
		{
			$this->comments[] = $comment;
		}
	
		function dump_mem($indent = 1)
		{
			for ($i = 0; $i < $indent; $i++)
			{
				$indentstring .= $this->indentstring;
			}

			$result = $indentstring.'<'.$this->name;
			if(count($this->attributes) > 0 )
			{
				reset($this->attributes);
				while(list($key,$val) = each ($this->attributes))
				{
					$result .= ' '.$key.'="'.$val.'"';
				}
			}

			$endtag_indent = $indentstring;
			if (empty($this->data_type))
			{
				$result .= '/>'."\n";
			}
			else
			{
				$result .= '>';

				switch ($this->data_type)
				{
					case 'text':
						if(is_array($this->data))
						{
							$type_error = True;
							break;
						}

						if(strlen($this->data) > 30)
						{
							$result .= "\n".$indentstring.$this->indentstring.$this->data."\n";
							$endtag_indent = $indentstring;
						}
						else
						{
							$result .= $this->data;
							$endtag_indent = '';
						}
						break;
					case 'cdata':
						if(is_array($this->data))
						{
							$type_error = True;
							break;
						}
						$result .= '<![CDATA['.$this->data.']]>';
						$endtag_indent = '';
						break;
					case 'node':
						$result .= "\n";
						if(!is_array($this->data))
						{
							$type_error = True;
							break;
						}
					
						$subindent = $indent+1;
						while(list($key,$val) = each ($this->data))
						{
							if(is_object($val))
							{
								$result .= $val->dump_mem($subindent);
							}
						}
						break;
					default:
					if($this->data != '')
					{
						echo 'Invalid or unset data type ('.$this->data_type.'). This should not be possible if using the class as intended<br>';
					}
				}

				if ($type_error)
				{
					echo 'Invalid data type. Tagged as '.$this->data_type.' but data is '.gettype($this->data).'<br>';
				}

				$result .= $endtag_indent.'</'.$this->name.'>';
				if($indent != 0)
				{
					$result .= "\n";
				}
			}
			if(count($this->comments) > 0 )
			{
				reset($this->comments);
				while(list($key,$val) = each ($this->comments))
				{
					$result .= $endtag_indent."<!-- $val -->\n";
				}
			}
			return $result;
		}
	}

