<?php

###
# DEV NOTE:
#
# index.php is depreciated by the inc/class.xxfilemanager.inc.php files.
# index.php is still used in the 0.9.14 release, but all future changes should be
# made to the inc/class.xxfilemanager.inc.php files (3-tiered).  This includes using templates.
###

###
# Enable this to display some debugging info
###

$phpwh_debug = 0;

@reset ($GLOBALS['HTTP_POST_VARS']);
while (list ($name,) = @each ($GLOBALS['HTTP_POST_VARS']))
{
	$$name = $GLOBALS['HTTP_POST_VARS'][$name];
}

@reset ($GLOBALS['HTTP_GET_VARS']);
while (list ($name,) = @each ($GLOBALS['HTTP_GET_VARS']))
{
	$$name = $GLOBALS['HTTP_GET_VARS'][$name];
}

$to_decode = array
(
	/*
	Decode
	'var'	when	  'avar' == 'value'
	or
	'var'	when	  'var'  is set
	*/
	'op'	=> array ('op' => ''),
	'path'	=> array ('path' => ''),
	'file'	=> array ('file' => ''),
	'sortby'	=> array ('sortby' => ''),
	'fileman'	=> array ('fileman' => ''),
	'messages'	=> array ('messages'	=> ''),
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
				$temp = array ();
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

if ($noheader || $nofooter || ($download && (count ($fileman) > 0)) || ($op == 'view' && $file) || ($op == 'history' && $file) || ($op == 'help' && $help_name))
{
	$noheader = True;
	$nofooter = True;
}

$GLOBALS['phpgw_info']['flags'] = array
(
	'currentapp'	=> 'filemanager',
	'noheader'	=> $noheader,
	'nofooter'	=> $nofooter,
	'noappheader'	=> False,
	'enable_vfs_class'	=> True,
	'enable_browser_class'	=> True
);

include ('../header.inc.php');

if ($execute && $command_line)
{
	if ($result = $GLOBALS['phpgw']->vfs->command_line (array ('command_line' => stripslashes ($command_line))))
	{
		$messages = html_text_bold (lang('Command sucessfully run'),1);
		if ($result != 1 && strlen ($result) > 0)
		{
			$messages .= html_break (2, NULL, 1) . $result;
		}
	}
	else
	{
		$messages = $GLOBALS['phpgw']->common->error_list (array (lang('Error running command')));
	}
}

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
	$path = $GLOBALS['phpgw']->vfs->pwd ();

	if (!$path || $GLOBALS['phpgw']->vfs->pwd (array ('full' => False)) == '')
	{
		$path = $GLOBALS['homedir'];
	}
}

$GLOBALS['phpgw']->vfs->cd (array ('string' => False, 'relatives' => array (RELATIVE_NONE), 'relative' => False));
$GLOBALS['phpgw']->vfs->cd (array ('string' => $path, 'relatives' => array (RELATIVE_NONE), 'relative' => False));

$pwd = $GLOBALS['phpgw']->vfs->pwd ();

if (!$cwd = substr ($path, strlen ($GLOBALS['homedir']) + 1))
{
	$cwd = '/';
}
else
{
	$cwd = substr ($pwd, strrpos ($pwd, '/') + 1);
}

$disppath = $path;

/* This just prevents // in some cases */
if ($path == '/')
	$dispsep = '';
else
	$dispsep = '/';

if (!($lesspath = substr ($path, 0, strrpos ($path, '/'))))
	$lesspath = '/';

$now = date ('Y-m-d');

if ($phpwh_debug)
{
	echo "<b>Filemanager debug:</b><br>
		path: $path<br>
		disppath: $disppath<br>
		cwd: $cwd<br>
		lesspath: $lesspath
		<p>
		<b>phpGW debug:</b><br>
		real getabsolutepath: " . $GLOBALS['phpgw']->vfs->getabsolutepath (array ('target' => False, 'mask' => False, 'fake' => False)) . "<br>
		fake getabsolutepath: " . $GLOBALS['phpgw']->vfs->getabsolutepath (array ('target' => False)) . "<br>
		appsession: " . $GLOBALS['phpgw']->session->appsession ('vfs','') . "<br>
		pwd: " . $GLOBALS['phpgw']->vfs->pwd () . "<br>";
}

###
# Get their readable groups to be used throughout the script
###

$groups = array ();

$groups = $GLOBALS['phpgw']->accounts->get_list ('groups');

$readable_groups = array ();

while (list ($num, $account) = each ($groups))
{
	if ($GLOBALS['phpgw']->vfs->acl_check (array (
			'owner_id' => $account['account_id'],
			'operation' => PHPGW_ACL_READ
		))
	)
	{
		$readable_groups[$account['account_lid']] = Array('account_id' => $account['account_id'], 'account_name' => $account['account_lid']);
	}
}

$groups_applications = array ();

while (list ($num, $group_array) = each ($readable_groups))
{
	$group_id = $GLOBALS['phpgw']->accounts->name2id ($group_array['account_name']);

	$applications = CreateObject('phpgwapi.applications', $group_id);
	$groups_applications[$group_array['account_name']] = $applications->read_account_specific ();
}

###
# We determine if they're in their home directory or a group's directory,
# and set the VFS working_id appropriately
###

if ((preg_match ('+^'.$GLOBALS['fakebase'].'\/(.*)(\/|$)+U', $path, $matches)) && $matches[1] != $GLOBALS['userinfo']['account_lid'])
{
	$GLOBALS['phpgw']->vfs->working_id = $GLOBALS['phpgw']->accounts->name2id ($matches[1]);
}
else
{
	$GLOBALS['phpgw']->vfs->working_id = $GLOBALS['userinfo']['username'];
}

