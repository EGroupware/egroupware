<?php
  /**************************************************************************\
  * phpGroupWare API - VFS                                                   *
  * This file written by Jason Wies (Zone) <zone@phpgroupware.org>           *
  * This class handles file/dir access for phpGroupWare                      *
  * Copyright (C) 2001-2003 Jason Wies, Jonathon Sim		                 *
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


	/* Relative defines.  Used mainly by getabsolutepath () */
	define ('RELATIVE_ROOT', 1);
	define ('RELATIVE_USER', 2);
	define ('RELATIVE_CURR_USER', 4);
	define ('RELATIVE_USER_APP', 8);
	define ('RELATIVE_PATH', 16);
	define ('RELATIVE_NONE', 32);
	define ('RELATIVE_CURRENT', 64);
	define ('VFS_REAL', 1024);
	define ('RELATIVE_ALL', RELATIVE_PATH);

	/* These are used in calls to extra_sql () */
	define ('VFS_SQL_SELECT', 1);
	define ('VFS_SQL_DELETE', 2);
	define ('VFS_SQL_UPDATE', 4);

	/* These are used in calls to add_journal (), and allow journal messages to be more standard */
	define ('VFS_OPERATION_CREATED', 1);
	define ('VFS_OPERATION_EDITED', 2);
	define ('VFS_OPERATION_EDITED_COMMENT', 4);
	define ('VFS_OPERATION_COPIED', 8);
	define ('VFS_OPERATION_MOVED', 16);
	define ('VFS_OPERATION_DELETED', 32);

	/*Different aspects of debugging. DEBUG enables debug output for this class
	DEBUG_SQL enables some SQL debugging.  DEBUG_DAV enables (LOTS) of debugging inside
	the HTTP class
	*/
	define ('DEBUG', 0);
	define ('TRACE', 0);//This generates a whole lotta output
	define ('DEBUG_SQL', 0);
	define ('DEBUG_DAV', 0);

	/*!
	@class path_class
	@abstract helper class for path_parts
	*/

	class path_class
	{
		var $mask;
		var $outside;
		var $fake_full_path;
		var $fake_leading_dirs;
		var $fake_extra_path;
		var $fake_name;
		var $real_full_url;
		var $real_full_auth_url;
		var $real_full_secure_url;
		var $real_full_path;
		var $real_leading_dirs;
		var $real_extra_path;
		var $real_name;
		var $fake_full_path_clean;
		var $fake_leading_dirs_clean;
		var $fake_extra_path_clean;
		var $fake_name_clean;
		var $real_full_url_clean;
		var $real_full_auth_url_clean;
		var $real_full_secure_url_clean;
		var $real_full_path_clean;
		var $real_leading_dirs_clean;
		var $real_extra_path_clean;
		var $real_name_clean;
	}

	class vfs
	{
		var $basedir;
		var $fakebase;
		var $relative;
		var $working_id;
		var $working_lid;
		var $attributes;
		var $override_acl;
		var $linked_dirs;
		var $meta_types;
		var $now;
		var $override_locks;
		//These are DAV-native properties that have different names in VFS
		var $vfs_property_map = array(
			'creationdate' => 'created',
			'getlastmodified' => 'modified',
			'getcontentlength' => 'size',
			'getcontenttype' => 'mime_type',
			'description' => 'comment',
			'creator_id' => 'createdby_id',
			'contributor_id' => 'modifiedby_id',
			'publisher_id' => 'owner_id'
		);
		
		/*!
		@function vfs
		@abstract constructor, sets up variables
		*/

		function vfs ()
		{
			$this->basedir = $GLOBALS['phpgw_info']['server']['files_dir'];
			$this->fakebase = '/home';
			$this->working_id = $GLOBALS['phpgw_info']['user']['account_id'];
			$this->working_lid = $GLOBALS['phpgw']->accounts->id2name($this->working_id);
			$this->now = date ('Y-m-d');
			$this->override_acl = 0;
			/*
			   File/dir attributes, each corresponding to a database field.  Useful for use in loops
			   If an attribute was added to the table, add it here and possibly add it to
			   set_attributes ()

			   set_attributes now uses this array().   07-Dec-01 skeeter
			*/

			$this->attributes = array(
				'file_id',
				'owner_id',
				'createdby_id',
				'modifiedby_id',
				'created',
				'modified',
				'size',
				'mime_type',
				'deleteable',
				'comment',
				'app',
				'directory',
				'name',
				'link_directory',
				'link_name',
				'version'
			);

			/*
			   These are stored in the MIME-type field and should normally be ignored.
			   Adding a type here will ensure it is normally ignored, but you will have to
			   explicitly add it to acl_check (), and to any other SELECT's in this file
			*/

			$this->meta_types = array ('journal', 'journal-deleted');

			/* We store the linked directories in an array now, so we don't have to make the SQL call again */
			if ($GLOBALS['phpgw_info']['server']['db_type']=='mssql'
				|| $GLOBALS['phpgw_info']['server']['db_type']=='sybase')
			{
				$query = $GLOBALS['phpgw']->db->query ("SELECT directory, name, link_directory, link_name FROM phpgw_vfs WHERE CONVERT(varchar,link_directory) != '' AND CONVERT(varchar,link_name) != ''" . $this->extra_sql (array ('query_type' => VFS_SQL_SELECT)), __LINE__,__FILE__);
			}
			else
			{
				$query = $GLOBALS['phpgw']->db->query ("SELECT directory, name, link_directory, link_name FROM phpgw_vfs WHERE (link_directory IS NOT NULL or link_directory != '') AND (link_name IS NOT NULL or link_name != '')" . $this->extra_sql (array ('query_type' => VFS_SQL_SELECT)), __LINE__,__FILE__);
			}

			$this->linked_dirs = array ();
			while ($GLOBALS['phpgw']->db->next_record ())
			{
				$this->linked_dirs[] = $GLOBALS['phpgw']->db->Record;
			}

			
			$this->repository = $GLOBALS['phpgw_info']['server']['files_dir'];
			$this->dav_user=$GLOBALS['phpgw_info']['user']['userid'];
			$this->dav_pwd=$GLOBALS['phpgw_info']['user']['passwd'];
			$parsed_url = parse_url($this->repository);
			$this->dav_host=$parsed_url['host'];
			$this->dav_port=@isset($parsed_url['port']) ? $parsed_url['port'] : 80;

			$this->dav_client = CreateObject('phpgwapi.http_dav_client');
			$this->dav_client->set_credentials($this->dav_user,$this->dav_pwd);
			$this->dav_client->set_attributes($this->attributes,$this->vfs_property_map);
			$result = $this->dav_client->connect($this->dav_host,$this->dav_port);
			if (DEBUG_DAV) 
			{
				echo '<b>DAV client debugging enabled!</b>';
				$this->dav_client->set_debug(DBGTRACE|DBGINDATA|DBGOUTDATA|DBGSOCK|DBGLOW);
			}
			if (!$result)
			{
				echo '<h2>Cannot connect to the file repository server!</h2>';
				die($this->dav_client->get_body());
			}
			//determine the supported DAV features
/*			$features = $this->dav_client->dav_features('http://'.$this->dav_host);
			if (!$features || ! in_array( '1', $features) )
			{
				die("Error :: The specified file repository: $this->dav_host doesn't appear to support WebDAV! ");
			
			}
*/	
			//Reload the overriden_locks
			$app = $GLOBALS['phpgw_info']['flags']['currentapp'];
			$session_data = base64_decode($GLOBALS['phpgw']->session->appsession ('vfs_dav',$app));
			$this->override_locks = array();
			if ($session_data)
			{
				$locks = explode('\n', $session_data);	
				foreach ($locks as $lock)
				{
					$lockdata = explode(';', $lock);
					$name = $lockdata[0]; 
					$token = $lockdata[1];
					$this->override_locks[$name] = $token;
				}
			}

				register_shutdown_function(array(&$this, 'vfs_umount'));
				$this->debug('Constructed with debug enabled');
			
		}

		//TODO:  Get rid of this
		//A quick, temporary debug output function
		function debug($info) {
			if (DEBUG)
			{
				echo '<b> vfs_sql_dav debug:<em> ';
				if (is_array($info))
				{
					print_r($info);
				}
				else
				{
					echo $info;
				}
				echo '</em></b><br>';
			}
		}

		/*!
		@function dav_path
		@abstract Apaches mod_dav in particular requires that the path sent in a dav request NOT be a URI
		*/
		function dav_path($uri) {
			//$this->debug('DAV path');
			$parsed = parse_url($uri);
			return $parsed['path'];
		}

		/*!
		@function glue_url
		@abstract glues a parsed url (ie parsed using PHP's parse_url) back
			together
		@param $url	The parsed url (its an array)
		*/
		function glue_url ($url){
			if (!is_array($url))
			{
				return false;
			}
			// scheme
			$uri = (!empty($url['scheme'])) ? $url['scheme'].'://' : '';
			// user & pass
			if (!empty($url['user']))
			{
				$uri .= $url['user'];
				if (!empty($url['pass']))
				{
					$uri .=':'.$url['pass'];
				}
				$uri .='@'; 
			}
			// host 
			$uri .= $url['host'];
			// port 
			$port = (!empty($url['port'])) ? ':'.$url['port'] : '';
			$uri .= $port; 
			// path 
			$uri .= $url['path'];
			// fragment or query
			if (isset($url['fragment']))
			{
				$uri .= '#'.$url['fragment'];
			} elseif (isset($url['query']))
			{
				$uri .= '?'.$url['query'];
			}
			return $uri;
		}

		function dav_host($uri) {
			//$this->debug('DAV path');
			$parsed = parse_url($uri);
			$parsed['path'] = '';
			$host = $this->glue_url($parsed);
			return $host;
		}

		function vfs_umount()
		{
			$this->dav_client->disconnect();
		}


		/*!
		@function set_relative
		@abstract Set path relativity
		@param mask Relative bitmask (see RELATIVE_ defines)
		*/
		function set_relative ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

			if (!$data['mask'])
			{
				unset ($this->relative);
			}
			else
			{
				$this->relative = $data['mask'];
			}
		}

		/*!
		@function get_relative
		@abstract Return relativity bitmask
		@discussion Returns relativity bitmask, or the default of "completely relative" if unset
		*/
		function get_relative ()
		{
			if (isset ($this->relative) && $this->relative)
			{
				return $this->relative;
			}
			else
			{
				return RELATIVE_ALL;
			}
		}

		/*!
		@function sanitize
		@abstract Removes leading .'s from 'string'
		@discussion You should not pass all filenames through sanitize () unless you plan on rejecting
				.files.  Instead, pass the name through securitycheck () first, and if it fails,
				pass it through sanitize
		@param string string to sanitize
		@result $string 'string' without it's leading .'s
		*/
		function sanitize ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

			/* We use path_parts () just to parse the string, not translate paths */
			$p = $this->path_parts (array(
					'string' => $data['string'],
					'relatives' => array (RELATIVE_NONE)
				)
			);

			return (ereg_replace ('^\.+', '', $p->fake_name));
		}

		/*!
		@function securitycheck
		@abstract Security check function
		@discussion Checks for basic violations such as ..
				If securitycheck () fails, run your string through vfs->sanitize ()
		@param string string to check security of
		@result Boolean True/False.  True means secure, False means insecure
		*/
		function securitycheck ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

			if (substr ($data['string'], 0, 1) == "\\" || strstr ($data['string'], "..") || strstr ($data['string'], "\\..") || strstr ($data['string'], ".\\."))
			{
				return False;
			}
			else
			{
				return True;
			}
		}

		/*!
		@function db_clean
		@abstract Clean 'string' for use in database queries
		@param string String to clean
		@result Cleaned version of 'string'
		*/
		function db_clean ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

			$string = ereg_replace ("'", "\'", $data['string']);

			return $string;
		}

		/*!
		@function extra_sql
		@abstract Return extra SQL code that should be appended to certain queries
		@param query_type The type of query to get extra SQL code for, in the form of a VFS_SQL define
		@result Extra SQL code
		*/
		function extra_sql ($data)
		{ //This is purely for SQL
			return '';
		}

		/*!
		@function add_journal
		@abstract Add a journal entry after (or before) completing an operation,
			  and increment the version number.  This function should be used internally only
		@discussion Note that state_one and state_two are ignored for some VFS_OPERATION's, for others
			    they are required.  They are ignored for any "custom" operation
			    The two operations that require state_two:
			    operation			state_two
			    VFS_OPERATION_COPIED	fake_full_path of copied to
			    VFS_OPERATION_MOVED		fake_full_path of moved to

			    If deleting, you must call add_journal () before you delete the entry from the database
		@param string File or directory to add entry for
		@param relatives Relativity array
		@param operation The operation that was performed.  Either a VFS_OPERATION define or
				  a non-integer descriptive text string
		@param state_one The first "state" of the file or directory.  Can be a file name, size,
				  location, whatever is appropriate for the specific operation
		@param state_two The second "state" of the file or directory
		@param incversion Boolean True/False.  Increment the version for the file?  Note that this is
				   handled automatically for the VFS_OPERATION defines.
				   i.e. VFS_OPERATION_EDITED would increment the version, VFS_OPERATION_COPIED
				   would not
		@result Boolean True/False
		*/
		function add_journal ($data) {
		//The journalling dont work :(  Ideally this will become "versioning"
			return True;
		}


		/*!
		@function flush_journal
		@abstract Flush journal entries for $string.  Used before adding $string
		@discussion flush_journal () is an internal function and should be called from add_journal () only
		@param string File/directory to flush journal entries of
		@param relatives Realtivity array
		@param deleteall Delete all types of journal entries, including the active Create entry.
				  Normally you only want to delete the Create entry when replacing the file
				  Note that this option does not effect $deleteonly
		@param deletedonly Only flush 'journal-deleted' entries (created when $string was deleted)
		@result Boolean True/False
		*/
		function flush_journal ($data)
		{
			return True;
		}


		/*!
		@function get_journal
		@abstract Retrieve journal entries for $string
		@param string File/directory to retrieve journal entries of
		@param relatives Relativity array
		@param type 0/False = any, 1 = 'journal', 2 = 'journal-deleted'
		@result Array of arrays of journal entries
		*/
		function get_journal ($data)
		{
			return array();
		}

		/*!
		@function path_parts
		@abstract take a real or fake pathname and return an array of its component parts
		@param string full real or fake path
		@param relatives Relativity array
		@param object True returns an object instead of an array
		@param nolinks Don't check for links (made with make_link ()).  Used internally to prevent recursion
		@result $rarray/$robject Array or object containing the fake and real component parts of the path
		@discussion Returned values are:
				mask
				outside
				fake_full_path
				fake_leading_dirs
				fake_extra_path		BROKEN
				fake_name
				real_full_path
				real_leading_dirs
				real_extra_path		BROKEN
				real_name
				fake_full_path_clean
				fake_leading_dirs_clean
				fake_extra_path_clean	BROKEN
				fake_name_clean
				real_full_path_clean
				real_leading_dirs_clean
				real_extra_path_clean	BROKEN
				real_name_clean
				real_uri
			"clean" values are run through vfs->db_clean () and
			are safe for use in SQL queries that use key='value'
			They should be used ONLY for SQL queries, so are used
			mostly internally
			mask is either RELATIVE_NONE or RELATIVE_NONE|VFS_REAL,
			and is used internally
			outside is boolean, True if 'relatives' contains VFS_REAL
		*/
		function path_parts ($data)
		{
			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT),
					'object'	=> True,
					'nolinks'	=> False
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$sep = SEP;

			$rarray['mask'] = RELATIVE_NONE;

			if (!($data['relatives'][0] & VFS_REAL))
			{
				$rarray['outside'] = False;
				$fake = True;
			}
			else
			{
				$rarray['outside'] = True;
				$rarray['mask'] |= VFS_REAL;
			}

			$string = $this->getabsolutepath (array(
					'string'	=> $data['string'],
					'mask'	=> array ($data['relatives'][0]),
					'fake'	=> $fake
				)
			);

			if ($fake)
			{
				$base_sep = '/';
				$base = '/';

				$opp_base = $this->basedir . $sep;

				$rarray['fake_full_path'] = $string;
			}
			else
			{
				$base_sep = $sep;
				if (ereg ("^$this->basedir" . $sep, $string))
				{
					$base = $this->basedir . $sep;
				}
				else
				{
					$base = $sep;
				}

				$opp_base = '/';
				$rarray['real_full_url'] = $string;
				$rarray['real_full_path'] = $this->dav_path($string);
			}

			/* This is needed because of substr's handling of negative lengths */
			$baselen = strlen ($base);
			$lastslashpos = strrpos ($string, $base_sep);
			$lastslashpos < $baselen ? $length = 0 : $length = $lastslashpos - $baselen;

			$extra_path = $rarray['fake_extra_path'] = $rarray['real_extra_path'] = substr ($string, strlen ($base), $length);
			$name = $rarray['fake_name'] = $rarray['real_name'] = substr ($string, strrpos ($string, $base_sep) + 1);

			if ($fake)
			{
				$rarray['real_extra_path'] ? $dispsep = $sep : $dispsep = '';
				$rarray['real_full_url'] = $opp_base . $rarray['real_extra_path'] . $dispsep . $rarray['real_name'];
				$rarray['real_full_path'] = $this->dav_path($rarray['real_full_url']);
				if ($extra_path)
				{
					$rarray['fake_leading_dirs'] = $base . $extra_path;
					$rarray['real_leading_dirs'] = $this->dav_path($opp_base . $extra_path);
				}
				elseif (strrpos ($rarray['fake_full_path'], $sep) == 0)
				{
					/* If there is only one $sep in the path, we don't want to strip it off */
					$rarray['fake_leading_dirs'] = $sep;
					$rarray['real_leading_dirs'] = $this->dav_path( substr ($opp_base, 0, strlen ($opp_base) - 1));
				}
				else
				{
					/* These strip the ending / */
					$rarray['fake_leading_dirs'] = substr ($base, 0, strlen ($base) - 1);
					$rarray['real_leading_dirs'] = $this->dav_path( substr ($opp_base, 0, strlen ($opp_base) - 1));
				}
			}
			else
			{
				$rarray['fake_full_path'] = $opp_base . $rarray['fake_extra_path'] . '/' . $rarray['fake_name'];
				if ($extra_path)
				{
					$rarray['fake_leading_dirs'] = $opp_base . $extra_path;
					$rarray['real_leading_dirs'] = $this->dav_path($base . $extra_path);
				}
				else
				{
					$rarray['fake_leading_dirs'] = substr ($opp_base, 0, strlen ($opp_base) - 1);
					$rarray['real_leading_dirs'] = $this->dav_path(substr ($base, 0, strlen ($base) - 1));
				}
			}

			/* We check for linked dirs made with make_link ().  This could be better, but it works */
			if (!$data['nolinks'])
			{
				reset ($this->linked_dirs);
				while (list ($num, $link_info) = each ($this->linked_dirs))
				{
					if (ereg ("^$link_info[directory]/$link_info[name](/|$)", $rarray['fake_full_path']))
					{
						$rarray['real_full_path'] = ereg_replace ("^$this->basedir", '', $rarray['real_full_path']);
						$rarray['real_full_path'] = ereg_replace ("^$link_info[directory]" . SEP . "$link_info[name]", $link_info['link_directory'] . SEP . $link_info['link_name'], $rarray['real_full_path']);

						$p = $this->path_parts (array(
								'string'	=> $rarray['real_full_path'],
								'relatives'	=> array (RELATIVE_NONE|VFS_REAL),
								'nolinks'	=> True
							)
						);

						$rarray['real_leading_dirs'] = $this->dav_path($p->real_leading_dirs);
						$rarray['real_extra_path'] = $p->real_extra_path;
						$rarray['real_name'] = $p->real_name;
					}
				}
			}
			
			/*
				Create the 'real_auth_url', which includes the user and
				password (for the view method to redirect you there)
			*/

			$parsed_url = parse_url($rarray['real_full_url']);
			$parsed_url['user'] = $this->dav_user;
