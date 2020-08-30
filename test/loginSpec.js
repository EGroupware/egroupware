let expect = require('chai').expect;
const { JSDOM } = require('jsdom'),

// the file I will be loading
	uri = 'https://boulder.egroupware.org/egroupware/login.php',

// the options that I will be giving to jsdom
	options = {
		runScripts: 'dangerously',	// 'outside-only' does NOT work for scripts in the loaded page!
		resources: 'usable'
	};

// load from an external file
describe('EGroupware login-page', function() {
	it('Should load egw object', function() {
		debugger
		return JSDOM.fromURL(uri, options).then(function (dom) {
			let window = dom.window,
				document = window.document;
			expect(document.querySelectorAll('form')).key(0);
			expect(document.querySelectorAll('form')[0].action).to.be.a('string').and.satisfy(msg => msg.startsWith(uri));
			return new Promise((resolve, reject) => {
				window.onload = resolve;
			}).then(function() {
				console.log('Window loaded :)');
				return new Promise((resolve, reject) => {
					window.egw_LAB.wait(function() {
						console.log('Async script-loading / egw_LAB done :)')
						resolve(window.egw);
					});
				})
			});
		}).then(function(egw) {
			expect(egw.webserverUrl).equal('/egroupware', 'egw.webserverURL !== "/egroupware"');
			expect(egw.lang('Test12345 %1', 'success')).to.equal('Test12345 success');
			egw.window.close();
		})
	})
})
