<?php

/*
 * Egroupware - Infolog - A portlet for displaying a list of portlet entries
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package infolog
 * @subpackage home
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

/**
 * The infolog_list_portlet uses a nextmatch / favorite
 * to display a list of entries.
 */
class infolog_favorite_portlet extends home_favorite_portlet
{

	/**
	 * Construct the portlet
	 *
	 */
	public function __construct(Array &$context = array(), &$need_reload = false)
	{
		$context['appname'] = 'infolog';
		
		// Let parent handle the basic stuff
		parent::__construct($context,$need_reload);

		$ui = new infolog_ui();

		$this->context['template'] = 'infolog.index.rows';
		$this->nm_settings += array(
			'get_rows'	=> 'infolog.infolog_ui.get_rows',
			// Use a different template so it can be accessed from client side
			'template'	=> 'infolog.home',
			// Don't overwrite infolog
			'session_for'	=> 'home',
			
			// Allow add actions even when there's no rows
			'placeholder_actions'	=> array(),
			'sel_options' => array(
				'info_type'     => $ui->bo->enums['type'],
				'pm_id'      => array(lang('No project')),
				'info_priority' => $ui->bo->enums['priority'],
			)
		);
	}

	/**
	 * Here we need to handle any incoming data.  Setup is done in the constructor,
	 * output is handled by parent.
	 *
	 * @param type $id
	 * @param etemplate_new $etemplate
	 */
	public static function process($values = array())
	{
		parent::process($values);
		$ui = new infolog_ui();
		if (is_array($values) && !empty($values['nm']['multi_action']))
		{
			if (!count($values['nm']['selected']) && !$values['nm']['select_all'])
			{
				egw_framework::message(lang('You need to select some entries first'));
			}
			else
			{
				// Some processing to add values in for links and cats
				$multi_action = $values['nm']['multi_action'];
				// Action has an additional action - add / delete, etc.  Buttons named <multi-action>_action[action_name]
				if(in_array($multi_action, array('link', 'responsible')))
				{
					// eTemplate ignores the _popup namespace, but et2 doesn't
					if($values[$multi_action.'_popup'])
					{
						$popup =& $values[$multi_action.'_popup'];
					}
					else
					{
						$popup =& $values;
					}
					$values['nm']['multi_action'] .= '_' . key($popup[$multi_action . '_action']);
					if($multi_action == 'link')
					{
						$popup[$multi_action] = $popup['link']['app'] . ':'.$popup['link']['id'];
					}
					else if(is_array($popup[$multi_action]))
					{
						$popup[$multi_action] = implode(',',$popup[$multi_action]);
					}
					$values['nm']['multi_action'] .= '_' . $popup[$multi_action];
					unset($values[$multi_action.'_popup']);
					unset($values[$multi_action]);
				}
				$success = $failed = $action_msg = null;
				if ($ui->action($values['nm']['multi_action'], $values['nm']['selected'], $values['nm']['select_all'],
					$success, $failed, $action_msg, $values['nm'], $msg, $values['nm']['checkboxes']['no_notifications']))
				{
					$msg .= lang('%1 entries %2',$success,$action_msg);
					egw_json_response::get()->apply('egw.message',array($msg,'success'));
					foreach($values['nm']['selected'] as &$id)
					{
						$id = 'infolog::'.$id;
					}
					// Directly request an update - this will get infolog tab too
					egw_json_response::get()->apply('egw.dataRefreshUIDs',array($values['nm']['selected']));
				}
				elseif(is_null($msg))
				{
					$msg .= lang('%1 entries %2, %3 failed because of insufficent rights !!!',$success,$action_msg,$failed);
					egw_json_response::get()->apply('egw.message',array($msg,'error'));
				}
				elseif($msg)
				{
					$msg .= "\n".lang('%1 entries %2, %3 failed.',$success,$action_msg,$failed);
					egw_json_response::get()->apply('egw.message',array($msg,'error'));
				}
				unset($values['nm']['multi_action']);
				unset($values['nm']['select_all']);
			}
		}
	}
 }