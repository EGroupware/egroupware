<?php
/**
 * EGroupware - Addressbook - user interface
 *
 * @link www.egroupware.org
 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2005-19 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2005/6 by Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use EGroupware\Api\Link;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;
use EGroupware\Api\Acl;
use EGroupware\Api\Vfs;
use EGroupware\Api\Etemplate;
use EGroupware\Kanban\Hooks;

/**
 * General user interface object of the adressbook
 */
class addressbook_ui extends addressbook_bo
{
	public $public_functions = array(
		'extSearch'	=> True,
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
	 * @var Etemplate
	 */
	protected $tmpl;

	/**
	 * @var array
	 */
	public $grouped_views;

	/**
	 * Constructor
	 *
	 * @param string $contact_app
	 */
	function __construct($contact_app='addressbook')
	{
		parent::__construct($contact_app);

		$this->tmpl = new Etemplate();

		$this->grouped_views = array(
			'org_name'                  => lang('Organisations'),
			'org_name,adr_one_locality' => lang('Organisations by location'),
			'org_name,org_unit'         => lang('Organisations by departments'),
			'duplicates'                => lang('Duplicates'),
			'shared_by_me'              => lang('Shared by me'),
		);

		// make sure the hook for export_limit is registered
		if (!Api\Hooks::exists('export_limit','addressbook')) Api\Hooks::read(true);

		$this->config =& $GLOBALS['egw_info']['server'];

		// check if a contact specific export limit is set, if yes use it also for etemplate's csv export
		$this->config['export_limit'] = $this->config['contact_export_limit'] = Api\Storage\Merge::getExportLimit($app='addressbook');

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
	 * @param array $_content =null submitted content
	 * @param string $msg =null	message to show
	 */
	function index($_content=null,$msg=null)
	{
		//echo "<p>uicontacts::index(".print_r($_content,true).",'$msg')</p>\n";
		if (($re_submit = is_array($_content)))
		{

			if (isset($_content['nm']['rows']['delete']))	// handle a single delete like delete with the checkboxes
			{
				$id = @key($_content['nm']['rows']['delete']);
				$_content['nm']['action'] = 'delete';
				$_content['nm']['selected'] = array($id);
			}
			if (isset($_content['nm']['rows']['document']))	// handle insert in default document button like an action
			{
				$id = @key($_content['nm']['rows']['document']);
				$_content['nm']['action'] = 'document';
				$_content['nm']['selected'] = array($id);
			}
			if ($_content['nm']['action'] !== '' && $_content['nm']['action'] !== null)
			{
				if (!count($_content['nm']['selected']) && !$_content['nm']['select_all'] && $_content['nm']['action'] != 'delete_list')
				{
					$msg = lang('You need to select some contacts first');
				}
				elseif ($_content['nm']['action'] == 'view_org' || $_content['nm']['action'] == 'view_duplicates')
				{
					// grouped view via context menu
					$_content['nm']['grouped_view'] = array_shift($_content['nm']['selected']);
				}
				else
				{
					$success = $failed = $action_msg = null;
					if ($this->action($_content['nm']['action'],$_content['nm']['selected'],$_content['nm']['select_all'],
						$success,$failed,$action_msg,'index',$msg,$_content['nm']['checkboxes'], $error_msg))
					{
						$msg .= lang('%1 contact(s) %2',$success,$action_msg);
						Framework::message($msg);
					}
					elseif(is_null($msg))
					{
						if (empty($error_msg))
						{
							$msg .= lang('%1 contact(s) %2, %3 failed because of insufficent rights !!!', $success, $action_msg, $failed);
						}
						else
						{
							$msg .= lang('%1 contact(s) %2, %3 failed because of %4 !!!', $success, $action_msg, $failed, $error_msg);
						}
						Framework::message($msg,'error');
					}
					$msg = '';
				}
			}
			if (!empty($_content['nm']['rows']['infolog']))
			{
				$org = key($_content['nm']['rows']['infolog']);
				return $this->infolog_org_view($org);
			}
			if (!empty($_content['nm']['rows']['view']))	// show all contacts of an organisation
			{
				$grouped_view = key($_content['nm']['rows']['view']);
			}
			else
			{
				$grouped_view = $_content['nm']['grouped_view'];
			}
			$typeselection = $_content['nm']['col_filter']['tid'];
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
		$preserv = array();
		$content = array();
		if($msg || $_GET['msg'])
		{
			Framework::message($msg ? $msg : $_GET['msg']);
		}

		$content['nm'] = Api\Cache::getSession('addressbook', 'index');
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
				//'cat_id_label' => lang('Categories'),
				//'filter_label' => lang('Addressbook'),	// I  label for filter    (optional)
				'filter'         =>	'',	// =All	// IO filter, if not 'no_filter' => True
				'filter_no_lang' => True,		// I  set no_lang for filter (=dont translate the options)
				'no_filter2'     => True,		// I  disable the 2. filter (params are the same as for filter)
				//'filter2_label'=>	lang('Distribution lists'),			// IO filter2, if not 'no_filter2' => True
				'filter2'        =>	'',			// IO filter2, if not 'no_filter2' => True
				'filter2_no_lang'=> True,		// I  set no_lang for filter2 (=dont translate the options)
				'lettersearch'   => true,
				// using a positiv list now, as we constantly adding new columns in addressbook, but not removing them from default
				'default_cols'   => 'type,n_fileas_n_given_n_family_n_family_n_given_org_name_n_family_n_given_n_fileas,'.
					'number,org_name,org_unit,'.
					'business_adr_one_countrycode_adr_one_postalcode,tel_work_tel_cell_tel_home,url_email_email_home',
				/* old negative list
				'default_cols'   => '!cat_id,contact_created_contact_modified,distribution_list,contact_id,owner,room',*/
				'filter2_onchange' => "return app.addressbook.filter2_onchange();",
				'filter2_tags'	=> true,
				//'actions'        => $this->get_actions(),		// set on each request, as it depends on some filters
				'row_id'         => 'id',
				'row_modified'   => 'modified',
				'add_on_top_sort_field' => 'modified',
				'is_parent'      => 'group_count',
				'parent_id'      => 'parent_id',
				'favorites'      => true,
			);

			// use the state of the last session stored in the user prefs
			if (($state = @unserialize($this->prefs['index_state'])))
			{
				$content['nm'] = array_merge($content['nm'],$state);
			}
		}
		$sel_options['cat_id'] = array('' => lang('All categories'), '0' => lang('None'));

		$content['nm']['placeholder_actions'] = array('add');
		// Edit and delete list actions depends on permissions
		if($this->get_lists(Acl::EDIT))
		{
			$content['nm']['placeholder_actions'][] = 'rename_list';
			$content['nm']['placeholder_actions'][] = 'delete_list';
		}

		// Search parameter passed in
		if ($_GET['search']) {
			$content['nm']['search'] = $_GET['search'];
		}
		if (isset($typeselection)) $content['nm']['col_filter']['tid'] = $typeselection;
		// save the tid for use in creating new addressbook entrys via UI. Current tid is to be used as type of new entrys
		//error_log(__METHOD__.__LINE__.' '.$content['nm']['col_filter']['tid']);
		Api\Cache::setSession('addressbook','active_tid',$content['nm']['col_filter']['tid']);
		if ($this->lists_available())
		{
			$sel_options['filter2'] = $this->get_lists(Acl::READ,array('' => lang('No distribution list')));
			$sel_options['filter2']['add'] = lang('Add a new list').'...';	// put it at the end
		}

		// Organisation stuff is not (yet) availible with ldap
		if($GLOBALS['egw_info']['server']['contact_repository'] != 'ldap' && Api\Header\UserAgent::mobile() == '')
		{
			$content['nm']['header_left'] = 'addressbook.index.left';
		}
		$sel_options['filter'] = $sel_options['owner'] = $this->get_addressbooks(Acl::READ, lang('All addressbooks'));
		$sel_options['to'] = array(
			'to'  => 'To',
			'cc'  => 'Cc',
			'bcc' => 'Bcc',
		);

		// if there is any export limit set, pass it on to the nextmatch, to be evaluated by the export
		if (isset($this->config['contact_export_limit']) && (int)$this->config['contact_export_limit']) $content['nm']['export_limit']=$this->config['contact_export_limit'];

		// Merge to email dialog needs the infolog types
		$infolog = new infolog_bo();
		$sel_options['info_type'] = $infolog->enums['type'];

		// dont show tid-selection if we have only one content_type
		// be a bit more sophisticated about it
		$availabletypes = array_keys($this->content_types);
		if ($content['nm']['col_filter']['tid'] && !in_array($content['nm']['col_filter']['tid'],$availabletypes))
		{
			//_debug_array(array('Typefilter:'=> $content['nm']['col_filter']['tid'],'Available Types:'=>$availabletypes,'action:'=>'remove invalid filter'));
			unset($content['nm']['col_filter']['tid']);
		}
		if (!isset($content['nm']['col_filter']['tid'])) $content['nm']['col_filter']['tid'] = $availabletypes[0];
		if (count($this->content_types) > 1)
		{
			foreach($this->content_types as $tid => $data)
			{
				$sel_options['tid'][$tid] = $data['name'];
			}
		}
		else
		{
			$this->tmpl->disableElement('nm[col_filter][tid]');
		}
		// get the availible grouped-views plus the label of the contacts view of one group
		$sel_options['grouped_view'] = $this->grouped_views;
		if (isset($grouped_view))
		{
			$content['nm']['grouped_view'] = $grouped_view;
		}

		$content['nm']['actions'] = $this->get_actions($content['nm']['col_filter']['tid']);

		if (!isset($sel_options['grouped_view'][(string) $content['nm']['grouped_view']]))
		{
			$sel_options['grouped_view'] += $this->_get_grouped_name((string)$content['nm']['grouped_view']);
		}
		// unset the filters regarding grouped views, when there is no group selected
		if (empty($sel_options['grouped_view'][(string) $content['nm']['grouped_view']]) || stripos($grouped_view,":") === false )
		{
			$this->unset_grouped_filters($content['nm']);
		}
		$content['nm']['grouped_view_label'] = $sel_options['grouped_view'][(string) $content['nm']['grouped_view']];

		// allow to also filter by (not) shared contacts
		$sel_options['shared_with'] = [
			'not' => lang('not shared'),
			'shared' => lang('shared'),
		];

		$this->tmpl->read('addressbook.index');
		return $this->tmpl->exec('addressbook.addressbook_ui.index',
			$content,$sel_options,array(),$preserv);
	}

	/**
	 * Get actions / context menu items
	 *
	 * @param ?string $tid_filter =null
	 * @return array see Etemplate\Widget\Nextmatch::get_actions()
	 */
	public function get_actions($tid_filter=null)
	{
		// Contact view
		$actions = array(
			'view' => array(
				'caption' => 'CRM-View',
				'default' => $GLOBALS['egw_info']['user']['preferences']['addressbook']['crm_list'] != '~edit~',
				'allowOnMultiple' => false,
				'group' => $group=1,
				'onExecute' => 'javaScript:app.addressbook.view',
				'enableClass' => 'contact_contact',
				'hideOnDisabled' => true,
				// Children added below
				'children' => array(),
				'hideOnMobile' => true
			),
			'open' => array(
				'caption' => 'Open',
				'default' => $GLOBALS['egw_info']['user']['preferences']['addressbook']['crm_list'] == '~edit~',
				'allowOnMultiple' => false,
				'enableClass' => 'contact_contact',
				'hideOnDisabled' => true,
				'url' => 'menuaction=addressbook.addressbook_ui.edit&contact_id=$id',
				'popup' => Link::get_registry('addressbook', 'edit_popup'),
				'group' => $group,
			),
			'add' => array(
				'caption' => 'Add',
				'group' => $group,
				'hideOnDisabled' => true,
				'children' => array(
					'new' => array(
						'caption' => 'New',
						'url' => 'menuaction=addressbook.addressbook_ui.edit',
						'popup' => Link::get_registry('addressbook', 'add_popup'),
						'icon' => 'new',
					),
					'copy' => array(
						'caption' => 'Copy',
						'url' => 'menuaction=addressbook.addressbook_ui.edit&makecp=1&contact_id=$id',
						'popup' => Link::get_registry('addressbook', 'add_popup'),
						'enableClass' => 'contact_contact',
						'allowOnMultiple' => false,
						'icon' => 'copy',
					),
				),
				'hideOnMobile' => true
			),
		);
		// CRM view options
		$crm_apps = array('infolog','tracker');
		foreach($crm_apps as $crm_index => $app)
		{
			if (!$GLOBALS['egw_info']['user']['apps'][$app])
			{
				unset($crm_apps[$crm_index]);
			}
		}
		if($GLOBALS['egw_info']['user']['apps']['infolog'])
		{
			array_splice($crm_apps, 1, 0, 'infolog-organisation');
		}
		if(count($crm_apps) > 1)
		{
			foreach($crm_apps as $app)
			{
				$actions['view']['children']["view-$app"] = array(
					'caption' => $app,
					'icon' => $app == 'infolog-organisation' ? "infolog/navbar" : "$app/navbar"
				);
			}
		}

		// org view
		$actions += array(
			'view_org' => array(
				'caption' => 'View',
				'default' => true,
				'allowOnMultiple' => false,
				'group' => $group=1,
				'enableClass' => 'contact_organisation',
				'hideOnDisabled' => true
			),
			'add_org' => array(
				'caption' => 'Add',
				'group' => $group,
				'allowOnMultiple' => false,
				'enableClass' => 'contact_organisation',
				'hideOnDisabled' => true,
				'url' => 'menuaction=addressbook.addressbook_ui.edit&org=$id',
				'popup' => Link::get_registry('addressbook', 'add_popup'),
			),
		);

		// Duplicates view
		$actions += array(
			'view_duplicates' => array(
				'caption' => 'View',
				'default' => true,
				'allowOnMultiple' => false,
				'group' => $group=1,
				'enableClass' => 'contact_duplicate',
				'hideOnDisabled' => true
			)
		);

		++$group;	// other AB related stuff group: lists, AB's, categories
		// categories submenu
		$actions['cat'] = array(
			'caption' => 'Categories',
			'group' => $group,
			'children' => array(
				'cat_add' => Etemplate\Widget\Nextmatch::category_action(
					'addressbook',$group,'Add category', 'cat_add_',
					true, 0,Etemplate\Widget\Nextmatch::DEFAULT_MAX_MENU_LENGTH,false
				)+array(
					'icon' => 'foldertree_nolines_plus',
					'disableClass' => 'rowNoEdit',
				),
				'cat_del' => Etemplate\Widget\Nextmatch::category_action(
					'addressbook',$group,'Delete category', 'cat_del_',
					true, 0,Etemplate\Widget\Nextmatch::DEFAULT_MAX_MENU_LENGTH,false
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
		if (($add_lists = $this->get_lists(Acl::EDIT)))	// do we have distribution lists?, and are we allowed to edit them
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
				'rename_list' => array(
					'caption' => 'Rename selected distribution list',
					'icon' => 'edit',
					'enabled' => 'javaScript:app.addressbook.nm_compare_field',
					'fieldId' => 'exec[nm][filter2]',
					'fieldValue' => '!',	// enable if list != ''
					'onExecute' => 'javaScript:app.addressbook.rename_list'
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
				$actions['lists']['children']['rename_list']['fieldId'] = 'filter2';
				$actions['lists']['children']['delete_list']['fieldId'] = 'filter2';
			}
		}
		// move to AB
		if (($move2addressbooks = $this->get_addressbooks(Acl::ADD)))	// do we have addressbooks, we should
		{
			unset($move2addressbooks[0]);	// do not offer action to move contact to an account, as we dont support that currrently
			foreach($move2addressbooks as $owner => $label)
			{
				$icon = $type_label = null;
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
				'hideOnMobile' => true
			);
		}
		if (($share2addressbooks = $this->get_addressbooks(Acl::EDIT|self::ACL_SHARED, null, null, false)))
		{
			unset($share2addressbooks[0]);    // do not offer action to share contact into accounts AB
			foreach ($share2addressbooks as $owner => $label)
			{
				if (substr($owner, -1) === 'p')	// share with private AB makes no sense
				{
					unset($share2addressbooks[$owner]);
					continue;
				}
				$icon = $type_label = null;
				$this->type_icon((int)$owner, substr($owner, -1) == 'p', 'n', $icon, $type_label);
				$share2addressbooks[$owner] = array(
					'icon' => $icon,
					'caption' => $label,
				);
			}
			$actions['shared_with'] = [
				'caption' => 'Share into addressbook',
				'children' => [
					'writable' => [
						'id' => 'writable',
						'caption' => 'Share writable',
						'checkbox' => true,
					]] + $share2addressbooks + [
					'unshare' => [
						'icon' => 'delete',
						'caption' => 'Unshare',
						'group' => $group,
						'enableClass' => 'unshare_contact',
						'hideOnDisabled' => true,
						'hideOnMobile' => true
					]
				],
				'prefix' => 'shared_with_',
				'group' => $group,
				'hideOnMobile' => true
			];
		}
		$actions['change_type'] = $this->change_type_actions($group);
		$actions['merge'] = array(
			'caption' => 'Merge contacts',
			'confirm' => 'Merge into first or account, deletes all other!',
			'hint' => 'Merge into first or account, deletes all other!',
			'allowOnMultiple' => 'only',
			'group' => $group,
			'hideOnMobile' => true,
			'enabled' => 'javaScript:app.addressbook.can_merge'
		);
		// Duplicates view
		$actions['merge_duplicates'] = array(
			'caption'	=> 'Merge duplicates',
			'group'		=> $group,
			'allowOnMultiple'	=> true,
			'enableClass' => 'contact_duplicate',
			'hideOnDisabled'	=> true
		);

		++$group;	// integration with other apps: infolog, calendar, filemanager, messenger

		// Integrate Status Videoconference actions
		if ($GLOBALS['egw_info']['user']['apps']['status'])
		{
			$actions['videoconference'] = [
				'caption' => 'Video Conference',
				'icon' => 'status/videoconference',
				'group' => $group,
				'children' => [
					'call' => [
						'caption' => lang('Video Call'),
						'icon' => 'status/videoconference_call',
						'allowOnMultiple' => true,
						'onExecute' => 'javaScript:app.addressbook.videoconference_actionCall',
						'enabled' => 'javaScript:app.addressbook.videoconference_isUserOnline'
					],
					'audiocall' => [
						'caption' => lang('Audio Call'),
						'icon' => 'accept_call',
						'allowOnMultiple' => true,
						'onExecute' => 'javaScript:app.addressbook.videoconference_actionCall',
						'enabled' => 'javaScript:app.addressbook.videoconference_isUserOnline'
					],
					'invite' => [
						'caption' => lang('Invite to current call'),
						'icon' => 'status/videoconference_join',
						'allowOnMultiple' => true,
						'onExecute' => 'javaScript:app.addressbook.videoconference_actionCall',
						'enabled' => 'javaScript:app.addressbook.videoconference_isThereAnyCall'
					],
					'schedule_call' => [
						'caption' => lang('Schedule a video conference'),
						'icon' => 'calendar/navbar',
						'allowOnMultiple' => true,
						'onExecute' => 'javaScript:app.addressbook.add_cal',
					]
				]
			];
		}

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
						'disableClass' => 'contact_duplicate',
						'allowOnMultiple' => true,
						'hideOnDisabled' => true,
					),
					'infolog_add' => array(
						'caption' => 'Add a new Infolog',
						'icon' => 'new',
						'url' => 'menuaction=infolog.infolog_ui.edit&type=task&action=addressbook&action_id=$id',
						'popup' => Link::get_registry('infolog', 'add_popup'),
						'onExecute' => 'javaScript:app.addressbook.add_task',	// call server for org-view only
					),
				),
				'hideOnMobile' => true
			);
		}
		if ($GLOBALS['egw_info']['user']['apps']['calendar'])
		{
			$actions['calendar'] = array(
				'icon' => 'calendar/navbar',
				'caption' => 'Calendar',
				'group' => $group,
				'enableClass' => 'contact_contact',
				'children' => array(
					'calendar_view' => array(
						'caption' => 'Show',
						'icon' => 'view',
						'onExecute' => 'javaScript:app.addressbook.view_calendar',
						'targetapp' => 'calendar',	// open in calendar tab,
						'hideOnDisabled' => true,
					),
					'calendar_add' => array(
						'caption' => 'Add appointment',
						'icon' => 'new',
						'popup' => Link::get_registry('calendar', 'add_popup'),
						'onExecute' => 'javaScript:app.addressbook.add_cal',
					),
				),
				'hideOnMobile' => true
			);
		}
		//Send to email
		$actions['email'] = array(
				'caption' => 'Email',
				'icon'	=> 'mail/navbar',
				'enableClass' => 'contact_contact',
				'hideOnDisabled' => true,
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
				// disable for for group-views, as it needs contact-ids
				'enableClass' => 'contact_contact',
				'hideOnMobile' => true
			);
		}
		if ($GLOBALS['egw_info']['user']['apps']['kanban'])
		{
			$actions['kanban'] = EGroupware\Kanban\Hooks::get_actions(['addressbook'], $group);
		}

		$actions['geolocation'] = array(
			'caption' => 'GeoLocation',
			'icon' => 'map',
			'group' => ++$group,
			'enableClass' => 'contact_contact',
			'children' => array (
				'private' => array(
					'caption' => 'Private Address',
					'enabled' => 'javaScript:app.addressbook.geoLocation_enabled',
					'onExecute' => 'javaScript:app.addressbook.geoLocationExec',

				),
				'business' => array(
					'caption' => 'Business Address',
					'enabled' => 'javaScript:app.addressbook.geoLocation_enabled',
					'onExecute' => 'javaScript:app.addressbook.geoLocationExec',

				)
			)
		);
		$actions += EGroupware\Api\Link\Sharing::get_actions('addressbook', $group);

		// check if user is an admin or the export is not generally turned off (contact_export_limit is non-numerical, eg. no)
		$exception = Api\Storage\Merge::is_export_limit_excepted();
		if ((isset($GLOBALS['egw_info']['user']['apps']['admin']) || $exception)  || !$this->config['contact_export_limit'] || (int)$this->config['contact_export_limit'])
		{
			$actions['export'] = array(
				'caption' => 'Export',
				'icon' => 'filesave',
				'enableClass' => 'contact_contact',
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
						'icon' => Vfs::mime_icon('text/vcard'),
					),
				),
				'hideOnMobile' => true
			);
		}

		$actions['documents'] = Api\Contacts\Merge::document_action(
			$this->prefs['document_dir'], $group, 'Insert in document', 'document_',
			$this->prefs['default_document'], $this->config['contact_export_limit']
		);
		if ($GLOBALS['egw_info']['user']['apps']['felamimail']||$GLOBALS['egw_info']['user']['apps']['mail'])
		{
			$actions['mail'] = array(
				'caption' => lang('Mail VCard'),
				'icon' => 'filemanager/mail_post_to',
				'group' => $group,
				'onExecute' => 'javaScript:app.addressbook.adb_mail_vcard',
				'enableClass' => 'contact_contact',
				'hideOnDisabled' => true,
				'hideOnMobile' => true,
				'disableIfNoEPL' => true
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
				'onExecute' => 'javaScript:app.addressbook.action',
			);
		}
		if ($this->grants[0] & Acl::DELETE)
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
		if (isset($actions['export']['children']['csv']) &&
			(!isset($GLOBALS['egw_info']['user']['apps']['importexport']) ||
			!importexport_helper_functions::has_definitions('addressbook','export')))
		{
			unset($actions['export']['children']['csv']);
		}
		// Intercept open action in order to open entry into view mode instead of edit
		if (Api\Header\UserAgent::mobile())
		{
			$actions['open']['onExecute'] = 'javaScript:app.addressbook.viewEntry';
			$actions['open']['mobileViewTemplate'] = 'view?'.filemtime(Api\Etemplate\Widget\Template::rel2path('/addressbook/templates/mobile/view.xet'));
			$actions['view']['default'] = false;
			$actions['open']['default'] = true;
		}
		// Allow contacts to be dragged
		/*
		$actions['drag'] = array(
			'type' => 'drag',
			'dragType' => 'addressbook'
		);
		*/
		return $actions;
	}

	protected function change_type_actions($group)
	{

		$types = array();
		foreach($this->content_types as $key => $type)
		{
			// Skip deleted
			if($key == self::DELETED_TYPE) continue;

			$types[$key] = array(
					'caption' => $type['name'],
			);
		}

		return array(
			'caption' => 'Type',
			'children' => $types,
			'prefix' => 'to_type_',
			'group' => $group,
			'disableClass' => 'rowNoEdit',
			'hideOnDisabled' => true,
			'disabled' => (count($types) <= 1),
			'hideOnMobile' => true
		);
	}

	/**
	 * Get a nice name for the given grouped view ID
	 *
	 * @param String $view_id Some kind of indicator for a specific group, either
	 *	organisation or duplicate.  It looks like key:value pairs seperated by |||.
	 *
	 * @return Array(ID => name), where ID is the $view_id passed in
	 */
	protected function _get_grouped_name($view_id)
	{
		$group_name = array();
		if (strpos($view_id,'*AND*')!== false) $view_id = str_replace('*AND*','&',$view_id);
		foreach(explode('|||',$view_id) as $part)
		{
			list(,$name) = explode(':',$part,2);
			if ($name) $group_name[] = $name;
		}
		$name = implode(': ',$group_name);
		return $name ? array($view_id => $name) : array();
	}

	/**
	 * Unset the relevant column filters to clear a grouped view
	 *
	 * @param Array $query
	 */
	protected function unset_grouped_filters(&$query)
	{
		unset($query['col_filter']['org_name']);
		unset($query['col_filter']['org_unit']);
		unset($query['col_filter']['adr_one_locality']);
		foreach(array_keys(static::$duplicate_fields) as $field)
		{
			unset($query['col_filter'][$field]);
		}
	}

	/**
	 * Adjust the query as needed and get the rows for the grouped views (organisation
	 * or duplicate contacts)
	 *
	 * @param Array $query Nextmatch query
	 * @return array rows found
	 */
	protected function get_grouped_rows(&$query)
	{
		// Query doesn't like empties
		unset($query['col_filter']['parent_id']);

		if($query['actions'] && $query['actions']['open'])
		{
			// Just switched from contact view, update actions
			$query['actions'] = $this->get_actions($query['col_filter']['tid']);
		}

		$template = $query['grouped_view'] == 'duplicates' ? 'addressbook.index.duplicate_rows' : 'addressbook.index.org_rows';

		if ($query['advanced_search'])
		{
			$query['op'] = $query['advanced_search']['operator'];
			unset($query['advanced_search']['operator']);
			$query['wildcard'] = $query['advanced_search']['meth_select'];
			unset($query['advanced_search']['meth_select']);
			$original_search = $query['search'];
			$query['search'] = $query['advanced_search'];
		}

		switch ($template)
		{
			case 'addressbook.index.org_rows':
				if ($query['order'] != 'org_name')
				{
					$query['sort'] = 'ASC';
					$query['order'] = 'org_name';
				}
				$query['org_view'] = $query['grouped_view'];
				// switch the distribution list selection off for ldap
				if($this->contact_repository != 'sql')
				{
					$query['no_filter2'] = true;
					unset($query['col_filter']['list']);	// does not work here
				}
				else
				{
					$rows = parent::organisations($query);
				}
				break;
			case 'addressbook.index.duplicate_rows':
				$query['no_filter2'] = true;			// switch the distribution list selection off
				unset($query['col_filter']['list']);	// does not work for duplicates
				$rows = parent::duplicates($query);
				break;
		}

		if ($query['advanced_search'])
		{
			$query['search'] = $original_search;
			unset($query['wildcard']);
			unset($query['op']);
		}
		$GLOBALS['egw_info']['flags']['params']['manual'] = array('page' => 'ManualAddressbookIndexOrga');

		return $rows;
	}

	/**
	 * Return the contacts in an organisation via AJAX
	 *
	 * @param string|string[] $org Organisation ID
	 * @param mixed $_query Query filters (category, etc) to use, or null to use session
	 * @return array
	 */
	public function ajax_organisation_contacts($org, $_query = null)
	{
		$org_contacts = array();
		$query = !$_query ? Api\Cache::getSession('addressbook', 'index') : $_query;
		$query['num_rows'] = -1;	// all
		if(!is_array($query['col_filter'])) $query['col_filter'] = array();

		if(!is_array($org)) $org = array($org);
		foreach($org as $org_name)
		{
			$query['grouped_view'] = $org_name;
			$checked = array();
			$readonlys = null;
			$this->get_rows($query,$checked,$readonlys,true);	// true = only return the id's
			if($checked[0])
			{
				$org_contacts = array_merge($org_contacts,$checked);
			}
		}
		Api\Json\Response::get()->data(array_unique($org_contacts));
	}

	/**
	 * Show the infologs of an whole organisation
	 *
	 * @param string $org
	 */
	function infolog_org_view($org)
	{
		$query = Api\Cache::getSession('addressbook', 'index');
		$query['num_rows'] = -1;	// all
		$query['grouped_view'] = $org;
		$query['searchletter'] = '';
		$checked = $readonlys = null;
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
		Egw::redirect_link('/index.php',array(
			'menuaction' => 'infolog.infolog_ui.index',
			'action' => 'addressbook',
			'action_id' => implode(',',$checked),
			'action_title' => $org,
		),'infolog');
	}

	/**
	 * Create or rename an existing email list
	 *
	 * @param int $list_id ID of existing list, or 0 for a new one
	 * @param string $new_name List name
	 * @param int $_owner List owner, or empty for current user
	 * @param string[] [$contacts] List of contacts to add to the array
	 * @return boolean|string
	 */
	function ajax_set_list($list_id, $new_name, $_owner = false, $contacts = array())
	{
		// Set owner to current user, if not set
		$owner = $_owner ? $_owner : $GLOBALS['egw_info']['user']['account_id'];
		// if admin forced or set default for add_default pref
		// consider default_addressbook as owner which already
		// covered all cases in contacts class.
		if ($owner == (int)$GLOBALS['egw']->preferences->default['addressbook']['add_default'] ||
				$owner == (int)$GLOBALS['egw']->preferences->forced['addressbook']['add_default'])
		{
			$owner = $this->default_addressbook;
		}
		// Check for valid list & permissions
		if(!(int)$list_id && !$this->check_list(null,EGW_ACL_ADD|EGW_ACL_EDIT,$owner))
		{
			Api\Json\Response::get()->apply('egw.message', array(  lang('List creation failed, no rights!'),'error'));
			return;
		}
		if ((int)$list_id && !$this->check_list((int)$list_id, Acl::EDIT, $owner))
		{
			Api\Json\Response::get()->apply('egw.message', array(  lang('Insufficent rights to edit this list!'),'error'));
			return;
		}

		$list = array('list_owner' => $owner);

		// Rename
		if($list_id)
		{
			$list = $this->read_list((int)$list_id);
		}
		$list['list_name'] = $new_name;

		$new_id = $this->add_list(array('list_id' => (int)$list_id), $list['list_owner'],array(),$list);

		if($contacts)
		{
			$this->add2list($contacts,$new_id);
		}
		Api\Json\Response::get()->apply('egw.message', array(
			$new_id == $list_id ? lang('Distribution list renamed') : lang('List created'),
			'success'
		));
		// Success, just update selectbox to new value
		Api\Json\Response::get()->data($new_id == $list_id ? "true" : $new_id);
	}

	/**
	 * Ajax function to get contact data out of provided account_id
	 *
	 * @param string $account_id
	 */
	function ajax_get_contact ($account_id)
	{
		$bo = new Api\Contacts();
		$contact = $bo->read('account:'.$account_id);
		Api\Json\Response::get()->data($contact);
	}

	/**
	 * Disable / clear advanced search
	 *
	 * Advanced search is stored server side in session no matter what the nextmatch
	 * sends, so we have to clear it here.
	 */
	public static function ajax_clear_advanced_search()
	{
		$query = Api\Cache::getSession('addressbook', 'index');
		unset($query['advanced_search']);
		Api\Cache::setSession('addressbook','index',$query);
		Api\Cache::setSession('addressbook', 'advanced_search', false);
	}

	/**
	 * Apply an action to multiple events, but called via AJAX instead of submit
	 *
	 * @param string $action
	 * @param string[] $selected
	 * @param bool $all_selected All entries are selected, not just what's in $selected
	 * @param bool $skip_notification
	 */
	public function ajax_action($action, $selected, $all_selected, $skip_notification = false)
	{
		$success = 0;
		$failed = 0;
		$action_msg = '';
		$session_name = 'index';

		if($this->action($action, $selected, $all_selected, $success, $failed, $action_msg, $session_name, $msg, $skip_notification))
		{
			$msg = lang('%1 event(s) %2',$success,$action_msg);
		}
		elseif(is_null($msg))
		{
			$msg .= lang('%1 event(s) %2, %3 failed because of insufficient rights !!!',$success,$action_msg,$failed);
		}
		Api\Json\Response::get()->message($msg);
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
	 * @param string/array $session_name 'index' or array with session-data depending if we are in the main list or the popup
	 * @param ?string& $error_msg on return optional error-message
	 * @return boolean true if all actions succeded, false otherwise
	 */
	function action($action, $checked, $use_all, &$success, &$failed, &$action_msg, $session_name, &$msg, $checkboxes = NULL, &$error_msg=null)
	{
		//echo "<p>uicontacts::action('$action',".print_r($checked,true).','.(int)$use_all.",...)</p>\n";
		$success = $failed = 0;
		$error_msg = null;
		if ($use_all || in_array($action,array('remove_from_list','delete_list','unshare')))
		{
			// get the whole selection
			$query = is_array($session_name) ? $session_name : Api\Cache::getSession('addressbook', $session_name);

			if ($use_all)
			{
				@set_time_limit(0);			// switch off the execution time limit, as it's for big selections to small
				$query['num_rows'] = -1;	// all
				$readonlys = null;
				$this->get_rows($query,$checked,$readonlys,true);	// true = only return the id's
			}
		}
		// replace org_name:* id's with all id's of that org
		$grouped_contacts = $this->find_grouped_ids($action, $checked, $use_all, $success,$failed,$action_msg,$session_name, $msg);
		if ($grouped_contacts) $checked = array_unique($checked ? array_merge($checked,$grouped_contacts) : $grouped_contacts);
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
		elseif (substr($action, 0, 8) == 'to_type_')
		{
			$to_type = substr($action,8);
			$action = 'to_type';
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
		elseif(substr($action, 0, 12) === 'shared_with_')
		{
			$shared_with = substr($action, 12);
			$action = 'shared_with';
		}
		// Security: stop non-admins to export more then the configured number of contacts
		if (in_array($action,array('csv','vcard')) && $this->config['contact_export_limit'] && !Api\Storage\Merge::is_export_limit_excepted() &&
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
				$error_msg = null;
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
					Api\Cache::setSession('addressbook', $session_name, $query);
				}
				return false;

			case 'document':
				if (!$document) $document = $this->prefs['default_document'];
				$document_merge = new Api\Contacts\Merge();
				$msg = $document_merge->download($document, $checked, '', $this->prefs['document_dir']);
				$failed = count($checked);
				return false;

			case 'infolog_add':
				Framework::popup(Egw::link('/index.php',array(
						'menuaction' => 'infolog.infolog_ui.edit',
						'type' => 'task',
						'action' => 'addressbook',
						'action_id' => implode(',',$checked),
					)),'_blank',Link::get_registry('infolog', 'add_popup'));
				$msg = '';	// no message, as we send none in javascript too and users sees opening popup
				return false;

			case 'calendar_add':	// add appointment for org-views, other views are handled directly in javascript
				Framework::popup(Egw::link('/index.php',array(
						'menuaction' => 'calendar.calendar_uiforms.edit',
						'participants' => 'c'.implode(',c',$checked),
					)),'_blank',Link::get_registry('calendar', 'add_popup'));
				$msg = '';	// no message, as we send none in javascript too and users sees opening popup
				return false;

			case 'calendar_view':	// show calendar for org-views, although all views are handled directly in javascript
				Egw::redirect_link('/index.php',array(
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
					if (($Ok = !!($contact = $this->read($id)) && $this->check_perms(Acl::EDIT,$contact)))
					{
						$action_msg = $action == 'cat_add' ? lang('categorie added') : lang('categorie delete');
						$cat_ids = $contact['cat_id'] ? explode(',', $contact['cat_id']) : array();   //existing Api\Categories
						if ($action == 'cat_add')
						{
							$cat_ids[] = $cat_id;
							$cat_ids = array_unique($cat_ids);
						}
						elseif ((($key = array_search($cat_id,$cat_ids))) !== false)
						{
							unset($cat_ids[$key]);
						}
						$ids = $cat_ids ? implode(',',$cat_ids) : null;
						if ($ids !== $contact['cat_id'])
						{
							$contact['cat_id'] = $ids;
							$Ok = $this->save($contact);
						}
					}
					break;

				case 'delete':
					$action_msg = lang('deleted');
					if (($Ok = !!($contact = $this->read($id)) && $this->check_perms(Acl::DELETE,$contact)))
					{
						if ($contact['owner'] ||	// regular contact or
							empty($contact['account_id']) ||	// accounts without account_id
							// already deleted account (should no longer happen, but needed to allow for cleanup)
							$contact['tid'] == self::DELETED_TYPE)
						{
							$Ok = $this->delete($id, $contact['tid'] != self::DELETED_TYPE && $contact['account_id']);
						}
						// delete single account --> redirect to admin
						elseif (count($checked) == 1 && $contact['account_id'])
						{
							Egw::redirect_link('/index.php',array(
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
					if (($contact = $this->read($id)))
					{
						$contact['tid'] = 'n';
						$Ok = $this->save($contact);
					}
					break;

				case 'email':
				case 'email_home':
					/* this cant work anymore, as Framework::set_onload does not longer exist
					$action_fallback = $action == 'email' ? 'email_home' : 'email';
					$action_msg = lang('added');
					if(($contact = $this->read($id)))
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
							Framework::set_onload("addEmail('".addslashes(
								$contact['n_fn'] ? $contact['n_fn'].' <'.trim($email).'>' : trim($email))."');");
							//error_log(__METHOD__.__LINE__."addEmail('".addslashes(
							//	$contact['n_fn'] ? $contact['n_fn'].' <'.trim($email).'>' : trim($email))."');");
							$Ok = true;
						}
					}*/
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
				case 'to_type':
					$action_msg = lang('changed type to %1', lang($this->content_types[$to_type]['name']));
					if (($Ok = !!($contact = $this->read($id)) && $this->check_perms(Acl::EDIT,$contact)))
					{
						if (!$contact['owner'])		// no change of accounts
						{
							$Ok = false;
						}
						else
						{
							$contact['tid'] = $to_type;
							$Ok = $this->save($contact);
						}
					}
					break;
				case 'shared_with':
					// as "unshare" is in "shared_with" submenu/children it uses "shared_with_unshare"
					if ($shared_with === 'unshare')
					{
						$action_msg = lang('unshared');
						if (($Ok = !!($contact = $this->read($id))))
						{
							$need_save = false;
							foreach($contact['shared'] as $key => $shared)
							{
								// only unshare contacts shared by current user
								if (($shared['shared_by'] == $this->user ||
									$this->check_perms(ACL::EDIT, $contact)) &&
									// only unshare from given addressbook, or all
									(empty($query['filter']) || $shared['shared_with'] == (int)$query['filter']))
								{
									$need_save = true;
									unset($contact['shared'][$key]);
								}
							}
							// we might need to ignore acl, as we are allowed to share with just read-rights
							// setting user and update-time is explicitly desired for sync(-collection)!
							$Ok = !$need_save || $this->save($contact, true);
						}
						break;
					}
					$action_msg = lang('shared into addressbook %1', Api\Accounts::username($shared_with));
					if (($Ok = !!($contact = $this->read($id))))
					{
						$new_shared_with = [[
							'shared_with' => $shared_with,
							'shared_by' => $this->user,
							'shared_at' => new Api\DateTime('now'),
							// only allow to share writable, if user has edit-rights!
							'shared_writable' => (int)($checkboxes['writable'] && $this->check_perms(Acl::EDIT, $contact)),
							'contact_id' => $id,
							'contact' => $contact,
						]];
						if ($this->check_shared_with($new_shared_with, $error_msg))	// returns [] if OK
						{
							$Ok = false;
						}
						else
						{
							$contact['shared'][] = $new_shared_with[0];
							// we might need to ignore acl, as we are allowed to share with just read-rights
							// setting user and update-time is explicitly desired for sync(-collection)!
							$Ok = $this->save($contact, true);
						}
					}
					break;
				default:	// move to an other addressbook
					if (!(int)$action || !($this->grants[(string) (int) $action] & Acl::EDIT))	// might be ADD in the future
					{
						return false;
					}
					if (!$checkboxes['move_to_copy'])
					{
						$action_msg = lang('moved');
						if (($Ok = !!($contact = $this->read($id)) && $this->check_perms(Acl::DELETE,$contact)))
						{
							if (!$contact['owner'])		// no (mass-)move of Api\Accounts
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
						if (($Ok = !!($contact = $this->read($id)) && $this->check_perms(Acl::READ,$contact)))
						{
							if ($contact['owner'] != (int)$action || $contact['private'] != (int)(substr($action,-1) == 'p'))
							{
								$this->copy_contact($contact, false);	// do NOT use self::$copy_fields, copy everything but uid etc.
								$links = $contact['link_to']['to_id'];
								$contact['owner'] = (int) $action;
								$contact['private'] = (int)(substr($action,-1) == 'p');
								$Ok = $this->save($contact);
								if ($Ok && is_array($links))
								{
									Link::link('addressbook', $contact['id'], $links);
								}
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
	 * Find the individual contact IDs for a list of grouped contacts
	 *
	 * Successful lookups are removed from the checked array.
	 *
	 * Used for action on organisation and duplicate views
	 * @param string/int $action 'delete', 'vcard', 'csv' or nummerical account_id to move contacts to that addessbook
	 * @param array $checked contact id's to use if !$use_all
	 * @param boolean $use_all if true use all contacts of the current selection in the session (NOT used!)
	 * @param int &$success number of succeded actions
	 * @param int &$failed number of failed actions (not enought permissions)
	 * @param string &$action_msg translated verb for the actions, to be used in a message like %1 contacts 'deleted'
	 * @param string/array $session_name 'index' or 'email', or array with session-data depending if we are in the main list or the popup
	 *
	 * @return array List of contact IDs in the provided groups
	 */
	protected function find_grouped_ids($action,&$checked,$use_all,&$success,&$failed,&$action_msg,$session_name,&$msg)
	{
		unset($use_all);
		$grouped_contacts = array();
		foreach((array)$checked as $n => $id)
		{
			if (substr($id,0,9) == 'org_name:' || substr($id, 0,10) == 'duplicate:')
			{
				if (count($checked) == 1 && !count($grouped_contacts) && $action == 'infolog')
				{
					return $this->infolog_org_view($id);	// uses the org-name, instead of 'selected contacts'
				}
				unset($checked[$n]);
				$query = Api\Cache::getSession('addressbook', $session_name);
				$query['num_rows'] = -1;	// all
				$query['grouped_view'] = $id;
				unset($query['filter2']);
				$extra = $readonlys = null;
				$this->get_rows($query,$extra,$readonlys,true);	// true = only return the id's

				// Merge them here, so we only merge the ones that are duplicates,
				// not merge all selected together
				if($action == 'merge_duplicates')
				{
					$loop_success = $loop_fail = 0;
					$this->action('merge', $extra, false, $loop_success, $loop_fail, $action_msg,$session_name,$msg);
					$success += $loop_success;
					$failed += $loop_fail;
				}
				if ($extra[0])
				{
					$grouped_contacts = array_merge($grouped_contacts,$extra);
				}
			}
		}
		return $grouped_contacts;
	}

	/**
	 * Copy a given contact (not storing it!)
	 *
	 * Taken care only configured fields get copied and certain fields never to copy (uid etc.).
	 *
	 * @param array& $content
	 * @param boolean $only_copy_fields =true true: only copy fields configured for copying (eg. no name),
	 *		false: copy everything, but never to copy fields
	 */
	function copy_contact(array &$content, $only_copy_fields=true)
	{
		$content['link_to']['to_id'] = 0;
		Link::link('addressbook',$content['link_to']['to_id'],'addressbook',$content['id'],
			lang('Copied by %1, from record #%2.',Api\Accounts::format_username('',
			$GLOBALS['egw_info']['user']['account_firstname'],$GLOBALS['egw_info']['user']['account_lastname']),
			$content['id']));
		// create a new contact with the content of the old
		foreach(array_keys($content) as $key)
		{
			if($only_copy_fields && !in_array($key, self::$copy_fields) || in_array($key, array('id','etag','carddav_name','uid')))
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
	}

	/**
	 * rows callback for index nextmatch
	 *
	 * @internal
	 * @param array &$query
	 * @param array &$rows returned rows/cups
	 * @param array &$readonlys eg. to disable buttons based on Acl
	 * @param boolean $id_only =false if true only return (via $rows) an array of contact-ids, dont save state to session
	 * @return int total number of contacts matching the selection
	 */
	function get_rows(&$query,&$rows,&$readonlys,$id_only=false)
	{
		$what = $query['sitemgr_display'] ? $query['sitemgr_display'] : 'index';

		if (!$id_only && !$query['csv_export'])	// do NOT store state for csv_export or querying id's (no regular view)
		{
			$store_query = $query;
			// Do not store these
			foreach(array('options-cat_id','actions','action_links','placeholder_actions') as $key)
			{
				unset($store_query[$key]);
			}
			$old_state = $store_query;
			Api\Cache::setSession('addressbook', $what, $store_query);
		}
		else
		{
			$old_state = Api\Cache::getSession('addressbook', $what);
		}
		$GLOBALS['egw']->session->commit_session();

		if ($query['grouped_view'] === 'shared_by_me')
		{
			$query['col_filter']['shared_by'] = $this->user;
			$query['grouped_view'] = '';
		}

		if (!isset($this->grouped_views[(string) $query['grouped_view']]) || strpos($query['grouped_view'],':') === false)
		{
			// we don't have a grouped view, unset the according col_filters
			$this->unset_grouped_filters($query);
		}

		if (isset($this->grouped_views[(string) $query['grouped_view']]))
		{
			// we have a grouped view, reset the advanced search
			if (empty($query['search']) && !empty($old_state['advanced_search']))
			{
				$query['advanced_search'] = $old_state['advanced_search'];
			}
		}
		// eg. paging in an advanced search
		elseif(empty($query['search']) && is_array($old_state) && array_key_exists('advanced_search', $old_state))
		{
			$query['advanced_search'] = $old_state['advanced_search'];
		}

		// Make sure old lettersearch filter doesn't stay - current letter filter will be added later
		foreach($query['col_filter'] as $key => $col_filter)
		{
			if(!is_numeric($key)) continue;
			if(preg_match('/'.$GLOBALS['egw']->db->capabilities['case_insensitive_like'].
				' '.$GLOBALS['egw']->db->quote('[a-z]%').'$/i',$col_filter) == 1
			)
			{
				unset($query['col_filter'][$key]);
			}
		}

		//echo "<p>uicontacts::get_rows(".print_r($query,true).")</p>\n";
		if (!$id_only)
		{
			// check if accounts are stored in ldap, which does NOT yet support the org-views
			if ($this->so_accounts && $query['filter'] === '0' && $query['grouped_view'])
			{
				if ($old_state['filter'] === '0')	// user changed to org_view
				{
					$query['filter'] = '';			// --> change filter to all contacts
				}
				else								// user changed to accounts
				{
					$query['grouped_view'] = '';		// --> change to regular contacts view
				}
			}
			if ($query['grouped_view'] && isset($this->grouped_views[$old_state['grouped_view']]) && !isset($this->grouped_views[$query['grouped_view']]))
			{
				$query['searchletter'] = '';		// reset lettersearch if viewing the contacts of one group (org or duplicates)
			}
			// save the state of the index in the user prefs
			$state = serialize(array(
				'filter'        => $query['filter'],
				'cat_id'        => $query['cat_id'],
				'order'         => $query['order'],
				'sort'		    => $query['sort'],
				'col_filter'    => array('tid' => $query['col_filter']['tid']),
				'grouped_view'  => $query['grouped_view'],
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
		if ($GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts'] === '1')
		{
			$query['col_filter']['account_id'] = null;
		}
		else
		{
			unset($query['col_filter']['account_id']);
		}

		// all backends allow now at least to use groups as distribution lists
		$query['no_filter2'] = false;

		// Grouped view
		if (isset($this->grouped_views[(string) $query['grouped_view']]) && !$query['col_filter']['parent_id'])
		{
			$query['grouped_view_label'] = '';
			$rows = $this->get_grouped_rows($query);
		}
		else	// contacts view
		{
			if($query['col_filter']['parent_id'])
			{
				$query['grouped_view'] = $query['col_filter']['parent_id'];
			}
			// Query doesn't like parent_id
			unset($query['col_filter']['parent_id']);
			if ($query['grouped_view'])	// view the contacts of one organisation only
			{
				if (strpos($query['grouped_view'],'*AND*') !== false) $query['grouped_view'] = str_replace('*AND*','&',$query['grouped_view']);
				if (!is_array($fields = $GLOBALS['egw_info']['user']['preferences']['addressbook']['duplicate_fields'] ?? []))
				{
					$fields = explode(',', $fields);
				}
				foreach(explode('|||',$query['grouped_view']) as $part)
				{
					list($name,$value) = explode(':',$part,2);
					// do NOT set invalid column, as this gives an SQL error ("AND AND" in sql)
					if (static::$duplicate_fields[$name] && $value && (
							strpos($query['grouped_view'], 'duplicate:') === 0 && in_array($name, $fields) ||
							strpos($query['grouped_view'], 'duplicate:') !== 0
					))
					{
						$query['col_filter'][$name] = $value;
					}
				}
			}
			else if($query['actions'] && !$query['actions']['edit'])
			{
				// Just switched from grouped view, update actions
				$query['actions'] = $this->get_actions($query['col_filter']['tid']);
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
				$wildcard = $query['advanced_search']['meth_select'] == $wildcard ? $wildcard : '';
				unset($query['advanced_search']['meth_select']);
			}

			$columsel = $this->prefs['nextmatch-addressbook.index.rows'];
			$columselection = $columsel ? explode(',',$columsel) : array();
			$extracols = [];
			if (in_array('owner_shared_with', $columselection))
			{
				$extracols[] = 'shared_with';
			}

			$rows = parent::search($query['advanced_search'] ? $query['advanced_search'] : $query['search'],$id_only,
				$order, $extracols, $wildcard,false, $op,[(int)$query['start'], (int)$query['num_rows']], $query['col_filter']);

			// do we need to read the custom fields, depends on the column is enabled and customfields
			$available_distib_lists=$this->get_lists(Acl::READ);
			$ids = $calendar_participants = array();
			if (!$id_only && $rows)
			{
				$show_custom_fields = (in_array('customfields',$columselection) || $this->config['index_load_cfs']) && $this->customfields;
				$show_calendar = $this->config['disable_event_column'] != 'True' && in_array('calendar_calendar',$columselection);
				$show_distributionlist = in_array('distribution_list', $columselection) ||
					is_array($available_distib_lists) && count($available_distib_lists);
				if ($show_calendar || $show_custom_fields || $show_distributionlist)
				{
					foreach($rows as $val)
					{
						$ids[] = $val['id'];
						$calendar_participants[$val['id']] = $val['account_id'] ? $val['account_id'] : 'c'.$val['id'];
					}
					if ($show_custom_fields)
					{
						$selected_cfs = array();
						if(in_array('customfields',$columselection))
						{
							foreach($columselection as $col)
							{
								if ($col[0] == '#') $selected_cfs[] = substr($col,1);
							}
						}
						$selected_cfs = array_unique(array_merge($selected_cfs, (array)$this->config['index_load_cfs']));
						$customfields = $this->read_customfields($ids,$selected_cfs);
					}
					if ($show_calendar && !empty($ids)) $calendar = $this->read_calendar($calendar_participants);
					// distributionlist memership for the entrys
					//_debug_array($this->get_lists(Acl::EDIT));
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

		$unshare_grants = [];
		foreach($this->grants as $grantee => $rights)
		{
			if ($rights & (ACL::EDIT|self::ACL_SHARED)) $unshare_grants[] = $grantee;
		}
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
			if (isset($this->grouped_views[(string) $query['grouped_view']]))
			{
				$row['type'] = 'home';
				$row['type_label'] = $query['grouped_view'] == 'duplicate' ? lang('Duplicates') : lang('Organisation');

				if ($query['filter'] && !($this->grants[(int)$query['filter']] & Acl::DELETE))
				{
					$row['class'] .= 'rowNoDelete ';
				}
				$row['class'] .= 'rowNoEdit ';	// no edit in OrgView
				$row['class'] .= $query['grouped_view'] == 'duplicates' ? 'contact_duplicate' : 'contact_organisation ';
			}
			else
			{
				$this->type_icon($row['owner'],$row['private'],$row['tid'],$row['type'],$row['type_label']);

				static $tel2show = array('tel_work','tel_cell','tel_home','tel_fax');
				static $prefer_marker = null;
				if (is_null($prefer_marker))
				{
					// as et2 adds options with .text(), it can't be entities, but php knows no string literals with utf-8
					$prefer_marker = html_entity_decode(' &#9734;', ENT_NOQUOTES, 'utf-8');
				}
				foreach($tel2show as $name)
				{
					$row[$name] .= ' '.($row['tel_prefer'] == $name ? $prefer_marker : '');		// .' ' to NOT remove the field
				}
				// always show the prefered phone, if not already shown
				if (!in_array($row['tel_prefer'],$tel2show) && $row[$row['tel_prefer']])
				{
					$row['tel_prefered'] = $row[$row['tel_prefer']].$prefer_marker;
				}
				// Show nice name as status text
				if($row['tel_prefer'])
				{
					$row['tel_prefer_label'] = $this->contact_fields[$row['tel_prefer']];
				}
				if (!$row['owner'] && $row['account_id'] > 0)
				{
					$row['class'] .= 'rowAccount rowNoDelete ';
				}
				elseif (!$this->check_perms(Acl::DELETE,$row) || (!$GLOBALS['egw_info']['user']['apps']['admin'] && $this->config['history'] != 'userpurge' && $query['col_filter']['tid'] == self::DELETED_TYPE))
				{
					$row['class'] .= 'rowNoDelete ';
				}
				if (!$this->check_perms(Acl::EDIT,$row))
				{
					$row['class'] .= 'rowNoEdit ';
				}
				$row['class'] .= 'contact_contact ';

				if (!self::hasPhoto($row))
				{
					$row['lname'] = $row['n_family'];
					$row['fname'] = $row['n_given'];
					unset($row['photo']);   // no need to send, as there is no photo
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
				if (isset($calendar[$calendar_participants[$row['id']]]))
				{
					foreach($calendar[$calendar_participants[$row['id']]] as $name => $data)
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
				$row['cat_id'] = $this->categories->check_list(Acl::READ,$row['cat_id']);
			}

			if ($query['col_filter']['shared_by'] == $this->user || !empty($row['shared_with']) &&
				array_intersect($unshare_grants, explode(',', $row['shared_with'])))
			{
				$row['class'] .= 'unshare_contact ';
			}
		}
		$rows['no_distribution_list'] = (bool)$query['filter2'];

		// disable customfields column, if we have no customefield(s)
		if (!$this->customfields)
		{
			$rows['no_customfields'] = true;
		}

		// Disable next/last date if so configured
		if($this->config['disable_event_column'] == 'True')
		{
			$rows['no_event_column'] = true;
		}

		// If we've changed the sort order on them, update the display
		if($order !== $query['order'] )
		{
			$rows['order'] = $order;
		}
		$rows['call_popup'] = $this->config['call_popup'];
		$rows['customfields'] = array_values($this->customfields);

		// full app-header with all search criteria specially for the print
		$header = array();
		if ($query['filter'] !== '' && !isset($this->grouped_views[$query['grouped_view']]))
		{
			$header[] = ($query['filter'] == '0' ? lang('accounts') :
				($GLOBALS['egw']->accounts->get_type($query['filter']) == 'g' ?
					lang('Group %1',$GLOBALS['egw']->accounts->id2name($query['filter'])) :
					Api\Accounts::username((int)$query['filter']).
						(substr($query['filter'],-1) == 'p' ? ' ('.lang('private').')' : '')));
		}
		if ($query['grouped_view'])
		{
			$header[] = $query['grouped_view_label'];
			// Make sure option is there
			if(!array_key_exists($query['grouped_view'], $this->grouped_views))
			{
				$this->grouped_views += $this->_get_grouped_name($query['grouped_view']);
				$rows['sel_options']['grouped_view'] = $this->grouped_views;
			}
		}
		if($query['advanced_search'])
		{
			$header[] = lang('Advanced search');
		}
		if ($query['cat_id'])
		{
			$header[] = lang('Category').' '.$GLOBALS['egw']->categories->id2name($query['cat_id']);
		}
		if ($query['searchletter'])
		{
			$order = $order == 'n_given' ? lang('first name') : ($order == 'n_family' ? lang('last name') : lang('Organisation'));
			$header[] = lang("%1 starts with '%2'",$order,$query['searchletter']);
		}
		if ($query['search'] && !$query['advanced_search']) // do not add that, if we have advanced search active
		{
			$header[] = lang("Search for '%1'",$query['search']);
		}
		$GLOBALS['egw_info']['flags']['app_header'] = implode(': ', $header);

		if ($query['grouped_view'] === '' && $query['col_filter']['shared_by'] == $this->user)
		{
			$query['grouped_view'] = 'shared_by_me';
			unset($query['col_filter']['shared_by']);
		}
		return $this->total;
	}

	/**
	 * Get addressbook type icon from owner, private and tid
	 *
	 * @param int $owner user- or group-id or 0 for Api\Accounts
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
			$label = $owner == $this->user ? lang('personal') : Api\Accounts::username($owner);
		}
		// show tid icon for tid!='n' AND only if one is defined
		if ($tid != 'n' && Api\Image::find('addressbook',$this->content_types[$tid]['name']))
		{
			$icon = Api\Image::find('addressbook',$this->content_types[$tid]['name']);
		}

		// Legacy - from when icons could be anywhere
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
			// sync $content['shared'] with $content['shared_values']
			foreach((array)$content['shared'] as $key => $shared)
			{
				$shared_value = $shared['shared_id'].':'.$shared['shared_with'].':'.$shared['shared_by'].':'.$shared['shared_writable'];
				if (($k = array_search($shared_value, (array)$content['shared_values'])) === false)
				{
					unset($content['shared'][$key]);
				}
				else
				{
					unset($content['shared_values'][$k]);
				}
			}
			foreach((array)$content['shared_values'] as $account_id)
			{
				$content['shared'][] = [
					'contact_id' => $content['id'],
					'contact' => $content,
					'shared_with' => $account_id,
					'shared_by' => $this->user,
					'shared_at' => new Api\DateTime(),
					'shared_writable' => (int)(bool)$content['shared_writable'],
				];
			}
			unset($content['shared_values']);
			// remove invalid shared-with entries (should not happen, as we validate already on client-side)
			$this->check_shared_with($content['shared']);

			$button = @key($content['button'] ?? []);
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
					if ($content['presets_fields'])
					{
						// unset the duplicate_filed after submit because we don't need to warn user for second time about contact duplication
						unset($content['presets_fields']);
					}
					// photo might be changed by ajax_upload_photo
					if (!array_key_exists('jpegphoto', $content))
					{
						$content['photo_unchanged'] = true;	// hint no need to store photo
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
						// we store region-name not code
						if (!empty($content[$c_prefix.'_region']))
						{
							$states = Api\Country::get_states($content[$c_prefix.'_countrycode']);
							if ($states && isset($states[$content[$c_prefix.'_region']]))
							{
								$content[$c_prefix.'_region'] = $states[$content[$c_prefix.'_region']];
							}
						}
						// handling custom country-name
						if (!Api\Country::get_full_name($content[$c_prefix.'_countrycode']))
						{
							$content[$c_prefix.'_countryname'] = $content[$c_prefix.'_countrycode'];
							unset($content[$c_prefix.'_countrycode']);
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

						unset($content['jpegphoto'], $content['photo_unchanged']);

						foreach((array)$content['post_save_callbacks'] as $callback)
						{
							try {
								if (($success_msg = call_user_func_array($callback, array(&$content))))
								{
									$content['msg'] .= ', '.$success_msg;
								}
							}
							catch(Api\Exception\Redirect $r)
							{
								// catch it to continue execution and rethrow it later
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
								htmlspecialchars(Egw::link('/index.php',array(
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
						Link::link('addressbook',$content['id'],$links);
					}
					// Update client side global datastore
					$response = Api\Json\Response::get();
					$response->generic('data', array('uid' => 'addressbook::'.$content['id'], 'data' => $content));
					Framework::refresh_opener($content['msg'], 'addressbook', $content['id'],  $content['id'] ? 'edit' : 'add',
						null, null, null, $this->error ? 'error' : 'success');

					// re-throw redirect exception, if there's no error
					if (!$this->error && isset($r))
					{
						throw $r;
					}
					if ($button == 'save')
					{
						Framework::window_close();
					}
					else
					{
						Framework::message($content['msg'], $this->error ? 'error' : 'success');
						unset($content['msg']);
					}
					$content['link_to']['to_id'] = $content['id'];
					break;

				case 'delete':
					$success = $failed = $action_msg = null;
					if($this->action('delete',array($content['id']),false,$success,$failed,$action_msg,'',$content['msg']))
					{
						if ($GLOBALS['egw']->currentapp == 'addressbook')
						{
							Framework::refresh_opener(lang('Contact deleted'), 'addressbook', $content['id'], 'delete' );
							Framework::window_close();
						}
						else
						{
							Framework::refresh_opener(lang('Contact deleted'), 'addressbook', $content['id'], null, 'addressbook');
							Framework::window_close();
						}
					}
					else
					{
						$content['msg'] = lang('Error deleting the contact !!!');
					}
					break;
			}
			$view = !$this->check_perms(Acl::EDIT, $content);
		}
		else
		{
			$content = array();
			$contact_id = $_GET['contact_id'] ? $_GET['contact_id'] : ((int)$_GET['account_id'] ? 'account:'.(int)$_GET['account_id'] : 0);
			$view = (boolean)$_GET['view'];
			// new contact --> set some defaults
			if ($contact_id && is_array($content = $this->read($contact_id)))
			{
				$contact_id = $content['id'];	// it could have been: "account:$account_id"
				if (!$this->check_perms(Acl::EDIT, $content))
				{
					$view = true;
				}
			}
			else // not found
			{
				$state = Api\Cache::getSession('addressbook', 'index');
				// check if we create the new contact in an existing org
				if (($org = $_GET['org']))
				{
					// arguments containing a comma get quoted by etemplate/js/nextmatch_action.js
					// leading to error in Api\Db::column_data_implode, if not unquoted
					if ($org[0] == '"') $org = substr($org, 1, -1);
					$content = $this->read_org($org);
				}
				elseif ($state['grouped_view'] && !isset($this->grouped_views[$state['grouped_view']]))
				{
					$content = $this->read_org($state['grouped_view']);
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
					$content['owner'] = (string)($state['filter'] == 0 ? '' : $state['filter']);
				}
				$content['private'] = (int) ($content['owner'] && substr($content['owner'],-1) == 'p');
				if ($content['owner'] === '' || !($this->grants[$content['owner'] = (string) (int) $content['owner']] & Acl::ADD))
				{
					$content['owner'] = $this->default_addressbook;
					$content['private'] = (int)$this->default_private;

					if (!($this->grants[$content['owner'] = (string) (int) $content['owner']] & Acl::ADD))
					{
						$content['owner'] = (string) $this->user;
						$content['private'] = 0;
					}
				}
				$new_type = array_keys($this->content_types);
				// fetch active type to preset the type, if param typeid is not passed
				$active_tid = Api\Cache::getSession('addressbook','active_tid');
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
							//Set the presets fields in content in order to be able to use them later in client side for checking duplication only on first time load
							// after save/apply we unset them
							$content['presets_fields'][]= $field;
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
				$this->copy_contact($content);
				$content['msg'] = lang('%1 copied - the copy can now be edited', lang(Link::get_registry('addressbook','entry')));
				$view = false;
			}
			else
			{
				if ($contact_id && is_numeric($contact_id)) $content['link_to']['to_id'] = $contact_id;
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
						Link::link('addressbook',$content['link_to']['to_id'],$link_app,$link_id);
					}
				}
			}
		}
		// set $content[shared_options/_values] from $content[shared]
		$content['shared_options'] = [];
		$content['shared_values'] = [];
		foreach((array)$content['shared'] as $shared)
		{
			$shared_value = $shared['shared_id'] . ':' . $shared['shared_with'] . ':' . $shared['shared_by'] . ':' . $shared['shared_writable'];
			$content['shared_values'][] = $shared_value;
			$sel_options['shared_values'][] = [
				'value' => $shared_value,
				'label' => Api\Accounts::username($shared['shared_with']),
				'title' => lang('%1 shared this contact on %2 with %3 %4',
								Api\Accounts::username($shared['shared_by']), Api\DateTime::to($shared['shared_at']),
								Api\Accounts::username($shared['shared_with']), $shared['shared_writable'] ? lang('writable') : lang('readonly')
				),
				'icon'  => $shared['shared_writable'] ? 'edit' : 'view',
			];
		}
		// disable shared with UI for non-SQL backends
		$content['shared_disabled'] = !is_a($this->get_backend($content['id'], $content['owner']), Api\Contacts\Sql::class);

		if ($content['id'])
		{
			// last and next calendar date
			$dates = current($this->read_calendar(array($content['account_id'] ? $content['account_id'] : 'c'.$content['id']),false));
			if(is_array($dates)) $content += $dates;
		}

		// Registry has view_id as contact_id, so set it (custom fields uses it)
		$content['contact_id'] = $content['id'];

		// Avoid ID conflict with tree & selectboxes
		$content['cat_id_tree'] = $content['cat_id'];

		// Avoid setting conflicts with private custom fields
		$content['private_cfs'] = array();
		foreach(Api\Storage\Customfields::get('addressbook', true) as $name => $cf)
		{
			if ($this->config['private_cf_tab'] && $cf['private'] && isset($content['#'.$name]))
			{
				$content['private_cfs']['#'.$name] = $content['#'.$name];
			}
		}

		// how to display addresses
		$content['addr_format']  = $this->addr_format_by_country($content['adr_one_countryname']);
		$content['addr_format2'] = $this->addr_format_by_country($content['adr_two_countryname']);

		// Country codes
		foreach(array('adr_one', 'adr_two') as $c_prefix)
		{
			// handling custom country-name
			if (empty($content[$c_prefix.'_countrycode']) && !empty($content[$c_prefix.'_countryname']))
			{
				$content[$c_prefix.'_countrycode'] = $content[$c_prefix.'_countryname'];
			}
			// translate from our stored state-/region-name to the code
			if (!empty($content[$c_prefix.'_region']) && !empty($content[$c_prefix.'_countrycode']))
			{
				$states = Api\Country::get_states($content[$c_prefix.'_countrycode']);
				if ($states && ($key = array_search($content[$c_prefix.'_region'], $states)))
				{
					$content[$c_prefix.'_region'] = $key;
				}
			}
		}

		//_debug_array($content);
		$readonlys['button[delete]'] = !$content['owner'] || !$this->check_perms(Acl::DELETE,$content);
		$readonlys['button[copy]'] = $readonlys['button[edit]'] = $readonlys['button[vcard]'] = true;
		$readonlys['button[save]'] = $readonlys['button[apply]'] = $view;
		if ($view)
		{
			$readonlys['__ALL__'] = true;
			$readonlys['button[cancel]'] = false;
		}

		$sel_options['fileas_type'] = $this->fileas_options($content);
		$sel_options['owner'] = $this->get_addressbooks(Acl::ADD);
		if ($content['owner']) unset($sel_options['owner'][0]);	// do not offer to switch to accounts, as we do not support moving contacts to accounts
		if ((string) $content['owner'] !== '')
		{
			if (!isset($sel_options['owner'][(int)$content['owner']]))
			{
				$sel_options['owner'][(int)$content['owner']] = !$content['owner'] ? lang('Accounts') :
					Api\Accounts::username($content['owner']);
			}
			$readonlys['owner'] = !$content['owner'] || 		// dont allow to move accounts, as this mean deleting the user incl. all content he owns
				$content['id'] && !$this->check_perms(Acl::DELETE,$content);	// you need delete rights to move an existing contact into an other addressbook
		}
		// set the unsupported fields from the backend to readonly
		foreach($this->get_fields('unsupported',$content['id'],$content['owner']) as $field)
		{
			$readonlys[$field] = true;
		}
		// for editing own account, make all fields not allowed by own_account_acl readonly
		if (!$this->is_admin() && !$content['owner'] && $content['account_id'] == $this->user && $this->own_account_acl && !$view)
		{
			$readonlys['__ALL__'] = true;
			$readonlys['button[cancel]'] = false;

			foreach($this->own_account_acl as $field)
			{
				$readonlys[$field] = false;
			}
			if (!$readonlys['jpegphoto'])
			{
				$readonlys = array_merge($readonlys, array(
					'upload_photo' => false,
					'delete_photo' => false,
					'addressbook.edit.upload' => false
				));
			}
			if (!$readonlys['pubkey'])
			{
				$readonlys['addressbook:'.$content['id'].':.files/pgp-pubkey.asc'] =
				$readonlys['addressbook:'.$content['id'].':.files/smime-pubkey.crt'] = false;
			}
		}

		if (isset($readonlys['n_fileas'])) $readonlys['fileas_type'] = $readonlys['n_fileas'];
		// disable not needed tabs
		$readonlys['tabs']['cats'] = !($content['cat_tab'] = $this->config['cat_tab']);
		$readonlys['tabs']['custom'] = !$this->customfields ||
			// only show custom fields tab for LDAP, if we have LDAP CF's defined and an existing contact (as no schema defined)
			$this->get_backend($content['id'],$content['owner']) == $this->so_accounts &&
				(empty($content['id']) || !array_filter($this->customfields, static function($cf)
				{
					return substr($cf['name'], 0, 5) === 'ldap_';
				}));
		$readonlys['tabs']['custom_private'] = $readonlys['tabs']['custom'] || !$this->config['private_cf_tab'];
		$readonlys['tabs']['distribution_list'] = !$content['distrib_lists'];#false;
		$readonlys['tabs']['history'] = $this->contact_repository != 'sql' || !$content['id'] ||
			$this->account_repository != 'sql' && $content['account_id'];
		if (!$content['id']) $readonlys['button[delete]'] = !$content['id'];
		if ($this->config['private_cf_tab']) $content['no_private_cfs'] = 0;
		$content['hide_change_org'] = $readonlys['change_org'] = empty($content['org_name']) || $view;

		// for editing the own account (by a non-admin), enable only the fields allowed via the "own_account_acl"
		if (!$content['owner'] && !$this->check_perms(Acl::EDIT, $content))
		{
			$this->_set_readonlys_for_own_account_acl($readonlys, $content['id']);
		}
		for($i = -23; $i<=23; $i++)
		{
			$tz[$i] = ($i > 0 ? '+' : '').$i;
		}
		$sel_options['tz'] = $tz;
		$content['tz'] = $content['tz'] ? $content['tz'] : '0';
		if (count($this->content_types) > 1)
		{
			foreach($this->content_types as $type => $data)
			{
				$sel_options['tid'][$type] = $data['name'];
			}
			$content['typegfx'] = Api\Html::image('addressbook',$this->content_types[$content['tid']]['options']['icon'],'',' width="16px" height="16px"');
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
		if($content['tid'] == self::DELETED_TYPE)
		{
			$content['link_to']['show_deleted'] = true;
			if(!$GLOBALS['egw_info']['user']['apps']['admin'] && $this->config['history'] != 'userpurge')
			{
				$readonlys['button[delete]'] = true;
			}
		}

		// Enable history
		$this->setup_history($content, $sel_options);

		$content['photo'] = $this->photo_src($content['id'],$content['jpegphoto'],'',$content['etag']);

		if ($content['private']) $content['owner'] .= 'p';

		// for custom types, check if we have a custom edit template named "addressbook.edit.$type", $type is the name
		if (in_array($content['tid'], array('n',self::DELETED_TYPE)) || !$this->tmpl->read('addressbook.edit.'.$this->content_types[$content['tid']]['name']))
		{
			$this->tmpl->read('addressbook.edit');
		}

		// allow other apps to add tabs to addressbook edit
		$preserve = $content;
		$preserve['old_owner'] = $content['owner'];
		unset($preserve['jpegphoto'], $content['jpegphoto']);	// unused and messes up json encoding (not utf-8)
		$this->tmpl->setElementAttribute('tabs', 'add_tabs', true);
		$tabs =& $this->tmpl->getElementAttribute('tabs', 'extraTabs');
		if (($first_call = !isset($tabs)))
		{
			$tabs = array();
		}
		//error_log(__LINE__.': '.__METHOD__."() first_call=$first_call");
		$hook_data = Api\Hooks::process(array('location' => 'addressbook_edit')+$content);
		//error_log(__METHOD__."() hook_data=".array2string($hook_data));
		foreach($hook_data as $extra_tabs)
		{
			if (!$extra_tabs) continue;

			foreach(count(array_filter(array_keys($extra_tabs), 'is_int')) ? $extra_tabs : array($extra_tabs) as $extra_tab)
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
	 * Check if user has right to share with / into given AB
	 *
	 * @param array $_data values for keys "shared_writable", "shared_values" and "contact"
	 * @return array of entries removed from $shared_with because current user is not allowed to share into
	 */
	public function ajax_check_shared(array $_data)
	{
		$response = Api\Json\Response::get();
		try {
			$shared = [];
			foreach($_data['shared_values'] as $value)
			{
				if (is_numeric($value))
				{
					$shared[$value] = [
						'shared_with' => $value,
						'shared_by' => $this->user,
						'shared_writable' => (int)$_data['shared_writable'],
					];
				}
				else
				{
					$shared[$value] = array_combine(['shared_id', 'shared_with', 'shared_by', 'shared_writable'], explode(':', $value));
				}
				$shared[$value]['contact'] = $_data['contact'];
			}
			if (($failed = $this->check_shared_with($shared, $error)))
			{
				$response->data(array_keys($failed));
				$response->message($error ?: lang('You are not allowed to share into the addressbook of %1',
					implode(', ', array_map(function ($data) {
						return Api\Accounts::username($data['shared_with']);
					}, $failed))), 'error');
			}
		}
		catch (\Exception $e) {
			$response->message($e->getMessage(), 'error');
		}
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
			foreach(array_keys($this->customfields) as $name)
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
	 * @param int $own_id =0 own contact id, to not check against it
	 * @return array with keys 'msg' => "EMail address exists, do you want to open contact?" (or null if not existing)
	 * 	'data' => array of id => "full name (addressbook)" pairs
	 *  'fileas_options'
	 */
	public function ajax_check_values($values, $name, $own_id=0)
	{
		if (!is_array($fields = $GLOBALS['egw_info']['user']['preferences']['addressbook']['duplicate_fields'] ?? []))
		{
			$fields = explode(',', $fields);
		}
		$threshold = (int)$GLOBALS['egw_info']['user']['preferences']['addressbook']['duplicate_threshold'];

		$ret = array('doublicates' => array(), 'msg' => null);

		// set not returned n_fileas value, to keep custom fileas value
		$values['n_fileas'] = $this->fileas($values, $values['fileas_type']);

		// if email changed, check for doublicates
		if (in_array($name, array('email', 'email_home')) && in_array('contact_'.$name, $fields))
		{
			if (preg_match(Etemplate\Widget\Url::EMAIL_PREG, $values[$name]))	// only search for real email addresses, to not return to many contacts
			{
				$contacts = parent::search(array(
					'email' => $values[$name],
					'email_home' => $values[$name],
				), false, '', '', '', false, 'OR');
			}
		}
		else
		{
			// only set fileas-options if other then email changed
			$ret['fileas_options'] = array_values($this->fileas_options($values));
			// Full options for et2
			$ret['fileas_sel_options'] = $this->fileas_options($values);

			// if name, firstname or org changed and enough are specified, check for doublicates
			$specified_count = 0;
			foreach($fields as $field)
			{
				if($values[trim($field)])
				{
					$specified_count++;
				}
			}
			if (in_array($name,$fields) && $specified_count >= $threshold)
			{
				$filter = array();
				foreach($fields as $n)	// use email too, to exclude obvious false positives
				{
					if (!empty($values[$n])) $filter[$n] = $values[$n];
				}
				$contacts = parent::search('', false, '', '', '', false, 'AND', false, $filter);
			}
		}
		if ($contacts)
		{
			foreach($contacts as $contact)
			{
				if ($own_id && $contact['id'] == $own_id) continue;

				$ret['doublicates'][$contact['id']] = $this->fileas($contact).' ('.
					(!$contact['owner'] ? lang('Accounts') : ($contact['owner'] == $this->user ?
					($contact['private'] ? lang('Private') : lang('Personal')) : Api\Accounts::username($contact['owner']))).')';
			}
			if ($ret['doublicates'])
			{
				$ret['msg'] = lang('Similar contacts found:').
					"\n\n".implode("\n", $ret['doublicates'])."\n\n".
					lang('Open for editing?');
			}
		}
		//error_log(__METHOD__.'('.array2string($values).", '$name', $own_id) doublicates found ".array2string($ret['doublicates']));
		Api\Json\Response::get()->data($ret);
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
			$button = key($content['button'] ?? []);
			switch ($button)
			{
				case 'vcard':
					Egw::redirect_link('/index.php','menuaction=addressbook.uivcard.out&ab_id=' .$content['id']);

				case 'cancel':
					Egw::redirect_link('/index.php','menuaction=addressbook.addressbook_ui.index&ajax=true');

				case 'delete':
					Egw::redirect_link('/index.php',array(
						'menuaction' => 'addressbook.addressbook_ui.index',
						'msg' => $this->delete($content) ? lang('Contact deleted') : lang('Error deleting the contact !!!'),
					));

				case 'next':
					$inc = 1;
					// fall through
				case 'back':
					if (!isset($inc)) $inc = -1;
					// get next/previous contact in selection
					$query = Api\Cache::getSession('addressbook', 'index');
					$query['start'] = $content['index'] + $inc;
					$query['num_rows'] = 1;
					$rows = $readonlys = array();
					$num_rows = $this->get_rows($query, $rows, $readonlys, true);
					//error_log(__METHOD__."() get_rows()=$num_rows rows=".array2string($rows));
					$contact_id = $rows[0];
					if(!$contact_id || !is_array($content = $this->read($contact_id)))
					{
						Egw::redirect_link('/index.php',array(
							'menuaction' => 'addressbook.addressbook_ui.index',
							'msg' => $content,
							'ajax' => 'true'
						));
					}
					$content['index'] = $query['start'];

					// List nextmatch is already there, just update the filter
					if($contact_id && Api\Json\Request::isJSONRequest())
					{
						switch($crm_list)
						{
							case 'infolog-organisation':
								$contact_id = $this->get_all_org_contacts($contact_id);
								// Fall through
							case 'infolog':
							case 'tracker':
							default:
								Api\Json\Response::get()->apply('app.addressbook.view_set_list',Array(Array('action'=>'addressbook', 'action_id' => $contact_id)));
								break;
						}

						// Clear contact_id, it's used as a flag to send the list
						unset($contact_id);
					}
					break;
				default:
					// No button, probably a refresh
					$content = $this->read($content['id']);
					break;
			}
		}
		else
		{
			// allow to search eg. for a phone number
			if (isset($_GET['search']))
			{
				$query = Api\Cache::getSession('addressbook', 'index');
				$query['search'] = $_GET['search'];
				unset($_GET['search']);
				// reset all filters
				unset($query['advanced_search']);
				$query['col_filter'] = array();
				$query['filter'] = $query['filter2'] = $query['cat_id'] = '';
				Api\Cache::setSession('addressbook', 'index', $query);
				$query['start'] = 0;
				$query['num_rows'] = 1;
				$rows = $readonlys = array();
				$num_rows = $this->get_rows($query, $rows, $readonlys, true);
				$_GET['contact_id'] = array_shift($rows);
				$_GET['index'] = 0;
			}
			$contact_id = $_GET['contact_id'] ?? ((int)$_GET['account_id'] ? 'account:'.(int)$_GET['account_id'] : 0);
			if(!$contact_id || !is_array($content = $this->read($contact_id)))
			{
				Egw::redirect_link('/index.php',array(
					'menuaction' => 'addressbook.addressbook_ui.index',
					'msg' => $content,
					'ajax' => 'true'
				)+(isset($_GET['search']) ? array('search' => $_GET['search']) : array()));
			}
			if (isset($_GET['index']))
			{
				$content['index'] = (int)$_GET['index'];
				// get number of rows to determine if we can have a next button
				$query = Api\Cache::getSession('addressbook', 'index');
				$query['start'] = $content['index'];
				$query['num_rows'] = 1;
				$rows = $readonlys = array();
				$num_rows = $this->get_rows($query, $rows, $readonlys, true);
			}
		}
		$content['jpegphoto'] = !empty($content['jpegphoto']);	// unused and messes up json encoding (not utf-8)

		// make everything not explicit mentioned readonly
		$readonlys['__ALL__'] = true;
		$readonlys['photo']  = $readonlys['button[copy]'] =false;

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
			$content['cat_id'] = $this->categories->check_list(Acl::READ,$content['cat_id']);
		}
		$content['cat_id_tree'] = $content['cat_id'];

		$content['view'] = true;
		$content['link_to'] = array(
			'to_app' => 'addressbook',
			'to_id'  => $content['id'],
		);
		// Links for deleted entries
		if($content['tid'] == self::DELETED_TYPE)
		{
			$content['link_to']['show_deleted'] = true;
		}
		$readonlys['button[delete]'] = !$content['owner'] || !$this->check_perms(Acl::DELETE,$content);
		$readonlys['button[edit]'] = !$this->check_perms(Acl::EDIT,$content);

		// how to display addresses
		$content['addr_format']  = $this->addr_format_by_country($content['adr_one_countryname']);
		$content['addr_format2'] = $this->addr_format_by_country($content['adr_two_countryname']);

		$sel_options['fileas_type'][$content['fileas_type']] = $this->fileas($content);
		$sel_options['owner'] = $this->get_addressbooks();
		for($i = -23; $i<=23; $i++)
		{
			$tz[$i] = ($i > 0 ? '+' : '').$i;
		}
		$sel_options['tz'] = $tz;
		$content['tz'] = $content['tz'] ? $content['tz'] : 0;
		if (count($this->content_types) > 1)
		{
			foreach($this->content_types as $type => $data)
			{
				$sel_options['tid'][$type] = $data['name'];
			}
			$content['typegfx'] = Api\Html::image('addressbook',$this->content_types[$content['tid']]['options']['icon'],'',' width="16px" height="16px"');
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
		$content['hide_change_org'] = true;

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
		if (!empty($content['id'])) $dates = current($this->read_calendar(array($content['account_id'] ? $content['account_id'] : 'c'.$content['id']),false));
		if(is_array($dates)) $content += $dates;

		// Disable importexport
		$GLOBALS['egw_info']['flags']['disable_importexport']['export'] = true;
		$GLOBALS['egw_info']['flags']['disable_importexport']['merge'] = true;

		// set id for automatic linking via quick add
		$GLOBALS['egw_info']['flags']['currentid'] = $content['id'];

		// load app.css for addressbook explicit, as addressbook_view hooks changes currentapp!
		Framework::includeCSS('addressbook', 'app');

		// dont show an app-header
		$GLOBALS['egw_info']['flags']['app_header'] = '';

		// always show sidebox, as it contains contact-data
		unset($GLOBALS['egw_info']['user']['preferences']['common']['auto_hide_sidebox']);

		// need to load list's app.js now, as exec calls header before other app can include it
	//	Framework::includeJS('/'.$crm_list.'/js/app.js');

		// Load CRM code
		Framework::includeJS('.','CRM','addressbook');
		$content['view_sidebox'] = addressbook_hooks::getViewDOMID($content['id'], $crm_list);
		$this->tmpl->exec('addressbook.addressbook_ui.view',$content,$sel_options,$readonlys,array(
			'id' => $content['id'],
			'index' => $content['index'],
			'crm_list' => $crm_list
		));

		// Only load this on first time - we're using AJAX, so it stays there through submits.
		// Sending it again (via ajax) will break the addressbook.view etemplate2
		if($contact_id)
		{
			// Show for whole organisation, not just selected contact
			if($crm_list == 'infolog-organisation')
			{
				$crm_list = str_replace('-organisation','',$crm_list);
				$_query = Api\Cache::getSession('addressbook', 'index');
				$content['id'] = $this->get_all_org_contacts($content['id']);
			}
			Api\Hooks::single(array(
				'location' => 'addressbook_view',
				'ab_id'    => $content['id']
			),$crm_list);
		}
	}

	/**
	 * Get all the contact IDs in the given contact's organisation
	 *
	 * @param int $contact_id
	 * @param Array $query Optional base query
	 *
	 * @return array of contact IDs in the organisation
	 */
	function get_all_org_contacts($contact_id, $query = array())
	{
		$contact = $this->read($contact_id);

		// No org name, early return with just the contact
		if(!$contact['org_name'])
		{
			return array($contact_id);
		}

		$query['num_rows'] = -1;
		$query['start'] = 0;
		if(!array_key_exists('filter', $query))
		{
			$query['filter'] = '';
		}
		if(!is_array($query['col_filter']))
		{
			$query['col_filter'] = array();
		}
		$query['grouped_view'] = 'org_name:'.$contact['org_name'];

		$org_contacts = array();
		$readonlys = null;
		$this->get_rows($query,$org_contacts,$readonlys,true);	// true = only return the id's

		return $org_contacts ? $org_contacts : array($contact_id);
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
	function extSearch($_content=array())
	{
		if(!empty($_content))
		{

			$_content['cat_id'] = $this->config['cat_tab'] === 'Tree' ? $_content['cat_id_tree'] : $_content['cat_id'];

			$response = Api\Json\Response::get();

			$query = Api\Cache::getSession('addressbook', 'index');

			if ($_content['button']['cancelsearch'])
			{
				unset($query['advanced_search']);
			}
			else
			{
				if($this->config['private_cf_tab'])
				{
					$_content = array_merge($_content, $_content['private_cfs']);
				}
				$query['advanced_search'] = array_intersect_key(
					$_content,
					array_flip(array_merge($this->get_contact_columns(), array('operator', 'meth_select')))
				);
				foreach($query['advanced_search'] as $key => $value)
				{
					if(!$value)
					{
						unset($query['advanced_search'][$key]);
					}
				}
				// Skip n_fn, it causes problems in sql
				unset($query['advanced_search']['n_fn']);
			}
			$query['search'] = '';
			// store the index state in the session
			Api\Cache::setSession('addressbook', 'index', $query);

			// store the advanced search in the session to call it again
			Api\Cache::setSession('addressbook', 'advanced_search', $query['advanced_search']);

			// Update client / nextmatch with filters, or clear
			$response->call("app.addressbook.adv_search", array('advanced_search' => $_content['button']['search'] ? $query['advanced_search'] : ''));
			if ($_content['button']['cancelsearch'])
			{
				Framework::window_close ();

				// No need to reload popup
				return;
			}
		}

		$GLOBALS['egw_info']['etemplate']['advanced_search'] = true;

		// initialize etemplate arrays
		$sel_options = $readonlys = array();
		$this->tmpl->read('addressbook.edit');
		$content = Api\Cache::getSession('addressbook', 'advanced_search');
		$content['n_fn'] = $this->fullname($content);
		// Avoid ID conflict with tree & selectboxes
		$content['cat_id_tree'] = $content['cat_id'];

		for($i = -23; $i<=23; $i++)
		{
			$tz[$i] = ($i > 0 ? '+' : '').$i;
		}
		$sel_options['tz'] = $tz + array('' => lang('doesn\'t matter'));
		$sel_options['tid'][] = lang('all');
		//foreach($this->content_types as $type => $data) $sel_options['tid'][$type] = $data['name'];

		// configure search options
		$sel_options['owner'] = $this->get_addressbooks(Acl::READ,lang('all'));
		$sel_options['operator'] =  array(
			'AND' => lang('AND'),
			'OR' => lang('OR'),
		);
		$sel_options['meth_select'] = array(
			'%'		=> lang('contains'),
			false	=> lang('exact'),
		);
		if ($this->customfields)
		{
			foreach($this->customfields as $name => $data)
			{
				if(substr($data['type'], 0, 6) == 'select' && !($data['rows'] > 1))
				{
					if(!isset($content['#' . $name]))
					{
						$content['#' . $name] = '';
					}
					if(!isset($data['values']['']))
					{
						$sel_options['#' . $name][''] = lang('Select one');
					}
				}
				// Make them not required, otherwise you can't search
				$this->tmpl->setElementAttribute('#' . $name, 'required', FALSE);
				if($this->config['private_cf_tab'] == 'True' && $data['private'])
				{
					if(isset($content['#' . $name]))
					{
						$content['private_cfs']['#' . $name] = $content['#' . $name];
					}
					// Private CF tab results in a different ID, turn required off there too
					$this->tmpl->setElementAttribute('private_cfs[#' . $name . ']', 'required', FALSE);
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
		$content['hide_change_org'] = true;
		$content['no_tid'] = true;
		$content['showsearchbuttons'] = true; // enable search operation and search buttons| they're disabled by default

		if ($this->config['private_cf_tab']) $content['no_private_cfs'] = 0;

		return $this->tmpl->exec('addressbook.addressbook_ui.extSearch',$content,$sel_options,$readonlys,array(),2);
	}

	/**
	 * Check if there's a photo for given contact id. This is used for avatar widget
	 * to set or unset delete button. If there's no uploaded photo it responses true.
	 *
	 * @param type $contact_id
	 */
	function ajax_noPhotoExists ($contact_id)
	{
		$response = Api\Json\Response::get();
		$response->data((!($contact = $this->read($contact_id)) ||
			empty($contact['photo']) &&	!(($contact['files'] & Api\Contacts::FILES_BIT_PHOTO) &&
				($size = filesize($url=Api\Link::vfs_path('addressbook', $contact_id, Api\Contacts::FILES_PHOTO))))));
	}

	/**
	 * Ajax method to update edited avatar photo via avatar widget
	 *
	 * @param string $etemplate_exec_id to update id, files, etag, ...
	 * @param file string $file null means to delete
	 */
	function ajax_update_photo ($etemplate_exec_id, $file)
	{
		$et_request = Api\Etemplate\Request::read($etemplate_exec_id);
		$response = Api\Json\Response::get();
		if ($file)
		{
			$filteredFile = substr($file, strpos($file, ",")+1);
			// resize photo if wider then default width of 240pixel (keeping aspect ratio)
			$decoded = $this->resize_photo(base64_decode($filteredFile));
		}
		$response->data(true);
		// add photo into current eT2 request
		$et_request->preserv = array_merge($et_request->preserv, array(
			'jpegphoto' => is_null($file) ? $file : $decoded,
			'photo_unchanged' => false,	// hint photo is changed
		));
	}

	/**
	 * Callback for vfs-upload widgets for PGP and S/Mime pubkey
	 *
	 * @param array $file
	 * @param string $widget_id
	 * @param Api\Etemplate\Request $request eT2 request eg. to access attribute $content
	 * @param Api\Json\Response $response
	 */
	public function pubkey_uploaded(array $file, $widget_id, Api\Etemplate\Request $request, Api\Json\Response $response)
	{
		//error_log(__METHOD__."(".array2string($file).", ...) widget_id=$widget_id, id=".$request->content['id'].", files=".$request->content['files']);
		unset($file, $response);	// not used, but required by function signature
		list(,,$path) = explode(':', $widget_id);
		$bit = $path === Api\Contacts::FILES_PGP_PUBKEY ? Api\Contacts::FILES_BIT_PGP_PUBKEY : Api\Contacts::FILES_BIT_SMIME_PUBKEY;
		if (!($request->content['files'] & $bit) && $this->check_perms(Acl::EDIT, $request->content))
		{
			$content = $request->content;
			$content['files'] |= $bit;
			$content['photo_unchanged'] = true;	// hint no need to store photo
			if ($this->save($content))
			{
				$changed = array_diff_assoc($content, $request->content);
				//error_log(__METHOD__."() changed=".array2string($changed));
				$request->content = $content;
				// need to update preserv, as edit stores content there too and we would get eg. an contact modified error when trying to store
				$request->preserv = array_merge($request->preserv, $changed);
			}
		}
	}

	/**
	 * Migrate contacts to or from LDAP (called by Admin >> Addressbook >> Site configuration (Admin only)
	 *
	 */
	function migrate2ldap($type=null)
	{
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Addressbook').' - '.lang('Migration to LDAP');
		echo $GLOBALS['egw']->framework->header();
		echo $GLOBALS['egw']->framework->navbar();

		if (!$this->is_admin())
		{
			echo '<h1>'.lang('Permission denied !!!')."</h1>\n";
		}
		else
		{
			parent::migrate2ldap($type ?? $_GET['type']);
			echo '<p style="margin-top: 20px;"><b>'.lang('Migration finished')."</b></p>\n";
		}
		echo $GLOBALS['egw']->framework->footer();
	}

	/**
	 * Set n_fileas (and n_fn) in contacts of all users  (called by Admin >> Addressbook >> Site configuration (Admin only)
	 *
	 * If $_GET[all] all fileas fields will be set, if !$_GET[all] only empty ones
	 *
	 */
	function admin_set_fileas()
	{
		Api\Translation::add_app('admin');
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Addressbook').' - '.lang('Contact maintenance');
		echo $GLOBALS['egw']->framework->header();
		echo $GLOBALS['egw']->framework->navbar();

		// check if user has admin rights AND if a valid fileas type is given (Security)
		if (!$this->is_admin() || $_GET['type'] != '' && !in_array($_GET['type'],$this->fileas_types))
		{
			echo '<h1>'.lang('Permission denied !!!')."</h1>\n";
		}
		else
		{
			$errors = null;
			$updated = parent::set_all_fileas($_GET['type'],(boolean)$_GET['all'],$errors,true);	// true = ignore Acl
			echo '<p style="margin-top: 20px;"><b>'.lang('%1 contacts updated (%2 errors).',$updated,$errors)."</b></p>\n";
		}
		echo $GLOBALS['egw']->framework->footer();
	}

	/**
	 * Cleanup all contacts of all users (called by Admin >> Addressbook >> Site configuration (Admin only)
	 *
	 */
	function admin_set_all_cleanup()
	{
		Api\Translation::add_app('admin');
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Addressbook').' - '.lang('Contact maintenance');
		echo $GLOBALS['egw']->framework->header();
		echo $GLOBALS['egw']->framework->navbar();

		// check if user has admin rights (Security)
		if (!$this->is_admin())
		{
			echo '<h1>'.lang('Permission denied !!!')."</h1>\n";
		}
		else
		{
			$errors = null;
			$updated = parent::set_all_cleanup($errors,true);	// true = ignore Acl
			echo '<p style="margin-top: 20px;"><b>'.lang('%1 contacts updated (%2 errors).',$updated,$errors)."</b></p>\n";
		}
		echo $GLOBALS['egw']->framework->footer();
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

		// Addressbook also has an 'owner' field, which has different options.
		// If we don't put something here (just empty won't work), history log will use
		// those options instead of the select-account options.
		$sel_options['history']['owner'] = ['ignore' => 'me'];
	}
}