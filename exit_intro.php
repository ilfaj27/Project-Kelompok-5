<?php
// exit_intro.php - Halaman Transisi Keluar HoopBall
// Animasi: Bola basket masuk ring + Scoreboard + Confetti + Spotlight

// Ambil URL tujuan dari parameter, default ke index.php
$destination = isset($_GET['to']) ? htmlspecialchars($_GET['to']) : 'intro.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="refresh" content="4.5;url=<?= $destination ?>">
<title>HoopBall — Sampai Jumpa</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --orange: #FF4500;
    --orange-light: #FF6B35;
    --orange-dark: #CC3700;
    --orange-glow: rgba(255, 69, 0, 0.6);
    --dark: #0A0E17;
    --darker: #05070A;
}

*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
html, body { width: 100%; height: 100vh; font-family: 'Barlow', sans-serif; overflow: hidden; background: var(--darker); }

/* ═══════════════════════════════════════════════════════════════
   STAGE: Basketball Court Floor
   ═══════════════════════════════════════════════════════════════ */
.exit-stage {
    position: absolute; inset: 0;
    background: 
        radial-gradient(ellipse at 50% 100%, rgba(255,69,0,0.15) 0%, transparent 60%),
        linear-gradient(180deg, var(--darker) 0%, #0f1520 40%, #1a2332 70%, var(--darker) 100%);
    animation: stageIn 0.8s cubic-bezier(0.22, 1, 0.36, 1) 0.1s both;
}
@keyframes stageIn {
    0% { opacity: 0; transform: scale(1.2) translateY(50px); }
    100% { opacity: 1; transform: scale(1) translateY(0); }
}

.court-lines {
    position: absolute; inset: 0; opacity: 0.08;
}
.court-lines::before {
    content: '';
    position: absolute; bottom: 0; left: 50%; transform: translateX(-50%);
    width: 600px; height: 400px;
    border: 3px solid var(--orange); border-bottom: none;
    border-radius: 300px 300px 0 0;
}
.court-lines::after {
    content: '';
    position: absolute; bottom: 0; left: 50%; transform: translateX(-50%);
    width: 200px; height: 150px;
    border: 3px solid var(--orange); border-bottom: none;
    border-radius: 100px 100px 0 0;
}

/* ═══════════════════════════════════════════════════════════════
   SPOTLIGHT EFFECT
   ═══════════════════════════════════════════════════════════════ */
.exit-spotlight {
    position: absolute; top: -100px; left: 50%; transform: translateX(-50%);
    width: 400px; height: 600px;
    background: radial-gradient(ellipse at 50% 0%, rgba(255,69,0,0.3) 0%, transparent 70%);
    opacity: 0; pointer-events: none; mix-blend-mode: screen;
    animation: spotlightIn 1s ease-out 0.3s forwards;
}
@keyframes spotlightIn {
    from { opacity: 0; transform: translateX(-50%) translateY(-50px); }
    to { opacity: 1; transform: translateX(-50%) translateY(0); }
}

/* ═══════════════════════════════════════════════════════════════
   HOOP & BACKBOARD
   ═══════════════════════════════════════════════════════════════ */
.exit-hoop-container {
    position: absolute; top: 15%; left: 50%; transform: translateX(-50%);
    width: 200px; height: 200px;
    animation: hoopAppear 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) 0.5s both;
}
@keyframes hoopAppear {
    from { opacity: 0; transform: translateX(-50%) translateY(-100px) scale(0.5); }
    to { opacity: 1; transform: translateX(-50%) translateY(0) scale(1); }
}

.backboard {
    position: absolute; top: 0; left: 50%; transform: translateX(-50%);
    width: 160px; height: 110px;
    background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
    border: 3px solid rgba(255,255,255,0.2); border-radius: 4px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
}
.backboard::after {
    content: '';
    position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
    width: 60px; height: 45px;
    border: 2px solid var(--orange); border-radius: 2px; opacity: 0.6;
}

