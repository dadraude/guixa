# Guixa — app per a una cooperativa d'alimentació

Projecte desenvolupat amb **Spec-Driven Development (SDD)**, pas a pas i en sessions curtes.

## Flux SDD

1. **Visió** (`docs/visio.md`) — què és la cooperativa, qui fa servir l'app, què NO farem.
2. **Principis** (`docs/principis.md`) — decisions tècniques i regles que valen per a tot el projecte.
3. Per a cada funcionalitat, una carpeta `specs/NNN-nom/` amb:
   - `spec.md` — el QUÈ i el PER QUÈ (històries d'usuari, criteris d'acceptació). Sense detalls tècnics.
   - `pla.md` — el COM (model de dades, pantalles, API, decisions).
   - `tasques.md` — llista de tasques petites i verificables, amb `[ ]` / `[x]`.
4. **Implementació** — una o poques tasques per sessió, amb tests.

No s'escriu codi d'una funcionalitat fins que la seva `spec.md` i `pla.md` estan aprovades per l'usuari.
Les decisions rellevants es registren a `docs/decisions/NNN-titol.md` (ADR curt).

## Regles de sessió

- Cada sessió té **un sol objectiu** petit (p. ex. "escriure spec 001", "tasques 3-4 de 002").
- A l'inici: llegir `ESTAT.md`.
- Al final: deixar la feina tancada (res a mitges), actualitzar `ESTAT.md` amb el que s'ha fet i el següent pas concret, i fer commit.
- Si l'objectiu no hi cap, partir-lo i tancar la part feta.

## Idioma

Documentació i converses en català.
