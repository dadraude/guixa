# Guixa

The app of a food cooperative: households order weekly from producers, the cooperative packs
their boxes and keeps each household's balance. It replaces the cooperative's old in-house
software.

## Documentation

- [Vision](docs/vision.md): what the cooperative is, who uses the app, what is in and out of
  scope.
- [Glossary](CONTEXT.md): domain terms, with the Catalan word used in the app. Use these
  terms everywhere (code, specs, tickets).
- [Principles](docs/principles.md): technical decisions and rules for the whole project.
- [Architecture decisions](docs/adr/): ADRs, one per significant decision.
- [Specs](specs/): one folder per feature (`NNN-name/spec.md`).
- [Status](STATUS.md): what has been done and the next step.

## How we work

Spec-driven development in short sessions, with Matt Pocock's skills: grill each feature,
write and approve its spec, split it into tickets in Linear, then implement test-first. See
[CLAUDE.md](CLAUDE.md) and [ADR 0001](docs/adr/0001-sdd-workflow-with-mattpocock-skills.md).
