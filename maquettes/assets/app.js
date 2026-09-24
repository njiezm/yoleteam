// YoleTeam — prototype cliquable (routeur hash + écrans). Aucune dépendance hors Tailwind CDN.
(function () {
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const params = new URLSearchParams(location.search);

  // ---------- état de démo ----------
  const S = {
    offline: params.get('offline') === '1',
    attendance: { ...YT.todayAttendance },
    boatId: 1,
    configId: 12,
    plan: { ...YT.demoPlan },
    placement: { ...YT.demoPlacement },
    selected: params.get('sel') || null,
    roleFilter: 'tous',
    search: '',
    memberTab: 'tous',
    sheet: params.get('sheet') === '1',
    modal: params.get('modal') || null,
  };
  const boat = () => YT.boats.find(b => b.id === S.boatId);
  const config = () => boat().configs.find(c => c.id === S.configId) || boat().configs[0];

  // ---------- icônes (tracé type Lucide) ----------
  const P = {
    home: 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z',
    users: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
    calendar: 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2',
    check: 'M20 6 9 17l-5-5',
    checkSq: 'M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11',
    boat: 'M12 2v14M12 2l7 12H12M12 5 6 14h6M2 18c2 2 4 3 10 3s8-1 10-3l-2-2H4z',
    trophy: 'M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0zM17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3',
    settings: 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 0 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 0 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 0 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 0 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1',
    refresh: 'M21 12a9 9 0 0 1-15.5 6.2L3 16M3 12a9 9 0 0 1 15.5-6.2L21 8M21 3v5h-5M3 21v-5h5',
    wifiOff: 'M2 2l20 20M8.5 16.5a5 5 0 0 1 7 0M5 13a10 10 0 0 1 5.2-2.8M19 13a10 10 0 0 0-2-1.6M2 8.8a15 15 0 0 1 4.2-2.6M22 8.8A15 15 0 0 0 10.7 5M12 20h.01',
    cloud: 'M17.5 19H9a7 7 0 1 1 6.7-9h1.8a4.5 4.5 0 1 1 0 9',
    cloudCheck: 'M17.5 19H9a7 7 0 1 1 6.7-9h1.8a4.5 4.5 0 1 1 0 9M9 14l2 2 4-4',
    search: 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16M21 21l-4.3-4.3',
    plus: 'M12 5v14M5 12h14',
    left: 'M15 18l-6-6 6-6',
    right: 'M9 18l6-6-6-6',
    star: 'M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z',
    phone: 'M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2',
    mail: 'M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2M22 6l-10 7L2 6',
    edit: 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z',
    trash: 'M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6',
    clock: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20M12 6v6l4 2',
    pin: 'M12 22s8-6 8-12a8 8 0 1 0-16 0c0 6 8 12 8 12M12 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6',
    wind: 'M9.6 4.6A2 2 0 1 1 11 8H2M12.6 19.4A2 2 0 1 0 14 16H2M17.7 7.7A2.5 2.5 0 1 1 19.5 12H2',
    scale: 'M12 3v18M5 21h14M3 7h18M6 7l-3 7a3 3 0 0 0 6 0zM18 7l-3 7a3 3 0 0 0 6 0z',
    alert: 'M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0M12 9v4M12 17h.01',
    logout: 'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9',
    filter: 'M22 3H2l8 9.5V19l4 2v-8.5z',
    download: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3',
    printer: 'M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z',
    x: 'M18 6 6 18M6 6l12 12',
    more: 'M12 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2M19 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2M5 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2',
    swap: 'M16 3l4 4-4 4M20 7H4M8 21l-4-4 4-4M4 17h16',
    history: 'M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5M12 7v5l4 2',
    chart: 'M3 3v18h18M18 17V9M13 17V5M8 17v-3',
    lock: 'M5 11h14v10H5zM8 11V7a4 4 0 0 1 8 0v4',
    user: 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8',
    grip: 'M9 5h.01M9 12h.01M9 19h.01M15 5h.01M15 12h.01M15 19h.01',
    ruler: 'M21.3 8.7 8.7 21.3a1 1 0 0 1-1.4 0l-4.6-4.6a1 1 0 0 1 0-1.4L15.3 2.7a1 1 0 0 1 1.4 0l4.6 4.6a1 1 0 0 1 0 1.4M7.5 10.5l2 2M10.5 7.5l2 2M13.5 4.5l2 2M4.5 13.5l2 2',
    share: 'M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8M16 6l-4-4-4 4M12 2v13',
    smartphone: 'M7 2h10a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2M12 18h.01',
  };
  const I = (n, c = 'w-5 h-5') => `<svg class="${c} shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="${P[n]}"/></svg>`;

  // ---------- composants ----------
  const avatar = (m, size = 'w-10 h-10 text-sm', ring = '') => {
    const c = YT.roles[m.roles[0]].color;
    return `<span class="${size} ${ring} rounded-full grid place-items-center font-bold text-white shrink-0" style="background:${c}">${m.initials}</span>`;
  };
  const rolePill = (code, star = false) => {
    const r = YT.roles[code];
    return `<span class="chip" style="background:${r.color}1f;color:${shade(r.color)}">${star ? I('star', 'w-3 h-3 fill-current') : `<i class="w-1.5 h-1.5 rounded-full" style="background:${r.color}"></i>`}${r.label}</span>`;
  };
  function shade(hex) { // assombrit pour le texte des pastilles
    const n = parseInt(hex.slice(1), 16); const f = .62;
    return `rgb(${Math.round((n >> 16) * f)},${Math.round(((n >> 8) & 255) * f)},${Math.round((n & 255) * f)})`;
  }
  const levelPill = l => {
    const map = { debutant: 'bg-slate-100 text-slate-700', intermediaire: 'bg-sky-100 text-sky-800', confirme: 'bg-navy-100 text-navy-800', expert: 'bg-sun-100 text-amber-800' };
    return `<span class="chip ${map[l]}">${YT.levels[l]}</span>`;
  };
  const attPill = st => st ? `<span class="chip" style="background:${YT.attendance[st].bg};color:${shade(YT.attendance[st].color)}">${YT.attendance[st].label}</span>` : `<span class="chip bg-slate-100 text-slate-500">Non pointé</span>`;
  const bar = (v, color = '#10B981') => `<div class="h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-full rounded-full" style="width:${v}%;background:${color}"></div></div>`;
  const rateColor = v => v >= 85 ? '#10B981' : v >= 70 ? '#F5B700' : '#EF4444';
  const sectionTitle = (t, right = '') => `<div class="flex items-center justify-between mb-3"><h3 class="font-bold text-navy-950">${t}</h3>${right}</div>`;
  const syncBadge = () => {
    const pending = YT.syncQueue.length;
    return S.offline
      ? `<a href="#/synchro" class="chip bg-amber-100 text-amber-800 h-8 px-3">${I('wifiOff', 'w-4 h-4')}Hors ligne · ${pending} en attente</a>`
      : `<a href="#/synchro" class="chip bg-emerald-50 text-emerald-700 h-8 px-3">${I('cloudCheck', 'w-4 h-4')}Synchronisé · 06:02</a>`;
  };
  const counts = () => {
    const c = { present: 0, retard: 0, excuse: 0, absent: 0, none: 0 };
    YT.members.forEach(m => { const s = S.attendance[m.id]; s ? c[s]++ : c.none++; });
    return c;
  };
  const presentIds = () => YT.members.filter(m => ['present', 'retard'].includes(S.attendance[m.id])).map(m => m.id);
  const kpi = (label, value, sub, icon, tone = 'navy') => {
    const tones = { navy: 'bg-navy-50 text-navy-700', sun: 'bg-sun-100 text-amber-700', green: 'bg-emerald-50 text-emerald-700', sky: 'bg-sky-50 text-sky-700' };
    return `<div class="card p-4 lg:p-5"><div class="flex items-start justify-between"><p class="text-[13px] font-semibold muted">${label}</p><span class="w-9 h-9 rounded-xl grid place-items-center ${tones[tone]}">${I(icon, 'w-[18px] h-[18px]')}</span></div>
      <p class="mt-2 text-2xl lg:text-[28px] font-extrabold tracking-tight">${value}</p><p class="text-xs muted mt-0.5">${sub}</p></div>`;
  };
  const field = (label, input, hint = '') => `<div><label class="label">${label}</label>${input}${hint ? `<p class="text-xs muted mt-1.5">${hint}</p>` : ''}</div>`;
  const inp = (v = '', ph = '', type = 'text') => `<input class="input" type="${type}" value="${v}" placeholder="${ph}">`;
  const sel = (opts, v) => `<select class="input appearance-none">${opts.map(o => `<option ${o === v ? 'selected' : ''}>${o}</option>`).join('')}</select>`;

  // ---------- coquille ----------
  const NAV = [
    ['#/', 'home', 'Tableau de bord'],
    ['#/sorties', 'calendar', 'Sorties'],
    ['#/appel', 'checkSq', 'Présences du jour'],
    ['#/equipage', 'boat', 'Plan d’équipage'],
    ['#/membres', 'users', 'Membres'],
    ['#/yoles', 'boat', 'Yoles'],
    ['#/regates', 'trophy', 'Régates'],
    ['#/historique', 'chart', 'Historique & stats'],
  ];
  const NAV2 = [['#/synchro', 'refresh', 'Synchronisation'], ['#/parametres', 'settings', 'Paramètres']];
  const MOBILE_NAV = [['#/', 'home', 'Accueil'], ['#/sorties', 'calendar', 'Sorties'], ['#/appel', 'checkSq', 'Appel'], ['#/equipage', 'boat', 'Équipage'], ['#/plus', 'more', 'Plus']];

  function logo(dark = true) {
    return `<div class="flex items-center gap-2.5"><span class="w-9 h-9 rounded-xl bg-sun-400 grid place-items-center text-navy-950">${I('boat', 'w-5 h-5')}</span>
      <div class="leading-tight"><p class="font-extrabold ${dark ? 'text-white' : 'text-navy-950'} tracking-tight">YoleTeam</p><p class="text-[11px] ${dark ? 'text-navy-200' : 'muted'} font-medium">${YT.association.name}</p></div></div>`;
  }

  function shell(page, route) {
    const active = h => (h === '#/' ? route === '#/' : route.startsWith(h));
    const offlineBar = S.offline ? `<div class="bg-amber-400 text-navy-950 text-[13px] font-semibold px-4 py-2 flex items-center justify-center gap-2">${I('wifiOff', 'w-4 h-4')}Mode hors ligne — vos modifications sont enregistrées sur cet appareil et seront synchronisées au retour du réseau.</div>` : '';
    return `
    <div class="min-h-screen lg:flex">
      <aside class="hidden lg:block w-[264px] shrink-0 bg-navy-900 text-white"><div class="sticky top-0 h-screen flex flex-col p-4">
        <div class="px-2 py-2">${logo()}</div>
        <nav class="mt-6 space-y-1 flex-1">${NAV.map(([h, i, l]) => `<a href="${h}" class="nav-link ${active(h) ? 'on' : ''}">${I(i, 'w-[18px] h-[18px]')}${l}${h === '#/appel' ? `<span class="ml-auto chip bg-sun-400 text-navy-950 px-2 py-0.5">${counts().none}</span>` : ''}</a>`).join('')}
          <div class="h-px bg-white/10 my-4"></div>
          ${NAV2.map(([h, i, l]) => `<a href="${h}" class="nav-link ${active(h) ? 'on' : ''}">${I(i, 'w-[18px] h-[18px]')}${l}${h === '#/synchro' && S.offline ? `<span class="ml-auto w-2 h-2 rounded-full bg-amber-400"></span>` : ''}</a>`).join('')}
        </nav>
        <div class="rounded-2xl bg-white/5 p-3 flex items-center gap-3">
          <span class="w-10 h-10 rounded-full bg-sun-400 text-navy-950 grid place-items-center font-bold">RC</span>
          <div class="text-sm leading-tight flex-1"><p class="font-semibold">Rodrigue Céleste</p><p class="text-navy-200 text-xs">Patron · Entraîneur</p></div>
          <a href="#/login" class="text-navy-200 hover:text-white" title="Déconnexion">${I('logout', 'w-[18px] h-[18px]')}</a>
        </div>
      </div></aside>
      <div class="flex-1 min-w-0 flex flex-col">
        ${offlineBar}
        <header class="sticky top-0 z-30 bg-white/90 backdrop-blur border-b border-slate-200/80">
          <div class="h-16 px-4 lg:px-8 flex items-center gap-3">
            ${page.back ? `<a href="${page.back}" class="lg:hidden -ml-1 w-10 h-10 grid place-items-center rounded-xl hover:bg-slate-100">${I('left')}</a>` : `<span class="lg:hidden w-9 h-9 rounded-xl bg-navy-900 text-sun-400 grid place-items-center">${I('boat', 'w-5 h-5')}</span>`}
            <div class="min-w-0 flex-1">
              ${page.crumb ? `<p class="hidden lg:block text-xs muted font-medium">${page.crumb}</p>` : ''}
              <h1 class="font-extrabold tracking-tight text-navy-950 truncate text-[17px] lg:text-xl">${page.title}</h1>
            </div>
            <div class="hidden md:block w-72"><label class="relative block"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">${I('search', 'w-4 h-4')}</span><input class="input h-10 pl-9 bg-slate-50" placeholder="Rechercher un membre, une sortie…"></label></div>
            <span class="hidden sm:inline-flex">${syncBadge()}</span>
            <a href="#/synchro" class="sm:hidden w-10 h-10 grid place-items-center rounded-xl ${S.offline ? 'bg-amber-100 text-amber-700' : 'text-emerald-600'}">${I(S.offline ? 'wifiOff' : 'cloudCheck')}</a>
            ${page.actions ? `<div class="hidden lg:flex items-center gap-2">${page.actions}</div>` : ''}
          </div>
        </header>
        <main class="flex-1 px-4 lg:px-8 py-5 lg:py-7 ${page.noBottomNav ? '' : 'pb-28'} lg:pb-10">${page.body}</main>
        ${page.sticky ? `<div class="lg:hidden fixed inset-x-0 bottom-[68px] z-30 px-4 pb-3 pt-3 bg-gradient-to-t from-sea-50 via-sea-50 to-transparent">${page.sticky}</div>` : ''}
        <nav class="lg:hidden fixed inset-x-0 bottom-0 z-40 bg-white border-t border-slate-200 safe-b">
          <div class="grid grid-cols-5 h-[60px]">${MOBILE_NAV.map(([h, i, l]) => {
            const on = h === '#/plus' ? ['#/membres', '#/yoles', '#/regates', '#/historique', '#/parametres', '#/synchro', '#/plus'].some(x => route.startsWith(x)) : active(h);
            return `<a href="${h}" class="flex flex-col items-center justify-center gap-0.5 text-[11px] font-semibold ${on ? 'text-navy-900' : 'text-slate-400'}">
              <span class="relative ${on ? 'bg-sun-100 text-navy-900' : ''} w-12 h-7 rounded-full grid place-items-center">${I(i, 'w-5 h-5')}${h === '#/appel' && counts().none ? `<i class="absolute -top-0.5 right-1.5 w-2 h-2 rounded-full bg-red-500"></i>` : ''}</span>${l}</a>`;
          }).join('')}</div>
        </nav>
      </div>
    </div>
    ${S.modal ? modal(S.modal) : ''}`;
  }

  // ---------- écrans ----------
  const R = {};

  R['#/login'] = () => ({
    raw: `
    <div class="min-h-screen grid lg:grid-cols-2">
      <div class="relative hidden lg:flex flex-col justify-between bg-navy-900 text-white p-12 overflow-hidden">
        ${logo()}
        <div class="absolute -right-24 top-16 w-[520px] h-[900px] rotate-[18deg] opacity-90">${YT.yoleSVG({ config: { sails: 2, bwa: 4 }, plan: YT.demoPlan, placement: YT.demoPlacement, wind: false, labels: false })}</div>
        <div class="relative max-w-md">
          <p class="chip bg-sun-400 text-navy-950 mb-4">Saison 2026</p>
          <h2 class="text-4xl font-extrabold tracking-tight leading-tight">L’appel et le plan d’équipage,<br>même sans réseau au bord de l’eau.</h2>
          <p class="mt-4 text-navy-200">Présences du jour, positions sur les bwa dressés, régates : tout votre équipage dans la poche du patron.</p>
        </div>
        <p class="relative text-xs text-navy-300">© 2026 ${YT.association.name} · ${YT.association.city}</p>
      </div>
      <div class="flex flex-col justify-center px-6 py-10 sm:px-12 bg-white lg:bg-sea-50">
        <div class="w-full max-w-sm mx-auto">
          <div class="lg:hidden mb-10 flex justify-center"><div class="bg-navy-900 rounded-2xl p-3 pr-5">${logo()}</div></div>
          <h1 class="text-2xl font-extrabold tracking-tight">Connexion</h1>
          <p class="muted text-sm mt-1">Espace réservé au bureau et aux patrons.</p>
          <form class="mt-8 space-y-4" onsubmit="location.hash='#/';return false">
            ${field('Adresse e-mail', inp('patron@yoleteam.test', 'vous@exemple.fr', 'email'))}
            ${field('Mot de passe', inp('password', '', 'password'))}
            <div class="flex items-center justify-between text-sm"><label class="flex items-center gap-2"><input type="checkbox" checked class="w-4 h-4 accent-navy-900">Rester connecté</label><a class="font-semibold text-navy-700" href="#/login">Mot de passe oublié ?</a></div>
            <button class="btn-primary w-full h-12">Se connecter</button>
          </form>
          <div class="mt-6 rounded-xl bg-navy-50 text-navy-800 text-[13px] p-3 flex gap-2">${I('smartphone', 'w-5 h-5')}<p>Installez l’application sur votre téléphone : après la première connexion, l’appel et le plan d’équipage fonctionnent <b>hors ligne</b>.</p></div>
        </div>
      </div>
    </div>`,
  });

  R['#/'] = () => {
    const c = counts(); const total = YT.members.length; const pct = Math.round((c.present + c.retard) / total * 100);
    const b1 = YT.balance({ sails: 2, bwa: 4 }, S.plan);
    const recent = [78, 84, 71, 88, 80, 92, 86, pct];
    return {
      title: 'Bonjour Rodrigue 👋', crumb: 'Mercredi 23 septembre 2026',
      actions: `<a href="#/sorties/nouvelle" class="btn-ghost btn-sm">${I('plus', 'w-4 h-4')}Nouvelle sortie</a>`,
      body: `
      <div class="grid gap-5 xl:grid-cols-3">
        <section class="xl:col-span-2 rounded-3xl bg-navy-900 text-white p-5 lg:p-7 relative overflow-hidden">
          <div class="absolute -right-10 -bottom-16 w-72 h-72 rounded-full bg-sun-400/10 hidden sm:block"></div><div class="absolute right-16 -top-20 w-48 h-48 rounded-full bg-white/5 hidden sm:block"></div>
          <div class="relative">
            <div class="flex items-center gap-2"><span class="chip bg-emerald-400/20 text-emerald-300"><i class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></i>En cours</span><span class="text-navy-200 text-sm">Entraînement · 06:00 – 08:30</span></div>
            <h2 class="text-2xl lg:text-3xl font-extrabold tracking-tight mt-3">Entraînement du matin</h2>
            <p class="text-navy-200 text-sm mt-1 flex items-center gap-1.5">${I('pin', 'w-4 h-4')}Baie du François · ${I('wind', 'w-4 h-4')} E-NE 15 nds</p>
            <div class="mt-5 grid grid-cols-4 gap-2 max-w-lg">
              ${['present', 'retard', 'excuse', 'absent'].map(k => `<div class="rounded-2xl bg-white/5 p-3"><p class="text-2xl font-extrabold" style="color:${YT.attendance[k].color}">${c[k]}</p><p class="text-[11px] text-navy-200 font-semibold">${YT.attendance[k].label}${k === 'present' || k === 'retard' ? '' : 's'}</p></div>`).join('')}
            </div>
            <div class="mt-4 max-w-lg"><div class="flex justify-between text-xs text-navy-200 mb-1.5"><span>${c.present + c.retard} / ${total} membres sur place</span><span>${c.none} non pointés</span></div><div class="h-2 rounded-full bg-white/10"><div class="h-full rounded-full bg-sun-400" style="width:${pct}%"></div></div></div>
            <div class="mt-6 flex flex-wrap gap-2"><a href="#/appel" class="btn-sun">${I('checkSq', 'w-4 h-4')}Faire l’appel</a><a href="#/equipage" class="btn bg-white/10 text-white hover:bg-white/15">${I('boat', 'w-4 h-4')}Plan d’équipage</a></div>
          </div>
        </section>
        <section class="card p-5">
          ${sectionTitle('Équipages du jour', '<a href="#/sorties/101" class="text-sm font-semibold text-navy-700">Voir</a>')}
          ${[['Ti-Bwa', '#E11D48', b1.filled, 15, 'Validé', 'bg-emerald-100 text-emerald-800'], ['La Cagou', '#0EA5E9', 9, 15, 'Brouillon', 'bg-amber-100 text-amber-800']].map(([n, col, f, t, st, cls]) => `
            <a href="#/equipage" class="flex items-center gap-3 p-3 -mx-2 rounded-xl hover:bg-slate-50">
              <span class="w-11 h-11 rounded-xl grid place-items-center text-white" style="background:${col}">${I('boat')}</span>
              <div class="flex-1 min-w-0"><div class="flex items-center gap-2"><p class="font-bold">${n}</p><span class="chip ${cls}">${st}</span></div><p class="text-xs muted">2 voiles · ${f}/${t} postes</p><div class="mt-1.5">${bar(f / t * 100, col)}</div></div>
              ${I('right', 'w-4 h-4 text-slate-400')}</a>`).join('')}
          <div class="mt-3 rounded-xl bg-amber-50 text-amber-800 text-[13px] p-3 flex gap-2">${I('alert', 'w-4 h-4 mt-0.5')}<p><b>Zetwal</b> en réparation — non disponible pour les sorties.</p></div>
        </section>
      </div>
      <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 lg:gap-5 mt-5">
        ${kpi('Membres actifs', '24', '3 jeunes intégrés cette saison', 'users')}
        ${kpi('Présence (30 j)', '82 %', '+4 pts vs août', 'chart', 'green')}
        ${kpi('Sorties ce mois', '9', 'dont 2 régates', 'calendar', 'sky')}
        ${kpi('Prochaine régate', 'J-11', 'Sainte-Anne · 4 oct.', 'trophy', 'sun')}
      </div>
      <div class="grid gap-5 xl:grid-cols-3 mt-5">
        <section class="card p-5 xl:col-span-2">
          ${sectionTitle('Prochaines sorties', '<a href="#/sorties" class="text-sm font-semibold text-navy-700">Toutes les sorties</a>')}
          <div class="divide-y divide-slate-100">${YT.outings.filter(o => o.status === 'planifiee').map(outingRow).join('')}</div>
        </section>
        <section class="card p-5">
          ${sectionTitle('Présence — 8 dernières sorties')}
          <div class="flex items-end gap-2 h-36 mt-2">${recent.map((v, i) => `<div class="flex-1 h-full flex flex-col justify-end items-center gap-1.5"><span class="text-[10px] font-bold muted">${v}</span><div class="w-full rounded-t-lg ${i === recent.length - 1 ? 'bg-sun-400' : 'bg-navy-200'}" style="height:${v}%"></div></div>`).join('')}</div>
          <div class="flex justify-between text-[10px] muted mt-2 font-semibold"><span>29 août</span><span>Aujourd’hui</span></div>
        </section>
      </div>`,
    };
  };

  function outingRow(o) {
    const [d, n, m] = o.date.split(' ');
    return `<a href="#/sorties/${o.id}" class="flex items-center gap-4 py-3 group">
      <div class="w-14 text-center rounded-xl ${o.today ? 'bg-sun-400 text-navy-950' : 'bg-navy-50 text-navy-800'} py-1.5"><p class="text-[10px] font-bold uppercase">${d.replace('.', '')}</p><p class="text-lg font-extrabold leading-none">${n}</p><p class="text-[10px] font-semibold">${m}</p></div>
      <div class="flex-1 min-w-0"><div class="flex items-center gap-2 flex-wrap"><p class="font-bold truncate group-hover:text-navy-600">${o.title}</p><span class="chip ${o.type === 'regate' ? 'bg-sun-100 text-amber-800' : 'bg-slate-100 text-slate-700'}">${YT.outingTypes[o.type]}</span></div>
      <p class="text-xs muted mt-0.5 flex items-center gap-3 flex-wrap"><span class="inline-flex items-center gap-1">${I('clock', 'w-3.5 h-3.5')}${o.time}</span><span class="inline-flex items-center gap-1">${I('pin', 'w-3.5 h-3.5')}${o.place}</span><span class="inline-flex items-center gap-1">${I('boat', 'w-3.5 h-3.5')}${o.boats.map(id => YT.boats.find(b => b.id === id).name).join(', ')}</span></p></div>
      <span class="chip ${YT.outingStatus[o.status].cls} hidden sm:inline-flex">${YT.outingStatus[o.status].label}</span>${I('right', 'w-4 h-4 text-slate-400')}</a>`;
  }

  // ----- Présences du jour (appel) -----
  R['#/appel'] = () => {
    const c = counts();
    const list = YT.members.filter(m => !S.search || m.name.toLowerCase().includes(S.search.toLowerCase()));
    const btn = (m, k) => {
      const on = S.attendance[m.id] === k; const a = YT.attendance[k];
      return `<button data-att="${m.id}:${k}" class="h-10 w-10 lg:w-auto lg:px-3 rounded-xl text-[13px] font-bold border transition ${on ? 'text-white border-transparent shadow-sm' : 'bg-white border-slate-200 text-slate-500 hover:border-slate-300'}" style="${on ? `background:${a.color}` : ''}" title="${a.label}"><span class="lg:hidden">${a.label[0]}</span><span class="hidden lg:inline">${a.label}</span></button>`;
    };
    return {
      title: 'Présences du jour', crumb: 'Mer. 23 sept. · Entraînement du matin', back: '#/',
      actions: `<button data-action="all-present" class="btn-ghost btn-sm">${I('check', 'w-4 h-4')}Tous présents</button><button data-action="save-att" class="btn-primary btn-sm">Enregistrer</button>`,
      sticky: `<div class="flex gap-2"><button data-action="save-att" class="btn-primary flex-1 h-12">${I('check', 'w-4 h-4')}Enregistrer</button><a href="#/equipage" class="btn-sun h-12">Équipage ${I('right', 'w-4 h-4')}</a></div>`,
      body: `
      <div class="card p-4 lg:p-5 flex flex-col lg:flex-row lg:items-center gap-4">
        <div class="flex items-center gap-3 flex-1"><span class="w-12 h-12 rounded-2xl bg-navy-900 text-sun-400 grid place-items-center">${I('calendar')}</span>
          <div><p class="font-extrabold">Entraînement du matin</p><p class="text-xs muted">06:00 – 08:30 · Baie du François · Ti-Bwa, La Cagou</p></div></div>
        <div class="grid grid-cols-5 gap-2 lg:w-[520px]">
          ${['present', 'retard', 'excuse', 'absent'].map(k => `<div class="rounded-xl p-2.5 text-center" style="background:${YT.attendance[k].bg}"><p class="text-xl font-extrabold" style="color:${shade(YT.attendance[k].color)}">${c[k]}</p><p class="text-[10px] font-bold uppercase tracking-wide" style="color:${shade(YT.attendance[k].color)}">${YT.attendance[k].label}</p></div>`).join('')}
          <div class="rounded-xl p-2.5 text-center bg-slate-100"><p class="text-xl font-extrabold text-slate-600">${c.none}</p><p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">À pointer</p></div>
        </div>
      </div>
      <div class="flex gap-2 mt-4">
        <label class="relative flex-1"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">${I('search', 'w-4 h-4')}</span><input data-input="search" value="${S.search}" class="input pl-9" placeholder="Rechercher un membre…"></label>
        <button data-action="all-present" class="btn-ghost lg:hidden px-3">${I('check', 'w-4 h-4')}Tous</button>
      </div>
      <div class="card mt-4 divide-y divide-slate-100 overflow-hidden">
        ${list.map(m => `
          <div class="flex items-center gap-2.5 lg:gap-3 px-3 lg:px-5 py-3 ${!S.attendance[m.id] ? 'bg-amber-50/40' : ''}">
            ${avatar(m)}
            <div class="flex-1 min-w-0"><p class="font-bold truncate"><span class="lg:hidden">${m.short}</span><span class="hidden lg:inline">${m.name}</span></p><p class="text-xs muted truncate">${YT.roles[m.roles[0]].label}<span class="hidden sm:inline"> · ${m.kg} kg</span>${S.attendance[m.id] === 'retard' ? ' · <span class="text-amber-700 font-semibold">arrivé 06:14</span>' : ''}</p></div>
            <div class="flex gap-1 lg:gap-1.5">${['present', 'retard', 'excuse', 'absent'].map(k => btn(m, k)).join('')}</div>
          </div>`).join('')}
      </div>
      <p class="text-xs muted text-center mt-4 flex items-center justify-center gap-1.5">${I('lock', 'w-3.5 h-3.5')}Enregistré localement à chaque clic — synchronisation automatique au retour du réseau.</p>`,
    };
  };

  // ----- Plan d'équipage -----
  R['#/equipage'] = () => {
    const cfg = config(); const b = boat();
    const pos = YT.buildConfig(cfg.sails, cfg.bwa);
    const bal = YT.balance(cfg, S.plan);
    const assigned = new Set(Object.values(S.plan));
    const present = presentIds();
    const selPos = S.selected && pos.find(p => p.code === S.selected);
    const selMember = selPos && S.plan[selPos.code] && YT.member(S.plan[selPos.code]);
    const filterRoles = ['tous', 'patron', 'aide_patron', 'premiere_corde', 'ecoute', 'dresseur', 'ecopeur'];
    let avail = YT.members.filter(m => present.includes(m.id));
    if (S.roleFilter !== 'tous') avail = avail.filter(m => m.roles.includes(S.roleFilter) || (S.roleFilter === 'premiere_corde' && m.roles.includes('deuxieme_corde')));
    avail = [...avail].sort((a, z) => (assigned.has(a.id) - assigned.has(z.id)) || (selPos ? z.roles.includes(selPos.role) - a.roles.includes(selPos.role) : 0));
    const diff = bal.diff; const warn = Math.abs(diff) > 12;
    const pct = Math.round(bal.filled / pos.length * 100);

    const memberCard = m => {
      const placed = assigned.has(m.id);
      const fits = selPos && m.roles.includes(selPos.role);
      return `<div draggable="${!placed}" data-member="${m.id}" class="group flex items-center gap-3 p-2.5 rounded-xl border ${placed ? 'border-transparent bg-slate-50 opacity-55' : fits ? 'border-sun-400 bg-sun-100/40 cursor-grab' : 'border-slate-200 bg-white hover:border-navy-300 cursor-grab'}">
        <span class="text-slate-300 hidden lg:block">${I('grip', 'w-4 h-4')}</span>${avatar(m, 'w-9 h-9 text-xs')}
        <div class="flex-1 min-w-0"><p class="text-sm font-bold truncate">${m.short}${S.attendance[m.id] === 'retard' ? ' <span class="text-amber-600">◷</span>' : ''}</p><p class="text-[11px] muted truncate">${m.roles.map(r => YT.roles[r].label).join(' · ')}</p></div>
        <div class="text-right"><p class="text-xs font-bold">${m.kg} kg</p><p class="text-[10px] muted">${placed ? 'placé' : YT.levels[m.level]}</p></div></div>`;
    };

    const inspector = selPos ? `
      <div class="card p-4 border-sun-400/60">
        <div class="flex items-start justify-between"><div><p class="text-[11px] font-bold uppercase tracking-wider muted">Poste sélectionné</p><p class="font-extrabold text-lg">${selPos.label}</p>
          <p class="text-xs muted">${YT.roles[selPos.role].zone}${selPos.bwa ? ` · Bwa n°${selPos.bwa} · ${selPos.side === 'babord' ? 'Bâbord' : 'Tribord'}` : ''}</p></div>
          <button data-action="unselect" class="w-8 h-8 grid place-items-center rounded-lg hover:bg-slate-100">${I('x', 'w-4 h-4')}</button></div>
        ${selMember ? `
          <div class="mt-3 flex items-center gap-3 p-3 rounded-xl bg-slate-50">${avatar(selMember, 'w-11 h-11')}<div class="flex-1"><p class="font-bold">${selMember.name}</p><p class="text-xs muted">${selMember.kg} kg · ${selMember.cm} cm · ${YT.levels[selMember.level]}</p></div></div>
          ${selPos.role === 'dresseur' ? `<p class="label mt-4">Position sur le bwa</p><div class="seg w-full">${['interieur', 'milieu', 'exterieur'].map(k => `<button data-place="${k}" class="flex-1 justify-center ${(S.placement[selPos.code] || 'milieu') === k ? 'on' : ''}">${{ interieur: 'Intérieur', milieu: 'Milieu', exterieur: 'Extérieur' }[k]}</button>`).join('')}</div>
          <p class="text-[11px] muted mt-2">La position des dresseurs s’adapte au vent et à l’équilibre de la yole.</p>` : ''}
          <div class="grid grid-cols-2 gap-2 mt-4"><button data-action="clear-pos" class="btn-ghost btn-sm">${I('trash', 'w-4 h-4')}Retirer</button><button class="btn-ghost btn-sm">${I('swap', 'w-4 h-4')}Échanger</button></div>`
        : `<p class="text-sm muted mt-3">Glissez un membre sur ce poste, ou cliquez sur un membre ci-dessous. Les membres habitués à ce poste sont surlignés.</p>`}
      </div>` : '';

    const balance = `
      <div class="card p-4">
        <div class="flex items-center justify-between"><p class="font-bold flex items-center gap-2">${I('scale', 'w-4 h-4')}Équilibre</p><span class="chip whitespace-nowrap ${warn ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'}">${warn ? 'À surveiller' : 'Équilibré'}</span></div>
        <div class="mt-3 flex justify-between text-xs font-bold"><span>Bâbord · ${bal.bab} kg</span><span>${bal.tri} kg · Tribord</span></div>
        <div class="mt-2">
          <div class="relative h-3 rounded-full bg-slate-100"><div class="absolute top-0 bottom-0 left-1/2 w-px bg-slate-400"></div><div class="absolute -top-1 w-5 h-5 rounded-full border-4 border-white shadow ${warn ? 'bg-amber-500' : 'bg-emerald-500'}" style="left:calc(${50 - Math.max(-45, Math.min(45, diff * 1.5))}% - 10px)"></div></div>
        </div>
        <div class="mt-3 grid grid-cols-3 gap-2 text-center">
          <div class="rounded-lg bg-slate-50 p-2"><p class="text-[10px] muted font-bold uppercase">Avant</p><p class="font-extrabold">${bal.av} kg</p></div>
          <div class="rounded-lg bg-slate-50 p-2"><p class="text-[10px] muted font-bold uppercase">Arrière</p><p class="font-extrabold">${bal.ar} kg</p></div>
          <div class="rounded-lg bg-slate-50 p-2"><p class="text-[10px] muted font-bold uppercase">Total</p><p class="font-extrabold">${bal.total} kg</p></div>
        </div>
        ${warn ? `<p class="mt-3 text-[12px] text-amber-800 bg-amber-50 rounded-lg p-2 flex gap-1.5">${I('alert', 'w-4 h-4')}Écart de ${Math.abs(diff)} kg côté ${diff > 0 ? 'bâbord' : 'tribord'} entre les dresseurs.</p>` : ''}
        <p class="mt-2 text-[11px] muted">Indication basée sur les poids déclarés — ne remplace pas l’œil du patron.</p>
      </div>`;

    const toolbar = `
      <div class="flex flex-wrap items-center gap-2">
        <div class="seg">${YT.boats.map(x => `<button data-boat="${x.id}" class="${x.id === S.boatId ? 'on' : ''} ${x.status !== 'ok' ? 'opacity-50' : ''}"><i class="w-2.5 h-2.5 rounded-full" style="background:${x.color}"></i>${x.name}</button>`).join('')}</div>
        <div class="seg">${b.configs.map(x => `<button data-config="${x.id}" class="${x.id === cfg.id ? 'on' : ''}">${x.name}</button>`).join('')}</div>
        <span class="chip bg-slate-100 text-slate-700 h-9 px-3">${I('wind', 'w-4 h-4')}E-NE · 15 nds</span>
      </div>`;

    return {
      title: 'Plan d’équipage', crumb: 'Mer. 23 sept. · Entraînement du matin', back: '#/sorties/101',
      actions: `<button data-action="reset" class="btn-ghost btn-sm">${I('refresh', 'w-4 h-4')}Réinitialiser</button><a href="#/equipage/valide" class="btn-ghost btn-sm">${I('printer', 'w-4 h-4')}Aperçu</a><button data-action="validate" class="btn-sun btn-sm">${I('check', 'w-4 h-4')}Valider le plan</button>`,
      sticky: S.sheet ? '' : `<div class="flex gap-2"><div class="flex-1 card px-3 flex items-center gap-2 h-12"><span class="text-sm font-bold">${bal.filled}/${pos.length}</span><div class="flex-1">${bar(pct, '#0B2545')}</div><span class="chip ${warn ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'}">${I('scale', 'w-3.5 h-3.5')}${warn ? Math.abs(diff) + ' kg' : 'OK'}</span></div><button data-action="validate" class="btn-sun h-12">Valider</button></div>`,
      body: `
      <div class="flex flex-col lg:flex-row lg:items-center gap-3 justify-between">
        ${toolbar}
        <div class="hidden lg:flex items-center gap-3 text-sm"><span class="font-bold">${bal.filled}/${pos.length} postes</span><div class="w-32">${bar(pct, '#0B2545')}</div><span class="chip bg-amber-100 text-amber-800">Brouillon · v3</span></div>
      </div>
      <div class="mt-4 grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px] xl:grid-cols-[260px_minmax(0,1fr)_340px]">
        <aside class="hidden xl:flex flex-col gap-4">
          ${balance}
          <div class="card p-4">
            <p class="font-bold mb-3">Légende des postes</p>
            <div class="space-y-2">${Object.entries(YT.roles).filter(([k]) => k !== 'deuxieme_corde').map(([k, r]) => `<div class="flex items-center gap-2.5 text-sm"><span class="w-3.5 h-3.5 rounded-full" style="background:${r.color}"></span><span class="font-semibold flex-1">${k === 'premiere_corde' ? '1ère / 2ème corde' : r.label}</span><span class="text-[11px] muted">${r.zone}</span></div>`).join('')}</div>
            <div class="mt-3 pt-3 border-t border-slate-100 text-[12px] muted space-y-1.5"><p class="flex items-center gap-2"><span class="w-4 h-4 rounded-full border-2 border-dashed border-slate-400"></span>Poste à pourvoir</p><p class="flex items-center gap-2"><span class="w-4 h-1.5 rounded bg-[#B7793F]"></span>Bwa dressé (hors coque)</p><p class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-navy-900 ring-2 ring-white"></span>Mât</p></div>
          </div>
        </aside>
        <section class="card p-3 lg:p-4 relative">
          <div class="mx-auto max-w-[440px] aspect-[400/820]" id="yole-canvas">${YT.yoleSVG({ config: cfg, plan: S.plan, placement: S.placement, selected: S.selected, interactive: true, boatColor: b.color })}</div>
          <p class="text-center text-[12px] muted mt-2">Glissez-déposez un membre sur un poste · Touchez un poste pour l’attribuer</p>
        </section>
        <aside class="flex flex-col gap-4">
          ${inspector}
          <div class="xl:hidden">${balance}</div>
          <div class="card p-4 hidden lg:block">
            <div class="flex items-center justify-between mb-3"><p class="font-bold">Membres disponibles</p><span class="chip bg-emerald-100 text-emerald-800">${present.length} présents</span></div>
            <label class="relative block"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">${I('search', 'w-4 h-4')}</span><input class="input h-10 pl-9" placeholder="Rechercher…"></label>
            <div class="flex gap-1.5 overflow-x-auto scrollbar-none mt-3 -mx-1 px-1">${filterRoles.map(f => `<button data-rolefilter="${f}" class="chip whitespace-nowrap ${S.roleFilter === f ? 'bg-navy-900 text-white' : 'bg-slate-100 text-slate-600'}">${f === 'tous' ? 'Tous' : f === 'premiere_corde' ? 'Cordes' : YT.roles[f].label}</button>`).join('')}</div>
            <div class="mt-3 space-y-2 max-h-[560px] overflow-y-auto pr-1">${avail.map(memberCard).join('')}</div>
            <p class="text-[11px] muted mt-3">Seuls les membres pointés présents ou en retard sont proposés. <a class="font-semibold text-navy-700" href="#/appel">Modifier l’appel</a></p>
          </div>
        </aside>
      </div>
      ${S.sheet || (S.selected && innerWidth < 1024) ? mobileSheet(selPos, avail, memberCard) : ''}`,
    };
  };

  function mobileSheet(selPos, avail, memberCard) {
    if (!selPos) return '';
    const cur = S.plan[selPos.code] && YT.member(S.plan[selPos.code]);
    return `<div class="lg:hidden fixed inset-0 z-50 bg-navy-950/40" data-action="unselect"></div>
    <div class="lg:hidden fixed inset-x-0 bottom-0 z-[60] bg-white rounded-t-3xl shadow-2xl max-h-[72vh] flex flex-col safe-b">
      <div class="pt-2.5 pb-3 px-5 border-b border-slate-100"><div class="w-10 h-1.5 rounded-full bg-slate-200 mx-auto mb-3"></div>
        <div class="flex items-center gap-3"><span class="w-10 h-10 rounded-full grid place-items-center text-white font-bold text-xs" style="background:${YT.roles[selPos.role].color}">${YT.roles[selPos.role].short}</span>
        <div class="flex-1"><p class="font-extrabold">${selPos.label}</p><p class="text-xs muted">${cur ? 'Actuellement : ' + cur.name + ' · ' + cur.kg + ' kg' : 'Poste à pourvoir'}</p></div>
        <button data-action="unselect" class="w-9 h-9 grid place-items-center rounded-full bg-slate-100">${I('x', 'w-4 h-4')}</button></div>
        ${selPos.role === 'dresseur' && cur ? `<div class="seg w-full mt-3">${['interieur', 'milieu', 'exterieur'].map(k => `<button data-place="${k}" class="flex-1 justify-center ${(S.placement[selPos.code] || 'milieu') === k ? 'on' : ''}">${{ interieur: 'Intérieur', milieu: 'Milieu', exterieur: 'Extérieur' }[k]}</button>`).join('')}</div>` : ''}
      </div>
      <div class="overflow-y-auto p-4 space-y-2">
        <p class="text-[11px] font-bold uppercase tracking-wider muted">Suggérés pour ce poste</p>
        ${avail.filter(m => m.roles.includes(selPos.role)).map(memberCard).join('') || '<p class="text-sm muted">Aucun présent habitué à ce poste.</p>'}
        <p class="text-[11px] font-bold uppercase tracking-wider muted pt-2">Autres présents</p>
        ${avail.filter(m => !m.roles.includes(selPos.role)).map(memberCard).join('')}
      </div>
      ${cur ? `<div class="p-4 border-t border-slate-100 grid grid-cols-2 gap-2"><button data-action="clear-pos" class="btn-ghost">${I('trash', 'w-4 h-4')}Retirer</button><button data-action="unselect" class="btn-primary">Terminé</button></div>` : ''}
    </div>`;
  }

  R['#/equipage/valide'] = () => {
    const cfg = { sails: 2, bwa: 4 }; const pos = YT.buildConfig(2, 4);
    const groups = [['Arrière', ['pat', 'ap1', 'ap2', 'ecp']], ['Gréement & avant', ['c1', 'c2', 'eco']], ['Dresseurs bâbord', ['db1', 'db2', 'db3', 'db4']], ['Dresseurs tribord', ['dt1', 'dt2', 'dt3', 'dt4']]];
    return {
      title: 'Ti-Bwa · Plan validé', crumb: 'Mer. 23 sept. · Entraînement du matin', back: '#/equipage',
      actions: `<button class="btn-ghost btn-sm">${I('share', 'w-4 h-4')}Partager</button><button class="btn-ghost btn-sm">${I('printer', 'w-4 h-4')}Imprimer / PDF</button><a href="#/equipage" class="btn-primary btn-sm">${I('edit', 'w-4 h-4')}Modifier</a>`,
      body: `
      <div class="card p-4 flex flex-wrap items-center gap-3 bg-emerald-50 border-emerald-200"><span class="w-10 h-10 rounded-xl bg-emerald-500 text-white grid place-items-center">${I('check')}</span>
        <div class="flex-1"><p class="font-bold text-emerald-900">Plan validé par Rodrigue C. à 06:31</p><p class="text-xs text-emerald-800">Version 3 · 15/15 postes · 2 voiles · vent E-NE 15 nds · 1 213 kg à bord</p></div></div>
      <div class="grid gap-5 lg:grid-cols-[440px_1fr] mt-5">
        <div class="card p-3"><div class="aspect-[400/820]">${YT.yoleSVG({ config: cfg, plan: S.plan, placement: S.placement })}</div></div>
        <div class="grid sm:grid-cols-2 gap-4 content-start">
          ${groups.map(([t, codes]) => `<div class="card p-4"><p class="font-bold mb-3">${t}</p><div class="space-y-2.5">${codes.map(c => { const p = pos.find(x => x.code === c); const m = YT.member(S.plan[c]); return `<div class="flex items-center gap-3">${avatar(m, 'w-9 h-9 text-xs')}<div class="flex-1 min-w-0"><p class="text-sm font-bold truncate">${m.name}</p><p class="text-[11px] muted">${p.label}${p.role === 'dresseur' ? ' · ' + (S.placement[c] || 'milieu') : ''}</p></div><span class="text-xs font-bold">${m.kg} kg</span></div>`; }).join('')}</div></div>`).join('')}
          <div class="card p-4 sm:col-span-2"><p class="font-bold mb-2">Historique du plan</p>
            <ol class="text-sm space-y-2">${[['06:31', 'Validé — v3', 'Rodrigue C.'], ['06:22', 'Olivier T. remplace Ludovic A. (excusé) — Dresseur tribord 3', 'Rodrigue C. · hors ligne'], ['06:10', 'Passage en 2 voiles', 'Rodrigue C.'], ['Hier 21:04', 'Brouillon créé depuis l’équipage type', 'Max B.']].map(([t, a, w]) => `<li class="flex gap-3"><span class="w-20 shrink-0 text-xs font-bold muted pt-0.5">${t}</span><div><p class="font-semibold">${a}</p><p class="text-xs muted">${w}</p></div></li>`).join('')}</ol></div>
        </div>
      </div>`,
    };
  };

  // ----- Membres -----
  R['#/membres'] = () => {
    const list = YT.members.filter(m => S.memberTab === 'tous' || m.roles.includes(S.memberTab));
    return {
      title: 'Membres', crumb: `${YT.members.length} membres · saison 2026`,
      actions: `<a href="#/membres/nouveau" class="btn-primary btn-sm">${I('plus', 'w-4 h-4')}Ajouter un membre</a>`,
      sticky: `<a href="#/membres/nouveau" class="btn-primary w-full h-12">${I('plus', 'w-4 h-4')}Ajouter un membre</a>`,
      body: `
      <div class="flex flex-col lg:flex-row gap-3">
        <label class="relative flex-1"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">${I('search', 'w-4 h-4')}</span><input class="input pl-9" placeholder="Nom, surnom, téléphone…"></label>
        <div class="flex gap-2 overflow-x-auto scrollbar-none">${sel(['Tous niveaux', 'Débutant', 'Intermédiaire', 'Confirmé', 'Expert'], 'Tous niveaux').replace('class="input', 'class="input w-44')}${sel(['Actifs', 'Inactifs', 'Tous'], 'Actifs').replace('class="input', 'class="input w-32')}</div>
      </div>
      <div class="flex gap-1.5 overflow-x-auto scrollbar-none mt-3">${['tous', ...Object.keys(YT.roles).filter(k => k !== 'deuxieme_corde')].map(k => `<button data-mtab="${k}" class="chip h-8 px-3 whitespace-nowrap ${S.memberTab === k ? 'bg-navy-900 text-white' : 'bg-white border border-slate-200 text-slate-600'}">${k === 'tous' ? `Tous · ${YT.members.length}` : YT.roles[k].label}</button>`).join('')}</div>
      <div class="card mt-4 overflow-hidden hidden lg:block">
        <table class="w-full"><thead class="bg-slate-50"><tr><th class="th">Membre</th><th class="th">Postes maîtrisés</th><th class="th">Niveau</th><th class="th">Gabarit</th><th class="th w-48">Présence (saison)</th><th class="th">Téléphone</th><th class="th"></th></tr></thead>
        <tbody>${list.map(m => `<tr class="hover:bg-slate-50 cursor-pointer" onclick="location.hash='#/membres/${m.id}'">
          <td class="td"><div class="flex items-center gap-3">${avatar(m, 'w-9 h-9 text-xs')}<div><p class="font-bold">${m.name}</p><p class="text-xs muted">${m.nick ? '« ' + m.nick + ' » · ' : ''}${{ jeune: 'Jeune', senior: 'Senior', veteran: 'Vétéran' }[m.cat]}</p></div></div></td>
          <td class="td"><div class="flex flex-wrap gap-1">${m.roles.map((r, i) => rolePill(r, i === 0)).join('')}</div></td>
          <td class="td">${levelPill(m.level)}</td>
          <td class="td text-sm"><b>${m.kg}</b> kg · ${m.cm} cm</td>
          <td class="td"><div class="flex items-center gap-2"><div class="flex-1">${bar(m.rate, rateColor(m.rate))}</div><span class="text-xs font-bold w-9 text-right">${m.rate}%</span></div></td>
          <td class="td text-sm muted">${m.phone}</td>
          <td class="td text-right text-slate-400">${I('right', 'w-4 h-4')}</td></tr>`).join('')}</tbody></table>
      </div>
      <div class="lg:hidden mt-4 space-y-2">${list.map(m => `<a href="#/membres/${m.id}" class="card p-3 flex items-center gap-3">${avatar(m)}
        <div class="flex-1 min-w-0"><p class="font-bold truncate">${m.name}</p><div class="flex items-center gap-1.5 mt-1">${rolePill(m.roles[0], true)}<span class="text-xs muted">${m.kg} kg</span></div></div>
        <div class="text-right"><p class="text-sm font-extrabold" style="color:${rateColor(m.rate)}">${m.rate}%</p><p class="text-[10px] muted">présence</p></div></a>`).join('')}</div>`,
    };
  };

  R['#/membres/:id'] = id => {
    const m = YT.member(+id) || YT.members[2];
    const hist = ['present', 'present', 'retard', 'present', 'absent', 'present', 'present', 'excuse', 'present', 'present', 'present', 'present'];
    return {
      title: m.name, crumb: 'Membres', back: '#/membres',
      actions: `<a href="#/membres/nouveau" class="btn-ghost btn-sm">${I('edit', 'w-4 h-4')}Modifier</a>`,
      body: `
      <div class="grid gap-5 lg:grid-cols-[340px_1fr]">
        <div class="space-y-5">
          <div class="card p-5 text-center">
            <div class="mx-auto w-fit">${avatar(m, 'w-24 h-24 text-3xl', 'ring-4 ring-sun-400/40')}</div>
            <h2 class="mt-3 text-xl font-extrabold">${m.name}</h2>${m.nick ? `<p class="muted text-sm">« ${m.nick} »</p>` : ''}
            <div class="flex justify-center gap-1.5 mt-3">${levelPill(m.level)}<span class="chip bg-slate-100 text-slate-700">${{ jeune: 'Jeune', senior: 'Senior', veteran: 'Vétéran' }[m.cat]}</span><span class="chip bg-emerald-100 text-emerald-800">Actif</span></div>
            <div class="grid grid-cols-3 gap-2 mt-5">${[['Poids', m.kg + ' kg'], ['Taille', m.cm + ' cm'], ['Présence', m.rate + '%']].map(([l, v]) => `<div class="rounded-xl bg-slate-50 p-2.5"><p class="text-[10px] font-bold uppercase muted">${l}</p><p class="font-extrabold">${v}</p></div>`).join('')}</div>
            <div class="grid grid-cols-2 gap-2 mt-4"><a class="btn-ghost btn-sm" href="tel:">${I('phone', 'w-4 h-4')}Appeler</a><a class="btn-ghost btn-sm" href="#">${I('mail', 'w-4 h-4')}E-mail</a></div>
          </div>
          <div class="card p-5">${sectionTitle('Postes maîtrisés')}
            <div class="space-y-2">${m.roles.map((r, i) => `<div class="flex items-center gap-3 p-2.5 rounded-xl bg-slate-50"><span class="w-3 h-3 rounded-full" style="background:${YT.roles[r].color}"></span><span class="font-semibold flex-1">${YT.roles[r].label}</span>${i === 0 ? `<span class="chip bg-sun-100 text-amber-800">${I('star', 'w-3 h-3 fill-current')}Préféré</span>` : ''}</div>`).join('')}</div></div>
        </div>
        <div class="space-y-5">
          <div class="grid grid-cols-2 xl:grid-cols-4 gap-3">${kpi('Sorties (saison)', '46', 'sur 51 programmées', 'calendar')}${kpi('Retards', '3', 'moyenne 9 min', 'clock', 'sun')}${kpi('Régates', '8', 'Tour des Yoles inclus', 'trophy', 'sky')}${kpi('Série en cours', '7', 'présences d’affilée', 'check', 'green')}</div>
          <div class="card p-5">${sectionTitle('12 dernières sorties', '<a href="#/historique" class="text-sm font-semibold text-navy-700">Historique complet</a>')}
            <div class="flex gap-1.5 flex-wrap">${hist.map(h => `<span class="w-9 h-9 rounded-lg grid place-items-center text-xs font-bold text-white" style="background:${YT.attendance[h].color}" title="${YT.attendance[h].label}">${YT.attendance[h].icon}</span>`).join('')}</div>
            <div class="flex gap-4 mt-3 text-xs muted">${Object.values(YT.attendance).map(a => `<span class="flex items-center gap-1.5"><i class="w-2.5 h-2.5 rounded-sm" style="background:${a.color}"></i>${a.label}</span>`).join('')}</div></div>
          <div class="card overflow-hidden">${`<div class="p-5 pb-0">${sectionTitle('Postes occupés récemment')}</div>`}
            <table class="w-full"><thead class="bg-slate-50"><tr><th class="th">Date</th><th class="th">Sortie</th><th class="th">Yole</th><th class="th">Poste</th></tr></thead><tbody>
            ${[['23 sept.', 'Entraînement du matin', 'Ti-Bwa', 'Dresseur bâbord 1 · extérieur'], ['19 sept.', 'Entraînement vent fort', 'Ti-Bwa', 'Dresseur bâbord 2 · milieu'], ['16 sept.', 'Entraînement du matin', 'Ti-Bwa', 'Dresseur tribord 1 · extérieur'], ['2 août', 'Tour des Yoles — étape 7', 'Ti-Bwa', 'Dresseur bâbord 1 · extérieur']].map(r => `<tr>${r.map((c, i) => `<td class="td ${i === 3 ? 'font-semibold' : ''}">${c}</td>`).join('')}</tr>`).join('')}</tbody></table></div>
          <div class="card p-5">${sectionTitle('Notes du patron')}<p class="text-sm text-slate-600">Très bon premier dresseur par vent fort. Préfère bâbord. Disponible en semaine uniquement le mercredi matin.</p></div>
        </div>
      </div>`,
    };
  };

  R['#/membres/nouveau'] = () => ({
    title: 'Nouveau membre', crumb: 'Membres', back: '#/membres',
    actions: `<a href="#/membres" class="btn-ghost btn-sm">Annuler</a><button data-action="save-member" class="btn-primary btn-sm">Enregistrer</button>`,
    sticky: `<button data-action="save-member" class="btn-primary w-full h-12">Enregistrer le membre</button>`,
    body: `
    <div class="max-w-4xl space-y-5">
      <section class="card p-5 lg:p-6"><h3 class="font-bold mb-4">Identité</h3>
        <div class="flex flex-col sm:flex-row gap-5">
          <div class="shrink-0"><div class="w-28 h-28 rounded-2xl border-2 border-dashed border-slate-300 grid place-items-center text-center text-xs muted bg-slate-50 cursor-pointer hover:border-sun-400">${I('user', 'w-7 h-7 mx-auto text-slate-400')}<span class="block mt-1">Ajouter<br>une photo</span></div></div>
          <div class="grid sm:grid-cols-2 gap-4 flex-1">${field('Prénom *', inp('Mickaël'))}${field('Nom *', inp('Sainte-Rose'))}${field('Surnom', inp('Mika'))}${field('Date de naissance', inp('2004-03-12', '', 'date'))}
            <div class="sm:col-span-2"><label class="label">Genre</label><div class="seg">${['Homme', 'Femme', 'Autre'].map((g, i) => `<button class="${i === 0 ? 'on' : ''}">${g}</button>`).join('')}</div></div></div>
        </div></section>
      <section class="card p-5 lg:p-6"><h3 class="font-bold mb-4">Contact</h3><div class="grid sm:grid-cols-2 gap-4">${field('Téléphone', inp('0696 12 34 56', '', 'tel'))}${field('E-mail', inp('', 'prenom.nom@exemple.fr', 'email'))}</div></section>
      <section class="card p-5 lg:p-6"><h3 class="font-bold mb-1">Gabarit</h3><p class="text-xs muted mb-4">Utilisé uniquement pour l’indicateur d’équilibre du plan d’équipage.</p>
        <div class="grid grid-cols-2 gap-4">${field('Poids (kg)', inp('74', '', 'number'))}${field('Taille (cm)', inp('178', '', 'number'))}</div></section>
      <section class="card p-5 lg:p-6"><h3 class="font-bold mb-4">Profil yoleur</h3>
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="label">Niveau</label><div class="seg flex-wrap">${Object.values(YT.levels).map((l, i) => `<button class="${i === 1 ? 'on' : ''}">${l}</button>`).join('')}</div></div>
          ${field('Catégorie', sel(['Jeune', 'Senior', 'Vétéran'], 'Jeune'))}
        </div>
        <p class="label mt-5">Postes maîtrisés <span class="font-normal muted">— l’étoile indique le poste préféré</span></p>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">${Object.entries(YT.roles).map(([k, r]) => { const on = ['dresseur', 'ecopeur'].includes(k); const pref = k === 'dresseur'; return `<label class="flex items-center gap-3 p-3 rounded-xl border ${on ? 'border-navy-900 bg-navy-50' : 'border-slate-200'} cursor-pointer"><input type="checkbox" ${on ? 'checked' : ''} class="w-4 h-4 accent-navy-900"><span class="w-3 h-3 rounded-full" style="background:${r.color}"></span><span class="font-semibold text-sm flex-1">${r.label}</span><span class="${pref ? 'text-sun-500' : 'text-slate-300'}">${I('star', `w-4 h-4 ${pref ? 'fill-current' : ''}`)}</span></label>`; }).join('')}</div>
      </section>
      <section class="card p-5 lg:p-6"><h3 class="font-bold mb-4">Notes & statut</h3>
        <textarea class="input h-28 py-3" placeholder="Disponibilités, remarques du patron…"></textarea>
        <label class="flex items-center justify-between mt-4 p-3 rounded-xl bg-slate-50"><div><p class="font-semibold text-sm">Membre actif</p><p class="text-xs muted">Les membres inactifs n’apparaissent plus dans l’appel.</p></div><span class="w-11 h-6 rounded-full bg-emerald-500 relative"><i class="absolute right-0.5 top-0.5 w-5 h-5 rounded-full bg-white shadow"></i></span></label>
      </section>
    </div>`,
  });

  // ----- Yoles -----
  R['#/yoles'] = () => ({
    title: 'Yoles', crumb: '3 yoles · flotte de l’association',
    actions: `<button class="btn-primary btn-sm">${I('plus', 'w-4 h-4')}Ajouter une yole</button>`,
    body: `<div class="grid md:grid-cols-2 xl:grid-cols-3 gap-5">${YT.boats.map(b => {
      const def = b.configs.find(c => c.def); const n = YT.buildConfig(def.sails, def.bwa).length;
      return `<a href="#/yoles/${b.id}" class="card overflow-hidden hover:shadow-lg transition group">
        <div class="h-2" style="background:${b.color}"></div>
        <div class="p-5 flex gap-4">
          <div class="w-24 h-44 shrink-0 rounded-xl overflow-hidden">${YT.yoleSVG({ config: def, wind: false, labels: false, compact: true, boatColor: b.color })}</div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between"><h3 class="text-lg font-extrabold">${b.name}</h3>${b.status === 'ok' ? '<span class="chip bg-emerald-100 text-emerald-800">Opérationnelle</span>' : '<span class="chip bg-amber-100 text-amber-800">En réparation</span>'}</div>
            <p class="text-sm muted">${b.sponsor}</p>
            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm"><div><dt class="text-[11px] font-bold uppercase muted">Longueur</dt><dd class="font-bold">${String(b.length).replace('.', ',')} m</dd></div><div><dt class="text-[11px] font-bold uppercase muted">Équipage</dt><dd class="font-bold">${n} postes</dd></div></dl>
            <p class="text-[11px] font-bold uppercase muted mt-4 mb-1.5">Configurations</p>
            <div class="flex flex-wrap gap-1.5">${b.configs.map(c => `<span class="chip ${c.def ? 'bg-navy-900 text-white' : 'bg-slate-100 text-slate-700'}">${c.name} · ${c.bwa} bwa${c.def ? ' · défaut' : ''}</span>`).join('')}</div>
          </div>
        </div></a>`;
    }).join('')}</div>`,
  });

  R['#/yoles/:id'] = id => {
    const b = YT.boats.find(x => x.id === +id) || YT.boats[0];
    const c = b.configs.find(x => x.sails === 2); const pos = YT.buildConfig(c.sails, c.bwa);
    return {
      title: `${b.name} · Configuration`, crumb: 'Yoles', back: '#/yoles',
      actions: `<button class="btn-ghost btn-sm">${I('plus', 'w-4 h-4')}Nouvelle configuration</button><button data-action="toast-saved" class="btn-primary btn-sm">Enregistrer</button>`,
      body: `
      <div class="grid gap-5 lg:grid-cols-[1fr_400px]">
        <div class="space-y-5">
          <div class="card p-5"><div class="flex flex-wrap items-center justify-between gap-3"><div class="seg">${b.configs.map(x => `<button class="${x.sails === 2 ? 'on' : ''}">${x.name}</button>`).join('')}</div><label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" checked class="w-4 h-4 accent-navy-900">Configuration par défaut</label></div>
            <div class="grid sm:grid-cols-3 gap-4 mt-5">
              ${field('Nom', inp(c.name))}
              <div><label class="label">Nombre de voiles</label><div class="seg w-full"><button class="flex-1 justify-center">1</button><button class="flex-1 justify-center on">2</button></div></div>
              <div><label class="label">Bwa dressés par bord</label><div class="flex items-center gap-2"><button class="btn-ghost w-11 px-0">–</button><span class="flex-1 text-center text-xl font-extrabold">${c.bwa}</span><button class="btn-ghost w-11 px-0">+</button></div></div>
            </div>
            <div class="mt-4 grid sm:grid-cols-2 gap-2">
              <label class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 text-sm font-semibold"><input type="checkbox" checked class="w-4 h-4 accent-navy-900">2ème aide-patron (optionnel)</label>
              <label class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 text-sm font-semibold"><input type="checkbox" checked class="w-4 h-4 accent-navy-900">Poste d’écopeur</label>
            </div>
          </div>
          <div class="card overflow-hidden"><div class="p-5 pb-3 flex items-center justify-between"><h3 class="font-bold">Postes (${pos.length})</h3><span class="text-xs muted">Générés automatiquement · réordonnables</span></div>
            <div class="overflow-x-auto"><table class="w-full min-w-[560px]"><thead class="bg-slate-50"><tr><th class="th w-8"></th><th class="th">Poste</th><th class="th">Rôle</th><th class="th">Côté</th><th class="th">Bwa</th><th class="th">Optionnel</th></tr></thead><tbody>
            ${pos.map(p => `<tr><td class="td text-slate-300">${I('grip', 'w-4 h-4')}</td><td class="td font-semibold">${p.label}</td><td class="td">${rolePill(p.role)}</td><td class="td text-sm">${{ babord: 'Bâbord', tribord: 'Tribord', centre: 'Axe' }[p.side]}</td><td class="td text-sm">${p.bwa || '—'}</td><td class="td">${p.optional ? '<span class="chip bg-slate-100 text-slate-600">Oui</span>' : '<span class="text-slate-300">—</span>'}</td></tr>`).join('')}
            </tbody></table></div></div>
        </div>
        <div class="card p-4 lg:sticky lg:top-24 self-start"><p class="font-bold mb-2">Aperçu</p><div class="aspect-[400/820]">${YT.yoleSVG({ config: c, boatColor: b.color })}</div></div>
      </div>`,
    };
  };

  // ----- Sorties -----
  R['#/sorties'] = () => ({
    title: 'Sorties', crumb: 'Entraînements, régates et sorties libres',
    actions: `<a href="#/sorties/nouvelle" class="btn-primary btn-sm">${I('plus', 'w-4 h-4')}Nouvelle sortie</a>`,
    sticky: `<a href="#/sorties/nouvelle" class="btn-primary w-full h-12">${I('plus', 'w-4 h-4')}Nouvelle sortie</a>`,
    body: `
    <div class="card p-4">
      <div class="flex items-center justify-between"><p class="font-bold">Septembre – octobre 2026</p><div class="flex gap-1"><button class="w-9 h-9 grid place-items-center rounded-lg hover:bg-slate-100">${I('left', 'w-4 h-4')}</button><button class="w-9 h-9 grid place-items-center rounded-lg hover:bg-slate-100">${I('right', 'w-4 h-4')}</button></div></div>
      <div class="grid grid-cols-7 gap-1.5 mt-3 text-center">${['L', 'M', 'M', 'J', 'V', 'S', 'D'].map(d => `<p class="text-[11px] font-bold muted">${d}</p>`).join('')}
        ${Array.from({ length: 21 }, (_, i) => { const d = 14 + i; const day = d > 30 ? d - 30 : d; const ev = { 16: 'e', 19: 'e', 23: 'e', 26: 'e', 34: 'r', 37: 's' }[d]; const today = d === 23; return `<div class="aspect-square sm:aspect-auto sm:h-14 rounded-xl ${today ? 'bg-navy-900 text-white' : 'bg-slate-50'} p-1 flex flex-col items-center justify-center gap-1"><span class="text-sm font-bold">${day}</span>${ev ? `<i class="w-1.5 h-1.5 rounded-full ${ev === 'r' ? 'bg-sun-400' : ev === 's' ? 'bg-sky-400' : today ? 'bg-sun-400' : 'bg-navy-500'}"></i>` : ''}</div>`; }).join('')}
      </div>
    </div>
    <div class="flex items-center justify-between mt-5 mb-2"><div class="seg"><button class="on">À venir</button><button>Passées</button><button>Toutes</button></div><button class="btn-ghost btn-sm">${I('filter', 'w-4 h-4')}<span class="hidden sm:inline">Filtrer</span></button></div>
    <div class="card px-4 lg:px-5 divide-y divide-slate-100">${YT.outings.filter(o => o.status !== 'terminee').map(outingRow).join('')}</div>
    <p class="text-[11px] font-bold uppercase muted mt-6 mb-2">Passées</p>
    <div class="card px-4 lg:px-5 divide-y divide-slate-100">${YT.outings.filter(o => o.status === 'terminee').map(outingRow).join('')}</div>`,
  });

  R['#/sorties/nouvelle'] = () => ({
    title: 'Nouvelle sortie', crumb: 'Sorties', back: '#/sorties',
    actions: `<a href="#/sorties" class="btn-ghost btn-sm">Annuler</a><a href="#/sorties/102" class="btn-primary btn-sm">Créer la sortie</a>`,
    sticky: `<a href="#/sorties/102" class="btn-primary w-full h-12">Créer la sortie</a>`,
    body: `
    <div class="max-w-3xl space-y-5">
      <section class="card p-5 lg:p-6"><label class="label">Type de sortie</label>
        <div class="grid grid-cols-3 gap-2">${[['entrainement', 'Entraînement', 'calendar'], ['regate', 'Régate', 'trophy'], ['sortie_libre', 'Sortie libre', 'boat']].map(([k, l, i], n) => `<button class="p-3 rounded-xl border-2 ${n === 0 ? 'border-navy-900 bg-navy-50' : 'border-slate-200'} flex flex-col items-center gap-1.5 text-sm font-bold">${I(i)}${l}</button>`).join('')}</div>
        <div class="grid sm:grid-cols-2 gap-4 mt-5">
          <div class="sm:col-span-2">${field('Titre', inp('Entraînement virements'))}</div>
          ${field('Date', inp('2026-09-26', '', 'date'))}
          <div class="grid grid-cols-2 gap-2">${field('Début', inp('07:00', '', 'time'))}${field('Fin', inp('10:00', '', 'time'))}</div>
          <div class="sm:col-span-2">${field('Lieu', inp('Baie du François'))}</div>
        </div></section>
      <section class="card p-5 lg:p-6"><h3 class="font-bold mb-1">Yoles engagées</h3><p class="text-xs muted mb-4">Un plan d’équipage sera créé pour chaque yole cochée.</p>
        <div class="space-y-2">${YT.boats.map((b, i) => `<div class="flex items-center gap-3 p-3 rounded-xl border ${i < 2 ? 'border-navy-900 bg-navy-50/50' : 'border-slate-200 opacity-60'}"><input type="checkbox" ${i < 2 ? 'checked' : ''} ${b.status !== 'ok' ? 'disabled' : ''} class="w-4 h-4 accent-navy-900"><span class="w-3 h-8 rounded" style="background:${b.color}"></span><div class="flex-1"><p class="font-bold">${b.name}</p><p class="text-xs muted">${b.status === 'ok' ? b.sponsor : 'En réparation — indisponible'}</p></div>${b.status === 'ok' ? sel(b.configs.map(c => c.name), '2 voiles').replace('class="input', 'class="input w-32 h-9') : ''}</div>`).join('')}</div></section>
      <section class="card p-5 lg:p-6 grid sm:grid-cols-2 gap-4">${field('Rattacher à une régate', sel(['— Aucune —', 'Régate de Sainte-Anne', 'Championnat FYRM – manche 3'], '— Aucune —'))}${field('Partir d’un équipage type', sel(['Aucun', 'Équipage A — vent fort', 'Équipage jeunes'], 'Équipage A — vent fort'))}
        <div class="sm:col-span-2">${field('Consignes', '<textarea class="input h-24 py-3" placeholder="Objectifs de la séance, matériel…">Travail des virements de bord, rotation des dresseurs.</textarea>')}</div></section>
    </div>`,
  });

  R['#/sorties/:id'] = () => {
    const c = counts();
    return {
      title: 'Entraînement du matin', crumb: 'Sorties · Mer. 23 sept. 2026', back: '#/sorties',
      actions: `<button class="btn-ghost btn-sm">${I('edit', 'w-4 h-4')}Modifier</button><button class="btn-ghost btn-sm text-red-600">Annuler la sortie</button>`,
      body: `
      <div class="card p-5 flex flex-wrap items-center gap-x-6 gap-y-3">
        <span class="chip bg-emerald-100 text-emerald-800">En cours</span>
        ${[['calendar', 'Mer. 23 sept.'], ['clock', '06:00 – 08:30'], ['pin', 'Baie du François'], ['wind', 'E-NE 15 nds'], ['user', 'Patron : Rodrigue C.']].map(([i, t]) => `<span class="text-sm font-semibold flex items-center gap-1.5 text-slate-700">${I(i, 'w-4 h-4 text-slate-400')}${t}</span>`).join('')}
      </div>
      <div class="card p-5 mt-5"><p class="text-[11px] font-bold uppercase muted mb-4">Déroulé avant la sortie</p>
        <ol class="grid sm:grid-cols-3 gap-3">${[['1', 'Présences', `${c.present + c.retard} présents · ${c.none} à pointer`, '#/appel', 'current'], ['2', 'Constitution des équipages', 'Ti-Bwa 15/15 · La Cagou 9/15', '#/equipage', 'todo'], ['3', 'Validation', '1 plan sur 2 validé', '#/equipage/valide', 'todo']].map(([n, t, s, h, st]) => `<li><a href="${h}" class="flex items-center gap-3 p-4 rounded-2xl ${st === 'current' ? 'bg-sun-100 ring-2 ring-sun-400' : 'bg-slate-50 hover:bg-slate-100'}"><span class="w-9 h-9 rounded-full grid place-items-center font-extrabold ${st === 'current' ? 'bg-sun-400 text-navy-950' : 'bg-white text-navy-900 border border-slate-200'}">${n}</span><div class="flex-1"><p class="font-bold">${t}</p><p class="text-xs muted">${s}</p></div>${I('right', 'w-4 h-4 text-slate-400')}</a></li>`).join('')}</ol></div>
      <div class="grid gap-5 lg:grid-cols-2 mt-5">
        ${[[YT.boats[0], 15, 'Validé', 'bg-emerald-100 text-emerald-800', S.plan], [YT.boats[1], 9, 'Brouillon', 'bg-amber-100 text-amber-800', { pat: 22, ap1: 15, c1: 9, db1: 13, dt1: 23, db2: 7, eco: 5, ecp: 19, dt2: 24 }]].map(([b, f, st, cls, plan]) => `
          <a href="#/equipage" class="card p-5 flex gap-5 hover:shadow-lg transition"><div class="w-28 h-56 shrink-0">${YT.yoleSVG({ config: { sails: 2, bwa: 4 }, plan, placement: S.placement, wind: false, labels: false, compact: true, boatColor: b.color })}</div>
          <div class="flex-1"><div class="flex items-center justify-between"><h3 class="text-lg font-extrabold">${b.name}</h3><span class="chip ${cls}">${st}</span></div><p class="text-sm muted">2 voiles · 4 bwa par bord</p>
          <div class="mt-4">${bar(f / 15 * 100, b.color)}</div><p class="text-xs font-bold mt-1.5">${f}/15 postes pourvus</p>
          <div class="flex -space-x-2 mt-4">${Object.values(plan).slice(0, 7).map(id => avatar(YT.member(id), 'w-8 h-8 text-[10px]', 'ring-2 ring-white')).join('')}<span class="w-8 h-8 rounded-full bg-slate-100 ring-2 ring-white grid place-items-center text-[10px] font-bold">+${Math.max(0, f - 7)}</span></div>
          <p class="mt-4 text-sm font-bold text-navy-700 flex items-center gap-1">${st === 'Validé' ? 'Voir le plan' : 'Continuer le plan'} ${I('right', 'w-4 h-4')}</p></div></a>`).join('')}
      </div>`,
    };
  };

  // ----- Historique & stats -----
  R['#/historique'] = () => {
    const outs = ['29/08', '02/09', '05/09', '09/09', '12/09', '16/09', '19/09', '23/09'];
    const seed = (a, b) => { const v = (a * 31 + b * 17) % 23; return v < 15 ? 'present' : v < 18 ? 'retard' : v < 20 ? 'excuse' : 'absent'; };
    return {
      title: 'Historique & statistiques', crumb: 'Présences',
      actions: `<button class="btn-ghost btn-sm">${I('download', 'w-4 h-4')}Exporter CSV</button>`,
      body: `
      <div class="flex flex-wrap gap-2">${sel(['30 derniers jours', 'Saison 2026', 'Personnalisé…'], '30 derniers jours').replace('class="input', 'class="input w-48')}${sel(['Tous les membres', 'Dresseurs', 'Jeunes'], 'Tous les membres').replace('class="input', 'class="input w-48')}${sel(['Tous types', 'Entraînements', 'Régates'], 'Tous types').replace('class="input', 'class="input w-40')}</div>
      <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 lg:gap-5 mt-5">${kpi('Taux de présence', '82 %', '8 sorties · 24 membres', 'chart', 'green')}${kpi('Présents / sortie', '19,4', 'moyenne', 'users')}${kpi('Retards', '11', '−3 vs période préc.', 'clock', 'sun')}${kpi('Absences non excusées', '6', '3 membres concernés', 'alert', 'sky')}</div>
      <div class="card p-5 mt-5 overflow-hidden">${sectionTitle('Grille des présences', `<div class="hidden sm:flex gap-3 text-xs muted">${Object.values(YT.attendance).map(a => `<span class="flex items-center gap-1.5"><i class="w-2.5 h-2.5 rounded-sm" style="background:${a.color}"></i>${a.label}</span>`).join('')}</div>`)}
        <div class="overflow-x-auto -mx-5 px-5"><table class="min-w-[640px] w-full"><thead><tr><th class="th pl-0">Membre</th>${outs.map(o => `<th class="th text-center px-1">${o}</th>`).join('')}<th class="th text-right">Taux</th></tr></thead><tbody>
        ${YT.members.slice(0, 14).map(m => `<tr><td class="py-1.5 pr-3"><div class="flex items-center gap-2">${avatar(m, 'w-7 h-7 text-[10px]')}<span class="text-sm font-semibold whitespace-nowrap">${m.short}</span></div></td>${outs.map((o, i) => { const s = i === 7 ? (S.attendance[m.id] || null) : seed(m.id, i); return `<td class="px-1 py-1.5"><div class="h-7 rounded-md ${s ? '' : 'bg-slate-100'}" style="${s ? `background:${YT.attendance[s].color}` : ''}" title="${s ? YT.attendance[s].label : 'Non pointé'}"></div></td>`; }).join('')}<td class="text-right text-sm font-extrabold" style="color:${rateColor(m.rate)}">${m.rate}%</td></tr>`).join('')}
        </tbody></table></div><p class="text-xs muted mt-3">14 membres sur 24 affichés · <a class="font-semibold text-navy-700" href="#">voir tout</a></p></div>
      <div class="grid gap-5 lg:grid-cols-2 mt-5">
        <div class="card p-5">${sectionTitle('Les plus assidus')}${[...YT.members].sort((a, z) => z.rate - a.rate).slice(0, 5).map((m, i) => `<div class="flex items-center gap-3 py-2"><span class="w-6 text-sm font-extrabold ${i === 0 ? 'text-sun-500' : 'muted'}">${i + 1}</span>${avatar(m, 'w-8 h-8 text-[10px]')}<span class="flex-1 text-sm font-semibold">${m.name}</span><div class="w-24">${bar(m.rate, rateColor(m.rate))}</div><span class="text-sm font-bold w-10 text-right">${m.rate}%</span></div>`).join('')}</div>
        <div class="card p-5">${sectionTitle('À relancer')}${[...YT.members].sort((a, z) => a.rate - z.rate).slice(0, 5).map(m => `<div class="flex items-center gap-3 py-2">${avatar(m, 'w-8 h-8 text-[10px]')}<div class="flex-1"><p class="text-sm font-semibold">${m.name}</p><p class="text-[11px] muted">${Math.round((100 - m.rate) / 12)} absences sur les 8 dernières sorties</p></div><span class="text-sm font-bold" style="color:${rateColor(m.rate)}">${m.rate}%</span><a href="tel:" class="w-8 h-8 grid place-items-center rounded-lg bg-slate-100">${I('phone', 'w-4 h-4')}</a></div>`).join('')}</div>
      </div>`,
    };
  };

  // ----- Régates -----
  R['#/regates'] = () => ({
    title: 'Régates & courses', crumb: 'Saison 2026',
    actions: `<button class="btn-primary btn-sm">${I('plus', 'w-4 h-4')}Nouvelle régate</button>`,
    body: `
    <div class="rounded-3xl bg-gradient-to-br from-navy-900 to-navy-700 text-white p-5 lg:p-7 flex flex-col lg:flex-row gap-5 lg:items-center">
      <div class="flex-1"><span class="chip bg-sun-400 text-navy-950">Prochaine échéance · J-11</span><h2 class="text-2xl font-extrabold mt-3">Régate de Sainte-Anne</h2><p class="text-navy-200 text-sm mt-1">Dimanche 4 octobre 2026 · départ 08:00 · Ti-Bwa engagée</p></div>
      <div class="flex gap-2"><a href="#/regates/2" class="btn-sun">Préparer l’équipage</a></div>
    </div>
    <div class="grid md:grid-cols-2 gap-5 mt-5">${YT.races.map(r => `
      <a href="#/regates/${r.id}" class="card p-5 hover:shadow-lg transition">
        <div class="flex items-start gap-4"><span class="w-12 h-12 rounded-2xl grid place-items-center ${r.status === 'terminee' ? 'bg-navy-50 text-navy-700' : 'bg-sun-100 text-amber-700'}">${I('trophy')}</span>
        <div class="flex-1 min-w-0"><div class="flex items-center gap-2 flex-wrap"><h3 class="font-extrabold">${r.name}</h3><span class="chip bg-slate-100 text-slate-700">${r.type}</span></div>
          <p class="text-sm muted mt-0.5">${r.start}${r.end ? ' → ' + r.end : ''} · ${r.place}</p>
          <div class="flex items-center gap-4 mt-3 text-sm"><span class="flex items-center gap-1.5 font-semibold">${I('boat', 'w-4 h-4 text-slate-400')}${r.boat}</span><span class="flex items-center gap-1.5 font-semibold">${I('pin', 'w-4 h-4 text-slate-400')}${r.stages} étape${r.stages > 1 ? 's' : ''}</span></div></div>
        ${r.rank ? `<div class="text-center"><p class="text-3xl font-extrabold text-navy-900">${r.rank}<sup class="text-sm">e</sup></p><p class="text-[10px] font-bold uppercase muted">classement</p></div>` : `<span class="chip bg-sky-100 text-sky-800">À venir</span>`}</div></a>`).join('')}</div>`,
  });

  R['#/regates/:id'] = () => ({
    title: 'Tour des Yoles Rondes 2026', crumb: 'Régates', back: '#/regates',
    actions: `<button class="btn-ghost btn-sm">${I('edit', 'w-4 h-4')}Modifier</button><button class="btn-primary btn-sm">${I('plus', 'w-4 h-4')}Saisir un résultat</button>`,
    body: `
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 lg:gap-5">${kpi('Classement général', '6ᵉ', 'sur 15 yoles', 'trophy', 'sun')}${kpi('Meilleure étape', '3ᵉ', 'Étape 4 · Le François', 'star', 'green')}${kpi('Points', '70', 'cumul 7 étapes', 'chart')}${kpi('Yoleurs engagés', '19', 'rotation sur 7 étapes', 'users', 'sky')}</div>
    <div class="grid gap-5 lg:grid-cols-[1fr_360px] mt-5">
      <div class="card overflow-hidden"><div class="p-5 pb-3">${sectionTitle('Étapes & résultats · Ti-Bwa')}</div>
        <div class="overflow-x-auto"><table class="w-full min-w-[620px]"><thead class="bg-slate-50"><tr><th class="th">Étape</th><th class="th">Date</th><th class="th">Parcours</th><th class="th">Temps</th><th class="th text-center">Rang</th><th class="th text-center">Pts</th><th class="th"></th></tr></thead><tbody>
        ${YT.stages.map(s => `<tr class="hover:bg-slate-50"><td class="td font-extrabold">${s.n}</td><td class="td text-sm">${s.date}</td><td class="td text-sm font-semibold">${s.from} → ${s.to}</td><td class="td text-sm tabular-nums">${s.time}</td><td class="td text-center"><span class="inline-grid place-items-center w-8 h-8 rounded-full font-extrabold text-sm ${s.rank <= 3 ? 'bg-sun-400 text-navy-950' : 'bg-slate-100'}">${s.rank}</span></td><td class="td text-center font-bold">${s.pts}</td><td class="td"><a href="#/equipage/valide" class="text-xs font-bold text-navy-700 whitespace-nowrap">Équipage →</a></td></tr>`).join('')}
        </tbody></table></div></div>
      <div class="space-y-5">
        <div class="card p-5">${sectionTitle('Rang par étape')}
          <svg viewBox="0 0 300 140" class="w-full"><g stroke="#E2E8F0">${[1, 5, 10, 15].map(r => `<line x1="20" x2="295" y1="${10 + (r - 1) * 8.5}" y2="${10 + (r - 1) * 8.5}"/><text x="0" y="${14 + (r - 1) * 8.5}" font-size="9" fill="#94A3B8" stroke="none">${r}</text>`).join('')}</g>
          <polyline fill="none" stroke="#0B2545" stroke-width="2.5" points="${YT.stages.map((s, i) => `${30 + i * 43},${10 + (s.rank - 1) * 8.5}`).join(' ')}"/>${YT.stages.map((s, i) => `<circle cx="${30 + i * 43}" cy="${10 + (s.rank - 1) * 8.5}" r="4.5" fill="${s.rank <= 3 ? '#F5B700' : '#0B2545'}" stroke="#fff" stroke-width="2"/>`).join('')}</svg>
          <p class="text-[11px] muted">Plus le point est haut, meilleur est le classement.</p></div>
        <div class="card p-5">${sectionTitle('Équipage le plus aligné')}${[1, 2, 4, 6, 9, 10, 3].map(id => { const m = YT.member(id); return `<div class="flex items-center gap-3 py-1.5">${avatar(m, 'w-8 h-8 text-[10px]')}<span class="flex-1 text-sm font-semibold">${m.name}</span><span class="text-xs muted">${YT.roles[m.roles[0]].label}</span><span class="chip bg-navy-50 text-navy-800">${7 - (id % 3)}/7</span></div>`; }).join('')}</div>
      </div>
    </div>`,
  });

  // ----- Synchronisation -----
  R['#/synchro'] = () => ({
    title: 'Synchronisation', crumb: 'Mode hors ligne',
    actions: `<button data-action="toggle-offline" class="btn-ghost btn-sm">${I(S.offline ? 'cloud' : 'wifiOff', 'w-4 h-4')}${S.offline ? 'Simuler retour réseau' : 'Simuler hors ligne'}</button><button class="btn-primary btn-sm">${I('refresh', 'w-4 h-4')}Synchroniser</button>`,
    body: `
    <div class="grid gap-5 lg:grid-cols-3">
      <div class="card p-5 lg:col-span-2 ${S.offline ? 'bg-amber-50 border-amber-200' : ''}">
        <div class="flex items-center gap-4"><span class="w-14 h-14 rounded-2xl grid place-items-center ${S.offline ? 'bg-amber-400 text-navy-950' : 'bg-emerald-500 text-white'}">${I(S.offline ? 'wifiOff' : 'cloudCheck', 'w-7 h-7')}</span>
        <div class="flex-1"><p class="text-lg font-extrabold">${S.offline ? 'Hors ligne' : 'En ligne — tout est à jour'}</p><p class="text-sm muted">${S.offline ? `${YT.syncQueue.length} modifications enregistrées sur cet appareil, envoyées dès le retour du réseau.` : 'Dernière synchronisation aujourd’hui à 06:02.'}</p></div>
        <button data-action="toggle-offline" class="btn-ghost btn-sm lg:hidden">Simuler</button></div>
      </div>
      <div class="card p-5"><p class="font-bold mb-3">Disponible hors ligne</p>${[['users', '24 membres'], ['boat', '3 yoles · 6 configurations'], ['calendar', '4 sorties à venir'], ['checkSq', 'Présences & plans du jour']].map(([i, t]) => `<p class="flex items-center gap-2.5 text-sm py-1">${I(i, 'w-4 h-4 text-emerald-600')}${t}</p>`).join('')}<p class="text-[11px] muted mt-2">Données locales mises à jour à 06:02.</p></div>
    </div>
    <div class="card mt-5 overflow-hidden"><div class="p-5 pb-3">${sectionTitle('File d’attente', `<span class="chip bg-slate-100 text-slate-700">${YT.syncQueue.length} éléments</span>`)}</div>
      <div class="divide-y divide-slate-100">${YT.syncQueue.map(q => `<div class="px-5 py-3 flex items-center gap-3"><span class="w-9 h-9 rounded-xl grid place-items-center ${q.status === 'conflict' ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-700'}">${I(q.status === 'conflict' ? 'alert' : 'clock', 'w-4 h-4')}</span><div class="flex-1 min-w-0"><p class="text-sm font-semibold truncate">${q.label}</p><p class="text-xs muted">${q.entity} · modifié à ${q.when} sur ce téléphone</p></div>${q.status === 'conflict' ? `<a href="#/synchro?modal=conflict" data-action="open-conflict" class="chip bg-red-100 text-red-700">Conflit · résoudre</a>` : '<span class="chip bg-amber-100 text-amber-800">En attente</span>'}</div>`).join('')}</div></div>
    <div class="card p-5 mt-5 border-red-200">
      <div class="flex items-start gap-3"><span class="w-10 h-10 rounded-xl bg-red-100 text-red-600 grid place-items-center">${I('alert')}</span><div class="flex-1"><p class="font-extrabold">Conflit sur « La Cagou · Patron »</p><p class="text-sm muted">Le poste a été modifié à la fois sur ce téléphone et par un autre utilisateur.</p></div></div>
      <div class="grid sm:grid-cols-2 gap-3 mt-4">
        <div class="rounded-2xl border-2 border-navy-900 p-4"><p class="text-[11px] font-bold uppercase muted">Votre version · 06:25 (hors ligne)</p><div class="flex items-center gap-3 mt-2">${avatar(YT.member(22))}<div><p class="font-bold">Thierry Cyrille</p><p class="text-xs muted">par Rodrigue C. · ce téléphone</p></div></div><button class="btn-primary btn-sm w-full mt-4">Garder ma version</button></div>
        <div class="rounded-2xl border border-slate-200 p-4"><p class="text-[11px] font-bold uppercase muted">Version serveur · 06:19</p><div class="flex items-center gap-3 mt-2">${avatar(YT.member(2))}<div><p class="font-bold">Jean-Marc Lérus</p><p class="text-xs muted">par Max B. · ordinateur du club</p></div></div><button class="btn-ghost btn-sm w-full mt-4">Garder la version serveur</button></div>
      </div>
    </div>`,
  });

  // ----- Paramètres -----
  R['#/parametres'] = () => ({
    title: 'Paramètres', crumb: 'Administration',
    actions: `<button data-action="toast-saved" class="btn-primary btn-sm">Enregistrer</button>`,
    body: `
    <div class="flex gap-1.5 overflow-x-auto scrollbar-none mb-5">${['Association', 'Utilisateurs', 'Postes & rôles', 'Application'].map((t, i) => `<button class="chip h-9 px-4 whitespace-nowrap ${i === 0 ? 'bg-navy-900 text-white' : 'bg-white border border-slate-200 text-slate-600'}">${t}</button>`).join('')}</div>
    <div class="grid gap-5 xl:grid-cols-2">
      <section class="card p-5 lg:p-6"><h3 class="font-bold mb-4">Association</h3>
        <div class="flex items-center gap-4 mb-5"><span class="w-16 h-16 rounded-2xl bg-navy-900 text-sun-400 grid place-items-center">${I('boat', 'w-8 h-8')}</span><div><button class="btn-ghost btn-sm">Changer le logo</button><p class="text-[11px] muted mt-1">PNG ou SVG, 512×512 recommandé</p></div></div>
        <div class="grid sm:grid-cols-2 gap-4">${field('Nom', inp(YT.association.name))}${field('Commune', inp(YT.association.city))}${field('Saison en cours', sel(['2026', '2027'], '2026'))}
          <div><label class="label">Couleur principale</label><div class="flex gap-2">${['#0B2545', '#E11D48', '#0EA5E9', '#16A34A', '#7C3AED'].map((c, i) => `<button class="w-10 h-10 rounded-xl ${i === 0 ? 'ring-4 ring-sun-400/60' : ''}" style="background:${c}"></button>`).join('')}</div></div></div></section>
      <section class="card overflow-hidden"><div class="p-5 lg:p-6 pb-3 flex items-center justify-between"><h3 class="font-bold">Utilisateurs</h3><button class="btn-ghost btn-sm">${I('plus', 'w-4 h-4')}Inviter</button></div>
        <div class="divide-y divide-slate-100">${YT.users.map(u => `<div class="px-5 lg:px-6 py-3 flex items-center gap-3"><span class="w-10 h-10 rounded-full bg-navy-100 text-navy-800 grid place-items-center font-bold text-sm">${u.name.split(' ').map(x => x[0]).join('')}</span><div class="flex-1 min-w-0"><p class="font-semibold text-sm">${u.name}</p><p class="text-xs muted truncate">${u.email} · ${u.last}</p></div><span class="chip ${u.role === 'admin' ? 'bg-navy-900 text-white' : 'bg-sun-100 text-amber-800'}">${u.role === 'admin' ? 'Admin / bureau' : 'Patron'}</span></div>`).join('')}</div>
        <div class="p-5 lg:p-6 pt-3 text-[12px] muted grid sm:grid-cols-2 gap-3"><p><b class="text-navy-900">Admin / bureau</b> — membres, yoles, régates, utilisateurs, paramètres.</p><p><b class="text-navy-900">Patron</b> — appel, plans d’équipage, sorties, consultation des membres.</p></div></section>
      <section class="card p-5 lg:p-6 xl:col-span-2"><h3 class="font-bold mb-1">Postes & rôles</h3><p class="text-xs muted mb-4">Référentiel commun à toutes les yoles. Les couleurs sont utilisées dans le plan d’équipage.</p>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-2">${Object.entries(YT.roles).map(([k, r]) => `<div class="flex items-center gap-3 p-3 rounded-xl bg-slate-50"><span class="w-9 h-9 rounded-full grid place-items-center text-white text-[10px] font-extrabold" style="background:${r.color}">${r.short}</span><div><p class="font-semibold text-sm">${r.label}</p><p class="text-[11px] muted">${r.zone}</p></div></div>`).join('')}</div></section>
    </div>`,
  });

  // ----- Plus (mobile) -----
  R['#/plus'] = () => ({
    title: 'Plus',
    body: `<div class="card p-4 flex items-center gap-3"><span class="w-12 h-12 rounded-full bg-sun-400 text-navy-950 grid place-items-center font-bold">RC</span><div class="flex-1"><p class="font-bold">Rodrigue Céleste</p><p class="text-xs muted">Patron · ${YT.association.name}</p></div></div>
    <div class="card mt-4 divide-y divide-slate-100">${[['#/membres', 'users', 'Membres', '24'], ['#/yoles', 'boat', 'Yoles', '3'], ['#/regates', 'trophy', 'Régates', '4'], ['#/historique', 'chart', 'Historique & stats', ''], ['#/synchro', 'refresh', 'Synchronisation', S.offline ? '4 en attente' : 'À jour'], ['#/parametres', 'settings', 'Paramètres', ''], ['#/etats', 'smartphone', 'États de l’interface (maquette)', '']].map(([h, i, l, b]) => `<a href="${h}" class="flex items-center gap-3 px-4 py-3.5"><span class="w-9 h-9 rounded-xl bg-navy-50 text-navy-700 grid place-items-center">${I(i, 'w-[18px] h-[18px]')}</span><span class="flex-1 font-semibold">${l}</span><span class="text-xs muted">${b}</span>${I('right', 'w-4 h-4 text-slate-400')}</a>`).join('')}</div>
    <a href="#/login" class="btn-ghost w-full mt-4 text-red-600">${I('logout', 'w-4 h-4')}Se déconnecter</a>`,
  });

  // ----- États de l'interface -----
  R['#/etats'] = () => ({
    title: 'États de l’interface', crumb: 'Référence UX',
    body: `
    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
      <div class="card p-6 text-center"><p class="text-[11px] font-bold uppercase muted text-left mb-4">État vide</p><div class="w-16 h-16 rounded-2xl bg-navy-50 text-navy-500 grid place-items-center mx-auto">${I('calendar', 'w-8 h-8')}</div><p class="font-extrabold mt-4">Aucune sortie programmée</p><p class="text-sm muted mt-1">Créez votre première sortie pour faire l’appel et composer les équipages.</p><button class="btn-primary btn-sm mt-4">${I('plus', 'w-4 h-4')}Nouvelle sortie</button></div>
      <div class="card p-6"><p class="text-[11px] font-bold uppercase muted mb-4">Chargement</p>${[1, 2, 3, 4].map(() => `<div class="flex items-center gap-3 py-2"><div class="skeleton w-10 h-10 rounded-full"></div><div class="flex-1 space-y-2"><div class="skeleton h-3 w-2/3"></div><div class="skeleton h-2.5 w-1/3"></div></div></div>`).join('')}</div>
      <div class="card p-6 space-y-3"><p class="text-[11px] font-bold uppercase muted mb-1">Notifications (toasts)</p>
        <div class="rounded-xl bg-navy-900 text-white p-3 flex items-center gap-2.5 text-sm">${I('check', 'w-5 h-5 text-emerald-400')}Présences enregistrées</div>
        <div class="rounded-xl bg-amber-400 text-navy-950 p-3 flex items-center gap-2.5 text-sm font-semibold">${I('wifiOff', 'w-5 h-5')}Enregistré sur l’appareil — synchro au retour du réseau</div>
        <div class="rounded-xl bg-emerald-600 text-white p-3 flex items-center gap-2.5 text-sm">${I('cloudCheck', 'w-5 h-5')}4 modifications synchronisées</div>
        <div class="rounded-xl bg-red-600 text-white p-3 flex items-center gap-2.5 text-sm">${I('alert', 'w-5 h-5')}1 conflit à résoudre</div></div>
      <div class="card p-6"><p class="text-[11px] font-bold uppercase muted mb-4">Erreur de validation</p>${field('Poids (kg)', '<input class="input border-red-400 ring-4 ring-red-100" value="350">', '')}<p class="text-xs text-red-600 font-semibold mt-1.5 flex items-center gap-1">${I('alert', 'w-3.5 h-3.5')}Le poids doit être compris entre 30 et 150 kg.</p>
        <div class="mt-4 rounded-xl bg-red-50 text-red-700 text-sm p-3">Un membre ne peut occuper qu’un seul poste sur la même yole.</div></div>
      <div class="card p-6"><p class="text-[11px] font-bold uppercase muted mb-4">Badges de statut</p><div class="flex flex-wrap gap-2">
        <span class="chip bg-emerald-50 text-emerald-700 h-8 px-3">${I('cloudCheck', 'w-4 h-4')}Synchronisé</span><span class="chip bg-amber-100 text-amber-800 h-8 px-3">${I('wifiOff', 'w-4 h-4')}Hors ligne · 4</span><span class="chip bg-sky-100 text-sky-800 h-8 px-3">${I('refresh', 'w-4 h-4')}Synchronisation…</span><span class="chip bg-red-100 text-red-700 h-8 px-3">${I('alert', 'w-4 h-4')}Conflit</span>
        ${Object.keys(YT.attendance).map(attPill).join('')}${attPill(null)}<span class="chip bg-emerald-100 text-emerald-800">Validé</span><span class="chip bg-amber-100 text-amber-800">Brouillon</span></div></div>
      <div class="card p-6"><p class="text-[11px] font-bold uppercase muted mb-4">Installer l’application</p><div class="rounded-2xl bg-navy-900 text-white p-4"><div class="flex items-center gap-3"><span class="w-12 h-12 rounded-xl bg-sun-400 text-navy-950 grid place-items-center">${I('boat', 'w-6 h-6')}</span><div class="flex-1"><p class="font-bold">Installer YoleTeam</p><p class="text-xs text-navy-200">Accès rapide et mode hors ligne</p></div></div><div class="grid grid-cols-2 gap-2 mt-4"><button class="btn bg-white/10 text-white h-10">Plus tard</button><button class="btn-sun h-10">Installer</button></div></div></div>
      <div class="md:col-span-2 xl:col-span-3"><p class="text-[11px] font-bold uppercase muted mb-3">Fenêtre de confirmation</p><div class="rounded-3xl bg-navy-950/40 p-6 lg:p-10 grid place-items-center">${confirmBox()}</div></div>
    </div>`,
  });

  function confirmBox() {
    const b = YT.balance({ sails: 2, bwa: 4 }, S.plan);
    return `<div class="bg-white rounded-3xl shadow-2xl w-full max-w-md p-6">
      <span class="w-12 h-12 rounded-2xl bg-sun-100 text-amber-700 grid place-items-center">${I('check', 'w-6 h-6')}</span>
      <h3 class="text-lg font-extrabold mt-4">Valider le plan d’équipage ?</h3>
      <p class="text-sm muted mt-1">Ti-Bwa · 2 voiles · ${b.filled}/15 postes · ${b.total} kg à bord. Le plan sera visible par tous les patrons et figé pour cette sortie.</p>
      <div class="mt-4 rounded-xl bg-slate-50 p-3 text-sm space-y-1.5"><p class="flex justify-between"><span class="muted">Bâbord / tribord</span><b>${b.bab} / ${b.tri} kg</b></p><p class="flex justify-between"><span class="muted">Avant / arrière</span><b>${b.av} / ${b.ar} kg</b></p></div>
      <div class="grid grid-cols-2 gap-2 mt-5"><button data-action="close-modal" class="btn-ghost">Annuler</button><a href="#/equipage/valide" data-action="close-modal" class="btn-sun">Valider</a></div></div>`;
  }
  function modal(kind) {
    return `<div class="fixed inset-0 z-[70] bg-navy-950/50 grid place-items-center p-4" data-action="close-modal-bg">${kind === 'validate' ? confirmBox() : ''}</div>`;
  }

  // ---------- routeur ----------
  function resolve(hash) {
    const h = (hash || '#/').split('?')[0];
    if (R[h]) return [R[h], []];
    for (const k of Object.keys(R)) {
      if (!k.includes(':')) continue;
      const re = new RegExp('^' + k.replace(/:\w+/g, '([^/]+)') + '$');
      const m = h.match(re);
      if (m) return [R[k], m.slice(1)];
    }
    return [R['#/'], []];
  }
  function render() {
    const hash = location.hash || '#/';
    const q = new URLSearchParams(hash.split('?')[1] || '');
    if (q.get('modal') === 'conflict') S.modal = null;
    const [fn, args] = resolve(hash);
    const page = fn(...args);
    const route = hash.split('?')[0];
    $('#app').innerHTML = page.raw || shell(page, route);
    document.title = `${page.title || 'Connexion'} — YoleTeam`;
    bindDnD();
  }

  function toast(msg, tone = 'navy') {
    const cls = { navy: 'bg-navy-900 text-white', sun: 'bg-amber-400 text-navy-950' }[tone];
    $('#toast').innerHTML = `<div class="rounded-xl ${cls} px-4 py-3 shadow-2xl text-sm font-semibold flex items-center gap-2">${I(tone === 'sun' ? 'wifiOff' : 'check', 'w-4 h-4')}${msg}</div>`;
    clearTimeout(toast.t); toast.t = setTimeout(() => ($('#toast').innerHTML = ''), 2600);
  }

  function assign(code, memberId) {
    for (const k of Object.keys(S.plan)) if (S.plan[k] === memberId) delete S.plan[k];
    S.plan[code] = memberId;
    render();
  }

  // ---------- interactions ----------
  document.addEventListener('click', e => {
    const t = e.target.closest('[data-att],[data-action],[data-pos],[data-member],[data-boat],[data-config],[data-place],[data-rolefilter],[data-mtab]');
    if (!t) return;
    const d = t.dataset;
    if (d.att) { const [id, st] = d.att.split(':'); S.attendance[id] = S.attendance[id] === st ? null : st; render(); return; }
    if (d.pos) { S.selected = S.selected === d.pos ? null : d.pos; render(); return; }
    if (d.member) {
      const id = +d.member;
      if (S.selected && ![...Object.values(S.plan)].includes(id)) { assign(S.selected, id); toast(`${YT.member(id).short} → ${YT.buildConfig(config().sails, config().bwa).find(p => p.code === S.selected).label}`); }
      else if (!S.selected) toast('Sélectionnez d’abord un poste sur la yole');
      return;
    }
    if (d.boat) { S.boatId = +d.boat; S.configId = boat().configs.find(c => c.def).id; S.selected = null; if (S.boatId !== 1) S.plan = {}; else S.plan = { ...YT.demoPlan }; render(); return; }
    if (d.config) { S.configId = +d.config; const codes = YT.buildConfig(config().sails, config().bwa).map(p => p.code); for (const k of Object.keys(S.plan)) if (!codes.includes(k)) delete S.plan[k]; S.selected = null; render(); return; }
    if (d.place) { S.placement[S.selected] = d.place; render(); return; }
    if (d.rolefilter) { S.roleFilter = d.rolefilter; render(); return; }
    if (d.mtab) { S.memberTab = d.mtab; render(); return; }
    switch (d.action) {
      case 'all-present': YT.members.forEach(m => { if (!S.attendance[m.id]) S.attendance[m.id] = 'present'; }); render(); toast('Tous les membres non pointés sont présents'); break;
      case 'save-att': S.offline ? toast('Enregistré sur l’appareil — synchro au retour du réseau', 'sun') : toast('Présences enregistrées'); break;
      case 'unselect': S.selected = null; S.sheet = false; render(); break;
      case 'clear-pos': delete S.plan[S.selected]; render(); break;
      case 'reset': S.plan = {}; S.selected = null; render(); toast('Plan réinitialisé'); break;
      case 'validate': S.modal = 'validate'; render(); break;
      case 'close-modal': S.modal = null; if (t.tagName !== 'A') { e.preventDefault(); render(); } break;
      case 'close-modal-bg': if (e.target === t) { S.modal = null; render(); } break;
      case 'toggle-offline': S.offline = !S.offline; render(); break;
      case 'save-member': location.hash = '#/membres/7'; toast('Membre enregistré'); break;
      case 'toast-saved': toast('Modifications enregistrées'); break;
    }
  });
  document.addEventListener('input', e => {
    if (e.target.dataset.input === 'search') {
      S.search = e.target.value; const pos = e.target.selectionStart; render();
      const el = $('[data-input="search"]'); el.focus(); el.setSelectionRange(pos, pos);
    }
  });

  function bindDnD() {
    $$('[data-member][draggable="true"]').forEach(el => {
      el.addEventListener('dragstart', ev => { ev.dataTransfer.setData('text/plain', el.dataset.member); el.classList.add('drag-ghost'); });
      el.addEventListener('dragend', () => el.classList.remove('drag-ghost'));
    });
    $$('.yl-pos[data-pos]').forEach(g => {
      g.addEventListener('dragover', ev => { ev.preventDefault(); g.classList.add('drop-over'); });
      g.addEventListener('dragleave', () => g.classList.remove('drop-over'));
      g.addEventListener('drop', ev => { ev.preventDefault(); const id = +ev.dataTransfer.getData('text/plain'); S.selected = g.dataset.pos; assign(g.dataset.pos, id); toast(`${YT.member(id).short} placé`); });
    });
  }

  window.addEventListener('hashchange', () => { S.selected = params.get('sel') || null; S.modal = new URLSearchParams(location.hash.split('?')[1] || '').get('modal') || null; if (S.modal === 'conflict') S.modal = null; render(); scrollTo(0, 0); });
  S.modal = new URLSearchParams(location.hash.split('?')[1] || '').get('modal') || S.modal;
  render();
})();
