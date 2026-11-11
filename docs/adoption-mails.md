# Adoption mails

The mails that help people start with Howy and keep using it: a welcome, how it works, a thank-you for the
first contribution, a nudge, and later a weekly and monthly rhythm. They grew out of a proposal for 13 mails
(kept as received in [`docs/adoption-mails/proposal/`](adoption-mails/proposal/), with its flow overview in
`mailflow.html`). This file says what is built, what is parked, and what each parked mail still needs.

## Status

| Step | What                                                                                                       | State                                  |
| ---- | ---------------------------------------------------------------------------------------------------------- | -------------------------------------- |
| 1    | One mail frame, every mail designed (live, system and parked), preview at `/admin/mails`                   | **Built, waiting for design sign-off** |
| 2    | Colleagues and mailbox rules (`Colleague`, `MailboxRule`, replacing `mailbox_senders` and `domain_access`) | Planned                                |
| 3    | `HowyMailer`, `OutboundMail` (mail log, pause queue, reply recognition)                                    | Planned                                |
| 4    | The account's Live switch, and 01                                                                          | Planned                                |
| 5    | 02 and 06                                                                                                  | Planned                                |
| 6    | 04                                                                                                         | Planned                                |
| 7    | Links without login (rating 05, adjusting 04, unsubscribing)                                               | Planned                                |

Until step 1 is signed off nothing new is sent. What is live on the branch: the existing mails (answers, not
found, conflict, beta invite, mailbox needs reconnecting, password reset, email verification) in the new
frame and voice.

## Where it lives

- `App\Mail\Content\MailContent`: a mail as a list of blocks (heading, paragraph, list, rules, callout,
  button, mockup, stats, ranking, checklist, quote, choices, signoff). Each block keeps its data for its view
  and its plain text, so the HTML and the text part never drift apart. `**bold**` is the only markup.
- `App\Mail\Content\MailRenderer`: renders the HTML (`resources/views/mail/content.blade.php`, a view per block
  in `resources/views/mail/blocks/`) and the text.
- `resources/views/components/mail/frame.blade.php`: the frame of every mail Howy sends, including the replies
  in `resources/views/mail/cc/`.
- `App\Mail\Flow\FlowMails`: 01, 02, 04, 06 and the new-link mail. `App\Mail\Flow\FlowRecipient` is who it is for.
- `App\Mail\SystemMails`: the beta invite, mailbox needs reconnecting, password reset, email verification.
  The last two replace Fortify's mails in `FortifyServiceProvider::configureMail()`.
- `App\Mail\Parked\ParkedMails`: the designed mails nothing sends yet.
- `App\Mail\Previews\MailPreviews` and `/admin/mails` (super-admins): every mail with sample data, per
  language, per kind of access, light or dark, as HTML or text, with a test send to yourself.
- `config/adoption.php`: every interval, from the environment.

## Design rules

- **No images, no attachments, nothing remote.** The wordmark is type with a lime dot, bullets are text,
  buttons are table cells with a background. There is no web font link: Plus Jakarta Sans where the reader
  has it, else the system font. A mail looks the same where images are blocked and never shows a paperclip.
- **Tokens from `resources/css/app.css`.** `cc-bg`, `cc-raised`, `cc-ink`, `cc-muted`; `cc-accent` only as a
  fill, `cc-accent-deep` for links; `cc-highlight` to mark something in a mockup.
- **Light and dark.** Light is the base. The `hm-*` classes switch to the dark tokens under
  `prefers-color-scheme: dark` (Apple Mail, iOS, Outlook for Mac) and Outlook.com's `data-ogsc`/`data-ogsb`.
  Gmail and the Outlook apps invert colours themselves; with no image in the mail that stays readable.
- **Tables and inline styles**, with a few classes for dark mode and phones (`hm-stack`, `hm-wide`).
- **One voice.** Howy writes, in the first person: "Hi Sanne,", short sentences, signed "Howy" with the
  account and address under it. The footer says why the reader got the mail, with the privacy policy and,
  for flow mails, the way out.
