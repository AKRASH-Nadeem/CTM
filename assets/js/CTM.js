function injectStylesIfPossible(stylesInjected) {
    const iframe = document.querySelector('iframe[name="editor-canvas"]');
    if (iframe && !stylesInjected) {
        // Wait for the iframe to fully load
        iframe.addEventListener('load', () => {
            const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
            if (iframeDoc.body) {
                const style = iframeDoc.createElement('style');
                style.textContent = `
                .editor-post-text-editor,
                .block-editor-block-list__layout {
                    display: none !important;
                }

                body {
                    padding: 0 !important;
                }
                `; // Your CSS here
                iframeDoc.body.appendChild(style);
                stylesInjected = true; // Prevent duplicate injections
            }
        });

        // If the iframe is already loaded, inject immediately
        if (iframe.contentDocument.readyState === 'complete') {
            iframe.dispatchEvent(new Event('load'));
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    wp.domReady(() => {
        const { select, dispatch } = wp.data;
        const { getBlocks } = select('core/block-editor');
        const { updateBlockAttributes } = dispatch('core/block-editor');
        const { subscribe } = wp.data;

        // Track if styles have already been injected to avoid duplicates
        let stylesInjected = false;

        // Lock the blocks
        const blocks = getBlocks();
        blocks.forEach(block => {
            updateBlockAttributes(block.clientId, { lock: { move: false, remove: false } });
        });

        // Run when the editor is ready
        subscribe(() => {
            const isEditorReady = wp.data.select('core/editor').getCurrentPost();
            if (isEditorReady) {
                injectStylesIfPossible(stylesInjected);
            }
        });

    });   
});