/* ================================== */
/* Idots 2 Based on SKIN && DESKTOP Linux KDE Standard */
/* ================================== */


function skin_IDOTS2(wName) {
	//c85050//
	var frame_bgcolor = "#CCCCCC"; //"#66A9C8";
	var frame_titleclass = "xDT_wTitle";
	var frame_borderwidth = 1;
	var taskbar_style = 2;
	var frame_topheight = 20;
	var frame_bottomheight = 10;
	var frame_contentbgcolor = '#CCCCCC';
	var frame_dummypic = xDT.resPath() + 'images/blank.gif';
	var iconpath = xDT.resPath() + 'skins/IDOTS2';
	var frame_stylecolor = '#fff';
	var frame_border = 1;
	var frame_bordertype = "outset"; // solid, outset, inset
	var frame_style =         'border-top: ' + frame_border + 'px ' + frame_stylecolor + ' ' + frame_bordertype + '; ' +
		'border-bottom: ' + frame_border + 'px ' + frame_stylecolor + ' ' + frame_bordertype + '; ' +
		'border-left: ' + frame_border + 'px ' + frame_stylecolor + ' ' + frame_bordertype + '; ' +
		'border-right: ' + frame_border + 'px ' + frame_stylecolor + ' ' + frame_bordertype + '; ';
	return (
			'<table cellpadding="0" cellspacing="0" border="0" height="100%" width="100%"><tr>' +
			'<td align="left" valign="top" height="100%" width="100%" style="' + frame_style + '">' +
			'<table cellpadding="0" cellspacing="0" border="0" height="100% width="100%" bgcolor="' + frame_bgcolor + '" >' +
			'<tr><td><img src="' + frame_dummypic + '" width="' + frame_borderwidth + '" height="' + frame_topheight + '" border="0"></td>' + 
			'<td width="100%" align="left" valign="middle" class="' + frame_titleclass + '" background="' + iconpath + '/wintitlebgr.png">' + 
			'<table cellpadding="0" cellspacing="0" border="0"><tr>' +
			'<td width="100%" align="left" valign="middle" class="' + frame_titleclass + '" style="cursor: move;"><div class="title"><img src=\"' + iconpath + '/btn_white_left.png\" class="titleleft"><span class="titlemiddle">' + xDT.prop(wName,"wTitle") + '</span><img src=\"' + iconpath + '/btn_white_right.png\" class="titleright"><div></td>' +
			'<td class="' + frame_titleclass + '"><a class="" href="javascript: void(0)" onmouseover="' + "SwapImg('winmin_" + wName + "','" + iconpath + "/winmin_over.png'); " + 'xDT.prop(' + "'" + wName + "','wIcon','M1'" +')" ' +  'onmouseout="' + "SwapImg('winmin_" + wName + "','" + iconpath + "/winmin.png'); " + 'xDT.prop(' + "'" + wName + "','wIcon','M0'" + ')"'+ '><img name="winmin_' + wName + '" border="0" src="' + iconpath + '/winmin.png"></a></td>' +
			'<td><img src="' + frame_dummypic + '" width="2" border="0"></td>' + 
			'<td class="' + frame_titleclass + '"><a class="" href="javascript: void(0)" onmouseover="' + "SwapImg('winmax_" + wName + "','" + iconpath + "/winmax_over.png'); " + 'xDT.prop(' + "'" + wName + "','wIcon','X1'" +')" ' +  'onmouseout="' + "SwapImg('winmax_" + wName + "','" + iconpath + "/winmax.png'); " + 'xDT.prop(' + "'" + wName + "','wIcon','X0'" + ')"'+ '><img name="winmax_' + wName + '" border="0" src="' + iconpath + '/winmax.png"></a></td>' +
			'<td><img src="' + frame_dummypic + '" width="2" border="0"></td>' + 
			'<td class="' + frame_titleclass + '"><a class="" href="javascript: void(0)" onmouseover="' + "SwapImg('winclose_" + wName + "','" + iconpath + "/winclose_over.png'); " + 'xDT.prop(' + "'" + wName + "','wIcon','C1'" +')" ' +  'onmouseout="' + "SwapImg('winclose_" + wName + "','" + iconpath + "/winclose.png'); " + 'xDT.prop(' + "'" + wName + "','wIcon','C0'" + ')"'+ '><img name="winclose_' + wName + '" border="0" src="' + iconpath + '/winclose.png"></a></td>' +
			'</tr></table>' +
			'</td>' + 
			'<td style="cursor: se-resize;"><img src="' + frame_dummypic + '" width="' + frame_borderwidth + '" height="' + frame_topheight + ' border="0"></td></tr>' +
			'<tr><td style="cursor: move;"><img src="' + frame_dummypic + '" width="' + frame_borderwidth + '" border="0"></td><td align="left" valign="top" width="100%" height="100%" style="background: ' + frame_contentbgcolor +'; " id="' + wName + 'iTD' + '"></td><td style="cursor: se-resize;"><img src="' + frame_dummypic + '" width="' + frame_borderwidth + '" border="0"></td></tr>' +
			'<tr><td style="cursor: move;"><img src="' + frame_dummypic + '" width="' + frame_borderwidth + '" height="' + frame_bottomheight + '" border="0"></td><td width="100%" style="cursor: move;"><div style="width: 20px; float: right; height: 100%;cursor: se-resize;"></div>' + 
			'</td><td style="cursor: se-resize;"><img src="' + frame_dummypic + '" width="' + frame_borderwidth + '" height="' + frame_bottomheight + '" border="0"></td>' + 
			'</tr></table>' +
			'</td></tr></table>'
		   );
}

