// Shim to provide a stable default export for DOMPurify
// Import the UMD build so rollup (which handles commonjs) can bundle it.
import * as DOMPurify from 'dompurify/dist/purify.js';

// Export default to satisfy code that expects a default export in browser ESM tests.
// Rollup's commonjs interop merges the UMD exports onto the namespace, so sanitize() is
// present directly.  Under web-test-runner the request is remapped to the package's ESM
// build, whose namespace only carries "default" - unwrap it so both environments agree.
export default ((DOMPurify as any).sanitize ? DOMPurify : (DOMPurify as any).default);
