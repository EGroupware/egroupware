<?php

###
# Enable this to display some debugging info
###

$phpwh_debug = 0;

reset ($GLOBALS['HTTP_POST_VARS']);
while (list ($name,) = each ($GLOBALS['HTTP_POST_VARS']))
{
	$$name = $GLOBALS['HTTP_POST_VARS'][$name];
}

$to_decode = array
(
	/*
	Decode
	'var'	when	  'avar' == 'value'
	*/
	'op'	=> array ('op' => ''),
	'path'	=> array ('path' => ''),
	'file'	=> array ('file' => ''),
	'sortby'	=> array ('sortby' => ''),
	'fileman'	=> array ('fileman' => ''),
	'help_name'	=> array ('help_name' => ''),
	'renamefiles'	=> array ('renamefiles' => ''),
	'comment_files'	=> array ('comment_files' => ''),
	'show_upload_boxes'	=> array ('show_upload_boxes' => '')
);

reset ($to_decode);
while (list ($var, $conditions) = each ($to_decode))
{
	while (list ($condvar, $condvalue) = each ($conditions))
	{
		if (isset ($$condvar) && ($condvar == $var || $$condvar == $condvalue))
		{
			if (is_array ($$var))
			{
				while (list ($varkey, $varvalue) = each ($$var))
				{
					if (is_int ($varkey))
					{
						$temp[$varkey] = stripslashes (base64_decode ($varvalue));
					}
					else
					{
						$temp[stripslashes (base64_decode ($varkey))] = $varvalue;
					}
				}
				$$var = $temp;
			}
			elseif (isset ($$var))
			{
				$$var = stripslashes (base64_decode ($$var));
			}
		}
	}
}

if ($noheader || $download || $op == "view" || $op == "history" || $op == help)
{
	$noheader = True;
}

$phpgw_info["flags"] = array
(
	"currentapp" => "phpwebhosting",
	"noheader" => $noheader,
	"noappheader" => False,
	"enable_vfs_class" => True,
	"enable_browser_class" => True
);

include ("../header.inc.php");

###
# Page to process users
# Code is fairly hackish at the beginning, but it gets better
# Highly suggest turning wrapping off due to long SQL queries
###

###
# Some hacks to set and display directory paths correctly
###

if ($go)
{
	$path = $todir;
}

if (!$path)
{
	$path = $phpgw->vfs->pwd ();

	if (!$path || $phpgw->vfs->pwd (False) == "")
	{
		$path = $homedir;
	}
}

$phpgw->vfs->cd (False, False, array (RELATIVE_NONE));
$phpgw->vfs->cd ($path, False, array (RELATIVE_NONE));

$pwd = $phpgw->vfs->pwd ();

if (!$cwd = substr ($path, strlen ($homedir) + 1))
{
	$cwd = "/";
}
else
{
	$cwd = substr ($pwd, strrpos ($pwd, "/") + 1);
}

$disppath = $path;

/* This just prevents // in some cases */
if ($path == "/")
	$dispsep = "";
else
	$dispsep = "/";

if (!($lesspath = substr ($path, 0, strrpos ($path, "/"))))
	$lesspath = "/";

$now = date ("Y-m-d");

if ($phpwh_debug)
{
	echo "<b>PHPWebHosting debug:</b><br>
		path: $path<br>
		disppath: $disppath<br>
		cwd: $cwd<br>
		lesspath: $lesspath
		<p>
		<b>phpGW debug:</b><br>
		real getabsolutepath: " . $phpgw->vfs->getabsolutepath (False, False, False) . "<br>
		fake getabsolutepath: " . $phpgw->vfs->getabsolutepath (False) . "<br>
		appsession: " . $phpgw->common->appsession () . "<br>
		pwd: " . $phpgw->vfs->pwd () . "<br>";
}

###
# Get their memberships to be used throughout the script
###

$memberships = $phpgw->accounts->membership ($userinfo["username"]);

if (!is_array ($memberships))
{
	$memberships = array ();
}

while (list ($num, $group_array) = each ($memberships))
{
	$membership_id = $phpgw->accounts->name2id ($group_array["account_name"]);

	$group_applications = CreateObject('phpgwapi.applications', $membership_id);
	$membership_applications[$group_array["account_name"]] = $group_applications->read_account_specific ();
}

###
# We determine if they're in their home directory or a group's directory,
# and set the VFS working_id appropriately
###

if ((preg_match ("+^$fakebase\/(.*)(\/|$)+U", $path, $matches)) && $matches[1] != $userinfo["account_lid"])
{
	$phpgw->vfs->working_id = $phpgw->accounts->name2id ($matches[1]);
}
else
{
	$phpgw->vfs->working_id = $userinfo["username"];
}

