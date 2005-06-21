 Directionality plugin for TinyMCE
------------------------------

This plugin adds directionality icons to TinyMCE that enables TinyMCE to better handle languages that is written from right to left.

Installation instructions:
  * Add plugin to TinyMCE plugin option list example: plugins : "directionality".
  * Add the ltr, rtl button names to button list, example: theme_advanced_buttons3_add : "ltr,rtl".

Initialization example:
  tinyMCE.init({
    theme : "advanced",
    mode : "textareas",
    plugins : "directionality",
    theme_advanced_buttons3_add : "ltr,rtl"
  });
