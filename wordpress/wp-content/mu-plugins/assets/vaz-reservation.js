/**
 * Villa Azur — reservation UI + pricing engine (front-end).
 *
 * Consumes window.VAZ_RESA (config + i18n) localized from PHP, so every price and
 * limit is the SAME value the server uses — nothing is hardcoded here.
 *
 * Layers:
 *   engine.*  — pure pricing functions, mirror VAZ_Reservation::quote() in PHP.
 *   ui        — repeatable room cards + live itemised total, injected into the
 *               Fluent Forms #4 popup once it is open and visible.
 *
 * The module never trusts itself for the record: it writes the room payload into
 * hidden Fluent Forms fields and the server re-computes the authoritative total.
 */
(function () {
  'use strict';

  if (typeof window.jQuery === 'undefined' || !window.VAZ_RESA) { return; }

  var $    = window.jQuery;
  var CFG  = window.VAZ_RESA.config;
  var T    = window.VAZ_RESA.i18n;
  var POP  = '#pum-' + CFG.popup_id;
  var FORM = '#fluentform_' + CFG.form_id;

  /* ───────────────────────────── Pricing engine ─────────────────────────── */

  var engine = {
    // Y-m-d string → season key (ISO strings compare lexicographically).
    seasonForDate: function (iso) {
      var seasons = CFG.seasons, key, ranges, i;
      for (key in seasons) {
        if (!seasons.hasOwnProperty(key)) { continue; }
        ranges = seasons[key].ranges;
        for (i = 0; i < ranges.length; i++) {
          if (iso >= ranges[i][0] && iso <= ranges[i][1]) { return key; }
        }
      }
      return CFG.default_season;
    },

    // Inclusive check-in, exclusive check-out → array of Y-m-d night strings.
    nightsBetween: function (inIso, outIso) {
      if (!inIso || !outIso || outIso <= inIso) { return []; }
      var nights = [];
      var cursor = engine._parse(inIso);
      var end    = engine._parse(outIso);
      var guard  = 0;
      while (cursor < end && guard < 400) {
        nights.push(engine._iso(cursor));
        cursor.setDate(cursor.getDate() + 1);
        guard++;
      }
      return nights;
    },

    _parse: function (iso) {
      var p = iso.split('-');
      return new Date(+p[0], +p[1] - 1, +p[2]);
    },
    _iso: function (d) {
      var m = d.getMonth() + 1, day = d.getDate();
      return d.getFullYear() + '-' + (m < 10 ? '0' + m : m) + '-' + (day < 10 ? '0' + day : day);
    },

    /**
     * quote(inIso, outIso, rooms) → itemised breakdown (mirrors PHP quote()).
     * rooms: [{type, board, adults, children}]
     */
    quote: function (inIso, outIso, rooms) {
      var nights = engine.nightsBetween(inIso, outIso);
      var n = nights.length;
      var out = {
        currency: CFG.currency, nights: n, valid: n > 0 && rooms.length > 0,
        accommodation: 0, child_discount: 0, adult_discount: 0,
        single_supp: 0, tourist_tax: 0, total: 0, total_guests: 0,
        rooms: [], discount_notes: []
      };
      if (n === 0) { return out; }

      var childRate = CFG.discounts.child_under_12.rate;
      var adultRate = CFG.discounts.third_adult.rate;

      rooms.forEach(function (room, idx) {
        var board    = (room.board === 'hb') ? 'hb' : 'bb';
        var adults   = Math.max(0, parseInt(room.adults, 10) || 0);
        var children = Math.max(0, parseInt(room.children, 10) || 0);

        var acc = 0, cd = 0, ad = 0, ss = 0;
        nights.forEach(function (date) {
          var s    = CFG.seasons[engine.seasonForDate(date)];
          var rate = parseFloat(s[board]);
          var supp = parseFloat(s.single_supp);
          acc += (adults + children) * rate;
          cd  += children * rate * childRate;
          ad  += Math.max(0, adults - 2) * rate * adultRate;
          if (adults === 1) { ss += supp; }
        });

        out.accommodation  += acc;
        out.child_discount += cd;
        out.adult_discount += ad;
        out.single_supp    += ss;
        out.total_guests   += adults + children;
        out.rooms.push({
          index: idx + 1, type: room.type, board: board,
          adults: adults, children: children,
          child_discount: round2(cd), adult_discount: round2(ad), single_supp: round2(ss),
          subtotal: round2(acc - cd - ad + ss)
        });
      });

      var tax = CFG.tourist_tax;
      var taxNights = Math.min(n, tax.max_nights);
      var taxable = tax.applies_to_children
        ? out.total_guests
        : out.rooms.reduce(function (c, r) { return c + r.adults; }, 0);
      out.tourist_tax = taxable * taxNights * tax.per_person_per_night;

      out.total = out.accommodation - out.child_discount - out.adult_discount
        + out.single_supp + out.tourist_tax;

      if (out.child_discount > 0) { out.discount_notes.push(CFG.discounts.child_under_12.label); }
      if (out.adult_discount > 0) { out.discount_notes.push(CFG.discounts.third_adult.label); }

      ['accommodation', 'child_discount', 'adult_discount', 'single_supp', 'tourist_tax', 'total']
        .forEach(function (k) { out[k] = round2(out[k]); });
      return out;
    }
  };

  function round2(n) { return Math.round((n + Number.EPSILON) * 100) / 100; }

  // Percentage shown from config (French spaced "%"), so changing a discount
  // rate in the PHP config updates both the maths and the displayed percentage.
  function pct(rate) { return Math.round(rate * 100) + ' %'; }

  // Money format matching PHP: thin-space thousands, comma decimals, no trailing
  // ,00 on whole numbers.
  function money(n) {
    n = round2(n);
    var whole = Math.trunc(n);
    var hasDec = n !== whole;
    var s = Math.abs(whole).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    if (hasDec) {
      var dec = Math.round(Math.abs(n - whole) * 100);
      s += ',' + (dec < 10 ? '0' + dec : dec);
    }
    return (n < 0 ? '-' : '') + s;
  }
  function amount(n) { return money(n) + ' ' + CFG.currency; }

  /* ─────────────────────────────── State / UI ───────────────────────────── */

  var state = { rooms: [] };

  function defaultRoom(type) {
    type = type || 'simple';
    return { type: type, board: 'bb', adults: type === 'double' ? 2 : 1, children: 0 };
  }

  // Read the two Fluent Forms date inputs → ISO, preferring the flatpickr
  // instance (authoritative) and falling back to parsing the d/m/Y text.
  function readDate($popup, name) {
    var el = $popup.find('input[name="' + name + '"]')[0];
    if (!el) { return ''; }
    if (el._flatpickr && el._flatpickr.selectedDates && el._flatpickr.selectedDates[0]) {
      return engine._iso(el._flatpickr.selectedDates[0]);
    }
    var v = (el.value || '').trim();
    var m = v.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    return m ? (m[3] + '-' + m[2] + '-' + m[1]) : '';
  }

  function stepper(field, value, min, max) {
    return '' +
      '<div class="vaz-stepper" data-field="' + field + '">' +
      '  <button type="button" class="vaz-step vaz-step--minus" data-dir="-1" aria-label="Diminuer">&minus;</button>' +
      '  <span class="vaz-step-val" aria-live="polite">' + value + '</span>' +
      '  <button type="button" class="vaz-step vaz-step--plus" data-dir="1" aria-label="Augmenter">+</button>' +
      '  <input type="hidden" data-min="' + min + '" data-max="' + max + '" value="' + value + '">' +
      '</div>';
  }

  function segmented(name, options, current) {
    var html = '<div class="vaz-seg" role="radiogroup" data-field="' + name + '">';
    Object.keys(options).forEach(function (key) {
      var on = key === current;
      html += '' +
        '<button type="button" class="vaz-seg-opt' + (on ? ' is-on' : '') + '" ' +
        'role="radio" aria-checked="' + on + '" data-val="' + key + '">' +
        '<span class="vaz-seg-label">' + options[key].label + '</span>' +
        (options[key].sub ? '<span class="vaz-seg-sub">' + options[key].sub + '</span>' : '') +
        '</button>';
    });
    return html + '</div>';
  }

  function roomCard(room, i) {
    var lim = CFG.limits;
    return '' +
      '<div class="vaz-room" data-room="' + i + '">' +
      '  <div class="vaz-room-head">' +
      '    <span class="vaz-room-title">' + T.room + ' ' + (i + 1) + '</span>' +
      (i > 0 ? '    <button type="button" class="vaz-room-remove" aria-label="' + T.removeRoom + '">' + T.removeRoom + '</button>' : '') +
      '  </div>' +
      '  <div class="vaz-field">' +
      '    <label class="vaz-lbl">' + T.roomType + '</label>' +
      segmented('type', CFG.room_types, room.type) +
      '  </div>' +
      '  <div class="vaz-field">' +
      '    <label class="vaz-lbl">' + T.board + '</label>' +
      segmented('board', CFG.boards, room.board) +
      '  </div>' +
      '  <div class="vaz-occ">' +
      '    <div class="vaz-field vaz-field--occ">' +
      '      <label class="vaz-lbl">' + T.adults + '</label>' +
      stepper('adults', room.adults, lim.min_adults_room, lim.max_adults_room) +
      '    </div>' +
      '    <div class="vaz-field vaz-field--occ">' +
      '      <label class="vaz-lbl">' + T.children + '</label>' +
      stepper('children', room.children, 0, lim.max_children_room) +
      '    </div>' +
      '  </div>' +
      '  <div class="vaz-room-hint">' + T.childHint + '</div>' +
      '</div>';
  }

  function render($popup) {
    var $host = $popup.find('#vaz-resa-rooms');
    if (!$host.length) { return; }
    $host.html(state.rooms.map(roomCard).join(''));

    var $add = $popup.find('#vaz-resa-add');
    var full = state.rooms.length >= CFG.limits.max_rooms;
    $add.prop('disabled', full).attr('aria-disabled', full)
        .find('.vaz-add-note').text(full ? '(' + T.maxRoomsHit + ')' : '');

    recalc($popup);
  }

  function recalc($popup) {
    var inIso  = readDate($popup, 'date_arrivee');
    var outIso = readDate($popup, 'date_depart');
    var q = engine.quote(inIso, outIso, state.rooms);
    paintSummary($popup, q, inIso, outIso);
    syncHidden($popup, q);
  }

  function line(label, value, mod) {
    return '<div class="vaz-sum-row' + (mod ? ' ' + mod : '') + '">' +
      '<span>' + label + '</span><span class="vaz-sum-amt">' + value + '</span></div>';
  }

  function paintSummary($popup, q, inIso, outIso) {
    var $s = $popup.find('#vaz-resa-summary');
    if (!$s.length) { return; }

    if (!inIso || !outIso || q.nights === 0) {
      $s.html('<div class="vaz-sum-empty">' + T.pickDates + '</div>');
      return;
    }

    // Stay line: "10 août → 13 août 2026 · 3 nuits".
    var stay = formatStay(inIso, outIso, q.nights);

    // Per-room line items — each carries its OWN price and its own "why" notes,
    // so a guest always sees what each room costs and why any discount applied.
    var roomsHtml = q.rooms.map(function (r) {
      var typeLabel  = (CFG.room_types[r.type] || {}).label || r.type;
      var boardLabel = (CFG.boards[r.board] || {}).label || r.board;
      var occ = r.adults + ' adulte' + (r.adults > 1 ? 's' : '');
      if (r.children > 0) { occ += ', ' + r.children + ' enfant' + (r.children > 1 ? 's' : ''); }

      var notes = [];
      if (r.child_discount > 0) { notes.push('✓ ' + T.childDiscount + ' −' + pct(CFG.discounts.child_under_12.rate) + ' · −' + amount(r.child_discount)); }
      if (r.adult_discount > 0) { notes.push('✓ ' + T.adultDiscount + ' −' + pct(CFG.discounts.third_adult.rate) + ' · −' + amount(r.adult_discount)); }
      if (r.single_supp > 0)    { notes.push('Supplément single · +' + amount(r.single_supp)); }
      var notesHtml = notes.length
        ? '<div class="vaz-sum-room-notes">' + notes.map(function (n) { return '<span>' + n + '</span>'; }).join('') + '</div>'
        : '';

      return '<div class="vaz-sum-room">' +
        '<div class="vaz-sum-room-top">' +
          '<span class="vaz-sum-room-name">' + T.room + ' ' + r.index + ' · ' + typeLabel + '</span>' +
          '<span class="vaz-sum-amt">' + amount(r.subtotal) + '</span>' +
        '</div>' +
        '<div class="vaz-sum-room-meta">' + boardLabel + ' · ' + occ + ' · ' +
          q.nights + ' ' + (q.nights > 1 ? T.nights : T.night) + '</div>' +
        notesHtml +
      '</div>';
    }).join('');

    var accSubtotal = round2(q.total - q.tourist_tax);
    var tx = CFG.tourist_tax;
    var taxDetail = tx.per_person_per_night + ' ' + q.currency + ' par personne et par nuit, plafonnée à ' +
      tx.max_nights + ' nuits (taxe gouvernementale, Loi de finances 2024).';

    $s.html('' +
      '<div class="vaz-sum-head">' + T.summaryTitle + '</div>' +
      '<div class="vaz-sum-stay">' + stay + '</div>' +
      '<div class="vaz-sum-rooms">' + roomsHtml + '</div>' +
      '<div class="vaz-sum-rows">' +
        line('Sous-total hébergement', amount(accSubtotal)) +
        line(T.touristTax, amount(q.tourist_tax)) +
      '</div>' +
      '<div class="vaz-sum-taxnote"><span class="vaz-sum-taxnote-i">i</span>' + taxDetail + '</div>' +
      '<div class="vaz-sum-total"><span>' + T.total + '</span>' +
        '<span class="vaz-sum-grand">' + amount(q.total) + '</span></div>' +
      '<p class="vaz-sum-note">' + T.estimateNote + '</p>');
  }

  // "10 août → 13 août 2026 · 3 nuits" — year shown once, on the departure.
  function formatStay(inIso, outIso, nights) {
    var a = engine._parse(inIso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' });
    var b = engine._parse(outIso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
    return a + ' → ' + b + ' · ' + nights + ' ' + (nights > 1 ? T.nights : T.night);
  }

  // Keep the hidden Fluent Forms fields current on every change, so the value is
  // always right at submit time without needing a submit-time hook.
  function syncHidden($popup, q) {
    var $f = $popup.find(FORM);
    function set(name, val) {
      var $i = $f.find('[name="' + name + '"]');
      if ($i.length) { $i.val(val); }
    }
    set('vaz_rooms_json', JSON.stringify(state.rooms.map(function (r) {
      return { type: r.type, board: r.board, adults: r.adults, children: r.children };
    })));
    set('vaz_nights', String(q.nights));
    set('vaz_grand_total', q.nights ? amount(q.total) : '');

    var summary = q.rooms.map(function (r) {
      var type  = (CFG.room_types[r.type] || {}).label || r.type;
      var board = (CFG.boards[r.board] || {}).label || r.board;
      var occ   = r.adults + ' adulte' + (r.adults > 1 ? 's' : '');
      if (r.children > 0) { occ += ' + ' + r.children + ' enfant' + (r.children > 1 ? 's' : ''); }
      return 'Chambre ' + r.index + ' — ' + type + ' · ' + board + ' · ' + occ +
        ' — ' + amount(r.subtotal);
    }).join('\n');
    set('vaz_rooms_summary', summary);

    var bd = [];
    if (q.nights) {
      bd.push('Séjour : ' + q.nights + ' ' + (q.nights > 1 ? 'nuits' : 'nuit'));
      bd.push('Hébergement : ' + amount(q.accommodation));
      if (q.child_discount > 0) { bd.push('Réduction enfant (-12 ans) : -' + amount(q.child_discount)); }
      if (q.adult_discount > 0) { bd.push('3e adulte (-30%) : -' + amount(q.adult_discount)); }
      if (q.single_supp > 0)    { bd.push('Supplément single : ' + amount(q.single_supp)); }
      bd.push('Taxe de séjour : ' + amount(q.tourist_tax));
      bd.push('TOTAL ESTIMÉ : ' + amount(q.total));
    }
    set('vaz_breakdown', bd.join('\n'));
  }

  /* ──────────────────────────── DOM construction ────────────────────────── */

  // Build the reservation block once and insert it after the departure date,
  // before the requests textarea (falling back to before the submit button).
  function ensureBlock($popup) {
    var $form = $popup.find(FORM);
    if (!$form.length || $form.find('#vaz-resa-block').length) { return true; }

    var $block = $('' +
      '<div id="vaz-resa-block" class="vaz-resa">' +
      '  <div class="vaz-resa-title">Vos chambres</div>' +
      '  <div id="vaz-resa-rooms"></div>' +
      '  <button type="button" id="vaz-resa-add" class="vaz-add">' +
      '    <span class="vaz-add-plus">+</span> ' + T.addRoom +
      '    <span class="vaz-add-note"></span>' +
      '  </button>' +
      '  <div id="vaz-resa-summary" class="vaz-sum" aria-live="polite"></div>' +
      '</div>');

    var $depart = $form.find('input[name="date_depart"]').closest('.ff-el-group');
    var $requests = $form.find('textarea[name="demandes_particulieres"]').closest('.ff-el-group');
    if ($depart.length) {
      $block.insertAfter($depart);
    } else if ($requests.length) {
      $block.insertBefore($requests);
    } else {
      $form.find('.ff-el-group.ff_submit_btn_wrapper, .ff-t-container .ff-el-group').last().before($block);
    }
    return true;
  }

  function clampStepper($stepper, delta) {
    var $val = $stepper.find('.vaz-step-val');
    var $inp = $stepper.find('input');
    var min = parseInt($inp.attr('data-min'), 10);
    var max = parseInt($inp.attr('data-max'), 10);
    var next = Math.min(max, Math.max(min, (parseInt($inp.val(), 10) || 0) + delta));
    $inp.val(next);
    $val.text(next);
    return next;
  }

  // One delegated handler set, bound once per popup element.
  function bindEvents($popup) {
    if ($popup.data('vazBound')) { return; }
    $popup.data('vazBound', true);

    // Keep every click on our controls from bubbling up to Popup Maker's
    // document-level "click-outside-closes" handler. Re-rendering a room card
    // detaches the very button that was clicked, so by the time that handler
    // runs it can't find the target inside the popup and wrongly closes it.
    // One capturing guard covers add / remove / segmented / stepper controls.
    $popup.on('click', '.vaz-add, .vaz-room-remove, .vaz-seg-opt, .vaz-step', function (e) {
      e.stopPropagation();
    });

    // Add room
    $popup.on('click', '#vaz-resa-add', function () {
      if (state.rooms.length >= CFG.limits.max_rooms) { return; }
      state.rooms.push(defaultRoom('double'));
      render($popup);
    });

    // Remove room
    $popup.on('click', '.vaz-room-remove', function () {
      var i = +$(this).closest('.vaz-room').attr('data-room');
      state.rooms.splice(i, 1);
      render($popup);
    });

    // Segmented controls (room type / board)
    $popup.on('click', '.vaz-seg-opt', function () {
      var $opt = $(this);
      var $seg = $opt.closest('.vaz-seg');
      var field = $seg.attr('data-field');
      var i = +$opt.closest('.vaz-room').attr('data-room');
      var val = $opt.attr('data-val');
      var room = state.rooms[i];
      if (!room) { return; }

      if (field === 'type') {
        room.type = val;
        // Sensible occupancy default when switching type (free to adjust after).
        room.adults = Math.min(CFG.limits.max_adults_room,
          Math.max(CFG.limits.min_adults_room, val === 'double' ? Math.max(room.adults, 2) : 1));
        if (val === 'simple') { room.adults = 1; room.children = 0; }
        render($popup); // adults/children steppers may have changed
        return;
      }
      room.board = val;
      $seg.find('.vaz-seg-opt').removeClass('is-on').attr('aria-checked', 'false');
      $opt.addClass('is-on').attr('aria-checked', 'true');
      recalc($popup);
    });

    // Steppers (adults / children) — no full re-render, just this control.
    $popup.on('click', '.vaz-step', function () {
      var $stepper = $(this).closest('.vaz-stepper');
      var i = +$(this).closest('.vaz-room').attr('data-room');
      var field = $stepper.attr('data-field');
      var next = clampStepper($stepper, +$(this).attr('data-dir'));
      if (state.rooms[i]) { state.rooms[i][field] = next; }
      recalc($popup);
    });

    // Live reccompute when the guest changes either date (flatpickr fires change).
    $popup.on('change', 'input[name="date_arrivee"], input[name="date_depart"]', function () {
      recalc($popup);
    });

    // Block submission client-side on an impossible date range, with a specific
    // message. The server re-validates regardless (defence in depth).
    var submitBtn = $popup.find(FORM + ' .ff-btn-submit')[0];
    if (submitBtn && !submitBtn._vazGuard) {
      submitBtn._vazGuard = true;
      submitBtn.addEventListener('click', function (e) {
        var inIso  = readDate($popup, 'date_arrivee');
        var outIso = readDate($popup, 'date_depart');
        if (inIso && outIso && engine.nightsBetween(inIso, outIso).length === 0) {
          e.preventDefault();
          e.stopImmediatePropagation();
          flashDateError($popup);
        }
      }, true); // capture: run before Fluent Forms' own submit handler
    }
  }

  function flashDateError($popup) {
    var $sum = $popup.find('#vaz-resa-summary');
    $sum.html('<div class="vaz-sum-empty vaz-sum-empty--err">' +
      'La date de départ doit être postérieure à la date d’arrivée.</div>');
    var el = $popup.find('input[name="date_depart"]')[0];
    if (el && el.scrollIntoView) { el.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
  }

  /* ────────────────────────────── Lifecycle ─────────────────────────────── */

  function boot($popup) {
    if (!state.rooms.length) { state.rooms = [defaultRoom('simple')]; }
    ensureBlock($popup);
    bindEvents($popup);
    render($popup);
  }

  // Fresh state each time the popup opens (a returning guest starts clean); runs
  // after villa-azur-fix.php's own reset on the same event.
  $(document).on('pumBeforeOpen', POP, function () {
    state.rooms = [defaultRoom('simple')];
  });

  // Datepickers are (re)initialised by villa-azur-fix.php on pumAfterOpen; we run
  // on the same event so the form DOM and date inputs are present and visible.
  $(document).on('pumAfterOpen', POP, function () {
    var $popup = $(this);
    // Defer one tick so villa-azur-fix.php's flatpickr init has attached, letting
    // us read _flatpickr on the date inputs.
    window.setTimeout(function () { boot($popup); }, 0);
  });

  // If the popup is already open at script load (unlikely, but safe), initialise.
  $(function () {
    var $open = $(POP + '.pum-active, ' + POP + '[style*="display: block"]');
    if ($open.length) { boot($open.first()); }
  });
})();
