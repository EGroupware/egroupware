<?php
  /**************************************************************************\
  * phpGroupWare - Calendar - Custom fields and sorting                      *
  * http://www.phpgroupware.org                                              *
  * Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */

	class bocustom_fields
	{
		var $stock_fields = array(
			'title' => array(
				'label' => 'Title',
				'title' => True
			),
			'description' => 'Description',
			'category'    => 'Category',
			'location'    => 'Location',
			'startdate'   => 'Start Date/Time',
			'enddate'     => 'End Date/Time',
			'priority'    => 'Priority',
			'access'      => 'Access',
			'participants'=> 'Participants',
			'owner'       => 'Created By',
			'updated'     => 'Updated',
			'alarm'       => 'Alarm',
			'recure_type' => 'Repetition'
		);

		function bocustom_fields()
		{
			$this->config = CreateObject('phpgwapi.config','calendar');
			$this->config->read_repository();

			$this->fields = &$this->config->config_data['fields'];

			if (!is_array($this->fields)) {
				$this->fields = array();
			}

			foreach ($this->fields as $field => $data)	// this can be removed after a while
			{
				if (!isset($this->stock_fields[$field]) && $field[0] != '#')
				{
					unset($this->fields[$field]);
					$this->fields['#'.$field] = $data;
				}
			}

			foreach($this->stock_fields as $field => $data)
			{
				if (!is_array($data))
				{
					$data = array('label' => $data);
				}
				if (!isset($this->fields[$field]))
				{
					$this->fields[$field] = array(
						'name'     => $field,
						'title'    => $data['title'],
						'disabled' => $data['disabled']
					);
				}
				$this->fields[$field]['label']  = $data['label'];
				$this->fields[$field]['length'] = $data['length'];
				$this->fields[$field]['shown']  = $data['shown'];
			}
		}

		function set($data)
		{
			if (is_array($data) && strlen($data['name']) > 0)
			{
				if (!isset($this->stock_fields[$name = $data['name']]))
				{
					$name = '#'.$name;
				}
				$this->fields[$name] = $data;
			}
		}

		function save($fields=False)
		{
			if ($fields)
			{
				$this->fields = $fields;
			}
			//echo "<pre>"; print_r($this->config->config_data); echo "</pre>\n";
			$this->config->save_repository();
		}
	}
