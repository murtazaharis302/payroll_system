<script>
    window.pendingBankAction = null;

    function checkBankSettingsAndProceed(callback) {
        var bankName = document.getElementById('bank_input_name').value.trim();
        var acctNum = document.getElementById('bank_input_acct').value.trim();

        if (!bankName || !acctNum) {
            var modalElement = document.getElementById('bankSettingsModal');
            if (modalElement) {
                var modal = new bootstrap.Modal(modalElement);
                modal.show();
                window.pendingBankAction = callback;
                return false;
            }
        }
        if (typeof callback === 'function') {
            callback();
        }
        return true;
    }

    function toggleAllCheckboxes(boxId, state) {
        var box = document.getElementById(boxId);
        if (box) {
            var cbs = box.querySelectorAll('input[type="checkbox"]');
            cbs.forEach(function(cb) { cb.checked = state; });
        }
    }

    function clearReportSearchInput() {
        var searchInput = document.getElementById('reportSearchInput');
        if (searchInput) {
            searchInput.value = '';
            searchInput.dispatchEvent(new Event('input'));
        }
    }

    function fuzzyMatch(pattern, haystack) {
        pattern = pattern.toLowerCase().trim();
        haystack = haystack.toLowerCase();

        if (!pattern) return true;
        if (haystack.indexOf(pattern) !== -1) return true;

        var pIdx = 0;
        var hIdx = 0;
        var pLen = pattern.length;
        var hLen = haystack.length;

        while (pIdx < pLen && hIdx < hLen) {
            if (pattern.charAt(pIdx) === haystack.charAt(hIdx)) {
                pIdx++;
            }
            hIdx++;
        }

        return pIdx === pLen;
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Populate "All Reports" pane on load by cloning all canonical report cards across categories
        var allGrid = document.getElementById('allReportsGridContainer');
        var canonicalCards = document.querySelectorAll('.tab-content .tab-pane:not(#pane-all) .report-card-item');
        var totalCanonicalCount = canonicalCards.length;

        if (allGrid && totalCanonicalCount > 0) {
            allGrid.innerHTML = '';
            canonicalCards.forEach(function(card) {
                var clone = card.cloneNode(true);

                // Update collapse target IDs inside clone so buttons expand the clone's body
                var collapseDiv = clone.querySelector('.collapse');
                var toggleBtns = clone.querySelectorAll('[data-bs-toggle="collapse"]');
                if (collapseDiv && collapseDiv.id) {
                    var oldId = collapseDiv.id;
                    var newId = oldId + '-all-clone';
                    collapseDiv.id = newId;
                    toggleBtns.forEach(function(b) {
                        b.setAttribute('data-bs-target', '#' + newId);
                    });
                }

                allGrid.appendChild(clone);
            });
            if (typeof window.initSearchableSelects === 'function') {
                window.initSearchableSelects(allGrid);
            }
        }

        // --- Client-Side Quick Search & Tab Memory ---
        var searchInput = document.getElementById('reportSearchInput');
        var searchCount = document.getElementById('reportSearchCount');
        var clearBtn = document.getElementById('clearReportSearch');
        var noFoundMsg = document.getElementById('noReportsFoundMessage');
        var tabLinks = document.querySelectorAll('#reportCategoryTabs button[data-bs-toggle="tab"]');

        var urlParams = new URLSearchParams(window.location.search);
        var initialTabId = urlParams.get('tab') || localStorage.getItem('active_report_tab') || 'payroll';
        var previousTabId = initialTabId !== 'all' ? initialTabId : 'payroll';
        var isSearching = false;

        if (searchCount) {
            searchCount.innerHTML = 'Showing all ' + totalCanonicalCount + ' report modules';
        }

        if (initialTabId) {
            var targetBtn = document.querySelector('#reportCategoryTabs button[data-tab-id="' + initialTabId + '"]');
            if (targetBtn) {
                var tabObj = new bootstrap.Tab(targetBtn);
                tabObj.show();
            }
        }

        // Handle Tab Change Events
        tabLinks.forEach(function(btn) {
            btn.addEventListener('shown.bs.tab', function(e) {
                var tabId = e.target.getAttribute('data-tab-id');
                if (tabId) {
                    localStorage.setItem('active_report_tab', tabId);
                    var url = new URL(window.location);
                    url.searchParams.set('tab', tabId);
                    window.history.replaceState({}, '', url);

                    if (!isSearching && tabId !== 'all') {
                        previousTabId = tabId;
                    }
                }
            });
        });

        // Filter function for search across all canonical report cards using fuzzy matching
        function filterReports() {
            var rawQuery = searchInput ? searchInput.value.toLowerCase().trim() : '';

            if (rawQuery === '') {
                if (isSearching) {
                    isSearching = false;
                    var restoreBtn = document.querySelector('#reportCategoryTabs button[data-tab-id="' + previousTabId + '"]');
                    if (restoreBtn) {
                        var tabObj = new bootstrap.Tab(restoreBtn);
                        tabObj.show();
                    }
                }

                var allClones = allGrid ? allGrid.querySelectorAll('.report-card-item') : [];
                allClones.forEach(function(card) { card.style.display = ''; });

                if (searchCount) searchCount.innerHTML = 'Showing all ' + totalCanonicalCount + ' report modules';
                if (clearBtn) clearBtn.classList.add('d-none');
                if (noFoundMsg) noFoundMsg.classList.add('d-none');
                return;
            }

            if (!isSearching) {
                var currentActiveBtn = document.querySelector('#reportCategoryTabs button.active');
                if (currentActiveBtn) {
                    var currentTabId = currentActiveBtn.getAttribute('data-tab-id');
                    if (currentTabId && currentTabId !== 'all') {
                        previousTabId = currentTabId;
                    }
                }
                isSearching = true;

                var allTabBtn = document.querySelector('#reportCategoryTabs button[data-tab-id="all"]');
                if (allTabBtn && !allTabBtn.classList.contains('active')) {
                    var startPos = searchInput ? searchInput.selectionStart : null;
                    var endPos = searchInput ? searchInput.selectionEnd : null;

                    var restoreFocus = function() {
                        if (searchInput) {
                            searchInput.focus({ preventScroll: true });
                            if (startPos !== null && endPos !== null && typeof searchInput.setSelectionRange === 'function') {
                                try {
                                    searchInput.setSelectionRange(startPos, endPos);
                                } catch (e) {}
                            }
                        }
                    };

                    var onSearchTabShown = function() {
                        allTabBtn.removeEventListener('shown.bs.tab', onSearchTabShown);
                        restoreFocus();
                    };
                    allTabBtn.addEventListener('shown.bs.tab', onSearchTabShown);

                    var tabObj = new bootstrap.Tab(allTabBtn);
                    tabObj.show();

                    requestAnimationFrame(function() {
                        restoreFocus();
                        setTimeout(restoreFocus, 50);
                    });
                }
            }

            if (clearBtn) clearBtn.classList.remove('d-none');
            var tokens = rawQuery.split(/\s+/).filter(Boolean);

            var cardsInGrid = allGrid ? allGrid.querySelectorAll('.report-card-item') : [];
            var visibleCount = 0;

            cardsInGrid.forEach(function(card) {
                var titleEl = card.querySelector('h5');
                var titleText = (titleEl ? titleEl.textContent : '').toLowerCase();

                var isMatch = tokens.every(function(token) {
                    return fuzzyMatch(token, titleText);
                });

                if (isMatch) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            if (visibleCount === 0) {
                if (noFoundMsg) noFoundMsg.classList.remove('d-none');
                if (searchCount) searchCount.innerHTML = '0 reports found';
            } else {
                if (noFoundMsg) noFoundMsg.classList.add('d-none');
                if (searchCount) searchCount.innerHTML = 'Found ' + visibleCount + ' matching report(s)';
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', filterReports);
        }

        // Bank Form Submit Interceptor
        var bankForm = document.getElementById('bankReportForm');
        if (bankForm) {
            bankForm.addEventListener('submit', function(e) {
                var bankName = document.getElementById('bank_input_name').value.trim();
                var acctNum = document.getElementById('bank_input_acct').value.trim();
                if (!bankName || !acctNum) {
                    e.preventDefault();
                    checkBankSettingsAndProceed(function() {
                        bankForm.submit();
                    });
                }
            });
        }

        // Inline Bank Settings Ajax Submit
        var inlineBankForm = document.getElementById('inlineBankSettingsForm');
        if (inlineBankForm) {
            inlineBankForm.addEventListener('submit', function(e) {
                e.preventDefault();
                var btn = document.getElementById('saveBankSettingsBtn');
                btn.disabled = true;

                var formData = new FormData(inlineBankForm);

                fetch("{{ panelRoute('reports.save-bank-settings') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    btn.disabled = false;
                    if (data.success) {
                        document.getElementById('bank_input_name').value = data.setting.bank_name || '';
                        document.getElementById('bank_input_acct').value = data.setting.account_number || '';
                        if (data.setting.corporate_signee_1) {
                            document.getElementById('bank_input_signatory').value = data.setting.corporate_signee_1;
                        }

                        var modalElement = document.getElementById('bankSettingsModal');
                        var modal = bootstrap.Modal.getInstance(modalElement);
                        if (!modal && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                            modal = new bootstrap.Modal(modalElement);
                        }
                        if (modal) modal.hide();

                        if (window.pendingBankAction && typeof window.pendingBankAction === 'function') {
                            var action = window.pendingBankAction;
                            window.pendingBankAction = null;
                            action();
                        }
                    } else {
                        var errDiv = document.getElementById('modalAlertError');
                        if (errDiv) {
                            errDiv.textContent = data.message || 'Failed to save bank settings.';
                            errDiv.classList.remove('d-none');
                        }
                    }
                })
                .catch(err => {
                    btn.disabled = false;
                    alert('Error saving bank settings: ' + err.message);
                });
            });
        }
    });
</script>
