const { isExternalLink } = require('./strings.cjs');

/**
 * Transforms external links to make them safer and optionally add a target. The provided doc should be a document
 * object provided by JSDOM. The same document will be returned with the appropriate DOM manipulations.
 */
module.exports = function (doc, options) {
  options = {
    className: 'external-link', // the class name to add to links
    noopener: true, // sets rel="noopener"
    noreferrer: true, // sets rel="noreferrer"
    ignore: () => false, // callback function to filter links that should be ignored
    within: 'body', // element that contains the target links
    target: '', // sets the target attribute
    ...options
  };

  const within = doc.querySelector(options.within);

  if (within) {
    within.querySelectorAll('a').forEach(link => {
      if (isExternalLink(link) && !options.ignore(link)) {
        link.classList.add(options.className);

        const rel = [];
        if (options.noopener) rel.push('noopener');
        if (options.noreferrer) rel.push('noreferrer');

        if (rel.length) {
          link.setAttribute('rel', rel.join(' '));
        }

        if (options.target) {
          link.setAttribute('target', options.target);
        }

        // The arrow that says "this leaves the site". docs.css has styled
        // .external-link__icon (and hidden it in @media print) all along, but nothing ever
        // produced the element, so the rules were dead.
        //
        // Skipped for a link with no text of its own - the sidebar's GitHub / Star / EGroupware
        // buttons are icons already, and an arrow tacked onto one reads as a second icon rather
        // than as a hint. Inline SVG rather than an icon font or a CDN sprite: it inherits
        // currentColor, so it is correct in both themes and needs nothing loaded.
        if (link.textContent.trim() && !link.querySelector('.external-link__icon')) {
          const icon = doc.createElementNS('http://www.w3.org/2000/svg', 'svg');
          icon.setAttribute('class', 'external-link__icon');
          icon.setAttribute('viewBox', '0 0 24 24');
          icon.setAttribute('fill', 'none');
          icon.setAttribute('stroke', 'currentColor');
          icon.setAttribute('stroke-width', '2');
          icon.setAttribute('stroke-linecap', 'round');
          icon.setAttribute('stroke-linejoin', 'round');
          // Decorative: the link text already says where it goes, and target="_blank" is
          // announced by the browser.
          icon.setAttribute('aria-hidden', 'true');
          const box = doc.createElementNS('http://www.w3.org/2000/svg', 'path');
          box.setAttribute('d', 'M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6');
          const arrow = doc.createElementNS('http://www.w3.org/2000/svg', 'polyline');
          arrow.setAttribute('points', '15 3 21 3 21 9');
          const line = doc.createElementNS('http://www.w3.org/2000/svg', 'line');
          line.setAttribute('x1', '10'); line.setAttribute('y1', '14');
          line.setAttribute('x2', '21'); line.setAttribute('y2', '3');
          icon.append(box, arrow, line);
          // A word joiner first, so the arrow cannot wrap onto a line of its own and sit there
          // orphaned under the link it belongs to. Zero width, unlike &nbsp;, so it does not
          // fight the icon's own margin.
          link.append(doc.createTextNode('\u2060'), icon);
        }
      }
    });
  }

  return doc;
};
