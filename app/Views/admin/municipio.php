<?php use App\Core\Csrf; use App\Core\Format; $hasGeo=!empty($municipio['geojson_delimitacao']); $hasBrasao=!empty($municipio['brasao_path']); $territorialActive=(int)($municipio['inteligencia_territorial_ativa']??0)===1; ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<div class="module-page municipality-detail-page">
    <div class="module-hero municipality-detail-hero">
        <div class="municipality-detail-title">
            <?php if($hasBrasao):?><img class="municipality-detail-coat" src="/media/municipios/<?=$municipio['id']?>/brasao?v=<?=substr(sha1((string)$municipio['brasao_path']),0,12)?>" loading="eager" decoding="async" alt="Brasão de <?=Format::h($municipio['nome'])?>"><?php else:?><span class="municipality-detail-coat placeholder">▥</span><?php endif;?>
            <div><h1><?=Format::h($municipio['nome'].' - '.$municipio['uf'])?></h1><p>Identidade institucional, dados básicos e delimitação territorial da instância.</p></div>
        </div>
        <div class="municipality-detail-actions"><a class="btn neutral" href="/admin/municipios">← Voltar aos municípios</a><a class="btn primary" href="/<?=Format::h($municipio['slug'])?>/dashboard">Abrir instância</a></div>
    </div>

    <div class="module-kpis-grid municipality-detail-kpis">
        <div class="module-kpi"><small>CÓDIGO IBGE</small><b><?=Format::h($municipio['codigo_ibge']?:'—')?></b><span>Identificador oficial</span></div>
        <div class="module-kpi"><small>POPULAÇÃO</small><b><?=!empty($municipio['populacao'])?number_format((int)$municipio['populacao'],0,',','.'):'—'?></b><span>Dado cadastrado</span></div>
        <div class="module-kpi"><small>ÁREA TERRITORIAL</small><b><?=!empty($municipio['area_km2'])?number_format((float)$municipio['area_km2'],2,',','.').' km²':'—'?></b><span>Território municipal</span></div>
        <div class="module-kpi neutral"><small>DELIMITAÇÃO</small><b><?=$hasGeo?'CADASTRADA':'PENDENTE'?></b><span><?=$hasGeo?'GeoJSON disponível':'Envie o arquivo territorial'?></span></div>
        <div class="module-kpi <?=$territorialActive?'success':'neutral'?>"><small>INTELIGÊNCIA TERRITORIAL</small><b><?=$territorialActive?'ATIVA':'INATIVA'?></b><span><?=$territorialActive?'Disponível aos usuários municipais':'Somente a Stratelli visualiza e configura'?></span></div>
        <div class="module-kpi success"><small>USUÁRIOS</small><b><?=$municipio['usuarios_ativos']?></b><span>Ativos na instância</span></div>
        <div class="module-kpi success"><small>GESTORES</small><b><?=$municipio['gestores_ativos']?></b><span>Gestores ativos</span></div>
        <div class="module-kpi"><small>SECRETARIAS</small><b><?=$municipio['secretarias_ativas']?></b><span>Unidades ativas</span></div>
        <div class="module-kpi"><small>FASES</small><b><?=$municipio['fases_ativas']?></b><span>Fases cadastradas</span></div>
    </div>

    <section class="card municipality-module-card <?=$territorialActive?'is-active':'is-inactive'?>">
        <div class="municipality-module-copy">
            <span class="municipality-module-icon">⌖</span>
            <div><small>MÓDULO DA INSTÂNCIA</small><h2>Inteligência Territorial</h2><p>Por padrão, este módulo permanece inativo para Gestores e Usuários do município. A Stratelli pode preparar mapas, camadas e objetos antes da liberação.</p></div>
        </div>
        <div class="municipality-module-action">
            <span class="module-status <?=$territorialActive?'approved':'pending'?>"><?=$territorialActive?'ATIVA PARA O MUNICÍPIO':'INATIVA PARA O MUNICÍPIO'?></span>
            <form method="post" action="/admin/municipios/<?=$municipio['id']?>/modulos/territorio">
                <input type="hidden" name="_token" value="<?=Format::h(Csrf::token())?>">
                <input type="hidden" name="voltar" value="/admin/municipios/<?=$municipio['id']?>">
                <input type="hidden" name="ativo" value="<?=$territorialActive?'0':'1'?>">
                <button class="btn <?=$territorialActive?'danger':'primary'?>" type="submit"><?=$territorialActive?'Desativar para usuários':'Ativar para usuários'?></button>
            </form>
            <a class="mini-link" href="/<?=Format::h($municipio['slug'])?>/territorio?modo=configuracao">Configurar dados territoriais</a>
        </div>
    </section>

    <div class="municipality-detail-grid">
        <section class="card municipality-profile-card">
            <?php $municipioStatusClass=match((string)$municipio['status']){'ATIVO'=>'municipality-status-active','IMPLANTACAO'=>'municipality-status-implementation','NEGOCIACAO'=>'municipality-status-negotiation','APRESENTACAO'=>'municipality-status-presentation','SUSPENSO'=>'municipality-status-suspended','DESATIVADO'=>'municipality-status-disabled',default=>'municipality-status-implementation'}; $municipioStatusLabel=match((string)$municipio['status']){'ATIVO'=>'ATIVO','IMPLANTACAO'=>'IMPLANTAÇÃO','NEGOCIACAO'=>'NEGOCIAÇÃO','APRESENTACAO'=>'APRESENTAÇÃO','SUSPENSO'=>'SUSPENSO','DESATIVADO'=>'DESATIVADO',default=>(string)$municipio['status']}; ?>
            <div class="municipality-section-head"><div><h2>Cadastro do município</h2><p>Dados institucionais usados em toda a plataforma.</p></div><span class="module-status <?=$municipioStatusClass?>"><?=Format::h($municipioStatusLabel)?></span></div>
            <form method="post" action="/admin/municipios/<?=$municipio['id']?>" enctype="multipart/form-data" class="production-form municipality-edit-form">
                <input type="hidden" name="_token" value="<?=Format::h(Csrf::token())?>">
                <div class="municipality-form-grid"><label>Município<input name="nome" value="<?=Format::h($municipio['nome'])?>" required></label><label>UF<input name="uf" maxlength="2" value="<?=Format::h($municipio['uf'])?>" required></label><label>Slug da instância<input name="slug" value="<?=Format::h($municipio['slug'])?>" required></label><label>Status<select name="status"><option value="NEGOCIACAO" <?=$municipio['status']==='NEGOCIACAO'?'selected':''?>>Negociação</option><option value="APRESENTACAO" <?=$municipio['status']==='APRESENTACAO'?'selected':''?>>Apresentação</option><option value="IMPLANTACAO" <?=$municipio['status']==='IMPLANTACAO'?'selected':''?>>Implantação</option><option value="ATIVO" <?=$municipio['status']==='ATIVO'?'selected':''?>>Ativo</option><option value="SUSPENSO" <?=$municipio['status']==='SUSPENSO'?'selected':''?>>Suspenso</option><option value="DESATIVADO" <?=$municipio['status']==='DESATIVADO'?'selected':''?>>Desativado</option></select></label></div>
                <div class="municipality-form-grid"><label>Código IBGE<input name="codigo_ibge" inputmode="numeric" value="<?=Format::h($municipio['codigo_ibge']??'')?>"></label><label>População<input name="populacao" inputmode="numeric" value="<?=Format::h($municipio['populacao']??'')?>"></label><label>Área territorial (km²)<input name="area_km2" inputmode="decimal" value="<?=Format::h($municipio['area_km2']??'')?>"></label><label>Site oficial<input name="site_oficial" value="<?=Format::h($municipio['site_oficial']??'')?>" placeholder="https://..."></label></div>
                <div class="municipality-form-grid"><label>Latitude central<input name="latitude" inputmode="decimal" value="<?=Format::h($municipio['latitude']??'')?>" placeholder="-23.4205"></label><label>Longitude central<input name="longitude" inputmode="decimal" value="<?=Format::h($municipio['longitude']??'')?>" placeholder="-51.9333"></label></div>
                <div class="municipality-upload-grid" id="cadastro-territorial">
                    <label class="municipality-upload-box"><span>Brasão municipal</span><small>PNG, JPG ou WebP · até 2 MB</small><input type="file" name="brasao" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"><?php if($hasBrasao):?><em>✓ Brasão atualmente cadastrado</em><label class="inline-check"><input type="checkbox" name="remover_brasao" value="1"> Remover brasão atual</label><?php endif;?></label>
                    <label class="municipality-upload-box"><span>Delimitação territorial</span><small>GeoJSON/JSON do perímetro municipal · até 8 MB</small><input type="file" name="geojson" accept=".geojson,.json,application/geo+json,application/json"><?php if($hasGeo):?><em>✓ Delimitação atualmente cadastrada</em><label class="inline-check"><input type="checkbox" name="remover_geojson" value="1"> Remover delimitação atual</label><?php endif;?></label>
                </div>
                <button class="btn primary" type="submit">Salvar cadastro municipal</button>
            </form>
        </section>

        <section class="card municipality-map-card" id="mapa-territorial">
            <div class="municipality-section-head"><div><h2>Mapa geoprocessado</h2><p>Visualização do perímetro cadastrado para o município.</p></div><span class="territory-status <?=$hasGeo?'ready':'empty'?>"><?=$hasGeo?'PERÍMETRO CARREGADO':'AGUARDANDO GEOJSON'?></span></div>
            <?php if($hasGeo):?><div id="municipalityGeoMap" class="municipality-geo-map"></div><div class="municipality-map-legend"><span><i></i> Limite municipal cadastrado</span><small>Base cartográfica: OpenStreetMap</small></div>
            <?php else:?><div class="municipality-map-empty"><span>⌖</span><b>Delimitação ainda não cadastrada</b><p>Envie um arquivo GeoJSON no cadastro ao lado. A plataforma ajustará automaticamente o mapa para o perímetro municipal.</p></div><?php endif;?>
        </section>
    </div>
