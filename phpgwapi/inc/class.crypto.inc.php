<?php
  /**************************************************************************\
  * phpGroupWare API - Crypto                                                *
  * http://www.phpgroupware.org/api                                          *
  * This file written by Joseph Engo <jengo@phpgroupware.org>                *
  * Handles encrypting strings based on various encryption schemes           *
  * Copyright (C) 2000, 2001 Dan Kuykendall                                  *
  * -------------------------------------------------------------------------*
  * This library is part of phpGroupWare (http://www.phpgroupware.org)       * 
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

  class crypto {
    var $td = False;		// Handle for mcrypt
    var $iv = "";
    var $key = "";
    function crypto($vars)
    {
      global $phpgw, $phpgw_info;
      $key = $vars[0];
      $iv = $vars[1];
      if ($phpgw_info["server"]["mcrypt_enabled"] && extension_loaded("mcrypt")) {
      	if ($phpgw_info["server"]["versions"]["mcrypt"] == "old") {
          $this->td = false;
          if (PHP_VERSION > "4.0.2pl1") {
            $keysize = mcrypt_get_key_size(MCRYPT_TRIPLEDES);
            $ivsize  = mcrypt_get_iv_size(MCRYPT_TRIPLEDES,MCRYPT_MODE_CBC);
          } else {
            $keysize = 8;
            $ivsize = 8;
          }
        } else {
      	  // Start up mcrypt
          $this->td = mcrypt_module_open (MCRYPT_TRIPLEDES, "", MCRYPT_MODE_CBC, "");

          $ivsize  = mcrypt_enc_get_iv_size($this->td);
      	  $keysize = mcrypt_enc_get_key_size($this->td);
        } 

      	// Hack IV to be the correct size
        $x = strlen($iv);
        for ($i = 0; $i < $ivsize; $i++) {
          $this->iv .= $iv[$i % $x];
        }
 
        // Hack Key to be the correct size
        $x = strlen($key);

        for ($i = 0; $i < $keysize; $i++) {
          $this->key .= $key[$i % $x];
        }
        if ($phpgw_info["server"]["versions"]["mcrypt"] != "old") {
          mcrypt_generic_init ($this->td, $this->key, $this->iv);
        } 
      } 
      // If mcrypt isn't loaded key and iv are not needed
    }

    function cleanup()
    {
      global $phpgw_info;

      if ($phpgw_info["server"]["mcrypt_enabled"] && extension_loaded("mcrypt")) {
        if ($phpgw_info["server"]["versions"]["mcrypt"] != "old") {
          mcrypt_generic_end ($this->td);
        } 
      }
    }
    function hex2bin($data)
    {
       $len = strlen($data);
       return pack("H" . $len, $data);
    }

    function encrypt($data) {
      global $phpgw_info;

      $data = serialize($data);

      // Disable all encryption if the admin didn't set it up
      if ($phpgw_info["server"]["mcrypt_enabled"] && extension_loaded("mcrypt")) {
      	switch ($phpgw_info["server"]["versions"]["mcrypt"]) {
    	    // The old code, only works with mcrypt <= 2.2.x
      	  case "old": {	 
            $encrypteddata = mcrypt_cbc(MCRYPT_TripleDES, $this->key, $data, MCRYPT_ENCRYPT);
            break;
          }
          default: { // Handle 2.4 and newer API
            $encrypteddata = mcrypt_generic($this->td, $data);
          }    
        }
        $encrypteddata = bin2hex($encrypteddata);
        return $encrypteddata;
      } else {  // No mcrypt == insecure !
        return $data;
      }  
    }

    function decrypt($encrypteddata) {
      global $phpgw_info;

      // Disable all encryption if the admin didn't set it up
      if ($phpgw_info["server"]["mcrypt_enabled"] && extension_loaded("mcrypt")) {
        $data = $this->hex2bin($encrypteddata);

      	switch ($phpgw_info["server"]["versions"]["mcrypt"]) {
   	     // The old code, only works with mcrypt <= 2.2.x
          case "old": {	 
            $data = mcrypt_cbc(MCRYPT_TripleDES, $this->key, $data, MCRYPT_DECRYPT);
            break;
          }
          default: { // Handle 2.4 and newer API
            $data = mdecrypt_generic($this->td, $data);
          }
        }   
        return unserialize($data);
      } else {
        return unserialize($encrypteddata);
      }  
    }

  }  // class crypto
