<?php
  /**************************************************************************\
  * phpGroupWare API - WebDAV                                                *
  * This file written by Jonathon Sim (for Zeald Ltd) <jsim@free.net.nz>     *
  * Provides methods for manipulating an RFC 2518 DAV repository             *
  * Copyright (C) 2002 Zeald Ltd                                             *
  * -------------------------------------------------------------------------*
  * This library is part of the phpGroupWare API                             *
  * http://www.phpgroupware.org/api                                          *
  * ------------------------------------------------------------------------ *
  * This library is free software; you can redistribute it and/or modify it  *
  * under the terms of the GNU Lesser General Public License as published by *
  * the Free Software Foundation; either version 2.1 of the License,         *
  * or any later version.                                                    *
  * This library is distributed in the hope that it will be useful, but      *
  * WITHOUT ANY WARRANTY; without even the implied warranty of               *
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     *
  * See the GNU Lesser General Public License for more details.              *
  * You should have received a copy of the GNU Lesser General Public License *
  * along with this library; if not, write to the Free Software Foundation,  *
  * Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            *
  \**************************************************************************/

  /* $Id$ */

  /*At the moment much of this is simply a wrapper around the NET_HTTP_Client class,
  with some other methods for parsing the returned XML etc
  Ideally this will eventually use groupware's inbuilt HTTP class
  */

  define ('DEBUG_DAV_CLIENT', 0);
  define ('DEBUG_DAV_XML', 0);
  define ('DEBUG_CACHE', 0);


