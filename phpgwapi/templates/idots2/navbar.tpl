<!-- BEGIN xdesktop_header -->
<script type='text/javascript'>

var xDT = new xDesktop();
var clock_set = '{clock}';
var clock_show = '{clock_show}';
var url_pref = '{urlpref}';
var default_app = '{default_app}';
var default_title = '{default_title}';
var strXmlUrl = '{strXmlUrl}';
var scrWidth = '{scrWidth}';
var scrHeight = '{scrHeight}';
var titleAdd = '{titleAdd}';
var titleRem = '{titleRem}';
var titlePref = '{titlePref}';
var titleAbout = '{titleAbout}';
var programs = '{programs}';
var back_shortcut = '{back_shortcut}';
var color_shortcut = '{color_shortcut}';
var color_text_sc = '{color_text_sc}';
showShortcuts('{appTitles}', '{appUrls}', '{appImgs}','{appTop}','{appLeft}','{appType}','{appName}','{strXmlUrl}');
initSizes('{sizeTitles}', '{sizeWidth}', '{sizeHeight}');

			
function start() {
	xDT.resPath('{template_dir}/js/x-desktop/xDT/'); 
	xDT.desktop.init();
  	
	xDT.desktop.skin('IDOTS2');
	
	idotsW = "xD" + new Date().getUTCMilliseconds();
	if(default_app != "") {
		openX(default_title, default_app); 
	
	}
	
}

</script>
	


	
	{dIcons}
<!-- END xdesktop_header -->

<!-- BEGIN show_clock -->

<div id="taskbar"><IMG SRC="{iconpath}/launch.png" onClick="displayLaunch();" id="launch"><div id="tb" OnClick="sdt();"><img src="{iconpath}/show_desktop.png"></div><div id="tasks"></div><div id="clock" onclick="openX('{calendarTitle}', 'calendar/index.php');"></div></div>
<a id="warning" onClick="warning();">
	<img src="{serverpath}/phpgwapi/templates/idots2/js/x-desktop/xDT/skins/IDOTS2/idea.png" alt="New notification">
</a>

<!-- END show_clock -->

<!-- BEGIN no_clock -->

<div id="taskbar"><IMG SRC="{iconpath}/launch.png" onClick="displayLaunch();" id="launch"><div id="tb" OnClick="sdt();"><img src="{iconpath}/show_desktop.png"></div><div id="tasks"></div></div>
<a id="warning" class="noclock" onClick="warning();">
	<img src="{serverpath}/phpgwapi/templates/idots2/js/x-desktop/xDT/skins/IDOTS2/idea.png" alt="New notification">
</a>


<!-- END no_clock -->

<!-- BEGIN logo -->


<img src="{iconpath}/x-desktop.png" id="xdesktoplogo"><img src="{iconpath}/egroupware.png" id="egroupwarelogo">

<!-- END logo -->

<!-- BEGIN background_stretched -->


<img src="{backgroundimage}" id="{backgroundstyle}" style="background-color: {backgroundcolor}">

<!-- END background_stretched -->

<!-- BEGIN background -->


<div id="{backgroundstyle}" style="background-color: {backgroundcolor}; background-image: url('{backgroundimage}');"></div>

<!-- END background -->

<!-- BEGIN background_none -->

<div id="stretched" style="background-color: {backgroundcolor};"></div>

<!-- END background_none -->

<!-- BEGIN navbar_header_begin -->


<div id="launchmenu" class="" style="display:none;" onBlur="this.style.display='none';">
	<div id="launchinfo"> </div>
	<ul>
<!-- END navbar_header_begin -->

<!-- BEGIN navbar_header_end -->		
	</ul>
</div>




<ul id="notify" onBlur="this.style.display='none';">

</ul>




<!-- END navbar_header_end -->

<!-- BEGIN appbox -->	
	

{sideboxcolstart}<ul id="nav">


<!-- END appbox -->

<!-- BEGIN end_appbox -->	
	

</ul>


<!-- END end_appbox -->

<!-- BEGIN begin_toolbar -->
</ul>

<div class="idots2toolbar">

<!-- END begin_toolbar -->

<!-- BEGIN toolbar_item -->

<a href="{url}" onmouseout="this.style.border='1px solid #e5e5e5';" onmouseover="'this.style.border=1px outset #FFF';" onmousedown="this.style.border='1px inset #FFF';" style="background-image: url({image})"></a>

<!-- END toolbar_item -->

<!-- BEGIN toolbar_seperator -->



<!-- END toolbar_seperator -->

<!-- BEGIN end_toolbar -->

</div>

<!-- END end_toolbar -->




<!-- BEGIN navbar_footer -->	
{sideboxcolend}

		

<!-- End Sidebox Column -->

<!-- Applicationbox Column -->
		
<div id="divAppbox">	
			
<!-- END navbar_footer -->


<!-- BEGIN extra_blocks_header -->

	<li onclick="itemClick(event);" onmouseover="itemHover(event);"> <a href="javascript:void(0);">{lang_title}</a>
	<ul>

<!-- END extra_blocks_header -->


<!-- BEGIN extra_blocks_footer -->

	</ul></li>

<!-- END extra_blocks_footer -->



<!-- BEGIN extra_block_row -->

<li><a class="textSidebox" href="{item_link}"{target}>{lang_item}</a></li>

<!-- END extra_block_row -->

<!-- BEGIN extra_block_row_raw -->

<li><div>><a href="#">{lang_item}</a></div></li>

<!-- END extra_block_row_raw -->

<!-- BEGIN extra_block_row_no_link -->
<li><a href="#">{lang_item}</a></li>

<!-- END extra_block_row_no_link -->

<!-- BEGIN extra_block_spacer -->
&nbsp;
<!-- END extra_block_spacer -->


<!-- BEGIN launch_app -->

<li><a href="javascript:openX('{title}', '{url}');"><img src="{icon}" alt="{title}" title="{title}">{title}</a></li>
					
<!-- END launch_app -->

<!-- BEGIN logout -->

<li><a href="javascript:document.location = '{url}';"><img src="{icon}" alt="{title}" title="{title}">{title}</a></li>
					
<!-- END logout -->
