<?php
/**
 * EGroupware - Collabeditor
 *
 * @link http://www.egroupware.org
 * @package Collabeditor
 * @author Hadi Nategh <hn-AT-egroupware.de>
 * @copyright (c) 2016 by Hadi Nategh <hn-AT-egroupware.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */
namespace EGroupware\Collabeditor;

use EGroupware\Api;
use EGroupware\Api\Vfs;
use EGroupware\Api\Json;
use EGroupware\Api\Etemplate;

class Ui {

	/**
	 * Methods callable via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'editor' => true,
		'poll'	 => true
	);

	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
		$this->bo = new Bo();
	}

	/**
	 * Editor for odf files
	 *
	 * @param array $content
	 */
	function editor($content=null)
	{
		$tmpl = new Etemplate('collabeditor.editor');
		$path = $_GET['path'];
		if (!preg_match("/\/webdav.php\//", $path))
		{
			$download_url = Vfs::download_url($path);
		}
		else
		{
			$download_url = $path;
			$paths = explode('/webdav.php', $path);
			$path = $paths[1];
		}

		// Include css files used by wodocollabeditor
		Api\Framework::includeCSS('/collabeditor/js/webodf/collab/app/resources/app.css');
		Api\Framework::includeCSS('/collabeditor/js/webodf/collab/wodocollabpane.css');
		Api\Framework::includeCSS('/collabeditor/js/webodf/collab/wodotexteditor.css');
		Api\Framework::includeJS('/collabeditor/js/app.js',null, 'collabeditor');

		if (!$content)
		{
			if ($download_url)
			{
				$content['es_id'] = md5 ($download_url);
				$content['file_path'] = $path;
			}
			else
			{
				$content = array();
			}
		}

		$actions = self::getActions();
		if (!Vfs::check_access($path, Vfs::WRITABLE))
		{
			unset ($actions['save']);
			unset ($actions['discard']);
			unset ($actions['delete']);
		}
		$tmpl->setElementAttribute('tools', 'actions', $actions);
		$preserve = $content;
		$tmpl->exec('collabeditor.'.__CLASS__.'.editor',$content,array(),array(),$preserve,2);
	}

	/**
	 * Polling mechanism to synchronize data
	 *
	 * @throws Exception
	 */
	function poll ()
	{
		$this->bo->poll();
	}

	/**
	 * Ajax function to handle actions called by client-side
	 * types: save, delete, discard
	 *
	 * @param array $params
	 * @param string $action
	 */
	function ajax_actions ($params, $action)
	{
		$response = Json\Response::get();
		switch ($action)
		{
			case 'save':
				$this->bo->save($params['es_id'], $params['file_path']);
				break;
			case 'delete':
				$this->bo->delete($params['es_id']);
				break;
			case 'discard':
				$this->bo->discard($params['es_id']);
				break;
			case 'checkLastMember':
				$activeMembers = $this->bo->checkLastMember($params['es_id']);
				$response->data(is_array($activeMembers) && count($activeMembers) > 1?false:true);
				break;
			default:
				//
		}
	}

	/**
	 * Function to get genesis url by generating a temp genesis temp file
	 * out of given path, and returning es_id md5 hash and genesis url to
	 * client.
	 *
	 * @param type $file_path file path
	 * @param boolean $_isNew true means this is an empty doc opened as new file
	 * in client-side and not stored yet therefore no genesis file should get generated for it.
	 */
	function ajax_getGenesisUrl ($file_path, $_isNew)
	{
		$response = Json\Response::get();
		$response->data($this->bo->getGenesisUrl($file_path, $_isNew));
	}

	/**
	 * Editor dialog's toolbar actions
	 *
	 * @return array return array of actions
	 */
	static function getActions()
	{
		$group = 0;
		$actions = array (
			'save' => array(
				'caption' => 'Save',
				'icon' => 'apply',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.filemanager.editor_save',
				'allowOnMultiple' => false,
				'toolbarDefault' => true
			),
			'new' => array(
				'caption' => 'New',
				'icon' => 'add',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.filemanager.create_new',
				'allowOnMultiple' => false,
				'toolbarDefault' => true
			),
			'close' => array(
				'caption' => 'Close',
				'icon' => 'close',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.filemanager.editor_close',
				'allowOnMultiple' => false,
				'toolbarDefault' => true
			),
			'saveas' => array(
				'caption' => 'Save As',
				'icon' => 'save_all',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.filemanager.editor_save',
				'allowOnMultiple' => false,
				'toolbarDefault' => true
			),
			'delete' => array(
				'caption' => 'Delete',
				'icon' => 'delete',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.filemanager.editor_delete',
				'allowOnMultiple' => false,
				'toolbarDefault' => false
			),
			'discard' => array(
				'caption' => 'Discard',
				'icon' => 'discard',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.filemanager.editor_discard',
				'allowOnMultiple' => false,
				'toolbarDefault' => false
			)
		);
		return $actions;
	}
}