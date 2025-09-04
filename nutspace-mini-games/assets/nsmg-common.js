/* ==========================================
   NutSpace Mini Games – Common JS (v0.9.0)
   ========================================== */
console.log('NSMG Common v0.9.0 loaded');

window.NSMGCommon = (function(){
  const apiBase = (window.NSMG_CFG?.rest) || (location.origin + '/wp-json/nutspace/v1/');

  // --- Helpers ---
  function keepTop(){
    try { window.scrollTo({top:0,left:0,behavior:'auto'}); }
    catch { window.scrollTo(0,0); }
  }

  // --- Popup system ---
  function showPopup(title, html, actions){
    const p = document.getElementById('popup');
    if(!p){ alert(title+'\n\n'+html); return; }
    document.getElementById('popup-title').textContent = title||'Notice';
    document.getElementById('popup-msg').innerHTML = html||'';
    const a=document.getElementById('popup-actions'); a.innerHTML='';
    (actions?.length?actions:[{label:'OK',primary:true}]).forEach(x=>{
      const b=document.createElement('button');
      b.className='btn pill'+(x.primary?' primary':'');
      b.textContent=x.label||'OK';
      b.onclick=()=>{ p.classList.remove('show'); x.onClick&&x.onClick(); };
      a.appendChild(b);
    });
    p.classList.add('show');
    keepTop();
  }

  // --- Magic Link ---
  async function sendMagicLink(email){
    try{
      const res = await fetch(apiBase+'magiclink',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({email})
      });
      const j=await res.json();
      if(j?.ok){ showPopup('One-time verification','We’ve sent a login link to <b>'+email+'</b>.<br/>Check your inbox.'); }
      else { showPopup('Error', j?.msg||'Could not send email.'); }
    }catch(e){
      showPopup('Error','Failed to reach server. '+e.message);
    }
  }

  // --- Rewards (Certificate / Practice Pack) ---
  function openRewardModal(opts){
    const child = opts?.child||{};
    const parent = opts?.parent||{};
    const isCert = opts?.type==='cert';

    let html = '<p>'+ (isCert
      ? 'Enter your details to claim your certificate.'
      : 'Enter your details to get your free practice pack.'
    ) +'</p>';

    html += '<input id="rewardParentName" placeholder="Your Name" value="'+(parent.name||'')+'"/>';
    html += '<input id="rewardParentEmail" placeholder="Your Email" value="'+(parent.email||'')+'"/>';
    html += '<input id="rewardParentPhone" placeholder="Your Phone (optional)" value="'+(parent.phone||'')+'"/>';

    showPopup(isCert?'Claim Certificate':'Get Practice Pack', html, [
      {
        label: isCert?'Claim Certificate':'Get Practice Pack',
        primary:true,
        onClick: async ()=>{
          const name=document.getElementById('rewardParentName').value.trim();
          const email=document.getElementById('rewardParentEmail').value.trim();
          const phone=document.getElementById('rewardParentPhone').value.trim();
          if(!email) return showPopup('Missing','Please enter your email address.');
          try{
            await fetch(apiBase+'reward',{
              method:'POST',
              headers:{'Content-Type':'application/json'},
              body:JSON.stringify({
                type:isCert?'certificate':'pack',
                child, parent:{name,email,phone}
              })
            });
            showPopup('Sent!','Your '+(isCert?'certificate':'practice pack')+' will be emailed shortly.');
          }catch(e){ showPopup('Error','Could not send reward: '+e.message); }
        }
      },
      {label:'Cancel'}
    ]);
  }

  // Public API
  return {
    showPopup,
    sendMagicLink,
    openRewardModal
  };
})();
