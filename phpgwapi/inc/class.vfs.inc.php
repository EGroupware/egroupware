<?php
  /**************************************************************************\
  * phpGroupWare API - VFS                                                   *
  * This file written by Jason Wies (Zone) <zone@users.sourceforge.net>      *
  * and Dan Kuykendall <seek3r@phpgroupware.org>,                            *
  * This class handles file/dir access for phpGroupWare                      *
  * Copyright (C) 2000, 2001 Dan Kuykendall, Jason Wies                      *
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
		@class vfs
		@abstract virtual file system
		@description Authors: Zone, Seek3r
		*/

	/* Relative defines.  Used mainly by getabsolutepath () */
	define (RELATIVE_ROOT, 1);
	define (RELATIVE_USER, 2);
	define (RELATIVE_CURR_USER, 4);
	define (RELATIVE_USER_APP, 8);
	define (RELATIVE_PATH, 16);
	define (RELATIVE_NONE, 32);
	define (RELATIVE_CURRENT, 64);
	define (VFS_REAL, 1024);
	define (RELATIVE_ALL, RELATIVE_PATH);

	/*!
	@class path_class
	@abstract helper class for path_parts
	*/

	class path_class
	{
		var $fake_full_path;
		var $fake_leading_dirs;
		var $fake_extra_path;
		var $fake_name;
		var $real_full_path;
		var $real_leading_dirs;
		var $real_extra_path;
		var $real_name;
	}


class vfs
{
	var $basedir;
	var $fakebase;
	var $relative;
	var $working_id;
	var $working_lid;
	var $attributes;

	/*!
	@function vfs
	@abstract constructor, sets up variables
	*/

	function vfs ()
	{
		global $phpgw, $phpgw_info;

		$this->basedir = PHPGW_SERVER_ROOT . $phpgw_info["server"]["files_dir"];
		$this->fakebase = "/home";
		$this->working_id = $phpgw_info["user"]["account_id"];
		$this->working_lid = $phpgw->accounts->id2name ($this->working_id);

		/* File/dir attributes, each corresponding to a database field.  Useful for use in loops */
		$this->attributes = array ("file_id", "owner_id", "createdby_id", "modifiedby_id", "created", "modified", "size", "mime_type", "deletable", "comment", "app", "directory", "name");

	}

	/*!
	@function set_relative
	@abstract Set path relativity
	@param $mask Relative bitmask (see RELATIVE_ defines)
	*/

	function set_relative ($mask)
	{
		if (!$mask)
			unset ($this->relative);
		else
			$this->relative = $mask;
	}

	/*!
	@function get_relative
	@abstract Return relativity bitmask
	@discussion Returns relativity bitmask, or the default of "completely relative" if unset
	*/

	function get_relative ()
	{
		if (isset ($this->relative))
			return $this->relative;
		else
			return RELATIVE_ALL;
	}

	/*!
 	@function sanitize
 	@abstract Removes leading .'s from $string
	@discussion You should not pass all filenames through sanitize () unless you plan on rejecting
			.files.  Instead, pass the name through securitycheck () first, and if it fails,
			pass it through sanitize
	@param $string string to sanitize
	@result $string without it's leading .'s
 	*/

	function sanitize ($string)
	{
		global $phpgw, $phpgw_info;

		/* We use path_parts () just to parse the string, not translate paths */
		$p = $this->path_parts ($string, array (RELATIVE_NONE));

		return (ereg_replace ("^\.+", "", $p->fake_name));
	}

	/*!
	@function securitycheck
	@abstract Security check function
	@discussion Checks for basic violations such as ..
			If securitycheck () fails, run your string through vfs->sanitize ()
	@param $string string to check security of
	@result Boolean True/False.  True means secure, False means insecure
	*/

	function securitycheck ($string)
	{
		if (substr ($string, 0, 1) == "\\" || strstr ($string, "..") || strstr ($string, "\\..") || strstr ($string, ".\\."))
		{
			return False;
		}
		else
		{
			return True;
		}
	}

	/*!
	@function path_parts
	@abstract take a real or fake pathname and return an array of its component parts
	@param $string full real or fake path
	@param $relatives Relativity array
	@param $object True returns an object instead of an array
	@result $rarray/$robject Array or object containing the fake and real component parts of the path
	@discussion Returned values are:
			fake_full_path
			fake_leading_dirs
			fake_extra_path
			fake_name
			real_full_path
			real_leading_dirs
			real_extra_path
			real_name
	*/

