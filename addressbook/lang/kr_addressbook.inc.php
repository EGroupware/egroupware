<?php

  function lang_addressbook($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "address book":     $s = "주소록";          break;
       case "last name":        $s = "이름";            break;
       case "first name":       $s = "성";              break;
       case "e-mail":           $s = "E-Mail";          break;
       case "home phone":       $s = "집전화";          break;
       case "fax":              $s = "팩스";            break;
       case "work phone":       $s = "직장전화";        break;
       case "pager":            $s = "삐삐";            break;
       case "mobile":           $s = "핸드폰";          break;
       case "other number":     $s = "기타번호";    	break;
       case "street":           $s = "주소";          	break;
       case "birthday":         $s = "생일";        	break;
       case "city":             $s = "도시";            break;
       case "state":            $s = "지역";           	break;
       case "zip code":         $s = "우편번호";        break;
       case "notes":            $s = "기타";           	break;

       default: $s = "<b>*</b> ". $message;
    }
    return $s;
  }