##############################################################
# 'Private' classes - these are only used internally and should
# not be used by external code
##############################################################

	/*
	PHP STILL doesnt have any sort of stable DOM parser.  So lets make our
	own XML parser, that parses XML into a tree of arrays (I know, it could do
	something resembling DOM, but it doesnt!)
	*/
	class xml_tree_parser
	{
		var $namespaces;
		var $current_element;
		var $num = 1;
		var $tree = NULL;

		/*
		This is the only end-user function in the class.  Call parse with an XML string, and
		you will get back the tree of 'element' arrays
		*/
		function parse($xml_string)
		{
			$this->xml_parser = xml_parser_create();
			xml_set_element_handler($this->xml_parser,array(&$this,"start_element"),array(&$this,"end_element"));
			xml_set_character_data_handler($this->xml_parser,array(&$this,"parse_data"));

			$this->parser_result=array();
			$this->xml = $xml_string;
			xml_parse($this->xml_parser,$xml_string);
			xml_parser_free($this->xml_parser);
			if (DEBUG_DAV_XML) 
			{
				echo '<pre>'.htmlentities($xml_string).'</pre>';
				echo 'parsed to:' ; $this->print_tree();
			}
			return $this->tree;
		}
		
		//a useful function for debug output - will print the tree after parsing
		function print_tree($element=NULL, $prefix='|', $processed=array()) 
		{	
		
			if ($processed[$element['id']])
			{
				echo '<b>***RECURSION!!***</b></br>';	
				die();
			}
			else
			{
				$processed[$element['id']] = true;
			}
			
			if ($element == NULL)
			{
				$element = $this->tree;
			}
			echo $prefix.$element['namespace'].':'.$element['name'].' '.$element['start'].'->'.$element['end'].'<br>';
			$prefix .= '-->';
			if ($element['data'])
			{
				echo $prefix.$element['data'].'<br>';
			}

			foreach ($element['children'] as $id=>$child)
			{
				$this->print_tree($child, $prefix, &$processed);
			}
			
		}
		
		//called by the xml parser at the start of an element, it creates that elements node in the tree
		function start_element($parser,$name,$attr)
		{
			
			if (preg_match('/(.*):(.*)/', $name, $matches))
			{
				$ns = $this->namespaces[$matches[1]];
				$element = $matches[2];
			}
			else
			{
				$element = $name;
			}
			
			if ($this->tree == NULL) 
			{
				$this->tree = array(
					'namespace'=>$ns,
					'name' => $element,
					'attributes' => $attr,
					'data' => '',
					'children' => array(),
					'parent' => NULL,
					'type' => 'xml_element',
					'id' => $this->num
					);
				$this->current_element = &$this->tree;
			}
			else
			{	
				$parent = &$this->current_element;	
				$parent['children'][$this->num]=array(
					'namespace'=>$ns,
					'name' => $element,
					'attributes' => $attr,
					'data' => '',
					'children' => array(),
					'parent' => &$parent,
					'type' => 'xml_element',
					'id' => $this->num
					);
				$this->current_element = &$parent['children'][$this->num];				
			}
			$this->num++;
			$this->current_element['start'] = xml_get_current_byte_index($parser);
			foreach ($attr as $name => $value)
			{
				if (ereg('^XMLNS:(.*)', $name, $matches) )
				{
					$this->namespaces[$matches[1]] = $value;
				}
			}
		}

		//at the end of an element, stores the start and end positions in the xml stream, and moves up the tree
		function end_element($parser,$name)
		{
			$curr = xml_get_current_byte_index($parser);
			$this->current_element['end'] =strpos($this->xml, '>', $curr);
			$this->current_element = &$this->current_element['parent'];		
			
		}

		//if there is a CDATA element, puts it into the parent elements node
		function parse_data($parser,$data)
		{
			$this->current_element['data']=$data;
		}
		
	}
	
	/*This class uses a bunch of recursive functions to process the DAV XML tree
	digging out the relevent information and putting it into an array
	*/
	class dav_processor
	{
		function dav_processor($xml_string)
		{
			$this->xml = $xml_string;
			$this->dav_parser = new xml_tree_parser();
			$this->tree = $this->dav_parser->parse($xml_string);
			
		}
		
		function process_tree(&$element, &$result_array)
		{
	
			//This lets us mark a node as 'done' and provides protection against infinite loops
			if ($this->processed[$element['id']])
			{
				return $result_array;	
			}
			else
			{
				$this->processed[$element['id']] = true;
			}
			
			if ( $element['namespace'] == 'DAV:')
			{
				if ($element['name'] == 'RESPONSE')
				{
						$result = array( 		
							'size' => 0,
							'getcontenttype' => 'application/octet-stream',
							'is_dir' => 0
					);
					foreach ($element['children'] as $id=>$child)
					{
						$this->process_properties($child, $result);
					}
					$result_array[$result['full_name']] = $result;
					
				}
			}
			// ->recursion
			foreach ($element['children'] as $id=>$child)
			{
				$this->process_tree($child, $result_array);
			}
			return $result_array;
		}
		
		function process_properties($element, &$result_array)
		{	
			if ($this->processed[$element['id']])
			{
				return $result_array;	
			}
			else
			{
				$this->processed[$element['id']] = true;
			}

			if ( $element['namespace'] == 'DAV:')
			{
				switch ($element['name'])
				{
					case 'HREF':
						$string = $element['data'];
						$idx=strrpos($string,SEP);
						if($idx && $idx==strlen($string)-1)
						{
							$this->current_ref=substr($string,0,$idx);
						}
						else
						{
							$this->current_ref=$string;
						}
						$result_array['name']=basename($string);
						$result_array['directory']=dirname($string);
						$result_array['full_name'] = $this->current_ref;
					break;
					case 'SUPPORTEDLOCK':
						if (count($element['children'])) //There are active locks
						{
							$result_array['supported_locks'] = array();
							foreach ($element['children'] as $id=>$child)
							{
								$this->process_properties($child, $result_array['supported_locks']);
							}
						}	
					break;
					case 'LOCKDISCOVERY':
						if (count($element['children'])) //There are active locks
						{
							$result_array['locks'] = array();
							foreach ($element['children'] as $id=>$child)
							{
								$this->process_properties($child, $result_array['locks']);
							}
					}	
					break;
					case 'LOCKENTRY':
						if (count($element['children'])) 
						{
							$result_array[$element['id']] = array();
							foreach ($element['children'] as $id=>$child)
							{
								$this->process_properties($child, $result_array[$element['id']] );
							}
					}	
					break;
					case 'ACTIVELOCK':
						if (count($element['children'])) 
						{
							$result_array[$element['id']] = array();
							foreach ($element['children'] as $id=>$child)
							{
								$this->process_properties($child, $result_array[$element['id']] );
							}
					}	
					break;
					case 'OWNER':
						$result_array['owner'] = array();

						foreach ($element['children'] as $child) 
						{
							$this->process_verbatim($child, &$result_array['owner'][]);							
						}
						
						//print_r($element);die();
						//die();
						$result_array['owner_xml'] = substr($this->xml, $element['start'], $element['end']-$element['start']+1);
						return $result_array; //No need to process this branch further
					break;	
					case 'LOCKTOKEN':
						if (count($element['children'])) 
						{
							
							foreach ($element['children'] as $id=>$child)
							{
								$this->process_properties($child, $tmp_result , $processed);
								$result_array['lock_tokens'][$tmp_result['full_name']] = $tmp_result;
							}
					}	
					break;
					case 'LOCKTYPE':
						$child = end($element['children']);
						if ($child) 
						{
							$this->processed[$child['id']] = true;
							$result_array['locktype'] = $child['name'];
						}
					break;
					case 'LOCKSCOPE':
						$child = end($element['children']);
						if ($child) 
						{
							$this->processed[$child['id']] = true;
							$result_array['lockscope'] = $child['name'];
						}
					break;
					default:
						if (trim($element['data']))
						{
							$result_array[strtolower($element['name'])] = $element['data'];
						}
				}
			}
			else
			{
				if (trim($element['data']))
				{
					$result_array[strtolower($element['name'])] = $element['data'];
				}
			}

			foreach ($element['children'] as $id=>$child)
			{
				$this->process_properties($child, $result_array);
			}
			
			return $result_array;
		}	
		
		function process_verbatim($element, &$result_array)
		{	
			if ($this->processed[$element['id']])
			{
				return $result_array;	
			}
			else
			{
				$this->processed[$element['id']] = true;
			}
			
			foreach ( $element as $key => $value)
			{
				//The parent link is death to naive programmers (eg me) :)
				if (!( $key == 'children' || $key == 'parent') )
				{
					$result_array[$key] = $value;
				}
			}
			$result_array['children'] = array();		
			foreach ($element['children'] as $id=>$child)
			{
				echo 'processing child:';
				$this->process_verbatim($child, $result_array['children']);
			}
			return $result_array;
		}
	}
	
