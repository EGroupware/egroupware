<?php
  /**************************************************************************\
  * phpGroupWare API - VFS base class                                        *
  * This file written by Jason Wies (Zone) <zone@phpgroupware.org>           *
  * This class handles file/dir access for phpGroupWare                      *
  * Copyright (C) 2001 Jason Wies		                             *
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

	/* These are used in calls to add_journal (), and allow journal messages to be more standard */
	define ('VFS_OPERATION_CREATED', 1);
	define ('VFS_OPERATION_EDITED', 2);
	define ('VFS_OPERATION_EDITED_COMMENT', 4);
	define ('VFS_OPERATION_COPIED', 8);
	define ('VFS_OPERATION_MOVED', 16);
	define ('VFS_OPERATION_DELETED', 32);

	/*!
	 * @class path_class
	 * @abstract helper class for path_parts
	 */
	class path_class
	{
		var $mask;
		var $outside;
		var $fake_full_path;
		var $fake_leading_dirs;
		var $fake_extra_path;
		var $fake_name;
		var $real_full_path;
		var $real_leading_dirs;
		var $real_extra_path;
		var $real_name;
		var $fake_full_path_clean;
		var $fake_leading_dirs_clean;
		var $fake_extra_path_clean;
		var $fake_name_clean;
		var $real_full_path_clean;
		var $real_leading_dirs_clean;
		var $real_extra_path_clean;
		var $real_name_clean;
	}

	/*!
	 * @class vfs_shared
	 * @abstract Base class for Virtual File System classes
	 * @author Zone
	 */
	class vfs_shared
	{
		/*
		 * All VFS classes must have some form of 'linked directories'.
		 * Linked directories allow an otherwise disparate "real" directory
		 * to be linked into the "virtual" filesystem.  See make_link().
		 */
		var $linked_dirs = array ();

		/*
		 * All VFS classes need to support the access control in some form
		 * (see acl_check()).  There are times when applications will need
		 * to explictly disable access checking, for example when creating a
		 * user's home directory for the first time or when the admin is
		 * performing maintanence.  When override_acl is set, any access
		 * checks must return True.
		 */
		var $override_acl = 0;

		/*
		 * The current relativity.  See set_relative() and get_relative().
		 */
		var $relative;

		/*
		 * Implementation dependant 'base real directory'.  It is not required
		 * that derived classes use $basedir, but some of the shared functions
		 * below rely on it, so those functions will need to be overload if
		 * basedir isn't appropriate for a particular backend.
		 */
		var $basedir;

		/*
		 * Fake base directory.  Only the administrator should change this.
		 */
		var $fakebase = '/home';

		/*
		 * All derived classes must store certain information about each
		 * location.  The attributes in the 'attributes' array represent
		 * the minimum attributes that must be stored.  Derived classes
		 * should add to this array any custom attributes.
		 *
		 * Not all of the attributes below are appropriate for all backends.
		 * Those that don't apply can be replaced by dummy values, ie. '' or 0.
		 */
		var $attributes = array(
			'file_id',	/* Integer.  Unique to each location */
			'owner_id',	/* phpGW account_id of owner */
			'createdby_id', /* phpGW account_id of creator */
			'modifiedby_id',/* phpGW account_id of who last modified */
			'created',	/* Datetime created, in SQL format */
			'modified',	/* Datetime last modified, in SQL format */
			'size',		/* Size in bytes */
			'mime_type',	/* Mime type.  'Directory' for directories */
			'comment',	/* User-supplied comment.  Can be empty */
			'app',		/* Name of phpGW application responsible for location */
			'directory',	/* Directory location is in */
			'name',		/* Name of file/directory */
			'link_directory',	/* Directory location is linked to, if any */
			'link_name',		/* Name location is linked to, if any */
			'version',	/* Version of file.  May be 0 */
		);

		/*!
		 * @function vfs_shared
		 * @abstract constructor
		 * @description All derived classes should call this function in their
		 *		constructor ($this->vfs_shared())
		 */
		function vfs_shared ()
		{
		}

		/*
		 * Definitions for functions that every derived
		 * class must have, and suggestions for private functions
		 * to completement the public ones.  The prototypes for
		 * the public functions need to be uniform for all
		 * classes.  Of course, each derived class should overload these
		 * functions with their own version.
		 */

		/*
		 * Journal functions.
		 *
		 * See also: VFS_OPERATION_* defines
		 *
		 * Overview:
		 * Each action performed on a location
		 * should be recorded, in both machine and human
		 * readable format.
		 *
		 * PRIVATE functions (suggested examples only, not mandatory):
		 *
		 * add_journal - Add journal entry
		 * flush_journal - Clear all journal entries for a location
		 *
		 * PUBLIC functions (mandatory):
		 *
		 * get_journal - Get journal entries for a location
		 */

		/* Private, suggestions only */
		function add_journal ($data) {}
		function flush_journal ($data) {}

		/*!
		 * @function get_journal
		 * @abstract Get journal entries for a location
		 * @required string	Path to location
		 * @optional relatives	Relativity array (default: RELATIVE_CURRENT)
		 * @optional type	[0|1|2]
		 *				0 = any journal entries
		 *				1 = current journal entries
		 *				2 = deleted journal entries
		 * @result Array of arrays of journal entries
		 *	   The keys will vary depending on the implementation,
		 *	   with most attributes in this->attributes being valid,
		 *	   and these keys being mandatory:
		 *		created - Datetime in SQL format that journal entry
		 *			  was entered
		 *		comment - Human readable comment describing the action
		 *		version - May be 0 if the derived class does not support
		 *			  versioning
		 */
		function get_journal ($data) { return array(array()); }

		/*
		 * Access checking functions.
		 *
		 * Overview:
		 * Each derived class should have some kind of
		 * user and group access control.  This will
		 * usually be based directly on the ACL class.
		 *
		 * If $this->override_acl is set, acl_check()
		 * must always return True.
		 *
		 * PUBLIC functions (mandatory):
		 *
		 * acl_check() - Check access for a user to a given
		 */

		/*!
		 * @function acl_check
		 * @abstract Check access for a user to a given location
		 * @discussion If $this->override_acl is set, always return True
		 * @required string	Path to location
		 * @optional relatives	Relativity array (default: RELATIVE_CURRENT)
		 * @required operation	Operation to check access for.  Any combination
		 *			of the PHPGW_ACL_* defines, for example:
		 *			PHPGW_ACL_READ
		 *			PHPGW_ACL_READ|PHPGW_ACL_WRITE
		 * @optional owner_id	phpGW ID to check access for.
		 * 			Default: $GLOBALS['phpgw_info']['user']['account_id']
		 * @optional must_exist	If set, string must exist, and acl_check() must
		 *			return False if it doesn't.  If must_exist isn't
		 *			passed, and string doesn't exist, check the owner_id's
		 *			access to the parent directory, if it exists.
		 * @result Boolean.  True if access is ok, False otherwise.
		 */
		function acl_check ($data) { return True; }

		/*
		 * Operations functions.
		 *
		 * Overview:
		 * These functions perform basic file operations.
		 *
		 * PUBLIC functions (mandatory):
		 *
		 * read - Retreive file contents
		 *
		 * write - Store file contents
		 *
		 * touch - Create a file if it doesn't exist.
		 *	   Optionally, update the modified time and
		 *	   modified user if the file exists.
		 *
		 * cp - Copy location
		 *
		 * mv - Move location
		 *
		 * rm - Delete location
		 *
		 * mkdir - Create directory
		 */

		/*!
		 * @function read
		 * @abstract Retreive file contents
		 * @required string	Path to location
		 * @optional relatives	Relativity array (default: RELATIVE_CURRENT)
		 * @result String.  Contents of 'string', or False on error.
		 */
		function read ($data) { return False; }

		 /*!
		@function view
		@abstract Views the specified file (does not return!)
		@param string filename
		@param relatives Relativity array
		@result None (doesnt return)
		@discussion By default this function just reads the file and
		outputs it too the browser, after setting the content-type header 
		appropriately.  For some other VFS implementations though, there
		may be some more sensible way of viewing the file.
		*/
		 function view($data)
		 {
		 	
		 	$default_values = array
		 		(
					'relatives'	=> array (RELATIVE_CURRENT)
				);
			$data = array_merge ($this->default_values ($data, $default_values), $data);
 
			$GLOBALS['phpgw_info']['flags']['noheader'] = true;
			$GLOBALS['phpgw_info']['flags']['nonavbar'] = true;
			$GLOBALS['phpgw_info']['flags']['noappheader'] = true;
			$GLOBALS['phpgw_info']['flags']['noappfooter'] = true;
			$ls_array = $this->ls (array (
					'string'	=>  $data['string'],
					'relatives'	=> $data['relatives'],
					'checksubdirs'	=> False,
					'nofiles'	=> True
				)
			);
		
			if ($ls_array[0]['mime_type'])
			{
				$mime_type = $ls_array[0]['mime_type'];
			}
			elseif ($GLOBALS['settings']['viewtextplain'])
			{
				$mime_type = 'text/plain';
			}
		
			header('Content-type: ' . $mime_type);
			echo $this->read (array (
					'string'	=>  $data['string'],
					'relatives'	=> $data['relatives'],
				)
			);		
			exit(); 
		 }
		
		/*!
		 * @function write
		 * @abstract Store file contents
		 * @required string	Path to location
		 * @optional relatives	Relativity array (default: RELATIVE_CURRENT)
		 * @result Boolean.  True on success, False otherwise.
		 */
		function write ($data) { return False; }

		/*!
		 * @function touch
		 * @abstract Create a file if it doesn't exist.
		 *	     Optionally, update the modified time and
		 *	     modified user if the file exists.
		 * @required string	Path to location
		 * @optional relatives	Relativity array (default: RELATIVE_CURRENT)
		 * @result Boolean.  True on success, False otherwise.
		 */
		function touch ($data) { return False; }

		/*!
		 * @function cp
		 * @abstract Copy location
		 * @required from	Path to location to copy from
		 * @required to		Path to location to copy to
		 * @optional relatives	Relativity array (default: RELATIVE_CURRENT, RELATIVE_CURRENT)
		 * @result Boolean.  True on success, False otherwise.
		 */
		function cp ($data) { return False; }

		/*!
		 * @function mv
		 * @abstract Move location
		 * @required from	Path to location to move from
		 * @required to		Path to location to move to
		 * @optional relatives	Relativity array (default: RELATIVE_CURRENT, RELATIVE_CURRENT)
		 * @result Boolean.  True on success, False otherwise.
		 */
		function mv ($data) { return False; }

		/*!
		 * @function rm
		 * @abstract Delete location
		 * @required string	Path to location
		 * @optional relatives	Relativity array (default: RELATIVE_CURRENT)
		 * @result Boolean.  True on success, False otherwise.
		 */
		function rm ($data) { return False; }

		/*!
		 * @function mkdir
		 * @abstract Create directory
		 * @required string	Path to location
		 * @optional relatives	Relativity array (default: RELATIVE_CURRENT)
		 * @result Boolean.  True on success, False otherwise.
		 */
		function mkdir ($data) { return False; }

		/*
		 * Information functions.
		 *
		 * Overview:
		 * These functions set or return information about locations.
		 *
		 * PUBLIC functions (mandatory):
		 *
		 * set_attributes - Set attributes for a location
		 *
		 * file_exists - Check if a location (file or directory) exists
		 *
		 * get_size - Determine size of location
		 *
		 * ls - Return detailed information for location(s)
		 */

		/*!
		 * @function set_attributes
		 * @abstract Set attributes for a location
		 * @discussion Valid attributes are listed in vfs->attributes,
		 *	       which may be extended by each derived class
		 * @required string	Path to location
		 * @optional relatives	Relativity array (default: RELATIVE_CURRENT)
		 * @optional attributes	Keyed array of attributes.  Key is attribute
		 *			name, value is attribute value.
		 * @result Boolean.  True on success, False otherwise.
		 */
		 function set_attributes ($data) { return False; }

		/*!
		 * @function file_exists
		 * @abstract Check if a location (file or directory) exists
		 * @required string	Path to location
		 * @optional relatives	Relativity array (default: RELATIVE_CURRENT)
		 * @result Boolean.  True if file exists, False otherwise.
		 */
		function file_exists ($data) { return False; }

		/*!
		 * @function get_size
		 * @abstract Determine size of location
		 * @required string	Path to location
		 * @optional relatives	Relativity array (default: RELATIVE_CURRENT)
		 * @optional checksubdirs	Boolean.  If set, include the size of
		 *				all subdirectories recursively.
		 * @result Integer.  Size of location in bytes.
		 */
		function get_size ($data) { return 0; }

		/*!
		 * @function ls
		 * @abstract Return detailed information for location(s)
		 * @required string	Path to location
		 * @optional relatives	Relativity array (default: RELATIVE_CURRENT)
		 * @optional checksubdirs	Boolean.  If set, return information for all
		 *				subdirectories recursively.
		 * @optional mime	String.  Only return information for locations with MIME type
		 *			specified.  VFS classes must recogize these special types:
		 *				"Directory" - Location is a directory
		 *				" " - Location doesn't not have a MIME type
		 * @optional nofiles	Boolean.  If set and 'string' is a directory, return
		 *			information about the directory, not the files in it.
		 * @result Array of arrays of file information.
		 *	   Keys may vary depending on the implementation, but must include
		 *	   at least those attributes listed in $this->attributes.
		 */
		function ls ($data) { return array(array()); }

		/*
		 * Linked directory functions.
		 *
		 * Overview:
		 * One 'special' feature that VFS classes must support
		 * is linking an otherwise unrelated 'real' directory into
		 * the virtual filesystem.  For a traditional filesystem, this
		 * might mean linking /var/specialdir in the real filesystem to
		 * /home/user/specialdir in the VFS.  For networked filesystems,
		 * this might mean linking 'another.host.com/dir' to
		 * 'this.host.com/home/user/somedir'.
		 *
		 * This is a feature that will be used mostly be administrators,
		 * in order to present a consistent view to users.  Each VFS class
		 * will almost certainly need a new interface for the administrator
		 * to use to make links, but the concept is the same across all the
		 * VFS backends.
		 *
		 * Note that by using $this->linked_dirs in conjunction with
		 * $this->path_parts(), you can keep the implementation of linked
		 * directories very isolated in your code.
		 *
		 * PUBLIC functions (mandatory):
		 *
		 * make_link - Create a real to virtual directory link
		 */

		/*!
		 * @function make_link
		 * @abstract Create a real to virtual directory link
		 * @required rdir	Real directory to make link from/to
		 * @required vdir	Virtual directory to make link to/from
		 * @optional relatives	Relativity array (default: RELATIVE_CURRENT, RELATIVE_CURRENT)
		 * @result Boolean.  True on success, False otherwise.
		 */
		function make_link ($data) { return False; }

		/*
		 * Miscellaneous functions.
		 *
		 * PUBLIC functions (mandatory):
		 *
		 * update_real - Ensure that information about a location is
		 *		 up-to-date
		 */

		/*!
		 * @function update_real
		 * @abstract Ensure that information about a location is up-to-date
		 * @discussion Some VFS backends store information about locations
		 *	       in a secondary location, for example in a database
		 *	       or in a cache file.  update_real() can be called to
		 *	       ensure that the information in the secondary location
		 *	       is up-to-date.
		 * @required string	Path to location
		 * @optional relatives	Relativity array (default: RELATIVE_CURRENT)
		 * @result Boolean.  True on success, False otherwise.
		 */
		function update_real ($data) { return False; }
 
 		/*
		 * SHARED FUNCTIONS
		 *
		 * The rest of the functions in this file are shared between
		 * all derived VFS classes.
		 *
		 * Derived classes can overload any of these functions if they
		 * see it fit to do so, as long as the prototypes and return
		 * values are the same for public functions, and the function
		 * accomplishes the same goal.
		 *
		 * PRIVATE functions:
		 *
		 * securitycheck - Check if location string is ok to use in VFS functions
		 *
		 * sanitize - Remove any possible security problems from a location
		 *	      string (i.e. remove leading '..')
		 *
		 * clean_string - Clean location string.  This function is used if
		 *		  any special characters need to be escaped or removed
		 *		  before accessing a database, network protocol, etc.
		 *		  The default is to escape characters before doing an SQL
		 *		  query.
		 *
		 * getabsolutepath - Translate a location string depending on the
		 *		     relativity.  This is the only function that is
		 *		     directly concerned with relativity.
		 *
		 * get_ext_mime_type - Return MIME type based on file extension
		 *
		 * PUBLIC functions (mandatory):
		 *
		 * set_relative - Sets the current relativity, the relativity used
		 *		  when RELATIVE_CURRENT is passed to a function
		 *
		 * get_relative - Return the current relativity
		 *
		 * path_parts - Return information about the component parts of a location string
		 *
		 * cd - Change current directory.  This function is used to store the
		 *	current directory in a standard way, so that it may be accessed
		 *	throughout phpGroupWare to provide a consistent view for the user.
		 *
		 * pwd - Return current directory
		 *
		 * copy - Alias for cp
		 *
		 * move - Alias for mv
		 *
		 * delete - Alias for rm
		 *
		 * dir - Alias for ls
		 *
		 * command_line - Process and run a Unix-sytle command line
		 */

		/* PRIVATE functions */

		/*!
		 * @function securitycheck
		 * @abstract Check if location string is ok to use in VFS functions
		 * @discussion Checks for basic violations such as ..
		 *	       If securitycheck () fails, run your string through $this->sanitize ()
		 * @required string	Path to location
		 * @result Boolean.  True if string is ok, False otherwise.
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
		 * @function sanitize
		 * @abstract Remove any possible security problems from a location
		 *	     string (i.e. remove leading '..')
		 * @discussion You should not pass all filenames through sanitize ()
		 *	       unless you plan on rejecting .files.  Instead, pass
		 *	       the name through securitycheck () first, and if it fails,
		 *	       pass it through sanitize.
		 * @required string	Path to location
		 * @result String. 'string' with any security problems fixed.
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

			return (ereg_replace ("^\.+", '', $p->fake_name));
		}

		/*!
		 * @function clean_string
		 * @abstract Clean location string.  This function is used if
		 *	     any special characters need to be escaped or removed
		 *	     before accessing a database, network protocol, etc.
		 *	     The default is to escape characters before doing an SQL
		 *	     query.
		 * @required string	Location string to clean
		 * @result String.  Cleaned version of 'string'.
		 */
		function clean_string ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

			$string = ereg_replace ("'", "\'", $data['string']);

			return $string;
		}

		/*!
		 * @function getabsolutepath
		 * @abstract Translate a location string depending on the
		 *	     relativity. This is the only function that is
		 *	     directly concerned with relativity.
		 * @optional string	Path to location, relative to mask[0].
		 * 			Defaults to empty string.
		 * @optional mask	Relativity array (default: RELATIVE_CURRENT)
		 * @optional fake	Boolean.  If set, returns the 'fake' path,
		 *			i.e. /home/user/dir/file.  This is not always
		 *			possible,  use path_parts() instead.
		 * @result String. Full fake or real path, or False on error.
		 */
		function getabsolutepath ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

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
				$basedir = "/";
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
		 * @function get_ext_mime_type
		 * @abstract Return MIME type based on file extension
		 * @description Internal use only.  Applications should call vfs->file_type ()
		 * @author skeeter
		 * @required string	Real path to file, with or without leading paths
		 * @result String.  MIME type based on file extension.
		 */
		function get_ext_mime_type ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

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

		/* PUBLIC functions (mandatory) */

		/*!
		 * @function set_relative
		 * @abstract Sets the current relativity, the relativity used
		 *	     when RELATIVE_CURRENT is passed to a function
		 * @optional mask	Relative bitmask.  If not set, relativity
		 *			will be returned to the default.
		 * @result Void
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
		 * @function get_relative
		 * @abstract Return the current relativity
		 * @discussion Returns relativity bitmask, or the default
		 *	       of "completely relative" if unset
		 * @result Integer.  One of the RELATIVE_* defines.
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
		 * @function path_parts
		 * @abstract Return information about the component parts of a location string
		 * @discussion Most VFS functions call path_parts() with their 'string' and
		 *	       'relatives' arguments before doing their work, in order to
		 *	       determine the file/directory to work on.
		 * @required string	Path to location
		 * @optional relatives	Relativity array (default: RELATIVE_CURRENT)
		 * @optional object	If set, return an object instead of an array
		 * @optional nolinks	Don't check for linked directories (made with
		 *			make_link()).  Used internally to prevent recursion.
		 * @result Array or object.  Contains the fake and real component parts of the path.
		 * @discussion Returned values are:
		 *		mask
		 *		outside
		 *		fake_full_path
		 *		fake_leading_dirs
		 *		fake_extra_path		BROKEN
		 *		fake_name
		 *		real_full_path
		 *		real_leading_dirs
		 *		real_extra_path		BROKEN
		 *		real_name
		 *		fake_full_path_clean
		 *		fake_leading_dirs_clean
		 *		fake_extra_path_clean	BROKEN
		 *		fake_name_clean
		 *		real_full_path_clean
		 *		real_leading_dirs_clean
		 *		real_extra_path_clean	BROKEN
		 *		real_name_clean
		 *	"clean" values are run through vfs->clean_string () and
		 *	are safe for use in SQL queries that use key='value'
		 *	They should be used ONLY for SQL queries, so are used
		 *	mostly internally
		 *	mask is either RELATIVE_NONE or RELATIVE_NONE|VFS_REAL,
		 *	and is used internally
		 *	outside is boolean, True if 'relatives' contains VFS_REAL
		 */
		function path_parts ($data)
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

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

				$rarray['real_full_path'] = $string;
			}

			/* This is needed because of substr's handling of negative lengths */
			$baselen = strlen ($base);
			$lastslashpos = strrpos ($string, $base_sep);
			$lastslashpos < $baselen ? $length = 0 : $length = $lastslashpos - $baselen;

			$extra_path = $rarray['fake_extra_path'] = $rarray['real_extra_path'] = substr ($string, strlen ($base), $length);
			if($string[1] != ':')
			{
 				$name = $rarray['fake_name'] = $rarray['real_name'] = substr ($string, strrpos ($string, $base_sep) + 1);
			}
			else
			{
				$name = $rarray['fake_name'] = $rarray['real_name'] = $string;
			}

			if ($fake)
			{
				$rarray['real_extra_path'] ? $dispsep = $sep : $dispsep = '';
				$rarray['real_full_path'] = $opp_base . $rarray['real_extra_path'] . $dispsep . $rarray['real_name'];
				if ($extra_path)
				{
					$rarray['fake_leading_dirs'] = $base . $extra_path;
					$rarray['real_leading_dirs'] = $opp_base . $extra_path;
				}
				elseif (strrpos ($rarray['fake_full_path'], $sep) == 0)
				{
					/* If there is only one $sep in the path, we don't want to strip it off */
					$rarray['fake_leading_dirs'] = $sep;
					$rarray['real_leading_dirs'] = substr ($opp_base, 0, strlen ($opp_base) - 1);
				}
				else
				{
					/* These strip the ending / */
					$rarray['fake_leading_dirs'] = substr ($base, 0, strlen ($base) - 1);
					$rarray['real_leading_dirs'] = substr ($opp_base, 0, strlen ($opp_base) - 1);
				}
			}
			else
			{
				if($rarray['fake_name'][1] != ':')
				{
 					$rarray['fake_full_path'] = $opp_base . $rarray['fake_extra_path'] . '/' . $rarray['fake_name'];
				}
				else
				{
					$rarray['fake_full_path'] = $rarray['fake_name'];
				}
				if ($extra_path)
				{
					$rarray['fake_leading_dirs'] = $opp_base . $extra_path;
					$rarray['real_leading_dirs'] = $base . $extra_path;
				}
				else
				{
					$rarray['fake_leading_dirs'] = substr ($opp_base, 0, strlen ($opp_base) - 1);
					$rarray['real_leading_dirs'] = substr ($base, 0, strlen ($base) - 1);
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

						$rarray['real_leading_dirs'] = $p->real_leading_dirs;
						$rarray['real_extra_path'] = $p->real_extra_path;
						$rarray['real_name'] = $p->real_name;
					}
				}
			}

			/*
			   We have to count it before because new keys will be added,
			   which would create an endless loop
			*/
			$count = count ($rarray);
			reset ($rarray);
			for ($i = 0; (list ($key, $value) = each ($rarray)) && $i != $count; $i++)
			{
				$rarray[$key . '_clean'] = $this->clean_string (array ('string' => $value));
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
		 * @function cd
		 * @abstract Change current directory.  This function is used to store the
		 *	     current directory in a standard way, so that it may be accessed
		 *	     throughout phpGroupWare to provide a consistent view for the user.
		 * @discussion To cd to the root '/', use:
		 *		cd (array(
		 *			'string' => '/',
		 *			'relative' => False,
		 *			'relatives' => array (RELATIVE_NONE)
		 *		));
		 * @optional string	Directory location to cd into.  Default is '/'.
		 * @optional relative	If set, add target to current path.
		 *			Else, pass 'relative' as mask to getabsolutepath()
		 *			Default is True.
		 * @optional relatives	Relativity array (default: RELATIVE_CURRENT)
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
		 * @function pwd
		 * @abstract Return current directory
		 * @optional full	If set, return full fake path, else just
		 *			the extra dirs (False strips the leading /).
		 *			Default is True.
		 * @result String.  The current directory.
		 */
		function pwd ($data = '')
		{
			if (!is_array ($data))
			{
				$data = array ();
			}

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
		 * @function copy
		 * @abstract shortcut to cp
		 */
		function copy ($data)
		{
			return $this->cp ($data);
		}

		/*!
		 * @function move
		 * @abstract shortcut to mv
		 */
		function move ($data)
		{
			return $this->mv ($data);
		}

		/*!
		 * @function delete
		 * @abstract shortcut to rm
		 */
		function delete ($data)
		{
			return $this->rm ($data);
		}

		/*!
		 * @function dir
		 * @abstract shortcut to ls
		 */
		function dir ($data)
		{
			return $this->ls ($data);
		}

		/*!
		 * @function command_line
		 * @abstract Process and run a Unix-sytle command line
		 * @discussion EXPERIMENTAL.  DANGEROUS.  DO NOT USE THIS UNLESS YOU
		 *	       KNOW WHAT YOU'RE DOING!
		 * 	       This is mostly working, but the command parser needs
		 *	       to be improved to take files with spaces into
		 *	       consideration (those should be in "").
		 * @required command_line	Unix-style command line with one of the
		 *				commands in the $args array
		 * @result The return value of the actual VFS call
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
				if (substr ($argv[$i], 0, 1) == "/")
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

		/* Helper functions, not public */

		function default_values ($data, $default_values)
		{
			for ($i = 0; list ($key, $value) = each ($default_values); $i++)
			{
				if (!isset ($data[$key]))
				{
					$data[$key] = $value;
				}
			}

			return $data;
		}
	}

?>
