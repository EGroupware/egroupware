<?php
/**
 * EGroupware EMailAdmin: User interface
 *
 * @link http://www.stylite.de
 * @package emailadmin
 * @author Klaus Leithoff <kl@stylite.de>
 * @copyright (c) 2009-10 by Klaus Leithoff <kl-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * User interface
 */
class emailadmin_ui extends emailadmin_bo
{	
	var $public_functions = array
	(
		'index'			=> True,
		'add'			=> True,
		'delete'		=> True,
		'edit'			=> True,
		'save'			=> True,
		'listProfiles'	=> True,
	);
	
	function __construct()
	{
		parent::__construct();
	}

	/**
 	 * Main emailadmin page
	 *
	 * @param array $content=null
	 * @param string $msg=null
	 */
	function index(array $content=null,$msg=null)
	{
		$accountID = false;
		$groupID = false;
		$filter = '';
		$rowsfound = 0;
		if(is_int(intval($_GET['account_id'])) && !empty($_GET['account_id']))
		{
			if ( intval($_GET['account_id']) < 0 ) {
				$groupID =  intval($_GET['account_id']);
				$filter['ea_group'] = intval($_GET['account_id']);
			} else {
				$accountID = intval($_GET['account_id']);
				$filter['ea_user'] = intval($_GET['account_id']);
			}
			$r = parent::search($filter);
			$rowsfound = count($r);
		}
		if ($rowsfound)
		{
			if (($accountID || !empty($groupID)) && $rowsfound == 1) 
			{
				$linkData = array
				(
					'menuaction'    => 'emailadmin.emailadmin_ui.edit',
					'profileid' => $r[0]['ea_profile_id']
				);
				$addJavaScript = "<script type=\"text/javascript\">".'egw_openWindowCentered2(\''.$GLOBALS['egw']->link('/index.php',$linkData).'\',\'ea_editProfile\',700,600);</script>';
			}
		} else {
			if ($accountID || !empty($groupID)) {
				$linkData = array
				(
					'menuaction'    => 'emailadmin.emailadmin_ui.edit',
					'account_id' => ($accountID ? $accountID : $groupID)
				);
				$addJavaScript = "<script type=\"text/javascript\">".'egw_openWindowCentered2(\''.$GLOBALS['egw']->link('/index.php',$linkData).'\',\'ea_addProfile\',700,600);</script>';
			}
		}
		if ($accountID || !empty($groupID)) {
			$linkData = array
			(
				'menuaction'    => 'emailadmin.emailadmin_ui.index',
			);
			$listLink = '<a href="'.$GLOBALS['egw']->link('/index.php',$linkData).
				'" onClick="return confirm(\''.lang('Do you really want to reset the filter for the Profile listing').'?\')">'.
				lang('reset filter').'</a>';

			if ($GLOBALS['egw_info']['user']['apps']['admin']) {
				$linkData = array
				(
					'menuaction'    => 'admin.uiaccounts.list_'.($accountID ? 'users' : 'groups'),
				);
				$listLink2 = '<a href="'.$GLOBALS['egw']->link('/index.php',$linkData).'">'.($accountID ? lang('Back to Admin/Userlist'): lang('Back to Admin/Grouplist')).'</a>';
			}
			unset($r);
			$subtitle = ($accountID || !empty($groupID) ? ' '.($accountID ? lang('filtered by Account') :  lang('filtered by Group')).' ['.$listLink.']'.' ['.$listLink2.']': '');
		}
		//_debug_array($content);
		$tpl = new etemplate('emailadmin.index');
		if (!is_array($content))
		{
			$content = array(
				'nm' => $GLOBALS['egw']->session->appsession('index',parent::APP),
			);
			if (!is_array($content['nm']))
			{
				$content['nm'] = array(
					'get_rows'       => 'emailadmin.emailadmin_ui.get_rows',  // I  method/callback to request the data for the rows
					'no_filter'      => True,    // nofilter
					'no_filter2'     => True,   // I  disable the 2. filter (params are the same as for filter)
					'no_cat'         => True,   // I  disable the cat-selectbox
					'lettersearch'   => True,   // I  show a lettersearch
					'searchletter'   => false,  // I0 active letter of the lettersearch or false for [all]
					'start'          => 0,      // IO position in list
					'order'          => 'ea_order, ea_profile_id', // IO name of the column to sort after (optional for the sortheaders)
					'sort'           => 'ASC',  // IO direction of the sort: 'ASC' or 'DESC'
					//'default_cols'   => '!comment,ctime',   // I  columns to use if there's no user or default pref (! as first char uses all but the columns listed)
					'csv_fields'     => false, // I  false=disable csv export, true or unset=enable it with auto-detected fieldnames,
						//or array with name=>label or name=>array('label'=>label,'type'=>type) pairs (type is a eT widget-type)
				);
			}
		}
		elseif(isset($content['nm']['rows']['delete']))
		{
			list($profileids) = each($content['nm']['rows']['delete']);
			unset($content['nm']['rows']['delete']);
			if ($profileids && self::delete($profileids))
			{
			    $content['msg'] = lang('%1 entries deleted.',1);
			}
			else
			{
			    $content['msg'] = lang('Error deleting entry!');
			}
		}
		elseif(isset($content['delete']))
		{
			unset($content['delete']);
			if (($deleted = self::delete($content['nm']['rows']['selected'])))
			{
				$content['msg'] = lang('%1 entries deleted.',$deleted);
			}
			else
			{
				$content['msg'] = lang('Error deleting entry!');
			}
		}

		if (isset($_GET['msg'])) $msg = $_GET['msg'];
		$content['msg'] .= $msg;
		/*
		if ($content['action'] || $content['nm']['rows'])
		{
			if ($content['action'])
			{
				// SOME ACTION AS EDIT, DELETE, ...
				$content['msg'] = self::action($content['action'],$content['nm']['rows']['checked']);
				unset($content['action']);
			}
			elseif($content['nm']['rows']['delete'])
			{
				$content['msg'] = self::action('delete',array_keys($content['nm']['rows']['delete']));
			}
			unset($content['nm']['rows']);
		}
		*/
		if ($content['AddProfile'])
		{
			unset($content['AddProfile']);
		}
		if ($content['button'])
		{
			if ($content['button'])
			{
				list($button) = each($content['button']);
				unset($content['button']);
			}
			switch($button)
			{
				default:
					break;
			}
		}
		$sel_options['ea_smtp_type']=parent::getSMTPServerTypes();
		$sel_options['ea_imap_type']=parent::getIMAPServerTypes(false);
		$sel_options['ea_appname']	=self::getAllowedApps();
		// setting for the top of the app, etc.
		$content['addJavaScript'] = $addJavaScript;
		$content['subtitle'] = $subtitle;
		if (!empty($filter)) foreach ($filter as $fk => $fv) $content['nm']['col_filter'][$fk] = $fv;
		// seTting the Title of the app
		$GLOBALS['egw_info']['flags']['app_header'] = lang('emailadmin');
		$tpl->exec('emailadmin.emailadmin_ui.index',$content,$sel_options,$readonlys,array('nm' => $content['nm']));		
	}

