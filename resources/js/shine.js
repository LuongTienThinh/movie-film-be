document.querySelectorAll('.shine-select').forEach(select => {
    const header = select.querySelector('.shine-select-header');
    const body = select.querySelector('.shine-select-body');
    const items = select.querySelectorAll('.shine-select-item');

    const selectedSpan = header.querySelector('.selected-values');
    let searchInput = header.querySelector('.search-input');

    const mode = select.dataset.mode || 'single';
    let selectedText = mode === 'single' ? searchInput.placeholder : null;

    let selectedValues = '';
    if (mode === 'multiple') {
        selectedValues = selectedSpan.dataset.selected.split(',') || [];
    }

    const updateHeader = () => {
        if (mode === 'multiple') {
            selectedSpan.innerHTML = selectedValues.length > 0 ?
                selectedValues.map((val, index) => {
                    const item = Array.from(items).find(item => item.dataset.value === val);
                    const text = item ? item.querySelector('span').textContent : val;

                    return `
                            <span class="badge" data-value="${val}">
                                <span>${text}</span>
                                <span class="remove-btn">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" fill="currentColor">
                                        <path d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z"/>
                                    </svg>
                                </span>
                            </span>`;
                }).join('') + '<input type="text" class="search-input" placeholder="">' :
                '<input type="text" class="search-input" placeholder="Select an option">';
            selectedSpan.setAttribute('data-selected', selectedValues.join(','));

            searchInput = selectedSpan.querySelector('.search-input');

            attachSearchInputEvents();
        } else {
            selectedSpan.innerHTML = `<input type="text" class="search-input" placeholder="${selectedText}">`;
            selectedSpan.setAttribute('data-selected', selectedValues);

            searchInput = selectedSpan.querySelector('.search-input');

            attachSearchInputEvents();
        }
    };

    const attachSearchInputEvents = () => {
        searchInput.addEventListener('input', (e) => {
            filterItems();
        });
        searchInput.addEventListener('click', (e) => {
            e.stopPropagation();

            if (!body.classList.contains('active')) {
                openSelect();
            }
        });
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === ' ') {
                e.preventDefault();
                e.stopPropagation();

                e.target.value += ' ';
            }
        });
    };

    const filterItems = () => {
        const searchText = searchInput.value.toLowerCase().trim();
        let hasVisibleItems = false;

        items.forEach(item => {
            const text = item.querySelector('span').textContent.toLowerCase();
            const isVisible = searchText === '' || text.includes(searchText);

            item.classList.toggle('hidden', !isVisible);
            if (isVisible) hasVisibleItems = true;
        });
        if (body.classList.contains('active')) {
            const fullHeight = hasVisibleItems ? body.scrollHeight : 0;
            body.style.setProperty('--shine-select-target-height', `${fullHeight}px`);

            if (!hasVisibleItems) {
                body.style.height = '0';
            } else if (body.style.height !== 'auto') {
                body.style.height = `${fullHeight}px`;

                requestAnimationFrame(() => {
                    body.style.height = 'auto';
                });
            }
        }
    };

    const openSelect = () => {
        if (!body.classList.contains('active')) {
            header.disabled = true;

            body.classList.remove('collapsing');
            body.style.paddingTop = 'var(--shine-select-body-padding)';
            body.style.paddingBottom = 'var(--shine-select-body-padding)';

            const fullHeight = body.scrollHeight;
            body.style.setProperty('--shine-select-target-height', `${fullHeight}px`);
            body.style.height = '0';

            body.classList.add('active');
            body.classList.add('expanding');

            header.classList.add('active');

            body.addEventListener('animationend', () => {
                body.classList.remove('expanding');
                body.style.height = 'auto';

                header.disabled = false;

                searchInput.focus();
            }, {
                once: true
            });
        }
    };

    const closeSelect = () => {
        if (body.classList.contains('active')) {
            header.disabled = true;

            body.classList.remove('active');
            body.classList.remove('expanding');

            header.classList.remove('active');

            const fullHeight = body.scrollHeight;
            body.style.height = `${fullHeight}px`;
            body.style.setProperty('--shine-select-target-height', `${fullHeight}px`);

            requestAnimationFrame(() => {
                body.classList.add('collapsing');
            });

            body.addEventListener('animationend', () => {
                body.classList.remove('collapsing');
                body.style.height = '0';
                body.style.paddingTop = '0';
                body.style.paddingBottom = '0';

                header.disabled = false;

                searchInput.value = '';

                filterItems();
            }, {
                once: true
            });
        }
    };

    header.addEventListener('click', (e) => {
        if (e.target.closest('.search-input, .remove-btn')) {
            return;
        }

        e.stopPropagation();

        const isActive = body.classList.contains('active');

        if (isActive) {
            closeSelect();
        } else {
            openSelect();
        }
    });

    items.forEach(item => {
        item.addEventListener('click', (e) => {
            e.stopPropagation();

            const value = item.dataset.value;
            const text = item.querySelector('span').textContent;

            if (mode === 'multiple') {
                if (selectedValues.includes(value)) {
                    selectedValues = selectedValues.filter(v => v !== value);
                    item.classList.remove('selected');
                } else {
                    selectedValues.push(value);
                    item.classList.add('selected');
                }

                updateHeader();
            } else {
                selectedValues = item.dataset.value;
                selectedText = text;
                selectedSpan.setAttribute('data-selected', value);

                items.forEach(i => i.classList.remove('selected'));
                item.classList.add('selected');

                updateHeader();
                closeSelect();
            }

            searchInput.value = '';

            filterItems();
        });
    });

    selectedSpan.addEventListener('click', (e) => {
        if (mode === 'multiple') {
            const removeBtn = e.target.closest('.remove-btn');
            if (removeBtn) {
                e.stopPropagation();

                const badge = removeBtn.parentElement;
                const value = badge.dataset.value;
                selectedValues = selectedValues.filter(v => v !== value);

                const item = Array.from(items).find(i => i.dataset.value === value);
                if (item) item.classList.remove('selected');

                updateHeader();

                searchInput.focus();
            }
        }
    });

    document.addEventListener('click', (e) => {
        if (!select.contains(e.target)) {
            closeSelect();
        }
    });

    if (!body.classList.contains('active')) {
        body.style.height = '0';
        body.style.paddingTop = '0';
        body.style.paddingBottom = '0';
    } else {
        header.classList.add('active');
    }

    if (mode === 'multiple') {
        selectedSpan.classList.add('selected-values');
    }

    updateHeader();
});