if ($path != $homedir && $path != $fakebase && $path != "/" && !$phpgw->vfs->acl_check ($path, array (RELATIVE_NONE), PHPGW_ACL_READ))
{
	echo $phpgw->common->error_list (array ("You do not have access to $path"));
	html_break (2);
	html_link ("$appname/index.php?path=$homedir", "Go to your home directory");
	html_page_close ();
}

$userinfo["working_id"] = $phpgw->vfs->working_id;
$userinfo["working_lid"] = $phpgw->accounts->id2name ($userinfo["working_id"]);

###
# If their home directory doesn't exist, we create it
# Same for group directories
###

if (($path == $homedir) && !$phpgw->vfs->file_exists ($homedir, array (RELATIVE_NONE)))
{
	$phpgw->vfs->override_acl = 1;
	$phpgw->vfs->mkdir ($homedir, array (RELATIVE_NONE));
	$phpgw->vfs->override_acl = 0;
}
elseif (preg_match ("|^$fakebase\/(.*)$|U", $path, $matches))
{
	if (!$phpgw->vfs->file_exists ($path, array (RELATIVE_NONE)))
	{
		$phpgw->vfs->override_acl = 1;
		$phpgw->vfs->mkdir ($path, array (RELATIVE_NONE));
		$phpgw->vfs->override_acl = 0;

		$group_id = $phpgw->accounts->name2id ($matches[1]);
		$phpgw->vfs->set_attributes ($path, array (RELATIVE_NONE), array ("owner_id" => $group_id, "createdby_id" => $group_id));
	}
}

###
# Verify path is real
###

if ($path != $homedir && $path != "/" && $path != $fakebase)
{
	if (!$phpgw->vfs->file_exists ($path, array (RELATIVE_NONE)))
	{
		echo $phpgw->common->error_list (array ("Directory $path does not exist"));
		html_break (2);
		html_link ("$appname/index.php?path=$homedir", "Go to your home directory");
		html_break (2);
		html_link_back ();
		html_page_close ();
	}
}

/* Update if they request it, or one out of 20 page loads */
srand ((double) microtime() * 1000000);
if ($update || rand (0, 19) == 4)
{
	$phpgw->vfs->update_real ($path, array (RELATIVE_NONE));
}

###
# Default is to sort by name
###

if (!$sortby)
{
	$sortby = "name";
}

###
# Decide how many upload boxes to show
###

if (!$show_upload_boxes || $show_upload_boxes <= 0)
{
	$show_upload_boxes = $settings["show_upload_boxes"];
}


###
# Read in file info from database to use in the rest of the script
# $fakebase is a special directory.  In that directory, we list the user's
# home directory and the directories for the groups they're in
###

if ($path == $fakebase)
{
	if (!$phpgw->vfs->file_exists ($homedir, array (RELATIVE_NONE)))
	{
		$phpgw->vfs->mkdir ($homedir, array (RELATIVE_NONE));
	}

	$ls_array = $phpgw->vfs->ls ($homedir, array (RELATIVE_NONE), False, False, True);
	$files_array[] = $ls_array[0];
	$numoffiles++;

	reset ($memberships);
	while (list ($num, $group_array) = each ($memberships))
	{
		###
		# If the group doesn't have access to this app, we don't show it
		###

		if (!$membership_applications[$group_array["account_name"]][$appname]["enabled"])
		{
			continue;
		}

		if (!$phpgw->vfs->file_exists ("$fakebase/$group_array[account_name]", array (RELATIVE_NONE)))
		{
			$phpgw->vfs->mkdir ("$fakebase/$group_array[account_name]", array (RELATIVE_NONE));
			$phpgw->vfs->set_attributes ("$fakebase/$group_array[account_name]", array (RELATIVE_NONE), array ("owner_id" => $group_array["account_id"], "createdby_id" => $group_array["account_id"]));
		}

		$ls_array = $phpgw->vfs->ls ("$fakebase/$group_array[account_name]", array (RELATIVE_NONE), False, False, True);

		$files_array[] = $ls_array[0];

		$numoffiles++;
	}
}
else
{
	$ls_array = $phpgw->vfs->ls ($path, array (RELATIVE_NONE), False, False, False, $sortby);

	while (list ($num, $file_array) = each ($ls_array))
	{
		$numoffiles++;
		$files_array[] = $file_array;
	}
}

if (!is_array ($files_array))
{
	$files_array = array ();
}

if ($download)
{
	for ($i = 0; $i != $numoffiles; $i++)
	{
		if (!$fileman[$i])
		{
			continue;
		}

		$download_browser = CreateObject ('phpgwapi.browser');
		$download_browser->content_header ($fileman[$i]);
		echo $phpgw->vfs->read ($fileman[$i]);
		$phpgw->common->phpgw_exit ();
	}
}