.rim {
    position: absolute; top: 105px; left: 50%; transform: translateX(-50%);
    width: 100px; height: 12px;
    border: 4px solid #C0392B; border-radius: 50%;
    background: linear-gradient(90deg, #C0392B, #E74C3C, #C0392B);
    box-shadow: 0 4px 20px rgba(192, 57, 43, 0.4), 0 0 30px rgba(255,69,0,0.2);
}

.net {
    position: absolute; top: 115px; left: 50%; transform: translateX(-50%);
    width: 90px; height: 80px; overflow: hidden;
}
.net::before {
    content: '';
    position: absolute; inset: 0;
    background: 
        linear-gradient(90deg, transparent 48%, rgba(255,255,255,0.2) 49%, rgba(255,255,255,0.2) 51%, transparent 52%),
        linear-gradient(60deg, transparent 48%, rgba(255,255,255,0.15) 49%, rgba(255,255,255,0.15) 51%, transparent 52%),
        linear-gradient(-60deg, transparent 48%, rgba(255,255,255,0.15) 49%, rgba(255,255,255,0.15) 51%, transparent 52%);
    clip-path: polygon(10% 0%, 90% 0%, 75% 100%, 25% 100%);
    animation: netSway 2s ease-in-out infinite;
}
@keyframes netSway {
    0%, 100% { transform: skewX(-3deg) scaleY(1); }
    50% { transform: skewX(3deg) scaleY(1.05); }
}

/* ═══════════════════════════════════════════════════════════════
   BASKETBALL — Arc Shot Animation
   ═══════════════════════════════════════════════════════════════ */
.exit-ball {
    position: absolute; width: 70px; height: 70px;
    left: 50%; bottom: 20%; transform: translateX(-50%);
    z-index: 50;
    animation: ballArc 2.5s cubic-bezier(0.25, 0.46, 0.45, 0.94) 0.8s both;
}
.exit-ball svg {
    width: 100%; height: 100%;
    filter: drop-shadow(0 15px 30px rgba(0,0,0,0.5));
}
@keyframes ballArc {
    0% { 
        opacity: 1; left: 50%; bottom: 20%; 
        transform: translateX(-50%) scale(0.5) rotate(0deg); 
    }
    30% { 
        left: 50%; bottom: 55%; 
        transform: translateX(-50%) scale(1) rotate(360deg); 
    }
    50% { 
        left: 50%; bottom: 62%; 
        transform: translateX(-50%) scale(1.1) rotate(540deg); 
    }
    55% { 
        left: 50%; bottom: 60%; 
        transform: translateX(-50%) scale(1) rotate(600deg); 
    }
    60% { 
        left: 50%; bottom: 58%; 
        transform: translateX(-50%) scale(0.95) rotate(630deg); 
    }
    65% { 
        opacity: 1; left: 50%; bottom: 56%; 
        transform: translateX(-50%) scale(0.9) rotate(650deg); 
    }
    70% { 
        opacity: 0.8; left: 50%; bottom: 30%; 
        transform: translateX(-50%) scale(0.7) rotate(720deg); 
    }
    100% { 
        opacity: 0; left: 50%; bottom: -10%; 
        transform: translateX(-50%) scale(0.3) rotate(900deg); 
    }
}

.ball-shadow-floor {
    position: absolute; bottom: 18%; left: 50%; transform: translateX(-50%);
    width: 60px; height: 15px;
    background: radial-gradient(ellipse, rgba(0,0,0,0.4) 0%, transparent 70%);
    border-radius: 50%; opacity: 0; z-index: 49;
    animation: shadowArc 2.5s ease-out 0.8s both;
}
@keyframes shadowArc {
    0% { opacity: 0.4; transform: translateX(-50%) scale(1); }
    30% { opacity: 0.2; transform: translateX(-50%) scale(0.6); }
    50% { opacity: 0; transform: translateX(-50%) scale(0.3); }
    100% { opacity: 0; transform: translateX(-50%) scale(0); }
}

/* ═══════════════════════════════════════════════════════════════
   SWISH EFFECT (when ball goes through net)
   ═══════════════════════════════════════════════════════════════ */
.swish-effect {
    position: absolute; top: 58%; left: 50%; transform: translateX(-50%);
    width: 120px; height: 80px; opacity: 0; pointer-events: none; z-index: 45;
    animation: swishShow 0.1s ease-out 1.35s forwards;
}
.swish-particles { position: absolute; inset: 0; }
.swish-particle {
    position: absolute; width: 4px; height: 4px;
    background: var(--orange); border-radius: 50%; opacity: 0;
}
.swish-particle:nth-child(1) { animation: swishBurst 0.8s ease-out 1.35s forwards; left: 50%; top: 0; --sx: -30px; --sy: 40px; }
.swish-particle:nth-child(2) { animation: swishBurst 0.8s ease-out 1.38s forwards; left: 50%; top: 0; --sx: 30px; --sy: 35px; }
.swish-particle:nth-child(3) { animation: swishBurst 0.8s ease-out 1.4s forwards; left: 50%; top: 0; --sx: -15px; --sy: 50px; }
.swish-particle:nth-child(4) { animation: swishBurst 0.8s ease-out 1.42s forwards; left: 50%; top: 0; --sx: 15px; --sy: 45px; }
.swish-particle:nth-child(5) { animation: swishBurst 0.8s ease-out 1.45s forwards; left: 50%; top: 0; --sx: -40px; --sy: 30px; }
.swish-particle:nth-child(6) { animation: swishBurst 0.8s ease-out 1.48s forwards; left: 50%; top: 0; --sx: 40px; --sy: 25px; }
@keyframes swishShow { from { opacity: 1; } to { opacity: 1; } }
@keyframes swishBurst {
    0% { opacity: 1; transform: translate(-50%, 0) scale(1); }
    100% { opacity: 0; transform: translate(calc(-50% + var(--sx)), var(--sy)) scale(0); }
}

/* ═══════════════════════════════════════════════════════════════
   FLASH BULB EFFECT
   ═══════════════════════════════════════════════════════════════ */
.flash-bulb {
    position: absolute; inset: 0; background: #fff;
    opacity: 0; pointer-events: none; z-index: 100;
    animation: flashPop 0.3s ease-out 1.3s forwards;
}
@keyframes flashPop {
    0% { opacity: 0; }
    10% { opacity: 1; }
    100% { opacity: 0; }
}

/* ═══════════════════════════════════════════════════════════════
   SCOREBOARD
   ═══════════════════════════════════════════════════════════════ */
.exit-scoreboard {
    position: absolute; top: 8%; left: 50%; transform: translateX(-50%);
    background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
    border: 3px solid var(--orange); border-radius: 12px;
    padding: 16px 40px; display: flex; align-items: center; gap: 24px;
    opacity: 0;
    box-shadow: 0 0 40px rgba(255,69,0,0.2), inset 0 0 20px rgba(255,69,0,0.05);
    z-index: 20;
    animation: scoreboardIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) 0.3s both;
}
@keyframes scoreboardIn {
    from { opacity: 0; transform: translateX(-50%) translateY(-30px) scale(0.8); }
    to { opacity: 1; transform: translateX(-50%) translateY(0) scale(1); }
}

