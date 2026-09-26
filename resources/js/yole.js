// Top-down SVG drawing of a yole ronde: hull, bwa dressés (outside the hull), masts, steering paddle and crew seats.
// Positions come from the database (boat_positions): x/y are percentages, y = 0 bow, y = 100 stern, x = 50 hull axis.

const W = 400;
const H = 820;
const X = (x) => x * 4;
const Y = (y) => 22 + y * 7.8;
const PLACEMENT_X = { interieur: 24, milieu: 15, exterieur: 7 };
const COMPASS = ['N', 'N-NE', 'NE', 'E-NE', 'E', 'E-SE', 'SE', 'S-SE', 'S', 'S-SO', 'SO', 'O-SO', 'O', 'O-NO', 'NO', 'N-NO'];

let instance = 0;

export const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

export function windLabel(degrees) {
    if (degrees === null || degrees === undefined || degrees === '') return null;
    return COMPASS[Math.round((((+degrees % 360) + 360) % 360) / 22.5) % 16];
}

/**
 * Bwa dressés sit on the windward side (bwaSide; stored coordinates are on the babord side). Écoutes sit by
 * their sail, to leeward (stored on the tribord side): both are mirrored when the bwa go tribord.
 */
function seatX(position, placement, bwaSide) {
    if (position.role === 'dresseur') {
        const offset = PLACEMENT_X[placement || 'milieu'];
        return X(bwaSide === 'tribord' ? 100 - offset : offset);
    }
    if (position.role === 'ecoute' && bwaSide === 'tribord') return X(100 - position.x);
    return X(position.x);
}

// Seats are name tags (rectangles) so that long first names stay readable.
const tagWidth = (text, compact) => Math.min(compact ? 64 : 118, Math.max(compact ? 40 : 70, text.length * (compact ? 6 : 6.4) + 18));

export const fondIndex = (code) => { const m = /^fond_(\d+)$/.exec(code); return m ? +m[1] : null; };

/** Seats used by a plan: fond / écopeur seats beyond fondCount are hidden. */
export function visiblePositions(positions, fondCount) {
    return positions.filter((p) => { const f = fondIndex(p.code); return f === null || fondCount === null || fondCount === undefined || f <= fondCount; });
}

/**
 * @param {object} opts
 * @param {{sail_count:number, positions:Array}} opts.config
 * @param {Object<string,{label,short,color,zone}>} opts.roles
 * @param {Object<number,{initials,short}>} opts.members
 * @param {Object<string,{member_id:number, placement:?string}>} opts.assignments  keyed by position code
 */