if ($op == "view" && $file)
{
	$ls_array = $phpgw->vfs->ls ($file, array (RELATIVE_ALL), False, False, True);

	if ($ls_array[0]["mime_type"])
	{
		$mime_type = $ls_array[0]["mime_type"];
	}
	elseif ($settings["viewtextplain"])
	{
		$mime_type = "text/plain";
	}

	header('Content-type: ' . $mime_type);
	echo $phpgw->vfs->read ($file);
	$phpgw->common->phpgw_exit ();
}

if ($op == "history" && $file)
{
	html_table_begin ();
	html_table_row_begin ();
	html_table_col_begin ();
	html_text_bold ("Date");
	html_table_col_end ();
	html_table_col_begin ();
	html_text_bold ("Version");
	html_table_col_end ();
	html_table_col_begin ();
	html_text_bold ("Who");
	html_table_col_end ();
	html_table_col_begin ();
	html_text_bold ("Operation");
	html_table_col_end ();
	html_table_row_end ();

	$journal_array = $phpgw->vfs->get_journal ($file, array (RELATIVE_ALL));
	while (list ($num, $journal_entry) = each ($journal_array))
	{
		html_table_row_begin ();
		html_table_col_begin ();
		html_text ($journal_entry["created"] . html_nbsp (3, 1));
		html_table_col_end ();
		html_table_col_begin ();
		html_text ($journal_entry["version"] . html_nbsp (3, 1));
		html_table_col_end ();
		html_table_col_begin ();
		html_text ($phpgw->accounts->id2name ($journal_entry["owner_id"]) . html_nbsp (3, 1));
		html_table_col_end ();
		html_table_col_begin ();
		html_text ($journal_entry["comment"]);
		html_table_col_end ();
	}

	html_table_end ();
	$phpgw->common->phpgw_exit ();
}

if ($op == "help" && $help_name)
{
	while (list ($num, $help_array) = each ($help_info))
	{
		if ($help_array[0] != $help_name)
			continue;

		$help_array[1] = preg_replace ("/\[(.*)\|(.*)\]/Ue", "html_help_link ('\\1', '\\2', False, True)", $help_array[1]);
		$help_array[1] = preg_replace ("/\[(.*)\]/Ue", "html_help_link ('\\1', '\\1', False, True)", $help_array[1]);

		html_font_set ("4");
		$title = ereg_replace ("_", " ", $help_array[0]);
		$title = ucwords ($title);
		html_text ($title);
		html_font_end ();

		html_break (2);

		html_font_set ("2");
		html_text ($help_array[1]);
		html_font_end ();
	}

	$phpgw->common->phpgw_exit ();
}

###
# Start Main Page
###

if ($op != "changeinfo" && $op != "logout" && $op != "delete")
{
	html_page_begin ("Users :: $userinfo[username]");
	html_page_body_begin (HTML_PAGE_BODY_COLOR);
}

if (!is_array ($settings))
{
	$pref = CreateObject ('phpgwapi.preferences', $userinfo["username"]);
	$phpgw->common->hook_single ('add_def_pref', $appname);
	$pref->save_repository (True);
	$pref_array = $pref->read_repository ();
	$settings = $pref_array[$appname];
}

###
# Start Main Table 
###

