<?php
/**
 * EGroupware: Admin app ACL
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb@stylite.de>
 * @package admin
 * @copyright (c) 2013 by Ralf Becker <rb@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

require_once EGW_INCLUDE_ROOT.'/etemplate/inc/class.etemplate.inc.php';

/**
 * UI for admin
 */
class admin_acl
{
	/**
	 * Methods callable via menuaction
	 * @var array
	 */
	public $public_functions = array(
		'index' => true,
	);

	/**
	 * Callback for nextmatch to fetch acl
	 *
	 * @param array $query
	 * @param array &$rows=null
	 * @return int total number of rows available
	 */
	public static function get_rows(array $query, array &$rows=null)
	{
		$so_sql = new so_sql('phpgwapi', acl::TABLE, null, '', true);

		$memberships = $GLOBALS['egw']->accounts->memberships($query['account_id'], true);
		$memberships[] = $query['account_id'];

		if ($GLOBALS['egw_info']['user']['preferences']['admin']['acl_filter'] != $query['filter'])
		{
			$GLOBALS['egw']->preferences->add('admin', 'acl_filter', $query['filter']);
			$GLOBALS['egw']->preferences->save_repository(false,'user',false);
		}
		switch($query['filter'])
		{
			default:
			case 'run':
				$query['col_filter']['acl_location'] = 'run';
				$query['col_filter']['acl_account'] = $memberships;
				break;
			case 'own':
				$query['col_filter'][] = "acl_location!='run'";
				$query['col_filter']['acl_account'] = $memberships;
				break;

			case 'other':
				$query['col_filter']['acl_location'] = $query['account_id'];
				break;
		}

		$total = $so_sql->get_rows($query, $rows, $readonlys);

		static $rights = array(
			acl::READ => 'read',
			acl::ADD  => 'add',
			acl::EDIT => 'edit',
			acl::DELETE => 'delete',
			acl::PRIVAT => 'private',
			acl::CUSTOM1 => 'custom 1',
			acl::CUSTOM2 => 'custom 2',
			acl::CUSTOM3 => 'custom 3',
		);

		$app_rights = $GLOBALS['egw']->hooks->process(array(
			'location' => 'acl_rights',
			'owner' => $query['account_id'],
		), array(), true);

		foreach($rows as $n => &$row)
		{
			// generate a row-id
			$row['id'] = $row['acl_appname'].'-'.$row['acl_account'].'-'.$row['acl_location'];

			if ($query['filter'] == 'run')
			{
				$row['acl1'] = lang('run');
			}
			else
			{
				if ($app !== $row['acl_appname']) translation::add_app($row['app_name']);
				foreach(isset($app_rights[$row['acl_appname']]) ? $app_rights[$row['acl_appname']] : $rights as $val => $label)
				{
					if ($row['acl_rights'] & $val)
					{
						$row['acl'.$val] = lang($label);
					}
				}
			}
			error_log(__METHOD__."() $n: ".array2string($row));
		}
		error_log(__METHOD__."(".array2string($query).") returning ".$total);
		return $total;
	}

	/**
	 * New index page
	 *
	 * @param array $content
	 * @param string $msg
	 */
	public function index(array $content=null, $msg='')
	{
		$tpl = new etemplate_new('admin.acl');

		$content = array();
		$content['nm'] = array(
			'get_rows' => 'admin_acl::get_rows',
			'no_cat' => true,
			'filter' => $GLOBALS['egw_info']['user']['preferences']['admin']['acl_filter'],
			'no_filter2' => true,
			'lettersearch' => false,
			//'order' => 'account_lid',
			'sort' => 'ASC',
			'row_id' => 'id',
			//'default_cols' => '!account_id,account_created',
			'actions' => self::get_actions(),
		);
		if (isset($_GET['account_id']) && (int)$_GET['account_id'])
		{
			$content['nm']['account_id'] = (int)$_GET['account_id'];
			$content['nm']['acl_app'] = '';	// show app run rights
			$content['nm']['order'] = 'acl_appname';
		}
		$sel_options = array(
			'filter' => array(
				'other' => 'Rights granted to others',
				'own'   => 'Own rights granted from others',
				'run'   => 'Run rights for applications',
			),
		);
		$tpl->exec('admin.admin_acl.index', $content, $sel_options);
	}

	/**
	 * Get actions for ACL
	 *
	 * @return array
	 */
	static function get_actions()
	{
		return array(
			'edit' => array(
				'caption' => 'Edit ACL',
				'default' => true,
				'allowOnMultiple' => false,
			),
			'add' => array(
				'caption' => 'Add ACL',
			),
			'delete' => array(
				'confirm' => 'Delete this ACL',
				'caption' => 'Delete ACL',
				'disableClass' => 'rowNoEdit',
			),
		);
	}
}
