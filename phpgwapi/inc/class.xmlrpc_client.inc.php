<?php
	// by Edd Dumbill (C) 1999-2001
	// <edd@usefulinc.com>
	// xmlrpc.inc,v 1.18 2001/07/06 18:23:57 edmundd

	// License is granted to use or modify this software ("XML-RPC for PHP")
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

	class xmlrpc_client
	{
		var $path;
		var $server;
		var $port;
		var $errno;
		var $errstring;
		var $debug=0;
		var $username = '';
		var $password = '';

		function xmlrpc_client($path='', $server='', $port=80)
		{
			$this->port   = $port;
			$this->server = $server;
			$this->path   = $path;
		}

		function setDebug($in)
		{
			if ($in)
			{
				$this->debug = 1;
			}
			else
			{
				$this->debug = 0;
			}
		}

		function setCredentials($u, $p)
		{
			$this->username = $u;
			$this->password = $p;
		}

		function send($msg, $timeout=0)
		{
			// where msg is an xmlrpcmsg
			$msg->debug=$this->debug;
			return $this->sendPayloadHTTP10(
				$msg,
				$this->server, $this->port,
				$timeout, $this->username, 
				$this->password
			);
		}

		function sendPayloadHTTP10($msg, $server, $port, $timeout=0,$username="", $password="")
		{
			if($timeout>0)
			{
				$fp=fsockopen($server, $port,&$this->errno, &$this->errstr, $timeout);
			}
			else
			{
				$fp=fsockopen($server, $port,&$this->errno, &$this->errstr);
			}
			if (!$fp)
			{
				return 0;
			}
			// Only create the payload if it was not created previously
			if(empty($msg->payload))
			{
				$msg->createPayload();
			}
		
			// thanks to Grant Rauscher <grant7@firstworld.net>
			// for this
			$credentials = '';
			if ($username!="")
			{
				$credentials = "Authorization: Basic " . base64_encode($username . ":" . $password) . "\r\n";
			}

			$op = "POST " . $this->path . " HTTP/1.0\r\nUser-Agent: PHP XMLRPC 1.0\r\n"
				. "Host: ". $this->server . "\r\n" .
				. 'X-PHPGW-Server: '  . $this->server . ' ' . "\r\n"
				. 'X-PHPGW-Version: ' . $GLOBALS['phpgw_info']['server']['versions']['phpgwapi'] . "\r\n"
				. $credentials
				. "Content-Type: text/xml\r\nContent-Length: "
				. strlen($msg->payload) . "\r\n\r\n"
				. $msg->payload;

			if (!fputs($fp, $op, strlen($op)))
			{
				$this->errstr="Write error";
				return 0;
			}
			$resp = $msg->parseResponseFile($fp);
			fclose($fp);
			return $resp;
		}
	}
?>
