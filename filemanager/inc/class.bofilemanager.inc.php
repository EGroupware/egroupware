<?php

	class bofilemanager
	{

		// used

		var $so;
		var $vfs;

		var $rootdir;
		var $fakebase;
		var $appname;
		var $filesdir;
		var $hostname;
		var $userinfo = Array();
		var $homedir;
		var $sep;
		var $file_attributes;

		var $matches;//FIXME matches not defined

		var $debug = False;

		function bofilemanager()
		{
			$this->so = CreateObject('filemanager.sofilemanager');
			$this->so->db_init();

			$this->vfs = CreateObject('phpgwapi.vfs');

			error_reporting (4);

			### Start Configuration Options ###
			### These are automatically set in phpGW - do not edit ###

			$this->sep = SEP;
			$this->rootdir = $this->vfs->basedir;
			$this->fakebase = $this->vfs->fakebase;
			$this->appname = $GLOBALS['phpgw_info']['flags']['currentapp'];
		
			if (stristr ($this->rootdir, PHPGW_SERVER_ROOT))
			{
				$this->filesdir = substr ($this->rootdir, strlen (PHPGW_SERVER_ROOT));
			}
			else
			{
				unset ($this->filesdir);
			}

			$this->hostname = $GLOBALS['phpgw_info']['server']['webserver_url'] . $this->filesdir;

//			die($this->hostname); 
			###
			# Note that $userinfo["username"] is actually the id number, not the login name
			###

			$this->userinfo['username'] = $GLOBALS['phpgw_info']['user']['account_id'];
			$this->userinfo['account_lid'] = $GLOBALS['phpgw']->accounts->id2name ($this->userinfo['username']);
			$this->userinfo['hdspace'] = 10000000000; // to settings
			$this->homedir = $this->fakebase.'/'.$this->userinfo['account_lid'];

			### End Configuration Options ###

			if (!defined ('NULL'))
			{
				define ('NULL', '');
			}

			###
			# Define the list of file attributes.  Format is "internal_name" => "Displayed name"
			# This is used both by internally and externally for things like preferences
			###

			$this->file_attributes = Array(
				'name' => lang('File Name'),
				'mime_type' => lang('MIME Type'),
				'size' => lang('Size'),
				'created' => lang('Created'),
				'modified' => lang('Modified'),
				'owner' => lang('Owner'),
				'createdby_id' => lang('Created by'),
				'modifiedby_id' => lang('Created by'),
				'modifiedby_id' => lang('Modified by'),
				'app' => lang('Application'),
				'comment' => lang('Comment'),
				'version' => lang('Version')
			);

			###
			# Calculate and display B or KB
			# And yes, that first if is strange, 
			# but it does do something
			###

		}


		function borkb ($size, $enclosed = NULL, $return = 1)
		{
			if (!$size)
			$size = 0;

			if ($enclosed)
			{
				$left = '(';
				$right = ')';
			}

			if ($size < 1024)
			$rstring = $left . $size . 'B' . $right;
			else
			$rstring = $left . round($size/1024) . 'KB' . $right;

			return ($this->eor ($rstring, $return));
		}

		###
		# Check for and return the first unwanted character
		###

		function bad_chars ($string, $all = True, $return = 0)
		{
			if ($all)
			{
				if (preg_match("-([\\/<>\'\"\&])-", $string, $badchars))
				$rstring = $badchars[1];
			}
			else
			{
				if (preg_match("-([\\/<>])-", $string, $badchars))
				$rstring = $badchars[1];
			}

			return trim (($this->eor ($rstring, $return)));
		}

		###
		# Match character in string using ord ().
		###

		function ord_match ($string, $charnum)
		{
			for ($i = 0; $i < strlen ($string); $i++)
			{
				$character = ord (substr ($string, $i, 1));

				if ($character == $charnum)
				{
					return True;
				}
			}

			return False;
		}

		###
		# Decide whether to echo or return.  Used by HTML functions
		###

		function eor ($rstring, $return)
		{
			if ($return)
			return ($rstring);
			else
			{
				$this->html_text ($rstring . "\n");
				return (0);
			}
		}
		
		function html_text ($string, $times = 1, $return = 0, $lang = 0)
		{
			if ($lang)
			$string = lang($string);

			if ($times == NULL)
			$times = 1;
			for ($i = 0; $i != $times; $i++)
			{
				if ($return)
				$rstring .= $string;
				else
				echo $string;
			}
			if ($return)
			return ($rstring);
		}

		###
		# URL encode a string
		# First check if its a query string, then if its just a URL, then just encodes it all
		# Note: this is a hack.  It was made to work with form actions, form values, and links only,
		# but should be able to handle any normal query string or URL
		###

		function string_encode ($string, $return = False)
		{
			//var_dump($string);
			if (preg_match ("/=(.*)(&|$)/U", $string))
			{
				$rstring = $string;

				preg_match_all ("/=(.*)(&|$)/U", $string, $matches, PREG_SET_ORDER);//FIXME matches not defined

				reset ($matches);//FIXME matches not defined

				while (list (,$match_array) = each ($matches))//FIXME matches not defined

				{
					$var_encoded = rawurlencode (base64_encode ($match_array[1]));
					$rstring = str_replace ($match_array[0], '=' . $var_encoded . $match_array[2], $rstring);
				}
			}
			elseif ($this->hostname != "" && ereg('^'.$this->hostname, $string))
//			elseif (ereg ('^'.$this->hostname, $string))
			{
				$rstring = ereg_replace ('^'.$this->hostname.'/', '', $string);
				$rstring = preg_replace ("/(.*)(\/|$)/Ue", "rawurlencode (base64_encode ('\\1')) . '\\2'", $rstring);
				$rstring = $this->hostname.'/'.$rstring;
			}
			else
			{
				$rstring = rawurlencode ($string);

				/* Terrible hack, decodes all /'s back to normal */  
				$rstring = preg_replace ("/%2F/", '/', $rstring);
			}

			return ($this->eor ($rstring, $return));
		}

		function string_decode ($string, $return = False)
		{
			$rstring = rawurldecode ($string);

			return ($this->eor ($rstring, $return));
		}

		###
		# HTML encode a string
		# This should be used with anything in an HTML tag that might contain < or >
		###

		function html_encode ($string, $return)
		{
			$rstring = htmlspecialchars ($string);

			return ($this->eor ($rstring, $return));
		}
	}
	?>
