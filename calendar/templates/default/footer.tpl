<!-- $Id$ -->
<!-- BEGIN footer_table -->
       <hr clear="all">
       <font size="-1">
       <table border="0" width="100%" cellpadding="0" cellspacing="0">
        <tr>
{table_row}
	</tr>
       </table>
<!-- END footer_table -->
<!-- BEGIN footer_row -->
         <td valign="top" width="33%">
          <font size="-1">
           <form action="{action_url}" method="post" name="{form_name}">
            <B>{label}:</B>
			{hidden_vars}
            <select name="{form_label}" onchange="{form_onchange}">
	     {row}
	    </select>
            <noscript><input type="submit" value="{go}"></noscript>
	   </form>
	  </font>
	 </td>
<!-- END footer_row -->
<!-- BEGIN blank_row -->
         <td>
          {b_row}
         </td>
<!-- END blank_row -->


