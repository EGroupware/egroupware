<!-- $Id$ -->
<!-- BEGIN edit_entry_begin -->
<script language="JavaScript">
// do a little form verifying
function validate_and_submit() {
  if (document.addform.name.value == "") {
    alert("{name_error}");
    document.addform.name.select();
    document.addform.name.focus();
    return false;
  }
  sh = parseInt(document.addform.hour.value);
  sm = parseInt(document.addform.minute.value);
  if (sh < 0 || sh > 23) {
    alert ("{time_error}");
    document.addform.hour.select();
    document.addform.hour.focus();
    return false;
  }
  if (sm < 0 || sm > 59) {
    alert ("{time_error}");
    document.addform.minute.select();
    document.addform.minute.focus();
    return false;
  }
  eh = parseInt(document.addform.end_hour.value);
  em = parseInt(document.addform.end_minute.value);
  if (eh < 0 || eh > 23) {
    alert ("{time_error}");
    document.addform.end_hour.select();
    document.addform.end_hour.focus();
    return false;
  }
  if (em < 0 || em > 59) {
    alert ("{time_error}");
    document.addform.end_minute.select();
    document.addform.end_minute.focus();
    return false;
  }
//  so = parseInt(document.addform.month.value);
//  sd = parseInt(document.addform.day.value);
//  sy = parseInt(document.addform.year.value);
//  eo = parseInt(document.addform.end_month.value);
//  ed = parseInt(document.addform.end_day.value);
//  ey = parseInt(document.addform.end_year.value);
//  if (sy == ey && so == eo && sd == ed) {
//    if (sh > eh) {
//      alert ("{time_error}");
//      document.addform.end_hour.select();
//      document.addform.end_hour.focus();
//      return false;
//    }
//    if (sh == eh && sm > em) {
//      alert ("{time_error}");
//      document.addform.end_hour.select();
//      document.addform.end_hour.focus();
//      return false;
//    }
//  }
//  if (sy == ey && so == eo && sd > ed) {
//      alert ("{date_error}");
//      document.addform.end_day.select();
//      document.addform.end_day.focus();
//      return false;
//  }
//  if (sy == ey && so > eo) {
//      alert ("{date_error}");
//      document.addform.end_month.select();
//      document.addform.end_month.focus();
//      return false;
//  }
//  if (sy > ey) {
//      alert ("{date_error}");
//      document.addform.end_year.select();
//      document.addform.end_year.focus();
//      return false;
//  }

  // would be nice to also check date to not allow Feb 31, etc...
  document.addform.submit();
//  return true;
}
</script>
</head>
<body bgcolor="#C0C0C0">
<center>
<h2><font color="#000000">{calendar_action}</font></h2>

<form action="{action_url}" method="post" name="addform">
{common_hidden}
<table border="0" width="75%">
<!-- END edit_entry_begin -->

{output}

<!-- BEGIN edit_entry_end -->
</table>
<input type="button" value="{submit_button}" onClick="validate_and_submit();">
<!-- <noscript><input type="button" value="{submit_button}"></noscript> -->
</form>

{delete_button}
</center>
<!-- END edit_entry_end -->

