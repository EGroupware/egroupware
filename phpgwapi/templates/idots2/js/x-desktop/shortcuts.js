var LastClick;
var strXmlUrl;
document.onselectstart = function()
{
        return false;
}

function showShortcuts(appTitles, appUrls, appImgs, appTop, appLeft, appType, appName, xmlUrl)
{
        strXmlUrl = xmlUrl;
        var aTitle = appTitles.split(',');
        var aUrl = appUrls.split(',');
        var aImg = appImgs.split(',');
        var aTop = appTop.split(',');
        var aLeft = appLeft.split(',');
        var aType = appType.split(',');
        var aName = appName.split(',');

        for(i=0;i<aTitle.length; i++)
        {
                if(aTitle[i] !="")
                {
                        showShortcut(aTitle[i], aUrl[i], aImg[i], aTop[i], aLeft[i], aName[i], aType[i]);
                }
        }
}
function showShortcut(appTitles, appUrls, appImgs, appTop, appLeft,appName, appType)
{
        a = document.createElement('div');


        span = document.createElement('span');
        span.innerHTML = appTitles;
        span.className = "title";
        span.id = "span_"+appTitles;
        if(back_shortcut == 'no')
        {
                span.style.backgroundColor = color_shortcut;
        }
        else
        {
                span.style.backgroundColor = 'transparent';
        }
        if(color_text_sc != '')
        {
                span.style.color = color_text_sc;
        }
        else
        {
                span.style.color = '#000';
        }
        a.id = appName;
        var detect = navigator.userAgent.toLowerCase();
        place = detect.indexOf("msie") + 1;

        if(place && document.all)
        {
                a2 = document.createElement('span');
                a2.className = "iex";
                a2.style.backgroundPosition = 'top center';
                a2.style.backgroundRepeat = 'no-repeat';
                a2.style.filter = "progid:DXImageTransform.Microsoft.AlphaImageLoader(src='" + appImgs + "')";

                a.appendChild(a2);
        }
        else
        {
                a.style.backgroundImage = 'url("'+appImgs+'")';
                a.style.backgroundPosition = 'top center';
                a.style.backgroundRepeat = 'no-repeat';
        }

        a.src = appUrls;
        a.onmousedown = mousedown;

        a.className = "short";
        a.onmouseup = mouseupevent;
        a.className = "shortcut";
        a.style.display = "block";
        a.style.cursor = "pointer";
        a.appendChild(span);
        document.body.appendChild(a);
        createPos(a, appLeft,appTop);
        if(a.clientWidth < 32)
        {
                a.style.width = "32px";
        }

}
var mocuob;
function mouseupevent(e)
{
        if (!e) var e = window.event;
        var source = (e.target) ? e.target : e.srcElement;
        if(source.tagName == "SPAN")
        {
                source = source.parentNode;
        }
       if(mocuob == source)
        {
                if(e.button != 2)
                {
                        if (new Date().getTime() - LastClick > 500)
                        {
                                mouseUp(this.id,e);
                        }
                        else
                        {
                                mObj = document.getElementById(this.id);
                                if(mObj.tagName == "IMG" || mObj.tagName == "SPAN")
                                {
                                        mObj = mObj.parentNode;
                                }
                                source = mObj.src;
                                mObj.onmousemove = "";
                                for(i = 0; i < mObj.childNodes.length; i++)
                                {
                                        if(mObj.childNodes[i].tagName == "SPAN")
                                        {
                                                Title = mObj.childNodes[i].innerHTML;
                                        }
                                }
                                openX(Title,source);
                        }
                }
                mocuob = null;
        }
}



function mousedown(e)
{


        if (!e) var e = window.event;
        var source = (e.target) ? e.target : e.srcElement;

        if(e.button != 2)
        {
                LastClick = new Date().getTime();
                //return false;
                if (!e) var e = window.event;
                var source = (e.target) ? e.target : e.srcElement;
                if(source.tagName == "SPAN") {
                   source = source.parentNode;
                }
                source.onmousemove = mouseDrag;
        }
        else
        {
                if (!e) var e = window.event;
                var source = (e.target) ? e.target : e.srcElement;
                if(source.tagName == "SPAN") {
                   source = source.parentNode;
                }
                createContext(false, source.id ,e.clientX, e.clientY);
        }
        mocuob = source;
}
function createContext(add, url,x,y)
{

        if(!document.getElementById('context'))
        {
                ul = document.createElement('ul');
        }
        else
        {
                removeAllChilds(ul);
        }
        li = document.createElement('li');
        a = document.createElement('a');
        if(add == true)
        {
                a.href = "javascript:openShort('"+titleAdd+"','"+url+"','"+y+"','"+x+"');";
                a.innerHTML = titleAdd;
        }
        else
        {
                a.href = "javascript:remShort('"+url+"');";
                a.innerHTML = titleRem;
        }
        li.appendChild(a);
        ul.appendChild(li);

        li2 = document.createElement('li');
        a2 = document.createElement('a');
        a2.innerHTML = titlePref;
        a2.href="javascript:openX('"+titlePref+"','"+url_pref+"');";
        li2.appendChild(a2);
        ul.appendChild(li2);

        li3 = document.createElement('li');
        a3 = document.createElement('a');
        a3.innerHTML = titleAbout;
        a3.href="javascript:openX('"+titleAbout+"','about.php');";
        li3.appendChild(a3);
        ul.appendChild(li3);

        ul.id = "context";
        ul.style.display = "block";
        document.body.appendChild(ul);
        createPos(ul, x, y);

}

