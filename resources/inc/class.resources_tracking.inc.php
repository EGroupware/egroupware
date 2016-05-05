<?php
/**
 * Resources - history & notifications
 *
 * @author Nathan Gray
 * @copyright 2012 Nathan Gray
 * @package resources
 * @sub-package history
 * @see Api\Storage\Tracking
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;

/**
 * Resources - tracking object for history
 */
class resources_tracking extends Api\Storage\Tracking
{

	
	public function __construct() {
		$this->appname = 'resources';
		$this->id_field = 'res_id';

		$this->field2history = array(
                        'res_id'        => 'res_id',
                        'name'          => 'name',
                        'short_description'     => 'short_description',
                        'cat_id'        => 'cat_id',
                        'quantity'      => 'quantity',
                        'useable'       => 'useable',
                        'location'      => 'location',
                        'storage_info'  => 'storage_info',
                        'bookable'      => 'bookable',
                        'buyable'       => 'buyable',
                        'prize'         => 'prize',
                        'long_description'      => 'long_description',
                        'inventory_number'      => 'inventory_number',
                        'accessory_of'  => 'accessory_of'
		);
		parent::__construct($this->appname);
	}
}
