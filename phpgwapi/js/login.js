/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


egw_LAB.wait(function() {
	$j.ajax('https://www.egroupware.org/social.js', {
		dataType: "jsonp",
		jsonp: false,
		jsonpCallback: "do_social",
		cache: true
	}).done(function(_data)
	{
		$j(document).ready(function() {
			var isPixelegg = $j('link[href*="pixelegg.css"]')[0];
			var social = $j(document.createElement('div'))
				.attr({
					id: "socialMedia",
					class: "socialMedia"
				})
				 .appendTo($j( isPixelegg? 'form' : '#socialBox'));

			for(var i=0; i < _data.length; ++i)
			{
				var data = _data[i];
				var url = (data.lang ? data.lang[$j('meta[name="language"]').attr('content')] : null) || data.url;
				$j(document.createElement('a')).attr({
					href: url,
					target: '_blank'
				})
				.appendTo(social)
				.append($j(document.createElement('img'))
					.attr('src', data.svg));
			}
		});
	});
});
