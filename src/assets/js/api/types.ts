// =========================================================================
// API Response Envelope Types
// =========================================================================

export interface ApiMeta {
  timestamp: string;
  version: string;
  request_id: string;
  timezone: string;
}

export interface ApiResponse<T = unknown> {
  success: boolean;
  code: string;
  message: string;
  data: T;
  meta: ApiMeta;
  error?: string;
}

export interface Pagination {
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
  has_next: boolean;
  has_prev: boolean;
}

export interface PaginatedResponse<T> extends ApiResponse<T[]> {
  pagination: Pagination;
}

export interface ApiErrorResponse {
  success: false;
  code: string;
  message: string;
  data: Record<string, unknown>;
  meta: ApiMeta;
}

// =========================================================================
// Entity Data Types
// =========================================================================

export interface CameraData {
  id: number;
  descricao: string;
  em_manutencao: 0 | 1;
  ip: string;
  numero_serie: string;
  serie_mac: string;
  data_instalacao: string;
  observacao: string;
  local_id: number;
  secretaria_id: number;
  status_id: number;
  modelo_id: number;
  marca_id: number;
  tipo_id: number;
  tipo_camera_id: number;
  tipo_camera_nome: string;
  transmissao_id: number;
  origem_link_id: number;
  patrimonio: string;
  regiao_id: number;
  procedimento_id: number;
  regiao_nome: string;
  procedimento_nome: string;
  mosaico: string;
  coordenadas: string;
  numero_ruas: number;
  inscricao: string;
  status_nome: string;
  local_nome: string;
  local_logradouro: string;
  local_bairro: string;
  local_cidade: string;
  local_uf: string;
  local_cep: string;
  local_numero: string;
  descricao_posicao: string;
  tipo_logradouro: string;
  tem_alarme: 0 | 1;
  alarme_conta: number;
  secretaria_nome: string;
  modelo_nome: string;
  lpr_sentido_via: string;
  lpr_faixa_monitorada: string;
  lpr_url_acesso: string;
  lpr_leitura_noturna: string;
  dvr_modelo: string;
  dvr_canais: number;
  dvr_armazenamento_tb: string;
  totem_quantidade_cameras: number;
  totem_tem_facial: 0 | 1;
  totem_tem_lpr: 0 | 1;
}

export interface AlarmeData {
  id: number;
  conta: number;
  status: string;
  regiao: string;
  local: string;
  endereco: string;
  numero: string;
  pgm1: string;
  pgm2: string;
  mac: string;
  ip: string;
  integracao: string;
  camera_gm: string;
  quant_camera_gm: number;
  ip_dvr: string;
  cameras_dvr: string;
  modelo_alarme_id: number;
  quant_repetidor: number;
  qtde_sensores: number;
  documentacao: string;
  monitorada: string;
  numero_sei: string;
  data_atualizacao: string;
  observacao: string;
  modelo_central: string;
}

export interface LocalData {
  id: number;
  nome: string;
  logradouro: string;
  bairro: string;
  cidade: string;
  uf: string;
  cep: string;
  numero: string;
  descricao_posicao: string;
  regiao_id: number;
  secretaria_id: number;
  latitude: string;
  longitude: string;
  tipo_local_id: number;
  tipo_logradouro_nome: string;
}

export interface DashboardStats {
  total: number;
  ativas: number;
  manutencao: number;
  desativadas: number;
  atrasada: number;
  uptime: number;
  alarmes_total: number;
  alarmes_manutencao: number;
  alarmes_atrasadas: number;
}

export interface ChartDataItem {
  status?: string;
  tipo?: string;
  quantidade: number;
}

export interface DashboardManutencaoItem {
  camera_nome: string;
  serie_mac: string;
  descricao: string;
  data_hora: string;
  tecnico_responsavel: string;
  status: string;
}

export interface DashboardProblemaItem {
  id: number;
  nome: string;
  numero_serie: string;
  localizacao: string;
  status: string;
  dias_atraso: number;
  ultima_manutencao: string;
}

export interface DashboardUserInfo {
  nome: string;
  nivel_acesso: string;
}

export interface DashboardData {
  stats: DashboardStats;
  tipos: Array<{ tipo: string; quantidade: number }>;
  manutencoes: DashboardManutencaoItem[];
  problemas: DashboardProblemaItem[];
  status_data: ChartDataItem[];
  alarm_status_data: ChartDataItem[];
  camera_tipo_data: ChartDataItem[];
  user_info: DashboardUserInfo;
}

export interface CheckItem {
  id: number;
  nome: string;
}

export interface ManutencaoDefaults {
  status_id: number | null;
  procedimento_id: number | null;
}

export interface CameraOption {
  id: number;
  descricao: string;
  local_nome: string;
  local_logradouro: string;
  local_numero: string;
  local_bairro: string;
  local_cidade: string;
  local_uf: string;
  local_cep: string;
  tipo_logradouro: string;
  em_manutencao: 0 | 1;
  ip: string;
  numero_serie: string;
  modelo_nome: string;
  tipo_equipamento_nome: string;
  status_nome: string;
}

