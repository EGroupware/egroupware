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

*/

	/*  changelog:
	2001-07-04
	- abstract type system to support either 1999 or 2001 schema (arg, typing still needs much
	solidification.)
	- implemented proxy support, based on sample code from miles lott <milos@speakeasy.net>
	- much general cleanup of code & cleaned out what was left of original xml-rpc/gigaideas code
	- implemented a transport argument into send() that allows you to specify different transports
	(assuming you have implemented the function, and added it to the conditional statement in send()
	- abstracted the determination of charset in Content-type header
	2001-07-5
	- fixed more weird type/namespace issues
	*/

	// $path can be a complete endpoint url, with the other parameters left blank:
	// $soap_client = new soap_client("http://path/to/soap/server");

  /* $Id$ */

	class soap_client
	{
		 function soap_client($path,$server=False,$port=False)
		 {
			$this->port = 80;
			$this->path = $path;
			$this->server = $server;
			$this->errno;
			$this->errstring;
			$this->debug_flag = False;
			$this->debug_str = '';
			$this->username = '';
			$this->password = '';
			$this->action = '';
			$this->incoming_payload = '';
			$this->outgoing_payload = '';
			$this->response = '';
			$this->action = '';

			// endpoint mangling
			if(ereg("^http://",$path))
			{
				$path = str_replace('http://','',$path);
				$this->path = strstr($path,'/');
				$this->debug("path = $this->path");
				if(ereg(':',$path))
				{
					$this->server = substr($path,0,strpos($path,':'));
					$this->port = substr(strstr($path,':'),1);
					$this->port = substr($this->port,0,strpos($this->port,'/'));
				}
				else
				{
					$this->server = substr($path,0,strpos($path,'/'));
				}
			}
			if($port)
			{
				$this->port = $port;
			}
		}

		function setCredentials($u, $p)
		{
			$this->username = $u;
			$this->password = $p;
		}

		function send($msg, $action, $timeout=0, $ssl=False)
		{
			// where msg is an soapmsg
			$msg->debug_flag = $this->debug_flag;
			$this->action = $action;
			if($ssl)
			{
				return $this->ssl_sendPayloadHTTP10(
					$msg,
					$this->server,
					$this->port,
					$timeout,
					$this->username,
					$this->password
				);
			}
			else
			{
				return $this->sendPayloadHTTP10(
					$msg,
					$this->server,
					$this->port,
					$timeout,
					$this->username,
					$this->password
				);
			}
		}

		function sendPayloadHTTP10($msg, $server, $port, $timeout=0, $username='', $password='')
		{	
			if($timeout > 0)
			{
				$fp = fsockopen($server, $port,&$this->errno, &$this->errstr, $timeout);
			}
			else
			{
				$fp = fsockopen($server, $port,&$this->errno, &$this->errstr);
			}
			if (!$fp)
			{
				$this->debug("Couldn't open socket connection to server!");
				$this->debug("Server: $this->server"); 
				return 0;
			}

			// thanks to Grant Rauscher <grant7@firstworld.net> for this
			$credentials = '';
			if ($username != '')
			{
				$credentials = "Authorization: Basic " . base64_encode($username . ":" . $password) . "\r\n";
			}

			$soap_data = $msg->serialize();
			$this->outgoing_payload = 'POST '
				. $this->path
				. " HTTP/1.0\r\n"
				. 'User-Agent: phpGroupware/' . $cliversion . '(PHP) ' . "\r\n"
				. 'X-PHPGW-Server: ' . $this->server . "\r\n"
				. 'X-PHPGW-Version: ' . $GLOBALS['phpgw_info']['server']['versions']['phpgwapi'] . "\r\n"
				. 'Host: '.$this->server . "\r\n"
				. $credentials
				. "Content-Type: text/xml\r\nContent-Length: " . strlen($soap_data) . "\r\n"
				. 'SOAPAction: "' . $this->action . '"' . "\r\n\r\n"
				. $soap_data;
			// send
			if(!fputs($fp, $this->outgoing_payload, strlen($this->outgoing_payload)))
			{
				$this->debug('Write error');
			}

			// get reponse
			while($data = fread($fp, 32768))
			{
				$incoming_payload .= $data;
			}

			fclose($fp);
			$this->incoming_payload = $incoming_payload;
			// $response is a soapmsg object
			$this->response = $msg->parseResponse($incoming_payload);
			$this->debug($msg->debug_str);
			return $this->response;
		}

		function ssl_sendPayloadHTTP10($msg, $server, $port, $timeout=0,$username='', $password='')
		{
			if(!function_exists(curl_init))
			{
				$this->errstr = 'No curl functions available - use of ssl is invalid';
				return False;
			}
			/* curl Method borrowed from:
			  http://sourceforge.net/tracker/index.php?func=detail&aid=427359&group_id=23199&atid=377731
			*/

			// thanks to Grant Rauscher <grant7@firstworld.net>
			// for this
			$credentials = '';
			if ($username!='')
			{
				$credentials = "Authorization: Basic " . base64_encode($username . ':' . $password) . "\r\n";
			}

			$soap_data = $msg->serialize();
			$this->outgoing_payload = 'POST '
				. $this->path
				. " HTTP/1.0\r\n"
				. 'User-Agent: phpGroupware/' . $cliversion . '(PHP) ' . "\r\n"
				. 'X-PHPGW-Server: ' . $this->server . "\r\n"
				. 'X-PHPGW-Version: ' . $GLOBALS['phpgw_info']['server']['versions']['phpgwapi'] . "\r\n"
				. 'Host: ' . $this->server . "\r\n"
				. $credentials
				. "Content-Type: text/xml\r\nContent-Length: " . strlen($soap_data) . "\r\n"
				. 'SOAPAction: "' . $this->action . '"' . "\r\n\r\n"
				. $soap_data;

			// send
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL,$this->server);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->outgoing_payload);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			$incoming_payload = curl_exec($ch);
			curl_close($ch);

			$this->incoming_payload = $incoming_payload;
			// $response is a soapmsg object
			$this->response = $msg->parseResponse($incoming_payload);
			$this->debug($msg->debug_str);
			return $this->response;
		}

		function debug($string)
		{
			if($this->debug_flag)
			{
				$this->debug_str .= "$string\n";
			}
		}
	} // end class soap_client
?>
