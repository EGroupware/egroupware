<?php
  /* $Id$ */

	// soap message class
	class soapmsg
	{
		// params is an array of soapval objects
		function soapmsg($method,$params,$method_namespace='http://testuri.org',$new_namespaces=False)
		{
			// globalize method namespace
			global $methodNamespace;
			$methodNamespace = $method_namespace;
			// make method struct
			$this->value = CreateObject('phpgwapi.soapval',$method,"struct",$params,$method_namespace);
			if(is_array($new_namespaces))
			{
				global $namespaces;
				$i = count($namespaces);
				@reset($new_namespaces);
				while(list($null,$v) = @each($new_namespaces))
				/* foreach($new_namespaces as $v) */
				{
					$namespaces[$v] = 'ns' . $i++;
				}
				$this->namespaces = $namespaces;
			}
			$this->payload = '';
			$this->debug_flag = True;
			$this->debug_str = "entering soapmsg() with soapval ".$this->value->name."\n";
		}

		function make_envelope($payload)
		{
			global $namespaces;
			@reset($namespaces);
			while(list($k,$v) = @each($namespaces))
			/* foreach($namespaces as $k => $v) */
			{
				$ns_string .= " xmlns:$v=\"$k\"";
			}
			return "<SOAP-ENV:Envelope $ns_string SOAP-ENV:encodingStyle=\"http://schemas.xmlsoap.org/soap/encoding/\">\n"
				. $payload . "</SOAP-ENV:Envelope>\n";
		}

		function make_body($payload)
		{
			return "<SOAP-ENV:Body>\n" . $payload . "</SOAP-ENV:Body>\n";
		}

		function createPayload()
		{
			$value = $this->value;
			$payload = $this->make_envelope($this->make_body($value->serialize()));
			$this->debug($value->debug_str);
			$payload = "<?xml version=\"1.0\"?>\n".$payload;
			if($this->debug_flag)
			{
				$payload .= $this->serializeDebug();
			}
			$this->payload = str_replace("\n","\r\n", $payload);
		}

		function serialize()
		{
			if($this->payload == '')
			{
				$this->createPayload();
				return $this->payload;
			}
			else
			{
				return $this->payload;
			}
		}

		// returns a soapval object
		function parseResponse($data)
		{
			$this->debug("Entering parseResponse()");
			//$this->debug(" w/ data $data");
			// strip headers here
			//$clean_data = ereg_replace("\r\n","\n", $data);
			if(ereg("^.*\r\n\r\n<",$data))
			{
				$this->debug("found proper seperation of headers and document");
				$this->debug("getting rid of headers, stringlen: ".strlen($data));
				$clean_data = ereg_replace("^.*\r\n\r\n<","<", $data);
				$this->debug("cleaned data, stringlen: ".strlen($clean_data));
			}
			else
			{
				// return fault
				return CreateObject('phpgwapi.soapval',
					'fault',
					'SOAPStruct',
					Array(
						CreateObject('phpgwapi.soapval','faultcode','string','SOAP-MSG'),
						CreateObject('phpgwapi.soapval','faultstring','string','HTTP Error'),
						CreateObject('phpgwapi.soapval','faultdetail','string','HTTP headers were not immediately followed by \'\r\n\r\n\'')
					)
				);
			}
	/*
			// if response is a proper http response, and is not a 200
			if(ereg("^HTTP",$clean_data) && !ereg("200$", $clean_data))
			{
				// get error data
				$errstr = substr($clean_data, 0, strpos($clean_data, "\n")-1);
				// return fault
				return CreateObject('phpgwapi.soapval',
					"fault",
					"SOAPStruct",
					array(
						CreateObject('phpgwapi.soapval',"faultcode","string","SOAP-MSG"),
						CreateObject('phpgwapi.soapval',"faultstring","string","HTTP error")
					)
				);
			}
	*/
			$this->debug("about to create parser instance w/ data: $clean_data");
			// parse response
			$response = CreateObject('phpgwapi.soap_parser',$clean_data);
			// return array of parameters
			$ret = $response->get_response();
			$this->debug($response->debug_str);
			return $ret;
	 	}

		// dbg
		function debug($string)
		{
			if($this->debug_flag)
			{
				$this->debug_str .= "$string\n";
			}
		}

		// preps debug data for encoding into soapmsg
		function serializeDebug()
		{
			if($this->debug_flag)
			{
				return "<!-- DEBUG INFO:\n".$this->debug_str."-->\n";
			}
			else
			{
				return '';
			}
		}
	}
?>
