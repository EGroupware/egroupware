<!-- BEGIN admin_line -->
  <tr class="{tr_color}">
   <td>{num}:</td>
   <td>{val_valid}</td>
   <td>{val_trans}</td>
   <td>{val_ip}</td>
  </tr>
<!-- END admin_line -->

<!-- BEGIN info_admin -->
<br>
<form action="{action_url}" method="POST">
 <table border="0">
  <tr class="th">
   <td colspan="4">{text}</td>
  </tr>
  <tr class="th">
   <td>#</td>
   <td>{lang_valid}</td>
   <td>{lang_trans}</td>
   <td>{lang_ip}</td>
  </tr>
  {admin_lines}
  <tr>
   <td colspan="4" align="left">
    {save_button} &nbsp; {done_button}
   </td>
  </tr>
 </table>
</form>
<!-- END info_admin -->
