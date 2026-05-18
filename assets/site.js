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