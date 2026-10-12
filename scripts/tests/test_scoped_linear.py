import copy
import unittest
from scripts.scoped_linear import LinearControl, Scope, Refused, UnknownOutcome

ISSUE = '11111111-1111-4111-8111-111111111111'
STATE = '22222222-2222-4222-8222-222222222222'
OTHER = '33333333-3333-4333-8333-333333333333'
ORG = '1fc81da1-225b-4c39-baf7-31062f5833d8'
PROJECT = 'de902c96-bf85-45b6-950d-32d3694a232c'
TEAM = '4e59d964-0eca-4e08-817b-96c6c08e9282'


def issue():
    return dict(id=ISSUE, identifier='DRA-135', team={'id': TEAM},
                project={'id': PROJECT}, state={'id': STATE, 'team': {'id': TEAM}})


def read():
    return {'data': {'organization': {'id': ORG, 'name': 'Draude'},
                     'issue': issue(), 'workflowState': {'id': STATE, 'team': {'id': TEAM}}}}


class Fake:
    def __init__(self, replies):
        self.replies = replies
        self.calls = []

    def __call__(self, query, variables):
        self.calls.append((query, variables))
        reply = self.replies.pop(0)
        if isinstance(reply, Exception):
            raise reply
        return copy.deepcopy(reply)


class LinearTests(unittest.TestCase):
    def control(self, replies):
        fake = Fake(replies)
        return LinearControl(Scope(((ISSUE, 'DRA-135'),), (STATE,)), fake), fake

    def test_exact_transition(self):
        control, fake = self.control([read(), {'data': {'issueUpdate': {'success': True, 'issue': issue()}}}])
        self.assertEqual(control.transition(ISSUE, STATE)['identifier'], 'DRA-135')
        self.assertEqual(fake.calls[-1][1], {'issue': ISSUE, 'state': STATE})
        self.assertIn('input: {stateId: $state}', fake.calls[-1][0])

    def test_forbidden_requests_never_call_transport(self):
        for args in [(OTHER, STATE), (ISSUE, OTHER), ('DRA-135', STATE)]:
            control, fake = self.control([])
            with self.assertRaises(Refused):
                control.transition(*args)
            self.assertEqual(fake.calls, [])

    def test_cross_scope_response_never_mutates(self):
        paths = [('organization', 'id'), ('organization', 'name'), ('issue', 'id'),
                 ('issue', 'identifier'), ('issue', 'team', 'id'),
                 ('issue', 'project', 'id'), ('issue', 'state', 'team', 'id'),
                 ('workflowState', 'id'), ('workflowState', 'team', 'id')]
        for path in paths:
            reply = read()
            node = reply['data']
            for key in path[:-1]:
                node = node[key]
            node[path[-1]] = OTHER
            control, fake = self.control([reply])
            with self.assertRaises(Refused):
                control.transition(ISSUE, STATE)
            self.assertEqual(len(fake.calls), 1)

    def test_partial_error_and_malformed_read(self):
        for reply in [dict(read(), errors=[{'message': 'secret'}]), {'data': None},
                      {'data': dict(read()['data'], unexpected={})}, TimeoutError('secret')]:
            control, fake = self.control([reply])
            with self.assertRaises(Refused) as error:
                control.transition(ISSUE, STATE)
            self.assertNotIn('secret', str(error.exception))
            self.assertEqual(len(fake.calls), 1)

    def test_mutation_unknown_outcome_is_never_retried(self):
        wrong = issue()
        wrong['project']['id'] = OTHER
        for reply in [TimeoutError('secret'), {'errors': [{'message': 'secret'}]},
                      {'data': {'issueUpdate': {'success': False, 'issue': issue()}}},
                      {'data': {'issueUpdate': {'success': True, 'issue': wrong}}}]:
            control, fake = self.control([read(), reply])
            with self.assertRaises(UnknownOutcome) as error:
                control.transition(ISSUE, STATE)
            self.assertNotIn('secret', str(error.exception))
            self.assertEqual(len(fake.calls), 2)

    def test_each_transition_reads_fresh(self):
        reply = read()
        reply['data']['issue']['project']['id'] = OTHER
        control, fake = self.control([read(), {'data': {'issueUpdate': {'success': True, 'issue': issue()}}}, reply])
        control.transition(ISSUE, STATE)
        with self.assertRaises(Refused):
            control.transition(ISSUE, STATE)
        self.assertEqual(len(fake.calls), 3)

    def test_read_exact_issue(self):
        reply = read()
        del reply['data']['workflowState']
        control, fake = self.control([reply])
        self.assertEqual(control.read_issue(ISSUE), issue())
        self.assertEqual(fake.calls[0][1], {'issue': ISSUE})

    def test_invalid_host_scope(self):
        for issues, states in [((), (STATE,)), (((ISSUE, 'OTHER-1'),), (STATE,)),
                               (((ISSUE, 'DRA-135'),), ('invalid',))]:
            with self.assertRaises(Refused):
                Scope(issues, states)


class TransportTests(unittest.TestCase):
    def test_http_bounds_redirects_and_authentication(self):
        from unittest.mock import patch
        from scripts.scoped_linear import HTTPTransport, READ, MAX_RESPONSE
        for scheme, authorization in [('oauth', 'Bearer test-token'), ('personal', 'test-token')]:
            with patch('scripts.scoped_linear.http.client.HTTPSConnection') as factory:
                response = factory.return_value.getresponse.return_value
                response.status = 200
                response.getheader.side_effect = lambda key, default=None: 'application/json' if key == 'Content-Type' else default
                response.read.return_value = b'{"data": {}}'
                self.assertEqual(HTTPTransport('test-token', scheme)(READ, {'issue': ISSUE}), {'data': {}})
                factory.assert_called_once_with('api.linear.app', timeout=10)
                args = factory.return_value.request.call_args.args
                self.assertEqual(args[:2], ('POST', '/graphql'))
                self.assertEqual(args[3]['Authorization'], authorization)
                response.read.assert_called_once_with(MAX_RESPONSE + 1)
                factory.return_value.close.assert_called_once()
        for status, location, body in [(302, 'https://evil.example', b'{}'),
                                       (200, 'https://evil.example', b'{}'),
                                       (200, None, b'x' * (MAX_RESPONSE + 1)),
                                       (200, None, b'invalid')]:
            with patch('scripts.scoped_linear.http.client.HTTPSConnection') as factory:
                response = factory.return_value.getresponse.return_value
                response.status = status
                response.getheader.side_effect = lambda key, default=None: location if key == 'Location' else 'application/json'
                response.read.return_value = body
                with self.assertRaises(Refused):
                    HTTPTransport('test-token', 'oauth')(READ, {})
                factory.return_value.close.assert_called_once()

    def test_no_generic_operation_and_unknown_outcome_blocks_reuse(self):
        from scripts.scoped_linear import HTTPTransport
        with self.assertRaises(Refused):
            HTTPTransport('test-token', 'oauth')('mutation {issueCreate {success}}', {})
        control = LinearControl(Scope(((ISSUE, 'DRA-135'),), (STATE,)), Fake([read(), TimeoutError()]))
        with self.assertRaises(UnknownOutcome):
            control.transition(ISSUE, STATE)
        with self.assertRaises(Refused):
            control.transition(ISSUE, STATE)
        self.assertEqual(len(control._transport.calls), 2)
