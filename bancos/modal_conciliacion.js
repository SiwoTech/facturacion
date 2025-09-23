$(document).ready(function() {
    let depositoId = null;
    let depositoMonto = 0;
    let facturasAgregadas = [];
    let montoCubierto = 0;

    // Abrir modal
    $('.btn-conciliar').on('click', function() {
        depositoId = $(this).data('id');
        depositoMonto = parseFloat($(this).data('deposito'));
        facturasAgregadas = [];
        montoCubierto = 0;
        $('#modal-facturas-list').html('');
        $('#modal-restante').html('Monto restante: $' + depositoMonto.toFixed(2));
        $('#modal-condonar').hide();
        $('#modal-conciliacion').show();
    });

    // Cerrar modal
    $('#modal-cerrar').on('click', function() {
        $('#modal-conciliacion').hide();
    });

    // Agregar factura
    $('#modal-agregar-factura').on('click', function() {
        let factura = $('#modal-factura-select').val();
        let monto = parseFloat($('#modal-factura-select option:selected').data('monto'));
        if (!factura || isNaN(monto)) return;
        facturasAgregadas.push({factura, monto});
        montoCubierto += monto;
        let html = '';
        facturasAgregadas.forEach(function(f) {
            html += f.factura + ' ($' + f.monto.toFixed(2) + ')<br>';
        });
        $('#modal-facturas-list').html(html);
        let restante = depositoMonto - montoCubierto;
        $('#modal-restante').html('Monto restante: $' + restante.toFixed(2));
        if (restante > 0) {
            $('#modal-condonar').show();
        } else {
            $('#modal-condonar').hide();
        }
    });

    // Aplicar condonación
    $('#modal-condonar').on('click', function() {
        let restante = depositoMonto - montoCubierto;
        if (restante <= 0) return;
        // Enviar facturas y condonación al backend
        $.post('conciliar_deposito.php', {
            deposito_id: depositoId,
            facturas: JSON.stringify(facturasAgregadas),
            condonacion: restante
        }, function(resp) {
            alert(resp);
            location.reload();
        });
        $('#modal-conciliacion').hide();
    });
});