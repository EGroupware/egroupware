<?php
/**
 * EGroupware - Addressbook - user interface
 *
 * @link www.egroupware.org
 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2005-14 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2005/6 by Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * General user interface object of the adressbook
 */
class addressbook_ui extends addressbook_bo
{
	public $public_functions = array(
		'search'	=> True,
		'edit'		=> True,
		'view'		=> True,
		'index'     => True,
		'photo'		=> True,
		'emailpopup'=> True,
		'migrate2ldap' => True,
		'admin_set_fileas' => True,
		'admin_set_all_cleanup' => True,
		'cat_add' => True,
	);
	protected $org_views;

	/**
	 * Addressbook configuration (stored as phpgwapi = general server config)
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * Fields to copy, default if nothing specified in config
	 *
	 * @var array
	 */
	static public $copy_fields = array(
		'org_name',
		'org_unit',
		'adr_one_street',
		'adr_one_street2',
		'adr_one_locality',
		'adr_one_region',
		'adr_one_postalcode',
		'adr_one_countryname',
		'adr_one_countrycode',
		'email',
		'url',
		'tel_work',
		'cat_id'
	);

	/**
	 * Instance of eTemplate class
	 *
	 * @var etemplate_new
	 */
	protected $tmpl;

	/**
	 * Constructor
	 *
	 * @param string $contact_app
	 */
	function __construct($contact_app='addressbook')
	{
		parent::__construct($contact_app);

		$this->tmpl = new etemplate_new();

		$this->org_views = array(
			'org_name'                  => lang('Organisations'),
			'org_name,adr_one_locality' => lang('Organisations by location'),
			'org_name,org_unit'         => lang('Organisations by departments'),
		);

		// make sure the hook for export_limit is registered
		if (!$GLOBALS['egw']->hooks->hook_exists('export_limit','addressbook')) $GLOBALS['egw']->hooks->register_single_app_hook('addressbook','export_limit');

		$this->config =& $GLOBALS['egw_info']['server'];

		// check if a contact specific export limit is set, if yes use it also for etemplate's csv export
		$this->config['export_limit'] = $this->config['contact_export_limit'] = bo_merge::getExportLimit($app='addressbook');

		if ($this->config['copy_fields'] && ($fields = is_array($this->config['copy_fields']) ?
			$this->config['copy_fields'] : unserialize($this->config['copy_fields'])))
		{
			// Set country code if country name is selected
			$supported_fields = $this->get_fields('supported',null,0);
			if(in_array('adr_one_countrycode', $supported_fields) && in_array('adr_one_countryname',$fields))
			{
				$fields[] = 'adr_one_countrycode';
			}
			if(in_array('adr_two_countrycode', $supported_fields) && in_array('adr_two_countryname',$fields))
			{
				$fields[] = 'adr_two_countrycode';
			}

			self::$copy_fields = $fields;
		}
	}

	/**
	 * List contacts of an addressbook
	 *
	 * @param array $content=null submitted content
	 * @param string $msg=null	message to show
	 * @param boolean $do_email=false do an email-selection popup or the regular index-page
	 */
	function index($content=null,$msg=null,$do_email=false)
	{
		//echo "<p>uicontacts::index(".print_r($content,true).",'$msg')</p>\n";
		if (($re_submit = is_array($content)))
		{
			$do_email = $content['do_email'];

			if (isset($content['nm']['rows']['delete']))	// handle a single delete like delete with the checkboxes
			{
				list($id) = @each($content['nm']['rows']['delete']);
				$content['nm']['action'] = 'delete';
				$content['nm']['selected'] = array($id);
			}
			if (isset($content['nm']['rows']['document']))	// handle insert in default document button like an action
			{
				list($id) = @each($content['nm']['rows']['document']);
				$content['nm']['action'] = 'document';
				$content['nm']['selected'] = array($id);
			}
			if ($content['nm']['action'] !== '')
			{
				if (!count($content['nm']['selected']) && !$content['nm']['select_all'] && $content['nm']['action'] != 'delete_list')
				{
					$msg = lang('You need to select some contacts first');
				}
				elseif ($content['nm']['action'] == 'view')	// org-view via context menu
				{
					$content['nm']['org_view'] = array_shift($content['nm']['selected']);
				}
				else
				{
					if ($this->action($content['nm']['action'],$content['nm']['selected'],$content['nm']['select_all'],
						$success,$failed,$action_msg,$content['do_email'] ? 'email' : 'index',$msg,$content['nm']['checkboxes']))
					{
						$msg .= lang('%1 contact(s) %2',$success,$action_msg);
						egw_framework::message($msg);
					}
					elseif(is_null($msg))
					{
						$msg .= lang('%1 contact(s) %2, %3 failed because of insufficent rights !!!',$success,$action_msg,$failed);
						egw_framework::message($msg,'error');
					}
					$msg = '';
				}
			}
			if ($content['nm']['rows']['infolog'])
			{
				list($org) = each($content['nm']['rows']['infolog']);
				return $this->infolog_org_view($org);
			}
			if ($content['nm']['rows']['view'])	// show all contacts of an organisation
			{
				list($org_view) = each($content['nm']['rows']['view']);
			}
			else
			{
				$org_view = $content['nm']['org_view'];
			}
			$typeselection = $content['nm']['col_filter']['tid'];
		}
		elseif($_GET['add_list'])
		{
			$list = $this->add_list($_GET['add_list'],$_GET['owner']?$_GET['owner']:$this->user);
			if ($list === true)
			{
				$msg = lang('List already exists!');
			}
			elseif ($list)
			{
				$msg = lang('List created');
			}
			else
			{
				$msg = lang('List creation failed, no rights!');
			}
		}
		$preserv = array(
			'do_email' => $do_email,
		);
		$to = $content['nm']['to'];
		$content = array();
		if($msg || $_GET['msg'])
		{
			egw_framework::message($msg ? $msg : $_GET['msg']);
		}

		$content['nm'] = egw_session::appsession($do_email ? 'email' : 'index','addressbook');
		if (!is_array($content['nm']))
		{
			$content['nm'] = array(
				'get_rows'       =>	'addressbook.addressbook_ui.get_rows',	// I  method/callback to request the data for the rows eg. 'notes.bo.get_rows'
				'bottom_too'     => false,		// I  show the nextmatch-line (arrows, filters, search, ...) again after the rows
				'never_hide'     => True,		// I  never hide the nextmatch-line if less then maxmatch entrie
				'start'          =>	0,			// IO position in list
				'cat_id'         =>	'',			// IO category, if not 'no_cat' => True
				'search'         =>	'',			// IO search pattern
				'order'          =>	'n_family',	// IO name of the column to sort after (optional for the sortheaders)
				'sort'           =>	'ASC',		// IO direction of the sort: 'ASC' or 'DESC'
				'col_filter'     =>	array(),	// IO array of column-name value pairs (optional for the filterheaders)
				'filter_label'   =>	lang('Addressbook'),	// I  label for filter    (optional)
				'filter'         =>	'',	// =All	// IO filter, if not 'no_filter' => True
				'filter_no_lang' => True,		// I  set no_lang for filter (=dont translate the options)
				'no_filter2'     => True,		// I  disable the 2. filter (params are the same as for filter)
				'filter2_label'  =>	lang('Distribution lists'),			// IO filter2, if not 'no_filter2' => True
				'filter2'        =>	'',			// IO filter2, if not 'no_filter2' => True
				'filter2_no_lang'=> True,		// I  set no_lang for filter2 (=dont translate the options)
				'lettersearch'   => true,
				'do_email'       => $do_email ? 1 : 0,
				'default_cols'   => '!cat_id,contact_created_contact_modified,distribution_list,contact_id,owner,room',
				'filter2_onchange' => "app.addressbook.filter2_onchange();",
				'manual'         => $do_email ? ' ' : false,	// space for the manual icon
				//'actions'        => $this->get_actions(),		// set on each request, as it depends on some filters
				'row_id'         => 'id',
				'row_modified'   => 'modified',
				'favorites'      => true,
				'placeholder_actions' => array('add')
			);

			if ($do_email)
			{
				$content['nm']['filter2_onchange'] = 'app.addressbook.filter2_onchange_email();';
			}
			// use the state of the last session stored in the user prefs
			if (($state = @unserialize($this->prefs[$do_email ? 'email_state' : 'index_state'])))
			{
				$content['nm'] = array_merge($content['nm'],$state);
			}
		}
		$sel_options['cat_id'] = array('' => lang('all'), '0' => lang('None'));

		// Delete list action depends on permissions
		if($this->get_lists(EGW_ACL_EDIT))
		{
			$content['nm']['placeholder_actions'][] = 'delete_list';
		}

		// Search parameter passed in
		if ($_GET['search']) {
			$content['nm']['search'] = $_GET['search'];
		}
		if (isset($typeselection)) $content['nm']['col_filter']['tid'] = $typeselection;
		// save the tid for use in creating new addressbook entrys via UI. Current tid is to be used as type of new entrys
		//error_log(__METHOD__.__LINE__.' '.$content['nm']['col_filter']['tid']);
		egw_cache::setSession('addressbook','active_tid',$content['nm']['col_filter']['tid']);
		if ($this->lists_available())
		{
			$sel_options['filter2'] = $this->get_lists(EGW_ACL_READ,array('' => lang('none')));
			$sel_options['filter2']['add'] = lang('Add a new list').'...';	// put it at the end
		}
		if ($do_email)
		{
			if (!$re_submit)
			{
				$content['nm']['to'] = 'to'; // use 'bcc' if you want bcc as preselected standard mailaddress scope
				$content['nm']['email_type'] = $this->prefs['distributionListPreferredMail'] ? $this->prefs['distributionListPreferredMail'] : 'email';
				$content['nm']['search'] = '@';
			}
			else
			{
				$content['nm']['to'] = $to;
				$content['nm']['email_type'] = $this->prefs['distributionListPreferredMail'] ? $this->prefs['distributionListPreferredMail'] : 'email';
			}
			$content['nm']['header_left'] = 'addressbook.email.left';
		}
		// Organisation stuff is not (yet) availible with ldap
		elseif($GLOBALS['egw_info']['server']['contact_repository'] != 'ldap')
		{
			$content['nm']['header_left'] = 'addressbook.index.left';
		}
		$sel_options['filter'] = $sel_options['owner'] = $this->get_addressbooks(EGW_ACL_READ,lang('All'));
		$sel_options['to'] = array(
			'to'  => 'To',
			'cc'  => 'Cc',
			'bcc' => 'Bcc',
		);

		// if there is any export limit set, pass it on to the nextmatch, to be evaluated by the export
		if (isset($this->config['contact_export_limit']) && (int)$this->config['contact_export_limit']) $content['nm']['export_limit']=$this->config['contact_export_limit'];

		// dont show tid-selection if we have only one content_type
		// be a bit more sophisticated about it
		$content['nm']['header_right'] = 'addressbook.index.right_add';
		$availabletypes = array_keys($this->content_types);
		if ($content['nm']['col_filter']['tid'] && !in_array($content['nm']['col_filter']['tid'],$availabletypes))
		{
			//_debug_array(array('Typefilter:'=> $content['nm']['col_filter']['tid'],'Available Types:'=>$availabletypes,'action:'=>'remove invalid filter'));
			unset($content['nm']['col_filter']['tid']);
		}
		if (!isset($content['nm']['col_filter']['tid'])) $content['nm']['col_filter']['tid'] = $availabletypes[0];
		if (count($this->content_types) > 1)
		{
			$content['nm']['header_right'] = 'addressbook.index.right_addplus';
			foreach($this->content_types as $tid => $data)
			{
				$sel_options['tid'][$tid] = $data['name'];
			}
		}
		// get the availible org-views plus the label of the contacts view of one org
		$sel_options['org_view'] = $this->org_views;
		if (isset($org_view)) $content['nm']['org_view'] = $org_view;

		$content['nm']['actions'] = $this->get_actions($content['nm']['col_filter']['tid'], $content['nm']['org_view']);

		if (!isset($sel_options['org_view'][(string) $content['nm']['org_view']]))
		{
			$sel_options['org_view'] += $this->_get_org_name((string)$content['nm']['org_view']);
		}
		// unset the filters regarding organisations, when there is no organisation selected
		if (empty($sel_options['org_view'][(string) $content['nm']['org_view']]) || stripos($org_view,":") === false )
		{
			unset($content['nm']['col_filter']['org_name']);
			unset($content['nm']['col_filter']['org_unit']);
			unset($content['nm']['col_filter']['adr_one_locality']);
		}
		$content['nm']['org_view_label'] = $sel_options['org_view'][(string) $content['nm']['org_view']];

		$this->tmpl->read($do_email ? 'addressbook.email' : 'addressbook.index');
		return $this->tmpl->exec($do_email ? 'addressbook.addressbook_ui.emailpopup' : 'addressbook.addressbook_ui.index',
			$content,$sel_options,$readonlys,$preserv,$do_email ? 2 : 0);
	}

