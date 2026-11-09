---
status: proposed
---

# Controllers, routes and pages are grouped by domain; roles are gates

Giving a role the same power over another actor's data copies a role area. DRA-251 (T03) let the
Catalogue role edit any producer's weekly products, which a Producer already does for its own. It
added 54 files and 1,813 lines (against ~450–600 estimated): `Catalogue/WeeklyProductController` and
`Catalogue/Producers/WeeklyProduct/{Create,Edit}.vue` (146 lines) mirror `Producer/ProductController`
and `Producer/Products/{Create,Edit}.vue` (109 lines), differing only in whose products they scope.
The page also moved from `/manage/...` to `/catalogue/...`, so the old controller, page, spec, links
and fixtures changed with it. `ProductPolicy` took 10 lines: authorization was never the cost.

The backend already splits by domain (`src/Catalog`, `Sales`, `Wallet`, `Stock`). Only the HTTP and
page layer splits by role: `Manage` (30 controllers, 20 pages), `Finance` (10, 5), `Producer` (5, 6),
`Catalogue` (3, 4), `Member` (2, 2), each with its own route group and `role:` middleware. The same
domain screen already exists once per role (`Finance/Baskets/Show.vue` and `Member/Baskets/Show.vue`,
see [ADR 0001](0001-calculations-in-domain-reads-through-read-repositories.md)).

We decided that controllers, routes and pages are grouped by domain, that a role is only a gate on
them, and that role areas remain only as navigation.

## Rules

1. **One controller per domain resource, not per role.** `Catalog\ProductController` serves every
   actor allowed to manage a product; there is no second controller for the same use case in another
   role folder. Folders under `app/Http/Controllers` and `resources/js/pages` are domains
   (`Catalog`, `Sales`, `Wallet`, `Stock`, `Membership`, `Ordering`), mirroring `src/`.
2. **The resource names its owner in the URL; the role is not in it.** Weekly products live at
   `/producers/{producer}/products/...`, whoever asks. A producer reaches its own at the same URL with
   its own id. The route group carries no role prefix, and moving a power between roles changes no URL.
3. **Authorization decides, in one place.** A policy or gate (`ProductPolicy`, `administer-catalogue`)
   states who may act on the resource, and the use case enforces it again on submit. Route middleware
   is only the coarse filter (`auth`, `verified`, and `role:` when a whole screen belongs to one role,
   such as Finance basket collection). A new role with the same power adds a gate rule, not a route
   group, a controller or pages.
4. **One page per screen; the server sends what the actor may do.** A page takes the resource and a
   `can` object (ADR 0001, rule 6) and shows or enables controls from it. It never assumes the actor
   is the owner. Producer and Catalogue edit a weekly product on the same pages.
5. **Role areas are navigation.** `RoleAreas`, the sidebar and the dashboard shortcuts list the links a
   role sees, with the area's label. They do not determine controllers, folders or URLs. Breadcrumbs
   and the pilot theme follow the actor's active role, not the page's folder.
6. **Role-specific workflows stay in their domain.** A screen with a single audience (Finance basket
   collection, Receiver reception) lives in its domain with one gate; it needs no shared variant.

## Considered options

- **Keep role areas and extract shared components.** What T03 did with `WeeklyProductFields.vue`:
  it removes part of the page duplication but keeps the second controller, the route group, the URL
  move and the shared-surface edits. Rejected as the standing rule.
- **One area per role with role-aware pages behind a shared controller.** Keeps role URLs, so a power
  moving between roles still changes URLs and links. Rejected.
- **Do nothing; accept the cost per new role.** The roles planned in 003 (T04, T05, T06) repeat the
  pattern, so the cost recurs.

## Consequences

- `specs/constitution.md` states the HTTP layout in "Project structure" and A5/A16; the layout clause
  and a rule for the `can` contract are added when this ADR is accepted (separate change).
- `Manage/` stops being the home of everything the manager does. Its controllers move to their domain
  one domain at a time. The Manager keeps the same powers through gates.
- URLs change when a controller moves out of a role prefix (`/manage/...`, `/catalogue/...`); old paths
  need redirects only if they are linked from outside the app.
- Page specs and `layout-rules.spec.ts` follow the pages to their new folders; the HTTP matrix test
  (`RolePermissionMatrixTest`) stays the single check that each role reaches only its screens.
- Adoption is incremental, as in ADR 0001: a domain migrates when a feature next touches it. Catalog
  goes first, because T03 created its overlap, and it is also where T04 (stock) and T05 (availability)
  land next. Not yet measured: how many controllers and pages in `Manage`, `Finance` and `Member`
  duplicate each other; that count sizes the migration and belongs in the plan that follows.
