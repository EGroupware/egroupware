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

  /* $Id$ */

	class xmlrpcval
	{
		var $me=array();
		var $mytype=0;

		function xmlrpcval($val=-1, $type='')
		{
			//$this->me=array();
			//$this->mytype=0;
			if($val!==-1 || $type!='')
			{
				if($type=='')
				{
					$type='string';
				}
				if($GLOBALS['xmlrpcTypes'][$type]==1)
				{
					$this->addScalar($val,$type);
				}
				elseif($GLOBALS['xmlrpcTypes'][$type]==2)
				{
					$this->addArray($val);
				}
				elseif($GLOBALS['xmlrpcTypes'][$type]==3)
				{
					$this->addStruct($val);
				}
			}
		}

		function addScalar($val, $type='string')
		{
			$typeof=@$GLOBALS['xmlrpcTypes'][$type];
			if($typeof!=1)
			{
				error_log("addScalar: not a scalar type ($typeof)");
				return 0;
			}

			// coerce booleans into correct values
			// NB: shall we do it for datetime too?
			if($type == xmlrpcBoolean)
			{
				if(strcasecmp($val,'true')==0 || $val==1 || ($val==true && strcasecmp($val,'false')))
				{
					$val=true;
				}
				else
				{
					$val=false;
				}
			}

			switch($this->mytype)
			{
				case 1:
					error_log('addScalar: scalar xmlrpcval can have only one value');
					return 0;
				case 3:
					error_log('addScalar: cannot add anonymous scalar to struct xmlrpcval');
					return 0;
				case 2:
					// we're adding a scalar value to an array here
					//$ar=$this->me['array'];
					//$ar[]= new xmlrpcval($val, $type);
					//$this->me['array']=$ar;
					// Faster (?) avoid all the costly array-copy-by-val done here...
					$this->me['array'][]=& CreateObject('phpgwapi.xmlrpcval',$val, $type);
					return 1;
				default:
					// a scalar, so set the value and remember we're scalar
					$this->me[$type]=$val;
					$this->mytype=$typeof;
					return 1;
			}
		}

		///@todo add some checking for $vals to be an array of xmlrpcvals?
		function addArray($vals)
		{
			if($this->mytype==0)
			{
				$this->mytype=$GLOBALS['xmlrpcTypes']['array'];
				$this->me['array']=$vals;
				return 1;
			}
			elseif($this->mytype==2)
			{
				// we're adding to an array here
				$this->me['array'] = array_merge($this->me['array'], $vals);
			}
			else
			{
				error_log('xmlrpcval: already initialized as a [' . $this->kindOf() . ']');
				return 0;
			}
		}

		///@todo add some checking for $vals to be an array?
		function addStruct($vals)
		{
			if($this->mytype==0)
			{
				$this->mytype = $GLOBALS['xmlrpcTypes']['struct'];
				$this->me['struct']=$vals;
				return 1;
			}
			elseif($this->mytype==3)
			{
				// we're adding to a struct here
				$this->me['struct'] = array_merge($this->me['struct'], $vals);
			}
			else
			{
				error_log('xmlrpcval: already initialized as a [' . $this->kindOf() . ']');
				return 0;
			}
		}

		function dump($ar)
		{
			foreach($ar as $key => $val)
			{
				echo "$key => $val<br />";
				if($key == 'array')
				{
					while(list($key2, $val2) = each($val))
					{
						echo "-- $key2 => $val2<br />";
					}
				}
			}
		}

		function kindOf()
		{
			switch($this->mytype)
			{
				case 3:
					return 'struct';
					break;
				case 2:
					return 'array';
					break;
				case 1:
					return 'scalar';
					break;
				default:
					return 'undef';
			}
		}

		function serializedata($typ, $val)
		{
			$rs='';
			switch(@$GLOBALS['xmlrpcTypes'][$typ])
			{
				case 3:
					// struct
					$rs.="<struct>\n";
					foreach($val as $key2 => $val2)
					{
						$rs.="<member><name>${key2}</name>\n";
						$rs.=$this->serializeval($val2);
						$rs.="</member>\n";
					}
					$rs.='</struct>';
					break;
				case 2:
					// array
					$rs.="<array>\n<data>\n";
					for($i=0; $i<sizeof($val); $i++)
					{
						$rs.=$this->serializeval($val[$i]);
					}
					$rs.="</data>\n</array>";
					break;
				case 1:
					switch($typ)
					{
						case xmlrpcBase64:
							$rs.="<${typ}>" . base64_encode($val) . "</${typ}>";
							break;
						case xmlrpcBoolean:
							$rs.="<${typ}>" . ($val ? '1' : '0') . "</${typ}>";
							break;
						case xmlrpcString:
							// G. Giunta 2005/2/13: do NOT use htmlentities, since
							// it will produce named html entities, which are invalid xml
							$rs.="<${typ}>" . xmlrpc_encode_entities($val). "</${typ}>";
							// $rs.="<${typ}>" . htmlentities($val). "</${typ}>";
							break;
						default:
							$rs.="<${typ}>${val}</${typ}>";
					}
					break;
				default:
					break;
			}
			return $rs;
		}

		function serialize()
		{
			return $this->serializeval($this);
		}

		function serializeval($o)
		{
			// add check? slower, but helps to avoid recursion in serializing broken xmlrpcvals...
			//if (is_object($o) && (get_class($o) == 'xmlrpcval' || is_subclass_of($o, 'xmlrpcval')))
			//{
			$ar=$o->me;
			reset($ar);
			list($typ, $val) = each($ar);
			return '<value>' . $this->serializedata($typ, $val) . "</value>\n";
			//}
		}

		function structmemexists($m)
		{
			return array_key_exists($this->me['struct'][$m]);
		}

		function structmem($m)
		{
			return $this->me['struct'][$m];
		}

		function structreset()
		{
			reset($this->me['struct']);
		}

		function structeach()
		{
			return each($this->me['struct']);
		}

		function scalarval()
		{
			reset($this->me);
			list(,$b)=each($this->me);
			return $b;
		}

		function scalartyp()
		{
			reset($this->me);
			list($a,$b)=each($this->me);
			if($a == xmlrpcI4)
			{
				$a = xmlrpcInt;
			}
			return $a;
		}

		function arraymem($m)
		{
			return $this->me['array'][$m];
		}

		function arraysize()
		{
			return count($this->me['array']);
		}

		function structsize()
		{
			return count($this->me['struct']);
		}

		// DEPRECATED! this code looks like it is very fragile and has not been fixed
		// for a long long time. Shall we remove it for 2.0?
		function getval()
		{
			// UNSTABLE ?
			reset($this->me);
			list($a,$b)=each($this->me);
			// contributed by I Sofer, 2001-03-24
			// add support for nested arrays to scalarval
			// i've created a new method here, so as to
			// preserve back compatibility

			if(is_array($b))
			{
				foreach($b as $id => $cont)
				{
					$b[$id] = $cont->scalarval();
				}
			}

			// add support for structures directly encoding php objects
			if(is_object($b))
			{
				$t = get_object_vars($b);
				foreach($t as $id => $cont)
				{
					$t[$id] = $cont->scalarval();
				}

				foreach($t as $id => $cont)
				{
					@$b->$id = $cont;
				}
			}
			// end contrib
			return $b;
		}
	}
?>
