<?php

	if (!extension_loaded('xslt'))
	{
		if (PHP_OS == 'Windows' || PHP_OS == 'OS/2')
		{
			dl('php_xslt.dll');	
		}
		else
		{
			dl('xslt.so');
		}
	}

	require_once('class.xmltool.inc.php');

	class xslttemplates
	{
		var $rootdir = '';
		var $prev_rootdir = '';

		/* The xslfiles will be loaded up and merged into $xsldata */
		var $xslfiles = Array();
		var	$xsldata = '';
		
		/* Users can set $vars which will be converted into xmldata before xsl processing */
		/* Or they can generate their own XML data and set it directly when they have */
		/* need for a more robust schema */
		var $vars = Array();
		var $xmlvars = Array();
		var $xmldata = '';
		
		function xslttemplates($root = '.')
		{
			if(@isset($GLOBALS['phpgw_info']['flags']['printview']) && $GLOBALS['phpgw_info']['flags']['printview'] == True)
			{
				$this->print = True;
			}
			$this->set_root($root);
		}

		function halt($msg)
		{
			echo $msg;
			exit;
		}

		function set_root($rootdir)
		{
			if (!is_dir($rootdir))
			{
				$this->halt('set_root: '.$rootdir.' is not a directory.');
				return False;
			}
			$this->prev_rootdir = $this->rootdir;
			$this->rootdir = $rootdir;
			return True;
		}

		function reset_root()
		{
			$this->rootdir = $this->prev_rootdir;
		}

		function add_file($filename,$rootdir='',$time=1)
		{
			if (!is_array($filename))
			{
				if($rootdir=='')
				{
					$rootdir=$this->rootdir;
				}
				
				if (substr($filename, 0, 1) != '/')
				{
					$new_filename = $rootdir.'/'.$filename;
				}
				else
				{
					$new_filename = $filename;
				}
				
				if ($this->print && $time!=2 && $time!=4)
				{
					$new_filename = $new_filename.'_print';
				}

//				echo 'Rootdir: '.$rootdir.'<br>'."\n".'Filename: '.$filename.'<br>'."\n".'New Filename: '.$new_filename.'<br>'."\n";
				if (!file_exists($new_filename.'.xsl'))
				{
					switch($time)
					{
						case 2:
							$new_root = str_replace($GLOBALS['phpgw_info']['server']['template_set'],'default',$rootdir);
							$this->add_file($filename,$new_root,3);
							return;
							break;
						case 3:
							$this->add_file($filename,$rootdir,4);
							return;
							break;
						case 4:
							$this->halt("filename: file $new_filename.xsl does not exist.");
							break;
						default:
							if (!$this->print)
							{
								$new_root = str_replace($GLOBALS['phpgw_info']['server']['template_set'],'default',$rootdir);
								$this->add_file($filename,$new_root,4);
								return;
							}
							else
							{
								$this->add_file($filename,$rootdir,2);
								return;
							}
					}
				}
				else
				{
					$this->xslfiles[$filename] = $new_filename.'.xsl';
				}
			}
			else
			{
				reset($filename);
				while(list(,$file) = each($filename))
				{
					$this->add_file($file);
				}
			}
		}

		function set_var($name, $value, $append = False)
		{
			if($append)
			{
				//_debug_array($value);
				if (is_array($value))
				{
					while(list($key,$val) = each($value))
					{
						$this->vars[$name][$key] = $val;
					}
				}
			}
			else
			{
				$this->vars[$name] = $value;
			}
		}
	
		function set_xml($xml, $append = False)
		{
			if(!$append)
			{
				$this->xmlvars = $xml;
			}
			else
			{
				$this->xmlvars .= $xml;
			}
		}

		function get_var($name)
		{
			return $this->vars[$name];
		}
		
		function get_vars()
		{
			return $this->vars;
		}

		function get_xml()
		{
			return $this->xmlvars;
		}

		function xsl_parse()
		{
			if(count($this->xslfiles) > 0)
			{
				$this->xsldata = '<?xml version="1.0" encoding="' . lang('charset') . '"?>'."\n";
				$this->xsldata .= '<!DOCTYPE xsl:stylesheet ['."\n";
				$this->xsldata .= '<!ENTITY nl "&#10;">'."\n";
				$this->xsldata .= '<!ENTITY nbsp "&#160;">'."\n";
				$this->xsldata .= ']>'."\n";
				$this->xsldata .= '<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">'."\n";
				$this->xsldata .= '<xsl:output method="html" version="1.0" encoding="' . lang('charset') . '" indent="yes" omit-xml-declaration="yes" doctype-public="-//W3C/DTD XHTML 1.0 Transitional//EN" doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" standalone="yes" media-type="text/html"/>'."\n";
				$this->xsldata .= '<xsl:template match="/">'."\n";
				$this->xsldata .= "\t".'<xsl:apply-templates select="PHPGW"/>'."\n";
				$this->xsldata .= '</xsl:template>'."\n";
				reset($this->xslfiles);
				while(list($dummy,$xslfile) = each($this->xslfiles))
				{
//					echo 'XSLFILES: '.$dummy.'<br>'."\n".'XSL File: '.$xslfile.'<br>'."\n";
					$fd = fopen ($xslfile, "r");
					$this->xsldata .= fread($fd, filesize($xslfile));
					fclose ($fd);
				}
				$this->xsldata .= '</xsl:stylesheet>'."\n";
			}
			else
			{
				echo 'Error: No XSL files have been selected';
				exit;
			}
			return $this->xsldata;
		}

		function xml_parse()
		{
			$this->xmldata = '';
			$xmlvars = $this->xmlvars;

			$xmldata = $this->vars;
			
			/* auto generate xml based on vars */
			
			while(list($key,$value) = each($xmlvars))
			{
				$xmldata[$key] = $value;
			}
			//$tmpxml_object = var2xml('PHPGW',$xmldata);
			//$this->xmldata = $tmpxml_object->dump_mem();
			//return $this->xmldata;
			$this->xmldata = var2xml('PHPGW',$xmldata);
			return $this->xmldata;
		}

		function list_lineno($xml)
		{
			$xml = explode("\n",$xml);

			echo "<pre>\n";
			for ($n=1; isset($xml[$n]); ++$n)
			{
				echo "$n: ".htmlentities($xml[$n])."\n";
			}
			echo "</pre>\n";
		}

		function parse($parsexsl = True, $parsexml = True)
		{
			if($parsexsl)
			{
				$this->xsl_parse();
			}
			if($parsexml)
			{
				$this->xml_parse();
			}
			$xsltproc = xslt_create();

			$minor = explode(".",phpversion());
			if($minor[1] >= 1) // PHP 4.1.x -- preferred
			{
				$arguments = array('/_xml' => $this->xmldata, '/_xsl' => $this->xsldata);
				$html = xslt_process($xsltproc,'arg:/_xml','arg:/_xsl',NULL,$arguments);
			}
			else // PHP 4.0.6 -- works okay
			{
				xslt_process($this->xsldata, $this->xmldata,$html);
			}
			if (!$html)
			{
				echo "<p>xml-data = ";  $this->list_lineno($this->xmldata);
				echo "<p>xsl-data = "; $this->list_lineno($this->xsldata);
				die(/*$this->xsldata.*/"\n\n XSLT processing error: ".xslt_error($xsltproc));
			}
			xslt_free($xsltproc);
			return $html;
		}

		function pparse()
		{
			print $this->parse();
			return False;
		}
		function pp()
		{
			return $this->pparse();
		}
	}
?>
