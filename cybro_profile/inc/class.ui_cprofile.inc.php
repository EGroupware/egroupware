<?php
/**
*  
*
*
**/

class ui_cprofile
{
   var $sav2;
   var $bo;
   var $errormsg;

   var $public_functions = Array
   (
	  'registration' => True,
	  'personal' => True,
	  'security' => True,
	  'sidebox_menu' => False,
	  'assignValues' => False
   );
 
   function ui_cprofile()
   {
	  $this->sav2 = CreateObject('phpgwapi.tplsavant2');
	  $this->bo = CreateObject('cybro_profile.bo_cprofile');

	  $this->assignValues(); // for now... we need temp. data to see things work

   }

   function sidebox_menu()
   {
	  $appname = 'cybro_profile';
	  $content = Array(    
		 '0' => array(     
			'link'=>$GLOBALS['phpgw']->link('/index.php','menuaction=cybro_profile.ui_cprofile.registration'),
			'icon'=>(($_GET['menuaction']=='cybro_profile.ui_cprofile.registration')?'registration_active':'registration_inactive'),
			'text'=>'Registration Info'
		 ),                
		 '1' => array(
			'link'=>$GLOBALS['phpgw']->link('/index.php','menuaction=cybro_profile.ui_cprofile.personal'),
			'icon'=>(($_GET['menuaction']=='cybro_profile.ui_cprofile.personal') ? 'personal_active':'personal_inactive'),
			'text'=>'Personal Info'              
		 ),
		 '2' => array(
			'link'=>$GLOBALS['phpgw']->link('/index.php','menuaction=cybro_profile.ui_cprofile.security'),
			'icon'=>(($_GET['menuaction']=='cybro_profile.ui_cprofile.security') ? 'security_active':'security_inactive'),
			'text'=>'Security'
		 )
	  );
	  $menu_title = lang('Cybro Profile Menu');
	  display_sidebox($appname,$menu_title,$content);
   }

   function assignValues()
   {
	  // needed by
	  // :all
	  $this->sav2->defaultpic = '"'.EGW_INCLUDE_ROOT.'/myprofile/templates/default/images/photo.png'.'"';
	  //_debug_array($this->sav2->defaultpic);
	  $this->sav2->postpath = $GLOBALS['phpgw']->link('/index.php','menuaction=cybro_profile.ui_cprofile.'); 
	  // :registration
	  // :personal
	  // :security
   }

   function contentBox($template)
   {
	  if(isset($this->errormsg))
	  {
		 $this->sav2->errormsg = $this->errormsg;
	  }
	  $this->sav2->rootpath = $GLOBALS['egw_info']['server']['webserver_url'];
	  $this->sav2->postpath = $this->sav2->postpath.$template;
	  $GLOBALS['egw']->common->phpgw_header();
	  echo parse_navbar();
	  $this->sav2->display($template.'.tpl.php');
	  $GLOBALS['egw']->common->phpgw_footer();
   }
     
   function registration()
   {
	  if($_POST['save'] == 'Save')
	  {
		 $this->bo->cp_validate($_POST, 'in');
		 $this->errormsg[] = $this->bo->cp_updateValues('registration', $_POST);
	  }
	  $dbarr = $this->bo->cp_getValues('registration');
	  $this->sav2->cybroid=$this->bo->user_arr['USER_Name']; 
	  $this->sav2->firstname=$dbarr['firstname'];
	  $this->sav2->lastname=$dbarr['lastname'];  // todo: whitespaces?!
	  $this->sav2->cybemail=$dbarr['useremail']; 
	  $this->sav2->cybrorg=$dbarr['cybroorg'];
	  $this->sav2->sponsors=array(   // todo: sponsors from db
		 ''=>'Select one',
		 '1'=>'sponsor1',
		 '2'=>'sponsor2'
	  );
	  $this->sav2->sponstypes=array(
		 ''=>'Select one',
		 '1'=>'type1',
		 '2'=>'type2'
	  );
	  $this->contentBox('registration');
   }

   function personal()
   {
	  if(isset($_POST['savepic']))
	  {
		 //_debug_array($_POST);
	  }
	  if($_POST['save'] == 'Save')
	  {
		 // dont forget : in 'countries' may be something different
		 // dont change db if not necessary. default = 0. keys are saved.

		 $this->bo->cp_validate($_POST,'in');
		 $this->errormsg[] = $this->bo->cp_updateValues('personal', $_POST);
	  }

	  if (!is_object($GLOBALS['phpgw']->jscalendar))
	  {
		 $GLOBALS['phpgw']->jscalendar = CreateObject('phpgwapi.jscalendar');
	  }
	  $input = $GLOBALS['phpgw']->jscalendar->input($field_name,'',$year,$month,$day);
	  //$this->sav2->jscal = $input;
	  
	  $dbarr = $this->bo->cp_getValues('personal');
	  $pattern = '/(19|20)(\d{2})(\d{2})(\d{2})/';
	  $replacestr = '\1\2/\3/\4';
	  $this->sav2->birthday=preg_replace($pattern, $replacestr,$dbarr['birth']);
	  $this->sav2->cybprofession=$dbarr['cybprofession'];
	  $this->sav2->cybcardid=$dbarr['cybcardid'];
	  $this->sav2->adr_one_region=$dbarr['adroneregion']; 
	  $this->sav2->cyskype=$dbarr['cybskype'];
	  $this->sav2->adr_one_locality=$dbarr['adronelocality'];
	  $this->sav2->userurl=$dbarr['userurl'];
	  $this->sav2->tel_work=$dbarr['telwork'];
	  $this->sav2->tel_cell=$dbarr['telcell'];
	  $this->sav2->note=$dbarr['aboutmyself']; 

	  // ['language'] is in fact an array
	  // of up to 3 preferred langs: 
	  $this->sav2->preflang = split(',',($dbarr['language']),3);
	  $this->sav2->languages=$this->bo->cp_getLangs();
	  $defaultz = array(
		 'lang_id' => '',
		 'lang_name' => 'Select one'
	  );
	  array_unshift($this->sav2->languages, $defaultz);
	  
	  $countries = $this->bo->cp_getCountries();
	  array_unshift($countries, 'Select one');
	  $this->sav2->countries = $countries;
	  $this->sav2->mycountry = $dbarr['country'];

	  $cafes = $this->bo->cp_getcafelist();
	  $this->sav2->cafes = $cafes;
	  $defaultz = array(
		 'cafeid'=>'',
		 'cafename'=>'Select one'
	  );
	  array_unshift($this->sav2->cafes, $defaultz);
	  $this->sav2->mycafe = $dbarr['cybcafe'];
	  
	  $this->contentBox('personal');
   }

   function security()
   {
	  // validate the postvalues if submitted
	  if ($_POST['save'] == 'Save')
	  {
		 if($_POST['new_pwd'] != $_POST['confirm_pwd'])
		 {
			$this->errormsg[] = 'The two values for your new password do not match.';
			$this->errormsg[] = 'Please make sure you type the same password in the confirmation field.';
			$this->errormsg[] = 'password NOT changed.';
		 }
		 else
		 {
			$this->bo->cp_validate($_POST,'in');
			if($this->bo->cp_changepasswd($_POST))
			{
			   $this->errormsg[] = 'password changed.';
			}
			else
			{
			   $this->errormsg[] = 'password NOT changed.';
			}
		 }
	  }
	  // show results. but fake them of course.
	  $this->sav2->old_pwd='empty';
	  $this->sav2->new_pwd='empty';
	  $this->sav2->confirm_pwd='empty';
	  
	  $this->contentBox('security');
   }
}

?>

