// Données fictives de démonstration — alignées sur les seeders Laravel (DemoSeeder)
window.YT = window.YT || {};

YT.association = { name: 'Association Yole Nou', short: 'Yole Nou', city: 'Le François', season: 2026 };

YT.users = [
  { id: 1, name: 'Sandrine Lagier', email: 'admin@yoleteam.test', role: 'admin', last: 'Aujourd’hui 08:12' },
  { id: 2, name: 'Rodrigue Céleste', email: 'patron@yoleteam.test', role: 'patron', last: 'Aujourd’hui 06:41' },
  { id: 3, name: 'Max Bellance', email: 'max.bellance@yoleteam.test', role: 'patron', last: 'Hier 17:05' },
];

YT.roles = {
  patron:         { label: 'Patron',        short: 'PAT', color: '#F5B700', zone: 'Arrière' },
  aide_patron:    { label: 'Aide-patron',   short: 'AID', color: '#3B82F6', zone: 'Arrière' },
  premiere_corde: { label: '1ère corde',    short: 'C1',  color: '#8B5CF6', zone: 'Avant' },
  deuxieme_corde: { label: '2ème corde',    short: 'C2',  color: '#8B5CF6', zone: 'Avant' },
  ecoute:         { label: 'Écoute',        short: 'ÉCO', color: '#F97316', zone: 'Gréement' },
  dresseur:       { label: 'Dresseur',      short: 'DR',  color: '#10B981', zone: 'Bwa dressés' },
  ecopeur:        { label: 'Écopeur',       short: 'ÉCP', color: '#06B6D4', zone: 'Coque' },
};

YT.levels = { debutant: 'Débutant', intermediaire: 'Intermédiaire', confirme: 'Confirmé', expert: 'Expert' };

YT.attendance = {
  present: { label: 'Présent', color: '#10B981', bg: '#D1FAE5', icon: '✓' },
  retard:  { label: 'Retard',  color: '#F59E0B', bg: '#FEF3C7', icon: '◷' },
  excuse:  { label: 'Excusé',  color: '#3B82F6', bg: '#DBEAFE', icon: 'E' },
  absent:  { label: 'Absent',  color: '#EF4444', bg: '#FEE2E2', icon: '✕' },
};

