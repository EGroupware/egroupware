<?php
/**************************************************************************\
* eGroupWare - API htmlarea translations (according to lang in user prefs) *
* http://www.eGroupWare.org                                                *
* Modified by Ralf Becker <RalfBecker@outdoor-training.de>                 *
* This file is derived from htmlareas's lang/en.js file                    *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

$GLOBALS['phpgw_info']['flags'] = Array(
	'currentapp'  => 'home',		// can't be phpgwapi, nor htmlarea (no own directory)
	'noheader'    => True,
	'nonavbar'    => True,
	'noappheader' => True,
	'noappfooter' => True,
	'nofooter'    => True,
	'nocachecontrol' => True			// allow cacheing
);

include('../../header.inc.php');
$GLOBALS['phpgw']->translation->add_app('htmlarea');

// I18N constants

// LANG: "en", ENCODING: UTF-8 | ISO-8859-1
// Author: Mihai Bazon, <mishoo@infoiasi.ro>

// FOR TRANSLATORS:
//
//   1. PLEASE PUT YOUR CONTACT INFO IN THE ABOVE LINE
//      (at least a valid email address)
//
//   2. PLEASE TRY TO USE UTF-8 FOR ENCODING;
//      (if this is not possible, please include a comment
//       that states what encoding is necessary.)
?>
HTMLArea.I18N = {

	// the following should be the filename without .js extension
	// it will be used for automatically load plugin language.
	lang: "<?php echo $GLOBALS['phpgw_info']['user']['preferences']['common']['lang']; ?>",

	tooltips: {
		bold:           "<?php echo lang('Bold'); ?>",
		italic:         "<?php echo lang('Italic'); ?>",
		underline:      "<?php echo lang('Underline'); ?>",
		strikethrough:  "<?php echo lang('Strikethrough'); ?>",
		subscript:      "<?php echo lang('Subscript'); ?>",
		superscript:    "<?php echo lang('Superscript'); ?>",
		justifyleft:    "<?php echo lang('Justify Left'); ?>",
		justifycenter:  "<?php echo lang('Justify Center'); ?>",
		justifyright:   "<?php echo lang('Justify Right'); ?>",
		justifyfull:    "<?php echo lang('Justify Full'); ?>",
		insertorderedlist:    "<?php echo lang('Ordered List'); ?>",
		insertunorderedlist:  "<?php echo lang('Bulleted List'); ?>",
		outdent:        "<?php echo lang('Decrease Indent'); ?>",
		indent:         "<?php echo lang('Increase Indent'); ?>",
		forecolor:      "<?php echo lang('Font Color'); ?>",
		hilitecolor:    "<?php echo lang('Background Color'); ?>",
		inserthorizontalrule: "<?php echo lang('Horizontal Rule'); ?>",
		createlink:     "<?php echo lang('Insert Web Link'); ?>",
		insertimage:    "<?php echo lang('Insert Image'); ?>",
		inserttable:    "<?php echo lang('Insert Table'); ?>",
		htmlmode:       "<?php echo lang('Toggle HTML Source'); ?>",
		popupeditor:    "<?php echo lang('Enlarge Editor'); ?>",
		about:          "<?php echo lang('About this editor'); ?>",
		showhelp:       "<?php echo lang('Help using editor'); ?>",
		textindicator:  "<?php echo lang('Current style'); ?>",
		undo:           "<?php echo lang('Undoes your last action'); ?>",
		redo:           "<?php echo lang('Redoes your last action'); ?>",
		cut:            "<?php echo lang('Cut selection'); ?>",
		copy:           "<?php echo lang('Copy selection'); ?>",
		paste:          "<?php echo lang('Paste from clipboard'); ?>"
	},

	buttons: {
		"ok":           "<?php echo lang('OK'); ?>",
		"cancel":       "<?php echo lang('Cancel'); ?>"
	},

	msg: {
		"Path":         "<?php echo lang('Path'); ?>",
		"TEXT_MODE":    "<?php echo lang('You are in TEXT MODE.  Use the [<>] button to switch back to WYSIWIG.'); ?>"
	}
};
