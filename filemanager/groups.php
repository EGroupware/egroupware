<?

require ("main.inc");
error_reporting (4);

###
# Authenticate user
###

$userinfo = check_auth();

###
# Groups can allow/disallow access for anonymous users
# Update: actually not anymore, but we still need this
# for a few display options
###

if ($userinfo["username"] == "anonymous")
	$anonymous = 1;

if (!$group)
	choose_group ();

$query = sql_query ("SELECT * FROM groupinfo WHERE groupname = '$group'");

if (!$groupinfo = mysql_fetch_array($query))
	choose_group ("Group $group does not exist");

$group_access = group_auth ();

###
# Something's gone wrong if we get GROUP_NONE
###

if ($group_access <= GROUP_NONE)
	choose_group ("You do not have access to $groupinfo[groupname]");

if ($group_access >= GROUP_VIEW)
	$view = 1;

if ($group_access >= GROUP_WRITE)
	$write = 1;

if ($group_access >= GROUP_ADMIN)
	$admin = 1;

if ($group_access >= GROUP_FOUNDER)
	$founder = 1;

$phpwh->fs->set_account_type ("group");

$query = sql_query ("SELECT * FROM groupinfo WHERE groupname = '$group'");

if (!$sortby)
	$sortby = "name";

if (!$path)
	$path = "/";

if ($path != "/" && $nextdir)
	$path = $path . "/" . $nextdir;
else
	$path = $path . $nextdir;

if ($path == "/")
	$disppath = "";
else
	$disppath = $path;

$cwd = substr ($path, strrpos ($path, "/") +1);

if (!($lesspath = substr ($path, 0, strrpos ($path, "/"))))
	$lesspath = "/";

if ($rename)
{
	for ($j = 0; $j != $numoffiles; $j++)
		$filesman = array_push ($fileman[$j]);
}

if ($path != "/")
{
	$query = sql_query ("SELECT name FROM groupfiles WHERE groupname = '$groupinfo[groupname]' AND name = '$cwd' AND type = 'Directory' AND directory = '$lesspath'");
	if (!mysql_fetch_row($query))
	{
		html_text_error ("Directory does not exist", 1);
		html_link ("$hostname/groups.php?group=$groupinfo[groupname]", HTML_TEXT_NAVIGATION_BACK_TO_GROUP);
		exit;
	}
}

$query = sql_query ("SELECT * FROM groupfiles WHERE groupname = '$groupinfo[groupname]' AND directory = '$path' ORDER BY $sortby");
$files = mysql_fetch_array($query);
$numoffiles = mysql_affected_rows($db_main);

if ($op != 'showinfo' && $op != 'changeinfo' && $op != 'delete')
{
	html_page_begin ("Groups :: $groupinfo[groupname]");
	html_page_body_begin (HTML_PAGE_BODY_COLOR);
}

