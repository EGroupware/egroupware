<?php

error_reporting (4);

if (@!is_object($GLOBALS['phpgw']->vfs))
{
	$GLOBALS['phpgw']->vfs = CreateObject ('phpgwapi.vfs');
}

### Start Configuration Options ###
### These are automatically set in phpGW - do not edit ###

$sep = SEP;
$GLOBALS['rootdir'] = $GLOBALS['phpgw']->vfs->basedir;
$GLOBALS['fakebase'] = $GLOBALS['phpgw']->vfs->fakebase;
$GLOBALS['appname'] = $GLOBALS['phpgw_info']['flags']['currentapp'];
$GLOBALS['settings'] = $GLOBALS['phpgw_info']['user']['preferences'][$appname];

if (stristr ($GLOBALS['rootdir'], PHPGW_SERVER_ROOT))
{
	$GLOBALS['filesdir'] = substr ($GLOBALS['rootdir'], strlen (PHPGW_SERVER_ROOT));
}
else
{
	unset ($GLOBALS['filesdir']);
}

$GLOBALS['hostname'] = $GLOBALS['phpgw_info']['server']['webserver_url'] . $GLOBALS['filesdir'];

###
# Note that $userinfo["username"] is actually the id number, not the login name
###

$GLOBALS['userinfo']['username'] = $GLOBALS['phpgw_info']['user']['account_id'];
$GLOBALS['userinfo']['account_lid'] = $GLOBALS['phpgw']->accounts->id2name ($GLOBALS['userinfo']['username']);
$GLOBALS['userinfo']['hdspace'] = 10000000000;
$GLOBALS['homedir'] = $GLOBALS['fakebase'].'/'.$GLOBALS['userinfo']['account_lid'];

### End Configuration Options ###

if (!defined ('NULL'))
{
	define ('NULL', '');
}

require (PHPGW_APP_INC . '/db.inc.php');

/* Set up any initial db settings */
db_init ();

###
# Get user settings from database
###

/* We have to define these by hand in phpGW, or rely on it's templates */

define ('HTML_TABLE_FILES_HEADER_BG_COLOR', '');
define ('HTML_TABLE_FILES_HEADER_TEXT_COLOR', 'maroon');
define ('HTML_TABLE_FILES_COLUMN_HEADER_BG_COLOR', '');
define ('HTML_TABLE_FILES_COLUMN_HEADER_TEXT_COLOR', 'maroon');
define ('HTML_TABLE_FILES_BG_COLOR', '');
define ('HTML_TABLE_FILES_TEXT_COLOR', 'maroon');
define ('HTML_TEXT_ERROR_COLOR', 'red');
define ('HTML_TEXT_NAVIGATION_BACK_TO_USER', 'Back to file manager');

###
# Need to include this here so they recognize the settings
###

require (PHPGW_APP_INC . '/html.inc.php');

###
# Define the list of file attributes.  Format is "internal_name" => "Displayed name"
# This is used both by internally and externally for things like preferences
###

$file_attributes = Array(
	'name' => 'Filename',
	'mime_type' => 'MIME Type',
	'size' => 'Size',
	'created' => 'Created',
	'modified' => 'Modified',
	'owner' => 'Owner',
	'createdby_id' => 'Created by',
	'modifiedby_id' => 'Created by',
	'modifiedby_id' => 'Modified by',
	'app' => 'Application',
	'comment' => 'Comment',
	'version' => 'Version'
);

###
# Calculate and display B or KB
# And yes, that first if is strange, 
# but it does do something
###

function borkb ($size, $enclosed = NULL, $return = 0)
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
	
	return (eor ($rstring, $return));
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

	return trim ((eor ($rstring, $return)));
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
		html_text ($rstring . "\n");
		return (0);
	}
}

###
# URL encode a string
# First check if its a query string, then if its just a URL, then just encodes it all
# Note: this is a hack.  It was made to work with form actions, form values, and links only,
# but should be able to handle any normal query string or URL
###

function string_encode ($string, $return = False)
{
	if (preg_match ("/=(.*)(&|$)/U", $string))
	{
		$rstring = preg_replace ("/=(.*)(&|$)/Ue", "'=' . rawurlencode (base64_encode ('\\1')) . '\\2'", $string);
	}
	elseif (ereg ('^'.$GLOBALS['hostname'], $string))
	{
		$rstring = ereg_replace ('^'.$GLOBALS['hostname'].'/', '', $string);
		$rstring = preg_replace ("/(.*)(\/|$)/Ue", "rawurlencode (base64_encode ('\\1')) . '\\2'", $rstring);
		$rstring = $GLOBALS['hostname'].'/'.$rstring;
	}
	else
	{
		$rstring = rawurlencode ($string);

		/* Terrible hack, decodes all /'s back to normal */  
		$rstring = preg_replace ("/%2F/", '/', $rstring);
	}

	return (eor ($rstring, $return));
}

function string_decode ($string, $return = False)
{
	$rstring = rawurldecode ($string);

	return (eor ($rstring, $return));
}

###
# HTML encode a string
# This should be used with anything in an HTML tag that might contain < or >
###

