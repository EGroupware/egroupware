/*
	DynAPI Distribution
	dynapi.functions.String extension	
*/

var f = dynapi.functions;
f.String = {}; // used by dynapi.library


// String Functions --------------------------------

f.sprintf = function(t){
	var ar = arguments;
	var i=1,inx = t.indexOf("%s");
	while(inx>=0){
		t = t.substr(0, inx) + ar[i++] + t.substr(inx+2);
		inx = t.indexOf("%s");
	}
	return t;
};
f.strRepeat = function(s,n) {
	if(!s) return '';
	var i,a=[];
	for(i=1;i<=n;i++){
		a[a.length]=s;
	}
	return a.join('');
};
f.strReverse = function(s) {
	if(!s) return '';
	var a=(s+'').split('');
	a.reverse();
	return a.join('');
};
f.strStuff = function(s,v,index) {
	if(!s) return '';	
	if (index==null) s=s+v+'';
	else {
		var t1=t2=s+'';
		s=t1.substr(0,index)+v+t2.substr(index,t2.length-index);
	}
	return s;
};
f.trim = function(s,dir){
	if(!s) return;
	else s+=''; // make sure s is a string
	dir=(dir)? dir:'<>';
	if(dir=='<'||dir=='<>') s=s.replace(/^(\s+)/g,'');
	if(dir=='>'||dir=='<>') s=s.replace(/(\s+)$/g,'');
	return s;
};
f.limitString = function (s,max)
{
	if (!s) return s;
	else s+='';

	// Do not process HTML tags \\
	var tag = new RegExp(/[<][^>]*[>]/g);
	var special = new RegExp(/[&][^;]*;/g);
	var m, count=0;
	var tags = new Array();
	var specials = new Array();

	var r_s = s.replace(/[<][^>]*[>]/g,'\xFE').replace(/[&][^;]*;/g,'\xFF');
	
	while (m = tag.exec(s))
	{
		tags[tags.length] = m[0];
		max++;
	}

	while (m = special.exec(s))
	{
		specials[specials.length] = m[0];
		max += 2;
	}

	var i;
	var count = 0;
	var r_final = '';
	for (i=0; i<r_s.length;i++)
	{
		if (r_s[i] == '\xFF')
		{
			r_final += r_s.charAt(i);
		}
		else if (r_s[i] == '\xFE')
		{
			r_final += r_s.charAt(i);
			count++;
		}
		else if (count <= max)
		{
			r_final += r_s.charAt(i);
			count++;
		}
	}

	if (count > max)
	{
		r_final += '...';
	}
	
	for (i=0; i<tags.length; i++)
	{
		r_final = r_final.replace(/\xFE/,tags[i]);
	}

	for (i=0; i<specials.length; i++)
	{
		r_final = r_final.replace(/\xFF/,specials[i]);
	}

	return r_final;
}
