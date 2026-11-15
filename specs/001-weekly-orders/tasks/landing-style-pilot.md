# 001 — Desglossament acceptat: pilot visual d'estoc (2026-11-11)

Part del [registre canònic](../tasks.md), amb una única definició de dependències al registre. Font de
disseny: secció «Llenguatge visual de la portada a tota l'aplicació» del [pla
001](../plan.md#llenguatge-visual-de-la-portada-a-tota-laplicació--proposta-de-pla), ara acceptada per
l'usuari. Aquest desglossament està acceptat; no autoritza implementació ni publicació de tiquets.

Aprovació del pla: [decisió
literal](../history/2026-11-11T16-58-01.938Z-no-task-af456e57-d88a-4d3a-b411-52e529785370.md).

## Lectura de preparació i arquitectura

**Camí actual.** `Manage/Stock/Index.vue` rep `StockLevelRow[]` del controlador existent, filtra per nom al
client i mostra les mateixes dades en llista mòbil o taula d'escriptori. L'acció d'edició obre
`StockEditDialog.vue`; `NativeDialog.vue` usa `<dialog>.showModal()`, posa el focus al formulari i el retorna
al botó disparador quan es tanca. Des del formulari, `router.put` desa la quantitat per
`StockMovementController.setAvailable`; els canvis d'estat reutilitzen les rutes existents de
desactivació/reactivació. El shell i els estils actuals provenen d'`AppLayout.vue`, `lib/pilotTheme.ts`,
`public/theme-colors.css` i `resources/css/app.css`. Aquest camí ja funciona: la primera diferència observable
és la identitat visual de la portada aplicada de forma coherent a tota aquesta ruta i al seu diàleg sense
canviar el resultat de cap acció.

**Preparació del tall T84.** Comportament: llest, perquè el pilot preserva el contracte actual de l'estoc i
les regles acceptades de 001; cap R#/S# de negoci nou. Decisions: llestes per al pilot, amb paleta,
tipografia, abast de la textura, frontera temporal i porta d'aprovació estètica al pla acceptat. Disseny:
llest per al pilot, amb els components i estats reals identificats; els tokens concrets de camp, estat i focus
s'han de derivar i mesurar en execució, no inventar-los com a nous requisits de producte. Externs: no
aplicable; no hi ha servei o contracte nou. Camins: els fitxers esmentats existeixen, i el manifest de
fixtures és un fitxer nou previst. Verificació: possible amb ruta real, fixture determinista, mesures de DOM i
proves actuals; les fixtures visuals simulades no acrediten persistència o autorització. La cobertura actual
del graf és antiga/parcial, per tant l'implementador ha de confirmar els consumidors i rutes vigents al codi
de la seva base abans d’editar. El registre de fonts del pilot és `specs/001-weekly-orders/workflow.json`;
la seva comprovació només acredita prerequisits documentals, no autorització d’executar.

**Tall.** Una sola tasca executable T84. Separar tokens, shell, taula i diàleg en tasques independents
deixaria resultats parcials sense un recorregut d'usuari complet, i multiplicaria la frontera temporal del
tema. Si el diff creix, fer checkpoints interns revisables; no fusionar una preparació cromàtica sense la ruta
pilot. T47–T51 i T83 són antecedents integrats, no treball que cal repetir ni bloquejos d'implementació. T84
no és un nou bloqueig de T33 ni absorbeix gates de tancament històrics de 001. El desplegament cap a altres
famílies només es detallarà després de l'aprovació visual explícita del pilot.

**Dependències i frontera.** `After: —`: el pilot pot implementar-se sobre les fonts integrades; cap tasca
oberta no ha de lliurar abans un contracte nou. PR #385/T83 i T47–T51 són context integrat. El front
d'implementació conté T84 des del punt de vista de dependències, independentment de l'etiqueta de tracker,
autorització d'execució o CI. No afegir-hi una relació nativa amb 002, 003 o 004. Les seves obligacions
rellevants continuen com a restriccions de regressió, no com a tasques que aquest pilot completa. Sense nou
epic o tiquet en aquesta passada documental.

## T84

**Aplicar la identitat de la landing al pilot d’Estoc de gestió. Estat: acceptada; sense autorització d’execució.**

**Resultat.** Un gestor pot entrar a l'índex d'estoc, cercar un producte, llegir-ne preu i quantitat, obrir
l'edició, desar una quantitat vàlida o rebre'n l'error, consultar moviments, canviar l’estat directament a la llista o amb confirmació dins del diàleg i
tancar el diàleg amb el focus retornat; la ruta, el shell i els overlays mostren el llenguatge de la portada
en clar i fosc. Les mateixes accions, dades, permisos i destinacions continuen funcionant. Les regles
R25/R25a/R26/R27/R28/R28a i S23/S71/S72 són contractes heretats que es protegeixen en la part afectada; T84 no
els completa ni canvia l'atribució de les tasques funcionals.

**Entrada, abast i camins verificats.** Els camins abreujats de frontend parteixen de `resources/js/`.
Entrada: `/manage/stock`, restringida a gestor, component `pages/Manage/Stock/Index.vue` amb `products:
App.Http.StockLevelRow[]`. A l'índex: filtre per `name`, estat buit global, cap coincidència, llista mòbil,
taula d'escriptori, `ActiveSwitch`, botó d'edició, enllaç a moviments i enllaç a triar productor. Diàleg:
`StockEditDialog.vue` dins `components/manager/NativeDialog.vue`, camp `available`, `InputError`, botons de
desar/cancel·lar, secció de moviments i confirmació de desactivar/reactivar. El diàleg és natiu al top layer,
no un Teleport Vue; comprovar-ne superfície/focus en aquest camí real. El shell inclou `AppLayout.vue`,
components `AppSidebar*`, `Breadcrumbs.vue`, `NavMain.vue`, el menú de compte i la sheet mòbil.
`lib/pilotTheme.ts` i `composables/usePilotTheme.ts` delimiten la variant; `public/theme-colors.css` s'aplica
després de `@vite`; `resources/css/app.css` conté `.manager-stock-*`, `.manager-dialog`, `.manager-button`,
`.reference-style` i els patrons de disposició. Fitxer nou previst: manifest de casos sota
`resources/js/fixtures/`, amb nom coherent amb el visual runner. Actualitzar únicament els tests que cobreixin
la nova frontera o una regressió observada.

**Contractes i límits.** La forma de `StockLevelRow`, el format `ca-ES` de `price`/`available`, l'entrada i
sortida de cerca, les rutes de `setAvailable`, `destroy`, `reactivate` i moviments, i els errors
`available`/`domain` es mantenen. La validació visible continua acceptant fins a set dígits enters i tres
decimals, amb coma o punt; l'error de servidor segueix apareixent al mateix formulari. Mantenir l'atribució
dels estats actiu/inactiu i destructiu amb text, no només color; preservar busy/disabled, la confirmació de canvi d’estat només dins del diàleg, el tancament amb Escape i retorn de focus. Cap canvi a controlador, política, cas d'ús, base
de dades, seeder, traduccions de negoci, altres rutes o flux de comandes. La capa semàntica nova queda
limitada per una variant explícita de tema del pilot; `.pilot-theme` ja cobreix més pàgines i no identifica
per si sola T84. No remapejar `:root` globalment: una ruta no migrada i Welcome han de mantenir l'aspecte
integrat de partida. El shell pot rebre els valors de marca del pilot només dins d'aquesta variant, inclosos
els overlays portalled que realment obre; no posar el cistell ni filtres `pigment-wash` en files, controls o
icones.

**Rols i negatius.** El pilot visual complet és de gestor, inclòs gestor amb rols addicionals. Catàleg
administra fitxes de producte mitjançant les seves rutes; no obté accés a `/manage/stock` ni a l’ajust de
quantitats. Finances, recepció, productor i membre sense gestor mantenen les denegacions vigents. Per als
canvis compartits del shell, provar una ruta no migrada de membre (`Orders/Index`) i una de Catàleg
(`Catalogue/Producers/Index`), amb el seu `roleAreas` real, sense concedir-hi accions noves. Les variants de
tema no intervenen en autorització.

**Contracte de desplegament.** Variant prevista `landing-stock`, afegida al context existent; valor absent o desactivat conserva exactament el tema actual. Activació per coincidència exacta de `Manage/Stock/Index`, mai per prefix `Manage/` ni per rol sol. Sortir de la pàgina desactiva la variant al shell i a tots els portals; tornar-hi l’activa. La llista d’admissió és local al codi, sense preferència persistent ni nou framework de flags. Provar on: estoc amb diàleg/menú/sheet; off: la mateixa ruta sense variant en fixture de reversió, Welcome i les dues rutes no migrades. El responsable de T84 lliura i comprova aquesta frontera segura; el coordinador de la fase 5 és propietari de retirar-la només després de revisar tots els consumidors. Els lliuraments intermedis només amplien les rutes explícitament admeses.

**Dos camins d’estat.** L’`ActiveSwitch` de l’índex envia immediatament la petició existent: no afegir-hi confirmació. El diàleg conserva la seva confirmació pròpia. Exercitar per separat activar/desactivar, busy i errors on cada camí els exposa; cancel·lar la confirmació del diàleg no ha d’enviar cap petició. No homogeneïtzar aquests comportaments per motius visuals.

**Dades fixes del manifest.** Partir de les dades que `Manage/Stock/Index.spec.ts` ja comprova: 20 files
`stock-${index}`, `producer-1`, unitat `pot`, preu `5.00`, disponible `9.000`, primera inactiva i les altres
actives; la taula té sis columnes, i els noms accessibles «Reactiva», «Desactiva», «Edita» i «Moviments»
identifiquen el producte. Afegir casos deterministes de (A) llista buida; (B) cerca «Producte 1»; (C) consulta
sense coincidències; (D) nom molt llarg; (E) producte actiu/inactiu amb el diàleg obert. Les dades són
fixtures de revisió, no registres de producció. Comprovar que `formatEuroAmount` i `formatQuantity` presenten
les mateixes cadenes abans/després, i que els URLs i noms accessibles de les accions corresponen al producte
correcte. Afegir variants de diàleg amb error local, error servidor, processant i confirmació d'estat; el
runner visual pot simular l'estat, però la verificació d'accions ha de passar pel camí real i les proves de
contracte ja existents.

**Primer checkpoint que falla abans de passar.** Amb la ruta real i `Producte 1` actiu, obrir «Edita Producte
1» a 375 px en clar. La primera mesura ha de mostrar encara el color verd actual en un element principal o en
el diàleg; registrar valor computat i element. Aplicar el mapping semàntic acotat i tornar a mesurar: el
shell, botó principal, superfície, camp i diàleg mostren les parelles del pla; una ruta no migrada i Welcome
conserven els valors anteriors. Obrir/tancar el diàleg amb teclat i verificar focus al camp i retorn al
disparador. Aquesta és la primera observació de la ruta completa, no un test que només llegeix una classe CSS.

**Fet quan — casos Given/When/Then.**

1. **Donat** un gestor amb accés a estoc i els productes actiu/inactiu de la fixture, **quan** obre l'índex a 1280 o 375 px en clar/fosc, **aleshores** veu els mateixos noms, unitats, preus, quantitats, estats i accions en taula o llista, sense pèrdua de dades ni scroll horitzontal de pàgina; el shell, la ruta i el diàleg usen la nova paleta i `Nunito Variable`, i les xifres mantenen alineació tabular.
2. **Donat** el filtre «oli», **quan** escriu o neteja la cerca, **aleshores** les files visibles canvien com abans; amb llista buida o cap coincidència apareix el text pertinent, llegible en els dos modes. Els enllaços de moviments i de triar productor conserven destinació i permís.
3. **Donat** el botó d'edició d'un producte, **quan** s'obre el diàleg i es desa `9,125`, **aleshores** la petició segueix enviant `{ available: '9,125' }` a `setAvailable`, el resultat actualitzat es llegeix després del retorn real d'Inertia i el focus torna al botó en tancar. **Quan** no canvia `9.000`, el diàleg es tanca sense petició. **Quan** s'introdueix `9,1234` o el servidor retorna `available`/`domain`, **aleshores** el missatge existent és visible i no desplaça controls fora d'abast. Cap fixture simulada no compta com a prova de persistència.
4. **Donat** l’interruptor d’un producte a la llista, **quan** es canvia, **aleshores** s’envia directament la petició existent sense confirmació nova. **Donat** un producte actiu o inactiu dins del diàleg, **quan** s'obre «Estat i historial» i es confirma el canvi, **aleshores** la ruta i la confirmació vigents es mantenen, l'usuari identifica l'estat per text i el perill conserva el seu significat. **Quan** cancel·la, prem Escape o tanca el diàleg, **aleshores** no hi ha petició i el focus torna al disparador connectat.
5. **Donada** una pàgina encara no migrada, **quan** s'hi navega des del pilot i es torna, inclosa la sheet mòbil i el menú de compte oberts, **aleshores** no hereta per accident els tokens del pilot; el pilot recupera la seva variant. Welcome conserva el resultat de PR #385. Comprovar clar/fosc/sistema i navegació Inertia, també al top layer natiu i als portals realment usats pel shell.
6. **Donats** els casos A–E i els estats de diàleg, **quan** s'executa el manifest a 375 i 1280 px en clar/fosc i a 1024 px amb text al 200% en tots dos modes, **aleshores** les mesures de font, superfície, contrast, mida i overflow coincideixen amb el pla. Afegir 375 px/200% per al diàleg més arriscat. Text normal ≥4,5:1, text gran i límits essencials/focus ≥3:1, objectius interactius ≥44 × 44 px, focus visible, ordre de tabulació i Escape/retorn correctes, moviment reduït i font de reserva sense ruptura. Mesurar amb DOM/visual runner abans de qualsevol preview i registrar l'evidència lligada al commit/base.

### Assignació de requisits i límits

| Font vigent | Part que T84 preserva i comprova | Resta i propietari |
| --- | --- | --- |
| Pla 001, fase 1 i criteris visuals; Constitució UI | Tema del pilot, shell, llista/taula, diàleg, controls, accessibilitat i contenció | La resta de rutes correspon als lliuraments futurs de les fases 2–5; tancament global a fase 5 |
| 001 R25, S71; 003 R4 | Accions d’estat i enllaços de fitxa conservats; permisos de gestor i de Catàleg diferenciats | Creació, fitxes i classificació mantenen propietat de les implementacions acceptades T52/T53 i 003; cap reimplementació |
| 001 R27/R28, S23 | Desament de recompte per l’endpoint existent, errors i quantitat retornada sense càlcul al frontend | Entrades, ajustos signats i ledger continuen a les proves i implementació existents; T84 no declara S23 complet |
| 001 R28a, S93 | Destinació de l’enllaç a moviments i absència de canvis d’autorització | Historial, paginació i totes les variants de rol romanen propietat de T76; el seu nou estil pertany al desplegament posterior |
| 001 R25a, S72 | No alterar fitxa ni controls protegits en sortir del pilot; conservar tests existents | Unitats/variable i snapshots continuen a T53; no s’atribueix cobertura completa a T84 |

Les fonts de negoci són [stock-wallet.md](../stock-wallet.md) i [003, R4](../../003-role-management/spec.md).
No s’afegeix cap R#/S# ni es tanquen requisits amb una prova de color. T84 només afegeix l’obligació de
presentació assignada al pilot; les obligacions funcionals prèvies conserven propietari.

**Comprovacions.** Reutilitzar les proves de `Manage/Stock/Index.spec.ts`, `Manage/Stock/Edit.spec.ts`,
`components/manager/NativeDialog.spec.ts`, `composables/usePilotTheme.spec.ts` i `lib/pilotTheme.spec.ts`;
ampliar només on hi hagi risc nou de variant, focus o regressió real. Reexecutar els contractes HTTP de
`tests/Feature/Manage/StockProductsPageTest.php`, `tests/Feature/Catalogue/CatalogueAreaTest.php` i
`tests/Feature/Stock/StockMovementHistoryPagesTest.php` quan el pilot afecti entrada, permisos o enllaços.
Comprovar la negativa d'accés a `/manage/stock` per un rol de catàleg i usar una ruta `/catalogue/...` encara
no migrada com a prova de no propagació del tema: R4 de 003 concedeix gestió de registres de catàleg, no pas
moviments de quantitat exclusius del gestor. S23 conserva el recompte 10→9 i el rebuig de −10; S71 conserva la
manca d'administració del productor; S72 conserva la protecció de la unitat després d'estoc zero. Són
regressions seleccionades, no una declaració de cobertura completa dels escenaris. Format, lint i tipus segons
el diff; build només si és prerequisit de la prova enfocada. Validació completa en CI sobre la candidata.
Revisió visual explícita del pilot abans de planificar el desplegament de la resta de l'app; les mesures, la
fixture i CI són evidència, no substitueixen aquesta aprovació.

**Mida, checkpoints i propietat.** Estimació de 350–700 línies authored modificades, incertesa alta fins a
inventariar els overlays i els selectors CSS, amb fixture i tests inclosos; documentar per separat generats i
eliminacions. Cap canvi d'estat de negoci ni de contracte API; una variant semàntica temporal i la seva
propagació al shell/portals són la superfície de risc. Checkpoints interns: (1) manifest i mesura fallida
reproduïble, (2) tokens/variant acotats i una ruta no migrada sense fuga, (3) llista/taula/diàleg i accions,
(4) matrix, regressió i revisió visual. Una sola persona o equip propietari del resultat vertical; els futurs
lliuraments consumiran els tokens aprovats i retiraran la frontera quan tots els consumidors s'hagin revisat.
`DemoSeeder.php` i `DemoSeederTest.php` no canvien: el pilot no afegeix dades ni estats visibles i el demo ja
té actors de tots els rols.

## Fases posteriors, sense tasques executables encara

Després de l'aprovació visual de T84, detallar en un altre desglossament les comandes de membre,
catàleg/productor, finances/recepció/administració i accés/preferències/tancament transversal segons les fases
2–5 del pla. Els seus criteris, dependències i estimacions han de partir de l'evidència de T84 i de les rutes
vigents en aquell moment. La porta d'aprovació no bloqueja el pilot; bloqueja només estendre aquesta direcció
a altres famílies. Sense nous IDs, tiquets, bloquejos o promeses de desplegament per a aquestes fases en
aquest document.

## Evidència de preparació i estat de la porta

Fonts revisades: `origin/main` a `cfda8e70c4a5a0c56c5f8f52ca07c91905b0351f`, pla presentat a `ccbd1f4b` i
incorporat al worktree documental a `c0b349ca`, amb aprovació literal registrada. Historial combinat 001
consultat abans de reservar T84; T01–T83 i les seves dependències es conserven. No s’ha modificat ni consultat
l’estat remot de tiquets per declarar-los completats: la base de les dependències tècniques és el codi
integrat, i les notes antigues de Linear continuen sent històriques.

La descoberta Verify usa el graf `guixa`, generació `2026-11-01T14:55:31Z`. `StockEditDialog` i
`usePilotTheme` es localitzen sense truncació; `check_index_coverage` marca pla, tasques, Index i
StockEditDialog com a canviats, de manera que es llegeixen les fonts actuals. El resultat no equival a un
inventari exhaustiu de consumidors. Abans de l’execució, repetir la comprovació sobre la base candidata, en
particular per als wrappers de portals que canviïn.

La mancança inicial de `workflow.json` queda reconciliada amb fonts actuals i decisions reals. El manifest
selecciona només `landing-style-stock-pilot` (T84). La descoberta es documenta ara com a reconstrucció
d’abast de les decisions ja acceptades; la revisió de gaps és nova i limitada al pilot, no una auditoria
retrospectiva de tota 001. Els requisits de negoci es mantenen sense canvis.

[Acceptació literal de T84](../history/2026-11-11T17-11-48.918Z-no-task-f1d25139-bd9e-455b-8b11-b1728e63688a.md). El coordinador valida els prerequisits documentals amb
`node .agent-workflow/scripts/validate-feature.mjs 001-weekly-orders implementation` després de reconciliar
els hashes. El resultat es registra a l’historial; no omplir implementation/review/integration/acceptance
amb proves o aprovacions que encara no existeixen.

**Frontera tècnica:** T84 no té dependències de codi pendents. **Autorització:** l’usuari ha acceptat pla i
desglossament però ha exclòs expressament implementar i publicar tiquets. La revisió estètica del pilot
continua posterior a la seva implementació i anterior a la propagació. No s’ha executat cap test d’app ni
comprovació visual en aquesta reconciliació documental.
