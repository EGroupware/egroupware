<?php
  /**************************************************************************\
  * eGroupWare API - Access Control List                                     *
  * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
  * Security scheme based on ACL design                                      *
  * Copyright (C) 2000, 2001 Dan Kuykendall                                  *
  * -------------------------------------------------------------------------*
  * This library is part of the eGroupWare API                               *
  * http://www.egroupware.org/api                                            * 
  * ------------------------------------------------------------------------ *
  * This library is free software; you can redistribute it and/or modify it  *
  * under the terms of the GNU Lesser General Public License as published by *
  * the Free Software Foundation; either version 2.1 of the License,         *
  * or any later version.                                                    *
  * This library is distributed in the hope that it will be useful, but      *
  * WITHOUT ANY WARRANTY; without even the implied warranty of               *
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     *
  * See the GNU Lesser General Public License for more details.              *
  * You should have received a copy of the GNU Lesser General Public License *
  * along with this library; if not, write to the Free Software Foundation,  *
  * Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            *
  \**************************************************************************/

  /* $Id$ */

	/**
		 * Access Control List System
		 *
		 * This class provides an ACL security scheme.
		 * This can manage rights to 'run' applications, and limit certain features within an application.
		 * It is also used for granting a user "membership" to a group, or making a user have the security equivilance of another user.
		 * It is also used for granting a user or group rights to various records, such as todo or calendar items of another user.
		 * $acl =& CreateObject('phpgwapi.acl',5);  // 5 is the user id
		 * @author Seek3r
		 * @copyright LGPL
		 * @package api
		 * @subpackage accounts
		 * @access public
	 */
	class acl
	{
		/**
		 * @var int $account_id the account-id this class is instanciated for
		 */
		var $account_id = 0;
		/**
		 * @var $account_type 
		 */
		var $account_type;
		/**
		 * @var array $data internal repository with acl rows for the given app and account-id (incl. memberships)
		 */
		var $data = Array();
		/**
		 * @var object/db $db internal copy of the db-object
		 */
		var $db;
		/**
		 * @var string $table_name name of the acl_table
		 */
		var $table_name = 'phpgw_acl';

		/**
		 * ACL constructor for setting account id
		 *
		 * Author: Seek3r <br>
		 * Sets the ID for $acl->account_id. Can be used to change a current instances id as well. <br>
		 * Some functions are specific to this account, and others are generic. <br>
		 * @example acl->acl(5); // 5 is the user id  <br>
		 * @param int $account_id int-the user id
		 */
		function acl($account_id = '')
		{
			$this->db = clone($GLOBALS['egw']->db);
			$this->db->set_app('phpgwapi');

			if ((int)$this->account_id != (int)$account_id)
			{
				$this->account_id = get_account_id((int)$account_id,@$GLOBALS['egw_info']['user']['account_id']);
			}
		}

		function DONTlist_methods($_type='xmlrpc')
		{
			/*
			  This handles introspection or discovery by the logged in client,
			  in which case the input might be an array.  The server always calls
			  this function to fill the server dispatch map using a string.
			*/

			if (is_array($_type))
			{
				$_type = $_type['type'] ? $_type['type'] : $_type[0];
			}

			switch($_type)
			{
				case 'xmlrpc':
				$xml_functions = array(
						'read_repository' => array(
							'function'  => 'read_repository',
							'signature' => array(array(xmlrpcStruct)),
							'docstring' => lang('FIXME!')
						),
						'get_rights' => array(
							'function'  => 'get_rights',
							'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
							'docstring' => lang('FIXME!')

						),
						'list_methods' => array(
							'function'  => 'list_methods',
							'signature' => array(array(xmlrpcStruct,xmlrpcString)),
							'docstring' => lang('Read this list of methods.')
						)
					);
					return $xml_functions;
					break;
				case 'soap':
					return $this->soap_functions;
					break;
				default:
					return array();
					break;
			}
		}

		/**************************************************************************\
		* These are the standard $this->account_id specific functions              *
		\**************************************************************************/

		/**
		 * Read acl records from reposity
		 *
		 * Author: Seek3r <br>
		 * Reads ACL records for $acl->account_id and returns array along with storing it in $acl->data.  <br>
		 * Syntax: array read_repository() <br>
		 * Example1: acl->read_repository(); <br>
		 * Should only be called within this class
		 */
		function read_repository()
		{
			// For some reason, calling this via XML-RPC doesn't call the constructor.
			// Here is yet another work around(tm) (jengo)
			if (!$this->account_id)
			{
				$this->acl();
			}
			$this->db->select($this->table_name,'*',array(
				'acl_account' => array($this->account_id,0) + array_values((array)$this->get_location_list_for_id('phpgw_group', 1, $this->account_id))
			),__LINE__,__FILE__);
			
			$this->data = Array();
			while($this->db->next_record())
			{
				$this->data[] = array(
					'appname'  => $this->db->f('acl_appname'),
					'location' => $this->db->f('acl_location'), 
					'account'  => $this->db->f('acl_account'), 
					'rights'   => $this->db->f('acl_rights')
				);
			}
			return $this->data;
		}

		/**
		 * Read acl records from $acl->data
		 *
		 * Author: Seek3r
		 * @return array all ACL records from $this->data.
		 */
		function read()
		{
			if (!count($this->data))
			{
				$this->read_repository();
			}
			return $this->data;
		}

		/**
		 * Adds ACL record to  the repository of the class
		 *
		 * Adds ACL record to $this->data. 
		 *
		 * @param string $appname default False derives value from $GLOBALS['egw_info']['flags']['currentapp']
		 * @param string $location location
		 * @param int $rights rights
		 * @return array all ACL records from $this->data.
		 */
		function add($appname,$location,$rights)
		{
			if (!$appname) $appname = $GLOBALS['egw_info']['flags']['currentapp'];

			$this->data[] = array(
				'appname'  => $appname, 
				'location' => $location, 
				'account'  => (int) $this->account_id, 
				'rights'   => (int) $rights
			);

			return $this->data;
		}

		/**
		 * Delete ACL record in the repository of the class
		 *
		 * @param string $appname appname or '' for $GLOBALS['egw_info']['flags']['currentapp']
		 * @param string $location location
		 * @return array all ACL records from $this->data.
		 */
		function delete($appname,$location)
		{
			if (!$appname) $appname = $GLOBALS['egw_info']['flags']['currentapp'];

			foreach($this->data as $idx => $value)
			{
				if ($this->data[$idx]['appname'] == $appname && $this->data[$idx]['location'] == $location && $this->data[$idx]['account'] == $this->account_id)
				{
					unset($this->data[$idx]);
				}
			}
			return $this->data;
		}

		/**
		 * save the internal repository or the class
		 *
		 * @return array all ACL records from $this->data.
		 */
		function save_repository()
		{
			$this->db->delete($this->table_name,array(
				'acl_account' => $this->account_id,
			),__LINE__,__FILE__);

			foreach($this->data as $value)
			{
				if ($value['account'] == $this->account_id)
				{
					$this->db->insert($this->table_name,array(
						'acl_appname'  => $value['appname'],
						'acl_location' => $value['location'],
						'acl_account'  => $this->account_id,
						'acl_rights'   => $value['rights'],
					),false,__LINE__,__FILE__);
				}
			}
			return $this->data;
		}

		/**************************************************************************\
		* These are the non-standard $this->account_id specific functions          *
		\**************************************************************************/

		/**
		 * get rights from the class repository (included rights of $this->account_id and all it's memberships)
		 *
		 * @param string $location app location to get rights from
		 * @param string $appname optional defaults to $GLOBALS['egw_info']['flags']['currentapp'];
		 * @return int all rights or'ed together
		 */
		function get_rights($location,$appname = '')
		{
			// For XML-RPC, change this once its working correctly for passing parameters (jengo)
			if (is_array($location))
			{
				$appname  = $location['appname'];
				$location = $location['location'];
			}

			if (!count($this->data))
			{
				$this->read_repository();
			}
			if (!$appname) $appname = $GLOBALS['egw_info']['flags']['currentapp'];

			if (!count($this->data) && $GLOBALS['egw_info']['server']['acl_default'] != 'deny')
			{
				return True;
			}
			$rights = 0;
			foreach($this->data as $idx => $value)
			{
				if ($value['appname'] == $appname)
				{
					if ($value['location'] == $location || $value['location'] == 'everywhere')
					{
						if ($value['rights'] == 0)
						{
							return False;
						}
						$rights |= $value['rights'];
					}
				}
			}
			return $rights;
		}

		/**
		 * check required rights agains the internal repository (included rights of $this->account_id and all it's memberships)
		 *
		 * @param $location app location
		 * @param $required required right to check against
		 * @param $appname optional defaults to currentapp
		 * @return boolean
		 */
		function check($location, $required, $appname = False)
		{
			$rights = $this->get_rights($location,$appname);

			return !!($rights & $required);
		}

		/**
		 * get specific rights for this->account_id for an app location
		 *
		 * @param string $location app location
		 * @param string $appname optional defaults to currentapp
		 * @return int $rights
		 */
		function get_specific_rights($location, $appname = '')
		{
			if (!$appname) $appname = $GLOBALS['egw_info']['flags']['currentapp'];

			if (!count($this->data) && $GLOBALS['egw_info']['server']['acl_default'] != 'deny')
			{
				return True;
			}
			$rights = 0;

			foreach($this->data as $idx => $value)
			{
				if ($value['appname'] == $appname && 
					($value['location'] == $location ||	$value['location'] == 'everywhere') &&
					$value['account'] == $this->account_id)
				{
					if ($value['rights'] == 0)
					{
						return False;
					}
					$rights |= $value['rights'];
				}
			}
			return $rights;
		}

		/**
		 * check specific rights
		 *
		 * @param string $location app location
		 * @param int $required required rights
		 * @param string $appname optional defaults to currentapp
		 * @return boolean
		 */
		function check_specific($location, $required, $appname = '')
		{
			$rights = $this->get_specific_rights($location,$appname);

			return !!($rights & $required);
		}

		/**************************************************************************\
		* These are the generic functions. Not specific to $this->account_id       *
		\**************************************************************************/

		/**
		 * add repository information / rights for app/location/account_id to the database 
		 *
		 * @param string $app appname
		 * @param string $location location
		 * @param int $account_id account id
		 * @param int $rights rights
		 * @return boolean allways true
		 */
		function add_repository($app, $location, $account_id, $rights)
		{
			//echo "<p>acl::add_repository('$app','$location',$account_id,$rights);</p>\n";
			$this->db->insert($this->table_name,array(
				'acl_rights' => $rights,
			),array(
				'acl_appname' => $app,
				'acl_location' => $location,
				'acl_account'  => $account_id,
			),__LINE__,__FILE__);

			return True;
		}

		/**
		 * delete repository information / rights for app/location[/account_id] from the DB
		 *
		 * @param string $app appname
		 * @param string $location location
		 * @param int/boolean $account_id account id, default 0=$this->account_id, or false to delete all entries for $app/$location
		 * @return int number of rows deleted
		 */
		function delete_repository($app, $location, $accountid='')
		{
			static $cache_accountid;

			$where = array(
				'acl_appname'  => $app,
				'acl_location' => $location,
			);
			if ($accountid !== false)
			{
				if(isset($cache_accountid[$accountid]) && $cache_accountid[$accountid])
				{
					$where['acl_account'] = $cache_accountid[$accountid];
				}
				else
				{
					$where['acl_account'] = $cache_accountid[$accountid] = get_account_id($accountid,$this->account_id);
				}
			}
			if ($app == '%' || $app == '%%') unset($where['acl_appname']);

			$this->db->delete($this->table_name,$where,__LINE__,__FILE__);

			return $this->db->affected_rows();
		}

		/**
		 * get application list for an account id
		 *
		 * @param string $location location
		 * @param int $required required rights
		 * @param int $account_id account id defaults to $GLOBALS['egw_info']['user']['account_id'];
		 * @return array/boolean false if there are no matching row in the db, else array with app-names
		 */
		function get_app_list_for_id($location, $required, $accountid = '')
		{
			static $cache_accountid;

			if($cache_accountid[$accountid])
			{
				$account_id = $cache_accountid[$accountid];
			}
			else
			{
				$account_id = get_account_id($accountid,$this->account_id);
				$cache_accountid[$accountid] = $account_id;
			}
			$this->db->select($this->table_name,array('acl_appname','acl_rights'),array(
				'acl_location' => $location,
				'acl_account'  => $account_id,
			),__LINE__,__FILE__);

			$rights = 0;
			$apps = false;
			while ($this->db->next_record())
			{
				if ($this->db->f('acl_rights') == 0)
				{
					return False;
				}
				$rights |= $this->db->f('acl_rights');
				if (!!($rights & $required))
				{
					$apps[] = $this->db->f('acl_appname');
				}
			}
			return $apps;
		}

		/**
		 * get location list for id
		 *
		 * @param string $app app
		 * @param int $required required rights
		 * @param int $accountid optional defaults to $GLOBALS['egw_info']['user']['account_id'];
		 * @return array/boolean false if there are no matching rows in the db or array with location-strings
		 */
		function get_location_list_for_id($app, $required, $accountid = '')
		{
			static $cache_accountid;
			
			if($cache_accountid[$accountid])
			{
				$accountid = $cache_accountid[$accountid];
			}
			else
			{
				$accountid = $cache_accountid[$accountid] = get_account_id($accountid,$this->account_id);
			}
			$this->db->select($this->table_name,'acl_location,acl_rights',array(
				'acl_appname' => $app,
				'acl_account' => $accountid,
			),__LINE__,__FILE__);

			$locations = false;
			while ($this->db->next_record())
			{
				if ($this->db->f('acl_rights') & $required)
				{
					$locations[] = $this->db->f('acl_location');
				}
			}
			return $locations;
		}

		/**
		 * get ids for location
		 *
		 * @param string $location location
		 * @param int $required required rights
		 * @param string $app app optional defaults to $GLOBALS['egw_info']['flags']['currentapp'];
		 * @return boolean/array false if there are no matching rows in the db or array of account-ids
		 */
		function get_ids_for_location($location, $required, $app = '')
		{
			if (!$app) $app = $GLOBALS['egw_info']['flags']['currentapp'];

			$this->db->select($this->table_name,array('acl_account','acl_rights'),array(
				'acl_appname'  => $app,
				'acl_location' => $location,
			),__LINE__,__FILE__);

			$accounts = false;
			while ($this->db->next_record())
			{
				if (!!($this->db->f('acl_rights') & $required))
				{
					$accounts[] = (int) $this->db->f('acl_account');
				}
			}
			return $accounts;
		}

		/**
		 * get the locations for an app (excluding the run location !!!)
		 *
		 * @param string $app app optional defaults to $GLOBALS['egw_info']['flags']['currentapp'];
		 * @return boolean/array false if there are no matching location in the db or array of locations
		 */
		function get_locations_for_app($app='')
		{
			if (!$app) $app = $GLOBALS['egw_info']['flags']['currentapp'];

			$this->db->select($this->table_name,'DISTINCT '.'acl_location',array(
				'acl_appname'  => $app,
			),__LINE__,__FILE__);

			$locations = false;
			while ($this->db->next_record())
			{
				if (($location = $this->db->f(0)) != 'run')
				{
					$locations[] = $location;
				}
			}
			return $locations;
		}

		/**
		 * get a list of applications a user has rights to
		 *
		 * @param int $account_id optional defaults to $GLOBALS['egw_info']['user']['account_id'];
		 * @return boolean/array containing list of apps or false if there are none
		 */
		function get_user_applications($accountid = '')
		{
			static $cache_accountid;

			if($cache_accountid[$accountid])
			{
				$account_id = $cache_accountid[$accountid];
			}
			else
			{
				$account_id = get_account_id($accountid,$this->account_id);
				$cache_accountid[$accountid] = $account_id;
			}
			$memberships = array($account_id);
			foreach((array)$GLOBALS['egw']->accounts->membership($account_id) as $group)
			{
				$memberships[] = $group['account_id'];
			}
			$db2 = clone($this->db);
			$db2->select($this->table_name,array('acl_appname','acl_rights'),array(
				'acl_location' => 'run',
				'acl_account'  => $memberships,
			),__LINE__,__FILE__);

			$apps = false;
			while ($db2->next_record())
			{
				$app = $db2->f('acl_appname');
				if(!isset($apps[$app]))
				{
					$apps[$app] = 0;
				}
				$apps[$app] |= (int) $db2->f('acl_rights');
			}
			return $apps;
		}

		/**
		 * Read the grants other users gave $this->account_id for $app, group ACL is taken into account
		 *
		 * @param string $app optional defaults to $GLOBALS['egw_info']['flags']['currentapp'];
		 * @return array with account-ids (of owners) and granted rights as values
		 */
		function get_grants($app='')
		{
			if (!$app) $app = $GLOBALS['egw_info']['flags']['currentapp'];

			$memberships = array($this->account_id);
			foreach((array)$GLOBALS['egw']->accounts->membership($this->account_id) as $group)
			{
				$memberships[] = $group['account_id'];
			}
			$db2 = clone($this->db);
			$db2->select($this->table_name,array('acl_account','acl_rights'),array(
				'acl_appname'  => $app,
				'acl_location' => $memberships,
			),__LINE__,__FILE__);
			
			$grants = $accounts = Array();
			while ($db2->next_record())
			{
				$grantor = $db2->f('acl_account');
				$rights = $db2->f('acl_rights');

				if(!isset($accounts[$grantor]))
				// cache the group-members for performance
				{
					// if $grantor is a group, get its members
					$members = $this->get_ids_for_location($grantor,1,'phpgw_group');
					if(!$members)
					{
						$accounts[$grantor] = Array($grantor);
						$is_group[$grantor] = False;
					}
					else
					{
						$accounts[$grantor] = $members;
						$is_group[$grantor] = True;
					}
				}
				if(@$is_group[$grantor])
				{
					// Don't allow to override private!
					$rights &= (~ EGW_ACL_PRIVATE);
					if(!isset($grants[$grantor]))
					{
						$grants[$grantor] = 0;
					}
					$grants[$grantor] |= $rights;
					if(!!($rights & EGW_ACL_READ))
					{
						$grants[$grantor] |= EGW_ACL_READ;
					}
				}
				foreach($accounts[$grantor] as $grantors)
				{
					if(!isset($grants[$grantors]))
					{
						$grants[$grantors] = 0;
					}
					$grants[$grantors] |= $rights;
				}
			}
			$grants[$GLOBALS['egw_info']['user']['account_id']] = ~0;

			return $grants;
		}
		
		/**
		 * Deletes all ACL entries for an account (user or group)
		 *
		 * @param int $account_id acount-id
		 */
		function delete_account($account_id)
		{
			if ((int) $account_id)
			{
				$this->db->delete($this->table_name,array(
					'acl_account' => $account_id
				),__LINE__,__FILE__);
				// delete all memberships in account_id (if it is a group)
				$this->db->delete($this->table_name,array(
					'acl_appname' => 'phpgw_group',
					'acl_location' => $account_id,
				),__LINE__,__FILE__);
			}
		}
	} //end of acl class
