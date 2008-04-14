<?php
/**
 * API - Interapplicaton links BO layer
 *
 * Links have two ends each pointing to an entry, each entry is a double:
 * 	 - app   app-name or directory-name of an egw application, eg. 'infolog'
 * 	 - id    this is the id, eg. an integer or a tupple like '0:INBOX:1234'
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage link
 * @version $Id$
 */

/**
 * Generalized linking between entries of eGroupware apps - BO layer
 * 
 * @deprecated use egw_link class with it's static methods instead
 */
class bolink extends egw_link
{
	/**
	 * @deprecated use egw_link::VFS_APPNAME
	 */
	var $vfs_appname = egw_link::VFS_APPNAME;
	/**
	 * @deprecated use solink::TABLE
	 */
	var $link_table = solink::TABLE;
	
	/**
	 * Overwrite private constructor of egw_links, to allow (depricated) instancated usage
	 *
	 */
	function __construct()
	{
		error_log('Call to depricated bolink class from '.function_backtrace(1));
	}
}