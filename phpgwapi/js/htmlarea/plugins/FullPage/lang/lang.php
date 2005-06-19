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
$GLOBALS['phpgw']->translation->add_app('htmlarea-FullPage');

// I18N for the FullPage plugin

// LANG: "en", ENCODING: UTF-8 | ISO-8859-1
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
FullPage.I18N = {
	"Alternate style-sheet:":		"<?php echo lang('Alternate style-sheet:'); ?>",
	"Background color:":			"<?php echo lang('Background color:'); ?>",
	"Cancel":				"<?php echo lang('Cancel'); ?>",
	"DOCTYPE:":				"<?php echo lang('DOCTYPE:'); ?>",
	"Document properties":			"<?php echo lang('Document properties'); ?>",
	"Document title:":			"<?php echo lang('Document title:'); ?>",
	"OK":					"<?php echo lang('OK'); ?>",
	"Primary style-sheet:":			"<?php echo lang('Primary style-sheet:'); ?>",
	"Text color:":				"<?php echo lang('Text color:'); ?>"
};
