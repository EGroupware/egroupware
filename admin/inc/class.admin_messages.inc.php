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

	var $sections = array ('mainscreen', 'loginscreen');

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

			$content = array(
				'html' => true,
				'lang' => $GLOBALS['egw_info']['user']['preferences']['common']['lang']
			);
		}
		else {
			list($button) = @each($content['button']);

			if ($button)
			{
				switch($button)
				{
					case 'apply':
					case 'save':
						foreach (self::$sections as $section)
						{
							$prefix = $content['html'] == true ? 'html_' : 'text_';
							if ($content[$prefix.$section] && $content[$prefix.$section])
							{
								Api\Translation::write($content['lang'], $section, $section.'_message',$content[$prefix.$section]);
							}
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
		}
		$readonlys = array(
			'tabs' => array(
				self::MAINSCREEN => !$acls[self::MAINSCREEN],
				self::LOGINSCREEN => !$acls[self::LOGINSCREEN]
			)
		);
		$tpl->exec('admin.admin_messages.index', $content, array(), $readonlys);
	}
}
