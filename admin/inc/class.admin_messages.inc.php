<?php
/**
 * EGgroupware administration
 *
 * @link http://www.egroupware.org
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Framework;

/**
 * Mainscreen and login message
 */
class admin_messages
{
	var $public_functions = array('index' => True);

	function index()
	{
		$section     = $_POST['section'];
		$select_lang = $_POST['select_lang'];
		$message     = get_magic_quotes_gpc() ? stripslashes($_POST['message']) : $_POST['message'];
		$acl_ok = array();
		if (!$GLOBALS['egw']->acl->check('mainscreen_messa',1,'admin'))
		{
			$acl_ok['mainscreen'] = True;
		}
		if (!$GLOBALS['egw']->acl->check('mainscreen_messa',2,'admin'))
		{
			$acl_ok['loginscreen'] = True;
		}
		if ($_POST['cancel'] && !isset($_POST['message']) ||
				!count($acl_ok) || $_POST['submit'] && !isset($acl_ok[$section]))
		{
			$GLOBALS['egw']->redirect_link('/admin/index.php');
		}

		Framework::includeJS('vendor/egroupware/ckeditor/ckeditor.js');

		$GLOBALS['egw']->template->set_file(array('message' => 'mainscreen_message.tpl'));
		$GLOBALS['egw']->template->set_block('message','form','form');
		$GLOBALS['egw']->template->set_block('message','row','row');
		$GLOBALS['egw']->template->set_block('message','row_2','row_2');

		if ($_POST['save'])
		{
			Api\Translation::write($select_lang,$section,$section.'_message',$message);
			Framework::message(lang('message has been updated'));

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
			echo $GLOBALS['egw']->framework->header();

			$GLOBALS['egw']->template->set_var('form_action',$GLOBALS['egw']->link('/index.php','menuaction=admin.admin_messages.index'));
			$GLOBALS['egw']->template->set_var('value','&nbsp;');
			$GLOBALS['egw']->template->fp('rows','row_2',True);

			$langs = Api\Translation::get_installed_langs();
			$langs['en'] .= ' ('.lang('All languages').')';
			$lang_select = Api\Html::select('select_lang', 'en', $langs);

			$GLOBALS['egw']->template->set_var('label',lang('Language'));
			$GLOBALS['egw']->template->set_var('value',$lang_select);
			$GLOBALS['egw']->template->fp('rows','row',True);

			$select_section = '<select name="section">'."\n";
			foreach(array_keys($acl_ok) as $key)
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
				Api\Html::submit_button('edit', lang('Edit'))."\n".Api\Html::submit_button('cancel', lang('Cancel')));
			$GLOBALS['egw']->template->fp('rows','row_2',True);
		}
		else
		{
			$current_message = Api\Translation::read($select_lang,$section,$section.'_message');
			if ($_POST['no']) $current_message = strip_tags($current_message);
			if (empty($_POST['no']) && ($_POST['yes'] || empty($current_message) ||
				strlen($current_message) != strlen(strip_tags($current_message))))
			{
				 $text_or_htmlarea = Api\Html::fckEditorQuick('message','advanced',$current_message,'400px','800px');
				 $htmlarea_button = Api\Html::submit_button("no", lang('disable WYSIWYG-editor'));
			}
			else
			{
				 $text_or_htmlarea='<textarea name="message" style="width:100%; min-width:350px; height:300px;" wrap="virtual">' .
					Api\Html::htmlspecialchars($current_message) . '</textarea>';
				 $htmlarea_button = Api\Html::submit_button("yes", lang('activate WYSIWYG-editor'));
			}
			echo $GLOBALS['egw']->framework->header();

			$GLOBALS['egw']->template->set_var('form_action',$GLOBALS['egw']->link('/index.php','menuaction=admin.admin_messages.index'));
			$GLOBALS['egw']->template->set_var('select_lang',$select_lang);
			$GLOBALS['egw']->template->set_var('section',$section);
			$GLOBALS['egw']->template->set_var('value','&nbsp;');
			$GLOBALS['egw']->template->fp('rows','row_2',True);

			$GLOBALS['egw']->template->set_var('value',$text_or_htmlarea);

			$GLOBALS['egw']->template->fp('rows','row_2',True);

			$GLOBALS['egw']->template->set_var('value',
				Api\Html::submit_button('save', lang('Save'))."\n".Api\Html::submit_button('cancel', lang('Cancel')).
				"\n".$htmlarea_button);
			$GLOBALS['egw']->template->fp('rows','row_2',True);
		}

		$GLOBALS['egw']->template->set_var('lang_cancel',lang('Cancel'));
		$GLOBALS['egw']->template->pparse('out','form');

		echo $GLOBALS['egw']->framework->footer();
	}
}
