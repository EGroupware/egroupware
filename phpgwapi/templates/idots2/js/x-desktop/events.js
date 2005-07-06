function createPos(mObj, mX, mY)
{
        mX = Math.round(mX);
        mY = Math.round(mY);
        wHeight = Math.round(getWindowHeight());
        wWidth = Math.round(getWindowWidth());
        if( mX + mObj.clientWidth > wWidth)
        {
                if(mX > wWidth)
                {
                        mObj.style.left = wWidth - mObj.clientWidth + "px";
                }
                else
                {
                        mObj.style.left = mX - mObj.clientWidth  + "px";
                }
        }
        else
        {
                mObj.style.left = mX + "px";
        }
        if( mY + mObj.clientHeight > wHeight - 23)
        {
                if(mY > wHeight - 30)
                {
                        mObj.style.top = wHeight - 30 + "px";
                }
                else
                {
                        mObj.style.top = mY - mObj.clientHeight + "px";
                }
        }
        else
        {
                mObj.style.top = mY + "px";
        }
}

function getWindowWidth() {
        var myWidth = 0;
        if( typeof( window.innerWidth ) == 'number' )
        {
                //Non-IE
                myWidth = window.innerWidth;
        }
        else
        if( document.documentElement && ( document.documentElement.clientWidth || document.documentElement.clientHeight ) )
        {
                //IE 6+ in 'standards compliant mode'
                myWidth = document.documentElement.clientWidth;
        }
        else
        if( document.body && ( document.body.clientWidth || document.body.clientHeight ) )
        {
                //IE 4 compatible
                myWidth = document.body.clientWidth;
        }
        return myWidth;
}

function getWindowHeight() {
        var myHeight = 0;
        if( typeof( window.innerWidth ) == 'number' )
        {
                //Non-IE
                myHeight = window.innerHeight;
        }
        else
        if( document.documentElement && ( document.documentElement.clientWidth || document.documentElement.clientHeight ) )
        {
                //IE 6+ in 'standards compliant mode'
                myHeight = document.documentElement.clientHeight;
        }
        else
        if( document.body && ( document.body.clientWidth || document.body.clientHeight ) )
        {
                //IE 4 compatible
                myHeight = document.body.clientHeight;
        }
        return myHeight;
}


function openX(idotsname, url)
{
        if(scrWidth == "")
        {
                scrWidth2 = 600;
        }
	else {
		scrWidth2 = scrWidth;
	}
	if(scrHeight =="")
        {
                scrHeight2 = 400;
        }
	else {
		scrHeight2 = scrHeight;
	}
	for(i = 0; i < aTitle.length; i++)
	{
		if(aTitle[i] != "" && aTitle[i] == idotsname) {
			scrWidth2 = aWidth[i];
			scrHeight2 = aHeight[i];
		}
	}


        if(document.getElementById('context'))
        {
                document.getElementById('context').style.display = "none";
        }

        if(document.getElementById('launchmenu'))
        {
                document.getElementById('launchmenu').style.display = "none";
        }
	
	startX = Math.round((getWindowWidth() / 2) - (scrWidth2 / 2));
	startY = Math.round((getWindowHeight() / 2) - (scrHeight2 / 2));
	
	
        idotsW = "xD" + new Date().getUTCMilliseconds();
	for (var i=0;i<=xDT.maxWindow();i++) {
		winName = xDT.wName(i);
		
		if (typeof(winName) != "undefined" && i >= xDTwin.syswin && winName != "")
		{
			oldPos = xDT.window.pos(winName);
			posArray = oldPos.split("," );
			if(posArray[0] == startX)
				startX+=20;
			
			if(posArray[1] == startY)
				startY+=20;

			if(Math.round(startX)+Math.round(scrWidth2) > getWindowWidth() && Math.round(startY)+Math.round(scrHeight2) > getWindowHeight()) 
			{	
				startX = 0;
				startY = 0;
			}
		}
	}

        xDT.addWindow(idotsW, idotsname, scrWidth2, scrHeight2, '' + startX + ',' +  startY, 'IDOTS2');
        xDT.url(idotsW, url);
        xDT.show(idotsW);
	xDT.window.onClose(idotsW, "saveSize('" + idotsW + "');");
        correctPNG();
}
function saveSize(idotsName) {
	title = xDT.prop(idotsName, 'wTitle');
	w = xDT.prop(idotsName, 'wWidth');
	h = xDT.prop(idotsName, 'wHeight');
	
	url = strXmlUrl + "/write_size.php?title=" + title + "&w="  + w + "&h="  + h;
	var found = false;
	for(i = 0; i < aTitle.length; i++)
	{
		if(aTitle[i] != "" && aTitle[i] == title) {
			aWidth[i] = w;
			aHeight[i] = h;
			found = true;
		}
	}
	if(!found) 
	{
		aTitle[aTitle.length] = title;
		aWidth[aWidth.length] = w;
		aHeight[aHeight.length] = h;	
	}

	loadXMLDoc(url);
	return true;

}

