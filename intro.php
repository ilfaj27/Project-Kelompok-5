<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HoopBall — Premium Basketball Experience</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
    --orange: #FF4500;
    --orange-dk: #E03E00;
    --orange-glow: rgba(255, 69, 0, 0.4);
    --dark: #0D1117;
    --darker: #07090D;
    --light: #F3F4F6;
}

*, *::before, *::after {
    margin: 0; padding: 0;
    box-sizing: border-box;
}

html, body {
    width: 100%; height: 100%;
    overflow: hidden;
    font-family: 'Barlow', sans-serif;
    background: var(--darker);
}

/* ════════════════════════════════════════
   STAGE — Container Utama
   ════════════════════════════════════════ */
.stage {
    position: fixed;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: radial-gradient(ellipse at 50% 50%, #1a1f2e 0%, var(--darker) 70%);
    z-index: 1000;
    transition: opacity 0.4s ease-in, transform 0.4s ease-in, filter 0.4s ease-in; /* Transisi keluar dipercepat menjadi 0.4s */
}

/* Spotlight effect */
.stage::before {
    content: '';
    position: absolute;
    top: -50%; left: 50%;
    transform: translateX(-50%);
    width: 800px; height: 800px;
    background: radial-gradient(circle, rgba(255,69,0,0.08) 0%, transparent 70%);
    pointer-events: none;
    animation: spotlightPulse 3s ease-in-out infinite; /* Dipercepat sedikit agar lebih dinamis */
}

@keyframes spotlightPulse {
    0%, 100% { opacity: 0.6; transform: translateX(-50%) scale(1); }
    50% { opacity: 1; transform: translateX(-50%) scale(1.05); }
}

/* ════════════════════════════════════════
   COURT LINES — Garis Lapangan Basket
   ════════════════════════════════════════ */
.court-lines {
    position: absolute;
    inset: 0;
    pointer-events: none;
    opacity: 0.03;
}

.court-lines::before {
    content: '';
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 600px; height: 600px;
    border: 2px solid var(--orange);
    border-radius: 50%;
}

.court-lines::after {
    content: '';
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 300px; height: 300px;
    border: 2px solid var(--orange);
    border-radius: 50%;
}

/* ════════════════════════════════════════
   BASKETBALL — SVG Animasi
   ════════════════════════════════════════ */
.ball-container {
    position: relative;
    width: 200px; height: 200px;
    margin-bottom: 60px;
}

.ball {
    width: 100%; height: 100%;
    animation: ballBounce 1.6s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite; /* Pantulan dipercepat sedikit */
}

@keyframes ballBounce {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-70px) rotate(180deg); }
}

/* Shadow di bawah bola */
.ball-shadow {
    position: absolute;
    bottom: -20px; left: 50%;
    transform: translateX(-50%);
    width: 80px; height: 20px;
    background: radial-gradient(ellipse, rgba(0,0,0,0.5) 0%, transparent 70%);
    border-radius: 50%;
    animation: shadowScale 1.6s ease-in-out infinite;
}

@keyframes shadowScale {
    0%, 100% { transform: translateX(-50%) scale(1); opacity: 0.5; }
    50% { transform: translateX(-50%) scale(0.6); opacity: 0.2; }
}

/* ════════════════════════════════════════
   HOOP & RIM — Ring Basket
   ════════════════════════════════════════ */
.hoop-container {
    position: absolute;
    top: -120px; left: 50%;
    transform: translateX(-50%);
    width: 180px; height: 120px;
    opacity: 0;
    animation: hoopAppear 0.6s ease-out 0.1s forwards; /* Dipercepat */
}

@keyframes hoopAppear {
    from { opacity: 0; transform: translateX(-50%) translateY(-20px); }
    to { opacity: 1; transform: translateX(-50%) translateY(0); }
}

.hoop-rim {
    width: 120px; height: 12px;
    border: 4px solid #C0392B;
    border-radius: 50%;
    margin: 0 auto;
    position: relative;
    background: linear-gradient(90deg, #C0392B, #E74C3C, #C0392B);
    box-shadow: 0 4px 20px rgba(192, 57, 43, 0.3);
}

.hoop-net {
    width: 100px; height: 70px;
    margin: -6px auto 0;
    position: relative;
    overflow: hidden;
}

.hoop-net::before {
    content: '';
    position: absolute;
    inset: 0;
    background: 
        linear-gradient(90deg, transparent 48%, rgba(255,255,255,0.15) 49%, rgba(255,255,255,0.15) 51%, transparent 52%),
        linear-gradient(60deg, transparent 48%, rgba(255,255,255,0.1) 49%, rgba(255,255,255,0.1) 51%, transparent 52%),
        linear-gradient(-60deg, transparent 48%, rgba(255,255,255,0.1) 49%, rgba(255,255,255,0.1) 51%, transparent 52%);
    clip-path: polygon(10% 0%, 90% 0%, 70% 100%, 30% 100%);
    animation: netSway 1.6s ease-in-out infinite;
}

@keyframes netSway {
    0%, 100% { transform: skewX(-2deg); }
    50% { transform: skewX(2deg); }
}

.hoop-backboard {
    position: absolute;
    top: -50px; left: 50%;
    transform: translateX(-50%);
    width: 160px; height: 100px;
    background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%);
    border: 2px solid rgba(255,255,255,0.1);
    border-radius: 4px;
    opacity: 0;
    animation: boardAppear 0.6s ease-out 0s forwards; /* Dipercepat */
}

