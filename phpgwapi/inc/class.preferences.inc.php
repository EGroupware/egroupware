<?php
	/**************************************************************************\
	* phpGroupWare API - Preferences                                           *
	* This file written by Joseph Engo <jengo@phpgroupware.org>                *
	* and Mark Peters <skeeter@phpgroupware.org>                               *
	* Manages user preferences                                                 *
	* Copyright (C) 2000, 2001 Joseph Engo                                     *
	* -------------------------------------------------------------------------*
	* This library is part of the phpGroupWare API                             *
	* http://www.phpgroupware.org/api                                          *
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

	/*!
	@class preferences 
	@abstract preferences class used for setting application preferences
	@discussion Author: none yet
	*/
	class preferences
	{
		/*! @var account_id */
		var $account_id;
		/*! @var account_type */
		var $account_type;
		/*! @var data */
		var $data = array();
		/*! @var db */
		var $db;
		/*! @var debug_init_prefs */
		var $debug_init_prefs = 0;
		//var $debug_init_prefs = 1;
		//var $debug_init_prefs = 2;
		//var $debug_init_prefs = 3;

		/**************************************************************************\
		* Standard constructor for setting $this->account_id                       *
		\**************************************************************************/
		/*! 
		@function preferences
		@abstract Standard constructor for setting $this->account_id
		@discussion Author:
		*/
		function preferences($account_id = '')
		{
			$this->db         = $GLOBALS['phpgw']->db;
			$this->account_id = get_account_id($account_id);
		}

		/**************************************************************************\
		* These are the standard $this->account_id specific functions              *
		\**************************************************************************/

		/*! 
		@function read_repository
		@abstract private - read preferences from the repository
		@discussion private function should only be called from within this class
		*/
		function read_repository()
		{
			$this->db->query("SELECT * FROM phpgw_preferences WHERE "
				. "preference_owner='" . $this->account_id . "' or "
				. "preference_owner='-1' order by preference_owner desc",__LINE__,__FILE__);
			$this->db->next_record();

			$pref_info  = $this->db->f('preference_value');
			$this->data = unserialize($pref_info);

			if ($this->db->next_record())
			{
				$global_defaults = unserialize($this->db->f('preference_value'));

				while (is_array($global_defaults) && list($appname,$values) = each($global_defaults))
				{
					while (is_array($values) && list($var,$value) = each($values))
					{
						$this->data[$appname][$var] = $value;
					}
				}
			}

			/* This is to supress warnings during login */
			if (is_array($this->data))
			{
				 reset ($this->data);
			}

			// This is to supress warnings durring login
			if (is_array($this->data))
			{
				reset($this->data);
			}
			return $this->data;
		}

		/*!
		@function read
		@abstract public - read preferences from repository and stores in an array
		@discussion Syntax array read(); <>
		Example1: preferences->read();
		@result $data array containing user preferences
		*/
		function read()
		{
			if (count($this->data) == 0)
			{
				$this->read_repository();
			}
			reset ($this->data);
			return $this->data;
		}

		/*!
		@function add
		@abstract add preference to $app_name a particular app
		@discussion
		@param $app_name name of the app
		@param $var name of preference to be stored
		@param $value value of the preference
		*/
		function add($app_name,$var,$value = '')
		{
			if (! $value)
			{
				global $$var;
				$value = $$var;
			}
 
			$this->data[$app_name][$var] = $value;
			reset($this->data);
			return $this->data;
		}

		/*! 
		@function delete
		@abstract delete preference from $app_name
		@discussion
		@param $app_name name of app
		@param $var variable to be deleted
		*/
		function delete($app_name, $var = '')
		{
			if (is_string($var) && $var == '')
			{
//				$this->data[$app_name] = array();
				unset($this->data[$app_name]);
			}
			else
			{
				unset($this->data[$app_name][$var]);
			}
			reset ($this->data);
			return $this->data;
		}

		/*!
		@function add_struct
		@abstract add complex array data preference to $app_name a particular app
		@discussion Use for sublevels of prefs, such as email app's extra accounts preferences
		@param $app_name name of the app
		@param $var String to be evaled's as an ARRAY structure, name of preference to be stored
		@param $value value of the preference
		*/
		function add_struct($app_name,$var,$value = '')
		{
			$code = '$this->data[$app_name]'.$var.' = $value;';
			//echo 'class.preferences: add_struct: $code: '.$code.'<br>';
			eval($code);
			//echo 'class.preferences: add_struct: $this->data[$app_name] dump:'; _debug_array($this->data[$app_name]); echo '<br>';
			reset($this->data);
			return $this->data;
		}

		/*! 
		@function delete_struct
		@abstract delete complex array data preference from $app_name
		@discussion Use for sublevels of prefs, such as email app's extra accounts preferences
		@param $app_name name of app
		@param $var String to be evaled's as an ARRAY structure, name of preference to be deleted
		*/
		function delete_struct($app_name, $var = '')
		{
			$code_1 = '$this->data[$app_name]'.$var.' = "";';
			//echo 'class.preferences: delete_struct: $code_1: '.$code_1.'<br>';
			eval($code_1);
			$code_2 = 'unset($this->data[$app_name]'.$var.');' ;
			//echo 'class.preferences: delete_struct:  $code_2: '.$code_2.'<br>';
			eval($code_2);
			//echo ' * $this->data[$app_name] dump:'; _debug_array($this->data[$app_name]); echo '<br>';
			reset ($this->data);
			return $this->data;
		}


		/*!
		@function save_repository
		@abstract save the the preferences to the repository
		@discussion
		*/
		function save_repository($update_session_info = False)
		{
			$temp_data = $this->data;
			if (! $GLOBALS['phpgw']->acl->check('session_only_preferences',1,'preferences'))
			{
				$this->db->transaction_begin();
				$this->db->query("delete from phpgw_preferences where preference_owner='" . $this->account_id
					. "'",__LINE__,__FILE__);

				if (floor(phpversion()) < 4)
				{
					$pref_info = addslashes(serialize($this->data));
				}
				else
				{
					$pref_info = serialize($this->data);
				}
				$this->db->query("insert into phpgw_preferences (preference_owner,preference_value) values ('"
					. $this->account_id . "','" . $pref_info . "')",__LINE__,__FILE__);

				$this->db->transaction_commit();
			}
			else
			{
				$GLOBALS['phpgw_info']['user']['preferences'] = $this->data;
				$GLOBALS['phpgw']->session->save_repositories();
			}

			if ($GLOBALS['phpgw_info']['server']['cache_phpgw_info'] && $this->account_id == $GLOBALS['phpgw_info']['user']['account_id'])
			{
				$GLOBALS['phpgw']->session->delete_cache($this->account_id);
				$GLOBALS['phpgw']->session->read_repositories(False);
			}
			
			return $temp_data;
		}

		/*!
		@function create_defaults
		@abstract insert a copy of the default preferences for use by real account_id
		@discussion
		@param $account_id numerical id of account for which to create the prefs
		*/
		function create_defaults($account_id)
		{
			$this->db->query("select * from phpgw_preferences where preference_owner='-2'",__LINE__,__FILE__);
			$this->db->next_record();

			if($this->db->f('preference_value'))
			{
				$this->db->query("insert into phpgw_preferences values ('$account_id','"
					. $this->db->f('preference_value') . "')",__LINE__,__FILE__);
			}
			
			if ($GLOBALS['phpgw_info']['server']['cache_phpgw_info'] && $account_id == $GLOBALS['phpgw_info']['user']['account_id'])
			{
				$GLOBALS['phpgw']->session->read_repositories(False);
			}

		}

		/*!
		@function update_data
		@abstract update the preferences array
		@discussion 
		@param $data array of preferences
		*/
		function update_data($data)
		{
			reset($data);
			$this->data = Array();
			$this->data = $data;
			reset($this->data);
			return $this->data;
		}

		/* legacy support */
		function change($app_name,$var,$value = "")
		{
			return $this->add($app_name,$var,$value);
		}
		function commit($update_session_info = True)
		{
			return $this->save_repository($update_session_info);
		}

		/**************************************************************************\
		* These are the non-standard $this->account_id specific functions          *
		\**************************************************************************/

		/*!
		@function verify_basic_settings
		@abstract verify basic settings
		@discussion
		*/
		function verify_basic_settings()
		{
			if (!@is_array($GLOBALS['phpgw_info']['user']['preferences']))
			{
				 $GLOBALS['phpgw_info']['user']['preferences'] = array();
			}
			/* This takes care of new users who dont have proper default prefs setup */
			if (!isset($GLOBALS['phpgw_info']['flags']['nocommon_preferences']) || 
				!$GLOBALS['phpgw_info']['flags']['nocommon_preferences'])
			{
				$preferences_update = False;
				if (!isset($GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs']) || 
					!$GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs'])
				{
					$this->add('common','maxmatchs',15);
					$preferences_update = True;
				}
				if (!isset($GLOBALS['phpgw_info']['user']['preferences']['common']['theme']) || 
					!$GLOBALS['phpgw_info']['user']['preferences']['common']['theme'])
				{
					$this->add('common','theme','default');
					$preferences_update = True;
				}
				if (!isset($GLOBALS['phpgw_info']['user']['preferences']['common']['template_set']) || 
					!$GLOBALS['phpgw_info']['user']['preferences']['common']['template_set'])
				{
					$this->add('common','template_set','default');
					$preferences_update = True;
				}
				if (!isset($GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat']) || 
					!$GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat'])
				{
					$this->add('common','dateformat','m/d/Y');
					$preferences_update = True;
				}
				if (!isset($GLOBALS['phpgw_info']['user']['preferences']['common']['timeformat']) || 
					!$GLOBALS['phpgw_info']['user']['preferences']['common']['timeformat'])
				{
					$this->add('common','timeformat',12);
					$preferences_update = True;
				}
				if (!isset($GLOBALS['phpgw_info']['user']['preferences']['common']['lang']) || 
					!$GLOBALS['phpgw_info']['user']['preferences']['common']['lang'])
				{
					$this->add('common','lang',$GLOBALS['phpgw']->common->getPreferredLanguage());
					$preferences_update = True;
				}
				if ($preferences_update)
				{
					$this->save_repository();
				}
				unset($preferences_update);
			}
		}

		/****************************************************\
		* Email Preferences and Private Support Functions   *
		\****************************************************/

		/*!
		@function sub_get_mailsvr_port
		@abstract Helper function for create_email_preferences, gets mail server port number.
		@discussion This will generate the appropriate port number to access a
		mail server of type pop3, pop3s, imap, imaps users value from
		$phpgw_info['user']['preferences']['email']['mail_port'].
		if that value is not set, it generates a default port for the given $server_type.
		Someday, this *MAY* be 
		(a) a se4rver wide admin setting, or 
		(b)user custom preference
		Until then, simply set the port number based on the mail_server_type, thereof
		ONLY call this function AFTER ['email']['mail_server_type'] has been set.
		@param $prefs - user preferences array based on element ['email'][]
		@author  Angles
		@access Private
		*/
		function sub_get_mailsvr_port($prefs)
		{
			/*// UNCOMMENT WHEN mail_port IS A REAL, USER SET OPTION
			// first we try the port number supplied in preferences
			if ( (isset($prefs['email']['mail_port']))
			&& ($prefs['email']['mail_port'] != '') )
			{
				$port_number = $prefs['email']['mail_port'];
			}
			// preferences does not have a port number, generate a default value
			else
			{
			*/
				switch($prefs['email']['mail_server_type'])
				{
					case 'imaps':
						// IMAP over SSL
						$port_number = 993;
						break;
					case 'pop3s':
						// POP3 over SSL
						$port_number = 995;
						break;
					case 'pop3':
						// POP3 normal connection, No SSL
						// ( same string as normal imap above)
						$port_number = 110;
						break;
					case 'nntp':
						// NNTP news server port
						$port_number = 119;
						break;
					case 'imap':
						// IMAP normal connection, No SSL 
					default:
						// UNKNOWN SERVER in Preferences, return a
						// default value that is likely to work
						// probably should raise some kind of error here
						$port_number = 143;
						break;
				}
				// set the preference string, since it was not set and that's why we are here
				//$prefs['email']['mail_port'] = $port_number;
			// UNCOMMENT WHEN mail_port IS A REAL, USER SET OPTION
			//}
			return $port_number;
		}

		/*!
		@function sub_default_userid
		@abstract Helper function for create_email_preferences, gets default userid for email
		@discussion This will generate the appropriate userid for accessing an email server.
		In the absence of a custom ['email']['userid'], this function should be used to set it.
		@param $accountid - as determined in and/or passed to "create_email_preferences"
		@access Private
		*/
		function sub_default_userid($account_id='')
		{
			if ($GLOBALS['phpgw_info']['server']['mail_login_type'] == 'vmailmgr')
			{
				$prefs_email_userid = $GLOBALS['phpgw']->accounts->id2name($account_id)
					. '@' . $GLOBALS['phpgw_info']['server']['mail_suffix'];
			}
			else
			{
				$prefs_email_userid = $GLOBALS['phpgw']->accounts->id2name($account_id);
			}
			return $prefs_email_userid;
		}

		/*!
		@function sub_default_address
		@abstract Helper function for create_email_preferences, gets default "From:" email address
		@discussion This will generate the appropriate email address used as the "From:" 
		email address when the user sends email, the localpert@domain part. The "personal" 
		part is generated elsewhere.
		In the absence of a custom ['email']['address'], this function should be used to set it.
		@param $accountid - as determined in and/or passed to "create_email_preferences"
		@access Private
		*/
		function sub_default_address($account_id='')
		{
			$prefs_email_address = $GLOBALS['phpgw']->accounts->id2name($account_id)
				. '@' . $GLOBALS['phpgw_info']['server']['mail_suffix'];
			return $prefs_email_address;
		}

		/*!
		@function create_email_preferences
		@abstract create email preferences
		@param $account_id -optional defaults to : get_account_id()
		@discussion fills a local copy of ['email'][] prefs array which is then returned to the calling
		function, which the calling function generally tacks onto the $GLOBALS['phpgw_info'] array as such:
			$GLOBALS['phpgw_info']['user']['preferences'] = $GLOBALS['phpgw']->preferences->create_email_preferences();
		which fills an array based at:
			$GLOBALS['phpgw_info']['user']['preferences']['email'][prefs_are_elements_here]
		Reading the raw preference DB data and comparing to the email preference schema defined in 
		/email/class.bopreferences.inc.php (see discussion there and below) to create default preference values 
		for the  in the ['email'][] pref data array in cases where the user has not supplied 
		a preference value for any particular preference item available to the user.
		@access Public
		*/
		function create_email_preferences($accountid='', $acctnum='')
		{
			if ($this->debug_init_prefs > 0) { echo 'class.preferences: create_email_preferences: ENTERING<br>'; }
			// we may need function "html_quotes_decode" from the mail_msg class
			$email_base = CreateObject("email.mail_msg");

			$account_id = get_account_id($accountid);
			// If the current user is not the request user, grab the preferences
			// and reset back to current user.
			if($account_id != $this->account_id)
			{
				// Temporarily store the values to a temp, so when the
				// read_repository() is called, it doesn't destory the
				// current users settings.
				$temp_account_id = $this->account_id;
				$temp_data = $this->data;

				// Grab the new users settings, only if they are not the
				// current users settings.
				$this->account_id = $account_id;
				$prefs = $this->read_repository();

				// Reset the data to what it was prior to this call
				$this->account_id = $temp_account_id;
				$this->data = $temp_data;
			}
			else
			{
				$prefs = $this->data;
			}
			// are we dealing with the default email account or an extra email account?
			if (!(isset($acctnum)) || ((string)$acctnum == ''))
			{
				settype($acctnum,'integer');
				// account 0 is the default email account
				$acctnum = 0;
				// $prefs stays AS IS!
			}
			else
			{
				// prefs are actually a sub-element of the main email prefs
				// at location [email][ex_accounts][X][...pref names] => pref values
				// make this look like "prefs[email] so the code below code below will do its job transparently
				
				// store original prefs
				$orig_prefs = array();
				$orig_prefs = $prefs;
				// obtain the desired sub-array of extra account prefs
				$sub_prefs = array();
				$sub_prefs['email'] = $prefs['email']['ex_accounts'][$acctnum];
				// make the switch, make it seem like top level email prefs
				$prefs = array();
				$prefs['email'] = $sub_prefs['email'];
				// since we return just $prefs, it's up to the calling program to put the sub prefs in the right place
			}

			if ($this->debug_init_prefs > 0)
			{
				echo 'class.preferences: create_email_preferences: $acctnum: ['.$acctnum.'] ; raw $this->data dump';
				_debug_array($this->data);
			}

			// = = = =  NOT-SIMPLE  PREFS  = = = =
			// Default Preferences info that is:
			// (a) not controlled by email prefs itself (mostly api and/or server level stuff)
			// (b) too complicated to be described in the email prefs data array instructions
			
			// ---  [server][mail_server_type]  ---
			// Set API Level Server Mail Type if not defined
			// if for some reason the API didnot have a mail server type set during initialization
			if (empty($GLOBALS['phpgw_info']['server']['mail_server_type']))
			{
				$GLOBALS['phpgw_info']['server']['mail_server_type'] = 'imap';
			}

			// ---  [server][mail_folder]  ---
			// ====  UWash Mail Folder Location used to be "mail", now it's changeable, but keep the
			// ====  default to "mail" so upgrades happen transparently
			// ---  TEMP MAKE DEFAULT UWASH MAIL FOLDER ~/mail (a.k.a. $HOME/mail)
			$GLOBALS['phpgw_info']['server']['mail_folder'] = 'mail';
			// ---  DELETE THE ABOVE WHEN THIS OPTION GETS INTO THE SYSTEM SETUP
			// pick up custom "mail_folder" if it exists (used for UWash and UWash Maildir servers)
			// else use the system default (which we temporarily hard coded to "mail" just above here)

			//---  [email][newsmode]  ---
			// == Orphan == currently NOT USED
			// This is going to be used to switch to the nntp class
			if ((isset($GLOBALS['phpgw_info']['flags']['newsmode']) &&
				$GLOBALS['phpgw_info']['flags']['newsmode']))
			{
				$prefs['email']['mail_server_type'] = 'nntp';
			}

			//---  [email][mail_port]  ---
			// These sets the mail_port server variable
			// someday (not currently) this may be a site-wide property set during site setup
			// additionally, someday (not currently) the user may be able to override this with
			// a custom email preference. Currently, we simply use standard port numbers
			// for the service in question.
			$prefs['email']['mail_port'] = $this->sub_get_mailsvr_port($prefs);
			
			//---  [email][fullname]  ---
			// we pick this up from phpgw api for the default account
			// the user does not directly manipulate this pref for the default email account
			if ((string)$acctnum == '0')
			{
				$prefs['email']['fullname'] = $GLOBALS['phpgw_info']['user']['fullname'];
			}
			
			
			// = = = =  SIMPLER PREFS  = = = =

			// Default Preferences info that is articulated in the email prefs schema array itself
			// such email prefs schema array is described and established in /email/class.bopreferences
			// by function "init_available_prefs", see the discussion there.

			// --- create the objectified /email/class.bopreferences.inc.php ---
			$bo_mail_prefs = CreateObject('email.bopreferences');

			// --- bo_mail_prefs->init_available_prefs() ---
			// this fills object_email_bopreferences->std_prefs and ->cust_prefs
			// we will initialize the users preferences according to the rules and instructions
			// embodied in those prefs arrays, applying those rules to the unprocessed
			// data read from the preferences DB. By taking the raw data and applying those rules,
			// we will construct valid and known email preference data for this user.
			$bo_mail_prefs->init_available_prefs();

			// --- combine the two array (std and cust) for 1 pass handling ---
			// when this preference DB was submitted and saved, it was hopefully so well structured
			// that we can simply combine the two arrays, std_prefs and cust_prefs, and do a one
			// pass analysis and preparation of this users preferences.
			$avail_pref_array = $bo_mail_prefs->std_prefs;
			$c_cust_prefs = count($bo_mail_prefs->cust_prefs);
			for($i=0;$i<$c_cust_prefs;$i++)
			{
				// add each custom prefs to the std prefs array
				$next_idx = count($avail_pref_array);
				$avail_pref_array[$next_idx] = $bo_mail_prefs->cust_prefs[$i];
			}
			if ($this->debug_init_prefs > 1)
			{
				echo 'class.preferences: create_email_preferences: std AND cust arrays combined:';
				_debug_array($avail_pref_array);
			}

			// --- make the schema-based pref data for this user ---
			// user defined values and/or user specified custom email prefs are read from the
			// prefs DB with mininal manipulation of the data. Currently the only change to 
			// users raw data is related to reversing the encoding of "database un-friendly" chars
			// which itself may become unnecessary if and when the database handlers can reliably 
			// take care of this for us. Of course, password data requires special decoding,
			// but the password in the array [email][paswd] should be left in encrypted form 
			// and only decrypted seperately when used to login in to an email server.

			// --- generating a default value if necessary ---
			// in the absence of a user defined custom email preference for a particular item, we can
			// determine the desired default value for that pref as such:
			// $this_avail_pref['init_default']  is a comma seperated seperated string which should
			// be exploded into an array containing 2 elements that are:
			// exploded[0] : an description of how to handle the next string element to get a default value.
			// Possible "instructional tokens" for exploded[0] (called $set_proc[0] below) are:
			//	string
			//	set_or_not
			//	function
			//	init_no_fill
			//	varEVAL
			// tells you how to handle the string in exploded[1] (called $set_proc[1] below) to get a valid
			// default value for a particular preference if one is needed (i.e. if no user custom
			// email preference exists that should override that default value, in which case we
			// do not even need to obtain such a default value as described in ['init_default'] anyway).
			
			// --- loop thru $avail_pref_array and process each pref item ---
			$c_prefs = count($avail_pref_array);
			for($i=0;$i<$c_prefs;$i++)
			{
				$this_avail_pref = $avail_pref_array[$i];
				if ($this->debug_init_prefs > 1) { echo 'class.preferences: create_email_preferences: value from DB for $prefs[email]['.$this_avail_pref['id'].'] = ['.$prefs['email'][$this_avail_pref['id']].']<br>'; }
				if ($this->debug_init_prefs > 2)
				{
					echo 'class.preferences: create_email_preferences: std/cust_prefs $this_avail_pref['.$i.'] dump:';
					_debug_array($this_avail_pref);
				}

				// --- is there a value in the DB for this preference item ---
				// if the prefs DB has no value for this defined available preference, we must make one.
				// This occurs if (a) this is user's first login, or (b) this is a custom pref which the user 
				// has not overriden, do a default (non-custom) value is needed.
				if (!isset($prefs['email'][$this_avail_pref['id']]))
				{
					// now we are analizing an individual pref that is available to the user
					// AND the user had no existing value in the prefs DB for this.

					// --- get instructions on how to generate a default value ---
					$set_proc = explode(',', $this_avail_pref['init_default']);
					if ($this->debug_init_prefs > 1) { echo ' * set_proc=['.serialize($set_proc).']<br>'; }

					// --- use "instructional token" in $set_proc[0] to take appropriate action ---
					// STRING
					if ($set_proc[0] == 'string')
					{
						// means this pref item's value type is string
						// which defined string default value is in $set_proc[1]
						if ($this->debug_init_prefs > 2) { echo ' * handle "string" set_proc: '.serialize($set_proc).'<br>'; }
						if (trim($set_proc[1]) == '')
						{
							// this happens when $this_avail_pref['init_default'] = "string, "
							$this_string = '';
						}
						else
						{
							$this_string = $set_proc[1];
						}
						$prefs['email'][$this_avail_pref['id']] = $this_string;
					}
					// SET_OR_NOT
					elseif ($set_proc[0] == 'set_or_not')
					{
						// typical with boolean options, True = "set/exists" and False = unset
						if ($this->debug_init_prefs > 2) { echo ' * handle "set_or_not" set_proc: '.serialize($set_proc).'<br>'; }
						if ($set_proc[1] == 'not_set')
						{
							// leave it NOT SET
						}
						else
						{
							// opposite of boolean not_set  = string "True" which simply sets a 
							// value it exists in the users session [email][] preference array
							$prefs['email'][$this_avail_pref['id']] = 'True';
						}
					}
					// FUNCTION
					elseif ($set_proc[0] == 'function')
					{
						// string in $set_proc[1] should be "eval"uated as code, calling a function
						// which will give us a default value to put in users session [email][] prefs array
						if ($this->debug_init_prefs > 2) { echo ' * handle "function" set_proc: '.serialize($set_proc).'<br>'; }
						$evaled = '';
						//eval('$evaled = $this->'.$set_proc[1].'('.$account_id.');');

						$code = '$evaled = $this->'.$set_proc[1].'('.$account_id.');';
						if ($this->debug_init_prefs > 2) { echo ' * $code: '.$code.'<br>'; }
						eval($code);

						//$code = '$evaled = '.$set_proc[1];
						//if ($this->debug_init_prefs > 1) { echo ' * $code: '.$code.'<br>'; }
						//eval($code);

						if ($this->debug_init_prefs > 2) { echo ' * $evaled: '.$evaled.'<br>'; }
						$prefs['email'][$this_avail_pref['id']] = $evaled;
					}
					// INIT_NO_FILL
					elseif ($set_proc[0] == 'init_no_fill')
					{
						// we have an available preference item that we may NOT fill with a default 
						// value. Only the user may supply a value for this pref item.
						if ($this->debug_init_prefs > 1) { echo ' * handle "init_no_fill" set_proc: '.serialize($set_proc).'<br>'; }
						// we are FORBADE from filling this at this time!
					}
					// varEVAL
					elseif ($set_proc[0] == 'varEVAL')
					{
						// similar to "function" but used for array references, the string in $set_proc[1] 
						// represents code which typically is an array referencing a system/api property
						if ($this->debug_init_prefs > 2) { echo ' * handle "GLOBALS" set_proc: '.serialize($set_proc).'<br>'; }
						$evaled = '';
						$code = '$evaled = '.$set_proc[1];
						if ($this->debug_init_prefs > 2) { echo ' * $code: '.$code.'<br>'; }
						eval($code);
						if ($this->debug_init_prefs > 2) { echo ' * $evaled: '.$evaled.'<br>'; }
						$prefs['email'][$this_avail_pref['id']] = $evaled;
					}
					else
					{
						// error, no instructions on how to handle this element's default value creation
						echo 'class.preferences: create_email_preferences: set_proc ERROR: '.serialize($set_proc).'<br>';
					}
				}
				else
				{
					// we have a value in the database, do we need to prepare it in any way?
					// (the following discussion is unconfirmed:)
					// DO NOT ALTER the data in the prefs array!!!! or the next time we call
					// save_repository withOUT undoing what we might do here, the
					// prefs will permenantly LOOSE the very thing(s) we are un-doing
					/// here until the next OFFICIAL submit email prefs function, where it
					// will again get this preparation before being written to the database.

					// NOTE: if database de-fanging is eventually handled deeper in the 
					// preferences class, then the following code would become depreciated 
					// and should be removed in that case.
					if (($this_avail_pref['type'] == 'user_string') &&
						(stristr($this_avail_pref['write_props'], 'no_db_defang') == False))
					{
						// this value was "de-fanged" before putting it in the database
						// undo that defanging now
						$db_unfriendly = $email_base->html_quotes_decode($prefs['email'][$this_avail_pref['id']]);
						$prefs['email'][$this_avail_pref['id']] = $db_unfriendly;
					}
				}
			}
			// users preferences are now established to known structured values...

			// SANITY CHECK 
			// ---  [email][use_trash_folder]  ---
			// ---  [email][use_sent_folder]  ---
			// is it possible to use Trash and Sent folders - i.e. using IMAP server
			// if not - force settings to false
			if (stristr($prefs['email']['mail_server_type'], 'imap') == False)
			{
				if (isset($prefs['email']['use_trash_folder']))
				{
					unset($prefs['email']['use_trash_folder']);
				}

				if (isset($prefs['email']['use_sent_folder']))
				{
					unset($prefs['email']['use_sent_folder']);
				}
			}

			// DEBUG : force some settings to test stuff
			//$prefs['email']['p_persistent'] = 'True';
			
			if ($this->debug_init_prefs > 1)
			{
				echo 'class.preferences: $acctnum: ['.$acctnum.'] ; create_email_preferences: $prefs[email]';
				_debug_array($prefs['email']);
			}
			
			if ($this->debug_init_prefs > 0) { echo 'class.preferences: create_email_preferences: LEAVING<br>'; }
			return $prefs;
		}

			/*
			// ==== DEPRECIATED - ARCHIVAL CODE ====
			// used to be part of function this->create_email_preferences()
			// = = = =  SIMPLER PREFS  = = = =
			// Default Preferences info that is:
			// described in the email prefs array itself

			$default_trash_folder = 'Trash';
			$default_sent_folder = 'Sent';

			// ---  userid  ---
			if (!isset($prefs['email']['userid']))
			{
				$prefs['email']['userid'] = $this->sub_default_userid($accountid);
			}
			// ---  address  --- 
			if (!isset($prefs['email']['address']))
			{
				$prefs['email']['address'] = $this->sub_default_address($accountid);
			}
			// ---  mail_server  ---
			if (!isset($prefs['email']['mail_server']))
			{
				$prefs['email']['mail_server'] = $GLOBALS['phpgw_info']['server']['mail_server'];
			}
			// ---  mail_server_type  ---
			if (!isset($prefs['email']['mail_server_type']))
			{
				$prefs['email']['mail_server_type'] = $GLOBALS['phpgw_info']['server']['mail_server_type'];
			}
			// ---  imap_server_type  ---
			if (!isset($prefs['email']['imap_server_type']))
			{
				$prefs['email']['imap_server_type'] = $GLOBALS['phpgw_info']['server']['imap_server_type'];
			}
			// ---  mail_folder  ---
			// because of the way this option works, an empty string IS ACTUALLY a valid value
			// which represents the $HOME/* as the UWash mail files location
			// THERFOR we must check the "Use_custom_setting" option to help us figure out what to do
			if (!isset($prefs['email']['use_custom_settings']))
			{
				// we are NOT using custom settings so this MUST be the server default
				$prefs['email']['mail_folder'] = $GLOBALS['phpgw_info']['server']['mail_folder'];
			}
			else
			{
				// we ARE using custom settings AND a BLANK STRING is a valid option, so...
				if ((isset($prefs['email']['mail_folder']))
				&& ($prefs['email']['mail_folder'] != ''))
				{
					// using custom AND a string exists, so "mail_folder" is that string stored in the custom prefs by the user
					// DO NOTING - VALID OPTION VALUE for $prefs['email']['mail_folder']
				}
				else
				{
					// using Custom Prefs BUT this text box was left empty by the user on submit, so no value stored
					// BUT since we are using custom prefs, "mail_folder" MUST BE AN EMPTY STRING
					// which is an acceptable, valid preference, overriding any value which
					// may have been set in ["server"]["mail_folder"]
					// This is one of the few instances in the preference class where an empty, unspecified value
					// actually does NOT get deleted from the repository.
					$prefs['email']['mail_folder'] = '';
				}
			}

			// ---  use_trash_folder  ---
			// ---  trash_folder_name  ---
			// if the option to use the Trash folder is ON, make sure a proper name is specified
			if (isset($prefs['email']['use_trash_folder']))
			{
				if ((!isset($prefs['email']['trash_folder_name']))
				|| ($prefs['email']['trash_folder_name'] == ''))
				{
					$prefs['email']['trash_folder_name'] = $default_trash_folder;
				}
			}

			// ---  use_sent_folder  ---
			// ---  sent_folder_name  ---
			// if the option to use the sent folder is ON, make sure a proper name is specified
			if (isset($prefs['email']['use_sent_folder']))
			{
				if ((!isset($prefs['email']['sent_folder_name']))
				|| ($prefs['email']['sent_folder_name'] == ''))
				{
					$prefs['email']['sent_folder_name'] = $default_sent_folder;
				}
			}

			// ---  layout  ---
			// Layout Template Preference
			// layout 1 = default ; others are prefs
			if (!isset($prefs['email']['layout']))
			{
				$prefs['email']['layout'] = 1;
			}

			//// ---  font_size_offset  ---
			//// Email Index Page Font Size Preference
			//// layout 1 = default ; others are prefs
			//if (!isset($prefs['email']['font_size_offset']))
			//{
			//	$prefs['email']['font_size_offset'] = 'normal';
			//}

			// SANITY CHECK 
			// ---  use_trash_folder  ---
			// ---  use_sent_folder  ---
			// is it possible to use Trash and Sent folders - i.e. using IMAP server
			// if not - force settings to false
			if (stristr($prefs['email']['mail_server_type'], 'imap') == False)
			{
				if (isset($prefs['email']['use_trash_folder']))
				{
					unset($prefs['email']['use_trash_folder']);
				}
				
				if (isset($prefs['email']['use_sent_folder']))
				{
					unset($prefs['email']['use_sent_folder']);
				}
			}

			// DEBUG : force some settings to test stuff
			//$prefs['email']['layout'] = 1;
			//$prefs['email']['layout'] = 2;
			//$prefs['email']['font_size_offset'] = (-1);

			// DEBUG
			//echo "<br>prefs['email']: <br>"
			//	.'<pre>'.serialize($prefs['email']) .'</pre><br>';
			return $prefs;
			*/
	} /* end of preferences class */
?>
