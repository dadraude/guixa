# 0010 — SOLID and DDD layering, enforced by Pest architecture tests

- Status: accepted
- Date: 2026-10-07

Guixa follows SOLID and a DDD-style layering: `Models` and `Auth` hold the domain, `Actions`
hold the use cases, and `Http`, `Console`, `Mail` and `Providers` are the outer delivery and
infrastructure layers. Dependencies point inwards only. Reviews keep finding the same violations
by eye, so the rules that can be checked statically live in `tests/Unit/ArchitectureTest.php`.

## Considered options

- **Rules only in docs and reviews:** cheap, but the same findings keep coming back and nobody
  checks them after the first review.
- **Full hexagonal layout (`Domain/`, `Application/`, `Infrastructure/` folders per context):**
  a large move of existing code for a single-context app of 20–60 households, and it fights
  Laravel's conventions (Eloquent models, facades, service providers).
- **Architecture tests on the current namespaces (chosen):** no code moves, the rules run in CI
  with the rest of the suite, and a broken rule shows the exact class and dependency.

## Consequences

- SOLID rules that a static check can express are tests: dependency direction (`Models` does not
  use `Actions` or `Mail`; `Mail` does not use `Actions`; domain code does not use HTTP, console,
  providers or Inertia) and a size limit per class as a proxy for a single responsibility.
- Not enforced by tests, by design: that `Actions` depend on abstractions instead of the `Mail`
  facade (dependency inversion) and that each action has one public method. Pest cannot express
  them without many false positives, so they stay review rules.
- Adding a rule means a new arch test in the same file. Dropping a rule needs a reason in the
  same commit.
- Existing violations found while writing the rules were fixed, not excepted:
  `EmailChangeMail` read a constant from `RequestEmailChange`, so the action now passes the hours
  to the mail.
