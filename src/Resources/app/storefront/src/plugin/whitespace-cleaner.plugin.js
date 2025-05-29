import Plugin from 'src/plugin-system/plugin.class';

export default class WhitespaceCleanerPlugin extends Plugin {
    init() {
        // Target elements with .finish-order-subtitle on finish page
        if (document.body.classList.contains('is-act-finishpage')) {
            this.cleanWhitespace();
        }
    }

    cleanWhitespace() {
        // Select all .finish-order-subtitle elements
        const elements = document.querySelectorAll('.finish-order-subtitle');
        
        elements.forEach((element) => {
            // Get text content and trim it
            const textContent = element.textContent.trim();
            
            // If the content is empty after trimming (only whitespace), clear it
            if (textContent === '') {
                element.textContent = '';
            }
        });
    }
}