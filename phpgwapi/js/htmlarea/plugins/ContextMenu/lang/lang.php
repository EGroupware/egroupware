<?php
/**************************************************************************\
* eGroupWare - API htmlarea translations (according to lang in user prefs) *
* http: //www.eGroupWare.org                                                *
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

include('../../../../../../header.inc.php');
header('Content-type: text/javascript; charset='.$GLOBALS['phpgw']->translation->charset());
$GLOBALS['phpgw']->translation->add_app('htmlarea-ContextMenu');

// I18N constants

// LANG:  "en", ENCODING: UTF-8 | ISO-8859-1
// Author: Mihai Bazon, http://dynarch.com/mishoo

// FOR TRANSLATORS:
//
//   1. PLEASE PUT YOUR CONTACT INFO IN THE ABOVE LINE
//      (at least a valid email address)
//
//   2. PLEASE TRY TO USE UTF-8 FOR ENCODING;
//      (if this is not possible, please include a comment
//       that states what encoding is necessary.)
?>
ContextMenu.I18N = {
	// Items that appear in menu.  Please note that an underscore (_)
	// character in the translation (right column) will cause the following
	// letter to become underlined and be shortcut for that menu option.

	"Cut"                                                   :  "<?php echo lang('Cut'); ?>",
	"Copy"                                                  :  "<?php echo lang('Copy'); ?>",
	"Paste"                                                 :  "<?php echo lang('Paste'); ?>",
	"Image Properties"                                      :  "<?php echo lang('_Image Properties...'); ?>",
	"Modify Link"                                           :  "<?php echo lang('_Modify Link...'); ?>",
	"Check Link"                                            :  "<?php echo lang('Chec_k Link...'); ?>",
	"Remove Link"                                           :  "<?php echo lang('_Remove Link...'); ?>",
	"Cell Properties"                                       :  "<?php echo lang('C_ell Properties...'); ?>",
	"Row Properties"                                        :  "<?php echo lang('Ro_w Properties...'); ?>",
	"Insert Row Before"                                     :  "<?php echo lang('I_nsert Row Before'); ?>",
	"Insert Row After"                                      :  "<?php echo lang('In_sert Row After'); ?>",
	"Delete Row"                                            :  "<?php echo lang('_Delete Row'); ?>",
	"Table Properties"                                      :  "<?php echo lang('_Table Properties...'); ?>",
	"Insert Column Before"                                  :  "<?php echo lang('Insert _Column Before'); ?>",
	"Insert Column After"                                   :  "<?php echo lang('Insert C_olumn After'); ?>",
	"Delete Column"                                         :  "<?php echo lang('De_lete Column'); ?>",
	"Justify Left"                                          :  "<?php echo lang('Justify Left'); ?>",
	"Justify Center"                                        :  "<?php echo lang('Justify Center'); ?>",
	"Justify Right"                                         :  "<?php echo lang('Justify Right'); ?>",
	"Justify Full"                                          :  "<?php echo lang('Justify Full'); ?>",
	"Make link"                                             :  "<?php echo lang('Make lin_k...'); ?>",
	"Remove the"                                            :  "<?php echo lang('Remove the'); ?>",
	"Element"                                               :  "<?php echo lang('Element...'); ?>",

	// Other labels (tooltips and alert/confirm box messages)

	"Please confirm that you want to remove this element:"  :  "<?php echo lang('Please confirm that you want to remove this element:'); ?>",
	"Remove this node from the document"                    :  "<?php echo lang('Remove this node from the document'); ?>",
	"How did you get here? (Please report!)"                :  "<?php echo lang('How did you get here? (Please report!)'); ?>",
	"Show the image properties dialog"                      :  "<?php echo lang('Show the image properties dialog'); ?>",
	"Modify URL"                                            :  "<?php echo lang('Modify URL'); ?>",
	"Current URL is"                                        :  "<?php echo lang('Current URL is'); ?>",
	"Opens this link in a new window"                       :  "<?php echo lang('Opens this link in a new window'); ?>",
	"Please confirm that you want to unlink this element."  :  "<?php echo lang('Please confirm that you want to unlink this element.'); ?>",
	"Link points to:"                                       :  "<?php echo lang('Link points to:'); ?>",
	"Unlink the current element"                            :  "<?php echo lang('Unlink the current element'); ?>",
	"Show the Table Cell Properties dialog"                 :  "<?php echo lang('Show the Table Cell Properties dialog'); ?>",
	"Show the Table Row Properties dialog"                  :  "<?php echo lang('Show the Table Row Properties dialog'); ?>",
	"Insert a new row before the current one"               :  "<?php echo lang('Insert a new row before the current one'); ?>",
	"Insert a new row after the current one"                :  "<?php echo lang('Insert a new row after the current one'); ?>",
	"Delete the current row"                                :  "<?php echo lang('Delete the current row'); ?>",
	"Show the Table Properties dialog"                      :  "<?php echo lang('Show the Table Properties dialog'); ?>",
	"Insert a new column before the current one"            :  "<?php echo lang('Insert a new column before the current one'); ?>",
	"Insert a new column after the current one"             :  "<?php echo lang('Insert a new column after the current one'); ?>",
	"Delete the current column"                             :  "<?php echo lang('Delete the current column'); ?>",
	"Create a link"                                         :  "<?php echo lang('Create a link'); ?>"
};
