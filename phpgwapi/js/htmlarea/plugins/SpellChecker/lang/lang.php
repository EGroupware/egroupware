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
$GLOBALS['phpgw']->translation->add_app('htmlarea-SpellChecker');

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
SpellChecker.I18N = {
	"CONFIRM_LINK_CLICK"                    :  "<?php echo lang('Please confirm that you want to open this link'); ?>",
	"Cancel"                                :  "<?php echo lang('Cancel'); ?>",
	"Dictionary"                            :  "<?php echo lang('Dictionary'); ?>",
	"Finished list of mispelled words"      :  "<?php echo lang('Finished list of mispelled words'); ?>",
	"I will open it in a new page."         :  "<?php echo lang('I will open it in a new page.'); ?>",
	"Ignore all"                            :  "<?php echo lang('Ignore all'); ?>",
	"Ignore"                                :  "<?php echo lang('Ignore'); ?>",
	"NO_ERRORS"                             :  "<?php echo lang('No mispelled words found with the selected dictionary.'); ?>",
	"NO_ERRORS_CLOSING"                     :  "<?php echo lang('Spell check complete, didn\'t find any mispelled words.  Closing now...'); ?>",
	"OK"                                    :  "<?php echo lang('OK'); ?>",
	"Original word"                         :  "<?php echo lang('Original word'); ?>",
	"Please wait.  Calling spell checker."  :  "<?php echo lang('Please wait.  Calling spell checker.'); ?>",
	"Please wait: changing dictionary to"   :  "<?php echo lang('Please wait: changing dictionary to'); ?>",
	"QUIT_CONFIRMATION"                     :  "<?php echo lang('This will drop changes and quit spell checker.  Please confirm.'); ?>",
	"Re-check"                              :  "<?php echo lang('Re-check'); ?>",
	"Replace all"                           :  "<?php echo lang('Replace all'); ?>",
	"Replace with"                          :  "<?php echo lang('Replace with'); ?>",
	"Replace"                               :  "<?php echo lang('Replace'); ?>",
	"Revert"                                :  "<?php echo lang('Revert'); ?>",
	"SC-spell-check"                        :  "<?php echo lang('Spell-check'); ?>",
	"Suggestions"                           :  "<?php echo lang('Suggestions'); ?>",
	"pliz weit ;-)"                         :  "<?php echo lang('pliz weit ;-)'); ?>"
};