	/**
	 * Get actions / context menu items
	 *
	 * @param string $tid_filter=null
	 * @param string $org_view=null
	 * @return array see nextmatch_widget::get_actions()
	 */
	private function get_actions($tid_filter=null, $org_view=null)
	{
		// we have no org view (view of one org has context menu like regular "add contacts" view, as it shows contacts
		if (!isset($this->org_views[(string) $org_view]))
		{
			$actions = array(
				'view' => array(
					'caption' => 'CRM-View',
					'default' => $GLOBALS['egw_info']['user']['preferences']['addressbook']['crm_list'] != '~edit~',
					'allowOnMultiple' => false,
					'group' => $group=1,
					'onExecute' => 'javaScript:app.addressbook.view',
					// Children added below
					'children' => array()
				),
				'open' => array(
					'caption' => 'Open',
					'default' => $GLOBALS['egw_info']['user']['preferences']['addressbook']['crm_list'] == '~edit~',
					'allowOnMultiple' => false,
					'url' => 'menuaction=addressbook.addressbook_ui.edit&contact_id=$id',
					'popup' => egw_link::get_registry('addressbook', 'add_popup'),
					'group' => $group,
				),
				'add' => array(
					'caption' => 'Add',
					'group' => $group,
					'children' => array(
						'new' => array(
							'caption' => 'New',
							'url' => 'menuaction=addressbook.addressbook_ui.edit',
							'popup' => egw_link::get_registry('addressbook', 'add_popup'),
							'icon' => 'new',
						),
						'copy' => array(
							'caption' => 'Copy',
							'url' => 'menuaction=addressbook.addressbook_ui.edit&makecp=1&contact_id=$id',
							'popup' => egw_link::get_registry('addressbook', 'add_popup'),
							'allowOnMultiple' => false,
							'icon' => 'copy',
						),
					),
				),
			);
			// CRM view options
			$crm_count = 0;
			$crm_apps = array('infolog','tracker');
			foreach($crm_apps as $app)
			{
				if ($GLOBALS['egw_info']['user']['apps'][$app]) $crm_count++;
			}
			if($crm_count > 1)
			{
				foreach($crm_apps as $app)
				{
					$actions['view']['children']["view-$app"] = array(
						'caption' => $app,
						'icon' => "$app/navbar"
					);
				}
			}
		}
		else	// org view
		{
			$actions = array(
				'view' => array(
					'caption' => 'View',
					'default' => true,
					'allowOnMultiple' => false,
					'group' => $group=1,
				),
				'add' => array(
					'caption' => 'Add',
					'group' => $group,
					'allowOnMultiple' => false,
					'url' => 'menuaction=addressbook.addressbook_ui.edit&org=$id',
					'popup' => egw_link::get_registry('addressbook', 'add_popup'),
				),
			);
		}

		++$group;	// other AB related stuff group: lists, AB's, categories
		// categories submenu
		$actions['cat'] = array(
			'caption' => 'Categories',
			'group' => $group,
			'children' => array(
				'cat_add' => nextmatch_widget::category_action(
					'addressbook',$group,'Add category', 'cat_add_',
					true, 0,nextmatch_widget::DEFAULT_MAX_MENU_LENGTH,false
				)+array(
					'icon' => 'foldertree_nolines_plus',
					'disableClass' => 'rowNoEdit',
				),
				'cat_del' => nextmatch_widget::category_action(
					'addressbook',$group,'Delete category', 'cat_del_',
					true, 0,nextmatch_widget::DEFAULT_MAX_MENU_LENGTH,false
				)+array(
					'icon' => 'foldertree_nolines_minus',
					'disableClass' => 'rowNoEdit',
				),
			),
		);
		if (!$GLOBALS['egw_info']['user']['apps']['preferences']) unset($actions['cats']['children']['cat_edit']);
		// Submenu for all distributionlist stuff
		$actions['lists'] = array(
			'caption' => 'Distribution lists',
			'children' => array(
				'list_add' => array(
					'caption' => 'Add a new list',
					'icon' => 'new',
					'onExecute' => 'javaScript:app.addressbook.add_new_list',
				),
			),
			'group' => $group,
		);
		if (($add_lists = $this->get_lists(EGW_ACL_EDIT)))	// do we have distribution lists?, and are we allowed to edit them
		{
			$actions['lists']['children'] += array(
				'to_list' => array(
					'caption' => 'Add to distribution list',
					'children' => $add_lists,
					'prefix' => 'to_list_',
					'icon' => 'foldertree_nolines_plus',
					'enabled' => ($add_lists?true:false), // if there are editable lists, allow to add a contact to one of them,
					//'disableClass' => 'rowNoEdit',	  // wether you are allowed to edit the contact or not, as you alter a list, not the contact
				),
				'remove_from_list' => array(
					'caption' => 'Remove from distribution list',
					'confirm' => 'Remove selected contacts from distribution list',
					'icon' => 'foldertree_nolines_minus',
					'enabled' => 'javaScript:app.addressbook.nm_compare_field',
					'fieldId' => 'exec[nm][filter2]',
					'fieldValue' => '!',	// enable if list != ''
				),
				'delete_list' => array(
					'caption' => 'Delete selected distribution list!',
					'confirm' => 'Delete selected distribution list!',
					'icon' => 'delete',
					'enabled' => 'javaScript:app.addressbook.nm_compare_field',
					'fieldId' => 'exec[nm][filter2]',
					'fieldValue' => '!',	// enable if list != ''
				),
			);
			if(is_subclass_of('etemplate', 'etemplate_new'))
			{
				$actions['lists']['children']['remove_from_list']['fieldId'] = 'filter2';
				$actions['lists']['children']['delete_list']['fieldId'] = 'filter2';
			}
		}
		// move to AB
		if (($move2addressbooks = $this->get_addressbooks(EGW_ACL_ADD)))	// do we have addressbooks, we should
		{
			unset($move2addressbooks[0]);	// do not offer action to move contact to an account, as we dont support that currrently
			foreach($move2addressbooks as $owner => $label)
			{
				$this->type_icon((int)$owner, substr($owner,-1) == 'p', 'n', $icon, $type_label);
				$move2addressbooks[$owner] = array(
					'icon' => $icon,
					'caption' => $label,
				);
			}
			// copy checkbox
			$move2addressbooks= array(
				'copy' =>array(
					'id' => 'move_to_copy',
					'caption' => 'Copy instead of move',
					'checkbox' => true,
				)) + $move2addressbooks;
			$actions['move_to'] = array(
				'caption' => 'Move to addressbook',
				'children' => $move2addressbooks,
				'prefix' => 'move_to_',
				'group' => $group,
				'disableClass' => 'rowNoDelete',
			);
		}
		$actions['merge'] = array(
			'caption' => 'Merge contacts',
			'confirm' => 'Merge into first or account, deletes all other!',
			'hint' => 'Merge into first or account, deletes all other!',
			'allowOnMultiple' => 'only',
			'group' => $group,
		);

		++$group;	// integration with other apps: infolog, calendar, filemanager
		if ($GLOBALS['egw_info']['user']['apps']['infolog'])
		{
			$actions['infolog_app'] = array(
				'caption' => 'InfoLog',
				'icon' => 'infolog/navbar',
				'group' => $group,
				'children' => array(
					'infolog' => array(
						'caption' => lang('View linked InfoLog entries'),
						'icon' => 'infolog/navbar',
						'onExecute' => 'javaScript:app.addressbook.view_infolog',
						'allowOnMultiple' => true,
					),
					'infolog_add' => array(
						'caption' => 'Add a new Infolog',
						'icon' => 'new',
						'url' => 'menuaction=infolog.infolog_ui.edit&type=task&action=addressbook&action_id=$id',
						'popup' => egw_link::get_registry('infolog', 'add_popup'),
						'onExecute' => 'javaScript:app.addressbook.add_task',	// call server for org-view only
					),
				)
			);
		}
		if ($GLOBALS['egw_info']['user']['apps']['calendar'])
		{
			$actions['calendar'] = array(
				'icon' => 'calendar/navbar',
				'caption' => 'Calendar',
				'group' => $group,
				'children' => array(
					'calendar_view' => array(
						'caption' => 'Show',
						'icon' => 'view',
						'url' => 'menuaction=calendar.calendar_uilist.listview&filter=all&owner=0,c$id&ajax=true',
						'targetapp' => 'calendar',	// open in calendar tab
					),
					'calendar_add' => array(
						'caption' => 'Add appointment',
						'icon' => 'new',
						'url' => 'menuaction=calendar.calendar_uiforms.edit&participants=c$id',
						'popup' => egw_link::get_registry('calendar', 'add_popup'),
						'onExecute' => 'javaScript:app.addressbook.add_cal',	// call server for org-view only
					),
				),
			);
		}
		//Send to email
		$actions['email'] = array(
				'caption' => 'Email',
				'icon'	=> 'mail/navbar',
				'group' => $group,
				'children' => array(
						'add_to_to' => array(
							'caption' => lang('Add to To'),
							'no_lang' => true,
							'onExecute' => 'javaScript:app.addressbook.addEmail',

						),
						'add_to_cc' => array(
							'caption' => lang('Add to Cc'),
							'no_lang' => true,
							'onExecute' => 'javaScript:app.addressbook.addEmail',

						),
						'add_to_bcc' => array(
							'caption' => lang('Add to BCc'),
							'no_lang' => true,
							'onExecute' => 'javaScript:app.addressbook.addEmail',

						),
						'email_business' => array(
							'caption' => lang('Business email'),
							'no_lang' => true,
							'checkbox' => true,
							'group'	=> $group,
							'onExecute' => 'javaScript:app.addressbook.mailCheckbox',
							'checked' => $this->prefs['preferredMail']['business'],
						),
						'email_home' => array(
							'caption' => lang('Home email'),
							'no_lang' => true,
							'checkbox' => true,
							'group'	=> $group,
							'onExecute' => 'javaScript:app.addressbook.mailCheckbox',
							'checked' => $this->prefs['preferredMail']['private'],
						),
				),

			);
		if (!$this->prefs['preferredMail'])
			$actions['email']['children']['email_business']['checked'] = true;

		if ($GLOBALS['egw_info']['user']['apps']['filemanager'])
		{
			$actions['filemanager'] = array(
				'icon' => 'filemanager/navbar',
				'caption' => 'Filemanager',
				'url' => 'menuaction=filemanager.filemanager_ui.index&path=/apps/addressbook/$id&ajax=true',
				'allowOnMultiple' => false,
				'group' => $group,
				// disable for for org-views, as it needs contact-ids
				'enabled' => !isset($this->org_views[(string) $org_view]),
			);
		}
		// check if user is an admin or the export is not generally turned off (contact_export_limit is non-numerical, eg. no)
		$exception = bo_merge::is_export_limit_excepted();
		if ((isset($GLOBALS['egw_info']['user']['apps']['admin']) || $exception)  || !$this->config['contact_export_limit'] || (int)$this->config['contact_export_limit'])
		{
			$actions['export'] = array(
				'caption' => 'Export',
				'icon' => 'filesave',
				'group' => ++$group,
				'children' => array(
					'csv'    => array(
						'caption' => 'Export as CSV',
						'allowOnMultiple' => true,
						'url' => 'menuaction=importexport.importexport_export_ui.export_dialog&appname=addressbook&plugin=addressbook_export_contacts_csv&selection=$id&select_all=$select_all',
						'popup' => '850x440'
					),
					'vcard'  => array(
						'caption' => 'Export as VCard',
						'postSubmit' => true,	// download needs post submit (not Ajax) to work
						'icon' => egw_vfs::mime_icon('text/vcard'),
					),
				),
			);
		}

		$actions['documents'] = addressbook_merge::document_action(
			$this->prefs['document_dir'], $group, 'Insert in document', 'document_',
			$this->prefs['default_document'], $this->config['contact_export_limit']
		);
		if (isset($GLOBALS['egw_info']['apps']['stylite']) && ($GLOBALS['egw_info']['user']['apps']['felamimail']||$GLOBALS['egw_info']['user']['apps']['mail']))
		{
			$actions['mail'] = array(
				'caption' => lang('Mail VCard'),
				'icon' => 'filemanager/mail_post_to',
				'group' => $group,
				'onExecute' => 'javaScript:app.addressbook.adb_mail_vcard',
			);
		}
		++$group;
		if (!($tid_filter == 'D' && !$GLOBALS['egw_info']['user']['apps']['admin'] && $this->config['history'] != 'userpurge'))
		{
			$actions['delete'] = array(
				'caption' => 'Delete',
				'confirm' => 'Delete this contact',
				'confirm_multiple' => 'Delete these entries',
				'group' => $group,
				'disableClass' => 'rowNoDelete',
			);
		}
		if ($this->grants[0] & EGW_ACL_DELETE)
		{
			$actions['delete_account'] = array(
				'caption' => 'Delete',
				'icon' => 'delete',
				'group' => $group,
				'enableClass' => 'rowAccount',
				'hideOnDisabled' => true,
				'popup' => '400x200',
				'url' => 'menuaction=admin.admin_account.delete&contact_id=$id',
			);
			$actions['delete']['hideOnDisabled'] = true;
		}
		if($tid_filter == 'D')
		{
			$actions['undelete'] = array(
				'caption' => 'Un-delete',
				'icon' => 'revert',
				'group' => $group,
				'disableClass' => 'rowNoEdit',
			);
		}
		if (isset($actions['export']['children']['csv']) && !importexport_helper_functions::has_definitions('addressbook','export')) unset($actions['export']['children']['csv']);

		//echo "<p>".__METHOD__."($do_email, $tid_filter, $org_view)</p>\n"; _debug_array($actions);

		// Allow contacts to be dragged
		/*
		$actions['drag'] = array(
			'type' => 'drag',
			'dragType' => 'addressbook'
		);
		*/
		return $actions;
	}

