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

	function encryptalgo($config)
	{
		if(@function_exists('mcrypt_list_algorithms'))
		{
			$listed = array();
			if(!isset($config['mcrypt_algo']))
			{
				$config['mcrypt_algo'] = 'tripledes';  /* MCRYPT_TRIPLEDES */
			}
			$algos = @mcrypt_list_algorithms();
			$found = False;

			$out = '';
			while(list($key,$value) = each($algos))
			{
				$found = True;
				/* Only show each once - seems this is a problem in some installs */
				if(!in_array($value,$listed))
				{
					if($config['mcrypt_algo'] == $value)
					{
						$selected = ' selected';
					}
					else
					{
						$selected = '';
					}
					$descr = strtoupper($value);

					$out .= '<option value="' . $value . '"' . $selected . '>' . $descr . '</option>' . "\n";
					$listed[] = $value;
				}
			}
			if(!$found)
			{
				/* Something is wrong with their mcrypt install or php.ini */
				$out = '<option value="">' . lang('no algorithms available') . '</option>' . "\n";;
			}
		}
		else
		{
			$out = '<option value="tripledes">TRIPLEDES</option>' . "\n";;
		}
		return $out;
	}

	function encryptmode($config)
	{
		if(@function_exists('mcrypt_list_modes'))
		{
			$listed = array();
			if(!isset($config['mcrypt_mode']))
			{
				$config['mcrypt_mode'] = 'cbc'; /* MCRYPT_MODE_CBC */
			}
			$modes = @mcrypt_list_modes();
			$found = False;

			$out = '';
			while(list($key,$value) = each($modes))
			{
				$found = True;
				/* Only show each once - seems this is a problem in some installs */
				if(!in_array($value,$listed))
				{
					if($config['mcrypt_mode'] == $value)
					{
						$selected = ' selected';
					}
					else
					{
						$selected = '';
					}
					$descr = strtoupper($value);

					$out .= '<option value="' . $value . '"' . $selected . '>' . $descr . '</option>' . "\n";
					$listed[] = $value;
				}
			}
			if(!$found)
			{
				/* Something is wrong with their mcrypt install or php.ini */
				$out = '<option value="" selected>' . lang('no modes available') . '</option>' . "\n";
			}
		}
		else
		{
			$out = '<option value="cbc" selected>CBC</option>' . "\n";
		}
		return $out;
	}

	function passwdhashes($config)
	{
		$hashes = array(
			'des' => 'des',
			'md5' => 'md5'
		);
		if(@function_exists('mhash'))
		{
			$hashes += array(
				'smd5' => 'smd5',
				'sha'  => 'sha',
				'ssha' => 'ssha'
			);
		}

		while(list($key, $value) = each($hashes))
		{
			if($config['ldap_encryption_type'] == $value)
			{
				$selected = ' selected';
			}
			else
			{
				$selected = '';
			}
			$descr = strtoupper($value);

			$out .= '<option value="' . $value . '"' . $selected . '>' . $descr . '</option>' . "\n";
		}
		return $out;
	}

	function sql_passwdhashes($config)
	{
		$hashes = array(
			'md5' => 'md5'
		);

		/* Check for available crypt methods based on what is defined by php */ 
		if(@defined('CRYPT_BLOWFISH') && CRYPT_BLOWFISH == 1)
		{
			$hashes['blowish_crypt'] = 'blowish_crypt';
		}
		if(@defined('CRYPT_MD5') && CRYPT_MD5 == 1)
		{
			$hashes['md5_crypt'] = 'md5_crypt'; 
		}
		if(@defined('CRYPT_EXT_DES') && CRYPT_EXT_DES == 1)
		{
			$hashes['ext_crypt'] = 'ext_crypt';
		}
		if(@defined('CRYPT_STD_DES') && CRYPT_STD_DES == 1)
		{
			$hashes['crypt'] = 'crypt';
		}

		if(@function_exists('mhash'))
		{
			$hashes += array(
				'smd5' => 'smd5',
				'sha'  => 'sha',
				'ssha' => 'ssha'
			);
		}

		while(list($key, $value) = each($hashes))
		{
			if($config['sql_encryption_type'] == $value)
			{
				$selected = ' selected';
			}
			else
			{
				$selected = '';
			}
			$descr = strtoupper($value);

			$out .= '<option value="' . $value . '"' . $selected . '>' . $descr . '</option>' . "\n";
		}
		return $out;
	}
?>
