<!-- BEGIN portal_box -->
<p>
<div class="portal_box">
<table border="0" cellpadding="0" cellspacing="0" width="{outer_width}"> 
 <tr nowrap align="center">
  <td align="left">
	<table class="portal_box_header" border="0" cellpadding="1" cellspacing="0">
	  <tr>
		<td align="left">&nbsp;<strong>{title}</strong></td>{portal_controls}
	  </tr>
	</table>
  </td>
 </tr>
 <tr>
  <td colspan="2">
   <table border="0" cellpadding="0" cellspacing="0">
    {row}
   </table>
  </td>
 </tr>
</table>
</div>
</p>
<!-- END portal_box -->



<!-- BEGIN portal_row -->
    <tr>
	  <td>
{output}
	  </td>
    </tr>
<!-- END portal_row -->



<!-- BEGIN portal_listbox_header -->
	<tr>
	 <td>
	  <ul>
<!-- END portal_listbox_header -->



<!-- BEGIN portal_listbox_link -->
<li><a href="{link}">{text}</a></li>
<!-- END portal_listbox_link -->



<!-- BEGIN portal_listbox_footer -->
	  </ul>
	 </td>
	</tr>
<!-- END portal_listbox_footer -->



<!-- BEGIN portal_control -->
  <td valign="middle" align="right" nowrap="nowrap">{control_link}</td>
<!-- END portal_control -->



<!-- BEGIN link_field -->
   {link_field_data}
<!-- END link_field -->
