<?php

	class uiactions 
	{	
		var $action_classes = array(
			0=>'filemanager.uiaction_edit'
		);
		var $actions = array();
		
		function uiactions()
		{
			//Construct the action objects
			foreach($this->action_classes as $action_class)
			{
				$o = CreateObject($action_class);
				foreach ($o->actions as $name => $displayname)
				{
					$this->actions[$name] = &$o;
				}
			}
		}
		function run_action($parent, $action)
		{

			
	//		print_r($this->actions);
			$this->actions[$action]->$action($parent);
			exit();
		}
		function dispatch($parent)
		{
			//First, see if the action is specified in the url with a 'uiaction=' param
			if ($action = get_var('uiaction', array('GET', 'POST')))
			{
				$this->run_action($parent, $action);	
			}
			@reset($_POST);
			while(list($name,$value) = each($_POST))
			{
				if (substr($name, 0 , 8) == 'uiaction')
				{
					$action = substr($name, 9);
					$this->run_action($parent, $action);

				}
			}
			
		}
	}
?>
