(function ($) {
  'use strict';

  var $form   = $('#mecspe-filter-form');
  var $grid   = $('#mecspe-trucks-grid');
  var $count  = $('#mecspe-count');
  var $loader = $('#mecspe-loader');

  function doFilter() {
    var data = $form.serializeArray().reduce(function (acc, field) {
      acc[field.name] = field.value;
      return acc;
    }, {});

    data.action = 'mecspe_filter_trucks';
    data.nonce  = mecspeTrucks.nonce;

    $loader.show();
    $grid.addClass('mecspe-loading');

    $.post(mecspeTrucks.ajaxUrl, data)
      .done(function (res) {
        if (res.success) {
          $grid.html(res.data.html);
          $count.text(res.data.count + ' veicoli trovati');
        }
      })
      .always(function () {
        $loader.hide();
        $grid.removeClass('mecspe-loading');
      });
  }

  $form.on('submit', function (e) {
    e.preventDefault();
    doFilter();
  });

  $('#mecspe-reset').on('click', function () {
    $form[0].reset();
    doFilter();
  });

})(jQuery);
