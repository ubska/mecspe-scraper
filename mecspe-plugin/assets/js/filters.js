/* ============================================================
   MECSPE PRODOTTI v2 — Filtri AJAX
   ============================================================ */
(function ($) {
    'use strict';

    var state = { order: 'title-ASC', taxes: {}, paged: 1, maxPages: 1, loading: false };
    var $wrap, $list, $count, $loadMore, $pagination;

    $(document).ready(function () {
        $wrap       = $('#mecspe-wrap');
        if (!$wrap.length) return;
        $list       = $('#mecspe-grid');
        $count      = $('#mecspe-count');
        $loadMore   = $('#mecspe-load-more');
        $pagination = $('#mecspe-pagination');
        state.maxPages = parseInt($loadMore.data('max') || 1, 10);

        initAccordion();
        initOrderby();
        initCheckboxes();
        initKmRange();
        initLoadMore();
        initDropdownSearch();
        initReset();
    });

    /* ── Accordion sidebar ── */
    function initAccordion() {
        $(document).on('click', '.mecspe-acc-toggle', function () {
            var $toggle = $(this);
            var $body   = $toggle.next('.mecspe-acc-body');
            var open    = $toggle.hasClass('open');
            $toggle.toggleClass('open', !open);
            $body.slideToggle(180);
            $toggle.attr('aria-expanded', !open);
        });
    }

    /* ── Ordinamento ── */
    function initOrderby() {
        $('#mecspe-orderby').on('change', function () {
            state.order = $(this).val();
            state.paged = 1;
            fetch(false);
        });
    }

    /* ── Checkbox sidebar ── */
    function initCheckboxes() {
        $(document).on('change', '.mecspe-filter-check', function () {
            rebuildTaxes();
            state.paged = 1;
            fetch(false);
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

    /* ── KM range ── */
    function initKmRange() {
        var timer;
        $(document).on('input', '#mecspe-km-min, #mecspe-km-max', function () {
            clearTimeout(timer);
            timer = setTimeout(function () { state.paged = 1; fetch(false); }, 600);
        });
    }

    /* ── Dropdown top bar + RICERCA ── */
    function initDropdownSearch() {
        $('#mecspe-dd-search').on('click', function () {
            state.paged = 1;
            fetch(false);
        });
        /* Invio anche premendo Enter su un dropdown */
        $('.mecspe-dd').on('keydown', function (e) {
            if (e.key === 'Enter') { state.paged = 1; fetch(false); }
        });
    }

    /* ── Load more ── */
    function initLoadMore() {
        $(document).on('click', '#mecspe-load-more', function () {
            if (state.loading) return;
            state.paged++;
            fetch(true);
        });
    }

    /* ── Reset ── */
    function initReset() {
        $(document).on('click', '#mecspe-reset', function () {
            $('.mecspe-filter-check').prop('checked', false);
            $('#mecspe-km-min, #mecspe-km-max').val('');
            $('.mecspe-dd').val('');
            state.taxes = {};
            state.paged = 1;
            fetch(false);
        });
    }

    /* ── FETCH AJAX ── */
    function fetch(append) {
        if (state.loading) return;
        state.loading = true;
        $wrap.addClass('mecspe-loading');

        if (!append) {
            $list.html(skeleton(4));
        } else {
            $loadMore.prop('disabled', true).text('Caricamento…');
        }

        var data = {
            action:        'mecspe_filter',
            nonce:         MecspeAjax.nonce,
            paged:         state.paged,
            mecspe_order:  state.order,
            mecspe_km_min: $('#mecspe-km-min').val() || '',
            mecspe_km_max: $('#mecspe-km-max').val() || '',
            dd_marca:      $('#dd_marca').val()  || '',
            dd_anno:       $('#dd_anno').val()   || '',
            dd_cambio:     $('#dd_cambio').val() || '',
            dd_allest:     $('#dd_allest').val() || '',
        };
        $.each(state.taxes, function (tax, vals) { data[tax] = vals; });

        $.post(MecspeAjax.ajaxurl, data)
            .done(function (res) {
                if (!res.success) return;
                var d = res.data;
                state.maxPages = d.max_pages;
                if (append) {
                    $list.append(d.html);
                } else {
                    $list.html(d.html);
                    $('html,body').animate({ scrollTop: $wrap.offset().top - 20 }, 250);
                }
                $count.text('Risultati: ' + d.found);
                if (state.paged >= state.maxPages) {
                    $pagination.hide();
                } else {
                    $pagination.show();
                    $loadMore.prop('disabled', false).text('Carica altri');
                }
            })
            .fail(function () {
                if (!append) $list.html('<div class="mecspe-no-results"><p>Errore nel caricamento.</p></div>');
            })
            .always(function () {
                state.loading = false;
                $wrap.removeClass('mecspe-loading');
            });
    }

    /* ── Skeleton loader ── */
    function skeleton(n) {
        var h = '';
        for (var i = 0; i < n; i++) {
            h += '<div class="mecspe-card">'
               + '<div class="mecspe-card-left" style="background:#f5f5f5">'
               + '<div class="mp-skel" style="height:20px"></div>'
               + '<div class="mp-skel" style="height:155px;margin:4px 0"></div>'
               + '<div style="padding:6px;display:flex;gap:3px">'
               + '<div class="mp-skel" style="height:18px;flex:1"></div>'
               + '<div class="mp-skel" style="height:18px;flex:1"></div>'
               + '</div></div>'
               + '<div class="mecspe-card-right">'
               + '<div class="mp-skel" style="height:22px;width:60%;margin-bottom:8px"></div>'
               + '<div class="mp-skel" style="height:14px;width:40%;margin-bottom:14px"></div>'
               + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px">'
               + '<div class="mp-skel" style="height:14px"></div>'
               + '<div class="mp-skel" style="height:14px"></div>'
               + '<div class="mp-skel" style="height:14px"></div>'
               + '<div class="mp-skel" style="height:14px"></div>'
               + '</div></div></div>';
        }
        return h;
    }

}(jQuery));
