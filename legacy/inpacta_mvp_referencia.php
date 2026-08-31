<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
function agora(): string
{
    return date('Y-m-d H:i:s');
}
function dataBr(?string $value): string
{
    if (!$value) return '—';
    $ts = strtotime($value);
    return $ts ? date('d/m/Y H:i:s', $ts) : $value;
}
function dataCurtaBr(?string $value): string
{
    if (!$value) return '—';
    $ts = strtotime($value);
    return $ts ? date('d/m/Y', $ts) : $value;
}
function dataIsoValida(string $value): bool
{
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $dt !== false && $dt->format('Y-m-d') === $value;
}
function somarDias(string $data, int $dias): string
{
    return (new DateTimeImmutable($data))->modify(($dias >= 0 ? '+' : '') . $dias . ' days')->format('Y-m-d');
}
function diferencaDias(string $inicio, string $fim): int
{
    $a = new DateTimeImmutable($inicio);
    $b = new DateTimeImmutable($fim);
    return (int) $a->diff($b)->format('%r%a');
}
function slug(string $value): string
{
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $ascii = $ascii !== false ? $ascii : $value;
    $ascii = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii));
    return trim($ascii, '-') ?: 'arquivo';
}
function tamanhoArquivo(int $bytes): string
{
    if ($bytes <= 0) return '';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 0, ',', '.') . ' KB';
    return $bytes . ' B';
}
function flash(string $tipo, string $mensagem): void
{
    $_SESSION['flash'] = [$tipo, $mensagem];
}
function redirectTo(array $params = []): never
{
    $base = strtok($_SERVER['REQUEST_URI'] ?? basename(__FILE__), '?');
    header('Location: ' . $base . ($params ? '?' . http_build_query($params) : ''));
    exit;
}
function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}
function validarCsrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        throw new RuntimeException('Sessão expirada. Atualize a página e tente novamente.');
    }
}
function dbAll(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function dbOne(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}
function dbExec(PDO $pdo, string $sql, array $params = []): PDOStatement
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
function normalizarExtensoes(string $value): string
{
    $items = preg_split('/[,;\s]+/', strtolower($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $items = array_values(array_unique(array_map(fn($x) => ltrim(trim($x), '.'), $items)));
    return implode(',', $items);
}
function salvarUpload(string $campo, string $pastaRelativa, string $prefixo, array $permitidas): array
{
    if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Selecione um arquivo para enviar.');
    }
    if ($_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Não foi possível receber o arquivo enviado.');
    }
    if ((int) $_FILES[$campo]['size'] > 20 * 1024 * 1024) {
        throw new RuntimeException('O arquivo deve ter no máximo 20 MB.');
    }
    $original = basename((string) $_FILES[$campo]['name']);
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!$ext || !in_array($ext, $permitidas, true)) {
        throw new RuntimeException('Formato não permitido para este documento.');
    }
    $pastaAbs = __DIR__ . '/' . trim($pastaRelativa, '/');
    if (!is_dir($pastaAbs) && !mkdir($pastaAbs, 0775, true) && !is_dir($pastaAbs)) {
        throw new RuntimeException('Não foi possível criar a pasta de arquivos.');
    }
    $seguro = preg_replace('/[^a-zA-Z0-9._-]/', '_', $original);
    $nome = slug($prefixo) . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $seguro;
    $destino = $pastaAbs . '/' . $nome;
    if (!move_uploaded_file($_FILES[$campo]['tmp_name'], $destino)) {
        throw new RuntimeException('Não foi possível salvar o arquivo enviado.');
    }
    return [
        'original' => $original,
        'caminho' => trim($pastaRelativa, '/') . '/' . $nome,
        'tamanho' => (int) $_FILES[$campo]['size'],
    ];
}
function enviarDownload(string $relativo, string $nome): never
{
    $base = realpath(__DIR__);
    $arquivo = realpath(__DIR__ . '/' . ltrim($relativo, '/'));
    if (!$base || !$arquivo || strpos($arquivo, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($arquivo)) {
        throw new RuntimeException('O arquivo solicitado não está disponível.');
    }
    $mime = function_exists('mime_content_type') ? (mime_content_type($arquivo) ?: 'application/octet-stream') : 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($arquivo));
    header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode(basename($nome)));
    header('X-Content-Type-Options: nosniff');
    readfile($arquivo);
    exit;
}
function registrarHistorico(PDO $pdo, array $dados): void
{
    dbExec($pdo, 'INSERT INTO historico_documentos
        (municipio_id, fase_id, requisito_id, evento, tipo_arquivo, arquivo_original, arquivo_salvo,
         arquivo_anterior_original, tamanho, usuario, email, perfil, motivo, status, criado_em)
        VALUES (:municipio_id,:fase_id,:requisito_id,:evento,:tipo_arquivo,:arquivo_original,:arquivo_salvo,
         :arquivo_anterior_original,:tamanho,:usuario,:email,:perfil,:motivo,:status,:criado_em)', [
        ':municipio_id' => $dados['municipio_id'] ?? '',
        ':fase_id' => $dados['fase_id'] ?? null,
        ':requisito_id' => $dados['requisito_id'] ?? null,
        ':evento' => $dados['evento'] ?? 'Registro',
        ':tipo_arquivo' => $dados['tipo_arquivo'] ?? '',
        ':arquivo_original' => $dados['arquivo_original'] ?? '',
        ':arquivo_salvo' => $dados['arquivo_salvo'] ?? '',
        ':arquivo_anterior_original' => $dados['arquivo_anterior_original'] ?? '',
        ':tamanho' => (int) ($dados['tamanho'] ?? 0),
        ':usuario' => $dados['usuario'] ?? '',
        ':email' => $dados['email'] ?? '',
        ':perfil' => $dados['perfil'] ?? '',
        ':motivo' => $dados['motivo'] ?? '',
        ':status' => $dados['status'] ?? '',
        ':criado_em' => agora(),
    ]);
}
function statusDocumento(?array $doc): array
{
    if (!$doc) return ['PENDENTE', 'pending'];
    return match ($doc['status'] ?? 'aguardando') {
        'aprovado' => ['APROVADO', 'approved'],
        'correcao' => ['CORREÇÃO', 'correction'],
        default => ['AGUARDANDO', 'waiting'],
    };
}

$perfilSolicitado = $_GET['perfil'] ?? ($_SESSION['perfil'] ?? 'stratelli');
$perfil = in_array($perfilSolicitado, ['municipio', 'stratelli', 'secretaria'], true) ? $perfilSolicitado : 'stratelli';
$_SESSION['perfil'] = $perfil;
$municipioId = (string) ($_GET['municipio'] ?? ($_SESSION['municipio_id'] ?? 'maringa'));
$municipioNome = (string) ($_SESSION['municipio_nome'] ?? 'Maringá - PR');
$_SESSION['municipio_id'] = $municipioId;
$secretariaPerfilId = max(0, (int) ($_GET['secretaria'] ?? ($_POST['secretaria_contexto'] ?? ($_SESSION['secretaria_id'] ?? 0))));
$secretariaPerfil = null;
$secretariasPerfilDisponiveis = [];

if ($perfil === 'municipio') {
    $usuarioLogado = $_SESSION['usuario_nome'] ?? 'Gestor Municipal de Maringá';
    $emailUsuario = $_SESSION['usuario_email'] ?? 'adminmaringa@stratelli.local';
} elseif ($perfil === 'secretaria') {
    $usuarioLogado = 'Usuário da Secretaria';
    $emailUsuario = 'secretaria@municipio.local';
} else {
    $usuarioLogado = $_SESSION['usuario_nome'] ?? 'Stratelli Consultoria';
    $emailUsuario = $_SESSION['usuario_email'] ?? 'stratelli@stratelli.local';
}

$view = (string) ($_GET['view'] ?? 'workflow');
$viewsPermitidas=['workflow','dashboard','municipios','documentos','relatorios','historico','configuracoes'];
if (!in_array($view, $viewsPermitidas, true)) $view = 'workflow';
if ($perfil !== 'stratelli' && in_array($view,['configuracoes','municipios'],true)) $view = 'dashboard';
if ($perfil === 'secretaria' && $view === 'documentos') $view = 'dashboard';

$driverDisponivel = in_array('sqlite', PDO::getAvailableDrivers(), true);
if (!$driverDisponivel) {
    http_response_code(500);
    echo '<!doctype html><html lang="pt-br"><meta charset="utf-8"><title>INPACTA</title><body style="font-family:Arial;padding:40px;background:#f6f9fc;color:#08224b"><h1>Extensão PDO SQLite necessária</h1><p>Habilite <b>pdo_sqlite</b> no PHP para executar este MVP. O arquivo já contém a criação automática do banco e das tabelas.</p></body></html>';
    exit;
}

$dadosDir = __DIR__ . '/dados_workflow';
if (!is_dir($dadosDir)) mkdir($dadosDir, 0775, true);
$pdo = new PDO('sqlite:' . $dadosDir . '/inpacta_dinamico.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec('PRAGMA journal_mode = WAL');

$schema = [
"CREATE TABLE IF NOT EXISTS fases (
 id INTEGER PRIMARY KEY AUTOINCREMENT, ordem INTEGER NOT NULL UNIQUE, codigo TEXT NOT NULL UNIQUE,
 aba TEXT NOT NULL, titulo TEXT NOT NULL, descricao TEXT NOT NULL DEFAULT '', responsavel TEXT NOT NULL DEFAULT '',
 dia_inicio INTEGER NOT NULL DEFAULT 1, dia_fim INTEGER NOT NULL DEFAULT 1, entregavel TEXT NOT NULL DEFAULT '',
 criterio TEXT NOT NULL DEFAULT '', exclusivo_stratelli INTEGER NOT NULL DEFAULT 0, ativo INTEGER NOT NULL DEFAULT 1,
 criado_em TEXT NOT NULL, atualizado_em TEXT NOT NULL)",
"CREATE TABLE IF NOT EXISTS secretarias (
 id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL UNIQUE, sigla TEXT NOT NULL DEFAULT '', ativo INTEGER NOT NULL DEFAULT 1,
 criado_em TEXT NOT NULL, atualizado_em TEXT NOT NULL)",
"CREATE TABLE IF NOT EXISTS departamentos (
 id INTEGER PRIMARY KEY AUTOINCREMENT, secretaria_id INTEGER NOT NULL, nome TEXT NOT NULL, sigla TEXT NOT NULL DEFAULT '',
 ativo INTEGER NOT NULL DEFAULT 1, criado_em TEXT NOT NULL, atualizado_em TEXT NOT NULL,
 UNIQUE(secretaria_id,nome), FOREIGN KEY(secretaria_id) REFERENCES secretarias(id))",
"CREATE TABLE IF NOT EXISTS fase_secretarias (
 fase_id INTEGER NOT NULL, secretaria_id INTEGER NOT NULL, PRIMARY KEY(fase_id,secretaria_id),
 FOREIGN KEY(fase_id) REFERENCES fases(id) ON DELETE CASCADE, FOREIGN KEY(secretaria_id) REFERENCES secretarias(id) ON DELETE CASCADE)",
"CREATE TABLE IF NOT EXISTS tipos_documento (
 id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL UNIQUE, descricao TEXT NOT NULL DEFAULT '',
 extensoes TEXT NOT NULL DEFAULT 'pdf,doc,docx,xls,xlsx,odt,ods,rtf,txt', ativo INTEGER NOT NULL DEFAULT 1,
 criado_em TEXT NOT NULL, atualizado_em TEXT NOT NULL)",
"CREATE TABLE IF NOT EXISTS requisitos_documentais (
 id INTEGER PRIMARY KEY AUTOINCREMENT, fase_id INTEGER NOT NULL, secretaria_id INTEGER NOT NULL, departamento_id INTEGER,
 tipo_documento_id INTEGER NOT NULL, nome TEXT NOT NULL, descricao TEXT NOT NULL DEFAULT '', perfil_envio TEXT NOT NULL DEFAULT 'municipio',
 obrigatorio INTEGER NOT NULL DEFAULT 1, ativo INTEGER NOT NULL DEFAULT 1, ordem INTEGER NOT NULL DEFAULT 1,
 criado_em TEXT NOT NULL, atualizado_em TEXT NOT NULL,
 FOREIGN KEY(fase_id) REFERENCES fases(id), FOREIGN KEY(secretaria_id) REFERENCES secretarias(id),
 FOREIGN KEY(departamento_id) REFERENCES departamentos(id), FOREIGN KEY(tipo_documento_id) REFERENCES tipos_documento(id))",
"CREATE TABLE IF NOT EXISTS modelos_documentos (
 id INTEGER PRIMARY KEY AUTOINCREMENT, requisito_id INTEGER NOT NULL, arquivo_original TEXT NOT NULL, arquivo_salvo TEXT NOT NULL,
 tamanho INTEGER NOT NULL DEFAULT 0, usuario TEXT NOT NULL DEFAULT '', email TEXT NOT NULL DEFAULT '', ativo INTEGER NOT NULL DEFAULT 1,
 criado_em TEXT NOT NULL, FOREIGN KEY(requisito_id) REFERENCES requisitos_documentais(id))",
"CREATE TABLE IF NOT EXISTS documentos_enviados (
 id INTEGER PRIMARY KEY AUTOINCREMENT, municipio_id TEXT NOT NULL, requisito_id INTEGER NOT NULL, versao INTEGER NOT NULL DEFAULT 1,
 arquivo_original TEXT NOT NULL, arquivo_salvo TEXT NOT NULL, tamanho INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT 'aguardando',
 observacao_validacao TEXT NOT NULL DEFAULT '', enviado_por TEXT NOT NULL DEFAULT '', enviado_email TEXT NOT NULL DEFAULT '', enviado_em TEXT NOT NULL,
 validado_por TEXT NOT NULL DEFAULT '', validado_em TEXT, FOREIGN KEY(requisito_id) REFERENCES requisitos_documentais(id))",
"CREATE INDEX IF NOT EXISTS idx_documentos_municipio_requisito ON documentos_enviados(municipio_id,requisito_id,id)",
"CREATE TABLE IF NOT EXISTS historico_documentos (
 id INTEGER PRIMARY KEY AUTOINCREMENT, municipio_id TEXT NOT NULL DEFAULT '', fase_id INTEGER, requisito_id INTEGER,
 evento TEXT NOT NULL, tipo_arquivo TEXT NOT NULL DEFAULT '', arquivo_original TEXT NOT NULL DEFAULT '', arquivo_salvo TEXT NOT NULL DEFAULT '',
 arquivo_anterior_original TEXT NOT NULL DEFAULT '', tamanho INTEGER NOT NULL DEFAULT 0, usuario TEXT NOT NULL DEFAULT '',
 email TEXT NOT NULL DEFAULT '', perfil TEXT NOT NULL DEFAULT '', motivo TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT '', criado_em TEXT NOT NULL)",
"CREATE TABLE IF NOT EXISTS cronograma_processos (
 municipio_id TEXT PRIMARY KEY, data_inicio TEXT NOT NULL, criado_em TEXT NOT NULL, atualizado_em TEXT NOT NULL)",
"CREATE TABLE IF NOT EXISTS cronograma_fases (
 id INTEGER PRIMARY KEY AUTOINCREMENT, municipio_id TEXT NOT NULL, fase_id INTEGER NOT NULL, data_conclusao_real TEXT NOT NULL,
 concluido_por TEXT NOT NULL DEFAULT '', observacao TEXT NOT NULL DEFAULT '', criado_em TEXT NOT NULL, atualizado_em TEXT NOT NULL,
 UNIQUE(municipio_id,fase_id), FOREIGN KEY(fase_id) REFERENCES fases(id))",
];
foreach ($schema as $sql) $pdo->exec($sql);

if ((int) $pdo->query('SELECT COUNT(*) FROM fases')->fetchColumn() === 0) {
    $pdo->beginTransaction();
    try {
        $tipos = [
            ['Comprovante interno', 'Evidência de atividade executada pela Stratelli', 'pdf,doc,docx,jpg,jpeg,png,webp,zip'],
            ['Documento administrativo', 'Documento formal administrativo', 'pdf,doc,docx,odt,rtf'],
            ['Estudo técnico', 'Estudos, matrizes e peças técnicas', 'pdf,doc,docx,xls,xlsx,odt,ods'],
            ['Pesquisa e orçamento', 'Orçamentos, mapas comparativos e pesquisas', 'pdf,doc,docx,xls,xlsx'],
            ['Documento jurídico', 'Pareceres, despachos e documentos jurídicos', 'pdf,doc,docx,odt,rtf'],
            ['Contrato e publicação', 'Editais, contratos, publicações e ordens de serviço', 'pdf,doc,docx'],
        ];
        foreach ($tipos as $t) dbExec($pdo, 'INSERT INTO tipos_documento(nome,descricao,extensoes,criado_em,atualizado_em) VALUES(?,?,?,?,?)', [$t[0],$t[1],$t[2],agora(),agora()]);
        $secretariasSeed = [
            ['Stratelli', 'STRATELLI'], ['Secretaria de Segurança Urbana', 'SESEG'],
            ['Secretaria de Administração e Licitações', 'SEADM'], ['Procuradoria-Geral do Município', 'PGM'],
            ['Gabinete do Prefeito', 'GAB'], ['Secretaria de Fazenda', 'SEFAZ'],
        ];
        foreach ($secretariasSeed as $s) dbExec($pdo, 'INSERT INTO secretarias(nome,sigla,criado_em,atualizado_em) VALUES(?,?,?,?)', [$s[0],$s[1],agora(),agora()]);
        $fasesSeed = [
            [0,'INICIAL-01','Apresentação','Apresentação e Negociação','Reunião de apresentação das soluções e definição da estratégia de contratação.','Stratelli',1,1,'Apresentação concluída e município liberado','Checklist interno concluído',1],
            [1,'INICIAL-02','Solicitação','Solicitação','Sinalização formal de interesse e definição da estratégia de contratação, direta ou licitatória.','Secretaria de Segurança Urbana / Prefeito',1,9,'Sinalização da prefeitura pelo prosseguimento','Aprovação do prefeito e equipe',0],
            [2,'INICIAL-03','ETP','Estudo Técnico Preliminar','Consolidação das necessidades e preparação das peças técnicas de contratação.','Setor de licitações municipal',10,24,'ETP e Matriz de Risco','Peças técnicas prontas',0],
            [3,'INICIAL-04','Pesquisa','Pesquisa de Mercado','Coleta de orçamentos e verificação de expertise técnica das empresas candidatas.','Setor de licitações municipal',10,19,'Pesquisa de preços consolidada','Pesquisa pronta',0],
            [4,'INICIAL-05','Termo de Referência','Termo de Referência','Redação do Termo de Referência com base no cronograma técnico escolhido.','Setor de licitações municipal',16,25,'Termo de Referência consolidado','TR pronto',0],
            [5,'INICIAL-06','Parecer Jurídico','Parecer Jurídico','Análise jurídica e bloqueio de dotação orçamentária para o projeto.','Setor Jurídico Municipal',26,40,'Parecer jurídico para prosseguimento','Parecer e reserva aprovados',0],
            [6,'INICIAL-07','Seleção / Contratação','Seleção ou Contratação Direta','Publicação de edital ou formalização de dispensa/inexigibilidade.','Setor de licitações municipal',41,85,'Publicação do certame e seleção','Publicação ou rito formalizado',0],
            [7,'INICIAL-08','Contrato e OS','Contrato e Ordem de Serviço','Assinatura do contrato e emissão da Ordem de Serviço para início técnico.','Prefeito / Secretaria / Licitações',86,90,'Contrato assinado e recurso empenhado','Contrato assinado, empenho e OS emitidos',0],
        ];
        foreach ($fasesSeed as $f) dbExec($pdo, 'INSERT INTO fases(ordem,codigo,aba,titulo,descricao,responsavel,dia_inicio,dia_fim,entregavel,criterio,exclusivo_stratelli,criado_em,atualizado_em) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)', array_merge($f,[agora(),agora()]));
        $idsFase = [];
        foreach (dbAll($pdo, 'SELECT id,ordem FROM fases') as $r) $idsFase[(int)$r['ordem']] = (int)$r['id'];
        $idsSec = [];
        foreach (dbAll($pdo, 'SELECT id,nome FROM secretarias') as $r) $idsSec[$r['nome']] = (int)$r['id'];
        $idsTipo = [];
        foreach (dbAll($pdo, 'SELECT id,nome FROM tipos_documento') as $r) $idsTipo[$r['nome']] = (int)$r['id'];
        $vinculos = [
            0=>['Stratelli'], 1=>['Secretaria de Segurança Urbana','Gabinete do Prefeito'],
            2=>['Secretaria de Administração e Licitações'], 3=>['Secretaria de Administração e Licitações'],
            4=>['Secretaria de Administração e Licitações'], 5=>['Procuradoria-Geral do Município','Secretaria de Fazenda'],
            6=>['Secretaria de Administração e Licitações'], 7=>['Gabinete do Prefeito','Secretaria de Administração e Licitações','Secretaria de Fazenda'],
        ];
        foreach ($vinculos as $ordem=>$secs) foreach ($secs as $sec) dbExec($pdo, 'INSERT INTO fase_secretarias(fase_id,secretaria_id) VALUES(?,?)', [$idsFase[$ordem],$idsSec[$sec]]);
        $docsSeed = [
            [0,'Stratelli','Comprovante interno','Reunião de apresentação realizada','stratelli'],
            [0,'Stratelli','Comprovante interno','Interesse formalizado pelo município','stratelli'],
            [0,'Stratelli','Comprovante interno','Projeto aprovado internamente','stratelli'],
            [0,'Stratelli','Comprovante interno','Definição da estratégia de contratação','stratelli'],
            [1,'Secretaria de Segurança Urbana','Documento administrativo','Manifestação formal de interesse','municipio'],
            [1,'Gabinete do Prefeito','Documento administrativo','Ata ou despacho de aprovação','municipio'],
            [1,'Secretaria de Segurança Urbana','Documento administrativo','Definição da modalidade de contratação','municipio'],
            [2,'Secretaria de Administração e Licitações','Estudo técnico','ETP','municipio'],
            [2,'Secretaria de Administração e Licitações','Estudo técnico','Matriz de Risco','municipio'],
            [2,'Secretaria de Administração e Licitações','Estudo técnico','Necessidades consolidadas','municipio'],
            [3,'Secretaria de Administração e Licitações','Pesquisa e orçamento','Orçamentos coletados','municipio'],
            [3,'Secretaria de Administração e Licitações','Pesquisa e orçamento','Mapa comparativo','municipio'],
            [3,'Secretaria de Administração e Licitações','Pesquisa e orçamento','Comprovação de expertise técnica','municipio'],
            [4,'Secretaria de Administração e Licitações','Estudo técnico','Termo de Referência','municipio'],
            [4,'Secretaria de Administração e Licitações','Estudo técnico','Objeto detalhado','municipio'],
            [4,'Secretaria de Administração e Licitações','Estudo técnico','Critérios de execução e aceite','municipio'],
            [5,'Procuradoria-Geral do Município','Documento jurídico','Parecer jurídico','municipio'],
            [5,'Secretaria de Fazenda','Documento administrativo','Reserva orçamentária','municipio'],
            [5,'Procuradoria-Geral do Município','Documento jurídico','Despacho de prosseguimento','municipio'],
            [6,'Secretaria de Administração e Licitações','Contrato e publicação','Edital ou contratação direta','municipio'],
            [6,'Secretaria de Administração e Licitações','Contrato e publicação','Publicação oficial','municipio'],
            [6,'Secretaria de Administração e Licitações','Contrato e publicação','Resultado da seleção','municipio'],
            [7,'Gabinete do Prefeito','Contrato e publicação','Contrato assinado','municipio'],
            [7,'Secretaria de Fazenda','Documento administrativo','Empenho','municipio'],
            [7,'Secretaria de Administração e Licitações','Contrato e publicação','Ordem de Serviço','municipio'],
        ];
        $ordemPorFase = [];
        foreach ($docsSeed as $d) {
            $ordemPorFase[$d[0]] = ($ordemPorFase[$d[0]] ?? 0) + 1;
            dbExec($pdo, 'INSERT INTO requisitos_documentais(fase_id,secretaria_id,tipo_documento_id,nome,perfil_envio,ordem,criado_em,atualizado_em) VALUES(?,?,?,?,?,?,?,?)', [
                $idsFase[$d[0]], $idsSec[$d[1]], $idsTipo[$d[2]], $d[3], $d[4], $ordemPorFase[$d[0]], agora(), agora()
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Perfil Secretaria (teste): a secretaria escolhida funciona como o escopo de acesso.
// Em produção, este ID deverá vir do usuário autenticado, sem seletor livre na URL.
$secretariasPerfilDisponiveis = dbAll($pdo, "SELECT * FROM secretarias WHERE ativo=1 AND UPPER(COALESCE(sigla,''))<>'STRATELLI' AND LOWER(nome)<>'stratelli' ORDER BY nome");
if ($perfil === 'secretaria') {
    foreach ($secretariasPerfilDisponiveis as $secretariaDisponivel) {
        if ((int)$secretariaDisponivel['id'] === $secretariaPerfilId) {
            $secretariaPerfil = $secretariaDisponivel;
            break;
        }
    }
    if (!$secretariaPerfil && $secretariasPerfilDisponiveis) {
        $secretariaPerfil = $secretariasPerfilDisponiveis[0];
        $secretariaPerfilId = (int)$secretariaPerfil['id'];
    }
    if (!$secretariaPerfil) {
        throw new RuntimeException('Nenhuma secretaria ativa está disponível para o perfil de teste.');
    }
    $_SESSION['secretaria_id'] = $secretariaPerfilId;
    $usuarioLogado = (string)$secretariaPerfil['nome'];
    $emailUsuario = strtolower((string)($secretariaPerfil['sigla'] ?: 'secretaria')) . '@municipio.local';
}
$contextoSecretaria = $perfil === 'secretaria' ? ['secretaria'=>$secretariaPerfilId] : [];
$contextoSecretariaQuery = $perfil === 'secretaria' ? '&secretaria=' . $secretariaPerfilId : '';
$perfilRotulo = match($perfil){'stratelli'=>'STRATELLI','secretaria'=>'SECRETARIA',default=>'GESTOR MUNICIPAL'};
$perfilClasse = $perfil === 'stratelli' ? 'stratelli' : ($perfil === 'secretaria' ? 'secretaria' : '');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && isset($_GET['acao'])) {
        $acaoGet = (string) $_GET['acao'];
        if ($acaoGet === 'download_modelo') {
            $modelo = dbOne($pdo, 'SELECT m.*,r.secretaria_id,r.perfil_envio FROM modelos_documentos m JOIN requisitos_documentais r ON r.id=m.requisito_id WHERE m.requisito_id=? AND m.ativo=1 ORDER BY m.id DESC LIMIT 1', [(int)($_GET['requisito'] ?? 0)]);
            if (!$modelo) throw new RuntimeException('O modelo ainda não foi disponibilizado.');
            if ($perfil === 'secretaria' && ((int)$modelo['secretaria_id'] !== $secretariaPerfilId || $modelo['perfil_envio'] !== 'municipio')) throw new RuntimeException('Este modelo não pertence à sua secretaria.');
            enviarDownload($modelo['arquivo_salvo'], $modelo['arquivo_original']);
        }
        if ($acaoGet === 'download_documento') {
            $doc = dbOne($pdo, 'SELECT d.*,r.secretaria_id,r.perfil_envio FROM documentos_enviados d JOIN requisitos_documentais r ON r.id=d.requisito_id WHERE d.id=?', [(int)($_GET['id'] ?? 0)]);
            if (!$doc || (in_array($perfil,['municipio','secretaria'],true) && $doc['municipio_id'] !== $municipioId)) throw new RuntimeException('Documento não encontrado.');
            if ($perfil === 'secretaria' && ((int)$doc['secretaria_id'] !== $secretariaPerfilId || $doc['perfil_envio'] !== 'municipio')) throw new RuntimeException('Este documento não pertence à sua secretaria.');
            enviarDownload($doc['arquivo_salvo'], $doc['arquivo_original']);
        }
        if ($acaoGet === 'download_historico') {
            $hist = dbOne($pdo, 'SELECT h.*,r.secretaria_id,r.perfil_envio FROM historico_documentos h LEFT JOIN requisitos_documentais r ON r.id=h.requisito_id WHERE h.id=?', [(int)($_GET['id'] ?? 0)]);
            if (!$hist || !$hist['arquivo_salvo']) throw new RuntimeException('Arquivo do histórico não encontrado.');
            if ($perfil === 'secretaria' && ((int)($hist['secretaria_id']??0) !== $secretariaPerfilId || ($hist['perfil_envio']??'') !== 'municipio')) throw new RuntimeException('Este histórico não pertence à sua secretaria.');
            enviarDownload($hist['arquivo_salvo'], $hist['arquivo_original'] ?: 'documento');
        }
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        validarCsrf();
        $acao = (string) ($_POST['acao'] ?? '');
        $retornoBase = ['perfil'=>$perfil,'municipio'=>$municipioId,'view'=>$view] + $contextoSecretaria;

        if (in_array($acao, ['salvar_fase','salvar_secretaria','salvar_departamento','salvar_tipo','salvar_requisito','alternar_fase','alternar_secretaria','alternar_departamento','alternar_tipo','alternar_requisito','vincular_secretaria','desvincular_secretaria'], true) && $perfil !== 'stratelli') {
            throw new RuntimeException('Apenas o perfil Stratelli pode alterar as configurações.');
        }

        if ($acao === 'salvar_inicio_cronograma') {
            if ($perfil !== 'stratelli') throw new RuntimeException('Apenas a Stratelli pode alterar a data de início do processo.');
            $dataInicio = trim((string) ($_POST['data_inicio'] ?? ''));
            if (!dataIsoValida($dataInicio)) throw new RuntimeException('Informe uma data de início válida.');
            dbExec($pdo, 'INSERT INTO cronograma_processos(municipio_id,data_inicio,criado_em,atualizado_em) VALUES(?,?,?,?)
                ON CONFLICT(municipio_id) DO UPDATE SET data_inicio=excluded.data_inicio,atualizado_em=excluded.atualizado_em',
                [$municipioId,$dataInicio,agora(),agora()]);
            flash('ok','Data de início do processo atualizada. Todos os prazos operacionais foram recalculados.');
            redirectTo($retornoBase + ($view === 'workflow' ? ['fase'=>(int)($_POST['fase_id'] ?? 0)] : []));
        }
        if ($acao === 'registrar_conclusao_fase') {
            if ($perfil !== 'stratelli') throw new RuntimeException('Apenas a Stratelli pode registrar a conclusão de uma fase.');
            $faseIdCronograma = (int) ($_POST['fase_id'] ?? 0);
            $dataConclusao = trim((string) ($_POST['data_conclusao'] ?? ''));
            $observacaoConclusao = trim((string) ($_POST['observacao'] ?? ''));
            if (!$faseIdCronograma || !dbOne($pdo,'SELECT id FROM fases WHERE id=?',[$faseIdCronograma])) throw new RuntimeException('Fase inválida.');
            if (!dataIsoValida($dataConclusao)) throw new RuntimeException('Informe uma data de conclusão válida.');
            dbExec($pdo, 'INSERT INTO cronograma_fases(municipio_id,fase_id,data_conclusao_real,concluido_por,observacao,criado_em,atualizado_em) VALUES(?,?,?,?,?,?,?)
                ON CONFLICT(municipio_id,fase_id) DO UPDATE SET data_conclusao_real=excluded.data_conclusao_real,concluido_por=excluded.concluido_por,observacao=excluded.observacao,atualizado_em=excluded.atualizado_em',
                [$municipioId,$faseIdCronograma,$dataConclusao,$usuarioLogado,$observacaoConclusao,agora(),agora()]);
            flash('ok','Conclusão da fase registrada. As datas das fases seguintes foram recalculadas.');
            redirectTo(['perfil'=>$perfil,'municipio'=>$municipioId,'view'=>$view,'fase'=>$faseIdCronograma] + $contextoSecretaria);
        }
        if ($acao === 'remover_conclusao_fase') {
            if ($perfil !== 'stratelli') throw new RuntimeException('Apenas a Stratelli pode reabrir uma fase.');
            $faseIdCronograma = (int) ($_POST['fase_id'] ?? 0);
            dbExec($pdo,'DELETE FROM cronograma_fases WHERE municipio_id=? AND fase_id=?',[$municipioId,$faseIdCronograma]);
            flash('ok','Registro manual de conclusão removido. O prazo foi recalculado.');
            redirectTo(['perfil'=>$perfil,'municipio'=>$municipioId,'view'=>$view,'fase'=>$faseIdCronograma] + $contextoSecretaria);
        }

        if ($acao === 'salvar_fase') {
            $id = (int) ($_POST['id'] ?? 0);
            $ordem = max(0, (int) ($_POST['ordem'] ?? 0));
            $codigo = trim((string) ($_POST['codigo'] ?? '')) ?: 'FASE-' . str_pad((string)$ordem, 2, '0', STR_PAD_LEFT);
            $params = [
                ':ordem'=>$ordem, ':codigo'=>$codigo, ':aba'=>trim((string)$_POST['aba']), ':titulo'=>trim((string)$_POST['titulo']),
                ':descricao'=>trim((string)($_POST['descricao'] ?? '')), ':responsavel'=>trim((string)($_POST['responsavel'] ?? '')),
                ':dia_inicio'=>max(1,(int)($_POST['dia_inicio'] ?? 1)), ':dia_fim'=>max(1,(int)($_POST['dia_fim'] ?? 1)),
                ':entregavel'=>trim((string)($_POST['entregavel'] ?? '')), ':criterio'=>trim((string)($_POST['criterio'] ?? '')),
                ':exclusivo'=>isset($_POST['exclusivo_stratelli']) ? 1 : 0, ':agora'=>agora(),
            ];
            if ($params[':titulo']==='' || $params[':aba']==='') throw new RuntimeException('Informe o nome curto e o título da fase.');
            if ($params[':dia_fim'] < $params[':dia_inicio']) throw new RuntimeException('O dia final não pode ser menor que o dia inicial.');
            if ($id > 0) {
                $params[':id']=$id;
                dbExec($pdo, 'UPDATE fases SET ordem=:ordem,codigo=:codigo,aba=:aba,titulo=:titulo,descricao=:descricao,responsavel=:responsavel,dia_inicio=:dia_inicio,dia_fim=:dia_fim,entregavel=:entregavel,criterio=:criterio,exclusivo_stratelli=:exclusivo,atualizado_em=:agora WHERE id=:id', $params);
                flash('ok','Fase atualizada com sucesso.');
            } else {
                dbExec($pdo, 'INSERT INTO fases(ordem,codigo,aba,titulo,descricao,responsavel,dia_inicio,dia_fim,entregavel,criterio,exclusivo_stratelli,criado_em,atualizado_em) VALUES(:ordem,:codigo,:aba,:titulo,:descricao,:responsavel,:dia_inicio,:dia_fim,:entregavel,:criterio,:exclusivo,:agora,:agora)', $params);
                flash('ok','Nova fase cadastrada com sucesso.');
            }
            redirectTo($retornoBase + ['secao'=>'fases']);
        }
        if ($acao === 'alternar_fase') {
            dbExec($pdo, 'UPDATE fases SET ativo=CASE WHEN ativo=1 THEN 0 ELSE 1 END, atualizado_em=? WHERE id=?', [agora(),(int)$_POST['id']]);
            flash('ok','Status da fase alterado.');
            redirectTo($retornoBase + ['secao'=>'fases']);
        }
        if ($acao === 'salvar_secretaria') {
            $id=(int)($_POST['id']??0); $nome=trim((string)$_POST['nome']); $sigla=strtoupper(trim((string)($_POST['sigla']??'')));
            if ($nome==='') throw new RuntimeException('Informe o nome da secretaria.');
            $pdo->beginTransaction();
            if ($id>0) dbExec($pdo,'UPDATE secretarias SET nome=?,sigla=?,atualizado_em=? WHERE id=?',[$nome,$sigla,agora(),$id]);
            else { dbExec($pdo,'INSERT INTO secretarias(nome,sigla,criado_em,atualizado_em) VALUES(?,?,?,?)',[$nome,$sigla,agora(),agora()]); $id=(int)$pdo->lastInsertId(); }
            dbExec($pdo,'DELETE FROM fase_secretarias WHERE secretaria_id=?',[$id]);
            foreach ((array)($_POST['fase_ids']??[]) as $faseId) dbExec($pdo,'INSERT OR IGNORE INTO fase_secretarias(fase_id,secretaria_id) VALUES(?,?)',[(int)$faseId,$id]);
            $pdo->commit();
            flash('ok','Secretaria e vínculos atualizados.');
            redirectTo($retornoBase + ['secao'=>'secretarias']);
        }
        if ($acao === 'alternar_secretaria') {
            dbExec($pdo,'UPDATE secretarias SET ativo=CASE WHEN ativo=1 THEN 0 ELSE 1 END,atualizado_em=? WHERE id=?',[agora(),(int)$_POST['id']]);
            flash('ok','Status da secretaria alterado.'); redirectTo($retornoBase+['secao'=>'secretarias']);
        }
        if ($acao === 'salvar_departamento') {
            $id=(int)($_POST['id']??0); $secretariaId=(int)$_POST['secretaria_id']; $nome=trim((string)$_POST['nome']); $sigla=strtoupper(trim((string)($_POST['sigla']??'')));
            if (!$secretariaId || $nome==='') throw new RuntimeException('Informe a secretaria e o nome do departamento.');
            if ($id>0) dbExec($pdo,'UPDATE departamentos SET secretaria_id=?,nome=?,sigla=?,atualizado_em=? WHERE id=?',[$secretariaId,$nome,$sigla,agora(),$id]);
            else dbExec($pdo,'INSERT INTO departamentos(secretaria_id,nome,sigla,criado_em,atualizado_em) VALUES(?,?,?,?,?)',[$secretariaId,$nome,$sigla,agora(),agora()]);
            flash('ok','Departamento salvo com sucesso.'); redirectTo($retornoBase+['secao'=>'secretarias']);
        }
        if ($acao === 'alternar_departamento') {
            dbExec($pdo,'UPDATE departamentos SET ativo=CASE WHEN ativo=1 THEN 0 ELSE 1 END,atualizado_em=? WHERE id=?',[agora(),(int)$_POST['id']]);
            flash('ok','Status do departamento alterado.'); redirectTo($retornoBase+['secao'=>'secretarias']);
        }
        if ($acao === 'salvar_tipo') {
            $id=(int)($_POST['id']??0); $nome=trim((string)$_POST['nome']); $descricao=trim((string)($_POST['descricao']??'')); $ext=normalizarExtensoes((string)($_POST['extensoes']??''));
            if ($nome==='' || $ext==='') throw new RuntimeException('Informe o nome e as extensões permitidas.');
            if ($id>0) dbExec($pdo,'UPDATE tipos_documento SET nome=?,descricao=?,extensoes=?,atualizado_em=? WHERE id=?',[$nome,$descricao,$ext,agora(),$id]);
            else dbExec($pdo,'INSERT INTO tipos_documento(nome,descricao,extensoes,criado_em,atualizado_em) VALUES(?,?,?,?,?)',[$nome,$descricao,$ext,agora(),agora()]);
            flash('ok','Tipo de documento salvo.'); redirectTo($retornoBase+['secao'=>'tipos']);
        }
        if ($acao === 'alternar_tipo') {
            dbExec($pdo,'UPDATE tipos_documento SET ativo=CASE WHEN ativo=1 THEN 0 ELSE 1 END,atualizado_em=? WHERE id=?',[agora(),(int)$_POST['id']]);
            flash('ok','Status do tipo de documento alterado.'); redirectTo($retornoBase+['secao'=>'tipos']);
        }
        if ($acao === 'salvar_requisito') {
            $id=(int)($_POST['id']??0); $faseId=(int)$_POST['fase_id']; $secretariaId=(int)$_POST['secretaria_id']; $departamentoId=(int)($_POST['departamento_id']??0); $tipoId=(int)$_POST['tipo_documento_id'];
            $nome=trim((string)$_POST['nome']); $descricao=trim((string)($_POST['descricao']??'')); $perfilEnvio=(($_POST['perfil_envio']??'municipio')==='stratelli'?'stratelli':'municipio');
            $ordem=max(1,(int)($_POST['ordem']??1)); $obrigatorio=isset($_POST['obrigatorio'])?1:0;
            if (!$faseId || !$secretariaId || !$tipoId || $nome==='') throw new RuntimeException('Preencha fase, secretaria, tipo e nome do documento.');
            if ($departamentoId) {
                $dep=dbOne($pdo,'SELECT secretaria_id FROM departamentos WHERE id=?',[$departamentoId]);
                if (!$dep || (int)$dep['secretaria_id']!==$secretariaId) throw new RuntimeException('O departamento selecionado não pertence à secretaria informada.');
            }
            dbExec($pdo,'INSERT OR IGNORE INTO fase_secretarias(fase_id,secretaria_id) VALUES(?,?)',[$faseId,$secretariaId]);
            if ($id>0) {
                dbExec($pdo,'UPDATE requisitos_documentais SET fase_id=?,secretaria_id=?,departamento_id=?,tipo_documento_id=?,nome=?,descricao=?,perfil_envio=?,obrigatorio=?,ordem=?,atualizado_em=? WHERE id=?',[$faseId,$secretariaId,$departamentoId?:null,$tipoId,$nome,$descricao,$perfilEnvio,$obrigatorio,$ordem,agora(),$id]);
            } else {
                dbExec($pdo,'INSERT INTO requisitos_documentais(fase_id,secretaria_id,departamento_id,tipo_documento_id,nome,descricao,perfil_envio,obrigatorio,ordem,criado_em,atualizado_em) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[$faseId,$secretariaId,$departamentoId?:null,$tipoId,$nome,$descricao,$perfilEnvio,$obrigatorio,$ordem,agora(),agora()]);
                $id=(int)$pdo->lastInsertId();
            }
            if (isset($_FILES['arquivo_modelo']) && $_FILES['arquivo_modelo']['error']!==UPLOAD_ERR_NO_FILE) {
                $tipo=dbOne($pdo,'SELECT extensoes FROM tipos_documento WHERE id=?',[$tipoId]);
                $permitidas=array_values(array_filter(explode(',',(string)($tipo['extensoes']??''))));
                $up=salvarUpload('arquivo_modelo','dados_workflow/arquivos/modelos','modelo-'.$id,$permitidas);
                $anterior=dbOne($pdo,'SELECT * FROM modelos_documentos WHERE requisito_id=? AND ativo=1 ORDER BY id DESC LIMIT 1',[$id]);
                dbExec($pdo,'UPDATE modelos_documentos SET ativo=0 WHERE requisito_id=?',[$id]);
                dbExec($pdo,'INSERT INTO modelos_documentos(requisito_id,arquivo_original,arquivo_salvo,tamanho,usuario,email,criado_em) VALUES(?,?,?,?,?,?,?)',[$id,$up['original'],$up['caminho'],$up['tamanho'],$usuarioLogado,$emailUsuario,agora()]);
                registrarHistorico($pdo,['municipio_id'=>$municipioId,'fase_id'=>$faseId,'requisito_id'=>$id,'evento'=>$anterior?'Modelo substituído':'Modelo disponibilizado','tipo_arquivo'=>'modelo','arquivo_original'=>$up['original'],'arquivo_salvo'=>$up['caminho'],'arquivo_anterior_original'=>$anterior['arquivo_original']??'','tamanho'=>$up['tamanho'],'usuario'=>$usuarioLogado,'email'=>$emailUsuario,'perfil'=>'STRATELLI','motivo'=>$anterior?'Atualização do modelo vinculada à configuração do documento.':'Primeiro modelo vinculado ao documento.','status'=>'modelo']);
            }
            flash('ok','Regra documental salva e vinculada à fase.'); redirectTo($retornoBase+['secao'=>'documentos']);
        }
        if ($acao === 'alternar_requisito') {
            dbExec($pdo,'UPDATE requisitos_documentais SET ativo=CASE WHEN ativo=1 THEN 0 ELSE 1 END,atualizado_em=? WHERE id=?',[agora(),(int)$_POST['id']]);
            flash('ok','Status da regra documental alterado.'); redirectTo($retornoBase+['secao'=>'documentos']);
        }
        if ($acao === 'upload_modelo') {
            if ($perfil!=='stratelli') throw new RuntimeException('Apenas a Stratelli pode disponibilizar modelos.');
            $reqId=(int)($_POST['requisito_id']??0);
            $reqIds=array_values(array_unique(array_filter(array_map('intval',preg_split('/[,;\s]+/',(string)($_POST['requisito_ids']??''),-1,PREG_SPLIT_NO_EMPTY)?:[]))));
            if($reqId>0&&!in_array($reqId,$reqIds,true))$reqIds[]=$reqId;
            if(!$reqIds)throw new RuntimeException('Documento configurado não encontrado.');
            $marcadores=implode(',',array_fill(0,count($reqIds),'?'));
            $reqs=dbAll($pdo,'SELECT r.*,t.extensoes FROM requisitos_documentais r JOIN tipos_documento t ON t.id=r.tipo_documento_id WHERE r.id IN ('.$marcadores.') ORDER BY r.id',$reqIds);
            if(!$reqs)throw new RuntimeException('Documento configurado não encontrado.');
            $req=$reqs[0];
            foreach($reqs as $reqGrupo){
                if((int)$reqGrupo['fase_id']!==(int)$req['fase_id']
                    ||(int)$reqGrupo['tipo_documento_id']!==(int)$req['tipo_documento_id']
                    ||trim((string)$reqGrupo['nome'])!==trim((string)$req['nome'])
                    ||(string)$reqGrupo['perfil_envio']!==(string)$req['perfil_envio']
                    ||(int)$reqGrupo['obrigatorio']!==(int)$req['obrigatorio']){
                    throw new RuntimeException('Os documentos selecionados não podem compartilhar o mesmo modelo.');
                }
            }
            $up=salvarUpload('arquivo_modelo','dados_workflow/arquivos/modelos','modelo-grupo-'.$reqId,array_values(array_filter(explode(',',$req['extensoes']))));
            $pdo->beginTransaction();
            foreach($reqs as $reqGrupo){
                $idGrupo=(int)$reqGrupo['id'];
                $anterior=dbOne($pdo,'SELECT * FROM modelos_documentos WHERE requisito_id=? AND ativo=1 ORDER BY id DESC LIMIT 1',[$idGrupo]);
                dbExec($pdo,'UPDATE modelos_documentos SET ativo=0 WHERE requisito_id=?',[$idGrupo]);
                dbExec($pdo,'INSERT INTO modelos_documentos(requisito_id,arquivo_original,arquivo_salvo,tamanho,usuario,email,criado_em) VALUES(?,?,?,?,?,?,?)',[$idGrupo,$up['original'],$up['caminho'],$up['tamanho'],$usuarioLogado,$emailUsuario,agora()]);
                registrarHistorico($pdo,['municipio_id'=>$municipioId,'fase_id'=>$reqGrupo['fase_id'],'requisito_id'=>$idGrupo,'evento'=>$anterior?'Modelo substituído':'Modelo disponibilizado','tipo_arquivo'=>'modelo','arquivo_original'=>$up['original'],'arquivo_salvo'=>$up['caminho'],'arquivo_anterior_original'=>$anterior['arquivo_original']??'','tamanho'=>$up['tamanho'],'usuario'=>$usuarioLogado,'email'=>$emailUsuario,'perfil'=>'STRATELLI','motivo'=>count($reqs)>1?'Modelo único aplicado a todas as secretarias vinculadas a este documento.':($anterior?'Modelo atualizado pela Stratelli.':'Primeiro modelo disponibilizado.'),'status'=>'modelo']);
            }
            $pdo->commit();
            flash('ok',count($reqs)>1?'Modelo aplicado a todas as secretarias deste documento.':'Modelo disponibilizado com sucesso.');
            redirectTo(['perfil'=>$perfil,'municipio'=>$municipioId,'view'=>'workflow','fase'=>$req['fase_id']] + $contextoSecretaria);
        }
        if ($acao === 'enviar_documento') {
            $reqId=(int)$_POST['requisito_id'];
            $req=dbOne($pdo,'SELECT r.*,t.extensoes FROM requisitos_documentais r JOIN tipos_documento t ON t.id=r.tipo_documento_id WHERE r.id=? AND r.ativo=1',[$reqId]);
            if (!$req) throw new RuntimeException('Documento configurado não encontrado.');
            $podeEnviarDocumento = $req['perfil_envio']===$perfil
                || ($perfil==='secretaria' && $req['perfil_envio']==='municipio' && (int)$req['secretaria_id']===$secretariaPerfilId);
            if (!$podeEnviarDocumento) throw new RuntimeException('Seu perfil não é o responsável pelo envio deste documento.');
            $up=salvarUpload('arquivo','dados_workflow/arquivos/envios/'.slug($municipioId),'documento-'.$reqId,array_values(array_filter(explode(',',$req['extensoes']))));
            $anterior=dbOne($pdo,'SELECT * FROM documentos_enviados WHERE municipio_id=? AND requisito_id=? ORDER BY id DESC LIMIT 1',[$municipioId,$reqId]);
            $versao=(int)($anterior['versao']??0)+1;
            $status=$perfil==='stratelli'?'aprovado':'aguardando';
            dbExec($pdo,'INSERT INTO documentos_enviados(municipio_id,requisito_id,versao,arquivo_original,arquivo_salvo,tamanho,status,enviado_por,enviado_email,enviado_em,validado_por,validado_em) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',[$municipioId,$reqId,$versao,$up['original'],$up['caminho'],$up['tamanho'],$status,$usuarioLogado,$emailUsuario,agora(),$perfil==='stratelli'?$usuarioLogado:'',$perfil==='stratelli'?agora():null]);
            registrarHistorico($pdo,['municipio_id'=>$municipioId,'fase_id'=>$req['fase_id'],'requisito_id'=>$reqId,'evento'=>$anterior?'Documento substituído':'Documento enviado','tipo_arquivo'=>'documento','arquivo_original'=>$up['original'],'arquivo_salvo'=>$up['caminho'],'arquivo_anterior_original'=>$anterior['arquivo_original']??'','tamanho'=>$up['tamanho'],'usuario'=>$usuarioLogado,'email'=>$emailUsuario,'perfil'=>strtoupper($perfil),'motivo'=>$anterior?($anterior['status']==='correcao'&&$anterior['observacao_validacao']?$anterior['observacao_validacao']:'Nova versão enviada para substituir a anterior.'):'Primeiro envio do documento.','status'=>$status]);
            flash('ok','Documento enviado com sucesso.'); redirectTo(['perfil'=>$perfil,'municipio'=>$municipioId,'view'=>'workflow','fase'=>$req['fase_id']] + $contextoSecretaria);
        }
        if (in_array($acao,['aprovar_documento','corrigir_documento'],true)) {
            if ($perfil!=='stratelli') throw new RuntimeException('Apenas a Stratelli pode validar documentos.');
            $docId=(int)$_POST['documento_id']; $doc=dbOne($pdo,'SELECT d.*,r.fase_id,r.nome FROM documentos_enviados d JOIN requisitos_documentais r ON r.id=d.requisito_id WHERE d.id=?',[$docId]);
            if (!$doc) throw new RuntimeException('Documento enviado não encontrado.');
            if (($doc['status']??'')==='aprovado') throw new RuntimeException('Este documento já está aprovado. Envie uma nova versão para realizar uma nova validação.');
            $status=$acao==='aprovar_documento'?'aprovado':'correcao'; $obs=trim((string)($_POST['observacao']??''));
            if ($status==='correcao' && $obs==='') throw new RuntimeException('Informe o motivo da correção.');
            dbExec($pdo,'UPDATE documentos_enviados SET status=?,observacao_validacao=?,validado_por=?,validado_em=? WHERE id=?',[$status,$obs,$usuarioLogado,agora(),$docId]);
            registrarHistorico($pdo,['municipio_id'=>$doc['municipio_id'],'fase_id'=>$doc['fase_id'],'requisito_id'=>$doc['requisito_id'],'evento'=>$status==='aprovado'?'Documento aprovado':'Correção solicitada','tipo_arquivo'=>'validacao','arquivo_original'=>$doc['arquivo_original'],'arquivo_salvo'=>$doc['arquivo_salvo'],'tamanho'=>$doc['tamanho'],'usuario'=>$usuarioLogado,'email'=>$emailUsuario,'perfil'=>'STRATELLI','motivo'=>$obs?:'Documento conferido e aprovado pela Stratelli.','status'=>$status]);
            flash('ok',$status==='aprovado'?'Documento aprovado.':'Correção solicitada.'); redirectTo(['perfil'=>$perfil,'municipio'=>$municipioId,'view'=>'workflow','fase'=>$doc['fase_id']] + $contextoSecretaria);
        }
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('erro', $e instanceof PDOException && str_contains(strtolower($e->getMessage()), 'unique') ? 'Já existe um cadastro com esta ordem, código ou nome.' : $e->getMessage());
    redirectTo((['perfil'=>$perfil,'municipio'=>$municipioId,'view'=>$view] + $contextoSecretaria) + ($view==='configuracoes'?['secao'=>(string)($_GET['secao']??$_POST['secao']??'fases')]:['fase'=>(int)($_GET['fase']??$_POST['fase_id']??0)]));
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$csrf = csrfToken();

$fasesTodas = dbAll($pdo, 'SELECT * FROM fases ORDER BY ordem,id');
$fasesPermitidasSecretaria=[];
if($perfil==='secretaria'){
    foreach(dbAll($pdo,"SELECT DISTINCT fase_id FROM requisitos_documentais WHERE ativo=1 AND perfil_envio='municipio' AND secretaria_id=?",[$secretariaPerfilId]) as $fasePermitidaSecretaria){
        $fasesPermitidasSecretaria[(int)$fasePermitidaSecretaria['fase_id']]=true;
    }
}
$fasesVisiveis = array_values(array_filter($fasesTodas, fn($f) => (int)$f['ativo']===1
    && ($perfil==='stratelli' || !(int)$f['exclusivo_stratelli'])
    && ($perfil!=='secretaria' || isset($fasesPermitidasSecretaria[(int)$f['id']]))));
$faseAtualId = (int) ($_GET['fase'] ?? ($fasesVisiveis[0]['id'] ?? 0));
$atividade = null;
foreach ($fasesVisiveis as $f) if ((int)$f['id']===$faseAtualId) $atividade=$f;
if (!$atividade && $fasesVisiveis) { $atividade=$fasesVisiveis[0]; $faseAtualId=(int)$atividade['id']; }

$secretarias = dbAll($pdo, 'SELECT s.*, GROUP_CONCAT(f.ordem || ": " || f.aba, " • ") fases_vinculadas,
 GROUP_CONCAT(f.id) fase_ids FROM secretarias s LEFT JOIN fase_secretarias fs ON fs.secretaria_id=s.id LEFT JOIN fases f ON f.id=fs.fase_id GROUP BY s.id ORDER BY s.nome');
$departamentos = dbAll($pdo, 'SELECT d.*,s.nome secretaria_nome FROM departamentos d JOIN secretarias s ON s.id=d.secretaria_id ORDER BY s.nome,d.nome');
$tiposDocumento = dbAll($pdo, 'SELECT * FROM tipos_documento ORDER BY nome');
$requisitosTodos = dbAll($pdo, 'SELECT r.*,f.ordem fase_ordem,f.aba fase_aba,f.titulo fase_titulo,s.nome secretaria_nome,s.sigla secretaria_sigla,
 d.nome departamento_nome,t.nome tipo_nome,t.extensoes,
 (SELECT m.id FROM modelos_documentos m WHERE m.requisito_id=r.id AND m.ativo=1 ORDER BY m.id DESC LIMIT 1) modelo_id,
 (SELECT m.arquivo_original FROM modelos_documentos m WHERE m.requisito_id=r.id AND m.ativo=1 ORDER BY m.id DESC LIMIT 1) modelo_nome,
 (SELECT m.arquivo_salvo FROM modelos_documentos m WHERE m.requisito_id=r.id AND m.ativo=1 ORDER BY m.id DESC LIMIT 1) modelo_caminho,
 (SELECT m.tamanho FROM modelos_documentos m WHERE m.requisito_id=r.id AND m.ativo=1 ORDER BY m.id DESC LIMIT 1) modelo_tamanho,
 (SELECT m.criado_em FROM modelos_documentos m WHERE m.requisito_id=r.id AND m.ativo=1 ORDER BY m.id DESC LIMIT 1) modelo_data
 FROM requisitos_documentais r JOIN fases f ON f.id=r.fase_id JOIN secretarias s ON s.id=r.secretaria_id
 LEFT JOIN departamentos d ON d.id=r.departamento_id JOIN tipos_documento t ON t.id=r.tipo_documento_id
 ORDER BY f.ordem,r.ordem,r.id');

$ultimosDocs = [];
foreach (dbAll($pdo, 'SELECT d.* FROM documentos_enviados d JOIN (SELECT requisito_id,MAX(id) max_id FROM documentos_enviados WHERE municipio_id=? GROUP BY requisito_id) x ON x.max_id=d.id', [$municipioId]) as $d) $ultimosDocs[(int)$d['requisito_id']]=$d;

$requisitosVisiveis = array_values(array_filter($requisitosTodos, fn($r)=>(int)$r['ativo']===1 && (
    $perfil==='stratelli'
    || ($perfil==='municipio' && $r['perfil_envio']==='municipio')
    || ($perfil==='secretaria' && $r['perfil_envio']==='municipio' && (int)$r['secretaria_id']===$secretariaPerfilId)
)));
$totalDocs=count($requisitosVisiveis); $totalEnviados=0; $totalAprovados=0; $totalCorrecoes=0; $aguardando=0;
foreach ($requisitosVisiveis as $r) {
    $d=$ultimosDocs[(int)$r['id']]??null;
    if ($d) { $totalEnviados++; if ($d['status']==='aprovado')$totalAprovados++; elseif($d['status']==='correcao')$totalCorrecoes++; else $aguardando++; }
}
$progresso=$totalDocs?round(($totalAprovados/$totalDocs)*100):0;

function statusFase(array $fase, array $requisitos, array $ultimos): string
{
    $docs=array_values(array_filter($requisitos,fn($r)=>(int)$r['fase_id']===(int)$fase['id']&&(int)$r['ativo']===1&&(int)$r['obrigatorio']===1));
    if (!$docs) return 'pending';
    $enviados=0;$aprovados=0;$correcoes=0;
    foreach($docs as $r){$d=$ultimos[(int)$r['id']]??null;if($d){$enviados++;if($d['status']==='aprovado')$aprovados++;elseif($d['status']==='correcao')$correcoes++;}}
    if($aprovados===count($docs))return 'done'; if($correcoes>0)return 'correction'; if($enviados>0)return 'run'; return 'pending';
}
// Cronograma operacional: usa a duração já cadastrada no Gantt e encadeia cada fase
// a partir do dia seguinte à conclusão real da fase anterior.
$cronogramaProcesso=dbOne($pdo,'SELECT * FROM cronograma_processos WHERE municipio_id=?',[$municipioId]);
if(!$cronogramaProcesso){
    $primeiraMovimentacao=dbOne($pdo,'SELECT MIN(data_evento) data_evento FROM (
        SELECT substr(enviado_em,1,10) data_evento FROM documentos_enviados WHERE municipio_id=?
        UNION ALL SELECT substr(criado_em,1,10) FROM historico_documentos WHERE municipio_id=? AND criado_em<>""
    )',[$municipioId,$municipioId]);
    $dataInicioPadrao=(string)($primeiraMovimentacao['data_evento']??'');
    if(!dataIsoValida($dataInicioPadrao))$dataInicioPadrao=date('Y-m-d');
    dbExec($pdo,'INSERT OR IGNORE INTO cronograma_processos(municipio_id,data_inicio,criado_em,atualizado_em) VALUES(?,?,?,?)',[$municipioId,$dataInicioPadrao,agora(),agora()]);
    $cronogramaProcesso=dbOne($pdo,'SELECT * FROM cronograma_processos WHERE municipio_id=?',[$municipioId]);
}
$dataInicioProcesso=(string)($cronogramaProcesso['data_inicio']??date('Y-m-d'));
if(!dataIsoValida($dataInicioProcesso))$dataInicioProcesso=date('Y-m-d');
$hojeCronograma=date('Y-m-d');

$registrosConclusao=[];
foreach(dbAll($pdo,'SELECT * FROM cronograma_fases WHERE municipio_id=?',[$municipioId]) as $registroConclusao){
    $registrosConclusao[(int)$registroConclusao['fase_id']]=$registroConclusao;
}

function conclusaoAutomaticaFase(array $fase, array $requisitos, array $ultimos): ?string
{
    $obrigatorios=array_values(array_filter($requisitos,fn($r)=>(int)$r['fase_id']===(int)$fase['id']&&(int)$r['ativo']===1&&(int)$r['obrigatorio']===1));
    if(!$obrigatorios)return null;
    $maiorTs=0;
    foreach($obrigatorios as $req){
        $doc=$ultimos[(int)$req['id']]??null;
        if(!$doc||($doc['status']??'')!=='aprovado')return null;
        $ts=strtotime((string)($doc['validado_em']?:$doc['enviado_em']));
        if($ts>$maiorTs)$maiorTs=$ts;
    }
    return $maiorTs?date('Y-m-d',$maiorTs):null;
}

$cronogramaPorFase=[];
$fasesCronogramaBase=array_values(array_filter($fasesTodas,fn($f)=>(int)$f['ativo']===1));
$prevPrazo=null;
$prevConclusao=null;
foreach($fasesCronogramaBase as $indiceCronograma=>$faseCronograma){
    $duracaoCronograma=max(1,(int)$faseCronograma['dia_fim']-(int)$faseCronograma['dia_inicio']+1);
    $registroManual=$registrosConclusao[(int)$faseCronograma['id']]??null;
    $conclusaoReal=$registroManual['data_conclusao_real']??conclusaoAutomaticaFase($faseCronograma,$requisitosTodos,$ultimosDocs);
    $conclusaoOrigem=$registroManual?'manual':($conclusaoReal?'automatica':'');
    if($indiceCronograma===0){
        $inicioOperacional=$dataInicioProcesso;
        $bloqueada=false;
    }else{
        if($prevConclusao){
            $inicioOperacional=somarDias($prevConclusao,1);
            $bloqueada=false;
        }else{
            $inicioOperacional=somarDias((string)$prevPrazo,1);
            $bloqueada=true;
        }
    }
    $fimOperacional=somarDias($inicioOperacional,$duracaoCronograma-1);
    $diasRestantes=diferencaDias($hojeCronograma,$fimOperacional);
    $diasDesvio=$conclusaoReal?diferencaDias($fimOperacional,$conclusaoReal):0;
    $limiteAtencao=max(2,(int)ceil($duracaoCronograma*.20));
    if($conclusaoReal){
        $statusPrazo=$diasDesvio>0?'completed-late':'completed-on-time';
    }elseif($bloqueada){
        $statusPrazo='blocked';
    }elseif($hojeCronograma<$inicioOperacional){
        $statusPrazo='scheduled';
    }elseif($diasRestantes<0){
        $statusPrazo='overdue';
    }elseif($diasRestantes<=$limiteAtencao){
        $statusPrazo='attention';
    }else{
        $statusPrazo='on-track';
    }
    $rotuloPrazo=match($statusPrazo){
        'completed-on-time'=>'CONCLUÍDA NO PRAZO',
        'completed-late'=>'CONCLUÍDA COM ATRASO',
        'overdue'=>'ATRASADA',
        'attention'=>'ATENÇÃO AO PRAZO',
        'on-track'=>'NO PRAZO',
        'scheduled'=>'AGENDADA',
        default=>'AGUARDANDO FASE ANTERIOR',
    };
    $textoPrazo=match($statusPrazo){
        'completed-on-time'=>$diasDesvio<0?abs($diasDesvio).' dia(s) antes do limite':'Concluída na data limite',
        'completed-late'=>$diasDesvio.' dia(s) de atraso',
        'overdue'=>abs($diasRestantes).' dia(s) de atraso',
        'attention'=>$diasRestantes===0?'Vence hoje':'Restam '.$diasRestantes.' dia(s)',
        'on-track'=>'Restam '.$diasRestantes.' dia(s)',
        'scheduled'=>'Início previsto em '.dataCurtaBr($inicioOperacional),
        default=>'A data definitiva depende da conclusão anterior',
    };
    if($hojeCronograma<$inicioOperacional)$percentualTempo=0;
    else{
        $diasConsumidos=diferencaDias($inicioOperacional,min($hojeCronograma,$fimOperacional))+1;
        $percentualTempo=max(0,min(100,round(($diasConsumidos/$duracaoCronograma)*100)));
    }
    $percentualHojeNaFase=$percentualTempo;
    if($conclusaoReal)$percentualHojeNaFase=100;
    elseif($hojeCronograma<$inicioOperacional)$percentualHojeNaFase=0;
    elseif($hojeCronograma>$fimOperacional)$percentualHojeNaFase=100;
    $cronogramaPorFase[(int)$faseCronograma['id']]=[
        'fase_id'=>(int)$faseCronograma['id'],
        'duracao'=>$duracaoCronograma,
        'inicio'=>$inicioOperacional,
        'fim'=>$fimOperacional,
        'conclusao_real'=>$conclusaoReal,
        'conclusao_origem'=>$conclusaoOrigem,
        'bloqueada'=>$bloqueada,
        'status'=>$statusPrazo,
        'rotulo'=>$rotuloPrazo,
        'texto'=>$textoPrazo,
        'dias_restantes'=>$diasRestantes,
        'dias_desvio'=>$diasDesvio,
        'percentual_tempo'=>$percentualTempo,
        'percentual_hoje_fase'=>$percentualHojeNaFase,
        'provisorio'=>$bloqueada,
        'registro_manual'=>$registroManual,
    ];
    $prevPrazo=$fimOperacional;
    $prevConclusao=$conclusaoReal;
}
$diaAtualProjeto=max(1,diferencaDias($dataInicioProcesso,$hojeCronograma)+1);
$maxDiaCronograma=90; foreach($fasesVisiveis as $faseMaxCronograma)$maxDiaCronograma=max($maxDiaCronograma,(int)$faseMaxCronograma['dia_fim']);
$percentualHojeGantt=max(0,min(100,(($diaAtualProjeto-1)/max(1,$maxDiaCronograma-1))*100));

$fasesConcluidas=0;
foreach($fasesVisiveis as $f){
    $prazoFaseContagem=$cronogramaPorFase[(int)$f['id']]??null;
    if(!empty($prazoFaseContagem['conclusao_real'])||statusFase($f,$requisitosTodos,$ultimosDocs)==='done')$fasesConcluidas++;
}

// A fase atual é única para todo o processo municipal. Ela é calculada com a visão
// completa da Stratelli, considerando todos os documentos obrigatórios e os registros
// de conclusão das fases, sem avançar separadamente por secretaria.
$statusGlobalPorFase=[];
$faseGlobalAtual=null;
$faseGlobalAtualStatus='pending';
$fasesGlobaisAtivas=array_values(array_filter($fasesTodas,fn($faseGlobal)=>(int)$faseGlobal['ativo']===1));
foreach($fasesGlobaisAtivas as $faseGlobal){
    $prazoGlobal=$cronogramaPorFase[(int)$faseGlobal['id']]??null;
    $statusGlobal=!empty($prazoGlobal['conclusao_real'])?'done':statusFase($faseGlobal,$requisitosTodos,$ultimosDocs);
    $statusGlobalPorFase[(int)$faseGlobal['id']]=$statusGlobal;
    if(!$faseGlobalAtual && $statusGlobal!=='done'){
        $faseGlobalAtual=$faseGlobal;
        $faseGlobalAtualStatus=$statusGlobal;
    }
}
if(!$faseGlobalAtual && $fasesGlobaisAtivas){
    $faseGlobalAtual=$fasesGlobaisAtivas[count($fasesGlobaisAtivas)-1];
    $faseGlobalAtualStatus='done';
}

// Dashboard situacional dos três perfis. O Gestor Municipal consolida todas as secretarias
// e todos os documentos de responsabilidade municipal nas fases visíveis.
// Para a Stratelli, a visão permanece completa.
$dashboardRequisitosBase=array_values(array_filter($requisitosTodos,fn($r)=>(int)$r['ativo']===1&&(
    $perfil==='stratelli'
    || ($perfil==='municipio'&&($r['perfil_envio']??'')==='municipio')
    || ($perfil==='secretaria'&&($r['perfil_envio']??'')==='municipio'&&(int)$r['secretaria_id']===$secretariaPerfilId)
)));
$fasesSituacionais=[];
$faseSituacional=null;
$faseSituacionalStatus='pending';
foreach($fasesVisiveis as $faseSituacionalItem){
    $prazoSituacionalItem=$cronogramaPorFase[(int)$faseSituacionalItem['id']]??null;
    $statusSituacional=$perfil==='secretaria'
        ?($statusGlobalPorFase[(int)$faseSituacionalItem['id']]??'pending')
        :(!empty($prazoSituacionalItem['conclusao_real'])?'done':statusFase($faseSituacionalItem,$dashboardRequisitosBase,$ultimosDocs));
    $faseSituacionalItem['prazo_operacional']=$prazoSituacionalItem;
    $faseSituacionalItem['status_situacional']=$statusSituacional;
    $fasesSituacionais[]=$faseSituacionalItem;
    if(!$faseSituacional && $statusSituacional!=='done'){
        $faseSituacional=$faseSituacionalItem;
        $faseSituacionalStatus=$statusSituacional;
    }
}
if($perfil==='secretaria' && $faseGlobalAtual){
    // Toda secretaria enxerga exatamente a mesma fase atual definida pelo processo global.
    $faseSituacional=$faseGlobalAtual;
    $faseSituacionalStatus=$faseGlobalAtualStatus;
}elseif(!$faseSituacional && $fasesSituacionais){
    $faseSituacional=$fasesSituacionais[count($fasesSituacionais)-1];
    $faseSituacionalStatus='done';
}
$prazoDashboard=$faseSituacional?($cronogramaPorFase[(int)$faseSituacional['id']]??null):null;
$dashboardFaseStatusLabel=match($faseSituacionalStatus){
    'done'=>'CONCLUÍDA',
    'correction'=>'CORREÇÃO SOLICITADA',
    'run'=>'EM ANDAMENTO',
    default=>'AGUARDANDO INÍCIO',
};
$dashboardFaseStatusClass=match($faseSituacionalStatus){
    'done'=>'done',
    'correction'=>'correction',
    'run'=>'running',
    default=>'pending',
};
$dashboardRequisitosFase=$faseSituacional?array_values(array_filter($dashboardRequisitosBase,fn($r)=>(int)$r['fase_id']===(int)$faseSituacional['id'])):[];
$dashboardFaseTotal=count($dashboardRequisitosFase);
$dashboardFaseEnviados=$dashboardFaseAprovados=$dashboardFaseCorrecoes=$dashboardFaseAguardando=$dashboardFaseNaoEntregues=0;
$dashboardSecretariasPendentes=[];
foreach($dashboardRequisitosFase as $dashboardReq){
    $dashboardDoc=$ultimosDocs[(int)$dashboardReq['id']]??null;
    if(!$dashboardDoc){
        $dashboardFaseNaoEntregues++;
    }else{
        $dashboardFaseEnviados++;
        if(($dashboardDoc['status']??'')==='aprovado')$dashboardFaseAprovados++;
        elseif(($dashboardDoc['status']??'')==='correcao')$dashboardFaseCorrecoes++;
        else $dashboardFaseAguardando++;
    }

    // Secretarias só aparecem como devedoras quando o envio é de responsabilidade municipal.
    if(($dashboardReq['perfil_envio']??'')!=='municipio')continue;
    $statusPendencia='';
    $rotuloPendencia='';
    if(!$dashboardDoc){
        $statusPendencia='not-delivered';
        $rotuloPendencia='NÃO ENTREGUE';
    }elseif(($dashboardDoc['status']??'')==='correcao'){
        $statusPendencia='needs-correction';
        $rotuloPendencia='EM CORREÇÃO';
    }else{
        continue;
    }
    $secId=(int)($dashboardReq['secretaria_id']??0);
    $secKey=$secId>0?(string)$secId:'sec_'.md5((string)($dashboardReq['secretaria_nome']??'secretaria'));
    if(!isset($dashboardSecretariasPendentes[$secKey])){
        $dashboardSecretariasPendentes[$secKey]=[
            'id'=>$secId,
            'nome'=>(string)($dashboardReq['secretaria_nome']??'Secretaria'),
            'sigla'=>(string)($dashboardReq['secretaria_sigla']??''),
            'nao_entregues'=>0,
            'correcoes'=>0,
            'documentos'=>[],
        ];
    }
    if($statusPendencia==='not-delivered')$dashboardSecretariasPendentes[$secKey]['nao_entregues']++;
    else $dashboardSecretariasPendentes[$secKey]['correcoes']++;
    $dashboardSecretariasPendentes[$secKey]['documentos'][]=[
        'nome'=>(string)$dashboardReq['nome'],
        'departamento'=>(string)($dashboardReq['departamento_nome']??''),
        'tipo'=>(string)($dashboardReq['tipo_nome']??''),
        'status_visual'=>$statusPendencia,
        'rotulo'=>$rotuloPendencia,
        'observacao'=>$statusPendencia==='needs-correction'?(string)($dashboardDoc['observacao_validacao']??''):''
    ];
}
$dashboardSecretariasPendentes=array_values($dashboardSecretariasPendentes);
foreach($dashboardSecretariasPendentes as &$dashboardSecretaria){
    $dashboardSecretaria['total_pendencias']=(int)$dashboardSecretaria['nao_entregues']+(int)$dashboardSecretaria['correcoes'];
    $dashboardSecretaria['status_visual']=(int)$dashboardSecretaria['nao_entregues']>0?'not-delivered':'needs-correction';
}
unset($dashboardSecretaria);
usort($dashboardSecretariasPendentes,function($a,$b){
    if((int)$a['nao_entregues']!==(int)$b['nao_entregues'])return (int)$b['nao_entregues']<=>(int)$a['nao_entregues'];
    if((int)$a['correcoes']!==(int)$b['correcoes'])return (int)$b['correcoes']<=>(int)$a['correcoes'];
    return strcasecmp((string)$a['nome'],(string)$b['nome']);
});
$dashboardTotalPendencias=array_sum(array_column($dashboardSecretariasPendentes,'total_pendencias'));
$dashboardFaseProgresso=$dashboardFaseTotal?round(($dashboardFaseAprovados/$dashboardFaseTotal)*100):0;
$dashboardFasesConcluidas=count(array_filter($fasesSituacionais,fn($f)=>($f['status_situacional']??'')==='done'));
$dashboardQtdFases=count($fasesSituacionais);
$dashboardTrackResto=$dashboardQtdFases%5;
$dashboardTrackColsVazias=$dashboardQtdFases?($dashboardTrackResto===0?0:5-$dashboardTrackResto):0;
$dashboardResumoCard=[
    'titulo'=>$perfil==='stratelli'?'Resumo geral do processo':($perfil==='secretaria'?'Resumo da secretaria':'Resumo municipal'),
    'progresso'=>$progresso.'%',
    'pendencias'=>(string)$dashboardTotalPendencias,
    'analise'=>(string)$dashboardFaseAguardando,
    'fases'=>$dashboardFasesConcluidas.'/'.$dashboardQtdFases,
    'prazo'=>(string)($prazoDashboard['rotulo']??'—'),
];

// No perfil Secretaria, a navegação do Workflow fica bloqueada na fase global
// definida pelo andamento completo do processo, e não pelo desempenho isolado da unidade.
$fasesWorkflowTabs=$fasesVisiveis;
$faseRestritaSecretariaId=null;
if($perfil==='secretaria' && $faseGlobalAtual){
    $faseRestritaSecretariaId=(int)$faseGlobalAtual['id'];
    $fasesWorkflowTabs=[$faseGlobalAtual];
    $atividade=$faseGlobalAtual;
    $faseAtualId=$faseRestritaSecretariaId;
    if($view==='workflow' && (int)($_GET['fase']??$faseRestritaSecretariaId)!==$faseRestritaSecretariaId){
        redirectTo(['view'=>'workflow','fase'=>$faseRestritaSecretariaId,'perfil'=>$perfil,'municipio'=>$municipioId] + $contextoSecretaria);
    }
}


// Centro de notificações contextual. O sino mostra ocorrências relevantes para o perfil ativo,
// sem expor documentos de outras secretarias no perfil restrito.
$fasesPorIdNotificacao=[];
foreach($fasesTodas as $faseNotificacao)$fasesPorIdNotificacao[(int)$faseNotificacao['id']]=$faseNotificacao;
$notificacoes=[];
$ordemFaseSituacional=(int)($faseSituacional['ordem']??PHP_INT_MAX);
$adicionarNotificacao=static function(array &$lista,string $tipo,string $icone,string $titulo,string $texto,int $faseId,string $data='',int $prioridade=100,bool $acao=true):void{
    $lista[]=[
        'tipo'=>$tipo,
        'icone'=>$icone,
        'titulo'=>$titulo,
        'texto'=>$texto,
        'fase_id'=>$faseId,
        'data'=>$data,
        'prioridade'=>$prioridade,
        'acao'=>$acao,
    ];
};
foreach($requisitosVisiveis as $requisitoNotificacao){
    $faseIdNotificacao=(int)$requisitoNotificacao['fase_id'];
    $faseNotificacao=$fasesPorIdNotificacao[$faseIdNotificacao]??null;
    if(!$faseNotificacao||(int)$faseNotificacao['ordem']>$ordemFaseSituacional)continue;
    $documentoNotificacao=$ultimosDocs[(int)$requisitoNotificacao['id']]??null;
    $nomeDocumentoNotificacao=(string)$requisitoNotificacao['nome'];
    $secretariaNomeNotificacao=(string)($requisitoNotificacao['secretaria_nome']??'Secretaria');
    $faseNomeNotificacao='Fase '.(string)$faseNotificacao['ordem'].' · '.(string)$faseNotificacao['aba'];

    if(!$documentoNotificacao){
        if($faseSituacional&&$faseIdNotificacao===(int)$faseSituacional['id']&&($requisitoNotificacao['perfil_envio']??'')==='municipio'){
            $tituloNotificacao=$perfil==='stratelli'?'Entrega municipal pendente':'Documento ainda não enviado';
            $textoNotificacao=$nomeDocumentoNotificacao.' · '.$secretariaNomeNotificacao.' · '.$faseNomeNotificacao;
            $adicionarNotificacao($notificacoes,'not-delivered','×',$tituloNotificacao,$textoNotificacao,$faseIdNotificacao,'',320,true);
        }
        continue;
    }

    $statusNotificacao=(string)($documentoNotificacao['status']??'aguardando');
    if($statusNotificacao==='aguardando'){
        $tituloNotificacao=$perfil==='stratelli'?'Documento aguardando validação':'Documento aguardando análise';
        $textoNotificacao=$nomeDocumentoNotificacao.' · '.$secretariaNomeNotificacao.' · enviado em '.dataBr($documentoNotificacao['enviado_em']??null);
        $adicionarNotificacao($notificacoes,'waiting','…',$tituloNotificacao,$textoNotificacao,$faseIdNotificacao,(string)($documentoNotificacao['enviado_em']??''),$perfil==='stratelli'?430:250,$perfil==='stratelli');
    }elseif($statusNotificacao==='correcao'){
        $tituloNotificacao=$perfil==='stratelli'?'Aguardando reenvio corrigido':'Correção solicitada pela Stratelli';
        $motivoNotificacao=trim((string)($documentoNotificacao['observacao_validacao']??''));
        $textoNotificacao=$nomeDocumentoNotificacao.' · '.$secretariaNomeNotificacao.($motivoNotificacao!==''?' · '.$motivoNotificacao:'');
        $adicionarNotificacao($notificacoes,'correction','!',$tituloNotificacao,$textoNotificacao,$faseIdNotificacao,(string)($documentoNotificacao['validado_em']??$documentoNotificacao['enviado_em']??''),410,true);
    }elseif($statusNotificacao==='aprovado'){
        $dataAprovacaoNotificacao=(string)($documentoNotificacao['validado_em']??'');
        $tsAprovacaoNotificacao=$dataAprovacaoNotificacao!==''?strtotime($dataAprovacaoNotificacao):false;
        if($faseSituacional&&$faseIdNotificacao===(int)$faseSituacional['id']&&$tsAprovacaoNotificacao!==false&&$tsAprovacaoNotificacao>=strtotime('-7 days')){
            $adicionarNotificacao($notificacoes,'approved','✓','Documento aprovado',$nomeDocumentoNotificacao.' · '.$secretariaNomeNotificacao,$faseIdNotificacao,$dataAprovacaoNotificacao,120,false);
        }
    }
}
if($faseSituacional&&$prazoDashboard){
    $statusPrazoNotificacao=(string)($prazoDashboard['status']??'');
    if(in_array($statusPrazoNotificacao,['overdue','completed-late'],true)){
        $adicionarNotificacao($notificacoes,'not-delivered','⌛','Prazo da fase em atraso','Fase '.(string)$faseSituacional['ordem'].' · '.(string)($prazoDashboard['texto']??'Prazo ultrapassado'),(int)$faseSituacional['id'],$hojeCronograma,500,true);
    }elseif($statusPrazoNotificacao==='attention'){
        $adicionarNotificacao($notificacoes,'correction','⌛','Atenção ao prazo da fase','Fase '.(string)$faseSituacional['ordem'].' · '.(string)($prazoDashboard['texto']??'Prazo próximo'),(int)$faseSituacional['id'],$hojeCronograma,450,true);
    }
}
usort($notificacoes,static function($a,$b){
    if((int)$a['prioridade']!==(int)$b['prioridade'])return (int)$b['prioridade']<=>(int)$a['prioridade'];
    $ta=!empty($a['data'])?(strtotime((string)$a['data'])?:0):0;
    $tb=!empty($b['data'])?(strtotime((string)$b['data'])?:0):0;
    return $tb<=>$ta;
});
$notificacaoContagemAtiva=count(array_filter($notificacoes,fn($n)=>!empty($n['acao'])));
$notificacaoTotal=count($notificacoes);
$notificacoesExibidas=array_slice($notificacoes,0,8);
$notificacoesRestantes=max(0,$notificacaoTotal-count($notificacoesExibidas));
$notificacaoRotuloPerfil=$perfil==='stratelli'?'Validações e andamento':($perfil==='secretaria'?'Avisos da secretaria':'Avisos do Município');

$requisitosFase=$atividade?array_values(array_filter($requisitosTodos,fn($r)=>(int)$r['fase_id']===(int)$atividade['id']&&(int)$r['ativo']===1&&(
    $perfil==='stratelli'
    || ($perfil==='municipio'&&$r['perfil_envio']==='municipio')
    || ($perfil==='secretaria'&&$r['perfil_envio']==='municipio'&&(int)$r['secretaria_id']===$secretariaPerfilId)
))):[];

// Indicadores do Workflow da Secretaria: sempre restritos à secretaria logada
// e exclusivamente à fase global atual definida pela Stratelli.
$secretariaTemDocumentosFaseAtual=$perfil!=='secretaria'||count($requisitosFase)>0;
$workflowSecretariaTotal=count($requisitosFase);
$workflowSecretariaEnviados=0;
$workflowSecretariaAguardando=0;
$workflowSecretariaCorrecoes=0;
$workflowSecretariaPendentes=0;
if($perfil==='secretaria'){
    foreach($requisitosFase as $requisitoWorkflowSecretaria){
        $documentoWorkflowSecretaria=$ultimosDocs[(int)$requisitoWorkflowSecretaria['id']]??null;
        if(!$documentoWorkflowSecretaria){
            $workflowSecretariaPendentes++;
            continue;
        }
        $workflowSecretariaEnviados++;
        if(($documentoWorkflowSecretaria['status']??'')==='correcao')$workflowSecretariaCorrecoes++;
        elseif(($documentoWorkflowSecretaria['status']??'')!=='aprovado')$workflowSecretariaAguardando++;
    }
}

// Agrupamento visual dos documentos da fase. O banco continua preservando um requisito,
// um envio, um status e um histórico para cada secretaria/departamento. Somente a interface
// reúne documentos equivalentes para exibir um único modelo e as entregas individualizadas.
$gruposDocumentosFase=[];
$gruposBaseDocumentos=[];
$normalizarGrupo=static function($valor):string{
    $valor=(string)$valor;
    $valor=function_exists('mb_strtolower')?mb_strtolower($valor,'UTF-8'):strtolower($valor);
    $valor=trim($valor);
    return preg_replace('/\s+/u',' ',$valor)?:$valor;
};
foreach($requisitosFase as $requisitoGrupo){
    $chaveBase=implode('|',[
        (int)$requisitoGrupo['fase_id'],
        $normalizarGrupo($requisitoGrupo['nome']),
        (int)$requisitoGrupo['tipo_documento_id'],
        (string)$requisitoGrupo['perfil_envio'],
        (int)$requisitoGrupo['obrigatorio'],
    ]);
    $gruposBaseDocumentos[$chaveBase][]=$requisitoGrupo;
}
foreach($gruposBaseDocumentos as $itensBaseDocumento){
    $assinaturasModelo=[];
    foreach($itensBaseDocumento as $itemBaseDocumento){
        if(!empty($itemBaseDocumento['modelo_id'])){
            $assinatura=$normalizarGrupo($itemBaseDocumento['modelo_nome']).'|'.(int)$itemBaseDocumento['modelo_tamanho'];
            $assinaturasModelo[$assinatura]=true;
        }
    }
    $subgruposDocumento=[];
    if(count($assinaturasModelo)<=1){
        $subgruposDocumento[]=$itensBaseDocumento;
    }else{
        foreach($itensBaseDocumento as $itemBaseDocumento){
            $assinatura=!empty($itemBaseDocumento['modelo_id'])
                ?$normalizarGrupo($itemBaseDocumento['modelo_nome']).'|'.(int)$itemBaseDocumento['modelo_tamanho']
                :'__sem_modelo__';
            $subgruposDocumento[$assinatura][]=$itemBaseDocumento;
        }
    }
    foreach($subgruposDocumento as $itensDocumento){
        usort($itensDocumento,fn($a,$b)=>[(int)$a['ordem'],(int)$a['id']]<=>[(int)$b['ordem'],(int)$b['id']]);
        $referenciaDocumento=$itensDocumento[0];
        $referenciaModelo=null;
        foreach($itensDocumento as $itemDocumento){
            if(!empty($itemDocumento['modelo_id'])){$referenciaModelo=$itemDocumento;break;}
        }
        $entregasDocumento=[];
        $contagemStatus=['approved'=>0,'waiting'=>0,'correction'=>0,'pending'=>0];
        foreach($itensDocumento as $itemDocumento){
            $docItem=$ultimosDocs[(int)$itemDocumento['id']]??null;
            [$rotuloItem,$classeItem]=statusDocumento($docItem);
            $contagemStatus[$classeItem]=($contagemStatus[$classeItem]??0)+1;
            $entregasDocumento[]=['requisito'=>$itemDocumento,'documento'=>$docItem,'rotulo'=>$rotuloItem,'classe'=>$classeItem];
        }
        $totalEntregasDocumento=count($entregasDocumento);
        if($contagemStatus['correction']>0){$rotuloGeral='CORREÇÃO PENDENTE';$classeGeral='correction';}
        elseif($contagemStatus['waiting']>0){$rotuloGeral='EM ANÁLISE';$classeGeral='waiting';}
        elseif($contagemStatus['approved']===$totalEntregasDocumento){$rotuloGeral='TODAS APROVADAS';$classeGeral='approved';}
        else{$rotuloGeral=$contagemStatus['pending'].' ENTREGA(S) PENDENTE(S)';$classeGeral='pending';}
        $gruposDocumentosFase[]=[
            'referencia'=>$referenciaDocumento,
            'modelo'=>$referenciaModelo,
            'requisitos'=>$itensDocumento,
            'ids'=>implode(',',array_map(fn($x)=>(int)$x['id'],$itensDocumento)),
            'entregas'=>$entregasDocumento,
            'contagem'=>$contagemStatus,
            'rotulo_geral'=>$rotuloGeral,
            'classe_geral'=>$classeGeral,
            'ordem'=>(int)$referenciaDocumento['ordem'],
        ];
    }
}
usort($gruposDocumentosFase,fn($a,$b)=>[$a['ordem'],(int)$a['referencia']['id']]<=>[$b['ordem'],(int)$b['referencia']['id']]);

$prazoAtividade=$atividade?($cronogramaPorFase[(int)$atividade['id']]??null):null;

// Prazo apresentado à Secretaria no resumo da fase. Quando ainda existe ação
// da secretaria, exibe a quantidade de dias disponível até a data-limite.
$workflowPrazoEntregaTexto='—';
$workflowPrazoEntregaComplemento='';
$workflowPrazoEntregaClasse=(string)($prazoAtividade['status']??'blocked');
if($perfil==='secretaria'){
    $secretariaAindaPrecisaAgir=($workflowSecretariaPendentes+$workflowSecretariaCorrecoes)>0;
    if($secretariaAindaPrecisaAgir){
        $diasRestantesSecretaria=(int)($prazoAtividade['dias_restantes']??0);
        $statusPrazoSecretaria=(string)($prazoAtividade['status']??'blocked');
        if(in_array($statusPrazoSecretaria,['on-track','attention'],true)){
            if($diasRestantesSecretaria===0)$workflowPrazoEntregaTexto='Vence hoje';
            elseif($diasRestantesSecretaria===1)$workflowPrazoEntregaTexto='Resta 1 dia';
            else $workflowPrazoEntregaTexto='Restam '.$diasRestantesSecretaria.' dias';
            $workflowPrazoEntregaComplemento=!empty($prazoAtividade['fim'])?'Entrega até '.dataCurtaBr($prazoAtividade['fim']):'';
        }elseif($statusPrazoSecretaria==='overdue'){
            $diasAtrasoSecretaria=abs($diasRestantesSecretaria);
            $workflowPrazoEntregaTexto=$diasAtrasoSecretaria===1?'Prazo encerrado há 1 dia':'Prazo encerrado há '.$diasAtrasoSecretaria.' dias';
            $workflowPrazoEntregaComplemento='Envio ou correção em atraso';
        }elseif($statusPrazoSecretaria==='scheduled'){
            $workflowPrazoEntregaTexto='Prazo ainda não iniciado';
            $workflowPrazoEntregaComplemento=!empty($prazoAtividade['inicio'])?'Início em '.dataCurtaBr($prazoAtividade['inicio']):'';
        }else{
            $workflowPrazoEntregaTexto='Aguardando fase anterior';
            $workflowPrazoEntregaComplemento='A data será liberada pelo andamento global';
        }
    }elseif($workflowSecretariaAguardando>0){
        $workflowPrazoEntregaTexto='Entrega realizada';
        $workflowPrazoEntregaComplemento='Aguardando validação da Stratelli';
        $workflowPrazoEntregaClasse='waiting';
    }elseif($workflowSecretariaTotal>0){
        $workflowPrazoEntregaTexto='Entregas concluídas';
        $workflowPrazoEntregaComplemento='Documentos aprovados pela Stratelli';
        $workflowPrazoEntregaClasse='completed-on-time';
    }
}

// Painel didático de situação documental da fase. O verde representa somente
// documentos aprovados pela Stratelli. Arquivos enviados e ainda não validados
// possuem um estado próprio, separado das entregas efetivamente aprovadas.
$resumoEntregasFase=[];
$resumoEntregues=$resumoAguardandoValidacao=$resumoCorrecoes=$resumoNaoEntregues=0;
foreach($requisitosFase as $requisitoResumo){
    $documentoResumo=$ultimosDocs[(int)$requisitoResumo['id']]??null;
    if(!$documentoResumo){
        $statusResumo='not-delivered';
        $rotuloResumo='NÃO ENTREGUE';
        $iconeResumo='×';
        $resumoNaoEntregues++;
    }elseif(($documentoResumo['status']??'')==='correcao'){
        $statusResumo='needs-correction';
        $rotuloResumo='CORREÇÃO NECESSÁRIA';
        $iconeResumo='!';
        $resumoCorrecoes++;
    }elseif(($documentoResumo['status']??'')==='aprovado'){
        $statusResumo='delivered';
        $rotuloResumo='ENTREGUE E APROVADO';
        $iconeResumo='✓';
        $resumoEntregues++;
    }else{
        $statusResumo='awaiting-validation';
        $rotuloResumo='AGUARDANDO VALIDAÇÃO';
        $iconeResumo='…';
        $resumoAguardandoValidacao++;
    }
    $resumoEntregasFase[]=[
        'requisito'=>$requisitoResumo,
        'documento'=>$documentoResumo,
        'status_visual'=>$statusResumo,
        'rotulo'=>$rotuloResumo,
        'icone'=>$iconeResumo,
    ];
}
$totalResumoEntregas=count($resumoEntregasFase);
$percentualEntregues=$totalResumoEntregas?($resumoEntregues/$totalResumoEntregas)*100:0;
$percentualAguardandoValidacao=$totalResumoEntregas?($resumoAguardandoValidacao/$totalResumoEntregas)*100:0;
$percentualCorrecoes=$totalResumoEntregas?($resumoCorrecoes/$totalResumoEntregas)*100:0;
$percentualNaoEntregues=$totalResumoEntregas?($resumoNaoEntregues/$totalResumoEntregas)*100:0;

// Consolidado por secretaria para mostrar, de forma imediata, quem ainda precisa
// entregar documentos ou reenviar arquivos em correção nesta fase.
$secretariasResumoFase=[];
foreach($resumoEntregasFase as $itemResumo){
    $reqSecretaria=$itemResumo['requisito'];
    $secId=(int)($reqSecretaria['secretaria_id']??0);
    $secChave=$secId>0?(string)$secId:'sec_'.md5((string)($reqSecretaria['secretaria_nome']??'secretaria'));
    if(!isset($secretariasResumoFase[$secChave])){
        $secretariasResumoFase[$secChave]=[
            'id'=>$secId,
            'nome'=>(string)($reqSecretaria['secretaria_nome']??'Secretaria'),
            'total'=>0,
            'delivered'=>0,
            'awaiting_validation'=>0,
            'needs_correction'=>0,
            'not_delivered'=>0,
            'pendencias'=>0,
            'documentos_pendentes'=>[],
            'documentos_correcao'=>[],
            'documentos_aguardando'=>[],
        ];
    }
    $secretariasResumoFase[$secChave]['total']++;
    $nomeDocSecretaria=(string)($reqSecretaria['nome']??'Documento');
    if(!empty($reqSecretaria['departamento_nome'])) $nomeDocSecretaria.=' — '.(string)$reqSecretaria['departamento_nome'];
    if($itemResumo['status_visual']==='not-delivered'){
        $secretariasResumoFase[$secChave]['not_delivered']++;
        $secretariasResumoFase[$secChave]['pendencias']++;
        $secretariasResumoFase[$secChave]['documentos_pendentes'][]=$nomeDocSecretaria;
    }elseif($itemResumo['status_visual']==='needs-correction'){
        $secretariasResumoFase[$secChave]['needs_correction']++;
        $secretariasResumoFase[$secChave]['pendencias']++;
        $secretariasResumoFase[$secChave]['documentos_correcao'][]=$nomeDocSecretaria;
    }elseif($itemResumo['status_visual']==='awaiting-validation'){
        $secretariasResumoFase[$secChave]['awaiting_validation']++;
        $secretariasResumoFase[$secChave]['documentos_aguardando'][]=$nomeDocSecretaria;
    }else{
        $secretariasResumoFase[$secChave]['delivered']++;
    }
}
$secretariasPendentesFase=array_values(array_filter($secretariasResumoFase,fn($sec)=>(int)$sec['pendencias']>0));
foreach($secretariasPendentesFase as &$secretariaResumo){
    $partesResumo=[];
    if((int)$secretariaResumo['not_delivered']>0)$partesResumo[]=$secretariaResumo['not_delivered'].' não '.((int)$secretariaResumo['not_delivered']===1?'entregue':'entregues');
    if((int)$secretariaResumo['needs_correction']>0)$partesResumo[]=$secretariaResumo['needs_correction'].' em correção';
    $secretariaResumo['resumo_pendencia']=$partesResumo?implode(' · ',$partesResumo):'Nenhuma pendência';
    $secretariaResumo['status_visual']=(int)$secretariaResumo['not_delivered']>0?'not-delivered':'needs-correction';
    $secretariaResumo['rotulo']=(int)$secretariaResumo['not_delivered']>0?'ENVIO PENDENTE':'CORREÇÃO PENDENTE';
}
unset($secretariaResumo);
usort($secretariasPendentesFase,function($a,$b){
    return [(int)$b['not_delivered'],(int)$b['needs_correction'],strcasecmp((string)$a['nome'],(string)$b['nome'])] <=> [(int)$a['not_delivered'],(int)$a['needs_correction'],strcasecmp((string)$b['nome'],(string)$a['nome'])];
});

$statusAtual=$atividade?(!empty($prazoAtividade['conclusao_real'])?'done':statusFase($atividade,$requisitosTodos,$ultimosDocs)):'pending';
$faseVisualClass=match($statusAtual){'done'=>'phase-complete','correction'=>'phase-correction',default=>'phase-pending'};
$faseStatusLabel=match($statusAtual){'done'=>'CONCLUÍDA','correction'=>'CORREÇÃO SOLICITADA','run'=>'EM ANDAMENTO',default=>'PENDENTE'};

$historicoFase=[];
if($atividade){
    if($perfil==='secretaria'){
        $historicoFase=dbAll($pdo,'SELECT h.*,r.nome documento FROM historico_documentos h JOIN requisitos_documentais r ON r.id=h.requisito_id WHERE h.fase_id=? AND (h.municipio_id=? OR h.municipio_id="") AND r.secretaria_id=? AND r.perfil_envio="municipio" ORDER BY h.id DESC LIMIT 100',[$atividade['id'],$municipioId,$secretariaPerfilId]);
    }else{
        $historicoFase=dbAll($pdo,'SELECT h.*,r.nome documento FROM historico_documentos h LEFT JOIN requisitos_documentais r ON r.id=h.requisito_id WHERE h.fase_id=? AND (h.municipio_id=? OR h.municipio_id="") ORDER BY h.id DESC LIMIT 100',[$atividade['id'],$municipioId]);
    }
}
$logsPorPagina=6;$totalLogsFase=count($historicoFase);$totalPaginasLog=max(1,(int)ceil($totalLogsFase/$logsPorPagina));$paginaLog=max(1,min($totalPaginasLog,(int)($_GET['log_pagina']??1)));$inicioLog=($paginaLog-1)*$logsPorPagina;$historicoFasePagina=array_slice($historicoFase,$inicioLog,$logsPorPagina);$primeiroRegistroLog=$totalLogsFase?$inicioLog+1:0;$ultimoRegistroLog=min($inicioLog+$logsPorPagina,$totalLogsFase);


// Conteúdo dos módulos do menu lateral.
$municipiosClientes=[
    [
        'id'=>'maringa','nome'=>'Maringá','uf'=>'PR','status'=>'MVP ativo','classe'=>'active','tem_dados'=>true,
        'fase'=>$faseSituacional?'Fase '.$faseSituacional['ordem'].' · '.$faseSituacional['aba']:'Sem fase ativa',
        'progresso'=>$progresso,'documentos'=>$totalDocs,'pendencias'=>$dashboardTotalPendencias,
        'descricao'=>'Município com processo, documentos, cronograma e histórico disponíveis no MVP.'
    ],
    [
        'id'=>'uberlandia','nome'=>'Uberlândia','uf'=>'MG','status'=>'Aguardando implantação','classe'=>'waiting','tem_dados'=>false,
        'fase'=>'Sem dados cadastrados','progresso'=>0,'documentos'=>0,'pendencias'=>0,
        'descricao'=>'Cliente cadastrado para composição do portfólio. Estrutura documental ainda não iniciada.'
    ],
    [
        'id'=>'sao-bernardo-do-campo','nome'=>'São Bernardo do Campo','uf'=>'SP','status'=>'Aguardando implantação','classe'=>'waiting','tem_dados'=>false,
        'fase'=>'Sem dados cadastrados','progresso'=>0,'documentos'=>0,'pendencias'=>0,
        'descricao'=>'Cliente cadastrado para composição do portfólio. Estrutura documental ainda não iniciada.'
    ],
];

$documentosPagina=[];
$documentosPorFasePagina=[];
if(in_array($perfil,['stratelli','municipio'],true)){
    foreach($requisitosVisiveis as $reqPagina){
        if(!(int)$reqPagina['ativo'])continue;
        $docPagina=$ultimosDocs[(int)$reqPagina['id']]??null;
        [$rotuloDocPagina,$classeDocPagina]=statusDocumento($docPagina);
        $itemDocPagina=[
            'requisito'=>$reqPagina,
            'documento'=>$docPagina,
            'rotulo'=>$rotuloDocPagina,
            'classe'=>$classeDocPagina,
            'tem_modelo'=>!empty($reqPagina['modelo_id']),
        ];
        $documentosPagina[]=$itemDocPagina;
        $faseChave=(int)$reqPagina['fase_id'];
        if(!isset($documentosPorFasePagina[$faseChave])){
            $documentosPorFasePagina[$faseChave]=[
                'fase_id'=>$faseChave,
                'ordem'=>(int)$reqPagina['fase_ordem'],
                'aba'=>$reqPagina['fase_aba'],
                'titulo'=>$reqPagina['fase_titulo'],
                'itens'=>[],
            ];
        }
        $documentosPorFasePagina[$faseChave]['itens'][]=$itemDocPagina;
    }
    uasort($documentosPorFasePagina,fn($a,$b)=>[$a['ordem'],$a['fase_id']]<=>[$b['ordem'],$b['fase_id']]);
}
$documentosPaginaAprovados=count(array_filter($documentosPagina,fn($x)=>$x['classe']==='approved'));
$documentosPaginaAguardando=count(array_filter($documentosPagina,fn($x)=>$x['classe']==='waiting'));
$documentosPaginaCorrecao=count(array_filter($documentosPagina,fn($x)=>$x['classe']==='correction'));
$documentosPaginaPendentes=count(array_filter($documentosPagina,fn($x)=>$x['classe']==='pending'));
$documentosPaginaComModelo=count(array_filter($documentosPagina,fn($x)=>$x['tem_modelo']));

$relatorioFases=[];
foreach($fasesVisiveis as $faseRelatorio){
    $requisitosRelatorio=array_values(array_filter($requisitosVisiveis,fn($r)=>(int)$r['fase_id']===(int)$faseRelatorio['id']&&(int)$r['ativo']===1));
    $aprovadosRelatorio=$analiseRelatorio=$correcaoRelatorio=$pendentesRelatorio=0;
    foreach($requisitosRelatorio as $reqRelatorio){
        $docRelatorio=$ultimosDocs[(int)$reqRelatorio['id']]??null;
        if(!$docRelatorio)$pendentesRelatorio++;
        elseif(($docRelatorio['status']??'')==='aprovado')$aprovadosRelatorio++;
        elseif(($docRelatorio['status']??'')==='correcao')$correcaoRelatorio++;
        else $analiseRelatorio++;
    }
    $totalRelatorio=count($requisitosRelatorio);
    $relatorioFases[]=[
        'fase'=>$faseRelatorio,
        'total'=>$totalRelatorio,
        'aprovados'=>$aprovadosRelatorio,
        'analise'=>$analiseRelatorio,
        'correcao'=>$correcaoRelatorio,
        'pendentes'=>$pendentesRelatorio,
        'progresso'=>$totalRelatorio?round(($aprovadosRelatorio/$totalRelatorio)*100):0,
        'prazo'=>$cronogramaPorFase[(int)$faseRelatorio['id']]??null,
    ];
}
$relatorioSecretarias=[];
foreach($requisitosVisiveis as $reqRelSec){
    $secKey=(int)$reqRelSec['secretaria_id'];
    if(!isset($relatorioSecretarias[$secKey]))$relatorioSecretarias[$secKey]=[
        'nome'=>$reqRelSec['secretaria_nome'],'sigla'=>$reqRelSec['secretaria_sigla'],'total'=>0,'aprovados'=>0,'analise'=>0,'correcao'=>0,'pendentes'=>0
    ];
    $relatorioSecretarias[$secKey]['total']++;
    $docRelSec=$ultimosDocs[(int)$reqRelSec['id']]??null;
    if(!$docRelSec)$relatorioSecretarias[$secKey]['pendentes']++;
    elseif(($docRelSec['status']??'')==='aprovado')$relatorioSecretarias[$secKey]['aprovados']++;
    elseif(($docRelSec['status']??'')==='correcao')$relatorioSecretarias[$secKey]['correcao']++;
    else $relatorioSecretarias[$secKey]['analise']++;
}
foreach($relatorioSecretarias as &$secRelatorio)$secRelatorio['progresso']=$secRelatorio['total']?round(($secRelatorio['aprovados']/$secRelatorio['total'])*100):0;
unset($secRelatorio);
uasort($relatorioSecretarias,fn($a,$b)=>[$b['pendentes']+$b['correcao'],$a['nome']]<=>[$a['pendentes']+$a['correcao'],$b['nome']]);

$historicoTipoFiltro=trim((string)($_GET['historico_tipo']??''));
$historicoFaseFiltro=max(0,(int)($_GET['historico_fase']??0));
$historicoOrdenacoesPermitidas=[
    'evento'=>'LOWER(COALESCE(h.evento,""))',
    'fase'=>'COALESCE(f.ordem,999999)',
    'documento'=>'LOWER(COALESCE(r.nome,""))',
    'secretaria'=>'LOWER(COALESCE(s.nome,""))',
    'responsavel'=>'LOWER(COALESCE(h.usuario,""))',
    'perfil'=>'LOWER(COALESCE(h.perfil,""))',
    'data'=>'h.criado_em',
    'detalhamento'=>'LOWER(COALESCE(h.motivo,""))',
];
$historicoOrdemSolicitada=(string)($_GET['historico_ordem']??'data');
$historicoOrdem=array_key_exists($historicoOrdemSolicitada,$historicoOrdenacoesPermitidas)?$historicoOrdemSolicitada:'data';
$historicoDirecao=strtolower((string)($_GET['historico_direcao']??'desc'))==='asc'?'asc':'desc';
$historicoSql='SELECT h.*,r.nome documento,r.perfil_envio,r.secretaria_id,f.ordem fase_ordem,f.aba fase_aba,s.nome secretaria_nome,s.sigla secretaria_sigla
    FROM historico_documentos h
    LEFT JOIN requisitos_documentais r ON r.id=h.requisito_id
    LEFT JOIN fases f ON f.id=h.fase_id
    LEFT JOIN secretarias s ON s.id=r.secretaria_id
    WHERE (h.municipio_id=? OR h.municipio_id="")';
$historicoParams=[$municipioId];
if($perfil==='municipio')$historicoSql.=' AND r.perfil_envio="municipio"';
if($perfil==='secretaria'){
    $historicoSql.=' AND r.perfil_envio="municipio" AND r.secretaria_id=?';
    $historicoParams[]=$secretariaPerfilId;
}
if($historicoFaseFiltro>0){$historicoSql.=' AND h.fase_id=?';$historicoParams[]=$historicoFaseFiltro;}
if($historicoTipoFiltro!==''){
    $historicoSql.=' AND LOWER(h.evento) LIKE ?';
    $historicoParams[]='%'.strtolower($historicoTipoFiltro).'%';
}
$historicoSql.=' ORDER BY '.$historicoOrdenacoesPermitidas[$historicoOrdem].' '.strtoupper($historicoDirecao).', h.id DESC';
$historicoGeralCompleto=dbAll($pdo,$historicoSql,$historicoParams);
$historicoResumo=[
    'total'=>count($historicoGeralCompleto),
    'envios'=>count(array_filter($historicoGeralCompleto,fn($h)=>str_contains(strtolower((string)$h['evento']),'envi')||str_contains(strtolower((string)$h['evento']),'substitu'))),
    'aprovacoes'=>count(array_filter($historicoGeralCompleto,fn($h)=>str_contains(strtolower((string)$h['evento']),'aprov'))),
    'correcoes'=>count(array_filter($historicoGeralCompleto,fn($h)=>str_contains(strtolower((string)$h['evento']),'corre'))),
    'modelos'=>count(array_filter($historicoGeralCompleto,fn($h)=>str_contains(strtolower((string)$h['evento']),'modelo'))),
];
$historicoPorPagina=10;
$historicoTotalRegistros=count($historicoGeralCompleto);
$historicoTotalPaginas=max(1,(int)ceil($historicoTotalRegistros/$historicoPorPagina));
$historicoPagina=max(1,min($historicoTotalPaginas,(int)($_GET['historico_pagina']??1)));
$historicoInicio=($historicoPagina-1)*$historicoPorPagina;
$historicoGeral=array_slice($historicoGeralCompleto,$historicoInicio,$historicoPorPagina);
$historicoPrimeiroRegistro=$historicoTotalRegistros?$historicoInicio+1:0;
$historicoUltimoRegistro=min($historicoInicio+$historicoPorPagina,$historicoTotalRegistros);
$historicoQueryBase=[
    'view'=>'historico',
    'perfil'=>$perfil,
    'municipio'=>$municipioId,
];
if($perfil==='secretaria')$historicoQueryBase['secretaria']=$secretariaPerfilId;
if($historicoFaseFiltro>0)$historicoQueryBase['historico_fase']=$historicoFaseFiltro;
if($historicoTipoFiltro!=='')$historicoQueryBase['historico_tipo']=$historicoTipoFiltro;
$historicoQueryBase['historico_ordem']=$historicoOrdem;
$historicoQueryBase['historico_direcao']=$historicoDirecao;
$historicoOrdenacaoBase=$historicoQueryBase;
unset($historicoOrdenacaoBase['historico_ordem'],$historicoOrdenacaoBase['historico_direcao']);
$historicoCabecalhosOrdenaveis=[
    'evento'=>'Evento',
    'fase'=>'Fase',
    'documento'=>'Documento / Arquivo',
    'secretaria'=>'Secretaria',
    'responsavel'=>'Responsável',
    'perfil'=>'Perfil',
    'data'=>'Data / Hora',
    'detalhamento'=>'Detalhamento',
];
$historicoPaginasVisiveis=[];
if($historicoTotalPaginas<=7){
    $historicoPaginasVisiveis=range(1,$historicoTotalPaginas);
}else{
    $historicoPaginasVisiveis=array_values(array_unique(array_filter([
        1,
        $historicoPagina-2,
        $historicoPagina-1,
        $historicoPagina,
        $historicoPagina+1,
        $historicoPagina+2,
        $historicoTotalPaginas,
    ],fn($paginaHistorico)=>$paginaHistorico>=1&&$paginaHistorico<=$historicoTotalPaginas)));
    sort($historicoPaginasVisiveis);
}

$secao=(string)($_GET['secao']??'fases');
$editarFase=isset($_GET['editar_fase'])?dbOne($pdo,'SELECT * FROM fases WHERE id=?',[(int)$_GET['editar_fase']]):null;
$editarSecretaria=isset($_GET['editar_secretaria'])?dbOne($pdo,'SELECT * FROM secretarias WHERE id=?',[(int)$_GET['editar_secretaria']]):null;
$editarDepartamento=isset($_GET['editar_departamento'])?dbOne($pdo,'SELECT * FROM departamentos WHERE id=?',[(int)$_GET['editar_departamento']]):null;
$editarTipo=isset($_GET['editar_tipo'])?dbOne($pdo,'SELECT * FROM tipos_documento WHERE id=?',[(int)$_GET['editar_tipo']]):null;
$editarRequisito=isset($_GET['editar_requisito'])?dbOne($pdo,'SELECT * FROM requisitos_documentais WHERE id=?',[(int)$_GET['editar_requisito']]):null;
$faseIdsSecretaria=[];
if($editarSecretaria)foreach(dbAll($pdo,'SELECT fase_id FROM fase_secretarias WHERE secretaria_id=?',[$editarSecretaria['id']])as$x)$faseIdsSecretaria[]=(int)$x['fase_id'];
$maxDia=90;foreach($fasesVisiveis as$f)$maxDia=max($maxDia,(int)$f['dia_fim']);
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>INPACTA | <?=h(match($view){'dashboard'=>'Dashboard Situacional','municipios'=>'Municípios','documentos'=>'Documentos','relatorios'=>'Relatórios','historico'=>'Histórico','configuracoes'=>'Configurações',default=>'Workflow'})?></title>
<style>

            :root {
                --navy: #082a55;
                --navy2: #0a4475;
                --ink: #08224b;
                --muted: #60708a;
                --line: #dfe7f0;
                --bg: #f6f9fc;
                --card: #fff;
                --blue: #176fdd;
                --purple: #6b35d4;
                --green: #18a64a;
                --orange: #ff9200;
                --red: #e43b38;
                --shadow: 0 8px 24px rgba(7, 35, 75, 0.05);
            }
            * {
                box-sizing: border-box;
            }
            body {
                margin: 0;
                font-family:
                    Inter,
                    Segoe UI,
                    Arial,
                    sans-serif;
                background: var(--bg);
                color: var(--ink);
            }
            .app {
                display: grid;
                grid-template-columns: 280px 1fr;
                min-height: 100vh;
            }
            .side {
                background: #fff;
                border-right: 1px solid var(--line);
                padding: 20px 18px;
                position: sticky;
                top: 0;
                height: 100vh;
            }
            .brand {
                display: flex;
                align-items: center;
                gap: 10px;
                font-weight: 900;
                line-height: 1.05;
            }
            .brand .mark {
                font-size: 38px;
                letter-spacing: -4px;
            }
            .brand small {
                font-size: 11px;
            }
            .user {
                margin: 24px 0 14px;
                padding: 14px;
                border: 1px solid #edf1f6;
                background: #f8fafc;
                border-radius: 14px;
                display: flex;
                gap: 11px;
                align-items: center;
            }
            .avatar {
                width: 36px;
                height: 36px;
                border-radius: 9px;
                background: #dfeaf6;
                display: grid;
                place-items: center;
                font-weight: 900;
            }
            .user b {
                font-size: 13px;
            }
            .user span {
                font-size: 11px;
                color: var(--muted);
            }
            .role {
                display: inline-block;
                margin-top: 4px;
                padding: 3px 7px;
                border-radius: 999px;
                background: #dbeafe;
                color: #0f5ab5 !important;
                font-weight: 900;
            }
            .role.stratelli {
                background: #eee5ff;
                color: #6430c5 !important;
            }
            .role.secretaria {
                background: #e5f7ee;
                color: #167448 !important;
            }
            .menu {
                display: grid;
                gap: 6px;
                margin-top: 16px;
            }
            .mi {
                padding: 12px 12px;
                border-radius: 10px;
                font-weight: 800;
                font-size: 14px;
                color: #233a5c;
            }
            .mi.active {
                background: linear-gradient(90deg, var(--navy), var(--navy2));
                color: #fff;
            }
            .profilebox {
                position: absolute;
                bottom: 22px;
                left: 18px;
                right: 18px;
                padding: 15px;
                border-radius: 13px;
                background: linear-gradient(90deg, var(--navy), var(--navy2));
                color: white;
            }
            .profilebox.stratelli {
                background: linear-gradient(90deg, #5e2db4, #743bd8);
            }
            .profilebox.secretaria {
                background: linear-gradient(90deg, #126f4a, #1c8c60);
            }
            .secretaria-profile-select {
                display: inline-flex;
                align-items: center;
                gap: 7px;
            }
            .secretaria-profile-select[hidden] { display: none !important; }
            .profilebox small {
                display: block;
                opacity: 0.8;
            }
            .profilebox b {
                display: block;
                margin-top: 4px;
            }
            .main {
                min-width: 0;
            }
            .top {
                background: #fff;
                border-bottom: 1px solid var(--line);
                padding: 18px 28px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .top-right {
                display: flex;
                gap: 18px;
                align-items: center;
                font-size: 14px;
            }
            .wrap {
                padding: 24px 28px 34px;
            }
            .hero {
                display: flex;
                justify-content: space-between;
                gap: 18px;
                align-items: flex-start;
            }
            .hero h1 {
                margin: 0;
                font-size: 24px;
            }
            .hero p {
                margin: 7px 0 0;
                color: var(--muted);
            }
            .notice {
                min-width: 360px;
                max-width: 430px;
                border: 1px solid #b8d8ff;
                background: #f3f9ff;
                color: #24528b;
                border-radius: 8px;
                padding: 12px 14px;
                font-size: 12px;
                line-height: 1.45;
            }
            .notice.purple {
                border-color: #d8c2ff;
                background: #faf7ff;
                color: #5d37a7;
            }
            .kpis {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 18px;
                margin: 24px 0 16px;
                align-items: stretch;
            }
            .workflow-kpis {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
            .workflow-kpis.secretaria {
                grid-template-columns: repeat(5, minmax(0, 1fr));
            }
            .kpi {
                position: relative;
                display: flex;
                flex-direction: column;
                justify-content: flex-start;
                background: #fff;
                border: 1px solid #d9e3ee;
                border-radius: 14px;
                padding: 18px 18px 16px;
                min-height: 112px;
                box-shadow: none;
                overflow: hidden;
            }
            .kpi::before {
                content: "";
                position: absolute;
                left: -1px;
                right: -1px;
                top: -1px;
                height: 4px;
                border-radius: 14px 14px 0 0;
                background: #dfe7f0;
            }
            .kpi:nth-child(3n + 1)::before {
                background: #ff3b3f;
            }
            .kpi:nth-child(3n + 2)::before {
                background: #f39a0a;
            }
            .kpi:nth-child(3n)::before {
                background: #158c43;
            }
            .kpi small {
                display: block;
                font-size: 11px;
                line-height: 1.25;
                font-weight: 500;
                letter-spacing: 0.02em;
                text-transform: uppercase;
                color: #60708a;
                margin-bottom: 14px;
                word-break: break-word;
            }
            .kpi b {
                display: block;
                font-size: 22px;
                line-height: 1.05;
                margin: 0 0 10px;
                color: var(--navy);
                word-break: break-word;
            }
            .kpi span {
                display: block;
                font-size: 12px;
                line-height: 1.35;
                color: var(--muted);
                margin-top: auto;
                word-break: break-word;
            }
            .kpi.success::before {
                background: #20a84d;
            }
            .kpi.waiting::before {
                background: #176fdd;
            }
            .kpi.warning::before {
                background: #f2b632;
            }
            .kpi.alert::before {
                background: #df4541;
            }
            .phase-tabs {
                display: grid;
                grid-template-columns: repeat(8, 1fr);
                background: #fff;
                border: 2px solid <?= $perfil==='stratelli'?'#6b35d4':'#176fdd' ?>;
                border-radius: 9px;
                overflow: hidden;
                margin-bottom: 14px;
            }
            .phase-tabs.municipio {
                grid-template-columns: repeat(7, 1fr);
            }
            .phase-tab {
                text-decoration: none;
                color: var(--ink);
                padding: 10px 8px;
                text-align: center;
                font-size: 12px;
                border-right: 1px solid #eef2f7;
            }
            .phase-tab:last-child {
                border-right: 0;
            }
            .phase-tab b {
                display: block;
                font-size: 13px;
                margin-bottom: 4px;
            }
            .phase-tab.active {
                background: #f5f9ff;
                box-shadow: inset 0 0 0 1px #176fdd;
            }
            .phase-tab.active.stratelli {
                background: #faf7ff;
                box-shadow: inset 0 0 0 1px #6b35d4;
            }
            .contract-preview {
                --preview-accent: #176fdd;
                margin: 0 0 18px;
                padding: 17px 17px 14px;
                background: #fff;
                border: 1px solid #d9e3ee;
                border-radius: 14px;
                box-shadow: var(--shadow);
                overflow: hidden;
            }
            .contract-preview.stratelli {
                --preview-accent: #6b35d4;
            }
            .contract-preview-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 14px;
                flex-wrap: wrap;
                margin-bottom: 13px;
            }
            .contract-preview-head h2 {
                margin: 0 0 4px;
                font-size: 17px;
                color: var(--navy);
            }
            .contract-preview-head p {
                margin: 0;
                color: var(--muted);
                font-size: 11px;
                line-height: 1.45;
            }
            .contract-preview-law {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                padding: 7px 10px;
                border: 1px solid #cfe0f6;
                border-radius: 999px;
                background: #f3f8ff;
                color: #285b93;
                font-size: 9px;
                font-weight: 900;
                white-space: nowrap;
            }
            .contract-preview-law::before {
                content: "⚖";
                font-size: 13px;
            }
            .contract-preview-scroll {
                overflow-x: auto;
                overflow-y: hidden;
                padding: 3px 1px 8px;
                scrollbar-width: thin;
            }
            .contract-preview-flow {
                display: grid;
                grid-auto-flow: column;
                grid-auto-columns: minmax(145px, 1fr);
                min-width: max(100%, calc(var(--phase-count, 1) * 145px));
                gap: 10px;
                align-items: stretch;
            }
            .contract-preview-stage {
                position: relative;
                display: grid;
                grid-template-rows: 38px auto auto;
                min-width: 0;
                color: inherit;
                text-decoration: none;
                border-radius: 12px;
                cursor: pointer;
                outline: none;
            }
            .contract-preview-stage:hover .contract-preview-card,
            .contract-preview-stage:focus-visible .contract-preview-card {
                transform: translateY(-3px);
                box-shadow: 0 9px 22px rgba(8,42,85,.14);
            }
            .contract-preview-stage:hover .contract-preview-milestone,
            .contract-preview-stage:focus-visible .contract-preview-milestone {
                filter: brightness(1.06);
            }
            .contract-preview-stage:focus-visible {
                box-shadow: 0 0 0 3px rgba(23,111,221,.24);
            }
            .contract-preview-stage:not(:first-child)::before {
                content: "";
                position: absolute;
                top: 15px;
                right: calc(50% + 16px);
                width: calc(100% - 22px);
                height: 8px;
                border-radius: 999px;
                background:
                    linear-gradient(135deg, transparent 0 38%, #cbd5e1 39% 55%, transparent 56%) 0 0 / 18px 8px repeat-x,
                    #eef2f7;
                z-index: 0;
            }
            .contract-preview-number {
                position: relative;
                z-index: 2;
                justify-self: center;
                display: grid;
                place-items: center;
                width: 36px;
                height: 36px;
                border: 3px solid #fff;
                border-radius: 50%;
                background: #7b8797;
                color: #fff;
                box-shadow: 0 2px 7px rgba(8,42,85,.18);
                font-size: 16px;
                font-weight: 900;
            }
            .contract-preview-stage.completed .contract-preview-number { background: #20a84d; }
            .contract-preview-stage.current .contract-preview-number { background: var(--preview-accent); }
            .contract-preview-stage.attention .contract-preview-number { background: #d89000; }
            .contract-preview-stage.overdue .contract-preview-number { background: #df4541; }
            .contract-preview-card {
                position: relative;
                display: flex;
                transition: transform .18s ease, box-shadow .18s ease;
                flex-direction: column;
                min-height: 184px;
                margin-top: -1px;
                border: 1px solid #dce5ef;
                border-radius: 10px;
                background: #fff;
                overflow: hidden;
                box-shadow: 0 4px 12px rgba(8,42,85,.05);
            }
            .contract-preview-stage.current .contract-preview-card {
                border-color: var(--preview-accent);
                box-shadow: 0 0 0 2px color-mix(in srgb, var(--preview-accent) 13%, transparent);
            }
            .contract-preview-stage.completed .contract-preview-card { border-color: #a7dfb8; }
            .contract-preview-stage.attention .contract-preview-card { border-color: #f2d184; }
            .contract-preview-stage.overdue .contract-preview-card { border-color: #f0aaa7; }
            .contract-preview-date {
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 38px;
                padding: 7px 8px;
                background: #0d3f73;
                color: #fff;
                text-align: center;
                font-size: 9px;
                font-weight: 900;
                line-height: 1.25;
                text-transform: uppercase;
            }
            .contract-preview-stage.current .contract-preview-date { background: var(--preview-accent); }
            .contract-preview-stage.completed .contract-preview-date { background: #15733a; }
            .contract-preview-stage.attention .contract-preview-date { background: #b77700; }
            .contract-preview-stage.overdue .contract-preview-date { background: #b93430; }
            .contract-preview-body {
                flex: 1;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 8px;
                padding: 12px 10px 10px;
                text-align: center;
            }
            .contract-preview-icon {
                display: grid;
                place-items: center;
                width: 42px;
                height: 42px;
                border-radius: 12px;
                background: #edf4fb;
                color: #0d3f73;
                font-size: 22px;
                font-weight: 900;
            }
            .contract-preview-stage.current .contract-preview-icon {
                background: color-mix(in srgb, var(--preview-accent) 12%, #fff);
                color: var(--preview-accent);
            }
            .contract-preview-body strong {
                display: block;
                font-size: 11px;
                line-height: 1.3;
                color: var(--navy);
            }
            .contract-preview-body small {
                display: block;
                color: #65758a;
                font-size: 8px;
                line-height: 1.35;
                font-weight: 800;
                text-transform: uppercase;
            }
            .contract-preview-duration {
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 30px;
                padding: 6px 8px;
                border-top: 1px solid #e4eaf1;
                background: #f2f5f9;
                color: #4e6077;
                font-size: 9px;
                font-weight: 900;
            }
            .contract-preview-stage.current .contract-preview-duration {
                background: #eaf3ff;
                color: var(--preview-accent);
            }
            .contract-preview-milestone {
                position: relative;
                display: flex;
                transition: filter .18s ease;
                align-items: center;
                justify-content: center;
                min-height: 45px;
                margin: 13px 3px 0;
                padding: 7px 8px;
                border-radius: 8px;
                background: #0d3f73;
                color: #fff;
                text-align: center;
                font-size: 8px;
                font-weight: 800;
                line-height: 1.3;
            }
            .contract-preview-milestone::before {
                content: "";
                position: absolute;
                top: -14px;
                left: 50%;
                width: 1px;
                height: 14px;
                background: #8ba0b8;
                border-left: 1px dashed #8ba0b8;
            }
            .contract-preview-stage.current .contract-preview-milestone { background: var(--preview-accent); }
            .contract-preview-stage.completed .contract-preview-milestone { background: #15733a; }
            .contract-preview-stage.attention .contract-preview-milestone { background: #b77700; }
            .contract-preview-stage.overdue .contract-preview-milestone { background: #b93430; }
            .contract-preview-footer {
                display: grid;
                grid-template-columns: minmax(190px,.55fr) minmax(0,1.45fr);
                gap: 10px;
                align-items: stretch;
                margin-top: 8px;
            }
            .contract-preview-legal,
            .contract-preview-note {
                display: flex;
                align-items: center;
                gap: 10px;
                min-height: 58px;
                padding: 10px 12px;
                border: 1px solid #dce5ef;
                border-radius: 10px;
                background: #f8fafc;
            }
            .contract-preview-legal-icon {
                flex: 0 0 auto;
                display: grid;
                place-items: center;
                width: 36px;
                height: 36px;
                border-radius: 50%;
                background: #0d3f73;
                color: #fff;
                font-size: 17px;
            }
            .contract-preview-legal strong,
            .contract-preview-note strong {
                display: block;
                margin-bottom: 2px;
                font-size: 9px;
                color: var(--navy);
                text-transform: uppercase;
            }
            .contract-preview-legal span,
            .contract-preview-note span {
                display: block;
                color: #5d7086;
                font-size: 9px;
                line-height: 1.4;
            }
            .dashboard-process-preview .contract-preview-law::before {
                content: "●";
                color: #20a84d;
                font-size: 9px;
            }
            .dashboard-process-preview .contract-preview-milestone.phase-indicator-list {
                display: flex !important;
                flex-direction: column !important;
                align-items: stretch !important;
                justify-content: center !important;
                gap: 0 !important;
                min-height: 118px;
                padding: 9px 11px;
                font-weight: 900;
                line-height: 1.25;
                letter-spacing: -.005em;
                text-align: left;
            }
            .dashboard-process-preview .contract-preview-milestone .phase-indicator-item {
                display: block !important;
                flex: 0 0 auto !important;
                width: 100% !important;
                min-height: 23px;
                padding: 5px 0;
                border-bottom: 1px solid rgba(255,255,255,.20);
                font-size: clamp(8px, .66vw, 11px);
                line-height: 1.2;
                white-space: nowrap;
            }
            .dashboard-process-preview .contract-preview-milestone .phase-indicator-item:last-child {
                border-bottom: 0;
            }
            .dashboard-process-preview .contract-preview-milestone .phase-no-documents {
                display: grid;
                place-items: center;
                min-height: 72px;
                text-align: center;
            }
            .dashboard-process-preview .contract-preview-duration {
                text-transform: uppercase;
                letter-spacing: .02em;
            }
            .dashboard-process-footer {
                grid-template-columns: minmax(0,1.25fr) minmax(220px,.75fr);
            }
            .dashboard-process-summary {
                align-items: stretch;
            }
            .dashboard-process-summary-content {
                flex: 1 1 auto;
                min-width: 0;
            }
            .dashboard-process-summary-metrics {
                display: grid;
                grid-template-columns: repeat(4,minmax(70px,1fr));
                gap: 7px;
                margin-top: 7px;
            }
            .dashboard-process-summary-metric {
                min-width: 0;
                padding: 7px 8px;
                border: 1px solid rgba(8,42,85,.08);
                border-radius: 8px;
                background: #fff;
            }
            .dashboard-process-summary-metric b {
                display: block;
                color: var(--navy2);
                font-size: 15px;
                line-height: 1;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .dashboard-process-summary-metric small {
                display: block;
                margin-top: 4px;
                color: #5d7086;
                font-size: 7px;
                font-weight: 900;
                line-height: 1.25;
                text-transform: uppercase;
            }
            @media (max-width: 900px) {
                .dashboard-process-footer { grid-template-columns: 1fr; }
            }
            @media (max-width: 620px) {
                .dashboard-process-summary-metrics { grid-template-columns: repeat(2,minmax(0,1fr)); }
            }
            @media (max-width: 900px) {
                .contract-preview-footer { grid-template-columns: 1fr; }
                .contract-preview-flow { grid-auto-columns: minmax(150px, 170px); }
            }
            .content {
                display: grid;
                grid-template-columns: 1.05fr 1.15fr;
                gap: 14px;
            }
            .card {
                background: #fff;
                border: 1px solid var(--line);
                border-radius: 12px;
                padding: 18px;
                box-shadow: var(--shadow);
            }
            .card h2 {
                margin: 0 0 8px;
                font-size: 20px;
            }
            .badge {
                display: inline-block;
                padding: 5px 9px;
                border-radius: 999px;
                font-size: 10px;
                font-weight: 900;
                background: #dbeafe;
                color: #1559ad;
            }
            .badge.purple {
                background: #eee5ff;
                color: #6430c5;
            }
            .statusbox {
                display: inline-block;
                padding: 5px 9px;
                border-radius: 999px;
                background: #dbeafe;
                color: #1559ad;
                font-size: 10px;
                font-weight: 900;
            }
            .details-row {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 16px;
                border-top: 1px solid var(--line);
                padding-top: 18px;
                margin-top: 18px;
            }
            .detail small {
                display: block;
                color: var(--muted);
                font-size: 10px;
                margin-bottom: 5px;
            }
            .detail b {
                font-size: 12px;
            }
            .detail .detail-note {
                display: block;
                margin-top: 4px;
                color: var(--muted);
                font-size: 10px;
                font-weight: 700;
                line-height: 1.35;
            }
            .detail.deadline-on-track b,
            .detail.deadline-completed-on-time b {
                color: #15733a;
            }
            .detail.deadline-attention b,
            .detail.deadline-scheduled b {
                color: #946000;
            }
            .detail.deadline-overdue b,
            .detail.deadline-completed-late b {
                color: #ad2e2b;
            }
            .detail.deadline-waiting b,
            .detail.deadline-blocked b {
                color: #176fdd;
            }
            .doc-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 12px;
            }
            .doc-table th {
                background: #f1f4f8;
                text-align: left;
                padding: 10px;
            }
            .doc-table td {
                padding: 10px;
                border-top: 1px solid var(--line);
                vertical-align: middle;
            }
            .pill {
                padding: 5px 8px;
                border-radius: 999px;
                font-size: 10px;
                font-weight: 900;
            }
            .pending {
                background: #eef1f5;
                color: #5f6d7f;
            }
            .waiting {
                background: #fff4df;
                color: #a76400;
            }
            .approved {
                background: #e9f8ee;
                color: #16813c;
            }
            .correction {
                background: #ffebea;
                color: #b92f2c;
            }
            .btn {
                border: 0;
                border-radius: 7px;
                padding: 8px 12px;
                font-weight: 900;
                cursor: pointer;
            }
            .btn.primary {
                background: linear-gradient(90deg, var(--navy), var(--navy2));
                color: white;
            }
            .btn.approve {
                background: var(--green);
                color: white;
            }
            .btn.correct {
                background: var(--red);
                color: white;
            }
            .upload-form {
                display: flex;
                gap: 8px;
                align-items: center;
            }
            .upload-form input {
                max-width: 180px;
                font-size: 11px;
            }
            .actions-stack {
                display: grid;
                gap: 10px;
                min-width: 250px;
            }
            .model-box {
                display: grid;
                gap: 6px;
            }
            .model-meta {
                font-size: 10px;
                color: var(--muted);
                line-height: 1.4;
            }
            .model-empty {
                font-size: 11px;
                color: var(--muted);
            }
            .model-upload {
                display: flex;
                gap: 7px;
                align-items: center;
                padding-bottom: 9px;
                border-bottom: 1px dashed var(--line);
            }
            .model-upload input {
                max-width: 165px;
                font-size: 10px;
            }
            .btn.model {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: #e8f1ff;
                color: #145eb8;
                text-decoration: none;
                border: 1px solid #bfd7fb;
            }
            .btn.model:hover {
                background: #dceaff;
            }
            .btn.model-upload-btn {
                background: var(--purple);
                color: #fff;
                white-space: nowrap;
            }
            .validation {
                display: grid;
                gap: 7px;
            }
            .validation textarea {
                width: 100%;
                border: 1px solid var(--line);
                border-radius: 8px;
                padding: 8px;
                font-family: inherit;
            }
            .validation-actions {
                display: flex;
                gap: 7px;
            }
            .phase0-grid {
                display: grid;
                grid-template-columns: 1.05fr 1fr;
                gap: 14px;
            }
            .checklist {
                display: grid;
                gap: 10px;
                margin-top: 14px;
            }
            .checkline {
                display: flex;
                gap: 8px;
                align-items: center;
                font-size: 13px;
            }
            .successbox {
                border: 1px solid #93d7a8;
                background: #f3fff7;
                border-radius: 9px;
                padding: 14px;
                color: #17793a;
            }
            .dangerbtn {
                width: 100%;
                margin-top: 10px;
                background: var(--red);
                color: #fff;
            }
            .register table {
                width: 100%;
                border-collapse: collapse;
                font-size: 12px;
            }
            .register th,
            .register td {
                border: 1px solid var(--line);
                padding: 9px;
                text-align: left;
            }
            .footnote {
                margin-top: 12px;
                border: 1px solid #d4c3ff;
                background: #faf7ff;
                color: #5e3ca6;
                padding: 11px;
                border-radius: 8px;
                font-size: 12px;
            }
            .flash {
                margin-bottom: 14px;
                padding: 12px 14px;
                border-radius: 9px;
                font-weight: 800;
            }
            .flash.ok {
                background: #eaf8ef;
                color: #14783a;
            }
            .flash.erro {
                background: #ffeded;
                color: #b52b2b;
            }
            .profile-switch {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .profile-switch select {
                border: 1px solid var(--line);
                background: #fff;
                border-radius: 9px;
                padding: 9px 11px;
                font-weight: 800;
                color: var(--ink);
            }
            .profile-switch button {
                border: 0;
                border-radius: 9px;
                padding: 9px 12px;
                background: linear-gradient(90deg, var(--navy), var(--navy2));
                color: #fff;
                font-weight: 900;
                cursor: pointer;
            }
            .gantt-panel {
                width: 100%;
                max-width: 100%;
                min-width: 0;
                margin-top: 18px;
                background: #fff;
                border: 1px solid var(--line);
                border-radius: 12px;
                box-shadow: var(--shadow);
                overflow: hidden;
            }
            .gantt-head {
                display: flex;
                justify-content: space-between;
                gap: 16px;
                align-items: flex-start;
                padding: 18px;
                border-bottom: 1px solid var(--line);
            }
            .gantt-head h2 {
                margin: 0 0 6px;
                font-size: 20px;
            }
            .gantt-head p {
                margin: 0;
                color: var(--muted);
                font-size: 12px;
            }
            .gantt-chip {
                display: inline-block;
                border: 1px solid var(--line);
                background: #f7f9fc;
                border-radius: 999px;
                padding: 7px 10px;
                font-size: 11px;
                font-weight: 900;
                color: #41536f;
            }
            .gantt-body {
                width: 100%;
                max-width: 100%;
                min-width: 0;
                padding: 16px 18px;
                overflow-x: hidden;
            }
            .gantt-scale,
            .gantt-row {
                display: grid;
                grid-template-columns: minmax(175px, 210px) minmax(0, 1fr);
                gap: 14px;
                align-items: center;
                width: 100%;
                min-width: 0;
            }
            .gantt-scale {
                margin-bottom: 8px;
            }
            .gantt-days {
                display: grid;
                grid-template-columns: repeat(10, minmax(0, 1fr));
                width: 100%;
                min-width: 0;
                font-size: 10px;
                color: var(--muted);
                font-weight: 900;
            }
            .gantt-days span {
                border-left: 1px solid #dfe7f0;
                padding-left: 5px;
            }
            .gantt-row {
                padding: 9px 0;
                border-top: 1px solid #eef2f6;
            }
            .gantt-label {
                font-size: 11px;
                line-height: 1.35;
            }
            .gantt-label b {
                display: block;
                font-size: 12px;
                color: var(--ink);
            }
            .gantt-line {
                position: relative;
                width: 100%;
                max-width: 100%;
                min-width: 0;
                height: 30px;
                border: 1px solid #e5ebf2;
                border-radius: 8px;
                overflow: hidden;
                box-sizing: border-box;
                background: repeating-linear-gradient(90deg, #f8fafc 0, #f8fafc 9.8%, #edf2f7 10%, #f8fafc 10.2%);
            }
            .gantt-bar {
                position: absolute;
                top: 5px;
                height: 18px;
                min-width: 16px;
                border-radius: 999px;
                background: #a9b4c3;
            }
            .gantt-bar.done {
                background: linear-gradient(90deg, #12843d, #18a64a);
            }
            .gantt-bar.run {
                background: linear-gradient(90deg, #cf7600, #ff9200);
            }
            .gantt-bar.correction {
                background: linear-gradient(90deg, #c52f2d, #e43b38);
            }
            .gantt-bar.pending {
                background: linear-gradient(90deg, #718097, #a9b4c3);
            }
            .gantt-tag {
                position: absolute;
                right: 7px;
                top: 50%;
                transform: translateY(-50%);
                font-size: 9px;
                color: #fff;
                font-weight: 900;
                white-space: nowrap;
            }
            .gantt-legend {
                display: flex;
                gap: 14px;
                flex-wrap: wrap;
                margin-top: 13px;
                font-size: 11px;
                color: var(--muted);
            }
            .dot {
                width: 9px;
                height: 9px;
                border-radius: 50%;
                display: inline-block;
                margin-right: 5px;
            }
            .dot.done {
                background: #18a64a;
            }
            .dot.run {
                background: #ff9200;
            }
            .dot.correction {
                background: #e43b38;
            }
            .dot.pending {
                background: #a9b4c3;
            }
            @media (max-width: 1300px) {
                .kpis {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                }
                .content,
                .phase0-grid {
                    grid-template-columns: 1fr;
                }
            }
            @media (max-width: 900px) {
                .app {
                    grid-template-columns: 1fr;
                }
                .app.menu-collapsed {
                    grid-template-columns: 1fr;
                }
                .app.menu-collapsed .side {
                    display: none;
                }
                .side {
                    position: relative;
                    height: auto;
                }
                .profilebox {
                    position: static;
                    margin-top: 18px;
                }
                .phase-tabs,
                .phase-tabs.municipio {
                    grid-template-columns: repeat(2, 1fr);
                }
                .hero {
                    display: block;
                }
                .notice {
                    min-width: 0;
                    margin-top: 14px;
                }
                .kpis {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: 16px;
                }
                .details-row {
                    grid-template-columns: 1fr 1fr;
                }
            }
            @media (max-width: 900px) {
                .gantt-head {
                    flex-direction: column;
                }
                .gantt-head-chips {
                    justify-content: flex-start;
                }
                .gantt-body {
                    padding: 14px 12px;
                }
                .gantt-scale,
                .gantt-row {
                    grid-template-columns: 1fr;
                    gap: 8px;
                }
                .gantt-days {
                    grid-template-columns: repeat(10, minmax(0, 1fr));
                    font-size: 8px;
                }
                .gantt-label {
                    padding-right: 0;
                }
                .profile-switch {
                    margin-top: 10px;
                }
            }
            @media (max-width: 560px) {
                .phase-summary .details-row {
                    grid-template-columns: 1fr;
                }
                .kpis,
                .details-row {
                    grid-template-columns: 1fr;
                }
                .wrap {
                    padding: 16px;
                }
                .top {
                    padding: 14px;
                }
                .upload-form {
                    display: grid;
                }
                .top-right {
                    align-items: flex-start;
                    flex-direction: column;
                }
                .profile-switch {
                    width: 100%;
                }
                .profile-switch select,
                .profile-switch button {
                    flex: 1;
                }
                .kpi {
                    min-height: 104px;
                    padding: 16px 16px 14px;
                }
                .kpi b {
                    font-size: 20px;
                }
            }
            .print-btn {
                border: 0;
                border-radius: 9px;
                padding: 9px 13px;
                background: #fff;
                color: var(--ink);
                font-weight: 900;
                cursor: pointer;
                border: 1px solid var(--line);
                display: inline-flex;
                align-items: center;
                gap: 7px;
            }
            .print-btn:hover {
                background: #f5f8fc;
            }
            .menu-toggle-btn {
                border: 1px solid var(--line);
                border-radius: 9px;
                padding: 9px 13px;
                background: #fff;
                color: var(--ink);
                font-weight: 900;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 7px;
                white-space: nowrap;
            }
            .menu-toggle-btn:hover {
                background: #f5f8fc;
            }
            .menu-toggle-btn:focus-visible {
                outline: 3px solid rgba(23, 111, 221, 0.22);
                outline-offset: 2px;
            }
            .app,
            .side,
            .main {
                transition:
                    grid-template-columns 0.22s ease,
                    width 0.22s ease,
                    opacity 0.18s ease,
                    padding 0.22s ease;
            }
            .app.menu-collapsed {
                grid-template-columns: 0 minmax(0, 1fr);
            }
            .app.menu-collapsed .side {
                width: 0;
                min-width: 0;
                padding-left: 0;
                padding-right: 0;
                border-right: 0;
                overflow: hidden;
                opacity: 0;
                pointer-events: none;
            }
            .app.menu-collapsed .main {
                width: 100%;
            }

            /* Ajustes exclusivamente estruturais para responsividade.
   Tipografia, tamanhos, cores, bordas e identidade visual originais são preservados. */
            html,
            body {
                width: 100%;
                max-width: 100%;
                overflow-x: hidden;
            }
            .app {
                width: 100%;
                max-width: 100%;
                grid-template-columns: minmax(220px, 280px) minmax(0, 1fr);
            }
            .main,
            .wrap,
            .content,
            .phase0-grid,
            .card,
            .kpis,
            .kpi,
            .phase-tabs,
            .gantt-panel {
                min-width: 0;
                max-width: 100%;
            }
            .top,
            .top-right,
            .hero,
            .profile-switch,
            .profile-switch form {
                min-width: 0;
            }
            .top-right {
                flex-wrap: wrap;
                justify-content: flex-end;
            }
            .hero > div:first-child {
                min-width: 0;
            }
            .notice {
                min-width: 0;
                flex: 0 1 430px;
            }
            .content {
                grid-template-columns: minmax(0, 1fr);
                gap: 14px;
            }
            .content > .card {
                min-width: 0;
                overflow: hidden;
            }
            .phase-summary {
                position: relative;
                overflow: hidden;
                box-shadow: none;
            }
            .phase-summary::before {
                content: "";
                position: absolute;
                left: -1px;
                right: -1px;
                top: -1px;
                height: 4px;
                border-radius: 12px 12px 0 0;
                background: var(--orange);
            }
            .phase-summary.phase-complete::before {
                background: var(--green);
            }
            .phase-summary.phase-pending::before {
                background: var(--orange);
            }
            .phase-summary.phase-correction::before {
                background: var(--red);
            }
            .phase-summary-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                flex-wrap: wrap;
            }
            .phase-summary-head h2 {
                margin: 0;
            }
            .phase-title-wrap {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }
            .phase0-checklist-head {
                display: flex;
                align-items: flex-end;
                justify-content: space-between;
                gap: 12px;
                flex-wrap: wrap;
                margin: 18px 0 10px;
            }
            .phase0-checklist-head b {
                font-size: 14px;
            }
            .phase0-checklist-progress {
                font-size: 11px;
                color: var(--muted);
                font-weight: 800;
            }
            .phase0-evidence-list {
                display: grid;
                gap: 10px;
            }
            .phase0-evidence-item {
                border: 1px solid var(--line);
                border-radius: 10px;
                background: #fbfcfe;
                padding: 12px;
                display: grid;
                gap: 10px;
                min-width: 0;
            }
            .phase0-evidence-item.complete {
                border-color: #a9ddba;
                background: #f8fffa;
            }
            .phase0-evidence-top {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 12px;
            }
            .phase0-item-check {
                display: flex;
                align-items: flex-start;
                gap: 9px;
                min-width: 0;
                font-size: 13px;
                font-weight: 800;
                line-height: 1.35;
                cursor: pointer;
            }
            .phase0-item-check input {
                margin-top: 2px;
                accent-color: var(--green);
                width: 16px;
                height: 16px;
                flex: 0 0 auto;
            }
            .phase0-item-check span {
                overflow-wrap: anywhere;
            }
            .phase0-item-status {
                display: inline-flex;
                align-items: center;
                flex: 0 0 auto;
                padding: 5px 8px;
                border-radius: 999px;
                font-size: 9px;
                font-weight: 900;
                white-space: nowrap;
                background: #fff4df;
                color: #a76400;
            }
            .phase0-evidence-item.complete .phase0-item-status {
                background: #e9f8ee;
                color: #16813c;
            }
            .phase0-file-line {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
                border-top: 1px dashed var(--line);
                padding-top: 9px;
                min-width: 0;
            }
            .phase0-file-info {
                min-width: 0;
                display: grid;
                gap: 2px;
            }
            .phase0-file-info b {
                font-size: 11px;
                overflow-wrap: anywhere;
                word-break: break-word;
            }
            .phase0-file-info small {
                font-size: 10px;
                color: var(--muted);
                line-height: 1.35;
            }
            .phase0-file-empty {
                font-size: 11px;
                color: var(--muted);
            }
            .phase0-download {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                text-decoration: none;
                border: 1px solid #bfd7fb;
                background: #e8f1ff;
                color: #145eb8;
                border-radius: 7px;
                padding: 7px 10px;
                font-size: 10px;
                font-weight: 900;
                white-space: nowrap;
            }
            .phase0-item-form {
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto;
                gap: 8px;
                align-items: center;
            }
            .phase0-item-form input[type="file"] {
                min-width: 0;
                width: 100%;
                font-size: 10px;
            }
            .phase0-save-btn {
                background: var(--purple);
                color: #fff;
                white-space: nowrap;
            }
            .phase0-conclude-btn {
                width: 100%;
                margin-top: 14px;
            }
            .phase0-help {
                margin-top: 10px;
                font-size: 10px;
                color: var(--muted);
                line-height: 1.45;
            }
            @media (max-width: 620px) {
                .phase0-evidence-top,
                .phase0-file-line {
                    align-items: stretch;
                    flex-direction: column;
                }
                .phase0-item-status {
                    align-self: flex-start;
                }
                .phase0-item-form {
                    grid-template-columns: 1fr;
                }
                .phase0-download {
                    align-self: flex-start;
                }
            }

            .phase-summary > p {
                max-width: 100%;
                line-height: 1.5;
                margin: 18px 0 0;
            }
            .phase-summary .details-row {
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 22px;
            }
            .phase-summary .detail {
                min-width: 0;
            }
            .phase-summary .detail b {
                display: block;
                line-height: 1.45;
                overflow-wrap: break-word;
                word-break: normal;
            }
            .statusbox.phase-complete {
                background: #e9f8ee;
                color: #16813c;
            }
            .statusbox.phase-pending {
                background: #fff4df;
                color: #a76400;
            }
            .statusbox.phase-correction {
                background: #ffebea;
                color: #b92f2c;
            }
            .details-row > .detail {
                min-width: 0;
                overflow-wrap: break-word;
                word-break: normal;
            }
            .phase-tab {
                min-width: 0;
                overflow-wrap: anywhere;
            }
            .doc-table {
                width: 100%;
                max-width: 100%;
                table-layout: fixed;
            }
            .doc-table th:nth-child(1),
            .doc-table td:nth-child(1) {
                width: 27%;
            }
            .doc-table th:nth-child(2),
            .doc-table td:nth-child(2) {
                width: 24%;
            }
            .doc-table th:nth-child(3),
            .doc-table td:nth-child(3) {
                width: 12%;
            }
            .doc-table th:nth-child(4),
            .doc-table td:nth-child(4) {
                width: 37%;
            }
            .doc-table th,
            .doc-table td {
                min-width: 0;
                overflow-wrap: anywhere;
                word-break: break-word;
            }
            .doc-table small,
            .model-meta,
            .model-empty {
                overflow-wrap: anywhere;
                word-break: break-word;
            }
            .grouped-doc-table th:nth-child(1),
            .grouped-doc-table td:nth-child(1) {
                width: 25%;
            }
            .grouped-doc-table th:nth-child(2),
            .grouped-doc-table td:nth-child(2) {
                width: 22%;
            }
            .grouped-doc-table th:nth-child(3),
            .grouped-doc-table td:nth-child(3) {
                width: 53%;
            }
            .secretaria-doc-groups {
                display: grid;
                gap: 16px;
            }
            .secretaria-doc-group {
                border: 2px solid #8f98ab;
                border-radius: 14px;
                background: #f8fafc;
                overflow: hidden;
                box-shadow: 0 1px 0 rgba(8,42,85,.03);
            }
            .secretaria-doc-group-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                padding: 14px 16px;
                border-bottom: 1px solid #d9e1eb;
                background: #eef2f7;
            }
            .secretaria-doc-group-head h3 {
                margin: 0;
                font-size: 16px;
                color: var(--navy2);
            }
            .secretaria-doc-group-head p {
                margin: 4px 0 0;
                color: var(--muted);
                font-size: 12px;
            }
            .secretaria-doc-group-badge {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 7px 11px;
                border-radius: 999px;
                border: 1px solid #c6d0de;
                background: #fff;
                color: #50647d;
                font-size: 10px;
                font-weight: 900;
                text-transform: uppercase;
                white-space: nowrap;
            }
            .secretaria-grouped-doc-table {
                margin: 0;
                border-radius: 0;
                overflow: visible;
            }
            .secretaria-grouped-doc-table th:nth-child(1),
            .secretaria-grouped-doc-table td:nth-child(1) {
                width: 44%;
            }
            .secretaria-grouped-doc-table th:nth-child(2),
            .secretaria-grouped-doc-table td:nth-child(2) {
                width: 23%;
            }
            .secretaria-grouped-doc-table th:nth-child(3),
            .secretaria-grouped-doc-table td:nth-child(3) {
                width: 33%;
            }
            .secretaria-grouped-doc-table tbody tr:last-child td {
                border-bottom: 0;
            }
            .secretaria-grouped-doc-table tbody tr.row-approved td:nth-child(2),
            .secretaria-grouped-doc-table tbody tr.row-approved td:nth-child(3) {
                background: #f5fcf7;
            }
            .secretaria-grouped-doc-table tbody tr.row-approved td:nth-child(2) {
                border-left: 1px solid #d8eddc;
                border-right: 1px solid #d8eddc;
            }
            .secretaria-grouped-doc-table tbody tr.row-approved td:nth-child(3) {
                border-right: 1px solid #d8eddc;
            }
            .secretaria-grouped-doc-table tbody tr.row-approved td:nth-child(2) .model-empty,
            .secretaria-grouped-doc-table tbody tr.row-approved td:nth-child(2) .model-meta,
            .secretaria-grouped-doc-table tbody tr.row-approved td:nth-child(3) .grouped-document-description,
            .secretaria-grouped-doc-table tbody tr.row-approved td:nth-child(3) .doc-owner {
                color: #4b6e57;
            }
            .grouped-document-title {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 8px;
                flex-wrap: wrap;
            }
            .grouped-document-title > b {
                font-size: 13px;
                line-height: 1.35;
            }
            .grouped-document-description {
                display: block;
                margin-top: 6px;
                color: var(--muted);
                line-height: 1.45;
            }
            .grouped-document-counts {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
                margin-top: 9px;
            }
            .grouped-document-counts span {
                display: inline-flex;
                padding: 4px 6px;
                border-radius: 7px;
                font-size: 8px;
                font-weight: 900;
                white-space: nowrap;
            }
            .grouped-model-upload {
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto;
                align-items: center;
                gap: 7px;
                margin-top: 12px;
                padding-top: 10px;
                border-top: 1px dashed var(--line);
                border-bottom: 0;
            }
            .grouped-model-upload small {
                grid-column: 1 / -1;
                color: var(--muted);
                font-size: 9px;
                line-height: 1.4;
            }
            .grouped-delivery-list {
                display: grid;
                gap: 9px;
            }
            .grouped-delivery-card {
                padding: 11px;
                border: 1px solid #dfe7f0;
                border-left: 5px solid #aab5c3;
                border-radius: 10px;
                background: #fbfcfe;
            }
            .grouped-delivery-card.approved {
                border-color: #b7e4c4;
                border-left-color: #20a84d;
                background: #f5fcf7;
            }
            .grouped-delivery-card.waiting {
                border-color: #f4d184;
                border-left-color: #f2b632;
                background: #fffaf0;
            }
            .grouped-delivery-card.correction {
                border-color: #f0aaa7;
                border-left-color: #df4541;
                background: #fff7f6;
            }
            .grouped-delivery-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 10px;
            }
            .grouped-delivery-head strong {
                display: block;
                font-size: 11px;
                line-height: 1.35;
            }
            .grouped-delivery-head small {
                display: block;
                margin-top: 3px;
                color: var(--muted);
                font-size: 9px;
            }
            .grouped-delivery-guidance {
                margin-top: 8px;
                padding: 7px 8px;
                border-radius: 8px;
                background: #eef5ff;
                border: 1px solid #d3e3f8;
                color: #385878;
                font-size: 9px;
                line-height: 1.45;
            }
            .grouped-delivery-file {
                margin-top: 8px;
                padding: 7px 8px;
                border-radius: 8px;
                background: rgba(255,255,255,.82);
                border: 1px solid rgba(8,42,85,.07);
                color: #415671;
                font-size: 9px;
                line-height: 1.45;
                overflow-wrap: anywhere;
            }
            .grouped-delivery-file.empty {
                color: #7a8798;
                font-style: italic;
            }
            .grouped-delivery-correction {
                margin-top: 7px;
                padding: 7px 8px;
                border-radius: 8px;
                background: #fff1d6;
                color: #825700;
                font-size: 9px;
                line-height: 1.45;
            }
            .grouped-delivery-actions {
                display: flex;
                gap: 7px;
                align-items: center;
                flex-wrap: wrap;
                margin-top: 8px;
            }
            .grouped-delivery-actions .upload-form {
                flex: 1 1 260px;
            }
            .validation-complete {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
                margin-top: 10px;
                padding: 9px 11px;
                border: 1px solid #b7e4c4;
                border-radius: 9px;
                background: #ecf9f0;
                color: #15733a;
                font-size: 9px;
                font-weight: 800;
                line-height: 1.4;
            }
            .validation-complete strong {
                font-size: 10px;
                text-transform: uppercase;
                white-space: nowrap;
            }
            .grouped-validation {
                margin-top: 8px;
                padding-top: 8px;
                border-top: 1px dashed rgba(8,42,85,.12);
            }
            .model-box,
            .actions-stack,
            .validation {
                min-width: 0;
                width: 100%;
            }
            .actions-stack {
                min-width: 0;
            }
            .model-upload,
            .upload-form,
            .validation-actions {
                min-width: 0;
                flex-wrap: wrap;
            }
            .model-upload input,
            .upload-form input {
                min-width: 0;
                width: 0;
                max-width: 100%;
                flex: 1 1 110px;
            }
            .model-upload .btn,
            .upload-form .btn {
                flex: 0 0 auto;
            }
            .validation textarea {
                min-width: 0;
                max-width: 100%;
                resize: vertical;
            }
            .register {
                min-width: 0;
                overflow: hidden;
            }
            .register table {
                table-layout: fixed;
            }
            .register th,
            .register td {
                overflow-wrap: anywhere;
                word-break: break-word;
            }
            .gantt-body,
            .gantt-scale,
            .gantt-row,
            .gantt-line {
                min-width: 0;
                max-width: 100%;
            }
            .phase0-history-wrap {
                max-height: 620px;
                overflow: auto;
                border: 1px solid var(--line);
                border-radius: 9px;
                background: #fff;
            }
            .phase0-history-table {
                width: 100%;
                border-collapse: separate !important;
                border-spacing: 0;
                table-layout: fixed;
                font-size: 11px !important;
            }
            .phase0-history-table thead th {
                position: sticky;
                top: 0;
                z-index: 2;
                background: #f1f4f8;
                color: var(--ink);
                padding: 10px 9px;
                border: 0;
                border-bottom: 1px solid var(--line);
                text-align: left;
            }
            .phase0-history-table tbody td {
                padding: 10px 9px;
                border: 0;
                border-bottom: 1px solid var(--line);
                vertical-align: top;
                line-height: 1.35;
                overflow-wrap: anywhere;
            }
            .phase0-history-table tbody tr:last-child td {
                border-bottom: 0;
            }
            .phase0-history-table th:nth-child(1),
            .phase0-history-table td:nth-child(1) {
                width: 20%;
            }
            .phase0-history-table th:nth-child(2),
            .phase0-history-table td:nth-child(2) {
                width: 34%;
            }
            .phase0-history-table th:nth-child(3),
            .phase0-history-table td:nth-child(3) {
                width: 17%;
            }
            .phase0-history-table th:nth-child(4),
            .phase0-history-table td:nth-child(4) {
                width: 17%;
            }
            .phase0-history-table th:nth-child(5),
            .phase0-history-table td:nth-child(5) {
                width: 12%;
                text-align: center;
            }
            .phase0-history-event {
                display: inline-flex;
                padding: 4px 7px;
                border-radius: 999px;
                background: #e8f1ff;
                color: #145eb8;
                font-size: 9px;
                font-weight: 900;
                white-space: nowrap;
            }
            .phase0-history-event.replaced {
                background: #fff4df;
                color: #a76400;
            }
            .phase0-history-file {
                display: grid;
                gap: 3px;
            }
            .phase0-history-file b {
                font-size: 11px;
                color: var(--ink);
            }
            .phase0-history-file small {
                font-size: 9px;
                color: var(--muted);
                line-height: 1.35;
            }
            .phase0-history-download {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                text-decoration: none;
                border: 1px solid #bfd7fb;
                background: #e8f1ff;
                color: #145eb8;
                border-radius: 7px;
                padding: 6px 8px;
                font-size: 10px;
                font-weight: 900;
                white-space: nowrap;
            }
            .phase0-history-download:hover {
                background: #dceaff;
            }
            .phase0-history-empty {
                padding: 18px;
                border: 1px dashed var(--line);
                border-radius: 9px;
                color: var(--muted);
                font-size: 12px;
                text-align: center;
                background: #f8fafc;
            }
            .phase0-history-conclusion {
                margin-top: 12px;
                padding: 11px 12px;
                border-radius: 9px;
                border: 1px solid #93d7a8;
                background: #f3fff7;
                color: #17793a;
                font-size: 11px;
                line-height: 1.45;
            }
            .phase0-history-conclusion.pending {
                border-color: #f5c46b;
                background: #fffaf0;
                color: #956000;
            }
            .delivery-overview-card {
                margin-top: 18px;
                overflow: hidden;
                border-top: 4px solid var(--navy2);
            }
            .delivery-overview-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 18px;
                flex-wrap: wrap;
                margin-bottom: 16px;
            }
            .delivery-overview-head h2 {
                margin: 0 0 5px;
                font-size: 20px;
            }
            .delivery-overview-head p {
                margin: 0;
                color: var(--muted);
                font-size: 12px;
                line-height: 1.5;
            }
            .delivery-overview-summary {
                display: grid;
                grid-template-columns: repeat(4, minmax(105px, 1fr));
                gap: 9px;
                min-width: min(100%, 500px);
            }
            .delivery-summary-box {
                display: grid;
                grid-template-columns: 32px 1fr;
                grid-template-rows: auto auto;
                column-gap: 9px;
                align-items: center;
                padding: 10px 11px;
                border: 1px solid;
                border-radius: 11px;
            }
            .delivery-summary-box .delivery-summary-icon {
                grid-row: 1 / 3;
                display: grid;
                place-items: center;
                width: 32px;
                height: 32px;
                border-radius: 9px;
                font-size: 18px;
                font-weight: 900;
            }
            .delivery-summary-box strong {
                font-size: 19px;
                line-height: 1;
            }
            .delivery-summary-box small {
                font-size: 9px;
                font-weight: 900;
                text-transform: uppercase;
                letter-spacing: .035em;
            }
            .delivery-summary-box.delivered {
                border-color: #a7dfb8;
                background: #f1fcf4;
                color: #15733a;
            }
            .delivery-summary-box.delivered .delivery-summary-icon {
                background: #d8f4e1;
            }
            .delivery-summary-box.awaiting-validation {
                border-color: #a9c9ef;
                background: #f1f7ff;
                color: #176fdd;
            }
            .delivery-summary-box.awaiting-validation .delivery-summary-icon {
                background: #dcecff;
            }
            .delivery-summary-box.needs-correction {
                border-color: #f4d184;
                background: #fff9e9;
                color: #946000;
            }
            .delivery-summary-box.needs-correction .delivery-summary-icon {
                background: #ffedb8;
            }
            .delivery-summary-box.not-delivered {
                border-color: #f0aaa7;
                background: #fff3f2;
                color: #ad2e2b;
            }
            .delivery-summary-box.not-delivered .delivery-summary-icon {
                background: #ffdedd;
            }
            .delivery-progress-bar {
                display: flex;
                width: 100%;
                height: 20px;
                overflow: hidden;
                border-radius: 999px;
                background: #edf1f5;
                box-shadow: inset 0 0 0 1px rgba(8, 42, 85, .06);
                margin: 4px 0 10px;
            }
            .delivery-progress-bar span {
                position: relative;
                display: flex;
                align-items: center;
                justify-content: center;
                min-width: 0;
                overflow: hidden;
                transition: width .2s ease;
            }
            .delivery-progress-bar span b {
                display: block;
                padding: 0 3px;
                color: #fff;
                font-size: 8px;
                font-weight: 900;
                line-height: 1;
                white-space: nowrap;
                text-shadow: 0 1px 2px rgba(0,0,0,.24);
            }
            .delivery-progress-bar .needs-correction b {
                color: #5f4100;
                text-shadow: none;
            }
            .delivery-progress-bar .delivered {
                background: #20a84d;
            }
            .delivery-progress-bar .awaiting-validation {
                background: #176fdd;
            }
            .delivery-progress-bar .needs-correction {
                background: #f2b632;
            }
            .delivery-progress-bar .not-delivered {
                background: #df4541;
            }
            .delivery-legend {
                display: flex;
                align-items: center;
                gap: 15px;
                flex-wrap: wrap;
                margin-bottom: 15px;
                color: #566981;
                font-size: 10px;
                font-weight: 800;
            }
            .delivery-legend span {
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }
            .delivery-legend i {
                width: 9px;
                height: 9px;
                border-radius: 50%;
            }
            .delivery-legend i.delivered {
                background: #20a84d;
            }
            .delivery-legend i.awaiting-validation {
                background: #176fdd;
            }
            .delivery-legend i.needs-correction {
                background: #f2b632;
            }
            .delivery-legend i.not-delivered {
                background: #df4541;
            }
            .workflow-inline-progress {
                padding: 14px 16px;
                margin: 14px 0 10px;
            }
            .workflow-inline-progress-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                flex-wrap: wrap;
                margin-bottom: 8px;
            }
            .workflow-inline-progress-head strong {
                font-size: 13px;
                color: var(--navy2);
            }
            .workflow-inline-progress-head small {
                color: var(--muted);
                font-size: 11px;
                line-height: 1.45;
            }
            .workflow-inline-progress-pill {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 6px 10px;
                border-radius: 999px;
                background: #eef6ff;
                border: 1px solid #cfe0f6;
                color: #285b93;
                font-size: 10px;
                font-weight: 900;
                text-transform: uppercase;
                white-space: nowrap;
            }
            .delivery-secretaria-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                flex-wrap: wrap;
                margin: 18px 0 10px;
                padding-top: 12px;
                border-top: 1px solid #e5edf6;
            }
            .delivery-secretaria-head h3 {
                margin: 0 0 4px;
                font-size: 15px;
            }
            .delivery-secretaria-head p {
                margin: 0;
                color: var(--muted);
                font-size: 11px;
                line-height: 1.45;
            }
            .delivery-secretaria-badge {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 8px 11px;
                border-radius: 999px;
                background: #f0f6ff;
                border: 1px solid #cfe0f6;
                color: var(--navy2);
                font-size: 11px;
                font-weight: 900;
                white-space: nowrap;
            }
            .delivery-secretaria-quickgrid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 10px;
                margin: 0 0 12px;
            }
            .delivery-secretaria-quickcard {
                position: relative;
                display: flex;
                align-items: center;
                gap: 11px;
                padding: 12px 14px;
                border: 1px solid;
                border-left-width: 6px;
                border-radius: 12px;
                min-height: 82px;
                box-shadow: 0 6px 18px rgba(8, 34, 75, 0.04);
            }
            .delivery-secretaria-quickcard.not-delivered {
                background: #fff5f4;
                border-color: #f1b3b0;
                border-left-color: #df4541;
            }
            .delivery-secretaria-quickcard.needs-correction {
                background: #fffaf0;
                border-color: #f2d694;
                border-left-color: #f2b632;
            }
            .delivery-secretaria-quickicon {
                flex: 0 0 auto;
                width: 42px;
                height: 42px;
                border-radius: 12px;
                display: grid;
                place-items: center;
                font-size: 22px;
                font-weight: 900;
            }
            .delivery-secretaria-quickcard.not-delivered .delivery-secretaria-quickicon {
                background: #ffdedd;
                color: #ad2e2b;
            }
            .delivery-secretaria-quickcard.needs-correction .delivery-secretaria-quickicon {
                background: #ffedb8;
                color: #946000;
            }
            .delivery-secretaria-quickcontent {
                min-width: 0;
                display: grid;
                gap: 4px;
            }
            .delivery-secretaria-quicktop {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 8px;
            }
            .delivery-secretaria-quicktop strong {
                font-size: 13px;
                line-height: 1.3;
            }
            .delivery-secretaria-quickpill {
                flex: 0 0 auto;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 4px 7px;
                border-radius: 999px;
                font-size: 8px;
                font-weight: 900;
                text-transform: uppercase;
                letter-spacing: .04em;
                white-space: nowrap;
            }
            .delivery-secretaria-quickcard.not-delivered .delivery-secretaria-quickpill {
                background: #ffdedd;
                color: #ad2e2b;
            }
            .delivery-secretaria-quickcard.needs-correction .delivery-secretaria-quickpill {
                background: #ffedb8;
                color: #946000;
            }
            .delivery-secretaria-quickmeta {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
                color: #50647d;
                font-size: 10px;
                font-weight: 800;
            }
            .delivery-secretaria-quickmeta span {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 3px 6px;
                border-radius: 7px;
                background: rgba(255,255,255,.82);
                border: 1px solid rgba(8,42,85,.08);
            }
            .delivery-secretaria-quickhint {
                color: #5d7086;
                font-size: 10px;
                line-height: 1.35;
                overflow: hidden;
                text-overflow: ellipsis;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
            }
            .delivery-secretaria-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 11px;
                margin-bottom: 14px;
            }
            .delivery-secretaria-card {
                padding: 14px;
                border: 1px solid;
                border-left-width: 5px;
                border-radius: 12px;
            }
            .delivery-secretaria-card.not-delivered {
                border-color: #f0aaa7;
                border-left-color: #df4541;
                background: #fff7f6;
            }
            .delivery-secretaria-card.needs-correction {
                border-color: #f4d184;
                border-left-color: #f2b632;
                background: #fffaf0;
            }
            .delivery-secretaria-card-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 10px;
                margin-bottom: 10px;
            }
            .delivery-secretaria-card-head strong {
                display: block;
                font-size: 14px;
                line-height: 1.35;
            }
            .delivery-secretaria-card-head small {
                display: block;
                margin-top: 4px;
                color: #56708b;
                font-size: 10px;
                font-weight: 700;
            }
            .delivery-secretaria-status {
                flex: 0 0 auto;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 5px 8px;
                border-radius: 999px;
                font-size: 8px;
                font-weight: 900;
                letter-spacing: .04em;
                text-transform: uppercase;
                white-space: nowrap;
            }
            .delivery-secretaria-card.not-delivered .delivery-secretaria-status {
                background: #ffdedd;
                color: #ad2e2b;
            }
            .delivery-secretaria-card.needs-correction .delivery-secretaria-status {
                background: #ffedb8;
                color: #946000;
            }
            .delivery-secretaria-counts {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                margin-bottom: 10px;
            }
            .delivery-secretaria-counts span {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 5px 8px;
                border-radius: 8px;
                background: rgba(255,255,255,.82);
                border: 1px solid rgba(8,42,85,.08);
                font-size: 10px;
                font-weight: 800;
                color: #50647d;
            }
            .delivery-secretaria-counts .not-delivered {
                color: #ad2e2b;
            }
            .delivery-secretaria-counts .needs-correction {
                color: #946000;
            }
            .delivery-secretaria-counts .delivered {
                color: #15733a;
            }
            .delivery-secretaria-counts .awaiting-validation {
                color: #176fdd;
            }
            .delivery-secretaria-list {
                display: grid;
                gap: 8px;
            }
            .delivery-secretaria-topic {
                padding: 8px 9px;
                border-radius: 9px;
                background: rgba(255,255,255,.72);
                border: 1px dashed rgba(8,42,85,.12);
            }
            .delivery-secretaria-topic strong {
                display: block;
                margin-bottom: 4px;
                font-size: 10px;
                text-transform: uppercase;
                letter-spacing: .03em;
            }
            .delivery-secretaria-topic ul {
                margin: 0;
                padding-left: 16px;
                color: #435b75;
                font-size: 10px;
                line-height: 1.45;
            }
            .delivery-secretaria-topic li + li {
                margin-top: 3px;
            }
            .delivery-secretaria-empty {
                margin: 18px 0 14px;
                padding: 14px 16px;
                border-radius: 11px;
                border: 1px solid #a7dfb8;
                background: #f3fff7;
                color: #15733a;
                font-size: 12px;
                font-weight: 700;
            }
            .delivery-document-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 11px;
            }
            .delivery-document-item {
                display: grid;
                grid-template-columns: 44px minmax(0, 1fr);
                gap: 12px;
                align-items: start;
                min-height: 126px;
                padding: 14px;
                border: 1px solid;
                border-left-width: 5px;
                border-radius: 12px;
            }
            .delivery-document-item.delivered {
                border-color: #a7dfb8;
                border-left-color: #20a84d;
                background: #f5fcf7;
            }
            .delivery-document-item.awaiting-validation {
                border-color: #a9c9ef;
                border-left-color: #176fdd;
                background: #f3f8ff;
            }
            .delivery-document-item.needs-correction {
                border-color: #f4d184;
                border-left-color: #f2b632;
                background: #fffaf0;
            }
            .delivery-document-item.not-delivered {
                border-color: #f0aaa7;
                border-left-color: #df4541;
                background: #fff6f5;
            }
            .delivery-document-icon {
                display: grid;
                place-items: center;
                width: 44px;
                height: 44px;
                border-radius: 12px;
                font-size: 24px;
                font-weight: 900;
            }
            .delivery-document-item.delivered .delivery-document-icon {
                background: #d8f4e1;
                color: #15733a;
            }
            .delivery-document-item.awaiting-validation .delivery-document-icon {
                background: #dcecff;
                color: #176fdd;
            }
            .delivery-document-item.needs-correction .delivery-document-icon {
                background: #ffedb8;
                color: #946000;
            }
            .delivery-document-item.not-delivered .delivery-document-icon {
                background: #ffdedd;
                color: #ad2e2b;
            }
            .delivery-document-content {
                min-width: 0;
            }
            .delivery-document-title {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 9px;
            }
            .delivery-document-title strong {
                font-size: 13px;
                line-height: 1.35;
            }
            .delivery-status-label {
                flex: 0 0 auto;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 5px 7px;
                border-radius: 999px;
                font-size: 8px;
                font-weight: 900;
                letter-spacing: .025em;
                white-space: nowrap;
            }
            .delivery-document-item.delivered .delivery-status-label {
                background: #d8f4e1;
                color: #15733a;
            }
            .delivery-document-item.awaiting-validation .delivery-status-label {
                background: #dcecff;
                color: #176fdd;
            }
            .delivery-document-item.needs-correction .delivery-status-label {
                background: #ffedb8;
                color: #946000;
            }
            .delivery-document-item.not-delivered .delivery-status-label {
                background: #ffdedd;
                color: #ad2e2b;
            }
            .delivery-document-meta {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
                margin-top: 8px;
            }
            .delivery-document-meta span {
                padding: 4px 6px;
                border-radius: 6px;
                background: rgba(255, 255, 255, .74);
                border: 1px solid rgba(8, 42, 85, .08);
                color: #50647d;
                font-size: 9px;
                font-weight: 800;
            }
            .delivery-document-detail {
                margin: 9px 0 0;
                color: #415671;
                font-size: 10px;
                line-height: 1.45;
                overflow-wrap: anywhere;
            }
            .delivery-document-detail strong {
                color: inherit;
            }
            .delivery-document-item.awaiting-validation .delivery-document-detail {
                color: #285b93;
            }
            .delivery-document-item.needs-correction .delivery-document-detail {
                color: #825700;
            }
            .delivery-document-item.not-delivered .delivery-document-detail {
                color: #9d3532;
                font-weight: 700;
            }
            .delivery-overview-empty {
                padding: 25px;
                border: 1px dashed #cbd7e4;
                border-radius: 11px;
                background: #fbfdff;
                color: var(--muted);
                text-align: center;
                font-size: 12px;
            }
            @media (max-width: 920px) {
                .delivery-document-grid,
                .delivery-secretaria-grid,
                .delivery-secretaria-quickgrid {
                    grid-template-columns: 1fr;
                }
                .delivery-overview-summary {
                    width: 100%;
                }
            }
            @media (max-width: 620px) {
                .delivery-overview-summary {
                    grid-template-columns: 1fr;
                }
                .delivery-document-title {
                    display: grid;
                }
                .delivery-status-label {
                    width: max-content;
                }
            }
            @media print {
                .delivery-overview-card {
                    break-inside: avoid;
                }
                .delivery-document-item {
                    break-inside: avoid;
                }
            }
            .workflow-log-card {
                margin-top: 18px;
                overflow: hidden;
            }
            .workflow-log-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 14px;
                flex-wrap: wrap;
                margin-bottom: 14px;
            }
            .workflow-log-head h2 {
                margin: 0 0 5px;
                font-size: 20px;
            }
            .workflow-log-head p {
                margin: 0;
                color: var(--muted);
                font-size: 12px;
            }
            .workflow-log-count {
                display: inline-flex;
                align-items: center;
                padding: 7px 10px;
                border: 1px solid var(--line);
                background: #f7f9fc;
                border-radius: 999px;
                font-size: 10px;
                font-weight: 900;
                color: #41536f;
                white-space: nowrap;
            }
            .workflow-log-wrap {
                overflow-x: auto;
                border: 1px solid var(--line);
                border-radius: 10px;
            }
            .workflow-log-table {
                width: 100%;
                min-width: 930px;
                border-collapse: collapse;
                table-layout: fixed;
                font-size: 11px;
            }
            .workflow-log-table th {
                background: #f1f4f8;
                text-align: left;
                padding: 10px;
                border-bottom: 1px solid var(--line);
                font-size: 10px;
            }
            .workflow-log-table td {
                padding: 11px 10px;
                border-bottom: 1px solid var(--line);
                vertical-align: top;
                line-height: 1.4;
                overflow-wrap: anywhere;
            }
            .workflow-log-table tr:last-child td {
                border-bottom: 0;
            }
            .workflow-log-table th:nth-child(1),
            .workflow-log-table td:nth-child(1) {
                width: 15%;
            }
            .workflow-log-table th:nth-child(2),
            .workflow-log-table td:nth-child(2) {
                width: 25%;
            }
            .workflow-log-table th:nth-child(3),
            .workflow-log-table td:nth-child(3) {
                width: 17%;
            }
            .workflow-log-table th:nth-child(4),
            .workflow-log-table td:nth-child(4) {
                width: 13%;
            }
            .workflow-log-table th:nth-child(5),
            .workflow-log-table td:nth-child(5) {
                width: 23%;
            }
            .workflow-log-table th:nth-child(6),
            .workflow-log-table td:nth-child(6) {
                width: 7%;
                text-align: center;
            }
            .workflow-event {
                display: inline-flex;
                padding: 5px 8px;
                border-radius: 999px;
                font-size: 9px;
                font-weight: 900;
                white-space: nowrap;
                background: #e8f1ff;
                color: #145eb8;
            }
            .workflow-event.correction {
                background: #ffebea;
                color: #b92f2c;
            }
            .workflow-event.approved {
                background: #e9f8ee;
                color: #16813c;
            }
            .workflow-event.replaced {
                background: #fff4df;
                color: #a76400;
            }
            .workflow-event.model {
                background: #eee5ff;
                color: #6430c5;
            }
            .workflow-log-file {
                display: grid;
                gap: 3px;
            }
            .workflow-log-file b {
                font-size: 11px;
            }
            .workflow-log-file span {
                color: #314766;
            }
            .workflow-log-file small {
                font-size: 9px;
                color: var(--muted);
            }
            .workflow-log-user {
                display: grid;
                gap: 3px;
            }
            .workflow-log-user small {
                font-size: 9px;
                color: var(--muted);
            }
            .workflow-log-profile {
                display: inline-flex;
                width: max-content;
                padding: 3px 6px;
                border-radius: 999px;
                background: #eef1f5;
                color: #5f6d7f;
                font-size: 8px;
                font-weight: 900;
            }
            .workflow-log-profile.stratelli {
                background: #eee5ff;
                color: #6430c5;
            }
            .workflow-log-profile.municipio {
                background: #dbeafe;
                color: #1559ad;
            }
            .workflow-log-reason {
                color: #314766;
            }
            .workflow-log-reason strong {
                display: block;
                color: var(--ink);
                font-size: 10px;
                margin-bottom: 3px;
            }
            .workflow-log-download {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                text-decoration: none;
                border: 1px solid #bfd7fb;
                background: #e8f1ff;
                color: #145eb8;
                border-radius: 7px;
                padding: 6px 8px;
                font-size: 10px;
                font-weight: 900;
                white-space: nowrap;
            }
            .workflow-log-empty {
                padding: 24px;
                border: 1px dashed var(--line);
                border-radius: 10px;
                background: #f8fafc;
                color: var(--muted);
                font-size: 12px;
                text-align: center;
            }
            .workflow-log-pagination {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 12px;
                margin-top: 13px;
                flex-wrap: wrap;
                font-size: 11px;
                color: var(--muted);
            }
            .workflow-log-pages {
                display: flex;
                align-items: center;
                gap: 7px;
            }
            .workflow-log-pages a,
            .workflow-log-pages span {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 30px;
                padding: 6px 10px;
                border: 1px solid var(--line);
                border-radius: 7px;
                background: #fff;
                color: var(--ink);
                font-weight: 800;
                text-decoration: none;
            }
            .workflow-log-pages a:hover {
                background: #f5f8fc;
            }
            .workflow-log-pages .disabled {
                opacity: 0.45;
                pointer-events: none;
            }
            .workflow-log-pages .current {
                background: var(--navy);
                border-color: var(--navy);
                color: #fff;
            }

            @media (max-width: 1180px) {
                .content {
                    grid-template-columns: 1fr;
                }
                .content > .card {
                    overflow: visible;
                }
            }

            @media (max-width: 900px) {
                .app {
                    grid-template-columns: 1fr;
                }
                .phase-summary .details-row {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
                .top {
                    flex-wrap: wrap;
                    gap: 12px;
                }
                .top-right {
                    width: 100%;
                    justify-content: flex-start;
                }
                .profile-switch,
                .profile-switch form {
                    width: 100%;
                    flex-wrap: wrap;
                }
                .profile-switch form label {
                    flex: 0 0 auto;
                }
                .profile-switch select,
                .profile-switch button {
                    min-width: 0;
                }
                .hero {
                    min-width: 0;
                }
                .notice {
                    width: 100%;
                    max-width: none;
                }
                .gantt-head {
                    flex-wrap: wrap;
                }
            }

            @media (max-width: 720px) {
                .doc-table,
                .doc-table tbody,
                .doc-table tr,
                .doc-table td {
                    display: block;
                    width: 100%;
                }
                .doc-table thead {
                    display: none;
                }
                .doc-table tr {
                    margin-bottom: 12px;
                    border: 1px solid var(--line);
                    border-radius: 10px;
                    overflow: hidden;
                    background: #fff;
                }
                .doc-table td,
                .doc-table td:nth-child(1),
                .doc-table td:nth-child(2),
                .doc-table td:nth-child(3),
                .doc-table td:nth-child(4) {
                    width: 100%;
                    position: relative;
                    padding: 10px 10px 10px 104px;
                    border-top: 1px solid var(--line);
                    min-height: 42px;
                }
                .grouped-doc-table td,
                .grouped-doc-table td:nth-child(1),
                .grouped-doc-table td:nth-child(2),
                .grouped-doc-table td:nth-child(3) {
                    width: 100%;
                }
                .secretaria-doc-group-head {
                    align-items: flex-start;
                    flex-direction: column;
                }
                .secretaria-grouped-doc-table td,
                .secretaria-grouped-doc-table td:nth-child(1),
                .secretaria-grouped-doc-table td:nth-child(2),
                .secretaria-grouped-doc-table td:nth-child(3) {
                    width: 100%;
                }
                .grouped-model-upload {
                    grid-template-columns: 1fr;
                }
                .grouped-model-upload .btn,
                .grouped-model-upload input {
                    width: 100%;
                    max-width: none;
                }
                .grouped-delivery-actions {
                    align-items: stretch;
                }
                .grouped-delivery-actions > a,
                .grouped-delivery-actions .upload-form {
                    width: 100%;
                }
                .doc-table td:first-child {
                    border-top: 0;
                }
                .doc-table td::before {
                    content: attr(data-label);
                    position: absolute;
                    left: 10px;
                    top: 11px;
                    width: 82px;
                    font-size: 10px;
                    font-weight: 900;
                    color: #3b4e6d;
                    text-transform: uppercase;
                }
                .model-upload,
                .upload-form {
                    align-items: stretch;
                }
                .model-upload input,
                .upload-form input {
                    width: 100%;
                    flex: 1 1 100%;
                }
                .model-upload .btn,
                .upload-form .btn {
                    width: 100%;
                }
                .validation-actions .btn {
                    flex: 1 1 120px;
                }
            }

            @media (max-width: 560px) {
                .top-right {
                    gap: 10px;
                }
                .top-right > span {
                    overflow-wrap: anywhere;
                }
                .profile-switch form {
                    display: grid !important;
                    grid-template-columns: 1fr;
                    align-items: stretch !important;
                }
                .profile-switch select,
                .profile-switch button {
                    width: 100%;
                }
                .phase-tabs,
                .phase-tabs.municipio {
                    grid-template-columns: 1fr 1fr;
                }
                .gantt-head,
                .gantt-body {
                    padding-left: 14px;
                    padding-right: 14px;
                }
            }

            @media (max-width: 700px) {
                .phase0-history-wrap {
                    max-height: none;
                    overflow: visible;
                    border: 0;
                    background: transparent;
                }
                .phase0-history-table,
                .phase0-history-table tbody {
                    display: block;
                    width: 100%;
                }
                .phase0-history-table thead {
                    display: none;
                }
                .phase0-history-table tr {
                    display: grid !important;
                    gap: 0;
                    margin-bottom: 10px;
                    border: 1px solid var(--line);
                    border-radius: 9px;
                    background: #fff;
                    overflow: hidden;
                }
                .phase0-history-table tbody td {
                    display: grid !important;
                    grid-template-columns: 105px minmax(0, 1fr);
                    gap: 10px;
                    width: 100% !important;
                    text-align: left !important;
                    border: 0 !important;
                    border-bottom: 1px solid var(--line) !important;
                    padding: 9px 10px !important;
                }
                .phase0-history-table tbody td:last-child {
                    border-bottom: 0 !important;
                }
                .phase0-history-table tbody td::before {
                    content: attr(data-label);
                    font-size: 9px;
                    font-weight: 900;
                    text-transform: uppercase;
                    color: var(--muted);
                }
                .phase0-history-download {
                    justify-self: start;
                }
            }

            @media (max-width: 760px) {
                .workflow-log-wrap {
                    overflow: visible;
                    border: 0;
                }
                .workflow-log-table,
                .workflow-log-table tbody {
                    display: block;
                    min-width: 0;
                    width: 100%;
                }
                .workflow-log-table thead {
                    display: none;
                }
                .workflow-log-table tr {
                    display: grid;
                    margin-bottom: 10px;
                    border: 1px solid var(--line);
                    border-radius: 10px;
                    background: #fff;
                    overflow: hidden;
                }
                .workflow-log-table td {
                    display: grid;
                    grid-template-columns: 110px minmax(0, 1fr);
                    gap: 10px;
                    width: 100% !important;
                    text-align: left !important;
                    border-bottom: 1px solid var(--line) !important;
                    padding: 9px 10px;
                }
                .workflow-log-table td:last-child {
                    border-bottom: 0 !important;
                }
                .workflow-log-table td::before {
                    content: attr(data-label);
                    font-size: 9px;
                    font-weight: 900;
                    text-transform: uppercase;
                    color: var(--muted);
                }
                .workflow-log-download {
                    justify-self: start;
                }
                .workflow-log-pagination {
                    align-items: stretch;
                    flex-direction: column;
                }
                .workflow-log-pages {
                    justify-content: space-between;
                }
            }
            @page {
                size: A3 landscape;
                margin: 8mm;
            }
            @media print {
                html,
                body {
                    width: 100%;
                    min-width: 0 !important;
                    background: #fff !important;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                    color-adjust: exact !important;
                }
                body {
                    zoom: 0.78;
                }
                .app {
                    display: grid !important;
                    grid-template-columns: 280px minmax(0, 1fr) !important;
                    min-height: auto !important;
                    width: 100% !important;
                }
                .app.menu-collapsed {
                    grid-template-columns: 280px minmax(0, 1fr) !important;
                }
                .app.menu-collapsed .side {
                    display: block !important;
                    width: auto !important;
                    min-width: 0 !important;
                    padding: 20px 18px !important;
                    border-right: 1px solid var(--line) !important;
                    overflow: visible !important;
                    opacity: 1 !important;
                    pointer-events: auto !important;
                }
                .side {
                    position: relative !important;
                    top: auto !important;
                    height: auto !important;
                    min-height: 100vh !important;
                    break-inside: avoid !important;
                    page-break-inside: avoid !important;
                }
                .profilebox {
                    position: absolute !important;
                    bottom: 22px !important;
                }
                .main {
                    width: auto !important;
                    min-width: 0 !important;
                }
                .top {
                    position: relative !important;
                }
                .wrap {
                    padding: 24px 28px 34px !important;
                }
                .hero {
                    display: flex !important;
                }
                .notice {
                    min-width: 360px !important;
                }
                .kpis {
                    grid-template-columns: repeat(3, 1fr) !important;
                }
                .situational-kpis {
                    grid-template-columns: repeat(6, minmax(0, 1fr)) !important;
                }
                .process-phase-track {
                    grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
                }
                .phase-tabs {
                    grid-template-columns: repeat(8, 1fr) !important;
                }
                .phase-tabs.municipio {
                    grid-template-columns: repeat(7, 1fr) !important;
                }
                .content {
                    grid-template-columns: 1fr !important;
                }
                .phase0-grid {
                    grid-template-columns: 1.05fr 1fr !important;
                }
                .details-row {
                    grid-template-columns: repeat(4, 1fr) !important;
                }
                .gantt-scale,
                .gantt-row {
                    grid-template-columns: 210px 1fr !important;
                }
                .gantt-days {
                    grid-template-columns: repeat(10, 1fr) !important;
                }
                .card,
                .kpi,
                .gantt-panel,
                .phase-tabs,
                .notice,
                .side,
                .top {
                    box-shadow: none !important;
                }
                .card,
                .kpi,
                .gantt-panel,
                .phase-tabs,
                .notice,
                .gantt-row,
                .doc-table tr,
                .register tr {
                    break-inside: avoid !important;
                    page-break-inside: avoid !important;
                }
                .gantt-panel {
                    break-before: auto !important;
                    page-break-before: auto !important;
                }
                .no-print,
                .print-btn,
                .menu-toggle-btn,
                .profile-switch button {
                    display: none !important;
                }
                a {
                    text-decoration: none !important;
                    color: inherit !important;
                }
                input[type="file"],
                textarea {
                    border-color: #cfd8e3 !important;
                }
            }
        /* Complementos para a área dinâmica de configurações */
.config-nav{display:flex;gap:10px;flex-wrap:wrap;margin:18px 0 22px}.config-nav a{padding:11px 15px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--ink);font-weight:800;font-size:13px}.config-nav a.active{background:var(--purple);border-color:var(--purple);color:#fff}.config-grid{display:grid;grid-template-columns:minmax(320px,.85fr) minmax(520px,1.6fr);gap:18px;align-items:start}.config-grid.equal{grid-template-columns:1fr 1fr}.config-card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:20px;box-shadow:var(--shadow)}.config-card h2{margin:0 0 6px;font-size:18px}.config-card>p{margin:0 0 18px;color:var(--muted);font-size:13px;line-height:1.5}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}.form-grid .span-2{grid-column:1/-1}.field{display:flex;flex-direction:column;gap:6px}.field label{font-size:11px;font-weight:900;color:#465a76;text-transform:uppercase;letter-spacing:.04em}.field input,.field select,.field textarea{width:100%;border:1px solid #d8e2ee;border-radius:9px;background:#fff;padding:10px 11px;color:var(--ink);font:inherit}.field textarea{min-height:82px;resize:vertical}.field small{color:var(--muted)}.check-line{display:flex;align-items:center;gap:9px;font-size:13px;font-weight:700}.check-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;padding:11px;border:1px solid #e3eaf2;border-radius:10px;background:#f8fafc;max-height:190px;overflow:auto}.check-grid label{display:flex;gap:7px;align-items:flex-start;font-size:12px;line-height:1.35}.form-actions{display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin-top:15px}.btn.secondary{background:#edf3fa;color:#274b73}.btn.neutral{background:#fff;border:1px solid #cfd9e6;color:#395474}.btn.tiny{padding:7px 9px;font-size:11px}.btn.warn{background:#fff4de;color:#a45d00;border:1px solid #ffd89b}.btn.off{background:#fff0f0;color:#ad2e2b;border:1px solid #ffc9c8}.table-scroll{overflow:auto}.config-table{width:100%;border-collapse:collapse;font-size:12px}.config-table th{padding:10px 9px;text-align:left;background:#f5f8fc;color:#53657d;border-bottom:1px solid var(--line);white-space:nowrap}.config-table td{padding:11px 9px;border-bottom:1px solid #edf1f6;vertical-align:top}.config-table tr:last-child td{border-bottom:0}.config-table .actions{display:flex;gap:6px;flex-wrap:wrap}.config-table small{color:var(--muted);line-height:1.35}.status-mini{display:inline-flex;padding:4px 7px;border-radius:999px;font-size:10px;font-weight:900;background:#eaf8ef;color:#15733a}.status-mini.inactive{background:#f1f3f6;color:#687386}.entity-title{display:flex;gap:7px;align-items:center;flex-wrap:wrap}.entity-code{font-size:10px;font-weight:900;padding:3px 6px;border-radius:6px;background:#e9f1fb;color:#285b93}.relation-tag{display:inline-block;margin:2px 3px 2px 0;padding:4px 7px;border-radius:7px;background:#f1edff;color:#5e35ae;font-size:10px;font-weight:800}.config-note{padding:13px 15px;border-radius:11px;background:#eef6ff;border:1px solid #cfe5ff;color:#285884;font-size:12px;line-height:1.5;margin-bottom:16px}.config-kpis{grid-template-columns:repeat(4,minmax(0,1fr))}.doc-owner{display:flex;flex-wrap:wrap;gap:5px;margin-top:7px}.doc-owner span{padding:4px 7px;border-radius:7px;background:#eef3f8;color:#49627e;font-size:10px;font-weight:800}.doc-owner span.profile{background:#f0eaff;color:#643bb5}.empty-state{padding:32px;text-align:center;color:var(--muted);border:1px dashed #cbd7e4;border-radius:12px;background:#fbfdff}.secretaria-no-documents{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:16px;padding:22px 24px;margin:22px 0;border:1px solid #cfe0f6;border-left:6px solid #176fdd;border-radius:14px;background:linear-gradient(180deg,#f8fbff,#eef5fd);box-shadow:var(--shadow)}.secretaria-no-documents-icon{display:grid;place-items:center;width:52px;height:52px;border-radius:14px;background:#dcecff;color:#176fdd;font-size:25px;font-weight:900}.secretaria-no-documents h2{margin:0 0 5px;font-size:18px;color:var(--navy2)}.secretaria-no-documents p{margin:0;color:#50647d;font-size:12px;line-height:1.55}.secretaria-no-documents-meta{display:flex;flex-direction:column;align-items:flex-end;gap:7px;text-align:right}.secretaria-no-documents-meta span{display:inline-flex;padding:6px 9px;border-radius:999px;background:#fff;border:1px solid #cfe0f6;color:#315b86;font-size:9px;font-weight:900;text-transform:uppercase;white-space:nowrap}.secretaria-no-documents-meta b{font-size:12px;color:var(--navy2)}.phase-tabs.dynamic{display:flex;overflow-x:auto;grid-template-columns:none}.phase-tabs.dynamic .phase-tab{min-width:125px;flex:1}.actions-stack .download-current{margin-bottom:7px}.db-badge{display:inline-flex;gap:7px;align-items:center;padding:7px 10px;border-radius:9px;background:#eaf8ef;color:#156f39;font-size:11px;font-weight:900}.db-badge:before{content:'●';font-size:9px}.subheading{margin:22px 0 10px;font-size:14px}.muted-row{opacity:.58}.section-divider{height:1px;background:var(--line);margin:20px 0}.field-inline{display:grid;grid-template-columns:1fr auto;gap:8px;align-items:end}.model-current{padding:10px;border-radius:9px;background:#f8fafc;border:1px solid #e1e8f0;margin-top:8px;font-size:12px}.model-current b{display:block;margin-bottom:3px}.requirements-filter{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}.requirements-filter input,.requirements-filter select{border:1px solid #d8e2ee;border-radius:9px;padding:9px 10px}.top .mi-link{display:none}
/* Dashboard situacional — exclusivo do perfil Stratelli */
.situational-hero{display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap;margin-bottom:18px}.situational-hero h1{margin:0 0 6px;font-size:27px}.situational-hero p{margin:0;color:var(--muted);font-size:13px;line-height:1.5}.situational-update{display:inline-flex;align-items:center;gap:8px;padding:9px 12px;border:1px solid #cfe0f6;border-radius:10px;background:#f2f7fd;color:#315b86;font-size:11px;font-weight:900}.situational-update:before{content:'●';color:#18a64a;font-size:9px}.situational-kpis{grid-template-columns:repeat(6,minmax(0,1fr));margin-bottom:18px}.situational-kpis .kpi{min-width:0;padding:17px 18px}.situational-kpis .kpi small{font-size:10px}.situational-kpis .kpi b{font-size:clamp(17px,1.55vw,25px);line-height:1.02;letter-spacing:-.02em;white-space:nowrap;overflow-wrap:normal;word-break:normal;text-wrap:nowrap}.situational-kpis .kpi.current b{font-size:clamp(19px,1.8vw,25px)}.situational-kpis .kpi.kpi-deadline b{font-size:clamp(13px,1.35vw,20px);line-height:1.08;letter-spacing:-.01em;white-space:normal;overflow-wrap:anywhere;word-break:break-word;text-wrap:balance;max-width:100%}.situational-kpis .kpi.alert{border-top-color:#df4541}.situational-kpis .kpi.warning{border-top-color:#f2b632}.situational-kpis .kpi.success{border-top-color:#20a84d}.current-process-card{background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow);overflow:hidden;margin-bottom:18px}.current-process-main{display:grid;grid-template-columns:180px minmax(0,1fr) 250px;gap:0}.current-phase-marker{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:25px 20px;background:linear-gradient(145deg,var(--navy),var(--navy2));color:#fff;text-align:center}.current-phase-marker small{font-size:10px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;opacity:.8}.current-phase-marker b{font-size:54px;line-height:1;margin:8px 0}.current-phase-marker span{font-size:12px;font-weight:800;line-height:1.35}.current-phase-info{padding:23px 25px}.current-phase-top{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap}.current-phase-top h2{margin:0 0 5px;font-size:21px}.current-phase-top p{margin:0;color:var(--muted);font-size:12px;line-height:1.5}.current-phase-status{display:inline-flex;align-items:center;justify-content:center;padding:7px 10px;border-radius:999px;font-size:9px;font-weight:900;letter-spacing:.04em;white-space:nowrap}.current-phase-status.done{background:#d8f4e1;color:#15733a}.current-phase-status.running{background:#dcecff;color:#176fdd}.current-phase-status.correction{background:#ffedb8;color:#946000}.current-phase-status.pending{background:#eef1f5;color:#65758a}.current-phase-progress{margin-top:18px}.current-phase-progress-head{display:flex;justify-content:space-between;gap:12px;margin-bottom:7px;font-size:11px;font-weight:900;color:#50647d}.current-phase-progress-track{height:11px;border-radius:999px;background:#edf1f5;overflow:hidden}.current-phase-progress-track span{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#176fdd,#18a64a)}.current-phase-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-top:15px}.current-phase-stat{padding:9px;border:1px solid #e3eaf2;border-radius:9px;background:#f8fafc}.current-phase-stat b{display:block;font-size:18px;line-height:1}.current-phase-stat small{font-size:9px;font-weight:900;text-transform:uppercase;color:#65758a}.current-phase-action{display:flex;flex-direction:column;justify-content:center;align-items:stretch;padding:22px;border-left:1px solid var(--line);background:#fbfdff}.current-phase-action strong{font-size:12px;margin-bottom:5px}.current-phase-action p{margin:0 0 13px;color:var(--muted);font-size:11px;line-height:1.45}.current-phase-action .btn{width:100%;text-align:center}.process-situation-card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:20px;box-shadow:var(--shadow);margin-bottom:18px}.process-situation-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:15px}.process-situation-head h2{margin:0 0 5px;font-size:19px}.process-situation-head p{margin:0;color:var(--muted);font-size:12px}.process-phase-track{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:9px}.process-phase-step{position:relative;display:block;padding:13px;border:1px solid #dfe7f0;border-radius:11px;background:#fafcff;color:var(--ink);text-decoration:none;min-height:92px}.process-phase-step:hover{border-color:#9dbde1;transform:translateY(-1px)}.process-phase-step.current{box-shadow:0 0 0 2px rgba(23,111,221,.14);border-color:#176fdd}.process-phase-step.done{background:#f3fbf5;border-color:#b7e4c4}.process-phase-step.correction{background:#fffaf0;border-color:#f4d184}.process-phase-step.running{background:#f2f7ff;border-color:#b9d3f3}.process-phase-step.pending{background:#fafbfc}.process-phase-number{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:9px}.process-phase-number b{font-size:12px}.process-phase-dot{width:10px;height:10px;border-radius:50%;background:#aab5c3}.process-phase-step.done .process-phase-dot{background:#20a84d}.process-phase-step.correction .process-phase-dot{background:#f2b632}.process-phase-step.running .process-phase-dot{background:#176fdd}.process-phase-step strong{display:block;font-size:12px;line-height:1.35}.process-phase-step small{display:block;margin-top:5px;color:#65758a;font-size:9px;font-weight:800;text-transform:uppercase}.process-phase-summary{display:grid;grid-template-columns:repeat(4,minmax(78px,1fr));align-content:start;align-items:start;gap:10px;padding:13px;border:1px solid #cfe0f6;border-radius:11px;background:linear-gradient(180deg,#f7fbff,#edf4fb);min-height:92px;height:100%;box-shadow:inset 0 0 0 1px rgba(255,255,255,.55)}.process-phase-summary.span-2{grid-column:span 2}.process-phase-summary.span-3{grid-column:span 3}.process-phase-summary.span-4{grid-column:span 4}.process-phase-summary.span-5{grid-column:1/-1}.process-phase-summary-intro{grid-column:1/-1;min-width:0;text-align:center;display:flex;flex-direction:column;align-items:center;justify-content:flex-start}.process-phase-summary-intro strong{display:block;font-size:13px;line-height:1.3;color:var(--navy2)}.process-phase-summary-intro small{display:block;margin-top:5px;color:#5d7086;font-size:9px;font-weight:800;line-height:1.35;max-width:320px}.process-phase-summary-metric{min-width:0;padding:8px 9px;border-radius:9px;background:rgba(255,255,255,.86);border:1px solid rgba(8,42,85,.08)}.process-phase-summary-metric b{display:block;font-size:17px;line-height:1;color:var(--navy2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.process-phase-summary-metric small{display:block;margin-top:4px;color:#5d7086;font-size:8px;font-weight:900;text-transform:uppercase;line-height:1.25}.situational-pending-section{background:#fff;border:1px solid var(--line);border-radius:16px;padding:20px;box-shadow:var(--shadow)}.situational-pending-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:15px}.situational-pending-head h2{margin:0 0 5px;font-size:19px}.situational-pending-head p{margin:0;color:var(--muted);font-size:12px;line-height:1.45}.situational-pending-total{display:inline-flex;align-items:center;gap:8px;padding:8px 11px;border-radius:999px;background:#fff3f2;border:1px solid #f0aaa7;color:#ad2e2b;font-size:11px;font-weight:900}.situational-secretary-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.situational-secretary-card{border:1px solid;border-left-width:6px;border-radius:13px;padding:15px;overflow:hidden}.situational-secretary-card.not-delivered{background:#fff7f6;border-color:#f0aaa7;border-left-color:#df4541}.situational-secretary-card.needs-correction{background:#fffaf0;border-color:#f4d184;border-left-color:#f2b632}.situational-secretary-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:11px}.situational-secretary-identification{display:flex;align-items:center;gap:10px;min-width:0;flex-wrap:wrap}.situational-secretary-identification>div:last-child{min-width:0;flex:1 1 180px}.situational-secretary-avatar{flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;width:auto;min-width:42px;max-width:100%;height:42px;padding:0 12px;border-radius:11px;background:#fff;border:1px solid rgba(8,42,85,.1);font-size:11px;font-weight:900;color:var(--navy2);white-space:nowrap;line-height:1;overflow:visible}.situational-secretary-identification strong{display:block;font-size:14px;line-height:1.3}.situational-secretary-identification small{display:block;margin-top:3px;color:#65758a;font-size:10px;font-weight:800}.situational-secretary-count{flex:0 0 auto;display:grid;place-items:center;min-width:42px;height:42px;border-radius:11px;font-size:18px;font-weight:900}.situational-secretary-card.not-delivered .situational-secretary-count{background:#ffdedd;color:#ad2e2b}.situational-secretary-card.needs-correction .situational-secretary-count{background:#ffedb8;color:#946000}.situational-doc-list{display:grid;gap:7px}.situational-doc-item{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:start;padding:10px;border-radius:9px;background:rgba(255,255,255,.82);border:1px solid rgba(8,42,85,.08)}.situational-doc-item strong{display:block;font-size:11px;line-height:1.35}.situational-doc-item small{display:block;margin-top:4px;color:#65758a;font-size:9px;line-height:1.4}.situational-doc-status{display:inline-flex;padding:5px 7px;border-radius:999px;font-size:8px;font-weight:900;white-space:nowrap}.situational-doc-status.not-delivered{background:#ffdedd;color:#ad2e2b}.situational-doc-status.needs-correction{background:#ffedb8;color:#946000}.situational-doc-observation{grid-column:1/-1;padding-top:6px;border-top:1px dashed rgba(8,42,85,.12);color:#825700;font-size:9px;line-height:1.4}.situational-empty{padding:24px;border:1px solid #a7dfb8;border-radius:12px;background:#f3fff7;color:#15733a;text-align:center}.situational-empty b{display:block;font-size:16px;margin-bottom:5px}.situational-empty span{font-size:11px}

.schedule-start-control{display:flex;align-items:end;justify-content:space-between;gap:14px;flex-wrap:wrap;margin:-4px 0 18px;padding:13px 15px;border:1px solid #cfe0f6;border-radius:12px;background:#f7fbff}.schedule-start-control div{display:grid;gap:4px}.schedule-start-control strong{font-size:12px}.schedule-start-control small{color:var(--muted);font-size:10px}.schedule-start-control form{display:flex;align-items:end;gap:8px;flex-wrap:wrap}.schedule-start-control label{display:grid;gap:4px;font-size:9px;font-weight:900;text-transform:uppercase;color:#50647d}.schedule-start-control input{height:36px;border:1px solid var(--line);border-radius:8px;padding:0 9px;font-family:inherit;color:var(--ink);background:#fff}
.situational-kpis{grid-template-columns:repeat(6,minmax(0,1fr))}.kpi.deadline-on-track,.kpi.deadline-completed-on-time{border-top-color:#20a84d}.kpi.deadline-attention{border-top-color:#f2b632}.kpi.deadline-overdue,.kpi.deadline-completed-late{border-top-color:#df4541}.kpi.deadline-blocked,.kpi.deadline-scheduled{border-top-color:#8795a8}.deadline-status-pill{display:inline-flex;align-items:center;justify-content:center;width:max-content;max-width:100%;padding:6px 9px;border-radius:999px;font-size:8px;font-weight:900;letter-spacing:.04em;text-transform:uppercase}.deadline-status-pill.on-track,.deadline-status-pill.completed-on-time{background:#d8f4e1;color:#15733a}.deadline-status-pill.attention{background:#ffedb8;color:#946000}.deadline-status-pill.overdue,.deadline-status-pill.completed-late{background:#ffdedd;color:#ad2e2b}.deadline-status-pill.blocked,.deadline-status-pill.scheduled{background:#e9eef4;color:#5f6d7f}.current-phase-deadline-dates{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px;margin:9px 0}.current-phase-deadline-dates div{padding:8px;border:1px solid #e2eaf3;border-radius:8px;background:#fff}.current-phase-deadline-dates small{display:block;color:#65758a;font-size:8px;font-weight:900;text-transform:uppercase}.current-phase-deadline-dates b{display:block;margin-top:3px;font-size:12px}.deadline-time-head{display:flex;justify-content:space-between;gap:10px;margin:8px 0 5px;color:#50647d;font-size:9px;font-weight:900}.deadline-time-track{position:relative;height:8px;border-radius:999px;background:#e8edf3;overflow:hidden}.deadline-time-track span{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#176fdd,#18a64a)}.deadline-time-track em{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);font-style:normal;font-size:8px;font-weight:900;letter-spacing:.02em;color:#315b86;pointer-events:none;z-index:2;white-space:nowrap}.deadline-time-track.attention span{background:#f2b632}.deadline-time-track.overdue span,.deadline-time-track.completed-late span{background:#df4541}.deadline-note{margin:7px 0 11px!important;font-weight:800}.process-phase-deadline{margin-top:7px!important;padding-top:6px;border-top:1px dashed rgba(8,42,85,.12);text-transform:none!important;line-height:1.35}.process-phase-deadline.on-track,.process-phase-deadline.completed-on-time{color:#15733a!important}.process-phase-deadline.attention{color:#946000!important}.process-phase-deadline.overdue,.process-phase-deadline.completed-late{color:#ad2e2b!important}
.deadline-control-card{margin-top:0;border-left:5px solid #8795a8}.deadline-control-card.on-track,.deadline-control-card.completed-on-time{border-left-color:#20a84d;background:#f7fdf9}.deadline-control-card.attention{border-left-color:#f2b632;background:#fffaf0}.deadline-control-card.overdue,.deadline-control-card.completed-late{border-left-color:#df4541;background:#fff7f6}.deadline-control-card.blocked,.deadline-control-card.scheduled{border-left-color:#8795a8;background:#fafbfd}.deadline-control-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap}.deadline-control-head h2{margin:0 0 5px;font-size:18px}.deadline-control-head p{margin:0;color:var(--muted);font-size:11px}.deadline-control-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:9px;margin-top:14px}.deadline-control-item{padding:10px;border:1px solid #dfe7f0;border-radius:9px;background:rgba(255,255,255,.82)}.deadline-control-item small{display:block;color:#65758a;font-size:8px;font-weight:900;text-transform:uppercase}.deadline-control-item b{display:block;margin-top:4px;font-size:12px;line-height:1.35}.deadline-control-progress{margin-top:12px}.deadline-control-actions{display:flex;align-items:end;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:13px;padding-top:12px;border-top:1px dashed #dce5ef}.deadline-control-actions form{display:flex;align-items:end;gap:8px;flex-wrap:wrap}.deadline-control-actions label{display:grid;gap:4px;color:#50647d;font-size:8px;font-weight:900;text-transform:uppercase}.deadline-control-actions input,.deadline-control-actions textarea{border:1px solid var(--line);border-radius:8px;padding:8px;font-family:inherit;background:#fff}.deadline-control-actions textarea{min-width:210px;resize:vertical}.deadline-provisional{display:inline-flex;margin-top:7px;padding:5px 7px;border-radius:7px;background:#eef1f5;color:#5f6d7f;font-size:9px;font-weight:800}
.gantt-head-chips{display:flex;gap:7px;align-items:center;flex-wrap:wrap;justify-content:flex-end}.gantt-line{overflow:visible}.gantt-today{position:absolute;top:-3px;bottom:-3px;width:2px;background:#176fdd;z-index:4;box-shadow:0 0 0 1px rgba(255,255,255,.7)}.gantt-today:before{content:'Hoje';position:absolute;top:-18px;left:50%;transform:translateX(-50%);padding:2px 5px;border-radius:5px;background:#176fdd;color:#fff;font-size:7px;font-weight:900;white-space:nowrap}.gantt-today.edge-end:before{left:auto;right:0;transform:none}.gantt-completed{position:absolute;top:-3px;bottom:-3px;width:2px;z-index:4;box-shadow:0 0 0 1px rgba(255,255,255,.7)}.gantt-completed:before{content:'Concluído';position:absolute;top:-18px;left:50%;transform:translateX(-50%);padding:2px 5px;border-radius:5px;color:#fff;font-size:7px;font-weight:900;white-space:nowrap}.gantt-completed.edge-end:before{left:auto;right:0;transform:none}.gantt-completed.done{background:#20a84d}.gantt-completed.done:before{background:#20a84d}.gantt-completed.late{background:#df4541}.gantt-completed.late:before{background:#df4541}.gantt-bar.deadline-on-track{background:linear-gradient(90deg,#176fdd,#2288df)}.gantt-bar.deadline-attention{background:linear-gradient(90deg,#d89000,#f2b632)}.gantt-bar.deadline-overdue{background:linear-gradient(90deg,#bd2f2b,#df4541)}.gantt-bar.deadline-completed-on-time{background:linear-gradient(90deg,#12843d,#20a84d)}.gantt-bar.deadline-completed-late{background:linear-gradient(90deg,#9f2724,#df4541)}.gantt-bar.deadline-blocked,.gantt-bar.deadline-scheduled{background:linear-gradient(90deg,#718097,#a9b4c3)}.gantt-label .gantt-operational-date{display:block;margin-top:4px;font-size:9px;font-weight:800;color:#50647d}.gantt-label .gantt-operational-status{display:inline-flex;margin-top:4px;padding:3px 5px;border-radius:6px;font-size:7px;font-weight:900;text-transform:uppercase}.gantt-operational-status.on-track,.gantt-operational-status.completed-on-time{background:#d8f4e1;color:#15733a}.gantt-operational-status.attention{background:#ffedb8;color:#946000}.gantt-operational-status.overdue,.gantt-operational-status.completed-late{background:#ffdedd;color:#ad2e2b}.gantt-operational-status.blocked,.gantt-operational-status.scheduled{background:#e9eef4;color:#5f6d7f}
@media(max-width:1100px){.deadline-control-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:680px){.deadline-control-grid{grid-template-columns:1fr 1fr}.current-phase-deadline-dates{grid-template-columns:1fr}.schedule-start-control{align-items:stretch}.schedule-start-control form{display:grid;grid-template-columns:1fr auto;width:100%}.schedule-start-control input{width:100%}}
@media(max-width:1100px){.config-grid,.config-grid.equal{grid-template-columns:1fr}.config-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.workflow-kpis,.workflow-kpis.secretaria{grid-template-columns:repeat(2,minmax(0,1fr))}.situational-kpis{grid-template-columns:repeat(6,minmax(145px,1fr));overflow-x:auto;padding-bottom:4px}.process-phase-track{grid-template-columns:repeat(3,minmax(0,1fr))}.process-phase-summary.span-2,.process-phase-summary.span-3,.process-phase-summary.span-4,.process-phase-summary.span-5{grid-column:1/-1}.process-phase-summary{grid-template-columns:repeat(4,minmax(0,1fr))}.current-process-main{grid-template-columns:150px minmax(0,1fr)}.current-phase-action{grid-column:1/-1;border-left:0;border-top:1px solid var(--line);display:grid;grid-template-columns:1fr auto;gap:12px;align-items:center}.current-phase-action p{margin:0}.situational-secretary-grid{grid-template-columns:1fr}}
@media(max-width:680px){.form-grid,.check-grid{grid-template-columns:1fr}.form-grid .span-2{grid-column:auto}.config-kpis{grid-template-columns:1fr}.workflow-kpis,.workflow-kpis.secretaria{grid-template-columns:1fr}.situational-kpis{grid-template-columns:repeat(6,minmax(145px,1fr));overflow-x:auto}.process-phase-track{grid-template-columns:1fr}.process-phase-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.process-phase-summary-intro{grid-column:1/-1}.config-card{padding:15px}.current-process-main{grid-template-columns:1fr}.current-phase-marker{padding:18px}.current-phase-marker b{font-size:42px}.current-phase-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.current-phase-action{display:block}.current-phase-action p{margin-bottom:12px}.situational-doc-item{grid-template-columns:1fr}.situational-doc-status{width:max-content}.situational-doc-observation{grid-column:auto}.secretaria-no-documents{grid-template-columns:1fr;text-align:center}.secretaria-no-documents-icon{margin:0 auto}.secretaria-no-documents-meta{align-items:center;text-align:center}}
@media print{.config-nav,.config-card form,.config-table .actions{display:none!important}}

/* Cabeçalho contextual: perfil, secretaria e ações */
.top.app-header{
    display:grid;
    grid-template-columns:minmax(560px,1fr) auto;
    align-items:end;
    gap:18px;
    padding:12px 20px;
    min-height:74px;
}
.header-profile-area,.profile-switch{min-width:0;width:100%}
.profile-switch-form{
    display:grid;
    grid-template-columns:minmax(180px,220px) auto;
    align-items:end;
    gap:10px;
    width:100%;
    margin:0;
}
.profile-switch-form.has-secretaria{
    grid-template-columns:minmax(170px,205px) minmax(280px,430px) auto;
}
.profile-control{
    display:grid;
    grid-template-rows:auto 42px;
    gap:5px;
    min-width:0;
}
.profile-control label{
    display:flex;
    align-items:center;
    gap:6px;
    margin:0;
    color:#526681;
    font-size:9px;
    font-weight:900;
    line-height:1;
    letter-spacing:.055em;
    text-transform:uppercase;
    white-space:nowrap;
}
.profile-control label i{
    display:grid;
    place-items:center;
    width:17px;
    height:17px;
    border-radius:5px;
    background:#eaf2fb;
    color:var(--navy2);
    font-style:normal;
    font-size:9px;
}
.profile-switch .profile-control select{
    width:100%;
    min-width:0;
    height:42px;
    padding:0 36px 0 12px;
    border:1px solid #cfdaea;
    border-radius:10px;
    background-color:#fff;
    color:var(--ink);
    font-size:12px;
    font-weight:800;
    line-height:42px;
    box-shadow:0 1px 2px rgba(8,42,85,.03);
    text-overflow:ellipsis;
}
.secretaria-profile-select{
    display:grid;
}
.secretaria-profile-select[hidden]{display:none!important}
.profile-switch .profile-apply-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:7px;
    align-self:end;
    min-width:112px;
    height:42px;
    padding:0 15px;
    border-radius:10px;
    white-space:nowrap;
    font-size:11px;
}
.header-actions{
    display:flex;
    align-items:center;
    justify-content:center;
    align-self:end;
    justify-self:center;
    gap:10px;
    min-width:0;
    height:42px;
    min-height:42px;
    margin:0;
    font-size:12px;
}
.header-action-buttons{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    height:42px;
    min-height:42px;
    flex:0 0 auto;
}
.header-action-buttons .menu-toggle-btn,
.header-action-buttons .print-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    height:42px;
    min-height:42px;
    margin:0;
    border-radius:10px;
    white-space:nowrap;
    text-align:center;
}
.header-context{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:7px;
    min-width:0;
    height:42px;
    min-height:42px;
}
.header-context-chip{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    height:42px;
    min-height:42px;
    max-width:230px;
    margin:0;
    padding:0 10px;
    border:1px solid #d8e3ef;
    border-radius:10px;
    background:#f8fbff;
    color:#536780;
    line-height:1.2;
    white-space:nowrap;
}
.header-context-chip b{
    max-width:165px;
    overflow:hidden;
    color:var(--navy);
    text-overflow:ellipsis;
    white-space:nowrap;
}
.header-context-chip.secretaria{
    border-color:#cfe0f6;
    background:#f1f7ff;
}
.header-icon-btn{
    display:grid;
    place-items:center;
    flex:0 0 auto;
    width:42px;
    height:42px;
    margin:0;
    border:1px solid #d8e3ef;
    border-radius:10px;
    background:#fff;
    color:var(--navy);
    font-size:15px;
}
.header-user-indicator{
    width:9px;
    height:9px;
    border:2px solid #fff;
    border-radius:50%;
    background:#20a84d;
    box-shadow:0 0 0 1px #b8d8c2;
}
.notification-center{
    position:relative;
    display:flex;
    align-items:center;
    justify-content:center;
    height:42px;
}
.notification-center .header-icon-btn{
    position:relative;
    appearance:none;
    cursor:pointer;
    padding:0;
    font-family:inherit;
}
.notification-center .header-icon-btn:hover,
.notification-center .header-icon-btn[aria-expanded="true"]{
    border-color:#9dbde1;
    background:#f1f7ff;
    box-shadow:0 0 0 3px rgba(23,111,221,.08);
}
.notification-badge{
    position:absolute;
    top:-6px;
    right:-6px;
    display:grid;
    place-items:center;
    min-width:19px;
    height:19px;
    padding:0 5px;
    border:2px solid #fff;
    border-radius:999px;
    background:#df4541;
    color:#fff;
    font-size:8px;
    font-weight:900;
    line-height:1;
    box-shadow:0 2px 6px rgba(173,46,43,.25);
}
.notification-panel{
    position:absolute;
    z-index:120;
    top:calc(100% + 10px);
    right:0;
    width:min(390px,calc(100vw - 28px));
    max-height:min(620px,calc(100vh - 95px));
    overflow:hidden;
    border:1px solid #d6e1ed;
    border-radius:14px;
    background:#fff;
    box-shadow:0 18px 46px rgba(8,34,75,.20);
    text-align:left;
}
.notification-panel[hidden]{display:none!important}
.notification-panel-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    padding:15px 16px 12px;
    border-bottom:1px solid #e6edf5;
    background:linear-gradient(180deg,#fbfdff,#f5f9fd);
}
.notification-panel-head h3{
    margin:0 0 3px;
    color:var(--navy);
    font-size:16px;
}
.notification-panel-head p{
    margin:0;
    color:#65758a;
    font-size:10px;
    line-height:1.4;
}
.notification-total{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:32px;
    height:25px;
    padding:0 8px;
    border-radius:999px;
    background:#e9f1fb;
    color:#285b93;
    font-size:10px;
    font-weight:900;
    white-space:nowrap;
}
.notification-list{
    max-height:430px;
    overflow:auto;
    padding:7px;
}
.notification-item{
    display:grid;
    grid-template-columns:36px minmax(0,1fr) auto;
    gap:10px;
    align-items:start;
    padding:10px;
    border:1px solid transparent;
    border-radius:10px;
    color:var(--ink);
    text-decoration:none;
}
.notification-item:hover{
    border-color:#d8e5f2;
    background:#f8fbff;
}
.notification-item + .notification-item{margin-top:3px}
.notification-item-icon{
    display:grid;
    place-items:center;
    width:36px;
    height:36px;
    border-radius:10px;
    font-size:17px;
    font-weight:900;
}
.notification-item.waiting .notification-item-icon{background:#dcecff;color:#176fdd}
.notification-item.correction .notification-item-icon{background:#ffedb8;color:#946000}
.notification-item.not-delivered .notification-item-icon{background:#ffdedd;color:#ad2e2b}
.notification-item.approved .notification-item-icon{background:#d8f4e1;color:#15733a}
.notification-item-content{min-width:0}
.notification-item-content strong{
    display:block;
    margin-bottom:3px;
    font-size:11px;
    line-height:1.3;
}
.notification-item-content span{
    display:-webkit-box;
    overflow:hidden;
    color:#65758a;
    font-size:9px;
    line-height:1.45;
    -webkit-line-clamp:2;
    -webkit-box-orient:vertical;
}
.notification-item-time{
    color:#8492a5;
    font-size:8px;
    font-weight:800;
    white-space:nowrap;
}
.notification-empty{
    padding:28px 20px;
    text-align:center;
    color:#65758a;
}
.notification-empty b{
    display:block;
    margin:7px 0 4px;
    color:#15733a;
    font-size:13px;
}
.notification-empty span{font-size:10px;line-height:1.45}
.notification-panel-footer{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    padding:10px 14px;
    border-top:1px solid #e6edf5;
    background:#f8fbff;
    color:#65758a;
    font-size:9px;
    font-weight:800;
}
.notification-panel-footer a{
    color:#176fdd;
    font-weight:900;
    text-decoration:none;
    white-space:nowrap;
}
.notification-panel-footer a:hover{text-decoration:underline}
@media(max-width:1380px){
    .top.app-header{grid-template-columns:1fr;gap:10px}
    .header-actions{width:100%;justify-content:center}
    .header-context{margin-left:0;justify-content:center}
}
@media(max-width:900px){
    .top.app-header{padding:12px 14px}
    .profile-switch-form.has-secretaria{grid-template-columns:minmax(150px,.75fr) minmax(240px,1.5fr) auto}
    .top-right.header-actions{width:100%;align-items:center;justify-content:center;flex-direction:row}
}
@media(max-width:720px){
    .profile-switch-form,
    .profile-switch-form.has-secretaria{grid-template-columns:1fr}
    .profile-switch .profile-apply-btn{width:100%}
    .header-actions{align-items:stretch;flex-direction:column}
    .header-action-buttons{display:grid;grid-template-columns:1fr 1fr}
    .header-context{justify-content:flex-start;flex-wrap:wrap;margin-left:0}
    .header-context-chip{max-width:100%}
    .notification-panel{position:fixed;top:72px;left:12px;right:12px;width:auto;max-height:calc(100vh - 86px)}
}


            /* Indicadores documentais do Dashboard — estrutura v47 */
            .dashboard-process-preview .dashboard-phase-metrics-v47 {
                display: grid !important;
                grid-template-columns: minmax(0, 1fr) !important;
                grid-auto-flow: row !important;
                grid-auto-rows: auto !important;
                align-content: center !important;
                align-items: stretch !important;
                justify-content: stretch !important;
                gap: 5px !important;
                min-width: 0 !important;
                min-height: 148px !important;
                height: auto !important;
                margin: 13px 3px 0 !important;
                padding: 10px !important;
                list-style: none !important;
                overflow: visible !important;
                white-space: normal !important;
                text-align: left !important;
            }
            .dashboard-process-preview .dashboard-phase-metrics-v47::before {
                top: -14px !important;
            }
            .dashboard-process-preview .dashboard-phase-metric-v47 {
                display: grid !important;
                grid-template-columns: 18px minmax(0, 1fr) !important;
                align-items: start !important;
                gap: 5px !important;
                width: 100% !important;
                min-width: 0 !important;
                min-height: 25px !important;
                margin: 0 !important;
                padding: 5px 0 !important;
                border: 0 !important;
                border-bottom: 1px solid rgba(255,255,255,.22) !important;
                color: inherit !important;
                font-size: clamp(9px, .7vw, 11px) !important;
                font-weight: 800 !important;
                line-height: 1.28 !important;
                white-space: normal !important;
                overflow: visible !important;
                text-overflow: clip !important;
                overflow-wrap: normal !important;
                word-break: normal !important;
            }
            .dashboard-process-preview .dashboard-phase-metric-v47:last-child {
                border-bottom: 0 !important;
            }
            .dashboard-process-preview .dashboard-phase-metric-v47 > span:last-child {
                display: block !important;
                min-width: 0 !important;
                white-space: normal !important;
            }
            .dashboard-process-preview .dashboard-phase-metric-v47 strong {
                display: inline !important;
                color: inherit !important;
                font-size: 1.08em !important;
                font-weight: 950 !important;
            }
            .dashboard-process-preview .dashboard-phase-metric-icon-v47 {
                display: inline-grid !important;
                place-items: center !important;
                width: 18px !important;
                height: 18px !important;
                color: inherit !important;
                font-size: 12px !important;
                font-weight: 950 !important;
                line-height: 1 !important;
            }
            .dashboard-process-preview .dashboard-phase-metric-empty-v47 {
                display: grid !important;
                place-items: center !important;
                min-height: 105px !important;
                padding: 8px !important;
                text-align: center !important;
                font-size: 10px !important;
                line-height: 1.4 !important;
            }
            @media (max-width: 1150px) {
                .dashboard-process-preview .dashboard-phase-metrics-v47 {
                    min-height: 154px !important;
                }
                .dashboard-process-preview .dashboard-phase-metric-v47 {
                    font-size: 10px !important;
                }
            }

            /* Módulos do menu lateral */
            .module-page{display:grid;gap:18px}.module-hero{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;flex-wrap:wrap}.module-hero h1{margin:0 0 6px;font-size:27px;color:var(--navy)}.module-hero p{margin:0;color:var(--muted);font-size:13px;line-height:1.5;max-width:900px}.module-context{display:inline-flex;align-items:center;gap:7px;padding:9px 12px;border:1px solid #cfe0f6;border-radius:10px;background:#f2f7fd;color:#315b86;font-size:10px;font-weight:900;text-transform:uppercase}.module-kpis-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px}.module-kpi{min-width:0;padding:16px 17px;border:1px solid #d7e2ef;border-top:3px solid #176fdd;border-radius:12px;background:#fff}.module-kpi.success{border-top-color:#20a84d}.module-kpi.warning{border-top-color:#f2b632}.module-kpi.danger{border-top-color:#df4541}.module-kpi.neutral{border-top-color:#8290a3}.module-kpi small{display:block;color:#566a84;font-size:9px;font-weight:900;text-transform:uppercase}.module-kpi b{display:block;margin:8px 0 5px;color:var(--navy);font-size:24px;line-height:1}.module-kpi span{display:block;color:var(--muted);font-size:11px;line-height:1.4}
            .municipality-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.municipality-card{display:flex;flex-direction:column;min-height:270px;border:1px solid #d7e2ef;border-radius:14px;background:#fff;overflow:hidden;box-shadow:0 4px 14px rgba(8,42,85,.05)}.municipality-card.active{border-color:#9fdcb1}.municipality-card.waiting{border-color:#cfd8e4}.municipality-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:17px 18px;background:#f5f8fc;border-bottom:1px solid #e0e7f0}.municipality-card.active .municipality-card-head{background:#f1fbf4}.municipality-card-head h2{margin:0;color:var(--navy);font-size:18px}.municipality-card-head p{margin:4px 0 0;color:var(--muted);font-size:11px}.municipality-status{display:inline-flex;padding:6px 9px;border-radius:999px;background:#e8edf3;color:#5e6d81;font-size:8px;font-weight:900;text-transform:uppercase;white-space:nowrap}.municipality-card.active .municipality-status{background:#dff5e6;color:#15733a}.municipality-card-body{flex:1;padding:17px 18px}.municipality-card-body>p{margin:0 0 15px;color:#53677f;font-size:12px;line-height:1.5}.municipality-metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}.municipality-metric{padding:10px;border:1px solid #e0e7ef;border-radius:9px;background:#f9fbfd}.municipality-metric b{display:block;color:var(--navy);font-size:18px}.municipality-metric small{display:block;margin-top:4px;color:#6a7b91;font-size:8px;font-weight:900;text-transform:uppercase}.municipality-phase{margin-top:13px;padding:10px 11px;border-radius:9px;background:#eef4fb;color:#315b86;font-size:11px;font-weight:800}.municipality-card-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 18px;border-top:1px solid #e4eaf1}.municipality-disabled{color:#8794a5;font-size:10px;font-weight:800}
            .phase-document-list{display:grid;gap:15px}.phase-document-section{border:1px solid #d7e2ef;border-radius:14px;background:#fff;overflow:hidden}.phase-document-head{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:14px 16px;background:#f1f5fa;border-bottom:1px solid #dfe7f0}.phase-document-title{display:flex;align-items:center;gap:11px}.phase-document-number{display:grid;place-items:center;width:34px;height:34px;border-radius:50%;background:#0d477b;color:#fff;font-size:14px;font-weight:900}.phase-document-head h2{margin:0;color:var(--navy);font-size:16px}.phase-document-head p{margin:3px 0 0;color:var(--muted);font-size:10px}.phase-document-count{display:inline-flex;padding:7px 10px;border-radius:999px;background:#fff;border:1px solid #cbd7e5;color:#52657e;font-size:9px;font-weight:900}.document-catalog-table{width:100%;border-collapse:collapse}.document-catalog-table th{padding:10px 12px;background:#fafbfd;border-bottom:1px solid #dfe7f0;color:#50647d;text-align:left;font-size:9px;text-transform:uppercase}.document-catalog-table td{padding:12px;border-bottom:1px solid #e5ebf2;vertical-align:top;font-size:10px;color:#455b74}.document-catalog-table tr:last-child td{border-bottom:0}.document-catalog-name b{display:block;color:var(--navy);font-size:12px}.document-catalog-name small{display:block;margin-top:4px;color:var(--muted);line-height:1.4}.module-status{display:inline-flex;align-items:center;padding:5px 8px;border-radius:999px;font-size:8px;font-weight:900;text-transform:uppercase;white-space:nowrap}.module-status.approved{background:#dff5e6;color:#15733a}.module-status.waiting{background:#e2efff;color:#1761b8}.module-status.correction{background:#fff0c8;color:#946000}.module-status.pending{background:#ffe3e1;color:#b1322e}.document-model-state{display:block;font-size:9px;font-weight:800}.document-model-state.available{color:#15733a}.document-model-state.missing{color:#8a6670}.document-last-file{max-width:240px;overflow-wrap:anywhere}.document-actions{display:flex;gap:6px;flex-wrap:wrap}.mini-link{display:inline-flex;align-items:center;justify-content:center;padding:6px 8px;border:1px solid #cbd8e7;border-radius:7px;background:#fff;color:#174b7c;font-size:9px;font-weight:900;text-decoration:none}.mini-link.primary{border-color:#0d477b;background:#0d477b;color:#fff}
            .report-catalog{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.report-card{padding:16px;border:1px solid #d7e2ef;border-radius:13px;background:#fff}.report-card-icon{display:grid;place-items:center;width:38px;height:38px;border-radius:10px;background:#edf4fb;color:#0d477b;font-size:18px}.report-card h3{margin:12px 0 6px;color:var(--navy);font-size:14px}.report-card p{margin:0;color:var(--muted);font-size:11px;line-height:1.5}.report-card strong{display:block;margin-top:12px;color:#174b7c;font-size:12px}.report-section{border:1px solid #d7e2ef;border-radius:14px;background:#fff;overflow:hidden}.report-section-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;background:#f3f7fb;border-bottom:1px solid #dfe7f0}.report-section-head h2{margin:0;color:var(--navy);font-size:16px}.report-section-head p{margin:3px 0 0;color:var(--muted);font-size:10px}.report-table{width:100%;border-collapse:collapse}.report-table th,.report-table td{padding:10px 12px;border-bottom:1px solid #e4eaf1;text-align:left;font-size:10px}.report-table th{background:#fafbfd;color:#52657d;font-size:9px;text-transform:uppercase}.report-table tr:last-child td{border-bottom:0}.progress-inline{display:flex;align-items:center;gap:8px;min-width:150px}.progress-inline-track{flex:1;height:7px;border-radius:999px;background:#e8edf3;overflow:hidden}.progress-inline-track span{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,#176fdd,#20a84d)}.progress-inline b{font-size:10px;color:var(--navy)}
            .history-filter{display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap;padding:14px 16px;border:1px solid #d7e2ef;border-radius:12px;background:#fff}.history-filter label{display:grid;gap:5px;color:#52657d;font-size:9px;font-weight:900;text-transform:uppercase}.history-filter select{min-width:190px;padding:9px 10px;border:1px solid #cdd9e7;border-radius:8px;background:#fff;color:var(--navy);font-weight:700}.history-table-wrap{border:1px solid #d7e2ef;border-radius:14px;background:#fff;overflow:auto}.history-table{width:100%;min-width:1050px;border-collapse:collapse}.history-table th{padding:0;background:#eef3f8;color:#50647d;text-align:left;font-size:9px;text-transform:uppercase}.history-table th.history-action-head{padding:10px 12px}.history-sort-link{display:flex;align-items:center;justify-content:space-between;gap:7px;width:100%;min-height:38px;padding:10px 12px;color:#50647d;text-decoration:none;font:inherit;font-weight:900;text-transform:uppercase;white-space:nowrap}.history-sort-link:hover{background:#e3edf7;color:#0d4b7f}.history-sort-link.active{background:#e7f1fd;color:#0d4b7f}.history-sort-icon{display:inline-grid;place-items:center;min-width:16px;font-size:10px;color:#8190a3}.history-sort-link.active .history-sort-icon{color:#176fdd}.history-table td{padding:11px 12px;border-bottom:1px solid #e4eaf1;vertical-align:top;color:#455b74;font-size:10px}.history-table tr:last-child td{border-bottom:0}.history-event{display:inline-flex;padding:5px 7px;border-radius:7px;background:#eef3f8;color:#34546f;font-size:8px;font-weight:900;text-transform:uppercase}.history-event.approved{background:#dff5e6;color:#15733a}.history-event.correction{background:#fff0c8;color:#946000}.history-event.model{background:#eee4ff;color:#6534bd}.history-file b,.history-user b{display:block;color:var(--navy);font-size:10px}.history-file small,.history-user small{display:block;margin-top:3px;color:var(--muted);font-size:8px}.history-empty{padding:28px;text-align:center;color:#66778d}.scope-note{padding:12px 14px;border-left:4px solid #176fdd;border-radius:9px;background:#eef5ff;color:#355879;font-size:11px;line-height:1.5}.history-pagination{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-top:12px;padding:12px 14px;border:1px solid #d7e2ef;border-radius:12px;background:#fff}.history-pagination-info{color:#5a6e86;font-size:10px;font-weight:800}.history-pagination-nav{display:flex;align-items:center;gap:6px;flex-wrap:wrap}.history-page-link,.history-page-current,.history-page-disabled{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 10px;border-radius:8px;font-size:10px;font-weight:900;text-decoration:none}.history-page-link{border:1px solid #cdd9e7;background:#fff;color:#174d7e}.history-page-link:hover{border-color:#176fdd;background:#eef5ff}.history-page-current{border:1px solid #0d4b7f;background:#0d4b7f;color:#fff}.history-page-disabled{border:1px solid #e0e6ee;background:#f4f6f9;color:#a0abb9;cursor:not-allowed}.history-page-ellipsis{padding:0 2px;color:#7b899b;font-weight:900}
            @media(max-width:1180px){.module-kpis-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.municipality-grid,.report-catalog{grid-template-columns:repeat(2,minmax(0,1fr))}.document-catalog-table{min-width:1000px}.phase-document-section{overflow:auto}}
            @media(max-width:760px){.module-kpis-grid,.municipality-grid,.report-catalog{grid-template-columns:1fr}.module-hero{display:block}.module-context{margin-top:10px}.municipality-metrics{grid-template-columns:1fr}.history-filter{align-items:stretch}.history-filter label,.history-filter select{width:100%}}
</style>
</head>
<body>
<div class="app">
    <aside class="side" id="menuLateral">
        <div class="brand"><div class="mark">IN</div><div>Sistema de<br />Governança<br /><small>de Proteção Municipal</small></div></div>
        <div class="user">
            <div class="avatar"><?=h(strtoupper(substr($usuarioLogado,0,1)))?></div>
            <div><b><?=h($usuarioLogado)?></b><br /><span><?=h($emailUsuario)?></span><br /><span class="role <?=h($perfilClasse)?>"><?=h($perfilRotulo)?></span></div>
        </div>
        <div class="menu">
            <?php if($perfil==='municipio'): ?>
                <a class="mi <?=$view==='dashboard'?'active':''?>" href="?view=dashboard&perfil=municipio&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>">▦ Dashboard Municipal</a>
                <a class="mi <?=$view==='workflow'?'active':''?>" href="?view=workflow&perfil=municipio&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>">▤ Workflow de Contratação</a>
                <a class="mi <?=$view==='documentos'?'active':''?>" href="?view=documentos&perfil=municipio&municipio=<?=h($municipioId)?>">▤ Documentos</a>
                <a class="mi <?=$view==='relatorios'?'active':''?>" href="?view=relatorios&perfil=municipio&municipio=<?=h($municipioId)?>">▥ Relatórios</a>
                <a class="mi <?=$view==='historico'?'active':''?>" href="?view=historico&perfil=municipio&municipio=<?=h($municipioId)?>">◷ Histórico</a>
                <div class="mi">↪ Sair</div>
            <?php elseif($perfil==='secretaria'): ?>
                <a class="mi <?=$view==='dashboard'?'active':''?>" href="?view=dashboard&perfil=secretaria&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>">▦ Dashboard da Secretaria</a>
                <a class="mi <?=$view==='workflow'?'active':''?>" href="?view=workflow&perfil=secretaria&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>">▤ Minhas Entregas</a>
                <a class="mi <?=$view==='relatorios'?'active':''?>" href="?view=relatorios&perfil=secretaria&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>">▥ Meus Relatórios</a>
                <a class="mi <?=$view==='historico'?'active':''?>" href="?view=historico&perfil=secretaria&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>">◷ Meu Histórico</a>
                <div class="mi">↪ Sair</div>
            <?php else: ?>
                <a class="mi <?=$view==='dashboard'?'active':''?>" href="?view=dashboard&perfil=stratelli&municipio=<?=h($municipioId)?>">▦ Dashboard Situacional</a>
                <a class="mi <?=$view==='municipios'?'active':''?>" href="?view=municipios&perfil=stratelli&municipio=<?=h($municipioId)?>">▥ Municípios</a>
                <a class="mi <?=$view==='workflow'?'active':''?>" href="?view=workflow&perfil=stratelli&municipio=<?=h($municipioId)?>">▤ Workflow de Contratação</a>
                <a class="mi <?=$view==='documentos'?'active':''?>" href="?view=documentos&perfil=stratelli&municipio=<?=h($municipioId)?>">▤ Documentos</a>
                <a class="mi <?=$view==='relatorios'?'active':''?>" href="?view=relatorios&perfil=stratelli&municipio=<?=h($municipioId)?>">▥ Relatórios</a>
                <a class="mi <?=$view==='historico'?'active':''?>" href="?view=historico&perfil=stratelli&municipio=<?=h($municipioId)?>">◷ Histórico</a>
                <a class="mi <?=$view==='configuracoes'?'active':''?>" href="?view=configuracoes&perfil=stratelli&municipio=<?=h($municipioId)?>">⚙ Configurações</a>
                <div class="mi">↪ Sair</div>
            <?php endif; ?>
        </div>
        <div class="profilebox <?=h($perfilClasse)?>"><small>Perfil ativo</small><b><?=h($perfilRotulo)?></b><?php if($perfil==='secretaria'&&$secretariaPerfil):?><small><?=h($secretariaPerfil['sigla']?:$secretariaPerfil['nome'])?></small><?php endif;?></div>
    </aside>
    <main class="main">
        <header class="top app-header">
            <div class="header-profile-area">
                <div class="profile-switch">
                    <form method="get" class="profile-switch-form <?=$perfil==='secretaria'?'has-secretaria':'compact'?>">
                        <input type="hidden" name="view" value="<?=h(in_array($view,['dashboard','municipios','documentos','relatorios','historico','configuracoes','workflow'],true)?$view:'workflow')?>" />
                        <input type="hidden" name="municipio" value="<?=h($municipioId)?>" />
                        <div class="profile-control">
                            <label for="perfilRapido"><i>◉</i>Visualizar como</label>
                            <select id="perfilRapido" name="perfil"><option value="municipio" <?=$perfil==='municipio'?'selected':''?>>Gestor Municipal</option><option value="secretaria" <?=$perfil==='secretaria'?'selected':''?>>Secretaria</option><option value="stratelli" <?=$perfil==='stratelli'?'selected':''?>>Stratelli</option></select>
                        </div>
                        <div class="profile-control secretaria-profile-select" id="secretariaPerfilWrap" <?=$perfil==='secretaria'?'':'hidden'?>>
                            <label for="secretariaPerfilRapido"><i>▤</i>Secretaria de acesso</label>
                            <select id="secretariaPerfilRapido" name="secretaria">
                                <?php foreach($secretariasPerfilDisponiveis as $secretariaOpcao):?><option value="<?=$secretariaOpcao['id']?>" <?=((int)$secretariaOpcao['id']===$secretariaPerfilId)?'selected':''?>><?=h($secretariaOpcao['sigla']?($secretariaOpcao['sigla'].' — '.$secretariaOpcao['nome']):$secretariaOpcao['nome'])?></option><?php endforeach;?>
                            </select>
                        </div>
                        <button type="submit" class="profile-apply-btn"><span aria-hidden="true">↻</span><span>Aplicar perfil</span></button>
                    </form>
                </div>
            </div>
            <div class="top-right header-actions">
                <div class="header-action-buttons">
                    <button type="button" class="menu-toggle-btn" id="menuToggle" onclick="alternarMenu()" aria-controls="menuLateral" aria-expanded="true"><span aria-hidden="true">▣</span><span id="menuToggleText">Recolher menu</span></button>
                    <button type="button" class="print-btn" onclick="imprimirWorkflow()">🖨 Imprimir</button>
                </div>
                <div class="header-context">
                    <span class="header-context-chip" title="<?=h($municipioNome)?>">▥ Município <b><?=h($municipioNome)?></b></span>
                    <?php if($perfil==='secretaria'&&$secretariaPerfil):?><span class="header-context-chip secretaria" title="<?=h($secretariaPerfil['nome'])?>">▤ Secretaria <b><?=h($secretariaPerfil['sigla']?:$secretariaPerfil['nome'])?></b></span><?php endif;?>
                    <div class="notification-center">
                        <button type="button" class="header-icon-btn" id="notificationToggle" title="Abrir notificações" aria-label="Abrir notificações" aria-expanded="false" aria-controls="notificationPanel" onclick="alternarNotificacoes(event)">
                            <span aria-hidden="true">🔔</span>
                            <?php if($notificacaoContagemAtiva>0):?><span class="notification-badge"><?=$notificacaoContagemAtiva>99?'99+':$notificacaoContagemAtiva?></span><?php endif;?>
                        </button>
                        <section class="notification-panel" id="notificationPanel" aria-label="Centro de notificações" hidden>
                            <div class="notification-panel-head">
                                <div><h3>Notificações</h3><p><?=h($notificacaoRotuloPerfil)?> · atualizadas pelo status do processo</p></div>
                                <span class="notification-total"><?=$notificacaoTotal?></span>
                            </div>
                            <?php if($notificacoesExibidas):?>
                                <div class="notification-list">
                                    <?php foreach($notificacoesExibidas as $notificacao):$dataNotificacao=(string)($notificacao['data']??'');$tempoNotificacao=$dataNotificacao!==''?dataCurtaBr($dataNotificacao):'Agora';?>
                                        <a class="notification-item <?=h($notificacao['tipo'])?>" href="?view=workflow&fase=<?=(int)$notificacao['fase_id']?>&perfil=<?=h($perfil)?>&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>#documentos-fase">
                                            <span class="notification-item-icon" aria-hidden="true"><?=h($notificacao['icone'])?></span>
                                            <span class="notification-item-content"><strong><?=h($notificacao['titulo'])?></strong><span><?=h($notificacao['texto'])?></span></span>
                                            <small class="notification-item-time"><?=h($tempoNotificacao)?></small>
                                        </a>
                                    <?php endforeach;?>
                                </div>
                            <?php else:?>
                                <div class="notification-empty"><span aria-hidden="true">✓</span><b>Nenhum aviso no momento</b><span>Não há entregas, correções ou prazos exigindo atenção para este perfil.</span></div>
                            <?php endif;?>
                            <div class="notification-panel-footer"><span><?=$notificacoesRestantes>0?'+ '.$notificacoesRestantes.' aviso(s) no Workflow':'Situação calculada em tempo real'?></span><a href="?view=workflow&perfil=<?=h($perfil)?>&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>">Abrir Workflow</a></div>
                        </section>
                    </div>
                    <span class="header-user-indicator" title="Usuário conectado" aria-label="Usuário conectado"></span>
                </div>
            </div>
        </header>
        <section class="wrap">
            <?php if($flash): ?><div class="flash <?=h($flash[0])?>"><?=h($flash[1])?></div><?php endif; ?>

            <?php if($view==='dashboard'): ?>
                <div class="situational-hero">
                    <div><h1>Dashboard Situacional</h1><p><?=$perfil==='stratelli'?'Visão executiva do processo atual do município, da fase em andamento e das secretarias que ainda precisam agir.':($perfil==='secretaria'?'Visão restrita às fases e aos documentos sob responsabilidade da sua secretaria.':'Visão consolidada do processo municipal, reunindo todas as secretarias, documentos, entregas, correções e análises.')?></p></div>
                    <div class="situational-update"><?=$perfil==='stratelli'?'Situação calculada em tempo real pelo banco de dados':($perfil==='secretaria'?'Exibindo somente as responsabilidades de '.h($secretariaPerfil['sigla']?:$secretariaPerfil['nome']):'Gestão integrada de todas as secretarias do município')?></div>
                </div>
                <div class="schedule-start-control">
                    <div><strong>Marco inicial do cronograma</strong><small>Os prazos usam a duração cadastrada no Gantt. Cada fase começa no dia seguinte à conclusão da anterior.</small></div>
                    <?php if($perfil==='stratelli'):?>
                        <form method="post">
                            <input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="acao" value="salvar_inicio_cronograma">
                            <label>Início do processo<input type="date" name="data_inicio" required value="<?=h($dataInicioProcesso)?>"></label>
                            <button class="btn primary">Recalcular prazos</button>
                        </form>
                    <?php else:?><span class="deadline-status-pill scheduled">Processo iniciado em <?=h(dataCurtaBr($dataInicioProcesso))?></span><?php endif;?>
                </div>
                <div class="kpis situational-kpis">
                    <div class="kpi current"><small>FASE ATUAL</small><b><?=$faseSituacional?'Fase '.h($faseSituacional['ordem']):'—'?></b><span><?=$faseSituacional?h($faseSituacional['aba']):'Nenhuma fase ativa'?></span></div>
                    <div class="kpi kpi-deadline deadline-<?=h($prazoDashboard['status']??'blocked')?>"><small>SITUAÇÃO DO PRAZO</small><b><?=h($prazoDashboard['rotulo']??'—')?></b><span><?=h($prazoDashboard['texto']??'Cronograma não disponível')?></span></div>
                    <div class="kpi success"><small><?=$perfil==='stratelli'?'PROGRESSO GERAL':($perfil==='secretaria'?'PROGRESSO DA SECRETARIA':'PROGRESSO MUNICIPAL')?></small><b><?=$progresso?>%</b><span><?=$totalAprovados?> de <?=$totalDocs?> documentos aprovados</span></div>
                    <div class="kpi alert"><small><?=$perfil==='secretaria'?'SECRETARIA ATIVA':'SECRETARIAS PENDENTES'?></small><b><?=$perfil==='secretaria'?h($secretariaPerfil['sigla']?:'—'):count($dashboardSecretariasPendentes)?></b><span><?=$perfil==='secretaria'?h($secretariaPerfil['nome']):($perfil==='stratelli'?'Precisam entregar ou corrigir':'Ainda possuem ações necessárias')?></span></div>
                    <div class="kpi alert"><small>DOCUMENTOS PENDENTES</small><b><?=$dashboardTotalPendencias?></b><span>Na fase atual</span></div>
                    <div class="kpi warning"><small>AGUARDANDO ANÁLISE</small><b><?=$dashboardFaseAguardando?></b><span><?=$perfil==='stratelli'?'Recebidos na fase atual':'Enviados para conferência'?></span></div>
                </div>

                <?php if($faseSituacional):?>
                    <section class="current-process-card">
                        <div class="current-process-main">
                            <div class="current-phase-marker"><small><?=$perfil==='stratelli'?'Estamos atualmente na':($perfil==='secretaria'?'Sua secretaria atua na':'O processo municipal está na')?></small><b><?=h($faseSituacional['ordem'])?></b><span>FASE <?=h($faseSituacional['ordem'])?><br><?=h($faseSituacional['aba'])?></span></div>
                            <div class="current-phase-info">
                                <div class="current-phase-top">
                                    <div><h2><?=h($faseSituacional['titulo'])?></h2><p><?=h($faseSituacional['descricao'])?></p></div>
                                    <span class="current-phase-status <?=h($dashboardFaseStatusClass)?>"><?=h($dashboardFaseStatusLabel)?></span>
                                </div>
                                <div class="current-phase-progress">
                                    <div class="current-phase-progress-head"><span>Progresso documental da fase</span><strong><?=$dashboardFaseProgresso?>%</strong></div>
                                    <div class="current-phase-progress-track"><span style="width:<?=$dashboardFaseProgresso?>%"></span></div>
                                </div>
                                <div class="current-phase-stats">
                                    <div class="current-phase-stat"><b><?=$dashboardFaseAprovados?></b><small>Aprovados</small></div>
                                    <div class="current-phase-stat"><b><?=$dashboardFaseAguardando?></b><small>Em análise</small></div>
                                    <div class="current-phase-stat"><b><?=$dashboardFaseCorrecoes?></b><small>Correções</small></div>
                                    <div class="current-phase-stat"><b><?=$dashboardFaseNaoEntregues?></b><small>Não entregues</small></div>
                                </div>
                            </div>
                            <div class="current-phase-action">
                                <div>
                                    <strong>Prazo operacional da fase</strong>
                                    <span class="deadline-status-pill <?=h($prazoDashboard['status']??'blocked')?>"><?=h($prazoDashboard['rotulo']??'—')?></span>
                                    <div class="current-phase-deadline-dates"><div><small>Início</small><b><?=h(dataCurtaBr($prazoDashboard['inicio']??null))?></b></div><div><small>Data limite</small><b><?=h(dataCurtaBr($prazoDashboard['fim']??null))?></b></div></div>
                                    <p class="deadline-note"><?=h($prazoDashboard['texto']??'Cronograma indisponível')?><?php if(!empty($prazoDashboard['provisorio'])):?> · datas provisórias<?php endif;?></p>
                                    <div class="deadline-time-head"><span>Consumo do prazo</span><strong><?=h($prazoDashboard['percentual_tempo']??0)?>%</strong></div>
                                    <div class="deadline-time-track <?=h($prazoDashboard['status']??'blocked')?>"><span style="width:<?=h($prazoDashboard['percentual_tempo']??0)?>%"></span></div>
                                </div>
                                <a class="btn primary" href="?view=workflow&fase=<?=$faseSituacional['id']?>&perfil=<?=h($perfil)?>&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>#controle-prazo">Abrir detalhes da fase</a>
                            </div>
                        </div>
                    </section>
                <?php else:?><div class="empty-state">Nenhuma fase ativa foi cadastrada para o processo.</div><?php endif;?>

                <section class="contract-preview dashboard-process-preview <?=$perfil==='stratelli'?'stratelli':''?>" aria-label="Situação de todas as fases do processo">
                    <div class="contract-preview-head">
                        <div>
                            <h2>Situação de todo o processo</h2>
                            <p>Visão consolidada das fases, com datas operacionais, situação documental, prazo e indicadores do escopo deste perfil.</p>
                        </div>
                        <span class="contract-preview-law"><?=$dashboardFasesConcluidas?> de <?=count($fasesSituacionais)?> fases concluídas</span>
                    </div>
                    <?php if($fasesSituacionais):?>
                        <div class="contract-preview-scroll">
                            <div class="contract-preview-flow" style="--phase-count:<?=count($fasesSituacionais)?>">
                                <?php
                                $iconesPainelSituacional=['▣','▤','✎','⌕','☷','⚖','◎','◇','✓','§'];
                                foreach($fasesSituacionais as $fasePainel):
                                    $statusPainel=(string)($fasePainel['status_situacional']??'pending');
                                    $prazoPainel=$cronogramaPorFase[(int)$fasePainel['id']]??null;
                                    $statusPrazoPainel=(string)($prazoPainel['status']??'blocked');
                                    $faseAtualPainel=$faseSituacional&&(int)$faseSituacional['id']===(int)$fasePainel['id'];
                                    if($statusPainel==='done')$classePainelPrevia='completed';
                                    elseif($faseAtualPainel)$classePainelPrevia='current';
                                    elseif(in_array($statusPrazoPainel,['overdue','completed-late'],true))$classePainelPrevia='overdue';
                                    elseif($statusPainel==='correction'||$statusPrazoPainel==='attention')$classePainelPrevia='attention';
                                    else $classePainelPrevia='future';
                                    $rotuloPainel=match($statusPainel){'done'=>'Concluída','correction'=>'Correção solicitada','run'=>'Em andamento',default=>'Pendente'};
                                    $rotuloDataPainel=(!empty($prazoPainel['inicio'])&&!empty($prazoPainel['fim']))
                                        ?dataCurtaBr($prazoPainel['inicio']).' — '.dataCurtaBr($prazoPainel['fim'])
                                        :'Datas em definição';
                                    $requisitosPainelFase=array_values(array_filter($dashboardRequisitosBase,fn($reqPainel)=>(int)$reqPainel['fase_id']===(int)$fasePainel['id']));
                                    $totalPainelFase=count($requisitosPainelFase);
                                    $aprovadosPainelFase=$analisePainelFase=$correcoesPainelFase=$pendentesPainelFase=0;
                                    foreach($requisitosPainelFase as $reqPainelFase){
                                        $docPainelFase=$ultimosDocs[(int)$reqPainelFase['id']]??null;
                                        if(!$docPainelFase){$pendentesPainelFase++;continue;}
                                        if(($docPainelFase['status']??'')==='aprovado')$aprovadosPainelFase++;
                                        elseif(($docPainelFase['status']??'')==='correcao')$correcoesPainelFase++;
                                        else $analisePainelFase++;
                                    }
                                    $progressoPainelFase=$totalPainelFase?round(($aprovadosPainelFase/$totalPainelFase)*100):0;
                                    $iconePainel=$iconesPainelSituacional[((int)$fasePainel['ordem'])%count($iconesPainelSituacional)];
                                    $resumoIndicadoresPainel=$totalPainelFase
                                        ?'✓ '.$aprovadosPainelFase.' aprovados; '.$analisePainelFase.' em análise; '.$correcoesPainelFase.' em correção; '.$pendentesPainelFase.' pendentes.'
                                        :'Sem documentos no escopo deste perfil';
                                ?>
                                    <a class="contract-preview-stage <?=h($classePainelPrevia)?>" href="?view=workflow&fase=<?=$fasePainel['id']?>&perfil=<?=h($perfil)?>&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>" aria-label="Abrir Fase <?=h($fasePainel['ordem'])?> — <?=h($fasePainel['titulo'])?>" <?=$faseAtualPainel?'aria-current="step"':''?>>
                                        <div class="contract-preview-number"><?=h($fasePainel['ordem'])?></div>
                                        <div class="contract-preview-card">
                                            <div class="contract-preview-date"><?=h($rotuloDataPainel)?></div>
                                            <div class="contract-preview-body">
                                                <div class="contract-preview-icon" aria-hidden="true"><?=h($iconePainel)?></div>
                                                <strong><?=h($fasePainel['titulo'])?></strong>
                                                <small><?=h($rotuloPainel)?> · <?=h($prazoPainel['rotulo']??'Aguardando prazo')?><?php if(!empty($prazoPainel['provisorio'])):?> · prévia<?php endif;?></small>
                                            </div>
                                            <div class="contract-preview-duration"><?=$totalPainelFase?$progressoPainelFase.'% aprovado':'Sem documentos'?></div>
                                        </div>
                                        <ul class="contract-preview-milestone dashboard-phase-metrics-v47" aria-label="<?=h($resumoIndicadoresPainel)?>" data-layout="metricas-v47">
                                            <?php if($totalPainelFase):?>
                                                <li class="dashboard-phase-metric-v47">
                                                    <span class="dashboard-phase-metric-icon-v47" aria-hidden="true">✓</span>
                                                    <span><strong><?=$aprovadosPainelFase?></strong> <?=$aprovadosPainelFase===1?'documento aprovado':'documentos aprovados'?></span>
                                                </li>
                                                <li class="dashboard-phase-metric-v47">
                                                    <span class="dashboard-phase-metric-icon-v47" aria-hidden="true">…</span>
                                                    <span><strong><?=$analisePainelFase?></strong> <?=$analisePainelFase===1?'documento aguardando validação':'documentos aguardando validação'?></span>
                                                </li>
                                                <li class="dashboard-phase-metric-v47">
                                                    <span class="dashboard-phase-metric-icon-v47" aria-hidden="true">!</span>
                                                    <span><strong><?=$correcoesPainelFase?></strong> <?=$correcoesPainelFase===1?'documento em correção':'documentos em correção'?></span>
                                                </li>
                                                <li class="dashboard-phase-metric-v47">
                                                    <span class="dashboard-phase-metric-icon-v47" aria-hidden="true">×</span>
                                                    <span><strong><?=$pendentesPainelFase?></strong> <?=$pendentesPainelFase===1?'documento pendente':'documentos pendentes'?></span>
                                                </li>
                                            <?php else:?>
                                                <li class="dashboard-phase-metric-empty-v47">Sem documentos no escopo deste perfil</li>
                                            <?php endif;?>
                                        </ul>
                                    </a>
                                <?php endforeach;?>
                            </div>
                        </div>
                        <div class="contract-preview-footer dashboard-process-footer">
                            <div class="contract-preview-legal dashboard-process-summary">
                                <div class="contract-preview-legal-icon" aria-hidden="true">◎</div>
                                <div class="dashboard-process-summary-content">
                                    <strong><?=h($dashboardResumoCard['titulo'])?></strong>
                                    <div class="dashboard-process-summary-metrics">
                                        <div class="dashboard-process-summary-metric"><b><?=h($dashboardResumoCard['progresso'])?></b><small>Progresso</small></div>
                                        <div class="dashboard-process-summary-metric"><b><?=h($dashboardResumoCard['pendencias'])?></b><small>Pendências</small></div>
                                        <div class="dashboard-process-summary-metric"><b><?=h($dashboardResumoCard['analise'])?></b><small>Em análise</small></div>
                                        <div class="dashboard-process-summary-metric"><b><?=h($dashboardResumoCard['fases'])?></b><small>Fases concluídas</small></div>
                                    </div>
                                </div>
                            </div>
                            <div class="contract-preview-note">
                                <div><strong>Leitura da situação</strong><span>Cada etapa apresenta as datas operacionais, o status documental, a situação do prazo e os quantitativos correspondentes ao perfil atualmente selecionado.</span></div>
                            </div>
                        </div>
                    <?php else:?><div class="empty-state">Nenhuma fase cadastrada.</div><?php endif;?>
                </section>

                <section class="situational-pending-section">
                    <div class="situational-pending-head">
                        <div><h2><?=$perfil==='stratelli'?'Secretarias que precisam entregar documentos':($perfil==='secretaria'?'Ações necessárias da sua secretaria':'Ações necessárias por secretaria')?></h2><p><?=$perfil==='stratelli'?'São exibidas somente as pendências da fase atual. Documentos de fases futuras não entram nesta cobrança.':($perfil==='secretaria'?'Aqui aparecem somente os documentos atribuídos à sua secretaria nesta fase.':'Veja quais secretarias ainda precisam enviar documentos ou atender às correções solicitadas nesta fase.')?></p></div>
                        <?php if($dashboardTotalPendencias>0):?><span class="situational-pending-total">× <?=$dashboardTotalPendencias?> documento(s) exigindo ação</span><?php endif;?>
                    </div>
                    <?php if($dashboardSecretariasPendentes):?>
                        <div class="situational-secretary-grid">
                            <?php foreach($dashboardSecretariasPendentes as $secretariaDashboard):?>
                                <article class="situational-secretary-card <?=h($secretariaDashboard['status_visual'])?>">
                                    <div class="situational-secretary-head">
                                        <div class="situational-secretary-identification"><div class="situational-secretary-avatar"><?=h($secretariaDashboard['sigla']?:strtoupper(substr($secretariaDashboard['nome'],0,3)))?></div><div><strong><?=h($secretariaDashboard['nome'])?></strong><small><?=$secretariaDashboard['nao_entregues']?> não entregue(s) · <?=$secretariaDashboard['correcoes']?> em correção</small></div></div>
                                        <div class="situational-secretary-count" title="Total de documentos que exigem ação"><?=$secretariaDashboard['total_pendencias']?></div>
                                    </div>
                                    <div class="situational-doc-list">
                                        <?php foreach($secretariaDashboard['documentos'] as $documentoDashboard):?>
                                            <div class="situational-doc-item">
                                                <div><strong><?=h($documentoDashboard['nome'])?></strong><small><?php if($documentoDashboard['departamento']):?><?=h($documentoDashboard['departamento'])?> · <?php endif;?><?=h($documentoDashboard['tipo'])?></small></div>
                                                <span class="situational-doc-status <?=h($documentoDashboard['status_visual'])?>"><?=h($documentoDashboard['rotulo'])?></span>
                                                <?php if($documentoDashboard['observacao']):?><div class="situational-doc-observation"><b>Correção solicitada:</b> <?=h($documentoDashboard['observacao'])?></div><?php endif;?>
                                            </div>
                                        <?php endforeach;?>
                                    </div>
                                </article>
                            <?php endforeach;?>
                        </div>
                    <?php else:?><div class="situational-empty"><b><?=$perfil==='secretaria'?'Sua secretaria está em dia na fase atual.':'Todas as secretarias estão em dia na fase atual.'?></b><span><?=$perfil==='stratelli'?'Não há documentos sem entrega nem arquivos aguardando reenvio por correção.':'No momento, não há documentos pendentes nem correções que precisem de novo envio.'?></span></div><?php endif;?>
                </section>

                <?php if($fasesVisiveis):?>
                <section class="gantt-panel" id="gantt">
                    <div class="gantt-head"><div><h2>Gráfico Gantt — Cronograma dinâmico</h2><p>Os blocos mantêm os dias planejados originalmente; as datas operacionais são recalculadas pela conclusão da fase anterior.</p></div><div class="gantt-head-chips"><span class="gantt-chip"><?=count($fasesVisiveis)?> fase(s)</span><span class="gantt-chip">Início: <?=h(dataCurtaBr($dataInicioProcesso))?></span><span class="gantt-chip">Hoje: D<?=$diaAtualProjeto?> · <?=h(dataCurtaBr($hojeCronograma))?></span></div></div>
                    <div class="gantt-body"><div class="gantt-scale"><div></div><div class="gantt-days"><?php for($i=0;$i<10;$i++):$dia=1+(int)round(($maxDiaCronograma-1)*$i/9);?><span>D<?=$dia?></span><?php endfor;?></div></div>
                    <?php foreach($fasesVisiveis as $gf):$prazoGantt=$cronogramaPorFase[(int)$gf['id']]??null;$statusPrazoGantt=(string)($prazoGantt['status']??'blocked');$inicioBarraDia=!empty($prazoGantt['inicio'])?max(1,diferencaDias($dataInicioProcesso,$prazoGantt['inicio'])+1):(int)$gf['dia_inicio'];$fimBarraDia=!empty($prazoGantt['fim'])?max($inicioBarraDia,diferencaDias($dataInicioProcesso,$prazoGantt['fim'])+1):(int)$gf['dia_fim'];$left=max(0,min(100,(($inicioBarraDia-1)/max(1,$maxDiaCronograma))*100));$width=max(1.5,(($fimBarraDia-$inicioBarraDia+1)/max(1,$maxDiaCronograma))*100);$width=min($width,max(0,100-$left));$markerConclusaoClass=in_array($statusPrazoGantt,['completed-on-time','completed-late'],true)?($statusPrazoGantt==='completed-late'?'late':'done'):'';$markerConclusaoPos=max(0,min(99.6,$left+$width));$markerConclusaoEdge=$markerConclusaoPos>=96?' edge-end':'';$todayEdge=$percentualHojeGantt>=96?' edge-end':'';?>
                        <div class="gantt-row">
                            <div class="gantt-label"><b>Fase <?=h($gf['ordem'])?> — <?=h($gf['aba'])?></b><?=h($gf['titulo'])?><br><span>Planejado: Dia <?=h($gf['dia_inicio'])?> ao <?=h($gf['dia_fim'])?></span><span class="gantt-operational-date">Operacional: <?=h(dataCurtaBr($prazoGantt['inicio']??null))?> a <?=h(dataCurtaBr($prazoGantt['fim']??null))?></span><span class="gantt-operational-status <?=h($statusPrazoGantt)?>"><?=h($prazoGantt['rotulo']??'Aguardando')?></span></div>
                            <div class="gantt-line" title="<?=h($gf['titulo'].' · '.($prazoGantt['texto']??''))?>"><?php if($markerConclusaoClass):?><i class="gantt-completed <?=$markerConclusaoClass?><?=$markerConclusaoEdge?>" style="left:<?=number_format($markerConclusaoPos,4,'.','')?>%"></i><?php elseif($diaAtualProjeto<=$maxDiaCronograma):?><i class="gantt-today<?=$todayEdge?>" style="left:<?=number_format(min(99.6,$percentualHojeGantt),4,'.','')?>%"></i><?php endif;?><div class="gantt-bar deadline-<?=h($statusPrazoGantt)?>" style="left:<?=number_format($left,4,'.','')?>%;width:<?=number_format($width,4,'.','')?>%"><span class="gantt-tag"><?=max(1,$fimBarraDia-$inicioBarraDia+1)?> dia(s)</span></div></div>
                        </div>
                    <?php endforeach;?>
                    <div class="gantt-legend"><span><i class="dot done"></i>Concluída no prazo</span><span><i class="dot run"></i>Em andamento e no prazo</span><span><i class="dot correction"></i>Atenção ou atraso</span><span><i class="dot pending"></i>Aguardando fase anterior</span></div></div>
                </section>
                <?php endif;?>


            <?php elseif($view==='municipios' && $perfil==='stratelli'): ?>
                <div class="module-page">
                    <div class="module-hero">
                        <div><h1>Municípios</h1><p>Visão do portfólio de clientes da Stratelli, com situação de implantação, andamento do processo e indicadores principais.</p></div>
                        <span class="module-context">▥ Portfólio Stratelli</span>
                    </div>
                    <div class="module-kpis-grid">
                        <div class="module-kpi"><small>CLIENTES CADASTRADOS</small><b><?=count($municipiosClientes)?></b><span>Municípios no portfólio</span></div>
                        <div class="module-kpi success"><small>COM DADOS ATIVOS</small><b>1</b><span>Maringá disponível no MVP</span></div>
                        <div class="module-kpi neutral"><small>EM PREPARAÇÃO</small><b>2</b><span>Aguardando implantação</span></div>
                        <div class="module-kpi warning"><small>FASE ATUAL</small><b><?=$faseSituacional?'Fase '.h($faseSituacional['ordem']):'—'?></b><span><?=$faseSituacional?h($faseSituacional['aba']):'Sem processo ativo'?></span></div>
                        <div class="module-kpi danger"><small>PENDÊNCIAS ATUAIS</small><b><?=$dashboardTotalPendencias?></b><span>Documentos exigindo ação</span></div>
                    </div>
                    <div class="municipality-grid">
                        <?php foreach($municipiosClientes as $clienteMunicipio):?>
                            <article class="municipality-card <?=h($clienteMunicipio['classe'])?>">
                                <div class="municipality-card-head">
                                    <div><h2><?=h($clienteMunicipio['nome'])?> — <?=h($clienteMunicipio['uf'])?></h2><p>ID do cliente: <?=h(strtoupper($clienteMunicipio['id']))?></p></div>
                                    <span class="municipality-status"><?=h($clienteMunicipio['status'])?></span>
                                </div>
                                <div class="municipality-card-body">
                                    <p><?=h($clienteMunicipio['descricao'])?></p>
                                    <div class="municipality-metrics">
                                        <div class="municipality-metric"><b><?=$clienteMunicipio['progresso']?>%</b><small>Progresso</small></div>
                                        <div class="municipality-metric"><b><?=$clienteMunicipio['documentos']?></b><small>Documentos</small></div>
                                        <div class="municipality-metric"><b><?=$clienteMunicipio['pendencias']?></b><small>Pendências</small></div>
                                    </div>
                                    <div class="municipality-phase"><?=h($clienteMunicipio['fase'])?></div>
                                </div>
                                <div class="municipality-card-foot">
                                    <?php if($clienteMunicipio['tem_dados']):?>
                                        <a class="btn primary" href="?view=dashboard&perfil=stratelli&municipio=maringa">Abrir município</a>
                                        <a class="mini-link" href="?view=workflow&perfil=stratelli&municipio=maringa">Workflow</a>
                                    <?php else:?>
                                        <span class="municipality-disabled">Conteúdo será liberado após a implantação.</span>
                                    <?php endif;?>
                                </div>
                            </article>
                        <?php endforeach;?>
                    </div>
                </div>

            <?php elseif($view==='documentos' && in_array($perfil,['stratelli','municipio'],true)): ?>
                <div class="module-page">
                    <div class="module-hero">
                        <div><h1>Documentos</h1><p>Catálogo completo dos documentos do processo, separados por fase e com modelo, secretaria responsável, último envio e situação atual.</p></div>
                        <span class="module-context">▤ <?=$perfil==='stratelli'?'Visão completa Stratelli':'Visão do Gestor Municipal'?></span>
                    </div>
                    <div class="module-kpis-grid">
                        <div class="module-kpi"><small>TOTAL DE DOCUMENTOS</small><b><?=count($documentosPagina)?></b><span>No escopo deste perfil</span></div>
                        <div class="module-kpi success"><small>APROVADOS</small><b><?=$documentosPaginaAprovados?></b><span>Validação concluída</span></div>
                        <div class="module-kpi"><small>AGUARDANDO VALIDAÇÃO</small><b><?=$documentosPaginaAguardando?></b><span>Em análise pela Stratelli</span></div>
                        <div class="module-kpi warning"><small>EM CORREÇÃO</small><b><?=$documentosPaginaCorrecao?></b><span>Precisam de reenvio</span></div>
                        <div class="module-kpi danger"><small>NÃO ENTREGUES</small><b><?=$documentosPaginaPendentes?></b><span>Aguardando envio</span></div>
                    </div>
                    <div class="scope-note"><?=$perfil==='stratelli'?'A Stratelli visualiza documentos internos e municipais, com acesso às fases e aos arquivos disponíveis.':'O Gestor Municipal visualiza todos os documentos atribuídos às secretarias do município. Documentos internos da Stratelli não aparecem nesta área.'?></div>
                    <div class="phase-document-list">
                        <?php if(!$documentosPorFasePagina):?><div class="empty-state">Nenhum documento disponível neste escopo.</div><?php endif;?>
                        <?php foreach($documentosPorFasePagina as $grupoFaseDocumento):?>
                            <section class="phase-document-section">
                                <div class="phase-document-head">
                                    <div class="phase-document-title"><span class="phase-document-number"><?=h($grupoFaseDocumento['ordem'])?></span><div><h2>Fase <?=h($grupoFaseDocumento['ordem'])?> — <?=h($grupoFaseDocumento['aba'])?></h2><p><?=h($grupoFaseDocumento['titulo'])?></p></div></div>
                                    <span class="phase-document-count"><?=count($grupoFaseDocumento['itens'])?> documento(s)</span>
                                </div>
                                <table class="document-catalog-table">
                                    <thead><tr><th>Documento</th><th>Secretaria</th><th>Tipo</th><th>Modelo</th><th>Status</th><th>Último arquivo</th><th>Ações</th></tr></thead>
                                    <tbody>
                                    <?php foreach($grupoFaseDocumento['itens'] as $itemDocumentoPagina):$reqDocPagina=$itemDocumentoPagina['requisito'];$docAtualPagina=$itemDocumentoPagina['documento'];?>
                                        <tr>
                                            <td><div class="document-catalog-name"><b><?=h($reqDocPagina['nome'])?></b><small><?=h($reqDocPagina['descricao']?:'Sem orientação complementar.')?></small></div></td>
                                            <td><b><?=h($reqDocPagina['secretaria_sigla']?:$reqDocPagina['secretaria_nome'])?></b><br><small><?=h($reqDocPagina['secretaria_nome'])?></small></td>
                                            <td><?=h($reqDocPagina['tipo_nome'])?></td>
                                            <td><span class="document-model-state <?=$itemDocumentoPagina['tem_modelo']?'available':'missing'?>"><?=$itemDocumentoPagina['tem_modelo']?'✓ Modelo disponível':'Modelo não enviado'?></span></td>
                                            <td><span class="module-status <?=h($itemDocumentoPagina['classe'])?>"><?=h($itemDocumentoPagina['rotulo'])?></span></td>
                                            <td class="document-last-file"><?php if($docAtualPagina):?><b><?=h($docAtualPagina['arquivo_original'])?></b><br><small>Versão <?=h($docAtualPagina['versao'])?> · <?=h(dataBr($docAtualPagina['enviado_em']))?></small><?php else:?><span>Sem arquivo enviado</span><?php endif;?></td>
                                            <td><div class="document-actions">
                                                <a class="mini-link primary" href="?view=workflow&fase=<?=$grupoFaseDocumento['fase_id']?>&perfil=<?=h($perfil)?>&municipio=<?=h($municipioId)?>">Abrir fase</a>
                                                <?php if($itemDocumentoPagina['tem_modelo']):?><a class="mini-link" href="?acao=download_modelo&requisito=<?=$reqDocPagina['id']?>&perfil=<?=h($perfil)?>&municipio=<?=h($municipioId)?>">Modelo</a><?php endif;?>
                                                <?php if($docAtualPagina):?><a class="mini-link" href="?acao=download_documento&id=<?=$docAtualPagina['id']?>&perfil=<?=h($perfil)?>&municipio=<?=h($municipioId)?>">Arquivo</a><?php endif;?>
                                            </div></td>
                                        </tr>
                                    <?php endforeach;?>
                                    </tbody>
                                </table>
                            </section>
                        <?php endforeach;?>
                    </div>
                </div>

            <?php elseif($view==='relatorios'): ?>
                <div class="module-page">
                    <div class="module-hero">
                        <div><h1><?=$perfil==='secretaria'?'Meus Relatórios':'Relatórios'?></h1><p><?=$perfil==='stratelli'?'Indicadores executivos, documentais, operacionais e de auditoria para acompanhamento do município.':($perfil==='municipio'?'Relatórios gerenciais do processo municipal e do desempenho das secretarias.':'Indicadores restritos às entregas, correções, validações e prazos da sua secretaria.')?></p></div>
                        <span class="module-context">▥ Dados atualizados em tempo real</span>
                    </div>
                    <div class="module-kpis-grid">
                        <div class="module-kpi success"><small>PROGRESSO</small><b><?=$progresso?>%</b><span><?=$totalAprovados?> de <?=$totalDocs?> aprovados</span></div>
                        <div class="module-kpi"><small>AGUARDANDO VALIDAÇÃO</small><b><?=$aguardando?></b><span>Arquivos em análise</span></div>
                        <div class="module-kpi warning"><small>CORREÇÕES</small><b><?=$totalCorrecoes?></b><span>Reenvios solicitados</span></div>
                        <div class="module-kpi danger"><small>PENDENTES</small><b><?=max(0,$totalDocs-$totalEnviados)?></b><span>Sem envio</span></div>
                        <div class="module-kpi neutral"><small>FASES CONCLUÍDAS</small><b><?=$dashboardFasesConcluidas?>/<?=count($fasesSituacionais)?></b><span>Andamento global</span></div>
                    </div>
                    <div class="report-catalog">
                        <?php if($perfil==='stratelli'):?>
                            <article class="report-card"><span class="report-card-icon">◎</span><h3>Relatório executivo municipal</h3><p>Progresso global, fase atual, documentos e principais riscos do processo.</p><strong><?=$progresso?>% de conformidade documental</strong></article>
                            <article class="report-card"><span class="report-card-icon">▤</span><h3>Pendências por secretaria</h3><p>Secretarias com documentos não entregues ou devolvidos para correção.</p><strong><?=count($dashboardSecretariasPendentes)?> secretaria(s) exigindo ação</strong></article>
                            <article class="report-card"><span class="report-card-icon">⌛</span><h3>Controle de prazos e SLA</h3><p>Fases no prazo, em atenção, atrasadas e datas operacionais recalculadas.</p><strong><?=h($prazoDashboard['rotulo']??'Cronograma indisponível')?></strong></article>
                            <article class="report-card"><span class="report-card-icon">✓</span><h3>Produtividade de validação</h3><p>Arquivos aprovados, aguardando análise e correções solicitadas pela Stratelli.</p><strong><?=$aguardando?> arquivo(s) aguardando análise</strong></article>
                            <article class="report-card"><span class="report-card-icon">◷</span><h3>Auditoria e rastreabilidade</h3><p>Movimentações, responsáveis, versões, validações e substituições documentais.</p><strong><?=$historicoResumo['total']?> registro(s) disponíveis</strong></article>
                            <article class="report-card"><span class="report-card-icon">▥</span><h3>Desempenho por fase</h3><p>Comparativo de progresso, volume documental e gargalos por etapa.</p><strong><?=count($relatorioFases)?> fase(s) analisadas</strong></article>
                        <?php elseif($perfil==='municipio'):?>
                            <article class="report-card"><span class="report-card-icon">◎</span><h3>Resumo executivo municipal</h3><p>Visão consolidada do andamento da contratação e dos documentos municipais.</p><strong><?=$progresso?>% de progresso</strong></article>
                            <article class="report-card"><span class="report-card-icon">▤</span><h3>Entregas por secretaria</h3><p>Comparativo de documentos aprovados, pendentes, em análise e correção.</p><strong><?=count($relatorioSecretarias)?> secretaria(s) monitoradas</strong></article>
                            <article class="report-card"><span class="report-card-icon">⌛</span><h3>Relatório de prazo</h3><p>Data-limite da fase atual e projeções das próximas etapas.</p><strong><?=h($prazoDashboard['texto']??'Cronograma indisponível')?></strong></article>
                            <article class="report-card"><span class="report-card-icon">!</span><h3>Correções solicitadas</h3><p>Documentos devolvidos pela Stratelli e que ainda precisam de reenvio.</p><strong><?=$totalCorrecoes?> correção(ões)</strong></article>
                            <article class="report-card"><span class="report-card-icon">…</span><h3>Aguardando validação</h3><p>Arquivos enviados pelas secretarias que aguardam conferência.</p><strong><?=$aguardando?> arquivo(s)</strong></article>
                            <article class="report-card"><span class="report-card-icon">◷</span><h3>Rastreabilidade municipal</h3><p>Histórico das ações realizadas pelas secretarias e pela gestão.</p><strong><?=$historicoResumo['total']?> movimentação(ões)</strong></article>
                        <?php else:?>
                            <article class="report-card"><span class="report-card-icon">▤</span><h3>Minhas entregas</h3><p>Resumo de todos os documentos atribuídos à sua secretaria.</p><strong><?=$totalEnviados?> de <?=$totalDocs?> enviados</strong></article>
                            <article class="report-card"><span class="report-card-icon">✓</span><h3>Documentos aprovados</h3><p>Entregas já validadas definitivamente pela Stratelli.</p><strong><?=$totalAprovados?> documento(s)</strong></article>
                            <article class="report-card"><span class="report-card-icon">…</span><h3>Aguardando validação</h3><p>Arquivos enviados e ainda não conferidos pela Stratelli.</p><strong><?=$aguardando?> arquivo(s)</strong></article>
                            <article class="report-card"><span class="report-card-icon">!</span><h3>Correções da secretaria</h3><p>Documentos que precisam ser corrigidos e reenviados.</p><strong><?=$totalCorrecoes?> correção(ões)</strong></article>
                            <article class="report-card"><span class="report-card-icon">⌛</span><h3>Prazo da fase atual</h3><p>Tempo disponível para concluir as responsabilidades da secretaria.</p><strong><?=h($prazoDashboard['texto']??'Cronograma indisponível')?></strong></article>
                            <article class="report-card"><span class="report-card-icon">◷</span><h3>Meu histórico</h3><p>Registro das entregas, substituições e retornos relacionados à secretaria.</p><strong><?=$historicoResumo['total']?> movimentação(ões)</strong></article>
                        <?php endif;?>
                    </div>
                    <section class="report-section">
                        <div class="report-section-head"><div><h2>Desempenho documental por fase</h2><p>Quantitativos calculados conforme o escopo do perfil selecionado.</p></div></div>
                        <table class="report-table"><thead><tr><th>Fase</th><th>Progresso</th><th>Aprovados</th><th>Em análise</th><th>Correções</th><th>Pendentes</th><th>Prazo</th></tr></thead><tbody>
                        <?php foreach($relatorioFases as $faseRelatorioItem):?>
                            <tr><td><b>Fase <?=h($faseRelatorioItem['fase']['ordem'])?></b><br><small><?=h($faseRelatorioItem['fase']['aba'])?></small></td><td><div class="progress-inline"><div class="progress-inline-track"><span style="width:<?=$faseRelatorioItem['progresso']?>%"></span></div><b><?=$faseRelatorioItem['progresso']?>%</b></div></td><td><?=$faseRelatorioItem['aprovados']?></td><td><?=$faseRelatorioItem['analise']?></td><td><?=$faseRelatorioItem['correcao']?></td><td><?=$faseRelatorioItem['pendentes']?></td><td><span class="module-status <?=h(match($faseRelatorioItem['prazo']['status']??'blocked'){'completed-on-time'=>'approved','completed-late'=>'correction','on-track'=>'waiting','attention'=>'correction','overdue'=>'pending',default=>'pending'})?>"><?=h($faseRelatorioItem['prazo']['rotulo']??'Aguardando')?></span></td></tr>
                        <?php endforeach;?>
                        </tbody></table>
                    </section>
                    <?php if($relatorioSecretarias):?>
                    <section class="report-section">
                        <div class="report-section-head"><div><h2><?=$perfil==='secretaria'?'Resumo da sua secretaria':'Desempenho por secretaria'?></h2><p>Conformidade documental e ações necessárias.</p></div></div>
                        <table class="report-table"><thead><tr><th>Secretaria</th><th>Progresso</th><th>Total</th><th>Aprovados</th><th>Em análise</th><th>Correções</th><th>Pendentes</th></tr></thead><tbody>
                        <?php foreach($relatorioSecretarias as $secRelatorioItem):?>
                            <tr><td><b><?=h($secRelatorioItem['sigla']?:$secRelatorioItem['nome'])?></b><br><small><?=h($secRelatorioItem['nome'])?></small></td><td><div class="progress-inline"><div class="progress-inline-track"><span style="width:<?=$secRelatorioItem['progresso']?>%"></span></div><b><?=$secRelatorioItem['progresso']?>%</b></div></td><td><?=$secRelatorioItem['total']?></td><td><?=$secRelatorioItem['aprovados']?></td><td><?=$secRelatorioItem['analise']?></td><td><?=$secRelatorioItem['correcao']?></td><td><?=$secRelatorioItem['pendentes']?></td></tr>
                        <?php endforeach;?>
                        </tbody></table>
                    </section>
                    <?php endif;?>
                </div>

            <?php elseif($view==='historico'): ?>
                <div class="module-page">
                    <div class="module-hero">
                        <div><h1><?=$perfil==='secretaria'?'Meu Histórico':'Histórico de Movimentações'?></h1><p><?=$perfil==='stratelli'?'Registro completo das movimentações realizadas pela Stratelli, pelo Gestor Municipal e pelas secretarias.':($perfil==='municipio'?'Movimentações documentais das secretarias vinculadas ao município.':'Movimentações exclusivamente relacionadas aos documentos da sua secretaria.')?></p></div>
                        <span class="module-context">◷ Trilha de auditoria</span>
                    </div>
                    <div class="module-kpis-grid">
                        <div class="module-kpi"><small>TOTAL DE REGISTROS</small><b><?=$historicoResumo['total']?></b><span>Após os filtros aplicados</span></div>
                        <div class="module-kpi"><small>ENVIOS E SUBSTITUIÇÕES</small><b><?=$historicoResumo['envios']?></b><span>Arquivos movimentados</span></div>
                        <div class="module-kpi success"><small>APROVAÇÕES</small><b><?=$historicoResumo['aprovacoes']?></b><span>Validações concluídas</span></div>
                        <div class="module-kpi warning"><small>CORREÇÕES</small><b><?=$historicoResumo['correcoes']?></b><span>Devoluções registradas</span></div>
                        <div class="module-kpi neutral"><small>MODELOS</small><b><?=$historicoResumo['modelos']?></b><span>Disponibilizações e trocas</span></div>
                    </div>
                    <div class="scope-note"><?=$perfil==='stratelli'?'Escopo completo: todas as movimentações do município, incluindo ações da Stratelli e das secretarias.':($perfil==='municipio'?'Escopo municipal: movimentações dos documentos atribuídos às secretarias.':'Escopo restrito: somente registros vinculados à secretaria selecionada.')?></div>
                    <form class="history-filter" method="get">
                        <input type="hidden" name="view" value="historico"><input type="hidden" name="perfil" value="<?=h($perfil)?>"><input type="hidden" name="municipio" value="<?=h($municipioId)?>"><?php if($perfil==='secretaria'):?><input type="hidden" name="secretaria" value="<?=$secretariaPerfilId?>"><?php endif;?>
                        <label>Fase<select name="historico_fase"><option value="0">Todas as fases</option><?php foreach($fasesVisiveis as $faseFiltroHist):?><option value="<?=$faseFiltroHist['id']?>" <?=$historicoFaseFiltro===(int)$faseFiltroHist['id']?'selected':''?>>Fase <?=h($faseFiltroHist['ordem'])?> — <?=h($faseFiltroHist['aba'])?></option><?php endforeach;?></select></label>
                        <label>Movimentação<select name="historico_tipo"><option value="">Todos os eventos</option><option value="envi" <?=$historicoTipoFiltro==='envi'?'selected':''?>>Envios</option><option value="substitu" <?=$historicoTipoFiltro==='substitu'?'selected':''?>>Substituições</option><option value="aprov" <?=$historicoTipoFiltro==='aprov'?'selected':''?>>Aprovações</option><option value="corre" <?=$historicoTipoFiltro==='corre'?'selected':''?>>Correções</option><option value="modelo" <?=$historicoTipoFiltro==='modelo'?'selected':''?>>Modelos</option></select></label>
                        <button class="btn primary" type="submit">Aplicar filtros</button>
                        <a class="mini-link" href="?view=historico&perfil=<?=h($perfil)?>&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>">Limpar</a>
                    </form>
                    <div class="history-table-wrap">
                        <?php if(!$historicoGeral):?><div class="history-empty">Nenhuma movimentação encontrada para os filtros selecionados.</div><?php else:?>
                        <table class="history-table"><thead><tr>
                            <?php foreach($historicoCabecalhosOrdenaveis as $chaveOrdenacaoHistorico=>$rotuloOrdenacaoHistorico):
                                $ordenacaoAtivaHistorico=$historicoOrdem===$chaveOrdenacaoHistorico;
                                $proximaDirecaoHistorico=$ordenacaoAtivaHistorico&&$historicoDirecao==='asc'?'desc':'asc';
                                $iconeOrdenacaoHistorico=$ordenacaoAtivaHistorico?($historicoDirecao==='asc'?'▲':'▼'):'↕';
                                $queryOrdenacaoHistorico=array_merge($historicoOrdenacaoBase,['historico_ordem'=>$chaveOrdenacaoHistorico,'historico_direcao'=>$proximaDirecaoHistorico,'historico_pagina'=>1]);
                            ?>
                                <th scope="col"><a class="history-sort-link <?=$ordenacaoAtivaHistorico?'active':''?>" href="?<?=h(http_build_query($queryOrdenacaoHistorico))?>" aria-label="Ordenar por <?=h($rotuloOrdenacaoHistorico)?> em ordem <?=$proximaDirecaoHistorico==='asc'?'crescente':'decrescente'?>" <?=$ordenacaoAtivaHistorico?'aria-sort="'.($historicoDirecao==='asc'?'ascending':'descending').'"':''?>><span><?=h($rotuloOrdenacaoHistorico)?></span><span class="history-sort-icon" aria-hidden="true"><?=$iconeOrdenacaoHistorico?></span></a></th>
                            <?php endforeach;?>
                            <th class="history-action-head" scope="col">Ação</th>
                        </tr></thead><tbody>
                        <?php foreach($historicoGeral as $histGeral):$eventoHistLower=strtolower((string)$histGeral['evento']);$classeEventoHist=str_contains($eventoHistLower,'aprov')?'approved':(str_contains($eventoHistLower,'corre')?'correction':(str_contains($eventoHistLower,'modelo')?'model':''));?>
                            <tr>
                                <td><span class="history-event <?=h($classeEventoHist)?>"><?=h($histGeral['evento'])?></span></td>
                                <td><?php if($histGeral['fase_ordem']!==null):?><b>Fase <?=h($histGeral['fase_ordem'])?></b><br><small><?=h($histGeral['fase_aba'])?></small><?php else:?>—<?php endif;?></td>
                                <td><div class="history-file"><b><?=h($histGeral['documento']?:'Documento não identificado')?></b><small><?=h($histGeral['arquivo_original']?:'Sem arquivo associado')?></small><?php if($histGeral['arquivo_anterior_original']):?><small>Substituiu: <?=h($histGeral['arquivo_anterior_original'])?></small><?php endif;?></div></td>
                                <td><?=h($histGeral['secretaria_sigla']?:($histGeral['secretaria_nome']?:'—'))?></td>
                                <td><div class="history-user"><b><?=h($histGeral['usuario']?:'—')?></b><small><?=h($histGeral['email'])?></small></div></td>
                                <td><?=h(strtoupper((string)$histGeral['perfil']))?></td>
                                <td><?=h(dataBr($histGeral['criado_em']))?></td>
                                <td><?=h($histGeral['motivo']?:'Sem observação registrada.')?></td>
                                <td><?php if($histGeral['arquivo_salvo']):?><a class="mini-link" href="?acao=download_historico&id=<?=$histGeral['id']?>&perfil=<?=h($perfil)?>&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>">Baixar</a><?php else:?>—<?php endif;?></td>
                            </tr>
                        <?php endforeach;?>
                        </tbody></table>
                        <?php endif;?>
                    </div>
                    <?php if($historicoTotalRegistros>0):?>
                        <nav class="history-pagination" aria-label="Paginação do histórico">
                            <div class="history-pagination-info">Exibindo <?=$historicoPrimeiroRegistro?> a <?=$historicoUltimoRegistro?> de <?=$historicoTotalRegistros?> registro(s) · Página <?=$historicoPagina?> de <?=$historicoTotalPaginas?></div>
                            <div class="history-pagination-nav">
                                <?php if($historicoPagina>1):?><a class="history-page-link" href="?<?=h(http_build_query($historicoQueryBase+['historico_pagina'=>$historicoPagina-1]))?>">‹ Anterior</a><?php else:?><span class="history-page-disabled">‹ Anterior</span><?php endif;?>
                                <?php $historicoPaginaAnteriorRenderizada=0;foreach($historicoPaginasVisiveis as $paginaHistoricoRender):?>
                                    <?php if($historicoPaginaAnteriorRenderizada>0&&$paginaHistoricoRender>$historicoPaginaAnteriorRenderizada+1):?><span class="history-page-ellipsis">…</span><?php endif;?>
                                    <?php if($paginaHistoricoRender===$historicoPagina):?><span class="history-page-current" aria-current="page"><?=$paginaHistoricoRender?></span><?php else:?><a class="history-page-link" href="?<?=h(http_build_query($historicoQueryBase+['historico_pagina'=>$paginaHistoricoRender]))?>"><?=$paginaHistoricoRender?></a><?php endif;?>
                                    <?php $historicoPaginaAnteriorRenderizada=$paginaHistoricoRender;endforeach;?>
                                <?php if($historicoPagina<$historicoTotalPaginas):?><a class="history-page-link" href="?<?=h(http_build_query($historicoQueryBase+['historico_pagina'=>$historicoPagina+1]))?>">Próxima ›</a><?php else:?><span class="history-page-disabled">Próxima ›</span><?php endif;?>
                            </div>
                        </nav>
                    <?php endif;?>
                </div>

            <?php elseif($view==='configuracoes' && $perfil==='stratelli'): ?>
                <div class="hero">
                    <div><h1>Configurações do Workflow</h1><p>Cadastros dinâmicos de fases, estruturas administrativas, tipos e documentos</p></div>
                    <div class="db-badge">Dados persistidos em SQLite</div>
                </div>
                <div class="kpis config-kpis">
                    <div class="kpi"><small>FASES CADASTRADAS</small><b><?=count($fasesTodas)?></b><span><?=count(array_filter($fasesTodas,fn($x)=>(int)$x['ativo']===1))?> ativas</span></div>
                    <div class="kpi"><small>SECRETARIAS</small><b><?=count($secretarias)?></b><span><?=count($departamentos)?> departamentos</span></div>
                    <div class="kpi"><small>TIPOS DE DOCUMENTO</small><b><?=count($tiposDocumento)?></b><span>Extensões configuráveis</span></div>
                    <div class="kpi"><small>REGRAS DOCUMENTAIS</small><b><?=count($requisitosTodos)?></b><span><?=count(array_filter($requisitosTodos,fn($x)=>(int)$x['ativo']===1))?> ativas</span></div>
                </div>
                <nav class="config-nav">
                    <a class="<?=$secao==='fases'?'active':''?>" href="?view=configuracoes&secao=fases&perfil=stratelli&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>">1. Fases</a>
                    <a class="<?=$secao==='secretarias'?'active':''?>" href="?view=configuracoes&secao=secretarias&perfil=stratelli&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>">2. Secretarias e Departamentos</a>
                    <a class="<?=$secao==='tipos'?'active':''?>" href="?view=configuracoes&secao=tipos&perfil=stratelli&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>">3. Tipos de Documento</a>
                    <a class="<?=$secao==='documentos'?'active':''?>" href="?view=configuracoes&secao=documentos&perfil=stratelli&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>">4. Documentos e Modelos</a>
                </nav>

                <?php if($secao==='fases'): ?>
                <div class="config-grid">
                    <section class="config-card">
                        <h2><?=$editarFase?'Editar fase':'Cadastrar nova fase'?></h2>
                        <p>A ordem define a sequência no workflow e no Gantt. Uma fase pode ser exclusiva da Stratelli.</p>
                        <form method="post">
                            <input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="acao" value="salvar_fase"><input type="hidden" name="secao" value="fases"><input type="hidden" name="id" value="<?=h($editarFase['id']??0)?>">
                            <div class="form-grid">
                                <div class="field"><label>Ordem da fase</label><input type="number" name="ordem" min="0" required value="<?=h($editarFase['ordem']??count($fasesTodas))?>"></div>
                                <div class="field"><label>Código interno</label><input name="codigo" required value="<?=h($editarFase['codigo']??'')?>" placeholder="FASE-08"></div>
                                <div class="field"><label>Nome curto da aba</label><input name="aba" required value="<?=h($editarFase['aba']??'')?>" placeholder="Planejamento"></div>
                                <div class="field"><label>Título completo</label><input name="titulo" required value="<?=h($editarFase['titulo']??'')?>" placeholder="Planejamento do Projeto"></div>
                                <div class="field span-2"><label>Descrição</label><textarea name="descricao"><?=h($editarFase['descricao']??'')?></textarea></div>
                                <div class="field span-2"><label>Responsável geral</label><input name="responsavel" value="<?=h($editarFase['responsavel']??'')?>"></div>
                                <div class="field"><label>Dia inicial</label><input type="number" min="1" name="dia_inicio" value="<?=h($editarFase['dia_inicio']??1)?>"></div>
                                <div class="field"><label>Dia final</label><input type="number" min="1" name="dia_fim" value="<?=h($editarFase['dia_fim']??1)?>"></div>
                                <div class="field"><label>Entregável</label><input name="entregavel" value="<?=h($editarFase['entregavel']??'')?>"></div>
                                <div class="field"><label>Critério de conclusão</label><input name="criterio" value="<?=h($editarFase['criterio']??'')?>"></div>
                                <label class="check-line span-2"><input type="checkbox" name="exclusivo_stratelli" value="1" <?=!empty($editarFase['exclusivo_stratelli'])?'checked':''?>> Fase exclusiva do perfil Stratelli</label>
                            </div>
                            <div class="form-actions"><button class="btn primary"><?=$editarFase?'Salvar alterações':'Cadastrar fase'?></button><?php if($editarFase):?><a class="btn neutral" href="?view=configuracoes&secao=fases&perfil=stratelli&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>">Cancelar</a><?php endif;?></div>
                        </form>
                    </section>
                    <section class="config-card">
                        <h2>Fases cadastradas</h2><p>As fases inativas permanecem no banco, mas deixam de aparecer no workflow.</p>
                        <div class="table-scroll"><table class="config-table"><thead><tr><th>Ordem</th><th>Fase</th><th>Prazo</th><th>Visibilidade</th><th>Status</th><th>Ações</th></tr></thead><tbody>
                        <?php foreach($fasesTodas as $f): ?><tr class="<?=!(int)$f['ativo']?'muted-row':''?>"><td><b><?=h($f['ordem'])?></b></td><td><div class="entity-title"><b><?=h($f['aba'])?></b><span class="entity-code"><?=h($f['codigo'])?></span></div><small><?=h($f['titulo'])?></small></td><td>Dia <?=h($f['dia_inicio'])?> ao <?=h($f['dia_fim'])?></td><td><?=$f['exclusivo_stratelli']?'Somente Stratelli':'Município e Stratelli'?></td><td><span class="status-mini <?=!(int)$f['ativo']?'inactive':''?>"><?=$f['ativo']?'ATIVA':'INATIVA'?></span></td><td><div class="actions"><a class="btn tiny secondary" href="?view=configuracoes&secao=fases&editar_fase=<?=$f['id']?>&perfil=stratelli&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>">Editar</a><form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="acao" value="alternar_fase"><input type="hidden" name="id" value="<?=$f['id']?>"><button class="btn tiny <?=$f['ativo']?'off':'approve'?>"><?=$f['ativo']?'Desativar':'Ativar'?></button></form></div></td></tr><?php endforeach; ?>
                        </tbody></table></div>
                    </section>
                </div>
                <?php elseif($secao==='secretarias'): ?>
                <div class="config-note">Cada secretaria pode ser vinculada a uma ou mais fases. Os departamentos ficam subordinados à secretaria e podem receber documentos específicos.</div>
                <div class="config-grid equal">
                    <section class="config-card">
                        <h2><?=$editarSecretaria?'Editar secretaria':'Cadastrar secretaria'?></h2><p>Selecione também as fases em que essa secretaria participa.</p>
                        <form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="acao" value="salvar_secretaria"><input type="hidden" name="id" value="<?=h($editarSecretaria['id']??0)?>">
                            <div class="form-grid"><div class="field"><label>Nome</label><input name="nome" required value="<?=h($editarSecretaria['nome']??'')?>"></div><div class="field"><label>Sigla</label><input name="sigla" value="<?=h($editarSecretaria['sigla']??'')?>"></div><div class="field span-2"><label>Fases vinculadas</label><div class="check-grid"><?php foreach($fasesTodas as $f):?><label><input type="checkbox" name="fase_ids[]" value="<?=$f['id']?>" <?=in_array((int)$f['id'],$faseIdsSecretaria,true)?'checked':''?>> Fase <?=h($f['ordem'])?> — <?=h($f['aba'])?></label><?php endforeach;?></div></div></div>
                            <div class="form-actions"><button class="btn primary">Salvar secretaria</button><?php if($editarSecretaria):?><a class="btn neutral" href="?view=configuracoes&secao=secretarias&perfil=stratelli&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>">Cancelar</a><?php endif;?></div>
                        </form>
                    </section>
                    <section class="config-card">
                        <h2><?=$editarDepartamento?'Editar departamento':'Cadastrar departamento'?></h2><p>O departamento poderá ser usado para direcionar um documento de forma mais específica.</p>
                        <form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="acao" value="salvar_departamento"><input type="hidden" name="id" value="<?=h($editarDepartamento['id']??0)?>">
                            <div class="form-grid"><div class="field span-2"><label>Secretaria</label><select name="secretaria_id" required><option value="">Selecione</option><?php foreach($secretarias as $s):?><option value="<?=$s['id']?>" <?=((int)($editarDepartamento['secretaria_id']??0)===(int)$s['id'])?'selected':''?>><?=h($s['nome'])?></option><?php endforeach;?></select></div><div class="field"><label>Nome do departamento</label><input name="nome" required value="<?=h($editarDepartamento['nome']??'')?>"></div><div class="field"><label>Sigla</label><input name="sigla" value="<?=h($editarDepartamento['sigla']??'')?>"></div></div>
                            <div class="form-actions"><button class="btn primary">Salvar departamento</button><?php if($editarDepartamento):?><a class="btn neutral" href="?view=configuracoes&secao=secretarias&perfil=stratelli&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>">Cancelar</a><?php endif;?></div>
                        </form>
                    </section>
                </div>
                <div class="config-grid equal" style="margin-top:18px">
                    <section class="config-card"><h2>Secretarias cadastradas</h2><div class="table-scroll"><table class="config-table"><thead><tr><th>Secretaria</th><th>Fases</th><th>Status</th><th>Ações</th></tr></thead><tbody><?php foreach($secretarias as $s):?><tr class="<?=!(int)$s['ativo']?'muted-row':''?>"><td><b><?=h($s['nome'])?></b><br><small><?=h($s['sigla'])?></small></td><td><?php if($s['fases_vinculadas']):foreach(explode(' • ',$s['fases_vinculadas']) as $v):?><span class="relation-tag"><?=h($v)?></span><?php endforeach;else:?><small>Sem fase vinculada</small><?php endif;?></td><td><span class="status-mini <?=!(int)$s['ativo']?'inactive':''?>"><?=$s['ativo']?'ATIVA':'INATIVA'?></span></td><td><div class="actions"><a class="btn tiny secondary" href="?view=configuracoes&secao=secretarias&editar_secretaria=<?=$s['id']?>&perfil=stratelli&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>">Editar</a><form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="acao" value="alternar_secretaria"><input type="hidden" name="id" value="<?=$s['id']?>"><button class="btn tiny <?=$s['ativo']?'off':'approve'?>"><?=$s['ativo']?'Desativar':'Ativar'?></button></form></div></td></tr><?php endforeach;?></tbody></table></div></section>
                    <section class="config-card"><h2>Departamentos cadastrados</h2><div class="table-scroll"><table class="config-table"><thead><tr><th>Departamento</th><th>Secretaria</th><th>Status</th><th>Ações</th></tr></thead><tbody><?php if($departamentos):foreach($departamentos as $d):?><tr class="<?=!(int)$d['ativo']?'muted-row':''?>"><td><b><?=h($d['nome'])?></b><br><small><?=h($d['sigla'])?></small></td><td><?=h($d['secretaria_nome'])?></td><td><span class="status-mini <?=!(int)$d['ativo']?'inactive':''?>"><?=$d['ativo']?'ATIVO':'INATIVO'?></span></td><td><div class="actions"><a class="btn tiny secondary" href="?view=configuracoes&secao=secretarias&editar_departamento=<?=$d['id']?>&perfil=stratelli&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>">Editar</a><form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="acao" value="alternar_departamento"><input type="hidden" name="id" value="<?=$d['id']?>"><button class="btn tiny <?=$d['ativo']?'off':'approve'?>"><?=$d['ativo']?'Desativar':'Ativar'?></button></form></div></td></tr><?php endforeach;else:?><tr><td colspan="4">Nenhum departamento cadastrado.</td></tr><?php endif;?></tbody></table></div></section>
                </div>
                <?php elseif($secao==='tipos'): ?>
                <div class="config-grid">
                    <section class="config-card"><h2><?=$editarTipo?'Editar tipo':'Cadastrar tipo de documento'?></h2><p>O tipo controla a classificação e as extensões permitidas no upload.</p><form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="acao" value="salvar_tipo"><input type="hidden" name="id" value="<?=h($editarTipo['id']??0)?>"><div class="form-grid"><div class="field span-2"><label>Nome do tipo</label><input name="nome" required value="<?=h($editarTipo['nome']??'')?>"></div><div class="field span-2"><label>Descrição</label><textarea name="descricao"><?=h($editarTipo['descricao']??'')?></textarea></div><div class="field span-2"><label>Extensões permitidas</label><input name="extensoes" required value="<?=h($editarTipo['extensoes']??'pdf,doc,docx,xls,xlsx')?>"><small>Separe por vírgula, sem ponto.</small></div></div><div class="form-actions"><button class="btn primary">Salvar tipo</button><?php if($editarTipo):?><a class="btn neutral" href="?view=configuracoes&secao=tipos&perfil=stratelli&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>">Cancelar</a><?php endif;?></div></form></section>
                    <section class="config-card"><h2>Tipos cadastrados</h2><div class="table-scroll"><table class="config-table"><thead><tr><th>Tipo</th><th>Extensões</th><th>Status</th><th>Ações</th></tr></thead><tbody><?php foreach($tiposDocumento as $t):?><tr class="<?=!(int)$t['ativo']?'muted-row':''?>"><td><b><?=h($t['nome'])?></b><br><small><?=h($t['descricao'])?></small></td><td><code><?=h($t['extensoes'])?></code></td><td><span class="status-mini <?=!(int)$t['ativo']?'inactive':''?>"><?=$t['ativo']?'ATIVO':'INATIVO'?></span></td><td><div class="actions"><a class="btn tiny secondary" href="?view=configuracoes&secao=tipos&editar_tipo=<?=$t['id']?>&perfil=stratelli&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>">Editar</a><form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="acao" value="alternar_tipo"><input type="hidden" name="id" value="<?=$t['id']?>"><button class="btn tiny <?=$t['ativo']?'off':'approve'?>"><?=$t['ativo']?'Desativar':'Ativar'?></button></form></div></td></tr><?php endforeach;?></tbody></table></div></section>
                </div>
                <?php else: ?>
                <div class="config-note"><b>Regra central:</b> nenhum documento fica fixo no código. Cada regra abaixo define qual fase exige o documento, qual secretaria ou departamento deve enviá-lo, qual tipo controla o arquivo e qual modelo será disponibilizado.</div>
                <div class="config-grid">
                    <section class="config-card">
                        <h2><?=$editarRequisito?'Editar documento exigido':'Cadastrar documento exigido'?></h2><p>O modelo pode ser enviado agora ou posteriormente no próprio workflow.</p>
                        <form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="acao" value="salvar_requisito"><input type="hidden" name="id" value="<?=h($editarRequisito['id']??0)?>">
                            <div class="form-grid">
                                <div class="field"><label>Fase</label><select name="fase_id" required><option value="">Selecione</option><?php foreach($fasesTodas as $f):?><option value="<?=$f['id']?>" <?=((int)($editarRequisito['fase_id']??0)===(int)$f['id'])?'selected':''?>>Fase <?=h($f['ordem'])?> — <?=h($f['aba'])?></option><?php endforeach;?></select></div>
                                <div class="field"><label>Ordem dentro da fase</label><input type="number" min="1" name="ordem" value="<?=h($editarRequisito['ordem']??1)?>"></div>
                                <div class="field"><label>Secretaria responsável</label><select name="secretaria_id" id="reqSecretaria" required><option value="">Selecione</option><?php foreach($secretarias as $s):?><option value="<?=$s['id']?>" <?=((int)($editarRequisito['secretaria_id']??0)===(int)$s['id'])?'selected':''?>><?=h($s['nome'])?></option><?php endforeach;?></select></div>
                                <div class="field"><label>Departamento (opcional)</label><select name="departamento_id" id="reqDepartamento"><option value="">Toda a secretaria</option><?php foreach($departamentos as $d):?><option data-secretaria="<?=$d['secretaria_id']?>" value="<?=$d['id']?>" <?=((int)($editarRequisito['departamento_id']??0)===(int)$d['id'])?'selected':''?>><?=h($d['nome'])?></option><?php endforeach;?></select></div>
                                <div class="field"><label>Tipo de documento</label><select name="tipo_documento_id" required><option value="">Selecione</option><?php foreach($tiposDocumento as $t):?><option value="<?=$t['id']?>" <?=((int)($editarRequisito['tipo_documento_id']??0)===(int)$t['id'])?'selected':''?>><?=h($t['nome'])?></option><?php endforeach;?></select></div>
                                <div class="field"><label>Responsável pelo envio</label><select name="perfil_envio"><option value="municipio" <?=($editarRequisito['perfil_envio']??'municipio')==='municipio'?'selected':''?>>Município</option><option value="stratelli" <?=($editarRequisito['perfil_envio']??'')==='stratelli'?'selected':''?>>Stratelli</option></select></div>
                                <div class="field span-2"><label>Nome do documento</label><input name="nome" required value="<?=h($editarRequisito['nome']??'')?>"></div>
                                <div class="field span-2"><label>Orientação / descrição</label><textarea name="descricao"><?=h($editarRequisito['descricao']??'')?></textarea></div>
                                <div class="field span-2"><label>Arquivo modelo (opcional)</label><input type="file" name="arquivo_modelo"><small>As extensões serão validadas conforme o tipo selecionado.</small></div>
                                <label class="check-line span-2"><input type="checkbox" name="obrigatorio" value="1" <?=!isset($editarRequisito['obrigatorio'])||$editarRequisito['obrigatorio']?'checked':''?>> Documento obrigatório para concluir a fase</label>
                            </div>
                            <div class="form-actions"><button class="btn primary">Salvar regra documental</button><?php if($editarRequisito):?><a class="btn neutral" href="?view=configuracoes&secao=documentos&perfil=stratelli&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>">Cancelar</a><?php endif;?></div>
                        </form>
                    </section>
                    <section class="config-card">
                        <h2>Documentos configurados</h2><p>Os modelos são versionados: o anterior permanece preservado no histórico.</p>
                        <div class="table-scroll"><table class="config-table"><thead><tr><th>Fase / Documento</th><th>Responsável</th><th>Tipo / Modelo</th><th>Status</th><th>Ações</th></tr></thead><tbody>
                        <?php foreach($requisitosTodos as $r):?><tr class="<?=!(int)$r['ativo']?'muted-row':''?>"><td><b>Fase <?=h($r['fase_ordem'])?> — <?=h($r['nome'])?></b><br><small><?=h($r['fase_aba'])?> · Ordem <?=h($r['ordem'])?> · <?=$r['obrigatorio']?'Obrigatório':'Opcional'?></small></td><td><b><?=h($r['secretaria_nome'])?></b><?php if($r['departamento_nome']):?><br><small><?=h($r['departamento_nome'])?></small><?php endif;?><br><span class="relation-tag">Envio: <?=h(strtoupper($r['perfil_envio']))?></span></td><td><b><?=h($r['tipo_nome'])?></b><br><?php if($r['modelo_nome']):?><small>📎 <?=h($r['modelo_nome'])?><br><?=h(tamanhoArquivo((int)$r['modelo_tamanho']))?> · <?=h(dataBr($r['modelo_data']))?></small><?php else:?><small>Sem modelo</small><?php endif;?></td><td><span class="status-mini <?=!(int)$r['ativo']?'inactive':''?>"><?=$r['ativo']?'ATIVO':'INATIVO'?></span></td><td><div class="actions"><a class="btn tiny secondary" href="?view=configuracoes&secao=documentos&editar_requisito=<?=$r['id']?>&perfil=stratelli&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>">Editar</a><?php if($r['modelo_id']):?><a class="btn tiny neutral" href="?acao=download_modelo&requisito=<?=$r['id']?>&perfil=stratelli&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>">Modelo</a><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="acao" value="alternar_requisito"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn tiny <?=$r['ativo']?'off':'approve'?>"><?=$r['ativo']?'Desativar':'Ativar'?></button></form></div></td></tr><?php endforeach;?>
                        </tbody></table></div>
                    </section>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="hero">
                    <div><h1>Workflow de Contratação / Fases</h1><p>Envio dos documentos e acompanhamento do processo</p></div>
                    <div class="notice <?=$perfil==='stratelli'?'purple':''?>"><?=$perfil==='municipio'?'Como Gestor Municipal, você acompanha e envia documentos de todas as secretarias vinculadas ao processo.':($perfil==='secretaria'?'Acesso restrito aos documentos de responsabilidade de '.h($secretariaPerfil['nome']).'. A fase atual é única para todas as secretarias e segue o andamento global definido pela Stratelli.':'Todas as fases, secretarias, tipos e modelos podem ser administrados no menu Configurações.')?></div>
                </div>
                <?php if($perfil==='secretaria' && !$secretariaTemDocumentosFaseAtual):?>
                    <section class="secretaria-no-documents" role="status" aria-live="polite">
                        <div class="secretaria-no-documents-icon" aria-hidden="true">✓</div>
                        <div>
                            <h2>Nenhum documento atribuído à sua secretaria nesta fase</h2>
                            <p>A fase atual do processo é <strong>Fase <?=h($atividade['ordem']??'—')?> — <?=h($atividade['aba']??'Em definição')?></strong>, porém a <?=h($secretariaPerfil['nome'])?> não possui documentos para enviar, corrigir ou acompanhar nela. Quando o processo avançar para uma fase com responsabilidade desta secretaria, o conteúdo será liberado automaticamente.</p>
                        </div>
                        <div class="secretaria-no-documents-meta">
                            <span>Sem ação necessária</span>
                            <b>Andamento controlado pela Stratelli</b>
                        </div>
                    </section>
                <?php else:?>
                <div class="kpis workflow-kpis <?=$perfil==='secretaria'?'secretaria':''?>">
                    <?php if($perfil==='secretaria'):?>
                        <div class="kpi waiting"><small>DOCUMENTOS ENVIADOS</small><b><?=$workflowSecretariaEnviados?></b><span>Enviados nesta fase pela secretaria</span></div>
                        <div class="kpi waiting"><small>AGUARDANDO VALIDAÇÃO</small><b><?=$workflowSecretariaAguardando?></b><span>Em análise pela Stratelli nesta fase</span></div>
                        <div class="kpi alert"><small>DOCUMENTOS PENDENTES</small><b><?=$workflowSecretariaPendentes?></b><span>Aguardando envio nesta fase</span></div>
                        <div class="kpi warning"><small>CORREÇÕES SOLICITADAS</small><b><?=$workflowSecretariaCorrecoes?></b><span>Pendências de correção nesta fase</span></div>
                        <div class="kpi kpi-deadline deadline-<?=h($prazoAtividade['status']??'blocked')?>"><small>PRAZO DA FASE</small><b><?=h($prazoAtividade['rotulo']??'—')?></b><span><?=h($prazoAtividade['texto']??'Cronograma indisponível')?></span></div>
                    <?php else:?>
                        <div class="kpi success"><small>PROGRESSO GERAL</small><b><?=$progresso?>%</b><span>Documentos aprovados</span></div>
                        <div class="kpi waiting"><small>DOCUMENTOS ENVIADOS</small><b><?=$totalEnviados?></b><span>Arquivos enviados</span></div>
                        <div class="kpi alert"><small>DOCUMENTOS PENDENTES</small><b><?=max(0,$totalDocs-$totalEnviados)?></b><span>Aguardando envio</span></div>
                        <div class="kpi waiting"><small>AGUARDANDO VALIDAÇÃO</small><b><?=$aguardando?></b><span>Em análise pela Stratelli</span></div>
                        <div class="kpi warning"><small>CORREÇÕES SOLICITADAS</small><b><?=$totalCorrecoes?></b><span>Pendências de correção</span></div>
                        <div class="kpi kpi-deadline deadline-<?=h($prazoAtividade['status']??'blocked')?>"><small>PRAZO DA FASE</small><b><?=h($prazoAtividade['rotulo']??'—')?></b><span><?=h($prazoAtividade['texto']??'Cronograma indisponível')?></span></div>
                        <?php if($perfil==='stratelli'):?>
                            <div class="kpi success"><small>FASES CONCLUÍDAS</small><b><?=$fasesConcluidas?> de <?=count($fasesVisiveis)?></b><span>Fases concluídas</span></div>
                            <div class="kpi alert"><small>SECRETARIAS PENDENTES</small><b><?=count($dashboardSecretariasPendentes)?></b><span>Com ações pendentes na fase atual</span></div>
                        <?php endif;?>
                    <?php endif;?>
                </div>
                <?php if($fasesWorkflowTabs):?>
                    <section class="contract-preview <?=$perfil==='stratelli'?'stratelli':''?>" aria-label="Prévia do cronograma das fases">
                        <div class="contract-preview-head">
                            <div>
                                <h2>Prévia do cronograma por fase</h2>
                                <p>Selecione uma fase para abrir seus detalhes. As datas previstas são calculadas pelos prazos cadastrados e pela conclusão da fase anterior.</p>
                            </div>
                            <span class="contract-preview-law">Base normativa geral: Lei nº 14.133/2021</span>
                        </div>
                        <div class="contract-preview-scroll">
                            <div class="contract-preview-flow" style="--phase-count:<?=count($fasesWorkflowTabs)?>">
                                <?php
                                $iconesPrevias=['▣','▤','✎','⌕','☷','⚖','◎','◇','✓','§'];
                                foreach($fasesWorkflowTabs as $fasePrevia):
                                    $prazoPrevia=$cronogramaPorFase[(int)$fasePrevia['id']]??null;
                                    $statusDocumentalPrevia=statusFase($fasePrevia,$requisitosVisiveis,$ultimosDocs);
                                    $statusPrazoPrevia=(string)($prazoPrevia['status']??'blocked');
                                    if(!empty($prazoPrevia['conclusao_real'])||$statusDocumentalPrevia==='done')$classePrevia='completed';
                                    elseif((int)$fasePrevia['id']===$faseAtualId)$classePrevia='current';
                                    elseif(in_array($statusPrazoPrevia,['overdue','completed-late'],true))$classePrevia='overdue';
                                    elseif($statusPrazoPrevia==='attention')$classePrevia='attention';
                                    else $classePrevia='future';
                                    $duracaoPrevia=max(1,(int)($prazoPrevia['duracao']??((int)$fasePrevia['dia_fim']-(int)$fasePrevia['dia_inicio']+1)));
                                    $iconePrevia=$iconesPrevias[((int)$fasePrevia['ordem'])%count($iconesPrevias)];
                                    $rotuloDataPrevia=(!empty($prazoPrevia['inicio'])&& !empty($prazoPrevia['fim']))
                                        ? dataCurtaBr($prazoPrevia['inicio']).' — '.dataCurtaBr($prazoPrevia['fim'])
                                        : 'Datas em definição';
                                    $rotuloSituacaoPrevia=(string)($prazoPrevia['rotulo']??'Aguardando cálculo');
                                    $entregavelPrevia=trim((string)($fasePrevia['entregavel']??'')) ?: 'Conclusão da '.$fasePrevia['aba'];
                                ?>
                                    <a class="contract-preview-stage <?=h($classePrevia)?>" href="?view=workflow&fase=<?=$fasePrevia['id']?>&perfil=<?=$perfil?>&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>" aria-label="Abrir Fase <?=h($fasePrevia['ordem'])?> — <?=h($fasePrevia['titulo'])?>" <?=$faseAtualId===(int)$fasePrevia['id']?'aria-current="step"':''?>>
                                        <div class="contract-preview-number"><?=h($fasePrevia['ordem'])?></div>
                                        <div class="contract-preview-card">
                                            <div class="contract-preview-date"><?=h($rotuloDataPrevia)?></div>
                                            <div class="contract-preview-body">
                                                <div class="contract-preview-icon" aria-hidden="true"><?=h($iconePrevia)?></div>
                                                <strong><?=h($fasePrevia['titulo'])?></strong>
                                                <small><?=h($rotuloSituacaoPrevia)?><?php if(!empty($prazoPrevia['provisorio'])):?> · prévia<?php endif;?></small>
                                            </div>
                                            <div class="contract-preview-duration"><?=$duracaoPrevia?> dia(s)</div>
                                        </div>
                                        <div class="contract-preview-milestone"><?=h($entregavelPrevia)?></div>
                                    </a>
                                <?php endforeach;?>
                            </div>
                        </div>
                        <div class="contract-preview-footer">
                            <div class="contract-preview-legal">
                                <div class="contract-preview-legal-icon" aria-hidden="true">⚖</div>
                                <div><strong>Base legal</strong><span>Lei nº 14.133/2021 e demais normas aplicáveis às licitações e contratações públicas.</span></div>
                            </div>
                            <div class="contract-preview-note">
                                <div><strong>Leitura da prévia</strong><span>As datas e durações são parâmetros operacionais cadastrados no INPACTA. Elas podem ser recalculadas após a conclusão real da fase anterior e não representam, isoladamente, prazos obrigatórios definidos pela lei.</span></div>
                            </div>
                        </div>
                    </section>
                <?php endif;?>
                <?php if($perfil==='secretaria' && $secretariaTemDocumentosFaseAtual):?>
                    <section class="card workflow-inline-progress" aria-label="Andamento dos documentos da sua secretaria na fase">
                        <div class="workflow-inline-progress-head">
                            <div>
                                <strong>Andamento dos documentos da sua secretaria na fase atual</strong><br>
                                <small>Visualização rápida do percentual aprovado, aguardando validação, em correção e não entregue.</small>
                            </div>
                            <span class="workflow-inline-progress-pill">Fase <?=h($atividade['ordem']??'—')?> · <?=h($atividade['aba']??'Em definição')?></span>
                        </div>
                        <?php if($totalResumoEntregas>0):?>
                            <div class="delivery-progress-bar" role="img" aria-label="<?=$resumoEntregues?> aprovado(s), <?=$resumoAguardandoValidacao?> aguardando validação, <?=$resumoCorrecoes?> em correção e <?=$resumoNaoEntregues?> não entregue(s)">
                                <?php if($resumoEntregues>0):?><span class="delivered" style="width:<?=number_format(($resumoEntregues/$totalResumoEntregas)*100,4,'.','')?>%"><b><?=round(($resumoEntregues/$totalResumoEntregas)*100)?>%</b></span><?php endif;?>
                                <?php if($resumoAguardandoValidacao>0):?><span class="awaiting-validation" style="width:<?=number_format(($resumoAguardandoValidacao/$totalResumoEntregas)*100,4,'.','')?>%"><b><?=round(($resumoAguardandoValidacao/$totalResumoEntregas)*100)?>%</b></span><?php endif;?>
                                <?php if($resumoCorrecoes>0):?><span class="needs-correction" style="width:<?=number_format(($resumoCorrecoes/$totalResumoEntregas)*100,4,'.','')?>%"><b><?=round(($resumoCorrecoes/$totalResumoEntregas)*100)?>%</b></span><?php endif;?>
                                <?php if($resumoNaoEntregues>0):?><span class="not-delivered" style="width:<?=number_format(($resumoNaoEntregues/$totalResumoEntregas)*100,4,'.','')?>%"><b><?=round(($resumoNaoEntregues/$totalResumoEntregas)*100)?>%</b></span><?php endif;?>
                            </div>
                        <?php endif;?>
                        <div class="delivery-legend">
                            <span><i class="delivered"></i>Verde: entregue e aprovado</span>
                            <span><i class="awaiting-validation"></i>Azul: aguardando validação</span>
                            <span><i class="needs-correction"></i>Amarelo: correção necessária</span>
                            <span><i class="not-delivered"></i>Vermelho: ainda não entregue</span>
                        </div>
                    </section>
                <?php endif;?>
                <?php if(!$atividade):?><div class="empty-state">Nenhuma fase ativa está disponível para este perfil.</div><?php else:?>
                <div class="content">
                    <section class="card phase-summary <?=h($faseVisualClass)?>">
                        <div class="phase-summary-head"><h2>Fase <?=h($atividade['ordem'])?> - <?=h($atividade['titulo'])?></h2><span class="statusbox <?=h($faseVisualClass)?>"><?=h($faseStatusLabel)?></span></div>
                        <p><?=h($atividade['descricao'])?></p>
                        <div class="details-row"><div class="detail <?=$perfil==='secretaria'?'deadline-'.h($workflowPrazoEntregaClasse):''?>"><small><?=$perfil==='secretaria'?'Prazo para entrega':'Prazo'?></small><?php if($perfil==='secretaria'):?><b><?=h($workflowPrazoEntregaTexto)?></b><?php if($workflowPrazoEntregaComplemento):?><span class="detail-note"><?=h($workflowPrazoEntregaComplemento)?></span><?php endif;?><?php else:?><b>Dia <?=h($atividade['dia_inicio'])?> ao <?=h($atividade['dia_fim'])?></b><?php endif;?></div><div class="detail"><small>Responsável</small><b><?=h($atividade['responsavel'])?></b></div><div class="detail"><small>Duração</small><b><?=max(1,(int)$atividade['dia_fim']-(int)$atividade['dia_inicio']+1)?> dia(s)</b></div><div class="detail"><small>Entregável</small><b><?=h($atividade['entregavel'])?></b></div></div>
                    </section>
                    <section class="card deadline-control-card <?=h($prazoAtividade['status']??'blocked')?>" id="controle-prazo">
                        <div class="deadline-control-head">
                            <div><h2>Controle de prazo da Fase <?=h($atividade['ordem'])?></h2><p>O prazo utiliza os <?=h($prazoAtividade['duracao']??max(1,(int)$atividade['dia_fim']-(int)$atividade['dia_inicio']+1))?> dia(s) cadastrados no Gantt e começa no dia seguinte à conclusão da fase anterior.</p></div>
                            <span class="deadline-status-pill <?=h($prazoAtividade['status']??'blocked')?>"><?=h($prazoAtividade['rotulo']??'—')?></span>
                        </div>
                        <div class="deadline-control-grid">
                            <div class="deadline-control-item"><small>Início do processo</small><b><?=h(dataCurtaBr($dataInicioProcesso))?></b></div>
                            <div class="deadline-control-item"><small>Início desta fase</small><b><?=h(dataCurtaBr($prazoAtividade['inicio']??null))?></b></div>
                            <div class="deadline-control-item"><small>Data limite</small><b><?=h(dataCurtaBr($prazoAtividade['fim']??null))?></b></div>
                            <div class="deadline-control-item"><small>Conclusão real</small><b><?=h(dataCurtaBr($prazoAtividade['conclusao_real']??null))?></b></div>
                            <div class="deadline-control-item"><small>Situação</small><b><?=h($prazoAtividade['texto']??'Cronograma indisponível')?></b></div>
                        </div>
                        <?php if(!empty($prazoAtividade['provisorio'])):?><span class="deadline-provisional">Datas provisórias: a fase anterior ainda não foi concluída. O sistema recalculará automaticamente.</span><?php endif;?>
                        <div class="deadline-control-progress">
                            <div class="deadline-time-head"><span>Prazo consumido até <?=h(dataCurtaBr($hojeCronograma))?></span><strong><?=h($prazoAtividade['percentual_tempo']??0)?>%</strong></div>
                            <div class="deadline-time-track <?=h($prazoAtividade['status']??'blocked')?>"><span style="width:<?=h($prazoAtividade['percentual_tempo']??0)?>%"></span><em><?=h($prazoAtividade['percentual_tempo']??0)?>%</em></div>
                        </div>
                        <?php if($perfil==='stratelli'):?>
                            <div class="deadline-control-actions">
                                <form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="acao" value="salvar_inicio_cronograma"><input type="hidden" name="fase_id" value="<?=$atividade['id']?>"><label>Marco inicial do processo<input type="date" name="data_inicio" required value="<?=h($dataInicioProcesso)?>"></label><button class="btn neutral">Atualizar início</button></form>
                                <form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="acao" value="registrar_conclusao_fase"><input type="hidden" name="fase_id" value="<?=$atividade['id']?>"><label>Conclusão da fase<input type="date" name="data_conclusao" required value="<?=h($prazoAtividade['conclusao_real']??$hojeCronograma)?>"></label><label>Observação<textarea name="observacao" rows="1" placeholder="Opcional"><?=h($prazoAtividade['registro_manual']['observacao']??'')?></textarea></label><button class="btn approve"><?=!empty($prazoAtividade['conclusao_real'])?'Atualizar conclusão':'Concluir fase'?></button></form>
                                <?php if(!empty($prazoAtividade['registro_manual'])):?><form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="acao" value="remover_conclusao_fase"><input type="hidden" name="fase_id" value="<?=$atividade['id']?>"><button class="btn correct">Remover conclusão manual</button></form><?php endif;?>
                            </div>
                        <?php endif;?>
                    </section>
                    <section class="card grouped-documents-section" id="documentos-fase">
                        <h2>Documentos da Fase</h2><p><?=$perfil==='secretaria'?'Os documentos estão organizados no agrupamento da sua secretaria, com entrega, modelo e informações próprias.':'Os documentos estão organizados por secretaria. Cada agrupamento preserva entrega, modelo, validação e histórico individualizados.'?></p>
                        <?php if(!$gruposDocumentosFase):?><div class="empty-state">Nenhum documento ativo foi configurado para esta fase.</div><?php else:?>
                        <?php
                        $gruposPorSecretaria=[];
                        foreach($gruposDocumentosFase as $grupoDocumentoTmp){
                            foreach($grupoDocumentoTmp['entregas'] as $entregaDocumentoTmp){
                                $reqGrupoSecretaria=$entregaDocumentoTmp['requisito']??null;
                                if(!$reqGrupoSecretaria)continue;
                                $chaveSecretaria='sec_'.(string)($reqGrupoSecretaria['secretaria_id']??'0');
                                if(!isset($gruposPorSecretaria[$chaveSecretaria])){
                                    $gruposPorSecretaria[$chaveSecretaria]=[
                                        'nome'=>$reqGrupoSecretaria['secretaria_nome']??'Secretaria',
                                        'sigla'=>$reqGrupoSecretaria['secretaria_sigla']??'',
                                        'itens'=>[],
                                    ];
                                }
                                $gruposPorSecretaria[$chaveSecretaria]['itens'][]=[
                                    'grupo'=>$grupoDocumentoTmp,
                                    'entrega'=>$entregaDocumentoTmp,
                                ];
                            }
                        }
                        uasort($gruposPorSecretaria,fn($a,$b)=>strnatcasecmp((string)$a['nome'],(string)$b['nome']));
                        ?>
                        <div class="secretaria-doc-groups">
                            <?php foreach($gruposPorSecretaria as $grupoSecretaria):?>
                                <section class="secretaria-doc-group">
                                    <div class="secretaria-doc-group-head">
                                        <div>
                                            <h3><?=h(($grupoSecretaria['sigla']?$grupoSecretaria['sigla'].' — ':'').$grupoSecretaria['nome'])?></h3>
                                            <p><?=$perfil==='secretaria'?'Documentos sob responsabilidade da sua secretaria nesta fase.':'Entregas, modelos e documentos vinculados a esta secretaria na fase atual.'?></p>
                                        </div>
                                        <span class="secretaria-doc-group-badge"><?=count($grupoSecretaria['itens'])?> documento(s)</span>
                                    </div>
                                    <table class="doc-table grouped-doc-table secretaria-grouped-doc-table">
                                        <thead><tr><th>Entregas por secretaria</th><th>Modelo único</th><th>Documento</th></tr></thead>
                                        <tbody>
                                        <?php foreach($grupoSecretaria['itens'] as $itemSecretaria):
                                            $grupoDocumento=$itemSecretaria['grupo'];
                                            $entregaGrupo=$itemSecretaria['entrega'];
                                            $r=$grupoDocumento['referencia'];
                                            $modeloGrupo=$grupoDocumento['modelo'];
                                            $reqEntrega=$entregaGrupo['requisito'];
                                            $docEntrega=$entregaGrupo['documento'];
                                        ?>
                                            <tr class="<?=($entregaGrupo['classe']??'')==='approved'?'row-approved':''?>">
                                                <td data-label="Entregas por secretaria">
                                                    <article class="grouped-delivery-card <?=h($entregaGrupo['classe'])?>">
                                                        <div class="grouped-delivery-head">
                                                            <div><strong><?=h($reqEntrega['secretaria_nome'])?></strong><?php if($reqEntrega['departamento_nome']):?><small><?=h($reqEntrega['departamento_nome'])?></small><?php endif;?></div>
                                                            <span class="pill <?=h($entregaGrupo['classe'])?>"><?=h($entregaGrupo['rotulo'])?></span>
                                                        </div>
                                                        <?php if(!empty($reqEntrega['descricao'])&&$normalizarGrupo($reqEntrega['descricao'])!==$normalizarGrupo($r['descricao']??'')):?><div class="grouped-delivery-guidance"><b>Orientação específica:</b> <?=h($reqEntrega['descricao'])?></div><?php endif;?>
                                                        <?php if($docEntrega):?><div class="grouped-delivery-file"><b>Versão <?=h($docEntrega['versao'])?></b> · <?=h($docEntrega['arquivo_original'])?><br><small>Enviado em <?=h(dataBr($docEntrega['enviado_em']))?></small></div><?php else:?><div class="grouped-delivery-file empty">Nenhum arquivo enviado por esta secretaria.</div><?php endif;?>
                                                        <?php if($docEntrega&&($docEntrega['status']??'')==='correcao'&&$docEntrega['observacao_validacao']):?><div class="grouped-delivery-correction"><b>Correção solicitada:</b> <?=h($docEntrega['observacao_validacao'])?></div><?php endif;?>
                                                        <div class="grouped-delivery-actions">
                                                            <?php if($docEntrega):?><a class="btn neutral download-current" href="?acao=download_documento&id=<?=$docEntrega['id']?>&perfil=<?=$perfil?>&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>">⬇ Baixar enviado</a><?php endif;?>
                                                            <?php $podeEnviarEntrega=$reqEntrega['perfil_envio']===$perfil||($perfil==='secretaria'&&$reqEntrega['perfil_envio']==='municipio'&&(int)$reqEntrega['secretaria_id']===$secretariaPerfilId);if($podeEnviarEntrega):?><form class="upload-form" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="acao" value="enviar_documento"><input type="hidden" name="secretaria_contexto" value="<?=$secretariaPerfilId?>"><input type="hidden" name="requisito_id" value="<?=$reqEntrega['id']?>"><input type="file" name="arquivo" required><button class="btn primary"><?=$docEntrega?'Reenviar':'Enviar'?></button></form><?php endif;?>
                                                        </div>
                                                        <?php if($perfil==='stratelli'&&$reqEntrega['perfil_envio']==='municipio'&&$docEntrega):?>
                                                            <?php if(($docEntrega['status']??'')!=='aprovado'):?>
                                                                <form class="validation grouped-validation" method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="documento_id" value="<?=$docEntrega['id']?>"><textarea name="observacao" rows="2" placeholder="Motivo da correção ou observação"></textarea><div class="validation-actions"><button class="btn approve" name="acao" value="aprovar_documento">Aprovar</button><button class="btn correct" name="acao" value="corrigir_documento">Correção</button></div></form>
                                                            <?php else:?>
                                                                <div class="validation-complete"><span>Validação concluída<?php if(!empty($docEntrega['validado_em'])):?> em <?=h(dataBr($docEntrega['validado_em']))?><?php endif;?><?php if(!empty($docEntrega['validado_por'])):?> por <?=h($docEntrega['validado_por'])?><?php endif;?>.</span><strong>✓ Aprovado</strong></div>
                                                            <?php endif;?>
                                                        <?php endif;?>
                                                    </article>
                                                </td>
                                                <td data-label="Modelo único">
                                                    <?php if($modeloGrupo):?><div class="model-box"><a class="btn model" href="?acao=download_modelo&requisito=<?=$modeloGrupo['id']?>&perfil=<?=$perfil?>&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>">⬇ Baixar modelo</a><span class="model-meta"><?=h($modeloGrupo['modelo_nome'])?><?php if($modeloGrupo['modelo_tamanho']):?> · <?=h(tamanhoArquivo((int)$modeloGrupo['modelo_tamanho']))?><?php endif;?><br>Atualizado em <?=h(dataBr($modeloGrupo['modelo_data']))?><br><b><?=$perfil==='secretaria'?'Modelo aplicável à sua secretaria.':'Modelo compartilhado entre as secretarias vinculadas a este documento.'?></b></span></div><?php else:?><span class="model-empty">Modelo ainda não disponibilizado para este documento.</span><?php endif;?>
                                                    <?php if($perfil==='stratelli' && ($entregaGrupo['classe']??'')!=='approved'):?><form class="model-upload grouped-model-upload" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="acao" value="upload_modelo"><input type="hidden" name="requisito_id" value="<?=$r['id']?>"><input type="hidden" name="requisito_ids" value="<?=h($grupoDocumento['ids'])?>"><input type="file" name="arquivo_modelo" required><button class="btn model-upload-btn"><?=$modeloGrupo?'Substituir para todas':'Subir para todas'?></button><small>O arquivo será vinculado a todas as secretarias deste documento.</small></form><?php endif;?>
                                                </td>
                                                <td data-label="Documento">
                                                    <div class="grouped-document-title"><b><?=h($r['nome'])?></b><span class="pill <?=h($entregaGrupo['classe'])?>"><?=h($entregaGrupo['rotulo'])?></span></div>
                                                    <?php if($r['descricao']):?><small class="grouped-document-description"><?=h($r['descricao'])?></small><?php endif;?>
                                                    <div class="doc-owner"><span><?=h($r['tipo_nome'])?></span><span class="profile">Envio: <?=h($perfil==='secretaria'?'SECRETARIA':strtoupper($r['perfil_envio']))?></span><span><?=count($grupoDocumento['entregas'])?> secretaria(s)/unidade(s) vinculada(s)</span><?php if(!$r['obrigatorio']):?><span>Opcional</span><?php endif;?></div>
                                                </td>
                                            </tr>
                                        <?php endforeach;?>
                                        </tbody>
                                    </table>
                                </section>
                            <?php endforeach;?>
                        </div>
                        <?php endif;?>
                    </section>
                </div>
                <section class="card delivery-overview-card" id="painel-entregas" aria-labelledby="titulo-painel-entregas">
                    <div class="delivery-overview-head">
                        <div>
                            <h2 id="titulo-painel-entregas">Situação dos documentos da Fase <?=h($atividade['ordem'])?></h2>
                            <p>Visão rápida do que foi aprovado, do que aguarda validação, do que precisa de correção e do que ainda falta ser enviado.</p>
                        </div>
                        <div class="delivery-overview-summary" aria-label="Resumo da situação dos documentos">
                            <div class="delivery-summary-box delivered"><span class="delivery-summary-icon">✓</span><strong><?=$resumoEntregues?></strong><small>Entregues</small></div>
                            <div class="delivery-summary-box awaiting-validation"><span class="delivery-summary-icon">…</span><strong><?=$resumoAguardandoValidacao?></strong><small>Aguardando validação</small></div>
                            <div class="delivery-summary-box needs-correction"><span class="delivery-summary-icon">!</span><strong><?=$resumoCorrecoes?></strong><small>Em correção</small></div>
                            <div class="delivery-summary-box not-delivered"><span class="delivery-summary-icon">×</span><strong><?=$resumoNaoEntregues?></strong><small>Não entregues</small></div>
                        </div>
                    </div>
                    <?php if($totalResumoEntregas>0):?>
                        <div class="delivery-progress-bar" role="img" aria-label="<?=$resumoEntregues?> aprovado(s), <?=$resumoAguardandoValidacao?> aguardando validação, <?=$resumoCorrecoes?> em correção e <?=$resumoNaoEntregues?> não entregue(s)">
                            <?php if($resumoEntregues>0):?><span class="delivered" title="Entregues e aprovados: <?=number_format($percentualEntregues,0,',','.')?>%" style="width:<?=number_format($percentualEntregues,4,'.','')?>%"><b><?=number_format($percentualEntregues,0,',','.')?>%</b></span><?php endif;?>
                            <?php if($resumoAguardandoValidacao>0):?><span class="awaiting-validation" title="Aguardando validação: <?=number_format($percentualAguardandoValidacao,0,',','.')?>%" style="width:<?=number_format($percentualAguardandoValidacao,4,'.','')?>%"><b><?=number_format($percentualAguardandoValidacao,0,',','.')?>%</b></span><?php endif;?>
                            <?php if($resumoCorrecoes>0):?><span class="needs-correction" title="Em correção: <?=number_format($percentualCorrecoes,0,',','.')?>%" style="width:<?=number_format($percentualCorrecoes,4,'.','')?>%"><b><?=number_format($percentualCorrecoes,0,',','.')?>%</b></span><?php endif;?>
                            <?php if($resumoNaoEntregues>0):?><span class="not-delivered" title="Não entregues: <?=number_format($percentualNaoEntregues,0,',','.')?>%" style="width:<?=number_format($percentualNaoEntregues,4,'.','')?>%"><b><?=number_format($percentualNaoEntregues,0,',','.')?>%</b></span><?php endif;?>
                        </div>
                        <div class="delivery-legend"><span><i class="delivered"></i>Verde: entregue e aprovado</span><span><i class="awaiting-validation"></i>Azul: aguardando validação</span><span><i class="needs-correction"></i>Amarelo: correção necessária</span><span><i class="not-delivered"></i>Vermelho: ainda não entregue</span></div>
                        <?php if($secretariasPendentesFase):?>
                            <div class="delivery-secretaria-head">
                                <div>
                                    <h3><?=$perfil==='secretaria'?'Pendências da sua secretaria':'Secretarias com pendências nesta fase'?></h3>
                                    <p><?=$perfil==='secretaria'?'Lista dos documentos que sua secretaria ainda precisa enviar ou corrigir.':'Lista objetiva das secretarias que ainda precisam enviar documento(s) ou reenviar arquivo(s) devolvidos para correção.'?></p>
                                </div>
                                <span class="delivery-secretaria-badge"><?=count($secretariasPendentesFase)?> secretaria(s) com pendência</span>
                            </div>
                            <div class="delivery-secretaria-quickgrid" aria-label="Mapa rápido de secretarias com pendência">
                                <?php foreach($secretariasPendentesFase as $secretariaPendente):$primeiroItemSecretaria=$secretariaPendente['documentos_pendentes'][0]??($secretariaPendente['documentos_correcao'][0]??'Verifique os documentos desta secretaria.');?>
                                    <article class="delivery-secretaria-quickcard <?=h($secretariaPendente['status_visual'])?>">
                                        <div class="delivery-secretaria-quickicon" aria-hidden="true"><?=(int)$secretariaPendente['not_delivered']>0?'✕':'!'?></div>
                                        <div class="delivery-secretaria-quickcontent">
                                            <div class="delivery-secretaria-quicktop">
                                                <strong><?=h($secretariaPendente['nome'])?></strong>
                                                <span class="delivery-secretaria-quickpill"><?=h($secretariaPendente['rotulo'])?></span>
                                            </div>
                                            <div class="delivery-secretaria-quickmeta">
                                                <span>✕ <?=$secretariaPendente['not_delivered']?> pendente(s)</span>
                                                <span>! <?=$secretariaPendente['needs_correction']?> correção(ões)</span>
                                            </div>
                                            <div class="delivery-secretaria-quickhint">Prioridade: <?=h($primeiroItemSecretaria)?></div>
                                        </div>
                                    </article>
                                <?php endforeach;?>
                            </div>
                        <?php else:?><div class="delivery-secretaria-empty">Não há ações de envio ou correção pendentes. Arquivos em análise continuam aguardando validação da Stratelli.</div><?php endif;?>
                        <div class="delivery-document-grid">
                            <?php foreach($resumoEntregasFase as $itemResumo):$rResumo=$itemResumo['requisito'];$docResumo=$itemResumo['documento'];?>
                                <article class="delivery-document-item <?=h($itemResumo['status_visual'])?>">
                                    <div class="delivery-document-icon" aria-hidden="true"><?=h($itemResumo['icone'])?></div>
                                    <div class="delivery-document-content">
                                        <div class="delivery-document-title"><strong><?=h($rResumo['nome'])?></strong><span class="delivery-status-label"><?=h($itemResumo['rotulo'])?></span></div>
                                        <div class="delivery-document-meta"><span><?=h($rResumo['secretaria_nome'])?></span><?php if($rResumo['departamento_nome']):?><span><?=h($rResumo['departamento_nome'])?></span><?php endif;?><span><?=h($rResumo['tipo_nome'])?></span><?php if(!(int)$rResumo['obrigatorio']):?><span>Opcional</span><?php endif;?></div>
                                        <?php if($itemResumo['status_visual']==='delivered'):?>
                                            <p class="delivery-document-detail"><strong>Arquivo aprovado:</strong> <?=h($docResumo['arquivo_original'])?> · versão <?=h($docResumo['versao'])?> · aprovado em <?=h(dataBr($docResumo['validado_em']?:$docResumo['enviado_em']))?></p>
                                        <?php elseif($itemResumo['status_visual']==='awaiting-validation'):?>
                                            <p class="delivery-document-detail"><strong>Arquivo enviado:</strong> <?=h($docResumo['arquivo_original'])?> · versão <?=h($docResumo['versao'])?> · aguardando validação da Stratelli desde <?=h(dataBr($docResumo['enviado_em']))?></p>
                                        <?php elseif($itemResumo['status_visual']==='needs-correction'):?>
                                            <p class="delivery-document-detail"><strong>Correção solicitada:</strong> <?=h($docResumo['observacao_validacao']?:'O documento precisa ser ajustado e reenviado.')?></p>
                                        <?php else:?>
                                            <p class="delivery-document-detail"><?=$perfil==='secretaria'?'Aguardando o envio deste documento pela sua secretaria.':'Aguardando o envio deste documento pelo perfil '.h(strtoupper($rResumo['perfil_envio'])).'.'?></p>
                                        <?php endif;?>
                                    </div>
                                </article>
                            <?php endforeach;?>
                        </div>
                    <?php else:?><div class="delivery-overview-empty">Nenhum documento ativo foi configurado para esta fase.</div><?php endif;?>
                </section>
                <section class="card workflow-log-card" id="log-documentos">
                    <div class="workflow-log-head"><div><h2>Log de documentos da Fase <?=h($atividade['ordem'])?></h2><p>Histórico de modelos, uploads, substituições, aprovações e correções.</p></div><span class="workflow-log-count"><?=$totalLogsFase?> registro(s)</span></div>
                    <?php if($historicoFasePagina):?><div class="workflow-log-wrap"><table class="workflow-log-table"><thead><tr><th>Evento</th><th>Documento e arquivo</th><th>Responsável</th><th>Data / Hora</th><th>Motivo / alteração</th><th>Ação</th></tr></thead><tbody>
                    <?php foreach($historicoFasePagina as $hist):$evento=(string)$hist['evento'];$eventoClass=str_contains(strtolower($evento),'corre')?'correction':(str_contains(strtolower($evento),'aprov')?'approved':(str_contains(strtolower($evento),'substit')?'replaced':(str_contains(strtolower($evento),'modelo')?'model':'')));$perfilHist=strtoupper((string)$hist['perfil']);?>
                        <tr><td data-label="Evento"><span class="workflow-event <?=h($eventoClass)?>"><?=h($evento)?></span></td><td data-label="Documento e arquivo"><div class="workflow-log-file"><b><?=h($hist['documento']??'Documento')?></b><span>📎 <?=h($hist['arquivo_original']?:'Sem arquivo associado')?></span><?php if($hist['arquivo_anterior_original']):?><small>Substituiu: <?=h($hist['arquivo_anterior_original'])?></small><?php endif;?><?php if($hist['tamanho']):?><small><?=h(tamanhoArquivo((int)$hist['tamanho']))?></small><?php endif;?></div></td><td data-label="Responsável"><div class="workflow-log-user"><b><?=h($hist['usuario']?:'—')?></b><?php if($hist['email']):?><small><?=h($hist['email'])?></small><?php endif;?><span class="workflow-log-profile <?=$perfilHist==='STRATELLI'?'stratelli':'municipio'?>"><?=h($perfilHist?:'USUÁRIO')?></span></div></td><td data-label="Data / Hora"><?=h(dataBr($hist['criado_em']))?></td><td data-label="Motivo / alteração"><div class="workflow-log-reason"><strong><?=$eventoClass==='correction'?'Motivo da correção':'Detalhamento'?></strong><?=h($hist['motivo']?:'Sem observação registrada.')?></div></td><td data-label="Ação"><?php if($hist['arquivo_salvo']):?><a class="workflow-log-download" href="?acao=download_historico&id=<?=$hist['id']?>&fase=<?=$faseAtualId?>&perfil=<?=$perfil?>&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>">⬇ Baixar</a><?php else:?>—<?php endif;?></td></tr>
                    <?php endforeach;?></tbody></table></div><?php else:?><div class="workflow-log-empty">Nenhuma movimentação registrada nesta fase.</div><?php endif;?>
                    <?php if($totalLogsFase>0):?><div class="workflow-log-pagination"><span>Exibindo <?=$primeiroRegistroLog?>–<?=$ultimoRegistroLog?> de <?=$totalLogsFase?> registros</span><div class="workflow-log-pages"><?php if($paginaLog>1):?><a href="?view=workflow&fase=<?=$faseAtualId?>&perfil=<?=$perfil?>&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>&log_pagina=<?=$paginaLog-1?>#log-documentos">← Anterior</a><?php else:?><span class="disabled">← Anterior</span><?php endif;?><span class="current"><?=$paginaLog?> / <?=$totalPaginasLog?></span><?php if($paginaLog<$totalPaginasLog):?><a href="?view=workflow&fase=<?=$faseAtualId?>&perfil=<?=$perfil?>&municipio=<?=h($municipioId)?><?=h($contextoSecretariaQuery)?>&log_pagina=<?=$paginaLog+1?>#log-documentos">Próxima →</a><?php else:?><span class="disabled">Próxima →</span><?php endif;?></div></div><?php endif;?>
                </section>
                <?php endif;?>
                <?php endif;?>
            <?php endif; ?>
        </section>
    </main>
</div>
<script>
function atualizarBotaoMenu(recolhido){const botao=document.getElementById('menuToggle');const texto=document.getElementById('menuToggleText');if(!botao||!texto)return;botao.setAttribute('aria-expanded',recolhido?'false':'true');texto.textContent=recolhido?'Mostrar menu':'Recolher menu'}
function alternarMenu(){const app=document.querySelector('.app');if(!app)return;const recolhido=app.classList.toggle('menu-collapsed');atualizarBotaoMenu(recolhido);try{localStorage.setItem('inpactaMenuRecolhido',recolhido?'1':'0')}catch(e){}}
function alternarNotificacoes(event){if(event)event.stopPropagation();const painel=document.getElementById('notificationPanel');const botao=document.getElementById('notificationToggle');if(!painel||!botao)return;const abrir=painel.hidden;painel.hidden=!abrir;botao.setAttribute('aria-expanded',abrir?'true':'false')}
function fecharNotificacoes(){const painel=document.getElementById('notificationPanel');const botao=document.getElementById('notificationToggle');if(painel)painel.hidden=true;if(botao)botao.setAttribute('aria-expanded','false')}
document.addEventListener('DOMContentLoaded',function(){const painelNotificacoes=document.getElementById('notificationPanel');if(painelNotificacoes)painelNotificacoes.addEventListener('click',function(event){event.stopPropagation()});document.addEventListener('click',fecharNotificacoes);document.addEventListener('keydown',function(event){if(event.key==='Escape')fecharNotificacoes()});const app=document.querySelector('.app');if(app){let recolhido=false;try{recolhido=localStorage.getItem('inpactaMenuRecolhido')==='1'}catch(e){}app.classList.toggle('menu-collapsed',recolhido);atualizarBotaoMenu(recolhido)}const perfilRapido=document.getElementById('perfilRapido');const secretariaPerfilWrap=document.getElementById('secretariaPerfilWrap');if(perfilRapido&&secretariaPerfilWrap){const formularioPerfil=perfilRapido.closest('form');const atualizarSecretariaPerfil=()=>{const secretariaAtiva=perfilRapido.value==='secretaria';secretariaPerfilWrap.hidden=!secretariaAtiva;if(formularioPerfil){formularioPerfil.classList.toggle('has-secretaria',secretariaAtiva);formularioPerfil.classList.toggle('compact',!secretariaAtiva)}};perfilRapido.addEventListener('change',atualizarSecretariaPerfil);atualizarSecretariaPerfil()}const secretaria=document.getElementById('reqSecretaria');const departamento=document.getElementById('reqDepartamento');if(secretaria&&departamento){const filtrar=()=>{const id=secretaria.value;Array.from(departamento.options).forEach((op,i)=>{if(i===0)return;op.hidden=!!id&&op.dataset.secretaria!==id;if(op.hidden&&op.selected)departamento.value=''})};secretaria.addEventListener('change',filtrar);filtrar()}});
function imprimirWorkflow(){const tituloOriginal=document.title;document.title='INPACTA - <?=h(match($view){'dashboard'=>'Dashboard Situacional','municipios'=>'Municipios','documentos'=>'Documentos','relatorios'=>'Relatorios','historico'=>'Historico','configuracoes'=>'Configuracoes',default=>'Workflow'})?> - <?=h($municipioNome)?> - <?=date('d-m-Y')?>';window.setTimeout(function(){window.print();window.setTimeout(function(){document.title=tituloOriginal},300)},150)}
</script>
</body>
</html>
