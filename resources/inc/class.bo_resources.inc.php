<?php
	/**************************************************************************\
	* eGroupWare - resources - Resource Management System                      *
	* http://www.egroupware.org                                                *
	* Written by Lukas Weiss [ichLukas@gmx.net] and                            *
	*            Cornelius Weiss [nelius@gmx.net]                              *
	* -----------------------------------------------                          *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	
class bo_resources
{
	/*var $public_functions = array
	(
		'get_rows'	=> True
	);*/
	
	function bo_resources()
	{
		$this->so = CreateObject('resources.so_resources');
		$this->acl = CreateObject('resources.bo_acl');
	}

	/*!
		@function get_rows
		@abstract get rows for resources list
		@autor Cornelius Weiﬂ <egw@von-und-zu-weiss.de>
	*/
	function get_rows($query,&$rows,&$readonlys)
	{
		$query['search'] = $query['search'] ? $query['search'] : '*';
		
		$criteria = array(	'name' 			=> $query['search'], 
					'short_description' 	=> $query['search']
				);
		$cats = $query['filter'] ? array($query['filter'] => '') : $this->acl->get_cats(PHPGW_ACL_READ);
		
		$rows = array( 0 => array(	'id'			=> '',
						'name' 			=> '',
						'short_description' 	=> '',
						'useable'		=> '',
						'bookable'		=> '',
						'cat_id'		=> '',
						'location'		=> ''
					));
		
		$order_by = $query['order'] ? $query['order'].' '. $query['sort'] : '';
		
		$nr = $this->so->search($criteria,$cats,&$rows,$order_by,$offset=$query['start'],$num_rows=0);
		
		foreach($rows as $num => $resource)
		{
			if (!$this->acl->is_permitted($resource['cat_id'],PHPGW_ACL_EDIT))
			{
				$readonlys["edit[$resource[id]]"] = true;
			}
			if (!$this->acl->is_permitted($resource['cat_id'],PHPGW_ACL_DELETE))
			{
				$readonlys["delete[$resource[id]]"] = true;
			}
			if (!$resource['bookable'] /* && calender-acl viewable */)
			{
				$readonlys["bookable[$resource[id]]"] = true;
			}
		}
		return $nr;
	}

	/*!
		@function read
		@abstract reads a resource exept binary datas
		@param int $id resource id
		@return array with key => value or false if not found or allowed
	*/
	function read($id)
	{
		if(!$this->acl->is_permitted($this->so->get_value('cat_id',$id),PHPGW_ACL_READ))
		{
			echo lang('You are not permitted to get information about this resource!') . '<br>';
			echo lang('Notify your administrator to correct this situation') . '<br>';
			return false;
		}
		return /* all exept pictures(blobs) */$this->so->read($id);
	}
	
	/*!
		@function save
		@abstract saves a resource including picture upload ...
		@param array $resource array with key => value of all needed datas
		@return string msg if somthing went wrong
	*/
	function save($resource)
	{
		if($resource['own_file']['size']>0 && ($resource['picture_src']=='db_src' || sizeof($resource['picture_src'])<1))
		{
			$resource['picture_src'] = 'db_src';
			switch($resource['own_file']['type'])
			{
				case 'image/gif':
					$resource['db_src'] = imagecreatefromgif($resource['own_file']['tmp_name']);
					break;
				case 'image/jpeg':
				case 'image/pjpeg':
					$resource['db_src'] = imagecreatefromjpeg($resource['own_file']['tmp_name']);
					break;
				case 'image/png':
				case 'image/x-png':
					$resource['db_src'] = imagecreatefrompng($resource['own_file']['tmp_name']);
					break;
				default:
					return 'Picture type is not supported, sorry!';
			}
		}
		
		if($resource['picture_src'] == 'gen_src')
		{
		}

			
		return $this->so->save_data($resource);
	}

	function delete($id)
	{
		return $this->so->delete(array('id'=>$id));
	}
	
	function get_images($params)
	{ 
		$id = implode($params);
		$picture = $this->so->get_value('picture',$id);
		if($picture)
		{
			// $picture = GD($picture);
			header('Content-type: image/png');
			echo $picture;
		}
		header('Content-type: image/png');
		echo file_get_contents(PHPGW_INCLUDE_ROOT.'/resources/templates/default/images/generic.png');
		return;
	}
}


