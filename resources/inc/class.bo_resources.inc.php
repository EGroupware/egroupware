<?php
	/**************************************************************************\
	* eGroupWare - resources - Resource Management System                      *
	* http://www.egroupware.org                                                *
	* Written by Lukas Weiss [wnz_gh05t@users.sourceforge.net] and             *
	*            Cornelius Weiss [nelius@gmx.net]                              *
	* -----------------------------------------------                          *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	
	/* $Id$ */
	
class bo_resources
{
	var $vfs_basedir = '/resources/';
	var $pictures_dir = '/resources/pictures/';
	var $thumbs_dir = '/resources/pictures/thumbs/';
	var $resource_icons = '/resources/templates/default/images/resource_icons/';
	
	function bo_resources()
	{
		$this->so = CreateObject('resources.so_resources');
		$this->acl = CreateObject('resources.bo_acl');
		$this->cats = $this->acl->egw_cats;
		$this->vfs = CreateObject('phpgwapi.vfs');
		$this->link = CreateObject('infolog.bolink');
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
		$accessory_of = $query['view_accs_of'] ? $query['view_accs_of'] : -1;
		
		$rows = array( 0 => array(	'id'			=> '',
						'name' 			=> '',
						'short_description' 	=> '',
						'quantity'		=> '',
						'useable'		=> '',
						'bookable'		=> '',
						'buyable'		=> '',
						'cat_id'		=> '',
						'location'		=> ''
					));
		
		$order_by = $query['order'] ? $query['order'].' '. $query['sort'] : '';
		
		$nr = $this->so->search($criteria,$cats,&$rows,$accessory_of,$order_by,$offset=$query['start'],$num_rows=0);
		
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
			if ((!$this->acl->is_permitted($resource['cat_id'],PHPGW_ACL_ADD)) || $accessory_of != -1)
			{
				$readonlys["new_acc[$resource[id]]"] = true;
			}
			if (!$resource['bookable'] /* && calender-acl viewable */)
			{
				$readonlys["bookable[$resource[id]]"] = true;
			}
			if (!$resource['buyable'])
			{
				$readonlys["buyable[$resource[id]]"] = true;
			}
			$readonlys["view_acc[$resource[id]]"] = true;
			$links = $this->link->get_links('resources',$resource['id']);
			if(count($links) != 0)
			{
				foreach ($links as $link_num => $link)
				{
					if($link['app'] == 'resources')
					{
						if($this->so->get_value('accessory_of',$link['id']) != -1)
						{
							$readonlys["view_acc[$resource[id]]"] = false;
						}
					}
				}
			}
			$rows[$num]['picture_thumb'] = $this->get_picture($resource['id']);
			$rows[$num]['admin'] = $this->acl->get_cat_admin($resource['cat_id']);
		}
		return $nr;
	}

	/*!
		@function read
		@abstract reads a resource exept binary datas
		@autor Cornelius Weiﬂ <egw@von-und-zu-weiss.de>
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
		return $this->so->read($id);
	}
	
	/*!
		@function save
		@abstract saves a resource. pictures are saved in vfs
		@autor Cornelius Weiﬂ <egw@von-und-zu-weiss.de>
		@param array $resource array with key => value of all needed datas
		@return string msg if somthing went wrong; nothing if all right
	*/
	function save($resource)
	{
		if(!$this->acl->is_permitted($resource['cat_id'],PHPGW_ACL_EDIT))
		{
			return lang('You are not permitted to edit this reource!');
		}
		
		// we need an id to save pictures and make links...
		if(!$resource['id'])
		{
			$resource['id'] = $this->so->save($resource);
		}

		switch ($resource['picture_src'])
		{
			case 'own_src':
				$vfs_data = array('string' => $this->pictures_dir.$resource['id'].'.jpg','relatives' => array(RELATIVE_ROOT));
				if($resource['own_file']['size'] > 0)
				{
					$msg = $this->save_picture($resource['own_file'],$resource['id']);
					break;
				}
				elseif($this->vfs->file_exists($vfs_data))
				{
					break;
				}
				$resource['picture_src'] = 'cat_src';
			case 'cat_src':
				break;
			case 'gen_src':
				$resource['picture_src'] = $resource['gen_src_list'];
				break;
			default:
				if($resource['own_file']['size'] > 0)
				{
					$resource['picture_src'] = 'own_src';
					$msg = $this->save_picture($resource['own_file'],$resource['id']);
				}
				else
				{
					$resource['picture_src'] = 'cat_src';
				}
		}
		// somthing went wrong on saving own picture
		if($msg)
		{
			return $msg;
		}
		
		// delete old pictures
		if($resource['picture_src'] != 'own_src')
		{
			$this->remove_picture($resource['id']);
		}

		// save links
		if(is_array($resource['link_to']['to_id']))
		{
			$this->link->link('resources',$resource['id'],$resource['link_to']['to_id']);
		}
		if($resource['accessory_of'] != -1)
		{	echo $resource['id'].', '.$resource['accessory_of'];
			$this->link->link('resources',$resource['id'],'resources',$resource['accessory_of']);
		}
		
		return $this->so->save($resource) ? false : lang('Something went wrong by saving resource');
	}

	/*!
		@function delete
		@abstract deletes resource including pictures and links
		@autor Lukas Weiss <wnz_gh05t@users.sourceforge.net>
		@param int $id id of resource
	*/
	function delete($id)
	{
		$this->remove_picture($id);
 		$this->link->unlink(0,'resources',$id);
		return $this->so->delete(array('id'=>$id)) ? false : lang('Something went wrong by deleting resource');
	}
	
	/*!
		@function get_acc_list
		@abstract gets list of accessories for resource
		@autor Cornelius Weiﬂ <egw@von-und-zu-weiss.de>
		@param int $id id of resource
		@return array
	*/
	function get_acc_list($id)
	{
		if($id < 1){return;}
		$cat_id = $this->so->get_value('cat_id',$id);
		$data[0] = array('id' => '', 'name' => '');
		$this->so->search(array('name' => '*'),array($cat_id => ''),&$data,$accessory_of=$id,$order_by='',$offset=false,$num_rows=-1);
		foreach($data as $num => $resource)
		{
			$acc_list[$resource['id']] = $resource['name'];
		}
		return $acc_list;
	}

	/*!
		@function link_query
		@syntax link_query(  $pattern  )
		@author Cornelius Weiﬂ <egw@von-und-zu-weiss.de>
		@abstract query infolog for entries matching $pattern
	*/
	function link_query( $pattern )
	{
		$criteria = array('name' => $pattern,'short_description'  => $pattern);
		$cats = $this->acl->get_cats(PHPGW_ACL_READ);
		$data[0] = array('id' => '','name' => '', 'short_description' => '');
		$this->so->search($criteria,$cats,&$data,$accessory_of=-1,$order_by='',$offset=false,$num_rows=-1);
		
		foreach($data as $num => $resource)
		{
			if($num != 0)
			{
				$list[$resource['id']] = $resource['name']. ($resource['short_description'] ? ', ['.$resource['short_description'].']':'');
			}
		}
		return $list;
	}
		
	/*!
		@function link_title
		@syntax link_title(  $id  )
		@author Cornelius Weiﬂ <egw@von-und-zu-weiss.de>
		@abstract get title for an infolog entry identified by $id
	*/
	function link_title( $resource )
	{
		if (!is_array($resource) && $resource > 0)
		{
			$resource  = $this->so->read($resource);
			$title = $resource['name']. ($resource['short_description'] ? ', ['.$resource['short_description'].']':'');
		}
		return $title ? $title : false;
	}
	
	/*!
		@function save_picture
		@abstract resizes and saves an pictures in vfs
		@autor Cornelius Weiﬂ <egw@von-und-zu-weiss.de>
		@param array $file array with key => value
		@param int $resource_id
		@return mixed string with msg if somthing went wrong; nothing if all right
		TODO make thumb an picture sizes choosable by preferences
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
		
		$src_img_size = getimagesize($file['tmp_name']);
		$dst_img_size = array( 0 => 320, 1 => 240);
		$thumb_size = array( 0 => 64, 1 => 48);
		
		$tmp_dir = $GLOBALS['phpgw_info']['server']['temp_dir'].'/';
		if($src_img_size[0] > 64 || $src_img_size[1] > 48)
		{
			$f = $thumb_size[0] / $src_img_size[0];
			$f = $thumb_size[1] / $src_img_size[1] < $f ? $thumb_size[1] / $src_img_size[1] : $f;
			$dst_img = imagecreatetruecolor($src_img_size[0] * $f, $src_img_size[1] * $f);
			imagecopyresized($dst_img,$src_img,0,0,0,0,$src_img_size[0] * $f,$src_img_size[1] * $f,$src_img_size[0],$src_img_size[1]);
			imagejpeg($dst_img,$tmp_dir.$resouce_id.'.thumb.jpg');
			if($src_img_size[0] > $dst_img_size[0] || $src_img_size[1] > $dst_img_size[1])
			{
				$f = $dst_img_size[0] / $src_img_size[0];
				$f = $dst_img_size[1] / $src_img_size[1] < $f ? $dst_img_size[1] / $src_img_size[1] : $f;
				$dst_img = imagecreatetruecolor($src_img_size[0] * $f, $src_img_size[1] * $f);
				imagecopyresized($dst_img,$src_img,0,0,0,0,$src_img_size[0] * $f,$src_img_size[1] * $f,$src_img_size[0],$src_img_size[1]);
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
		@autor Cornelius Weiﬂ <egw@von-und-zu-weiss.de>
		@param int $id id of resource
		@param bool $size false = thumb, true = full pic
		@return string url of picture
	*/
	function get_picture($id=0,$size=false)
	{
		if ($id > 0)
		{
			$src = $this->so->get_value('picture_src',$id);
		}
		
		switch($src)
		{
			case 'own_src':
				$picture = false /*$this->config->use_vfs*/ ? 'vfs:' : $GLOBALS['phpgw_info']['server']['webserver_url'];
				$picture .= $size ? $this->pictures_dir.$id.'.jpg' : $this->thumbs_dir.$id.'.jpg';
				break;
			case 'cat_src':
				list($picture) = $this->cats->return_single($this->so->get_value('cat_id',$id));
				$picture = unserialize($picture['data']);
				if($picture['icon'])
				{
					$picture = $GLOBALS['phpgw_info']['server']['webserver_url'].'/phpgwapi/images/'.$picture['icon'];
					break;
				}
			case 'gen_src':
			default :
				$picture = $GLOBALS['phpgw_info']['server']['webserver_url'].$this->resource_icons;
				$picture .= strstr($src,'.') ? $src : 'generic.png';
		}
		return $picture;
	}
	
	/*!
		@fuction remove_picture
		@abstract removes picture from vfs
		@autor Cornelius Weiﬂ <egw@von-und-zu-weiss.de>
		@param int $id id of resource
		@return bool succsess or not
	*/
	function remove_picture($id)
	{
		$vfs_data = array('string' => $this->pictures_dir.$id.'.jpg','relatives' => array(RELATIVE_ROOT));
		$this->vfs->override_acl = 1;
		if($this->vfs->file_exists($vfs_data))
		{
			$this->vfs->rm($vfs_data);
			$vfs_data['string'] = $this->thumbs_dir.$id.'.jpg';
			$this->vfs->rm($vfs_data);
		}
		$this->vfs->override_acl = 0;
	}

	/*!
		@fuction get_genpicturelist
		@abstract gets all pictures from 'generic picutres dir' in selectbox style for eTemplate
		@autor Cornelius Weiﬂ <egw@von-und-zu-weiss.de>
		@return array directory contens in eTemplates selectbox style
	*/
	function get_genpicturelist()
	{
		$icons['generic.png'] = lang('gernal resource');
		$dir = dir(PHPGW_SERVER_ROOT.$this->resource_icons);
		while($file = $dir->read())
		{
			if (preg_match('/\\.(png|gif|jpe?g)$/i',$file) && $file != 'generic.png')
			{
				$icons[$file] = substr($file,0,strpos($file,'.'));
			}
		}
		$dir->close();
		return $icons;
	}
}
