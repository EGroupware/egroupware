<?php
	/**************************************************************************\
	* eGroupWare - administration                                              *
	* http://www.egroupware.org                                                *
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

		function index()
		{
			$html =& CreateObject('phpgwapi.html');
			$section     = $_POST['section'];
			$select_lang = $_POST['select_lang'];
			$message     = get_magic_quotes_gpc() ? stripslashes($_POST['message']) : $_POST['message'];

			$acl_ok = array();
			if (!$GLOBALS['egw']->acl->check('mainscreen_message_access',1,'admin'))
			{
				$acl_ok['mainscreen'] = True;
			}
			if (!$GLOBALS['egw']->acl->check('mainscreen_message_access',2,'admin'))
			{
				$acl_ok['loginscreen'] = True;
			}
			if ($_POST['cancel'] && !isset($_POST['message']) || 
					!count($acl_ok) || $_POST['submit'] && !isset($acl_ok[$section]))
			{
				$GLOBALS['egw']->redirect_link('/admin/index.php');
			}

			$GLOBALS['egw']->template->set_file(array('message' => 'mainscreen_message.tpl'));
			$GLOBALS['egw']->template->set_block('message','form','form');
			$GLOBALS['egw']->template->set_block('message','row','row');
			$GLOBALS['egw']->template->set_block('message','row_2','row_2');

			if ($_POST['submit'])
			{
				$GLOBALS['egw']->translation->write($select_lang,$section,$section.'_message',$message);
				$feedback_message = '<center>'.lang('message has been updated').'</center>';
				
				$section = '';
			}
			if ($_POST['cancel'])	// back to section/lang-selection
			{
				$message = $section = '';
			}
			switch ($section)
			{
				case 'mainscreen':
					$GLOBALS['egw_info']['flags']['app_header'] = lang('Admin').' - '.lang('Edit main screen message') . ': '.strtoupper($select_lang);
					break;
				case 'loginscreen':
					$GLOBALS['egw_info']['flags']['app_header'] = lang('Admin').' - '.lang('Edit login screen message') . ': '.strtoupper($select_lang);
					break;
				default:
					$GLOBALS['egw_info']['flags']['app_header'] = lang('Admin').' - '.lang('Main screen message');
					break;
			}
			if (empty($section))
			{

				 $GLOBALS['egw']->js->validate_file('jscode','openwindow','admin');
				 $GLOBALS['egw']->common->egw_header();
				 echo parse_navbar();
					
				 
				$GLOBALS['egw']->template->set_var('form_action',$GLOBALS['egw']->link('/index.php','menuaction=admin.uimainscreen.index'));
				$GLOBALS['egw']->template->set_var('tr_color',$GLOBALS['egw_info']['theme']['th_bg']);
				$GLOBALS['egw']->template->set_var('value','&nbsp;');
				$GLOBALS['egw']->template->fp('rows','row_2',True);

				$tr_color = $GLOBALS['egw']->nextmatchs->alternate_row_color($tr_color);
				$GLOBALS['egw']->template->set_var('tr_color',$tr_color);

				$lang_select = '<select name="select_lang">';
				foreach($GLOBALS['egw']->translation->get_installed_langs() as $lang => $lang_name)
				{
					$lang_select .= '<option value="' . $lang . '"'.($lang == $select_lang ? ' selected="selected"' : '').'>' . 
						$lang . ' - ' . $lang_name . "</option>\n";
				}
				$lang_select .= '</select>';
				
				$GLOBALS['egw']->template->set_var('label',lang('Language'));
				$GLOBALS['egw']->template->set_var('value',$lang_select);
				$GLOBALS['egw']->template->fp('rows','row',True);

				$tr_color = $GLOBALS['egw']->nextmatchs->alternate_row_color($tr_color);
				$GLOBALS['egw']->template->set_var('tr_color',$tr_color);
				$select_section = '<select name="section">'."\n";
				foreach($acl_ok as $key => $val)
				{
					$select_section .= ' <option value="'.$key.'"'.
						($key == $_POST['section'] ? ' selected' : '') . '>' . 
						($key == 'mainscreen' ? lang('Main screen') : lang("Login screen")) . "</option>\n";
				}
				$select_section .= '</select>';
				$GLOBALS['egw']->template->set_var('label',lang('Section'));
				$GLOBALS['egw']->template->set_var('value',$select_section);
				$GLOBALS['egw']->template->fp('rows','row',True);

				$tr_color = $GLOBALS['egw']->nextmatchs->alternate_row_color($tr_color);
				$GLOBALS['egw']->template->set_var('tr_color',$tr_color);
				$GLOBALS['egw']->template->set_var('value','<input type="submit" value="' . lang('Edit')
					. '"><input type="submit" name="cancel" value="'. lang('cancel') .'">');
				$GLOBALS['egw']->template->fp('rows','row_2',True);
			}
			else
			{
				$current_message = $GLOBALS['egw']->translation->read($select_lang,$section,$section.'_message');
				if ($_POST['no-htmlarea']) $current_message = strip_tags($current_message);	
				if($_POST['htmlarea'] || strlen($current_message) > strlen(strip_tags($current_message)))
				{
					 $text_or_htmlarea=$html->htmlarea('message',$current_message,'advanced');
					 $htmlarea_button='<input type="submit" name="no-htmlarea" onclick="self.location.href=\''.$GLOBALS['egw']->link('/index.php',
						'menuaction=admin.uimainscreen.index&htmlarea=true').'\'" value="'.lang('disable WYSIWYG-editor').'">';
				}
				else
				{
					 $text_or_htmlarea='<textarea name="message" style="width:100%; min-width:350px; height:300px;" wrap="virtual">' . 
						$html->htmlspecialchars($current_message) . '</textarea>';
					 $htmlarea_button='<input type="submit" name="htmlarea" onclick="self.location.href=\''.
						$GLOBALS['egw']->link('/index.php','menuaction=admin.uimainscreen.index&htmlarea=true').'\'" value="'.
						lang('activate WYSIWYG-editor').'">';

				}			   

				$GLOBALS['egw']->js->validate_file('jscode','openwindow','admin');
				$GLOBALS['egw']->common->egw_header();
				echo parse_navbar();
				
				$GLOBALS['egw']->template->set_var('form_action',$GLOBALS['egw']->link('/index.php','menuaction=admin.uimainscreen.index'));
				$GLOBALS['egw']->template->set_var('select_lang',$select_lang);
				$GLOBALS['egw']->template->set_var('section',$section);
				$GLOBALS['egw']->template->set_var('tr_color',$GLOBALS['egw_info']['theme']['th_bg']);
				$GLOBALS['egw']->template->set_var('value','&nbsp;');
				$GLOBALS['egw']->template->fp('rows','row_2',True);

				$tr_color = $GLOBALS['egw']->nextmatchs->alternate_row_color($tr_color);
				$GLOBALS['egw']->template->set_var('tr_color',$tr_color);

				
				$GLOBALS['egw']->template->set_var('value',$text_or_htmlarea);
				
				
				
				$GLOBALS['egw']->template->fp('rows','row_2',True);

				$tr_color = $GLOBALS['egw']->nextmatchs->alternate_row_color($tr_color);
				$GLOBALS['egw']->template->set_var('tr_color',$tr_color);
				$GLOBALS['egw']->template->set_var('value','<input type="submit" name="submit" value="' . lang('Save')
				. '"><input type="submit" name="cancel" value="'. lang('cancel') .'">'.$htmlarea_button);
				$GLOBALS['egw']->template->fp('rows','row_2',True);
			}

			$GLOBALS['egw']->template->set_var('lang_cancel',lang('Cancel'));
			$GLOBALS['egw']->template->set_var('error_message',$feedback_message);
			$GLOBALS['egw']->template->pfp('out','form');
		}
	}
?>