if (!$op && !$delete && !$createdir && !$renamefiles)
{
	html_table_begin ("100%");
	html_table_row_begin ();
	html_table_col_begin (NULL, NULL, "top");
	html_font_set (2);
	html_text ("Welcome to " . html_text_bold ("$groupinfo[groupname]", 1));
	html_break (2, html_text_bold ("$userinfo[username]", 1));

	if ($anonymous)
	{
		html_break (2, html_link ("$hostname/login.php", "Login", 1));
		html_break (2, html_link ("$hostname/signup.php", "Create an account", 1));
	}
	else
		html_break (2, html_link ("$hostname/users.php", "Your user page", 1));

	if ($admin)
		html_break (2, html_link ("$hostname/groups.php?group=$groupinfo[groupname]&op=showinfo", "Edit this group", 1));

	if ($founder)
		html_break (2, html_link ("$hostname/groups.php?group=$groupinfo[groupname]&op=delete", "Delete this group", 1));

	html_break (2, html_link ("$hostname/index.php", "Home", 1));
	html_break (2);
	html_break (1);
	
	html_text_bold ($group_access_names[$group_access]);
	html_text ("access");
	html_font_end ();
	html_table_col_end ();
	html_table_col_begin ("center", NULL, "top");
	html_align ("center");
	html_form_begin ("$hostname/groups.php?group=$groupinfo[groupname]&path=$path");
	if ($numoffiles || $cwd)
	{
		html_table_begin ();
		html_table_row_begin (NULL, NULL, NULL, HTML_TABLE_FILES_HEADER_BG_COLOR);
		html_table_col_begin ("center", NULL, NULL, NULL, 8);
		html_table_begin ("100%");
		html_table_row_begin ();
		html_table_col_begin ("left");

		if ($cwd)
			html_link ("$hostname/groups.php?group=$groupinfo[groupname]&path=$lesspath", html_image ("$hostname/images/folder-up.gif", "Up", "left", 0, NULL, 1));
		html_table_col_end ();
		html_table_col_begin ("center");
		
		if ($cwd)
			html_image ("$hostname/images/folder.gif", "Folder", "center");
		else
			html_image ("$hostname/images/folder-home.gif", "Home");
		
		html_font_set (4, HTML_TABLE_FILES_HEADER_TEXT_COLOR);
		html_text_bold (strtoupper($cwd));
		html_table_col_end ();
		html_table_col_begin ("right");

		if ($cwd)
			html_link ("$hostname/groups.php?group=$groupinfo[groupname]", html_image ("$hostname/images/folder-home.gif", "Home", "right", 0, NULL, 1));

		html_table_col_end ();
		html_table_row_end ();
		html_table_end ();
		html_table_col_end ();
		html_table_row_end ();
		html_table_row_begin (NULL, NULL, NULL, HTML_TABLE_FILES_COLUMN_HEADER_BG_COLOR);

		###
		# Start File Table Column Headers
		###

		html_table_col_begin ();
		html_text ("Sort by:" . html_nbsp (5, 1));
		html_table_col_end ();

		html_table_col_begin ();
		html_link ("$hostname/groups.php?group=$groupinfo[groupname]&path=$path&sortby=name", html_text_bold ("Filename", 1));
		html_table_col_end ();

		html_table_col_begin ();
		html_link ("$hostname/groups.php?group=$groupinfo[groupname]&path=$path&sortby=type", html_text_bold ("Type", 1));
		html_table_col_end ();

		html_table_col_begin ();
		html_link ("$hostname/groups.php?group=$groupinfo[groupname]&path=$path&sortby=size", html_text_bold ("Size", 1));
		html_table_col_end ();

		html_table_col_begin ();
		html_link ("$hostname/groups.php?group=$groupinfo[groupname]&path=$path&sortby=createdby", html_text_bold ("Created By", 1));
		html_table_col_end ();

		html_table_col_begin ();
		html_link ("$hostname/groups.php?group=$groupinfo[groupname]&path=$path&sortby=modifiedby", html_text_bold ("Modified By", 1));
		html_table_col_end ();

		html_table_col_begin ();
		html_link ("$hostname/groups.php?group=$groupinfo[groupname]&path=$path&sortby=created", html_text_bold ("Created", 1));
		html_table_col_end ();

		html_table_col_begin ();
		html_link ("$hostname/groups.php?group=$groupinfo[groupname]&path=$path&sortby=modified", html_text_bold ("Modified", 1));
		html_table_col_end ();

		html_table_col_begin ();
		html_table_col_end ();
		html_table_row_end ();

		###
		# List all of the files, with their attributes
		###

		$i = 0;
		while ($i != $numoffiles)
		{
			if ($rename)
			{
				unset($renamethis);
        	                for ($j = 0; $j != $numoffiles; $j++)
				{
                	                if ($fileman[$j] == $files["name"])
					{
                        	                $renamethis = 1;
						break;
					}
        			}
			}

			html_table_row_begin (NULL, NULL, NULL, HTML_TABLE_FILES_BG_COLOR);
			html_table_col_begin ("right");

			if ($write)
			{
				if (!$rename)
					html_form_input ("checkbox", "fileman[$i]", "$files[name]");
				elseif ($renamethis)
					html_form_input ("checkbox", "fileman[$files[name]]", "$files[name]", NULL, NULL, "checked");
				else
					html_nbsp;
			}

			html_table_col_end ();
			html_table_col_begin ();

			if ($renamethis)
			{
				if ($files["type"] == "Directory")
					html_image ("$hostname/images/folder.gif", "Folder");
				html_form_input ("text", "renamefiles[$files[name]]", "$files[name]", 255);
			}
			else
			{
				if ($files["type"] == "Directory")
				{
					html_image ("$hostname/images/folder.gif", "Folder");
					html_link ("$hostname/groups.php?group=$groupinfo[groupname]&path=$path&nextdir=$files[name]", $files["name"]);
                                }
                                else
				{
					html_link ("$hostname/groups/$groupinfo[groupname]$disppath/$files[name]", $files["name"]);
                                }
                        }

			html_table_col_end ();
			html_table_col_begin ();
			html_text ($files["type"]);
			html_table_col_end ();
			html_table_col_begin ();

			if ($files["type"] == "Directory")
			{
				$query2 = sql_query ("SELECT SUM(size) FROM groupfiles WHERE groupname = '$groupinfo[groupname]' AND directory RLIKE '^$disppath/$files[name]'");
				$fileinfo = mysql_fetch_row($query2);
				if ($fileinfo[0])
					borkb($fileinfo[0]+1024);
				else
					echo "1KB";
			}
			else
				borkb($files["size"]);

			html_table_col_end ();

			html_table_col_begin ();
			html_text ($files["createdby"]);
			html_table_col_end ();

			html_table_col_begin ();
			html_text ($files["modifiedby"]);
			html_table_col_end ();

			html_table_col_begin ();
			html_text ($files["created"]);
			html_table_col_end ();

			html_table_col_begin ();
			html_text ($files["modified"]);
			html_table_col_end ();

			html_table_col_begin ();
			html_text ($files["owner"]);
			html_table_col_end ();

			if ($files["deleteable"] == "N")
			{
				html_table_col_begin ();
				html_image ("$hostname/images/locked.gif", "Locked");
				html_table_col_end ();
			}
			else
			{
				html_table_col_begin ();
				html_table_col_end ();
			}

			html_table_row_end ();

			if ($files["type"] == "Directory")
				$usedspace += $fileinfo[0];
			else
				$usedspace += $files["size"];
			$files = mysql_fetch_array($query);
			$i++;
		}

		html_table_end ();
		html_break (2);

		if ($write)
		{
			html_form_input ("submit", "rename", "Rename");
			html_nbsp (3);
			if (!$rename)
			{
				html_form_input ("submit", "delete", "Delete");
				html_nbsp (3);
			}
		}
	}
	if (!$rename)
	{
		if ($write)
		{
			html_form_input ("text", "createdir", NULL, 255);
			html_nbsp ();
			html_form_input ("submit", "newdir", "Create Folder");
			html_form_end ();
		}

		html_break (1);
		html_text_bold ("Files: ");
		html_text ($numoffiles);
		html_nbsp (3);

		html_text_bold ("Used space: ");
		html_text (borkb ($usedspace, NULL, 1));
		html_nbsp (3);
		
		if ($path == "/")
		{
			html_text_bold ("Unused space: ");
			html_text (borkb ($groupinfo["hdspace"] - $usedspace, NULL, 1));

			$query = sql_query ("SELECT name FROM groupfiles WHERE groupname = '$groupinfo[groupname]'");
			$i = mysql_affected_rows($db_main);

			html_break (2);
			html_text_bold ("Total Files: ");
			html_text ($i);
		}
	}
	if ($write)
	{
		html_break (2);
		html_form_begin ("$hostname/groups.php?group=$groupinfo[groupname]&op=upload&path=$path", "post", "multipart/form-data");
		html (html_form_input ("file", "file[]", NULL, 255, NULL, NULL, NULL, 1) . "<br>", 5);
		html_form_input ("submit", "upload_files", "Upload files");
		html_form_end ();
	}

	html_table_col_end ();
	html_table_row_end ();
	html_table_end ();
	html_page_body_end ();
	html_page_end ();
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
			if (strlen($file_name[$i]) > 255)
			{
				html_text_summary_error ("File names must be under 256 characters");
				continue;
			}

			if ($badchar = bad_chars($file_name[$i], 1))
			{
				html_text_summary_error ("Cannot upload $file_name[$i]", "(filename contains '$badchar')");
				continue;
			}

			$query = sql_query ("SELECT * FROM groupfiles WHERE name = '$file_name[$i]' AND groupname = '$groupinfo[groupname]' AND directory = '$path'");
			if ($fileinfo = mysql_fetch_array($query))
			{
				if ($fileinfo["type"] == "Directory")
				{
					html_text_summary_error ("Cannot replace $fileinfo[name] because it is a directory");
					continue;
				}

				$query = sql_query ("SELECT SUM(size) FROM groupfiles WHERE groupname = '$groupinfo[groupname]' AND name != '$file_name[$i]'");
        			$files = mysql_fetch_row($query);
        			$usedspace = $files[0];

				if (($file_size[$i] + $usedspace) > $userinfo["hdspace"])
				{
					html_text_summary_error ("Not enough space to upload $file_name[$i]", NULL, $file_size[$i]);
					continue;
				}

				if ($fileinfo["deleteable"] != "N")
				{
					$query = sql_query ("UPDATE groupfiles SET groupname = '$groupinfo[groupname]',modifiedby = '$userinfo[username]', size = $file_size[$i], type = '$file_type[$i]', modified = NOW(), deleteable = 'Y' WHERE number = '$fileinfo[number]' AND groupname = '$groupinfo[groupname]' AND directory = '$path'");
					copy ($file[$i], "$rootdir/groups/$groupinfo[groupname]$path/$file_name[$i]");

					html_text_summary ("Replaced $disppath/$file_name[$i]", $file_size[$i]);
				}
			}
			else
			{
				$query = sql_query ("SELECT SUM(size) FROM groupfiles WHERE groupname = '$groupinfo[groupname]'");
                                $files = mysql_fetch_row($query);
                                $usedspace = $files[0];

				if (($file_size[$i] + $usedspace) > $userinfo["hdspace"])
				{
					html_text_summary_error ("Not enough space to upload $file_name[$i]", NULL, $file_size[$i]);
					continue;
                                }

				$query = sql_query ("SELECT number FROM groupfiles WHERE groupname = 'number'");
				$number = mysql_fetch_row($query);

				$query = sql_query ("INSERT INTO groupfiles SET number = $number[0]+1, groupname='$groupinfo[groupname]', createdby='$userinfo[username]', modifiedby='', size=$file_size[$i], type='$file_type[$i]', created=NOW(), modified='', deleteable='Y', directory='$path', name='$file_name[$i]'");
				copy ($file[$i], "$rootdir/groups/$groupinfo[groupname]$path/$file_name[$i]");

				$query = sql_query ("UPDATE groupfiles SET number = $number[0]+1 WHERE groupname = 'number'");

				html_text_summary ("Created $disppath/$file_name[$i]", $file_size[$i]);
			}
		}
	}