// [id, prénom, nom, surnom, kg, cm, niveau, catégorie, postes (1er = préféré), téléphone]
YT.members = [
  [1,  'Rodrigue', 'Céleste',   'Rod',     84, 181, 'expert',        'senior',  ['patron', 'aide_patron']],
  [2,  'Jean-Marc','Lérus',     '',        79, 176, 'confirme',      'senior',  ['aide_patron', 'patron']],
  [3,  'Kévin',    'Rosemain',  'Kéké',    78, 183, 'confirme',      'senior',  ['dresseur']],
  [4,  'Steeve',   'Nelson',    '',        92, 188, 'expert',        'senior',  ['dresseur']],
  [5,  'Johan',    'Bellemare', '',        88, 185, 'confirme',      'senior',  ['dresseur', 'ecoute']],
  [6,  'Wilfried', 'Duféal',    'Wil',     95, 190, 'expert',        'senior',  ['dresseur']],
  [7,  'Mickaël',  'Sainte-Rose','Mika',   74, 178, 'intermediaire', 'jeune',   ['dresseur', 'ecopeur']],
  [8,  'Dimitri',  'Jean-Louis','',        81, 180, 'confirme',      'senior',  ['dresseur']],
  [9,  'Lucien',   'Marie-Anne','Ti Lu',   68, 172, 'expert',        'veteran', ['ecoute', 'premiere_corde']],
  [10, 'Fabrice',  'Hyacinthe', '',        72, 175, 'confirme',      'senior',  ['premiere_corde', 'deuxieme_corde']],
  [11, 'Yannick',  'Élisabeth', 'Yaya',    70, 174, 'intermediaire', 'senior',  ['deuxieme_corde', 'premiere_corde']],
  [12, 'Ludovic',  'Anatole',   '',        86, 184, 'confirme',      'senior',  ['dresseur']],
  [13, 'Cédric',   'Mondésir',  '',        83, 182, 'intermediaire', 'senior',  ['dresseur']],
  [14, 'Teddy',    'Vadeleux',  '',        63, 170, 'debutant',      'jeune',   ['ecopeur']],
  [15, 'Alain',    'Rapon',     'Papa',    77, 173, 'expert',        'veteran', ['aide_patron', 'ecoute']],
  [16, 'Grégory',  'Nicolas',   '',        90, 187, 'confirme',      'senior',  ['dresseur']],
  [17, 'Raphaël',  'Louisy',    '',        76, 179, 'intermediaire', 'jeune',   ['dresseur', 'deuxieme_corde']],
  [18, 'Olivier',  'Théodose',  '',        85, 183, 'confirme',      'senior',  ['dresseur']],
  [19, 'Maëlle',   'Joseph',    '',        61, 168, 'intermediaire', 'jeune',   ['ecopeur', 'deuxieme_corde']],
  [20, 'Patrice',  'Zébina',    '',        89, 186, 'expert',        'senior',  ['dresseur']],
  [21, 'Nicolas',  'Bolivard',  '',        80, 181, 'debutant',      'jeune',   ['dresseur']],
  [22, 'Thierry',  'Cyrille',   'Titi',    82, 177, 'confirme',      'veteran', ['aide_patron', 'dresseur']],
  [23, 'Anthony',  'Mérine',    '',        87, 185, 'intermediaire', 'senior',  ['dresseur']],
  [24, 'Jérémy',   'Sévère',    '',        73, 176, 'debutant',      'jeune',   ['ecopeur', 'dresseur']],
].map(([id, first, last, nick, kg, cm, level, cat, roles]) => ({
  id, first, last, nick, kg, cm, level, cat, roles,
  name: `${first} ${last}`, short: `${first} ${last[0]}.`,
  initials: (first[0] + last[0]).toUpperCase(),
  phone: '0696 ' + String(100000 + id * 7919).slice(-6).replace(/(\d{2})(\d{2})(\d{2})/, '$1 $2 $3'),
  rate: [96, 91, 88, 94, 79, 92, 71, 85, 98, 83, 76, 87, 69, 64, 95, 81, 73, 89, 77, 93, 58, 86, 74, 62][id - 1],
  active: id !== 21 || true,
}));

YT.member = id => YT.members.find(m => m.id === id);

// Configurations de yole : positions en % (x : 50 = axe de la coque ; y : 0 = avant, 100 = arrière)
YT.buildConfig = (sails, bwa) => {
  const pos = [];
  pos.push({ code: 'c1', role: 'premiere_corde', label: '1ère corde', side: 'centre', x: 50, y: sails === 2 ? 9 : 11 });
  if (sails === 2) pos.push({ code: 'c2', role: 'deuxieme_corde', label: '2ème corde', side: 'centre', x: 50, y: 21 });
  const y0 = sails === 2 ? 31 : 33, y1 = sails === 2 ? 69 : 63;
  for (let i = 1; i <= bwa; i++) {
    const y = bwa === 1 ? y0 : y0 + (i - 1) * (y1 - y0) / (bwa - 1);
    pos.push({ code: `db${i}`, role: 'dresseur', label: `Dresseur bâbord ${i}`, side: 'babord', bwa: i, x: 14, y });
    pos.push({ code: `dt${i}`, role: 'dresseur', label: `Dresseur tribord ${i}`, side: 'tribord', bwa: i, x: 86, y });
  }
  pos.push({ code: 'eco', role: 'ecoute', label: 'Écoute', side: 'centre', x: 50, y: sails === 2 ? 50 : 48 });
  pos.push({ code: 'ecp', role: 'ecopeur', label: 'Écopeur', side: 'centre', x: 50, y: 75 });
  if (sails === 2) {
    pos.push({ code: 'ap1', role: 'aide_patron', label: 'Aide-patron 1', side: 'centre', x: 40, y: 84 });
    pos.push({ code: 'ap2', role: 'aide_patron', label: 'Aide-patron 2', side: 'centre', x: 60, y: 84, optional: true });
  } else {
    pos.push({ code: 'ap1', role: 'aide_patron', label: 'Aide-patron', side: 'centre', x: 50, y: 84 });
  }
  pos.push({ code: 'pat', role: 'patron', label: 'Patron', side: 'centre', x: 50, y: 93 });
  return pos;
};

