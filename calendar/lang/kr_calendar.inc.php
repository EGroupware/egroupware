<?php

  function lang_calendar($message, $m1 = "", $m2 = "", $m3 = "", $m4 = "")
  {
    $message = strtolower($message);

    switch($message)
    {
       case "today":		$s = "오늘";	break;
       case "this week":	$s = "이번주";	break;
       case "this month":	$s = "이번달";	break;

       case "generate printer-friendly version":
	$s = "프린트하기";	break;

       case "printer friendly":		$s = "프린트하기";	break;

       case "you have not entered a\\nbrief description":
	$s = "간단한 설명을 입력하세요.";	break;

       case "you have not entered a\\nvalid time of day.":
	$s = "시간을 정확하게 입력하세요.";	break;

       case "are you sure\\nyou want to\\ndelete this entry ?":
	$s = "이 항목을 정말로 삭제 하시겠습니까 ?";	break;

       case "participants":		$s = "참여자";	break;
       case "calendar - edit":	$s = "달력 - 편집";	break;
       case "calendar - add":	$s = "달력 - 추가";	break;
       case "brief description":$s = "간단한 설명";break;
       case "full description":	$s = "자세한 설명";break;
       case "duration":			$s = "기간";		break;
       case "minutes":			$s = "분";			break;
       case "repeat type":		$s = "반복방법";		break;
       case "none":				$s = "없음";			break;
       case "daily":			$s = "매일";			break;
       case "weekly":			$s = "매주";			break;
       case "monthly (by day)":	$s = "매월 (by day) ";break;
       case "monthly (by date)":$s = "매월 (by date)";break;
       case "yearly":			$s = "매년";	break;
       case "repeat end date":	$s = "반복 종료날짜";	break;
       case "use end date":		$s = "마지막 날짜 사용";	break;
       case "repeat day":		$s = "반복 요일";		break;
       case "(for weekly)":		$s = "";	break;
       case "frequency":		$s = "빈도";		break;
       case "sun":				$s = "일요일";				break;
       case "mon":				$s = "월요일";				break;
       case "tue":				$s = "화요일";				break;
       case "wed":				$s = "수요일";				break;
       case "thu":				$s = "목요일";				break;
       case "fri":				$s = "금요일";				break;
       case "sat":				$s = "토요일";				break;
       case "search results":	$s = "검색결과";	break;
       case "no matches found.":$s = "검색조건에 맞는 항목이 없습니다.";break;
       case "1 match found":	$s = "1개항목 찾음";	break;
       case "x matches found":	$s = "$m1개 항목 찾음";break;
       case "description":		$s = "설명";		break;
       case "repetition":		$s = "반복";		break;
       case "days repeated":	$s = "동안 반복되었음";	break;
       case "go!":				$s = "실행!";				break;
       case "year":				$s = "년";			break;
       case "month":			$s = "월";			break;
       case "week":				$s = "주";			break;
       case "new entry":		$s = "새로운 항목";		break;
       case "view this entry":	$s = "항목 보기";	break;

       case "the following conflicts with the suggested time:<ul>x</ul>":
	$s = "다음 항목이 제안된 시간과 충돌합니다. :<ul>$m1</ul>";	break;

       case "your suggested time of <B> x - x </B> conflicts with the following existing calendar entries:":
	$s = "<B> $m1 - $m2 </B>이 항목이 달력에 있는 내용과 같습니다.";	break;

       case "you must enter one or more search keywords":
	$s = "하나 이상의 검색키워드를 입력하셔야 합니다.";	break;

       case "are you sure\\nyou want to\\ndelete this entry ?\\n\\nthis will delete\\nthis entry for all users.":		$s = "이 항목을 정말로 삭제하시겠습니까?";	break;

       case "":		$s = "";	break;
       case "":		$s = "";	break;
       case "":		$s = "";	break;
       case "":		$s = "";	break;
       case "":		$s = "";	break;
       case "":		$s = "";	break;
       default: $s = "<b>*</b> ". $message;
    }
    return $s;
  }
?>
