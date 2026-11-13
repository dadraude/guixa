# 001 — Desglossament acceptat: fase 2, comandes del membre (2026-11-13)

Part del [registre canònic](../tasks.md), amb una única definició de dependències al registre. Font de
disseny: [«Llenguatge visual de la portada a tota l'aplicació»](../plan.md#llenguatge-visual-de-la-portada-a-tota-laplicació--proposta-de-pla),
seqüència d'implementació futura, punt 2, i «Inventari de desplegament». Aquest desglossament **està acceptat** (2026-11-13, [decisió
literal](../history/2026-11-13T10-40-21.781Z-no-task-e89110b6-19ce-472c-9144-10e2bdee47ff.md)); autoritza publicar el tiquet, no implementar-lo.

Desbloqueig: l'aprovació visual del pilot (T84) consta a l'[entrada
Q&A](../history/2026-11-13T09-22-04.247Z-DRA-265-e23507b9-941a-4aaf-b2d7-fb4fd0385aad.md); només desbloqueja
aquest desglossament, no altres famílies.

## Lectura de preparació i arquitectura

**Camí actual.** `/` és `OrderController@index` (`routes/web.php:61`): serveix `Orders/Index` si l'actor té
rol de membre i correu verificat, i `Welcome` altrament (`app/Http/Controllers/OrderController.php:24-30`).
`Orders/Index.vue` agrupa els cicles en obert / tancat a comandes / cancel·lat i enllaça cada `CycleCard` a
`orders.edit` (`pages/Orders/Index.vue:11-27`). `Orders/Edit.vue` (`routes/web.php:104-107`, middleware
`role:member`) mostra per productor les files d'`OrderProductRow` (`pages/Orders/Edit.vue:155-205`). Cada
fila té camp de quantitat, import i un botó per fila: «Desa» (actiu només si `open && !saving && dirty`,
`components/orders/OrderProductRow.vue:89`) o «Elimina» per a una línia marcada (`:79`). El desament és una
petició explícita `router.put` amb `only: ['order','errors']` (`Edit.vue:59-72`), també amb Enter
(`OrderProductRow.vue:59`); mentre es tecleja, una pausa de 250 ms demana `router.get` amb `only: ['preview']`
perquè el servidor posi preu al que s'ha escrit (`Edit.vue:34-51`). L'únic error visible és el `domain` de
pàgina (`Edit.vue:22-24,110`); l'error de validació `quantity` no es mostra enlloc avui (no hi ha `InputError`
per fila).

**Què ja dóna T84.** Admissió per coincidència exacta a `landingStockPages` i `pilotVariantFor`
(`lib/pilotTheme.ts:54-58`); `AppLayout.vue` en deriva la variant i `AppSidebarLayout.vue` la posa com a classe
del shell i la proveeix als portals (`usePilotVariantClass`: dialog, sheet, dropdown, tooltip, sonner). Els
tokens i el mapping viuen a `.landing-stock` / `.dark .landing-stock` (`resources/css/app.css:2124-2260`);
`Orders/Index` i `Orders/Edit` ja són a la llista verda `pilotPages` (`lib/pilotTheme.ts:2-3`), com
`Manage/Stock/Index`, de manera que els patrons `.pilot-*` ja s'hi apliquen i la variant els recolora. Les
pàgines de comandes no tenen cap diàleg propi: els únics overlays són els del shell.

**Què és nou.** (1) Admetre dues pàgines més a la mateixa llista exacta. (2) Mesurar i, si cal, ajustar només
els patrons que Comandes exerceix i el pilot no: fila amb camp de quantitat i import, insígnia Obert/Tancat
(`Edit.vue:176-183`, `bg-primary` / `bg-background text-muted-foreground`), text de línia marcada, enllaç a
cistella, avís de cicle cancel·lat, targeta de cicle enllaçada i fila de total. (3) Fixtures per als estats
que els manifests actuals no cobreixen. Cap canvi de contracte, controlador, política ni traducció.

