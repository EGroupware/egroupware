<?php
   /**************************************************************************\
   * phpGroupWare                                                             *
   * http://www.phpgroupware.org                                              *
   * --------------------------------------------                             *
   *  This program is free software; you can redistribute it and/or modify it *
   *  under the terms of the GNU General Public License as published by the   *
   *  Free Software Foundation; either version 2 of the License, or (at your  *
   *  option) any later version.                                              *
   \**************************************************************************/

   if($GLOBALS['phpgw_info']['user']['preferences']['common']['show_generation_time'])
   {
	  $mtime = microtime(); 
	  $mtime = explode(' ',$mtime); 
	  $mtime = $mtime[1] + $mtime[0]; 
	  $GLOBALS['page_start_time'] = $mtime; 
   }


   // get used language code


   $lang_code = $GLOBALS['phpgw_info']['user']['preferences']['common']['lang'];

   /*
   ** Getting the correct directories for finding the resources	
   */
   $template_dir = $GLOBALS['phpgw_info']['server']['webserver_url'] . "/phpgwapi/templates/" . $GLOBALS['phpgw_info']['server']['template_set'];

   $js_url = $template_dir.'/js/'; 
   $css_url = $template_dir.'/css/';


   if($GLOBALS['phpgw_info']['flags']['currentapp']=='eGroupWare') 
   {
	  //Initializing x-desktop
	  $bodyheader = ' id="xdesktop"';
	  $theme_css = '<link rel="stylesheet" type="text/css" href="'.$css_url.'idots2_skin.css">';
	  $theme_css .= '<link rel="stylesheet" type="text/css" href="'.$css_url.'taskbar_down.css">';

	  $cbe_core = '<script type=\'text/javascript\' src=\''.$js_url.'x-desktop/cbe_core.js\'></script>';	
	  $cbe_event = '<script type=\'text/javascript\' src=\''.$js_url.'x-desktop/cbe_event.js\'></script>';	
	  $cbe_slide = '<script type=\'text/javascript\' src=\''.$js_url.'x-desktop/cbe_slide.js\'></script>';	
	  $skin_idots2 = '<script type=\'text/javascript\' src=\''.$js_url.'x-desktop/x-desktop_skin_IDOTS2.js\'></script>';	
	  $x_core = '<script type=\'text/javascript\' src=\''.$js_url.'x-desktop/x-desktop_core.js\'></script>';
	  $x_events =  '<script type =\'text/javascript\' src=\''.$js_url.'x-desktop/events.js\'></script>';
	  $x_shortcuts = '<script type =\'text/javascript\' src=\''.$js_url.'x-desktop/shortcuts.js\'></script>';
	  $xdesktop .= $theme_css . $cbe_core . $cbe_event . $cbe_slide . $skin_idots2 . $x_core . $x_events . $x_shortcuts;


   }
   else 
   {
	  //Just a normal page
	  $bodyheader = ' id="xpage"';
	  $menu_js = '<script type=\'text/javascript\' src=\''.$js_url.'menu.js\'></script>';	
	  $theme_css = '<link rel="stylesheet" type="text/css" href="'.$css_url.'idots2_page.css">';

	  $xdesktop = $theme_css . $menu_js;
   }


   /*
   ** Create/use the template 
   */
   $tpl = CreateObject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);
   $tpl->set_unknowns('remove');
   $tpl->set_file(array('_head' => 'head.tpl'));
   $tpl->set_block('_head','head');

   if ($GLOBALS['phpgw_info']['flags']['app_header'])
   {
	  $app = $GLOBALS['phpgw_info']['flags']['app_header'];
   }
   else
   {
	  $app = $GLOBALS['phpgw_info']['flags']['currentapp'];
	  $app = isset($GLOBALS['phpgw_info']['apps'][$app]) ? $GLOBALS['phpgw_info']['apps'][$app]['title'] : lang($app);
   }
   if($GLOBALS['phpgw_info']['flags']['currentapp']=='eGroupWare') 
   {
	  $app = "";

   }

   $var = Array(
	  'img_icon'      => PHPGW_IMAGES_DIR . '/favicon.ico',
	  'img_shortcut'  => PHPGW_IMAGES_DIR . '/favicon.ico',
	  'slider_effects'=> $slider_effects,
	  'simple_show_hide'=> $simple_show_hide,
	  'lang_code'	=> $lang_code,
	  'charset'       => $GLOBALS['phpgw']->translation->charset(),
	  'font_family'   => $GLOBALS['phpgw_info']['theme']['font'],
	  'website_title' => $GLOBALS['phpgw_info']['server']['site_title']. ($app ? " [$app]" : ''),
	  'body_tags'     => $bodyheader .' '. $GLOBALS['phpgw']->common->get_body_attribs(),
	  'xdesktop'      => $xdesktop,
	  'css'           => $GLOBALS['phpgw']->common->get_css(),
	  'bckGrnd'       => $bckGrnd,
	  'java_script'   => $GLOBALS['phpgw']->common->get_java_script(),
   );
   $tpl->set_var($var);
   $tpl->pfp('out','head');
   unset($tpl);
?>
