<?php

//$GLOBALS['phpgw_info']['flags']['currentapp'] = 1; //need to replace with real default
//$GLOBALS['phpgw_info']['user']['userid'] = 1; //need to replace with real default

	class acl2
	{
		var $account_id;
		var $host_id;
		var $app_id;
		var $memberships = Array(0=>1); //group 0 is for all users
		var $memberships_sql = '0'; //group 0 is for all users
		var $rights_cache = Array();
		var $masks_cache = Array();
		var $previous_location; // used for inheritance
		var $db;

		/*************************************************************************\
		* These lines load up the templates class and set some default values     *
		\*************************************************************************/
		function acl2()
		{
			$expected_args[0] = Array('name'=>'account_id','default'=>$GLOBALS['phpgw_info']['user']['userid'], 'type'=>'number');
			$expected_args[1] = Array('name'=>'host_id','default'=>0, 'type'=>'number');
			$expected_args[2] = Array('name'=>'app_id','default'=>$GLOBALS['phpgw']->applications->data[$GLOBALS['phpgw_info']['flags']['currentapp']]['id'], 'type'=>'number');
			$recieved_args = func_get_args();
			$args = safe_args($expected_args, $recieved_args,__LINE__,__FILE__);

			$this->db = $GLOBALS['phpgw']->db;
			$this->account_id = $args['account_id'];
			$this->host_id = $args['host_id'];
			$this->app_id = $args['app_id'];
		}
		
		function get_memberships ()
		{
			$expected_args[0] = Array('name'=>'account_id','default'=>$this->account_id, 'type'=>'number');
			$recieved_args = func_get_args();
			$args = safe_args($expected_args, $recieved_args,__LINE__,__FILE__);
			
			$sql = "SELECT acl_location,acl_rights FROM phpgw_acl2 
							WHERE ( acl_host='".$this->host_id."' and acl_appid = 0 and acl_account = ".$args['account_id'].")";
			$this->db->query($sql,__LINE__,__FILE__);
							
			while ($this->db->next_record())
			{
				if(!isset($this->memberships[$this->db->f('acl_location')]))
				{
					$this->memberships[$this->db->f('acl_location')] = $this->db->f('acl_rights');
					$this->memberships_sql .= ','.$this->db->f('acl_location');
					$this->get_memberships(Array('account_id'=>$this->db->f('acl_location')));
				}
			}
		}

		function cache_rights()
		{
			$expected_args[0] = Array('name'=>'location','default'=>'##REQUIRED##', 'type'=>'alphanumeric');
			$expected_args[1] = Array('name'=>'app_id','default'=>$this->app_id, 'type'=>'number');
			$recieved_args = func_get_args();
			$args = safe_args($expected_args, $recieved_args,__LINE__,__FILE__);

			if(isset($this->rights_cache[$args['app_id']][$args['location']]))
			{
				return;
			}

			$sql = "SELECT acl_rights,acl_type,acl_data,acl_location FROM phpgw_acl2 WHERE (acl_appid = '".$args['app_id']."' ";
			$sql .= " and (acl_account in (".$this->account_id.",".$this->memberships_sql.'))';
			$sql .= " and (".$this->get_location_list($args['location'],$args['app_id']).")";
			$sql .= ') ORDER BY acl_location, acl_type DESC';

			$this->db->query($sql,__LINE__,__FILE__);
			while ($this->db->next_record())
			{
				if($this->rights_cache[$args['app_id']][$args['location']] == 0)
				{
					if ($this->previous_location != '')
					{
						$this->rights_cache[$args['app_id']][$this->db->f('acl_location')] = $this->bit_mask($this->rights_cache[$args['app_id']][$this->previous_location], $this->masks_cache[$args['app_id']][$this->previous_location]);
					}
					else
					{
						$this->masks_cache[$args['app_id']][$this->db->f('acl_location')] = 0;
						$this->rights_cache[$args['app_id']][$this->db->f('acl_location')] = 0;
					}
				}

				if((int)$this->db->f('acl_type') == 0)
				{
					$this->rights_cache[$args['app_id']][$this->db->f('acl_location')] = $this->bit_set($this->rights_cache[$args['app_id']][$this->db->f('acl_location')],(int)$this->db->f('acl_rights'));
				}
				else
				{
					$this->masks_cache[$args['app_id']][$this->db->f('acl_location')] = $this->bit_set($this->rights_cache[$args['app_id']][$this->db->f('acl_location')],(int)$this->db->f('acl_rights'));
				}
				$this->previous_location = $this->db->f('acl_location');
			}
			$this->previous_location = '';
		}
		
		function get_location_list()
		{
			$expected_args[0] = Array('name'=>'location','default'=>'##REQUIRED##', 'type'=>'alphanumeric');
			$expected_args[1] = Array('name'=>'app_id','default'=>$this->app_id, 'type'=>'number');
			$expected_args[2] = Array('name'=>'return','default'=>'sql', 'type'=>'alpha');
			$recieved_args = func_get_args();
			$args = safe_args($expected_args, $recieved_args,__LINE__,__FILE__);

			if(!strstr($args['location'], '.'))
			{
				$location_list = Array('.','.'.$args['location'],$args['location']);
				$sql = "acl_location in ('.', '.'.".$args['location'].",".$args['location'].")";
				return $sql;
			}
			$location_list = explode('.',$args['location']);
			$num = count($location_list);
			for ($i=0; $i < $num; $i++)
			{
				if(isset($location_list[$i-1]))
				{
					if($location_list[$i-1] != '.')
					{
						$location_list[$i] = $location_list[$i-1].'.'.$location_list[$i];
					}
					else
					{
						$location_list[$i] = $location_list[$i-1].$location_list[$i];
					}
				}
				else
				{
					$location_list[$i] = '.';
				}

				if(!isset($sql))
				{
					$sql = "acl_location in ('".$location_list[$i]."'";
				}
				else
				{
					$sql .= ",'".$location_list[$i]."'";
				}

				if($args['return'] != 'array' && !isset($this->rights_cache[$args['app_id']][$args['location']]))
				{
					$this->rights_cache[$args['app_id']][$location_list[$i]] = 0;
					$this->masks_cache[$args['app_id']][$location_list[$i]] = 0;
				}
			}
			$sql .= ')';
			if ($args['return'] == 'array')
			{
				return $location_list;
			}
			return $sql;
		}

		function check()
		{
			$expected_args[0] = Array('name'=>'location','default'=>'##REQUIRED##', 'type'=>'alphanumeric');
			$expected_args[1] = Array('name'=>'required','default'=>1, 'type'=>'number');
			$expected_args[2] = Array('name'=>'app_id','default'=>$this->app_id, 'type'=>'number');
			$recieved_args = func_get_args();
			$args = safe_args($expected_args, $recieved_args,__LINE__,__FILE__);

			$this->cache_rights($args['location'],$args['app_id']);
			return $this->bit_check($this->rights_cache[$args['app_id']][$args['location']],$args['required']);			
		}

		/*!
		@function add
		@abstract Adds ACL record to $acl->data
		@discussion Adds ACL record to $acl->data. <br>
		Syntax: array add() <br>
		Example1: acl->add();
		@param $appname default False derives value from $phpgw_info['flags']['currentapp']
		@param $location location
		@param $rights rights
		*/
		function add()
		{
			$expected_args[0] = Array('name'=>'location','default'=>'##REQUIRED##', 'type'=>'alphanumeric');
			$expected_args[1] = Array('name'=>'rights','default'=>1, 'type'=>'number');
			$expected_args[2] = Array('name'=>'type','default'=>0, 'type'=>'number');
			$expected_args[3] = Array('name'=>'app_id','default'=>$this->app_id, 'type'=>'number');
			$expected_args[4] = Array('name'=>'data','default'=>NULL, 'type'=>'any');
			$recieved_args = func_get_args();
			$args = safe_args($expected_args, $recieved_args,__LINE__,__FILE__);

			$sql = "SELECT acl_rights FROM phpgw_acl2 WHERE (acl_appid = '".$args['app_id']."' ";
			$sql .= " and acl_account = ".$this->account_id;
			$sql .= " and acl_location = '".$args['location']."' and acl_type=".$args['type'].")";
			$this->db->query($sql,__LINE__,__FILE__);
			if($this->db->num_rows() != 0)
			{
				$this->db->next_record();
				$newrights = $this->bit_set($args['rights'], (int)$this->db->f('acl_rights'));
				$sql = "UPDATE phpgw_acl2 SET acl_rights =".$newrights;
				$sql .= " WHERE acl_host=".$this->host_id." AND acl_appid=".$args['app_id']." AND acl_account=".$this->account_id." AND acl_location='".$args['location']."' AND acl_type=".$args['type'];
			}
			else
			{
				$sql = "INSERT INTO phpgw_acl2 (acl_host,acl_appid,acl_account,acl_location,acl_rights,acl_type,acl_data) VALUES (".$this->host_id.",".$args['app_id'].",".$this->account_id.",'".$args['location']."',".$args['rights'].",".$args['type'].",'".$args['data']."')";
			}
			$this->db->query($sql,__LINE__,__FILE__);
			$this->rights_cache = Array();
			$this->masks_cache = Array();
		}

		function set()
		{
			$expected_args[0] = Array('name'=>'location','default'=>'##REQUIRED##', 'type'=>'alphanumeric');
			$expected_args[1] = Array('name'=>'rights','default'=>1, 'type'=>'number');
			$expected_args[2] = Array('name'=>'type','default'=>0, 'type'=>'number');
			$expected_args[3] = Array('name'=>'app_id','default'=>$this->app_id, 'type'=>'number');
			$expected_args[4] = Array('name'=>'data','default'=>NULL, 'type'=>'any');
			$recieved_args = func_get_args();
			$args = safe_args($expected_args, $recieved_args,__LINE__,__FILE__);

			$sql = "SELECT acl_rights FROM phpgw_acl2 WHERE (acl_appid = '".$args['app_id']."' ";
			$sql .= " and acl_account = ".$this->account_id;
			$sql .= " and acl_location = '".$args['location']."' and acl_type=".$args['type'].")";
			$this->db->query($sql,__LINE__,__FILE__);
			if($this->db->num_rows() != 0)
			{
				if((int)$args['rights'] == 0)
				{
					$sql = "DELETE FROM phpgw_acl2";
				}
				else
				{
					$sql = "UPDATE phpgw_acl2 SET acl_rights =".$args['rights'];
				}
				$sql .= " WHERE acl_host=".$this->host_id." AND acl_appid=".$args['app_id']." AND acl_account=".$this->account_id." AND acl_location='".$args['location']."' AND acl_type=".$args['type'];
				$this->db->query($sql,__LINE__,__FILE__);
			}
			else
			{
				if($args['rights'] != 0)
				{
					$sql = "INSERT INTO phpgw_acl2 (acl_host,acl_appid,acl_account,acl_location,acl_rights,acl_type,acl_data) VALUES (".$this->host_id.",".$args['app_id'].",".$this->account_id.",'".$args['location']."',".$args['rights'].",".$args['type'].",'".$args['data']."')";
					$this->db->query($sql,__LINE__,__FILE__);
				}
			}
			$this->rights_cache = Array();
			$this->masks_cache = Array();
		}

		function remove()
		{
			$expected_args[0] = Array('name'=>'location','default'=>'##REQUIRED##', 'type'=>'alphanumeric');
			$expected_args[1] = Array('name'=>'rights','default'=>1, 'type'=>'number');
			$expected_args[2] = Array('name'=>'type','default'=>0, 'type'=>'number');
			$expected_args[3] = Array('name'=>'app_id','default'=>$this->app_id, 'type'=>'number');
			$expected_args[4] = Array('name'=>'data','default'=>NULL, 'type'=>'any');
			$recieved_args = func_get_args();
			$args = safe_args($expected_args, $recieved_args,__LINE__,__FILE__);

			$sql = "SELECT acl_rights FROM phpgw_acl2 WHERE (acl_appid = '".$args['app_id']."' ";
			$sql .= " and acl_account = ".$this->account_id;
			$sql .= " and acl_location = '".$args['location']."' and acl_type=".$args['type'].")";
			$this->db->query($sql,__LINE__,__FILE__);
			if($this->db->num_rows() != 0)
			{
				$this->db->next_record();
//echo '$args[rights] = '.$args['rights'].'<br>';
//echo '$this->db->f(acl_rights) = '.$this->db->f('acl_rights').'<br>';
				$newrights = $this->bit_mask((int)$this->db->f('acl_rights'),$args['rights']);
//echo 'newrights = '.$newrights.'<br>';
				if ($newrights != 0)
				{
					$sql = "UPDATE phpgw_acl2 SET acl_rights =".$newrights;
				}
				else
				{
					$sql = "DELETE FROM phpgw_acl2";
				}
				$sql .= " WHERE acl_host=".$this->host_id." AND acl_appid=".$args['app_id']." AND acl_account=".$this->account_id." AND acl_location='".$args['location']."' AND acl_type=".$args['type'];
				$this->db->query($sql,__LINE__,__FILE__);
				$this->rights_cache = Array();
				$this->masks_cache = Array();
			}
		}		
		
		/*************************************************************************\
		* Non-standard functions. Should only be used for ACL management needs    *
		\*************************************************************************/
		function check_specific()
		{
			$expected_args[0] = Array('name'=>'location','default'=>'##REQUIRED##', 'type'=>'alphanumeric');
			$expected_args[1] = Array('name'=>'required','default'=>1, 'type'=>'number');
			$expected_args[2] = Array('name'=>'account_id','default'=>$this->account_id, 'type'=>'number');
			$expected_args[3] = Array('name'=>'app_id','default'=>$this->app_id, 'type'=>'number');
			$recieved_args = func_get_args();
			$args = safe_args($expected_args, $recieved_args,__LINE__,__FILE__);

			$sql = "SELECT acl_rights,acl_type,acl_data FROM phpgw_acl2 WHERE (acl_appid = '".$args['app_id']."' ";
			$sql .= " and acl_account = ".$args['account_id'];
			$sql .= " and acl_location = '".$args['location']."' and acl_type=0)";
			$this->db->query($sql,__LINE__,__FILE__);
			$rights = 0;
			while ($this->db->next_record())
			{
				$rights = $this->bit_set($rights,(int)$this->db->f('acl_rights'));
			}
			return $this->bit_check($rights,$args['required']);			
		}
		
		/* I dont feel this function will be needed, and plan to remove it when certain.
		function check_location()
		{
			$expected_args[0] = Array('name'=>'location','default'=>'##REQUIRED##', 'type'=>'alphanumeric');
			$expected_args[1] = Array('name'=>'required','default'=>1, 'type'=>'number');
			$expected_args[2] = Array('name'=>'app_id','default'=>$this->app_id, 'type'=>'number');
			$recieved_args = func_get_args();
			$args = safe_args($expected_args, $recieved_args,__LINE__,__FILE__);

			$sql = "SELECT acl_rights,acl_type,acl_data FROM phpgw_acl2 WHERE (acl_appid = '".$args['app_id']."' ";
			$sql .= " and (acl_account in (".$this->account_id.",".$this->memberships_sql.'))';
			$sql .= " and acl_location = '".$args['location']."' and acl_type=0)";
			$this->db->query($sql,__LINE__,__FILE__);
			$rights = 0;
			while ($this->db->next_record())
			{
				$rights = $this->bit_set($rights,(int)$this->db->f('acl_rights'));
			}
			return $this->bit_check($rights,$args['required']);			
		}
		*/

		
		/*************************************************************************\
		* Support functions                                                       *
		\*************************************************************************/
		/*!
		@function bit_set
		@abstract add/turn_on new bit to current value
		*/		
		function bit_set($rights, $new)
		{
			return $rights |= $new;	
		}

		/*!
		@function bit_mask
		@abstract mask/turn_off new bit from current value
		*/		
		function bit_mask($rights, $mask)
		{
			return $rights &= ~$mask;	
		}
	
		/*!
		@function bit_check
		@abstract check if required bit is set/turned_on in the rights
		*/		
		function bit_check($rights, $required)
		{
			return ($rights & $required);
		}
	}
?>
