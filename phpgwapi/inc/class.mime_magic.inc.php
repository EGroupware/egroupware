<?php
/**
 * EGroupware API - Mime Magic
 *
 * Originally taken from the Horde Framework http://horde.org
 *
 * Copyright 1999-2003 Anil Madhavapeddy <anil@recoil.org>
 * Copyright 2002-2003 Michael Slusarz <slusarz@bigworm.colorado.edu>
 * Copyright 2003 Free Software Foundation, Inc.
 *
 * Generated: 12/04/14 00:15:12 by slusarz on bigworm.curecanti.org
 *
 * Ported to phpGroupWare by Dave Hall - dave.hall@mbox.com.au
 * Note: this class was relicensed as GPL by Dave Hall - all mods GPL
 *
 * @link http://www.egroupware.org
 * @package API
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;

/**
 * Mime Magic class
 *
 * All methods can be called static, no need to instanciate the class.
 *
 * @deprecated use Api\MimeMagic
 */
class mime_magic extends Api\MimeMagic
{
	/**
	 *  temporary fix for apps using the old name
	 *
	 * @deprecated use filename2mime
	 */
	public static function filename2mine($filename)
	{
		return self::filename2mime($filename);
	}

	/**
	 * @deprecated use static parameter Api\MimeMagi::$mime_extension_map
	 * @return array
	 */
	public static function get_mime_ext_map()
	{
		return self::$mime_extension_map;
	}

	/**
	 * @deprecated use static parameter Api\MimeMagi::$mime_magic_file
	 * @return array
	 */
	public static function get_mime_magic_file()
	{
		return self::$mime_magic_file;
	}
}
