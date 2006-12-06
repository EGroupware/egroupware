<!-- BEGIN list -->
<style type="text/css">
	.letter_box,.letter_box_active {
		background-color: #D3DCE3;
		width: 25px;
		border: 1px solid #D3DCE3;
		text-align: center;
		cursor: pointer;
		cusror: hand;
	}
	.letter_box_active {
		font-weight: bold;
		background-color: #E8F0F0;
	}
	.letter_box_active,.letter_box:hover {
		border: 1px solid black;
		background-color: #E8F0F0;
	}
</style>
<p>
 <table border="0" width="45%" align="center">
	<tr>
		<td colspan="5">
			<table width="100%"><tr>
<!-- BEGIN letter_search -->
				<td class="{class}" onclick="location.href='{link}';">{letter}</td>
<!-- END letter_search -->
			</tr></table>
		</td>
	</tr>
  <tr>
   {left_next_matchs}
   <td align="center">{lang_groups}</td>
   {right_next_matchs}
  </tr>
 </table>

 <table border="0" width="45%" align="center">
  <tr class="th">
   <td>{sort_name}</td>
   <td>{header_edit}</td>
   <td>{header_delete}</td>
  </tr>

  {rows}

 </table>

 <table border="0" width="45%" align="center">
  <tr>
   <td align="left">
    <form method="POST" action="{new_action}">
     {input_add}
    </form>
   </td>
   <td align="right">
   <!-- 
    <form method="POST" action="{search_action}">
     {input_search}
    </form>
    -->
			<form method="POST" action="{accounts_url}">
				<table width="100%"><tr>
					<td>{lang_group} {group}</td>
					<td align="right">
						{query_type}
						<input type="text" name="query" value="{query}">
						<input type="submit" name="search" value="{lang_search}">
					</td>
				</tr></table>
			</form>
   </td>
  </tr>
 </table>
<!-- END list -->

<!-- BEGIN row -->
 <tr class="{class}">
  <td>{group_name}</td>
  <td class="narrow_column">{edit_link}</td>
  <td class="narrow_column">{delete_link}</td>
 </tr>
<!-- END row -->

<!-- BEGIN row_empty -->
   <tr>
    <td colspan="5" align="center">{message}</td>
   </tr>
<!-- END row_empty -->
