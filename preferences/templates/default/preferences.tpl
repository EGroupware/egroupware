<!-- BEGIN header -->
<b>{lang_title}</b>
<hr><p>

<form method="POST" action="{action_url}">
 <table border="0">

<!-- END header -->

<!-- BEGIN footer -->
</table>
<table border="0" width="70%" cellspacing="5" cellpadding="5">
 <tr>
  <td align="left"><input type="submit" name="submit" value="{lang_submit}"></td>
  <td align="right"><input type="submit" name="cancel" value="{lang_cancel}"></td>
 </tr>
</table>
<!-- END footer -->

<!-- BEGIN list_a -->
 <tr bgcolor="{th_bg}">
  <td>&nbsp;</td>
  <td>{lang_user}</td>
  <td>{lang_global}</td>
  <td>{lang_default}</td>
 </tr>
{rows}
<!-- END list_a -->

<!-- BEGIN row_a -->
 <tr bgcolor="{tr_color}">
  <td>{row_name}</td>
  <td>{row_user}</td>
  <td>{row_global}</td>
  <td>{row_default}</td>
 </tr>
<!-- END row_a -->

<!-- BEGIN list_u -->
 <tr bgcolor="{th_bg}">
  <td>&nbsp;</td>
  <td>{lang_user}</td>
 </tr>
{rows}
<!-- END list_u -->

<!-- BEGIN row_u -->
 <tr bgcolor="{tr_color}">
  <td>{row_name}</td>
  <td>{row_user}</td>
 </tr>
<!-- END row_u -->
