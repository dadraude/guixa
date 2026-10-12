"""Inactive trusted-host Linear control; never load this into a worker runtime.

The host supplies immutable approved UUIDs and a transport holding its own token.
There is no worker policy file, arbitrary GraphQL operation, creation or retry API.
Linear does not provide an atomic scope precondition: a concurrent issue move after
our fresh read remains a live-service limitation requiring host supervision.
"""

from dataclasses import dataclass
import http.client
import json
import re
from uuid import UUID

ENDPOINT = 'https://api.linear.app/graphql'
ORGANIZATION = '1fc81da1-225b-4c39-baf7-31062f5833d8'
PROJECT = 'de902c96-bf85-45b6-950d-32d3694a232c'
TEAM = '4e59d964-0eca-4e08-817b-96c6c08e9282'
MAX_RESPONSE = 128 * 1024
ISSUE_FIELDS = 'id identifier team {id} project {id} state {id team {id}}'
READ = 'query ScopedIssue($issue: String!) { organization {id name} issue(id: $issue) {' + ISSUE_FIELDS + '} }'
PREPARE = 'query ScopedTransition($issue: String!, $state: String!) { organization {id name} issue(id: $issue) {' + ISSUE_FIELDS + '} workflowState(id: $state) {id team {id}} }'
UPDATE = 'mutation ScopedTransition($issue: String!, $state: String!) { issueUpdate(id: $issue, input: {stateId: $state}) {success issue {' + ISSUE_FIELDS + '}} }'


class Refused(ValueError):
    """Request or read failed closed; no mutation was attempted."""


class UnknownOutcome(Refused):
    """A mutation was attempted; reconcile externally before any further action."""


def uuid(value):
    try:
        return isinstance(value, str) and str(UUID(value)) == value
    except (ValueError, AttributeError):
        return False


@dataclass(frozen=True)
class Scope:
    """Explicit host-confirmed issue UUID/identifier pairs and allowed state UUIDs."""

    issues: tuple
    states: tuple

    def __post_init__(self):
        if (type(self.issues) is not tuple or type(self.states) is not tuple
                or not 1 <= len(self.issues) <= 4 or not 1 <= len(self.states) <= 16):
            raise Refused('Invalid host scope')
        for pair in self.issues:
            if (type(pair) is not tuple or len(pair) != 2 or not uuid(pair[0])
                    or not isinstance(pair[1], str) or not re.fullmatch(r'DRA-[1-9][0-9]*', pair[1])):
                raise Refused('Invalid approved issue')
        if (len(dict(self.issues)) != len(self.issues)
                or len({pair[1] for pair in self.issues}) != len(self.issues)
                or any(not uuid(state) for state in self.states)
                or len(set(self.states)) != len(self.states)):
            raise Refused('Invalid host scope')


class HTTPTransport:
    """Standard-library HTTPS, fixed endpoint, no redirects, retries or token discovery.

    Host passes a token explicitly and selects oauth or personal authentication.
    Do not pass this object or its token to a worker or serialize it into a manifest.
    """

    def __init__(self, token, scheme, timeout=10):
        if (not isinstance(token, str) or not token or any(c.isspace() for c in token)
                or scheme not in ('oauth', 'personal') or not 0 < timeout <= 30):
            raise Refused('Invalid host authentication or timeout')
        self._authorization = ('Bearer ' if scheme == 'oauth' else '') + token
        self._timeout = timeout

    def __call__(self, query, variables):
        if query not in (READ, PREPARE, UPDATE):
            raise Refused('Unsupported operation')
        connection = http.client.HTTPSConnection('api.linear.app', timeout=self._timeout)
        try:
            connection.request('POST', '/graphql', json.dumps({'query': query, 'variables': variables}),
                               {'Authorization': self._authorization, 'Content-Type': 'application/json'})
            response = connection.getresponse()
            if response.status != 200 or response.getheader('Location') is not None:
                raise Refused('Unexpected Linear HTTP response')
            if response.getheader('Content-Type', '').split(';')[0].strip() != 'application/json':
                raise Refused('Unexpected Linear content type')
            body = response.read(MAX_RESPONSE + 1)
            if len(body) > MAX_RESPONSE:
                raise Refused('Linear response exceeds bound')
            return json.loads(body)
        except Exception:
            raise Refused('Linear transport failed') from None
        finally:
            connection.close()


class LinearControl:
    """Two fixed operations: read an approved issue, transition to an approved state."""

    def __init__(self, scope, transport):
        if not isinstance(scope, Scope) or not callable(transport):
            raise Refused('Invalid host control')
        self._scope = scope
        self._transport = transport
        self._uncertain = False

    def _request(self, query, variables, keys):
        try:
            response = self._transport(query, variables)
            if (type(response) is not dict
                    or ('errors' in response and response['errors'] != [])
                    or set(response) - {'data', 'errors', 'extensions'}
                    or type(response.get('data')) is not dict
                    or set(response['data']) != keys):
                raise Refused('Unexpected Linear response')
            return response['data']
        except Exception:
            raise Refused('Linear operation failed') from None

    def _approved(self, issue):
        if self._uncertain:
            raise Refused('Previous mutation outcome requires external reconciliation')
        if not isinstance(issue, str) or issue not in dict(self._scope.issues):
            raise Refused('Issue outside approved host scope')

    def _issue(self, issue, expected, state=None):
        if (type(issue) is not dict or set(issue) != {'id', 'identifier', 'team', 'project', 'state'}
                or issue['id'] != expected or issue['identifier'] != dict(self._scope.issues)[expected]
                or issue['team'] != {'id': TEAM} or issue['project'] != {'id': PROJECT}
                or type(issue['state']) is not dict or set(issue['state']) != {'id', 'team'}
                or not uuid(issue['state']['id']) or issue['state']['team'] != {'id': TEAM}
                or (state is not None and issue['state']['id'] != state)):
            raise Refused('Issue response outside approved scope')
        return issue

    def _read(self, issue, state=None):
        self._approved(issue)
        variables = {'issue': issue}
        keys = {'organization', 'issue'}
        if state is not None:
            if not isinstance(state, str) or state not in self._scope.states:
                raise Refused('State outside approved host scope')
            variables['state'] = state
            keys.add('workflowState')
        data = self._request(PREPARE if state is not None else READ, variables, keys)
        if data['organization'] != {'id': ORGANIZATION, 'name': 'Draude'}:
            raise Refused('Unexpected Linear workspace')
        result = self._issue(data['issue'], issue)
        if state is not None and data['workflowState'] != {'id': state, 'team': {'id': TEAM}}:
            raise Refused('Unexpected target state')
        return result

    def read_issue(self, issue):
        return self._read(issue)

    def transition(self, issue, state):
        self._read(issue, state)
        try:
            data = self._request(UPDATE, {'issue': issue, 'state': state}, {'issueUpdate'})
            payload = data['issueUpdate']
            if (type(payload) is not dict or set(payload) != {'success', 'issue'}
                    or payload['success'] is not True):
                raise Refused('Unexpected mutation result')
            return self._issue(payload['issue'], issue, state)
        except Exception:
            self._uncertain = True
            raise UnknownOutcome('Linear mutation outcome requires external reconciliation') from None
