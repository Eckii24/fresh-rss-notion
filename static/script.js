// Initialize when DOM is ready
if (document.readyState && document.readyState !== 'loading') {
    configureNotionButtons();
} else {
    document.addEventListener('DOMContentLoaded', configureNotionButtons, false);
}

function configureNotionButtons() {
    // Use event delegation to handle notion button clicks
    document.getElementById('global').addEventListener('click', function (e) {
        for (var target = e.target; target && target != this; target = target.parentNode) {
            
            // Make sure button text is visible when article is displayed
            if (target.matches('.flux_header')) {
                const notionBtn = target.nextElementSibling?.querySelector('.notion-add-btn');
                if (notionBtn && !notionBtn.textContent.trim()) {
                    notionBtn.textContent = 'Add to Notion';
                }
            }

            // Handle notion button clicks
            if (target.matches('.notion-add-btn')) {
                e.preventDefault();
                e.stopPropagation();
                if (target.dataset.request) {
                    handleNotionButtonClick(target);
                }
                break;
            }
        }
    }, false);
}

function handleNotionButtonClick(button) {
    const container = button.parentNode;
    const contentDiv = container.querySelector('.notion-add-content');
    
    // If we're already in a success state, don't do anything
    if (container.classList.contains('success')) {
        return;
    }
    
    // If we're in error state, retry
    if (container.classList.contains('error')) {
        addToNotion(container, button);
        return;
    }
    
    // Otherwise, add to notion
    addToNotion(container, button);
}

async function addToNotion(container, button) {
    const contentDiv = container.querySelector('.notion-add-content');
    
    // Set loading state
    container.classList.remove('error', 'success');
    container.classList.add('loading');
    button.disabled = true;
    button.textContent = 'Adding...';
    contentDiv.innerHTML = 'Adding video to Notion database...';
    contentDiv.classList.add('visible');
    
    try {
        const url = button.dataset.request;
        const formData = new FormData();
        formData.append('ajax', 'true');
        formData.append('_csrf', context.csrf);
        
        const response = await fetch(url, {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Unknown error occurred');
        }
        
        // Set success state
        container.classList.remove('loading');
        container.classList.add('success');
        
        // Display the success message
        contentDiv.innerHTML = formatSuccessMessage(data.message, data.notion_page_id);
        button.textContent = 'Added to Notion';
        button.disabled = true; // Keep disabled on success
        
    } catch (error) {
        console.error('Notion add error:', error);
        
        // Set error state
        container.classList.remove('loading');
        container.classList.add('error');
        contentDiv.innerHTML = `Error: ${error.message}`;
        button.textContent = 'Retry';
        button.disabled = false;
    }
}

function formatSuccessMessage(message, pageId) {
    let formatted = `<strong>✅ ${message}</strong>`;
    
    if (pageId) {
        formatted += `<br><small>Page ID: ${pageId}</small>`;
    }
    
    return formatted;
}