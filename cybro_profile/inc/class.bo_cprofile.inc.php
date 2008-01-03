<?php
/**
*
*
*
*  
**/
require_once(EGW_INCLUDE_ROOT.'/egwcybroapi/inc/class.bo_cybroapi.inc.php');

class bo_cprofile extends bo_cybroapi
{
   var $cp_so;

   function bo_cprofile()
   {
	  parent::bo_cybroapi();
	  $this->cp_so = CreateObject('cybro_profile.so_cprofile');
   }

   function cp_validate($formvals, $dir)
   {
	  //_debug_array($formvals);
	  //_debug_array($this->user_arr);
	  // form validation here
	  switch($dir)
	  {
		 case 'ennudoenweerechtietsmee':

			foreach($formvals as $key => $val)
			{
			   if($val == '') $formvals[$key] = "#empty"; 
			}
		 }

	  return $formvals;
   }

   function cp_getValues($template)
   { 
	  switch($template)
	  {
		 case 'registration':
		   $record = $this->cp_getregvals($this->user_arr['USER_Name']);
			break;
		 case 'personal':
			$record = $this->cp_getpersvals($this->user_arr['USER_Name']);
			break;
		 default:
			return 'illegal call to function.';
			break;
		 }
		 return $this->cp_validate($record, 'out');
   }


   function cp_updateValues($template, $arr)
   {
	  switch($template)
	  {
		 case 'registration':
			$msg = $this->cp_updateregvals($arr['firstname'],$arr['lastname']);
			if($msg['status'] == 200 )
			{
			   return 'registration info changed.';
			}
			else return 'update failed. information NOT changed.';
			break;
		 case 'personal':
			$procdarr = $arr;
			//_debug_array($GLOBALS['egw_info']['user']['preferences']['common']['lang']);
			$GLOBALS['egw_info']['user']['preferences']['common']['lang'] = $procdarr['language1'];
			$GLOBALS['egw']->translation->init();	
			//_debug_array($GLOBALS['egw_info']['user']['preferences']['common']['lang']);
			
			$msg = $this->cp_updatepersvals($procdarr);
			if($msg['status'] == 200)
			{
			   return 'personal info changed.';
			}
			else return 'update failed. information NOT changed.';
			break;
		 default:
			return 'illegal call to function.';
			break;
	  }
   }

   function cp_changepasswd($arr)
   {
	  $oldpass = $arr['old_pwd'];
	  $newpass = $arr['new_pwd'];
	  $msg = $this->update_password($this->user_arr['USER_Id'],$oldpass,$newpass);
	  return $msg;
   }

   function cp_getregvals($name)
   {
	  $dataArray = array( new xmlrpcval(
		 array (
			"supname" => new xmlrpcval($name)
		 ),
		 "struct")
	  );
	  $result=$this->xmlrpcdialog('get_profile_reg_vals',$dataArray,0);
	  if (!$result['faultcode'])
	  {
		 $_arr=array(
			'useremail',
			'cybroorg',
			'firstname',
			'lastname'
		 );
		 for($i=0; $i<$result['max']; $i++)
		 {
			$rec2=$result['v']->arraymem($i);//get the different array (VALUES) members, they are structs
			$ret=$this->getStructScalarFromArray($_arr,$rec2);
			return $ret;
		 }
	  } else $result['faultcode']; // todo:more "elegant" solution please
   }

   function cp_updateregvals($firstname, $lastname)
   {
	  $dataArray = array( new xmlrpcval(
		 array (
			"supid"     => new xmlrpcval($this->user_arr['USER_Id'],'int'),
			"firstname" => new xmlrpcval($firstname),
			"lastname"  => new xmlrpcval($lastname)
		 ),
		 "struct")
	  );
	  $result=$this->xmlrpcdialog('update_profile_reg_vals',$dataArray,0);
	  if (!$result['faultcode'])
	  {
		 $_arr=array(
			'status',
		 );
		 for($i=0; $i<$result['max']; $i++)
		 {
			$rec2=$result['v']->arraymem($i);//get the different array (VALUES) members, they are structs
			$ret=$this->getStructScalarFromArray($_arr,$rec2);
			return $ret;
		 }
	  } else return $result['faultcode']; // todo:more "elegant" solution please
   }