**Tall.** Recomanació: **una sola tasca vertical, T85**, amb checkpoints interns. Contra dividir en
Index / Edit: (a) el recorregut demostrable del pla és un de sol, «escollir cicle, editar quantitats i llegir
totals»; amb dues entregues l'usuari saltaria de crema/blau (Index) a verd (Edit) en una navegació d'un clic
i el shell compartit canviaria dues vegades; (b) Index són 71 línies i `CycleCard`/`OrderPageHeader` són
compartits amb Edit, de manera que una entrega separada d'Index seria gairebé només l'admissió més mesures;
(c) la frontera temporal es toca una vegada, no dues. A favor de dividir: Edit conté la interacció més
arriscada (previsualització del servidor amb pausa), i un diff gran la podria amagar. Es mitiga amb
checkpoints (vegeu «Mida») sense separar PR obligatòriament. Si en executar el diff supera el rang estimat,
l'implementador ho comunica abans de dividir; no es divideix en silenci.

**Dependències i frontera.** `After: T84`, ja integrada (base `76ec1abf`; PR #408 fusionada): és una
dependència de codi satisfeta, no un bloqueig pendent. Sense relació nativa amb 002, 003 ni 004: només 004
enllaça destinacions `/cycles/{cycle}/order` (`004-dashboard/plan.md:26`), que no canvien. T85 només amplia les
rutes admeses; retirar la frontera temporal i desduplicar els tokens `--welcome-*` copiats (Deviation de
DRA-265, `history/2026-11-13T08-42-47.108Z-DRA-265-0189b8a6-6942-4c91-80f9-6a426385f9a2.md`) continua sent de
la fase 5. Les fases 3–5 no depenen de T85: el pla fa dependre la fase 3 de la jerarquia de camps del pilot,
«no de canvis funcionals en comandes». Sense nou epic ni tiquet en aquesta passada.

## T85

**Aplicar la identitat de la landing a les comandes del membre. Estat: acceptada; sense autorització d'execució.**

**Resultat.** Un membre pot entrar a `/`, veure els cicles agrupats, obrir-ne un, canviar quantitats, desar-les
amb la petició explícita, veure el total que calcula el servidor, treure una línia marcada «No es lliurarà» i,
en un cicle liquidat, anar a la seva cistella; la llista, l'editor i el shell mostren el llenguatge de la
portada en clar i fosc. Dades, permisos, rutes i missatges no canvien. Les regles R3b, R7–R10, R4b, R23a i
S4/S32/S33/S37/S48/S49 són contractes heretats que es protegeixen; T85 no els completa.

**Entrada, abast i camins verificats.** Camins de frontend des de `resources/js/`. Pàgines: `pages/Orders/Index.vue`
(prop `cycles: App.Http.MemberCycleRow[]`) i `pages/Orders/Edit.vue` (props `order`, `preview`). Components:
`components/orders/{OrderProductRow,CycleCard,OrderPageHeader}.vue` (sense specs propis), `lib/orderRows.ts`
(+ spec, no es toca), `components/InputError.vue`, `components/ui/input/Input.vue` (camp sense `type` ni
`pattern`). Estils: `lib/pilotTheme.ts`, `resources/css/app.css` (`.landing-stock`, `.pilot-layout-*`,
`.pilot-list-item`, `.pilot-amount`, `.order-page-header`). Tests: `pages/Orders/{Index,Edit}.spec.ts`,
`lib/pilotTheme.spec.ts`, `composables/usePilotTheme.spec.ts`, `tests/Feature/Ordering/OrdersPagesTest.php`.
Fitxer nou previst: manifest `resources/js/fixtures/landing-orders.visual.json` i el seu entry `.ts`, seguint
`landing-stock*.{ts,html,visual.json}`. Confirmar consumidors a la base candidata.

**Contractes i límits.** Es mantenen: la petició `PUT cycles/{cycle}/order/lines/{product}` amb `{ quantity }`
i `only:['order','errors']`; l'esborrat de línia marcada per `DELETE`; la previsualització `GET` amb
`typed`, `only:['preview']`, `preserveState`, `replace` i pausa de 250 ms (`Edit.vue:34-51`); Enter al camp
desa com avui; «Desa» només actiu amb canvi, obert i sense desament en curs; fila tancada de només lectura;
«Elimina» només a línia marcada (R3b/R8), també amb productor tancat; enllaç a cistella només si hi ha
`order.basketId` (R23a); cicle cancel·lat sense controls (R4b); avís «cap productor»; error `domain` com a
alerta de pàgina; noms llargs amb `wrap-anywhere`; imports amb `formatMoney`/`euros` i quantitats
amb `formatQuantity` en `ca-ES`, xifres tabulars (`.pilot-amount`). No es mostra cap error de camp nou ni es
canvia el comportament d'Enter: només presentació.

**Rols i negatius.** Membre amb correu verificat: ruta completa. Un gestor enllaçat a una llar és membre
(R35, `stock-wallet.md`) i veu el mateix; la variant no depèn del rol. Cap rol sense llar (gestor+productor)
obté `Orders/Edit` ni les mutacions: 403 (`OrdersPagesTest.php:10-20`); invitat redirigit a login; `/` amb
compte no membre o correu no verificat segueix servint `Welcome` (`OrderController.php:28-30`; tests
`OrdersPagesTest.php:262-272`). Les variants de tema no intervenen en autorització.

**Contracte de desplegament.** Afegir `Orders/Index` i `Orders/Edit` a l'`Set` exacte de `lib/pilotTheme.ts:54`,
mai per prefix `Orders/` ni per rol. Valor absent conserva el tema actual. Sortir de la pàgina treu la
variant del shell i dels portals; tornar-hi la posa. `Manage/Stock/Index` manté la variant; totes les altres
rutes no migrades i `Welcome` conserven els valors actuals. **Primer checkpoint, mecànic:** renombrar la
variant `landing-stock` a `landing` a tots els consumidors (`lib/pilotTheme.ts`, `resources/css/app.css`, les
dues specs i els tres fixtures de T84, inclosos els noms de fitxer `landing-stock*`) sense canviar cap valor ni
comportament; les proves de T84 han de continuar passant amb el nom nou abans d'afegir cap ruta (decisió A,
resolta). Sense preferència persistent ni flag nou. El tema verd (`pilotPages`) continua
llistant les dues pàgines, tal com fa el pilot per a `Manage/Stock/Index` i com demana el pla («mantenir el
comportament del flag actual durant la migració»).

