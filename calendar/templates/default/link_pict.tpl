<!-- $Id$    link_pict -->

<!-- BEGIN link_pict -->
{picture}
<!-- END link_pict -->

<!-- BEGIN link_open -->
<div id="calendar_event_entry" style="overflow:hidden;" onmouseover="this.T_STATIC=true; this.T_TITLE='{title} [{time}]'; return escape('<table width=250px border=0 style=\'background: #FFFFCC;\'><tr><td>{desc}</td></tr><tr><td>&nbsp;</td></tr><tr><td style=\'border-top: 1px solid silver; font-size:8px; background: #c7c7c7;\'>{location-title}:&nbsp;<em>{location}</em></td></tr>')";>
<a class="event_entry" href="{link_link}" onMouseOver="window.status='{lang_view}'; return true;" onMouseOut="window.status=''; return true;"><br>

<!-- END link_open -->

<!-- BEGIN pict -->
 <img src="{pic_image}" width="{width}" height="{height}" title="{title}" border="0" />
<!-- END pict -->

<!-- BEGIN link_text_old -->
<nobr>&nbsp;{time}&nbsp;</nobr> {title}&nbsp;{users_status}: <i>{desc}</i><!--({location})-->
<!-- END link_text_old -->

<!-- BEGIN link_text -->
<nobr>&nbsp;<span style="color: black">{time}</span> {users_status}</nobr><br><b>{title}</b><br><i>{desc}</i><br>(<span style="font-size:7px;">{location})</span>
<!-- END link_text -->

<!-- BEGIN link_close -->
</a></div>
<!-- END link_close -->
