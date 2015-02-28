<?php
/**
 * EGroupware API: VFS file for use with SabreDAV
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
 * VFS file for use with SabreDAV
 */
class File extends DAV\FS\File
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
		$this->vfs_path = $path;

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
	 * Returns the ETag for a file
	 *
	 * An ETag is a unique identifier representing the current version of the file. If the file changes, the ETag MUST change.
	 * The ETag is an arbitrary string, but MUST be surrounded by double-quotes.
	 *
	 * Return null if the ETag can not effectively be determined
	 *
	 * @return mixed
	 */
	function getETag()
	{
		if (($stat = Vfs::url_stat($this->vfs_path, STREAM_URL_STAT_QUIET)))
		{
			return '"'.$stat['ino'].':'.$stat['mtime'].':'.$stat['size'].'"';
		}
		return null;
	}

	/**
	 * Returns the mime-type for a file
	 *
	 * If null is returned, we'll assume application/octet-stream
	 *
	 * @return mixed
	 */
	function getContentType()
	{
		return Vfs::mime_content_type($this->vfs_path);
	}
}