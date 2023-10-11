import * as path from 'path';
//import {customElementJetBrainsPlugin} from 'custom-element-jet-brains-integration';
//import {customElementVsCodePlugin} from 'custom-element-vs-code-integration';
import {parse} from 'comment-parser';
import {pascalCase} from 'pascal-case';
import commandLineArgs from 'command-line-args';
import fs from 'fs';

const packageData = JSON.parse(fs.readFileSync('package.json', 'utf8'));
const {name, description, version, author, homepage, license} = packageData;


function noDash(string)
{
	return string.replace(/^\s?-/, '').trim();
}

function replace(string, terms)
{
	terms.forEach(({from, to}) =>
	{
		string = string?.replace(from, to);
	});

	return string;
}

export default {
	globs: ["api/js/etemplate/**/Et2*/*.ts"],
	/** Globs to exclude */
	exclude: [],//, 'et2_*.ts', '**/test/*', '**/*.styles.ts', '**/*.test.ts'],
	dev: false,
	litelement: true,
	plugins: [
		// Append package data
		{
			name: 'egroupware-package-data',
			packageLinkPhase({customElementsManifest})
			{
				customElementsManifest.package = {name, description, version, author, homepage, license};
			}
		},

		// Parse custom jsDoc tags
		{
			name: 'shoelace-custom-tags',
			analyzePhase({ts, node, moduleDoc})
			{
				switch (node.kind)
				{
					case ts.SyntaxKind.ClassDeclaration:
					{
						const className = node.name.getText();
						const classDoc = moduleDoc?.declarations?.find(declaration => declaration.name === className);
						const customTags = ['animation', 'dependency', 'documentation', 'since', 'status', 'title'];
						let customComments = '/**';

						node.jsDoc?.forEach(jsDoc =>
						{
							jsDoc?.tags?.forEach(tag =>
							{
								const tagName = tag.tagName.getText();

								if (customTags.includes(tagName))
								{
									customComments += `\n * @${tagName} ${tag.comment}`;
								}
							});
						});

						// This is what allows us to map JSDOC comments to ReactWrappers.
						classDoc['jsDoc'] = node.jsDoc?.map(jsDoc => jsDoc.getFullText()).join('\n');

//						const parsed = parse(`${customComments}\n */`);
						/*
												parsed[0].tags?.forEach(t =>
												{
													switch (t.tag)
													{
														// Animations
														case 'animation':
															if (!Array.isArray(classDoc['animations']))
															{
																classDoc['animations'] = [];
															}
															classDoc['animations'].push({
																name: t.name,
																description: noDash(t.description)
															});
															break;

														// Dependencies
														case 'dependency':
															if (!Array.isArray(classDoc['dependencies']))
															{
																classDoc['dependencies'] = [];
															}
															classDoc['dependencies'].push(t.name);
															break;

														// Value-only metadata tags
														case 'documentation':
														case 'since':
														case 'status':
														case 'title':
															classDoc[t.tag] = t.name;
															break;

														// All other tags
														default:
															if (!Array.isArray(classDoc[t.tag]))
															{
																classDoc[t.tag] = [];
															}

															classDoc[t.tag].push({
																name: t.name,
																description: t.description,
																type: t.type || undefined
															});
													}
												});
											*/
					}
				}
			}
		},
		{
			name: 'shoelace-translate-module-paths',
			packageLinkPhase({customElementsManifest})
			{
				customElementsManifest?.modules?.forEach(mod =>
				{
					//
					// CEM paths look like this:
					//
					//  src/components/button/button.ts
					//
					// But we want them to look like this:
					//
					//  components/button/button.js
					//
					const terms = [
						{from: /^src\//, to: ''}, // Strip the src/ prefix
						{from: /\.component.(t|j)sx?$/, to: '.js'} // Convert .ts to .js
					];

					mod.path = replace(mod.path, terms);

					for (const ex of mod.exports ?? [])
					{
						ex.declaration.module = replace(ex.declaration.module, terms);
					}

					for (const dec of mod.declarations ?? [])
					{
						if (dec.kind === 'class')
						{
							for (const member of dec.members ?? [])
							{
								if (member.inheritedFrom)
								{
									member.inheritedFrom.module = replace(member.inheritedFrom.module, terms);
								}
							}
						}
					}
				});
			}
		},

		// Generate custom VS Code data
		/*
		customElementVsCodePlugin({
			outdir,
			cssFileName: null,
			referencesTemplate: (_, tag) => [
				{
					name: 'Documentation',
					url: `https://shoelace.style/components/${tag.replace('sl-', '')}`
				}
			]
		}),

		customElementJetBrainsPlugin({
			excludeCss: true,
			referencesTemplate: (_, tag) =>
			{
				return {
					name: 'Documentation',
					url: `https://shoelace.style/components/${tag.replace('sl-', '')}`
				};
			}
		})

		 */
	]
};
