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
				$GLOBALS['phpgw']->session->read_repositories(False);
			}
			
			return $temp_data;
		}

		function create_defaults($account_id)
		{
			$this->db->query("select * from phpgw_preferences where preference_owner='-2'",__LINE__,__FILE__);
			$this->db->next_record();

			$this->db->query("insert into phpgw_preferences values ('$account_id','"
				. $this->db->f('preference_value') . "')",__LINE__,__FILE__);
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
			if (gettype($GLOBALS['phpgw_info']['user']['preferences']) != 'array')
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

		/*!
		@function get_mailsvr_port
		@abstract get_mailsvr_port
		@discussion This will generate the appropriate port number to access a
			mail server of type pop3, pop3s, imap, imaps users value from
			$phpgw_info['user']['preferences']['email']['mail_port'].
			if that value is not set, it generates a default port for the given
			$server_type
		@param $prefs - any user preferences
		*/	
		function get_mailsvr_port($prefs)
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
					case 'imaps':		// IMAP over SSL
						$port_number = 993;
						break;
					case 'pop3s':		// POP3 over SSL
						$port_number = 995;
						break;
					case 'pop3':		// POP3 normal connection, No SSL
								// ( same string as normal imap above)
						$port_number = 110;
						break;
					case 'nntp':		// NNTP news server port
						$port_number = 119;
						break;
					case 'imap':		// IMAP normal connection, No SSL 
					default:		// UNKNOWN SERVER in Preferences, return a
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
		@function create_email_preferences
		@abstract create email preferences
		@discussion This fills the global $phpgw_info array with the required email preferences for this user
		@param $account_id -optional defaults to : phpgw_info['user']['account_id']
		*/	
		function create_email_preferences($accountid='')
		{

			$default_trash_folder = 'Trash';
			$default_sent_folder = 'Sent';


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

			// Add default preferences info
			if (!isset($prefs['email']['userid']))
			{
				if ($GLOBALS['phpgw_info']['server']['mail_login_type'] == 'vmailmgr')
				{
					$prefs['email']['userid'] = $GLOBALS['phpgw']->accounts->id2name($account_id)
						. '@' . $GLOBALS['phpgw_info']['server']['mail_suffix'];
				}
				else
				{
					$prefs['email']['userid'] = $GLOBALS['phpgw']->accounts->id2name($account_id);
				}
			}
			// Set Server Mail Type if not defined
			if (empty($GLOBALS['phpgw_info']['server']['mail_server_type']))
			{
				$GLOBALS['phpgw_info']['server']['mail_server_type'] = 'imap';
			}
			if (!isset($prefs['email']['address']))
			{
				$prefs['email']['address'] = $GLOBALS['phpgw']->accounts->id2name($account_id)
					. '@' . $GLOBALS['phpgw_info']['server']['mail_suffix'];
			}
			if (!isset($prefs['email']['mail_server']))
			{
				$prefs['email']['mail_server'] = $GLOBALS['phpgw_info']['server']['mail_server'];
			}
			if (!isset($prefs['email']['mail_server_type']))
			{
				$prefs['email']['mail_server_type'] = $GLOBALS['phpgw_info']['server']['mail_server_type'];
			}
			if (!isset($prefs['email']['imap_server_type']))
			{
				$prefs['email']['imap_server_type'] = $GLOBALS['phpgw_info']['server']['imap_server_type'];
			}
		
			// ====  UWash Mail Folder Location used to be "mail", now it's changeable, but keep the
			// ====  default to "mail" so upgrades happen transparently
			// ---  TEMP MAKE DEFAULT UWASH MAIL FOLDER ~/mail (a.k.a. $HOME/mail)
			$GLOBALS['phpgw_info']['server']['mail_folder'] = 'mail';
			// ---  DELETE THE ABOVE WHEN THIS OPTION GETS INTO THE SYSTEM SETUP
			// pick up custom "mail_folder" if it exists (used for UWash and UWash Maildor servers)
			// else use the system default (which we temporarily hard coded to "mail" just above here)
		
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
					// using custom AND an string exists, so "mail_folder" is that string stored in the custom prefs by the user
					// DO NOTING - VALID OPTION VALUE for $prefs['email']['mail_folder']
				}
				else
				{
					// using Custom Prefs BUT this text box was left empty by the user on submit, so no value stored
					// BUT since we are using custom prefs, "mail_folder" MUST BE AN EMPTY STRING
					// which is an acceptable, valid preference, overriding any value which may have been set in ["server"]["mail_folder"]
					$prefs['email']['mail_folder'] = '';
				}
			}

			// This is going to be used to switch to the nntp class
			if ((isset($GLOBALS['phpgw_info']['flags']['newsmode'])
			&& $GLOBALS['phpgw_info']['flags']['newsmode']))
			{
				$prefs['email']['mail_server_type'] = 'nntp';
			}

			// These sets the mail_port server variable
			$prefs['email']['mail_port'] = $this->get_mailsvr_port($prefs);

			// if the option to use the Trash folder is ON, make sure a proper name is specified
			if (isset($prefs['email']['use_trash_folder']))
			{
				if ((!isset($prefs['email']['trash_folder_name']))
				|| ($prefs['email']['trash_folder_name'] == ''))
				{
					$prefs['email']['trash_folder_name'] = $default_trash_folder;
				}
			}

			// if the option to use the sent folder is ON, make sure a proper name is specified
			if (isset($prefs['email']['use_sent_folder']))
			{
				if ((!isset($prefs['email']['sent_folder_name']))
				|| ($prefs['email']['sent_folder_name'] == ''))
				{
					$prefs['email']['sent_folder_name'] = $default_sent_folder;
				}
			}	

			// SANITY CHECK - is it possible to use Trash and Sent folders - i.e. using IMAP server
			// if not - force settings to false
			if  (($prefs['email']['mail_server_type'] != 'imap')
			&& ($prefs['email']['mail_server_type'] != 'imaps'))
			{
				if (isset($prefs['email']['use_sent_folder']))
				{
					unset($prefs['email']['use_sent_folder']);
				}
	
				if (isset($prefs['email']['use_trash_folder']))
				{
					unset($prefs['email']['use_trash_folder']);
				}
			}

			// Layout Template Preference
			// layout 1 = default ; others are prefs
			if (!isset($prefs['email']['layout']))
			{
				$prefs['email']['layout'] = 1;
			}
			// force seeting here to test stuff
			//$prefs['email']['layout'] = 1;
			//$prefs['email']['layout'] = 2;

			// DEBUG
			//echo "<br>prefs['email']: <br>"
			//	.'<pre>'.serialize($prefs['email']) .'</pre><br>';
			return $prefs;
		}
	} /* end of preferences class */
?>
