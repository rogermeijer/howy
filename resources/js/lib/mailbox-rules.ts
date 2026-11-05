import type { Mailbox, SendPolicy } from '@/types';

/**
 * The rules of one mailbox, as saved or as being edited: enough to say whose
 * mail Howy reads there and who may get a reply.
 */
export type MailboxRules = {
    address: string;
    domain: string;
    policy: SendPolicy;
    whitelist: string[];
    blacklist: string[];
    repliesEnabled: boolean;
};

export function rulesOf(mailbox: Mailbox): MailboxRules {
    return {
        address: mailbox.emailAddress,
        domain: mailbox.domain,
        policy: mailbox.sendPolicy,
        whitelist: mailbox.sendWhitelist,
        blacklist: mailbox.sendBlacklist,
        repliesEnabled: mailbox.repliesEnabled,
    };
}

/**
 * A list as typed in the rules dialog, split the way the server splits it
 * (MailboxValidationRules::normalizeSendList).
 */
export function parseList(value: string): string[] {
    return [
        ...new Set(
            value
                .split(/[\s,;]+/)
                .map((entry) => entry.trim().toLowerCase())
                .filter((entry) => entry !== ''),
        ),
    ];
}

const domainOf = (address: string): string =>
    address.slice(address.lastIndexOf('@') + 1).toLowerCase();

/**
 * An entry is a full address (jan@x.nl) or a domain (x.nl or @x.nl).
 */
const listMatches = (entries: string[], address: string): boolean =>
    entries.some((raw) => {
        const entry = raw.trim().toLowerCase();

        return (
            entry === address || entry.replace(/^@+/, '') === domainOf(address)
        );
    });

/**
 * Whether the rules let this address in. Mirrors Mailbox::accepts(): keep
 * the two the same, or the explanation tells something the mailbox does not.
 */
export function accepts(rules: MailboxRules, raw: string): boolean {
    const address = raw.trim().toLowerCase();

    if (!address.includes('@')) {
        return false;
    }

    switch (rules.policy) {
        case 'whitelist':
            return listMatches(rules.whitelist, address);
        case 'domain':
            return (
                domainOf(address) === rules.domain.toLowerCase() &&
                !listMatches(rules.blacklist, address)
            );
        case 'always':
            return !listMatches(rules.blacklist, address);
    }
}

/**
 * Whether Howy may reply to this address. Mirrors Mailbox::maySendTo().
 */
export function maySendTo(rules: MailboxRules, address: string): boolean {
    return rules.repliesEnabled && accepts(rules, address);
}

/**
 * A list entry as someone who would write from it: an address stays, a
 * domain gets a name in front.
 */
export function addressFor(entry: string, name: string): string {
    return entry.includes('@') && !entry.startsWith('@')
        ? entry
        : `${name}@${entry.replace(/^@+/, '')}`;
}
