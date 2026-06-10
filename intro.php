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
    animation: spotlightPulse 4s ease-in-out infinite;
}

@keyframes spotlightPulse {
    0%, 100% { opacity: 0.6; transform: translateX(-50%) scale(1); }
    50% { opacity: 1; transform: translateX(-50%) scale(1.1); }
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
    animation: ballBounce 2s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite;
}

@keyframes ballBounce {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-80px) rotate(180deg); }
}

/* Shadow di bawah bola */
.ball-shadow {
    position: absolute;
    bottom: -20px; left: 50%;
    transform: translateX(-50%);
    width: 80px; height: 20px;
    background: radial-gradient(ellipse, rgba(0,0,0,0.5) 0%, transparent 70%);
    border-radius: 50%;
    animation: shadowScale 2s ease-in-out infinite;
}

@keyframes shadowScale {
    0%, 100% { transform: translateX(-50%) scale(1); opacity: 0.5; }
    50% { transform: translateX(-50%) scale(0.5); opacity: 0.2; }
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
    animation: hoopAppear 1s ease-out 0.5s forwards;
}

@keyframes hoopAppear {
    from { opacity: 0; transform: translateX(-50%) translateY(-30px); }
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
    animation: netSway 2s ease-in-out infinite;
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
    animation: boardAppear 1s ease-out 0.3s forwards;
}

@keyframes boardAppear {
    from { opacity: 0; transform: translateX(-50%) scale(0.9); }
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
    animation: particleFloat 3s ease-in-out infinite;
}

.particle:nth-child(1) { left: 20%; top: 80%; animation-delay: 0s; }
.particle:nth-child(2) { left: 80%; top: 60%; animation-delay: 0.5s; width: 6px; height: 6px; }
.particle:nth-child(3) { left: 50%; top: 20%; animation-delay: 1s; }
.particle:nth-child(4) { left: 30%; top: 40%; animation-delay: 1.5s; width: 3px; height: 3px; }
.particle:nth-child(5) { left: 70%; top: 30%; animation-delay: 2s; }
.particle:nth-child(6) { left: 10%; top: 50%; animation-delay: 2.5s; width: 5px; height: 5px; }
.particle:nth-child(7) { left: 90%; top: 80%; animation-delay: 0.8s; }
.particle:nth-child(8) { left: 45%; top: 70%; animation-delay: 1.8s; width: 2px; height: 2px; }

@keyframes particleFloat {
    0% { transform: translateY(0) scale(0); opacity: 0; }
    20% { opacity: 1; transform: translateY(-20px) scale(1); }
    80% { opacity: 0.5; transform: translateY(-100px) scale(0.5); }
    100% { transform: translateY(-150px) scale(0); opacity: 0; }
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
    animation: fadeSlideUp 0.8s ease-out 1.5s forwards;
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
    animation: fadeSlideUp 0.8s ease-out 1.8s forwards;
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
    animation: underlineGrow 1s ease-out 2.5s forwards;
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
    animation: fadeSlideUp 0.8s ease-out 2.2s forwards;
}

@keyframes fadeSlideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ════════════════════════════════════════
   PROGRESS BAR — Loading Indicator
   ════════════════════════════════════════ */
