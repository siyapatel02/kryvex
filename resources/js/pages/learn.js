/* ==========================================================================
   KRYVEX LEARN & ACADEMY INTERACTIVE LOGIC (learn.js)
   ========================================================================== */
// import footer from '../pages/footer.js';


document.addEventListener('DOMContentLoaded', () => {
    initCategoryFiltering();
    initSearchFilter();
    initGlossaryAccordions();
});

// Category Tabs Filter
function initCategoryFiltering() {
    const tabButtons = document.querySelectorAll('.learn-tab-btn');
    const guideCards = document.querySelectorAll('.learn-guide-card');

    tabButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            tabButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const category = btn.dataset.category || 'all';

            guideCards.forEach((card) => {
                const cardCat = card.dataset.category || '';
                if (category === 'all' || cardCat === category) {
                    card.style.display = 'flex';
                    card.style.opacity = '1';
                } else {
                    card.style.display = 'none';
                    card.style.opacity = '0';
                }
            });
        });
    });
}

// Live Search Input Filter
function initSearchFilter() {
    const searchInput = document.getElementById('learnSearchInput');
    if (!searchInput) return;

    searchInput.addEventListener('input', (e) => {
        const query = e.target.value.trim().toLowerCase();
        const guideCards = document.querySelectorAll('.learn-guide-card');
        const glossaryItems = document.querySelectorAll('.glossary-item');

        // Filter Guides
        guideCards.forEach((card) => {
            const title = card.querySelector('h3')?.textContent.toLowerCase() || '';
            const desc = card.querySelector('p')?.textContent.toLowerCase() || '';
            const match = title.includes(query) || desc.includes(query);
            card.style.display = match ? 'flex' : 'none';
        });

        // Filter Glossary
        glossaryItems.forEach((item) => {
            const term = item.querySelector('.glossary-term')?.textContent.toLowerCase() || '';
            const def = item.querySelector('.glossary-content')?.textContent.toLowerCase() || '';
            const match = term.includes(query) || def.includes(query);
            item.style.display = match ? 'block' : 'none';
        });
    });
}

// Single Open Glossary Accordions
function initGlossaryAccordions() {
    const headers = document.querySelectorAll('.glossary-header');

    headers.forEach((header) => {
        header.addEventListener('click', () => {
            const currentItem = header.closest('.glossary-item');
            if (!currentItem) return;

            const isAlreadyOpen = currentItem.classList.contains('is-open');

            // Close all items
            document.querySelectorAll('.glossary-item').forEach((item) => {
                item.classList.remove('is-open');
                const icon = item.querySelector('.glossary-icon');
                if (icon) icon.textContent = '+';
            });

            // Toggle current if it wasn't open
            if (!isAlreadyOpen) {
                currentItem.classList.add('is-open');
                const icon = header.querySelector('.glossary-icon');
                if (icon) icon.textContent = '−';
            }
        });
    });
    // <footer />
}
