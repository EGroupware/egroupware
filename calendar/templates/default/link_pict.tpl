<!-- $Id$    link_pict -->

<!-- BEGIN link_pict -->
{picture}
<!-- END link_pict -->

<!-- BEGIN link_open -->
<div id="calendar_event_entry" style="overflow:hidden;">
<a class="event_entry" href="{link_link}" onMouseOver="window.status='{lang_view}'; return true;" onMouseOut="window.status=''; return true;"><br>
<!-- removed for favour of tooltip (NDEE) title="{desc}" -->
<!-- END link_open -->

<!-- BEGIN pict -->
 <img src="{pic_image}" width="{width}" height="{height}" title="{title}" border="0" />
<!-- END pict -->

<!-- BEGIN link_text_old -->
<nobr>&nbsp;{time}&nbsp;</nobr> {title}&nbsp;{users_status}: <i>{desc}</i><!--({location})-->
<!-- END link_text_old -->

<!-- BEGIN link_text -->
<nobr>&nbsp;<span style="color: black">{time}</span> {users_status}</nobr><br><div  onmouseover="this.T_STATIC=true;this.T_OFFSETX=-2;this.T_OFFSETY=36;return escape('<table width=200 cellpadding=3 cellspacing=0 border=0><tr><td style=\'color: white;\' bgcolor=#606060>{time}::<b>{title}</b></td></tr><tr><td><br>{desc}<br><br></td></tr><tr><td bgcolor=#c7c7c7>{location}</td></tr></table>');"><b>{title}</b><br><i>{desc}</i><br><b>{location}</b></div>
<!-- END link_text -->

<!-- BEGIN link_close -->
</a></div>
<!-- END link_close -->
