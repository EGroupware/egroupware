<?php

/* soapx4 high level class

usage:

// instantiate client with server info
$soapclient = new soapclient( string path [ ,boolean wsdl] );

// call method, get results
echo $soapclient->call( string methodname [ ,array parameters] );

// bye bye client
unset($soapclient);

*/

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
			if($this->endpointType != "wsdl")
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
			if($this->endpointType != "wsdl")
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
			$this->debug($dbg."instantiated client successfully");
			$this->debug("client data:<br>server: $soap_client->server<br>path: $soap_client->path<br>port: $soap_client->port");
			// send
			$dbg = "sending msg w/ soapaction '$soapAction'...";
			if($return = $soap_client->send($soapmsg,$soapAction))
			{
				$this->request = $soap_client->outgoing_payload;
				$this->response = $soap_client->incoming_payload;
				$this->debug($dbg."sent message successfully and got a '$return' back");
				// check for valid response
				if(get_class($return) == "soapval")
				{
					// fault?
					if(eregi("fault",$return->name))
					{
						$this->debug("got fault");
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
				$this->debug("client send/recieve error");
				return false;
			}
		}
	}

	function debug($string)
	{
		if($this->debug_flag)
		{
			print $string."<br>";
		}
	}
}
?>