function saveSideboxState(idotsName,sideboxstate) {
title = xDT.prop(idotsName, 'wTitle');
//w = xDT.prop(idotsName, 'wWidth');
//h = xDT.prop(idotsName, 'wHeight');

url = strXmlUrl + "/write_settings.php?action=save_sidebox_state&title=" + idotsName + "&sidebox_state="+sideboxstate;
/*var found = false;
for(i = 0; i < aTitle.length; i++)
{
	if(aTitle[i] != "" && aTitle[i] == title) {
		aWidth[i] = w;
		aHeight[i] = h;
		found = true;
	}
}
if(!found) 
{
	aTitle[aTitle.length] = title;
	aWidth[aWidth.length] = w;
	aHeight[aHeight.length] = h;	
}
*/
loadXMLDoc(url);
return true;

}

function findPosX(obj)
{
        var curleft = 0;
        if (obj.offsetParent)
        {
                while (obj.offsetParent)
                {
                        curleft += obj.offsetLeft;
                        obj = obj.offsetParent;
                }
        }
        if (obj.offsetLeft)
        {
                curleft += obj.offsetLeft;
        }
        return curleft;
}

function findPosY(obj)
{
        var curtop = 0;
        if (obj.offsetParent)
        {
                while (obj.offsetParent)
                {
                        curtop += obj.offsetTop;
                        obj = obj.offsetParent;
                }
        }
        if (obj.offsetTop)
                curtop += obj.offsetTop;
        return curtop;
}


function correctPNG() // correctly handle PNG transparency in Win IE 5.5 or higher.
{
        if (document.all)
        {
                var detect = navigator.userAgent.toLowerCase();
                place = detect.indexOf("msie") + 1;

                if(place)
                {
                        for(var i=0; i<document.images.length; i++)
                        {
                                var img = document.images[i];
                                var imgName = img.src.toUpperCase();
                                if (imgName.substring(imgName.length-3, imgName.length) == "PNG")
                                {
                                        w = img.width;
                                        h = img.height;
                                        if(w != 0)
                                        {
                                                img.style.filter = "progid:DXImageTransform.Microsoft.AlphaImageLoader(src='" + img.src + "\', sizingMethod='scale')";
                                                img.src = "phpgwapi/templates/idots2/images/spacer.gif";
                                                img.style.width = w + "px";
                                                img.style.height = h + " px";
                                        }
                                }
                        }
                }
        }

}


function SwapImg(obj,img)  // Switch Image
{
        document.images[obj].src = img;
        img = document.images[obj];
        correctImage(img);
}

