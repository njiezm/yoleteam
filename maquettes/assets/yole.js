// Rendu SVG vue de dessus d'une yole ronde : coque, bwa dressés (hors coque), mâts, pagaie, postes.
window.YT = window.YT || {};

(function () {
  const W = 400, H = 820;
  const X = x => x * 4;
  const Y = y => 22 + y * 7.8;
  const PLACEMENT_X = { interieur: 24, milieu: 15, exterieur: 7 };

  function posX(p, placement) {
    if (p.role !== 'dresseur') return X(p.x);
    const off = PLACEMENT_X[placement || 'milieu'];
    return X(p.side === 'babord' ? off : 100 - off);
  }

  YT.yoleSVG = function (opts) {
    const {
      config, plan = {}, placement = {}, selected = null, interactive = false,
      wind = true, compact = false, labels = true, boatColor = '#E11D48', highlight = null,
    } = opts;
    const pos = YT.buildConfig(config.sails, config.bwa);
    const bwaYs = [...new Set(pos.filter(p => p.role === 'dresseur').map(p => p.y))];
    const r = compact ? 20 : 23;

    let s = `<svg viewBox="0 0 ${W} ${H}" class="yole-svg w-full h-full" role="img" aria-label="Plan de la yole vue de dessus">
    <defs>
      <linearGradient id="sea" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#E6F1FA"/><stop offset="1" stop-color="#D5E7F5"/></linearGradient>
      <linearGradient id="hull" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#F8FAFC"/><stop offset=".5" stop-color="#FFFFFF"/><stop offset="1" stop-color="#E2E8F0"/></linearGradient>
      <linearGradient id="wood" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#C98A4B"/><stop offset="1" stop-color="#9A6431"/></linearGradient>
      <filter id="sh" x="-20%" y="-20%" width="140%" height="140%"><feDropShadow dx="0" dy="2" stdDeviation="2.5" flood-color="#0B2545" flood-opacity=".25"/></filter>
      <pattern id="waves" width="60" height="24" patternUnits="userSpaceOnUse"><path d="M0 12 Q15 4 30 12 T60 12" fill="none" stroke="#BFD9EE" stroke-width="1.2"/></pattern>
    </defs>
    <rect width="${W}" height="${H}" rx="18" fill="url(#sea)"/>
    <rect width="${W}" height="${H}" rx="18" fill="url(#waves)" opacity=".7"/>`;

    // repères avant / arrière
    s += `<text x="${W / 2}" y="14" text-anchor="middle" class="yl-dir">▲ AVANT</text>
          <text x="${W / 2}" y="${H - 6}" text-anchor="middle" class="yl-dir">ARRIÈRE ▼</text>
          <text x="16" y="${H / 2}" class="yl-side" transform="rotate(-90 16 ${H / 2})" text-anchor="middle">BÂBORD</text>
          <text x="${W - 16}" y="${H / 2}" class="yl-side" transform="rotate(90 ${W - 16} ${H / 2})" text-anchor="middle">TRIBORD</text>`;

    // sillage + pagaie de gouverne (patron)
    s += `<path d="M186 790 Q200 812 214 790" fill="none" stroke="#fff" stroke-width="3" opacity=".8"/>
          <line x1="214" y1="746" x2="262" y2="806" stroke="#7C4A1E" stroke-width="6" stroke-linecap="round"/>
          <ellipse cx="266" cy="811" rx="9" ry="16" transform="rotate(-38 266 811)" fill="#9A6431"/>`;

    // bwa dressés sous la coque (traversent de bord à bord)
    bwaYs.forEach((y, i) => {
      const yy = Y(y);
      s += `<g class="yl-bwa"><rect x="26" y="${yy - 5}" width="${W - 52}" height="10" rx="5" fill="url(#wood)" stroke="#6B3F16" stroke-width="1"/>
            <text x="${W / 2}" y="${yy - 9}" text-anchor="middle" class="yl-bwa-label">bwa ${i + 1}</text></g>`;
    });

    // coque
    s += `<path d="M200 26 C238 92 260 222 260 400 C260 598 252 702 238 754 Q200 794 162 754 C148 702 140 598 140 400 C140 222 162 92 200 26 Z"
            fill="url(#hull)" stroke="${boatColor}" stroke-width="5" filter="url(#sh)"/>
          <path d="M200 44 C230 104 248 226 248 400 C248 592 241 694 229 742 Q200 774 171 742 C159 694 152 592 152 400 C152 226 170 104 200 44 Z"
            fill="none" stroke="#CBD5E1" stroke-width="1.5"/>
          <line x1="200" y1="50" x2="200" y2="760" stroke="#E2E8F0" stroke-width="1.5" stroke-dasharray="4 6"/>`;
    // bwa visibles par-dessus le plat-bord (continuité)
    bwaYs.forEach(y => {
      const yy = Y(y);
      s += `<rect x="146" y="${yy - 4}" width="108" height="8" rx="4" fill="url(#wood)" opacity=".85"/>`;
    });

    // mâts & voiles (vue de dessus : bôme + voile gonflée)
    const masts = config.sails === 2 ? [15, 38] : [22];
    masts.forEach((m, i) => {
      const my = Y(m);
      s += `<path d="M200 ${my} Q${300 - i * 10} ${my + 70} ${318 - i * 12} ${my + 150}" fill="none" stroke="#fff" stroke-width="12" opacity=".75" stroke-linecap="round"/>
            <path d="M200 ${my} Q${300 - i * 10} ${my + 70} ${318 - i * 12} ${my + 150}" fill="none" stroke="#94A3B8" stroke-width="1.5" stroke-dasharray="3 4"/>
            <circle cx="200" cy="${my}" r="7" fill="#0B2545" stroke="#fff" stroke-width="2"/>`;
    });

    // vent
    if (wind) {
      s += `<g transform="translate(${W - 58} 58)"><circle r="30" fill="#fff" stroke="#CBD5E1"/>
            <g transform="rotate(${wind === true ? 245 : wind})"><path d="M0 -20 L7 6 L0 1 L-7 6 Z" fill="#0B2545"/></g>
            <text y="46" text-anchor="middle" class="yl-wind">Vent E-NE</text><text y="60" text-anchor="middle" class="yl-wind-s">15 nds</text></g>`;
    }

    // postes
    pos.forEach(p => {
      const role = YT.roles[p.role];
      const mid = plan[p.code];
      const m = mid ? YT.member(mid) : null;
      const cx = posX(p, placement[p.code]);
      const cy = Y(p.y);
      const isSel = selected === p.code;
      const dim = highlight && !highlight.includes(p.code);
      const attrs = interactive ? `data-pos="${p.code}" class="yl-pos cursor-pointer" tabindex="0"` : 'class="yl-pos"';
      s += `<g ${attrs} opacity="${dim ? .35 : 1}">`;
      if (p.role === 'dresseur') {
        // rail de placement sur le bwa
        const x0 = X(p.side === 'babord' ? PLACEMENT_X.exterieur : 100 - PLACEMENT_X.interieur);
        const x1 = X(p.side === 'babord' ? PLACEMENT_X.interieur : 100 - PLACEMENT_X.exterieur);
        s += `<line x1="${x0}" y1="${cy}" x2="${x1}" y2="${cy}" stroke="#fff" stroke-width="2" stroke-dasharray="2 5" opacity=".9"/>`;
      }
      if (isSel) s += `<circle cx="${cx}" cy="${cy}" r="${r + 8}" fill="none" stroke="#F5B700" stroke-width="4"><animate attributeName="r" values="${r + 6};${r + 10};${r + 6}" dur="1.6s" repeatCount="indefinite"/></circle>`;
      if (m) {
        s += `<circle cx="${cx}" cy="${cy}" r="${r}" fill="${role.color}" stroke="#fff" stroke-width="3" filter="url(#sh)"/>
              <text x="${cx}" y="${cy + 5}" text-anchor="middle" class="yl-init">${m.initials}</text>`;
        if (labels) {
          const lw = Math.max(58, m.short.length * 6.4 + 12);
          const ly = cy + r + 6;
          s += `<rect x="${cx - lw / 2}" y="${ly}" width="${lw}" height="17" rx="8.5" fill="#0B2545" opacity=".92"/>
                <text x="${cx}" y="${ly + 12}" text-anchor="middle" class="yl-name">${m.short}</text>`;
        }
      } else {
        s += `<circle cx="${cx}" cy="${cy}" r="${r}" fill="#ffffffd9" stroke="${role.color}" stroke-width="2.5" stroke-dasharray="5 4"/>
              <text x="${cx}" y="${cy - 2}" text-anchor="middle" class="yl-plus" fill="${role.color}">+</text>
              <text x="${cx}" y="${cy + 11}" text-anchor="middle" class="yl-short" fill="${role.color}">${role.short}${p.bwa ? p.bwa : ''}</text>`;
      }
      s += `</g>`;
    });
    s += `</svg>`;
    return s;
  };

  // Indicateur d'équilibre (informatif — ne calcule pas la stabilité réelle)
  YT.balance = function (config, plan) {
    const pos = YT.buildConfig(config.sails, config.bwa);
    let bab = 0, tri = 0, av = 0, ar = 0, total = 0, filled = 0;
    pos.forEach(p => {
      const m = plan[p.code] && YT.member(plan[p.code]);
      if (!m) return;
      filled++; total += m.kg;
      if (p.side === 'babord') bab += m.kg;
      if (p.side === 'tribord') tri += m.kg;
      if (p.y < 50) av += m.kg; else ar += m.kg;
    });
    return { bab, tri, av, ar, total, filled, count: pos.filter(p => !p.optional).length, max: pos.length, diff: bab - tri };
  };
})();
