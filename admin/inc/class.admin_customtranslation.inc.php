<?php
/**
 * EGgroupware admin - custom translations
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2011-16 by Ralf Becker <rb@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Egw;
use EGroupware\Api\Etemplate;

/**
 * Custom - instance specific - translations
 */
class admin_customtranslation
{
	/**
	 * Which methods of this class can be called as menuation
	 *
	 * @var array
	 */
	public $public_functions = array(
		'index' => true,
	);

	/**
	 * Add, modify, delete custom translations
	 *
	 * @param array $_content =null
	 * @param string $msg =''
	 */
	function index(array $_content=null, $msg='')
	{
		if (is_array($_content))
		{
			//_debug_array($_content);
			if (isset($_content['button']))
			{
				$action = key($_content['button']);
				unset($_content['button']);
			}
			elseif($_content['rows']['delete'])
			{
				$action = key($_content['rows']['delete']);
				unset($_content['rows']['delete']);
			}
			switch($action)
			{
				case 'save':
				case 'apply':
					$saved = 0;
					foreach($_content['rows'] as $data)
					{
						if (!empty($data['phrase']))
						{
							Api\Translation::write('en', 'custom', strtolower(trim($data['phrase'])), $data['translation']);
							++$saved;
						}
					}
					if ($saved) $msg = lang('%1 phrases saved.', $saved);
					if ($action == 'apply') break;
					// fall through
				case 'cancel':
					Egw::redirect_link('/admin/index.php');
					break;

				default:	// line to delete;
					if (!empty($_content['rows'][$action]['phrase']))
					{
						Api\Translation::write('en', 'custom', strtolower(trim($_content['rows'][$action]['phrase'])), null);
						$msg = lang('Phrase deleted');
					}
					break;
			}
		}
		$content = array('rows' => array());
		foreach(Api\Translation::load_app('custom', 'en') as $phrase => $translation)
		{
			$content['rows'][++$row] = array(
				'phrase' => $phrase,
				'translation' => $translation,
			);
		}
		// one empty line to add new translations
		$content['rows'][++$row] = array(
			'phrase' => '',
			'translation' => '',
		);
		$readonlys["delete[$row]"] = true;	// no delete for empty row
		$content['msg'] = $msg;

		$GLOBALS['egw_info']['flags']['app_header'] = lang('Custom translation');
		$tpl = new Etemplate('admin.customtranslation');
		$tpl->exec('admin.admin_customtranslation.index', $content, array(), $readonlys);
	}
}
