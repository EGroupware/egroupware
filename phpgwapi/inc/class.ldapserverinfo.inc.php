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

	define('UNKNOWN_LDAPSERVER',0);
	define('OPENLDAP_LDAPSERVER',1);

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
		* the constructor for this class
		*/
		/*function ldapserverinfo() {
		}*/
		
		/**
		* gets the version
		*
		* @return integer the supported ldap version
		*/
		function getVersion() {
			return $this->version;
		}

		/**
		* sets the namingcontexts
		*
		* @param array $_namingContext the supported namingcontexts
		*/
		function setNamingContexts($_namingContext) {
			$this->namingContext = $_namingContext;
		}

		/**
		* sets the type of the ldap server(OpenLDAP, ADS, NDS, ...)
		*
		* @param integer $_serverType the type of ldap server
		*/
		function setServerType($_serverType) {
			$this->serverType = $_serverType;
		}

		/**
		* sets the DN for the subschema entry
		*
		* @param string $_subSchemaEntry the subschema entry DN
		*/
		function setSubSchemaEntry($_subSchemaEntry) {
			$this->subSchemaEntry = $_subSchemaEntry;
		}

		/**
		* sets the supported objectclasses
		*
		* @param array $_supportedObjectClasses the supported objectclasses
		*/
		function setSupportedObjectClasses($_supportedObjectClasses) {
			$this->supportedOIDs = $_supportedObjectClasses;
			$this->supportedObjectClasses = array_flip($_supportedObjectClasses);
		}

		/**
		* sets the version
		*
		* @param integer $_version the supported ldap version
		*/
		function setVersion($_version) {
			$this->version = $_version;
		}
		
		/**
		* checks for supported objectclasses
		*
		* @return bool returns true if the ldap server supports this objectclass
		*/
		function supportsObjectClass($_objectClass) {
			if($this->supportedObjectClasses[strtolower($_objectClass)]) {
				return true;
			} else {
				return false;
			}
		}
	}
?>