var hitTop;
var hitLeft;
function openShort(title, url, top, left)
{
        hitTop = top;
        hitLeft = left;
        //openX(title,url);
        if(document.getElementById('context'))
        {
                document.getElementById('context').style.display = "none";
        }

        if(document.getElementById('launchmenu'))
        {
                document.getElementById('launchmenu').style.display = "none";
        }

        xDT.addWindow('short', title, 320, 200, 'center', 'IDOTS2');
        xDT.url('short', url);
        xDT.show('short');
        correctPNG();

}


function remShort(id)
{
        mObj = document.getElementById(id);
        if (mObj.tagName == "SPAN")
        {
                id = mObj.parentNode.id;
        }

        strXmlRemUrl2 = strXmlRemUrl + "?id=";
        strXmlRemUrl2 = strXmlRemUrl2 + id;
        if (window.XMLHttpRequest)
        {
                req = new XMLHttpRequest();
                req.open("GET", strXmlRemUrl2, true);
                req.send(null);

        }
        else
        {
                if (window.ActiveXObject)
                {
                        req = new ActiveXObject("Microsoft.XMLHTTP");
                        if (req)
                        {
                                req.open("GET", strXmlRemUrl2, true);
                                req.send();
                        }
                }
        }
        shortcut = document.getElementById(id);
        shortcut.parentNode.removeChild(shortcut);
        con = document.getElementById("context");
        con.parentNode.removeChild(con);
}

function mouseDrag(e)
{

        if (!e) var e = window.event;
        var source = (e.target) ? e.target : e.srcElement;
        if(source.tagName == "SPAN" || source.tagName == "span")
        {
                source = source.parentNode;
        }

        if(mocuob == source)
        {
                if( e.clientY > ((source.clientHeight / 2) + 5) && e.clientY < getWindowHeight()-((source.clientHeight / 2)+35) )
                {
                        source.style.top = e.clientY - (source.clientHeight / 2) + "px";
                }
                if(e.clientX > ((source.clientWidth /2) + 5) && e.clientX < getWindowWidth()- (source.clientWidth /2))
                {
                        source.style.left =  e.clientX - (source.clientWidth /2) + "px";
                }
                LastClick = 0;
        }
}


function mouseUp(id,e)
{
        mObj = document.getElementById(id);
        y = e.clientY + mObj.clientHeight;
        x = e.clientX + mObj.clientWidth;
        y2 = findPosY(mObj);
        x2 = findPosX(mObj);
        mObj.onmousemove = "";
        strXmlUrl2 = strXmlUrl +  "?id=";
        strXmlUrl2 = strXmlUrl2 + id;
        strXmlUrl2 = strXmlUrl2 + "&top=";
        strXmlUrl2 = strXmlUrl2 + y2;
        strXmlUrl2 = strXmlUrl2 + "&left=";
        strXmlUrl2 = strXmlUrl2 + x2;
        createPos(mObj, x2,y2);
        if (window.XMLHttpRequest)
        {
                req = new XMLHttpRequest();
                req.open("GET", strXmlUrl2, true);
                req.send(null);
                //                req.onreadystatechange = test9;
        }
        else
        {
                if (window.ActiveXObject)
                {
                        req = new ActiveXObject("Microsoft.XMLHTTP");
                        if (req)
                        {
                                req.open("GET", strXmlUrl2, true);
                                req.send();
                        }
                }
        }
}



document.oncontextmenu = function(e)
{
        if (!e) var e = window.event;
        var source = (e.target) ? e.target : e.srcElement;
        if(source.nodeName =="SPAN"|| source.nodeName == "span")
        {
                source = source.parentNode;
        }
        if(document.getElementById('context') && source.className !="shortcut")
        {
                ul.style.display = "none";
        }
        if(source.id != "launch")
        {
                document.getElementById('launchmenu').style.display = "none";
        }
        if (source.className != "shortcut" && source.parentNode.className != "shortcut")
        {
                createContext(true, addShorcutUrl,e.clientX,e.clientY);
        }
        e.cancelBubble = true;
        if(e.stopPropagation)
        {
                e.stopPropagation();
        }
        return false;

}
