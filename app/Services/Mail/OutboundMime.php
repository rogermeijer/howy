<?php

namespace App\Services\Mail;

use App\Models\OutboundMail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email as MimeEmail;

/**
 * A logged mail as the raw message Gmail sends: built from the log row alone,
 * so a mail that waited for its mailbox goes out as it was written.
 */
class OutboundMime
{
    /** Headers that carry message ids, not text. */
    private const array ID_HEADERS = ['In-Reply-To', 'References'];

    public function compose(OutboundMail $mail, string $from): string
    {
        $message = (new MimeEmail)
            ->from(new Address($from))
            ->to(new Address($mail->recipient))
            ->subject((string) $mail->subject)
            ->text($mail->text)
            ->html($mail->html);

        if (($mail->cc ?? []) !== []) {
            $message->cc(...array_map(fn (string $address): Address => new Address($address), $mail->cc ?? []));
        }

        $headers = $message->getHeaders();

        if ($mail->reference !== null) {
            $headers->addIdHeader('Message-ID', $this->ids($mail->reference));
        }

        foreach ($mail->headers ?? [] as $name => $value) {
            in_array($name, self::ID_HEADERS, true)
                ? $headers->addIdHeader($name, $this->ids($value))
                : $headers->addTextHeader($name, $value);
        }

        return $message->toString();
    }

    /**
     * Message ids without their angle brackets, as Symfony wants them.
     *
     * @return list<string>
     */
    private function ids(string $header): array
    {
        preg_match_all('/<([^>]+)>/', $header, $matches);

        return $matches[1] !== [] ? $matches[1] : [trim($header, '<> ')];
    }
}
