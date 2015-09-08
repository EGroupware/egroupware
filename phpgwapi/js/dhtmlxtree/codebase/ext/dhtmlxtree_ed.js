//v.3.6 build 131023

/*
Copyright DHTMLX LTD. http://www.dhtmlx.com
You allowed to use this component or parts of it under GPL terms
To use it on other terms or get Professional edition of the component please contact us at sales@dhtmlx.com
*/
dhtmlXTreeObject.prototype.enableItemEditor=function(c){this._eItEd=convertStringToBoolean(c);if(!this._eItEdFlag)this._edn_dblclick=this._edn_click_IE=!0,this._ie_aFunc=this.aFunc,this._ie_dblclickFuncHandler=this.dblclickFuncHandler,this.setOnDblClickHandler(function(a,b){this._edn_dblclick&&this._editItem(a,b);return!0}),this.setOnClickHandler(function(a,b){this._stopEditItem(a,b);this.ed_hist_clcik==a&&this._edn_click_IE&&this._editItem(a,b);this.ed_hist_clcik=a;return!0}),this._eItEdFlag=!0};
dhtmlXTreeObject.prototype.setOnEditHandler=function(c){this.attachEvent("onEdit",c)};dhtmlXTreeObject.prototype.setEditStartAction=function(c,a){this._edn_click_IE=convertStringToBoolean(c);this._edn_dblclick=convertStringToBoolean(a)};
dhtmlXTreeObject.prototype._stopEdit=function(c,a){if(this._editCell&&(this.dADTempOff=this.dADTempOffEd,this._editCell.id!=c)){var b=!0;a?(b=!1,this.callEvent("onEditCancel",[this._editCell.id,this._editCell._oldValue])):b=this.callEvent("onEdit",[2,this._editCell.id,this,this._editCell.span.childNodes[0].value]);if(b===!0)b=this._editCell.span.childNodes[0].value;else if(b===!1)b=this._editCell._oldValue;var d=b!=this._editCell._oldValue;this._editCell.span.innerHTML=b;this._editCell.label=this._editCell.span.innerHTML;
var e=this._editCell.i_sel?"selectedTreeRow":"standartTreeRow";this._editCell.span.className=e;this._editCell.span.parentNode.className="standartTreeRow";this._editCell.span.style.paddingRight=this._editCell.span.style.paddingLeft="5px";this._editCell.span.onclick=this._editCell.span.ondblclick=function(){};var f=this._editCell.id;this.childCalc&&this._fixChildCountLabel(this._editCell);this._editCell=null;a||this.callEvent("onEdit",[3,f,this,d]);this._enblkbrd&&(this.parentObject.lastChild.focus(),
this.parentObject.lastChild.focus())}};dhtmlXTreeObject.prototype._stopEditItem=function(c){this._stopEdit(c)};dhtmlXTreeObject.prototype.stopEdit=function(c){this._editCell&&this._stopEdit(this._editCell.id+"_non",c)};dhtmlXTreeObject.prototype.editItem=function(c){this._editItem(c,this)};
dhtmlXTreeObject.prototype._editItem=function(c){if(this._eItEd){this._stopEdit();var a=this._globalIdStorageFind(c);if(a){var b=this.callEvent("onEdit",[0,c,this,a.span.innerHTML]);if(b===!0)b=typeof a.span.innerText!="undefined"?a.span.innerText:a.span.textContent;else if(b===!1)return;this.dADTempOffEd=this.dADTempOff;this.dADTempOff=!1;this._editCell=a;a._oldValue=b;a.span.innerHTML="<input type='text' class='intreeeditRow' />";a.span.style.paddingRight=a.span.style.paddingLeft="0px";a.span.onclick=
a.span.ondblclick=function(a){(a||event).cancelBubble=!0};a.span.childNodes[0].value=b;a.span.childNodes[0].onselectstart=function(a){return(a||event).cancelBubble=!0};a.span.childNodes[0].onmousedown=function(a){return(a||event).cancelBubble=!0};a.span.childNodes[0].focus();a.span.childNodes[0].focus();a.span.onclick=function(a){(a||event).cancelBubble=!0;return!1};a.span.className="";a.span.parentNode.className="";var d=this;a.span.childNodes[0].onkeydown=function(a){if(!a)a=window.event;a.keyCode==
13?(a.cancelBubble=!0,d._stopEdit(window.undefined)):a.keyCode==27&&d._stopEdit(window.undefined,!0);(a||event).cancelBubble=!0};this.callEvent("onEdit",[1,c,this])}}};

//v.3.6 build 131023

/*
Copyright DHTMLX LTD. http://www.dhtmlx.com
You allowed to use this component or parts of it under GPL terms
To use it on other terms or get Professional edition of the component please contact us at sales@dhtmlx.com
*/