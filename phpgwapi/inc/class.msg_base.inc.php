<?php
  /**************************************************************************\
  * phpGroupWare - E-Mail                                                    *
  * http://www.phpgroupware.org                                              *
  * Based on Aeromail by Mark Cushman <mark@cushman.net>                     *
  *          http://the.cushman.net/                                         *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */

	class msg_base
	{
		var $msg_struct;
		var $err = array("code","msg","desc");
		var $msg_info = Array(Array());

		var $tempfile;
		var $folder_list_changed = False;
		var $enable_utf7 = False;
		var $imap_builtin = True;
		var $force_msg_uids = False;
		//var $att_files_dir;
		var $force_check;

		var $boundary,
		   $got_structure;

		function msg_base()
		{
			$this->err["code"] = " ";
			$this->err["msg"]  = " ";
			$this->err["desc"] = " ";
			$this->tempfile = $GLOBALS['phpgw_info']['server']['temp_dir'].SEP.$GLOBALS['phpgw_info']['user']['sessionid'].'.mhd';
			$this->force_check = false;
			$this->got_structure = false;
		}

		function utf7_encode($data)
		{
			// handle utf7 encoding of folder names, if necessary
			if (($this->enable_utf7 == False)
			|| (function_exists('imap_utf7_encode') == False)
			|| (!isset($data)))
			{
				return $data;
			}

			// data to and from the server can be either array or string
			if (gettype($data) == 'array')
			{
				// array data
				$return_array = Array();
				for ($i=0; $i<count($data);$i++)
				{
					$return_array[$i] = $this->utf7_encode_string($data[$i]);
				}
				return $return_array;
			}
			elseif (gettype($data) == 'string')
			{
				// string data
				return $this->utf7_encode_string($data);
			}
			else
			{
				// ERROR
				return $data;
			}
		}

		function utf7_encode_string($data_str)
		{
			$name = Array();
			$name['folder_before'] = '';
			$name['folder_after'] = '';
			$name['translated'] = '';
			
			// folder name at this stage is  {SERVER_NAME:PORT}FOLDERNAME
			// get everything to the right of the bracket "}", INCLUDES the bracket itself
			$name['folder_before'] = strstr($data_str,'}');
			// get rid of that 'needle' "}"
			$name['folder_before'] = substr($name['folder_before'], 1);
			// translate
			$name['folder_after'] = imap_utf7_encode($name['folder_before']);
			// replace old folder name with new folder name
			$name['translated'] = str_replace($name['folder_before'], $name['folder_after'], $data_str);
			return $name['translated'];
		}

		function utf7_decode($data)
		{
			// handle utf7 decoding of folder names, if necessary
			if (($this->enable_utf7 == False)
			|| (function_exists('imap_utf7_decode') == False)
			|| (!isset($data)))
			{
				return $data;
			}

			// data to and from the server can be either array or string
			if (gettype($data) == 'array')
			{
				// array data
				$return_array = Array();
				for ($i=0; $i<count($data);$i++)
				{
					$return_array[$i] = $this->utf7_decode_string($data[$i]);
				}
				return $return_array;
			}
			elseif (gettype($data) == 'string')
			{
				// string data
				return $this->utf7_decode_string($data);
			}
			else
			{
				// ERROR
				return $data;
			}
		}

		function utf7_decode_string($data_str)
		{
			$name = Array();
			$name['folder_before'] = '';
			$name['folder_after'] = '';
			$name['translated'] = '';
			
			// folder name at this stage is  {SERVER_NAME:PORT}FOLDERNAME
			// get everything to the right of the bracket "}", INCLUDES the bracket itself
			$name['folder_before'] = strstr($data_str,'}');
			// get rid of that 'needle' "}"
			$name['folder_before'] = substr($name['folder_before'], 1);
			// translate
			$name['folder_after'] = imap_utf7_decode($name['folder_before']);
			// "imap_utf7_decode" returns False if no translation occured
			if ($name['folder_after'] == False)
			{
				// no translation occured
				return $data_str;
			}
			else
			{
				// replace old folder name with new folder name
				$name['translated'] = str_replace($name['folder_before'], $name['folder_after'], $data_str);
				return $name['translated'];
			}
		}

		function get_flag($stream,$msg_num,$flag)
		{
			$header = $this->fetchheader($stream,$msg_num);
			$flag = strtolower($flag);
			for ($i=0;$i<count($header);$i++)
			{
				$pos = strpos($header[$i],":");
				if (is_int($pos) && $pos)
				{
					$keyword = trim(substr($header[$i],0,$pos));
					$content = trim(substr($header[$i],$pos+1));
					if (strtolower($keyword) == $flag)
					{
						return $content;
					}
				}
			}
			return false;
		}

	} // end of class mail
?>
