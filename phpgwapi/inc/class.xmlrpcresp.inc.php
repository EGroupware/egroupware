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
		var $xv = array();
		var $fn;
		var $fs = '';
		var $hdrs;

		function xmlrpcresp($val='', $fcode=0, $fstr='')
		{
			if ($fcode!=0)
			{
				$this->xv = 0;
				$this->fn = $fcode;
				$this->fs = htmlspecialchars($fstr);
			}
			else
			{
				if($val)
				{
					$this->xv = $val;
				}
				$this->fn = 0;
			}
		}

		function faultCode()
		{
			if (isset($this->fn)) 
			{
				return $this->fn;
			}
			else
			{
				return 0;
			}
		}

		function faultString()
		{
			return $this->fs;
		}

		function value()
		{
			return $this->xv;
		}

		function serialize()
		{
			$rs='<methodResponse>'."\n";
			if (isset($this->fn) && !empty($this->fn))
			{
				$rs .= '<fault>
  <value>
    <struct>
      <member>
        <name>faultCode</name>
        <value><int>' . $this->fn . '</int></value>
      </member>
      <member>
        <name>faultString</name>
        <value><string>' . $this->fs . '</string></value>
      </member>
    </struct>
  </value>
</fault>';
			}
			else
			{
				$rs .= '<params>'."\n".'<param>'."\n".@$this->xv->serialize().'</param>'."\n".'</params>';
			}
			$rs.="\n".'</methodResponse>';
			return $rs;
		}
	}
?>
