<?php
namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use PDO;
use RuntimeException;

/** Um marco documental por município/instância operacional na arquitetura atual. */
final class FormalStartService
{
    private PDO $pdo;
    public function __construct() { $this->pdo = Database::connection(); }

    public function get(int $mid): ?array
    {
        $q = $this->pdo->prepare('SELECT * FROM pge_inicios_formais WHERE municipio_id=? AND status="ATIVO" LIMIT 1');
        $q->execute([$mid]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function register(int $mid, string $date, string $reference, string $observation): void
    {
        if (!Auth::isPlatformAdmin()) throw new RuntimeException('Somente administradores da Stratelli podem formalizar o início.');
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date) throw new RuntimeException('Informe uma data de início válida.');
        if ($date > date('Y-m-d')) throw new RuntimeException('A data do início formal não pode ser futura.');
        $reference = trim($reference);
        if ($reference === '' || mb_strlen($reference) > 180) throw new RuntimeException('Informe a identificação do documento (até 180 caracteres).');
        if ($this->get($mid)) throw new RuntimeException('O início formal já foi registrado; não é permitido sobrescrevê-lo. Solicite retificação auditada.');
        $file = (new FileStorage())->storeUpload('documento_inicio', 'marcos_formais/'.$mid, 'inicio_formal', ['pdf'], 20971520);
        try {
            if (($file['mime'] ?? '') !== 'application/pdf') throw new RuntimeException('O documento deve ser um PDF válido.');
            $this->pdo->beginTransaction();
            $st=$this->pdo->prepare('INSERT INTO pge_inicios_formais (municipio_id,data_inicio,referencia,observacao,arquivo_original,arquivo_salvo,arquivo_mime,arquivo_tamanho,checksum_sha256,usuario_id,status,criado_em) VALUES (?,?,?,?,?,?,?,?,?, ?,"ATIVO",NOW())');
            $st->execute([$mid,$date,$reference,mb_substr(trim($observation),0,2000),$file['original'],$file['path'],$file['mime'],$file['size'],$file['checksum'],(int)(Auth::id()??0)]);
            // Preserva compatibilidade com os módulos que consultam o cronograma antigo.
            $st=$this->pdo->prepare('INSERT INTO cronograma_processos(municipio_id,data_inicio,criado_em,atualizado_em) VALUES(?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE data_inicio=VALUES(data_inicio),atualizado_em=NOW()');
            $st->execute([$mid,$date]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            (new FileStorage())->discard($file['path']);
            throw $e;
        }
        Audit::log('INICIO_FORMAL_REGISTRADO','Início formal registrado em '.$date.' pelo documento '.$reference,$mid,['categoria'=>'ADMINISTRACAO','severidade'=>'ATENCAO','checksum_sha256'=>$file['checksum']]);
    }

    public function download(int $mid): never
    {
        $r=$this->get($mid);
        if (!$r) throw new RuntimeException('Nenhum documento formal de início cadastrado.');
        (new FileStorage())->send((string)$r['arquivo_salvo'],(string)$r['arquivo_original'],(string)$r['checksum_sha256'],'application/pdf');
    }
}