- **Always a text part**, from the same blocks.
- **Only what the reader's access allows.** Someone Howy only learns from is never told to ask it something,
  and gets a note saying so; someone it only answers is never told to copy it in.

## The flow

Who: everyone who mails a Howy mailbox and does not come out at _ignore_, once they are a colleague
(step 2). Day 0 is per person: the moment their welcome went out. There is no company-wide start date;
the account's Live switch is the start signal.

| #   | Mail              | When                                                                                                                           | To                                   | Channel                     |
| --- | ----------------- | ------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------ | --------------------------- |
| 01  | Welcome           | Going live (admin confirms the list); adding a colleague (tick "Send welcome mail"); a colleague's first mail after going live | The colleague                        | Howy mailbox                |
| 02  | How it works      | 01 + `ADOPTION_HOW_IT_WORKS_AFTER_WORKDAYS` (2)                                                                                | The colleague                        | Howy mailbox                |
| 04  | Saved!            | The first mail Howy files something from, after 01. Not on the mail that triggered 01                                          | The sender only, never in the thread | Howy mailbox                |
| 05  | Answer (+ rating) | Every question Howy answers                                                                                                    | The asker, others in Cc as now       | Howy mailbox, in the thread |
| 06  | What do you know? | 01 + `ADOPTION_NUDGE_AFTER_WORKDAYS` (5) with nothing received since 01; only for someone Howy learns from                     | The colleague                        | Howy mailbox                |

Rules:

- **Nothing before Live.** Answers to questions work regardless.
- **The mailbox pauses everything.** When it is not active, flow mails wait and go once it is connected
  again. There is no fallback to another sender.
- **Working days** are Monday to Friday in the account's timezone; public holidays are not counted yet.
  Scheduled mails go between `ADOPTION_SEND_FROM_HOUR` and `ADOPTION_SEND_UNTIL_HOUR`. A welcome sent by
  hand goes at once.
- **Unsubscribing stops flow mails, never answers.** A colleague can also be switched off by an admin
  ("Receives Howy mails").
- **Every mail Howy sends carries a signed reference** in its `Message-ID` (and `References`), so a reply to
  it is recognised as a reply to Howy (step 3). Later this lets Howy answer questions about Howy itself.

| Variable                               | Default | Meaning                                            |
| -------------------------------------- | ------- | -------------------------------------------------- |
| `ADOPTION_HOW_IT_WORKS_AFTER_WORKDAYS` | 2       | Working days between 01 and 02                     |
| `ADOPTION_NUDGE_AFTER_WORKDAYS`        | 5       | Working days between 01 and 06                     |
| `ADOPTION_SEND_FROM_HOUR`              | 9       | Start of the sending window, account's local time  |
| `ADOPTION_SEND_UNTIL_HOUR`             | 17      | End of the sending window                          |
| `MAIL_LINK_HOURS`                      | 48      | How long a link in a mail works without logging in |

## Privacy

The colleagues page shows admins activity per person (last mail, contributions, questions). That departs
from the proposal, which kept individual figures from managers. So no mail may promise "no monitoring" or
"individual figures are never visible". What mails may say, and do: Howy only sees mails it is put in Cc or
To on.

Left out of the proposal's copy until checked: "100% GDPR-proof, stored in the EU" (AI processing runs at
OpenAI), the statistics with sources in 01, 02 and 06 (25% of the week searching, 42% of knowledge with one
person, 53 mails a day, six months to get up to speed) and the note on the Belgian DPA guideline about
closing a leaver's mailbox. Each can come back once confirmed.

## Before sign-off

- Send each mail to yourself from `/admin/mails` and look at it in Gmail (web, iOS, Android), Outlook (new and
  classic on Windows) and Apple Mail, light and dark: no paperclip, no "load remote content" prompt,
  readable, buttons easy to tap, a sensible text part.
