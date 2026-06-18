class EditarCamera {
    form: HTMLFormElement;
    marcaSelect: HTMLSelectElement;
    toggleModeloExistente: HTMLInputElement;
    loadingOverlay: HTMLElement;
    btnSubmit: HTMLButtonElement;
    tipoSelect: HTMLSelectElement;

    constructor() {
        this.form = document.getElementById("formEditarCamera") as HTMLFormElement;
        this.marcaSelect = document.getElementById("marcaSelect") as HTMLSelectElement;
        this.toggleModeloExistente = document.getElementById("toggleModeloExistente") as HTMLInputElement;
        this.loadingOverlay = document.getElementById("loadingOverlay") as HTMLElement;
        this.btnSubmit = document.getElementById("btnSubmit") as HTMLButtonElement;
        this.tipoSelect = this.form.querySelector('[name="tipo_id"]') as HTMLSelectElement;

        this.init();
    }

    init(): void {
        if (!this.form) {
            return;
        }

        this.form.addEventListener("submit", (e: Event) => this.handleSubmit(e));

        if (this.marcaSelect) {
            this.marcaSelect.addEventListener("change", () => this.handleMarcaChange());
        }

        if (this.tipoSelect) {
            this.tipoSelect.addEventListener("change", () => this.handleTipoChange());
        }

        const tipoCameraSelect = this.form.querySelector('[name="tipo_camera"]') as HTMLSelectElement;
        if (tipoCameraSelect) {
            tipoCameraSelect.addEventListener("change", () => this.handleTipoChange());
        }

        if (this.toggleModeloExistente) {
            this.toggleModeloExistente.addEventListener("change", () => this.toggleTipoModelo());
        }

        this.initMasks();
        if (window.aplicarUppercaseUniversal) {
            window.aplicarUppercaseUniversal(this.form);
        }
        this.initOrigemInscricaoSync();
        this.handleTipoChange();
    }

    initOrigemInscricaoSync(): void {
        const origemSelect = this.form.querySelector('[name="origem_link_id"]') as HTMLSelectElement;
        const inscricaoInput = this.form.querySelector('[name="inscricao"]') as HTMLInputElement;
        if (!origemSelect || !inscricaoInput) return;

        origemSelect.addEventListener("change", () => {
            const selected = origemSelect.options[origemSelect.selectedIndex];
            const inscricao = ((selected as HTMLOptionElement)?.dataset?.inscricao || "").trim();
            if (inscricao) {
                inscricaoInput.value = inscricao.toUpperCase();
            }
        });

        inscricaoInput.addEventListener("input", () => {
            const typed = inscricaoInput.value.trim().toUpperCase();
            const selected = origemSelect.options[origemSelect.selectedIndex] as HTMLOptionElement;
            const current = (selected?.dataset?.inscricao || "").trim().toUpperCase();
            if (origemSelect.value && typed && current && typed !== current) {
                origemSelect.value = "";
            }
        });
    }

    initMasks(): void {
        const ipField = this.form.querySelector('input[name="ip"]') as HTMLInputElement;
        if (ipField) {
            ipField.addEventListener("input", function (this: HTMLInputElement) {
                let value = this.value.replace(/[^0-9.]/g, "");
                const parts = value.split(".");
                if (parts.length > 4) value = parts.slice(0, 4).join(".");
                this.value = value;
            });
        }

        const macField = this.form.querySelector('input[name="serie_mac"]') as HTMLInputElement;
        if (macField) {
            macField.addEventListener("input", function (this: HTMLInputElement) {
                let value = this.value.replace(/[^0-9A-Fa-f]/g, "").toUpperCase();
                if (value.length > 12) value = value.substring(0, 12);

                let formatted = "";
                for (let i = 0; i < value.length; i++) {
                    if (i > 0 && i % 2 === 0) formatted += ":";
                    formatted += value[i];
                }
                this.value = formatted;
            });
        }
    }

    toggleTipoModelo(): void {
        if (!this.toggleModeloExistente) return;

        const usarExistente = this.toggleModeloExistente.checked;
        const modeloContainer = document.getElementById("modeloContainer");

        if (!modeloContainer) {
            return;
        }

        if (usarExistente) {
            modeloContainer.innerHTML = `
                <select name="modelo_existente" id="modeloExistenteSelect" class="form-select" required>
                    <option value="">Carregando modelos...</option>
                </select>
                <input type="hidden" name="novo_modelo_nome" value="">
                <div class="form-text">Selecione um modelo da lista</div>
            `;

            if (this.marcaSelect && this.marcaSelect.value) {
                this.carregarModelosExistentes(this.marcaSelect.value);
            }
        } else {
            modeloContainer.innerHTML = `
                <input type="text" name="novo_modelo_nome" class="form-control" required
                       placeholder="Ex: IPC-HDW5842T-ZE, DS-2CD2143G0-I">
                <input type="hidden" name="modelo_existente" value="">
                <div class="form-text">Digite o nome do novo modelo</div>
            `;
        }
    }

    handleMarcaChange(): void {
        const marcaId = this.marcaSelect.value;

        if (this.toggleModeloExistente.checked && marcaId) {
            this.carregarModelosExistentes(marcaId);
        }
    }

    handleTipoChange(): void {
        const tipoId = this.tipoSelect ? parseInt(this.tipoSelect.value) : 0;
        const tipoCameraSelect = this.form.querySelector('[name="tipo_camera"]') as HTMLSelectElement;
        const tipoCameraId = tipoCameraSelect ? parseInt(tipoCameraSelect.value) : 0;

        const secaoLPR = document.getElementById("secaoEditLPR") as HTMLElement;
        const secaoDVR = document.getElementById("secaoEditDVR") as HTMLElement;
        const secaoTotem = document.getElementById("secaoEditTotem") as HTMLElement;

        if (secaoLPR) secaoLPR.style.display = (tipoId === 2 || tipoCameraId === 3) ? "" : "none";
        if (secaoDVR) secaoDVR.style.display = tipoId === 3 ? "" : "none";
        if (secaoTotem) secaoTotem.style.display = tipoId === 4 ? "" : "none";
    }

    async carregarModelosExistentes(marcaId: string): Promise<void> {
        const select = document.getElementById("modeloExistenteSelect") as HTMLSelectElement;
        if (!select) return;

        try {
            select.disabled = true;
            select.innerHTML = '<option value="">Carregando modelos...</option>';

            const tipoField = this.form.querySelector('[name="tipo_id"]') as HTMLInputElement;
            const tipoId = tipoField ? tipoField.value : '';
            const qs = new URLSearchParams({ marca_id: marcaId });
            if (tipoId) {
                qs.set('tipo_id', tipoId);
            }
            const response = await fetchWithTimeout(`${window.APP_API_BASE}api_modelos_cameras&${qs.toString()}`);

            if (!response.ok) {
                throw new Error(`Erro HTTP: ${response.status}`);
            }

            const data = await response.json();

            if (data.success && data.data?.modelos && data.data.modelos.length > 0) {
                select.innerHTML = '<option value="">Selecione um modelo...</option>';
                data.data.modelos.forEach((modelo: Record<string, unknown>) => {
                    const option = document.createElement("option");
                    option.value = String(modelo.id);
                    option.textContent = modelo.nome as string;
                    select.appendChild(option);
                });

                select.disabled = false;
            } else {
                select.innerHTML = '<option value="">Nenhum modelo encontrado</option>';
                window.showToast("Nenhum modelo cadastrado para esta marca. Use a opção 'novo modelo'.", "warning");
            }

        } catch (error) {
            console.error("Erro ao carregar modelos:", error);
            select.innerHTML = '<option value="">Erro ao carregar modelos</option>';
        }
    }

    async handleSubmit(e: Event): Promise<void> {
        e.preventDefault();
        e.stopPropagation();

        if (!this.validarCamposObrigatorios()) {
            window.showToast("Por favor, preencha todos os campos obrigatórios.", "warning");
            return;
        }

        const formData = this.prepareFormData();

        if (!this.validateModelo(formData)) {
            return;
        }

        await this.submitToAPI(formData);
    }

    validarCamposObrigatorios(): boolean {
        const requiredFields = this.form.querySelectorAll('[required]');
        let allValid = true;

        requiredFields.forEach(field => {
            const el = field as HTMLInputElement;
            if (el.type === 'hidden' || (el as HTMLElement).offsetParent === null) {
                return;
            }
            if (!el.value || !el.value.trim()) {
                el.classList.add('is-invalid');
                allValid = false;
            } else {
                el.classList.remove('is-invalid');
            }
        });

        return allValid;
    }

    prepareFormData(): Record<string, unknown> {
        const formDataObj = new FormData(this.form);
        const data: Record<string, unknown> = {};

        const getValue = (name: string): string => (this.form.querySelector(`[name="${name}"]`) as HTMLInputElement)?.value?.trim() || "";
        const tipoLogradouro = getValue("tipo_logradouro");

        for (let [key, value] of formDataObj.entries()) {
            if (value === '' && !['id', 'edicao'].includes(key)) {
                continue;
            }

            const uppercaseFields = [
                'nome_local', 'mosaico',
                'serie_mac', 'patrimonio', 'novo_modelo_nome', 'inscricao',
                'tipo_logradouro', 'logradouro', 'numero', 'bairro', 'cidade', 'uf', 'complemento',
                'lpr_sentido_via', 'lpr_faixa_monitorada', 'dvr_modelo'
            ];

            if (uppercaseFields.includes(key) && typeof value === "string") {
                data[key] = value.toUpperCase().trim();
            } else {
                data[key] = value;
            }
        }

        const classificacaoMap: Record<string, number> = {
            'RUA': 1, 'AVENIDA': 2, 'TRAVESSA': 3, 'RODOVIA': 4,
            'ALAMEDA': 5, 'PRACA': 6, 'PARQUE': 7, 'ESTRADA RURAL': 8,
            'CHACARA': 9, 'OUTRO': 10
        };
        if (tipoLogradouro && classificacaoMap[tipoLogradouro.toUpperCase()]) {
            data['classificacao_endereco_id'] = classificacaoMap[tipoLogradouro.toUpperCase()];
        }

        return data;
    }

    validateModelo(_formData: Record<string, unknown>): boolean {
        if (this.toggleModeloExistente.checked) {
            const modeloSelect = document.getElementById("modeloExistenteSelect") as HTMLSelectElement;
            if (!modeloSelect || !modeloSelect.value) {
                window.showToast("Selecione um modelo existente.", "warning");
                modeloSelect?.focus();
                return false;
            }
        } else {
            const novoModeloInput = this.form.querySelector('input[name="novo_modelo_nome"]') as HTMLInputElement;
            if (!novoModeloInput || !novoModeloInput.value.trim()) {
                window.showToast("Informe o nome do novo modelo.", "warning");
                novoModeloInput?.focus();
                return false;
            }

            if (novoModeloInput.value.trim().length < 2) {
                window.showToast("O nome do modelo deve ter pelo menos 2 caracteres.", "warning");
                novoModeloInput.focus();
                return false;
            }
        }

        return true;
    }

    async submitToAPI(formData: Record<string, unknown>): Promise<void> {
        this.showLoading(true);

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
            const response = await fetchWithTimeout(`${window.APP_API_BASE}api_editar_camera`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": csrfToken,
                },
                body: JSON.stringify(formData),
            });

            const resultado = await response.json();

            if (resultado.success === true) {
                window.showToast(resultado.data?.message || resultado.message, 'success');

                this.form.classList.remove("was-validated");

                const fields = this.form.querySelectorAll('.is-valid, .is-invalid');
                fields.forEach(field => {
                    field.classList.remove('is-valid', 'is-invalid');
                });

                const firstField = this.form.querySelector('input:not([type="hidden"]), select') as HTMLElement;
                if (firstField) {
                    firstField.focus();
                    firstField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }

            } else {
                window.showToast(resultado.message || "Erro ao editar câmera", "danger");
            }
        } catch (error) {
            console.error("Erro de rede:", error);
            window.showToast("Erro de conexão com o servidor.", "danger");
        } finally {
            this.showLoading(false);
        }
    }

    showLoading(show: boolean): void {
        if (!this.loadingOverlay || !this.btnSubmit) return;

        if (show) {
            this.loadingOverlay.classList.remove("is-hidden");
            this.btnSubmit.disabled = true;
            this.btnSubmit.innerHTML = `
                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                Salvando...
            `;
        } else {
            this.loadingOverlay.classList.add("is-hidden");
            this.btnSubmit.disabled = false;
            this.btnSubmit.innerHTML = `
                <i class="fas fa-save me-2"></i>Salvar Alterações
            `;
        }
    }
}