   function cp_getpersvals($name)
   {
	  $dataArray = array( new xmlrpcval(
		 array (
			"supname" => new xmlrpcval($name)
		 ),
		 "struct")
	  );
	  $result=$this->xmlrpcdialog('get_profile_pers_vals',$dataArray,0);
	  if (!$result['faultcode'])
	  {
		 $_arr=array(
			'language',
			'country',
			'cybcafe',
			'cybprofession',
			'birth',
			'cybcardid',
			'adroneregion',
			'cybskype',
			'adronelocality',
			'userurl',
			'telwork',
			'telcell',
			'aboutmyself'
		 );
		 for($i=0; $i<$result['max']; $i++)
		 {
			$rec2=$result['v']->arraymem($i);//get the different array (VALUES) members, they are structs
			$ret=$this->getStructScalarFromArray($_arr,$rec2);
		 }
	  } else return $result['faultcode']; // todo:more "elegant" solution please
	  return $ret;
   }

   function cp_getLangs()
   {
	  return $this->cp_so->getegwlangs();
   }

   function cp_getCountries()
   {
	  require_once(EGW_INCLUDE_ROOT.'/phpgwapi/inc/class.country.inc.php');
	  $country = CreateObject('phpgwapi.country');
	  return $country->countries();
   }

   function fixbirthd($str)
   {
	  $str = str_replace('/','',$str);
	  return $str;
   }

   function cp_updatepersvals($arr)
   {
	  foreach($arr as $key=>$arrval)
	  {
		 if ($arrval == '') 
		 { 
			$arr[$key] = NULL;
		 }
	  }
  
	  // stuff langs together
	  $lang = $arr['language1'].','.$arr['language2'].','.$arr['language3'];
	  $cprof = $arr['cybro_profession'];
	  $ccardid = $arr['cybro_cardid'];
	  $ccountry= $arr['adr_one_countryname'];
	  $ccafe   = $arr['cybro_cafe'];
	  $cregion = $arr['adr_one_region'];
	  $cskype  = $arr['cybro_skype'];
	  $clocal  = $arr['adr_one_locality'];
	  $userurl = $arr['userurl'];
	  $ctwork  = $arr['tel_work'];
	  $ctcell  = $arr['tel_cell'];
	  $cnote   = $arr['note'];
	  $cbirthd = $this->fixbirthd($arr['exec']['bday']['str']);
	  
	  $dataArray = array( new xmlrpcval(
		 array (
			"supid"     => new xmlrpcval($this->user_arr['USER_Id'],'int'),
			"language" => new xmlrpcval($lang),
			"cybprofession" => new xmlrpcval($cprof),
			"birthd" => new xmlrpcval($cbirthd,'int'),
			"cybcardid" => new xmlrpcval($ccardid,'int'),
			"country" => new xmlrpcval($ccountry),
			"cybcafe" => new xmlrpcval($ccafe),
			"region" => new xmlrpcval($cregion),
			"cybskype" => new xmlrpcval($cskype),
			"adronelocal" => new xmlrpcval($clocal),
			"userurl" => new xmlrpcval($userurl),
			"telwork" => new xmlrpcval($ctwork),
			"telcell" => new xmlrpcval($ctcell),
			"note" => new xmlrpcval($cnote),
		 ),
		 "struct")
	  );
	  $result=$this->xmlrpcdialog('update_profile_pers_vals',$dataArray,0);
	  if (!$result['faultcode'])
	  {
		 $_arr=array(
			'status',
		 );
		 for($i=0; $i<$result['max']; $i++)
		 {
			$rec2=$result['v']->arraymem($i);//get the different array (VALUES) members, they are structs
			$ret=$this->getStructScalarFromArray($_arr,$rec2);
			return $ret;
		 }
	  } 
	  else 
	  {
		 return '400';
	  }
   }

   function cp_getcafelist()
   {
	  $dataArray = array( new xmlrpcval(
		 array (
			"" => new xmlrpcval()
		 ),
		 "struct")
	  );
	  $result=$this->xmlrpcdialog('get_cafeslist',$dataArray,0);
	  if (!$result['faultcode'])
	  {
		 $_arr=array(
			'cafeid',   // todo: why does this value disappear?
			'cafename'
		 );
		 for($i=0; $i<$result['max']; $i++)
		 {
			$rec2=$result['v']->arraymem($i);//get the different array (VALUES) members, they are structs
		//	$cafeid=$rec2->structmem("cafeid");
		//	$cafename=$rec2->structmem("cafename");
		///	$ret['cafeid']=$this->getStructScalarFromArray($cafeid,$rec2);
		//	$ret['cafename']=$this->getStructScalarFromArray($cafename,$rec2);
			$ret[]=$this->getStructScalarFromArray($_arr,$rec2);
			//$ret_arr[]=$ret;
		 }
	  } else return $result['faultcode']; // todo:more "elegant" solution please
	  return $ret;
   }

}