	/**
	 * Get the name of an organization from an ID for the org_view filter
	 *
	 * @param string $org
	 * @return Array ID => name
	 */
	private function _get_org_name($org)
	{
		$org_name = array();
		if (strpos($org,'*AND*')!== false) $org = str_replace('*AND*','&',$org);
		foreach(explode('|||',$org) as $part)
		{
			list(,$name) = explode(':',$part,2);
			if ($name) $org_name[] = $name;
		}
		$org_name = implode(': ',$org_name);
		return $org_name ? array($org => $org_name) : array();
	}

	/**
	 * Email address-selection popup
	 *
	 * @param array $content=null submitted content
	 * @param string $msg=null	message to show
	 */
	function emailpopup($content=null,$msg=null)
	{
		if (strpos($GLOBALS['egw_info']['flags']['java_script'],'addEmail') === false)
		{
			$handler = 'opener.addEmail(to,email)';
			$GLOBALS['egw_info']['flags']['java_script'].= "
<script>
window.egw_LAB.wait(function() {
	window.focus();

	window.addEmail = function(email)
	{
		var to = 'to';
		splitter = email.indexOf(' <');
		namepart = email.substring(0,splitter);
		emailpart = email.substring(splitter);
		email = namepart.replace(/@/g,' ')+emailpart;

		if (document.getElementById('exec[nm][to][cc]').checked == true)
		{
			to = 'cc';
		}
		else
		{
			if (document.getElementById('exec[nm][to][bcc]').checked == true)
			{
				to = 'bcc';
			}
		}
		$handler;
	};
});
</script>
";
		}
		return $this->index($content,$msg,true);
	}

	/**
	 * Return the contacts in an organisation via AJAX
	 *
	 * @param string|string[] $org Organisation ID
	 * @param mixed $query Query filters (category, etc) to use, or null to use session
	 * @return array
	 */
	public function ajax_organisation_contacts($org, $query = null)
	{
		$org_contacts = array();
		$query = $query == null ? egw_session::appsession('index','addressbook') : $query;
		$query['num_rows'] = -1;	// all
		if(!is_array($query['col_filter'])) $query['col_filter'] = array();

		if(!is_array($org)) $org = array($org);
		foreach($org as $org_name)
		{
			error_log("Org: $org_name");
			$query['org_view'] = $org_name;
			$checked = array();
			$count = $this->get_rows($query,$checked,$readonlys,true);	// true = only return the id's
			error_log("Count: $count " . array2string($checked));
			if($checked[0])
			{
				$org_contacts = array_merge($org_contacts,$checked);
			}
		}
		egw_json_response::get()->data(array_unique($org_contacts));
	}

	/**
	 * Show the infologs of an whole organisation
	 *
	 * @param string $org
	 */
	function infolog_org_view($org)
	{
		$query = egw_session::appsession('index','addressbook');
		$query['num_rows'] = -1;	// all
		$query['org_view'] = $org;
		$query['searchletter'] = '';
		$this->get_rows($query,$checked,$readonlys,true);	// true = only return the id's

		if (count($checked) > 1)	// use a nicely formatted org-name as title in infolog
		{
			$parts = array();
			if (strpos($org,'*AND*')!== false) $org = str_replace('*AND*','&',$org);
			foreach(explode('|||',$org) as $part)
			{
				list(,$part) = explode(':',$part,2);
				if ($part) $parts[] = $part;
			}
			$org = implode(', ',$parts);
		}
		else
		{
			$org = '';	// use infolog default of link-title
		}
		egw::redirect_link('/index.php',array(
			'menuaction' => 'infolog.infolog_ui.index',
			'action' => 'addressbook',
			'action_id' => implode(',',$checked),
			'action_title' => $org,
		),'infolog');
	}

	function ajax_add_whole_list($list, $email_type = 'email')
	{
		$query = egw_session::appsession('email','addressbook');
		$query['filter2'] = (int)$list;
		$this->action($email_type,array(),true,$success,$failed,$action_msg,$query,$msg);

		$response = egw_json_response::get();

		if ($success) $response->addScript(egw_framework::set_onload(''));

		// close window only if no errors AND something added
		if ($failed || !$success)
		{
			if (!$msg) $msg = $failed ? lang('%1 contact(s) %2, %3 failed because of insufficent rights !!!',$success,$action_msg,$failed) :
				lang('%1 contact(s) %2',$success,$action_msg);

			$response->addScript("alert('".addslashes($msg)."')");
			// reset the filter
			$response->addScript("document.getElementById('exec[nm][filter2]').value='';");
		}
		else
		{
			if (!$msg) $msg = lang('%1 contact(s) %2',$success,$action_msg);
			$response->addScript("alert('".addslashes($msg)."')");
			$response->addScript('window.close();');
		}

	}

	/**
	 * apply an action to multiple contacts
	 *
	 * @param string/int $action 'delete', 'vcard', 'csv' or nummerical account_id to move contacts to that addessbook
	 * @param array $checked contact id's to use if !$use_all
	 * @param boolean $use_all if true use all contacts of the current selection (in the session)
	 * @param int &$success number of succeded actions
	 * @param int &$failed number of failed actions (not enought permissions)
	 * @param string &$action_msg translated verb for the actions, to be used in a message like %1 contacts 'deleted'
	 * @param string/array $session_name 'index' or 'email', or array with session-data depending if we are in the main list or the popup
	 * @return boolean true if all actions succeded, false otherwise
	 */
	function action($action,$checked,$use_all,&$success,&$failed,&$action_msg,$session_name,&$msg, $checkboxes = NULL)
	{
		//echo "<p>uicontacts::action('$action',".print_r($checked,true).','.(int)$use_all.",...)</p>\n";
		$success = $failed = 0;
		if ($use_all || in_array($action,array('remove_from_list','delete_list')))
		{
			// get the whole selection
			$query = is_array($session_name) ? $session_name : egw_session::appsession($session_name,'addressbook');

			if ($use_all)
			{
				@set_time_limit(0);			// switch off the execution time limit, as it's for big selections to small
				$query['num_rows'] = -1;	// all
				$this->get_rows($query,$checked,$readonlys,true);	// true = only return the id's
			}
		}
		// replace org_name:* id's with all id's of that org
		$org_contacts = array();
		foreach((array)$checked as $n => $id)
		{
			if (substr($id,0,9) == 'org_name:')
			{
				if (count($checked) == 1 && !count($org_contacts) && $action == 'infolog')
				{
					return $this->infolog_org_view($id);	// uses the org-name, instead of 'selected contacts'
				}
				unset($checked[$n]);
				$query = egw_session::appsession($session_name,'addressbook');
				$query['num_rows'] = -1;	// all
				$query['org_view'] = $id;
				unset($query['filter2']);
				$this->get_rows($query,$extra,$readonlys,true);	// true = only return the id's
				if ($extra[0]) $org_contacts = array_merge($org_contacts,$extra);
			}
		}
		if ($org_contacts) $checked = array_unique($checked ? array_merge($checked,$org_contacts) : $org_contacts);
		//_debug_array($checked); exit;

		if (substr($action,0,8) == 'move_to_')
		{
			$action = (int)substr($action,8).(substr($action,-1) == 'p' ? 'p' : '');
		}
		elseif (substr($action,0,8) == 'to_list_')
		{
			$to_list = (int)substr($action,8);
			$action = 'to_list';
		}
		elseif (substr($action,0,9) == 'document_')
		{
			$document = substr($action,9);
			$action = 'document';
		}
		elseif(substr($action,0,4) == 'cat_')	// cat_add_123 or cat_del_456
		{
			$cat_id = (int)substr($action, 8);
			$action = substr($action,0,7);
		}
		// Security: stop non-admins to export more then the configured number of contacts
		if (in_array($action,array('csv','vcard')) && $this->config['contact_export_limit'] && !bo_merge::is_export_limit_excepted() &&
			(!is_numeric($this->config['contact_export_limit']) || count($checked) > $this->config['contact_export_limit']))
		{
			$action_msg = lang('exported');
			$failed = count($checked);
			return false;
		}
		switch($action)
		{
			case 'vcard':
				$action_msg = lang('exported');
				$vcard = new addressbook_vcal('addressbook','text/vcard');
				$vcard->export($checked);
				// does not return!
				$Ok = false;
				break;

			case 'merge':
				$success = $this->merge($checked,$error_msg);
				$failed = count($checked) - (int)$success;
				$action_msg = lang('merged');
				$checked = array();	// to not start the single actions
				break;

			case 'delete_list':
				if (!$query['filter2'])
				{
					$msg = lang('You need to select a distribution list');
				}
				elseif($this->delete_list($query['filter2']) === false)
				{
					$msg = lang('Insufficent rights to delete this list!');
				}
				else
				{
					$msg = lang('Distribution list deleted');
					unset($query['filter2']);
					egw_session::appsession($session_name,'addressbook',$query);
				}
				return false;

			case 'document':
				if (!$document) $document = $this->prefs['default_document'];
				$document_merge = new addressbook_merge();
				$msg = $document_merge->download($document, $checked, '', $this->prefs['document_dir']);
				$failed = count($checked);
				return false;

			case 'infolog_add':
				list($width,$height) = explode('x',egw_link::get_registry('infolog', 'add_popup'));
				egw_framework::set_onload(
					"egw_openWindowCentered2('".egw::link('/index.php',array(
						'menuaction' => 'infolog.infolog_ui.edit',
						'type' => 'task',
						'action' => 'addressbook',
						'action_id' => implode(',',$checked),
					))."','_blank',$width,$height);");
				$msg = '';	// no message, as we send none in javascript too and users sees opening popup
				return false;

			case 'calendar_add':	// add appointment for org-views, other views are handled directly in javascript
				list($width,$height) = explode('x',egw_link::get_registry('calendar', 'add_popup'));
				egw_framework::set_onload(
					"egw_openWindowCentered2('".egw::link('/index.php',array(
						'menuaction' => 'calendar.calendar_uiforms.edit',
						'participants' => 'c'.implode(',c',$checked),
					))."','_blank',$width,$height);");
				$msg = '';	// no message, as we send none in javascript too and users sees opening popup
				return false;

			case 'calendar_view':	// show calendar for org-views, other views are handled directly in javascript
				list($width,$height) = explode('x',egw_link::get_registry('calendar', 'add_popup'));
				egw::redirect_link('/index.php',array(
					'menuaction' => 'calendar.calendar_uiviews.index',
					'owner' => 'c'.implode(',c',$checked),
				));
		}
		foreach($checked as $id)
		{
			switch($action)
			{
				case 'cat_add':
				case 'cat_del':
					if (($Ok = !!($contact = $this->read($id)) && $this->check_perms(EGW_ACL_EDIT,$contact)))
					{
						$action_msg = $action == 'cat_add' ? lang('categorie added') : lang('categorie delete');
						$cat_ids = $contact['cat_id'] ? explode(',', $contact['cat_id']) : array();   //existing categories
						if ($action == 'cat_add')
						{
							$cat_ids[] = $cat_id;
							$cat_ids = array_unique($cat_ids);
						}
						elseif ((($key = array_search($cat_id,$cat_ids))) !== false)
						{
							unset($cat_ids[$key]);
						}
						$cat_ids = $cat_ids ? implode(',',$cat_ids) : null;
						if ($cat_ids !== $contact['cat_id'])
						{
							$contact['cat_id'] = $cat_ids;
							$Ok = $this->save($contact);
						}
					}
					break;

				case 'delete':
					$action_msg = lang('deleted');
					if (($Ok = !!($contact = $this->read($id)) && $this->check_perms(EGW_ACL_DELETE,$contact)))
					{
						if ($contact['owner'] ||	// regular contact or
							// already deleted account (should no longer happen, but needed to allow for cleanup)
							$contact['tid'] == addressbook_so::DELETED_TYPE)
						{
							$Ok = $this->delete($id, $contact['tid'] != addressbook_so::DELETED_TYPE);
						}
						// delete single account --> redirect to admin
						elseif (count($checked) == 1 && $contact['account_id'])
						{
							egw::redirect_link('/index.php',array(
								'menuaction' => 'admin.admin_account.delete',
								'account_id' => $contact['account_id'],
							));
							// this does NOT return!
						}
						else	// no mass delete of accounts
						{
							$Ok = false;
						}
					}
					break;

				case 'undelete':
					$action_msg = lang('recovered');
					if ($contact = $this->read($id))
					{
						$contact['tid'] = 'n';
						$Ok = $this->save($contact);
					}
					break;

				case 'email':
				case 'email_home':
					$action == 'email' ? $action_fallback = 'email_home' : $action_fallback = 'email';
					$action_msg = lang('added');
					if($contact = $this->read($id))
					{
						if(strpos($contact[$action],'@') !== false)
						{
							$email = $contact[$action];
						}
						elseif(strpos($contact[$action_fallback],'@') !== false)
						{
							$email = $contact[$action_fallback];
						}
						else
						{
							$Ok = $email = false;
						}
						if($email)
						{
							$contact['n_fn'] = str_replace(array(',','@'),' ',$contact['n_fn']);
							egw_framework::set_onload("addEmail('".addslashes(
								$contact['n_fn'] ? $contact['n_fn'].' <'.trim($email).'>' : trim($email))."');");
							//error_log(__METHOD__.__LINE__."addEmail('".addslashes(
							//	$contact['n_fn'] ? $contact['n_fn'].' <'.trim($email).'>' : trim($email))."');");
							$Ok = true;
						}
					}
					break;

				case 'remove_from_list':
					$action_msg = lang('removed from distribution list');
					if (!$query['filter2'])
					{
						$msg = lang('You need to select a distribution list');
						return false;
					}
					else
					{
						$Ok = $this->remove_from_list($id,$query['filter2']) !== false;
					}
					break;

				case 'to_list':
					$action_msg = lang('added to distribution list');
					if (!$to_list)
					{
						$msg = lang('You need to select a distribution list');
						return false;
					}
					else
					{
						$Ok = $this->add2list($id,$to_list) !== false;
					}
					break;

				default:	// move to an other addressbook
					if (!(int)$action || !($this->grants[(string) (int) $action] & EGW_ACL_EDIT))	// might be ADD in the future
					{
						return false;
					}
					if (!$checkboxes['move_to_copy'])
					{
						$action_msg = lang('moved');
						if (($Ok = !!($contact = $this->read($id)) && $this->check_perms(EGW_ACL_DELETE,$contact)))
						{
							if (!$contact['owner'])		// no mass-change of accounts
							{
								$Ok = false;
							}
							elseif ($contact['owner'] != (int)$action || $contact['private'] != (int)(substr($action,-1) == 'p'))
							{
								$contact['owner'] = (int) $action;
								$contact['private'] = (int)(substr($action,-1) == 'p');
								$Ok = $this->save($contact);
							}
						}
					}
					else
					{
						$action_msg = lang('copied');
						if (($Ok = !!($contact = $this->read($id)) && $this->check_perms(EGW_ACL_DELETE,$contact)))
						{
							if (!$contact['owner'])		// no mass-change of accounts
							{
								$Ok = false;
							}
							elseif ($contact['owner'] != (int)$action || $contact['private'] != (int)(substr($action,-1) == 'p'))
							{
								unset($contact['id']);
								unset($contact['uid']);
								unset($contact['etag']);
								$contact['owner'] = (int) $action;
								$contact['private'] = (int)(substr($action,-1) == 'p');
								$Ok = $this->save($contact);
							}
						}
					}
					break;
			}
			if ($Ok)
			{
				++$success;
			}
			elseif ($action != 'email' && $action != 'email_home')
			{
				++$failed;
			}
		}
		return !$failed;
	}

