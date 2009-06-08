<?php
	// by Edd Dumbill (C) 1999-2001
	// <edd@usefulinc.com>
	// xmlrpc.inc,v 1.18 2001/07/06 18:23:57 edmundd

	// License is granted to use or modify this software ('XML-RPC for PHP')
	// for commercial or non-commercial use provided the copyright of the author
	// is preserved in any distributed or derivative work.

	// THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESSED OR
	// IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
	// OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
	// IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
	// INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
	// NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, 
	// DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
	// THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
	// (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
	// THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

	/* $Id$ */

	class xmlrpcmsg
	{
		var $payload;
		var $methodname;
		var $params = array();
		var $debug  = 0;

		function xmlrpcmsg($meth, $pars=0)
		{
			$this->methodname=$meth;
			if(is_array($pars) && sizeof($pars)>0)
			{
				for($i=0; $i<sizeof($pars); $i++)
				{
					$this->addParam($pars[$i]);
				}
			}
		}

		function xml_header()
		{
			return "<?xml version=\"1.0\"?" . ">\n<methodCall>\n";
		}

		function xml_footer()
		{
			return "</methodCall>\n";
		}

		function createPayload()
		{
			$this->payload=$this->xml_header();
			$this->payload.='<methodName>' . $this->methodname . "</methodName>\n";
			//	if(sizeof($this->params)) {
			$this->payload.="<params>\n";
			for($i=0; $i<sizeof($this->params); $i++)
			{
				$p=$this->params[$i];
				$this->payload.="<param>\n" . $p->serialize() .
				"</param>\n";
			}
			$this->payload.="</params>\n";
			// }
			$this->payload.=$this->xml_footer();
			//$this->payload=str_replace("\n", "\r\n", $this->payload);
		}

		function method($meth='')
		{
			if($meth!='')
			{
				$this->methodname=$meth;
			}
			return $this->methodname;
		}

		function serialize()
		{
			$this->createPayload();
			return $this->payload;
		}

		function addParam($par)
		{
			// add check: do not add to self params which are not xmlrpcvals
			if(is_object($par) && (get_class($par) == 'xmlrpcval' || is_subclass_of($par, 'xmlrpcval')))
			{
				$this->params[]=$par;
				return true;
			}
			else
			{
				return false;
			}
		}

		function getParam($i) { return $this->params[$i]; }
		function getNumParams() { return sizeof($this->params); }

		function &parseResponseFile($fp)
		{
			$ipd='';
			while($data=fread($fp, 32768))
			{
				$ipd.=$data;
			}
			//fclose($fp);
			$r =& $this->parseResponse($ipd);
			return $r;
		}

		function &parseResponse($data='', $headers_processed=false)
		{
			$parser = xml_parser_create($GLOBALS['xmlrpc_defencoding']);

			$hdrfnd = 0;
			if($this->debug)
			{
				//by maHo, replaced htmlspecialchars with htmlentities
				print "<PRE>---GOT---\n" . htmlentities($data) . "\n---END---\n</PRE>";
			}

			if($data == '')
			{
				error_log('No response received from server.');
				$r =& CreateObject('phpgwapi.xmlrpcresp',0, $GLOBALS['xmlrpcerr']['no_data'], $GLOBALS['xmlrpcstr']['no_data']);
				return $r;
			}
			// see if we got an HTTP 200 OK, else bomb
			// but only do this if we're using the HTTP protocol.
			if(preg_match('/'."^HTTP".'/',$data))
			{
				// Strip HTTP 1.1 100 Continue header if present
				while(preg_match('/^HTTP\\/1.1 1[0-9]{2} /', $data))
				{
					$pos = strpos($data, 'HTTP', 12);
					// server sent a Continue header without any (valid) content following...
					// give the client a chance to know it
					if(!$pos && !is_int($pos)) // works fine in php 3, 4 and 5
					{
						break;
					}
					$data = substr($data, $pos);
				}
				if(!preg_match('/'."^HTTP\\/[0-9\\.]+ 200 ".'/', $data))
				{
					$errstr= substr($data, 0, strpos($data, "\n")-1);
					error_log('HTTP error, got response: ' .$errstr);
					$r=& CreateObject('phpgwapi.xmlrpcresp',0, $GLOBALS['xmlrpcerr']['http_error'], $GLOBALS['xmlrpcstr']['http_error']. ' (' . $errstr . ')');
					return $r;
				}
			}

			$GLOBALS['_xh'][$parser] = array();
			$GLOBALS['_xh'][$parser]['headers'] = array();
			$GLOBALS['_xh'][$parser]['stack'] = array();
			$GLOBALS['_xh'][$parser]['valuestack'] = array();

			// separate HTTP headers from data
			if(preg_match('/'."^HTTP".'/', $data))
			{
				// be tolerant to usage of \n instead of \r\n to separate headers and data
				// (even though it is not valid http)
				$pos = strpos($data,"\r\n\r\n");
				if($pos || is_int($pos))
				{
					$bd = $pos+4;
				}
				else
				{
					$pos = strpos($data,"\n\n");
					if($pos || is_int($pos))
					{
						$bd = $pos+2;
					}
					else
					{
						// No separation between response headers and body: fault?
						$bd = 0;
					}
				}
				// be tolerant to line endings, and extra empty lines
				$ar = preg_split("/\r?\n/", trim(substr($data, 0, $pos)));
				while(list(,$line) = @each($ar))
				{
					// take care of multi-line headers
					$arr = explode(':',$line);
					if(count($arr) > 1)
					{
						$header_name = strtolower(trim($arr[0]));
						// TO DO: some headers (the ones that allow a CSV list of values)
						// do allow many values to be passed using multiple header lines.
						// We should add content to $GLOBALS['_xh'][$parser]['headers'][$header_name]
						// instead of replacing it for those...
						$GLOBALS['_xh'][$parser]['headers'][$header_name] = $arr[1];
						for($i = 2; $i < count($arr); $i++)
						{
							$GLOBALS['_xh'][$parser]['headers'][$header_name] .= ':'.$arr[$i];
						} // while
						$GLOBALS['_xh'][$parser]['headers'][$header_name] = trim($GLOBALS['_xh'][$parser]['headers'][$header_name]);
					}
					elseif(isset($header_name))
					{
						$GLOBALS['_xh'][$parser]['headers'][$header_name] .= ' ' . trim($line);
					}
				}
				$data = substr($data, $bd);

				if($this->debug && count($GLOBALS['_xh'][$parser]['headers']))
				{
					print '<PRE>';
					foreach($GLOBALS['_xh'][$parser]['headers'] as $header => $value)
					{
						print "HEADER: $header: $value\n";
					}
					print "</PRE>\n";
				}
			}

			// if CURL was used for the call, http headers have been processed,
			// and dechunking + reinflating have been carried out
			if(!$headers_processed)
			{
				// Decode chunked encoding sent by http 1.1 servers
				if(isset($GLOBALS['_xh'][$parser]['headers']['transfer-encoding']) && $GLOBALS['_xh'][$parser]['headers']['transfer-encoding'] == 'chunked')
				{
					if(!$data = decode_chunked($data))
					{
						error_log('Errors occurred when trying to rebuild the chunked data received from server');
						$r =& CreateObject('phpgwapi.xmlrpcresp',0, $GLOBALS['xmlrpcerr']['dechunk_fail'], $GLOBALS['xmlrpcstr']['dechunk_fail']);
						return $r;
					}
				}

				// Decode gzip-compressed stuff
				// code shamelessly inspired from nusoap library by Dietrich Ayala
				if(isset($GLOBALS['_xh'][$parser]['headers']['content-encoding']))
				{
					if($GLOBALS['_xh'][$parser]['headers']['content-encoding'] == 'deflate' || $GLOBALS['_xh'][$parser]['headers']['content-encoding'] == 'gzip')
					{
						// if decoding works, use it. else assume data wasn't gzencoded
						if(function_exists('gzinflate'))
						{
							if($GLOBALS['_xh'][$parser]['headers']['content-encoding'] == 'deflate' && $degzdata = @gzinflate($data))
							{
								$data = $degzdata;
								if($this->debug)
								print "<PRE>---INFLATED RESPONSE---[".strlen($data)." chars]---\n" . htmlentities($data) . "\n---END---</PRE>";
							}
							elseif($GLOBALS['_xh'][$parser]['headers']['content-encoding'] == 'gzip' && $degzdata = @gzinflate(substr($data, 10)))
							{
								$data = $degzdata;
								if($this->debug)
								print "<PRE>---INFLATED RESPONSE---[".strlen($data)." chars]---\n" . htmlentities($data) . "\n---END---</PRE>";
							}
							else
							{
								error_log('Errors occurred when trying to decode the deflated data received from server');
								$r =& CreateObject('phpgwapi.xmlrpcresp',0, $GLOBALS['xmlrpcerr']['decompress_fail'], $GLOBALS['xmlrpcstr']['decompress_fail']);
								return $r;
							}
						}
						else
						{
							error_log('The server sent deflated data. Your php install must have the Zlib extension compiled in to support this.');
							$r =& CreateObject('phpgwapi.xmlrpcresp',0, $GLOBALS['xmlrpcerr']['cannot_decompress'], $GLOBALS['xmlrpcstr']['cannot_decompress']);
							return $r;
						}
					}
				}
			} // end of 'de-chunk, re-inflate response'

			// be tolerant of extra whitespace in response body
			$data = trim($data);

			// be tolerant of junk after methodResponse (e.g. javascript automatically inserted by free hosts)
			// idea from Luca Mariano <luca.mariano@email.it> originally in PEARified version of the lib
			$bd = false;
			$pos = strpos($data, '</methodResponse>');
			while($pos || is_int($pos))
			{
				$bd = $pos+17;
				$pos = strpos($data, '</methodResponse>', $bd);
			}
			if($bd)
			{
				$data = substr($data, 0, $bd);
			}

			$GLOBALS['_xh'][$parser]['isf']=0;
			$GLOBALS['_xh'][$parser]['isf_reason']='';
			$GLOBALS['_xh'][$parser]['ac']='';
			$GLOBALS['_xh'][$parser]['qt']='';

			xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, true);
			// G. Giunta 2005/02/13: PHP internally uses ISO-8859-1, so we have to tell
			// the xml parser to give us back data in the expected charset
			xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, $GLOBALS['xmlrpc_internalencoding']);

			xml_set_element_handler($parser, 'xmlrpc_se', 'xmlrpc_ee');
			xml_set_character_data_handler($parser, 'xmlrpc_cd');
			xml_set_default_handler($parser, 'xmlrpc_dh');
			//$xmlrpc_value=new xmlrpcval;

			if(!xml_parse($parser, $data, sizeof($data)))
			{
				// thanks to Peter Kocks <peter.kocks@baygate.com>
				if((xml_get_current_line_number($parser)) == 1)
				{
					$errstr = 'XML error at line 1, check URL';
				}
				else
				{
					$errstr = sprintf('XML error: %s at line %d',
						xml_error_string(xml_get_error_code($parser)),
						xml_get_current_line_number($parser));
				}
				error_log($errstr);
				$r =& CreateObject('phpgwapi.xmlrpcresp',0, $GLOBALS['xmlrpcerr']['invalid_return'], $GLOBALS['xmlrpcstr']['invalid_return'].' ('.$errstr.')');
				xml_parser_free($parser);
				if($this->debug)
				{
					print $errstr;
				}
				$r->hdrs = $GLOBALS['_xh'][$parser]['headers'];
				return $r;
			}
			xml_parser_free($parser);
			if($GLOBALS['_xh'][$parser]['isf'] > 1)
			{
				if ($this->debug)
				{
					///@todo echo something for user?
				}

				$r =& CreateObject('phpgwapi.xmlrpcresp',0, $GLOBALS['xmlrpcerr']['invalid_return'],
				$GLOBALS['xmlrpcstr']['invalid_return'] . ' ' . $GLOBALS['_xh'][$parser]['isf_reason']);
			}
			elseif (!is_object($GLOBALS['_xh'][$parser]['value']))
			{
				// then something odd has happened
				// and it's time to generate a client side error
				// indicating something odd went on
				$r=& CreateObject('phpgwapi.xmlrpcresp',0, $GLOBALS['xmlrpcerr']['invalid_return'],
				$GLOBALS['xmlrpcstr']['invalid_return']);
			}
			else
			{
				if ($this->debug)
				{
					print "<PRE>---PARSED---\n" ;
					var_dump($GLOBALS['_xh'][$parser]['value']);
					print "\n---END---</PRE>";
				}				// note that using =& will raise an error if $GLOBALS['_xh'][$parser]['st'] does not generate an object.

				$v = $GLOBALS['_xh'][$parser]['value'];

				if($GLOBALS['_xh'][$parser]['isf'])
				{
					$errno_v = $v->structmem('faultCode');
					$errstr_v = $v->structmem('faultString');
					$errno = $errno_v->scalarval();

					if($errno == 0)
					{
						// FAULT returned, errno needs to reflect that
						$errno = -1;
					}

					$r =& CreateObject('phpgwapi.xmlrpcresp',$v, $errno, $errstr_v->scalarval());
				}
				else
				{
					$r=& CreateObject('phpgwapi.xmlrpcresp',$v);
				}
			}

			$r->hdrs = $GLOBALS['_xh'][$parser]['headers'];
			return $r;
		}
	}
?>
