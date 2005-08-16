<?php
	/**************************************************************************\
	* eGroupWare - preferences                                                 *
	* http://www.egroupware.org                                                *
	* Written by Joseph Engo <jengo@phpgroupware.org>                          *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	class bopassword
	{
		var $public_functions = array(
			'changepass' => True
		);

		var $xml_functions  = array();
		var $xmlrpc_methods = array();
		var $soap_functions = array(
			'changepass' => array(
				'in'  => array('string','string'),
				'out' => array('boolean')
			)
		);

		var $debug = False;

		function changepass($old,$new)
		{
			return $GLOBALS['egw']->auth->change_password($old, $new);
		}

		function list_methods($_type='xmlrpc')
		{
			/*
			  This handles introspection or discovery by the logged in client,
			  in which case the input might be an array.  The server always calls
			  this function to fill the server dispatch map using a string.
			*/
			if(is_array($_type))
			{
				$_type = $_type['type'] ? $_type['type'] : $_type[0];
			}
			switch($_type)
			{
				case 'xmlrpc':
					$xml_functions = array(
						'changepass' => array(
							'function'  => 'changepass',
							'signature' => array(array(xmlrpcBoolean,xmlrpcString,xmlrcpString)),
							'docstring' => lang('Change a user password by passing the old and new passwords.  Returns TRUE on success, FALSE on failure.')
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
	}
?>