**Dades fixes del manifest.** Reutilitzar les de `dra-205-visual.ts` (cicle `cycle-1`, productor `Hort de la
Vall`, producte `Tomàquets` kg 3,20 variable, línia quantitat `2`, `basketId: null`, `:156`) i els estats
existents: Edit per defecte, buit, error, contingut llarg, cicle cancel·lat; Index per defecte, buit, llarg,
tancat-i-cancel·lat. **Afegir** (A) línia marcada «No es lliurarà» amb «Elimina»; (B) enllaç a cistella amb
`basketId` informat; (C) desament en curs (botons deshabilitats); (D) quantitat escrita amb previsualització
no nul·la (`preview.amountCents`/`totalCents` ≠ 0, a diferència del `0` actual); (E) error `domain` amb una
fila editada i «Desa» actiu, en lloc d'un «error de camp», que avui no existeix; (F) nom llarg de producte
i de productor; (G) moltes línies (p. ex. 12 files en 3 productors, amb un productor tancat i un buit). Són
dades de revisió: no acrediten persistència ni autorització. Comprovar que `formatMoney` i `formatQuantity`
presenten les mateixes cadenes abans i després.

**Primer checkpoint que falla abans de passar.** Amb la ruta real `Orders/Edit` a 375 px en clar, mesurar
amb `getComputedStyle` el color de fons de «Desa» (`[data-test="save-…"]`) i del contenidor
`.pilot-layout-panel`: ha de mostrar encara el verd actual (`--palette-brand: #246b57`, `public/theme-colors.css:9`) abans
d'admetre la pàgina; registrar valor i element. Admetre-la i tornar a mesurar: botó principal, superfície,
camp i insígnia mostren les parelles del pla; una ruta no migrada i `Welcome`
conserven els valors anteriors. Mesura sobre ruta real, no la lectura d'una classe.

