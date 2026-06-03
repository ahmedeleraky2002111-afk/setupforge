/* =============================================================
   SetupForge Tutorial System — sf-tut
   - Tooltip always to the RIGHT of the highlighted element
   - Arrow always points LEFT
   - Entry animation cycles: right → left → up → down → repeat
   - No fade, movement only
   - Back navigation suppresses tutorial on previous step
============================================================= */

(function () {
  'use strict';

  const STORAGE_KEYS = {
    setup:     'sf_tut_setup_done',
    packages:  'sf_tut_packages_done',
    packages2: 'sf_tut_packages2_done',
    jobs:      'sf_tut_jobs_done',
  };

  const ENTRY_CYCLE = ['right', 'left', 'up', 'down'];

  let currentSteps = [];
  let currentIndex = 0;
  let onDoneCb     = null;

  let overlay, spotlight, tooltip, elCounter, elTitle, elText, btnSkip, btnNext, btnPrev;

  /* ── Build DOM once ──────────────────────────────────────── */
  function buildDOM() {
    if (document.getElementById('sf-tut-overlay')) return;

    overlay = document.createElement('div');
    overlay.id = 'sf-tut-overlay';

    spotlight = document.createElement('div');
    spotlight.id = 'sf-tut-spotlight';

    tooltip = document.createElement('div');
    tooltip.id = 'sf-tut-tooltip';
    tooltip.innerHTML =
      '<div class="sf-tut-top">' +
        '<span id="sf-tut-counter" class="sf-tut-counter"></span>' +
        '<button id="sf-tut-skip-all" class="sf-tut-skip-all">Skip all</button>' +
      '</div>' +
      '<div id="sf-tut-title" class="sf-tut-title"></div>' +
      '<p id="sf-tut-text" class="sf-tut-text"></p>' +
      '<div class="sf-tut-actions">' +
        '<button id="sf-tut-btn-prev" class="sf-tut-btn-prev">\u2190 Back</button>' +
        '<button id="sf-tut-btn-next" class="sf-tut-btn-next">Next \u2192</button>' +
      '</div>';

    document.body.appendChild(overlay);
    document.body.appendChild(spotlight);
    document.body.appendChild(tooltip);

    elCounter = document.getElementById('sf-tut-counter');
    elTitle   = document.getElementById('sf-tut-title');
    elText    = document.getElementById('sf-tut-text');
    btnSkip   = document.getElementById('sf-tut-skip-all');
    btnNext   = document.getElementById('sf-tut-btn-next');
    btnPrev   = document.getElementById('sf-tut-btn-prev');

    btnNext.addEventListener('click', next);
    btnPrev.addEventListener('click', prev);
    btnSkip.addEventListener('click', finish);
    overlay.addEventListener('click', finish);
  }

  /* ── Hide everything ─────────────────────────────────────── */
  function hideTooltip() {
    if (!tooltip) return;
    tooltip.style.display   = 'none';
    spotlight.style.display = 'none';
    overlay.style.display   = 'none';
  }

  /* ── Move spotlight to element ───────────────────────────── */
  function moveSpotlight(r) {
    const pad = 10;
    spotlight.style.top    = (r.top    - pad + window.scrollY) + 'px';
    spotlight.style.left   = (r.left   - pad) + 'px';
    spotlight.style.width  = (r.width  + pad * 2) + 'px';
    spotlight.style.height = (r.height + pad * 2) + 'px';
  }

  /* ── Place tooltip to RIGHT of element, slide in ─────────── */
  function placeTooltip(r, entryDir) {
    const gap = 24;
    const ttW = tooltip.offsetWidth  || 300;
    const ttH = tooltip.offsetHeight || 180;
    const vpW = window.innerWidth;

    // Final position: right of element, vertically centered
    let finalLeft = r.right + gap;
    let finalTop  = r.top + (r.height / 2) - (ttH / 2) + window.scrollY;

    // Clamp
    if (finalLeft + ttW > vpW - 16) finalLeft = vpW - ttW - 16;
    if (finalLeft < 16) finalLeft = 16;
    if (finalTop < window.scrollY + 16) finalTop = window.scrollY + 16;

    // Arrow always points left toward element
    tooltip.setAttribute('data-arrow', 'left');

    // Start offset based on entry direction
    const DIST = 70;
    let startTop  = finalTop;
    let startLeft = finalLeft;

    if (entryDir === 'right') startLeft = finalLeft + DIST;
    if (entryDir === 'left')  startLeft = finalLeft - DIST;
    if (entryDir === 'up')    startTop  = finalTop  - DIST;
    if (entryDir === 'down')  startTop  = finalTop  + DIST;

    // Snap to start — no transition
    tooltip.style.transition = 'none';
    tooltip.style.top  = startTop  + 'px';
    tooltip.style.left = startLeft + 'px';

    // Force reflow
    void tooltip.offsetWidth;

    // Animate to final
    tooltip.style.transition =
      'top  0.50s cubic-bezier(0.16,1,0.3,1),' +
      'left 0.50s cubic-bezier(0.16,1,0.3,1)';
    tooltip.style.top  = finalTop  + 'px';
    tooltip.style.left = finalLeft + 'px';
  }

  /* ── Show a step ─────────────────────────────────────────── */
  function showStep(index) {
    const step = currentSteps[index];

    // el can be a selector string OR a direct DOM element
    const el = typeof step.el === 'string'
      ? document.querySelector(step.el)
      : step.el;

    if (!el) {
      if (index < currentSteps.length - 1) {
        currentIndex++;
        showStep(currentIndex);
      } else {
        finish();
      }
      return;
    }

    // Update content
    elCounter.textContent    = pad2(index + 1) + ' / ' + pad2(currentSteps.length);
    elTitle.textContent      = step.title || '';
    elText.textContent       = step.text;
    btnPrev.style.visibility = index === 0 ? 'hidden' : 'visible';
    btnNext.textContent      = index === currentSteps.length - 1 ? 'Done \u2713' : 'Next \u2192';

    overlay.style.display   = 'block';
    spotlight.style.display = 'block';
    tooltip.style.display   = 'block';

    el.scrollIntoView({ behavior: 'smooth', block: 'center' });

    setTimeout(function () {
      const r        = el.getBoundingClientRect();
      const entryDir = ENTRY_CYCLE[index % ENTRY_CYCLE.length];
      moveSpotlight(r);
      placeTooltip(r, entryDir);
    }, 420);
  }

  function pad2(n) { return n < 10 ? '0' + n : '' + n; }

  /* ── Navigation ──────────────────────────────────────────── */
  function next() {
    if (currentIndex < currentSteps.length - 1) {
      currentIndex++;
      showStep(currentIndex);
    } else {
      finish();
    }
  }

  function prev() {
    if (currentIndex > 0) {
      currentIndex--;
      showStep(currentIndex);
    }
  }

  function finish() {
    if (onDoneCb) onDoneCb();
    hideTooltip();
  }

  /* ── Start ───────────────────────────────────────────────── */
  function start(steps, storageKey) {
    if (!steps || !steps.length) return;

    // Resolve any DOM-index based steps
    const valid = steps.filter(function (s) {
      if (typeof s.el === 'string') return !!document.querySelector(s.el);
      return !!s.el; // direct element reference
    });
    if (!valid.length) return;

    buildDOM();

    currentSteps = valid;
    currentIndex = 0;
    onDoneCb = storageKey
      ? function () { localStorage.setItem(storageKey, '1'); }
      : null;

    showStep(0);
  }

  function shouldShow(key) {
    return !localStorage.getItem(key);
  }

  window.SFTutorial = { start, shouldShow, finish, hideTooltip, KEYS: STORAGE_KEYS };

})();


