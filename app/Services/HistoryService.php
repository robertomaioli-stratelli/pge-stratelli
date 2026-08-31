<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Tenant;

final class HistoryService
{
    public function log(array $data): void
    {
        $pdo=Database::connection();
        $municipioId=(int)($data['municipio_id']??Tenant::id()??0);
        if(!$municipioId) return;
        $sql='INSERT INTO historico_documentos
            (municipio_id,fase_id,requisito_id,documento_id,usuario_id,evento,tipo_arquivo,arquivo_original,arquivo_salvo,arquivo_anterior_original,tamanho,mime_type,checksum_sha256,versao,motivo,status,ip,user_agent,criado_em)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())';
        $pdo->prepare($sql)->execute([
            $municipioId,
            $data['fase_id']??null,
            $data['requisito_id']??null,
            $data['documento_id']??null,
            $data['usuario_id']??Auth::id(),
            $data['evento']??'Registro',
            $data['tipo_arquivo']??'',
            $data['arquivo_original']??'',
            $data['arquivo_salvo']??'',
            $data['arquivo_anterior_original']??'',
            (int)($data['tamanho']??0),
            $data['mime_type']??'',
            $data['checksum_sha256']??'',
            isset($data['versao'])?(int)$data['versao']:null,
            $data['motivo']??null,
            $data['status']??'',
            \App\Core\Audit::ip(),
            substr($_SERVER['HTTP_USER_AGENT']??'',0,500),
        ]);
    }
}
