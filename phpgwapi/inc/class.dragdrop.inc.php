<?php
/**
 * PHPGWAPI Dragdrop - generates javascript for Walter Zorns dragdrop class 
 *
 * @link www.egroupware.org
 * @author Christian Binder <christian.binder@freakmail.de>
 * @copyright (c) 2006 by Christian Binder <christian.binder@freakmail.de>
 * @package phpgwapi
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.dragdrop.inc.php 22783 2006-11-02 13:52:24Z jaytraxx $ 
 */

/**
 * General object containing the draggables and droppables
 *
 * @package phpgwapi
 * @author Christian Binder <christian.binder@freakmail.de>
 * @copyright (c) 2006 by Christian Binder <christian.binder@freakmail.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
class dragdrop
{

	/**
	 * draggable Objects
	 * 
	 * @var array
	 */
	var $draggables;

	/**
	 * droppable Objects
	 * 
	 * @var array
	 */
	var $droppables;
	
	/**
	 * ensures that function setJSCode is only run once
	 * 
	 * @var boolean
	 */
	var $setCodeDone = false;

	/**
	 * JavaScript(s) to include which contains the actions while dragging or dropping
	 * 
	 * @var array
	 */
	var $actionScripts;
	
	function dragdrop()
	{
	}

	/**
	 * adds a Draggable javascript object
	 *
	 * @param string $name unique html id of the object
	 * @param mixed $value=false optional value of the object - readable by the drop ActionScript
	 * @param string $dragAction=false ActionScript executed while item is dragged e.g. calendar.myscript.mydrag
	 * @param string $dropAction=false ActionScript executed when item is dropped e.g. calendar.myscript.mydrop
	 * @return boolean true if all actions succeded, false otherwise
	 */
	function addDraggable($name,$value = false,$dragAction = false,$dropAction = false)
	{
		if(!$this->checkUnique($name)) { return false; }
			$this->draggables[] = array('name'=>$name,'value'=>$value,'dragAction'=>$this->registerActionScript($dragAction),'dropAction'=>$this->registerActionScript($dropAction));
		return true;
	}

	/**
	 * adds a Droppable javascript object
	 *
	 * @param string $name unique html id of the object
	 * @param mixed $value value of the object - readable by the drop ActionScript
 	 * @return boolean true if all actions succeded, false otherwise
	 */
	function addDroppable($name,$value = false)
	{
		if(!$this->checkUnique($name)) { return false; }
			$this->droppables[] = array('name'=>$name,'value'=>$value);
		return true;
	}

	/**
	 * generates the appropriate JSCode for all defined objects
	 *
	 * @return boolean true if all actions succeed or false if the function was called more than once 
	 */
	function setJSCode()
	{
		// this function can only be run once, so we check that at the beginning
		if($this->setCodeDone) { return false; }
		
		$GLOBALS['egw_info']['flags']['need_footer'] .= "<!-- BEGIN JavaScript for wz_dragdrop.js -->\n";

		// include wz_dragdrop once
		if(!$GLOBALS['egw_info']['flags']['wz_dragdrop_included'])
		{
			$GLOBALS['egw_info']['flags']['need_footer'] .= '<script language="JavaScript" type="text/javascript" src="'.$GLOBALS['egw_info']['server']['webserver_url'].'/phpgwapi/js/wz_dragdrop/wz_dragdrop.js"></script>'."\n";
			$GLOBALS['egw_info']['flags']['wz_dragdrop_included'] = True;
		}
		
		// include actionScripts
		if(is_array($this->actionScripts))
		{
			foreach($this->actionScripts as $i => $actionScript)
			{
				$GLOBALS['egw_info']['flags']['need_footer'] .= "<script language='JavaScript' type='text/javascript' src='".$actionScript['file']."'></script>\n";
			}
		}
		
		// register all dragdrop elements to wz_dragdrop
		if(is_array($this->draggables))
		{
			foreach($this->draggables as $i=>$element)
			{
				$element_names_array[] = "\"".$element['name']."\"";
			}
		}
		if(is_array($this->droppables))
		{
			foreach($this->droppables as $i=>$element)
			{
				$element_names_array[] = "\"".$element['name']."\"";
			}
		}
		if(is_array($element_names_array))
		{
			$element_names=implode(",",$element_names_array);
			$GLOBALS['egw_info']['flags']['need_footer'] .= '<script language="JavaScript" type="text/javascript">SET_DHTML(SCROLL,TRANSPARENT,CURSOR_HAND,'.$element_names.');</script>'."\n";
		}
		
		// set special params for draggable elements
		if(is_array($this->draggables))
		{
			$GLOBALS['egw_info']['flags']['need_footer'] .= '<script language="JavaScript" type="text/javascript">'."\n";
			foreach($this->draggables as $i=>$element)
			{
				if($element['value']) { $GLOBALS['egw_info']['flags']['need_footer'] .= 'dd.elements.'.$element['name'].'.value = "'.$element['value'].'";'."\n"; }
				if($element['dragAction']) { $GLOBALS['egw_info']['flags']['need_footer'] .= 'dd.elements.'.$element['name'].'.setDragFunc('.$element['dragAction'].');'."\n"; }
				if($element['dropAction']) { $GLOBALS['egw_info']['flags']['need_footer'] .= 'dd.elements.'.$element['name'].'.setDropFunc('.$element['dropAction'].');'."\n"; }
			}
			$GLOBALS['egw_info']['flags']['need_footer'] .= '</script>'."\n";
		}

		// set special params for droppable elements
		if(is_array($this->droppables))
		{
			$GLOBALS['egw_info']['flags']['need_footer'] .= '<script language="JavaScript" type="text/javascript">'."\n";
			foreach($this->droppables as $i=>$element)
			{
				if($element['value']) { $GLOBALS['egw_info']['flags']['need_footer'] .= 'dd.elements.'.$element['name'].'.value = "'.$element['value'].'";'."\n"; }
				$GLOBALS['egw_info']['flags']['need_footer'] .= 'dd.elements.'.$element['name'].'.setDraggable(false);'."\n";
			}
			$GLOBALS['egw_info']['flags']['need_footer'] .= '</script>'."\n";
		}

		$GLOBALS['egw_info']['flags']['need_footer'] .= "<!-- END JavaScript for wz_dragdrop.js -->\n";
		return $this->setCodeDone = true;	
	}

	
	/**
	 * checks if the given name of an object is unique in all draggable AND droppable objects
	 *
	 * @param string $name unique html id of the object
	 * @return boolean true if $name is unique, otherwise false
	 */
	function checkUnique($name)
	{
		if(is_array($this->draggables))
		{
			foreach($this->draggables as $i=>$element)
			{
				if($element['name'] == $name)
				{
					error_log("class.dragdrop.inc.php::addDraggable duplicate name for object '".$name."'");
					return false;
				}
			}
		}
		if(is_array($this->droppables))
		{
			foreach($this->droppables as $i=>$element)
			{
				if($element['name'] == $name)
				{
					error_log("class.dragdrop.inc.php::addDraggable duplicate name for object '".$name."'");
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * registers additional javascript file(s) which contain scripts for various object actions
	 * and handles duplicates
	 *
	 * @param string $script script to register, e.g. 'calendar.dragdrop.moveEvent'
	 * @return string $functionname if JavaScript file exists, otherwise false
	*/
	function registerActionScript($script)
	{
		list($appname,$scriptname,$functionname) = explode('.',$script);
		$script = $appname.".".$scriptname;
		$serverFile = EGW_INCLUDE_ROOT.'/'.$appname.'/js/'.$scriptname.'.js';
		$browserFile = $GLOBALS['egw_info']['server']['webserver_url'].'/'.$appname.'/js/'.$scriptname.'.js';
	
		// check if file exists otherwise exit
		if(!file_exists($serverFile))
		{
			return false;
		}
		// check duplicates
		if(is_array($this->actionScripts))
		{
			foreach($this->actionScripts as $i=>$actionScript)
			{
				if($actionScript['script'] == $script) { return $functionname; }
			}
		}
	
		$this->actionScripts[] = array('script' => $script,'file' => $browserFile);
		return $functionname;
	}

}
