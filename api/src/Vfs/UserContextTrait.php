<?php
/**
 * EGroupware API: VFS - Trait to store user / account_id in stream context
 *
 * @link https://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2020 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 */

namespace EGroupware\Api\Vfs;

use EGroupware\Api\Vfs;
use EGroupware\Api;

/**
 * Trait to store user / account_id in stream context
 *
 * Used by Vfs and SqlFS stream-wrapper.
 *
 * @property int $user user / account_id stored in context
 */
trait UserContextTrait
{
	/**
	 * optional context param when opening the stream, null if no context passed
	 *
	 * @var resource
	 */
	public $context;

	/**
	 * Contructor to set context/user incl. from user in url or passed in context
	 *
	 * @param resource|string|null $url_or_context url with user or context to set
	 */
	public function __construct($url_or_context=null)
	{
		if (is_resource($url_or_context))
		{
			$this->context = $url_or_context;
		}
		else
		{
			if (!isset($this->context))	// PHP set's it before constructor is called!
			{
				$this->context = stream_context_get_default();
			}
			// if context set by PHP contains no user, set user from our default context (Vfs::$user)
			elseif (empty(stream_context_get_options($this->context)[Vfs::SCHEME]['user']))
			{
				stream_context_set_option($this->context, stream_context_get_options(stream_context_get_default()));
			}

			if (is_string($url_or_context))
			{
				$this->check_set_context($url_or_context);
			}
		}

		// if we have no user set, set the current one / Api\Vfs::$user
		if (empty($this->user) && !empty(Api\Vfs::$user))
		{
			$this->user = Api\Vfs::$user;
		}
	}

	/**
	 * Check if we have an url with a user --> set it as context
	 *
	 * @param $url
	 */
	protected function check_set_context($url)
	{
		if ($url[0] !== '/' && ($account_lid = Vfs::parse_url($url, PHP_URL_USER)))
		{
			$this->user = $account_lid;
		}
	}

