/*
  NutSpace – Story Sequencing (Frontend) v1.0.0 (patched from v0.9.0)
  - Uses NSMGCommon for popups, magic link, rewards
  - New: submits score to /wp-json/nsmg/v1/score + certificate link
*/
console.log('NSMG Story Sequencing v1.0.0 (patched)');

(function(){
  const $  = (s, r)=> (r||document).querySelector(s);
  const $$ = (s, r)=> Array.from((r||document).querySelectorAll(s));

  // REST namespaces
  const REST_NEW     = (window.NSMG_CFG && NSMG_CFG.rest) || (location.origin + '/wp-json/nsmg/v1/');       // new endpoints (scores, certs, etc.)
  const REST_LEGACY  = location.origin + '/wp-json/nutspace/v1/';                                            // legacy endpoints (stories, old submit)
  const REST_STORIES = REST_LEGACY; // keep your existing stories endpoint working as-is

  // Screens
  const intro = $('#intro-screen'), game = $('#game-screen'), end = $('#end-screen');

  // Intro
  const childNameEl=$('#childName'), childGradeEl=$('#childGrade'), startBtn=$('#startBtn');

  // HUD
  const storyTitleEl=$('#story-title'), audioEl=$('#story-audio'), timerEl=$('#timer'), livesEl=$('#lives'), tip=$('#instruction-tip');

  // Board
  const palette=$('#palette'), slots=$('#slots');

  // Buttons
  const checkBtn=$('#checkOrderBtn'), resetBtn=$('#resetBtn');

  // End
  const endName=$('#player-name-display'), finalTime=$('#final-time'), finalScore=$('#final-score'),
        claimBtn=$('#claimRewardBtn'), tryAnotherBtn=$('#tryAnotherBtn');
  const confettiEnd=$('#confettiEnd'), celebrate=$('#celebrateStrip');

  // We may add a Download Certificate button dynamically if not present
  let certBtn = $('#nsmg-cert-btn');

  const state = {
    child:{name:'',grade:''},
    story:null,
    lives:3,
    seconds:0,
    timerId:null,
    lastScore:0,
    isTouch:(('ontouchstart' in window) || navigator.maxTouchPoints>0)
  };

  /* Utils */
  const fmt = (sec)=>{ sec=+sec||0; const m=('0'+Math.floor(sec/60)).slice(-2), s=('0'+(sec%60)).slice(-2); return `${m}:${s}`; };
  function keepTop(){ try{ window.scrollTo({top:0, behavior:'auto'});}catch(e){ window.scrollTo(0,0); } }
  function stopTimer(){ if(state.timerId){ clearInterval(state.timerId); state.timerId=null; } }
  function startTimer(){ stopTimer(); state.seconds=0; if (timerEl) timerEl.textContent='00:00'; state.timerId=setInterval(()=>{ state.seconds++; if (timerEl) timerEl.textContent=fmt(state.seconds); },1000); }
  function stopAudio(){ try{ audioEl.pause(); audioEl.currentTime=0; }catch(e){} }
  function heart(on){ const d=document.createElement('span'); d.className='heart'+(on?'':' off'); d.textContent='❤'; return d; }
  function renderLives(){ if (!livesEl) return; livesEl.innerHTML=''; for(let i=0;i<3;i++) livesEl.appendChild(heart(i<state.lives)); }
  function confetti(host){
    if(!host) return;
    const colors=['#fec10c','#fee9b6','#1d1f4c','#ffffff','#f59e0b','#93c5fd','#34d399'];
    for(let i=0;i<60;i++){
      const s=document.createElement('span'), size=6+Math.random()*8;
      s.style.position='absolute'; s.style.top='-10px'; s.style.left=(Math.random()*100)+'%';
      s.style.width=size+'px'; s.style.height=size+'px'; s.style.background=colors[Math.floor(Math.random()*colors.length)];
      s.style.borderRadius=(Math.random()<0.35?'50%':'4px'); s.style.opacity='0.95';
      s.style.transform=`rotate(${Math.random()*360}deg)`; s.style.animation=`ns-confetti-fall ${1100+Math.random()*900}ms ease-out ${Math.random()*200}ms 1 both`;
      host.appendChild(s); setTimeout(()=> s.remove(), 2400);
    }
  }

  /* Data */
  async function fetchStories(grade){
    const url = REST_STORIES+'stories'+(grade?('?grade='+encodeURIComponent(grade)):'');
    try{ const j=await fetch(url).then(r=>r.json()); return j?.stories||[]; }catch(e){ return []; }
  }
  function populateGrades(){
    const sel=childGradeEl;
    if (!sel) return;
    sel.innerHTML = `
      <option value="">Select grade</option>
      <option value="kg">KG</option><option value="1">1</option>
      <option value="2">2</option><option value="3">3</option><option value="4">4</option>`;
  }

  /* Drag / Drop (+ tap-to-place) */
  let selectedKey=null, selectedFrom=null;
  function clearSelection(){ selectedKey=null; selectedFrom=null; $$('.tile',palette).forEach(t=>t.classList.remove('selected')); $$('.tile',slots).forEach(t=>t.classList.remove('selected')); }
  function buildTileEl(key,src,fromIndex){
    const el=document.createElement('div'); el.className='tile'; el.dataset.key=key;
    const img=document.createElement('img'); img.src=src; el.appendChild(img);
    el.draggable=!state.isTouch;
    if(!state.isTouch){
      el.addEventListener('dragstart', e=>{ e.dataTransfer.setData('text/key',key); e.dataTransfer.setData('text/from', (typeof fromIndex==='number')?('slot:'+fromIndex):'palette'); });
    } else {
      el.addEventListener('click', (ev)=>{ ev.stopPropagation(); clearSelection(); el.classList.add('selected'); selectedKey=key; selectedFrom=(typeof fromIndex==='number')?{slot:fromIndex}:'palette'; }, {passive:true});
    }
    return el;
  }
  function findSlotWithKey(key){
    const all=$$('.slot',slots);
    for(let i=0;i<all.length;i++){ const t=all[i].querySelector('.tile'); if(t && t.dataset.key===key) return {slotEl:all[i], index:i}; }
    return null;
  }
  function keyToSrc(key){
    const imgs=(state.story?.images)||[];
    for(const u of imgs){ const fn=(u.split('/').pop()||'').toLowerCase(); if(fn===key.toLowerCase()) return u; }
    return imgs[0]||'';
  }
  function placeKeyInSlot(targetIndex,key){
    const slotEls=$$('.slot',slots); const target=slotEls[targetIndex]; if(!target) return;
    const existing=findSlotWithKey(key);
    if(existing && existing.index!==targetIndex){ // remove from old slot
      existing.slotEl.innerHTML=''; const lbl=document.createElement('div'); lbl.className='slot-label'; lbl.textContent=(existing.index+1); existing.slotEl.appendChild(lbl);
    }
    target.innerHTML=''; target.appendChild(buildTileEl(key, keyToSrc(key), targetIndex));
  }
  function renderSlots(n){
    if (!slots) return;
    slots.innerHTML='';
    for(let i=0;i<n;i++){
      const s=document.createElement('div'); s.className='slot'; s.dataset.index=i;
      const lab=document.createElement('div'); lab.className='slot-label'; lab.textContent=(i+1); s.appendChild(lab);
      if(!state.isTouch){
        s.addEventListener('dragover', e=>{ e.preventDefault(); s.classList.add('over'); });
        s.addEventListener('dragleave', ()=> s.classList.remove('over'));
        s.addEventListener('drop', e=>{ e.preventDefault(); s.classList.remove('over'); const key=e.dataTransfer.getData('text/key'); if(!key) return; placeKeyInSlot(i,key); });
      } else {
        s.addEventListener('click', ()=>{ if(!selectedKey) return; placeKeyInSlot(i,selectedKey); clearSelection(); }, {passive:true});
      }
      slots.appendChild(s);
    }
  }
  function renderPalette(images){
    if (!palette) return;
    palette.innerHTML='';
    images.forEach((src,i)=>{
      const key=(src.split('/').pop()||('img'+i));
      const t=buildTileEl(key,src,'palette');
      if(!state.isTouch){ t.addEventListener('dragstart', e=>{ e.dataTransfer.setData('text/key',key); e.dataTransfer.setData('text/from','palette'); }); }
      else { t.addEventListener('click', ()=>{ if(selectedKey===key && selectedFrom==='palette'){ clearSelection(); return; } clearSelection(); selectedKey=key; selectedFrom='palette'; t.classList.add('selected'); }, {passive:true}); }
      palette.appendChild(t);
    });
  }
  const currentOrder = ()=> $$('.slot',slots).map(s=> s.querySelector('.tile')?.dataset.key || null);
  const completePlaced = ()=> currentOrder().every(Boolean);
  const scoreFor = (timeSec,mistakes)=> Math.max(0, 100 + Math.max(0,60 - Math.floor(timeSec/3)) - mistakes*5);

  function reshuffle(){ if(!state.story) return; renderPalette(state.story.images||[]); renderSlots((state.story.images||[]).length||4); }

  function revealCorrectOrderAnimated(){
    return new Promise((resolve)=>{
      $('#revealBanner')?.classList.remove('hidden');
      if (window.matchMedia('(max-width: 520px)').matches){
        const col = palette?.parentElement?.parentElement; if (col) col.classList.add('hidden-mobile');
      }
      if (!slots) return resolve();
      slots.innerHTML='';
      const order=state.story.correctOrder||[];
      order.forEach((key,i)=>{
        const slot=document.createElement('div'); slot.className='slot';
        const tile=buildTileEl(key, keyToSrc(key), i); tile.style.animation=`fadeIn .2s ease ${i*0.22}s both`;
        slot.appendChild(tile);
        setTimeout(()=> slots.appendChild(slot), i*220);
      });
      keepTop(); setTimeout(()=> resolve(), (order.length-1)*220 + 2200);
    });
  }

  /* NEW: Submit to nsmg/v1/score (with graceful legacy fallback) */
  async function submitScoreNew(payload){
    // Prefer helper if loaded
    if (window.NSMGScore && typeof NSMGScore.postScore === 'function') {
      return NSMGScore.postScore(payload);
    }
    // Direct REST call
    const res = await fetch(REST_NEW+'score',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const json = await res.json().catch(()=> ({}));
    if (!res.ok) throw json;
    return json;
  }

  function ensureCertButton(){
    if (certBtn && end && end.contains(certBtn)) return certBtn;
    // Create a simple button if it doesn't exist
    certBtn = document.createElement('a');
    certBtn.id = 'nsmg-cert-btn';
    certBtn.className = 'btn primary';
    certBtn.style.display = 'none';
    certBtn.style.marginLeft = '8px';
    certBtn.textContent = 'Download Certificate';
    certBtn.target = '_blank';
    certBtn.rel = 'noopener';
    // Try to place it next to claim button or final score
    const anchorHost = claimBtn?.parentElement || end?.querySelector('.end-actions') || end;
    anchorHost && anchorHost.appendChild(certBtn);
    return certBtn;
  }

  /* Flow */
  async function startFlow(){
    state.child.name  = (childNameEl?.value||'').trim();
    state.child.grade = (childGradeEl?.value||'').trim();

    // Soft, non-blocking pre-play verify (sends link, allows play)
    try{
      if (window.NSMGCommon && NSMGCommon.sendMagicLink){
        NSMGCommon.showPopup(
          'One-time verification',
          `<p>Enter your email to save progress and get free learning resources. We’ll send a verification link.</p>
           <input type="email" id="preplayEmail" placeholder="you@example.com" style="width:100%">
           <p style="font-size:.9rem;opacity:.85">This is a one-time verification. You can continue to play now.</p>`,
          [
            {label:'Send Link & Continue', primary:true, onClick:()=>{
              const em=(document.getElementById('preplayEmail')?.value||'').trim();
              if (em) NSMGCommon.sendMagicLink(em);
              proceed();
            }},
            {label:'Skip for now', onClick: proceed}
          ]
        );
      } else {
        proceed();
      }
    }catch(e){ proceed(); }

    async function proceed(){
      let stories = await fetchStories(state.child.grade);
      if (!stories.length) stories = await fetchStories('');
      if (!stories.length){
        (window.NSMGCommon?.showPopup ? NSMGCommon.showPopup('No stories','Please add “Stories” in WP Admin for this grade.') : alert('No stories found.'));
        return;
      }
      state.story = stories[0];

      if (storyTitleEl) storyTitleEl.textContent = state.story.title || 'Story';
      if (audioEl) { audioEl.src = state.story.audio || ''; audioEl.style.marginTop='10px'; }

      renderPalette(state.story.images||[]); renderSlots((state.story.images||[]).length||4);
      state.lives=3; renderLives(); startTimer();

      intro?.classList.add('hidden'); end?.classList.add('hidden'); game?.classList.remove('hidden'); keepTop();
      if (tip){
        tip.textContent = state.isTouch
          ? 'Tap a picture (or a filled slot) to select, then tap a slot to place it'
          : 'Drag from the left (or between slots) and drop in order on the right';
      }
    }
  }

  function onCheckOrder(){
    if(!completePlaced()){ window.NSMGCommon?.showPopup?.('Almost there!','Please place all the images before checking.') || alert('Please place all images.'); return; }
    const order=currentOrder(), correct=state.story.correctOrder||[]; let mistakes=0;
    for(let i=0;i<order.length;i++){ if(order[i]!==correct[i]) mistakes++; }

    if(mistakes===0){
      stopTimer(); stopAudio();
      const score=scoreFor(state.seconds,0); state.lastScore=score;

      // ---------- NEW SUBMIT LOGIC ----------
      const childId  = window.NSMG_CURRENT_CHILD_ID || null;
      const storyId  = parseInt(state.story?.id, 10) || 0;
      // Try to get a numeric grade_id. Prefer server-provided story.grade_id; else attempt parseInt of the UI value; else 0.
      let gradeId = parseInt(state.story?.grade_id, 10);
      if (!Number.isFinite(gradeId)) {
        const raw = (state.child.grade||'').trim();
        gradeId = /^\d+$/.test(raw) ? parseInt(raw, 10) : 0;
      }

      // First try the new endpoint. If it fails (e.g., missing ids), silently fall back to legacy submit.
      (async()=>{
        let scoreRes = null;
        try{
          scoreRes = await submitScoreNew({
            child_id:   childId,
            grade_id:   gradeId,
            story_id:   storyId,
            points:     score,
            duration_s: state.seconds
          });
        }catch(e){
          // Fallback to legacy submit (best-effort)
          try{
            await fetch(REST_LEGACY+'submit', {
              method:'POST',
              headers:{'Content-Type':'application/json'},
              body: JSON.stringify({
                game_key:'story_sequence',
                child_name:state.child.name,
                grade:state.child.grade,
                score,
                time_sec:state.seconds
              })
            });
          }catch(_) {}
        }

        // If new score API returned an id, show certificate button
        if (scoreRes && scoreRes.id){
          const base = (REST_NEW || '/wp-json/nsmg/v1/').replace(/\/nsmg\/v1\/?$/,'');
          const certUrl = `${base}/nsmg/v1/cert/${scoreRes.id}.png`;
          const btn = ensureCertButton();
          btn.href = certUrl;
          btn.style.display = 'inline-flex';
        }
      })();
      // ---------- END NEW SUBMIT LOGIC ----------

      if (endName) endName.textContent=state.child.name||'Player';
      if (finalTime) finalTime.textContent=fmt(state.seconds);
      if (finalScore) finalScore.textContent=score;

      game?.classList.add('hidden'); end?.classList.remove('hidden'); keepTop();

      if (celebrate){ celebrate.classList.add('hidden'); celebrate.innerHTML=''; celebrate.style.display=''; }
      if (confettiEnd){ confettiEnd.innerHTML=''; confettiEnd.style.display='block'; }
      setTimeout(()=>{ confetti(confettiEnd); setTimeout(()=>{ if(confettiEnd){confettiEnd.innerHTML=''; confettiEnd.style.display='none';} if(celebrate){ celebrate.innerHTML='<span class="badge">Great!</span><div><span class="msg">🎉 Well done!</span><span class="sub">Score: '+score+' • Time: '+fmt(state.seconds)+'</span></div>'; celebrate.classList.remove('hidden'); celebrate.style.display='flex'; } }, 2200); }, 800);

    } else {
      state.lives--; renderLives();
      if(state.lives<=0){
        stopTimer(); stopAudio();
        revealCorrectOrderAnimated().then(()=>{
          window.NSMGCommon?.showPopup?.('Out of lives','This is the correct order. You may need some practice.',[
            {label:'Get Practice Pack', primary:true, onClick:()=> NSMGCommon.openRewardModal({ type:'pack', child:{name:state.child.name,grade:state.child.grade}, parent:{} }) },
            {label:'Try Another Story', onClick:()=>{ intro?.classList.remove('hidden'); game?.classList.add('hidden'); end?.classList.add('hidden'); keepTop(); setTimeout(()=> startFlow(), 60); } }
          ]);
        });
      } else {
        window.NSMGCommon?.showPopup?.('Try again!','That order is not quite right — we’ve reshuffled the cards for you.',[{label:'OK',primary:true,onClick:()=> reshuffle()}]) || reshuffle();
      }
    }
  }

  function onReshuffle(){ reshuffle(); }
  function onClaim(){
    NSMGCommon?.openRewardModal?.({
      type:'cert',
      child:{name:state.child.name,grade:state.child.grade},
      parent:{}
    });
  }
  function onAnother(){ intro?.classList.remove('hidden'); game?.classList.add('hidden'); end?.classList.add('hidden'); keepTop(); }

  /* Bind */
  startBtn?.addEventListener('click', startFlow);
  checkBtn?.addEventListener('click', onCheckOrder);
  resetBtn?.addEventListener('click', onReshuffle);
  claimBtn?.addEventListener('click', onClaim);
  tryAnotherBtn?.addEventListener('click', onAnother);

  /* Init */
  (function init(){ populateGrades(); })();
})();
