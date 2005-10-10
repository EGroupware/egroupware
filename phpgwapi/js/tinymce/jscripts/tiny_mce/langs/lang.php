<?php
/**************************************************************************\
* eGroupWare - API tinymce translations (according to lang in user prefs)  *
* http: //www.eGroupWare.org                                               *
* Modified by Ralf Becker <RalfBecker@outdoor-training.de>                 *
* This file is derived from tinymce's langs/en.js file                     *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

$GLOBALS['egw_info']['flags'] = Array(
	'currentapp'  => 'home',		// can't be phpgwapi, nor htmlarea (no own directory)
	'noheader'    => True,
	'nonavbar'    => True,
	'noappheader' => True,
	'noappfooter' => True,
	'nofooter'    => True,
	'nocachecontrol' => True			// allow cacheing
);

include('../../../../../../header.inc.php');
header('Content-type: text/javascript; charset='.$GLOBALS['egw']->translation->charset());
$GLOBALS['egw']->translation->add_app('tinymce');
?>

// from langs/en.js
tinyMCELang['lang_bold_desc']     = '<?php echo lang('Bold'); ?>';
tinyMCELang['lang_italic_desc']   = '<?php echo lang('Italic'); ?>';
tinyMCELang['lang_underline_desc']   = '<?php echo lang('Underline'); ?>';
tinyMCELang['lang_striketrough_desc']   = '<?php echo lang('Striketrough'); ?>';
tinyMCELang['lang_justifyleft_desc']   = '<?php echo lang('Align left'); ?>';
tinyMCELang['lang_justifycenter_desc']   = '<?php echo lang('Align center'); ?>';
tinyMCELang['lang_justifyright_desc']   = '<?php echo lang('Align right'); ?>';
tinyMCELang['lang_justifyfull_desc']   = '<?php echo lang('Align full'); ?>';
tinyMCELang['lang_bullist_desc']   = '<?php echo lang('Unordered list'); ?>';
tinyMCELang['lang_numlist_desc']   = '<?php echo lang('Ordered list'); ?>';
tinyMCELang['lang_outdent_desc']   = '<?php echo lang('Outdent'); ?>';
tinyMCELang['lang_indent_desc']   = '<?php echo lang('Indent'); ?>';
tinyMCELang['lang_undo_desc']   = '<?php echo lang('Undo'); ?>';
tinyMCELang['lang_redo_desc']   = '<?php echo lang('Redo'); ?>';
tinyMCELang['lang_link_desc']   = '<?php echo lang('Insert/edit link'); ?>';
tinyMCELang['lang_unlink_desc']   = '<?php echo lang('Unlink'); ?>';
tinyMCELang['lang_image_desc']   = '<?php echo lang('Insert/edit image'); ?>';
tinyMCELang['lang_cleanup_desc']   = '<?php echo lang('Cleanup messy code'); ?>';
tinyMCELang['lang_focus_alert']   = '<?php echo lang('A editor instance must be focused before using this command.'); ?>';
tinyMCELang['lang_edit_confirm']   = '<?php echo lang('Do you want to use the WYSIWYG mode for this textarea?'); ?>';
tinyMCELang['lang_insert_link_title']   = '<?php echo lang('Insert/edit link'); ?>';
tinyMCELang['lang_insert']   = '<?php echo lang('Insert'); ?>';
tinyMCELang['lang_update']   = '<?php echo lang('Update'); ?>';
tinyMCELang['lang_cancel']   = '<?php echo lang('Cancel'); ?>';
tinyMCELang['lang_insert_link_url']   = '<?php echo lang('Link URL'); ?>';
tinyMCELang['lang_insert_link_target']   = '<?php echo lang('Target'); ?>';
tinyMCELang['lang_insert_link_target_same']   = '<?php echo lang('Open link in the same window'); ?>';
tinyMCELang['lang_insert_link_target_blank']   = '<?php echo lang('Open link in a new window'); ?>';
tinyMCELang['lang_insert_image_title']   = '<?php echo lang('Insert/edit image'); ?>';
tinyMCELang['lang_insert_image_src']   = '<?php echo lang('Image URL'); ?>';
tinyMCELang['lang_insert_image_alt']   = '<?php echo lang('Image description'); ?>';
tinyMCELang['lang_help_desc']   = '<?php echo lang('Help'); ?>';
<?php
//lang('bold.gif'),lang('italic.gif'),lang('underline.gif')
foreach(array('bold','italic','underline') as $style)
{
	$gif = lang($style . '.gif');
	if ($gif == $style.'.gif*') $gif = $style.'.gif';
	echo "tinyMCELang['lang_{$style}_img'] = '$gif';\n";
}
?>
tinyMCELang['lang_clipboard_msg']   = '<?php echo lang('Copy/Cut/Paste is not available in Mozilla and Firefox.\nDo you want more information about this issue?'); ?>';

// from plugins/advhr/langs/en.js
tinyMCELang['lang_insert_advhr_desc']     = '<?php echo lang('Insert / edit Horizontale Rule'); ?>';
tinyMCELang['lang_insert_advhr_width']    = '<?php echo lang('Width'); ?>';
tinyMCELang['lang_insert_advhr_size']     = '<?php echo lang('Height'); ?>';
tinyMCELang['lang_insert_advhr_noshade']  = '<?php echo lang('No shadow'); ?>';

// from plugins/advimage/langs/en.js
tinyMCELang['lang_insert_image_alt2']  = '<?php echo lang('Image title'); ?>';
tinyMCELang['lang_insert_image_onmousemove']  = '<?php echo lang('Alternative image'); ?>';
tinyMCELang['lang_insert_image_mouseover']  = '<?php echo lang('for mouse over'); ?>';
tinyMCELang['lang_insert_image_mouseout']  = '<?php echo lang('for mouse out'); ?>';

// from plugins/advlink/langs/en.js
tinyMCELang['lang_insert_link_target_same']  = '<?php echo lang('Open in this window / frame'); ?>';
tinyMCELang['lang_insert_link_target_parent']  = '<?php echo lang('Open in parent window / frame'); ?>';
tinyMCELang['lang_insert_link_target_top']  = '<?php echo lang('Open in top frame (replaces all frames)'); ?>';
tinyMCELang['lang_insert_link_target_blank']  = '<?php echo lang('Open in new window'); ?>';
tinyMCELang['lang_insert_link_target_named']  = '<?php echo lang('Open in the window'); ?>';
tinyMCELang['lang_insert_link_popup']  = '<?php echo lang('JS-Popup'); ?>';
tinyMCELang['lang_insert_link_popup_url']  = '<?php echo lang('Popup URL'); ?>';
tinyMCELang['lang_insert_link_popup_name']  = '<?php echo lang('Window name'); ?>';
tinyMCELang['lang_insert_link_popup_return']  = "<?php echo lang("insert 'return false'"); ?>";
tinyMCELang['lang_insert_link_popup_scrollbars']  = '<?php echo lang('Show scrollbars'); ?>';
tinyMCELang['lang_insert_link_popup_statusbar']  = '<?php echo lang('Show statusbar'); ?>';
tinyMCELang['lang_insert_link_popup_toolbar']  = '<?php echo lang('Show toolbars'); ?>';
tinyMCELang['lang_insert_link_popup_menubar']  = '<?php echo lang('Show menubar'); ?>';
tinyMCELang['lang_insert_link_popup_location']  = '<?php echo lang('Show locationbar'); ?>';
tinyMCELang['lang_insert_link_popup_resizable']  = '<?php echo lang('Make window resizable'); ?>';
tinyMCELang['lang_insert_link_popup_size']  = '<?php echo lang('Size'); ?>';
tinyMCELang['lang_insert_link_popup_position']  = '<?php echo lang('Position (X/Y)'); ?>';
tinyMCELang['lang_insert_link_popup_missingtarget']  = '<?php echo lang('Please insert a name for the target or choose another option.'); ?>';

// from plugins/directionality/langs/en.js
tinyMCELang['lang_directionality_ltr_desc']  = '<?php echo lang('Direction left to right'); ?>';
tinyMCELang['lang_directionality_rtl_desc']  = '<?php echo lang('Direction right to left'); ?>';

// from plugins/emotions/langs/en.js
tinyMCELang['lang_insert_emotions_title']  = '<?php echo lang('Insert emotion'); ?>';
tinyMCELang['lang_emotions_desc']  = '<?php echo lang('Emotions'); ?>';

// from plugins/flash/langs/en.js
tinyMCELang['lang_insert_flash']       = '<?php echo lang('Insert / edit Flash Movie'); ?>';
tinyMCELang['lang_insert_flash_file']  = '<?php echo lang('Flash-File (.swf)'); ?>';
tinyMCELang['lang_insert_flash_size']  = '<?php echo lang('Size'); ?>';
tinyMCELang['lang_insert_flash_list']  = '<?php echo lang('Flash files'); ?>';
tinyMCELang['lang_flash_props']  = '<?php echo lang('Flash properties'); ?>';

// from plugins/fullscreen/langs/en.js
tinyMCELang['lang_fullscreen_title']  = '<?php echo lang('Fullscreen mode'); ?>';
tinyMCELang['lang_fullscreen_desc']  = '<?php echo lang('Toggle fullscreen mode'); ?>';

// from plugins/iespell/langs/en.js
tinyMCELang['lang_iespell_desc']  = '<?php echo lang('Run spell checking'); ?>';
tinyMCELang['lang_iespell_download'] = '<?php echo lang('ieSpell not detected. Click OK to go to download page.'); ?>';

// from plugins/insertdate/langs/en.js
tinyMCELang['lang_insertdate_desc']  = '<?php echo lang('Insert date'); ?>';
tinyMCELang['lang_inserttime_desc']  = '<?php echo lang('Insert time'); ?>';
tinyMCELang['lang_inserttime_months_long'] = new Array(<?php // full month names
$GLOBALS['egw']->translation->add_app('jscalendar');
$monthnames = array('January','February','March','April','May','June','July','August','September','October','November','December');
foreach($monthnames as $n => $name)
{
	echo "\n \"".lang($name).'"'.($n < 11 ? ',' : '');
}
?>);
tinyMCELang['lang_inserttime_months_short'] = new Array(<?php // short month names
foreach($monthnames as $n => $name)
{
	$short = lang(substr($name,0,3));	// test if our lang-file have a translation for the english short with 3 chars
	if (substr($short,-1) == '*')		// else create one by truncating the full translation to x chars
	{
		$short = substr(lang($name),0,(int)lang('3 number of chars for month-shortcut'));
	}
	echo "\n \"".$short.'"'.($n < 11 ? ',' : '');
}
?>);
tinyMCELang['lang_inserttime_day_long'] = new Array(<?php // full day names
$daynames = array("Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday");
foreach($daynames as $n => $name)
{
	echo "\n \"".lang($name).'"'.($n < 7 ? ',' : '');
}
?>);
tinyMCELang['lang_inserttime_day_short'] = new Array(<?php // short month names
foreach($daynames as $n => $name)
{
	$short = lang(substr($name,0,3));	// test if our lang-file have a translation for the english short with 3 chars
	if (substr($short,-1) == '*')		// else create one by truncating the full translation to x chars
	{
		$short = substr(lang($name),0,(int)lang('3 number of chars for day-shortcut'));
	}
	echo "\n \"".$short.'"'.($n < 7 ? ',' : '');
}
?>);

// from plugins/paste/langs/en.js
tinyMCELang['lang_paste_text_desc']  = '<?php echo lang('Paste as Plain Text'); ?>';
tinyMCELang['lang_paste_text_title']  = '<?php echo lang('Use CTRL+V on your keyboard to paste the text into the window.'); ?>';
tinyMCELang['lang_paste_text_linebreaks']  = '<?php echo lang('Keep linebreaks'); ?>';
tinyMCELang['lang_paste_word_desc']  = '<?php echo lang('Paste from Word'); ?>';
tinyMCELang['lang_paste_word_title']  = '<?php echo lang('Use CTRL+V on your keyboard to paste the text into the window.'); ?>';
tinyMCELang['lang_selectall_desc']  = '<?php echo lang('Select All'); ?>';

// from plugins/preview/langs/en.js
tinyMCELang['lang_preview_desc']  = '<?php echo lang('Preview'); ?>';

// from plugins/print/langs/en.js
tinyMCELang['lang_print_desc']  = '<?php echo lang('Print'); ?>';

// from plugins/save/langs/en.js
tinyMCELang['lang_save_desc']  = '<?php echo lang('Save'); ?>'; 

// from plugins/searchreplace/langs/en.js
tinyMCELang['lang_searchreplace_search_desc']  = '<?php echo lang('Find'); ?>';
tinyMCELang['lang_searchreplace_searchnext_desc']  = '<?php echo lang('Find again'); ?>';
tinyMCELang['lang_searchreplace_replace_desc']  = '<?php echo lang('Find/Replace'); ?>';
tinyMCELang['lang_searchreplace_notfound']  = '<?php echo lang('The search has been compleated. The search string could not be found.'); ?>';
tinyMCELang['lang_searchreplace_search_title']  = '<?php echo lang('Find'); ?>';
tinyMCELang['lang_searchreplace_replace_title']  = '<?php echo lang('Find/Replace'); ?>';
tinyMCELang['lang_searchreplace_allreplaced']  = '<?php echo lang('All occurrences of the search string was replaced.'); ?>';
tinyMCELang['lang_searchreplace_findwhat']  = '<?php echo lang('Find what'); ?>';
tinyMCELang['lang_searchreplace_replacewith']  = '<?php echo lang('Replace with'); ?>';
tinyMCELang['lang_searchreplace_direction']  = '<?php echo lang('Direction'); ?>';
tinyMCELang['lang_searchreplace_up']  = '<?php echo lang('Up'); ?>';
tinyMCELang['lang_searchreplace_down']  = '<?php echo lang('Down'); ?>';
tinyMCELang['lang_searchreplace_case']  = '<?php echo lang('Match case'); ?>';
tinyMCELang['lang_searchreplace_findnext']  = '<?php echo lang('Find&nbsp;next'); ?>';
tinyMCELang['lang_searchreplace_replace']  = '<?php echo lang('Replace'); ?>';
tinyMCELang['lang_searchreplace_replaceall']  = '<?php echo lang('Replace&nbsp;all'); ?>';
tinyMCELang['lang_searchreplace_cancel']  = '<?php echo lang('Cancel'); ?>';

// from plugins/table/langs/en.js
tinyMCELang['lang_table_desc']  = '<?php echo lang('Inserts a new table'); ?>';
tinyMCELang['lang_table_insert_row_before_desc']  = '<?php echo lang('Insert row before'); ?>';
tinyMCELang['lang_table_insert_row_after_desc']  = '<?php echo lang('Insert row after'); ?>';
tinyMCELang['lang_table_delete_row_desc']  = '<?php echo lang('Delete row'); ?>';
tinyMCELang['lang_table_insert_col_before_desc']  = '<?php echo lang('Insert column before'); ?>';
tinyMCELang['lang_table_insert_col_after_desc']  = '<?php echo lang('Insert column after'); ?>';
tinyMCELang['lang_table_delete_col_desc']  = '<?php echo lang('Remove col'); ?>';
tinyMCELang['lang_insert_table_title']  = '<?php echo lang('Insert/Modify table'); ?>';
tinyMCELang['lang_insert_table_width']  = '<?php echo lang('Width'); ?>';
tinyMCELang['lang_insert_table_height']  = '<?php echo lang('Height'); ?>';
tinyMCELang['lang_insert_table_cols']  = '<?php echo lang('Columns'); ?>';
tinyMCELang['lang_insert_table_rows']  = '<?php echo lang('Rows'); ?>';
tinyMCELang['lang_insert_table_cellspacing']  = '<?php echo lang('Cellspacing'); ?>';
tinyMCELang['lang_insert_table_cellpadding']  = '<?php echo lang('Cellpadding'); ?>';
tinyMCELang['lang_insert_table_border']  = '<?php echo lang('Border'); ?>';
tinyMCELang['lang_insert_table_align']  = '<?php echo lang('Alignment'); ?>';
tinyMCELang['lang_insert_table_align_default']  = '<?php echo lang('Default'); ?>';
tinyMCELang['lang_insert_table_align_left']  = '<?php echo lang('Left'); ?>';
tinyMCELang['lang_insert_table_align_right']  = '<?php echo lang('Right'); ?>';
tinyMCELang['lang_insert_table_align_middle']  = '<?php echo lang('Center'); ?>';
tinyMCELang['lang_insert_table_class']  = '<?php echo lang('Class'); ?>';
tinyMCELang['lang_table_row_title']  = '<?php echo lang('Table row properties'); ?>';
tinyMCELang['lang_table_cell_title']  = '<?php echo lang('Table cell properties'); ?>';
tinyMCELang['lang_table_row_desc']  = '<?php echo lang('Table row properties'); ?>';
tinyMCELang['lang_table_cell_desc']  = '<?php echo lang('Table cell properties'); ?>';
tinyMCELang['lang_insert_table_valign']  = '<?php echo lang('Vertical alignment'); ?>';
tinyMCELang['lang_insert_table_align_top']  = '<?php echo lang('Top'); ?>';
tinyMCELang['lang_insert_table_align_bottom']  = '<?php echo lang('Bottom'); ?>';
tinyMCELang['lang_table_props_desc']  = '<?php echo lang('Table properties'); ?>';
tinyMCELang['lang_table_bordercolor']  = '<?php echo lang('Border color'); ?>';
tinyMCELang['lang_table_bgcolor']  = '<?php echo lang('Bg color'); ?>';
tinyMCELang['lang_table_merge_cells_title']  = '<?php echo lang('Merge table cells'); ?>';
tinyMCELang['lang_table_split_cells_desc']  = '<?php echo lang('Split table cells'); ?>';
tinyMCELang['lang_table_merge_cells_desc']  = '<?php echo lang('Merge table cells'); ?>';
tinyMCELang['lang_table_cut_row_desc']  = '<?php echo lang('Cut table row'); ?>';
tinyMCELang['lang_table_copy_row_desc']  = '<?php echo lang('Copy table row'); ?>';
tinyMCELang['lang_table_paste_row_before_desc']  = '<?php echo lang('Paste table row before'); ?>';
tinyMCELang['lang_table_paste_row_after_desc']  = '<?php echo lang('Paste table row after'); ?>';

// from plugins/zoom/langs/en.js
tinyMCELang['lang_zoom_prefix']  = '<?php echo lang('Zoom'); ?>';

// for plugins/filemanger
tinyMCELang['lang_insert_filemanager']  = '<?php echo lang('Insert link to file'); ?>';
