<?PHP
/**
*
* class for creating predefines select boxes
*
* @author		Marc Logemann [loge@mail.com]
* @version	0.9
* 
*/
class sbox {
	
	var $monthnames = array ("", "january", "February", "March", "April", "May", "June", "July",
									"August", "September", "October", "November", "December");
	
	function getMonthText($name, $selected=0)
	{
		$out = "<select name=\"$name\">\n";
		
		for($i=0;$i<count($this->monthnames);$i++)
		{              
			$out .= "<option value=\"$i\"";
			if($selected==$i) $out .= " SELECTED";
			$out .= ">"; 
			if($this->monthnames[$i]!="") 
				$out .= lang($this->monthnames[$i]);
			else
				$out .= "";
			$out .= "</option>\n";
		}
      $out .= "</select>\n";
      return $out;
   }
   
   function getDays($name, $selected=0)
   {
   	$out = "<select name=\"$name\">\n";
		
		for($i=0;$i<32;$i++)
		{              
			if($i==0) $val = ""; else $val = $i;
			$out .= "<option value=\"$val\"";
			if($selected==$i) $out .= " SELECTED";
			$out .= ">$val</option>\n";
		}
      $out .= "</select>\n";
      return $out;
   }

	function getYears($name, $selected=0)
   {
   	$out = "<select name=\"$name\">\n";
		
		$out .= "<option value=\"\"";
		if($selected == 0 OR $selected == "") $out .= " SELECTED";
		$out .= "></option>\n";
		
		for($i=date("Y");$i<date("Y")+5;$i++)
		{              
			$out .= "<option value=\"$i\"";
			if($selected==$i) $out .= " SELECTED";
			$out .= ">$i</option>\n";
		}
      $out .= "</select>\n";
      return $out;
   }

	function getPercentage($name, $selected=0)
   {
   	$out = "<select name=\"$name\">\n";

		for($i=0;$i<101;$i=$i+10)
		{              
			$out .= "<option value=\"$i\"";
			if($selected==$i) $out .= " SELECTED";
			$out .= ">$i%</option>\n";
		}
      $out .= "</select>\n";
      // echo $out;
      return $out;
   }

	function getPriority($name, $selected=2)
   {
   	$arr = array("", "low", "normal", "high");
   	$out = "<select name=\"$name\">\n";
		
		for($i=1;$i<count($arr);$i++)
		{              
			$out .= "<option value=\"";
			$out .= $i;
			$out .= "\"";
			if($selected==$i) $out .= " SELECTED";
			$out .= ">";
			$out .= lang($arr[$i]);
			$out .= "</option>\n";
		}
      $out .= "</select>\n";
      return $out;
   }

	function getAccessList($name, $selected="private")
   {
   	$arr = array("private" => "Private",
   						"public" => "Global public",
   						"group" => "Group public");

       if (ereg(",", $selected))
 {
          $selected = "group";
       }
   						
   	$out = "<select name=\"$name\">\n";
		
		for(reset($arr);current($arr);next($arr))
		{              
			$out .= '<option value="' . key($arr) . '"';
			if($selected==key($arr)) $out .= " SELECTED";
			$out .= ">" . pos($arr) . "</option>\n";
		}
      $out .= "</select>\n";
      return $out;
   }
   
   function getGroups($groups, $selected="")
   {
      global $phpgw;

  	$out = '<select name="n_groups[]" multiple>';
      while (list($null,$group) = each($groups)) {
         $out .= '<option value="' . $group[0] . '"';
         if (ereg("," . $group[0] . ",", $selected))
 {
            $out .= " SELECTED";
         }
         $out .= ">" . $group[1] . "</option>\n";
      }
      $out .= "</select>\n";

      return $out;
   }
}