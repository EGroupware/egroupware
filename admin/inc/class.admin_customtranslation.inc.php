<?php
/**
 * EGgroupware admin - custom translations
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2011 by Ralf Becker <rb@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

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
	 * @param array $content=null
	 * @param string $msg=''
	 */
	function index(array $content=null, $msg='')
	{
		if (is_array($content))
		{
			//_debug_array($content);
			if (isset($content['button']))
			{
				list($action) = each($content['button']);
				unset($content['button']);
			}
			elseif($content['rows']['delete'])
			{
				list($action) = each($content['rows']['delete']);
				unset($content['rows']['delete']);
			}
			switch($action)
			{
				case 'save':
				case 'apply':
					$saved = 0;
					foreach($content['rows'] as $n => $data)
					{
						if (!empty($data['phrase']))
						{
							translation::write('en', 'custom', strtolower(trim($data['phrase'])), $data['translation']);
							++$saved;
						}
					}
					if ($saved) $msg = lang('%1 phrases saved.', $saved);
					if ($action == 'apply') break;
					// fall through
				case 'cancel':
					egw::redirect_link('/admin/index.php');
					break;

				default:	// line to delete;
					if (!empty($content['rows'][$action]['phrase']))
					{
						translation::write('en', 'custom', strtolower(trim($content['rows'][$action]['phrase'])), null);
						$msg = lang('Phrase deleted');
					}
					break;
			}
		}
		$content = array('rows' => array());
		foreach(translation::load_app('custom', 'en') as $phrase => $translation)
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
		$tpl = new etemplate('admin.customtranslation');
		$tpl->exec('admin.admin_customtranslation.index', $content, $sel_options, $readonlys, $preserv);
	}
}
