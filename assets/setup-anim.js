/* =============================================================
   SetupForge — Wizard Step Entry Animations
   Pure visual only. Zero logic changes.
   Staggered slide-up + fade per element group.
   Tooltip fires after last element settles.
============================================================= */

(function () {
  'use strict';

  var EASE    = 'cubic-bezier(0.16, 1, 0.3, 1)';
  var DUR     = 600;   // ms per element
  var STAGGER = 90;    // ms between each element
  var CARD_STAGGER = 60; // ms between individual cards

  /* ── Apply animation to a single element ─────────────────── */
  function animEl(el, delay) {
    if (!el) return;
    el.style.opacity   = '0';
    el.style.transform = 'translateY(22px)';
    el.style.transition = 'none';

    setTimeout(function () {
      el.style.transition =
        'opacity '  + DUR + 'ms ' + EASE + ' ' + delay + 'ms,' +
        'transform ' + DUR + 'ms ' + EASE + ' ' + delay + 'ms';
      el.style.opacity   = '1';
      el.style.transform = 'translateY(0)';
    }, 30); // tiny delay so initial state registers
  }

  /* ── Apply animation to a list of elements with stagger ──── */
  // Returns the delay at which the LAST element starts animating
  function animList(els, startDelay, stagger) {
    var delay = startDelay;
    els.forEach(function (el) {
      if (!el) return;
      animEl(el, delay);
      delay += stagger;
    });
    return delay - stagger; // last element's start delay
  }

  /* ── Get when last animation fully settles ───────────────── */
  function settleTime(lastDelay) {
    return lastDelay + DUR + 100; // last start + duration + buffer
  }

  /* ── Per-step animation maps ─────────────────────────────── */
  function runStep(step) {

    /* ── Step 0: Business Name ── */
    if (step === 0) {
      var h1     = document.querySelector('.sf-name-title');
      var sub    = document.querySelector('.sf-name-sub');
      var input  = document.querySelector('.sf-input-wrap');
      var btn    = document.querySelector('.sf-name-btn');

      var last = animList([h1, sub, input, btn], 80, STAGGER);
      return settleTime(last);
    }

    /* ── Step 1: Business Type ── */
    if (step === 1) {
      var h1    = document.querySelector('.sf-name-title');
      var sub   = document.querySelector('.sf-name-sub');
      var cards = Array.from(document.querySelectorAll('.sf-biz-card-landscape'));
      var acts  = document.querySelector('.sf-actions');

      animList([h1, sub], 80, STAGGER);
      var last = animList(cards, 80 + STAGGER * 2, CARD_STAGGER);
      animEl(acts, last + CARD_STAGGER + 40);
      return settleTime(last + CARD_STAGGER + 40);
    }

    /* ── Step 2: Restaurant Type ── */
    if (step === 2) {
      var h1    = document.querySelector('.sf-name-title');
      var sub   = document.querySelector('.sf-name-sub');
      var cards = Array.from(document.querySelectorAll('.sf-restaurant-card'));
      var acts  = document.querySelector('.sf-actions');

      animList([h1, sub], 80, STAGGER);
      var last = animList(cards, 80 + STAGGER * 2, CARD_STAGGER);
      animEl(acts, last + CARD_STAGGER + 40);
      return settleTime(last + CARD_STAGGER + 40);
    }

    /* ── Step 3: Area + Multifloor ── */
    if (step === 3) {
      var h1         = document.querySelector('.sf-name-title');
      var sub        = document.querySelector('.sf-name-sub');
      var areaBlock  = document.querySelector('.sf-slider-block');
      var floorBlock = document.querySelector('.sf-multifloor-block');
      var acts       = document.querySelector('.sf-actions');

      animList([h1, sub, areaBlock, floorBlock, acts], 80, STAGGER);
      var last = 80 + STAGGER * 4;
      return settleTime(last);
    }

    /* ── Step 4: Tables ── */
    if (step === 4) {
      var h1      = document.querySelector('.sf-name-title');
      var sub     = document.querySelector('.sf-name-sub');
      var sliders = Array.from(document.querySelectorAll('.sf-slider-block'));
      var acts    = document.querySelector('.sf-actions');

      animList([h1, sub], 80, STAGGER);
      var last = animList(sliders, 80 + STAGGER * 2, STAGGER);
      animEl(acts, last + STAGGER + 40);
      return settleTime(last + STAGGER + 40);
    }

    /* ── Step 5: Budget ── */
    if (step === 5) {
      var h1    = document.querySelector('.sf-name-title');
      var sub   = document.querySelector('.sf-name-sub');
      var cards = Array.from(document.querySelectorAll('.sf-budget-card'));
      var acts  = document.querySelector('.sf-actions');

      animList([h1, sub], 80, STAGGER);
      var last = animList(cards, 80 + STAGGER * 2, CARD_STAGGER);
      animEl(acts, last + CARD_STAGGER + 40);
      return settleTime(last + CARD_STAGGER + 40);
    }

    /* ── Step 6: Installation ── */
    if (step === 6) {
      var h1    = document.querySelector('.sf-name-title');
      var sub   = document.querySelector('.sf-name-sub');
      var cards = Array.from(document.querySelectorAll('.sf6-card'));
      var note  = document.querySelector('.sf6-info-note');
      var acts  = document.querySelector('.sf-actions');

      animList([h1, sub], 80, STAGGER);
      var last = animList(cards, 80 + STAGGER * 2, CARD_STAGGER);
      animEl(note, last + CARD_STAGGER + 20);
      animEl(acts, last + CARD_STAGGER + 60);
      return settleTime(last + CARD_STAGGER + 60);
    }

    /* ── Step 7: Staffing ── */
    if (step === 7) {
      var h1    = document.querySelector('.sf-name-title');
      var sub   = document.querySelector('.sf-name-sub');
      var rows  = Array.from(document.querySelectorAll('.sf6-staff-row'));
      var acts  = document.querySelector('.sf-actions');

      animList([h1, sub], 80, STAGGER);
      var last = animList(rows, 80 + STAGGER * 2, CARD_STAGGER);
      animEl(acts, last + CARD_STAGGER + 40);
      return settleTime(last + CARD_STAGGER + 40);
    }

    return 800; // fallback
  }

  /* ── Also animate summary panel ─────────────────────────── */
  function animSummary() {
    var summary = document.querySelector('.sf-wiz-summary-inner');
    if (!summary) return;
    animEl(summary, 200);
  }

  /* ── Also animate progress bar ──────────────────────────── */
  function animProgress() {
    var bar = document.querySelector('.sf-wiz-progress');
    if (!bar) return;
    animEl(bar, 40);
  }

  /* ── Init ────────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', function () {
    // Only run on setup.php
    var stepAttr = document.body.dataset.wizStep;
    if (stepAttr === undefined) return;

    var step = parseInt(stepAttr);
    if (isNaN(step)) return;

    animProgress();
    animSummary();

    // Run step animations and get when they settle
    var tooltipDelay = runStep(step);

    // Fire tutorial AFTER all elements have animated in
    if (window.SFTutorial) {
      // Tutorial already has its own 700ms delay in tutorial.js
      // We override it by delaying the start relative to animation settle
      // Store settle time so tutorial.js can use it
      window.sfAnimSettleMs = tooltipDelay;
    }
  });

})();