<?php
	/**************************************************************************\
	* phpGroupWare - eTemplates - Tutoria Example - a simple MediaDB           *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	include_once(PHPGW_INCLUDE_ROOT . '/etemplate/inc/class.so_sql.inc.php');
	$GLOBALS['phpgw_info']['flags']['included_classes']['so_sql'] = True; // for 0.9.14

	class et_media extends so_sql
	{
		var $types = array(
			'' => 'Select one ...',
			'cd' => 'Compact Disc',
			'dvd' => 'DVD',
			'book' => 'Book',
			'video' => 'Video Tape'
		);

		function et_media()
		{
			$this->tmpl = CreateObject('etemplate.etemplate','et_media.edit');

			$this->so_sql('et_media','phpgw_et_media');	// sets up our storage layer using the table 'phpgw_et_media'
			$this->empty_on_write = "''";	// that means if a column is empty how to write in the db, the default is NULL

			$this->public_functions += array(
				'edit' => True,
				'writeLangFile' => True
			);
		}

		function edit($content='',$msg = '')
		{
			if (is_array($content))	// not first call from index
			{
				if ($content['id'] > 0)
				{
					$this->read($content);
				}
				//echo "<p>edit: content ="; _debug_array($content);
				$this->data_merge($content);
				//echo "<p>edit: data ="; _debug_array($this->data);

				if (isset($content['save']))
				{
					$msg .= !$this->save() ? lang('Entry saved') : lang('Error: writeing !!!');
				}
				elseif (isset($content['read']))
				{
					unset($content['id']);
					$found = $this->search($content,False,'name,author');

					if (!$found)
					{
						$msg .= lang('Nothing matched search criteria !!!');
					}
					elseif (count($found) == 1)
					{
						$this->init($found[0]);
					}
					else
					{
						$this->show($found);
						return;
					}
				}
				elseif (isset($content['cancel']))
				{
					$this->init();
				}
				elseif (isset($content['delete']))
				{
					$this->delete();
					$this->init();
				}
				elseif (isset($content['entry']['edit']))
				{
					list($id) = each($content['entry']['edit']);
					if ($id > 0)
					{
						$this->read(array('id' => $id));
					}
				}
			}

			// now we filling the content array for the next call to etemplate.exec

			$content = $this->data + array(
				'msg' => $msg
			);
			$sel_options = array(
				'type' => $this->types
			);
			$no_button = array(
				'delete' => !$this->data[$this->db_key_cols[$this->autoinc_id]]
			);
			$this->tmpl->exec('et_media.et_media.edit',$content,$sel_options,$no_button,array(
				'id' => $this->data['id']
			));
		}

		function show($found)
		{
			if (!is_array($found) || !count($found))
			{
				$this->edit();
				return;
			}
			reset($found);
			for ($row=1; list($key,$data) = each($found); ++$row)
			{
				$entry[$row] = $data;
			}
			$content = array(
				'msg' => lang('%1 matches on search criteria',count($found)),
				'entry' => $entry
			);
			$this->tmpl->read('et_media.show');

			$this->tmpl->exec('et_media.et_media.edit',$content);
		}

		/*!
		@function writeLangFile
		@abstract writes langfile with all templates and types here
		@discussion can be called via [write Langfile] in the eTemplate editor or
		@discussion http://domain/phpgroupware/index.php?et_media.et_media.writeLangFile
		*/
		function writeLangFile()
		{
			return $this->tmpl->writeLangFile('et_media','en',$this->types);
		}
	};



