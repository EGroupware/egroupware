<!-- BEGIN header -->
<b>{lang_title}</b>
<hr><p>

<center>{messages}</center>

<table border="0" width="100%" cellspacing="0" cellpadding="0">
 <tr>
  <td>{tabs}</td>
 </tr>
</table>

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

<!-- BEGIN list -->
 <tr class="th">
  <td colspan="2">&nbsp;</td>
 </tr>
{rows}
<!-- END list -->

<!-- BEGIN row -->
 <tr class={tr_color}>
  <td>{row_name}</td>
  <td>{row_value}</td>
 </tr>
<!-- END row -->