	function path_parts ($string, $relatives = array (RELATIVE_CURRENT), $object = True)
	{
		global $phpgw, $phpgw_info;
		$sep = $phpgw_info["server"]["dir_separator"];

		if (!($relatives[0] & VFS_REAL))
		{
			$fake = True;
		}

		$string = $this->getabsolutepath ($string, array ($relatives[0]), $fake);

		if ($fake)
		{
			$base_sep = "/";
			$base = "/";

			$opp_base = $this->basedir . $sep;

			$rarray["fake_full_path"] = $string;
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

			$opp_base = "/";

			$rarray["real_full_path"] = $string;
		}

		/* This is needed because of substr's handling of negative lengths */
		$baselen = strlen ($base);
		$lastslashpos = strrpos ($string, $base_sep);
		$lastslashpos < $baselen ? $length = 0 : $length = $lastslashpos - $baselen;

		$extra_path = $rarray["fake_extra_path"] = $rarray["real_extra_path"] = substr ($string, strlen ($base), $length);
		$name = $rarray["fake_name"] = $rarray["real_name"] = substr ($string, strrpos ($string, $base_sep) + 1);
	
		if ($fake)
		{
			$rarray["real_extra_path"] ? $dispsep = $sep : $dispsep = "";
			$rarray["real_full_path"] = $opp_base . $rarray["real_extra_path"] . $dispsep . $rarray["real_name"];
			if ($extra_path)
			{
				$rarray["fake_leading_dirs"] = $base . $extra_path;
				$rarray["real_leading_dirs"] = $opp_base . $extra_path;
			}
			else
			{
				/* These strip the ending / */
				$rarray["fake_leading_dirs"] = substr ($base, 0, strlen ($base) - 1);
				$rarray["real_leading_dirs"] = substr ($opp_base, 0, strlen ($opp_base) - 1);
			}
		}
		else
		{
			$rarray["fake_full_path"] = $opp_base . $rarray["fake_extra_path"] . "/" . $rarray["fake_name"];
			if ($extra_path)
			{
				$rarray["fake_leading_dirs"] = $opp_base . $extra_path;
				$rarray["real_leading_dirs"] = $base . $extra_path;
			}
			else
			{
				$rarray["fake_leading_dirs"] = substr ($opp_base, 0, strlen ($opp_base) - 1);
				$rarray["real_leading_dirs"] = substr ($base, 0, strlen ($base) - 1);
			}
		}

		if ($object)
		{
			$robject = new path_class;
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

		if ($object)
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
	@param $target defaults to False, directory/file to get path of, relative to $relatives[0]
	@param $mask Relativity bitmask (see RELATIVE_ defines).  RELATIVE_CURRENT means use $this->relative
	@param $fake Returns the "fake" path, ie /home/user/dir/file (not always possible.  use path_parts () instead)
	@result $basedir Full fake or real path
	*/      

	function getabsolutepath ($target = False, $relatives = array (RELATIVE_CURRENT), $fake = True)
	{
		global $phpgw, $phpgw_info;

		$currentdir = $this->pwd (False);

		if (!is_array ($relatives))
		{
			$relatives = array (RELATIVE_CURRENT);
		}

		/* If they supply just VFS_REAL, we assume they want current relativity */
		if ($relatives[0] == VFS_REAL)
		{
			$relatives[0] |= RELATIVE_CURRENT;
		}

		if (!$this->securitycheck ($target))
		{
			return False;
		}

		if ($relatives[0] & RELATIVE_NONE)
		{
			return $target;
		}

		if ($fake)
		{
			$sep = "/";
		}
		else
		{
			$sep = $phpgw_info["server"]["dir_separator"];
		}

		/* if RELATIVE_CURRENT, retrieve the current mask */
		if ($relatives[0] & RELATIVE_CURRENT)
		{
			$mask = $relatives[0];
			/* Respect any additional masks by re-adding them after retrieving the current mask*/
			$relatives[0] = $this->get_relative () + ($mask - RELATIVE_CURRENT);
		}

		if ($fake)
		{
			$basedir = "/";
		}
		else
		{
			$basedir = PHPGW_SERVER_ROOT;
			$basedir = $basedir . $phpgw_info["server"]["files_dir"] . $sep;
		}

		if (($relatives[0] & RELATIVE_PATH) && $currentdir)
		{
			$basedir = $basedir . $currentdir . $sep;
		}
		elseif (($relatives[0] & RELATIVE_USER) || ($relatives[0] & RELATIVE_USER_APP))
		{
			$basedir = $basedir . $this->fakebase . $sep;
		}

		if ($relatives[0] & RELATIVE_CURR_USER)
		{
			$basedir = $basedir . $this->working_lid . $sep;
		}

		if (($relatives[0] & RELATIVE_USER) || ($relatives[0] & RELATIVE_USER_APP))
		{
			$basedir = $basedir . $phpgw_info["user"]["account_lid"] . $sep;
		}

		if ($relatives[0] & RELATIVE_USER_APP)
		{
			$basedir = $basedir . "." . $phpgw_info["flags"]["currentapp"] . $sep;
		}

		/* Don't add target if it's a /, just for aesthetics */
		if ($target && $target != $sep)
			$basedir = $basedir . $target;

		/* Let's not return // */
		while (ereg ($sep . $sep, $basedir))
		{
			$basedir = ereg_replace ($sep . $sep, $sep, $basedir);
		}

		$basedir = ereg_replace ("$sep$", "", $basedir);

		return $basedir;
	}

	/*!
	@function cd
	@abstract Change directory
	@discussion To cd to the files root "/", use cd ("/", False, array (RELATIVE_NONE));
	@param $target default "/".  directory to cd into.  if "/" and $relative is True, uses "/home/<working_lid>";
	@param $relative default True/relative means add target to current path, else pass $relative as mask to getabsolutepath()
	*/

	function cd ($target = "/", $relative = True, $relatives = array (RELATIVE_CURRENT))
	{
		global $phpgw, $phpgw_info;

		if ($relatives[0] & VFS_REAL)
		{
			$sep = $phpgw_info["server"]["dir_separator"];
		}
		else
		{
			$sep = "/";
		}

		if ($relative == "relative" || $relative == True)
		{
			/* if $target is "/" and $relative is set, we cd to the user/group home dir */
			if ($target == "/")
			{
				$relatives[0] = RELATIVE_USER;
				$basedir = $this->getabsolutepath (False, array ($relatives[0]), True);
			}
			else
			{
				$currentdir = $phpgw->common->appsession ();
				$basedir = $this->getabsolutepath ($currentdir . $sep . $target, array ($relatives[0]), True);
			}
		}
		else
		{
			$basedir = $this->getabsolutepath ($target, array ($relatives[0]));
		}

		$phpgw->common->appsession ($basedir);

		return True;
	}

	/*!
	@function pwd
	@abstract current working dir
	@param $full default True returns full fake path, else just the extra dirs (false strips the leading /)
	@result $currentdir currentdir
	*/

	function pwd ($full = True)
	{
		global $phpgw;

		$currentdir = $phpgw->common->appsession ();

		if (!$full)
		{
			$currentdir = ereg_replace ("^/", "", $currentdir);
		}

		if ($currentdir == "" && $full)
		{
			$currentdir = "/";
		}

		return $currentdir;
	}

	/*!
	@function read
	@abstract return file contents
	@param $file filename
	@param $relatives Relativity array
	@result $contents Contents of $file, or False if file cannot be read
	*/

	function read ($file, $relatives = array (RELATIVE_CURRENT))
	{
		global $phpgw;
		global $phpgw_info;

		$p = $this->path_parts ($file, array ($relatives[0]));

		if ($fp = fopen ($p->real_full_path, "r"))
		{
			$contents = fread ($fp, filesize ($p->real_full_path));
			fclose ($fp);

			return $contents;
		}
		else
		{
			return False;
		}
	}

	/*!
	@function write
	@abstract write to a file
	@param $file file name
	@param $contents contents
	@param $relatives Relativity array
	@result int 1 for success 0 if not
	*/		

	function write ($file, $contents, $relatives = array (RELATIVE_CURRENT))
	{
		global $phpgw;
		global $phpgw_info;

		$p = $this->path_parts ($file, array ($relatives[0]));

		umask(000);

		if (!$this->file_exists ($p->fake_full_path, array (RELATIVE_NONE)))
		{
			$this->touch ($p->fake_full_path, array (RELATIVE_NONE));
		}

		if ($fp = fopen ($p->real_full_path, "w"))
		{
			fwrite ($fp, $contents, strlen ($contents));
			fclose ($fp);

			$this->set_attributes ($p->fake_full_path, array ("size" => filesize ($p->real_full_path)), array (RELATIVE_NONE));

			return True;
		}
		else
		{
			return False;
		}
	}

	/*!
	@function touch
	@abstract Create blank file $file or set the modification time and modified by of $file to current time and user
	@param $file File to touch or set modifies
	@param $relatives Relativity array
	@result Boolean True/False
	*/

	function touch ($file, $relatives = array (RELATIVE_CURRENT))
	{
		global $phpgw, $phpgw_info;

		$account_id = $phpgw_info["user"]["account_id"];
		$currentapp = $phpgw_info["flags"]["currentapp"];

		$p = $this->path_parts ($file, array ($relatives[0]));

		umask (000);

		/*
		   PHP's touch function will automatically decide whether to
		   create the file or set the modification time
		*/
		$rr = touch ($p->real_full_path);

		/* We, however, have to decide this ourselves */
		if ($this->file_exists ($p->fake_full_path, array (RELATIVE_NONE)))
		{
			$vr = $this->set_attributes ($p->fake_full_path, array ("modifiedby_id" => $account_id, "modified" => date ("Y-m-d")), array (RELATIVE_NONE));
		}
		else
		{
			$query = $phpgw->db->query ("INSERT INTO phpgw_vfs SET owner_id='$this->working_id', createdby_id='$account_id', created=NOW(), size=0, deleteable='Y', app='$this->currentapp', directory='$p->fake_leading_dirs', name='$p->fake_name'");
		}

		if ($tr || $vr || $query)
		{
			return True;
		}
		else
		{
			return False;
		}
	}

	/*!
	@function cp
	@abstract copy file
	@param $from from file/directory
	@param $to to file/directory
	@param $relatives Relativity array
	@result boolean True/False
	*/

	function cp ($from, $to, $relatives = array (RELATIVE_CURRENT, RELATIVE_CURRENT))
	{
		global $phpgw;
		global $phpgw_info;

		$account_id = $phpgw_info["user"]["account_id"];

		$f = $this->path_parts ($from, array ($relatives[0]));
		$t = $this->path_parts ($to, array ($relatives[1]));

		umask(000);

		if ($this->file_type ($from, array ($relatives[0])) != "Directory")
		{
			if (!copy ($f->real_full_path, $t->real_full_path))
			{
				return False;
			}
			else
			{
				$size = filesize ($t->real_full_path);

				$query = $phpgw->db->query ("SELECT size, mime_type, deleteable, comment, app FROM phpgw_vfs WHERE owner_id='$this->working_id' AND directory='$f->fake_leading_dirs' AND name='$f->fake_name'");
				$phpgw->db->next_record ();
				$record = $phpgw->db->Record;

				if ($this->file_exists ($to, array ($relatives[1])))
				{
					$phpgw->db->query ("UPDATE phpgw_vfs SET owner_id='$this->working_id', createdby_id='$account_id', created = NOW(), size='$size', mime_type='$record[mime_type]', deleteable='Y', comment='$record[comment]', app='$record[app]', directory='$t->fake_leading_dirs', name='$->fake_name' WHERE owner_id='$this->working_id' AND directory='$t->fake_leading_dirs' AND name='$t->fake_name'");
				}
				else
				{
					$phpgw->db->query ("INSERT INTO phpgw_vfs SET owner_id='$this->working_id', createdby_id='$account_id', created = NOW(), size='$size', deleteable='Y', comment='$record[comment]', app='$record[app]', directory='$t->fake_leading_dirs', name='$t->fake_name'");
				}

				return True;
			}
		}
		else	/* It's a directory */
		{
			/* First, make the initial directory */
			$this->mkdir ($to, array ($relatives[1]));

			/* Next, we create all the directories below the initial directory */
			$ls = $this->ls ($f->fake_full_path, array (RELATIVE_NONE), True, "Directory");

			while (list ($num, $entry) = each ($ls))
			{
				$newdir = ereg_replace ("^$f->fake_full_path", "$t->fake_full_path", $entry["directory"]);
				$this->mkdir ("$newdir/$entry[name]", array (RELATIVE_NONE));
			}

			/* Lastly, we copy the files over */
			$ls = $this->ls ($f->fake_full_path, array (RELATIVE_NONE));

			while (list ($num, $entry) = each ($ls))
			{
				if ($entry["mime_type"] == "Directory")
				{
					continue;
				}

				$newdir = ereg_replace ("^$f->fake_full_path", "$t->fake_full_path", $entry["directory"]);
				$this->cp ("$entry[directory]/$entry[name]", "$newdir/$entry[name]", array (RELATIVE_NONE, RELATIVE_NONE));
			}

			return True;
		}
	}

	function copy ($from, $to, $relatives = array (RELATIVE_CURRENT, RELATIVE_CURRENT))
	{
		umask (000);
		return $this->cp ($from, $to);
	}

	/*!
	@function mv
	@abstract move file/directory
	@param $from from file/directory
	@param $to to file/directory
	@param $relatives Relativity array
	@result boolean True/False
	*/

	function mv ($from, $to, $relatives = array (RELATIVE_CURRENT, RELATIVE_CURRENT))
	{
		global $phpgw;
		global $phpgw_info;

		$account_id = $phpgw_info["user"]["account_id"];

		$f = $this->path_parts ($from, array ($relatives[0]));
		$t = $this->path_parts ($to, array ($relatives[1]));

		umask (000);

		if ($this->file_exists ($f->fake_full_path, array (RELATIVE_NONE)))
		{
			/* We get the listing now, because it will change after we update the database */
			$ls = $this->ls ($f->fake_full_path, array (RELATIVE_NONE));

			$this->delete ($t->fake_full_path, array (RELATIVE_NONE));
			$query = $phpgw->db->query ("UPDATE phpgw_vfs SET name='$t->fake_name', directory='$t->fake_leading_dirs', modifiedby_id='$account_id', modified=NOW() WHERE owner_id='$this->working_id' AND directory='$f->fake_leading_dirs' AND name='$f->fake_name'");

			$rr = rename ($f->real_full_path, $t->real_full_path);
		}
		else
		{
			return False;
		}

		if ($this->file_type ($t->fake_full_path, array (RELATIVE_NONE)) == "Directory")
		{
			/* We got $ls from above, before we renamed the directory */
			while (list ($num, $entry) = each ($ls))
			{
				$newdir = ereg_replace ("^$f->fake_full_path", $t->fake_full_path, $entry["directory"]);
				$query = $phpgw->db->query ("UPDATE phpgw_vfs SET directory='$newdir' WHERE file_id='$entry[file_id]'");
			}
		}

		return True;
	}

	/*!
	@function move
	@abstract shortcut to mv
	*/

	function move ($from, $to, $relatives = array (RELATIVE_CURRENT, RELATIVE_CURRENT))
	{
		umask (000);
		return $this->mv ($from, $to, $relatives);
	}

	/*!
	@function rm
	@abstract delete file/directory
	@param $string file/directory to delete
	@param $relatives Relativity array
	@result boolean True/False
	*/

	function rm ($string, $relatives = array (RELATIVE_CURRENT))
	{
		global $phpgw;
		global $phpgw_info;

		$account_id = $phpgw_info["user"]["account_id"];

		$p = $this->path_parts ($string, array ($relatives[0]));

		if (!$this->file_exists ($string, array ($relatives[0])))
		{
			unlink ($p->real_full_path);

			return True;
		}

		if ($this->file_type ($string, array ($relatives[0])) != "Directory")
		{
			$query = $phpgw->db->query ("DELETE FROM phpgw_vfs WHERE directory='$p->fake_leading_dirs' AND name='$p->fake_name'");
			$rr = unlink ($p->real_full_path);

			if ($query || $rr)
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
			$ls = $this->ls ($p->fake_full_path, array (RELATIVE_NONE));

			/* First, we cycle through the entries and delete the files */
			while (list ($num, $entry) = each ($ls))
			{
				if ($entry["mime_type"] == "Directory")
				{
					continue;
				}

				$this->rm ("$entry[directory]/$entry[name]", array (RELATIVE_NONE));
			}

			/* Now we cycle through again and delete the directories */
			reset ($ls);
			while (list ($num, $entry) = each ($ls))
			{
				if ($entry["mime_type"] != "Directory")
				{
					continue;
				}

				/* Only the best in confusing recursion */
				$this->rm ("$entry[directory]/$entry[name]", array (RELATIVE_NONE));
			}

			/* Last, we delete the directory itself */
			$query = $phpgw->db->query ("DELETE FROM phpgw_vfs WHERE owner_id='$this->working_id' AND directory='$p->fake_leading_dirs' AND name='$p->fake_name'");
			rmdir ($p->real_full_path);

			return True;
		}
	}

	/*!
	@function delete
	@abstract shortcut to rm
	*/

	function delete ($string, $relatives = array (RELATIVE_CURRENT))
	{
		return $this->rm ($string, $relatives);
	}

	/*!
	@function mkdir
	@abstract make a new directory
	@param $dir Directory name
	@param $relatives Relativity array
	@result boolean True on success
	*/

	function mkdir ($dir, $relatives = array (RELATIVE_CURRENT))
	{
		global $phpgw;
		global $phpgw_info;

		$account_id = $phpgw_info["user"]["account_id"];
		$currentapp = $phpgw_info["flags"]["currentapp"];

		$p = $this->path_parts ($dir, array ($relatives[0]));

		/* We don't allow /'s in dir names, of course */
		if (ereg ("/", $p->fake_name))
		{
			return False;
		}

		umask (000);

		if (!mkdir ($p->real_full_path, 01770))
		{
			echo "Couldn't make real dir";
			return False;
		}
		else
		{
			if (!$this->file_exists ($p->fake_leading_dirs . "/" . $dir, array (RELATIVE_NONE)))
			{
				$query = $phpgw->db->query ("INSERT INTO phpgw_vfs SET owner_id='$this->working_id', createdby_id='" . $phpgw_info["user"]["account_id"] . "', name='$p->fake_name', size=1024, mime_type='Directory', created=NOW(), modified='', deleteable='Y', app='$currentapp', directory='$p->fake_leading_dirs'");
			}
			else
			{
				return False;
			}

			return True;
		}
	}

	/*!
	@function set_attributes
	@abstract Update database entry for $file with the attributes in $attributes
	@param $file file/directory to update
	@param $attributes keyed array of attributes.  key is attribute name, value is attribute value
	@param $relatives Relativity array
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
	*/

	function set_attributes ($file, $attributes = array (), $relatives = array (RELATIVE_CURRENT))
	{
		global $phpgw;
		global $phpgw_info;

		$p = $this->path_parts ($file, array ($relatives[0]));

		if (!$this->file_exists ($file, array ($relatives[0])))
		{
			return False;
		}

		/*
		   All this voodoo just decides which attributes to keep, and which to update
		   depending on if the attribute was supplied in the $attributes array
		*/
		
		$query = $phpgw->db->query ("SELECT file_id, owner_id, createdby_id, modifiedby_id, created, modified, size, mime_type, deleteable, comment, app FROM phpgw_vfs WHERE owner_id='$this->working_id' AND directory='$p->fake_leading_dirs' AND name='$p->fake_name'");
		$phpgw->db->next_record ();
		$record = $phpgw->db->Record;

		$attribute_names = array ("owner_id", "createdby_id", "modifiedby_id", "created", "modified", "size", "mime_type", "deleteable", "comment", "app");

		while (list ($num, $attribute) = each ($attribute_names))
		{
			if ($attributes[$attribute])
			{
				$$attribute = $attributes[$attribute];
			}
			else
			{
				$$attribute = $record[$attribute];
			}
		}

		$query = $phpgw->db->query ("UPDATE phpgw_vfs SET owner_id='$owner_id', createdby_id='$createdby_id', modifiedby_id='$modifiedby_id', created='$created', modified='$modified', size='$size', mime_type='$mime_type', deleteable='$deleteable', comment='$comment', app='$app' WHERE file_id='$record[file_id]'");

		if ($query) 
		{
			return True;
		}
		else
		{
			return False;
		}
	}

	/*!
	@function file_type
	@abstract return file/dir type (MIME or other)
	@param $file File or directory path (/home/user/dir/dir2/dir3, /home/user/dir/dir2/file)
	@param $relatives Relativity array
	@result MIME type, "Directory", or nothing if MIME type is not known
	*/

	function file_type ($file, $relatives = array (RELATIVE_CURRENT))
	{
		global $phpgw;

		$p = $this->path_parts ($file, array ($relatives[0]));

		$query = $phpgw->db->query ("SELECT mime_type FROM phpgw_vfs WHERE owner_id='$this->working_id' AND directory='$p->fake_leading_dirs' AND name='$p->fake_name'");
		$phpgw->db->next_record ();
		$mime_type = $phpgw->db->Record["mime_type"];

		return ($mime_type);
	}

	/*!
	@function file_exists
	@abstract check if file/directory exists
	@param $string file/directory to check existance of
	@param $relatives Relativity array
	*/

	function file_exists ($string, $relatives = array (RELATIVE_CURRENT))
	{
		global $phpgw;

		$p = $this->path_parts ($string, array ($relatives[0]));

		$query = $phpgw->db->query ("SELECT name FROM phpgw_vfs WHERE owner_id='$this->working_id' AND directory='$p->fake_leading_dirs' AND name='$p->fake_name'");

		if ($phpgw->db->next_record ())
		{
			return True;
		}
		else
		{
			return False;
		}
	}

	/*!
	@function checkperms
	@abstract Check if you have write access to create files in $dir
	@discussion This isn't perfect, because vfs->touch () returns True even
			if only the database entry worked.  ACLs need to be
			implemented for better permission checking.  It's
			also pretty slow, so I wouldn't recommend using it
			often
	@param $dir Directory to check access of
	@param $relatives Relativity array
	@result Boolean True/False
	*/
		
	function checkperms ($dir, $relatives = array (RELATIVE_CURRENT))
	{
		global $phpgw;
		global $phpgw_info;

		if ($this->file_type ($dir, array ($relatives[0])) != "Directory")
		{
			return False;
		}

		/* Create a simple 10 digit random filename */
		srand ((double) microtime () * 1000000);
		for ($i = 0; $i < 9; $i++)
		{
			$filename .= rand (0,9);
		}

		if ($this->touch ("$dir/$filename", array ($relatives[0])))
		{
			$this->rm ("$dir/$filename", array ($relatives[0]));

			return True;
		}
		else
		{
			return False;
		}
	}

	/*!
	@function ls
	@abstract get directory listing
	@discussion Note: the entries are not guaranteed to be returned in any logical order
	@param $dir Directory
	@param $relatives Relativity array
	@param $checksubdirs Boolean, recursively list all sub directories as well?
	@param $mime_type Only return entries matching MIME-type $mime_type.  Can be "Directory" or "\ " for those without MIME types
	@result array of arrays.  Subarrays contain full info for each file/dir.
	*/

	function ls ($dir = False, $relatives = array (RELATIVE_CURRENT), $checksubdirs = True, $mime_type = False)
	{
		global $phpgw, $phpgw_info;

		$p = $this->path_parts ($dir, array ($relatives[0]));
		$dir = $p->fake_full_path;

		/* If they pass us a file instead of a directory, return the info for that file only */
		if (($type = $this->file_type ($dir, array (RELATIVE_NONE))) != "Directory")
		{
			$p = $this->path_parts ($dir, array (RELATIVE_NONE));
			$rarray = array (array ("directory" => $p->fake_leading_dirs, "name" => $p->fake_name, "mime_type" => $type));

			return $rarray;
		}

		$sql = "SELECT file_id, owner_id, createdby_id, modifiedby_id, created, modified, size, mime_type, deleteable, comment, app, directory, name FROM phpgw_vfs WHERE owner_id='$this->working_id' AND directory LIKE '$dir%'";
		if ($mime_type)
		{
			$sql .= " AND mime_type='$mime_type'";
		}

		$sql .= " ORDER BY directory";

		$query = $phpgw->db->query ($sql);

		$rarray = array ();
		while ($phpgw->db->next_record ())
		{
			$record = $phpgw->db->Record;

			/* Further checking on the directory.  This makes sure /home/user/test won't match /home/user/test22 */
			if (!ereg ("^$dir(/|$)", $record["directory"]))
			{
				continue;
			}

			/* If they want only this directory, then $dir should end without a trailing / */
			if (!$checksubdirs && ereg ("^$dir/", $record["directory"]))
			{
				continue;
			}

			$rarray[] = array ("file_id" => $record["file_id"], "owner_id" => $record["owner_id"], "createdby_id" => $record["createdby_id"], "modifiedby_id" => $record["modifiedby_id"], "created" => $record["created"], "modified" => $record["modified"], "size" => $record["size"], "mime_type" => $record["mime_type"], "deletable" => $record["deletable"], "comment" => $record["comment"], "app" => $record["app"], "directory" => $record["directory"], "name" => $record["name"]);
		}

		return $rarray;
	}

	/*!
	@function dir
	@abstract shortcut to ls
	*/

	function dir ($dir = False, $relatives = array (RELATIVE_CURRENT), $checksubdirs = True, $mime_type = False)
	{
		return $this->ls ($dir, $relatives, $checksubdirs, $mime_type);
	}
}
