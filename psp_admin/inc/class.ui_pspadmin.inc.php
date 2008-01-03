<?php
/**
*  
*
*
*
*
*
*
**/

class ui_pspadmin
{
   var $bo;
   var $sav2;

   var $wrapper;
   var $module;
   
   var $public_functions = Array
   (
	  'modules' => True,
	  'settings' => True,
	  'apitesting' => True,
	  'sidebox_menu' => True
   );
 
   function ui_pspadmin()
   {
	  $this->sav2 = CreateObject('phpgwapi.tplsavant2');
	  $this->bo = CreateObject('psp_admin.bo_pspadmin');

	  // nec.
	  if(!$this->checks())
	  {
		 die('UI:: checks failed.');
	  }
	  if(isset($_POST['wrapper']))
	  {
		 $this->wrapper = $_POST['wrapper'];
	  } else     // this is for now... but should be handled by POST
	  {
		 $this->wrapper = 'oscommerce';
	  }
	  $this->module = $_GET['plug'];
   }

   function sidebox_menu()
   {
	  $appname = 'psp_admin';
	  $content = Array(    
		 '0' => array(     
			'link'=>$GLOBALS['phpgw']->link('/index.php','menuaction=psp_admin.ui_pspadmin.modules'),
			'icon'=>( ($_GET['menuaction']=='psp_admin.ui_pspadmin.modules'||!$_GET['menuaction']) ?'c_plan_a':'c_plan'),
			'text'=>'Modules'            
		 ),
		 '1' => array(
			'link'=>$GLOBALS['phpgw']->link('/index.php','menuaction=psp_admin.ui_pspadmin.settings'),
			'icon'=>( ($_GET['menuaction']=='psp_admin.ui_pspadmin.settings'||!$_GET['menuaction']) ?'settings':'settings'),
			'text'=>'Settings'
		 )
	  );
	  $menu_title = lang('PSP Admin');
	  display_sidebox($appname,$menu_title,$content);
   }

   function showMe($template)
   {
	  $this->sav2->baselink = $GLOBALS['phpgw']->link('/index.php','menuaction=psp_admin.ui_pspadmin.').$template;
	  $GLOBALS['egw']->common->phpgw_header();
	  echo parse_navbar();
	  $this->sav2->display($template.'.tpl.php');
	  $GLOBALS['egw']->common->phpgw_footer();
   }
     
   function modules()
   {
	  switch($_POST['submitted'])
	  {
		 case false:
		 break;
		 case 'update':
		 $this->process_post( 'modules' , $_POST);
		 case 'cancel':
		 $this->sav2->module = $this->module = $_POST['payplug'];
		 $_res = $this->bo->config_module($this->module);
		 $this->sav2->selection = $_res[0];
		 $this->sav2->modinfo = $this->sav2->fetch('confmod.tpl.php');
		 break;
		 default:
		 die('no strange posting here.');
	  }

	  $this->sav2->wrapper = $this->wrapper;

	  if(isset($_GET['act']) && isset($_GET['plug']) && True) // todo: True replaced by sec.expression
	  {
		 if($this->wrapper == 'oscommerce')
		 {
			$this->sav2->module = $_GET['plug'];
			switch($_GET['act'])
			{
			   case 'editmod': // when we actually want to change something
			   $selection = $this->bo->config_module($this->module);
			   $this->sav2->editline = 'no';
			   $this->sav2->dim2array = $this->gen_form('modules',$selection[0],'text', '10','255',$this->module);
			   $result =  $this->sav2->fetch('generic_form.tpl.php');
			   break;
			   case 'install':
			   $result = $this->bo->oscom_install($_GET['plug']);
			   break;
			   case 'remove':
			   $result = $this->bo->oscom_remove($_GET['plug']);
			   break;
			   case 'broken':
			   $result = $this->bo->oscom_broken($_GET['plug']);
			   break;
			   case 'conf':
			   $_res = $this->bo->config_module($this->module);  //$_GET['plug']); hm.., redundancy
			   $this->sav2->selection = $_res[0]; 
			   $result = $this->sav2->fetch('confmod.tpl.php');
			   break;
			   default:
			   die('illegal call to function.');
			}
			$this->sav2->modinfo = $result;
		 }
		 else $this->sav2->infomsg = 'oh? another wrapper?';
	  }
	  $mod_overview = $this->bo->get_installed_modules($this->wrapper);
	  if(!is_array($mod_overview)) $this->sav2->infomsg = $mod_overview; // on error: display it
	  else $this->sav2->list = $mod_overview;


	  $this->showMe('modules');
   }

   function process_post( $from ,$arr)
   {
	  switch($from)
	  {
		 case 'modules':
		 $this->module = $arr['payplug'];
		 unset($arr['payplug']); unset($arr['submitted']);
		 break;
		 case 'settings':
		 unset($arr['submitted']);
		 break;
		 default:
		 die('nope.');
	  }
	  foreach( $arr as $key=>$value )
	  {
		 $this->bo->so->confedit($key, $value);
	  }
   }

   function settings()
   {
	  switch($_POST['submitted'])
	  {
		 case false:
		 break;
		 case 'update':
		 $this->process_post( 'settings' , $_POST);
		 break;
		 case 'cancel':
		 break;
		 default:
		 die('no strange posting here.');
		 break;
	  }


	  $this->sav2->settings = $this->bo->so->get_settings();
	  $this->sav2->wrapper = $this->wrapper;
	  $this->showMe('settings');
   }
   
   function checks()
   {
	  // check if this page is reached in a proper way

	  // check if the necessary tables exist and are sane
	  $_ret = $this->bo->so->check_tables();
	  if(!$_ret) $this->sav2->infomsg = $_ret;
	  else return True;
   }
  
   function gen_form($link2func, $update, $type, $size, $maxl, $payplug)
   {
	  $this->sav2->form['baselink'] = $GLOBALS['phpgw']->link('/index.php','menuaction=psp_admin.ui_pspadmin.'.$link2func);
	  $this->sav2->form['update'] = $update;
	  $this->sav2->form['type'] = $type;
	  $this->sav2->form['size'] = $size;
	  $this->sav2->form['max'] = $maxl;
	  $this->sav2->form['payplug'] = $payplug;
	  $this->sav2->genset_form = $this->sav2->fetch('generic_form.tpl.php');
   }

   
   // ==================================================
   // begin skeleton : api access
/*
   function apitesting()
   {
	  //  
	  $fail_url = 'psp_admin.ui_pspadmin.apifail';
	  $success_url = 'psp_admin.ui_pspadmin.apisuccess';
	  $base_url = 'psp_admin.ui_pspadmin.apitesting' ; // app.class.function

	  $stoken = md5(uniqid(rand(), true));

	  if($_POST[submitted] != True)
	  {
		 $this->sav2->assign('step',1);
		 $this->sav2->assign('title',"1");
		 $content = $this->sav2->fetch("stepbefore.tpl.php");
	  }
	  else
	  {
		 require_once(EGW_SERVER_ROOT.'/psp_admin/inc/class.oscadminapi.inc.php');
		 $oscapi = new oscadminapi();
		 $content = $oscapi->purchase($stoken, $amount, $fail_url, $success_url, $base_url, $_POST);
	  }


	  $this->sav2->assign('content',$content);
	  $this->showMe('apitest');

   }

   function apifail()
   {
	  die('something went wrong. please try again.');
   }

   function apisuccess()
   {
	  die('Thank You.');
   }
   */

   // end testing of oscadminapi
   // ==================================================


}

?>

