/**
 * Funções para Gestão de Consignado
 */

// --- MODAL DE VINCULAÇÃO ---
function openModalVincular() {
    const inputPage = document.getElementById('numero_contrato');
    const inputModal = document.getElementById('numero_contrato_modal'); // Assumindo que existe este ID no modal ou ajustando a lógica
    // No código original, o input do modal não tinha ID específico para receber valor, 
    // mas a lógica tentava copiar. Vamos manter a consistência.
    
    // O modal original usa o mesmo name='numero_contrato' mas está dentro de um form diferente.
    // É ideal ter IDs únicos.
    
    document.getElementById('modal-vincular').classList.remove('hidden');
}

function closeModalVincular() { 
    document.getElementById('modal-vincular').classList.add('hidden'); 
}

function enableEditContrato() {
    const input = document.getElementById('numero_contrato');
    if (!input) return;
    
    input.removeAttribute('readonly');
    if(input.value === 'Não Informado') input.value = '';
    input.focus();
    input.classList.remove('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
    input.classList.add('bg-white', 'text-gray-900');
}

// --- MODAL DE PRODUTO ---
function openModalProduto() { 
    document.getElementById('modal-produto').classList.remove('hidden'); 
}

function closeModalProduto() { 
    document.getElementById('modal-produto').classList.add('hidden'); 
}

// --- MODAL EDITAR ITEM ---
function openModalItemInfo(item) {
    document.getElementById('modal_item_id').value = item.id;
    document.getElementById('modal_item_num').textContent = item.numero_item;
    document.getElementById('modal_item_desc').textContent = item.descricao;
    document.getElementById('modal_catmat').value = item.codigo_catmat || '';
    document.getElementById('modal_qtd_licitado').value = item.quantidade;
    document.getElementById('modal_qtd_entregue').value = item.qtd_entregue || 0;
    document.getElementById('modal_qtd_faturada').value = item.qtd_faturada || 0;
    document.getElementById('modal_observacao').value = item.observacao_item || '';
    
    document.getElementById('modal-item-info').classList.remove('hidden');
}

function closeModalItemInfo() { 
    document.getElementById('modal-item-info').classList.add('hidden'); 
}

// --- MODAL ADICIONAR AFC ---
function openModalAddAFC(item, loteNome) {
    document.getElementById('afc_item_id').value = item.id;
    document.getElementById('afc_lote').textContent = loteNome !== 'SEM_LOTE' ? loteNome : 'GERAL';
    document.getElementById('afc_item_num').textContent = 'ITEM ' + item.numero_item;
    document.getElementById('afc_catmat').value = item.codigo_catmat || '-';
    
    const valUnit = parseFloat(item.valor_unitario);
    document.getElementById('afc_unit').value = 'R$ ' + valUnit.toLocaleString('pt-BR', {minimumFractionDigits: 2});
    document.getElementById('afc_unit_val').value = valUnit;
    
    document.getElementById('afc_qtd').value = '';
    document.getElementById('afc_total').value = '';
    
    document.getElementById('modal-afc').classList.remove('hidden');
}

function closeModalAFC() { 
    document.getElementById('modal-afc').classList.add('hidden'); 
}

function calcTotalAFC() {
    const qtd = parseFloat(document.getElementById('afc_qtd').value) || 0;
    const unit = parseFloat(document.getElementById('afc_unit_val').value) || 0;
    const total = qtd * unit;
    
    document.getElementById('afc_total').value = 'R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits: 2});
    document.getElementById('afc_total_val').value = total;
}

// --- MODAL ADICIONAR CI ---
function openModalAddCI(item, loteNome) {
    document.getElementById('ci_item_id').value = item.id;
    document.getElementById('ci_lote').textContent = loteNome !== 'SEM_LOTE' ? loteNome : 'GERAL';
    document.getElementById('ci_item_num').textContent = 'ITEM ' + item.numero_item;
    document.getElementById('ci_catmat').value = item.codigo_catmat || '-';
    
    const valUnit = parseFloat(item.valor_unitario);
    document.getElementById('ci_unit').value = 'R$ ' + valUnit.toLocaleString('pt-BR', {minimumFractionDigits: 2});
    document.getElementById('ci_unit_val').value = valUnit;
    
    document.getElementById('ci_qtd').value = '';
    document.getElementById('ci_total').value = '';
    
    document.getElementById('modal-ci').classList.remove('hidden');
}

function closeModalCI() { 
    document.getElementById('modal-ci').classList.add('hidden'); 
}

function calcTotalCI() {
    const qtd = parseFloat(document.getElementById('ci_qtd').value) || 0;
    const unit = parseFloat(document.getElementById('ci_unit_val').value) || 0;
    const total = qtd * unit;
    
    document.getElementById('ci_total').value = 'R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits: 2});
    document.getElementById('ci_total_val').value = total;
}

// --- MODAL DETALHES AFC ---
function openModalDetalhesAFC(afc, item, loteNome) {
    document.getElementById('det_afc_id').value = afc.id;
    document.getElementById('det_lote').textContent = loteNome !== 'SEM_LOTE' ? loteNome : 'GERAL';
    document.getElementById('det_item_num').textContent = 'ITEM ' + item.numero_item;
    document.getElementById('det_numero_afc').value = afc.numero_afc;
    document.getElementById('det_catmat').value = item.codigo_catmat || '-';
    document.getElementById('det_qtd_solicitada').value = afc.qtd_solicitada;
    document.getElementById('det_qtd_entregue').value = afc.qtd_entregue || 0;
    document.getElementById('det_observacao').value = afc.observacao || '';
    
    const valUnit = parseFloat(item.valor_unitario);
    document.getElementById('det_unit').value = 'R$ ' + valUnit.toLocaleString('pt-BR', {minimumFractionDigits: 2});
    document.getElementById('det_unit_val').value = valUnit;
    
    // Popula kits
    let kitEntregue = {};
    try {
        if (afc.detalhes_entregue) {
            kitEntregue = JSON.parse(afc.detalhes_entregue);
        }
    } catch (e) {}

    const setKitVal = (id, key) => {
        const input = document.getElementById(id);
        if(input) {
            input.value = kitEntregue[key] !== undefined ? kitEntregue[key] : (afc.qtd_entregue || 0);
        }
    };

    setKitVal('entregue_oxigenador', 'oxigenador');
    setKitVal('entregue_bomba', 'bomba');
    setKitVal('entregue_hemoconcentrador', 'hemoconcentrador');
    setKitVal('entregue_tubos', 'tubos');
    setKitVal('entregue_cardioplegia', 'cardioplegia');

    calcDetalhesAFC();
    document.getElementById('modal-detalhes-afc').classList.remove('hidden');
}

function closeModalDetalhesAFC() { 
    document.getElementById('modal-detalhes-afc').classList.add('hidden'); 
}

function calcDetalhesAFC() {
    const qtdSolicitada = parseFloat(document.getElementById('det_qtd_solicitada').value) || 0;
    const qtdEntregue = parseFloat(document.getElementById('det_qtd_entregue').value) || 0;
    const valUnit = parseFloat(document.getElementById('det_unit_val').value) || 0;

    const faltaEntregar = qtdSolicitada - qtdEntregue;
    const valorTotalEntregue = qtdEntregue * valUnit;

    document.getElementById('det_falta_entregar').value = faltaEntregar < 0 ? 0 : faltaEntregar;
    document.getElementById('det_total').value = 'R$ ' + valorTotalEntregue.toLocaleString('pt-BR', {minimumFractionDigits: 2});

    document.querySelectorAll('.comp-entregar').forEach(el => el.value = qtdSolicitada);
    document.querySelectorAll('.comp-entregue').forEach(input => calcFaltaComponente(input));
}

function calcFaltaComponente(input) {
    const row = input.closest('.grid');
    const entregar = parseFloat(row.querySelector('.comp-entregar').value) || 0;
    const entregue = parseFloat(input.value) || 0;
    const falta = entregar - entregue;
    
    const inputFalta = row.querySelector('.comp-falta');
    if (inputFalta) {
        inputFalta.value = falta < 0 ? 0 : falta;
    }
}
