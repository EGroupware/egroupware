<?php
/**
 * eGroupWare editable Templates - Example media database (et_media)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage et_media
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

include_once(EGW_INCLUDE_ROOT . '/etemplate/inc/class.so_sql.inc.php');

/**
* Business object for et_media
*/
class bo_et_media extends so_sql
{
	/**
	 * Availible media types
	 *
	 * @var array
	 */
	var $types = array(
		''      => 'Select one ...',
		'cd'    => 'Compact Disc',
		'dvd'   => 'DVD',
		'book'  => 'Book',
		'video' => 'Video Tape'
	);
	/**
	 * Constructor initialising so_sql
	 *
	 * @return so_et_media
	 */
	function bo_et_media()
	{
		$this->so_sql('et_media','egw_et_media');
		$this->empty_on_write = "''";
	}
}