if (!$op && !$delete && !$createdir && !$renamefiles && !$move && !$copy && !$edit && !$comment_files)
{
	html_table_begin ("100%");
	html_table_row_begin ();
	html_table_col_begin ("center", NULL, "top");
	html_align ("center");
	html_form_begin ("$appname/index.php?path=$path");
	if ($numoffiles || $cwd)
	{
		while (list ($num, $name) = each ($settings))
		{
			if ($name)
				$columns++;
		}
		$columns++;
		html_table_begin ();
		html_table_row_begin (NULL, NULL, NULL, HTML_TABLE_FILES_HEADER_BG_COLOR);
		html_table_col_begin ("center", NULL, NULL, NULL, $columns);
		html_table_begin ("100%");
		html_table_row_begin ();
		html_table_col_begin ("left");
		
		if ($path != "/")
		{
			html_link ("$appname/index.php?path=$lesspath", html_image ("images/folder-up.gif", "Up", "left", 0, NULL, 1));
			html_help_link ("up");
		}
		
		html_table_col_end ();
		html_table_col_begin ("center");
		
		if ($cwd)
		{
			if ($path == $homedir)
				html_image ("images/folder-home.gif", "Folder", "center");
			else
				html_image ("images/folder.gif", "Folder", "center");
		}
		else
			html_image ("images/folder-home.gif", "Home");
		
		html_font_set (4, HTML_TABLE_FILES_HEADER_TEXT_COLOR);
                html_text_bold (strtoupper ($disppath));
		html_font_end ();
		html_help_link ("directory_name");
		html_table_col_end ();
		html_table_col_begin ("right");
		
		if ($path != $homedir)
		{
			html_link ("$appname/index.php?path=$homedir", html_image ("images/folder-home.gif", "Home", "right", 0, NULL, 1));
			html_help_link ("home");
		}

		html_table_col_end ();
		html_table_row_end ();
		html_table_end ();
		html_table_col_end ();
		html_table_row_end ();
		html_table_row_begin (NULL, NULL, NULL, HTML_TABLE_FILES_COLUMN_HEADER_BG_COLOR);
		
		###
		# Start File Table Column Headers
		# Reads values from $file_attributes array and preferences
		###

		html_table_col_begin ();
		html_text ("Sort by:" . html_nbsp (1, 1), NULL, NULL, 1);
		html_help_link ("sort_by");
		html_table_col_end ();

		reset ($file_attributes);
		while (list ($internal, $displayed) = each ($file_attributes))
		{
			if ($settings[$internal])
			{
				html_table_col_begin ();
				html_link ("$appname/index.php?path=$path&sortby=$internal", html_text_bold ("$displayed", 1, 1));
				html_help_link (strtolower (ereg_replace (" ", "_", $displayed)));
				html_table_col_end ();
			}
		}

		html_table_col_begin ();
		html_table_col_end ();
		html_table_row_end ();

		if ($settings["dotdot"] && $settings["name"] && $path != "/")
		{
			html_table_row_begin ();
			html_table_col_begin ();
			html_table_col_end ();

			/* We can assume the next column is the name */
			html_table_col_begin ();
			html_image ("images/folder.gif", "Folder");
			html_link ("$appname/index.php?path=$lesspath", "..");
			html_table_col_end ();

			if ($settings["mime_type"])
			{
				html_table_col_begin ();
				html_text ("Directory");
				html_table_col_end ();
			}

			html_table_row_end ();
		}

		###
		# List all of the files, with their attributes
		###

		reset ($files_array);
		for ($i = 0; $i != $numoffiles; $i++)
		{
			$files = $files_array[$i];

			if ($rename || $edit_comments)
			{
				unset ($this_selected);
				unset ($renamethis);
				unset ($edit_this_comment);

        	                for ($j = 0; $j != $numoffiles; $j++)
				{
                	                if ($fileman[$j] == $files["name"])
					{
                        	                $this_selected = 1;
						break;
					}
        			}

				if ($rename && $this_selected)
					$renamethis = 1;
				elseif ($edit_comments && $this_selected)
					$edit_this_comment = 1;
			}

			if (!$settings["dotfiles"] && ereg ("^\.", $files["name"]))
			{
				continue;
			}

			html_table_row_begin (NULL, NULL, NULL, HTML_TABLE_FILES_BG_COLOR);

			###
			# Checkboxes
			###

			html_table_col_begin ("right");

			if (!$rename && !$edit_comments && $path != $fakebase && $path != "/")
			{
				html_form_input ("checkbox", "fileman[$i]", base64_encode ("$files[name]"));
			}
			elseif ($renamethis)
			{
				html_form_input ("hidden", "fileman[" . base64_encode ($files[name]) . "]", "$files[name]", NULL, NULL, "checked");
			}
			else
			{
				html_nbsp;
			}

			html_table_col_end ();

			###
			# File name and icon
			###

			if ($settings["name"])
			{
				html_table_col_begin ();

				if ($renamethis)
				{
					if ($files["mime_type"] == "Directory")
						html_image ("images/folder.gif", "Folder");
					html_form_input ("text", "renamefiles[" . base64_encode ($files[name]) . "]", $files["name"], 255);
				}
				else
				{
					if ($files["mime_type"] == "Directory")
					{
						html_image ("images/folder.gif", "Folder");		
						html_link ("$appname/index.php?path=$path$dispsep$files[name]", $files["name"]);
	                                }
					else
					{
						if ($settings["viewonserver"] && isset ($filesdir) && !$files["link_directory"])
						{
							$clickview = "$filesdir$pwd/$files[name]";
						}
						else
						{
							$clickview = "$appname/index.php?op=view&file=$files[name]&path=$path";
						}

	        	                        if ($settings["viewinnewwin"])
						{
							$target = "_new";
						}

						html_link ($clickview, $files["name"], 0, 1, 0, $target);
					}
	                        }

				html_table_col_end ();
			}

			###
			# MIME type
			###

			if ($settings["mime_type"])
			{
				html_table_col_begin ();
				html_text ($files["mime_type"]);
				html_table_col_end ();
			}

			###
			# File size
			###

			if ($settings["size"])
			{
				html_table_col_begin ();

				$size = $phpgw->vfs->get_size ($files["directory"] . "/" . $files["name"], array (RELATIVE_NONE));
				borkb ($size);

				html_table_col_end ();
			}

			###
			# Date created
			###
			if ($settings["created"])
			{
				html_table_col_begin ();
				html_text ($files["created"]);
				html_table_col_end ();
			}

			###
			# Date modified
			###

			if ($settings["modified"])
			{
				html_table_col_begin ();
				if ($files["modified"] != "0000-00-00")
					html_text ($files["modified"]);
				html_table_col_end ();
			}

			###
			# Owner name
			###

			if ($settings["owner"])
			{
				html_table_col_begin ();
				html_text ($phpgw->accounts->id2name ($files["owner_id"]));
				html_table_col_end ();
			}

			###
			# Creator name
			###

			if ($settings["createdby_id"])
			{
				html_table_col_begin ();
				html_text ($phpgw->accounts->id2name ($files["createdby_id"]));
				html_table_col_end ();
			}

			###
			# Modified by name
			###

			if ($settings["modifiedby_id"])
			{
				html_table_col_begin ();
				html_text ($phpgw->accounts->id2name ($files["modifiedby_id"]));
				html_table_col_end ();
			}

			###
			# Application
			###

			if ($settings["app"])
			{
				html_table_col_begin ();
				html_text ($files["app"]);
				html_table_col_end ();
			}

			###
			# Comment
			###

			if ($settings["comment"])
			{
				html_table_col_begin ();
				if ($edit_this_comment)
				{
					html_form_input ("text", "comment_files[" . base64_encode ($files[name]) . "]", html_encode ($files["comment"], 1), 255);
				}
				else
				{
					html_text ($files["comment"]);
				}
				html_table_col_end ();
			}

			###
			# Version
			###

			if ($settings["version"])
			{
				html_table_col_begin ();
				html_link ("$appname/index.php?op=history&file=$files[name]&path=$path", $files["version"], NULL, True, NULL, "_new");
				html_table_col_end ();
			}

			###
			# Deleteable (currently not used)
			###

			if ($settings["deleteable"])
			{
				if ($files["deleteable"] == "N")
				{
					html_table_col_begin ();
					html_image ("images/locked.gif", "Locked");
					html_table_col_end ();
				}
				else
				{
					html_table_col_begin ();
					html_table_col_end ();
				}
			}

			html_table_row_end ();

			if ($files["mime_type"] == "Directory")
			{
				$usedspace += $fileinfo[0];
			}
			else
			{
				$usedspace += $files["size"];
			}
		}

		html_table_end ();
		html_break (2);

		if ($path != "/" && $path != $fakebase)
		{
			if (!$rename && !$edit_comments)
			{
				html_form_input ("submit", "edit", "Edit");
				html_help_link ("edit");
				html_nbsp (3);
			}

			if (!$edit_comments)
			{
				html_form_input ("submit", "rename", "Rename");
				html_help_link ("rename");
				html_nbsp (3);
			}

			if (!$rename && !$edit_comments)
			{
				html_form_input ("submit", "delete", "Delete");
				html_help_link ("delete");
				html_nbsp (3);
			}

			if (!$rename)
			{
				html_form_input ("submit", "edit_comments", "Edit comments");
				html_help_link ("edit_comments");
			}
		}
	}

	###
	# Display some inputs and info, but not when renaming or editing comments
	###

	if (!$rename && !$edit_comments)
	{
		###
		# Begin Copy to/Move to selection
		###
		
		html_break (1);
		html_form_input ("submit", "go", "Go to:");
		html_help_link ("go_to");

		if ($path != "/" && $path != $fakebase)
		{
			html_form_input ("submit", "copy", "Copy to:");
			html_help_link ("copy_to");

			html_form_input ("submit", "move", "Move to:");
			html_help_link ("move_to");
		}

		html_form_select_begin ("todir");

		html_break (1);

		###
		# First we get the directories in their home directory
		###

		$dirs[] = array ("directory" => $fakebase, "name" => $userinfo["account_lid"]);

		$ls_array = $phpgw->vfs->ls ($homedir, array (RELATIVE_NONE), True, "Directory");
		while (list ($num, $dir) = each ($ls_array))
		{
			$dirs[] = $dir;
		}

		###
		# Then we get the directories in their membership's home directories
		###

		reset ($memberships);
		while (list ($num, $group_array) = each ($memberships))
		{
			###
			# Don't list directories for groups that don't have access
			###

			if (!$membership_applications[$group_array["account_name"]][$appname]["enabled"])
			{
				continue;
			}

			$dirs[] = array ("directory" => $fakebase, "name" => $group_array["account_name"]);

			$ls_array = $phpgw->vfs->ls ("$fakebase/$group_array[account_name]", array (RELATIVE_NONE), True, "Directory");
			while (list ($num, $dir) = each ($ls_array))
			{
				$dirs[] = $dir;
			}
		}

		reset ($dirs);
		while (list ($num, $dir) = each ($dirs))
		{
			if (!$dir["directory"])
			{
				continue;
			}
			
			###
			# So we don't display //
			###

			if ($dir["directory"] != '/')
			{
				$dir["directory"] .= '/';
			}

			###
			# No point in displaying the current directory, or a directory that doesn't exist
			###
			
			if ((($dir["directory"] . $dir["name"]) != $path) && $phpgw->vfs->file_exists ($dir["directory"] . $dir["name"], array (RELATIVE_NONE)))
			{
				html_form_option ($dir["directory"] . $dir["name"], $dir["directory"] . $dir["name"]);
			}
		}

		html_form_select_end ();
		html_help_link ("directory_list");

		if ($path != "/" && $path != $fakebase)
		{
			html_break (1);

			html_form_input ("submit", "download", "Download");
			html_help_link ("download");
			html_nbsp (3);

			html_form_input ("text", "createdir", NULL, 255, 15);
			html_form_input ("submit", "newdir", "Create Folder");
			html_help_link ("create_folder");
		}

		html_break (1);
		html_form_input ("submit", "update", "Update");
		html_help_link ("update");

		html_form_end ();

		html_help_link ("file_stats");
		html_break (1);
		html_text_bold ("Files: ");
		html_text ($numoffiles);
		html_nbsp (3);

		html_text_bold ("Used space: ");
		html_text (borkb ($usedspace, NULL, 1));
		html_nbsp (3);
		
		if ($path == $homedir || $path == $fakebase)
		{
			html_text_bold ("Unused space: ");
			html_text (borkb ($userinfo["hdspace"] - $usedspace, NULL, 1));

			$ls_array = $phpgw->vfs->ls ($path, array (RELATIVE_NONE));
			$i = count ($ls_array);

			html_break (2);
			html_text_bold ("Total Files: ");
			html_text ($i);
		}
		
		###
		# Show file upload boxes. Note the last argument to html ().  Repeats $show_upload_boxes times
		###

		if ($path != "/" && $path != $fakebase)
		{
			html_break (2);
			html_form_begin ("$appname/index.php?op=upload&path=$path", "post", "multipart/form-data");
			html_table_begin ();
			html_table_row_begin ("center");
			html_table_col_begin ();
			html_text_bold ("File");
			html_help_link ("upload_file");
			html_table_col_end ();
			html_table_col_begin ();
			html_text_bold ("Comment");
			html_help_link ("upload_comment");
			html_table_col_end ();
			html_table_row_end ();

			html_table_row_begin ();
			html_table_col_begin ();
			html_form_input ("hidden", "show_upload_boxes", base64_encode ($show_upload_boxes));
			html (html_form_input ("file", "upload_file[]", NULL, 255, NULL, NULL, NULL, 1) . html_break (1, NULL, 1), $show_upload_boxes);
			html_table_col_end ();
			html_table_col_begin ();
			html (html_form_input ("text", "upload_comment[]", NULL, NULL, NULL, NULL, NULL, 1) . html_break (1, NULL, 1), $show_upload_boxes);
			html_table_col_end ();
			html_table_row_end ();
			html_table_end ();
			html_form_input ("submit", "upload_files", "Upload files");
			html_help_link ("upload_files");
			html_break (2);
			html_text ("Show" . html_nbsp (1, True));
			html_link ("$appname/index.php?show_upload_boxes=5", "5");
			html_nbsp ();
			html_link ("$appname/index.php?show_upload_boxes=10", "10");
			html_nbsp ();
			html_link ("$appname/index.php?show_upload_boxes=20", "20");
			html_nbsp ();
			html_link ("$appname/index.php?show_upload_boxes=50", "50");
			html_nbsp ();
			html_text ("upload fields");
			html_nbsp ();
			html_help_link ("show_upload_fields");
			html_form_end ();
		}
	}

	html_table_col_end ();
	html_table_row_end ();
	html_table_end ();
	html_page_close ();
}

