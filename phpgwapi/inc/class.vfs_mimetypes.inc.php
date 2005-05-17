<?php
  /***************************************************************************\
  * eGroupWare - File Manager                                                 *
  * http://www.egroupware.org                                                 *
  * Written by:                                                               *
  *  - Vinicius Cubas Brand <viniciuscb@users.sourceforge.net>                *
  *  sponsored by Thyamad - http://www.thyamad.com                            *
  * ------------------------------------------------------------------------- *
  * Description: Mime type class handler for VFS (SQL implementation v2)      *
  * ------------------------------------------------------------------------- *
  *  This program is free software; you can redistribute it and/or modify it  *
  *  under the terms of the GNU General Public License as published by the    *
  *  Free Software Foundation; either version 2 of the License, or (at your   *
  *  option) any later version.                                               *
  \***************************************************************************/

	#based on the filetypes.inc.php class by intermesh, see groupoffice.com

	#02 Sep 2004 viniciuscb Initial Release

	class vfs_mimetypes
	{
		var $default_filetype_icon;
		var $db;
		var $vfs; //Just to get mime types from vfs->get_ext_mime_type()

		var $default_mimetype = "application/OCTET-STREAM";

		function vfs_mimetypes($create_vfs=true)
		{
			//use create_vfs=false and after this use $this->set_vfs to keep the
			// same object (i.e. use reference) in $this->vfs
			if ($create_vfs)
			{
				$this->vfs =& CreateObject('phpgwapi.vfs');
			}

			$this->default_filetype_icon = PHPGW_INCLUDE_ROOT.'/filescenter/icons/default.gif';

			$this->db = $GLOBALS['phpgw']->db;
			
		}

		/*!
		 * function add_filetype
		 * @description Adds a new mime_type to the mime_types repository
		 * @note One of the two: extension or mime are required
		 * @param extension string  A file extension
		 * @param mime string  the mime type according to the RFCs 2046/1524
		 * @param friendly string  File type description in human-friendly
		 *                                                               format
		 * @param image string content of icon file
		 * @param icon_name string complete path filename of icon image
		 *      (note: will be used only when icon_data is not set)
		 * @param mime_magic data used for mime_magic functions (planned)
		 * @param return_image bool if true will return the binary data of image
		 * @result Array with mime_id and other mime info, false otherwise
		 */
		function add_filetype($data,$return_image=false,$dont_update=false)
		{
			if (!$data['extension'] && !$data['mime'])
			{
				return false;
			}


			if (!$data['mime'])
			{
				$data['mime'] = $this->get_mime_type($data['extension']);
			}
			elseif (!$data['extension'])
			{
				$data['extension'] = '(n/a)'; //Thinking in 'Directory'
			}

			if (!$data['friendly'])
			{
				if ($data['extension'] != '(n/a)')
				{
					$data['friendly'] = strtoupper($data['extension']).' File';
				}
				else
				{
					$data['friendly'] = $data['mime'];
				}
				
			}

			if (!$data['image'])
			{
				if (!$data['icon_name'] || !file_exists($data['icon_name']))
				{
					$data['icon_name'] = $this->default_filetype_icon;
				}
				$fp = fopen($data['icon_name'],"r");
				$data['image'] = fread($fp,filesize($data['icon_name']));
				fclose($fp);

				$data['image'] = $this->db->quote($data['image'],'blob');

				unset($data['icon_name']);
			}


			$where['extension'] = $data['extension'];
			#$where['mime'] = $data['mime'];

			if ($dont_update)
			{
				$this->db->select('phpgw_vfs2_mimetypes','mime_id',$where,__LINE__,__FILE__);
				if ($this->db->next_record())
				{
					return false; //msgerror Existent register that cannot be overwritten
				}
			}


			$res = $this->db->insert('phpgw_vfs2_mimetypes',$data,$where,__LINE__,__FILE__);
			

			if($res)
			{ 
				$this->db->select('phpgw_vfs2_mimetypes','mime_id',$where,
					__LINE__,__FILE__);

				$this->db->next_record();
		
				$data['mime_id'] = $this->db->Record['mime_id'];

				if (!$return_image)
				{
					unset($data['image']);
				}

				return $data;
			}

			return false;
		}

		/*!
		 * function edit_filetype
		 * @description Edits a mime_type of the mime_types repository
		 * @note mime_id required. Will change only passed vars.
		 * @param extension string  A file extension
		 * @param mime string  the mime type according to the RFCs 2046/1524
		 * @param friendly string  File type description in human-friendly
		 *                                                               format
		 * @param image string content of icon file
		 * @param icon_name string complete path filename of icon image
		 *      (note: will be used only when icon_data is not set)
		 * @param mime_magic data used for mime_magic functions (planned)
		 * @param return_image bool if true will return the binary data of image
		 * @result Array with mime_id and other mime info, false otherwise
		 */
		function edit_filetype($data,$return_image=false)
		{

			if (!$data['mime_id'])
			{
				return false;
			}

			$where['mime_id'] = $data['mime_id'];

			if ($data['image'])
			{
				$data['image'] = $this->db->quote($data['image'],'blob');
			}

			$res = $this->db->update('phpgw_vfs2_mimetypes',$data,$where,__LINE__,__FILE__);
			

			if($res)
			{ 
				if ($return_image)
				{
					$return_fields = '*';
				}
				else
				{
					$return_fields = 'mime_id,extension,mime,friendly,mime_magic,proper_id';
				}
				$this->db->select('phpgw_vfs2_mimetypes',$return_fields,$where,
					__LINE__,__FILE__);

				$this->db->next_record();
		
				return $this->db->Record;
			}

			return false;
		}




		/*!
		 * function get_type
		 * @description Returns a mime type, based in extension or mime_id
		 * @param $desc array  have index 'extension', 'mime_id' or 'mime'
		 * @param $return_image  if true will return the binary data of image
		 * @result Array with mime_id and other mime info, false otherwise
		 */
		function get_type($data, $return_image=false)
		{
			//TODO error messages
			if ((!$data['extension'] || $data['extension'] == '(n/a)') &&
				!$data['mime_id'] && !$data['mime'])
				return false;


			$return_fields = ($return_image)?'*':'mime_id,extension,friendly,mime_magic,mime,proper_id';

			if ($data['mime_id'])
			{
				$this->db->select('phpgw_vfs2_mimetypes',$return_fields,
					array('mime_id'=>$data['mime_id']),__LINE__,__FILE__);
			}
			else if ($data['extension'])
			{
				$this->db->select('phpgw_vfs2_mimetypes',$return_fields,
					array('extension'=>$data['extension']),__LINE__,__FILE__);
			}
			else if ($data['mime'])
			{
				$this->db->select('phpgw_vfs2_mimetypes',$return_fields,
					array('mime'=>$data['mime']),__LINE__,__FILE__);
			}
	
			if ($data['extension'] && $data['mime'])
			{
				//if there is extension and mime specified and nothing was found only with extension, search mime
				if ($this->db->next_record())
					return $this->db->Record;
				else
				{
					$this->db->select('phpgw_vfs2_mimetypes',$return_fields,
						array('mime'=>$data['mime']),__LINE__,__FILE__);

					if ($this->db->next_record())
						return $this->db->Record;
				}
			}
			else
			{
				if ($this->db->next_record())
					return $this->db->Record;
			}

			return false;

		}

		/*!
		 * function get_filetypes
		 * @description Returns an array with all file types in the repository
		 * @param $return_images if true, the images are returned in the array
		 * @param $offset int if specified, will bring a subset of repository
		 * @result Array: $arr[1]['mime_id'] ...
		 */
		function get_filetypes($return_images=false,$offset=false)
		{
			$return_fields = ($return_images)?'*':'mime_id,extension,friendly,mime_magic,mime,proper_id';

			$this->db->select('phpgw_vfs2_mimetypes',$return_fields,false,
				__LINE__,__FILE__,$offset);

			$result = array();
			while ($this->db->next_record())
			{
				$result[] = $this->db->Record;
			}
			return $result;
		}

		/*!
		 * function update_filetype
		 * @description Updates a file type information in the filetype
		 *  repository
		 * @param $return_image  if true will return the binary data of image
		 * @note parameters in $data: the same of add_filetype()
		 * @result bool true on success, false otherwise
		 */
		function update_filetype($data)
		{
			if (!$data['mime_id'])
			{
				return false;
			}

			if ($data['icon_name'])
			{
				if (file_exists($data['icon_name']))
				{
					$fp = fopen($data['icon_name'],"r");
					$data['image'] = fread($fp,filesize($icon));
					fclose($fp);
				}

				$data['image'] = $this->db->quote($data['image'],'blob');

				unset($data['icon_name']);
			}

	
			$where['mime_id'] = $data['mime_id'];

			if ($this->db->update('phpgw_vfs2_mimetypes',$data,$where,__LINE__,
				__FILE__))
				return true;

			return false;
		}


		/*!
		 * function delete_filetype
		 * @description deletes a type from the file type repository
		 * @param $mime_id  the mime_id of the record
		 * @result bool true on success, false otherwise
		 */
		function delete_filetype($mime_id)
		{
			if ($this->db->delete('phpgw_vfs2_mimetypes',
				array('mime_id'=>$mime_id),__LINE__,__FILE__))
				return true;

			return false;
		}

		function set_vfs(&$vfs)
		{
			$this->vfs =& $vfs;
		}

		#private methods

		function get_mime_type($extension)
		{
			return $this->vfs->get_ext_mime_type(array('string'=>$extension));
		}

		function default_values ($data, $default_values)
		{
			for ($i = 0; list ($key, $value) = each ($default_values); $i++)
			{
				if (!isset ($data[$key]))
				{
					$data[$key] = $value;
				}
			}

			return $data;
		}

	}

?>
