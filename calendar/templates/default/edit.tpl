<!-- $Id$ -->
<!-- BEGIN edit_entry_begin -->
<script language="JavaScript">
// do a little form verifying
function validate_and_submit() {
  if (document.addform.name.value == "") {
    alert("{name_error}");
    return false;
  }
  h = parseInt(document.addform.hour.value);
  m = parseInt(document.addform.minute.value);
  if (h > 23 || m > 59) {
     alert ("{time_error}");
     return false;
  }
  // would be nice to also check date to not allow Feb 31, etc...
  document.addform.submit();
  return true;
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
<input type="submit" value="{submit_button}" onClick="validate_and_submit()">
</form>

{delete_button}
</center>
<!-- END edit_entry_end -->

