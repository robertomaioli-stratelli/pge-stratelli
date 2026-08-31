<?php use App\Core\Csrf; ?>
<div class="module-page">
    <div class="module-hero">
        <div>
            <h1>Usuários</h1>
            <p>Criação centralizada de administradores Stratelli, gestores e usuários municipais.</p>
        </div>
    </div>

    <?php if(!empty($erro)):?><div class="flash error"><?=htmlspecialchars($erro)?></div><?php endif;?>
    <?php if(!empty($ok)):?><div class="flash ok"><?=htmlspecialchars($ok)?></div><?php endif;?>

    <div class="production-two-columns">
        <section class="card config-card">
            <h2>Novo usuário</h2>
            <form method="post" action="/admin/usuarios" class="production-form" id="userCreateForm">
                <input type="hidden" name="_token" value="<?=htmlspecialchars(Csrf::token())?>">
                <label>Nome<input name="nome" required></label>
                <label>E-mail<input type="email" name="email" required></label>
                <label>Senha inicial<input type="password" name="senha" minlength="10" required></label>
                <label>Grupo
                    <select name="grupo" id="userGroup">
                        <option>ADMINISTRADOR</option>
                        <option selected>GESTOR</option>
                        <option>USUARIO</option>
                    </select>
                </label>
                <label>Município
                    <select name="municipio_id" id="userMunicipio">
                        <option value="">Selecione</option>
                        <?php foreach($municipios as $m):?>
                            <option value="<?=$m['id']?>"><?=htmlspecialchars($m['nome'].' - '.$m['uf'])?></option>
                        <?php endforeach;?>
                    </select>
                </label>
                <label>Secretaria <small>(obrigatória para USUARIO)</small>
                    <select name="secretaria_id" id="userSecretaria">
                        <option value="">Sem secretaria</option>
                        <?php foreach($secretarias as $s):?>
                            <option value="<?=$s['id']?>" data-municipio="<?=$s['municipio_id']?>"><?=htmlspecialchars(($s['sigla']?$s['sigla'].' — ':'').$s['nome'])?></option>
                        <?php endforeach;?>
                    </select>
                </label>
                <label>Departamento <small>(opcional; restringe o usuário à unidade)</small>
                    <select name="departamento_id" id="userDepartamento">
                        <option value="">Toda a secretaria</option>
                        <?php foreach($departamentos as $d):?>
                            <option value="<?=$d['id']?>" data-municipio="<?=$d['municipio_id']?>" data-secretaria="<?=$d['secretaria_id']?>"><?=htmlspecialchars(($d['sigla']?$d['sigla'].' — ':'').$d['nome'])?></option>
                        <?php endforeach;?>
                    </select>
                </label>
                <label class="check-line"><input type="checkbox" name="administrador_plataforma" value="1"> Administrador da plataforma Stratelli</label>
                <button class="btn primary">Criar usuário</button>
            </form>
        </section>

        <section class="card config-card users-grid-card">
            <div class="users-grid-heading">
                <div>
                    <h2>Usuários cadastrados</h2>
                    <p>Pesquise, ordene e organize a visualização dos usuários da plataforma.</p>
                </div>
                <span class="users-grid-total" id="usersTotalBadge"><?=count($usuarios)?> usuário(s)</span>
            </div>

            <div class="users-grid-toolbar">
                <label class="users-search-field">
                    <span>Buscar usuário</span>
                    <div class="users-search-input-wrap">
                        <span aria-hidden="true">⌕</span>
                        <input type="search" id="usersGridSearch" placeholder="Nome, e-mail, município, unidade, grupo ou status" autocomplete="off">
                    </div>
                </label>

                <label class="users-color-toggle" for="usersColorByMunicipio">
                    <input type="checkbox" id="usersColorByMunicipio">
                    <span>
                        <b>Colorir linhas por município</b>
                        <small>Opcional · desativado por padrão</small>
                    </span>
                </label>
            </div>

            <div class="users-grid-color-legend" id="usersColorLegend" hidden></div>

            <div class="table-wrap users-table-wrap">
                <table class="production-table users-management-table" id="usersManagementTable">
                    <thead>
                        <tr>
                            <th><button type="button" class="users-sort-button" data-sort="usuario" data-type="text">Usuário <span>↕</span></button></th>
                            <th><button type="button" class="users-sort-button" data-sort="grupo" data-type="text">Grupo <span>↕</span></button></th>
                            <th><button type="button" class="users-sort-button" data-sort="municipio" data-type="text">Município <span>↕</span></button></th>
                            <th><button type="button" class="users-sort-button" data-sort="unidade" data-type="text">Unidade <span>↕</span></button></th>
                            <th><button type="button" class="users-sort-button" data-sort="status" data-type="text">Status <span>↕</span></button></th><th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="usersManagementBody">
                        <?php foreach($usuarios as $u):
                            $grupoTexto=$u['administrador_plataforma']?'ADMIN STRATELLI':$u['grupo'];
                            $municipioTexto=$u['municipio_nome']?($u['municipio_nome'].' - '.$u['municipio_uf']):'Plataforma';
                            if($u['administrador_plataforma']){
                                $unidadeTexto='—';
                            } elseif($u['secretaria_nome']){
                                $unidadeTexto=(($u['secretaria_sigla']?$u['secretaria_sigla'].' — ':'').$u['secretaria_nome']);
                                if($u['departamento_nome']) $unidadeTexto.=' · '.(($u['departamento_sigla']?$u['departamento_sigla'].' — ':'').$u['departamento_nome']);
                            } else {
                                $unidadeTexto='Visão municipal';
                            }
                            $statusTexto=$u['ativo']?'ATIVO':'INATIVO';
                            $searchText=implode(' ',[$u['nome'],$u['email'],$grupoTexto,$municipioTexto,$unidadeTexto,$statusTexto]);
                        ?>
                            <tr
                                data-search="<?=htmlspecialchars(mb_strtolower($searchText,'UTF-8'))?>"
                                data-usuario="<?=htmlspecialchars(mb_strtolower($u['nome'].' '.$u['email'],'UTF-8'))?>"
                                data-grupo="<?=htmlspecialchars(mb_strtolower($grupoTexto,'UTF-8'))?>"
                                data-municipio="<?=htmlspecialchars($municipioTexto)?>"
                                data-municipio-sort="<?=htmlspecialchars(mb_strtolower($municipioTexto,'UTF-8'))?>"
                                data-unidade="<?=htmlspecialchars(mb_strtolower($unidadeTexto,'UTF-8'))?>"
                                data-status="<?=htmlspecialchars(mb_strtolower($statusTexto,'UTF-8'))?>"
                            >
                                <td><b><?=htmlspecialchars($u['nome'])?></b><br><small><?=htmlspecialchars($u['email'])?></small></td>
                                <td><?=htmlspecialchars($grupoTexto)?></td>
                                <td><?=htmlspecialchars($municipioTexto)?></td>
                                <td>
                                    <?php if($u['administrador_plataforma']):?>
                                        —
                                    <?php elseif($u['secretaria_nome']):?>
                                        <b><?=htmlspecialchars(($u['secretaria_sigla']?$u['secretaria_sigla'].' — ':'').$u['secretaria_nome'])?></b>
                                        <?php if($u['departamento_nome']):?>
                                            <br><small><?=htmlspecialchars(($u['departamento_sigla']?$u['departamento_sigla'].' — ':'').$u['departamento_nome'])?></small>
                                        <?php endif;?>
                                    <?php else:?>
                                        Visão municipal
                                    <?php endif;?>
                                </td>
                                <td><span class="module-status <?=$u['ativo']?'approved':'pending'?>"><?=$statusTexto?></span></td><td><div class="users-row-actions"><a class="mini-link" href="/admin/usuarios/<?=$u['id']?>">Editar</a><form method="post" action="/admin/usuarios/<?=$u['id']?>/status"><input type="hidden" name="_token" value="<?=htmlspecialchars(Csrf::token())?>"><input type="hidden" name="ativo" value="<?=$u['ativo']?0:1?>"><button class="mini-link <?=$u['ativo']?'danger':''?>" type="submit"><?=$u['ativo']?'Desativar':'Ativar'?></button></form><?php if($u['ativo']):?><form method="post" action="/admin/usuarios/<?=$u['id']?>/recuperacao"><input type="hidden" name="_token" value="<?=htmlspecialchars(Csrf::token())?>"><button class="mini-link" type="submit">Enviar recuperação</button></form><?php endif;?></div></td>
                            </tr>
                        <?php endforeach;?>
                    </tbody>
                </table>
            </div>

            <div class="users-grid-empty" id="usersGridEmpty" hidden>
                Nenhum usuário encontrado para o filtro informado.
            </div>

            <div class="users-grid-footer">
                <div class="users-grid-range" id="usersGridRange"></div>
                <nav class="users-grid-pagination" id="usersGridPagination" aria-label="Paginação dos usuários"></nav>
            </div>
        </section>
    </div>
