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
define('SAMBA4_LDAPSERVER',2);

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

	/**
	 * Query given ldap connection for available information
	 *
	 * @param resource $ds
	 * @param string $host
	 * @param int $version 2 or 3
	 * @return ldapserverinfo
	 */
	public static function get($ds, $host, $version=3)
	{
		$filter='(objectclass=*)';
		$justthese = array('structuralObjectClass','namingContexts','supportedLDAPVersion','subschemaSubentry','vendorname');
		if(($sr = @ldap_read($ds, '', $filter, $justthese)))
		{
			if($info = ldap_get_entries($ds, $sr))
			{
				$ldapServerInfo = new ldapserverinfo($host);
				$ldapServerInfo->setVersion($version);

				// check for naming contexts
				if($info[0]['namingcontexts'])
				{
					for($i=0; $i<$info[0]['namingcontexts']['count']; $i++)
					{
						$namingcontexts[] = $info[0]['namingcontexts'][$i];
					}
					$ldapServerInfo->setNamingContexts($namingcontexts);
				}

				// check for ldap server type
				if($info[0]['structuralobjectclass'])
				{
					switch($info[0]['structuralobjectclass'][0])
					{
						case 'OpenLDAProotDSE':
							$ldapServerType = OPENLDAP_LDAPSERVER;
							break;
						default:
							$ldapServerType = UNKNOWN_LDAPSERVER;
							break;
					}
					$ldapServerInfo->setServerType($ldapServerType);
				}
				if ($info[0]['vendorname'] && stripos($info[0]['vendorname'][0], 'samba') !== false)
				{
					$ldapServerInfo->setServerType(SAMBA4_LDAPSERVER);
				}

				// check for subschema entry dn
				if($info[0]['subschemasubentry'])
				{
					$subschemasubentry = $info[0]['subschemasubentry'][0];
					$ldapServerInfo->setSubSchemaEntry($subschemasubentry);
				}

				// create list of supported objetclasses
				if(!empty($subschemasubentry))
				{
					$filter='(objectclass=*)';
					$justthese = array('objectClasses');

					if($sr=ldap_read($ds, $subschemasubentry, $filter, $justthese))
					{
						if($info = ldap_get_entries($ds, $sr))
						{
							if($info[0]['objectclasses']) {
								for($i=0; $i<$info[0]['objectclasses']['count']; $i++)
								{
									$pattern = '/^\( (.*) NAME \'(\w*)\' /';
									if(preg_match($pattern, $info[0]['objectclasses'][$i], $matches))
									{
										#_debug_array($matches);
										if(count($matches) == 3)
										{
											$supportedObjectClasses[$matches[1]] = strtolower($matches[2]);
										}
									}
								}
								$ldapServerInfo->setSupportedObjectClasses($supportedObjectClasses);
							}
						}
					}
				}
			}
		}
		return $ldapServerInfo;
	}
}