/* =============================================================
   SETUP.PHP INIT
   Requires: <body data-wiz-step="<?= $step ?>">
============================================================= */
document.addEventListener('DOMContentLoaded', function () {
  if (!window.SFTutorial) return;

  // If user clicked Back, skip tutorial on this load
  if (sessionStorage.getItem('sf_tut_going_back') === '1') {
    sessionStorage.removeItem('sf_tut_going_back');
    return;
  }

  const step = parseInt(document.body.dataset.wizStep);
  if (isNaN(step)) return;

  // Per-step seen flag — once a step is visited, never show tutorial again
  var stepKey = 'sf_tut_step_' + step + '_done';
  if (localStorage.getItem(stepKey)) return;

  // Attach going-back flag to back buttons AND clickable progress steps
  document.querySelectorAll('.sf-btn-back, .sf-wiz-step.is-clickable').forEach(function (btn) {
    btn.addEventListener('click', function () {
      sessionStorage.setItem('sf_tut_going_back', '1');
    });
  });

  // ── Step 3: grab both slider blocks by index ─────────────
  // querySelectorAll gives us [0]=indoor, [1]=outdoor
  var sliderBlocks = document.querySelectorAll('.sf-slider-block');
  var indoorEl     = sliderBlocks[0] || null;
  var outdoorEl    = sliderBlocks[1] || null;
  var multifloorEl = document.querySelector('.sf-multifloor-block') || null;

  const stepMap = {
    0: [{
      el:    '.sf-input-lux',
      title: 'Business Name',
      text:  'This is how your business will appear across the platform. You can change it anytime.',
    }],
    1: [{
      el:    '.sf-biz-landscape-grid',
      title: 'Business Type',
      text:  'Pick what best describes your business — this shapes everything we recommend for you.',
    }],
    2: [{
      el:    '.sf-restaurant-type-grid',
      title: 'Restaurant Style',
      text:  'Your dining style determines the furniture, equipment, and layout we\'ll suggest.',
    }],
    3: [
      {
        el:    '#area_range',
        title: 'Why do we ask about area?',
        text:  'Indoor area only — this determines how many AC units you need and at what capacity.',
      },
      {
        el:    multifloorEl,
        title: 'Multiple Floors',
        text:  'Check this if your restaurant has more than one floor — we\'ll multiply the AC calculation accordingly.',
      },
    ],
    4: [
      {
        el:    indoorEl,
        title: 'Indoor Tables',
        text:  'How many tables do you have inside? We\'ll use this to size your furniture order.',
      },
      {
        el:    outdoorEl,
        title: 'Outdoor Tables',
        text:  'Got a terrace or outdoor seating? Add it here — we\'ll account for it separately.',
      },
    ],
    5: [{
      el:    '.sf-budget-grid',
      title: 'Why do we ask about budget?',
      text:  'We split your budget across Kitchen, POS, Furniture, and AC — then pick the best products that fit each slice.',
    }],
    6: [{
      el:    '.sf6-card-grid',
      title: 'Installation Services',
      text:  'Select what needs to be physically installed. Verified local companies will quote you after payment.',
    }],
    7: [{
      el:    '.sf7-staff-card',
      title: 'Staffing',
      text:  'Tell us how many of each role you need — we\'ll post job listings automatically.',
    }],
  };

  const steps = stepMap[step];
  if (!steps) return;

  // Filter out null elements (e.g. outdoorEl when not on step 3)
  const validSteps = steps.filter(function (s) {
    if (!s.el) return false;
    if (typeof s.el === 'string') return !!document.querySelector(s.el);
    return true;
  });
  if (!validSteps.length) return;

  // Wait for step animations to settle before showing tooltip
  var delay = (window.sfAnimSettleMs && window.sfAnimSettleMs > 700)
    ? window.sfAnimSettleMs
    : 700;

  setTimeout(function () {
    localStorage.setItem(stepKey, '1');
    SFTutorial.start(validSteps, null);
  }, delay);
});


