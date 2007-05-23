<?php
/**
 * InfoLog - Link-registry
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package infolog
 * @copyright (c) 2003-6 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */
	
include_once(EGW_INCLUDE_ROOT.'/infolog/inc/class.boinfolog.inc.php');

/**
 * This class returns the link-registry for infolog
 *
 * To prevent an invinit recursion, it has to be outside the boinfolog class, 
 * which itself instanciats the link class by default.
 */
class infolog_link_registry
{
	function search_link($location)
	{
		$bo =& new boinfolog(0,false);	// false = dont instanciate the link class
		
		return $bo->search_link($location);
	}
}