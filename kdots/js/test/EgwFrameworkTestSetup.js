// test/setup/egw-framework-setup.js
export function setupEgwFrameworkTests()
{
	beforeEach(async () =>
	{
		document.body.innerHTML = `
      <div id="egw_script_id" data-url="test.com"></div>
    `;
	});

	afterEach(() =>
	{
		document.body.innerHTML = '';
	});
}