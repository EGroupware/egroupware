<?php

/*
 * Egroupware - Filemanager - A portlet for displaying a list of entries
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package filemanager
 * @subpackage home
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

/**
 * The filemanager_list_portlet uses a nextmatch / favorite
 * to display a list of entries.
 */
class filemanager_favorite_portlet extends home_favorite_portlet
{

	/**
	 * Construct the portlet
	 *
	 */
	public function __construct(Array &$context = array(), &$need_reload = false)
	{
		$context['appname'] = 'filemanager';
		
		// Let parent handle the basic stuff
		parent::__construct($context,$need_reload);

		$ui = new filemanager_ui();

		$this->nm_settings += array(
			'get_rows'       => 'filemanager.filemanager_ui.get_rows',
			'csv_export'     => true,
			// Use a different template so it can be accessed from client side
			'template'       => ($this->nm_settings['view'] == 'tile' ? 'filemanager.tile' : 'filemanager.home.rows' ),
			// Filemanager needs this header, it's an important component for actions, but we reduce it to the minimum
			'header_left'    => 'filemanager.home.header_left',
			// Use a reduced column set for home, user can change if needed
			'default_cols'   => 'mime,name',
			'no_cat'         => true,
			'no_filter2'     => true,
			'row_id'         => 'path',
			'row_modified'   => 'mtime',
			'parent_id'      => 'dir',
			'is_parent'      => 'mime',
			'is_parent_value'=> egw_vfs::DIR_MIME_TYPE,
			'placeholder_actions' => array('mkdir','file_drop_mail','file_drop_move','file_drop_copy','file_drop_symlink')
		);
	}

	public function exec($id = null, etemplate_new &$etemplate = null)
	{

		$this->context['sel_options']['filter'] = array(
			'' => 'Current directory',
			'2' => 'Directories sorted in',
			'3' => 'Show hidden files',
			'4' => 'All subdirectories',
			'5' => 'Files from links',
			'0'  => 'Files from subdirectories',
		);
		$this->nm_settings['actions'] = filemanager_ui::get_actions();

		$this->nm_settings['home_dir'] = filemanager_ui::get_home_dir();
		parent::exec($id, $etemplate);
	}

	/**
	 * Override from filemanager to clear the app header
	 *
	 * @param type $query
	 * @param type $rows
	 * @param type $readonlys
	 * @return integer Total rows found
	 */
	public static function get_rows(&$query, &$rows, &$readonlys)
	{
		$ui = new filemanager_ui();
		$total = $ui->get_rows($query, $rows, $readonlys);
		// Change template to match selected view
		if($query['view'])
		{
			$query['template'] = ($query['view'] == 'row' ? 'filemanager.home.rows' : 'filemanager.tile');
		}
		unset($GLOBALS['egw_info']['flags']['app_header']);
		return $total;
	}

	/**
	 * Here we need to handle any incoming data.  Setup is done in the constructor,
	 * output is handled by parent.
	 *
	 * @param type $id
	 * @param etemplate_new $etemplate
	 */
	public static function process($content = array())
	{
		parent::process($content);

		// This is just copy+pasted from filemanager_ui line 378, but we don't want
		// the etemplate exec to fire again.
		if ($content['nm']['action'])
		{
			$msg = filemanager_ui::action($content['nm']['action'],$content['nm']['selected'],$content['nm']['path']);
			if($msg) egw_json_response::get()->apply('egw.message',array($msg));
			foreach($content['nm']['selected'] as &$id)
			{
				$id = 'filemanager::'.$id;
			}
			// Directly request an update - this will get filemanager tab too
			egw_json_response::get()->apply('egw.dataRefreshUIDs',array($content['nm']['selected']));
		}
	}
 }