@keyframes boardAppear {
    from { opacity: 0; transform: translateX(-50%) scale(0.95); }
    to { opacity: 1; transform: translateX(-50%) scale(1); }
}

.hoop-backboard::after {
    content: '';
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 60px; height: 45px;
    border: 2px solid rgba(255,69,0,0.4);
    border-radius: 2px;
}

/* ════════════════════════════════════════
   PARTICLES — Partikel Bintang/Api
   ════════════════════════════════════════ */
.particles {
    position: absolute;
    inset: 0;
    pointer-events: none;
    overflow: hidden;
}

.particle {
    position: absolute;
    width: 4px; height: 4px;
    background: var(--orange);
    border-radius: 50%;
    opacity: 0;
    animation: particleFloat 2s ease-in-out infinite; /* Dipercepat sedikit */
}

.particle:nth-child(1) { left: 20%; top: 80%; animation-delay: 0s; }
.particle:nth-child(2) { left: 80%; top: 60%; animation-delay: 0.3s; width: 6px; height: 6px; }
.particle:nth-child(3) { left: 50%; top: 20%; animation-delay: 0.6s; }
.particle:nth-child(4) { left: 30%; top: 40%; animation-delay: 0.9s; width: 3px; height: 3px; }
.particle:nth-child(5) { left: 70%; top: 30%; animation-delay: 1.2s; }
.particle:nth-child(6) { left: 10%; top: 50%; animation-delay: 1.5s; width: 5px; height: 5px; }

@keyframes particleFloat {
    0% { transform: translateY(0) scale(0); opacity: 0; }
    20% { opacity: 1; transform: translateY(-20px) scale(1); }
    80% { opacity: 0.5; transform: translateY(-80px) scale(0.5); }
    100% { transform: translateY(-120px) scale(0); opacity: 0; }
}

/* ════════════════════════════════════════
   TEXT ANIMATION — HoopBall Branding
   ════════════════════════════════════════ */
.brand-container {
    text-align: center;
    position: relative;
    z-index: 10;
}

.brand-tagline {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 6px;
    text-transform: uppercase;
    color: var(--orange);
    margin-bottom: 16px;
    opacity: 0;
    animation: fadeSlideUp 0.5s ease-out 0.15s forwards; /* Dipercepat */
}

.brand-title {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 72px;
    font-weight: 900;
    color: #fff;
    letter-spacing: 4px;
    line-height: 1;
    position: relative;
    opacity: 0;
    animation: fadeSlideUp 0.5s ease-out 0.25s forwards; /* Dipercepat */
}

.brand-title span {
    color: var(--orange);
    position: relative;
    display: inline-block;
}

.brand-title span::after {
    content: '';
    position: absolute;
    bottom: 5px; left: 0;
    width: 100%; height: 8px;
    background: var(--orange);
    opacity: 0.3;
    border-radius: 2px;
    animation: underlineGrow 0.6s ease-out 0.5s forwards; /* Dipercepat */
    transform: scaleX(0);
    transform-origin: left;
}

@keyframes underlineGrow {
    to { transform: scaleX(1); }
}

.brand-subtitle {
    font-size: 14px;
    font-weight: 500;
    color: rgba(255,255,255,0.4);
    margin-top: 20px;
    letter-spacing: 2px;
    opacity: 0;
    animation: fadeSlideUp 0.5s ease-out 0.35s forwards; /* Dipercepat */
}

@keyframes fadeSlideUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ════════════════════════════════════════
   PROGRESS BAR — Loading Indicator
   ════════════════════════════════════════ */
.progress-container {
    margin-top: 50px;
    width: 280px;
    opacity: 0;
    animation: fadeSlideUp 0.5s ease-out 0.4s forwards; /* Dipercepat agar tampil hampir bersamaan dengan judul */
}

.progress-track {
    width: 100%; height: 3px;
    background: rgba(255,255,255,0.08);
    border-radius: 3px;
    overflow: hidden;
    position: relative;
}

.progress-fill {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, var(--orange), #FF6B35, var(--orange));
    border-radius: 3px;
    position: relative;
    transition: width 0.08s ease-out; /* Transisi dipercepat untuk mengikuti kalkulasi pemuatan baru */
}

.progress-fill::after {
    content: '';
    position: absolute;
    right: 0; top: 50%;
    transform: translateY(-50%);
    width: 8px; height: 8px;
    background: var(--orange);
    border-radius: 50%;
    box-shadow: 0 0 20px var(--orange-glow);
}

