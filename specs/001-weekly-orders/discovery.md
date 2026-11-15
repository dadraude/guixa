# 001 — Descoberta reconstruïda per al pilot visual d'Estoc

**Data:** 2026-11-11. **Tall seleccionat:** `landing-style-stock-pilot` (T84). **Naturalesa:** reconstrucció documental de decisions ja preses; no és una descoberta retrospectiva inventada ni una nova proposta de producte.

## Origen i decisió de ruta

- La [petició original](history/2026-11-11T16-38-52.821Z-no-task-e8ac632f-06de-471a-96fa-f051311ab013.md) demana un pla per aplicar l'estil de la landing a tota l'app. És una petició de presentació dins de 001, no una funcionalitat nova. La ruta escollida fou **plan-only** sobre l'especificació 001 existent.
- L'[aprovació del pla](history/2026-11-11T16-58-01.938Z-no-task-af456e57-d88a-4d3a-b411-52e529785370.md) fixa la direcció visual, la seqüència i el primer pilot `Manage/Stock/Index.vue` + `StockEditDialog.vue` + shell compartit. No autoritzà implementar ni publicar tiquets.
- La petició actual diu: «Aprovo el desglossament de T84. Reconcilia l’evidència de workflow de 001 amb les skills del projecte, sense implementar encara ni publicar tiquets. Ha de ser una spec nova?» Aquesta aprovació consta a [l’entrada de decisió](history/2026-11-11T17-11-48.918Z-no-task-f1d25139-bd9e-455b-8b11-b1728e63688a.md). Selecciona T84 com a primer tall documental i permet reconciliar l'evidència existent; no canvia els contractes de negoci.

La [spec 001](spec.md), les àrees vigents i les [obligacions de rols de 003](../003-role-management/spec.md) ja defineixen el comportament. El [pla 001](plan.md#llenguatge-visual-de-la-portada-a-tota-laplicació--proposta-de-pla) acceptat defineix la direcció de presentació. El [desglossament T84](tasks/landing-style-pilot.md#t84) acceptat defineix el lliurament vertical i les seves comprovacions. **No cal una spec nova:** no hi ha nou actor, regla, estat, permís, ruta ni contracte de dades. Si una implementació futura descobreix una decisió funcional nova, es tramitarà aleshores per la ruta corresponent; aquesta reconstrucció no la pressuposa.

## Abast, persones i resultat

L'usuari directe del pilot és el gestor que consulta i ajusta l'estoc. El resultat útil és una ruta d'estoc que permet llegir, cercar, obrir el diàleg, desar un recompte o veure'n l'error i tornar al mateix context, amb la identitat visual real de la landing en clar i fosc. El shell i els overlays que s'obren en aquest recorregut comparteixen el tema. L'altra persona afectada és qualsevol usuari d'una ruta encara no migrada: no ha de rebre aquest tema per accident. El rol Catàleg conserva les seves rutes i permisos propis; `/manage/stock` continua sent del gestor.

Els camins d'entrada i sortida són els existents: `resources/js/pages/Manage/Stock/Index.vue` rep `StockLevelRow[]`; `StockEditDialog.vue` i `NativeDialog.vue` mostren l'edició; les accions usen els endpoints d'estoc/catàleg ja establerts. El tema compartit passa per `AppLayout.vue`, `lib/pilotTheme.ts`, `public/theme-colors.css` i `resources/css/app.css`. La variant `landing-stock` de T84 s'activa només per coincidència exacta de `Manage/Stock/Index`; el valor absent conserva l'aspecte previ. La revisió visual explícita del pilot precedeix qualsevol extensió a altres famílies.

**Fora d'abast:** implementació en aquesta reconciliació, publicació de tiquets, nova pantalla, noves dades de demo, regles de quantitat o d'estat, càlculs, permisos, rutes, props, traduccions de negoci, redesign global immediat, recuperació de la proposta 002 retirada. La portada integrada a PR #385 es manté com a referència i no es reimplementa.

## Obligacions compartides i propietat

| Font | Contracte del pilot | Propietari de la resta |
| --- | --- | --- |
| Pla 001, fase 1 i criteris visuals | T84: identitat de la landing en llista/taula, formulari, diàleg, shell i overlays; contrast, focus, reflow i contenció de variant | Fases 2–5 del pla: comandes, catàleg/productor, finances/administració, accés i tancament transversal; cada tall es detalla després del pilot |
| 001 R25/R25a, S71/S72; 003 R4 | T84 conserva estat visible, enllaços i separació de permisos gestor/Catàleg | T52/T53 i 003 conserven creació, fitxa, classificació, unitats i autorització de negoci |
| 001 R27/R28, S23 | T84 conserva formulari, errors, endpoint i quantitat retornada | Implementació funcional i proves existents conserven entrada, ajustos i ledger; T84 no tanca S23 |
| 001 R28a, S93 | T84 conserva la destinació de moviments i l'autorització | T76 conserva historial, paginació i variants de rol; el seu estil correspon al tall posterior |
| 004 dashboard | Cap canvi funcional en T84 | 004 conserva els seus contractes i la futura reconciliació visual |

No s'afegeix cap R#/S# i cap comprovació de color demostra per si sola una regla de negoci. T47–T51 i T83 són feina integrada que el pilot reutilitza; les seves obligacions de tancament històriques no es reassignen. T84 té una sola persona/equip propietari del resultat vertical, amb checkpoints de mesura inicial, variant acotada, recorregut i revisió. Una separació de tokens, shell i diàleg en tasques independents no lliuraria un recorregut útil i faria insegura la frontera del tema.

## Full de ruta lleuger després del tall seleccionat

| Tall candidat | Resultat observable | Dependència real i límit |
| --- | --- | --- |
| Comandes del membre | Llegir cicles, editar quantitats i totals amb la identitat aprovada | Usa tokens/shell validats i necessita revisió visual explícita del pilot; flux de comanda intacte |
| Catàleg i productor | Llistes i formularis de productes llegibles amb els permisos existents | Usa jerarquia de formularis i accions del pilot; detall per ruta/rol després de la revisió |
| Finances, recepció i administració | Revisar dades denses i accions sensibles amb el mateix llenguatge | Consumeix patrons aprovats; conserva obligacions 003 i 004 sense absorbir-les |
| Accés, preferències i tancament transversal | Cobertura de totes les rutes, overlays i preferència clar/fosc/sistema, amb retirada de la variant temporal | Depèn de la revisió de tots els consumidors; no es planifica com una sola PR |

La preferència pel pilot d'estoc ja fou acceptada: combina taula d'escriptori, llista mòbil, formulari, diàleg i navegació en un recorregut. Començar per la portada duplicaria feina integrada; començar per una pàgina simple reduiria menys incertesa. Aquest full de ruta conserva les fases acceptades, sense donar IDs, estimacions ni tasques executables als talls futurs. La seva selecció detallada es farà amb evidència de la base integrada i del pilot aprovat.

## Traça i límits de l'evidència

Aquesta reconstrucció enllaça decisions i fonts existents, sense afirmar que s'haguessin produït entrevistes o una descoberta separada abans del pla. La base de codi examinada per al pla i T84 és `cfda8e70`; el graf `guixa` de 2026-11-01 era antic/parcial, i els fitxers canviats es van llegir directament. La validació de `workflow.json` i els gates de la skill s'han de nodrir d'aprovacions i revisions reals; cap camp buit o acceptació futura no s'ha de fabricar. La reconciliació documental no és una auditoria d'execució, de navegació o d'accessibilitat.
