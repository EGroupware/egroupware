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
$GLOBALS['phpgw']->translation->add_app('htmlarea-TableOperations');

// I18N constants

// LANG: "en", ENCODING: UTF-8 | ISO-8859-1
// Author: Mihai Bazon, http: //dynarch.com/mishoo

// FOR TRANSLATORS: 
//
//   1. PLEASE PUT YOUR CONTACT INFO IN THE ABOVE LINE
//      (at least a valid email address)
//
//   2. PLEASE TRY TO USE UTF-8 FOR ENCODING;
//      (if this is not possible, please include a comment
//       that states what encoding is necessary.)
?>
TableOperations.I18N = {
	"Align": "<?php echo lang('Align'); ?>",
	"All four sides": "<?php echo lang('All four sides'); ?>",
	"Background": "<?php echo lang('Background'); ?>",
	"Baseline": "<?php echo lang('Baseline'); ?>",
	"Border": "<?php echo lang('Border'); ?>",
	"Borders": "<?php echo lang('Borders'); ?>",
	"Bottom": "<?php echo lang('Bottom'); ?>",
	"CSS Style": "<?php echo lang('Style [CSS]'); ?>",
	"Caption": "<?php echo lang('Caption'); ?>",
	"Cell Properties": "<?php echo lang('Cell Properties'); ?>",
	"Center": "<?php echo lang('Center'); ?>",
	"Char": "<?php echo lang('Char'); ?>",
	"Collapsed borders": "<?php echo lang('Collapsed borders'); ?>",
	"Color": "<?php echo lang('Color'); ?>",
	"Description": "<?php echo lang('Description'); ?>",
	"FG Color": "<?php echo lang('FG Color'); ?>",
	"Float": "<?php echo lang('Float'); ?>",
	"Frames": "<?php echo lang('Frames'); ?>",
	"Height": "<?php echo lang('Height'); ?>",
	"How many columns would you like to merge?": "<?php echo lang('How many columns would you like to merge?'); ?>",
	"How many rows would you like to merge?": "<?php echo lang('How many rows would you like to merge?'); ?>",
	"Image URL": "<?php echo lang('Image URL'); ?>",
	"Justify": "<?php echo lang('Justify'); ?>",
	"Layout": "<?php echo lang('Layout'); ?>",
	"Left": "<?php echo lang('Left'); ?>",
	"Margin": "<?php echo lang('Margin'); ?>",
	"Middle": "<?php echo lang('Middle'); ?>",
	"No rules": "<?php echo lang('No rules'); ?>",
	"No sides": "<?php echo lang('No sides'); ?>",
	"None": "<?php echo lang('None'); ?>",
	"Padding": "<?php echo lang('Padding'); ?>",
	"Please click into some cell": "<?php echo lang('Please click into some cell'); ?>",
	"Right": "<?php echo lang('Right'); ?>",
	"Row Properties": "<?php echo lang('Row Properties'); ?>",
	"Rules will appear between all rows and columns": "<?php echo lang('Rules will appear between all rows and columns'); ?>",
	"Rules will appear between columns only": "<?php echo lang('Rules will appear between columns only'); ?>",
	"Rules will appear between rows only": "<?php echo lang('Rules will appear between rows only'); ?>",
	"Rules": "<?php echo lang('Rules'); ?>",
	"Spacing and padding": "<?php echo lang('Spacing and padding'); ?>",
	"Spacing": "<?php echo lang('Spacing'); ?>",
	"Summary": "<?php echo lang('Summary'); ?>",
	"TO-cell-delete": "<?php echo lang('Delete cell'); ?>",
	"TO-cell-insert-after": "<?php echo lang('Insert cell after'); ?>",
	"TO-cell-insert-before": "<?php echo lang('Insert cell before'); ?>",
	"TO-cell-merge": "<?php echo lang('Merge cells'); ?>",
	"TO-cell-prop": "<?php echo lang('Cell properties'); ?>",
	"TO-cell-split": "<?php echo lang('Split cell'); ?>",
	"TO-col-delete": "<?php echo lang('Delete column'); ?>",
	"TO-col-insert-after": "<?php echo lang('Insert column after'); ?>",
	"TO-col-insert-before": "<?php echo lang('Insert column before'); ?>",
	"TO-col-split": "<?php echo lang('Split column'); ?>",
	"TO-row-delete": "<?php echo lang('Delete row'); ?>",
	"TO-row-insert-above": "<?php echo lang('Insert row before'); ?>",
	"TO-row-insert-under": "<?php echo lang('Insert row after'); ?>",
	"TO-row-prop": "<?php echo lang('Row properties'); ?>",
	"TO-row-split": "<?php echo lang('Split row'); ?>",
	"TO-table-prop": "<?php echo lang('Table properties'); ?>",
	"Table Properties": "<?php echo lang('Table Properties'); ?>",
	"Text align": "<?php echo lang('Text align'); ?>",
	"The bottom side only": "<?php echo lang('The bottom side only'); ?>",
	"The left-hand side only": "<?php echo lang('The left-hand side only'); ?>",
	"The right and left sides only": "<?php echo lang('The right and left sides only'); ?>",
	"The right-hand side only": "<?php echo lang('The right-hand side only'); ?>",
	"The top and bottom sides only": "<?php echo lang('The top and bottom sides only'); ?>",
	"The top side only": "<?php echo lang('The top side only'); ?>",
	"Top": "<?php echo lang('Top'); ?>",	
	"Unset color": "<?php echo lang('Unset color'); ?>",
	"Vertical align": "<?php echo lang('Vertical align'); ?>",
	"Width": "<?php echo lang('Width'); ?>",
	"not-del-last-cell": "<?php echo lang('HTMLArea cowardly refuses to delete the last cell in row.'); ?>",
	"not-del-last-col": "<?php echo lang('HTMLArea cowardly refuses to delete the last column in table.'); ?>",
	"not-del-last-row": "<?php echo lang('HTMLArea cowardly refuses to delete the last row in table.'); ?>",
	"percent": "<?php echo lang('percent'); ?>",
	"pixels": "<?php echo lang('pixels'); ?>"
};
