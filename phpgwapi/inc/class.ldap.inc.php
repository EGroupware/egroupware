<?php
	/**************************************************************************\
	* eGroupWare API - Accounts manager for LDAP                               *
	* This file written by Lars Kneschke <l.kneschke@metaways.de>              *
	* View and manipulate contact records using LDAP                           *
	* ------------------------------------------------------------------------ *
	* This library is part of the eGroupWare API                               *
	* http://www.egroupware.org/api                                            *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; version 2 of the License.                     *
	\**************************************************************************/
	
	/* $Id$ */
	
	include_once(EGW_INCLUDE_ROOT.'/phpgwapi/inc/class.ldapserverinfo.inc.php');

	/*!
	 @class contacts
	 @abstract Contact List System
	 @discussion Author: jengo/Milosch <br>
	 This class provides a contact database scheme. <br>
	 It attempts to be based on the vcard 2.1 standard, with mods as needed to make for more reasonable sql storage. <br>
	 The LDAP schema used here may require installation of schema files available in the phpgwapi/doc/ldap dir.
	 Please see the README file there.
	 Syntax: CreateObject('phpgwapi.contacts'); <br>
	 Example1: $contacts = CreateObject('phpgwapi.contacts');
	*/
	class ldap
	{
		/**
		* @var resource $ds holds the LDAP link identifier
		*/
		var $ds;
		
		/**
		* @var array $ldapServerInfo holds the detected information about the different ldap servers
		*/
		var $ldapServerInfo;
		
		/**
		 * the constructor for this class
		 */
		function ldap() {
			$this->restoreSessionData();
		}
		
		/**
		 * escapes a string for use in searchfilters meant for ldap_search.
		 *
		 * Escaped Characters are: '*', '(', ')', ' ', '\', NUL
		 * It's actually a PHP-Bug, that we have to escape space.
		 * For all other Characters, refer to RFC2254.
		 * @param $string either a string to be escaped, or an array of values to be escaped
		 */
		function getLDAPServerInfo($_host)
		{
			if(is_a($this->ldapServerInfo[$_host], 'ldapserverinfo')) {
				return $this->ldapServerInfo[$_host];
			}
			
			return false;
		}

		/**
		 * escapes a string for use in searchfilters meant for ldap_search.
		 *
		 * Escaped Characters are: '*', '(', ')', ' ', '\', NUL
		 * It's actually a PHP-Bug, that we have to escape space.
		 * For all other Characters, refer to RFC2254.
		 * 
		 * @param string/array $string either a string to be escaped, or an array of values to be escaped
		 */
		function quote($string)
		{
			return str_replace(array('\\','*','(',')','\0',' '),array('\\\\','\*','\(','\)','\\0','\20'),$string);
		}

		/**
		 * connect to the ldap server and return a handle
		 *
		 * @param $host ldap host
		 * @param $dn ldap dn
		 * @param $passwd ldap pw
		 */
		function ldapConnect($host='', $dn='', $passwd='')
		{
			if(!function_exists('ldap_connect'))
			{
				/* log does not exist in setup(, yet) */
				if(is_object($GLOBALS['egw']->log))
				{
					$GLOBALS['egw']->log->message('F-Abort, LDAP support unavailable');
					$GLOBALS['egw']->log->commit();
				}

				printf('<b>Error: LDAP support unavailable</b><br>',$host);
				return False;
			}
			if(!$host)
			{
				$host = $GLOBALS['egw_info']['server']['ldap_host'];
			}

			if(!$dn)
			{
				$dn = $GLOBALS['egw_info']['server']['ldap_root_dn'];
			}

			if(!$passwd)
			{
				$passwd = $GLOBALS['egw_info']['server']['ldap_root_pw'];
			}

			// connects to ldap server
			if(!$this->ds = ldap_connect($host))
			{
				/* log does not exist in setup(, yet) */
				if(is_object($GLOBALS['egw']->log))
				{
					$GLOBALS['egw']->log->message('F-Abort, Failed connecting to LDAP server');
					$GLOBALS['egw']->log->commit();
				}

				printf("<b>Error: Can't connect to LDAP server %s!</b><br>",$host);
				echo function_backtrace(1);
				return False;
			}

			if(!isset($this->ldapServerInfo[$host])) {
				//print "no ldap server info found<br>";
				if (!($ldapbind = @ldap_bind($this->ds, '', '')))
				{
					// try with version 3 ;-)
					ldap_set_option($this->ds, LDAP_OPT_PROTOCOL_VERSION, 3);
					$ldapbind = ldap_bind($this->ds, '', '');
				}
				$filter='(objectclass=*)';
				$justthese = array('structuralObjectClass','namingContexts','supportedLDAPVersion','subschemaSubentry');
				
				if(($sr = @ldap_read($this->ds, '', $filter, $justthese))) {
					if($info = ldap_get_entries($this->ds, $sr)) {

						$ldapServerInfo = new ldapserverinfo();
		
						// check for supported ldap version
						if($info[0]['supportedldapversion']) {
							for($i=0; $i<$info[0]['supportedldapversion']['count']; $i++) {
								$supportedVersion = ($supportedVersion < $info[0]['supportedldapversion'][$i] ? $info[0]['supportedldapversion'][$i] : $supportedVersion);
							}
							$ldapServerInfo->setVersion($supportedVersion);
						}
						
						// check for naming contexts
						if($info[0]['namingcontexts']) {
							for($i=0; $i<$info[0]['namingcontexts']['count']; $i++) {
								$namingcontexts[] = $info[0]['namingcontexts'][$i];
							}
							$ldapServerInfo->setNamingContexts($namingcontexts);
						}

						// check for ldap server type
						if($info[0]['structuralobjectclass']) {
							switch($info[0]['structuralobjectclass'][0]) {
								case 'OpenLDAProotDSE':
									$ldapServerType = OPENLDAP_LDAPSERVER;
									break;
								default:
									$ldapServerType = UNKNOWN_LDAPSERVER;
									break;
							}
							$ldapServerInfo->setServerType($ldapServerType);
						}
						
						// check for subschema entry dn
						if($info[0]['subschemasubentry']) {
							$subschemasubentry = $info[0]['subschemasubentry'][0];
							$ldapServerInfo->setSubSchemaEntry($subschemasubentry);
						}
						
						// create list of supported objetclasses
						if(!empty($subschemasubentry)) {
							$filter='(objectclass=*)';
							$justthese = array('objectClasses');
							
							if($sr=ldap_read($this->ds, $subschemasubentry, $filter, $justthese)) {
								if($info = ldap_get_entries($this->ds, $sr)) {
									if($info[0]['objectclasses']) {
										for($i=0; $i<$info[0]['objectclasses']['count']; $i++) {
											$pattern = '/^\( (.*) NAME \'(\w*)\' /';
											if(preg_match($pattern, $info[0]['objectclasses'][$i], $matches)) {
												if(count($matches) == 3) {
													$supportedObjectClasses[$matches[1]] = strtolower($matches[2]);
												}
											}
										}
										$ldapServerInfo->setSupportedObjectClasses($supportedObjectClasses);
									}
								}
							}
						}
						$this->ldapServerInfo[$host] = $ldapServerInfo;
					}
				} else {
					$this->ldapServerInfo[$host] = false;
				}
				$this->saveSessionData();
			} else {
				$ldapServerInfo = $this->ldapServerInfo[$host];
			}

			if(is_a($ldapServerInfo, 'ldapserverinfo') && $ldapServerInfo->getVersion() > 2) {
				ldap_set_option($this->ds, LDAP_OPT_PROTOCOL_VERSION, 3);
			}

			if(!ldap_bind($this->ds,$dn,$passwd))
			{
				if(is_object($GLOBALS['egw']->log))
				{
					$GLOBALS['egw']->log->message('F-Abort, Failed binding to LDAP server');
					$GLOBALS['egw']->log->commit();
				}

				printf("<b>Error: Can't bind to LDAP server: %s!</b><br>",$dn);
				echo function_backtrace(1);
				return False;
			}

			return $this->ds;
		}

		/**
		 * disconnect from the ldap server
		 */
		function ldapDisconnect() 
		{
			if(is_resource($this->ds)) 
			{
				ldap_unbind($this->ds);
			}
		}
		
		/**
		 * restore the session data
		 */
		function restoreSessionData() 
		{
			if (is_object($GLOBALS['egw']->session))	// no availible in setup
			{
				$this->ldapServerInfo = $GLOBALS['egw']->session->appsession('ldapServerInfo');
			}
		}
		/**
		 * save the session data
		 */
		function saveSessionData() 
		{
			if (is_object($GLOBALS['egw']->session))	// no availible in setup
			{
				$GLOBALS['egw']->session->appsession('ldapServerInfo','',$this->ldapServerInfo);
			}
		}
		                                                        
	}
?>
