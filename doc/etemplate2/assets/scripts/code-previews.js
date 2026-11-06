(() =>
{
	function convertModuleLinks(html)
	{
		html = html
			.replace(/@shoelace-style\/shoelace/g, `https://esm.sh/@shoelace-style/shoelace@${egwVersion}`)
			.replace(/from 'react'/g, `from 'https://esm.sh/react@${reactVersion}'`)
			.replace(/from "react"/g, `from "https://esm.sh/react@${reactVersion}"`);

		return html;
	}

	function getAdjacentExample(name, pre)
	{
		let currentPre = pre.nextElementSibling;

		while (currentPre?.tagName.toLowerCase() === 'pre')
		{
			if (currentPre?.getAttribute('data-lang').split(' ').includes(name))
			{
				return currentPre;
			}

			currentPre = currentPre.nextElementSibling;
		}

		return null;
	}

	function runScript(script)
	{
		const newScript = document.createElement('script');

		if (script.type === 'module')
		{
			newScript.type = 'module';
			newScript.textContent = script.innerHTML;
		}
		else
		{
			newScript.appendChild(document.createTextNode(`(() => { ${script.innerHTML} })();`));
		}

		script.parentNode.replaceChild(newScript, script);
	}

	function getFlavor()
	{
		return sessionStorage.getItem('flavor') || 'html';
	}

	function setFlavor(newFlavor)
	{
		flavor = ['html', 'react'].includes(newFlavor) ? newFlavor : 'html';
		sessionStorage.setItem('flavor', flavor);

		// Set the flavor class on the body
		document.documentElement.classList.toggle('flavor-html', flavor === 'html');
		document.documentElement.classList.toggle('flavor-react', flavor === 'react');
	}

	function syncFlavor()
	{
		setFlavor(getFlavor());

		document.querySelectorAll('.code-preview__button--html').forEach(preview =>
		{
			if (flavor === 'html')
			{
				preview.classList.add('code-preview__button--selected');
			}
		});

		document.querySelectorAll('.code-preview__button--react').forEach(preview =>
		{
			if (flavor === 'react')
			{
				preview.classList.add('code-preview__button--selected');
			}
		});
	}

	const egwVersion = document.documentElement.getAttribute('data-egroupware-version');
	const shoelaceVersion = document.documentElement.getAttribute('data-shoelace-version');
	const reactVersion = '18.2.0';
	const cdndir = 'cdn';
	const npmdir = 'dist';
	let flavor = getFlavor();
	let count = 1;

	// We need the version to open
	if (!egwVersion)
	{
		throw new Error('The data-egroupware-version attribute is missing from <html>.');
	}

	// Sync flavor UI on page load
	syncFlavor();

	//
	// Resizing previews
	//
	document.addEventListener('mousedown', handleResizerDrag);
	document.addEventListener('touchstart', handleResizerDrag, {passive: true});

	function handleResizerDrag(event)
	{
		const resizer = event.target.closest('.code-preview__resizer');
		const preview = event.target.closest('.code-preview__preview');

		if (!resizer || !preview)
		{
			return;
		}

		let startX = event.changedTouches ? event.changedTouches[0].pageX : event.clientX;
		let startWidth = parseInt(document.defaultView.getComputedStyle(preview).width, 10);

		event.preventDefault();
		preview.classList.add('code-preview__preview--dragging');
		document.documentElement.addEventListener('mousemove', dragMove);
		document.documentElement.addEventListener('touchmove', dragMove);
		document.documentElement.addEventListener('mouseup', dragStop);
		document.documentElement.addEventListener('touchend', dragStop);

		function dragMove(event)
		{
			const width = startWidth + (event.changedTouches ? event.changedTouches[0].pageX : event.pageX) - startX;
			preview.style.width = `${width}px`;
		}

		function dragStop()
		{
			preview.classList.remove('code-preview__preview--dragging');
			document.documentElement.removeEventListener('mousemove', dragMove);
			document.documentElement.removeEventListener('touchmove', dragMove);
			document.documentElement.removeEventListener('mouseup', dragStop);
			document.documentElement.removeEventListener('touchend', dragStop);
		}
	}

	//
	// Toggle source mode
	//
	document.addEventListener('click', event =>
	{
		const button = event.target.closest('.code-preview__button');
		const codeBlock = button?.closest('.code-preview');

		if (button?.classList.contains('code-preview__button--html'))
		{
			// Show HTML
			setFlavor('html');
			toggleSource(codeBlock, true);
		}
		else if (button?.classList.contains('code-preview__button--react'))
		{
			// Show React
			setFlavor('react');
			toggleSource(codeBlock, true);
		}
		else if (button?.classList.contains('code-preview__toggle'))
		{
			// Toggle source
			toggleSource(codeBlock);
		}
		else
		{
			return;
		}

		// Update flavor buttons
		[...document.querySelectorAll('.code-preview')].forEach(cb =>
		{
			cb.querySelector('.code-preview__button--html')?.classList.toggle(
				'code-preview__button--selected',
				flavor === 'html'
			);
			cb.querySelector('.code-preview__button--react')?.classList.toggle(
				'code-preview__button--selected',
				flavor === 'react'
			);
		});
	});

	function toggleSource(codeBlock, force)
	{
		codeBlock.classList.toggle('code-preview--expanded', force);
		event.target.setAttribute('aria-expanded', codeBlock.classList.contains('code-preview--expanded'));
	}

	// No "Edit on CodePen" button: a pen can only pull Shoelace from a CDN, and an <et2-*>
	// example needs the EGroupware bundle (etemplate2 + the egw bootstrap it depends on) that
	// only this site serves - so every exported pen rendered an empty page. See default.njk
	// for what a page has to load before a widget will upgrade at all.

	// Set the initial flavor
	window.addEventListener('turbo:load', syncFlavor);
})();
