<?php
  /***************************************************************************\
  * eGroupWare - File Manager                                                 *
  * http://www.egroupware.org                                                 *
  * Written by:                                                               *
  *  - Vinicius Cubas Brand <viniciuscb@users.sourceforge.net>                *
  *  sponsored by Thyamad - http://www.thyamad.com                            *
  * ------------------------------------------------------------------------- *
  * Description: Custom Field class handler for VFS (SQL implementation v2)   *
  * ------------------------------------------------------------------------- *
  *  This program is free software; you can redistribute it and/or modify it  *
  *  under the terms of the GNU General Public License as published by the    *
  *  Free Software Foundation; either version 2 of the License, or (at your   *
  *  option) any later version.                                               *
  \***************************************************************************/

	class vfs_customfields
	{
		var $db;

		function vfs_customfields()
		{
			$this->db = $GLOBALS['phpgw']->db;

		}

		# =-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=- #
		# Functions to associate customfields to a file              # 
		# =-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=- #


		//$file_id: the id of the file
		function & get_fields_by_fileid($file_id)
		{
			if (!is_numeric($file_id) || !(((int)$file_id)==$file_id))
				return false;

			$sql = "SELECT cf.customfield_name as name, 
			               cfd.data as data
			        FROM   phpgw_vfs2_customfields cf,
					       phpgw_vfs2_customfields_data cfd
					WHERE  cf.customfield_id = cfd.customfield_id
					  AND  cf.customfield_active = 'Y'
					  AND  cfd.file_id = $file_id";

			$this->db->query($sql,__LINE__,__FILE__);

			while($this->db->next_record())
			{
				$result[$this->db->Record['name']] = $this->db->Record['data'];
			}
			
			return $result;

		}

		//$data = array(file_id  => array('field1'=> 'value', ....),
		//              file_id2 => array(...) ... )
		// Store will do insert or update, depending if the field exists or
		// not.
		function store_fields($data)
		{
			if (!is_array($data))
				return false;

			$fields = $this->get_customfields('customfield_name');
				
			foreach($data as $file_id => $file_data)
			{
				foreach($file_data as $name => $value)
				{
					//Column type does not exists
					if (!array_key_exists($name,$fields))
					{
						//TODO ERROR HANDLING
						continue;
					}
					
					$this->db->insert('phpgw_vfs2_customfields_data',
						array('data' => $value),
						array('file_id'=>$file_id,'customfield_id'=>$fields[$name]['customfield_id']),
						__LINE__,__FILE__);

				}
			}
			return true;
		}

		//$data = array('file_id' => array('field1','field2',...),
		//              'fileid2' => ... )
		function delete_fields($data)
		{

			$fields = $this->get_customfields('customfield_name');

			foreach($data as $file_id => $file_fields)
			{
				foreach($file_fields as $column_name)
				{
					if (!array_key_exists($column_name,$fields))
					{
						//TODO ERROR HANDLING
						continue;
					}

					$this->db->delete('phpgw_vfs2_customfields_data',
						array('file_id' => $file_id,'customfield_id'=>$fields[$column_name]['customfield_id']),__LINE__,__FILE__);
				}
			}
		}

		/* Search the files that have $keyword in any field, or in the fields
		   specified in the array fields 
		   @param $fields array('field1','fields2',...)*/
		function search_files($keyword,$fields=null)
		{

			$where = '';

			if ($fields)
			{
				$customfields = $this->get_customfields('customfield_name');

				foreach ($fields as $field)
				{
					if ($customfields[$field])
					{
						$cf_ids[] = 'customfield_id='.$customfields[$field]['customfield_id'];
					}
				}

				if ($cf_ids)
				{
					$where = implode(' OR ',$cf_ids);
					
					$where = 'AND ('.$where.')';
				}

			}
			
			

			$sql = "SELECT file_id
					FROM   phpgw_vfs2_customfields_data
					WHERE  data LIKE '%$keyword%'
					$where";

			$this->db->query($sql,__LINE__,__FILE__);

			$res = array();
			while($this->db->next_record())
			{
				$res[] = $this->db->Record['file_id'];
			}
			return $res;
		}

		# =-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=- #
		# Functions to manage custom field types                     # 
		# =-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=- #

		//Gets all custom field types, indexed by $indexedby.
		//Example: array( 'customfieldname1' => array('customfield_id'=>x,'customfield_description'=>y,'type'=>z...)...)
		//@param bool $activeonly If true, brings only active fields
		function get_customfields($indexedby='customfield_name',$activeonly=true)
		{
			$where=($activeonly)?array('customfield_active'=>'Y'):array();
		
			$this->db->select('phpgw_vfs2_customfields','*',$where,
				__LINE__,__FILE__);

			$result = array();
			while($this->db->next_record())
			{
				$result[] = $this->db->Record;
			}

			if (!is_array($result[0]) || !array_key_exists($indexedby,$result[0]))
			{	
				$indexedby = 'customfield_name';
			}

			$result2 = array();
			foreach($result as $key => $val)
			{		
				$result2[$val[$indexedby]] = $val;
			}

			return $result2;
		}

		//samething that $this->get_customfields, but returns an array in the
		//format array('name' => 'description', 'name2'=>'desc2',...)
		function get_attributes($activeonly=true)
		{
			$where=($activeonly)?array('customfield_active'=>'Y'):array();
		
			$this->db->select('phpgw_vfs2_customfields','*',$where,
				__LINE__,__FILE__);

			while($this->db->next_record())
			{
				$result[] = $this->db->Record;
			}

			$result2 = array();
			foreach($result as $key => $val)
			{		
				$result2[$val['customfield_name']] = $val['customfield_description'];
			}

			return $result2;
		}


	
		//Add a new type of custom field
		//$type can be of the same possible types for egw 
		function add_customfield($name,$description,$type,$precision='',$active='N')
		{
			$active = strtoupper($active);
			
			$res = $this->db->insert('phpgw_vfs2_customfields',array(
				'customfield_name' => $name,
				'customfield_description' => $description,
				'customfield_type' => $type,
				'customfield_precision' => $precision,
				'customfield_active' => $active ),
				__LINE__,__FILE__);

			if ($res)
			{
				return true;
			}
			return false;
		}

		//Update a customfield type (customfield_name, customfield_description, type, precision, active)
		function update_customfield($customfield_id,$data)
		{
			if (!is_numeric($customfield_id) || !(((int)$customfield_id)==$customfield_id))
			{
				return false;
			}

			if ($data['customfield_active'])
			{
				$data['customfield_active'] = strtoupper($data['customfield_active']);
			}

			if ($this->db->update('phpgw_vfs2_customfields',$data,array('customfield_id'=>$customfield_id),__LINE__,__FILE__))
			{
				return true;
			}
			return false;
		}
		
		//generally better inactivate a field than remove.
		function remove_customfield($customfield_id)
		{
			$res = $this->db->delete('phpgw_vfs2_customfields',array('customfield_id'=>$customfield_id),__LINE__,__FILE__);

			if ($res)
			{
				$res2 = $this->db->delete('phpgw_vfs2_customfields_data',array('customfield_id'=>$customfield_id),__LINE__,__FILE__);

				if ($res2)
				{
					return true;
				}
			}
			return false;
		} 

	}
?>
