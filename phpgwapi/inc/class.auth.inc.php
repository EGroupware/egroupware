<?php 
  /**************************************************************************\
  * eGroupWare API - Password auth and crypt functions                       *
  * This file written by Miles Lott <milos@groupwhere.org>                   *
  * Copyright (C) 2004 Miles Lott                                            *
  *  Many functions based on code from Frank Thomas <frank@thomas-alfeld.de> *
  *    which can be seen at http://www.thomas-alfeld.de/frank/               *
  *  Other functions from class.common.inc.php originally from phpGroupWare  *
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

	if(empty($GLOBALS['phpgw_info']['server']['auth_type']))
	{
		$GLOBALS['phpgw_info']['server']['auth_type'] = 'sql';
	}
	include(PHPGW_API_INC.'/class.auth_'.$GLOBALS['phpgw_info']['server']['auth_type'].'.inc.php');

	class auth extends auth_
	{
		var $seeded = False;
		var $error  = '';

		/*!
		@function randomstring
		@abstract return a random string of size $size
		@param $size int-size of random string to return
		*/
		function randomstring($size)
		{
			$s = '';
			$random_char = array(
				'0','1','2','3','4','5','6','7','8','9','a','b','c','d','e','f',
				'g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v',
				'w','x','y','z','A','B','C','D','E','F','G','H','I','J','K','L',
				'M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'
			);

			if(!$this->seeded && phpversion() < '4.2.0')
			{
			    list($usec, $sec) = explode(' ', microtime());
				mt_srand((float)$sec + ((float)$usec * 100000));
				$this->seeded = True;
			}

			for ($i=0; $i<$size; $i++)
			{
				$s .= $random_char[mt_rand(1,61)];
			}
			return $s;
		}

		/*!
		@function encrypt_password
		@abstract encrypt password
		@abstract uses the encryption type set in setup and calls the appropriate encryption functions
		@param $password password to encrypt
		*/
		function encrypt_password($password,$sql=False)
		{
			if($sql)
			{
				return $this->encrypt_sql($password);
			}
			return $this->encrypt_ldap($password);
		}

		/*!
		@function encrypt_ldap
		@abstract encrypt password for ldap
		@abstract uses the encryption type set in setup and calls the appropriate encryption functions
		@param $password password to encrypt
		*/
		function encrypt_ldap($password)
		{
			$type = strtolower($GLOBALS['phpgw_info']['server']['ldap_encryption_type']);
			$salt = '';
			switch($type)
			{
				case 'des':
					$salt       = $this->randomstring(2);
					$_password  = crypt($password, $salt);
					$e_password = sprintf('%s%s', '{crypt}', $_password);
					break;
				case 'md5':
					/* New method taken from the openldap-software list as recommended by
					 * Kervin L. Pierre" <kervin@blueprint-tech.com>
					 */
					$e_password = '{md5}' . base64_encode(pack("H*",md5($password)));
					break;
				case 'smd5':
					if(!function_exists('mhash'))
					{
						return False;
					}
					$salt = $this->randomstring(8);
					$hash = mhash(MHASH_MD5, $password . $salt);
					$e_password = '{SMD5}' . base64_encode($hash . $salt);
					break;
				case 'sha':
					if(!function_exists('mhash'))
					{
						return False;
					}
					$e_password = '{SHA}' . base64_encode(mhash(MHASH_SHA1, $password));
					break;
				case 'ssha':
					if(!function_exists('mhash'))
					{
						return False;
					}
					$salt = $this->randomstring(8);
					$hash = mhash(MHASH_SHA1, $password . $salt);
					$e_password = '{SSHA}' . base64_encode($hash . $salt);
					break;
				default:
					return False;
			}
			return $e_password;
		}

		/* Create a password for storage in the phpgw_accounts table */
		function encrypt_sql($password)
		{
			/* Grab configured type, or default to md5() (old method) */
			$type = @$GLOBALS['phpgw_info']['server']['sql_encryption_type']
				? strtolower($GLOBALS['phpgw_info']['server']['sql_encryption_type'])
				: 'md5';
			switch($type)
			{
				case 'crypt':
					if(@defined('CRYPT_STD_DES') && CRYPT_STD_DES == 1)
					{
						$salt = $this->randomstring(2);
						return crypt($password,$salt);
					}
					$this->error = 'no std crypt';
					break;
				case 'blowfish_crypt':
					if(@defined('CRYPT_BLOWFISH') && CRYPT_BLOWFISH == 1)
					{
						$salt = '$2$' . $this->randomstring(13);
						return crypt($password,$salt);
					}
					$this->error = 'no blowfish crypt';
					break;
				case 'md5_crypt':
					if(@defined('CRYPT_MD5') && CRYPT_MD5 == 1)
					{
						$salt = '$1$' . $this->randomstring(9);
						return crypt($password,$salt);
					}
					$this->error = 'no md5 crypt';
					break;
				case 'ext_crypt':
					if(@defined('CRYPT_EXT_DES') && CRYPT_EXT_DES == 1)
					{
						$salt = $this->randomstring(9);
						return crypt($password,$salt);
					}
					$this->error = 'no ext crypt';
					break;
				case 'smd5':
					if(!function_exists('mhash'))
					{
						return False;
					}
					$salt = $this->randomstring(8);
					$hash = mhash(MHASH_MD5, $password . $salt);
					return '{SMD5}' . base64_encode($hash . $salt);
				case 'sha':
					if(!function_exists('mhash'))
					{
						$this->error = 'no sha';
						return False;
					}
					return '{SHA}' . base64_encode(mhash(MHASH_SHA1,$password));
				case 'ssha':
					if(!function_exists('mhash'))
					{
						$this->error = 'no ssha';
						return False;
					}
					$salt = $this->randomstring(8);
					$hash = mhash(MHASH_SHA1, $password . $salt);
					return '{SSHA}' . base64_encode($hash . $salt);
				case 'md5':
				default:
					/* This is the old standard for password storage in SQL */
					return md5($password);
			}
			$this->error = $this->error ? $this->error : 'no valid encryption available';
			return False;
		}

		/**
		@function smd5_compare
		@abstract compare SHA-encrypted passwords for authentication
		@param $form_val user input value for comparison
		@param $db_val   stored value (from database)
		@return boolean	 True on successful comparison
		*/
		function smd5_compare($form_val,$db_val)
		{
			/* Start with the first char after {SMD5} */
			$hash = base64_decode(substr($db_val,6));
			$new_hash = mhash(MHASH_MD5,$form_val);
			//echo '<br>  DB: ' . base64_encode($orig_hash) . '<br>FORM: ' . base64_encode($new_hash);

			if(strcmp($hash,$new_hash) == 0)
			{
				return True;
			}
			return False;
		}

		/**
		@function sha_compare
		@abstract compare SHA-encrypted passwords for authentication
		@param $form_val user input value for comparison
		@param $db_val   stored value (from database)
		@return boolean	 True on successful comparison
		*/
		function sha_compare($form_val,$db_val)
		{
			/* Start with the first char after {SHA} */
			$hash = base64_decode(substr($db_val,5));
			$new_hash = mhash(MHASH_SHA1,$form_val);
			//echo '<br>  DB: ' . base64_encode($orig_hash) . '<br>FORM: ' . base64_encode($new_hash);

			if(strcmp($hash,$new_hash) == 0)
			{
				return True;
			}
			return False;
		}

		/**
		@function ssha_compare
		@abstract compare SSHA-encrypted passwords for authentication
		@param $form_val user input value for comparison
		@param $db_val   stored value (from database)
		@return boolean	 True on successful comparison
		*/
		function ssha_compare($form_val,$db_val)
		{
			/* Start with the first char after {SSHA} */
			$hash = base64_decode(substr($db_val, 6));

			// SHA-1 hashes are 160 bits long
			$orig_hash = substr($hash, 0, 20);
			$salt = substr($hash, 20);
			$new_hash = mhash(MHASH_SHA1, $form_val . $salt);

			if(strcmp($orig_hash,$new_hash) == 0)
			{
				return True;
			}
			return False;
		}

		/**
		@function crypt_compare
		@abstract compare crypted passwords for authentication whether des,ext_des,md5, or blowfish crypt
		@param $form_val user input value for comparison
		@param $db_val   stored value (from database)
		@param $type     crypt() type
		@return boolean	 True on successful comparison
		*/
		function crypt_compare($form_val,$db_val,$type)
		{
			$saltlen = array(
				'blowfish_crypt' => 16,
				'md5_crypt' => 12,
				'ext_crypt' => 9,
				'crypt' => 2
			);

			// PHP's crypt(): salt + hash
			// notice: "The encryption type is triggered by the salt argument."
			$salt = substr($db_val, 0, (int)$saltlen[$type]);
			$new_hash = crypt($form_val, $salt);

			if(strcmp($db_val,$new_hash) == 0)
			{
				return True;
			}
			return False;
		}
	}
?>
