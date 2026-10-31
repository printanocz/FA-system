# Splatno — návrhy obrazovek (referenční markup)

Orphan větev jen s návrhy z Claude Design. Kód aplikace je na `main`, screenshoty z PR na `pr-assets`.

- `artboards/*.dc.html` — artboardy plátna „Splatno — návrhy obrazovek“. Jsou to šablony Claude Design
  (`<x-dc>`, `{{…}}`, `<sc-for>`); samostatně se v prohlížeči nevykreslí, čtou se jako zdroj:
  struktura, třídy z `bundle.css`, inline rozměry, texty. Vzorová data jsou ve `renderVals()` na konci souboru.
- `artboards/canvas.json` — názvy a pořadí artboardů.

Implementace se řídí těmito soubory 1:1 (tokeny a třídy design systému, žádné hardcoded hodnoty).
Při změně návrhu řídicí chat tuto větev aktualizuje a v zadání uvede commit.