.progress-text {
    display: flex;
    justify-content: space-between;
    margin-top: 12px;
    font-size: 11px;
    font-weight: 600;
    color: rgba(255,255,255,0.3);
    letter-spacing: 1px;
}

.progress-percent {
    color: var(--orange);
    font-weight: 800;
}

/* ════════════════════════════════════════
   EXIT ANIMATION — Fade Out
   ════════════════════════════════════════ */
.stage.fade-out {
    opacity: 0;
    transform: scale(1.03);
    filter: blur(8px);
    pointer-events: none;
}

/* ════════════════════════════════════════
   RESPONSIVE
   ════════════════════════════════════════ */
@media (max-width: 768px) {
    .ball-container { width: 140px; height: 140px; }
    .brand-title { font-size: 48px; }
    .brand-tagline { font-size: 10px; letter-spacing: 4px; }
    .progress-container { width: 220px; }
    .hoop-backboard { width: 120px; height: 80px; }
    .hoop-rim { width: 90px; }
}
</style>
</head>
<body>

<div class="stage" id="stage">
    <!-- Court Lines Background -->
    <div class="court-lines"></div>

    <!-- Floating Particles -->
    <div class="particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>

    <!-- Basketball Animation -->
    <div class="ball-container">
        <!-- Backboard -->
        <div class="hoop-backboard"></div>

        <!-- Hoop -->
        <div class="hoop-container">
            <div class="hoop-rim"></div>
            <div class="hoop-net"></div>
        </div>

        <!-- Ball SVG -->
        <svg class="ball" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <radialGradient id="ballGradient" cx="35%" cy="35%" r="65%">
                    <stop offset="0%" style="stop-color:#FF6B35"/>
                    <stop offset="50%" style="stop-color:#FF4500"/>
                    <stop offset="100%" style="stop-color:#CC3700"/>
                </radialGradient>
                <filter id="ballShadow" x="-20%" y="-20%" width="140%" height="140%">
                    <feDropShadow dx="0" dy="8" stdDeviation="12" flood-color="#000" flood-opacity="0.4"/>
                </filter>
            </defs>

            <!-- Ball Body -->
            <circle cx="100" cy="100" r="95" fill="url(#ballGradient)" filter="url(#ballShadow)"/>

            <!-- Ball Lines -->
            <g stroke="rgba(0,0,0,0.25)" stroke-width="3" fill="none">
                <path d="M 10 100 Q 100 115 190 100" stroke-width="4"/>
                <path d="M 100 5 Q 70 100 100 195"/>
                <path d="M 100 5 Q 130 100 100 195"/>
                <path d="M 30 30 Q 100 60 170 30"/>
                <path d="M 30 170 Q 100 140 170 170"/>
            </g>

            <!-- Highlight -->
            <ellipse cx="70" cy="70" rx="30" ry="20" fill="rgba(255,255,255,0.15)" transform="rotate(-30 70 70)"/>
        </svg>

        <!-- Ball Shadow -->
        <div class="ball-shadow"></div>
    </div>

    <!-- Brand Text -->
    <div class="brand-container">
        <div class="brand-tagline">Premium Basketball Experience</div>
        <h1 class="brand-title">HOOP<span>BALL</span></h1>
        <p class="brand-subtitle">WEBSITE HOOPBALL</p>
    </div>

    <!-- Progress Bar -->
    <div class="progress-container">
        <div class="progress-track">
            <div class="progress-fill" id="progressFill"></div>
        </div>
        <div class="progress-text">
            <span>Loading</span>
            <span class="progress-percent" id="progressPercent">0%</span>
        </div>
    </div>
</div>

<script>
// Progress Counter Animation
let progress = 0;
const progressPercent = document.getElementById('progressPercent');
const progressFill = document.getElementById('progressFill');

function updateProgress() {
    if (progress < 100) {
        // Rentang nilai penambahan diperbesar agar pemuatan berjalan jauh lebih cepat
        progress += Math.random() * 4 + 4.5; 
        if (progress > 100) progress = 100;

        progressPercent.textContent = Math.floor(progress) + '%';
        progressFill.style.width = progress + '%';

        if (progress >= 100) {
            // Jeda dipersingkat menjadi 300ms saat loading selesai untuk transisi yang instan
            setTimeout(triggerExit, 300);
        } else {
            // Interval dipersingkat menjadi 20ms - 40ms untuk transisi yang sangat responsif
            setTimeout(updateProgress, 20 + Math.random() * 20);
        }
    }
}

// Proses pemuatan dimulai lebih awal (setelah 400ms saat halaman terbuka)
setTimeout(updateProgress, 400);

// Fungsi transisi keluar otomatis ke halaman index.php
function triggerExit() {
    const stage = document.getElementById('stage');
    stage.classList.add('fade-out');

    // Pengalihan halaman dipercepat menjadi 400ms (menyesuaikan transisi CSS baru)
    setTimeout(() => {
        window.location.href = 'index.php?load=done';
    }, 400);
}
</script>

</body>
</html>