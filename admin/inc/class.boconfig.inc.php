<?php
	/**************************************************************************\
	* phpGroupWare - configuration administration                              *
	* http://www.phpgroupware.org                                              *
	* Copyright (C) 2001 Loic Dachary                                          *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	class boconfig
	{
		var $public_functions = array(
		);

		var $xml_functions = array();

		var $soap_functions = array(
			'rpc_values' => array(
				'in'  => array('struct', 'struct'),
				'out' => array()
			)
		);

		function list_methods($_type='xmlrpc')
		{
			/*
			  This handles introspection or discovery by the logged in client,
			  in which case the input might be an array.  The server always calls
			  this function to fill the server dispatch map using a string.
			*/
			if (is_array($_type))
			{
				$_type = $_type['type'] ? $_type['type'] : $_type[0];
			}
			switch($_type)
			{
				case 'xmlrpc':
					$xml_functions = array(
						'rpc_values' => array(
							'function'  => 'rpc_values',
							'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
							'docstring' => lang('Set preference values.')
						),
						'list_methods' => array(
							'function'  => 'list_methods',
							'signature' => array(array(xmlrpcStruct,xmlrpcString)),
							'docstring' => lang('Read this list of methods.')
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

	  // xmlrpc functions

		function rpc_values($data)
		{
			$newsettings = $data['newsettings'];
			if (!$data['appname'])
			{
				$errors[] = "Missing appname";
			}
			if (!is_array($newsettings))
			{
				$errors[] = "Missing newsettings or not an array";
			}

			if (is_array($errors))
			{
				return $errors;
			}

			$conf = CreateObject('phpgwapi.config', $data['appname']);

			$conf->read_repository();
			reset($newsettings);
			while(list($key,$val) = each($newsettings))
			{
				$conf->value($key, $val);
			}
			$conf->save_repository();
			return True;
		}

	}
?>