function onReady(): void {
    new EditarCamera();
    initCameraEditor();
}

async function initCameraEditor(): Promise<void> {
    const form = document.getElementById('formEditarCamera') as HTMLFormElement;
    const idInput = document.getElementById('camera_id') as HTMLInputElement;
    const cameraSelector = document.getElementById('cameraSelector') as HTMLSelectElement;
    const filtroBuscaCamera = document.getElementById('filtroBuscaCamera') as HTMLInputElement;
    const btnBuscarCamera = document.getElementById('btnBuscarCamera') as HTMLElement;
    const marcaSelect = document.getElementById('marcaSelect') as HTMLSelectElement;
    const params = new URLSearchParams(window.location.search);
    const idFromUrl = params.get('id');
    let cameras: Array<Record<string, unknown>> = [];
    let allCameras: Array<Record<string, unknown>> = [];

    const setValue = (name: string, value: unknown): void => {
        const field = form.querySelector(`[name="${name}"]`) as HTMLInputElement;
        if (field && value !== undefined && value !== null) {
            field.value = String(value);
        }
    };

    const escapeHtml = (value: unknown): string =>
        String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

    const fetchCameras = async (params: Record<string, unknown> = {}): Promise<Record<string, unknown>> => {
        const qs = new URLSearchParams();
        if (params.id) {
            qs.set('id', String(params.id));
        }
        if (params.busca) {
            qs.set('busca', String(params.busca));
        }
        if (params.page) {
            qs.set('page_num', String(params.page));
        }
        if (params.perPage) {
            qs.set('per_page', String(params.perPage));
        }

        const response = await fetchWithTimeout(`${window.APP_API_BASE}api_cameras&${qs.toString()}`);
        const payload = await response.json();
        if (!response.ok || !payload.success) {
            throw new Error(payload.error || 'Erro ao carregar cameras');
        }
        return payload;
    };

    const renderOptions = (list: Array<Record<string, unknown>>): void => {
        if (!Array.isArray(list) || list.length === 0) {
            cameraSelector.innerHTML = '<option value="">Nenhuma camera encontrada</option>';
            return;
        }

        cameraSelector.innerHTML = '<option value="">Selecione...</option>' + list.map((camera) => {
            const descricao = camera.descricao as string || `EQUIPAMENTO ${camera.id}`;
            const serie = (camera.serie_mac as string) || '-';
            const ip = (camera.ip as string) || '-';
            const label = `${camera.id} - ${descricao} | SERIE: ${serie} | IP: ${ip}`;
            return `<option value="${escapeHtml(camera.id)}">${escapeHtml(label)}</option>`;
        }).join('');
    };

    const applyCameraToForm = (camera: Record<string, unknown> | null): void => {
        if (!camera) {
            idInput.value = '';
            return;
        }

        idInput.value = String(camera.id ?? '');

        const anexosSection = document.getElementById('anexosSection');
        if (anexosSection) {
            const list = anexosSection.querySelector('.anexos-list');
            if (list) {
                list.setAttribute('data-equipamento-id', String(camera.id || ''));
            }
            if (camera.id) {
                if (window.initAnexoSection) {
                    window.initAnexoSection(anexosSection);
                }
            } else {
                const list2 = anexosSection.querySelector('.anexos-list');
                if (list2) list2.innerHTML = '';
            }
        }

        setValue('nome_local', camera.descricao ?? '');
        setValue('ip', camera.ip ?? '');
        setValue('serie_mac', camera.serie_mac ?? '');
        setValue('data_instalacao', camera.data_instalacao ?? '');
        setValue('observacao', camera.observacao ?? '');
        setValue('status_id', camera.status_id ?? '');
        setValue('tipo_id', camera.tipo_id ?? '');
        setValue('tipo_camera', camera.tipo_camera_id ?? '');
        setValue('local_id', camera.local_id ?? '');
        setValue('secretaria_id', camera.secretaria_id ?? '');
        setValue('marca_id', camera.marca_id ?? '');
        setValue('transmissao_id', camera.transmissao_id ?? '');
        setValue('origem_link_id', camera.origem_link_id ?? '');
        setValue('inscricao', camera.inscricao ?? '');
        setValue('patrimonio', camera.patrimonio ?? '');
        setValue('mosaico', camera.mosaico ?? '');
        setValue('coordenadas', camera.coordenadas ?? '');
        setValue('logradouro', camera.local_logradouro ?? '');
        setValue('bairro', camera.local_bairro ?? '');
        setValue('cidade', camera.local_cidade ?? '');
        setValue('uf', camera.local_uf ?? '');
        setValue('cep', camera.local_cep ?? '');
        setValue('numero', camera.local_numero ?? '');
        if (camera.tipo_logradouro) {
            const tipoSelect = form.querySelector('[name="tipo_logradouro"]') as HTMLSelectElement;
            if (tipoSelect) tipoSelect.value = String(camera.tipo_logradouro);
        }
        setValue('descricao_posicao', camera.descricao_posicao ?? '');
        setValue('lpr_sentido_via', camera.lpr_sentido_via ?? '');
        setValue('lpr_faixa_monitorada', camera.lpr_faixa_monitorada ?? '');
        setValue('lpr_url_acesso', camera.lpr_url_acesso ?? '');
        setValue('dvr_modelo', camera.dvr_modelo ?? '');
        setValue('dvr_canais', camera.dvr_canais ?? '');
        setValue('dvr_armazenamento_tb', camera.dvr_armazenamento_tb ?? '');
        setValue('totem_quantidade_cameras', camera.totem_quantidade_cameras ?? '');
        const lprNoturnaField = form.querySelector('[name="lpr_leitura_noturna"]') as HTMLInputElement;
        if (lprNoturnaField) lprNoturnaField.checked = !!camera.lpr_leitura_noturna;
        const totemFacialField = form.querySelector('[name="totem_tem_facial"]') as HTMLInputElement;
        if (totemFacialField) totemFacialField.checked = !!camera.totem_tem_facial;
        const totemLprField = form.querySelector('[name="totem_tem_lpr"]') as HTMLInputElement;
        if (totemLprField) totemLprField.checked = !!camera.totem_tem_lpr;

        const tipoSelect = form.querySelector('[name="tipo_id"]') as HTMLSelectElement;
        if (tipoSelect) tipoSelect.dispatchEvent(new Event('change'));

        const temAlarmeField = form.querySelector('[name="tem_alarme"]') as HTMLInputElement;
        if (temAlarmeField) temAlarmeField.checked = !!camera.tem_alarme;
        const alarmeContaField = form.querySelector('[name="alarme_conta"]') as HTMLInputElement;
        if (alarmeContaField) alarmeContaField.value = String(camera.alarme_conta || '');
        if (temAlarmeField) temAlarmeField.dispatchEvent(new Event('change'));

        form.querySelectorAll('input[type="text"], input[type="search"], input[type="tel"], input[type="url"], textarea, input:not([type])').forEach(function (f: Element) {
            const field = f as HTMLInputElement;
            if (field.value && field.readOnly === false) {
                const pos = field.selectionStart;
                field.value = field.value.toUpperCase();
                if (document.activeElement === field) {
                    field.setSelectionRange(pos, pos);
                }
            }
        });

        const tipoField = form.querySelector('[name="tipo_id"]') as HTMLSelectElement;
        if (tipoField) {
            tipoField.dispatchEvent(new Event('change'));
        }

        if (marcaSelect && camera.marca_id) {
            marcaSelect.value = String(camera.marca_id);
            marcaSelect.dispatchEvent(new Event('change'));
            const applyModelWhenReady = (tries = 0): void => {
                const modeloSelect = document.getElementById('modeloExistenteSelect') as HTMLSelectElement;
                if (modeloSelect && modeloSelect.options.length > 1) {
                    modeloSelect.value = String(camera.modelo_id ?? '');
                    return;
                }
                if (tries < 8) {
                    setTimeout(() => applyModelWhenReady(tries + 1), 120);
                }
            };
            applyModelWhenReady();
        }
    };

    const applyFilter = async (): Promise<void> => {
        const term = (filtroBuscaCamera.value || '').trim();
        if (!term) {
            cameras = allCameras.slice();
            renderOptions(cameras);
            return;
        }

        try {
            const payload = await fetchCameras({ busca: term, page: 1, perPage: 200 });
            cameras = Array.isArray(payload.data) ? payload.data as Array<Record<string, unknown>> : [];
            renderOptions(cameras);
        } catch (e) {
            console.error('Erro ao buscar cameras:', e);
            cameraSelector.innerHTML = '<option value="">Erro ao buscar cameras</option>';
        }
    };

    const filtrarLocalmente = (): void => {
        const term = (filtroBuscaCamera.value || '').trim().toLowerCase();
        if (!term) {
            cameras = allCameras.slice();
            renderOptions(cameras);
            return;
        }
        cameras = allCameras.filter(camera => {
            const descricao = (camera.descricao as string || '').toLowerCase();
            const ip = (camera.ip as string || '').toLowerCase();
            const tipo = (camera.tipo_camera_nome as string || '').toLowerCase();
            return descricao.includes(term) || ip.includes(term) || tipo.includes(term);
        });
        renderOptions(cameras);
    };

    try {
        const payload = await fetchCameras({ page: 1, perPage: 200 });
        if (!Array.isArray(payload.data)) {
            cameraSelector.innerHTML = '<option value="">Erro ao carregar cameras</option>';
            return;
        }

        allCameras = payload.data as Array<Record<string, unknown>>;
        cameras = allCameras.slice();
        renderOptions(cameras);

        if (idFromUrl) {
            try {
                const idPayload = await fetchCameras({ id: idFromUrl, page: 1, perPage: 1 });
                const fromUrl = Array.isArray(idPayload.data) ? (idPayload.data as Array<Record<string, unknown>>)[0] : null;
                if (fromUrl) {
                    const already = cameras.find((c: Record<string, unknown>) => String(c.id) === String(fromUrl.id));
                    if (!already) {
                        cameras = [fromUrl].concat(cameras);
                        renderOptions(cameras);
                    }
                    cameraSelector.value = String(fromUrl.id);
                    applyCameraToForm(fromUrl);
                }
            } catch (inner) {
                console.error('Erro ao carregar camera por ID:', inner);
            }
        }

        cameraSelector.addEventListener('change', () => {
            const selectedId = cameraSelector.value;
            const selectedCamera = cameras.find((c) => String(c.id) === String(selectedId));
            applyCameraToForm(selectedCamera ?? null);
        });

        btnBuscarCamera.addEventListener('click', async () => {
            await applyFilter();
        });
        filtroBuscaCamera.addEventListener('keydown', async (event: KeyboardEvent) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                await applyFilter();
            }
        });
        filtroBuscaCamera.addEventListener('input', filtrarLocalmente);

        const chkAlarme = document.getElementById('temAlarme') as HTMLInputElement;
        const containerAlarme = document.getElementById('containerAlarmeConta') as HTMLElement;
        const inputAlarme = document.getElementById('alarmeConta') as HTMLInputElement;
        const wrapperAlarme = document.getElementById('alarmeWrapper') as HTMLElement;
        if (chkAlarme && containerAlarme && inputAlarme && wrapperAlarme) {
            chkAlarme.addEventListener('change', () => {
                if (chkAlarme.checked) {
                    containerAlarme.classList.add('visivel');
                    wrapperAlarme.classList.add('alarme-ativo');
                    inputAlarme.disabled = false;
                    inputAlarme.focus();
                } else {
                    containerAlarme.classList.remove('visivel');
                    wrapperAlarme.classList.remove('alarme-ativo');
                    inputAlarme.disabled = true;
                    inputAlarme.value = '';
                }
            });
        }
    } catch (e) {
        console.error('Erro ao carregar camera para edicao:', e);
        cameraSelector.innerHTML = '<option value="">Erro ao carregar cameras</option>';
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', onReady);
} else {
    onReady();
}
export {};