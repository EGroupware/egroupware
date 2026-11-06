let count = 1;

function escapeHtml(str) {
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

/**
 * Turns code fields with the :preview suffix into interactive code previews.
 */
module.exports = function (doc, options) {
  options = {
    within: 'body', // the element containing the code fields to convert
    ...options
  };

  const within = doc.querySelector(options.within);
  if (!within) {
    return doc;
  }

  within.querySelectorAll('[class*=":preview"]').forEach(code => {
    const pre = code.closest('pre');
    // !pre.parentNode: already removed by an earlier iteration of this same loop. Belt and braces
    // alongside the adjacentPre fix below - a detached node here can only throw.
    if (!pre || !pre.parentNode) {
      return;
    }
    // A preview may be followed by a second <pre> holding the same example's React source, which
    // is folded into the same widget. Only claim the next <pre> when it actually contains that -
    // otherwise two ordinary examples written back to back look like a pair, and the second one
    // is removed below. When the second is itself a :preview, this loop then reaches a <pre> that
    // is already detached and insertAdjacentHTML throws NoModificationAllowedError, failing the
    // whole build with no indication of which file caused it.
    const nextPre = pre.nextElementSibling?.tagName.toLowerCase() === 'pre' ? pre.nextElementSibling : null;
    const reactCode = nextPre?.querySelector('code[class$="react"]');
    const adjacentPre = reactCode ? nextPre : null;
    const sourceGroupId = `code-preview-source-group-${count}`;
    const isExpanded = code.getAttribute('class').includes(':expanded');

    count++;

    const htmlButton = `
      <button type="button"
        title="Show HTML code"
        class="code-preview__button code-preview__button--html"
      >
        HTML
      </button>
    `;

    const reactButton = `
      <button type="button" title="Show React code" class="code-preview__button code-preview__button--react">
        React
      </button>
    `;

    const codePreview = `
      <div class="code-preview ${isExpanded ? 'code-preview--expanded' : ''}">
        <div class="code-preview__preview">
          ${code.textContent}
          <div class="code-preview__resizer">
            <sl-icon name="grip-vertical"></sl-icon>
          </div>
        </div>

        <div class="code-preview__source-group" id="${sourceGroupId}">
          <div class="code-preview__source code-preview__source--html" ${reactCode ? 'data-flavor="html"' : ''}>
            <pre><code class="language-html">${escapeHtml(code.textContent)}</code></pre>
          </div>

          ${
            reactCode
              ? `
            <div class="code-preview__source code-preview__source--react" data-flavor="react">
              <pre><code class="language-jsx">${escapeHtml(reactCode.textContent)}</code></pre>
            </div>
          `
              : ''
          }
        </div>

        <div class="code-preview__buttons">
          <button
            type="button"
            class="code-preview__button code-preview__toggle"
            aria-expanded="${isExpanded ? 'true' : 'false'}"
            aria-controls="${sourceGroupId}"
          >
            Source
            <svg
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
          </button>

          ${reactCode ? ` ${htmlButton} ${reactButton} ` : ''}
        </div>
      </div>
    `;

    pre.insertAdjacentHTML('afterend', codePreview);
    pre.remove();

    if (adjacentPre) {
      adjacentPre.remove();
    }
  });

  // Wrap code preview scripts in anonymous functions so they don't run in the global scope
  doc.querySelectorAll('.code-preview__preview script').forEach(script => {
    if (script.type === 'module') {
      // Modules are already scoped
      script.textContent = script.innerHTML;
    } else {
      // Wrap non-modules in an anonymous function so they don't run in the global scope
      script.textContent = `(() => { ${script.innerHTML} })();`;
    }
  });

  return doc;
};
