<?php
/**
 * EGroupware API - LDAP server information
 *
 * @link http://www.egroupware.org
 * @author Lars Kneschke <l.kneschke@metaways.de>
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage ldap
 */

namespace EGroupware\Api\Ldap;

/**
 * Class to store and retrieve information (eg. supported object classes) of a connected ldap server
 */
class ServerInfo
{
	/**
	 * Unknown LDAP server
	 */
	const UNKNOWN = 0;
	/**
	 * OpenLDAP server
	 */
	const OPENLDAP = 1;
	/**
	 * Samba4 Active Directory server
	 */
	const SAMBA4 = 2;
	/**
	 * Windows Active Directory server
	 */
	const WINDOWS_AD = 4;

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
	 * @var array OIDs of supported controls LDAP_CONTROL_*
	 */
	var $suportedControl = [];

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
	 * @param ?bool $windows_ad true: check for windows AD, false: check for Samba4, null: check of any AD
	 * @return bool
	 */
	function activeDirectory($windows_ad=null)
	{
		return !isset($windows_ad) ? in_array($this->serverType, [self::WINDOWS_AD, self::SAMBA4], true) :
			$this->serverType === ($windows_ad ? self::WINDOWS_AD : self::SAMBA4);
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
	 * sets the supported objectclasses
	 *
	 * @param array $_supportedControl LDAP_CONTROL_* values
	 */
	function setSupportedControl(array $_supportedControl)
	{
		unset($_supportedControl['count']);
		$this->suportedControl = $_supportedControl;
	}

	/**
	 * Check if given (multiple) LDAP_CONTROL_* args are (all) supported
	 *
	 * @param int $control LDAP_CONTROL_* value(s)
	 * @return boolean
	 */
	function supportedControl($control)
	{
		return count(array_intersect(func_get_args(), $this->suportedControl)) === func_num_args();
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
	 * @return self
	 */
	public static function get($ds, $host, $version=3)
	{
		$filter='(objectclass=*)';
		$justthese = array('structuralObjectClass','namingContexts','supportedLDAPVersion','subschemaSubentry','vendorname','supportedControl','forestFunctionality');
		if(($sr = @ldap_read($ds, '', $filter, $justthese)))
		{
			if(($info = ldap_get_entries($ds, $sr)))
			{
				$ldapServerInfo = new ServerInfo($host);
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
							$ldapServerType = self::OPENLDAP;
							break;
						default:
							$ldapServerType = self::UNKNOWN;
							break;
					}
					$ldapServerInfo->setServerType($ldapServerType);
				}
				// Check for ActiveDirectory by forestFunctionality and set Samba4 if vendorName includes samba
				if(!empty($info[0]['forestfunctionality'][0]))
				{
					$ldapServerInfo->setServerType(!empty($info[0]['vendorname']) && stripos($info[0]['vendorname'][0], 'samba') !== false ?
						self::SAMBA4 : self::WINDOWS_AD);
				}

				// check for subschema entry dn
				if($info[0]['subschemasubentry'])
				{
					$subschemasubentry = $info[0]['subschemasubentry'][0];
					$ldapServerInfo->setSubSchemaEntry($subschemasubentry);
				}

				if (!empty($info[0]['supportedcontrol']) && is_array($info[0]['supportedcontrol']))
				{
					$ldapServerInfo->setSupportedControl($info[0]['supportedcontrol']);
				}

				// create list of supported objetclasses
				if(!empty($subschemasubentry))
				{
					$filter='(objectclass=*)';
					$justthese = array('objectClasses');

					if(($sr = ldap_read($ds, $subschemasubentry, $filter, $justthese)))
					{
						if(($info = ldap_get_entries($ds, $sr)))
						{
							if($info[0]['objectclasses']) {
								for($i=0; $i<$info[0]['objectclasses']['count']; $i++)
								{
									$matches = null;
									if(preg_match('/^\( (.*) NAME \'(\w*)\' /', $info[0]['objectclasses'][$i], $matches))
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
