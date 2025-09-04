/* NutSpace – Story Builder Admin JS (v0.9.1 + simpler order + preview) */
(function($){
  const $grid   = $('#nsmg-images-grid');
  const $images = $('#nsmg-images-csv');
  const $order  = $('#nsmg-order-manual');
  const $chips  = $('#nsmg-order-chips');
  const $manualToggle = $('#nsmg-edit-order-manually');

  const PREFILL = window.NSMG_STORY_PREFILL || {audio:'',images:'',order:''};

  function urlToFilename(u){
    try {
      const p = new URL(u, window.location.origin);
      return decodeURIComponent((p.pathname.split('/').pop()||'').trim());
    } catch(e){
      const parts = (u||'').split('?')[0].split('#')[0].split('/');
      return decodeURIComponent((parts.pop()||'').trim());
    }
  }

  function renderEmptyGrid(){
    $grid.html('<div class="nsmg-empty">'+ ($grid.data('empty')||'No images yet') +'</div>');
  }

  function gridItems(){
    return $grid.find('.nsmg-tile').map(function(){
      return { url: $(this).data('url'), fn: $(this).data('fn') };
    }).get();
  }

  function syncHiddenCSV(){
    $images.val( gridItems().map(x=>x.url).join(', ') );
  }

  function syncOrderFromGrid(){
    // Auto-sync only when manual is OFF
    if (!$manualToggle.prop('checked')){
      const fns = gridItems().map(x=>x.fn);
      $order.val(fns.join(', '));
      renderChips(fns);
    }
  }

  function renderChips(filenames){
    $chips.empty();
    if (!filenames || !filenames.length){
      $chips.append('<span class="chip chip-empty">No order yet</span>');
      return;
    }
    filenames.forEach(fn => $chips.append('<span class="chip">'+fn+'</span>'));
  }

  function addTile(url){
    const fn = urlToFilename(url);
    const $tile = $('<div class="nsmg-tile" draggable="false"></div>')
      .attr('data-url', url).attr('data-fn', fn)
      .append('<div class="thumb"><img src="'+url+'" alt=""></div>')
      .append('<div class="meta"><span class="fn" title="'+fn+'">'+fn+'</span><button type="button" class="del" title="Remove">&times;</button></div>');
    $grid.append($tile);
  }

  function rebuildFromCSV(imagesCSV){
    $grid.empty();
    const urls = (imagesCSV||'').split(',').map(s=>s.trim()).filter(Boolean);
    if (!urls.length){ renderEmptyGrid(); renderChips([]); return; }
    urls.forEach(addTile);
    syncHiddenCSV();
    syncOrderFromGrid(); // <-- auto set order + chips
  }

  function ensureGrid(){
    if (!$grid.find('.nsmg-tile').length) { renderEmptyGrid(); renderChips([]); }
  }

  /* Media: Audio */
  $('#nsmg-choose-audio').on('click', function(e){
    e.preventDefault();
    const frame = wp.media({
      title:'Choose Audio', button:{text:'Use this audio'},
      library:{type:['audio/mpeg','audio/ogg','audio/wav','audio']}, multiple:false
    });
    frame.on('select', function(){
      const file = frame.state().get('selection').first().toJSON();
      $('#nsmg-audio-url').val(file.url);
      $('#nsmg-audio-preview').html('<audio controls src="'+file.url+'" style="max-width:420px;"></audio>');
    });
    frame.open();
  });

  /* Media: Images (multiple) */
  $('#nsmg-add-images').on('click', function(e){
    e.preventDefault();
    const frame = wp.media({
      title:'Add Images', button:{text:'Add selected images'},
      library:{type:'image'}, multiple:true
    });
    frame.on('select', function(){
      const sel = frame.state().get('selection').toJSON();
      if ($grid.find('.nsmg-empty').length) $grid.empty();
      sel.forEach(item=> addTile(item.url));
      syncHiddenCSV();
      syncOrderFromGrid();
    });
    frame.open();
  });

  /* Remove tile */
  $grid.on('click', '.nsmg-tile .del', function(){
    $(this).closest('.nsmg-tile').remove();
    if (!$grid.find('.nsmg-tile').length) { renderEmptyGrid(); }
    syncHiddenCSV();
    syncOrderFromGrid();
  });

  /* Sortable grid */
  $grid.sortable({
    items:'.nsmg-tile',
    placeholder:'nsmg-tile placeholder',
    stop:function(){
      syncHiddenCSV();
      syncOrderFromGrid();
    }
  });

  /* Manual order toggle */
  $manualToggle.on('change', function(){
    if ($(this).is(':checked')){
      $('#nsmg-order-manual').slideDown(120);
      // When enabling manual, pre-fill with latest grid order once
      if (!$order.val()){
        const fns = gridItems().map(x=>x.fn);
        $order.val(fns.join(', '));
      }
    } else {
      $('#nsmg-order-manual').slideUp(120);
      syncOrderFromGrid(); // resume auto-sync
    }
  });

  /* Prefill existing */
  rebuildFromCSV(PREFILL.images);
  if (PREFILL.order){
    // If existing manual order is present, respect it and show chips accordingly
    $manualToggle.prop('checked', true);
    $('#nsmg-order-manual').show().val(PREFILL.order);
    renderChips(PREFILL.order.split(',').map(s=>s.trim()).filter(Boolean));
  }
  if (PREFILL.audio){
    $('#nsmg-audio-url').val(PREFILL.audio);
    $('#nsmg-audio-preview').html('<audio controls src="'+PREFILL.audio+'" style="max-width:420px;"></audio>');
  }

  /* Validate on submit */
  $('#post').on('submit', function(){
    const items = gridItems();
    if (items.length < 4){ alert('Please add at least 4 images.'); return false; }
    const fns = items.map(x=>x.fn);
    const dup = fns.find((fn, idx)=> fns.indexOf(fn) !== idx);
    if (dup){ alert('Duplicate image filename detected: '+dup+'. Filenames must be unique.'); return false; }

    if ($manualToggle.prop('checked')){
      const manual = ($order.val()||'').split(',').map(s=>s.trim()).filter(Boolean);
      const unknown = manual.find(fn => !fns.includes(fn));
      if (unknown){ alert('Correct Order contains a filename not in the selected images: '+unknown); return false; }
    } else {
      // ensure manual field is in sync with grid order (filenames)
      $order.val(fns.join(', '));
    }
    syncHiddenCSV();
    return true;
  });

  /* -------- Live Preview -------- */
  function shuffle(arr){
    const a = arr.slice();
    for(let i=a.length-1;i>0;i--){ const j=Math.floor(Math.random()*(i+1)); [a[i],a[j]]=[a[j],a[i]]; }
    return a;
  }
  function openPreview(){
    const $modal = $('#nsmg-preview-modal');
    const $pal   = $modal.find('.nsmg-preview-palette');
    const $slots = $modal.find('.nsmg-preview-slots');

    const urls = gridItems().map(x=>x.url);
    const fns  = gridItems().map(x=>x.fn);
    if (!urls.length){ alert('Please add images first.'); return; }

    $pal.empty(); $slots.empty();

    // Slots show target order numbers
    fns.forEach((fn, idx)=>{
      const $slot = $('<div class="slot"><div class="slot-label">'+(idx+1)+'</div></div>');
      $slots.append($slot);
    });

    // Palette shows shuffled cards
    shuffle(urls).forEach(u=>{
      $pal.append('<div class="tile"><img src="'+u+'" alt=""></div>');
    });

    $modal.show();
  }
  $('#nsmg-live-preview').on('click', openPreview);
  $('#nsmg-preview-modal').on('click', '.nsmg-modal-close', function(){ $(this).closest('.nsmg-modal').hide(); });
})(jQuery);