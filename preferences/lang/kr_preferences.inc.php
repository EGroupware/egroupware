<?php

  function lang_pref($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "max matchs per page":
	$s = "페이지당 최대 검색 결과 항목 수";		break;
       
       case "time zone offset":
	$s = "시간대 변경";		break;
       
       case "this server is located in the x timezone":
	$s = "이 서버는 " . $m1 . " 의 시간대를 사용하고 있습니다.";	break;
       
       case "date format":	$s = "날짜 형식";			break;
       case "time format":	$s = "시간 형식";			break;
       case "language":		$s = "언어";			break;

       case "show text on navigation icons":
	$s = "툴팁보기";			break;
       
       case "show current users on navigation bar":
	$s = "현재 사용자를 navigation bar에 표시합니다.";	break;
       
       case "show new messages on main screen":
	$s = "새로운 메시지를 메인화면에 보여줍니다.";	break;
       
       case "email signature":
	$s = "E-Mail 서명";	break;
       
       case "show birthday reminders on main screen":
	$s = "생일인 사람 알려주기.";	break;
       
       case "show high priority events on main screen":
	$s = "중요도가 높은 작업 보여주기";	break;
       
       case "weekday starts on":
	$s = "한주일의 시작";	break;
       
       case "work day starts on":
	$s = "근무 시작 시각";	break;
       
       case "work day ends on":
	$s = "근무 종료 시각";	break;
       
       case "select headline news sites":
	$s = "표제어 뉴스 사이트 선택";	break;
       
       case "change your password":
	$s = "암호 변경";		break;

       case "select different theme":
	$s = "테마 바꾸기";		break;

       case "change your settings":
	$s = "설정 바꾸기";		break;

       case "enter your new password":
	$s = "새로운 암호 ";		break;

       case "re-enter your password":
	$s = "새로운 암호 확인";	break;

       case "the two passwords are not the same":
	$s = "암호가 잘못 입력되었습니다.";	break;

       case "you must enter a password":
	$s = "암호를 입력해야만 합니다.";	break;

       case "your current theme is: x":
	$s = "현재 사용중인 테마는 <b>" . $m1 . "</b> 입니다.";	break;

       case "please, select a new theme":
	$s = "새로운 테마를 선택하세요.";	break;

       case "note: this feature does *not* change your email password. this will need to be done manually.":
	$s = "참고: e-mail의 암호는 변경되지 않습니다. 수동으로 변경하십시오.";	break;


       default: $s = "<b>*</b> ". $message;
    }
    return $s;
  }


