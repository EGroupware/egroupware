<?php
/**
 * API - LDAP server information
 *
 * @link http://www.egroupware.org
 * @author Lars Kneschke <l.kneschke@metaways.de>
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage ldap
 * @version $Id$
 */

define('UNKNOWN_LDAPSERVER',0);
define('OPENLDAP_LDAPSERVER',1);

/**
 * Class to store and retrieve information (eg. supported object classes) of a connected ldap server
 */
class ldapserverinfo
{
	/**
	* @var array $namingContext holds the supported namingcontexts
	*/
	var $namingContext = array();

	/**
	* @var string $version holds the LDAP server version
	*/
	var $version = 2;

	/**
	* @var integer $serverType holds the type of LDAP server(OpenLDAP, ADS, NDS, ...)
	*/
	var $serverType = 0;

	/**
	* @var string $_subSchemaEntry the subschema entry DN
	*/
	var $subSchemaEntry = '';

	/**
	* @var array $supportedObjectClasses the supported objectclasses
	*/
	var $supportedObjectClasses = array();

	/**
	* @var array $supportedOIDs the supported OIDs
	*/
	var $supportedOIDs = array();

	/**
	 * Name of host
	 *
	 * @var string
	 */
	var $host;

	/**
	 * Constructor
	 *
	 * @param string $host
	 */
	function __construct($host)
	{
		$this->host = $host;
	}

	/**
	* gets the version
	*
	* @return integer the supported ldap version
	*/
	function getVersion()
	{
		return $this->version;
	}

	/**
	* sets the namingcontexts
	*
	* @param array $_namingContext the supported namingcontexts
	*/
	function setNamingContexts($_namingContext)
	{
		$this->namingContext = $_namingContext;
	}

	/**
	* sets the type of the ldap server(OpenLDAP, ADS, NDS, ...)
	*
	* @param integer $_serverType the type of ldap server
	*/
	function setServerType($_serverType)
	{
		$this->serverType = $_serverType;
	}

	/**
	* sets the DN for the subschema entry
	*
	* @param string $_subSchemaEntry the subschema entry DN
	*/
	function setSubSchemaEntry($_subSchemaEntry)
	{
		$this->subSchemaEntry = $_subSchemaEntry;
	}

	/**
	* sets the supported objectclasses
	*
	* @param array $_supportedObjectClasses the supported objectclasses
	*/
	function setSupportedObjectClasses($_supportedObjectClasses)
	{
		$this->supportedOIDs = $_supportedObjectClasses;
		$this->supportedObjectClasses = array_flip($_supportedObjectClasses);
	}

	/**
	* sets the version
	*
	* @param integer $_version the supported ldap version
	*/
	function setVersion($_version)
	{
		$this->version = $_version;
	}

	/**
	* checks for supported objectclasses
	*
	* @return bool returns true if the ldap server supports this objectclass
	*/
	function supportsObjectClass($_objectClass)
	{
		if($this->supportedObjectClasses[strtolower($_objectClass)])
		{
			return true;
		}
		return false;
	}
}
