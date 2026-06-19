<?php
// intro.php - Premium Loading Screen for HoopBall
// Redirects to index.php?load=done when finished
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HoopBall — Loading...</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
    --orange: #FF4500;
    --orange-light: #FF6B35;
    --orange-glow: rgba(255, 69, 0, 0.5);
    --dark: #0A0E17;
    --darker: #05070A;
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
   PRELOADER STAGE
   ════════════════════════════════════════ */
.preloader {
    position: fixed;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: 
        radial-gradient(ellipse at 50% 0%, rgba(255,69,0,0.06) 0%, transparent 50%),
        radial-gradient(ellipse at 50% 100%, rgba(255,69,0,0.03) 0%, transparent 40%),
        var(--darker);
    z-index: 99999;
    transition: opacity 0.8s cubic-bezier(0.76, 0, 0.24, 1),
                visibility 0.8s cubic-bezier(0.76, 0, 0.24, 1);
}

.preloader.fade-out {
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
}

/* Grid Background */
.preloader-grid {
    position: absolute;
    inset: 0;
    background-image: 
        linear-gradient(rgba(255,255,255,0.015) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.015) 1px, transparent 1px);
    background-size: 60px 60px;
    opacity: 0.6;
}

/* Court Lines (subtle basketball court reference) */
.court-lines {
    position: absolute;
    inset: 0;
    pointer-events: none;
    opacity: 0.04;
}

.court-lines::before {
    content: '';
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 500px; height: 500px;
    border: 2px solid var(--orange);
    border-radius: 50%;
}

.court-lines::after {
    content: '';
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 250px; height: 250px;
    border: 2px solid var(--orange);
    border-radius: 50%;
}

/* Spotlight from top */
.spotlight {
    position: absolute;
    top: -30%; left: 50%;
    transform: translateX(-50%);
    width: 600px; height: 600px;
    background: radial-gradient(circle, rgba(255,69,0,0.06) 0%, transparent 70%);
    pointer-events: none;
    animation: spotlightPulse 4s ease-in-out infinite;
}

@keyframes spotlightPulse {
    0%, 100% { opacity: 0.5; transform: translateX(-50%) scale(1); }
    50% { opacity: 1; transform: translateX(-50%) scale(1.1); }
}

/* ════════════════════════════════════════
   PROGRESS RING CONTAINER
   ════════════════════════════════════════ */
.ring-container {
    position: relative;
    width: 260px;
    height: 260px;
    margin-bottom: 50px;
}

/* Outer glow ring */
.ring-glow {
    position: absolute;
    inset: -10px;
    border-radius: 50%;
    background: conic-gradient(from 0deg, transparent, var(--orange-glow), transparent);
    opacity: 0;
    animation: glowPulse 2s ease-in-out infinite;
    filter: blur(20px);
}

@keyframes glowPulse {
    0%, 100% { opacity: 0.3; transform: scale(1); }
    50% { opacity: 0.6; transform: scale(1.05); }
}

/* SVG Progress Ring */
.progress-ring-svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}

.progress-ring-track {
    fill: none;
    stroke: rgba(255,255,255,0.04);
    stroke-width: 2;
}

.progress-ring-fill {
    fill: none;
    stroke: var(--orange);
    stroke-width: 3;
    stroke-linecap: round;
    stroke-dasharray: 754; /* 2 * PI * 120 */
    stroke-dashoffset: 754;
    transition: stroke-dashoffset 0.15s ease-out;
    filter: drop-shadow(0 0 6px var(--orange-glow));
}

/* Dashed decorative ring */
.ring-dashed {
    position: absolute;
    inset: 15px;
    border-radius: 50%;
    border: 1px dashed rgba(255,255,255,0.08);
    animation: ringRotate 20s linear infinite;
}

@keyframes ringRotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* ════════════════════════════════════════
   CENTER LOGO — Basketball (SPINNING)
   ════════════════════════════════════════ */
.center-logo {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 140px;
    height: 140px;
}

.ball-svg {
    width: 100%;
    height: 100%;
    animation: ballSpin 2s linear infinite;
}

/* Ball spinning animation - rotates continuously */
@keyframes ballSpin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* ════════════════════════════════════════
   ORBITING PARTICLES
   ════════════════════════════════════════ */
.orbit-particles {
    position: absolute;
    inset: -30px;
    pointer-events: none;
}

