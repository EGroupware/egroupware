
try{if('undefined'==typeof xajax)
throw{name:'SequenceError',message:'Error: xajax core was not detected, legacy module disabled.'}
if('undefined'==typeof xajax.legacy)
xajax.legacy={}
xajax.legacy.call=xajax.call;xajax.call=function(sFunction,objParameters){var oOpt={}
oOpt.parameters=objParameters;if(undefined!=xajax.loadingFunction){if(undefined==oOpt.callback)
oOpt.callback={}
oOpt.callback.onResponseDelay=xajax.loadingFunction;}
if(undefined!=xajax.doneLoadingFunction){if(undefined==oOpt.callback)
oOpt.callback={}
oOpt.callback.onComplete=xajax.doneLoadingFunction;}
return xajax.legacy.call(sFunction,oOpt);}
xajax.legacy.isLoaded=true;}catch(e){alert(e.name+': '+e.message);}
