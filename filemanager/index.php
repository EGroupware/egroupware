<?

$phpgw_info["flags"] = array("currentapp" => "phpwebhosting",
				"noheader" => False,
				"noappheader" => False,
				"enable_vfs_class" => True);
include("../header.inc.php");

error_reporting (4);

###
# Page to process users
# Code is fairly hackish at the beginning, but it gets better
# Highly suggest turning wrapping off due to long SQL queries
###

###
# Note that $userinfo["username"] is actually the id number, not the login name
###

$userinfo["username"] = $phpgw_info["user"]["account_id"];
$userinfo["account_lid"] = $phpgw->accounts->id2name ($userinfo["username"]);
$userinfo["hdspace"] = 10000000000;
$homedir = "$fakebase/$userinfo[account_lid]";

###
# Enable this to display some debugging info
###

$phpwh_debug = 0;

if ($download && $fileman[0])
{
	$phpgw->browser->content_header ($fn);
	echo $phpgw->vfs->read ($path/$fileman[0]);
	$phpgw->common->phpgw_exit ();
}

###
# Default is to sort by name
###

if (!$sortby)
	$sortby = "name";

###
# Some hacks to set and display directory paths correctly
###

if (!$path)
{
	$path = $phpgw->vfs->pwd ();
	if (!$path || $phpgw->vfs->pwd (False) == "")
		$path = $homedir;
}

$extra_dir = substr ($path, strlen ($homedir) + 1);
$phpgw->vfs->cd (False, False, array (RELATIVE_NONE));
$phpgw->vfs->cd ($path, False, array (RELATIVE_NONE));

$pwd = $phpgw->vfs->pwd ();

if (!$cwd = substr ($path, strlen ($homedir) + 1))
	$cwd = "/";
else
	$cwd = substr ($pwd, strrpos ($pwd, "/") + 1);

$disppath = $path;

/* This just prevents // in some cases */
if ($path == "/")
	$dispsep = "";
else
	$dispsep = "/";

if (!($lesspath = substr ($path, 0, strrpos ($path, "/"))))
	$lesspath = "/";

$now = date ("Y-m-d");

//This will hopefully be replaced by a session management working_id
//if (!$phpgw->vfs->working_id = preg_replace ("/\$fakebase\/(.*)\/(.*)$/U", "\\1", $path))

$userinfo["working_id"] = $phpgw->vfs->working_id;
$userinfo["working_lid"] = $phpgw->accounts->id2name ($userinfo["working_id"]);

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
# Verify path is real
###

if ($path != $homedir && $path != "/" && $path != $fakebase)
{
	if ($phpwh_debug)
	{
		echo "SELECT name FROM phpgw_vfs WHERE owner_id = '$userinfo[username]' AND name = '$cwd' AND mime_type = 'Directory' AND directory = '$lesspath'<br>";
	}

	$query = db_query ("SELECT name FROM phpgw_vfs WHERE owner_id = '$userinfo[username]' AND name = '$cwd' AND mime_type = 'Directory' AND directory = '$lesspath'");
	if (!$phpgw->db->next_record ($query))
	{
		html_text_error ("Directory $dir does not exist", 1);
		html_break (2);
		html_link ("$appname/index.php?path=$homedir", "Go to your home directory");
		html_break (2);
		html_link_back ();
		html_page_close ();
	}
}

###
# Read in file info from database to use in the rest of the script
# $files in the loop below uses $query
###

$files_query = db_query ("SELECT * FROM phpgw_vfs WHERE owner_id = '$userinfo[username]' AND directory = '$path' AND name != '' ORDER BY $sortby");
$numoffiles = db_call ("affected_rows", $files_query);

###
# Start Main Page
###

