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

	class xmlrpcval
	{
		var $me = array();
		var $mytype = 0;

		function xmlrpcval($val = -1, $type = '')
		{
			$this->me = array();
			$this->mytype = 0;

			if ($val != -1 || $type != '')
			{
				if ($type=='')
				{
					$type='string';
				}
				if ($GLOBALS['xmlrpcTypes'][$type]==1)
				{
					$this->addScalar($val,$type);
				}
				elseif ($GLOBALS['xmlrpcTypes'][$type]==2)
				{
					$this->addArray($val);
				}
				elseif ($GLOBALS['xmlrpcTypes'][$type]==3)
				{
					$this->addStruct($val);
				}
			}
		}

		function addScalar($val, $type='string')
		{
			if ($this->mytype==1)
			{
				echo '<B>xmlrpcval</B>: scalar can have only one value<BR>';
				return 0;
			}
			$typeof=$GLOBALS['xmlrpcTypes'][$type];
			if ($typeof!=1)
			{
				echo '<B>xmlrpcval</B>: not a scalar type ('.$typeof.')<BR>';
				return 0;
			}
		
			if ($type==xmlrpcBoolean)
			{
				if (strcasecmp($val,'true')==0 || 
					$val==1 || ($val==true && 
					strcasecmp($val,'false')))
				{
					$val=1;
				}
				else
				{
					$val=0;
				}
			}
		
			if ($this->mytype==2)
			{
				// we're adding to an array here
				$ar=$this->me['array'];
				$ar[] = CreateObject('phpgwapi.xmlrpcval',$val, $type);
				$this->me['array']=$ar;
			}
			else
			{
				// a scalar, so set the value and remember we're scalar
				$this->me[$type]=$val;
				$this->mytype=$typeof;
			}
			return 1;
		}

		function addArray($vals)
		{
			if ($this->mytype!=0)
			{
				echo '<B>xmlrpcval</B>: already initialized as a [' . $this->kindOf() . ']<BR>';
				return 0;
			}

			$this->mytype=$GLOBALS['xmlrpcTypes']['array'];
			$this->me['array']=$vals;
			return 1;
		}

		function addStruct($vals)
		{
//			global $xmlrpcTypes;
			if ($this->mytype!=0)
			{
				echo '<B>xmlrpcval</B>: already initialized as a [' . $this->kindOf() . ']<BR>';
				return 0;
			}
			$this->mytype=$GLOBALS['xmlrpcTypes']['struct'];
			$this->me['struct']=$vals;
			return 1;
		}

		function dump($ar)
		{
			reset($ar);
			while (list($key,$val) = each($ar))
			{
				echo $key.' => '.$val.'<br>';
				if ($key == 'array')
				{
					while (list($key2,$val2) = each($val))
					{
						echo '-- '.$key2.' => '.$val2.'<br>';
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
			if($typ)
			{
				switch($GLOBALS['xmlrpcTypes'][$typ])
				{
					case 3:
						// struct
						$rs .= '<struct>'."\n";
						reset($val);
						while(list($key2, $val2)=each($val))
						{
							$rs .= '<member><name>'.$key2.'</name>'."\n".$this->serializeval($val2).'</member>'."\n";
						}
						$rs .= '</struct>';
						break;
					case 2:
						// array
						$rs .= '<array>'."\n".'<data>'."\n";
						for($i=0; $i<sizeof($val); $i++)
						{
							$rs .= $this->serializeval($val[$i]);
						}
						$rs .= '</data>'."\n".'</array>';
						break;
					case 1:
						$rs .= '<'.$typ.'>';
						switch ($typ)
						{
							case xmlrpcBase64:
								$rs.= base64_encode($val);
								break;
							case xmlrpcBoolean:
								$rs.= ($val ? '1' : '0');
								break;
							case xmlrpcString:
								$rs.= htmlspecialchars($val);
								break;
							default:
								$rs.= $val;
						}
						$rs .= '</'.$typ.'>';
						break;
					default:
						break;
				}
			}
			return $rs;
		}

		function serialize()
		{
			return $this->serializeval($this);
		}

		function serializeval($o)
		{
			$rs='';
			$ar=$o->me;
			reset($ar);
			list($typ, $val) = each($ar);
			$rs.='<value>';
			$rs.=$this->serializedata($typ, $val);
			$rs.='</value>'."\n";
			return $rs;
		}

		function structmem($m)
		{
			$nv=$this->me['struct'][$m];
			return $nv;
		}

		function structreset()
		{
			reset($this->me['struct']);
		}

		function structeach()
		{
			return each($this->me['struct']);
		}

		function getval()
		{
			// UNSTABLE
			reset($this->me);
			list($a,$b)=each($this->me);
			// contributed by I Sofer, 2001-03-24
			// add support for nested arrays to scalarval
			// i've created a new method here, so as to
			// preserve back compatibility

			if (is_array($b))
			{
				@reset($b);
				while(list($id,$cont) = @each($b))
				{
					$b[$id] = $cont->scalarval();
				}
			}

			// add support for structures directly encoding php objects
			if (is_object($b))
			{
				$t = get_object_vars($b);
				@reset($t);
				while(list($id,$cont) = @each($t))
				{
					$t[$id] = $cont->scalarval();
				}
				@reset($t);
				while(list($id,$cont) = @each($t))
				{
					eval('$b->'.$id.' = $cont;');
				}
			}
			// end contrib
			return $b;
		}

		function scalarval()
		{
			reset($this->me);
			list($a,$b)=each($this->me);
			return $b;
		}

		function scalartyp()
		{
			reset($this->me);
			list($a,$b)=each($this->me);
			if ($a==xmlrpcI4) 
			{
				$a=xmlrpcInt;
			}
			return $a;
		}

		function arraymem($m)
		{
			$nv=@$this->me['array'][$m];
			return $nv;
		}

		function arraysize()
		{
			reset($this->me);
			list($a,$b)=each($this->me);
			return sizeof($b);
		}
	}
?>