export interface AlarmeOption {
  id: number;
  conta: number;
  local: string;
  endereco: string;
  ip: string;
  status: string;
  modelo_central: string;
}

export interface MaintenanceOrder {
  id: number;
  equipamento_id?: number;
  alarme_id?: number;
  numero_os: string;
  local_servico: string;
  endereco_servico: string;
  problemas: string;
  data_hora: string;
  created_at: string;
  camera_nome?: string;
  alarme_nome?: string;
  ip: string;
  numero_serie?: string;
  modelo_nome?: string;
  tipo_equipamento_nome?: string;
  usuario_nome: string;
  procedimento_id?: number;
  conta?: number;
}

export interface MaintenanceHistorico {
  id: number;
  equipamento_id?: number;
  alarme_id?: number;
  numero_os: string;
  tecnico: string;
  local_servico: string;
  endereco_servico: string;
  descricao: string;
  pecas_previstas: string;
  problemas?: string;
  data_hora: string;
  data_execucao: string;
  created_at: string;
  camera_nome?: string;
  alarme_nome?: string;
  ip: string;
  numero_serie?: string;
  modelo_nome?: string;
  tipo_equipamento_nome?: string;
  status_nome: string;
  procedimento_nome: string;
  usuario_nome: string;
  conta?: number;
}

export interface Sorting {
  sort_by: string;
  sort_dir: 'ASC' | 'DESC';
}

export interface ManutencaoCamerasResponse {
  cameras: CameraOption[];
  status_options: CheckItem[];
  procedimento_options: CheckItem[];
  defaults: ManutencaoDefaults;
  pending_orders: MaintenanceOrder[];
  executing_orders: MaintenanceOrder[];
  historico: MaintenanceHistorico[];
  pagination: Pagination;
  sorting: Sorting;
}

export interface ManutencaoAlarmesResponse {
  alarme: AlarmeOption[];
  status_options: CheckItem[];
  procedimento_options: CheckItem[];
  defaults: ManutencaoDefaults;
  pending_orders: MaintenanceOrder[];
  executing_orders: MaintenanceOrder[];
  historico: MaintenanceHistorico[];
  pagination: Pagination;
  sorting: Sorting;
}

export interface AnexoData {
  id: number;
  equipamento_id: number | null;
  alarme_id: number | null;
  manutencao_camera_id: number | null;
  manutencao_alarme_id: number | null;
  tipo: string;
  nome_original: string;
  nome_arquivo: string;
  caminho: string;
  mime_type: string;
  tamanho: number;
  descricao: string;
  created_by: number;
  created_at: string;
  usuario_nome: string;
  url: string;
  tamanho_formatado: string;
}

export interface AnexosResponse {
  data: AnexoData[];
  total: number;
}

export interface ModeloItem {
  id: number;
  nome: string;
}

export interface ModelosResponse {
  modelos: ModeloItem[];
}

export interface HealthResponse {
  status: 'ok' | 'degraded';
  checks: {
    db_connection: boolean;
  };
}

export interface CepLookupData {
  cep: string;
  logradouro: string;
  bairro: string;
  cidade: string;
  uf: string;
  complemento: string;
}

export interface GeocodeData {
  lat: number;
  lon: number;
}

export interface ReportCameraItem {
  id: number;
  nome_local: string;
  descricao: string;
  ip: string;
  porta: number;
  url_acesso: string;
  serie_mac: string;
  patrimonio: string;
  data_instalacao: string;
  observacao: string;
  created_at: string;
  updated_at: string;
  local_id: number;
  secretaria_id: number;
  status_id: number;
  modelo_id: number;
  marca_id: number;
  tipo_id: number;
  tipo_camera_id: number;
  procedimento_id: number;
  regiao_id: number;
  transmissao_id: number;
  origem_link_id: number;
  modelo_nome: string;
  marca_nome: string;
  local_nome: string;
  local_logradouro: string;
  local_bairro: string;
  local_cidade: string;
  local_uf: string;
  local_cep: string;
  local_numero: string;
  secretaria_nome: string;
  status_nome: string;
  procedimento_nome: string;
  regiao_nome: string;
  transmissao_nome: string;
  origem_link_nome: string;
  tipo_camera_nome: string;
  tipo_logradouro: string;
  mosaico: string;
  coordenadas: string;
  numero_ruas: number;
  alarme_conta: number;
}

export interface CreatedResource {
  camera_id?: string;
  alarme_id?: string;
  message?: string;
  input?: Record<string, unknown>;
}

export interface CreatedResponse extends ApiResponse<{
  resource: CreatedResource;
  id: string | null;
}> {}

// =========================================================================
// Generic helper types for paginated/filter queries
// =========================================================================

export interface QueryFilters {
  page?: number;
  per_page?: number;
  busca?: string;
  status?: string;
  local_id?: string;
  regiao?: string;
  local?: string;
  pesquisa?: string;
}
