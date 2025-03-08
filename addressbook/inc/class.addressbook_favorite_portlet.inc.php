<?php
/**
 * Egroupware - Addressbook - A portlet for displaying a list of entries
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package addressbook
 * @subpackage home
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Acl;
use EGroupware\Api\Etemplate;

/**
 * The addressbook_list_portlet uses a nextmatch / favorite
 * to display a list of entries.
 */
class addressbook_favorite_portlet extends home_favorite_portlet
{

	/**
	 * Construct the portlet
	 *
	 */
	public function __construct(Array &$context = array(), &$need_reload = false)
	{
		$context['appname'] = 'addressbook';

		// Let parent handle the basic stuff
		parent::__construct($context,$need_reload);

		$ui = new addressbook_ui();

		$this->context['template'] = 'addressbook.index.rows';
		$this->context['sel_options'] = array();
		foreach($ui->content_types as $tid => $data)
		{
			$this->context['sel_options']['tid'][$tid] = $data['name'];
		}
		$this->nm_settings += array(
			'get_rows'	=> 'addressbook_favorite_portlet::get_rows',
			// Use a different template so it can be accessed from client side
			'template'	=> 'addressbook.index.rows',
			'default_cols'   => 'n_fileas_n_given_n_family_n_family_n_given_org_name_n_family_n_given_n_fileas,'.
				'tel_work_tel_cell_tel_home,url_email_email_home',
		);
	}

	public function exec($id = null, Etemplate &$etemplate = null)
	{
		$ui = new addressbook_ui();
		$this->context['sel_options']['filter'] = $this->context['sel_options']['owner'] = $ui->get_addressbooks(Acl::READ,lang('All'));
		$this->context['sel_options']['filter2'] = $ui->get_lists(Acl::READ,array('' => lang('none')));
		$this->nm_settings['actions'] = $ui->get_actions($this->nm_settings['col_filter']['tid'], $this->nm_settings['org_view']);

		parent::exec($id, $etemplate);
	}

	/**
	 * Override from addressbook to clear the app header
	 *
	 * @param type $query
	 * @param type $rows
	 * @param type $readonlys
	 * @return integer Total rows found
	 */
	public static function get_rows(&$query, &$rows, &$readonlys)
	{
		$ui = new addressbook_ui();
		$total = $ui->get_rows($query, $rows, $readonlys);
		unset($GLOBALS['egw_info']['flags']['app_header']);
		return $total;
	}

	/**
	 * Here we need to handle any incoming data.  Setup is done in the constructor,
	 * output is handled by parent.
	 *
	 * @param type $id
	 * @param Etemplate $etemplate
	 */
	public static function process($values = array())
	{
		parent::process($values);
		$ui = new addressbook_ui();
		if (is_array($values) && !empty($values['nm']['action']))
		{
			if (!count($values['nm']['selected']) && !$values['nm']['select_all'])
			{
				Framework::message(lang('You need to select some entries first'));
			}
			else
			{
				// Some processing to add values in for links and cats
				$success = $failed = $action_msg = $msg = null;
				if ($ui->action($values['nm']['action'],$values['nm']['selected'],$values['nm']['select_all'],
						$success,$failed,$action_msg,'index',$msg,$values['nm']['checkboxes']))
				{
					$msg .= lang('%1 contact(s) %2',$success,$action_msg);
					Api\Json\Response::get()->apply('egw.message',array($msg,'success'));
					foreach($values['nm']['selected'] as &$id)
					{
						$id = 'addressbook::'.$id;
					}
					// Directly request an update - this will get addressbook tab too
					Api\Json\Response::get()->apply('egw.dataRefreshUIDs',array($values['nm']['selected']));
				}
				elseif(is_null($msg))
				{
					$msg .= lang('%1 entries %2, %3 failed because of insufficient rights !!!',$success,$action_msg,$failed);
					Api\Json\Response::get()->apply('egw.message',array($msg,'error'));
				}
				elseif($msg)
				{
					$msg .= "\n".lang('%1 entries %2, %3 failed.',$success,$action_msg,$failed);
					Api\Json\Response::get()->apply('egw.message',array($msg,'error'));
				}
				unset($values['nm']['action']);
				unset($values['nm']['select_all']);
			}
		}
	}
 }