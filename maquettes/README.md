# Maquettes YoleTeam

Prototype cliquable + exports des 23 écrans (desktop 1440 px et mobile 390 px).

| Fichier | Rôle |
|---|---|
| `index.html` | Tableau de toutes les maquettes (ouvre le prototype au clic) |
| `app.html` | Prototype interactif responsive (routeur `#/…`) |
| `export/png/desktop`, `export/png/mobile` | Captures PNG (mobile en @2x) |
| `export/YoleTeam-maquettes.pdf` | Planche PDF A3 paysage, une page par écran |
| `assets/data.js` | Données fictives, alignées sur `database/seeders/DemoSeeder.php` |
| `assets/yole.js` | Dessin SVG de la yole vue de dessus (coque, bwa dressés, mâts, postes) — à reprendre en composant Blade |
| `assets/tw.css` | Thème Tailwind 4 (couleurs marine / jaune, composants) — à reprendre dans `resources/css/app.css` |

## Interactions du prototype

- **Appel** : un clic sur P / R / E / A change le statut, les compteurs se mettent à jour.
- **Plan d'équipage** : glisser-déposer un membre sur un poste (desktop), ou toucher un poste puis choisir un membre (mobile).
  Changer de yole ou passer de 1 à 2 voiles. Pour un dresseur : position intérieur / milieu / extérieur sur le bwa.
  L'indicateur d'équilibre se recalcule à partir des poids déclarés.
- États de démo par URL : `app.html?offline=1#/appel` (mode hors ligne), `?sel=dt3&sheet=1#/equipage` (poste sélectionné).

## Régénérer les exports

```sh
# CSS (Tailwind CLI 4)
npx @tailwindcss/cli -i assets/tw.css -o assets/app.css
# PNG + PDF (puppeteer-core + Chrome installé)
npm i puppeteer-core
node tools/export.cjs            # tous les écrans
node tools/export.cjs membres    # un seul écran (slug)
```
