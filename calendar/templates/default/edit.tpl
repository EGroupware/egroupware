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
  h = parseInt(document.addform.hour.value);
  m = parseInt(document.addform.minute.value);
  if (h < 0 || h > 23) {
    alert ("{time_error}");
    document.addform.hour.select();
    document.addform.hour.focus();
    return false;
  }
  if (m < 0 || m > 59) {
    alert ("{time_error}");
    document.addform.minute.select();
    document.addform.minute.focus();
    return false;
  }
  h = parseInt(document.addform.end_hour.value);
  m = parseInt(document.addform.end_minute.value);
  if (h < 0 || h > 23) {
    alert ("{time_error}");
    document.addform.end_hour.select();
    document.addform.end_hour.focus();
    return false;
  }
  if (m < 0 || m > 59) {
    alert ("{time_error}");
    document.addform.end_minute.select();
    document.addform.end_minute.focus();
    return false;
  }
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
<!-- <input type="hidden" name="participant_list" value=""> -->
<input type="button" value="{submit_button}" onClick="validate_and_submit()">
</form>

{delete_button}
</center>
<!-- END edit_entry_end -->

