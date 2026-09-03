<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Response;
use App\Services\FileStorage;

final class MediaController
{

    public function municipioTerritorio(string $id): void
    {
        $mid=(int)$id;$pdo=Database::connection();$st=$pdo->prepare('SELECT id,geojson_delimitacao,inteligencia_territorial_ativa,ativo FROM municipios WHERE id=? LIMIT 1');$st->execute([$mid]);$m=$st->fetch();
        if(!$m||empty($m['geojson_delimitacao']))Response::abort(404,'Delimitação territorial não disponível.');
        $u=Auth::user();if(!$u)Response::abort(401,'Não autenticado.');
        if(!Auth::isPlatformAdmin()&&(int)($m['ativo']??0)!==1)Response::abort(404,'Delimitação territorial não disponível.');
        if(!Auth::isPlatformAdmin()&&(int)($u['municipio_id']??0)!==$mid)Response::abort(403,'Acesso não autorizado.');
        if(!Auth::isPlatformAdmin()&&(int)($m['inteligencia_territorial_ativa']??0)!==1)Response::abort(403,'Inteligência Territorial não ativada para este município.');
        if(session_status()===PHP_SESSION_ACTIVE) session_write_close();
        header('Content-Type: application/geo+json; charset=utf-8');
        header('Cache-Control: private, max-age=300');
        echo (string)$m['geojson_delimitacao'];
        exit;
    }

    public function municipioBrasao(string $id): void
    {
        $mid=(int)$id;$pdo=Database::connection();$st=$pdo->prepare('SELECT id,brasao_path FROM municipios WHERE id=? AND ativo=1 LIMIT 1');$st->execute([$mid]);$m=$st->fetch();
        if(!$m||empty($m['brasao_path']))Response::abort(404,'Brasão não disponível.');
        $u=Auth::user();if(!$u)Response::abort(401,'Não autenticado.');
        if(!Auth::isPlatformAdmin()&&(int)($u['municipio_id']??0)!==$mid)Response::abort(403,'Acesso não autorizado.');
        if(session_status()===PHP_SESSION_ACTIVE) session_write_close();
        (new FileStorage())->sendInline((string)$m['brasao_path']);
    }
}