//			$parsed_url['pass'] = $this->dav_pwd;
			$rarray['real_full_auth_url'] = $this->glue_url($parsed_url);
					
			$parsed_url = parse_url($rarray['real_full_url']);
			$parsed_url['scheme'] = 'https';
			$parsed_url['user'] = $this->dav_user;
			$rarray['real_full_secure_url'] = $this->glue_url($parsed_url);
			
			
			/*
			   We have to count it before because new keys will be added,
			   which would create an endless loop
			*/
			$count = count ($rarray);
			reset ($rarray);
			for ($i = 0; (list ($key, $value) = each ($rarray)) && $i != $count; $i++)
			{
				$rarray[$key . '_clean'] = $this->db_clean (array ('string' => $value));
			}

			if ($data['object'])
			{
				$robject = new path_class;

				reset ($rarray);
				while (list ($key, $value) = each ($rarray))
				{
					$robject->$key = $value;
				}
			}

/*
			echo "<br>fake_full_path: $rarray[fake_full_path]
				<br>fake_leading_dirs: $rarray[fake_leading_dirs]
				<br>fake_extra_path: $rarray[fake_extra_path]
				<br>fake_name: $rarray[fake_name]
				<br>real_full_path: $rarray[real_full_path]
				<br>real_full_url: $rarray[real_full_url]
				<br>real_leading_dirs: $rarray[real_leading_dirs]
				<br>real_extra_path: $rarray[real_extra_path]
				<br>real_name: $rarray[real_name]";
*/

			if ($data['object'])
			{
				return ($robject);
			}
			else
			{
				return ($rarray);
			}
		}

		/*!
		@function getabsolutepath
		@abstract get the absolute path
		@param string defaults to False, directory/file to get path of, relative to relatives[0]
		@param mask Relativity bitmask (see RELATIVE_ defines).  RELATIVE_CURRENT means use $this->relative
		@param fake Returns the "fake" path, ie /home/user/dir/file (not always possible.  use path_parts () instead)
		@result $basedir Full fake or real path
		*/
		function getabsolutepath ($data)
		{
			$default_values = array
				(
					'string'	=> False,
					'mask'	=> array (RELATIVE_CURRENT),
					'fake'	=> True
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$currentdir = $this->pwd (False);

			/* If they supply just VFS_REAL, we assume they want current relativity */
			if ($data['mask'][0] == VFS_REAL)
			{
				$data['mask'][0] |= RELATIVE_CURRENT;
			}

			if (!$this->securitycheck (array(
					'string'	=> $data['string']
				))
			)
			{
				return False;
			}

			if ($data['mask'][0] & RELATIVE_NONE)
			{
				return $data['string'];
			}

			if ($data['fake'])
			{
				$sep = '/';
			}
			else
			{
				$sep = SEP;
			}

			/* if RELATIVE_CURRENT, retrieve the current mask */
			if ($data['mask'][0] & RELATIVE_CURRENT)
			{
				$mask = $data['mask'][0];
				/* Respect any additional masks by re-adding them after retrieving the current mask*/
				$data['mask'][0] = $this->get_relative () + ($mask - RELATIVE_CURRENT);
			}

			if ($data['fake'])
			{
				$basedir = '/';
			}
			else
			{
				$basedir = $this->basedir . $sep;

				/* This allows all requests to use /'s */
				$data['string'] = preg_replace ("|/|", $sep, $data['string']);
			}

			if (($data['mask'][0] & RELATIVE_PATH) && $currentdir)
			{
				$basedir = $basedir . $currentdir . $sep;
			}
			elseif (($data['mask'][0] & RELATIVE_USER) || ($data['mask'][0] & RELATIVE_USER_APP))
			{
				$basedir = $basedir . $this->fakebase . $sep;
			}

			if ($data['mask'][0] & RELATIVE_CURR_USER)
			{
				$basedir = $basedir . $this->working_lid . $sep;
			}

			if (($data['mask'][0] & RELATIVE_USER) || ($data['mask'][0] & RELATIVE_USER_APP))
			{
				$basedir = $basedir . $GLOBALS['phpgw_info']['user']['account_lid'] . $sep;
			}

			if ($data['mask'][0] & RELATIVE_USER_APP)
			{
				$basedir = $basedir . "." . $GLOBALS['phpgw_info']['flags']['currentapp'] . $sep;
			}

			/* Don't add string if it's a /, just for aesthetics */
			if ($data['string'] && $data['string'] != $sep)
			{
				$basedir = $basedir . $data['string'];
			}

			/* Let's not return // */
			while (ereg ($sep . $sep, $basedir))
			{
				$basedir = ereg_replace ($sep . $sep, $sep, $basedir);
			}

			$basedir = ereg_replace ($sep . '$', '', $basedir);

			return $basedir;
		}

		/*!
		@function acl_check
		@abstract Check ACL access to $file for $GLOBALS['phpgw_info']["user"]["account_id"];
		@param string File to check access of
		@discussion To check the access for a file or directory, pass 'string'/'relatives'/'must_exist'.
				To check the access to another user or group, pass 'owner_id'.
				If 'owner_id' is present, we bypass checks on 'string'/'relatives'/'must_exist'
		@param relatives Standard relativity array
		@param operation Operation to check access to.  In the form of a PHPGW_ACL defines bitmask.  Default is read
		@param owner_id Owner id to check access of (see discussion above)
		@param must_exist Boolean.  Set to True if 'string' must exist.  Otherwise, we check the parent directory as well
		@result Boolean.  True if access is ok, False otherwise
		*/
		function acl_check ($data)
		{
			return True;
			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT),
					'operation'	=> PHPGW_ACL_READ,
					'must_exist'	=> False
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			/* Accommodate special situations */
			if ($this->override_acl || $data['relatives'][0] == RELATIVE_USER_APP)
			{
				return True;
			}

			if (!$data['owner_id'])
			{
				$p = $this->path_parts (array(
						'string'	=> $data['string'],
						'relatives'	=> array ($data['relatives'][0])
					)
				);

				/* Temporary, until we get symlink type files set up */
				if ($p->outside)
				{
					return True;
				}

				/* Read access is always allowed here, but nothing else is */
				if ($data['string'] == '/' || $data['string'] == $this->fakebase)
				{
					if ($data['operation'] == PHPGW_ACL_READ)
					{
						return True;
					}
					else
					{
						return False;
					}
				}

				/* If the file doesn't exist, we get ownership from the parent directory */
				if (!$this->file_exists (array(
						'string'	=> $p->fake_full_path,
						'relatives'	=> array ($p->mask)
					))
				)
				{
					if ($data['must_exist'])
					{
						return False;
					}

					$data['string'] = $p->fake_leading_dirs;
					$p2 = $this->path_parts (array(
							'string'	=> $data['string'],
							'relatives'	=> array ($p->mask)
						)
					);

					if (!$this->file_exists (array(
							'string'	=> $data['string'],
							'relatives'	=> array ($p->mask)
						))
					)
					{
						return False;
					}
				}
				else
				{
					$p2 = $p;
				}

				$file_info = $this->ls($data);
				$owner_id = $file_info['owner_id'];
			}
			else
			{
				$owner_id = $data['owner_id'];
			}

			/* This is correct.  The ACL currently doesn't handle undefined values correctly */
			if (!$owner_id)
			{
				$owner_id = 0;
			}

			$user_id = $GLOBALS['phpgw_info']['user']['account_id'];

			/* They always have access to their own files */
			if ($owner_id == $user_id)
			{
				return True;
			}

			/* Check if they're in the group */
			$memberships = $GLOBALS['phpgw']->accounts->membership ($user_id);

			if (is_array ($memberships))
			{
				reset ($memberships);
				while (list ($num, $group_array) = each ($memberships))
				{
					if ($owner_id == $group_array['account_id'])
					{
						$group_ok = 1;
						break;
					}
				}
			}

			$acl = CreateObject ('phpgwapi.acl', $owner_id);
			$acl->account_id = $owner_id;
			$acl->read_repository ();

			$rights = $acl->get_rights ($user_id);

			/* Add privileges from the groups this user belongs to */
			if (is_array ($memberships))
			{
				reset ($memberships);
				while (list ($num, $group_array) = each ($memberships))
				{
					$rights |= $acl->get_rights ($group_array['account_id']);
				}
			}

			if ($rights & $data['operation'])
			{
				return True;
			}
			elseif (!$rights && $group_ok)
			{
				$conf = CreateObject('phpgwapi.config', 'phpgwapi');
				$conf->read_repository();
				if ($conf->config_data['acl_default'] == 'grant')
				{
					return True;
				}
				else
				{
					return False;
				}
			}
			else
			{
				return False;
			}
		}

		/*!
		@function cd
		@abstract Change directory
		@discussion To cd to the files root '/', use cd ('/', False, array (RELATIVE_NONE));
		@param string default '/'.  directory to cd into.  if "/" and $relative is True, uses "/home/<working_lid>";
		@param relative default True/relative means add target to current path, else pass $relative as mask to getabsolutepath()
		@param relatives Relativity array
		*/
		function cd ($data = '')
		{
			if (!is_array ($data))
			{
				$noargs = 1;
				$data = array ();
			}

			$default_values = array
				(
					'string'	=> '/',
					'relative'	=> True,
					'relatives'	=> array (RELATIVE_CURRENT)
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			if ($data['relatives'][0] & VFS_REAL)
			{
				$sep = SEP;
			}
			else
			{
				$sep = '/';
			}

			if ($data['relative'] == 'relative' || $data['relative'] == True)
			{
				/* if 'string' is "/" and 'relative' is set, we cd to the user/group home dir */
				if ($data['string'] == '/')
				{
					$data['relatives'][0] = RELATIVE_USER;
					$basedir = $this->getabsolutepath (array(
							'string'	=> False,
							'mask'	=> array ($data['relatives'][0]),
							'fake'	=> True
						)
					);
				}
				else
				{
					$currentdir = $GLOBALS['phpgw']->session->appsession('vfs','');
					$basedir = $this->getabsolutepath (array(
							'string'	=> $currentdir . $sep . $data['string'],
							'mask'	=> array ($data['relatives'][0]),
							'fake'	=> True
						)
					);
				}
			}
			else
			{
				$basedir = $this->getabsolutepath (array(
						'string'	=> $data['string'],
						'mask'	=> array ($data['relatives'][0])
					)
				);
			}

			$GLOBALS['phpgw']->session->appsession('vfs','',$basedir);

			return True;
		}

		/*!
		@function pwd
		@abstract current working dir
		@param full default True returns full fake path, else just the extra dirs (false strips the leading /)
		@result $currentdir currentdir
		*/
		function pwd ($data = '')
		{
			$default_values = array
				(
					'full'	=> True
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$currentdir = $GLOBALS['phpgw']->session->appsession('vfs','');

			if (!$data['full'])
			{
				$currentdir = ereg_replace ("^/", '', $currentdir);
			}

			if ($currentdir == '' && $data['full'])
			{
				$currentdir = '/';
			}

			$currentdir = trim ($currentdir);

			return $currentdir;
		}

		/*!
		@function read
		@abstract return file contents
		@param string filename
		@param relatives Relativity array
		@result $contents Contents of $file, or False if file cannot be read
		*/
		function read ($data)
		{

			/*If the user really wants to 'view' the file in the browser, it
			is much smarter simply to redirect them to the files web-accessable
			url */
/*			$app = $GLOBALS['phpgw_info']['flags']['currentapp'];
			if ( ! $data['noview'] && ($app == 'phpwebhosting' || $app = 'filemanager' ))
			{
				$this->view($data);
			}	
*/			
			if (!is_array ($data))
			{
				$data = array ();
			}

			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT)
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);

			if (!$this->acl_check (array(
					'string'	=> $p->fake_full_path,
					'relatives'	=> array ($p->mask),
					'operation'	=> PHPGW_ACL_READ
				))
			)
			{
				return False;
			}
			if ($p->outside)
			{
					    
			    if (! $fp = fopen ($p->real_full_path, 'r')) 
			    {
			    	return False;
			    }
			    $size=filesize($p->real_full_path);
			    $buffer=fread($fp, $size);
			    fclose ($fp);
				return $buffer;
			}
			else
			{
				$status=$this->dav_client->get($p->real_full_path);
	$this->debug($this->dav_client->get_headers());
	
				if($status != 200) return False;
				$contents=$this->dav_client->get_body();
	$this->debug('Read:returning contents.  Status:'.$status);
				return $contents;
			}
		}
		
		/*
		@function view
		@abstract Redirect the users browser to the file
		@param string filename
		@param relatives Relativity array
		@result None (doesnt return)
		@discussion In the case of WebDAV, the file is web-accessible.  So instead
		of reading it into memory and then dumping it back out again when someone
		views a file, it makes much more sense to simply redirect, which is what 
		this method does (its only called when reading from the file in the file manager,
		when the variable "noview" isnt set to "true"
		*/
		function view($data)
		{	
		
			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT)
				);
			$data = array_merge ($this->default_values ($data, $default_values), $data);
			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);
			
			//Determine whether the repository supports SSL
			$parsed_url = parse_url($this->repository);
			if ($parsed_url['scheme']=='https')
			{
				header( 'Location: '.$p->real_full_secure_url, true ); 
			}
			else
			{
				header( 'Location: '.$p->real_full_auth_url, true ); 			
			}
			exit(); 

		}
		
		/*
		@function lock
		@abstract DAV (class 2) locking - sets an exclusive write lock
		@param string filename
		@param relatives Relativity array
		@result True if successfull 
		*/		
		function lock ($data)
		{
			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT),
					'timeout'	=> 'infinity',
					
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);		
			return $this->dav_client->lock($p->real_full_url, $this->dav_user, 0, $data['timeout']);

		}
		function lock_token ($data)
		{
			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT),
					'token' => ''
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);
			
			$ls_array = $GLOBALS['phpgw']->vfs->ls (array (
			'string'	=> $data['string'],
			'relatives'	=> $data['relatives']
			)
			);	
			 $lock = @end($ls_array[0]['locks']);
			 $token = @end($lock['lock_tokens']);
			 return $token['full_name'];
		}
		
		
		/*
		@function add_lock_override
		@abstract override a lock
		@param string filename
		@param relatives Relativity array
		@param token (optional) a token for the lock we want to override
		@result None
		@discussion locks are no good unless you can write to a file you yourself locked:
		to do this call add_lock_override with the lock token (or without it - it will
		find it itself, so long as there is only one).  lock_override info is stored in
		the groupware session, so it will persist between page loads, but will be lost 
		when the browser is closed
		*/	
		function add_lock_override($data)
		{
			$default_values = array
			(
				'relatives'	=> array (RELATIVE_CURRENT),
				'token' => ''
				
			);

			$data = array_merge ($this->default_values ($data, $default_values), $data);
			
			if (!strlen($data['token']))
			{
				$ls_array = $GLOBALS['phpgw']->vfs->ls (array (
				'string'	=> $data['string'],
				'relatives'	=> $data['relatives']
				)
				);	
				 $lock = @end($ls_array[0]['locks']);
				 $token_array = @end($lock['lock_tokens']);
				 $token =  $token_array['full_name'];
			}
			else
			{
				$token = $data['token'];
			}
			 
			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);		
			$this->override_locks[$p->real_full_path] = $token;
			$this->save_session();
		}
		
		/*
		@function remove_lock_override
		@abstract stops overriding a lock
		@param string filename
		@param relatives Relativity array
		@result None
		*/	
		function remove_lock_override($data)
		{
			$default_values = array
			(
				'relatives'	=> array (RELATIVE_CURRENT)
				
			);

			$data = array_merge ($this->default_values ($data, $default_values), $data);
			
			if (!strlen($data['token']))
			{
				$ls_array = $GLOBALS['phpgw']->vfs->ls (array (
				'string'	=> $data['string'],
				'relatives'	=> $data['relatives']
				)
				);	
				 $lock = @end($ls_array[0]['locks']);
				 $token_array = @end($lock['lock_tokens']);
				 $token =  $token_array['full_name'];
			}
			else
			{
				$token = $data['token'];
			}
			 
			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);		
			unset($this->override_locks[$p->real_full_path]);
			$this->save_session();
		}	
		
		/*
		@function unlock
		@abstract DAV (class 2) unlocking - unsets the specified lock
		@param string filename
		@param relatives Relativity array
		@param tocken	The token for the lock we wish to remove.
		@result True if successfull
		*/		
		function unlock ($data, $token)
		{
			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT),
					'content'	=> ''
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);
			$this->remove_lock_override (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);
			return $this->dav_client->unlock($p->real_full_url, $token);


		}
		
		/*
		@function options
		@abstract Allows querying for optional features - esp optional DAV features 
		like locking
		@param option	The option you want to test for.  Options include 'LOCKING'
			'VIEW', 'VERSION-CONTROL (eventually) etc
		@result true if the specified option is supported
		@discussion This should really check the server.  Unfortunately the overhead of doing this
		in every VFS instance is unacceptable (it essentially doubles the time for any request). Ideally
		we would store these features in the session perhaps?
		*/		
		function options($option)
		{
			switch ($option)
			{
			case 'LOCKING':
				return true;
			case 'VIEW':
				return true;
			default:
				return false;
			}
		}
		
		/*!
		@function write
		@abstract write to a file
		@param string file name
		@param relatives Relativity array
		@param content content
		@result Boolean True/False
		*/
		function write ($data)
		{
			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT),
					'content'	=> ''
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);

			if ($this->file_exists (array (
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				))
			)
			{
				$acl_operation = PHPGW_ACL_EDIT;
				$journal_operation = VFS_OPERATION_EDITED;
			}
			else
			{
				$acl_operation = PHPGW_ACL_ADD;
			}

			if (!$this->acl_check (array(
					'string'	=> $p->fake_full_path,
					'relatives'	=> array ($p->mask),
					'operation'	=> $acl_operation
				))
			)
			{
				return False;
			}

			//umask(000);

			/*
			   If 'string' doesn't exist, touch () creates both the file and the database entry
			   If 'string' does exist, touch () sets the modification time and modified by
			*/
			/*$this->touch (array(
					'string'	=> $p->fake_full_path,
					'relatives'	=> array ($p->mask)
				)
			);*/
			
			$size=strlen($data['content']);
			if ($p->outside)
			{			    
			    if (! $fp = fopen ($p->real_full_path, 'w')) 
			    {
			    	return False;
			    }
			    $result = fwrite($fp, $data['content']);
			    fclose ($fp);
			    return $result;
			}
			else
			{
				$token =  $this->override_locks[$p->real_full_path];
				$status=$this->dav_client->put($p->real_full_path,$data['content'],$token);
$this->debug('Put complete,  status: '.$status);
				if($status!=201 && $status!=204) 
				{
					return False;
				}
				else
				{
					return True;
				}
			}
		}

		/*!
		@function touch
		@abstract Create blank file $file or set the modification time and modified by of $file to current time and user
		@param string File to touch or set modifies
		@param relatives Relativity array
		@result Boolean True/False
		*/
		function touch ($data)
		{
			$default_values = array(
						'relatives'	=> array (RELATIVE_CURRENT)
						);
			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$account_id = $GLOBALS['phpgw_info']['user']['account_id'];
			$currentapp = $GLOBALS['phpgw_info']['flags']['currentapp'];

			$p = $this->path_parts (array(
						      'string'	=> $data['string'],
						      'relatives'	=> array ($data['relatives'][0])
						      )
						);
			umask (000);

			/*
			   PHP's touch function will automatically decide whether to
			   create the file or set the modification time
			*/
			if($p->outside)
			{
			  return @touch($p->real_full_path);
			}
			elseif ($this->file_exists (array(
					'string'	=> $p->fake_full_path,
					'relatives'	=> array ($p->mask)
				))
			)
			{
				$result =  $this->set_attributes (array(
						'string'	=> $p->fake_full_path,
						'relatives'	=> array ($p->mask),
						'attributes'	=> array(
									'modifiedby_id' => $account_id,
									'modified' => $this->now
						)));
			}
			else
			{
				if (!$this->acl_check (array(
						'string'	=> $p->fake_full_path,
						'relatives'	=> array ($p->mask),
						'operation'	=> PHPGW_ACL_ADD
					))
				) return False;
				$result = $this->write (array(
						      'string'	=> $data['string'],
						      'relatives'	=> array ($data['relatives'][0]),
						      'content' => ''
						      ));
				$this->set_attributes(array(
					'string'	=> $p->fake_full_path,
					'relatives'	=> array ($p->mask),
					'attributes'	=> array (
								'createdby_id' => $account_id,
								'created' => $this->now,
								'app' => $currentapp
							)));
			}

			return ($result);
		}

		/*!
		@function cp
		@abstract copy file
		@param from from file/directory
		@param to to file/directory
		@param relatives Relativity array
		@result boolean True/False
		*/
		function cp ($data)
		{
			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT, RELATIVE_CURRENT)
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$account_id = $GLOBALS['phpgw_info']['user']['account_id'];

			$f = $this->path_parts (array(
					'string'	=> $data['from'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);

			$t = $this->path_parts (array(
					'string'	=> $data['to'],
					'relatives'	=> array ($data['relatives'][1])
				)
			);

			if (!$this->acl_check (array(
					'string'	=> $f->fake_full_path,
					'relatives'	=> array ($f->mask),
					'operation'	=> PHPGW_ACL_READ
				))
			)
			{
				return False;
			}

			if ($this->file_exists (array(
					'string'	=> $t->fake_full_path,
					'relatives'	=> array ($t->mask)
				))
			)
			{
				$remote_operation=PHPGW_ACL_EDIT;
			}
			else
			{
				$remote_operation=PHPGW_ACL_ADD;

			}
			if (!$this->acl_check (array(
						     'string'	=> $t->fake_full_path,
						     'relatives'	=> array ($t->mask),
						     'operation'	=> $remote_operation
						     ))
			    )
			{
				return False;
			}

			umask(000);

			if ($this->file_type (array(
					'string'	=> $f->fake_full_path,
					'relatives'	=> array ($f->mask)
				)) != 'Directory'
			)
			{
			  
				if ($f->outside && $t->outside)
				{
					return copy($f->real_full_path, $t->real_full_url);
				}
				elseif ($f->outside || $t->outside)
				{
			      	$content = $this->read(array(
						'string'	=> $f->fake_full_path,
						'noview' => true,
						'relatives'	=> array ($f->mask)
						)
					);
					$result = $this->write(array(
						'string'	=> $t->fake_full_path,
						'relatives'	=> array ($t->mask),
						'content' => $content
						)
					);
			    }
				else 
				{
				    $status=$this->dav_client->copy($f->real_full_path, $t->real_full_url,True, 'Infinity', $this->override_locks[$p->real_full_path]);
				    $result = $status == 204 || $status==201;
				    if (!$result)
				    {
				    	return False;
				    }
			 	 }

				$this->set_attributes(array(
					'string'	=> $t->fake_full_path,
					'relatives'	=> array ($t->mask),
					'attributes' => array (
								'owner_id' => $this->working_id,
								'createdby_id' => $account_id,
							)
						)
					);
				return $result;

			}
			else if (!($f->outside || $t->outside)) 
			{
				//if the files are both on server, its just a depth=infinity copy
				$status=$this->dav_client->copy($f->real_full_path, $t->real_full_url,True, 'infinity', $this->override_locks[$p->real_full_path]);
			    if($status != 204 && $status!=201) 
			    {
			    	return False;
			    }
			    else 
			    {
			    	return True;
			    }
			}
			else	/* It's a directory, and one of the files is local */
			{
				/* First, make the initial directory */
				$this->mkdir (array(
						'string'	=> $data['to'],
						'relatives'	=> array ($data['relatives'][1])
					)
				);

				/* Next, we create all the directories below the initial directory */
				$ls = $this->ls (array(
						'string'	=> $f->fake_full_path,
						'relatives'	=> array ($f->mask),
						'checksubdirs'	=> True,
						'mime_type'	=> 'Directory'
					)
				);

				while (list ($num, $entry) = each ($ls))
				{
					$newdir = ereg_replace ("^$f->fake_full_path", "$t->fake_full_path", $entry['directory']);
					$this->mkdir (array(
							'string'	=> $newdir.'/'.$entry['name'],
							'relatives'	=> array ($t->mask)
						)
					);
				}

				/* Lastly, we copy the files over */
				$ls = $this->ls (array(
						'string'	=> $f->fake_full_path,
						'relatives'	=> array ($f->mask)
					)
				);

				while (list ($num, $entry) = each ($ls))
				{
					if ($entry['mime_type'] == 'Directory')
					{
						continue;
					}

					$newdir = ereg_replace ("^$f->fake_full_path", "$t->fake_full_path", $entry['directory']);
					$this->cp (array(
							'from'	=> "$entry[directory]/$entry[name]",
							'to'	=> "$newdir/$entry[name]",
							'relatives'	=> array ($f->mask, $t->mask)
						)
					);
				}
			}

			return True;
		}

		function copy ($data)
		{
			return $this->cp ($data);
		}

		/*!
		@function mv
		@abstract move file/directory
		@param from from file/directory
		@param to to file/directory
		@param relatives Relativity array
		@result boolean True/False
		*/
		function mv ($data)
		{
			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT, RELATIVE_CURRENT)
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$account_id = $GLOBALS['phpgw_info']['user']['account_id'];

			$f = $this->path_parts (array(
					'string'	=> $data['from'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);

			$t = $this->path_parts (array(
					'string'	=> $data['to'],
					'relatives'	=> array ($data['relatives'][1])
				)
			);

			if (!$this->acl_check (array(
					'string'	=> $f->fake_full_path,
					'relatives'	=> array ($f->mask),
					'operation'	=> PHPGW_ACL_READ
				))
				|| !$this->acl_check (array(
					'string'	=> $f->fake_full_path,
					'relatives'	=> array ($f->mask),
					'operation'	=> PHPGW_ACL_DELETE
				))
			)
			{
				return False;
			}

			if (!$this->acl_check (array(
					'string'	=> $t->fake_full_path,
					'relatives'	=> array ($t->mask),
					'operation'	=> PHPGW_ACL_ADD
				))
			)
			{
				return False;
			}

			if ($this->file_exists (array(
					'string'	=> $t->fake_full_path,
					'relatives'	=> array ($t->mask)
				))
			)
			{
				if (!$this->acl_check (array(
						'string'	=> $t->fake_full_path,
						'relatives'	=> array ($t->mask),
						'operation'	=> PHPGW_ACL_EDIT
					))
				)
				{
					return False;
				}
			}
			umask (000);

			/* We can't move directories into themselves */
			if (($this->file_type (array(
					'string'	=> $f->fake_full_path,
					'relatives'	=> array ($f->mask)
				) == 'Directory'))
				&& ereg ("^$f->fake_full_path", $t->fake_full_path)
			)
			{
				if (($t->fake_full_path == $f->fake_full_path) || substr ($t->fake_full_path, strlen ($f->fake_full_path), 1) == '/')
				{
					return False;
				}
			}

			if ($this->file_exists (array(
					'string'	=> $f->fake_full_path,
					'relatives'	=> array ($f->mask)
				))
			)
			{
				/* We get the listing now, because it will change after we update the database */
				$ls = $this->ls (array(
						'string'	=> $f->fake_full_path,
						'relatives'	=> array ($f->mask)
					)
				);

				if ($this->file_exists (array(
						'string'	=> $t->fake_full_path,
						'relatives'	=> array ($t->mask)
					))
				)
				{
					$this->rm (array(
							'string'	=> $t->fake_full_path,
							'relatives'	=> array ($t->mask)
						)
					);
				}

				$this->correct_attributes (array(
						'string'	=> $t->fake_full_path,
						'relatives'	=> array ($t->mask)
					)
				);
				
				if ($f->outside && $t->outside)
				{
					echo 'local';
					$result = rename ($f->real_full_path, $t->real_full_path);
				}
				else if ($f->outside || $t->outside) //if either file is local, read then write
				{
					$content = $this->read(array(
						'string'	=> $f->fake_full_path,
						'noview' => true,
						'relatives'	=> array ($f->mask)
						)
					);
					$result = $this->write(array(
						'string'	=> $t->fake_full_path,
						'relatives'	=> array ($t->mask),
						'content' => $content
						)
					);
					if ($result)
					{
						$result = $this->rm(array(
							'string'	=> $f->fake_full_path,
							'relatives'	=> array ($f->mask),
							'content' => $content
							)
						);
					}
				}
				else {  //we can do a server-side copy if both files are on the server
					$status=$this->dav_client->move($f->real_full_path, $t->real_full_url,True, 'infinity', $this->override_locks[$p->real_full_path]);
			        $result = ($status==201 || $status==204);
				}
				
				if ($result) $this->set_attributes(array(
						'string'	=> $t->fake_full_path,
						'relatives'	=> array ($t->mask),
						'attributes'	=> array (
									'modifiedby_id' => $account_id,
									'modified' => $this->now
								)));
				return $result;
			}
			else
			{
				return False;
			}

			$this->add_journal (array(
					'string'	=> $t->fake_full_path,
					'relatives'	=> array ($t->mask),
					'operation'	=> VFS_OPERATION_MOVED,
					'state_one'	=> $f->fake_full_path,
					'state_two'	=> $t->fake_full_path
				)
			);

			return True;
		}

		/*!
		@function move
		@abstract shortcut to mv
		*/
		function move ($data)
		{
			return $this->mv ($data);
		}

		/*!
		@function rm
		@abstract delete file/directory
		@param string file/directory to delete
		@param relatives Relativity array
		@result boolean True/False
		*/
		function rm ($data)
		{
			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT)
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);
			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);
			$this->debug("rm: $p->real_full_path");
			if (!$this->acl_check (array(
					'string'	=> $p->fake_full_path,
					'relatives'	=> array ($p->mask),
					'operation'	=> PHPGW_ACL_DELETE
				))
			)
			{
				return False;
			} 