var sd = true;
function desktop_IDOTS2() {
	var iconpath = xDT.resPath() + 'skins/IDOTS2';
	xDT.addSkin('IDOTS2',0,31);

	xDT.taskbarColor("#D4D4D4","#000","#000");
	xDT.cbe("dDesktop").resizeTo(document.cbe.width(),document.cbe.height());
	/*if (clock_show == 'yes')
	  {
	  xDT.cbe("dDesktop").innerHtml('<div id="taskbar"><IMG SRC="' + iconpath + '/launch.png" onClick="displayLaunch();" id="launch"><div id="tb" OnClick="sdt();"><img src="' + iconpath + '/show_desktop.png"/></div><div id="tasks"></div><div id="clock" onclick="openX(\'Calendar\', \'../calendar/index.php\');"></div></div><img src="' + iconpath + '/x-desktop.png"/ id="xdesktoplogo"><img src="' + iconpath + '/egroupware.png"/ id="egroupwarelogo">');
	  }
	  else
	  {
	  xDT.cbe("dDesktop").innerHtml('<div id="taskbar"><IMG SRC="' + iconpath + '/launch.png" onClick="displayLaunch();" id="launch"><div id="tb" OnClick="sdt();"><img src="' + iconpath + '/show_desktop.png"></div></div><img src="' + iconpath + '/x-desktop.png"/ id="xdesktoplogo"><div id="tasks"></div><img src="' + iconpath + '/egroupware.png"/ id="egroupwarelogo">');
	  }

	  xDT.cbe("dDesktop").innerHtml('<table cellpadding="0" cellspacing="0" border="0" height="100%" width="100%">' +
	  '<tr><td height="100%"></td></tr>' +
	  '</table>');
	 */
	xDT.cbe("dDesktop").zIndex(0);
	xDT.show("dDesktop");
	correctPNG();
	if (clock_show =='yes')
	{
		makeTime();
	}
	notify();
}

function taskbar_IDOTS2() {
	var iconpath = xDT.resPath() + 'skins/IDOTS2';

	imgbegin = new Image();
	imgend = new Image();
	a = document.createElement('a');
	span = document.createElement('span');

	imgbegin.className = "taskbegin";
	imgend.className = "taskend";


	//tasks = document.getElementById("tasks");
	if(document.getElementById("tasks")) {

		var tasks = document.getElementById("tasks");

		removeAllChilds(tasks);
		//while(tasks.hasChildNodes() == true)
		//       {
		//                tasks.removeChild(tasks.childNodes[0]);
		//        }



		var str = "";
		var str2 = "";
		var winName = "";
		var winTitle = "";
		for (var i=0;i<=xDT.maxWindow();i++) {
			winName = xDT.wName(i);
			str2 += winName;
			if (typeof(winName) != "undefined" && i >= xDTwin.syswin && winName != "")
			{

				if (str == "") {
					str = winName;
				}
				else
				{
					str += "," + winName;
				}
			}
		}
		if(str != "") {
			var wins = str.split(",");

			xDT.p_taskbar = true;
			var lostSpace = 500;
			var buttonWidth = (document.cbe.width() - lostSpace) / wins.length; // this needs to be corrected to the sizes of the clock
			if(buttonWidth > 250) {
				buttonWidth = 250;
			}
			var marginLeft = 50 / wins.length;
			if(marginLeft > 10) {
				marginLeft = 10;
			}
			var charSize = 12;         //number of pixels one char prob. will fill
			var charCount = (buttonWidth - 34) / charSize;

			a.style.width = buttonWidth - 10 + "px";
			a.style.marginLeft = marginLeft;
			span.style.width = buttonWidth - 34 + "px";
			for (var i=0;i<wins.length;i++) {
				//if (_property(winName,"wStat") != "min") continue;

				stat = xDT.prop(wins[i],"wStat");
				imgbegin.src = iconpath + "/btn_white_left.png" ;
				imgend.src = iconpath + "/btn_white_right.png" ;
				a.className = 'taskNode';
				winName = wins[i];
				winTitle = xDT.prop(winName,"wTitle");
				if (winTitle.length > charCount) {
					winTitle = winTitle.substr(0,charCount-3) + "...";
				}

				a.innerHTML = "";
				a.href = "javascript:xDT.taskbar('" + winName + "');";
				span.innerHTML = winTitle;

				a.appendChild(imgbegin.cloneNode(true));
				a.appendChild(span.cloneNode(true));
				a.appendChild(imgend.cloneNode(true));
				tasks.appendChild(a.cloneNode(true));


			}
		}
	}
}











