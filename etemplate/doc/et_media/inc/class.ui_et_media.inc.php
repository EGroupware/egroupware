<?php
/**
 * eGroupWare editable Templates - Example media database (et_media)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage et_media
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$ 
 */

include_once(EGW_INCLUDE_ROOT . '/et_media/inc/class.bo_et_media.inc.php');

class ui_et_media extends bo_et_media
{
	/**
	 * Public functions callable via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'edit' => True,
		'writeLangFile' => True
	);

	/**
	 * Constructor
	 *
	 * @return ui_et_media
	 */
	function ui_et_media()
	{
		$this->bo_et_media();	// calling the constructor of the extended bo object

		$this->tmpl =& CreateObject('etemplate.etemplate','et_media.edit');
	}

	/**
	 * Edit a media database entry
	 *
	 * @param array $content=null
	 * @param string $msg=''
	 */
	function edit($content=null,$msg = '')
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
				$msg .= !$this->save() ? lang('Entry saved') : lang('Error: while saving !!!');
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

	/**
	 * Showing entries from the media database
	 *
	 * @param array $found
	 */
	function show($found=null)
	{
		if (!is_array($found) || !count($found))
		{
			$this->edit();
			return;
		}
		array_unshift($found,false);	// change the array to start with index 1
		$content = array(
			'msg' => lang('%1 matches on search criteria',count($found)),
			'entry' => $found,
		);
		$this->tmpl->read('et_media.show');

		$this->tmpl->exec('et_media.et_media.edit',$content);
	}
}
