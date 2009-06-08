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

	class xmlrpcresp
	{
		var $val = 0;
		var $errno = 0;
		var $errstr = '';
		var $hdrs = array();

		/// @todo add check that $val is of correct type???
		function xmlrpcresp($val, $fcode = 0, $fstr = '')
		{
			if($fcode != 0)
			{
				// error
				$this->errno = $fcode;
				$this->errstr = $fstr;
				//$this->errstr = htmlspecialchars($fstr); // XXX: encoding probably shouldn't be done here; fix later.
			}
			elseif(!is_object($val) || (get_class($val) != 'xmlrpcval' && !is_subclass_of($val, 'xmlrpcval')))
			{
				// programmer error
				// TODO
				error_log("Invalid type '" . gettype($val) . "' (value: $val) passed to xmlrpcresp. Defaulting to empty value.");
				$this->val = new xmlrpcval();
			}
			else
			{
				// success
				$this->val = $val;
			}
		}

		function faultCode()
		{
			return $this->errno;
		}

		function faultString()
		{
			return $this->errstr;
		}

		function value()
		{
			return $this->val;
		}

		function serialize()
		{
			$result = "<methodResponse>\n";
			if($this->errno)
			{
				// G. Giunta 2005/2/13: let non-ASCII response messages be tolerated by clients
				$result .= '<fault>
<value>
<struct>
<member>
<name>faultCode</name>
<value><int>' . $this->errno . '</int></value>
</member>
<member>
<name>faultString</name>
<value><string>' . xmlrpc_encode_entities($this->errstr) . '</string></value>
</member>
</struct>
</value>
</fault>';
			}
			else
			{
				$result .= "<params>\n<param>\n" .
					$this->val->serialize() .
					"</param>\n</params>";
			}
			$result .= "\n</methodResponse>";
			return $result;
		}
	}
