 file Manager plugin for TinyMCE
---------------------------------

Installation instructions:
  * Copy the ibrowser directory to the plugins directory of TinyMCE (/jscripts/tiny_mce/plugins).
  * Add plugin to TinyMCE plugin option list example: plugins : "filemanager".
  * Add the ibrowser button name to button list, example: theme_advanced_buttons3_add : "filemanager".
  * Modify the ..../jscripts/tiny_mce/plugins/filemanager/insertfile/config.inc.php

Configuration example:
$MY_DOCUMENT_ROOT     = 'C:/appserv/www/tinymce142/resource/insfile'; //* system path to the directory you want to manage the files and folders
$MY_BASE_URL          = "http://localhost/tinymce142/resource/insfile";
$MY_URL_TO_OPEN_FILE  = "http://localhost/tinymce142/resource/insfile"; 
$MY_ALLOW_EXTENSIONS = array('html', 'doc', 'xls', 'txt', 'gif', 'jpeg', 'jpg', 'png', 'pdf', 'zip', 'pdf');
$MY_DENY_EXTENSIONS  = array('php', 'php3', 'php4', 'phtml', 'shtml', 'cgi', 'pl');
$MY_LIST_EXTENSIONS  = array('html', 'doc', 'xls', 'txt', 'gif', 'jpeg', 'jpg', 'png', 'pdf', 'zip', 'pdf');
$MY_ALLOW_CREATE     = true; // Boolean (false or true) whether creating folders is allowed or not.
$MY_ALLOW_DELETE     = true; // Boolean (false or true) whether deleting files and folders is allowed or not.
$MY_ALLOW_RENAME     = true; // Boolean (false or true) whether renaming files and folders is allowed or not.
$MY_ALLOW_MOVE       = true; // Boolean (false or true) whether moving files and folders is allowed or not.
$MY_ALLOW_UPLOAD     = true; // Boolean (false or true) whether uploading files is allowed or not.



Initialization example:
  tinyMCE.init({
    theme : "advanced",
    elements: "ta",
    mode : "exact",
    plugins : "filemanager",
    theme_advanced_buttons3_add : "filemanager"
  });