.orbit-particle {
    position: absolute;
    width: 6px;
    height: 6px;
    background: var(--orange);
    border-radius: 50%;
    opacity: 0;
    box-shadow: 0 0 10px var(--orange-glow), 0 0 20px var(--orange-glow);
}

.orbit-particle:nth-child(1) {
    top: 0; left: 50%;
    transform: translateX(-50%);
    animation: orbit1 3s ease-in-out infinite;
}
.orbit-particle:nth-child(2) {
    top: 50%; right: 0;
    transform: translateY(-50%);
    animation: orbit2 3s ease-in-out infinite 0.75s;
}
.orbit-particle:nth-child(3) {
    bottom: 0; left: 50%;
    transform: translateX(-50%);
    animation: orbit3 3s ease-in-out infinite 1.5s;
}
.orbit-particle:nth-child(4) {
    top: 50%; left: 0;
    transform: translateY(-50%);
    animation: orbit4 3s ease-in-out infinite 2.25s;
}

@keyframes orbit1 {
    0%, 100% { opacity: 0; transform: translateX(-50%) scale(0); }
    50% { opacity: 1; transform: translateX(-50%) scale(1); }
}
@keyframes orbit2 {
    0%, 100% { opacity: 0; transform: translateY(-50%) scale(0); }
    50% { opacity: 1; transform: translateY(-50%) scale(1); }
}
@keyframes orbit3 {
    0%, 100% { opacity: 0; transform: translateX(-50%) scale(0); }
    50% { opacity: 1; transform: translateX(-50%) scale(1); }
}
@keyframes orbit4 {
    0%, 100% { opacity: 0; transform: translateY(-50%) scale(0); }
    50% { opacity: 1; transform: translateY(-50%) scale(1); }
}

/* Floating particles around */
.float-particles {
    position: absolute;
    inset: 0;
    pointer-events: none;
    overflow: hidden;
}

.float-particle {
    position: absolute;
    width: 3px;
    height: 3px;
    background: var(--orange);
    border-radius: 50%;
    opacity: 0;
    animation: floatUp 4s ease-in-out infinite;
}

.float-particle:nth-child(1) { left: 20%; top: 80%; animation-delay: 0s; width: 4px; height: 4px; }
.float-particle:nth-child(2) { left: 80%; top: 60%; animation-delay: 0.8s; }
.float-particle:nth-child(3) { left: 50%; top: 20%; animation-delay: 1.6s; width: 5px; height: 5px; }
.float-particle:nth-child(4) { left: 30%; top: 40%; animation-delay: 2.4s; }
.float-particle:nth-child(5) { left: 70%; top: 30%; animation-delay: 3.2s; width: 4px; height: 4px; }
.float-particle:nth-child(6) { left: 10%; top: 50%; animation-delay: 1s; }
.float-particle:nth-child(7) { left: 90%; top: 45%; animation-delay: 2s; }
.float-particle:nth-child(8) { left: 45%; top: 75%; animation-delay: 2.8s; width: 3px; height: 3px; }

@keyframes floatUp {
    0% { transform: translateY(0) scale(0); opacity: 0; }
    15% { opacity: 0.8; transform: translateY(-15px) scale(1); }
    85% { opacity: 0.3; transform: translateY(-80px) scale(0.6); }
    100% { transform: translateY(-120px) scale(0); opacity: 0; }
}

/* ════════════════════════════════════════
   TEXT ELEMENTS
   ════════════════════════════════════════ */
.brand-container {
    text-align: center;
    position: relative;
    z-index: 10;
}

.brand-tagline {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 5px;
    text-transform: uppercase;
    color: var(--orange);
    margin-bottom: 16px;
    opacity: 0;
    animation: fadeSlideUp 0.6s ease-out 0.3s forwards;
}

.brand-title {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 56px;
    font-weight: 900;
    color: #fff;
    letter-spacing: 3px;
    line-height: 1;
    margin-bottom: 8px;
    opacity: 0;
    animation: fadeSlideUp 0.6s ease-out 0.4s forwards;
}

.brand-title span {
    color: var(--orange);
    position: relative;
}

.brand-title span::after {
    content: '';
    position: absolute;
    bottom: 4px; left: 0;
    width: 100%; height: 6px;
    background: var(--orange);
    opacity: 0.25;
    border-radius: 2px;
    animation: underlineGrow 0.8s ease-out 1s forwards;
    transform: scaleX(0);
    transform-origin: left;
}

@keyframes underlineGrow {
    to { transform: scaleX(1); }
}

