class CadastroCamera {
    form: HTMLFormElement;
    marcaSelect: HTMLSelectElement;
    toggleModeloExistente: HTMLInputElement;
    loadingOverlay: HTMLElement;
    btnSubmit: HTMLButtonElement;
    btnFinalizar: HTMLElement;
    btnBuscarCep: HTMLElement;
    btnBuscarCoordenadas: HTMLElement;
    tipoSelect: HTMLSelectElement;
    savedEquipmentId: string | null;

    constructor() {
        this.form = document.getElementById("formCadastroCamera") as HTMLFormElement;
        this.marcaSelect = document.getElementById("marcaSelect") as HTMLSelectElement;
        this.toggleModeloExistente = document.getElementById("toggleModeloExistente") as HTMLInputElement;
        this.loadingOverlay = document.getElementById("loadingOverlay") as HTMLElement;
        this.btnSubmit = document.getElementById("btnSubmit") as HTMLButtonElement;
        this.btnFinalizar = document.getElementById("btnFinalizar") as HTMLElement;
        this.btnBuscarCep = document.getElementById("btnBuscarCep") as HTMLElement;
        this.btnBuscarCoordenadas = document.getElementById("btnBuscarCoordenadas") as HTMLElement;
        this.tipoSelect = this.form.querySelector('[name="tipo_id"]') as HTMLSelectElement;
        this.savedEquipmentId = null;

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
            this.toggleTipoModelo();
        }

        this.initMasks();
        this.initValidation();

        if (window.aplicarUppercaseUniversal) {
            window.aplicarUppercaseUniversal(this.form);
        }
        this.initAddressTools();
        this.handleTipoChange();

        if (this.btnFinalizar) {
            this.btnFinalizar.addEventListener("click", () => this.handleFinalizar());
        }
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

        const coordField = this.form.querySelector('input[name="coordenadas"]') as HTMLInputElement;
        if (coordField) {
            coordField.addEventListener("input", function (this: HTMLInputElement) {
                this.value = this.value.replace(/[^0-9.,\- ]/g, "");
            });
        }

        const cepField = this.form.querySelector('input[name="cep"]') as HTMLInputElement;
        if (cepField) {
            cepField.addEventListener("input", function (this: HTMLInputElement) {
                const digits = this.value.replace(/\D/g, "").substring(0, 8);
                if (digits.length > 5) {
                    this.value = `${digits.slice(0, 5)}-${digits.slice(5)}`;
                } else {
                    this.value = digits;
                }
            });
        }