**Fet quan — casos Given/When/Then.**

1. **Donat** un membre amb cicles oberts, tancats i cancel·lats, **quan** obre `/` a 375 o 1280 px, clar o
   fosc, **aleshores** veu els mateixos grups, dates, recomptes i destinacions, sense scroll horitzontal, amb
   shell i targetes en la nova paleta; sense cicles apareix «Encara no hi ha cap cicle de comanda.».
2. **Donat** un cicle obert, **quan** el membre escriu `2` al camp i prem Enter, **aleshores** es fa un sol
   `PUT` amb `{ quantity: '2' }`, `only:['order','errors']`, i el camp, el total i l'import es llegeixen
   després del retorn d'Inertia. **Donat** el camp sense canvis, «Desa» és deshabilitat.
3. **Donat** que escriu `2` i espera, **quan** passen 250 ms, **aleshores** una sola petició `GET` amb
   `typed` i `only:['preview']` actualitza l'import i el total, sense restablir el scroll; abans de la pausa
   no se'n fa cap.
4. **Donat** un `domain` del servidor (R8/S2, R9/S49), **quan** es desa, **aleshores** l'alerta és visible,
   llegible en els dos modes i no desplaça controls; la fila conserva el seu valor escrit.
5. **Donat** un productor tancat, una línia marcada i un productor sense productes, **aleshores** el camp és
   només lectura, la insígnia «Tancat» es distingeix per text, «Elimina» és present amb nom accessible per
   producte i, en prémer-lo, s'envia el `DELETE` existent; el productor buit mostra el seu text.
6. **Donat** un cicle liquidat amb `basketId`, **aleshores** «Obre la teva cistella» apunta a la cistella; sense
   `basketId` no hi ha secció. **Donat** un cicle cancel·lat, només l'avís i el total, sense controls.
7. **Donades** les pàgines de comandes i la sheet mòbil / menú de compte oberts, **quan** es navega a una ruta
   no migrada, a `Manage/Stock/Index` i a `Welcome` i es torna, **aleshores** cada una mostra només la seva
   variant, sense fuga a portals; clar/fosc/sistema i navegació Inertia.
8. **Donats** tots els estats del manifest (existents + A–G), **quan** s'executa a 375 i 1280 px en clar/fosc
   i a 1024 px amb text al 200% en tots dos modes, **aleshores** les mesures coincideixen amb el pla: text
   normal ≥4,5:1, text gran i límits essencials (camp, insígnia, focus) ≥3:1, objectius ≥44 × 44 px, focus
   visible i ordre de tabulació, cap scroll horitzontal ni retall amb noms llargs, xifres tabulars, moviment
   reduït i font de reserva. Mesurar amb DOM/runner visual abans de qualsevol captura.

### Assignació de requisits i límits

| Font vigent | Part que T85 preserva i comprova | Resta i propietari |
| --- | --- | --- |
| Pla 001, fase 2 i criteris visuals | Variant, shell, llista, editor i contenció | Fases 3–5; tancament a fase 5 |
| 001 R7, R8, S33, S2/S3 | Només membre amb llar edita; productor tancat de només lectura | Regles i tests existents (`OrdersPagesTest.php`) |
| 001 R9, S49 | Mateix `quantity` enviat i `domain` mostrat; cap validació nova al client | Servidor propietari |
| 001 R3b, R6c, S32, S48 | Línia marcada visible i eliminable també amb productor tancat | Comportament ja implementat |
| 001 R4b, S37; R23a | Cicle cancel·lat sense controls; enllaç a cistella si `basketId` | Contingut de cistella: T65 |
| 001 R10, S5 | Mateixa instantània de nom/preu a la fila | `lib/orderRows.ts`, sense canvis |
| 001 R35 | Gestor enllaçat és membre; sense llar, no | Propietat existent |

