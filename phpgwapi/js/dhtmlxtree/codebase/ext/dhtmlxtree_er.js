//v.3.6 build 131023

/*
Copyright DHTMLX LTD. http://www.dhtmlx.com
You allowed to use this component or parts of it under GPL terms
To use it on other terms or get Professional edition of the component please contact us at sales@dhtmlx.com
*/
var g=[];dhtmlXTreeObject.prototype._createSelfA2=dhtmlXTreeObject.prototype._createSelf;dhtmlXTreeObject.prototype._createSelf=function(){g[g.length]=this;return this._createSelfA2()};
window.onerror=function(e,d,a,c){c=document.createElement("DIV");c.style.cssText="position:absolute; background-color:white; top:10px; left:10px; z-index:20; width:500px; border: 2px silver outset;";var b="<div style='width:100%; color:red; font-size:8pt; font-family:Arial; font-weight:bold; '>Javascript Error</div>";b+="<div style='width:100%; font-size:8pt; font-family:Arial; '>The next error ocured :<br/> <strong>"+e+"</strong> in <strong>"+d+"</strong> at line <strong>"+a+"</strong></div>";b+=
"<div style='width:100%; font-size:8pt; font-family:Arial; '>If you think that error can be caused by dhtmlxtree press the 'Generate report' button and send generated report to <a href='email:support@dhtmlx.com'>support@dhtmlx.com</a> </div>";b+="<input style='font-size:8pt; font-family:Arial; ' onclick='dhtmlxtreeReport(this)' type='button' value='Generate report'/><input style='font-size:8pt; font-family:Arial; ' type='button' value='Close' onclick='this.parentNode.parentNode.removeChild(this.parentNode);'/>";
b+="<div/>";c.innerHTML=b;document.body.appendChild(c);return!0};function dhtmlxtreeErrorReport(e,d,a){var c=e+" ["+d+"]";e=="LoadXML"&&(c+="<br/>"+a[0].responseText+"</br>"+a[0].status);window.onerror(c,"none","none")}
function dhtmlxtreeReport(e){var d=e.parentNode;d.lastChild.innerHTML="<textarea style='width:100%; height:300px;'></textarea>";for(var a=d.childNodes[1].innerHTML,c=0;c<g.length;c++){var b=g[c];a+="\n\n Tree "+c+"\n";for(b in b)typeof b[b]!="function"&&(a+=b+"="+b[b]+"\n");a+="---------------------\n";if(b.XMLLoader)try{var i=b.XMLLoader.getXMLTopNode("tree");if(document.all)a+=i.xml+"\n";else{var j=new XMLSerializer;a+=j.serializeToString(i)+"\n"}}catch(l){a+="XML not recognised\n"}a+="---------------------\n";
for(var k in b._idpull){var f=b._idpull[k];if(typeof f=="object"){a+="Node: "+f.id;a+="  Childs: "+f.childsCount;for(var h=0;h<f.childsCount;h++)a+="  ch"+h+":"+f.childNodes[h].id;a+="\n"}}}d.lastChild.childNodes[0].value=a}dhtmlxError.catchError("ALL",dhtmlxtreeErrorReport);

//v.3.6 build 131023

/*
Copyright DHTMLX LTD. http://www.dhtmlx.com
You allowed to use this component or parts of it under GPL terms
To use it on other terms or get Professional edition of the component please contact us at sales@dhtmlx.com
*/