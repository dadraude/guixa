const MAILPIT_URL = process.env.MAILPIT_URL ?? 'http://127.0.0.1:8025';

type MessageSummary = { ID: string };
type Message = { Text: string };

export async function clearMailbox(): Promise<void> {
    await fetch(`${MAILPIT_URL}/api/v1/messages`, { method: 'DELETE' });
}

/**
 * Polls the mailbox until a message to the address has a text matching the
 * pattern, then returns the first capture group of the newest such message.
 */
export async function readFromEmail(
    address: string,
    pattern: RegExp,
    { timeoutMs = 15_000 } = {},
): Promise<string> {
    const deadline = Date.now() + timeoutMs;

    while (Date.now() < deadline) {
        const found = await findInNewestMessages(address, pattern);

        if (found !== null) {
            return found;
        }

        await new Promise((resolve) => setTimeout(resolve, 500));
    }

    throw new Error(`No email to ${address} matched ${pattern} in time`);
}

async function findInNewestMessages(
    address: string,
    pattern: RegExp,
): Promise<string | null> {
    const search = await fetch(
        `${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent(`to:${address}`)}`,
    );
    const { messages } = (await search.json()) as {
        messages: MessageSummary[];
    };

    // Mailpit lists the newest message first.
    for (const { ID } of messages) {
        const response = await fetch(`${MAILPIT_URL}/api/v1/message/${ID}`);
        const { Text } = (await response.json()) as Message;
        const match = Text.match(pattern);

        if (match?.[1]) {
            return match[1];
        }
    }

    return null;
}
