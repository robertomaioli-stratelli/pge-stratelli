<?php
use App\Core\Format;
$m=$municipioCadastro;
$hasGeo=!empty($m['geojson_delimitacao']);
$hasBrasao=!empty($m['brasao_path']);
$territorialActive=(int)($m['inteligencia_territorial_ativa']??0)===1;
$statusClass=match((string)$m['status']){
    'ATIVO'=>'active','IMPLANTACAO'=>'implementation','NEGOCIACAO'=>'negotiation','APRESENTACAO'=>'presentation','SUSPENSO'=>'suspended','DESATIVADO'=>'disabled',default=>'implementation'
};
$statusLabel=match((string)$m['status']){
    'ATIVO'=>'Ativo','IMPLANTACAO'=>'Implantação','NEGOCIACAO'=>'Negociação','APRESENTACAO'=>'Apresentação','SUSPENSO'=>'Suspenso','DESATIVADO'=>'Desativado',default=>(string)$m['status']
};
$site=trim((string)($m['site_oficial']??''));
$siteHref=$site!==''&&preg_match('~^https?://~i',$site)?$site:'';
$decoracao=((string)($m['estilo_decoracao_cabecalho']??'semicirculo'))==='nenhuma'?'Sem decoração':'Semicírculo suave';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<div class="module-page municipality-readonly-page">
    <section class="module-hero municipality-readonly-hero">
        <div class="municipality-readonly-hero-main">
            <?php if($hasBrasao):?><img class="municipality-readonly-coat" src="/media/municipios/<?=$m['id']?>/brasao?v=<?=substr(sha1((string)$m['brasao_path']),0,12)?>" loading="eager" decoding="async" alt="Brasão de <?=Format::h($m['nome'])?>"><?php else:?><span class="municipality-readonly-coat placeholder">▥</span><?php endif;?>
            <div><span class="municipality-readonly-eyebrow">Cadastro institucional</span><h1><?=Format::h($m['nome'].' - '.$m['uf'])?></h1><p>Consulta dos dados oficiais cadastrados para esta instância municipal.</p></div>
        </div>
        <div class="municipality-readonly-hero-actions"><span class="municipality-readonly-mode">◉ Somente leitura</span><a class="btn neutral" href="/<?=Format::h($m['slug'])?>/dashboard">← Voltar ao Dashboard</a></div>
    </section>

    <section class="municipality-readonly-note"><span>i</span><div><b>Dados administrados pela Stratelli</b><p>Esta tela é apenas para consulta. Alterações no cadastro institucional e na identidade visual são realizadas pela administração da plataforma.</p></div></section>

    <div class="municipality-readonly-kpis">
        <article><small>Status da instância</small><b class="municipality-readonly-status <?=$statusClass?>"><?=Format::h($statusLabel)?></b><span>Situação cadastral atual</span></article>
        <article><small>Etapa atual</small><b><?=Format::h($m['nome_etapa_atual']?:'Etapa 1')?></b><span>Etapa operacional configurada</span></article>
        <article><small>Secretarias</small><b><?=intval($m['secretarias_ativas'])?></b><span>Unidades ativas</span></article>
        <article><small>Usuários</small><b><?=intval($m['usuarios_ativos'])?></b><span>Contas ativas</span></article>
    </div>

    <div class="municipality-readonly-grid">
        <section class="card municipality-readonly-card">
            <header class="municipality-readonly-card-head"><div><span>01</span><h2>Dados institucionais</h2></div><small>Cadastro oficial da instância</small></header>
            <div class="municipality-readonly-data-grid">
                <div><small>Município</small><b><?=Format::h($m['nome'])?></b></div>
                <div><small>UF</small><b><?=Format::h($m['uf'])?></b></div>
                <div><small>Código IBGE</small><b><?=Format::h($m['codigo_ibge']?:'Não informado')?></b></div>
                <div><small>População</small><b><?=!empty($m['populacao'])?number_format((int)$m['populacao'],0,',','.'):'Não informada'?></b></div>
                <div><small>Área territorial</small><b><?=!empty($m['area_km2'])?number_format((float)$m['area_km2'],2,',','.').' km²':'Não informada'?></b></div>
                <div><small>Identificador da instância</small><b><?=Format::h($m['slug'])?></b></div>
                <div class="wide"><small>Site oficial</small><?php if($siteHref):?><a href="<?=Format::h($siteHref)?>" target="_blank" rel="noopener noreferrer"><?=Format::h($site)?> ↗</a><?php else:?><b><?=Format::h($site?:'Não informado')?></b><?php endif;?></div>
            </div>
        </section>

        <section class="card municipality-readonly-card">
            <header class="municipality-readonly-card-head"><div><span>02</span><h2>Estrutura da instância</h2></div><small>Resumo operacional</small></header>
            <div class="municipality-readonly-structure">
                <div><span>◉</span><p><small>Gestores ativos</small><b><?=intval($m['gestores_ativos'])?></b></p></div>
                <div><span>▤</span><p><small>Secretarias ativas</small><b><?=intval($m['secretarias_ativas'])?></b></p></div>
                <div><span>▥</span><p><small>Departamentos ativos</small><b><?=intval($m['departamentos_ativos'])?></b></p></div>
                <div><span>◇</span><p><small>Fases ativas</small><b><?=intval($m['fases_ativas'])?></b></p></div>
            </div>
        </section>

        <section class="card municipality-readonly-card municipality-readonly-identity">
            <header class="municipality-readonly-card-head"><div><span>03</span><h2>Identidade visual</h2></div><small>Padrão aplicado à instância</small></header>
            <div class="municipality-readonly-identity-preview" style="--preview-primary:<?=Format::h($m['cor_primaria']?:'#082A55')?>;--preview-secondary:<?=Format::h($m['cor_secundaria']?:'#176FDD')?>">
                <div class="municipality-readonly-identity-brand"><?php if($hasBrasao):?><img src="/media/municipios/<?=$m['id']?>/brasao?v=<?=substr(sha1((string)$m['brasao_path']),0,12)?>" alt=""><?php else:?><span>▥</span><?php endif;?><div><small>IDENTIDADE DA INSTÂNCIA</small><b><?=Format::h($m['nome'])?></b></div></div>
                <i></i>
            </div>
            <div class="municipality-readonly-color-row"><div><span style="background:<?=Format::h($m['cor_primaria']?:'#082A55')?>"></span><p><small>Cor primária</small><b><?=Format::h($m['cor_primaria']?:'#082A55')?></b></p></div><div><span style="background:<?=Format::h($m['cor_secundaria']?:'#176FDD')?>"></span><p><small>Cor secundária</small><b><?=Format::h($m['cor_secundaria']?:'#176FDD')?></b></p></div><div><p><small>Decoração dos cabeçalhos</small><b><?=Format::h($decoracao)?></b></p></div></div>
        </section>

        <section class="card municipality-readonly-card">
            <header class="municipality-readonly-card-head"><div><span>04</span><h2>Território e localização</h2></div><small>Dados geográficos cadastrados</small></header>
            <div class="municipality-readonly-territory-meta">
                <div><small>Latitude central</small><b><?=Format::h($m['latitude']!==null?$m['latitude']:'Não informada')?></b></div>
                <div><small>Longitude central</small><b><?=Format::h($m['longitude']!==null?$m['longitude']:'Não informada')?></b></div>
                <div><small>Delimitação GeoJSON</small><b><?=$hasGeo?'Cadastrada':'Não cadastrada'?></b></div>
                <div><small>Inteligência Territorial</small><b class="<?=$territorialActive?'is-active':'is-inactive'?>"><?=$territorialActive?'Ativa':'Inativa'?></b></div>
            </div>
            <?php if($hasGeo):?><div id="municipalityReadonlyMap" class="municipality-readonly-map"></div><?php else:?><div class="municipality-readonly-map-empty"><span>⌖</span><div><b>Perímetro municipal não cadastrado</b><p>Não há delimitação GeoJSON disponível para consulta nesta instância.</p></div></div><?php endif;?>
        </section>
    </div>

    <footer class="municipality-readonly-footer"><span>Última atualização cadastral: <b><?=Format::h(Format::dateTime($m['atualizado_em']??null))?></b></span><span>Consulta em modo somente leitura</span></footer>
</div>
<?php if($hasGeo):?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(function(){
 const el=document.getElementById('municipalityReadonlyMap');if(!el||typeof L==='undefined')return;
 let geo=null;try{geo=JSON.parse(<?=json_encode((string)$m['geojson_delimitacao'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>)}catch(e){return;}
 const fallback=[<?=json_encode((float)($m['latitude']?:-15))?>,<?=json_encode((float)($m['longitude']?:-52))?>];
 const map=L.map(el,{zoomControl:true,scrollWheelZoom:false}).setView(fallback,7);
 L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap contributors'}).addTo(map);
 const boundary=L.geoJSON(geo,{style:{color:getComputedStyle(document.body).getPropertyValue('--tenant-secondary').trim()||'#176FDD',weight:3,fillColor:getComputedStyle(document.body).getPropertyValue('--tenant-primary').trim()||'#082A55',fillOpacity:.10}}).addTo(map);
 try{const bounds=boundary.getBounds();if(bounds.isValid())map.fitBounds(bounds,{padding:[22,22]});}catch(e){}
 setTimeout(()=>map.invalidateSize(),120);
})();
</script>
<?php endif;?>
