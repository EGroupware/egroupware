<?php
	/**************************************************************************\
	* phpGroupWare - Administration                                            *
	* http://www.phpgroupware.org                                              *
	* Written by coreteam <phpgroupware-developers@gnu.org>                    *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	/* $Id$ */

	class uimainscreen
	{
		var $public_functions = array
		(
			'index'			=> True,
			'mainscreen'	=> True
		);

		function uimainscreen()
		{
			$GLOBALS['phpgw_info']['flags']['xslt_app'] = True;
			$GLOBALS['phpgw']->nextmatchs = CreateObject('phpgwapi.nextmatchs');
		}

		function mainscreen()
		{
			$GLOBALS['phpgw_info']['flags']['java_script_url'] = 'default/app_scripts.js';
			$GLOBALS['phpgw']->xslttpl->add_file('app_data');
			$GLOBALS['phpgw']->hooks->process('admin');

			$GLOBALS['phpgw']->xslttpl->set_var('phpgw',array('list' => $GLOBALS['phpgw']->common->output));
		}

		function index()
		{
			if ($GLOBALS['HTTP_POST_VARS']['cancel'])
			{
				header('Location: ' . $GLOBALS['phpgw']->link('/admin/index.php'));
			}

			$GLOBALS['phpgw']->template->set_file(array('message' => 'mainscreen_message.tpl'));
			$GLOBALS['phpgw']->template->set_block('message','form','form');
			$GLOBALS['phpgw']->template->set_block('message','row','row');
			$GLOBALS['phpgw']->template->set_block('message','row_2','row_2');

			$section     = $GLOBALS['HTTP_POST_VARS']['section'];
			$select_lang = $GLOBALS['HTTP_POST_VARS']['select_lang'];
			$message     = $GLOBALS['HTTP_POST_VARS']['message'];

			$GLOBALS['phpgw']->common->phpgw_header();

			if ($GLOBALS['HTTP_POST_VARS']['submit'])
			{
				$GLOBALS['phpgw']->db->query("DELETE FROM phpgw_lang WHERE message_id='$section" . "_message' AND app_name='"
					. "$section' AND lang='$select_lang'",__LINE__,__FILE__);
				$GLOBALS['phpgw']->db->query("INSERT INTO phpgw_lang VALUES ('$section" . "_message','$section','$select_lang','"
					. addslashes($message) . "')",__LINE__,__FILE__);
				$message = '<center>'.lang('message has been updated').'</center>';
			}

			if (empty($select_lang))
			{
				$GLOBALS['phpgw']->template->set_var('header_lang',lang('Main screen message'));
				$GLOBALS['phpgw']->template->set_var('form_action',$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uimainscreen.index'));
				$GLOBALS['phpgw']->template->set_var('tr_color',$GLOBALS['phpgw_info']['theme']['th_bg']);
				$GLOBALS['phpgw']->template->set_var('value','&nbsp;');
				$GLOBALS['phpgw']->template->fp('rows','row_2',True);

				$tr_color = $GLOBALS['phpgw']->nextmatchs->alternate_row_color($tr_color);
				$GLOBALS['phpgw']->template->set_var('tr_color',$tr_color);

				$select_lang = '<select name="select_lang">';
				$GLOBALS['phpgw']->db->query("SELECT lang,phpgw_languages.lang_name,phpgw_languages.lang_id FROM phpgw_lang,phpgw_languages WHERE "
					. "phpgw_lang.lang=phpgw_languages.lang_id GROUP BY lang,phpgw_languages.lang_name,"
					. "phpgw_languages.lang_id ORDER BY lang");
				while ($GLOBALS['phpgw']->db->next_record())
				{
					$select_lang .= '<option value="' . $GLOBALS['phpgw']->db->f('lang') . '">' . $GLOBALS['phpgw']->db->f('lang_id')
						. ' - ' . $GLOBALS['phpgw']->db->f('lang_name') . '</option>';
				}
				$select_lang .= '</select>';
				$GLOBALS['phpgw']->template->set_var('label',lang('Language'));
				$GLOBALS['phpgw']->template->set_var('value',$select_lang);
				$GLOBALS['phpgw']->template->fp('rows','row',True);

				$tr_color = $GLOBALS['phpgw']->nextmatchs->alternate_row_color($tr_color);
				$GLOBALS['phpgw']->template->set_var('tr_color',$tr_color);
				$select_section = '<select name="section"><option value="mainscreen">' . lang('Main screen')
					. '</option><option value="loginscreen">' . lang("Login screen") . '</option>'
					. '</select>';
				$GLOBALS['phpgw']->template->set_var('label',lang('Section'));
				$GLOBALS['phpgw']->template->set_var('value',$select_section);
				$GLOBALS['phpgw']->template->fp('rows','row',True);

				$tr_color = $GLOBALS['phpgw']->nextmatchs->alternate_row_color($tr_color);
				$GLOBALS['phpgw']->template->set_var('tr_color',$tr_color);
				$GLOBALS['phpgw']->template->set_var('value','<input type="submit" value="' . lang('Submit')
					. '"><input type="submit" name="cancel" value="'. lang('cancel') .'">');
				$GLOBALS['phpgw']->template->fp('rows','row_2',True);
			}
			else
			{
				$GLOBALS['phpgw']->db->query("SELECT content FROM phpgw_lang WHERE lang='$select_lang' AND message_id='$section"
				. "_message'");
				$GLOBALS['phpgw']->db->next_record();
				$current_message = $GLOBALS['phpgw']->db->f('content');

				if ($section == 'mainscreen')
				{
					$GLOBALS['phpgw']->template->set_var('header_lang',lang('Edit main screen message'));
				}
				else
				{
					$GLOBALS['phpgw']->template->set_var('header_lang',lang('Edit login screen message'));
				}

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
				$GLOBALS['phpgw']->template->set_var('value','<input type="submit" name="submit" value="' . lang('Update')
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
