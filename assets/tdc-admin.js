(function () {
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.tdcSettings === 'undefined' || !window.tdcSettings.maxDepthByTax) {
            return;
        }



        // Settings page: adjust level label inputs when depth changes
        document.querySelectorAll('input[name$="[depth]"]').forEach(function (depthInput) {
            // panels are now in .tdc-tab-panel; fall back to .tdc-tax-card for legacy
            var wrap = depthInput.closest('.tdc-tab-panel') || depthInput.closest('.tdc-tax-card');
            if (!wrap) return;

            var labelsContainer = wrap.querySelector('.tdc-label-inputs');
            if (!labelsContainer) return;

            function renderLabels() {
                var depth = parseInt(depthInput.value || '0', 10);
                var levels = Math.max(1, depth + 1);
                var existing = Array.prototype.slice
                    .call(labelsContainer.querySelectorAll('input'))
                    .map(function (i) { return i.value; });

                labelsContainer.innerHTML = '';

                var tax = wrap.querySelector('.tdc-level-labels').getAttribute('data-tax');

                for (var i = 0; i < levels; i++) {
                    var val = existing[i] || '';
                    var div = document.createElement('div');
                    div.className = 'tdc-label-row';
                    div.innerHTML =
                        '<label>Level ' + i + ' name: ' +
                        '<input type="text" ' +
                        'name="tdc_settings[' + tax + '][labels][]" ' +
                        'value="' + val.replace(/"/g, '&quot;') + '" ' +
                        'placeholder="Optional label for this level" />' +
                        '</label>';
                    labelsContainer.appendChild(div);
                }
                // update preview
                var preview = wrap.querySelector('.tdc-preview');
                if (preview) {
                    var entered = Array.prototype.slice.call(labelsContainer.querySelectorAll('input')).map(function (i, idx) { return i.value || ('Level ' + idx); });
                    preview.textContent = 'Preview: ' + entered.join(' › ');
                }
            }
            depthInput.addEventListener('change', renderLabels);
            depthInput.addEventListener('input', renderLabels);
            // initial render
            renderLabels();
        });

        // Tab switching for settings page: show/hide panels and trigger preview render
        document.querySelectorAll('.tdc-tab').forEach(function (tab) {
            tab.addEventListener('click', function (e) {
                // prevent button from submitting the settings form
                if (e && e.preventDefault) e.preventDefault();

                var tax = tab.getAttribute('data-tax') || (tab.closest && tab.closest('li') && tab.closest('li').getAttribute('data-tax'));
                if (!tax) return;
                document.querySelectorAll('.tdc-tab').forEach(function (t) { t.classList.remove('nav-tab-active'); });
                document.querySelectorAll('.tdc-tab-panel').forEach(function (p) { p.classList.remove('nav-tab-active'); });
                tab.classList.add('nav-tab-active');
                var panel = document.querySelector('#tdc-tab-' + tax);
                if (panel) {
                    panel.classList.add('nav-tab-active');
                    var depthInput = panel.querySelector('input[name$="[depth]"]');
                    if (depthInput) {
                        depthInput.dispatchEvent(new Event('input'));
                    }
                }
            });

            tab.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    // call click handler — it already prevents default
                    tab.click();
                }
            });
        });

        // Disable checklist inputs deeper than allowed
        function applyChecklistDepth(tax, maxDepth) {
            var selector = 'input[name="tax_input[' + tax + '][]"]';
            document.querySelectorAll(selector).forEach(function (input) {
                var li = input.closest('li');
                if (!li) return;

                var depth = parseInt(li.getAttribute('data-depth') || '0', 10);
                if (depth > maxDepth) {
                    input.disabled = true;
                    input.title = 'Term deeper than allowed depth';
                    li.classList.add('tdc-depth-disabled');
                } else {
                    input.disabled = false;
                    input.removeAttribute('title');
                    li.classList.remove('tdc-depth-disabled');
                }
            });
        }

        Object.keys(window.tdcSettings.maxDepthByTax).forEach(function (tax) {
            applyChecklistDepth(tax, window.tdcSettings.maxDepthByTax[tax]);
        });

        // Remove parent options that exceed max depth
        function applyParentSelectDepth() {
            var current = window.tdcSettings.currentTax;
            if (!current) return;

            var depths = (window.tdcSettings.termDepths || {})[current] || {};
            var max = window.tdcSettings.maxDepthByTax[current] || 0;
            if (max <= 0) return;

            document.querySelectorAll('select[name="parent"]').forEach(function (select) {
                Array.prototype.slice.call(select.options).forEach(function (opt) {
                    var id = parseInt(opt.value, 10);
                    if (isNaN(id)) return;

                    var depth = depths[id] !== undefined
                        ? depths[id]
                        : parseInt(opt.getAttribute('data-depth') || '0', 10);

                    if (depth >= max) {
                        opt.remove();
                    }
                });
            });
        }

        applyParentSelectDepth();

        // Replace Level cell text with configured labels
        function applyLevelLabels() {
            var current = window.tdcSettings.currentTax;
            if (!current || !window.tdcSettings.labels || !window.tdcSettings.labels[current]) return;

            var labels = window.tdcSettings.labels[current];

            // Iterate rows instead of relying on a separate Level column
            document.querySelectorAll('table.wp-list-table tbody tr').forEach(function (tr) {
                if (!tr || !tr.classList) return;

                var match = Array.prototype.slice
                    .call(tr.classList)
                    .map(function (c) { return c.match(/^level-(\d+)$/); })
                    .filter(Boolean)[0];

                var depth = match ? parseInt(match[1], 10) : 0;
                var label = labels[depth];
                var showFlag = (window.tdcSettings.showLabel && window.tdcSettings.showLabel[current]) ? true : false;

                // remove any existing badge first
                var nameCell = tr.querySelector('td.name.column-name');
                if (nameCell) {
                    var existing = nameCell.querySelector('.tdc-level-badge');
                    if (existing) existing.remove();
                }

                // Only add badge if setting enabled and label exists
                if (showFlag && label && nameCell) {
                    var badge = document.createElement('span');
                    badge.className = 'tdc-level-badge';
                    badge.textContent = label;
                    var strong = nameCell.querySelector('strong');
                    (strong || nameCell).appendChild(badge);
                }
            });
        }

        applyLevelLabels();

        // Hide header and cells for configured hidden columns (fallback if CSS didn't apply)
        function applyHideColumns() {
            var current = window.tdcSettings.currentTax;
            if (!current || !window.tdcSettings.hideColumns || !window.tdcSettings.hideColumns[current]) return;
            var flags = window.tdcSettings.hideColumns[current];
            if (flags.description) {
                document.querySelectorAll('.edit-tags-php thead th.column-description, .edit-tags-php thead th#description, .edit-tags-php tfoot th.column-description, .edit-tags-php tfoot th#description, .edit-tags-php td.column-description').forEach(function (el) { el.style.display = 'none'; });
            }
            if (flags.slug) {
                document.querySelectorAll('.edit-tags-php thead th.column-slug, .edit-tags-php thead th#slug, .edit-tags-php tfoot th.column-slug, .edit-tags-php tfoot th#slug, .edit-tags-php td.column-slug').forEach(function (el) { el.style.display = 'none'; });
            }
            if (flags.count) {
                document.querySelectorAll('.edit-tags-php thead th.column-posts, .edit-tags-php thead th#posts, .edit-tags-php tfoot th.column-posts, .edit-tags-php tfoot th#posts, .edit-tags-php td.column-posts').forEach(function (el) { el.style.display = 'none'; });
            }
        }

        applyHideColumns();

        // Reapply labels after quick-edit interactions or when inline rows change
        document.body.addEventListener('click', function (e) {
            if (e.target && (e.target.matches('.editinline') || e.target.closest('.editinline'))) {
                setTimeout(function () {
                    applyLevelLabels();
                    applyHideColumns();
                }, 300);
            }
        });

        // Observe AJAX-added nodes
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                m.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) return;

                    if (node.matches && node.matches('select[name="parent"]')) {
                        applyParentSelectDepth();
                    }

                    if (node.querySelector && node.querySelector('select[name="parent"]')) {
                        applyParentSelectDepth();
                    }

                    Object.keys(window.tdcSettings.maxDepthByTax).forEach(function (tax) {
                        var selector = 'input[name="tax_input[' + tax + '][]"]';
                        if (
                            (node.matches && node.matches(selector)) ||
                            (node.querySelector && node.querySelector(selector))
                        ) {
                            applyChecklistDepth(tax, window.tdcSettings.maxDepthByTax[tax]);
                        }
                    });

                    if (node.querySelector && (node.querySelectorAll('tr.level-0, tr.level-1, tr.level-2, tr.level-3, tr.level-4, tr.level-5').length)) {
                        applyLevelLabels();
                    }
                });
            });
        });

        observer.observe(document.body, { childList: true, subtree: true });
    });
})();
