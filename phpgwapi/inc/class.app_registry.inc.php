<?php
  /**************************************************************************\
  * phpGroupWare API - App(lication) Registry Manager Class                  *
  * This file written by Mark Peters <skeeter@phpgroupware.org>              *
  * Copyright (C) 2001 Mark Peters                                           *
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
	/*!
	@class app_registry
	@abstract functions for managing and installing apps via XML-RPC/SOAP
	@discussion Author: skeeter
	*/
	class app_registry
	{
		var $public_functions = array(
			'list_methods'          => True,
			'request_appbyid'       => True,
			'request_appbyname'     => True,
			'request_newer_applist' => True,
			'request_packaged_app'  => True,
			'get_appbyid'           => True,
			'get_appbyname'         => True,
			'find_new_app'          => True
		);

		var $soap_functions = array();

		var $db;
		var $is;
		var $client;
		var $server;

		var $dir_file = Array();

//		var $target_page = '/cvsdemo/xmlrpc.php';
//		var $target_site = 'www.phpgroupware.org';
		var $target_page = '/phpgroupware/xmlrpc.php';
		var $target_site = 'devel';
		var $target_port = 80;
		
		function app_registry($param='')
		{
			$this->db = $GLOBALS['phpgw']->db;

			if(is_array($param))
			{
				// This is the interserver communicator
				$this->is = CreateObject('phpgwapi.interserver',$param['server']);
				$this->is->sessionid = $param['sessionid'];
				$this->is->kp3 = $param['kp3'];
			}
			else
			{
				// create the app_registry services client
				$this->client = CreateObject('phpgwapi.xmlrpc_client',$this->target_page,$this->target_site,$this->target_port);
				$this->client->debug = False;
			}
		}


		function list_methods($_type='xmlrpc')
		{
			/*
			  This handles introspection or discovery by the logged in client,
			  in which case the input might be an array.  The server always calls
			  this function to fill the server dispatch map using a string.
			*/
			if (is_array($_type))
			{
				$_type = $_type['type'];
			}
			switch($_type)
			{
				case 'xmlrpc':
					$xml_functions = Array(
						'list_methods' => Array(
							'function'  => 'list_methods',
							'signature' => Array(
								Array(
									xmlrpcStruct,
									xmlrpcString
								)
							),
							'docstring' => lang('Read this list of methods.')
 						),
						'get_appbyid' => Array(
							'function'  => 'get_appbyid',
							'signature' => Array(
								Array(
									xmlrpcStruct,
									xmlrpcString
								)
							),
							'docstring' => lang('Read a single app by id.')
						),
						'get_appbyname' => Array(
							'function'  => 'get_appbyname',
							'signature' => Array(
								Array(
									xmlrpcStruct,
									xmlrpcString
								)
							),
							'docstring' => lang('Read a single app by name.')
						),
						'find_new_app' => Array(
							'function'  => 'find_new_app',
							'signature' => Array(
								Array(
									xmlrpcStruct,
									xmlrpcStruct
								)
							),
							'docstring' => lang('compare an array of apps/versions against the repository and return new/updated list of apps.')
						),
						'package_app_byid' => Array(
							'function'  => 'package_app_byid',
							'signature' => Array(
								Array(
									xmlrpcStruct,
									xmlrpcString
								)
							),
							'docstring' => lang('Package an application for transport back to the calling client.')
						)
					);
					return $xml_functions;
					break;
				case 'soap':
					return $this->soap_functions;
					break;
				default:
					return array();
					break;
			}
		}

		function request($method, $args)
		{
			$msg = CreateObject('phpgwapi.xmlrpcmsg',$method,$args);
			$resp = $this->client->send($msg);
			if (!$resp)
			{
				echo '<p>IO error: '.$this->client->errstr.'</p>';
				$GLOBALS['phpgw']->common->phpgw_exit();
			}
			if ($resp->faultCode())
			{
				echo '<p>There was an error: '.$resp->faultCode().' '.$resp->faultString().'</p>';
				$GLOBALS['phpgw']->common->phpgw_exit();
			}
			return xmlrpc_decode($resp->value());
		}

		function request_appbyid($appid)
		{
			if(is_object($this->is))
			{
				return $this->is->send('system.get_appbyid',$appid,$this->is->server['server_url']);
			}
			else
			{
				return $this->request('phpgwapi.app_registry.get_appbyid',$appid);
			}
		}


		function request_appbyname($app_name)
		{
			if(is_object($this->is))
			{
				return $this->is->send('system.get_appbyname',$app_name,$this->is->server['server_url']);
			}
			else
			{
				return $this->request('phpgwapi.app_registry.get_appbyname',$app_name);
			}
		}

		function request_newer_applist($dummy='')
		{
			$this->db->query('SELECT * FROM phpgw_applications',__LINE__,__FILE__);
			while($this->db->next_record())
			{
				$app_list[$this->db->f('app_id')] = Array(
					'id'      => $this->db->f('app_id'),
					'version' => $this->db->f('app_version')
				);
			}
		
			if(is_object($this->is))
			{
				return $this->is->send('system.find_new_app',$app_list,$this->is->server['server_url']);
			}
			else
			{
				return $this->request('phpgwapi.app_registry.find_new_app',$app_list);
			}
		}

		function request_packaged_app($app_id)
		{
			if(is_object($this->is))
			{
				return $this->is->send('system.package_app_byid',$app_id,$this->is->server['server_url']);
			}
			else
			{
				return $this->request('phpgwapi.app_registry.get_appbyname',$app_name);
			}
		}

		function get_result()
		{
			switch($this->db->num_rows())
			{
				case 0:
					$app = False;
					break;
				default:
					while($this->db->next_record())
					{
						$app[$this->db->f('app_id')] = Array(
							'id'      => $this->db->f('app_id'),
							'name'    => $this->db->f('app_name'),
							'title'   => $this->db->f('app_title'),
							'version' => $this->db->f('app_version'),
							'tables'  => $this->db->f('app_tables')
						);
					}
					break;
			}
			return $app;
		}

		function xml_response($app)
		{
			switch(gettype($app))
			{
				case 'boolean':
					return CreateObject('phpgwapi.xmlrpcval',CreateObject('phpgwapi.xmlrpcval',False,'boolean'),'boolean');
					break;
				case 'array':
					@reset($app);
					while($app && list($id,$application) = each($app))
					{
						$updated_app[$id] = CreateObject('phpgwapi.xmlrpcval',
							Array(
								'id'      => CreateObject('phpgwapi.xmlrpcval',$id,'int'),
								'name'    => CreateObject('phpgwapi.xmlrpcval',$application['name'],'string'),
								'title'   => CreateObject('phpgwapi.xmlrpcval',$application['title'],'string'),
								'version' => CreateObject('phpgwapi.xmlrpcval',$application['version'],'string'),
								'tables'  => CreateObject('phpgwapi.xmlrpcval',$application['tables'],'string')
							),
							'struct'
						);
					}
					return CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval',$updated_app, 'struct'));
					break;
			}
		}

		function get_appbyid($app_id)
		{
			$this->db->query('SELECT * FROM phpgw_applications WHERE app_id='.$app_id,__LINE__,__FILE__);
			return $this->xml_response($this->get_result());
		}

		function get_appbyname($app_name)
		{
			$this->db->query("SELECT * FROM phpgw_applications WHERE app_name='".$app_name."'",__LINE__,__FILE__);
			return $this->xml_response($this->get_result());
		}

		function get_allapps()
		{
			$this->db->query('SELECT * FROM phpgw_applications',__LINE__,__FILE__);
			return $this->xml_response($this->get_result());
		}

		function find_new_app($apps)
		{
			$this->db->query('SELECT * FROM phpgw_applications',__LINE__,__FILE__);
			$app = $this->get_result();
			@reset($apps);
			while($apps && list($id,$application) = each($apps))
			{
				if($app[$id])
				{
					if($app[$id]['version'] == $application['version'])
					{
						unset($app[$id]);
					}
				}
			}
			return $this->xml_response($app);
		}

		function package_file($filename)
		{
			$fp=fopen($filename,'rt');
			$packed_file = CreateObject('phpgwapi.xmlrpcval',fread($fp,filesize($filename)),'base64');
			fclose($fp);
			return $packed_file;
		}

		function pack_dir($directory,$app,$dir_prefix='')
		{
			$sep = filesystem_separator();
			if($dir_prefix)
			{
				$dir_prefix .= $sep;
			}
			$d = dir($directory);
			while($entry = $d->read())
			{
				$new_filename = $directory.$sep.$entry;
				if(is_file($new_filename))
				{
					$this->dir_file[$dir_prefix.$entry] = $this->package_file($new_filename);
				}
				elseif(is_dir($new_filename))
				{
					if($entry != '.' && $entry != '..' && $entry != 'CVS')
					{
//						$this->dir_file[$dir_prefix.$entry] = CreateObject('phpgwapi.xmlrpcval',$new_filename,'string');
						$dir_path[$dir_prefix.$entry] = $new_filename;
					}
				}
			}
			$d->close();
			@reset($dir_path);
			while($dir_path && list($dir_prefix,$filename) = each($dir_path))
			{
				$this->pack_dir($filename,$app,$dir_prefix);
			}
		}

		function package_app_byid($appid)
		{
			$this->db->query('SELECT app_name FROM phpgw_applications WHERE app_id='.$appid,__LINE__,__FILE__);
			if(!$this->db->num_rows())
			{
				return CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval',False,'boolean'),'boolean');
			}
			$this->db->next_record();
			$path_prefix = PHPGW_SERVER_ROOT.filesystem_separator().$this->db->f('app_name');
			$this->dir_file = Array();
			$this->pack_dir($path_prefix,$this->db->f('app_name'));
			return CreateObject('phpgwapi.xmlrpcresp',CreateObject('phpgwapi.xmlrpcval',$this->dir_file,'struct'));
		}

	}
?>
