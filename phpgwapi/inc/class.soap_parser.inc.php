<?php
  /* $Id$ */

	class soap_parser
	{
		function soap_parser($xml='',$encoding='UTF-8')
		{
			$this->soapTypes = $GLOBALS['soapTypes'];
			$this->xml = $xml;
			$this->xml_encoding = $encoding;
			$this->root_struct = "";
			// options: envelope,header,body,method
			// determines where in the message we are (envelope,header,body,method)
			$this->status = '';
			$this->position = 0;
			$this->pos_stat = 0;
			$this->depth = 0;
			$this->default_namespace = '';
			$this->namespaces = array();
			$this->message = array();
			$this->fault = false;
			$this->fault_code = '';
			$this->fault_str = '';
			$this->fault_detail = '';
			$this->eval_str = '';
			$this->depth_array = array();
			$this->debug_flag = True;
			$this->debug_str = '';
			$this->previous_element = '';

			$this->entities = array (
				'&' => '&amp;',
				'<' => '&lt;',
				'>' => '&gt;',
				"'" => '&apos;',
				'"' => '&quot;'
			);

			// Check whether content has been read.
			if(!empty($xml))
			{
				$this->debug('Entering soap_parser()');
				//$this->debug("DATA DUMP:\n\n$xml");
				// Create an XML parser.
				$this->parser = xml_parser_create($this->xml_encoding);
				// Set the options for parsing the XML data.
				//xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1); 
				xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, 0);
				// Set the object for the parser.
				xml_set_object($this->parser, &$this);
				// Set the element handlers for the parser.
				xml_set_element_handler($this->parser, 'start_element','end_element');
				xml_set_character_data_handler($this->parser,'character_data');
				xml_set_default_handler($this->parser, 'default_handler');

				// Parse the XML file.
				if(!xml_parse($this->parser,$xml,true))
				{
					// Display an error message.
					$this->debug(sprintf("XML error on line %d: %s",
						xml_get_current_line_number($this->parser),
						xml_error_string(xml_get_error_code($this->parser))));
					$this->fault = true;
				}
				else
				{
					// get final eval string
					$this->eval_str = "\$response = ".trim($this->build_eval($this->root_struct)).";";
				}
				xml_parser_free($this->parser);
			}
			else
			{
				$this->debug("xml was empty, didn't parse!");
			}
		}

		// loop through msg, building eval_str
		function build_eval($pos)
		{
			$this->debug("inside build_eval() for $pos: ".$this->message[$pos]["name"]);
			$eval_str = $this->message[$pos]['eval_str'];
			// loop through children, building...
			if($this->message[$pos]['children'] != '')
			{
				$this->debug('children string = '.$this->message[$pos]['children']);
				$children = explode('|',$this->message[$pos]['children']);
				$this->debug('it has '.count($children).' children');
				@reset($children);
				while(list($c,$child_pos) = @each($children))
				/* foreach($children as $c => $child_pos) */
				{
					//$this->debug("child pos $child_pos: ".$this->message[$child_pos]["name"]);
					if($this->message[$child_pos]['eval_str'] != '')
					{
						$this->debug('entering build_eval() for '.$this->message[$child_pos]['name'].", array pos $c, pos: $child_pos");
						$eval_str .= $this->build_eval($child_pos).', ';
					}
				}
				$eval_str = substr($eval_str,0,strlen($eval_str)-2);
			}
			// add current node's eval_str
			$eval_str .= $this->message[$pos]['end_eval_str'];
			return $eval_str;
		}

		// start-element handler
		function start_element($parser, $name, $attrs)
		{
			// position in a total number of elements, starting from 0
			// update class level pos
			$pos = $this->position++;
			// and set mine
			$this->message[$pos]['pos'] = $pos;

			// parent/child/depth determinations

			// depth = how many levels removed from root?
			// set mine as current global depth and increment global depth value
			$this->message[$pos]['depth'] = $this->depth++;

			// else add self as child to whoever the current parent is
			if($pos != 0)
			{
				$this->message[$this->parent]['children'] .= "|$pos";
			}
			// set my parent
			$this->message[$pos]['parent'] = $this->parent;
			// set self as current value for this depth
			$this->depth_array[$this->depth] = $pos;
			// set self as current parent
			$this->parent = $pos;

			// set status
			if(ereg(":Envelope$",$name))
			{
				$this->status = 'envelope';
			}
			elseif(ereg(":Header$",$name))
			{
				$this->status = 'header';
			}
			elseif(ereg(":Body$",$name))
			{
				$this->status = 'body';
			// set method
			}
			elseif($this->status == 'body')
			{
				$this->status = 'method';
				if(ereg(':',$name))
				{
					$this->root_struct_name = substr(strrchr($name,':'),1);
				}
				else
				{
					$this->root_struct_name = $name;
				}
				$this->root_struct = $pos;
				$this->message[$pos]['type'] = 'struct';
			}
			// set my status
			$this->message[$pos]['status'] = $this->status;

			// set name
			$this->message[$pos]['name'] = htmlspecialchars($name);
			// set attrs
			$this->message[$pos]['attrs'] = $attrs;
			// get namespace
			if(ereg(":",$name))
			{
				$namespace = substr($name,0,strpos($name,':'));
				$this->message[$pos]['namespace'] = $namespace;
				$this->default_namespace = $namespace;
			}
			else
			{
				$this->message[$pos]['namespace'] = $this->default_namespace;
			}
			// loop through atts, logging ns and type declarations
			@reset($attrs);
			while (list($key,$value) = @each($attrs))
			/* foreach($attrs as $key => $value) */
			{
				// if ns declarations, add to class level array of valid namespaces
				if(ereg('xmlns:',$key))
				{
					$namespaces[substr(strrchr($key,':'),1)] = $value;
					if($name == $this->root_struct_name)
					{
						$this->methodNamespace = $value;
					}
				}
				// if it's a type declaration, set type
				elseif($key == 'xsi:type')
				{
					// then get attname and set $type
					$type = substr(strrchr($value,':'),1);
				}
			}

			// set type if available
			if($type)
			{
				$this->message[$pos]['type'] = $type;
			}

			// debug
			//$this->debug("parsed $name start, eval = '".$this->message[$pos]["eval_str"]."'");
		}

		// end-element handler
		function end_element($parser, $name)
		{
			// position of current element is equal to the last value left in depth_array for my depth
			$pos = $this->depth_array[$this->depth];
			// bring depth down a notch
			$this->depth--;
		
			// get type if not set already
			if($this->message[$pos]['type'] == '')
			{
//				if($this->message[$pos]['cdata'] == '' && $this->message[$pos]['children'] != '')
				if($this->message[$pos]['children'] != '')
				{
					$this->message[$pos]['type'] = 'SOAPStruct';
				}
				else
				{
					$this->message[$pos]['type'] = 'string';
				}
			}

			// set eval str start if it has a valid type and is inside the method
			if($pos >= $this->root_struct)
			{
				$this->message[$pos]['eval_str'] .= "\n CreateObject(\"phpgwapi.soapval\",\"".htmlspecialchars($name)."\", \"".$this->message[$pos]["type"]."\" ";
				$this->message[$pos]['end_eval_str'] = ')';
				$this->message[$pos]['inval'] = 'true';
				/*
				if($this->message[$pos]["name"] == $this->root_struct_name){
					$this->message[$pos]["end_eval_str"] .= " ,\"$this->methodNamespace\"";
				}
				*/
				if($this->message[$pos]['children'] != '')
				{
					$this->message[$pos]['eval_str'] .= ', array( ';
					$this->message[$pos]['end_eval_str'] .= ' )';
				}
			}

			// if i have no children and have cdata...then i must be a scalar value, so add my data to the eval_str
			if($this->status == 'method' && $this->message[$pos]['children'] == '')
			{
				// add cdata w/ no quotes if only int/float/dbl
				if($this->message[$pos]['type'] == 'string')
				{
					$this->message[$pos]['eval_str'] .= ", \"".$this->message[$pos]['cdata']."\"";
				}
				elseif($this->message[$pos]['type'] == 'int' || $this->message[$pos]['type'] == 'float' || $this->message[$pos]['type'] == 'double')
				{
					//$this->debug("adding cdata w/o quotes");
					$this->message[$pos]['eval_str'] .= ', '.trim($this->message[$pos]['cdata']);
				}
				elseif(is_string($this->message[$pos]['cdata']))
				{
					//$this->debug("adding cdata w/ quotes");
					$this->message[$pos]['eval_str'] .= ", \"".$this->message[$pos]['cdata']."\"";
				}
			}
			// if in the process of making a soap_val, close the parentheses and move on...
			if($this->message[$pos]['inval'] == 'true')
			{
				$this->message[$pos]['inval'] == 'false';
			}
			// if tag we are currently closing is the method wrapper
			if($pos == $this->root_struct)
			{
				$this->status = 'body';
			}
			elseif(ereg(':Body',$name))
			{
				$this->status = 'header';
	 		}
			elseif(ereg(':Header',$name))
			{
				$this->status = 'envelope';
			}
			// set parent back to my parent
			$this->parent = $this->message[$pos]['parent'];
			$this->debug("parsed $name end, type '".$this->message[$pos]['type']."'eval_str = '".trim($this->message[$pos]['eval_str'])."' and children = ".$this->message[$pos]['children']);
		}

		// element content handler
		function character_data($parser, $data)
		{
			$pos = $this->depth_array[$this->depth];
			$this->message[$pos]['cdata'] .= $data;
			//$this->debug("parsed ".$this->message[$pos]["name"]." cdata, eval = '$this->eval_str'");
		}

		// default handler
		function default_handler($parser, $data)
		{
			//$this->debug("DEFAULT HANDLER: $data");
		}

		// function to get fault code
		function fault()
		{
			if($this->fault)
			{
				return true;
			}
			else
			{
				return false;
			}
		}

		// have this return a soap_val object
		function get_response()
		{
			$this->debug("eval()ing eval_str: $this->eval_str");
			@eval("$this->eval_str");
			if($response)
			{
				$this->debug("successfully eval'd msg");
				return $response;
			}
			else
			{
				$this->debug('ERROR: did not successfully eval the msg');
				$this->fault = true;
				return CreateObject('phpgwapi.soapval',
					'Fault',
					'struct',
					array(
						CreateObject('phpgwapi.soapval',
							'faultcode',
							'string',
							'SOAP-ENV:Server'
						),
						CreateObject('phpgwapi.soapval',
							'faultstring',
							'string',
							"couldn't eval \"$this->eval_str\""
						)
					)
				);
			}
		}

		function debug($string)
		{
			if($this->debug_flag)
			{
				$this->debug_str .= "$string\n";
			}
		}

		function decode_entities($text)
		{
			@reset($this->entities);
			while(list($entity,$encoded) = @each($this->entities))
			/* foreach($this->entities as $entity => $encoded) */
			{
				$text = str_replace($encoded,$entity,$text);
			}
			return $text;
		}
	}
?>