.brand-subtitle {
    font-size: 13px;
    font-weight: 500;
    color: rgba(255,255,255,0.35);
    letter-spacing: 2px;
    margin-bottom: 40px;
    opacity: 0;
    animation: fadeSlideUp 0.6s ease-out 0.5s forwards;
}

/* Percentage Display */
.percent-display {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 64px;
    font-weight: 900;
    color: #fff;
    letter-spacing: -2px;
    line-height: 1;
    margin-bottom: 12px;
    opacity: 0;
    animation: fadeSlideUp 0.6s ease-out 0.6s forwards;
}

.percent-display span {
    color: var(--orange);
    font-size: 32px;
    font-weight: 700;
}

/* Progress Bar (linear below percentage) */
.progress-bar-container {
    width: 200px;
    opacity: 0;
    animation: fadeSlideUp 0.6s ease-out 0.7s forwards;
}

.progress-bar-track {
    width: 100%;
    height: 2px;
    background: rgba(255,255,255,0.06);
    border-radius: 2px;
    overflow: hidden;
    position: relative;
}

.progress-bar-fill {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, var(--orange), var(--orange-light), var(--orange));
    border-radius: 2px;
    position: relative;
    transition: width 0.1s ease-out;
}

.progress-bar-fill::after {
    content: '';
    position: absolute;
    right: 0; top: 50%;
    transform: translateY(-50%);
    width: 6px; height: 6px;
    background: var(--orange);
    border-radius: 50%;
    box-shadow: 0 0 15px var(--orange-glow), 0 0 30px var(--orange-glow);
}

/* Loading text */
.loading-text {
    margin-top: 20px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 4px;
    text-transform: uppercase;
    color: rgba(255,255,255,0.2);
    opacity: 0;
    animation: fadeSlideUp 0.6s ease-out 0.8s forwards;
}

.loading-text::after {
    content: '';
    animation: dots 1.5s steps(4, end) infinite;
}

@keyframes dots {
    0% { content: ''; }
    25% { content: '.'; }
    50% { content: '..'; }
    75% { content: '...'; }
}

/* ════════════════════════════════════════
   ENTRANCE ANIMATIONS
   ════════════════════════════════════════ */
@keyframes fadeSlideUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ════════════════════════════════════════
   EXIT TRANSITION
   ════════════════════════════════════════ */
.preloader.exit {
    animation: preloaderExit 0.6s cubic-bezier(0.76, 0, 0.24, 1) forwards;
}

@keyframes preloaderExit {
    0% { opacity: 1; transform: scale(1); filter: blur(0); }
    100% { opacity: 0; transform: scale(1.1); filter: blur(15px); }
}

/* ════════════════════════════════════════
   RESPONSIVE
   ════════════════════════════════════════ */
@media (max-width: 768px) {
    .ring-container { width: 200px; height: 200px; }
    .center-logo { width: 110px; height: 110px; }
    .brand-title { font-size: 40px; }
    .percent-display { font-size: 48px; }
    .percent-display span { font-size: 24px; }
    .court-lines::before { width: 350px; height: 350px; }
    .court-lines::after { width: 180px; height: 180px; }
}

@media (max-width: 480px) {
    .ring-container { width: 170px; height: 170px; }
    .center-logo { width: 90px; height: 90px; }
    .brand-title { font-size: 32px; letter-spacing: 2px; }
    .percent-display { font-size: 40px; }
}
</style>
</head>
<body>

