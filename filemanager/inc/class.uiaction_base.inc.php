<?php

	class uiaction_base
	{
		function load_header()
		{
			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			unset($GLOBALS['phpgw_info']['flags']['noappheader']);
			unset($GLOBALS['phpgw_info']['flags']['noappfooter']);
			//$GLOBALS['phpgw']->common->phpgw_header();
		}
		
		function action_link($action)
		{
			return $GLOBALS['phpgw']->link('/index.php',
							Array(
								'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.action',
								'path'		=> urlencode($this->bo->path),
								'uiaction' => urlencode($action)
							)
						);
					
		}
	}


?>