<!-- $Id$ -->
<!-- BEGIN day -->
 <style type="text/css">
  <!--
    .event
    {
      color: {font_color};
      font-family: {font};
      font-weight: 100;
      font-size: 80%;
      line-height: 110%;
      vertical-align: middle;
    }

    .time
    {
      width: {time_width}%;
      background-color: {time_bgcolor};
      border-color: {time_border_color};
      border-width: 1;
      color: {font_color};
      font-family: {font};
      font-size: 65%;
      line-height: 100%;
      vertical-align: middle;
    }

  -->
 </style>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
{row}
</table>
<!-- END day -->
<!-- BEGIN day_row -->
    <tr>{item}
    </tr>
<!-- END day_row -->
<!-- BEGIN day_event -->
     <td class="event" bgcolor="{bgcolor}"{extras}>{event}</td>
<!-- END day_event -->
<!-- BEGIN day_time -->
     <td class="time"><nobr>{open_link}{time}{close_link}</nobr></td>
<!-- END day_time -->