<div class="preloader" id="preloader">
    <!-- Background Elements -->
    <div class="preloader-grid"></div>
    <div class="court-lines"></div>
    <div class="spotlight"></div>
    <div class="float-particles">
        <div class="float-particle"></div>
        <div class="float-particle"></div>
        <div class="float-particle"></div>
        <div class="float-particle"></div>
        <div class="float-particle"></div>
        <div class="float-particle"></div>
        <div class="float-particle"></div>
        <div class="float-particle"></div>
    </div>

    <!-- Main Content -->
    <div class="ring-container">
        <!-- Glow effect -->
        <div class="ring-glow"></div>

        <!-- Dashed decorative ring -->
        <div class="ring-dashed"></div>

        <!-- SVG Progress Ring -->
        <svg class="progress-ring-svg" viewBox="0 0 260 260">
            <circle class="progress-ring-track" cx="130" cy="130" r="120"/>
            <circle class="progress-ring-fill" id="progressRing" cx="130" cy="130" r="120"/>
        </svg>

        <!-- Center Basketball Logo (SPINNING) -->
        <div class="center-logo">
            <svg class="ball-svg" viewBox="0 0 140 140" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <radialGradient id="ballGrad" cx="35%" cy="35%" r="65%">
                        <stop offset="0%" style="stop-color:#FF8C42"/>
                        <stop offset="40%" style="stop-color:#FF4500"/>
                        <stop offset="100%" style="stop-color:#B83200"/>
                    </radialGradient>
                    <filter id="ballShadow" x="-20%" y="-20%" width="140%" height="140%">
                        <feDropShadow dx="0" dy="6" stdDeviation="12" flood-color="#000" flood-opacity="0.5"/>
                    </filter>
                    <filter id="ballGlow">
                        <feGaussianBlur stdDeviation="4" result="blur"/>
                        <feMerge>
                            <feMergeNode in="blur"/>
                            <feMergeNode in="SourceGraphic"/>
                        </feMerge>
                    </filter>
                </defs>
                <!-- Ball body -->
                <circle cx="70" cy="70" r="65" fill="url(#ballGrad)" filter="url(#ballShadow)"/>
                <!-- Ball lines -->
                <g stroke="rgba(0,0,0,0.28)" stroke-width="2.5" fill="none">
                    <path d="M 10 70 Q 70 78 130 70" stroke-width="3.5"/>
                    <path d="M 70 10 Q 52 70 70 130"/>
                    <path d="M 70 10 Q 88 70 70 130"/>
                    <path d="M 22 22 Q 70 42 118 22"/>
                    <path d="M 22 118 Q 70 98 118 118"/>
                </g>
                <!-- Highlight -->
                <ellipse cx="48" cy="48" rx="22" ry="14" fill="rgba(255,255,255,0.12)" transform="rotate(-30 48 48)"/>
                <!-- Inner shine -->
                <ellipse cx="55" cy="42" rx="8" ry="5" fill="rgba(255,255,255,0.08)" transform="rotate(-30 55 42)"/>
            </svg>
        </div>

        <!-- Orbiting particles -->
        <div class="orbit-particles">
            <div class="orbit-particle"></div>
            <div class="orbit-particle"></div>
            <div class="orbit-particle"></div>
            <div class="orbit-particle"></div>
        </div>
    </div>

    <!-- Brand Text -->
    <div class="brand-container">
        <div class="brand-tagline">Premium Basketball Experience</div>
        <h1 class="brand-title">HOOP<span>BALL</span></h1>
        <p class="brand-subtitle">WEBSITE HOOPBALL</p>
    </div>

    <!-- Percentage & Progress -->
    <div class="percent-display" id="percentDisplay">0<span>%</span></div>
    <div class="progress-bar-container">
        <div class="progress-bar-track">
            <div class="progress-bar-fill" id="progressBar"></div>
        </div>
    </div>
    <div class="loading-text">Loading</div>
</div>

<script>
// ════════════════════════════════════════
// PROGRESS ANIMATION
// ════════════════════════════════════════
const preloader = document.getElementById('preloader');
const progressRing = document.getElementById('progressRing');
const progressBar = document.getElementById('progressBar');
const percentDisplay = document.getElementById('percentDisplay');

const circumference = 2 * Math.PI * 120; // r = 120
progressRing.style.strokeDasharray = circumference;
progressRing.style.strokeDashoffset = circumference;

let progress = 0;
const totalDuration = 2800; // 2.8 seconds
const interval = 25;
const baseIncrement = 100 / (totalDuration / interval);

function updateProgress() {
    // Add some randomness for natural feel
    const randomBoost = Math.random() * 2.5;
    progress += baseIncrement + randomBoost;

    if (progress > 100) progress = 100;

    const currentPercent = Math.floor(progress);

    // Update ring
    const offset = circumference - (progress / 100) * circumference;
    progressRing.style.strokeDashoffset = offset;

    // Update bar
    progressBar.style.width = progress + '%';

    // Update percentage text
    percentDisplay.innerHTML = currentPercent + '<span>%</span>';

    if (progress < 100) {
        setTimeout(updateProgress, interval);
    } else {
        // Small pause at 100% then exit
        setTimeout(exitPreloader, 500);
    }
}

function exitPreloader() {
    preloader.classList.add('exit');

    // Redirect after exit animation
    setTimeout(() => {
        window.location.href = 'index.php?load=done';
    }, 600);
}

// Start after brief delay
setTimeout(updateProgress, 400);
</script>

</body>
</html>