html_break (2);
html_link ("$hostname/groups.php?group=$groupinfo[groupname]&path=$path", HTML_TEXT_NAVIGATION_BACK_TO_GROUP);
}

elseif ($renamefiles)
{
	while (list($file) = each($renamefiles))
	{
		if ($badchar = bad_chars ($renamefiles[$file], 1))
		{
			html_text_error_summary ("Cannot rename $file to $renamefiles[$file]", "(filename contains '$badchar')");
			continue;
		} 
		if (($fileman[$file] && $renamefiles[$file]) && ($fileman[$file] != $renamefiles[$file]))
		{
			$query = sql_query ("SELECT name FROM groupfiles WHERE groupname = '$groupinfo[groupname]'  AND directory = '$path' AND name = '$renamefiles[$file]'");
			if (mysql_fetch_row($query))
			{
				html_text_summary_error ("Cannot rename $fileman[$file]: $renamefiles[$file] exists");
				continue;
			}
			$query = sql_query ("SELECT number,name,directory FROM groupfiles WHERE groupname = '$groupinfo[groupname]' AND (directory RLIKE '^$disppath/$fileman[$file]/' OR directory = '$disppath/$fileman[$file]')");
			while ($fileinfo = mysql_fetch_row($query))
			{
				$newdir = $fileinfo[2];
				$newdir = preg_replace("|^$disppath/$fileman[$file]|","$disppath/$renamefiles[$file]",$newdir);
				$query2 = sql_query ("UPDATE groupfiles SET directory = '$newdir' WHERE groupname = '$groupinfo[groupname]' AND name = '$fileinfo[1]' AND number = '$fileinfo[0]' AND directory RLIKE '^$disppath/$fileman[$file]'");
			}

			$query = sql_query ("UPDATE groupfiles SET name = '$renamefiles[$file]' WHERE groupname = '$groupinfo[groupname]' AND directory = '$path' AND name = '$fileman[$file]'");
			rename("$rootdir/$userinfo[username]$path/$fileman[$file]","$rootdir/$userinfo[username]$path/$renamefiles[$file]");
			html_text_summary ("Renamed $disppath/$fileman[$file] to $disppath/$renamefiles[$file]");
		}
	}

html_break (2);
html_link ("$hostname/groups.php?group=$groupinfo[groupname]&path=$path", HTML_TEXT_NAVIGATION_BACK_TO_GROUP);
}

