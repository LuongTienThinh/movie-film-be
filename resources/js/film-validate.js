/* film-validate.js
   Client-side required-field validation for film create/edit forms.
   Looks for labels with class 'require' inside .form-group and ensures
   the corresponding input or shine-select has a value before allowing submit.
*/
(function () {
    function hasValueInput(input) {
        if (!input) return false;
        if (input.disabled) return false;
        var val = input.value;
        return val != null && String(val).trim() !== '';
    }

    function findHiddenValues(form, name) {
        // exact match
        var inputs = form.querySelectorAll('input[name="' + name + '"]');
        if (inputs.length) return Array.from(inputs).map(function(i){ return i.value; }).filter(Boolean);
        // try array style (if name ends with [])
        if (!name.endsWith('[]')) {
            var alt = name + '[]';
            inputs = form.querySelectorAll('input[name="' + alt + '"]');
            if (inputs.length) return Array.from(inputs).map(function(i){ return i.value; }).filter(Boolean);
        }
        return [];
    }

    document.addEventListener('DOMContentLoaded', function () {
        // select all forms on the page (form may wrap .film-detail or contain it)
        var forms = document.querySelectorAll('form');
        forms.forEach(function (form) {
            form.addEventListener('submit', function (e) {
                    // clear previous invalid markers
                    form.querySelectorAll('.is-invalid').forEach(function(i){ i.classList.remove('is-invalid'); });
                    form.querySelectorAll('.shine-select.invalid').forEach(function(s){ s.classList.remove('invalid'); });

                    var groups = form.querySelectorAll('.form-group');
                    var firstInvalid = null;
                    groups.forEach(function (g) {
                        if (firstInvalid) return;
                        var label = g.querySelector('label.require');
                        if (!label) return;
                        // prefer named inputs
                        var input = g.querySelector('input[name]:not([type=hidden]), select[name], textarea[name]');
                        if (input) {
                            var valid = false;
                            if (hasValueInput(input)) {
                                valid = true;
                            } else if (input.disabled) {
                                // check for a hidden input with the same name (e.g., slug display)
                                try {
                                    var hiddenMirror = form.querySelector('input[type="hidden"][name="' + input.name + '"]');
                                    if (hiddenMirror && String(hiddenMirror.value).trim() !== '') valid = true;
                                } catch (err) { /* ignore */ }
                            }

                            if (!valid) {
                                firstInvalid = input;
                                input.classList.add('is-invalid');
                            }
                            return;
                        }
                        // check shine-select
                        var shine = g.querySelector('.shine-select');
                        if (shine) {
                            var name = shine.getAttribute('data-name');
                            if (!name) return;
                            var values = findHiddenValues(form, name);
                            if (!values.length) {
                                // if no hidden inputs yet, also check if selected-values has badges
                                var badges = shine.querySelectorAll('.selected-values .badge');
                                if (!badges.length) {
                                    firstInvalid = shine;
                                    shine.classList.add('invalid');
                                    var header = shine.querySelector('.shine-select-header');
                                    if (header) header.classList.add('invalid');
                                }
                            }
                        }
                    });

                    if (firstInvalid) {
                        e.preventDefault();
                        try {
                            var focusEl = firstInvalid.querySelector ? (firstInvalid.querySelector('.search-input') || firstInvalid) : firstInvalid;
                            focusEl.scrollIntoView({behavior: 'smooth', block: 'center'});
                            try { (focusEl.focus && focusEl.focus()); } catch (err) {}
                        } catch (err) {}
                        return false;
                    }
                });
        });

        // image preview handlers for file inputs
        function handleFileInputChange(input) {
            var container = input.closest('.file-uploader');
            if (!container) return;
            var preview = container.querySelector('.file-preview');
            var placeholder = container.querySelector('.file-placeholder');
            var file = input.files && input.files[0];
            if (file) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    if (!preview) {
                        preview = document.createElement('div');
                        preview.className = 'file-preview';
                        container.appendChild(preview);
                    }
                    preview.style.display = 'block';
                    // set preview image
                    var altText = (window.i18n && window.i18n.preview_alt) ? window.i18n.preview_alt : 'preview';
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="' + altText + '">';
                    if (placeholder) placeholder.style.display = 'none';
                    // ensure there's a remove overlay button
                    var removeBtn = preview.querySelector('.preview-remove');
                    if (!removeBtn) {
                        removeBtn = document.createElement('button');
                        removeBtn.type = 'button';
                        removeBtn.className = 'preview-remove';
                        removeBtn.innerHTML = `
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" fill="currentColor">
                                <path d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z"/>
                            </svg>`;
                        preview.appendChild(removeBtn);
                    }
                    // click handler for remove overlay
                    removeBtn.onclick = function (ev) {
                        ev.stopPropagation();
                        ev.preventDefault();
                        // set remove flag if exists
                        var name = container.classList.contains('poster-uploader') ? 'remove_poster' : 'remove_thumbnail';
                        var hidden = container.querySelector('input[type="hidden"][name="' + name + '"]');
                        if (hidden) hidden.value = '1';
                        // clear preview and file input
                        preview.style.display = 'none';
                        preview.innerHTML = '';
                        if (placeholder) placeholder.style.display = '';
                        var fi = container.querySelector('.file-input');
                        if (fi) fi.value = '';
                    };

                    // make preview image clickable to open file input
                    setTimeout(function(){
                        var img = container.querySelector('.file-preview img');
                        var fi = container.querySelector('.file-input');
                        if (img && fi) {
                            img.style.cursor = 'pointer';
                            img.addEventListener('click', function (ev) { ev.stopPropagation(); fi.click(); });
                        }
                    }, 0);
                };
                reader.readAsDataURL(file);
            } else {
                if (preview) {
                    preview.style.display = 'none';
                    preview.innerHTML = '';
                }
                if (placeholder) placeholder.style.display = '';
                // remove any click handlers and remove overlay
                var img = container.querySelector('.file-preview img');
                if (img) {
                    try { img.style.cursor = ''; img.replaceWith(img.cloneNode(true)); } catch (err) {}
                }
                var removeBtn = container.querySelector('.file-preview .preview-remove');
                if (removeBtn) try { removeBtn.remove(); } catch (err) {}
            }
        }

        document.querySelectorAll('.file-input').forEach(function (fi) {
            fi.addEventListener('change', function () { handleFileInputChange(fi); });
            // initialize preview if file input already has a file selected (unlikely)
            if (fi.files && fi.files.length) handleFileInputChange(fi);
        });

        // make existing preview images clickable and add overlay remove if needed
        document.querySelectorAll('.file-uploader').forEach(function (container) {
            var preview = container.querySelector('.file-preview');
            var previewImg = preview ? preview.querySelector('img') : null;
            var fi = container.querySelector('.file-input');
            if (previewImg && fi) {
                previewImg.style.cursor = 'pointer';
                previewImg.addEventListener('click', function (ev) { ev.stopPropagation(); fi.click(); });
                // add overlay remove button for edit previews
                var removeBtn = preview.querySelector('.preview-remove');
                if (!removeBtn) {
                    removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'preview-remove';
                    removeBtn.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" fill="currentColor">
                            <path d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z"/>
                        </svg>`;
                    preview.appendChild(removeBtn);
                }
                removeBtn.onclick = function (ev) {
                    ev.stopPropagation(); ev.preventDefault();
                    var name = container.classList.contains('poster-uploader') ? 'remove_poster' : 'remove_thumbnail';
                    var hidden = container.querySelector('input[type="hidden"][name="' + name + '"]');
                    if (hidden) hidden.value = '1';
                    preview.style.display = 'none';
                    preview.innerHTML = '';
                    var placeholder = container.querySelector('.file-placeholder');
                    if (placeholder) placeholder.style.display = '';
                    var fi = container.querySelector('.file-input');
                    if (fi) fi.value = '';
                };
            }
        });

        // when choosing a new file, ensure remove flag is cleared
        document.querySelectorAll('.file-input').forEach(function (fi) {
            fi.addEventListener('change', function () {
                var container = fi.closest('.file-uploader');
                if (!container) return;
                var hiddenPoster = container.querySelector('input[type="hidden"][name="remove_poster"]');
                var hiddenThumb = container.querySelector('input[type="hidden"][name="remove_thumbnail"]');
                if (hiddenPoster) hiddenPoster.value = '0';
                if (hiddenThumb) hiddenThumb.value = '0';
            });
        });

        // slug generation: sync slug (disabled display) and hidden input from name
        function slugify(text) {
            return String(text)
                .toLowerCase()
                .normalize('NFD')
                .replace(/\p{Diacritic}/gu, '')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        }

        forms.forEach(function (form) {
            var nameInput = form.querySelector('input[name="name"]');
            var slugDisplay = form.querySelector('input[name="slug"]');
            var hiddenSlug = form.querySelector('input[type="hidden"][name="slug"]');
            if (!hiddenSlug) {
                hiddenSlug = document.createElement('input');
                hiddenSlug.type = 'hidden';
                hiddenSlug.name = 'slug';
                form.appendChild(hiddenSlug);
            }

            function updateSlug() {
                if (!nameInput) return;
                var s = slugify(nameInput.value || '');
                if (slugDisplay) slugDisplay.value = s;
                hiddenSlug.value = s;
            }

            if (nameInput) {
                nameInput.addEventListener('input', updateSlug);
                nameInput.addEventListener('blur', updateSlug);
                // initialize on load if empty
                if ((slugDisplay && !slugDisplay.value) || !hiddenSlug.value) updateSlug();
            }
        
        // genre chips: toggle selection and keep hidden inputs in sync
        document.querySelectorAll('.genre-list').forEach(function(list){
            list.querySelectorAll('.genre-item').forEach(function(item){
                item.addEventListener('click', function () {
                    var id = item.getAttribute('data-id');
                    if (!id) return;
                    var form = item.closest('form');
                    var name = (list.getAttribute('data-name') || 'genres[]');
                    var existing = form.querySelectorAll('input[type="hidden"][name="' + name + '"]');
                    if (item.classList.contains('selected')) {
                        // deselect
                        item.classList.remove('selected');
                        // remove hidden inputs with this value
                        Array.from(existing).forEach(function(h){ if (h.value == id) h.remove(); });
                    } else {
                        // select
                        item.classList.add('selected');
                        // append hidden input
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = name;
                        input.value = id;
                        form.appendChild(input);
                    }
                });
            });
        });
        });

        // episodes handlers: add/edit/delete rows dynamically
        document.querySelectorAll('.episodes-list').forEach(function(list){
            // inline SVGs for edit/delete buttons (copied from blade includes)
            var editSvg = `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M13.26 3.59997L5.04997 12.29C4.73997 12.62 4.43997 13.27 4.37997 13.72L4.00997 16.96C3.87997 18.13 4.71997 18.93 5.87997 18.73L9.09997 18.18C9.54997 18.1 10.18 17.77 10.49 17.43L18.7 8.73997C20.12 7.23997 20.76 5.52997 18.55 3.43997C16.35 1.36997 14.68 2.09997 13.26 3.59997Z" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/><path d="M11.89 5.05005C12.32 7.81005 14.56 9.92005 17.34 10.2" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/><path d="M3 22H21" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
            var deleteSvg = `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M21 5.97998C17.67 5.64998 14.32 5.47998 10.98 5.47998C9 5.47998 7.02 5.57998 5.04 5.77998L3 5.97998" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M8.5 4.97L8.72 3.66C8.88 2.71 9 2 10.69 2H13.31C15 2 15.13 2.75 15.28 3.67L15.5 4.97" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M18.85 9.14001L18.2 19.21C18.09 20.78 18 22 15.21 22H8.79002C6.00002 22 5.91002 20.78 5.80002 19.21L5.15002 9.14001" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M10.33 16.5H13.66" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M9.5 12.5H14.5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>`;

            var tickSvg = `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M12 22C17.5 22 22 17.5 22 12C22 6.5 17.5 2 12 2C6.5 2 2 6.5 2 12C2 17.5 6.5 22 12 22Z" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M7.75 12L10.58 14.83L16.25 9.17004" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
            function makeAddHtml(){ return '<button type="button" class="btn btn-sm btn-add">' + tickSvg + '</button>'; }
            function makeEditDeleteHtml(){ return '<button type="button" class="btn btn-sm btn-edit">' + editSvg + '</button>' +
                                                  '<button type="button" class="btn btn-sm btn-delete">' + deleteSvg + '</button>'; }

            function refreshButtons(){
                var rows = list.querySelectorAll('.episode-row');
                Array.from(rows).forEach(function(r, idx){
                    var actions = r.querySelector('.episode-actions');
                    if (!actions) return;
                    if (idx === rows.length - 1) {
                        actions.innerHTML = makeAddHtml();
                    } else {
                        actions.innerHTML = makeEditDeleteHtml();
                    }
                });
            }

            // initial refresh to ensure correct buttons
            refreshButtons();

            list.addEventListener('click', function(ev){
                var clicked = ev.target.closest('button, .btn-add, .btn-edit, .btn-delete');
                if (!clicked) return;
                var row = clicked.closest('.episode-row');

                if (clicked.classList.contains('btn-add')) {
                    ev.preventDefault();
                    // convert current last row (row) to edit+delete, then append a fresh empty row with add
                    if (row) {
                        var actions = row.querySelector('.episode-actions');
                        if (actions) actions.innerHTML = makeEditDeleteHtml();
                    }

                    // build new row element
                    var newRow = document.createElement('div');
                    newRow.className = 'episode-row d-flex gap-2 mb-2 align-items-center';
                    newRow.innerHTML = '<input type="text" name="episode_name[]" class="episode-name text-center" style="width:120px; text-align:center;" value="">' +
                                       '<input type="text" name="episode_link[]" class="episode-link" value="" placeholder="Link embedded">' +
                                       '<div class="episode-actions">' + makeAddHtml() + '</div>';
                    list.appendChild(newRow);
                    var newName = newRow.querySelector('.episode-name'); if (newName) try { newName.focus(); } catch (err) {}
                    refreshButtons();
                    return;
                }

                if (clicked.classList.contains('btn-delete')) {
                    ev.preventDefault();
                    var rows = list.querySelectorAll('.episode-row');
                    if (rows.length <= 1) {
                        if (row) {
                            var ni = row.querySelector('.episode-name'); if (ni) ni.value = '';
                            var li = row.querySelector('.episode-link'); if (li) li.value = '';
                        }
                        refreshButtons();
                        return;
                    }
                    if (row) row.remove();
                    refreshButtons();
                    return;
                }

                if (clicked.classList.contains('btn-edit')) {
                    ev.preventDefault();
                    if (row) {
                        var link = row.querySelector('.episode-link');
                        if (link) try { link.focus(); } catch (err) {}
                    }
                    return;
                }
            });
        });

    });
})();
