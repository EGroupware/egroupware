//v.3.6 build 131108

/*
Copyright DHTMLX LTD. http://www.dhtmlx.com
You allowed to use this component or parts of it under GPL terms
To use it on other terms or get Professional edition of the component please contact us at sales@dhtmlx.com
*/
dhtmlXMenuObject.prototype.enableEffect=function(a,b,c){this._menuEffect=a=="opacity"||a=="slide"||a=="slide+"?a:!1;for(var d in this.idPull)if(d.search(/polygon/)===0)this._pOpacityApply(d,_isIE?100:1),this.idPull[d].style.height="";this._pOpMax=(typeof b=="undefined"?100:b)/(_isIE?1:100);this._pOpStyleName=_isIE?"filter":"opacity";this._pOpStyleValue=_isIE?"progid:DXImageTransform.Microsoft.Alpha(Opacity=#)":"#";this._pSlSteps=_isIE?10:20;this._pSlTMTimeMax=c||50};
dhtmlXMenuObject.prototype._showPolygonEffect=function(a){this._pShowHide(a,!0)};dhtmlXMenuObject.prototype._hidePolygonEffect=function(a){this._pShowHide(a,!1)};dhtmlXMenuObject.prototype._pOpacityApply=function(a,b){this.idPull[a].style[this._pOpStyleName]=String(this._pOpStyleValue).replace("#",b||this.idPull[a]._op)};
dhtmlXMenuObject.prototype._pShowHide=function(a,b){if(this.idPull){if(this.idPull[a]._tmShow!=null){if(this.idPull[a]._step_h>0&&b==!0||this.idPull[a]._step_h<0&&b==!1)return;window.clearTimeout(this.idPull[a]._tmShow);this.idPull[a]._tmShow=null;this.idPull[a]._max_h=null}if(!(b==!1&&(this.idPull[a].style.visibility=="hidden"||this.idPull[a].style.display=="none"))){if(b==!0&&this.idPull[a].style.display=="none")this.idPull[a].style.visibility="hidden",this.idPull[a].style.display="";if(this.idPull[a]._max_h==
null){this.idPull[a]._max_h=parseInt(this.idPull[a].offsetHeight);this.idPull[a]._h=b==!0?0:this.idPull[a]._max_h;this.idPull[a]._step_h=Math.round(this.idPull[a]._max_h/this._pSlSteps)*(b==!0?1:-1);if(this.idPull[a]._step_h==0)return;this.idPull[a]._step_tm=Math.round(this._pSlTMTimeMax/this._pSlSteps);if(this._menuEffect=="slide+"||this._menuEffect=="opacity"){this.idPull[a].op_tm=this.idPull[a]._step_tm;this.idPull[a].op_step=this._pOpMax/this._pSlSteps*(b==!0?1:-1);if(_isIE)this.idPull[a].op_step=
Math.round(this.idPull[a].op_step);this.idPull[a]._op=b==!0?0:this._pOpMax}else this.idPull[a]._op=_isIE?100:1;this._pOpacityApply(a);if(this._menuEffect.search(/slide/)===0)this.idPull[a].style.height="0px";this.idPull[a].style.visibility="visible"}this._pEffectSet(a,this.idPull[a]._h+this.idPull[a]._step_h)}}};
dhtmlXMenuObject.prototype._pEffectSet=function(a,b){if(this.idPull){this.idPull[a]._tmShow&&window.clearTimeout(this.idPull[a]._tmShow);this.idPull[a]._h=Math.max(0,Math.min(b,this.idPull[a]._max_h));if(this._menuEffect.search(/slide/)===0)this.idPull[a].style.height=this.idPull[a]._h+"px";b+=this.idPull[a]._step_h;if(this._menuEffect=="slide+"||this._menuEffect=="opacity")this.idPull[a]._op=Math.max(0,Math.min(this._pOpMax,this.idPull[a]._op+this.idPull[a].op_step)),this._pOpacityApply(a);if(this.idPull[a]._step_h>
0&&b<=this.idPull[a]._max_h||this.idPull[a]._step_h<0&&b>=0){var c=this;this.idPull[a]._tmShow=window.setTimeout(function(){c._pEffectSet(a,b)},this.idPull[a]._step_tm)}else{if(this._menuEffect.search(/slide/)===0)this.idPull[a].style.height="";if(this.idPull[a]._step_h<0)this.idPull[a].style.visibility="hidden";if(this._menuEffect=="slide+"||this._menuEffect=="opacity")this.idPull[a]._op=this.idPull[a]._step_h<0?_isIE?100:1:this._pOpMax,this._pOpacityApply(a);this.idPull[a]._tmShow=null;this.idPull[a]._h=
null;this.idPull[a]._max_h=null;this.idPull[a]._step_tm=null}}};

//v.3.6 build 131108

/*
Copyright DHTMLX LTD. http://www.dhtmlx.com
You allowed to use this component or parts of it under GPL terms
To use it on other terms or get Professional edition of the component please contact us at sales@dhtmlx.com
*/