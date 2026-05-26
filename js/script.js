document.addEventListener('DOMContentLoaded', () => {
    // Lógica das Abas
    const tabContainer = document.querySelector('.tab-container');
    if (tabContainer) {
        tabContainer.addEventListener('click', (event) => {
            const tab = event.target.closest('.tab-btn');
            if (!tab || (tab.tagName === 'A' && tab.getAttribute('href') !== '#')) return;
            event.preventDefault();

            tabContainer.querySelectorAll('.tab-btn').forEach(item => item.classList.remove('active'));
            tab.classList.add('active');

            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            const targetTab = tab.dataset.tab;
            if (targetTab) {
                const targetContent = document.getElementById(`tab-${targetTab}`);
                if (targetContent) targetContent.classList.add('active');
            }
        });
    }

    // Lógica Genérica para Modais
    const setupModal = (modalId, openBtnSelector = null, formId = null) => {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        const form = formId ? document.getElementById(formId) : null;
        const closeBtns = modal.querySelectorAll('.close-modal-btn');

        const openModal = () => modal.classList.remove('hidden');
        const closeModal = () => {
            modal.classList.add('hidden');
            if (form) form.reset();
        };

        if (openBtnSelector) {
            document.querySelectorAll(openBtnSelector).forEach(btn => btn.addEventListener('click', openModal));
        }
        
        if(openBtnSelector && document.getElementById(openBtnSelector)) {
             document.getElementById(openBtnSelector).addEventListener('click', openModal);
        }

        closeBtns.forEach(btn => btn.addEventListener('click', closeModal));
        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
    };
    
    // Configura modais
    setupModal('modal-pregao', 'open-modal-pregao-btn', 'form-pregao');
    setupModal('modal-fornecedor', 'open-modal-fornecedor-btn', 'form-fornecedor');
    setupModal('modal-edit-item', null, 'form-edit-item'); 

    // Lógica para preencher modal de edição de PREGÃO
    document.querySelectorAll('.edit-pregao-btn').forEach(button => {
        button.addEventListener('click', () => {
            const modal = document.getElementById('modal-pregao');
            const form = document.getElementById('form-pregao');
            if(!modal || !form) return;
            
            form.reset();
            modal.querySelector('#modal-pregao-title').textContent = 'Editar Pregão';
            const submitBtn = modal.querySelector('#submit-pregao-btn');
            submitBtn.textContent = 'Salvar Alterações';
            submitBtn.name = 'submit_edit_pregao';
            
            form.querySelector('[name="edit_pregao_id"]').value = button.dataset.id;
            form.querySelector('[name="numero_edital"]').value = button.dataset.numero_edital || '';
            form.querySelector('[name="numero_processo"]').value = button.dataset.numero_processo || '';
            form.querySelector('[name="modalidade"]').value = button.dataset.modalidade || '';
            form.querySelector('[name="orgao_comprador"]').value = button.dataset.orgao_comprador || '';
            form.querySelector('[name="local_disputa"]').value = button.dataset.local_disputa || '';
            form.querySelector('[name="uasg"]').value = button.dataset.uasg || '';
            form.querySelector('[name="objeto"]').value = button.dataset.objeto || '';
            form.querySelector('[name="data_sessao"]').value = button.dataset.data_sessao || '';
            form.querySelector('[name="hora_sessao"]').value = button.dataset.hora_sessao || '';
            form.querySelector('[name="status"]').value = button.dataset.status || '';
            
            modal.classList.remove('hidden');
        });
    });
    
    // Lógica para preencher modal de edição de ITEM
    document.querySelectorAll('.edit-item-btn').forEach(button => {
        button.addEventListener('click', () => {
            const modal = document.getElementById('modal-edit-item');
            const form = document.getElementById('form-edit-item');
            if (!modal || !form) return;

            form.reset();
            
            form.querySelector('[name="edit_item_id"]').value = button.dataset.id;
            form.querySelector('[name="edit_fornecedor_id"]').value = button.dataset.fornecedor_id || '';
            form.querySelector('[name="edit_numero_lote"]').value = button.dataset.numero_lote || '';
            form.querySelector('[name="edit_numero_item"]').value = button.dataset.numero_item || '';
            form.querySelector('[name="edit_descricao"]').value = button.dataset.descricao || '';
            form.querySelector('[name="edit_fabricante"]').value = button.dataset.fabricante || '';
            form.querySelector('[name="edit_modelo"]').value = button.dataset.modelo || '';
            form.querySelector('[name="edit_quantidade"]').value = button.dataset.quantidade || '';
            form.querySelector('[name="edit_valor_unitario"]').value = button.dataset.valor_unitario || '';
            form.querySelector('[name="edit_valor_unitario_ref"]').value = button.dataset.valor_unitario_ref || '0';
            form.querySelector('[name="edit_status_item"]').value = button.dataset.status_item || 'Classificada';
            form.querySelector('[name="edit_status_item_ref"]').value = button.dataset.status_item_ref || '';
            form.querySelector('[name="edit_status_motivo"]').value = button.dataset.status_motivo || '';
            
            modal.classList.remove('hidden');
        });
    });

    // LÓGICA DAS NOTIFICAÇÕES
    const notificacoesContainer = document.getElementById('notificacoes-container');
    if (notificacoesContainer) {
        const notificacoesBtn = document.getElementById('notificacoes-btn');
        const notificacoesDropdown = document.getElementById('notificacoes-dropdown');
        const notificacoesBadge = document.getElementById('notificacoes-badge');

        notificacoesBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isHidden = notificacoesDropdown.classList.toggle('hidden');

            if (!isHidden && notificacoesBadge) {
                fetch('api_modules.php?module=notificacoes&action=mark_as_read', {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        setTimeout(() => {
                           if (notificacoesBadge) {
                               notificacoesBadge.remove();
                           }
                        }, 2000); 
                    }
                })
                .catch(error => console.error('Erro ao marcar notificações como lidas:', error));
            }
        });

        document.addEventListener('click', (e) => {
            if (!notificacoesContainer.contains(e.target)) {
                notificacoesDropdown.classList.add('hidden');
            }
        });
    }
    
    // NOTIFICAÇÕES TOAST
    const showToast = (message, type = 'success') => {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        container.appendChild(toast);

        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            toast.addEventListener('transitionend', () => toast.remove());
        }, 5000);
    };

    // MODAL DE CONFIRMAÇÃO PARA EXCLUSÃO
    const confirmModal = document.getElementById('modal-confirm');
    if (confirmModal) {
        const messageEl = document.getElementById('modal-confirm-message');
        const okBtn = document.getElementById('modal-confirm-ok');
        const cancelBtn = document.getElementById('modal-confirm-cancel');
        let formToSubmit = null;

        document.body.addEventListener('click', function(event) {
            if (event.target.classList.contains('js-confirm-delete')) {
                const button = event.target;
                const formId = button.dataset.formId;
                const message = button.dataset.message;
                formToSubmit = document.getElementById(formId);
                if (formToSubmit && message) {
                    messageEl.textContent = message;
                    confirmModal.classList.remove('hidden');
                }
            }
        });
        
        okBtn.addEventListener('click', () => {
            if (formToSubmit) formToSubmit.submit();
            confirmModal.classList.add('hidden');
        });
        cancelBtn.addEventListener('click', () => confirmModal.classList.add('hidden'));
    }
    
    // AJAX PARA ADICIONAR FORNECEDOR, MÁSCARA CNPJ E AUTO-PREENCHIMENTO
    const fornecedorForm = document.getElementById('form-fornecedor');
    const cnpjInput = document.getElementById('cnpj_fornecedor_input');
    const cnpjStatus = document.getElementById('cnpj-status-text');
    let cnpjBuscaTimer = null;

    if (cnpjInput) {
        cnpjInput.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/^(\d{2})(\d)/, '$1.$2');
            value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
            value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
            value = value.replace(/(\d{4})(\d)/, '$1-$2');
            e.target.value = value.slice(0, 18);

            const digits = value.replace(/\D/g, '');
            if (digits.length === 14) {
                if (cnpjBuscaTimer) clearTimeout(cnpjBuscaTimer);
                cnpjBuscaTimer = setTimeout(() => buscarCNPJ(digits), 500);
            } else if (digits.length > 2 && cnpjStatus) {
                cnpjStatus.textContent = 'Digite 14 dígitos para buscar...';
                cnpjStatus.className = 'text-xs text-gray-400';
            } else {
                if (cnpjStatus) cnpjStatus.textContent = '';
                const msgDiv = document.getElementById('fornecedor-form-message');
                if (msgDiv) msgDiv.innerHTML = '';
            }
        });
    }

    async function buscarCNPJ(cnpj) {
        if (!cnpjStatus) return;
        cnpjStatus.textContent = 'Buscando dados do CNPJ...';
        cnpjStatus.className = 'text-xs text-blue-600';

        const messageDiv = document.getElementById('fornecedor-form-message');

        try {
            const response = await fetch('api_handler.php?action=buscar_cnpj&cnpj=' + cnpj);
            const result = await response.json();

            if (result.success && result.data) {
                const d = result.data;

                if (d.ja_cadastrado) {
                    if (messageDiv) {
                        messageDiv.innerHTML = '<div class="bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded-md mb-2"><strong>Atenção!</strong> Este CNPJ já está cadastrado como <strong>' + d.cadastrado_nome + '</strong>.</div>';
                    }
                    cnpjStatus.textContent = 'CNPJ já cadastrado!';
                    cnpjStatus.className = 'text-xs text-yellow-600 font-bold';
                } else {
                    if (messageDiv) messageDiv.innerHTML = '';
                    cnpjStatus.textContent = 'Dados preenchidos automaticamente';
                    cnpjStatus.className = 'text-xs text-green-600';
                }

                const nomeInput = document.querySelector('[name="nome_fornecedor"]');
                const fantasiaInput = document.getElementById('nome_fantasia_fornecedor');
                const porteSelect = document.getElementById('porte_fornecedor');
                const enderecoInput = document.getElementById('endereco_fornecedor');
                const bairroInput = document.getElementById('bairro_fornecedor');
                const cidadeInput = document.getElementById('cidade_fornecedor');
                const estadoSelect = document.getElementById('estado_fornecedor_select');
                const cepInput = document.getElementById('cep_fornecedor');

                if (nomeInput && d.razao_social) nomeInput.value = d.razao_social;
                if (fantasiaInput && d.nome_fantasia) fantasiaInput.value = d.nome_fantasia;

                if (porteSelect && d.porte) {
                    const porteNorm = d.porte.toUpperCase();
                    if (['MEI', 'ME', 'EPP', 'DEMAIS', 'GRANDE'].includes(porteNorm)) {
                        porteSelect.value = porteNorm;
                    }
                }

                let endereco = d.logradouro || '';
                if (d.numero) endereco += ', ' + d.numero;
                if (d.complemento) endereco += ' - ' + d.complemento;
                if (enderecoInput && endereco) enderecoInput.value = endereco;

                if (bairroInput && d.bairro) bairroInput.value = d.bairro;
                if (cidadeInput && d.municipio) cidadeInput.value = d.municipio;
                if (estadoSelect && d.uf) estadoSelect.value = d.uf;
                if (cepInput && d.cep) cepInput.value = d.cep;
            } else {
                cnpjStatus.textContent = result.error || 'CNPJ não encontrado';
                cnpjStatus.className = 'text-xs text-red-500';
            }
        } catch (error) {
            cnpjStatus.textContent = 'Erro ao consultar CNPJ. Preencha manualmente.';
            cnpjStatus.className = 'text-xs text-red-500';
        }
    }
    
    if(fornecedorForm) {
        fornecedorForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(fornecedorForm);
            const messageDiv = document.getElementById('fornecedor-form-message');
            
            try {
                const response = await fetch('api_handler.php?action=add_fornecedor', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    document.getElementById('modal-fornecedor').classList.add('hidden');
                    showToast(result.message, 'success');
                    
                    let cnpjDisplay = result.data.cnpj.replace(/\D/g, '');
                    if(cnpjDisplay.length === 14) {
                        cnpjDisplay = cnpjDisplay.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
                    }

                    const tableBody = document.getElementById('fornecedores-table-body');
                    const newRow = document.createElement('tr');
                    newRow.id = `fornecedor-row-${result.data.id}`;
                    newRow.innerHTML = `
                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">${result.data.nome}</td>
                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">${cnpjDisplay}</td>
                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">${result.data.me_epp}</td>
                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">${result.data.estado}</td>
                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-center">
                            <form id="delete-fornecedor-form-${result.data.id}" method="POST"><input type="hidden" name="excluir_id_fornecedor" value="${result.data.id}"></form>    
                            <button type="button" class="btn btn-danger btn-sm js-confirm-delete" data-form-id="delete-fornecedor-form-${result.data.id}" data-message="Tem certeza que deseja excluir o fornecedor ${result.data.nome}?">Excluir</button>
                        </td>
                    `;
                    tableBody.prepend(newRow); 
                    fornecedorForm.reset();
                } else {
                    messageDiv.innerHTML = `<div class="bg-red-100 text-red-700 p-3 rounded-md">${result.error}</div>`;
                }
            } catch (error) {
                messageDiv.innerHTML = `<div class="bg-red-100 text-red-700 p-3 rounded-md">Erro de conexão. Tente novamente.</div>`;
            }
        });
    }

    // GERENCIAMENTO DE ITENS E PARTICIPANTES DINÂMICOS
    const btnAddItem = document.getElementById('btn-add-item');
    const itensContainer = document.getElementById('itens-container');
    const templateItem = document.getElementById('template-item');
    const templateParticipante = document.getElementById('template-participante');

    if (btnAddItem && itensContainer && templateItem && templateParticipante) {
        let itemIndex = 0;

        function adicionarParticipante(itemBlock, itemIdx) {
            var idx = itemBlock.querySelectorAll('.participante-row').length;
            const clone = templateParticipante.content.cloneNode(true);
            const row = clone.querySelector('.participante-row');

            var fields = [
                { sel: '.part-fornecedor', name: 'itens[' + itemIdx + '][participantes][' + idx + '][fornecedor_id]' },
                { sel: '.part-fabricante', name: 'itens[' + itemIdx + '][participantes][' + idx + '][fabricante]' },
                { sel: '.part-modelo', name: 'itens[' + itemIdx + '][participantes][' + idx + '][modelo]' },
                { sel: '.part-valor', name: 'itens[' + itemIdx + '][participantes][' + idx + '][valor_unitario]' },
                { sel: '.part-status', name: 'itens[' + itemIdx + '][participantes][' + idx + '][status_item]' }
            ];
            fields.forEach(function(f) {
                var el = row.querySelector(f.sel);
                if (el) el.setAttribute('name', f.name);
            });

            row.querySelector('.remover-participante').addEventListener('click', function() {
                row.remove();
                reindexParticipantes(itemBlock, itemIdx);
            });

            itemBlock.querySelector('.item-participantes-container').appendChild(row);
        }

        function reindexParticipantes(itemBlock, itemIdx) {
            var rows = itemBlock.querySelectorAll('.participante-row');
            rows.forEach(function(row, i) {
                var fornecedorEl = row.querySelector('.part-fornecedor');
                if (fornecedorEl) fornecedorEl.setAttribute('name', 'itens[' + itemIdx + '][participantes][' + i + '][fornecedor_id]');
                var fabEl = row.querySelector('.part-fabricante');
                if (fabEl) fabEl.setAttribute('name', 'itens[' + itemIdx + '][participantes][' + i + '][fabricante]');
                var modEl = row.querySelector('.part-modelo');
                if (modEl) modEl.setAttribute('name', 'itens[' + itemIdx + '][participantes][' + i + '][modelo]');
                var valEl = row.querySelector('.part-valor');
                if (valEl) valEl.setAttribute('name', 'itens[' + itemIdx + '][participantes][' + i + '][valor_unitario]');
                var statusEl = row.querySelector('.part-status');
                if (statusEl) statusEl.setAttribute('name', 'itens[' + itemIdx + '][participantes][' + i + '][status_item]');
            });
        }

        function adicionarItem() {
            const clone = templateItem.content.cloneNode(true);
            const block = clone.querySelector('.item-bloco');
            var idx = itemIndex;

            var itemFields = [
                { sel: '.item-numero', name: 'itens[' + idx + '][numero_item]' },
                { sel: '.item-descricao', name: 'itens[' + idx + '][descricao_item]' },
                { sel: '.item-qtd', name: 'itens[' + idx + '][quantidade_item]' },
                { sel: '.item-vref', name: 'itens[' + idx + '][valor_unitario_ref_item]' },
                { sel: '.item-status-ref', name: 'itens[' + idx + '][status_item_ref_item]' }
            ];
            itemFields.forEach(function(f) {
                var el = block.querySelector(f.sel);
                if (el) el.setAttribute('name', f.name);
            });

            block.querySelector('.remover-item').addEventListener('click', function() {
                block.remove();
                reindexItens();
            });

            var btnAddPart = block.querySelector('.btn-add-participante-item');
            btnAddPart.addEventListener('click', function() {
                adicionarParticipante(block, idx);
            });

            itensContainer.appendChild(block);

            // Inicia com 1 participante
            adicionarParticipante(block, idx);

            itemIndex++;
        }

        function reindexItens() {
            itemIndex = 0;
            var blocks = itensContainer.querySelectorAll('.item-bloco');
            blocks.forEach(function(block, newIdx) {
                // Reindex item reference fields
                var numEl = block.querySelector('.item-numero');
                if (numEl) numEl.setAttribute('name', 'itens[' + newIdx + '][numero_item]');
                var descEl = block.querySelector('.item-descricao');
                if (descEl) descEl.setAttribute('name', 'itens[' + newIdx + '][descricao_item]');
                var qtdEl = block.querySelector('.item-qtd');
                if (qtdEl) qtdEl.setAttribute('name', 'itens[' + newIdx + '][quantidade_item]');
                var vrefEl = block.querySelector('.item-vref');
                if (vrefEl) vrefEl.setAttribute('name', 'itens[' + newIdx + '][valor_unitario_ref_item]');
                var stEl = block.querySelector('.item-status-ref');
                if (stEl) stEl.setAttribute('name', 'itens[' + newIdx + '][status_item_ref_item]');

                // Reindex participantes desta linha
                reindexParticipantes(block, newIdx);

                itemIndex++;
            });
        }

        btnAddItem.addEventListener('click', adicionarItem);

        // Inicia com 1 item
        adicionarItem();
    }

    // MODAL DE EDIÇÃO EM LOTE (ITEM COMPLETO)
    const modalBulk = document.getElementById('modal-edit-item-bulk');
    if (modalBulk) {
        const closeBtnsBulk = modalBulk.querySelectorAll('.close-modal-btn');
        closeBtnsBulk.forEach(function(btn) {
            btn.addEventListener('click', function() { modalBulk.classList.add('hidden'); });
        });
        modalBulk.addEventListener('click', function(e) { if (e.target === modalBulk) modalBulk.classList.add('hidden'); });

        var bulkParticipanteIndex = 0;

        function adicionarParticipanteBulk(container) {
            var idx = bulkParticipanteIndex;
            var html = '<div class="participante-row-bulk border rounded-md p-3 bg-gray-50 relative mb-2">' +
                '<button type="button" class="remover-participante-bulk absolute top-2 right-2 text-red-500 hover:text-red-700 font-bold text-lg">&times;</button>' +
                '<div class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-2 pr-6">' +
                '<div class="md:col-span-2"><label class="text-xs text-gray-600">Fornecedor</label>' +
                '<select name="itens_bulk[0][participantes][' + idx + '][fornecedor_id]" class="part-forn-bulk form-input w-full px-2 py-1.5 border rounded text-sm" required>' +
                '<option value="">Selecione...</option></select></div>' +
                '<div><label class="text-xs text-gray-600">Fabricante/Marca</label>' +
                '<input type="text" name="itens_bulk[0][participantes][' + idx + '][fabricante]" class="part-fab-bulk form-input w-full px-2 py-1.5 border rounded text-sm" placeholder="Opcional"></div>' +
                '<div><label class="text-xs text-gray-600">Modelo</label>' +
                '<input type="text" name="itens_bulk[0][participantes][' + idx + '][modelo]" class="part-mod-bulk form-input w-full px-2 py-1.5 border rounded text-sm" placeholder="Opcional"></div>' +
                '<div><label class="text-xs text-gray-600">Valor Unitário (R$)</label>' +
                '<input type="number" step="0.01" name="itens_bulk[0][participantes][' + idx + '][valor_unitario]" class="part-val-bulk form-input w-full px-2 py-1.5 border rounded text-sm" required></div>' +
                '<div><label class="text-xs text-gray-600">Status</label>' +
                '<select name="itens_bulk[0][participantes][' + idx + '][status_item]" class="part-st-bulk form-input w-full px-2 py-1.5 border rounded text-sm"></select></div>' +
                '</div></div>';

            var tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            var row = tempDiv.firstElementChild;

            row.querySelector('.remover-participante-bulk').addEventListener('click', function() {
                row.remove();
                reindexBulkParticipantes(container);
            });

            var tmplPart = document.querySelector('#template-participante');
            if (tmplPart) {
                var fornSelect = row.querySelector('.part-forn-bulk');
                var statusSelect = row.querySelector('.part-st-bulk');
                if (fornSelect) {
                    var tmplForn = tmplPart.content.querySelector('.part-fornecedor');
                    if (tmplForn) fornSelect.innerHTML = tmplForn.innerHTML;
                }
                if (statusSelect) {
                    var tmplSt = tmplPart.content.querySelector('.part-status');
                    if (tmplSt) statusSelect.innerHTML = tmplSt.innerHTML;
                }
            }

            container.appendChild(row);
            bulkParticipanteIndex++;
        }

        function reindexBulkParticipantes(container) {
            var rows = container.querySelectorAll('.participante-row-bulk');
            if (rows.length === 0) { bulkParticipanteIndex = 0; return; }
            var idx = 0;
            rows.forEach(function(row) {
                var selects = row.querySelectorAll('select, input');
                selects.forEach(function(el) {
                    var name = el.getAttribute('name');
                    if (name) {
                        name = name.replace(/\[participantes\]\[\d+\]/, '[participantes][' + idx + ']');
                        el.setAttribute('name', name);
                    }
                });
                idx++;
            });
            bulkParticipanteIndex = idx;
        }

        document.body.addEventListener('click', function(event) {
            var btn = event.target.closest('.edit-item-bulk-btn');
            if (!btn) return;

            var loteKey = btn.getAttribute('data-lote');
            var itemKey = btn.getAttribute('data-item');
            var data = (window._itensData && window._itensData[loteKey] && window._itensData[loteKey][itemKey]) ? window._itensData[loteKey][itemKey] : [];

            if (!data.length) return;

            var itemRef = data[0];

            document.getElementById('edit_bulk_old_lote').value = (loteKey === 'SEM_LOTE' ? '' : loteKey);
            document.getElementById('edit_bulk_old_item').value = itemKey;
            document.getElementById('edit_bulk_numero_lote').value = (loteKey === 'SEM_LOTE' ? '' : loteKey);
            document.getElementById('edit_bulk_numero_item').value = itemRef.numero_item || '';
            document.getElementById('edit_bulk_descricao').value = itemRef.descricao || '';
            document.getElementById('edit_bulk_quantidade').value = itemRef.quantidade || '';
            document.getElementById('edit_bulk_valor_unitario_ref').value = itemRef.valor_unitario_ref || '0';
            document.getElementById('edit_bulk_status_item_ref').value = itemRef.status_item_ref || '';

            var partContainer = document.getElementById('participantes-bulk-container');
            partContainer.innerHTML = '';
            bulkParticipanteIndex = 0;

            data.forEach(function(p) {
                var idx = bulkParticipanteIndex;
                var html = '<div class="participante-row-bulk border rounded-md p-3 bg-gray-50 relative mb-2">' +
                    '<button type="button" class="remover-participante-bulk absolute top-2 right-2 text-red-500 hover:text-red-700 font-bold text-lg">&times;</button>' +
                    '<div class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-2 pr-6">' +
                    '<div class="md:col-span-2"><label class="text-xs text-gray-600">Fornecedor</label>' +
                    '<select name="itens_bulk[0][participantes][' + idx + '][fornecedor_id]" class="part-forn-bulk form-input w-full px-2 py-1.5 border rounded text-sm" required>' +
                    '<option value="">Selecione...</option></select></div>' +
                    '<div><label class="text-xs text-gray-600">Fabricante/Marca</label>' +
                    '<input type="text" name="itens_bulk[0][participantes][' + idx + '][fabricante]" class="part-fab-bulk form-input w-full px-2 py-1.5 border rounded text-sm" value="' + (p.fabricante || '').replace(/"/g, '&quot;') + '" placeholder="Opcional"></div>' +
                    '<div><label class="text-xs text-gray-600">Modelo</label>' +
                    '<input type="text" name="itens_bulk[0][participantes][' + idx + '][modelo]" class="part-mod-bulk form-input w-full px-2 py-1.5 border rounded text-sm" value="' + (p.modelo || '').replace(/"/g, '&quot;') + '" placeholder="Opcional"></div>' +
                    '<div><label class="text-xs text-gray-600">Valor Unitário (R$)</label>' +
                    '<input type="number" step="0.01" name="itens_bulk[0][participantes][' + idx + '][valor_unitario]" class="part-val-bulk form-input w-full px-2 py-1.5 border rounded text-sm" value="' + (p.valor_unitario || '') + '" required></div>' +
                    '<div><label class="text-xs text-gray-600">Status</label>' +
                    '<select name="itens_bulk[0][participantes][' + idx + '][status_item]" class="part-st-bulk form-input w-full px-2 py-1.5 border rounded text-sm"></select></div>' +
                    '</div></div>';

                var tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;
                var row = tempDiv.firstElementChild;

                row.querySelector('.remover-participante-bulk').addEventListener('click', function() {
                    row.remove();
                    reindexBulkParticipantes(partContainer);
                });

                var tmplPart = document.querySelector('#template-participante');
                if (tmplPart) {
                    var fornSelect = row.querySelector('.part-forn-bulk');
                    var statusSelect = row.querySelector('.part-st-bulk');
                    if (fornSelect) {
                        var tmplForn = tmplPart.content.querySelector('.part-fornecedor');
                        if (tmplForn) {
                            fornSelect.innerHTML = tmplForn.innerHTML;
                            fornSelect.value = p.fornecedor_id || '';
                        }
                    }
                    if (statusSelect) {
                        var tmplSt = tmplPart.content.querySelector('.part-status');
                        if (tmplSt) {
                            statusSelect.innerHTML = tmplSt.innerHTML;
                            statusSelect.value = p.status_item || 'Classificada';
                        }
                    }
                }

                partContainer.appendChild(row);
                bulkParticipanteIndex++;
            });

            modalBulk.classList.remove('hidden');
        });

        var btnAddPartBulk = document.getElementById('btn-add-participante-bulk');
        if (btnAddPartBulk) {
            btnAddPartBulk.addEventListener('click', function() {
                adicionarParticipanteBulk(document.getElementById('participantes-bulk-container'));
            });
        }
    }

});

