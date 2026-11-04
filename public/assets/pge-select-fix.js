/* PGE V18 – substitui o popup nativo apenas na aba Documentos e Modelos. */
(function(){
  'use strict';

  function ready(fn){
    if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  function getDocumentContainers(){
    return Array.prototype.slice.call(document.querySelectorAll('.document-tab-page'));
  }

  function closeAll(except){
    document.querySelectorAll('.pge-custom-select.is-open').forEach(function(node){
      if(except && node === except) return;
      node.classList.remove('is-open');
    });
  }

  function normalizeLabel(text){
    return String(text || '').replace(/\s+/g,' ').trim();
  }

  function build(select){
    if(!select || select.multiple || select.dataset.pgeEnhanced === '1') return;
    if(select.closest('.pge-custom-select')) return;

    var wrapper = document.createElement('div');
    wrapper.className = 'pge-custom-select';

    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'pge-custom-select-btn';
    button.setAttribute('aria-haspopup', 'listbox');
    button.setAttribute('aria-expanded', 'false');

    var panel = document.createElement('div');
    panel.className = 'pge-custom-select-panel';

    var searchWrap = document.createElement('div');
    searchWrap.className = 'pge-custom-select-search-wrap';
    var search = document.createElement('input');
    search.type = 'search';
    search.className = 'pge-custom-select-search';
    search.placeholder = 'Filtrar opções';
    searchWrap.appendChild(search);

    var list = document.createElement('div');
    list.className = 'pge-custom-select-list';

    panel.appendChild(searchWrap);
    panel.appendChild(list);

    select.classList.add('pge-native-select-hidden');
    select.dataset.pgeEnhanced = '1';
    select.parentNode.insertBefore(wrapper, select.nextSibling);
    wrapper.appendChild(button);
    wrapper.appendChild(panel);

    function syncState(){
      wrapper.classList.toggle('is-disabled', !!select.disabled);
      button.disabled = !!select.disabled;
      var idx = select.selectedIndex >= 0 ? select.selectedIndex : 0;
      var current = select.options[idx] || null;
      var label = current ? normalizeLabel(current.textContent) : 'Selecione';
      button.textContent = label || 'Selecione';
      button.title = label || 'Selecione';
      button.setAttribute('aria-expanded', wrapper.classList.contains('is-open') ? 'true' : 'false');
      Array.prototype.forEach.call(list.querySelectorAll('.pge-custom-select-option'), function(optBtn){
        optBtn.classList.toggle('is-selected', String(optBtn.dataset.value) === String(select.value));
      });
    }

    function rebuild(filter){
      var term = normalizeLabel(filter).toLowerCase();
      list.innerHTML = '';
      var shown = 0;
      Array.prototype.forEach.call(select.options, function(option, index){
        var label = normalizeLabel(option.textContent);
        var hay = label.toLowerCase();
        if(term && hay.indexOf(term) === -1) return;
        var optBtn = document.createElement('button');
        optBtn.type = 'button';
        optBtn.className = 'pge-custom-select-option';
        if(!option.value) optBtn.classList.add('is-placeholder');
        optBtn.textContent = label || '—';
        optBtn.title = label;
        optBtn.dataset.value = option.value;
        optBtn.dataset.index = String(index);
        if(option.disabled) optBtn.disabled = true;
        if(String(option.value) === String(select.value)) optBtn.classList.add('is-selected');
        optBtn.addEventListener('click', function(){
          if(option.disabled) return;
          select.selectedIndex = index;
          select.value = option.value;
          select.dispatchEvent(new Event('change', {bubbles:true}));
          syncState();
          closeAll();
        });
        list.appendChild(optBtn);
        shown++;
      });
      if(!shown){
        var empty = document.createElement('div');
        empty.className = 'pge-custom-select-empty';
        empty.textContent = 'Nenhuma opção encontrada.';
        list.appendChild(empty);
      }
      syncState();
    }

    button.addEventListener('click', function(e){
      e.stopPropagation();
      if(select.disabled) return;
      var willOpen = !wrapper.classList.contains('is-open');
      closeAll(wrapper);
      wrapper.classList.toggle('is-open', willOpen);
      button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      if(willOpen){
        search.value = '';
        rebuild('');
        setTimeout(function(){ search.focus(); }, 0);
      }
    });

    search.addEventListener('input', function(){ rebuild(search.value || ''); });
    search.addEventListener('keydown', function(e){ if(e.key === 'Escape'){ closeAll(); button.focus(); } });

    select.addEventListener('change', function(){ rebuild(search.value || ''); syncState(); });
    select.addEventListener('disabledchange', syncState);

    var observer = new MutationObserver(function(){ rebuild(search.value || ''); syncState(); });
    observer.observe(select, {childList:true, subtree:true, attributes:true, attributeFilter:['disabled']});

    rebuild('');
    syncState();
  }

  function init(){
    var containers = getDocumentContainers();
    if(!containers.length) return;

    containers.forEach(function(container){
      container.querySelectorAll('select').forEach(build);
      var observer = new MutationObserver(function(records){
        records.forEach(function(record){
          record.addedNodes.forEach(function(node){
            if(node.nodeType !== 1) return;
            if(node.matches && node.matches('select')) build(node);
            if(node.querySelectorAll) node.querySelectorAll('select').forEach(build);
          });
        });
      });
      observer.observe(container, {childList:true, subtree:true});
    });

    document.addEventListener('click', function(){ closeAll(); });
    document.addEventListener('keydown', function(e){ if(e.key === 'Escape') closeAll(); });
  }

  ready(init);
})();