###
# Handle Editing files
###

if ($edit)
{
	###
	# If $edit is "Edit", we do nothing, and let the for loop take over
	###

	if ($edit_file)
	{
		$edit_file_content = stripslashes ($edit_file_content);
	}

	if ($edit_preview)
	{
		$content = $edit_file_content;

		html_break (1);
		html_text_bold ("Preview of $path/$edit_file");
		html_break (2);

		html_table_begin ("90%");
		html_table_row_begin ();
		html_table_col_begin ();
		html_text (nl2br ($content));
		html_table_col_end ();
		html_table_row_end ();
		html_table_end ();
	}
	elseif ($edit_save)
	{
		$content = $edit_file_content;

		if ($phpgw->vfs->write ($edit_file, array (RELATIVE_ALL), $content))
		{
			html_text_bold ("Saved $path/$edit_file");
			html_break (2);
			html_link_back ();
		}
		else
		{
			html_text_error ("Could not save $path/$edit_file");
			html_break (2);
			html_link_back ();
		}
	}

/* This doesn't work just yet
	elseif ($edit_save_all)
	{
		for ($j = 0; $j != $numoffiles; $j++)
		{
			$fileman[$j];

			$content = $$fileman[$j];
			echo "fileman[$j]: $fileman[$j]<br><b>$content</b><br>";
			continue;

			if ($phpgw->vfs->write ($fileman[$j], array (RELATIVE_ALL), $content))
			{
				html_text_bold ("Saved $path/$fileman[$j]");
				html_break (1);
			}
			else
			{
				html_text_error ("Could not save $path/$fileman[$j]");
				html_break (1);
			}
		}

		html_break (1);
	}
*/

	###
	# Now we display the edit boxes and forms
	###

	for ($j = 0; $j != $numoffiles; $j++)
	{
		###
		# If we're in preview or save mode, we only show the file
		# being previewed or saved
		###

		if ($edit_file && ($fileman[$j] != $edit_file))
		{
			continue;
		}

		if ($fileman[$j] && $phpgw->vfs->file_exists ($fileman[$j], array (RELATIVE_ALL)))
		{
			if ($edit_file)
			{
				$content = stripslashes ($edit_file_content);
			}
			else
			{
				$content = $phpgw->vfs->read ($fileman[$j]);
			}

			html_table_begin ("100%");
			html_form_begin ("$appname/index.php?path=$path");
			html_form_input ("hidden", "edit", True);
			html_form_input ("hidden", "edit_file", $fileman[$j]);

			###
			# We need to include all of the fileman entries for each file's form,
			# so we loop through again
			###

			for ($i = 0; $i != $numoffiles; $i++)
			{
				html_form_input ("hidden", "fileman[$i]", base64_encode ($fileman[$i]));
			}

			html_table_row_begin ();
			html_table_col_begin ();
			html_form_textarea ("edit_file_content", 35, 75, $content);
			html_table_col_end ();
			html_table_col_begin ("center");
			html_form_input ("submit", "edit_preview", "Preview " . html_encode ($fileman[$j], 1));
			html_break (1);
			html_form_input ("submit", "edit_save", "Save " . html_encode ($fileman[$j], 1));
//			html_break (1);
//			html_form_input ("submit", "edit_save_all", "Save all");
			html_table_col_end ();
			html_table_row_end ();
			html_break (2);
			html_form_end ();
			html_table_end ();
		}
	}
}