function correctImage(img) //Correct PNG's for IE's opinion about PNG's.
 {

        var detect = navigator.userAgent.toLowerCase();
        place = detect.indexOf("msie") + 1;

        if(place && document.all)
        {
                var imgName = img.src.toUpperCase();
                if (imgName.substring(imgName.length-3, imgName.length) == "PNG")
                {
                        w = img.width;
                        h = img.height;
                        if(w != 0)
                        {
                                img.style.filter = "progid:DXImageTransform.Microsoft.AlphaImageLoader(src='" + img.src + "\', sizingMethod='scale')";
                                img.src = "phpgwapi/templates/idots2/images/spacer.gif";
                                img.style.width = w + "px";
                                img.style.height = h + " px";
                        }
                }
        }
}


function notify()
{
        var url = "notifyxml.php";
        loadXMLDoc(url);
        setInterval("loadXMLDoc('"+ url+ "');", 60000);
}

var req;

function loadXMLDoc(url)
{
        // branch for native XMLHttpRequest object
        if (window.XMLHttpRequest) {
                req = new XMLHttpRequest();
                req.onreadystatechange = processReqChange;
                req.open("GET", url, true);
                req.send(null);
                // branch for IE/Windows ActiveX version
        } else if (window.ActiveXObject) {
                req = new ActiveXObject("Microsoft.XMLHTTP");
                if (req) {
                        req.onreadystatechange = processReqChange;
                        req.open("GET", url, true);
                        req.send();
                }
        }
}

function processReqChange()
{
        // only if req shows "complete"
        if (req.readyState == 4) {
                if (req.status == 200) {
                        var iconpath = xDT.resPath() + 'skins/IDOTS2';

                        if(req.responseXML.documentElement)
                        {
                        response = req.responseXML.documentElement;
                        if(response.getElementsByTagName('title').length > 0) {
                                        notify = document.getElementById("notify");

                                        removeAllChilds(notify);

                                        for(i = 0; i <  response.getElementsByTagName('title').length; i++)
                                        {
                                                title = response.getElementsByTagName('title')[i].firstChild.data;
                                                url = response.getElementsByTagName('url')[i].firstChild.data;
                                                message = response.getElementsByTagName('message')[i].firstChild.data;
                                                a = document.createElement("a");
                                                a.href = "javascript:openX('" + title + "', '" + url + "');warning();";
                                                a.innerHTML = message;

                                                li = document.createElement("li");
                                                li.appendChild(a);
                                                notify.appendChild(li);


                                        }
                                        document.getElementById('warning').style.display = "block";
                                }

                                else
                                {
                                        if(document.getElementById('warning'))
                                        {
                                                document.getElementById('warning').style.display = "none";
                                        }
                                }
                        }
                }
        }
}

function warning()
{
        notify = document.getElementById('notify');
        if(notify.style.display == "none" || notify.style.display == "")
        {
                notify.style.display = "block";
        }
        else
        {
                notify.style.display = "none";
        }
}

function removeAllChilds(obj)
{
        while(obj.hasChildNodes() == true)
        {
                obj.removeChild(obj.firstChild);
        }

}

document.onmousedown = function(e)
        //document.onclick = function(e)
{
        if (!e) var e = window.event;
        var source = (e.target) ? e.target : e.srcElement;
        //        alert(source.parentNode.parentNode.tagName);
        //        alert(source.parentNode.className);
        if(source.nodeName =="SPAN")
        {
                source = source.parentNode;
        }
        //test = document.getElementById('context2');
        //alert(test.id);
        if(source.parentNode && source.parentNode.parentNode)
        {
                if(source.parentNode.parentNode.id != "context")
                {
                        if(document.getElementById('context') &&  source.className !="shortcut")
                        {
                                ul.style.display = "none";
                        }
                        if(document.getElementById('context'))
                        {
                                if(e.button != 2)
                                        ul.style.display = "none";
                        }
                }
                if(source.parentNode.parentNode.parentNode.id !="launchmenu")
                {
                        if(source.id != "launch")
                        {
                                document.getElementById('launchmenu').style.display = "none";
                        }
                }
        }



}
window.onresize = function(e) {
        launchSize(true);
        links = document.getElementsByTagName('a');
        for(i = 0; i <  links.length; i++)
        {

                if(links[i].className == "shortcut")
                {
                        mObj = links[i];
                        mX = findPosX(mObj);
                        mY = findPosY(mObj);
                        createPos(mObj, mX, mY)
                }
        }
}