/*this would become apparent soon enough anyway?
			if (!$this->file_exists (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				))
			) return False;
*/
			if ($this->file_type (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)) != 'Directory'
			)
			{
				if ($p->outside)
				{
					return unlink($p->real_full_path);
				}
				else
				{
					$rr=$this->dav_client->delete($p->real_full_path, 0, $this->override_locks[$p->real_full_path]);
					return $rr == 204;	
				}
			}
			else
			{
				$ls = $this->ls (array(
						'string'	=> $p->fake_full_path,
						'relatives'	=> array ($p->mask)
					)
				);

				while (list ($num, $entry) = each ($ls))
				{
					$this->rm (array(
							'string'	=> "$entry[directory]/$entry[name]",
							'relatives'	=> array ($p->mask)
						)
					);
				}

				/* If the directory is linked, we delete the placeholder directory */
				$ls_array = $this->ls (array(
						'string'	=> $p->fake_full_path,
						'relatives'	=> array ($p->mask),
						'checksubdirs'	=> False,
						'mime_type'	=> False,
						'nofiles'	=> True
					)
				);
				$link_info = $ls_array[0];

				if ($link_info['link_directory'] && $link_info['link_name'])
				{
					$path = $this->path_parts (array(
							'string'	=> $link_info['directory'] . '/' . $link_info['name'],
							'relatives'	=> array ($p->mask),
							'nolinks'	=> True
						)
					);
					$this->dav_client->delete($path->real_full_path,0, $this->override_locks[$p->real_full_path]);
				}

				/* Last, we delete the directory itself */
				$this->add_journal (array(
						'string'	=> $p->fake_full_path,
						'relatives'	=> array ($p->mask),
						'operaton'	=> VFS_OPERATION_DELETED
					)
				);

				$query = $GLOBALS['phpgw']->db->query ("DELETE FROM phpgw_vfs WHERE directory='$p->fake_leading_dirs_clean' AND name='$p->fake_name_clean'" . $this->extra_sql (array ('query_type' => VFS_SQL_DELETE)), __LINE__, __FILE__);

				//rmdir ($p->real_full_path);
				$this->dav_client->delete($p->real_full_path.'/','Infinity', $this->override_locks[$p->real_full_path]);

				return True;
			}
		}

		/*!
		@function delete
		@abstract shortcut to rm
		*/
		function delete ($data)
		{
			return $this->rm ($data);
		}

		/*!
		@function mkdir
		@abstract make a new directory
		@param string Directory name
		@param relatives Relativity array
		@result boolean True on success
		*/
		function mkdir ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT)
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$account_id = $GLOBALS['phpgw_info']['user']['account_id'];
			$currentapp = $GLOBALS['phpgw_info']['flags']['currentapp'];

			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);

			if (!$this->acl_check (array(
					'string'	=> $p->fake_full_path,
					'relatives'	=> array ($p->mask),
					'operation'	=> PHPGW_ACL_ADD)
				)
			)
			{
				return False;
			}

			/* We don't allow /'s in dir names, of course */
			if (ereg ('/', $p->fake_name))
			{
				return False;
			}
			if ($p->outside)
			{
				if (!mkdir($p->real_full_path, 0777))
				{
					return False;
				}
			}
			else if($this->dav_client->mkcol($p->real_full_path, $this->override_locks[$p->real_full_path]) != 201) 
			{
				return False;
			}
			

			if (!$this->file_exists (array(
					'string'	=> $p->fake_full_path.'/'
				))
			)
			{
				/*Now we need to set access control for this dir.  Simply create an .htaccess
				file limiting access to this user, if we are creating this dir in the user's home dir*/
				$homedir = $this->fakebase.'/'.$this->dav_user; 
				if ( substr($p->fake_leading_dirs, 0, strlen($homedir)) == $homedir)
				{ 
					$conf = CreateObject('phpgwapi.config', 'phpgwapi');
					$conf->read_repository();
					if (!$conf->config_data['acl_default'] == 'grant')
					{
						$htaccess = 'require user '.$GLOBALS['phpgw_info']['user']['account_lid'];
						if ( ! $this->write(array(
								'string' =>  $p->fake_full_path.'/.htaccess',
								'content' => $htaccess,
								'relatives' => array(RELATIVE_NONE)
							)))
						{
							echo '<p><b>Unable to write .htaccess file</b></p></b>';
						};	
					}
				}
				return True;
			}
			else
			{
				return False;
			}
		}

		/*!
		@function make_link
		@abstract Make a link from virtual directory 'vdir' to real directory 'rdir'
		@discussion Making a link from 'vdir' to 'rdir' will cause path_parts () to substitute 'rdir' for the real
				path variables when presented with 'vdir'
		@param vdir Virtual dir to make link from
		@param rdir Real dir to make link to
		@param relatives Relativity array
		@result Boolean True/False
		*/
		function make_link ($data)
		{
			return False; //This code certainly wont work anymore.  Does anything use it?
		/*
			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT, RELATIVE_CURRENT)
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$account_id = $GLOBALS['phpgw_info']['user']['account_id'];
			$currentapp = $GLOBALS['phpgw_info']['flags']['currentapp'];

			$vp = $this->path_parts (array(
					'string'	=> $data['vdir'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);

			$rp = $this->path_parts (array(
					'string'	=> $data['rdir'],
					'relatives'	=> array ($data['relatives'][1])
				)
			);

			if (!$this->acl_check (array(
					'string'	=> $vp->fake_full_path,
					'relatives'	=> array ($vp->mask),
					'operation'	=> PHPGW_ACL_ADD
				))
			) return False;

			if ((!$this->file_exists (array(
					'string'	=> $rp->real_full_path,
					'relatives'	=> array ($rp->mask)
				)))
				&& !mkdir ($rp->real_full_path, 0770)) return False;

			if (!$this->mkdir (array(
					'string'	=> $vp->fake_full_path,
					'relatives'	=> array ($vp->mask)
				))
			)return False;

			$size = $this->get_size (array(
					'string'	=> $rp->real_full_path,
					'relatives'	=> array ($rp->mask)
				)
			);

			$this->set_attributes(array(
					'string'	=> $vp->fake_full_path,
					'relatives'	=> array ($vp->mask),
					'attributes'	=> array (
								'link_directory' => $rp->real_leading_dirs,
								'link_name' => $rp->real_name,
								'size' => $size
							)
				)
			);

			$this->correct_attributes (array(
					'string'	=> $vp->fake_full_path,
					'relatives'	=> array ($vp->mask)
				)
			);

			return True;
	*/
		}

		/*!
		@function set_attributes
		@abstract Update database entry for 'string' with the attributes in 'attributes'
		@param string file/directory to update
		@param relatives Relativity array
		@param attributes keyed array of attributes.  key is attribute name, value is attribute value
		@result Boolean True/False
		@discussion Valid attributes are:
				owner_id
				createdby_id
				modifiedby_id
				created
				modified
				size
				mime_type
				deleteable
				comment
				app
				link_directory
				link_name
				version
				name
				directory
		*/
		function set_attributes ($data,$operation=PHPGW_ACL_EDIT)
		{
			/*To get much benefit out of DAV properties we should use 
			some sensible XML namespace.  We will use the Dublin Core 
			metadata specification (http://dublincore.org/) here where 
			we can*/
			$p = $this->path_parts (array(
				'string'	=> $data['string'],
				'relatives'	=> array ($data['relatives'][0])
				));
			$dav_properties = array();
			$lid=''; $fname = ''; $lname='';
			if ($data['attributes']['comment'])
			{
				$dav_properties['dc:description'] = $data['attributes']['comment'];
			}
			if ($id=$data['attributes']['owner_id'])
			{
				$GLOBALS['phpgw']->accounts->get_account_name($id,&$lid,&$fname,&$lname);
				$dav_properties['dc:publisher'] = $fname .' '. $lname;
				$dav_properties['publisher_id'] = $id;
			}
			if ($id=$data['attributes']['createdby_id'])
			{
				$GLOBALS['phpgw']->accounts->get_account_name($id,&$lid,&$fname,&$lname);
				$dav_properties['dc:creator'] = $fname .' '. $lname;
				$dav_properties['creator_id'] = $id;
			}
			if ($id=$data['attributes']['modifiedby_id'])
			{
				$GLOBALS['phpgw']->accounts->get_account_name($id,&$lid,&$fname,&$lname);
				$dav_properties['dc:contributor'] = $fname .' '. $lname;
				$dav_properties['contributor_id'] = $id;
			}

			$xmlns = 'xmlns:dc="http://purl.org/dc/elements/1.0/"';
			$this->dav_client->proppatch($p->real_full_path, $dav_properties, $xmlns, $this->override_locks[$p->real_full_path]);
			return True;
		}

		/*!
		@function correct_attributes
		@abstract Set the correct attributes for 'string' (e.g. owner)
		@param string File/directory to correct attributes of
		@param relatives Relativity array
		@result Boolean True/False
		*/
		function correct_attributes ($data)
		{
			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT)
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);
$this->debug('correct_attributes: '.$data['string']);
			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);

			if ($p->fake_leading_dirs != $this->fakebase && $p->fake_leading_dirs != '/')
			{
				$ls_array = $this->ls (array(
						'string'	=> $p->fake_leading_dirs,
						'relatives'	=> array ($p->mask),
						'checksubdirs'	=> False,
						'nofiles'	=> True
					)
				);
				$set_attributes_array = Array(
					'owner_id' => $ls_array[0]['owner_id']
				);
			}
			elseif (preg_match ("+^$this->fakebase\/(.*)$+U", $p->fake_full_path, $matches))
			{
				$set_attributes_array = Array(
					'owner_id' => $GLOBALS['phpgw']->accounts->name2id ($matches[1])
				);
			}
			else
			{
				$set_attributes_array = Array(
					'owner_id' => 0
				);
			}

			$this->set_attributes (array(
					'string'	=> $p->fake_full_name,
					'relatives'	=> array ($p->mask),
					'attributes'	=> $set_attributes_array
				)
			);

			return True;
		}

		/*!
		@function file_type
		@abstract return file/dir type (MIME or other)
		@param string File or directory path (/home/user/dir/dir2/dir3, /home/user/dir/dir2/file)
		@param relatives Relativity array
		@result MIME type, "Directory", or nothing if MIME type is not known
		*/
		function file_type ($data)
		{
$this->debug('file_type');
			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT)
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);

			if (!$this->acl_check (array(
					'string'	=> $p->fake_full_path,
					'relatives'	=> array ($p->mask),
					'operation'	=> PHPGW_ACL_READ,
					'must_exist'	=> True
				))
			) return False;

			if ($p->outside)
			{
			  if(is_dir($p->real_full_path)) return ('Directory');
			  else return $this->get_ext_mime_type(array('string' => $p->real_full_path));

			}
			$tmp_prop=$this->dav_client->get_properties($p->real_full_path);
