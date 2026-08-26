/* RPP Kusmedios Deluxe - Admin JS */
(function ($) {
  'use strict';

  var data = window.rpkusData || {};
  var strings = data.strings || {};

  var platformHints = {
	azuracast:  'Ej: https://radio.miservidor.com',
	zenofm:     'Dejar en blanco (ZenoFM no necesita URL base)',
	sonicpanel: 'Ej: https://miservidor.com  (sin puerto)',
	shoutcast:  'Ej: http://miservidor.com:8000',
	icecast:    'Ej: http://miservidor.com:8000',
	manual:     'Ingresa la URL directamente',
  };

  var platformEndpoints = {
	azuracast:    '/api/nowplaying/',
	zenofm:       '/mounts/icestats/sub/',
	sonicpanel:   '/stats?json=1',
	shoutcast:    '/statistics?json=1&sid=',
	icecast:      '/status-json.xsl',
  };

  function getStreamUrl(platform, baseUrl, extra) {
	var base = (baseUrl || '').replace(/\/+$/, '');
	switch (platform) {
	  case 'azuracast':
		var stid  = $('#rpkus_azura_station_id').val() || '1';
		var mount = $('#rpkus_azura_mount').val() || '/radio.mp3';
		return base + '/listen/' + stid + mount;
	  case 'zenofm':
		var zid = $('#rpkus_zeno_id').val() || 'STATION-ID';
		return 'https://stream.zeno.fm/' + zid;
	  case 'sonicpanel':
		var port = $('#rpkus_sonic_port').val() || '8000';
		return base + ':' + port + '/stream';
	  case 'shoutcast':
		return base + ';stream.mp3';
	  case 'icecast':
		var icm = $('#rpkus_ic_mount').val() || '/stream';
		return base + icm;
	  default:
		return base;
	}
  }

  function showPlatformFields(platform) {
	// Hide all platform panels
	$('.rpkus-platform-fields').removeClass('rpkus-visible');
	// Hide base URL panel if no platform
	if (!platform) {
	  $('#rpkus-field-base-url').removeClass('rpkus-visible');
	  $('#rpkus-sync-section').removeClass('rpkus-visible');
	  $('#rpkus-generated-stream').removeClass('rpkus-visible');
	  return;
	}
	// Show base URL (except ZenoFM doesn't need it)
	if (platform !== 'zenofm') {
	  $('#rpkus-field-base-url').addClass('rpkus-visible');
	} else {
	  $('#rpkus-field-base-url').removeClass('rpkus-visible');
	}
	// Show matching platform panel
	$('[data-platform="' + platform + '"]').addClass('rpkus-visible');
	// Show sync section
	$('#rpkus-sync-section').addClass('rpkus-visible');
	$('#rpkus-generated-stream').addClass('rpkus-visible');
	// Toggle azura-only items
	if (platform === 'azuracast') {
	  $('.rpkus-azura-only').show();
	} else {
	  $('.rpkus-azura-only').hide();
	}
	// Update hint
	var hint = platformHints[platform] || '';
	$('#rpkus-base-url-hint').text(hint);
	// Update preview
	updateStreamPreview();
  }

  function updateStreamPreview() {
	var platform = $('#rpkus_platform').val();
	var baseUrl  = $('#rpkus_base_url').val();
	if (!platform) return;
	var url = getStreamUrl(platform, baseUrl);
	$('#rpkus-stream-preview').val(url);
	// Update Azuracast endpoint preview
	if (platform === 'azuracast') {
	  var stid = $('#rpkus_azura_station_id').val() || '1';
	  var ep = (baseUrl || 'https://tu-servidor').replace(/\/+$/, '') + '/api/nowplaying/' + stid;
	  $('#rpkus-azura-endpoint').text(ep);
	}
	// Update SonicPanel stream preview
	if (platform === 'sonicpanel') {
	  var port = $('#rpkus_sonic_port').val() || '8000';
	  var sp = (baseUrl || 'https://tu-servidor').replace(/\/+$/, '') + ':' + port + '/stream';
	  $('#rpkus-sonic-stream').text(sp);
	}
  }

  function testConnection() {
	var platform = $('#rpkus_platform').val();
	var baseUrl  = $('#rpkus_base_url').val();
	var $result  = $('#rpkus-test-result');
	var $btn     = $('#rpkus-test-btn');

	if (!platform || !baseUrl) {
	  $result.text('Selecciona plataforma e ingresa URL base.').removeClass('ok').addClass('err');
	  return;
	}

	var extra = '';
	if (platform === 'azuracast')   extra = $('#rpkus_azura_station_id').val() || '1';
	if (platform === 'zenofm')      extra = $('#rpkus_zeno_id').val();
	if (platform === 'sonicpanel')  extra = $('#rpkus_sonic_port').val() || '8000';
	if (platform === 'shoutcast')   extra = $('#rpkus_sc_sid').val() || '1';

	$btn.prop('disabled', true);
	$result.text(strings.testing || 'Probando...').removeClass('ok err');

	$.post(data.ajax_url, {
	  action:   'rpkus_test_connection',
	  nonce:    data.nonce,
	  platform: platform,
	  base_url: baseUrl,
	  extra:    extra,
	})
	.done(function (resp) {
	  if (resp.success) {
		$result.text(strings.test_ok || 'Conexion exitosa').removeClass('err').addClass('ok');
	  } else {
		$result.text((strings.test_fail || 'Error: ') + (resp.data || resp.error || '')).removeClass('ok').addClass('err');
	  }
	})
	.fail(function () {
	  $result.text(strings.test_fail || 'Error de conexion').removeClass('ok').addClass('err');
	})
	.always(function () {
	  $btn.prop('disabled', false);
	});
  }

  function copyToClipboard() {
	var val = $('#rpkus-stream-preview').val();
	if (!val) return;
	navigator.clipboard.writeText(val).then(function () {
	  var $btn = $('#rpkus-copy-stream');
	  var orig = $btn.text();
	  $btn.text(strings.copy_success || 'Copiado!');
	  setTimeout(function () { $btn.text(orig); }, 1500);
	});
  }

  function applyStreamUrl() {
	var url = $('#rpkus-stream-preview').val();
	if (!url) return;
	var $rppField = $('#radplapag_station_stream_url');
	if ($rppField.length) {
	  $rppField.val(url).trigger('input');
	  $rppField[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
	  $rppField.css('background', '#eaffea');
	  setTimeout(function () { $rppField.css('background', ''); }, 1500);
	  alert(strings.apply_success || 'URL aplicada al player!');
	} else {
	  alert('Campo Stream URL del plugin RPP no encontrado en esta pagina.');
	}
  }

  $(document).ready(function () {
	// Init
	var currentPlatform = $('#rpkus_platform').val();
	showPlatformFields(currentPlatform);

	// Platform change
	$('#rpkus_platform').on('change', function () {
	  showPlatformFields($(this).val());
	});

	// Live preview updates
	$('#rpkus_base_url, #rpkus_azura_station_id, #rpkus_azura_mount,
	   #rpkus_zeno_id, #rpkus_sonic_port, #rpkus_ic_mount').on('input change', function () {
	  updateStreamPreview();
	});

	// Test connection
	$('#rpkus-test-btn').on('click', testConnection);

	// Copy stream URL
	$('#rpkus-copy-stream').on('click', copyToClipboard);

	// Apply stream URL to RPP field
	$('#rpkus-apply-stream').on('click', applyStreamUrl);

	// Add tooltips for platform hints
	$(document).on('mouseenter', '#rpkus_base_url', function() {
	  var platform = $('#rpkus_platform').val();
	  var hint = platformHints[platform] || '';
	  if (hint) {
		$(this).prop('title', hint);
	  }
	});
  });

})(jQuery);