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
	var $vfs_basedir = '/resources/';
	var $pictures_dir = '/resources/pictures/';
	var $thumbs_dir = '/resources/pictures/thumbs/';
	
	function bo_resources()
	{
		$this->so = CreateObject('resources.so_resources');
		$this->acl = CreateObject('resources.bo_acl');
		$this->cats = $this->acl->egw_cats;
		$this->vfs = CreateObject('phpgwapi.vfs');
		
// 		print_r($this->cats->return_single(33)); die(); 
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
						'location'		=> '',
						'picture_src'		=> ''
					));
		
		$order_by = $query['order'] ? $query['order'].' '. $query['sort'] : '';
		
		$nr = $this->so->search($criteria,$cats,&$rows,$order_by,$offset=$query['start'],$num_rows=0);
// 		print_r($rows);die();
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
			$rows[$num]['picture_thumb'] = $this->get_picture($resource['id'],$resource['picture_src']);
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
		@abstract saves a resource. pictures are saved in vfs
		@param array $resource array with key => value of all needed datas
		@return string msg if somthing went wrong; nothing if all right
	*/
	function save($resource)
	{
		if(!$this->acl->is_permitted($resource['cat_id'],PHPGW_ACL_EDIT))
		{
			return lang('You are not permitted to edit this reource!');
		}
		
		// we need an id to save pictures
		if(!$resource['id'])
		{
			$resource['id'] = $this->so->save($resource);
		}

		if($resource['own_file']['size']>0 && ($resource['picture_src']=='own_src' || sizeof($resource['picture_src'])<1))
		{
			$resource['picture_src'] = 'own_src';
			$msg = $this->save_picture($resource['own_file'],$resource['id']);
			if($msg)
			{
				return $msg;
			}
		}
		
		if($resource['picture_src'] == 'gen_src')
		{
			// welches bild? --> picture_src = dateiname
		}
		
		return $this->so->save($resource) ? false : lang('Something went wrong by saving resource');
	}

	function delete($id)
	{
		return $this->so->delete(array('id'=>$id)) ? false : lang('Something went wrong by saving resource');
	}

	/*!
		@function save_picture
		@abstract resizes and saves an pictures in vfs
		@param array $file array with key => value
		@param int $resource_id
		@return mixed string with msg if somthing went wrong; nothing if all right
		TODO make thumb an picture sizes choosable by preferences
		TODO better handling for not 4:3 images
	*/	
	function save_picture($file,$resouce_id)
	{
		// test upload dir
		$vfs_data = array('string'=>$this->vfs_basedir,'relatives'=>array(RELATIVE_ROOT));
		if (!($this->vfs->file_exists($vfs_data)))
		{
			$this->vfs->override_acl = 1;
			$this->vfs->mkdir($vfs_data);
			$vfs_data['string'] = $this->pictures_dir;
			$this->vfs->mkdir($vfs_data);
			$vfs_data['string'] = $this->thumbs_dir;
			$this->vfs->mkdir($vfs_data);
			$this->vfs->override_acl = 0;
		}
		
		switch($file['type'])
		{
			case 'image/gif':
				$src_img = imagecreatefromgif($file['tmp_name']);
				break;
			case 'image/jpeg':
			case 'image/pjpeg':
				$src_img = imagecreatefromjpeg($file['tmp_name']);
				break;
			case 'image/png':
			case 'image/x-png':
				$src_img = imagecreatefrompng($file['tmp_name']);
				break;
			default:
				return lang('Picture type is not supported, sorry!');
		}
		
		$img_size = getimagesize($file['tmp_name']);
		$tmp_dir = $GLOBALS['phpgw_info']['server']['temp_dir'].'/';
		if($img_size[0] > 64 || $img_size[1] > 48)
		{
			$dst_img = imagecreatetruecolor(64, 48);
			imagecopyresized($dst_img,$src_img,0,0,0,0,64,48,$img_size[0],$img_size[1]);
			imagejpeg($dst_img,$tmp_dir.$resouce_id.'.thumb.jpg');
			if($img_size[0] > 320 || $img_size[1] > 240)
			{
				$dst_img = imagecreatetruecolor(320, 240);
				imagecopyresized($dst_img,$src_img,0,0,0,0,320,240,$img_size[0],$img_size[1]);
				imagejpeg($dst_img,$tmp_dir.$resouce_id.'.jpg');
			}
			else
			{
				imagejpeg($src_img,$tmp_dir.$resouce_id.'.jpg');
			}
			imagedestroy($dst_img);
		}
		else
		{
				imagejpeg($src_img,$tmp_dir.$resouce_id.'.jpg');
				imagejpeg($src_img,$tmp_dir.$resouce_id.'.thumb.jpg');
		}
		imagedestroy($src_img);
			
		$this->vfs->override_acl = 1;
		$this->vfs->mv(array(
			'from' => $tmp_dir.$resouce_id.'.jpg',
			'to'   => $this->pictures_dir.$resouce_id.'.jpg',
			'relatives' => array(RELATIVE_NONE|VFS_REAL,RELATIVE_ROOT)
		));
		$this->vfs->set_attributes(array(
			'string' => $this->pictures_dir.$resouce_id.'.jpg',
			'relatives' => array (RELATIVE_ROOT),
			'attributes' => array (
				'mime_type' => 'image/jpeg',
				'comment' => 'picture of resource no.'.$resouce_id,
				'app' => $GLOBALS['phpgw_info']['flags']['currentapp']
		)));
		$this->vfs->mv(array(
			'from' => $tmp_dir.$resouce_id.'.thumb.jpg',
			'to'   => $this->thumbs_dir.$resouce_id.'.jpg',
			'relatives' => array(RELATIVE_NONE|VFS_REAL,RELATIVE_ROOT)
			));
		$this->vfs->set_attributes(array(
			'string' => $this->thumbs_dir.$resouce_id.'.jpg',
			'relatives' => array (RELATIVE_ROOT),
			'attributes' => array (
				'mime_type' => 'image/jpeg',
				'comment' => 'thumbnail of resource no.'.$resouce_id,
				'app' => $GLOBALS['phpgw_info']['flags']['currentapp']
		)));
		$this->vfs->override_acl = 0;
		return;
	}
	
	/*!
		@function get_picture
		@abstact get resource picture either from vfs or from symlink
		@param int $id id of resource
		@param string $src can be: own_src, gen_src, cat_scr
		@param bool $size false = thumb, true = full pic
		@return string url of picture
	*/
	function get_picture($id,$src,$size=false)
	{
		switch($src)
		{
			case 'own_src':
				$picture = false /*$this->config->use_vfs*/ ? 'vfs:' : $GLOBALS['phpgw_info']['server']['webserver_url'];
				$picture .= $size ? $this->pictures_dir.$id.'.jpg' : $this->thumbs_dir.$id.'.jpg';
				break;
			case 'gen_src':
			case 'cat_src':
			default :
				$picture = 'generic.png';
		}
		return $picture;
	}
}