YT.boats = [
  { id: 1, name: 'Ti-Bwa',   sponsor: 'Distillerie Habitation', color: '#E11D48', length: 9.8,  status: 'ok',
    configs: [{ id: 11, name: '1 voile', sails: 1, bwa: 3 }, { id: 12, name: '2 voiles', sails: 2, bwa: 4, def: true }] },
  { id: 2, name: 'La Cagou', sponsor: 'Carrosserie Madinina',   color: '#0EA5E9', length: 10.2, status: 'ok',
    configs: [{ id: 21, name: '1 voile', sails: 1, bwa: 3 }, { id: 22, name: '2 voiles', sails: 2, bwa: 4, def: true }] },
  { id: 3, name: 'Zetwal',   sponsor: 'Boulangerie du Bourg',   color: '#F5B700', length: 9.5,  status: 'reparation',
    configs: [{ id: 31, name: '1 voile', sails: 1, bwa: 3, def: true }, { id: 32, name: '2 voiles', sails: 2, bwa: 5 }] },
];

YT.outings = [
  { id: 101, type: 'entrainement', title: 'Entraînement du matin', date: 'Mer. 23 sept.', iso: '2026-09-23', time: '06:00 – 08:30', place: 'Baie du François', status: 'en_cours', boats: [1, 2], today: true },
  { id: 102, type: 'entrainement', title: 'Entraînement virements', date: 'Sam. 26 sept.', iso: '2026-09-26', time: '07:00 – 10:00', place: 'Baie du François', status: 'planifiee', boats: [1, 2] },
  { id: 103, type: 'regate', title: 'Régate de Sainte-Anne', date: 'Dim. 4 oct.', iso: '2026-10-04', time: '08:00 – 13:00', place: 'Sainte-Anne', status: 'planifiee', boats: [1] },
  { id: 104, type: 'sortie_libre', title: 'Sortie découverte jeunes', date: 'Mer. 7 oct.', iso: '2026-10-07', time: '14:00 – 16:00', place: 'Baie du François', status: 'planifiee', boats: [2] },
  { id: 99,  type: 'entrainement', title: 'Entraînement vent fort', date: 'Sam. 19 sept.', iso: '2026-09-19', time: '07:00 – 10:00', place: 'Baie du François', status: 'terminee', boats: [1, 2] },
  { id: 98,  type: 'entrainement', title: 'Entraînement du matin', date: 'Mer. 16 sept.', iso: '2026-09-16', time: '06:00 – 08:30', place: 'Baie du François', status: 'terminee', boats: [1] },
];
YT.outingTypes = { entrainement: 'Entraînement', regate: 'Régate', sortie_libre: 'Sortie libre' };
YT.outingStatus = {
  planifiee: { label: 'Planifiée', cls: 'bg-slate-100 text-slate-700' },
  en_cours:  { label: 'En cours',  cls: 'bg-emerald-100 text-emerald-800' },
  terminee:  { label: 'Terminée',  cls: 'bg-navy-50 text-navy-700' },
  annulee:   { label: 'Annulée',   cls: 'bg-red-100 text-red-700' },
};

