# Preflight

Settle what a session needs from the machine before any task is admitted. The profile names one
read-only preflight command; it reports each check as `ok` or `FAIL` with a fix line and writes timestamped
evidence bound to the base, which the selector accepts for 15 minutes.

## In a session

Run it first and pass its output to the selector. Never repair anything: a failed check makes the
affected tasks `needs-environment`, and the report carries each fix line. Never install, reconfigure or
relocate to get past a check.

## With the user

Run it once per machine, and again when a session reports a failed check. Settle each `FAIL` with the
user one question at a time and rerun until it is ready. Show the exact rules before applying any host
permission change from the adapter. Installing runtimes, starting services and authenticating tools are
the user's to do: give the fix line and wait. Preflight never touches the tracker, creates a branch or
starts a task.