	/**
	 * rows callback for index nextmatch
	 *
	 * @internal
	 * @param array &$query
	 * @param array &$rows returned rows/cups
	 * @param array &$readonlys eg. to disable buttons based on acl
	 * @param boolean $id_only=false if true only return (via $rows) an array of contact-ids, dont save state to session
	 * @return int total number of contacts matching the selection
	 */
	function get_rows(&$query,&$rows,&$readonlys,$id_only=false)
	{
		$do_email = $query['do_email'];
		$what = $query['sitemgr_display'] ? $query['sitemgr_display'] : ($do_email ? 'email' : 'index');

		if (!$id_only && !$query['csv_export'])	// do NOT store state for csv_export or querying id's (no regular view)
		{
			$store_query = $query;
			// Do not store these
			foreach(array('options-cat_id','actions') as $key)
			{
				unset($store_query[$key]);
			}
			$old_state = egw_session::appsession($what,'addressbook',$store_query);
		}
		else
		{
			$old_state = egw_session::appsession($what,'addressbook');
		}
		if (!isset($this->org_views[(string) $query['org_view']]))   // we dont have an org view, unset the according col_filters
		{
			if (isset($query['col_filter']['org_name'])) unset($query['col_filter']['org_name']);
			if (isset($query['col_filter']['adr_one_locality'])) unset($query['col_filter']['adr_one_locality']);
			if (isset($query['col_filter']['org_unit'])) unset($query['col_filter']['org_unit']);
		}

		if (isset($this->org_views[(string) $query['org_view']]))	// we have an org view, reset the advanced search
		{
			//_debug_array(array('Search'=>$query['search'],
			//	'AdvancedSearch'=>$query['advanced_search']));
			//if (is_array($query['search'])) unset($query['search']);
			//unset($query['advanced_search']);
			if(!$query['search'] && $old_state['advanced_search']) $query['advanced_search'] = $old_state['advanced_search'];
		}
		elseif(!$query['search'] && $old_state['advanced_search'])	// eg. paging in an advanced search
		{
			$query['advanced_search'] = $old_state['advanced_search'];
		}
		if ($do_email && etemplate::$loop)
		{	// remove previous addEmail() calls, otherwise they will be run again
			egw_framework::set_onload(preg_replace('/addEmail\([^)]+\);/','',egw_framework::set_onload()),true);
		}

		// Make sure old lettersearch filter doesn't stay - current letter filter will be added later
		foreach($query['col_filter'] as $key => $col_filter)
		{
			if(!is_numeric($key)) continue;
			if(preg_match('/'.$GLOBALS['egw']->db->capabilities['case_insensitive_like'].
				' '.$GLOBALS['egw']->db->quote('[a-z]%').'$/',$col_filter) == 1
			)
			{
				unset($query['col_filter'][$key]);
			}
		}

		//echo "<p>uicontacts::get_rows(".print_r($query,true).")</p>\n";
		if (!$id_only)
		{
			// check if accounts are stored in ldap, which does NOT yet support the org-views
			if ($this->so_accounts && $query['filter'] === '0' && $query['org_view'])
			{
				if ($old_state['filter'] === '0')	// user changed to org_view
				{
					$query['filter'] = '';			// --> change filter to all contacts
				}
				else								// user changed to accounts
				{
					$query['org_view'] = '';		// --> change to regular contacts view
				}
			}
			if ($query['org_view'] && isset($this->org_views[$old_state['org_view']]) && !isset($this->org_views[$query['org_view']]))
			{
				$query['searchletter'] = '';		// reset lettersearch if viewing the contacts of one organisation
			}
			// save the state of the index in the user prefs
			$state = serialize(array(
				'filter'     => $query['filter'],
				'cat_id'     => $query['cat_id'],
				'order'      => $query['order'],
				'sort'       => $query['sort'],
				'col_filter' => array('tid' => $query['col_filter']['tid']),
				'org_view'   => $query['org_view'],
			));
			if ($state != $this->prefs[$what.'_state'] && !$query['csv_export'])
			{
				$GLOBALS['egw']->preferences->add('addressbook',$what.'_state',$state);
				// save prefs, but do NOT invalid the cache (unnecessary)
				$GLOBALS['egw']->preferences->save_repository(false,'user',false);
			}
		}
		unset($old_state);

		if ((string)$query['cat_id'] != '')
		{
			$query['col_filter']['cat_id'] = $query['cat_id'] ? $query['cat_id'] : null;
		}
		else
		{
			unset($query['col_filter']['cat_id']);
		}
		if ($query['filter'] !== '')	// not all addressbooks
		{
			$query['col_filter']['owner'] = (string) (int) $query['filter'];

			if ($this->private_addressbook)
			{
				$query['col_filter']['private'] = substr($query['filter'],-1) == 'p' ? 1 : 0;
			}
		}
		else
		{
			unset($query['col_filter']['owner']);
			unset($query['col_filter']['private']);
		}
		if ((int)$query['filter2'])	// not no distribution list
		{
			$query['col_filter']['list'] = (string) (int) $query['filter2'];
		}
		else
		{
			unset($query['col_filter']['list']);
		}
		if ($GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts'])
		{
			$query['col_filter']['account_id'] = null;
		}
		// enable/disable distribution lists depending on backend
		$query['no_filter2'] = !$this->lists_available($query['filter']);

		if (isset($this->org_views[(string) $query['org_view']]))	// we have an org view
		{
			if($query['actions'] && $query['actions']['open'])
			{
				// Just switched from contact view, update actions
				$query['actions'] = $this->get_actions($query['col_filter']['tid'], $query['org_view']);
			}
			unset($query['col_filter']['list']);	// does not work together
			$query['no_filter2'] = true;			// switch the distribution list selection off

			$query['template'] = 'addressbook.index.org_rows';

			if ($query['order'] != 'org_name')
			{
				$query['sort'] = 'ASC';
				$query['order'] = 'org_name';
			}
			if ($query['advanced_search'])
			{
				$query['op'] = $query['advanced_search']['operator'];
				unset($query['advanced_search']['operator']);
				$query['wildcard'] = $query['advanced_search']['meth_select'];
				unset($query['advanced_search']['meth_select']);
				$original_search = $query['search'];
				$query['search'] = $query['advanced_search'];
			}

			$rows = parent::organisations($query);
			if ($query['advanced_search'])
			{
				$query['search'] = $original_search;
				unset($query['wildcard']);
				unset($query['op']);
			}
			$GLOBALS['egw_info']['flags']['params']['manual'] = array('page' => 'ManualAddressbookIndexOrga');
		}
		else	// contacts view
		{
			if ($query['sitemgr_display'])
			{
				$query['template'] = $query['sitemgr_display'].'.rows';
			}
			else
			{
				$query['template'] = $do_email ? 'addressbook.email.rows' : 'addressbook.index.rows';
			}
			if ($query['org_view'])	// view the contacts of one organisation only
			{
				if (strpos($query['org_view'],'*AND*') !== false) $query['org_view'] = str_replace('*AND*','&',$query['org_view']);
				foreach(explode('|||',$query['org_view']) as $part)
				{
					list($name,$value) = explode(':',$part,2);
					// do NOT set invalid column, as this gives an SQL error ("AND AND" in sql)
					if (in_array($name, array('org_name','org_unit','adr_one_location')))
					{
						$query['col_filter'][$name] = $value;
					}
				}
			}
			else if($query['actions'] && !$query['actions']['edit'])
			{
				// Just switched from org view, update actions
				$query['actions'] = $this->get_actions($query['col_filter']['tid'], $query['org_view']);
			}
			// translate the select order to the really used over all 3 columns
			$sort = $query['sort'];
			switch($query['order'])		// "xxx<>'' DESC" sorts contacts with empty order-criteria always at the end
			{							// we don't exclude them, as the total would otherwise depend on the order-criteria
				case 'org_name':
					$order = "egw_addressbook.org_name<>''DESC,egw_addressbook.org_name $sort,n_family $sort,n_given $sort";
					break;
				default:
					if ($query['order'][0] == '#')	// we order by a custom field
					{
						$order = "{$query['order']} $sort,org_name $sort,n_family $sort,n_given $sort";
						break;
					}
					$query['order'] = 'n_family';
				case 'n_family':
					$order = "n_family<>'' DESC,n_family $sort,n_given $sort,org_name $sort";
					break;
				case 'n_given':
					$order = "n_given<>'' DESC,n_given $sort,n_family $sort,org_name $sort";
					break;
				case 'n_fileas':
					$order = "n_fileas<>'' DESC,n_fileas $sort";
					break;
				case 'adr_one_postalcode':
				case 'adr_two_postalcode':
					$order = $query['order']."<>'' DESC,".$query['order']." $sort,org_name $sort,n_family $sort,n_given $sort";
					break;
				case 'contact_modified':
				case 'contact_created':
					$order = "$query[order] IS NULL,$query[order] $sort,org_name $sort,n_family $sort,n_given $sort";
					break;
				case 'contact_id':
					$order = "egw_addressbook.$query[order] $sort";
			}
			if ($query['searchletter'])	// only show contacts if the order-criteria starts with the given letter
			{
				$no_letter_search = array('adr_one_postalcode', 'adr_two_postalcode', 'contact_id', 'contact_created','contact_modified');
				$query['col_filter'][] = (in_array($query['order'],$no_letter_search) ? 'org_name' : (substr($query['order'],0,1)=='#'?'':'egw_addressbook.').$query['order']).' '.
					$GLOBALS['egw']->db->capabilities['case_insensitive_like'].' '.$GLOBALS['egw']->db->quote($query['searchletter'].'%');
			}
			$wildcard = '%';
			$op = 'OR';
			if ($query['advanced_search'])
			{
				// Make sure op & wildcard are only valid options
				$op = $query['advanced_search']['operator'] == $op ? $op : 'AND';
				unset($query['advanced_search']['operator']);
				$wildcard = $query['advanced_search']['meth_select'] == $wildcard ? $wildcard : false;
				unset($query['advanced_search']['meth_select']);
			}
			//if ($do_email ) $email_only = array('id','owner','tid','n_fn','n_family','n_given','org_name','email','email_home');
			$rows = parent::search($query['advanced_search'] ? $query['advanced_search'] : $query['search'],$id_only,
				$order,'',$wildcard,false,$op,array((int)$query['start'],(int) $query['num_rows']),$query['col_filter']);

			// do we need to read the custom fields, depends on the column is enabled and customfields exist
			// $query['csv_export'] allways needs to read ALL cf's
			$columselection = $this->prefs['nextmatch-addressbook.'.($do_email ? 'email' : 'index').'.rows'];
			$available_distib_lists=$this->get_lists(EGW_ACL_READ);
			$columselection = $columselection && !$query['csv_export'] ? explode(',',$columselection) : array();
			if (!$id_only && $rows)
			{
				$show_custom_fields = (!$columselection || in_array('customfields',$columselection) || $query['csv_export']) && $this->customfields;
				$show_calendar = !$columselection || in_array('calendar_calendar',$columselection);
				$show_distributionlist = !$columselection || in_array('distrib_lists',$columselection) || count($available_distib_lists);
				if ($show_calendar || $show_custom_fields || $show_distributionlist)
				{
					foreach($rows as $val)
					{
						$ids[] = $val['id'];
					}
					if ($show_custom_fields)
					{
						foreach($columselection as $col)
						{
							if ($col[0] == '#') $selected_cfs[] = substr($col,1);
						}
						$customfields = $this->read_customfields($ids,$selected_cfs);
					}
					if ($show_calendar && !empty($ids)) $calendar = $this->read_calendar($ids);
					// distributionlist memership for the entrys
					//_debug_array($this->get_lists(EGW_ACL_EDIT));
					if ($show_distributionlist && $available_distib_lists)
					{
						$distributionlist = $this->read_distributionlist($ids,array_keys($available_distib_lists));
					}
				}
			}
		}
		if (!$rows) $rows = array();

		if ($id_only)
		{
			foreach($rows as $n => $row)
			{
				$rows[$n] = $row['id'];
			}
			return $this->total;	// no need to set other fields or $readonlys
		}
		$order = $query['order'];

		$readonlys = array();
		foreach($rows as $n => &$row)
		{
			$given = $row['n_given'] ? $row['n_given'] : ($row['n_prefix'] ? $row['n_prefix'] : '');

			switch($order)
			{
				default:	// postalcode, created, modified, ...
				case 'org_name':
					$row['line1'] = $row['org_name'];
					$row['line2'] = $row['n_family'].($given ? ', '.$given : '');
					break;
				case 'n_family':
					$row['line1'] = $row['n_family'].($given ? ', '.$given : '');
					$row['line2'] = $row['org_name'];
					break;
				case 'n_given':
					$row['line1'] = $given.' '.$row['n_family'];
					$row['line2'] = $row['org_name'];
					break;
				case 'n_fileas':
					if (!$row['n_fileas']) $row['n_fileas'] = $this->fileas($row);
					list($row['line1'],$row['line2']) = explode(': ',$row['n_fileas']);
					break;
			}
			if (isset($this->org_views[(string) $query['org_view']]))
			{
				$row['type'] = 'home';
				$row['type_label'] = lang('Organisation');

				if ($query['filter'] && !($this->grants[(int)$query['filter']] & EGW_ACL_DELETE))
				{
					$row['class'] .= 'rowNoDelete ';
				}
				$row['class'] .= 'rowNoEdit ';	// no edit in OrgView
			}
			else
			{
				$this->type_icon($row['owner'],$row['private'],$row['tid'],$row['type'],$row['type_label']);

				static $tel2show = array('tel_work','tel_cell','tel_home','tel_fax');
				static $prefer_marker;
				if (is_null($prefer_marker))
				{
					// as et2 adds options with .text(), it can't be entities, but php knows no string literals with utf-8
					$prefer_marker = html_entity_decode(' &#9829;', ENT_NOQUOTES, 'utf-8');
				}
				foreach($tel2show as $name)
				{
					$row[$name] .= ' '.($row['tel_prefer'] == $name ? $prefer_marker : '');		// .' ' to NOT remove the field
				}
				// allways show the prefered phone, if not already shown
				if (!in_array($row['tel_prefer'],$tel2show) && $row[$row['tel_prefer']])
				{
					$row['tel_prefered'] = $row[$row['tel_prefer']].$prefer_marker;
				}
				if (!$row['owner'])
				{
					$row['class'] .= 'rowAccount rowNoDelete';
				}
				elseif (!$this->check_perms(EGW_ACL_DELETE,$row) || (!$GLOBALS['egw_info']['user']['apps']['admin'] && $this->config['history'] != 'userpurge' && $query['col_filter']['tid'] == addressbook_so::DELETED_TYPE))
				{
					$row['class'] .= 'rowNoDelete ';
				}
				if (!$this->check_perms(EGW_ACL_EDIT,$row))
				{
					$row['class'] .= 'rowNoEdit ';
				}

				unset($row['jpegphoto']);	// unused and messes up json encoding (not utf-8)

				if (isset($customfields[$row['id']]))
				{
					foreach($this->customfields as $name => $data)
					{
						$row['#'.$name] = $customfields[$row['id']]['#'.$name];
					}
				}
				if (isset($distributionlist[$row['id']]))
				{
					$row['distrib_lists'] = implode("\n",array_values($distributionlist[$row['id']]));
					//if ($show_distributionlist) $readonlys['distrib_lists'] =true;
				}
				if (isset($calendar[$row['id']]))
				{
					foreach($calendar[$row['id']] as $name => $data)
					{
						$row[$name] = $data;
					}
				}
			}

			// hide region for address format 'postcode_city'
			if (($row['addr_format']  = $this->addr_format_by_country($row['adr_one_countryname']))=='postcode_city') unset($row['adr_one_region']);
			if (($row['addr_format2'] = $this->addr_format_by_country($row['adr_two_countryname']))=='postcode_city') unset($row['adr_two_region']);

			// respect category permissions
			if(!empty($row['cat_id']))
			{
				$row['cat_id'] = $this->categories->check_list(EGW_ACL_READ,$row['cat_id']);
			}
		}
		$readonlys['no_distrib_lists'] = (bool)$show_distributionlist;

		// disable customfields column, if we have no customefield(s)
		if (!$this->customfields) $rows['no_customfields'] = true;

		$rows['order'] = $order;
		$rows['call_popup'] = $this->config['call_popup'];
		$rows['customfields'] = array_values($this->customfields);

		// full app-header with all search criteria specially for the print
		$GLOBALS['egw_info']['flags']['app_header'] = lang('addressbook');
		if ($query['filter'] !== '' && !isset($this->org_views[$query['org_view']]))
		{
			$GLOBALS['egw_info']['flags']['app_header'] .= ' '.($query['filter'] == '0' ? lang('accounts') :
				($GLOBALS['egw']->accounts->get_type($query['filter']) == 'g' ?
					lang('Group %1',$GLOBALS['egw']->accounts->id2name($query['filter'])) :
					common::grab_owner_name((int)$query['filter']).
						(substr($query['filter'],-1) == 'p' ? ' ('.lang('private').')' : '')));
		}
		if ($query['org_view'])
		{
			$GLOBALS['egw_info']['flags']['app_header'] .= ': '.$query['org_view_label'];
			// Make sure option is there
			if(!array_key_exists($query['org_view'], $this->org_views))
			{
				$this->org_views += $this->_get_org_name($query['org_view']);
				$rows['sel_options']['org_view'] = $this->org_views;
			}
		}
		if($query['advanced_search'])
		{
			$GLOBALS['egw_info']['flags']['app_header'] .= ': '.lang('Advanced search');
		}
		if ($query['cat_id'])
		{
			$GLOBALS['egw_info']['flags']['app_header'] .= ': '.lang('Category').' '.$GLOBALS['egw']->categories->id2name($query['cat_id']);
		}
		if ($query['searchletter'])
		{
			$order = $order == 'n_given' ? lang('first name') : ($order == 'n_family' ? lang('last name') : lang('Organisation'));
			$GLOBALS['egw_info']['flags']['app_header'] .= ' - '.lang("%1 starts with '%2'",$order,$query['searchletter']);
		}
		if ($query['search'] && !$query['advanced_search']) // do not add that, if we have advanced search active
		{
			$GLOBALS['egw_info']['flags']['app_header'] .= ' - '.lang("Search for '%1'",$query['search']);
		}
		return $this->total;
	}

	/**
	 * Get addressbook type icon from owner, private and tid
	 *
	 * @param int $owner user- or group-id or 0 for accounts
	 * @param boolean $private
	 * @param string $tid 'n' for regular addressbook
	 * @param string &$icon icon-name
	 * @param string &$label translated label
	 */
	function type_icon($owner,$private,$tid,&$icon,&$label)
	{
		if (!$owner)
		{
			$icon = 'accounts';
			$label = lang('accounts');
		}
		elseif ($private)
		{
			$icon = 'private';
			$label = lang('private');
		}
		elseif ($GLOBALS['egw']->accounts->get_type($owner) == 'g')
		{
			$icon = 'group';
			$label = lang('group %1',$GLOBALS['egw']->accounts->id2name($owner));
		}
		else
		{
			$icon = 'personal';
			$label = $owner == $this->user ? lang('personal') : common::grab_owner_name($owner);
		}
		// show tid icon for tid!='n' AND only if one is defined
		if ($tid != 'n' && $this->content_types[$tid]['options']['icon'])
		{
			$icon = $this->content_types[$tid]['options']['icon'];
			$label = $this->content_types[$tid]['name'].' ('.$label.')';
		}
	}

	/**
	* Edit a contact
	*
	* @param array $content=null submitted content
	* @param int $_GET['contact_id'] contact_id mainly for popup use
	* @param bool $_GET['makecp'] true if you want to copy the contact given by $_GET['contact_id']
	*/
	function edit($content=null)
	{
		if (is_array($content))
		{
			list($button) = @each($content['button']);
			unset($content['button']);
			$content['private'] = (int) ($content['owner'] && substr($content['owner'],-1) == 'p');
			$content['owner'] = (string) (int) $content['owner'];
			$content['cat_id'] = $this->config['cat_tab'] === 'Tree' ? $content['cat_id_tree'] : $content['cat_id'];
			if ($this->config['private_cf_tab']) $content = (array)$content['private_cfs'] + $content;
			unset($content['private_cfs']);

			switch($button)
			{
				case 'save':
				case 'apply':
					if ($content['delete_photo'])
					{
						$content['jpegphoto'] = null;
						unset($content['delete_photo']);
					}
					if (is_array($content['upload_photo']) && !empty($content['upload_photo']['tmp_name']) &&
						$content['upload_photo']['tmp_name'] != 'none' &&
						($f = fopen($content['upload_photo']['tmp_name'],'r')))
					{
						$content['jpegphoto'] = $this->resize_photo($f);
						fclose($f);
						unset($content['upload_photo']);
					}
					$links = false;
					if (!$content['id'] && is_array($content['link_to']['to_id']))
					{
						$links = $content['link_to']['to_id'];
					}
					$fullname = $old_fullname = parent::fullname($content);
					if ($content['id'] && $content['org_name'] && $content['change_org'])
					{
						$old_org_entry = $this->read($content['id']);
						$old_fullname = ($old_org_entry['n_fn'] ? $old_org_entry['n_fn'] : parent::fullname($old_org_entry));
					}
					if ( $content['n_fn'] != $fullname ||  $fullname != $old_fullname)
					{
						unset($content['n_fn']);
					}
					// Country codes
					foreach(array('adr_one', 'adr_two') as $c_prefix)
					{
						if ($content[$c_prefix.'_countrycode'] == '-custom-')
						{
							$content[$c_prefix.'_countrycode'] = null;
						}
					}
					$content['msg'] = '';
					$this->error = false;
					foreach((array)$content['pre_save_callbacks'] as $callback)
					{
						try {
							if (($success_msg = call_user_func_array($callback, array(&$content))))
							{
								$content['msg'] .= ($content['msg'] ? ', ' : '').$success_msg;
							}
						}
						catch (Exception $ex) {
							$content['msg'] .= ($content['msg'] ? ', ' : '').$ex->getMessage();
							$button = 'apply';	// do not close dialog
							$this->error = true;
							break;
						}
					}
					if ($this->error)
					{
						// error in pre_save_callbacks
					}
					elseif ($this->save($content))
					{
						$content['msg'] .= ($content['msg'] ? ', ' : '').lang('Contact saved');

						foreach((array)$content['post_save_callbacks'] as $callback)
						{
							try {
								if (($success_msg = call_user_func_array($callback, array(&$content))))
								{
									$content['msg'] .= ', '.$success_msg;
								}
							}
							catch (Exception $ex) {
								$content['msg'] .= ', '.$ex->getMessage();
								$button = 'apply';	// do not close dialog
								$this->error = true;
								break;
							}
						}

						if ($content['change_org'] && $old_org_entry && ($changed = $this->changed_fields($old_org_entry,$content,true)) &&
							($members = $this->org_similar($old_org_entry['org_name'],$changed)))
						{
							//foreach($changed as $name => $old_value) echo "<p>$name: '$old_value' --> '{$content[$name]}'</p>\n";
							list($changed_members,$changed_fields,$failed_members) = $this->change_org($old_org_entry['org_name'],$changed,$content,$members);
							if ($changed_members)
							{
								$content['msg'] .= ', '.lang('%1 fields in %2 other organisation member(s) changed',$changed_fields,$changed_members);
							}
							if ($failed_members)
							{
								$content['msg'] .= ', '.lang('failed to change %1 organisation member(s) (insufficent rights) !!!',$failed_members);
							}
						}
					}
					elseif($this->error === true)
					{
						$content['msg'] = lang('Error: the entry has been updated since you opened it for editing!').'<br />'.
							lang('Copy your changes to the clipboard, %1reload the entry%2 and merge them.','<a href="'.
								htmlspecialchars(egw::link('/index.php',array(
									'menuaction' => 'addressbook.addressbook_ui.edit',
									'contact_id' => $content['id'],
								))).'">','</a>');
						break;	// dont refresh the list
					}
					else
					{
						$content['msg'] = lang('Error saving the contact !!!').
							($this->error ? ' '.$this->error : '');
						$button = 'apply';	// to not leave the dialog
					}
					// writing links for new entry, existing ones are handled by the widget itself
					if ($links && $content['id'])
					{
						egw_link::link('addressbook',$content['id'],$links);
					}
					egw_framework::refresh_opener($content['msg'], 'addressbook', $content['id'],  $content['id'] ? 'update' : 'add',
						null, null, null, $this->error ? 'error' : 'success');
					if ($button == 'save')
					{
						egw_framework::window_close();
					}
					else
					{
						egw_framework::message($content['msg'], $this->error ? 'error' : 'success');
						unset($content['msg']);
					}
					$content['link_to']['to_id'] = $content['id'];
					break;

				case 'delete':
					if($this->action('delete',array($content['id']),false,$success,$failed,$action_msg,'',$content['msg']))
					{
						if ($GLOBALS['egw']->currentapp == 'addressbook')
						{
							egw_framework::refresh_opener(lang('Contact deleted'), 'addressbook', $content['id'], 'delete' );
							egw_framework::window_close();
						}
						else
						{
							egw_framework::refresh_opener(lang('Contact deleted'), 'addressbook', $content['id'], null, 'addressbook');
							egw_framework::window_close();
						}
					}
					else
					{
						$content['msg'] = lang('Error deleting the contact !!!');
					}
					break;
			}
			// type change
		}
		else
		{
			$content = array();
			$contact_id = $_GET['contact_id'] ? $_GET['contact_id'] : ((int)$_GET['account_id'] ? 'account:'.(int)$_GET['account_id'] : 0);
			$view = $_GET['view'];
			// new contact --> set some defaults
			if ($contact_id && is_array($content = $this->read($contact_id)))
			{
				$contact_id = $content['id'];	// it could have been: "account:$account_id"
				if (!$this->check_perms(EGW_ACL_EDIT, $content))
				{
					$view = true;
				}
			}
			else // not found
			{
				$state = egw_session::appsession('index','addressbook');
				// check if we create the new contact in an existing org
				if (($org = $_GET['org']))
				{
					// arguments containing a comma get quoted by etemplate/js/nextmatch_action.js
					// leading to error in egw_db::column_data_implode, if not unquoted
					if ($org[0] == '"') $org = substr($org, 1, -1);
					$content = $this->read_org($org);
				}
				elseif ($state['org_view'] && !isset($this->org_views[$state['org_view']]))
				{
					$content = $this->read_org($state['org_view']);
				}
				else
				{
					if ($GLOBALS['egw_info']['user']['preferences']['common']['country'])
					{
						$content['adr_one_countrycode'] =
							$GLOBALS['egw_info']['user']['preferences']['common']['country'];
						$content['adr_one_countryname'] =
							$GLOBALS['egw']->country->get_full_name($GLOBALS['egw_info']['user']['preferences']['common']['country']);
						$content['adr_two_countrycode'] =
							$GLOBALS['egw_info']['user']['preferences']['common']['country'];
						$content['adr_two_countryname'] =
							$GLOBALS['egw']->country->get_full_name($GLOBALS['egw_info']['user']['preferences']['common']['country']);
					}
					if ($this->prefs['fileas_default']) $content['fileas_type'] = $this->prefs['fileas_default'];
				}
				if (isset($_GET['owner']) && $_GET['owner'] !== '')
				{
					$content['owner'] = $_GET['owner'];
				}
				else
				{
					$content['owner'] = (string)$state['filter'];
				}
				$content['private'] = (int) ($content['owner'] && substr($content['owner'],-1) == 'p');
				if ($content['owner'] === '' || !($this->grants[$content['owner'] = (string) (int) $content['owner']] & EGW_ACL_ADD))
				{
					$content['owner'] = $this->default_addressbook;
					$content['private'] = (int)$this->default_private;

					if (!($this->grants[$content['owner'] = (string) (int) $content['owner']] & EGW_ACL_ADD))
					{
						$content['owner'] = (string) $this->user;
						$content['private'] = 0;
					}
				}
				$new_type = array_keys($this->content_types);
				// fetch active type to preset the type, if param typeid is not passed
				$active_tid = egw_cache::getSession('addressbook','active_tid');
				if ($active_tid && strtoupper($active_tid) === 'D') unset($active_tid);
				$content['tid'] = $_GET['typeid'] ? $_GET['typeid'] : ($active_tid?$active_tid:$new_type[0]);
				foreach($this->get_contact_columns() as $field)
				{
					if ($_GET['presets'][$field])
					{
						if ($field=='email'||$field=='email_home')
						{
							$singleAddress = imap_rfc822_parse_adrlist($_GET['presets'][$field],'');
							//error_log(__METHOD__.__LINE__.' Address:'.$singleAddress[0]->mailbox."@".$singleAddress[0]->host.", ".$singleAddress[0]->personal);
							if (!(!is_array($singleAddress) || count($singleAddress)<1))
							{
								$content[$field] = $singleAddress[0]->mailbox."@".$singleAddress[0]->host;
								if (!empty($singleAddress[0]->personal))
								{
									if (strpos($singleAddress[0]->personal,',')===false)
									{
										list($P_n_given,$P_n_family,$P_org_name)=explode(' ',$singleAddress[0]->personal,3);
										if (strlen(trim($P_n_given))>0) $content['n_given'] = trim($P_n_given);
										if (strlen(trim($P_n_family))>0) $content['n_family'] = trim($P_n_family);
										if (strlen(trim($P_org_name))>0) $content['org_name'] = trim($P_org_name);
									}
									else
									{
										list($P_n_family,$P_other)=explode(',',$singleAddress[0]->personal,2);
										if (strlen(trim($P_n_family))>0) $content['n_family'] = trim($P_n_family);
										if (strlen(trim($P_other))>0)
										{
											list($P_n_given,$P_org_name)=explode(',',$P_other,2);
											if (strlen(trim($P_n_given))>0) $content['n_given'] = trim($P_n_given);
											if (strlen(trim($P_org_name))>0) $content['org_name'] = trim($P_org_name);
										}
									}
								}
							}
							else
							{
								$content[$field] = $_GET['presets'][$field];
							}
						}
						else
						{
							$content[$field] = $_GET['presets'][$field];
						}
					}
				}
				if (isset($_GET['presets']))
				{
					foreach(array('email','email_home','n_family','n_given','org_name') as $field)
					{
						if (!empty($content[$field]))
						{
							egw_framework::set_onload("app.addressbook.check_value(document.getElementById('exec[$field]'),0);");
							break;
						}
					}
					if (empty($content['n_fn'])) $content['n_fn'] = $this->fullname($content);
				}
				$content['creator'] = $this->user;
				$content['created'] = $this->now_su;
				unset($state);
				//_debug_array($content);
			}

			if ($_GET['msg']) $content['msg'] = strip_tags($_GET['msg']);	// dont allow HTML!

			if($content && $_GET['makecp'])	// copy the contact
			{
				$content['link_to']['to_id'] = 0;
				egw_link::link('addressbook',$content['link_to']['to_id'],'addressbook',$content['id'],
					lang('Copied by %1, from record #%2.',common::display_fullname('',
					$GLOBALS['egw_info']['user']['account_firstname'],$GLOBALS['egw_info']['user']['account_lastname']),
					$content['id']));
				// create a new contact with the content of the old
				foreach($content as $key => $value)
				{
					if(!in_array($key, self::$copy_fields) || in_array($key, array('etag','carddav_name','uid')))
					{
						unset($content[$key]);
					}
				}
				if(!isset($content['owner']))
				{
					$content['owner'] = $this->default_private ? $this->user.'p' : $this->default_addressbook;
				}
				$content['creator'] = $this->user;
				$content['created'] = $this->now_su;
				$content['msg'] = lang('Contact copied');
			}
			else
			{
				if (is_numeric($contact_id)) $content['link_to']['to_id'] = $contact_id;
			}
			// automatic link new entries to entries specified in the url
			if (!$contact_id && isset($_REQUEST['link_app']) && isset($_REQUEST['link_id']) && !is_array($content['link_to']['to_id']))
			{
				$link_ids = is_array($_REQUEST['link_id']) ? $_REQUEST['link_id'] : array($_REQUEST['link_id']);
				foreach(is_array($_REQUEST['link_app']) ? $_REQUEST['link_app'] : array($_REQUEST['link_app']) as $n => $link_app)
				{
					$link_id = $link_ids[$n];
					if (preg_match('/^[a-z_0-9-]+:[:a-z_0-9-]+$/i',$link_app.':'.$link_id))	// gard against XSS
					{
						egw_link::link('addressbook',$content['link_to']['to_id'],$link_app,$link_id);
					}
				}
			}
		}
		if ($content['id'])
		{
			// last and next calendar date
			list(,$dates) = each($this->read_calendar(array($content['id']),false));
			if(is_array($dates)) $content += $dates;
		}
		// Avoid ID conflict with tree & selectboxes
		$content['cat_id_tree'] = $content['cat_id'];

		// Avoid setting conflicts with private custom fields
		if ($this->config['private_cf_tab'])
		{
			$content['private_cfs'] = array();
			foreach(config::get_customfields('addressbook', true) as $name => $cf)
			{
				if ($cf['private'] && isset($content['#'.$name]))
				{
					$content['private_cfs']['#'.$name] = $content['#'.$name];
				}
			}
		}
		// how to display addresses
		$content['addr_format']  = $this->addr_format_by_country($content['adr_one_countryname']);
		$content['addr_format2'] = $this->addr_format_by_country($content['adr_two_countryname']);

		//_debug_array($content);
		$readonlys['button[delete]'] = !$content['owner'] || !$this->check_perms(EGW_ACL_DELETE,$content);
		$readonlys['button[copy]'] = $readonlys['button[edit]'] = $readonlys['button[vcard]'] = true;
		$readonlys['button[save]'] = $readonlys['button[apply]'] = $view;

		$sel_options['fileas_type'] = $this->fileas_options($content);
		$sel_options['owner'] = $this->get_addressbooks(EGW_ACL_ADD);
		if ($content['owner']) unset($sel_options['owner'][0]);	// do not offer to switch to accounts, as we do not support moving contacts to accounts
		if ((string) $content['owner'] !== '')
		{
			if (!isset($sel_options['owner'][(int)$content['owner']]))
			{
				$sel_options['owner'][(int)$content['owner']] = !$content['owner'] ? lang('Accounts') :
					common::grab_owner_name($content['owner']);
			}
			$readonlys['owner'] = !$content['owner'] || 		// dont allow to move accounts, as this mean deleting the user incl. all content he owns
				$content['id'] && !$this->check_perms(EGW_ACL_DELETE,$content);	// you need delete rights to move an existing contact into an other addressbook
		}
		// set the unsupported fields from the backend to readonly
		foreach($this->get_fields('unsupported',$content['id'],$content['owner']) as $field)
		{
			$readonlys[$field] = true;
		}
		if ($readonlys['n_fileas']) $readonlys['fileas_type'] = true;
		// disable not needed tabs
		$readonlys['tabs']['cats'] = !($content['cat_tab'] = $this->config['cat_tab']);
		$readonlys['tabs']['custom'] = !$this->customfields || $this->get_backend($content['id'],$content['owner']) == $this->so_accounts;
		$readonlys['tabs']['custom_private'] = $readonlys['tabs']['custom'] || !$this->config['private_cf_tab'];
		$readonlys['tabs']['distribution_list'] = !$content['distrib_lists'];#false;
		$readonlys['tabs']['history'] = $this->contact_repository != 'sql' || !$content['id'] ||
			$this->account_repository != 'sql' && $content['account_id'];
		if (!$content['id']) $readonlys['button[delete]'] = !$content['id'];
		if ($this->config['private_cf_tab']) $content['no_private_cfs'] = 0;
		$readonlys['change_org'] = empty($content['org_name']) || $view;

		// for editing the own account (by a non-admin), enable only the fields allowed via the "own_account_acl"
		if (!$content['owner'] && !$this->check_perms(EGW_ACL_EDIT, $content))
		{
			$this->_set_readonlys_for_own_account_acl($readonlys,$id);
		}
		for($i = -23; $i<=23; $i++) $tz[$i] = ($i > 0 ? '+' : '').$i;
		$sel_options['tz'] = $tz;
		$content['tz'] = $content['tz'] ? $content['tz'] : '0';
		if (count($this->content_types) > 1)
		{
			foreach($this->content_types as $type => $data)
			{
				$sel_options['tid'][$type] = $data['name'];
			}
			$content['typegfx'] = html::image('addressbook',$this->content_types[$content['tid']]['options']['icon'],'',' width="16px" height="16px"');
		}
		else
		{
			$content['no_tid'] = true;
		}

		$content['view'] = false;
		$content['link_to'] = array(
			'to_app' => 'addressbook',
			'to_id'  => $content['link_to']['to_id'],
		);

		// Links for deleted entries
		if($content['tid'] == addressbook_so::DELETED_TYPE)
		{
			$content['link_to']['show_deleted'] = true;
			if(!$GLOBALS['egw_info']['user']['apps']['admin'] && $this->config['history'] != 'userpurge')
			{
				$readonlys['button[delete]'] = true;
			}
		}

		// Enable history
		$this->setup_history($content, $sel_options);

		$content['photo'] = $this->photo_src($content['id'],$content['jpegphoto'],'photo',$content['etag']);

		if ($content['private']) $content['owner'] .= 'p';

		//$GLOBALS['egw_info']['flags']['include_xajax'] = true;

		if (!$this->tmpl->read($this->content_types[$content['tid']]['options']['template'] ? $this->content_types[$content['tid']]['options']['template'] : 'addressbook.edit'))
		{
			$content['msg']  = lang('WARNING: Template "%1" not found, using default template instead.', $this->content_types[$content['tid']]['options']['template'])."\n";
			$content['msg'] .= lang('Please update the templatename in your customfields section!');
			$this->tmpl->read('addressbook.edit');
		}

		// allow other apps to add tabs to addressbook edit
		$preserve = $content;
		$content['jpegphoto'] = !empty($content['jpegphoto']);	// unused and messes up json encoding (not utf-8)
		$this->tmpl->setElementAttribute('tabs', 'add_tabs', true);
		$tabs =& $this->tmpl->getElementAttribute('tabs', 'tabs');
		if (($first_call = !isset($tabs)))
		{
			$tabs = array();
		}
		//error_log(__LINE__.': '.__METHOD__."() first_call=$first_call");
		$hook_data = $GLOBALS['egw']->hooks->process(array('location' => 'addressbook_edit')+$content);
		//error_log(__METHOD__."() hook_data=".array2string($hook_data));
		foreach($hook_data as $extra_tabs)
		{
			if (!$extra_tabs) continue;

			foreach(isset($extra_tabs[0]) ? $extra_tabs : array($extra_tabs) as $extra_tab)
			{
				if ($extra_tab['data'] && is_array($extra_tab['data']))
				{
					$content = array_merge($content, $extra_tab['data']);
				}
				if ($extra_tab['preserve'] && is_array($extra_tab['preserve']))
				{
					$preserve = array_merge($preserve, $extra_tab['preserve']);
				}
				if ($extra_tab['readonlys'] && is_array($extra_tab['readonlys']))
				{
					$readonlys = array_merge($readonlys, $extra_tab['readonlys']);
				}
				// we must NOT add tabs and callbacks more then once!
				if (!$first_call) continue;

				if (!empty($extra_tab['pre_save_callback']))
				{
					$preserve['pre_save_callbacks'][] = $extra_tab['pre_save_callback'];
				}
				if (!empty($extra_tab['post_save_callback']))
				{
					$preserve['post_save_callbacks'][] = $extra_tab['post_save_callback'];
				}
				if (!empty($extra_tab['label']) && !empty($extra_tab['name']))
				{
					$tabs[] = array(
						'label' =>	$extra_tab['label'],
						'template' =>	$extra_tab['name'],
						'prepend' => $extra_tab['prepend'],
					);
				}
				//error_log(__METHOD__."() changed tabs=".array2string($tabs));
			}
		}
		return $this->tmpl->exec('addressbook.addressbook_ui.edit', $content, $sel_options, $readonlys, $preserve, 2);
	}

	/**
	 * Set the readonlys for non-admins editing their own account
	 *
	 * @param array &$readonlys
	 * @param int $id
	 */
	function _set_readonlys_for_own_account_acl(&$readonlys,$id)
	{
		// regular fields depending on the backend
		foreach($this->get_fields('supported',$id,0) as $field)
		{
			if (!$this->own_account_acl || !in_array($field,$this->own_account_acl))
			{
				$readonlys[$field] = true;
				switch($field)
				{
					case 'tel_work':
					case 'tel_cell':
					case 'tel_home':
						$readonlys[$field.'2'] = true;
						break;
					case 'n_fileas':
						$readonlys['fileas_type'] = true;
						break;
				}
			}
		}
		// custom fields
		if ($this->customfields)
		{
			foreach($this->customfields as $name => $data)
			{
				if (!$this->own_account_acl || !in_array('#'.$name,$this->own_account_acl))
				{
					$readonlys['#'.$name] = true;
				}
			}
		}
		// links
		if (!$this->own_account_acl || !in_array('link_to',$this->own_account_acl))
		{
			$readonlys['link_to'] = true;
		}
	}

	/**
	 * Doublicate check: returns similar contacts: same email or 2 of name, firstname, org
	 *
	 * Also update/return fileas options, if necessary.
	 *
	 * @param array $values contact values from form
	 * @param string $name name of changed value, eg. "email"
	 * @param int $own_id=0 own contact id, to not check against it
	 * @return array with keys 'msg' => "EMail address exists, do you want to open contact?" (or null if not existing)
	 * 	'data' => array of id => "full name (addressbook)" pairs
	 *  'fileas_options'
	 */
	public function ajax_check_values($values, $name, $own_id=0)
	{
		if (preg_match('/^exec\[([^\]]+)\]$/', $name, $matches)) $name = $matches[1];	// remove exec[ ]

		$ret = array('doublicates' => array(), 'msg' => null);

		// if email changed, check for doublicates
		if (in_array($name, array('email', 'email_home')))
		{
			if (preg_match('/^'.url_widget::EMAIL_PREG.'$/i', $values[$name]))	// only search for real email addresses, to not return to many contacts
			{
				$contacts = parent::search(array(
					'email' => $values[$name],
					'email_home' => $values[$name],
				),$only_keys=false, $order_by='', $extra_cols='', $wildcard='', $empty=False, $op='OR');
			}
		}
		else
		{
			// only set fileas-options if other then email changed
			$ret['fileas_options'] = array_values($this->fileas_options($values));
			// Full options for et2
			$ret['fileas_sel_options'] = $this->fileas_options($values);

			// if name, firstname or org changed and at least 2 are specified, check for doublicates
			if (in_array($name, array('n_given', 'n_family', 'org_name')) &&
				!empty($values['n_given'])+!empty($values['n_family'])+!empty($values['org_name']) >= 2)
			{
				$filter = array();
				foreach(array('email', 'n_given', 'n_family', 'org_name') as $n)	// use email too, to exclude obvious false positives
				{
					if (!empty($values[$n])) $filter[$n] = $values[$n];
				}
				$contacts = parent::search($criteria='', $only_keys=false, $order_by='', $extra_cols='', $wildcard='',
					$empty=False, $op='AND', $start=false, $filter);
			}
		}
		if ($contacts)
		{
			foreach($contacts as $contact)
			{
				if ($own_id && $contact['id'] == $own_id) continue;

				$ret['doublicates'][$contact['id']] = $this->fileas($contact).' ('.
					(!$contact['owner'] ? lang('Accounts') : ($contact['owner'] == $this->user ?
					($contact['private'] ? lang('Private') : lang('Personal')) : common::grab_owner_name($contact['owner']))).')';
			}
			if ($ret['doublicates'])
			{
				$ret['msg'] = lang('Similar contacts found:').
					"\n\n".implode("\n", $ret['doublicates'])."\n\n".
					lang('Open for editing?');
			}
		}
		//error_log(__METHOD__.'('.array2string($values).", '$name', $own_id) doublicates found ".array2string($ret['doublicates']));
		egw_json_response::get()->data($ret);
	}

	/**
	 * CRM view
	 *
	 * @param array $content
	 */
	function view(array $content=null)
	{
		// CRM list comes from content, request, or preference
		$crm_list = $content['crm_list'] ? $content['crm_list'] :
			($_GET['crm_list'] ? $_GET['crm_list'] : $GLOBALS['egw_info']['user']['preferences']['addressbook']['crm_list']);
		if(!$crm_list || $crm_list == '~edit~') $crm_list = 'infolog';

		if(is_array($content))
		{
			list($button) = each($content['button']);
			switch ($content['toolbar'] ? $content['toolbar'] : $button)
			{
				case 'vcard':
					egw::redirect_link('/index.php','menuaction=addressbook.uivcard.out&ab_id=' .$content['id']);

				case 'cancel':
					egw::redirect_link('/index.php','menuaction=addressbook.addressbook_ui.index&ajax=true');

				case 'delete':
					egw::redirect_link('/index.php',array(
						'menuaction' => 'addressbook.addressbook_ui.index',
						'msg' => $this->delete($content) ? lang('Contact deleted') : lang('Error deleting the contact !!!'),
					));

				case 'next':
					$inc = 1;
					// fall through
				case 'back':
					if (!isset($inc)) $inc = -1;
					// get next/previous contact in selection
					$query = egw_session::appsession('index', 'addressbook');
					$query['start'] = $content['index'] + $inc;
					$query['num_rows'] = 1;
					$rows = $readonlys = array();
					$num_rows = $this->get_rows($query, $rows, $readonlys, true);
					//error_log(__METHOD__."() get_rows()=$num_rows rows=".array2string($rows));
					$contact_id = $rows[0];
					if(!$contact_id || !is_array($content = $this->read($contact_id)))
					{
						egw::redirect_link('/index.php',array(
							'menuaction' => 'addressbook.addressbook_ui.index',
							'msg' => $content,
							'ajax' => 'true'
						));
					}
					$content['index'] = $query['start'];

					// List nextmatch is already there, just update the filter
					if($contact_id && egw_json_request::isJSONRequest())
					{
						switch($crm_list)
						{
							case 'infolog':
							case 'tracker':
							default:
								egw_json_response::get()->apply('app.addressbook.view_set_list',Array(Array('action'=>'addressbook', 'action_id' => $contact_id)));
								break;
						}

						// Clear contact_id, it's used as a flag to send the list
						unset($contact_id);
					}
					break;
			}
		}
		else
		{
			$contact_id = $_GET['contact_id'] ? $_GET['contact_id'] : ((int)$_GET['account_id'] ? 'account:'.(int)$_GET['account_id'] : 0);
			if(!$contact_id || !is_array($content = $this->read($contact_id)))
			{
				egw::redirect_link('/index.php',array(
					'menuaction' => 'addressbook.addressbook_ui.index',
					'msg' => $content,
					'ajax' => 'true'
				));
			}
			if (isset($_GET['index']))
			{
				$content['index'] = (int)$_GET['index'];
				// get number of rows to determine if we can have a next button
				$query = egw_session::appsession('index', 'addressbook');
				$query['start'] = $content['index'];
				$query['num_rows'] = 1;
				$rows = $readonlys = array();
				$num_rows = $this->get_rows($query, $rows, $readonlys, true);
			}
		}
		$content['jpegphoto'] = !empty($content['jpegphoto']);	// unused and messes up json encoding (not utf-8)

		// make everything not explicit mentioned readonly
		$readonlys['__ALL__'] = true;
		$readonlys['photo'] = $readonlys['button[cancel]'] = $readonlys['button[copy]'] =
			$readonlys['button[ok]'] = $readonlys['button[more]'] = $readonlys['toolbar'] = false;

		foreach(array_keys($this->contact_fields) as $key)
		{
			if (in_array($key,array('tel_home','tel_work','tel_cell','tel_fax')))
			{
				$content[$key.'2'] = $content[$key];
			}
		}

		// respect category permissions
		if(!empty($content['cat_id']))
		{
			$content['cat_id'] = $this->categories->check_list(EGW_ACL_READ,$content['cat_id']);
		}
		$content['cat_id_tree'] = $content['cat_id'];

		$content['view'] = true;
		$content['link_to'] = array(
			'to_app' => 'addressbook',
			'to_id'  => $content['id'],
		);
		// Links for deleted entries
		if($content['tid'] == addressbook_so::DELETED_TYPE)
		{
			$content['link_to']['show_deleted'] = true;
		}
		$readonlys['button[delete]'] = !$content['owner'] || !$this->check_perms(EGW_ACL_DELETE,$content);
		$readonlys['button[edit]'] = !$this->check_perms(EGW_ACL_EDIT,$content);

		// how to display addresses
		$content['addr_format']  = $this->addr_format_by_country($content['adr_one_countryname']);
		$content['addr_format2'] = $this->addr_format_by_country($content['adr_two_countryname']);

		$sel_options['fileas_type'][$content['fileas_type']] = $this->fileas($content);
		$sel_options['owner'] = $this->get_addressbooks();
		for($i = -23; $i<=23; $i++) $tz[$i] = ($i > 0 ? '+' : '').$i;
		$sel_options['tz'] = $tz;
		$content['tz'] = $content['tz'] ? $content['tz'] : 0;
		if (count($this->content_types) > 1)
		{
			foreach($this->content_types as $type => $data)
			{
				$sel_options['tid'][$type] = $data['name'];
			}
			$content['typegfx'] = html::image('addressbook',$this->content_types[$content['tid']]['options']['icon'],'',' width="16px" height="16px"');
		}
		else
		{
			$content['no_tid'] = true;
		}
		$this->tmpl->read('addressbook.view');
		/*if (!$this->tmpl->read($this->content_types[$content['tid']]['options']['template'] ? $this->content_types[$content['tid']]['options']['template'] : 'addressbook.edit'))
		{
			$content['msg']  = lang('WARNING: Template "%1" not found, using default template instead.', $this->content_types[$content['tid']]['options']['template'])."\n";
			$content['msg'] .= lang('Please update the templatename in your customfields section!');
			$this->tmpl->read('addressbook.edit');
		}*/
		if ($this->private_addressbook && $content['private'] && $content['owner'] == $this->user)
		{
			$content['owner'] .= 'p';
		}
		$this->tmpl->set_cell_attribute('change_org','disabled',true);

		// Prevent double countries - invalid code blanks it, disabling doesn't work
		$content['adr_one_countrycode'] = '-';
		$content['adr_two_countrycode'] = '-';

		// Enable history
		$this->setup_history($content, $sel_options);

		// disable not needed tabs
		$readonlys['tabs']['cats'] = !($content['cat_tab'] = $this->config['cat_tab']);
		$readonlys['tabs']['custom'] = !$this->customfields;
		$readonlys['tabs']['custom_private'] = !$this->customfields || !$this->config['private_cf_tab'];
		$readonlys['tabs']['distribution_list'] = !$content['distrib_lists'];#false;
		$readonlys['tabs']['history'] = $this->contact_repository != 'sql' || !$content['id'] ||
			$this->account_repository != 'sql' && $content['account_id'];
		if ($this->config['private_cf_tab']) $content['no_private_cfs'] = 0;

		// last and next calendar date
		if (!empty($content['id'])) list(,$dates) = each($this->read_calendar(array($content['id']),false));
		if(is_array($dates)) $content += $dates;

		// Disable importexport
		$GLOBALS['egw_info']['flags']['disable_importexport']['export'] = true;
		$GLOBALS['egw_info']['flags']['disable_importexport']['merge'] = true;

		// set id for automatic linking via quick add
		$GLOBALS['egw_info']['flags']['currentid'] = $content['id'];

		// load app.css for addressbook explicit, as addressbook_view hooks changes currentapp!
		egw_framework::includeCSS('addressbook', 'app');

		// dont show an app-header
		$GLOBALS['egw_info']['flags']['app_header'] = '';

		$actions = array(
			'open' => array(
				'caption' => 'Open',
				'toolbarDefault' => true,
			),
			'copy' => 'Copy',
			'delete' => array(
				'caption' => 'Delete',
				'confirm' => 'Delete this entry',
			),
			'cancel' => array(
				'caption' => 'Cancel',
				'toolbarDefault' => true,
			),
			'back' => array(
				'caption' => 'Back',
				'toolbarDefault' => true,
			),
			'next' => array(
				'caption' => 'Next',
				'toolbarDefault' => true,
			),
		);
		if (!isset($content['index']) || !$content['index'])
		{
			unset($actions['back']);
		}
		if (!isset($content['index']) || $content['index'] >= $num_rows-1)
		{
			unset($actions['next']);
		}
		$this->tmpl->setElementAttribute('toolbar', 'actions', $actions);

		// always show sidebox, as it contains contact-data
		unset($GLOBALS['egw_info']['user']['preferences']['common']['auto_hide_sidebox']);

		// need to load list's app.js now, as exec calls header before other app can include it
		egw_framework::validate_file('/'.$crm_list.'/js/app.js');

		$this->tmpl->exec('addressbook.addressbook_ui.view',$content,$sel_options,$readonlys,array(
			'id' => $content['id'],
			'index' => $content['index'],
			'crm_list' => $crm_list
		));

		// Only load this on first time - we're using AJAX, so it stays there through submits.
		// Sending it again (via ajax) will break the addressbook.view etemplate2
		if($contact_id)
		{
			$GLOBALS['egw']->hooks->single(array(
				'location' => 'addressbook_view',
				'ab_id'    => $content['id']
			),$crm_list);
		}
	}

	/**
	 * convert email-address in compose link
	 *
	 * @param string $email email-addresse
	 * @return array/string array with get-params or mailto:$email, or '' or no mail addresse
	 */
	function email2link($email)
	{
		if (strpos($email,'@') == false) return '';

		if($GLOBALS['egw_info']['user']['apps']['mail'])
		{
			return array(
				'menuaction' => 'mail.mail_compose.compose',
				'send_to'    => base64_encode($email)
			);
		}
		if($GLOBALS['egw_info']['user']['apps']['felamimail'])
		{
			return array(
				'menuaction' => 'felamimail.uicompose.compose',
				'send_to'    => base64_encode($email)
			);
		}
		if($GLOBALS['egw_info']['user']['apps']['email'])
		{
			return array(
				'menuaction' => 'email.uicompose.compose',
				'to' => $email,
			);
		}
		return 'mailto:' . $email;
	}

	/**
	 * Extended search
	 *
	 * @param array $_content
	 * @return string
	 */
	function search($_content=array())
	{
		if(!empty($_content))
		{

			$response = egw_json_response::get();

			$query = egw_session::appsession('index','addressbook');

			if ($_content['button']['cancelsearch'])
			{
				unset($query['advanced_search']);
			}
			else
			{
				$query['advanced_search'] = array_intersect_key($_content,array_flip(array_merge($this->get_contact_columns(),array('operator','meth_select'))));
				foreach ($query['advanced_search'] as $key => $value)
				{
					if(!$value) unset($query['advanced_search'][$key]);
				}
				// Skip n_fn, it causes problems in sql
				unset($query['advanced_search']['n_fn']);
			}
			$query['search'] = '';
			// store the index state in the session
			egw_session::appsession('index','addressbook',$query);

			// store the advanced search in the session to call it again
			egw_session::appsession('advanced_search','addressbook',$query['advanced_search']);

			// Update client / nextmatch with filters, or clear
			$response->call("app.addressbook.adv_search", array('advanced_search' => $_content['button']['search'] ? $query['advanced_search'] : ''));
			if ($_content['button']['cancelsearch'])
			{
				egw_framework::window_close (); //$response->addScript('this.close();');

				// No need to reload popup
				return;
			}
		}

		$GLOBALS['egw_info']['etemplate']['advanced_search'] = true;

		// initialize etemplate arrays
		$sel_options = $readonlys = array();
		$content = egw_session::appsession('advanced_search','addressbook');
		$content['n_fn'] = $this->fullname($content);

		for($i = -23; $i<=23; $i++) $tz[$i] = ($i > 0 ? '+' : '').$i;
		$sel_options['tz'] = $tz + array('' => lang('doesn\'t matter'));
		$sel_options['tid'][] = lang('all');
		//foreach($this->content_types as $type => $data) $sel_options['tid'][$type] = $data['name'];

		// configure search options
		$sel_options['owner'] = $this->get_addressbooks(EGW_ACL_READ,lang('all'));
		$sel_options['operator'] =  array(
			'AND' => 'AND',
			'OR' => 'OR',
		);
		$sel_options['meth_select'] = array(
			'%'		=> lang('contains'),
			false	=> lang('exact'),
		);
		if ($this->customfields)
		{
			foreach($this->customfields as $name => $data)
			{
				if (substr($data['type'], 0, 6) == 'select' && !($data['rows'] > 1))
				{
					if (!isset($content['#'.$name])) $content['#'.$name] = '';
					if(!isset($data['values'][''])) $sel_options['#'.$name][''] = lang('Select one');
				}
			}
		}
		// configure edit template as search dialog
		$readonlys['change_photo'] = true;
		$readonlys['fileas_type'] = true;
		$readonlys['creator'] = true;
		// this setting will enable (and show) the search and cancel buttons, setting this to true will hide the before mentioned buttons completely
		$readonlys['button'] = false;
		// disable not needed tabs
		$readonlys['tabs']['cats'] = !($content['cat_tab'] = $this->config['cat_tab']);
		$readonlys['tabs']['custom'] = !$this->customfields;
		$readonlys['tabs']['custom_private'] = !$this->customfields || !$this->config['private_cf_tab'];
		$readonlys['tabs']['links'] = true;
		$readonlys['tabs']['distribution_list'] = true;
		$readonlys['tabs']['history'] = true;
		// setting hidebuttons for content will hide the 'normal' addressbook edit dialog buttons
		$content['hidebuttons'] = true;
		$content['no_tid'] = true;
		$content['showsearchbuttons'] = true; // enable search operation and search buttons| they're disabled by default

		if ($this->config['private_cf_tab']) $content['no_private_cfs'] = 0;

		$this->tmpl->read('addressbook.edit');
		$this->tmpl->set_cell_attribute('change_org','disabled',true);
		return $this->tmpl->exec('addressbook.addressbook_ui.search',$content,$sel_options,$readonlys,array(),2);
	}

	/**
	 * download photo of the given ($_GET['contact_id'] or $_GET['account_id']) contact
	 */
	function photo()
	{
		ob_start();
		$contact_id = isset($_GET['contact_id']) ? $_GET['contact_id'] :
			(isset($_GET['account_id']) ? 'account:'.$_GET['account_id'] : 0);

		if (substr($contact_id,0,8) == 'account:')
		{
			$contact_id = $GLOBALS['egw']->accounts->id2name(substr($contact_id,8),'person_id');
		}
		if (!($contact = $this->read($contact_id)) || !$contact['jpegphoto'])
		{
			egw::redirect(common::image('addressbook','photo'));
		}
		// use an etag over the image mapp
		$etag = '"'.$contact['id'].':'.$contact['etag'].'"';
		if (!ob_get_contents())
		{
			header('Content-type: image/jpeg');
			header('ETag: '.$etag);
			// if etag parameter given in url, we can allow browser to cache picture via an Expires header
			// different url with different etag parameter will force a reload
			if (isset($_GET['etag']))
			{
				egw_session::cache_control(30*86400);	// cache for 30 days
			}
			// if servers send a If-None-Match header, response with 304 Not Modified, if etag matches
			if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag)
			{
				header("HTTP/1.1 304 Not Modified");
			}
			else
			{
				header('Content-length: '.bytes($contact['jpegphoto']));
				echo $contact['jpegphoto'];
			}
			common::egw_exit();
		}
	}

