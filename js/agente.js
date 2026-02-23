/**
 * Agente Licitatório IA - Javascript Integrado
 */

const anexoId = document.getElementById('anexo_id') ? document.getElementById('anexo_id').value : null;

// Prompts baseados no clique do usuário (Ações Rápidas)
const promptsRapidos = {
    'resumo': "Aja como um pregoeiro ou analista de licitação sênior. Resuma os pontos mais vitais deste edital. Inclua: 1) O objeto exato. 2) Datas, Prazos e Horários importantes de forma destacada. 3) Quais são os itens listados para compra. 4) Se há algo fora do padrão ou muito restritivo (exigências absurdas técnicas ou documentais).",

    'riscos': "Aja como um advogado de direito administrativo especialista em licitações públicas. Liste ponto a ponto os 'Riscos' ou armadilhas que você encontrou nesse edital. Foque em restrições indevidas à competitividade, exigências raras em atestados, documentação de habilitação muito rigorosa, prazos irreais, ou problemas no Termo de Referência.",

    'esclarecimento': "Crie uma minuta de Pedido de Esclarecimento formal (em padrão ofício) direcionada ao pregoeiro ou comissão de licitação (descubra pelo edital quem é o órgão se possível). Leia o edital para tentar encontrar alguma contradição ou ambiguidade óbvia nos prazos ou no termo de referência e use isso como base. Deixe lacunas '[PREENCHER]' caso seja um edital perfeito, apenas mostrando a estrutura da peça.",

    'impugnacao': "Escreva uma peça de Impugnação ao Edital completa, agressiva administrativamente, mas respeitosa. Baseie-se na Lei 14.133/2021 (se for a lei usada) ou na regra do edital. Ataque alguma regra que pareça excessivamente restritiva encontrada no texto do anexo. Se não encontrar algo restritivo, monte a peça deixando em aberto os fundamentos fáticos para o cliente preencher.",

    'intencao_recurso': "Crie o texto de 'Intenção de Recurso' sucinto (máximo de 3 a 5 linhas). Este é o texto que eu irei colar no chat do Comprasnet / Portal de Compras logo após a fase de lances/habilitação para garantir meu direito de recorrer depois. O fundamento principal será que a empresa vencedora falhou em cumprir os requisitos de qualificação técnica lidos neste edital.",

    'recurso': "Elabore uma peça completa de Recurso Administrativo direcionada à comissão deste órgão (tente achar o nome no edital). Use linguagem jurídica formal e cite doutrina ou jurisprudência (TCU/TCE) básica sobre competitividade. Estruture: 1) Dos Fatos, 2) Do Direito e da Previsão Editalícia, 3) Dos Pedidos. Defenda a desclassificação do concorrente e minha vitória."
};

function enviarPrompt(tipoAcao) {
    if (!anexoId) return;
    const promptTexto = promptsRapidos[tipoAcao];
    if (promptTexto) {
        solicitarIA(promptTexto);
    }
}

function enviarPromptLivre() {
    if (!anexoId) return;
    const inputArea = document.getElementById('chat_input');
    const promptTexto = inputArea.value.trim();
    if (promptTexto.length < 5) {
        alert("Por favor, digite uma pergunta ou solicitação um pouco mais detalhada.");
        return;
    }

    // Constrói o contexto para garantir o tom do assistente
    const promptCompleto = `Baseado no edital/anexo fornecido, atue como um especialista em licitações e atenda ao seguinte pedido do usuário: "${promptTexto}"`;

    solicitarIA(promptCompleto);
}

function analisarCatalogo() {
    if (!anexoId) return;
    const descritivo = document.getElementById('descritivo_produto').value.trim();
    const arquivoInput = document.getElementById('arquivo_catalogo');

    if (descritivo.length < 5) {
        alert("Por favor, preencha o descritivo técnico exigido no edital.");
        return;
    }

    if (arquivoInput.files.length === 0) {
        alert("Por favor, anexe o catálogo do produto (PDF, Doc ou txt).");
        return;
    }

    const arquivo = arquivoInput.files[0];
    const formData = new FormData();
    formData.append('anexo_id', anexoId);
    formData.append('descritivo', descritivo);
    formData.append('catalogo', arquivo);

    // Prompt interno para a IA
    const promptTexto = `Aja como um pregoeiro especialista em análise de produtos e um advogado de direito administrativo.
Eu estou enviando DADOS DE DOIS DOCUMENTOS:
Documento 1: O edital da licitação (já anexado no contexto).
Documento 2: O catálogo do produto de um concorrente (estou enviando agora).

A exigência do edital/Termo de Referência para este produto é a seguinte:
"${descritivo}"

Por favor, faça uma análise minuciosa cruzando a exigência do edital com as informações do catálogo do concorrente. 
Verifique se o produto do catálogo ATENDE ou NÃO ATENDE aos requisitos.
Se houver qualquer divergência, detalhe qual é. 
Ao final, gere uma peça de IMPUGNAÇÃO ou RECURSO ADMINISTRATIVO argumentando a desclassificação do concorrente com base nessa divergência e na regra fática apresentada. Especifique os fatos.`;

    formData.append('prompt', promptTexto);

    document.getElementById('loading_overlay').classList.remove('hidden');
    document.getElementById('btn_copy').classList.add('hidden');
    document.getElementById('resposta_content').innerHTML = ''; // limpa

    fetch('api_agente.php', {
        method: 'POST',
        body: formData // Envia como formulário multipart
    })
        .then(response => {
            if (!response.ok) throw new Error("Erro de comunicação com o servidor.");
            return response.json();
        })
        .then(data => tratarRespostaIA(data))
        .catch(error => tratarErroIA(error));
}