function html_encode ($string, $return)
{
	$rstring = htmlspecialchars ($string);

	return (eor ($rstring, $return));
}

function translate ($text)
{
	return ($GLOBALS['phpgw']->lang($text));
}

$help_info = Array(
		array ("up", "The Up button takes you to the directory above the current directory.  For example, if you're in /home/jdoe/mydir, the Up button would take you to /home/jdoe."),
		array ("directory_name", "The name of the directory you're currently in."),
		array ("home", "The Home button takes you to your personal home directory."),
		array ("sort_by", "Click on any of the column headers to sort the list by that column."),
		array ("filename", "The name of the file or directory."),
		array ("mime_type", "The MIME-type of the file.  Examples include text/plain, text/html, image/jpeg.  The special MIME-type Directory is used for directories."),
		array ("size", "The size of the file or directory in the most convenient units: bytes (B), kilobytes (KB), megabytes (MB), gigabytes (GB).  Sizes for directories include subfiles and subdirectories."),
		array ("created", "When the file or directory was created."),
		array ("modified", "When the file or directory was last modified."),
		array ("owner", "The owner of the file or directory.  This can be a user or group name."),
		array ("created_by", "Displays who created the file or directory."),
		array ("modified_by", "Displays who last modified the file or directory."),
		array ("application", "The application associated with the file or directory.  Usually the application used to create it.  A blank application field is ok."),
		array ("comment", "The comment for the file or directory.  Comments can be set when creating the file or directory, and created or edited any time thereafter."),
		array ("version", "The current version for the file or directory.  Clicking on the version number will display a list of changes made to the file or directory."),
		array ("edit", "Edit the text of the selected file(s).  You can select more than one file; this is useful when you want to copy part of one file into another.  Clicking Preview will show you a preview of the file.  Click Save to save your changes."),
		array ("rename", "Rename the selected file(s).  You can select as many files or directories as you want.  You are presented with a text field to enter the new name of each file or directory."),
		array ("delete", "Delete the selected file(s).  You can select as many files or directories as you want.  When deleting directories, the entire directory and all of its contents are deleted.  You will not be prompted to make sure you want to delete the file(s); make sure you really want to delete them before clicking Delete."),
		array ("edit_comments", "Create a comment for a file or directory, or edit an existing comment.  You can select as many files or directories as you want."),
		array ("go_to", "The Go to button takes you to the directory selected in the drop down [directory_list|Directory List]."),
		array ("copy_to", "This will copy all selected files and directories to the directory selected in the drop down [directory_list|Directory List]."),
		array ("move_to", "This will move all selected files and directories to the directory selected in the drop down [directory_list|Directory List]."),
		array ("directory_list", "The Directory List contains a list of all directories you have (at least) read access to.  Selecting a directory and clicking one of the [go_to|Go to]/[copy_to|Copy to]/[move_to|Move to] buttons will perform the selected action on that directory.  For example, if you select \"/home/somegroup/reports\" from the Directory List, and click the \"[copy_to|Copy to]\" button, all selected files and directories will be copied to \"/home/somegroup/reports\"."),
		array ("download", "Download the first selected file to your local computer.  You can only download one file at a time.  Directories cannot be downloaded, only files."),
		array ("create_folder", "Creates a directory (folder == directory).  The name of the directory is specified in the text box next to the Create Folder button."),
		array ("create_file", "Creates a file in the current directory.  The name of the file is specified in the text box next to the Create File button.  After clicking the Create File button you will be presented with the [edit|Edit] screen, where you may edit the file you just created.  If you do not with to make any changes to the file at this time, simply click the Save button and the file will be saved as an empty file."),
		array ("command_line", "Enter a Unix-style command line here, which will be executed when the [execute|Execute] button is pressed.  If you don't know what this is, you probably should turn the option off in the Preferences."),
		array ("execute", "Clicking the Execute button will execute the Unix-style [command_line|command line] specified in the text box above.  If you don't know what this is, you probably should turn the option off in the Preferences."),
		array ("update", "Sync the database with the filesystem for the current directory.  This is useful if you use another interface to access the same files.  Any new files or directories in the current directory will be read in, and the attributes for the other files will be updated to reflect any changes to the filesystem.  Update is run automatically every few page loads (currently every 20 page loads as of this writing, but that may have changed by now)."),
		array ("file_stats", "Various statistics on the number and size of the files in the current directory.  In some situations, these reflect different statistics.  For example, when in / or the base directory."),
		array ("upload_file", "The full path of the local file to upload.  You can type it in or use the Browse.. button to select it.  The file will be uploaded to the current directory.  You cannot upload directories, only files."),
		array ("upload_comment", "The inital comment to use for the newly uploaded file.  Totally optional and completely arbitrary.  You can [edit_comments|create or edit the comment] at any time in the future."),
		array ("upload_files", "This will upload the files listed in the input boxes above, and store them in the current directory."),
		array ("show_upload_fields", "This setting determines how many [upload_files|upload fields] will be shown at once.  You can change the default number that will be shown in the [preferences].")
	);

?>
