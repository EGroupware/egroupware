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

require_once(EGW_INCLUDE_ROOT. '/phpgwapi/inc/class.browser.inc.php');

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
	 * custom DHTML Objects
	 * 
	 * @var array
	 */
	var $customs;
	
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

	/**
	 * enables class for all browsers - use this for testing still not validated browsers
	 * 
	 * @var boolean
	 */
	var $browserTestMode = false;
	
	function dragdrop()
	{
	}

	/**
	 * adds a Draggable DHTML object
	 *
	 * @param string $name unique html id of the object
	 * @param array $values=false optional associative array with values of the object
	 * @param string $dragAction=false ActionScript executed while item is dragged e.g. calendar.myscript.mydrag
	 * @param string $dropAction=false ActionScript executed when item is dropped e.g. calendar.myscript.mydrop
	 * @param string $focus=false position of the focus for underlying objects, something like 'top left 5' or 'center center 0'
	 * @return boolean true if all actions succeded, false otherwise
	 */
	function addDraggable($name,$values = false,$dragAction = false,$dropAction = false,$focus = false)
	{
		if(!$this->checkUnique($name)) { return false; }
		$this->draggables[] = array('name'=>$name,'values'=>$values,'dragAction'=>$this->registerActionScript($dragAction),'dropAction'=>$this->registerActionScript($dropAction),'focus'=>$this->addApostrophes($focus));
		return true;
	}

	/**
	 * adds a Droppable DHTML object
	 *
	 * @param string $name unique html id of the object
	 * @param array $values=false optional associative array with values of the object
 	 * @return boolean true if all actions succeded, false otherwise
	 */
	function addDroppable($name,$values = false)
	{
		if(!$this->checkUnique($name)) { return false; }
		$this->droppables[] = array('name'=>$name,'values'=>$values);
		return true;
	}

	/**
	 * adds a Custom DHTML object
	 *
	 * @param string $name unique html id of the object
	 * @param array $commands=false optional array with commands for the object,
	 *		e.g. CURSOR_HAND or NO_DRAG like described on walter zorns homepage
	 *		http://www.walterzorn.com
	 * @param array $values=false optional associative array with values of the object
	 * @param string $dragAction=false ActionScript executed while item is dragged e.g. calendar.myscript.mydrag
	 * @param string $dropAction=false ActionScript executed when item is dropped e.g. calendar.myscript.mydrop
 	 * @return boolean true if all actions succeded, false otherwise
	 */
	function addCustom($name,$commands = false,$values = false,$dragAction = false,$dropAction = false)
	{
		if(!$this->checkUnique($name)) { return false; }
		$this->customs[] = array('name'=>$name,'commands'=>$commands,'values'=>$values,'dragAction'=>$this->registerActionScript($dragAction),'dropAction'=>$this->registerActionScript($dropAction));
		return true;
	}

	/**
	 * generates the appropriate JSCode for all defined objects
	 *
	 * @return boolean true if all actions succeed or false if the function was called more than once 
	 */
	function setJSCode()
	{
		// check that dragdrop is enabled by prefs and that we have a supported browser
		if(	!$GLOBALS['egw_info']['user']['preferences']['common']['enable_dragdrop'] ||
			!$this->validateBrowser()
		)
		{
			return false;
		}

		// this function can only be run once, so we check that at the beginning
		if($this->setCodeDone)
		{
			error_log('phpgwapi.dragdrop::setJSCode called more than once - aborting');
			return false;
		}
		
		$GLOBALS['egw_info']['flags']['need_footer'] .= "<!-- BEGIN JavaScript for wz_dragdrop.js -->\n";

		// include wz_dragdrop once
		if(!$GLOBALS['egw_info']['flags']['wz_dragdrop_included'])
		{
			$GLOBALS['egw_info']['flags']['need_footer'] .= '<script language="JavaScript" type="text/javascript" src="'.$GLOBALS['egw_info']['server']['webserver_url'].'/phpgwapi/js/wz_dragdrop/wz_dragdrop.js"></script>'."\n";
			$GLOBALS['egw_info']['flags']['wz_dragdrop_included'] = true;
		}
		
		// include actionScripts
		if(is_array($this->actionScripts))
		{
			foreach($this->actionScripts as $i => $actionScript)
			{
				$GLOBALS['egw_info']['flags']['need_footer'] .= '<script language="JavaScript" type="text/javascript" src="'.$actionScript['file'].'"></script>'."\n";
			}
		}
		
		// register all elements to wz_dragdrop
		if(is_array($this->draggables))
		{
			foreach($this->draggables as $i=>$element)
			{
				$element_names_array[] = '"'.$element['name'].'"+CURSOR_HAND+TRANSPARENT+SCROLL';
			}
		}
		if(is_array($this->droppables))
		{
			foreach($this->droppables as $i=>$element)
			{
				$element_names_array[] = '"'.$element['name'].'"';
			}
		}
		if(is_array($this->customs))
		{
			foreach($this->customs as $i=>$element)
			{
				$element_names_array[] = '"'.$element['name'].'"+'.implode('+',$element['commands']);
			}
		}
		if(is_array($element_names_array))
		{
			$element_names=implode(',',$element_names_array);
			$GLOBALS['egw_info']['flags']['need_footer'] .= '<script language="JavaScript" type="text/javascript">'."\n";
			$GLOBALS['egw_info']['flags']['need_footer'] .= $this->DHTMLcommand().'('.$element_names.')'."\n";
			$GLOBALS['egw_info']['flags']['need_footer'] .= '</script>'."\n";
		}
		
		// set special params for draggable elements
		if(is_array($this->draggables))
		{
			$GLOBALS['egw_info']['flags']['need_footer'] .= '<script language="JavaScript" type="text/javascript">'."\n";
			foreach($this->draggables as $i=>$element)
			{
				if(is_array($element['values']))
				{
					foreach($element['values'] as $val_name=>$val_value)
					{
						if($val_value)
						{
							$GLOBALS['egw_info']['flags']['need_footer'] .= 'dd.elements.'.$element['name'].'.my_'.$val_name.' = "'.$val_value.'";'."\n";
						}
					}
				}
				if($element['dragAction']) { $GLOBALS['egw_info']['flags']['need_footer'] .= 'dd.elements.'.$element['name'].'.setDragFunc('.$element['dragAction'].');'."\n"; }
				if($element['dropAction']) { $GLOBALS['egw_info']['flags']['need_footer'] .= 'dd.elements.'.$element['name'].'.setDropFunc('.$element['dropAction'].');'."\n"; }
				if($element['focus']) { $GLOBALS['egw_info']['flags']['need_footer'] .= 'dd.elements.'.$element['name'].'.setFocus('.$element['focus'].');'."\n"; }
			}
			$GLOBALS['egw_info']['flags']['need_footer'] .= '</script>'."\n";
		}

		// set special params for droppable elements
		if(is_array($this->droppables))
		{
			$GLOBALS['egw_info']['flags']['need_footer'] .= '<script language="JavaScript" type="text/javascript">'."\n";
			foreach($this->droppables as $i=>$element)
			{
				if(is_array($element['values']))
				{
					foreach($element['values'] as $val_name=>$val_value)
					{
						if($val_value)
						{
							$GLOBALS['egw_info']['flags']['need_footer'] .= 'dd.elements.'.$element['name'].'.my_'.$val_name.' = "'.$val_value.'";'."\n";
						}
					}
				}
				$GLOBALS['egw_info']['flags']['need_footer'] .= 'dd.elements.'.$element['name'].'.setDraggable(false);'."\n";
			}
			$GLOBALS['egw_info']['flags']['need_footer'] .= '</script>'."\n";
		}

		// set special params for custom elements
		if(is_array($this->customs))
		{
			$GLOBALS['egw_info']['flags']['need_footer'] .= '<script language="JavaScript" type="text/javascript">'."\n";
			foreach($this->customs as $i=>$element)
			{
				if(is_array($element['values']))
				{
					foreach($element['values'] as $val_name=>$val_value)
					{
						if($val_value)
						{
							$GLOBALS['egw_info']['flags']['need_footer'] .= 'dd.elements.'.$element['name'].'.my_'.$val_name.' = "'.$val_value.'";'."\n";
						}
					}
				}
				if($element['dragAction']) { $GLOBALS['egw_info']['flags']['need_footer'] .= 'dd.elements.'.$element['name'].'.setDragFunc('.$element['dragAction'].');'."\n"; }
				if($element['dropAction']) { $GLOBALS['egw_info']['flags']['need_footer'] .= 'dd.elements.'.$element['name'].'.setDropFunc('.$element['dropAction'].');'."\n"; }
			}
			$GLOBALS['egw_info']['flags']['need_footer'] .= '</script>'."\n";
		}

		$GLOBALS['egw_info']['flags']['need_footer'] .= "<!-- END JavaScript for wz_dragdrop.js -->\n";
		return $this->setCodeDone = true;	
	}

	
	/**
	 * checks if the given name of an object is unique in all draggable,droppable and custom objects
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
					error_log('class.dragdrop.inc.php: duplicate name for object "'.$name.'"');
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
					error_log('class.dragdrop.inc.php: duplicate name for object "'.$name.'"');
					return false;
				}
			}
		}
		if(is_array($this->customs))
		{
			foreach($this->customs as $i=>$element)
			{
				if($element['name'] == $name)
				{
					error_log('class.dragdrop.inc.php: duplicate name for object "'.$name.'"');
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * checks if the browser is validated to work with the dragdrop class
	 * used to enable/disable this class for the clients browser
	 *
	 * @return boolean true if browser is validated, otherwise false
	*/
	function validateBrowser()
	{
		$clientBrowser = new browser();

		if($this->browserTestMode)
		{
			error_log('dragdrop::validateBrowser, agent: ' . $clientBrowser->get_agent());
		}
 
		foreach(array('MOZILLA') as $id=>$validatedBrowser)
		{
			if($this->browserTestMode || $clientBrowser->get_agent() == $validatedBrowser)
			{
				return true;
			}
			
			return false;
		}
			
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
		$script = $appname.'.'.$scriptname;
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

	/**
	 * adds apostrophes to each value in a space separated string
	 *
	 * @param string $val space separated values
	 * @return string comma separated values in apostrophes if $val is true, otherwise false
	*/
	function addApostrophes($val=false)
	{
		if($val)
		{
			foreach(explode(' ',$val) as $id=>$value)
			{
				$apostropheVal[] = '"'.$value.'"';
			}
			return implode(',',$apostropheVal);
		}

		return false;
	}

	/**
	 * evaluate the right DHTML command for adding DHTML objects
	 *
	 * @return string 'SET_DHTML' or 'ADD_DHTML'
	*/
	function DHTMLcommand()
	{
		if(!$GLOBALS['egw_info']['flags']['wz_dragdrop_runonce_SET_DHTML'])
		{
			$GLOBALS['egw_info']['flags']['wz_dragdrop_runonce_SET_DHTML'] = true;
			return 'SET_DHTML';
		}
		else
		{
			return 'ADD_DHTML';
		}
	}

}