if ($path != $GLOBALS['homedir']
	&& $path != $GLOBALS['fakebase']
	&& $path != '/'
	&& !$GLOBALS['phpgw']->vfs->acl_check (array (
			'string' => $path,
			'relatives' => array (RELATIVE_NONE),
			'operation' => PHPGW_ACL_READ
	))
)
{
	echo $GLOBALS['phpgw']->common->error_list (array (lang('You do not have access to x', $path)));
	html_break (2);
	html_link ($GLOBALS['appname'].'/index.php?path='.$GLOBALS['homedir'], lang('Go to your home directory'));
	html_page_close ();
}

$GLOBALS['userinfo']['working_id'] = $GLOBALS['phpgw']->vfs->working_id;
$GLOBALS['userinfo']['working_lid'] = $GLOBALS['phpgw']->accounts->id2name ($GLOBALS['userinfo']['working_id']);

###
# If their home directory doesn't exist, we create it
# Same for group directories
###

if (($path == $GLOBALS['homedir'])
	&& !$GLOBALS['phpgw']->vfs->file_exists (array (
		'string' => $GLOBALS['homedir'],
		'relatives' => array (RELATIVE_NONE)
	))
)
{
	$GLOBALS['phpgw']->vfs->override_acl = 1;

	if (!$GLOBALS['phpgw']->vfs->mkdir (array ('string' => $GLOBALS['homedir'], 'relatives' => array (RELATIVE_NONE))))
	{
		$p = $phpgw->vfs->path_parts (array ('string' => $GLOBALS['homedir'], 'relatives' => array (RELATIVE_NONE)));
		echo $GLOBALS['phpgw']->common->error_list (array (lang('Could not create directory x', $GLOBALS['homedir'] . ' (' . $p->real_full_path . ')')));
	}

	$GLOBALS['phpgw']->vfs->override_acl = 0;
}

###
# Verify path is real
###

if ($path != $GLOBALS['homedir'] && $path != '/' && $path != $GLOBALS['fakebase'])
{
	if (!$GLOBALS['phpgw']->vfs->file_exists (array ('string' => $path, 'relatives' => array (RELATIVE_NONE))))
	{
		echo $GLOBALS['phpgw']->common->error_list (array (lang('Directory x does not exist', $path)));
		html_break (2);
		html_link ($GLOBALS['appname'].'/index.php?path='.$GLOBALS['homedir'], lang('Go to your home directory'));
		html_break (2);
		html_link_back ();
		html_page_close ();
	}
}

/* Update if they request it, or one out of 20 page loads */
srand ((double) microtime() * 1000000);
if ($update || rand (0, 19) == 4)
{
	$GLOBALS['phpgw']->vfs->update_real (array ('string' => $path, 'relatives' => array (RELATIVE_NONE)));
}

###
# Check available permissions for $path, so we can disable unusable operations in user interface
###

if ($GLOBALS['phpgw']->vfs->acl_check (array (
        'string'        => $path,
        'relatives' => array (RELATIVE_NONE),
        'operation' => PHPGW_ACL_ADD
        ))
)
{
	$can_add = True;
}

###
# Default is to sort by name
###

if (!$sortby)
{
	$sortby = 'name';
}

###
# Decide how many upload boxes to show
###

if (!$show_upload_boxes || $show_upload_boxes <= 0)
{
	if (!$show_upload_boxes = $GLOBALS['settings']['show_upload_boxes'])
	{
		$show_upload_boxes = 5;
	}
}


###
# Read in file info from database to use in the rest of the script
# $fakebase is a special directory.  In that directory, we list the user's
# home directory and the directories for the groups they're in
###

$numoffiles = 0;
if ($path == $GLOBALS['fakebase'])
{
	if (!$GLOBALS['phpgw']->vfs->file_exists (array ('string' => $GLOBALS['homedir'], 'relatives' => array (RELATIVE_NONE))))
	{
		$GLOBALS['phpgw']->vfs->mkdir (array ('string' => $GLOBALS['homedir'], 'relatives' => array (RELATIVE_NONE)));
	}

	$ls_array = $GLOBALS['phpgw']->vfs->ls (array (
			'string'	=> $GLOBALS['homedir'],
			'relatives'	=> array (RELATIVE_NONE),
			'checksubdirs'	=> False,
			'nofiles'	=> True
		)
	);
	$files_array[] = $ls_array[0];
	$numoffiles++;
//	$files_array = $ls_array;
//	$numoffiles = count($ls_array);

	reset ($readable_groups);
	while (list ($num, $group_array) = each ($readable_groups))
	{
		###
		# If the group doesn't have access to this app, we don't show it
		###

		if (!$groups_applications[$group_array['account_name']][$GLOBALS['appname']]['enabled'])
		{
			continue;
		}

		if (!$GLOBALS['phpgw']->vfs->file_exists (array (
				'string'	=> $GLOBALS['fakebase'].'/'.$group_array['account_name'],
				'relatives'	=> array (RELATIVE_NONE)
			))
		)
		{
			$GLOBALS['phpgw']->vfs->override_acl = 1;
			$GLOBALS['phpgw']->vfs->mkdir (array (
					'string'	=> $GLOBALS['fakebase'].'/'.$group_array['account_name'],
					'relatives'	=> array (RELATIVE_NONE)
				)
			);
			$GLOBALS['phpgw']->vfs->override_acl = 0;

			$GLOBALS['phpgw']->vfs->set_attributes (array (
					'string'	=> $GLOBALS['fakebase'].'/'.$group_array['account_name'],
					'relatives'	=> array (RELATIVE_NONE),
					'attributes'	=> array (
								'owner_id' => $group_array['account_id'],
								'createdby_id' => $group_array['account_id']
							)
				)
			);
		}

		$ls_array = $GLOBALS['phpgw']->vfs->ls (array (
				'string'	=> $GLOBALS['fakebase'].'/'.$group_array['account_name'],
				'relatives'	=> array (RELATIVE_NONE),
				'checksubdirs'	=> False,
				'nofiles'	=> True
			)
		);

		$files_array[] = $ls_array[0];

		$numoffiles++;
	}
}
else
{
	$ls_array = $GLOBALS['phpgw']->vfs->ls (array (
			'string'	=> $path,
			'relatives'	=> array (RELATIVE_NONE),
			'checksubdirs'	=> False,
			'nofiles'	=> False,
			'orderby'	=> $sortby
		)
	);

	if ($phpwh_debug)
	{
		echo '# of files found in "'.$path.'" : '.count($ls_array).'<br>'."\n";
	}

	while (list ($num, $file_array) = each ($ls_array))
	{
		$numoffiles++;
		$files_array[] = $file_array;
		if ($phpwh_debug)
		{
			echo 'Filename: '.$file_array['name'].'<br>'."\n";
		}
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
		echo $GLOBALS['phpgw']->vfs->read (array ('string' => $fileman[$i]));
		$GLOBALS['phpgw']->common->phpgw_exit ();
	}
}

