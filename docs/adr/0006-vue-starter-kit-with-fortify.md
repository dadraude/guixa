# 0006 — Vue starter kit with Fortify, plus our own magic link

- Status: accepted
- Date: 2026-10-02

Guixa starts from Laravel's official Vue starter kit (Inertia, Vue 3 + TypeScript, Tailwind,
shadcn-vue, Wayfinder), which uses **Fortify** for authentication. We keep Fortify for
password sign-in, sign-out and rate limiting, and disable registration, two-factor
authentication and password reset. The magic link with its 6-digit code and the invitations
are written by us next to Fortify, because no Fortify feature or common package binds a code
to the requesting session the way spec 001 needs. The kit's `User` model is the sign-in
identity to which members and administrators are attached (see `CONTEXT.md`).

## Considered options

- **Blank Laravel with Inertia set up by hand:** nothing to remove, but much more setup and
  more conventions to invent and keep consistent.
- **Starter kit without Fortify:** one style of auth code, but we would rewrite password
  sign-in and rate limiting that Fortify already provides and tests.
- **A community magic-link package:** less code, but an extra dependency that would still
  need adapting for the session-bound code.

## Consequences

- Authentication lives in two places: Fortify's configuration and actions, and our own
  magic-link and invitation code. Both must share the same rate limits and session handling.
- Disabled Fortify features must also be removed from the frontend, since Wayfinder fails
  the build when a page references a route that no longer exists.
- The kit's code is ours from day one; there is no upstream to keep up with.
