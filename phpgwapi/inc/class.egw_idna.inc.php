<?php
/**
 * eGroupWare API: egw class to include (and configure (basic)) idna_convert by Matthias Sommerfeld <mso@phlylabs.de>
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage idna_convert
 * @author Klaus Leithoff <kl-AT-stylite.de>
 * @version $Id$
 */

require_once(EGW_API_INC.'/idna_convert/idna_convert.class.php');

/**
 * This class does NOT use anything EGroupware specific, it just calls idna_convert and supports autoloading
 * while matching egw namespace requirements, and switch to idn version 2008 by default
 */
class egw_idna extends idna_convert
{
	function __construct($options = false)
	{
		$this->_idn_version = 2008;      // Can be either 2003 (old, default) or 2008
		// if options is given, the above may be changed according to $options['idn_version']
		parent::__construct($options);
		/*
		if ($idna2==false && (@include_once 'Net/IDNA2.php') != false) {
			_debug_array('Umlautdomains supported (by PEAR)');
			$idna2 = new Net_IDNA2;
		}
		*/
	}

}