###
# Handle File Uploads
###

elseif ($op == "upload" && $path != "/" && $path != $fakebase)
{
	for ($i = 0; $i != $show_upload_boxes; $i++)
	{
		if ($badchar = bad_chars ($upload_file_name[$i], True, True))
		{
			echo $phpgw->common->error_list (array (html_encode ("Filenames cannot contain \"$badchar\"", 1)));

			continue;
		}

		###
		# Check to see if the file exists in the database, and get its info at the same time
		###

		$ls_array = $phpgw->vfs->ls ($path . "/" . $upload_file_name[$i], array (RELATIVE_NONE), False, False, True);
		$fileinfo = $ls_array[0];

		if ($fileinfo["name"])
		{
			if ($fileinfo["mime_type"] == "Directory")
			{
				echo $phpgw->common->error_list (array ("Cannot replace $fileinfo[name] because it is a directory"));
				continue;
			}
		}

		if ($upload_file_size[$i] > 0)
		{
			if ($fileinfo["name"] && $fileinfo["deleteable"] != "N")
			{
				$phpgw->vfs->set_attributes ($upload_file_name[$i], array (RELATIVE_ALL), array ("owner_id" => $userinfo["username"], "modifiedby_id" => $userinfo["username"], "modified" => $now, "size" => $upload_file_size[$i], mime_type => $upload_file_type[$i], "deleteable" => "Y", "comment" => stripslashes ($upload_comment[$i])));
				$phpgw->vfs->cp ($upload_file[$i], "$upload_file_name[$i]", array (RELATIVE_NONE|VFS_REAL, RELATIVE_ALL));

				html_text_summary ("Replaced $disppath/$upload_file_name[$i]", $upload_file_size[$i]);
			}
			else
			{
				$phpgw->vfs->cp ($upload_file[$i], $upload_file_name[$i], array (RELATIVE_NONE|VFS_REAL, RELATIVE_ALL));
				$phpgw->vfs->set_attributes ($upload_file_name[$i], array (RELATIVE_ALL), array ("mime_type" => $upload_file_type[$i], "comment" => stripslashes ($upload_comment[$i])));

				html_text_summary ("Created $disppath/$upload_file_name[$i]", $upload_file_size[$i]);
			}
		}
		elseif ($upload_file_name[$i])
		{
			$phpgw->vfs->touch ($upload_file_name[$i], array (RELATIVE_ALL));
			$phpgw->vfs->set_attributes ($upload_file_name[$i], array (RELATIVE_ALL), array ("mime_type" => $upload_file_type[$i], "comment" => $upload_comment[$i]));

			html_text_summary ("Created $disppath/$upload_file_name[$i]", $file_size[$i]);
		}
	}

	html_break (2);
	html_link_back ();
}

