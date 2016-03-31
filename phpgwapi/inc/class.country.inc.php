<?php
/**
 * EGroupware API - Country codes
 *
 * @link http://www.egroupware.org
 * @author Mark Peters <skeeter@phpgroupware.org>
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage country
 * @access public
 * @version $Id$
 */

use EGroupware\Api;

/**
 * 2-digit ISO 3166 Country codes
 *
 * All methods are static now, no need to instanciate it via $GLOBALS['egw']->country->method(),
 * just use Api\Country::method().
 *
 * @see http://www.iso.ch/iso/en/prods-services/iso3166ma/02iso-3166-code-lists/list-en1.html
 * @see https://github.com/datasets/country-list
 */
class country extends Api\Country
{
	/**
	 * Selectbox for country-selection
	 *
	 * @deprecated use html::select with country_array
	 * @param string $code 2-letter iso country-code
	 * @param string $name ='country'
	 * @return string
	 */
	public static function form_select($code,$name='country')
	{
		return html::select($name, strtoupper($code), self::$country_array);
	}
}
