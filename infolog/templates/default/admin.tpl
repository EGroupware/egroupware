<!-- BEGIN admin_line -->
  <tr class="{bg_nm_color}">
   <td>{num}:</td>
   <td>{val_valid}</td>
   <td>{val_trans}</td>
   <td>{val_ip}</td>
  </tr>
<!-- END admin_line -->

<!-- BEGIN info_admin -->
<p><b>{title}:</b><hr><p>
<form action="{action_url}" method="POST">
 <table border="0">
  <tr class="{bg_h_color}">
   <td colspan="4">{text}</td>
  </tr>
  <tr class="{bg_h_color}">
   <td>#</td>
   <td>{lang_valid}</td>
   <td>{lang_trans}</td>
   <td>{lang_ip}</td>
  </tr>
  {admin_lines}
 </table>
 <p>{save_button} &nbsp; {done_button}
</form>
<!-- END info_admin -->