.score-team { text-align: center; }
.score-team-name { font-size: 10px; color: rgba(255,255,255,0.5); font-weight: 700; letter-spacing: 2px; text-transform: uppercase; }
.score-team-logo { font-size: 24px; color: var(--orange); margin: 4px 0; }
.score-divider { width: 2px; height: 40px; background: linear-gradient(180deg, transparent, var(--orange), transparent); }
.score-points { font-family: 'Barlow Condensed', sans-serif; font-size: 42px; font-weight: 900; color: #fff; line-height: 1; }
.score-points span { font-size: 14px; color: var(--orange); }
.score-timer { font-size: 11px; color: rgba(255,255,255,0.4); font-weight: 700; letter-spacing: 3px; margin-top: 4px; }

.score-points { animation: scoreTick 0.3s ease-out 1.4s both; }
@keyframes scoreTick {
    0% { transform: scale(1); }
    50% { transform: scale(1.3); color: var(--orange); }
    100% { transform: scale(1); }
}

/* ═══════════════════════════════════════════════════════════════
   CONFETTI EXPLOSION
   ═══════════════════════════════════════════════════════════════ */
.confetti-container {
    position: absolute; inset: 0; pointer-events: none; overflow: hidden; z-index: 60;
}
.confetti-piece {
    position: absolute; width: 10px; height: 10px; opacity: 0;
    top: 50%; left: 50%;
    animation: confettiExplode 1.5s ease-out forwards;
}
.confetti-piece:nth-child(1) { background: var(--orange); --cx: -200px; --cy: -300px; --cr: 720deg; animation-delay: 1.4s; }
.confetti-piece:nth-child(2) { background: #FF6B35; --cx: 150px; --cy: -350px; --cr: -540deg; animation-delay: 1.45s; }
.confetti-piece:nth-child(3) { background: #FFD700; --cx: 250px; --cy: -200px; --cr: 360deg; animation-delay: 1.5s; }
.confetti-piece:nth-child(4) { background: var(--orange); --cx: -150px; --cy: -400px; --cr: -720deg; animation-delay: 1.55s; }
.confetti-piece:nth-child(5) { background: #FF8C42; --cx: 100px; --cy: -450px; --cr: 450deg; animation-delay: 1.6s; }
.confetti-piece:nth-child(6) { background: #FFD700; --cx: -250px; --cy: -250px; --cr: -360deg; animation-delay: 1.65s; }
.confetti-piece:nth-child(7) { background: var(--orange); --cx: 300px; --cy: -300px; --cr: 630deg; animation-delay: 1.7s; }
.confetti-piece:nth-child(8) { background: #FF6B35; --cx: -100px; --cy: -350px; --cr: -450deg; animation-delay: 1.75s; }
.confetti-piece:nth-child(9) { background: #FFD700; --cx: 200px; --cy: -400px; --cr: 540deg; animation-delay: 1.8s; }
.confetti-piece:nth-child(10) { background: var(--orange); --cx: -300px; --cy: -200px; --cr: -630deg; animation-delay: 1.85s; }
.confetti-piece:nth-child(11) { background: #FF8C42; --cx: 50px; --cy: -500px; --cr: 720deg; animation-delay: 1.9s; }
.confetti-piece:nth-child(12) { background: #FFD700; --cx: -200px; --cy: -450px; --cr: -540deg; animation-delay: 1.95s; }
@keyframes confettiExplode {
    0% { opacity: 1; transform: translate(-50%, -50%) scale(1) rotate(0deg); }
    100% { opacity: 0; transform: translate(calc(-50% + var(--cx)), calc(-50% + var(--cy))) scale(0) rotate(var(--cr)); }
}

/* ═══════════════════════════════════════════════════════════════
   TEXT REVEAL
   ═══════════════════════════════════════════════════════════════ */
.exit-message {
    position: absolute; bottom: 15%; left: 50%; transform: translateX(-50%);
    text-align: center; opacity: 0; z-index: 70;
    animation: messageIn 0.8s ease-out 2s both;
}
@keyframes messageIn {
    from { opacity: 0; transform: translateX(-50%) translateY(30px); }
    to { opacity: 1; transform: translateX(-50%) translateY(0); }
}

.exit-message-tagline {
    font-size: 12px; font-weight: 700; letter-spacing: 6px;
    text-transform: uppercase; color: var(--orange); margin-bottom: 12px;
}

.exit-message-title {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 56px; font-weight: 900; color: #fff;
    letter-spacing: 4px; line-height: 1;
}
.exit-message-title span { color: var(--orange); position: relative; }
.exit-message-title span::after {
    content: ''; position: absolute; bottom: 5px; left: 0;
    width: 100%; height: 8px; background: var(--orange); opacity: 0.3; border-radius: 2px;
}

.exit-message-sub {
    font-size: 14px; color: rgba(255,255,255,0.4);
    margin-top: 16px; letter-spacing: 2px;
}

/* ═══════════════════════════════════════════════════════════════
   LOADING BAR
   ═══════════════════════════════════════════════════════════════ */
.exit-loading-bar {
    position: absolute; bottom: 8%; left: 50%; transform: translateX(-50%);
    width: 200px; height: 3px;
    background: rgba(255,255,255,0.1); border-radius: 3px;
    overflow: hidden; opacity: 0; z-index: 70;
    animation: loadingBarIn 0.5s ease-out 2.3s both;
}
@keyframes loadingBarIn { from { opacity: 0; } to { opacity: 1; } }

.exit-loading-fill {
    height: 100%; width: 0%;
    background: linear-gradient(90deg, var(--orange), #FF6B35);
    border-radius: 3px; box-shadow: 0 0 10px var(--orange-glow);
    animation: loadingFill 2s ease-out 2.3s forwards;
}
@keyframes loadingFill { 0% { width: 0%; } 100% { width: 100%; } }

/* ═══════════════════════════════════════════════════════════════
   VIGNETTE
   ═══════════════════════════════════════════════════════════════ */
.exit-vignette {
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at center, transparent 20%, rgba(0,0,0,0.7) 100%);
    opacity: 0; pointer-events: none; z-index: 90;
    animation: vignetteIn 1s ease-out 0.2s forwards;
}
@keyframes vignetteIn { from { opacity: 0; } to { opacity: 1; } }

/* ═══════════════════════════════════════════════════════════════
   CROWD PARTICLES
   ═══════════════════════════════════════════════════════════════ */
.crowd-particles {
    position: absolute; inset: 0; pointer-events: none;
    opacity: 0; z-index: 15;
    animation: crowdIn 2s ease-out 1.3s forwards;
}
@keyframes crowdIn { from { opacity: 0; } to { opacity: 0.3; } }

.crowd-particle {
    position: absolute; width: 3px; height: 3px;
    background: rgba(255,255,255,0.3); border-radius: 50%;
    animation: crowdFloat 3s ease-in-out infinite;
}
.crowd-particle:nth-child(1) { left: 10%; top: 80%; animation-delay: 0s; }
.crowd-particle:nth-child(2) { left: 20%; top: 60%; animation-delay: 0.5s; }
.crowd-particle:nth-child(3) { left: 80%; top: 70%; animation-delay: 1s; }
.crowd-particle:nth-child(4) { left: 90%; top: 50%; animation-delay: 1.5s; }
.crowd-particle:nth-child(5) { left: 30%; top: 40%; animation-delay: 0.8s; }
.crowd-particle:nth-child(6) { left: 70%; top: 30%; animation-delay: 1.2s; }
@keyframes crowdFloat {
    0%, 100% { transform: translateY(0) scale(1); opacity: 0.3; }
    50% { transform: translateY(-20px) scale(1.5); opacity: 0.6; }
}

/* ═══════════════════════════════════════════════════════════════
   RESPONSIVE
   ═══════════════════════════════════════════════════════════════ */
@media (max-width: 768px) {
    .exit-message-title { font-size: 36px; }
    .exit-hoop-container { transform: translateX(-50%) scale(0.7); }
    .exit-scoreboard { transform: translateX(-50%) scale(0.8); }
    .exit-ball { width: 50px; height: 50px; }
}
</style>
</head>
<body>

<!-- Stage Background -->
<div class="exit-stage">
    <div class="court-lines"></div>
</div>

<!-- Spotlight -->
<div class="exit-spotlight"></div>

<!-- Crowd Particles -->
<div class="crowd-particles">
    <div class="crowd-particle"></div>
    <div class="crowd-particle"></div>
    <div class="crowd-particle"></div>
    <div class="crowd-particle"></div>
    <div class="crowd-particle"></div>
    <div class="crowd-particle"></div>
</div>

<!-- Scoreboard -->
<div class="exit-scoreboard">
    <div class="score-team">
        <div class="score-team-name">HOME</div>
        <div class="score-team-logo"><i class="fa-solid fa-basketball"></i></div>
    </div>
    <div class="score-divider"></div>
    <div class="score-team">
        <div class="score-points" id="scorePoints">98<span> - 96</span></div>
        <div class="score-timer">Q4 00:03</div>
    </div>
    <div class="score-divider"></div>
    <div class="score-team">
        <div class="score-team-name">AWAY</div>
        <div class="score-team-logo"><i class="fa-solid fa-shield-halved"></i></div>
    </div>
</div>

<!-- Hoop & Backboard -->
<div class="exit-hoop-container">
    <div class="backboard"></div>
    <div class="rim"></div>
    <div class="net"></div>
</div>

<!-- Swish Effect Particles -->
<div class="swish-effect">
    <div class="swish-particles">
        <div class="swish-particle"></div>
        <div class="swish-particle"></div>
        <div class="swish-particle"></div>
        <div class="swish-particle"></div>
        <div class="swish-particle"></div>
        <div class="swish-particle"></div>
    </div>
</div>

<!-- Basketball -->
<div class="exit-ball" id="exitBall">
    <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <radialGradient id="ballGrad" cx="30%" cy="30%" r="70%">
                <stop offset="0%" style="stop-color:#FF8C42"/>
                <stop offset="40%" style="stop-color:#FF4500"/>
                <stop offset="100%" style="stop-color:#CC3700"/>
            </radialGradient>
            <filter id="ballGlow">
                <feGaussianBlur stdDeviation="3" result="blur"/>
                <feMerge>
                    <feMergeNode in="blur"/>
                    <feMergeNode in="SourceGraphic"/>
                </feMerge>
            </filter>
        </defs>
        <circle cx="50" cy="50" r="45" fill="url(#ballGrad)" filter="url(#ballGlow)"/>
        <g stroke="rgba(0,0,0,0.3)" stroke-width="2.5" fill="none">
            <path d="M 8 50 Q 50 58 92 50" stroke-width="3.5"/>
            <path d="M 50 8 Q 35 50 50 92"/>
            <path d="M 50 8 Q 65 50 50 92"/>
            <path d="M 18 18 Q 50 35 82 18"/>
            <path d="M 18 82 Q 50 65 82 82"/>
        </g>
        <ellipse cx="35" cy="35" rx="18" ry="12" fill="rgba(255,255,255,0.2)" transform="rotate(-30 35 35)"/>
    </svg>
</div>

<!-- Ball Shadow on Floor -->
<div class="ball-shadow-floor"></div>

<!-- Flash Bulb -->
<div class="flash-bulb"></div>

<!-- Confetti -->
<div class="confetti-container">
    <div class="confetti-piece"></div>
    <div class="confetti-piece"></div>
    <div class="confetti-piece"></div>
    <div class="confetti-piece"></div>
    <div class="confetti-piece"></div>
    <div class="confetti-piece"></div>
    <div class="confetti-piece"></div>
    <div class="confetti-piece"></div>
    <div class="confetti-piece"></div>
    <div class="confetti-piece"></div>
    <div class="confetti-piece"></div>
    <div class="confetti-piece"></div>
</div>

<!-- Message -->
<div class="exit-message">
    <div class="exit-message-tagline">Sampai Jumpa</div>
    <div class="exit-message-title">HOOP<span>BALL</span></div>
    <div class="exit-message-sub">Terima kasih telah bermain</div>
</div>

<!-- Loading Bar -->
<div class="exit-loading-bar">
    <div class="exit-loading-fill"></div>
</div>

<!-- Vignette -->
<div class="exit-vignette"></div>

<script>
// Update score saat bola masuk ring
setTimeout(() => {
    const scoreEl = document.getElementById('scorePoints');
    if (scoreEl) {
        scoreEl.innerHTML = '101<span> - 96</span>';
        scoreEl.style.color = '#10B981';
    }
}, 1400);
</script>

</body>
</html>