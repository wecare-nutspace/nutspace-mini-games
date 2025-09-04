<?php
/**
 * Story Sequencing – Template (v0.9.0)
 */
?>
<div class="nsmg story-sequence">

  <!-- Intro Screen -->
  <div id="intro-screen">
    <h2>Story Sequencing Challenge</h2>
    <label for="childName">Your Name</label>
    <input id="childName" type="text" placeholder="Enter your name"/>
    <label for="childGrade">Grade</label>
    <select id="childGrade">
      <option value="">Select grade</option>
      <option value="kg">KG</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
      <option value="4">4</option>
    </select>
    <button id="startBtn" class="btn primary pill">Start Game</button>
  </div>

  <!-- Game Screen -->
  <div id="game-screen" class="hidden">
    <div class="hud">
      <h2 id="story-title">Story Title</h2>
      <audio id="story-audio" controls></audio>
      <div class="hud-bar">
        <span id="timer">00:00</span>
        <span id="lives"></span>
      </div>
      <p id="instruction-tip">Drag from the left, drop in order on the right</p>
    </div>
    <div class="board">
      <div id="palette" class="palette"></div>
      <div id="slots" class="slots"></div>
    </div>
    <div class="gameplay-buttons">
      <button id="checkOrderBtn" class="btn primary">Check My Order</button>
      <button id="resetBtn" class="btn">Reshuffle</button>
    </div>
    <div id="revealBanner" class="reveal-banner hidden">Correct Order</div>
  </div>

  <!-- End Screen -->
  <div id="end-screen" class="hidden">
    <div id="confettiEnd"></div>
    <div id="celebrateStrip" class="hidden"></div>
    <h2>Leaderboard</h2>
    <p><b id="player-name-display"></b> – Time: <span id="final-time"></span>, Score: <span id="final-score"></span></p>
    <button id="claimRewardBtn" class="btn primary pill">Claim Certificate</button>
    <button id="tryAnotherBtn" class="btn">Try Another Story</button>
  </div>

  <!-- Popup -->
  <div id="popup">
    <div class="popup-inner">
      <h2 id="popup-title"></h2>
      <div id="popup-msg"></div>
      <div id="popup-actions" class="popup-actions"></div>
    </div>
  </div>

</div>
