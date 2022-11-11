<?php
/**
 * EGgroupware administration
 *
 * @link http://www.egroupware.org
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Hadi Nategh <hn@egroupware.org>
 * @copyright (c) 2018, Hadi Nategh <hn@egroupware.org>
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Etemplate;
use EGroupware\Api\Egw;

/**
 * Mainscreen and login message
 */
class admin_messages
{
	var $public_functions = array('index' => True);

	const MAINSCREEN = 'mainscreen';
	const LOGINSCREEN = 'loginscreen';

	function index ($content = null)
	{
		$tpl = new Etemplate('admin.mainscreen_message');

		$acls = array (
			self::MAINSCREEN => !$GLOBALS['egw']->acl->check('mainscreen_messa',1,'admin'),
			self::LOGINSCREEN => !$GLOBALS['egw']->acl->check('mainscreen_messa',2,'admin')
		);

		if (!is_array($content))
		{
			$lang = $GLOBALS['egw_info']['user']['preferences']['common']['lang'];
			$content = 	array_merge(array(
				'html' => true,
				'lang' => $lang
			), $this->_getMessages($lang));
		}
		else
		{
			$button = key($content['button'] ?? []);

			if ($button)
			{
				switch($button)
				{
					case 'apply':
					case 'save':
						foreach (array ('mainscreen', 'loginscreen') as $section)
						{
							$prefix = $content['html'] == true ? 'html_' : 'text_';
							Api\Translation::write($content['lang'], $section, $section.'_message',$content[$prefix.$section]);
						}
						Framework::message(lang('message has been updated'));
						if ($button == 'apply') break;
						//fall through
					default:
						Egw::redirect_link('/index.php', array(
							'menuaction' => 'admin.admin_ui.index',
							'ajax' => 'true'
						), 'admin');
				}
			}
			else if ($content['lang'])
			{
				$content = array_merge($content, $this->_getMessages($content['lang'], $content['html']));
			}
		}
		$readonlys = array(
			'tabs' => array(
				self::MAINSCREEN => !$acls[self::MAINSCREEN],
				self::LOGINSCREEN => !$acls[self::LOGINSCREEN]
			)
		);

		$tpl->exec('admin.admin_messages.index', $content, array('lang' => Api\Translation::get_installed_langs()), $readonlys);
	}

	/**
	 * Get messages content
	 *
	 * @param type $lang
	 * @param type $html
	 * @return array returns an array of content
	 */
	private function _getMessages ($lang, $html = true)
	{
		if ($html)
		{
			return array (
				'html_mainscreen' => Api\Translation::read($lang, self::MAINSCREEN, self::MAINSCREEN.'_message'),
				'html_loginscreen' => Api\Translation::read($lang, self::LOGINSCREEN, self::LOGINSCREEN.'_message'),
			);
		}
		else
		{
			return array (
				'text_mainscreen' => strip_tags(Api\Translation::read($lang, self::MAINSCREEN, self::MAINSCREEN.'_message')),
				'text_loginscreen' => strip_tags(Api\Translation::read($lang, self::LOGINSCREEN, self::LOGINSCREEN.'_message'))
			);
		}
	}
}