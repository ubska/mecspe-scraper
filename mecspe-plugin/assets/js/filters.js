/* ============================================================
   MECSPE PRODOTTI — Filtri AJAX v1.1
   ============================================================ */
(function ($) {
    'use strict';

    var state = {
        s:       '',
        order:   'title-ASC',
        taxes:   {},   // { taxonomy_slug: [val, val] }
        paged:   1,
        maxPages: 1,
        loading: false,
    };

    var $wrap, $grid, $count, $loadMore, $pagination, $badge;
    var searchTimer;
    var STR = window.MecspeAjax ? MecspeAjax.strings : {};

    /* ── Init ── */
    $(document).ready(function () {
        $wrap       = $('#mecspe-wrap');
        if (!$wrap.length) return;

        $grid       = $('#mecspe-grid');
        $count      = $('#mecspe-count');
        $loadMore   = $('#mecspe-load-more');
        $pagination = $('#mecspe-pagination');
        $badge      = $('#mecspe-filter-badge');

        state.maxPages = parseInt($loadMore.data('max') || 1, 10);

        initSearch();
        initOrderby();
        initCheckboxes();
        initKmRange();
        initLoadMore();
        initSidebarToggle();
        initGroupToggles();
        syncChips();
        syncBadge();
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
                fetch(false);
            }, 400);
        });
    }

    /* ============================================================
       ORDINA
       ============================================================ */
    function initOrderby() {
        $('#mecspe-orderby').on('change', function () {
            state.order = $(this).val();
            state.paged = 1;
            fetch(false);
        });
    }

    /* ============================================================
       CHECKBOX
       ============================================================ */
    function initCheckboxes() {
        $(document).on('change', '.mecspe-filter-check', function () {
            rebuildTaxes();
            state.paged = 1;
            fetch(false);
            syncChips();
            syncBadge();
        });
    }

    function rebuildTaxes() {
        state.taxes = {};
        $('.mecspe-filter-check:checked').each(function () {
            var tax = $(this).data('taxonomy');
            if (!state.taxes[tax]) state.taxes[tax] = [];
            state.taxes[tax].push($(this).val());
        });
    }

    /* ============================================================
       LOAD MORE
       ============================================================ */
    function initLoadMore() {
        $(document).on('click', '#mecspe-load-more', function () {
            if (state.loading) return;
            state.paged++;
            fetch(true);
        });
    }

    /* ============================================================
       KM RANGE
       ============================================================ */
    function initKmRange() {
        var timer;
        $(document).on('input', '#mecspe-km-min, #mecspe-km-max', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                state.paged = 1;
                fetch(false);
                syncBadge();
            }, 500);
        });
    }

    /* ============================================================
       FETCH AJAX
       ============================================================ */
    function fetch(append) {
        if (state.loading) return;
        state.loading = true;
        $wrap.addClass('mecspe-loading');

        if (!append) {
            $grid.html(skeleton(6));
        } else {
            $loadMore.prop('disabled', true).text(STR.loading || 'Caricamento…');
        }

        var data = {
            action:        'mecspe_filter',
            nonce:         MecspeAjax.nonce,
            paged:         state.paged,
            mecspe_s:      state.s,
            mecspe_order:  state.order,
            mecspe_km_min: $('#mecspe-km-min').val() || '',
            mecspe_km_max: $('#mecspe-km-max').val() || '',
        };

        $.each(state.taxes, function (tax, vals) {
            data[tax] = vals;
        });

        $.post(MecspeAjax.ajaxurl, data)
            .done(function (res) {
                if (!res.success) return;
                var d = res.data;
                state.maxPages = d.max_pages;

                if (append) {
                    $grid.append(d.html);
                } else {
                    $grid.html(d.html);
                    $('html,body').animate({ scrollTop: $wrap.offset().top - 24 }, 280);
                }

                /* Contatore */
                var label = d.found === 1
                    ? '1 ' + (STR.found_singular || 'prodotto trovato')
                    : d.found + ' ' + (STR.found_plural || 'prodotti trovati');
                $count.text(label);

                /* Load more */
                if (state.paged >= state.maxPages) {
                    $pagination.hide();
                } else {
                    $pagination.show();
                    $loadMore.prop('disabled', false).text(STR.load_more || 'Carica altri');
                }
            })
            .fail(function () {
                if (!append) {
                    $grid.html('<div class="mecspe-no-results"><p>' + (STR.error || 'Errore nel caricamento.') + '</p></div>');
                }
            })
            .always(function () {
                state.loading = false;
                $wrap.removeClass('mecspe-loading');
            });
    }

    /* ── Skeleton ── */
    function skeleton(n) {
        var h = '';
        for (var i = 0; i < n; i++) {
            h += '<article class="mecspe-card">'
               + '<div style="height:180px;background:#eef0f4;border-bottom:1px solid #dde2ea"></div>'
               + '<div class="mecspe-card-body" style="gap:12px">'
               + '<div class="mp-skel" style="height:18px;width:70%"></div>'
               + '<div class="mp-skel" style="height:13px;width:45%"></div>'
               + '<div class="mp-skel" style="height:12px;width:90%"></div>'
               + '<div class="mp-skel" style="height:12px;width:60%"></div>'
               + '</div>'
               + '<div style="padding:12px 16px;background:#f2f4f7;border-top:1px solid #dde2ea">'
               + '<div class="mp-skel" style="height:34px;border-radius:8px"></div>'
               + '</div>'
               + '</article>';
        }
        return h;
    }

    /* ============================================================
       SIDEBAR TOGGLE (mobile)
       ============================================================ */
    function initSidebarToggle() {
        var $sidebar = $('#mecspe-sidebar');
        var $overlay = $('#mecspe-overlay');

        $('#mecspe-toggle-sidebar').on('click', function () {
            var open = $sidebar.hasClass('open');
            $sidebar.toggleClass('open', !open);
            $overlay.toggleClass('open', !open);
        });

        $overlay.on('click', function () {
            $sidebar.removeClass('open');
            $overlay.removeClass('open');
        });
    }

    /* ============================================================
       ACCORDION GRUPPI
       ============================================================ */
    function initGroupToggles() {
        /* Imposta max-height iniziale */
        $('.mecspe-filter-options').each(function () {
            $(this).css('max-height', this.scrollHeight + 'px');
        });

        $(document).on('click', '.mecspe-filter-group-toggle', function () {
            var $btn  = $(this);
            var $opts = $btn.closest('.mecspe-filter-group').find('.mecspe-filter-options');
            var open  = $btn.attr('aria-expanded') === 'true';

            $btn.attr('aria-expanded', !open);
            if (open) {
                $opts.css('max-height', $opts[0].scrollHeight + 'px');
                requestAnimationFrame(function () {
                    $opts.css('max-height', '0').addClass('collapsed');
                });
            } else {
                $opts.removeClass('collapsed').css('max-height', $opts[0].scrollHeight + 'px');
                setTimeout(function () { $opts.css('max-height', ''); }, 260);
            }
        });
    }

    /* ============================================================
       CHIPS ATTIVI
       ============================================================ */
    function syncChips() {
        var $c = $('#mecspe-active-filters').empty();

        $('.mecspe-filter-check:checked').each(function () {
            var tax   = $(this).data('taxonomy');
            var val   = $(this).val();
            var label = $(this).data('label') || val;

            $c.append(
                $('<span class="mecspe-chip"></span>')
                    .text(label + ' ')
                    .append(
                        $('<button class="mecspe-chip-remove" aria-label="Rimuovi">×</button>')
                            .data({ tax: tax, val: val })
                    )
            );
        });

        $c.off('click', '.mecspe-chip-remove').on('click', '.mecspe-chip-remove', function () {
            var tax = $(this).data('tax');
            var val = $(this).data('val');
            $('.mecspe-filter-check[data-taxonomy="' + tax + '"][value="' + val + '"]').prop('checked', false);
            rebuildTaxes();
            state.paged = 1;
            fetch(false);
            syncChips();
            syncBadge();
        });
    }

    /* ── Badge contatore filtri attivi (mobile) ── */
    function syncBadge() {
        var n = $('.mecspe-filter-check:checked').length;
        if (n > 0) {
            $badge.text(n).show();
        } else {
            $badge.hide();
        }
    }

    /* ── Reset ── */
    $(document).on('click', '#mecspe-reset', function () {
        $('.mecspe-filter-check').prop('checked', false);
        $('#mecspe-search').val('');
        $('#mecspe-km-min, #mecspe-km-max').val('');
        state.s     = '';
        state.taxes = {};
        state.paged = 1;
        fetch(false);
        syncChips();
        syncBadge();
    });

}(jQuery));
