<!-- BEGIN list -->
<style>
<!--
#prefIndex {
}
#divGenTime {
	clear: left;
}
.prefAppBox {
	width: 225px;
	min-height: 125px;
	border: 2px ridge gray;
	border-radius: 10px;
	margin: 5px;
	padding-left: 5px;
	float: left;
	box-shadow:8px 8px 8px #666;
}


.prefAppBox h3 {
	height: 32px;
	padding-left: 50px;
	padding-top: 10px;
	background-image: url(../phpgwapi/templates/default/images/nonav.png);
	background-repeat: no-repeat;
	background-position: left;
	background-size: 32px;
	margin: 0;
}

.prefAppBox ul {
	margin: 0;
	padding-left: 20px;
	padding-top: 0;
}
-->
</style>
<div id="prefIndex">
 {rows}
</div>
<!-- END list -->

<!-- BEGIN app_row -->
 <div class="prefAppBox">
 <h3 style="background-image: url({app_icon})">{app_name}</h3>
 <ul>
<!-- END app_row -->

<!-- BEGIN app_row_noicon -->
 <div class="prefAppBox">
 <h3>{app_name}</h3>
 <ul>
<!-- END app_row_noicon -->

<!-- BEGIN link_row -->
  <li><a href="{pref_link}">{pref_text}</a></li>
<!-- END link_row -->

<!-- BEGIN spacer_row -->
  </ul>
 </div>
<!-- END spacer_row -->
