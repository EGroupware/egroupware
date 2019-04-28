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
	 *
	 * @param int|str|array $id_data contact-id or array with data
	 */
	function __construct($id_data)
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
				throw Api\Exception\NotFound("Contact '$id_data' not found!");
			}
		}
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
	 * Get session-independent url / sharing link for the contact photo
	 *
	 * @return string|NULL with session-independent url / sharing-link or null if no photo exists
	 */
	function __toString()
	{
		$path = $this->hasPhoto();

		if (empty($path))
		{
			return null;
		}
		// if we got photo, we have to create a temp. file to share
		if ($path[0] !== '/')
		{
			$tmp = tempnam($GLOBALS['egw_info']['server']['temp_dir'], '.jpeg');
			if (!file_put_contents($tmp, $path))
			{
				return null;
			}
			$path = $tmp;
		}
		return Api\Vfs\Sharing::share2link(Api\Vfs\Sharing::create(
			$path,
			Api\Vfs\Sharing::READONLY,
			basename($path),
			array()
		));
	}
}