<?php
  /**************************************************************************\
  * phpGroupWare - administration                                            *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	class uimainscreen
	{
		var $public_functions = array('index' => True);

		function uimainscreen()
		{
			$GLOBALS['phpgw']->nextmatchs = CreateObject('phpgwapi.nextmatchs');
		}

		function index()
		{
			$section     = addslashes($_POST['section']);
			$select_lang = addslashes($_POST['select_lang']);
			$message     = addslashes($_POST['message']);

			$acl_ok = array();
			if (!$GLOBALS['phpgw']->acl->check('mainscreen_message_access',1,'admin'))
			{
				$acl_ok['mainscreen'] = True;
			}
			if (!$GLOBALS['phpgw']->acl->check('mainscreen_message_access',2,'admin'))
			{
				$acl_ok['loginscreen'] = True;
			}
			if ($_POST['cancel'] && !isset($_POST['message']) || 
			    !count($acl_ok) || $_POST['submit'] && !isset($acl_ok[$section]))
			{
				$GLOBALS['phpgw']->redirect_link('/admin/index.php');
			}

			$GLOBALS['phpgw']->template->set_file(array('message' => 'mainscreen_message.tpl'));
			$GLOBALS['phpgw']->template->set_block('message','form','form');
			$GLOBALS['phpgw']->template->set_block('message','row','row');
			$GLOBALS['phpgw']->template->set_block('message','row_2','row_2');

			if ($_POST['submit'])
			{
				$GLOBALS['phpgw']->db->query("DELETE FROM phpgw_lang WHERE message_id='$section" . "_message' AND app_name='"
					. "$section' AND lang='$select_lang'",__LINE__,__FILE__);
				$GLOBALS['phpgw']->db->query("INSERT INTO phpgw_lang VALUES ('$section" . "_message','$section','$select_lang','"
					. $message . "')",__LINE__,__FILE__);
				$message = '<center>'.lang('message has been updated').'</center>';
				
				$section = '';
			}
			if ($_POST['cancel'])	// back to section/lang-selection
			{
				$message = $section = '';
			}
			switch ($section)
			{
				case 'mainscreen':
					$GLOBALS['phpgw_info']['flags']['app_header'] = lang('Admin').' - '.lang('Edit main screen message') . ': '.strtoupper($select_lang);
					break;
				case 'loginscreen':
					$GLOBALS['phpgw_info']['flags']['app_header'] = lang('Admin').' - '.lang('Edit login screen message') . ': '.strtoupper($select_lang);
					break;
				default:
					$GLOBALS['phpgw_info']['flags']['app_header'] = lang('Admin').' - '.lang('Main screen message');
					break;
			}
			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();

			if (empty($section))
			{
				$GLOBALS['phpgw']->template->set_var('form_action',$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uimainscreen.index'));
				$GLOBALS['phpgw']->template->set_var('tr_color',$GLOBALS['phpgw_info']['theme']['th_bg']);
				$GLOBALS['phpgw']->template->set_var('value','&nbsp;');
				$GLOBALS['phpgw']->template->fp('rows','row_2',True);

				$tr_color = $GLOBALS['phpgw']->nextmatchs->alternate_row_color($tr_color);
				$GLOBALS['phpgw']->template->set_var('tr_color',$tr_color);

				$lang_select = '<select name="select_lang">';
				$GLOBALS['phpgw']->db->query("SELECT lang,phpgw_languages.lang_name,phpgw_languages.lang_id FROM phpgw_lang,phpgw_languages WHERE "
					. "phpgw_lang.lang=phpgw_languages.lang_id GROUP BY lang,phpgw_languages.lang_name,"
					. "phpgw_languages.lang_id ORDER BY lang",__LINE__,__FILE__);
				while ($GLOBALS['phpgw']->db->next_record())
				{
					$lang = $GLOBALS['phpgw']->db->f('lang');
					$lang_select .= '<option value="' . $lang . '"'.($lang == $select_lang ? ' selected' : '').'>' . 
						$lang . ' - ' . $GLOBALS['phpgw']->db->f('lang_name') . "</option>\n";
				}
				$lang_select .= '</select>';
				$GLOBALS['phpgw']->template->set_var('label',lang('Language'));
				$GLOBALS['phpgw']->template->set_var('value',$lang_select);
				$GLOBALS['phpgw']->template->fp('rows','row',True);

				$tr_color = $GLOBALS['phpgw']->nextmatchs->alternate_row_color($tr_color);
				$GLOBALS['phpgw']->template->set_var('tr_color',$tr_color);
				$select_section = '<select name="section">'."\n";
				foreach($acl_ok as $key => $val)
				{
					$select_section .= ' <option value="'.$key.'"'.
						($key == $_POST['section'] ? ' selected' : '') . '>' . 
						($key == 'mainscreen' ? lang('Main screen') : lang("Login screen")) . "</option>\n";
				}
				$select_section .= '</select>';
				$GLOBALS['phpgw']->template->set_var('label',lang('Section'));
				$GLOBALS['phpgw']->template->set_var('value',$select_section);
				$GLOBALS['phpgw']->template->fp('rows','row',True);

				$tr_color = $GLOBALS['phpgw']->nextmatchs->alternate_row_color($tr_color);
				$GLOBALS['phpgw']->template->set_var('tr_color',$tr_color);
				$GLOBALS['phpgw']->template->set_var('value','<input type="submit" value="' . lang('Edit')
					. '"><input type="submit" name="cancel" value="'. lang('cancel') .'">');
				$GLOBALS['phpgw']->template->fp('rows','row_2',True);
			}
			else
			{
				$GLOBALS['phpgw']->db->query("SELECT content FROM phpgw_lang WHERE lang='$select_lang' AND message_id='$section"
				. "_message'",__LINE__,__FILE__);
				$GLOBALS['phpgw']->db->next_record();
				$current_message = $GLOBALS['phpgw']->db->f('content');

				$GLOBALS['phpgw']->template->set_var('form_action',$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uimainscreen.index'));
				$GLOBALS['phpgw']->template->set_var('select_lang',$select_lang);
				$GLOBALS['phpgw']->template->set_var('section',$section);
				$GLOBALS['phpgw']->template->set_var('tr_color',$GLOBALS['phpgw_info']['theme']['th_bg']);
				$GLOBALS['phpgw']->template->set_var('value','&nbsp;');
				$GLOBALS['phpgw']->template->fp('rows','row_2',True);

				$tr_color = $GLOBALS['phpgw']->nextmatchs->alternate_row_color($tr_color);
				$GLOBALS['phpgw']->template->set_var('tr_color',$tr_color);
				$GLOBALS['phpgw']->template->set_var('value','<textarea name="message" cols="50" rows="10" wrap="virtual">' . stripslashes($current_message) . '</textarea>');
				$GLOBALS['phpgw']->template->fp('rows','row_2',True);

				$tr_color = $GLOBALS['phpgw']->nextmatchs->alternate_row_color($tr_color);
				$GLOBALS['phpgw']->template->set_var('tr_color',$tr_color);
				$GLOBALS['phpgw']->template->set_var('value','<input type="submit" name="submit" value="' . lang('Save')
					. '"><input type="submit" name="cancel" value="'. lang('cancel') .'">'
				);
				$GLOBALS['phpgw']->template->fp('rows','row_2',True);
			}

			$GLOBALS['phpgw']->template->set_var('lang_cancel',lang('Cancel'));
			$GLOBALS['phpgw']->template->set_var('error_message',$message);
			$GLOBALS['phpgw']->template->pfp('out','form');
		}
	}
?>
