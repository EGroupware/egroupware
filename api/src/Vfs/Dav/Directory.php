<?php
/**
 * EGroupware API: VFS directory for use with SabreDAV
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <rb@stylitede>
 * @copyright (c) 2015 by Ralf Becker <rb@stylite.de>
 * @version $Id$
 */

namespace EGroupware\Api\Vfs\Dav;

use Sabre\DAV;
use EGroupware\Api\Vfs;

/**
 * VFS directory for use with SabreDAV
 */
class Directory extends DAV\FS\Directory
{
	/**
	 * VFS path without prefix / vfs schema
	 *
	 * @var string
	 */
	protected $vfs_path;

	/**
	 * Constructor
	 *
	 * @param string $path vfs path without prefix
	 */
	function __construct($path)
	{
		//error_log(__METHOD__."('$path')");
		$this->vfs_path = rtrim($path, '/');

		parent::__construct(Vfs::PREFIX.$path);
	}

	/**
	 * Returns the name of the node
	 *
	 * We override this method to remove url-decoding required by EGroupware VFS
	 *
	 * @return string
	 */
	function getName()
	{
		return Vfs::decodePath(parent::getName());
	}

	/**
	 * Returns a specific child node, referenced by its name
	 *
	 * This method must throw DAV\Exception\NotFound if the node does not
	 * exist.
	 *
	 * @param string $name
	 * @throws DAV\Exception\NotFound
	 * @return DAV\INode
	 */
	function getChild($name)
	{
		//error_log(__METHOD__."('$name') this->path=$this->path, this->vfs_path=$this->vfs_path");
		$path = $this->vfs_path . '/' . $name;
		$vfs_path = $this->vfs_path . '/' . Vfs::encodePathComponent($name);

		if (!Vfs::file_exists($vfs_path)) throw new DAV\Exception\NotFound('File with name ' . $path . ' could not be located');

		if (Vfs::is_dir($vfs_path))
		{
			return new Directory($vfs_path);
		}
		else
		{
			return new File($vfs_path);
		}
	}

	/**
	 * Returns available diskspace information
	 *
	 * @return array [ available-space, free-space ]
	 */
	function getQuotaInfo()
	{
		return [ false, false ];
	}
}
