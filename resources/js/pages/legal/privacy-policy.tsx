import LegalLayout, { type LegalSection } from '@/layouts/legal-layout';

// Copy lives here as English source strings, translated at render time.
// `:company`, `:kvk` and `:address` are the company details, `:email` the
// contact address as a link.

const sections: LegalSection[] = [
    {
        id: 'who-we-are',
        title: 'Who we are',
        body: [
            ':company, registered with the Dutch Chamber of Commerce (KvK) under number :kvk, at :address, is responsible for the processing described in this policy, unless stated otherwise below.',
            'Questions about privacy, or a request about your data? Email us at :email.',
        ],
    },
    {
        id: 'roles',
        title: 'Your company decides about its own data',
        body: [
            "Howy is used by companies. The data a company puts into Howy, such as the mail in its connected mailboxes, its documents and the knowledge base built from them, belongs to that company. For that data the company is the controller and we are its processor: we only process it on the company's instructions, under a data processing agreement.",
            "Is your mail or a document of yours in a company's Howy account, for example because you emailed someone who works there? Then that company decides what happens to it. Please contact the company first; we will help it answer your request.",
            'For the data we need to run Howy itself, such as user accounts, invite requests and this website, we are the controller.',
        ],
    },
    {
        id: 'data',
        title: 'What we process',
        body: [
            [
                'Account details: your name, email address, password (stored only as a one-way hash), language, time zone, two-factor authentication settings if you turn it on, and the accounts you belong to.',
                'Company details: the company name, the domain it sends mail from, and its languages and time zone.',
                'Invite requests: the email address and language you leave when you ask for an invite, and the invite code we send you.',
                'Mail: once an administrator connects a Gmail mailbox, the messages that arrive in its inbox and the older messages someone in the team chooses to import, with their senders, recipients, subject, content and attachments.',
                'Documents: the files your team uploads or shares as mail attachments, and what Howy derives from them, such as text, summaries, facts, folders and search indexes.',
                'Technical data: log entries such as the IP address, browser and time of a request, and per account how much AI is used for each processing step.',
            ],
        ],
    },
    {
        id: 'purposes',
        title: 'Why we process it, and on what basis',
        body: [
            [
                "To provide Howy to your company: performance of our contract with your company (Article 6(1)(b) GDPR) and, for your company's data, its instructions to us as processor.",
                'To create and secure your user account, sign you in and send the messages the service needs, such as email verification, password resets and invitations: performance of the contract.',
                'To handle your invite request during the private beta: your request, and our legitimate interest in running the beta (Article 6(1)(f) GDPR).',
                'To keep Howy secure, prevent abuse and fix errors: our legitimate interest in a safe and working service.',
                'To meet legal obligations, such as tax and accounting rules: legal obligation (Article 6(1)(c) GDPR).',
            ],
            'We do not sell personal data, we do not use it for advertising, and we do not build profiles of the people in your mail.',
        ],
    },
    {
        id: 'gmail',
        title: 'Gmail and Google user data',
        body: [
            'An administrator can connect a Gmail mailbox, such as a shared support@ address. Howy then asks Google for permission to read the mailbox (gmail.readonly), to send mail from it (gmail.send), and for its email address (openid and email).',
            'Howy uses this access only to:',
            [
                "store the new messages that arrive in the mailbox's inbox;",
                'import older messages that someone in your team selects;',
                'turn those messages and their attachments into knowledge for your company;',
                "reply in a thread to senders that the mailbox's rules allow Howy to answer.",
            ],
            "Howy's use and transfer to any other app of information received from Google APIs will adhere to the Google API Services User Data Policy, including the Limited Use requirements.",
            'We do not use Google user data for advertising, we do not sell it, and we do not use it to develop, improve or train generalized AI or machine-learning models. Nobody at Howy reads it, unless you give us permission for a specific message, it is needed for security, or the law requires it.',
            "Mailbox access tokens are stored encrypted. Disconnecting a mailbox in Settings revokes Howy's access at Google and deletes the tokens; you can also revoke access from the security settings of your Google Account. Mail Howy already stored stays in the knowledge base, because knowledge links back to it, until your company's account is closed or your company asks us to delete it.",
        ],
    },
    {
        id: 'ai',
        title: 'How Howy uses AI',
        body: [
            'Howy uses AI models to read mail and documents, write summaries, extract facts, build search indexes, file knowledge into folders, translate it into the language of your knowledge base and write replies.',
            'To do that, the text and images involved are sent to our AI providers, OpenAI and Anthropic, through their business APIs. Under their terms, data sent through these APIs is not used to train their models.',
            'Every AI request goes through one place in Howy, which decides what is sent and records how much is used per account. To find errors, the content of AI requests may be kept for at most 30 days; after that, only the usage figures remain.',
            'AI makes mistakes. That is why nothing new becomes part of your knowledge base until an administrator of your company has approved it.',
        ],
    },
    {
        id: 'sharing',
        title: 'Who we share data with',
        body: [
            'We work with the following service providers. Each processes data only on our instructions, under a data processing agreement:',
            [
                'Laravel Forge, and a virtual private server (VPS) in the European Union: hosting of the app, its database and its files.',
                'OpenAI and Anthropic: AI processing, as described above.',
                'Resend: sending transactional email, such as email verification, password resets, invitations and notifications.',
                'Google: the Gmail API and Google Cloud Pub/Sub, for the mailboxes your company connects.',
            ],
            "Beyond these, we only share data when the law requires it, or with your company's permission.",
        ],
    },
    {
        id: 'transfers',
        title: 'Data outside the European Union',
        body: [
            "Your data is stored on servers in the European Union. Some of our providers, such as OpenAI, Anthropic, Resend, Laravel Forge and Google, are based in the United States, so data may also be processed there. In that case we rely on the EU-U.S. Data Privacy Framework or on the European Commission's Standard Contractual Clauses, with additional safeguards where needed.",
        ],
    },
    {
        id: 'retention',
        title: 'How long we keep data',
        body: [
            [
                "Your company's mail, documents and knowledge: as long as your company's account exists. After the account is closed we keep them for 30 days, so they can still be exported on request, and then delete them. Backups are overwritten within a further 60 days.",
                "Your user account: until you delete it from your profile, or until your company's account is closed.",
                'Invite requests: until the invite is used, and at most 12 months after the request.',
                'Content of AI requests: at most 30 days. Usage figures: as long as the account exists.',
                'Technical logs: no longer than needed for security and troubleshooting.',
                'Financial records: 7 years, as Dutch tax law requires.',
            ],
        ],
    },
    {
        id: 'security',
        title: 'How we protect data',
        body: [
            "Every connection to Howy is encrypted. Passwords are stored as one-way hashes and mailbox access tokens are encrypted. Each company's data is kept strictly apart from that of every other company, enforced in the application and tested with every change. Every user can turn on two-factor authentication, and only the people who need it have access to our servers.",
        ],
    },
    {
        id: 'cookies',
        title: 'Cookies',
        body: [
            'Howy only uses functional cookies: to keep you signed in, to protect forms against cross-site request forgery, to remember your language and, if you choose to stay signed in, to remember that. We do not use analytics, tracking or advertising cookies.',
        ],
    },
    {
        id: 'rights',
        title: 'Your rights',
        body: [
            'Under the GDPR you have the right to access your personal data, to have it corrected or deleted, to restrict or object to its processing, and to receive it in a machine-readable format. Where we process data based on your consent, you can withdraw it at any time.',
            "To exercise these rights, email :email. We respond within one month. For data in a company's Howy account, we pass your request on to that company, since it decides about that data.",
            'You also have the right to lodge a complaint with the Dutch Data Protection Authority (Autoriteit Persoonsgegevens), or with the supervisory authority in your own country.',
        ],
    },
    {
        id: 'changes',
        title: 'Changes to this policy',
        body: [
            'We may update this policy as Howy changes. We inform administrators about important changes by email before they take effect. The date at the top shows when this policy last changed.',
        ],
    },
];

export default function PrivacyPolicy() {
    return (
        <LegalLayout
            title="Privacy policy"
            intro="This policy explains which personal data Howy processes, why, and what your rights are. It applies to our website, the Howy app and the mailboxes connected to it."
            updatedAt="2026-11-10T12:00:00Z"
            sections={sections}
        />
    );
}