- Spike for step 3: send through the Gmail API with our own `Message-ID` and `References`, reply from Gmail,
  Outlook and Apple Mail, and check what comes back in `In-Reply-To` and `References`.

## Parked mails

Designed in `App\Mail\Parked\ParkedMails`, shown under _Parked_ at `/admin/mails`, sent by nothing. Their
copy still names credits, badges and dashboards that do not exist.

### 03 · Welcome challenge (`welcomeChallenge`)

- **Proposal:** 01 + 3 working days; four actions in seven days to become a Howy Hero, 70 credits.
- **Missing:** credits, badges, a progress page, "pick your expertise topics".
- **Approach:** the actions are checkable from what exists (three copied mails, one question via
  `emails.colleague_id`); expertise topics need a way to pick topics per colleague.
- **Open:** is gamification wanted at all? Who funds the rewards credits buy?

### 07 · Trending topics (`weeklyTopics`)

- **Proposal:** every Friday, the top 3 topics with new items, lookups and trend, plus "your week".
- **Missing:** counting lookups per topic (citations in answers, per week), a weekly streak per colleague.
- **Approach:** new facts per `KnowledgeTopic` per week, plus answers citing them; "your week" from
  `emails.colleague_id`. Skip the mail when a week is too quiet.
- **Open:** minimum activity before sending; clash with 11 in the first week of a month.

### 08 · Challenge completed (`challengeCompleted`)

- **Proposal:** when all four challenge actions are done within the deadline.
- **Missing:** everything 03 needs.

### 09 · Howy asks (`howyAsks`)

- **Proposal:** a knowledge gap (unanswered question, outdated item) put to the person who knows the topic;
  "someone else knows better" and "not now"; a few times a month at most.
- **Missing:** a knowledge holder per topic, gap detection beyond `InterpretationOutcome::NotFound`, routing
  the answer back to the asker (also 05 variant B).
- **Approach:** derive a holder from who contributed most facts to a topic (`emails.colleague_id` →
  `KnowledgeFactSource`); a reply to the mail is recognised through its reference (step 3) and filed.
- **Open:** how often per person; may an admin choose the holder?

### 10 · Your knowledge helped (`knowledgeHelped`)

- **Proposal:** a colleague rated an answer built on your knowledge as valuable; bundled at most weekly.
- **Missing:** ratings (step 7), the line from a rating to the colleague: citation → fact → source mail →
  `emails.colleague_id`.
- **Approach:** a weekly job over the week's "valuable" ratings, one mail per contributing colleague.

### 11 · Monthly update (`monthlyUpdate`)

- **Proposal:** first working day of the month: company figures, top 3 topics, your contribution, gaps,
  contributors of the month (opt-in).
- **Missing:** a reuse score per topic, an opt-in for being named.
- **Approach:** the same counts as 07 over a month; gaps from `NotFound` questions.

### 12 · New colleague (`newColleague`)

- **Proposal:** a new employee's first working day; replaces 01 and 02 for them.
- **Today:** 01 covers anyone added later, since day 0 is per person.
- **Missing:** start date, role, team, buddy, a starter pack per role.
- **Approach:** an optional "new employee" flag with a start date when adding a colleague, and a starter pack
  as a saved set of topics.

### 13 · Team report (`teamReport`)

- **Proposal:** a monthly report for team leads, team figures only, with a variant under target.
- **Missing:** teams and managers.
- **Approach:** start as the engagement dashboard on the colleagues page for account admins, and a monthly
  admin summary mail from the same figures.

### 05 variant B · Forwarded to the knowledge holder

- **Proposal:** when Howy has no answer, it forwards the question to whoever knows most, and mails the asker
  once there is an answer.
- **Missing:** the same knowledge holder as 09.

### Questions about Howy itself

A reply to a flow mail ("how do I…?", "what do you do with my mail?") is recognised as a reply to Howy
(step 3). The next step is to answer those from a built-in Howy FAQ rather than the account's knowledge base.
