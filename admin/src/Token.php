<?php
/**
 * EGroupware - Admin - Application passwords / tokens
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb-AT-egroupware.org>
 * @package admin
 * @copyright (c) 2023 by Ralf Becker <rb-AT-egroupware.org>
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Admin;

use EGroupware\Api;

class Token
{
	const APP = 'admin';

	/**
	 * Methods callable via menuaction GET parameter
	 *
	 * @var array
	 */
	public $public_functions = [
		'index' => true,
		'edit'  => true,
	];

	/**
	 * Instance of our business object
	 *
	 * @var Api\Auth\Token
	 */
	protected $token;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->token = new Api\Auth\Token();
	}

	/**
	 * Edit or add a token
	 *
	 * @param array $content =null
	 */
	public function edit(array $content=null)
	{
		if (!is_array($content))
		{
			if (!empty($_GET['token_id']))
			{
				if (!($content = $this->token->read(['token_id' => $_GET['token_id']])))
				{
					Api\Framework::window_close(lang('Token not found!'));
				}
			}
			else
			{
				$content = $this->token->init()+['new_token' => true];
			}
			if (static::APP !== 'admin')
			{
				Api\Translation::add_app('admin');
			}
		}
		elseif (!empty($content['button']))
		{
			$button = key($content['button'] ?? []);
			unset($content['button']);

			if ($button !== 'cancel' && static::APP !== 'admin' &&
				!(new Api\Auth())->authenticate($GLOBALS['egw_info']['user']['account_lid'], $content['password']))
			{
				Api\Etemplate::set_validation_error('password', lang('Password is invalid'));
				$button = null;
			}
			try {
				switch($button)
				{
					case 'save':
					case 'apply':
						$content['token_limits'] = Api\Auth\Token::apps2limits($content['token_apps']);
						if (empty($content['token_id']) || $content['new_token'])
						{
							$content['new_token'] = true;
							$button = 'apply';  // must not close window to show token
							if (empty($GLOBALS['egw_info']['user']['apps']['admin']) || static::APP !== 'admin')
							{
								$content['account_id'] = $GLOBALS['egw_info']['user']['account_id'];
							}
						}
						$this->token->save($content);
						Api\Framework::refresh_opener(empty($content['new_token']) ? lang('Token saved.') : lang('Token created.'),
							self::APP, $this->token->data['token_id'],'edit');
						unset($content['new_token']);
						$content = array_merge($content, $this->token->data);
						if ($button === 'save')
						{
							Api\Framework::window_close();	// does NOT return
						}
						break;

					case 'delete':
						$this->token->revoke($content['token_id']);
						Api\Framework::refresh_opener(lang('Token revoked.'),
								self::APP, $content['token_id'], 'update');
						Api\Framework::window_close();	// does NOT return
						break;

					case 'cancel':
						Api\Framework::window_close();	// does NOT return
						break;
				}
			}
			catch(\Exception $e) {
				Api\Framework::message($e->getMessage(), 'error');
			}
		}
		$content['token_apps'] = Api\Auth\Token::limits2apps($content['token_limits']);
		$content['admin'] = !empty($GLOBALS['egw_info']['user']['apps']['admin']) && static::APP === 'admin';
		if (empty($content['account_id'])) $content['account_id'] = '';
		$readonlys = [
			'button[delete]' => !$content['token_id'],
			'account_id' => empty($GLOBALS['egw_info']['user']['apps']['admin']) || static::APP !== 'admin',
		];
		$GLOBALS['egw_info']['flags']['app_header'] = empty($content['token_id']) ? lang('Add token') : lang('Edit token');
		$tmpl = new Api\Etemplate(self::APP.'.token.edit');
		$tmpl->exec(static::APP.'.'.static::class.'.edit', $content, [], $readonlys, $content, 2);
	}

	/**
	 * Fetch rows to display
	 *
	 * @param array $query with keys 'start', 'search', 'order', 'sort', 'col_filter'
	 *	For other keys like 'filter', 'cat_id' you have to reimplement this method in a derived class.
	 * @param array &$rows returned rows/competitions
	 * @param array &$readonlys eg. to disable buttons based on acl, not use here, maybe in a derived class
	 * @param string $join ='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or
	 *	"LEFT JOIN table2 ON (x=y)", Note: there's no quoting done on $join!
	 * @param boolean $need_full_no_count =false If true an unlimited query is run to determine the total number of rows, default false
	 * @param mixed $only_keys =false, see search
	 * @param string|array $extra_cols =array()
	 * @return int total number of rows
	 */
	function get_rows($query,&$rows,&$readonlys,$join='',$need_full_no_count=false,$only_keys=false,$extra_cols=array())
	{
		// do NOT show all users or other users to non-admin or regular user UI
		if (empty($GLOBALS['egw_info']['user']['apps']['admin']) || static::APP !== 'admin')
		{
			$query['col_filter']['account_id'] = $GLOBALS['egw_info']['user']['account_id'];
		}
		// sort revoked token behind active ones
		if (empty($query['order']) || $query['order'] === 'token_id')
		{
			$order_by = 'token_revoked IS NOT NULL,token_id '.($query['sort'] ?? 'DESC').',token_revoked '.($query['sort'] ?? 'DESC');
		}
		else
		{
			$order_by = $query['order'].' '.$query['sort'];
		}
		$rows = $this->token->search($query['critera'] ?? '', $only_keys, $order_by, $extra_cols,
			'',false, 'AND',$query['num_rows']?array((int)$query['start'],$query['num_rows']):(int)$query['start'],
			$query['col_filter'],$join,$need_full_no_count) ?: [];
		foreach($rows as &$row)
		{
			$row['token_apps'] = Api\Auth\Token::limits2apps($row['token_limits']);
			if ($row['token_revoked'])
			{
				$row['class'] = 'revoked';
			}
		}
		return $this->token->total;
	}

	/**
	 * Index
	 *
	 * @param array $content =null
	 */
	public function index(array $content=null)
	{
		if (!is_array($content) || empty($content['token']))
		{
			$content = [
				'token' => self::get_nm_options(),
			];
		}
		elseif(!empty($content['token']['action']))
		{
			try {
				Api\Framework::message(self::action($content['token']['action'],
					$content['token']['selected'], $content['token']['select_all']));
			}
			catch (\Exception $ex) {
				Api\Framework::message($ex->getMessage(), 'error');
			}
		}
		$tmpl = new Api\Etemplate(self::APP.'.tokens');
		$tmpl->exec(self::APP.'.'.self::class.'.index', $content, [
			'account_id' => ['0' => lang('All users')]
		], [], ['token' => $content['token']]);
	}

	/**
	 * Options for NM widget
	 *
	 * @return array
	 */
	protected static function get_nm_options()
	{
		return [
			'get_rows'       =>	static::APP.'.'.static::class.'.get_rows',
			'no_filter'      => true,	// disable the diverse filters we not (yet) use
			'no_filter2'     => true,
			'no_cat'         => true,
			'order'          =>	'token_id',// IO name of the column to sort after (optional for the sortheaders)
			'sort'           =>	'DESC',// IO direction of the sort: 'ASC' or 'DESC'
			'row_id'         => 'token_id',
			'actions'        => self::get_actions(static::APP),
			'placeholder_actions' => array('add'),
			'add_action'       => "egw.open_link('".Api\Egw::link('/index.php', 'menuaction='.static::APP.'.'.static::class.'.edit')."','_blank','600x380')",
		];
	}

	/**
	 * Return actions for cup list
	 *
	 * @param array $cont values for keys license_(nation|year|cat)
	 * @return array
	 */
	public static function get_actions(string $app='admin')
	{
		$actions = [
			'edit' => [
				'caption' => 'Edit',
				'default' => true,
				'allowOnMultiple' => false,
				'url' => 'menuaction='.self::APP.'.'.self::class.'.edit&token_id=$id',
				'popup' => '640x480',
				'group' => $group=0,
			],
			'add' => [
				'caption' => 'Create',
				'url' => 'menuaction='.self::APP.'.'.self::class.'.edit',
				'popup' => '640x400',
				'group' => $group,
			],
			'activate' => [
				'caption' => 'Activate',
				'confirm' => 'Active this token again',
				'enableClass' => 'revoked',
				'group' => $group=5,
			],
			'revoke' => [
				'caption' => 'Revoke',
				'confirm' => 'Revoke this token',
				'icon' => 'delete',
				'disableClass' => 'revoked',
				'group' => $group,
			],
		];
		if ($app === 'preferences')
		{
			foreach([
		        'edit' => 'app.preferences.editToken',
		        'add' => 'app.preferences.addToken',
			] as $action => $exec)
			{
				$actions[$action]['onExecute'] = 'javaScript:'.$exec;
				unset($actions[$action]['url'], $actions[$action]['popup']);
			}
		}
		return $actions;
	}

	/**
	 * Execute action on list
	 *
	 * @param string $action
	 * @param array|int $selected
	 * @param boolean $select_all
	 * @returns string with success message
	 * @throws Api\Exception\AssertionFailed
	 */
	protected static function action($action, $selected, $select_all=false)
	{
		$success = 0;
		try {
			switch($action)
			{
				case 'revoke':
				case 'activate':
					$revoke = $action === 'revoke';
					foreach($selected as $token_id)
					{
						Api\Auth\Token::revoke($token_id, $revoke);
						++$success;
					}
					return lang('%1 token %2.', $success, $revoke ? lang('revoked') : lang('activated again'));

				default:
					throw new Api\Exception\AssertionFailed('To be implemented ;)');
			}
		}
		catch(\Exception $e) {
			if ($success) {
				$e = new \Exception($e->getMessage().', '.lang('%1 successful', $success), $e);
			}
			throw $e;
		}
	}
}