var launchInit = false;

function launchSize(forceResize)
{
        if(document.all) {
           launchSizeIE(forceResize);
        }
        else {
           launchSizeAll(forceResize);
        }
}

function launchSizeAll(forceResize)
{
        var iconpath = xDT.resPath() + 'skins/IDOTS2';
		var divideAmount= 22.5;
        if(forceResize || !launchInit)
        {
                launchInit = true;

                div = document.getElementById('launchmenu');

                listItems = new Array();
                x = 0;
                for(i = 0; i < div.childNodes.length; i++)
                {
                        if(div.childNodes[i].tagName == "UL")
                        {
                                ul = div.childNodes[i];
                                for(j = 0; j < ul.childNodes.length; j++)
                                {
                                        if(ul.childNodes[j].nodeName == "LI" && ul.childNodes[j].className != "programs")
                                        {
                                                listItems[x] = ul.childNodes[j].cloneNode(true);
                                                x++;
                                        }
                                }
                        }
                }



                totItems = listItems.length;
                taskHeight = document.getElementById('taskbar').clientHeight;
                
			divHeight = getWindowHeight()-taskHeight;

                document.getElementById('launchinfo').style.height = divHeight + "px";

                maxItems = Math.floor((divHeight / divideAmount)-1);

                margin = divHeight % divideAmount;
                if(maxItems >= totItems)
                {
                        margin += (maxItems - totItems) * divideAmount;
                }
	
                document.getElementById('launchmenu').style.top = (margin)+ "px";

                info = document.getElementById("launchinfo").cloneNode(true);
                div.innerHTML = "";
                div.appendChild(info);
                newUl = new Array();
                ulCount = 0;
                newUl[ulCount] = document.createElement("UL");

                html = '<img src="' + iconpath + '/btn_white_left.png" class="titleleft"><span class="titlemiddle">'+programs+'</span><img src="' + iconpath + '/btn_white_right.png" class="titleright">';


                programs = document.createElement("LI");

                programs.innerHTML = html;
                programs.className = "programs";

                newUl[ulCount].appendChild(programs);

                if(maxItems >= totItems) {
                        newUl[ulCount].style.marginRight = "5px";
                }

                programs2 = programs.cloneNode(false);

                for(i = 0; i < totItems; i++) {
                        newUl[ulCount].appendChild(listItems[i]);
                        if(i+1 >= (maxItems * (ulCount+1)))
                        {
                                newUl[ulCount].style.height = "100%";
                                div.appendChild(newUl[ulCount]);
                                ulCount++;

                                newUl[ulCount] = document.createElement("UL");
                                newUl[ulCount].appendChild(programs2.cloneNode(true));
                        }
                }
                if(newUl[ulCount].childNodes.length > 1)
                {
                        div.appendChild(newUl[ulCount]);
                }
                correctPNG();


        }

}