</div>

<style>
.users-grid-card{min-width:0}
.users-grid-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:14px}.users-grid-heading h2{margin-bottom:4px}.users-grid-heading p{margin:0;color:#60708a;font-size:12px}.users-grid-total{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:7px 11px;border:1px solid #cfe0f5;border-radius:10px;background:#f4f8fd;color:#0b3d73;font-size:11px;font-weight:900;white-space:nowrap}
.users-grid-toolbar{display:grid;grid-template-columns:minmax(260px,1fr) auto;gap:12px;align-items:end;margin-bottom:12px}.users-search-field{display:grid;gap:6px;color:#23405f;font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.02em}.users-search-input-wrap{display:flex;align-items:center;gap:8px;min-width:0;height:42px;padding:0 12px;border:1px solid #cfdae8;border-radius:10px;background:#fff}.users-search-input-wrap:focus-within{border-color:#176fdd;box-shadow:0 0 0 3px rgba(23,111,221,.1)}.users-search-input-wrap span{font-size:16px;color:#58708c}.users-search-input-wrap input{width:100%;min-width:0;border:0!important;outline:0!important;box-shadow:none!important;padding:0!important;background:transparent!important;color:#0d2948;font-size:13px;text-transform:none;font-weight:600;letter-spacing:0}
.users-color-toggle{display:flex;align-items:center;gap:10px;min-height:42px;padding:7px 12px;border:1px solid #cfdae8;border-radius:10px;background:#fff;cursor:pointer;user-select:none}.users-color-toggle input{width:17px;height:17px;margin:0;accent-color:#176fdd}.users-color-toggle span{display:grid;gap:1px}.users-color-toggle b{font-size:11px;color:#173d63}.users-color-toggle small{font-size:9px;color:#74849a}
.users-grid-color-legend{display:flex;flex-wrap:wrap;gap:7px;margin:0 0 12px}.users-grid-color-legend .legend-item{display:inline-flex;align-items:center;gap:6px;padding:5px 8px;border:1px solid #dbe4ef;border-radius:999px;background:#fff;color:#3c536d;font-size:9px;font-weight:800}.users-grid-color-legend .legend-dot{width:10px;height:10px;border-radius:3px;background:var(--legend-color);border:1px solid rgba(13,41,72,.08)}
.users-management-table th{white-space:nowrap}.users-sort-button{display:inline-flex;align-items:center;gap:5px;width:100%;padding:0;border:0;background:transparent;color:inherit;font:inherit;font-weight:900;text-align:left;cursor:pointer}.users-sort-button span{font-size:10px;color:#7890aa}.users-sort-button.is-active{color:#0d5da8}.users-sort-button.is-active span{color:#0d5da8}.users-management-table tbody.is-colored tr[data-municipio]:not([data-municipio="Plataforma"])>td{background:var(--municipality-row-bg,#fff)!important}.users-management-table tbody tr>td{transition:background .15s ease}.users-management-table tbody.is-colored tr:not([data-municipio="Plataforma"]):hover>td{box-shadow:inset 0 0 0 9999px rgba(255,255,255,.20)}
.users-grid-empty{padding:20px;border:1px dashed #ccd8e6;border-radius:10px;text-align:center;color:#61738a;font-size:12px;background:#fafcfe}.users-grid-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:12px;min-height:34px}.users-grid-range{font-size:10px;color:#60708a;font-weight:700}.users-grid-pagination{display:flex;align-items:center;justify-content:flex-end;flex-wrap:wrap;gap:5px}.users-page-button{display:inline-flex;align-items:center;justify-content:center;min-width:31px;height:31px;padding:0 9px;border:1px solid #d4deea;border-radius:8px;background:#fff;color:#173f68;font-size:10px;font-weight:900;cursor:pointer}.users-page-button:hover:not(:disabled){border-color:#176fdd;color:#176fdd}.users-page-button.is-current{background:#0d4f83;border-color:#0d4f83;color:#fff}.users-page-button:disabled{opacity:.42;cursor:not-allowed}.users-page-ellipsis{padding:0 2px;color:#8090a4;font-size:11px}
@media(max-width:1100px){.users-grid-toolbar{grid-template-columns:1fr}.users-color-toggle{justify-self:start}.users-grid-footer{align-items:flex-start;flex-direction:column}.users-grid-pagination{justify-content:flex-start}}
@media(max-width:640px){.users-grid-heading{align-items:flex-start;flex-direction:column}.users-grid-toolbar{grid-template-columns:minmax(0,1fr)}.users-color-toggle{width:100%}.users-grid-pagination{gap:4px}.users-page-button{min-width:29px;height:29px;padding:0 7px}}
</style>

<script>
(function(){
    const municipio=document.getElementById('userMunicipio'), secretaria=document.getElementById('userSecretaria'), departamento=document.getElementById('userDepartamento'), grupo=document.getElementById('userGroup');
    function filterSecretarias(){const mid=municipio.value;[...secretaria.options].forEach((o,i)=>{if(i===0)return;o.hidden=!!mid&&o.dataset.municipio!==mid;});if(secretaria.selectedOptions[0]?.hidden)secretaria.value='';filterDepartamentos();}
    function filterDepartamentos(){const mid=municipio.value,sid=secretaria.value;[...departamento.options].forEach((o,i)=>{if(i===0)return;o.hidden=(!!mid&&o.dataset.municipio!==mid)||(!!sid&&o.dataset.secretaria!==sid);});if(departamento.selectedOptions[0]?.hidden)departamento.value='';}
    function syncRole(){const common=grupo.value==='USUARIO';secretaria.disabled=!common;departamento.disabled=!common;if(!common){secretaria.value='';departamento.value='';}filterSecretarias();}
    municipio?.addEventListener('change',filterSecretarias);secretaria?.addEventListener('change',filterDepartamentos);grupo?.addEventListener('change',syncRole);syncRole();

    const body=document.getElementById('usersManagementBody');
    if(!body)return;
    const table=document.getElementById('usersManagementTable');
    const rows=[...body.querySelectorAll('tr')];
    const search=document.getElementById('usersGridSearch');
    const pagination=document.getElementById('usersGridPagination');
    const range=document.getElementById('usersGridRange');
    const empty=document.getElementById('usersGridEmpty');
    const colorToggle=document.getElementById('usersColorByMunicipio');
    const legend=document.getElementById('usersColorLegend');
    const pageSize=10;
    let currentPage=1;
    let sortKey='usuario';
    let sortDirection='asc';

    const normalize=(value)=>String(value??'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('pt-BR').trim();
    rows.forEach((row,index)=>{row.dataset.originalIndex=String(index);});

    const municipalityPalette=[
        '#eef7ff','#f3efff','#eefaf3','#fff6e8','#fff0f1','#eef8f8',
        '#f7f3e9','#f2f5ff','#f8eef8','#eef6ee','#fff3e8','#f0f4f8'
    ];
    const municipalities=[...new Set(rows.map(row=>row.dataset.municipio).filter(name=>name&&name!=='Plataforma'))].sort((a,b)=>a.localeCompare(b,'pt-BR',{sensitivity:'base'}));
    const municipalityColors=new Map();
    municipalities.forEach((name,index)=>municipalityColors.set(name,municipalityPalette[index%municipalityPalette.length]));
    rows.forEach(row=>{const color=municipalityColors.get(row.dataset.municipio);if(color)row.style.setProperty('--municipality-row-bg',color);});

    function renderLegend(){
        legend.innerHTML='';
        municipalities.forEach(name=>{
            const item=document.createElement('span');
            item.className='legend-item';
            item.innerHTML='<i class="legend-dot" aria-hidden="true"></i><span></span>';
            item.style.setProperty('--legend-color',municipalityColors.get(name));
            item.querySelector('span').textContent=name;
            legend.appendChild(item);
        });
    }
    renderLegend();

    const prefKey='inpacta_admin_users_color_by_municipio';
    let colorEnabled=false;
    try{colorEnabled=localStorage.getItem(prefKey)==='1';}catch(e){colorEnabled=false;}
    colorToggle.checked=colorEnabled;
    function applyColorPreference(){
        body.classList.toggle('is-colored',colorToggle.checked);
        legend.hidden=!colorToggle.checked||municipalities.length===0;
        try{localStorage.setItem(prefKey,colorToggle.checked?'1':'0');}catch(e){}
    }
    colorToggle.addEventListener('change',applyColorPreference);
    applyColorPreference();

    function filteredAndSortedRows(){
        const query=normalize(search.value);
        const result=rows.filter(row=>!query||normalize(row.dataset.search).includes(query));
        result.sort((a,b)=>{
            const aVal=normalize(sortKey==='municipio'?a.dataset.municipioSort:a.dataset[sortKey]);
            const bVal=normalize(sortKey==='municipio'?b.dataset.municipioSort:b.dataset[sortKey]);
            const cmp=aVal.localeCompare(bVal,'pt-BR',{numeric:true,sensitivity:'base'});
            if(cmp!==0)return sortDirection==='asc'?cmp:-cmp;
            return Number(a.dataset.originalIndex)-Number(b.dataset.originalIndex);
        });
        return result;
    }

    function paginationModel(totalPages,page){
        if(totalPages<=7)return Array.from({length:totalPages},(_,i)=>i+1);
        const pages=[1];
        if(page>4)pages.push('...');
        const start=Math.max(2,page-1), end=Math.min(totalPages-1,page+1);
        for(let p=start;p<=end;p++)pages.push(p);
        if(page<totalPages-3)pages.push('...');
        pages.push(totalPages);
        return pages;
    }

    function updateSortIndicators(){
        document.querySelectorAll('.users-sort-button').forEach(button=>{
            const active=button.dataset.sort===sortKey;
            button.classList.toggle('is-active',active);
            button.setAttribute('aria-sort',active?(sortDirection==='asc'?'ascending':'descending'):'none');
            const indicator=button.querySelector('span');
            if(indicator)indicator.textContent=active?(sortDirection==='asc'?'▲':'▼'):'↕';
        });
    }

    function render(){
        const data=filteredAndSortedRows();
        const total=data.length;
        const totalPages=Math.max(1,Math.ceil(total/pageSize));
        currentPage=Math.min(Math.max(1,currentPage),totalPages);
        const start=(currentPage-1)*pageSize;
        const end=Math.min(start+pageSize,total);
        const visible=new Set(data.slice(start,end));

        rows.forEach(row=>row.hidden=!visible.has(row));
        data.forEach(row=>body.appendChild(row));
        rows.filter(row=>!data.includes(row)).forEach(row=>body.appendChild(row));

        empty.hidden=total!==0;
        table.hidden=total===0;
        range.textContent=total===0?'Nenhum usuário encontrado':`Exibindo ${start+1} a ${end} de ${total} usuário(s)`;

        pagination.innerHTML='';
        if(totalPages>1){
            const makeButton=(label,page,disabled=false,current=false)=>{
                const button=document.createElement('button');
                button.type='button';
                button.className='users-page-button'+(current?' is-current':'');
                button.textContent=label;
                button.disabled=disabled;
                if(current)button.setAttribute('aria-current','page');
                button.addEventListener('click',()=>{currentPage=page;render();});
                return button;
            };
            pagination.appendChild(makeButton('Anterior',currentPage-1,currentPage===1));
            paginationModel(totalPages,currentPage).forEach(item=>{
                if(item==='...'){
                    const ellipsis=document.createElement('span');ellipsis.className='users-page-ellipsis';ellipsis.textContent='…';pagination.appendChild(ellipsis);
                } else {
                    pagination.appendChild(makeButton(String(item),item,false,item===currentPage));
                }
            });
            pagination.appendChild(makeButton('Próxima',currentPage+1,currentPage===totalPages));
        }
        updateSortIndicators();
    }

    search.addEventListener('input',()=>{currentPage=1;render();});
    document.querySelectorAll('.users-sort-button').forEach(button=>button.addEventListener('click',()=>{
        const key=button.dataset.sort;
        if(sortKey===key)sortDirection=sortDirection==='asc'?'desc':'asc';
        else{sortKey=key;sortDirection='asc';}
        currentPage=1;
        render();
    }));
    render();
})();
</script>