Fonts de negoci: [ordering.md](../ordering.md), [pickup.md](../pickup.md), [stock-wallet.md](../stock-wallet.md).
Cap R#/S# nou; cap requisit es tanca amb una prova de color.

**Comprovacions.** Reutilitzar `pages/Orders/{Index,Edit}.spec.ts` (Enter, previsualització amb pausa,
etiquetes, cancel·lat, tancat, marcada, cistella), `lib/orderRows.spec.ts`, `pilotTheme.spec.ts` i
`usePilotTheme.spec.ts`; ampliar `pilotTheme.spec.ts` amb l'admissió exacta (`Orders/Index`, `Orders/Edit`
sí; `Orders/Other` i `Welcome` no). Reexecutar `tests/Feature/Ordering/OrdersPagesTest.php` si el diff toca
entrada o enllaços. Format, lint i tipus segons el diff. Validació completa en CI. Mesures i fixture no
substitueixen una revisió visual explícita (decisió B).

**Mida, checkpoints i propietat.** Estimació de 250–500 línies authored modificades (fixtures i tests
inclosos), incertesa mitjana: no cal crear infraestructura de variant, però el CSS exercit per la fila
d'edició no s'ha mesurat. Checkpoints: (1) manifest i mesura fallida reproduïble; (2) admissió exacta amb
tests i una ruta no migrada sense fuga; (3) `Orders/Index`; (4) `Orders/Edit` amb tots els estats i la
interacció real; (5) matriu, regressió i revisió. Un sol propietari. `DemoSeeder.php`/`DemoSeederTest.php` no
canvien: no s'afegeixen dades ni estats; el demo ja té membres amb llar.

**Preparació del tall T85.** Comportament: llest, sense R#/S# nou; cal preservar l'Enter no controlat per
«dirty» tal com és. Decisions: pendents A–B (no bloquegen mesurar). Disseny: llest, tokens heretats de T84; els
colors de camp, insígnia i total s'han de derivar i mesurar en execució. Externs: no aplicable. Camins: els
fitxers existeixen; el manifest és nou. Verificació: possible amb ruta real, fixtures i mesures; l'enllaç a
cistella i el desament dependeran dels tests HTTP/Vitest, no de les fixtures simulades.

## Decisions resolides i evidència

**A. Nom de la variant — resolta (2026-11-13).** `landing-stock` es renombra a `landing` dins T85, com a primer
checkpoint mecànic (vegeu «Contracte de desplegament»). Alternatives descartades: mantenir el nom fins a la fase
5 (enganyós a partir d'aquí) i un àlies (dos noms en paral·lel). Cost: 7 fitxers, 14 aparicions a `app.css`.

**B. Aprovació visual de la fase 2 — resolta (2026-11-13).** Aprovació explícita **no bloquejant**: abans
d'integrar T85, l'usuari revisa tres captures petites d'`Orders/Edit` (375 px clar, 1280 px fosc, línia
marcada) i les aprova; la fase 3 no en depèn, perquè depèn del pilot. Les mesures i el CI no la substitueixen.
Les captures es generen només per a aquesta revisió, amb el camí opcional del runner visual.

Evidència, no decisions: (c) el tema verd es manté, segons el pla i el precedent de T84 (`lib/pilotTheme.ts:2-3`);
(d) no hi ha l'equivalent del buit `pattern` vs validació nativa: el camp de quantitat és un `Input` sense `type` ni
`pattern` (`Input.vue`, `OrderProductRow.vue:47-60`), de manera que `9,1234` arriba al servidor. Troballa
associada: Enter desa sense comprovar `dirty`, i amb el camp intacte envia `quantity` sense valor (no verificat en execució); el
servidor hauria de retornar l'error de validació `quantity` (`OrdersPagesTest.php:111-117`) que cap component mostra. No
canvia l'acceptació; es deixa documentat com a possible millora funcional separada.
