/**
 * Scaffolding a real EGroupware page provides and EgwFramework expects to find in document.body.
 *
 * The runner's own test page already has the #egw_script_id bootstrap div (see
 * web-test-runner.config.mjs), so all that is missing is the initial loader the framework removes
 * once it has finished its first render.
 */
export function setupEgwFrameworkTests()
{
	beforeEach(() =>
	{
		if(!document.getElementById("egw_fw_firstload"))
		{
			document.body.insertAdjacentHTML("afterbegin", '<div id="egw_fw_firstload"></div>');
		}
	});

	afterEach(() =>
	{
		// Only our own scaffolding: emptying document.body would take @open-wc's fixture wrapper
		// with it, and its own cleanup then fails trying to remove a node that is already gone
		document.getElementById("egw_fw_firstload")?.remove();
	});
}
