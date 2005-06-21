 Fullscreen plugin for TinyMCE
------------------------------

This plugin adds fullscreen mode to TinyMCE.

Installation instructions:
  * Add plugin to TinyMCE plugin option list example: plugins : "fullscreen".
  * Add the fullscreen button name to button list, example: theme_advanced_buttons3_add : "fullscreen".

Initialization example:
  tinyMCE.init({
    theme : "advanced",
    mode : "textareas",
    plugins : "fullscreen",
    theme_advanced_buttons3_add : "fullscreen",
    plaintext_create_paragraphs : false
  });