if ($op != "changeinfo" && $op != "logout" && $op != "delete")
{
	html_page_begin ("Users :: $userinfo[username]");
	html_page_body_begin (HTML_PAGE_BODY_COLOR);
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
			html_link ("$appname/index.php?path=$lesspath", html_image ("images/folder-up.gif", "Up", "left", 0, NULL, 1));
		
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
		html_table_col_end ();
		html_table_col_begin ("right");
		
		if ($path != $homedir)
			html_link ("$appname/index.php?path=$homedir", html_image ("images/folder-home.gif", "Home", "right", 0, NULL, 1));

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
		html_table_col_end ();

		reset ($file_attributes);
		while (list ($internal, $displayed) = each ($file_attributes))
		{
			if ($settings[$internal])
			{
				html_table_col_begin ();
				html_link ("$appname/index.php?path=$path&sortby=$internal", html_text_bold ("$displayed", 1, 1));
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

		for ($i = 0; $i != $numoffiles; $i++)
		{
			$files = db_fetch_array ($files_query);

			if ($rename || $edit_comments)
			{
				unset ($this_selected);
				unset ($renamethis);
				unset ($edit_this_comment);

        	                for ($j = 0; $j != $numoffiles; $j++)
				{
                	                if ($fileman[$j] == string_encode ($files["name"], 1))
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

			if (!$rename && !$edit_comments)
				html_form_input ("checkbox", "fileman[$i]", "$files[name]");
			elseif ($renamethis)
				html_form_input ("hidden", "fileman[" . string_encode ($files[name], 1) . "]", "$files[name]", NULL, NULL, "checked");
			else
				html_nbsp;

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
					html_form_input ("text", "renamefiles[" . string_encode ($files[name], 1) . "]", "$files[name]", 255);
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
						html_link ("$filesdir$pwd/$files[name]", $files["name"]);
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

				if ($files["mime_type"] == "Directory")
				{
					$size_query = db_query ("SELECT SUM(size) FROM phpgw_vfs WHERE owner_id = '$userinfo[username]' AND directory RLIKE '^$disppath/$files[name]'");
					$fileinfo = db_fetch_array ($size_query);
					db_call ("free", $size_query);
					if ($fileinfo[0])
						borkb ($fileinfo[0]+1024);
					else
						echo "1KB";
				}
				else
					borkb ($files["size"]);

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
					html_form_input ("text", "comment_files[" . string_encode ($files[name], 1) . "]", "$files[comment]", 255);
				}
				else
				{
					html_text ($files["comment"]);
				}
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

		if (!$rename && !$edit_comments)
		{
			html_form_input ("submit", "edit", "Edit");
			html_nbsp (3);
		}

		if (!$edit_comments)
		{
			html_form_input ("submit", "rename", "Rename");
			html_nbsp (3);
		}

		if (!$rename && !$edit_comments)
		{
			html_form_input ("submit", "delete", "Delete");
			html_nbsp (3);
		}

		if (!$rename)
		{
			html_form_input ("submit", "edit_comments", "Edit comments");
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
		html_form_input ("submit", "copy", "Copy to:");

		html_form_input ("submit", "move", "Move to:");
		html_form_select_begin ("todir");

		$query3 = db_query ("SELECT name, directory FROM phpgw_vfs WHERE owner_id = '$userinfo[username]' AND mime_type = 'Directory' ORDER BY name");
		while ($dirs = db_fetch_array ($query3))
		{
			###
			# So we don't display //
			###

			if ($dirs["directory"] != '/')
			{
				$dirs["directory"] .= '/';
			}

			###
			# No point in displaying the current directory
			###
			
			if (($dirs["directory"] . $dirs["name"]) != $path)
			{
				html_form_option ($dirs["directory"] . $dirs["name"]);
			}
		}

		html_form_select_end ();

		html_break (1);
		html_form_input ("submit", "download", "Download");
		html_nbsp (3);

		html_form_input ("text", "createdir", NULL, 255, 15);
		html_form_input ("submit", "newdir", "Create Folder");

		html_form_end ();

		html_break (1);
		html_text_bold ("Files: ");
		html_text ($numoffiles);
		html_nbsp (3);

		html_text_bold ("Used space: ");
		html_text (borkb ($usedspace, NULL, 1));
		html_nbsp (3);
		
		if ($path == $homedir)
		{
			html_text_bold ("Unused space: ");
			html_text (borkb ($userinfo["hdspace"] - $usedspace, NULL, 1));

			$query4 = db_query ("SELECT name FROM phpgw_vfs WHERE owner_id = '$userinfo[username]'");
			$i = db_call ("affected_rows", $query4);

			html_break (2);
			html_text_bold ("Total Files: ");
			html_text ($i);
		}
		
		###
		# Show file upload boxes. Note the last argument to html ().  Repeats 5 times
		###

		html_break (2);
		html_form_begin ("$appname/index.php?op=upload&path=$path", "post", "multipart/form-data");
		html_table_begin ();
		html_table_row_begin ("center");
		html_table_col_begin ();
		html_text_bold ("File");
		html_table_col_end ();
		html_table_col_begin ();
		html_text_bold ("Comment");
		html_table_col_end ();
		html_table_row_end ();

		html_table_row_begin ();
		html_table_col_begin ();
		html (html_form_input ("file", "file[]", NULL, 255, NULL, NULL, NULL, 1) . html_break (1, NULL, 1), 5);
		html_table_col_end ();
		html_table_col_begin ();
		html (html_form_input ("text", "comment[]", NULL, NULL, NULL, NULL, NULL, 1) . html_break (1, NULL, 1), 5);
		html_table_col_end ();
		html_table_row_end ();
		html_table_end ();
		html_form_input ("submit", "upload_files", "Upload files");
		html_form_end ();
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

	if ($edit_preview)
	{
		$content = $$edit_file;

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
		$content = $$edit_file;

		if ($phpgw->vfs->write ($edit_file, $content))
		{
			html_text_bold ("Saved $path/$edit_file");
			html_break (2);
		}
		else
		{
			html_text_error ("Could not save $path/$edit_file");
			html_break (2);
		}
	}

/* This doesn't work just yet
	elseif ($edit_save_all)
	{
		for ($j = 0; $j != $numoffiles; $j++)
		{
			$content = $$fileman[$j];
			echo "fileman[$j]: $fileman[$j]<br><b>$content</b><br>";
			continue;

			if ($phpgw->vfs->write ($fileman[$j], $content))
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
		if ($content = $phpgw->vfs->read ($fileman[$j]))
		{
			html_table_begin ("100%");
			html_form_begin ("$appname/index.php?path=$path");
			html_form_input ("hidden", "edit", True);
			html_form_input ("hidden", "edit_file", "$fileman[$j]");

			###
			# We need to include all of the fileman entries for each file's form,
			# so we loop through again
			###

			for ($i = 0; $i != $numoffiles; $i++)
			{
				html_form_input ("hidden", "fileman[$i]", "$fileman[$i]");
			}

			html_table_row_begin ();
			html_table_col_begin ();
			html_form_textarea ($fileman[$j], 35, 75, $content);
			html_table_col_end ();
			html_table_col_begin ("center");
			html_form_input ("submit", "edit_preview", "Preview $fileman[$j]");
			html_break (1);
			html_form_input ("submit", "edit_save", "Save $fileman[$j]");
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

elseif ($op == "upload")
{
	for ($i = 0; $i != 5; $i++)
	{
		if ($file_size[$i] != 0)
		{
			###
			# Check to see if the file exists in the database
			###

			$query = db_query ("SELECT * FROM phpgw_vfs WHERE name = '$file_name[$i]' AND owner_id = '$userinfo[username]' AND directory = '$path'");

			if ($fileinfo = db_fetch_array ($query))
			{
				if ($fileinfo["mime_type"] == "Directory")
				{
					html_text_summary_error ("Cannot replace $fileinfo[name] because it is a directory");
					continue;
				}

				$query = db_query ("SELECT SUM(size) FROM phpgw_vfs WHERE owner_id = '$userinfo[username]' AND name != '$file_name[$i]'");
        			$files = db_fetch_array ($query);
        			$usedspace = $files[0];

				if (($file_size[$i] + $usedspace) > $userinfo["hdspace"])
				{
					html_text_summary_error ("Sorry, you do not have enough space to upload those files");
					continue;
				}

				if ($fileinfo["deleteable"] != "N")
				{
					$phpgw->vfs->set_attributes ($file_name[$i], array ("owner_id" => $userinfo["username"], "modifiedby_id" => $userinfo["username"], "modified" => $now, "size" => $file_size[$i], mime_type => $file_type[$i], "deleteable" => "Y", "comment" => $comment[$i]), array (RELATIVE_ALL));
					$phpgw->vfs->cp ($file[$i], "$file_name[$i]", array (RELATIVE_NONE|VFS_REAL, RELATIVE_ALL));

					html_text_summary ("Replaced $disppath/$file_name[$i]", $file_size[$i]);
				}
			}
			else
			{
				$query = db_query ("SELECT SUM(size) FROM phpgw_vfs WHERE owner_id = '$userinfo[username]'");
                                $files = db_fetch_array ($query);
                                $usedspace = $files[0];

				if (($file_size[$i] + $usedspace) > $userinfo["hdspace"])
				{
					html_text_summary_error ("Not enough space to upload $file_name[$i]", NULL, $file_size[$i]);
					continue;
                                }

				$phpgw->vfs->cp ($file[$i], $file_name[$i], array (RELATIVE_NONE|VFS_REAL, RELATIVE_ALL));
				$phpgw->vfs->set_attributes ($file_name[$i], array ("mime_type" => $file_type[$i], "comment" => $comment[$i]), array (RELATIVE_ALL));

				html_text_summary ("Created $disppath/$file_name[$i]", $file_size[$i]);
			}
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
		$phpgw->vfs->set_attributes ($file, array ("comment" => $comment_files[$file]));

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
	while (list ($file) = each ($renamefiles))
	{
		$file_decoded = string_decode ($file, 1);

		if (ereg ("/", $renamefiles[$file]))
		{
			echo $phpgw->common->error_list (array ("File names cannot contain /"));
		}
		elseif (!$phpgw->vfs->mv ($file_decoded, $renamefiles[$file]))
		{
			echo $phpgw->common->error_list (array ("Could not rename $disppath/$file_decoded to $disppath/$renamefiles[$file]"));
		}
		else
		{
			html_text_summary ("Renamed $disppath/$file_decoded to $disppath/$renamefiles[$file]");
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
		$file_decoded = string_decode ($file, 1);
		if ($phpgw->vfs->mv ($file_decoded, $todir . "/" . $file_decoded, array (RELATIVE_ALL, RELATIVE_NONE)))
		{
			$moved++;
			html_text_summary ("Moved $disppath/$file_decoded to $todir/$file_decoded");
		}
		else
		{
			echo $phpgw->common->error_list (array ("Could not move $disppath/$file_decoded to $todir/$file_decoded"));
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
		$file_decoded = string_decode ($file, 1);

		if ($phpgw->vfs->cp ($file_decoded, $todir . "/" . $file_decoded, array (RELATIVE_ALL, RELATIVE_NONE)))
		{
			$copied++;
			html_text_summary ("Copied $disppath/$file_decoded to $todir/$file_decoded");
		}
		else
		{
			echo $phpgw->common->error_list (array ("Could not copy $disppath/$file_decoded to $todir/$file_decoded"));
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
	$query = db_query ("SELECT name FROM phpgw_vfs WHERE owner_id = '$userinfo[username]'");
	$numoffiles = db_call ("affected_rows", $query);
	for ($i = 0; $i != $numoffiles; $i++)
	{
		if ($fileman[$i])
		{
			###
			# There is no need to create a separate $fileman_decode variable, because it will never be passed again
			###

			$fileman[$i] = string_decode ($fileman[$i], 1);

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
	if ($badchar = bad_chars_file ($createdir, 1))
	{
		html_text_summary_error ("Cannot create directory $createdir&nbsp;&nbsp;&nbsp;(name contains \"$badchar\")");
		html_break (2);
		html_link_back ();
		html_page_close ();
	}
	
	if ($createdir[strlen($createdir)-1] == " " || $createdir[0] == " ")
	{
		html_text_summary_error ("Cannot create directory $createdir because it begins or ends in a space", 1);
		html_break (2);
		html_link_back ();
		html_page_close ();
	}

	$query = db_query ("SELECT name,mime_type FROM phpgw_vfs WHERE name = '$createdir' AND owner_id = '$userinfo[username]' AND directory = '$path'");
	if ($fileinfo = db_fetch_array ($query))
	{
		if ($fileinfo[1] != "Directory")
		{
			html_text_summary_error ("$fileinfo[0] already exists as a file");
			html_break (2);
			html_link_back ();
			html_page_close ();
		}
		else
		{
			html_text_summary_error ("Directory $fileinfo[0] already exists");
			html_break (2);
			html_link_back ();
			html_page_close ();
		}
	}
	else
	{
		$query = db_query ("SELECT SUM(size) FROM phpgw_vfs WHERE owner_id = '$userinfo[username]' AND name != '$file_name[$i]'");
		$files = db_fetch_array ($query);
		$usedspace = $files[0];

		if (($usedspace + 1024) > $userinfo["hdspace"])
		{
			html_text_summary_error ("Sorry, you do not have enough space to create a new directory", 1);
			html_page_close ();
		}

		if ($phpgw->vfs->mkdir ($createdir))
		{
			html_text_summary ("Created directory $disppath/$createdir");
			html_break (2);
			html_link ("$appname/index.php?path=$disppath/$createdir", "Go to $disppath/$createdir");
		}
		else
			echo $phpgw->common->error_list (array ("Could not create $disppath/$createdir"));
	}

	html_break (2);
	html_link_back ();
}

html_page_close ();

?>