$this->debug('tmpprop: '.$p->real_full_path);
$this->debug($tmp_prop);
			$mime_type=$tmp_prop[$p->real_full_path]['mime_type'];
			if ($mime_type == 'httpd/unix-directory' || $tmp_prop[$p->real_full_path]['is_dir']== '1')
			{
				$mime_type='Directory';
			}
$this->debug('file_type: Mime type : '.$mime_type);
			return $mime_type;
		}

		/*!
		@function get_ext_mime_type
		@abstract return MIME type based on file extension
		@description Authors: skeeter
			     Internal use only.  Applications should call vfs->file_type ()
		@param string File name, with or without leading paths
		@result MIME type based on file extension
		*/
		function get_ext_mime_type ($data)
		{
			$file=basename($data['string']);
			$mimefile=PHPGW_API_INC.'/phpgw_mime.types';
			$fp=fopen($mimefile,'r');
			$contents = explode("\n",fread($fp,filesize($mimefile)));
			fclose($fp);

			$parts=explode('.',strtolower($file));
			$ext=$parts[(sizeof($parts)-1)];

			for($i=0;$i<sizeof($contents);$i++)
			{
				if (!ereg("^#",$contents[$i]))
				{
					$line=split("[[:space:]]+", $contents[$i]);
					if (sizeof($line) >= 2)
					{
						for($j=1;$j<sizeof($line);$j++)
						{
							if($line[$j] == $ext)
							{
								return $line[0];
							}
						}
					}
				}
			}

			return '';
 		}
		

 
		/*!
		@function file_exists
		@abstract check if file/directory exists
		@param string file/directory to check existance of
		@param relatives Relativity array
		@result Boolean True/False
		*/
		function file_exists ($data)
		{
			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT)
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);
$this->debug('vfs->file_exists() data:'.$data['string']);
$this->debug('vfs->file_exists() full_path:  '.$p->real_full_path);
			if ($p->outside)
			{
			  return file_exists($p->real_full_path);
			}
			
			$path = $p->real_full_path;
			
			//Even though this does full XML parsing on the output, because
			// it then caches the result this limits the amount of traffic to
			//the dav server (which makes it faster even over a local connection)
			$props = $this->dav_client->get_properties($path);
			if ($props[$path])
			{
				$this->debug('found');
				return True;
			}
			else
			{
				$this->debug('not found');
				return False;
			}
		}

		/*!
		@function get_size
		@abstract Return size of 'string'
		@param string file/directory to get size of
		@param relatives Relativity array
		@param checksubdirs Boolean, recursively add the size of all sub directories as well?
		@result Size of 'string' in bytes
		*/
		function get_size ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT),
					'checksubdirs'	=> True
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);

			if (!$this->acl_check (array(
					'string'	=> $p->fake_full_path,
					'relatives'	=> array ($p->mask),
					'operation'	=> PHPGW_ACL_READ,
					'must_exist'	=> True
				))
			)
			{
				return False;
			}

			/*
			   WIP - this should run through all of the subfiles/directories in the directory and tally up
			   their sizes.  Should modify ls () to be able to return a list for files outside the virtual root
			*/
			if ($p->outside){
			  return filesize($p->real_full_path);
			}

			$ls_array = $this->ls (array(
					'string'	=> $p->fake_full_path,
					'relatives'	=> array ($p->mask),
					'checksubdirs'	=> $data['checksubdirs'],
					'nofiles'	=> !$data['checksubdirs']
				)
			);

			while (list ($num, $file_array) = each ($ls_array))
			{
				/*
				   Make sure the file is in the directory we want, and not
				   some deeper nested directory with a similar name
				*/
/*
				if (@!ereg ('^' . $file_array['directory'], $p->fake_full_path))
				{
					continue;
				}
*/

				$size += $file_array['size'];
$this->debug('size:getting size from fs: '.$size);
			}

			return $size;
		}

		/*!
		@function checkperms
		@abstract Check if $this->working_id has write access to create files in $dir
		@discussion Simple call to acl_check
		@param string Directory to check access of
		@param relatives Relativity array
		@result Boolean True/False
		*/
		function checkperms ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT)
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);

			if (!$this->acl_check (array(
					'string'	=> $p->fake_full_path,
					'relatives'	=> array ($p->mask),
					'operation'	=> PHPGW_ACL_ADD
				))
			)
			{
				return False;
			}
			else
			{
				return True;
			}
		}

		/*!
		@function ls
		@abstract get directory listing or info about a single file
		@discussion Note: The entries are not guaranteed to be returned in any logical order
			    Note: The size for directories does not include subfiles/subdirectories.
				  If you need that, use $this->get_size ()
		@param string File or Directory
		@param relatives Relativity array
		@param checksubdirs Boolean, recursively list all sub directories as well?
		@param mime_type Only return entries matching MIME-type 'mime_type'.  Can be any MIME-type, "Directory" or "\ " for those without MIME types
		@param nofiles Boolean.  True means you want to return just the information about the directory $dir.  If $dir is a file, $nofiles is implied.  This is the equivalent of 'ls -ld $dir'
		@param orderby How to order results.  Note that this only works for directories inside the virtual root
		@result array of arrays.  Subarrays contain full info for each file/dir.
		*/
		function ls ($data)
		{
			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT),
					'checksubdirs'	=> True,
					'mime_type'	=> False,
					'nofiles'	=> False,
					'orderby'	=> 'directory'
				);
			$data = array_merge ($this->default_values ($data, $default_values), $data);
			//Stupid "nofiles" fix"
			if ($data['nofiles'])
			{
				$data['relatives'] = array (RELATIVE_NONE);
			}
			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);
		
			if ($data['checksubdirs']==False && ereg('.*/$', $data['string']) && $data['nofiles'] )
			{
$this->debug('Returning empty for'.$data['string']);
				return array();
			}
			$dir = $p->fake_full_path;
