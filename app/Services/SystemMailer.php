<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

class SystemMailer
{
    public function send(string $to, string $subject, string $message, ?string $replyTo = null): array
    {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Destinatário de e-mail inválido.'];
        }

        $fromEmail = trim((string) Config::get('MAIL_FROM_EMAIL', ''));
        if ($fromEmail === '') {
            $host = parse_url((string) Config::get('APP_URL', ''), PHP_URL_HOST);
            $host = is_string($host) && $host !== '' ? $host : 'localhost';
            $fromEmail = 'noreply@' . preg_replace('/[^a-z0-9\.\-]+/i', '', $host);
        }
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'MAIL_FROM_EMAIL inválido.'];
        }

        $fromName = trim((string) Config::get('MAIL_FROM_NAME', (string) Config::get('APP_NAME', 'TRAXTER CRM')));
        if ($fromName === '') {
            $fromName = 'TRAXTER CRM';
        }

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $this->formatMailbox($fromEmail, $fromName),
        ];
        if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $ok = @mail($to, $encodedSubject, $message, implode("\r\n", $headers));
        return [
            'ok' => $ok,
            'error' => $ok ? null : 'Falha no transporte de e-mail com mail().',
        ];
    }

    private function formatMailbox(string $email, string $name): string
    {
        $safeName = str_replace(['"', "\r", "\n"], '', $name);
        return '"' . $safeName . '" <' . $email . '>';
    }
}