	/**
	 * Migrate contacts to or from LDAP (called by Admin >> Addressbook >> Site configuration (Admin only)
	 *
	 */
	function migrate2ldap()
	{
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Addressbook').' - '.lang('Migration to LDAP');
		common::egw_header();
		parse_navbar();

		if (!$this->is_admin())
		{
			echo '<h1>'.lang('Permission denied !!!')."</h1>\n";
		}
		else
		{
			parent::migrate2ldap($_GET['type']);
			echo '<p style="margin-top: 20px;"><b>'.lang('Migration finished')."</b></p>\n";
		}
		common::egw_footer();
	}

	/**
	 * Set n_fileas (and n_fn) in contacts of all users  (called by Admin >> Addressbook >> Site configuration (Admin only)
	 *
	 * If $_GET[all] all fileas fields will be set, if !$_GET[all] only empty ones
	 *
	 */
	function admin_set_fileas()
	{
		translation::add_app('admin');
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Addressbook').' - '.lang('Contact maintenance');
		common::egw_header();
		parse_navbar();

		// check if user has admin rights AND if a valid fileas type is given (Security)
		if (!$this->is_admin() || $_GET['type'] != '' && !in_array($_GET['type'],$this->fileas_types))
		{
			echo '<h1>'.lang('Permission denied !!!')."</h1>\n";
		}
		else
		{
			$updated = parent::set_all_fileas($_GET['type'],(boolean)$_GET['all'],$errors,true);	// true = ignore acl
			echo '<p style="margin-top: 20px;"><b>'.lang('%1 contacts updated (%2 errors).',$updated,$errors)."</b></p>\n";
		}
		common::egw_footer();
	}

