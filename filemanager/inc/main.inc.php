<?

### Start Configuration Options ###
### These are automatically set in phpGW - do not edit ###

$sep = $phpgw_info["server"]["dir_separator"];
$rootdir = $phpgw->vfs->basedir;
$fakebase = $phpgw->vfs->fakebase;
$hostname = $phpgw_info["server"]["webserver_url"] . $filesdir;
$appname = $phpgw_info["flags"]["currentapp"];
$settings = $phpgw_info["user"]["preferences"][$appname];

if (stristr ($rootdir, PHPGW_SERVER_ROOT))
{
	$filesdir = substr ($rootdir, strlen (PHPGW_SERVER_ROOT));
}
else
{
	unset ($filesdir);
}

### End Configuration Options ###

define ("NULL", "");

require ("./inc/db.inc.php");

/* Set up any initial db settings */
db_init ();

###
# Get user settings from database
###

/* We have to define these by hand in phpGW, or rely on it's templates */

define ('HTML_TABLE_FILES_HEADER_BG_COLOR', "");
define ('HTML_TABLE_FILES_HEADER_TEXT_COLOR', "maroon");
define ('HTML_TABLE_FILES_COLUMN_HEADER_BG_COLOR', "");
define ('HTML_TABLE_FILES_COLUMN_HEADER_TEXT_COLOR', "maroon");
define ('HTML_TABLE_FILES_BG_COLOR', "");
define ('HTML_TABLE_FILES_TEXT_COLOR', "maroon");
define ('HTML_TEXT_ERROR_COLOR', "red");
define ('HTML_TEXT_NAVIGATION_BACK_TO_USER', "Back to file manager");

###
# Need to include this here so they recognize the settings
###

require ("./inc/html.inc.php");

###
# Define the list of file attributes.  Format is "internal_name" => "Displayed name"
# This is used both by internally and externally for things like preferences
###

$file_attributes = array ("name" => "Filename", "mime_type" => "MIME Type", "size" => "Size", "created" => "Created", "modified" => "Modified", "owner" => "Owner", "createdby_id" => "Created by", "modifiedby_id" => "Created by", "modifiedby_id" => "Modified by", "app" => "Application", "comment" => "Comment");

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
		$left = "(";
		$right = ")";
	}

	if ($size < 1024)
		$rstring = $left . $size . "B" . $right;
	else
		$rstring = $left . round($size/1024) . "KB" . $right;
	
	return (eor ($rstring, $return));
}

###
# Check for and return the first unwanted character
###

function bad_chars ($string, $return = 0)
{
	if (preg_match("-([\\\|/|<|>|\"])-", $string, $badchars))
		$rstring = $badchars[1];

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

function string_encode ($string, $return)
{
	global $hostname;

	if (preg_match ("/=(.*)(&|$)/U", $string))
	{
		$rstring = preg_replace ("/=(.*)(&|$)/Ue", "'=' . rawurlencode ('\\1') . '\\2'", $string);
	}
	elseif (ereg ("^$hostname", $string))
	{
		$rstring = ereg_replace ("^$hostname/", "", $string);
		$rstring = preg_replace ("/(.*)(\/|$)/Ue", "rawurlencode ('\\1') . '\\2'", $rstring);
		$rstring = "$hostname/$rstring";
	}
	else
	{
		$rstring = rawurlencode ($string);

		/* Terrible hack, decodes all /'s back to normal */  
		$rstring = preg_replace ("/%2F/", "/", $rstring);
	}

	return (eor ($rstring, $return));
}

function string_decode ($string, $return)
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
	global $phpgw;

	return ($phpgw->lang ($text));
}

?>