        const ufField = this.form.querySelector('input[name="uf"]') as HTMLInputElement;
        if (ufField) {
            ufField.addEventListener("input", function (this: HTMLInputElement) {
                this.value = this.value.replace(/[^A-Za-z]/g, "").toUpperCase().substring(0, 2);
            });
        }
    }

    initValidation(): void {
        const inputs = this.form.querySelectorAll("input, select, textarea");
        inputs.forEach((input) => {
            const el = input as HTMLInputElement;
            el.addEventListener("blur", () => {
                this.validateField(el);
            });

            el.addEventListener("input", () => {
                if (el.classList.contains("is-invalid")) {
                    this.validateField(el);
                }
            });
        });

        const ipField = this.form.querySelector('input[name="ip"]') as HTMLInputElement;
        if (ipField) {
            ipField.addEventListener("blur", () => {
                if (ipField.value) {
                    const ipRegex = /^((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
                    if (!ipRegex.test(ipField.value)) {
                        ipField.classList.add("is-invalid");
                        ipField.classList.remove("is-valid");
                    } else {
                        ipField.classList.remove("is-invalid");
                        ipField.classList.add("is-valid");
                    }
                }
            });
        }
    }

    validateField(field: HTMLElement): boolean {
        if (field.hasAttribute("required") && !(field as HTMLInputElement).value.trim()) {
            field.classList.add("is-invalid");
            field.classList.remove("is-valid");
            return false;
        } else if ((field as HTMLInputElement).value.trim()) {
            field.classList.remove("is-invalid");
            field.classList.add("is-valid");
            return true;
        }

        if (!field.hasAttribute("required") && !(field as HTMLInputElement).value.trim()) {
            field.classList.remove("is-invalid");
            field.classList.remove("is-valid");
        }

        return true;
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
                    <option value="">Selecione a marca primeiro...</option>
                </select>
                <input type="hidden" name="novo_modelo_nome" value="">
                <input type="hidden" name="modelo_id" id="modelo_id" value="">
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
                <input type="hidden" name="modelo_id" id="modelo_id" value="">
                <div class="form-text">Digite o nome do novo modelo</div>
            `;
        }

        if (window.aplicarUppercaseUniversal) {
            window.aplicarUppercaseUniversal(this.form);
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

        const secaoLPR = document.getElementById("secaoEspecificaLPR") as HTMLElement;
        const secaoDVR = document.getElementById("secaoEspecificaDVR") as HTMLElement;
        const secaoTotem = document.getElementById("secaoEspecificaTotem") as HTMLElement;

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
            const response = await fetchWithTimeout(`${window.APP_API_BASE}api_modelos_cameras&${qs.toString()}`, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            });

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

                const modeloIdInput = document.getElementById("modelo_id") as HTMLInputElement;
                if (modeloIdInput && modeloIdInput.value) {
                    select.value = modeloIdInput.value;
                }

            } else {
                select.innerHTML = '<option value="">Nenhum modelo encontrado</option>';
                window.showToast("Nenhum modelo cadastrado para esta marca. Use a opção 'novo modelo'.", "warning");
            }

        } catch (error) {
            console.error("Erro ao carregar modelos:", error);
            select.innerHTML = '<option value="">Erro ao carregar modelos</option>';
            window.showToast("Erro ao carregar modelos. Verifique sua conexão.", "danger");
        }
    }

    initAddressTools(): void {
        if (this.btnBuscarCep) {
            this.btnBuscarCep.addEventListener("click", () => this.buscarEnderecoPorCep());
        }

        if (this.btnBuscarCoordenadas) {
            this.btnBuscarCoordenadas.addEventListener("click", () => this.buscarCoordenadasPorEndereco());
        }

        const cepField = this.form?.querySelector('[name="cep"]') as HTMLInputElement;
        if (cepField) {
            cepField.addEventListener("blur", () => {
                const cep = (cepField.value || "").replace(/\D/g, "");
                if (cep.length === 8) {
                    this.buscarEnderecoPorCep({ silent: true });
                }
            });
        }
    }

    extrairTipoENomeVia(logradouroCompleto: string): { tipo: string; via: string } {
        const texto = (logradouroCompleto || "").trim();
        if (!texto) {
            return { tipo: "", via: "" };
        }

        const tipos: Record<string, string> = {
            RUA: "RUA",
            AV: "AVENIDA",
            AVENIDA: "AVENIDA",
            ALAMEDA: "ALAMEDA",
            TRAVESSA: "TRAVESSA",
            TV: "TRAVESSA",
            RODOVIA: "RODOVIA",
            ESTRADA: "ESTRADA",
        };

        const partes = texto.split(/\s+/);
        const primeiro = (partes[0] || "").replace(".", "").toUpperCase();
        const tipoNormalizado = tipos[primeiro] || "";
        const viaSemTipo = tipoNormalizado ? partes.slice(1).join(" ").trim() : texto;

        return {
            tipo: tipoNormalizado,
            via: viaSemTipo || texto
        };
    }

    montarEnderecoCompleto(): { enderecoCompleto: string; query: string; camposMinimosOk: boolean } {
        const getValue = (name: string): string => (this.form.querySelector(`[name="${name}"]`) as HTMLInputElement)?.value?.trim() || "";

        const tipoLogradouro = getValue("tipo_logradouro");
        const logradouro = getValue("logradouro");
        const numero = getValue("numero");
        const bairro = getValue("bairro");
        const cidade = getValue("cidade");
        const uf = getValue("uf").toUpperCase();
        const cep = getValue("cep");
        const complemento = getValue("complemento");

        const via = [tipoLogradouro, logradouro].filter(Boolean).join(" ").trim();
        const enderecoBase = [
            via && numero ? `${via}, ${numero}` : (via || numero),
            bairro,
            cidade,
            uf,
            cep ? `CEP ${cep}` : "",
            "BRASIL"
        ].filter(Boolean).join(" - ");

        const enderecoCompleto = [enderecoBase, complemento ? `COMPLEMENTO ${complemento}` : ""]
            .filter(Boolean)
            .join(" - ")
            .toUpperCase()
            .substring(0, 120);

        return {
            enderecoCompleto,
            query: [via, numero, bairro, cidade, uf, cep, "Brasil"].filter(Boolean).join(", "),
            camposMinimosOk: Boolean(logradouro && numero && bairro && cidade && uf)
        };
    }

    async buscarEnderecoPorCep(options: { silent?: boolean } = {}): Promise<void> {
        const { silent = false } = options;
        const cepField = this.form.querySelector('[name="cep"]') as HTMLInputElement;
        if (!cepField) return;

        const cep = (cepField.value || "").replace(/\D/g, "");
        if (cep.length !== 8) {
            if (!silent) {
                window.showToast("Informe um CEP valido com 8 digitos.", "warning");
            }
            cepField.focus();
            return;
        }

        this.showLoading(true);
        try {
            const response = await fetchWithTimeout(`${window.APP_API_BASE}api_cep_lookup&cep=${encodeURIComponent(cep)}`, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            });
            if (!response.ok) {
                throw new Error(`Erro HTTP ${response.status}`);
            }

            const payload = await response.json();
            if (!payload.success) {
                if (!silent) {
                    window.showToast(payload.error || "CEP nao encontrado.", "warning");
                }
                return;
            }

            const data = payload.data;
            const logradouroField = this.form.querySelector('[name="logradouro"]') as HTMLInputElement;
            const bairroField = this.form.querySelector('[name="bairro"]') as HTMLInputElement;
            const cidadeField = this.form.querySelector('[name="cidade"]') as HTMLInputElement;
            const ufField = this.form.querySelector('[name="uf"]') as HTMLInputElement;
            const tipoLogradouroField = this.form.querySelector('[name="tipo_logradouro"]') as HTMLSelectElement;
            const complementoField = this.form.querySelector('[name="complemento"]') as HTMLInputElement;

            const { tipo, via } = this.extrairTipoENomeVia(data.logradouro || "");

            if (tipoLogradouroField && tipo && !tipoLogradouroField.value) {
                tipoLogradouroField.value = tipo;
            }
            if (logradouroField && via) {
                logradouroField.value = via;
                this.validateField(logradouroField);
            }
            if (bairroField && data.bairro) {
                bairroField.value = data.bairro;
                this.validateField(bairroField);
            }
            if (cidadeField && data.cidade) {
                cidadeField.value = data.cidade;
                this.validateField(cidadeField);
            }
            if (ufField && data.uf) {
                ufField.value = data.uf;
                this.validateField(ufField);
            }
            if (complementoField && data.complemento && !complementoField.value) {
                complementoField.value = data.complemento;
            }

            if (!silent) {
                window.showToast("Rua e endereco preenchidos a partir do CEP.", "success");
            }
        } catch (error) {
            console.error("Erro ao buscar CEP:", error);
            if (!silent) {
                window.showToast("Erro ao consultar CEP.", "danger");
            }
        } finally {
            this.showLoading(false);
        }
    }

    async buscarCoordenadasPorEndereco(): Promise<void> {
        if (!window.APP_API_BASE) {
            window.showToast("Erro interno: base da API não definida.", "danger");
            return;
        }

        const enderecoData = this.montarEnderecoCompleto();

        const { query, camposMinimosOk } = enderecoData;
        if (!camposMinimosOk) {
            window.showToast("Para geolocalizar com precisão, preencha via, número, bairro, cidade e UF.", "warning");
            return;
        }

        const params = new URLSearchParams({ q: query });
        const requestUrl = `${window.APP_API_BASE}api_geocode&${params.toString()}`;

        this.showLoading(true);
        try {
            const response = await fetchWithTimeout(requestUrl, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (response.status === 401 || response.status === 403) {
                window.showToast("Sessão expirada ou acesso negado. Faça login novamente.", "danger");
                return;
            }

            if (!response.ok) {
                throw new Error(`Erro HTTP ${response.status}`);
            }

            const rawBody = await response.text();

            let payload: Record<string, unknown> | null = null;
            try {
                payload = JSON.parse(rawBody);
            } catch (parseError) {
                console.error("Erro ao fazer parse do JSON:", parseError);
                throw new Error("Resposta inválida da API de coordenadas.");
            }

            if (!(payload as Record<string, unknown>).success) {
                const pData = payload as { data?: { error?: string }; error?: string };
                window.showToast(pData.data?.error || (payload as Record<string, unknown>).error as string || "Endereço não encontrado para gerar coordenadas.", "warning");
                return;
            }

            const pData = payload as { data: { lat: number; lon: number } };
            const lat = Number(pData.data.lat);
            const lon = Number(pData.data.lon);
            if (!Number.isFinite(lat) || !Number.isFinite(lon)) {
                throw new Error("Coordenadas inválidas recebidas");
            }

            const coordField = this.form.querySelector('[name="coordenadas"]') as HTMLInputElement;
            if (coordField) {
                coordField.value = `${lat.toFixed(6)}, ${lon.toFixed(6)}`;
                this.validateField(coordField);
            }

            window.showToast("Coordenadas preenchidas automaticamente.", "success");
        } catch (error) {
            console.error("Erro ao buscar coordenadas:", error);
            window.showToast("Erro ao buscar coordenadas automaticas.", "danger");
        } finally {
            this.showLoading(false);
        }
    }

    async handleSubmit(e: Event): Promise<void> {
        e.preventDefault();
        e.stopPropagation();

        const allValid = this.validateAllFields();
        if (!allValid) {
            window.showToast("Por favor, corrija os campos destacados em vermelho.", "warning");

            const firstInvalid = this.form.querySelector('.is-invalid:not([type="hidden"])') as HTMLElement;
            if (firstInvalid) {
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstInvalid.focus();
            }

            return;
        }

        const formData = this.prepareFormData();

        if (!this.validateModelo(formData)) {
            return;
        }

        await this.submitToAPI(formData);
    }

    validateAllFields(): boolean {
        let isValid = true;
        const requiredFields = this.form.querySelectorAll('[required]');

        requiredFields.forEach(field => {
            const el = field as HTMLElement;
            if (el.offsetParent === null || (field as HTMLInputElement).type === 'hidden') return;
            if (!this.validateField(el)) {
                isValid = false;
            }
        });

        return isValid;
    }

    prepareFormData(): Record<string, unknown> {
        const formDataObj = new FormData(this.form);
        const data: Record<string, unknown> = {};

        for (let [key, value] of formDataObj.entries()) {
            if (value === '' && !['modelo_id', 'modelo_existente'].includes(key)) {
                continue;
            }

            const uppercaseFields = [
                'nome_local', 'mosaico', 'numero_ruas', 'serie_mac', 'patrimonio', 'novo_modelo_nome',
                'tipo_logradouro', 'logradouro', 'numero', 'bairro', 'cidade', 'uf', 'complemento', 'inscricao',
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
            'ALAMEDA': 5, 'PRACA': 6, 'PARQUE': 7, 'ESTRADA': 8,
            'ESTRADA RURAL': 8, 'CHACARA': 9, 'OUTRO': 10
        };
        const tipoLogradouro = data['tipo_logradouro'] as string | undefined;
        if (tipoLogradouro && classificacaoMap[tipoLogradouro]) {
            data['classificacao_endereco_id'] = classificacaoMap[tipoLogradouro];
        }
        delete data['tipo_logradouro'];

        if (this.toggleModeloExistente.checked) {
            const modeloSelect = document.getElementById("modeloExistenteSelect") as HTMLSelectElement;
            if (modeloSelect && modeloSelect.value) {
                data['modelo_existente'] = modeloSelect.value;
            }
            delete data['modelo_id'];
        } else {
            delete data['modelo_existente'];
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
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = metaTag ? metaTag.getAttribute("content") : "";
            if (!csrfToken) {
                this.showLoading(false);
                window.showToast('Erro: token CSRF ausente. Recarregue a página.', 'danger');
                return;
            }
            const response = await fetchWithTimeout(`${window.APP_API_BASE}api_cadastrar_cameras`, {
                method: "POST",
                credentials: 'same-origin',
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": csrfToken,
                    "Accept": "application/json"
                },
                body: JSON.stringify(formData),
            });

            const resultado = await response.json();

            if (resultado.success === true) {
                this.savedEquipmentId = resultado.data?.resource?.camera_id || null;

                if (this.savedEquipmentId) {
                    const anexosSection = document.getElementById('anexosSection');
                    if (anexosSection) {
                        anexosSection.classList.add('anexos-section-visible');
                        const list = anexosSection.querySelector('.anexos-list');
                        if (list) list.setAttribute('data-equipamento-id', this.savedEquipmentId);
                        if (window.initAnexoSection) {
                            window.initAnexoSection(anexosSection);
                        }
                        anexosSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }

                if (typeof window.showSuccessModal === 'function') {
                    window.showSuccessModal('Cadastro Realizado!', resultado.data?.resource?.message || 'Câmera cadastrada com sucesso.');
                } else {
                    window.showToast(resultado.data?.resource?.message || 'Câmera cadastrada com sucesso!', 'success');
                }
                window.showToast(`Agora você pode anexar imagens se desejar.`, "success");

                this.btnSubmit.disabled = true;
                this.btnSubmit.innerHTML = `
                    <i class="fas fa-check-circle me-2"></i>Salvo
                `;

                if (this.btnFinalizar) {
                    this.btnFinalizar.classList.remove('d-none');
                }

            } else {
                window.showToast(resultado.message || "Erro ao cadastrar câmera", "danger");

                if (resultado.data?.resource?.input) {
                    this.preencherFormulario(resultado.data.resource.input);
                }
            }
        } catch (error) {
            console.error("Erro de rede:", error);
            window.showToast("Erro de conexão com o servidor. Verifique sua rede.", "danger");
        } finally {
            this.showLoading(false);
        }
    }

    handleFinalizar(): void {
        this.form.reset();
        this.form.classList.remove("was-validated");

        const fields = this.form.querySelectorAll('.is-valid, .is-invalid');
        fields.forEach(field => {
            field.classList.remove('is-valid', 'is-invalid');
        });

        this.btnSubmit.disabled = false;
        this.btnSubmit.innerHTML = `
            <i class="fas fa-save me-2"></i>Salvar
        `;

        if (this.btnFinalizar) {
            this.btnFinalizar.classList.add('d-none');
        }

        const anexosSection = document.getElementById('anexosSection');
        if (anexosSection) {
            anexosSection.classList.remove('anexos-section-visible');
            const list = anexosSection.querySelector('.anexos-list');
            if (list) {
                list.removeAttribute('data-equipamento-id');
                list.removeAttribute('data-alarme-id');
            }
        }

        this.savedEquipmentId = null;

        window.showSuccessModal('Cadastro Finalizado!', 'O cadastro da câmera foi concluído com sucesso.');

        const firstField = this.form.querySelector('input:not([type="hidden"]), select') as HTMLElement;
        if (firstField) {
            firstField.focus();
            firstField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    preencherFormulario(dados: Record<string, unknown>): void {
        Object.keys(dados).forEach((key) => {
            const field = this.form.querySelector(`[name="${key}"]`) as HTMLInputElement;
            if (field) {
                if (field.type === 'checkbox' || field.type === 'radio') {
                    field.checked = !!dados[key];
                } else {
                    field.value = String(dados[key] ?? '');
                }
            }
        });

        const modeloId = (dados.modelo_id || dados.modelo_existente) as string | undefined;
        if (modeloId) {
            const toggle = document.getElementById("toggleModeloExistente") as HTMLInputElement;
            if (toggle) {
                toggle.checked = true;
                this.toggleTipoModelo();

                setTimeout(() => {
                    const select = document.getElementById("modeloExistenteSelect") as HTMLSelectElement;
                    if (select) {
                        select.value = modeloId;
                    }
                }, 500);
            }
        }

        this.handleTipoChange();
    }

    showLoading(show: boolean): void {
        if (!this.loadingOverlay || !this.btnSubmit) return;

        if (show) {
            this.loadingOverlay.classList.remove("is-hidden");
            this.btnSubmit.disabled = true;
            this.btnSubmit.innerHTML = `
                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                Processando...
            `;
            if (this.btnFinalizar) {
                (this.btnFinalizar as HTMLButtonElement).disabled = true;
            }
        } else {
            this.loadingOverlay.classList.add("is-hidden");
            if (!this.savedEquipmentId) {
                this.btnSubmit.disabled = false;
                this.btnSubmit.innerHTML = `
                    <i class="fas fa-save me-2"></i>Salvar
                `;
            }
            if (this.btnFinalizar) {
                (this.btnFinalizar as HTMLButtonElement).disabled = false;
            }
        }
    }
}

function onReady(): void {
    new CadastroCamera();

    const chk = document.getElementById('temAlarme') as HTMLInputElement;
    const container = document.getElementById('containerAlarmeConta') as HTMLElement;
    const input = document.getElementById('alarmeConta') as HTMLInputElement;
    const wrapper = document.getElementById('alarmeWrapper') as HTMLElement;

    if (!chk || !container || !input || !wrapper) {
        return;
    }

    const toggleAlarmeSection = (): void => {
        if (chk.checked) {
            container.classList.add('visivel');
            wrapper.classList.add('alarme-ativo');
            input.disabled = false;
            input.focus();
        } else {
            container.classList.remove('visivel');
            wrapper.classList.remove('alarme-ativo');
            input.disabled = true;
            input.value = '';
        }
    };

    chk.addEventListener('change', toggleAlarmeSection);
    toggleAlarmeSection();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', onReady);
} else {
    onReady();
}
export {};