.progress-container {
    margin-top: 50px;
    width: 280px;
    opacity: 0;
    animation: fadeSlideUp 0.8s ease-out 2.6s forwards;
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
    animation: progressLoad 3s ease-out 3s forwards;
    position: relative;
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

@keyframes progressLoad {
    0% { width: 0%; }
    50% { width: 70%; }
    100% { width: 100%; }
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
    animation: percentCount 3s ease-out 3s forwards;
}

@keyframes percentCount {
    0% { content: '0%'; }
    50% { content: '70%'; }
    100% { content: '100%'; }
}

/* ════════════════════════════════════════
   ENTER BUTTON — CTA
   ════════════════════════════════════════ */
.enter-btn {
    margin-top: 40px;
    padding: 16px 48px;
    background: transparent;
    border: 2px solid var(--orange);
    color: var(--orange);
    font-family: 'Barlow', sans-serif;
    font-size: 14px;
    font-weight: 800;
    letter-spacing: 3px;
    text-transform: uppercase;
    cursor: pointer;
    border-radius: 50px;
    position: relative;
    overflow: hidden;
    opacity: 0;
    animation: fadeSlideUp 0.8s ease-out 5s forwards, btnPulse 2s ease-in-out 6s infinite;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
}

.enter-btn::before {
    content: '';
    position: absolute;
    inset: 0;
    background: var(--orange);
    transform: scaleX(0);
    transform-origin: right;
    transition: transform 0.4s ease;
    z-index: -1;
}

.enter-btn:hover::before {
    transform: scaleX(1);
    transform-origin: left;
}

.enter-btn:hover {
    color: #fff;
    border-color: var(--orange);
    box-shadow: 0 0 40px var(--orange-glow);
}

@keyframes btnPulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(255, 69, 0, 0.4); }
    50% { box-shadow: 0 0 0 15px rgba(255, 69, 0, 0); }
}

/* ════════════════════════════════════════
   SKIP LINK
   ════════════════════════════════════════ */
.skip-link {
    position: absolute;
    bottom: 30px;
    font-size: 11px;
    color: rgba(255,255,255,0.2);
    text-decoration: none;
    letter-spacing: 1px;
    transition: color 0.3s;
    opacity: 0;
    animation: fadeIn 0.5s ease-out 6s forwards;
}

.skip-link:hover {
    color: var(--orange);
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* ════════════════════════════════════════
   EXIT ANIMATION — Fade Out
   ════════════════════════════════════════ */
.stage.fade-out {
    animation: stageExit 0.8s ease-in forwards;
}

@keyframes stageExit {
    to { opacity: 0; transform: scale(1.1); filter: blur(10px); }
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
                <!-- Horizontal line -->
                <path d="M 10 100 Q 100 115 190 100" stroke-width="4"/>
                <!-- Vertical curve left -->
                <path d="M 100 5 Q 70 100 100 195"/>
                <!-- Vertical curve right -->
                <path d="M 100 5 Q 130 100 100 195"/>
                <!-- Side curves -->
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

    <!-- Enter Button -->
    <a href="index.php" class="enter-btn" id="enterBtn" onclick="return handleEnter(event)">
        Masuk Sistem
    </a>

    <!-- Skip Link -->
    <a href="index.php" class="skip-link">Lewati Animasi →</a>
</div>

<script>
// Progress Counter Animation
let progress = 0;
const progressPercent = document.getElementById('progressPercent');
const progressFill = document.getElementById('progressFill');
const enterBtn = document.getElementById('enterBtn');

function updateProgress() {
    if (progress < 100) {
        progress += Math.random() * 3 + 0.5;
        if (progress > 100) progress = 100;

        progressPercent.textContent = Math.floor(progress) + '%';
        progressFill.style.width = progress + '%';

        // Enable button at 100%
        if (progress >= 100) {
            enterBtn.style.pointerEvents = 'auto';
            enterBtn.style.opacity = '1';
        }

        setTimeout(updateProgress, 50 + Math.random() * 100);
    }
}

// Start progress after delay
setTimeout(updateProgress, 3000);

// Enter Button Handler
function handleEnter(e) {
    e.preventDefault();
    const stage = document.getElementById('stage');
    stage.classList.add('fade-out');

    setTimeout(() => {
        window.location.href = 'index.php';
    }, 800);
    return false;
}

// Auto redirect after animation (optional - 8 seconds)
// setTimeout(() => {
//     if (!document.querySelector('.stage.fade-out')) {
//         handleEnter({ preventDefault: () => {} });
//     }
// }, 8000);
</script>

</body>
</html>