// assets/site.js
document.addEventListener("DOMContentLoaded", () => {
  // =========================
  // Wizard option cards
  // =========================

  // RADIO groups
  document.querySelectorAll('.sf-card-option input[type="radio"]').forEach((inp) => {
    const syncGroup = () => {
      const name = inp.name;
      document
        .querySelectorAll(`.sf-card-option input[type="radio"][name="${name}"]`)
        .forEach((r) => {
          const card = r.closest(".sf-card-option");
          if (card) card.classList.toggle("selected", r.checked);
        });
    };
    inp.addEventListener("change", syncGroup);
    syncGroup();
  });

  // CHECKBOX
  document.querySelectorAll('.sf-card-option input[type="checkbox"]').forEach((inp) => {
    const syncCard = () => {
      const card = inp.closest(".sf-card-option");
      if (card) card.classList.toggle("selected", inp.checked);
    };
    inp.addEventListener("change", syncCard);
    syncCard();
  });

  // Package cards RADIO groups
  document.querySelectorAll(".sf-pkg-card input[type='radio']").forEach((inp) => {
    const syncGroup = () => {
      const name = inp.name;
      document
        .querySelectorAll(`.sf-pkg-card input[type="radio"][name="${name}"]`)
        .forEach((r) => {
          const card = r.closest(".sf-pkg-card");
          if (card) card.classList.toggle("selected", r.checked);
        });
    };
    inp.addEventListener("change", syncGroup);
    syncGroup();
  });

  // =========================
  // REPLACE + SELLERS PANELS (SAFE / SCOPED)
  // =========================
  // Expected HTML:
  // - Replace button:  data-replace-open="1"
  // - Replace panel:   <div class="sf-replace-panel ..."> right after .sf-cart-row
  //
  // - Sellers button:  data-sellers-open="1"  data-group="some_group_key"
  // - Sellers panel:   <div class="sf-sellers-panel ..."> right after .sf-cart-row
  //
  // If your sellers panel is not immediate sibling, we also look for:
  // .sf-sellers-panel[data-group="..."] inside the same parent container.

  const closePanelsInScope = (scopeEl) => {
    if (!scopeEl) return;
    scopeEl.querySelectorAll(".sf-replace-panel").forEach((p) => p.classList.add("d-none"));
    scopeEl.querySelectorAll(".sf-sellers-panel").forEach((p) => p.classList.add("d-none"));
  };

  document.addEventListener("click", (e) => {
    const replaceBtn = e.target.closest("[data-replace-open='1']");
    const sellersBtn = e.target.closest("[data-sellers-open='1']");

    // Not our buttons
    if (!replaceBtn && !sellersBtn) return;

    e.preventDefault();

    const btn = replaceBtn || sellersBtn;

    // Find the cart row that contains the button
    const row = btn.closest(".sf-cart-row");
    if (!row) return;

    // Scope = the cart container (prevents touching other modules/pages)
    const scope = row.closest(".sf-cart") || row.parentElement;

    // Close all panels in same scope, then open the right one
    closePanelsInScope(scope);

    // Try immediate next sibling first
    const next = row.nextElementSibling;

    // --- Replace ---
    if (replaceBtn) {
      if (next && next.classList.contains("sf-replace-panel")) {
        next.classList.remove("d-none");
        return;
      }

      // Fallback: find replace panel by type (your existing markup uses data-replace-panel="type")
      const type = replaceBtn.getAttribute("data-type");
      if (type && scope) {
        const alt = scope.querySelector(`.sf-replace-panel[data-replace-panel="${type}"]`);
        if (alt) alt.classList.remove("d-none");
      }
      return;
    }

    // --- Sellers ---
    if (sellersBtn) {
      // Prefer data-group (product_group_key)
      const group = sellersBtn.getAttribute("data-group");

      // If your sellers panel is right after row
      if (next && next.classList.contains("sf-sellers-panel")) {
        next.classList.remove("d-none");
        return;
      }

      // Fallback: find by group key
      if (group && scope) {
        const alt = scope.querySelector(`.sf-sellers-panel[data-group="${CSS.escape(group)}"]`);
        if (alt) alt.classList.remove("d-none");
      }
      return;
    }
  });

  // ===============================
  // Tier-per-module (safe + scoped)
  // ===============================
  document.querySelectorAll('input[name="modules[]"]').forEach((cb) => {
    const key = cb.value;

    const wrap = document.querySelector(`[data-tier-wrap="${key}"]`);
    const hidden = document.querySelector(`[data-tier-input="${key}"]`);

    // If page doesn't have tier UI, skip safely
    if (!wrap || !hidden) return;

    const setVisible = (isOn) => {
      wrap.classList.toggle("d-none", !isOn);
    };

    const setTier = (tier) => {
      hidden.value = tier;

      // UI state (active chip)
      wrap.querySelectorAll("[data-tier]").forEach((b) => {
        b.classList.toggle("active", b.getAttribute("data-tier") === tier);
        b.setAttribute("aria-pressed", b.classList.contains("active") ? "true" : "false");
      });
    };

    // Default tier if empty
    if (!hidden.value) setTier("Balanced");

    const sync = () => {
      setVisible(cb.checked);
    };

    cb.addEventListener("change", sync);
    sync();

    wrap.querySelectorAll("[data-tier]").forEach((btn) => {
      btn.addEventListener("click", (ev) => {
        ev.preventDefault();
        const tier = btn.getAttribute("data-tier");
        if (!tier) return;
        setTier(tier);
      });
    });
  });
});
(function () {
  const track = document.getElementById('sfWwTrack');
  if (!track) return;

  const originals = Array.from(track.querySelectorAll('.sf-ww-card'));
  const total = originals.length;
  if (!total) return;

  // Clone before and after for infinite loop
  originals.forEach(c => track.appendChild(c.cloneNode(true)));
  originals.forEach(c => track.insertBefore(c.cloneNode(true), track.firstChild));

  // Start at first real card (index = total, since we prepended `total` clones)
  let current = total;
  let locked = false;

  function cardWidth() {
    const c = track.querySelectorAll('.sf-ww-card')[0];
    const gap = parseFloat(getComputedStyle(track).gap) || 0;
    return c.offsetWidth + gap;
  }

  function jump(index, animate) {
    track.style.transition = animate ? 'transform 0.55s cubic-bezier(0.4,0,0.2,1)' : 'none';
    track.style.transform = `translateX(-${index * cardWidth()}px)`;
  }

  track.addEventListener('transitionend', () => {
    if (current >= total * 2) { current = total;          jump(current, false); }
    if (current < total)      { current = total * 2 - 1;  jump(current, false); }
    locked = false;
  });

  function go(dir) {
    if (locked) return;
    locked = true;
    current += dir;
    jump(current, true);
  }

  // Init
  jump(current, false);

  // Arrows
  document.getElementById('sfWwNext')?.addEventListener('click', () => go(1));
  document.getElementById('sfWwPrev')?.addEventListener('click', () => go(-1));

  // Auto-play
  let timer = setInterval(() => go(1), 4000);
  const resetTimer = () => { clearInterval(timer); timer = setInterval(() => go(1), 4000); };

  // Drag
  let startX = 0, dragging = false;
  track.addEventListener('mousedown', e => { startX = e.clientX; dragging = true; track.classList.add('is-dragging'); clearInterval(timer); });
  window.addEventListener('mouseup', e => {
    if (!dragging) return;
    dragging = false;
    track.classList.remove('is-dragging');
    if (Math.abs(e.clientX - startX) > 60) go(e.clientX < startX ? 1 : -1);
    resetTimer();
  });

  // Touch
  track.addEventListener('touchstart', e => { startX = e.touches[0].clientX; clearInterval(timer); }, { passive: true });
  track.addEventListener('touchend', e => {
    const diff = e.changedTouches[0].clientX - startX;
    if (Math.abs(diff) > 60) go(diff < 0 ? 1 : -1);
    resetTimer();
  });

  window.addEventListener('resize', () => jump(current, false));
})();
function initPriceSlider(){
  const sMin = document.getElementById('sliderMin');
  const sMax = document.getElementById('sliderMax');
  const fill = document.getElementById('sfRangeFill');
  const valMin = document.getElementById('sliderMinVal');
  const valMax = document.getElementById('sliderMaxVal');
  const hidMin = document.getElementById('min_price');
  const hidMax = document.getElementById('max_price');
  const rangeSlider = document.getElementById('sfRangeSlider');
  
  if(!sMin || !sMax || !rangeSlider) {
    console.warn('Price sliders not found in DOM');
    return;
  }
  
  const MAX_VAL = 200000;

  function update(){
    let lo = parseInt(sMin.value);
    let hi = parseInt(sMax.value);
    
    // Prevent min from exceeding max
    if(lo > hi) {
      let temp = lo;
      lo = hi;
      hi = temp;
      sMin.value = lo;
      sMax.value = hi;
    }
    
    // Update fill bar position and width
    const pct1 = (lo / MAX_VAL) * 100;
    const pct2 = (hi / MAX_VAL) * 100;
    if(fill){
      fill.style.left = pct1 + '%';
      fill.style.width = (pct2 - pct1) + '%';
    }
    
    // Update visible label numbers
    if(valMin) {
      valMin.textContent = lo.toLocaleString();
    }
    if(valMax) {
      valMax.textContent = hi.toLocaleString();
    }
    
    // Update hidden inputs for form submission
    if(hidMin) {
      hidMin.value = lo;
    }
    if(hidMax) {
      hidMax.value = hi;
    }
    
    console.log('Slider updated - Min: ' + lo + ', Max: ' + hi);
  }

  // Attach event listeners for input changes
  sMin.addEventListener('input', update);
  sMax.addEventListener('input', update);
  
  // Determine which slider should be on top based on proximity to pointer
  rangeSlider.addEventListener('pointermove', (e) => {
    const rect = rangeSlider.getBoundingClientRect();
    const pointerX = e.clientX - rect.left;
    const sliderWidth = rect.width;
    const pointerPct = (pointerX / sliderWidth) * 100;
    
    const lo = parseInt(sMin.value);
    const hi = parseInt(sMax.value);
    const minPct = (lo / MAX_VAL) * 100;
    const maxPct = (hi / MAX_VAL) * 100;
    
    // Determine which slider is closer to pointer
    const minDist = Math.abs(pointerPct - minPct);
    const maxDist = Math.abs(pointerPct - maxPct);
    
    if(minDist < maxDist) {
      sMin.style.zIndex = 6;
      sMax.style.zIndex = 4;
    } else {
      sMin.style.zIndex = 4;
      sMax.style.zIndex = 6;
    }
  });
  
  // Leave range slider to restore default on mouse leave
  rangeSlider.addEventListener('pointerleave', () => {
    const lo = parseInt(sMin.value);
    const minPct = (lo / MAX_VAL) * 100;
    const maxPct = ((parseInt(sMax.value)) / MAX_VAL) * 100;
    
    // Keep closer slider on top
    if(Math.abs(50 - minPct) < Math.abs(50 - maxPct)) {
      sMin.style.zIndex = 6;
      sMax.style.zIndex = 4;
    } else {
      sMin.style.zIndex = 4;
      sMax.style.zIndex = 6;
    }
  });
  
  // Also ensure values are captured on form submit
  const form = sMin.closest('form');
  if(form) {
    form.addEventListener('submit', function(e) {
      update(); // Force update before submission
      console.log('Form submitting with min_price=' + hidMin.value + ', max_price=' + hidMax.value);
    });
  }
  
  // Initialize on load
  update();
}

