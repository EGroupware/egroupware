<?php
	/***************************************************************************\
	* phpGroupWare - Notes eTemplate Port                                       *
	* http://www.phpgroupware.org                                               *
	* Written by : Bettina Gille [ceb@phpgroupware.org]                         *
	*              Andy Holman (LoCdOg)                                         *
	* Ported to eTemplate by Ralf Becker [ralfbecker@outdoor-training.de]       *
	* -------------------------------------------------                         *
	* This program is free software; you can redistribute it and/or modify it   *
	* under the terms of the GNU General Public License as published by the     *
	* Free Software Foundation; either version 2 of the License, or (at your    *
	* option) any later version.                                                *
	\***************************************************************************/
	/* $Id$ */

	class ui
	{
		var $grants;
		var $session_data;
		var $message;

		var $public_functions = array
		(
			'index'  => True,
			'view'   => True,
			'add'    => True,
			'edit'   => True,
			'delete' => True,
			'preferences' => True
		);

		function ui()
		{
			$this->cats			= CreateObject('phpgwapi.categories');
			$this->account		= $GLOBALS['phpgw_info']['user']['account_id'];
			$this->tpl			= CreateObject('etemplate.etemplate','et_notes.edit');
			$this->bo			= CreateObject('et_notes.bo',True);

			$this->session_data = array(
				'start'	=> $this->bo->start,
				'search'	=> $this->bo->search,
				'filter'	=> $this->bo->filter,
				'cat_id'	=> $this->bo->cat_id
			);
			$this->data			= $this->bo->data;
		}

		function save_sessiondata()
		{
			$this->bo->save_sessiondata($this->session_data);
		}

		function index($values = 0)
		{
			//echo "<p>notes.ui.index: values = "; _debug_array($values);

			if (!is_array($values))
			{
				$values = array('nm' => $this->session_data);
			}
			if ($values['add'] || $values['cats'] || isset($values['nm']['rows']))
			{
				$this->session_data = $values['nm'];
				unset($this->session_data['rows']);
				$this->save_sessiondata();

				if ($values['add'])
				{
					return $this->edit();
				}
				elseif ($values['cats'])
				{
					Header('Location: ' .$GLOBALS['phpgw']->link('/index.php?menuaction=preferences.uicategories.index&cats_app=et_notes&cats_level=True&global_cats=True'));
					$GLOBALS['phpgw']->common->phpgw_exit();
				}
				elseif (isset($values['nm']['rows']['view']))
				{
					list($id) = each($values['nm']['rows']['view']);
					return $this->view($id);
				}
				elseif (isset($values['nm']['rows']['edit']))
				{
					list($id) = each($values['nm']['rows']['edit']);
					return $this->edit($id);
				}
				elseif (isset($values['nm']['rows']['delete']))
				{
					list($id) = each($values['nm']['rows']['delete']);
					return $this->delete($id);
				}
			}
			$this->tpl->read('et_notes.index');

			$values['nm']['options-filter'] = array
			(
				'all'			=> 'Show all',
				'public'		=> 'Only yours',
				'private'	=> 'Private'
			);
			$values['nm']['get_rows'] = 'et_notes.bo.get_rows';
			$values['nm']['no_filter2'] = True;
			$values['user'] = $GLOBALS['phpgw_info']['user']['fullname'];

			$this->tpl->exec('et_notes.ui.index',$values);
		}

		function edit($values=0,$view=False)
		{
			//echo "<p>notes.ui.edit():"; _debug_array($values);

			if (!is_array($values))
			{
				$id = $values > 0 ? $values : get_var('id',array('POST','GET'));
				$values = array( );
			}
			else
			{
				$id = $values['id'];
			}
			if ($id > 0)
			{
				$content = $this->bo->read_single($id);
			}
			else
			{
				$content = array();
			}
			if ($this->debug)
			{
				echo '<p>edit: id=$id, values = ' .  _debug_array($values);
			}
			if ($values['save'])
			{
				$this->bo->save($values);
				return $this->index();
			}
			elseif($values['done'])
			{
				return $this->index();
			}
			elseif($values['delete'])
			{
				return $this->delete($values['id']);
			}
			elseif($values['reset'])
			{
				$content = array();
			}
			elseif($values['cats'])
			{
				Header('Location: ' .$GLOBALS['phpgw']->link('/index.php?menuaction=preferences.uicategories.index&cats_app=et_notes&cats_level=True&global_cats=True'));
				$GLOBALS['phpgw']->common->phpgw_exit();
			}
			if ($view)
			{
				$content['header'] = 'Notes - View note for';
				$this->tpl->read('et_notes.view');
				$no_button['content'] = $no_button['cat_id'] = $no_button['access'] = True; // make the tpl readonly
				$no_button['delete'] = !$this->bo->check_perms($this->bo->grants[$content['owner']],PHPGW_ACL_DELETE);
				$no_button['edit'] = !$this->bo->check_perms($this->bo->grants[$content['owner']],PHPGW_ACL_EDIT);
			}
			else
			{
				if ($content['id'] <= 0)
				{
					$no_button['delete'] = True;
					$content['header'] = 'Notes - Add note for';
					$content['owner'] = $GLOBALS['phpgw_info']['user']['account_id'];
				}
				else
				{
					$no_button['reset']  = True;
					$no_button['delete'] = !$this->bo->check_perms($this->bo->grants[$content['owner']],PHPGW_ACL_DELETE);
					$content['header'] = 'Notes - Edit note for';
				}
			}
			$content['user'] = $GLOBALS['phpgw_info']['user']['fullname'];

			$this->tpl->exec('et_notes.ui.edit',$content,'',$no_button,array('id' => $id));
		}

		function view($id)
		{
			$this->edit($id,True);
		}

		function delete($values=0)
		{
			if (!is_array($values))
			{
				if (!$values)
				{
					$values = get_var('id',array('POST','GET'));
				}
				if ($values > 0)
				{
					$content = $this->bo->read_single($values);
					$this->tpl->read('et_notes.delete');
					$this->tpl->exec('et_notes.ui.delete',$content,'','',array('id' => $values));
					return;
				}
			}
			elseif ($values['confirm'])
			{
				$this->bo->delete($values['id']);
			}
			$this->index();
		}
	}
?>
