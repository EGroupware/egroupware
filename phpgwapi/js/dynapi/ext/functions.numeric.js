/*
	DynAPI Distribution
	dynapi.functions.Numeric extension	
*/

var f = dynapi.functions;
f.Numeric = Numeric = {}; // used by dynapi.library

// Numeric Function ---------------------------------

f.formatNumber = function(n,format){
	if(isNaN(n)) return;
	var i,c,f,comma,symbol='',sign='',decimals='',integers='';
	var fInt,fDec,nInt,nDec,len=0,cnt=0;
	if(n<0) sign='-';
	n+='';if(sign) n=n.replace('-','');
	format=(format)? format+'':'#,##0.00';
	if(format.indexOf(',')>=0) comma=',';
	if(format.indexOf('$')>=0) symbol='$';
	else if(format.indexOf('%')>=0)	symbol='%';
	s=format.split('.');
	fInt=((s[0]==''||s[0]==null||s[0]=='undefinded')? '':s[0]);
	fInt=fInt.split('').reverse().join('');
	fDec=(s[1]==''||s[1]==null||s[1]=='undefinded')? '':s[1];
	s=n.split('.');
	nInt=((s[0]==''||s[0]==null||s[0]=='undefinded')? '':s[0]);
	nInt=nInt.split('').reverse().join('');;
	nDec=(s[1]==''||s[1]==null||s[1]=='undefinded')? '':s[1];
	if (nInt) len=nInt.length;
	if (fInt.length>len) len=fInt.length;	
	for(i=0;i<len;i++){
		c=nInt.charAt(i);
		f=fInt.charAt(i);
		cnt++;
		if (cnt==4 && comma && (c||f=='0')) integers+=comma;
		if(f=='0' && !c) integers+='0';
		else if(c) integers+=c;
		if (cnt==4) cnt=1;
	}
	if(fDec) len=fDec.length;
	for(i=0;i<len;i++){
		c=nDec.charAt(i);
		f=fDec.charAt(i);
		if(f=='0' && !c) decimals+='0';
		else if((f=='#' || f=='0') && c) decimals+=c;
	}
	f=((integers+'').split('').reverse().join(''))+((decimals)? '.'+decimals:'');
	if(symbol=='%') f+=symbol;
	else f=symbol+f;
	return sign+f;
};
f.isFloat=function(n){
	if(typeof(n)=='number' && (n+'').indexOf('.')>=0) return true;
	else return false;
};
f.isInteger=function(n){
	if(typeof(n)=='number' && (n+'').indexOf('.')<0) return true;
	else return false;;
};
f.toInteger=function(dt){
	var vl;
	if(!dt) return 0;
	if(isNaN(dt)) vl=parseInt((dt+'').replace(/\,/g,''));
	else vl= parseInt(dt);
	if (isNaN(vl)) vl = 0;
	return vl;
};
f.toFloat=function(dt){
	var vl;
	if(!dt) return 0;
	if(isNaN(dt)) vl=parseFloat((dt+'').replace(/\,/g,''));
	else vl = parseFloat(dt);
	if (isNaN(vl)) vl = 0;
	return vl;
};
f.toBoolean = function(dt) {
	return (dt=='true'||dt>=1)? true:false;
};

f.isNumeric = function (n)
{
	var nReg = new RegExp('[-][[:digit:]]');
}