###
# Handle Editing comments
###

elseif ($comment_files)
{
	while (list ($file) = each ($comment_files))
	{
		if ($badchar = bad_chars ($comment_files[$file], False, True))
		{
			echo $phpgw->common->error_list (array (html_text_italic ($file, 1) . html_encode (": Comments cannot contain \"$badchar\"", 1)));
			continue;
		}

		$phpgw->vfs->set_attributes ($file, array (RELATIVE_ALL), array ("comment" => stripslashes ($comment_files[$file])));

		html_text_summary ("Updated comment for $path/$file");
	}

	html_break (2);
	html_link_back ();
}

###
# Handle Renaming Files and Directories
###

elseif ($renamefiles)
{
	while (list ($from, $to) = each ($renamefiles))
	{
		if ($badchar = bad_chars ($to, True, True))
		{
			echo $phpgw->common->error_list (array (html_encode ("File names cannot contain \"$badchar\"", 1)));
			continue;
		}

		if (ereg ("/", $to) || ereg ("\\\\", $to))
		{
			echo $phpgw->common->error_list (array ("File names cannot contain \\ or /"));
		}
		elseif (!$phpgw->vfs->mv ($from, $to))
		{
			echo $phpgw->common->error_list (array ("Could not rename $disppath/$from to $disppath/$to"));
		}
		else 
		{
			html_text_summary ("Renamed $disppath/$from to $disppath/$to");
		}
	}

        html_break (2);
        html_link_back ();
}