$this->debug("ls'ing dir: $dir path: ".$p->real_full_path);
			/* If they pass us a file or 'nofiles' is set, return the info for $dir only */
			if (((($type = $this->file_type (array(
					'string'	=> $dir,
					'relatives'	=> array ($p->mask)
				)) != 'Directory'))
				|| ($data['nofiles'])) && !$p->outside
			)
			{
$this->debug('ls branch 1');
			$prop=$this->dav_client->get_properties($p->real_full_path, 1);
			//make the key the 'orderby' attribute
			if (! ($data['orderby'] == 'directory'))
			{
				$tmp_prop = array();
				$id=0;
				foreach ( $prop as $key=>$value)
				{
					$id++;
					$new_key =  substr($value[$data['orderby']].'        ',0, 8);
					$tmp_prop[strtolower($new_key).'_'.$id] = $value;
				}
			}
			else 
			{
				$tmp_prop = $prop;
			}
			ksort($tmp_prop);
			$rarray = array ();
			foreach($tmp_prop as $idx => $value)
			{
				if($value['mime_type']==$data['mime_type'] or $data['mime_type']=='')
				{
					$directory = $this->path_parts($value['directory']);
					$value['directory'] = $directory->fake_full_path;
					if($value['is_dir']) $value['mime_type']='Directory';
					$rarray[] = $value;
				}
			}
$this->debug('ls returning 1:');
				return $rarray;
			}

			//WIP - this should recurse using the same options the virtual part of ls () does
			/* If $dir is outside the virutal root, we have to check the file system manually */
			if ($p->outside)
			{
$this->debug('ls branch 2 (outside)');
				if ($this->file_type (array(
						'string'	=> $p->fake_full_path,
						'relatives'	=> array ($p->mask)
					)) == 'Directory'
					&& !$data['nofiles']
				)
				{
					$dir_handle = opendir ($p->real_full_path);
					while ($filename = readdir ($dir_handle))
					{
						if ($filename == '.' || $filename == '..')
						{
							continue;
						}

						$rarray[] = $this->get_real_info (array(
								'string'	=> $p->real_full_path . SEP . $filename,
								'relatives'	=> array ($p->mask)
							)
						);
					}
				}
				else
				{
					$rarray[] = $this->get_real_info (array(
							'string'	=> $p->real_full_path,
							'relatives'	=> array ($p->mask)
						)
					);
				}
$this->debug('ls returning 2:');
				return $rarray;
			}
