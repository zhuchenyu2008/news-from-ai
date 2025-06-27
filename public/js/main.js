document.addEventListener('DOMContentLoaded', () => {
    const newsFeedContainer = document.getElementById('news-feed');
    const body = document.body;

    // --- 1. Theme Switching ---
    function applyTheme() {
        const currentHour = new Date().getHours();
        // Light theme between 7 AM and 6 PM (inclusive of 7, exclusive of 19)
        if (currentHour >= 7 && currentHour < 19) {
            body.classList.remove('dark-theme');
            body.classList.add('light-theme');
        } else {
            body.classList.remove('light-theme');
            body.classList.add('dark-theme');
        }
    }
    applyTheme(); // Apply theme on initial load
    // Optional: Re-apply theme periodically or on visibility change if desired
    // setInterval(applyTheme, 60 * 60 * 1000); // e.g., every hour


    // --- Optional: Manual Theme Toggle ---
    // const themeToggleButton = document.getElementById('theme-toggle');
    // if (themeToggleButton) {
    //     themeToggleButton.addEventListener('click', () => {
    //         if (body.classList.contains('light-theme')) {
    //             body.classList.remove('light-theme');
    //             body.classList.add('dark-theme');
    //             themeToggleButton.textContent = "Switch to Light Theme";
    //         } else {
    //             body.classList.remove('dark-theme');
    //             body.classList.add('light-theme');
    //             themeToggleButton.textContent = "Switch to Dark Theme";
    //         }
    //     });
    // }


    // --- 2. Data Fetching ---
    async function fetchNews() {
        try {
            // Adjust the path if your public folder is not the document root
            // or if you are using a base URL.
            // Assuming get_news.php is at /app/api/get_news.php relative to project root
            // and the website is served from /public/
            // So, the URL relative to the domain would be /app/api/get_news.php if 'public' is not in the URL path.
            // If 'public' is the web root, then it's '/../app/api/get_news.php' or a direct '/app/api/get_news.php'
            // depending on server setup.
            // For simplicity, assuming the API is accessible at '/app/api/get_news.php' from the root.
            // This might need adjustment based on actual deployment.

            // Standard approach: if index.php is at domain.com/, then API is at domain.com/app/api/get_news.php
            // This requires .htaccess or server config to route /app/* outside /public if needed,
            // or placing the api folder inside public (not recommended for this structure).
            // Let's assume a setup where the API is directly callable from the root.
            // If your webserver serves from `public` and `app` is outside, you might need
            // a path like `/api/get_news.php` if you have a rewrite rule, or `/../app/api/get_news.php` (less common).
            // For this project structure, `../app/api/get_news.php` is the correct relative path from `public/js` to `app/api`.
            // However, fetch URLs are relative to the HTML page's location, not the JS file's location.
            // So, if index.php is in public/, the path to app/api/get_news.php is `../app/api/get_news.php`.
            // Or, if the server is configured to serve `app` directly: `/app/api/get_news.php`.
            // The prompt implies `get_news.php` is inside `app/api/`, so if `public` is the webroot,
            // the direct URL path would be `../app/api/get_news.php` (going up one level from public).
            // This is often simplified by routing, e.g., `domain.com/api/news` -> `app/api/get_news.php`.
            // Given the PHP files are not designed with a router, we'll use a relative path from public.
            // A better setup would be to have a single entry point (e.g. public/index.php handling all requests)
            // or placing the api directly accessible.
            // Let's assume a common scenario where `app` is not directly web-accessible, and a symlink or alias
            // might be used, or the API is within the public directory (which is not the case here).
            // The most robust way without complex server config for *this specific structure*
            // would be to have `get_news.php` inside `public/api/` or use a PHP router in `public/index.php`.
            // Sticking to the provided structure, we'll assume the API is callable via a path.
            // The prompt implies `/app/api/get_news.php` is the fetch URL from client-side.
            // This means the web server root must be the project root, not the `public` folder,
            // OR there's a rewrite rule.
            // Let's assume the web server root is the project root for the API call to work as `/app/api/...`

            const response = await fetch('../app/api/get_news.php'); // Relative from public/index.php
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const newsItems = await response.json();
            renderNews(newsItems);
        } catch (error) {
            console.error("Failed to fetch news:", error);
            if (newsFeedContainer) {
                newsFeedContainer.innerHTML = '<p>Failed to load news. Please try again later.</p>';
            }
        }
    }

    // --- 3. Dynamic Rendering ---
    function renderNews(newsItems) {
        if (!newsFeedContainer) {
            console.error("News feed container not found.");
            return;
        }
        newsFeedContainer.innerHTML = ''; // Clear loading message or old content

        if (!newsItems || newsItems.length === 0) {
            newsFeedContainer.innerHTML = '<p>No news available at the moment.</p>';
            return;
        }

        newsItems.forEach((item, index) => {
            const articleElement = document.createElement('article');
            articleElement.classList.add('news-item');

            // Sanitize Markdown content before parsing if necessary, but marked.js handles HTML escaping by default.
            // For added security, especially if markdown can come from less trusted AI outputs,
            // consider a more robust HTML sanitizer like DOMPurify *after* marked.parse().
            // For this project, we'll rely on marked's default behavior.
            const unsafeHtmlContent = marked.parse(item.content_markdown || '');

            // Basic metadata
            let metadataHtml = `<div class="metadata">`;
            metadataHtml += `<span>Format: ${item.format || 'N/A'}</span>`;
            if (item.created_at) {
                metadataHtml += `<span>Published: ${new Date(item.created_at).toLocaleString()}</span>`;
            }
            metadataHtml += `</div>`;

            // Source links
            let sourcesHtml = '';
            if (item.source_url) { // For RSS summaries
                sourcesHtml += `<div class="source-link"><strong>Source:</strong> <a href="${item.source_url}" target="_blank" rel="noopener noreferrer">${item.source_url}</a></div>`;
            }
            if (item.sources_json) { // For AI-generated multi-source reports
                try {
                    const sourcesArray = JSON.parse(item.sources_json);
                    if (Array.isArray(sourcesArray) && sourcesArray.length > 0) {
                        sourcesHtml += `<div class="sources-list"><strong>Sources:</strong><ul>`;
                        sourcesArray.forEach(sourceUrl => {
                            if (typeof sourceUrl === 'string' && sourceUrl.trim() !== '') {
                                sourcesHtml += `<li><a href="${sourceUrl}" target="_blank" rel="noopener noreferrer">${sourceUrl}</a></li>`;
                            } else if (typeof sourceUrl === 'object' && sourceUrl.url) { // If sources are objects with a URL property
                                sourcesHtml += `<li><a href="${sourceUrl.url}" target="_blank" rel="noopener noreferrer">${sourceUrl.title || sourceUrl.url}</a></li>`;
                            }
                        });
                        sourcesHtml += `</ul></div>`;
                    }
                } catch (e) {
                    console.warn("Failed to parse sources_json:", item.sources_json, e);
                }
            }

            // Use a template literal for cleaner HTML structure
            // The title could be part of the markdown, or we extract it if necessary.
            // For now, assuming the markdown content includes its own title (e.g. H1 or H2).
            articleElement.innerHTML = `
                ${metadataHtml}
                <div class="content">
                    ${unsafeHtmlContent}
                </div>
                ${sourcesHtml}
            `;

            newsFeedContainer.appendChild(articleElement);

            // Add animation class with a slight delay for a staggered effect
            setTimeout(() => {
                articleElement.classList.add('animate-in');
            }, index * 100); // Adjust delay as needed
        });
    }

    // Initial fetch
    if (newsFeedContainer) {
        fetchNews();
    } else {
        console.error("The #news-feed container element was not found in the DOM.");
    }
});