elseif ($delete)
{
	$query = sql_query ("SELECT name FROM groupfiles WHERE groupname = '$groupinfo[groupname]'");
	$numoffiles = mysql_affected_rows($db_main);
	for ($i = 0; $i != $numoffiles; $i++)
	{
		if ($fileman[$i])
		{
			if ($query = sql_query ("SELECT name,type,size FROM groupfiles WHERE groupname = '$groupinfo[groupname]' AND name = '$fileman[$i]' AND directory = '$path' AND deleteable = 'Y'"))
			{
				$fileinfo = mysql_fetch_row($query);
				if ($fileinfo[1] == "Directory")
				{
					$query2 = sql_query ("SELECT name,size,directory FROM groupfiles WHERE groupname = '$groupinfo[groupname]' AND type != 'Directory' AND directory RLIKE '^$disppath/$fileman[$i]'");
					while ($files = mysql_fetch_row($query2))
					{
						unlink("$rootdir/groups/$groupinfo[groupname]$files[2]/$files[0]");
						html_text_summary ("Deleted $files[2]/$files[0]", $files[1]);
					}

					$query2 = sql_query ("DELETE FROM groupfiles WHERE groupname = '$groupinfo[groupname]' AND directory RLIKE '^$disppath/$fileman[$i]' AND type != 'Directory'");

					$query2 = sql_query ("SELECT name,type,directory FROM groupfiles WHERE groupname = '$groupinfo[groupname]' AND type = 'Directory' AND (directory RLIKE '^$disppath/$fileman[$i]' OR (name = '$fileman[$i]' AND directory = '$path')) ORDER BY directory DESC");
					while ($files = mysql_fetch_row($query2))
					{
						rmdir("$rootdir/groups/$groupinfo[groupname]$files[2]/$files[0]");
						html_text_summary ("Deleted directory ");
						if ($files[2] == "/")
							html_text_bold ("/$files[0]");
						else
							html_text_bold ("$files[2]/$files[0]");
					}

					$query2 = sql_query ("DELETE FROM groupfiles WHERE groupname = '$groupinfo[groupname]' AND type = 'Directory' AND (directory RLIKE '^$disppath/$fileman[$i]' OR (name = '$fileman[$i]' AND directory = '$path'))");
				}

				else
				{
					$query = sql_query ("DELETE FROM groupfiles WHERE groupname = '$groupinfo[groupname]'
								AND name = '$fileman[$i]' AND directory = '$path'");
					unlink("$rootdir/groups/$groupinfo[groupname]$path/$fileman[$i]");

					html_text_summary ("Deleted $disppath/$fileman[$i]", $fileinfo[2]);
				}
			}
		}
	}
html_break (2);
html_link ("$hostname/groups.php?group=$groupinfo[groupname]&path=$path", HTML_TEXT_NAVIGATION_BACK_TO_GROUP);
}

elseif ($newdir && $createdir)
{
	if ($badchar = bad_chars ($createdir, 1))
	{
		html_text_error_summary ("Cannot create directory $createdir", "(name contains '$badchar')");
		html_break (2);
		html_link ("$hostname/groups.php?group=$groupinfo[groupname]&path=$path", HTML_TEXT_NAVIGATION_BACK_TO_GROUP);
		html_page_close ();
	}

	if ($createdir[strlen($createdir)-1] == " " || $createdir[0] == " ")
	{
		html_text_error_summary ("Cannot create directory $createdir because it begins or ends in a space");
		html_break (2);
		html_link ("$hostname/groups.php?group=$groupinfo[groupname]&path=$path", HTML_TEXT_NAVIGATION_BACK_TO_GROUP);
		html_page_close ();
	}

	$query = sql_query ("SELECT name,type FROM groupfiles WHERE name = '$createdir' AND groupname = '$groupinfo[groupname]' AND directory = '$path'");
	if ($fileinfo = mysql_fetch_row($query))
	{
		if ($fileinfo[1] != "Directory")
		{
			html_text_error_summary ("$fileinfo[0] already exists as a file");
			html_break (2);
			html_link ("$hostname/groups.php?group=$groupinfo[groupname]&path=$path", HTML_TEXT_NAVIGATION_BACK_TO_GROUP);
			html_page_close ();
		}
		else
		{
			html_text_error ("Directory $fileinfo[0] already exists");
			html_break (2);
			html_link ("$hostname/groups.php?group=$groupinfo[groupname]&path=$path", HTML_TEXT_NAVIGATION_BACK_TO_GROUP);
			html_page_close ();
		}
	}
	else
	{
		$query = sql_query ("SELECT SUM(size) FROM groupfiles WHERE groupname = '$groupinfo[groupname]' AND name != '$file_name[$i]'");
		$files = mysql_fetch_row($query);
		$usedspace = $files[0];

		if (($usedspace + 1024) > $userinfo["hdspace"])
		{
			html_text_summary_error ("Sorry, you do not have enough space to create a new directory","Groups : $groupinfo[groupname]");
			html_page_close ();
		}

		$query = sql_query ("SELECT number FROM groupfiles WHERE groupname = 'number'");
		$number = mysql_fetch_row($query);

		$query = sql_query ("INSERT INTO groupfiles SET number=$number[0]+1, groupname='$groupinfo[groupname]', createdby='$userinfo[username]', modifiedby='', size=1024, type='Directory', created=NOW(), modified='', deleteable='Y', directory='$path', name='$createdir'");
		mkdir("$rootdir/groups/$groupinfo[groupname]$path/$createdir",0755);

		$query = sql_query ("UPDATE groupfiles SET number = $number[0]+1 WHERE groupname = 'number'");

		html_text_summary ("Created directory $disppath/$createdir/");
	}

html_break (2);
html_link ("$hostname/groups.php?group=$groupinfo[groupname]&path=$path", HTML_TEXT_NAVIGATION_BACK_TO_GROUP);
}

###
# Show info about a group (to change), but only if they have admin access
# Having write access is not enough
###

elseif ($op == "showinfo")
{
	if (group_auth () >= GROUP_ADMIN)
		showgroupinfo ();

	else
		html_page_error (html_text_summary_error ("Admin access to $groupinfo[groupname] is denied", NULL, NULL, 1));
}

###
# Change group info.  Proceeds showinfo above
###

elseif ($op == "changeinfo")
{
	if ($group_access < GROUP_ADMIN)
		html_page_error (html_text_summary_error ("Admin access to $groupinfo[groupname] is denied", NULL, NULL, 1));

	if ($grouppass == $groupinfo["groupname"])
		showgroupinfo ("Your password cannot be the same as the group name");

	if (strlen ($grouppass) > 10)
		showgroupinfo ("Your password must be 10 characters or less");

	$users = split("\n", $usernames);

	for ($i = 0; $users[$i]; $i++)
	{
		$query = sql_query ("SELECT username FROM userinfo WHERE username = '$users[$i]'");
		if (!$user = mysql_fetch_row($query))
			showgroupinfo ("User $users[$i] does not exist");

		$usernamessep .= $user[0] . ",";
	}

	if ($usernamessep)
		$usernamessep = ',' . $usernamessep; 

	if ($public)
		$public = 'Y';
	else
		$public = 'N';
	
	if ($passonly)
		$passonly = 'Y';
	else
		$passonly = 'N';

	$query = sql_query ("UPDATE groupinfo SET grouppass = PASSWORD('$grouppass'), public = '$public', passonly = '$passonly', users = '$usernamessep' WHERE groupname = '$groupinfo[groupname]'");

	html_page_begin ("Groups :: $groupinfo[groupname]");
	html_page_body_begin ();
	html_break (2);
	html_font_set (NULL, HTML_TEXT_UPDATE_COLOR);
	html_text_bold (HTML_TEXT_NAVIGATION_UPDATE_SUCCESSFUL);
	html_font_end ();
	html_break (2);
	html_link ("$hostname/groups.php?group=$groupinfo[groupname]&path=$path", HTML_TEXT_NAVIGATION_BACK_TO_GROUP);
	html_page_close ();
}

elseif ($op == "delete")
{
	if ($group_access < GROUP_FOUNDER)
		html_page_error (html_text_summary_error ("Founder access to $groupinfo[groupname] is denied", NULL, NULL, 1));

	if ($yesdelete)
	{
		$query = sql_query ("SELECT name,directory FROM groupfiles WHERE groupname = '$groupinfo[groupname]' AND type != 'Directory'");
		while ($fileinfo = mysql_fetch_row($query))
		{
			unlink ("$rootdir/groups/$groupinfo[groupname]$fileinfo[1]/$fileinfo[0]");
		}
		
		$query = sql_query ("SELECT name,directory FROM groupfiles WHERE groupname = '$groupinfo[groupname]' AND type = 'Directory' ORDER BY directory DESC");
                while ($fileinfo = mysql_fetch_row($query))
		{
                        rmdir("$rootdir/groups/$groupinfo[groupname]$fileinfo[1]/$fileinfo[0]");
                }	
		
		$query = sql_query ("DELETE FROM groupfiles WHERE groupname = '$groupinfo[groupname]'");

		$query = sql_query ("DELETE FROM groupinfo WHERE groupname = '$groupinfo[groupname]'");
		rmdir("$rootdir/groups/$groupinfo[groupname]");

		html_page_begin ("Groups :: $groupinfo[groupname]");
		html_page_body_begin ();
		html_break (2);
		html_font_set (NULL, HTML_TEXT_DELETE_ACCOUNT_COLOR);
		html_text_bold (HTML_TEXT_NAVIGATION_DELETED_ACCOUNT);
		html_font_end ();
		html_break (2);
		html_link ("$hostname", HTML_TEXT_NAVIGATION_HOME);
		html_page_close ();
	}
	else
	{
		html_page_begin ("Groups :: $groupinfo[groupname]");
		html_page_body_begin ();
		html_font_set (NULL, HTML_TEXT_DELETE_ACCOUNT_COLOR);
		html_text_bold (HTML_TEXT_NAVIGATION_DELETE_ACCOUNT);
		html_form_begin ("$hostname/groups.php?group=$groupinfo[groupname]&op=delete");
		html_form_input ("submit", "yesdelete", "Yes, please delete my group");
		html_form_end ();
		html_page_close ();
	}
}

?>