export function yoleSVG(opts) {
    const {
        config, roles, members = {}, assignments = {}, selected = null, interactive = false,
        wind = null, compact = false, labels = true, boatColor = '#0B2545', bwaSide = 'babord', fondCount = 1,
    } = opts;
    const id = `yl${++instance}`;
    const positions = visiblePositions(config.positions, fondCount);
    const windward = bwaSide === 'tribord' ? 'tribord' : 'babord';
    const bwaYs = [...new Set(positions.filter((p) => p.role === 'dresseur').map((p) => p.y))].sort((a, b) => a - b);

    let s = `<svg viewBox="0 0 ${W} ${H}" class="w-full h-full select-none" role="img" aria-label="Plan de la yole vue de dessus">
    <defs>
      <linearGradient id="${id}-sea" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#E6F1FA"/><stop offset="1" stop-color="#D5E7F5"/></linearGradient>
      <linearGradient id="${id}-hull" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#F8FAFC"/><stop offset=".5" stop-color="#FFFFFF"/><stop offset="1" stop-color="#E2E8F0"/></linearGradient>
      <linearGradient id="${id}-wood" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#C98A4B"/><stop offset="1" stop-color="#9A6431"/></linearGradient>
      <filter id="${id}-sh" x="-20%" y="-20%" width="140%" height="140%"><feDropShadow dx="0" dy="2" stdDeviation="2.5" flood-color="#0B2545" flood-opacity=".25"/></filter>
      <pattern id="${id}-waves" width="60" height="24" patternUnits="userSpaceOnUse"><path d="M0 12 Q15 4 30 12 T60 12" fill="none" stroke="#BFD9EE" stroke-width="1.2"/></pattern>
    </defs>
    <rect width="${W}" height="${H}" rx="18" fill="url(#${id}-sea)"/>
    <rect width="${W}" height="${H}" rx="18" fill="url(#${id}-waves)" opacity=".7"/>
    <text x="${W / 2}" y="14" text-anchor="middle" class="yl-dir">▲ AVANT</text>
    <text x="${W / 2}" y="${H - 6}" text-anchor="middle" class="yl-dir">ARRIÈRE ▼</text>
    <text x="16" y="${H / 2}" class="yl-side" transform="rotate(-90 16 ${H / 2})" text-anchor="middle">BÂBORD${windward === 'babord' ? ' · AU VENT' : ''}</text>
    <text x="${W - 16}" y="${H / 2}" class="yl-side" transform="rotate(90 ${W - 16} ${H / 2})" text-anchor="middle">TRIBORD${windward === 'tribord' ? ' · AU VENT' : ''}</text>
    <path d="M186 790 Q200 812 214 790" fill="none" stroke="#fff" stroke-width="3" opacity=".8"/>
    <line x1="214" y1="746" x2="262" y2="806" stroke="#7C4A1E" stroke-width="6" stroke-linecap="round"/>
    <ellipse cx="266" cy="811" rx="9" ry="16" transform="rotate(-38 266 811)" fill="#9A6431"/>`;

    // Bwa dressés: one pole per dresseur, wedged across the hull and sticking out on the windward side.
    const poleX = windward === 'babord' ? 26 : 146;
    bwaYs.forEach((y, i) => {
        const yy = Y(y);
        s += `<rect x="${poleX}" y="${yy - 5}" width="${W - 26 - 146}" height="10" rx="5" fill="url(#${id}-wood)" stroke="#6B3F16" stroke-width="1"/>
              <text x="${windward === 'babord' ? 138 : W - 138}" y="${yy - 9}" text-anchor="${windward === 'babord' ? 'end' : 'start'}" class="yl-bwa-label">bwa ${i + 1}</text>`;
    });

    s += `<path d="M200 26 C238 92 260 222 260 400 C260 598 252 702 238 754 Q200 794 162 754 C148 702 140 598 140 400 C140 222 162 92 200 26 Z"
            fill="url(#${id}-hull)" stroke="${escapeHtml(boatColor)}" stroke-width="5" filter="url(#${id}-sh)"/>
          <path d="M200 44 C230 104 248 226 248 400 C248 592 241 694 229 742 Q200 774 171 742 C159 694 152 592 152 400 C152 226 170 104 200 44 Z" fill="none" stroke="#CBD5E1" stroke-width="1.5"/>
          <line x1="200" y1="50" x2="200" y2="760" stroke="#E2E8F0" stroke-width="1.5" stroke-dasharray="4 6"/>`;
    bwaYs.forEach((y) => {
        s += `<rect x="146" y="${Y(y) - 4}" width="108" height="8" rx="4" fill="url(#${id}-wood)" opacity=".85"/>`;
    });

    // Masts and sails seen from above. 2 voiles: masts at the bow and about a third of the hull; misaine: the
    // big mast is stepped forward (faux mât). Sails always blow to leeward, opposite the bwa dressés.
    const masts = config.sail_count >= 2 ? [15, 38] : [14];
    const leeward = windward === 'babord' ? 1 : -1;
    masts.forEach((m, i) => {
        const my = Y(m);
        const d = `M200 ${my} Q${200 + leeward * (100 - i * 10)} ${my + 70} ${200 + leeward * (118 - i * 12)} ${my + 150}`;
        s += `<path d="${d}" fill="none" stroke="#fff" stroke-width="12" opacity=".75" stroke-linecap="round"/>
              <path d="${d}" fill="none" stroke="#94A3B8" stroke-width="1.5" stroke-dasharray="3 4"/>
              <circle cx="200" cy="${my}" r="7" fill="#0B2545" stroke="#fff" stroke-width="2"/>`;
    });

    if (wind && wind.dir !== null && wind.dir !== undefined) {
        s += `<g transform="translate(${W - 58} 58)"><circle r="30" fill="#fff" stroke="#CBD5E1"/>
              <g transform="rotate(${(+wind.dir + 180) % 360})"><path d="M0 -20 L7 6 L0 1 L-7 6 Z" fill="#0B2545"/></g>
              <text y="46" text-anchor="middle" class="yl-wind">Vent ${windLabel(wind.dir)}</text>
              ${wind.kts ? `<text y="60" text-anchor="middle" class="yl-wind-s">${+wind.kts} nds</text>` : ''}</g>`;
    }

    positions.forEach((p) => {
        const role = roles[p.role] || { color: '#64748B', short: '?' };
        const assignment = assignments[p.code];
        const member = assignment ? members[assignment.member_id] : null;
        const cx = seatX(p, assignment?.placement, windward);
        const cy = Y(p.y);
        const text = member ? (labels ? member.short : member.initials) : `+ ${role.short}${p.bwa ?? ''}`;
        const h = compact ? 24 : 30;
        const w = tagWidth(text, compact || !labels);
        const x = Math.min(W - 4 - w, Math.max(4, cx - w / 2));
        const y = cy - h / 2;
        const attrs = interactive
            ? `data-pos="${escapeHtml(p.code)}" class="yl-pos cursor-pointer" tabindex="0" role="button" aria-label="${escapeHtml(p.label)}${member ? ' : ' + escapeHtml(member.short) : ''}"`
            : 'class="yl-pos"';
        s += `<g ${attrs}>`;
        if (p.role === 'dresseur') {
            const x0 = X(windward === 'babord' ? PLACEMENT_X.exterieur : 100 - PLACEMENT_X.interieur);
            const x1 = X(windward === 'babord' ? PLACEMENT_X.interieur : 100 - PLACEMENT_X.exterieur);
            s += `<line x1="${x0}" y1="${cy}" x2="${x1}" y2="${cy}" stroke="#fff" stroke-width="2" stroke-dasharray="2 5" opacity=".9"/>`;
        }
        if (selected === p.code) {
            s += `<rect x="${x - 6}" y="${y - 6}" width="${w + 12}" height="${h + 12}" rx="13" fill="none" stroke="#F5B700" stroke-width="4"><animate attributeName="stroke-opacity" values="1;.35;1" dur="1.6s" repeatCount="indefinite"/></rect>`;
        }
        if (member) {
            s += `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="9" fill="${role.color}" stroke="#fff" stroke-width="2.5" filter="url(#${id}-sh)"/>
                  <text x="${x + w / 2}" y="${cy + 4}" text-anchor="middle" class="yl-name">${escapeHtml(text)}</text>`;
        } else {
            s += `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="9" fill="#ffffffe0" stroke="${role.color}" stroke-width="2" stroke-dasharray="5 4"/>
                  <text x="${x + w / 2}" y="${cy + 4}" text-anchor="middle" class="yl-short" fill="${role.color}">${escapeHtml(text)}</text>`;
        }
        s += '</g>';
    });

    return `${s}</svg>`;
}

/**
 * Informative weight distribution (declared weights only — not a stability computation).
 * @param {Array} positions
 * @param {Object<string,{member_id}>} assignments
 * @param {(id:number) => {kg:number}|undefined} memberById
 */
export function balance(positions, assignments, memberById) {
    const result = { bwa: 0, bwaCount: 0, avant: 0, arriere: 0, total: 0, filled: 0, positions: positions.length };
    positions.forEach((p) => {
        const assignment = assignments[p.code];
        const member = assignment && memberById(assignment.member_id);
        if (!member) return;
        const kg = +member.kg || 0;
        result.filled++;
        result.total += kg;
        result[p.y < 50 ? 'avant' : 'arriere'] += kg;
        if (p.role === 'dresseur') { result.bwa += kg; result.bwaCount++; }
    });
    return result;
}

/** Renders every read-only [data-yole] container on the page. */
export function mountDrawings(root = document) {
    root.querySelectorAll('[data-yole]').forEach((el) => {
        el.innerHTML = yoleSVG(JSON.parse(el.dataset.yole));
    });
}