function launchSizeIE(forceResize) {
        var iconpath = xDT.resPath() + 'skins/IDOTS2';
		var divideAmount= 23;
		if(forceResize || !launchInit)
        {

                launchInit = true;

                div = document.getElementById('launchmenu');

                listItems = new Array();
                x = 0;
                for(i = 0; i < div.childNodes.length; i++)
                {

                        if(div.childNodes[i].tagName == "UL")
                        {
                                ul = div.childNodes[i];
                                for(j = 0; j < ul.childNodes.length; j++)
                                {
                                        if(ul.childNodes[j].nodeName == "LI" && ul.childNodes[j].className != "programs")
                                        {
                                                listItems[x] = ul.childNodes[j].cloneNode(true);
                                                x++;
                                        }
                                }
                        }
                }

                totItems = listItems.length;
                taskHeight = document.getElementById('taskbar').clientHeight;
                divHeight = getWindowHeight()-(taskHeight);



                maxItems = Math.floor((divHeight / divideAmount)-1);

                margin = divHeight % divideAmount;
                if(maxItems >= totItems)
                {
                        margin += (maxItems - totItems) * divideAmount;
                }

                document.getElementById('launchmenu').style.bottom = taskHeight + "px";


                info = document.getElementById("launchinfo").cloneNode(true);

                div.innerHTML = "";

                div.appendChild(info);
                newUl = new Array();
                ulCount = 0;
                newUl[ulCount] = document.createElement("UL");
                html = '<img src="' + iconpath + '/btn_white_left.png" class="titleleft"><span class="titlemiddle">'+programs+'</span><img src="' + iconpath + '/btn_white_right.png" class="titleright">';


                program = document.createElement("LI");

                program.innerHTML = html;
                program.className = "programs";

                newUl[ulCount].appendChild(program);

                if(maxItems >= totItems) {
                        //newUl[ulCount].style.marginRight = "5px";
                }

                programs2 = program.cloneNode(false);

                for(i = 0; i < totItems; i++) {
                        newUl[ulCount].appendChild(listItems[i]);
                        if(i+2 >= (maxItems * (ulCount+1)))
                        {
                                newUl[ulCount].style.height = "100%";
                                div.appendChild(newUl[ulCount]);
                                ulCount++;

                                newUl[ulCount] = document.createElement("UL");
                                newUl[ulCount].appendChild(programs2.cloneNode(true));
                        }
                }
                if(newUl[ulCount].childNodes.length > 1)
                {
                        div.appendChild(newUl[ulCount]);
                }
                if(document.all)
                {
                        info.style.height = div.clientHeight + "px";
                }
                correctPNG();


        }

}


function displayLaunch()
{
        el = document.getElementById('launchmenu');
        if(el.style.display == "block")
        {
                el.style.display = "none";
        }
        else
        {
                el.style.display = "block";
                launchSize(false);
        }
}

function sdt()
{
        if(sd == true)
        {
                sd = false;
                xDT.minimizeAllWindows();
        }
        else
        {
                xDT.restoreAllWindows();
                sd = true;
        }
}

function makeTime() {
        clock_d = new Date();
        clock_day = clock_d.getDay();
        clock_mon = clock_d.getMonth();
        clock_date = clock_d.getDate();
        clock_year = clock_d.getYear();
        clock_hr = clock_d.getHours();
        clock_min = clock_d.getMinutes();
        clock_sec = clock_d.getSeconds();

        if(clock_year<1000){clock_year=("" + (clock_year+11900)).substring(1,5);}
        else{clock_year=("" + (clock_year+10000)).substring(1,5);}




        clock_zmon=new Array();
        clock_zmon=["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];

        if(document.getElementById('clock')!= null)
        {
                if(clock_set == 'minute')
                {
                        if(clock_sec > 30)
                        {
                                clock_min = clock_min + 1;
                        }
                        if(clock_min <= 9)
                        {
                                clock_min="0"+clock_min
                        }
                        if(clock_sec <= 9)
                        {
                                clock_sec="0"+clock_sec
                        }
                        document.getElementById('clock').innerHTML = "" + clock_zmon[clock_mon] + " "+ clock_date + ", " + clock_year + " / " + clock_hr + ":" + clock_min + " ";
                }
                else
                {
                        if(clock_min <= 9)
                        {
                                clock_min="0"+clock_min
                        }
                        if(clock_sec <= 9)
                        {
                                clock_sec="0"+clock_sec
                        }
                        document.getElementById('clock').innerHTML = "" + clock_zmon[clock_mon] + " "+ clock_date + ", " + clock_year + " / " + clock_hr + ":" + clock_min + ":" + clock_sec + " ";
                }
        }

        if(clock_set == 'minute')
        {
                setTimeout("makeTime()", 60000);
        }
        else
        {
                setTimeout("makeTime()", 1000);
        }
}
