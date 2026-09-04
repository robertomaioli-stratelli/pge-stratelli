/* PGE-Stratelli — Ícones Territoriais v4
 * Drop-in para Leaflet + Font Awesome 6.4.
 * Mantém compatibilidade com nomes antigos do banco e com os novos fa-*.
 */
(function(global){
  'use strict';

  const aliases = Object.freeze({
    pin:'fa-location-dot', school:'fa-school', health:'fa-plus', works:'fa-screwdriver-wrench',
    camera:'fa-video', area:'fa-draw-polygon', activity:'fa-dumbbell', 'book-open':'fa-book-open',
    trophy:'fa-futbol', shield:'fa-shield-halved', users:'fa-users', trees:'fa-tree', sprout:'fa-seedling',
    bus:'fa-bus', plane:'fa-plane', home:'fa-house', building:'fa-building', utensils:'fa-utensils',
    landmark:'fa-landmark', theater:'fa-masks-theater', 'heart-handshake':'fa-venus', hospital:'fa-hospital',
    cross:'fa-plus', ambulance:'fa-truck-medical', 'heart-pulse':'fa-brain', stethoscope:'fa-stethoscope',
    'paw-print':'fa-paw', 'graduation-cap':'fa-graduation-cap', warehouse:'fa-screwdriver-wrench',
    briefcase:'fa-briefcase'
  });

  const allowed = new Set([
    'fa-location-dot','fa-school','fa-plus','fa-screwdriver-wrench','fa-video','fa-draw-polygon',
    'fa-dumbbell','fa-book-open','fa-futbol','fa-children','fa-shield-halved','fa-users','fa-tree',
    'fa-seedling','fa-bus','fa-plane','fa-house','fa-person-walking','fa-building','fa-utensils',
    'fa-landmark','fa-masks-theater','fa-venus','fa-hospital','fa-truck-medical','fa-brain','fa-tooth',
    'fa-stethoscope','fa-paw','fa-graduation-cap','fa-briefcase'
  ]);

  function iconClass(value){
    const raw=String(value||'').trim();
    const normalized=aliases[raw]||raw;
    return allowed.has(normalized)?normalized:'fa-location-dot';
  }

  function normalizeColor(value){
    const raw=String(value||'').trim();
    return /^#[0-9a-fA-F]{6}$/.test(raw)||/^#[0-9a-fA-F]{3}$/.test(raw)?raw:'#176fdd';
  }

  function iconHtml(value){
    return '<i class="fa-solid '+iconClass(value)+'" aria-hidden="true"></i>';
  }

  function markerIcon(layer,options){
    if(typeof L==='undefined'||typeof L.divIcon!=='function'){
      throw new Error('Leaflet precisa ser carregado antes de stratelli-territorial-icons.js');
    }
    const opts=options||{};
    const size=Number(opts.size)||34;
    const compact=!!opts.compact;
    const color=normalizeColor(layer&&layer.cor);
    const cls='territorial-map-marker'+(compact?' compact':'');
    return L.divIcon({
      className:'territorial-div-icon',
      html:'<span class="'+cls+'" style="--marker-color:'+color+'"><span>'+iconHtml(layer&&layer.icone)+'</span></span>',
      iconSize:[size,size+4],
      iconAnchor:[size/2,size+1],
      popupAnchor:[0,-size],
      tooltipAnchor:[0,-size/2]
    });
  }

  global.StratelliTerritorialIcons=Object.freeze({iconClass,iconHtml,normalizeColor,markerIcon});
})(window);