/* =============================================================
   PACKAGES.PHP INIT
============================================================= */
document.addEventListener('DOMContentLoaded', function () {
  if (!window.SFTutorial) return;
  if (!document.querySelector('.sf-pkg-tabs')) return;

  if (SFTutorial.shouldShow(SFTutorial.KEYS.packages)) {
    setTimeout(function () {
      SFTutorial.start([
        {
          el:    '.sf-pkg-tabs',
          title: 'Setup Categories',
          text:  'Kitchen, POS, Furniture, AC — each has its own budget slice and recommended products.',
        },
        {
          el:    '.sf-section-pills',
          title: 'Product Sections',
          text:  'Toggle sections on or off. Turn off anything you don\'t need.',
        },
        {
          el:    '.sf-pkg-progress-wrap',
          title: 'Budget Bar',
          text:  'Shows how much of this category\'s budget you\'ve used. Go over and we\'ll warn you.',
        },
      ], SFTutorial.KEYS.packages);
    }, 800);
  }

  if (SFTutorial.shouldShow(SFTutorial.KEYS.packages2)) {
    const recCard = document.querySelector('.sf-pkg-card--rec');
    if (recCard) {
      function triggerBatch2() {
        recCard.removeEventListener('mouseenter', triggerBatch2);
        SFTutorial.start([
          {
            el:    '.sf-pkg-card--rec',
            title: 'Our Top Pick',
            text:  'Chosen based on your budget, restaurant type, and highest rating. Swap it if you want.',
          },
          {
            el:    '.sf-pkg-card--alt',
            title: 'Alternatives',
            text:  'Same category, different brand or price point — all within your budget tier.',
          },
          {
            el:    '.sf-pkg-card--add',
            title: 'Add Outside Your Tier',
            text:  'Want something we didn\'t recommend? Browse the full catalog.',
          },
        ], SFTutorial.KEYS.packages2);
      }
      recCard.addEventListener('mouseenter', triggerBatch2);
    }
  }
});