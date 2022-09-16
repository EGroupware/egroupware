<?php
/**
 * EGroupware API: Contacts photo
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package api
 * @subpackage contacts
 * @copyright (c) 2019 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Contacts;

use EGroupware\Api;

/**
 * Class to handle contact photo, specially create a session-independent url / sharing link of the photo of a contact or account
 *
 * @todo move all photo handling to this class
 */
class Photo
{
	/**
	 * Contact data
	 *
	 * @var array
	 */
	protected $contact;

	/**
	 * VFS path of photo
	 *
	 * @var string
	 */
	protected $path;

	/**
	 * @var bool
	 */
	protected $no_sharing_link=false;

	/**
	 * Contructor
	 *
	 * @param int|string|array $id_data contact-id, "account:$account_id", array with contact-data or email for gravatar-url
	 * @param boolean $no_sharing_link =false true: do NOT generate sharing link, as we currently destroy the EGroupware
	 * session, if the link is from an other EGroupware user
	 */
	function __construct($id_data, $no_sharing_link=false)
	{
		if (is_array($id_data))
		{
			$this->contact = $id_data;
		}
		else
		{
			$contact = new Api\Contacts();

			if (!($this->contact = $contact->read($id_data)))
			{
				$this->contact = $id_data;
				//throw Api\Exception\NotFound("Contact '$id_data' not found!");
			}
		}
		$this->no_sharing_link = $no_sharing_link;
	}

	/**
	 * Get VFS path of photo
	 *
	 * Does not check the photo exists, use hasPhoto for that!
	 *
	 * @return string
	 */
	function vfsPath()
	{
		if (empty($this->path))
		{
			$this->path = Api\Link::vfs_path('addressbook', $this->contact['id'], Api\Contacts::FILES_PHOTO, true);
		}
		return $this->path;
	}

	/**
	 * Get photo of contact instanciated for as string or VFS path
	 *
	 * @return string|null photo-data or VFS path (starting with '/') or null if no photo exists
	 */
	function hasPhoto()
	{
		if (!is_array($this->contact))
		{
			return null;
		}
		if (!empty($this->contact['jpegphoto']))
		{
			return $this->contact['jpegphoto'];
		}
		if (Api\Vfs::file_exists($this->vfsPath()))
		{
			return $this->path;
		}
		return NULL;
	}

	/**
	 * Return URL for anonymous letter-avatar or Gravatar for email-addresses
	 *
	 * @return string
	 */
	function anonLavatar()
	{
		if (!is_array($this->contact))
		{
			return 'https://www.gravatar.com/avatar/'.md5(trim(strtolower($this->contact)));
		}
		return Api\Framework::getUrl(Api\Egw::link('/api/anon_lavatar.php', [
			'firstname' => $this->contact['n_given'],
			'lastname'  => $this->contact['n_family'],
			'id'        => $this->contact['id'],
		]));
	}

	/**
	 * Get session-independent url / sharing link for the contact photo
	 *
	 * @return string|NULL with session-independent url / sharing-link or null if no photo exists
	 */
	function __toString()
	{
		$path = $this->hasPhoto();

		if (empty($path))
		{
			return $this->anonLavatar();
		}
		// do we want a sharing link or not
		if ($this->no_sharing_link)
		{
			return Api\Framework::getUrl(Api\Egw::link('/api/avatar.php', [
				'contact_id' => $this->contact['id'],
			]));
		}
		// to test/debug behavior for accounts in LDAP or AD uncomment the following line
		//if ($path[0] === '/') $path = file_get_contents(Api\Vfs::PREFIX.$path);

		// if we got photo, we have to create a temp. file to share
		if ($path[0] !== '/')
		{
			$tmp = tempnam($GLOBALS['egw_info']['server']['temp_dir'], '.jpeg');
			if (!file_put_contents($tmp, $path))
			{
				return $this->anonLavatar();
			}
			$path = $tmp;
		}
		return Api\Vfs\Sharing::share2link(Api\Vfs\Sharing::create(
			'', $path, Api\Vfs\Sharing::READONLY, basename($path), array()
		));
	}
}