// Wait for DOM to be ready, then initialize
if(document.readyState === 'loading'){
  document.addEventListener('DOMContentLoaded', initPriceSlider);
} else {
  // DOM already loaded
  setTimeout(initPriceSlider, 100);
}

/* ===========================
   CUSTOM SELECT DROPDOWNS
=========================== */
function initCustomSelects(){
  if(!document.querySelector('.sf-custom-select')) return;
  
  const customSelects = document.querySelectorAll('.sf-custom-select');
  
  customSelects.forEach(selectWrapper => {
    const trigger = selectWrapper.querySelector('.sf-custom-select-trigger');
    const menu = selectWrapper.querySelector('.sf-custom-select-menu');
    const options = selectWrapper.querySelectorAll('.sf-custom-select-option');
    const hiddenSelect = selectWrapper.querySelector('select');
    const textDisplay = trigger.querySelector('.sf-custom-select-text');
    const label = trigger.getAttribute('data-label');
    
    if(!trigger || !menu || !hiddenSelect) return;
    
    // Initialize: set initial text based on hidden select value
    function updateDisplay(){
      const selected = hiddenSelect.querySelector('option:checked');
      if(selected){
        const text = selected.textContent.trim();
        textDisplay.textContent = text || label;
      }
    }
    
    updateDisplay();
    
    // Toggle menu on trigger click
    trigger.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const isOpen = menu.classList.contains('is-open');
      closeAllMenus();
      if(!isOpen){
        menu.classList.add('is-open');
        trigger.classList.add('is-open');
      }
    });
    
    // Handle option selection
    options.forEach(option => {
      option.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        const value = option.getAttribute('data-value');
        
        // Update hidden select
        hiddenSelect.value = value;
        
        // Update visual state
        options.forEach(o => o.classList.remove('is-selected'));
        option.classList.add('is-selected');
        
        // Update trigger text
        const text = option.textContent.trim();
        textDisplay.textContent = text || label;
        
        // Close menu
        menu.classList.remove('is-open');
        trigger.classList.remove('is-open');
        
        // Fire change event for form
        hiddenSelect.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
    
    // Mark initial selected option
    options.forEach(option => {
      const value = option.getAttribute('data-value');
      if(hiddenSelect.value === value){
        option.classList.add('is-selected');
      }
    });
  });
  
  // Close all menus when clicking outside
  function closeAllMenus(){
    document.querySelectorAll('.sf-custom-select-menu').forEach(menu => {
      menu.classList.remove('is-open');
    });
    document.querySelectorAll('.sf-custom-select-trigger').forEach(trigger => {
      trigger.classList.remove('is-open');
    });
  }
  
  document.addEventListener('click', (e) => {
    if(!e.target.closest('.sf-custom-select')){
      closeAllMenus();
    }
  });
  
  document.addEventListener('keydown', (e) => {
    if(e.key === 'Escape'){
      closeAllMenus();
    }
  });
}

if(document.readyState === 'loading'){
  document.addEventListener('DOMContentLoaded', initCustomSelects);
} else {
  setTimeout(initCustomSelects, 100);
}