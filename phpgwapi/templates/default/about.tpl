<!-- BEGIN egroupware -->
	<p>
		<a href="http://www.eGroupWare.org" target="_new"><img src="{phpgw_logo}" border="0" alt="eGroupWare"></a>
	</p>
	<p>{phpgw_version}</p>
	<p>{phpgw_message}</p>
	<img src="phpgwapi/templates/default/images/spacer.gif" alt="spacer" height="3" />
	<div class="greyLine"></div>
<!-- END egroupware -->

<!-- BEGIN app_list_tablestart -->
	<p>{phpgw_app_listinfo}</p>
	<table border="0" cellpadding="5">
		<tr>
			<th>&nbsp;</th>
			<th align="left">{phpgw_about_th_name}</th>
			<th align="left">{phpgw_about_th_author}</th>
			<th align="left">{phpgw_about_th_maintainer}</th>
			<th align="left">{phpgw_about_th_version}</th>
			<th align="left">{phpgw_about_th_license}</th>
			<th>{phpgw_about_th_details}</th>
		</tr>
<!-- END app_list_tablestart -->

<!-- BEGIN app_list_tablerow -->
		<tr>
			<td><img src="{phpgw_about_td_image}" title="{phpgw_about_td_title}" /></td>
			<td><strong>{phpgw_about_td_title}</strong></td>
			<td valign="top">{phpgw_about_td_author}</td>
			<td valign="top">{phpgw_about_td_maintainer}</td>
			<td>{phpgw_about_td_version}</td>
			<td>{phpgw_about_td_license}</td>
			<td><a href="{phpgw_about_td_details_url}"><img src="{phpgw_about_td_details_img}" /></a></td>
		</tr>
<!-- END app_list_tablerow -->

<!-- BEGIN app_list_tablestop -->
	</table>
<!-- END app_list_tablestop -->



<!-- BEGIN application -->
 <tr>
 <td  height="3"><img src="phpgwapi/templates/default/images/spacer.gif" alt="spacer" height="3" /></td>
</tr>
 <tr>
  <td align="left">

{phpgw_app_about}

  </td>
 </tr>
<!-- END application --> 
 
 
</table>