#####################################################
#This is the actual public interface of this class
#####################################################
	class http_dav_client 
	{
		var $attributes=array();
		var $vfs_property_map = array();
		var $cached_props =  array();
		function http_dav_client()
		{
			$this->http_client = CreateObject('phpgwapi.net_http_client');
			$this->set_debug(0);
		}
		
		//TODO:  Get rid of this
		//A quick, temporary debug output function
		function debug($info) {

			if (DEBUG_DAV_CLIENT)
			{
				echo '<b> http_dav_client debug:<em> ';
				if (is_array($info))
				{
					print_r($info);
				}
				else
				{
					echo $info;
				}
				echo '</em></b><br>';
			}
		}
		/*!
		@function glue_url
		@abstract glues a parsed url (ie parsed using PHP's parse_url) back
			together
		@param $url	The parsed url (its an array)
		*/
		
		function glue_url ($url){
			if (!is_array($url))
			{
				return false;
			}
			// scheme
			$uri = (!empty($url['scheme'])) ? $url['scheme'].'://' : '';
			// user & pass
			if (!empty($url['user']))
			{
				$uri .= $url['user'];
				if (!empty($url['pass']))
				{
					$uri .=':'.$url['pass'];
				}
				$uri .='@'; 
			}
			// host 
			$uri .= $url['host'];
			// port 
			$port = (!empty($url['port'])) ? ':'.$url['port'] : '';
			$uri .= $port; 
			// path 
			$uri .= $url['path'];
			// fragment or query
			if (isset($url['fragment']))
			{
				$uri .= '#'.$url['fragment'];
			} elseif (isset($url['query']))
			{
				$uri .= '?'.$url['query'];
			}
			return $uri;
		}	
		
		/*!
		@function encodeurl
		@abstract encodes a url from its "display name" to something the dav server will accept
		@param uri The unencoded uri
		@discussion
			Deals with "url"s which may contain spaces and other unsavoury characters,
			by using appropriate %20s
		*/			
		function encodeurl($uri)
		{
			$parsed_uri =  parse_url($uri);
			if (empty($parsed_uri['scheme']))
			{
				$path = $uri;
			}
			else
			{
				$path = $parsed_uri['path'];
			}
			$fixed_array = array();
			foreach (explode('/', $path) as $name)
			{
				$fixed_array[] = rawurlencode($name);
			}
			$fixed_path = implode('/', $fixed_array);
			if (!empty($parsed_uri['scheme']))
			{
				$parsed_uri['path'] = $fixed_path;
				$newuri = $this->glue_url($parsed_uri);
			}
			else
			{
				$newuri = $fixed_path;
			}			
			return $newuri;
			
		}
		/*!
		@function decodeurl
		@abstract decodes a url to its "display name"
		@param uri The encoded uri
		@discussion
			Deals with "url"s which may contain spaces and other unsavoury characters,
			by using appropriate %20s
		*/		
		function decodeurl($uri)
		{
			$parsed_uri =  parse_url($uri);
			if (empty($parsed_uri['scheme']))
			{
				$path = $uri;
			}
			else
			{
				$path = $parsed_uri['path'];
			}
			$fixed_array = array();
			foreach (explode('/', $path) as $name)
			{
				$fixed_array[]  = rawurldecode($name);
			}
			$fixed_path = implode('/', $fixed_array);
			if (!empty($parsed_uri['scheme']))
			{
				$parsed_uri['path'] = $fixed_path;
				$newuri = $this->glue_url($parsed_uri);
			}
			else
			{
				$newuri = $fixed_path;
			}			
			return $newuri;
			
		}
		/*!
		@function set_attributes
		@abstract Sets the "attribute map"
		@param attributes Attributes to extract "as-is" from the DAV properties
		@param dav_map A mapping of dav_property_name => attribute_name for attributes 
			with different names in DAV and the desired name space.
		@discussion
			This is mainly for use by VFS, where the VFS attributes (eg size) differ
			from the corresponding DAV ones ("getcontentlength")
		*/
		function set_attributes($attributes, $dav_map)
		{
			$this->vfs_property_map = $dav_map;
			$this->attributes = $attributes;
		}
		
		/*!
		@function set_credentials
		@abstract Sets authentication credentials for HTTP AUTH
		@param username The username to connect with
		@param password The password to connect with
		@discussion
			The only supported authentication type is "basic"
		*/
		function set_credentials( $username, $password )
		{
			$this->http_client->setCredentials($username, $password );
		}

		/*!
		@function connect
		@abstract connects to the server
		@param dav_host The host to connect to
		@param dav_port The port to connect to
		@discussion
			If the server requires authentication you will need to set credentials
			with set_credentials first
		*/

		function connect($dav_host,$dav_port)
		{
			$this->dav_host = $dav_host;
			$this->dav_port = $dav_port;
			$this->http_client->addHeader('Host',$this->dav_host);
			$this->http_client->addHeader('Connection','close');
			//$this->http_client->addHeader('transfer-encoding','identity');
	//		$this->http_client->addHeader('Connection','keep-alive');
	//		$this->http_client->addHeader('Keep-Alive','timeout=20, state="Accept,Accept-Language"');
			$this->http_client->addHeader('Accept-Encoding','chunked');
			$this->http_client->setProtocolVersion( '1.1' );
			$this->http_client->addHeader( 'user-agent', 'Mozilla/5.0 (compatible; PHPGroupware dav_client/1; Linux)');
			return $this->http_client->Connect($dav_host,$dav_port);
		}
		function set_debug($debug)
		{
			$this->http_client->setDebug($debug);
		}

		/*!
		@function disconnect
		@abstract disconnect from the server
		@discussion
			When doing HTTP 1.1 we frequently close/reopen the connection
			anyway, so this function needs to be called after any other DAV calls
			(since if they find the connection closed, they just reopen it)
		*/

		function disconnect()
		{
			$this->http_client->Disconnect();
		}
		
		/*!
		@function get_properties
		@abstract a high-level method of getting DAV properties
		@param url The URL to get properties for
		@param scope the 'depth' to recuse subdirectories (default 1)
		@param sorted whether we should sort the rsulting array (default True)
		@result array of file->property arra
		@discussion
			This function performs all the necessary XML parsing etc to convert DAV properties (ie XML nodes)
			into associative arrays of properties - including doing mappings
			from DAV property names to any desired property name format (eg the VFS one)
			This is controlled by the attribute arrays set in the set_attributes function.
		*/
		function get_properties($url,$scope=1){
			$request_id = $url.'//'.$scope.'//'.$sorted; //A unique id for this request (for caching)
			if ($this->cached_props[$request_id])
			{
if (DEBUG_CACHE) echo'Cache hit : cache id:'.$request_id;
				return $this->cached_props[$request_id];
			}
			else if (! $sorted && $this->cached_props[$url.'//'.$scope.'//1'])
			{
if (DEBUG_CACHE) echo ' Cache hit : cache id: '.$request_id;
				return $this->cached_props[$url.'//'.$scope.'//1'];
			}
if (DEBUG_CACHE) 
{
	echo ' <b>Cache miss </b>: cache id: '.$request_id;
/*	echo " cache:<pre>";
	print_r($this->cached_props);
	echo '</pre>';*/
}
	

			if($this->propfind($url,$scope) != 207)
			{
				if($this->propfind($url.'/',$scope) != 207)
				{
					return array();
				}
			}
			$xml_result=$this->http_client->getBody();
			$result_array = array();
			$dav_processor = new dav_processor($xml_result);
			$tmp_list = $dav_processor->process_tree($dav_processor->tree, $result_array);

			foreach($tmp_list as $name=>$item) {
				$fixed_name = $this->decodeurl($name);
				$newitem = $item;
				$newitem['is_dir']= ($item['getcontenttype'] =='httpd/unix-directory' ? 1 : 0);
				$item['directory'] = $this->decodeurl($item['directory']);
				//Since above we sawed off the protocol and host portions of the url, lets readd them.
				if (strlen($item['directory'])) {
					$path = $item['directory'];
					$host = $this->dav_host;
					$newitem['directory'] = $host.$path;
				}

				//Get any extra properties that may share the vfs name
				foreach ($this->attributes as $num=>$vfs_name)
				{
					if ($item[$vfs_name])
					{
						$newitem[$vfs_name] = $item[$vfs_name];
					}
				}

				//Map some DAV properties onto VFS ones.
				foreach ($this->vfs_property_map as $dav_name=>$vfs_name)
				{
					if ($item[$dav_name])
					{
						$newitem[$vfs_name] = $item[$dav_name];
					}
				}
				
				if ($newitem['is_dir'] == 1)
				{
					$newitem['mime_type']='Directory';
				}
				
				$this->debug('<br><br>properties:<br>');
				$this->debug($newitem);
				$newitem['name'] = $this->decodeurl($newitem['name']);
				$result[$fixed_name]=$newitem;
				if ($newitem['is_dir']==1)
				{
					$this->cached_props[$name.'//0//1'] = array($fixed_name=>$newitem);
				}
				else
				{
					$this->cached_props[$name.'//1//1'] = array($fixed_name=>$newitem);
				}
			}
			if ($sorted)
			{
				ksort($result);
			}
			$this->cached_props[$request_id] = $result;
			return $result;
		}
		
		function get($uri)
		{
			$uri = $this->encodeurl($uri);
			return $this->http_client->Get($uri);
		}

		/*!
		@function get_body
		@abstract return the response body
		@result string body content
		@discussion
			invoke it after a Get() call for instance, to retrieve the response
		*/
		function get_body()
		{
			return $this->http_client->getBody();
		}

		/*!
		@function get_headers
		@abstract return the response headers
		@result array headers received from server in the form headername => value
		@discussion
			to be called after a Get() or Head() call
		*/
		function get_headers()
		{
			return $this->http_client->getHeaders();
		}

		/*!
		@function copy
		@abstract PUT is the method to sending a file on the server.
		@param uri the location of the file on the server. dont forget the heading "/"
		@param data the content of the file. binary content accepted
		@result string response status code 201 (Created) if ok
		*/
		function put($uri, $data, $token='')
		{
		$uri = $this->encodeurl($uri);
if (DEBUG_CACHE) echo '<b>cache cleared</b>';
		if (strlen($token)) 
		{
			$this->http_client->addHeader('If', '<'.$uri.'>'.' (<'.$token.'>)');
		}

		$this->cached_props = array();
		$result = $this->http_client->Put($uri, $data);
		$this->http_client->removeHeader('If');
		return $result;
		}
		
		/*!
		@function copy
		@abstract Copy a file -allready on the server- into a new location
		@param srcUri the current file location on the server. dont forget the heading "/"
		@param destUri the destination location on the server. this is *not* a full URL
		@param overwrite boolean - true to overwrite an existing destination - overwrite by default
		@result Returns the HTTP status code
		@discussion
			returns response status code 204 (Unchanged) if ok
		*/
		function copy( $srcUri, $destUri, $overwrite=true, $scope=0, $token='')
		{
			$srcUri = $this->encodeurl($srcUri);
			$destUri = $this->encodeurl($destUri);
if (DEBUG_CACHE) echo '<b>cache cleared</b>';
			if (strlen($token)) 
			{
				$this->http_client->addHeader('If', '<'.$uri.'>'.' (<'.$token.'>)');
			}
			$this->cached_props = array();
			$result = $this->http_client->Copy( $srcUri, $destUri, $overwrite, $scope);
			$this->http_client->removeHeader('If');
			return $result;
		}

		/*!
		@function move
		@abstract Moves a WEBDAV resource on the server
		@param srcUri the current file location on the server. dont forget the heading "/"
		@param destUri the destination location on the server. this is *not* a full URL
		@param overwrite boolean - true to overwrite an existing destination (default is yes)
		@result Returns the HTTP status code
		@discussion
			returns response status code 204 (Unchanged) if ok
		*/
		function move( $srcUri, $destUri, $overwrite=true, $scope=0, $token='' )
		{
			$srcUri = $this->encodeurl($srcUri);
			$destUri = $this->encodeurl($destUri);
if (DEBUG_CACHE) echo '<b>cache cleared</b>';
			if (strlen($token)) 
			{
				$this->http_client->addHeader('If', '<'.$uri.'>'.' (<'.$token.'>)');
			}
			$this->cached_props = array();
			$result = $this->http_client->Move( $srcUri, $destUri, $overwrite, $scope);
			$this->http_client->removeHeader('If');
			return $result;
		}

		/*!
		@function delete
		@abstract Deletes a WEBDAV resource
		@param uri The URI we are deleting
		@result Returns the HTTP status code
		@discussion
			returns response status code 204 (Unchanged) if ok
		*/
		function delete( $uri, $scope=0, $token='')
		{
			$uri = $this->encodeurl($uri);
if (DEBUG_CACHE) echo '<b>cache cleared</b>';
			if (strlen($token)) 
			{
				$this->http_client->addHeader('If', '<'.$uri.'>'.' (<'.$token.'>)');
			}
			
			$this->cached_props = array();
			$result = $this->http_client->Delete( $uri, $scope);
			$this->http_client->removeHeader('If');
			return $result;
		}
		
		/*!
		@function mkcol
		@abstract Creates a WEBDAV collection (AKA a directory)
		@param uri The URI to create
		@result Returns the HTTP status code
		*/
		function mkcol( $uri, $token='' )
		{
			$uri = $this->encodeurl($uri);
if (DEBUG_CACHE) echo '<b>cache cleared</b>';
			if (strlen($token)) 
			{
				$this->http_client->addHeader('If', '<'.$uri.'>'.' (<'.$token.'>)');
			}
			$this->cached_props = array();
			return $this->http_client->MkCol( $uri );
			$this->http_client->removeHeader('If');
		}

		/*!
		@function propfind
		@abstract Queries WEBDAV properties
		@param uri uri of resource whose properties we are changing
		@param scope Specifies how "deep" to search (0=just this file/dir 1=subfiles/dirs etc)
		@result Returns the HTTP status code
		@discussion
			to get the result XML call get_body()
		*/
		function propfind( $uri, $scope=0 )
		{
			$uri = $this->encodeurl($uri);
			return $this->http_client->PropFind( $uri, $scope);
		}
		/*!
		@function proppatch
		@abstract Sets DAV properties
		@param uri uri of resource whose properties we are changing
		@param attributes An array of attributes and values.
		@param namespaces Extra namespace definitions that apply to the properties
		@result Returns the HTTP status code
		@discussion
			To make DAV properties useful it helps to use a well established XML dialect
			such as the "Dublin Core"

		*/
		function proppatch($uri, $attributes,  $namespaces='', $token='')
		{
			$uri = $this->encodeurl($uri);
if (DEBUG_CACHE) echo '<b>cache cleared</b>';
			if (strlen($token)) 
			{
				$this->http_client->addHeader('If', '<'.$uri.'>'.' (<'.$token.'>)');
			}
			$this->cached_props = array();
			//Begin evil nastiness
			$davxml = '<?xml version="1.0" encoding="utf-8" ?>
<D:propertyupdate xmlns:D="DAV:"';

			if ($namespaces)
			{
				$davxml .= ' ' . $namespaces;
			}
			$davxml .= ' >';
			foreach ($attributes as $name => $value)
			{
				$davxml .= '
  <D:set>
    <D:prop>
       <'.$name.'>'.utf8_encode(htmlspecialchars($value)).'</'.$name.'>
    </D:prop>
  </D:set>
';
			}
			$davxml .= '
</D:propertyupdate>';

			if (DEBUG_DAV_XML) {
				echo '<b>send</b><pre>'.htmlentities($davxml).'</pre>';
			}
			$this->http_client->requestBody = $davxml;
			if( $this->http_client->sendCommand( 'PROPPATCH '.$uri.' HTTP/1.1' ) )
			{
				$this->http_client->processReply();
			}

			if (DEBUG_DAV_XML) {
				echo '<b>Recieve</b><pre>'.htmlentities($this->http_client->getBody()).'</pre>';
			}
			$this->http_client->removeHeader('If');
			return $this->http_client->reply;
		}
		
		/*!
		@function unlock
		@abstract unlocks a locked resource on the DAV server
		@param uri uri of the resource we are unlocking
		@param a 'token' for the lock (to get the token, do a propfind)
		@result true if successfull
		@discussion
			Not all DAV servers support locking (its in the RFC, but many common
			DAV servers only implement "DAV class 1" (no locking)
		*/	
				
		function unlock($uri, $token)
		{
			$uri = $this->encodeurl($uri);
if (DEBUG_CACHE) echo '<b>cache cleared</b>';
			$this->cached_props = array();
			$this->http_client->addHeader('Lock-Token', '<'.$token.'>');
			$this->http_client->sendCommand( 'UNLOCK '.$uri.' HTTP/1.1');
			$this->http_client->removeHeader('Lock-Token');
			$this->http_client->processReply();
			if ( $this->http_client->reply  == '204')
			{
				return true;
			}
			else
			{
				$headers = $this->http_client->getHeaders();
				echo $this->http_client->getBody();
				if ($headers['Content-Type'] == 'text/html')
				{
					echo $this->http_client->getBody();
					die();
				}
				else
				{
					return false;
				}
			}
		}
		
		/*!
		@function lock
		@abstract locks a resource on the DAV server
		@param uri uri of the resource we are locking
		@param owner the 'owner' information for the lock (purely informative)
		@param depth the depth to which we lock collections
		@result true if successfull
		@discussion
			Not all DAV servers support locking (its in the RFC, but many common
			DAV servers only implement "DAV class 1" (no locking)
		*/	
		function lock($uri, $owner, $depth=0, $timeout='infinity')
		{
			$uri = $this->encodeurl($uri);
if (DEBUG_CACHE) echo '<b>cache cleared</b>';
			$this->cached_props = array();
			$body = "<?xml version=\"1.0\" encoding=\"utf-8\" ?>
<D:lockinfo xmlns:D='DAV:'>
<D:lockscope><D:exclusive/></D:lockscope>\n<D:locktype><D:write/></D:locktype>
	<D:owner><D:href>$owner</D:href></D:owner>
</D:lockinfo>\n";
		
		$this->http_client->requestBody = utf8_encode( $body );
		$this->http_client->addHeader('Depth', $depth);
		if (! (strtolower(trim($timeout)) == 'infinite'))
		{
			$timeout = 'Second-'.$timeout;
		}
		$this->http_client->addHeader('Timeout', $timeout);
		
		if( $this->http_client->sendCommand( "LOCK $uri HTTP/1.1" ) )
			$this->http_client->processReply();
			$this->http_client->removeHeader('timeout');
			if ( $this->http_client->reply  == '200')
			{
				return true;
			}
			else
			{
				$headers = $this->http_client->getHeaders();
				echo $this->http_client->getBody();
				return false;

			}

		}
		/*!
		@function options
		@abstract determines the optional HTTP features supported by a server
		@param uri uri of the resource we are seeking options for (or * for the whole server)
		@result Returns an array of option values
		@discussion
			Interesting options include "ACCESS" (whether you can read a file) and
			DAV (DAV features)

		*/
		function options($uri)
		{
			$uri = $this->encodeurl($uri);
			if( $this->http_client->sendCommand( 'OPTIONS '.$uri.' HTTP/1.1' ) == '200' )
			{
				$this->http_client->processReply();
				$headers = $this->http_client->getHeaders();
				return $headers;
			}
			else
			{
				return False;
			}
		}
		/*!
		@function dav_features
		@abstract determines the features of a DAV server
		@param uri uri of resource whose properties we are changing
		@result Returns an array of option values
		@discussion
			Likely return codes include NULL (this isnt a dav server!), 1 
			(This is a dav server, supporting all standard DAV features except locking)
			2, (additionally supports locking (should also return 1)) and 
			'version-control' (this server supports versioning extensions for this resource)
		*/		
		function dav_features($uri)
		{
			$uri = $this->encodeurl($uri);
			$options = $this->options($uri);
			$dav_options = $options['DAV'];
			if ($dav_options)
			{
				$features=explode(',', $dav_options);
			}
			else
			{
				$features = NULL;
			}
			return $features;
		}
/**************************************************************
 RFC 3253 DeltaV versioning extensions 
 **************************************************************
 These are 100% untested, and almost certainly dont work yet...
 eventually they will be made to work with subversion...
 */
	
		/*!
		@function report
		@abstract Report is a kind of extended PROPFIND - it queries properties accros versions etc
		@param uri uri of resource whose properties we are changing
		@param report the type of report desired eg DAV:version-tree, DAV:expand-property etc (see http://greenbytes.de/tech/webdav/rfc3253.html#METHOD_REPORT)
		@param namespace any extra XML namespaces needed for the specified properties
		@result Returns an array of option values
		@discussion
			From the relevent RFC:
			"A REPORT request is an extensible mechanism for obtaining information about
			a resource. Unlike a resource property, which has a single value, the value 
			of a report can depend on additional information specified in the REPORT 
			request body and in the REPORT request headers."
		*/		
		function report($uri, $report, $properties,  $namespaces='')
		{
			$uri = $this->encodeurl($uri);
			$davxml = '<?xml version="1.0" encoding="utf-8" ?>
<D:'.$report . 'xmlns:D="DAV:"';
			if ($namespaces)
			{
				$davxml .= ' ' . $namespaces;
			}
			$davxml .= ' >
	<D:prop>';
			foreach($properties as $property) 
			{
				$davxml .= '<'.$property.'/>\n';
			}
			$davxml .= '\t<D:/prop>\n<D:/'.$report.'>';		
			if (DEBUG_DAV_XML) {
				echo '<b>send</b><pre>'.htmlentities($davxml).'</pre>';
			}
			$this->http_client->requestBody = $davxml;
			if( $this->http_client->sendCommand( 'REPORT '.$uri.' HTTP/1.1' ) )
			{
				$this->http_client->processReply();
			}

			if (DEBUG_DAV_XML) {
				echo '<b>Recieve</b><pre>'.htmlentities($this->http_client->getBody()).'</pre>';
			}
			return $this->http_client->reply;		
		}
	
	}

