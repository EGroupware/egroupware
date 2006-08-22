/* Import theme specific language pack */
tinyMCE.importPluginLanguagePack('filemanager', 'en');

function TinyMCE_filemanager_getControlHTML(control_name) {
    switch (control_name) {
        case "filemanager":
            return '<img id="{$editor_id}_filemanager" src="{$pluginurl}/images/filemanager.png" title="{$lang_insert_filemanager}" width="20" height="20" class="mceButtonNormal" onmouseover="tinyMCE.switchClass(this,\'mceButtonOver\');" onmouseout="tinyMCE.restoreClass(this);" onmousedown="tinyMCE.restoreAndSwitchClass(this,\'mceButtonDown\');" onclick="tinyMCE.execInstanceCommand(\'{$editor_id}\',\'mceFilemanager\');" />';
    }
    return "";
}

/**
 * Executes the mceFilemanager command.
 */
function TinyMCE_filemanager_execCommand(editor_id, element, command, user_interface, value) {
    // Handle commands
    switch (command) {
        case "mceFilemanager":
            var template = new Array();
            template['file']   = '../../plugins/filemanager/InsertFile/insert_file.php'; // Relative to theme
            template['width']  = 660;
            template['height'] = 500;

            tinyMCE.openWindow(template, {editor_id : editor_id});
       return true;
   }
   // Pass to next handler in chain
   return false;
}


