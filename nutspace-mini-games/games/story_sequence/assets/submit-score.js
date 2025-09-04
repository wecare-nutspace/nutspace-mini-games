/*
  NutSpace – Submit Score helper (v1.0.0)
  Posts results to WP REST API so leaderboards can work.
*/
console.log('NSMG Submit Score loaded');

(function(){
  const REST = (window.NSMG_CFG && NSMG_CFG.rest) || (location.origin + '/wp-json/nutspace/v1/');

  /**
   * Submit a score
   * @param {Object} opts
   *   - child_id   (int|null)
   *   - grade_id   (int|string)
   *   - story_id   (int)
   *   - points     (int)
   *   - duration_s (int)
   */
  async function submitScore(opts){
    try{
      const res = await fetch(REST+'score',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
          child_id:   opts.child_id || null,
          grade_id:   opts.grade_id || null,
          story_id:   opts.story_id || null,
          points:     opts.points  || 0,
          duration_s: opts.duration_s || 0
        })
      });
      const j = await res.json();
      if(!res.ok){ console.error('Score submit failed', j); }
      else{ console.log('Score submitted!', j); }
      return j;
    }catch(e){
      console.error('Score submit error', e);
      return null;
    }
  }

  // Expose globally
  window.NSMGSubmitScore = submitScore;
})();
