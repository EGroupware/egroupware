<!-- $Id$ -->
<!-- BEGIN edit_entry_begin -->
<body bgcolor="#C0C0C0">
<center>
<h2><font color="#000000">{calendar_action}</font></h2>

<form action="{action_url}" method="post" name="addform">
{common_hidden}
<table border="0" width="75%">
 <tr>
  <td colspan="2">
   <center><h1>{errormsg}</h1></center>
   <hr>
  </td>
 </tr>
<!-- END edit_entry_begin -->

{output}

<!-- BEGIN edit_entry_end -->
</table>
<input type="submit" value="{submit_button}">
</form>

{delete_button}
</center>
<!-- END edit_entry_end -->

