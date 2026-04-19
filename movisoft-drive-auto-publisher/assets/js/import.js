jQuery(function ($) {
	'use strict';

	const $status = $('#movisoft-import-status');
	const $button = $('#movisoft-start-import');
	const $checkAll = $('#movisoft-check-all');

	if ($checkAll.length) {
		$checkAll.on('change', function () {
			$('.movisoft-file-check:not(:disabled)').prop('checked', $(this).is(':checked'));
		});
	}

	function log(message) {
		$status.append($('<p>').text(message));
	}

	function getSelectedIds() {
		const ids = [];
		$('.movisoft-file-check:checked').each(function () {
			ids.push($(this).val());
		});
		return ids;
	}

	function processBatch(offset, total) {
		$.post(movisoftDAP.ajaxUrl, {
			action: 'movisoft_dap_process_batch',
			nonce: movisoftDAP.nonce,
			offset: offset
		}).done(function (response) {
			if (!response.success) {
				log('Error: ' + (response.data.message || 'Error en batch.'));
				$button.prop('disabled', false);
				return;
			}

			const data = response.data;
			log('Procesados: ' + data.next_offset + ' / ' + total);

			if (data.complete) {
				log('Importación completada.');
				$button.prop('disabled', false);
				return;
			}

			processBatch(data.next_offset, total);
		}).fail(function () {
			log('Error AJAX al procesar lote.');
			$button.prop('disabled', false);
		});
	}

	$button.on('click', function () {
		const selectedIds = getSelectedIds();
		$status.empty();

		if (!selectedIds.length) {
			log('Selecciona al menos un archivo no publicado.');
			return;
		}

		$button.prop('disabled', true);
		log('Iniciando importación...');

		$.post(movisoftDAP.ajaxUrl, {
			action: 'movisoft_dap_start_import',
			nonce: movisoftDAP.nonce,
			selected_ids: selectedIds
		}).done(function (response) {
			if (!response.success) {
				log('Error: ' + (response.data.message || 'No se pudo iniciar.'));
				$button.prop('disabled', false);
				return;
			}

			const total = response.data.total || 0;
			if (total < 1) {
				log('No hay archivos para importar.');
				$button.prop('disabled', false);
				return;
			}

			log('Total de archivos seleccionados: ' + total);
			processBatch(0, total);
		}).fail(function () {
			log('Error AJAX al iniciar.');
			$button.prop('disabled', false);
		});
	});
});
