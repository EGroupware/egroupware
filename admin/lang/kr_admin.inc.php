<?php

  function lang_admin($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "last x logins":	$s = "Last $m1 logins";		break;
       case "loginid":			$s = "접속ID";				break;
       case "ip":				$s = "IP";					break;
       case "total records":	$s = "Total records";		break;
       case "user accounts":	$s = "사용자 계정";		break;
       case "new group name":	$s = "새 그룹 이름";		break;
       case "create group":		$s = "새 그룹 만들기";		break;
       case "kill":				$s = "종료";				break;
       case "idle":				$s = "idle";				break;
       case "login time":		$s = "접속 시간";			break;
       case "anonymous user":	$s = "무명 사용자";		break;
       case "manager":			$s = "관리자";				break;
       case "account active":	$s = "계정 활정화";		break;
       case "re-enter password": $s = "비밀번호 재입력";	break;
       case "group name": 		$s = "그룹이름";			break;
       case "display":			$s = "Display";				break;
       case "base url":			$s = "기본  URL";			break;
       case "news file":		$s = "새로운 파일";			break;
       case "minutes between reloads":	$s = "재로드 시간(분단위)";		break;
       case "listings displayed":	$s = "Listings Displayed";		break;
       case "news type":		$s = "뉴스 타입";			break;
       case "user groups":		$s = "사용자 그룹";			break;
       case "headline sites":	$s = "헤드라인 사이트";		break;
       case "network news":	$s = "네트워크 뉴스";		break;
       case "site":				$s = "Site";				break;
       case "view sessions":	$s = "세션 보기";		break;
       case "view access log":	$s = "접속 로그 보기";		break;
       case "active":			$s = "활성화";				break;
       case "disabled":			$s = "비활성화";			break;
       case "last time read":	$s = "마지막 읽은 시각";		break;
       case "manager":			$s = "관리자";		break;

       case "are you sure you want to delete this group ?":
	$s = "이 그룹을 정말 삭제하시겠습니까 ?"; break;

       case "are you sure you want to kill this session ?":
	$s = "이 세션을 정말 종료시키시겠습니까 ?"; break;

       case "all records and account information will be lost!":
	$s = "이 계정의 자료와 정보가 삭제됩니다.";	break;

       case "are you sure you want to delete this account ?":
	$s = "이 계정을 정말 삭제하시겠습니까 ?";	break;

       case "are you sure you want to delete this news site ?":
	$s = "이 뉴스사이트를 정말 삭제하시겠습니까?";		break;

       case "* make sure that you remove users from this group before you delete it.":
	$s = "* 이 그룹에서 사용자를 정말 삭제하시겠습니까?";	break;

       case "percent of users that logged out":
	$s = "퍼센트의 사용자가 로그아웃 하였습니다.";			break;

       case "list of current users":
	$s = "현재 사용자 목록";						break;

       case "new password [ leave blank for no change ]":
	$s = "새 패스워드[ 바꾸지 않으려면 빈칸으로 남겨두세요 ]";	break;

       case "the two passwords are not the same":
	$s = "비밀번호가 같지 않습니다.";			break;

       case "the login and password can not be the same":
	$s = "계정과 패스워드는 같지 않아야 합니다.";	break;

       case "you must enter a password":	$s = "패스워드를 입력해야 합니다.";		break;

       case "that loginid has already been taken":
	$s = "이 사용자 계정은 이미 사용중입니다.";			break;

       case "you must enter a display":		$s = "출력을 입력해야 합니다.";		break;
       case "you must enter a base url":	$s = "기본 URL을 입력해야 합니다.";		break;
       case "you must enter a news url":	$s = "뉴스 URL을 입력해야 합니다.";		break;

       case "you must enter the number of minutes between reload":
	$s = "재로드될 시간 간격을 입력해야 합니다.";		break;

       case "you must enter the number of listings display":
	$s = "한번에 출혁할 개수를 입력해야 합니다.";		break;

       case "you must select a file type":
	$s = "파일 타입을 선택해야 합니다.";					break;

       case "that site has already been entered":
	$s = "이 사이트는 이미 입력되어 있습니다.";			break;

       case "select users for inclusion":
        $s = "포함할 사용자를 선택하세요.";	break;

       default: $s = "<b>*</b> ". $message;
    }
    return $s;
  }
?>
