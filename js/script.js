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
            form.querySelector('[name="edit_status_item"]').value = button.dataset.status_item || 'Classificada';
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
    
    // AJAX PARA ADICIONAR FORNECEDOR E MÁSCARA CNPJ
    const fornecedorForm = document.getElementById('form-fornecedor');
    const cnpjInput = document.getElementById('cnpj_fornecedor_input');

    if (cnpjInput) {
        cnpjInput.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/^(\d{2})(\d)/, '$1.$2');
            value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
            value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
            value = value.replace(/(\d{4})(\d)/, '$1-$2');
            e.target.value = value.slice(0, 18);
        });
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
});