	/**
	 * The stream_wrapper interface checks is_{readable|writable|executable} against the webservers uid,
	 * which is wrong in case of our vfs, as we use the current users id and memberships
	 *
	 * @param string $path path
	 * @param int $check mode to check: one or more or'ed together of: 4 = Vfs::READABLE,
	 * 	2 = Vfs::WRITABLE, 1 = Vfs::EXECUTABLE
	 * @param array|boolean $stat =null stat array or false, to not query it again
	 * @return boolean
	 */
	function check_access($path, $check, $stat=null)
	{
		if (Vfs::$is_root)
		{
			return true;
		}

		// throw exception if stat array is used insead of path, can be removed soon
		if (is_array($path))
		{
			throw new Api\Exception\WrongParameter('path has to be string, use check_access($path,$check,$stat=null)!');
		}

		// if we have no $stat, delegate whole check to vfs stream-wrapper to correctly deal with shares / effective user-ids
		if (is_null($stat))
		{
			$stat = $this->url_stat($path, 0);
		}
		//error_log(__METHOD__."(path=$path||stat[name]={$stat['name']},stat[mode]=".sprintf('%o',$stat['mode']).",$check)");

		if (!$stat)
		{
			//error_log(__METHOD__."(path=$path||stat[name]={$stat['name']},stat[mode]=".sprintf('%o',$stat['mode']).",$check) no stat array!");
			return false;	// file not found
		}

		// only vfs stream-wrapper sets $stat['url'], use given url instead
		if (!isset($stat['url']) && $path[0] !== '/')
		{
			$stat['url'] = $path;
		}

		// if we check writable and have a readonly mount --> return false, as backends dont know about r/o url parameter
		if ($check == Vfs::WRITABLE && Vfs\StreamWrapper::url_is_readonly($stat['url'] ?? null))
		{
			//error_log(__METHOD__."(path=$path, check=writable, ...) failed because mount is readonly");
			return false;
		}

		// check if we use an EGroupwre stream wrapper, or a stock php one
		// if it's not an EGroupware one, we can NOT use uid, gid and mode!
		if (($scheme = Vfs::parse_url($stat['url'] ?? null, PHP_URL_SCHEME)) && !(class_exists(Vfs::scheme2class($scheme))))
		{
			switch($check)
			{
				case Vfs::READABLE:
					return is_readable($stat['url']);
				case Vfs::WRITABLE:
					return is_writable($stat['url']);
				case Vfs::EXECUTABLE:
					return is_executable($stat['url']);
			}
		}

		// check if other rights grant access
		if (($stat['mode'] & $check) == $check)
		{
			//error_log(__METHOD__."(path=$path||stat[name]={$stat['name']},stat[mode]=".sprintf('%o',$stat['mode']).",$check) access via other rights!");
			return true;
		}

		// check if there's owner access and we are the owner
		if (($stat['mode'] & ($check << 6)) == ($check << 6) && $stat['uid'] && $stat['uid'] == $this->user)
		{
			//error_log(__METHOD__."(path=$path||stat[name]={$stat['name']},stat[mode]=".sprintf('%o',$stat['mode']).",$check) access via owner rights!");
			return true;
		}
		// check if there's a group access and we have the right membership
		if (($stat['mode'] & ($check << 3)) == ($check << 3) && $stat['gid'])
		{
			if (($memberships = Api\Accounts::getInstance()->memberships($this->user, true)) && in_array(-abs($stat['gid']), $memberships))
			{
				//error_log(__METHOD__."(path=$path||stat[name]={$stat['name']},stat[mode]=".sprintf('%o',$stat['mode']).",$check) access via group rights!");
				return true;
			}
		}

		// check extended acls (only if path given)
		$ret = method_exists($this, 'check_extended_acl') && $path && $this->check_extended_acl($stat['url'] ?? $path, $check);

		//error_log(__METHOD__."(path=$path||stat[name]={$stat['name']},stat[mode]=".sprintf('%o',$stat['mode']).",$check) ".($ret ? 'backend extended acl granted access.' : 'no access!!!'));
		return $ret;
	}

	/**
	 * @param string $name
	 * @return mixed|null
	 */
	public function __get($name)
	{
		switch($name)
		{
			case 'user':
				return $this->context ? stream_context_get_options($this->context)[Vfs::SCHEME]['user'] : Vfs::$user;
		}
		return null;
	}

	/**
	 * @param string $name
	 * @param mixed $value
	 */
	public function __set($name, $value)
	{
		switch($name)
		{
			case 'user':
				if (!is_int($value) && is_string($value) && !is_numeric($value))
				{
					$value = Api\Accounts::getInstance()->name2id($value);
				}
				if ($value)
				{
					$options = [
						Vfs::SCHEME => ['user' => (int)$value]
					];
					// do NOT overwrite default context
					if ($this->context && $this->context !== stream_context_get_default())
					{
						stream_context_set_option($this->context, $options);
					}
					else
					{
						$this->context = stream_context_create($options);
					}
				}
				else
				{
					$this->context = stream_context_create();
				}
				break;
		}
	}

	/**
	 * Get user context for given url, eg. to use with regular stream functions
	 *
	 * @param string $url
	 * @param array $extra =[] addtional context options
	 * @return resource context with user, plus optional extra options
	 */
	public static function userContext($url, array $extra=[])
	{
		if (($user = Vfs::parse_url($url, PHP_URL_USER)) &&
			($account_id = Api\Accounts::getInstance()->name2id($user)) &&
			($account_id != Vfs::$user) || $extra)	// never set extra options on default context!
		{
			$context = stream_context_create(array_merge_recursive([Vfs::SCHEME => ['user' => (int)$account_id ?: Vfs::$user]], $extra));
		}
		else
		{
			$context = stream_context_get_default();
		}
		return $context;
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	public function __isset($name)
	{
		return $this->__get($name) !== null;
	}
}