</div>

<style>
.municipality-status-active{background:#e6f7eb!important;color:#117438!important;border-color:#bfe8cc!important}.municipality-status-implementation{background:#e8f2ff!important;color:#176fdd!important;border-color:#c9ddf7!important}.municipality-status-negotiation{background:#f1e9ff!important;color:#6d31c9!important;border-color:#d9c5fb!important}.municipality-status-presentation{background:#e8f8f8!important;color:#087b80!important;border-color:#bfe5e5!important}.municipality-status-suspended{background:#fff4dc!important;color:#9a6300!important;border-color:#efd28f!important}.municipality-status-disabled{background:#fdebea!important;color:#b8322b!important;border-color:#efcbc8!important}
</style>
<?php if($hasGeo):?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(function(){
 const el=document.getElementById('municipalityGeoMap'); if(!el||typeof L==='undefined')return;
 const geo=JSON.parse(<?=json_encode((string)$municipio['geojson_delimitacao'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>);
 const fallback=[<?=json_encode((float)($municipio['latitude']?:-15))?>,<?=json_encode((float)($municipio['longitude']?:-52))?>];
 const map=L.map(el,{zoomControl:true,scrollWheelZoom:true}).setView(fallback,6);
 L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap contributors'}).addTo(map);
 const boundary=L.geoJSON(geo,{style:{color:'#df4541',weight:2,opacity:1,fillColor:'#176fdd',fillOpacity:.10}}).addTo(map);
 try{const bounds=boundary.getBounds();if(bounds.isValid())map.fitBounds(bounds,{padding:[22,22]});}catch(e){}
 const refreshMap=()=>map.invalidateSize({pan:false});
 setTimeout(refreshMap,100);
 window.addEventListener('resize',refreshMap,{passive:true});
 if('ResizeObserver' in window){
   const ro=new ResizeObserver(()=>refreshMap());
   ro.observe(el);
 }
})();
</script>
<?php endif;?>
