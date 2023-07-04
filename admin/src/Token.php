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
	 * Edit a host
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
				$content = $this->token->init();
				if (empty($GLOBALS['egw_info']['user']['apps']['admin']))
				{
					$content['account_id'] = $GLOBALS['egw_info']['user']['account_id'];
				}
			}
		}
		elseif (!empty($content['button']))
		{
			try {
				$button = key($content['button']);
				unset($content['button']);
				switch($button)
				{
					case 'save':
					case 'apply':
						$content['token_limits'] = Api\Auth\Token::apps2limits($content['token_apps']);
						if (empty($content['token_id']))
						{
							$content = Api\Auth\Token::create($content['account_id'] ?: 0, $content['token_valid_until'], $content['token_remark'],
								$content['token_limits']);
							Api\Framework::refresh_opener(lang('Token created.'),
								self::APP, $this->token->data['token_id'],'add');
							$button = 'apply';  // must not close window to show token
						}
						elseif (!$this->token->save($content))
						{
							Api\Framework::refresh_opener(lang('Token saved.'),
								self::APP, $this->token->data['token_id'],'edit');
							$content = array_merge($content, $this->token->data);
						}
						else
						{
							throw new \Exception(lang('Error storing token!'));
						}
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
				}
			}
			catch(\Exception $e) {
				Api\Framework::message($e->getMessage(), 'error');
			}
		}
		$content['token_apps'] = Api\Auth\Token::limits2apps($content['token_limits']);
		if (empty($content['account_id'])) $content['account_id'] = '';
		$readonlys = [
			'button[delete]' => !$content['token_id'],
			'account_id' => empty($GLOBALS['egw_info']['user']['apps']['admin']),
		];
		$tmpl = new Api\Etemplate(self::APP.'.token.edit');
		$tmpl->exec(self::APP.'.'.self::class.'.edit', $content, [], $readonlys, $content, 2);
	}

	/**
	 * Fetch rows to display
	 *
	 * @param array $query
	 * @param array& $rows =null
	 * @param array& $readonlys =null
	 */
	public function get_rows($query, array &$rows=null, array &$readonlys=null)
	{
		$total = $this->token->get_rows($query, $rows, $readonlys);
		foreach($rows as &$row)
		{
			$row['token_apps'] = Api\Auth\Token::limits2apps($row['token_limits']);
			if ($row['token_revoked'])
			{
				$row['class'] = 'revoked';
			}
		}
		return $total;
	}

	/**
	 * Index
	 *
	 * @param array $content =null
	 */
	public function index(array $content=null)
	{
		if (!is_array($content) || empty($content['nm']))
		{
			$content = [
				'nm' => [
					'get_rows'       =>	self::APP.'.'.__CLASS__.'.get_rows',
					'no_filter'      => true,	// disable the diverse filters we not (yet) use
					'no_filter2'     => true,
					'no_cat'         => true,
					'order'          =>	'token_id',// IO name of the column to sort after (optional for the sortheaders)
					'sort'           =>	'DESC',// IO direction of the sort: 'ASC' or 'DESC'
					'row_id'         => 'token_id',
					'actions'        => $this->get_actions(),
					'placeholder_actions' => array('add'),
					'add_link'       => Api\Egw::link('/index.php', 'menuaction='.self::APP.'.'.self::class.'.edit'),
				]
			];
		}
		elseif(!empty($content['nm']['action']))
		{
			try {
				Api\Framework::message($this->action($content['nm']['action'],
					$content['nm']['selected'], $content['nm']['select_all']));
			}
			catch (\Exception $ex) {
				Api\Framework::message($ex->getMessage(), 'error');
			}
		}
		$tmpl = new Api\Etemplate(self::APP.'.tokens');
		$tmpl->exec(self::APP.'.'.self::class.'.index', $content, [
			'account_id' => ['0' => lang('All users')]
		], [], ['nm' => $content['nm']]);
	}

	/**
	 * Return actions for cup list
	 *
	 * @param array $cont values for keys license_(nation|year|cat)
	 * @return array
	 */
	protected function get_actions()
	{
		return [
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
	protected function action($action, $selected, $select_all)
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