// Présences du jour (sortie 101)
YT.todayAttendance = {
  1: 'present', 2: 'present', 3: 'present', 4: 'present', 5: 'present', 6: 'present', 7: 'retard', 8: 'present',
  9: 'present', 10: 'present', 11: 'present', 12: 'excuse', 13: 'present', 14: 'present', 15: 'present', 16: 'present',
  17: 'absent', 18: 'present', 19: 'present', 20: 'present', 21: null, 22: 'present', 23: null, 24: null,
};

// Plan d'équipage de démonstration : Ti-Bwa, 2 voiles
YT.demoPlan = {
  c1: 10, c2: 11, db1: 3, dt1: 4, db2: 5, dt2: 6, db3: 8, dt3: 12 /* excusé → sera remplacé */, db4: 16, dt4: 20,
  eco: 9, ecp: 14, ap1: 2, ap2: 15, pat: 1,
};
YT.demoPlan.dt3 = 18;
YT.demoPlacement = { db1: 'exterieur', dt1: 'milieu', db2: 'exterieur', dt2: 'milieu', db3: 'milieu', dt3: 'interieur', db4: 'milieu', dt4: 'interieur' };

YT.races = [
  { id: 1, name: 'Tour des Yoles Rondes 2026', type: 'Tour des Yoles', start: '26 juil.', end: '2 août 2026', place: 'Tour de la Martinique', status: 'terminee', boat: 'Ti-Bwa', rank: 6, stages: 7 },
  { id: 2, name: 'Régate de Sainte-Anne', type: 'Régate', start: '4 oct. 2026', end: '', place: 'Sainte-Anne', status: 'planifiee', boat: 'Ti-Bwa', rank: null, stages: 1 },
  { id: 3, name: 'Championnat FYRM – manche 3', type: 'Championnat', start: '15 nov. 2026', end: '', place: 'Le Robert', status: 'planifiee', boat: 'Ti-Bwa · La Cagou', rank: null, stages: 2 },
  { id: 4, name: 'Régate du Vauclin', type: 'Régate', start: '14 juin 2026', end: '', place: 'Le Vauclin', status: 'terminee', boat: 'La Cagou', rank: 3, stages: 1 },
];
YT.stages = [
  { n: 1, date: 'Dim. 26 juil.', from: 'Le Lamentin', to: 'Sainte-Luce', rank: 7, time: '2 h 41 min 12 s', pts: 9 },
  { n: 2, date: 'Lun. 27 juil.', from: 'Sainte-Luce', to: 'Le Marin', rank: 5, time: '1 h 58 min 40 s', pts: 11 },
  { n: 3, date: 'Mar. 28 juil.', from: 'Le Marin', to: 'Le Vauclin', rank: 4, time: '2 h 12 min 05 s', pts: 12 },
  { n: 4, date: 'Mer. 29 juil.', from: 'Le Vauclin', to: 'Le François', rank: 3, time: '1 h 44 min 51 s', pts: 13 },
  { n: 5, date: 'Jeu. 30 juil.', from: 'Le François', to: 'Le Robert', rank: 8, time: '1 h 31 min 22 s', pts: 8 },
  { n: 6, date: 'Ven. 31 juil.', from: 'Le Robert', to: 'La Trinité', rank: 6, time: '2 h 05 min 17 s', pts: 10 },
  { n: 7, date: 'Dim. 2 août',  from: 'Schœlcher', to: 'Fort-de-France', rank: 9, time: '0 h 48 min 36 s', pts: 7 },
];

// File de synchronisation hors ligne (démo)
YT.syncQueue = [
  { entity: 'Présence', label: 'Mickaël S. → Retard', when: '06:14', status: 'pending' },
  { entity: 'Présence', label: 'Raphaël L. → Absent', when: '06:15', status: 'pending' },
  { entity: 'Plan d’équipage', label: 'Ti-Bwa · Dresseur tribord 3 → Olivier T.', when: '06:22', status: 'pending' },
  { entity: 'Plan d’équipage', label: 'La Cagou · Patron → Thierry C.', when: '06:25', status: 'conflict' },
];
