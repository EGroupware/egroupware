<?php
/*

	SOAPx4
	by Dietrich Ayala (C) 2001 dietrich@ganx4.com

	This project began based on code from the 2 projects below,
	and still contains some original code. The licenses of both must be respected.

	XML-RPC for PHP
	originally by Edd Dumbill (C) 1999-2000

	SOAP for PHP
	by Victor Zou (C) 2000-2001 <victor@gigaideas.com.cn>

	this is a class that loads a wsdl file and makes it's data available to an application
	it should provide methods that allow both client and server usage of it

	also should have methods for creating a wsdl file from scratch and
	serializing wsdl into valid markup
*/
	/* $Id$ */

	class wsdl
	{
		// constructor
		function wsdl($wsdl=False)
		{	
			// define internal arrays of bindings, ports, operations, messages, etc.
			$this->complexTypes = array();
			$this->messages = array();
			$this->currentMessage;
			$this->portOperations = array();
			$this->currentOperation;
			$this->portTypes = array();
			$this->currentPortType;
			$this->bindings = array();
			$this->currentBinding;
			// debug switch
			$this->debug_flag = False;
			// parser vars
			$this->parser;
			$this->position;
			$this->depth;
			$this->depth_array = array();

			if($wsdl == "-1")
			{
				$wsdl=False;
			}
			// Check whether content has been read.
			if($wsdl)
			{
				$wsdl_string = join("",file($wsdl));
				// Create an XML parser.
				$this->parser = xml_parser_create();
				// Set the options for parsing the XML data.
				//xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1); 
				xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, 0);
				// Set the object for the parser.
				xml_set_object($this->parser, &$this);
				// Set the element handlers for the parser.
				xml_set_element_handler($this->parser, 'start_element','end_element');
				xml_set_character_data_handler($this->parser,'character_data');
				//xml_set_default_handler($this->parser, 'default_handler');

				// Parse the XML file.
				if(!xml_parse($this->parser,$wsdl_string,True))
				{
					// Display an error message.
					$this->debug(sprintf('XML error on line %d: %s',
						xml_get_current_line_number($this->parser),
						xml_error_string(xml_get_error_code($this->parser))));
					$this->fault = True;
				}
				xml_parser_free($this->parser);
			}
		}

		// start-element handler
		function start_element($parser, $name, $attrs)
		{
			// position in the total number of elements, starting from 0
			$pos = $this->position++;
			$depth = $this->depth++;
			// set self as current value for this depth
			$this->depth_array[$depth] = $pos;

			// find status, register data
			switch($this->status)
			{
				case 'types':
				switch($name)
				{
					case 'schema':
						$this->schema = True;
						break;
					case 'complexType':
						$this->currentElement = $attrs['name'];
						$this->schemaStatus = 'complexType';
						break;
					case 'element':
						$this->complexTypes[$this->currentElement]['elements'][$attrs['name']] = $attrs;
						break;
					case 'complexContent':
						break;
					case 'restriction':
						$this->complexTypes[$this->currentElement]['restrictionBase'] = $attrs['base'];
						break;
					case 'sequence':
						$this->complexTypes[$this->currentElement]['order'] = 'sequence';
						break;
					case "all":
						$this->complexTypes[$this->currentElement]['order'] = 'all';
						break;
					case 'attribute':
						if($attrs['ref'])
						{
							$this->complexTypes[$this->currentElement]['attrs'][$attrs['ref']] = $attrs;
						}
						elseif($attrs['name'])
						{
							$this->complexTypes[$this->currentElement]['attrs'][$attrs['name']] = $attrs;
						}
						break;
					}
					break;
				case 'message':
					if($name == 'part')
					{
						$this->messages[$this->currentMessage][$attrs['name']] = $attrs['type'];
					}
					break;
				case 'portType':
					switch($name)
					{
						case 'operation':
							$this->currentOperation = $attrs['name'];
							$this->portTypes[$this->currentPortType][$attrs['name']] = $attrs['parameterOrder'];
						break;
						default:
							$this->portOperations[$this->currentOperation][$name]= $attrs;
							break;
					}
					break;
				case 'binding':
					switch($name)
					{
						case 'soap:binding':
							$this->bindings[$this->currentBinding] = array_merge($this->bindings[$this->currentBinding],$attrs);
							break;
						case 'operation':
							$this->currentOperation = $attrs['name'];
							$this->bindings[$this->currentBinding]['operations'][$attrs['name']] = array();
							break;
						case 'soap:operation':
							$this->bindings[$this->currentBinding]['operations'][$this->currentOperation]['soapAction'] = $attrs['soapAction'];
							break;
						case 'input':
							$this->opStatus = 'input';
						case 'soap:body':
							$this->bindings[$this->currentBinding]['operations'][$this->currentOperation][$this->opStatus] = $attrs;
							break;
						case 'output':
							$this->opStatus = 'output';
							break;
					}
					break;
				case 'service':
					switch($name)
					{
						case 'port':
							$this->currentPort = $attrs['name'];
							$this->ports[$attrs['name']] = $attrs;
							break;
						case 'soap:address':
							$this->ports[$this->currentPort]['location'] = $attrs['location'];
							break;
					}
					break;
			}
			// set status
			switch($name)
			{
				case 'types':
					$this->status = 'types';
					break;
				case 'message':
					$this->status = 'message';
					$this->messages[$attrs['name']] = array();
					$this->currentMessage = $attrs['name'];
					break;
				case 'portType':
					$this->status = 'portType';
					$this->portTypes[$attrs['name']] = array();
					$this->currentPortType = $attrs['name'];
					break;
				case 'binding':
					$this->status = 'binding';
					$this->currentBinding = $attrs['name'];
					$this->bindings[$attrs['name']]['type'] = $attrs['type'];
					break;
				case 'service':
					$this->status = 'service';
					break;
			}
			// get element prefix
			if(ereg(":",$name))
			{
				$prefix = substr($name,0,strpos($name,':'));
			}
		}

		function getEndpoint($portName)
		{
			if($endpoint = $this->ports[$portName]['location'])
			{
				return $endpoint;
			}
			return False;
		}

		function getPortName($operation)
		{
			@reset($this->ports);
			while(list($port,$portAttrs) = @each($this->ports));
			/* foreach($this->ports as $port => $portAttrs) */
			{
				$binding = substr($portAttrs['binding'],4);
				@reset($this->bindings[$binding]['operations']);
				while(list($op,$opAttrs) = @each($this->bindings[$binding]['operations']))
				/* foreach($this->bindings[$binding]["operations"] as $op => $opAttrs) */
				{
					if($op == $operation)
					{
						return $port;
					}
				}
			}
		}

		function getSoapAction($portName,$operation)
		{
			if($binding = substr($this->ports[$portName]['binding'],4))
			{
				if($soapAction = $this->bindings[$binding]['operations'][$operation]['soapAction'])
				{
					return $soapAction;
				}
				return False;
			}
			return False;
		}

		function getNamespace($portName,$operation)
		{
			if($binding = substr($this->ports[$portName]['binding'],4))
			{
				//$this->debug("looking for namespace using binding '$binding', port '$portName', operation '$operation'");
				if($namespace = $this->bindings[$binding]['operations'][$operation]['input']['namespace'])
				{
					return $namespace;
				}
				return False;
			}
			return False;
		}

		// end-element handler
		function end_element($parser, $name)
		{
			// position of current element is equal to the last value left in depth_array for my depth
			$pos = $this->depth_array[$this->depth];
			// bring depth down a notch
			$this->depth--;	
		}

		// element content handler
		function character_data($parser, $data)
		{
			$pos = $this->depth_array[$this->depth];
			$this->message[$pos]['cdata'] .= $data;
		}

		function debug($string)
		{
			if($this->debug_flag)
			{
				$this->debug_str .= "$string\n";
			}
		}
	}
?>
