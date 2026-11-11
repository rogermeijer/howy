# Adoption mails

The mails that help people start with Howy and keep using it: a welcome, how it works, a thank-you for the
first contribution, a nudge, and later a weekly and monthly rhythm. They grew out of a proposal for 13 mails
(kept as received in [`docs/adoption-mails/proposal/`](adoption-mails/proposal/), with its flow overview in
`mailflow.html`). This file says what is built, what is parked, and what each parked mail still needs.

## Status

| Step | What                                                                                                       | State                        |
| ---- | ---------------------------------------------------------------------------------------------------------- | ---------------------------- |
| 1    | One mail frame, every mail designed (live, system and parked), preview at `/admin/mails`                   | **Built, design signed off** |
| 2    | Colleagues and mailbox rules (`Colleague`, `MailboxRule`, replacing `mailbox_senders` and `domain_access`) | **Built**                    |
| 3    | `HowyMailer`, `OutboundMail` (mail log, pause queue, reply recognition)                                    | **Built**                    |
| 4    | The account's Live switch, and 01                                                                          | **Built**                    |
| 5    | 02 and 06                                                                                                  | **Built**                    |
| 6    | 04                                                                                                         | **Built**                    |
| 7    | Links without login (rating 05, adjusting 04, unsubscribing)                                               | **Built**                    |

Everything in the plan is built on the branch: the existing mails in the new frame and voice, colleagues with
their page, the mail log, the Live switch, 01, 02, 04 and 06, and the links without login. What is left before
going out is under "Before sign-off".

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

## Colleagues

A colleague (`App\Models\Colleague`, table `colleagues`) is someone who mails Howy, one per address per account.
For nearly every account they work at the company, and they are who the flow mails are for. Most never log in;
`user_id` links one who is also a member of the account.

- **Made** when an admin lists their address on a mailbox (a rule of their own), when their mail comes in (push
  or poll, never an import) and the domain rule that lets them in creates colleagues (the own domain always;
  another domain when an admin switches it on), or by hand on the colleagues page. Someone the rules ignore is
  never made one, except by a rule of their own that says ignore.
- **Kept up** by `Colleagues::record()` from `StoreGmailMessage`: `emails.colleague_id`, `first_seen_at`,
  `last_mail_at`, the name when it was empty, the member with that address.
- **Howy mails** (`receives_mail`, "Receives Howy mails") start on for colleagues at the company's domain (the
  mailbox's own, or the account's `domain`), for members, and for anyone added by hand; off for others listed
  one by one, such as a customer's address. Their home mailbox (`home_mailbox_id`) is the first they mailed: the
  flow mails will come from there.
- **The page** (`/colleagues`, account admins, in the top bar): search, filter on where their Howy mails stand
  and on what Howy does with their mail on their home mailbox, and per person their access per mailbox (a rule
  of their own, or their domain's), their mail, and the steps of their flow (`welcomed_at`, `explained_at`,
  `first_saved_at`, `nudged_at`, `opted_out_at`; nothing sets those until step 4).

What Howy may do with mail is the mailbox's rules, now per colleague or per domain; see "Sender rules" in
[`knowledge-base.md`](knowledge-base.md).

**After deploying step 2**, once: `php artisan colleagues:backfill`. It makes colleagues from the mail stored so
far by the same rules, oldest first, and links each mail to its sender. It only reads mail not linked yet, so it
is safe to run again.

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
- **Links a recipient can open.** Every link in a mail is built with `MailUrl`, on `APP_PUBLIC_URL` when set,
  else `APP_URL`; a signed link is signed for that address. Locally `APP_URL` is a `.test` host: a welcome
  linking to `howyknows.test` was silently dropped by the recipient's spam filter (11 November 2026), while
  the same mail linking to the real domain arrived. Set `APP_PUBLIC_URL` to the ngrok address there.
- **Only what the reader's access allows.** Someone Howy only learns from is never told to ask it something,
  and gets a note saying so; someone it only answers is never told to copy it in.
- **Cc and To as Howy actually treats them.** In Cc, Howy listens and learns, and stays quiet: nobody on the
  thread hears from it. In To, Howy responds when it knows something relevant, and learns from what it is
  sent (telling the sender when that contradicts what it knows). The cards in `MailContent::rules()` and mail
  02 say exactly this.

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
- **The mailbox pauses everything.** When it needs reconnecting, flow mails and answers wait and go once it
  is connected again. There is no fallback to another sender. A mailbox an admin disconnected sends nothing
  again: its mails are skipped.
- **Working days** are Monday to Friday in the account's timezone; public holidays are not counted yet.
  Scheduled mails go between `ADOPTION_SEND_FROM_HOUR` and `ADOPTION_SEND_UNTIL_HOUR`. A welcome sent by
  hand goes at once.
