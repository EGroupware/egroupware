<?php
  /* $Id$ */

	// soap value object
	class soapval
	{
	// 	function soapval($name='',$type=False,$value=-1,$namespace=False,$type_namespace=False)
		function soapval($name='',$type=False,$value=0,$namespace=False,$type_namespace=False)
		{
			global $soapTypes, $typemap, $namespaces, $methodNamespace;

			// detect type if not passed
			if(!$type)
			{
				if(is_array($value) && count($value) >= 1)
				{
					if(ereg("[a-zA-Z0-9\-]*",key($v)))
					{
						$type = 'struct';
					}
					else
					{
						$type = 'array';
					}
				}
				elseif(is_int($v))
				{
					$type = 'int';
				}
				elseif(is_float($v) || $v == 'NaN' || $v == 'INF')
				{
					$type = 'float';
				}
				else
				{
					$type = gettype($value);
				}
			}
			// php type name mangle
			if($type == 'integer')
			{
				$type = 'int';
			}

			$this->soapTypes = $soapTypes;
			$this->name = $name;
			$this->value = '';
			$this->type = $type;
			$this->type_code = 0;
			$this->type_prefix = false;
			$this->array_type = '';
			$this->debug_flag = True;
			$this->debug_str = '';
			$this->debug("Entering soapval - name: '$name' type: '$type'");

			if($namespace)
			{
				$this->namespace = $namespace;
				if(!isset($namespaces[$namespace]))
				{
					$namespaces[$namespace] = "ns".(count($namespaces)+1);
				}
				$this->prefix = $namespaces[$namespace];
			}

			// get type prefix
			if(ereg(":",$type))
			{
				$this->type = substr(strrchr($type,':'),1,strlen(strrchr($type,':')));
				$this->type_prefix = substr($type,0,strpos($type,':'));
			}
			elseif($type_namespace)
			{
				if(!isset($namespaces[$type_namespace]))
				{
					$namespaces[$type_namespace] = 'ns'.(count($namespaces)+1);
				}
				$this->type_prefix = $namespaces[$type_namespace];
			}

			// if type namespace was not explicitly passed, and we're not in a method struct:
			elseif(!$this->type_prefix && !isset($this->namespace))
			{
				// try to get type prefix from typeMap
				if(!$this->type_prefix = $this->verify_type($type))
				{
					// else default to method namespace
					$this->type_prefix = $namespaces[$methodNamespace];
				}
			}

			// if scalar
			if($this->soapTypes[$this->type] == 1)
			{
				$this->type_code = 1;
				$this->addScalar($value,$this->type,$name);
			// if array
			}
			elseif($this->soapTypes[$this->type] == 2)
			{
				$this->type_code = 2;
				$this->addArray($value);
			// if struct
			}
			elseif($this->soapTypes[$this->type] == 3)
			{
				$this->type_code = 3;
				$this->addStruct($value);
			}
			else
			{
				//if($namespace == $methodNamespace){
					$this->type_code = 3;
					$this->addStruct($value);
				//}
			}
		}

		function addScalar($value, $type, $name="")
		{
			$this->debug("adding scalar '$name' of type '$type'");
			
			// if boolean, change value to 1 or 0
			if ($type == "boolean")
			{
				if((strcasecmp($value,"true") == 0) || ($value == 1))
				{
					$value = 1;
				}
				else
				{
					$value = 0;
				}
			}

			$this->value = $value;
			return true;
		}

		function addArray($vals)
		{
			$this->debug("adding array '$this->name' with ".count($vals)." vals");
			$this->value = array();
			if(is_array($vals) && count($vals) >= 1)
			{
				@reset($vals);
				while(list($k,$v) = @each($vals))
				/* foreach($vals as $k => $v) */
				{
					$this->debug("checking value $k : $v");
					// if soapval, add..
					if(get_class($v) == 'soapval')
					{
						$this->value[] = $v;
						$this->debug($v->debug_str);
					// else make obj and serialize
					}
					else
					{
						if(is_array($v))
						{
							if(ereg("[a-zA-Z\-]*",key($v)))
							{
								$type = 'struct';
							}
							else
							{
								$type = 'array';
							}
						}
						elseif(!ereg("^[0-9]*$",$k) && in_array($k,array_keys($this->soapTypes)))
						{
							$type = $k;
						}
						elseif(is_int($v))
						{
							$type = 'int';
						}
						elseif(is_float($v) || $v == 'NaN' || $v == 'INF')
						{
							$type = 'float';
						}
						else
						{
							$type = gettype($v);
						}
						$new_val = CreateObject('phpgwapi.soapval','item',$type,$v);
						$this->debug($new_val->debug_str);
						$this->value[] = $new_val;
					}
				}
			}
			return true;
		}

		function addStruct($vals)
		{
			$this->debug("adding struct '$this->name' with ".count($vals).' vals');
			if(is_array($vals) && count($vals) >= 1)
			{
				@reset($vals);
				while(list($k,$v) = @each($vals))
				/* foreach($vals as $k => $v) */
				{
					// if serialize, if soapval
					if(get_class($v) == 'soapval')
					{
						$this->value[] = $v;
						$this->debug($v->debug_str);
					// else make obj and serialize
					}
					else
					{
						if(is_array($v))
						{
							@reset($v);
							while(list($a,$b) = @each($v))
							/* foreach($v as $a => $b) */
							{
								if($a == "0")
								{
									$type = 'array';
								}
								else
								{
									$type = 'struct';
								}
								break;
							}
						}
//						elseif(is_array($k) && in_array($k,array_keys($this->soapTypes)))
						elseif(is_array($k,in_array($k,array_keys($this->soapTypes))))
						{
							$this->debug("got type '$type' for value '$v' from soapTypes array!");
							$type = $k;
						}
						elseif(is_int($v))
						{
							$type = 'int';
						}
						elseif(is_float($v) || $v == "NaN" || $v == "INF")
						{
							$type = 'float';
						}
						else
						{
							$type = gettype($v);
							$this->debug("got type '$type' for value '$v' from php gettype()!");
						}
						$new_val = CreateObject('phpgwapi.soapval',$k,$type,$v);
						$this->debug($new_val->debug_str);
						$this->value[] = $new_val;
					}
				}
			}
			else
			{
				$this->value = array();
			}
			return true;
		}

		// turn soapvals into xml, woohoo!
		function serializeval($soapval=false)
		{
			if(!$soapval)
			{
				$soapval = $this;
			}
			$this->debug("serializing '$soapval->name' of type '$soapval->type'");
			if($soapval->name == '')
			{
				$soapval->name = 'return';
			}

			switch($soapval->type_code)
			{
				case 3:
					// struct
					$this->debug('got a struct');
					if($soapval->prefix && $soapval->type_prefix)
					{
						$xml .= "<$soapval->prefix:$soapval->name xsi:type=\"$soapval->type_prefix:$soapval->type\">\n";
					}
					elseif($soapval->type_prefix)
					{
						$xml .= "<$soapval->name xsi:type=\"$soapval->type_prefix:$soapval->type\">\n";
					}
					elseif($soapval->prefix)
					{
						$xml .= "<$soapval->prefix:$soapval->name>\n";
					}
					else
					{
						$xml .= "<$soapval->name>\n";
					}
					if(is_array($soapval->value))
					{
						@reset($soapval->value);
						while(list($k,$v) = @each($soapval->value))
						/* foreach($soapval->value as $k => $v) */
						{
							$xml .= $this->serializeval($v);
						}
					}
					if($soapval->prefix)
					{
						$xml .= "</$soapval->prefix:$soapval->name>\n";
					}
					else
					{
						$xml .= "</$soapval->name>\n";
					}
					break;
				case 2:
					// array
					@reset($soapval->value);
					while(list($null,$array_val) = @each($soapval->value))
					/* foreach($soapval->value as $array_val) */
					{
						$array_types[$array_val->type] = 1;
						$xml .= $this->serializeval($array_val);
					}
					if(count($array_types) > 1)
					{
						$array_type = 'xsd:ur-type';
					}
					elseif(count($array_types) >= 1)
					{
						$array_type = $array_val->type_prefix.":".$array_val->type;
					}

					$xml = "<$soapval->name xsi:type=\"SOAP-ENC:Array\" SOAP-ENC:arrayType=\"".$array_type."[".sizeof($soapval->value)."]\">\n".$xml."</$soapval->name>\n";
					break;
				case 1:
					$xml .= "<$soapval->name xsi:type=\"$soapval->type_prefix:$soapval->type\">$soapval->value</$soapval->name>\n";
					break;
				default:
					break;
			}
			return $xml;
		}

		// serialize
		function serialize()
		{
			return $this->serializeval($this);
		}

		function decode($soapval=false)
		{
			if(!$soapval)
			{
				$soapval = $this;
			}
			// scalar decode
			if($soapval->type_code == 1)
			{
				return $soapval->value;
			// array decode
			}
			elseif($soapval->type_code == 2)
			{
				if(is_array($soapval->value))
				{
					@reset($soapval->value);
					while(list($null,$item) = @each($soapval->value))
					/* foreach($soapval->value as $item) */
					{
						$return[] = $this->decode($item);
					}
					return $return;
				}
				else
				{
					return array();
				}
			// struct decode
			}
			elseif($soapval->type_code == 3)
			{
				if(is_array($soapval->value))
				{
					@reset($soapval->value);
					while(list($null,$item) = @each($soapval->value))
					/* foreach($soapval->value as $item) */
					{
						$return[$item->name] = $this->decode($item);
					}
					return $return;
				}
				else
				{
					return array();
				}
			}
		}

		// verify type
		function verify_type($type)
		{
			if ($type)
			{
//				global $namespaces,$soapTypes,$typemap;
				global $namespaces,$typemap;

				@reset($typemap);
				while(list($namespace,$types) = @each($typemap))
				/* foreach($typemap as $namespace => $types) */
				{
					if(in_array($type,$types))
					{
						return $namespaces[$namespace];
					}
				}
			}
			return false;
		}

		// alias for verify_type() - pass it a type, and it returns it's prefix
		function get_prefix($type)
		{
			if($prefix = $this->verify_type($type))
			{
				return $prefix;
			}
			return false;
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
