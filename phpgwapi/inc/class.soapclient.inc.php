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

/* soapx4 high level class

	usage:

	// instantiate client with server info
	$soapclient = new soapclient( string path [ ,boolean wsdl] );

	// call method, get results
	echo $soapclient->call( string methodname [ ,array parameters] );

	// bye bye client
	unset($soapclient);

*/
  /* $Id$ */

	class soapclient
	{
		function soapclient($endpoint,$wsdl=False,$portName=False)
		{
			$this->debug_flag = True;
			$this->endpoint = $endpoint;
			$this->portName = False;

			// make values
			if($wsdl)
			{
				$this->endpointType = 'wsdl';
				if($portName)
				{
					$this->portName = $portName;
				}
			}
		}

		function call($method,$params='',$namespace=false,$soapAction=false)
		{
			if($this->endpointType == 'wsdl')
			{
				// instantiate wsdl class
				$this->wsdl = CreateObject('phpgwapi.wsdl',$this->endpoint);
				// get portName
				if(!$this->portName)
				{
					$this->portName = $this->wsdl->getPortName($method);
				}
				// get endpoint
				if(!$this->endpoint = $this->wsdl->getEndpoint($this->portName))
				{
					die("no port of name '$this->portName' in the wsdl at that location!");
				}
				$this->debug("endpoint: $this->endpoint");
				$this->debug("portName: $this->portName");
			}
			// get soapAction
			if(!$soapAction)
			{
				if($this->endpointType != 'wsdl')
				{
					die("method call requires soapAction if wsdl is not available!");
				}
				if(!$soapAction = $this->wsdl->getSoapAction($this->portName,$method))
				{
					die("no soapAction for operation: $method!");
				}
			}
			$this->debug("soapAction: $soapAction");
			// get namespace
			if(!$namespace)
			{
				if($this->endpointType != 'wsdl')
				{
					die("method call requires namespace if wsdl is not available!");
				}
				if(!$namespace = $this->wsdl->getNamespace($this->portName,$method))
				{
					die("no soapAction for operation: $method!");
				}
			}
			$this->debug("namespace: $namespace");

			// make message
			$soapmsg = CreateObject('phpgwapi.soapmsg',$method,$params,$namespace);
			/* _debug_array($soapmsg); */
			
			// instantiate client
			$dbg = "calling server at '$this->endpoint'...";
			if($soap_client = CreateObject('phpgwapi.soap_client',$this->endpoint))
			{
				//$soap_client->debug_flag = true;
				$this->debug($dbg.'instantiated client successfully');
				$this->debug("client data:<br>server: $soap_client->server<br>path: $soap_client->path<br>port: $soap_client->port");
				// send
				$dbg = "sending msg w/ soapaction '$soapAction'...";
				if($return = $soap_client->send($soapmsg,$soapAction))
				{
					$this->request = $soap_client->outgoing_payload;
					$this->response = $soap_client->incoming_payload;
					$this->debug($dbg . "sent message successfully and got a '$return' back");
					// check for valid response
					if(get_class($return) == 'soapval')
					{
						// fault?
						if(eregi('fault',$return->name))
						{
							$this->debug('got fault');
							$faultArray = $return->decode();
							@reset($faultArray);
							while(list($k,$v) = @each($faultArray))
							/* foreach($faultArray as $k => $v) */
							{
								print "$k = $v<br>";
							}
							return false;
						}
						else
						{
							$returnArray = $return->decode();
							if(is_array($returnArray))
							{
								return array_shift($returnArray);
							}
							else
							{
								$this->debug("didn't get array back from decode() for $return->name");
								return false;
							}
						}
					}
					else
					{
						$this->debug("didn't get soapval object back from client");
						return false;
					}
				}
				else
				{
					$this->debug('client send/recieve error');
					return false;
				}
			}
		}

		function debug($string)
		{
			if($this->debug_flag)
			{
				print $string . '<br>';
			}
		}
	}
?>
