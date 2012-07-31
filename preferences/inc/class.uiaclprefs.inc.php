<?php
/**
 * EGroupware - UI for setting ACL
 *
 * @link http://www.egroupware.org
 * @package preferences
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * UI for setting ACL
 */
class uiaclprefs
{
	/**
	 * Instance of acl class
	 *
	 * @var acl
	 */
	var $acl;
	/**
	 * Instance of Template class
	 *
	 * @var Template
	 */
	var $template;

	/**
	 * Functions callable via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array('index' => True);

	/**
	 * UI to set / show ACL
	 *
	 */
	function index()
	{
		$query_types = array(
			'all' => 'all fields',
			'lid' => 'LoginID',
			'start' => 'start with',
			'exact' => 'exact',
		);

		$acl_app	= get_var('acl_app',array('POST','GET'));
		$start		= get_var('start',array('POST','GET'),0);
		$query		= get_var('query',array('POST','GET'));
		$owner		= get_var('owner',array('POST','GET'),$GLOBALS['egw_info']['user']['account_id']);
		$search_type= get_var('search_type',array('POST','GET'));

		if (!$acl_app)
		{
			$acl_app            = 'preferences';
			$acl_app_not_passed = True;
		}
		else
		{
			translation::add_app($acl_app);
		}
		// make acl called via sidebox menu of an app, to behave like a part of that app
		$referer = $_POST['referer'];
		if (!$referer)
		{
			$referer = common::get_referer('/preferences/index.php');
		}
		//echo '<p align="right">'."search_type='$search_type'</p>\n";

		$GLOBALS['egw_info']['flags']['currentapp'] = $acl_app;

		if ($acl_app_not_passed)
		{
			if(is_object($GLOBALS['egw']->log))
			{
				$GLOBALS['egw']->log->message(array(
					'text' => 'F-BadmenuactionVariable, failed to pass acl_app.',
					'line' => __LINE__,
					'file' => __FILE__
				));
				$GLOBALS['egw']->log->commit();
			}
		}

		if (($GLOBALS['egw_info']['server']['deny_user_grants_access'] || $owner != $GLOBALS['egw_info']['user']['account_id'])
			&& !isset($GLOBALS['egw_info']['user']['apps']['admin']) || $acl_app_not_passed)
		{
			$GLOBALS['egw']->framework->render('<center><b>' . lang('Access not permitted') . '</b></center>',null,true);
			return;
		}

		$owner_name = common::grab_owner_name($owner);
		if(!($no_privat_grants = $GLOBALS['egw']->accounts->get_type($owner) == 'g'))
		{
			// admin setting acl-rights is handled as with group-rights => no private grants !!
			$no_privat_grants = $owner != $GLOBALS['egw_info']['user']['account_id'];
		}
		$this->acl = new acl((int)$owner);
		// should we enumerate group acl (does app use it), eg. addressbook does NOT use group ACL's but group addressbooks
		$not_enum_group_acls = $acl_app == 'addressbook' ? true : $GLOBALS['egw']->hooks->single('not_enum_group_acls',$acl_app);
		$this->acl->read_repository($not_enum_group_acls);

		if ($_POST['save'] || $_POST['apply'])
		{
			$processed = $_POST['processed'];
			$to_remove = unserialize(urldecode($processed));
			foreach($to_remove as $uid)
			{
				//echo "deleting acl-records for $uid=".$GLOBALS['egw']->accounts->id2name($uid)." and $acl_app<br>\n";
				$this->acl->delete($acl_app,$uid);
			}

			/* Group records */
			$totalacl = array();
			$group_variable = $_POST['g_'.$GLOBALS['egw_info']['flags']['currentapp']];

			if (is_array($group_variable))
			{
				foreach($group_variable as $rowinfo => $perm)
				{
					list($group_id,$rights) = explode('_',$rowinfo);
					$totalacl[$group_id] += $rights;
				}
				foreach($totalacl as $group_id => $rights)
				{
					if($no_privat_grants)
					{
						/* Don't allow group-grants or admin to grant private */
						$rights &= ~EGW_ACL_PRIVATE;
					}
					//echo "adding acl-rights $rights for $group_id=".$GLOBALS['egw']->accounts->id2name($group_id)." and $acl_app<br>\n";
					$this->acl->add($GLOBALS['egw_info']['flags']['currentapp'],$group_id,$rights);
				}
			}

			/* User records */
			$totalacl = array();
			$user_variable = $_POST['u_'.$GLOBALS['egw_info']['flags']['currentapp']];

			if (is_array($user_variable))
			{
				foreach($user_variable as $rowinfo => $perm)
				{
					list($user_id,$rights) = explode('_',$rowinfo);
					$totalacl[$user_id] += $rights;
				}
				foreach($totalacl as $user_id => $rights)
				{
					if($no_privat_grants)
					{
						/* Don't allow group-grants or admin to grant private */
						$rights &= ~ EGW_ACL_PRIVATE;
					}
					//echo "adding acl-rights $rights for $user_id=".$GLOBALS['egw']->accounts->id2name($user_id)." and $acl_app<br>\n";
					$this->acl->add($GLOBALS['egw_info']['flags']['currentapp'],$user_id,$rights);
				}
			}
			$this->acl->save_repository();
		}
		if ($_POST['save'] || $_POST['cancel'])
		{
			egw::redirect_link($referer);
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('%1 - Preferences',$GLOBALS['egw_info']['apps'][$acl_app]['title']).' - '.lang('acl').': '.$owner_name;
		common::egw_header();
		echo parse_navbar();

		$this->template = new Template(common::get_tpl_dir($acl_app));
		$templates = Array (
			'preferences' => '../../../preferences/templates/default/acl.tpl',
			'row_colspan' => 'preference_colspan.tpl',
			'acl_row'     => 'preference_acl_row.tpl'
		);

		$this->template->set_file($templates);
		$this->template->set_block('preferences','list','list'); // refers to list area in acl.tpl (which is named as preferences)
		$this->template->set_block('list','letter_search','letter_search_cells'); // refers to the area letter_search (nested within area list)
		if ($submit)
		{
			$this->template->set_var('errors',lang('ACL grants have been updated'));
		}

		$common_hidden_vars = array(
			'start'		=> $start,
			'query'		=> $query,
			'owner'		=> $owner,
			'acl_app'	=> $acl_app,
			'referer'   => $referer,
			'search_type' => $search_type, // KL 20061204 added to have a search type available
		);
		$var = Array(
			'errors'      => '',
			'title'       => '<br>',
			'action_url'  => egw::link('/index.php','menuaction=preferences.uiaclprefs.index&acl_app=' . $acl_app),
			'lang_save'   => lang('Save'),
			'lang_apply'  => lang('Apply'),
			'lang_cancel' => lang('Cancel'),
			'common_hidden_vars_form' => html::input_hidden($common_hidden_vars)
		);
		$this->template->set_var($var);

		$vars = $this->template->get_undefined('row_colspan');
		foreach($vars as $var)
		{
			if(strpos($var,'lang_') !== false)
			{
				$value = str_replace('lang_','',$var);
				$value = str_replace('_',' ',$value);

				$this->template->set_var($var,lang($value));
			}
		}

		$accounts = $GLOBALS['egw']->accounts->search(array(
			'type'	=> 'both',
			'start'	=> $start,
			'query'	=> $query,
			'query_type' => $search_type, //KL 20061204 added to have query_type available
			'order' => 'account_type,account_fullname',
			'sort'	=> 'ASC',
		));
		$totalentries = $GLOBALS['egw']->accounts->total;
		$shownentries = count($accounts);

		if ($not_enum_group_acls === true)
		{
			$memberships = array();
		}
		else
		{
			$memberships = $GLOBALS['egw']->accounts->memberships($owner,true);
			if (is_array($not_enum_group_acls)) $memberships = array_diff($memberships,$not_enum_group_acls);
		}
		$header_type = '';
		$processed = Array();
		foreach((array)$accounts as $uid => $data)
		{
			if ($data['account_type'] == 'u' && $data['account_id'] == $owner)
			{
				continue;	/* no need to grant to self if user */
			}
			if ($data['account_type'] != $header_type)
			{
				$this->template->set_var('string',$data['account_type'] == 'g' ? lang('Groups') : lang('Users'));
				$this->template->parse('row','row_colspan',True);
				$header_type = $data['account_type'];
			}
			$tr_class = $GLOBALS['egw']->nextmatchs->alternate_row_color($tr_color,true);

			if ($data['account_type'] == 'g')
			{
				$this->display_row($tr_class,'g_',$data['account_id'],$data['account_lid'],$no_privat_grants,$memberships);
			}
			else
			{
				$this->display_row($tr_class,'u_',$data['account_id'],common::display_fullname($data['account_lid'],$data['account_firstname'],$data['account_lastname']),$no_privat_grants,$memberships);
			}
			$processed[] = $data['account_id'];
		}

		$extra_parms = array(
			'menuaction'	=> 'preferences.uiaclprefs.index',
			'acl_app'		=> $acl_app,
			'owner'			=> $owner,
			'referer'       => $referer,
			'search_type' => is_array($query_types) ? $search_type : '',
			'search_value' => isset($query) && $query ? html::htmlspecialchars($query) : '',
			'query' => isset($query) && $query ? html::htmlspecialchars($query) : '',
		);
		$var = Array(
			'nml'          => $GLOBALS['egw']->nextmatchs->left('/index.php',$start,$totalentries,$extra_parms),
			'nmr'          => $GLOBALS['egw']->nextmatchs->right('/index.php',$start,$totalentries,$extra_parms),
			'lang_groups' => lang('showing %1 - %2 of %3',$start+1,$start+$shownentries,$totalentries),
			'search_type' => is_array($query_types) ? html::select('search_type',$search_type,$query_types) : '',
			'search_value' => isset($query) && $query ? html::htmlspecialchars($query) : '',
			'search'       => lang('search'),
			'processed'    => urlencode(serialize($processed))
		);

		$letters = lang('alphabet');
		$letters = explode(',',substr($letters,-1) != '*' ? $letters : 'a,b,c,d,e,f,g,h,i,j,k,l,m,n,o,p,q,r,s,t,u,v,w,x,y,z');
		$extra_parms['search_type'] = 'start';
		foreach($letters as $letter)
		{
			$extra_parms['query'] = $letter;
			$this->template->set_var(array(
				'letter' => $letter,
				'link'   => egw::link('/index.php',$extra_parms),
				'class'  => $query == $letter && $search_type == 'start' ? 'letter_box_active' : 'letter_box',
			));
			$this->template->fp('letter_search_cells','letter_search',True);
		}
		unset($extra_parms['query']);
		unset($extra_parms['search_value']);
		unset($extra_parms['search_type']);
		$this->template->set_var(array(
			'letter' => lang('all'),
			'link'   => egw::link('/index.php',$extra_parms),
			'class'  => $search_type != 'start' || !in_array($query,$letters) ? 'letter_box_active' : 'letter_box',
		));
		$this->template->fp('letter_search_cells','letter_search',True);

		$this->template->set_var($var);

		$this->template->pfp('out','preferences');
	}

	/**
	 * Check if a single acl is checked and evtl. disabled
	 *
	 * @param string $label
	 * @param int $id account_id
	 * @param string $acl
	 * @param int $rights
	 * @param int $right
	 * @param boolean $disabled disable cell
	 */
	function check_acl($label,$id,$acl,$rights,$right,$disabled=False)
	{
		//echo "<p>check_acl($label,$id,$acl,$rights,$right,$disabled)</p>\n";
		$this->template->set_var($acl,$label.$GLOBALS['egw_info']['flags']['currentapp'].'['.$id.'_'.$right.']');
		$rights_set = ($rights & $right) ? ' checked="1"' : '';
		if ($disabled)
		{
			// This is so you can't select it in the GUI
			$rights_set .= ' disabled="1"';
		}
		$this->template->set_var($acl.'_selected',$rights_set);
	}

	/**
	 * Display an acl row for a given account
	 *
	 * @param string $tr_class
	 * @param string $label
	 * @param int $id account_id
	 * @param string $name
	 * @param boolean $no_privat_grants
	 * @param array $memberships
	 */
	function display_row($tr_class,$label,$id,$name,$no_privat_grants,$memberships)
	{
		$this->template->set_var('row_class',$tr_class);
		$this->template->set_var('user',$name);
		$rights = $this->acl->get_specific_rights($id, $GLOBALS['egw_info']['flags']['currentapp'], $memberships);
		if ($rights === true) $rights = 0;	// get_specific_rights returns true, if there no acl data at all
		//error_log(__METHOD__."(, $label, $id, $name, $no_privat_grants, ".array2string($memberships).") rights=".array2string($rights));

		foreach(array(
			EGW_ACL_READ		=> 'read',
			EGW_ACL_ADD			=> 'add',
			EGW_ACL_EDIT		=> 'edit',
			EGW_ACL_DELETE		=> 'delete',
			EGW_ACL_PRIVATE		=> 'private',
			EGW_ACL_CUSTOM_1	=> 'custom_1',
			EGW_ACL_CUSTOM_2	=> 'custom_2',
			EGW_ACL_CUSTOM_3	=> 'custom_3',
		) as $right => $name)
		{
			$is_group_set = False;
			if ($memberships)
			{
				$grantors = $this->acl->get_ids_for_location($id,$right,$GLOBALS['egw_info']['flags']['currentapp']);
				if (is_array($grantors))
				{
					foreach($grantors as $grantor)
					{
						//echo $GLOBALS['egw']->accounts->id2name($id)."=$id: $name-grant from ".$GLOBALS['egw']->accounts->id2name($grantor)."=$grantor<br>\n";
						// check if the grant comes from a group, the owner is a member off, in that case he is NOT allowed to remove it
						if(in_array($grantor,$memberships))
						{
							//echo "==> member of ==> set by group<br>";
							$is_group_set = True;
						}
					}
				}
			}
			$this->check_acl($label,$id,$name,$rights,$right,$is_group_set || $no_privat_grants && $right == EGW_ACL_PRIVATE);
		}
		$this->template->parse('row','acl_row',True);
	}
}