function solicitarIA(prompt) {
    // 1. Mostrar loading
    document.getElementById('loading_overlay').classList.remove('hidden');
    document.getElementById('btn_copy').classList.add('hidden');
    document.getElementById('resposta_content').innerHTML = ''; // limpa

    // 2. Fazer fetch API interna
    fetch('api_agente.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            anexo_id: anexoId,
            prompt: prompt
        })
    })
        .then(response => {
            if (!response.ok) throw new Error("Erro de comunicação com o servidor.");
            return response.json();
        })
        .then(data => tratarRespostaIA(data))
        .catch(error => tratarErroIA(error));
}

function tratarRespostaIA(data) {
    document.getElementById('loading_overlay').classList.add('hidden');

    if (data.status === 'success') {
        const respostaArea = document.getElementById('resposta_content');

        // Usa marked.js para converter de Markdown do Gemini para HTML bonito
        if (typeof marked !== 'undefined') {
            respostaArea.innerHTML = marked.parse(data.resposta);
        } else {
            // Fallback se o marked falhar em carregar
            const textArea = document.createElement('div');
            textArea.innerHTML = data.resposta.replace(/\n/g, '<br>');
            respostaArea.appendChild(textArea);
        }

        // Exibir botão de cópia
        document.getElementById('btn_copy').classList.remove('hidden');
        document.getElementById('btn_copy').innerHTML = '<i class="fas fa-copy"></i> Copiar Texto';

    } else {
        Swal.fire('Erro', data.message || 'Erro desconhecido ao processar.', 'error');
        document.getElementById('resposta_content').innerHTML = `
        <div class="text-center text-red-500 p-8">
            <i class="fas fa-exclamation-circle text-4xl mb-4"></i>
            <h3 class="text-xl font-bold">Falha no Processamento</h3>
            <p class="mt-2 text-gray-600">${data.message || 'Tente novamente.'}</p>
        </div>
    `;
    }
}

function tratarErroIA(error) {
    document.getElementById('loading_overlay').classList.add('hidden');
    document.getElementById('resposta_content').innerHTML = `
        <div class="text-center text-red-500 p-8">
            <i class="fas fa-wifi text-4xl mb-4"></i>
            <h3 class="text-xl font-bold">Erro de Conexão</h3>
            <p class="mt-2 text-gray-600">${error.message}</p>
        </div>
`;
}


function copiarResposta() {
    const content = document.getElementById('resposta_content');
    if (!content) return;

    // Copiar o texto puramente (sem HTML, mas preservando quebras) para jogar no word
    const plainText = content.innerText;

    navigator.clipboard.writeText(plainText).then(() => {
        const btn = document.getElementById('btn_copy');
        btn.innerHTML = '<i class="fas fa-check text-green-600"></i> Copiado!';
        setTimeout(() => {
            btn.innerHTML = '<i class="fas fa-copy"></i> Copiar Texto';
        }, 3000);
    }).catch(err => {
        console.error('Falha ao copiar:', err);
        alert('Seu navegador não suporta cópia automática.');
    });
}

function carregarHistorico(respostaJson) {
    const respostaArea = document.getElementById('resposta_content');

    // Decodifica JSON string para ter certeza que quebras de linha funcionam (PHP passa via json_encode)
    let respostaText = respostaJson;

    // Usa marked.js para converter de Markdown do Gemini para HTML bonito
    if (typeof marked !== 'undefined') {
        respostaArea.innerHTML = marked.parse(respostaText);
    } else {
        // Fallback se o marked falhar em carregar
        const textArea = document.createElement('div');
        textArea.innerHTML = respostaText.replace(/\n/g, '<br>');
        respostaArea.appendChild(textArea);
    }

    // Exibir botão de cópia
    const btnCopy = document.getElementById('btn_copy');
    if (btnCopy) {
        btnCopy.classList.remove('hidden');
        btnCopy.innerHTML = '<i class="fas fa-copy"></i> Copiar Texto';
    }

    // Rolagem suave mobile
    respostaArea.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
