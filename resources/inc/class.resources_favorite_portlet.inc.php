<?php

/*
 * Egroupware - Resources - A portlet for displaying a list of entries
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package resources
 * @subpackage home
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

/**
 * The resources_list_portlet uses a nextmatch / favorite
 * to display a list of entries.
 */
class resources_favorite_portlet extends home_favorite_portlet
{

	/**
	 * Construct the portlet
	 *
	 */
	public function __construct(Array &$context = array(), &$need_reload = false)
	{
		$context['appname'] = 'resources';
		
		// Let parent handle the basic stuff
		parent::__construct($context,$need_reload);

		$ui = new resources_ui();

		$this->context['template'] = 'resources.show.rows';
		$this->nm_settings += array(
			'get_rows'	=> 'resources.resources_bo.get_rows',
			// Use a different template so it can be accessed from client side
			'template'	=> 'resources.show.rows',
			// Don't store in session, there's no point
			'store_state'    => false,
			// Use a reduced column set for home, user can change if needed
			'default_cols'   => 'image,name_short_description',
			'row_id'         => 'res_id',
			'row_modified'   => 'ts_modified',

			'no_cat'         => true,
			'filter_label'   => lang('Category'),
			'filter2'        => -1,
		);
	}

	public function exec($id = null, etemplate_new &$etemplate = null)
	{
		$ui = new resources_ui();

		$this->context['sel_options']['filter']= array(''=>lang('all categories'))+(array)$ui->bo->acl->get_cats(EGW_ACL_READ);
		$this->context['sel_options']['filter2'] = resources_bo::$filter_options;
		if(!$content['nm']['filter2'])
		{
			$content['nm']['filter2'] = key(resources_bo::$filter_options);
		}
		$this->nm_settings['actions'] = $ui->get_actions();

		parent::exec($id, $etemplate);
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
		$ui = new resources_ui();

		// This is just copy+pasted from resources_ui line 816, but we don't want
		// the etemplate exec to fire again.
		if (is_array($content) && isset($content['nm']['rows']['document']))  // handle insert in default document button like an action
		{
			list($id) = @each($content['nm']['rows']['document']);
			$content['nm']['action'] = 'document';
			$content['nm']['selected'] = array($id);
		}
		if ($content['nm']['action'])
		{
			// remove sum-* rows from checked rows
			$content['nm']['selected'] = array_filter($content['nm']['selected'], function($id)
			{
				return $id > 0;
			});
			if (!count($content['nm']['selected']) && !$content['nm']['select_all'])
			{
				$msg = lang('You need to select some entries first!');
				egw_json_response::get()->apply('egw.message',array($msg,'error'));
			}
			else
			{
				$success = $failed = $action_msg = null;
				if ($ui->action($content['nm']['action'],$content['nm']['selected'],$content['nm']['select_all'],
					$success,$failed,$action_msg,'index',$msg))
				{
					$msg .= lang('%1 resource(s) %2',$success,$action_msg);

					egw_json_response::get()->apply('egw.message',array($msg,'success'));
					foreach($content['nm']['selected'] as &$id)
					{
						$id = 'resources::'.$id;
					}
					// Directly request an update - this will get resources tab too
					egw_json_response::get()->apply('egw.dataRefreshUIDs',array($content['nm']['selected']));
				}
				elseif(empty($msg))
				{
					$msg .= lang('%1 resource(s) %2, %3 failed because of insufficent rights !!!',$success,$action_msg,$failed);
					egw_json_response::get()->apply('egw.message',array($msg,'error'));
				}
			}
		}

	}
 }