$this->debug('ls branch 3');
			/* $dir's not a file, is inside the virtual root, and they want to check subdirs */
			$prop=$this->dav_client->get_properties($p->real_full_path,1);
			unset($prop[$p->real_full_path]);
			//make the key the 'orderby' attribute

			if (! ($data['orderby'] == 'directory'))
			{
				$tmp_prop = array();
				$id=0;
				foreach ( $prop as $key=>$value)
				{
					$id++;
					$new_key =  substr($value[$data['orderby']].'        ',0, 8);
					$tmp_prop[strtolower($new_key).'_'.$id] = $value;
				}
			}
			else 
			{
				$tmp_prop = $prop;
			}
			
			ksort($tmp_prop);

			unset($tmp_prop[ $p->real_full_path]);
			$rarray = array ();
			foreach($tmp_prop as $idx => $value)
			{	
				if($data['mime_type']=='' || $value['mime_type']==$data['mime_type'])
				{
					//$directory = $this->path_parts($value['directory']);
					$value['directory'] = $p->fake_full_path;
					$rarray[] = $value;
				}
			}		
$this->debug('ls:returning 3:');
			return $rarray;
		}

		/*!
		@function dir
		@abstract shortcut to ls
		*/
		function dir ($data)
		{
			return $this->ls ($data);
		}

		/*!
		@function command_line
		@abstract Process and run a Unix-sytle command line
		@discussion EXPERIMENTAL.  DANGEROUS.  DO NOT USE THIS UNLESS YOU KNOW WHAT YOU'RE DOING!
			    This is mostly working, but the command parser needs to be improved to take
			    files with spaces into consideration (those should be in "").
		@param command_line Unix-style command line with one of the commands in the $args array
		@result $result The return value of the actual VFS call
		*/
		function command_line ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

			$args = array
			(
				array ('name'	=> 'mv', 'params'	=> 2),
				array ('name'	=> 'cp', 'params'	=> 2),
				array ('name'	=> 'rm', 'params'	=> 1),
				array ('name'	=> 'ls', 'params'	=> -1),
				array ('name'	=> 'du', 'params'	=> 1, 'func'	=> get_size),
				array ('name'	=> 'cd', 'params'	=> 1),
				array ('name'	=> 'pwd', 'params'	=> 0),
				array ('name'	=> 'cat', 'params'	=> 1, 'func'	=> read),
				array ('name'	=> 'file', 'params'	=> 1, 'func'	=> file_type),
				array ('name'	=> 'mkdir', 'params'	=> 1),
				array ('name'	=> 'touch', 'params'	=> 1)
			);

			if (!$first_space = strpos ($data['command_line'], ' '))
			{
				$first_space = strlen ($data['command_line']);
			}
			if ((!$last_space = strrpos ($data['command_line'], ' ')) || ($last_space == $first_space))
			{
				$last_space = strlen ($data['command_line']) + 1;
			}
			$argv[0] = substr ($data['command_line'], 0, $first_space);
			if (strlen ($argv[0]) != strlen ($data['command_line']))
			{
				$argv[1] = substr ($data['command_line'], $first_space + 1, $last_space - ($first_space + 1));
				if ((strlen ($argv[0]) + 1 + strlen ($argv[1])) != strlen ($data['command_line']))
				{
					$argv[2] = substr ($data['command_line'], $last_space + 1);
				}
			}
			$argc = count ($argv);

			reset ($args);
			while (list (,$arg_info) = each ($args))
			{
				if ($arg_info['name'] == $argv[0])
				{
					$command_ok = 1;
					if (($argc == ($arg_info['params'] + 1)) || ($arg_info['params'] == -1))
					{
						$param_count_ok = 1;
					}
					break;
				}
			}

			if (!$command_ok)
			{
//				return E_VFS_BAD_COMMAND;
				return False;
			}
			if (!$param_count_ok)
			{
//				return E_VFS_BAD_PARAM_COUNT;
				return False;
			}

			for ($i = 1; $i != ($arg_info['params'] + 1); $i++)
			{
				if (substr ($argv[$i], 0, 1) == '/')
				{
					$relatives[] = RELATIVE_NONE;
				}
				else
				{
					$relatives[] = RELATIVE_ALL;
				}
			}

			$func = $arg_info['func'] ? $arg_info['func'] : $arg_info['name'];

			if (!$argv[2])
			{
				$rv = $this->$func (array(
						'string'	=> $argv[1],
						'relatives'	=> $relatives
					)
				);
			}
			else
			{
				$rv = $this->$func (array(
						'from'	=> $argv[1],
						'to'	=> $argv[2],
						'relatives'	=> $relatives
					)
				);
			}

			return ($rv);
		}

		/* Helper functions */

		function default_values ($data, $default_values)
		{
		  if(!is_array($data)) $data=array();
			for ($i = 0; list ($key, $value) = each ($default_values); $i++)
			{
				if (!isset ($data[$key]))
				{
					$data[$key] = $value;
				}
			}

			return $data;
		}

		/* Since we are always dealing with real info, this just calls ls */
		function get_real_info ($data){
			if (!is_array ($data))
			{
				$data = array ();
			}

			$default_values = array
				(
					'relatives'	=> array (RELATIVE_CURRENT)
				);

			$data = array_merge ($this->default_values ($data, $default_values), $data);

			$p = $this->path_parts (array(
					'string'	=> $data['string'],
					'relatives'	=> array ($data['relatives'][0])
				)
			);

			if (is_dir ($p->real_full_path))
			{
				$mime_type = 'Directory';
			}
			else
			{
				$mime_type = $this->get_ext_mime_type (array(
						'string'	=> $p->fake_name
					)
				);

				if($mime_type)
				{
					$GLOBALS['phpgw']->db->query ("UPDATE phpgw_vfs SET mime_type='".$mime_type."' WHERE directory='".$p->fake_leading_dirs_clean."' AND name='".$p->fake_name_clean."'" . $this->extra_sql (array ('query_type' => VFS_SQL_SELECT)), __LINE__, __FILE__);
				}
			}

			$size = filesize ($p->real_full_path);
			$rarray = array(
				'directory' => $p->fake_leading_dirs,
				'name' => $p->fake_name,
				'size' => $size,
				'mime_type' => $mime_type
			);

			return ($rarray);
		}
		
		function update_real()
		{ //hmmm. things break without this, but it does nothing in this implementation
			return True;
		}
		
		function save_session()
		{
			//Save the overrided locks in the session
			$app = $GLOBALS['phpgw_info']['flags']['currentapp'];
			$a = array();
			foreach ($this->override_locks as $name => $token)
			{
				$a[] = $name.';'.$token;	
			}	
			$session_data = implode('\n', $a);
			$this->session = $GLOBALS['phpgw']->session->appsession ('vfs_dav',$app, base64_encode($session_data));
				
		}	
	}
?>
