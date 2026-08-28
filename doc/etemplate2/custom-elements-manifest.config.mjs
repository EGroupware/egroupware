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
	// The base glob only matches files whose basename starts with "Et2" - several real mixins
	// don't (SearchMixin.ts x2, SelectAccountMixin.ts, RowLimitedMixin.ts, ExposeMixin.ts), so they
	// were invisible to the analyzer entirely, not just misclassified. Et2Nextmatch/** was already
	// special-cased for the same reason (FilterMixin.ts); listing the other known non-Et2-prefixed
	// mixin files explicitly here does the same for them without widening to whole directories.
	globs: [
		"api/js/etemplate/**/Et2*.ts",
		"api/js/etemplate/Et2Nextmatch/**/*.ts",
		"api/js/etemplate/Et2Widget/SearchMixin.ts",
		"api/js/etemplate/Et2Select/SearchMixin.ts",
		"api/js/etemplate/Et2Select/SelectAccountMixin.ts",
		"api/js/etemplate/Layout/RowLimitedMixin.ts",
		"api/js/etemplate/Expose/ExposeMixin.ts"
	],
	/** Globs to exclude */
	exclude: ["api/js/etemplate/**/test/*","api/js/etemplate/**/Et2*Readonly.ts","api/js/etemplate/**/Et2*Mobile.ts"],//, 'et2_*.ts', '**/test/*', '**/*.styles.ts', '**/*.test.ts'],
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

		// Explicit `@category` override for the docs sidebar taxonomy - lets a widget's author
		// override the automatic mixin-based Input/Display categorization when it's wrong (e.g.
		// Et2Diff carries the Et2InputWidget mixin for API consistency but is a read-only diff
		// viewer, not a real input). Values match widget-taxonomy.cjs's CATEGORY_TAG_VALUES:
		// layout, input, display, media, navigation, dialogs, data-grid. Kept as its own small,
		// focused plugin rather than reviving the (disabled) generic multi-tag block below.
		{
			name: 'egroupware-category-tag',
			analyzePhase({ts, node, moduleDoc})
			{
				if (node.kind !== ts.SyntaxKind.ClassDeclaration || !node.name)
				{
					return;
				}
				const className = node.name.getText();
				const classDoc = moduleDoc?.declarations?.find(declaration => declaration.name === className);
				if (!classDoc)
				{
					return;
				}
				node.jsDoc?.forEach(jsDoc =>
				{
					jsDoc?.tags?.forEach(tag =>
					{
						if (tag.tagName.getText() === 'category')
						{
							classDoc.category = tag.comment?.toString().trim();
						}
					});
				});
			}
		},

		// Explicit `@related <ClassName>` tag(s) - for cases where two widgets share a
		// naming/conceptual kinship but not a real inheritance relationship, so the automatic
		// Related-family detection (same source directory + shared name substring) can't find
		// them. E.g. Et2VfsSelectDialog is "a dialog" in the conceptual sense but actually
		// extends SearchMixin(Et2InputWidget(LitElement)), not Et2Dialog - there's no class
		// hierarchy to detect automatically, and changing what it extends just to satisfy a
		// docs grouping would be a real (and risky) behavior change to the widget itself.
		// widget-taxonomy.cjs symmetrizes this: tagging one side makes both pages list each
		// other, so `@related` only needs to be added once, on whichever side is more obvious.
		// Multiple `@related` tags on one class are all collected.
		{
			name: 'egroupware-related-tag',
			analyzePhase({ts, node, moduleDoc})
			{
				if (node.kind !== ts.SyntaxKind.ClassDeclaration || !node.name)
				{
					return;
				}
				const className = node.name.getText();
				const classDoc = moduleDoc?.declarations?.find(declaration => declaration.name === className);
				if (!classDoc)
				{
					return;
				}
				node.jsDoc?.forEach(jsDoc =>
				{
					jsDoc?.tags?.forEach(tag =>
					{
						if (tag.tagName.getText() === 'related')
						{
							const value = tag.comment?.toString().trim();
							if (value)
							{
								classDoc.relatedTags = (classDoc.relatedTags || []).concat(value);
							}
						}
					});
				});
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