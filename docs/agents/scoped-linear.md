# Host-scoped Linear transitions

`scripts/scoped_linear.py` gives the trusted coordinator two operations: read an approved issue
and move it to an approved state. Importing the module does nothing. It is not registered as an
MCP server or connected to the batch adapter, and merging it grants no service authority.

The coordinator constructs an immutable `Scope` with one to four exact issue UUID/identifier pairs
and the allowed destination state UUIDs. `LinearControl.read_issue` and `transition` take UUIDs;
they cannot accept arbitrary GraphQL, edit descriptions, create issues or publish status updates.
The scope is supplied by the trusted coordinator, never reconstructed from a worker's response.
Keep Done outside a ticket's scope while its acceptance criteria remain outstanding.

The implementation pins organization Draude (`1fc81da1-225b-4c39-baf7-31062f5833d8`), Workflow
autonomy project (`de902c96-bf85-45b6-950d-32d3694a232c`) and team Draude
(`4e59d964-0eca-4e08-817b-96c6c08e9282`). A fresh read checks the organization, issue UUID and
identifier, project, team and destination state's team before each mutation. The returned issue
must still match, including the new state. Other projects require a separate reviewed control.

`HTTPTransport` accepts an explicitly supplied host token and authentication scheme (`oauth` or
`personal`). It uses only HTTPS `api.linear.app/graphql`, refuses redirects, bounds response bytes
and timeouts, and exposes only the three fixed query documents used by the control. It neither
reads stored OAuth credentials nor retries mutations. Do not pass its token or transport to workers.
Use the control as the authorization boundary; its underlying transport is a trusted implementation
seam, not an independently scoped worker API.

Any failed or ambiguous mutation response raises `UnknownOutcome` and blocks further operations
on that control instance. The coordinator must inspect the service externally and reconcile before
constructing another instance. A read refusal does not imply a previous mutation was rolled back.
Linear has no atomic project-membership precondition for this operation: an issue can move between
the preflight read and update. This slice therefore requires supervision and does not establish
unattended service isolation against concurrent administrators.

Verification uses fake responses and mocked HTTPS boundaries, including cross-project/team/workspace
attempts, unapproved issues/states, HTTP and GraphQL errors, response bounds and ambiguous outcomes.
No live Linear token or mutation is exercised by these tests. DRA-135/DRA-144 remain open for native
worker exposure, coordinator integration and supervised live acceptance. Product-question creation
and deduplication remain part of DRA-144's outstanding integration, not an operation in this module.

The transport follows the official [Linear GraphQL guide](https://linear.app/developers/graphql)
and [data access documentation](https://linear.app/developers/sdk-fetching-and-modifying-data).
