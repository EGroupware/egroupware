<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  class crypto {
    var $td = False;		// Handle for mcrypt
    var $iv = "";
    var $key = "";
    function crypto($key,$iv)
    {
      global $phpgw, $phpgw_info;

      if ($phpgw_info["server"]["mcrypt_enabled"] && extension_loaded("mcrypt")) {
      	if ($phpgw_info["server"]["mcrypt_version"] == "old") {
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
        if ($phpgw_info["server"]["mcrypt_version"] != "old") {
          mcrypt_generic_init ($this->td, $this->key, $this->iv);
        } 
      } 
      // If mcrypt isn't loaded key and iv are not needed
    }

    function cleanup()
    {
      global $phpgw_info;

      if ($phpgw_info["server"]["mcrypt_enabled"] && extension_loaded("mcrypt")) {
        if ($phpgw_info["server"]["mcrypt_version"] != "old") {
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
      	switch ($phpgw_info["server"]["mcrypt_version"]) {
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

      	switch ($phpgw_info["server"]["mcrypt_version"]) {
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
