<?php
  /**************************************************************************\
  * eGroupWare                                                               *
  * http://www.egroupware.org                                                *
  * Written by Miles Lott <milos@groupwhere.org>                             *
  * ------------------------------------------------------------------------ *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	/*
	  Set a global flag to indicate this file was found by setup/config.php.
	  config.php will unset it after parsing the form values.
	*/
	$GLOBALS['egw_info']['server']['found_validation_hook'] = True;

	function in_docroot($path='')
	{
		$docroots = array(EGW_SERVER_ROOT,$_SERVER['DOCUMENT_ROOT']);

		foreach($docroots as $docroot)
		{
			$len = strlen($docroot);

			if($docroot == substr($path,0,$len))
			{
				$rest = substr($path,$len);

				if(!strlen($rest) || $rest[0] == DIRECTORY_SEPARATOR)
				{
					return True;
				}
			}
		}
		return False;
	}

	function files_dir($settings)
	{
		if(in_docroot($settings['files_dir']))
		{
			$GLOBALS['config_error'] = 'Path to user and group files HAS TO BE OUTSIDE of the webservers document-root!!!';
		}
	}

	function _mcrypt_test_module_mode($module,$mode)
	{
		/* Data */
		$key = 'this is a very long key, even too long for the cipher';
		$plain_text = 'very important data';

		/* Open module, and create IV */
		if(!$GLOBALS['td'] = @mcrypt_module_open($module, '',$mode, ''))
		{
			@mcrypt_module_close($GLOBALS['td']);
			return False;
		}
		$key = substr($key, 0, mcrypt_enc_get_key_size($GLOBALS['td']));
		$iv_size = mcrypt_enc_get_iv_size($GLOBALS['td']);
		$iv = @mcrypt_create_iv($iv_size, MCRYPT_RAND);

		/* Initialize encryption handle */
		if(mcrypt_generic_init($GLOBALS['td'], $key, $iv) != -1)
		{
			/* Encrypt data */
			$c_t = mcrypt_generic($GLOBALS['td'], $plain_text);
			mcrypt_generic_deinit($GLOBALS['td']);

			// close the module
			mcrypt_module_close($GLOBALS['td']);

			/* Reinitialize buffers for decryption */
			/* Open module */
			$GLOBALS['td'] = mcrypt_module_open($module, '', $mode, '');
			$key = substr($key, 0, mcrypt_enc_get_key_size($GLOBALS['td']));

			mcrypt_generic_init($GLOBALS['td'], $key, $iv);
			$p_t = trim(mdecrypt_generic($GLOBALS['td'], $c_t)); //trim to remove padding

			/* Clean up */
			mcrypt_generic_end($GLOBALS['td']);
			mcrypt_module_close($GLOBALS['td']);
		}
		@mcrypt_module_close($GLOBALS['td']);

		if(strncmp($p_t, $plain_text, strlen($plain_text)) == 0)
		{
			return True;
		}
		else
		{
			return False;
		}
	}

	/* run a self-test through every listed cipher and mode - from robert at peakepro dot com (php.net manual) */
	function mcrypt_check_sanity()
	{
		$modes = mcrypt_list_modes();
		$algorithms = mcrypt_list_algorithms();

		foreach($algorithms as $cipher)
		{
			if(mcrypt_module_self_test($cipher))
			{
				$GLOBALS['ciphers'][$cipher] = array();
				foreach($modes as $mode)
				{
					if(_mcrypt_test_module_mode($cipher,$mode))
					{
						$result = True;
					}
					else
					{
						$result = False;
					}
					@$GLOBALS['ciphers'][$cipher][$mode] = $result;
				}
			}
		}
		ksort($GLOBALS['ciphers']);
		/* _debug_array($GLOBALS['ciphers']); */
	}

	function mcrypt_algo($settings)
	{
		/* _debug_array($settings);exit; */
		if(@function_exists('mcrypt_list_modes'))
		{
			mcrypt_check_sanity();
			if(!@$GLOBALS['ciphers'][$settings['mcrypt_algo']][$settings['mcrypt_mode']])
			{
				$GLOBALS['config_error'] = 'Invalid Mcrypt Algorithm/Mode combination';
			}
		}
	}
