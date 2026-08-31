-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 31/08/2026 às 15:15
-- Versão do servidor: 10.4.13-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `plataforma_v5_limpa`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `arquivamentos_etapa`
--

CREATE TABLE `arquivamentos_etapa` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `municipio_id` bigint(20) UNSIGNED NOT NULL,
  `etapa_numero` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `nome` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `versao_pacote` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `arquivo_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `arquivo_nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `checksum_sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tamanho` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `resumo_json` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manifesto_json` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arquivado_por_usuario_id` bigint(20) UNSIGNED DEFAULT NULL,
  `arquivado_em` datetime NOT NULL,
  `criado_em` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `auditoria`
--

CREATE TABLE `auditoria` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `municipio_id` bigint(20) UNSIGNED DEFAULT NULL,
  `usuario_id` bigint(20) UNSIGNED DEFAULT NULL,
  `usuario_nome` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `usuario_email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `evento` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoria` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OPERACIONAL',
  `severidade` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INFO',
  `sucesso` tinyint(1) NOT NULL DEFAULT 1,
  `detalhes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `metodo` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `rota` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `referer` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `session_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `contexto_json` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `criado_em` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `auditoria`
--

INSERT INTO `auditoria` (`id`, `municipio_id`, `usuario_id`, `usuario_nome`, `usuario_email`, `evento`, `categoria`, `severidade`, `sucesso`, `detalhes`, `ip`, `user_agent`, `metodo`, `rota`, `referer`, `session_hash`, `contexto_json`, `criado_em`) VALUES
(1, NULL, 1, 'Administrador Stratelli', 'caio.toyoda@stratelli.com.br', 'LOGIN_SUCESSO', 'SEGURANCA', 'INFO', 1, 'Autenticação realizada', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', 'POST', '/login', 'http://plataforma.local/login', '735311a5b4bd3743522ff5f04ed92c0221611b2fb37f99daac794de5551a1fca', NULL, '2026-08-26 14:01:24'),
(2, NULL, 1, 'Administrador Stratelli', 'caio.toyoda@stratelli.com.br', 'SESSAO_EXPIRADA', 'SEGURANCA', 'ATENCAO', 1, 'Sessão expirada por política de segurança', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', 'GET', '/admin/auditoria', 'http://plataforma.local/admin/usuarios', '735311a5b4bd3743522ff5f04ed92c0221611b2fb37f99daac794de5551a1fca', NULL, '2026-08-28 18:42:29'),
(3, NULL, 1, 'Administrador Stratelli', 'caio.toyoda@stratelli.com.br', 'LOGIN_SUCESSO', 'SEGURANCA', 'INFO', 1, 'Autenticação realizada', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', 'POST', '/login', 'http://plataforma.local/login', '6ddc206b9d9d547c3bf95fd925214d215c808556c24b131691312326f8149be7', NULL, '2026-08-28 18:42:32'),
(4, NULL, 1, 'Administrador Stratelli', 'caio.toyoda@stratelli.com.br', 'SESSAO_EXPIRADA', 'SEGURANCA', 'ATENCAO', 1, 'Sessão expirada por política de segurança', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', 'GET', '/admin/municipios', 'http://plataforma.local/admin', '6ddc206b9d9d547c3bf95fd925214d215c808556c24b131691312326f8149be7', NULL, '2026-08-31 09:49:25'),
(5, NULL, 1, 'Administrador Stratelli', 'caio.toyoda@stratelli.com.br', 'LOGIN_SUCESSO', 'SEGURANCA', 'INFO', 1, 'Autenticação realizada', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', 'POST', '/login', 'http://plataforma.local/login', '8c4dfc534a1fd4511d458e925407fa67f1c75b4979a745a48933f4e9c04fd3ca', NULL, '2026-08-31 09:49:32');

--
-- Acionadores `auditoria`
--
DELIMITER $$
CREATE TRIGGER `trg_auditoria_no_delete` BEFORE DELETE ON `auditoria` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Auditoria: exclusão física não permitida.'
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_auditoria_no_update` BEFORE UPDATE ON `auditoria` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Auditoria: alteração de evento auditado não permitida.'
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `camadas_territoriais`
--

CREATE TABLE `camadas_territoriais` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `municipio_id` bigint(20) UNSIGNED NOT NULL,
  `nome` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `categoria` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `tipo_geometria` enum('POINT','LINESTRING','POLYGON','MIXED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'POINT',
  `secretaria_id` bigint(20) UNSIGNED DEFAULT NULL,
  `icone` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pin',
  `cor` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#176fdd',
  `ordem` int(11) NOT NULL DEFAULT 1,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL,
  `atualizado_em` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `cronograma_fases`
--

CREATE TABLE `cronograma_fases` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `municipio_id` bigint(20) UNSIGNED NOT NULL,
  `fase_id` bigint(20) UNSIGNED NOT NULL,
  `data_conclusao_real` date NOT NULL,
  `concluido_por_usuario_id` bigint(20) UNSIGNED DEFAULT NULL,
  `observacao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('ENCERRADA','REABERTA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ENCERRADA',
  `snapshot_documental` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `snapshot_sha256` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `encerrado_em` datetime DEFAULT NULL,
  `reaberto_por_usuario_id` bigint(20) UNSIGNED DEFAULT NULL,
  `reaberto_em` datetime DEFAULT NULL,
  `motivo_reabertura` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `criado_em` datetime NOT NULL,
  `atualizado_em` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `cronograma_processos`
--

CREATE TABLE `cronograma_processos` (
  `municipio_id` bigint(20) UNSIGNED NOT NULL,
  `data_inicio` date NOT NULL,
  `criado_em` datetime NOT NULL,
  `atualizado_em` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `departamentos`
--

CREATE TABLE `departamentos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `municipio_id` bigint(20) UNSIGNED NOT NULL,
  `secretaria_id` bigint(20) UNSIGNED NOT NULL,
  `nome` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sigla` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL,
  `atualizado_em` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `documentos_enviados`
--

CREATE TABLE `documentos_enviados` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `municipio_id` bigint(20) UNSIGNED NOT NULL,
  `requisito_id` bigint(20) UNSIGNED NOT NULL,
  `documento_anterior_id` bigint(20) UNSIGNED DEFAULT NULL,
  `versao` int(11) NOT NULL DEFAULT 1,
  `arquivo_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `arquivo_salvo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tamanho` bigint(20) NOT NULL DEFAULT 0,
  `mime_type` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checksum_sha256` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacao_envio` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('AGUARDANDO','APROVADO','CORRECAO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'AGUARDANDO',
  `observacao_validacao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `enviado_por_usuario_id` bigint(20) UNSIGNED DEFAULT NULL,
  `enviado_em` datetime NOT NULL,
  `validado_por_usuario_id` bigint(20) UNSIGNED DEFAULT NULL,
  `validado_em` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Acionadores `documentos_enviados`
--
DELIMITER $$
CREATE TRIGGER `trg_documentos_enviados_no_delete` BEFORE DELETE ON `documentos_enviados` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Documento auditado: exclusão física não permitida.'
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `fases`
--

CREATE TABLE `fases` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `municipio_id` bigint(20) UNSIGNED NOT NULL,
  `ordem` int(11) NOT NULL,
  `codigo` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `aba` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `responsavel` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `dia_inicio` int(11) NOT NULL DEFAULT 1,
  `dia_fim` int(11) NOT NULL DEFAULT 1,
  `entregavel` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `criterio` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exclusivo_stratelli` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL,
  `atualizado_em` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `fase_secretarias`
--

CREATE TABLE `fase_secretarias` (
  `municipio_id` bigint(20) UNSIGNED NOT NULL,
  `fase_id` bigint(20) UNSIGNED NOT NULL,
  `secretaria_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `historico_documentos`
--

CREATE TABLE `historico_documentos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `municipio_id` bigint(20) UNSIGNED NOT NULL,
  `fase_id` bigint(20) UNSIGNED DEFAULT NULL,
  `requisito_id` bigint(20) UNSIGNED DEFAULT NULL,
  `documento_id` bigint(20) UNSIGNED DEFAULT NULL,
  `usuario_id` bigint(20) UNSIGNED DEFAULT NULL,
  `evento` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_arquivo` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `arquivo_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `arquivo_salvo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `arquivo_anterior_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `tamanho` bigint(20) NOT NULL DEFAULT 0,
  `mime_type` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `checksum_sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `versao` int(11) DEFAULT NULL,
  `motivo` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ip` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `criado_em` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Acionadores `historico_documentos`
--
DELIMITER $$
CREATE TRIGGER `trg_historico_documentos_no_delete` BEFORE DELETE ON `historico_documentos` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Histórico documental auditado: exclusão física não permitida.'
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `historico_fases`
--

CREATE TABLE `historico_fases` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `municipio_id` bigint(20) UNSIGNED NOT NULL,
  `fase_id` bigint(20) UNSIGNED NOT NULL,
  `cronograma_fase_id` bigint(20) UNSIGNED DEFAULT NULL,
  `evento` enum('ENCERRAMENTO','REABERTURA') COLLATE utf8mb4_unicode_ci NOT NULL,
  `usuario_id` bigint(20) UNSIGNED DEFAULT NULL,
  `data_referencia` date DEFAULT NULL,
  `observacao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `snapshot_documental` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `snapshot_sha256` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `criado_em` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Acionadores `historico_fases`
--
DELIMITER $$
CREATE TRIGGER `trg_historico_fases_no_delete` BEFORE DELETE ON `historico_fases` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Histórico de encerramento de fase: exclusão física não permitida.'
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_historico_fases_no_update` BEFORE UPDATE ON `historico_fases` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Histórico de encerramento de fase: alteração de evento auditado não permitida.'
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `importacoes_estrutura`
--

CREATE TABLE `importacoes_estrutura` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `municipio_id` bigint(20) UNSIGNED NOT NULL,
  `origem_municipio_id` bigint(20) UNSIGNED DEFAULT NULL,
  `origem_tipo` enum('MUNICIPIO','PLANILHA') COLLATE utf8mb4_unicode_ci NOT NULL,
  `arquivo_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estrategia_conflito` enum('IGNORAR','ATUALIZAR') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'IGNORAR',
  `resumo_json` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `itens_json` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('CONCLUIDA','DESFEITA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CONCLUIDA',
  `criado_por_usuario_id` bigint(20) UNSIGNED DEFAULT NULL,
  `criado_em` datetime NOT NULL,
  `desfeita_em` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `modelos_documentos`
--

CREATE TABLE `modelos_documentos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `municipio_id` bigint(20) UNSIGNED NOT NULL,
  `requisito_id` bigint(20) UNSIGNED NOT NULL,
  `modelo_anterior_id` bigint(20) UNSIGNED DEFAULT NULL,
  `versao` int(11) NOT NULL DEFAULT 1,
  `arquivo_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `arquivo_salvo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tamanho` bigint(20) NOT NULL DEFAULT 0,
  `mime_type` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checksum_sha256` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `municipios`
--

CREATE TABLE `municipios` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nome` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uf` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo_ibge` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `populacao` bigint(20) UNSIGNED DEFAULT NULL,
  `area_km2` decimal(12,3) DEFAULT NULL,
  `site_oficial` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `brasao_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `geojson_delimitacao` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inteligencia_territorial_ativa` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('NEGOCIACAO','APRESENTACAO','IMPLANTACAO','ATIVO','SUSPENSO','DESATIVADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'IMPLANTACAO',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL,
  `atualizado_em` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `notificacoes`
--

CREATE TABLE `notificacoes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `usuario_id` bigint(20) UNSIGNED NOT NULL,
  `municipio_id` bigint(20) UNSIGNED DEFAULT NULL,
  `tipo` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icone` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '•',
  `titulo` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mensagem` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `link` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `prioridade` int(11) NOT NULL DEFAULT 100,
  `chave_evento` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lida_em` datetime DEFAULT NULL,
  `criado_em` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `objetos_territoriais`
--

CREATE TABLE `objetos_territoriais` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `municipio_id` bigint(20) UNSIGNED NOT NULL,
  `camada_id` bigint(20) UNSIGNED NOT NULL,
  `secretaria_id` bigint(20) UNSIGNED DEFAULT NULL,
  `departamento_id` bigint(20) UNSIGNED DEFAULT NULL,
  `nome` varchar(220) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `endereco` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` enum('ATIVO','ATENCAO','CRITICO','CONCLUIDO','INATIVO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ATIVO',
  `geometria_geojson` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `atributos_json` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_por_usuario_id` bigint(20) UNSIGNED DEFAULT NULL,
  `atualizado_por_usuario_id` bigint(20) UNSIGNED DEFAULT NULL,
  `criado_em` datetime NOT NULL,
  `atualizado_em` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `parametros_instancia`
--

CREATE TABLE `parametros_instancia` (
  `municipio_id` bigint(20) UNSIGNED NOT NULL,
  `prazo_alerta_dias` int(11) NOT NULL DEFAULT 3,
  `tamanho_maximo_arquivo_mb` int(11) NOT NULL DEFAULT 20,
  `extensoes_permitidas` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pdf,doc,docx,xls,xlsx,odt,ods,rtf,txt,png,jpg,jpeg,webp,csv,zip',
  `notificacoes_ativas` tinyint(1) NOT NULL DEFAULT 1,
  `observacao_envio_obrigatoria` tinyint(1) NOT NULL DEFAULT 0,
  `observacao_aprovacao_obrigatoria` tinyint(1) NOT NULL DEFAULT 0,
  `observacao_encerramento_obrigatoria` tinyint(1) NOT NULL DEFAULT 1,
  `observacao_reabertura_obrigatoria` tinyint(1) NOT NULL DEFAULT 1,
  `nome_etapa_atual` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Etapa 1',
  `cor_primaria` char(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#082A55',
  `cor_secundaria` char(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#176FDD',
  `estilo_decoracao_cabecalho` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'semicirculo',
  `criado_em` datetime NOT NULL,
  `atualizado_em` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `usuario_id` bigint(20) UNSIGNED NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expira_em` datetime NOT NULL,
  `usado_em` datetime DEFAULT NULL,
  `requested_ip` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `requested_user_agent` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `criado_em` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pontos_restauracao_instancia`
--

CREATE TABLE `pontos_restauracao_instancia` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `municipio_id` bigint(20) UNSIGNED NOT NULL,
  `nome` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `origem` enum('SALVO','IMPORTADO','AUTOMATICO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SALVO',
  `arquivo_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `arquivo_nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `checksum_sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tamanho` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `resumo_json` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('DISPONIVEL','REMOVIDO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DISPONIVEL',
  `criado_por_usuario_id` bigint(20) UNSIGNED DEFAULT NULL,
  `criado_em` datetime NOT NULL,
  `restaurado_por_usuario_id` bigint(20) UNSIGNED DEFAULT NULL,
  `restaurado_em` datetime DEFAULT NULL,
  `restauracoes` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `requisitos_documentais`
--

CREATE TABLE `requisitos_documentais` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `municipio_id` bigint(20) UNSIGNED NOT NULL,
  `fase_id` bigint(20) UNSIGNED NOT NULL,
  `secretaria_id` bigint(20) UNSIGNED NOT NULL,
  `departamento_id` bigint(20) UNSIGNED DEFAULT NULL,
  `tipo_documento_id` bigint(20) UNSIGNED NOT NULL,
  `nome` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `perfil_envio` enum('STRATELLI','MUNICIPIO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MUNICIPIO',
  `obrigatorio` tinyint(1) NOT NULL DEFAULT 1,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `ordem` int(11) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL,
  `atualizado_em` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `secretarias`
--

CREATE TABLE `secretarias` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `municipio_id` bigint(20) UNSIGNED NOT NULL,
  `nome` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sigla` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL,
  `atualizado_em` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tentativas_login`
--

CREATE TABLE `tentativas_login` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `usuario_id` bigint(20) UNSIGNED DEFAULT NULL,
  `email_normalizado` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ip` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sucesso` tinyint(1) NOT NULL DEFAULT 0,
  `motivo` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `criado_em` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `tentativas_login`
--

INSERT INTO `tentativas_login` (`id`, `usuario_id`, `email_normalizado`, `ip`, `user_agent`, `sucesso`, `motivo`, `criado_em`) VALUES
(1, 1, 'caio.toyoda@stratelli.com.br', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', 1, 'SUCESSO', '2026-08-26 14:01:24'),
(2, 1, 'caio.toyoda@stratelli.com.br', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', 1, 'SUCESSO', '2026-08-28 18:42:32'),
(3, 1, 'caio.toyoda@stratelli.com.br', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', 1, 'SUCESSO', '2026-08-31 09:49:32');

-- --------------------------------------------------------

--
-- Estrutura para tabela `tipos_documento`
--

CREATE TABLE `tipos_documento` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `municipio_id` bigint(20) UNSIGNED NOT NULL,
  `nome` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `extensoes` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pdf,doc,docx,xls,xlsx,odt,ods,rtf,txt',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL,
  `atualizado_em` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `municipio_id` bigint(20) UNSIGNED DEFAULT NULL,
  `secretaria_id` bigint(20) UNSIGNED DEFAULT NULL,
  `departamento_id` bigint(20) UNSIGNED DEFAULT NULL,
  `nome` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `senha_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `grupo` enum('ADMINISTRADOR','GESTOR','USUARIO') COLLATE utf8mb4_unicode_ci NOT NULL,
  `administrador_plataforma` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `auth_version` int(11) NOT NULL DEFAULT 1,
  `ultimo_login_em` datetime DEFAULT NULL,
  `senha_alterada_em` datetime DEFAULT NULL,
  `criado_em` datetime NOT NULL,
  `atualizado_em` datetime NOT NULL
) ;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `municipio_id`, `secretaria_id`, `departamento_id`, `nome`, `email`, `senha_hash`, `grupo`, `administrador_plataforma`, `ativo`, `auth_version`, `ultimo_login_em`, `senha_alterada_em`, `criado_em`, `atualizado_em`) VALUES
(1, NULL, NULL, NULL, 'Administrador Stratelli', 'caio.toyoda@stratelli.com.br', '$argon2id$v=19$m=65536,t=4,p=1$ZGVjMy8yaFNOTkIxc1J4eA$Xr2iKQhZtmXbIeIWuP7vNgVxilUBfiIvw3z9+ddAYQ8', 'ADMINISTRADOR', 1, 1, 1, '2026-08-31 09:49:32', NULL, '2026-08-26 14:00:55', '2026-08-26 14:00:55');

-- --------------------------------------------------------

--
-- Estrutura para tabela `vinculos_territoriais`
--

CREATE TABLE `vinculos_territoriais` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `municipio_id` bigint(20) UNSIGNED NOT NULL,
  `objeto_territorial_id` bigint(20) UNSIGNED NOT NULL,
  `fase_id` bigint(20) UNSIGNED DEFAULT NULL,
  `requisito_id` bigint(20) UNSIGNED DEFAULT NULL,
  `documento_id` bigint(20) UNSIGNED DEFAULT NULL,
  `tipo_vinculo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'FASE',
  `observacao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `criado_por_usuario_id` bigint(20) UNSIGNED DEFAULT NULL,
  `criado_em` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `arquivamentos_etapa`
--
ALTER TABLE `arquivamentos_etapa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_arquivamento_etapa` (`municipio_id`,`etapa_numero`),
  ADD KEY `idx_arquivamento_municipio` (`municipio_id`,`etapa_numero`,`arquivado_em`),
  ADD KEY `fk_arquivamento_usuario` (`arquivado_por_usuario_id`);

--
-- Índices de tabela `auditoria`
--
ALTER TABLE `auditoria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_auditoria_tenant_data` (`municipio_id`,`criado_em`),
  ADD KEY `fk_auditoria_usuario` (`usuario_id`);

--
-- Índices de tabela `camadas_territoriais`
--
ALTER TABLE `camadas_territoriais`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_camada_territorial_nome` (`municipio_id`,`nome`),
  ADD UNIQUE KEY `uq_camada_territorial_id_municipio` (`id`,`municipio_id`),
  ADD KEY `idx_camada_territorial_tenant` (`municipio_id`,`ativo`,`ordem`),
  ADD KEY `fk_camada_territorial_secretaria` (`secretaria_id`,`municipio_id`);

--
-- Índices de tabela `cronograma_fases`
--
ALTER TABLE `cronograma_fases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_crono_fase` (`municipio_id`,`fase_id`),
  ADD KEY `idx_crono_fase_status` (`municipio_id`,`status`,`fase_id`),
  ADD KEY `fk_crono_fase` (`fase_id`,`municipio_id`),
  ADD KEY `fk_crono_usuario` (`concluido_por_usuario_id`),
  ADD KEY `fk_crono_reaberto_usuario` (`reaberto_por_usuario_id`);

--
-- Índices de tabela `cronograma_processos`
--
ALTER TABLE `cronograma_processos`
  ADD PRIMARY KEY (`municipio_id`);

--
-- Índices de tabela `departamentos`
--
ALTER TABLE `departamentos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_departamento_municipio_nome` (`municipio_id`,`secretaria_id`,`nome`),
  ADD UNIQUE KEY `uq_departamento_id_municipio` (`id`,`municipio_id`),
  ADD KEY `fk_departamento_secretaria` (`secretaria_id`,`municipio_id`);

--
-- Índices de tabela `documentos_enviados`
--
ALTER TABLE `documentos_enviados`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_documento_versao` (`municipio_id`,`requisito_id`,`versao`),
  ADD UNIQUE KEY `uq_documento_id_municipio` (`id`,`municipio_id`),
  ADD KEY `idx_documento_tenant_req` (`municipio_id`,`requisito_id`,`id`),
  ADD KEY `idx_documento_checksum` (`municipio_id`,`checksum_sha256`),
  ADD KEY `fk_doc_req` (`requisito_id`,`municipio_id`),
  ADD KEY `fk_doc_anterior` (`documento_anterior_id`),
  ADD KEY `fk_doc_enviado_usuario` (`enviado_por_usuario_id`),
  ADD KEY `fk_doc_validado_usuario` (`validado_por_usuario_id`);

--
-- Índices de tabela `fases`
--
ALTER TABLE `fases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_fase_ordem` (`municipio_id`,`ordem`),
  ADD UNIQUE KEY `uq_fase_codigo` (`municipio_id`,`codigo`),
  ADD UNIQUE KEY `uq_fase_id_municipio` (`id`,`municipio_id`);

--
-- Índices de tabela `fase_secretarias`
--
ALTER TABLE `fase_secretarias`
  ADD PRIMARY KEY (`municipio_id`,`fase_id`,`secretaria_id`),
  ADD KEY `fk_fs_fase` (`fase_id`,`municipio_id`),
  ADD KEY `fk_fs_secretaria` (`secretaria_id`,`municipio_id`);

--
-- Índices de tabela `historico_documentos`
--
ALTER TABLE `historico_documentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_historico_tenant_data` (`municipio_id`,`criado_em`),
  ADD KEY `idx_historico_documento` (`documento_id`),
  ADD KEY `fk_hist_fase` (`fase_id`,`municipio_id`),
  ADD KEY `fk_hist_req` (`requisito_id`,`municipio_id`),
  ADD KEY `fk_hist_usuario` (`usuario_id`);

--
-- Índices de tabela `historico_fases`
--
ALTER TABLE `historico_fases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hist_fase_tenant` (`municipio_id`,`fase_id`,`criado_em`),
  ADD KEY `fk_hist_fase_fase` (`fase_id`,`municipio_id`),
  ADD KEY `fk_hist_fase_crono` (`cronograma_fase_id`),
  ADD KEY `fk_hist_fase_usuario` (`usuario_id`);

--
-- Índices de tabela `importacoes_estrutura`
--
ALTER TABLE `importacoes_estrutura`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_import_estrutura_municipio` (`municipio_id`,`status`,`criado_em`),
  ADD KEY `fk_import_estrutura_origem` (`origem_municipio_id`),
  ADD KEY `fk_import_estrutura_usuario` (`criado_por_usuario_id`);

--
-- Índices de tabela `modelos_documentos`
--
ALTER TABLE `modelos_documentos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_modelo_versao` (`municipio_id`,`requisito_id`,`versao`),
  ADD KEY `idx_modelo_tenant_req` (`municipio_id`,`requisito_id`,`ativo`),
  ADD KEY `idx_modelo_checksum` (`municipio_id`,`checksum_sha256`),
  ADD KEY `fk_modelo_req` (`requisito_id`,`municipio_id`),
  ADD KEY `fk_modelo_anterior` (`modelo_anterior_id`),
  ADD KEY `fk_modelo_usuario` (`usuario_id`);

--
-- Índices de tabela `municipios`
--
ALTER TABLE `municipios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_municipios_status` (`status`,`ativo`);

--
-- Índices de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_notificacao_usuario_evento` (`usuario_id`,`chave_evento`),
  ADD KEY `idx_notificacao_usuario_lida` (`usuario_id`,`lida_em`,`criado_em`),
  ADD KEY `idx_notificacao_municipio` (`municipio_id`,`criado_em`),
  ADD KEY `idx_notificacao_tipo` (`usuario_id`,`tipo`,`criado_em`);

--
-- Índices de tabela `objetos_territoriais`
--
ALTER TABLE `objetos_territoriais`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_objeto_territorial_id_municipio` (`id`,`municipio_id`),
  ADD KEY `idx_objeto_territorial_tenant_camada` (`municipio_id`,`camada_id`,`ativo`),
  ADD KEY `idx_objeto_territorial_secretaria` (`municipio_id`,`secretaria_id`,`departamento_id`),
  ADD KEY `idx_objeto_territorial_status` (`municipio_id`,`status`,`ativo`),
  ADD KEY `fk_objeto_territorial_camada` (`camada_id`,`municipio_id`),
  ADD KEY `fk_objeto_territorial_secretaria` (`secretaria_id`,`municipio_id`),
  ADD KEY `fk_objeto_territorial_departamento` (`departamento_id`,`municipio_id`),
  ADD KEY `fk_objeto_territorial_criador` (`criado_por_usuario_id`),
  ADD KEY `fk_objeto_territorial_atualizador` (`atualizado_por_usuario_id`);

--
-- Índices de tabela `parametros_instancia`
--
ALTER TABLE `parametros_instancia`
  ADD PRIMARY KEY (`municipio_id`);

--
-- Índices de tabela `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `idx_reset_user` (`usuario_id`,`usado_em`,`expira_em`);

--
-- Índices de tabela `pontos_restauracao_instancia`
--
ALTER TABLE `pontos_restauracao_instancia`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ponto_restauracao_municipio` (`municipio_id`,`status`,`criado_em`),
  ADD KEY `fk_ponto_restauracao_criador` (`criado_por_usuario_id`),
  ADD KEY `fk_ponto_restauracao_restaurador` (`restaurado_por_usuario_id`);

--
-- Índices de tabela `requisitos_documentais`
--
ALTER TABLE `requisitos_documentais`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_req_id_municipio` (`id`,`municipio_id`),
  ADD KEY `idx_req_tenant_fase` (`municipio_id`,`fase_id`,`ativo`),
  ADD KEY `fk_req_fase` (`fase_id`,`municipio_id`),
  ADD KEY `fk_req_secretaria` (`secretaria_id`,`municipio_id`),
  ADD KEY `fk_req_departamento` (`departamento_id`,`municipio_id`),
  ADD KEY `fk_req_tipo` (`tipo_documento_id`,`municipio_id`);

--
-- Índices de tabela `secretarias`
--
ALTER TABLE `secretarias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_secretaria_municipio_nome` (`municipio_id`,`nome`),
  ADD UNIQUE KEY `uq_secretaria_id_municipio` (`id`,`municipio_id`);

--
-- Índices de tabela `tentativas_login`
--
ALTER TABLE `tentativas_login`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_login_email_data` (`email_normalizado`,`criado_em`),
  ADD KEY `idx_login_ip_data` (`ip`,`criado_em`),
  ADD KEY `fk_tentativa_usuario` (`usuario_id`);

--
-- Índices de tabela `tipos_documento`
--
ALTER TABLE `tipos_documento`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tipo_doc_nome` (`municipio_id`,`nome`),
  ADD UNIQUE KEY `uq_tipo_doc_id_municipio` (`id`,`municipio_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_usuarios_tenant` (`municipio_id`,`grupo`,`ativo`),
  ADD KEY `fk_usuario_secretaria` (`secretaria_id`,`municipio_id`),
  ADD KEY `fk_usuario_departamento` (`departamento_id`,`municipio_id`);

--
-- Índices de tabela `vinculos_territoriais`
--
ALTER TABLE `vinculos_territoriais`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vinculo_territorial_objeto` (`municipio_id`,`objeto_territorial_id`,`tipo_vinculo`),
  ADD KEY `idx_vinculo_territorial_fase` (`municipio_id`,`fase_id`),
  ADD KEY `fk_vinculo_territorial_objeto` (`objeto_territorial_id`,`municipio_id`),
  ADD KEY `fk_vinculo_territorial_fase` (`fase_id`,`municipio_id`),
  ADD KEY `fk_vinculo_territorial_requisito` (`requisito_id`,`municipio_id`),
  ADD KEY `fk_vinculo_territorial_documento` (`documento_id`,`municipio_id`),
  ADD KEY `fk_vinculo_territorial_usuario` (`criado_por_usuario_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `arquivamentos_etapa`
--
ALTER TABLE `arquivamentos_etapa`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `auditoria`
--
ALTER TABLE `auditoria`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `camadas_territoriais`
--
ALTER TABLE `camadas_territoriais`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `cronograma_fases`
--
ALTER TABLE `cronograma_fases`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `departamentos`
--
ALTER TABLE `departamentos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `documentos_enviados`
--
ALTER TABLE `documentos_enviados`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `fases`
--
ALTER TABLE `fases`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `historico_documentos`
--
ALTER TABLE `historico_documentos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `historico_fases`
--
ALTER TABLE `historico_fases`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `importacoes_estrutura`
--
ALTER TABLE `importacoes_estrutura`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `modelos_documentos`
--
ALTER TABLE `modelos_documentos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `municipios`
--
ALTER TABLE `municipios`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `objetos_territoriais`
--
ALTER TABLE `objetos_territoriais`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pontos_restauracao_instancia`
--
ALTER TABLE `pontos_restauracao_instancia`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `requisitos_documentais`
--
ALTER TABLE `requisitos_documentais`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `secretarias`
--
ALTER TABLE `secretarias`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `tentativas_login`
--
ALTER TABLE `tentativas_login`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `tipos_documento`
--
ALTER TABLE `tipos_documento`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `vinculos_territoriais`
--
ALTER TABLE `vinculos_territoriais`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `arquivamentos_etapa`
--
ALTER TABLE `arquivamentos_etapa`
  ADD CONSTRAINT `fk_arquivamento_municipio` FOREIGN KEY (`municipio_id`) REFERENCES `municipios` (`id`),
  ADD CONSTRAINT `fk_arquivamento_usuario` FOREIGN KEY (`arquivado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `auditoria`
--
ALTER TABLE `auditoria`
  ADD CONSTRAINT `fk_auditoria_municipio` FOREIGN KEY (`municipio_id`) REFERENCES `municipios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_auditoria_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `camadas_territoriais`
--
ALTER TABLE `camadas_territoriais`
  ADD CONSTRAINT `fk_camada_territorial_municipio` FOREIGN KEY (`municipio_id`) REFERENCES `municipios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_camada_territorial_secretaria` FOREIGN KEY (`secretaria_id`,`municipio_id`) REFERENCES `secretarias` (`id`, `municipio_id`);

--
-- Restrições para tabelas `cronograma_fases`
--
ALTER TABLE `cronograma_fases`
  ADD CONSTRAINT `fk_crono_fase` FOREIGN KEY (`fase_id`,`municipio_id`) REFERENCES `fases` (`id`, `municipio_id`),
  ADD CONSTRAINT `fk_crono_reaberto_usuario` FOREIGN KEY (`reaberto_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_crono_usuario` FOREIGN KEY (`concluido_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `cronograma_processos`
--
ALTER TABLE `cronograma_processos`
  ADD CONSTRAINT `fk_crono_processo_municipio` FOREIGN KEY (`municipio_id`) REFERENCES `municipios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `departamentos`
--
ALTER TABLE `departamentos`
  ADD CONSTRAINT `fk_departamento_secretaria` FOREIGN KEY (`secretaria_id`,`municipio_id`) REFERENCES `secretarias` (`id`, `municipio_id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `documentos_enviados`
--
ALTER TABLE `documentos_enviados`
  ADD CONSTRAINT `fk_doc_anterior` FOREIGN KEY (`documento_anterior_id`) REFERENCES `documentos_enviados` (`id`),
  ADD CONSTRAINT `fk_doc_enviado_usuario` FOREIGN KEY (`enviado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_doc_req` FOREIGN KEY (`requisito_id`,`municipio_id`) REFERENCES `requisitos_documentais` (`id`, `municipio_id`),
  ADD CONSTRAINT `fk_doc_validado_usuario` FOREIGN KEY (`validado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `fases`
--
ALTER TABLE `fases`
  ADD CONSTRAINT `fk_fase_municipio` FOREIGN KEY (`municipio_id`) REFERENCES `municipios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `fase_secretarias`
--
ALTER TABLE `fase_secretarias`
  ADD CONSTRAINT `fk_fs_fase` FOREIGN KEY (`fase_id`,`municipio_id`) REFERENCES `fases` (`id`, `municipio_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fs_secretaria` FOREIGN KEY (`secretaria_id`,`municipio_id`) REFERENCES `secretarias` (`id`, `municipio_id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `historico_documentos`
--
ALTER TABLE `historico_documentos`
  ADD CONSTRAINT `fk_hist_documento` FOREIGN KEY (`documento_id`) REFERENCES `documentos_enviados` (`id`),
  ADD CONSTRAINT `fk_hist_fase` FOREIGN KEY (`fase_id`,`municipio_id`) REFERENCES `fases` (`id`, `municipio_id`),
  ADD CONSTRAINT `fk_hist_municipio` FOREIGN KEY (`municipio_id`) REFERENCES `municipios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_hist_req` FOREIGN KEY (`requisito_id`,`municipio_id`) REFERENCES `requisitos_documentais` (`id`, `municipio_id`),
  ADD CONSTRAINT `fk_hist_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `historico_fases`
--
ALTER TABLE `historico_fases`
  ADD CONSTRAINT `fk_hist_fase_crono` FOREIGN KEY (`cronograma_fase_id`) REFERENCES `cronograma_fases` (`id`),
  ADD CONSTRAINT `fk_hist_fase_fase` FOREIGN KEY (`fase_id`,`municipio_id`) REFERENCES `fases` (`id`, `municipio_id`),
  ADD CONSTRAINT `fk_hist_fase_municipio` FOREIGN KEY (`municipio_id`) REFERENCES `municipios` (`id`),
  ADD CONSTRAINT `fk_hist_fase_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `importacoes_estrutura`
--
ALTER TABLE `importacoes_estrutura`
  ADD CONSTRAINT `fk_import_estrutura_municipio` FOREIGN KEY (`municipio_id`) REFERENCES `municipios` (`id`),
  ADD CONSTRAINT `fk_import_estrutura_origem` FOREIGN KEY (`origem_municipio_id`) REFERENCES `municipios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_import_estrutura_usuario` FOREIGN KEY (`criado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `modelos_documentos`
--
ALTER TABLE `modelos_documentos`
  ADD CONSTRAINT `fk_modelo_anterior` FOREIGN KEY (`modelo_anterior_id`) REFERENCES `modelos_documentos` (`id`),
  ADD CONSTRAINT `fk_modelo_req` FOREIGN KEY (`requisito_id`,`municipio_id`) REFERENCES `requisitos_documentais` (`id`, `municipio_id`),
  ADD CONSTRAINT `fk_modelo_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `notificacoes`
--
ALTER TABLE `notificacoes`
  ADD CONSTRAINT `fk_notificacao_municipio` FOREIGN KEY (`municipio_id`) REFERENCES `municipios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notificacao_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `objetos_territoriais`
--
ALTER TABLE `objetos_territoriais`
  ADD CONSTRAINT `fk_objeto_territorial_atualizador` FOREIGN KEY (`atualizado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_objeto_territorial_camada` FOREIGN KEY (`camada_id`,`municipio_id`) REFERENCES `camadas_territoriais` (`id`, `municipio_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_objeto_territorial_criador` FOREIGN KEY (`criado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_objeto_territorial_departamento` FOREIGN KEY (`departamento_id`,`municipio_id`) REFERENCES `departamentos` (`id`, `municipio_id`),
  ADD CONSTRAINT `fk_objeto_territorial_municipio` FOREIGN KEY (`municipio_id`) REFERENCES `municipios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_objeto_territorial_secretaria` FOREIGN KEY (`secretaria_id`,`municipio_id`) REFERENCES `secretarias` (`id`, `municipio_id`);

--
-- Restrições para tabelas `parametros_instancia`
--
ALTER TABLE `parametros_instancia`
  ADD CONSTRAINT `fk_parametros_instancia_municipio` FOREIGN KEY (`municipio_id`) REFERENCES `municipios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `fk_reset_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `pontos_restauracao_instancia`
--
ALTER TABLE `pontos_restauracao_instancia`
  ADD CONSTRAINT `fk_ponto_restauracao_criador` FOREIGN KEY (`criado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ponto_restauracao_municipio` FOREIGN KEY (`municipio_id`) REFERENCES `municipios` (`id`),
  ADD CONSTRAINT `fk_ponto_restauracao_restaurador` FOREIGN KEY (`restaurado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `requisitos_documentais`
--
ALTER TABLE `requisitos_documentais`
  ADD CONSTRAINT `fk_req_departamento` FOREIGN KEY (`departamento_id`,`municipio_id`) REFERENCES `departamentos` (`id`, `municipio_id`),
  ADD CONSTRAINT `fk_req_fase` FOREIGN KEY (`fase_id`,`municipio_id`) REFERENCES `fases` (`id`, `municipio_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_req_secretaria` FOREIGN KEY (`secretaria_id`,`municipio_id`) REFERENCES `secretarias` (`id`, `municipio_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_req_tipo` FOREIGN KEY (`tipo_documento_id`,`municipio_id`) REFERENCES `tipos_documento` (`id`, `municipio_id`);

--
-- Restrições para tabelas `secretarias`
--
ALTER TABLE `secretarias`
  ADD CONSTRAINT `fk_secretaria_municipio` FOREIGN KEY (`municipio_id`) REFERENCES `municipios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `tentativas_login`
--
ALTER TABLE `tentativas_login`
  ADD CONSTRAINT `fk_tentativa_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `tipos_documento`
--
ALTER TABLE `tipos_documento`
  ADD CONSTRAINT `fk_tipo_doc_municipio` FOREIGN KEY (`municipio_id`) REFERENCES `municipios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuario_departamento` FOREIGN KEY (`departamento_id`,`municipio_id`) REFERENCES `departamentos` (`id`, `municipio_id`),
  ADD CONSTRAINT `fk_usuario_municipio` FOREIGN KEY (`municipio_id`) REFERENCES `municipios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_usuario_secretaria` FOREIGN KEY (`secretaria_id`,`municipio_id`) REFERENCES `secretarias` (`id`, `municipio_id`);

--
-- Restrições para tabelas `vinculos_territoriais`
--
ALTER TABLE `vinculos_territoriais`
  ADD CONSTRAINT `fk_vinculo_territorial_documento` FOREIGN KEY (`documento_id`,`municipio_id`) REFERENCES `documentos_enviados` (`id`, `municipio_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vinculo_territorial_fase` FOREIGN KEY (`fase_id`,`municipio_id`) REFERENCES `fases` (`id`, `municipio_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vinculo_territorial_municipio` FOREIGN KEY (`municipio_id`) REFERENCES `municipios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vinculo_territorial_objeto` FOREIGN KEY (`objeto_territorial_id`,`municipio_id`) REFERENCES `objetos_territoriais` (`id`, `municipio_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vinculo_territorial_requisito` FOREIGN KEY (`requisito_id`,`municipio_id`) REFERENCES `requisitos_documentais` (`id`, `municipio_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vinculo_territorial_usuario` FOREIGN KEY (`criado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
