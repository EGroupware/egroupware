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

// $path can be a complete endpoint url, with the other parameters left blank:
// $soap_client = new soap_client("http://path/to/soap/server");
class soap_client
{
	 function soap_client($path,$server=False,$port=False)
	 {
		$this->port = 80;
		$this->path = $path;
		$this->server = $server;
		$this->errno;
		$this->errstring;
		$this->debug_flag = True;
		$this->debug_str = "";
		$this->username = "";
		$this->password = "";
		$this->action = "";
		$this->incoming_payload = "";
		$this->outgoing_payload = "";
		$this->response = "";
		$this->action = "";

		// endpoint mangling
		if(ereg("^http://",$path))
		{
			$path = str_replace("http://","",$path);
			$this->path = strstr($path,"/");
			$this->debug("path = $this->path");
			if(ereg(":",$path))
			{
				$this->server = substr($path,0,strpos($path,":"));
				$this->port = substr(strstr($path,":"),1);
				$this->port = substr($this->port,0,strpos($this->port,"/"));
			}
			else
			{
				$this->server = substr($path,0,strpos($path,"/"));
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

	function send($msg, $action, $timeout=0)
	{
		// where msg is an soapmsg
		$msg->debug = $this->debug;
		$this->action = $action;
		return $this->sendPayloadHTTP10(
			$msg,
			$this->server,
			$this->port,
			$timeout,
			$this->username,
			$this->password
		);
	}

	function sendPayloadHTTP10($msg, $server, $port, $timeout=0, $username="", $password="")
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
		$credentials = "";
		if ($username != "")
		{
			$credentials = "Authorization: Basic "
				. base64_encode($username . ":" . $password) . "\r\n";
		}

		$soap_data = $msg->serialize();
		$this->outgoing_payload = "POST ".
			$this->path.
			" HTTP/1.0\r\n".
			"User-Agent: SOAPx4 v0.13492\r\n".
			"Host: ".$this->server . "\r\n".
			$credentials. 
			"Content-Type: text/xml\r\nContent-Length: ".strlen($soap_data)."\r\n".
			"SOAPAction: \"$this->action\""."\r\n\r\n".
			$soap_data;
		// send
		if(!fputs($fp, $this->outgoing_payload, strlen($this->outgoing_payload)))
		{
			$this->debug("Write error");
		}

		// get reponse
		while($data = fread($fp, 32768))
		{
	    	$incoming_payload .= $data;
		}

		fclose($fp);
		$this->incoming_payload = $incoming_payload;
		// $response is a soapmsg object
		//$msg->debug_flag = true;
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
