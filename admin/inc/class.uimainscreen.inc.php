<?php
/**
 * EGgroupware administration
 *
 * @link http://www.egroupware.org
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

class uimainscreen
{
	var $public_functions = array('index' => True);

	function index()
	{
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

		egw_framework::validate_file('ckeditor','ckeditor','phpgwapi');

		$GLOBALS['egw']->template->set_file(array('message' => 'mainscreen_message.tpl'));
		$GLOBALS['egw']->template->set_block('message','form','form');
		$GLOBALS['egw']->template->set_block('message','row','row');
		$GLOBALS['egw']->template->set_block('message','row_2','row_2');

		if ($_POST['save'])
		{
			translation::write($select_lang,$section,$section.'_message',$message);
			egw_framework::message(lang('message has been updated'));

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
			common::egw_header();
			echo parse_navbar();

			$GLOBALS['egw']->template->set_var('form_action',$GLOBALS['egw']->link('/index.php','menuaction=admin.uimainscreen.index'));
			$GLOBALS['egw']->template->set_var('value','&nbsp;');
			$GLOBALS['egw']->template->fp('rows','row_2',True);

			$langs = translation::get_installed_langs();
			$langs['en'] .= ' ('.lang('All languages').')';
			$lang_select = html::select('select_lang', 'en', $langs);

			$GLOBALS['egw']->template->set_var('label',lang('Language'));
			$GLOBALS['egw']->template->set_var('value',$lang_select);
			$GLOBALS['egw']->template->fp('rows','row',True);

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

			$GLOBALS['egw']->template->set_var('value',
				html::submit_button('edit', lang('Edit'))."\n".html::submit_button('cancel', lang('Cancel')));
			$GLOBALS['egw']->template->fp('rows','row_2',True);
		}
		else
		{
			$current_message = translation::read($select_lang,$section,$section.'_message');
			if ($_POST['no']) $current_message = strip_tags($current_message);
			if (empty($_POST['no']) && ($_POST['yes'] || empty($current_message) ||
				strlen($current_message) != strlen(strip_tags($current_message))))
			{
				 $text_or_htmlarea = html::fckEditorQuick('message','advanced',$current_message,'400px','800px');
				 $htmlarea_button = html::submit_button("no", lang('disable WYSIWYG-editor'));
			}
			else
			{
				 $text_or_htmlarea='<textarea name="message" style="width:100%; min-width:350px; height:300px;" wrap="virtual">' .
					html::htmlspecialchars($current_message) . '</textarea>';
				 $htmlarea_button = html::submit_button("yes", lang('activate WYSIWYG-editor'));
			}
			common::egw_header();
			echo parse_navbar();

			$GLOBALS['egw']->template->set_var('form_action',$GLOBALS['egw']->link('/index.php','menuaction=admin.uimainscreen.index'));
			$GLOBALS['egw']->template->set_var('select_lang',$select_lang);
			$GLOBALS['egw']->template->set_var('section',$section);
			$GLOBALS['egw']->template->set_var('value','&nbsp;');
			$GLOBALS['egw']->template->fp('rows','row_2',True);

			$GLOBALS['egw']->template->set_var('value',$text_or_htmlarea);

			$GLOBALS['egw']->template->fp('rows','row_2',True);

			$GLOBALS['egw']->template->set_var('value',
				html::submit_button('save', lang('Save'))."\n".html::submit_button('cancel', lang('Cancel')).
				"\n".$htmlarea_button);
			$GLOBALS['egw']->template->fp('rows','row_2',True);
		}

		$GLOBALS['egw']->template->set_var('lang_cancel',lang('Cancel'));
		$GLOBALS['egw']->template->pparse('out','form');
	}
}
