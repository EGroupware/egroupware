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
trait UserContext
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
		elseif(is_string($url_or_context))
		{
			$this->check_set_context($url_or_context, true);
		}
	}

	/**
	 * Check if we have no user-context, but an url with a user --> set it as context
	 *
	 * @param $url
	 * @param bool $always_set false (default): only set if we have not context or user in context, true: always set
	 */
	protected function check_set_context($url, $always_set=false)
	{
		if (($always_set || !$this->context || empty(stream_context_get_options($this->context)[Vfs::SCHEME]['user'])) &&
			$url[0] !== '/' && ($account_lid = Vfs::parse_url($url, PHP_URL_USER)))
		{
			$this->user = $account_lid;
		}
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
				return $this->context ? stream_context_get_options($this->context)[Vfs::SCHEME]['user'] : null;
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
					if ($this->context)
					{
						stream_context_set_option($this->context, $options);
					}
					else
					{
						$this->context = stream_context_create($options);
					}
				}
				break;
		}
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