	/**
	 * query the table
	 *
	 * reimplemented from so_sql to disable action-buttons based on the acl and make some modification on the data
	 *
	 * @param array &$query
	 * @param array &$rows returned rows/cups
	 * @param array &$readonlys eg. to disable buttons based on acl
	 * @param boolean $id_only=false if true only return (via $rows) an array of ids, dont save state to session
	 * @return int total number of rows matching the selection
	 */
	function get_rows(&$query_in,&$rows,&$readonlys,$id_only=false)
	{
		$query = $query_in;
		$filteredby = '';
		if ($query['searchletter']) // only show rows if the order-criteria starts with the given letter
		{
			$query['col_filter'][] = (in_array($query['order'],parent::$numericfields) || (is_string($query['order']) && !(strpos($query['order'],',')===false)) ? 'ea_description' : $query['order']).' '.
				$GLOBALS['egw']->db->capabilities['case_insensitive_like'].' '.$GLOBALS['egw']->db->quote($query['searchletter'].'%');
			if (in_array($query['order'],parent::$numericfields)) $query_in['order'] = $query['order'] = 'ea_description';
			$filteredby = $query['order'].' '.lang('starts with').' '.$query['searchletter'];
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('emailadmin').($filteredby? ' - '.$filteredby:'');
		$total = parent::get_rows($query,$rows,$readonlys);
		return $total;
	}

	static function getAllowedApps()
	{
		$applications = array(
			'calendar'	=> $GLOBALS['egw_info']['apps']['calendar']['title'],
			'felamimail' 	=> $GLOBALS['egw_info']['apps']['felamimail']['title'],
		//	'notifications'	=> $GLOBALS['egw_info']['apps']['notifications']['title'],
		);
		asort($applications);
		return $applications = array_merge(array('' => lang('any application')),$applications);
	}

	static function getIMAPLoginTypes($serverclass='defaultimap')
	{
		if (empty($serverclass)) $serverclass = 'defaultimap';
		//error_log(__METHOD__.' called with:'.$serverclass." with capabilities:".parent::$IMAPServerType[$serverclass]['imapcapabilities']);
		$returnval = array(
			'standard'	=> lang('username (standard)'),
			'vmailmgr'	=> lang('username@domainname (Virtual MAIL ManaGeR)'),
			'admin'		=> lang('Username/Password defined by admin'),
			'uidNumber' => lang('UserId@domain eg. u1234@domain'),
		);
		if (strpos($serverclass,'_') === false)
		{
			include_once(EGW_INCLUDE_ROOT.'/emailadmin/inc/class.'.$serverclass.'.inc.php');
		}
		if (!empty($serverclass) && stripos(constant($serverclass.'::CAPABILITIES'),'logintypeemail') !== false)
		{
			$returnval['email']	= lang('use Users eMail-Address (as seen in Useraccount)');
		}
		return $returnval;
	}

	function edit($content=null)
	{
		//$this->editProfile($profileid);
		$etpl = new etemplate(parent::APP.'.edit');
		if(!is_array($content))
		{
			$rowfound = false;
			$filter = array();
			if(is_int(intval($_GET['account_id'])) && !empty($_GET['account_id']))
			{
				$GLOBALS['egw']->accounts->get_account_name(intval($_GET['account_id']),$lid,$fname,$lname);
				if ( intval($_GET['account_id']) < 0 ) {
					$groupID =  intval($_GET['account_id']);
					$content['ea_group'] = $filter['ea_group'] = $groupID;
				} else {
					$accountID = intval($_GET['account_id']);
					$content['ea_user'] = $filter['ea_user'] = $accountID;
				}
				$content['ea_active'] = 'yes';
				$content['ea_imap_login_type'] = 'admin';
				$content['ea_description'] = common::display_fullname($lid,$fname,$lname,intval($_GET['account_id']));
			}
			if (!empty($_GET['profileid']))
			{
				$profileID = intval($_GET['profileid']);
				$filter['ea_profile_id'] = $profileID;
				$rowfound = parent::read($filter);
			}
			else
			{
				$content['ea_user_defined_accounts'] = "yes";
			}
		}
		else
		{
			$rowfound = true;
			// handle action/submit buttons
			if (isset($content['delete']))
			{
				unset($content['delete']);
				$button = 'delete';
			}
			if (isset($content['cancel']))
			{
				unset($content['cancel']);
				$button = 'cancel';
			}
			if (isset($content['apply']))
			{
				unset($content['apply']);
				$button = 'apply';
			}
			if (isset($content['save']))
			{
				unset($content['save']);
				$button = 'save';
			}
			unset($content['manage_stationery_templates']);
			//unset($content['tabs']);
			if (!empty($content['smtp_senders_email']))
			{
				$content['ea_smtp_auth_username'] = $content['ea_smtp_auth_username'].';'.$content['smtp_senders_email'];
				unset($content['smtp_senders_email']);
			}
			$this->data = $content;
			switch ($button)
			{
				case 'delete':
					if (($deleted = self::delete($content['ea_profile_id'])))
					{
						$msg = lang('%1 entries deleted.',$deleted);
					}
					else
					{
						$msg = lang('Error deleting entry!');
					}
					$js = "opener.location.href='".$GLOBALS['egw']->link('/index.php',array(
						'menuaction' => parent::APP.'.emailadmin_ui.index',
						'msg'        => $msg,
					))."';";
					$js .= 'window.close();';
					echo "<html>\n<body>\n<script>\n$js\n</script>\n</body>\n</html>\n";
					$GLOBALS['egw']->common->egw_exit();
					break;				
				case 'cancel':
					$js .= 'window.close();';
					echo "<html>\n<body>\n<script>\n$js\n</script>\n</body>\n</html>\n";
					$GLOBALS['egw']->common->egw_exit();
					break;
				case 'apply':
				case 'save':
					if ($etpl->validation_errors()) break;  // the user need to fix the error, before we can save the entry
					//_debug_array($this->data);
					if (parent::save() != 0)
					{
						$msg = lang('Error saving the entry!!!');
						$button = '';
					}
					else
					{
						$msg = lang('Entry saved');
					}
					$js = "opener.location.href='".$GLOBALS['egw']->link('/index.php',array(
						'menuaction' => parent::APP.'.emailadmin_ui.index',
						'msg'        => $msg,
					))."';";
					if ($button == 'save')
					{
						$js .= 'window.close();';
						echo "<html>\n<body>\n<script>\n$js\n</script>\n</body>\n</html>\n";
						$GLOBALS['egw']->common->egw_exit();
						break;
					}
					$row;
			}
		}
		if ($rowfound) $content = array_merge($this->data,array());
		$preserv['smtpcapabilities'] = $content['smtpcapabilities'] = 
			constant((!empty($content['ea_smtp_type'])?$content['ea_smtp_type']:'defaultsmtp').'::CAPABILITIES');
		$preserv['imapcapabilities'] = $content['imapcapabilities'] = 
			constant((!empty($content['ea_imap_type'])?$content['ea_imap_type']:'defaultimap').'::CAPABILITIES');
		if (!empty($msg)) $content['msg'] = $msg;
		list($content['ea_smtp_auth_username'],$content['smtp_senders_email']) = explode(';',$content['ea_smtp_auth_username']);
		$preserv['ea_profile_id'] = $content['ea_profile_id'];
		//$preserv['ea_stationery_active_templates'] = $content['ea_stationery_active_templates'];
		$sel_options['ea_smtp_type']=parent::getSMTPServerTypes();
		$sel_options['ea_imap_type']=parent::getIMAPServerTypes(false);
		$sel_options['ea_appname']	=self::getAllowedApps();
		$sel_options['ea_imap_login_type'] = self::getIMAPLoginTypes($content['ea_imap_type']);
		// Stationery settings
		$bostationery = new felamimail_bostationery();
		$sel_options['ea_stationery_active_templates'] = $bostationery->get_stored_templates();
		// setup history
		$content['history'] = array(
			'id'	=>	$content['ea_profile_id'],
			'app'	=>	'emailadmin'
		);
		//_debug_array($content);
		foreach($this->tracking->field2label as $field => $label) {
			$sel_options['status'][$field] = lang($label);
		}
		/*			
		$content['stored_templates'] = html::checkbox_multiselect(
			'ea_stationery_active_templates',$content['ea_stationery_active_templates']
			,$bostationery->get_stored_templates(),true,'',3,true,'width: 100%;');

		$content['manage_stationery_templates'] =
			html::a_href(
				lang('manage stationery templates'),
				'/index.php?menuaction=etemplate.editor.edit',
				array('name' => 'felamimail.stationery'),
				'target="_blank"'
			);
		*/
		//_debug_array($this->data);
		return $etpl->exec(parent::APP.'.emailadmin_ui.edit',$content,$sel_options,$readonlys,$preserv,2);
	}

	function add()
	{
		$this->edit();
	}

	function delete($profileid=null)
	{
		$_profileID = ($profileid ? $profileid : (int)$_GET['profileid']);
		if (empty($_profileID)) return 0;
		return parent::delete($_profileID);
	}

	function listProfiles()
	{
		$GLOBALS['egw']->hooks->register_all_hooks();
		self::index();
	}
}
