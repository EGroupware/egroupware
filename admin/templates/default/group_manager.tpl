<!-- BEGIN form -->
{error_messages}

  <center>
	<table border="0" width="70%">
		<tr>
			<td valign="top">
				{rows}
			</td>
			<td valign="top">

			   <table border="0" width="100%">
			    <tr class="th">
			      <td><b>{lang_group}:</b></td>
			      <td><b>{group_name}</b></td>
			    </tr>
				
			    <form action="{form_action}" method="post">
			    {hidden}
			    <tr class="tr_color1">
			     <td>{lang_select_managers}</td>
			     <td>{group_members}</td>
			    </tr>
				 {form_buttons}
			    </form>
			   </table>
   			</td>
   		</tr>
   	</table>
  </center>
<!-- END form -->

<!-- BEGIN link_row -->
	<tr class="{tr_color}">
		<td>&nbsp;<a href="{row_link}">{row_text}</a></td>
	</tr>
<!-- END link_row -->
