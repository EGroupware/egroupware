<?php
  /**************************************************************************\
  * phpGroupWare API - Crypto                                                *
  * This file written by Joseph Engo <jengo@phpgroupware.org>                *
  * Handles encrypting strings based on various encryption schemes           *
  * Copyright (C) 2000, 2001 Dan Kuykendall                                  *
  * -------------------------------------------------------------------------*
  * This library is part of the phpGroupWare API                             *
  * http://www.phpgroupware.org/api                                          *
  * -------------------------------------------------------------------------*
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

	class crypto
	{
		var $enabled = False;
		var $debug = False;

		var $mcrypt_version = '';
		var $algo = MCRYPT_TRIPLEDES;
		var $mode = MCRYPT_MODE_CBC;
		var $td = False; /* Handle for mcrypt */
		var $iv = '';
		var $key = '';

		function crypto($vars='')
		{
			if(is_array($vars))
			{
				$this->init($vars);
			}
		}

		function init($vars)
		{
			/* _debug_array(mcrypt_list_algorithms()); */
			$key = $vars[0];
			$iv  = $vars[1];

			if ($GLOBALS['phpgw_info']['server']['mcrypt_enabled'] && extension_loaded('mcrypt'))
			{
				if($GLOBALS['phpgw_info']['server']['mcrypt_algo'])
				{
					$this->algo = $GLOBALS['phpgw_info']['server']['mcrypt_algo'];
				}
				if($GLOBALS['phpgw_info']['server']['mcrypt_mode'])
				{
					$this->mode = $GLOBALS['phpgw_info']['server']['mcrypt_mode'];
				}

				if($this->debug)
				{
					echo '<br>crypto: algorithm=' . $this->algo;
					echo '<br>crypto: mode     =' . $this->mode;
				}

				$this->enabled = True;
				$this->mcrypt_version = $GLOBALS['phpgw_info']['server']['versions']['mcrypt'];
				if ($this->mcrypt_version == 'old')
				{
					$this->td = False;
					if (phpversion() > '4.0.2pl1')
					{
						$keysize = mcrypt_get_key_size($this->algo);
						$ivsize  = mcrypt_get_iv_size($this->algo,$this->mode);
					}
					else
					{
						$keysize = 8;
						$ivsize  = 8;
					}
				}
				else
				{
					/* Start up mcrypt */
					$this->td = mcrypt_module_open ($this->algo, '', $this->mode, '');

					$ivsize  = mcrypt_enc_get_iv_size($this->td);
					$keysize = mcrypt_enc_get_key_size($this->td);
				}

				/* Hack IV to be the correct size */
				$x = strlen($iv);
				for ($i = 0; $i < $ivsize; $i++)
				{
					$this->iv .= $iv[$i % $x];
				}

				/* Hack Key to be the correct size */
				$x = strlen($key);

				for ($i = 0; $i < $keysize; $i++)
				{
					$this->key .= $key[$i % $x];
				}
			}
			/* If mcrypt isn't loaded, key and iv are not needed. */
		}

		function cleanup()
		{
			if ($this->enabled)
			{
				if ($this->mcrypt_version != 'old')
				{
					mcrypt_generic_end ($this->td);
				}
			}
		}

		function hex2bin($data)
		{
			$len = strlen($data);
			return pack('H'.$len, $data);
		}

		function encrypt($data)
		{
			if($this->debug)
			{
				echo '<br>' . time() . ' crypto->encrypt() unencrypted data: ---->>>>' . $data . "\n";
			}

			if(is_array($data) || is_object($data))
			{
				if($this->debug)
				{
					echo '<br>' . time() . ' crypto->encrypt() found an "' . gettype($data) . '".  Serializing...' . "\n";
				}
				$data = serialize($data);
				$_obj = True;
			}
			else
			{
				if($this->debug)
				{
					echo '<br>' . time() . ' crypto->encrypt() found "' . gettype($data) . '". No serialization...' . "\n";
				}
			}

			/* Disable all encryption if the admin didn't set it up */
			if ($this->enabled)
			{
				if($_obj)
				{
					if($this->debug)
					{
						echo '<br>' . time() . ' crypto->encrypt() adding slashes' . "\n";
					}
					$data = addslashes($data);
				}

				if($this->debug)
				{
					echo '<br>' . time() . ' crypto->encrypt() data: ---->>>>' . $data;
				}

				switch ($this->mcrypt_version)
				{
					case 'old':
						/* The old code, only works with mcrypt <= 2.2.x */
						$encrypteddata = mcrypt_cbc($this->algo, $this->key, $data, MCRYPT_ENCRYPT);
						break;
					default:
						/* Handle 2.4 and newer API */
						mcrypt_generic_init ($this->td, $this->key, $this->iv);
						$encrypteddata = mcrypt_generic($this->td, $data);
						break;
				}
				$encrypteddata = bin2hex($encrypteddata);
				if($this->debug)
				{
					echo '<br>' . time() . ' crypto->encrypt() crypted data: ---->>>>' . $encrypteddata;
				}
				return $encrypteddata;
			}
			else
			{
				/* No mcrypt == insecure ! */
				if($this->debug)
				{
					echo '<br>' . time() . ' crypto->encrypt() crypted data: ---->>>>' . $data;
				}
				return $data;
			}
		}

		function decrypt($encrypteddata)
		{
			if($this->debug)
			{
				echo '<br>' . time() . ' crypto->decrypt() crypted data: ---->>>>' . $encrypteddata;
			}
			/* Disable all encryption if the admin didn't set it up */
			if ($this->enabled)
			{
				$data = $this->hex2bin($encrypteddata);
				switch ($this->mcrypt_version)
				{
					case 'old':
						/* The old code, only works with mcrypt <= 2.2.x */
						$data = mcrypt_cbc($this->algo, $this->key, $data, MCRYPT_DECRYPT);
						break;
					default:
						/* Handle 2.4 and newer API */
						mcrypt_generic_init ($this->td, $this->key, $this->iv);
						$data = mdecrypt_generic($this->td, $data);
						break;
				}

				if($this->debug)
				{
					echo '<br>' . time() . ' crypto->decrypt() decrypted data: ---->>>>' . $data;
				}
				$test = stripslashes($data);
				if(@unserialize($test))
				{
					if($this->debug)
					{
						echo '<br>' . time() . ' crypto->decrypt() stripping slashes' . "\n";
					}
					$data = $test;
				}
				unset($test);

				if($this->debug)
				{
					echo '<br>' . time() . ' crypto->decrypt() data: ---->>>>' . $data . "\n";
				}
			}
			else
			{
				/* No mcrypt == insecure ! */
				$data = $encrypteddata;
			}

			$newdata = @unserialize($data);
			if($newdata)
			{
				if($this->debug)
				{
					echo '<br>' . time() . ' crypto->decrypt() found serialized "' . gettype($newdata) . '".  Unserializing...' . "\n";
					echo '<br>' . time() . ' crypto->decrypt() returning: '; _debug_array($newdata);
				}
				return $newdata;
			}
			else
			{
				if($this->debug)
				{
					echo '<br>' . time() . ' crypto->decrypt() found UNserialized "' . gettype($data) . '".  No unserialization...' . "\n";
					echo '<br>' . time() . ' crypto->decrypt() returning: ' . $data;
				}
				return $data;
			}
		}

		function encrypt_mail_pass($data)
		{
			// Disable all encryption if the admin didn't set it up
			if ($this->enabled)
			{
				// ONLY manipulate data if we are going to encrypt it
				// question: why do we sreialize and add slashes before encrypting?? (ed: Angles)
				$data = serialize($data);
				$data = addslashes($data);
				switch ($this->mcrypt_version)
				{
					// The old code, only works with mcrypt <= 2.2.x
					case 'old':
					{
						$encrypteddata = mcrypt_cbc(MCRYPT_TripleDES, $this->key, $data, MCRYPT_ENCRYPT);
						break;
					}
					default:
					{ // Handle 2.4 and newer API
						mcrypt_generic_init ($this->td, $this->key, $this->iv);
						$encrypteddata = mcrypt_generic($this->td, $data);
						break;
					}
				}
				$encrypteddata = bin2hex($encrypteddata);
				return $encrypteddata;
			}
			else
			{ 
				// No mcrypt == insecure !
				// Data should be returned *unmolested* if encryption is not enabled
				return $data;
			}
		}

		function decrypt_mail_pass($encrypteddata)
		{
			// Disable all encryption if the admin didn't set it up
			if ($this->enabled)
			{
				$data = $this->hex2bin($encrypteddata);
				switch ($this->mcrypt_version)
				{
					// The old code, only works with mcrypt <= 2.2.x
					case 'old':
						$data = mcrypt_cbc(MCRYPT_TripleDES, $this->key, $data, MCRYPT_DECRYPT);
						break;
					// Handle 2.4 and newer API
					default:
						mcrypt_generic_init ($this->td, $this->key, $this->iv);
						$data = mdecrypt_generic($this->td, $data);
						break;
				}
				// hey -- since the encrypt() function calls serialize and then addslashes,
				// we should always do the reverse -- correct? (ed: Del)
				$data = stripslashes($data);
				$data = unserialize($data);
				// question: was it necessary to serialize and addslashes *before* encryption in the first place? (ed: Angles)
			}
			else
			{
				// Data should be returned *unmolested* if encryption is not enabled
				return $data;
			}
			/*
			// this is apparently intended to allow encryption of objects
			// at this point Dec 14, 2001, we simply need to handle strings correctly
			// which was broken previously (ed: Angles)
			if(!strpos(' '.$data,'O:8:"stdClass"'))
			{
				return unserialize($data);
			}
			else
			{
				$data = stripslashes($data);
				return $data;
			}
			*/
		}
	} // class crypto
?>
