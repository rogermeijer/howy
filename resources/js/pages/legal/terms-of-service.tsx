import LegalLayout, { type LegalSection } from '@/layouts/legal-layout';

// Copy lives here as English source strings, translated at render time.
// `:company`, `:kvk` and `:address` are the company details, `:email` the
// contact address as a link.

const sections: LegalSection[] = [
    {
        id: 'about',
        title: 'About these terms',
        body: [
            'Howy is offered to businesses, not to consumers. In these terms, “customer” means the company that creates an account in Howy, “users” are the people the customer gives access, and “customer data” is everything the customer and its users bring into Howy, including connected mailboxes, documents and the knowledge built from them.',
            'Our privacy policy and the data processing agreement are part of these terms. Where the customer has signed a separate agreement with us that differs from these terms, that agreement prevails.',
        ],
    },
    {
        id: 'service',
        title: 'The service',
        body: [
            "Howy turns the mail and documents a customer shares with it into a searchable knowledge base for that customer, in which every piece of knowledge links back to its source. Where the mailbox's rules allow it, Howy can also answer questions in a mail thread.",
            'We keep improving Howy, so features may change. We will not remove an essential feature without telling administrators in advance.',
        ],
    },
    {
        id: 'beta',
        title: 'Private beta',
        body: [
            'Howy is currently in private beta, and access is by invite. During the beta Howy may contain errors, features may change or disappear, and availability is not guaranteed.',
            'Using Howy during the beta is free, unless we agreed otherwise in writing. We will let you know well before any fees apply, and you then decide whether to continue.',
        ],
    },
    {
        id: 'accounts',
        title: 'Accounts and administrators',
        body: [
            'The person who creates an account confirms that they are authorized to accept these terms on behalf of the customer.',
            "The customer's administrators manage its users, its mailboxes and what is added to its knowledge base. The customer is responsible for everything that happens under its account and for keeping sign-in details secret. We strongly recommend turning on two-factor authentication.",
            'Suspect someone has unauthorized access? Let us know right away at :email.',
        ],
    },
    {
        id: 'use',
        title: 'Your responsibilities',
        body: [
            'The customer only connects mailboxes and adds content it is entitled to use, and informs its employees and the people it corresponds with that their mail is processed by Howy, as the GDPR requires.',
            'The customer will not use Howy to:',
            [
                'break the law or infringe the rights of others;',
                'send spam or other unsolicited mail;',
                "spread malware, or try to access our systems or another customer's data;",
                'copy, reverse engineer, overload or resell Howy without our permission.',
            ],
            'We may suspend access if these terms are seriously breached, or when needed to protect Howy, other customers or third parties. Where possible, we warn the customer first.',
        ],
    },
    {
        id: 'data',
        title: 'Your data',
        body: [
            'Customer data belongs to the customer. We only use it to provide Howy to the customer, as described in our privacy policy and the data processing agreement. We do not use it to train AI models, and we do not sell it.',
            'The customer grants us the rights needed to store, process and show customer data, for that purpose only.',
            'For customer data the customer is the controller and we are its processor. The customer is responsible for having a lawful basis for the processing.',
        ],
    },
    {
        id: 'ai',
        title: 'AI and answers',
        body: [
            'Howy uses AI, and AI can be incomplete or wrong. Nothing new is added to the knowledge base until an administrator has approved it, and the customer remains responsible for checking knowledge and answers before relying on them.',
            'Replies Howy sends in a mail thread are sent on behalf of the customer, from its own mailbox, and only to senders its rules allow. The customer is responsible for those rules and for the replies sent under them.',
        ],
    },
    {
        id: 'third-parties',
        title: 'Services of others',
        body: [
            'Howy works with services of others, such as Gmail. Their own terms apply to your use of them. We are not responsible for their availability, or for changes they make that affect Howy.',
        ],
    },
    {
        id: 'fees',
        title: 'Fees and payment',
        body: [
            'Once the beta or the free period agreed with the customer ends, the fees on our website or in our quote apply: per user per month, billed in advance. All amounts exclude VAT.',
            'We may change our fees once a year, announced at least 30 days in advance. If the customer does not agree, it may end the agreement before the change takes effect.',
        ],
    },
    {
        id: 'availability',
        title: 'Availability and support',
        body: [
            'We do our best to keep Howy available and secure, but cannot guarantee that it works without interruptions or errors. Where we can, we plan maintenance at quiet times.',
            'Questions and problems can be sent to :email.',
        ],
    },
    {
        id: 'intellectual-property',
        title: 'Intellectual property',
        body: [
            'All rights to Howy itself, such as the software, design, name and logo, remain ours. For the duration of the agreement, the customer receives a non-exclusive, non-transferable right to use Howy for its own business.',
            'We may use feedback the customer gives us freely to improve Howy.',
        ],
    },
    {
        id: 'liability',
        title: 'Liability',
        body: [
            'Our total liability for direct damage is limited to the fees the customer paid for Howy in the 12 months before the event that caused the damage. During the beta or another free period, it is limited to €500.',
            'We are not liable for indirect damage, such as lost profit, lost savings, loss of data or damage from business interruption, nor for decisions made based on knowledge or answers from Howy.',
            'These limitations do not apply in case of intent or gross negligence on our part.',
        ],
    },
    {
        id: 'termination',
        title: 'Duration and termination',
        body: [
            'The agreement runs until it is ended. During the beta or another free period, either party may end it at any time. A paid subscription runs for the agreed period and renews automatically, unless the customer ends it before the current period ends.',
            'We may end the agreement with immediate effect if the customer seriously breaches these terms or goes bankrupt.',
            'After the agreement ends, the customer can ask us for an export of its data for 30 days. After that, we delete customer data as described in our privacy policy.',
        ],
    },
    {
        id: 'changes',
        title: 'Changes to these terms',
        body: [
            'We may change these terms. We announce important changes to administrators at least 30 days before they take effect. If the customer does not agree, it may end the agreement before then; using Howy after that date counts as accepting the new terms.',
        ],
    },
    {
        id: 'law',
        title: 'Governing law and disputes',
        body: [
            'Dutch law applies to these terms and to every agreement with us. Disputes are submitted exclusively to the competent court in Amsterdam.',
            'If a provision of these terms is invalid, the rest remains in force, and we replace that provision with a valid one that comes as close to it as possible.',
        ],
    },
    {
        id: 'contact',
        title: 'Contact',
        body: [
            ':company, :address, registered with the Dutch Chamber of Commerce (KvK) under number :kvk.',
            'Email: :email.',
        ],
    },
];

export default function TermsOfService() {
    return (
        <LegalLayout
            title="Terms of service"
            intro="These terms apply to every use of Howy, a service of :company. By creating an account or using Howy for your company, you accept them on its behalf."
            updatedAt="2026-11-10T12:00:00Z"
            sections={sections}
        />
    );
}
