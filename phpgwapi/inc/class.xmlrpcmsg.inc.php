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
			$this->methodname = $meth;
			if (is_array($pars) && sizeof($pars)>0)
			{
				for($i=0; $i<sizeof($pars); $i++) 
				{
					$this->addParam($pars[$i]);
				}
			}
		}

		function xml_header()
		{
			return '<?xml version="1.0"?>' . "\n" . '<methodCall>' . "\n";
		}

		function xml_footer()
		{
			return '</methodCall>' . "\n";
		}

		function createPayload()
		{
			$this->payload  = $this->xml_header();
			$this->payload .= '<methodName>' . $this->methodname . '</methodName>' . "\n";
			if (sizeof($this->params))
			{
				$this->payload .= '<params>' . "\n";
				for($i=0; $i<sizeof($this->params); $i++)
				{
					$p = $this->params[$i];
					$this->payload .= '<param>' . "\n" . $p->serialize() . '</param>' . "\n";
				}
				$this->payload .= '</params>' . "\n";
			}
			$this->payload .= $this->xml_footer();
			$this->payload  = str_replace("\n", "\r\n", $this->payload);
		}

		function method($meth='')
		{
			if ($meth != '')
			{
				$this->methodname = $meth;
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
			$this->params[] = $par;
		}

		function getParam($i)
		{
			return $this->params[$i];
		}

		function getNumParams()
		{
			return sizeof($this->params);
		}

		function parseResponseFile($fp)
		{
			$ipd = '';

			while($data = fread($fp, 32768))
			{
				$ipd .= $data;
			}
			return $this->parseResponse($ipd);
		}

		function parseResponse($data='')
		{
			$parser = xml_parser_create($GLOBALS['xmlrpc_defencoding']);

			$GLOBALS['_xh'][$parser]        = array();
			$GLOBALS['_xh'][$parser]['st']  = ''; 
			$GLOBALS['_xh'][$parser]['cm']  = 0; 
			$GLOBALS['_xh'][$parser]['isf'] = 0; 
			$GLOBALS['_xh'][$parser]['ac']  = '';
			$GLOBALS['_xh'][$parser]['qt']  = '';
			$GLOBALS['_xh'][$parser]['ha']  = '';

			xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, true);
			xml_set_element_handler($parser, 'xmlrpc_se', 'xmlrpc_ee');
			xml_set_character_data_handler($parser, 'xmlrpc_cd');
			xml_set_default_handler($parser, 'xmlrpc_dh');
//			$xmlrpc_value = CreateObject('phpgwapi.xmlrpcval');

			$hdrfnd = 0;
			if ($this->debug)
			{
				echo '<PRE>---GOT---' . "\n" . htmlspecialchars($data) . "\n" . '---END---' . "\n" . '</PRE>';
			}
			if ($data == '')
			{
				error_log('No response received from server.');
				$r = CreateObject(
					'phpgwapi.xmlrpcresp',
					0,
					$GLOBALS['xmlrpcerr']['no_data'],
					$GLOBALS['xmlrpcstr']['no_data']
				);
				xml_parser_free($parser);
				return $r;
			}

			// see if we got an HTTP 200 OK, else bomb
			// but only do this if we're using the HTTP protocol.
			if (ereg("^HTTP",$data) && !ereg("^HTTP/[0-9\.]+ 200 ", $data))
			{
				$errstr = substr($data, 0, strpos($data, "\n")-1);
				error_log('HTTP error, got response: ' .$errstr);
				$r = CreateObject('phpgwapi.xmlrpcresp',0, $GLOBALS['xmlrpcerr']['http_error'],
					$GLOBALS['xmlrpcstr']['http_error'] . ' (' . $errstr . ')');
				xml_parser_free($parser);
				return $r;
			}

			// if using HTTP, then gotta get rid of HTTP headers here
			// and we store them in the 'ha' bit of our data array
			if (ereg("^HTTP", $data))
			{
				$ar=explode("\r\n", $data);
				$newdata = '';
				$hdrfnd  = 0;
				for ($i=0; $i<sizeof($ar); $i++)
				{
					if (!$hdrfnd)
					{
						if (strlen($ar[$i])>0)
						{
							$GLOBALS['_xh'][$parser]['ha'] .= $ar[$i]. "\r\n";
						}
						else
						{
							$hdrfnd=1;
						}
					}
					else
					{
						$newdata.=$ar[$i] . "\r\n";
					}
				}
				$data=$newdata;
			}

			if (!xml_parse($parser, $data, sizeof($data)))
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
				$r = CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval'), $GLOBALS['xmlrpcerr']['invalid_return'],$GLOBALS['xmlrpcstr']['invalid_return']);
				xml_parser_free($parser);
				return $r;
			}
			xml_parser_free($parser);
			if ($this->debug)
			{
				echo '<PRE>---EVALING---['
					. strlen($GLOBALS['_xh'][$parser]['st']) . ' chars]---' . "\n"
					. htmlspecialchars($GLOBALS['_xh'][$parser]['st']) . ';' . "\n" . '---END---</PRE>';
			}
			if (strlen($GLOBALS['_xh'][$parser]['st']) == 0)
			{
				// then something odd has happened
				// and it's time to generate a client side error
				// indicating something odd went on
				$r = CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval'), $GLOBALS['xmlrpcerr']['invalid_return'],$GLOBALS['xmlrpcstr']['invalid_return']);
			}
			else
			{
				$code = '$v=' . $GLOBALS['_xh'][$parser]['st'] . '; $allOK=1;';
				$code = ereg_replace(',,',",'',",$code);
				eval($code);
				if ($GLOBALS['_xh'][$parser]['isf'])
				{
					$f  = $v->structmem('faultCode');
					$fs = $v->structmem('faultString');
					$r  = CreateObject('phpgwapi.xmlrpcresp',$v, $f->scalarval(), $fs->scalarval());
				}
				else
				{
					$r = CreateObject('phpgwapi.xmlrpcresp',$v);
				}
			}
			$r->hdrs = split("\r?\n", $GLOBALS['_xh'][$parser]['ha'][1]);
			return $r;
		}
	}
?>
