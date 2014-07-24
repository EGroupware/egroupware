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
			var social = $j(document.createElement('div'))
				.attr({
					id: "socialMedia",
					class: "socialMedia"
				})
				.appendTo($j('#socialBox'));

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

//	$j('img.bgfade').hide();
////                                var dg_H = $j(window).height();
////                                var dg_W = $j(window).width();
////    $j('#wrap').css({'height':dg_H,'width':dg_W});
//
//    function anim() {
//    $j("#wrap img.bgfade").first().appendTo('#wrap').fadeOut(3500);
//    $j("#wrap img").first().fadeIn(3500);
//    setTimeout(anim, 7000);
//    }
//anim();
//$j(window).resize(function(){window.location.href=window.location.href});
//	});
//
//});
