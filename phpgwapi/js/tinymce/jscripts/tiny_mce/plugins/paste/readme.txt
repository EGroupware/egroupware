 Paste plugin for TinyMCE
------------------------------

This plugin adds paste as plain text and paste from Word icons to TinyMCE. This plugin was developed by Ryan Demmer and modified by
the TinyMCE crew to be more general and some extra features where added.

On 25 May 2005, this plugin was modified by speednet:  IE now pastes directly into the editor, bypassing the extra steps of opening the Insert box, selecting options, and clicking Insert.  Speednet also added the Select All command, which highlights all the content in the editor when the user clicks the toolbar button.  (Other miscellaneous cleanup also.)


Installation instructions:
  * Add plugin to TinyMCE plugin option list example: plugins : "paste".
  * Add the plaintext button name to button list, example: theme_advanced_buttons3_add : "pastetext,pasteword,selectall".

Initialization example:
  tinyMCE.init({
    theme : "advanced",
    mode : "textareas",
    plugins : "paste",
    theme_advanced_buttons3_add : "pastetext,pasteword,selectall",
    paste_create_paragraphs : false,
    paste_use_dialog : true
  });

Options:
 [paste_create_paragraphs] - If enabled double linefeeds are converted to paragraph
                             elements when using the plain text dialog. This is enabled by default.
 [paste_use_dialog]        - MSIE specific option, if you set this to false both Mozilla and MSIE will present a paste dialog.
                             if you set it to true pasting in MSIE will be done directly. This option is set to false by default.
