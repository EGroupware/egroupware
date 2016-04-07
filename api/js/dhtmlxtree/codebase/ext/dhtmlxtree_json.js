//v.3.6 build 131023

/*
Copyright DHTMLX LTD. http://www.dhtmlx.com
You allowed to use this component or parts of it under GPL terms
To use it on other terms or get Professional edition of the component please contact us at sales@dhtmlx.com
*/
function jsonPointer(a,b){this.d=a;this.dp=b}
jsonPointer.prototype={text:function(){var a=function(a){for(var f=[],c=0;c<a.length;c++)f.push("{"+b(a[c])+"}");return f.join(",")},b=function(d){var f=[],c;for(c in d)typeof d[c]=="object"?c.length?f.push('"'+c+'":['+a(d[c])+"]"):f.push('"'+c+'":{'+b(d[c])+"}"):f.push('"'+c+'":"'+d[c]+'"');return f.join(",")};return"{"+b(this.d)+"}"},get:function(a){return this.d[a]},exists:function(){return!!this.d},content:function(){return this.d.content},each:function(a,b,d){var f=this.d[a],c=new jsonPointer;
if(f)for(var e=0;e<f.length;e++)c.d=f[e],b.apply(d,[c,e])},get_all:function(){return this.d},sub:function(a){return new jsonPointer(this.d[a],this.d)},sub_exists:function(a){return!!this.d[a]},each_x:function(a,b,d,f,c){var e=this.d[a],g=new jsonPointer(0,this.d);if(e)for(c=c||0;c<e.length;c++)if(e[c][b]&&(g.d=e[c],d.apply(f,[g,c])==-1))break},up:function(){return new jsonPointer(this.dp,this.d)},set:function(a,b){this.d[a]=b},clone:function(){return new jsonPointer(this.d,this.dp)},through:function(a,
b,d,f,c){var e=this.d[a];if(e.length)for(var g=0;g<e.length;g++){if(e[g][b]!=null&&e[g][b]!=""&&(!d||e[g][b]==d)){var h=new jsonPointer(e[g],this.d);f.apply(c,[h,g])}var i=this.d;this.d=e[g];this.sub_exists(a)&&this.through(a,b,d,f,c);this.d=i}}};
dhtmlXTreeObject.prototype.loadJSArrayFile=function(a,b){this.parsCount||this.callEvent("onXLS",[this,this._ld_id]);this._ld_id=null;this.xmlstate=1;var d=this;this.XMLLoader=new dtmlXMLLoaderObject(function(a,b,e,g,h){eval("var z="+h.xmlDoc.responseText);d.loadJSArray(z)},this,!0,this.no_cashe);if(b)this.XMLLoader.waitCall=b;this.XMLLoader.loadXML(a)};
dhtmlXTreeObject.prototype.loadCSV=function(a,b){this.parsCount||this.callEvent("onXLS",[this,this._ld_id]);this._ld_id=null;this.xmlstate=1;var d=this;this.XMLLoader=new dtmlXMLLoaderObject(function(a,b,e,g,h){d.loadCSVString(h.xmlDoc.responseText)},this,!0,this.no_cashe);if(b)this.XMLLoader.waitCall=b;this.XMLLoader.loadXML(a)};
dhtmlXTreeObject.prototype.loadJSArray=function(a,b){for(var d=[],f=0;f<a.length;f++)d[a[f][1]]||(d[a[f][1]]=[]),d[a[f][1]].push({id:a[f][0],text:a[f][2]});var c={id:this.rootId},e=function(a,b){if(d[a.id]){a.item=d[a.id];for(var c=0;c<a.item.length;c++)b(a.item[c],b)}};e(c,e);this.loadJSONObject(c,b)};
dhtmlXTreeObject.prototype.loadCSVString=function(a,b){for(var d=[],f=a.split("\n"),c=0;c<f.length;c++){var e=f[c].split(",");d[e[1]]||(d[e[1]]=[]);d[e[1]].push({id:e[0],text:e[2]})}var g={id:this.rootId},h=function(a,b){if(d[a.id]){a.item=d[a.id];for(var c=0;c<a.item.length;c++)b(a.item[c],b)}};h(g,h);this.loadJSONObject(g,b)};
dhtmlXTreeObject.prototype.loadJSONObject=function(a,b){this.parsCount||this.callEvent("onXLS",[this,null]);this.xmlstate=1;var d=new jsonPointer(a);this._parse(d);this._p=d;b&&b()};
dhtmlXTreeObject.prototype.loadJSON=function(a,b){this.parsCount||this.callEvent("onXLS",[this,this._ld_id]);this._ld_id=null;this.xmlstate=1;var d=this;this.XMLLoader=new dtmlXMLLoaderObject(function(a,b,e,g,h){try{eval("var t="+h.xmlDoc.responseText)}catch(i){dhtmlxError.throwError("LoadXML","Incorrect JSON",[h.xmlDoc,this]);return}var j=new jsonPointer(t);d._parse(j);d._p=j},this,!0,this.no_cashe);if(b)this.XMLLoader.waitCall=b;this.XMLLoader.loadXML(a)};
dhtmlXTreeObject.prototype.serializeTreeToJSON=function(){for(var a=['{"id":"'+this.rootId+'", "item":['],b=[],d=0;d<this.htmlNode.childsCount;d++)b.push(this._serializeItemJSON(this.htmlNode.childNodes[d]));a.push(b.join(","));a.push("]}");return a.join("")};
dhtmlXTreeObject.prototype._serializeItemJSON=function(a){var b=[];if(a.unParsed)return a.unParsed.text();var d=this._selected.length?this._selected[0].id:"",f=a.span.innerHTML,f=f.replace(/\"/g,'\\"',f);this._xfullXML?b.push('{ "id":"'+a.id+'", '+(this._getOpenState(a)==1?' "open":"1", ':"")+(d==a.id?' "select":"1",':"")+' "text":"'+f+'", "im0":"'+a.images[0]+'", "im1":"'+a.images[1]+'", "im2":"'+a.images[2]+'" '+(a.acolor?', "aCol":"'+a.acolor+'" ':"")+(a.scolor?', "sCol":"'+a.scolor+'" ':"")+(a.checkstate==
1?', "checked":"1" ':a.checkstate==2?', "checked":"-1"':"")+(a.closeable?', "closeable":"1" ':"")+(this.XMLsource&&a.XMLload==0?', "child":"1" ':"")):b.push('{ "id":"'+a.id+'", '+(this._getOpenState(a)==1?' "open":"1", ':"")+(d==a.id?' "select":"1",':"")+' "text":"'+f+'"'+(this.XMLsource&&a.XMLload==0?', "child":"1" ':""));if(this._xuserData&&a._userdatalist){b.push(', "userdata":[');for(var c=a._userdatalist.split(","),e=[],g=0;g<c.length;g++)e.push('{ "name":"'+c[g]+'" , "content":"'+a.userData["t_"+
c[g]]+'" }');b.push(e.join(","));b.push("]")}if(a.childsCount){b.push(', "item":[');e=[];for(g=0;g<a.childsCount;g++)e.push(this._serializeItemJSON(a.childNodes[g]));b.push(e.join(","));b.push("]\n")}b.push("}\n");return b.join("")};

//v.3.6 build 131023

/*
Copyright DHTMLX LTD. http://www.dhtmlx.com
You allowed to use this component or parts of it under GPL terms
To use it on other terms or get Professional edition of the component please contact us at sales@dhtmlx.com
*/