- **Unsubscribing stops flow mails, never answers.** A colleague can also be switched off by an admin
  ("Receives Howy mails").
- **Every mail Howy sends carries a signed reference** in its `Message-ID`, so a reply to it is recognised as a
  reply to Howy. Later this lets Howy answer questions about Howy itself. See "Sending and the mail log".

| Variable                               | Default | Meaning                                            |
| -------------------------------------- | ------- | -------------------------------------------------- |
| `ADOPTION_HOW_IT_WORKS_AFTER_WORKDAYS` | 2       | Working days between 01 and 02                     |
| `ADOPTION_NUDGE_AFTER_WORKDAYS`        | 5       | Working days between 01 and 06                     |
| `ADOPTION_SEND_FROM_HOUR`              | 9       | Start of the sending window, account's local time  |
| `ADOPTION_SEND_UNTIL_HOUR`             | 17      | End of the sending window                          |
| `MAIL_LINK_HOURS`                      | 48      | How long a link in a mail works without logging in |

## Live and the welcome

`accounts.live_at` is the start signal (`Account::isLive()`), set by an account admin only: _Settings → Howy
mails_ (`LiveController`), or at the end of setup. Every account starts not live. Going live shows who the
welcome would go to now (`Welcomes::waiting()`: colleagues who get Howy mails, are not ignored by the rules of
their home mailbox, and have not had it), all ticked; the welcome goes to those kept on the list at once, at
any hour. Pausing (`live_at` null) holds Howy's own mails, also those already waiting for a mailbox, until it
is live again. Answers never wait for it.

After going live a colleague gets the welcome once (`Welcomes::send()`, through `FlowSender`):

- with their first mail to Howy (`AdoptionTriggers::afterInterpretation()`, from `InterpretEmail`; never from
  an import). That mail is their day 0;
- when an admin adds them on the colleagues page ("Send the welcome mail now", on by default);
- when an admin lists their address on a mailbox ("Send the welcome mail to new colleagues", on by default);
  only those who get Howy mails, so a customer listed one by one gets nothing;
- by hand, from their page, while they have not had it.

`FlowSender` writes the mail and logs it at once, as not sent yet, and the `DeliverOutboundMail` job sends it:
what is underway shows straight away, on the colleague's page ("On its way") and in the log. It locks the
colleague's row while it decides, so two first mails read at once, or a button clicked twice, log it once. The
mail is written for them (`FlowMailComposer`): their first name, the access of their home mailbox, in a
member's own language or the account's.

## How it works and the nudge

