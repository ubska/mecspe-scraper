/* ============================================================
   MECSPE ESPOSITORI — Filtri AJAX
   ============================================================ */
(function ($) {
    'use strict';

    /* ── Stato ── */
    var state = {
        s:               '',
        order:           'title-ASC',
        mecspe_settore:  [],
        mecspe_padiglione: [],
        paged:           1,
        maxPages:        1,
        loading:         false,
    };

    var $wrap, $grid, $count, $loadMore, $pagination;
    var searchTimer;

    /* ============================================================
       INIT
       ============================================================ */
    $(document).ready(function () {
        $wrap       = $('#mecspe-wrap');
        $grid       = $('#mecspe-grid');
        $count      = $('#mecspe-count');
        $loadMore   = $('#mecspe-load-more');
        $pagination = $('#mecspe-pagination');

        if (!$wrap.length) return;

        /* Leggi stato iniziale dai data-max */
        state.maxPages = parseInt($loadMore.data('max') || 1, 10);

        initSearch();
        initOrderby();
        initCheckboxes();
        initLoadMore();
        initSidebarToggle();
        initGroupToggles();
        initActiveChips();
    });

    /* ============================================================
       SEARCH
       ============================================================ */
    function initSearch() {
        $('#mecspe-search').on('input', function () {
            clearTimeout(searchTimer);
            var val = $(this).val();
            searchTimer = setTimeout(function () {
                state.s     = val;
                state.paged = 1;
                fetchResults(false);
            }, 420);
        });
    }

    /* ============================================================
       ORDINA
       ============================================================ */
    function initOrderby() {
        $('#mecspe-orderby').on('change', function () {
            state.order = $(this).val();
            state.paged = 1;
            fetchResults(false);
        });
    }

    /* ============================================================
       CHECKBOX FILTRI
       ============================================================ */
    function initCheckboxes() {
        $(document).on('change', '.mecspe-filter-check', function () {
            rebuildTaxFilters();
            state.paged = 1;
            fetchResults(false);
            updateActiveChips();
        });
    }

    function rebuildTaxFilters() {
        state.mecspe_settore    = [];
        state.mecspe_padiglione = [];
        $('.mecspe-filter-check:checked').each(function () {
            var tax = $(this).data('taxonomy');
            if (tax === 'mecspe_settore')    state.mecspe_settore.push($(this).val());
            if (tax === 'mecspe_padiglione') state.mecspe_padiglione.push($(this).val());
        });
    }

    /* ============================================================
       LOAD MORE
       ============================================================ */
    function initLoadMore() {
        $(document).on('click', '#mecspe-load-more', function () {
            if (state.loading) return;
            state.paged++;
            fetchResults(true);
        });
    }

    /* ============================================================
       FETCH
       ============================================================ */
    function fetchResults(append) {
        if (state.loading) return;
        state.loading = true;
        $wrap.addClass('mecspe-loading');

        if (!append) {
            $grid.html(skeletonHTML(6));
        } else {
            $loadMore.prop('disabled', true).text('Caricamento…');
        }

        var data = {
            action:  'mecspe_filter',
            nonce:   MecspeAjax.nonce,
            paged:   state.paged,
            mecspe_s: state.s,
            mecspe_order: state.order,
        };

        if (state.mecspe_settore.length)    data['mecspe_settore']    = state.mecspe_settore;
        if (state.mecspe_padiglione.length) data['mecspe_padiglione'] = state.mecspe_padiglione;

        $.post(MecspeAjax.ajaxurl, data)
            .done(function (res) {
                if (!res.success) return;

                var d = res.data;
                state.maxPages = d.max_pages;

                if (append) {
                    $grid.append(d.html);
                } else {
                    $grid.html(d.html);
                    /* scroll morbido verso la griglia */
                    $('html,body').animate({ scrollTop: $wrap.offset().top - 30 }, 300);
                }

                /* Aggiorna contatore */
                $count.text(d.found + ' espositore' + (d.found !== 1 ? 'i' : ''));

                /* Gestisci "carica altri" */
                if (state.paged >= state.maxPages) {
                    $pagination.hide();
                } else {
                    $pagination.show();
                    $loadMore
                        .data('page', state.paged)
                        .data('max',  state.maxPages)
                        .prop('disabled', false)
                        .text('Carica altri');
                }
            })
            .fail(function () {
                if (!append) $grid.html('<div class="mecspe-no-results"><p>Errore nel caricamento. Riprova.</p></div>');
            })
            .always(function () {
                state.loading = false;
                $wrap.removeClass('mecspe-loading');
            });
    }

    /* ============================================================
       SKELETON LOADER
       ============================================================ */
    function skeletonHTML(n) {
        var cards = '';
        for (var i = 0; i < n; i++) {
            cards += '<article class="mecspe-card" style="min-height:280px">' +
                '<div class="mecspe-skeleton" style="height:160px;border-radius:0"></div>' +
                '<div class="mecspe-card-body" style="gap:12px">' +
                '<div class="mecspe-skeleton" style="height:18px;border-radius:4px;width:75%"></div>' +
                '<div class="mecspe-skeleton" style="height:14px;border-radius:4px;width:50%"></div>' +
                '<div class="mecspe-skeleton" style="height:12px;border-radius:4px;width:90%"></div>' +
                '<div class="mecspe-skeleton" style="height:12px;border-radius:4px;width:65%"></div>' +
                '</div></article>';
        }
        return cards;
    }

    /* ============================================================
       SIDEBAR TOGGLE (mobile)
       ============================================================ */
    function initSidebarToggle() {
        var $sidebar = $('#mecspe-sidebar');
        var $overlay = $('<div class="mecspe-overlay"></div>');
        $('body').append($overlay);

        $('#mecspe-toggle-sidebar').on('click', function () {
            $sidebar.toggleClass('open');
            $overlay.toggle($sidebar.hasClass('open'));
        });

        $overlay.on('click', function () {
            $sidebar.removeClass('open');
            $overlay.hide();
        });
    }

    /* ============================================================
       COLLASSA/ESPANDI GRUPPI FILTRO
       ============================================================ */
    function initGroupToggles() {
        $(document).on('click', '.mecspe-filter-group-toggle', function () {
            var $btn     = $(this);
            var $opts    = $btn.closest('.mecspe-filter-group').find('.mecspe-filter-options');
            var expanded = $btn.attr('aria-expanded') === 'true';

            $btn.attr('aria-expanded', !expanded);
            if (expanded) {
                $opts.css('max-height', $opts[0].scrollHeight + 'px');
                // forza reflow
                $opts[0].offsetHeight; // eslint-disable-line no-unused-expressions
                $opts.css('max-height', '0').addClass('collapsed');
            } else {
                $opts.removeClass('collapsed').css('max-height', $opts[0].scrollHeight + 'px');
                setTimeout(function () { $opts.css('max-height', ''); }, 300);
            }
        });

        /* Imposta altezze iniziali */
        $('.mecspe-filter-options').each(function () {
            $(this).css('max-height', $(this)[0].scrollHeight + 'px');
        });
    }

    /* ============================================================
       CHIPS FILTRI ATTIVI
       ============================================================ */
    function initActiveChips() {
        /* Contenitore chips */
        var $chipsWrap = $('<div class="mecspe-active-filters" id="mecspe-active-filters"></div>');
        $('#mecspe-main').prepend($chipsWrap);
        updateActiveChips();

        /* Reset completo */
        $('#mecspe-reset').on('click', function () {
            $('.mecspe-filter-check').prop('checked', false);
            rebuildTaxFilters();
            state.paged = 1;
            fetchResults(false);
            updateActiveChips();
        });
    }

    function updateActiveChips() {
        var $chipsWrap = $('#mecspe-active-filters');
        $chipsWrap.empty();

        $('.mecspe-filter-check:checked').each(function () {
            var $cb   = $(this);
            var label = $cb.closest('.mecspe-checkbox-label').clone();
            label.find('input, .mecspe-checkbox-custom, .mecspe-term-count').remove();
            var name  = $.trim(label.text());
            var tax   = $cb.data('taxonomy');
            var val   = $cb.val();

            var $chip = $(
                '<span class="mecspe-chip">' + escapeHtml(name) +
                '<button class="mecspe-chip-remove" data-tax="' + escapeHtml(tax) +
                '" data-val="' + escapeHtml(val) + '" aria-label="Rimuovi filtro">×</button></span>'
            );
            $chipsWrap.append($chip);
        });

        /* Rimuovi singolo chip */
        $chipsWrap.off('click', '.mecspe-chip-remove').on('click', '.mecspe-chip-remove', function () {
            var tax = $(this).data('tax');
            var val = $(this).data('val');
            $('.mecspe-filter-check[data-taxonomy="' + tax + '"][value="' + val + '"]').prop('checked', false);
            rebuildTaxFilters();
            state.paged = 1;
            fetchResults(false);
            updateActiveChips();
        });
    }

    /* ============================================================
       UTILITY
       ============================================================ */
    function escapeHtml(str) {
        return $('<span>').text(str).html();
    }

}(jQuery));
