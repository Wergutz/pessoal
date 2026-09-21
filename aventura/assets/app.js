/* Pequenos ajustes de interface. Tudo funciona sem JS; isto apenas ajuda. */
(function () {
    'use strict';

    // Confirmação antes de ações destrutivas.
    document.querySelectorAll('[data-confirmar]').forEach(function (elemento) {
        elemento.addEventListener('click', function (evento) {
            if (!window.confirm(elemento.dataset.confirmar)) {
                evento.preventDefault();
            }
        });
    });

    // Mascara simples de telefone: (51) 99999-9999
    document.querySelectorAll('[data-mascara="telefone"]').forEach(function (campo) {
        campo.addEventListener('input', function () {
            var d = campo.value.replace(/\D/g, '').slice(0, 11);
            if (d.length > 6) {
                campo.value = '(' + d.slice(0, 2) + ') ' + d.slice(2, d.length - 4) + '-' + d.slice(-4);
            } else if (d.length > 2) {
                campo.value = '(' + d.slice(0, 2) + ') ' + d.slice(2);
            } else {
                campo.value = d;
            }
        });
    });

    // Mostra os campos do responsável apenas para menores de idade.
    var nascimento = document.querySelector('#nascimento');
    var blocoResponsavel = document.querySelector('#bloco-responsavel');
    if (nascimento && blocoResponsavel) {
        var referencia = blocoResponsavel.dataset.referencia || '';
        var avaliar = function () {
            if (!nascimento.value || !referencia) {
                blocoResponsavel.hidden = false;
                return;
            }
            var nasc = new Date(nascimento.value + 'T00:00:00');
            var ref = new Date(referencia + 'T00:00:00');
            var idade = ref.getFullYear() - nasc.getFullYear();
            var mes = ref.getMonth() - nasc.getMonth();
            if (mes < 0 || (mes === 0 && ref.getDate() < nasc.getDate())) {
                idade--;
            }
            blocoResponsavel.hidden = idade >= 18;
        };
        nascimento.addEventListener('change', avaliar);
        avaliar();
    }

    // Filtro instantaneo em tabelas marcadas.
    var busca = document.querySelector('[data-filtra-tabela]');
    if (busca) {
        var tabela = document.querySelector(busca.dataset.filtraTabela);
        if (tabela) {
            busca.addEventListener('input', function () {
                var termo = busca.value.toLowerCase().trim();
                tabela.querySelectorAll('tbody tr').forEach(function (linha) {
                    linha.hidden = termo !== '' && linha.textContent.toLowerCase().indexOf(termo) === -1;
                });
            });
        }
    }
}());