if ($op == 'view' && $file)
{
	$ls_array = $GLOBALS['phpgw']->vfs->ls (array (
			'string'	=> $path.'/'.$file,
			'relatives'	=> array (RELATIVE_ALL),
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
	echo $GLOBALS['phpgw']->vfs->read (array (
			'string'	=> $path.'/'.$file,
			'relatives'	=> array (RELATIVE_NONE)
		)
	);
	$GLOBALS['phpgw']->common->phpgw_exit ();
}

if ($op == 'history' && $file)
{
	$journal_array = $GLOBALS['phpgw']->vfs->get_journal (array (
			'string'	=> $file,
			'relatives'	=> array (RELATIVE_ALL)
		)
	);

	if (is_array ($journal_array))
	{
		html_table_begin ();
		html_table_row_begin ();
		html_table_col_begin ();
		html_text_bold (lang('Date'));
		html_table_col_end ();
		html_table_col_begin ();
		html_text_bold (lang('Version'));
		html_table_col_end ();
		html_table_col_begin ();
		html_text_bold (lang('Who'));
		html_table_col_end ();
		html_table_col_begin ();
		html_text_bold (lang('Operation'));
		html_table_col_end ();
		html_table_row_end ();

		while (list ($num, $journal_entry) = each ($journal_array))
		{
			html_table_row_begin ();
			html_table_col_begin ();
			html_text ($journal_entry['created'] . html_nbsp (3, 1));
			html_table_col_end ();
			html_table_col_begin ();
			html_text ($journal_entry['version'] . html_nbsp (3, 1));
			html_table_col_end ();
			html_table_col_begin ();
			html_text ($GLOBALS['phpgw']->accounts->id2name ($journal_entry['owner_id']) . html_nbsp (3, 1));
			html_table_col_end ();
			html_table_col_begin ();
			html_text ($journal_entry['comment']);
			html_table_col_end ();
		}

		html_table_end ();
		html_page_close ();
	}
	else
	{
		html_text_bold (lang('No version history for this file/directory'));
	}

}

if ($newfile && $createfile)
{
	if ($badchar = bad_chars ($createfile, True, True))
	{
		echo $GLOBALS['phpgw']->common->error_list (array (html_encode (lang('File names cannot contain "x"',$badchar), 1)));
		html_break (2);
		html_link_back ();
		html_page_close ();
	}

	if ($GLOBALS['phpgw']->vfs->file_exists (array (
			'string'	=> $createfile,
			'relatives'	=> array (RELATIVE_ALL)
		))
	)
	{
		echo $GLOBALS['phpgw']->common->error_list (array (lang('File x already exists. Please edit it or delete it first.', $createfile)));
		html_break (2);
		html_link_back ();
		html_page_close ();
	}

	if ($GLOBALS['phpgw']->vfs->touch (array (
			'string'	=> $createfile,
			'relatives'	=> array (RELATIVE_ALL)
		))
	)
	{
		$fileman = array ();
		$fileman[0] = $createfile;
		$edit = 1;
		$numoffiles++;
	}
	else
	{
		echo $GLOBALS['phpgw']->common->error_list (array (lang('File x could not be created.', $createfile)));
	}
}

if ($op == 'help' && $help_name)
{
	while (list ($num, $help_array) = each ($help_info))
	{
		if ($help_array[0] != $help_name)
			continue;

		$help_array[1] = preg_replace ("/\[(.*)\|(.*)\]/Ue", "html_help_link ('\\1', '\\2', False, True)", $help_array[1]);
		$help_array[1] = preg_replace ("/\[(.*)\]/Ue", "html_help_link ('\\1', '\\1', False, True)", $help_array[1]);

		html_font_set ('4');
		$title = ereg_replace ('_', ' ', $help_array[0]);
		$title = ucwords ($title);
		html_text ($title);
		html_font_end ();

		html_break (2);

		html_font_set ('2');
		html_text ($help_array[1]);
		html_font_end ();
	}

	$GLOBALS['phpgw']->common->phpgw_exit ();
}

###
# Start Main Page
###

html_page_begin (lang('Users').' :: '.$GLOBALS['userinfo']['username']);
html_page_body_begin (HTML_PAGE_BODY_COLOR);

if ($messages)
{
	html_text ($messages);
}

if (!is_array ($GLOBALS['settings']))
{
	$pref = CreateObject ('phpgwapi.preferences', $GLOBALS['userinfo']['username']);
	$pref->read_repository (); 
	$GLOBALS['phpgw']->hooks->single ('add_def_pref', $GLOBALS['appname']);
	$pref->save_repository (True);
	$pref_array = $pref->read_repository ();
	$GLOBALS['settings'] = $pref_array[$GLOBALS['appname']];
}

###
# Start Main Table 
###

if (!$op && !$delete && !$createdir && !$renamefiles && !$move && !$copy && !$edit && !$comment_files)
{
	html_table_begin ('100%');
	html_table_row_begin ();
	html_table_col_begin ('center', NULL, 'top');
	html_align ('center');
	html_form_begin ($GLOBALS['appname'].'/index.php?path='.$path);
	if ($numoffiles || $cwd)
	{
		while (list ($num, $name) = each ($GLOBALS['settings']))
		{
			if ($name)
			{
				$columns++;
			}
		}
		$columns++;
		html_table_begin ();
		html_table_row_begin (NULL, NULL, NULL, HTML_TABLE_FILES_HEADER_BG_COLOR);
		html_table_col_begin ('center', NULL, NULL, NULL, $columns);
		html_table_begin ('100%');
		html_table_row_begin ();
		html_table_col_begin ('left');
		
		if ($path != '/')
		{
			html_link ($GLOBALS['appname'].'/index.php?path='.$lesspath, html_image ('images/folder-up.gif', lang('Up'), 'left', 0, NULL, 1));
			html_help_link ('up');
		}
		
		html_table_col_end ();
		html_table_col_begin ('center');
		
		if ($cwd)
		{
			if ($path == $GLOBALS['homedir'])
			{
				html_image ('images/folder-home.gif', lang('Folder'), 'center');
			}
			else
			{
				html_image ('images/folder.gif', lang('Folder'), 'center');
			}
		}
		else
		{
			html_image ('images/folder-home.gif', lang('Home'));
		}
		
		html_font_set (4, HTML_TABLE_FILES_HEADER_TEXT_COLOR);
                html_text_bold (strtoupper ($disppath));
		html_font_end ();
		html_help_link ('directory_name');
		html_table_col_end ();
		html_table_col_begin ('right');
		
		if ($path != $GLOBALS['homedir'])
		{
			html_link ($GLOBALS['appname'].'/index.php?path='.$GLOBALS['homedir'], html_image ('images/folder-home.gif', lang('Home'), 'right', 0, NULL, 1));
			html_help_link ('home');
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
		html_text (lang('Sort by:') . html_nbsp (1, 1), NULL, NULL, 0);
		html_help_link ('sort_by');
		html_table_col_end ();

		reset ($file_attributes);
		while (list ($internal, $displayed) = each ($file_attributes))
		{
			if ($GLOBALS['settings'][$internal])
			{
				html_table_col_begin ();
				html_link ($GLOBALS['appname'].'/index.php?path='.$path.'&sortby='.$internal, html_text_bold ($displayed, 1, 0));
				html_help_link (strtolower (ereg_replace (' ', '_', $displayed)));
				html_table_col_end ();
			}
		}

		html_table_col_begin ();
		html_table_col_end ();
		html_table_row_end ();

		if ($GLOBALS['settings']['dotdot'] && $GLOBALS['settings']['name'] && $path != '/')
		{
			html_table_row_begin ();
			html_table_col_begin ();
			html_table_col_end ();

			/* We can assume the next column is the name */
			html_table_col_begin ();
			html_image ('images/folder.gif', lang('Folder'));
			html_link ($GLOBALS['appname'].'/index.php?path='.$lesspath, '..');
			html_table_col_end ();

			if ($GLOBALS['settings']['mime_type'])
			{
				html_table_col_begin ();
				html_text (lang('Directory'));
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
					if ($fileman[$j] == $files['name'])
					{
						$this_selected = 1;
						break;
					}
				}

				if ($rename && $this_selected)
				{
					$renamethis = 1;
				}
				elseif ($edit_comments && $this_selected)
				{
					$edit_this_comment = 1;
				}
			}

			if (!$GLOBALS['settings']['dotfiles'] && ereg ("^\.", $files['name']))
			{
				continue;
			}

			html_table_row_begin (NULL, NULL, NULL, HTML_TABLE_FILES_BG_COLOR);

			###
			# Checkboxes
			###

			html_table_col_begin ('right');

			if (!$rename && !$edit_comments && $path != $GLOBALS['fakebase'] && $path != '/')
			{
				html_form_input ('checkbox', 'fileman['.$i.']', base64_encode ($files['name']));
			}
			elseif ($renamethis)
			{
				html_form_input ('hidden', 'fileman[' . base64_encode ($files['name']) . ']', $files['name'], NULL, NULL, 'checked');
			}
			else
			{
				html_nbsp();
			}

			html_table_col_end ();

			###
			# File name and icon
			###

			if ($GLOBALS['settings']['name'])
			{
				if ($phpwh_debug)
				{
					echo 'Setting file name: '.$files['name'].'<br>'."\n";
				}

				html_table_col_begin ();

				if ($renamethis)
				{
					if ($files['mime_type'] == 'Directory')
					{
						html_image ('images/folder.gif', lang('Folder'));
					}
					html_form_input ('text', 'renamefiles[' . base64_encode ($files['name']) . ']', $files['name'], 255);
				}
				else
				{
					if ($files['mime_type'] == 'Directory')
					{
						html_image ('images/folder.gif', lang('Folder'));		
						html_link ($GLOBALS['appname'].'/index.php?path='.$path.$dispsep.$files['name'], $files['name']);
					}
					else
					{
						if ($GLOBALS['settings']['viewonserver'] && isset ($GLOBALS['filesdir']) && !$files['link_directory'])
						{
							$clickview = $GLOBALS['filesdir'].$pwd.'/'.$files['name'];

							if ($phpwh_debug)
							{
								echo 'Setting clickview = '.$clickview.'<br>'."\n";
							}
						}
						else
						{
							$clickview = $GLOBALS['appname'].'/index.php?op=view&file='.$files['name'].'&path='.$path;
						}

						if ($GLOBALS['settings']['viewinnewwin'])
						{
							$target = '_new';
						}

						html_link ($clickview, $files['name'], 0, 1, 0, $target);
					}
				}

				html_table_col_end ();
			}

			###
			# MIME type
			###

			if ($GLOBALS['settings']['mime_type'])
			{
				html_table_col_begin ();
				html_text ($files['mime_type']);
				html_table_col_end ();
			}

			###
			# File size
			###

			if ($GLOBALS['settings']['size'])
			{
				html_table_col_begin ();

				$size = $GLOBALS['phpgw']->vfs->get_size (array (
						'string'	=> $files['directory'] . '/' . $files['name'],
						'relatives'	=> array (RELATIVE_NONE)
					)
				);

				borkb ($size);

				html_table_col_end ();
			}

			###
			# Date created
			###
			if ($GLOBALS['settings']['created'])
			{
				html_table_col_begin ();
				html_text ($files['created']);
				html_table_col_end ();
			}

			###
			# Date modified
			###

			if ($GLOBALS['settings']['modified'])
			{
				html_table_col_begin ();
				if ($files['modified'] != '0000-00-00')
				{
					html_text ($files['modified']);
				}
				html_table_col_end ();
			}

			###
			# Owner name
			###

			if ($GLOBALS['settings']['owner'])
			{
				html_table_col_begin ();
				html_text ($GLOBALS['phpgw']->accounts->id2name ($files['owner_id']));
				html_table_col_end ();
			}

			###
			# Creator name
			###

			if ($GLOBALS['settings']['createdby_id'])
			{
				html_table_col_begin ();
				if ($files['createdby_id'])
				{
					html_text ($GLOBALS['phpgw']->accounts->id2name ($files['createdby_id']));
				}
				html_table_col_end ();
			}

			###
			# Modified by name
			###

			if ($GLOBALS['settings']['modifiedby_id'])
			{
				html_table_col_begin ();
				if ($files['modifiedby_id'])
				{
					html_text ($GLOBALS['phpgw']->accounts->id2name ($files['modifiedby_id']));
				}
				html_table_col_end ();
			}

			###
			# Application
			###

			if ($GLOBALS['settings']['app'])
			{
				html_table_col_begin ();
				html_text ($files['app']);
				html_table_col_end ();
			}

			###
			# Comment
			###

			if ($GLOBALS['settings']['comment'])
			{
				html_table_col_begin ();
				if ($edit_this_comment)
				{
					html_form_input ('text', 'comment_files[' . base64_encode ($files['name']) . ']', html_encode ($files['comment'], 1), 255);
				}
				else
				{
					html_text ($files['comment']);
				}
				html_table_col_end ();
			}

			###
			# Version
			###

			if ($GLOBALS['settings']['version'])
			{
				html_table_col_begin ();
				html_link ($GLOBALS['appname'].'/index.php?op=history&file='.$files['name'].'&path='.$path, $files['version'], NULL, True, NULL, '_new');
				html_table_col_end ();
			}

			###
			# Deleteable (currently not used)
			###

			if ($GLOBALS['settings']['deleteable'])
			{
				if ($files['deleteable'] == 'N')
				{
					html_table_col_begin ();
					html_image ('images/locked.gif', lang('Locked'));
					html_table_col_end ();
				}
				else
				{
					html_table_col_begin ();
					html_table_col_end ();
				}
			}

			html_table_row_end ();

			if ($files['mime_type'] == 'Directory')
			{
				$usedspace += $fileinfo[0];
			}
			else
			{
				$usedspace += $files['size'];
			}
		}

		html_table_end ();
		html_break (2);

		if ($path != '/' && $path != $GLOBALS['fakebase'])
		{
			if (!$rename && !$edit_comments)
			{
				html_form_input ('submit', 'edit', lang('Edit'));
				html_help_link ('edit');
				html_nbsp (3);
			}

			if (!$edit_comments)
			{
				html_form_input ('submit', 'rename', lang('Rename'));
				html_help_link ('rename');
				html_nbsp (3);
			}

			if (!$rename && !$edit_comments)
			{
				html_form_input ('submit', 'delete', lang('Delete'));
				html_help_link ('delete');
				html_nbsp (3);
			}

			if (!$rename)
			{
				html_form_input ('submit', 'edit_comments', lang('Edit comments'));
				html_help_link ('edit_comments');
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
		html_form_input ('submit', 'go', lang('Go to:'));
		html_help_link ('go_to');

		if ($path != '/' && $path != $GLOBALS['fakebase'])
		{
			html_form_input ('submit', 'copy', lang('Copy to:'));
			html_help_link ('copy_to');
			html_form_input ('submit', 'move', lang('Move to:'));
			html_help_link ('move_to');
		}

		html_form_select_begin ('todir');

		html_break (1);

		###
		# First we get the directories in their home directory
		###

		$dirs = array ();
		$dirs[] = array ('directory' => $GLOBALS['fakebase'], 'name' => $GLOBALS['userinfo']['account_lid']);

		$ls_array = $GLOBALS['phpgw']->vfs->ls (array (
				'string'	=> $GLOBALS['homedir'],
				'relatives'	=> array (RELATIVE_NONE),
				'checksubdirs'	=> True,
				'mime_type'	=> 'Directory'
			)
		);

		while (list ($num, $dir) = each ($ls_array))
		{
			$dirs[] = $dir;
		}


		###
		# Then we get the directories in their readable groups' home directories
		###

		reset ($readable_groups);
		while (list ($num, $group_array) = each ($readable_groups))
		{
			###
			# Don't list directories for groups that don't have access
			###

			if (!$groups_applications[$group_array['account_name']][$GLOBALS['appname']]['enabled'])
			{
				continue;
			}

			$dirs[] = array ('directory' => $GLOBALS['fakebase'], 'name' => $group_array['account_name']);

			$ls_array = $phpgw->vfs->ls (array (
					'string'	=> $GLOBALS['fakebase'].'/'.$group_array['account_name'],
					'relatives'	=> array (RELATIVE_NONE),
					'checksubdirs'	=> True,
					'mime_type'	=> 'Directory'
				)
			);
			while (list ($num, $dir) = each ($ls_array))
			{
				$dirs[] = $dir;
			}
		}

		reset ($dirs);
		while (list ($num, $dir) = each ($dirs))
		{
			if (!$dir['directory'])
			{
				continue;
			}
			
			###
			# So we don't display //
			###

			if ($dir['directory'] != '/')
			{
				$dir['directory'] .= '/';
			}

			###
			# No point in displaying the current directory, or a directory that doesn't exist
			###
			
			if ((($dir['directory'] . $dir['name']) != $path)
				&& $GLOBALS['phpgw']->vfs->file_exists (array (
						'string'	=> $dir['directory'] . $dir['name'],
						'relatives'	=> array (RELATIVE_NONE)
				))
			)
			{
				html_form_option ($dir['directory'] . $dir['name'], $dir['directory'] . $dir['name']);
			}
		}

		html_form_select_end ();
		html_help_link ('directory_list');

		if ($path != '/' && $path != $GLOBALS['fakebase'])
		{
			html_break (1);

			html_form_input ('submit', 'download', lang('Download'));
			html_help_link ('download');
			html_nbsp (3);

			if ($can_add)
			{
				html_form_input ('text', 'createdir', NULL, 255, 15);
				html_form_input ('submit', 'newdir', lang('Create Folder'));
				html_help_link ('create_folder');
			}
		}

		html_break (1);
		html_form_input ('submit', 'update', lang('Update'));
		html_help_link ('update');

		if ($path != '/' && $path != $GLOBALS['fakebase'] && $can_add)
		{
			html_nbsp (3);
			html_form_input ('text', 'createfile', NULL, 255, 15);
			html_form_input ('submit', 'newfile', lang('Create File'));
			html_help_link ('create_file');
		}

		if ($GLOBALS['settings']['show_command_line'])
		{
			html_break (2);
			html_form_input ('text', 'command_line', NULL, NULL, 50);
			html_help_link ('command_line');

			html_break (1);
			html_form_input ('submit', 'execute', lang('Execute'));
			html_help_link ('execute');
		}

		html_form_end ();

		html_help_link ('file_stats');
		html_break (1);
		html_text_bold (lang('Files').': ');
		html_text ($numoffiles);
		html_nbsp (3);

		html_text_bold (lang('Used space').': ');
		html_text (borkb ($usedspace, NULL, 1));
		html_nbsp (3);
		
		if ($path == $GLOBALS['homedir'] || $path == $GLOBALS['fakebase'])
		{
			html_text_bold (lang('Unused space').': ');
			html_text (borkb ($GLOBALS['userinfo']['hdspace'] - $usedspace, NULL, 1));

			$ls_array = $GLOBALS['phpgw']->vfs->ls (array (
					'string'	=> $path,
					'relatives'	=> array (RELATIVE_NONE)
				)
			);

			$i = count ($ls_array);

			html_break (2);
			html_text_bold (lang('Total Files').': ');
			html_text ($i);
		}
		
		###
		# Show file upload boxes. Note the last argument to html ().  Repeats $show_upload_boxes times
		###

		if ($path != '/' && $path != $GLOBALS['fakebase'] && $can_add)
		{
			html_break (2);
			html_form_begin ($GLOBALS['appname'].'/index.php?op=upload&path='.$path, 'post', 'multipart/form-data');
			html_table_begin ();
			html_table_row_begin ('center');
			html_table_col_begin ();
			html_text_bold (lang('File'));
			html_help_link ('upload_file');
			html_table_col_end ();
			html_table_col_begin ();
			html_text_bold (lang('Comment'));
			html_help_link ('upload_comment');
			html_table_col_end ();
			html_table_row_end ();

			html_table_row_begin ();
			html_table_col_begin ();
			html_form_input ('hidden', 'show_upload_boxes', base64_encode ($show_upload_boxes));
			html (html_form_input ('file', 'upload_file[]', NULL, 255, NULL, NULL, NULL, 1) . html_break (1, NULL, 1), $show_upload_boxes);
			html_table_col_end ();
			html_table_col_begin ();
			html (html_form_input ('text', 'upload_comment[]', NULL, NULL, NULL, NULL, NULL, 1) . html_break (1, NULL, 1), $show_upload_boxes);
			html_table_col_end ();
			html_table_row_end ();
			html_table_end ();
			html_form_input ('submit', 'upload_files', lang('Upload files'));
			html_help_link ('upload_files');
			html_break (2);
			html_text (lang('Show') . html_nbsp (1, True));
			html_link ($GLOBALS['appname'].'/index.php?show_upload_boxes=5', '5');
			html_nbsp ();
			html_link ($GLOBALS['appname'].'/index.php?show_upload_boxes=10', '10');
			html_nbsp ();
			html_link ($GLOBALS['appname'].'/index.php?show_upload_boxes=20', '20');
			html_nbsp ();
			html_link ($GLOBALS['appname'].'/index.php?show_upload_boxes=50', '50');
			html_nbsp ();
			html_text (lang('upload fields'));
			html_nbsp ();
			html_help_link ('show_upload_fields');
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
		html_text_bold (lang('Preview of x', $path.'/'.$edit_file));
		html_break (2);

		html_table_begin ('90%');
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

		if ($GLOBALS['phpgw']->vfs->write (array (
				'string'	=> $edit_file,
				'relatives'	=> array (RELATIVE_ALL),
				'content'	=> $content
			))
		)
		{
			html_text_bold (lang('Saved x', $path.'/'.$edit_file));
			html_break (2);
			html_link_back ();
		}
		else
		{
			html_text_error (lang('Could not save x', $path.'/'.$edit_file));
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

			$content = $fileman[$j];
			echo 'fileman['.$j.']: '.$fileman[$j].'<br><b>'.$content.'</b><br>';
			continue;

			if ($GLOBALS['phpgw']->vfs->write (array (
					'string'	=> $fileman[$j],
					'relatives'	=> array (RELATIVE_ALL),
					'content'	=> $content
				))
			)
			{
				html_text_bold (lang('Saved x', $path.'/'.$fileman[$j]));
				html_break (1);
			}
			else
			{
				html_text_error (lang('Could not save x', $path.'/'.$fileman[$j]));
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

		if ($fileman[$j] && $GLOBALS['phpgw']->vfs->file_exists (array (
						'string'	=> $fileman[$j],
						'relatives'	=> array (RELATIVE_ALL)
			))
		)
		{
			if ($edit_file)
			{
				$content = stripslashes ($edit_file_content);
			}
			else
			{
				$content = $GLOBALS['phpgw']->vfs->read (array ('string' => $fileman[$j]));
			}

			html_table_begin ('100%');
			html_form_begin ($GLOBALS['appname'].'/index.php?path='.$path);
			html_form_input ('hidden', 'edit', True);
			html_form_input ('hidden', 'edit_file', $fileman[$j]);

			###
			# We need to include all of the fileman entries for each file's form,
			# so we loop through again
			###

			for ($i = 0; $i != $numoffiles; $i++)
			{
				html_form_input ('hidden', 'fileman['.$i.']', base64_encode ($fileman[$i]));
			}

			html_table_row_begin ();
			html_table_col_begin ();
			html_form_textarea ('edit_file_content', 35, 75, $content);
			html_table_col_end ();
			html_table_col_begin ('center');
			html_form_input ('submit', 'edit_preview', lang('Preview x', html_encode ($fileman[$j], 1)));
			html_break (1);
			html_form_input ('submit', 'edit_save', lang('Save x', html_encode ($fileman[$j], 1)));
//			html_break (1);
//			html_form_input ('submit', 'edit_save_all', lang('Save all'));
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

elseif ($op == 'upload' && $path != '/' && $path != $GLOBALS['fakebase'])
{
	for ($i = 0; $i != $show_upload_boxes; $i++)
	{
		if ($badchar = bad_chars ($upload_file_name[$i], True, True))
		{
			echo $GLOBALS['phpgw']->common->error_list (array (html_encode (lang('File names cannot contain "x"', $badchar), 1)));

			continue;
		}

		###
		# Check to see if the file exists in the database, and get its info at the same time
		###

		$ls_array = $GLOBALS['phpgw']->vfs->ls (array (
				'string'	=> $path . '/' . $upload_file_name[$i],
				'relatives'	=> array (RELATIVE_NONE),
				'checksubdirs'	=> False,
				'nofiles'	=> True
			)
		);

		$fileinfo = $ls_array[0];

		if ($fileinfo['name'])
		{
			if ($fileinfo['mime_type'] == 'Directory')
			{
				echo $GLOBALS['phpgw']->common->error_list (array (lang('Cannot replace x because it is a directory', $fileinfo['name'])));
				continue;
			}
		}

		if ($upload_file_size[$i] > 0)
		{
			if ($fileinfo['name'] && $fileinfo['deleteable'] != 'N')
			{
				$GLOBALS['phpgw']->vfs->set_attributes (array (
						'string'	=> $upload_file_name[$i],
						'relatives'	=> array (RELATIVE_ALL),
						'attributes'	=> array (
									'owner_id' => $GLOBALS['userinfo']['username'],
									'modifiedby_id' => $GLOBALS['userinfo']['username'],
									'modified' => $now,
									'size' => $upload_file_size[$i],
									'mime_type' => $upload_file_type[$i],
									'deleteable' => 'Y',
									'comment' => stripslashes ($upload_comment[$i])
								)
					)
				);

				$GLOBALS['phpgw']->vfs->cp (array (
						'from'	=> $upload_file[$i],
						'to'	=> $upload_file_name[$i],
						'relatives'	=> array (RELATIVE_NONE|VFS_REAL, RELATIVE_ALL)
					)
				);

				html_text_summary (lang('Replaced x', $disppath.'/'.$upload_file_name[$i]), $upload_file_size[$i]);
			}
			else
			{
				$GLOBALS['phpgw']->vfs->cp (array (
						'from'	=> $upload_file[$i],
						'to'	=> $upload_file_name[$i],
						'relatives'	=> array (RELATIVE_NONE|VFS_REAL, RELATIVE_ALL)
					)
				);

				$GLOBALS['phpgw']->vfs->set_attributes (array (
						'string'	=> $upload_file_name[$i],
						'relatives'	=> array (RELATIVE_ALL),
						'attributes'	=> array (
									'mime_type' => $upload_file_type[$i],
									'comment' => stripslashes ($upload_comment[$i])
								)
					)
				);

				html_text_summary (lang('Created x', $disppath.'/'.$upload_file_name[$i]), $upload_file_size[$i]);
			}
		}
		elseif ($upload_file_name[$i])
		{
			$GLOBALS['phpgw']->vfs->touch (array (
					'string'	=> $upload_file_name[$i],
					'relatives'	=> array (RELATIVE_ALL)
				)
			);

			$GLOBALS['phpgw']->vfs->set_attributes (array (
					'string'	=> $upload_file_name[$i],
					'relatives'	=> array (RELATIVE_ALL),
					'attributes'	=> array (
								'mime_type' => $upload_file_type[$i],
								'comment' => $upload_comment[$i]
							)
				)
			);

			html_text_summary (lang('Created x', $disppath.'/'.$upload_file_name[$i]), $file_size[$i]);
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
			echo $GLOBALS['phpgw']->common->error_list (array (html_text_italic ($file, 1) . html_encode (': ' . lang('Comments cannot contain "x"', $badchar), 1)));
			continue;
		}

		$GLOBALS['phpgw']->vfs->set_attributes (array (
				'string'	=> $file,
				'relatives'	=> array (RELATIVE_ALL),
				'attributes'	=> array (
							'comment' => stripslashes ($comment_files[$file])
						)
			)
		);

		html_text_summary (lang('Updated comment for x', $path.'/'.$file));
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
			echo $GLOBALS['phpgw']->common->error_list (array (html_encode (lang('File names cannot contain "x"', $badchar), 1)));
			continue;
		}

		if (ereg ("/", $to) || ereg ("\\\\", $to))
		{
			echo $GLOBALS['phpgw']->common->error_list (array (lang("File names cannot contain \\ or /")));
		}
		elseif (!$GLOBALS['phpgw']->vfs->mv (array (
					'from'	=> $from,
					'to'	=> $to
			))
		)
		{
			echo $GLOBALS['phpgw']->common->error_list (array (lang('Could not rename x to x', $disppath.'/'.$from, $disppath.'/'.$to)));
		}
		else 
		{
			html_text_summary (lang('Renamed x to x', $disppath.'/'.$from, $disppath.'/'.$to));
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
		if ($GLOBALS['phpgw']->vfs->mv (array (
				'from'	=> $file,
				'to'	=> $todir . '/' . $file,
				'relatives'	=> array (RELATIVE_ALL, RELATIVE_NONE)
			))
		)
		{
			$moved++;
			html_text_summary (lang('Moved x to x', $disppath.'/'.$file, $todir.'/'.$file));
		}
		else
		{
			echo $GLOBALS['phpgw']->common->error_list (array (lang('Could not move x to x', $disppath.'/'.$file, $todir.'/'.$file)));
		}
	}

	if ($moved)
	{
		html_break (2);
		html_link ($GLOBALS['appname'].'/index.php?path='.$todir, lang('Go to x', $todir));
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
		if ($GLOBALS['phpgw']->vfs->cp (array (
				'from'	=> $file,
				'to'	=> $todir . '/' . $file,
				'relatives'	=> array (RELATIVE_ALL, RELATIVE_NONE)
			))
		)
		{
			$copied++;
			html_text_summary (lang('Copied x to x', $disppath.'/'.$file, $todir.'/'.$file));
		}
		else
		{
			echo $GLOBALS['phpgw']->common->error_list (array (lang('Could not copy x to x', $disppath.'/'.$file, $todir.'/'.$file)));
		}
	}

	if ($copied)
	{
		html_break (2);
		html_link ($GLOBALS['appname'].'/index.php?path='.$todir, lang('Go to x', $todir));
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
			if ($GLOBALS['phpgw']->vfs->delete (array ('string' => $fileman[$i])))
			{
				html_text_summary (lang('Deleted x', $disppath.'/'.$fileman[$i]), $fileinfo['size']);
			}
			else
			{
				$GLOBALS['phpgw']->common->error_list (array (lang('Could not delete x', $disppath.'/'.$fileman[$i])));
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
		echo $GLOBALS['phpgw']->common->error_list (array (html_encode (lang('Directory names cannot contain "x"', $badchar), 1)));
		html_break (2);
		html_link_back ();
		html_page_close ();
	}
	
	if ($createdir[strlen($createdir)-1] == ' ' || $createdir[0] == ' ')
	{
		echo $GLOBALS['phpgw']->common->error_list (array (lang('Cannot create directory because it begins or ends in a space')));
		html_break (2);
		html_link_back ();
		html_page_close ();
	}

	$ls_array = $GLOBALS['phpgw']->vfs->ls (array (
				'string'	=> $path . '/' . $createdir,
				'relatives'	=> array (RELATIVE_NONE),
				'checksubdirs'	=> False,
				'nofiles'	=> True
		)
	);

	$fileinfo = $ls_array[0];

	if ($fileinfo['name'])
	{
		if ($fileinfo['mime_type'] != 'Directory')
		{
			echo $GLOBALS['phpgw']->common->error_list (array (lang('x already exists as a file', $fileinfo['name'])));
			html_break (2);
			html_link_back ();
			html_page_close ();
		}
		else
		{
			echo $GLOBALS['phpgw']->common->error_list (array (lang('Directory x already exists', $fileinfo['name'])));
			html_break (2);
			html_link_back ();
			html_page_close ();
		}
	}
	else
	{
		if ($GLOBALS['phpgw']->vfs->mkdir (array ('string' => $createdir)))
		{
			html_text_summary (lang('Created directory x', $disppath.'/'.$createdir));
			html_break (2);
			html_link ($GLOBALS['appname'].'/index.php?path='.$disppath.'/'.$createdir, lang('Go to x', $disppath.'/'.$createdir));
		}
		else
		{
			echo $GLOBALS['phpgw']->common->error_list (array (lang('Could not create x', $disppath.'/'.$createdir)));
		}
	}

	html_break (2);
	html_link_back ();
}

html_page_close ();

?>
