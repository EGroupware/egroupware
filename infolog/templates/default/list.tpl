<!-- BEGIN projdetails -->
 <table width=95% border=0 cellspacing=1 cellpadding=3>
  <tr class="th">
   <td width="3%" class=list>{lang_type}</td>
   <td width="5%" class=list>{lang_status}</td>
   <td class=list>{lang_subject}</td>
   <td width="10%" class=list>{lang_startdate}<br> <!-- </td>
   <td width="10%" class=list>-->{lang_enddate}</td>
   <td width="10%" class=list>{lang_owner}<br>{lang_datemodified}</td>
   <td width="10%" class=list>{lang_responsible}</td>
  </tr>
  <tr class="row_on" valign="top">
   <td class=list>{type}</td>
   <td class=list>{status}</td>
   <td class=list>{subject}<br>{des}{filelinks}</td>
   <td class=list>{startdate}<br>{enddate}</td>
   <td class=list>{owner}<br>{datemodified}</td>
   <td class=list">{responsible}</td>
  </tr>
 </table><p>
<!-- END projdetails -->

<!-- BEGIN info_headers -->
  <tr class="th">
   <td width="2%" class=list>{lang_type}</td>
   <td width="4%" class=list>{lang_status}</td>
   <td class=list>{lang_subject}</td>
   <td width="8%" class=list>{lang_startdate}<br>{lang_enddate}</td>
   <td width="8%" class=list>{lang_owner}<br>{lang_datemodified}</td>
   <td width="8%" class=list>{lang_responsible}</td>
   <td width="3%" class=list>{h_lang_sub}</td>
   <td width="3%" class=list>{h_lang_action}</td>
  </tr>
<!-- END info_headers -->

<!-- BEGIN info_list -->
  <tr class="{tr_color}" valign="top">
   <td class=list>{type}</td>
   <td class=list>{status}</td>
   <td class=list>{subject}<br>{des}{filelinks}</td>
   <td class=list>{startdate}<br>{enddate}</td>
   <td class=list>{owner}<br>{datemodified}</td>
   <td class=list>{responsible}</td>
   <td class=list>
{subadd}
{viewsub}
{viewparent}
   </td>
   <td class=list>{edit}&nbsp;{delete}&nbsp;{addfiles}</td>
  </tr>
<!-- END info_list -->

<!-- BEGIN get_list -->
{info_css}
<table width=98%>
 <tr>
  <td>
   <span class=action>{lang_info_action}</span>
  </td><td align=center>
   {total_matchs}
  </td><td align=right>
   <span class=action>{add_icons}</span>
  </td>
 </tr>
</table>

<hr noshade width="98%" align="center" size="1">

<center>
 {projdetails}

 <!-- next_matchs Start -->
 {next_matchs}
 <!-- next_matchs Ende -->

 <table width=95% border=0 cellspacing=1 cellpadding=3>
  {info_headers}
  {info_list}

 </table>

 <!-- next_matchs_end Start -->
 {next_matchs_end}
 <!-- next_matchs_end Ende -->

</center>

 <table>
  <tr>
   <td>{add_button}</td>
   <td>{back2projects}</td>
  </tr>
 </table>
<!-- END get_list -->