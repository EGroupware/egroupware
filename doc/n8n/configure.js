/**
 * Used by start.sh: fills the example workflow or its two credentials from the EGW_N8N_*
 * environment variables and prints them as JSON for n8n's import:workflow / import:credentials.
 *
 *   node configure.js workflow      the workflow, with its Config node filled in
 *   node configure.js credentials   EGroupware Basic auth and the AI proxy key
 */
const env = process.env;

if (process.argv[2] === 'credentials')
{
	console.log(JSON.stringify([
		{
			id: 'egwRestBasicAuth', name: 'EGroupware REST API', type: 'httpBasicAuth',
			data: {user: env.EGW_N8N_USER, password: env.EGW_N8N_PASSWORD},
		},
		{
			id: 'egwAiProxyHeader', name: 'EGroupware AI proxy', type: 'httpHeaderAuth',
			data: {name: 'Authorization', value: 'Bearer ' + env.EGW_N8N_AI_KEY},
		},
	]));
}
else
{
	const workflow = require('./mail-to-tracker.workflow.json');
	const values = {
		egwBase: env.EGW_N8N_URL ? env.EGW_N8N_URL.replace(/\/$/, '') + '/groupdav.php' : '',
		egwUser: env.EGW_N8N_USER,
		mailAccount: env.EGW_N8N_MAIL_ACCOUNT,
		queueIds: env.EGW_N8N_QUEUES,
		llmUrl: env.EGW_N8N_LLM_URL,
		llmModel: env.EGW_N8N_LLM_MODEL,
	};
	for (const field of workflow.nodes.find(node => node.name === 'Config').parameters.assignments.assignments)
	{
		if (values[field.name])
		{
			field.value = values[field.name];
		}
	}
	console.log(JSON.stringify(workflow));
}
