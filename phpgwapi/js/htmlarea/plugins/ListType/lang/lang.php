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
$GLOBALS['phpgw']->translation->add_app('htmlarea-ListType');

// I18N constants

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
ListType.I18N = {
	"Decimal"                :    "<?php echo lang('Decimal numbers'); ?>",
	"Lower roman"            :    "<?php echo lang('Lower roman numbers'); ?>",
	"Upper roman"            :    "<?php echo lang('Upper roman numbers'); ?>",
	"Lower latin"            :    "<?php echo lang('Lower latin letters'); ?>",
	"Upper latin"            :    "<?php echo lang('Upper latin letters'); ?>",
	"Lower greek"            :    "<?php echo lang('Lower greek letters'); ?>",
	"ListStyleTooltip"       :    "<?php echo lang('Choose list style type (for ordered lists)'); ?>"
};