###
# Handle Moving Files and Directories
###

elseif ($move)
{
	while (list ($num, $file) = each ($fileman))
	{
		if ($phpgw->vfs->mv ($file, $todir . "/" . $file, array (RELATIVE_ALL, RELATIVE_NONE)))
		{
			$moved++;
			html_text_summary ("Moved $disppath/$file to $todir/$file");
		}
		else
		{
			echo $phpgw->common->error_list (array ("Could not move $disppath/$file to $todir/$file"));
		}
	}

	if ($moved)
	{
		html_break (2);
		html_link ("$appname/index.php?path=$todir", "Go to $todir");
	}

	html_break (2);
	html_link_back ();
}

###
# Handle Copying of Files and Directories
###

elseif ($copy)
{
	while (list ($num, $file) = each ($fileman))
	{
		if ($phpgw->vfs->cp ($file, $todir . "/" . $file, array (RELATIVE_ALL, RELATIVE_NONE)))
		{
			$copied++;
			html_text_summary ("Copied $disppath/$file to $todir/$file");
		}
		else
		{
			echo $phpgw->common->error_list (array ("Could not copy $disppath/$file to $todir/$file"));
		}
	}

	if ($copied)
	{
		html_break (2);
		html_link ("$appname/index.php?path=$todir", "Go to $todir");
	}

	html_break (2);
	html_link_back ();
}

###
# Handle Deleting Files and Directories
###

elseif ($delete)
{
	for ($i = 0; $i != $numoffiles; $i++)
	{
		if ($fileman[$i])
		{
			if ($phpgw->vfs->delete ($fileman[$i]))
			{
				html_text_summary ("Deleted $disppath/$fileman[$i]", $fileinfo["size"]);
			}
			else
			{
				$phpgw->common->error_list (array ("Could not delete $disppath/$fileman[$i]"));
			}
		}
	}

	html_break (2);
	html_link_back ();
}

elseif ($newdir && $createdir)
{
	if ($badchar = bad_chars ($createdir, True, True))
	{
		echo $phpgw->common->error_list (array (html_encode ("Directory names cannot contain \"$badchar\"", 1)));
		html_break (2);
		html_link_back ();
		html_page_close ();
	}
	
	if ($createdir[strlen($createdir)-1] == " " || $createdir[0] == " ")
	{
		echo $phpgw->common->error_list (array ("Cannot create directory because it begins or ends in a space"));
		html_break (2);
		html_link_back ();
		html_page_close ();
	}

	$ls_array = $phpgw->vfs->ls ($path . "/" . $createdir, array (RELATIVE_NONE), False, False, True);
	$fileinfo = $ls_array[0];

	if ($fileinfo["name"])
	{
		if ($fileinfo["mime_type"] != "Directory")
		{
			echo $phpgw->common->error_list (array ("$fileinfo[name] already exists as a file"));
			html_break (2);
			html_link_back ();
			html_page_close ();
		}
		else
		{
			echo $phpgw->common->error_list (array ("Directory $fileinfo[name] already exists"));
			html_break (2);
			html_link_back ();
			html_page_close ();
		}
	}
	else
	{
		if ($phpgw->vfs->mkdir ($createdir))
		{
			html_text_summary ("Created directory $disppath/$createdir");
			html_break (2);
			html_link ("$appname/index.php?path=$disppath/$createdir", "Go to $disppath/$createdir");
		}
		else
		{
			echo $phpgw->common->error_list (array ("Could not create $disppath/$createdir"));
		}
	}

	html_break (2);
	html_link_back ();
}

html_page_close ();

?>
