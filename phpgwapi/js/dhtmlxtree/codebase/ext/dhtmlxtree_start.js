//v.3.6 build 131023

/*
Copyright DHTMLX LTD. http://www.dhtmlx.com
You allowed to use this component or parts of it under GPL terms
To use it on other terms or get Professional edition of the component please contact us at sales@dhtmlx.com
*/
function dhtmlXTreeFromHTML(a){typeof a!="object"&&(a=document.getElementById(a));for(var b=a,l=b.id,e="",d=0;d<a.childNodes.length;d++)if(a.childNodes[d].nodeType=="1"){if(a.childNodes[d].tagName=="XMP")for(var j=a.childNodes[d],g=0;g<j.childNodes.length;g++)e+=j.childNodes[g].data;else a.childNodes[d].tagName.toLowerCase()=="ul"&&(e=dhx_li2trees(a.childNodes[d],[],0));break}a.innerHTML="";var c=new dhtmlXTreeObject(a,"100%","100%",0),k=[];for(b in c)k[b.toLowerCase()]=b;for(var f=a.attributes,h=
0;h<f.length;h++)if(f[h].name.indexOf("set")==0||f[h].name.indexOf("enable")==0){var m=f[h].name;c[m]||(m=k[f[h].name]);c[m].apply(c,f[h].value.split(","))}if(typeof e=="object"){c.XMLloadingWarning=1;for(var i=0;i<e.length;i++)b=c.insertNewItem(e[i][0],e[i][3],e[i][1]),e[i][2]&&c._setCheck(b,e[i][2]);c.XMLloadingWarning=0;c.lastLoadedXMLId=0;c._redrawFrom(c)}else c.loadXMLString("<tree id='0'>"+e+"</tree>");window[l]=c;var n=a.getAttribute("oninit");n&&eval(n);return c}
function dhx_init_trees(){for(var a=document.getElementsByTagName("div"),b=0;b<a.length;b++)a[b].className=="dhtmlxTree"&&dhtmlXTreeFromHTML(a[b])}
function dhx_li2trees(a,b,l){for(var e=0;e<a.childNodes.length;e++){var d=a.childNodes[e];if(d.nodeType==1&&d.tagName.toLowerCase()=="li"){for(var j="",g=null,c=d.getAttribute("checked"),k=0;k<d.childNodes.length;k++){var f=d.childNodes[k];f.nodeType==3?j+=f.data:f.tagName.toLowerCase()!="ul"?j+=dhx_outer_html(f):g=f}b[b.length]=[l,j,c,d.id||b.length+1];g&&(b=dhx_li2trees(g,b,d.id||b.length))}}return b}
function dhx_outer_html(a){if(a.outerHTML)return a.outerHTML;var b=document.createElement("DIV");b.appendChild(a.cloneNode(!0));return b=b.innerHTML}window.addEventListener?window.addEventListener("load",dhx_init_trees,!1):window.attachEvent&&window.attachEvent("onload",dhx_init_trees);

//v.3.6 build 131023

/*
Copyright DHTMLX LTD. http://www.dhtmlx.com
You allowed to use this component or parts of it under GPL terms
To use it on other terms or get Professional edition of the component please contact us at sales@dhtmlx.com
*/