`adoption:send` (hourly) writes the follow-ups to the welcome that are due (`FollowUps::sendDue()`), only while
the account is live and only in working time (`WorkingTime`: Monday to Friday, between
`ADOPTION_SEND_FROM_HOUR` and `ADOPTION_SEND_UNTIL_HOUR`, in the account's timezone):

- **How it works (02)**: `ADOPTION_HOW_IT_WORKS_AFTER_WORKDAYS` working days after the welcome went, at the same
  local time. Welcomed on Friday at ten, with 2 that is Tuesday at ten.
- **The nudge (06)**: `ADOPTION_NUDGE_AFTER_WORKDAYS` working days after the welcome, only for someone Howy learns
  from (on their home mailbox) who has not mailed it since: `last_mail_at` empty, or before `welcomed_at`. The
  mail that brought the welcome came before it, so it does not count.

Each goes once (`explained_at`, `nudged_at`), to someone who still gets Howy mails, and one of Howy's own mails a
day at most: after a pause the rest follows on the next working days. The colleague's page shows when each is
planned. `HowyMailer::deliver()` takes a lock per mail, so the hourly command and a job that reach the same mail
at once send it once.

## Saved!

The first mail after the welcome that Howy files something new from brings its sender a thanks (04,
`AdoptionTriggers`, after `InterpretEmail`): to them alone, as a message of its own and never in the thread,
where customers and suppliers often are. It shows up to three of the new statements (and how many more), the
mail's subject, and that an admin looks it over first when it waits for approval. Howy's text is fingerprinted
with the mail, so quoted under a reply it is not news. The mail that brought the welcome is day 0 and gets no
thanks: the next contribution does. Once (`first_saved_at`), only while live, only to someone who gets Howy
mails. Its button opens what Howy took from the mail, to adjust (see "Links without login").

## Links without login

A link in a mail opens a page without logging in (`/m/…`, `App\Http\Controllers\MailLinks`). Its token is made
by `MailLinks`: the account, what it opens, whom it went to, the mail's language and until when it works,
encrypted with AES-256-GCM under a key derived from the app key. How the account in it is trusted, and why that
is no breach of "never trust an account id from input", is in "Mail links" in
[`multi-tenancy.md`](multi-tenancy.md). The page is in the language of the mail. A link works for
`MAIL_LINK_HOURS` (48); an expired one offers a new one by mail, sent only to whom it went, from the mailbox
the mail came from, three times an hour at most, and not while that mailbox needs reconnecting.

- **Unsubscribe.** Every one of Howy's own mails carries the link in its footer and as `List-Unsubscribe` with
  `List-Unsubscribe-Post: List-Unsubscribe=One-Click`, so a mail client can unsubscribe in one click, without a
  session. The page unsubscribes, or brings someone back. This link never expires. Answers still go out.
- **Rating an answer (05).** Under every answer: Yes, Not quite, Outdated. Each opens the page with that choice
  made and room for a note; nothing is saved until it is sent, because link scanners in mail (Outlook's Safe
  Links) open every link. Kept on the interpretation (`rating`, `rating_note`, `rated_at`), shown under the
  answer on the mail page and as a tag in the inbox.
- **Adjusting what was saved (04).** The button in Saved! opens what Howy took from the mail. Per statement the
  sender keeps it, takes it back, or says it is not right with a correction (`SenderFeedback`). One still
  waiting for approval is rejected at once, as by the sender. One already in the knowledge base, and any
  correction, waits for an admin (`sender_feedback`, `needs_review`): it shows on the mail and in the approvals,
  counts in their badge, and is marked done there.

## Sending and the mail log

`App\Services\Mail\HowyMailer` is the one place Howy sends from a mailbox: the flow mails
(`toColleague()`, from the colleague's home mailbox, a message of its own) and the replies in a thread
(`reply()`, which `ThreadReplier` uses for answers, not-found and conflict replies). Every mail is first logged
as an `OutboundMail` (`outbound_mails`), with everything needed to send it later, and then:

| Status    | When                                                                                                                                                |
| --------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| `sent`    | Gmail took it. A flow mail sets the colleague's step (`welcomed_at`, `explained_at`, …).                                                            |
| `pending` | Not sent yet: about to go, or held while the mailbox needs reconnecting or Howy is paused. `adoption:send` (hourly) sends it once nothing holds it. |
| `skipped` | It may not go: unsubscribed, Howy mails off, ignored by the rules, not allowed to write to, no mailbox.                                             |
| `failed`  | Gmail refused it; the error is kept.                                                                                                                |

A waiting flow mail goes only in working time; a waiting reply goes at once, and its interpretation moves from
`reply_status` `waiting` to `sent`. Whether a flow mail may go is checked again when it goes, so someone who
unsubscribed meanwhile does not get it.

**References.** A mail's `Message-ID` is `<howy.{id}.{nonce}.{signature}@{mailbox domain}>` (`MailReferences`).
The nonce keeps it unique for good: a database started over under the same app key would otherwise hand out an
id it gave out before, and Gmail silently drops a mail whose Message-ID the recipient already has (it shows as
sent, and never arrives). The signature is an HMAC over the account, the mail and the nonce with the app key.
After sending, Howy asks Gmail which Message-ID the message carries and keeps it (`message_id_header`): from a
gmail.com mailbox Gmail puts its own in (`<…@mail.gmail.com>`), so there that one is what replies name.
`ReplyRecognizer`, from
`StoreGmailMessage`, links a mail that came in to the mail it replies to (`emails.outbound_mail_id`) by, in
order: a signed reference in `In-Reply-To` or `References`; the Message-ID Gmail sent Howy's mail with; the
thread of a flow mail, which starts a thread of its own. Only within the mailbox and the account. A reply is
read with what Howy wrote as its context, and the mail page says what it replies to.

**The log** is the _Sent mails_ tab on the colleagues page (`/colleagues/sent`, account admins): filter on
recipient, kind, status and period; open a mail to see it as its recipient got it (the stored HTML, in a frame
nothing in which runs or loads from elsewhere), why it did not go, and the replies it got. A colleague's page
lists what Howy sent them. One of Howy's own mails that went, or failed, can be sent again ("Send again", in the log and per step on
the colleague's page; `FlowSender::resend()`): written afresh, so it says what that mail says today, with today's
links and access, from their home mailbox, and logged at once with a Message-ID of its own; one on its way at a
time. Replies in someone's thread are never sent again.

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
- With a connected test mailbox: send a flow mail and an answer, reply from Gmail, Outlook and Apple Mail, and
  check on the mail page that each reply is recognised. From a gmail.com mailbox Gmail puts its own
  `Message-ID` in (seen on 11 November 2026); check whether a Google Workspace mailbox on its own domain keeps
  Howy's (`reference` equals `message_id_header`).
- Delivery: send the welcome to addresses at Gmail, Outlook.com and a hosted mail server, and check it lands
  in the inbox, not in spam or quarantine.

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