	/**
	 * Cleanup all contacts of all users (called by Admin >> Addressbook >> Site configuration (Admin only)
	 *
	 */
	function admin_set_all_cleanup()
	{
		translation::add_app('admin');
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Addressbook').' - '.lang('Contact maintenance');
		common::egw_header();
		parse_navbar();

		// check if user has admin rights (Security)
		if (!$this->is_admin())
		{
			echo '<h1>'.lang('Permission denied !!!')."</h1>\n";
		}
		else
		{
			$updated = parent::set_all_cleanup($errors,true);	// true = ignore acl
			echo '<p style="margin-top: 20px;"><b>'.lang('%1 contacts updated (%2 errors).',$updated,$errors)."</b></p>\n";
		}
		common::egw_footer();
	}

	/**
	* Set up history log widget
	*/
	protected function setup_history(&$content, &$sel_options)
	{
		if ($this->contact_repository == 'ldap' || !$content['id'] ||
			$this->account_repository == 'ldap' && $content['account_id'])
		{
			return;	// no history for ldap as history table only allows integer id's
		}
		$content['history'] = array(
			'id'	=>	$content['id'],
			'app'	=>	'addressbook',
			'status-widgets'	=>	array(
				'owner'		=>	'select-account',
				'creator'	=>	'select-account',
				'created'	=>	'date-time',
				'cat_id'	=>	'select-cat',
				'adr_one_countrycode' => 'select-country',
				'adr_two_countrycode' => 'select-country',
			),
		);

		foreach($this->content_types as $id => $settings)
		{
			$content['history']['status-widgets']['tid'][$id] = $settings['name'];
		}
		$sel_options['status'] = $this->contact_fields;

		// custom fields no longer need to be added, historylog-widget "knows" about them
	}
}
