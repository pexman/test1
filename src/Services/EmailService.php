<?php

declare(strict_types=1);

class EmailService
{
    private EmailTemplateRepository $templateRepository;
    private ActivityLogRepository $activityLogRepository;

    public function __construct(EmailTemplateRepository $templateRepository, ActivityLogRepository $activityLogRepository)
    {
        $this->templateRepository = $templateRepository;
        $this->activityLogRepository = $activityLogRepository;
    }

    public function sendTemplate(string $templateKey, array $variables, string $toEmail): void
    {
        $template = $this->templateRepository->findActiveByKey($templateKey);
        if (!$template) {
            throw new RuntimeException('Template not found');
        }

        $subject = $this->parseVariables($template['subject'], $variables);
        $body = $this->parseVariables($template['body_html'], $variables);

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        if (!empty(SMTP_USER)) {
            $headers .= 'From: ' . SMTP_USER . "\r\n";
        }

        if (!mail($toEmail, $subject, $body, $headers)) {
            throw new RuntimeException('Failed to send email');
        }

        $this->activityLogRepository->log('email', 'sent', [
            'user_identifier' => $toEmail,
            'metadata' => ['template' => $templateKey],
        ]);
    }

    private function parseVariables(string $content, array $variables): string
    {
        return (string) preg_replace_callback('/{{\s*(.*?)\s*}}/', static function ($matches) use ($variables) {
            $key = $matches[1];
            return htmlspecialchars((string) ($variables[$key] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }, $content);
    }
}
