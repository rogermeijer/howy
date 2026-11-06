import type { Mailbox, SenderAccess, SenderRule } from '@/types';

/**
 * The rules of one mailbox, as saved or as being edited: enough to say what
 * Howy does with anyone's mail there.
 */
export type MailboxRules = {
    address: string;
    domain: string;
    /** Anyone can have an address there, so domainAccess does not count. */
    publicDomain: boolean;
    domainAccess: SenderAccess;
    senders: SenderRule[];
};

export function rulesOf(mailbox: Mailbox): MailboxRules {
    return {
        address: mailbox.emailAddress,
        domain: mailbox.domain,
        publicDomain: mailbox.publicDomain,
        domainAccess: mailbox.domainAccess,
        senders: mailbox.senderRules,
    };
}

/** Every access, from the most to the least Howy may do. */
export const ACCESSES: SenderAccess[] = [
    'learn_answer',
    'learn',
    'answer',
    'ignore',
];

export const learns = (access: SenderAccess): boolean =>
    access === 'learn_answer' || access === 'learn';

export const answers = (access: SenderAccess): boolean =>
    access === 'learn_answer' || access === 'answer';

/** Which rule decided: the address listed, its domain, a colleague, or none. */
export type AccessReason =
    | 'address'
    | 'domain'
    | 'colleagues'
    | 'others'
    | 'invalid';

export const domainOf = (address: string): string =>
    address.slice(address.lastIndexOf('@') + 1).toLowerCase();

/**
 * What Howy may do with mail from this address, and which rule says so.
 * Mirrors Mailbox::access(): keep the two the same, or what the settings show
 * is not what the mailbox does.
 */
export function accessOf(
    rules: MailboxRules,
    raw: string,
): { access: SenderAccess; because: AccessReason } {
    const address = raw.trim().toLowerCase();

    if (!address.includes('@')) {
        return { access: 'ignore', because: 'invalid' };
    }

    const listed = (entry: string) =>
        rules.senders.find((rule) => rule.entry === entry)?.access;
    const domain = domainOf(address);
    const byAddress = listed(address);

    if (byAddress !== undefined) {
        return { access: byAddress, because: 'address' };
    }

    const byDomain = listed(domain);

    if (byDomain !== undefined) {
        return { access: byDomain, because: 'domain' };
    }

    return domain === rules.domain.toLowerCase() && !rules.publicDomain
        ? { access: rules.domainAccess, because: 'colleagues' }
        : { access: 'ignore', because: 'others' };
}

/** Whether Howy reads mail from this address at all. */
export function accepts(rules: MailboxRules, address: string): boolean {
    return accessOf(rules, address).access !== 'ignore';
}

/** Whether Howy may write to this address. Mirrors Mailbox::maySendTo(). */
export function maySendTo(rules: MailboxRules, address: string): boolean {
    return answers(accessOf(rules, address).access);
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

/** As MailboxValidationRules::DOMAIN_PATTERN, without the optional @. */
const DOMAIN = /^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/;
const ADDRESS =
    /^[^\s@<>()[\],;:"]+@((?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,})$/;

export type ParsedSenders = {
    /** Addresses and bare domains, lowercased, each once, in order. */
    entries: string[];
    /** What is neither an address nor a domain, as typed. */
    invalid: string[];
    /** Domains anyone can have an address on: listed address by address. */
    publicDomains: string[];
};

/**
 * Addresses and domains out of whatever is pasted or typed: one per line,
 * separated by commas, semicolons or spaces, or copied from a mail's To line
 * ("Jan Jansen" <jan@x.nl>; Piet <piet@y.nl>), where only the addresses count.
 * The server normalises the same way (MailboxValidationRules) and decides.
 */
export function parseSenders(
    text: string,
    publicDomains: string[],
): ParsedSenders {
    const parsed: ParsedSenders = {
        entries: [],
        invalid: [],
        publicDomains: [],
    };

    const tokens = text
        // A quoted name may hold commas of its own.
        .replace(/"[^"]*"/g, ' ')
        .split(/[,;\n\r]+/)
        .flatMap((segment) => {
            const bracketed = [...segment.matchAll(/<([^<>]+)>/g)].map(
                (match) => match[1],
            );

            // Name <address>: the name is not an entry.
            return bracketed.length > 0 ? bracketed : segment.split(/\s+/);
        })
        .map((token) => token.trim())
        .filter((token) => token !== '');

    for (const token of tokens) {
        const value = token
            .replace(/^mailto:/i, '')
            .replace(/^[<([']+|[>)\]'.]+$/g, '')
            .toLowerCase();
        const address = ADDRESS.exec(value);
        const domain = value.replace(/^@+/, '');

        if (address !== null || DOMAIN.test(domain)) {
            const entry = address !== null ? value : domain;

            if (address === null && publicDomains.includes(entry)) {
                parsed.publicDomains.push(entry);
            } else if (!parsed.entries.includes(entry)) {
                parsed.entries.push(entry);
            }
        } else {
            parsed.invalid.push(token);
        }
    }

    return parsed;
}
