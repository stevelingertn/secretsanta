<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Contestant login codes: random, nonsequential, stored encrypted (for admin reprint)
 * plus an HMAC lookup hash keyed from APP_KEY. Plaintext is never stored or logged.
 */
class LoginCodeService
{
    /** No 0/O, 1/I/L to avoid misreading printed ballots. 31 symbols x 10 chars = ~49.5 bits. */
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const LENGTH = 10;

    public function generate(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }

    public function normalize(string $input): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($input)) ?? '';
    }

    public function format(string $code): string
    {
        return substr($code, 0, 5).'-'.substr($code, 5);
    }

    public function hash(string $normalized): string
    {
        return hash_hmac('sha256', $normalized, $this->lookupKey());
    }

    /** Sets a fresh code on a participant (caller saves). Returns the plain code. */
    public function assignNew(Participant $participant): string
    {
        do {
            $code = $this->generate();
            $hash = $this->hash($code);
        } while (Participant::query()->where('login_code_hash', $hash)->exists());

        $participant->login_code_encrypted = Crypt::encryptString($code);
        $participant->login_code_hash = $hash;

        return $code;
    }

    /** Admin-only: formatted plain code for printing a ballot. */
    public function reveal(Participant $participant): string
    {
        return $this->format(Crypt::decryptString($participant->login_code_encrypted));
    }

    public function findParticipant(string $input, Event $event): ?Participant
    {
        $normalized = $this->normalize($input);
        if (strlen($normalized) !== self::LENGTH) {
            return null;
        }

        return Participant::query()
            ->where('event_id', $event->id)
            ->where('login_code_hash', $this->hash($normalized))
            ->first();
    }

    /** New code; the old one stops working and existing contestant sessions end. Votes and allowance are untouched. */
    public function rotate(Participant $participant, User $admin): void
    {
        DB::transaction(function () use ($participant, $admin) {
            $locked = Participant::query()->whereKey($participant->id)->lockForUpdate()->firstOrFail();
            $this->assignNew($locked);
            $locked->session_version = $locked->session_version + 1;
            $locked->code_rotated_at = now();
            $locked->save();

            AuditLog::record('login_code.rotated', $locked->event, $admin, $locked);
            $participant->setRawAttributes($locked->getAttributes(), true);
        });
    }

    private function lookupKey(): string
    {
        return hash_hmac('sha256', 'secret-santa-login-code-lookup', (string) config('app.key'), true);
    }
}
