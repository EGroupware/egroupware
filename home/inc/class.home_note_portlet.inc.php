<?php
/**
 * Egroupware
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package home
 * @subpackage portlet
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Etemplate;

 /**
  * A simple HTML note-to-self
  *
  * The implementation is a little more complicated because CKEditor needs some
  * CSP exceptions, but the headers will always be already sent.  This means we
  * need a popup window instead of edit in place or a dialog.
  */
class home_note_portlet extends home_portlet
{

	// Allow access to edit from client
	public $public_functions = array(
		'edit' => true
	);

	/**
	 * Context for this portlet
	 */
	public function __construct(Array &$context = array(), &$need_reload = false)
	{
		// Title not set for new widgets created via context menu
		if(!$context['title'])
		{
			// Set initial size to 3x2, default is too small
			$context['width'] = 3;
			$context['height'] = 2;

			$need_reload = true;
		}

		$this->context = $context;
	}

	/**
	 * Edit the note in a popup to accommodate CKEditor's CSP
	 * @param type $content
	 */
	public function edit($content = array())
	{
		$id = $_GET['id'] ? $_GET['id'] : $content['id'];
		$height = $_GET['height'] ? $_GET['height'] : $content['height'];

		if($content['group'] && $GLOBALS['egw_info']['apps']['admin'])
		{
			$prefs = new Api\Preferences(is_numeric($content['group']) ? $content['group'] : $GLOBALS['egw_info']['user']['account_id']);
		}
		else
		{
			$prefs = $GLOBALS['egw']->preferences;
		}
		$type = is_numeric($content['group']) ? "user" : $content['group'];
		$arr = $prefs->read_repository();
		$portlets = $arr['home'];
		if($content['button'])
		{
			// Save updated Api\Preferences
			$portlets[$id]['note'] = $content['note'];
			$prefs->add('home', $id, $portlets[$id],$type);
			$prefs->save_repository(True,$type);
			// Yay for AJAX submit
			Api\Json\Response::get()->apply('window.opener.app.home.refresh',array($id));

			if(key($content['button'])=='save')
			{
				Api\Json\Response::get()->apply('window.close',array());
			}
		}
		$etemplate = new Etemplate('home.note');

		$etemplate->setElementAttribute('note', 'width', '99%');
		$etemplate->setElementAttribute('note', 'height', $height);
		$preserve = array(
			'id'		=>	$id,
			'height'	=>	$height,
			'group'		=>	$portlets[$id]['group']
		);
		$etemplate->exec('home.home_note_portlet.edit',array(
			'note'	=> $portlets[$id]['note']
		), array(),array('note'=>false,'save'=>false), $preserve);
	}

	public function exec($id = null, Etemplate &$etemplate = null)
	{
		// Allow to submit directly back here
		if(is_array($id) && $id['id'])
		{
			$id = $id['id'];
		}
		$etemplate->read('home.note');

		$etemplate->set_dom_id($id);
		$content = $this->context;

		if(!$content['note'])
		{
			$content['note'] = '';
			$content['edit_settings'] = true;
		}

		//$etemplate->exec('home.home_note_portlet.exec',$content,array(),array('__ALL__'=>true),array('id' =>$id));
	}

	public function get_actions()
	{
		$actions = array(
			'edit' => array(
				'icon' => 'edit',
				'caption' => lang('edit'),
				'hideOnDisabled' => false,
				'onExecute' => 'javaScript:app.home.note_edit',
				'default' => true
			),
			'edit_settings' => array(
				'default' => false
			)
		);
		return $actions;
	}

	/**
	 * Return a list of settings to customize the portlet.
	 *
	 * Settings should be in the same style as for preferences.  It is OK to return an empty array
	 * for no customizable settings.
	 *
	 * These should be already translated, no further translation will be done.
	 *
	 * @see preferences/inc/class.preferences_settings.inc.php
	 * @return Array of settings.  Each setting should have the following keys:
	 * - name: Internal reference
	 * - type: Widget type for editing
	 * - label: Human name
	 * - help: Description of the setting, and what it does
	 * - default: Default value, for when it's not set yet
	 */
	public function get_properties()
	{
		$properties = parent::get_properties();

		$properties[] = array(
			'name'	=>	'title',
			'type'	=>	'textbox',
			'label'	=>	lang('Title'),
		);
		// Internal - no type means it won't show in configure dialog
		$properties[] = array(
			'name' => 'note'
		);
		return $properties;
	}

	public function get_description()
	{
		return array(
			'displayName' => lang('Note'),
			'title'       => $this->context['title'],
			'description' => lang('A quick note')
		);
	}

	public function get_type()
	{
		return 'et2-portlet-note';
	}
}