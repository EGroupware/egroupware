<?php
/**
 * EGroupware - messenger - PHP UI definition
 *
 * @link http://www.egroupware.org
 * @author Hadi Nategh <hn[at]stylite.de>
 * @package messenger
 * @subpackage setup
 * @copyright (c) 2014 by Stylite AG
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: $
 */
	
class messenger_ui extends admin_accesslog
{
	/**
	 * Public functions
	 *
	 * @var type
	 */
	var $public_functions = array(
		'dialog' => true,
		'index' => true,
	);
	
	/**
	 * Constructor
	 */
	function __construct()
	{
		parent::__construct();
	}
	
	/**
	 * Open a conversation dialog with Video,Audio and Message exchange availability
	 *
	 * @param type $content
	 */
	function dialog($content=null)
	{
		
		egw_framework::csp_connect_src_attrs(array("http://".$_SERVER['HTTP_HOST'],"ws://".$_SERVER['HTTP_HOST']));
		
		$tmpl = new etemplate_new('messenger.dialog');
		$content['account_id'] = $_GET['id'];
		$content['type'] = $_GET['type'];
		
		
		$tmpl->exec('messenger.messenger_ui.dialog', $content,array(),array(),array(),array(),2);
	}
	
	/**
	 * List of available users
	 *
	 * @param type $content
	 */
	function index($content=null)
	{
		egw_framework::csp_connect_src_attrs(array("http://".$_SERVER['HTTP_HOST'],"ws://".$_SERVER['HTTP_HOST']));
		
		if (!isset($content))
		{
			$content['nm'] = array (
				'get_rows'       =>	'messenger.messenger_ui.get_rows',
				'no_filter'      => True,	// I  disable the 1. filter
				'no_filter2'     => True,	// I  disable the 2. filter (params are the same as for filter)
				'no_cat'         => True,	// I  disable the cat-selectbox
				'sort'			 => 'DESC',
				'session_list'	 => 'active', // Choose active users from session
				'row_id'         => 'account_id',
				'actions'        => $this->get_actions(),
			);
		}
		else {
			
		}

		$tmpl = new etemplate_new('messenger.index');
		
		// Initialise the toolbar actions
		$tmpl->setElementAttribute('dialog[indexToolbar]', 'actions', self::get_toolbarActions());
		
		return $tmpl->exec('messenger.messenger_ui.index', $content, array(),array());
	}
	
	/**
	 * Sends notification from calller to callee, and opens a conversation dialog for callee
	 *
	 * @param array $_param
	 */
	function ajax_makeChat (array $_param)
	{
		if (is_array($_param))
		{
			$account_id = (int)$_param[0];
			$chatType = $_param[1];
		}
		if ($account_id && is_int($account_id))
		{
			$egwPush_Obj = new egw_json_push($account_id);
			$egwPush_Obj->call("egw.open_link",'messenger.messenger_ui.dialog&id='.$GLOBALS['egw_info']['user']['account_id'].'&type='.$chatType,'_blank','600x750','messenger',true);
		}
	}
	
	/**
	 * Create actions for nm action
	 *
	 * @return array an array of actions
	 */
	public static function get_actions ()
	{
		$actions = array(
			'chat' => array (
				'caption' => 'Send MSG',
				'default' => true,
				'url' => 'menuaction=messenger.messenger_ui.dialog&id=$id',
				'popup' => egw_link::get_registry('messenger', 'dialog_popup'),
			),
			'call' => array (
				'caption' => 'Call',
				'onExecute' => 'javaScript:app.messenger.makeCall',
				'popup' => egw_link::get_registry('messenger', 'dialog_popup')
			)		
		);
		return $actions;
	}
	
	/**
	 * query rows for the nextmatch widget
	 *
	 * @param array $query with keys 'start', 'search', 'order', 'sort', 'col_filter'
	 * @param array &$rows returned rows/competitions
	 * @param array &$readonlys eg. to disable buttons based on acl, not use here, maybe in a derived class
	 * @return int total number of rows
	 */
	function get_rows($query,&$rows,&$readonlys)
	{
		// Avoid to show own user name on the list
		$query['col_filter'][4] = "(account_id !=". $GLOBALS['egw_info']['user']['account_id']. ")";
		
		$total = parent::get_rows($query, $rows, $readonlys);
		return $total || array();
	}

	/**
	 * This function generates toolbar actions
	 *
	 * @return array an array of actions
	 */
	public static function get_toolbarActions ()
	{
		$actions  = array (
			'call' => array(
				'caption' => 'Call',
				'icon' => 'call',
				'onExecute' => 'javaScript:app.messenger.toolbarActions',
				'popup' => egw_link::get_registry('messenger', 'dialog_popup'),
			),
			'vcall' => array(
				'caption' => 'Video call',
				'icon' => 'video_call',
				'onExecute' => 'javaScript:app.messenger.toolbarActions',
				'popup' => egw_link::get_registry('messenger', 'dialog_popup'),
			),
			'hangup' => array (
				'caption' => 'Hangup',
				'icon' => 'hangup',
				'onExecute' => 'javaScript:app.messenger.toolbarActions',
			),
			'video' => array(
				'caption' => 'Video',
				'icon' => 'camera',
				'checkbox' => true,
				'onExecute' => 'javaScript:app.messenger.toolbarActions',
			),
			'micro' => array(
				'caption' => 'Microphone',
				'icon' => 'microphone',
				'checkbox' => true,
				'onExecute' => 'javaScript:app.messenger.toolbarActions',
			),

		);
		return $actions;
	}

}